package com.abirluxe.shop.fcm

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.media.RingtoneManager
import android.os.Build
import android.os.PowerManager
import android.provider.Settings
import androidx.core.app.NotificationCompat
import androidx.core.app.Person
import androidx.core.content.ContextCompat
import androidx.core.graphics.drawable.IconCompat
import com.abirluxe.shop.AbirLuxeApp
import com.abirluxe.shop.IncomingCallActivity
import com.abirluxe.shop.MainActivity
import com.abirluxe.shop.R
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class AbirFirebaseMessagingService : FirebaseMessagingService() {

    companion object {
        private const val BACKEND_URL = "https://shop.swapnopay.top/abir-luxe-shop-bd-0558/save-fcm-token.php"
        private val httpClient = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .build()

        fun sendTokenToBackend(context: Context, token: String) {
            if (token.isBlank()) return
            CoroutineScope(Dispatchers.IO).launch {
                try {
                    val json = JSONObject().apply {
                        put("token", token)
                        put("device_type", "android")
                    }
                    val body = json.toString().toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
                    val request = Request.Builder()
                        .url(BACKEND_URL)
                        .post(body)
                        .build()

                    httpClient.newCall(request).execute().use { response ->
                        // Success or silently log
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        val prefs = getSharedPreferences("abir_luxe_prefs", Context.MODE_PRIVATE)
        prefs.edit().putString("fcm_token", token).apply()
        sendTokenToBackend(applicationContext, token)
    }

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)

        try {
            val data = remoteMessage.data
            val notification = remoteMessage.notification

            val type = data["type"] ?: data["action"] ?: ""
            val title = data["title"] ?: notification?.title ?: getString(R.string.app_name)
            val body = data["body"] ?: data["message"] ?: notification?.body ?: ""
            val targetUrl = data["url"] ?: data["target_url"] ?: ""

            if (type.equals("call", ignoreCase = true) || data.containsKey("incoming_call")) {
                // Instant Store Incoming Call trigger
                triggerInstantIncomingCall(title, body, targetUrl)
            } else {
                // Regular Instant Status Bar Push Notification
                showStatusBarNotification(title, body, targetUrl)
            }
        } catch (e: Throwable) {
            e.printStackTrace()
        }
    }

    private fun getSafeAppLogo(): Bitmap? {
        return try {
            val options = BitmapFactory.Options().apply {
                inSampleSize = 4 // Downsample 800x800 -> 200x200 to prevent TransactionTooLargeException
            }
            val raw = BitmapFactory.decodeResource(resources, R.drawable.app_logo, options) ?: return null
            Bitmap.createScaledBitmap(raw, 128, 128, true)
        } catch (e: Throwable) {
            null
        }
    }

    private fun triggerInstantIncomingCall(callerName: String, callNote: String, targetUrl: String = "") {
        try {
            // Wake the screen up even if phone is locked, asleep, or in pocket
            try {
                val powerManager = getSystemService(Context.POWER_SERVICE) as? PowerManager
                @Suppress("DEPRECATION")
                val wakeLock = powerManager?.newWakeLock(
                    PowerManager.FULL_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP or PowerManager.ON_AFTER_RELEASE,
                    "abirluxe:IncomingCallWakeLock"
                )
                wakeLock?.acquire(30000L) // 30 seconds wake lock to cover call ringing duration
            } catch (e: Exception) {
                e.printStackTrace()
            }

            val callIntent = Intent(this, IncomingCallActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or
                        Intent.FLAG_ACTIVITY_CLEAR_TOP or
                        Intent.FLAG_ACTIVITY_SINGLE_TOP or
                        Intent.FLAG_ACTIVITY_REORDER_TO_FRONT
                putExtra("caller_name", callerName)
                putExtra("call_note", callNote)
                putExtra("target_url", targetUrl)
            }

            val fullScreenPendingIntent = PendingIntent.getActivity(
                this,
                1001,
                callIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val declineIntent = Intent(this, CallActionReceiver::class.java).apply {
                action = CallActionReceiver.ACTION_DECLINE_CALL
            }
            val declinePendingIntent = PendingIntent.getBroadcast(
                this,
                1002,
                declineIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val defaultCallUrl = "https://shop.swapnopay.top/abir-luxe-shop-bd-0558/messages.php?auto_answer=1"
            val resolvedCallUrl = if (targetUrl.isNotBlank()) {
                if (targetUrl.contains("?")) "$targetUrl&auto_answer=1" else "$targetUrl?auto_answer=1"
            } else {
                defaultCallUrl
            }

            val answerCallIntent = Intent(this, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                putExtra("call_answered", true)
                putExtra("target_url", resolvedCallUrl)
            }
            val answerPendingIntent = PendingIntent.getActivity(
                this,
                1003,
                answerCallIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
            val inForeground = try { AbirLuxeApp.instance.isAppInForeground } catch (e: Exception) { false }
            val shopLogoBitmap = getSafeAppLogo()

            val callNotificationBuilder = NotificationCompat.Builder(this, AbirLuxeApp.CHANNEL_ID_CALLS)
                .setSmallIcon(R.drawable.ic_stat_shop)
                .setContentTitle(callerName)
                .setContentText(if (callNote.isNotBlank()) callNote else getString(R.string.incoming_call_subtitle))
                .setPriority(NotificationCompat.PRIORITY_MAX)
                .setCategory(NotificationCompat.CATEGORY_CALL)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
                .setOngoing(true)
                .setAutoCancel(true)
                .setColor(ContextCompat.getColor(this, R.color.brand_gold))

            if (shopLogoBitmap != null) {
                callNotificationBuilder.setLargeIcon(shopLogoBitmap)
            }

            // Safe CallStyle attempt with fallback
            var callStyleApplied = false
            try {
                val callerPerson = Person.Builder()
                    .setName(callerName)
                    .setIcon(IconCompat.createWithResource(this, R.drawable.ic_stat_shop))
                    .setImportant(true)
                    .build()

                val callStyle = NotificationCompat.CallStyle.forIncomingCall(
                    callerPerson,
                    declinePendingIntent,
                    answerPendingIntent
                )
                callNotificationBuilder.setStyle(callStyle)
                callStyleApplied = true
            } catch (e: Throwable) {
                callNotificationBuilder.setStyle(
                    NotificationCompat.BigTextStyle().bigText(if (callNote.isNotBlank()) callNote else "Incoming Call")
                )
            }

            if (!callStyleApplied) {
                callNotificationBuilder.addAction(R.drawable.ic_call, "Answer", answerPendingIntent)
                callNotificationBuilder.addAction(R.drawable.ic_call_end, "Decline", declinePendingIntent)
            }

            if (!inForeground) {
                callNotificationBuilder.setSound(soundUri)
                callNotificationBuilder.setVibrate(longArrayOf(0, 1000, 1000, 1000, 1000))
                callNotificationBuilder.setFullScreenIntent(fullScreenPendingIntent, true)
                callNotificationBuilder.setContentIntent(fullScreenPendingIntent)
            } else {
                callNotificationBuilder.setContentIntent(answerPendingIntent)
            }

            val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            try {
                notificationManager.notify(9999, callNotificationBuilder.build())
            } catch (e: Throwable) {
                // Fallback to simple notification if CallStyle or rich builder failed
                try {
                    val fallback = NotificationCompat.Builder(this, AbirLuxeApp.CHANNEL_ID_CALLS)
                        .setSmallIcon(R.drawable.ic_stat_shop)
                        .setContentTitle(callerName)
                        .setContentText(if (callNote.isNotBlank()) callNote else "Incoming Store Call")
                        .setPriority(NotificationCompat.PRIORITY_MAX)
                        .setCategory(NotificationCompat.CATEGORY_CALL)
                        .setFullScreenIntent(fullScreenPendingIntent, true)
                        .setContentIntent(answerPendingIntent)
                        .addAction(R.drawable.ic_call, "Answer", answerPendingIntent)
                        .addAction(R.drawable.ic_call_end, "Decline", declinePendingIntent)
                        .build()
                    notificationManager.notify(9999, fallback)
                } catch (fallbackEx: Throwable) {
                    fallbackEx.printStackTrace()
                }
            }

            // Directly launch the activity ONLY when phone is asleep, locked, or app is in background
            if (!inForeground) {
                try {
                    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q || Settings.canDrawOverlays(this)) {
                        startActivity(callIntent)
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        } catch (globalEx: Throwable) {
            globalEx.printStackTrace()
        }
    }

    private fun showStatusBarNotification(title: String, message: String, url: String) {
        try {
            val clickIntent = Intent(this, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                if (url.isNotBlank()) {
                    putExtra("target_url", url)
                }
            }

            val pendingIntent = PendingIntent.getActivity(
                this,
                System.currentTimeMillis().toInt(),
                clickIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            val largeIcon = getSafeAppLogo()

            val notificationBuilder = NotificationCompat.Builder(this, AbirLuxeApp.CHANNEL_ID_ALERTS)
                .setSmallIcon(R.drawable.ic_stat_shop)
                .setContentTitle(title)
                .setContentText(message)
                .setStyle(NotificationCompat.BigTextStyle().bigText(message))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setCategory(NotificationCompat.CATEGORY_MESSAGE)
                .setAutoCancel(true)
                .setSound(soundUri)
                .setVibrate(longArrayOf(0, 250, 250, 250))
                .setColor(ContextCompat.getColor(this, R.color.brand_gold))
                .setContentIntent(pendingIntent)

            if (largeIcon != null) {
                notificationBuilder.setLargeIcon(largeIcon)
            }

            val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            val notificationId = (System.currentTimeMillis() % 100000).toInt()
            notificationManager.notify(notificationId, notificationBuilder.build())
        } catch (e: Throwable) {
            e.printStackTrace()
        }
    }
}
