package com.abirluxe.admin.fcm

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.BitmapFactory
import android.media.RingtoneManager
import android.os.Build
import android.os.PowerManager
import android.provider.Settings
import androidx.core.app.NotificationCompat
import androidx.core.app.Person
import androidx.core.content.ContextCompat
import androidx.core.graphics.drawable.IconCompat
import com.abirluxe.admin.AbirAdminApp
import com.abirluxe.admin.IncomingCallActivity
import com.abirluxe.admin.MainActivity
import com.abirluxe.admin.R
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

class AbirAdminFirebaseMessagingService : FirebaseMessagingService() {

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
                        put("device_type", "admin_android")
                    }
                    val body = json.toString().toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
                    val request = Request.Builder()
                        .url(BACKEND_URL)
                        .post(body)
                        .build()

                    httpClient.newCall(request).execute().use { _ -> }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        val prefs = getSharedPreferences("abir_admin_prefs", Context.MODE_PRIVATE)
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

        val threadId = data["thread_id"] ?: ""
        if (type.equals("call", ignoreCase = true) || data.containsKey("incoming_call")) {
            // Instant Incoming Customer Call trigger (Direct screen wake + CallStyle heads-up)
            triggerInstantIncomingCall(title, body, targetUrl, threadId)
        } else if (type.equals("order", ignoreCase = true) || data.containsKey("order_id")) {
            // Realtime New Order Alert
            showNewOrderNotification(title, body, targetUrl)
        } else {
            // General Admin Status Bar Notification (Inquiries, Alerts)
            showAdminAlertNotification(title, body, targetUrl)
        }
    }

    private fun triggerInstantIncomingCall(callerName: String, callNote: String, targetUrl: String = "", threadId: String = "") {
        // Wake the screen up even if phone is locked or asleep
        try {
            val powerManager = getSystemService(Context.POWER_SERVICE) as? PowerManager
            @Suppress("DEPRECATION")
            val wakeLock = powerManager?.newWakeLock(
                PowerManager.FULL_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP or PowerManager.ON_AFTER_RELEASE,
                "abiradmin:IncomingCallWakeLock"
            )
            wakeLock?.acquire(30000L)
        } catch (e: Exception) {
            e.printStackTrace()
        }

        val defaultCallUrl = "https://shop.swapnopay.top/abir-luxe-shop-bd-0558/admin/live-chat.php"
        var baseCallUrl = if (targetUrl.isNotBlank()) targetUrl else defaultCallUrl
        if (threadId.isNotBlank() && !baseCallUrl.contains("thread_id=")) {
            baseCallUrl += if (baseCallUrl.contains("?")) "&thread_id=$threadId" else "?thread_id=$threadId"
        }
        val resolvedCallUrl = if (!baseCallUrl.contains("auto_answer=")) {
            if (baseCallUrl.contains("?")) "$baseCallUrl&auto_answer=1" else "$baseCallUrl?auto_answer=1"
        } else {
            baseCallUrl
        }

        val callIntent = Intent(this, IncomingCallActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or
                    Intent.FLAG_ACTIVITY_CLEAR_TOP or
                    Intent.FLAG_ACTIVITY_SINGLE_TOP or
                    Intent.FLAG_ACTIVITY_REORDER_TO_FRONT
            putExtra("caller_name", callerName)
            putExtra("call_note", callNote)
            putExtra("thread_id", threadId)
            putExtra("target_url", resolvedCallUrl)
        }

        val fullScreenPendingIntent = PendingIntent.getActivity(
            this,
            2001,
            callIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val declineIntent = Intent(this, AdminCallActionReceiver::class.java).apply {
            action = AdminCallActionReceiver.ACTION_DECLINE_CALL
        }
        val declinePendingIntent = PendingIntent.getBroadcast(
            this,
            2002,
            declineIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val answerCallIntent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra("call_answered", true)
            putExtra("thread_id", threadId)
            putExtra("target_url", resolvedCallUrl)
        }
        val answerPendingIntent = PendingIntent.getActivity(
            this,
            2003,
            answerCallIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
        val shopLogoBitmap = try {
            BitmapFactory.decodeResource(resources, R.drawable.app_logo)
        } catch (e: Exception) {
            null
        }

        val callerPerson = Person.Builder()
            .setName(callerName)
            .setIcon(IconCompat.createWithResource(this, R.drawable.app_logo))
            .setImportant(true)
            .build()

        val callStyle = NotificationCompat.CallStyle.forIncomingCall(
            callerPerson,
            declinePendingIntent,
            answerPendingIntent
        )

        val inForeground = try { AbirAdminApp.instance.isAppInForeground } catch (e: Exception) { false }

        val callNotificationBuilder = NotificationCompat.Builder(this, AbirAdminApp.CHANNEL_ID_CALLS)
            .setSmallIcon(R.drawable.ic_stat_shop)
            .setContentTitle(callerName)
            .setContentText(if (callNote.isNotBlank()) callNote else getString(R.string.incoming_call_subtitle))
            .setStyle(callStyle)
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setOngoing(true)
            .setAutoCancel(true)
            .setColor(ContextCompat.getColor(this, R.color.brand_gold))
            .addAction(R.drawable.ic_call, "Answer", answerPendingIntent)
            .addAction(R.drawable.ic_call_end, "Decline", declinePendingIntent)

        if (!inForeground) {
            callNotificationBuilder.setSound(soundUri)
            callNotificationBuilder.setVibrate(longArrayOf(0, 1000, 1000, 1000, 1000, 1000))
            callNotificationBuilder.setFullScreenIntent(fullScreenPendingIntent, true)
            callNotificationBuilder.setContentIntent(fullScreenPendingIntent)
        } else {
            callNotificationBuilder.setContentIntent(answerPendingIntent)
        }

        if (shopLogoBitmap != null) {
            callNotificationBuilder.setLargeIcon(shopLogoBitmap)
        }

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.notify(9999, callNotificationBuilder.build())

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
    }

    private fun showNewOrderNotification(title: String, message: String, url: String) {
        val destinationUrl = if (url.isNotBlank()) url else "https://shop.swapnopay.top/abir-luxe-shop-bd-0558/admin/order.php"
        val clickIntent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra("target_url", destinationUrl)
        }

        val pendingIntent = PendingIntent.getActivity(
            this,
            System.currentTimeMillis().toInt(),
            clickIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
        val largeIcon = BitmapFactory.decodeResource(resources, R.drawable.app_logo)

        val notification = NotificationCompat.Builder(this, AbirAdminApp.CHANNEL_ID_ORDERS)
            .setSmallIcon(R.drawable.ic_stat_shop)
            .setLargeIcon(largeIcon)
            .setContentTitle(if (title.isNotBlank()) title else "🛍️ New Customer Order")
            .setContentText(message)
            .setStyle(NotificationCompat.BigTextStyle().bigText(message))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_EVENT)
            .setAutoCancel(true)
            .setSound(soundUri)
            .setVibrate(longArrayOf(0, 300, 150, 300))
            .setColor(ContextCompat.getColor(this, R.color.brand_gold))
            .setContentIntent(pendingIntent)
            .build()

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val notificationId = (System.currentTimeMillis() % 100000).toInt()
        notificationManager.notify(notificationId, notification)
    }

    private fun showAdminAlertNotification(title: String, message: String, url: String) {
        val destinationUrl = if (url.isNotBlank()) url else "https://shop.swapnopay.top/abir-luxe-shop-bd-0558/admin/live-chat.php"
        val clickIntent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra("target_url", destinationUrl)
        }

        val pendingIntent = PendingIntent.getActivity(
            this,
            System.currentTimeMillis().toInt(),
            clickIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
        val largeIcon = BitmapFactory.decodeResource(resources, R.drawable.app_logo)

        val notification = NotificationCompat.Builder(this, AbirAdminApp.CHANNEL_ID_ALERTS)
            .setSmallIcon(R.drawable.ic_stat_shop)
            .setLargeIcon(largeIcon)
            .setContentTitle(title)
            .setContentText(message)
            .setStyle(NotificationCompat.BigTextStyle().bigText(message))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setAutoCancel(true)
            .setSound(soundUri)
            .setVibrate(longArrayOf(0, 200, 200, 200))
            .setColor(ContextCompat.getColor(this, R.color.brand_gold))
            .setContentIntent(pendingIntent)
            .build()

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val notificationId = (System.currentTimeMillis() % 100000).toInt()
        notificationManager.notify(notificationId, notification)
    }
}

