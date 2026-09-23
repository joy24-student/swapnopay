package com.example.ui

import android.content.Intent
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.data.local.CustomerEntity
import com.example.data.local.SupplierEntity
import com.example.data.local.LedgerTransactionEntity
import java.text.SimpleDateFormat
import java.util.*

private data class LedgerPartyRow(
    val id: String,
    val name: String,
    val phone: String,
    val code: String = "",
    val openingBalance: Double,
    val currentBalance: Double,
    val createdAt: Long
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProductionLedgersScreen(viewModel: AppViewModel, initialTab: String = "CUSTOMER") {
    val context = LocalContext.current
    val customers by viewModel.customers.collectAsState()
    val suppliers by viewModel.suppliers.collectAsState()
    val transactions by viewModel.ledgerTransactions.collectAsState()
    val profile by viewModel.activeProfile.collectAsState()
    val dark by viewModel.isDarkMode.collectAsState()
    val language by viewModel.language.collectAsState()
    val isBangla = language == "Bangla"
    var tab by rememberSaveable(initialTab) { mutableStateOf(initialTab) }
    var range by rememberSaveable { mutableStateOf("ALL") }
    var query by rememberSaveable { mutableStateOf("") }
    var dueFilter by rememberSaveable { mutableStateOf("ALL") } // "ALL" or "DUES_ONLY"
    var sort by rememberSaveable { mutableStateOf("BALANCE_DESC") }
    var sortExpanded by remember { mutableStateOf(false) }
    var expandedParty by rememberSaveable { mutableStateOf<String?>(null) }
    var entryParty by remember { mutableStateOf<LedgerPartyRow?>(null) }
    var entryType by remember { mutableStateOf("payment") }
    var selectedCustomerForDetail by remember { mutableStateOf<CustomerEntity?>(null) }
    var selectedSupplierForDetail by remember { mutableStateOf<SupplierEntity?>(null) }
    var showAddPartyDialog by remember { mutableStateOf(false) }
    val cutoff = remember(range) { ledgerRangeCutoff(range) }
    val scopedTransactions = remember(transactions, cutoff) { transactions.filter { cutoff == null || it.date >= cutoff } }
    val baseParties = remember(customers, suppliers, tab) {
        if (tab == "CUSTOMER") customers.map {
            val cCode = it.code.ifBlank { "C-${it.phone.takeLast(4).ifBlank { it.id.take(4).uppercase() }}" }
            LedgerPartyRow(it.id, it.name, it.phone, cCode, it.openingBalance, it.currentBalance, it.createdAt)
        }
        else suppliers.map {
            val sCode = it.code.ifBlank { "S-${it.phone.takeLast(4).ifBlank { it.id.take(4).uppercase() }}" }
            LedgerPartyRow(it.id, it.name, it.phone, sCode, it.openingBalance, it.currentBalance, it.createdAt)
        }
    }
    val visibleParties = remember(baseParties, scopedTransactions, query, sort, cutoff, tab, dueFilter) {
        baseParties.filter { party ->
            val matchesSearch = query.isBlank() ||
                party.name.contains(query, true) ||
                party.phone.contains(query) ||
                party.code.contains(query, true)
            val hasActivity = cutoff == null || party.createdAt >= cutoff || scopedTransactions.any {
                if (tab == "CUSTOMER") it.customerId == party.id else it.supplierId == party.id
            }
            val matchesDue = if (dueFilter == "DUES_ONLY") {
                if (tab == "CUSTOMER") {
                    party.currentBalance > 0.01 || scopedTransactions.any { it.customerId == party.id && it.type == "credit" }
                } else {
                    party.currentBalance < -0.01 || scopedTransactions.any { it.supplierId == party.id && it.type == "credit" }
                }
            } else {
                true
            }
            matchesSearch && hasActivity && matchesDue
        }.let { rows ->
            when (sort) {
                "NAME" -> rows.sortedBy { it.name.lowercase() }
                "BALANCE_ASC" -> rows.sortedBy { kotlin.math.abs(it.currentBalance) }
                else -> rows.sortedByDescending { kotlin.math.abs(it.currentBalance) }
            }
        }
    }
    val visibleIds = remember(visibleParties) { visibleParties.mapTo(mutableSetOf()) { it.id } }
    val visibleTransactions = remember(scopedTransactions, visibleIds, tab) {
        scopedTransactions.filter { if (tab == "CUSTOMER") it.customerId in visibleIds else it.supplierId in visibleIds }
    }
    val outstanding = visibleParties.sumOf { party ->
        if (tab == "CUSTOMER") party.currentBalance.coerceAtLeast(0.0) else (-party.currentBalance).coerceAtLeast(0.0)
    }
    val paidInRange = visibleTransactions.filter { it.type == "payment" }.sumOf { it.amount }
    val chargedInRange = visibleTransactions.filter { it.type == "credit" }.sumOf { it.amount }
    val bg = if (dark) Color(0xFF070707) else Color(0xFFF8FAFC)
    val card = if (dark) Color(0xFF13100A) else Color.White
    val border = if (dark) Color(0xFF382A0B) else Color(0xFFE2E8F0)
    val text = if (dark) Color.White else Color(0xFF0F172A)
    val muted = if (dark) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val accent = Color(0xFFF5C518)

    val exportLauncher = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("text/csv")) { uri ->
        if (uri != null) runCatching {
            context.contentResolver.openOutputStream(uri)?.bufferedWriter(Charsets.UTF_8)?.use { writer ->
                writer.appendLine("party_type,party_id,code,name,phone,opening_balance,current_balance,transaction_id,date,type,amount,payment_method,invoice_no,note")
                visibleParties.forEach { party ->
                    val partyTx = visibleTransactions.filter { if (tab == "CUSTOMER") it.customerId == party.id else it.supplierId == party.id }
                    if (partyTx.isEmpty()) {
                        writer.appendLine(listOf(tab, party.id, party.code, party.name, party.phone, party.openingBalance, party.currentBalance, "", "", "", "", "", "", "").joinToString(",") { ledgerCsv(it.toString()) })
                    } else partyTx.forEach { tx ->
                        writer.appendLine(listOf(tab, party.id, party.code, party.name, party.phone, party.openingBalance, party.currentBalance,
                            tx.id, ledgerIso(tx.date), tx.type, tx.amount, tx.paymentMethod, tx.invoiceNo.orEmpty(), tx.note.orEmpty())
                            .joinToString(",") { ledgerCsv(it.toString()) })
                    }
                }
            } ?: error("Unable to open the selected file")
        }.onSuccess { Toast.makeText(context, "Ledger CSV exported", Toast.LENGTH_LONG).show() }
            .onFailure { Toast.makeText(context, it.localizedMessage ?: "Export failed", Toast.LENGTH_LONG).show() }
    }

    val currentSelectedCustomer = selectedCustomerForDetail?.let { sel ->
        customers.find { it.id == sel.id } ?: sel
    }
    val currentSelectedSupplier = selectedSupplierForDetail?.let { sel ->
        suppliers.find { it.id == sel.id } ?: sel
    }

    if (currentSelectedCustomer != null) {
        CustomerDetailsView(
            customer = currentSelectedCustomer,
            viewModel = viewModel,
            onBack = { selectedCustomerForDetail = null }
        )
        return
    }

    if (currentSelectedSupplier != null) {
        SupplierDetailsView(
            supplier = currentSelectedSupplier,
            viewModel = viewModel,
            onBack = { selectedSupplierForDetail = null }
        )
        return
    }

    Scaffold(
        containerColor = bg,
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            if (tab == "CUSTOMER") {
                                if (isBangla) "কাস্টমার ডিরেক্টরি ও লেজার" else "Customer Directory & Ledger"
                            } else {
                                if (isBangla) "সাপ্লায়ার ডিরেক্টরি ও লেজার" else "Supplier Directory & Ledger"
                            },
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            if (isBangla) "সকল কাস্টমার ও সাপ্লায়ারদের লেজার হিসাব" else "Tenant-isolated account statement",
                            fontSize = 11.sp,
                            color = muted
                        )
                    }
                },
                navigationIcon = { IconButton(onClick = viewModel::goBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back") } },
                actions = {
                    IconButton(onClick = { showAddPartyDialog = true }) { Icon(Icons.Default.PersonAdd, "Add ${if (tab == "CUSTOMER") "Customer" else "Supplier"}", tint = accent) }
                    IconButton(onClick = { viewModel.navigateTo("SmsGateway") }) { Icon(Icons.Default.Sms, "SMS Due Reminder") }
                    IconButton(onClick = { viewModel.triggerSync() }) { Icon(Icons.Default.Sync, "Sync") }
                    IconButton(
                        onClick = { exportLauncher.launch("${tab.lowercase()}-ledger-${ledgerIso(System.currentTimeMillis())}.csv") },
                        enabled = visibleParties.isNotEmpty()
                    ) { Icon(Icons.Default.FileDownload, "Export CSV") }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = card, titleContentColor = text)
            )
        }
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding).padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            SingleChoiceSegmentedButtonRow(Modifier.fillMaxWidth()) {
                listOf("CUSTOMER", "SUPPLIER").forEachIndexed { index, value ->
                    SegmentedButton(
                        selected = tab == value,
                        onClick = { tab = value; expandedParty = null },
                        shape = SegmentedButtonDefaults.itemShape(index, 2)
                    ) {
                        Text(
                            if (value == "CUSTOMER") {
                                if (isBangla) "কাস্টমার (${customers.size})" else "All Customers (${customers.size})"
                            } else {
                                if (isBangla) "সাপ্লায়ার (${suppliers.size})" else "All Suppliers (${suppliers.size})"
                            },
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                val countWithDue = remember(baseParties, tab) {
                    baseParties.count { p ->
                        if (tab == "CUSTOMER") p.currentBalance > 0.01 else p.currentBalance < -0.01
                    }
                }

                // "All" pill
                Surface(
                    modifier = Modifier
                        .height(34.dp)
                        .clickable { dueFilter = "ALL" },
                    shape = RoundedCornerShape(50),
                    color = if (dueFilter == "ALL") {
                        if (dark) Color(0xFF1C180E) else Color(0xFFFFFDE7)
                    } else {
                        if (dark) Color(0xFF1E1E1E) else Color(0xFFF1F5F9)
                    },
                    border = BorderStroke(
                        1.5.dp,
                        if (dueFilter == "ALL") accent else if (dark) Color(0xFF2D2D2D) else Color(0xFFE2E8F0)
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        if (dueFilter == "ALL") Icon(Icons.Default.Check, null, tint = accent, modifier = Modifier.size(12.dp))
                        Text(
                            if (isBangla) "সকল (${baseParties.size})" else "All (${baseParties.size})",
                            fontSize = 11.5.sp,
                            fontWeight = if (dueFilter == "ALL") FontWeight.Bold else FontWeight.Normal,
                            color = if (dueFilter == "ALL") accent else if (dark) Color(0xFF94A3B8) else Color(0xFF64748B)
                        )
                    }
                }

                // "Dues Only" pill
                Surface(
                    modifier = Modifier
                        .height(34.dp)
                        .clickable { dueFilter = "DUES_ONLY" },
                    shape = RoundedCornerShape(50),
                    color = if (dueFilter == "DUES_ONLY") {
                        if (dark) Color(0xFF3B1E1E) else Color(0xFFFEE2E2)
                    } else {
                        if (dark) Color(0xFF1E1E1E) else Color(0xFFF1F5F9)
                    },
                    border = BorderStroke(
                        1.5.dp,
                        if (dueFilter == "DUES_ONLY") {
                            if (dark) Color(0xFFF87171) else Color(0xFFDC2626)
                        } else if (dark) Color(0xFF2D2D2D) else Color(0xFFE2E8F0)
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        if (dueFilter == "DUES_ONLY") Icon(Icons.Default.Check, null, tint = if (dark) Color(0xFFF87171) else Color(0xFFDC2626), modifier = Modifier.size(12.dp))
                        Text(
                            if (tab == "CUSTOMER") {
                                if (isBangla) "শুধু বাকি ($countWithDue)" else "Only Dues ($countWithDue)"
                            } else {
                                if (isBangla) "শুধু প্রদেয় ($countWithDue)" else "Only Payables ($countWithDue)"
                            },
                            fontSize = 11.5.sp,
                            fontWeight = if (dueFilter == "DUES_ONLY") FontWeight.Bold else FontWeight.Normal,
                            color = if (dueFilter == "DUES_ONLY") {
                                if (dark) Color(0xFFF87171) else Color(0xFFDC2626)
                            } else if (dark) Color(0xFF94A3B8) else Color(0xFF64748B)
                        )
                    }
                }

                Box(modifier = Modifier.width(1.dp).height(20.dp).background(if (dark) Color(0xFF333333) else Color(0xFFE2E8F0)))

                // Range Filter
                listOf("ALL" to (if (isBangla) "সব" else "All"), "TODAY" to (if (isBangla) "আজ" else "Today"), "7_DAYS" to "7D", "MONTH" to (if (isBangla) "মাস" else "1M")).forEach { (value, label) ->
                    FilterChip(
                        selected = range == value,
                        onClick = { range = value },
                        label = { Text(label, fontSize = 11.sp) }
                    )
                }
            }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                LedgerMetric("Outstanding", outstanding, card, border, text, muted, Modifier.weight(1f))
                LedgerMetric("Paid", paidInRange, card, border, text, muted, Modifier.weight(1f))
                LedgerMetric("Charged", chargedInRange, card, border, text, muted, Modifier.weight(1f))
            }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                OutlinedTextField(
                    value = query, onValueChange = { query = it }, modifier = Modifier.weight(1f), singleLine = true,
                    leadingIcon = { Icon(Icons.Default.Search, null) },
                    label = { Text(if (tab == "CUSTOMER") "Search customer name, phone, code" else "Search supplier name, phone, code") }
                )
                Box {
                    OutlinedButton(onClick = { sortExpanded = true }) { Icon(Icons.Default.Sort, null); Text("Sort") }
                    DropdownMenu(expanded = sortExpanded, onDismissRequest = { sortExpanded = false }) {
                        listOf("BALANCE_DESC" to "Highest balance", "BALANCE_ASC" to "Lowest balance", "NAME" to "Name A-Z").forEach { (value, label) ->
                            DropdownMenuItem(text = { Text(label) }, onClick = { sort = value; sortExpanded = false })
                        }
                    }
                }
            }
            if (visibleParties.isEmpty()) {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Default.MenuBook, null, tint = muted, modifier = Modifier.size(54.dp))
                        Text("No ledger accounts match this filter", color = text, fontWeight = FontWeight.Bold)
                        Text("Add a customer or supplier using the + icon above.", color = muted, fontSize = 12.sp)
                    }
                }
            } else LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp), contentPadding = PaddingValues(bottom = 24.dp)) {
                items(visibleParties, key = { it.id }) { party ->
                    val partyTransactions = visibleTransactions.filter { if (tab == "CUSTOMER") it.customerId == party.id else it.supplierId == party.id }
                    val due = if (tab == "CUSTOMER") party.currentBalance.coerceAtLeast(0.0) else (-party.currentBalance).coerceAtLeast(0.0)
                    Card(
                        modifier = Modifier.fillMaxWidth().clickable { expandedParty = if (expandedParty == party.id) null else party.id },
                        colors = CardDefaults.cardColors(containerColor = card), border = BorderStroke(1.dp, border), shape = RoundedCornerShape(16.dp)
                    ) {
                        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                        Text(
                                            party.name,
                                            color = text,
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 15.sp,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis,
                                            modifier = Modifier.weight(1f, fill = false)
                                        )
                                        if (party.code.isNotBlank()) {
                                            Surface(
                                                shape = RoundedCornerShape(4.dp),
                                                color = if (dark) Color(0xFF382A0B) else Color(0xFFFEF3C7),
                                                border = BorderStroke(1.dp, if (dark) Color(0xFF785E15) else Color(0xFFFDE68A))
                                            ) {
                                                Text(
                                                    party.code,
                                                    fontSize = 10.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = if (dark) Color(0xFFFACC15) else Color(0xFFB45309),
                                                    modifier = Modifier.padding(horizontal = 5.dp, vertical = 1.dp)
                                                )
                                            }
                                        }
                                    }
                                    Text(party.phone, color = muted, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                }
                                Spacer(Modifier.width(8.dp))
                                Column(horizontalAlignment = Alignment.End) {
                                    Text("BDT ${ledgerMoney(due)}", color = if (due > 0) Color(0xFFEF4444) else Color(0xFF10B981), fontWeight = FontWeight.Bold, fontSize = 14.sp, maxLines = 1)
                                    Text(if (tab == "CUSTOMER") "Current due" else "Current payable", color = muted, fontSize = 10.sp, maxLines = 1)
                                }
                            }
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                OutlinedButton(
                                    onClick = { entryParty = party; entryType = "credit" },
                                    modifier = Modifier.weight(1f).height(38.dp),
                                    shape = RoundedCornerShape(10.dp),
                                    border = BorderStroke(1.dp, border),
                                    colors = ButtonDefaults.outlinedButtonColors(contentColor = text),
                                    contentPadding = PaddingValues(horizontal = 8.dp, vertical = 0.dp)
                                ) {
                                    Icon(Icons.Default.AddCircleOutline, contentDescription = null, modifier = Modifier.size(15.dp), tint = text)
                                    Spacer(Modifier.width(4.dp))
                                    Text(if (tab == "CUSTOMER") "Charge" else "Purchase", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
                                }
                                Button(
                                    onClick = { entryParty = party; entryType = "payment" },
                                    enabled = due > 0.0,
                                    modifier = Modifier.weight(1f).height(38.dp),
                                    shape = RoundedCornerShape(10.dp),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = Color(0xFF10B981),
                                        disabledContainerColor = if (dark) Color(0xFF334155) else Color(0xFFE2E8F0)
                                    ),
                                    contentPadding = PaddingValues(horizontal = 8.dp, vertical = 0.dp)
                                ) {
                                    Icon(Icons.Default.Payment, contentDescription = null, modifier = Modifier.size(15.dp), tint = Color.White)
                                    Spacer(Modifier.width(4.dp))
                                    Text("Payment", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Color.White, maxLines = 1)
                                }
                                Surface(
                                    onClick = {
                                        if (tab == "CUSTOMER") {
                                            selectedCustomerForDetail = customers.find { it.id == party.id }
                                        } else {
                                            selectedSupplierForDetail = suppliers.find { it.id == party.id }
                                        }
                                    },
                                    shape = RoundedCornerShape(10.dp),
                                    color = if (dark) Color(0xFF1E293B) else Color(0xFFF1F5F9),
                                    border = BorderStroke(1.dp, border),
                                    modifier = Modifier.size(38.dp)
                                ) {
                                    Box(contentAlignment = Alignment.Center) {
                                        Icon(Icons.Default.Visibility, contentDescription = "View Profile & Ledger", tint = accent, modifier = Modifier.size(18.dp))
                                    }
                                }
                                Surface(
                                    onClick = { shareLedgerReminder(context, profile.businessName, party, due) },
                                    enabled = due > 0.0,
                                    shape = RoundedCornerShape(10.dp),
                                    color = if (dark) Color(0xFF1E293B) else Color(0xFFF1F5F9),
                                    border = BorderStroke(1.dp, border),
                                    modifier = Modifier.size(38.dp)
                                ) {
                                    Box(contentAlignment = Alignment.Center) {
                                        Icon(Icons.Default.Share, contentDescription = "Share reminder", tint = if (due > 0.0) text else muted, modifier = Modifier.size(18.dp))
                                    }
                                }
                            }
                            if (expandedParty == party.id) {
                                HorizontalDivider(color = border)
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("Recent Transactions", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = text)
                                    TextButton(
                                        onClick = {
                                            if (tab == "CUSTOMER") {
                                                selectedCustomerForDetail = customers.find { it.id == party.id }
                                            } else {
                                                selectedSupplierForDetail = suppliers.find { it.id == party.id }
                                            }
                                        }
                                    ) {
                                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                            Text("Full Details & Statement", fontSize = 12.sp, color = accent, fontWeight = FontWeight.SemiBold)
                                            Icon(Icons.Default.ArrowForward, null, tint = accent, modifier = Modifier.size(14.dp))
                                        }
                                    }
                                }
                                if (partyTransactions.isEmpty()) Text("No transactions in this period.", color = muted, fontSize = 12.sp)
                                partyTransactions.sortedByDescending { it.date }.forEach { tx -> LedgerTransactionRow(tx, text, muted) }
                            }
                        }
                    }
                }
            }
        }
    }

    entryParty?.let { party ->
        LedgerEntryDialog(
            party = party, partyType = tab, initialType = entryType,
            onDismiss = { entryParty = null },
            onSave = { type, amount, method, note ->
                val due = if (tab == "CUSTOMER") party.currentBalance.coerceAtLeast(0.0) else (-party.currentBalance).coerceAtLeast(0.0)
                if (type == "payment" && amount > due) {
                    Toast.makeText(context, "Payment cannot exceed the current due", Toast.LENGTH_LONG).show()
                } else viewModel.addLedgerTransaction(
                    customerId = if (tab == "CUSTOMER") party.id else null,
                    supplierId = if (tab == "SUPPLIER") party.id else null,
                    type = type, amount = amount, note = note.ifBlank { "$method ${type.replaceFirstChar(Char::uppercase)}" },
                    paymentMethod = method,
                    onResult = { ok, message -> Toast.makeText(context, message, Toast.LENGTH_LONG).show(); if (ok) entryParty = null }
                )
            }
        )
    }

    if (showAddPartyDialog) {
        var partyName by remember { mutableStateOf("") }
        var partyPhone by remember { mutableStateOf("") }
        val generatedCode = remember {
            if (tab == "CUSTOMER") viewModel.generateUniqueCustomerCode() else viewModel.generateUniqueSupplierCode()
        }
        var partyCode by remember { mutableStateOf(generatedCode) }
        var partyOpeningBal by remember { mutableStateOf("") }

        AlertDialog(
            onDismissRequest = { showAddPartyDialog = false },
            title = { Text(if (tab == "CUSTOMER") "Add New Customer" else "Add New Supplier", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = partyName,
                        onValueChange = { partyName = it },
                        label = { Text("Name *") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = partyPhone,
                        onValueChange = { partyPhone = it },
                        label = { Text("Phone Number *") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = partyCode,
                        onValueChange = { partyCode = it },
                        label = { Text("Unique Code") },
                        trailingIcon = {
                            IconButton(onClick = {
                                partyCode = if (tab == "CUSTOMER") viewModel.generateUniqueCustomerCode() else viewModel.generateUniqueSupplierCode()
                            }) {
                                Icon(Icons.Default.Refresh, "Regenerate")
                            }
                        },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = partyOpeningBal,
                        onValueChange = { partyOpeningBal = it.filter { ch -> ch.isDigit() || ch == '.' } },
                        label = { Text("Opening Balance (Optional)") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        if (partyName.isBlank() || partyPhone.isBlank()) {
                            Toast.makeText(context, "Please enter name and phone", Toast.LENGTH_SHORT).show()
                            return@Button
                        }
                        val opBal = partyOpeningBal.toDoubleOrNull() ?: 0.0
                        if (tab == "CUSTOMER") {
                            viewModel.addCustomer(
                                name = partyName.trim(),
                                phone = partyPhone.trim(),
                                initialBalance = opBal,
                                code = partyCode.trim()
                            )
                            Toast.makeText(context, "Customer added with code $partyCode", Toast.LENGTH_SHORT).show()
                        } else {
                            viewModel.addSupplier(
                                name = partyName.trim(),
                                phone = partyPhone.trim(),
                                openingBalance = opBal,
                                code = partyCode.trim()
                            )
                            Toast.makeText(context, "Supplier added with code $partyCode", Toast.LENGTH_SHORT).show()
                        }
                        showAddPartyDialog = false
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = accent)
                ) {
                    Text("Add", color = Color.Black, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showAddPartyDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
}

@Composable
private fun LedgerMetric(label: String, value: Double, card: Color, border: Color, text: Color, muted: Color, modifier: Modifier) {
    Card(modifier, colors = CardDefaults.cardColors(containerColor = card), border = BorderStroke(1.dp, border)) {
        Column(Modifier.padding(10.dp)) { Text(label, color = muted, fontSize = 10.sp); Text("BDT ${ledgerMoney(value)}", color = text, fontWeight = FontWeight.Bold, fontSize = 12.sp) }
    }
}

@Composable
private fun LedgerTransactionRow(tx: LedgerTransactionEntity, text: Color, muted: Color) {
    Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), horizontalArrangement = Arrangement.SpaceBetween) {
        Column(Modifier.weight(1f)) { Text(tx.note ?: tx.type, color = text, fontSize = 12.sp); Text("${ledgerDisplay(tx.date)} • ${tx.paymentMethod}${tx.invoiceNo?.let { " • $it" }.orEmpty()}", color = muted, fontSize = 10.sp) }
        Text("${if (tx.type == "payment") "-" else "+"} BDT ${ledgerMoney(tx.amount)}", color = if (tx.type == "payment") Color(0xFF10B981) else Color(0xFFEF4444), fontWeight = FontWeight.Bold, fontSize = 12.sp)
    }
}

@Composable
private fun LedgerEntryDialog(
    party: LedgerPartyRow, partyType: String, initialType: String, onDismiss: () -> Unit,
    onSave: (String, Double, String, String) -> Unit
) {
    var type by remember(initialType) { mutableStateOf(initialType) }
    var amount by remember { mutableStateOf("") }
    var method by remember { mutableStateOf("Cash") }
    var note by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    AlertDialog(
        onDismissRequest = onDismiss, title = { Text("${party.name} ledger entry") },
        text = { Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) { listOf("credit" to "Charge", "payment" to "Payment").forEach { (value,label) -> FilterChip(selected=type==value,onClick={type=value},label={Text(label)}) } }
            OutlinedTextField(
                value = amount,
                onValueChange = { amount = it.filter { ch -> ch.isDigit() || ch=='.' } },
                label = { Text("Amount (BDT)") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )
            Row(
                modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                listOf("Cash","MFS","Bank","Card").forEach { value ->
                    FilterChip(selected=method==value,onClick={method=value},label={Text(value)})
                }
            }
            OutlinedTextField(
                value = note,
                onValueChange = { note=it },
                label = { Text("Note / reference") },
                maxLines = 2,
                modifier = Modifier.fillMaxWidth()
            )
            Text(if (partyType=="CUSTOMER") "Customer current balance: BDT ${ledgerMoney(party.currentBalance)}" else "Supplier current balance: BDT ${ledgerMoney(party.currentBalance)}", fontSize=11.sp)
            error?.let { Text(it,color=MaterialTheme.colorScheme.error,fontSize=11.sp) }
        } },
        confirmButton = { Button(onClick = { val parsed=amount.toDoubleOrNull(); if(parsed==null||parsed<=0||!parsed.isFinite()) error="Enter a valid positive amount" else onSave(type,parsed,method,note.trim()) }) { Text("Save") } },
        dismissButton = { TextButton(onClick=onDismiss){Text("Cancel")} }
    )
}

private fun ledgerRangeCutoff(range: String): Long? {
    val calendar = Calendar.getInstance(TimeZone.getTimeZone("Asia/Dhaka"))
    return when (range) {
        "TODAY" -> calendar.apply { set(Calendar.HOUR_OF_DAY,0); set(Calendar.MINUTE,0); set(Calendar.SECOND,0); set(Calendar.MILLISECOND,0) }.timeInMillis
        "7_DAYS" -> calendar.apply { add(Calendar.DAY_OF_YEAR,-7) }.timeInMillis
        "MONTH" -> calendar.apply { set(Calendar.DAY_OF_MONTH,1); set(Calendar.HOUR_OF_DAY,0); set(Calendar.MINUTE,0); set(Calendar.SECOND,0); set(Calendar.MILLISECOND,0) }.timeInMillis
        else -> null
    }
}

private fun shareLedgerReminder(context: android.content.Context, business: String, party: LedgerPartyRow, due: Double) {
    val message = "Dear ${party.name}, your outstanding balance with $business is BDT ${ledgerMoney(due)}. Please contact us if this does not match your records."
    val intent = Intent(Intent.ACTION_SEND).apply { type="text/plain"; putExtra(Intent.EXTRA_TEXT,message); putExtra(Intent.EXTRA_SUBJECT,"Account balance reminder") }
    context.startActivity(Intent.createChooser(intent,"Share balance reminder"))
}

private fun ledgerMoney(value: Double) = String.format(Locale.US,"%,.2f",value)
private fun ledgerIso(value: Long) = SimpleDateFormat("yyyy-MM-dd",Locale.US).format(Date(value))
private fun ledgerDisplay(value: Long) = SimpleDateFormat("dd MMM yyyy, hh:mm a",Locale.getDefault()).format(Date(value))
private fun ledgerCsv(value: String) = "\"${value.replace("\"","\"\"")}\""
