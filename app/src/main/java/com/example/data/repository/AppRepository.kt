package com.example.data.repository

import android.content.Context
import com.example.data.local.*
import kotlinx.coroutines.*
import kotlin.coroutines.*
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.firstOrNull
import com.example.security.CryptoManager
import kotlinx.coroutines.flow.flowOf
import java.security.MessageDigest
import java.util.regex.Pattern

class AppRepository(private val context: Context) {

    private val db = AppDatabase.getDatabase(context)
    private val dao = db.appDao()
    val installationId: String by lazy {
        val prefs = context.getSharedPreferences("swapnopay_installation", Context.MODE_PRIVATE)
        prefs.getString("installation_id", null) ?: java.util.UUID.randomUUID().toString().also {
            prefs.edit().putString("installation_id", it).apply()
        }
    }

    // A local, installation-scoped profile exists only until an authenticated
    // merchant backend is connected. It is not seeded with demo business data.
    private val profiles = mutableListOf(
        MerchantProfileEntity(
            id = installationId,
            businessName = "Business setup required",
            email = "",
            phone = "",
            businessType = "",
            website = "",
            primaryBank = "",
            accountHolder = "",
            accountNumber = "",
            kycStatus = "UNVERIFIED",
            photoUrl = ""
        )
    )

    private var activeProfileId = profiles.first().id

    fun getActiveProfileId(): String = activeProfileId

    fun getProfiles(): List<MerchantProfileEntity> = profiles

    fun switchProfile(profileId: String) {
        val selected = profiles.find { it.id == profileId }
        if (selected != null) {
            activeProfileId = profileId
        }
    }

    // Supabase profiles operations
    fun observeSupabaseProfiles(): Flow<List<SupabaseProfileEntity>> {
        return dao.observeSupabaseProfiles().map { list ->
            list.map { profile ->
                profile.copy(
                    supabaseUrl = CryptoManager.decrypt(profile.supabaseUrl),
                    anonKey = CryptoManager.decrypt(profile.anonKey),
                    serviceRoleKey = CryptoManager.decrypt(profile.serviceRoleKey),
                    authEmail = CryptoManager.decrypt(profile.authEmail),
                    authSessionToken = CryptoManager.decrypt(profile.authSessionToken),
                    authRefreshToken = CryptoManager.decrypt(profile.authRefreshToken)
                )
            }
        }
    }

    suspend fun insertSupabaseProfile(profile: SupabaseProfileEntity) {
        val encrypted = profile.copy(
            supabaseUrl = CryptoManager.encrypt(profile.supabaseUrl),
            anonKey = CryptoManager.encrypt(profile.anonKey),
            serviceRoleKey = CryptoManager.encrypt(profile.serviceRoleKey),
            authEmail = CryptoManager.encrypt(profile.authEmail),
            authSessionToken = CryptoManager.encrypt(profile.authSessionToken),
            authRefreshToken = CryptoManager.encrypt(profile.authRefreshToken)
        )
        dao.insertSupabaseProfile(encrypted)
    }

    suspend fun getActiveSupabaseProfile(): SupabaseProfileEntity? {
        val profile = dao.getActiveSupabaseProfile() ?: return null
        return profile.copy(
            supabaseUrl = CryptoManager.decrypt(profile.supabaseUrl),
            anonKey = CryptoManager.decrypt(profile.anonKey),
            serviceRoleKey = CryptoManager.decrypt(profile.serviceRoleKey),
            authEmail = CryptoManager.decrypt(profile.authEmail),
            authSessionToken = CryptoManager.decrypt(profile.authSessionToken),
            authRefreshToken = CryptoManager.decrypt(profile.authRefreshToken)
        )
    }

    private suspend fun getAuthenticatedSupabaseProfile(): SupabaseProfileEntity? {
        val profile = getActiveSupabaseProfile() ?: return null
        activeProfileId = profile.id
        if (profile.authSessionToken.isBlank()) return null
        if (profile.authTokenExpiresAt == 0L || profile.authTokenExpiresAt > System.currentTimeMillis() + 60_000L) {
            return profile
        }
        if (profile.authRefreshToken.isBlank()) return null
        var refreshed: com.example.data.remote.SupabaseClient.AuthSession? = null
        withContext(Dispatchers.IO) {
            com.example.data.remote.SupabaseClient.refreshSession(
                profile.supabaseUrl,
                profile.anonKey,
                profile.authRefreshToken,
                onSuccess = { refreshed = it },
                onFailure = { android.util.Log.e("AppRepository", "Session refresh failed: $it") }
            )
        }
        val session = refreshed ?: return null
        return profile.copy(
            authEmail = session.email.ifBlank { profile.authEmail },
            authSessionToken = session.accessToken,
            authRefreshToken = session.refreshToken,
            authTokenExpiresAt = session.expiresAtMillis
        ).also { insertSupabaseProfile(it) }
    }

    suspend fun getSupabaseProfileById(id: String): SupabaseProfileEntity? {
        val profile = dao.getSupabaseProfileById(id) ?: return null
        return profile.copy(
            supabaseUrl = CryptoManager.decrypt(profile.supabaseUrl),
            anonKey = CryptoManager.decrypt(profile.anonKey),
            serviceRoleKey = CryptoManager.decrypt(profile.serviceRoleKey),
            authEmail = CryptoManager.decrypt(profile.authEmail),
            authSessionToken = CryptoManager.decrypt(profile.authSessionToken),
            authRefreshToken = CryptoManager.decrypt(profile.authRefreshToken)
        )
    }

    suspend fun deleteSupabaseProfile(id: String) {
        dao.deleteSupabaseProfile(id)
    }

    suspend fun deactivateSupabaseProfiles() = dao.deactivateAllProfiles()

    suspend fun selectActiveSupabaseProfile(profileId: String) {
        dao.deactivateAllProfiles()
        dao.activateProfile(profileId)
        activeProfileId = profileId
    }

    // SMS queue, orders, payments, appeals, devices
    fun observeSmsQueue(merchantId: String): Flow<List<SmsQueueEntity>> = dao.observeSmsQueue(merchantId)
    fun observeOrders(merchantId: String): Flow<List<CachedOrderEntity>> = dao.observeOrders(merchantId)
    fun observePayments(merchantId: String): Flow<List<CachedPaymentEntity>> = dao.observePayments(merchantId)
    fun observeAppeals(merchantId: String): Flow<List<AppealEntity>> = dao.observeAppeals(merchantId)
    fun observeDevices(merchantId: String): Flow<List<DeviceInfoEntity>> = dao.observeDevices(merchantId)
    fun observeMerchantProfile(): Flow<MerchantProfileEntity?> = dao.observeMerchantProfile()
    fun observeMfsPatterns(): Flow<List<MfsPatternEntity>> = dao.observeMfsPatterns()
    fun observeOutboxSms(merchantId: String): Flow<List<OutboxSmsEntity>> = dao.observeOutboxSms(merchantId)
    suspend fun insertOutboxSmsList(smsList: List<OutboxSmsEntity>) = dao.insertOutboxSmsList(smsList)

    suspend fun testSupabaseConnection(url: String, anonKey: String): Boolean = withContext(Dispatchers.IO) {
        var success = false
        try {
            com.example.data.remote.SupabaseClient.testConnection(
                url = url,
                anonKey = anonKey,
                onSuccess = { success = true },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Test connection failed: $err")
                    success = false
                }
            )
        } catch (e: Exception) {
            success = false
        }
        success
    }

    suspend fun syncMfsPatterns(): Boolean = withContext(Dispatchers.IO) {
        val active = getActiveSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchMfsPatterns(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken.ifBlank { active.anonKey },
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to sync dynamic regex patterns: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception syncing dynamic regex patterns", e)
        }

        if (resultData != null) {
            val patterns = mutableListOf<MfsPatternEntity>()
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                patterns.add(
                    MfsPatternEntity(
                        id = obj.getString("id"),
                        mfsName = obj.getString("mfs_name"),
                        patternName = obj.getString("pattern_name"),
                        regexPattern = obj.getString("regex_pattern"),
                        active = obj.optBoolean("active", true)
                    )
                )
            }
            dao.clearMfsPatterns()
            dao.insertMfsPatterns(patterns)
            android.util.Log.d("AppRepository", "Successfully synced ${patterns.size} dynamic regex patterns from Supabase.")
            true
        } else {
            false
        }
    }

    private fun parseIsoDateToMillis(isoStr: String?): Long {
        if (isoStr.isNullOrEmpty() || isoStr == "null") return System.currentTimeMillis()
        return try {
            val clean = isoStr.replace("Z", "+0000").replace("(\\+\\d{2}):(\\d{2})", "$1$2")
            val formats = listOf(
                "yyyy-MM-dd'T'HH:mm:ss.SSSSSSZ",
                "yyyy-MM-dd'T'HH:mm:ss.SSSZ",
                "yyyy-MM-dd'T'HH:mm:ssZ",
                "yyyy-MM-dd HH:mm:ss"
            )
            var parsed: Long? = null
            for (f in formats) {
                try {
                    parsed = java.text.SimpleDateFormat(f, java.util.Locale.US).parse(clean)?.time
                    if (parsed != null) break
                } catch (e: Exception) {}
            }
            parsed ?: System.currentTimeMillis()
        } catch (e: Exception) {
            System.currentTimeMillis()
        }
    }

    suspend fun syncOrdersFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty() || active.authSessionToken.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchOrders(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                merchantId = active.id,
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch orders: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching orders", e)
        }

        if (resultData != null) {
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                val order = CachedOrderEntity(
                    id = obj.getString("id"),
                    merchantId = active.id,
                    customerName = obj.optString("cus_name", "Anonymous"),
                    customerPhone = obj.getString("cus_phone"),
                    amount = obj.getDouble("amount"),
                    status = obj.getString("status"),
                    method = obj.optString("payment_method", "bKash"),
                    createdAt = parseIsoDateToMillis(obj.optString("created_at")),
                    expiresAt = parseIsoDateToMillis(obj.optString("expires_at")),
                    notes = obj.optJSONObject("metadata")?.optString("notes", "").orEmpty()
                )
                dao.insertOrder(order)
            }
            true
        } else {
            false
        }
    }

    suspend fun syncPaymentsFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile()
        var didSyncAny = false

        if (active != null && active.supabaseUrl.isNotEmpty() && active.anonKey.isNotEmpty() && active.authSessionToken.isNotEmpty()) {
            var resultData: org.json.JSONArray? = null
            try {
                com.example.data.remote.SupabaseClient.fetchPayments(
                    url = active.supabaseUrl,
                    anonKey = active.anonKey,
                    token = active.authSessionToken,
                    merchantId = active.id,
                    onSuccess = { jsonArray -> resultData = jsonArray },
                    onFailure = { err ->
                        android.util.Log.e("AppRepository", "Failed to fetch payments: $err")
                    }
                )
            } catch (e: Exception) {
                android.util.Log.e("AppRepository", "Exception fetching payments", e)
            }

            if (resultData != null) {
                for (i in 0 until resultData!!.length()) {
                    val obj = resultData!!.getJSONObject(i)
                    val payment = CachedPaymentEntity(
                        id = obj.optString("trx_id", obj.getString("id")),
                        merchantId = active.id,
                        amount = obj.getDouble("amount"),
                        sender = obj.optString("sender_number", "Unknown"),
                        timestamp = parseIsoDateToMillis(obj.optString("sms_timestamp", obj.optString("created_at"))),
                        status = obj.getString("status"),
                        method = obj.optString("method", "bKash"),
                        orderId = if (obj.isNull("matched_order_id")) null else obj.getString("matched_order_id")
                    )
                    dao.insertPayment(payment)
                }
                didSyncAny = true
            }
        }

        // Backend Sync Fallback: /v1/payment/transactions?merchant_id=...
        val targetMerchantId = dao.getMerchantProfile()?.id
            ?: active?.id
            ?: activeProfileId
        if (targetMerchantId.isNotBlank() && targetMerchantId != "00000000-0000-0000-0000-000000000001") {
            try {
                val url = java.net.URL("https://api.swapnopay.top/v1/payment/transactions?merchant_id=${java.net.URLEncoder.encode(targetMerchantId, "UTF-8")}")
                val conn = url.openConnection() as java.net.HttpURLConnection
                conn.requestMethod = "GET"
                conn.connectTimeout = 5000
                conn.readTimeout = 5000
                if (conn.responseCode in 200..299) {
                    val respStr = conn.inputStream.bufferedReader().use { it.readText() }
                    val json = org.json.JSONObject(respStr)
                    val list = json.optJSONArray("transactions")
                    if (list != null && list.length() > 0) {
                        for (i in 0 until list.length()) {
                            val obj = list.getJSONObject(i)
                            val trxId = obj.optString("trx_id").ifBlank { obj.optString("id") }
                            if (trxId.isNotBlank()) {
                                val payment = CachedPaymentEntity(
                                    id = trxId,
                                    merchantId = targetMerchantId,
                                    amount = obj.optDouble("amount", 0.0),
                                    sender = obj.optString("sender_number", obj.optString("sender", "Unknown")),
                                    timestamp = parseIsoDateToMillis(obj.optString("created_at")),
                                    status = obj.optString("status", "SUCCESS"),
                                    method = obj.optString("payment_method", obj.optString("method", "bKash")),
                                    orderId = if (obj.isNull("order_id")) null else obj.optString("order_id")
                                )
                                dao.insertPayment(payment)
                            }
                        }
                        didSyncAny = true
                    }
                }
            } catch (e: Exception) {
                android.util.Log.d("AppRepository", "Backend transactions sync notice: ${e.message}")
            }
        }

        didSyncAny
    }

    suspend fun syncAppealsFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty() || active.authSessionToken.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchAppeals(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch appeals: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching appeals", e)
        }

        if (resultData != null) {
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                val order = obj.optJSONObject("orders")
                val appeal = AppealEntity(
                    id = obj.getString("id"),
                    merchantId = active.id,
                    orderId = obj.optString("order_id", ""),
                    amount = order?.optDouble("amount", 0.0) ?: 0.0,
                    customerName = order?.optString("cus_name", "Payer") ?: "Payer",
                    customerPhone = obj.optString("cus_phone").ifBlank { order?.optString("cus_phone").orEmpty() },
                    trxId = obj.getString("trx_id"),
                    timestamp = parseIsoDateToMillis(obj.optString("created_at")),
                    status = obj.getString("status")
                )
                dao.insertAppeal(appeal)
            }
            true
        } else {
            false
        }
    }

    suspend fun syncDevicesFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty() || active.authSessionToken.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchDevices(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch devices: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching devices", e)
        }

        if (resultData != null) {
            val devices = mutableListOf<DeviceInfoEntity>()
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                devices.add(
                    DeviceInfoEntity(
                        id = obj.getString("id"),
                        merchantId = active.id,
                        deviceName = obj.optString("device_model", "Unknown Device"),
                        status = if (obj.optBoolean("online", true)) "ONLINE" else "OFFLINE",
                        batteryLevel = obj.optInt("battery_level", 100),
                        lastSyncTime = parseIsoDateToMillis(obj.optString("last_sync", obj.optString("created_at")))
                    )
                )
            }
            dao.clearDevices(active.id)
            dao.insertDevices(devices)
            true
        } else {
            false
        }
    }

    suspend fun sendDeviceHeartbeat(context: android.content.Context, batteryLevel: Int = 100): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.id.isBlank()) return@withContext false

        val deviceId = installationId.ifBlank {
            android.provider.Settings.Secure.getString(context.contentResolver, android.provider.Settings.Secure.ANDROID_ID) ?: "device_unknown"
        }
        val model = "${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}"
        val osVer = "Android ${android.os.Build.VERSION.RELEASE}"

        var success = false

        // 1. Always ping SwapnoPay backend heartbeat so gateway immediately marks merchant active
        try {
            val backendPayload = org.json.JSONObject().apply {
                put("merchant_id", active.id)
                put("device_id", deviceId)
                put("battery_level", batteryLevel)
                put("status", "ONLINE")
            }
            val conn = (java.net.URL("https://api.swapnopay.top/v1/payment/heartbeat").openConnection() as java.net.HttpURLConnection).apply {
                requestMethod = "POST"
                setRequestProperty("Content-Type", "application/json")
                doOutput = true
                connectTimeout = 4000
                readTimeout = 4000
            }
            conn.outputStream.use { os ->
                os.write(backendPayload.toString().toByteArray(Charsets.UTF_8))
            }
            if (conn.responseCode in 200..299) {
                success = true
            }
            conn.disconnect()
        } catch (_: Exception) {}

        // 2. Also register/update device on merchant Supabase if credentials present
        if (active.supabaseUrl.isNotBlank() && active.anonKey.isNotBlank()) {
            val token = active.authSessionToken.ifBlank { active.anonKey }
            try {
                com.example.data.remote.SupabaseClient.registerOrUpdateDevice(
                    url = active.supabaseUrl,
                    anonKey = active.anonKey,
                    token = token,
                    deviceId = deviceId,
                    model = model,
                    osVersion = osVer,
                    batteryLevel = batteryLevel,
                    online = true,
                    merchantId = active.id,
                    onSuccess = { success = true },
                    onFailure = { /* non-fatal if backend heartbeat succeeded */ }
                )
            } catch (_: Exception) {}
        }

        success
    }

    suspend fun syncMerchantNumbersToSupabase(number: String, type: String, isDefault: Boolean = true): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty() || active.authSessionToken.isEmpty()) return@withContext false

        var success = false
        try {
            com.example.data.remote.SupabaseClient.upsertMerchantNumber(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                merchantId = active.id,
                number = number,
                type = type,
                isDefault = isDefault,
                onSuccess = { success = true },
                onFailure = { success = false }
            )
        } catch (e: Exception) {
            success = false
        }
        success
    }

    // CRUD operations
    suspend fun insertOrder(order: CachedOrderEntity) {
        dao.insertOrder(order)
    }

    suspend fun insertOrders(orders: List<CachedOrderEntity>) = dao.insertOrders(orders)

    suspend fun insertPayment(payment: CachedPaymentEntity) {
        dao.insertPayment(payment)
    }

    suspend fun insertPayments(payments: List<CachedPaymentEntity>) = dao.insertPayments(payments)

    suspend fun insertAppeal(appeal: AppealEntity) {
        dao.insertAppeal(appeal)
    }

    suspend fun insertAppeals(appeals: List<AppealEntity>) = dao.insertAppeals(appeals)

    suspend fun insertMerchantProfile(profile: MerchantProfileEntity) {
        dao.insertMerchantProfile(profile)
        val idx = profiles.indexOfFirst { it.id == profile.id }
        if (idx != -1) {
            profiles[idx] = profile
        } else {
            profiles.add(profile)
        }
    }

    suspend fun getMerchantProfileById(id: String): MerchantProfileEntity? = dao.getMerchantProfileById(id)

    suspend fun reassignMerchantData(oldId: String, newId: String) {
        dao.reassignMerchantData(oldId, newId)
    }

    // SMS Parsing & Automation Engine
    suspend fun processIncomingSms(sender: String, body: String): Boolean {
        // 1. Sender Identification filter
        if (!isAllowedSender(sender)) {
            android.util.Log.d("AppRepository", "SMS rejected: sender '$sender' is not in allowed list.")
            return false
        }
        
        // 2. Content Filter – Incoming Money Only
        if (!isIncomingMoneyAlert(sender, body)) {
            android.util.Log.d("AppRepository", "SMS rejected: not an incoming money transaction alert.")
            return false
        }

        // 3. Regex Parsing – Extracting Structured Data
        val parsed = parseSms(sender, body) ?: run {
            android.util.Log.d("AppRepository", "SMS rejected: failed to parse structured data with regex.")
            return false
        }

        val merchantId = dao.getMerchantProfile()?.id
            ?: getActiveSupabaseProfile()?.id
            ?: activeProfileId
        val smsEntity = SmsQueueEntity(
            merchantId = merchantId,
            amount = parsed.amount,
            sender = parsed.senderPhone,
            trxId = parsed.trxId,
            timestamp = parsed.timestamp,
            merchantNumber = parsed.receiver,
            status = "PENDING",
            rawBody = body
        )
        val paymentEntity = CachedPaymentEntity(
            id = parsed.trxId,
            merchantId = merchantId,
            amount = parsed.amount,
            sender = parsed.senderPhone,
            timestamp = parsed.timestamp,
            status = "UNMATCHED",
            method = parsed.method,
            orderId = null
        )

        // Atomic deduplication & insertion in a single Room transaction
        val insertedId = dao.processAndInsertSmsAtomically(smsEntity, paymentEntity)
        if (insertedId == null) {
            android.util.Log.d("AppRepository", "SMS rejected: duplicate transaction detected (TrxID ${parsed.trxId}).")
            return false
        }

        // Attempt upload to Supabase, fallback to Room if fails
        val success = uploadSmsToSupabase(smsEntity.copy(id = insertedId.toInt()))
        if (success) {
            dao.updateSmsStatus(insertedId.toInt(), "SYNCED")
        }
        
        return true
    }

    suspend fun uploadSmsToSupabase(sms: SmsQueueEntity): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty() || active.authSessionToken.isEmpty()) return@withContext false
        
        var success = false
        try {
            com.example.data.remote.SupabaseClient.insertSmsLog(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                merchantId = sms.merchantId,
                deviceId = installationId,
                rawSms = sms.rawBody,
                amount = sms.amount,
                sender = sms.sender,
                trxId = sms.trxId,
                timestamp = sms.timestamp,
                onSuccess = { success = true },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to upload SMS log: $err")
                    success = false
                }
            )
        } catch (e: Exception) {
            success = false
        }
        success
    }

    fun startSmsQueueAutoRetry(scope: kotlinx.coroutines.CoroutineScope) {
        scope.launch {
            while (true) {
                kotlinx.coroutines.delay(60000) // Retry every 60 seconds
                try {
                    val merchantId = getActiveSupabaseProfile()?.id ?: activeProfileId
                    val pending = dao.getPendingSms(merchantId)
                    if (pending.isNotEmpty()) {
                        android.util.Log.d("AppRepository", "Offline SMS Queue: Retrying ${pending.size} pending SMS uploads.")
                        for (sms in pending) {
                            val success = uploadSmsToSupabase(sms)
                            if (success) {
                                dao.updateSmsStatus(sms.id, "SYNCED")
                                android.util.Log.d("AppRepository", "Successfully synced pending TrxID: ${sms.trxId}")
                            }
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }
    }

    suspend fun updateOrder(order: CachedOrderEntity) {
        dao.insertOrder(order)
    }

    suspend fun updateAppealStatus(id: String, status: String) {
        dao.updateAppealStatus(id, status)
    }

    // Prepopulate database with realistic business data so the user gets an immediately usable, gorgeous UI
    suspend fun prepopulateIfEmpty() {
        if (dao.getMerchantProfile() == null) dao.insertMerchantProfile(profiles.first())
        if (dao.getActiveSupabaseProfile() == null) {
            insertSupabaseProfile(
                SupabaseProfileEntity(
                    id = "00000000-0000-0000-0000-000000000001",
                    businessName = "SwapnoPay Main Cloud",
                    supabaseUrl = "https://tldubojeokgyoclxnzkb.supabase.co",
                    anonKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRsZHVib2plb2tneW9jbHhuemtiIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODc3NjcwODMsImV4cCI6MjEwMzM0MzA4M30.vlgmNEJ0_DpdbsZEQMA2Z82vwY4hwTxpgS4o9p5oEb0",
                    serviceRoleKey = "",
                    isActive = true
                )
            )
        }
        if (dao.getAllMfsPatterns().isEmpty()) {
            dao.insertMfsPatterns(
                listOf(
                    MfsPatternEntity("bkash_trad", "bKash", "traditional", "You have received Tk (\\d+\\.?\\d*).*?from (\\d+).*?TrxID (\\w+) at (\\d{2}/\\d{2}/\\d{4} \\d{2}:\\d{2})", true),
                    MfsPatternEntity("bkash_cash_in", "bKash", "cash-in", "Cash In Tk ([\\d,]+\\.?\\d*).*?from (\\d+).*?TrxID (\\w+) at (\\d{2}/\\d{2}/\\d{4} \\d{2}:\\d{2})", true),
                    MfsPatternEntity("nagad_received", "Nagad", "received", "Money Received\\..*?Amount:\\s*Tk (\\d+\\.?\\d*).*?Sender:\\s*(\\d+).*?TxnID:\\s*(\\w+).*?(\\d{2}/\\d{2}/\\d{4} \\d{2}:\\d{2})", true),
                    MfsPatternEntity("rocket_cash_in", "Rocket", "cash-in", "Cash-In from A/C:\\s*\\*+\\d+\\s*Tk([\\d,]+\\.?\\d*)[\\s\\S]*?TxnId:(\\d+)\\s+Date:(\\d{2}-[A-Z]{3}-\\d{2}\\s+\\d{2}:\\d{2}:\\d{2}\\s*[ap]m)", true),
                    MfsPatternEntity("upay_received", "Upay", "received", "(?:Money Received|Received Taka)[\\s\\S]*?Taka\\s*([\\d,]+\\.?\\d*)[\\s\\S]*?from\\s*(\\d+)[\\s\\S]*?TrxID\\s*(\\w+)[\\s\\S]*?(\\d{2}/\\d{2}/\\d{4} \\d{2}:\\d{2})", true)
                )
            )
        }
    }

    // Helper functions
    private fun sha256(base: String): String {
        return try {
            val digest = MessageDigest.getInstance("SHA-256")
            val hash = digest.digest(base.toByteArray(Charsets.UTF_8))
            val hexString = StringBuilder()
            for (i in hash.indices) {
                val hex = Integer.toHexString(0xff and hash[i].toInt())
                if (hex.length == 1) hexString.append('0')
                hexString.append(hex)
            }
            hexString.toString()
        } catch (ex: Exception) {
            base.hashCode().toString()
        }
    }

    fun isAllowedSender(address: String?): Boolean {
        val s = address?.lowercase()?.trim() ?: return false
        return s == "bkash" || s == "nagad" || s == "upay" || s == "16216" ||
               s.endsWith("bkash") || s.endsWith("nagad") || s.endsWith("upay") || s.endsWith("16216")
        val clean = s.filter { it.isLetterOrDigit() }
        return clean.contains("bkash") ||
               clean.contains("nagad") ||
               clean.contains("upay") ||
               clean.contains("rocket") ||
               clean == "16247" || clean.endsWith("16247") ||
               clean == "16167" || clean.endsWith("16167") ||
               clean == "16216" || clean.endsWith("16216") ||
               clean == "16268" || clean.endsWith("16268")
    }

    fun isIncomingMoneyAlert(sender: String, body: String): Boolean {
        val senderLower = sender.lowercase().trim()
        val clean = senderLower.filter { it.isLetterOrDigit() }
        val bodyLower = body.lowercase()
        return when {
            senderLower.contains("bkash") -> bodyLower.contains("received") || bodyLower.contains("cash in")
            senderLower.contains("nagad") -> bodyLower.contains("money received") || bodyLower.contains("cash in") || bodyLower.contains("received")
            senderLower.contains("upay") -> bodyLower.contains("money received") || bodyLower.contains("received taka") || bodyLower.contains("cash in")
            senderLower.contains("16216") -> bodyLower.contains("cash-in") || bodyLower.contains("cash in") || bodyLower.contains("received")
            else -> false
        }
        val isMfsSender = clean.contains("bkash") || clean.contains("nagad") || clean.contains("upay") || clean.contains("rocket") ||
                          clean == "16247" || clean.endsWith("16247") ||
                          clean == "16167" || clean.endsWith("16167") ||
                          clean == "16216" || clean.endsWith("16216") ||
                          clean == "16268" || clean.endsWith("16268")
        if (!isMfsSender) return false

        return bodyLower.contains("received") ||
               bodyLower.contains("payment") ||
               bodyLower.contains("cash in") ||
               bodyLower.contains("cash-in") ||
               bodyLower.contains("credited") ||
               bodyLower.contains("deposit") ||
               bodyLower.contains("transferred")
    }

    private fun parseSmsDateToMillis(dateStr: String, isRocket: Boolean): Long {
        return try {
            if (isRocket) {
                // e.g. "01-JUL-26 04:11:32 pm"
                val sdf = java.text.SimpleDateFormat("dd-MMM-yy hh:mm:ss a", java.util.Locale.US).apply {
                    timeZone = java.util.TimeZone.getTimeZone("Asia/Dhaka")
                }
                sdf.parse(dateStr)?.time ?: System.currentTimeMillis()
            } else {
                // e.g. "02/07/2026 15:00"
                val sdf = java.text.SimpleDateFormat("dd/MM/yyyy HH:mm", java.util.Locale.US).apply {
                    timeZone = java.util.TimeZone.getTimeZone("Asia/Dhaka")
                }
                sdf.parse(dateStr)?.time ?: System.currentTimeMillis()
            }
        } catch (e: Exception) {
            System.currentTimeMillis()
        }
    }

    data class ParsedSms(
        val amount: Double,
        val senderPhone: String,
        val trxId: String,
        val receiver: String,
        val method: String,
        val timestamp: Long
    )

    private suspend fun parseSms(sender: String, body: String): ParsedSms? {
        val senderLower = sender.lowercase().trim()
        val cleanSender = sender.lowercase().filter { it.isLetterOrDigit() }
        val mfsName = when {
            senderLower.contains("bkash") -> "bKash"
            senderLower.contains("nagad") -> "Nagad"
            senderLower.contains("upay") -> "Upay"
            senderLower.contains("16216") -> "Rocket"
            cleanSender.contains("bkash") || cleanSender.endsWith("16247") -> "bKash"
            cleanSender.contains("nagad") || cleanSender.endsWith("16167") -> "Nagad"
            cleanSender.contains("upay") || cleanSender.endsWith("16268") -> "Upay"
            cleanSender.contains("rocket") || cleanSender.endsWith("16216") -> "Rocket"
            body.contains("bkash", ignoreCase = true) -> "bKash"
            body.contains("nagad", ignoreCase = true) -> "Nagad"
            body.contains("upay", ignoreCase = true) -> "Upay"
            body.contains("rocket", ignoreCase = true) -> "Rocket"
            else -> return null
        }
        
        try {
            val cachedPatterns = dao.getAllMfsPatterns().filter { it.mfsName.equals(mfsName, ignoreCase = true) }
            for (patternEntity in cachedPatterns) {
                val isDotAll = patternEntity.mfsName.equals("Nagad", ignoreCase = true)
                val flags = if (isDotAll) Pattern.CASE_INSENSITIVE or Pattern.DOTALL else Pattern.CASE_INSENSITIVE
                
                val pattern = Pattern.compile(patternEntity.regexPattern, flags)
                val matcher = pattern.matcher(body)
                if (matcher.find()) {
                    val groupCount = matcher.groupCount()
                    if (patternEntity.mfsName.equals("Rocket", ignoreCase = true)) {
                        // Rocket: Group 1 amount, Group 2 trxId, Group 3 dateStr (optional)
                        val amountStr = if (groupCount >= 1) matcher.group(1)?.replace(",", "") ?: "0.0" else "0.0"
                        val amount = amountStr.toDoubleOrNull() ?: 0.0
                        val trxId = if (groupCount >= 2) matcher.group(2) ?: "Unknown" else "Unknown"
                        val dateStr = if (groupCount >= 3) matcher.group(3) ?: "" else ""
                        val timestamp = if (dateStr.isNotBlank()) parseSmsDateToMillis(dateStr, isRocket = true) else System.currentTimeMillis()
                        
                        val rocketAccPattern = Pattern.compile("A/C:\\s*(\\*+\\d+)", Pattern.CASE_INSENSITIVE)
                        val accMatcher = rocketAccPattern.matcher(body)
                        val senderPhone = if (accMatcher.find()) accMatcher.group(1) ?: "Rocket Payer" else "Rocket Payer"
                        
                        return ParsedSms(
                            amount = amount,
                            senderPhone = senderPhone,
                            trxId = trxId,
                            receiver = "rocket_merchant",
                            method = "Rocket",
                            timestamp = timestamp
                        )
                    } else {
                        // bKash, Nagad, Upay: Group 1 amount, Group 2 senderPhone, Group 3 trxId, Group 4 dateStr (optional)
                        val amountStr = if (groupCount >= 1) matcher.group(1)?.replace(",", "") ?: "0.0" else "0.0"
                        val amount = amountStr.toDoubleOrNull() ?: 0.0
                        val senderPhone = if (groupCount >= 2) matcher.group(2) ?: "Unknown" else "Unknown"
                        val trxId = if (groupCount >= 3) matcher.group(3) ?: "Unknown" else "Unknown"
                        val dateStr = if (groupCount >= 4) matcher.group(4) ?: "" else ""
                        val timestamp = if (dateStr.isNotBlank()) parseSmsDateToMillis(dateStr, isRocket = false) else System.currentTimeMillis()
                        return ParsedSms(
                            amount = amount,
                            senderPhone = senderPhone,
                            trxId = trxId,
                            receiver = "${mfsName.lowercase()}_merchant",
                            method = mfsName,
                            timestamp = timestamp
                        )
                    }
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
            android.util.Log.e("AppRepository", "Cached regex pattern matching failed: ${e.message}")
        }

        // Resilient Fallback Parser for Bangladeshi MFS Masked SMS (bKash, Nagad, Upay, Rocket)
        try {
            // 1. Extract Amount: "Tk 1,500.00", "Amount: Tk 500", "Taka 250"
            val amtPattern = Pattern.compile("(?:Amount:\\s*)?(?:Tk|Taka|BDT|Tk\\.)\\s*([\\d,]+\\.?\\d*)", Pattern.CASE_INSENSITIVE)
            val amtMatcher = amtPattern.matcher(body)
            val amount = if (amtMatcher.find()) {
                amtMatcher.group(1)?.replace(",", "")?.toDoubleOrNull() ?: 0.0
            } else 0.0

            // 2. Extract TrxID / TxnID: "TrxID 9ABCDE", "TxnID: 12345", "TxnId: 888"
            val trxPattern = Pattern.compile("(?:TrxID|TxnID|TxnId|Trx ID|Trans ID)[:\\s]+([A-Za-z0-9]+)", Pattern.CASE_INSENSITIVE)
            val trxMatcher = trxPattern.matcher(body)
            val trxId = if (trxMatcher.find()) {
                trxMatcher.group(1) ?: "TRX_${System.currentTimeMillis()}"
            } else {
                "TRX_${System.currentTimeMillis()}"
            }

            // 3. Extract Sender Phone / Account: "from 01712***789", "Sender: 018***", "A/C: *017***"
            val senderPattern = Pattern.compile("(?:from|Sender:?|from A/C:?|A/C:?)\\s*([0-9*xX+ -]{6,18})", Pattern.CASE_INSENSITIVE)
            val senderMatcher = senderPattern.matcher(body)
            val senderPhone = if (senderMatcher.find()) {
                senderMatcher.group(1)?.trim() ?: "Payer"
            } else "Payer"

            // 4. Extract Date / Time if available
            val datePattern = Pattern.compile("(\\d{2}/\\d{2}/\\d{4}\\s+\\d{2}:\\d{2})|(\\d{2}-[A-Za-z]{3}-\\d{2}\\s+\\d{2}:\\d{2}(?::\\d{2})?\\s*[ap]m)", Pattern.CASE_INSENSITIVE)
            val dateMatcher = datePattern.matcher(body)
            val timestamp = if (dateMatcher.find()) {
                val matched = dateMatcher.group(0) ?: ""
                parseSmsDateToMillis(matched, isRocket = (mfsName == "Rocket"))
            } else System.currentTimeMillis()

            if (amount > 0.0) {
                return ParsedSms(
                    amount = amount,
                    senderPhone = senderPhone,
                    trxId = trxId,
                    receiver = "${mfsName.lowercase()}_merchant",
                    method = mfsName,
                    timestamp = timestamp
                )
            }
        } catch (fallbackErr: Exception) {
            android.util.Log.e("AppRepository", "Resilient SMS parser error: ${fallbackErr.message}")
        }

        return null
    }

    // Customers Repository Operations
    fun observeCustomers(merchantId: String): Flow<List<CustomerEntity>> = dao.observeCustomers(merchantId)
    suspend fun getCustomerById(id: String, merchantId: String): CustomerEntity? = dao.getCustomerById(id, merchantId)
    suspend fun insertCustomer(customer: CustomerEntity) = dao.insertCustomer(customer)
    suspend fun insertCustomers(customers: List<CustomerEntity>) = dao.insertCustomers(customers)
    suspend fun deleteCustomerById(id: String) = dao.deleteCustomerById(id)

    // Suppliers Repository Operations
    fun observeSuppliers(merchantId: String): Flow<List<SupplierEntity>> = dao.observeSuppliers(merchantId)
    suspend fun getSupplierById(id: String, merchantId: String): SupplierEntity? = dao.getSupplierById(id, merchantId)
    suspend fun insertSupplier(supplier: SupplierEntity) = dao.insertSupplier(supplier)
    suspend fun insertSuppliers(suppliers: List<SupplierEntity>) = dao.insertSuppliers(suppliers)
    suspend fun deleteSupplierById(id: String) = dao.deleteSupplierById(id)

    // Ledger Transactions Repository Operations
    fun observeLedgerTransactions(merchantId: String): Flow<List<LedgerTransactionEntity>> = dao.observeLedgerTransactions(merchantId)
    
    suspend fun recordLedgerTransaction(tx: LedgerTransactionEntity) {
        dao.recordLedgerTransactionAtomic(tx)
    }
    suspend fun insertLedgerTransactions(transactions: List<LedgerTransactionEntity>) =
        dao.insertLedgerTransactions(transactions)

    suspend fun deleteLedgerTransaction(id: String) {
        dao.deleteLedgerTransactionById(id)
    }

    // Products / Stock Operations
    fun observeProducts(merchantId: String): Flow<List<ProductItemEntity>> = dao.observeProducts(merchantId)
    suspend fun getProductById(id: String, merchantId: String): ProductItemEntity? = dao.getProductById(id, merchantId)
    suspend fun insertProduct(product: ProductItemEntity) {
        dao.insertProduct(product)
        uploadProductToSupabase(product)
    }
    suspend fun insertProducts(products: List<ProductItemEntity>) = dao.insertProducts(products)
    suspend fun deletePristineProduct(id: String, merchantId: String) {
        dao.deletePristineProduct(id, merchantId)
        deleteProductFromSupabase(id)
    }
    suspend fun deleteProduct(id: String, merchantId: String): Boolean {
        val localDeleted = dao.deleteProductCascade(id, merchantId)
        try {
            deleteProductFromSupabase(id)
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Supabase deleteProduct failed: ${e.message}")
        }
        return localDeleted
    }
    suspend fun saveProductImages(product: ProductItemEntity, previous: String) = dao.updateProductMedia(product.id, product.merchantId, product.imageUrl, product.storefrontDetailsJson, previous)
    suspend fun canDeletePristineProduct(id: String, merchantId: String) = dao.canDeletePristineProduct(id, merchantId)
    suspend fun updateProductWithStockAdjustment(
        product: ProductItemEntity,
        stockAdjustment: StockTransactionEntity?
    ) {
        dao.updateProductWithStockAdjustmentAtomic(product, stockAdjustment)
        uploadProductToSupabase(product)
    }

    suspend fun syncProductsFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchRecords(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                tableName = "products",
                selectQuery = "*",
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch products: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching products", e)
        }

        if (resultData != null) {
            val products = mutableListOf<ProductItemEntity>()
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                val merchantId = obj.optString("merchant_id", active.id)
                if (merchantId == active.id) {
                    products.add(
                        ProductItemEntity(
                            id = obj.getString("id"),
                            merchantId = active.id,
                            name = obj.getString("name"),
                            code = obj.optString("code", null),
                            category = obj.optString("category", "General"),
                            purchasePrice = obj.optDouble("purchase_price", 0.0),
                            salePrice = obj.optDouble("sale_price", 0.0),
                            stockQuantity = obj.optDouble("stock_quantity", 0.0),
                            minStockThreshold = obj.optDouble("min_stock_threshold", 5.0),
                            unit = obj.optString("unit", "pcs"),
                            imageUrl = obj.optString("image_url", null),
                            storefrontDetailsJson = obj.optJSONObject("storefront_details")?.toString() ?: "{}",
                            createdAt = parseIsoDateToMillis(obj.optString("created_at")),
                            costPrice = obj.optDouble("purchase_price", 0.0),
                            askingPrice = obj.optDouble("sale_price", 0.0)
                        )
                    )
                }
            }
            if (products.isNotEmpty()) {
                dao.insertProducts(products)
            }
            true
        } else {
            false
        }
    }

    suspend fun uploadProductToSupabase(product: ProductItemEntity): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var success = false
        try {
            val payload = org.json.JSONObject().apply {
                put("id", product.id)
                put("merchant_id", active.id)
                put("name", product.name)
                put("code", product.code ?: "")
                put("category", product.category ?: "General")
                put("purchase_price", product.purchasePrice)
                put("sale_price", product.salePrice)
                put("stock_quantity", product.stockQuantity)
                put("min_stock_threshold", product.minStockThreshold)
                put("unit", product.unit)
                put("storefront_details", org.json.JSONObject(product.storefrontDetailsJson))
                if (!product.imageUrl.isNullOrBlank()) {
                    put("image_url", product.imageUrl)
                }
            }

            com.example.data.remote.SupabaseClient.upsertRecord(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                tableName = "products",
                payload = payload,
                onSuccess = { success = true },
                onFailure = { success = false }
            )
        } catch (e: Exception) {
            success = false
        }
        success
    }

    suspend fun deleteProductFromSupabase(productId: String): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var success = false
        try {
            com.example.data.remote.SupabaseClient.deleteRecord(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                tableName = "products",
                primaryKeyCol = "id",
                primaryKeyVal = productId,
                onSuccess = { success = true },
                onFailure = { success = false }
            )
        } catch (e: Exception) {
            success = false
        }
        success
    }

    suspend fun syncCustomersFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchRecords(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                tableName = "customers",
                selectQuery = "*",
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch customers: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching customers", e)
        }

        if (resultData != null) {
            val customers = mutableListOf<CustomerEntity>()
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                val merchantId = obj.optString("merchant_id", active.id)
                if (merchantId == active.id) {
                    customers.add(
                        CustomerEntity(
                            id = obj.getString("id"),
                            merchantId = active.id,
                            name = obj.getString("name"),
                            phone = obj.optString("phone", ""),
                            email = if (obj.isNull("email")) null else obj.optString("email"),
                            address = if (obj.isNull("address")) null else obj.optString("address"),
                            openingBalance = obj.optDouble("opening_balance", 0.0),
                            currentBalance = obj.optDouble("current_balance", 0.0),
                            status = obj.optString("status", "VIP"),
                            createdAt = parseIsoDateToMillis(obj.optString("created_at")),
                            code = obj.optString("code", "")
                        )
                    )
                }
            }
            if (customers.isNotEmpty()) {
                dao.insertCustomers(customers)
            }
            true
        } else {
            false
        }
    }

    suspend fun syncSuppliersFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchRecords(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                tableName = "suppliers",
                selectQuery = "*",
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch suppliers: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching suppliers", e)
        }

        if (resultData != null) {
            val suppliers = mutableListOf<SupplierEntity>()
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                val merchantId = obj.optString("merchant_id", active.id)
                if (merchantId == active.id) {
                    suppliers.add(
                        SupplierEntity(
                            id = obj.getString("id"),
                            merchantId = active.id,
                            name = obj.getString("name"),
                            phone = obj.optString("phone", ""),
                            email = if (obj.isNull("email")) null else obj.optString("email"),
                            address = if (obj.isNull("address")) null else obj.optString("address"),
                            openingBalance = obj.optDouble("opening_balance", 0.0),
                            currentBalance = obj.optDouble("current_balance", 0.0),
                            createdAt = parseIsoDateToMillis(obj.optString("created_at")),
                            code = obj.optString("code", "")
                        )
                    )
                }
            }
            if (suppliers.isNotEmpty()) {
                dao.insertSuppliers(suppliers)
            }
            true
        } else {
            false
        }
    }

    suspend fun syncLedgerFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchRecords(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                tableName = "ledger_transactions",
                selectQuery = "*",
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch ledger transactions: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching ledger transactions", e)
        }

        if (resultData != null) {
            val txs = mutableListOf<LedgerTransactionEntity>()
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                val merchantId = obj.optString("merchant_id", active.id)
                if (merchantId == active.id) {
                    txs.add(
                        LedgerTransactionEntity(
                            id = obj.getString("id"),
                            merchantId = active.id,
                            customerId = if (obj.isNull("customer_id") || obj.optString("customer_id").isBlank()) null else obj.optString("customer_id"),
                            supplierId = if (obj.isNull("supplier_id") || obj.optString("supplier_id").isBlank()) null else obj.optString("supplier_id"),
                            type = obj.optString("type", "credit"),
                            amount = obj.optDouble("amount", 0.0),
                            date = parseIsoDateToMillis(obj.optString("date", obj.optString("created_at"))),
                            note = if (obj.isNull("note")) null else obj.optString("note"),
                            productDetailsJson = obj.opt("product_details")?.toString() ?: "[]",
                            isVoiceEntry = obj.optBoolean("is_voice_entry", false),
                            attachmentUri = if (obj.isNull("attachment_url")) null else obj.optString("attachment_url"),
                            paymentMethod = obj.optString("payment_method", "Cash"),
                            invoiceNo = if (obj.isNull("invoice_no")) null else obj.optString("invoice_no"),
                            isSynced = true
                        )
                    )
                }
            }
            if (txs.isNotEmpty()) {
                dao.insertLedgerTransactions(txs)
            }
            true
        } else {
            false
        }
    }

    suspend fun syncPosSalesFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchRecords(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                tableName = "pos_sales",
                selectQuery = "*",
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch pos sales: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching pos sales", e)
        }

        if (resultData != null) {
            val sales = mutableListOf<PosSaleEntity>()
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                val merchantId = obj.optString("merchant_id", active.id)
                if (merchantId == active.id) {
                    sales.add(
                        PosSaleEntity(
                            id = obj.getString("id"),
                            merchantId = active.id,
                            invoiceNo = obj.optString("invoice_no", "INV-${obj.optString("id").takeLast(6)}"),
                            customerId = if (obj.isNull("customer_id") || obj.optString("customer_id").isBlank()) null else obj.optString("customer_id"),
                            customerName = obj.optString("customer_name", "Walk-in Customer"),
                            customerPhone = obj.optString("customer_phone", ""),
                            subtotal = obj.optDouble("subtotal", 0.0),
                            discount = obj.optDouble("discount", 0.0),
                            netTotal = obj.optDouble("net_total", 0.0),
                            cashReceived = obj.optDouble("cash_received", 0.0),
                            changeDue = obj.optDouble("change_due", 0.0),
                            paymentMethod = obj.optString("payment_method", "Cash"),
                            paymentStatus = obj.optString("payment_status", "PAID"),
                            itemCount = obj.optInt("item_count", 1),
                            cartItemsJson = obj.opt("cart_items")?.toString() ?: "[]",
                            timestamp = parseIsoDateToMillis(obj.optString("timestamp", obj.optString("created_at"))),
                            isSynced = true
                        )
                    )
                }
            }
            if (sales.isNotEmpty()) {
                dao.insertPosSales(sales)
            }
            true
        } else {
            false
        }
    }

    suspend fun syncMerchantNumbersFromSupabase(): Boolean = withContext(Dispatchers.IO) {
        val active = getAuthenticatedSupabaseProfile() ?: return@withContext false
        if (active.supabaseUrl.isEmpty() || active.anonKey.isEmpty()) return@withContext false

        var resultData: org.json.JSONArray? = null
        try {
            com.example.data.remote.SupabaseClient.fetchRecords(
                url = active.supabaseUrl,
                anonKey = active.anonKey,
                token = active.authSessionToken,
                tableName = "merchant_numbers",
                selectQuery = "*",
                onSuccess = { jsonArray -> resultData = jsonArray },
                onFailure = { err ->
                    android.util.Log.e("AppRepository", "Failed to fetch merchant numbers: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("AppRepository", "Exception fetching merchant numbers", e)
        }

        if (resultData != null) {
            for (i in 0 until resultData!!.length()) {
                val obj = resultData!!.getJSONObject(i)
                val merchantId = obj.optString("merchant_id", active.id)
                if (merchantId == active.id) {
                    val numStr = obj.optString("number")
                    if (numStr.isNotBlank()) {
                        dao.upsertMerchantNumber(
                            MerchantNumberEntity(
                                number = numStr,
                                merchantId = active.id,
                                method = obj.optString("type", "bKash"),
                                accountType = obj.optString("account_type", "Personal"),
                                isActive = obj.optBoolean("active", true),
                                isDefault = obj.optBoolean("is_default", false),
                                qrCodeUrl = if (obj.isNull("qr_code_url")) null else obj.optString("qr_code_url"),
                                updatedAt = parseIsoDateToMillis(obj.optString("created_at"))
                            )
                        )
                    }
                }
            }
            true
        } else {
            false
        }
    }

    // Product Variants & QR Code Operations
    fun observeProductVariants(merchantId: String): Flow<List<ProductVariantEntity>> = dao.observeProductVariants(merchantId)
    suspend fun getVariantsByProductId(productId: String, merchantId: String): List<ProductVariantEntity> =
        dao.getVariantsByProductId(productId, merchantId)
    suspend fun getVariantByQrCode(qrCode: String, merchantId: String): ProductVariantEntity? =
        dao.getVariantByQrCode(qrCode, merchantId)
    suspend fun getProductByCode(code: String, merchantId: String): ProductItemEntity? =
        dao.getProductByCode(code, merchantId)
    suspend fun insertProductVariant(variant: ProductVariantEntity) = dao.insertProductVariant(variant)
    suspend fun insertProductVariants(variants: List<ProductVariantEntity>) = dao.insertProductVariants(variants)
    suspend fun deleteProductVariantById(id: String) = dao.deleteProductVariantById(id)
    suspend fun createProductWithVariants(
        product: ProductItemEntity,
        variants: List<ProductVariantEntity>,
        transactions: List<StockTransactionEntity>
    ) = dao.createProductWithVariantsAtomic(product, variants, transactions)
    suspend fun createProductWithOpeningStock(product: ProductItemEntity, openingMovement: StockTransactionEntity?) =
        dao.createProductWithOpeningStockAtomic(product, openingMovement)

    // Stock Transactions
    fun observeStockTransactions(merchantId: String): Flow<List<StockTransactionEntity>> = dao.observeStockTransactions(merchantId)
    
    suspend fun recordStockTransaction(tx: StockTransactionEntity) {
        dao.recordStockTransactionsAtomic(listOf(tx))
    }
    suspend fun recordStockTransactions(transactions: List<StockTransactionEntity>) =
        dao.recordStockTransactionsAtomic(transactions)
    suspend fun insertStockTransactions(transactions: List<StockTransactionEntity>) =
        dao.insertStockTransactions(transactions)

    // Expenses Operations
    fun observeExpenses(merchantId: String): Flow<List<ExpenseEntity>> = dao.observeExpenses(merchantId)
    suspend fun insertExpense(expense: ExpenseEntity) = dao.insertExpense(expense)
    suspend fun insertExpenses(expenses: List<ExpenseEntity>) = dao.insertExpenses(expenses)
    suspend fun deleteExpenseById(id: String) = dao.deleteExpenseById(id)

    // Loans Operations
    fun observeLoans(merchantId: String): Flow<List<BusinessLoanEntity>> = dao.observeLoans(merchantId)
    suspend fun insertLoan(loan: BusinessLoanEntity) = dao.insertLoan(loan)
    suspend fun insertLoans(loans: List<BusinessLoanEntity>) = dao.insertLoans(loans)
    suspend fun updateLoanStatus(id: String, status: String, disbursedAt: Long?) = dao.updateLoanStatus(id, status, disbursedAt)

    fun observeDpsAccounts(merchantId: String): Flow<List<DpsAccountEntity>> = dao.observeDpsAccounts(merchantId)
    fun observeFinanceInstallments(merchantId: String): Flow<List<FinanceInstallmentEntity>> =
        dao.observeFinanceInstallments(merchantId)
    suspend fun createDpsAccount(account: DpsAccountEntity, installments: List<FinanceInstallmentEntity>) =
        dao.createDpsAccountAtomic(account, installments)
    suspend fun restoreDpsAccounts(accounts: List<DpsAccountEntity>) = dao.restoreDpsAccounts(accounts)
    suspend fun restoreFinanceInstallments(installments: List<FinanceInstallmentEntity>) =
        dao.restoreFinanceInstallments(installments)
    suspend fun createLoanWithSchedule(loan: BusinessLoanEntity, installments: List<FinanceInstallmentEntity>) =
        dao.createLoanWithScheduleAtomic(loan, installments)
    suspend fun markFinanceInstallmentPaid(
        installmentId: String,
        merchantId: String,
        paidAt: Long,
        paymentMethod: String,
        paymentReference: String
    ): Boolean = dao.markFinanceInstallmentPaid(installmentId, merchantId, paidAt, paymentMethod, paymentReference) == 1
    suspend fun markDpsAccountSynced(id: String, merchantId: String) = dao.markDpsAccountSynced(id, merchantId)
    suspend fun markLoanSynced(id: String, merchantId: String) = dao.markLoanSynced(id, merchantId)
    suspend fun markFinanceInstallmentSynced(id: String, merchantId: String) =
        dao.markFinanceInstallmentSynced(id, merchantId)

    // POS Sales Operations
    fun observePosSales(merchantId: String): Flow<List<PosSaleEntity>> = dao.observePosSales(merchantId)
    suspend fun insertPosSale(sale: PosSaleEntity) = dao.insertPosSale(sale)
    suspend fun insertPosSales(sales: List<PosSaleEntity>) = dao.insertPosSales(sales)
    suspend fun checkoutPosSale(
        sale: PosSaleEntity,
        stockMovements: List<StockTransactionEntity>,
        creditLedgerEntry: LedgerTransactionEntity?
    ) = dao.checkoutPosSaleAtomic(sale, stockMovements, creditLedgerEntry)

    fun observeMerchantNotifications(merchantId: String): Flow<List<MerchantNotificationEntity>> =
        dao.observeMerchantNotifications(merchantId)
    suspend fun upsertMerchantNotifications(notifications: List<MerchantNotificationEntity>) =
        dao.upsertMerchantNotifications(notifications)
    suspend fun upsertMerchantNotification(notification: MerchantNotificationEntity) =
        dao.upsertMerchantNotification(notification)
    suspend fun markMerchantNotificationRead(id: String, merchantId: String): Boolean =
        dao.markMerchantNotificationRead(id, merchantId, System.currentTimeMillis()) == 1
    suspend fun markAllMerchantNotificationsRead(merchantId: String): Int =
        dao.markAllMerchantNotificationsRead(merchantId, System.currentTimeMillis())
    suspend fun deletePosSaleById(id: String) = dao.deletePosSaleById(id)

    // Business Analytics Operations
    fun observeBusinessAnalytics(merchantId: String): Flow<BusinessAnalyticsEntity?> = dao.observeBusinessAnalytics(merchantId)
    suspend fun insertBusinessAnalytics(analytics: BusinessAnalyticsEntity) = dao.insertBusinessAnalytics(analytics)

    // Employees, merchant numbers, and form data are Room-backed so their screens
    // do not lose edits when the process is killed or the network is unavailable.
    fun observeEmployees(merchantId: String): Flow<List<EmployeeEntity>> = dao.observeEmployees(merchantId)
    suspend fun upsertEmployee(employee: EmployeeEntity) = dao.upsertEmployee(employee)
    suspend fun upsertEmployees(employees: List<EmployeeEntity>) = dao.upsertEmployees(employees)
    suspend fun deleteEmployee(id: String) = dao.deleteEmployee(id)

    fun observeMerchantNumbers(merchantId: String): Flow<List<MerchantNumberEntity>> =
        dao.observeMerchantNumbers(merchantId)
    suspend fun getMerchantNumbers(merchantId: String): List<MerchantNumberEntity> = dao.getMerchantNumbers(merchantId)
    suspend fun upsertMerchantNumber(number: MerchantNumberEntity) = dao.upsertMerchantNumber(number)
    suspend fun setDefaultMerchantNumber(merchantId: String, number: MerchantNumberEntity) {
        dao.setDefaultMerchantNumber(merchantId, number)
    }
    suspend fun deleteMerchantNumber(merchantId: String, number: String) =
        dao.deleteMerchantNumber(merchantId, number)

    fun observePaymentFormCache(merchantId: String): Flow<List<PaymentFormCacheEntity>> =
        dao.observePaymentFormCache(merchantId)
    suspend fun upsertPaymentFormCache(form: PaymentFormCacheEntity) = dao.upsertPaymentFormCache(form)
    suspend fun upsertPaymentFormCaches(forms: List<PaymentFormCacheEntity>) = dao.upsertPaymentFormCaches(forms)
    suspend fun deletePaymentFormCache(id: String) = dao.deletePaymentFormCache(id)

    fun observeFormSubmissionCache(merchantId: String): Flow<List<FormSubmissionCacheEntity>> =
        dao.observeFormSubmissionCache(merchantId)
    suspend fun upsertFormSubmissionCache(submission: FormSubmissionCacheEntity) =
        dao.upsertFormSubmissionCache(submission)
    suspend fun upsertFormSubmissionCaches(submissions: List<FormSubmissionCacheEntity>) =
        dao.upsertFormSubmissionCaches(submissions)

    // Outbox SMS & Automated Due Campaigns
    fun observeCustomersWithDue(merchantId: String): Flow<List<CustomerEntity>> = dao.observeCustomersWithDue(merchantId)
    suspend fun getCustomersWithDue(merchantId: String): List<CustomerEntity> = dao.getCustomersWithDue(merchantId)
    suspend fun getAllCustomersList(merchantId: String): List<CustomerEntity> = dao.getAllCustomersList(merchantId)
    suspend fun queueOutboxSms(sms: OutboxSmsEntity): Long = dao.insertOutboxSms(sms)
    suspend fun queueOutboxSmsList(smsList: List<OutboxSmsEntity>): List<Long> = dao.insertOutboxSmsList(smsList)
    suspend fun updateOutboxSmsStatus(id: String, status: String, sentAt: Long? = null, errorMessage: String? = null) =
        dao.updateOutboxSmsStatus(id, status, sentAt, errorMessage)
    suspend fun deleteOutboxSms(id: String): Int = dao.deleteOutboxSms(id)
    suspend fun clearOutboxSms(merchantId: String): Int = dao.clearOutboxSms(merchantId)
}
