package com.example.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.RingtoneManager
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import com.example.MainActivity
import com.example.R
import com.example.data.repository.AppRepository
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

/**
 * Handles incoming Firebase Cloud Messaging (FCM) push notifications and token refreshes
 * for SwapnoPay merchant & staff apps.
 */
class AppFirebaseMessagingService : FirebaseMessagingService() {

    companion object {
        private const val TAG = "SwapnoPayFCM"
        const val CHANNEL_ID_ALERTS = "swapnopay_alerts_channel"
        const val CHANNEL_ID_TRANSACTIONS = "swapnopay_transactions_channel"
        private const val PREFS_NAME = "swapnopay_fcm_prefs"
        private const val KEY_FCM_TOKEN = "latest_fcm_token"

        fun getSavedToken(context: Context): String? {
            return context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                .getString(KEY_FCM_TOKEN, null)
        }
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d(TAG, "New FCM Token received: $token")

        // Persist token in local preferences
        getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_FCM_TOKEN, token)
            .apply()

        // Sync with backend / Supabase
        syncTokenWithBackend(token)
    }

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        Log.d(TAG, "From: ${remoteMessage.from}")

        val data = remoteMessage.data
        val notification = remoteMessage.notification

        val title = data["title"] ?: notification?.title ?: getString(R.string.app_name)
        val body = data["body"] ?: data["message"] ?: notification?.body ?: "New update received"
        val type = data["type"] ?: data["action"] ?: "general"
        val targetScreen = data["screen"] ?: data["url"] ?: ""
        val messageId = remoteMessage.messageId ?: data["id"] ?: data["trx_id"]

        showPushNotification(title, body, type, targetScreen, messageId)
    }

    private fun showPushNotification(
        title: String,
        body: String,
        type: String,
        targetScreen: String,
        messageId: String? = null
    ) {
        val notifKey = messageId?.takeIf { it.isNotBlank() } ?: "fcm_${(title + body).hashCode()}"
        if (com.example.NotificationHelper.hasBeenNotified(this, notifKey)) {
            Log.d(TAG, "Push notification already posted for key: $notifKey")
            return
        }

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        val channelId = if (type.contains("transaction", ignoreCase = true) || type.contains("payment", ignoreCase = true)) {
            CHANNEL_ID_TRANSACTIONS
        } else {
            CHANNEL_ID_ALERTS
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channelName = if (channelId == CHANNEL_ID_TRANSACTIONS) {
                "Payment & Transaction Alerts"
            } else {
                "Important System Announcements"
            }
            val importance = NotificationManager.IMPORTANCE_HIGH
            val channel = NotificationChannel(channelId, channelName, importance).apply {
                description = "Real-time updates and push notifications for SwapnoPay"
                enableLights(true)
                enableVibration(true)
            }
            notificationManager.createNotificationChannel(channel)
        }

        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("fcm_type", type)
            putExtra("fcm_screen", targetScreen)
        }

        val pendingIntent = PendingIntent.getActivity(
            this,
            System.currentTimeMillis().toInt(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val defaultSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)

        val notificationBuilder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setSound(defaultSoundUri)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)

        val reqCode = (notifKey.hashCode() and 0x7FFFFFFF)
        notificationManager.notify(reqCode, notificationBuilder.build())
        com.example.NotificationHelper.markAsNotified(this, notifKey)
    }

    private fun syncTokenWithBackend(token: String) {
        Log.i(TAG, "Device token syncing to backend & admin: $token")
        try {
            val repository = AppRepository(applicationContext)
            CoroutineScope(Dispatchers.IO).launch {
                try {
                    repository.sendDeviceHeartbeat(applicationContext)
                    Log.i(TAG, "Device heartbeat sent successfully with new FCM token")
                } catch (e: Exception) {
                    Log.w(TAG, "Failed to send device heartbeat on token update: ${e.message}")
                }
            }
        } catch (e: Exception) {
            Log.w(TAG, "Error initiating token sync: ${e.message}")
        }
    }
}

