package com.abirluxe.admin

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
import com.abirluxe.admin.fcm.AbirAdminFirebaseMessagingService
import com.google.firebase.messaging.FirebaseMessaging

@SuppressLint("CustomSplashScreen")
class SplashActivity : AppCompatActivity() {

    private val requestStartupPermissionsLauncher =
        registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { _ ->
            proceedToMain()
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_splash)

        val logoView = findViewById<ImageView>(R.id.imgSplashLogo)
        val fadeIn = AlphaAnimation(0f, 1f).apply {
            duration = 800
            fillAfter = true
        }
        logoView.startAnimation(fadeIn)

        initFcmToken()

        Handler(Looper.getMainLooper()).postDelayed({
            checkNotificationPermissionAndProceed()
        }, 1200)
    }

    private fun initFcmToken() {
        try {
            FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                if (task.isSuccessful) {
                    val token = task.result
                    val prefs = getSharedPreferences("abir_admin_prefs", Context.MODE_PRIVATE)
                    prefs.edit().putString("fcm_token", token).apply()
                    AbirAdminFirebaseMessagingService.sendTokenToBackend(applicationContext, token)
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun checkNotificationPermissionAndProceed() {
        val permissionsToAsk = mutableListOf<String>()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
            ) {
                permissionsToAsk.add(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO)
            != PackageManager.PERMISSION_GRANTED
        ) {
            permissionsToAsk.add(Manifest.permission.RECORD_AUDIO)
        }
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
            != PackageManager.PERMISSION_GRANTED
        ) {
            permissionsToAsk.add(Manifest.permission.CAMERA)
        }

        if (permissionsToAsk.isNotEmpty()) {
            requestStartupPermissionsLauncher.launch(permissionsToAsk.toTypedArray())
        } else {
            proceedToMain()
        }
    }

    private fun proceedToMain() {
        val targetUrl = intent?.getStringExtra("target_url")
        val mainIntent = Intent(this, MainActivity::class.java).apply {
            if (!targetUrl.isNullOrBlank()) {
                putExtra("target_url", targetUrl)
            }
        }
        startActivity(mainIntent)
        @Suppress("DEPRECATION")
        overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out)
        finish()
    }
}

