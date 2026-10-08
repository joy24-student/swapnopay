package com.abirluxe.shop.fcm

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.BitmapFactory
import android.media.RingtoneManager
import android.os.PowerManager
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
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

        val data = remoteMessage.data
        val notification = remoteMessage.notification

        val type = data["type"] ?: data["action"] ?: ""
        val title = data["title"] ?: notification?.title ?: getString(R.string.app_name)
        val body = data["body"] ?: data["message"] ?: notification?.body ?: ""
        val targetUrl = data["url"] ?: data["target_url"] ?: ""

        if (type.equals("call", ignoreCase = true) || data.containsKey("incoming_call")) {
            // Instant Store Incoming Call trigger
            triggerInstantIncomingCall(title, body)
        } else {
            // Regular Instant Status Bar Push Notification
            showStatusBarNotification(title, body, targetUrl)
        }
    }

    private fun triggerInstantIncomingCall(callerName: String, callNote: String) {
        // Wake the phone up even if locked or user is not using the phone
        try {
            val powerManager = getSystemService(Context.POWER_SERVICE) as? PowerManager
            val wakeLock = powerManager?.newWakeLock(
                PowerManager.SCREEN_BRIGHT_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP,
                "abirluxe:IncomingCallWakeLock"
            )
            wakeLock?.acquire(15000L) // 15 seconds wake lock
        } catch (e: Exception) {
            e.printStackTrace()
        }

        val callIntent = Intent(this, IncomingCallActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra("caller_name", callerName)
            putExtra("call_note", callNote)
        }

        val fullScreenPendingIntent = PendingIntent.getActivity(
            this,
            1001,
            callIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)

        val callNotification = NotificationCompat.Builder(this, AbirLuxeApp.CHANNEL_ID_CALLS)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(callerName)
            .setContentText(if (callNote.isNotBlank()) callNote else getString(R.string.incoming_call_subtitle))
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setSound(soundUri)
            .setVibrate(longArrayOf(0, 1000, 1000, 1000, 1000))
            .setFullScreenIntent(fullScreenPendingIntent, true)
            .setContentIntent(fullScreenPendingIntent)
            .setAutoCancel(true)
            .setColor(ContextCompat.getColor(this, R.color.brand_gold))
            .build()

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.notify(9999, callNotification)

        // Directly launch the activity to ensure it surfaces immediately
        try {
            startActivity(callIntent)
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun showStatusBarNotification(title: String, message: String, url: String) {
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
        val largeIcon = BitmapFactory.decodeResource(resources, R.drawable.app_logo)

        val notification = NotificationCompat.Builder(this, AbirLuxeApp.CHANNEL_ID_ALERTS)
            .setSmallIcon(R.drawable.ic_notification)
            .setLargeIcon(largeIcon)
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
            .build()

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val notificationId = (System.currentTimeMillis() % 100000).toInt()
        notificationManager.notify(notificationId, notification)
    }
}
