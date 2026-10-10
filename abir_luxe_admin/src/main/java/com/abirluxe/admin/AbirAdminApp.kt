package com.abirluxe.admin

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.os.Build
import android.webkit.CookieManager

class AbirAdminApp : Application() {

    companion object {
        const val CHANNEL_ID_ORDERS = "abir_admin_orders"
        const val CHANNEL_ID_CALLS = "abir_admin_calls_v3"
        const val CHANNEL_ID_ALERTS = "abir_admin_alerts"
        lateinit var instance: AbirAdminApp
            private set
    }

    var isAppInForeground: Boolean = false
        private set

    override fun onCreate() {
        super.onCreate()
        instance = this

        registerLifecycleTracker()

        // 1. Initialize persistent CookieManager to preserve Admin sessions
        setupPersistentCookies()

        // 2. Register High-Priority Notification & Call Channels
        createNotificationChannels()
    }

    private fun registerLifecycleTracker() {
        registerActivityLifecycleCallbacks(object : ActivityLifecycleCallbacks {
            private var startedCount = 0

            override fun onActivityStarted(activity: android.app.Activity) {
                startedCount++
                isAppInForeground = (startedCount > 0)
            }

            override fun onActivityStopped(activity: android.app.Activity) {
                startedCount = maxOf(0, startedCount - 1)
                isAppInForeground = (startedCount > 0)
            }

            override fun onActivityCreated(activity: android.app.Activity, savedInstanceState: android.os.Bundle?) {}
            override fun onActivityResumed(activity: android.app.Activity) {
                isAppInForeground = true
            }
            override fun onActivityPaused(activity: android.app.Activity) {}
            override fun onActivitySaveInstanceState(activity: android.app.Activity, outState: android.os.Bundle) {}
            override fun onActivityDestroyed(activity: android.app.Activity) {}
        })
    }

    private fun setupPersistentCookies() {
        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.flush()
        }
    }

    private fun createNotificationChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val notificationManager = getSystemService(NotificationManager::class.java)

            // 1. Urgent Channel for Incoming Customer Support Calls
            val callSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
            val callAudioAttributes = AudioAttributes.Builder()
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
                setSound(callSoundUri, callAudioAttributes)
                setBypassDnd(true)
                lockscreenVisibility = android.app.Notification.VISIBILITY_PUBLIC
            }

            // 2. High Priority Channel for Realtime New Orders & Sales Alerts
            val notifSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            val notifAudioAttributes = AudioAttributes.Builder()
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                .build()

            val orderChannel = NotificationChannel(
                CHANNEL_ID_ORDERS,
                getString(R.string.channel_orders_name),
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = getString(R.string.channel_orders_desc)
                enableLights(true)
                lightColor = getColor(R.color.brand_gold)
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 300, 150, 300)
                setSound(notifSoundUri, notifAudioAttributes)
                setShowBadge(true)
                lockscreenVisibility = android.app.Notification.VISIBILITY_PUBLIC
            }

            // 3. Admin Alerts & Inquiries Channel
            val alertChannel = NotificationChannel(
                CHANNEL_ID_ALERTS,
                getString(R.string.channel_alerts_name),
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = getString(R.string.channel_alerts_desc)
                enableLights(true)
                lightColor = getColor(R.color.brand_gold)
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 200, 200, 200)
                setShowBadge(true)
            }

            notificationManager.createNotificationChannel(callChannel)
            notificationManager.createNotificationChannel(orderChannel)
            notificationManager.createNotificationChannel(alertChannel)
        }
    }
}

