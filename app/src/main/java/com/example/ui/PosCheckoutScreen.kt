@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
package com.example.ui

import android.widget.Toast
import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.*
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.lazy.grid.*
import androidx.compose.foundation.shape.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.*
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.*
import androidx.compose.ui.graphics.*
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.KeyboardType
import kotlinx.coroutines.launch
import org.json.JSONObject
import com.example.data.local.PosSaleEntity
import com.example.data.local.ProductVariantEntity
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale


@Composable
fun PosCheckoutScreen(viewModel: AppViewModel) {
    val posCart by viewModel.posCart.collectAsState()
    val customers by viewModel.customers.collectAsState()
    val allProducts by viewModel.products.collectAsState()
    val productVariants by viewModel.productVariants.collectAsState()
    val activeProfile by viewModel.activeProfile.collectAsState()
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    SideEffect { isDarkModeGlobal = isDarkMode }

    val context = LocalContext.current
    var searchQuery by remember { mutableStateOf("") }
    var selectedCategory by remember { mutableStateOf("All") }
    var checkoutPaymentType by remember { mutableStateOf("Cash") } // "Cash", "MFS", "Card", "CustomerCredit"
    var selectedCustomerId by remember { mutableStateOf<String?>(null) }
    var selectedCustomerName by remember { mutableStateOf("Walk-in Customer") }
    var selectedCustomerPhone by remember { mutableStateOf("") }
    var selectedCustomerCode by remember { mutableStateOf("") }
    var showCustomerDialog by remember { mutableStateOf(false) }
    var showAddNewCustomerDialog by remember { mutableStateOf(false) }
    var showDiscountDialog by remember { mutableStateOf(false) }
    var selectedProductForVariants by remember { mutableStateOf<com.example.data.local.ProductItemEntity?>(null) }
    var showInScreenScanner by remember { mutableStateOf(false) }
    var catalogLayoutMode by remember { mutableStateOf("HORIZONTAL") } // "HORIZONTAL" (Horizontal Cards) or "LIST" (Vertical List)

    // State for Add New Customer Dialog
    var newCustCode by remember { mutableStateOf("") }
    var newCustName by remember { mutableStateOf("") }
    var newCustPhone by remember { mutableStateOf("") }
    var newCustAddress by remember { mutableStateOf("") }
    var newCustOpeningBal by remember { mutableStateOf("") }

    LaunchedEffect(showAddNewCustomerDialog) {
        if (showAddNewCustomerDialog && newCustCode.isBlank()) {
            newCustCode = viewModel.generateUniqueCustomerCode()
        }
    }

    var completedSaleEntity by remember { mutableStateOf<PosSaleEntity?>(null) }

    val categories = remember(allProducts) {
        val set = mutableListOf("All")
        allProducts.forEach { p ->
            val cat = p.category?.trim()
            if (!cat.isNullOrBlank() && !set.contains(cat)) {
                set.add(cat)
            }
        }
        set
    }

    val filteredProducts = remember(allProducts, productVariants, searchQuery, selectedCategory) {
        allProducts.filter { prod ->
            val matchesCategory = selectedCategory == "All" || prod.category.equals(selectedCategory, ignoreCase = true)
            val query = searchQuery.trim()
            val matchesVariants = query.isNotBlank() && productVariants.any { v ->
                v.productId == prod.id && (
                    v.variantName.contains(query, ignoreCase = true) ||
                    v.qrCode.contains(query, ignoreCase = true)
                )
            }
            val matchesQuery = query.isBlank() ||
                prod.name.contains(query, ignoreCase = true) ||
                (prod.code?.contains(query, ignoreCase = true) == true) ||
                (prod.qrCode?.contains(query, ignoreCase = true) == true) ||
                (prod.category?.contains(query, ignoreCase = true) == true) ||
                matchesVariants
            matchesCategory && matchesQuery
        }
    }

    // Dynamic color theme definitions matching pixel-perfect white mood image
    val bgCanvas = if (isDarkMode) Color(0xFF090806) else Color(0xFFFFFFFF)
    val cardBg = if (isDarkMode) Color(0xFF13100C) else Color(0xFFFFFFFF)
    val cardBorder = if (isDarkMode) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val primaryText = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val secondaryText = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val headerLabelColor = if (isDarkMode) Color(0xFFE5A93C) else Color(0xFF64748B)

    val yellowPrimary = if (isDarkMode) Color(0xFFE5A93C) else Color(0xFFFFC800)
    val yellowLightBg = if (isDarkMode) Color(0xFF2B200E) else Color(0xFFFFFBEB)
    val stepperMinusBg = if (isDarkMode) Color(0xFF261F13) else Color(0xFFF8FAFC)
    val discountRed = Color(0xFFDC2626)

    val cartDisplayList = posCart

    val totalItemCount = cartDisplayList.sumOf { it.quantity.toInt() }
    val calculatedSubtotal = cartDisplayList.sumOf { it.sellingPrice * it.quantity }
    var isPercentageDiscount by remember { mutableStateOf(false) }
    var discountInputValue by remember { mutableStateOf("") }
    var customerGivenText by remember { mutableStateOf("") }

    val discountPercentNumber = if (isPercentageDiscount) (discountInputValue.toDoubleOrNull() ?: 0.0).coerceAtLeast(0.0) else 0.0
    val flatDiscountNumber = if (!isPercentageDiscount) (discountInputValue.toDoubleOrNull() ?: 0.0).coerceAtLeast(0.0) else 0.0

    val discountAmount = if (isPercentageDiscount) {
        ((calculatedSubtotal * discountPercentNumber) / 100.0).coerceAtMost(calculatedSubtotal)
    } else {
        flatDiscountNumber.coerceAtMost(calculatedSubtotal)
    }
    val calculatedTotal = (calculatedSubtotal - discountAmount).coerceAtLeast(0.0)

    val customerGivenAmount = customerGivenText.toDoubleOrNull()
    val hasGivenAmount = customerGivenAmount != null && customerGivenAmount > 0.0
    val effectiveGiven = customerGivenAmount ?: 0.0

    val changeReturnAmount = if (hasGivenAmount && effectiveGiven > calculatedTotal) {
        effectiveGiven - calculatedTotal
    } else {
        0.0
    }

    val dueShortageAmount = if (checkoutPaymentType == "Due") {
        if (hasGivenAmount && effectiveGiven < calculatedTotal) (calculatedTotal - effectiveGiven) else calculatedTotal
    } else if (hasGivenAmount && effectiveGiven < calculatedTotal) {
        (calculatedTotal - effectiveGiven)
    } else {
        0.0
    }

    androidx.activity.compose.BackHandler {
        viewModel.navigateTo("Dashboard")
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(bgCanvas)
    ) {
        Scaffold(
            containerColor = bgCanvas,
            topBar = {
                GradientTopBar(
                    title = "New Sale",
                    subtitle = "Create new sale and add items",
                    onBack = { viewModel.navigateTo("Dashboard") },
                    actions = {
                        TopHeaderActionPill(
                            text = "Items ($totalItemCount)",
                            icon = Icons.Outlined.ShoppingCart,
                            onClick = null
                        )
                        TopHeaderActionPill(
                            text = "History",
                            icon = Icons.Outlined.History,
                            onClick = { viewModel.navigateTo("Sales") }
                        )
                    }
                )
            },
            bottomBar = {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    color = cardBg,
                    shadowElevation = 8.dp,
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Row(
                        modifier = Modifier
                            .navigationBarsPadding()
                            .padding(horizontal = 16.dp, vertical = 12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text(
                                "TOTAL AMOUNT",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = secondaryText,
                                letterSpacing = 1.sp
                            )
                            Text(
                                "৳ ${String.format("%,.2f", calculatedTotal)}",
                                fontSize = 22.sp,
                                fontWeight = FontWeight.ExtraBold,
                                color = primaryText
                            )
                            if (hasGivenAmount) {
                                if (changeReturnAmount > 0.0) {
                                    Text(
                                        "ফেরত: ৳ ${String.format("%,.2f", changeReturnAmount)}",
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Color(0xFF10B981)
                                    )
                                } else if (dueShortageAmount > 0.0) {
                                    Text(
                                        "বাকি: ৳ ${String.format("%,.2f", dueShortageAmount)}",
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Color(0xFFEF4444)
                                    )
                                }
                            }
                        }

                        Button(
                            onClick = {
                                if (dueShortageAmount > 0.0 && selectedCustomerId == null && checkoutPaymentType != "Due") {
                                    Toast.makeText(context, "বাকি টাকা (৳${String.format("%,.2f", dueShortageAmount)}) রেকর্ড করতে অনুগ্রহ করে কাস্টমার সিলেক্ট করুন", Toast.LENGTH_LONG).show()
                                    showCustomerDialog = true
                                    return@Button
                                }
                                viewModel.checkoutPosCart(
                                    paymentType = if (dueShortageAmount > 0.0 && !hasGivenAmount) "Due" else checkoutPaymentType,
                                    customerId = selectedCustomerId,
                                    discount = discountAmount,
                                    cashReceived = if (hasGivenAmount) effectiveGiven else (if (checkoutPaymentType == "Due") 0.0 else null),
                                    onSuccess = { orderId, totalAmt, saleEntity ->
                                        completedSaleEntity = saleEntity
                                    },
                                    onError = { err ->
                                        viewModel.logFirebaseStatus("POS Checkout error: $err")
                                        Toast.makeText(context, err, Toast.LENGTH_LONG).show()
                                    }
                                )
                            },
                            modifier = Modifier.height(48.dp),
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary),
                            contentPadding = PaddingValues(horizontal = 20.dp)
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Text("Confirm Sale", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Color.Black)
                                Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color.Black, modifier = Modifier.size(20.dp))
                            }
                        }
                    }
                }
            }
        ) { padding ->
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
                contentPadding = PaddingValues(top = 8.dp, bottom = 16.dp)
            ) {

                // ── 0. CUSTOMER SELECTION BAR & DROPDOWN ─────────────────────────
                item {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { showCustomerDialog = true },
                        shape = RoundedCornerShape(14.dp),
                        colors = CardDefaults.cardColors(containerColor = cardBg),
                        border = BorderStroke(1.dp, if (selectedCustomerId != null) yellowPrimary else cardBorder)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 14.dp, vertical = 12.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(10.dp),
                                modifier = Modifier.weight(1f)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(38.dp)
                                        .clip(CircleShape)
                                        .background(if (selectedCustomerId != null) yellowLightBg else (if (isDarkMode) Color(0xFF1E1911) else Color(0xFFF1F5F9))),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = if (selectedCustomerId != null) Icons.Default.Person else Icons.Default.Storefront,
                                        contentDescription = null,
                                        tint = if (selectedCustomerId != null) yellowPrimary else secondaryText,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }

                                Column {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                                    ) {
                                        Text(
                                            text = selectedCustomerName,
                                            fontSize = 14.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = primaryText
                                        )
                                        if (selectedCustomerCode.isNotBlank()) {
                                            Surface(
                                                shape = RoundedCornerShape(6.dp),
                                                color = yellowLightBg,
                                                border = BorderStroke(0.5.dp, yellowPrimary)
                                            ) {
                                                Text(
                                                    text = selectedCustomerCode,
                                                    fontSize = 10.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = yellowPrimary,
                                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                                )
                                            }
                                        }
                                    }
                                    Text(
                                        text = if (selectedCustomerPhone.isNotBlank()) selectedCustomerPhone else "কাস্টমার নির্বাচন বা সার্চ করুন (Tap to select)",
                                        fontSize = 11.5.sp,
                                        color = secondaryText
                                    )
                                }
                            }

                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = if (isDarkMode) Color(0xFF1E1911) else Color(0xFFF8FAFC),
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(2.dp)
                                ) {
                                    Text("Change", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = yellowPrimary)
                                    Icon(Icons.Default.ArrowDropDown, null, tint = yellowPrimary, modifier = Modifier.size(16.dp))
                                }
                            }
                        }
                    }
                }

                // ── 1. SEARCH & SCAN INPUT ──────────────────────────────────────
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(
                            "SEARCH & SCAN INPUT",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = headerLabelColor,
                            letterSpacing = 1.2.sp
                        )

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            OutlinedTextField(
                                value = searchQuery,
                                onValueChange = { searchQuery = it },
                                placeholder = { Text("Enter SKU / Product Name / Barcode", fontSize = 12.sp, color = secondaryText) },
                                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = secondaryText) },
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedContainerColor = cardBg,
                                    unfocusedContainerColor = cardBg,
                                    focusedBorderColor = yellowPrimary,
                                    unfocusedBorderColor = cardBorder,
                                    focusedTextColor = primaryText,
                                    unfocusedTextColor = primaryText
                                ),
                                singleLine = true
                            )

                            Button(
                                onClick = { showInScreenScanner = true },
                                modifier = Modifier.height(52.dp),
                                shape = RoundedCornerShape(12.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary),
                                contentPadding = PaddingValues(horizontal = 16.dp)
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Icon(Icons.Default.QrCodeScanner, contentDescription = null, tint = Color.Black, modifier = Modifier.size(18.dp))
                                    Text("Scan QR", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Color.Black)
                                }
                            }
                        }
                    }
                }

                // ── 2. PRODUCT CATALOG & QUICK ADD ─────────────────────────────
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Text(
                                    "PRODUCT CATALOG (${filteredProducts.size})",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = headerLabelColor,
                                    letterSpacing = 1.2.sp
                                )
                                // Layout Mode Switcher (Horizontal Cards vs List)
                                Surface(
                                    shape = RoundedCornerShape(12.dp),
                                    color = cardBorder.copy(alpha = 0.35f)
                                ) {
                                    Row(modifier = Modifier.padding(2.dp)) {
                                        Surface(
                                            shape = RoundedCornerShape(10.dp),
                                            color = if (catalogLayoutMode == "HORIZONTAL") yellowPrimary else Color.Transparent,
                                            modifier = Modifier.clickable { catalogLayoutMode = "HORIZONTAL" }
                                        ) {
                                            Text(
                                                "Cards",
                                                fontSize = 10.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (catalogLayoutMode == "HORIZONTAL") Color.Black else secondaryText,
                                                modifier = Modifier.padding(horizontal = 7.dp, vertical = 2.dp)
                                            )
                                        }
                                        Surface(
                                            shape = RoundedCornerShape(10.dp),
                                            color = if (catalogLayoutMode == "LIST") yellowPrimary else Color.Transparent,
                                            modifier = Modifier.clickable { catalogLayoutMode = "LIST" }
                                        ) {
                                            Text(
                                                "List",
                                                fontSize = 10.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (catalogLayoutMode == "LIST") Color.Black else secondaryText,
                                                modifier = Modifier.padding(horizontal = 7.dp, vertical = 2.dp)
                                            )
                                        }
                                    }
                                }
                            }

                            Row(
                                modifier = Modifier.clickable { viewModel.navigateTo("Inventory") },
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Icon(Icons.Default.AddCircle, null, tint = yellowPrimary, modifier = Modifier.size(14.dp))
                                Text("+ New Product", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = yellowPrimary)
                            }
                        }

                        // Category Filter Chips
                        if (categories.size > 1) {
                            LazyRow(
                                horizontalArrangement = Arrangement.spacedBy(6.dp),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                items(categories) { cat ->
                                    val isCatSelected = selectedCategory == cat
                                    Surface(
                                        modifier = Modifier.clickable { selectedCategory = cat },
                                        shape = RoundedCornerShape(20.dp),
                                        color = if (isCatSelected) yellowPrimary else cardBg,
                                        border = BorderStroke(1.dp, if (isCatSelected) yellowPrimary else cardBorder)
                                    ) {
                                        Text(
                                            text = cat,
                                            fontSize = 12.sp,
                                            fontWeight = if (isCatSelected) FontWeight.Bold else FontWeight.Medium,
                                            color = if (isCatSelected) Color.Black else secondaryText,
                                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                                        )
                                    }
                                }
                            }
                        }

                        // Products List / Cards
                        if (filteredProducts.isEmpty()) {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = cardBg),
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Column(
                                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    verticalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Text(
                                        if (allProducts.isEmpty()) "ইনভেন্টরিতে কোনো পণ্য নেই (No products in inventory)" else "কোনো পণ্য পাওয়া যায়নি (No matching products)",
                                        fontSize = 13.sp,
                                        color = secondaryText,
                                        textAlign = TextAlign.Center
                                    )
                                    Button(
                                        onClick = { viewModel.navigateTo("Inventory") },
                                        colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary),
                                        shape = RoundedCornerShape(8.dp)
                                    ) {
                                        Text("পণ্য যোগ করুন (Add Product)", color = Color.Black, fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                    }
                                }
                            }
                        } else if (catalogLayoutMode == "HORIZONTAL") {
                            // ── HORIZONTAL PRODUCT CARDS (LazyRow Carousel) ────────────────
                            LazyRow(
                                horizontalArrangement = Arrangement.spacedBy(10.dp),
                                modifier = Modifier.fillMaxWidth(),
                                contentPadding = PaddingValues(horizontal = 2.dp, vertical = 4.dp)
                            ) {
                                items(filteredProducts, key = { it.id }) { prod ->
                                    val inCartItem = posCart.find { it.productId == prod.id }
                                    val stockInt = prod.stockQuantity.toInt()
                                    val prodVariants = productVariants.filter { it.productId == prod.id }
                                    val inCartCount = if (prodVariants.isNotEmpty()) posCart.filter { it.productId == prod.id }.sumOf { it.quantity.toInt() } else 0

                                    Card(
                                        modifier = Modifier
                                            .width(160.dp)
                                            .height(205.dp),
                                        shape = RoundedCornerShape(14.dp),
                                        colors = CardDefaults.cardColors(containerColor = cardBg),
                                        border = BorderStroke(
                                            1.dp,
                                            if (inCartItem != null || inCartCount > 0) yellowPrimary.copy(alpha = 0.7f) else cardBorder
                                        )
                                    ) {
                                        Column(
                                            modifier = Modifier
                                                .fillMaxSize()
                                                .padding(10.dp),
                                            verticalArrangement = Arrangement.SpaceBetween
                                        ) {
                                            // Top Row: Avatar + Stock & Variant Badges
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.Top
                                            ) {
                                                Box(
                                                    modifier = Modifier
                                                        .size(36.dp)
                                                        .clip(RoundedCornerShape(8.dp))
                                                        .background(yellowLightBg),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Text(
                                                        prod.name.take(1).uppercase(),
                                                        fontSize = 15.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = yellowPrimary
                                                    )
                                                }

                                                Column(
                                                    horizontalAlignment = Alignment.End,
                                                    verticalArrangement = Arrangement.spacedBy(3.dp)
                                                ) {
                                                    Surface(
                                                        shape = RoundedCornerShape(4.dp),
                                                        color = if (stockInt <= 0) discountRed.copy(alpha = 0.12f)
                                                        else if (stockInt <= 5) Color(0xFFF59E0B).copy(alpha = 0.12f)
                                                        else Color(0xFF10B981).copy(alpha = 0.12f)
                                                    ) {
                                                        Text(
                                                            if (stockInt <= 0) "Stock Out" else "$stockInt ${prod.unit}",
                                                            fontSize = 9.sp,
                                                            fontWeight = FontWeight.Bold,
                                                            color = if (stockInt <= 0) discountRed else if (stockInt <= 5) Color(0xFFF59E0B) else Color(0xFF10B981),
                                                            modifier = Modifier.padding(horizontal = 5.dp, vertical = 2.dp)
                                                        )
                                                    }

                                                    if (prodVariants.isNotEmpty()) {
                                                        Surface(
                                                            shape = RoundedCornerShape(4.dp),
                                                            color = if (isDarkMode) Color(0xFF1E1B2E) else Color(0xFFEEF2FF),
                                                            border = BorderStroke(0.6.dp, Color(0xFF818CF8).copy(alpha = 0.5f))
                                                        ) {
                                                            Text(
                                                                "${prodVariants.size} Var",
                                                                fontSize = 8.5.sp,
                                                                color = Color(0xFF818CF8),
                                                                fontWeight = FontWeight.Bold,
                                                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                            )
                                                        }
                                                    }
                                                }
                                            }

                                            // Middle: Product Name & Price
                                            Column(
                                                modifier = Modifier.fillMaxWidth(),
                                                verticalArrangement = Arrangement.spacedBy(2.dp)
                                            ) {
                                                Text(
                                                    text = prod.name,
                                                    fontSize = 12.5.sp,
                                                    fontWeight = FontWeight.SemiBold,
                                                    color = primaryText,
                                                    maxLines = 2,
                                                    minLines = 2,
                                                    overflow = TextOverflow.Ellipsis,
                                                    lineHeight = 15.sp
                                                )
                                                Text(
                                                    text = "৳ ${String.format("%,.0f", prod.salePrice)}",
                                                    fontSize = 13.5.sp,
                                                    fontWeight = FontWeight.ExtraBold,
                                                    color = yellowPrimary
                                                )
                                            }

                                            // Bottom: Quick Action
                                            if (prodVariants.isNotEmpty()) {
                                                Button(
                                                    onClick = { selectedProductForVariants = prod },
                                                    shape = RoundedCornerShape(8.dp),
                                                    colors = ButtonDefaults.buttonColors(
                                                        containerColor = if (inCartCount > 0) Color(0xFF818CF8) else yellowPrimary
                                                    ),
                                                    contentPadding = PaddingValues(horizontal = 6.dp, vertical = 2.dp),
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .height(30.dp)
                                                ) {
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                                    ) {
                                                        Icon(
                                                            Icons.Default.Layers,
                                                            contentDescription = null,
                                                            tint = Color.Black,
                                                            modifier = Modifier.size(12.dp)
                                                        )
                                                        Text(
                                                            if (inCartCount > 0) "Cart ($inCartCount)" else "Choose (${prodVariants.size})",
                                                            fontSize = 10.5.sp,
                                                            fontWeight = FontWeight.Bold,
                                                            color = Color.Black
                                                        )
                                                    }
                                                }
                                            } else if (inCartItem != null) {
                                                Row(
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .height(30.dp),
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.SpaceBetween
                                                ) {
                                                    Box(
                                                        modifier = Modifier
                                                            .size(28.dp)
                                                            .clip(RoundedCornerShape(6.dp))
                                                            .background(stepperMinusBg)
                                                            .border(BorderStroke(1.dp, cardBorder), RoundedCornerShape(6.dp))
                                                            .clickable {
                                                                viewModel.updatePosCartItemQuantity(inCartItem.variantId, inCartItem.quantity - 1.0)
                                                            },
                                                        contentAlignment = Alignment.Center
                                                    ) {
                                                        Icon(Icons.Default.Remove, contentDescription = null, tint = primaryText, modifier = Modifier.size(14.dp))
                                                    }
                                                    Text(
                                                        "${inCartItem.quantity.toInt()}",
                                                        fontSize = 12.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = primaryText
                                                    )
                                                    Box(
                                                        modifier = Modifier
                                                            .size(28.dp)
                                                            .clip(RoundedCornerShape(6.dp))
                                                            .background(yellowPrimary)
                                                            .clickable {
                                                                viewModel.updatePosCartItemQuantity(inCartItem.variantId, inCartItem.quantity + 1.0)
                                                            },
                                                        contentAlignment = Alignment.Center
                                                    ) {
                                                        Icon(Icons.Default.Add, contentDescription = null, tint = Color.Black, modifier = Modifier.size(14.dp))
                                                    }
                                                }
                                            } else {
                                                if (stockInt <= 0) {
                                                    Surface(
                                                        modifier = Modifier
                                                            .fillMaxWidth()
                                                            .height(30.dp),
                                                        shape = RoundedCornerShape(8.dp),
                                                        color = cardBorder.copy(alpha = 0.5f)
                                                    ) {
                                                        Box(contentAlignment = Alignment.Center) {
                                                            Text("Stock Out", fontSize = 10.5.sp, color = secondaryText, fontWeight = FontWeight.Medium)
                                                        }
                                                    }
                                                } else {
                                                    Button(
                                                        onClick = { viewModel.addProductToPosCart(prod, 1.0) },
                                                        shape = RoundedCornerShape(8.dp),
                                                        colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary),
                                                        contentPadding = PaddingValues(horizontal = 6.dp, vertical = 2.dp),
                                                        modifier = Modifier
                                                            .fillMaxWidth()
                                                            .height(30.dp)
                                                    ) {
                                                        Row(
                                                            verticalAlignment = Alignment.CenterVertically,
                                                            horizontalArrangement = Arrangement.spacedBy(3.dp)
                                                        ) {
                                                            Icon(Icons.Default.Add, contentDescription = null, tint = Color.Black, modifier = Modifier.size(13.dp))
                                                            Text("Add", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = Color.Black)
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            // ── COMPACT LIST VIEW ──────────────────────────────────────────
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = cardBg),
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Column(modifier = Modifier.padding(8.dp)) {
                                    filteredProducts.take(8).forEachIndexed { idx, prod ->
                                        if (idx > 0) HorizontalDivider(color = cardBorder.copy(alpha = 0.5f), thickness = 0.5.dp)
                                        val inCartItem = posCart.find { it.productId == prod.id }
                                        val stockInt = prod.stockQuantity.toInt()
                                        Row(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .padding(horizontal = 8.dp, vertical = 8.dp),
                                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            // Avatar
                                            Box(
                                                modifier = Modifier
                                                    .size(40.dp)
                                                    .clip(RoundedCornerShape(8.dp))
                                                    .background(yellowLightBg),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Text(
                                                    prod.name.take(1).uppercase(),
                                                    fontSize = 16.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = yellowPrimary
                                                )
                                            }

                                            // Name & SKU & Stock
                                            Column(modifier = Modifier.weight(1f)) {
                                                Text(
                                                    prod.name,
                                                    fontSize = 13.5.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = primaryText,
                                                    maxLines = 1,
                                                    overflow = TextOverflow.Ellipsis
                                                )
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                                ) {
                                                    Text("৳ ${String.format("%,.0f", prod.salePrice)}", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = yellowPrimary)
                                                    Text("•", fontSize = 10.sp, color = secondaryText)
                                                    Text(
                                                        if (stockInt <= 0) "Stock Out" else "Stock: $stockInt ${prod.unit}",
                                                        fontSize = 11.sp,
                                                        color = if (stockInt <= 0) discountRed else if (stockInt <= 5) Color(0xFFF59E0B) else Color(0xFF10B981)
                                                    )
                                                    val prodVariants = productVariants.filter { it.productId == prod.id }
                                                    if (prodVariants.isNotEmpty()) {
                                                        Surface(
                                                            shape = RoundedCornerShape(4.dp),
                                                            color = if (isDarkMode) Color(0xFF1E1B2E) else Color(0xFFEEF2FF),
                                                            border = BorderStroke(0.6.dp, Color(0xFF818CF8).copy(alpha = 0.5f))
                                                        ) {
                                                            Text(
                                                                "${prodVariants.size} Variants",
                                                                fontSize = 9.5.sp,
                                                                color = Color(0xFF818CF8),
                                                                fontWeight = FontWeight.Bold,
                                                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                            )
                                                        }
                                                    }
                                                }
                                            }

                                            // Cart action button
                                            val prodVariants = productVariants.filter { it.productId == prod.id }
                                            if (prodVariants.isNotEmpty()) {
                                                val inCartCount = posCart.filter { it.productId == prod.id }.sumOf { it.quantity.toInt() }
                                                Button(
                                                    onClick = { selectedProductForVariants = prod },
                                                    shape = RoundedCornerShape(8.dp),
                                                    colors = ButtonDefaults.buttonColors(containerColor = if (inCartCount > 0) Color(0xFF818CF8) else yellowPrimary),
                                                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp),
                                                    modifier = Modifier.height(32.dp)
                                                ) {
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                                    ) {
                                                        Icon(Icons.Default.Layers, contentDescription = null, tint = Color.Black, modifier = Modifier.size(13.dp))
                                                        Text(if (inCartCount > 0) "Cart ($inCartCount)" else "Choose (${prodVariants.size})", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color.Black)
                                                    }
                                                }
                                            } else if (inCartItem != null) {
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                                ) {
                                                    Box(
                                                        modifier = Modifier
                                                            .size(28.dp)
                                                            .clip(RoundedCornerShape(6.dp))
                                                            .background(stepperMinusBg)
                                                            .border(BorderStroke(1.dp, cardBorder), RoundedCornerShape(6.dp))
                                                            .clickable {
                                                                viewModel.updatePosCartItemQuantity(inCartItem.variantId, inCartItem.quantity - 1.0)
                                                            },
                                                        contentAlignment = Alignment.Center
                                                    ) {
                                                        Icon(Icons.Default.Remove, contentDescription = null, tint = primaryText, modifier = Modifier.size(14.dp))
                                                    }
                                                    Text(
                                                        "${inCartItem.quantity.toInt()}",
                                                        fontSize = 13.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = primaryText
                                                    )
                                                    Box(
                                                        modifier = Modifier
                                                            .size(28.dp)
                                                            .clip(RoundedCornerShape(6.dp))
                                                            .background(yellowPrimary)
                                                            .clickable {
                                                                viewModel.updatePosCartItemQuantity(inCartItem.variantId, inCartItem.quantity + 1.0)
                                                            },
                                                        contentAlignment = Alignment.Center
                                                    ) {
                                                        Icon(Icons.Default.Add, contentDescription = null, tint = Color.Black, modifier = Modifier.size(14.dp))
                                                    }
                                                }
                                            } else {
                                                Button(
                                                    onClick = { viewModel.addProductToPosCart(prod, 1.0) },
                                                    shape = RoundedCornerShape(8.dp),
                                                    colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary),
                                                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp),
                                                    modifier = Modifier.height(32.dp)
                                                ) {
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                                    ) {
                                                        Icon(Icons.Default.Add, contentDescription = null, tint = Color.Black, modifier = Modifier.size(14.dp))
                                                        Text("Add", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color.Black)
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if (filteredProducts.size > 8) {
                                        HorizontalDivider(color = cardBorder.copy(alpha = 0.5f), thickness = 0.5.dp)
                                        TextButton(
                                            onClick = { catalogLayoutMode = "HORIZONTAL" },
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Text(
                                                "View all ${filteredProducts.size} items in Horizontal Carousel ➔",
                                                fontSize = 12.sp,
                                                color = yellowPrimary,
                                                fontWeight = FontWeight.SemiBold
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // ── 3. CART ITEMS ──────────────────────────────────────────────
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                "CART ITEMS ($totalItemCount)",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = headerLabelColor,
                                letterSpacing = 1.2.sp
                            )
                            if (cartDisplayList.isNotEmpty()) {
                                Text(
                                    "Clear All",
                                    fontSize = 12.sp,
                                    color = discountRed,
                                    fontWeight = FontWeight.SemiBold,
                                    modifier = Modifier.clickable { viewModel.clearPosCart() }
                                )
                            }
                        }

                        if (cartDisplayList.isEmpty()) {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = cardBg),
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Column(
                                    modifier = Modifier.fillMaxWidth().padding(24.dp),
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    verticalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Icon(Icons.Outlined.ShoppingCart, contentDescription = null, tint = secondaryText, modifier = Modifier.size(36.dp))
                                    Text("Your cart is empty", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = primaryText)
                                    Text("Tap '+ Add' on products above or scan barcodes to begin sale", fontSize = 12.sp, color = secondaryText, textAlign = TextAlign.Center)
                                }
                            }
                        } else {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = cardBg),
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Column(
                                    modifier = Modifier.padding(14.dp),
                                    verticalArrangement = Arrangement.spacedBy(14.dp)
                                ) {
                                    cartDisplayList.forEachIndexed { index, item ->
                                        if (index > 0) {
                                            HorizontalDivider(color = cardBorder)
                                        }

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.spacedBy(12.dp),
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            // Avatar
                                            Box(
                                                modifier = Modifier
                                                    .size(48.dp)
                                                    .clip(RoundedCornerShape(10.dp))
                                                    .background(if (isDarkMode) Color(0xFF1E1911) else Color(0xFFF1F5F9))
                                                    .border(BorderStroke(1.dp, cardBorder), RoundedCornerShape(10.dp)),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Text(
                                                    item.productName.take(1).uppercase(),
                                                    fontSize = 18.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = yellowPrimary
                                                )
                                            }

                                            // Column Details + Stepper
                                            Column(modifier = Modifier.weight(1f)) {
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                                ) {
                                                    Text(
                                                        text = item.productName,
                                                        fontSize = 14.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = primaryText
                                                    )
                                                    if (item.variantName.isNotBlank() && item.variantName != "Standard" && item.variantName != "pcs") {
                                                        Surface(
                                                            shape = RoundedCornerShape(4.dp),
                                                            color = if (isDarkMode) Color(0xFF1E1B2E) else Color(0xFFEEF2FF),
                                                            border = BorderStroke(0.6.dp, Color(0xFF818CF8).copy(alpha = 0.5f))
                                                        ) {
                                                            Text(
                                                                text = item.variantName,
                                                                fontSize = 10.sp,
                                                                fontWeight = FontWeight.Bold,
                                                                color = Color(0xFF818CF8),
                                                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                            )
                                                        }
                                                    }
                                                }
                                                if (item.qrCode.isNotBlank()) {
                                                    Text(
                                                        text = "QR: ${item.qrCode}",
                                                        fontSize = 11.sp,
                                                        color = yellowPrimary,
                                                        fontWeight = FontWeight.SemiBold
                                                    )
                                                } else if (item.variantName.isNotBlank() && item.variantName != "Standard" && item.variantName != "pcs") {
                                                    Text(
                                                        text = item.variantName,
                                                        fontSize = 12.sp,
                                                        color = secondaryText
                                                    )
                                                }
                                                Spacer(modifier = Modifier.height(4.dp))

                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(
                                                        text = "৳ ${String.format("%,.2f", item.sellingPrice)}",
                                                        fontSize = 13.5.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = primaryText
                                                    )

                                                    // Stepper
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                                    ) {
                                                        Box(
                                                            modifier = Modifier
                                                                .size(28.dp)
                                                                .clip(RoundedCornerShape(6.dp))
                                                                .background(stepperMinusBg)
                                                                .border(BorderStroke(1.dp, cardBorder), RoundedCornerShape(6.dp))
                                                                .clickable {
                                                                    if (posCart.isNotEmpty()) {
                                                                        viewModel.updatePosCartItemQuantity(item.variantId, item.quantity - 1.0)
                                                                    }
                                                                },
                                                            contentAlignment = Alignment.Center
                                                        ) {
                                                            Icon(Icons.Default.Remove, contentDescription = null, tint = primaryText, modifier = Modifier.size(14.dp))
                                                        }

                                                        Text(
                                                            text = "${item.quantity.toInt()}",
                                                            fontSize = 13.sp,
                                                            fontWeight = FontWeight.Bold,
                                                            color = primaryText
                                                        )

                                                        Box(
                                                            modifier = Modifier
                                                                .size(28.dp)
                                                                .clip(RoundedCornerShape(6.dp))
                                                                .background(yellowLightBg)
                                                                .border(BorderStroke(1.dp, yellowPrimary), RoundedCornerShape(6.dp))
                                                                .clickable {
                                                                    if (posCart.isNotEmpty()) {
                                                                        viewModel.updatePosCartItemQuantity(item.variantId, item.quantity + 1.0)
                                                                    }
                                                                },
                                                            contentAlignment = Alignment.Center
                                                        ) {
                                                            Text("+", color = if (isDarkMode) yellowPrimary else Color(0xFFB45309), fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                                        }
                                                    }

                                                    Text(
                                                        text = "= ৳ ${String.format("%,.2f", item.sellingPrice * item.quantity)}",
                                                        fontSize = 13.5.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = primaryText
                                                    )

                                                    Icon(
                                                        Icons.Default.DeleteOutline,
                                                        contentDescription = "Delete",
                                                        tint = secondaryText,
                                                        modifier = Modifier
                                                            .size(20.dp)
                                                            .clickable {
                                                                if (posCart.isNotEmpty()) {
                                                                    viewModel.updatePosCartItemQuantity(item.variantId, 0.0)
                                                                }
                                                            }
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

                // ── 4. PAYMENT METHOD ──────────────────────────────────────────
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(
                            "PAYMENT METHOD",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = headerLabelColor,
                            letterSpacing = 1.2.sp
                        )

                        val paymentMethods = listOf(
                            Triple("Cash", "Cash\n(নগদ)", Icons.Default.Money),
                            Triple("MFS", "Mobile /\nMFS", Icons.Default.Smartphone),
                            Triple("Card", "Card\n(কার্ড)", Icons.Default.CreditCard),
                            Triple("Due", "Customer\nDue (বাকি)", Icons.Default.AccountBalance)
                        )

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            paymentMethods.forEach { (typeKey, label, icon) ->
                                val isSelected = checkoutPaymentType == typeKey
                                Card(
                                    modifier = Modifier
                                        .weight(1f)
                                        .height(84.dp)
                                        .clickable { checkoutPaymentType = typeKey },
                                    shape = RoundedCornerShape(12.dp),
                                    colors = CardDefaults.cardColors(
                                        containerColor = if (isSelected) yellowLightBg else cardBg
                                    ),
                                    border = BorderStroke(1.5.dp, if (isSelected) yellowPrimary else cardBorder)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .fillMaxSize()
                                            .padding(6.dp),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Column(
                                            horizontalAlignment = Alignment.CenterHorizontally,
                                            verticalArrangement = Arrangement.Center
                                        ) {
                                            Icon(
                                                imageVector = icon,
                                                contentDescription = null,
                                                tint = primaryText,
                                                modifier = Modifier.size(22.dp)
                                            )
                                            Spacer(modifier = Modifier.height(6.dp))
                                            Text(
                                                text = label,
                                                fontSize = 11.sp,
                                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                                color = primaryText,
                                                textAlign = TextAlign.Center,
                                                lineHeight = 13.sp
                                            )
                                        }

                                        if (isSelected) {
                                            Box(
                                                modifier = Modifier
                                                    .align(Alignment.TopEnd)
                                                    .size(16.dp)
                                                    .clip(CircleShape)
                                                    .background(yellowPrimary),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Icon(Icons.Default.Check, contentDescription = null, tint = Color.Black, modifier = Modifier.size(12.dp))
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // ── 5. CUSTOMER GIVEN AMOUNT & LIVE CHANGE/DUE CALCULATION ─────────
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                "CUSTOMER GIVEN AMOUNT (টাকা গ্রহণ ও ফেরত)",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = headerLabelColor,
                                letterSpacing = 1.2.sp
                            )
                            if (customerGivenText.isNotBlank()) {
                                Text(
                                    "মুছুন (Clear)",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = discountRed,
                                    modifier = Modifier.clickable { customerGivenText = "" }
                                )
                            }
                        }

                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Column(
                                modifier = Modifier.padding(14.dp),
                                verticalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                // Quick Cash Shortcut Chips
                                Text(
                                    "দ্রুত ক্যাশ নির্বাচন (Quick Presets):",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = secondaryText
                                )

                                val exactRounded = Math.ceil(calculatedTotal).toInt()
                                val presets = listOf(
                                    "Exact (৳$exactRounded)" to exactRounded.toDouble(),
                                    "৳500" to 500.0,
                                    "৳1000" to 1000.0,
                                    "৳2000" to 2000.0
                                )

                                LazyRow(
                                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    items(presets) { (label, amt) ->
                                        val isChosen = customerGivenText == (if (amt % 1.0 == 0.0) amt.toInt().toString() else amt.toString())
                                        Surface(
                                            shape = RoundedCornerShape(8.dp),
                                            color = if (isChosen) yellowPrimary else if (isDarkMode) Color(0xFF261F13) else Color(0xFFF8FAFC),
                                            border = BorderStroke(1.dp, if (isChosen) yellowPrimary else cardBorder),
                                            modifier = Modifier.clickable {
                                                customerGivenText = if (amt % 1.0 == 0.0) amt.toInt().toString() else amt.toString()
                                            }
                                        ) {
                                            Text(
                                                text = label,
                                                fontSize = 12.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (isChosen) Color.Black else primaryText,
                                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                                            )
                                        }
                                    }

                                    item {
                                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            listOf(50, 100, 500).forEach { plusAmt ->
                                                Surface(
                                                    shape = RoundedCornerShape(8.dp),
                                                    color = if (isDarkMode) Color(0xFF1E1810) else Color(0xFFFFFBEB),
                                                    border = BorderStroke(1.dp, yellowPrimary.copy(alpha = 0.5f)),
                                                    modifier = Modifier.clickable {
                                                        val current = customerGivenText.toDoubleOrNull() ?: 0.0
                                                        customerGivenText = (current + plusAmt).toInt().toString()
                                                    }
                                                ) {
                                                    Text(
                                                        text = "+৳$plusAmt",
                                                        fontSize = 12.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = if (isDarkMode) yellowPrimary else Color(0xFFB45309),
                                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 6.dp)
                                                    )
                                                }
                                            }
                                        }
                                    }
                                }

                                // Input Field
                                OutlinedTextField(
                                    value = customerGivenText,
                                    onValueChange = { input ->
                                        customerGivenText = input.filter { it.isDigit() || it == '.' }
                                    },
                                    label = { Text("গ্রাহকের দেওয়া নগদ টাকা (Given Amount ৳)", color = secondaryText, fontSize = 12.5.sp) },
                                    placeholder = { Text("৳ 0.00 (যেমন: ১০০০)", color = secondaryText.copy(alpha = 0.6f), fontSize = 13.sp) },
                                    leadingIcon = {
                                        Icon(Icons.Default.Payments, contentDescription = null, tint = yellowPrimary)
                                    },
                                    trailingIcon = {
                                        if (customerGivenText.isNotEmpty()) {
                                            IconButton(onClick = { customerGivenText = "" }) {
                                                Icon(Icons.Default.Clear, contentDescription = "Clear", tint = secondaryText, modifier = Modifier.size(18.dp))
                                            }
                                        }
                                    },
                                    singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(12.dp),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedTextColor = primaryText,
                                        unfocusedTextColor = primaryText,
                                        focusedBorderColor = yellowPrimary,
                                        unfocusedBorderColor = cardBorder
                                    )
                                )

                                // Live Calculation Feedback Card
                                if (hasGivenAmount) {
                                    if (changeReturnAmount > 0.0) {
                                        // Green Return / Change Card
                                        Surface(
                                            shape = RoundedCornerShape(12.dp),
                                            color = Color(0xFF10B981).copy(alpha = 0.12f),
                                            border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.4f)),
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Row(
                                                modifier = Modifier.padding(12.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                                            ) {
                                                Box(
                                                    modifier = Modifier
                                                        .size(36.dp)
                                                        .clip(CircleShape)
                                                        .background(Color(0xFF10B981)),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Icon(Icons.Default.CurrencyExchange, contentDescription = null, tint = Color.White, modifier = Modifier.size(20.dp))
                                                }
                                                Column(modifier = Modifier.weight(1f)) {
                                                    Text(
                                                        text = "ফেরত দিন (Return Change)",
                                                        fontSize = 11.5.sp,
                                                        fontWeight = FontWeight.SemiBold,
                                                        color = Color(0xFF10B981)
                                                    )
                                                    Text(
                                                        text = "৳ ${String.format("%,.2f", changeReturnAmount)}",
                                                        fontSize = 19.sp,
                                                        fontWeight = FontWeight.ExtraBold,
                                                        color = Color(0xFF10B981)
                                                    )
                                                    Text(
                                                        text = "গ্রাহক দিয়েছেন ৳${String.format("%,.2f", effectiveGiven)} • মোট বিল ৳${String.format("%,.2f", calculatedTotal)}",
                                                        fontSize = 10.5.sp,
                                                        color = secondaryText
                                                    )
                                                }
                                            }
                                        }
                                    } else if (dueShortageAmount > 0.0) {
                                        // Red/Amber Due Shortage Card
                                        Surface(
                                            shape = RoundedCornerShape(12.dp),
                                            color = Color(0xFFEF4444).copy(alpha = 0.12f),
                                            border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.4f)),
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                                ) {
                                                    Box(
                                                        modifier = Modifier
                                                            .size(36.dp)
                                                            .clip(CircleShape)
                                                            .background(Color(0xFFEF4444)),
                                                        contentAlignment = Alignment.Center
                                                    ) {
                                                        Icon(Icons.Default.AccountBalance, contentDescription = null, tint = Color.White, modifier = Modifier.size(20.dp))
                                                    }
                                                    Column(modifier = Modifier.weight(1f)) {
                                                        Text(
                                                            text = "বাকি থাকবে (Due / Shortage)",
                                                            fontSize = 11.5.sp,
                                                            fontWeight = FontWeight.SemiBold,
                                                            color = Color(0xFFEF4444)
                                                        )
                                                        Text(
                                                            text = "৳ ${String.format("%,.2f", dueShortageAmount)}",
                                                            fontSize = 19.sp,
                                                            fontWeight = FontWeight.ExtraBold,
                                                            color = Color(0xFFEF4444)
                                                        )
                                                        Text(
                                                            text = "গ্রাহক দিয়েছেন ৳${String.format("%,.2f", effectiveGiven)} • শর্ট/বাকি ৳${String.format("%,.2f", dueShortageAmount)}",
                                                            fontSize = 10.5.sp,
                                                            color = secondaryText
                                                        )
                                                    }
                                                }

                                                if (selectedCustomerId == null) {
                                                    Surface(
                                                        shape = RoundedCornerShape(8.dp),
                                                        color = Color(0xFFEF4444).copy(alpha = 0.15f),
                                                        modifier = Modifier.fillMaxWidth()
                                                    ) {
                                                        Row(
                                                            modifier = Modifier
                                                                .clickable { showCustomerDialog = true }
                                                                .padding(horizontal = 10.dp, vertical = 6.dp),
                                                            verticalAlignment = Alignment.CenterVertically,
                                                            horizontalArrangement = Arrangement.SpaceBetween
                                                        ) {
                                                            Text(
                                                                "⚠️ বাকি টাকা রেকর্ড করতে কাস্টমার সিলেক্ট করুন",
                                                                fontSize = 11.sp,
                                                                fontWeight = FontWeight.SemiBold,
                                                                color = Color(0xFFEF4444)
                                                            )
                                                            Text(
                                                                "সিলেক্ট করুন >",
                                                                fontSize = 11.sp,
                                                                fontWeight = FontWeight.Bold,
                                                                color = Color(0xFFEF4444)
                                                            )
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // Exact full paid Card
                                        Surface(
                                            shape = RoundedCornerShape(12.dp),
                                            color = Color(0xFF10B981).copy(alpha = 0.12f),
                                            border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.4f)),
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Row(
                                                modifier = Modifier.padding(12.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                                            ) {
                                                Box(
                                                    modifier = Modifier
                                                        .size(32.dp)
                                                        .clip(CircleShape)
                                                        .background(Color(0xFF10B981)),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Icon(Icons.Default.Check, contentDescription = null, tint = Color.White, modifier = Modifier.size(18.dp))
                                                }
                                                Column {
                                                    Text("সঠিক পরিমাণ পরিশোধিত (Exact Payment)", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                                                    Text("কোনো ফেরত বা বাকি টাকা নেই", fontSize = 11.sp, color = secondaryText)
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // ── 6. ORDER SUMMARY & CALCULATIONS ────────────────────────────
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(
                            "ORDER SUMMARY & CALCULATIONS",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = headerLabelColor,
                            letterSpacing = 1.2.sp
                        )

                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Column(
                                modifier = Modifier.padding(14.dp),
                                verticalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("Subtotal", fontSize = 13.sp, color = secondaryText)
                                    Text("৳ ${String.format("%,.2f", calculatedSubtotal)}", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                }

                                HorizontalDivider(color = cardBorder)

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                                    ) {
                                        Text("Discount (Coupon)", fontSize = 13.sp, color = secondaryText)
                                        if (isPercentageDiscount && discountPercentNumber > 0.0) {
                                            Surface(
                                                shape = RoundedCornerShape(4.dp),
                                                color = discountRed.copy(alpha = 0.15f),
                                                border = BorderStroke(1.dp, discountRed.copy(alpha = 0.3f))
                                            ) {
                                                Text(
                                                    "${if (discountPercentNumber % 1.0 == 0.0) discountPercentNumber.toInt().toString() else discountPercentNumber}% OFF",
                                                    fontSize = 10.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = discountRed,
                                                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                )
                                            }
                                        }
                                    }
                                    Row(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .clickable { showDiscountDialog = true }
                                            .padding(horizontal = 4.dp, vertical = 2.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        if (discountAmount > 0.0) {
                                            Text("- ৳ ${String.format("%.2f", discountAmount)}", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = discountRed)
                                        } else {
                                            Text("+ Add Coupon / Discount", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = yellowPrimary)
                                        }
                                        Icon(Icons.Default.Edit, contentDescription = "Edit Discount", tint = if (discountAmount > 0.0) discountRed else yellowPrimary, modifier = Modifier.size(14.dp))
                                    }
                                }

                                HorizontalDivider(color = cardBorder)

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("Total Payable", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                    Text("৳ ${String.format("%,.2f", calculatedTotal)}", fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = yellowPrimary)
                                }

                                if (hasGivenAmount) {
                                    HorizontalDivider(color = cardBorder)

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Customer Given (প্রাপ্ত)", fontSize = 13.sp, color = secondaryText)
                                        Text("৳ ${String.format("%,.2f", effectiveGiven)}", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                    }

                                    if (changeReturnAmount > 0.0) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("Return Change (ফেরত)", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                                            Text("৳ ${String.format("%,.2f", changeReturnAmount)}", fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF10B981))
                                        }
                                    } else if (dueShortageAmount > 0.0) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("Due Amount (বাকি)", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color(0xFFEF4444))
                                            Text("৳ ${String.format("%,.2f", dueShortageAmount)}", fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFFEF4444))
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Customer Selection Dialog
        if (showCustomerDialog) {
            var customerSearch by remember { mutableStateOf("") }
            val filteredCustomers = remember(customers, customerSearch) {
                if (customerSearch.isBlank()) customers else {
                    customers.filter {
                        it.name.contains(customerSearch, ignoreCase = true) ||
                        it.phone.contains(customerSearch) ||
                        it.code.contains(customerSearch, ignoreCase = true)
                    }
                }
            }

            EnterpriseGestureModal(
                onDismissRequest = { showCustomerDialog = false },
                title = "Select or Add Customer",
                subtitle = "কাস্টমার নির্বাচন বা নতুন তৈরি করুন",
                icon = Icons.Default.Person
            ) {
                Column(
                    modifier = Modifier.fillMaxWidth().verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    // Search customer
                    OutlinedTextField(
                        value = customerSearch,
                        onValueChange = { customerSearch = it },
                        placeholder = { Text("Search by name, phone or code (C-1001)...", fontSize = 12.sp, color = secondaryText) },
                        leadingIcon = { Icon(Icons.Default.Search, null, tint = secondaryText) },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    // Quick Actions: Walk-in vs Add New
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Surface(
                            modifier = Modifier
                                .weight(1f)
                                .height(44.dp)
                                .clickable {
                                    selectedCustomerId = null
                                    selectedCustomerName = "Walk-in Customer"
                                    selectedCustomerPhone = ""
                                    selectedCustomerCode = ""
                                    showCustomerDialog = false
                                },
                            shape = RoundedCornerShape(10.dp),
                            color = cardBg,
                            border = BorderStroke(1.dp, if (selectedCustomerId == null) yellowPrimary else cardBorder)
                        ) {
                            Row(
                                modifier = Modifier.fillMaxSize().padding(horizontal = 8.dp),
                                horizontalArrangement = Arrangement.Center,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.Storefront, null, tint = primaryText, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(6.dp))
                                Text("Walk-in", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = primaryText)
                            }
                        }

                        Button(
                            onClick = {
                                newCustCode = viewModel.generateUniqueCustomerCode()
                                showAddNewCustomerDialog = true
                            },
                            modifier = Modifier
                                .weight(1f)
                                .height(44.dp),
                            shape = RoundedCornerShape(10.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary)
                        ) {
                            Row(
                                horizontalArrangement = Arrangement.Center,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.PersonAdd, null, tint = Color.Black, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(6.dp))
                                Text("+ New", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color.Black)
                            }
                        }
                    }

                    HorizontalDivider(color = cardBorder)

                    Text("EXISTING CUSTOMERS (${filteredCustomers.size})", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = secondaryText)

                    if (filteredCustomers.isEmpty()) {
                        Text("No customers found. Click '+ New' above to create one.", fontSize = 12.sp, color = secondaryText, modifier = Modifier.padding(vertical = 12.dp))
                    } else {
                        filteredCustomers.forEach { cust ->
                            val isSelected = selectedCustomerId == cust.id
                            val cCode = if (cust.code.isNotBlank()) cust.code else "C-${cust.phone.takeLast(4).ifBlank { cust.id.take(4).uppercase() }}"
                            Surface(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable {
                                        selectedCustomerId = cust.id
                                        selectedCustomerName = cust.name
                                        selectedCustomerPhone = cust.phone
                                        selectedCustomerCode = cCode
                                        showCustomerDialog = false
                                    },
                                shape = RoundedCornerShape(10.dp),
                                color = if (isSelected) yellowLightBg else cardBg,
                                border = BorderStroke(1.dp, if (isSelected) yellowPrimary else cardBorder)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth().padding(12.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column {
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            Text(cust.name, color = primaryText, fontWeight = FontWeight.Bold, fontSize = 13.5.sp)
                                            Surface(
                                                shape = RoundedCornerShape(4.dp),
                                                color = yellowLightBg,
                                                border = BorderStroke(0.5.dp, yellowPrimary)
                                            ) {
                                                Text(
                                                    cCode,
                                                    fontSize = 9.5.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = yellowPrimary,
                                                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                )
                                            }
                                        }
                                        if (cust.phone.isNotBlank()) {
                                            Text(cust.phone, color = secondaryText, fontSize = 11.sp)
                                        }
                                    }
                                    if (cust.currentBalance != 0.0) {
                                        Text(
                                            "Due: ৳${String.format("%,.0f", cust.currentBalance)}",
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = if (cust.currentBalance > 0) discountRed else Color(0xFF10B981)
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Add New Customer Modal
        if (showAddNewCustomerDialog) {
            EnterpriseGestureModal(
                onDismissRequest = { showAddNewCustomerDialog = false },
                title = "নতুন কাস্টমার যোগ করুন",
                subtitle = "Add New Customer Profile",
                icon = Icons.Default.PersonAdd
            ) {
                Column(
                    modifier = Modifier.fillMaxWidth().verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedTextField(
                            value = newCustCode,
                            onValueChange = { newCustCode = it.uppercase() },
                            label = { Text("Customer Code (ইউনিক কোড)*") },
                            placeholder = { Text("e.g. C-1001") },
                            singleLine = true,
                            modifier = Modifier.weight(1f),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = yellowPrimary,
                                unfocusedBorderColor = cardBorder,
                                focusedTextColor = primaryText,
                                unfocusedTextColor = primaryText
                            )
                        )
                        IconButton(
                            onClick = { newCustCode = viewModel.generateUniqueCustomerCode() },
                            modifier = Modifier
                                .padding(top = 8.dp)
                                .size(48.dp)
                                .background(yellowLightBg, RoundedCornerShape(10.dp))
                                .border(BorderStroke(1.dp, yellowPrimary), RoundedCornerShape(10.dp))
                        ) {
                            Icon(Icons.Default.Refresh, contentDescription = "Regenerate Code", tint = yellowPrimary, modifier = Modifier.size(20.dp))
                        }
                    }

                    OutlinedTextField(
                        value = newCustName,
                        onValueChange = { newCustName = it },
                        label = { Text("Customer Name (নাম)*") },
                        placeholder = { Text("e.g. Rahim Ahmed") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    OutlinedTextField(
                        value = newCustPhone,
                        onValueChange = { newCustPhone = it },
                        label = { Text("Mobile Number (মোবাইল)*") },
                        placeholder = { Text("e.g. 01712345678") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    OutlinedTextField(
                        value = newCustAddress,
                        onValueChange = { newCustAddress = it },
                        label = { Text("Address (ঠিকানা - Optional)") },
                        placeholder = { Text("e.g. Mirpur, Dhaka") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    OutlinedTextField(
                        value = newCustOpeningBal,
                        onValueChange = { newCustOpeningBal = it },
                        label = { Text("Previous Due / Balance (পূর্বের বাকি ৳ - Optional)") },
                        placeholder = { Text("0.0") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    Spacer(modifier = Modifier.height(6.dp))

                    Button(
                        onClick = {
                            val cName = newCustName.trim()
                            if (cName.isBlank()) {
                                Toast.makeText(context, "Customer name is required", Toast.LENGTH_SHORT).show()
                                return@Button
                            }
                            val cPhone = newCustPhone.trim()
                            if (cPhone.isBlank()) {
                                Toast.makeText(context, "Phone number is required", Toast.LENGTH_SHORT).show()
                                return@Button
                            }
                            val cBal = newCustOpeningBal.toDoubleOrNull() ?: 0.0

                            viewModel.addCustomer(
                                name = cName,
                                phone = cPhone,
                                initialBalance = cBal,
                                address = newCustAddress.trim().ifBlank { null },
                                code = newCustCode.trim().ifBlank { null },
                                onResult = { success, msg, newCust ->
                                    if (success && newCust != null) {
                                        selectedCustomerId = newCust.id
                                        selectedCustomerName = newCust.name
                                        selectedCustomerPhone = newCust.phone
                                        selectedCustomerCode = newCust.code
                                        newCustName = ""
                                        newCustPhone = ""
                                        newCustAddress = ""
                                        newCustOpeningBal = ""
                                        newCustCode = ""
                                        showAddNewCustomerDialog = false
                                        showCustomerDialog = false
                                        Toast.makeText(context, "Customer [${newCust.code}] created & selected", Toast.LENGTH_SHORT).show()
                                    } else {
                                        Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                    }
                                }
                            )
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary),
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Text("Save & Select Customer", color = Color.Black, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    }
                }
            }
        }

        // Discount Edit Dialog
        if (showDiscountDialog) {
            var tempIsPercent by remember { mutableStateOf(isPercentageDiscount) }
            var tempDiscountText by remember { mutableStateOf(discountInputValue) }

            val tempVal = tempDiscountText.toDoubleOrNull() ?: 0.0
            val previewDiscountAmt = if (tempIsPercent) {
                ((calculatedSubtotal * tempVal) / 100.0).coerceAtMost(calculatedSubtotal)
            } else {
                tempVal.coerceAtMost(calculatedSubtotal)
            }
            val previewNetTotal = (calculatedSubtotal - previewDiscountAmt).coerceAtLeast(0.0)

            EnterpriseGestureModal(
                onDismissRequest = { showDiscountDialog = false },
                title = "Set Coupon & Discount",
                subtitle = "শতাংশ (%) বা নির্দিষ্ট টাকা (৳) ডিসকাউন্ট সেট করুন",
                icon = Icons.Default.LocalOffer
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    // Mode Selector Toggle
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isDarkMode) Color(0xFF1E1B16) else Color(0xFFF1F5F9))
                            .padding(4.dp),
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        // Percent Toggle
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(8.dp))
                                .background(if (tempIsPercent) yellowPrimary else Color.Transparent)
                                .clickable { tempIsPercent = true }
                                .padding(vertical = 10.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Icon(
                                    Icons.Default.Percent,
                                    contentDescription = null,
                                    tint = if (tempIsPercent) Color.Black else secondaryText,
                                    modifier = Modifier.size(16.dp)
                                )
                                Text(
                                    "Percentage (%)",
                                    fontSize = 13.sp,
                                    fontWeight = if (tempIsPercent) FontWeight.Bold else FontWeight.Medium,
                                    color = if (tempIsPercent) Color.Black else secondaryText
                                )
                            }
                        }

                        // Flat BDT Toggle
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(8.dp))
                                .background(if (!tempIsPercent) yellowPrimary else Color.Transparent)
                                .clickable { tempIsPercent = false }
                                .padding(vertical = 10.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Text(
                                    "৳",
                                    fontSize = 15.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (!tempIsPercent) Color.Black else secondaryText
                                )
                                Text(
                                    "Flat Amount (৳)",
                                    fontSize = 13.sp,
                                    fontWeight = if (!tempIsPercent) FontWeight.Bold else FontWeight.Medium,
                                    color = if (!tempIsPercent) Color.Black else secondaryText
                                )
                            }
                        }
                    }

                    // Quick Preset Chips
                    Text(
                        if (tempIsPercent) "Quick Percent Presets (যেমন ৭%):" else "Quick Amount Presets (দ্রুত টাকা):",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = secondaryText
                    )
                    val percentChips = listOf(5.0, 7.0, 10.0, 15.0, 20.0, 25.0)
                    val flatChips = listOf(20.0, 50.0, 100.0, 200.0, 500.0)

                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (tempIsPercent) {
                            items(percentChips) { pct ->
                                val label = "${if (pct % 1.0 == 0.0) pct.toInt().toString() else pct}%"
                                val isSelected = tempDiscountText == (if (pct % 1.0 == 0.0) pct.toInt().toString() else pct.toString())
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = if (isSelected) yellowPrimary else if (isDarkMode) Color(0xFF261F13) else Color(0xFFF8FAFC),
                                    border = BorderStroke(1.dp, if (isSelected) yellowPrimary else cardBorder),
                                    modifier = Modifier.clickable {
                                        tempDiscountText = if (pct % 1.0 == 0.0) pct.toInt().toString() else pct.toString()
                                    }
                                ) {
                                    Text(
                                        text = label,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (isSelected) Color.Black else primaryText,
                                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                                    )
                                }
                            }
                        } else {
                            items(flatChips) { amt ->
                                val label = "৳${amt.toInt()}"
                                val isSelected = tempDiscountText == amt.toInt().toString()
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = if (isSelected) yellowPrimary else if (isDarkMode) Color(0xFF261F13) else Color(0xFFF8FAFC),
                                    border = BorderStroke(1.dp, if (isSelected) yellowPrimary else cardBorder),
                                    modifier = Modifier.clickable {
                                        tempDiscountText = amt.toInt().toString()
                                    }
                                ) {
                                    Text(
                                        text = label,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (isSelected) Color.Black else primaryText,
                                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                                    )
                                }
                            }
                        }
                    }

                    // Input Text Field
                    OutlinedTextField(
                        value = tempDiscountText,
                        onValueChange = { tempDiscountText = it.filter { c -> c.isDigit() || c == '.' } },
                        label = {
                            Text(
                                if (tempIsPercent) "কুপন / ডিসকাউন্ট শতাংশ (% যেমন: 7)" else "ডিসকাউন্ট নির্দিষ্ট টাকা (৳)",
                                color = secondaryText
                            )
                        },
                        trailingIcon = {
                            Text(
                                if (tempIsPercent) "% " else "৳ ",
                                fontWeight = FontWeight.Bold,
                                color = secondaryText
                            )
                        },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText,
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder
                        )
                    )

                    // Live Preview Card
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (isDarkMode) Color(0xFF1E1810) else Color(0xFFFFFBEB)
                        ),
                        border = BorderStroke(1.dp, yellowPrimary.copy(alpha = 0.4f))
                    ) {
                        Column(
                            modifier = Modifier.padding(12.dp),
                            verticalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("মূল সাবটোটাল:", fontSize = 12.sp, color = secondaryText)
                                Text("৳ ${String.format("%,.2f", calculatedSubtotal)}", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = primaryText)
                            }
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text(
                                    if (tempIsPercent) "ডিসকাউন্ট (${tempDiscountText.ifBlank { "0" }}%):" else "ডিসকাউন্ট (টাকা):",
                                    fontSize = 12.sp,
                                    color = discountRed
                                )
                                Text("- ৳ ${String.format("%,.2f", previewDiscountAmt)}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = discountRed)
                            }
                            HorizontalDivider(color = yellowPrimary.copy(alpha = 0.3f), modifier = Modifier.padding(vertical = 2.dp))
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("ডিসকাউন্টের পর মোট বিল:", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                Text("৳ ${String.format("%,.2f", previewNetTotal)}", fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = yellowPrimary)
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(4.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        if (tempDiscountText.isNotBlank() && tempVal > 0.0) {
                            OutlinedButton(
                                onClick = {
                                    tempDiscountText = ""
                                    isPercentageDiscount = false
                                    discountInputValue = ""
                                    showDiscountDialog = false
                                },
                                modifier = Modifier.weight(1f).height(48.dp),
                                shape = RoundedCornerShape(12.dp),
                                border = BorderStroke(1.dp, discountRed)
                            ) {
                                Text("মুছে ফেলুন", color = discountRed, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                            }
                        }

                        Button(
                            onClick = {
                                isPercentageDiscount = tempIsPercent
                                discountInputValue = tempDiscountText
                                showDiscountDialog = false
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary),
                            modifier = Modifier.weight(if (tempDiscountText.isNotBlank() && tempVal > 0.0) 2f else 1f).height(48.dp),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Text("প্রয়োগ করুন (Apply)", color = Color.Black, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }
                    }
                }
            }
        }

        // Completed Order Modal - Print & Share Official Invoice
        if (completedSaleEntity != null) {
            val sale = completedSaleEntity!!
            val customerCodeForSale = remember(sale, customers) {
                customers.firstOrNull { it.id == sale.customerId }?.code ?: selectedCustomerCode
            }
            val invoice = remember(sale, activeProfile, customerCodeForSale) {
                InvoiceDocumentManager.invoiceFromSale(
                    sale = sale,
                    businessName = activeProfile.businessName.ifBlank { "SwapnoPay Merchant" },
                    email = activeProfile.email,
                    phone = activeProfile.phone,
                    customerCode = customerCodeForSale
                )
            }

            EnterpriseGestureModal(
                onDismissRequest = {
                    completedSaleEntity = null
                    viewModel.clearPosCart()
                    selectedCustomerId = null
                    selectedCustomerName = "Walk-in Customer"
                    selectedCustomerPhone = ""
                    selectedCustomerCode = ""
                    discountInputValue = ""
                    isPercentageDiscount = false
                    customerGivenText = ""
                },
                title = "Print Invoice & Receipt",
                subtitle = "Invoice #${sale.invoiceNo}",
                icon = Icons.Default.Print
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Success Confirmation Banner
                    Surface(
                        shape = RoundedCornerShape(12.dp),
                        color = Color(0xFF10B981).copy(alpha = 0.12f),
                        border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.35f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(32.dp)
                                    .clip(CircleShape)
                                    .background(Color(0xFF10B981)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(Icons.Default.Check, null, tint = Color.White, modifier = Modifier.size(20.dp))
                            }
                            Column {
                                Text("Sale Completed Successfully", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = Color(0xFF10B981))
                                Text("Inventory deducted • Transaction logged", fontSize = 11.sp, color = secondaryText)
                            }
                        }
                    }

                    // ── THERMAL RECEIPT / INVOICE PREVIEW PAPER ──
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(14.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (isDarkMode) Color(0xFF12131A) else Color(0xFFFFFFFF)
                        ),
                        border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF332612) else Color(0xFFE2E8F0))
                    ) {
                        Column(
                            modifier = Modifier.padding(16.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            // Store Header
                            Column(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Text(
                                    text = invoice.businessName,
                                    fontWeight = FontWeight.ExtraBold,
                                    fontSize = 17.sp,
                                    color = primaryText
                                )
                                if (invoice.businessPhone.isNotBlank()) {
                                    Text(
                                        text = "Phone: ${invoice.businessPhone}",
                                        fontSize = 11.5.sp,
                                        color = secondaryText
                                    )
                                }
                                Text(
                                    text = "--------------------------------",
                                    fontSize = 11.sp,
                                    color = secondaryText.copy(alpha = 0.4f),
                                    letterSpacing = 1.sp
                                )
                            }

                            // Invoice Metadata
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Invoice No:", fontSize = 12.sp, color = secondaryText)
                                Text(sale.invoiceNo, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = primaryText)
                            }
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Date & Time:", fontSize = 12.sp, color = secondaryText)
                                Text(
                                    SimpleDateFormat("dd MMM yyyy, hh:mm a", Locale.getDefault()).format(Date(sale.timestamp)),
                                    fontSize = 12.sp,
                                    color = primaryText
                                )
                            }
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Customer:", fontSize = 12.sp, color = secondaryText)
                                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                    Text(sale.customerName, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                    if (customerCodeForSale.isNotBlank()) {
                                        Surface(
                                            shape = RoundedCornerShape(4.dp),
                                            color = yellowPrimary.copy(alpha = 0.2f),
                                            border = BorderStroke(1.dp, yellowPrimary.copy(alpha = 0.5f))
                                        ) {
                                            Text(
                                                customerCodeForSale,
                                                fontSize = 9.5.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = yellowPrimary,
                                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                            )
                                        }
                                    }
                                }
                            }
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Payment:", fontSize = 12.sp, color = secondaryText)
                                Text(
                                    "${sale.paymentMethod} (${sale.paymentStatus})",
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = if (sale.paymentStatus == "DUE") Color(0xFFEF4444) else Color(0xFF10B981)
                                )
                            }

                            HorizontalDivider(
                                modifier = Modifier.padding(vertical = 4.dp),
                                color = if (isDarkMode) Color(0xFF2A2B35) else Color(0xFFE2E8F0)
                            )

                            // Items Header
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("ITEM", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = secondaryText, modifier = Modifier.weight(1.8f))
                                Text("QTY", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = secondaryText, modifier = Modifier.weight(0.7f))
                                Text("TOTAL", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = secondaryText, modifier = Modifier.weight(1f), textAlign = TextAlign.End)
                            }

                            // Items
                            invoice.lines.forEach { line ->
                                Row(
                                    modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1.8f)) {
                                        Text(line.description, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = primaryText)
                                        Text("@ ৳${String.format("%,.2f", line.unitPrice)}", fontSize = 10.sp, color = secondaryText)
                                    }
                                    Text(
                                        text = if (line.quantity % 1.0 == 0.0) line.quantity.toInt().toString() else String.format("%.1f", line.quantity),
                                        fontSize = 12.sp,
                                        color = primaryText,
                                        modifier = Modifier.weight(0.7f)
                                    )
                                    Text(
                                        text = "৳${String.format("%,.2f", line.lineTotal)}",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = primaryText,
                                        modifier = Modifier.weight(1f),
                                        textAlign = TextAlign.End
                                    )
                                }
                            }

                            HorizontalDivider(
                                modifier = Modifier.padding(vertical = 4.dp),
                                color = if (isDarkMode) Color(0xFF2A2B35) else Color(0xFFE2E8F0)
                            )

                            // Totals
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Subtotal", fontSize = 12.sp, color = secondaryText)
                                Text("৳${String.format("%,.2f", sale.subtotal)}", fontSize = 12.sp, color = primaryText)
                            }
                            if (sale.discount > 0.0) {
                                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                    Text("Discount", fontSize = 12.sp, color = discountRed)
                                    Text("-৳${String.format("%,.2f", sale.discount)}", fontSize = 12.sp, color = discountRed)
                                }
                            }
                            Row(
                                Modifier.fillMaxWidth().padding(top = 2.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text("Grand Total", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = primaryText)
                                Text(
                                    "৳${String.format("%,.2f", sale.netTotal)} BDT",
                                    fontSize = 17.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = yellowPrimary
                                )
                            }
                            if (sale.cashReceived > 0.0) {
                                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                    Text("Customer Given (প্রাপ্ত)", fontSize = 12.sp, color = secondaryText)
                                    Text("৳${String.format("%,.2f", sale.cashReceived)}", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = primaryText)
                                }
                            }
                            if (sale.changeDue > 0.0) {
                                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                    Text("Change Returned (ফেরত)", fontSize = 12.sp, color = Color(0xFF10B981))
                                    Text("৳${String.format("%,.2f", sale.changeDue)}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                                }
                            }
                            val remainingDueOnReceipt = (sale.netTotal - sale.cashReceived).coerceAtLeast(0.0)
                            if (remainingDueOnReceipt > 0.0 && sale.paymentStatus != "PAID") {
                                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                    Text("Due Amount (বাকি)", fontSize = 12.sp, color = Color(0xFFEF4444))
                                    Text("৳${String.format("%,.2f", remainingDueOnReceipt)}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFFEF4444))
                                }
                            }
                        }
                    }

                    // ── 1. PRIMARY PRINT INVOICE BUTTON ──
                    Button(
                        onClick = {
                            InvoiceDocumentManager.print(context, invoice)
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary),
                        modifier = Modifier.fillMaxWidth().height(52.dp),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Icon(Icons.Default.Print, contentDescription = null, tint = Color.Black, modifier = Modifier.size(22.dp))
                            Text("Print Invoice", color = Color.Black, fontWeight = FontWeight.ExtraBold, fontSize = 16.sp)
                        }
                    }

                    // ── 2. SECONDARY ACTIONS: Share PDF & Full Invoice View ──
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        OutlinedButton(
                            onClick = {
                                InvoiceDocumentManager.share(context, invoice)
                            },
                            modifier = Modifier.weight(1f).height(46.dp),
                            shape = RoundedCornerShape(10.dp),
                            border = BorderStroke(1.dp, yellowPrimary.copy(alpha = 0.6f))
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Icon(Icons.Default.Share, null, tint = yellowPrimary, modifier = Modifier.size(17.dp))
                                Text("Share PDF", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = yellowPrimary)
                            }
                        }

                        OutlinedButton(
                            onClick = {
                                viewModel.selectInvoiceSale(sale.id)
                                viewModel.navigateTo("PrintInvoice")
                                completedSaleEntity = null
                                viewModel.clearPosCart()
                            },
                            modifier = Modifier.weight(1f).height(46.dp),
                            shape = RoundedCornerShape(10.dp),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Icon(Icons.Default.OpenInNew, null, tint = primaryText, modifier = Modifier.size(17.dp))
                                Text("Full Invoice", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = primaryText)
                            }
                        }
                    }

                    // ── 3. START NEW SALE BUTTON ──
                    Button(
                        onClick = {
                            completedSaleEntity = null
                            viewModel.clearPosCart()
                            selectedCustomerId = null
                            selectedCustomerName = "Walk-in Customer"
                            selectedCustomerPhone = ""
                            selectedCustomerCode = ""
                            discountInputValue = ""
                            isPercentageDiscount = false
                            customerGivenText = ""
                        },
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (isDarkMode) Color(0xFF22232E) else Color(0xFFE2E8F0)
                        ),
                        modifier = Modifier.fillMaxWidth().height(46.dp),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(Icons.Default.Add, null, tint = primaryText, modifier = Modifier.size(18.dp))
                            Text("Start New Sale", color = primaryText, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }
                    }
                }
            }
        }
    }

    // ── SELECT VARIANT DIALOG ───────────────────────────────────────
    if (selectedProductForVariants != null) {
        val targetProduct = selectedProductForVariants!!
        val variants = productVariants.filter { it.productId == targetProduct.id }
        SelectVariantPosDialog(
            product = targetProduct,
            variants = variants,
            posCart = posCart,
            isDarkMode = isDarkMode,
            onAddVariant = { v -> viewModel.addVariantToPosCart(targetProduct, v, 1.0) },
            onUpdateQty = { variantId, qty -> viewModel.updatePosCartItemQuantity(variantId, qty) },
            onDismiss = { selectedProductForVariants = null }
        )
    }

    // ── IN-SCREEN POS CAMERA SCANNER DIALOG ─────────────────────────
    if (showInScreenScanner) {
        InScreenPosScannerDialog(
            viewModel = viewModel,
            isDarkMode = isDarkMode,
            onDismiss = { showInScreenScanner = false }
        )
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// POS SELECT VARIANT DIALOG
// ═══════════════════════════════════════════════════════════════════════════
@Composable
private fun SelectVariantPosDialog(
    product: com.example.data.local.ProductItemEntity,
    variants: List<ProductVariantEntity>,
    posCart: List<PosCartItem>,
    isDarkMode: Boolean,
    onAddVariant: (ProductVariantEntity) -> Unit,
    onUpdateQty: (String, Double) -> Unit,
    onDismiss: () -> Unit
) {
    val cardBg = if (isDarkMode) Color(0xFF13100C) else Color(0xFFFFFFFF)
    val cardBorder = if (isDarkMode) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val primaryText = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val secondaryText = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val yellowPrimary = if (isDarkMode) Color(0xFFE5A93C) else Color(0xFFFFC800)

    androidx.compose.ui.window.Dialog(onDismissRequest = onDismiss) {
        Surface(
            shape = RoundedCornerShape(20.dp),
            color = cardBg,
            border = BorderStroke(1.dp, cardBorder),
            modifier = Modifier.fillMaxWidth().padding(8.dp)
        ) {
            Column(modifier = Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                // Header
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = product.name,
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = primaryText
                        )
                        Text(
                            text = "ভ্যারিয়েন্ট নির্বাচন করুন (${variants.size} টি পাওয়া গেছে)",
                            fontSize = 11.5.sp,
                            color = secondaryText
                        )
                    }

                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.Close, contentDescription = "Close", tint = secondaryText)
                    }
                }

                HorizontalDivider(color = cardBorder, thickness = 0.5.dp)

                if (variants.isEmpty()) {
                    Box(
                        modifier = Modifier.fillMaxWidth().padding(vertical = 20.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text("এই পণ্যের কোনো ভ্যারিয়েন্ট যোগ করা নেই", color = secondaryText, fontSize = 13.sp)
                    }
                } else {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .heightIn(max = 350.dp)
                            .verticalScroll(rememberScrollState()),
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        variants.forEach { v ->
                            val inCartItem = posCart.find { it.variantId == v.id }
                            val stockInt = v.stockQuantity.toInt()

                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF1C1812) else Color(0xFFF8FAFC)),
                                border = BorderStroke(1.dp, if (inCartItem != null) yellowPrimary else cardBorder)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth().padding(12.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(
                                            text = v.variantName,
                                            fontSize = 14.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = primaryText
                                        )
                                        Spacer(modifier = Modifier.height(2.dp))
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            Text(
                                                text = "৳ ${String.format("%,.0f", v.salePrice)}",
                                                fontSize = 13.sp,
                                                fontWeight = FontWeight.SemiBold,
                                                color = yellowPrimary
                                            )
                                            Text("•", fontSize = 10.sp, color = secondaryText)
                                            Text(
                                                text = if (stockInt <= 0) "Stock Out" else "স্টক: $stockInt টি",
                                                fontSize = 11.sp,
                                                color = if (stockInt <= 0) Color(0xFFDC2626) else Color(0xFF10B981)
                                            )
                                        }
                                        if (v.qrCode.isNotBlank()) {
                                            Text(
                                                text = "QR: ${v.qrCode}",
                                                fontSize = 10.sp,
                                                color = secondaryText
                                            )
                                        }
                                    }

                                    // Add or Stepper
                                    if (inCartItem != null) {
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            Box(
                                                modifier = Modifier
                                                    .size(28.dp)
                                                    .clip(RoundedCornerShape(6.dp))
                                                    .background(if (isDarkMode) Color(0xFF261F13) else Color(0xFFE2E8F0))
                                                    .clickable { onUpdateQty(v.id, inCartItem.quantity - 1.0) },
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Icon(Icons.Default.Remove, null, tint = primaryText, modifier = Modifier.size(14.dp))
                                            }
                                            Text(
                                                "${inCartItem.quantity.toInt()}",
                                                fontSize = 13.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = primaryText
                                            )
                                            Box(
                                                modifier = Modifier
                                                    .size(28.dp)
                                                    .clip(RoundedCornerShape(6.dp))
                                                    .background(yellowPrimary)
                                                    .clickable { onUpdateQty(v.id, inCartItem.quantity + 1.0) },
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Icon(Icons.Default.Add, null, tint = Color.Black, modifier = Modifier.size(14.dp))
                                            }
                                        }
                                    } else {
                                        Button(
                                            onClick = { onAddVariant(v) },
                                            shape = RoundedCornerShape(8.dp),
                                            colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black),
                                            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp),
                                            modifier = Modifier.height(32.dp)
                                        ) {
                                            Row(
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                                            ) {
                                                Icon(Icons.Default.Add, null, tint = Color.Black, modifier = Modifier.size(14.dp))
                                                Text("Add", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                Button(
                    onClick = onDismiss,
                    modifier = Modifier.fillMaxWidth().height(44.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = if (isDarkMode) Color(0xFF201B15) else Color(0xFFF1F5F9))
                ) {
                    Text("সম্পন্ন (Done)", color = primaryText, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// IN-SCREEN POS CAMERA SCANNER DIALOG
// ═══════════════════════════════════════════════════════════════════════════
@Composable
private fun InScreenPosScannerDialog(
    viewModel: AppViewModel,
    isDarkMode: Boolean,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    val lastStatus by viewModel.lastScannedQrStatus.collectAsState()
    val posCart by viewModel.posCart.collectAsState()
    val totalCount = posCart.sumOf { it.quantity.toInt() }

    val cardBg = if (isDarkMode) Color(0xFF13100C) else Color(0xFFFFFFFF)
    val cardBorder = if (isDarkMode) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val primaryText = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val secondaryText = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val yellowPrimary = if (isDarkMode) Color(0xFFE5A93C) else Color(0xFFFFC800)

    androidx.compose.ui.window.Dialog(onDismissRequest = onDismiss) {
        Surface(
            shape = RoundedCornerShape(20.dp),
            color = cardBg,
            border = BorderStroke(1.dp, cardBorder),
            modifier = Modifier.fillMaxWidth().padding(6.dp)
        ) {
            Column(modifier = Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                // Header
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Icon(Icons.Default.QrCodeScanner, null, tint = yellowPrimary, modifier = Modifier.size(22.dp))
                        Column {
                            Text("দ্রুত বারকোড স্ক্যানার", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = primaryText)
                            Text("কার্টে মোট: $totalCount টি আইটেম", fontSize = 11.5.sp, color = Color(0xFF10B981))
                        }
                    }

                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.Close, null, tint = secondaryText)
                    }
                }

                // Camera Container
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(240.dp)
                        .clip(RoundedCornerShape(14.dp))
                        .border(1.5.dp, yellowPrimary, RoundedCornerShape(14.dp))
                ) {
                    RealQrCameraScanner(
                        onQrScanned = { raw ->
                            val trimmed = raw.trim()
                            if (trimmed.isNotEmpty()) {
                                viewModel.scanQrCodeToPosCart(trimmed)
                                Toast.makeText(context, "Scanned: $trimmed", Toast.LENGTH_SHORT).show()
                            }
                        },
                        modifier = Modifier.fillMaxSize()
                    )
                }

                // Status Message Box
                if (lastStatus != null) {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = if (isDarkMode) Color(0xFF1E1A11) else Color(0xFFFFFBEB),
                        border = BorderStroke(0.8.dp, yellowPrimary.copy(alpha = 0.5f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = lastStatus!!,
                            color = yellowPrimary,
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 11.5.sp,
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                        )
                    }
                }

                // Bottom Action Buttons
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(
                        onClick = onDismiss,
                        modifier = Modifier.weight(1f).height(44.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black)
                    ) {
                        Text("কার্ট দেখুন (${totalCount})", fontWeight = FontWeight.Bold, fontSize = 12.5.sp)
                    }

                    OutlinedButton(
                        onClick = {
                            onDismiss()
                            viewModel.navigateTo("QrScanner")
                        },
                        modifier = Modifier.weight(1f).height(44.dp),
                        shape = RoundedCornerShape(10.dp),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Text("ফুল স্ক্রিন স্ক্যানার", color = primaryText, fontSize = 11.5.sp)
                    }
                }
            }
        }
    }
}