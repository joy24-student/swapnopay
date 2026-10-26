package com.example

import android.app.Application
import android.util.Log
import com.google.firebase.messaging.FirebaseMessaging

class SwapnoPayApp : Application() {

    override fun onCreate() {
        super.onCreate()

        try {
            // 1. Create system notification channels early so background services & receivers can post immediately
            NotificationHelper.initNotificationChannels(this)
        } catch (e: Exception) {
            Log.e("SwapnoPayApp", "Failed to initialize notification channels: ${e.message}", e)
        }

        try {
            // 2. Enable FCM auto-init and subscribe to admin broadcast topics
            val fcm = FirebaseMessaging.getInstance()
            fcm.isAutoInitEnabled = true
            fcm.subscribeToTopic("all_merchants")
            fcm.subscribeToTopic("announcements")
            fcm.token.addOnCompleteListener { task ->
                if (task.isSuccessful && !task.result.isNullOrBlank()) {
                    val token = task.result
                    getSharedPreferences("swapnopay_fcm_prefs", android.content.Context.MODE_PRIVATE)
                        .edit()
                        .putString("latest_fcm_token", token)
                        .apply()
                    android.util.Log.d("SwapnoPayApp", "FCM token initialized: $token")
                }
            }
        } catch (e: Exception) {
            Log.w("SwapnoPayApp", "FCM setup note: ${e.message}")
        }
    }
}
