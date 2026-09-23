package com.example.ui

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.layout.ContentScale
import coil.compose.AsyncImage
import coil.request.ImageRequest
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.data.local.*

// ═══════════════════════════════════════════════════════════════════════════
// PIXEL-PERFECT BOTTOM NAVIGATION BAR WITH GOLD THEME
// ═══════════════════════════════════════════════════════════════════════════
@Composable
fun BottomNavigationBar(viewModel: AppViewModel, activeTab: String, onTabSelected: (String) -> Unit, onFabClick: () -> Unit) {
    val isDarkMode by viewModel.isDarkMode.collectAsState()

    Surface(
        color = Color.Transparent,
        modifier = Modifier.fillMaxWidth()
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(if (isDarkMode) Color(0xFF0A0C14) else Color.White)
                .border(
                    BorderStroke(1.dp, if (isDarkMode) Color(0xFF2E2413) else Color(0xFFF0F0F2)),
                    RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp)
                )
                .navigationBarsPadding()
                .height(60.dp)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceAround
            ) {
                BottomNavItem(
                    label = "Dashboard",
                    icon = if (activeTab == "Dashboard") Icons.Default.Home else Icons.Outlined.Home,
                    selected = activeTab == "Dashboard",
                    isDarkMode = isDarkMode,
                    onClick = {
                        viewModel.navigateTo("Main")
                        onTabSelected("Dashboard")
                    }
                )
                BottomNavItem(
                    label = "Transactions",
                    icon = if (activeTab == "Transactions") Icons.Default.ReceiptLong else Icons.Outlined.ReceiptLong,
                    selected = activeTab == "Transactions",
                    isDarkMode = isDarkMode,
                    onClick = {
                        viewModel.navigateTo("Transactions")
                        onTabSelected("Transactions")
                    }
                )

                // Center Floating Action "Forms" (Hosted Forms)
                Box(
                    modifier = Modifier
                        .weight(1.2f)
                        .fillMaxHeight()
                        .clickable {
                            viewModel.navigateTo("PaymentForms")
                            onFabClick()
                        },
                    contentAlignment = Alignment.Center
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center,
                        modifier = Modifier.fillMaxHeight()
                    ) {
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .shadow(6.dp, CircleShape, ambientColor = Color(0xFFE2B258), spotColor = Color(0xFFE2B258))
                                .background(
                                    Brush.linearGradient(listOf(Color(0xFFF3C766), Color(0xFFC78B23))),
                                    CircleShape
                                )
                                .border(BorderStroke(1.5.dp, Color(0xFFFFEA9F)), CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Default.Add, null, tint = Color(0xFF161005), modifier = Modifier.size(22.dp))
                        }
                        Spacer(modifier = Modifier.height(2.dp))
                        Text(
                            text = "Forms",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color(0xFFE2B258),
                            maxLines = 1
                        )
                    }
                }

                BottomNavItem(
                    label = "AI Assistant",
                    icon = if (activeTab == "AiCopilot" || activeTab == "AI") Icons.Default.AutoAwesome else Icons.Outlined.AutoAwesome,
                    selected = activeTab == "AiCopilot" || activeTab == "AI",
                    isDarkMode = isDarkMode,
                    onClick = {
                        viewModel.navigateTo("AiCopilot")
                        onTabSelected("AiCopilot")
                    }
                )
                BottomNavItem(
                    label = "More",
                    icon = if (activeTab == "More" || activeTab == "Appeals" || activeTab == "Support" || activeTab == "PaymentMethods" || activeTab == "SMSLogs" || activeTab == "Notifications" || activeTab == "Details") Icons.Default.GridView else Icons.Outlined.GridView,
                    selected = activeTab == "More" || activeTab == "Appeals" || activeTab == "Support" || activeTab == "PaymentMethods" || activeTab == "SMSLogs" || activeTab == "Notifications" || activeTab == "Details",
                    isDarkMode = isDarkMode,
                    onClick = {
                        viewModel.navigateTo("Main")
                        onTabSelected("More")
                    }
                )
            }
        }
    }
}

@Composable
fun RowScope.BottomNavItem(
    label: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    selected: Boolean,
    isDarkMode: Boolean,
    onClick: () -> Unit
) {
    val activeColor = if (isDarkMode) Color(0xFFE2B258) else Color(0xFFF59E0B)
    val inactiveColor = if (isDarkMode) Color(0xFF737A8C) else Color(0xFF9CA3AF)
    val iconScale by animateFloatAsState(
        targetValue = if (selected) 1.12f else 1f,
        animationSpec = spring(dampingRatio = 0.5f, stiffness = 400f),
        label = "iconScale"
    )

    Column(
        modifier = Modifier
            .weight(1f)
            .fillMaxHeight()
            .clickable(
                onClick = onClick,
                indication = null,
                interactionSource = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
            ),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier.size(34.dp),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = if (selected) activeColor else inactiveColor,
                modifier = Modifier.size(22.dp).scale(iconScale)
            )
        }
        Spacer(modifier = Modifier.height(2.dp))
        Text(
            text = label,
            fontSize = 10.5.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = if (selected) activeColor else inactiveColor,
            letterSpacing = 0.2.sp
        )
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// LEDGERS DASHBOARD SCREEN — Unified Supplier Payables & Customer Dues
// ═══════════════════════════════════════════════════════════════════════════
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LedgersDashboardScreen(viewModel: AppViewModel) {
    val suppliers by viewModel.suppliers.collectAsState()
    val customers by viewModel.customers.collectAsState()
    val transactions by viewModel.ledgerTransactions.collectAsState()
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    val languageState by viewModel.language.collectAsState()

    val context = LocalContext.current
    val clipboardManager = LocalClipboardManager.current

    // Tab state: 0 = SUPPLIER PAYABLE, 1 = CUSTOMER DUES
    var selectedTab by remember { mutableStateOf(0) }

    // Date Filter State
    var selectedTimeFilter by remember { mutableStateOf("Today") }

    // Search Query State
    var searchQuery by remember { mutableStateOf("") }
    var sortOption by remember { mutableStateOf("Highest Due") }

    // AI Robot Dialog State
    var showAiRobotDialog by remember { mutableStateOf(false) }

    // Bottom Sheet & Modal States
    var selectedSupplierForPayment by remember { mutableStateOf<SupplierEntity?>(null) }
    var selectedSupplierForLedger by remember { mutableStateOf<SupplierEntity?>(null) }
    var selectedCustomerForLedger by remember { mutableStateOf<CustomerEntity?>(null) }

    // Modal Input States
    var inputPayAmount by remember { mutableStateOf("") }
    var inputPayNote by remember { mutableStateOf("") }

    // Dynamic Light / Dark Color Palette matching pixel-perfect screenshots
    val isDark = isDarkMode
    SideEffect { isDarkModeGlobal = isDark }

    val bgDark = if (isDark) Color(0xFF050505) else Color(0xFFFAFAFC)
    val surfaceDark = if (isDark) Color(0xFF101116) else Color(0xFFFFFFFF)
    val cardDark = if (isDark) Color(0xFF12131A) else Color(0xFFFFFFFF)
    val cardBorder = if (isDark) Color(0xFF4D3A18) else Color(0xFFE2E8F0)

    val goldPrimary = if (isDark) Color(0xFFE2B258) else Color(0xFFFFC800)
    val goldLight = if (isDark) Color(0xFFF3C766) else Color(0xFFFEF08A)
    val goldDarkBg = if (isDark) Color(0xFF2B200B) else Color(0xFFFFC800)
    val goldBadgeBg = if (isDark) Color(0xFF2B200B) else Color(0xFFFEF3C7)
    val goldBorder = if (isDark) Color(0xFF4D3A18) else Color(0xFFE2E8F0)
    val goldText = if (isDark) Color(0xFFE2B258) else Color(0xFFD97706)

    val greenSuccess = if (isDark) Color(0xFF10B981) else Color(0xFF16A34A)
    val greenBg = if (isDark) Color(0xFF0D2B1E) else Color(0xFFDCFCE7)

    val redAlert = if (isDark) Color(0xFFEF4444) else Color(0xFFDC2626)
    val redBg = if (isDark) Color(0xFF331212) else Color(0xFFFEE2E2)

    val textWhite = if (isDark) Color(0xFFFFFFFF) else Color(0xFF0F172A)
    val textMuted = if (isDark) Color(0xFF9CA3AF) else Color(0xFF64748B)

    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = false)
    var dueOnlyFilter by remember { mutableStateOf(false) }

    // Summary calculations from real database entities
    val totalSupplierPayable = suppliers.sumOf { if (it.currentBalance < 0) Math.abs(it.currentBalance) else 0.0 }
    val supplierCount = suppliers.count { it.currentBalance < 0 }
    val totalSupplierPaid = transactions.filter { it.supplierId != null && it.type == "payment" }.sumOf { it.amount }
    val supplierPaymentCount = transactions.count { it.supplierId != null && it.type == "payment" }

    val totalCustomerDues = customers.sumOf { if (it.currentBalance > 0) it.currentBalance else 0.0 }
    val customerCount = customers.count { it.currentBalance > 0 }
    val totalCustomerCollected = transactions.filter { it.customerId != null && it.type == "payment" }.sumOf { it.amount }

    val filteredSuppliers = suppliers.filter {
        val matchesSearch = if (searchQuery.isBlank()) true else it.name.contains(searchQuery, ignoreCase = true) || it.phone.contains(searchQuery) || it.code.contains(searchQuery, ignoreCase = true)
        val matchesDue = if (dueOnlyFilter) it.currentBalance < 0 else true
        matchesSearch && matchesDue
    }

    val filteredCustomers = customers.filter {
        val matchesSearch = if (searchQuery.isBlank()) true else it.name.contains(searchQuery, ignoreCase = true) || it.phone.contains(searchQuery) || it.code.contains(searchQuery, ignoreCase = true)
        val matchesDue = if (dueOnlyFilter) it.currentBalance > 0 else true
        matchesSearch && matchesDue
    }

    if (selectedSupplierForLedger != null) {
        SupplierDetailsView(supplier = selectedSupplierForLedger!!, viewModel = viewModel, onBack = { selectedSupplierForLedger = null })
    } else if (selectedCustomerForLedger != null) {
        CustomerDetailsView(customer = selectedCustomerForLedger!!, viewModel = viewModel, onBack = { selectedCustomerForLedger = null })
    } else {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(bgDark)
    ) {
        Scaffold(
            containerColor = bgDark,
            topBar = {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(bgDark)
                        .statusBarsPadding()
                        .padding(horizontal = 16.dp, vertical = 10.dp)
                ) {
                    GradientTopBar(
                        title = "Ledgers",
                        subtitle = if (isDark) "Supplier & Customer Accounts" else null,
                        onBack = { viewModel.goBack() },
                        actions = {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                // Circle Icon Button
                                Box(
                                    modifier = Modifier
                                        .size(36.dp)
                                        .clip(CircleShape)
                                        .background(surfaceDark)
                                        .border(BorderStroke(1.dp, goldBorder), CircleShape)
                                        .clickable { showAiRobotDialog = true },
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Outlined.Circle,
                                        contentDescription = "Options",
                                        tint = if (isDark) goldPrimary else Color(0xFF0F172A),
                                        modifier = Modifier.size(20.dp)
                                    )
                                }

                                // Export Button (White with Black in Light Mode)
                                Surface(
                                    modifier = Modifier.clickable {
                                        viewModel.logFirebaseStatus("Exported Ledgers Statement")
                                    },
                                    shape = RoundedCornerShape(10.dp),
                                    color = if (isDark) surfaceDark else Color.White,
                                    border = BorderStroke(1.dp, if (isDark) goldBorder else Color(0xFFE2E8F0))
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                                    ) {
                                        Icon(
                                            Icons.Default.FileDownload,
                                            null,
                                            tint = if (isDark) goldPrimary else Color(0xFF000000),
                                            modifier = Modifier.size(16.dp)
                                        )
                                        Text(
                                            "Export",
                                            fontSize = 13.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = if (isDark) goldPrimary else Color(0xFF000000)
                                        )
                                    }
                                }
                            }
                        }
                    )

                    Spacer(modifier = Modifier.height(14.dp))

                    // Segmented Control Tabs (SUPPLIER PAYABLE vs CUSTOMER DUES)
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        color = if (isDark) Color(0xFF0A0C12) else Color(0xFFFFFFFF),
                        border = BorderStroke(1.dp, if (isDark) goldBorder.copy(alpha = 0.6f) else Color(0xFFE2E8F0)),
                        shadowElevation = 1.dp
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(4.dp),
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            // Tab 0: SUPPLIER PAYABLE
                            Surface(
                                modifier = Modifier
                                    .weight(1f)
                                    .height(42.dp)
                                    .clickable { selectedTab = 0 },
                                shape = RoundedCornerShape(9.dp),
                                color = if (selectedTab == 0) (if (isDark) Color(0xFF1C180E) else Color(0xFFFFFDF0)) else Color.Transparent,
                                border = if (selectedTab == 0) BorderStroke(1.5.dp, goldPrimary) else null,
                                shadowElevation = if (selectedTab == 0) 1.5.dp else 0.dp
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxSize(),
                                    horizontalArrangement = Arrangement.Center,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(
                                        Icons.Default.AccountBalanceWallet,
                                        null,
                                        tint = if (selectedTab == 0) (if (isDark) goldPrimary else Color(0xFFD97706)) else textMuted,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text(
                                        text = "All Suppliers (${suppliers.size})",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (selectedTab == 0) (if (isDark) goldPrimary else Color(0xFFD97706)) else textMuted
                                    )
                                }
                            }

                            // Tab 1: CUSTOMER DUES
                            Surface(
                                modifier = Modifier
                                    .weight(1f)
                                    .height(42.dp)
                                    .clickable { selectedTab = 1 },
                                shape = RoundedCornerShape(9.dp),
                                color = if (selectedTab == 1) (if (isDark) Color(0xFF1C180E) else Color(0xFFFFFDF0)) else Color.Transparent,
                                border = if (selectedTab == 1) BorderStroke(1.5.dp, goldPrimary) else null,
                                shadowElevation = if (selectedTab == 1) 1.5.dp else 0.dp
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxSize(),
                                    horizontalArrangement = Arrangement.Center,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(
                                        Icons.Default.Person,
                                        null,
                                        tint = if (selectedTab == 1) (if (isDark) goldPrimary else Color(0xFFD97706)) else textMuted,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text(
                                        text = "All Customers (${customers.size})",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (selectedTab == 1) (if (isDark) goldPrimary else Color(0xFFD97706)) else textMuted
                                    )
                                }
                            }
                        }
                    }
                }
            },
            bottomBar = {
                BottomNavigationBar(
                    viewModel = viewModel,
                    activeTab = "More",
                    onTabSelected = { tab -> viewModel.setTab(tab) },
                    onFabClick = { viewModel.navigateTo("PaymentForms") }
                )
            }
        ) { padding ->
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
                contentPadding = PaddingValues(top = 8.dp, bottom = 24.dp)
            ) {

                // ── 1. DATE FILTERS ROW ───────────────────────────────────────────
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (selectedTab == 1) {
                            Text("TIME RANGE", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = textMuted)
                        }

                        LazyRow(
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            val timeFilters = listOf("Today", "7 Days", "This Month", "Custom")
                            items(timeFilters) { filter ->
                                val isSelected = selectedTimeFilter == filter
                                Surface(
                                    modifier = Modifier.clickable { selectedTimeFilter = filter },
                                    shape = RoundedCornerShape(12.dp),
                                    color = if (isSelected) goldPrimary else surfaceDark,
                                    border = BorderStroke(1.dp, if (isSelected) goldPrimary else goldBorder)
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                                    ) {
                                        Icon(
                                            imageVector = if (filter == "Custom") Icons.Default.Tune else Icons.Default.CalendarToday,
                                            contentDescription = null,
                                            tint = if (isSelected) Color.Black else textMuted,
                                            modifier = Modifier.size(14.dp)
                                        )
                                        Text(
                                            text = filter,
                                            fontSize = 12.sp,
                                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                            color = if (isSelected) Color.Black else textMuted
                                        )
                                    }
                                }
                            }
                        }

                        if (selectedTab == 1) {
                            Row(
                                modifier = Modifier.fillMaxWidth().padding(top = 2.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Icon(Icons.Default.CalendarToday, null, tint = goldPrimary, modifier = Modifier.size(14.dp))
                                    Text("Active Range: ", fontSize = 12.sp, color = textMuted)
                                    Text("24 Jul 2026 - 24 Jul 2026", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = textWhite)
                                }
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    modifier = Modifier.clickable { }
                                ) {
                                    Text("Change ", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = goldPrimary)
                                    Icon(Icons.Default.ArrowForward, null, tint = goldPrimary, modifier = Modifier.size(12.dp))
                                }
                            }
                        }
                    }
                }

                // ── 2. SUMMARY CARDS GRID ─────────────────────────────────────────
                item {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        if (selectedTab == 0) {
                            // SUPPLIER SUMMARY: Total Payable & Paid
                            Card(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(14.dp),
                                colors = CardDefaults.cardColors(containerColor = cardDark),
                                border = BorderStroke(1.dp, goldBorder)
                            ) {
                                Column(modifier = Modifier.padding(14.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                        Box(
                                            modifier = Modifier
                                                .size(38.dp)
                                                .clip(CircleShape)
                                                .background(goldBadgeBg)
                                                .border(BorderStroke(1.dp, if (isDark) goldBorder else Color(0xFFFDE68A)), CircleShape),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(Icons.Default.AccountBalanceWallet, null, tint = goldText, modifier = Modifier.size(18.dp))
                                        }
                                        Column {
                                            Text("Total Payable", fontSize = 11.sp, color = textMuted, fontWeight = FontWeight.Medium)
                                            Text("৳ ${String.format("%,.2f", totalSupplierPayable)}", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = textWhite)
                                        }
                                    }
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text("$supplierCount Supplier", fontSize = 11.sp, color = goldText, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 48.dp))
                                }
                            }

                            Card(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(14.dp),
                                colors = CardDefaults.cardColors(containerColor = cardDark),
                                border = BorderStroke(1.dp, if (isDark) Color(0xFF1B3D2B) else Color(0xFFE2E8F0))
                            ) {
                                Column(modifier = Modifier.padding(14.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                        Box(
                                            modifier = Modifier
                                                .size(38.dp)
                                                .clip(CircleShape)
                                                .background(greenBg)
                                                .border(BorderStroke(1.dp, if (isDark) greenSuccess.copy(0.5f) else Color(0xFF86EFAC)), CircleShape),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(Icons.Default.AccountBalanceWallet, null, tint = greenSuccess, modifier = Modifier.size(18.dp))
                                        }
                                        Column {
                                            Text("Paid", fontSize = 11.sp, color = textMuted, fontWeight = FontWeight.Medium)
                                            Text("৳ ${String.format("%,.2f", totalSupplierPaid)}", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = textWhite)
                                        }
                                    }
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text("$supplierPaymentCount ${if (supplierPaymentCount == 1) "Payment" else "Payments"}", fontSize = 11.sp, color = textMuted, fontWeight = FontWeight.Medium, modifier = Modifier.padding(start = 48.dp))
                                }
                            }
                        } else {
                            // CUSTOMER SUMMARY: New Dues & Collected
                            Card(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(14.dp),
                                colors = CardDefaults.cardColors(containerColor = cardDark),
                                border = BorderStroke(1.dp, goldBorder)
                            ) {
                                Column(modifier = Modifier.padding(14.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                        Box(
                                            modifier = Modifier
                                                .size(38.dp)
                                                .clip(CircleShape)
                                                .background(goldBadgeBg)
                                                .border(BorderStroke(1.dp, if (isDark) goldBorder else Color(0xFFFDE68A)), CircleShape),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(Icons.Default.Description, null, tint = goldText, modifier = Modifier.size(18.dp))
                                        }
                                        Column {
                                            Text("NEW DUES", fontSize = 11.sp, color = textMuted, fontWeight = FontWeight.Bold)
                                            Text("৳ ${String.format("%,.2f", totalCustomerDues)}", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = textWhite)
                                        }
                                    }
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text("$customerCount Customers", fontSize = 11.sp, color = textMuted, modifier = Modifier.padding(start = 48.dp))
                                }
                            }

                            Card(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(14.dp),
                                colors = CardDefaults.cardColors(containerColor = cardDark),
                                border = BorderStroke(1.dp, if (isDark) Color(0xFF1B3D2B) else Color(0xFFE2E8F0))
                            ) {
                                Column(modifier = Modifier.padding(14.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                        Box(
                                            modifier = Modifier
                                                .size(38.dp)
                                                .clip(CircleShape)
                                                .background(greenBg)
                                                .border(BorderStroke(1.dp, if (isDark) greenSuccess.copy(0.5f) else Color(0xFF86EFAC)), CircleShape),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(Icons.Default.AccountBalanceWallet, null, tint = greenSuccess, modifier = Modifier.size(18.dp))
                                        }
                                        Column {
                                            Text("COLLECTED", fontSize = 11.sp, color = textMuted, fontWeight = FontWeight.Bold)
                                            Text("৳ ${String.format("%,.2f", totalCustomerCollected)}", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = textWhite)
                                        }
                                    }
                                    Spacer(modifier = Modifier.height(4.dp))
                                    val paymentCount = transactions.count { it.customerId != null && it.type == "payment" }
                                    Text("$paymentCount Payment${if (paymentCount != 1) "s" else ""}", fontSize = 11.sp, color = textMuted, modifier = Modifier.padding(start = 48.dp))
                                }
                            }
                        }
                    }
                }

                // ── DUE FILTER TOGGLE ─────────────────────────────────────────────
                item {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // "All" chip
                        val allLabel = if (selectedTab == 0) "All Suppliers (${suppliers.size})" else "All Customers (${customers.size})"
                        Surface(
                            modifier = Modifier
                                .height(36.dp)
                                .clickable { dueOnlyFilter = false },
                            shape = RoundedCornerShape(50),
                            color = if (!dueOnlyFilter) {
                                if (isDark) Color(0xFF1C180E) else Color(0xFFFFFDE7)
                            } else {
                                if (isDark) Color(0xFF1E1E1E) else Color(0xFFF1F5F9)
                            },
                            border = BorderStroke(
                                1.5.dp,
                                if (!dueOnlyFilter) goldPrimary else goldBorder
                            )
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                if (!dueOnlyFilter) {
                                    Icon(
                                        Icons.Default.Check,
                                        null,
                                        tint = if (isDark) goldPrimary else Color(0xFFD97706),
                                        modifier = Modifier.size(13.dp)
                                    )
                                }
                                Text(
                                    text = allLabel,
                                    fontSize = 11.5.sp,
                                    fontWeight = if (!dueOnlyFilter) FontWeight.Bold else FontWeight.Normal,
                                    color = if (!dueOnlyFilter) {
                                        if (isDark) goldPrimary else Color(0xFFD97706)
                                    } else {
                                        if (isDark) textMuted else Color(0xFF64748B)
                                    }
                                )
                            }
                        }

                        // "Dues Only" chip
                        val duesLabel = if (selectedTab == 0) "Payable Only ($supplierCount)" else "Due Only ($customerCount)"
                        Surface(
                            modifier = Modifier
                                .height(36.dp)
                                .clickable { dueOnlyFilter = true },
                            shape = RoundedCornerShape(50),
                            color = if (dueOnlyFilter) {
                                if (isDark) Color(0xFF3B1E1E) else Color(0xFFFEE2E2)
                            } else {
                                if (isDark) Color(0xFF1E1E1E) else Color(0xFFF1F5F9)
                            },
                            border = BorderStroke(
                                1.5.dp,
                                if (dueOnlyFilter) {
                                    if (isDark) Color(0xFFF87171) else Color(0xFFDC2626)
                                } else goldBorder
                            )
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                if (dueOnlyFilter) {
                                    Icon(
                                        Icons.Default.Check,
                                        null,
                                        tint = if (isDark) Color(0xFFF87171) else Color(0xFFDC2626),
                                        modifier = Modifier.size(13.dp)
                                    )
                                }
                                Text(
                                    text = duesLabel,
                                    fontSize = 11.5.sp,
                                    fontWeight = if (dueOnlyFilter) FontWeight.Bold else FontWeight.Normal,
                                    color = if (dueOnlyFilter) {
                                        if (isDark) Color(0xFFF87171) else Color(0xFFDC2626)
                                    } else {
                                        if (isDark) textMuted else Color(0xFF64748B)
                                    }
                                )
                            }
                        }
                    }
                }

                // ── 3. SEARCH & SORT ROW ──────────────────────────────────────────
                item {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        OutlinedTextField(
                            value = searchQuery,
                            onValueChange = { searchQuery = it },
                            placeholder = {
                                Text(
                                    text = if (selectedTab == 0) "Search supplier..." else "Search by customer...",
                                    fontSize = 12.sp,
                                    color = textMuted
                                )
                            },
                            leadingIcon = { Icon(Icons.Default.Search, null, tint = textMuted, modifier = Modifier.size(18.dp)) },
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(12.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedContainerColor = surfaceDark,
                                unfocusedContainerColor = surfaceDark,
                                focusedBorderColor = if (isDark) goldPrimary else Color(0xFFCBD5E1),
                                unfocusedBorderColor = goldBorder,
                                focusedTextColor = textWhite,
                                unfocusedTextColor = textWhite
                            ),
                            singleLine = true
                        )

                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = surfaceDark,
                            border = BorderStroke(1.dp, goldBorder),
                            modifier = Modifier.clickable { }
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 14.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Text("Sort:", fontSize = 11.5.sp, color = textMuted)
                                Text(
                                    text = if (selectedTab == 0) "Highest Due" else "Due High → Low",
                                    fontSize = 11.5.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (isDark) goldPrimary else Color(0xFF0F172A)
                                )
                                Icon(
                                    Icons.Default.KeyboardArrowDown,
                                    null,
                                    tint = if (isDark) goldPrimary else Color(0xFF0F172A),
                                    modifier = Modifier.size(14.dp)
                                )
                            }
                        }
                    }
                }

                // ── 4. LIST SECTION ──────────────────────────────────────────────
                if (selectedTab == 0) {
                    // SUPPLIER PAYABLES LIST
                    val listToShow = suppliers.filter {
                        if (searchQuery.isBlank()) true else it.name.contains(searchQuery, ignoreCase = true) || it.phone.contains(searchQuery) || it.code.contains(searchQuery, ignoreCase = true)
                    }

                    itemsIndexed(listToShow) { index, supplier ->
                        val dueAmount = Math.abs(supplier.currentBalance)
                        val isPaid = supplier.currentBalance >= 0
                        val isPartial = supplier.currentBalance != supplier.openingBalance && supplier.currentBalance < 0
                        val sCode = supplier.code.ifBlank { "S-${supplier.phone.takeLast(4).ifBlank { supplier.id.take(4).uppercase() }}" }

                        val supplierTxs = transactions.filter { it.supplierId == supplier.id }
                        val supplierTotalBill = Math.abs(supplier.openingBalance) + supplierTxs.filter { it.type == "credit" }.sumOf { it.amount }
                        val supplierPaid = supplierTxs.filter { it.type == "payment" }.sumOf { it.amount }

                        Card(
                            modifier = Modifier.fillMaxWidth().clickable { selectedSupplierForLedger = supplier },
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardDark),
                            border = BorderStroke(1.dp, goldBorder)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.Top
                                ) {
                                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                                        Box(
                                            modifier = Modifier
                                                .size(44.dp)
                                                .clip(CircleShape)
                                                .background(goldBadgeBg)
                                                .border(BorderStroke(1.dp, if (isDark) goldBorder else Color(0xFFFDE68A)), CircleShape),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(
                                                imageVector = Icons.Default.Business,
                                                contentDescription = null,
                                                tint = goldText,
                                                modifier = Modifier.size(20.dp)
                                            )
                                        }

                                        Column {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                                Text("${index + 1}. ${supplier.name}", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textWhite)
                                                Surface(
                                                    shape = RoundedCornerShape(4.dp),
                                                    color = goldBadgeBg,
                                                    border = BorderStroke(1.dp, if (isDark) goldBorder else Color(0xFFFDE68A))
                                                ) {
                                                    Text(sCode, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = goldText, modifier = Modifier.padding(horizontal = 5.dp, vertical = 1.dp))
                                                }
                                            }
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                                Icon(Icons.Default.Phone, null, tint = textMuted, modifier = Modifier.size(12.dp))
                                                Text(supplier.phone, fontSize = 12.sp, color = textMuted)
                                            }
                                        }
                                    }

                                    Column(horizontalAlignment = Alignment.End) {
                                        Surface(
                                            shape = RoundedCornerShape(6.dp),
                                            color = if (isPaid) greenBg else if (isPartial) goldBadgeBg else redBg,
                                            border = BorderStroke(1.dp, if (isPaid) greenSuccess.copy(alpha = 0.5f) else if (isPartial) goldBorder else redAlert.copy(alpha = 0.5f))
                                        ) {
                                            Text(
                                                text = if (isPaid) "Paid In Full" else if (isPartial) "Partially Paid" else "Full Due",
                                                fontSize = 10.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (isPaid) greenSuccess else if (isPartial) goldText else redAlert,
                                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                            )
                                        }
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(
                                            text = "৳ ${String.format("%,.2f", dueAmount)}",
                                            fontSize = 16.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = if (isPaid) greenSuccess else redAlert
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.height(12.dp))

                                // Breakdown 3 Grid Boxes (Total Bill, Paid, Due)
                                Surface(
                                    shape = RoundedCornerShape(10.dp),
                                    color = if (isDark) bgDark else Color(0xFFF8FAFC),
                                    border = BorderStroke(1.dp, if (isDark) goldBorder.copy(alpha = 0.5f) else Color(0xFFE2E8F0))
                                ) {
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(10.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                                Icon(Icons.Default.Description, null, tint = goldText, modifier = Modifier.size(12.dp))
                                                Text("Total Bill", fontSize = 11.sp, color = textMuted)
                                            }
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Text(
                                                text = "৳ ${String.format("%,.2f", supplierTotalBill)}",
                                                fontSize = 12.5.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = textWhite
                                            )
                                        }

                                        Box(modifier = Modifier.width(1.dp).height(26.dp).background(if (isDark) goldBorder else Color(0xFFE2E8F0)))

                                        Column {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                                Icon(Icons.Default.CheckCircle, null, tint = greenSuccess, modifier = Modifier.size(12.dp))
                                                Text("Paid", fontSize = 11.sp, color = textMuted)
                                            }
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Text(
                                                text = "৳ ${String.format("%,.2f", supplierPaid)}",
                                                fontSize = 12.5.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = textWhite
                                            )
                                        }

                                        Box(modifier = Modifier.width(1.dp).height(26.dp).background(if (isDark) goldBorder else Color(0xFFE2E8F0)))

                                        Column {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                                Icon(
                                                    imageVector = if (isPaid) Icons.Default.CheckCircle else Icons.Default.Warning,
                                                    contentDescription = null,
                                                    tint = if (isPaid) greenSuccess else redAlert,
                                                    modifier = Modifier.size(12.dp)
                                                )
                                                Text("Due", fontSize = 11.sp, color = textMuted)
                                            }
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Text(
                                                text = "৳ ${String.format("%,.2f", dueAmount)}",
                                                fontSize = 12.5.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (isPaid) greenSuccess else redAlert
                                            )
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(12.dp))

                                // Action Buttons
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                ) {
                                    Surface(
                                        modifier = Modifier
                                            .weight(1f)
                                            .height(40.dp)
                                            .clickable { selectedSupplierForLedger = supplier },
                                        shape = RoundedCornerShape(10.dp),
                                        color = surfaceDark,
                                        border = BorderStroke(1.dp, goldBorder)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxSize(),
                                            horizontalArrangement = Arrangement.Center,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Icon(
                                                Icons.Default.Description,
                                                null,
                                                tint = if (isDark) goldPrimary else Color(0xFF0F172A),
                                                modifier = Modifier.size(14.dp)
                                            )
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Text(
                                                "View Ledger",
                                                fontSize = 12.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (isDark) goldPrimary else Color(0xFF0F172A)
                                            )
                                        }
                                    }

                                    Surface(
                                        modifier = Modifier
                                            .weight(1f)
                                            .height(40.dp)
                                            .clickable {
                                                selectedSupplierForPayment = supplier
                                                inputPayAmount = dueAmount.toInt().toString()
                                            },
                                        shape = RoundedCornerShape(10.dp),
                                        color = if (isDark) goldDarkBg else Color(0xFFFFC800),
                                        border = if (isDark) BorderStroke(1.dp, goldPrimary) else null
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxSize(),
                                            horizontalArrangement = Arrangement.Center,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Icon(
                                                Icons.Default.AccountBalanceWallet,
                                                null,
                                                tint = if (isDark) goldPrimary else Color(0xFF000000),
                                                modifier = Modifier.size(14.dp)
                                            )
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Text(
                                                "Pay Due",
                                                fontSize = 12.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (isDark) goldPrimary else Color(0xFF000000)
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // CUSTOMER DUES LIST
                    val customerListToShow = customers.filter {
                        if (searchQuery.isBlank()) true else it.name.contains(searchQuery, ignoreCase = true) || it.phone.contains(searchQuery) || it.code.contains(searchQuery, ignoreCase = true)
                    }

                    itemsIndexed(customerListToShow) { index, customer ->
                        val dueAmt = customer.currentBalance
                        val cCode = customer.code.ifBlank { "C-${customer.phone.takeLast(4).ifBlank { customer.id.take(4).uppercase() }}" }

                        Card(
                            modifier = Modifier.fillMaxWidth().clickable { selectedCustomerForLedger = customer },
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardDark),
                            border = BorderStroke(1.dp, goldBorder)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.Top
                                ) {
                                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                        Box(
                                            modifier = Modifier
                                                .size(44.dp)
                                                .clip(CircleShape)
                                                .background(goldBadgeBg)
                                                .border(BorderStroke(1.dp, if (isDark) goldBorder else Color(0xFFFDE68A)), CircleShape),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(Icons.Default.Person, null, tint = goldText, modifier = Modifier.size(22.dp))
                                        }

                                        Column {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                                Text("${index + 1}. ${customer.name}", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textWhite)
                                                Surface(
                                                    shape = RoundedCornerShape(4.dp),
                                                    color = goldBadgeBg,
                                                    border = BorderStroke(1.dp, if (isDark) goldBorder else Color(0xFFFDE68A))
                                                ) {
                                                    Text(cCode, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = goldText, modifier = Modifier.padding(horizontal = 5.dp, vertical = 1.dp))
                                                }
                                            }
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                                Icon(Icons.Default.Phone, null, tint = textMuted, modifier = Modifier.size(12.dp))
                                                Text(customer.phone, fontSize = 12.sp, color = textMuted)
                                            }
                                        }
                                    }

                                    Column(horizontalAlignment = Alignment.End) {
                                        Surface(
                                            shape = RoundedCornerShape(6.dp),
                                            color = goldBadgeBg,
                                            border = BorderStroke(1.dp, if (isDark) goldBorder else Color(0xFFFDE68A))
                                        ) {
                                            Text("Due Remaining", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = goldText, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                                        }
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text("৳ ${String.format("%,.2f", dueAmt)}", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = if (dueAmt > 0) redAlert else greenSuccess)
                                    }
                                }

                                Spacer(modifier = Modifier.height(10.dp))

                                // Items & Bill Details
                                val customerTxs = transactions.filter { it.customerId == customer.id }
                                val customerTotalBill = customer.openingBalance + customerTxs.filter { it.type == "credit" }.sumOf { it.amount }
                                val customerPaid = customerTxs.filter { it.type == "payment" }.sumOf { it.amount }
                                val lastTxDate = customerTxs.maxByOrNull { it.date }?.date ?: customer.createdAt
                                val dateStr = java.text.SimpleDateFormat("dd MMM yyyy", java.util.Locale.getDefault()).format(java.util.Date(lastTxDate))
                                val lastCreditNote = customerTxs.filter { it.type == "credit" }.maxByOrNull { it.date }?.note ?: "Regular Order"

                                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                        Icon(Icons.Default.Description, null, tint = goldText, modifier = Modifier.size(12.dp))
                                        Text("Items: ", fontSize = 11.5.sp, color = textMuted)
                                        Text(
                                            text = lastCreditNote,
                                            fontSize = 11.5.sp,
                                            fontWeight = FontWeight.Medium,
                                            color = textWhite
                                        )
                                    }

                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                        Icon(Icons.Default.Receipt, null, tint = goldText, modifier = Modifier.size(12.dp))
                                        Text(
                                            text = "Total: ৳ ${String.format("%,.2f", customerTotalBill)}",
                                            fontSize = 11.5.sp,
                                            color = textMuted
                                        )
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Icon(
                                            imageVector = if (dueAmt <= 0) Icons.Default.CheckCircle else Icons.Default.Warning,
                                            contentDescription = null,
                                            tint = if (dueAmt <= 0) greenSuccess else redAlert,
                                            modifier = Modifier.size(12.dp)
                                        )
                                        Text(
                                            text = "Paid: ৳ ${String.format("%,.2f", customerPaid)}",
                                            fontSize = 11.5.sp,
                                            color = textMuted
                                        )
                                    }

                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                        Icon(Icons.Default.CalendarToday, null, tint = goldText, modifier = Modifier.size(12.dp))
                                        Text("Date: ", fontSize = 11.5.sp, color = textMuted)
                                        Text(dateStr, fontSize = 11.5.sp, color = textWhite)
                                    }
                                }

                                Spacer(modifier = Modifier.height(12.dp))

                                // Call & WhatsApp / Reminder / View Actions
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Surface(
                                        modifier = Modifier
                                            .weight(1f)
                                            .height(38.dp)
                                            .clickable { selectedCustomerForLedger = customer },
                                        shape = RoundedCornerShape(8.dp),
                                        color = surfaceDark,
                                        border = BorderStroke(1.dp, goldBorder)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxSize(),
                                            horizontalArrangement = Arrangement.Center,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Icon(Icons.Default.Visibility, null, tint = if (isDark) goldPrimary else Color(0xFF0F172A), modifier = Modifier.size(14.dp))
                                            Spacer(modifier = Modifier.width(4.dp))
                                            Text("View", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (isDark) goldPrimary else Color(0xFF0F172A))
                                        }
                                    }

                                    Surface(
                                        modifier = Modifier
                                            .weight(1f)
                                            .height(38.dp)
                                            .clickable {
                                                try {
                                                    val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:${customer.phone}"))
                                                    context.startActivity(intent)
                                                } catch (e: Exception) {
                                                    viewModel.logFirebaseStatus("Call trigger failed: ${e.message}")
                                                }
                                            },
                                        shape = RoundedCornerShape(8.dp),
                                        color = surfaceDark,
                                        border = BorderStroke(1.dp, goldBorder)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxSize(),
                                            horizontalArrangement = Arrangement.Center,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Icon(Icons.Default.Phone, null, tint = if (isDark) goldPrimary else Color(0xFF0F172A), modifier = Modifier.size(14.dp))
                                            Spacer(modifier = Modifier.width(4.dp))
                                            Text("Call", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (isDark) goldPrimary else Color(0xFF0F172A))
                                        }
                                    }

                                    Surface(
                                        modifier = Modifier
                                            .weight(1.2f)
                                            .height(38.dp)
                                            .clickable {
                                                viewModel.logFirebaseStatus("Sent WhatsApp Reminder to ${customer.name}")
                                            },
                                        shape = RoundedCornerShape(8.dp),
                                        color = if (isDark) goldDarkBg else Color(0xFFFFC800),
                                        border = if (isDark) BorderStroke(1.dp, goldPrimary) else null
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxSize(),
                                            horizontalArrangement = Arrangement.Center,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Icon(Icons.Default.Send, null, tint = if (isDark) goldPrimary else Color(0xFF000000), modifier = Modifier.size(14.dp))
                                            Spacer(modifier = Modifier.width(4.dp))
                                            Text("Reminder", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (isDark) goldPrimary else Color(0xFF000000))
                                        }
                                    }

                                    // ── 1-Tap AI Due Call ──
                                    Surface(
                                        modifier = Modifier
                                            .weight(1.1f)
                                            .height(38.dp)
                                            .clickable {
                                                viewModel.triggerDueReminderCall(
                                                    customerPhone = customer.phone,
                                                    customerName = customer.name,
                                                    dueAmount = dueAmt
                                                ) { success: Boolean, msg: String ->
                                                    android.widget.Toast.makeText(context, msg, android.widget.Toast.LENGTH_LONG).show()
                                                }
                                            },
                                        shape = RoundedCornerShape(8.dp),
                                        color = Color(0xFFEF4444).copy(alpha = 0.15f),
                                        border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.6f))
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxSize(),
                                            horizontalArrangement = Arrangement.Center,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Icon(Icons.Default.PhoneCallback, null, tint = Color(0xFFEF4444), modifier = Modifier.size(13.dp))
                                            Spacer(modifier = Modifier.width(3.dp))
                                            Text("AI কল", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFFEF4444))
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

    // ── AI ROBOT COPILOT DIALOG ───────────────────────────────────────────────
    if (showAiRobotDialog) {
        EnterpriseGestureModal(
            onDismissRequest = { showAiRobotDialog = false },
            title = "AI Ledger Copilot Insights",
            subtitle = "Swipe down or drag handle to dismiss",
            icon = Icons.Outlined.SmartToy
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    AsyncImage(
                        model = ImageRequest.Builder(LocalContext.current)
                            .data(com.example.R.drawable.img_ai_robot)
                            .crossfade(true)
                            .build(),
                        contentDescription = "AI Robot Avatar",
                        contentScale = ContentScale.Crop,
                        modifier = Modifier
                            .size(52.dp)
                            .clip(CircleShape)
                            .border(BorderStroke(2.dp, goldPrimary), CircleShape)
                    )
                    Text(
                        "Automated real-time ledger intelligence recommendations",
                        fontSize = 12.sp,
                        color = textMuted
                    )
                }

                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = surfaceDark,
                    border = BorderStroke(1.dp, goldBorder)
                ) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("💡 Customer Collection Target:", fontWeight = FontWeight.Bold, color = goldLight, fontSize = 12.sp)
                        if (customerCount > 0) {
                            Text("$customerCount customer${if (customerCount != 1) "s" else ""} have overdue payments totaling ৳${String.format("%,.2f", totalCustomerDues)}. Sending reminders can speed up cash recovery.", fontSize = 11.5.sp, color = textWhite)
                        } else {
                            Text("No outstanding customer dues at the moment. All payments are up to date.", fontSize = 11.5.sp, color = textWhite)
                        }
                    }
                }
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = surfaceDark,
                    border = BorderStroke(1.dp, goldBorder)
                ) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("💼 Supplier Payable Summary:", fontWeight = FontWeight.Bold, color = goldLight, fontSize = 12.sp)
                        if (supplierCount > 0) {
                            val topSupplier = suppliers.filter { it.currentBalance < 0 }.maxByOrNull { Math.abs(it.currentBalance) }
                            val topStr = if (topSupplier != null) " ${topSupplier.name} is due ৳${String.format("%,.2f", Math.abs(topSupplier.currentBalance))}." else ""
                            Text("Total supplier payables stand at ৳${String.format("%,.2f", totalSupplierPayable)} across $supplierCount supplier${if (supplierCount != 1) "s" else ""}.$topStr", fontSize = 11.5.sp, color = textWhite)
                        } else {
                            Text("No outstanding supplier payables at the moment.", fontSize = 11.5.sp, color = textWhite)
                        }
                    }
                }

                Spacer(modifier = Modifier.height(8.dp))

                Button(
                    onClick = {
                        showAiRobotDialog = false
                        viewModel.logFirebaseStatus("AI Copilot Ledger Reminders Action Triggered")
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = goldDarkBg),
                    border = BorderStroke(1.dp, goldPrimary),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().height(48.dp)
                ) {
                    Text("⚡ Auto-Send WhatsApp Reminders", color = goldPrimary, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                }
            }
        }
    }

    // ── MODAL BOTTOM SHEETS ───────────────────────────────────────────────────

    // 1. PAY SUPPLIER DUE SHEET
    if (selectedSupplierForPayment != null) {
        val sup = selectedSupplierForPayment!!
        ModalBottomSheet(
            onDismissRequest = { selectedSupplierForPayment = null },
            sheetState = sheetState,
            containerColor = cardDark,
            dragHandle = { BottomSheetDefaults.DragHandle() }
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 20.dp, vertical = 10.dp)
                    .navigationBarsPadding(),
                verticalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                Text("محাজনকে বাকি পরিশোধ (${sup.name})", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = goldPrimary)
                OutlinedTextField(
                    value = inputPayAmount,
                    onValueChange = { inputPayAmount = it },
                    label = { Text("পরিশোধের পরিমাণ (৳)", color = textMuted) },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = surfaceDark,
                        unfocusedContainerColor = surfaceDark,
                        focusedTextColor = textWhite,
                        unfocusedTextColor = textWhite,
                        focusedBorderColor = goldPrimary,
                        unfocusedBorderColor = goldBorder
                    )
                )
                OutlinedTextField(
                    value = inputPayNote,
                    onValueChange = { inputPayNote = it },
                    label = { Text("বিবরণ / নোট", color = textMuted) },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = surfaceDark,
                        unfocusedContainerColor = surfaceDark,
                        focusedTextColor = textWhite,
                        unfocusedTextColor = textWhite,
                        focusedBorderColor = goldPrimary,
                        unfocusedBorderColor = goldBorder
                    )
                )

                Button(
                    onClick = {
                        val amt = inputPayAmount.toDoubleOrNull() ?: 0.0
                        if (amt > 0) {
                            viewModel.addLedgerTransaction(null, sup.id, "payment", amt, inputPayNote.ifEmpty { "Cash Payment" })
                            selectedSupplierForPayment = null
                            inputPayAmount = ""; inputPayNote = ""
                        }
                    },
                    modifier = Modifier.fillMaxWidth().height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = goldDarkBg),
                    border = BorderStroke(1.dp, goldPrimary)
                ) {
                    Text("টাকা পরিশোধ করুন", fontWeight = FontWeight.Bold, color = goldPrimary)
                }
                Spacer(modifier = Modifier.height(16.dp))
            }
        }
    }

    // 2. VIEW SUPPLIER LEDGER STATEMENT SHEET
    if (selectedSupplierForLedger != null) {
        SupplierDetailsView(
            supplier = selectedSupplierForLedger!!,
            viewModel = viewModel,
            onBack = { selectedSupplierForLedger = null }
        )
    }
    }
}
