package com.example.ui

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

// Pixel-perfect color palette sampled directly from media_1790094284953.png
private val BrandGoldYellow = Color(0xFFFBC740)
private val BrandGoldAmber = Color(0xFFF59E0B)
private val SupportBubbleBgLight = Color(0xFFF1F5FB)
private val SupportBubbleBgDark = Color(0xFF1E2430)
private val TextDarkPrimary = Color(0xFF1E293B)
private val TextMutedSecondary = Color(0xFF64748B)
private val TimestampMuted = Color(0xFF94A3B8)
private val OnlineGreen = Color(0xFF22C55E)
private val IconDarkColor = Color(0xFF1E293B)
private val IconMutedColor = Color(0xFF64748B)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SupportChatScreen(viewModel: AppViewModel) {
    val context = LocalContext.current
    val clipboardManager = LocalClipboardManager.current
    val scope = rememberCoroutineScope()

    val isDark by viewModel.isDarkMode.collectAsState()
    val chatList by viewModel.supportChatList.collectAsState()
    val remoteConfig by viewModel.systemRemoteConfig.collectAsState()
    val activeProfile by viewModel.activeProfile.collectAsState()

    var chatInput by remember { mutableStateOf("") }
    var isSearchActive by remember { mutableStateOf(false) }
    var searchQuery by remember { mutableStateOf("") }
    var showMenu by remember { mutableStateOf(false) }
    var showFaqModal by remember { mutableStateOf(false) }
    var showAttachmentModal by remember { mutableStateOf(false) }

    val listState = rememberLazyListState()

    // Real-time live chat polling: continuously syncs with Admin Helpdesk
    LaunchedEffect(Unit) {
        viewModel.listenToSupportChatFromPlatformOwner()
    }

    // Scroll to bottom when message arrives
    LaunchedEffect(chatList.size) {
        if (chatList.isNotEmpty()) {
            listState.animateScrollToItem(chatList.size - 1)
        }
    }

    val filteredMessages = remember(chatList, searchQuery) {
        if (searchQuery.isBlank()) {
            chatList
        } else {
            val q = searchQuery.trim().lowercase(Locale.getDefault())
            chatList.filter { it.message.lowercase(Locale.getDefault()).contains(q) }
        }
    }

    val bgColor = if (isDark) Color(0xFF0F1117) else Color.White
    val topBarBg = if (isDark) Color(0xFF141720) else Color.White

    Scaffold(
        containerColor = bgColor,
        topBar = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(topBarBg)
            ) {
                // Top App Bar matching pixel-perfect specs
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .statusBarsPadding()
                        .height(68.dp)
                        .padding(horizontal = 12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Back Button
                    IconButton(
                        onClick = { viewModel.goBack() },
                        modifier = Modifier.size(40.dp)
                    ) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back",
                            tint = if (isDark) Color.White else IconDarkColor,
                            modifier = Modifier.size(24.dp)
                        )
                    }

                    Spacer(modifier = Modifier.width(4.dp))

                    // SwapnoPay Avatar: Yellow circle with bold dark "S"
                    Box(
                        modifier = Modifier
                            .size(44.dp)
                            .clip(CircleShape)
                            .background(BrandGoldYellow),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "S",
                            fontSize = 21.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = IconDarkColor
                        )
                    }

                    Spacer(modifier = Modifier.width(12.dp))

                    // Title, Subtitle, and Online Status (3 lines matching image)
                    Column(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.Center
                    ) {
                        Text(
                            text = "SwapnoPay",
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (isDark) Color.White else TextDarkPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            text = "Support Chat",
                            fontSize = 12.sp,
                            color = if (isDark) Color(0xFF94A3B8) else TextMutedSecondary
                        )
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Text(
                                text = "●",
                                fontSize = 7.sp,
                                color = OnlineGreen
                            )
                            Text(
                                text = "Online",
                                fontSize = 11.5.sp,
                                fontWeight = FontWeight.Medium,
                                color = OnlineGreen
                            )
                        }
                    }

                    // Search Button
                    IconButton(
                        onClick = {
                            isSearchActive = !isSearchActive
                            if (!isSearchActive) searchQuery = ""
                        },
                        modifier = Modifier.size(40.dp)
                    ) {
                        Icon(
                            imageVector = if (isSearchActive) Icons.Default.Close else Icons.Default.Search,
                            contentDescription = "Search messages",
                            tint = if (isDark) Color(0xFFCBD5E1) else IconDarkColor,
                            modifier = Modifier.size(23.dp)
                        )
                    }

                    // 3-Dots Overflow Menu
                    Box {
                        IconButton(
                            onClick = { showMenu = true },
                            modifier = Modifier.size(40.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.MoreVert,
                                contentDescription = "More options",
                                tint = if (isDark) Color(0xFFCBD5E1) else IconDarkColor,
                                modifier = Modifier.size(23.dp)
                            )
                        }

                        DropdownMenu(
                            expanded = showMenu,
                            onDismissRequest = { showMenu = false },
                            modifier = Modifier.background(if (isDark) Color(0xFF1E2430) else Color.White)
                        ) {
                            DropdownMenuItem(
                                text = { Text("View FAQs & Knowledgebase") },
                                onClick = {
                                    showMenu = false
                                    showFaqModal = true
                                },
                                leadingIcon = { Icon(Icons.Default.HelpOutline, null, tint = BrandGoldAmber) }
                            )
                            DropdownMenuItem(
                                text = { Text("Call Support Hotline") },
                                onClick = {
                                    showMenu = false
                                    val phone = remoteConfig.helplineNumber.ifBlank { "+8801700000000" }
                                    val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$phone"))
                                    context.startActivity(intent)
                                },
                                leadingIcon = { Icon(Icons.Default.Phone, null, tint = BrandGoldAmber) }
                            )
                            DropdownMenuItem(
                                text = { Text("Copy Support Email") },
                                onClick = {
                                    showMenu = false
                                    val email = remoteConfig.supportEmail.ifBlank { "support@swapnopay.top" }
                                    clipboardManager.setText(AnnotatedString(email))
                                    Toast.makeText(context, "Copied $email to clipboard", Toast.LENGTH_SHORT).show()
                                },
                                leadingIcon = { Icon(Icons.Default.ContentCopy, null, tint = BrandGoldAmber) }
                            )
                            HorizontalDivider()
                            DropdownMenuItem(
                                text = { Text("Clear Chat Messages", color = Color(0xFFEF4444)) },
                                onClick = {
                                    showMenu = false
                                    viewModel.clearSupportChat()
                                    Toast.makeText(context, "Chat conversation cleared", Toast.LENGTH_SHORT).show()
                                },
                                leadingIcon = { Icon(Icons.Default.DeleteOutline, null, tint = Color(0xFFEF4444)) }
                            )
                        }
                    }
                }

                // In-Chat Search Bar (Animated Expand/Collapse)
                AnimatedVisibility(
                    visible = isSearchActive,
                    enter = expandVertically() + fadeIn(),
                    exit = shrinkVertically() + fadeOut()
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 6.dp)
                            .background(
                                if (isDark) Color(0xFF1E222D) else Color(0xFFF1F5F9),
                                RoundedCornerShape(12.dp)
                            )
                            .padding(horizontal = 12.dp, vertical = 8.dp)
                    ) {
                        BasicTextField(
                            value = searchQuery,
                            onValueChange = { searchQuery = it },
                            singleLine = true,
                            textStyle = TextStyle(
                                color = if (isDark) Color.White else Color(0xFF0F172A),
                                fontSize = 14.sp
                            ),
                            cursorBrush = SolidColor(BrandGoldAmber),
                            decorationBox = { innerTextField ->
                                if (searchQuery.isEmpty()) {
                                    Text(
                                        text = "Search in chat...",
                                        fontSize = 14.sp,
                                        color = if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8)
                                    )
                                }
                                innerTextField()
                            },
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                }
            }
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .imePadding()
        ) {
            // Top Quick Help Banner Card ("Need quick help?")
            QuickHelpBanner(
                onViewFaqs = { showFaqModal = true },
                isDark = isDark
            )

            // Message History Feed (Real messages from Supabase live_chat_messages)
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f)
            ) {
                if (filteredMessages.isEmpty()) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = if (searchQuery.isNotBlank()) "No messages match '$searchQuery'" else "No messages yet.\nSend a message to start chatting with Support!",
                            fontSize = 13.5.sp,
                            color = if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8),
                            textAlign = TextAlign.Center,
                            lineHeight = 20.sp
                        )
                    }
                } else {
                    LazyColumn(
                        state = listState,
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(16.dp),
                        contentPadding = PaddingValues(top = 8.dp, bottom = 12.dp)
                    ) {
                        items(filteredMessages, key = { it.id }) { msg ->
                            val isUser = msg.sender == "MERCHANT"
                            ChatMessageItem(
                                message = msg,
                                isUser = isUser,
                                userInitial = activeProfile.businessName.take(1).ifBlank { activeProfile.accountHolder.take(1) }.ifBlank { "M" },
                                isDark = isDark
                            )
                        }
                    }
                }
            }

            // Quick Action Chips Row (Exact 4 chips from target design)
            QuickActionChips(
                onChipClick = { chipText ->
                    chatInput = chipText
                },
                isDark = isDark
            )

            // Bottom Input Bar (Full rounded pill container)
            ChatBottomInputBar(
                chatInput = chatInput,
                onInputChange = { chatInput = it },
                onSend = {
                    if (chatInput.trim().isNotEmpty()) {
                        viewModel.sendSupportChatMessage(chatInput.trim())
                        chatInput = ""
                    }
                },
                onAttachClick = { showAttachmentModal = true },
                onEmojiClick = {
                    chatInput += "👋 "
                },
                isDark = isDark
            )
        }
    }

    // FAQs Knowledgebase Bottom Sheet Modal
    if (showFaqModal) {
        FaqViewerModal(
            remoteFaqs = remoteConfig.faqs,
            onDismiss = { showFaqModal = false },
            onAskQuestion = { question ->
                showFaqModal = false
                viewModel.sendSupportChatMessage(question)
            },
            isDark = isDark
        )
    }

    // Attachment Selector Modal
    if (showAttachmentModal) {
        AttachmentSelectorModal(
            onSelectAttachment = { attachType ->
                showAttachmentModal = false
                viewModel.sendSupportChatMessage("[Attachment: $attachType]")
            },
            onDismiss = { showAttachmentModal = false },
            isDark = isDark
        )
    }
}

/**
 * Top Quick Help Banner Card
 * Replicating the warm cream card with dark headset icon, "Need quick help?", "Our support team is here 24/7", and "View FAQs >" pill button.
 */
@Composable
private fun QuickHelpBanner(
    onViewFaqs: () -> Unit,
    isDark: Boolean
) {
    val cardBg = if (isDark) Color(0xFF1E1D16) else Color(0xFFFFF9EC)
    val cardBorder = if (isDark) Color(0xFF453E1B) else Color(0xFFFEE89E).copy(alpha = 0.6f)
    val headsetBg = if (isDark) Color(0xFF383214) else Color(0xFFFEE89E)
    val pillBg = if (isDark) Color(0xFF383214) else Color(0xFFFEE89E)
    val pillTextColor = if (isDark) BrandGoldYellow else TextDarkPrimary

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 6.dp),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Headset Icon Circular Container (with dark headset matching image)
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .clip(CircleShape)
                    .background(headsetBg),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.Headset,
                    contentDescription = null,
                    tint = if (isDark) BrandGoldYellow else IconDarkColor,
                    modifier = Modifier.size(22.dp)
                )
            }

            Spacer(modifier = Modifier.width(12.dp))

            // Text Info
            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.Center
            ) {
                Text(
                    text = "Need quick help?",
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.5.sp,
                    color = if (isDark) Color.White else TextDarkPrimary
                )
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    text = "Our support team is here 24/7",
                    fontSize = 12.sp,
                    color = if (isDark) Color(0xFF94A3B8) else TextMutedSecondary
                )
            }

            // "View FAQs >" Pill Button
            Row(
                modifier = Modifier
                    .clip(RoundedCornerShape(20.dp))
                    .background(pillBg)
                    .clickable(onClick = onViewFaqs)
                    .padding(horizontal = 12.dp, vertical = 7.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(3.dp)
            ) {
                Text(
                    text = "View FAQs",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = pillTextColor
                )
                Icon(
                    imageVector = Icons.Default.ChevronRight,
                    contentDescription = null,
                    tint = pillTextColor,
                    modifier = Modifier.size(14.dp)
                )
            }
        }
    }
}

/**
 * Individual Chat Message Row
 * Support (incoming): Left-aligned with Headset avatar aligned to top, "SwapnoPay Support" label, soft grayish-blue bubble (#F1F5FB), timestamp below.
 * User (outgoing): Right-aligned with Brand Golden Yellow bubble (#FBC740), user avatar on top-right, timestamp + golden double checkmarks below.
 */
@Composable
private fun ChatMessageItem(
    message: AppViewModel.SupportChatMessage,
    isUser: Boolean,
    userInitial: String,
    isDark: Boolean
) {
    val timeFormat = remember { SimpleDateFormat("h:mm a", Locale.getDefault()) }
    val formattedTime = remember(message.timestamp) { timeFormat.format(Date(message.timestamp)) }

    val context = LocalContext.current
    val clipboardManager = LocalClipboardManager.current

    val incomingBubbleBg = if (isDark) SupportBubbleBgDark else SupportBubbleBgLight
    val incomingTextColor = if (isDark) Color.White else TextDarkPrimary

    if (isUser) {
        // --- OUTGOING USER MESSAGE (RIGHT ALIGNED) ---
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 40.dp),
            horizontalArrangement = Arrangement.End,
            verticalAlignment = Alignment.Top
        ) {
            Column(
                horizontalAlignment = Alignment.End,
                modifier = Modifier.weight(1f, fill = false)
            ) {
                // Outgoing Bubble (Warm Golden Yellow #FBC740)
                Surface(
                    shape = RoundedCornerShape(
                        topStart = 18.dp,
                        topEnd = 4.dp,
                        bottomStart = 18.dp,
                        bottomEnd = 18.dp
                    ),
                    color = BrandGoldYellow,
                    modifier = Modifier.clickable {
                        clipboardManager.setText(AnnotatedString(message.message))
                        Toast.makeText(context, "Copied message to clipboard", Toast.LENGTH_SHORT).show()
                    }
                ) {
                    Text(
                        text = message.message,
                        fontSize = 14.5.sp,
                        lineHeight = 21.sp,
                        fontWeight = FontWeight.Normal,
                        color = IconDarkColor,
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 11.dp)
                    )
                }

                Spacer(modifier = Modifier.height(4.dp))

                // Timestamp & Golden Double Checkmark
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp),
                    modifier = Modifier.padding(end = 4.dp)
                ) {
                    Text(
                        text = formattedTime,
                        fontSize = 11.sp,
                        color = TimestampMuted
                    )
                    Icon(
                        imageVector = Icons.Default.DoneAll,
                        contentDescription = "Read",
                        tint = BrandGoldAmber,
                        modifier = Modifier.size(15.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.width(8.dp))

            // User Profile Avatar Circle
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(Color(0xFF334155)),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = userInitial.uppercase(),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
            }
        }
    } else {
        // --- INCOMING SUPPORT MESSAGE (LEFT ALIGNED) ---
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(end = 40.dp),
            horizontalArrangement = Arrangement.Start,
            verticalAlignment = Alignment.Top
        ) {
            // Support Avatar: circular badge with dark headset matching image
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(if (isDark) Color(0xFF383214) else Color(0xFFFEE89E)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.Headset,
                    contentDescription = null,
                    tint = if (isDark) BrandGoldYellow else IconDarkColor,
                    modifier = Modifier.size(20.dp)
                )
            }

            Spacer(modifier = Modifier.width(8.dp))

            Column(
                horizontalAlignment = Alignment.Start,
                modifier = Modifier.weight(1f, fill = false)
            ) {
                // Sender Label: "SwapnoPay Support"
                Text(
                    text = if (message.sender == "AI_SUPPORT") "SwapnoPay AI Specialist" else "SwapnoPay Support",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isDark) Color(0xFFE2E8F0) else TextDarkPrimary,
                    modifier = Modifier.padding(start = 2.dp, bottom = 4.dp)
                )

                // Incoming Bubble (Soft Grayish Blue #F1F5FB)
                Surface(
                    shape = RoundedCornerShape(
                        topStart = 4.dp,
                        topEnd = 18.dp,
                        bottomStart = 18.dp,
                        bottomEnd = 18.dp
                    ),
                    color = incomingBubbleBg,
                    modifier = Modifier.clickable {
                        clipboardManager.setText(AnnotatedString(message.message))
                        Toast.makeText(context, "Copied message to clipboard", Toast.LENGTH_SHORT).show()
                    }
                ) {
                    Text(
                        text = message.message,
                        fontSize = 14.5.sp,
                        lineHeight = 21.sp,
                        color = incomingTextColor,
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 11.dp)
                    )
                }

                Spacer(modifier = Modifier.height(4.dp))

                // Timestamp
                Text(
                    text = formattedTime,
                    fontSize = 11.sp,
                    color = TimestampMuted,
                    modifier = Modifier.padding(start = 4.dp)
                )
            }
        }
    }
}

/**
 * Quick Action Chips Row
 * Pixel-perfect replica of the 4 chips in `media_1790094284953.png`:
 * 1. 📅 Change Booking
 * 2. ✖ Cancel Booking
 * 3. 🔄 Refund Status
 * 4. ••• Other Help
 */
@Composable
private fun QuickActionChips(
    onChipClick: (String) -> Unit,
    isDark: Boolean
) {
    val scrollState = rememberScrollState()

    val chipBg = if (isDark) Color(0xFF1E2430) else Color.White
    val chipBorder = if (isDark) Color(0xFF333A48) else Color(0xFFE5E7EB)
    val badgeBg = if (isDark) Color(0xFF383214) else Color(0xFFFEE89E)
    val iconTint = if (isDark) BrandGoldYellow else IconDarkColor
    val textColor = if (isDark) Color.White else TextDarkPrimary

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(scrollState)
            .padding(horizontal = 16.dp, vertical = 6.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        // Chip 1: Change Booking
        Surface(
            shape = RoundedCornerShape(24.dp),
            color = chipBg,
            border = BorderStroke(1.dp, chipBorder),
            modifier = Modifier
                .clip(RoundedCornerShape(24.dp))
                .clickable { onChipClick("Change Booking") }
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.padding(start = 4.dp, top = 4.dp, bottom = 4.dp, end = 12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(26.dp)
                        .clip(CircleShape)
                        .background(badgeBg),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.CalendarToday,
                        contentDescription = null,
                        tint = iconTint,
                        modifier = Modifier.size(13.dp)
                    )
                }
                Spacer(modifier = Modifier.width(7.dp))
                Text(
                    text = "Change Booking",
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = textColor
                )
            }
        }

        // Chip 2: Cancel Booking
        Surface(
            shape = RoundedCornerShape(24.dp),
            color = chipBg,
            border = BorderStroke(1.dp, chipBorder),
            modifier = Modifier
                .clip(RoundedCornerShape(24.dp))
                .clickable { onChipClick("Cancel Booking") }
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.padding(start = 4.dp, top = 4.dp, bottom = 4.dp, end = 12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(26.dp)
                        .clip(CircleShape)
                        .background(badgeBg),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Close,
                        contentDescription = null,
                        tint = iconTint,
                        modifier = Modifier.size(13.dp)
                    )
                }
                Spacer(modifier = Modifier.width(7.dp))
                Text(
                    text = "Cancel Booking",
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = textColor
                )
            }
        }

        // Chip 3: Refund Status
        Surface(
            shape = RoundedCornerShape(24.dp),
            color = chipBg,
            border = BorderStroke(1.dp, chipBorder),
            modifier = Modifier
                .clip(RoundedCornerShape(24.dp))
                .clickable { onChipClick("Refund Status") }
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.padding(start = 4.dp, top = 4.dp, bottom = 4.dp, end = 12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(26.dp)
                        .clip(CircleShape)
                        .background(badgeBg),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Refresh,
                        contentDescription = null,
                        tint = iconTint,
                        modifier = Modifier.size(13.dp)
                    )
                }
                Spacer(modifier = Modifier.width(7.dp))
                Text(
                    text = "Refund Status",
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = textColor
                )
            }
        }

        // Chip 4: Other Help
        Surface(
            shape = RoundedCornerShape(24.dp),
            color = chipBg,
            border = BorderStroke(1.dp, chipBorder),
            modifier = Modifier
                .clip(RoundedCornerShape(24.dp))
                .clickable { onChipClick("Other Help") }
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.padding(start = 4.dp, top = 4.dp, bottom = 4.dp, end = 12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(26.dp)
                        .clip(CircleShape)
                        .background(badgeBg),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "•••",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Black,
                        color = iconTint
                    )
                }
                Spacer(modifier = Modifier.width(7.dp))
                Text(
                    text = "Other Help",
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = textColor
                )
            }
        }
    }
}

/**
 * Bottom Input Bar
 * Outer white rounded pill containing:
 * - Attachment icon (paperclip)
 * - BasicTextField "Type your message..."
 * - Emoji smile icon
 * - Golden Yellow circular send button
 */
@Composable
private fun ChatBottomInputBar(
    chatInput: String,
    onInputChange: (String) -> Unit,
    onSend: () -> Unit,
    onAttachClick: () -> Unit,
    onEmojiClick: () -> Unit,
    isDark: Boolean
) {
    val pillBg = if (isDark) Color(0xFF1E2430) else Color.White
    val pillBorder = if (isDark) Color(0xFF333A48) else Color(0xFFE5E7EB)
    val hintColor = if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8)
    val inputTextColor = if (isDark) Color.White else Color(0xFF0F172A)
    val iconTint = if (isDark) Color(0xFF94A3B8) else IconMutedColor

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(if (isDark) Color(0xFF0F1117) else Color.White)
            .navigationBarsPadding()
            .padding(horizontal = 16.dp, vertical = 8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(pillBg, RoundedCornerShape(32.dp))
                .border(BorderStroke(1.dp, pillBorder), RoundedCornerShape(32.dp))
                .padding(start = 6.dp, end = 6.dp, top = 5.dp, bottom = 5.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Attachment Icon Button (Paperclip)
            IconButton(
                onClick = onAttachClick,
                modifier = Modifier.size(38.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.AttachFile,
                    contentDescription = "Attach file",
                    tint = iconTint,
                    modifier = Modifier.size(22.dp)
                )
            }

            // Message Text Input
            Box(
                modifier = Modifier
                    .weight(1f)
                    .padding(horizontal = 4.dp),
                contentAlignment = Alignment.CenterStart
            ) {
                if (chatInput.isEmpty()) {
                    Text(
                        text = "Type your message...",
                        fontSize = 14.5.sp,
                        color = hintColor
                    )
                }

                BasicTextField(
                    value = chatInput,
                    onValueChange = onInputChange,
                    textStyle = TextStyle(
                        color = inputTextColor,
                        fontSize = 14.5.sp,
                        fontWeight = FontWeight.Normal,
                        lineHeight = 20.sp
                    ),
                    cursorBrush = SolidColor(BrandGoldAmber),
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Send),
                    keyboardActions = KeyboardActions(onSend = { onSend() }),
                    maxLines = 4,
                    modifier = Modifier.fillMaxWidth()
                )
            }

            // Emoji Smile Button
            IconButton(
                onClick = onEmojiClick,
                modifier = Modifier.size(38.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.EmojiEmotions,
                    contentDescription = "Emoji",
                    tint = iconTint,
                    modifier = Modifier.size(22.dp)
                )
            }

            // Circular Golden Yellow Send Button (#FBC740)
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(BrandGoldYellow)
                    .clickable { onSend() },
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.Send,
                    contentDescription = "Send",
                    tint = IconDarkColor,
                    modifier = Modifier.size(19.dp)
                )
            }
        }
    }
}

/**
 * FAQs Knowledgebase Bottom Sheet Modal
 * Connects directly to real CMS remote config FAQs without fake items.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FaqViewerModal(
    remoteFaqs: List<AppViewModel.SupportFaqItem>,
    onDismiss: () -> Unit,
    onAskQuestion: (String) -> Unit,
    isDark: Boolean
) {
    var expandedIndex by remember { mutableStateOf<Int?>(null) }
    var searchFaq by remember { mutableStateOf("") }

    val filtered = remember(remoteFaqs, searchFaq) {
        if (searchFaq.isBlank()) remoteFaqs
        else remoteFaqs.filter {
            it.question.contains(searchFaq, ignoreCase = true) || it.answer.contains(searchFaq, ignoreCase = true)
        }
    }

    EnterpriseGestureModal(
        onDismissRequest = onDismiss,
        title = "Frequently Asked Questions",
        subtitle = "Official SwapnoPay support knowledgebase",
        icon = Icons.Default.HelpOutline
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp)
        ) {
            if (remoteFaqs.isEmpty()) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 32.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "No FAQs configured in admin CMS yet.\nFeel free to type your question directly in the live support chat!",
                        fontSize = 13.5.sp,
                        color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B),
                        textAlign = TextAlign.Center,
                        lineHeight = 20.sp
                    )
                }
            } else {
                OutlinedTextField(
                    value = searchFaq,
                    onValueChange = { searchFaq = it },
                    placeholder = { Text("Search FAQ topics...", fontSize = 13.5.sp) },
                    leadingIcon = { Icon(Icons.Default.Search, null, tint = BrandGoldAmber) },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = BrandGoldAmber,
                        unfocusedBorderColor = if (isDark) Color(0xFF333A48) else Color(0xFFE2E8F0)
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 12.dp)
                )

                LazyColumn(
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(max = 420.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    items(filtered.size) { index ->
                        val faq = filtered[index]
                        val isExpanded = expandedIndex == index

                        Card(
                            shape = RoundedCornerShape(12.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = if (isDark) Color(0xFF1E2430) else Color(0xFFF8FAFC)
                            ),
                            border = BorderStroke(1.dp, if (isDark) Color(0xFF333A48) else Color(0xFFE2E8F0)),
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(12.dp))
                                .clickable { expandedIndex = if (isExpanded) null else index }
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(14.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Text(
                                        text = faq.question,
                                        fontSize = 13.5.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = if (isDark) Color.White else TextDarkPrimary,
                                        modifier = Modifier.weight(1f)
                                    )
                                    Icon(
                                        imageVector = if (isExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                                        contentDescription = null,
                                        tint = BrandGoldAmber,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }

                                if (isExpanded) {
                                    Spacer(modifier = Modifier.height(10.dp))
                                    Text(
                                        text = faq.answer,
                                        fontSize = 13.sp,
                                        lineHeight = 18.sp,
                                        color = if (isDark) Color(0xFFCBD5E1) else TextMutedSecondary
                                    )
                                    Spacer(modifier = Modifier.height(10.dp))
                                    Button(
                                        onClick = { onAskQuestion(faq.question) },
                                        colors = ButtonDefaults.buttonColors(containerColor = BrandGoldYellow),
                                        shape = RoundedCornerShape(8.dp),
                                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Chat,
                                            contentDescription = null,
                                            tint = IconDarkColor,
                                            modifier = Modifier.size(14.dp)
                                        )
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text(
                                            text = "Ask this in Chat",
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = IconDarkColor
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
}

/**
 * Attachment Selector Modal
 * Quick attachments for screenshot, transaction slip, or error log.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AttachmentSelectorModal(
    onSelectAttachment: (String) -> Unit,
    onDismiss: () -> Unit,
    isDark: Boolean
) {
    val items = listOf(
        Triple("Payment Receipt Slip", Icons.Default.ReceiptLong, "Send proof of bank/wallet payment"),
        Triple("App Screenshot", Icons.Default.Image, "Share an issue or error screenshot"),
        Triple("Transaction Log", Icons.Default.Description, "Share transaction ID and SMS text")
    )

    EnterpriseGestureModal(
        onDismissRequest = onDismiss,
        title = "Add Attachment",
        subtitle = "Select an item to share with our support specialist",
        icon = Icons.Default.AttachFile
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            items.forEach { (title, icon, subtitle) ->
                Card(
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (isDark) Color(0xFF1E2430) else Color(0xFFF8FAFC)
                    ),
                    border = BorderStroke(1.dp, if (isDark) Color(0xFF333A48) else Color(0xFFE2E8F0)),
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .clickable { onSelectAttachment(title) }
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
                                .clip(CircleShape)
                                .background(if (isDark) Color(0xFF383214) else Color(0xFFFEE89E)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(icon, null, tint = if (isDark) BrandGoldYellow else IconDarkColor, modifier = Modifier.size(20.dp))
                        }
                        Spacer(modifier = Modifier.width(14.dp))
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = title,
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (isDark) Color.White else TextDarkPrimary
                            )
                            Text(
                                text = subtitle,
                                fontSize = 11.5.sp,
                                color = if (isDark) Color(0xFF94A3B8) else TextMutedSecondary
                            )
                        }
                    }
                }
            }
        }
    }
}
