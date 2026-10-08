package com.abirluxe.shop.bridge

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.webkit.JavascriptInterface
import android.widget.Toast
import com.abirluxe.shop.fcm.AbirFirebaseMessagingService

class AndroidBridge(private val activity: Activity) {

    private val prefs = activity.getSharedPreferences("abir_luxe_prefs", Context.MODE_PRIVATE)

    @JavascriptInterface
    fun isNativeApp(): Boolean {
        return true
    }

    @JavascriptInterface
    fun getFcmToken(): String {
        return prefs.getString("fcm_token", "") ?: ""
    }

    @JavascriptInterface
    fun syncFcmToken(token: String) {
        if (token.isNotBlank()) {
            prefs.edit().putString("fcm_token", token).apply()
            AbirFirebaseMessagingService.sendTokenToBackend(activity, token)
        }
    }

    @JavascriptInterface
    fun saveSessionToken(token: String, username: String) {
        prefs.edit()
            .putString("session_token", token)
            .putString("logged_user", username)
            .apply()
    }

    @JavascriptInterface
    fun getSessionToken(): String {
        return prefs.getString("session_token", "") ?: ""
    }

    @JavascriptInterface
    fun makePhoneCall(phoneNumber: String) {
        activity.runOnUiThread {
            try {
                val cleanNumber = phoneNumber.replace("[^0-9+]".toRegex(), "")
                val dialIntent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$cleanNumber"))
                activity.startActivity(dialIntent)
            } catch (e: Exception) {
                Toast.makeText(activity, "Cannot open dialer: ${e.message}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    @JavascriptInterface
    fun openWhatsApp(phoneNumber: String, message: String) {
        activity.runOnUiThread {
            try {
                val cleanNumber = phoneNumber.replace("[^0-9]".toRegex(), "")
                val url = "https://api.whatsapp.com/send?phone=$cleanNumber&text=${Uri.encode(message)}"
                val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                activity.startActivity(intent)
            } catch (e: Exception) {
                Toast.makeText(activity, "WhatsApp not installed", Toast.LENGTH_SHORT).show()
            }
        }
    }

    @JavascriptInterface
    fun shareProduct(title: String, url: String) {
        activity.runOnUiThread {
            try {
                val shareIntent = Intent(Intent.ACTION_SEND).apply {
                    type = "text/plain"
                    putExtra(Intent.EXTRA_SUBJECT, title)
                    putExtra(Intent.EXTRA_TEXT, "$title\n$url")
                }
                activity.startActivity(Intent.createChooser(shareIntent, "Share via"))
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    @JavascriptInterface
    fun triggerVibrate(milliseconds: Long) {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val vibratorManager = activity.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager
                vibratorManager?.defaultVibrator?.vibrate(
                    VibrationEffect.createOneShot(milliseconds.coerceAtMost(1000), VibrationEffect.DEFAULT_AMPLITUDE)
                )
            } else {
                @Suppress("DEPRECATION")
                val vibrator = activity.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
                @Suppress("DEPRECATION")
                vibrator?.vibrate(milliseconds.coerceAtMost(1000))
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
}
