package com.example

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.fragment.app.FragmentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.Surface
import androidx.compose.ui.Modifier
import androidx.compose.runtime.getValue
import androidx.compose.runtime.collectAsState
import androidx.core.content.ContextCompat
import android.content.Context
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.getValue
import com.example.ui.AppNavigation
import com.example.ui.AppViewModel
import com.example.ui.theme.MyApplicationTheme
import com.example.service.SmsMonitoringService

class MainActivity : FragmentActivity() {

    private val viewModel: AppViewModel by viewModels()

    private var isRequestingPermission = false
    private var showPermissionConsent by mutableStateOf(false)

    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        isRequestingPermission = false
        // Start service if RECEIVE_SMS or SEND_SMS was granted (either unlocks gateway functionality)
        val smsReceivedGranted = permissions[Manifest.permission.RECEIVE_SMS] ?: false
        val smsSendGranted = permissions[Manifest.permission.SEND_SMS] ?: false
        if (smsReceivedGranted || smsSendGranted) {
            startSmsService()
        }
    }

    private fun getMissingPermissions(): List<String> {
        val permissionsNeeded = mutableListOf(
            Manifest.permission.RECEIVE_SMS,
            Manifest.permission.SEND_SMS,
            Manifest.permission.READ_PHONE_STATE
        )
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            permissionsNeeded.add(Manifest.permission.POST_NOTIFICATIONS)
        }
        return permissionsNeeded.filter {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
            android.util.Log.e("SwapnoPayCrash", "Fatal uncaught exception in thread ${thread.name}: ${throwable.message}", throwable)
        }
        enableEdgeToEdge()

        // Handle deep link callback from Supabase email confirmation or reset links
        handleDeepLinkIntent(intent)

        val isEmployeeFlavor = try {
            com.example.BuildConfig.APP_FLAVOR_ROLE == "EMPLOYEE"
        } catch (e: Throwable) {
            false
        }

        val prefs = getSharedPreferences("swapnopay_policy_prefs", Context.MODE_PRIVATE)

        if (!isEmployeeFlavor) {
            // Check and request dynamic permissions (SMS gateway - merchant only)
            checkAndRequestPermissions()
        }

        setContent {
            val isDarkMode by viewModel.isDarkMode.collectAsState()
            val language by viewModel.language.collectAsState()
            val isBangla = language == "Bangla"

            MyApplicationTheme(darkTheme = isDarkMode) {
                Surface(modifier = Modifier.fillMaxSize()) {
                    AppNavigation(viewModel = viewModel)

                    // Render Prominent Disclosure Consent Dialog before requesting permissions
                    if (showPermissionConsent) {
                        com.example.ui.PermissionProminentDisclosureDialog(
                            isDarkMode = isDarkMode,
                            isBangla = isBangla,
                            onAccept = {
                                showPermissionConsent = false
                                prefs.edit().putBoolean("has_seen_permission_disclosure", true).apply()
                                val toRequest = getMissingPermissions()
                                if (toRequest.isNotEmpty()) {
                                    isRequestingPermission = true
                                    requestPermissionLauncher.launch(toRequest.toTypedArray())
                                } else {
                                    startSmsService()
                                }
                            },
                            onDismiss = {
                                showPermissionConsent = false
                                prefs.edit().putBoolean("has_seen_permission_disclosure", true).apply()
                            }
                        )
                    }
                }
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleDeepLinkIntent(intent)
    }

    private fun handleDeepLinkIntent(intent: Intent?) {
        val uri = intent?.data ?: return
        viewModel.handleAuthDeepLink(uri)
    }

    private var isAppInBackground = false

    override fun onStop() {
        super.onStop()
        isAppInBackground = true
    }

    override fun onResume() {
        super.onResume()
        if (isAppInBackground) {
            isAppInBackground = false
            // Switching between other mobile tabs/apps and returning should not trigger app lock
        }
    }

    private fun checkAndRequestPermissions() {
        val missingPermissions = getMissingPermissions()
        if (missingPermissions.isEmpty()) {
            startSmsService()
        } else {
            val prefs = getSharedPreferences("swapnopay_policy_prefs", Context.MODE_PRIVATE)
            val hasSeenConsent = prefs.getBoolean("has_seen_permission_disclosure", false)
            if (!hasSeenConsent) {
                showPermissionConsent = true
            } else {
                isRequestingPermission = true
                requestPermissionLauncher.launch(missingPermissions.toTypedArray())
            }
        }
    }

    private fun startSmsService() {
        runCatching {
            val hasSms = ContextCompat.checkSelfPermission(this, Manifest.permission.RECEIVE_SMS) == PackageManager.PERMISSION_GRANTED
            if (!hasSms) return@runCatching
            val serviceIntent = Intent(this, SmsMonitoringService::class.java)
            ContextCompat.startForegroundService(this, serviceIntent)
        }.onFailure {
            android.util.Log.e("MainActivity", "Unable to start SMS monitoring service", it)
        }
    }
}
