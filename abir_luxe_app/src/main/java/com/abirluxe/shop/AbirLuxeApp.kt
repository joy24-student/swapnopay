package com.abirluxe.shop

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.os.Build
import android.webkit.CookieManager

class AbirLuxeApp : Application() {

    companion object {
        const val CHANNEL_ID_ALERTS = "abir_luxe_store_alerts"
        const val CHANNEL_ID_CALLS = "abir_luxe_calls_v3"
        lateinit var instance: AbirLuxeApp
            private set
    }

    override fun onCreate() {
        super.onCreate()
        instance = this

        // 1. Initialize persistent CookieManager to preserve user login sessions
        setupPersistentCookies()

        // 2. Register High-Priority Notification & Call Channels
        createNotificationChannels()
    }

    private fun setupPersistentCookies() {
        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        // Flush cookies immediately to ensure no session is lost
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.flush()
        }
    }

    private fun createNotificationChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val notificationManager = getSystemService(NotificationManager::class.java)

            // Clean up obsolete channels so fresh sound and max importance take effect
            try { notificationManager.deleteNotificationChannel("abir_luxe_store_calls") } catch (e: Exception) {}
            try { notificationManager.deleteNotificationChannel("abir_luxe_store_calls_v2") } catch (e: Exception) {}

            // High Priority Channel for Deals, Orders & Admin Alerts
            val alertChannel = NotificationChannel(
                CHANNEL_ID_ALERTS,
                getString(R.string.channel_alerts_name),
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = getString(R.string.channel_alerts_desc)
                enableLights(true)
                lightColor = getColor(R.color.brand_gold)
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 250, 250, 250)
                setShowBadge(true)
            }

            // Urgent Channel for Instant Store Incoming Calls
            val callSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
            val audioAttributes = AudioAttributes.Builder()
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                .build()

            val callChannel = NotificationChannel(
                CHANNEL_ID_CALLS,
                getString(R.string.channel_calls_name),
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = getString(R.string.channel_calls_desc)
                enableLights(true)
                lightColor = getColor(R.color.brand_gold)
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 1000, 1000, 1000, 1000, 1000)
                setSound(callSoundUri, audioAttributes)
                setBypassDnd(true)
                lockscreenVisibility = android.app.Notification.VISIBILITY_PUBLIC
            }

            notificationManager.createNotificationChannel(alertChannel)
            notificationManager.createNotificationChannel(callChannel)
        }
    }
}
