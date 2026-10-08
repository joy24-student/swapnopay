package com.abirluxe.shop

import android.app.KeyguardManager
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.Ringtone
import android.media.RingtoneManager
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.view.WindowManager
import android.view.animation.AnimationUtils
import android.widget.ImageButton
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

class IncomingCallActivity : AppCompatActivity() {

    private var ringtone: Ringtone? = null
    private var vibrator: Vibrator? = null
    private val handler = Handler(Looper.getMainLooper())
    private val timeoutRunnable = Runnable { declineCall() }

    override fun onCreate(savedInstanceState: Bundle?) {
        turnScreenOnAndUnlock()
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_incoming_call)

        setupUI()
        startRingingAndVibration()

        // 35-second call timeout
        handler.postDelayed(timeoutRunnable, 35000)
    }

    override fun onResume() {
        super.onResume()
        turnScreenOnAndUnlock()
    }

    private fun turnScreenOnAndUnlock() {
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
            val keyguardManager = getSystemService(Context.KEYGUARD_SERVICE) as? KeyguardManager
            keyguardManager?.requestDismissKeyguard(this, null)
        } else {
            @Suppress("DEPRECATION")
            window.addFlags(
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or
                        WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD or
                        WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON
            )
        }
    }

    private fun setupUI() {
        val callerName = intent.getStringExtra("caller_name") ?: getString(R.string.incoming_call_title)
        val callNote = intent.getStringExtra("call_note") ?: getString(R.string.incoming_call_subtitle)

        findViewById<TextView>(R.id.tvCallerName).text = callerName
        findViewById<TextView>(R.id.tvCallStatus).text = callNote

        // Start wave animation on avatar
        val waveView = findViewById<android.view.View>(R.id.viewCallWave)
        val waveAnimation = AnimationUtils.loadAnimation(this, R.anim.ripple_ring)
        waveView.startAnimation(waveAnimation)

        // Decline Button
        findViewById<ImageButton>(R.id.btnDeclineCall).setOnClickListener {
            declineCall()
        }

        // Answer Button
        findViewById<ImageButton>(R.id.btnAnswerCall).setOnClickListener {
            answerCall()
        }
    }

    private fun startRingingAndVibration() {
        try {
            // Ringtone
            val alertUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
            ringtone = RingtoneManager.getRingtone(applicationContext, alertUri)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                ringtone?.audioAttributes = AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build()
            }
            ringtone?.play()
        } catch (e: Exception) {
            e.printStackTrace()
        }

        try {
            // Vibration
            val pattern = longArrayOf(0, 1000, 1000, 1000, 1000, 1000)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val vibratorManager = getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager
                vibrator = vibratorManager?.defaultVibrator
                vibrator?.vibrate(VibrationEffect.createWaveform(pattern, 1))
            } else {
                @Suppress("DEPRECATION")
                vibrator = getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
                @Suppress("DEPRECATION")
                vibrator?.vibrate(pattern, 1)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun stopRingingAndVibration() {
        try {
            if (ringtone?.isPlaying == true) {
                ringtone?.stop()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }

        try {
            vibrator?.cancel()
        } catch (e: Exception) {
            e.printStackTrace()
        }

        handler.removeCallbacks(timeoutRunnable)

        // Clear notification
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.cancel(9999)
    }

    private fun answerCall() {
        stopRingingAndVibration()
        // Open Main Store Web Activity directly to customer service or store home
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra("call_answered", true)
        }
        startActivity(intent)
        finish()
    }

    private fun declineCall() {
        stopRingingAndVibration()
        finish()
    }

    override fun onDestroy() {
        stopRingingAndVibration()
        super.onDestroy()
    }
}
