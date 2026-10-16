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
        super.onCreate(savedInstanceState)
        turnScreenOnAndUnlock()
        setContentView(R.layout.activity_incoming_call)

        try {
            setupUI()
            startRingingAndVibration()
        } catch (e: Exception) {
            e.printStackTrace()
        }

        // 35-second call timeout
        handler.postDelayed(timeoutRunnable, 35000)
    }

    override fun onResume() {
        super.onResume()
        turnScreenOnAndUnlock()
    }

    override fun onAttachedToWindow() {
        super.onAttachedToWindow()
        turnScreenOnAndUnlock()
    }

    private fun turnScreenOnAndUnlock() {
        try {
            @Suppress("DEPRECATION")
            window.addFlags(
                WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON or
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or
                WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD or
                WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON
            )
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
                setShowWhenLocked(true)
                setTurnScreenOn(true)
                val keyguardManager = getSystemService(Context.KEYGUARD_SERVICE) as? KeyguardManager
                keyguardManager?.requestDismissKeyguard(this, null)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun setupUI() {
        val callerName = intent?.getStringExtra("caller_name") ?: getString(R.string.incoming_call_title)
        val callNote = intent?.getStringExtra("call_note") ?: getString(R.string.incoming_call_subtitle)

        findViewById<TextView>(R.id.tvCallerName)?.text = callerName
        findViewById<TextView>(R.id.tvCallStatus)?.text = callNote

        // Start wave animation on avatar safely
        try {
            val waveView = findViewById<android.view.View>(R.id.viewCallWave)
            if (waveView != null) {
                val waveAnimation = AnimationUtils.loadAnimation(this, R.anim.ripple_ring)
                waveView.startAnimation(waveAnimation)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }

        // Decline Button
        findViewById<ImageButton>(R.id.btnDeclineCall)?.setOnClickListener {
            declineCall()
        }

        // Answer Button
        findViewById<ImageButton>(R.id.btnAnswerCall)?.setOnClickListener {
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
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                ringtone?.volume = 0.35f
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

        // Clear notification safely
        try {
            val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
            notificationManager?.cancel(9999)
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun answerCall() {
        try {
            stopRingingAndVibration()
            val rawUrl = intent?.getStringExtra("target_url")
            val finalUrl = if (!rawUrl.isNullOrBlank()) {
                if (rawUrl.contains("?")) "$rawUrl&auto_answer=1" else "$rawUrl?auto_answer=1"
            } else {
                "https://shop.swapnopay.top/abir-luxe-shop-bd-0558/messages.php?auto_answer=1"
            }
            val intent = Intent(this, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                putExtra("call_answered", true)
                putExtra("target_url", finalUrl)
            }
            startActivity(intent)
        } catch (e: Exception) {
            e.printStackTrace()
        } finally {
            finish()
        }
    }

    private fun declineCall() {
        try {
            stopRingingAndVibration()
        } catch (e: Exception) {
            e.printStackTrace()
        } finally {
            finish()
        }
    }

    override fun onDestroy() {
        stopRingingAndVibration()
        super.onDestroy()
    }
}
