package com.example.service

import android.app.*
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Build
import android.os.IBinder
import android.provider.Telephony
import android.util.Log
import androidx.core.app.NotificationCompat
import com.example.MainActivity
import com.example.data.repository.AppRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.withContext
import com.example.NotificationHelper
import com.example.R

class SmsMonitoringService : Service() {

    private val job = SupervisorJob()
    private val scope = CoroutineScope(Dispatchers.IO + job)
    private lateinit var repository: AppRepository
    private var smsReceiver: BroadcastReceiver? = null

    companion object {
        const val CHANNEL_ID = "SmsMonitoringChannel"
        const val NOTIFICATION_ID = 101
    }

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
        promoteToForeground()

        repository = AppRepository(applicationContext)
        startSmsReceiver()
        repository.startSmsQueueAutoRetry(scope)
        startHeartbeatLoop()

        scope.launch {
            val activeProfile = repository.getActiveSupabaseProfile()
            val merchantId = activeProfile?.id ?: "00000000-0000-0000-0000-000000000001"
            SmsGatewayEngine.startOutboxQueueProcessor(applicationContext, merchantId)
        }

        Log.d("SmsMonitoringService", "Service created, foreground notification active, and SMS monitoring/gateway initialized.")
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        promoteToForeground()
        return START_STICKY
    }

    private fun promoteToForeground() {
        try {
            val notification = createNotification()
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                androidx.core.app.ServiceCompat.startForeground(
                    this,
                    NOTIFICATION_ID,
                    notification,
                    android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC
                )
            } else {
                startForeground(NOTIFICATION_ID, notification)
            }
        } catch (e: Exception) {
            Log.e("SmsMonitoringService", "Failed to promote service to foreground: ${e.message}", e)
            try {
                stopSelf()
            } catch (_: Exception) {}
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun startSmsReceiver() {
        if (androidx.core.content.ContextCompat.checkSelfPermission(
                this,
                android.Manifest.permission.RECEIVE_SMS
            ) != android.content.pm.PackageManager.PERMISSION_GRANTED
        ) {
            Log.w("SmsMonitoringService", "RECEIVE_SMS permission not granted; receiver registration aborted.")
            return
        }

        smsReceiver = object : BroadcastReceiver() {
            override fun onReceive(context: Context, intent: Intent) {
                if (intent.action == Telephony.Sms.Intents.SMS_RECEIVED_ACTION) {
                    val messages = Telephony.Sms.Intents.getMessagesFromIntent(intent) ?: return
                    if (messages.isEmpty()) return
                    
                    // Group multipart SMS pieces by sender address and concatenate their text
                    val fullBodyMap = LinkedHashMap<String, StringBuilder>()
                    for (msg in messages) {
                        val sender = msg.originatingAddress ?: continue
                        val body = msg.messageBody ?: ""
                        fullBodyMap.getOrPut(sender) { StringBuilder() }.append(body)
                    }

                    Log.d("SmsMonitoringService", "Incoming SMS queued for local validation.")
                    for ((sender, bodyBuilder) in fullBodyMap) {
                        val fullBody = bodyBuilder.toString()
                        scope.launch {
                            repository.processIncomingSms(sender, fullBody)
                        }
                    }
                }
            }
        }
        
        try {
            androidx.core.content.ContextCompat.registerReceiver(
                this,
                smsReceiver,
                IntentFilter(Telephony.Sms.Intents.SMS_RECEIVED_ACTION),
                androidx.core.content.ContextCompat.RECEIVER_EXPORTED
            )
        } catch (e: Exception) {
            Log.e("SmsMonitoringService", "Failed to register dynamic SMS receiver: ${e.message}", e)
        }
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "SMS Monitoring Service",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Active background receiver for mobile banking SMS transactions"
            }
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }
    }

    private fun createNotification(): Notification {
        val pendingIntent = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("SwapnoPay Merchant SMS Active")
            .setContentText("Automated bKash, Nagad, and Rocket monitoring is running")
            .setSmallIcon(R.drawable.ic_stat_notification)
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .build()
    }

    private fun startHeartbeatLoop() {
        scope.launch {
            // Immediate registration upon service boot
            try {
                repository.sendDeviceHeartbeat(applicationContext)
            } catch (e: Exception) {
                Log.w("SmsMonitoringService", "Initial device heartbeat error: ${e.message}")
            }

            // Periodic heartbeat every 60 seconds to keep device status active in backend
            while (isActive) {
                delay(60_000L)
                try {
                    val bm = applicationContext.getSystemService(Context.BATTERY_SERVICE) as? android.os.BatteryManager
                    val batteryPct = bm?.getIntProperty(android.os.BatteryManager.BATTERY_PROPERTY_CAPACITY) ?: 100

                    repository.sendDeviceHeartbeat(applicationContext, batteryPct)

                    val p = repository.getActiveSupabaseProfile()
                    val mId = p?.id ?: "00000000-0000-0000-0000-000000000001"
                    SmsGatewayEngine.syncExternalGatewayJobs(applicationContext, mId, "")

                    // Background check for new admin broadcast notices and form submissions when user not in app
                    checkBackgroundAdminNoticesAndForms(applicationContext, repository)

                    // Daily Automated Due Reminder Background Trigger
                    val prefs = applicationContext.getSharedPreferences("sms_gateway_prefs", Context.MODE_PRIVATE)
                    val isScheduleActive = prefs.getBoolean("auto_due_schedule_active", false)
                    if (isScheduleActive) {
                        val targetHour = prefs.getInt("auto_due_schedule_hour", 10)
                        val cal = java.util.Calendar.getInstance()
                        val currentHour = cal.get(java.util.Calendar.HOUR_OF_DAY)
                        val todayStr = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).format(java.util.Date())
                        val lastRun = prefs.getString("last_auto_due_run_date", null)

                        if (currentHour == targetHour && lastRun != todayStr) {
                            prefs.edit().putString("last_auto_due_run_date", todayStr).apply()
                            val minAmount = prefs.getFloat("auto_due_min_amount", 100f).toDouble()
                            val dao = com.example.data.local.AppDatabase.getDatabase(applicationContext).appDao()
                            val debtors = dao.getCustomersWithDue(mId).filter { it.currentBalance >= minAmount }
                            if (debtors.isNotEmpty()) {
                                val template = prefs.getString("due_sms_template", null)
                                    ?: "প্রিয় {name}, {store}-এ আপনার বাকি {due} টাকা পরিশোধের অনুরোধ জানাচ্ছি। ধন্যবাদ।"
                                val storeName = p?.businessName?.ifBlank { "SwapnoPay Store" } ?: "SwapnoPay Store"
                                val simSlot = prefs.getInt("selected_sim_slot", 0)

                                val entities = debtors.map { debtor ->
                                    val dueFormatted = String.format(java.util.Locale.US, "%.0f", debtor.currentBalance)
                                    val msg = template
                                        .replace("{name}", debtor.name)
                                        .replace("{due}", dueFormatted)
                                        .replace("{store}", storeName)
                                        .replace("{phone}", debtor.phone)

                                    com.example.data.local.OutboxSmsEntity(
                                        merchantId = mId,
                                        recipientPhone = debtor.phone,
                                        messageText = msg,
                                        smsType = "DUE_REMINDER",
                                        simSlot = simSlot,
                                        status = "QUEUED",
                                        customerId = debtor.id,
                                        partsCount = (msg.length / 160) + 1
                                    )
                                }
                                dao.insertOutboxSmsList(entities)
                                Log.d("SmsMonitoringService", "Auto-scheduled due reminders queued for ${entities.size} customers.")
                            }
                        }
                    }
                } catch (e: Exception) {
                    Log.w("SmsMonitoringService", "Periodic device heartbeat error: ${e.message}")
                }
            }
        }
    }

    private suspend fun checkBackgroundAdminNoticesAndForms(context: Context, repository: AppRepository) {
        withContext(Dispatchers.IO) {
            try {
                // 1. Check central admin notices / broadcast alerts
                val noticeUrl = java.net.URL("https://api.swapnopay.top/v1/system-notice")
                val conn = noticeUrl.openConnection() as java.net.HttpURLConnection
                conn.requestMethod = "GET"
                conn.connectTimeout = 6000
                conn.readTimeout = 6000
                if (conn.responseCode in 200..299) {
                    val body = conn.inputStream.bufferedReader().use { it.readText() }
                    if (body.isNotBlank()) {
                        val json = org.json.JSONObject(body)
                        val latestObj = json.optJSONObject("latest_broadcast")
                        val systemNotice = json.optString("system_notice").takeIf { it.isNotBlank() }

                        if (latestObj != null) {
                            val bId = latestObj.optString("batch_id").ifBlank { latestObj.optString("id") }.ifBlank { "broadcast_${latestObj.hashCode()}" }
                            val bTitle = latestObj.optString("title").ifBlank { "📢 অ্যাডমিন নোটিশ" }
                            val bMsg = latestObj.optString("message").ifBlank { systemNotice ?: "" }
                            val bSev = latestObj.optString("severity", "INFO")
                            if (bMsg.isNotBlank() && !NotificationHelper.hasBeenNotified(context, bId)) {
                                NotificationHelper.showAdminNotification(
                                    context = context,
                                    title = bTitle,
                                    message = bMsg,
                                    noticeId = bId,
                                    severity = bSev
                                )
                            }
                        } else if (systemNotice != null) {
                            val noticeId = "notice_${systemNotice.hashCode()}"
                            if (!NotificationHelper.hasBeenNotified(context, noticeId)) {
                                NotificationHelper.showAdminNotification(
                                    context = context,
                                    title = "📢 অ্যাডমিন নোটিশ",
                                    message = systemNotice,
                                    noticeId = noticeId,
                                    severity = "INFO"
                                )
                            }
                        }
                    }
                }
            } catch (e: Exception) {
                // Background network check notice
            }

            try {
                // 2. Check form submissions from central API for active merchant
                val activeProfile = repository.getActiveSupabaseProfile()
                val mId = activeProfile?.id ?: "00000000-0000-0000-0000-000000000001"
                if (mId.isNotBlank() && mId != "00000000-0000-0000-0000-000000000001") {
                    val formSubUrl = java.net.URL("https://api.swapnopay.top/v1/forms/recent-submissions?merchant_id=${java.net.URLEncoder.encode(mId, "UTF-8")}")
                    val formConn = formSubUrl.openConnection() as java.net.HttpURLConnection
                    formConn.requestMethod = "GET"
                    formConn.connectTimeout = 6000
                    formConn.readTimeout = 6000
                    if (formConn.responseCode in 200..299) {
                        val body = formConn.inputStream.bufferedReader().use { it.readText() }
                        if (body.isNotBlank()) {
                            val json = org.json.JSONObject(body)
                            val subs = json.optJSONArray("submissions")
                            if (subs != null && subs.length() > 0) {
                                for (i in 0 until subs.length()) {
                                    val item = subs.getJSONObject(i)
                                    val sId = item.optString("id").ifBlank { continue }
                                    if (!NotificationHelper.hasBeenNotified(context, sId)) {
                                        val formTitle = item.optString("form_title", item.optString("title", "Online Form"))
                                        val customer = item.optString("customer_name", item.optString("phone", "New Submission"))
                                        NotificationHelper.showFormSubmissionNotification(
                                            context = context,
                                            formTitle = formTitle,
                                            customerInfo = "Submission by $customer",
                                            submissionId = sId
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (e: Exception) {
                // Background network check notice
            }
        }
    }

    override fun onTimeout(startId: Int) {
        super.onTimeout(startId)
        Log.w("SmsMonitoringService", "Foreground service dataSync timeout reached; stopping gracefully.")
        stopSelf(startId)
    }

    override fun onDestroy() {
        super.onDestroy()
        SmsGatewayEngine.stopOutboxQueueProcessor()
        smsReceiver?.let {
            try {
                unregisterReceiver(it)
            } catch (e: Exception) {
                Log.w("SmsMonitoringService", "Error unregistering receiver: ${e.message}")
            }
        }
        job.cancel()
        Log.d("SmsMonitoringService", "Service stopped, outbox dispatcher stopped, and SMS receiver unregistered.")
    }
}
