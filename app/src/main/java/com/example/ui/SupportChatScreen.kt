package com.example.ui

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.interaction.MutableInteractionSource
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
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
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

// Pixel-perfect color palette based on UI design
private val BrandGoldYellow = Color(0xFFF7C844)
private val BrandGoldDark = Color(0xFFD97706)
private val SupportBubbleLight = Color(0xFFF1F5FB)
private val SupportBubbleDark = Color(0xFF1E2430)
private val UserBubble = BrandGoldYellow
private val StatusOnlineGreen = Color(0xFF10B981)

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

    // Start polling or listening to support tickets when screen opens
    LaunchedEffect(Unit) {
        viewModel.listenToSupportChatFromPlatformOwner()
    }

    // Auto-scroll to latest message when new message arrives
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

    val bgColor = if (isDark) Color(0xFF0F1117) else Color(0xFFF8FAFC)
    val topBarBg = if (isDark) Color(0xFF141720) else Color.White
    val topBorderColor = if (isDark) Color(0xFF262C38) else Color(0xFFF1F5F9)

    Scaffold(
        containerColor = bgColor,
        topBar = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(topBarBg)
            ) {
                // Top App Bar
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .statusBarsPadding()
                        .height(64.dp)
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
                            tint = if (isDark) Color.White else Color(0xFF1E293B)
                        )
                    }

                    Spacer(modifier = Modifier.width(4.dp))

                    // SwapnoPay Avatar (Golden yellow circle with bold 'S')
                    Box(
                        modifier = Modifier
                            .size(42.dp)
                            .clip(CircleShape)
                            .background(BrandGoldYellow),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "S",
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Black,
                            color = Color(0xFF1E293B)
                        )
                    }

                    Spacer(modifier = Modifier.width(12.dp))

                    // Title & Online Status
                    Column(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.Center
                    ) {
                        Text(
                            text = "SwapnoPay",
                            fontSize = 16.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (isDark) Color.White else Color(0xFF0F172A),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(5.dp)
                        ) {
                            Text(
                                text = "Support Chat",
                                fontSize = 12.sp,
                                color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
                            )
                            Text(
                                text = "●",
                                fontSize = 8.sp,
                                color = StatusOnlineGreen
                            )
                            Text(
                                text = "Online",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = StatusOnlineGreen
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
                            tint = if (isDark) Color(0xFF94A3B8) else Color(0xFF475569)
                        )
                    }

                    // More Menu
                    Box {
                        IconButton(
                            onClick = { showMenu = true },
                            modifier = Modifier.size(40.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.MoreVert,
                                contentDescription = "More options",
                                tint = if (isDark) Color(0xFF94A3B8) else Color(0xFF475569)
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
                                leadingIcon = { Icon(Icons.Default.HelpOutline, null, tint = BrandGoldDark) }
                            )
                            DropdownMenuItem(
                                text = { Text("Call Support Helpline") },
                                onClick = {
                                    showMenu = false
                                    val phone = remoteConfig.helplineNumber.ifBlank { "+8801800000000" }
                                    val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$phone"))
                                    context.startActivity(intent)
                                },
                                leadingIcon = { Icon(Icons.Default.Phone, null, tint = BrandGoldDark) }
                            )
                            DropdownMenuItem(
                                text = { Text("Copy Support Email") },
                                onClick = {
                                    showMenu = false
                                    val email = remoteConfig.supportEmail.ifBlank { "support@swapnopay.top" }
                                    clipboardManager.setText(AnnotatedString(email))
                                    Toast.makeText(context, "Copied $email to clipboard", Toast.LENGTH_SHORT).show()
                                },
                                leadingIcon = { Icon(Icons.Default.ContentCopy, null, tint = BrandGoldDark) }
                            )
                            HorizontalDivider()
                            DropdownMenuItem(
                                text = { Text("Clear Conversation", color = Color(0xFFEF4444)) },
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
                            cursorBrush = SolidColor(BrandGoldDark),
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

                // Subtle Top Divider
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(1.dp)
                        .background(topBorderColor)
                )
            }
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .imePadding()
        ) {
            // Top Quick Help Banner Card: "Need quick help? Our support team is here 24/7"
            QuickHelpBanner(
                onViewFaqs = { showFaqModal = true },
                isDark = isDark
            )

            // Message History Feed
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
                            text = if (searchQuery.isNotBlank()) "No messages match '$searchQuery'" else "No messages yet.\nSay hello to our support team!",
                            fontSize = 13.5.sp,
                            color = if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8),
                            textAlign = TextAlign.Center
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
                                userInitial = activeProfile.businessName.take(1).ifBlank { "M" },
                                isDark = isDark
                            )
                        }
                    }
                }
            }

            // Quick Action Chips Row (Scrollable chips matching UI design)
            QuickActionChips(
                onChipClick = { chipText ->
                    viewModel.sendSupportChatMessage(chipText)
                },
                isDark = isDark
            )

            // Bottom Input Bar (Full rounded pill with paperclip, textfield, emoji, and golden yellow send button)
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
 * Replicating the yellow/cream card with headset icon, "Need quick help?", "Our support team is here 24/7", and "View FAQs >" pill button.
 */
@Composable
private fun QuickHelpBanner(
    onViewFaqs: () -> Unit,
    isDark: Boolean
) {
    val cardBg = if (isDark) Color(0xFF242014) else Color(0xFFFFFBEB)
    val cardBorder = if (isDark) Color(0xFF5E4E1C) else Color(0xFFFDE68A)
    val headsetBg = if (isDark) Color(0xFF382F14) else Color(0xFFFEF3C7)
    val pillBg = if (isDark) Color(0xFF332B14) else Color(0xFFFEF3C7)
    val pillBorder = if (isDark) Color(0xFF5E4E1C) else Color(0xFFFDE68A)
    val pillTextColor = if (isDark) Color(0xFFFDE68A) else Color(0xFF92400E)

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 10.dp),
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
            // Headset Icon Circular Container
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
                    tint = BrandGoldDark,
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
                    color = if (isDark) Color.White else Color(0xFF1E293B)
                )
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    text = "Our support team is here 24/7",
                    fontSize = 12.sp,
                    color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
                )
            }

            // "View FAQs >" Pill Button
            Row(
                modifier = Modifier
                    .clip(RoundedCornerShape(20.dp))
                    .background(pillBg)
                    .border(BorderStroke(1.dp, pillBorder), RoundedCornerShape(20.dp))
                    .clickable(onClick = onViewFaqs)
                    .padding(horizontal = 10.dp, vertical = 6.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(2.dp)
            ) {
                Text(
                    text = "View FAQs",
                    fontSize = 11.5.sp,
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
 * Support (incoming): Left-aligned with Headset avatar, "SwapnoPay Support" label, soft lavender/blue-gray bubble (#F1F5FB), timestamp below.
 * User (outgoing): Right-aligned with Brand Golden Yellow bubble (#F7C844), user avatar on right, timestamp + double checkmark below.
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

    val incomingBubbleBg = if (isDark) SupportBubbleDark else SupportBubbleLight
    val incomingTextColor = if (isDark) Color(0xFFF1F5F9) else Color(0xFF1E293B)

    if (isUser) {
        // --- OUTGOING USER MESSAGE (RIGHT ALIGNED) ---
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 48.dp),
            horizontalArrangement = Arrangement.End,
            verticalAlignment = Alignment.Bottom
        ) {
            Column(
                horizontalAlignment = Alignment.End,
                modifier = Modifier.weight(1f, fill = false)
            ) {
                // Outgoing Bubble (Golden Yellow)
                Surface(
                    shape = RoundedCornerShape(
                        topStart = 16.dp,
                        topEnd = 16.dp,
                        bottomStart = 16.dp,
                        bottomEnd = 4.dp
                    ),
                    color = UserBubble,
                    shadowElevation = 0.5.dp,
                    modifier = Modifier.clickable {
                        clipboardManager.setText(AnnotatedString(message.message))
                        Toast.makeText(context, "Copied to clipboard", Toast.LENGTH_SHORT).show()
                    }
                ) {
                    Text(
                        text = message.message,
                        fontSize = 14.sp,
                        lineHeight = 20.sp,
                        fontWeight = FontWeight.Medium,
                        color = Color(0xFF1E293B),
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)
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
                        fontSize = 10.5.sp,
                        color = if (isDark) Color(0xFF94A3B8) else Color(0xFF94A3B8)
                    )
                    Icon(
                        imageVector = Icons.Default.DoneAll,
                        contentDescription = "Read",
                        tint = BrandGoldDark,
                        modifier = Modifier.size(14.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.width(8.dp))

            // User Profile Avatar
            Box(
                modifier = Modifier
                    .size(34.dp)
                    .clip(CircleShape)
                    .background(if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = userInitial.uppercase(),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (isDark) Color.White else Color(0xFF334155)
                )
            }
        }
    } else {
        // --- INCOMING SUPPORT MESSAGE (LEFT ALIGNED) ---
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(end = 48.dp),
            horizontalArrangement = Arrangement.Start,
            verticalAlignment = Alignment.Top
        ) {
            // Support Avatar (Circular badge with headset)
            Box(
                modifier = Modifier
                    .size(34.dp)
                    .clip(CircleShape)
                    .background(if (isDark) Color(0xFF332B14) else Color(0xFFFEF3C7)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.Headset,
                    contentDescription = null,
                    tint = BrandGoldDark,
                    modifier = Modifier.size(18.dp)
                )
            }

            Spacer(modifier = Modifier.width(8.dp))

            Column(
                horizontalAlignment = Alignment.Start,
                modifier = Modifier.weight(1f, fill = false)
            ) {
                // Sender Label: "SwapnoPay Support"
                Text(
                    text = if (message.sender == "AI_SUPPORT") "SwapnoPay AI Assistant" else "SwapnoPay Support",
                    fontSize = 11.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B),
                    modifier = Modifier.padding(start = 2.dp, bottom = 4.dp)
                )

                // Incoming Bubble (Soft Blue/Gray)
                Surface(
                    shape = RoundedCornerShape(
                        topStart = 4.dp,
                        topEnd = 16.dp,
                        bottomStart = 16.dp,
                        bottomEnd = 16.dp
                    ),
                    color = incomingBubbleBg,
                    shadowElevation = 0.5.dp,
                    modifier = Modifier.clickable {
                        clipboardManager.setText(AnnotatedString(message.message))
                        Toast.makeText(context, "Copied to clipboard", Toast.LENGTH_SHORT).show()
                    }
                ) {
                    Text(
                        text = message.message,
                        fontSize = 14.sp,
                        lineHeight = 20.sp,
                        color = incomingTextColor,
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)
                    )
                }

                Spacer(modifier = Modifier.height(4.dp))

                // Timestamp
                Text(
                    text = formattedTime,
                    fontSize = 10.5.sp,
                    color = if (isDark) Color(0xFF94A3B8) else Color(0xFF94A3B8),
                    modifier = Modifier.padding(start = 4.dp)
                )
            }
        }
    }
}

/**
 * Quick Action Chips Row
 * Exactly matching `media_1790094284953.png`:
 * "📅 Change Booking", "❌ Cancel Booking", "🔄 Refund Status", "💬 Other Help"
 * + Extra merchant payment shortcuts.
 */
@Composable
private fun QuickActionChips(
    onChipClick: (String) -> Unit,
    isDark: Boolean
) {
    val scrollState = rememberScrollState()

    val chips = listOf(
        "📅 Change Booking",
        "❌ Cancel Booking",
        "🔄 Refund Status",
        "💬 Other Help",
        "⚡ Verify Payment",
        "💳 Gateway Setup"
    )

    val chipBg = if (isDark) Color(0xFF1E2430) else Color.White
    val chipBorder = if (isDark) Color(0xFF333A48) else Color(0xFFE2E8F0)
    val chipTextColor = if (isDark) Color(0xFFE2E8F0) else Color(0xFF334155)

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(scrollState)
            .padding(horizontal = 16.dp, vertical = 6.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        chips.forEach { chipText ->
            Surface(
                shape = RoundedCornerShape(20.dp),
                color = chipBg,
                border = BorderStroke(1.dp, chipBorder),
                modifier = Modifier
                    .clip(RoundedCornerShape(20.dp))
                    .clickable { onChipClick(chipText) }
            ) {
                Text(
                    text = chipText,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.Medium,
                    color = chipTextColor,
                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 7.dp)
                )
            }
        }
    }
}

/**
 * Bottom Input Bar
 * Pixel-perfect replica:
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
    val pillBg = if (isDark) Color(0xFF191D26) else Color.White
    val pillBorder = if (isDark) Color(0xFF2C3240) else Color(0xFFE2E8F0)
    val hintColor = if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8)
    val inputTextColor = if (isDark) Color.White else Color(0xFF0F172A)
    val iconTint = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(if (isDark) Color(0xFF0F1117) else Color(0xFFF8FAFC))
            .navigationBarsPadding()
            .padding(horizontal = 14.dp, vertical = 8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .shadow(
                    elevation = if (isDark) 0.dp else 2.dp,
                    shape = RoundedCornerShape(28.dp),
                    ambientColor = Color.Black.copy(alpha = 0.05f)
                )
                .background(pillBg, RoundedCornerShape(28.dp))
                .border(BorderStroke(1.dp, pillBorder), RoundedCornerShape(28.dp))
                .padding(horizontal = 6.dp, vertical = 4.dp),
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
                    modifier = Modifier.size(20.dp)
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
                        fontSize = 14.sp,
                        color = hintColor
                    )
                }

                BasicTextField(
                    value = chatInput,
                    onValueChange = onInputChange,
                    textStyle = TextStyle(
                        color = inputTextColor,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Normal,
                        lineHeight = 19.sp
                    ),
                    cursorBrush = SolidColor(BrandGoldDark),
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
                    modifier = Modifier.size(20.dp)
                )
            }

            // Circular Golden Yellow Send Button
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .clip(CircleShape)
                    .background(BrandGoldYellow)
                    .clickable { onSend() },
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.Send,
                    contentDescription = "Send",
                    tint = Color(0xFF1E293B),
                    modifier = Modifier.size(19.dp)
                )
            }
        }
    }
}

/**
 * FAQs Knowledgebase Bottom Sheet Modal
 * Displays expandable FAQs and allows tapping any question to ask it directly in chat.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FaqViewerModal(
    remoteFaqs: List<AppViewModel.SupportFaqItem>,
    onDismiss: () -> Unit,
    onAskQuestion: (String) -> Unit,
    isDark: Boolean
) {
    val defaultFaqs = remember {
        listOf(
            AppViewModel.SupportFaqItem(
                question = "How do I match customer payments automatically?",
                answer = "SwapnoPay automatically reads SMS notifications from bKash, Nagad, and Rocket to match customer payments with pending orders in real time using the Transaction ID (TrxID)."
            ),
            AppViewModel.SupportFaqItem(
                question = "How do I request a refund for a customer?",
                answer = "Navigate to Transactions, select the payment, and click 'Initiate Refund'. Refunds are routed directly through your merchant wallet or manual adjustment within 24 hours."
            ),
            AppViewModel.SupportFaqItem(
                question = "How to integrate SwapnoPay payment gateway on my website?",
                answer = "Go to More > Developer API Docs. You'll find your API keys, REST API endpoints, sample Node.js/PHP/Python code, and webhooks documentation."
            ),
            AppViewModel.SupportFaqItem(
                question = "Why is an incoming SMS not showing up?",
                answer = "Ensure the app has SMS Receive and Read permissions enabled in Android Settings, and that battery optimization is disabled so background sync is uninterrupted."
            ),
            AppViewModel.SupportFaqItem(
                question = "How can I change my receiving mobile numbers?",
                answer = "Go to Setup > Receiving Numbers. You can add, activate, or update bKash, Nagad, Rocket, and Upay numbers anytime."
            )
        )
    }

    val displayFaqs = if (remoteFaqs.isNotEmpty()) remoteFaqs else defaultFaqs
    var expandedIndex by remember { mutableStateOf<Int?>(null) }
    var searchFaq by remember { mutableStateOf("") }

    val filtered = remember(displayFaqs, searchFaq) {
        if (searchFaq.isBlank()) displayFaqs
        else displayFaqs.filter {
            it.question.contains(searchFaq, ignoreCase = true) || it.answer.contains(searchFaq, ignoreCase = true)
        }
    }

    EnterpriseGestureModal(
        onDismissRequest = onDismiss,
        title = "Frequently Asked Questions",
        subtitle = "Find quick solutions or tap a question to ask in chat",
        icon = Icons.Default.HelpOutline
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp)
        ) {
            // Search Input
            OutlinedTextField(
                value = searchFaq,
                onValueChange = { searchFaq = it },
                placeholder = { Text("Search FAQ topics...", fontSize = 13.5.sp) },
                leadingIcon = { Icon(Icons.Default.Search, null, tint = BrandGoldDark) },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = BrandGoldDark,
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
                                    color = if (isDark) Color.White else Color(0xFF1E293B),
                                    modifier = Modifier.weight(1f)
                                )
                                Icon(
                                    imageVector = if (isExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                                    contentDescription = null,
                                    tint = BrandGoldDark,
                                    modifier = Modifier.size(20.dp)
                                )
                            }

                            if (isExpanded) {
                                Spacer(modifier = Modifier.height(10.dp))
                                Text(
                                    text = faq.answer,
                                    fontSize = 13.sp,
                                    lineHeight = 18.sp,
                                    color = if (isDark) Color(0xFFCBD5E1) else Color(0xFF475569)
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
                                        tint = Color(0xFF1E293B),
                                        modifier = Modifier.size(14.dp)
                                    )
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text(
                                        text = "Ask this in Chat",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Color(0xFF1E293B)
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
                                .background(if (isDark) Color(0xFF332B14) else Color(0xFFFEF3C7)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(icon, null, tint = BrandGoldDark, modifier = Modifier.size(20.dp))
                        }
                        Spacer(modifier = Modifier.width(14.dp))
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = title,
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (isDark) Color.White else Color(0xFF1E293B)
                            )
                            Text(
                                text = subtitle,
                                fontSize = 11.5.sp,
                                color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
                            )
                        }
                    }
                }
            }
        }
    }
}
