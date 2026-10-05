package com.example.ui

import android.widget.Toast
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.ArrowDownward
import androidx.compose.material.icons.filled.ArrowUpward
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.PictureAsPdf
import androidx.compose.material.icons.filled.Print
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class, ExperimentalFoundationApi::class)
@Composable
fun ReportsMainScreen(viewModel: AppViewModel) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val isBangla = viewModel.language.collectAsState().value == "Bangla"
    val isDark by viewModel.isDarkMode.collectAsState()
    SideEffect { isDarkModeGlobal = isDark }

    // Dynamic Dual-Theme Colors (Matching New Sale Dark Mode Canvas & Crisp Executive White Mode)
    val screenBg = if (isDark) Color(0xFF090806) else Color(0xFFF8FAFC)
    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val primaryText = if (isDark) Color.White else Color(0xFF0F172A)
    val secondaryText = if (isDark) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val goldAccent = Color(0xFFF59E0B)
    val greenSuccess = Color(0xFF10B981)
    val redDanger = Color(0xFFEF4444)
    val blueAccent = Color(0xFF3B82F6)
    val softBg = if (isDark) Color(0xFF1C1710) else Color(0xFFF1F5F9)

    // Data States from ViewModel
    val posSales by viewModel.posSales.collectAsState()
    val orders by viewModel.orders.collectAsState()
    val payments by viewModel.payments.collectAsState()
    val expenses by viewModel.expenses.collectAsState()
    val customers by viewModel.customers.collectAsState()
    val suppliers by viewModel.suppliers.collectAsState()
    val ledgerTxs by viewModel.ledgerTransactions.collectAsState()
    val products by viewModel.products.collectAsState()

    // Filter Controls
    var selectedTimeFrame by remember { mutableStateOf("30 Days") } // Today, 7 Days, 30 Days, Year, All Time
    var selectedCategory by remember { mutableStateOf("Overview") } // Overview, Sales, Expenses, Receivables, Inventory
    var showExportModal by remember { mutableStateOf(false) }
    var isRefreshing by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        if (posSales.isEmpty() && ledgerTxs.isEmpty() && products.isEmpty()) {
            isRefreshing = true
            try {
                viewModel.pullAllMerchantDataFromRemoteInternal(viewModel.activeProfile.value.id)
            } catch (_: Exception) {
            } finally {
                isRefreshing = false
            }
        }
    }

    fun formatMoney(amount: Double): String {
        return "৳" + String.format(Locale.US, "%,.2f", amount)
    }

    // Calculation logic based on selected time filter
    val filterDays = when (selectedTimeFrame) {
        "Today" -> 1
        "7 Days" -> 7
        "30 Days" -> 30
        "Year" -> 365
        else -> 9999
    }

    val minTimestamp = remember(filterDays) {
        if (filterDays >= 9999) 0L
        else System.currentTimeMillis() - (filterDays * 24 * 60 * 60 * 1000L)
    }

    val filteredSales = remember(posSales, minTimestamp) {
        posSales.filter { it.timestamp >= minTimestamp }
    }

    val filteredOrders = remember(orders, minTimestamp) {
        orders.filter { it.status == "PAID" && (it.createdAt ?: 0L) >= minTimestamp }
    }

    val filteredPayments = remember(payments, minTimestamp) {
        payments.filter { it.timestamp >= minTimestamp }
    }

    val filteredExpenses = remember(expenses, minTimestamp) {
        expenses.filter { it.date >= minTimestamp }
    }

    val filteredLedgers = remember(ledgerTxs, minTimestamp) {
        ledgerTxs.filter { it.date >= minTimestamp }
    }

    // Key Aggregations
    val totalPosRevenue = remember(filteredSales) { filteredSales.sumOf { it.netTotal } }
    val totalOrderRevenue = remember(filteredOrders) { filteredOrders.sumOf { it.amount } }
    val totalPaymentRevenue = remember(filteredPayments) { filteredPayments.sumOf { it.amount } }
    val totalLedgerCredit = remember(filteredLedgers) {
        filteredLedgers.filter { it.type.lowercase() == "credit" }.sumOf { it.amount }
    }
    val totalGrossRevenue = totalPosRevenue + totalOrderRevenue + totalPaymentRevenue + totalLedgerCredit
    val totalExpenseAmount = remember(filteredExpenses) { filteredExpenses.sumOf { it.amount } }

    val estimatedCogs = remember(filteredSales) {
        filteredSales.sumOf { sale -> sale.netTotal * 0.60 }
    }

    val netProfit = totalGrossRevenue - totalExpenseAmount - estimatedCogs
    val profitMargin = if (totalGrossRevenue > 0) (netProfit / totalGrossRevenue) * 100 else 0.0

    val totalCustomerDues = remember(customers, ledgerTxs) {
        val sumFromEntities = customers.sumOf { kotlin.math.abs(it.currentBalance) }
        if (sumFromEntities > 0.0) sumFromEntities
        else customers.sumOf { c ->
            ledgerTxs.filter { it.customerId == c.id }
                .sumOf { if (it.type == "credit") it.amount else -it.amount }
                .coerceAtLeast(0.0)
        }
    }

    val navBarBottom = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(screenBg)
    ) {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = navBarBottom + 90.dp)
        ) {
            // 1. STICKY HEADER WITH SAFEAREA INSETS
            stickyHeader {
                Surface(
                    color = screenBg,
                    shadowElevation = 2.dp,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .statusBarsPadding()
                            .padding(horizontal = 18.dp, vertical = 14.dp)
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
                                        .size(42.dp)
                                        .clip(RoundedCornerShape(12.dp))
                                        .background(cardBg)
                                        .border(1.dp, cardBorder, RoundedCornerShape(12.dp))
                                        .clickable { viewModel.goBack() },
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                                        contentDescription = "Back",
                                        tint = primaryText,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }

                                Spacer(modifier = Modifier.width(10.dp))

                                Column(modifier = Modifier.weight(1f, fill = false)) {
                                    Text(
                                        text = if (isBangla) "ব্যবসা এনালিটিক্স ও রিপোর্ট" else "Executive Financial Report",
                                        fontSize = 18.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = primaryText,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                    Text(
                                        text = if (isBangla) "রিয়েলটাইম হিসাব, লাভ-ক্ষতি ও লেজার" else "Real-time LEDGER, POS & P&L Insights",
                                        fontSize = 11.sp,
                                        color = secondaryText,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                }
                            }

                            Spacer(modifier = Modifier.width(8.dp))

                            Row(
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                // Refresh Action
                                Box(
                                    modifier = Modifier
                                        .size(40.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(cardBg)
                                        .border(1.dp, cardBorder, RoundedCornerShape(10.dp))
                                        .clickable {
                                            isRefreshing = true
                                            scope.launch {
                                                try {
                                                    viewModel.pullAllMerchantDataFromRemoteInternal(viewModel.activeProfile.value.id)
                                                    viewModel.forceSyncSupabase()
                                                    Toast.makeText(context, if (isBangla) "ডাটাবেজ থেকে ডাটা সিঙ্ক ও হিসাব সম্পন্ন হয়েছে" else "Data synced and calculated from database", Toast.LENGTH_SHORT).show()
                                                } catch (e: Exception) {
                                                    Toast.makeText(context, "Sync error: ${e.message}", Toast.LENGTH_SHORT).show()
                                                } finally {
                                                    isRefreshing = false
                                                }
                                            }
                                        },
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Refresh,
                                        contentDescription = "Refresh Data",
                                        tint = goldAccent,
                                        modifier = Modifier.size(18.dp)
                                    )
                                }

                                // Export Button
                                Button(
                                    onClick = { showExportModal = true },
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = goldAccent,
                                        contentColor = Color.Black
                                    ),
                                    shape = RoundedCornerShape(10.dp),
                                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp),
                                    modifier = Modifier.height(40.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Download,
                                        contentDescription = null,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text(
                                        text = if (isBangla) "এক্সপোর্ট" else "Export",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 13.sp
                                    )
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(12.dp))

                        // Horizontally Scrollable Time Filters
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .horizontalScroll(rememberScrollState()),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            listOf("Today", "7 Days", "30 Days", "Year", "All Time").forEach { tf ->
                                val isSelected = selectedTimeFrame == tf
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(20.dp))
                                        .background(if (isSelected) goldAccent else cardBg)
                                        .border(
                                            1.dp,
                                            if (isSelected) goldAccent else cardBorder,
                                            RoundedCornerShape(20.dp)
                                        )
                                        .clickable { selectedTimeFrame = tf }
                                        .padding(horizontal = 14.dp, vertical = 6.dp)
                                ) {
                                    Text(
                                        text = tf,
                                        fontSize = 12.sp,
                                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                        color = if (isSelected) Color.Black else primaryText
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // 2. CATEGORY TABS (Overview, Sales, Expenses, Receivables, Inventory)
            item {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(start = 18.dp, end = 18.dp, top = 12.dp)
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    val tabs = listOf(
                        "Overview" to Icons.Outlined.Analytics,
                        "Sales" to Icons.Outlined.PointOfSale,
                        "Expenses" to Icons.Outlined.AccountBalanceWallet,
                        "Receivables" to Icons.Outlined.People,
                        "Inventory" to Icons.Outlined.Inventory2
                    )

                    tabs.forEach { (catName, icon) ->
                        val isSelected = selectedCategory == catName
                        Card(
                            colors = CardDefaults.cardColors(
                                containerColor = if (isSelected) cardBg else Color.Transparent
                            ),
                            border = BorderStroke(
                                1.dp,
                                if (isSelected) goldAccent else cardBorder
                            ),
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.clickable { selectedCategory = catName }
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = icon,
                                    contentDescription = null,
                                    tint = if (isSelected) goldAccent else secondaryText,
                                    modifier = Modifier.size(16.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text(
                                    text = catName,
                                    fontSize = 12.sp,
                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                    color = if (isSelected) primaryText else secondaryText
                                )
                            }
                        }
                    }
                }
            }

            // 3. EXECUTIVE KPI METRICS GRID
            item {
                Column(modifier = Modifier.padding(start = 18.dp, end = 18.dp, top = 16.dp)) {
                    Text(
                        text = "EXECUTIVE SUMMARY",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = goldAccent,
                        letterSpacing = 1.2.sp
                    )

                    Spacer(modifier = Modifier.height(10.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        // Gross Revenue Card
                        ReportKpiCard(
                            modifier = Modifier.weight(1f),
                            title = "Gross Revenue",
                            value = formatMoney(totalGrossRevenue),
                            subtitle = "${filteredSales.size} sales | ${filteredLedgers.size} txs",
                            icon = Icons.Outlined.AttachMoney,
                            accentColor = greenSuccess,
                            cardBg = cardBg,
                            cardBorder = cardBorder,
                            primaryText = primaryText,
                            secondaryText = secondaryText
                        )

                        // Net Profit Card
                        ReportKpiCard(
                            modifier = Modifier.weight(1f),
                            title = "Net Profit (Est.)",
                            value = formatMoney(netProfit),
                            subtitle = "Margin: ${String.format(Locale.US, "%.1f", profitMargin)}%",
                            icon = Icons.AutoMirrored.Filled.TrendingUp,
                            accentColor = if (netProfit >= 0) greenSuccess else redDanger,
                            cardBg = cardBg,
                            cardBorder = cardBorder,
                            primaryText = primaryText,
                            secondaryText = secondaryText
                        )
                    }

                    Spacer(modifier = Modifier.height(12.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        // Total Expenses
                        ReportKpiCard(
                            modifier = Modifier.weight(1f),
                            title = "Operating Expenses",
                            value = formatMoney(totalExpenseAmount),
                            subtitle = "${filteredExpenses.size} expense records",
                            icon = Icons.Outlined.ReceiptLong,
                            accentColor = redDanger,
                            cardBg = cardBg,
                            cardBorder = cardBorder,
                            primaryText = primaryText,
                            secondaryText = secondaryText
                        )

                        // Customer Dues
                        ReportKpiCard(
                            modifier = Modifier.weight(1f),
                            title = "Customer Dues",
                            value = formatMoney(totalCustomerDues),
                            subtitle = "${customers.count { it.currentBalance > 0 }} active debtors",
                            icon = Icons.Outlined.Handshake,
                            accentColor = blueAccent,
                            cardBg = cardBg,
                            cardBorder = cardBorder,
                            primaryText = primaryText,
                            secondaryText = secondaryText
                        )
                    }
                }
            }

            // 4. MAIN DYNAMIC GRAPH (REVENUE TREND LINE CHART)
            item {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(start = 18.dp, end = 18.dp, top = 18.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text(
                                    text = "Revenue vs Expense Trend",
                                    fontSize = 15.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                                Text(
                                    text = "Daily financial performance ($selectedTimeFrame)",
                                    fontSize = 11.sp,
                                    color = secondaryText
                                )
                            }

                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Box(
                                    modifier = Modifier
                                        .size(8.dp)
                                        .clip(CircleShape)
                                        .background(greenSuccess)
                                )
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("Sales", fontSize = 10.sp, color = secondaryText)

                                Spacer(modifier = Modifier.width(12.dp))

                                Box(
                                    modifier = Modifier
                                        .size(8.dp)
                                        .clip(CircleShape)
                                        .background(redDanger)
                                )
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("Expense", fontSize = 10.sp, color = secondaryText)
                            }
                        }

                        Spacer(modifier = Modifier.height(16.dp))

                        val salesPoints = remember(filteredSales) {
                            if (filteredSales.isEmpty()) emptyList<Float>()
                            else {
                                val grouped = filteredSales.take(10).map { it.netTotal.toFloat() }
                                if (grouped.size < 2) listOf(grouped.firstOrNull() ?: 0f, grouped.firstOrNull() ?: 0f) else grouped
                            }
                        }

                        val expensePoints = remember(filteredExpenses) {
                            if (filteredExpenses.isEmpty()) emptyList<Float>()
                            else {
                                val grouped = filteredExpenses.take(10).map { it.amount.toFloat() }
                                if (grouped.size < 2) listOf(grouped.firstOrNull() ?: 0f, grouped.firstOrNull() ?: 0f) else grouped
                            }
                        }

                        if (salesPoints.isEmpty() && expensePoints.isEmpty()) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(170.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = "No sales or expense data in this period",
                                    fontSize = 12.sp,
                                    color = secondaryText
                                )
                            }
                        } else {
                            AnimatedLineGraph(
                                salesData = if (salesPoints.isEmpty()) listOf(0f, 0f) else salesPoints,
                                expenseData = if (expensePoints.isEmpty()) listOf(0f, 0f) else expensePoints,
                                salesColor = greenSuccess,
                                expenseColor = redDanger,
                                gridColor = cardBorder.copy(alpha = 0.5f),
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(170.dp)
                            )
                        }
                    }
                }
            }

            // 5. BREAKDOWN BAR CHART (PAYMENT METHOD & MFS RATIO)
            item {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(start = 18.dp, end = 18.dp, top = 16.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Payment Gateway & MFS Mix",
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold,
                            color = primaryText
                        )
                        Text(
                            text = "Collection volume by MFS and Cash gateways",
                            fontSize = 11.sp,
                            color = secondaryText
                        )

                        Spacer(modifier = Modifier.height(16.dp))

                        val bkashTotal = filteredSales.filter { it.paymentMethod.equals("bKash", ignoreCase = true) }.sumOf { it.netTotal }
                        val nagadTotal = filteredSales.filter { it.paymentMethod.equals("Nagad", ignoreCase = true) }.sumOf { it.netTotal }
                        val cardTotal = filteredSales.filter { it.paymentMethod.equals("Card", ignoreCase = true) }.sumOf { it.netTotal }
                        val cashTotal = filteredSales.filter { it.paymentMethod.equals("Cash", ignoreCase = true) }.sumOf { it.netTotal }
                        val dueTotal = filteredSales.filter { it.paymentStatus.equals("DUE", ignoreCase = true) }.sumOf { it.netTotal }

                        val mfsCategories = listOf(
                            PaymentMixData("bKash", bkashTotal, Color(0xFFE2136E)),
                            PaymentMixData("Nagad", nagadTotal, Color(0xFFF7921E)),
                            PaymentMixData("Card / POS", cardTotal, blueAccent),
                            PaymentMixData("Cash", cashTotal, greenSuccess),
                            PaymentMixData("Due / Credit", dueTotal, goldAccent)
                        )

                        val maxVal = (mfsCategories.maxOfOrNull { it.amount } ?: 1.0).coerceAtLeast(100.0)

                        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            mfsCategories.forEach { item ->
                                val ratio = (item.amount / maxVal).toFloat().coerceIn(0.05f, 1.0f)
                                val animatedRatio by animateFloatAsState(
                                    targetValue = ratio,
                                    animationSpec = tween(durationMillis = 800, easing = FastOutSlowInEasing),
                                    label = "barAnimation"
                                )

                                Column {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Text(item.name, fontSize = 12.sp, color = primaryText, fontWeight = FontWeight.Medium)
                                        Text(formatMoney(item.amount), fontSize = 12.sp, color = goldAccent, fontWeight = FontWeight.Bold)
                                    }
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .height(8.dp)
                                            .clip(RoundedCornerShape(4.dp))
                                            .background(cardBorder)
                                    ) {
                                        Box(
                                            modifier = Modifier
                                                .fillMaxHeight()
                                                .fillMaxWidth(animatedRatio)
                                                .clip(RoundedCornerShape(4.dp))
                                                .background(item.color)
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // 6. EXPENSE CATEGORY DONUT BREAKDOWN
            item {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(start = 18.dp, end = 18.dp, top = 16.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Operating Expense Distribution",
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold,
                            color = primaryText
                        )
                        Text(
                            text = "Detailed outflow by expense classification",
                            fontSize = 11.sp,
                            color = secondaryText
                        )

                        Spacer(modifier = Modifier.height(16.dp))

                        val expenseGrouped = remember(filteredExpenses) {
                            if (filteredExpenses.isEmpty()) {
                                emptyMap<String, Double>()
                            } else {
                                filteredExpenses.groupBy { it.category }.mapValues { entry -> entry.value.sumOf { it.amount } }
                            }
                        }

                        val expenseColors = listOf(
                            Color(0xFFEF4444), Color(0xFFF59E0B), Color(0xFF3B82F6),
                            Color(0xFF8B5CF6), Color(0xFF10B981), Color(0xFFEC4899)
                        )

                        if (expenseGrouped.isEmpty()) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(130.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = "No expense data recorded in this period",
                                    fontSize = 12.sp,
                                    color = secondaryText
                                )
                            }
                        } else {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                // Donut Chart Canvas
                                Box(
                                    modifier = Modifier.size(130.dp),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Canvas(modifier = Modifier.fillMaxSize()) {
                                        val totalVal = expenseGrouped.values.sum().coerceAtLeast(1.0)
                                        var startAngle = -90f

                                        expenseGrouped.entries.forEachIndexed { idx, entry ->
                                            val sweep = ((entry.value / totalVal) * 360f).toFloat()
                                            val color = expenseColors[idx % expenseColors.size]

                                            drawArc(
                                                color = color,
                                                startAngle = startAngle,
                                                sweepAngle = sweep,
                                                useCenter = false,
                                                style = Stroke(width = 24.dp.toPx(), cap = StrokeCap.Butt)
                                            )
                                            startAngle += sweep
                                        }
                                    }

                                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                        Text(
                                            text = "Total",
                                            fontSize = 10.sp,
                                            color = secondaryText
                                        )
                                        Text(
                                            text = formatMoney(expenseGrouped.values.sum()),
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = primaryText
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.width(16.dp))

                                // Legend List
                                Column(
                                    modifier = Modifier.weight(1f),
                                    verticalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    expenseGrouped.entries.take(5).forEachIndexed { idx, entry ->
                                        val color = expenseColors[idx % expenseColors.size]
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
                                                        .size(10.dp)
                                                        .clip(CircleShape)
                                                        .background(color)
                                                )
                                                Spacer(modifier = Modifier.width(6.dp))
                                                Text(
                                                    text = entry.key,
                                                    fontSize = 12.sp,
                                                    color = primaryText,
                                                    maxLines = 1,
                                                    overflow = TextOverflow.Ellipsis
                                                )
                                            }
                                            Spacer(modifier = Modifier.width(8.dp))
                                            Text(
                                                text = formatMoney(entry.value),
                                                fontSize = 11.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = secondaryText,
                                                maxLines = 1,
                                                softWrap = false
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // 7. RECENT TRANSACTIONS AUDIT TABLE
            item {
                Column(modifier = Modifier.padding(start = 18.dp, end = 18.dp, top = 20.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "RECENT AUDIT LOGS",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = goldAccent,
                            letterSpacing = 1.2.sp
                        )

                        Text(
                            text = "View All (${filteredSales.size + filteredLedgers.size})",
                            fontSize = 12.sp,
                            color = blueAccent,
                            fontWeight = FontWeight.Medium,
                            modifier = Modifier.clickable { viewModel.navigateTo("SalesHistory") }
                        )
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    if (filteredSales.isEmpty() && filteredLedgers.isEmpty()) {
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(32.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                    Icon(
                                        imageVector = Icons.Outlined.FolderOff,
                                        contentDescription = null,
                                        tint = secondaryText,
                                        modifier = Modifier.size(36.dp)
                                    )
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text(
                                        text = "No records found for the selected time range",
                                        fontSize = 13.sp,
                                        color = secondaryText
                                    )
                                }
                            }
                        }
                    } else {
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            filteredSales.take(5).forEach { sale ->
                                ReportAuditRow(
                                    title = "Invoice ${sale.invoiceNo}",
                                    subtitle = "${sale.customerName} • ${sale.paymentMethod}",
                                    amount = "+ " + formatMoney(sale.netTotal),
                                    status = sale.paymentStatus,
                                    date = SimpleDateFormat("dd MMM, hh:mm a", Locale.getDefault()).format(Date(sale.timestamp)),
                                    isIncome = true,
                                    cardBg = cardBg,
                                    cardBorder = cardBorder,
                                    primaryText = primaryText,
                                    secondaryText = secondaryText,
                                    greenSuccess = greenSuccess,
                                    goldAccent = goldAccent
                                )
                            }
                        }
                    }
                }
            }
        }

        // EXPORT MODAL DIALOG
        if (showExportModal) {
            Dialog(onDismissRequest = { showExportModal = false }) {
                Surface(
                    shape = RoundedCornerShape(20.dp),
                    color = cardBg,
                    border = BorderStroke(1.dp, cardBorder),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Column(modifier = Modifier.padding(20.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "Export Financial Report",
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                color = primaryText
                            )
                            IconButton(onClick = { showExportModal = false }) {
                                Icon(Icons.Default.Close, contentDescription = "Close", tint = secondaryText)
                            }
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        Text(
                            text = "Select format to generate report for $selectedTimeFrame:",
                            fontSize = 13.sp,
                            color = secondaryText
                        )

                        Spacer(modifier = Modifier.height(16.dp))

                        // Export Options
                        ExportOptionRow(
                            title = "Executive PDF Report",
                            subtitle = "Includes summary graphs, P&L, & invoice logs",
                            icon = Icons.Default.PictureAsPdf,
                            color = redDanger,
                            cardBg = softBg,
                            cardBorder = cardBorder,
                            primaryText = primaryText,
                            secondaryText = secondaryText,
                            onClick = {
                                showExportModal = false
                                viewModel.exportTransactionsToCsv(context) { msg ->
                                    Toast.makeText(context, "Executive PDF/CSV report generated: $msg", Toast.LENGTH_LONG).show()
                                }
                            }
                        )

                        Spacer(modifier = Modifier.height(10.dp))

                        ExportOptionRow(
                            title = "Excel Spreadsheet (.xlsx)",
                            subtitle = "Raw dataset for accounting and audit",
                            icon = Icons.Outlined.TableChart,
                            color = greenSuccess,
                            cardBg = softBg,
                            cardBorder = cardBorder,
                            primaryText = primaryText,
                            secondaryText = secondaryText,
                            onClick = {
                                showExportModal = false
                                viewModel.exportTransactionsToCsv(context) { msg ->
                                    Toast.makeText(context, "Excel Data Downloaded: $msg", Toast.LENGTH_LONG).show()
                                }
                            }
                        )

                        Spacer(modifier = Modifier.height(10.dp))

                        ExportOptionRow(
                            title = "Print Statement",
                            subtitle = "Send direct to POS thermal or office printer",
                            icon = Icons.Default.Print,
                            color = blueAccent,
                            cardBg = softBg,
                            cardBorder = cardBorder,
                            primaryText = primaryText,
                            secondaryText = secondaryText,
                            onClick = {
                                showExportModal = false
                                viewModel.logFirebaseStatus("Triggered Print Statement")
                                try {
                                    val printManager = context.getSystemService(android.content.Context.PRINT_SERVICE) as android.print.PrintManager
                                    val jobName = "SwapnoPay Financial Report"
                                    val printDocAdapter = object : android.print.PrintDocumentAdapter() {
                                        override fun onLayout(oldAttrs: android.print.PrintAttributes?, newAttrs: android.print.PrintAttributes, cancellationSignal: android.os.CancellationSignal?, callback: LayoutResultCallback, extras: android.os.Bundle?) {
                                            if (cancellationSignal?.isCanceled == true) { callback.onLayoutCancelled(); return }
                                            callback.onLayoutFinished(android.print.PrintDocumentInfo.Builder(jobName).setContentType(android.print.PrintDocumentInfo.CONTENT_TYPE_DOCUMENT).build(), true)
                                        }
                                        override fun onWrite(pages: Array<out android.print.PageRange>?, destination: android.os.ParcelFileDescriptor?, cancellationSignal: android.os.CancellationSignal?, callback: WriteResultCallback) {
                                            callback.onWriteFailed("Use CSV export for full report data")
                                        }
                                    }
                                    printManager.print(jobName, printDocAdapter, android.print.PrintAttributes.Builder().build())
                                } catch (e: Exception) {
                                    Toast.makeText(context, "Print error: ${e.message}", Toast.LENGTH_SHORT).show()
                                }
                            }
                        )
                    }
                }
            }
        }
    }
}

// KPI CARD COMPONENT
@Composable
private fun ReportKpiCard(
    modifier: Modifier = Modifier,
    title: String,
    value: String,
    subtitle: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    accentColor: Color,
    cardBg: Color,
    cardBorder: Color,
    primaryText: Color,
    secondaryText: Color
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder),
        shape = RoundedCornerShape(14.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = title,
                    fontSize = 11.sp,
                    color = secondaryText,
                    fontWeight = FontWeight.Medium,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false)
                )

                Box(
                    modifier = Modifier
                        .size(28.dp)
                        .clip(CircleShape)
                        .background(accentColor.copy(alpha = 0.15f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = accentColor,
                        modifier = Modifier.size(16.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = value,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = primaryText,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )

            Spacer(modifier = Modifier.height(2.dp))

            Text(
                text = subtitle,
                fontSize = 10.sp,
                color = secondaryText
            )
        }
    }
}

// ANIMATED CANVAS LINE GRAPH
@Composable
private fun AnimatedLineGraph(
    salesData: List<Float>,
    expenseData: List<Float>,
    salesColor: Color,
    expenseColor: Color,
    gridColor: Color,
    modifier: Modifier = Modifier
) {
    val progress = remember { Animatable(0f) }

    LaunchedEffect(salesData, expenseData) {
        progress.snapTo(0f)
        progress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 1000, easing = FastOutSlowInEasing)
        )
    }

    Canvas(modifier = modifier) {
        val width = size.width
        val height = size.height

        val maxVal = (salesData.maxOrNull() ?: 100f).coerceAtLeast(expenseData.maxOrNull() ?: 100f).coerceAtLeast(10f)

        // Draw horizontal grid lines
        val gridLines = 4
        for (i in 0..gridLines) {
            val y = (height / gridLines) * i
            drawLine(
                color = gridColor,
                start = Offset(0f, y),
                end = Offset(width, y),
                strokeWidth = 1.dp.toPx()
            )
        }

        // Draw Sales Path
        if (salesData.size >= 2) {
            val salesPath = Path()
            val salesFillPath = Path()

            val stepX = width / (salesData.size - 1)

            salesData.forEachIndexed { i, valPoint ->
                val x = i * stepX
                val normalizedY = height - ((valPoint / maxVal) * height * 0.8f * progress.value) - (height * 0.1f)

                if (i == 0) {
                    salesPath.moveTo(x, normalizedY)
                    salesFillPath.moveTo(x, height)
                    salesFillPath.lineTo(x, normalizedY)
                } else {
                    val prevX = (i - 1) * stepX
                    val prevVal = salesData[i - 1]
                    val prevY = height - ((prevVal / maxVal) * height * 0.8f * progress.value) - (height * 0.1f)

                    val controlX1 = prevX + (stepX / 2)
                    val controlX2 = prevX + (stepX / 2)

                    salesPath.cubicTo(controlX1, prevY, controlX2, normalizedY, x, normalizedY)
                    salesFillPath.cubicTo(controlX1, prevY, controlX2, normalizedY, x, normalizedY)
                }

                if (i == salesData.size - 1) {
                    salesFillPath.lineTo(x, height)
                    salesFillPath.close()
                }
            }

            // Fill gradient under curve
            drawPath(
                path = salesFillPath,
                brush = Brush.verticalGradient(
                    colors = listOf(salesColor.copy(alpha = 0.35f), Color.Transparent),
                    startY = 0f,
                    endY = height
                )
            )

            // Stroke line
            drawPath(
                path = salesPath,
                color = salesColor,
                style = Stroke(width = 3.dp.toPx(), cap = StrokeCap.Round)
            )
        }

        // Draw Expense Path
        if (expenseData.size >= 2) {
            val expensePath = Path()
            val stepX = width / (expenseData.size - 1)

            expenseData.forEachIndexed { i, valPoint ->
                val x = i * stepX
                val normalizedY = height - ((valPoint / maxVal) * height * 0.8f * progress.value) - (height * 0.1f)

                if (i == 0) {
                    expensePath.moveTo(x, normalizedY)
                } else {
                    val prevX = (i - 1) * stepX
                    val prevVal = expenseData[i - 1]
                    val prevY = height - ((prevVal / maxVal) * height * 0.8f * progress.value) - (height * 0.1f)

                    expensePath.cubicTo(
                        prevX + (stepX / 2), prevY,
                        prevX + (stepX / 2), normalizedY,
                        x, normalizedY
                    )
                }
            }

            drawPath(
                path = expensePath,
                color = expenseColor,
                style = Stroke(width = 2.dp.toPx(), cap = StrokeCap.Round)
            )
        }
    }
}

private data class PaymentMixData(
    val name: String,
    val amount: Double,
    val color: Color
)

@Composable
private fun ReportAuditRow(
    title: String,
    subtitle: String,
    amount: String,
    status: String,
    date: String,
    isIncome: Boolean,
    cardBg: Color,
    cardBorder: Color,
    primaryText: Color,
    secondaryText: Color,
    greenSuccess: Color,
    goldAccent: Color
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(if (isIncome) greenSuccess.copy(alpha = 0.15f) else goldAccent.copy(alpha = 0.15f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = if (isIncome) Icons.Default.ArrowUpward else Icons.Default.ArrowDownward,
                    contentDescription = null,
                    tint = if (isIncome) greenSuccess else goldAccent,
                    modifier = Modifier.size(18.dp)
                )
            }

            Spacer(modifier = Modifier.width(10.dp))

            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(end = 8.dp)
            ) {
                Text(
                    text = title,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = primaryText,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    text = subtitle,
                    fontSize = 11.sp,
                    color = secondaryText,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }

            Column(
                horizontalAlignment = Alignment.End
            ) {
                Text(
                    text = amount,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (isIncome) greenSuccess else primaryText,
                    maxLines = 1,
                    softWrap = false
                )
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    text = date,
                    fontSize = 10.sp,
                    color = secondaryText,
                    maxLines = 1,
                    softWrap = false
                )
            }
        }
    }
}

@Composable
private fun ExportOptionRow(
    title: String,
    subtitle: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    color: Color,
    cardBg: Color,
    cardBorder: Color,
    primaryText: Color,
    secondaryText: Color,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(color.copy(alpha = 0.15f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = color,
                    modifier = Modifier.size(20.dp)
                )
            }

            Spacer(modifier = Modifier.width(12.dp))

            Column {
                Text(
                    text = title,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = primaryText
                )
                Text(
                    text = subtitle,
                    fontSize = 11.sp,
                    color = secondaryText
                )
            }
        }
    }
}
