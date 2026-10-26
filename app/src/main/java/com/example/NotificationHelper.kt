package com.example

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat

object NotificationHelper {

    private const val TAG = "NotificationHelper"

    const val CHANNEL_ADMIN = "swapnopay_admin_notices"
    const val CHANNEL_PAYMENTS = "swapnopay_payments"
    const val CHANNEL_FORMS = "swapnopay_form_submissions"
    const val CHANNEL_SMS_SERVICE = "SmsMonitoringChannel"

    private const val PREFS_NAME = "swapnopay_notifications_tracker"
    private const val KEY_NOTIFIED_IDS = "notified_ids"
    private const val MAX_TRACKED_IDS = 500

    /**
     * Initializes all required notification channels with High Importance
     * so they appear in statusbar, heads-up banner, and with sound/vibration.
     */
    fun initNotificationChannels(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
            ?: return

        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
        val audioAttributes = AudioAttributes.Builder()
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .build()

        // 1. Payment & Transaction Notifications Channel
        val paymentChannel = NotificationChannel(
            CHANNEL_PAYMENTS,
            "Payment & Transaction Alerts",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Instant notifications when money is received via bKash, Nagad, Rocket, Upay, or Cards"
            enableLights(true)
            lightColor = Color.GREEN
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 250, 150, 250)
            setSound(soundUri, audioAttributes)
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            setShowBadge(true)
        }
        notificationManager.createNotificationChannel(paymentChannel)

        // 2. Admin & System Notices Channel
        val adminChannel = NotificationChannel(
            CHANNEL_ADMIN,
            "Admin & System Notices",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Broadcast announcements, security alerts, and system notices from SwapnoPay Admin"
            enableLights(true)
            lightColor = Color.BLUE
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 200, 100, 200)
            setSound(soundUri, audioAttributes)
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            setShowBadge(true)
        }
        notificationManager.createNotificationChannel(adminChannel)

        // 3. Form Submissions Channel
        val formChannel = NotificationChannel(
            CHANNEL_FORMS,
            "Form Submissions",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Instant alerts when customers fill and submit payment or inquiry forms"
            enableLights(true)
            lightColor = Color.MAGENTA
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 200, 100, 200)
            setSound(soundUri, audioAttributes)
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            setShowBadge(true)
        }
        notificationManager.createNotificationChannel(formChannel)

        // 4. SMS Monitoring Foreground Service Channel
        val smsChannel = NotificationChannel(
            CHANNEL_SMS_SERVICE,
            "SMS Monitoring Service",
            NotificationManager.IMPORTANCE_LOW
        ).apply {
            description = "Active background receiver for mobile banking SMS transactions"
            setShowBadge(false)
        }
        notificationManager.createNotificationChannel(smsChannel)

        Log.d(TAG, "Notification channels registered successfully.")
    }

    /**
     * Checks if a notification with this unique ID has already been posted to avoid duplication.
     */
    fun hasBeenNotified(context: Context, id: String): Boolean {
        if (id.isBlank()) return false
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val set = prefs.getStringSet(KEY_NOTIFIED_IDS, emptySet()) ?: emptySet()
        return set.contains(id)
    }

    /**
     * Marks a notification ID as posted.
     */
    fun markAsNotified(context: Context, id: String) {
        if (id.isBlank()) return
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val existing = prefs.getStringSet(KEY_NOTIFIED_IDS, emptySet()) ?: emptySet()
        val currentSet = HashSet(existing)
        if (currentSet.size > MAX_TRACKED_IDS) {
            val list = currentSet.toList()
            val trimmed = HashSet(list.subList(list.size / 2, list.size))
            trimmed.add(id)
            prefs.edit().putStringSet(KEY_NOTIFIED_IDS, trimmed).commit()
        } else {
            currentSet.add(id)
            prefs.edit().putStringSet(KEY_NOTIFIED_IDS, currentSet).commit()
        }
    }

    /**
     * Can notifications be posted to the system? Checks Android 13+ runtime permission and app-level status.
     */
    fun canPostNotification(context: Context): Boolean {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                Log.w(TAG, "POST_NOTIFICATIONS permission is NOT granted on API 33+.")
                return false
            }
        }
        return NotificationManagerCompat.from(context).areNotificationsEnabled()
    }

    /**
     * Displays a Payment Received notification in the mobile statusbar and notification drawer.
     */
    fun showPaymentNotification(
        context: Context,
        amount: Double,
        method: String,
        sender: String,
        trxId: String
    ) {
        try {
            if (trxId.isNotBlank() && hasBeenNotified(context, trxId)) {
                Log.d(TAG, "Payment notification already delivered for TrxID: $trxId")
                return
            }

            initNotificationChannels(context)

            if (!canPostNotification(context)) {
                Log.w(TAG, "Cannot show payment notification: notifications disabled or permission missing.")
                return
            }

            val amountStr = if (amount % 1.0 == 0.0) {
                String.format(java.util.Locale.US, "%.0f", amount)
            } else {
                String.format(java.util.Locale.US, "%.2f", amount)
            }

            val title = "💰 পেমেন্ট প্রাপ্তি: ৳$amountStr"
            val senderText = if (sender.isNotBlank()) " | প্রেরক: $sender" else ""
            val body = "$method • TrxID: $trxId$senderText"
            val bigText = "নতুন পেমেন্ট সফলভাবে সংরক্ষিত হয়েছে।\nমাধ্যম: $method\nপরিমাণ: ৳$amountStr\nTrxID: $trxId" +
                    (if (sender.isNotBlank()) "\nগ্রাহক: $sender" else "")

            val intent = Intent(context, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                putExtra("target_screen", "Transactions")
                putExtra("notification_type", "PAYMENT")
                putExtra("trx_id", trxId)
            }

            val reqCode = (trxId.hashCode() and 0x7FFFFFFF)
            val pendingIntent = PendingIntent.getActivity(
                context,
                reqCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            val notification = NotificationCompat.Builder(context, CHANNEL_PAYMENTS)
                .setSmallIcon(R.drawable.ic_stat_notification)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(NotificationCompat.BigTextStyle().bigText(bigText))
                .setColor(0xFF10B981.toInt()) // Success Emerald Green
                .setPriority(NotificationCompat.PRIORITY_MAX)
                .setCategory(NotificationCompat.CATEGORY_STATUS)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setSound(soundUri)
                .setVibrate(longArrayOf(0, 250, 150, 250))
                .setAutoCancel(true)
                .setContentIntent(pendingIntent)
                .build()

            val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.notify(reqCode, notification)

            if (trxId.isNotBlank()) {
                markAsNotified(context, trxId)
            }
            Log.d(TAG, "Payment notification posted to statusbar: $title (Req: $reqCode)")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to display payment notification: ${e.message}", e)
        }
    }

    /**
     * Displays an Admin Notice or Broadcast notification in the mobile statusbar and notification drawer.
     */
    fun showAdminNotification(
        context: Context,
        title: String,
        message: String,
        noticeId: String = "",
        severity: String = "INFO"
    ) {
        try {
            if (noticeId.isNotBlank() && hasBeenNotified(context, noticeId)) {
                Log.d(TAG, "Admin notification already delivered for ID: $noticeId")
                return
            }

            initNotificationChannels(context)

            if (!canPostNotification(context)) {
                Log.w(TAG, "Cannot show admin notification: notifications disabled or permission missing.")
                return
            }

            val displayTitle = if (title.startsWith("📢") || title.startsWith("Notice")) {
                title
            } else {
                "📢 $title"
            }

            val intent = Intent(context, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                putExtra("target_screen", "Notifications")
                putExtra("notification_type", "ADMIN_NOTICE")
                putExtra("notice_id", noticeId)
            }

            val reqCode = if (noticeId.isNotBlank()) (noticeId.hashCode() and 0x7FFFFFFF) else (System.currentTimeMillis().toInt() and 0x7FFFFFFF)
            val pendingIntent = PendingIntent.getActivity(
                context,
                reqCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val color = when (severity.uppercase()) {
                "CRITICAL", "URGENT", "WARNING" -> 0xFFEF4444.toInt() // Red
                "ALERT", "MAINTENANCE" -> 0xFFF59E0B.toInt() // Amber
                else -> 0xFF6366F1.toInt() // Brand Indigo
            }

            val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            val notification = NotificationCompat.Builder(context, CHANNEL_ADMIN)
                .setSmallIcon(R.drawable.ic_stat_notification)
                .setContentTitle(displayTitle)
                .setContentText(message)
                .setStyle(NotificationCompat.BigTextStyle().bigText(message))
                .setColor(color)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setCategory(NotificationCompat.CATEGORY_MESSAGE)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setSound(soundUri)
                .setAutoCancel(true)
                .setContentIntent(pendingIntent)
                .build()

            val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.notify(reqCode, notification)

            if (noticeId.isNotBlank()) {
                markAsNotified(context, noticeId)
            }
            Log.d(TAG, "Admin notification posted to statusbar: $displayTitle (Req: $reqCode)")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to display admin notification: ${e.message}", e)
        }
    }

    /**
     * Displays a Form Fillup / Submission notification in the mobile statusbar and notification drawer.
     */
    fun showFormSubmissionNotification(
        context: Context,
        formTitle: String,
        customerInfo: String,
        submissionId: String = ""
    ) {
        try {
            if (submissionId.isNotBlank() && hasBeenNotified(context, submissionId)) {
                Log.d(TAG, "Form submission notification already delivered for ID: $submissionId")
                return
            }

            initNotificationChannels(context)

            if (!canPostNotification(context)) {
                Log.w(TAG, "Cannot show form submission notification: notifications disabled or permission missing.")
                return
            }

            val displayTitle = "📝 নতুন ফর্ম পূরণ: $formTitle"
            val displayMessage = if (customerInfo.isNotBlank()) customerInfo else "একটি নতুন ফর্ম সাবমিশন জমা পড়েছে"

            val intent = Intent(context, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                putExtra("target_screen", "Forms")
                putExtra("notification_type", "FORM_SUBMISSION")
                putExtra("submission_id", submissionId)
            }

            val reqCode = if (submissionId.isNotBlank()) (submissionId.hashCode() and 0x7FFFFFFF) else (System.currentTimeMillis().toInt() and 0x7FFFFFFF)
            val pendingIntent = PendingIntent.getActivity(
                context,
                reqCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            val notification = NotificationCompat.Builder(context, CHANNEL_FORMS)
                .setSmallIcon(R.drawable.ic_stat_notification)
                .setContentTitle(displayTitle)
                .setContentText(displayMessage)
                .setStyle(NotificationCompat.BigTextStyle().bigText("ফর্ম: $formTitle\nবিবরণ: $displayMessage\nএখনই অ্যাপে গিয়ে বিস্তারিত দেখুন।"))
                .setColor(0xFF8B5CF6.toInt()) // Purple
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setCategory(NotificationCompat.CATEGORY_EVENT)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setSound(soundUri)
                .setAutoCancel(true)
                .setContentIntent(pendingIntent)
                .build()

            val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.notify(reqCode, notification)

            if (submissionId.isNotBlank()) {
                markAsNotified(context, submissionId)
            }
            Log.d(TAG, "Form submission notification posted to statusbar: $displayTitle (Req: $reqCode)")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to display form submission notification: ${e.message}", e)
        }
    }
}
