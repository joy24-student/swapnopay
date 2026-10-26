package com.example.service

import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.os.Build
import android.telephony.SmsManager
import android.telephony.SubscriptionInfo
import android.telephony.SubscriptionManager
import android.util.Log
import androidx.core.content.ContextCompat
import com.example.data.local.AppDatabase
import com.example.data.local.OutboxSmsEntity
import kotlinx.coroutines.*
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

data class SimCardInfo(
    val slotIndex: Int,          // 0 = SIM 1, 1 = SIM 2
    val subscriptionId: Int,
    val carrierName: String,
    val displayName: String,
    val isDefault: Boolean = false
)

object SmsGatewayEngine {

    private const val TAG = "SmsGatewayEngine"

    private val engineJob = SupervisorJob()
    private val engineScope = CoroutineScope(Dispatchers.IO + engineJob)

    @Volatile
    var isDispatcherRunning = false
        private set

    /**
     * Inspect active physical SIM cards inserted into the device.
     * Supports Dual SIM detection across Grameenphone, Robi, Banglalink, Teletalk, Airtel.
     */
    fun getAvailableSimCards(context: Context): List<SimCardInfo> {
        val simList = mutableListOf<SimCardInfo>()
        try {
            if (ContextCompat.checkSelfPermission(context, android.Manifest.permission.READ_PHONE_STATE)
                != PackageManager.PERMISSION_GRANTED) {
                Log.w(TAG, "READ_PHONE_STATE not granted; returning system default SIM info.")
                simList.add(SimCardInfo(slotIndex = 0, subscriptionId = -1, carrierName = "Default SIM", displayName = "Primary SIM Slot", isDefault = true))
                return simList
            }

            val subManager = context.getSystemService(Context.TELEPHONY_SUBSCRIPTION_SERVICE) as? SubscriptionManager
                ?: (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP_MR1) SubscriptionManager.from(context) else null)
            if (subManager != null) {
                var activeSubs: List<SubscriptionInfo>? = null
                try {
                    activeSubs = subManager.activeSubscriptionInfoList
                } catch (e: SecurityException) {
                    Log.w(TAG, "SecurityException querying activeSubscriptionInfoList: ${e.message}")
                }
                if (!activeSubs.isNullOrEmpty()) {
                    for (info in activeSubs) {
                        val carrier = info.carrierName?.toString()?.ifBlank { null }
                            ?: info.displayName?.toString()?.ifBlank { null }
                            ?: "SIM ${info.simSlotIndex + 1}"
                        val display = "SIM ${info.simSlotIndex + 1} ($carrier)"
                        simList.add(
                            SimCardInfo(
                                slotIndex = info.simSlotIndex,
                                subscriptionId = info.subscriptionId,
                                carrierName = carrier,
                                displayName = display,
                                isDefault = info.simSlotIndex == 0
                            )
                        )
                    }
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error querying active SIM cards: ${e.message}")
        }

        if (simList.isEmpty()) {
            simList.add(SimCardInfo(slotIndex = 0, subscriptionId = -1, carrierName = "SIM 1 (Auto)", displayName = "Default Cellular SIM", isDefault = true))
        }
        return simList
    }

    /**
     * Obtains the target SmsManager instance for a selected subscription or default.
     */
    private fun getSmsManagerForSlot(context: Context, simSlot: Int): SmsManager {
        val simCards = getAvailableSimCards(context)
        val selectedSim = simCards.find { it.slotIndex == simSlot } ?: simCards.firstOrNull()
        Log.d(TAG, "Resolving SmsManager for requested simSlot: $simSlot -> matched: ${selectedSim?.displayName} (subId: ${selectedSim?.subscriptionId})")

        return if (selectedSim != null && selectedSim.subscriptionId != -1 && Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP_MR1) {
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                    context.getSystemService(SmsManager::class.java).createForSubscriptionId(selectedSim.subscriptionId)
                } else {
                    @Suppress("DEPRECATION")
                    SmsManager.getSmsManagerForSubscriptionId(selectedSim.subscriptionId)
                }
            } catch (e: Exception) {
                Log.w(TAG, "Failed creating subscription SmsManager for ${selectedSim.subscriptionId}: ${e.message}")
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    context.getSystemService(SmsManager::class.java)
                } else {
                    @Suppress("DEPRECATION")
                    SmsManager.getDefault()
                }
            }
        } else {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                context.getSystemService(SmsManager::class.java)
            } else {
                @Suppress("DEPRECATION")
                SmsManager.getDefault()
            }
        }
    }

    /**
     * Formats Bangladeshi or international phone numbers cleanly.
     * E.g.: "017XXXXXXXX", "+88017XXXXXXXX"
     */
    fun sanitizePhoneNumber(raw: String): String {
        val digits = raw.trim().replace(" ", "").replace("-", "").replace("(", "").replace(")", "")
        return if (digits.startsWith("01") && digits.length == 11) {
            "+88$digits"
        } else if (digits.startsWith("8801") && digits.length == 13) {
            "+$digits"
        } else {
            digits
        }
    }

    /**
     * Common USSD query codes for Bangladeshi cellular operators.
     * Allows merchant to check account balance and remaining SMS bundle.
     */
    fun getUssdCodesForCarrier(carrierName: String): List<Pair<String, String>> {
        val lower = carrierName.lowercase()
        return when {
            lower.contains("grameen") || lower.contains("gp") -> listOf(
                "Main Balance" to "*566#",
                "SMS Balance" to "*121*1*2#",
                "Internet Balance" to "*121*1*4#",
                "SIM Number" to "*2#"
            )
            lower.contains("banglalink") || lower.contains("bl") -> listOf(
                "Main Balance" to "*124#",
                "SMS Pack" to "*124*2#",
                "Internet" to "*5000*500#",
                "SIM Number" to "*511#"
            )
            lower.contains("robi") -> listOf(
                "Main Balance" to "*222#",
                "SMS Pack" to "*222*12#",
                "Internet" to "*3#",
                "SIM Number" to "*140*2*4#"
            )
            lower.contains("airtel") -> listOf(
                "Main Balance" to "*778#",
                "SMS Balance" to "*778*2#",
                "Internet" to "*3#",
                "SIM Number" to "*121*7*3#"
            )
            lower.contains("teletalk") -> listOf(
                "Main Balance" to "*152#",
                "SMS Balance" to "*152*1#",
                "SIM Number" to "*551#"
            )
            else -> listOf(
                "Balance Check" to "*566#",
                "SMS Balance" to "*121*1#",
                "SIM Number" to "*2#"
            )
        }
    }

    /**
     * Dials a USSD code safely using Intent.ACTION_DIAL on the merchant device.
     */
    fun dialUssd(context: Context, ussdCode: String) {
        try {
            val encoded = android.net.Uri.encode(ussdCode)
            val intent = Intent(Intent.ACTION_DIAL, android.net.Uri.parse("tel:$encoded")).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            context.startActivity(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Error dialing USSD code $ussdCode: ${e.message}")
        }
    }

    /**
     * Sends an individual SMS immediately using SmsManager.
     */
    fun sendDirectSms(
        context: Context,
        recipientPhone: String,
        message: String,
        simSlot: Int = 0,
        onResult: (Boolean, String?) -> Unit
    ) {
        if (ContextCompat.checkSelfPermission(context, android.Manifest.permission.SEND_SMS)
            != PackageManager.PERMISSION_GRANTED) {
            onResult(false, "SEND_SMS permission not granted on device")
            return
        }

        val cleanPhone = sanitizePhoneNumber(recipientPhone)
        if (cleanPhone.length < 10) {
            onResult(false, "Invalid recipient phone number: $recipientPhone")
            return
        }

        try {
            val smsManager = getSmsManagerForSlot(context, simSlot)
            val parts = smsManager.divideMessage(message)

            if (parts.size > 1) {
                smsManager.sendMultipartTextMessage(cleanPhone, null, parts, null, null)
            } else {
                smsManager.sendTextMessage(cleanPhone, null, message, null, null)
            }
            Log.d(TAG, "Direct SMS dispatched to $cleanPhone (${parts.size} part/s)")
            onResult(true, null)
        } catch (e: Exception) {
            Log.e(TAG, "Failed sending direct SMS to $cleanPhone: ${e.message}", e)
            onResult(false, e.localizedMessage ?: "Failed to dispatch SMS")
        }
    }

    /**
     * Starts the automated background SMS outbox queue worker.
     * Dequeues pending messages sequentially with an anti-spam delay (2.5 seconds)
     * to protect the SIM card from cellular operator spam blocks.
     */
    fun startOutboxQueueProcessor(context: Context, merchantId: String, throttleDelayMs: Long = 2500L) {
        if (isDispatcherRunning) return
        isDispatcherRunning = true

        engineScope.launch {
            Log.d(TAG, "SMS Gateway Outbox Dispatcher started for merchant: $merchantId (Delay: ${throttleDelayMs}ms)")
            val dao = AppDatabase.getDatabase(context).appDao()

            while (isActive && isDispatcherRunning) {
                try {
                    val pendingList = dao.getPendingOutboxSms(merchantId, limit = 10)
                    if (pendingList.isNotEmpty()) {
                        for (item in pendingList) {
                            if (!isActive || !isDispatcherRunning) break

                            dao.updateOutboxSmsStatus(item.id, "SENDING")
                            var sendSuccess = false
                            var sendError: String? = null

                            sendDirectSms(
                                context = context,
                                recipientPhone = item.recipientPhone,
                                message = item.messageText,
                                simSlot = item.simSlot
                            ) { success, err ->
                                sendSuccess = success
                                sendError = err
                            }

                            val now = System.currentTimeMillis()
                            if (sendSuccess) {
                                dao.updateOutboxSmsStatus(item.id, "SENT", sentAt = now, errorMessage = null)
                                Log.d(TAG, "Outbox item ${item.id} sent successfully.")

                                // If this was an external website API job, notify backend
                                if (!item.externalJobId.isNullOrBlank()) {
                                    reportGatewayJobStatus(item.externalJobId, "SENT", null)
                                }
                            } else {
                                dao.updateOutboxSmsStatus(item.id, "FAILED", errorMessage = sendError)
                                Log.w(TAG, "Outbox item ${item.id} failed: $sendError")

                                if (!item.externalJobId.isNullOrBlank()) {
                                    reportGatewayJobStatus(item.externalJobId, "FAILED", sendError)
                                }
                            }

                            // Anti-spam carrier safety throttle delay
                            delay(throttleDelayMs)
                        }
                    } else {
                        // Queue idle; wait before polling local database again
                        delay(4000L)
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error in outbox queue processing loop: ${e.message}")
                    delay(5000L)
                }
            }
        }
    }

    fun stopOutboxQueueProcessor() {
        isDispatcherRunning = false
    }

    /**
     * Queries the SwapnoPay VPS backend for pending external website SMS requests
     * (e.g. OTP verification requests, order confirmations) and enqueues them into Room.
     */
    suspend fun syncExternalGatewayJobs(context: Context, merchantId: String, apiKey: String): Int {
        if (merchantId.isBlank()) return 0
        return withContext(Dispatchers.IO) {
            var syncedCount = 0
            val candidateHosts = listOf("https://api.swapnopay.top", "http://10.0.2.2:4000")
            for (host in candidateHosts) {
                var conn: HttpURLConnection? = null
                try {
                    val url = URL("$host/v1/sms-gateway/device/pending?merchant_id=$merchantId")
                    conn = url.openConnection() as HttpURLConnection
                    conn.requestMethod = "GET"
                    conn.connectTimeout = 4000
                    conn.readTimeout = 4000
                    if (apiKey.isNotBlank()) {
                        conn.setRequestProperty("X-API-Key", apiKey)
                    }

                    if (conn.responseCode == 200) {
                        val respText = conn.inputStream.bufferedReader().readText()
                        val json = JSONObject(respText)
                        val jobsArray = json.optJSONArray("jobs") ?: JSONArray()
                        if (jobsArray.length() > 0) {
                            val dao = AppDatabase.getDatabase(context).appDao()
                            val entities = mutableListOf<OutboxSmsEntity>()

                            val prefs = context.getSharedPreferences("sms_gateway_prefs", Context.MODE_PRIVATE)
                            val defaultSimSlot = prefs.getInt("selected_sim_slot", 0)

                            for (i in 0 until jobsArray.length()) {
                                val jobObj = jobsArray.getJSONObject(i)
                                val jobId = jobObj.optString("job_id")
                                val phone = jobObj.optString("phone")
                                val msg = jobObj.optString("message")
                                val type = jobObj.optString("type", "GATEWAY_CUSTOM")

                                val targetSlot = if (jobObj.has("sim_slot") && !jobObj.isNull("sim_slot")) {
                                    val raw = jobObj.optInt("sim_slot", defaultSimSlot)
                                    if (raw in 1..2) raw - 1 else raw
                                } else {
                                    defaultSimSlot
                                }

                                if (phone.isNotBlank() && msg.isNotBlank()) {
                                    entities.add(
                                        OutboxSmsEntity(
                                            id = "gw_$jobId",
                                            merchantId = merchantId,
                                            recipientPhone = phone,
                                            messageText = msg,
                                            smsType = type,
                                            simSlot = targetSlot,
                                            status = "QUEUED",
                                            externalJobId = jobId,
                                            partsCount = (msg.length / 160) + 1,
                                            createdAt = System.currentTimeMillis()
                                        )
                                    )
                                }
                            }

                            if (entities.isNotEmpty()) {
                                dao.insertOutboxSmsList(entities)
                                syncedCount = entities.size
                                Log.d(TAG, "Enqueued ${entities.size} external gateway SMS job(s) for local SIM dispatch.")
                            }
                        }
                        break
                    }
                } catch (_: Exception) {
                    // Try next endpoint candidate
                } finally {
                    conn?.disconnect()
                }
            }
            syncedCount
        }
    }

    /**
     * Reports SMS execution result back to backend for webhook dispatches.
     */
    private fun reportGatewayJobStatus(jobId: String, status: String, error: String?) {
        engineScope.launch {
            val candidateHosts = listOf("https://api.swapnopay.top", "http://10.0.2.2:4000")
            for (host in candidateHosts) {
                var conn: HttpURLConnection? = null
                try {
                    val url = URL("$host/v1/sms-gateway/device/status")
                    conn = url.openConnection() as HttpURLConnection
                    conn.requestMethod = "POST"
                    conn.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                    conn.connectTimeout = 4000
                    conn.readTimeout = 4000
                    conn.doOutput = true

                    val payload = JSONObject().apply {
                        put("job_id", jobId)
                        put("status", status)
                        put("error", error ?: "")
                        put("sent_at", System.currentTimeMillis())
                    }
                    conn.outputStream.bufferedWriter().use { it.write(payload.toString()) }
                    if (conn.responseCode in 200..299) {
                        break
                    }
                } catch (_: Exception) {
                } finally {
                    conn?.disconnect()
                }
            }
        }
    }
}
