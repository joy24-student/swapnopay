@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
package com.example.ui

import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.example.data.local.ProductItemEntity
import com.example.data.local.ProductVariantEntity

// INVENTORY & QR BINDING SCREEN — Pixel-Perfect Redesign & Barcode Binding
@Composable
fun InventoryScreen(viewModel: AppViewModel) {
    val products by viewModel.products.collectAsState()
    val productVariants by viewModel.productVariants.collectAsState()
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    SideEffect { isDarkModeGlobal = isDarkMode }

    val context = LocalContext.current
    var searchQuery by remember { mutableStateOf("") }
    var showAddDialog by remember { mutableStateOf(false) }
    var editingProduct by remember { mutableStateOf<ProductItemEntity?>(null) }
    var productToDelete by remember { mutableStateOf<ProductItemEntity?>(null) }
    var bindingQrProduct by remember { mutableStateOf<ProductItemEntity?>(null) }
    var managingVariantsProduct by remember { mutableStateOf<ProductItemEntity?>(null) }
    var showBarcodeScannerForAdd by remember { mutableStateOf(false) }
    var showBarcodeScannerForEdit by remember { mutableStateOf(false) }

    // State for Add Product Modal
    var newProdName by remember { mutableStateOf("") }
    var newProdCode by remember { mutableStateOf("") }
    var newProdCategory by remember { mutableStateOf("General") }
    var newProdBuyPrice by remember { mutableStateOf("") }
    var newProdSellPrice by remember { mutableStateOf("") }
    var newProdStock by remember { mutableStateOf("") }
    var newProdUnit by remember { mutableStateOf("pcs") }

    val bgCanvas = if (isDarkMode) Color(0xFF070707) else Color(0xFFFAFAFC)
    val cardBg = if (isDarkMode) Color(0xFF0D0B07) else Color(0xFFFFFFFF)
    val containerBg = if (isDarkMode) Color(0xFF13100A) else Color(0xFFF8FAFC)
    val cardBorder = if (isDarkMode) Color(0xFF382A0B) else Color(0xFFE2E8F0)

    val primaryText = if (isDarkMode) Color(0xFFF3F4F6) else Color(0xFF0F172A)
    val secondaryText = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)

    val yellowPrimary = if (isDarkMode) Color(0xFFF5C518) else Color(0xFFFFC800)
    val yellowText = if (isDarkMode) Color(0xFFF5C518) else Color(0xFFD97706)
    val yellowBadgeBg = if (isDarkMode) Color(0xFF261D07) else Color(0xFFFFFBEB)
    val yellowBadgeBorder = if (isDarkMode) Color(0xFF4A380A) else Color(0xFFFDE68A)

    val totalItemsCount = products.sumOf { it.stockQuantity.toInt() }
    val totalInventoryAmount = products.sumOf { it.stockQuantity * it.purchasePrice }
    val limitedItemsCount = products.count { it.stockQuantity <= it.minStockThreshold }

    val filteredProducts = remember(products, searchQuery) {
        if (searchQuery.isBlank()) products else {
            products.filter { prod ->
                prod.name.contains(searchQuery, ignoreCase = true) ||
                (prod.category?.contains(searchQuery, ignoreCase = true) == true) ||
                (prod.code?.contains(searchQuery, ignoreCase = true) == true)
            }
        }
    }

    Scaffold(
        containerColor = bgCanvas,
        topBar = {
            GradientTopBar(
                title = "Inventory",
                subtitle = "পণ্যের হিসাব ও স্টক ম্যানেজমেন্ট",
                onBack = { viewModel.goBack() },
                actions = {
                    // Scan QR Camera Button
                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .clip(CircleShape)
                            .background(if (isDarkMode) Color(0xFF0D0B07) else Color(0xFFFFFBEB))
                            .border(BorderStroke(1.dp, if (isDarkMode) Color(0xFF5A441B) else Color(0xFFFDE68A)), CircleShape)
                            .clickable { viewModel.navigateTo("QrScanner") },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.QrCodeScanner,
                            contentDescription = "Scan QR",
                            tint = yellowText,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(bgCanvas)
        ) {
            Spacer(modifier = Modifier.height(10.dp))

            // ── SEARCH & FILTER ROW ─────────────────────────────────────
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { searchQuery = it },
                    placeholder = {
                        Text(
                            "Search product, category or SKU",
                            fontSize = 13.sp,
                            color = secondaryText
                        )
                    },
                    leadingIcon = {
                        Icon(
                            imageVector = Icons.Default.Search,
                            contentDescription = null,
                            tint = secondaryText,
                            modifier = Modifier.size(20.dp)
                        )
                    },
                    trailingIcon = {
                        IconButton(onClick = { viewModel.navigateTo("QrScanner") }) {
                            Icon(
                                imageVector = Icons.Default.QrCodeScanner,
                                contentDescription = "Scan Barcode",
                                tint = yellowText,
                                modifier = Modifier.size(20.dp)
                            )
                        }
                    },
                    modifier = Modifier
                        .weight(1f)
                        .height(50.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = containerBg,
                        unfocusedContainerColor = containerBg,
                        focusedBorderColor = yellowPrimary,
                        unfocusedBorderColor = cardBorder,
                        focusedTextColor = primaryText,
                        unfocusedTextColor = primaryText
                    ),
                    singleLine = true
                )

                Button(
                    onClick = { showAddDialog = true },
                    modifier = Modifier.height(50.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = yellowPrimary,
                        contentColor = Color.Black
                    ),
                    contentPadding = PaddingValues(horizontal = 14.dp)
                ) {
                    Icon(Icons.Default.Add, contentDescription = "Add Item", tint = Color.Black, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Add Item", fontWeight = FontWeight.Bold, color = Color.Black, fontSize = 13.sp, maxLines = 1)
                }
            }

            Spacer(modifier = Modifier.height(14.dp))

            // ── SCROLLABLE LIST WITH NON-FIXED OVERVIEW CARD ────────────
            LazyColumn(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
                contentPadding = PaddingValues(bottom = 24.dp)
            ) {
                // 1. SUMMARY METRICS CARD
                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = cardBg),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 16.dp, horizontal = 12.dp),
                            horizontalArrangement = Arrangement.SpaceEvenly,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            // Metric 1: Total Items
                            Column(
                                modifier = Modifier.weight(1f),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(38.dp)
                                        .clip(CircleShape)
                                        .background(yellowBadgeBg)
                                        .border(1.dp, yellowBadgeBorder, CircleShape),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Outlined.Store,
                                        contentDescription = null,
                                        tint = yellowText,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .width(3.dp)
                                            .height(11.dp)
                                            .clip(RoundedCornerShape(1.dp))
                                            .background(yellowPrimary)
                                    )
                                    Text(
                                        text = "Total Items",
                                        fontSize = 11.sp,
                                        color = secondaryText
                                    )
                                }
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(
                                    text = String.format("%,d", totalItemsCount),
                                    fontSize = 18.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "Items",
                                    fontSize = 11.sp,
                                    color = secondaryText
                                )
                            }

                            // Vertical Divider 1
                            Box(
                                modifier = Modifier
                                    .width(1.dp)
                                    .height(50.dp)
                                    .background(cardBorder)
                            )

                            // Metric 2: Total Value
                            Column(
                                modifier = Modifier.weight(1f),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(38.dp)
                                        .clip(CircleShape)
                                        .background(yellowBadgeBg)
                                        .border(1.dp, yellowBadgeBorder, CircleShape),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.AccountBalanceWallet,
                                        contentDescription = null,
                                        tint = yellowText,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .width(3.dp)
                                            .height(11.dp)
                                            .clip(RoundedCornerShape(1.dp))
                                            .background(yellowPrimary)
                                    )
                                    Text(
                                        text = "Total Value",
                                        fontSize = 11.sp,
                                        color = secondaryText
                                    )
                                }
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(
                                    text = "৳ ${String.format("%,.0f", totalInventoryAmount)}",
                                    fontSize = 17.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "Inventory Cost",
                                    fontSize = 11.sp,
                                    color = secondaryText
                                )
                            }

                            // Vertical Divider 2
                            Box(
                                modifier = Modifier
                                    .width(1.dp)
                                    .height(50.dp)
                                    .background(cardBorder)
                            )

                            // Metric 3: Low Stock Alert
                            Column(
                                modifier = Modifier.weight(1f),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(38.dp)
                                        .clip(CircleShape)
                                        .background(if (limitedItemsCount > 0) Color(0xFF3B1212) else yellowBadgeBg)
                                        .border(
                                            1.dp,
                                            if (limitedItemsCount > 0) Color(0xFFEF4444) else yellowBadgeBorder,
                                            CircleShape
                                        ),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Warning,
                                        contentDescription = null,
                                        tint = if (limitedItemsCount > 0) Color(0xFFEF4444) else yellowText,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .width(3.dp)
                                            .height(11.dp)
                                            .clip(RoundedCornerShape(1.dp))
                                            .background(if (limitedItemsCount > 0) Color(0xFFEF4444) else yellowPrimary)
                                    )
                                    Text(
                                        text = "Low Stock",
                                        fontSize = 11.sp,
                                        color = secondaryText
                                    )
                                }
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(
                                    text = "$limitedItemsCount",
                                    fontSize = 18.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (limitedItemsCount > 0) Color(0xFFEF4444) else primaryText
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "Alerts",
                                    fontSize = 11.sp,
                                    color = if (limitedItemsCount > 0) Color(0xFFEF4444) else secondaryText
                                )
                            }
                        }
                    }
                }

                // 2. PRODUCT LIST ITEMS
                if (filteredProducts.isEmpty()) {
                    item {
                        Card(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 24.dp),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Column(
                                modifier = Modifier.fillMaxWidth().padding(32.dp),
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                Icon(Icons.Outlined.Inventory2, contentDescription = null, tint = yellowText, modifier = Modifier.size(48.dp))
                                Text(
                                    text = if (searchQuery.isNotBlank()) "No products match \"$searchQuery\"" else "No products in inventory yet",
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                                Text(
                                    text = "Add new products to start tracking stock, barcodes, and POS sales.",
                                    fontSize = 12.sp,
                                    color = secondaryText,
                                    textAlign = TextAlign.Center
                                )
                                Button(
                                    onClick = { showAddDialog = true },
                                    colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black),
                                    shape = RoundedCornerShape(10.dp)
                                ) {
                                    Icon(Icons.Default.Add, contentDescription = null, tint = Color.Black, modifier = Modifier.size(16.dp))
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text("Add First Product", fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                } else {
                    items(filteredProducts, key = { it.id }) { prod ->
                        var showMenu by remember { mutableStateOf(false) }

                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Column(modifier = Modifier.padding(14.dp)) {
                                // Row 1: Avatar, Name, Category, SKU, 3-Dot Menu
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(48.dp)
                                            .clip(RoundedCornerShape(10.dp))
                                            .background(yellowBadgeBg),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        val effectiveProductImage = remember(prod.imageUrl, prod.storefrontDetailsJson) {
                                            prod.imageUrl?.takeIf { it.isNotBlank() }
                                                ?: com.example.data.local.ProductStorefrontDetails.parse(prod.storefrontDetailsJson).featuredImage?.preview?.takeIf { it.isNotBlank() }
                                        }
                                        val resolvedProdModel = rememberResolvedImageModel(effectiveProductImage)
                                        if (resolvedProdModel != null) {
                                            AsyncImage(
                                                model = resolvedProdModel,
                                                contentDescription = prod.name,
                                                contentScale = ContentScale.Crop,
                                                modifier = Modifier.fillMaxSize()
                                            )
                                        } else {
                                            Text(
                                                prod.name.take(1).uppercase(),
                                                fontSize = 20.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = yellowText
                                            )
                                        }
                                    }

                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(
                                            prod.name,
                                            fontSize = 15.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = primaryText
                                        )
                                        Spacer(modifier = Modifier.height(2.dp))
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            Text(
                                                prod.category ?: "General",
                                                fontSize = 11.sp,
                                                color = secondaryText
                                            )
                                            Text("•", fontSize = 11.sp, color = secondaryText)
                                            Text(
                                                "SKU: ${prod.code ?: "GEN"}",
                                                fontSize = 11.sp,
                                                color = yellowText
                                            )
                                        }

                                        Spacer(modifier = Modifier.height(5.dp))
                                        val itemVariants = productVariants.filter { it.productId == prod.id }
                                        val hasQr = !prod.qrCode.isNullOrBlank() || !prod.code.isNullOrBlank()
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            // QR Code Badge
                                            Surface(
                                                shape = RoundedCornerShape(6.dp),
                                                color = if (hasQr) (if (isDarkMode) Color(0xFF1E1A11) else Color(0xFFFFFBEB)) else (if (isDarkMode) Color(0xFF16120B) else Color(0xFFF1F5F9)),
                                                border = BorderStroke(0.8.dp, if (hasQr) yellowBadgeBorder else cardBorder),
                                                modifier = Modifier.clickable { bindingQrProduct = prod }
                                            ) {
                                                Row(
                                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp),
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(3.dp)
                                                ) {
                                                    Icon(
                                                        if (hasQr) Icons.Default.QrCode else Icons.Default.QrCodeScanner,
                                                        contentDescription = null,
                                                        tint = if (hasQr) yellowText else secondaryText,
                                                        modifier = Modifier.size(11.dp)
                                                    )
                                                    Text(
                                                        if (hasQr) (prod.qrCode ?: prod.code ?: "QR Attached") else "+ Bind QR",
                                                        fontSize = 10.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = if (hasQr) yellowText else secondaryText,
                                                        maxLines = 1
                                                    )
                                                }
                                            }

                                            // Variants Badge
                                            Surface(
                                                shape = RoundedCornerShape(6.dp),
                                                color = if (itemVariants.isNotEmpty()) (if (isDarkMode) Color(0xFF1A182E) else Color(0xFFEEF2FF)) else (if (isDarkMode) Color(0xFF121212) else Color(0xFFF8FAFC)),
                                                border = BorderStroke(0.8.dp, if (itemVariants.isNotEmpty()) Color(0xFF6366F1).copy(alpha = 0.5f) else cardBorder),
                                                modifier = Modifier.clickable { managingVariantsProduct = prod }
                                            ) {
                                                Row(
                                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp),
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(3.dp)
                                                ) {
                                                    Icon(
                                                        Icons.Default.Layers,
                                                        contentDescription = null,
                                                        tint = if (itemVariants.isNotEmpty()) Color(0xFF818CF8) else secondaryText,
                                                        modifier = Modifier.size(11.dp)
                                                    )
                                                    Text(
                                                        if (itemVariants.isNotEmpty()) "${itemVariants.size} Variants" else "+ Variants",
                                                        fontSize = 10.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = if (itemVariants.isNotEmpty()) Color(0xFF818CF8) else secondaryText
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    // 3-DOT OVERFLOW MENU (Edit, Delete, Scan QR, Variants)
                                    Box {
                                        IconButton(onClick = { showMenu = true }) {
                                            Icon(
                                                imageVector = Icons.Default.MoreVert,
                                                contentDescription = "Options",
                                                tint = primaryText
                                            )
                                        }

                                        DropdownMenu(
                                            expanded = showMenu,
                                            onDismissRequest = { showMenu = false },
                                            modifier = Modifier.background(cardBg).border(1.dp, cardBorder, RoundedCornerShape(8.dp))
                                        ) {
                                            DropdownMenuItem(
                                                text = {
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                                    ) {
                                                        Icon(Icons.Default.Edit, contentDescription = null, tint = yellowText, modifier = Modifier.size(16.dp))
                                                        Text("Edit Product", color = primaryText, fontSize = 13.sp)
                                                    }
                                                },
                                                onClick = {
                                                    showMenu = false
                                                    editingProduct = prod
                                                }
                                            )

                                            DropdownMenuItem(
                                                text = {
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                                    ) {
                                                        Icon(Icons.Default.QrCodeScanner, contentDescription = null, tint = yellowText, modifier = Modifier.size(16.dp))
                                                        Text("Scan / Bind QR", color = primaryText, fontSize = 13.sp)
                                                    }
                                                },
                                                onClick = {
                                                    showMenu = false
                                                    bindingQrProduct = prod
                                                }
                                            )

                                            DropdownMenuItem(
                                                text = {
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                                    ) {
                                                        Icon(Icons.Default.Layers, contentDescription = null, tint = Color(0xFF818CF8), modifier = Modifier.size(16.dp))
                                                        Text("Manage Variants & Serials", color = primaryText, fontSize = 13.sp)
                                                    }
                                                },
                                                onClick = {
                                                    showMenu = false
                                                    managingVariantsProduct = prod
                                                }
                                            )

                                            HorizontalDivider(color = cardBorder, thickness = 0.5.dp)

                                            DropdownMenuItem(
                                                text = {
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                                    ) {
                                                        Icon(Icons.Default.Delete, contentDescription = null, tint = Color(0xFFEF4444), modifier = Modifier.size(16.dp))
                                                        Text("Delete Product", color = Color(0xFFEF4444), fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                                                    }
                                                },
                                                onClick = {
                                                    showMenu = false
                                                    productToDelete = prod
                                                }
                                            )
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(10.dp))

                                // Row 2: 2x2 Grid with Stock, Buy, Sell, Value
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Column(
                                        modifier = Modifier.weight(1f),
                                        verticalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                                        ) {
                                            Icon(Icons.Outlined.Store, contentDescription = null, tint = yellowText, modifier = Modifier.size(13.dp))
                                            Text("Stock", fontSize = 11.sp, color = secondaryText)
                                            Spacer(modifier = Modifier.weight(1f))
                                            Text("${prod.stockQuantity.toInt()} ${prod.unit}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                        }

                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                                        ) {
                                            Icon(Icons.Default.ShoppingCart, contentDescription = null, tint = yellowText, modifier = Modifier.size(13.dp))
                                            Text("Buy", fontSize = 11.sp, color = secondaryText)
                                            Spacer(modifier = Modifier.weight(1f))
                                            Text("৳ ${String.format("%,.0f", prod.purchasePrice)}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                        }
                                    }

                                    Box(
                                        modifier = Modifier
                                            .padding(horizontal = 12.dp)
                                            .width(1.dp)
                                            .height(38.dp)
                                            .background(cardBorder)
                                    )

                                    Column(
                                        modifier = Modifier.weight(1f),
                                        verticalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                                        ) {
                                            Icon(Icons.Default.Sell, contentDescription = null, tint = yellowText, modifier = Modifier.size(13.dp))
                                            Text("Sell", fontSize = 11.sp, color = secondaryText)
                                            Spacer(modifier = Modifier.weight(1f))
                                            Text("৳ ${String.format("%,.0f", prod.salePrice)}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = primaryText)
                                        }

                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                                        ) {
                                            Icon(Icons.Default.MonetizationOn, contentDescription = null, tint = yellowText, modifier = Modifier.size(13.dp))
                                            Text("Value", fontSize = 11.sp, color = secondaryText)
                                            Spacer(modifier = Modifier.weight(1f))
                                            Text("৳ ${String.format("%,.0f", prod.stockQuantity * prod.purchasePrice)}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = primaryText)
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

    // ── DELETE PRODUCT CONFIRMATION MODAL ─────────────────────────────
    if (productToDelete != null) {
        val targetProd = productToDelete!!
        var isDeleting by remember { mutableStateOf(false) }

        EnterpriseGestureModal(
            onDismissRequest = { if (!isDeleting) productToDelete = null },
            title = "পণ্য মুছে ফেলুন (Delete Product)",
            subtitle = "Confirmation required",
            icon = Icons.Default.DeleteForever
        ) {
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(if (isDarkMode) Color(0xFF261212) else Color(0xFFFEF2F2), RoundedCornerShape(12.dp))
                        .border(1.dp, if (isDarkMode) Color(0xFF5A1C1C) else Color(0xFFFCA5A5), RoundedCornerShape(12.dp))
                        .padding(14.dp)
                ) {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.WarningAmber, null, tint = Color(0xFFEF4444), modifier = Modifier.size(24.dp))
                        Column {
                            Text(
                                text = "আপনি কি নিশ্চিত যে '${targetProd.name}' মুছে ফেলতে চান?",
                                fontWeight = FontWeight.Bold,
                                fontSize = 13.5.sp,
                                color = if (isDarkMode) Color(0xFFFCA5A5) else Color(0xFF991B1B)
                            )
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(
                                text = "SKU: ${targetProd.code ?: "N/A"} • Stock: ${targetProd.stockQuantity.toInt()} ${targetProd.unit}. This item will be removed permanently.",
                                fontSize = 11.5.sp,
                                color = if (isDarkMode) Color(0xFFF87171) else Color(0xFFB91C1C)
                            )
                        }
                    }
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    OutlinedButton(
                        onClick = { productToDelete = null },
                        modifier = Modifier.weight(1f).height(48.dp),
                        shape = RoundedCornerShape(12.dp),
                        enabled = !isDeleting
                    ) {
                        Text("Cancel (বাতিল)", fontWeight = FontWeight.SemiBold)
                    }

                    Button(
                        onClick = {
                            isDeleting = true
                            viewModel.deleteProduct(targetProd.id) { success, message ->
                                isDeleting = false
                                Toast.makeText(context, message, if (success) Toast.LENGTH_SHORT else Toast.LENGTH_LONG).show()
                                if (success) {
                                    productToDelete = null
                                    if (editingProduct?.id == targetProd.id) {
                                        editingProduct = null
                                    }
                                }
                            }
                        },
                        modifier = Modifier.weight(1f).height(48.dp),
                        shape = RoundedCornerShape(12.dp),
                        enabled = !isDeleting,
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444), contentColor = Color.White)
                    ) {
                        if (isDeleting) {
                            CircularProgressIndicator(modifier = Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
                        } else {
                            Icon(Icons.Default.Delete, null, tint = Color.White, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("Delete (মুছে ফেলুন)", fontWeight = FontWeight.Bold, color = Color.White)
                        }
                    }
                }
            }
        }
    }

    // ── NEW INVENTORY ITEM ENTRY DIALOG ──────────────────────────────
    var newProdImageUrl by remember { mutableStateOf<String?>(null) }
    var isUploadingImage by remember { mutableStateOf(false) }

    val addImagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            isUploadingImage = true
            viewModel.uploadProductImage(uri, context) { success, msg, uploadedUrl ->
                isUploadingImage = false
                if (success && uploadedUrl != null) {
                    newProdImageUrl = uploadedUrl
                    Toast.makeText(context, "Product photo attached", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    if (showAddDialog) {
        EnterpriseGestureModal(
            onDismissRequest = { showAddDialog = false },
            title = "নতুন পণ্য যোগ করুন (New Product Entry)",
            subtitle = "ইনভেন্টরি ও বারকোড ট্র্যাকিং",
            icon = Icons.Default.AddBox
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Product Image Upload Card
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(130.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .border(
                            BorderStroke(1.dp, if (newProdImageUrl != null) yellowPrimary else cardBorder),
                            RoundedCornerShape(12.dp)
                        )
                        .clickable(enabled = !isUploadingImage) {
                            addImagePicker.launch("image/*")
                        },
                    colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF16120B) else Color(0xFFF8FAFC))
                ) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        val resolvedNewProdModel = rememberResolvedImageModel(newProdImageUrl)
                        if (resolvedNewProdModel != null) {
                            AsyncImage(
                                model = resolvedNewProdModel,
                                contentDescription = "Product Image",
                                contentScale = ContentScale.Crop,
                                modifier = Modifier.fillMaxSize()
                            )
                            IconButton(
                                onClick = { newProdImageUrl = null },
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(6.dp)
                                    .size(30.dp)
                                    .background(Color.Black.copy(alpha = 0.7f), CircleShape)
                            ) {
                                Icon(Icons.Default.Close, contentDescription = "Remove", tint = Color.White, modifier = Modifier.size(16.dp))
                            }
                            Surface(
                                modifier = Modifier
                                    .align(Alignment.BottomCenter)
                                    .padding(bottom = 6.dp),
                                shape = RoundedCornerShape(12.dp),
                                color = Color.Black.copy(alpha = 0.75f)
                            ) {
                                Text(
                                    "Tap to change photo",
                                    color = Color.White,
                                    fontSize = 10.sp,
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                                )
                            }
                        } else if (isUploadingImage) {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                CircularProgressIndicator(
                                    color = yellowPrimary,
                                    modifier = Modifier.size(26.dp),
                                    strokeWidth = 2.5.dp
                                )
                                Text(
                                    "Uploading to Supabase Storage...",
                                    fontSize = 11.sp,
                                    color = secondaryText
                                )
                            }
                        } else {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Icon(
                                    imageVector = Icons.Outlined.AddPhotoAlternate,
                                    contentDescription = "Upload Product Image",
                                    tint = yellowPrimary,
                                    modifier = Modifier.size(34.dp)
                                )
                                Text(
                                    "Upload Product Image (পণ্যের ছবি)",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                                Text(
                                    "Tap to select photo (Auto-uploaded to Supabase)",
                                    fontSize = 11.sp,
                                    color = secondaryText
                                )
                            }
                        }
                    }
                }

                OutlinedTextField(
                    value = newProdName,
                    onValueChange = { newProdName = it },
                    label = { Text("Product Name (পণ্যের নাম)*") },
                    placeholder = { Text("e.g. Cotton T-Shirt") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = yellowPrimary,
                        unfocusedBorderColor = cardBorder,
                        focusedTextColor = primaryText,
                        unfocusedTextColor = primaryText
                    )
                )

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    OutlinedTextField(
                        value = newProdCode,
                        onValueChange = { newProdCode = it },
                        label = { Text("SKU / Barcode") },
                        placeholder = { Text("e.g. PRD-8820") },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    Button(
                        onClick = { showBarcodeScannerForAdd = !showBarcodeScannerForAdd },
                        modifier = Modifier.height(54.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = if (showBarcodeScannerForAdd) Color(0xFFEF4444) else yellowPrimary)
                    ) {
                        Icon(if (showBarcodeScannerForAdd) Icons.Default.Close else Icons.Default.QrCodeScanner, contentDescription = null, tint = Color.Black, modifier = Modifier.size(18.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(if (showBarcodeScannerForAdd) "Close" else "Scan", fontWeight = FontWeight.Bold, color = Color.Black, fontSize = 12.sp)
                    }
                }

                if (showBarcodeScannerForAdd) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(200.dp)
                            .clip(RoundedCornerShape(12.dp))
                            .border(1.dp, yellowPrimary, RoundedCornerShape(12.dp))
                    ) {
                        RealQrCameraScanner(
                            onQrScanned = { scanned ->
                                newProdCode = scanned.trim()
                                showBarcodeScannerForAdd = false
                                Toast.makeText(context, "Barcode scanned: $scanned", Toast.LENGTH_SHORT).show()
                            },
                            modifier = Modifier.fillMaxSize()
                        )
                    }
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedTextField(
                        value = newProdCategory,
                        onValueChange = { newProdCategory = it },
                        label = { Text("Category (ক্যাটাগরি)") },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    OutlinedTextField(
                        value = newProdUnit,
                        onValueChange = { newProdUnit = it },
                        label = { Text("Unit (একক)") },
                        placeholder = { Text("pcs / kg") },
                        singleLine = true,
                        modifier = Modifier.weight(0.8f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedTextField(
                        value = newProdBuyPrice,
                        onValueChange = { newProdBuyPrice = it },
                        label = { Text("Buy Price (ক্রয়মূল্য ৳)") },
                        placeholder = { Text("250.0") },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    OutlinedTextField(
                        value = newProdSellPrice,
                        onValueChange = { newProdSellPrice = it },
                        label = { Text("Sale Price (বিক্রয়মূল্য ৳)*") },
                        placeholder = { Text("350.0") },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )
                }

                OutlinedTextField(
                    value = newProdStock,
                    onValueChange = { newProdStock = it },
                    label = { Text("Opening Stock (মজুদ সংখ্যা)") },
                    placeholder = { Text("10") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = yellowPrimary,
                        unfocusedBorderColor = cardBorder,
                        focusedTextColor = primaryText,
                        unfocusedTextColor = primaryText
                    )
                )

                Spacer(modifier = Modifier.height(10.dp))

                Button(
                    onClick = {
                        val name = newProdName.trim()
                        if (name.isBlank()) {
                            Toast.makeText(context, "Product name is required (পণ্যের নাম লিখুন)", Toast.LENGTH_SHORT).show()
                            return@Button
                        }
                        val sellPrice = newProdSellPrice.toDoubleOrNull()
                        if (sellPrice == null || sellPrice < 0.0) {
                            Toast.makeText(context, "Please enter a valid Sale Price (বিক্রয়মূল্য দিন)", Toast.LENGTH_SHORT).show()
                            return@Button
                        }
                        val buyPrice = newProdBuyPrice.toDoubleOrNull() ?: 0.0
                        val stock = newProdStock.toDoubleOrNull() ?: 0.0
                        if (buyPrice < 0.0 || stock < 0.0) {
                            Toast.makeText(context, "Price and stock cannot be negative", Toast.LENGTH_SHORT).show()
                            return@Button
                        }
                        val code = newProdCode.trim().ifEmpty { null }
                        val cat = newProdCategory.trim().ifEmpty { "General" }
                        val unit = newProdUnit.trim().ifEmpty { "pcs" }

                        viewModel.addProduct(
                            name = name,
                            code = code,
                            category = cat,
                            purchasePrice = buyPrice,
                            salePrice = sellPrice,
                            stock = stock,
                            unit = unit,
                            imageUrl = newProdImageUrl,
                            onResult = { success, message ->
                                Toast.makeText(context, message, if (success) Toast.LENGTH_SHORT else Toast.LENGTH_LONG).show()
                                if (success) {
                                    newProdName = ""
                                    newProdCode = ""
                                    newProdBuyPrice = ""
                                    newProdSellPrice = ""
                                    newProdStock = ""
                                    newProdImageUrl = null
                                    showAddDialog = false
                                }
                            }
                        )
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(50.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black)
                ) {
                    Icon(Icons.Default.Save, contentDescription = null, tint = Color.Black, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Save & Add to Inventory", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                }
            }
        }
    }

    // ── EDIT INVENTORY ITEM MODAL ─────────────────────────────────────
    if (editingProduct != null) {
        val prod = editingProduct!!
        var editName by remember(prod) { mutableStateOf(prod.name) }
        var editCode by remember(prod) { mutableStateOf(prod.code ?: "") }
        var editCategory by remember(prod) { mutableStateOf(prod.category ?: "General") }
        var editUnit by remember(prod) { mutableStateOf(prod.unit) }
        var editBuyPrice by remember(prod) { mutableStateOf(prod.purchasePrice.toString()) }
        var editSellPrice by remember(prod) { mutableStateOf(prod.salePrice.toString()) }
        var editStock by remember(prod) { mutableStateOf(prod.stockQuantity.toString()) }
        var editImageUrl by remember(prod) {
            mutableStateOf(
                prod.imageUrl?.takeIf { it.isNotBlank() }
                    ?: com.example.data.local.ProductStorefrontDetails.parse(prod.storefrontDetailsJson).featuredImage?.preview?.takeIf { it.isNotBlank() }
            )
        }
        var isEditUploadingImage by remember { mutableStateOf(false) }

        val editImagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
            if (uri != null) {
                isEditUploadingImage = true
                viewModel.uploadProductImage(uri, context) { success, msg, uploadedUrl ->
                    isEditUploadingImage = false
                    if (success && uploadedUrl != null) {
                        editImageUrl = uploadedUrl
                        Toast.makeText(context, "Product photo updated", Toast.LENGTH_SHORT).show()
                    } else {
                        Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                    }
                }
            }
        }

        EnterpriseGestureModal(
            onDismissRequest = { editingProduct = null },
            title = "পণ্য পরিবর্তন করুন (Edit Product)",
            subtitle = "Update product details and pricing",
            icon = Icons.Default.Edit
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Product Image Edit Card
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(130.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .border(
                            BorderStroke(1.dp, if (editImageUrl != null) yellowPrimary else cardBorder),
                            RoundedCornerShape(12.dp)
                        )
                        .clickable(enabled = !isEditUploadingImage) {
                            editImagePicker.launch("image/*")
                        },
                    colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF16120B) else Color(0xFFF8FAFC))
                ) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        val resolvedEditProdModel = rememberResolvedImageModel(editImageUrl)
                        if (resolvedEditProdModel != null) {
                            AsyncImage(
                                model = resolvedEditProdModel,
                                contentDescription = "Product Image",
                                contentScale = ContentScale.Crop,
                                modifier = Modifier.fillMaxSize()
                            )
                            IconButton(
                                onClick = { editImageUrl = null },
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(6.dp)
                                    .size(30.dp)
                                    .background(Color.Black.copy(alpha = 0.7f), CircleShape)
                            ) {
                                Icon(Icons.Default.Close, contentDescription = "Remove", tint = Color.White, modifier = Modifier.size(16.dp))
                            }
                            Surface(
                                modifier = Modifier
                                    .align(Alignment.BottomCenter)
                                    .padding(bottom = 6.dp),
                                shape = RoundedCornerShape(12.dp),
                                color = Color.Black.copy(alpha = 0.75f)
                            ) {
                                Text(
                                    "Tap to change photo",
                                    color = Color.White,
                                    fontSize = 10.sp,
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                                )
                            }
                        } else if (isEditUploadingImage) {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                CircularProgressIndicator(
                                    color = yellowPrimary,
                                    modifier = Modifier.size(26.dp),
                                    strokeWidth = 2.5.dp
                                )
                                Text(
                                    "Uploading to Supabase Storage...",
                                    fontSize = 11.sp,
                                    color = secondaryText
                                )
                            }
                        } else {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Icon(
                                    imageVector = Icons.Outlined.AddPhotoAlternate,
                                    contentDescription = "Change Product Image",
                                    tint = yellowPrimary,
                                    modifier = Modifier.size(34.dp)
                                )
                                Text(
                                    "Upload / Change Image (পণ্যের ছবি)",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = primaryText
                                )
                                Text(
                                    "Tap to select photo (Auto-uploaded to Supabase)",
                                    fontSize = 11.sp,
                                    color = secondaryText
                                )
                            }
                        }
                    }
                }

                OutlinedTextField(
                    value = editName,
                    onValueChange = { editName = it },
                    label = { Text("Product Name*") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = yellowPrimary,
                        unfocusedBorderColor = cardBorder,
                        focusedTextColor = primaryText,
                        unfocusedTextColor = primaryText
                    )
                )

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    OutlinedTextField(
                        value = editCode,
                        onValueChange = { editCode = it },
                        label = { Text("SKU / Barcode") },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    Button(
                        onClick = { showBarcodeScannerForEdit = !showBarcodeScannerForEdit },
                        modifier = Modifier.height(54.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = if (showBarcodeScannerForEdit) Color(0xFFEF4444) else yellowPrimary)
                    ) {
                        Icon(if (showBarcodeScannerForEdit) Icons.Default.Close else Icons.Default.QrCodeScanner, contentDescription = null, tint = Color.Black, modifier = Modifier.size(18.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(if (showBarcodeScannerForEdit) "Close" else "Scan", fontWeight = FontWeight.Bold, color = Color.Black, fontSize = 12.sp)
                    }
                }

                if (showBarcodeScannerForEdit) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(200.dp)
                            .clip(RoundedCornerShape(12.dp))
                            .border(1.dp, yellowPrimary, RoundedCornerShape(12.dp))
                    ) {
                        RealQrCameraScanner(
                            onQrScanned = { scanned ->
                                editCode = scanned.trim()
                                showBarcodeScannerForEdit = false
                                Toast.makeText(context, "Barcode scanned: $scanned", Toast.LENGTH_SHORT).show()
                            },
                            modifier = Modifier.fillMaxSize()
                        )
                    }
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedTextField(
                        value = editCategory,
                        onValueChange = { editCategory = it },
                        label = { Text("Category") },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    OutlinedTextField(
                        value = editUnit,
                        onValueChange = { editUnit = it },
                        label = { Text("Unit") },
                        singleLine = true,
                        modifier = Modifier.weight(0.8f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedTextField(
                        value = editBuyPrice,
                        onValueChange = { editBuyPrice = it },
                        label = { Text("Buy Price (৳)") },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )

                    OutlinedTextField(
                        value = editSellPrice,
                        onValueChange = { editSellPrice = it },
                        label = { Text("Sale Price (৳)*") },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = yellowPrimary,
                            unfocusedBorderColor = cardBorder,
                            focusedTextColor = primaryText,
                            unfocusedTextColor = primaryText
                        )
                    )
                }

                OutlinedTextField(
                    value = editStock,
                    onValueChange = { editStock = it },
                    label = { Text("Current Stock Quantity") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = yellowPrimary,
                        unfocusedBorderColor = cardBorder,
                        focusedTextColor = primaryText,
                        unfocusedTextColor = primaryText
                    )
                )

                Spacer(modifier = Modifier.height(10.dp))

                Button(
                    onClick = {
                        val name = editName.trim()
                        if (name.isBlank()) {
                            Toast.makeText(context, "Product name is required", Toast.LENGTH_SHORT).show()
                            return@Button
                        }
                        val buyPrice = editBuyPrice.toDoubleOrNull()
                        val sellPrice = editSellPrice.toDoubleOrNull()
                        val stock = editStock.toDoubleOrNull()
                        if (buyPrice == null || sellPrice == null || stock == null ||
                            !buyPrice.isFinite() || !sellPrice.isFinite() || !stock.isFinite() ||
                            buyPrice < 0.0 || sellPrice < 0.0 || stock < 0.0) {
                            Toast.makeText(context, "Enter valid non-negative prices and stock", Toast.LENGTH_LONG).show()
                            return@Button
                        }
                        val code = editCode.trim().ifEmpty { null }
                        val cat = editCategory.trim().ifEmpty { "General" }
                        val unit = editUnit.trim().ifEmpty { "pcs" }

                        val existingStorefront = com.example.data.local.ProductStorefrontDetails.parse(prod.storefrontDetailsJson)
                        val updatedStorefront = if (!editImageUrl.isNullOrBlank()) {
                            val currentFeatured = existingStorefront.featuredImage
                            if (currentFeatured == null || currentFeatured.preview != editImageUrl) {
                                existingStorefront.copy(
                                    featuredImage = com.example.data.local.ProductMedia(
                                        reference = editImageUrl!!,
                                        url = if (editImageUrl!!.startsWith("http", ignoreCase = true)) editImageUrl!! else ""
                                    )
                                )
                            } else existingStorefront
                        } else {
                            existingStorefront.copy(featuredImage = null)
                        }

                        val updated = prod.copy(
                            name = name,
                            code = code,
                            category = cat,
                            purchasePrice = buyPrice,
                            salePrice = sellPrice,
                            stockQuantity = stock,
                            unit = unit,
                            imageUrl = editImageUrl,
                            storefrontDetailsJson = updatedStorefront.json().toString()
                        )
                        viewModel.updateProduct(updated) { success, message ->
                            Toast.makeText(context, message, if (success) Toast.LENGTH_SHORT else Toast.LENGTH_LONG).show()
                            if (success) editingProduct = null
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(50.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black)
                ) {
                    Icon(Icons.Default.Save, contentDescription = null, tint = Color.Black, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Save Changes", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                }

                Spacer(modifier = Modifier.height(4.dp))

                OutlinedButton(
                    onClick = {
                        val p = editingProduct
                        editingProduct = null
                        productToDelete = p
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = Color(0xFFEF4444)),
                    border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.5f))
                ) {
                    Icon(Icons.Default.Delete, contentDescription = null, tint = Color(0xFFEF4444), modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Delete Product (পণ্য মুছে ফেলুন)", fontWeight = FontWeight.Bold, color = Color(0xFFEF4444), fontSize = 14.sp)
                }
            }
        }
    }

    // ── BIND QR CODE MODAL ──────────────────────────────────────────
    if (bindingQrProduct != null) {
        BindProductQrModal(
            product = bindingQrProduct!!,
            viewModel = viewModel,
            isDarkMode = isDarkMode,
            onDismiss = { bindingQrProduct = null }
        )
    }

    // ── MANAGE VARIANTS & SERIALS MODAL ────────────────────────────
    if (managingVariantsProduct != null) {
        val targetProd = managingVariantsProduct!!
        val variants = productVariants.filter { it.productId == targetProd.id }
        ManageProductVariantsModal(
            product = targetProd,
            variants = variants,
            viewModel = viewModel,
            isDarkMode = isDarkMode,
            onDismiss = { managingVariantsProduct = null }
        )
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// BIND PRODUCT QR CODE MODAL
// ═══════════════════════════════════════════════════════════════════════════
@Composable
private fun BindProductQrModal(
    product: ProductItemEntity,
    viewModel: AppViewModel,
    isDarkMode: Boolean,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    var qrInput by remember(product) { mutableStateOf(product.qrCode ?: product.code ?: "") }
    var isCameraActive by remember { mutableStateOf(false) }
    var isSubmitting by remember { mutableStateOf(false) }

    val yellowPrimary = if (isDarkMode) Color(0xFFF5C518) else Color(0xFFFFC800)
    val cardBorder = if (isDarkMode) Color(0xFF382A0B) else Color(0xFFE2E8F0)
    val primaryText = if (isDarkMode) Color(0xFFF3F4F6) else Color(0xFF0F172A)
    val secondaryText = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)

    EnterpriseGestureModal(
        onDismissRequest = { if (!isSubmitting) onDismiss() },
        title = "কিউআর কোড যুক্ত করুন (Bind QR Code)",
        subtitle = "পণ্য: ${product.name} (SKU: ${product.code ?: "None"})",
        icon = Icons.Default.QrCode
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            // Product info badge
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF14120D) else Color(0xFFF8FAFC)),
                border = BorderStroke(1.dp, cardBorder)
            ) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(38.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(if (isDarkMode) Color(0xFF261D07) else Color(0xFFFFFBEB)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Default.QrCodeScanner, contentDescription = null, tint = yellowPrimary, modifier = Modifier.size(20.dp))
                    }
                    Column(modifier = Modifier.weight(1f)) {
                        Text(product.name, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = primaryText)
                        Text(
                            text = if (!product.qrCode.isNullOrBlank()) "বর্তমান QR: ${product.qrCode}" else "বর্তমানে কোনো QR কোড যুক্ত নেই",
                            fontSize = 11.5.sp,
                            color = if (!product.qrCode.isNullOrBlank()) Color(0xFF10B981) else secondaryText
                        )
                    }
                }
            }

            // QR Input field
            OutlinedTextField(
                value = qrInput,
                onValueChange = { qrInput = it },
                label = { Text("কিউআর / বারকোড কোড (QR or Barcode)") },
                placeholder = { Text("e.g. 89411002341 or SP-PRD-01") },
                singleLine = true,
                leadingIcon = { Icon(Icons.Default.QrCode, null, tint = yellowPrimary) },
                trailingIcon = {
                    if (qrInput.isNotEmpty()) {
                        IconButton(onClick = { qrInput = "" }) {
                            Icon(Icons.Default.Close, null, tint = secondaryText)
                        }
                    }
                },
                modifier = Modifier.fillMaxWidth(),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = yellowPrimary,
                    unfocusedBorderColor = cardBorder,
                    focusedTextColor = primaryText,
                    unfocusedTextColor = primaryText
                )
            )

            // Action Buttons: Camera Scan & Auto-Generate
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = { isCameraActive = !isCameraActive },
                    modifier = Modifier.weight(1f).height(46.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = if (isCameraActive) Color(0xFFEF4444) else primaryText),
                    border = BorderStroke(1.dp, if (isCameraActive) Color(0xFFEF4444) else cardBorder)
                ) {
                    Icon(
                        imageVector = if (isCameraActive) Icons.Default.Close else Icons.Default.QrCodeScanner,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(if (isCameraActive) "ক্যামেরা বন্ধ" else "ক্যামেরা স্ক্যান", fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                }

                OutlinedButton(
                    onClick = {
                        val genCode = "SP-${product.name.take(3).uppercase().filter { it.isLetterOrDigit() }.ifEmpty { "PRD" }}-${java.util.UUID.randomUUID().toString().take(6).uppercase()}"
                        qrInput = genCode
                        Toast.makeText(context, "কোড তৈরি হয়েছে: $genCode", Toast.LENGTH_SHORT).show()
                    },
                    modifier = Modifier.weight(1f).height(46.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = primaryText),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Icon(Icons.Default.AutoFixHigh, contentDescription = null, tint = yellowPrimary, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("অটো-কোড তৈরি", fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                }
            }

            // Real Camera Scanner Container
            if (isCameraActive) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(230.dp)
                        .clip(RoundedCornerShape(14.dp))
                        .border(1.5.dp, yellowPrimary, RoundedCornerShape(14.dp))
                ) {
                    RealQrCameraScanner(
                        onQrScanned = { code ->
                            val trimmed = code.trim()
                            if (trimmed.isNotEmpty()) {
                                qrInput = trimmed
                                isCameraActive = false
                                Toast.makeText(context, "স্ক্যান সফল: $trimmed", Toast.LENGTH_SHORT).show()
                            }
                        },
                        modifier = Modifier.fillMaxSize()
                    )
                }
            }

            Spacer(modifier = Modifier.height(4.dp))

            // Save / Bind Button
            Button(
                onClick = {
                    val code = qrInput.trim()
                    if (code.isBlank()) {
                        Toast.makeText(context, "দয়া করে একটি কিউআর বা বারকোড দিন", Toast.LENGTH_SHORT).show()
                        return@Button
                    }
                    isSubmitting = true
                    viewModel.bindProductQrCode(product.id, code) { success, message ->
                        isSubmitting = false
                        Toast.makeText(context, message, if (success) Toast.LENGTH_SHORT else Toast.LENGTH_LONG).show()
                        if (success) onDismiss()
                    }
                },
                enabled = !isSubmitting,
                modifier = Modifier.fillMaxWidth().height(48.dp),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black)
            ) {
                Icon(Icons.Default.Check, contentDescription = null, tint = Color.Black, modifier = Modifier.size(18.dp))
                Spacer(modifier = Modifier.width(8.dp))
                Text("কিউআর কোড যুক্ত ও সংরক্ষণ করুন", fontWeight = FontWeight.Bold, fontSize = 14.sp)
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// MANAGE PRODUCT VARIANTS & SERIALS MODAL
// ═══════════════════════════════════════════════════════════════════════════
@Composable
private fun ManageProductVariantsModal(
    product: ProductItemEntity,
    variants: List<ProductVariantEntity>,
    viewModel: AppViewModel,
    isDarkMode: Boolean,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    var selectedTab by remember { mutableIntStateOf(if (variants.isEmpty()) 1 else 0) }

    val yellowPrimary = if (isDarkMode) Color(0xFFF5C518) else Color(0xFFFFC800)
    val cardBorder = if (isDarkMode) Color(0xFF382A0B) else Color(0xFFE2E8F0)
    val primaryText = if (isDarkMode) Color(0xFFF3F4F6) else Color(0xFF0F172A)
    val secondaryText = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)

    // Form State for Single Variant
    var varName by remember { mutableStateOf("") }
    var varQty by remember { mutableStateOf("1") }
    var varBuyPrice by remember { mutableStateOf(if (product.purchasePrice > 0.0) product.purchasePrice.toString() else "") }
    var varAskingPrice by remember { mutableStateOf(if (product.askingPrice > 0.0) product.askingPrice.toString() else "") }
    var varSellPrice by remember { mutableStateOf(if (product.salePrice > 0.0) product.salePrice.toString() else "") }
    var varQrCode by remember { mutableStateOf("") }
    var isScanningSingleQr by remember { mutableStateOf(false) }
    var isSavingSingle by remember { mutableStateOf(false) }

    // Form State for Each Quantity Unique QR Mode
    var unitPrefix by remember { mutableStateOf("Piece") }
    var unitQtyCount by remember { mutableStateOf("3") }
    var unitBuyPrice by remember { mutableStateOf(if (product.purchasePrice > 0.0) product.purchasePrice.toString() else "") }
    var unitAskingPrice by remember { mutableStateOf(if (product.askingPrice > 0.0) product.askingPrice.toString() else "") }
    var unitSellPrice by remember { mutableStateOf(if (product.salePrice > 0.0) product.salePrice.toString() else "") }
    var generatedQrsList by remember { mutableStateOf<List<String>>(emptyList()) }
    var isScanningUnitQr by remember { mutableStateOf(false) }
    var isSavingUnits by remember { mutableStateOf(false) }

    EnterpriseGestureModal(
        onDismissRequest = onDismiss,
        title = "ভ্যারিয়েন্ট ও সিরিয়াল কিউআর (Variants & Serials)",
        subtitle = "${product.name} • ${product.category ?: "General"}",
        icon = Icons.Default.Layers
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            // Tabs Bar
            TabRow(
                selectedTabIndex = selectedTab,
                containerColor = if (isDarkMode) Color(0xFF13100B) else Color(0xFFF1F5F9),
                contentColor = yellowPrimary,
                modifier = Modifier.clip(RoundedCornerShape(10.dp))
            ) {
                Tab(
                    selected = selectedTab == 0,
                    onClick = { selectedTab = 0 },
                    text = { Text("তালিকা (${variants.size})", fontSize = 12.sp, fontWeight = if (selectedTab == 0) FontWeight.Bold else FontWeight.Normal) }
                )
                Tab(
                    selected = selectedTab == 1,
                    onClick = { selectedTab = 1 },
                    text = { Text("+ ভ্যারিয়েন্ট যোগ", fontSize = 12.sp, fontWeight = if (selectedTab == 1) FontWeight.Bold else FontWeight.Normal) }
                )
                Tab(
                    selected = selectedTab == 2,
                    onClick = { selectedTab = 2 },
                    text = { Text("প্রতি পিসের QR", fontSize = 12.sp, fontWeight = if (selectedTab == 2) FontWeight.Bold else FontWeight.Normal) }
                )
            }

            // ── TAB 0: EXISTING VARIANTS LIST ──────────────────────────
            if (selectedTab == 0) {
                if (variants.isEmpty()) {
                    Card(
                        modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF13100B) else Color(0xFFF8FAFC)),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Column(
                            modifier = Modifier.fillMaxWidth().padding(24.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Icon(Icons.Default.Layers, contentDescription = null, tint = secondaryText, modifier = Modifier.size(36.dp))
                            Text("এই পণ্যে এখনো কোনো ভ্যারিয়েন্ট নেই", fontWeight = FontWeight.Bold, color = primaryText, fontSize = 14.sp)
                            Text("সাইজ/রং বা প্রতি পিসের জন্য আলাদা QR কোড তৈরি করতে উপরের ট্যাব নির্বাচন করুন।", fontSize = 11.5.sp, color = secondaryText, textAlign = TextAlign.Center)
                            Spacer(modifier = Modifier.height(6.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Button(
                                    onClick = { selectedTab = 1 },
                                    colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black),
                                    shape = RoundedCornerShape(8.dp)
                                ) {
                                    Text("+ ভ্যারিয়েন্ট যোগ", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                }
                                OutlinedButton(
                                    onClick = { selectedTab = 2 },
                                    shape = RoundedCornerShape(8.dp),
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Text("প্রতি পিসের QR", fontSize = 12.sp, color = primaryText)
                                }
                            }
                        }
                    }
                } else {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        variants.forEach { v ->
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF16130E) else Color(0xFFFFFFFF)),
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Row(
                                    modifier = Modifier.padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(38.dp)
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(if (isDarkMode) Color(0xFF201B2E) else Color(0xFFEEF2FF)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(Icons.Default.QrCode, contentDescription = null, tint = Color(0xFF818CF8), modifier = Modifier.size(20.dp))
                                    }

                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(v.variantName, fontWeight = FontWeight.Bold, fontSize = 13.5.sp, color = primaryText)
                                        Text(
                                            "বিক্রি: ৳${String.format("%,.0f", v.salePrice)} • স্টক: ${v.stockQuantity.toInt()} টি",
                                            fontSize = 11.5.sp,
                                            color = secondaryText
                                        )
                                        Text(
                                            "QR: ${v.qrCode}",
                                            fontSize = 10.5.sp,
                                            color = yellowPrimary,
                                            fontWeight = FontWeight.SemiBold
                                        )
                                    }

                                    IconButton(
                                        onClick = {
                                            viewModel.deleteProductVariant(v.id) { success, msg ->
                                                Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                            }
                                        }
                                    ) {
                                        Icon(Icons.Default.Delete, contentDescription = "Delete", tint = Color(0xFFEF4444), modifier = Modifier.size(18.dp))
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // ── TAB 1: ADD SINGLE VARIANT ──────────────────────────────
            if (selectedTab == 1) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF14120D) else Color(0xFFF8FAFC)),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("স্ট্যান্ডার্ড ভ্যারিয়েন্ট যোগ করুন (e.g. Size L, Red Color, 500g):", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = primaryText)

                        OutlinedTextField(
                            value = varName,
                            onValueChange = { varName = it },
                            label = { Text("ভ্যারিয়েন্ট নাম (Variant Name)*") },
                            placeholder = { Text("e.g. Size XL / Red / 1kg") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth(),
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                        )

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value = varBuyPrice,
                                onValueChange = { varBuyPrice = it },
                                label = { Text("ক্রয়মূল্য (Buy ৳)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )
                            OutlinedTextField(
                                value = varSellPrice,
                                onValueChange = { varSellPrice = it },
                                label = { Text("বিক্রয়মূল্য (Sell ৳)*") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )
                        }

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value = varAskingPrice,
                                onValueChange = { varAskingPrice = it },
                                label = { Text("ট্যাগ মূল্য (Asking ৳)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )
                            OutlinedTextField(
                                value = varQty,
                                onValueChange = { varQty = it },
                                label = { Text("স্টক পরিমাণ (Qty)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )
                        }

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            OutlinedTextField(
                                value = varQrCode,
                                onValueChange = { varQrCode = it },
                                label = { Text("ভ্যারিয়েন্ট QR / বারকোড*") },
                                placeholder = { Text("Unique QR") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )

                            Button(
                                onClick = { isScanningSingleQr = !isScanningSingleQr },
                                modifier = Modifier.height(52.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = if (isScanningSingleQr) Color(0xFFEF4444) else yellowPrimary)
                            ) {
                                Icon(if (isScanningSingleQr) Icons.Default.Close else Icons.Default.QrCodeScanner, null, tint = Color.Black, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text(if (isScanningSingleQr) "Stop" else "Scan", color = Color.Black, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                            }

                            Button(
                                onClick = {
                                    val gen = "SP-VAR-${java.util.UUID.randomUUID().toString().take(6).uppercase()}"
                                    varQrCode = gen
                                    Toast.makeText(context, "কোড: $gen", Toast.LENGTH_SHORT).show()
                                },
                                modifier = Modifier.height(52.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = if (isDarkMode) Color(0xFF261D07) else Color(0xFFFEF3C7))
                            ) {
                                Text("Auto", color = yellowPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                            }
                        }

                        if (isScanningSingleQr) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(200.dp)
                                    .clip(RoundedCornerShape(12.dp))
                                    .border(1.dp, yellowPrimary, RoundedCornerShape(12.dp))
                            ) {
                                RealQrCameraScanner(
                                    onQrScanned = { code ->
                                        varQrCode = code.trim()
                                        isScanningSingleQr = false
                                        Toast.makeText(context, "Scanned: $code", Toast.LENGTH_SHORT).show()
                                    },
                                    modifier = Modifier.fillMaxSize()
                                )
                            }
                        }

                        Button(
                            onClick = {
                                val name = varName.trim()
                                if (name.isBlank()) {
                                    Toast.makeText(context, "ভ্যারিয়েন্টের নাম দিন", Toast.LENGTH_SHORT).show()
                                    return@Button
                                }
                                val sell = varSellPrice.toDoubleOrNull()
                                if (sell == null || sell < 0.0) {
                                    Toast.makeText(context, "সঠিক বিক্রয়মূল্য দিন", Toast.LENGTH_SHORT).show()
                                    return@Button
                                }
                                val buy = varBuyPrice.toDoubleOrNull() ?: 0.0
                                val asking = varAskingPrice.toDoubleOrNull() ?: sell
                                val qty = varQty.toDoubleOrNull() ?: 1.0
                                val qr = varQrCode.trim().ifEmpty {
                                    "SP-VAR-${java.util.UUID.randomUUID().toString().take(6).uppercase()}"
                                }

                                isSavingSingle = true
                                viewModel.addProductVariant(
                                    productId = product.id,
                                    variantName = name,
                                    qrCode = qr,
                                    costPrice = buy,
                                    askingPrice = asking,
                                    salePrice = sell,
                                    quantity = qty
                                ) { success, msg ->
                                    isSavingSingle = false
                                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                    if (success) {
                                        varName = ""
                                        varQrCode = ""
                                        selectedTab = 0
                                    }
                                }
                            },
                            enabled = !isSavingSingle,
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            shape = RoundedCornerShape(10.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black)
                        ) {
                            Icon(Icons.Default.Add, null, tint = Color.Black, modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("ভ্যারিয়েন্ট সংরক্ষণ করুন", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                        }
                    }
                }
            }

            // ── TAB 2: EACH QUANTITY UNIQUE QR / SERIAL ────────────────
            if (selectedTab == 2) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF14120D) else Color(0xFFF8FAFC)),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Icon(Icons.Default.AutoFixHigh, null, tint = yellowPrimary, modifier = Modifier.size(16.dp))
                            Text("প্রতি পিসের আলাদা QR কোড তৈরি (Each Unit QR Tracking):", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = primaryText)
                        }
                        Text(
                            "যেসব পণ্যের প্রতিটি পিস বা ইউনিটে আলাদা স্টিকার বা সিরিয়াল নাম্বার থাকে, তাদের জন্য প্রতিটি পিসের স্টক ১ ধরে আলাদা QR কোড তৈরি হবে।",
                            fontSize = 11.sp,
                            color = secondaryText
                        )

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value = unitPrefix,
                                onValueChange = { unitPrefix = it },
                                label = { Text("লেবেল বা প্রিফিক্স") },
                                placeholder = { Text("Piece / Serial / Unit") },
                                singleLine = true,
                                modifier = Modifier.weight(1.2f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )
                            OutlinedTextField(
                                value = unitQtyCount,
                                onValueChange = { unitQtyCount = it },
                                label = { Text("পিসের সংখ্যা (Qty)") },
                                placeholder = { Text("e.g. 5") },
                                singleLine = true,
                                modifier = Modifier.weight(0.8f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )
                        }

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value = unitBuyPrice,
                                onValueChange = { unitBuyPrice = it },
                                label = { Text("প্রতিটির ক্রয়মূল্য (৳)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )
                            OutlinedTextField(
                                value = unitSellPrice,
                                onValueChange = { unitSellPrice = it },
                                label = { Text("প্রতিটির বিক্রয়মূল্য (৳)*") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = yellowPrimary)
                            )
                        }

                        // Generation Buttons
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Button(
                                onClick = {
                                    val count = unitQtyCount.toIntOrNull() ?: 1
                                    if (count <= 0 || count > 50) {
                                        Toast.makeText(context, "পিসের সংখ্যা ১ থেকে ৫০ এর মধ্যে দিন", Toast.LENGTH_SHORT).show()
                                        return@Button
                                    }
                                    val pName = product.name.take(3).uppercase().filter { it.isLetterOrDigit() }.ifEmpty { "PRD" }
                                    val list = (1..count).map {
                                        "SP-$pName-${java.util.UUID.randomUUID().toString().take(6).uppercase()}"
                                    }
                                    generatedQrsList = list
                                    Toast.makeText(context, "$count টি ইউনিক QR কোড জেনারেট হয়েছে", Toast.LENGTH_SHORT).show()
                                },
                                modifier = Modifier.weight(1f).height(46.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = if (isDarkMode) Color(0xFF261D07) else Color(0xFFFEF3C7))
                            ) {
                                Icon(Icons.Default.AutoFixHigh, null, tint = yellowPrimary, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("অটো QR তৈরি করুন", color = yellowPrimary, fontSize = 11.5.sp, fontWeight = FontWeight.Bold)
                            }

                            Button(
                                onClick = { isScanningUnitQr = !isScanningUnitQr },
                                modifier = Modifier.weight(1f).height(46.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = if (isScanningUnitQr) Color(0xFFEF4444) else yellowPrimary)
                            ) {
                                Icon(if (isScanningUnitQr) Icons.Default.Close else Icons.Default.QrCodeScanner, null, tint = Color.Black, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text(if (isScanningUnitQr) "স্ক্যান বন্ধ" else "ক্যামেরা দিয়ে স্ক্যান", color = Color.Black, fontSize = 11.5.sp, fontWeight = FontWeight.Bold)
                            }
                        }

                        if (isScanningUnitQr) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(200.dp)
                                    .clip(RoundedCornerShape(12.dp))
                                    .border(1.dp, yellowPrimary, RoundedCornerShape(12.dp))
                            ) {
                                RealQrCameraScanner(
                                    onQrScanned = { code ->
                                        val trimmed = code.trim()
                                        if (trimmed.isNotEmpty() && !generatedQrsList.contains(trimmed)) {
                                            generatedQrsList = generatedQrsList + trimmed
                                            Toast.makeText(context, "যুক্ত হয়েছে (${generatedQrsList.size}টি): $trimmed", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                    modifier = Modifier.fillMaxSize()
                                )
                            }
                        }

                        // Generated / Scanned Units Preview
                        if (generatedQrsList.isNotEmpty()) {
                            Text("প্রস্তুতকৃত কোড তালিকা (${generatedQrsList.size} টি পিস):", fontWeight = FontWeight.Bold, fontSize = 11.sp, color = primaryText)
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .heightIn(max = 180.dp)
                                    .verticalScroll(rememberScrollState()),
                                verticalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                generatedQrsList.forEachIndexed { idx, qr ->
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .background(if (isDarkMode) Color(0xFF1E1A11) else Color(0xFFF1F5F9), RoundedCornerShape(6.dp))
                                            .padding(horizontal = 8.dp, vertical = 4.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("${unitPrefix.ifEmpty { "Unit" }} #${idx + 1}: $qr", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = primaryText)
                                        IconButton(
                                            onClick = { generatedQrsList = generatedQrsList.filterIndexed { i, _ -> i != idx } },
                                            modifier = Modifier.size(20.dp)
                                        ) {
                                            Icon(Icons.Default.Close, null, tint = Color(0xFFEF4444), modifier = Modifier.size(14.dp))
                                        }
                                    }
                                }
                            }
                        }

                        Button(
                            onClick = {
                                if (generatedQrsList.isEmpty()) {
                                    Toast.makeText(context, "দয়া করে অন্তত একটি QR কোড তৈরি বা স্ক্যান করুন", Toast.LENGTH_SHORT).show()
                                    return@Button
                                }
                                val sell = unitSellPrice.toDoubleOrNull()
                                if (sell == null || sell < 0.0) {
                                    Toast.makeText(context, "সঠিক বিক্রয়মূল্য দিন", Toast.LENGTH_SHORT).show()
                                    return@Button
                                }
                                val buy = unitBuyPrice.toDoubleOrNull() ?: 0.0
                                val asking = unitAskingPrice.toDoubleOrNull() ?: sell

                                isSavingUnits = true
                                viewModel.addUnitVariantsForEachQuantity(
                                    productId = product.id,
                                    unitPrefix = unitPrefix,
                                    qrCodes = generatedQrsList,
                                    costPrice = buy,
                                    askingPrice = asking,
                                    salePrice = sell
                                ) { success, msg ->
                                    isSavingUnits = false
                                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                    if (success) {
                                        generatedQrsList = emptyList()
                                        selectedTab = 0
                                    }
                                }
                            },
                            enabled = !isSavingUnits && generatedQrsList.isNotEmpty(),
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            shape = RoundedCornerShape(10.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = yellowPrimary, contentColor = Color.Black)
                        ) {
                            Icon(Icons.Default.Check, null, tint = Color.Black, modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("সকল ${generatedQrsList.size}টি পিসের QR কোড সংরক্ষণ করুন", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                        }
                    }
                }
            }
        }
    }
}
