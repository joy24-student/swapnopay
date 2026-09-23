@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
package com.example.ui

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.widget.Toast
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.example.data.local.ProductItemEntity
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import org.json.JSONObject

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * 1. EMPLOYEE LOGIN SCREEN (Staff Portal Terminal Pairing)
 * ═══════════════════════════════════════════════════════════════════════════════
 */
@Composable
fun EmployeeLoginScreen(viewModel: AppViewModel) {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val isLoading by viewModel.isEmployeeLoading.collectAsState()

    var loginMode by remember { mutableStateOf("QR") } // "QR" or "MANUAL"
    var manualQrInput by remember { mutableStateOf("") }
    var staffPinInput by remember { mutableStateOf("") }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    val bgGradient = if (isDark) {
        Brush.verticalGradient(listOf(Color(0xFF0F172A), Color(0xFF090D16)))
    } else {
        Brush.verticalGradient(listOf(Color(0xFFF1F5F9), Color(0xFFE2E8F0)))
    }

    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val cardBorder = if (isDark) Color(0xFF334155) else Color(0xFFCBD5E1)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val brandBlue = Color(0xFF2563EB)

    fun handlePairing(rawJson: String) {
        if (rawJson.isBlank()) {
            errorMessage = "অনুগ্রহ করে বৈধ পেয়ারিং কিউআর কোড স্ক্যান বা ইনপুট করুন।"
            return
        }
        errorMessage = null
        viewModel.pairEmployeeWithQr(
            qrPayloadJson = rawJson.trim(),
            staffPin = staffPinInput.trim().ifBlank { null },
            onSuccess = { session ->
                Toast.makeText(context, "স্বাগতম ${session.employeeName}! লগইন সফল হয়েছে।", Toast.LENGTH_SHORT).show()
                viewModel.navigateTo("EmployeePortal")
            },
            onError = { err ->
                errorMessage = err
            }
        )
    }

    Scaffold(
        modifier = Modifier.fillMaxSize(),
        containerColor = Color.Transparent
    ) { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(bgGradient)
                .padding(paddingValues)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Top Return to Merchant Login Bar
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    IconButton(
                        onClick = { viewModel.navigateTo("Login") },
                        modifier = Modifier
                            .size(40.dp)
                            .clip(RoundedCornerShape(12.dp))
                            .background(cardBg)
                            .border(1.dp, cardBorder, RoundedCornerShape(12.dp))
                    ) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "মার্চেন্ট লগইন",
                            tint = textPrimary
                        )
                    }

                    Text(
                        text = "স্টাফ সেলস টার্মিনাল",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = textSecondary
                    )

                    Spacer(modifier = Modifier.size(40.dp))
                }

                Spacer(modifier = Modifier.height(24.dp))

                // Brand Emblem
                Box(
                    modifier = Modifier
                        .size(72.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.linearGradient(listOf(Color(0xFF2563EB), Color(0xFF4F46E5)))
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Badge,
                        contentDescription = null,
                        tint = Color.White,
                        modifier = Modifier.size(38.dp)
                    )
                }

                Spacer(modifier = Modifier.height(14.dp))

                Text(
                    text = "কর্মচারী সেলস পোর্টাল",
                    fontSize = 24.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary
                )

                Spacer(modifier = Modifier.height(6.dp))

                Text(
                    text = "মার্চেন্ট অ্যাপের 'Employees' সেকশন থেকে আপনার স্টাফ কিউআর কোডটি স্ক্যান করে সাইন ইন করুন।",
                    fontSize = 13.sp,
                    color = textSecondary,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(horizontal = 16.dp)
                )

                Spacer(modifier = Modifier.height(24.dp))

                // Mode Selector Tabs (Scan QR vs Manual Input)
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(cardBg)
                        .border(1.dp, cardBorder, RoundedCornerShape(14.dp))
                        .padding(4.dp)
                ) {
                    TabPill(
                        selected = loginMode == "QR",
                        title = "📷 কিউআর স্ক্যানার",
                        onClick = { loginMode = "QR"; errorMessage = null },
                        modifier = Modifier.weight(1f)
                    )
                    TabPill(
                        selected = loginMode == "MANUAL",
                        title = "⌨️ পেয়ারিং কোড",
                        onClick = { loginMode = "MANUAL"; errorMessage = null },
                        modifier = Modifier.weight(1f)
                    )
                }

                Spacer(modifier = Modifier.height(20.dp))

                // Error Banner
                if (!errorMessage.isNullOrBlank()) {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 16.dp),
                        colors = CardDefaults.cardColors(containerColor = Color(0xFFEF4444).copy(alpha = 0.12f)),
                        border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.4f)),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Default.ErrorOutline,
                                contentDescription = null,
                                tint = Color(0xFFEF4444),
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(modifier = Modifier.width(10.dp))
                            Text(
                                text = errorMessage!!,
                                fontSize = 12.sp,
                                color = Color(0xFFEF4444),
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }
                }

                // Mode Content
                if (loginMode == "QR") {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(340.dp),
                        shape = RoundedCornerShape(20.dp),
                        colors = CardDefaults.cardColors(containerColor = cardBg),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Box(modifier = Modifier.fillMaxSize()) {
                            RealQrCameraScanner(
                                onQrScanned = { code ->
                                    if (!isLoading) {
                                        handlePairing(code)
                                    }
                                },
                                modifier = Modifier.fillMaxSize()
                            )
                        }
                    }
                } else {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(20.dp),
                        colors = CardDefaults.cardColors(containerColor = cardBg),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Column(modifier = Modifier.padding(20.dp)) {
                            Text(
                                text = "পেয়ারিং JSON বা পেয়ারিং কোড লিখুন:",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = textPrimary
                            )
                            Spacer(modifier = Modifier.height(10.dp))

                            OutlinedTextField(
                                value = manualQrInput,
                                onValueChange = { manualQrInput = it },
                                placeholder = {
                                    Text(
                                        "মার্চেন্ট অ্যাপ থেকে প্রাপ্ত JSON পেস্ট করুন",
                                        fontSize = 12.sp,
                                        color = textSecondary
                                    )
                                },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(130.dp),
                                shape = RoundedCornerShape(12.dp),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = brandBlue,
                                    unfocusedBorderColor = cardBorder
                                )
                            )

                            Spacer(modifier = Modifier.height(16.dp))

                            Button(
                                onClick = { handlePairing(manualQrInput) },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(48.dp),
                                shape = RoundedCornerShape(12.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = brandBlue),
                                enabled = !isLoading && manualQrInput.isNotBlank()
                            ) {
                                if (isLoading) {
                                    CircularProgressIndicator(
                                        color = Color.White,
                                        modifier = Modifier.size(20.dp),
                                        strokeWidth = 2.dp
                                    )
                                } else {
                                    Icon(Icons.Default.Login, contentDescription = null, modifier = Modifier.size(18.dp))
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text("পেয়ার ও সাইন ইন করুন", fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }

                Spacer(modifier = Modifier.height(30.dp))
                Spacer(modifier = Modifier.height(16.dp))

                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text(
                            text = "🔒 স্টাফ সিকিউরিটি পিন (যদি মার্চেন্ট সেট করে থাকেন)",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = textPrimary
                        )
                        Spacer(modifier = Modifier.height(6.dp))
                        OutlinedTextField(
                            value = staffPinInput,
                            onValueChange = { if (it.length <= 6) staffPinInput = it },
                            placeholder = { Text("ঐচ্ছিক ৪-সংখ্যার পিন", fontSize = 11.5.sp, color = textSecondary) },
                            singleLine = true,
                            visualTransformation = androidx.compose.ui.text.input.PasswordVisualTransformation(),
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(10.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = brandBlue,
                                unfocusedBorderColor = cardBorder
                            )
                        )
                    }
                }

                Spacer(modifier = Modifier.height(20.dp))

                // Bottom Hint
                Text(
                    text = "🔒 এন্ড-টু-এন্ড সুরক্ষিত সংযোগ। মার্চেন্ট অনুমতি প্রত্যাহার করলে টার্মিনাল স্বয়ংক্রিয়ভাবে বন্ধ হয়ে যাবে।",
                    fontSize = 11.sp,
                    color = textSecondary,
                    textAlign = TextAlign.Center,
                    lineHeight = 16.sp
                )
            }
        }
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * 2. MAIN EMPLOYEE PORTAL SCREEN (With 4 Bottom Tabs & Watchdog)
 * ═══════════════════════════════════════════════════════════════════════════════
 */
@Composable
fun EmployeePortalScreen(viewModel: AppViewModel) {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val session by viewModel.employeeSession.collectAsState()
    val isLoading by viewModel.isEmployeeLoading.collectAsState()

    var selectedTab by remember { mutableIntStateOf(0) } // 0: New Sale, 1: Inventory, 2: Sales History, 3: Profile

    // Watchdog Revocation State
    var showRevokedAlert by remember { mutableStateOf(false) }
    var revokeMessage by remember { mutableStateOf("") }
    var showLogoutDialog by remember { mutableStateOf(false) }

    // Colors
    val screenBg = if (isDark) Color(0xFF0F172A) else Color(0xFFF8FAFC)
    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val cardBorder = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val brandBlue = Color(0xFF2563EB)
    val emeraldGreen = Color(0xFF10B981)

    // Revocation & Status Watchdog Loop
    // Revocation & Status Watchdog Loop (Strict 10s instant lockout)
    LaunchedEffect(Unit) {
        viewModel.fetchEmployeeSales()
        while (true) {
            viewModel.checkEmployeeStatus { revokedReason ->
                revokeMessage = revokedReason
                showRevokedAlert = true
            }
            delay(25000L) // poll every 25s
            delay(10000L) // poll every 10s for instant revocation
        }
    }

    if (session == null) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(screenBg),
            contentAlignment = Alignment.Center
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("সেশন পাওয়া যায়নি। অনুগ্রহ করে লগইন করুন।", color = textPrimary)
                Spacer(modifier = Modifier.height(12.dp))
                Button(onClick = { viewModel.navigateTo("EmployeeLogin") }) {
                    Text("লগইন স্ক্রিন")
                }
            }
        }
        return
    }

    Scaffold(
        modifier = Modifier.fillMaxSize(),
        containerColor = screenBg,
        topBar = {
            Surface(
                color = cardBg,
                shadowElevation = 2.dp,
                border = BorderStroke(1.dp, cardBorder)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .statusBarsPadding()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(8.dp)
                                    .clip(CircleShape)
                                    .background(emeraldGreen)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = session!!.merchantStoreName,
                                fontSize = 15.sp,
                                fontWeight = FontWeight.Bold,
                                color = textPrimary,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                        Text(
                            text = "স্টাফ: ${session!!.employeeName} (${session!!.employeeRole})",
                            fontSize = 12.sp,
                            color = textSecondary
                        )
                    }

                    Row(verticalAlignment = Alignment.CenterVertically) {
                        IconButton(
                            onClick = {
                                viewModel.fetchEmployeeSales()
                                Toast.makeText(context, "ডেটা রিফ্রেশ করা হয়েছে", Toast.LENGTH_SHORT).show()
                            }
                        ) {
                            Icon(Icons.Default.Refresh, contentDescription = "রিফ্রেশ", tint = textSecondary)
                        }

                        IconButton(
                            onClick = { showLogoutDialog = true }
                        ) {
                            Icon(Icons.Default.Logout, contentDescription = "লগআউট", tint = Color(0xFFEF4444))
                        }
                    }
                }
            }
        },
        bottomBar = {
            NavigationBar(
                containerColor = cardBg,
                tonalElevation = 8.dp,
                modifier = Modifier.border(1.dp, cardBorder)
            ) {
                NavigationBarItem(
                    selected = selectedTab == 0,
                    onClick = { selectedTab = 0 },
                    icon = { Icon(Icons.Default.ShoppingCart, contentDescription = "বিক্রয়") },
                    label = { Text("নতুন বিক্রয়", fontSize = 11.sp, fontWeight = FontWeight.SemiBold) },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = brandBlue,
                        selectedTextColor = brandBlue,
                        unselectedIconColor = textSecondary,
                        unselectedTextColor = textSecondary
                    )
                )
                NavigationBarItem(
                    selected = selectedTab == 1,
                    onClick = { selectedTab = 1 },
                    icon = { Icon(Icons.Default.Inventory2, contentDescription = "মজুদ পণ্য") },
                    label = { Text("মজুদ পণ্য", fontSize = 11.sp, fontWeight = FontWeight.SemiBold) },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = brandBlue,
                        selectedTextColor = brandBlue,
                        unselectedIconColor = textSecondary,
                        unselectedTextColor = textSecondary
                    )
                )
                NavigationBarItem(
                    selected = selectedTab == 2,
                    onClick = { selectedTab = 2 },
                    icon = { Icon(Icons.AutoMirrored.Filled.ReceiptLong, contentDescription = "বিক্রয় হিস্ট্রি") },
                    label = { Text("হিস্ট্রি", fontSize = 11.sp, fontWeight = FontWeight.SemiBold) },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = brandBlue,
                        selectedTextColor = brandBlue,
                        unselectedIconColor = textSecondary,
                        unselectedTextColor = textSecondary
                    )
                )
                NavigationBarItem(
                    selected = selectedTab == 3,
                    onClick = { selectedTab = 3 },
                    icon = { Icon(Icons.Default.Person, contentDescription = "প্রোফাইল") },
                    label = { Text("প্রোফাইল", fontSize = 11.sp, fontWeight = FontWeight.SemiBold) },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = brandBlue,
                        selectedTextColor = brandBlue,
                        unselectedIconColor = textSecondary,
                        unselectedTextColor = textSecondary
                    )
                )
            }
        }
    ) { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            when (selectedTab) {
                0 -> EmployeeNewSaleTab(viewModel = viewModel, session = session!!)
                1 -> EmployeeInventoryTab(viewModel = viewModel, session = session!!)
                2 -> EmployeeSalesHistoryTab(viewModel = viewModel, session = session!!)
                3 -> EmployeeProfileTab(viewModel = viewModel, session = session!!)
            }
        }
    }

    // ── Instant Security Lockout Alert ──
    if (showRevokedAlert) {
        AlertDialog(
            onDismissRequest = { /* Cannot dismiss without logging out */ },
            icon = {
                Icon(Icons.Default.Block, contentDescription = null, tint = Color(0xFFEF4444), modifier = Modifier.size(36.dp))
            },
            title = {
                Text("অ্যাক্সেস প্রত্যাহার করা হয়েছে", fontWeight = FontWeight.Bold, color = Color(0xFFEF4444))
            },
            text = {
                Text(
                    text = if (revokeMessage.isNotBlank()) revokeMessage else "মার্চেন্ট এই কর্মচারীর অ্যাক্সেস নিষ্ক্রিয় বা অপসারণ করেছেন। আপনি আর এই টার্মিনাল ব্যবহার করতে পারবেন না।",
                    fontSize = 14.sp,
                    color = textPrimary
                )
            },
            confirmButton = {
                Button(
                    onClick = {
                        showRevokedAlert = false
                        viewModel.logoutEmployee()
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444))
                ) {
                    Text("লগইন পেজে ফিরুন")
                }
            },
            properties = DialogProperties(dismissOnBackPress = false, dismissOnClickOutside = false)
        )
    }

    // ── Logout Confirmation Dialog ──
    if (showLogoutDialog) {
        AlertDialog(
            onDismissRequest = { showLogoutDialog = false },
            title = { Text("টার্মিনাল লগআউট", fontWeight = FontWeight.Bold) },
            text = { Text("আপনি কি নিশ্চিত যে স্টাফ সেশন বন্ধ করে লগআউট করতে চান?") },
            confirmButton = {
                Button(
                    onClick = {
                        showLogoutDialog = false
                        viewModel.logoutEmployee()
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444))
                ) {
                    Text("লগআউট")
                }
            },
            dismissButton = {
                TextButton(onClick = { showLogoutDialog = false }) {
                    Text("বাতিল")
                }
            }
        )
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TAB 0: EMPLOYEE NEW SALE (Product Selection, Cart, & Dual Payment Flow)
 * ═══════════════════════════════════════════════════════════════════════════════
 */
@Composable
private fun EmployeeNewSaleTab(viewModel: AppViewModel, session: EmployeeSession) {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val products by viewModel.products.collectAsState()
    val isLoading by viewModel.isEmployeeLoading.collectAsState()

    var searchQuery by remember { mutableStateOf("") }
    var selectedCategory by remember { mutableStateOf("All") }

    // Cart state: map of productId -> Pair(ProductItemEntity, quantity)
    var cartItems by remember { mutableStateOf(mapOf<String, Pair<ProductItemEntity, Double>>()) }
    var showCheckoutModal by remember { mutableStateOf(false) }

    val categories = remember(products) {
        listOf("All") + products.mapNotNull { it.category?.ifBlank { null } }.distinct()
    }

    val filteredProducts = remember(products, searchQuery, selectedCategory) {
        products.filter { p ->
            val matchesCategory = selectedCategory == "All" || p.category.equals(selectedCategory, ignoreCase = true)
            val matchesQuery = searchQuery.isBlank() ||
                    p.name.contains(searchQuery, ignoreCase = true) ||
                    (p.code?.contains(searchQuery, ignoreCase = true) == true) ||
                    (p.qrCode?.contains(searchQuery, ignoreCase = true) == true)
            matchesCategory && matchesQuery
        }
    }

    val cartTotalCount = cartItems.values.sumOf { it.second.toInt() }
    val cartSubtotal = cartItems.values.sumOf { it.first.salePrice * it.second }

    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val cardBorder = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val brandBlue = Color(0xFF2563EB)

    Box(modifier = Modifier.fillMaxSize()) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(bottom = if (cartTotalCount > 0) 80.dp else 0.dp)
        ) {
            // Search Bar & Filter Header
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(cardBg)
                    .padding(horizontal = 16.dp, vertical = 10.dp)
            ) {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { searchQuery = it },
                    placeholder = { Text("পণ্য খুঁজুন বা SKU / বারকোড...", fontSize = 13.sp) },
                    leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = textSecondary) },
                    trailingIcon = {
                        if (searchQuery.isNotEmpty()) {
                            IconButton(onClick = { searchQuery = "" }) {
                                Icon(Icons.Default.Clear, contentDescription = null, tint = textSecondary)
                            }
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    singleLine = true,
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = brandBlue,
                        unfocusedBorderColor = cardBorder
                    )
                )

                Spacer(modifier = Modifier.height(10.dp))

                // Category Chips
                LazyRow(
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items(categories) { cat ->
                        val isSelected = selectedCategory.equals(cat, ignoreCase = true)
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(20.dp))
                                .background(if (isSelected) brandBlue else cardBg)
                                .border(1.dp, if (isSelected) brandBlue else cardBorder, RoundedCornerShape(20.dp))
                                .clickable { selectedCategory = cat }
                                .padding(horizontal = 14.dp, vertical = 6.dp)
                        ) {
                            Text(
                                text = if (cat == "All") "সব পণ্য" else cat,
                                fontSize = 12.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                color = if (isSelected) Color.White else textPrimary
                            )
                        }
                    }
                }
            }

            // Products Grid
            if (filteredProducts.isEmpty()) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(32.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Outlined.SearchOff, contentDescription = null, tint = textSecondary, modifier = Modifier.size(48.dp))
                        Spacer(modifier = Modifier.height(12.dp))
                        Text("কোনো পণ্য খুঁজে পাওয়া যায়নি", color = textPrimary, fontWeight = FontWeight.Bold)
                        Text("দোকানের ইনভেনটরি থেকে পণ্য যোগ করা হয়েছে কি না নিশ্চিত করুন।", color = textSecondary, fontSize = 12.sp, textAlign = TextAlign.Center)
                    }
                }
            } else {
                LazyVerticalGrid(
                    columns = GridCells.Fixed(2),
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(filteredProducts, key = { it.id }) { product ->
                        val inCartQty = cartItems[product.id]?.second ?: 0.0

                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, if (inCartQty > 0) brandBlue else cardBorder)
                        ) {
                            Column(modifier = Modifier.padding(12.dp)) {
                                // Category Pill & Stock
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(brandBlue.copy(alpha = 0.1f))
                                            .padding(horizontal = 6.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = product.category ?: "General",
                                            fontSize = 10.sp,
                                            color = brandBlue,
                                            fontWeight = FontWeight.SemiBold
                                        )
                                    }

                                    Text(
                                        text = "${product.stockQuantity.toInt()} ${product.unit}",
                                        fontSize = 11.sp,
                                        color = if (product.stockQuantity <= product.minStockThreshold) Color(0xFFEF4444) else textSecondary,
                                        fontWeight = FontWeight.Medium
                                    )
                                }

                                Spacer(modifier = Modifier.height(8.dp))

                                Text(
                                    text = product.name,
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis
                                )

                                Spacer(modifier = Modifier.height(4.dp))

                                Text(
                                    text = "৳${product.salePrice}",
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = brandBlue
                                )

                                Spacer(modifier = Modifier.height(10.dp))

                                if (inCartQty > 0) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        IconButton(
                                            onClick = {
                                                val nextQty = inCartQty - 1
                                                cartItems = if (nextQty <= 0) {
                                                    cartItems - product.id
                                                } else {
                                                    cartItems + (product.id to Pair(product, nextQty))
                                                }
                                            },
                                            modifier = Modifier
                                                .size(32.dp)
                                                .clip(RoundedCornerShape(8.dp))
                                                .background(cardBorder)
                                        ) {
                                            Icon(Icons.Default.Remove, contentDescription = null, modifier = Modifier.size(16.dp))
                                        }

                                        Text(
                                            text = inCartQty.toInt().toString(),
                                            fontWeight = FontWeight.Bold,
                                            color = textPrimary
                                        )

                                        IconButton(
                                            onClick = {
                                                cartItems = cartItems + (product.id to Pair(product, inCartQty + 1))
                                            },
                                            modifier = Modifier
                                                .size(32.dp)
                                                .clip(RoundedCornerShape(8.dp))
                                                .background(brandBlue)
                                        ) {
                                            Icon(Icons.Default.Add, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                                        }
                                    }
                                } else {
                                    Button(
                                        onClick = {
                                            cartItems = cartItems + (product.id to Pair(product, 1.0))
                                        },
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .height(34.dp),
                                        shape = RoundedCornerShape(8.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = brandBlue.copy(alpha = 0.12f)),
                                        contentPadding = PaddingValues(0.dp)
                                    ) {
                                        Icon(Icons.Default.AddShoppingCart, contentDescription = null, tint = brandBlue, modifier = Modifier.size(15.dp))
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text("যোগ করুন", fontSize = 12.sp, color = brandBlue, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Floating Cart Summary Bar
        if (cartTotalCount > 0) {
            Surface(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .fillMaxWidth()
                    .padding(16.dp),
                shape = RoundedCornerShape(18.dp),
                color = Color(0xFF1E293B),
                shadowElevation = 8.dp
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "$cartTotalCount টি পণ্য নির্বাচন করা হয়েছে",
                            color = Color(0xFF94A3B8),
                            fontSize = 12.sp
                        )
                        Text(
                            text = "মোট: ৳$cartSubtotal",
                            color = Color.White,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }

                    Button(
                        onClick = { showCheckoutModal = true },
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981))
                    ) {
                        Text("চেকআউট করুন", fontWeight = FontWeight.Bold)
                        Spacer(modifier = Modifier.width(6.dp))
                        Icon(Icons.Default.ArrowForward, contentDescription = null, modifier = Modifier.size(16.dp))
                    }
                }
            }
        }
    }

    // Checkout & Dual Payment Dialog
    if (showCheckoutModal) {
        EmployeeCheckoutDialog(
            isDark = isDark,
            session = session,
            cartItems = cartItems,
            onUpdateCart = { updated -> cartItems = updated },
            onDismiss = { showCheckoutModal = false },
            onSaleCompleted = {
                cartItems = emptyMap()
                showCheckoutModal = false
            },
            viewModel = viewModel
        )
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * CHECKOUT & DUAL PAYMENT DIALOG (Cash vs MFS Instant Matching)
 * ═══════════════════════════════════════════════════════════════════════════════
 */
@Composable
private fun EmployeeCheckoutDialog(
    isDark: Boolean,
    session: EmployeeSession,
    cartItems: Map<String, Pair<ProductItemEntity, Double>>,
    onUpdateCart: (Map<String, Pair<ProductItemEntity, Double>>) -> Unit,
    onDismiss: () -> Unit,
    onSaleCompleted: () -> Unit,
    viewModel: AppViewModel
) {
    val context = LocalContext.current
    val isLoading by viewModel.isEmployeeLoading.collectAsState()

    var customerName by remember { mutableStateOf("Walk-in Customer") }
    var customerPhone by remember { mutableStateOf("") }
    var discountInput by remember { mutableStateOf("0") }

    // Payment method: "Cash", "bKash", "Nagad", "Rocket", "Upay"
    var paymentMethod by remember { mutableStateOf("Cash") }
    var trxIdInput by remember { mutableStateOf("") }
    var completedOrder by remember { mutableStateOf<EmployeeSaleOrder?>(null) }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    val subtotal = remember(cartItems) {
        cartItems.values.sumOf { it.first.salePrice * it.second }
    }
    val discount = discountInput.toDoubleOrNull() ?: 0.0
    val netTotal = (subtotal - discount).coerceAtLeast(0.0)

    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val cardBorder = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val brandBlue = Color(0xFF2563EB)

    val itemsList = cartItems.values.map { (prod, qty) ->
        EmployeeSaleItem(
            productId = prod.id,
            name = prod.name,
            quantity = qty,
            unitPrice = prod.salePrice,
            lineTotal = prod.salePrice * qty
        )
    }

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Surface(
            modifier = Modifier
                .fillMaxWidth(0.96f)
                .fillMaxHeight(0.92f),
            shape = RoundedCornerShape(20.dp),
            color = cardBg,
            border = BorderStroke(1.dp, cardBorder)
        ) {
            if (completedOrder != null) {
                // Success Invoice Modal
                EmployeeSaleSuccessView(
                    order = completedOrder!!,
                    onClose = {
                        onSaleCompleted()
                    }
                )
            } else {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(20.dp)
                ) {
                    // Header
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.ShoppingCartCheckout, contentDescription = null, tint = brandBlue)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = "বিক্রয় চেকআউট ও পেমেন্ট",
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                color = textPrimary
                            )
                        }
                        IconButton(onClick = onDismiss) {
                            Icon(Icons.Default.Close, contentDescription = "Close", tint = textSecondary)
                        }
                    }

                    HorizontalDivider(modifier = Modifier.padding(vertical = 10.dp), color = cardBorder)

                    LazyColumn(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxWidth(),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        // 1. Customer Details
                        item {
                            Text("১. ক্রেতার তথ্য", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                            Spacer(modifier = Modifier.height(6.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                OutlinedTextField(
                                    value = customerName,
                                    onValueChange = { customerName = it },
                                    label = { Text("ক্রেতার নাম") },
                                    modifier = Modifier.weight(1f),
                                    shape = RoundedCornerShape(10.dp),
                                    singleLine = true
                                )
                                OutlinedTextField(
                                    value = customerPhone,
                                    onValueChange = { customerPhone = it },
                                    label = { Text("ফোন নম্বর") },
                                    modifier = Modifier.weight(1f),
                                    shape = RoundedCornerShape(10.dp),
                                    singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone)
                                )
                            }
                        }

                        // 2. Itemized Breakdown
                        item {
                            Text("২. পণ্যের বিবরণ (${itemsList.size} টি আইটেম)", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                            Spacer(modifier = Modifier.height(6.dp))

                            itemsList.forEach { item ->
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(if (isDark) Color(0xFF0F172A) else Color(0xFFF1F5F9))
                                        .padding(10.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(item.name, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
                                        Text("৳${item.unitPrice} x ${item.quantity.toInt()}", fontSize = 12.sp, color = textSecondary)
                                    }
                                    Text("৳${item.lineTotal}", fontWeight = FontWeight.Bold, color = textPrimary)
                                }
                                Spacer(modifier = Modifier.height(6.dp))
                            }
                        }

                        // 3. Totals & Discount
                        item {
                            Card(
                                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF131D2D) else Color(0xFFEFF6FF)),
                                shape = RoundedCornerShape(12.dp),
                                border = BorderStroke(1.dp, brandBlue.copy(alpha = 0.3f))
                            ) {
                                Column(modifier = Modifier.padding(12.dp)) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Text("সাবটোটাল:", color = textSecondary, fontSize = 13.sp)
                                        Text("৳$subtotal", fontWeight = FontWeight.SemiBold, color = textPrimary)
                                    }
                                    Spacer(modifier = Modifier.height(6.dp))
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("ডিসকাউন্ট (৳):", color = textSecondary, fontSize = 13.sp)
                                        OutlinedTextField(
                                            value = discountInput,
                                            onValueChange = { discountInput = it },
                                            modifier = Modifier.width(100.dp),
                                            singleLine = true,
                                            shape = RoundedCornerShape(8.dp),
                                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number)
                                        )
                                    }
                                    HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp), color = cardBorder)
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Text("পরিশোধযোগ্য মোট:", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = textPrimary)
                                        Text("৳$netTotal", fontWeight = FontWeight.ExtraBold, fontSize = 18.sp, color = brandBlue)
                                    }
                                }
                            }
                        }

                        // 4. Dual Payment Flow Selector
                        item {
                            Text("৩. পেমেন্ট মেথড নির্বাচন করুন", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                            Spacer(modifier = Modifier.height(8.dp))

                            val methods = listOf("Cash", "bKash", "Nagad", "Rocket", "Upay")
                            LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                items(methods) { method ->
                                    val isSelected = paymentMethod == method
                                    val isCash = method == "Cash"
                                    val methodColor = when (method) {
                                        "bKash" -> Color(0xFFD12053)
                                        "Nagad" -> Color(0xFFF7931E)
                                        "Rocket" -> Color(0xFF8C3494)
                                        "Upay" -> Color(0xFF0079C1)
                                        else -> Color(0xFF10B981)
                                    }

                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(12.dp))
                                            .background(if (isSelected) methodColor.copy(alpha = 0.18f) else cardBg)
                                            .border(2.dp, if (isSelected) methodColor else cardBorder, RoundedCornerShape(12.dp))
                                            .clickable {
                                                paymentMethod = method
                                                errorMessage = null
                                            }
                                            .padding(horizontal = 14.dp, vertical = 10.dp)
                                    ) {
                                        Text(
                                            text = if (isCash) "💵 ক্যাশ" else method,
                                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                            color = if (isSelected) methodColor else textPrimary,
                                            fontSize = 13.sp
                                        )
                                    }
                                }
                            }
                        }

                        // Specific Details for Selected Method
                        item {
                            if (paymentMethod == "Cash") {
                                // Cash Mechanism Notice
                                Card(
                                    colors = CardDefaults.cardColors(containerColor = Color(0xFFF59E0B).copy(alpha = 0.12f)),
                                    border = BorderStroke(1.dp, Color(0xFFF59E0B).copy(alpha = 0.4f)),
                                    shape = RoundedCornerShape(12.dp)
                                ) {
                                    Row(
                                        modifier = Modifier.padding(12.dp),
                                        verticalAlignment = Alignment.Top
                                    ) {
                                        Icon(Icons.Default.Info, contentDescription = null, tint = Color(0xFFF59E0B), modifier = Modifier.size(20.dp))
                                        Spacer(modifier = Modifier.width(10.dp))
                                        Column {
                                            Text(
                                                text = "ক্যাশ পেমেন্ট মেকানিজম",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 13.sp,
                                                color = Color(0xFFF59E0B)
                                            )
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Text(
                                                text = "ক্যাশ সেল তৈরি করলে মার্চেন্ট অ্যাপের 'লাইভ স্টাফ মনিটর'-এ 'PENDING_CASH_CONFIRMATION' হিসেবে দৃশ্যমান হবে। মার্চেন্ট ক্যাশ টাকা বুঝে পাওয়ার পর ১-ট্যাপে বিক্রয় অনুমোদন করবেন।",
                                                fontSize = 12.sp,
                                                color = textPrimary,
                                                lineHeight = 16.sp
                                            )
                                        }
                                    }
                                }
                            } else {
                                // MFS Instant Matching Flow
                                val mfsNumber = session.gatewayMethods[paymentMethod] ?: ""

                                Card(
                                    colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF0F172A) else Color(0xFFF8FAFC)),
                                    border = BorderStroke(1.dp, brandBlue.copy(alpha = 0.3f)),
                                    shape = RoundedCornerShape(12.dp)
                                ) {
                                    Column(modifier = Modifier.padding(14.dp)) {
                                        Text(
                                            text = "দোকানের $paymentMethod অ্যাকাউন্ট নম্বর:",
                                            fontSize = 12.sp,
                                            color = textSecondary
                                        )
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(
                                                text = mfsNumber,
                                                fontSize = 18.sp,
                                                fontWeight = FontWeight.ExtraBold,
                                                color = brandBlue
                                            )
                                            OutlinedButton(
                                                onClick = {
                                                    val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                                                    clipboard.setPrimaryClip(ClipData.newPlainText("MFS Number", mfsNumber))
                                                    Toast.makeText(context, "$paymentMethod নম্বর কপি হয়েছে", Toast.LENGTH_SHORT).show()
                                                },
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp)
                                            ) {
                                                Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(14.dp))
                                                Spacer(modifier = Modifier.width(4.dp))
                                                Text("কপি", fontSize = 12.sp)
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(8.dp))
                                        Text(
                                            text = "গ্রাহককে ৳$netTotal এই নম্বরে পাঠাতে বলুন এবং প্রাপ্ত ট্রানজেকশন আইডি (TrxID) নিচে প্রদান করে যাচাই করুন:",
                                            fontSize = 12.sp,
                                            color = textSecondary,
                                            lineHeight = 16.sp
                                        )

                                        Spacer(modifier = Modifier.height(10.dp))

                                        OutlinedTextField(
                                            value = trxIdInput,
                                            onValueChange = { trxIdInput = it.uppercase() },
                                            placeholder = { Text("TrxID লিখুন (যেমন: BLK928X)") },
                                            label = { Text("ট্রানজেকশন আইডি (TrxID)") },
                                            modifier = Modifier.fillMaxWidth(),
                                            shape = RoundedCornerShape(10.dp),
                                            singleLine = true,
                                            leadingIcon = { Icon(Icons.Default.ConfirmationNumber, contentDescription = null, tint = brandBlue) }
                                        )
                                    }
                                }
                            }
                        }

                        // Error Banner inside Dialog
                        if (!errorMessage.isNullOrBlank()) {
                            item {
                                Text(
                                    text = errorMessage!!,
                                    color = Color(0xFFEF4444),
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Medium
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    // Action Button
                    Button(
                        onClick = {
                            if (paymentMethod != "Cash" && trxIdInput.isBlank()) {
                                errorMessage = "অনুগ্রহ করে গ্রাহকের $paymentMethod TrxID প্রদান করুন।"
                                return@Button
                            }
                            errorMessage = null

                            // Step 1: Create employee sale
                            viewModel.createEmployeeSale(
                                items = itemsList,
                                customerName = customerName.ifBlank { "Walk-in Customer" },
                                customerPhone = customerPhone,
                                subtotal = subtotal,
                                discount = discount,
                                netTotal = netTotal,
                                paymentType = paymentMethod,
                                onSuccess = { order ->
                                    if (paymentMethod == "Cash") {
                                        completedOrder = order
                                        Toast.makeText(context, "ক্যাশ বিক্রয় তৈরি হয়েছে! মার্চেন্ট অনুমোদনের অপেক্ষায় রয়েছে।", Toast.LENGTH_LONG).show()
                                    } else {
                                        // Step 2: Instant MFS verification
                                        viewModel.verifyEmployeeMfsPayment(
                                            saleId = order.id,
                                            trxId = trxIdInput,
                                            method = paymentMethod,
                                            onSuccess = { verifiedOrder ->
                                                completedOrder = verifiedOrder
                                                Toast.makeText(context, "MFS পেমেন্ট যাচাই সম্পন্ন ও বিক্রয় চূড়ান্ত হয়েছে!", Toast.LENGTH_SHORT).show()
                                            },
                                            onError = { err ->
                                                errorMessage = err
                                            }
                                        )
                                    }
                                },
                                onError = { err ->
                                    errorMessage = err
                                }
                            )
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(50.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (paymentMethod == "Cash") Color(0xFFF59E0B) else Color(0xFF10B981)
                        ),
                        enabled = !isLoading && itemsList.isNotEmpty()
                    ) {
                        if (isLoading) {
                            CircularProgressIndicator(color = Color.White, modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                        } else {
                            Icon(
                                imageVector = if (paymentMethod == "Cash") Icons.Default.SendToMobile else Icons.Default.CheckCircle,
                                contentDescription = null,
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = if (paymentMethod == "Cash") "ক্যাশ অর্ডার পাঠান (অপেক্ষমান)" else "$paymentMethod পেমেন্ট যাচাই ও কনফার্ম",
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp
                            )
                        }
                    }
                }
            }
        }
    }
}

/**
 * Sale Success / Completed View
 */
@Composable
private fun EmployeeSaleSuccessView(order: EmployeeSaleOrder, onClose: () -> Unit) {
    val isCashPending = order.status == "PENDING_CASH_CONFIRMATION"

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier
                .size(70.dp)
                .clip(CircleShape)
                .background(if (isCashPending) Color(0xFFF59E0B).copy(alpha = 0.15f) else Color(0xFF10B981).copy(alpha = 0.15f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = if (isCashPending) Icons.Default.AccessTime else Icons.Default.CheckCircle,
                contentDescription = null,
                tint = if (isCashPending) Color(0xFFF59E0B) else Color(0xFF10B981),
                modifier = Modifier.size(44.dp)
            )
        }

        Spacer(modifier = Modifier.height(16.dp))

        Text(
            text = if (isCashPending) "ক্যাশ অনুমোদন অপেক্ষমান" else "বিক্রয় সফলভাবে সম্পন্ন!",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold
        )

        Spacer(modifier = Modifier.height(6.dp))

        Text(
            text = "ইনভয়েস নম্বর: ${order.invoiceNo}",
            fontSize = 14.sp,
            color = Color(0xFF2563EB),
            fontWeight = FontWeight.SemiBold
        )

        Spacer(modifier = Modifier.height(14.dp))

        Text(
            text = if (isCashPending)
                "বিক্রয় রেকর্ডটি মার্চেন্টের লাইভ স্টাফ মনিটরে জমা হয়েছে। মার্চেন্ট ক্যাশ টাকা গ্রহণ নিশ্চিত করলেই এটি পেইড হিসেবে আপডেট হবে।"
            else
                "পেমেন্ট সফলভাবে প্রাপ্ত ও যাচাই হয়েছে। পণ্য ক্রেতাকে বুঝিয়ে দিন।",
            fontSize = 13.sp,
            textAlign = TextAlign.Center,
            color = Color.Gray,
            modifier = Modifier.padding(horizontal = 16.dp)
        )

        Spacer(modifier = Modifier.height(24.dp))

        Button(
            onClick = onClose,
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2563EB))
        ) {
            Text("নতুন বিক্রয় শুরু করুন", fontWeight = FontWeight.Bold)
        }
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TAB 1: INVENTORY TAB (Strictly No Delete, Quantity Field Locked/Disabled)
 * ═══════════════════════════════════════════════════════════════════════════════
 */
@Composable
private fun EmployeeInventoryTab(viewModel: AppViewModel, session: EmployeeSession) {
    val isDark by viewModel.isDarkMode.collectAsState()
    val products by viewModel.products.collectAsState()

    var searchQuery by remember { mutableStateOf("") }
    var selectedCategory by remember { mutableStateOf("All") }
    var editingProduct by remember { mutableStateOf<ProductItemEntity?>(null) }

    val categories = remember(products) {
        listOf("All") + products.mapNotNull { it.category?.ifBlank { null } }.distinct()
    }

    val filtered = remember(products, searchQuery, selectedCategory) {
        products.filter { p ->
            val matchCat = selectedCategory == "All" || p.category.equals(selectedCategory, ignoreCase = true)
            val matchQuery = searchQuery.isBlank() ||
                    p.name.contains(searchQuery, ignoreCase = true) ||
                    (p.code?.contains(searchQuery, ignoreCase = true) == true)
            matchCat && matchQuery
        }
    }

    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val cardBorder = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val brandBlue = Color(0xFF2563EB)

    Column(modifier = Modifier.fillMaxSize()) {
        // Search & Filter
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(cardBg)
                .padding(16.dp)
        ) {
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                placeholder = { Text("মজুদ পণ্য খুঁজুন...", fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = textSecondary) },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                singleLine = true
            )

            Spacer(modifier = Modifier.height(10.dp))

            LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                items(categories) { cat ->
                    val isSel = selectedCategory.equals(cat, ignoreCase = true)
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(20.dp))
                            .background(if (isSel) brandBlue else cardBg)
                            .border(1.dp, if (isSel) brandBlue else cardBorder, RoundedCornerShape(20.dp))
                            .clickable { selectedCategory = cat }
                            .padding(horizontal = 14.dp, vertical = 6.dp)
                    ) {
                        Text(
                            text = if (cat == "All") "সব ক্যাটাগরি" else cat,
                            fontSize = 12.sp,
                            fontWeight = if (isSel) FontWeight.Bold else FontWeight.Normal,
                            color = if (isSel) Color.White else textPrimary
                        )
                    }
                }
            }
        }

        // Inventory Count Stats Banner
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "মোট পণ্য: ${filtered.size} টি",
                fontWeight = FontWeight.Bold,
                fontSize = 14.sp,
                color = textPrimary
            )

            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.Lock, contentDescription = null, tint = textSecondary, modifier = Modifier.size(14.dp))
                Spacer(modifier = Modifier.width(4.dp))
                Text(
                    text = "স্টক এডিট ও ডিলিট লকড",
                    fontSize = 12.sp,
                    color = textSecondary
                )
            }
        }

        // Product List
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            items(filtered, key = { it.id }) { product ->
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = product.name,
                                fontWeight = FontWeight.Bold,
                                fontSize = 15.sp,
                                color = textPrimary
                            )
                            Spacer(modifier = Modifier.height(4.dp))
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    text = "বিক্রয় মূল্য: ৳${product.salePrice}",
                                    fontSize = 13.sp,
                                    color = brandBlue,
                                    fontWeight = FontWeight.SemiBold
                                )
                                Spacer(modifier = Modifier.width(12.dp))
                                Text(
                                    text = "মজুদ: ${product.stockQuantity.toInt()} ${product.unit}",
                                    fontSize = 13.sp,
                                    color = if (product.stockQuantity <= product.minStockThreshold) Color(0xFFEF4444) else textSecondary,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }

                        // Edit Button (Allows details edition, strictly restricted)
                        IconButton(
                            onClick = { editingProduct = product },
                            modifier = Modifier
                                .size(38.dp)
                                .clip(RoundedCornerShape(10.dp))
                                .background(brandBlue.copy(alpha = 0.1f))
                        ) {
                            Icon(Icons.Default.Edit, contentDescription = "Edit Details", tint = brandBlue, modifier = Modifier.size(18.dp))
                        }
                    }
                }
            }
        }
    }

    // Product Edition Dialog (Enforcing: NO DELETE & QUANTITY LOCKED)
    if (editingProduct != null) {
        EmployeeProductEditDialog(
            product = editingProduct!!,
            isDark = isDark,
            onDismiss = { editingProduct = null },
            onSave = { updatedProduct ->
                viewModel.updateProduct(updatedProduct)
                editingProduct = null
            }
        )
    }
}

/**
 * Product Edit Dialog with strict Employee Access Controls:
 * 1. Quantity field is DISABLED / LOCKED (Merchant only)
 * 2. Delete button is COMPLETELY REMOVED
 */
@Composable
private fun EmployeeProductEditDialog(
    product: ProductItemEntity,
    isDark: Boolean,
    onDismiss: () -> Unit,
    onSave: (ProductItemEntity) -> Unit
) {
    var name by remember { mutableStateOf(product.name) }
    var category by remember { mutableStateOf(product.category ?: "General") }
    var salePriceInput by remember { mutableStateOf(product.salePrice.toString()) }

    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val cardBorder = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val brandBlue = Color(0xFF2563EB)

    Dialog(onDismissRequest = onDismiss) {
        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            shape = RoundedCornerShape(18.dp),
            color = cardBg,
            border = BorderStroke(1.dp, cardBorder)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("পণ্য তথ্য সম্পাদনা", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = textPrimary)
                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.Close, contentDescription = null, tint = textSecondary)
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                OutlinedTextField(
                    value = name,
                    onValueChange = { name = it },
                    label = { Text("পণ্যের নাম") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )

                Spacer(modifier = Modifier.height(10.dp))

                OutlinedTextField(
                    value = category,
                    onValueChange = { category = it },
                    label = { Text("ক্যাটাগরি") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )

                Spacer(modifier = Modifier.height(10.dp))

                OutlinedTextField(
                    value = salePriceInput,
                    onValueChange = { salePriceInput = it },
                    label = { Text("বিক্রয় মূল্য (৳)") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number)
                )

                Spacer(modifier = Modifier.height(14.dp))

                // STRICT RESTRICTION: Stock Quantity is Locked
                Card(
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFEF4444).copy(alpha = 0.08f)),
                    border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.25f)),
                    shape = RoundedCornerShape(10.dp)
                ) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Lock, contentDescription = null, tint = Color(0xFFEF4444), modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = "মজুদ পরিমাণ: ${product.stockQuantity.toInt()} ${product.unit} (লকড)",
                                fontWeight = FontWeight.Bold,
                                color = Color(0xFFEF4444),
                                fontSize = 13.sp
                            )
                        }
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "🔒 স্টক পরিমাণ পরিবর্তন বা পণ্য ডিলিট করার অধিকার কেবল মার্চেন্টের। কর্মচারী হিসেবে আপনি স্টক পরিবর্তন করতে পারবেন না।",
                            fontSize = 11.sp,
                            color = textSecondary,
                            lineHeight = 15.sp
                        )
                    }
                }

                Spacer(modifier = Modifier.height(20.dp))

                // Action Buttons (NO DELETE BUTTON AT ALL)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End
                ) {
                    TextButton(onClick = onDismiss) {
                        Text("বাতিল", color = textSecondary)
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    Button(
                        onClick = {
                            val newPrice = salePriceInput.toDoubleOrNull() ?: product.salePrice
                            onSave(
                                product.copy(
                                    name = name.trim().ifBlank { product.name },
                                    category = category.trim().ifBlank { product.category },
                                    salePrice = newPrice
                                    // Notice: product.stockQuantity remains 100% UNCHANGED and locked!
                                )
                            )
                        },
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = brandBlue)
                    ) {
                        Text("পরিবর্তন সংরক্ষণ করুন", fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TAB 2: SALES & PAYMENT HISTORY (Itemized Receipts & Status Badges)
 * ═══════════════════════════════════════════════════════════════════════════════
 */
@Composable
private fun EmployeeSalesHistoryTab(viewModel: AppViewModel, session: EmployeeSession) {
    val isDark by viewModel.isDarkMode.collectAsState()
    val sales by viewModel.employeeSales.collectAsState()

    var statusFilter by remember { mutableStateOf("ALL") } // "ALL", "PAID", "PENDING_CASH"
    var selectedOrderForDetail by remember { mutableStateOf<EmployeeSaleOrder?>(null) }

    val filteredSales = remember(sales, statusFilter) {
        when (statusFilter) {
            "PAID" -> sales.filter { it.status.equals("PAID", ignoreCase = true) }
            "PENDING_CASH" -> sales.filter { it.status.contains("PENDING", ignoreCase = true) }
            else -> sales
        }
    }

    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val cardBorder = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val brandBlue = Color(0xFF2563EB)

    Column(modifier = Modifier.fillMaxSize()) {
        // Filter Tabs
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(cardBg)
                .padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            FilterTabPill(
                selected = statusFilter == "ALL",
                title = "সব বিক্রয় (${sales.size})",
                onClick = { statusFilter = "ALL" },
                modifier = Modifier.weight(1f)
            )
            FilterTabPill(
                selected = statusFilter == "PAID",
                title = "পেইড",
                onClick = { statusFilter = "PAID" },
                modifier = Modifier.weight(1f)
            )
            FilterTabPill(
                selected = statusFilter == "PENDING_CASH",
                title = "ক্যাশ অপেক্ষমান",
                onClick = { statusFilter = "PENDING_CASH" },
                modifier = Modifier.weight(1f)
            )
        }

        if (filteredSales.isEmpty()) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(32.dp),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Outlined.ReceiptLong, contentDescription = null, tint = textSecondary, modifier = Modifier.size(48.dp))
                    Spacer(modifier = Modifier.height(12.dp))
                    Text("কোনো বিক্রয় হিস্ট্রি পাওয়া যায়নি", color = textPrimary, fontWeight = FontWeight.Bold)
                    Text("আপনার সম্পন্ন বা অপেক্ষমান বিক্রয়সমূহ এখানে প্রদর্শিত হবে।", color = textSecondary, fontSize = 12.sp, textAlign = TextAlign.Center)
                }
            }
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                items(filteredSales, key = { it.id }) { order ->
                    val isPaid = order.status.equals("PAID", ignoreCase = true)
                    val isPendingCash = order.status.contains("PENDING", ignoreCase = true)

                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { selectedOrderForDetail = order },
                        shape = RoundedCornerShape(14.dp),
                        colors = CardDefaults.cardColors(containerColor = cardBg),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Column(modifier = Modifier.padding(14.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = order.invoiceNo,
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = brandBlue
                                )

                                // Status Badge
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(
                                            if (isPaid) Color(0xFF10B981).copy(alpha = 0.15f)
                                            else if (isPendingCash) Color(0xFFF59E0B).copy(alpha = 0.15f)
                                            else Color(0xFF3B82F6).copy(alpha = 0.15f)
                                        )
                                        .padding(horizontal = 8.dp, vertical = 4.dp)
                                    ) {
                                    Text(
                                        text = if (isPaid) "✓ পরিশোধিত" else if (isPendingCash) "⏳ ক্যাশ অপেক্ষমান" else "MFS ম্যাচিং",
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (isPaid) Color(0xFF10B981) else if (isPendingCash) Color(0xFFF59E0B) else Color(0xFF3B82F6)
                                    )
                                }
                            }

                            Spacer(modifier = Modifier.height(8.dp))

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(
                                        text = order.customerName,
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.Medium,
                                        color = textPrimary
                                    )
                                    Text(
                                        text = "${order.createdAt} • ${order.paymentType}",
                                        fontSize = 11.sp,
                                        color = textSecondary
                                    )
                                }

                                Text(
                                    text = "৳${order.netTotal}",
                                    fontSize = 17.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = textPrimary
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    // Receipt Detail Modal
    if (selectedOrderForDetail != null) {
        EmployeeReceiptDialog(
            order = selectedOrderForDetail!!,
            isDark = isDark,
            session = session,
            onDismiss = { selectedOrderForDetail = null }
        )
    }
}

/**
 * Detailed Receipt Dialog
 */
@Composable
private fun EmployeeReceiptDialog(
    order: EmployeeSaleOrder,
    isDark: Boolean,
    session: EmployeeSession,
    onDismiss: () -> Unit
) {
    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)

    Dialog(onDismissRequest = onDismiss) {
        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            shape = RoundedCornerShape(18.dp),
            color = cardBg
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("বিক্রয় রশিদ", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = textPrimary)
                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.Close, contentDescription = null, tint = textSecondary)
                    }
                }

                HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))

                Text(session.merchantStoreName, fontWeight = FontWeight.Bold, fontSize = 16.sp, color = textPrimary)
                Text("ইনভয়েস: ${order.invoiceNo}", fontSize = 12.sp, color = textSecondary)
                Text("তারিখ: ${order.createdAt}", fontSize = 12.sp, color = textSecondary)
                Text("স্টাফ: ${order.employeeName}", fontSize = 12.sp, color = textSecondary)
                Text("ক্রেতা: ${order.customerName} ${order.customerPhone}", fontSize = 12.sp, color = textSecondary)

                HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))

                order.items.forEach { item ->
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("${item.name} x ${item.quantity.toInt()}", fontSize = 12.sp, color = textPrimary)
                        Text("৳${item.lineTotal}", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                }

                HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))

                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("মোট প্রদেয়:", fontWeight = FontWeight.Bold, color = textPrimary)
                    Text("৳${order.netTotal}", fontWeight = FontWeight.ExtraBold, fontSize = 16.sp, color = Color(0xFF2563EB))
                }

                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("পদ্ধতি:", fontSize = 12.sp, color = textSecondary)
                    Text(order.paymentType, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                }

                if (!order.trxId.isNullOrBlank()) {
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("TrxID:", fontSize = 12.sp, color = textSecondary)
                        Text(order.trxId!!, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                Button(
                    onClick = onDismiss,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                ) {
                    Text("বন্ধ করুন")
                }
            }
        }
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TAB 3: PROFILE & SHIFT SUMMARY
 * ═══════════════════════════════════════════════════════════════════════════════
 */
@Composable
private fun EmployeeProfileTab(viewModel: AppViewModel, session: EmployeeSession) {
    val isDark by viewModel.isDarkMode.collectAsState()
    val sales by viewModel.employeeSales.collectAsState()

    val totalSalesCount = sales.size
    val totalVolume = sales.sumOf { it.netTotal }
    val cashPendingVolume = sales.filter { it.status.contains("PENDING", ignoreCase = true) }.sumOf { it.netTotal }
    val paidVolume = sales.filter { it.status.equals("PAID", ignoreCase = true) }.sumOf { it.netTotal }

    val cardBg = if (isDark) Color(0xFF1E293B) else Color.White
    val cardBorder = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        // Staff Profile Card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(18.dp),
            colors = CardDefaults.cardColors(containerColor = cardBg),
            border = BorderStroke(1.dp, cardBorder)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Box(
                    modifier = Modifier
                        .size(64.dp)
                        .clip(CircleShape)
                        .background(Color(0xFF2563EB)),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = session.employeeName.take(2).uppercase(),
                        color = Color.White,
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold
                    )
                }

                Spacer(modifier = Modifier.height(12.dp))

                Text(
                    text = session.employeeName,
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary
                )

                Text(
                    text = session.employeeRole,
                    fontSize = 13.sp,
                    color = Color(0xFF2563EB),
                    fontWeight = FontWeight.SemiBold
                )

                Spacer(modifier = Modifier.height(8.dp))

                Text(
                    text = "দোকান: ${session.merchantStoreName}",
                    fontSize = 12.sp,
                    color = textSecondary
                )
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        Text("আজকের শিফট সারাংশ", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = textPrimary)

        Spacer(modifier = Modifier.height(10.dp))

        // Metrics Grid
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            ShiftMetricCard(
                title = "মোট অর্ডার",
                value = "$totalSalesCount টি",
                color = Color(0xFF2563EB),
                modifier = Modifier.weight(1f)
            )
            ShiftMetricCard(
                title = "মোট বিক্রয়",
                value = "৳$totalVolume",
                color = Color(0xFF10B981),
                modifier = Modifier.weight(1f)
            )
        }

        Spacer(modifier = Modifier.height(10.dp))

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            ShiftMetricCard(
                title = "ক্যাশ অপেক্ষমান",
                value = "৳$cashPendingVolume",
                color = Color(0xFFF59E0B),
                modifier = Modifier.weight(1f)
            )
            ShiftMetricCard(
                title = "পরিশোধিত বিক্রয়",
                value = "৳$paidVolume",
                color = Color(0xFF8B5CF6),
                modifier = Modifier.weight(1f)
            )
        }

        Spacer(modifier = Modifier.height(20.dp))

        // Security & Permissions Info
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            colors = CardDefaults.cardColors(containerColor = cardBg),
            border = BorderStroke(1.dp, cardBorder)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text("টার্মিনাল পারমিশন", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = textPrimary)
                Spacer(modifier = Modifier.height(8.dp))
                PermissionRow("নতুন বিক্রয় তৈরি ও পেমেন্ট রিসিভ", true)
                PermissionRow("মজুদ পণ্য ভিউ ও সাধারণ তথ্য আপডেট", true)
                PermissionRow("পণ্য ডিলিট করার অনুমতি", false)
                PermissionRow("স্টক পরিমাণ পরিবর্তনের অনুমতি", false)
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Logout Button
        Button(
            onClick = { viewModel.logoutEmployee() },
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444))
        ) {
            Icon(Icons.Default.Logout, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text("টার্মিনাল লগআউট করুন", fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun ShiftMetricCard(title: String, value: String, color: Color, modifier: Modifier = Modifier) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = color.copy(alpha = 0.1f)),
        border = BorderStroke(1.dp, color.copy(alpha = 0.25f))
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Text(title, fontSize = 11.sp, color = color, fontWeight = FontWeight.SemiBold)
            Spacer(modifier = Modifier.height(4.dp))
            Text(value, fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = color)
        }
    }
}

@Composable
private fun PermissionRow(title: String, granted: Boolean) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(title, fontSize = 12.sp, color = Color.Gray)
        Text(
            text = if (granted) "✓ অনুমোদিত" else "✗ নিষিদ্ধ",
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            color = if (granted) Color(0xFF10B981) else Color(0xFFEF4444)
        )
    }
}

@Composable
private fun TabPill(selected: Boolean, title: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (selected) Color(0xFF2563EB) else Color.Transparent)
            .clickable { onClick() }
            .padding(vertical = 8.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = title,
            fontSize = 13.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = if (selected) Color.White else Color.Gray
        )
    }
}

@Composable
private fun FilterTabPill(selected: Boolean, title: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(8.dp))
            .background(if (selected) Color(0xFF2563EB) else Color.Transparent)
            .border(1.dp, if (selected) Color(0xFF2563EB) else Color.LightGray.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
            .clickable { onClick() }
            .padding(vertical = 6.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = title,
            fontSize = 11.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = if (selected) Color.White else Color.Gray,
            textAlign = TextAlign.Center
        )
    }
}

