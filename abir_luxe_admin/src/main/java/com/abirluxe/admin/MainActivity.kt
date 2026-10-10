package com.abirluxe.admin

import android.Manifest
import android.annotation.SuppressLint
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.ConnectivityManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.View
import android.view.animation.AnimationUtils
import android.webkit.CookieManager
import android.webkit.PermissionRequest
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.abirluxe.admin.bridge.AndroidBridge
import com.abirluxe.admin.utils.NetworkUtils

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private lateinit var swipeRefreshLayout: SwipeRefreshLayout
    private lateinit var progressBar: ProgressBar
    private lateinit var layoutOffline: View
    private lateinit var btnRetryOffline: TextView
    private lateinit var progressOfflineRetry: ProgressBar
    private lateinit var viewOfflineRipple: View

    private var fileUploadCallback: ValueCallback<Array<Uri>>? = null
    private var networkCallback: ConnectivityManager.NetworkCallback? = null
    private var isOfflineState = false
    private var backPressedOnce = false
    private var pendingPermissionRequest: PermissionRequest? = null

    private val storeBaseUrl: String by lazy { getString(R.string.store_url) }

    private val mediaPermissionsLauncher =
        registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { _ ->
            val hasAudio = ContextCompat.checkSelfPermission(
                this,
                Manifest.permission.RECORD_AUDIO
            ) == PackageManager.PERMISSION_GRANTED

            val hasCamera = ContextCompat.checkSelfPermission(
                this,
                Manifest.permission.CAMERA
            ) == PackageManager.PERMISSION_GRANTED

            pendingPermissionRequest?.let { req ->
                val granted = mutableListOf<String>()
                for (res in req.resources) {
                    if (res == PermissionRequest.RESOURCE_AUDIO_CAPTURE && hasAudio) {
                        granted.add(res)
                    } else if (res == PermissionRequest.RESOURCE_VIDEO_CAPTURE && hasCamera) {
                        granted.add(res)
                    } else if (res == PermissionRequest.RESOURCE_PROTECTED_MEDIA_ID) {
                        granted.add(res)
                    }
                }
                if (granted.isNotEmpty()) {
                    req.grant(granted.toTypedArray())
                } else {
                    req.deny()
                }
            }
            pendingPermissionRequest = null
        }

    private val fileChooserLauncher =
        registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            if (fileUploadCallback == null) return@registerForActivityResult
            val results = WebChromeClient.FileChooserParams.parseResult(result.resultCode, result.data)
            fileUploadCallback?.onReceiveValue(results)
            fileUploadCallback = null
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        initViews()
        setupWebView()
        setupSwipeRefresh()
        setupOfflineView()
        setupBackNavigation()
        monitorNetworkChanges()

        // Check & prompt for instant call overlay permissions
        Handler(Looper.getMainLooper()).postDelayed({
            checkCallPermissions()
        }, 1200)

        // Cancel any incoming call notification if opened via answer
        if (intent?.getBooleanExtra("call_answered", false) == true) {
            val nm = getSystemService(Context.NOTIFICATION_SERVICE) as android.app.NotificationManager
            nm.cancel(9999)
        }

        // Load initial target URL or base admin URL
        val initialUrl = intent?.getStringExtra("target_url")
        loadStoreUrl(if (!initialUrl.isNullOrBlank()) initialUrl else storeBaseUrl)
    }

    private fun initViews() {
        webView = findViewById(R.id.webViewStore)
        swipeRefreshLayout = findViewById(R.id.swipeRefreshLayout)
        progressBar = findViewById(R.id.progressWebLoad)
        layoutOffline = findViewById(R.id.includeOfflineView)
        btnRetryOffline = layoutOffline.findViewById(R.id.btnRetryOffline)
        progressOfflineRetry = layoutOffline.findViewById(R.id.progressOfflineRetry)
        viewOfflineRipple = layoutOffline.findViewById(R.id.viewOfflineRipple)
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {
        val settings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true       // Preserves Admin login sessions & localStorage
        @Suppress("DEPRECATION")
        settings.databaseEnabled = true
        settings.cacheMode = WebSettings.LOAD_DEFAULT
        settings.allowFileAccess = true
        settings.allowContentAccess = true
        settings.useWideViewPort = true
        settings.loadWithOverviewMode = true
        settings.setSupportZoom(false)
        settings.displayZoomControls = false
        settings.mediaPlaybackRequiresUserGesture = false

        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        cookieManager.setAcceptThirdPartyCookies(webView, true)

        webView.addJavascriptInterface(AndroidBridge(this), "AndroidBridge")

        webView.webViewClient = object : WebViewClient() {
            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                super.onPageStarted(view, url, favicon)
                progressBar.visibility = View.VISIBLE
                progressBar.progress = 10
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                progressBar.visibility = View.GONE
                swipeRefreshLayout.isRefreshing = false

                if (!isOfflineState) {
                    showWebStoreView()
                }

                CookieManager.getInstance().flush()

                // Notify web app with native FCM token if available
                val prefs = getSharedPreferences("abir_admin_prefs", Context.MODE_PRIVATE)
                val token = prefs.getString("fcm_token", "")
                if (!token.isNullOrBlank()) {
                    view?.evaluateJavascript(
                        "if(window.ShopNotifications && typeof window.ShopNotifications.saveTokenToServer === 'function'){ window.ShopNotifications.saveTokenToServer('$token', 'admin_android'); } else if(typeof window.onNativeFcmTokenReceived === 'function'){ window.onNativeFcmTokenReceived('$token'); }",
                        null
                    )
                } else {
                    try {
                        com.google.firebase.messaging.FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                            if (task.isSuccessful && !task.result.isNullOrBlank()) {
                                val freshToken = task.result
                                prefs.edit().putString("fcm_token", freshToken).apply()
                                com.abirluxe.admin.fcm.AbirAdminFirebaseMessagingService.sendTokenToBackend(applicationContext, freshToken)
                            }
                        }
                    } catch (e: Exception) {
                        e.printStackTrace()
                    }
                }
            }

            override fun onReceivedError(
                view: WebView?,
                request: WebResourceRequest?,
                error: WebResourceError?
            ) {
                super.onReceivedError(view, request, error)
                if (request?.isForMainFrame == true) {
                    showAnimatedOfflineView()
                }
            }

            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val url = request?.url?.toString() ?: return false
                return handleExternalScheme(url)
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                super.onProgressChanged(view, newProgress)
                if (newProgress in 1..99) {
                    progressBar.visibility = View.VISIBLE
                    progressBar.progress = newProgress
                } else {
                    progressBar.visibility = View.GONE
                }
            }

            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                fileUploadCallback?.onReceiveValue(null)
                fileUploadCallback = filePathCallback

                val intent = fileChooserParams?.createIntent() ?: Intent(Intent.ACTION_GET_CONTENT).apply {
                    type = "image/*"
                    addCategory(Intent.CATEGORY_OPENABLE)
                }

                try {
                    fileChooserLauncher.launch(Intent.createChooser(intent, getString(R.string.file_chooser_title)))
                } catch (e: ActivityNotFoundException) {
                    fileUploadCallback = null
                    return false
                }
                return true
            }

            override fun onPermissionRequest(request: PermissionRequest?) {
                runOnUiThread {
                    if (request == null) return@runOnUiThread
                    val requestedResources = request.resources
                    var needsAudio = false
                    var needsVideo = false
                    for (r in requestedResources) {
                        if (r == PermissionRequest.RESOURCE_AUDIO_CAPTURE) needsAudio = true
                        if (r == PermissionRequest.RESOURCE_VIDEO_CAPTURE) needsVideo = true
                    }

                    val hasAudio = ContextCompat.checkSelfPermission(
                        this@MainActivity,
                        Manifest.permission.RECORD_AUDIO
                    ) == PackageManager.PERMISSION_GRANTED

                    val hasCamera = ContextCompat.checkSelfPermission(
                        this@MainActivity,
                        Manifest.permission.CAMERA
                    ) == PackageManager.PERMISSION_GRANTED

                    val ungranted = mutableListOf<String>()
                    if (needsAudio && !hasAudio) ungranted.add(Manifest.permission.RECORD_AUDIO)
                    if (needsVideo && !hasCamera) ungranted.add(Manifest.permission.CAMERA)

                    if (ungranted.isEmpty()) {
                        request.grant(requestedResources)
                    } else {
                        pendingPermissionRequest = request
                        mediaPermissionsLauncher.launch(ungranted.toTypedArray())
                    }
                }
            }

            override fun onPermissionRequestCanceled(request: PermissionRequest?) {
                super.onPermissionRequestCanceled(request)
                if (pendingPermissionRequest == request) {
                    pendingPermissionRequest = null
                }
            }
        }
    }

    private fun handleExternalScheme(url: String): Boolean {
        if (url.startsWith("tel:") || url.startsWith("mailto:") || url.startsWith("sms:")) {
            try {
                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                return true
            } catch (e: Exception) {
                e.printStackTrace()
            }
        } else if (url.startsWith("whatsapp:") || url.contains("api.whatsapp.com")) {
            try {
                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                return true
            } catch (e: Exception) {
                Toast.makeText(this, "WhatsApp is not installed", Toast.LENGTH_SHORT).show()
                return true
            }
        }
        return false
    }

    private fun setupSwipeRefresh() {
        swipeRefreshLayout.setColorSchemeColors(
            ContextCompat.getColor(this, R.color.brand_gold),
            ContextCompat.getColor(this, R.color.brand_gold_dark)
        )
        swipeRefreshLayout.setOnRefreshListener {
            if (NetworkUtils.isNetworkAvailable(this)) {
                webView.reload()
            } else {
                swipeRefreshLayout.isRefreshing = false
                showAnimatedOfflineView()
            }
        }
    }

    private fun setupOfflineView() {
        btnRetryOffline.setOnClickListener {
            triggerManualRetry()
        }
    }

    private fun triggerManualRetry() {
        btnRetryOffline.text = getString(R.string.checking_connection)
        progressOfflineRetry.visibility = View.VISIBLE
        btnRetryOffline.isEnabled = false

        Handler(Looper.getMainLooper()).postDelayed({
            if (NetworkUtils.isNetworkAvailable(this)) {
                showWebStoreView()
                webView.reload()
            } else {
                btnRetryOffline.text = getString(R.string.btn_retry)
                progressOfflineRetry.visibility = View.GONE
                btnRetryOffline.isEnabled = true
                Toast.makeText(this, "Connection still unavailable", Toast.LENGTH_SHORT).show()
            }
        }, 1200)
    }

    private fun showAnimatedOfflineView() {
        if (isOfflineState) return
        isOfflineState = true

        runOnUiThread {
            webView.visibility = View.GONE
            layoutOffline.visibility = View.VISIBLE
            swipeRefreshLayout.isRefreshing = false

            val pulseAnim = AnimationUtils.loadAnimation(this, R.anim.pulse)
            viewOfflineRipple.startAnimation(pulseAnim)

            btnRetryOffline.text = getString(R.string.btn_retry)
            progressOfflineRetry.visibility = View.GONE
            btnRetryOffline.isEnabled = true
        }
    }

    private fun showWebStoreView() {
        if (!isOfflineState && webView.visibility == View.VISIBLE) return
        isOfflineState = false

        runOnUiThread {
            viewOfflineRipple.clearAnimation()
            layoutOffline.visibility = View.GONE
            webView.visibility = View.VISIBLE
        }
    }

    private fun loadStoreUrl(url: String) {
        if (NetworkUtils.isNetworkAvailable(this)) {
            showWebStoreView()
            webView.loadUrl(url)
        } else {
            showAnimatedOfflineView()
        }
    }

    private fun monitorNetworkChanges() {
        networkCallback = NetworkUtils.registerNetworkCallback(
            this,
            onAvailable = {
                runOnUiThread {
                    if (isOfflineState) {
                        showWebStoreView()
                        webView.reload()
                    }
                }
            },
            onLost = {}
        )
    }

    private fun setupBackNavigation() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (isOfflineState) {
                    finish()
                    return
                }

                if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    if (backPressedOnce) {
                        finish()
                    } else {
                        backPressedOnce = true
                        Toast.makeText(this@MainActivity, "Press back again to exit", Toast.LENGTH_SHORT).show()
                        Handler(Looper.getMainLooper()).postDelayed({
                            backPressedOnce = false
                        }, 2000)
                    }
                }
            }
        })
    }

    override fun onNewIntent(intent: Intent?) {
        super.onNewIntent(intent)
        setIntent(intent)
        if (intent?.getBooleanExtra("call_answered", false) == true) {
            val nm = getSystemService(Context.NOTIFICATION_SERVICE) as android.app.NotificationManager
            nm.cancel(9999)
        }
        val targetUrl = intent?.getStringExtra("target_url")
        val threadId = intent?.getStringExtra("thread_id") ?: ""
        if (!targetUrl.isNullOrBlank()) {
            val currentWebUrl = webView.url ?: ""
            if (currentWebUrl.contains("admin/live-chat.php") && targetUrl.contains("auto_answer=1")) {
                webView.evaluateJavascript(
                    "if(typeof handleAutoAnswerFlow === 'function'){ handleAutoAnswerFlow('$threadId'); } else { window.location.href = '$targetUrl'; }",
                    null
                )
            } else {
                loadStoreUrl(targetUrl)
            }
        }
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }

    private fun checkCallPermissions() {
        if (isFinishing || isDestroyed) return

        val missingPermissions = mutableListOf<String>()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
            ) {
                missingPermissions.add(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO)
            != PackageManager.PERMISSION_GRANTED
        ) {
            missingPermissions.add(Manifest.permission.RECORD_AUDIO)
        }
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
            != PackageManager.PERMISSION_GRANTED
        ) {
            missingPermissions.add(Manifest.permission.CAMERA)
        }

        if (missingPermissions.isNotEmpty()) {
            mediaPermissionsLauncher.launch(missingPermissions.toTypedArray())
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !android.provider.Settings.canDrawOverlays(this)) {
            val prefs = getSharedPreferences("abir_admin_prefs", Context.MODE_PRIVATE)
            val sessionPrompted = prefs.getBoolean("overlay_session_prompted", false)
            if (!sessionPrompted) {
                prefs.edit().putBoolean("overlay_session_prompted", true).apply()
                androidx.appcompat.app.AlertDialog.Builder(this)
                    .setTitle("📞 Enable Instant Call Screen")
                    .setMessage("To allow customer support calls to ring and appear directly on your home screen and lock screen (just like WhatsApp), please enable 'Display over other apps'.")
                    .setCancelable(true)
                    .setPositiveButton("Enable Now") { _, _ ->
                        try {
                            val intent = Intent(
                                android.provider.Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                                Uri.parse("package:$packageName")
                            )
                            startActivity(intent)
                        } catch (e: Exception) {
                            e.printStackTrace()
                        }
                    }
                    .setNegativeButton("Later", null)
                    .show()
            }
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            val notificationManager = getSystemService(android.app.NotificationManager::class.java)
            if (notificationManager != null && !notificationManager.canUseFullScreenIntent()) {
                try {
                    val intent = Intent(android.provider.Settings.ACTION_MANAGE_APP_USE_FULL_SCREEN_INTENT).apply {
                        data = Uri.parse("package:$packageName")
                    }
                    startActivity(intent)
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }
    }

    override fun onDestroy() {
        NetworkUtils.unregisterNetworkCallback(this, networkCallback)
        webView.destroy()
        super.onDestroy()
    }
}

