@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)

package com.example.ui

import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.data.local.CustomerEntity
import com.example.data.local.SupplierEntity
import com.example.data.local.LedgerTransactionEntity
import java.util.Locale

@Composable
fun CustomerDetailsView(customer: CustomerEntity, viewModel: AppViewModel, onBack: () -> Unit) {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val allCustomers by viewModel.customers.collectAsState()
    val currentCustomer = allCustomers.find { it.id == customer.id } ?: customer
    val allTransactions by viewModel.ledgerTransactions.collectAsState()
    val customerTxs = remember(allTransactions, currentCustomer.id) {
        allTransactions.filter { it.customerId == currentCustomer.id }
    }

    var showEditDialog by remember { mutableStateOf(false) }
    var showAddLedgerDialog by remember { mutableStateOf(false) }
    var showAddPaymentDialog by remember { mutableStateOf(false) }

    var ledgerAmt by remember { mutableStateOf("") }
    var ledgerNote by remember { mutableStateOf("") }
    var ledgerMethod by remember { mutableStateOf("Cash") }
    var ledgerInvoice by remember { mutableStateOf("") }

    var paymentAmt by remember { mutableStateOf("") }
    var paymentNote by remember { mutableStateOf("") }
    var paymentMethod by remember { mutableStateOf("Cash") }

    var txFilter by remember { mutableStateOf("All Transactions") }

    val bgCanvas = if (isDark) Color(0xFF090806) else Color(0xFFFAFAFC)
    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val goldPrimary = if (isDark) Color(0xFFE5A93C) else Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFFD97706)

    // Real calculations from database
    val totalPurchases = remember(customerTxs, currentCustomer) {
        currentCustomer.openingBalance.coerceAtLeast(0.0) + customerTxs.filter { it.type == "credit" }.sumOf { it.amount }
    }
    val totalPaid = remember(customerTxs) {
        customerTxs.filter { it.type == "payment" }.sumOf { it.amount }
    }
    val currentDue = remember(currentCustomer) {
        currentCustomer.currentBalance.coerceAtLeast(0.0)
    }
    val chargeCount = remember(customerTxs, currentCustomer) {
        customerTxs.count { it.type == "credit" } + (if (currentCustomer.openingBalance > 0.0) 1 else 0)
    }
    val paymentCount = remember(customerTxs) {
        customerTxs.count { it.type == "payment" }
    }

    val filteredTxs = remember(customerTxs, txFilter) {
        when (txFilter) {
            "Charges Only" -> customerTxs.filter { it.type == "credit" }
            "Payments Only" -> customerTxs.filter { it.type == "payment" }
            else -> customerTxs
        }.sortedByDescending { it.date }
    }

    val initials = remember(currentCustomer.name) {
        val parts = currentCustomer.name.trim().split(" ")
        if (parts.size >= 2) {
            "${parts[0].take(1)}${parts[1].take(1)}".uppercase()
        } else {
            currentCustomer.name.take(2).uppercase()
        }
    }

    Scaffold(
        containerColor = bgCanvas,
        topBar = {
            Surface(
                color = bgCanvas,
                shadowElevation = 0.dp
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .statusBarsPadding()
                        .padding(horizontal = 12.dp, vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    IconButton(onClick = onBack) {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back",
                            tint = textPrimary,
                            modifier = Modifier.size(24.dp)
                        )
                    }

                    Text(
                        text = currentCustomer.name,
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = textPrimary,
                        modifier = Modifier.weight(1f).padding(horizontal = 8.dp),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )

                    // Working Edit Button with Edit Icon
                    Surface(
                        onClick = { showEditDialog = true },
                        shape = RoundedCornerShape(10.dp),
                        color = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0),
                        border = BorderStroke(1.2.dp, if (isDark) Color(0xFF8C6212) else Color(0xFFFDE047))
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(Icons.Default.Edit, contentDescription = "Edit Customer", tint = goldText, modifier = Modifier.size(15.dp))
                            Text("Edit", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = goldText)
                        }
                    }
                }
            }
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Spacer(modifier = Modifier.height(2.dp))

            // 1. TOP PROFILE CARD
            Card(
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = cardBg),
                border = BorderStroke(1.dp, cardBorder),
                elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    // Avatar Circle
                    Box(
                        modifier = Modifier
                            .size(64.dp)
                            .clip(CircleShape)
                            .background(if (isDark) Color(0xFF3D2E0B) else Color(0xFFFEF3C7)),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = initials,
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (isDark) Color(0xFFFDE68A) else Color(0xFF78350F)
                        )
                    }

                    // Contact Details
                    Column(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        val cCode = if (currentCustomer.code.isNotBlank()) currentCustomer.code else "C-${currentCustomer.phone.takeLast(4).ifBlank { currentCustomer.id.take(4).uppercase() }}"
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Surface(
                                shape = RoundedCornerShape(6.dp),
                                color = if (isDark) Color(0xFF3D2E0B) else Color(0xFFFEF3C7),
                                border = BorderStroke(1.dp, goldPrimary)
                            ) {
                                Text(
                                    "CODE: $cCode",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = goldText,
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                )
                            }
                            Text(
                                "Customer",
                                fontSize = 12.sp,
                                color = textSecondary,
                                fontWeight = FontWeight.Medium
                            )
                        }

                        if (currentCustomer.phone.isNotBlank()) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Icon(Icons.Outlined.Phone, null, tint = textSecondary, modifier = Modifier.size(15.dp))
                                Text(currentCustomer.phone, fontSize = 13.sp, color = textPrimary, fontWeight = FontWeight.Medium)
                            }
                        }

                        if (!currentCustomer.address.isNullOrBlank()) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Icon(Icons.Outlined.LocationOn, null, tint = textSecondary, modifier = Modifier.size(15.dp))
                                Text(
                                    currentCustomer.address!!,
                                    fontSize = 12.sp,
                                    color = textSecondary,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }
                        }

                        if (!currentCustomer.email.isNullOrBlank()) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Icon(Icons.Outlined.Email, null, tint = textSecondary, modifier = Modifier.size(15.dp))
                                Text(
                                    currentCustomer.email!!,
                                    fontSize = 12.sp,
                                    color = textSecondary,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }
                        }
                    }
                }
            }

            // 2. SUMMARY METRIC CARDS ROW (Responsive & Non-collapsing)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Card 1: Total Purchases
                Card(
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (isDark) Color(0xFF221C0E) else Color(0xFFFFFBEB)
                    ),
                    border = BorderStroke(1.dp, if (isDark) Color(0xFF5E420C) else Color(0xFFFDE68A))
                ) {
                    Column(
                        modifier = Modifier.padding(10.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            Icons.Outlined.ShoppingBag,
                            null,
                            tint = if (isDark) Color(0xFFFACC15) else Color(0xFFD97706),
                            modifier = Modifier.size(18.dp)
                        )
                        Text("Total Sales", fontSize = 10.5.sp, color = textSecondary, fontWeight = FontWeight.Medium, maxLines = 1)
                        Text(
                            "৳ ${String.format(Locale.US, "%,.1f", totalPurchases)}",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text("$chargeCount Entries", fontSize = 10.sp, color = textSecondary, maxLines = 1)
                    }
                }

                // Card 2: Total Paid
                Card(
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (isDark) Color(0xFF0C2318) else Color(0xFFF0FDF4)
                    ),
                    border = BorderStroke(1.dp, if (isDark) Color(0xFF14532D) else Color(0xFFBBF7D0))
                ) {
                    Column(
                        modifier = Modifier.padding(10.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            Icons.Outlined.AccountBalanceWallet,
                            null,
                            tint = if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A),
                            modifier = Modifier.size(18.dp)
                        )
                        Text("Total Paid", fontSize = 10.5.sp, color = textSecondary, fontWeight = FontWeight.Medium, maxLines = 1)
                        Text(
                            "৳ ${String.format(Locale.US, "%,.1f", totalPaid)}",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text("$paymentCount Payments", fontSize = 10.sp, color = textSecondary, maxLines = 1)
                    }
                }

                // Card 3: Current Due
                Card(
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (isDark) Color(0xFF281113) else Color(0xFFFEF2F2)
                    ),
                    border = BorderStroke(1.dp, if (isDark) Color(0xFF7F1D1D) else Color(0xFFFECACA))
                ) {
                    Column(
                        modifier = Modifier.padding(10.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            Icons.Outlined.ReceiptLong,
                            null,
                            tint = if (currentDue > 0) (if (isDark) Color(0xFFF87171) else Color(0xFFDC2626)) else (if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A)),
                            modifier = Modifier.size(18.dp)
                        )
                        Text("Current Due", fontSize = 10.5.sp, color = textSecondary, fontWeight = FontWeight.Medium, maxLines = 1)
                        Text(
                            "৳ ${String.format(Locale.US, "%,.1f", currentDue)}",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (currentDue > 0) (if (isDark) Color(0xFFF87171) else Color(0xFFDC2626)) else (if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A)),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(if (currentDue > 0) "Pending" else "Settled", fontSize = 10.sp, color = textSecondary, maxLines = 1)
                    }
                }
            }

            // 3. ACTION BUTTONS ROW (Clean, Icon-first, Non-collapsing)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                // Button 1: Add Charge
                Surface(
                    onClick = { showAddLedgerDialog = true },
                    modifier = Modifier.weight(1f).height(44.dp),
                    shape = RoundedCornerShape(10.dp),
                    color = cardBg,
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Row(
                        modifier = Modifier.fillMaxSize().padding(horizontal = 8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.Center
                    ) {
                        Icon(
                            Icons.Default.AddCircleOutline,
                            null,
                            tint = goldText,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            "Add Charge",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary,
                            maxLines = 1
                        )
                    }
                }

                // Button 2: Add Payment
                Surface(
                    onClick = { showAddPaymentDialog = true },
                    modifier = Modifier.weight(1f).height(44.dp),
                    shape = RoundedCornerShape(10.dp),
                    color = if (isDark) Color(0xFF1B3825) else Color(0xFFDCFCE7),
                    border = BorderStroke(1.dp, if (isDark) Color(0xFF15803D) else Color(0xFF86EFAC))
                ) {
                    Row(
                        modifier = Modifier.fillMaxSize().padding(horizontal = 8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.Center
                    ) {
                        Icon(
                            Icons.Default.Payment,
                            null,
                            tint = if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A),
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            "Record Payment",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A),
                            maxLines = 1
                        )
                    }
                }
            }

            // 4. TRANSACTION HISTORY SECTION HEADER
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = "Transaction History (${filteredTxs.size})",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary
                )

                var showTxFilterDropdown by remember { mutableStateOf(false) }
                Box {
                    Surface(
                        onClick = { showTxFilterDropdown = true },
                        shape = RoundedCornerShape(10.dp),
                        color = cardBg,
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(
                                Icons.Outlined.Tune,
                                null,
                                tint = textSecondary,
                                modifier = Modifier.size(14.dp)
                            )
                            Text(
                                text = txFilter,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium,
                                color = textPrimary
                            )
                            Icon(
                                Icons.Default.KeyboardArrowDown,
                                null,
                                tint = textSecondary,
                                modifier = Modifier.size(14.dp)
                            )
                        }
                    }
                    DropdownMenu(
                        expanded = showTxFilterDropdown,
                        onDismissRequest = { showTxFilterDropdown = false },
                        modifier = Modifier.background(cardBg)
                    ) {
                        listOf("All Transactions", "Charges Only", "Payments Only").forEach { f ->
                            DropdownMenuItem(
                                text = {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        if (txFilter == f) Icon(Icons.Default.Check, null, tint = goldText, modifier = Modifier.size(14.dp))
                                        else Spacer(Modifier.size(14.dp))
                                        Text(f, color = textPrimary, fontSize = 13.sp)
                                    }
                                },
                                onClick = { txFilter = f; showTxFilterDropdown = false }
                            )
                        }
                    }
                }
            }

            // 5. TRANSACTION HISTORY REAL DATA LIST (No fake data!)
            if (filteredTxs.isEmpty()) {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder),
                    modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp)
                ) {
                    Column(
                        modifier = Modifier.fillMaxWidth().padding(28.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(Icons.Default.ReceiptLong, null, tint = textSecondary, modifier = Modifier.size(42.dp))
                        Text("No transactions found", fontWeight = FontWeight.Bold, color = textPrimary, fontSize = 14.sp)
                        Text(
                            if (txFilter == "All Transactions") "No transactions recorded yet for this customer.\nUse 'Add Charge' or 'Record Payment' above."
                            else "No transactions match the '$txFilter' filter.",
                            color = textSecondary,
                            fontSize = 12.sp,
                            textAlign = androidx.compose.ui.text.style.TextAlign.Center
                        )
                    }
                }
            } else {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.fillMaxWidth()) {
                        filteredTxs.forEachIndexed { idx, tx ->
                            val isPayment = tx.type == "payment"
                            val amtColor = if (isPayment) (if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A)) else (if (isDark) Color(0xFFF87171) else Color(0xFFDC2626))
                            val txTitle = if (isPayment) "Payment Received" else "Invoice / Charge"
                            val txNote = tx.note?.takeIf { it.isNotBlank() } ?: (if (isPayment) "Payment received via ${tx.paymentMethod}" else "Customer Charge")

                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(horizontal = 14.dp, vertical = 12.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Row(
                                    modifier = Modifier.weight(1f).padding(end = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(36.dp)
                                            .clip(CircleShape)
                                            .background(if (isPayment) (if (isDark) Color(0xFF132A1C) else Color(0xFFDCFCE7)) else (if (isDark) Color(0xFF331618) else Color(0xFFFEE2E2))),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            if (isPayment) Icons.Default.ArrowDownward else Icons.Default.ArrowUpward,
                                            null,
                                            tint = amtColor,
                                            modifier = Modifier.size(18.dp)
                                        )
                                    }

                                    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            Text(
                                                txTitle,
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 13.5.sp,
                                                color = textPrimary
                                            )
                                            Surface(
                                                shape = RoundedCornerShape(4.dp),
                                                color = if (isPayment) (if (isDark) Color(0xFF132A1C) else Color(0xFFDCFCE7)) else (if (isDark) Color(0xFF2B200B) else Color(0xFFFEF3C7))
                                            ) {
                                                Text(
                                                    tx.paymentMethod,
                                                    fontSize = 9.5.sp,
                                                    fontWeight = FontWeight.SemiBold,
                                                    color = if (isPayment) (if (isDark) Color(0xFF86EFAC) else Color(0xFF166534)) else goldText,
                                                    modifier = Modifier.padding(horizontal = 5.dp, vertical = 1.dp)
                                                )
                                            }
                                        }
                                        Text(
                                            txNote,
                                            fontSize = 12.sp,
                                            color = textSecondary,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                        Text(
                                            formatDate(tx.date) + " • " + formatTime(tx.date) + (tx.invoiceNo?.let { " • $it" } ?: ""),
                                            fontSize = 10.sp,
                                            color = textSecondary
                                        )
                                    }
                                }

                                Column(horizontalAlignment = Alignment.End) {
                                    Text(
                                        "${if (isPayment) "-" else "+"} ৳ ${String.format(Locale.US, "%,.1f", tx.amount)}",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 14.5.sp,
                                        color = amtColor
                                    )
                                    Surface(
                                        shape = RoundedCornerShape(4.dp),
                                        color = if (isPayment) (if (isDark) Color(0xFF0F2618) else Color(0xFFDCFCE7)) else (if (isDark) Color(0xFF331416) else Color(0xFFFEE2E2))
                                    ) {
                                        Text(
                                            if (isPayment) "PAID" else "DUE",
                                            fontSize = 9.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = if (isPayment) (if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A)) else (if (isDark) Color(0xFFF87171) else Color(0xFFDC2626)),
                                            modifier = Modifier.padding(horizontal = 5.dp, vertical = 1.dp)
                                        )
                                    }
                                }
                            }

                            if (idx < filteredTxs.size - 1) {
                                HorizontalDivider(color = cardBorder.copy(alpha = 0.5f), thickness = 1.dp)
                            }
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(20.dp))
        }

        // Edit Customer Modal Dialog
        if (showEditDialog) {
            var editName by remember { mutableStateOf(currentCustomer.name) }
            var editPhone by remember { mutableStateOf(currentCustomer.phone) }
            var editCode by remember { mutableStateOf(currentCustomer.code) }
            var editAddress by remember { mutableStateOf(currentCustomer.address.orEmpty()) }
            var editEmail by remember { mutableStateOf(currentCustomer.email.orEmpty()) }
            var editError by remember { mutableStateOf<String?>(null) }
            var isSaving by remember { mutableStateOf(false) }

            EnterpriseGestureModal(
                onDismissRequest = { if (!isSaving) showEditDialog = false },
                title = "Edit Customer Details",
                subtitle = "Update contact and ledger account info",
                icon = Icons.Default.Edit
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = editName,
                        onValueChange = { editName = it },
                        label = { Text("Customer Name *") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = editPhone,
                        onValueChange = { editPhone = it },
                        label = { Text("Phone Number") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = editCode,
                        onValueChange = { editCode = it.uppercase() },
                        label = { Text("Customer Code") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = editAddress,
                        onValueChange = { editAddress = it },
                        label = { Text("Address") },
                        maxLines = 2,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = editEmail,
                        onValueChange = { editEmail = it },
                        label = { Text("Email Address") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    if (editError != null) {
                        Text(editError!!, color = Color(0xFFEF4444), fontSize = 12.sp)
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    Button(
                        onClick = {
                            if (editName.isBlank()) {
                                editError = "Customer name is required"
                                return@Button
                            }
                            isSaving = true
                            val updated = currentCustomer.copy(
                                name = editName.trim(),
                                phone = editPhone.trim(),
                                code = editCode.trim(),
                                address = editAddress.trim().ifBlank { null },
                                email = editEmail.trim().ifBlank { null }
                            )
                            viewModel.updateCustomer(updated) { ok, msg ->
                                isSaving = false
                                if (ok) {
                                    Toast.makeText(context, "Customer updated successfully", Toast.LENGTH_SHORT).show()
                                    showEditDialog = false
                                } else {
                                    editError = msg
                                }
                            }
                        },
                        enabled = !isSaving,
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = goldPrimary,
                            contentColor = Color.Black
                        )
                    ) {
                        Text("Save Changes", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    }
                }
            }
        }

        // Add Ledger / Charge Modal Dialog
        if (showAddLedgerDialog) {
            var errorMsg by remember { mutableStateOf<String?>(null) }
            EnterpriseGestureModal(
                onDismissRequest = { showAddLedgerDialog = false },
                title = "Add Customer Charge",
                subtitle = "Record a new sale, invoice or bill",
                icon = Icons.Default.Receipt
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = ledgerAmt,
                        onValueChange = { ledgerAmt = it.filter { ch -> ch.isDigit() || ch == '.' } },
                        label = { Text("Charge Amount (৳) *") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = ledgerNote,
                        onValueChange = { ledgerNote = it },
                        label = { Text("Item / Description / Note") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = ledgerInvoice,
                        onValueChange = { ledgerInvoice = it },
                        label = { Text("Invoice No (Optional)") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        listOf("Cash", "bKash", "Nagad", "Bank").forEach { m ->
                            FilterChip(
                                selected = ledgerMethod == m,
                                onClick = { ledgerMethod = m },
                                label = { Text(m, fontSize = 11.sp) }
                            )
                        }
                    }
                    if (errorMsg != null) {
                        Text(errorMsg!!, color = Color(0xFFEF4444), fontSize = 12.sp)
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    Button(
                        onClick = {
                            val amt = ledgerAmt.toDoubleOrNull() ?: 0.0
                            if (amt <= 0.0) {
                                errorMsg = "Enter a valid positive amount"
                                return@Button
                            }
                            viewModel.addLedgerTransaction(
                                customerId = currentCustomer.id,
                                supplierId = null,
                                type = "credit",
                                amount = amt,
                                note = ledgerNote.ifBlank { "Charge: $ledgerMethod" },
                                paymentMethod = ledgerMethod,
                                onResult = { ok, msg ->
                                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                    if (ok) {
                                        ledgerAmt = ""; ledgerNote = ""; ledgerInvoice = ""
                                        showAddLedgerDialog = false
                                    }
                                }
                            )
                        },
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = goldPrimary,
                            contentColor = Color.Black
                        )
                    ) {
                        Text("Save Charge", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    }
                }
            }
        }

        // Add Payment Modal Dialog
        if (showAddPaymentDialog) {
            var errorMsg by remember { mutableStateOf<String?>(null) }
            EnterpriseGestureModal(
                onDismissRequest = { showAddPaymentDialog = false },
                title = "Record Customer Payment",
                subtitle = "Record cash or digital collection received",
                icon = Icons.Outlined.AccountBalanceWallet
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = paymentAmt,
                        onValueChange = { paymentAmt = it.filter { ch -> ch.isDigit() || ch == '.' } },
                        label = { Text("Payment Received (৳) *") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = paymentNote,
                        onValueChange = { paymentNote = it },
                        label = { Text("Payment Note / Reference") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        listOf("Cash", "bKash", "Nagad", "Bank").forEach { m ->
                            FilterChip(
                                selected = paymentMethod == m,
                                onClick = { paymentMethod = m },
                                label = { Text(m, fontSize = 11.sp) }
                            )
                        }
                    }
                    if (errorMsg != null) {
                        Text(errorMsg!!, color = Color(0xFFEF4444), fontSize = 12.sp)
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    Button(
                        onClick = {
                            val amt = paymentAmt.toDoubleOrNull() ?: 0.0
                            if (amt <= 0.0) {
                                errorMsg = "Enter a valid positive amount"
                                return@Button
                            }
                            viewModel.addLedgerTransaction(
                                customerId = currentCustomer.id,
                                supplierId = null,
                                type = "payment",
                                amount = amt,
                                note = paymentNote.ifBlank { "Payment via $paymentMethod" },
                                paymentMethod = paymentMethod,
                                onResult = { ok, msg ->
                                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                    if (ok) {
                                        paymentAmt = ""; paymentNote = ""
                                        showAddPaymentDialog = false
                                    }
                                }
                            )
                        },
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A),
                            contentColor = Color.White
                        )
                    ) {
                        Text("Save Payment", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    }
                }
            }
        }
    }
}

@Composable
fun SupplierDetailsView(supplier: SupplierEntity, viewModel: AppViewModel, onBack: () -> Unit) {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val allSuppliers by viewModel.suppliers.collectAsState()
    val currentSupplier = allSuppliers.find { it.id == supplier.id } ?: supplier
    val txs by viewModel.ledgerTransactions.collectAsState()
    val supplierTxs = remember(txs, currentSupplier.id) {
        txs.filter { it.supplierId == currentSupplier.id }.sortedByDescending { it.date }
    }
    
    var showEditDialog by remember { mutableStateOf(false) }
    var showTxDialog by remember { mutableStateOf(false) }
    var txType by remember { mutableStateOf("credit") }
    var txAmount by remember { mutableStateOf("") }
    var txNote by remember { mutableStateOf("") }
    var txMethod by remember { mutableStateOf("Cash") }

    val sCode = if (currentSupplier.code.isNotBlank()) currentSupplier.code else "S-${currentSupplier.phone.takeLast(4).ifBlank { currentSupplier.id.take(4).uppercase() }}"

    val totalPurchases = remember(supplierTxs, currentSupplier) {
        (if (currentSupplier.openingBalance < 0) Math.abs(currentSupplier.openingBalance) else 0.0) +
            supplierTxs.filter { it.type == "credit" }.sumOf { it.amount }
    }
    val totalPaid = remember(supplierTxs) {
        supplierTxs.filter { it.type == "payment" }.sumOf { it.amount }
    }
    val currentPayable = remember(currentSupplier) {
        if (currentSupplier.currentBalance < 0) Math.abs(currentSupplier.currentBalance) else 0.0
    }

    Scaffold(
        containerColor = AppScreenBg,
        topBar = {
            GradientTopBar(
                title = currentSupplier.name,
                subtitle = "[$sCode] • ${currentSupplier.phone} • Supplier Ledger",
                onBack = onBack,
                gradient = GradPrimary,
                actions = {
                    IconButton(onClick = { showEditDialog = true }) {
                        Icon(
                            Icons.Default.Edit,
                            contentDescription = "Edit Supplier",
                            tint = if (isDark) Color(0xFFF5C518) else Color(0xFFD97706)
                        )
                    }
                }
            )
        }
    ) { padding ->
        Column(modifier = Modifier.padding(padding).padding(16.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            // Summary Cards Row (Responsive & Non-collapsing)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Card 1: Total Purchases
                Card(
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (isDark) Color(0xFF221C0E) else Color(0xFFFFFBEB)
                    ),
                    border = BorderStroke(1.dp, if (isDark) Color(0xFF5E420C) else Color(0xFFFDE68A))
                ) {
                    Column(
                        modifier = Modifier.padding(10.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            Icons.Outlined.ShoppingBag,
                            null,
                            tint = if (isDark) Color(0xFFFACC15) else Color(0xFFD97706),
                            modifier = Modifier.size(18.dp)
                        )
                        Text("Total Purchases", fontSize = 10.sp, color = AppTextSecondary, fontWeight = FontWeight.Medium, maxLines = 1)
                        Text(
                            "৳ ${String.format(Locale.US, "%,.1f", totalPurchases)}",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTextPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text("${supplierTxs.count { it.type == "credit" }} Bills", fontSize = 10.sp, color = AppTextSecondary, maxLines = 1)
                    }
                }

                // Card 2: Total Paid
                Card(
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (isDark) Color(0xFF0C2318) else Color(0xFFF0FDF4)
                    ),
                    border = BorderStroke(1.dp, if (isDark) Color(0xFF14532D) else Color(0xFFBBF7D0))
                ) {
                    Column(
                        modifier = Modifier.padding(10.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            Icons.Outlined.AccountBalanceWallet,
                            null,
                            tint = if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A),
                            modifier = Modifier.size(18.dp)
                        )
                        Text("Total Paid", fontSize = 10.sp, color = AppTextSecondary, fontWeight = FontWeight.Medium, maxLines = 1)
                        Text(
                            "৳ ${String.format(Locale.US, "%,.1f", totalPaid)}",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTextPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text("${supplierTxs.count { it.type == "payment" }} Paid", fontSize = 10.sp, color = AppTextSecondary, maxLines = 1)
                    }
                }

                // Card 3: Current Payable
                Card(
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (isDark) Color(0xFF281113) else Color(0xFFFEF2F2)
                    ),
                    border = BorderStroke(1.dp, if (isDark) Color(0xFF7F1D1D) else Color(0xFFFECACA))
                ) {
                    Column(
                        modifier = Modifier.padding(10.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            Icons.Outlined.ReceiptLong,
                            null,
                            tint = if (currentPayable > 0) (if (isDark) Color(0xFFF87171) else Color(0xFFDC2626)) else (if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A)),
                            modifier = Modifier.size(18.dp)
                        )
                        Text("Payable", fontSize = 10.sp, color = AppTextSecondary, fontWeight = FontWeight.Medium, maxLines = 1)
                        Text(
                            "৳ ${String.format(Locale.US, "%,.1f", currentPayable)}",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (currentPayable > 0) (if (isDark) Color(0xFFF87171) else Color(0xFFDC2626)) else (if (isDark) Color(0xFF4ADE80) else Color(0xFF16A34A)),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(if (currentPayable > 0) "We Owe" else "Settled", fontSize = 10.sp, color = AppTextSecondary, maxLines = 1)
                    }
                }
            }

            // Quick deposit/credit buttons (Clean & Non-collapsing)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Button(
                    onClick = { txType = "payment"; showTxDialog = true },
                    modifier = Modifier.weight(1f).height(44.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = SuccessGreen)
                ) {
                    Icon(Icons.Default.Payment, null, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Pay Supplier", fontWeight = FontWeight.Bold, fontSize = 13.sp, maxLines = 1)
                }
                Button(
                    onClick = { txType = "credit"; showTxDialog = true },
                    modifier = Modifier.weight(1f).height(44.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = ErrorRed)
                ) {
                    Icon(Icons.Default.AddCircleOutline, null, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Add Purchase", fontWeight = FontWeight.Bold, fontSize = 13.sp, maxLines = 1)
                }
            }

            Text("লেনদেন বিবরণী (Ledger Entries)", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = AppTextPrimary)

            if (supplierTxs.isEmpty()) {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = AppCardBg),
                    border = BorderStroke(1.dp, AppCardBorderColor),
                    modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp)
                ) {
                    Column(
                        modifier = Modifier.fillMaxWidth().padding(28.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(Icons.Default.ReceiptLong, null, tint = AppTextSecondary, modifier = Modifier.size(42.dp))
                        Text("No transactions found", fontWeight = FontWeight.Bold, color = AppTextPrimary, fontSize = 14.sp)
                        Text(
                            "No purchase or payment records found for this supplier.\nUse the buttons above to record a transaction.",
                            color = AppTextSecondary,
                            fontSize = 12.sp,
                            textAlign = androidx.compose.ui.text.style.TextAlign.Center
                        )
                    }
                }
            } else {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    supplierTxs.forEach { tx ->
                        EnterpriseCard {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(modifier = Modifier.weight(1f).padding(end = 8.dp)) {
                                    val txLabel = if (tx.type == "credit") "পণ্য ক্রয় (Purchase / Bill)" else "মূল্য পরিশোধ (Cash Paid)"
                                    Text(txLabel, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = AppTextPrimary)
                                    if (!tx.note.isNullOrEmpty()) {
                                        Text(tx.note, fontSize = 12.sp, color = AppTextSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    }
                                    Text(formatDate(tx.date) + " • " + formatTime(tx.date) + " • " + tx.paymentMethod, fontSize = 10.sp, color = AppTextSecondary)
                                }
                                val amtColor = if (tx.type == "credit") ErrorRed else SuccessGreen
                                val amtSign = if (tx.type == "credit") "+" else "-"
                                Text("$amtSign ৳${String.format(Locale.US, "%,.1f", tx.amount)}", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = amtColor)
                            }
                        }
                    }
                }
            }
        }

        // Edit Supplier Modal Dialog
        if (showEditDialog) {
            var editName by remember { mutableStateOf(currentSupplier.name) }
            var editPhone by remember { mutableStateOf(currentSupplier.phone) }
            var editCode by remember { mutableStateOf(currentSupplier.code) }
            var editAddress by remember { mutableStateOf(currentSupplier.address.orEmpty()) }
            var editEmail by remember { mutableStateOf(currentSupplier.email.orEmpty()) }
            var editError by remember { mutableStateOf<String?>(null) }
            var isSaving by remember { mutableStateOf(false) }

            EnterpriseGestureModal(
                onDismissRequest = { if (!isSaving) showEditDialog = false },
                title = "Edit Supplier Details",
                subtitle = "Update contact and ledger account info",
                icon = Icons.Default.Edit
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = editName,
                        onValueChange = { editName = it },
                        label = { Text("Supplier Name *") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = editPhone,
                        onValueChange = { editPhone = it },
                        label = { Text("Phone Number") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = editCode,
                        onValueChange = { editCode = it.uppercase() },
                        label = { Text("Supplier Code") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = editAddress,
                        onValueChange = { editAddress = it },
                        label = { Text("Address") },
                        maxLines = 2,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = editEmail,
                        onValueChange = { editEmail = it },
                        label = { Text("Email Address") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    if (editError != null) {
                        Text(editError!!, color = Color(0xFFEF4444), fontSize = 12.sp)
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    Button(
                        onClick = {
                            if (editName.isBlank()) {
                                editError = "Supplier name is required"
                                return@Button
                            }
                            isSaving = true
                            val updated = currentSupplier.copy(
                                name = editName.trim(),
                                phone = editPhone.trim(),
                                code = editCode.trim(),
                                address = editAddress.trim().ifBlank { null },
                                email = editEmail.trim().ifBlank { null }
                            )
                            viewModel.updateSupplier(updated) { ok, msg ->
                                isSaving = false
                                if (ok) {
                                    Toast.makeText(context, "Supplier updated successfully", Toast.LENGTH_SHORT).show()
                                    showEditDialog = false
                                } else {
                                    editError = msg
                                }
                            }
                        },
                        enabled = !isSaving,
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = BrandPurple,
                            contentColor = Color.White
                        )
                    ) {
                        Text("Save Changes", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    }
                }
            }
        }

        // Add Transaction Dialog (Payment / Purchase)
        if (showTxDialog) {
            var errorMsg by remember { mutableStateOf<String?>(null) }
            EnterpriseGestureModal(
                onDismissRequest = { showTxDialog = false },
                title = if (txType == "credit") "মহাজন থেকে ক্রয় (Purchase)" else "মহাজনকে মূল্য পরিশোধ (Payment)",
                subtitle = "Record supplier ledger transaction",
                icon = Icons.Default.Receipt
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = txAmount,
                        onValueChange = { txAmount = it.filter { ch -> ch.isDigit() || ch == '.' } },
                        label = { Text("Amount (টাকা) *") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = txNote,
                        onValueChange = { txNote = it },
                        label = { Text("Note / বিবরণ") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        listOf("Cash", "bKash", "Nagad", "Bank").forEach { m ->
                            FilterChip(
                                selected = txMethod == m,
                                onClick = { txMethod = m },
                                label = { Text(m, fontSize = 11.sp) }
                            )
                        }
                    }
                    if (errorMsg != null) {
                        Text(errorMsg!!, color = Color(0xFFEF4444), fontSize = 12.sp)
                    }
                    Spacer(modifier = Modifier.height(6.dp))
                    Button(
                        onClick = {
                            val amtVal = txAmount.toDoubleOrNull() ?: 0.0
                            if (amtVal <= 0.0) {
                                errorMsg = "Enter a valid positive amount"
                                return@Button
                            }
                            viewModel.addLedgerTransaction(
                                customerId = null,
                                supplierId = currentSupplier.id,
                                type = txType,
                                amount = amtVal,
                                note = txNote.ifBlank { if (txType == "credit") "Purchase via $txMethod" else "Payment via $txMethod" },
                                paymentMethod = txMethod,
                                onResult = { ok, msg ->
                                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                    if (ok) {
                                        txAmount = ""; txNote = ""
                                        showTxDialog = false
                                    }
                                }
                            )
                        },
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (txType == "credit") ErrorRed else SuccessGreen,
                            contentColor = Color.White
                        )
                    ) {
                        Text("Save Entry", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    }
                }
            }
        }
    }
}
