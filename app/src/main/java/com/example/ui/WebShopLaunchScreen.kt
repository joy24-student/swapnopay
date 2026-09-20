@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
package com.example.ui

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.runtime.collectAsState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch

@Composable
fun WebShopLaunchScreen(viewModel: AppViewModel) {
    val context = LocalContext.current
    val clipboardManager = LocalClipboardManager.current
    val scope = rememberCoroutineScope()
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    val webShopState by viewModel.webShopState.collectAsState()
    val recentOrders by viewModel.orders.collectAsState()
    val localProducts by viewModel.products.collectAsState()
    val activeProfile by viewModel.activeProfile.collectAsState()

    var storeName by remember { mutableStateOf<String>(webShopState.storeName) }
    var storeSubdomain by remember { mutableStateOf<String>(webShopState.shopSlug) }
    var customDomain by remember { mutableStateOf<String>(webShopState.customDomain) }
    var primaryCurrency by remember { mutableStateOf<String>(webShopState.primaryCurrency) }
    var selectedThemeColor by remember { mutableStateOf(Color(0xFF4F46E5)) }
    var showEditCredentialsDialog by remember { mutableStateOf(false) }

    var adminEmailInput by remember { mutableStateOf(webShopState.adminEmail) }
    var adminPasswordInput by remember { mutableStateOf(webShopState.adminPassword) }
    var showPassword by remember { mutableStateOf(false) }
    var editEmail by remember { mutableStateOf("") }
    var editPassword by remember { mutableStateOf("") }

    LaunchedEffect(Unit) {
        viewModel.loadWebShopStatus()
    }

    LaunchedEffect(webShopState, activeProfile) {
        if (webShopState.storeName.isNotBlank()) {
            storeName = webShopState.storeName
        } else if (storeName.isBlank()) {
            storeName = activeProfile.businessName.ifBlank { "My Web Store" }
        }
        if (webShopState.shopSlug.isNotBlank()) {
            storeSubdomain = webShopState.shopSlug
        } else if (storeSubdomain.isBlank()) {
            storeSubdomain = activeProfile.businessName.lowercase().replace(Regex("[^a-z0-9]"), "").take(16).ifBlank { "myshop" }
        }
        if (webShopState.customDomain.isNotBlank()) {
            customDomain = webShopState.customDomain
        }
        if (webShopState.adminEmail.isNotBlank()) {
            adminEmailInput = webShopState.adminEmail
        } else if (adminEmailInput.isBlank()) {
            adminEmailInput = activeProfile.email.ifBlank { "admin@myshop.com" }
        }
        if (webShopState.adminPassword.isNotBlank()) {
            adminPasswordInput = webShopState.adminPassword
        }
    }

    val freePlatformUrl = "https://${storeSubdomain.ifBlank { "store" }}.shop.swapnopay.top"
    val isPlatformDomain = customDomain.isBlank() || customDomain.contains("swapnopay.top")
    val effectiveUrl = when {
        customDomain.isNotBlank() && !customDomain.contains("swapnopay.top") -> "https://${customDomain.trim()}"
        webShopState.shopUrl.isNotBlank() -> webShopState.shopUrl
        else -> freePlatformUrl
    }

    val screenBg = if (isDarkMode) Color(0xFF0C0C0E) else Color(0xFFFAFAFC)
    val cardBg = if (isDarkMode) Color(0xFF13100C) else Color(0xFFFFFFFF)
    val cardBorder = if (isDarkMode) Color(0xFF272015) else Color(0xFFE2E8F0)
    val primaryText = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val secondaryText = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val accentIndigo = Color(0xFF4F46E5)
    val successGreen = Color(0xFF10B981)

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(screenBg)
    ) {
        // Top App Bar
        Surface(
            modifier = Modifier.fillMaxWidth(),
            color = cardBg,
            shadowElevation = 4.dp
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .statusBarsPadding()
                    .padding(horizontal = 16.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(onClick = { if (viewModel.canGoBack()) viewModel.goBack() else viewModel.navigateTo("More") }) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = "Back",
                        tint = primaryText
                    )
                }
                Spacer(modifier = Modifier.width(8.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Web Shop & Website Launch",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = primaryText
                    )
                    Text(
                        text = "VPS Hosted PostgreSQL Storefront",
                        fontSize = 12.sp,
                        color = secondaryText
                    )
                }
                val (statusText, statusBg, statusColor) = when (webShopState.status) {
                    "LIVE" -> Triple("LIVE VPS", successGreen.copy(alpha = 0.15f), successGreen)
                    "QUEUED", "PROVISIONING" -> Triple("PROVISIONING...", Color(0xFF3B82F6).copy(alpha = 0.15f), Color(0xFF3B82F6))
                    "WAITING_DNS" -> if (isPlatformDomain) Triple("SECURING SSL", Color(0xFF3B82F6).copy(alpha = 0.15f), Color(0xFF3B82F6)) else Triple("DNS PENDING", Color(0xFFF59E0B).copy(alpha = 0.15f), Color(0xFFF59E0B))
                    "WAITING_TLS" -> Triple("SECURING SSL", Color(0xFFF59E0B).copy(alpha = 0.15f), Color(0xFFF59E0B))
                    "DEGRADED" -> Triple("DEGRADED", Color(0xFFF97316).copy(alpha = 0.15f), Color(0xFFF97316))
                    "FAILED" -> Triple("FAILED", Color(0xFFEF4444).copy(alpha = 0.15f), Color(0xFFEF4444))
                    else -> Triple("NOT LAUNCHED", Color(0xFF6B7280).copy(alpha = 0.15f), Color(0xFF9CA3AF))
                }
                Surface(
                    shape = RoundedCornerShape(20.dp),
                    color = statusBg,
                    border = BorderStroke(1.dp, statusColor)
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .clip(CircleShape)
                                .background(statusColor)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = statusText,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = statusColor
                        )
                    }
                }
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Live Shop Banner Card
            item {
                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = cardBg,
                    border = BorderStroke(1.dp, cardBorder),
                    shadowElevation = 2.dp
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(48.dp)
                                    .clip(RoundedCornerShape(12.dp))
                                    .background(
                                        Brush.linearGradient(
                                            colors = listOf(accentIndigo, Color(0xFF818CF8))
                                        )
                                    ),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Language,
                                    contentDescription = null,
                                    tint = Color.White,
                                    modifier = Modifier.size(26.dp)
                                )
                            }
                            Spacer(modifier = Modifier.width(12.dp))
                            Column(modifier = Modifier.weight(1f)) {
                                Text(
                                    text = storeName.ifBlank { "SwapnoPay Web Store" },
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                                Text(
                                    text = effectiveUrl,
                                    fontSize = 13.sp,
                                    color = accentIndigo,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(16.dp))

                        // Live Website Storefront Actions
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            // Direct 1-Click Launch Button when not deployed
                            if (!webShopState.isDeployed) {
                                Button(
                                    onClick = {
                                        viewModel.deployWebShop(
                                            storeName = storeName.ifBlank { "My Store" },
                                            shopSlug = storeSubdomain.ifBlank { "store" },
                                            customDomain = if (customDomain.contains(".") && !customDomain.contains("swapnopay.top")) customDomain else "",
                                            primaryCurrency = primaryCurrency,
                                            adminEmail = adminEmailInput,
                                            adminPassword = adminPasswordInput
                                        ) { _, msg ->
                                            Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                                        }
                                    },
                                    enabled = !webShopState.isDeploying,
                                    modifier = Modifier.fillMaxWidth().height(48.dp),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = successGreen,
                                        disabledContainerColor = successGreen.copy(alpha = 0.5f)
                                    ),
                                    shape = RoundedCornerShape(10.dp)
                                ) {
                                    if (webShopState.isDeploying || webShopState.status in listOf("QUEUED", "PROVISIONING")) {
                                        CircularProgressIndicator(color = Color.White, modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text("Provisioning Cloud Storefront...", fontWeight = FontWeight.Bold, color = Color.White, fontSize = 13.sp)
                                    } else {
                                        Icon(Icons.Default.RocketLaunch, null, modifier = Modifier.size(18.dp), tint = Color.White)
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text("⚡ 1-CLICK LAUNCH WEBSITE (FREE HOSTING)", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = Color.White)
                                    }
                                }
                            } else {
                                Button(
                                    onClick = {
                                        if (effectiveUrl.isNotBlank()) {
                                            runCatching {
                                                val intent = Intent(Intent.ACTION_VIEW, Uri.parse(effectiveUrl))
                                                context.startActivity(intent)
                                            }.onFailure {
                                                Toast.makeText(context, "Could not open browser for: $effectiveUrl", Toast.LENGTH_SHORT).show()
                                            }
                                        }
                                    },
                                    enabled = effectiveUrl.isNotBlank(),
                                    modifier = Modifier.fillMaxWidth().height(44.dp),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = Color(0xFF0F172A),
                                        disabledContainerColor = Color(0xFF0F172A).copy(alpha = 0.4f)
                                    ),
                                    shape = RoundedCornerShape(10.dp)
                                ) {
                                    Icon(Icons.Default.OpenInNew, null, modifier = Modifier.size(16.dp), tint = Color.White)
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text(
                                        text = "VISIT LIVE WEB STOREFRONT",
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Color.White
                                    )
                                }
                            }

                            if (webShopState.statusMessage.isNotBlank()) {
                                val displayBannerMsg = if (webShopState.status == "WAITING_DNS" && isPlatformDomain) {
                                    "⚡ Free instant hosting active! Securing SSL certificate for $effectiveUrl..."
                                } else {
                                    webShopState.statusMessage
                                }
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = if (webShopState.isDeployed) successGreen.copy(alpha = 0.12f) else Color(0xFF3B82F6).copy(alpha = 0.12f),
                                    border = BorderStroke(1.dp, if (webShopState.isDeployed) successGreen.copy(alpha = 0.3f) else Color(0xFF3B82F6).copy(alpha = 0.3f)),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Text(
                                        text = displayBannerMsg,
                                        fontSize = 11.5.sp,
                                        color = if (webShopState.isDeployed) successGreen else Color(0xFF2563EB),
                                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                                    )
                                }
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                OutlinedButton(
                                    onClick = {
                                        if (effectiveUrl.isNotBlank()) {
                                            clipboardManager.setText(AnnotatedString(effectiveUrl))
                                            Toast.makeText(context, "Shop URL copied to clipboard!", Toast.LENGTH_SHORT).show()
                                        } else {
                                            Toast.makeText(context, "Launch your storefront first to get a live URL", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                    enabled = effectiveUrl.isNotBlank(),
                                    modifier = Modifier.weight(1f).height(38.dp),
                                    shape = RoundedCornerShape(10.dp),
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Icon(Icons.Default.ContentCopy, null, modifier = Modifier.size(14.dp), tint = primaryText)
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text("Copy Link", fontSize = 12.sp, color = primaryText)
                                }

                                OutlinedButton(
                                    onClick = {
                                        if (effectiveUrl.isNotBlank()) {
                                            runCatching {
                                                val sendIntent = Intent().apply {
                                                    action = Intent.ACTION_SEND
                                                    putExtra(Intent.EXTRA_TEXT, "Visit our store at: $effectiveUrl")
                                                    type = "text/plain"
                                                }
                                                context.startActivity(Intent.createChooser(sendIntent, "Share Web Shop"))
                                            }
                                        } else {
                                            Toast.makeText(context, "Launch your storefront first to share", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                    enabled = effectiveUrl.isNotBlank(),
                                    modifier = Modifier.weight(1f).height(38.dp),
                                    shape = RoundedCornerShape(10.dp),
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Icon(Icons.Default.Share, null, modifier = Modifier.size(14.dp), tint = primaryText)
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text("Share Store", fontSize = 12.sp, color = primaryText)
                                }
                            }

                            if (webShopState.isDeployed) {
                                OutlinedButton(
                                    onClick = {
                                        viewModel.deployWebShop(
                                            storeName = storeName.ifBlank { "My Store" },
                                            shopSlug = storeSubdomain.ifBlank { "store" },
                                            customDomain = if (customDomain.contains(".") && !customDomain.contains("swapnopay.top")) customDomain else "",
                                            primaryCurrency = primaryCurrency,
                                            adminEmail = adminEmailInput,
                                            adminPassword = adminPasswordInput
                                        ) { _, msg ->
                                            Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                                        }
                                    },
                                    enabled = !webShopState.isDeploying,
                                    modifier = Modifier.fillMaxWidth().height(38.dp),
                                    shape = RoundedCornerShape(10.dp),
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Icon(Icons.Default.Refresh, null, modifier = Modifier.size(15.dp), tint = primaryText)
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text("Re-deploy / Update Website", fontSize = 12.sp, color = primaryText)
                                }
                            }
                        }
                    }
                }
            }

            // ── DEDICATED CUSTOM DOMAIN CARD (নিজস্ব ডোমেইন সংযোগ) ──
            item {
                var domainInput by remember(customDomain) { mutableStateOf(customDomain) }
                var isBindingDomain by remember { mutableStateOf(false) }
                var showDnsGuide by remember { mutableStateOf(false) }

                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = cardBg,
                    border = BorderStroke(1.dp, if (domainInput.isNotBlank()) Color(0xFF10B981) else cardBorder),
                    shadowElevation = 2.dp
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                modifier = Modifier.weight(1f),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(38.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(Color(0xFF10B981).copy(alpha = 0.15f)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Language,
                                        contentDescription = null,
                                        tint = Color(0xFF10B981),
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                                Column(modifier = Modifier.weight(1f, fill = false)) {
                                    Text(
                                        text = "Custom Domain (নিজস্ব ডোমেইন)",
                                        fontSize = 15.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = primaryText,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                    Text(
                                        text = if (customDomain.isNotBlank()) "Connected: https://${customDomain.trim()}" else "Connect your own domain (e.g. mystore.com)",
                                        fontSize = 11.5.sp,
                                        color = if (customDomain.isNotBlank()) Color(0xFF10B981) else secondaryText,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                }
                            }
                            Spacer(modifier = Modifier.width(8.dp))
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = if (customDomain.isNotBlank()) Color(0xFF10B981).copy(alpha = 0.15f) else Color(0xFFF59E0B).copy(alpha = 0.15f),
                                border = BorderStroke(1.dp, if (customDomain.isNotBlank()) Color(0xFF10B981).copy(alpha = 0.4f) else Color(0xFFF59E0B).copy(alpha = 0.4f))
                            ) {
                                Text(
                                    text = if (customDomain.isNotBlank()) "CONNECTED" else "OPTIONAL",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = if (customDomain.isNotBlank()) Color(0xFF10B981) else Color(0xFFD97706),
                                    maxLines = 1,
                                    softWrap = false,
                                    modifier = Modifier.padding(horizontal = 7.dp, vertical = 3.dp)
                                )
                            }
                        }

                        OutlinedTextField(
                            value = domainInput,
                            onValueChange = { input ->
                                domainInput = input.trim().lowercase()
                                    .removePrefix("https://")
                                    .removePrefix("http://")
                                    .replace(Regex("^/+"), "")
                                    .replace(Regex("/.*$"), "")
                                    .replace(Regex(":[0-9]+$"), "")
                            },
                            placeholder = { Text("e.g. yourbrand.com or shop.brand.com", fontSize = 13.sp, color = secondaryText) },
                            leadingIcon = { Icon(Icons.Default.Link, null, tint = Color(0xFF10B981), modifier = Modifier.size(18.dp)) },
                            trailingIcon = {
                                if (domainInput.isNotEmpty()) {
                                    IconButton(onClick = { domainInput = "" }) {
                                        Icon(Icons.Default.Clear, null, tint = secondaryText, modifier = Modifier.size(16.dp))
                                    }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                            singleLine = true,
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Color(0xFF10B981),
                                unfocusedBorderColor = cardBorder,
                                focusedTextColor = primaryText,
                                unfocusedTextColor = primaryText
                            )
                        )

                        Text(
                            text = "💡 1-Click Free Hosting is included automatically at https://${storeSubdomain.ifBlank { "store" }}.shop.swapnopay.top. Connect a custom domain only if you purchased your own domain (e.g. yourbrand.com).",
                            fontSize = 11.5.sp,
                            color = secondaryText
                        )

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Button(
                                onClick = {
                                    val cleanDomain = domainInput.trim().lowercase()
                                        .removePrefix("https://")
                                        .removePrefix("http://")
                                        .replace(Regex("^/+"), "")
                                        .replace(Regex("/.*$"), "")
                                        .replace(Regex(":[0-9]+$"), "")

                                    if (!webShopState.isDeployed) {
                                        Toast.makeText(context, "Please click '1-Click Launch Website' first before connecting a custom domain.", Toast.LENGTH_LONG).show()
                                        return@Button
                                    }
                                    if (cleanDomain.isNotBlank() && cleanDomain.contains("swapnopay.top")) {
                                        Toast.makeText(context, "Free platform domain is already active! Custom domain is for your own domain (e.g. brand.com).", Toast.LENGTH_LONG).show()
                                        return@Button
                                    }
                                    if (cleanDomain.isNotBlank() && !cleanDomain.contains(".")) {
                                        Toast.makeText(context, "Please enter a valid domain name with an extension (e.g. yourbrand.com)", Toast.LENGTH_LONG).show()
                                        return@Button
                                    }
                                    isBindingDomain = true
                                    viewModel.updateWebShopCustomDomain(cleanDomain) { success, msg ->
                                        isBindingDomain = false
                                        if (success) {
                                            customDomain = cleanDomain
                                        }
                                        Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                                    }
                                },
                                enabled = !isBindingDomain,
                                modifier = Modifier.weight(1f).height(44.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981))
                            ) {
                                if (isBindingDomain) {
                                    CircularProgressIndicator(color = Color.White, modifier = Modifier.size(18.dp))
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text("Saving Domain...", fontSize = 12.5.sp, color = Color.White)
                                } else {
                                    Icon(Icons.Default.Save, null, modifier = Modifier.size(16.dp), tint = Color.White)
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text("Save & Connect Domain", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Color.White)
                                }
                            }

                            OutlinedButton(
                                onClick = { showDnsGuide = !showDnsGuide },
                                modifier = Modifier.height(44.dp),
                                shape = RoundedCornerShape(10.dp),
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Icon(Icons.Default.Dns, null, modifier = Modifier.size(16.dp), tint = primaryText)
                                Spacer(modifier = Modifier.width(6.dp))
                                Text(if (showDnsGuide) "Hide DNS" else "DNS Setup", fontSize = 12.sp, color = primaryText)
                            }
                        }

                        // Collapsible DNS Instructions Box
                        AnimatedVisibility(visible = showDnsGuide) {
                            Surface(
                                shape = RoundedCornerShape(12.dp),
                                color = if (isDarkMode) Color(0xFF162D20) else Color(0xFFF0FDF4),
                                border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.4f)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(
                                    modifier = Modifier.padding(12.dp),
                                    verticalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Text(
                                        text = "DNS Configuration Guide (ডোমেইন পয়েন্ট করুন):",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 12.sp,
                                        color = if (isDarkMode) Color(0xFF6EE7B7) else Color(0xFF065F46)
                                    )
                                    Text(
                                        text = "1. CNAME Record: Host 'shop' or '@' points to 'vps.swapnopay.top'",
                                        fontSize = 11.5.sp,
                                        fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace,
                                        color = primaryText
                                    )
                                    Text(
                                        text = "2. Or A Record: Host '@' points to '159.65.132.85' (VPS IP)",
                                        fontSize = 11.5.sp,
                                        fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace,
                                        color = primaryText
                                    )
                                    Text(
                                        text = "Note: DNS changes typically propagate within 5-30 minutes worldwide.",
                                        fontSize = 10.5.sp,
                                        color = secondaryText
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // Store Admin Panel & Control Center Card
            item {
                val adminUrl = if (effectiveUrl.endsWith("/")) effectiveUrl + "admin" else "$effectiveUrl/admin"
                val adminLoginUrl = if (effectiveUrl.endsWith("/")) effectiveUrl + "admin/login.php" else "$effectiveUrl/admin/login.php"

                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = cardBg,
                    border = BorderStroke(1.5.dp, Color(0xFF4F46E5).copy(alpha = 0.4f)),
                    shadowElevation = 3.dp
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                modifier = Modifier.weight(1f),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(38.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(Brush.linearGradient(listOf(Color(0xFF4F46E5), Color(0xFF7C3AED)))),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(Icons.Default.Lock, null, tint = Color.White, modifier = Modifier.size(20.dp))
                                }
                                Spacer(modifier = Modifier.width(10.dp))
                                Column(modifier = Modifier.weight(1f, fill = false)) {
                                    Text(
                                        text = "Store Admin Panel & Control",
                                        fontSize = 14.5.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = primaryText,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                    Text(
                                        text = "Dedicated Web Management Portal",
                                        fontSize = 11.5.sp,
                                        color = secondaryText,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                }
                            }
                            Spacer(modifier = Modifier.width(8.dp))
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = Color(0xFF10B981).copy(alpha = 0.15f),
                                border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.4f))
                            ) {
                                Text(
                                    text = "SUPER ADMIN",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = Color(0xFF10B981),
                                    maxLines = 1,
                                    softWrap = false,
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        // Prominent Launch Admin Button
                        Button(
                            onClick = {
                                if (adminLoginUrl.isNotBlank()) {
                                    runCatching {
                                        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(adminLoginUrl))
                                        context.startActivity(intent)
                                    }.onFailure {
                                        Toast.makeText(context, "Opening admin panel: $adminLoginUrl", Toast.LENGTH_SHORT).show()
                                    }
                                }
                            },
                            enabled = webShopState.isDeployed && adminLoginUrl.isNotBlank(),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(46.dp),
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color(0xFF4F46E5),
                                disabledContainerColor = Color(0xFF4F46E5).copy(alpha = 0.4f)
                            )
                        ) {
                            Icon(Icons.Default.RocketLaunch, null, modifier = Modifier.size(18.dp), tint = Color.White)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = if (webShopState.isDeployed) "LAUNCH STORE ADMIN PANEL" else "ADMIN PANEL AVAILABLE AFTER VPS LAUNCH",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }

                        Spacer(modifier = Modifier.height(12.dp))

                        // Credentials Container
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = if (isDarkMode) Color(0xFF181820) else Color(0xFFF1F5F9),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(12.dp),
                                verticalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Text(
                                    text = "Admin Access Credentials",
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )

                                // Login URL Row
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("LOGIN URL", fontSize = 10.sp, fontWeight = FontWeight.SemiBold, color = secondaryText)
                                        Text(adminLoginUrl, fontSize = 12.sp, color = primaryText, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    }
                                    IconButton(
                                        onClick = {
                                            clipboardManager.setText(AnnotatedString(adminLoginUrl))
                                            Toast.makeText(context, "Admin URL copied!", Toast.LENGTH_SHORT).show()
                                        },
                                        modifier = Modifier.size(32.dp)
                                    ) {
                                        Icon(Icons.Default.ContentCopy, null, modifier = Modifier.size(15.dp), tint = accentIndigo)
                                    }
                                }

                                HorizontalDivider(color = cardBorder.copy(alpha = 0.5f))

                                // Email Row
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("ADMIN EMAIL / USERNAME", fontSize = 10.sp, fontWeight = FontWeight.SemiBold, color = secondaryText)
                                        Text(webShopState.adminEmail, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = primaryText)
                                    }
                                    IconButton(
                                        onClick = {
                                            clipboardManager.setText(AnnotatedString(webShopState.adminEmail))
                                            Toast.makeText(context, "Admin email copied!", Toast.LENGTH_SHORT).show()
                                        },
                                        modifier = Modifier.size(32.dp)
                                    ) {
                                        Icon(Icons.Default.ContentCopy, null, modifier = Modifier.size(15.dp), tint = accentIndigo)
                                    }
                                }

                                HorizontalDivider(color = cardBorder.copy(alpha = 0.5f))

                                // Password Row
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("DEFAULT PASSWORD", fontSize = 10.sp, fontWeight = FontWeight.SemiBold, color = secondaryText)
                                        Text(
                                            if (showPassword) webShopState.adminPassword else "••••••••••••",
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.Medium,
                                            color = primaryText
                                        )
                                    }
                                    Row {
                                        IconButton(
                                            onClick = { showPassword = !showPassword },
                                            modifier = Modifier.size(32.dp)
                                        ) {
                                            Icon(
                                                if (showPassword) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                                null,
                                                modifier = Modifier.size(15.dp),
                                                tint = secondaryText
                                            )
                                        }
                                        IconButton(
                                            onClick = {
                                                clipboardManager.setText(AnnotatedString(webShopState.adminPassword))
                                                Toast.makeText(context, "Password copied to clipboard!", Toast.LENGTH_SHORT).show()
                                            },
                                            modifier = Modifier.size(32.dp)
                                        ) {
                                            Icon(Icons.Default.ContentCopy, null, modifier = Modifier.size(15.dp), tint = accentIndigo)
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(2.dp))

                                OutlinedButton(
                                    onClick = {
                                        editEmail = webShopState.adminEmail
                                        editPassword = webShopState.adminPassword
                                        showEditCredentialsDialog = true
                                    },
                                    modifier = Modifier.fillMaxWidth().height(36.dp),
                                    shape = RoundedCornerShape(8.dp),
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Icon(Icons.Default.Edit, null, modifier = Modifier.size(14.dp), tint = primaryText)
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text("Change Admin Login Credentials", fontSize = 11.5.sp, color = primaryText)
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        // 6 Quick-Access Admin Module Shortcuts
                        Text(
                            text = "Admin Control Shortcuts (Deep-Links)",
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = primaryText
                        )
                        Spacer(modifier = Modifier.height(8.dp))

                        val shortcuts = listOf(
                            Triple("📦 Products", "$adminUrl/product.php", Color(0xFF3B82F6)),
                            Triple("➕ Add Product", "$adminUrl/product-add.php", Color(0xFF10B981)),
                            Triple("🛒 Orders", "$adminUrl/order.php", Color(0xFF8B5CF6)),
                            Triple("⚙️ Settings", "$adminUrl/settings.php", Color(0xFFF59E0B)),
                            Triple("🖼️ Banners", "$adminUrl/slider.php", Color(0xFFEC4899)),
                            Triple("🎟️ Coupons", "$adminUrl/coupons.php", Color(0xFF06B6D4))
                        )

                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            for (row in shortcuts.chunked(3)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    for ((title, url, color) in row) {
                                        Surface(
                                            shape = RoundedCornerShape(8.dp),
                                            color = color.copy(alpha = 0.08f),
                                            border = BorderStroke(1.dp, color.copy(alpha = 0.25f)),
                                            modifier = Modifier
                                                .weight(1f)
                                                .clickable {
                                                    runCatching {
                                                        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                                                        context.startActivity(intent)
                                                    }.onFailure {
                                                        Toast.makeText(context, "Opening $title", Toast.LENGTH_SHORT).show()
                                                    }
                                                }
                                        ) {
                                            Row(
                                                modifier = Modifier.padding(vertical = 8.dp, horizontal = 6.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.Center
                                            ) {
                                                Text(
                                                    text = title,
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = color,
                                                    maxLines = 1,
                                                    overflow = TextOverflow.Ellipsis
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // PostgreSQL Database Status Card
            item {
                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = cardBg,
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp)
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween,
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    imageVector = Icons.Default.Storage,
                                    contentDescription = null,
                                    tint = Color(0xFF3B82F6),
                                    modifier = Modifier.size(20.dp)
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    text = "PostgreSQL Merchant Database",
                                    fontSize = 15.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                            }
                            Surface(
                                shape = RoundedCornerShape(6.dp),
                                color = successGreen.copy(alpha = 0.15f)
                            ) {
                                Text(
                                    text = "CONNECTED",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = successGreen,
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                )
                            }
                        }
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = "Connected with Supabase & VPS PostgreSQL. All 66 tables from ecommerceweb.sql are mapped with multi-tenant UUID isolation.",
                            fontSize = 12.5.sp,
                            color = secondaryText
                        )
                    }
                }
            }

            // Deployment Controls Card
            item {
                var isConfigExpanded by remember { mutableStateOf(true) }

                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = cardBg,
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Box(
                                    modifier = Modifier.size(32.dp).clip(RoundedCornerShape(8.dp)).background(Color(0xFFEEF2FF)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(Icons.Default.Settings, null, tint = Color(0xFF6366F1), modifier = Modifier.size(18.dp))
                                }
                                Column {
                                    Text("Store Configuration & VPS Launch", fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                    Text("Domain, subdomain, and storefront parameters", fontSize = 11.sp, color = secondaryText)
                                }
                            }
                            IconButton(onClick = { isConfigExpanded = !isConfigExpanded }, modifier = Modifier.size(32.dp)) {
                                Icon(
                                    imageVector = if (isConfigExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                                    contentDescription = null,
                                    tint = secondaryText
                                )
                            }
                        }

                        if (isConfigExpanded) {
                            Spacer(modifier = Modifier.height(14.dp))

                        OutlinedTextField(
                            value = storeName,
                            onValueChange = { storeName = it },
                            label = { Text("Business / Store Name") },
                            leadingIcon = { Icon(Icons.Default.Storefront, contentDescription = null) },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )

                        Spacer(modifier = Modifier.height(10.dp))

                        OutlinedTextField(
                            value = storeSubdomain,
                            onValueChange = { storeSubdomain = it.lowercase().replace(" ", "-") },
                            label = { Text("Subdomain Prefix (.swapnopay.top)") },
                            leadingIcon = { Icon(Icons.Default.Domain, contentDescription = null) },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )

                        Spacer(modifier = Modifier.height(10.dp))

                        OutlinedTextField(
                            value = customDomain,
                            onValueChange = { customDomain = it },
                            label = { Text("Custom Domain (Optional: e.g. mystore.com)") },
                            leadingIcon = { Icon(Icons.Default.Link, contentDescription = null) },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )

                        Spacer(modifier = Modifier.height(10.dp))

                        OutlinedTextField(
                            value = adminEmailInput,
                            onValueChange = { adminEmailInput = it },
                            label = { Text("Store Admin Login Email") },
                            leadingIcon = { Icon(Icons.Default.Person, contentDescription = null) },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )

                        Spacer(modifier = Modifier.height(10.dp))

                        OutlinedTextField(
                            value = adminPasswordInput,
                            onValueChange = { adminPasswordInput = it },
                            label = { Text("Store Admin Login Password") },
                            leadingIcon = { Icon(Icons.Default.Lock, contentDescription = null) },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )

                        Spacer(modifier = Modifier.height(12.dp))

                        if (webShopState.statusMessage.isNotBlank()) {
                            val displayStatusMsg = if (webShopState.status == "WAITING_DNS" && isPlatformDomain) {
                                "⚡ Free instant hosting active! Securing SSL certificate for $effectiveUrl..."
                            } else {
                                webShopState.statusMessage
                            }
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = if (webShopState.isDeployed) successGreen.copy(alpha = 0.1f) else Color(0xFFF59E0B).copy(alpha = 0.1f),
                                border = BorderStroke(1.dp, if (webShopState.isDeployed) successGreen.copy(alpha = 0.3f) else Color(0xFFF59E0B).copy(alpha = 0.3f)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Text(
                                    text = displayStatusMsg,
                                    fontSize = 12.sp,
                                    color = if (webShopState.isDeployed) successGreen else Color(0xFFF59E0B),
                                    modifier = Modifier.padding(10.dp)
                                )
                            }
                            Spacer(modifier = Modifier.height(10.dp))
                        }

                        Button(
                            onClick = {
                                viewModel.deployWebShop(
                                    storeName = storeName.ifBlank { "My Store" },
                                    shopSlug = storeSubdomain.ifBlank { "store" },
                                    customDomain = if (customDomain.contains(".") && !customDomain.contains("swapnopay.top")) customDomain else "",
                                    primaryCurrency = primaryCurrency,
                                    adminEmail = adminEmailInput,
                                    adminPassword = adminPasswordInput
                                ) { success: Boolean, msg: String ->
                                    Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                                }
                            },
                            enabled = !webShopState.isDeploying,
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(48.dp),
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = successGreen)
                        ) {
                            if (webShopState.isDeploying) {
                                CircularProgressIndicator(color = Color.White, modifier = Modifier.size(22.dp))
                                Spacer(modifier = Modifier.width(8.dp))
                                Text("Provisioning VPS Web Instance...", color = Color.White)
                            } else {
                                Icon(Icons.Default.RocketLaunch, contentDescription = null)
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    text = if (webShopState.isDeployed) "RE-DEPLOY / UPDATE STOREFRONT"
                                    else if (webShopState.status in listOf("QUEUED", "PROVISIONING")) "STOREFRONT IS PROVISIONING..."
                                    else "LAUNCH WEBSITE ON VPS",
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                        } // end isConfigExpanded
                    }
                }
            }

            // ── STORE CATALOG & INVENTORY SYNC CARD ──
            item {
                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = cardBg,
                    border = BorderStroke(1.dp, cardBorder),
                    shadowElevation = 2.dp
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                modifier = Modifier.weight(1f),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(38.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(Color(0xFF3B82F6).copy(alpha = 0.15f)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Inventory2,
                                        contentDescription = null,
                                        tint = Color(0xFF3B82F6),
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                                Column(modifier = Modifier.weight(1f, fill = false)) {
                                    Text(
                                        text = "Store Catalog & Inventory Sync",
                                        fontSize = 15.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = primaryText,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                    Text(
                                        text = "Sync POS products to web store catalog",
                                        fontSize = 11.5.sp,
                                        color = secondaryText,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                }
                            }
                            Spacer(modifier = Modifier.width(8.dp))
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = if (webShopState.productsCount > 0) Color(0xFF10B981).copy(alpha = 0.15f) else Color(0xFF64748B).copy(alpha = 0.15f),
                                border = BorderStroke(1.dp, if (webShopState.productsCount > 0) Color(0xFF10B981).copy(alpha = 0.4f) else Color(0xFF64748B).copy(alpha = 0.4f))
                            ) {
                                Text(
                                    text = "${webShopState.productsCount} ONLINE",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = if (webShopState.productsCount > 0) Color(0xFF10B981) else Color(0xFF64748B),
                                    maxLines = 1,
                                    softWrap = false,
                                    modifier = Modifier.padding(horizontal = 7.dp, vertical = 3.dp)
                                )
                            }
                        }

                        // Product count summary pills
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Surface(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(10.dp),
                                color = if (isDarkMode) Color(0xFF1E293B) else Color(0xFFF1F5F9)
                            ) {
                                Column(modifier = Modifier.padding(10.dp)) {
                                    Text("Local POS Items", fontSize = 11.sp, color = secondaryText)
                                    Text("${localProducts.size} products", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                }
                            }
                            Surface(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(10.dp),
                                color = if (isDarkMode) Color(0xFF1E293B) else Color(0xFFF1F5F9)
                            ) {
                                Column(modifier = Modifier.padding(10.dp)) {
                                    Text("Online Storefront", fontSize = 11.sp, color = secondaryText)
                                    Text("${webShopState.productsCount} in catalog", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color(0xFF3B82F6))
                                }
                            }
                        }

                        if (webShopState.syncMessage.isNotBlank()) {
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = Color(0xFF3B82F6).copy(alpha = 0.1f),
                                border = BorderStroke(1.dp, Color(0xFF3B82F6).copy(alpha = 0.3f)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Text(
                                    text = webShopState.syncMessage,
                                    fontSize = 11.5.sp,
                                    color = Color(0xFF3B82F6),
                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                                )
                            }
                        }

                        Button(
                            onClick = {
                                viewModel.syncLocalInventoryToWebShop { count, msg ->
                                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                }
                            },
                            enabled = !webShopState.isSyncing && (webShopState.isDeployed || webShopState.status == "LIVE"),
                            modifier = Modifier.fillMaxWidth().height(44.dp),
                            shape = RoundedCornerShape(10.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color(0xFF3B82F6),
                                disabledContainerColor = Color(0xFF3B82F6).copy(alpha = 0.4f)
                            )
                        ) {
                            if (webShopState.isSyncing) {
                                CircularProgressIndicator(color = Color.White, modifier = Modifier.size(18.dp))
                                Spacer(modifier = Modifier.width(8.dp))
                                Text("Syncing Products to Store...", fontSize = 12.5.sp, color = Color.White)
                            } else {
                                Icon(Icons.Default.Sync, null, modifier = Modifier.size(16.dp), tint = Color.White)
                                Spacer(modifier = Modifier.width(6.dp))
                                Text(
                                    text = if (webShopState.isDeployed || webShopState.status == "LIVE") "Sync Local Products to Store" else "Launch Store First to Sync Products",
                                    fontSize = 12.5.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color.White
                                )
                            }
                        }
                    }
                }
            }

            // Recent Web Orders Card
            item {
                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = cardBg,
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    imageVector = Icons.Default.ShoppingBag,
                                    contentDescription = null,
                                    tint = successGreen
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    text = "Recent Web Store Orders",
                                    fontSize = 15.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                            }
                            Surface(
                                shape = RoundedCornerShape(12.dp),
                                color = accentIndigo.copy(alpha = 0.12f)
                            ) {
                                Text(
                                    text = "${recentOrders.size} Orders",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = accentIndigo,
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(10.dp))

                        if (recentOrders.isEmpty()) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 16.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                    Icon(
                                        imageVector = Icons.Default.Inbox,
                                        contentDescription = null,
                                        tint = secondaryText.copy(alpha = 0.5f),
                                        modifier = Modifier.size(32.dp)
                                    )
                                    Spacer(modifier = Modifier.height(6.dp))
                                    Text(
                                        text = "No web orders yet. Orders placed on your web store will appear here in real time.",
                                        fontSize = 12.sp,
                                        color = secondaryText,
                                        textAlign = androidx.compose.ui.text.style.TextAlign.Center
                                    )
                                }
                            }
                        } else {
                            recentOrders.take(5).forEach { order ->
                                val isPaid = order.status.equals("PAID", ignoreCase = true)
                                val statusColor = if (isPaid) successGreen else Color(0xFFF59E0B)
                                val dateStr = java.text.SimpleDateFormat("dd MMM, hh:mm a", java.util.Locale.getDefault())
                                    .format(java.util.Date(order.createdAt))

                                Surface(
                                    shape = RoundedCornerShape(10.dp),
                                    color = if (isDarkMode) Color(0xFF1E1E24) else Color(0xFFF8FAFC),
                                    border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF2E2E38) else Color(0xFFE2E8F0)),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(vertical = 4.dp)
                                ) {
                                    Column(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(12.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Column(modifier = Modifier.weight(1f)) {
                                                Row(verticalAlignment = Alignment.CenterVertically) {
                                                    Text(
                                                        text = order.customerName.ifBlank { "Online Customer" },
                                                        fontSize = 13.5.sp,
                                                        fontWeight = FontWeight.SemiBold,
                                                        color = primaryText
                                                    )
                                                    Spacer(modifier = Modifier.width(6.dp))
                                                    Surface(
                                                        shape = RoundedCornerShape(4.dp),
                                                        color = accentIndigo.copy(alpha = 0.1f)
                                                    ) {
                                                        Text(
                                                            text = order.method.ifBlank { "MFS" },
                                                            fontSize = 10.sp,
                                                            fontWeight = FontWeight.Medium,
                                                            color = accentIndigo,
                                                            modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp)
                                                        )
                                                    }
                                                }
                                                Spacer(modifier = Modifier.height(2.dp))
                                                Text(
                                                    text = "${order.customerPhone.ifBlank { "N/A" }} • $dateStr",
                                                    fontSize = 11.5.sp,
                                                    color = secondaryText
                                                )
                                            }

                                            Column(horizontalAlignment = Alignment.End) {
                                                Text(
                                                    text = "৳ ${String.format(java.util.Locale.US, "%.2f", order.amount)}",
                                                    fontSize = 14.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = primaryText
                                                )
                                                Spacer(modifier = Modifier.height(2.dp))
                                                Surface(
                                                    shape = RoundedCornerShape(4.dp),
                                                    color = statusColor.copy(alpha = 0.15f)
                                                ) {
                                                    Text(
                                                        text = order.status,
                                                        fontSize = 10.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = statusColor,
                                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                                    )
                                                }
                                            }
                                        }

                                        // Quick Fulfillment Action Chips
                                        Spacer(modifier = Modifier.height(8.dp))
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.End,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            if (order.status.equals("PENDING", ignoreCase = true)) {
                                                FilledTonalButton(
                                                    onClick = {
                                                        viewModel.updateWebShopOrderStatus(order.id, "PAID") { ok, msg ->
                                                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                                        }
                                                    },
                                                    modifier = Modifier.height(30.dp),
                                                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 0.dp),
                                                    shape = RoundedCornerShape(6.dp),
                                                    colors = ButtonDefaults.filledTonalButtonColors(containerColor = successGreen.copy(alpha = 0.15f))
                                                ) {
                                                    Icon(Icons.Default.Check, null, modifier = Modifier.size(13.dp), tint = successGreen)
                                                    Spacer(modifier = Modifier.width(4.dp))
                                                    Text("Mark Paid", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = successGreen)
                                                }
                                            } else if (order.status.equals("PAID", ignoreCase = true)) {
                                                FilledTonalButton(
                                                    onClick = {
                                                        viewModel.updateWebShopOrderStatus(order.id, "SHIPPED", "Dispatched") { ok, msg ->
                                                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                                        }
                                                    },
                                                    modifier = Modifier.height(30.dp),
                                                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 0.dp),
                                                    shape = RoundedCornerShape(6.dp),
                                                    colors = ButtonDefaults.filledTonalButtonColors(containerColor = accentIndigo.copy(alpha = 0.15f))
                                                ) {
                                                    Icon(Icons.Default.LocalShipping, null, modifier = Modifier.size(13.dp), tint = accentIndigo)
                                                    Spacer(modifier = Modifier.width(4.dp))
                                                    Text("Dispatch / Ship", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = accentIndigo)
                                                }
                                            } else if (order.status.equals("SHIPPED", ignoreCase = true)) {
                                                FilledTonalButton(
                                                    onClick = {
                                                        viewModel.updateWebShopOrderStatus(order.id, "DELIVERED", "Delivered") { ok, msg ->
                                                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                                        }
                                                    },
                                                    modifier = Modifier.height(30.dp),
                                                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 0.dp),
                                                    shape = RoundedCornerShape(6.dp),
                                                    colors = ButtonDefaults.filledTonalButtonColors(containerColor = successGreen.copy(alpha = 0.15f))
                                                ) {
                                                    Icon(Icons.Default.DoneAll, null, modifier = Modifier.size(13.dp), tint = successGreen)
                                                    Spacer(modifier = Modifier.width(4.dp))
                                                    Text("Delivered", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = successGreen)
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Enterprise Branding Banner
            item {
                Surface(
                    shape = RoundedCornerShape(14.dp),
                    color = if (isDarkMode) Color(0xFF1E1B4B) else Color(0xFFEEF2FF),
                    border = BorderStroke(1.dp, Color(0xFF818CF8).copy(alpha = 0.3f)),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(14.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        Box(
                            modifier = Modifier.size(36.dp).clip(RoundedCornerShape(10.dp)).background(Color(0xFF6366F1)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Default.BarChart, null, tint = Color.White, modifier = Modifier.size(20.dp))
                        }
                        Column(modifier = Modifier.weight(1f)) {
                            Text("Your online store is live and ready!", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = primaryText)
                            Text("Manage products, track orders, and grow your business.", fontSize = 11.sp, color = secondaryText)
                        }
                        Text("SWAPNOPAY\nENTERPRISE", fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF6366F1), textAlign = TextAlign.End)
                    }
                }
            }

            // Bottom spacing to prevent navigation obstruction
            item {
                Spacer(modifier = Modifier.height(28.dp))
            }
        }


        // Edit Admin Credentials Dialog
        if (showEditCredentialsDialog) {
            AlertDialog(
                onDismissRequest = { showEditCredentialsDialog = false },
                title = { Text("Update Store Admin Credentials") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text(
                            text = "Set new login email and password for your web store admin panel ($effectiveUrl/admin).",
                            fontSize = 12.5.sp,
                            color = secondaryText
                        )
                        OutlinedTextField(
                            value = editEmail,
                            onValueChange = { editEmail = it },
                            label = { Text("Admin Email / Username") },
                            leadingIcon = { Icon(Icons.Default.Person, contentDescription = null) },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = editPassword,
                            onValueChange = { editPassword = it },
                            label = { Text("New Password (min 12 chars)") },
                            leadingIcon = { Icon(Icons.Default.Lock, contentDescription = null) },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                    }
                },
                confirmButton = {
                    Button(
                        onClick = {
                            if (editEmail.isNotBlank() && editPassword.length >= 12) {
                                showEditCredentialsDialog = false
                                viewModel.updateWebShopAdminCredentials(editEmail, editPassword) { success, msg ->
                                    Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                                }
                            } else {
                                Toast.makeText(context, "Please enter a valid email and password (min 12 chars)", Toast.LENGTH_SHORT).show()
                            }
                        }
                    ) {
                        Text("Save Credentials")
                    }
                },
                dismissButton = {
                    TextButton(onClick = { showEditCredentialsDialog = false }) {
                        Text("Cancel")
                    }
                }
            )
        }
    }
}

