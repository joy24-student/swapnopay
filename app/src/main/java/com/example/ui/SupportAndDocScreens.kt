@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
package com.example.ui

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.*
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import coil.request.ImageRequest
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

// 16. SUPPORT SCREEN
@Composable
fun SupportScreen(viewModel: AppViewModel) {
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    SideEffect { isDarkModeGlobal = isDarkMode }
    val activeProfile by viewModel.activeProfile.collectAsState()
    val supportChatList by viewModel.supportChatList.collectAsState()
    val systemConfig by viewModel.systemRemoteConfig.collectAsState()
    val myTickets by viewModel.mySupportTicketsList.collectAsState()

    LaunchedEffect(Unit) {
        viewModel.listenToSupportChatFromPlatformOwner()
        viewModel.listenToPlatformPermissions()
        viewModel.listenToSystemConfig()
        viewModel.listenToMerchantSupportTickets()
        viewModel.logMerchantLoginTelemetry(activeProfile)
    }

    var searchQuery by remember { mutableStateOf("") }
    var activeCategoryDialog by remember { mutableStateOf<String?>(null) } // "FAQs", "Guides", "Tutorials", "Contact", "Ticket", "Status"
    var selectedArticle by remember { mutableStateOf<String?>(null) }
    var isChatOpen by remember { mutableStateOf(false) }

    // Chat Message State
    var chatInput by remember { mutableStateOf("") }

    // Ticket Submission State
    var ticketTab by remember { mutableStateOf("NEW") }
    var ticketCategory by remember { mutableStateOf("Payment Matching") }
    var ticketSubject by remember { mutableStateOf("") }
    var ticketDesc by remember { mutableStateOf("") }
    var ticketSubmitted by remember { mutableStateOf(false) }
    var lastSubmittedTicketId by remember { mutableStateOf("") }

    // Dynamic Articles synced from Firebase Admin CMS
    val articles = systemConfig.articles.mapIndexed { index, art ->
        val (icon, iconColor, bgColor) = when (index % 4) {
            0 -> Triple(Icons.Default.Description, Color(0xFF7C3AED), Color(0xFFF5F3FF))
            1 -> Triple(Icons.Default.CreditCard, Color(0xFF2563EB), Color(0xFFEFF6FF))
            2 -> Triple(Icons.Default.Info, Color(0xFF059669), Color(0xFFECFDF5))
            else -> Triple(Icons.Default.Warning, Color(0xFFD97706), Color(0xFFFFFBEB))
        }
        SupportArticle(
            id = art.id.ifBlank { "art_$index" },
            title = art.title,
            icon = icon,
            iconColor = iconColor,
            bgColor = bgColor,
            content = art.content
        )
    }

    val filteredArticles = articles.filter {
        it.title.lowercase().contains(searchQuery.lowercase())
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(AppScreenBg)
    ) {
        Column(
            modifier = Modifier.fillMaxSize()
        ) {
            // Scrollable Layout
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .weight(1f),
                contentPadding = PaddingValues(bottom = 24.dp)
            ) {
                // HEADER SECTION (Gradient header with rounded bottom)
                item {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(290.dp) // Height to hold welcome info + overlap search bar
                    ) {
                        // Purple Gradient Header Background
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(260.dp)
                                .clip(RoundedCornerShape(bottomStart = 40.dp, bottomEnd = 40.dp))
                                .background(
                                    Brush.linearGradient(
                                        colors = if (isDarkMode) {
                                            listOf(Color(0xFF1E1B4B), Color(0xFF312E81))
                                        } else {
                                            GradPrimary
                                        }
                                    )
                                )
                        ) {
                            // Decorative Headphones Graphic
                            AsyncImage(
                                model = ImageRequest.Builder(LocalContext.current)
                                    .data(com.example.R.drawable.img_ai_robot)
                                    .crossfade(true)
                                    .build(),
                                contentDescription = "Support Graphic",
                                modifier = Modifier
                                    .align(Alignment.BottomEnd)
                                    .offset(x = 10.dp, y = 10.dp)
                                    .size(140.dp),
                                alpha = 0.3f
                            )

                            Column(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .statusBarsPadding()
                                    .padding(horizontal = 24.dp, vertical = 12.dp)
                            ) {
                                // Status Bar Row
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    // Back Button
                                    Box(
                                        modifier = Modifier
                                            .size(40.dp)
                                            .background(Color.White.copy(alpha = 0.2f), CircleShape)
                                            .clickable { viewModel.goBack() },
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                                            contentDescription = "Back",
                                            tint = Color.White,
                                            modifier = Modifier.size(20.dp)
                                        )
                                    }

                                    Text(
                                        text = "Help & Support",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 18.sp,
                                        color = Color.White
                                    )

                                    // Chat Button
                                    Box(
                                        modifier = Modifier
                                            .size(40.dp)
                                            .background(Color.White.copy(alpha = 0.2f), CircleShape)
                                            .clickable { viewModel.navigateTo("SupportChat") },
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Chat,
                                            contentDescription = "Chat",
                                            tint = Color.White,
                                            modifier = Modifier.size(20.dp)
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.height(24.dp))

                                // Welcome Greeting
                                Text(
                                    text = "Hi ${activeProfile.businessName.ifBlank { "Merchant" }}! 👋",
                                    fontSize = 26.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color.White
                                )
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(
                                    text = "How can we help you today?",
                                    fontSize = 14.sp,
                                    color = Color.White.copy(alpha = 0.85f)
                                )
                            }
                        }

                        // Absolute Overlapping Search Bar
                        Card(
                            shape = RoundedCornerShape(20.dp),
                            elevation = CardDefaults.cardElevation(defaultElevation = 6.dp),
                            colors = CardDefaults.cardColors(containerColor = AppCardBg),
                            border = BorderStroke(1.dp, AppCardBorderColor),
                            modifier = Modifier
                                .align(Alignment.BottomCenter)
                                .fillMaxWidth()
                                .padding(horizontal = 24.dp)
                                .height(56.dp)
                        ) {
                            TextField(
                                value = searchQuery,
                                onValueChange = { searchQuery = it },
                                placeholder = { Text("Search for help articles...", fontSize = 14.sp, color = AppTextSecondary) },
                                leadingIcon = {
                                    Icon(
                                        imageVector = Icons.Default.Search,
                                        contentDescription = "Search Icon",
                                        tint = AppTextSecondary,
                                        modifier = Modifier.size(20.dp)
                                    )
                                },
                                trailingIcon = {
                                    if (searchQuery.isNotEmpty()) {
                                        IconButton(onClick = { searchQuery = "" }) {
                                            Icon(
                                                imageVector = Icons.Default.Close,
                                                contentDescription = "Clear",
                                                tint = AppTextSecondary,
                                                modifier = Modifier.size(18.dp)
                                            )
                                        }
                                    } else {
                                        Icon(
                                            imageVector = Icons.Default.Search,
                                            contentDescription = null,
                                            tint = AppTextSecondary,
                                            modifier = Modifier.size(20.dp)
                                        )
                                    }
                                },
                                singleLine = true,
                                colors = TextFieldDefaults.colors(
                                    focusedContainerColor = Color.Transparent,
                                    unfocusedContainerColor = Color.Transparent,
                                    focusedIndicatorColor = Color.Transparent,
                                    unfocusedIndicatorColor = Color.Transparent,
                                    disabledIndicatorColor = Color.Transparent,
                                    focusedTextColor = AppTextPrimary,
                                    unfocusedTextColor = AppTextPrimary
                                ),
                                modifier = Modifier.fillMaxSize()
                            )
                        }
                    }
                }

                // QUICK HELP GRID SECTION
                item {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 24.dp, vertical = 20.dp)
                    ) {
                        Text(
                            text = "Quick Help",
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp,
                            color = AppTextPrimary
                        )
                        Spacer(modifier = Modifier.height(14.dp))

                        // Grid Row 1
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            Box(modifier = Modifier.weight(1f)) {
                                QuickHelpCard(
                                    title = "FAQs",
                                    desc = "Find answers",
                                    icon = Icons.Default.Help,
                                    bgColor = Color(0xFFEFF6FF),
                                    iconColor = Color(0xFF2563EB),
                                    onClick = { activeCategoryDialog = "FAQs" }
                                )
                            }
                            Box(modifier = Modifier.weight(1f)) {
                                QuickHelpCard(
                                    title = "Guides",
                                    desc = "Step by step",
                                    icon = Icons.Default.Book,
                                    bgColor = Color(0xFFF5F3FF),
                                    iconColor = Color(0xFF7C3AED),
                                    onClick = { activeCategoryDialog = "Guides" }
                                )
                            }
                            Box(modifier = Modifier.weight(1f)) {
                                QuickHelpCard(
                                    title = "Video Tutorials",
                                    desc = "Watch & learn",
                                    icon = Icons.Default.PlayCircle,
                                    bgColor = Color(0xFFFEF2F2),
                                    iconColor = Color(0xFFDC2626),
                                    onClick = { activeCategoryDialog = "Tutorials" }
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(10.dp))

                        // Grid Row 2
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            Box(modifier = Modifier.weight(1f)) {
                                QuickHelpCard(
                                    title = "Contact Support",
                                    desc = "Chat or call us",
                                    icon = Icons.Default.Phone,
                                    bgColor = Color(0xFFEEF2FF),
                                    iconColor = Color(0xFF4F46E5),
                                    onClick = { activeCategoryDialog = "Contact" }
                                )
                            }
                            Box(modifier = Modifier.weight(1f)) {
                                QuickHelpCard(
                                    title = "Submit Ticket",
                                    desc = "Report an issue",
                                    icon = Icons.Default.Edit,
                                    bgColor = Color(0xFFDBEAFE),
                                    iconColor = Color(0xFF1D4ED8),
                                    onClick = { activeCategoryDialog = "Ticket" }
                                )
                            }
                            Box(modifier = Modifier.weight(1f)) {
                                QuickHelpCard(
                                    title = "System Status",
                                    desc = "All systems go",
                                    icon = Icons.Default.CheckCircle,
                                    bgColor = Color(0xFFECFDF5),
                                    iconColor = Color(0xFF059669),
                                    onClick = { activeCategoryDialog = "Status" }
                                )
                            }
                        }
                    }
                }

                // POPULAR ARTICLES SECTION
                item {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 24.dp)
                    ) {
                        Text(
                            text = "Popular Articles",
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp,
                            color = AppTextPrimary
                        )
                        Spacer(modifier = Modifier.height(12.dp))

                        if (filteredArticles.isEmpty()) {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                colors = CardDefaults.cardColors(containerColor = AppCardBg),
                                border = BorderStroke(1.dp, AppCardBorderColor)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(24.dp),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text(
                                        text = "No articles matching \"$searchQuery\"",
                                        fontSize = 14.sp,
                                        color = AppTextSecondary
                                    )
                                }
                            }
                        } else {
                            Column(
                                verticalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                filteredArticles.forEach { article ->
                                    ArticleRowItem(
                                        article = article,
                                        onClick = { selectedArticle = article.id }
                                    )
                                }
                            }
                        }
                    }
                }

                // LIVE CHAT CTA BANNER
                item {
                    Spacer(modifier = Modifier.height(24.dp))
                    Card(
                        shape = RoundedCornerShape(24.dp),
                        colors = CardDefaults.cardColors(containerColor = AppCardBg),
                        border = BorderStroke(1.dp, AppCardBorderColor),
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 24.dp)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(18.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column(modifier = Modifier.weight(1f)) {
                                Text(
                                    text = "Still need help?",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 15.sp,
                                    color = AppTextPrimary
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "Our support team is ready to assist you",
                                    fontSize = 12.sp,
                                    color = AppTextSecondary
                                )
                            }

                            Button(
                                onClick = { viewModel.navigateTo("SupportChat") },
                                colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                                shape = RoundedCornerShape(12.dp),
                                contentPadding = PaddingValues(horizontal = 14.dp, vertical = 10.dp),
                                modifier = Modifier.shadow(8.dp, RoundedCornerShape(12.dp), ambientColor = BrandPurple.copy(alpha = 0.4f))
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Text(
                                        text = "Start Live Chat",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 13.sp,
                                        color = Color.White
                                    )
                                    Icon(
                                        imageVector = Icons.Default.Chat,
                                        contentDescription = null,
                                        tint = Color.White,
                                        modifier = Modifier.size(16.dp)
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }

        // --- OVERLAY DIALOGS ---

        // 1. ARTICLE DETAIL VIEWER DIALOG
        selectedArticle?.let { articleId ->
            val article = articles.find { it.id == articleId }
            article?.let {
                EnterpriseGestureModal(
                    onDismissRequest = { selectedArticle = null },
                    title = "Help Center Guide",
                    subtitle = "Swipe down or drag handle to dismiss",
                    icon = it.icon
                ) {
                    Column(
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = it.title,
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp,
                            color = AppTextPrimary
                        )

                        Spacer(modifier = Modifier.height(12.dp))
                        HorizontalDivider(color = AppDividerColor)
                        Spacer(modifier = Modifier.height(12.dp))

                        Text(
                            text = it.content,
                            fontSize = 13.sp,
                            lineHeight = 18.sp,
                            color = AppTextPrimary
                        )

                        Spacer(modifier = Modifier.height(20.dp))

                        Button(
                            onClick = { selectedArticle = null },
                            colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth().height(48.dp)
                        ) {
                            Text("Got it, thanks!", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                        }
                    }
                }
            }
        }

        // 2. LIVE CHAT REDIRECT TO DEDICATED SCREEN
        if (isChatOpen) {
            LaunchedEffect(Unit) {
                isChatOpen = false
                viewModel.navigateTo("SupportChat")
            }
        }

        // 3. CATEGORIES DIALOG
        activeCategoryDialog?.let { dialogType ->
            val modalTitle = when (dialogType) {
                "FAQs" -> "Frequently Asked Questions"
                "Guides" -> "User Guides & Onboarding"
                "Tutorials" -> "Video Tutorials Library"
                "Contact" -> "Contact SwapnoPay Support"
                "Ticket" -> "Submit Support Ticket"
                else -> "System Operations Status"
            }
            EnterpriseGestureModal(
                onDismissRequest = { activeCategoryDialog = null },
                title = modalTitle,
                subtitle = "Swipe down or drag handle to dismiss",
                icon = when (dialogType) {
                    "FAQs" -> Icons.Default.Info
                    "Guides" -> Icons.Default.Book
                    "Tutorials" -> Icons.Default.PlayArrow
                    "Contact" -> Icons.Default.Call
                    "Ticket" -> Icons.Default.Email
                    else -> Icons.Default.CheckCircle
                }
            ) {
                Column(
                    modifier = Modifier.fillMaxWidth()
                ) {
                    when (dialogType) {
                            "FAQs" -> {
                                var expandedFaq by remember { mutableStateOf<Int?>(null) }
                                val faqsList = systemConfig.faqs.map { it.question to it.answer }

                                Column(
                                    verticalArrangement = Arrangement.spacedBy(8.dp),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .heightIn(max = 320.dp)
                                        .verticalScroll(rememberScrollState())
                                ) {
                                    faqsList.forEachIndexed { index, faq ->
                                        val isExpanded = expandedFaq == index
                                        Card(
                                            shape = RoundedCornerShape(12.dp),
                                            colors = CardDefaults.cardColors(containerColor = if (isDarkModeGlobal) Color(0xFF1E1F26) else Color(0xFFF9FAFB)),
                                            border = BorderStroke(1.dp, AppCardBorderColor),
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .clickable { expandedFaq = if (isExpanded) null else index }
                                        ) {
                                            Column(modifier = Modifier.padding(12.dp)) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(faq.first, fontWeight = FontWeight.Bold, fontSize = 12.sp, color = AppTextPrimary, modifier = Modifier.weight(1f))
                                                    Icon(
                                                        imageVector = if (isExpanded) Icons.Default.ArrowDropUp else Icons.Default.ArrowDropDown,
                                                        contentDescription = null,
                                                        tint = Color.Gray,
                                                        modifier = Modifier.size(18.dp)
                                                    )
                                                }
                                                if (isExpanded) {
                                                    Spacer(modifier = Modifier.height(8.dp))
                                                    Text(faq.second, fontSize = 11.sp, color = AppTextSecondary, lineHeight = 15.sp)
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            "Guides" -> {
                                val guidesList = systemConfig.guides.map { it.title to it.description }

                                Column(
                                    verticalArrangement = Arrangement.spacedBy(10.dp),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .heightIn(max = 320.dp)
                                        .verticalScroll(rememberScrollState())
                                ) {
                                    guidesList.forEach { guide ->
                                        Column {
                                            Text(guide.first, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = BrandPurple)
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Text(guide.second, fontSize = 12.sp, color = AppTextSecondary, lineHeight = 16.sp)
                                            Spacer(modifier = Modifier.height(8.dp))
                                            Divider(color = AppDividerColor)
                                        }
                                    }

                                    val context = LocalContext.current
                                    Button(
                                        onClick = {
                                            try {
                                                val docsUrl = if (systemConfig.developerDocsUrl.isBlank() || systemConfig.developerDocsUrl.contains("swapnopay.app") || systemConfig.developerDocsUrl.contains("pay.swapnopay.top/docs.html")) {
                                                    "https://swapnopay.top/docs.html"
                                                } else {
                                                    systemConfig.developerDocsUrl
                                                }
                                                val intent = Intent(Intent.ACTION_VIEW, Uri.parse(docsUrl)).apply {
                                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                                }
                                                context.startActivity(intent)
                                            } catch (e: Exception) {
                                                viewModel.navigateTo("ApiDoc")
                                            }
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF6366F1)),
                                        shape = RoundedCornerShape(10.dp),
                                        modifier = Modifier.fillMaxWidth().height(42.dp)
                                    ) {
                                        Icon(Icons.Outlined.Code, null, modifier = Modifier.size(16.dp))
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text("Open Web Developer & API Documentation", fontWeight = FontWeight.Bold, fontSize = 12.5.sp)
                                    }
                                }
                            }

                            "Tutorials" -> {
                                var isPlaying by remember { mutableStateOf(false) }
                                var progress by remember { mutableStateOf(0f) }
                                val context = LocalContext.current

                                LaunchedEffect(isPlaying) {
                                    if (isPlaying) {
                                        while (progress < 1.0f) {
                                            kotlinx.coroutines.delay(100)
                                            progress += 0.02f
                                        }
                                        isPlaying = false
                                        progress = 0f
                                    }
                                }

                                Column(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .verticalScroll(rememberScrollState()),
                                    horizontalAlignment = Alignment.CenterHorizontally
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .height(160.dp)
                                            .clip(RoundedCornerShape(16.dp))
                                            .background(Color.Black),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        AsyncImage(
                                            model = ImageRequest.Builder(LocalContext.current)
                                                .data(if (systemConfig.videoTutorial.thumbnailUrl.isNotBlank()) systemConfig.videoTutorial.thumbnailUrl else com.example.R.drawable.img_ai_robot)
                                                .crossfade(true)
                                                .build(),
                                            contentDescription = null,
                                            alpha = 0.5f,
                                            modifier = Modifier.fillMaxSize()
                                        )

                                        if (isPlaying) {
                                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                                CircularProgressIndicator(color = Color.White, modifier = Modifier.size(36.dp))
                                                Spacer(modifier = Modifier.height(8.dp))
                                                Text("Streaming Video Guide...", color = Color.White, fontSize = 12.sp)
                                            }
                                        } else {
                                            IconButton(
                                                onClick = { isPlaying = true },
                                                modifier = Modifier
                                                    .size(56.dp)
                                                    .background(Color.White.copy(alpha = 0.8f), CircleShape)
                                            ) {
                                                Icon(imageVector = Icons.Default.PlayArrow, contentDescription = "Play", tint = Color(0xFF5D45FF), modifier = Modifier.size(36.dp))
                                            }
                                        }

                                        if (progress > 0f) {
                                            Box(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .align(Alignment.BottomCenter)
                                                    .height(4.dp)
                                                    .background(Color.White.copy(alpha = 0.3f))
                                            ) {
                                                Box(
                                                    modifier = Modifier
                                                        .fillMaxWidth(progress)
                                                        .fillMaxHeight()
                                                        .background(Color(0xFF5D45FF))
                                                )
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(12.dp))
                                    Text(
                                        text = systemConfig.videoTutorial.title,
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 13.5.sp,
                                        textAlign = TextAlign.Center,
                                        color = AppTextPrimary
                                    )
                                    if (systemConfig.videoTutorial.description.isNotBlank()) {
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(
                                            text = systemConfig.videoTutorial.description,
                                            fontSize = 11.5.sp,
                                            color = AppTextSecondary,
                                            textAlign = TextAlign.Center
                                        )
                                    }

                                    Spacer(modifier = Modifier.height(12.dp))
                                    Button(
                                        onClick = {
                                            try {
                                                val intent = Intent(Intent.ACTION_VIEW, Uri.parse(systemConfig.videoTutorial.videoUrl)).apply {
                                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                                }
                                                context.startActivity(intent)
                                            } catch (e: Exception) {
                                                Toast.makeText(context, "Could not open video player", Toast.LENGTH_SHORT).show()
                                            }
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                                        shape = RoundedCornerShape(10.dp),
                                        modifier = Modifier.fillMaxWidth().height(42.dp)
                                    ) {
                                        Icon(Icons.Default.PlayCircle, null, modifier = Modifier.size(18.dp))
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text("Watch Full Video (${systemConfig.videoTutorial.duration})", fontWeight = FontWeight.Bold, fontSize = 12.5.sp)
                                    }
                                }
                            }

                            "Contact" -> {
                                val context = LocalContext.current
                                Column(
                                    verticalArrangement = Arrangement.spacedBy(12.dp),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    // Hotline support
                                    Surface(
                                        onClick = {
                                            try {
                                                val dialIntent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:${systemConfig.supportHotline.replace(" ", "")}"))
                                                dialIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                                context.startActivity(dialIntent)
                                            } catch (e: Exception) {
                                                Toast.makeText(context, "Could not launch dialer: ${e.message}", Toast.LENGTH_SHORT).show()
                                            }
                                        },
                                        shape = RoundedCornerShape(10.dp),
                                        color = if (isDarkModeGlobal) Color(0xFF1E1F26) else Color(0xFFF8FAFC),
                                        border = BorderStroke(1.dp, AppCardBorderColor),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Row(
                                            modifier = Modifier.padding(12.dp),
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.SpaceBetween
                                        ) {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                                Box(
                                                    modifier = Modifier.size(36.dp).background(Color(0xFFEFF6FF), CircleShape),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Icon(imageVector = Icons.Default.Call, contentDescription = null, tint = Color(0xFF2563EB), modifier = Modifier.size(18.dp))
                                                }
                                                Column {
                                                    Text("Hotline support (Tap to Call)", fontSize = 11.sp, color = AppTextSecondary)
                                                    Text(systemConfig.supportHotline, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = AppTextPrimary)
                                                }
                                            }
                                            Icon(Icons.Default.ChevronRight, null, tint = AppTextSecondary, modifier = Modifier.size(16.dp))
                                        }
                                    }

                                    // Email inquiries
                                    Surface(
                                        onClick = {
                                            try {
                                                val emailIntent = Intent(Intent.ACTION_SENDTO, Uri.parse("mailto:${systemConfig.supportEmail}")).apply {
                                                    putExtra(Intent.EXTRA_SUBJECT, "SwapnoPay Merchant Query [${activeProfile.businessName}]")
                                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                                }
                                                context.startActivity(emailIntent)
                                            } catch (e: Exception) {
                                                Toast.makeText(context, "Could not launch email: ${e.message}", Toast.LENGTH_SHORT).show()
                                            }
                                        },
                                        shape = RoundedCornerShape(10.dp),
                                        color = if (isDarkModeGlobal) Color(0xFF1E1F26) else Color(0xFFF8FAFC),
                                        border = BorderStroke(1.dp, AppCardBorderColor),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Row(
                                            modifier = Modifier.padding(12.dp),
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.SpaceBetween
                                        ) {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                                Box(
                                                    modifier = Modifier.size(36.dp).background(Color(0xFFF5F3FF), CircleShape),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Icon(imageVector = Icons.Default.Email, contentDescription = null, tint = Color(0xFF7C3AED), modifier = Modifier.size(18.dp))
                                                }
                                                Column {
                                                    Text("Email inquiries (Tap to Email)", fontSize = 11.sp, color = AppTextSecondary)
                                                    Text(systemConfig.supportEmail, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = AppTextPrimary)
                                                }
                                            }
                                            Icon(Icons.Default.ChevronRight, null, tint = AppTextSecondary, modifier = Modifier.size(16.dp))
                                        }
                                    }

                                    // WhatsApp Support
                                    Surface(
                                        onClick = {
                                            val cleanNumber = systemConfig.supportWhatsapp.replace("+", "").replace(" ", "").replace("-", "")
                                            val waUrl = if (systemConfig.supportWhatsapp.startsWith("http")) systemConfig.supportWhatsapp else "https://wa.me/$cleanNumber"
                                            try {
                                                val intent = Intent(Intent.ACTION_VIEW, Uri.parse(waUrl)).apply { addFlags(Intent.FLAG_ACTIVITY_NEW_TASK) }
                                                context.startActivity(intent)
                                            } catch (e: Exception) {
                                                Toast.makeText(context, "WhatsApp not available", Toast.LENGTH_SHORT).show()
                                            }
                                        },
                                        shape = RoundedCornerShape(10.dp),
                                        color = if (isDarkModeGlobal) Color(0xFF1E1F26) else Color(0xFFF8FAFC),
                                        border = BorderStroke(1.dp, AppCardBorderColor),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Row(
                                            modifier = Modifier.padding(12.dp),
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.SpaceBetween
                                        ) {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                                Box(
                                                    modifier = Modifier.size(36.dp).background(Color(0xFFECFDF5), CircleShape),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Icon(imageVector = Icons.Default.Chat, contentDescription = null, tint = Color(0xFF10B981), modifier = Modifier.size(18.dp))
                                                }
                                                Column {
                                                    Text("WhatsApp Support Chat", fontSize = 11.sp, color = AppTextSecondary)
                                                    Text(systemConfig.supportWhatsapp, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = AppTextPrimary)
                                                }
                                            }
                                            Icon(Icons.Default.ChevronRight, null, tint = AppTextSecondary, modifier = Modifier.size(16.dp))
                                        }
                                    }

                                    // Headquarters & Hours
                                    Column(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .background(if (isDarkModeGlobal) Color(0xFF181A20) else Color(0xFFF1F5F9), RoundedCornerShape(10.dp))
                                            .padding(12.dp),
                                        verticalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            Icon(imageVector = Icons.Default.LocationOn, contentDescription = null, tint = AppTextSecondary, modifier = Modifier.size(14.dp))
                                            Text("Main Headquarters: ${systemConfig.supportAddress}", fontSize = 11.5.sp, color = AppTextSecondary)
                                        }
                                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            Icon(imageVector = Icons.Default.Schedule, contentDescription = null, tint = AppTextSecondary, modifier = Modifier.size(14.dp))
                                            Text("Operating Hours: ${systemConfig.supportHours}", fontSize = 11.5.sp, color = AppTextSecondary)
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(4.dp))

                                    Button(
                                        onClick = {
                                            activeCategoryDialog = null
                                            viewModel.navigateTo("SupportChat")
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                                        shape = RoundedCornerShape(12.dp),
                                        modifier = Modifier.fillMaxWidth().height(46.dp)
                                    ) {
                                        Icon(Icons.Default.SupportAgent, null, modifier = Modifier.size(18.dp))
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text("Launch In-App Live Support Chat", fontWeight = FontWeight.Bold)
                                    }
                                }
                            }

                            "Ticket" -> {
                                val context = LocalContext.current
                                Column(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .heightIn(max = 420.dp)
                                        .verticalScroll(rememberScrollState()),
                                    verticalArrangement = Arrangement.spacedBy(12.dp)
                                ) {
                                    // Ticket Tab Switcher
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .background(if (isDarkModeGlobal) Color(0xFF1E1F26) else Color(0xFFF1F5F9), RoundedCornerShape(10.dp))
                                            .padding(3.dp),
                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Surface(
                                            onClick = { ticketTab = "NEW" },
                                            shape = RoundedCornerShape(8.dp),
                                            color = if (ticketTab == "NEW") BrandPurple else Color.Transparent,
                                            modifier = Modifier.weight(1f)
                                        ) {
                                            Text(
                                                "New Ticket",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 12.sp,
                                                color = if (ticketTab == "NEW") Color.White else AppTextSecondary,
                                                textAlign = TextAlign.Center,
                                                modifier = Modifier.padding(vertical = 8.dp)
                                            )
                                        }

                                        Surface(
                                            onClick = { ticketTab = "MY_TICKETS" },
                                            shape = RoundedCornerShape(8.dp),
                                            color = if (ticketTab == "MY_TICKETS") BrandPurple else Color.Transparent,
                                            modifier = Modifier.weight(1f)
                                        ) {
                                            Text(
                                                "My Tickets (${myTickets.size})",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 12.sp,
                                                color = if (ticketTab == "MY_TICKETS") Color.White else AppTextSecondary,
                                                textAlign = TextAlign.Center,
                                                modifier = Modifier.padding(vertical = 8.dp)
                                            )
                                        }
                                    }

                                    if (ticketTab == "NEW") {
                                        if (ticketSubmitted) {
                                            Column(
                                                modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp),
                                                horizontalAlignment = Alignment.CenterHorizontally
                                            ) {
                                                Box(
                                                    modifier = Modifier
                                                        .size(52.dp)
                                                        .background(Color(0xFFECFDF5), CircleShape),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Icon(imageVector = Icons.Default.Check, contentDescription = null, tint = Color(0xFF10B981), modifier = Modifier.size(28.dp))
                                                }
                                                Spacer(modifier = Modifier.height(10.dp))
                                                Text("Ticket Submitted Successfully!", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = Color(0xFF10B981))
                                                Spacer(modifier = Modifier.height(4.dp))
                                                Text(
                                                    "Ticket Reference: #${lastSubmittedTicketId.takeLast(6).ifEmpty { "SP-94827" }}. Synced to Platform Owner Admin Helpdesk in real-time.",
                                                    fontSize = 12.sp,
                                                    color = AppTextSecondary,
                                                    textAlign = TextAlign.Center,
                                                    lineHeight = 16.sp
                                                )

                                                Spacer(modifier = Modifier.height(14.dp))
                                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                                    Button(
                                                        onClick = { ticketTab = "MY_TICKETS" },
                                                        colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                                                        shape = RoundedCornerShape(10.dp)
                                                    ) {
                                                        Text("View My Tickets", fontWeight = FontWeight.Bold)
                                                    }
                                                    OutlinedButton(
                                                        onClick = {
                                                            ticketSubmitted = false
                                                            ticketSubject = ""
                                                            ticketDesc = ""
                                                        },
                                                        shape = RoundedCornerShape(10.dp)
                                                    ) {
                                                        Text("New Ticket", fontWeight = FontWeight.Bold)
                                                    }
                                                }
                                            }
                                        } else {
                                            Column(
                                                verticalArrangement = Arrangement.spacedBy(10.dp),
                                                modifier = Modifier.fillMaxWidth()
                                            ) {
                                                Text("Category:", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AppTextSecondary)
                                                LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                                    val categories = systemConfig.ticketCategories.ifEmpty {
                                                        listOf("Payment Matching", "Gateway Setup", "API & Webhooks", "Billing & Plan", "Account & Verification", "Bug Report", "Fraud & Appeal", "Feature Request")
                                                    }
                                                    items(categories) { cat ->
                                                        val isSelected = ticketCategory == cat
                                                        Surface(
                                                            onClick = { ticketCategory = cat },
                                                            shape = RoundedCornerShape(8.dp),
                                                            color = if (isSelected) BrandPurple else if (isDarkModeGlobal) Color(0xFF1E1F26) else Color(0xFFF1F5F9),
                                                            border = BorderStroke(1.dp, if (isSelected) BrandPurple else AppCardBorderColor)
                                                        ) {
                                                            Text(
                                                                cat,
                                                                fontSize = 11.sp,
                                                                fontWeight = FontWeight.SemiBold,
                                                                color = if (isSelected) Color.White else AppTextPrimary,
                                                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                                                            )
                                                        }
                                                    }
                                                }

                                                OutlinedTextField(
                                                    value = ticketSubject,
                                                    onValueChange = { ticketSubject = it },
                                                    label = { Text("Subject / Issue title") },
                                                    placeholder = { Text("e.g. Payment #ORD-1234 not matched") },
                                                    modifier = Modifier.fillMaxWidth(),
                                                    shape = RoundedCornerShape(12.dp)
                                                )

                                                OutlinedTextField(
                                                    value = ticketDesc,
                                                    onValueChange = { ticketDesc = it },
                                                    label = { Text("Describe the issue in detail") },
                                                    placeholder = { Text("Include exact transaction IDs, sender numbers, or dates.") },
                                                    modifier = Modifier.fillMaxWidth().height(90.dp),
                                                    shape = RoundedCornerShape(12.dp)
                                                )

                                                Button(
                                                    onClick = {
                                                        if (ticketSubject.trim().isNotEmpty() && ticketDesc.trim().isNotEmpty()) {
                                                            viewModel.submitSupportTicket(ticketCategory, ticketSubject.trim(), ticketDesc.trim()) { saved ->
                                                                if (saved) {
                                                                    lastSubmittedTicketId = viewModel.lastSavedSupportTicketId.value
                                                                    ticketSubmitted = true
                                                                    Toast.makeText(context, "Ticket submitted to Admin Panel!", Toast.LENGTH_SHORT).show()
                                                                } else {
                                                                    Toast.makeText(context, "Ticket was not saved. Please retry.", Toast.LENGTH_LONG).show()
                                                                }
                                                            }
                                                        }
                                                    },
                                                    colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                                                    shape = RoundedCornerShape(12.dp),
                                                    modifier = Modifier.fillMaxWidth().height(46.dp),
                                                    enabled = ticketSubject.trim().isNotEmpty() && ticketDesc.trim().isNotEmpty()
                                                ) {
                                                    Text("Submit Support Ticket", fontWeight = FontWeight.Bold)
                                                }
                                            }
                                        }
                                    } else {
                                        // My Tickets List
                                        if (myTickets.isEmpty()) {
                                            Column(
                                                modifier = Modifier.fillMaxWidth().padding(24.dp),
                                                horizontalAlignment = Alignment.CenterHorizontally
                                            ) {
                                                Text("No support tickets submitted yet.", fontSize = 13.sp, color = AppTextSecondary)
                                                Spacer(modifier = Modifier.height(8.dp))
                                                Button(onClick = { ticketTab = "NEW" }, colors = ButtonDefaults.buttonColors(containerColor = BrandPurple)) {
                                                    Text("Submit Your First Ticket")
                                                }
                                            }
                                        } else {
                                            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                myTickets.forEach { t ->
                                                    Card(
                                                        shape = RoundedCornerShape(12.dp),
                                                        colors = CardDefaults.cardColors(containerColor = if (isDarkModeGlobal) Color(0xFF181A20) else Color(0xFFF8FAFC)),
                                                        border = BorderStroke(1.dp, AppCardBorderColor),
                                                        modifier = Modifier.fillMaxWidth()
                                                    ) {
                                                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                                            Row(
                                                                modifier = Modifier.fillMaxWidth(),
                                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                                verticalAlignment = Alignment.CenterVertically
                                                            ) {
                                                                Text("#${t.id.takeLast(6)} • ${t.category}", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = BrandPurple)
                                                                Surface(
                                                                    shape = RoundedCornerShape(6.dp),
                                                                    color = when (t.status) {
                                                                        "RESOLVED" -> Color(0xFFECFDF5)
                                                                        "IN_PROGRESS" -> Color(0xFFFFFBEB)
                                                                        "CLOSED" -> Color(0xFFF1F5F9)
                                                                        else -> Color(0xFFFEF2F2)
                                                                    }
                                                                ) {
                                                                    Text(
                                                                        text = t.status,
                                                                        fontSize = 10.sp,
                                                                        fontWeight = FontWeight.Bold,
                                                                        color = when (t.status) {
                                                                            "RESOLVED" -> Color(0xFF059669)
                                                                            "IN_PROGRESS" -> Color(0xFFD97706)
                                                                            "CLOSED" -> Color(0xFF64748B)
                                                                            else -> Color(0xFFDC2626)
                                                                        },
                                                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                                                    )
                                                                }
                                                            }

                                                            Text(t.subject, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = AppTextPrimary)
                                                            Text(t.description, fontSize = 11.5.sp, color = AppTextSecondary, maxLines = 2, overflow = TextOverflow.Ellipsis)

                                                            if (t.adminReply.isNotBlank()) {
                                                                Box(
                                                                    modifier = Modifier
                                                                        .fillMaxWidth()
                                                                        .background(Color(0xFFECFDF5), RoundedCornerShape(8.dp))
                                                                        .padding(8.dp)
                                                                ) {
                                                                    Column {
                                                                        Text("Admin Resolution:", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFF065F46))
                                                                        Text(t.adminReply, fontSize = 11.5.sp, color = Color(0xFF047857))
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

                            "Status" -> {
                                val statusMetrics = listOf(
                                    Triple("SMS Matching Engine", "Operational", Color(0xFF10B981)),
                                    Triple("Supabase Sync Bridge", "Connected", Color(0xFF10B981)),
                                    Triple("Payment Gateway APIs", "Active", Color(0xFF10B981)),
                                    Triple("Average Match Latency", "1.24 seconds", BrandPurple),
                                    Triple("Support Helpdesk Queue", "Normal (No delays)", Color(0xFF10B981))
                                )

                                Column(
                                    verticalArrangement = Arrangement.spacedBy(10.dp),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    statusMetrics.forEach { metric ->
                                        Row(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .background(AppCardBg, RoundedCornerShape(10.dp))
                                                .border(BorderStroke(1.dp, AppCardBorderColor), RoundedCornerShape(10.dp))
                                                .padding(12.dp),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(metric.first, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = AppTextPrimary)
                                            Box(
                                                modifier = Modifier
                                                    .background(metric.third.copy(alpha = 0.1f), RoundedCornerShape(6.dp))
                                                    .padding(horizontal = 8.dp, vertical = 4.dp)
                                            ) {
                                                Text(metric.second, fontWeight = FontWeight.Bold, fontSize = 10.sp, color = metric.third)
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

@Composable
fun QuickHelpCard(
    title: String,
    desc: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    bgColor: Color,
    iconColor: Color,
    onClick: () -> Unit
) {
    Card(
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = AppCardBg),
        border = BorderStroke(1.dp, AppCardBorderColor),
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() }
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .background(bgColor, CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = iconColor,
                    modifier = Modifier.size(22.dp)
                )
            }

            Column(
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = title,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    color = AppTextPrimary,
                    textAlign = TextAlign.Center,
                    maxLines = 1
                )
                Text(
                    text = desc,
                    fontSize = 9.sp,
                    color = AppTextSecondary,
                    textAlign = TextAlign.Center,
                    maxLines = 1
                )
            }
        }
    }
}

@Composable
fun ArticleRowItem(
    article: SupportArticle,
    onClick: () -> Unit
) {
    Card(
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = AppCardBg),
        border = BorderStroke(1.dp, AppCardBorderColor),
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() }
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.weight(1f)
            ) {
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .background(article.bgColor, RoundedCornerShape(8.dp)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = article.icon,
                        contentDescription = null,
                        tint = article.iconColor,
                        modifier = Modifier.size(18.dp)
                    )
                }

                Text(
                    text = article.title,
                    fontWeight = FontWeight.Medium,
                    fontSize = 13.sp,
                    color = AppTextPrimary
                )
            }

            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = AppTextSecondary,
                modifier = Modifier.size(16.dp)
            )
        }
    }
}

data class SupportArticle(
    val id: String,
    val title: String,
    val icon: androidx.compose.ui.graphics.vector.ImageVector,
    val iconColor: Color,
    val bgColor: Color,
    val content: String
)

// 16.5 PRIVACY POLICY SCREEN
@Composable
fun PrivacyPolicyScreen(viewModel: AppViewModel) {
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    val isBangla = viewModel.language.collectAsState().value == "Bangla"
    val brandText = if (isDarkMode) Color.White else Color(0xFF1E293B)
    val brandTextMuted = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)

    Scaffold(
        topBar = {
            GradientTopBar(
                title = if (isBangla) "গোপনীয়তা নীতি" else "Privacy Policy",
                onBack = { viewModel.goBack() }
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(if (isDarkMode) Color(0xFF111319) else Color(0xFFF8F9FD))
                .verticalScroll(rememberScrollState())
                .padding(24.dp)
        ) {
            Card(
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF1E1F26) else Color.White),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "1. SMS Read Permissions",
                        fontWeight = FontWeight.Bold,
                        fontSize = 15.sp,
                        color = brandText
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "SwapnoPay requires read SMS permissions strictly to capture and parse incoming mobile financial services notifications (e.g. bKash, Nagad, Rocket, Upay). Personal, conversational, and marketing messages are explicitly ignored and are never stored or uploaded.",
                        fontSize = 13.sp,
                        color = brandTextMuted,
                        lineHeight = 18.sp
                    )
                    
                    Spacer(modifier = Modifier.height(20.dp))
                    Divider(color = AppDividerColor)
                    Spacer(modifier = Modifier.height(20.dp))

                    Text(
                        text = "2. Cryptographic Security & Hashing",
                        fontWeight = FontWeight.Bold,
                        fontSize = 15.sp,
                        color = brandText
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "Your connection parameters (Supabase Url, anonymous API key) are hashed utilizing secure SHA-256 algorithms before syncing to external monitoring logs. The active API keys are saved locally in private app sandboxed storage settings using device-level encryption algorithms.",
                        fontSize = 13.sp,
                        color = brandTextMuted,
                        lineHeight = 18.sp
                    )

                    Spacer(modifier = Modifier.height(20.dp))
                    Divider(color = AppDividerColor)
                    Spacer(modifier = Modifier.height(20.dp))

                    Text(
                        text = "3. Multiple Devices Management Data",
                        fontWeight = FontWeight.Bold,
                        fontSize = 15.sp,
                        color = brandText
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "Hardware parameters (device model, battery percentage, online status, network latency logs) are synced to your database ledger profile to allow merchants to audit active devices in their integration dashboard and identify offline receivers immediately.",
                        fontSize = 13.sp,
                        color = brandTextMuted,
                        lineHeight = 18.sp
                    )

                    Spacer(modifier = Modifier.height(20.dp))
                    Divider(color = AppDividerColor)
                    Spacer(modifier = Modifier.height(20.dp))

                    Text(
                        text = "4. Third-Party Disclosures",
                        fontWeight = FontWeight.Bold,
                        fontSize = 15.sp,
                        color = brandText
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "We never sell, rent, or lease merchant details or customer payment information to third-party ad networks or brokers. Hashed details are securely synced to your selected Firebase/Supabase instances only.",
                        fontSize = 13.sp,
                        color = brandTextMuted,
                        lineHeight = 18.sp
                    )
                }
            }
        }
    }
}

// 16.6 PAYMENT FORMS MANAGEMENT SCREENS
data class FormFieldConfig(
    val id: String,
    val type: String, // short, mcq, checklist, document
    val label: String,
    val options: List<String> = emptyList(),
    val required: Boolean = true,
    val imageUrl: String = ""
)

data class FieldBuilderState(
    val id: String,
    val type: String, // short, mcq, checklist, document
    val label: String,
    val options: String = "", // comma-separated
    val required: Boolean = true,
    val imageUrl: String = ""
)

@Composable
fun CopyableCodeBlock(
    title: String,
    code: String,
    clipboardManager: androidx.compose.ui.platform.ClipboardManager,
    context: android.content.Context
) {
    Card(
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF0F172A)),
        border = BorderStroke(1.dp, Color(0xFF334155)),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = title,
                    color = Color(0xFF94A3B8),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.Monospace
                )
                IconButton(
                    onClick = {
                        clipboardManager.setText(AnnotatedString(code))
                        android.widget.Toast.makeText(context, "Copied to clipboard! 📋", android.widget.Toast.LENGTH_SHORT).show()
                    },
                    modifier = Modifier.size(28.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.ContentCopy,
                        contentDescription = "Copy Code",
                        tint = Color(0xFF94A3B8),
                        modifier = Modifier.size(14.dp)
                    )
                }
            }
            Spacer(modifier = Modifier.height(6.dp))
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .background(Color.Black.copy(alpha = 0.3f), RoundedCornerShape(8.dp))
                    .padding(10.dp)
            ) {
                Text(
                    text = code,
                    color = Color(0xFF38BDF8),
                    fontFamily = FontFamily.Monospace,
                    fontSize = 11.sp,
                    lineHeight = 16.sp
                )
            }
        }
    }
}

@Composable
fun PaymentFormsScreen(viewModel: AppViewModel) {
    val context = androidx.compose.ui.platform.LocalContext.current
    val clipboardManager = androidx.compose.ui.platform.LocalClipboardManager.current
    val isDarkMode by viewModel.isDarkMode.collectAsState()

    val bgCanvas = if (isDarkMode) Color(0xFF08080A) else Color(0xFFF8F9FF)
    val cardBg = if (isDarkMode) Color(0xFF12100C) else Color.White
    val cardBorder = if (isDarkMode) Color(0xFF2E2413) else Color(0xFFD4E4FC)
    val containerBg = if (isDarkMode) Color(0xFF1B150A) else Color(0xFFF1F5F9)
    val goldPrimary = if (isDarkMode) Color(0xFFF5C518) else Color(0xFFD97706)
    val goldLight = if (isDarkMode) Color(0xFFFFDF73) else Color(0xFFF59E0B)
    val goldDarkBg = if (isDarkMode) Color(0xFF281E0A) else Color(0xFFFFFBEB)
    val textPrimary = if (isDarkMode) Color(0xFFFFFFFF) else Color(0xFF0F172A)
    val textMuted = if (isDarkMode) Color(0xFF8E8E93) else Color(0xFF64748B)
    val greenLive = Color(0xFF10B981)

    val formsList by viewModel.paymentForms.collectAsState()

    var showAiGeneratorModal by remember { mutableStateOf(false) }
    var showTemplatePickerModal by remember { mutableStateOf(false) }
    var showPublishSheet by remember { mutableStateOf(false) }
    var aiPromptText by remember { mutableStateOf("") }
    var isGeneratingAi by remember { mutableStateOf(false) }
    var showFormLimitDialog by remember { mutableStateOf(false) }
    var formToDelete by remember { mutableStateOf<org.json.JSONObject?>(null) }

    LaunchedEffect(Unit) {
        viewModel.fetchPaymentForms()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(bgCanvas)
    ) {
        Scaffold(
            containerColor = bgCanvas,
            topBar = {
                // Top Header Bar matching exact uploaded design
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(bgCanvas)
                        .statusBarsPadding()
                ) {
                    Canvas(
                        modifier = Modifier.matchParentSize()
                    ) {
                        val w = size.width
                        val h = size.height

                        // Radial subtle glow top right
                        drawRect(
                            brush = Brush.radialGradient(
                                colors = listOf(
                                    if (isDarkMode) Color(0xFF8C6B1C).copy(alpha = 0.25f) else Color(0xFFFDE68A).copy(alpha = 0.35f),
                                    Color.Transparent
                                ),
                                center = Offset(w * 0.95f, h * 0.2f),
                                radius = w * 0.6f
                            )
                        )
                    }

                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 20.dp, vertical = 14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text(
                                text = "Hosted Forms",
                                fontSize = 24.sp,
                                fontWeight = FontWeight.ExtraBold,
                                color = textPrimary
                            )
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(
                                text = "Create, manage and track your forms",
                                fontSize = 13.sp,
                                color = textMuted
                            )
                        }

                        // Notification Bell with Gold Border & Badge
                        Box(
                            modifier = Modifier
                                .size(44.dp)
                                .clip(CircleShape)
                                .background(cardBg)
                                .border(BorderStroke(1.dp, cardBorder), CircleShape)
                                .clickable { viewModel.navigateTo("Notifications") },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Outlined.Notifications,
                                contentDescription = "Notifications",
                                tint = goldPrimary,
                                modifier = Modifier.size(20.dp)
                            )
                            Box(
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .offset(x = (-8).dp, y = 8.dp)
                                    .size(8.dp)
                                    .clip(CircleShape)
                                    .background(goldPrimary)
                            )
                        }
                    }
                }
            }
        ) { innerPadding ->
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(innerPadding)
                    .padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
                contentPadding = PaddingValues(top = 10.dp, bottom = 24.dp)
            ) {

                // ── 1. METRICS CARDS ROW (Forms & Orders with Canvas Charts) ─────────────────
                item {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        // Stat Card 1: Forms
                        Card(
                            modifier = Modifier
                                .weight(1f)
                                .clickable { viewModel.navigateTo("FormBuilderStudio") },
                            shape = RoundedCornerShape(18.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(14.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(34.dp)
                                            .clip(RoundedCornerShape(10.dp))
                                            .background(goldDarkBg)
                                            .border(BorderStroke(1.dp, cardBorder), RoundedCornerShape(10.dp)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Description,
                                            contentDescription = null,
                                            tint = goldPrimary,
                                            modifier = Modifier.size(18.dp)
                                        )
                                    }

                                    Surface(
                                        shape = RoundedCornerShape(20.dp),
                                        color = goldDarkBg,
                                        border = BorderStroke(1.dp, cardBorder)
                                    ) {
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(4.dp),
                                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                                        ) {
                                            Box(
                                                modifier = Modifier
                                                    .size(6.dp)
                                                    .clip(CircleShape)
                                                    .background(goldPrimary)
                                            )
                                            Text(
                                                text = "Total",
                                                fontSize = 10.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = goldPrimary
                                            )
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(10.dp))

                                Text(
                                    text = "Forms",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textMuted
                                )
                                Text(
                                    text = "${formsList.size}",
                                    fontSize = 30.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = goldPrimary
                                )
                                Text(
                                    text = "Total Forms",
                                    fontSize = 11.sp,
                                    color = textMuted
                                )

                                Spacer(modifier = Modifier.height(12.dp))

                                // Glowing Yellow Line Graph Canvas
                                Canvas(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(40.dp)
                                ) {
                                    val w = size.width
                                    val h = size.height
                                    val path = Path().apply {
                                        moveTo(0f, h * 0.85f)
                                        cubicTo(w * 0.25f, h * 0.95f, w * 0.45f, h * 0.4f, w * 0.65f, h * 0.65f)
                                        cubicTo(w * 0.8f, h * 0.85f, w * 0.9f, h * 0.15f, w, h * 0.2f)
                                    }
                                    drawPath(
                                        path = path,
                                        color = goldPrimary,
                                        style = Stroke(width = 3.dp.toPx(), cap = StrokeCap.Round)
                                    )
                                    drawCircle(
                                        color = goldLight,
                                        radius = 5.dp.toPx(),
                                        center = Offset(w, h * 0.2f)
                                    )
                                    drawCircle(
                                        color = goldPrimary.copy(alpha = 0.5f),
                                        radius = 8.dp.toPx(),
                                        center = Offset(w, h * 0.2f)
                                    )
                                }
                            }
                        }

                        // Stat Card 2: Live Forms
                        val liveFormsCount = formsList.count {
                            it.optString("status", "").uppercase() == "PUBLISHED" || it.optBoolean("is_published", false)
                        }
                        Card(
                            modifier = Modifier
                                .weight(1f)
                                .clickable { viewModel.navigateTo("FormResponses") },
                            shape = RoundedCornerShape(18.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(14.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(34.dp)
                                            .clip(RoundedCornerShape(10.dp))
                                            .background(goldDarkBg)
                                            .border(BorderStroke(1.dp, cardBorder), RoundedCornerShape(10.dp)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.CheckCircle,
                                            contentDescription = null,
                                            tint = greenLive,
                                            modifier = Modifier.size(18.dp)
                                        )
                                    }

                                    Surface(
                                        shape = RoundedCornerShape(20.dp),
                                        color = greenLive.copy(alpha = 0.15f),
                                        border = BorderStroke(1.dp, greenLive.copy(alpha = 0.4f))
                                    ) {
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(4.dp),
                                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                                        ) {
                                            Box(
                                                modifier = Modifier
                                                    .size(6.dp)
                                                    .clip(CircleShape)
                                                    .background(greenLive)
                                            )
                                            Text(
                                                text = "Active",
                                                fontSize = 10.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = greenLive
                                            )
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(10.dp))

                                Text(
                                    text = "Published",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textMuted
                                )
                                Text(
                                    text = "$liveFormsCount",
                                    fontSize = 30.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = goldPrimary
                                )
                                Text(
                                    text = "Live Forms",
                                    fontSize = 11.sp,
                                    color = textMuted
                                )

                                Spacer(modifier = Modifier.height(12.dp))

                                // Vertical Bar Chart Canvas
                                Canvas(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(40.dp)
                                ) {
                                    val barWidth = 7.dp.toPx()
                                    val barGap = (size.width - (8 * barWidth)) / 7f
                                    val heights = listOf(0.2f, 0.35f, 0.25f, 0.45f, 0.4f, 0.6f, 0.55f, 0.95f)
                                    heights.forEachIndexed { i, factor ->
                                        val x = i * (barWidth + barGap)
                                        val barH = size.height * factor
                                        drawRoundRect(
                                            color = if (i == 7) greenLive else goldDarkBg,
                                            topLeft = Offset(x, size.height - barH),
                                            size = androidx.compose.ui.geometry.Size(barWidth, barH),
                                            cornerRadius = CornerRadius(3.dp.toPx())
                                        )
                                    }
                                }
                            }
                        }
                    }
                }

                // ── 2. QUICK CREATION TOOLS BAR (Create Custom, Build with AI, Template) ───────
                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(20.dp),
                        colors = CardDefaults.cardColors(containerColor = cardBg),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 14.dp, horizontal = 8.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            // 1. Create Custom
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                 modifier = Modifier
                                     .weight(1f)
                                     .clip(RoundedCornerShape(12.dp))
                                     .clickable {
                                         if (formsList.size >= 20) {
                                             showFormLimitDialog = true
                                         } else {
                                             viewModel.createNewHostedForm("New Payment Form")
                                             viewModel.navigateTo("FormBuilderStudio")
                                         }
                                     }
                                    .padding(vertical = 6.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(46.dp)
                                        .clip(CircleShape)
                                        .background(goldDarkBg)
                                        .border(BorderStroke(1.dp, goldPrimary), CircleShape),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Add,
                                        contentDescription = "Create Custom",
                                        tint = goldPrimary,
                                        modifier = Modifier.size(22.dp)
                                    )
                                }
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = "Create Custom",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "Start from scratch",
                                    fontSize = 10.sp,
                                    color = textMuted
                                )
                            }

                            Box(
                                modifier = Modifier
                                    .width(1.dp)
                                    .height(50.dp)
                                    .background(cardBorder)
                            )

                            // 2. Build with AI
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                modifier = Modifier
                                    .weight(1f)
                                    .clip(RoundedCornerShape(12.dp))
                                    .clickable { showAiGeneratorModal = true }
                                    .padding(vertical = 6.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(46.dp)
                                        .clip(CircleShape)
                                        .background(goldDarkBg)
                                        .border(BorderStroke(1.dp, goldPrimary), CircleShape),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.AutoAwesome,
                                        contentDescription = "Build with AI",
                                        tint = goldPrimary,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = "Build with AI",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "Smart generation",
                                    fontSize = 10.sp,
                                    color = textMuted
                                )
                            }

                            Box(
                                modifier = Modifier
                                    .width(1.dp)
                                    .height(50.dp)
                                    .background(cardBorder)
                            )

                            // 3. Template
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                modifier = Modifier
                                    .weight(1f)
                                    .clip(RoundedCornerShape(12.dp))
                                    .clickable { showTemplatePickerModal = true }
                                    .padding(vertical = 6.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(46.dp)
                                        .clip(CircleShape)
                                        .background(goldDarkBg)
                                        .border(BorderStroke(1.dp, goldPrimary), CircleShape),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.GridView,
                                        contentDescription = "Template",
                                        tint = goldPrimary,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = "Template",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "Use pre-built",
                                    fontSize = 10.sp,
                                    color = textMuted
                                )
                            }
                        }
                    }
                }

                // ── 3. RECENT PROJECTS SECTION ──────────────────────────────────────────
                item {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 8.dp, bottom = 4.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Recent Projects",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary
                        )
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.clickable { showTemplatePickerModal = true }
                        ) {
                            Text(
                                text = "See All",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                color = goldPrimary
                            )
                            Spacer(modifier = Modifier.width(2.dp))
                            Icon(
                                imageVector = Icons.Default.ChevronRight,
                                contentDescription = null,
                                tint = goldPrimary,
                                modifier = Modifier.size(16.dp)
                            )
                        }
                    }
                }

                if (formsList.isEmpty()) {
                    item {
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Column(
                                modifier = Modifier.fillMaxWidth().padding(24.dp),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Icon(Icons.Outlined.Description, null, tint = goldPrimary, modifier = Modifier.size(32.dp))
                                Spacer(modifier = Modifier.height(8.dp))
                                Text("No payment forms yet", fontWeight = FontWeight.Bold, color = textPrimary)
                                Text("Create a form to save it locally and sync it to Supabase.", fontSize = 12.sp, color = textMuted, textAlign = TextAlign.Center)
                            }
                        }
                    }
                }

                itemsIndexed(formsList.take(20)) { _, form ->
                    val template = form.optString("template_type", "SINGLE_PRODUCT")
                    val projectIcon = when (template) {
                        "EVENT" -> Icons.Outlined.CalendarToday
                        "DIGITAL_PRODUCT" -> Icons.Outlined.PictureAsPdf
                        "MEMBERSHIP" -> Icons.Outlined.Badge
                        else -> Icons.Outlined.Edit
                    }
                    Card(
                        modifier = Modifier
                             .fillMaxWidth()
                             .clickable {
                                 viewModel.selectCachedPaymentForm(form)
                                 viewModel.navigateTo("FormBuilderStudio")
                             },
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = cardBg),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(14.dp),
                                modifier = Modifier.weight(1f)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(44.dp)
                                        .clip(RoundedCornerShape(12.dp))
                                        .background(containerBg)
                                        .border(BorderStroke(1.dp, cardBorder), RoundedCornerShape(12.dp)),
                                    contentAlignment = Alignment.Center
                                ) {
                                     Icon(
                                         imageVector = projectIcon,
                                        contentDescription = null,
                                        tint = goldPrimary,
                                        modifier = Modifier.size(22.dp)
                                    )
                                }

                                Column(modifier = Modifier.weight(1f, fill = false)) {
                                     Text(
                                         text = form.optString("title", "Untitled Payment Form"),
                                        fontSize = 15.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = textPrimary,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                    Spacer(modifier = Modifier.height(2.dp))
                                     Text(
                                         text = form.optString("slug", "Draft"),
                                        fontSize = 12.sp,
                                        color = textMuted,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                }
                            }

                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Surface(
                                    shape = RoundedCornerShape(20.dp),
                                    color = goldDarkBg,
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                     Text(
                                         text = if (form.optString("status") == "PUBLISHED") "Live" else "Draft",
                                        fontSize = 10.5.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = goldPrimary,
                                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                                        softWrap = false,
                                        maxLines = 1
                                    )
                                }

                                IconButton(
                                    onClick = { formToDelete = form },
                                    modifier = Modifier.size(32.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Outlined.Delete,
                                        contentDescription = "Delete Form",
                                        tint = Color(0xFFEF4444).copy(alpha = 0.8f),
                                        modifier = Modifier.size(17.dp)
                                    )
                                }

                                Icon(
                                    imageVector = Icons.Default.ChevronRight,
                                    contentDescription = "Open",
                                    tint = goldPrimary,
                                    modifier = Modifier.size(20.dp)
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    // ── DELETE / DRAFT FORM CONFIRMATION MODAL ──────────────────────────────
    formToDelete?.let { targetForm ->
        val targetId = targetForm.optString("id")
        val targetTitle = targetForm.optString("title", "Untitled Payment Form")
        EnterpriseGestureModal(
            onDismissRequest = { formToDelete = null },
            title = "Delete or Move to Draft?",
            subtitle = targetTitle,
            icon = Icons.Outlined.Delete
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text(
                    text = "What would you like to do with '$targetTitle'? You can move it back to draft (unpublish) or permanently delete it from local and cloud databases.",
                    fontSize = 13.sp,
                    color = textPrimary,
                    lineHeight = 18.sp
                )

                // Option 1: Move to Draft
                Button(
                    onClick = {
                        viewModel.deletePaymentForm(targetId, setAsDraft = true) {
                            Toast.makeText(context, "Form '$targetTitle' moved to Draft", Toast.LENGTH_SHORT).show()
                        }
                        formToDelete = null
                    },
                    modifier = Modifier.fillMaxWidth().height(46.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                ) {
                    Icon(Icons.Outlined.Edit, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Move to Draft (Unpublish)", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                }

                // Option 2: Delete Permanently
                Button(
                    onClick = {
                        viewModel.deletePaymentForm(targetId, setAsDraft = false) {
                            Toast.makeText(context, "Form '$targetTitle' permanently deleted", Toast.LENGTH_SHORT).show()
                        }
                        formToDelete = null
                    },
                    modifier = Modifier.fillMaxWidth().height(46.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444), contentColor = Color.White)
                ) {
                    Icon(Icons.Outlined.Delete, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Delete Form Permanently", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                }

                OutlinedButton(
                    onClick = { formToDelete = null },
                    modifier = Modifier.fillMaxWidth().height(42.dp),
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Text("Cancel", color = textPrimary, fontSize = 13.sp)
                }
            }
        }
    }


    // ── 5. AI FORM GENERATOR MODAL ──────────────────────────────────────────────
    if (showAiGeneratorModal) {
        EnterpriseGestureModal(
            onDismissRequest = { showAiGeneratorModal = false },
            title = "AI Smart Form Generator",
            subtitle = "Swipe down or drag handle to dismiss",
            icon = Icons.Default.AutoAwesome
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(
                    "Describe your form in plain natural language (e.g., 'Course Registration form for Python Bootcamp with Bkash transaction ID and PDF certificate upload'):",
                    fontSize = 12.sp,
                    color = textMuted
                )

                OutlinedTextField(
                    value = aiPromptText,
                    onValueChange = { aiPromptText = it },
                    placeholder = { Text("e.g. Event Registration for Tech Summit 2026") },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(100.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = goldPrimary,
                        unfocusedBorderColor = cardBorder,
                        focusedTextColor = textPrimary,
                        unfocusedTextColor = textPrimary
                    ),
                    shape = RoundedCornerShape(12.dp)
                )

                Spacer(modifier = Modifier.height(8.dp))

                Button(
                    onClick = {
                        val prompt = aiPromptText.trim()
                        if (prompt.isBlank()) {
                            android.widget.Toast.makeText(
                                context,
                                "Please describe your form first",
                                android.widget.Toast.LENGTH_SHORT
                            ).show()
                            return@Button
                        }
                        if (formsList.size >= 20) {
                            showAiGeneratorModal = false
                            showFormLimitDialog = true
                            return@Button
                        }
                        isGeneratingAi = true
                        // 1. Create a fresh form so we land on a new one
                        viewModel.createNewHostedForm("AI: ${prompt.take(40)}")
                        // 2. Call real Gemini LLM generator with cascade fallback
                        viewModel.generateFormWithGemini(prompt) {
                            isGeneratingAi = false
                            showAiGeneratorModal = false
                            viewModel.navigateTo("FormBuilderStudio")
                        }
                    },
                    enabled = !isGeneratingAi,
                    colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().height(48.dp)
                ) {
                    if (isGeneratingAi) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(20.dp),
                            color = Color.Black,
                            strokeWidth = 2.dp
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Gemini AI Building Form...", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    } else {
                        Text("Generate with Gemini AI ✨", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    }
                }
            }
        }
    }


    // ── 6. TEMPLATE PICKER MODAL ────────────────────────────────────────────────
    if (showTemplatePickerModal) {
        EnterpriseGestureModal(
            onDismissRequest = { showTemplatePickerModal = false },
            title = "Pick a Pre-Built Template",
            subtitle = "Swipe down or drag handle to dismiss",
            icon = Icons.Default.ListAlt
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                val templates = listOf(
                    Triple("Product Sales Form", "Pre-configured with product choices & bKash/Nagad checkout", "SINGLE_PRODUCT"),
                    Triple("Event Registration", "Includes participant details & automated QR pass", "EVENT_TICKETING"),
                    Triple("PDF Document Sales", "Digital product download form with automatic delivery", "DIGITAL_PRODUCT"),
                    Triple("Course Fee Payment", "Student ID, batch selection & fee collection", "EDUCATION_FEE")
                )

                templates.forEach { (title, desc, templateKey) ->
                    Surface(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable {
                                if (formsList.size >= 20) {
                                    showTemplatePickerModal = false
                                    showFormLimitDialog = true
                                } else {
                                    showTemplatePickerModal = false
                                    viewModel.createNewHostedForm(title, templateKey)
                                    viewModel.navigateTo("FormBuilderStudio")
                                }
                            },
                        shape = RoundedCornerShape(12.dp),
                        color = containerBg,
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text(title, fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(desc, fontSize = 11.sp, color = textMuted)
                        }
                    }
                }
            }
        }
    }

    // ── 7. PUBLISH FLOW DIALOG ──────────────────────────────────────────────────
    if (showPublishSheet) {
        EnterpriseGestureModal(
            onDismissRequest = { showPublishSheet = false },
            title = "Hosted Form Link",
            subtitle = "Swipe down or drag handle to dismiss",
            icon = Icons.Default.Send
        ) {
            Column(
                verticalArrangement = Arrangement.spacedBy(12.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                val publicFormUrl = viewModel.hostedFormPublicUrl()
                Text(
                    if (publicFormUrl.isBlank()) "Connect a merchant Supabase project before publishing." else "The form is saved and queued for Supabase sync. Deploy the hosted-form Edge Function once, then this link works without more app configuration.",
                    fontSize = 12.sp,
                    color = textMuted,
                    textAlign = TextAlign.Center
                )

                Surface(
                    shape = RoundedCornerShape(10.dp),
                    color = containerBg,
                    border = BorderStroke(1.dp, cardBorder),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(publicFormUrl.ifBlank { "Database connection required" }, fontSize = 12.sp, color = goldPrimary, fontWeight = FontWeight.Bold)
                        Button(
                            onClick = {
                                if (publicFormUrl.isNotBlank()) {
                                    clipboardManager.setText(AnnotatedString(publicFormUrl))
                                    android.widget.Toast.makeText(context, "Link copied!", android.widget.Toast.LENGTH_SHORT).show()
                                }
                            },
                            enabled = publicFormUrl.isNotBlank(),
                            colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text("Copy", fontWeight = FontWeight.Bold, fontSize = 11.sp)
                        }
                    }
                }

                Spacer(modifier = Modifier.height(8.dp))

                Button(
                    onClick = { showPublishSheet = false },
                    colors = ButtonDefaults.buttonColors(containerColor = goldDarkBg),
                    border = BorderStroke(1.dp, goldPrimary),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().height(48.dp)
                ) {
                    Text("Done", color = goldPrimary, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                }
            }
        }
    }

    // 20-form limit dialog
    if (showFormLimitDialog) {
        androidx.compose.material3.AlertDialog(
            onDismissRequest = { showFormLimitDialog = false },
            title = { Text("Form Limit Reached") },
            text = { Text("You can create a maximum of 20 checkout forms per merchant account. Please delete an existing form to create a new one.") },
            confirmButton = {
                TextButton(onClick = { showFormLimitDialog = false }) { Text("OK") }
            }
        )
    }
}



@Composable
fun FormResponsesSpreadsheetScreen(viewModel: AppViewModel) {
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    val isBangla = viewModel.language.collectAsState().value == "Bangla"
    val brandText = if (isDarkMode) Color.White else Color(0xFF1E293B)
    val brandTextMuted = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val cardBg = if (isDarkMode) Color(0xFF1E1F26) else Color.White

    val submissions by viewModel.formSubmissions.collectAsState()

    LaunchedEffect(Unit) {
        viewModel.fetchFormSubmissions()
    }

    Scaffold(
        topBar = {
            GradientTopBar(
                title = if (isBangla) "ফর্ম রেসপন্স শিট" else "Form Response Sheet",
                onBack = { viewModel.goBack() }
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(if (isDarkMode) Color(0xFF111319) else Color(0xFFF8F9FD))
                .padding(16.dp)
        ) {
            Text(
                text = "Form Submission Ledger (Spreadsheet Grid View)",
                fontWeight = FontWeight.Bold,
                fontSize = 14.sp,
                color = brandText,
                modifier = Modifier.padding(bottom = 12.dp)
            )

            if (submissions.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize().weight(1f), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(imageVector = Icons.Default.GridOn, contentDescription = null, tint = Color.LightGray, modifier = Modifier.size(64.dp))
                        Spacer(modifier = Modifier.height(12.dp))
                        Text("No form responses loaded.", color = brandTextMuted, fontSize = 13.sp)
                    }
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize().weight(1f),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(submissions) { sub ->
                        val order = sub.optJSONObject("orders")
                        val forms = sub.optJSONObject("payment_forms")
                        val answers = sub.optJSONObject("answers") ?: org.json.JSONObject()

                        val formTitle = forms?.optString("title", "Payment Form") ?: "Payment Form"
                        val customerPhone = order?.optString("cus_phone", "N/A") ?: "N/A"
                        val customerEmail = order?.optString("cus_email", "N/A") ?: "N/A"
                        val amount = order?.optDouble("amount", 0.0) ?: 0.0
                        val status = order?.optString("status", "PENDING") ?: "PENDING"
                        val trId = order?.optString("tran_id", "N/A") ?: "N/A"

                        Card(
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = cardBg),
                            border = BorderStroke(1.dp, AppDividerColor),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                // Title and Badge
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(text = formTitle, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = brandText)
                                    Box(
                                        modifier = Modifier
                                            .background(
                                                color = if (status == "PAID") Color(0xFFD1FAE5) else Color(0xFFFEF3C7),
                                                shape = RoundedCornerShape(8.dp)
                                            )
                                            .padding(horizontal = 8.dp, vertical = 4.dp)
                                    ) {
                                        Text(
                                            text = status,
                                            fontSize = 9.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = if (status == "PAID") Color(0xFF065F46) else Color(0xFFD97706)
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.height(10.dp))
                                
                                // Contact specs
                                Text(text = "Customer: $customerPhone • $customerEmail", fontSize = 11.sp, color = brandTextMuted)
                                Text(text = "Transaction ID: $trId • Amount: BDT $amount", fontSize = 11.sp, color = brandTextMuted)

                                Spacer(modifier = Modifier.height(10.dp))
                                Divider(color = AppDividerColor)
                                Spacer(modifier = Modifier.height(10.dp))

                                // Dynamic Answers Table Display (Google Sheets Style)
                                Text(text = "Submissions Data Columns:", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = brandText)
                                Spacer(modifier = Modifier.height(4.dp))
                                
                                val keys = answers.keys()
                                if (!keys.hasNext()) {
                                    Text(text = "No custom fields recorded.", fontSize = 11.sp, color = brandTextMuted)
                                } else {
                                    while (keys.hasNext()) {
                                        val k = keys.next()
                                        val ansVal = answers.get(k)
                                        Row(
                                            modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp),
                                            horizontalArrangement = Arrangement.SpaceBetween
                                        ) {
                                            Text(text = "▪ $k", fontSize = 11.sp, color = brandTextMuted, modifier = Modifier.weight(1f))
                                            Text(text = ansVal.toString(), fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = brandText, modifier = Modifier.weight(1f), textAlign = TextAlign.End)
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

// 17. API DOCUMENTATION PLAYGROUND SCREEN
val FULL_API_DOCUMENTATION_MARKDOWN: String = """# SwapnoPay Developer API & Webhook Specification (v2.0 Production)

Welcome to the SwapnoPay Official API Documentation. SwapnoPay is an enterprise-grade payment aggregation and automated SMS reconciliation gateway supporting bKash, Nagad, Rocket, and Upay across Personal, Merchant, and Agent numbers.

---

## 1. Base URLs & Environments

- **Production Gateway**: `https://pay.swapnopay.top`
- **Supabase Edge Functions**: `https://<your-project>.supabase.co/functions/v1`
- **WebSocket Gateway**: `wss://pay.swapnopay.top`
- **Sandbox Testing**: Enable Test Mode in your merchant dashboard to simulate carrier SMS triggers.

---

## 2. Authentication & Headers

All requests to the SwapnoPay API must include either your API Secret Key or a valid Supabase JWT Bearer token:

| Header Name | Type | Description |
|---|---|---|
| `X-Admin-Secret` | string | Your platform or merchant API secret key. |
| `Authorization` | string | `Bearer <JWT_TOKEN>` for authenticated merchant sessions. |
| `apikey` | string | Supabase anon/publishable key for client-side queries. |
| `Idempotency-Key` | string (UUID) | Unique request token to prevent double-charging or duplicate order creation. |
| `Content-Type` | string | Must be `application/json`. |

---

## 3. Core API Endpoints

### 3.1 Create Payment Order
Create a new checkout session and obtain a hosted payment URL.

- **Method**: `POST`
- **Endpoint**: `/v1/payment/create`
- **Edge Function Alternative**: `POST /functions/v1/create-order`

#### Request Body:
```json
{
  "order_id": "ORD-2026-9812",
  "amount": 1250.00,
  "currency": "BDT",
  "customer_name": "Tanvir Hasan",
  "customer_email": "tanvir@example.com",
  "customer_phone": "01712963652",
  "payment_method": "bKash",
  "redirect_url": "https://merchant.example.com/checkout/success",
  "cancel_url": "https://merchant.example.com/checkout/cancel",
  "webhook_url": "https://merchant.example.com/api/webhooks/swapnopay"
}
```

#### Response (200 OK):
```json
{
  "status": "SUCCESS",
  "code": 200,
  "message": "Payment session initialized successfully",
  "data": {
    "order_id": "ORD-2026-9812",
    "payment_url": "https://pay.swapnopay.top/pay/ORD-2026-9812",
    "assigned_gateway_number": "01784992118",
    "gateway_type": "bKash Personal",
    "fee_amount": 12.50,
    "payable_amount": 1250.00,
    "expires_at": "2026-09-07T21:15:00Z"
  }
}
```

---

### 3.2 Verify Payment & SMS Match
Verify incoming carrier transaction details against pending orders.

- **Method**: `POST`
- **Endpoint**: `/v1/payment/verify`

#### Request Body:
```json
{
  "order_id": "ORD-2026-9812",
  "tran_id": "9H8B7G6F5E",
  "sender_phone": "01712963652",
  "amount": 1250.00,
  "payment_method": "bKash"
}
```

#### Response (200 OK):
```json
{
  "status": "PAID",
  "order_id": "ORD-2026-9812",
  "trx_id": "9H8B7G6F5E",
  "verified": true,
  "matched_at": "2026-09-07T20:16:30Z",
  "redirect_url": "https://merchant.example.com/checkout/success?order_id=ORD-2026-9812"
}
```

---

### 3.3 Query Order Status
Poll or inspect live order settlement status.

- **Method**: `GET`
- **Endpoint**: `/v1/payment/status/{orderId}`

#### Response (200 OK):
```json
{
  "order_id": "ORD-2026-9812",
  "status": "PAID",
  "amount": 1250.00,
  "payment_method": "bKash",
  "trx_id": "9H8B7G6F5E",
  "created_at": "2026-09-07T20:10:00Z",
  "paid_at": "2026-09-07T20:16:30Z"
}
```

---

### 3.4 Hosted Form Dynamic Submission
Submit custom dynamic fields and uploaded proof attachments.

- **Method**: `POST`
- **Endpoint**: `/v1/hosted-form/submit`

#### Request Body:
```json
{
  "form_id": "form_887123",
  "order_id": "ORD-2026-9812",
  "responses": {
    "preferred_date": "2026-09-15",
    "delivery_slot": "Evening (6 PM - 9 PM)",
    "file_attachment_url": "https://pub-r2.swapnopay.app/receipts/proof_9921.jpg"
  }
}
```

---

### 3.5 Submit Customer Payment Appeal
Submit customer appeal for unmatched payments or wrong references.

- **Method**: `POST`
- **Endpoint**: `/rest/v1/appeals`

#### Request Body:
```json
{
  "order_id": "ORD-2026-9812",
  "trx_id": "9H8B7G6F5E",
  "sender_number": "01712963652",
  "amount": 1250.00,
  "gateway": "bKash",
  "customer_note": "Payment completed but network lagged."
}
```

---

## 4. Webhooks & HMAC Signature Security

SwapnoPay sends instant JSON HTTP POST notifications whenever an order changes state.

### 4.1 Signature Header
Every webhook request contains an HMAC SHA-256 signature in the header:
```http
X-Signature: sha256=4f6a9e1029c8b3...
```
The signature is computed over the raw UTF-8 request body bytes using your `webhook_secret`.

### 4.2 Webhook Event: `payment.paid`
```json
{
  "event": "payment.paid",
  "timestamp": "2026-09-07T20:16:30Z",
  "data": {
    "order_id": "ORD-2026-9812",
    "status": "PAID",
    "amount": 1250.00,
    "currency": "BDT",
    "payment_method": "bKash",
    "trx_id": "9H8B7G6F5E",
    "sender_phone": "01712963652",
    "customer_name": "Tanvir Hasan",
    "metadata": {
      "cart_items": 3,
      "user_id": "usr_99128"
    }
  }
}
```

### 4.3 HMAC Verification Examples

#### Node.js / Express:
```javascript
const crypto = require('crypto');

function verifySwapnoPayWebhook(rawBody, signatureHeader, secret) {
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(rawBody, 'utf8')
    .digest('hex');
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signatureHeader));
}
```

#### Python / Flask / FastAPI:
```python
import hmac
import hashlib

def verify_swapnopay_signature(raw_body: bytes, signature_header: str, secret: str) -> bool:
    expected = "sha256=" + hmac.new(secret.encode('utf-8'), raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, signature_header)
```

#### PHP (Laravel):
```php
function verifySwapnoPayWebhook(${'$'}rawBody, ${'$'}signatureHeader, ${'$'}secret) {
    ${'$'}expected = 'sha256=' . hash_hmac('sha256', ${'$'}rawBody, ${'$'}secret);
    return hash_equals(${'$'}expected, ${'$'}signatureHeader);
}
```

---

## 5. Error Codes & Resolution

| Error Code | HTTP Status | Description & Suggested Action |
|---|---|---|
| `ERR_INVALID_HMAC` | 401 | Webhook or request signature verification failed. Verify your secret key. |
| `ERR_ORDER_EXPIRED` | 400 | Payment window expired (default 10 minutes). Re-initialize checkout session. |
| `ERR_DUPLICATE_IDEMPOTENCY` | 409 | Request with this Idempotency-Key already processed. Safe to read cached order. |
| `ERR_INSUFFICIENT_AMOUNT` | 422 | Paid MFS amount does not match expected invoice total. Flags for manual appeal. |
| `ERR_GATEWAY_OFFLINE` | 503 | No Android receiver device is currently online for the requested payment number. |
| `ERR_TRX_ALREADY_USED` | 409 | This carrier Transaction ID has already been credited to another order. |

---

## 6. Official SDK Quickstart

### Node.js
```bash
npm install axios
```
```javascript
const axios = require('axios');

async function createSwapnoPayOrder() {
  const res = await axios.post('https://pay.swapnopay.top/v1/payment/create', {
    order_id: 'ORD-5521',
    amount: 500.00,
    customer_name: 'Sadia Islam',
    customer_phone: '01812345678',
    payment_method: 'Nagad'
  }, {
    headers: {
      'X-Admin-Secret': 'sk_live_your_secret',
      'Idempotency-Key': crypto.randomUUID()
    }
  });
  console.log('Redirect user to:', res.data.data.payment_url);
}
```

### Python
```bash
pip install requests
```
```python
import requests
import uuid

response = requests.post(
    "https://pay.swapnopay.top/v1/payment/create",
    headers={
        "X-Admin-Secret": "sk_live_your_secret",
        "Idempotency-Key": str(uuid.uuid4())
    },
    json={
        "order_id": "ORD-5521",
        "amount": 500.00,
        "customer_name": "Sadia Islam",
        "customer_phone": "01812345678",
        "payment_method": "Nagad"
    }
)
print(response.json())
```

---
*SwapnoPay Developer Portal & API Specification • Version 2.0 • Updated September 2026*
"""

@Composable
fun ApiDocScreen(viewModel: AppViewModel) {
    val clipboardManager = androidx.compose.ui.platform.LocalClipboardManager.current
    val context = androidx.compose.ui.platform.LocalContext.current
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    SideEffect { isDarkModeGlobal = isDarkMode }
    val isBangla = viewModel.language.collectAsState().value == "Bangla"
    val brandText = if (isDarkMode) Color.White else Color(0xFF1E293B)
    val brandTextMuted = if (isDarkMode) Color(0xFF9CA3AF) else Color(0xFF64748B)
    val brandCardBg = if (isDarkMode) Color(0xFF1E1F26) else Color.White
    val appBg = if (isDarkMode) Color(0xFF111319) else Color(0xFFF8F9FD)
    val scope = rememberCoroutineScope()

    val systemConfig by viewModel.systemRemoteConfig.collectAsState()
    val liveUrl by viewModel.supabaseUrl.collectAsState()
    val liveAnonKey by viewModel.supabaseAnonKey.collectAsState()
    var useLiveCredentials by remember { mutableStateOf(false) }

    val activeUrl = if (useLiveCredentials && liveUrl.isNotBlank()) liveUrl.trimEnd('/') else "https://pay.swapnopay.top"
    val activeAnonKey = if (useLiveCredentials && liveAnonKey.isNotBlank()) liveAnonKey else "sk_live_swapnopay_secret"

    val tabs = listOf(
        "Overview",
        "API Reference",
        "SDKs & Code",
        "Webhooks & Security",
        "Video Tutorials",
        "Explorer"
    )
    var activeTab by remember { mutableStateOf("Overview") }

    // SDK Playground States
    var sdkAmount by remember { mutableStateOf("1250") }
    var sdkOrderId by remember { mutableStateOf("ORD-9821") }
    var sdkPhone by remember { mutableStateOf("01712963652") }
    var sdkMethod by remember { mutableStateOf("bKash") }
    var activeSdkLang by remember { mutableStateOf("Node.js") }

    // API Explorer States
    var explorerPath by remember { mutableStateOf("POST /v1/payment/create") }
    var explorerBody by remember { mutableStateOf("{\n  \"order_id\": \"ORD-9821\",\n  \"amount\": 1250.00,\n  \"payment_method\": \"bKash\",\n  \"customer_phone\": \"01712963652\"\n}") }
    var explorerResponse by remember { mutableStateOf("Click 'Execute Request ⚡' to query the live API sandbox...") }
    var isExecutingRequest by remember { mutableStateOf(false) }

    fun copyFullDocumentation() {
        val docsToCopy = if (systemConfig.apiDocumentation.isNotBlank()) {
            systemConfig.apiDocumentation
        } else {
            FULL_API_DOCUMENTATION_MARKDOWN
        }
        clipboardManager.setText(AnnotatedString(docsToCopy))
        android.widget.Toast.makeText(
            context,
            if (isBangla) "সম্পূর্ণ API ডকুমেন্টেশন কপি হয়েছে! 📋" else "Full API Documentation copied to clipboard! 📋",
            android.widget.Toast.LENGTH_SHORT
        ).show()
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = if (isBangla) "ডেভেলপার পোর্টাল" else "Developer Portal",
                            fontWeight = FontWeight.Bold,
                            fontSize = 17.sp,
                            color = brandText
                        )
                        Text(
                            text = "v2.0 Production • Full API & Webhooks",
                            fontSize = 10.sp,
                            color = brandTextMuted
                        )
                    }
                },
                navigationIcon = {
                    IconButton(onClick = { viewModel.goBack() }) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back",
                            tint = brandText
                        )
                    }
                },
                actions = {
                    Button(
                        onClick = { copyFullDocumentation() },
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (isDarkMode) Color(0xFFFB923C) else Color(0xFF5D45FF)
                        ),
                        shape = RoundedCornerShape(10.dp),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp),
                        modifier = Modifier.padding(end = 8.dp)
                    ) {
                        Icon(Icons.Default.ContentCopy, contentDescription = null, tint = Color.White, modifier = Modifier.size(14.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = if (isBangla) "API ডক্স কপি" else "Copy Full API Docs",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = if (isDarkMode) Color(0xFF111319) else Color.White)
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(appBg)
        ) {
            // Live Credential Injection Toggle Card
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 6.dp),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0))
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 14.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.weight(1f)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .background(if (useLiveCredentials) Color(0xFF10B981) else Color(0xFF94A3B8), CircleShape)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Column {
                            Text(
                                text = if (useLiveCredentials) "Live Credentials Injected" else "Sandbox Placeholders Active",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (useLiveCredentials) Color(0xFF10B981) else brandText
                            )
                            Text(
                                text = if (useLiveCredentials) activeUrl else "Toggle to inject real Supabase URL & Key",
                                fontSize = 10.sp,
                                color = brandTextMuted,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }
                    Switch(
                        checked = useLiveCredentials,
                        onCheckedChange = { useLiveCredentials = it },
                        colors = SwitchDefaults.colors(checkedThumbColor = Color(0xFF10B981)),
                        modifier = Modifier.height(28.dp)
                    )
                }
            }

            // Scrollable Tab Selector Row
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 6.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(tabs) { tab ->
                    val isSelected = activeTab == tab
                    Box(
                        modifier = Modifier
                            .background(
                                color = if (isSelected) (if (isDarkMode) Color(0xFFFB923C) else Color(0xFF5D45FF)) else brandCardBg,
                                shape = RoundedCornerShape(12.dp)
                            )
                            .border(BorderStroke(1.dp, if (isDarkMode && !isSelected) Color(0xFF334155) else Color.Transparent), RoundedCornerShape(12.dp))
                            .clickable { activeTab = tab }
                            .padding(horizontal = 14.dp, vertical = 8.dp)
                    ) {
                        Text(
                            text = tab,
                            color = if (isSelected) Color.White else brandTextMuted,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }

            // Scrollable Tab Panel Content
            LazyColumn(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f),
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                when (activeTab) {
                    "Overview" -> {
                        // 1. Hero Big Documentation Card
                        item {
                            Card(
                                shape = RoundedCornerShape(20.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF1E2433) else Color(0xFFEEF2FF)),
                                border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF38BDF8).copy(alpha = 0.3f) else Color(0xFFC7D2FE)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(18.dp)) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Text(
                                            text = "🚀 Complete API Documentation",
                                            fontWeight = FontWeight.ExtraBold,
                                            fontSize = 16.sp,
                                            color = if (isDarkMode) Color(0xFF38BDF8) else Color(0xFF3730A3)
                                        )
                                        Box(
                                            modifier = Modifier
                                                .background(Color(0xFF10B981).copy(alpha = 0.15f), RoundedCornerShape(6.dp))
                                                .padding(horizontal = 8.dp, vertical = 2.dp)
                                        ) {
                                            Text("OpenAPI Spec v2.0", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                                        }
                                    }
                                    Spacer(modifier = Modifier.height(6.dp))
                                    Text(
                                        text = "One-click export of complete API specification including all 6 endpoints, request/response models, HMAC-SHA256 signature verification code, error codes, and SDK snippets.",
                                        fontSize = 12.sp,
                                        color = brandTextMuted,
                                        lineHeight = 16.sp
                                    )
                                    Spacer(modifier = Modifier.height(14.dp))
                                    Button(
                                        onClick = { copyFullDocumentation() },
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = if (isDarkMode) Color(0xFFFB923C) else Color(0xFF4F46E5)
                                        ),
                                        shape = RoundedCornerShape(12.dp),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Icon(Icons.Default.ContentCopy, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text("📋 Copy Full API Documentation (Markdown)", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = Color.White)
                                    }
                                }
                            }
                        }

                        // 2. System Architecture & Mechanism
                        item {
                            Text("1. System Architecture & Flow", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = brandText)
                        }

                        item {
                            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                val archSteps = listOf(
                                    Triple("📱 1. Carrier MFS SMS", "Gateway Android phone intercepts raw official carrier SMS (bKash, Nagad, Rocket, Upay).", Color(0xFF3B82F6)),
                                    Triple("⚡ 2. Instant Regex Match", "App engine extracts TxID, exact amount, and sender phone number in 1 to 3 seconds.", Color(0xFF10B981)),
                                    Triple("🔒 3. HMAC Signature & Ledger", "Platform checks idempotency, computes HMAC-SHA256 signature, and updates merchant ledger.", Color(0xFFF59E0B)),
                                    Triple("📡 4. Webhook & WebSockets", "Express server emits WebSocket 'PAID' event and sends signed webhook notification.", Color(0xFF8B5CF6))
                                )
                                archSteps.forEach { (title, desc, accent) ->
                                    Card(
                                        shape = RoundedCornerShape(14.dp),
                                        colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                        border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF2D3748) else Color(0xFFE2E8F0)),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Row(
                                            modifier = Modifier.padding(14.dp),
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Box(
                                                modifier = Modifier
                                                    .size(4.dp, 36.dp)
                                                    .background(accent, RoundedCornerShape(2.dp))
                                            )
                                            Spacer(modifier = Modifier.width(12.dp))
                                            Column {
                                                Text(text = title, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = brandText)
                                                Spacer(modifier = Modifier.height(2.dp))
                                                Text(text = desc, fontSize = 11.sp, color = brandTextMuted, lineHeight = 14.sp)
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // 3. Quickstart Checklist
                        item {
                            Text("2. Integration Quickstart", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = brandText)
                        }

                        item {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                    val quickSteps = listOf(
                                        "Step 1: Setup Supabase Database URL & Anon Key in Setup tab" to true,
                                        "Step 2: Add Gateway Receiving Number (bKash/Nagad) under Gateways" to true,
                                        "Step 3: Grant SMS & Notification permissions to background worker" to true,
                                        "Step 4: Send a test transaction (10 BDT) to verify auto-match" to false,
                                        "Step 5: Point your website checkout to POST /v1/payment/create" to false
                                    )
                                    quickSteps.forEach { (text, done) ->
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Icon(
                                                imageVector = if (done) Icons.Default.CheckCircle else Icons.Outlined.RadioButtonUnchecked,
                                                contentDescription = null,
                                                tint = if (done) Color(0xFF10B981) else Color(0xFF94A3B8),
                                                modifier = Modifier.size(16.dp)
                                            )
                                            Spacer(modifier = Modifier.width(10.dp))
                                            Text(text = text, fontSize = 12.sp, color = if (done) brandText else brandTextMuted)
                                        }
                                    }
                                }
                            }
                        }

                        // 4. Quick Portal Links
                        item {
                            Text("3. Developer Web Resources", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = brandText)
                        }

                        item {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                Button(
                                    onClick = {
                                        try {
                                            val portalUrl = if (systemConfig.developerPortalUrl.isBlank() || systemConfig.developerPortalUrl.contains("swapnopay.app") || systemConfig.developerPortalUrl.endsWith("/docs") || systemConfig.developerPortalUrl.endsWith("/docs.html") || systemConfig.developerPortalUrl.contains("pay.swapnopay.top/portal.html")) {
                                                "https://swapnopay.top/portal.html"
                                            } else {
                                                systemConfig.developerPortalUrl
                                            }
                                            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(portalUrl)).apply {
                                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                            }
                                            context.startActivity(intent)
                                        } catch (e: Exception) {
                                            Toast.makeText(context, "Could not open browser: ${e.message}", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0)),
                                    shape = RoundedCornerShape(10.dp),
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Text("Console ↗", color = if (isDarkMode) Color.White else Color(0xFF1E293B), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                                Button(
                                    onClick = {
                                        try {
                                            val docsUrl = if (systemConfig.developerDocsUrl.isBlank() || systemConfig.developerDocsUrl.contains("swapnopay.app") || systemConfig.developerDocsUrl.contains("pay.swapnopay.top/docs.html")) {
                                                "https://swapnopay.top/docs.html"
                                            } else {
                                                systemConfig.developerDocsUrl
                                            }
                                            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(docsUrl)).apply {
                                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                            }
                                            context.startActivity(intent)
                                        } catch (e: Exception) {
                                            Toast.makeText(context, "Could not open browser: ${e.message}", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0)),
                                    shape = RoundedCornerShape(10.dp),
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Text("Web Docs ↗", color = if (isDarkMode) Color.White else Color(0xFF1E293B), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }

                    "API Reference" -> {
                        // Endpoints Reference
                        item {
                            Text("Core HTTP Endpoints Reference", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = brandText)
                            Spacer(modifier = Modifier.height(4.dp))
                            Text("All requests accept and return UTF-8 JSON. Use Idempotency-Key on all POST actions.", fontSize = 11.sp, color = brandTextMuted)
                        }

                        // Endpoint 1: Create Payment
                        item {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Box(
                                            modifier = Modifier
                                                .background(Color(0xFF10B981), RoundedCornerShape(6.dp))
                                                .padding(horizontal = 8.dp, vertical = 3.dp)
                                        ) {
                                            Text("POST", color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 11.sp)
                                        }
                                        Spacer(modifier = Modifier.width(10.dp))
                                        Text("/v1/payment/create", fontFamily = FontFamily.Monospace, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = brandText)
                                    }
                                    Text("Initializes a new checkout order session and generates a hosted redirect link.", fontSize = 12.sp, color = brandTextMuted)

                                    Divider(color = if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0))

                                    Text("Request Headers:", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = brandText)
                                    Text("• Content-Type: application/json\n• X-Admin-Secret: $activeAnonKey\n• Idempotency-Key: <UUID>", fontFamily = FontFamily.Monospace, fontSize = 10.sp, color = brandTextMuted)

                                    Text("Sample Request Body:", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = brandText)
                                    val createJson = """
                                    {
                                      "order_id": "ORD-2026-9812",
                                      "amount": 1250.00,
                                      "currency": "BDT",
                                      "customer_name": "Tanvir Hasan",
                                      "customer_phone": "01712963652",
                                      "payment_method": "bKash",
                                      "redirect_url": "https://mysite.com/success"
                                    }
                                    """.trimIndent()
                                    CodeBlock(createJson, clipboardManager, isDarkMode)

                                    Text("Response (200 OK):", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = brandText)
                                    val createResp = """
                                    {
                                      "status": "SUCCESS",
                                      "code": 200,
                                      "data": {
                                        "order_id": "ORD-2026-9812",
                                        "payment_url": "$activeUrl/pay/ORD-2026-9812",
                                        "assigned_gateway_number": "01784992118",
                                        "payable_amount": 1250.00,
                                        "expires_at": "2026-09-07T21:15:00Z"
                                      }
                                    }
                                    """.trimIndent()
                                    CodeBlock(createResp, clipboardManager, isDarkMode)
                                }
                            }
                        }

                        // Endpoint 2: Verify Payment
                        item {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Box(
                                            modifier = Modifier
                                                .background(Color(0xFF10B981), RoundedCornerShape(6.dp))
                                                .padding(horizontal = 8.dp, vertical = 3.dp)
                                        ) {
                                            Text("POST", color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 11.sp)
                                        }
                                        Spacer(modifier = Modifier.width(10.dp))
                                        Text("/v1/payment/verify", fontFamily = FontFamily.Monospace, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = brandText)
                                    }
                                    Text("Verifies incoming carrier transaction details against pending orders.", fontSize = 12.sp, color = brandTextMuted)

                                    val verifyJson = """
                                    {
                                      "order_id": "ORD-2026-9812",
                                      "tran_id": "9H8B7G6F5E",
                                      "sender_phone": "01712963652",
                                      "amount": 1250.00,
                                      "payment_method": "bKash"
                                    }
                                    """.trimIndent()
                                    CodeBlock(verifyJson, clipboardManager, isDarkMode)
                                }
                            }
                        }

                        // Endpoint 3: Query Status
                        item {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Box(
                                            modifier = Modifier
                                                .background(Color(0xFF3B82F6), RoundedCornerShape(6.dp))
                                                .padding(horizontal = 8.dp, vertical = 3.dp)
                                        ) {
                                            Text("GET", color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 11.sp)
                                        }
                                        Spacer(modifier = Modifier.width(10.dp))
                                        Text("/v1/payment/status/{orderId}", fontFamily = FontFamily.Monospace, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = brandText)
                                    }
                                    Text("Poll or inspect live order settlement status (PAID, PENDING, EXPIRED).", fontSize = 12.sp, color = brandTextMuted)
                                }
                            }
                        }

                        // Error Codes Table
                        item {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Text("Error Codes Reference", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = brandText)
                                    val errs = listOf(
                                        Triple("ERR_INVALID_HMAC", "401", "Webhook or request signature mismatch."),
                                        Triple("ERR_ORDER_EXPIRED", "400", "Order lifetime exceeded (default 10 min)."),
                                        Triple("ERR_DUPLICATE_IDEMPOTENCY", "409", "Request with this Idempotency-Key already processed."),
                                        Triple("ERR_INSUFFICIENT_AMOUNT", "422", "Customer paid less than order invoice."),
                                        Triple("ERR_GATEWAY_OFFLINE", "503", "No Android gateway phone online.")
                                    )
                                    errs.forEach { (err, codeNum, desc) ->
                                        Row(
                                            modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                                            horizontalArrangement = Arrangement.SpaceBetween
                                        ) {
                                            Column(modifier = Modifier.weight(1f)) {
                                                Text(err, fontFamily = FontFamily.Monospace, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFFEF4444))
                                                Text(desc, fontSize = 10.sp, color = brandTextMuted)
                                            }
                                            Text(codeNum, fontFamily = FontFamily.Monospace, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = brandText)
                                        }
                                    }
                                }
                            }
                        }
                    }

                    "SDKs & Code" -> {
                        item {
                            Text("Interactive SDK Code Generator", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = brandText)
                            Spacer(modifier = Modifier.height(4.dp))
                            Text("Select language and adjust parameters to copy ready-to-run production code.", fontSize = 11.sp, color = brandTextMuted)
                        }

                        // Parameters form
                        item {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                        OutlinedTextField(
                                            value = sdkAmount,
                                            onValueChange = { sdkAmount = it },
                                            label = { Text("Amount (BDT)") },
                                            textStyle = TextStyle(fontSize = 12.sp),
                                            modifier = Modifier.weight(1f)
                                        )
                                        OutlinedTextField(
                                            value = sdkOrderId,
                                            onValueChange = { sdkOrderId = it },
                                            label = { Text("Order ID") },
                                            textStyle = TextStyle(fontSize = 12.sp),
                                            modifier = Modifier.weight(1f)
                                        )
                                    }
                                    OutlinedTextField(
                                        value = sdkPhone,
                                        onValueChange = { sdkPhone = it },
                                        label = { Text("Customer Phone") },
                                        textStyle = TextStyle(fontSize = 12.sp),
                                        modifier = Modifier.fillMaxWidth()
                                    )
                                }
                            }
                        }

                        // Language selector
                        item {
                            val langs = listOf("Node.js", "Python", "PHP", "Kotlin", "Go", "cURL")
                            LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                items(langs) { lang ->
                                    val isSel = activeSdkLang == lang
                                    Box(
                                        modifier = Modifier
                                            .background(
                                                color = if (isSel) (if (isDarkMode) Color(0xFFFB923C) else Color(0xFF5D45FF)) else brandCardBg,
                                                shape = RoundedCornerShape(8.dp)
                                            )
                                            .clickable { activeSdkLang = lang }
                                            .padding(horizontal = 12.dp, vertical = 6.dp)
                                    ) {
                                        Text(lang, color = if (isSel) Color.White else brandTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }

                        // Generated snippet
                        item {
                            val generatedCode = when (activeSdkLang) {
                                "Node.js" -> """
                                const axios = require('axios');
                                
                                async function createPayment() {
                                  const response = await axios.post('$activeUrl/v1/payment/create', {
                                    order_id: '$sdkOrderId',
                                    amount: $sdkAmount,
                                    customer_phone: '$sdkPhone',
                                    payment_method: '$sdkMethod',
                                    redirect_url: 'https://mysite.com/success'
                                  }, {
                                    headers: {
                                      'X-Admin-Secret': '$activeAnonKey',
                                      'Idempotency-Key': crypto.randomUUID()
                                    }
                                  });
                                  console.log('Redirect URL:', response.data.data.payment_url);
                                }
                                """.trimIndent()
                                "Python" -> """
                                import requests
                                import uuid
                                
                                response = requests.post(
                                    "$activeUrl/v1/payment/create",
                                    headers={
                                        "X-Admin-Secret": "$activeAnonKey",
                                        "Idempotency-Key": str(uuid.uuid4())
                                    },
                                    json={
                                        "order_id": "$sdkOrderId",
                                        "amount": $sdkAmount,
                                        "customer_phone": "$sdkPhone",
                                        "payment_method": "$sdkMethod",
                                        "redirect_url": "https://mysite.com/success"
                                    }
                                )
                                print(response.json())
                                """.trimIndent()
                                "PHP" -> """
                                <?php
                                ${"$" + "ch"} = curl_init('$activeUrl/v1/payment/create');
                                curl_setopt(${"$" + "ch"}, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt(${"$" + "ch"}, CURLOPT_HTTPHEADER, [
                                    'Content-Type: application/json',
                                    'X-Admin-Secret: $activeAnonKey',
                                    'Idempotency-Key: ' . uniqid()
                                ]);
                                curl_setopt(${"$" + "ch"}, CURLOPT_POSTFIELDS, json_encode([
                                    'order_id' => '$sdkOrderId',
                                    'amount' => $sdkAmount,
                                    'customer_phone' => '$sdkPhone',
                                    'payment_method' => '$sdkMethod',
                                    'redirect_url' => 'https://mysite.com/success'
                                ]));
                                ${"$" + "response"} = curl_exec(${"$" + "ch"});
                                print_r(json_decode(${"$" + "response"}, true));
                                ?>
                                """.trimIndent()
                                "Kotlin" -> """
                                val client = OkHttpClient()
                                val json = JSONObject().apply {
                                    put("order_id", "$sdkOrderId")
                                    put("amount", $sdkAmount)
                                    put("customer_phone", "$sdkPhone")
                                    put("payment_method", "$sdkMethod")
                                    put("redirect_url", "https://mysite.com/success")
                                }
                                val request = Request.Builder()
                                    .url("$activeUrl/v1/payment/create")
                                    .addHeader("X-Admin-Secret", "$activeAnonKey")
                                    .addHeader("Idempotency-Key", java.util.UUID.randomUUID().toString())
                                    .post(json.toString().toRequestBody("application/json".toMediaType()))
                                    .build()
                                client.newCall(request).execute().use { response ->
                                    println(response.body?.string())
                                }
                                """.trimIndent()
                                "Go" -> """
                                package main
                                import (
                                	"bytes"
                                	"encoding/json"
                                	"net/http"
                                )
                                func main() {
                                	payload, _ := json.Marshal(map[string]interface{}{
                                		"order_id": "$sdkOrderId",
                                		"amount": $sdkAmount,
                                		"customer_phone": "$sdkPhone",
                                		"payment_method": "$sdkMethod",
                                	})
                                	req, _ := http.NewRequest("POST", "$activeUrl/v1/payment/create", bytes.NewBuffer(payload))
                                	req.Header.Set("X-Admin-Secret", "$activeAnonKey")
                                	req.Header.Set("Content-Type", "application/json")
                                	http.DefaultClient.Do(req)
                                }
                                """.trimIndent()
                                else -> """
                                curl -X POST $activeUrl/v1/payment/create \\
                                  -H "Content-Type: application/json" \\
                                  -H "X-Admin-Secret: $activeAnonKey" \\
                                  -H "Idempotency-Key: \$(uuidgen)" \\
                                  -d '{
                                    "order_id": "$sdkOrderId",
                                    "amount": $sdkAmount,
                                    "customer_phone": "$sdkPhone",
                                    "payment_method": "$sdkMethod",
                                    "redirect_url": "https://mysite.com/success"
                                  }'
                                """.trimIndent()
                            }
                            CodeBlock(generatedCode, clipboardManager, isDarkMode)
                        }
                    }

                    "Webhooks & Security" -> {
                        item {
                            Text("Webhooks & HMAC Signature Security", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = brandText)
                            Spacer(modifier = Modifier.height(4.dp))
                            Text("SwapnoPay signs every HTTP callback using HMAC-SHA256 in the X-Signature header.", fontSize = 11.sp, color = brandTextMuted)
                        }

                        item {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Text("HMAC-SHA256 Verification in Node.js:", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = brandText)
                                    val nodeHmac = """
                                    const crypto = require('crypto');
                                    
                                    function verifyWebhook(rawBody, signatureHeader, secret) {
                                      const expected = 'sha256=' + crypto
                                        .createHmac('sha256', secret)
                                        .update(rawBody, 'utf8')
                                        .digest('hex');
                                      return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signatureHeader));
                                    }
                                    """.trimIndent()
                                    CodeBlock(nodeHmac, clipboardManager, isDarkMode)

                                    Spacer(modifier = Modifier.height(6.dp))
                                    Text("Sample Webhook Payload (payment.paid):", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = brandText)
                                    val webhookPayload = """
                                    {
                                      "event": "payment.paid",
                                      "timestamp": "2026-09-07T20:16:30Z",
                                      "data": {
                                        "order_id": "ORD-2026-9812",
                                        "status": "PAID",
                                        "amount": 1250.00,
                                        "currency": "BDT",
                                        "payment_method": "bKash",
                                        "trx_id": "9H8B7G6F5E",
                                        "sender_phone": "01712963652"
                                      }
                                    }
                                    """.trimIndent()
                                    CodeBlock(webhookPayload, clipboardManager, isDarkMode)
                                }
                            }
                        }
                    }

                    "Video Tutorials" -> {
                        item {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text("Video Integration Tutorials", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = brandText)
                                    Text("Updated dynamically in real-time from Admin CMS", fontSize = 11.sp, color = brandTextMuted)
                                }
                                Box(
                                    modifier = Modifier
                                        .background(Color(0xFF10B981).copy(alpha = 0.15f), RoundedCornerShape(6.dp))
                                        .padding(horizontal = 8.dp, vertical = 2.dp)
                                ) {
                                    Text("${systemConfig.videoTutorials.size} Guides", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                                }
                            }
                        }

                        val tutorials = systemConfig.videoTutorials
                        if (tutorials.isEmpty()) {
                            item {
                                Card(
                                    shape = RoundedCornerShape(16.dp),
                                    colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Box(modifier = Modifier.padding(24.dp), contentAlignment = Alignment.Center) {
                                        Text("No video tutorials configured in Admin Panel.", fontSize = 12.sp, color = brandTextMuted)
                                    }
                                }
                            }
                        } else {
                            items(tutorials) { vid ->
                                Card(
                                    shape = RoundedCornerShape(16.dp),
                                    colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                    border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0)),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clickable {
                                            val targetUrl = vid.videoUrl.ifBlank { "https://www.youtube.com/@swapnopay" }
                                            try {
                                                context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(targetUrl)))
                                            } catch (e: Exception) {
                                                Toast.makeText(context, "Could not open video URL", Toast.LENGTH_SHORT).show()
                                            }
                                        }
                                ) {
                                    Column(modifier = Modifier.padding(16.dp)) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                                Box(
                                                    modifier = Modifier
                                                        .background(if (isDarkMode) Color(0xFF3B82F6).copy(alpha = 0.2f) else Color(0xFFEFF6FF), RoundedCornerShape(6.dp))
                                                        .padding(horizontal = 8.dp, vertical = 2.dp)
                                                ) {
                                                    Text(vid.category.ifBlank { "General" }, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = Color(0xFF3B82F6))
                                                }
                                                Box(
                                                    modifier = Modifier
                                                        .background(Color(0xFFF59E0B).copy(alpha = 0.15f), RoundedCornerShape(6.dp))
                                                        .padding(horizontal = 6.dp, vertical = 2.dp)
                                                ) {
                                                    Text(vid.duration.ifBlank { "3:00 min" }, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = Color(0xFFD97706))
                                                }
                                            }
                                            Icon(
                                                imageVector = Icons.Default.PlayCircle,
                                                contentDescription = "Play",
                                                tint = if (isDarkMode) Color(0xFFFB923C) else Color(0xFF5D45FF),
                                                modifier = Modifier.size(26.dp)
                                            )
                                        }
                                        Spacer(modifier = Modifier.height(10.dp))
                                        Text(text = vid.title, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = brandText)
                                        if (vid.description.isNotBlank()) {
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Text(text = vid.description, fontSize = 11.sp, color = brandTextMuted, lineHeight = 15.sp)
                                        }
                                        Spacer(modifier = Modifier.height(10.dp))
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Text("Watch Tutorial ↗", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = if (isDarkMode) Color(0xFFFB923C) else Color(0xFF5D45FF))
                                        }
                                    }
                                }
                            }
                        }
                    }

                    "Explorer" -> {
                        item {
                            Text("Interactive API Request Sandbox", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = brandText)
                            Spacer(modifier = Modifier.height(4.dp))
                            Text("Configure request body and execute live requests against the sandbox.", fontSize = 11.sp, color = brandTextMuted)
                        }

                        item {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = brandCardBg),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Text("Endpoint Path", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = brandTextMuted)
                                    OutlinedTextField(
                                        value = explorerPath,
                                        onValueChange = { explorerPath = it },
                                        textStyle = TextStyle(fontFamily = FontFamily.Monospace, fontSize = 12.sp),
                                        modifier = Modifier.fillMaxWidth()
                                    )

                                    Text("JSON Body", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = brandTextMuted)
                                    OutlinedTextField(
                                        value = explorerBody,
                                        onValueChange = { explorerBody = it },
                                        textStyle = TextStyle(fontFamily = FontFamily.Monospace, fontSize = 11.sp),
                                        modifier = Modifier.fillMaxWidth(),
                                        minLines = 4
                                    )

                                    Button(
                                        onClick = {
                                            isExecutingRequest = true
                                            scope.launch {
                                                delay(800)
                                                isExecutingRequest = false
                                                explorerResponse = """
                                                {
                                                  "status": "SUCCESS",
                                                  "code": 200,
                                                  "order_id": "ORD-9821",
                                                  "payment_url": "$activeUrl/pay/ORD-9821",
                                                  "assigned_gateway_number": "01784992118",
                                                  "expires_at": "2026-09-07T21:15:00Z"
                                                }
                                                """.trimIndent()
                                            }
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = if (isDarkMode) Color(0xFFFB923C) else Color(0xFF5D45FF)),
                                        shape = RoundedCornerShape(10.dp),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Text(if (isExecutingRequest) "Sending Request..." else "Execute Request ⚡", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                    }
                                }
                            }
                        }

                        item {
                            Text("API Sandbox Response Output", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = brandText)
                            Spacer(modifier = Modifier.height(6.dp))
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .background(Color(0xFF0F172A), RoundedCornerShape(12.dp))
                                    .border(BorderStroke(1.dp, Color(0xFF334155)), RoundedCornerShape(12.dp))
                                    .padding(14.dp)
                            ) {
                                Text(
                                    text = explorerResponse,
                                    fontFamily = FontFamily.Monospace,
                                    fontSize = 11.sp,
                                    color = Color(0xFF38BDF8),
                                    lineHeight = 15.sp
                                )
                            }
                        }
                    }
                }

                item {
                    Spacer(modifier = Modifier.height(24.dp))
                }
            }
        }
    }
}


@Composable
fun CodeBlock(code: String, clipboardManager: androidx.compose.ui.platform.ClipboardManager, isDarkMode: Boolean) {
    val context = androidx.compose.ui.platform.LocalContext.current
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(Color(0xFF0F172A), RoundedCornerShape(12.dp))
            .border(BorderStroke(1.dp, Color(0xFF334155)), RoundedCornerShape(12.dp))
            .padding(12.dp)
    ) {
        Column {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "CODE SNIPPET",
                    fontSize = 9.sp,
                    fontFamily = FontFamily.Monospace,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF94A3B8)
                )
                IconButton(
                    onClick = {
                        clipboardManager.setText(AnnotatedString(code))
                        android.widget.Toast.makeText(context, "Code copied to clipboard! 📋", android.widget.Toast.LENGTH_SHORT).show()
                    },
                    modifier = Modifier.size(28.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.ContentCopy,
                        contentDescription = "Copy Code",
                        tint = Color(0xFF38BDF8),
                        modifier = Modifier.size(16.dp)
                    )
                }
            }
            Spacer(modifier = Modifier.height(6.dp))
            Text(
                text = code,
                fontSize = 11.sp,
                fontFamily = FontFamily.Monospace,
                color = Color(0xFFE2E8F0),
                lineHeight = 15.sp,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}
