package com.abirluxe.shop

import android.annotation.SuppressLint
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
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
import com.abirluxe.shop.bridge.AndroidBridge
import com.abirluxe.shop.utils.NetworkUtils

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

    private val storeBaseUrl: String by lazy { getString(R.string.store_url) }

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

        // Check and prompt for incoming call permissions (Display Over Other Apps & Full Screen Intent)
        Handler(Looper.getMainLooper()).postDelayed({
            checkCallPermissions()
        }, 1200)

        // Load initial target URL or base store URL
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
        settings.domStorageEnabled = true       // Critical: Preserves login sessions and token storage
        settings.databaseEnabled = true
        settings.cacheMode = WebSettings.LOAD_DEFAULT
        settings.allowFileAccess = true
        settings.allowContentAccess = true
        settings.useWideViewPort = true
        settings.loadWithOverviewMode = true
        settings.setSupportZoom(false)
        settings.displayZoomControls = false
        settings.mediaPlaybackRequiresUserGesture = false

        // Configure persistent cookies across sessions
        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        cookieManager.setAcceptThirdPartyCookies(webView, true)

        // Inject Native JavaScript Bridge
        webView.addJavascriptInterface(AndroidBridge(this), "AndroidBridge")

        // Setup WebViewClient
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

                // Flush cookies to persistent disk storage
                CookieManager.getInstance().flush()

                // Notify web app with native FCM token if available
                val prefs = getSharedPreferences("abir_luxe_prefs", Context.MODE_PRIVATE)
                val token = prefs.getString("fcm_token", "")
                if (!token.isNullOrBlank()) {
                    view?.evaluateJavascript(
                        "if(window.ShopNotifications && typeof window.ShopNotifications.saveTokenToServer === 'function'){ window.ShopNotifications.saveTokenToServer('$token', 'android'); } else if(typeof window.onNativeFcmTokenReceived === 'function'){ window.onNativeFcmTokenReceived('$token'); }",
                        null
                    )
                } else {
                    try {
                        com.google.firebase.messaging.FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                            if (task.isSuccessful && !task.result.isNullOrBlank()) {
                                val freshToken = task.result
                                prefs.edit().putString("fcm_token", freshToken).apply()
                                com.abirluxe.shop.fcm.AbirFirebaseMessagingService.sendTokenToBackend(applicationContext, freshToken)
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
                // Intercept main frame network loading failures and switch to animated offline view
                if (request?.isForMainFrame == true) {
                    showAnimatedOfflineView()
                }
            }

            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val url = request?.url?.toString() ?: return false
                return handleExternalScheme(url)
            }
        }

        // Setup WebChromeClient
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
            btnRetryOffline.text = ""
            progressOfflineRetry.visibility = View.VISIBLE

            Handler(Looper.getMainLooper()).postDelayed({
                if (NetworkUtils.isNetworkAvailable(this@MainActivity)) {
                    showWebStoreView()
                    webView.reload()
                } else {
                    btnRetryOffline.text = getString(R.string.btn_retry)
                    progressOfflineRetry.visibility = View.GONE
                    Toast.makeText(this@MainActivity, getString(R.string.offline_title), Toast.LENGTH_SHORT).show()
                }
            }, 600)
        }
    }

    private fun showAnimatedOfflineView() {
        isOfflineState = true
        webView.visibility = View.GONE
        layoutOffline.visibility = View.VISIBLE
        btnRetryOffline.text = getString(R.string.btn_retry)
        progressOfflineRetry.visibility = View.GONE

        // Start luxury radar pulse ripple animation
        val rippleAnim = AnimationUtils.loadAnimation(this, R.anim.ripple_ring)
        viewOfflineRipple.startAnimation(rippleAnim)
    }

    private fun showWebStoreView() {
        isOfflineState = false
        viewOfflineRipple.clearAnimation()
        layoutOffline.visibility = View.GONE
        webView.visibility = View.VISIBLE
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
            onLost = {
                runOnUiThread {
                    // Only switch if web page isn't already fully active or user tries to navigate
                }
            }
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
        val targetUrl = intent?.getStringExtra("target_url")
        if (!targetUrl.isNullOrBlank()) {
            loadStoreUrl(targetUrl)
        }
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }

    private fun checkCallPermissions() {
        if (isFinishing || isDestroyed) return

        // 1. Check Full Screen Intent permission on Android 14+
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            val notificationManager = getSystemService(android.app.NotificationManager::class.java)
            if (notificationManager != null && !notificationManager.canUseFullScreenIntent()) {
                try {
                    val intent = Intent(android.provider.Settings.ACTION_MANAGE_APP_USE_FULL_SCREEN_INTENT).apply {
                        data = Uri.parse("package:$packageName")
                    }
                    startActivity(intent)
                    return
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }

        // 2. Check Display Over Other Apps (Overlay) for WhatsApp-like full-screen call popups
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !android.provider.Settings.canDrawOverlays(this)) {
            val prefs = getSharedPreferences("abir_luxe_prefs", Context.MODE_PRIVATE)
            val alreadyPrompted = prefs.getBoolean("overlay_prompted", false)
            if (!alreadyPrompted) {
                androidx.appcompat.app.AlertDialog.Builder(this)
                    .setTitle("📞 Instant Store Call Permission")
                    .setMessage("To receive incoming calls directly on your lock screen and home screen (like WhatsApp), please enable 'Display over other apps'.")
                    .setCancelable(false)
                    .setPositiveButton("Enable Now") { _, _ ->
                        prefs.edit().putBoolean("overlay_prompted", true).apply()
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
                    .setNegativeButton("Later") { _, _ ->
                        prefs.edit().putBoolean("overlay_prompted", true).apply()
                    }
                    .show()
            }
        }
    }

    override fun onDestroy() {
        NetworkUtils.unregisterNetworkCallback(this, networkCallback)
        webView.destroy()
        super.onDestroy()
    }
}
