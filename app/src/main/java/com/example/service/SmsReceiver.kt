package com.example.service

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import android.provider.Telephony
import android.util.Log
import com.example.data.repository.AppRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

/**
 * Static manifest BroadcastReceiver to reliably process transaction SMS messages
 * even when SmsMonitoringService is stopped, killed by Doze mode, or during low-memory conditions.
 */
class SmsReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent?) {
        if (intent?.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) return

        val messages = Telephony.Sms.Intents.getMessagesFromIntent(intent) ?: return
        if (messages.isEmpty()) return

        val pendingResult = goAsync()
        val repository = AppRepository(context.applicationContext)

        CoroutineScope(Dispatchers.IO).launch {
            try {
                // Group multipart SMS segments by sender address and concatenate their text
                val fullBodyMap = LinkedHashMap<String, StringBuilder>()
                for (msg in messages) {
                    val sender = msg.originatingAddress ?: continue
                    val body = msg.messageBody ?: ""
                    fullBodyMap.getOrPut(sender) { StringBuilder() }.append(body)
                }

                for ((sender, bodyBuilder) in fullBodyMap) {
                    val fullBody = bodyBuilder.toString()
                    Log.d("SmsReceiver", "Static receiver intercepted full SMS from: $sender (length: ${fullBody.length})")
                    repository.processIncomingSms(sender, fullBody)
                }

                // Ensure foreground monitoring service is active
                try {
                    val serviceIntent = Intent(context, SmsMonitoringService::class.java)
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        context.startForegroundService(serviceIntent)
                    } else {
                        context.startService(serviceIntent)
                    }
                } catch (se: Exception) {
                    Log.w("SmsReceiver", "Could not wake SmsMonitoringService: ${se.message}")
                }
            } catch (e: Exception) {
                Log.e("SmsReceiver", "Error processing incoming SMS via static receiver", e)
            } finally {
                pendingResult.finish()
            }
        }
    }
}
