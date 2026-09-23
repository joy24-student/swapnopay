package com.example.ui

import android.widget.Toast
import androidx.activity.compose.BackHandler
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.data.local.BusinessLoanEntity
import com.example.data.local.DpsAccountEntity
import com.example.data.local.FinanceInstallmentEntity
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

// ─────────────────────────────────────────────────────────────────────────────
// UNIFIED DPS & LOAN MANAGEMENT SCREEN WITH TAB SWITCHING
// ─────────────────────────────────────────────────────────────────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DpsAndLoansScreen(viewModel: AppViewModel, initialTab: String = "DPS") {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val loans by viewModel.loans.collectAsState()
    val dpsAccounts by viewModel.dpsAccounts.collectAsState()
    val installments by viewModel.financeInstallments.collectAsState()

    val cleanInitialTab = if (initialTab.equals("LOANS", ignoreCase = true) || initialTab.equals("LOAN", ignoreCase = true) || initialTab.equals("LoanScreen", ignoreCase = true) || initialTab.equals("BusinessLoans", ignoreCase = true)) "LOANS" else "DPS"
    var selectedTab by rememberSaveable { mutableStateOf(cleanInitialTab) }

    LaunchedEffect(initialTab) {
        val target = if (initialTab.equals("LOANS", ignoreCase = true) || initialTab.equals("LOAN", ignoreCase = true) || initialTab.equals("LoanScreen", ignoreCase = true) || initialTab.equals("BusinessLoans", ignoreCase = true)) "LOANS" else "DPS"
        if (selectedTab != target) {
            selectedTab = target
        }
    }

    // Modal dialog controls
    var showLoanCalculator by remember { mutableStateOf(false) }
    var showDpsCalculator by remember { mutableStateOf(false) }
    var showAddLoanDialog by remember { mutableStateOf(false) }
    var showAddDepositDialog by remember { mutableStateOf(false) }
    var paymentTarget by remember { mutableStateOf<FinanceInstallmentEntity?>(null) }

    // Tab-specific filters & expansion states
    var expandedLoanId by rememberSaveable { mutableStateOf<String?>(null) }
    var loanFilterStatus by rememberSaveable { mutableStateOf("ALL") }
    var expandedDpsId by rememberSaveable { mutableStateOf<String?>(null) }
    var dpsFilterStatus by rememberSaveable { mutableStateOf("ALL") }

    val now = System.currentTimeMillis()
    val bg = if (isDark) Color(0xFF070707) else Color(0xFFF8FAFC)
    val card = if (isDark) Color(0xFF13100A) else Color.White
    val border = if (isDark) Color(0xFF382A0B) else Color(0xFFE2E8F0)
    val text = if (isDark) Color(0xFFF8FAFC) else Color(0xFF0F172A)
    val muted = if (isDark) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val gold = Color(0xFFF5C518)
    val goldPill = if (isDark) Color(0xFF261D07) else Color(0xFFFEF3C7)

    // Loan metrics
    val loanInstallments = remember(installments) { installments.filter { it.accountType == "LOAN" } }
    val totalBorrowed = remember(loans) { loans.sumOf { it.principalAmount } }
    val totalLoanPaid = remember(loanInstallments) {
        loanInstallments.filter { it.status == "PAID" }.sumOf { it.totalAmount }
    }
    val totalLoanOutstanding = remember(loanInstallments) {
        loanInstallments.filter { it.status != "PAID" && it.status != "WAIVED" }.sumOf { it.totalAmount }
    }
    val filteredLoans = remember(loans, loanFilterStatus, loanInstallments) {
        when (loanFilterStatus) {
            "ACTIVE" -> loans.filter { loan ->
                val remaining = loanInstallments.filter { it.accountId == loan.id && it.status != "PAID" }
                remaining.isNotEmpty()
            }
            "COMPLETED" -> loans.filter { loan ->
                val inst = loanInstallments.filter { it.accountId == loan.id }
                inst.isNotEmpty() && inst.all { it.status == "PAID" || it.status == "WAIVED" }
            }
            else -> loans
        }
    }

    // DPS metrics
    val dpsInstallments = remember(installments) { installments.filter { it.accountType == "DPS" } }
    val totalMonthlyCommitment = remember(dpsAccounts) { dpsAccounts.sumOf { it.monthlyDeposit } }
    val totalSavingsDeposited = remember(dpsInstallments) {
        dpsInstallments.filter { it.status == "PAID" }.sumOf { it.totalAmount }
    }
    val totalProjectedMaturity = remember(dpsAccounts) {
        dpsAccounts.sumOf { dps ->
            val totalDep = dps.monthlyDeposit * dps.durationMonths
            val estReturn = totalDep * (dps.interestRate / 100.0) * (dps.durationMonths / 12.0) * 0.55
            totalDep + estReturn
        }
    }
    val filteredDps = remember(dpsAccounts, dpsFilterStatus) {
        when (dpsFilterStatus) {
            "ACTIVE" -> dpsAccounts.filter { it.status == "ACTIVE" }
            "MATURED" -> dpsAccounts.filter { it.status == "MATURED" || it.maturityDate < now }
            else -> dpsAccounts
        }
    }

    Scaffold(
        containerColor = bg,
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = if (selectedTab == "DPS") "DPS & Savings" else "Business Loans & EMI",
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp
                        )
                        Text(
                            text = if (selectedTab == "DPS") "Recurring savings & maturity growth" else "Installment tracking & amortization",
                            fontSize = 11.sp,
                            color = muted
                        )
                    }
                },
                navigationIcon = {
                    IconButton(onClick = viewModel::goBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = text)
                    }
                },
                actions = {
                    IconButton(onClick = {
                        if (selectedTab == "DPS") showDpsCalculator = true else showLoanCalculator = true
                    }) {
                        Icon(Icons.Outlined.Calculate, contentDescription = "Calculator", tint = gold)
                    }
                    IconButton(onClick = {
                        if (selectedTab == "DPS") showAddDepositDialog = true else showAddLoanDialog = true
                    }) {
                        Icon(Icons.Default.Add, contentDescription = "Add", tint = gold)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = card, titleContentColor = text)
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            // ── TOP TAB SWITCHER (DPS vs LOANS) ──
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 4.dp),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF13100A) else Color(0xFFF1F5F9)),
                border = BorderStroke(1.dp, border)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(4.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    val isDpsSelected = selectedTab == "DPS"
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .height(44.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isDpsSelected) goldPill else Color.Transparent)
                            .border(
                                1.dp,
                                if (isDpsSelected) gold else Color.Transparent,
                                RoundedCornerShape(10.dp)
                            )
                            .clickable { selectedTab = "DPS" },
                        contentAlignment = Alignment.Center
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Outlined.Savings,
                                contentDescription = null,
                                tint = if (isDpsSelected) gold else muted,
                                modifier = Modifier.size(19.dp)
                            )
                            Text(
                                text = "DPS & Savings",
                                fontSize = 14.sp,
                                fontWeight = if (isDpsSelected) FontWeight.Bold else FontWeight.SemiBold,
                                color = if (isDpsSelected) (if (isDark) gold else Color(0xFFB45309)) else muted
                            )
                        }
                    }

                    val isLoanSelected = selectedTab == "LOANS"
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .height(44.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isLoanSelected) goldPill else Color.Transparent)
                            .border(
                                1.dp,
                                if (isLoanSelected) gold else Color.Transparent,
                                RoundedCornerShape(10.dp)
                            )
                            .clickable { selectedTab = "LOANS" },
                        contentAlignment = Alignment.Center
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Outlined.AccountBalance,
                                contentDescription = null,
                                tint = if (isLoanSelected) gold else muted,
                                modifier = Modifier.size(19.dp)
                            )
                            Text(
                                text = "Business Loans",
                                fontSize = 14.sp,
                                fontWeight = if (isLoanSelected) FontWeight.Bold else FontWeight.SemiBold,
                                color = if (isLoanSelected) (if (isDark) gold else Color(0xFFB45309)) else muted
                            )
                        }
                    }
                }
            }

            // ── TAB CONTENT ──
            if (selectedTab == "DPS") {
                // ── DPS SUMMARY CARDS ROW ──
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Card(
                        modifier = Modifier.weight(1f),
                        colors = CardDefaults.cardColors(containerColor = card),
                        border = BorderStroke(1.dp, border),
                        shape = RoundedCornerShape(14.dp)
                    ) {
                        Column(Modifier.padding(12.dp)) {
                            Text("Total Deposited", fontSize = 11.sp, color = muted)
                            Spacer(Modifier.height(4.dp))
                            Text("৳ ${formatMoney(totalSavingsDeposited)}", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                            Text("Monthly: ৳ ${formatMoney(totalMonthlyCommitment)}", fontSize = 10.sp, color = gold)
                        }
                    }
                    Card(
                        modifier = Modifier.weight(1f),
                        colors = CardDefaults.cardColors(containerColor = card),
                        border = BorderStroke(1.dp, border),
                        shape = RoundedCornerShape(14.dp)
                    ) {
                        Column(Modifier.padding(12.dp)) {
                            Text("Projected Maturity", fontSize = 11.sp, color = muted)
                            Spacer(Modifier.height(4.dp))
                            Text("৳ ${formatMoney(totalProjectedMaturity)}", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = gold)
                            Text("${dpsAccounts.size} DPS accounts", fontSize = 10.sp, color = muted)
                        }
                    }
                }

                // DPS Filter Chips
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("ALL" to "All DPS", "ACTIVE" to "Active", "MATURED" to "Matured").forEach { (key, label) ->
                        FilterChip(
                            selected = dpsFilterStatus == key,
                            onClick = { dpsFilterStatus = key },
                            label = { Text(label, fontSize = 12.sp) }
                        )
                    }
                }

                // DPS Accounts List
                if (filteredDps.isEmpty()) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .weight(1f),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Outlined.Savings, null, tint = muted, modifier = Modifier.size(56.dp))
                            Spacer(Modifier.height(12.dp))
                            Text("No DPS Accounts Found", fontWeight = FontWeight.Bold, color = text, fontSize = 16.sp)
                            Text("Create a monthly deposit scheme to build capital reserves.", color = muted, fontSize = 12.sp)
                            Spacer(Modifier.height(16.dp))
                            Button(
                                onClick = { showAddDepositDialog = true },
                                colors = ButtonDefaults.buttonColors(containerColor = gold, contentColor = Color.Black),
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                Text("Add DPS Account", fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                        contentPadding = PaddingValues(bottom = 32.dp)
                    ) {
                        items(filteredDps, key = { it.id }) { dps ->
                            val dpsRows = dpsInstallments.filter { it.accountId == dps.id }
                            val paidSum = dpsRows.filter { it.status == "PAID" }.sumOf { it.totalAmount }
                            val totalExpected = dps.monthlyDeposit * dps.durationMonths
                            val isExpanded = expandedDpsId == dps.id
                            val progress = if (totalExpected > 0.0) (paidSum / totalExpected).toFloat().coerceIn(0f, 1f) else 0f

                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { expandedDpsId = if (isExpanded) null else dps.id },
                                colors = CardDefaults.cardColors(containerColor = card),
                                border = BorderStroke(1.dp, border),
                                shape = RoundedCornerShape(16.dp)
                            ) {
                                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.Top
                                    ) {
                                        Column {
                                            Text(dps.providerName.ifBlank { "DPS Provider" }, fontWeight = FontWeight.Bold, fontSize = 16.sp, color = text)
                                            Text("Ref: ${dps.accountReference.ifBlank { dps.id.take(8) }}", fontSize = 11.sp, color = muted)
                                        }
                                        Column(horizontalAlignment = Alignment.End) {
                                            Text("Monthly Deposit", fontSize = 10.sp, color = muted)
                                            Text("৳ ${formatMoney(dps.monthlyDeposit)}", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = Color(0xFF10B981))
                                        }
                                    }

                                    LinearProgressIndicator(
                                        progress = { progress },
                                        modifier = Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp)),
                                        color = Color(0xFF10B981),
                                        trackColor = if (isDark) Color(0xFF062C12) else Color(0xFFDCFCE7)
                                    )

                                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                        Text("Deposited: ৳ ${formatMoney(paidSum)} (${(progress * 100).toInt()}%)", fontSize = 11.sp, color = Color(0xFF10B981))
                                        Text("Maturity: ${formatDate(dps.maturityDate)}", fontSize = 11.sp, color = muted)
                                    }

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Text("Return Rate: ${dps.interestRate}% p.a.", fontSize = 11.sp, color = muted)
                                        Text("Tenure: ${dps.durationMonths} Months", fontSize = 11.sp, color = muted)
                                        Text(
                                            if (isExpanded) "Hide Schedule ▲" else "View Schedule (${dpsRows.size}) ▼",
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            color = gold
                                        )
                                    }

                                    AnimatedVisibility(visible = isExpanded) {
                                        Column(
                                            modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
                                            verticalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            HorizontalDivider(color = border)
                                            Text("Monthly Deposit Schedule", fontWeight = FontWeight.SemiBold, fontSize = 12.sp, color = muted)

                                            if (dpsRows.isEmpty()) {
                                                Text("No installment schedule generated.", fontSize = 11.sp, color = muted)
                                            }

                                            dpsRows.forEach { row ->
                                                val isOverdue = row.status == "PENDING" && row.dueDate < now
                                                val statusDisplay = if (isOverdue) "OVERDUE" else row.status
                                                val statusColor = when (statusDisplay) {
                                                    "PAID" -> Color(0xFF10B981)
                                                    "OVERDUE" -> Color(0xFFEF4444)
                                                    else -> muted
                                                }

                                                Row(
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .padding(vertical = 4.dp),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Column {
                                                        Text("#${row.installmentNumber} • ${formatDate(row.dueDate)}", fontSize = 12.sp, fontWeight = FontWeight.Medium, color = text)
                                                        Text("৳ ${formatMoney(row.totalAmount)} • $statusDisplay", fontSize = 10.sp, color = statusColor)
                                                    }
                                                    if (statusDisplay in setOf("PENDING", "OVERDUE")) {
                                                        Button(
                                                            onClick = { paymentTarget = row },
                                                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981), contentColor = Color.White),
                                                            contentPadding = PaddingValues(horizontal = 10.dp, vertical = 2.dp),
                                                            shape = RoundedCornerShape(8.dp)
                                                        ) {
                                                            Text("Pay", fontSize = 11.sp, fontWeight = FontWeight.Bold)
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
                }
            } else {
                // ── LOANS TAB CONTENT ──
                // Summary Cards Row
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Card(
                        modifier = Modifier.weight(1f),
                        colors = CardDefaults.cardColors(containerColor = card),
                        border = BorderStroke(1.dp, border),
                        shape = RoundedCornerShape(14.dp)
                    ) {
                        Column(Modifier.padding(12.dp)) {
                            Text("Total Borrowed", fontSize = 11.sp, color = muted)
                            Spacer(Modifier.height(4.dp))
                            Text("৳ ${formatMoney(totalBorrowed)}", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = text)
                            Text("${loans.size} active loans", fontSize = 10.sp, color = gold)
                        }
                    }
                    Card(
                        modifier = Modifier.weight(1f),
                        colors = CardDefaults.cardColors(containerColor = card),
                        border = BorderStroke(1.dp, border),
                        shape = RoundedCornerShape(14.dp)
                    ) {
                        Column(Modifier.padding(12.dp)) {
                            Text("Remaining Dues", fontSize = 11.sp, color = muted)
                            Spacer(Modifier.height(4.dp))
                            Text("৳ ${formatMoney(totalLoanOutstanding)}", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Color(0xFFEF4444))
                            Text("Paid: ৳ ${formatMoney(totalLoanPaid)}", fontSize = 10.sp, color = Color(0xFF10B981))
                        }
                    }
                }

                // Filter Chips
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("ALL" to "All Loans", "ACTIVE" to "Active", "COMPLETED" to "Repaid").forEach { (key, label) ->
                        FilterChip(
                            selected = loanFilterStatus == key,
                            onClick = { loanFilterStatus = key },
                            label = { Text(label, fontSize = 12.sp) }
                        )
                    }
                }

                // Loans List
                if (filteredLoans.isEmpty()) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .weight(1f),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Outlined.AccountBalance, null, tint = muted, modifier = Modifier.size(56.dp))
                            Spacer(Modifier.height(12.dp))
                            Text("No Loans Found", fontWeight = FontWeight.Bold, color = text, fontSize = 16.sp)
                            Text("Add bank or private financing to track EMI repayments.", color = muted, fontSize = 12.sp)
                            Spacer(Modifier.height(16.dp))
                            Button(
                                onClick = { showAddLoanDialog = true },
                                colors = ButtonDefaults.buttonColors(containerColor = gold, contentColor = Color.Black),
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                Text("Add Loan Account", fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                        contentPadding = PaddingValues(bottom = 32.dp)
                    ) {
                        items(filteredLoans, key = { it.id }) { loan ->
                            val loanRows = loanInstallments.filter { it.accountId == loan.id }
                            val paidSum = loanRows.filter { it.status == "PAID" }.sumOf { it.totalAmount }
                            val remainingSum = loanRows.filter { it.status != "PAID" && it.status != "WAIVED" }.sumOf { it.totalAmount }
                            val isExpanded = expandedLoanId == loan.id
                            val progress = if (paidSum + remainingSum > 0.0) (paidSum / (paidSum + remainingSum)).toFloat() else 0f

                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { expandedLoanId = if (isExpanded) null else loan.id },
                                colors = CardDefaults.cardColors(containerColor = card),
                                border = BorderStroke(1.dp, border),
                                shape = RoundedCornerShape(16.dp)
                            ) {
                                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.Top
                                    ) {
                                        Column {
                                            Text(loan.providerName.ifBlank { "Loan Provider" }, fontWeight = FontWeight.Bold, fontSize = 16.sp, color = text)
                                            Text("Ref: ${loan.accountReference.ifBlank { loan.id.take(8) }}", fontSize = 11.sp, color = muted)
                                        }
                                        Column(horizontalAlignment = Alignment.End) {
                                            Text("Monthly EMI", fontSize = 10.sp, color = muted)
                                            Text("৳ ${formatMoney(loan.monthlyInstallment)}", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = gold)
                                        }
                                    }

                                    LinearProgressIndicator(
                                        progress = { progress },
                                        modifier = Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp)),
                                        color = gold,
                                        trackColor = if (isDark) Color(0xFF261D07) else Color(0xFFFEF3C7)
                                    )

                                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                        Text("Paid: ৳ ${formatMoney(paidSum)} (${(progress * 100).toInt()}%)", fontSize = 11.sp, color = Color(0xFF10B981))
                                        Text("Remaining: ৳ ${formatMoney(remainingSum)}", fontSize = 11.sp, color = muted)
                                    }

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Text("Rate: ${loan.interestRate}% (${loan.interestType})", fontSize = 11.sp, color = muted)
                                        Text("Tenure: ${loan.durationMonths} Mos", fontSize = 11.sp, color = muted)
                                        Text(
                                            if (isExpanded) "Tap to hide ▲" else "View Schedule (${loanRows.size}) ▼",
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            color = gold
                                        )
                                    }

                                    AnimatedVisibility(visible = isExpanded) {
                                        Column(
                                            modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
                                            verticalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            HorizontalDivider(color = border)
                                            Text("Repayment Schedule", fontWeight = FontWeight.SemiBold, fontSize = 12.sp, color = muted)

                                            if (loanRows.isEmpty()) {
                                                Text("No installment schedule generated.", fontSize = 11.sp, color = muted)
                                            }

                                            loanRows.forEach { row ->
                                                val isOverdue = row.status == "PENDING" && row.dueDate < now
                                                val statusDisplay = if (isOverdue) "OVERDUE" else row.status
                                                val statusColor = when (statusDisplay) {
                                                    "PAID" -> Color(0xFF10B981)
                                                    "OVERDUE" -> Color(0xFFEF4444)
                                                    else -> muted
                                                }

                                                Row(
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .padding(vertical = 4.dp),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Column {
                                                        Text("#${row.installmentNumber} • ${formatDate(row.dueDate)}", fontSize = 12.sp, fontWeight = FontWeight.Medium, color = text)
                                                        Text("৳ ${formatMoney(row.totalAmount)} • $statusDisplay", fontSize = 10.sp, color = statusColor)
                                                    }
                                                    if (statusDisplay in setOf("PENDING", "OVERDUE")) {
                                                        Button(
                                                            onClick = { paymentTarget = row },
                                                            colors = ButtonDefaults.buttonColors(containerColor = gold, contentColor = Color.Black),
                                                            contentPadding = PaddingValues(horizontal = 10.dp, vertical = 2.dp),
                                                            shape = RoundedCornerShape(8.dp)
                                                        ) {
                                                            Text("Pay", fontSize = 11.sp, fontWeight = FontWeight.Bold)
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
                }
            }
        }
    }

    // Modal Calculators & Dialogs
    if (showLoanCalculator) {
        LoanCalculatorDialog(
            isDarkMode = isDark,
            onDismiss = { showLoanCalculator = false },
            onApplyToNewLoan = { principal, rate, tenure, type ->
                showLoanCalculator = false
                showAddLoanDialog = true
            }
        )
    }

    if (showDpsCalculator) {
        DepositCalculatorDialog(
            isDarkMode = isDark,
            onDismiss = { showDpsCalculator = false },
            onApplyToNewDps = { deposit, rate, tenure ->
                showDpsCalculator = false
                showAddDepositDialog = true
            }
        )
    }

    if (showAddLoanDialog) {
        AddLoanAccountDialog(
            onDismiss = { showAddLoanDialog = false },
            onSave = { provider, ref, principal, rate, type, months ->
                viewModel.addLoanAccount(provider, ref, principal, rate, type, months, System.currentTimeMillis()) { ok, msg ->
                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                    if (ok) showAddLoanDialog = false
                }
            }
        )
    }

    if (showAddDepositDialog) {
        AddDepositAccountDialog(
            onDismiss = { showAddDepositDialog = false },
            onSave = { provider, ref, monthly, rate, months ->
                viewModel.addDpsAccount(provider, ref, monthly, rate, months, System.currentTimeMillis()) { ok, msg ->
                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                    if (ok) showAddDepositDialog = false
                }
            }
        )
    }

    paymentTarget?.let { row ->
        RecordInstallmentPaymentModal(
            installment = row,
            onDismiss = { paymentTarget = null },
            onSave = { method, ref ->
                viewModel.payFinanceInstallment(row.id, method, ref) { ok, msg ->
                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                    if (ok) paymentTarget = null
                }
            }
        )
    }
}

// Convenient alias composables for direct navigation calls
@Composable
fun LoanScreen(viewModel: AppViewModel) {
    DpsAndLoansScreen(viewModel = viewModel, initialTab = "LOANS")
}

@Composable
fun DepositScreen(viewModel: AppViewModel) {
    DpsAndLoansScreen(viewModel = viewModel, initialTab = "DPS")
}

// ─────────────────────────────────────────────────────────────────────────────
// INTERACTIVE LOAN EMI CALCULATOR DIALOG
// ─────────────────────────────────────────────────────────────────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LoanCalculatorDialog(
    isDarkMode: Boolean = true,
    onDismiss: () -> Unit,
    onApplyToNewLoan: ((Double, Double, Int, String) -> Unit)? = null
) {
    var principal by remember { mutableFloatStateOf(100000f) }
    var interestRate by remember { mutableFloatStateOf(9.0f) }
    var durationMonths by remember { mutableFloatStateOf(24f) }
    var interestType by remember { mutableStateOf("Reducing") } // "Reducing" or "Flat"

    val gold = Color(0xFFF5C518)
    val card = if (isDarkMode) Color(0xFF13100A) else Color.White
    val container = if (isDarkMode) Color(0xFF1E1A11) else Color(0xFFF1F5F9)
    val border = if (isDarkMode) Color(0xFF382A0B) else Color(0xFFE2E8F0)
    val text = if (isDarkMode) Color(0xFFF8FAFC) else Color(0xFF0F172A)
    val muted = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)

    // Calculate Loan Payments
    val p = principal.toDouble()
    val r = (interestRate / 100.0) / 12.0
    val n = durationMonths.toInt()

    val emi = if (interestType == "Reducing") {
        if (r > 0) (p * r * Math.pow(1 + r, n.toDouble())) / (Math.pow(1 + r, n.toDouble()) - 1)
        else p / n
    } else {
        // Flat Interest Rate: EMI = (P + P * R * T) / N
        val totalFlatInterest = p * (interestRate / 100.0) * (n / 12.0)
        (p + totalFlatInterest) / n
    }

    val totalPayable = emi * n
    val totalInterest = totalPayable - p

    EnterpriseGestureModal(
        onDismissRequest = onDismiss,
        title = "Loan EMI Calculator",
        subtitle = "Calculate monthly installments & interest schedule",
        icon = Icons.Outlined.Calculate
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Calculation Mode Selector (Reducing vs Flat)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(container, RoundedCornerShape(10.dp))
                    .padding(4.dp)
            ) {
                listOf("Reducing" to "Reducing Balance", "Flat" to "Flat Interest").forEach { (type, label) ->
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .height(36.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(if (interestType == type) (if (isDarkMode) Color(0xFF261D07) else Color(0xFFFEF3C7)) else Color.Transparent)
                            .clickable { interestType = type },
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            label,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (interestType == type) gold else muted
                        )
                    }
                }
            }

            // Principal Input & Slider
            Column {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Loan Principal Amount", fontSize = 12.sp, color = muted)
                    Text("৳ ${formatMoney(p)}", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = text)
                }
                Slider(
                    value = principal,
                    onValueChange = { principal = it },
                    valueRange = 10000f..2000000f,
                    steps = 199,
                    colors = SliderDefaults.colors(thumbColor = gold, activeTrackColor = gold)
                )
            }

            // Interest Rate Input & Slider
            Column {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Annual Interest Rate (%)", fontSize = 12.sp, color = muted)
                    Text("${String.format(Locale.US, "%.1f", interestRate)}% p.a.", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = gold)
                }
                Slider(
                    value = interestRate,
                    onValueChange = { interestRate = it },
                    valueRange = 1f..25f,
                    colors = SliderDefaults.colors(thumbColor = gold, activeTrackColor = gold)
                )
            }

            // Tenure Input & Slider
            Column {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Loan Tenure", fontSize = 12.sp, color = muted)
                    Text("$n Months (${n / 12} Years ${n % 12} Mos)", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = text)
                }
                Slider(
                    value = durationMonths,
                    onValueChange = { durationMonths = it },
                    valueRange = 3f..120f,
                    steps = 38,
                    colors = SliderDefaults.colors(thumbColor = gold, activeTrackColor = gold)
                )
            }

            // Output Result Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = container),
                border = BorderStroke(1.dp, border),
                shape = RoundedCornerShape(14.dp)
            ) {
                Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Monthly EMI Payment:", fontWeight = FontWeight.SemiBold, fontSize = 13.sp, color = muted)
                        Text("৳ ${formatMoney(emi)}", fontWeight = FontWeight.ExtraBold, fontSize = 17.sp, color = gold)
                    }
                    HorizontalDivider(color = border)
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Total Interest Payable:", fontSize = 12.sp, color = muted)
                        Text("৳ ${formatMoney(totalInterest)}", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = Color(0xFFEF4444))
                    }
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Total Amount (Principal + Interest):", fontSize = 12.sp, color = muted)
                        Text("৳ ${formatMoney(totalPayable)}", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = text)
                    }
                }
            }

            if (onApplyToNewLoan != null) {
                Button(
                    onClick = { onApplyToNewLoan(p, interestRate.toDouble(), n, interestType) },
                    colors = ButtonDefaults.buttonColors(containerColor = gold, contentColor = Color.Black),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().height(46.dp)
                ) {
                    Text("Save to Active Loans", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// INTERACTIVE DPS / DEPOSIT MATURITY CALCULATOR DIALOG
// ─────────────────────────────────────────────────────────────────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DepositCalculatorDialog(
    isDarkMode: Boolean = true,
    onDismiss: () -> Unit,
    onApplyToNewDps: ((Double, Double, Int) -> Unit)? = null
) {
    var monthlyDeposit by remember { mutableFloatStateOf(5000f) }
    var returnRate by remember { mutableFloatStateOf(8.5f) }
    var durationMonths by remember { mutableFloatStateOf(60f) }

    val gold = Color(0xFFF5C518)
    val card = if (isDarkMode) Color(0xFF13100A) else Color.White
    val container = if (isDarkMode) Color(0xFF1E1A11) else Color(0xFFF1F5F9)
    val border = if (isDarkMode) Color(0xFF382A0B) else Color(0xFFE2E8F0)
    val text = if (isDarkMode) Color(0xFFF8FAFC) else Color(0xFF0F172A)
    val muted = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)

    val monthly = monthlyDeposit.toDouble()
    val rate = returnRate.toDouble()
    val n = durationMonths.toInt()

    val totalDeposited = monthly * n
    // Compound monthly deposit return formula: P * ((1 + r/12)^n - 1) / (r/12)
    val r = (rate / 100.0) / 12.0
    val maturityValue = if (r > 0) {
        monthly * (Math.pow(1 + r, n.toDouble()) - 1) / r * (1 + r)
    } else {
        totalDeposited
    }
    val totalProfit = maturityValue - totalDeposited

    EnterpriseGestureModal(
        onDismissRequest = onDismiss,
        title = "DPS & Deposit Calculator",
        subtitle = "Simulate monthly recurring savings & total maturity return",
        icon = Icons.Outlined.Savings
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Monthly Deposit Slider
            Column {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Monthly Deposit Amount", fontSize = 12.sp, color = muted)
                    Text("৳ ${formatMoney(monthly)}", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = text)
                }
                Slider(
                    value = monthlyDeposit,
                    onValueChange = { monthlyDeposit = it },
                    valueRange = 500f..50000f,
                    steps = 98,
                    colors = SliderDefaults.colors(thumbColor = gold, activeTrackColor = gold)
                )
            }

            // Return Rate Slider
            Column {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Annual Profit / Interest Rate (%)", fontSize = 12.sp, color = muted)
                    Text("${String.format(Locale.US, "%.1f", returnRate)}% p.a.", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = gold)
                }
                Slider(
                    value = returnRate,
                    onValueChange = { returnRate = it },
                    valueRange = 2f..18f,
                    colors = SliderDefaults.colors(thumbColor = gold, activeTrackColor = gold)
                )
            }

            // Duration Slider
            Column {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Deposit Tenure", fontSize = 12.sp, color = muted)
                    Text("$n Months (${n / 12} Years)", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = text)
                }
                Slider(
                    value = durationMonths,
                    onValueChange = { durationMonths = it },
                    valueRange = 12f..120f,
                    steps = 8,
                    colors = SliderDefaults.colors(thumbColor = gold, activeTrackColor = gold)
                )
            }

            // Result Breakdown Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = container),
                border = BorderStroke(1.dp, border),
                shape = RoundedCornerShape(14.dp)
            ) {
                Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Total Deposited Amount:", fontSize = 12.sp, color = muted)
                        Text("৳ ${formatMoney(totalDeposited)}", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = text)
                    }
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Estimated Interest / Profit:", fontSize = 12.sp, color = muted)
                        Text("৳ ${formatMoney(totalProfit)}", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = Color(0xFF10B981))
                    }
                    HorizontalDivider(color = border)
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Estimated Maturity Value:", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = gold)
                        Text("৳ ${formatMoney(maturityValue)}", fontWeight = FontWeight.ExtraBold, fontSize = 17.sp, color = gold)
                    }
                }
            }

            if (onApplyToNewDps != null) {
                Button(
                    onClick = { onApplyToNewDps(monthly, rate, n) },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981), contentColor = Color.White),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().height(46.dp)
                ) {
                    Text("Save to Active DPS Accounts", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DIALOGS: ADD LOAN & ADD DPS
// ─────────────────────────────────────────────────────────────────────────────
@Composable
private fun AddLoanAccountDialog(
    onDismiss: () -> Unit,
    onSave: (String, String, Double, Double, String, Int) -> Unit
) {
    var provider by remember { mutableStateOf("") }
    var reference by remember { mutableStateOf("") }
    var principalStr by remember { mutableStateOf("") }
    var rateStr by remember { mutableStateOf("9.0") }
    var monthsStr by remember { mutableStateOf("24") }
    var interestType by remember { mutableStateOf("Reducing") }
    var error by remember { mutableStateOf<String?>(null) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Add Loan Account", fontWeight = FontWeight.Bold) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(provider, { provider = it }, label = { Text("Bank / Lender Name") }, placeholder = { Text("e.g. BRAC Bank SME") }, singleLine = true)
                OutlinedTextField(reference, { reference = it }, label = { Text("Loan Account / Reference #") }, placeholder = { Text("e.g. LN-2026-001") }, singleLine = true)
                OutlinedTextField(principalStr, { principalStr = it.filter { c -> c.isDigit() || c == '.' } }, label = { Text("Principal Amount (৳)") }, singleLine = true)
                OutlinedTextField(rateStr, { rateStr = it.filter { c -> c.isDigit() || c == '.' } }, label = { Text("Interest Rate (% p.a.)") }, singleLine = true)
                OutlinedTextField(monthsStr, { monthsStr = it.filter(Char::isDigit) }, label = { Text("Duration (Months)") }, singleLine = true)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("Reducing", "Flat").forEach {
                        FilterChip(selected = interestType == it, onClick = { interestType = it }, label = { Text(it) })
                    }
                }
                error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 12.sp) }
            }
        },
        confirmButton = {
            Button(onClick = {
                val principal = principalStr.toDoubleOrNull()
                val rate = rateStr.toDoubleOrNull()
                val months = monthsStr.toIntOrNull()
                if (provider.isBlank() || reference.isBlank() || principal == null || principal <= 0.0 || rate == null || rate < 0.0 || months == null || months <= 0) {
                    error = "Please fill in all fields with valid positive numbers."
                } else {
                    onSave(provider.trim(), reference.trim(), principal, rate, interestType, months)
                }
            }) { Text("Save Loan") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } }
    )
}

@Composable
private fun AddDepositAccountDialog(
    onDismiss: () -> Unit,
    onSave: (String, String, Double, Double, Int) -> Unit
) {
    var provider by remember { mutableStateOf("") }
    var reference by remember { mutableStateOf("") }
    var monthlyStr by remember { mutableStateOf("") }
    var rateStr by remember { mutableStateOf("8.5") }
    var monthsStr by remember { mutableStateOf("60") }
    var error by remember { mutableStateOf<String?>(null) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Add DPS Account", fontWeight = FontWeight.Bold) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(provider, { provider = it }, label = { Text("Bank / Financial Institution") }, placeholder = { Text("e.g. Sonali Bank DPS") }, singleLine = true)
                OutlinedTextField(reference, { reference = it }, label = { Text("DPS Account / Ref Number") }, placeholder = { Text("e.g. DPS-102938") }, singleLine = true)
                OutlinedTextField(monthlyStr, { monthlyStr = it.filter { c -> c.isDigit() || c == '.' } }, label = { Text("Monthly Deposit Amount (৳)") }, singleLine = true)
                OutlinedTextField(rateStr, { rateStr = it.filter { c -> c.isDigit() || c == '.' } }, label = { Text("Profit / Return Rate (% p.a.)") }, singleLine = true)
                OutlinedTextField(monthsStr, { monthsStr = it.filter(Char::isDigit) }, label = { Text("Tenure (Months)") }, singleLine = true)
                error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 12.sp) }
            }
        },
        confirmButton = {
            Button(onClick = {
                val monthly = monthlyStr.toDoubleOrNull()
                val rate = rateStr.toDoubleOrNull()
                val months = monthsStr.toIntOrNull()
                if (provider.isBlank() || reference.isBlank() || monthly == null || monthly <= 0.0 || rate == null || rate < 0.0 || months == null || months <= 0) {
                    error = "Please fill in all fields with valid positive numbers."
                } else {
                    onSave(provider.trim(), reference.trim(), monthly, rate, months)
                }
            }) { Text("Save DPS") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } }
    )
}

@Composable
private fun RecordInstallmentPaymentModal(
    installment: FinanceInstallmentEntity,
    onDismiss: () -> Unit,
    onSave: (String, String) -> Unit
) {
    var method by remember { mutableStateOf("Bank") }
    var reference by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Record Installment #${installment.installmentNumber}") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("৳ ${formatMoney(installment.totalAmount)} due ${formatDate(installment.dueDate)}")
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    listOf("Bank", "Cash", "bKash", "Nagad").forEach {
                        FilterChip(selected = method == it, onClick = { method = it }, label = { Text(it) })
                    }
                }
                OutlinedTextField(
                    reference,
                    { reference = it },
                    label = { Text("Payment / Trx Reference") },
                    placeholder = { Text("e.g. CHQ-991823 or TrxID") },
                    singleLine = true
                )
                error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 12.sp) }
            }
        },
        confirmButton = {
            Button(onClick = {
                if (reference.trim().length < 3) error = "Enter at least 3 characters for payment reference."
                else onSave(method, reference.trim())
            }) { Text("Confirm Paid") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } }
    )
}

private fun formatMoney(value: Double): String = String.format(Locale.US, "%,.2f", value)
