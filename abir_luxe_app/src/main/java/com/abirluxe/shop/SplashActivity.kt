package com.abirluxe.shop

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.animation.AlphaAnimation
import android.widget.ImageView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.abirluxe.shop.fcm.AbirFirebaseMessagingService
import com.google.firebase.messaging.FirebaseMessaging

@SuppressLint("CustomSplashScreen")
class SplashActivity : AppCompatActivity() {

    private val requestNotificationPermissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { _ ->
            proceedToMain()
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_splash)

        // Subtle fade-in animation on big logo
        val logoView = findViewById<ImageView>(R.id.imgSplashLogo)
        val fadeIn = AlphaAnimation(0f, 1f).apply {
            duration = 800
            fillAfter = true
        }
        logoView.startAnimation(fadeIn)

        // Initialize Firebase Messaging Token
        initFcmToken()

        // Request notification permission if Android 13+
        Handler(Looper.getMainLooper()).postDelayed({
            checkNotificationPermissionAndProceed()
        }, 1200)
    }

    private fun initFcmToken() {
        try {
            FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                if (task.isSuccessful) {
                    val token = task.result
                    val prefs = getSharedPreferences("abir_luxe_prefs", Context.MODE_PRIVATE)
                    prefs.edit().putString("fcm_token", token).apply()
                    AbirFirebaseMessagingService.sendTokenToBackend(applicationContext, token)
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun checkNotificationPermissionAndProceed() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
            ) {
                requestNotificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
                return
            }
        }
        proceedToMain()
    }

    private fun proceedToMain() {
        val targetUrl = intent?.getStringExtra("target_url")
        val mainIntent = Intent(this, MainActivity::class.java).apply {
            if (!targetUrl.isNullOrBlank()) {
                putExtra("target_url", targetUrl)
            }
        }
        startActivity(mainIntent)
        overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out)
        finish()
    }
}
