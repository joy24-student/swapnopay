package com.abirluxe.admin.fcm

import android.app.NotificationManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

class AdminCallActionReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action ?: return
        if (action == ACTION_DECLINE_CALL) {
            val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.cancel(9999)
        }
    }

    companion object {
        const val ACTION_DECLINE_CALL = "com.abirluxe.admin.ACTION_DECLINE_CALL"
    }
}

