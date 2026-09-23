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
    val savedSessions by viewModel.savedSupportChatSessions.collectAsState()
    val currentSessionId by viewModel.currentSupportSessionId.collectAsState()
    val myTickets by viewModel.mySupportTicketsList.collectAsState()

    var chatInput by remember { mutableStateOf("") }
    var showHistoryDrawer by remember { mutableStateOf(false) }
    var showFaqModal by remember { mutableStateOf(false) }
    var showAttachmentModal by remember { mutableStateOf(false) }

    val listState = rememberLazyListState()

    // Real-time live chat polling: continuously syncs with Admin Helpdesk & Tickets
    LaunchedEffect(Unit) {
        viewModel.listenToSupportChatFromPlatformOwner()
        viewModel.listenToMerchantSupportTickets()
    }

    // Auto-scroll to bottom when new messages arrive
    LaunchedEffect(chatList.size) {
        if (chatList.isNotEmpty()) {
            listState.animateScrollToItem(chatList.size - 1)
        }
    }

    Box(modifier = Modifier.fillMaxSize()) {
        Scaffold(
            containerColor = if (isDark) Color(0xFF0F1117) else Color.White,
            topBar = {
                ChatTopAppBar(
                    isDark = isDark,
                    onBack = { viewModel.goBack() },
                    onOpenHistory = { showHistoryDrawer = true }
                )
            },
            bottomBar = {
                ChatBottomInputBar(
                    chatInput = chatInput,
                    onInputChange = { chatInput = it },
                    onSend = {
                        if (chatInput.isNotBlank()) {
                            val msg = chatInput
                            chatInput = ""
                            viewModel.sendSupportChatMessage(msg)
                        }
                    },
                    onAttachClick = { showAttachmentModal = true },
                    onEmojiClick = {
                        chatInput += " 😊"
                    },
                    isDark = isDark
                )
            }
        ) { paddingValues ->
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
                    .background(if (isDark) Color(0xFF0F1117) else Color.White)
            ) {
                // Conversation Messages Feed
                if (chatList.isEmpty()) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .weight(1f)
                            .padding(24.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.Center
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(64.dp)
                                    .clip(CircleShape)
                                    .background(if (isDark) Color(0xFF1E2430) else Color(0xFFF1F5FB)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.HeadsetMic,
                                    contentDescription = null,
                                    tint = if (isDark) BrandGoldYellow else BrandGoldAmber,
                                    modifier = Modifier.size(32.dp)
                                )
                            }
                            Spacer(modifier = Modifier.height(14.dp))
                            Text(
                                text = "How can we help you today?",
                                fontSize = 15.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = if (isDark) Color.White else TextDarkPrimary
                            )
                            Spacer(modifier = Modifier.height(6.dp))
                            Text(
                                text = "Send a message below to chat with our 24/7 support specialist.",
                                fontSize = 13.sp,
                                color = if (isDark) Color(0xFF94A3B8) else TextMutedSecondary,
                                textAlign = TextAlign.Center,
                                modifier = Modifier.padding(horizontal = 24.dp)
                            )
                        }
                    }
                } else {
                    LazyColumn(
                        state = listState,
                        modifier = Modifier
                            .fillMaxWidth()
                            .weight(1f)
                            .padding(horizontal = 14.dp),
                        contentPadding = PaddingValues(top = 10.dp, bottom = 12.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        items(chatList, key = { it.id }) { message ->
                            ChatMessageItem(
                                message = message,
                                userInitial = activeProfile.businessName.ifBlank { "You" }.take(1).uppercase(Locale.getDefault()),
                                onCopy = { text ->
                                    clipboardManager.setText(AnnotatedString(text))
                                    Toast.makeText(context, "Message copied", Toast.LENGTH_SHORT).show()
                                },
                                isDark = isDark
                            )
                        }
                    }
                }
            }
        }

        // Side Navigation Drawer for Chat History & Actions
        if (showHistoryDrawer) {
            // Semi-transparent Backdrop Scrim
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.55f))
                    .clickable { showHistoryDrawer = false }
            )

            // Sliding Sidebar Drawer Content from Right Side
            AnimatedVisibility(
                visible = showHistoryDrawer,
                enter = slideInHorizontally(initialOffsetX = { it }),
                exit = slideOutHorizontally(targetOffsetX = { it }),
                modifier = Modifier.align(Alignment.CenterEnd)
            ) {
                ChatHistorySideDrawer(
                    savedSessions = savedSessions,
                    currentSessionId = currentSessionId,
                    currentChatMessages = chatList,
                    myTickets = myTickets,
                    isDark = isDark,
                    onClose = { showHistoryDrawer = false },
                    onNewChat = {
                        viewModel.startNewSupportChatSession()
                        showHistoryDrawer = false
                        Toast.makeText(context, "New chat started", Toast.LENGTH_SHORT).show()
                    },
                    onSelectSession = { sessionId ->
                        viewModel.loadSupportChatSession(sessionId)
                        showHistoryDrawer = false
                    },
                    onDeleteSession = { sessionId ->
                        viewModel.deleteSupportChatSession(sessionId)
                        Toast.makeText(context, "Conversation deleted", Toast.LENGTH_SHORT).show()
                    },
                    onClearAllSessions = {
                        viewModel.clearAllSupportChatSessions()
                        Toast.makeText(context, "All history cleared", Toast.LENGTH_SHORT).show()
                    },
                    onOpenFaq = {
                        showHistoryDrawer = false
                        showFaqModal = true
                    },
                    onCallHelpline = {
                        showHistoryDrawer = false
                        val helpline = remoteConfig.supportHelpline.ifBlank { "+8801700000000" }
                        val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$helpline"))
                        runCatching { context.startActivity(intent) }
                    },
                    onEmailSupport = {
                        showHistoryDrawer = false
                        val email = remoteConfig.supportEmail.ifBlank { "support@swapnopay.top" }
                        clipboardManager.setText(AnnotatedString(email))
                        Toast.makeText(context, "Support email copied: $email", Toast.LENGTH_SHORT).show()
                    },
                    onCopyTranscript = {
                        val transcript = if (chatList.isEmpty()) "No messages in conversation"
                        else chatList.joinToString("\n\n") { msg ->
                            val time = SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.getDefault()).format(Date(msg.timestamp))
                            "[${msg.sender} - $time]\n${msg.message}"
                        }
                        clipboardManager.setText(AnnotatedString(transcript))
                        Toast.makeText(context, "Chat transcript copied to clipboard", Toast.LENGTH_SHORT).show()
                    },
                    onClearCurrentChat = {
                        viewModel.clearSupportChat()
                        showHistoryDrawer = false
                        Toast.makeText(context, "Chat cleared", Toast.LENGTH_SHORT).show()
                    }
                )
            }
        }
    }

    // Knowledgebase FAQs Bottom Sheet
    if (showFaqModal) {
        FaqViewerModal(
            remoteFaqs = remoteConfig.faqs,
            onDismiss = { showFaqModal = false },
            onAskQuestion = { q ->
                showFaqModal = false
                viewModel.sendSupportChatMessage(q)
            },
            isDark = isDark
        )
    }

    // Attachment dialog
    if (showAttachmentModal) {
        AttachmentSelectorModal(
            onSelectAttachment = { type ->
                showAttachmentModal = false
                viewModel.sendSupportChatMessage("[Attached $type]")
            },
            onDismiss = { showAttachmentModal = false },
            isDark = isDark
        )
    }
}

/**
 * Top App Bar
 * - Back button
 * - Circular Golden Yellow Avatar ('S')
 * - 3-Line title block: SwapnoPay / Support Chat / ● Online
 * - Action button: Chat History side navigation drawer toggle (Replaces Three-dot and Search)
 */
@Composable
private fun ChatTopAppBar(
    isDark: Boolean,
    onBack: () -> Unit,
    onOpenHistory: () -> Unit
) {
    Surface(
        color = if (isDark) Color(0xFF0F1117) else Color.White,
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 8.dp, vertical = 6.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Back Arrow
                IconButton(
                    onClick = onBack,
                    modifier = Modifier.size(40.dp)
                ) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = "Back",
                        tint = if (isDark) Color.White else IconDarkColor,
                        modifier = Modifier.size(22.dp)
                    )
                }

                // Brand S Avatar
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(BrandGoldYellow),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "S",
                        color = IconDarkColor,
                        fontSize = 19.sp,
                        fontWeight = FontWeight.Black
                    )
                }

                Spacer(modifier = Modifier.width(10.dp))

                // Title, Subtitle, Online status
                Column(
                    modifier = Modifier.weight(1f)
                ) {
                    Text(
                        text = "SwapnoPay",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (isDark) Color.White else TextDarkPrimary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = "Support Chat",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Normal,
                        color = if (isDark) Color(0xFF94A3B8) else TextMutedSecondary
                    )
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(top = 1.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(6.dp)
                                .clip(CircleShape)
                                .background(OnlineGreen)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "Online",
                            fontSize = 11.5.sp,
                            fontWeight = FontWeight.Medium,
                            color = OnlineGreen
                        )
                    }
                }

                // Chat History Button (Replaces Three-dot and Search icon)
                IconButton(
                    onClick = onOpenHistory,
                    modifier = Modifier.size(40.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.History,
                        contentDescription = "Chat History",
                        tint = if (isDark) Color.White else IconDarkColor,
                        modifier = Modifier.size(22.dp)
                    )
                }
            }
        }
    }
}

/**
 * Message Bubble Item
 * - Outgoing: Merchant bubble in golden yellow (#FBC740), pointed top-right, avatar on right
 * - Incoming: Support bubble in soft bluish-gray (#F1F5FB), pointed top-left, headset avatar on left
 */
@Composable
private fun ChatMessageItem(
    message: AppViewModel.SupportChatMessage,
    userInitial: String,
    onCopy: (String) -> Unit,
    isDark: Boolean
) {
    val isUser = message.sender.equals("MERCHANT", ignoreCase = true) || message.sender.equals("USER", ignoreCase = true)
    val timeFormatted = remember(message.timestamp) {
        val sdf = SimpleDateFormat("h:mm a", Locale.getDefault())
        sdf.format(Date(message.timestamp))
    }

    if (isUser) {
        // Outgoing Merchant Bubble (Right side)
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
                Surface(
                    shape = RoundedCornerShape(
                        topStart = 16.dp,
                        topEnd = 4.dp,
                        bottomEnd = 16.dp,
                        bottomStart = 16.dp
                    ),
                    color = BrandGoldYellow,
                    modifier = Modifier
                        .clip(
                            RoundedCornerShape(
                                topStart = 16.dp,
                                topEnd = 4.dp,
                                bottomEnd = 16.dp,
                                bottomStart = 16.dp
                            )
                        )
                        .clickable { onCopy(message.message) }
                ) {
                    Text(
                        text = message.message,
                        color = IconDarkColor,
                        fontSize = 14.sp,
                        lineHeight = 20.sp,
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)
                    )
                }

                Spacer(modifier = Modifier.height(3.dp))

                // Timestamp & Checkmarks
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(end = 4.dp)
                ) {
                    Text(
                        text = timeFormatted,
                        fontSize = 11.sp,
                        color = TimestampMuted
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Icon(
                        imageVector = Icons.Default.DoneAll,
                        contentDescription = "Delivered",
                        tint = BrandGoldAmber,
                        modifier = Modifier.size(15.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.width(8.dp))

            // User initial avatar badge
            Box(
                modifier = Modifier
                    .size(28.dp)
                    .clip(CircleShape)
                    .background(BrandGoldYellow),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = userInitial,
                    color = IconDarkColor,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    } else {
        // Incoming Support Bubble (Left side)
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(end = 40.dp),
            horizontalArrangement = Arrangement.Start,
            verticalAlignment = Alignment.Top
        ) {
            // Headset avatar in yellow badge
            Box(
                modifier = Modifier
                    .size(28.dp)
                    .clip(CircleShape)
                    .background(if (isDark) Color(0xFF383214) else Color(0xFFFEE89E)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.HeadsetMic,
                    contentDescription = "Support",
                    tint = if (isDark) BrandGoldYellow else IconDarkColor,
                    modifier = Modifier.size(16.dp)
                )
            }

            Spacer(modifier = Modifier.width(8.dp))

            Column(
                horizontalAlignment = Alignment.Start,
                modifier = Modifier.weight(1f, fill = false)
            ) {
                Surface(
                    shape = RoundedCornerShape(
                        topStart = 4.dp,
                        topEnd = 16.dp,
                        bottomEnd = 16.dp,
                        bottomStart = 16.dp
                    ),
                    color = if (isDark) SupportBubbleBgDark else SupportBubbleBgLight,
                    modifier = Modifier
                        .clip(
                            RoundedCornerShape(
                                topStart = 4.dp,
                                topEnd = 16.dp,
                                bottomEnd = 16.dp,
                                bottomStart = 16.dp
                            )
                        )
                        .clickable { onCopy(message.message) }
                ) {
                    Column(
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)
                    ) {
                        Text(
                            text = "SwapnoPay Support",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (isDark) BrandGoldYellow else TextDarkPrimary,
                            modifier = Modifier.padding(bottom = 2.dp)
                        )
                        Text(
                            text = message.message,
                            color = if (isDark) Color.White else TextDarkPrimary,
                            fontSize = 14.sp,
                            lineHeight = 20.sp
                        )
                    }
                }

                Spacer(modifier = Modifier.height(3.dp))

                Text(
                    text = timeFormatted,
                    fontSize = 11.sp,
                    color = TimestampMuted,
                    modifier = Modifier.padding(start = 4.dp)
                )
            }
        }
    }
}

/**
 * Chat History Side Navigation Drawer
 * Displays past conversation sessions, support tickets, and quick support options.
 */
@Composable
private fun ChatHistorySideDrawer(
    savedSessions: List<AppViewModel.SupportChatSession>,
    currentSessionId: String,
    currentChatMessages: List<AppViewModel.SupportChatMessage>,
    myTickets: List<AppViewModel.SupportTicket>,
    isDark: Boolean,
    onClose: () -> Unit,
    onNewChat: () -> Unit,
    onSelectSession: (String) -> Unit,
    onDeleteSession: (String) -> Unit,
    onClearAllSessions: () -> Unit,
    onOpenFaq: () -> Unit,
    onCallHelpline: () -> Unit,
    onEmailSupport: () -> Unit,
    onCopyTranscript: () -> Unit,
    onClearCurrentChat: () -> Unit
) {
    val drawerBg = if (isDark) Color(0xFF131824) else Color.White
    val cardBg = if (isDark) Color(0xFF1C2233) else Color(0xFFF8FAFC)
    val cardBorder = if (isDark) Color(0xFF2C3549) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else TextDarkPrimary
    val textSecondary = if (isDark) Color(0xFF94A3B8) else TextMutedSecondary

    var selectedTab by remember { mutableIntStateOf(0) } // 0: Chats, 1: Tickets, 2: Support
    var historySearch by remember { mutableStateOf("") }

    val allSessions = remember(savedSessions, currentSessionId, currentChatMessages) {
        val list = savedSessions.toMutableList()
        if (currentChatMessages.isNotEmpty() && list.none { it.id == currentSessionId }) {
            val firstMsg = currentChatMessages.firstOrNull { it.sender.equals("MERCHANT", ignoreCase = true) || it.sender.equals("USER", ignoreCase = true) }?.message?.trim()
                ?: currentChatMessages.firstOrNull()?.message?.trim() ?: "Support Chat"
            val title = if (firstMsg.length > 45) firstMsg.take(42) + "..." else firstMsg
            list.add(0, AppViewModel.SupportChatSession(
                id = currentSessionId,
                title = title,
                timestamp = currentChatMessages.lastOrNull()?.timestamp ?: System.currentTimeMillis(),
                messages = currentChatMessages,
                status = "ACTIVE"
            ))
        }
        list
    }

    val filteredSessions = remember(allSessions, historySearch) {
        if (historySearch.isBlank()) allSessions
        else allSessions.filter { session ->
            session.title.contains(historySearch, ignoreCase = true) ||
                session.messages.any { it.message.contains(historySearch, ignoreCase = true) }
        }
    }

    val filteredTickets = remember(myTickets, historySearch) {
        if (historySearch.isBlank()) myTickets
        else myTickets.filter { ticket ->
            ticket.subject.contains(historySearch, ignoreCase = true) ||
                ticket.description.contains(historySearch, ignoreCase = true) ||
                ticket.category.contains(historySearch, ignoreCase = true)
        }
    }

    Surface(
        modifier = Modifier
            .fillMaxHeight()
            .widthIn(max = 340.dp)
            .fillMaxWidth(0.85f),
        color = drawerBg,
        shadowElevation = 24.dp,
        shape = RoundedCornerShape(topStart = 20.dp, bottomStart = 20.dp),
        border = BorderStroke(1.dp, cardBorder)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .statusBarsPadding()
                .navigationBarsPadding()
                .padding(horizontal = 16.dp, vertical = 14.dp)
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(38.dp)
                            .clip(CircleShape)
                            .background(BrandGoldYellow),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.History,
                            contentDescription = null,
                            tint = IconDarkColor,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                    Column {
                        Text(
                            text = "Chat History",
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary
                        )
                        Text(
                            text = "Past conversations & tickets",
                            fontSize = 11.5.sp,
                            color = textSecondary
                        )
                    }
                }

                IconButton(
                    onClick = onClose,
                    modifier = Modifier.size(34.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Close,
                        contentDescription = "Close",
                        tint = textSecondary,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.height(14.dp))

            // "+ Start New Chat" Button
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = BrandGoldYellow,
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .clickable { onNewChat() }
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Add,
                        contentDescription = null,
                        tint = IconDarkColor,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "Start New Chat",
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = IconDarkColor
                    )
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            // Search Filter
            OutlinedTextField(
                value = historySearch,
                onValueChange = { historySearch = it },
                placeholder = { Text("Search history...", fontSize = 12.5.sp, color = textSecondary) },
                leadingIcon = {
                    Icon(
                        imageVector = Icons.Default.Search,
                        contentDescription = null,
                        tint = textSecondary,
                        modifier = Modifier.size(17.dp)
                    )
                },
                trailingIcon = {
                    if (historySearch.isNotEmpty()) {
                        IconButton(
                            onClick = { historySearch = "" },
                            modifier = Modifier.size(24.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.Clear,
                                contentDescription = null,
                                tint = textSecondary,
                                modifier = Modifier.size(15.dp)
                            )
                        }
                    }
                },
                singleLine = true,
                shape = RoundedCornerShape(10.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = BrandGoldYellow,
                    unfocusedBorderColor = cardBorder
                ),
                modifier = Modifier.fillMaxWidth()
            )

            Spacer(modifier = Modifier.height(12.dp))

            // Tab Row (Chats / Tickets / Support)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(cardBg, RoundedCornerShape(10.dp))
                    .padding(3.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                listOf(
                    "Chats (${allSessions.size})",
                    "Tickets (${myTickets.size})",
                    "Support"
                ).forEachIndexed { idx, label ->
                    val isSelected = selectedTab == idx
                    Surface(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(8.dp))
                            .clickable { selectedTab = idx },
                        color = if (isSelected) BrandGoldYellow else Color.Transparent,
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Text(
                            text = label,
                            fontSize = 11.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                            color = if (isSelected) IconDarkColor else textSecondary,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.padding(vertical = 7.dp)
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            // Tab Content
            Box(modifier = Modifier.weight(1f)) {
                when (selectedTab) {
                    0 -> {
                        // CHATS TAB
                        if (filteredSessions.isEmpty()) {
                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .padding(24.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                    Icon(
                                        imageVector = Icons.Default.ChatBubbleOutline,
                                        contentDescription = null,
                                        tint = textSecondary,
                                        modifier = Modifier.size(36.dp)
                                    )
                                    Spacer(modifier = Modifier.height(10.dp))
                                    Text(
                                        text = if (historySearch.isNotBlank()) "No matching conversations" else "No saved conversations yet",
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = textPrimary
                                    )
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text(
                                        text = "Messages are automatically saved to your history.",
                                        fontSize = 11.5.sp,
                                        color = textSecondary,
                                        textAlign = TextAlign.Center
                                    )
                                }
                            }
                        } else {
                            LazyColumn(
                                modifier = Modifier.fillMaxSize(),
                                verticalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                items(filteredSessions, key = { it.id }) { session ->
                                    val isActive = session.id == currentSessionId
                                    val dateStr = remember(session.timestamp) {
                                        val sdf = SimpleDateFormat("dd MMM, hh:mm a", Locale.getDefault())
                                        sdf.format(Date(session.timestamp))
                                    }

                                    Card(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(12.dp))
                                            .clickable { onSelectSession(session.id) },
                                        shape = RoundedCornerShape(12.dp),
                                        colors = CardDefaults.cardColors(
                                            containerColor = if (isActive) {
                                                if (isDark) Color(0xFF262B3D) else Color(0xFFFFF9E6)
                                            } else cardBg
                                        ),
                                        border = BorderStroke(
                                            if (isActive) 1.5.dp else 1.dp,
                                            if (isActive) BrandGoldYellow else cardBorder
                                        )
                                    ) {
                                        Row(
                                            modifier = Modifier.padding(10.dp),
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Box(
                                                modifier = Modifier
                                                    .size(32.dp)
                                                    .clip(CircleShape)
                                                    .background(
                                                        if (isActive) BrandGoldYellow
                                                        else (if (isDark) Color(0xFF2E384D) else Color(0xFFE2E8F0))
                                                    ),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.ChatBubbleOutline,
                                                    contentDescription = null,
                                                    tint = if (isActive) IconDarkColor else textSecondary,
                                                    modifier = Modifier.size(16.dp)
                                                )
                                            }

                                            Spacer(modifier = Modifier.width(10.dp))

                                            Column(modifier = Modifier.weight(1f)) {
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                                ) {
                                                    Text(
                                                        text = session.title.ifBlank { "Support Conversation" },
                                                        fontSize = 12.5.sp,
                                                        fontWeight = FontWeight.SemiBold,
                                                        color = textPrimary,
                                                        maxLines = 1,
                                                        overflow = TextOverflow.Ellipsis,
                                                        modifier = Modifier.weight(1f, fill = false)
                                                    )
                                                    if (isActive) {
                                                        Surface(
                                                            shape = RoundedCornerShape(4.dp),
                                                            color = BrandGoldYellow
                                                        ) {
                                                            Text(
                                                                text = "Active",
                                                                fontSize = 9.5.sp,
                                                                fontWeight = FontWeight.Bold,
                                                                color = IconDarkColor,
                                                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                            )
                                                        }
                                                    }
                                                }

                                                Spacer(modifier = Modifier.height(3.dp))

                                                Text(
                                                    text = "$dateStr • ${session.messages.size} msgs",
                                                    fontSize = 11.sp,
                                                    color = textSecondary
                                                )
                                            }

                                            IconButton(
                                                onClick = { onDeleteSession(session.id) },
                                                modifier = Modifier.size(28.dp)
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.DeleteOutline,
                                                    contentDescription = "Delete",
                                                    tint = textSecondary,
                                                    modifier = Modifier.size(16.dp)
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    1 -> {
                        // TICKETS TAB
                        if (filteredTickets.isEmpty()) {
                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .padding(24.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                    Icon(
                                        imageVector = Icons.Default.Description,
                                        contentDescription = null,
                                        tint = textSecondary,
                                        modifier = Modifier.size(36.dp)
                                    )
                                    Spacer(modifier = Modifier.height(10.dp))
                                    Text(
                                        text = if (historySearch.isNotBlank()) "No matching tickets" else "No support tickets",
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = textPrimary
                                    )
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text(
                                        text = "Your platform tickets and disputes will appear here.",
                                        fontSize = 11.5.sp,
                                        color = textSecondary,
                                        textAlign = TextAlign.Center
                                    )
                                }
                            }
                        } else {
                            LazyColumn(
                                modifier = Modifier.fillMaxSize(),
                                verticalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                items(filteredTickets, key = { it.id }) { ticket ->
                                    val dateStr = remember(ticket.createdAt) {
                                        val sdf = SimpleDateFormat("dd MMM, hh:mm a", Locale.getDefault())
                                        sdf.format(Date(ticket.createdAt))
                                    }
                                    val statusColor = when (ticket.status.uppercase(Locale.getDefault())) {
                                        "OPEN" -> BrandGoldAmber
                                        "RESOLVED" -> OnlineGreen
                                        else -> textSecondary
                                    }

                                    Card(
                                        modifier = Modifier.fillMaxWidth(),
                                        shape = RoundedCornerShape(12.dp),
                                        colors = CardDefaults.cardColors(containerColor = cardBg),
                                        border = BorderStroke(1.dp, cardBorder)
                                    ) {
                                        Column(modifier = Modifier.padding(12.dp)) {
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                Text(
                                                    text = ticket.category.ifBlank { "General Support" },
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = BrandGoldAmber
                                                )
                                                Surface(
                                                    shape = RoundedCornerShape(4.dp),
                                                    color = statusColor.copy(alpha = 0.15f),
                                                    border = BorderStroke(0.8.dp, statusColor)
                                                ) {
                                                    Text(
                                                        text = ticket.status.uppercase(Locale.getDefault()),
                                                        fontSize = 9.5.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = statusColor,
                                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                                    )
                                                }
                                            }

                                            Spacer(modifier = Modifier.height(4.dp))

                                            Text(
                                                text = ticket.subject.ifBlank { ticket.description.take(40) },
                                                fontSize = 12.5.sp,
                                                fontWeight = FontWeight.SemiBold,
                                                color = textPrimary,
                                                maxLines = 2,
                                                overflow = TextOverflow.Ellipsis
                                            )

                                            if (ticket.adminReply.isNotBlank()) {
                                                Spacer(modifier = Modifier.height(6.dp))
                                                Surface(
                                                    shape = RoundedCornerShape(6.dp),
                                                    color = if (isDark) Color(0xFF162030) else Color(0xFFEFF6FF),
                                                    modifier = Modifier.fillMaxWidth()
                                                ) {
                                                    Row(
                                                        modifier = Modifier.padding(6.dp),
                                                        verticalAlignment = Alignment.Top
                                                    ) {
                                                        Text(
                                                            text = "Admin: ${ticket.adminReply}",
                                                            fontSize = 11.sp,
                                                            color = if (isDark) Color(0xFF93C5FD) else Color(0xFF1D4ED8),
                                                            maxLines = 2,
                                                            overflow = TextOverflow.Ellipsis
                                                        )
                                                    }
                                                }
                                            }

                                            Spacer(modifier = Modifier.height(4.dp))

                                            Text(
                                                text = dateStr,
                                                fontSize = 10.5.sp,
                                                color = textSecondary
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }

                    2 -> {
                        // QUICK SUPPORT ACTIONS
                        Column(
                            modifier = Modifier.fillMaxSize(),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            // Call Helpline
                            DrawerActionCard(
                                title = "Call Support Helpline",
                                subtitle = "24/7 dedicated merchant assistance",
                                icon = Icons.Default.Phone,
                                iconBg = BrandGoldYellow,
                                iconTint = IconDarkColor,
                                cardBg = cardBg,
                                cardBorder = cardBorder,
                                textPrimary = textPrimary,
                                textSecondary = textSecondary,
                                onClick = onCallHelpline
                            )

                            // Email Support
                            DrawerActionCard(
                                title = "Copy Support Email",
                                subtitle = "support@swapnopay.top",
                                icon = Icons.Default.Email,
                                iconBg = BrandGoldYellow,
                                iconTint = IconDarkColor,
                                cardBg = cardBg,
                                cardBorder = cardBorder,
                                textPrimary = textPrimary,
                                textSecondary = textSecondary,
                                onClick = onEmailSupport
                            )

                            // FAQs
                            DrawerActionCard(
                                title = "Knowledgebase FAQs",
                                subtitle = "Frequently asked questions & guides",
                                icon = Icons.Default.HelpOutline,
                                iconBg = BrandGoldYellow,
                                iconTint = IconDarkColor,
                                cardBg = cardBg,
                                cardBorder = cardBorder,
                                textPrimary = textPrimary,
                                textSecondary = textSecondary,
                                onClick = onOpenFaq
                            )

                            // Copy Transcript
                            DrawerActionCard(
                                title = "Copy Chat Transcript",
                                subtitle = "Save conversation to clipboard",
                                icon = Icons.Default.ContentCopy,
                                iconBg = if (isDark) Color(0xFF2E384D) else Color(0xFFE2E8F0),
                                iconTint = textPrimary,
                                cardBg = cardBg,
                                cardBorder = cardBorder,
                                textPrimary = textPrimary,
                                textSecondary = textSecondary,
                                onClick = onCopyTranscript
                            )

                            // Clear Current Chat
                            DrawerActionCard(
                                title = "Clear Current Chat",
                                subtitle = "Remove messages on screen",
                                icon = Icons.Default.DeleteOutline,
                                iconBg = Color(0xFFEF4444).copy(alpha = 0.15f),
                                iconTint = Color(0xFFEF4444),
                                cardBg = cardBg,
                                cardBorder = cardBorder,
                                textPrimary = Color(0xFFEF4444),
                                textSecondary = textSecondary,
                                onClick = onClearCurrentChat
                            )

                            // Clear All History
                            DrawerActionCard(
                                title = "Clear All History",
                                subtitle = "Delete all saved conversation sessions",
                                icon = Icons.Default.DeleteForever,
                                iconBg = Color(0xFFEF4444).copy(alpha = 0.15f),
                                iconTint = Color(0xFFEF4444),
                                cardBg = cardBg,
                                cardBorder = cardBorder,
                                textPrimary = Color(0xFFEF4444),
                                textSecondary = textSecondary,
                                onClick = onClearAllSessions
                            )
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(10.dp))
            HorizontalDivider(color = cardBorder, thickness = 0.8.dp)
            Spacer(modifier = Modifier.height(8.dp))

            // Footer
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.Center
            ) {
                Box(
                    modifier = Modifier
                        .size(6.dp)
                        .clip(CircleShape)
                        .background(OnlineGreen)
                )
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    text = "SwapnoPay Support Desk • 24/7 Active",
                    fontSize = 11.sp,
                    color = textSecondary,
                    fontWeight = FontWeight.Medium
                )
            }
        }
    }
}

@Composable
private fun DrawerActionCard(
    title: String,
    subtitle: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    iconBg: Color,
    iconTint: Color,
    cardBg: Color,
    cardBorder: Color,
    textPrimary: Color,
    textSecondary: Color,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .clickable { onClick() },
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(34.dp)
                    .clip(CircleShape)
                    .background(iconBg),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = iconTint,
                    modifier = Modifier.size(17.dp)
                )
            }
            Spacer(modifier = Modifier.width(10.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = textPrimary
                )
                Text(
                    text = subtitle,
                    fontSize = 10.5.sp,
                    color = textSecondary
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
 * - Golden Yellow circular send button (#FBC740)
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
                .padding(start = 6.dp, end = 6.dp, top = 4.dp, bottom = 4.dp),
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
                    modifier = Modifier.size(20.dp)
                )
            }

            // Circular Golden Yellow Send Button (#FBC740)
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
                    tint = IconDarkColor,
                    modifier = Modifier.size(19.dp)
                )
            }
        }
    }
}

/**
 * FAQs Knowledgebase Bottom Sheet Modal
 * Displays real FAQs from CMS remote config (no fake data)
 * Allows tapping any question to ask it directly in chat.
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
        title = "Help & FAQs",
        subtitle = "Tap any question to ask directly in chat",
        icon = Icons.Default.HelpOutline
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 12.dp)
        ) {
            OutlinedTextField(
                value = searchFaq,
                onValueChange = { searchFaq = it },
                placeholder = { Text("Search FAQ topics...", fontSize = 13.5.sp) },
                leadingIcon = { Icon(Icons.Default.Search, null, tint = BrandGoldAmber) },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = BrandGoldYellow,
                    unfocusedBorderColor = if (isDark) Color(0xFF333A48) else Color(0xFFE2E8F0)
                ),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 12.dp)
            )

            if (filtered.isEmpty()) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 32.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = if (searchFaq.isNotBlank()) "No matching FAQs found" else "No FAQs published yet.",
                        fontSize = 13.sp,
                        color = if (isDark) Color(0xFF94A3B8) else TextMutedSecondary
                    )
                }
            } else {
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
