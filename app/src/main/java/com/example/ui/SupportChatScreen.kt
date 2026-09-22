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

    // Auto-scroll to bottom when new messages arrive
    LaunchedEffect(chatList.size) {
        if (chatList.isNotEmpty()) {
            listState.animateScrollToItem(chatList.size - 1)
        }
    }

    val filteredMessages = remember(chatList, searchQuery) {
        if (searchQuery.isBlank()) chatList
        else chatList.filter { it.message.contains(searchQuery, ignoreCase = true) }
    }

    Scaffold(
        containerColor = if (isDark) Color(0xFF0F1117) else Color.White,
        topBar = {
            ChatTopAppBar(
                isDark = isDark,
                isSearchActive = isSearchActive,
                searchQuery = searchQuery,
                showMenu = showMenu,
                onBack = { viewModel.goBack() },
                onToggleSearch = {
                    isSearchActive = !isSearchActive
                    if (!isSearchActive) searchQuery = ""
                },
                onSearchChange = { searchQuery = it },
                onToggleMenu = { showMenu = !showMenu },
                onMenuDismiss = { showMenu = false },
                onOpenFaq = {
                    showMenu = false
                    showFaqModal = true
                },
                onCallHelpline = {
                    showMenu = false
                    val helpline = remoteConfig.supportHelpline.ifBlank { "+8801700000000" }
                    val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$helpline"))
                    runCatching { context.startActivity(intent) }
                },
                onEmailSupport = {
                    showMenu = false
                    val email = remoteConfig.supportEmail.ifBlank { "support@swapnopay.top" }
                    clipboardManager.setText(AnnotatedString(email))
                    Toast.makeText(context, "Support email copied: $email", Toast.LENGTH_SHORT).show()
                },
                onClearChat = {
                    showMenu = false
                    viewModel.clearSupportChat()
                    Toast.makeText(context, "Chat cleared", Toast.LENGTH_SHORT).show()
                }
            )
        },
        bottomBar = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(if (isDark) Color(0xFF0F1117) else Color.White)
            ) {
                // 4 Target Quick Action Chips
                ChatQuickActionChips(
                    onChipClick = { promptText ->
                        viewModel.sendSupportChatMessage(promptText)
                    },
                    isDark = isDark
                )

                // Pill Input Bar
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
        }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .background(if (isDark) Color(0xFF0F1117) else Color.White)
        ) {
            // "Need quick help?" Banner Card
            QuickHelpBannerCard(
                onViewFaqs = { showFaqModal = true },
                isDark = isDark
            )

            // Conversation Messages Feed
            if (filteredMessages.isEmpty()) {
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
                            text = if (searchQuery.isNotBlank()) "No messages match '$searchQuery'" else "How can we help you today?",
                            fontSize = 15.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = if (isDark) Color.White else TextDarkPrimary
                        )
                        Spacer(modifier = Modifier.height(6.dp))
                        Text(
                            text = if (searchQuery.isNotBlank()) "Try searching for a different keyword" else "Send a message or tap one of the quick actions below to reach out to our team.",
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
                    items(filteredMessages, key = { it.id }) { message ->
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
 * Pure pixel-perfect match:
 * - Back button
 * - Circular Golden Yellow Avatar ('S')
 * - 3-Line title block: SwapnoPay / Support Chat / ● Online
 * - Action buttons: Search and 3-dots Menu
 */
@Composable
private fun ChatTopAppBar(
    isDark: Boolean,
    isSearchActive: Boolean,
    searchQuery: String,
    showMenu: Boolean,
    onBack: () -> Unit,
    onToggleSearch: () -> Unit,
    onSearchChange: (String) -> Unit,
    onToggleMenu: () -> Unit,
    onMenuDismiss: () -> Unit,
    onOpenFaq: () -> Unit,
    onCallHelpline: () -> Unit,
    onEmailSupport: () -> Unit,
    onClearChat: () -> Unit
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

                // Search Icon
                IconButton(
                    onClick = onToggleSearch,
                    modifier = Modifier.size(40.dp)
                ) {
                    Icon(
                        imageVector = if (isSearchActive) Icons.Default.Close else Icons.Default.Search,
                        contentDescription = "Search",
                        tint = if (isDark) Color.White else IconDarkColor,
                        modifier = Modifier.size(22.dp)
                    )
                }

                // Overflow 3-dots Menu
                Box {
                    IconButton(
                        onClick = onToggleMenu,
                        modifier = Modifier.size(40.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.MoreVert,
                            contentDescription = "Menu",
                            tint = if (isDark) Color.White else IconDarkColor,
                            modifier = Modifier.size(22.dp)
                        )
                    }

                    DropdownMenu(
                        expanded = showMenu,
                        onDismissRequest = onMenuDismiss,
                        modifier = Modifier.background(if (isDark) Color(0xFF1E2430) else Color.White)
                    ) {
                        DropdownMenuItem(
                            text = { Text("View FAQs & Guides", fontSize = 14.sp) },
                            leadingIcon = { Icon(Icons.Default.HelpOutline, null, tint = BrandGoldAmber) },
                            onClick = onOpenFaq
                        )
                        DropdownMenuItem(
                            text = { Text("Call Support Helpline", fontSize = 14.sp) },
                            leadingIcon = { Icon(Icons.Default.Phone, null, tint = BrandGoldAmber) },
                            onClick = onCallHelpline
                        )
                        DropdownMenuItem(
                            text = { Text("Copy Support Email", fontSize = 14.sp) },
                            leadingIcon = { Icon(Icons.Default.Email, null, tint = BrandGoldAmber) },
                            onClick = onEmailSupport
                        )
                        HorizontalDivider(color = if (isDark) Color(0xFF333A48) else Color(0xFFE2E8F0))
                        DropdownMenuItem(
                            text = { Text("Clear Chat", fontSize = 14.sp, color = MaterialTheme.colorScheme.error) },
                            leadingIcon = { Icon(Icons.Default.DeleteOutline, null, tint = MaterialTheme.colorScheme.error) },
                            onClick = onClearChat
                        )
                    }
                }
            }

            // In-Chat Search Bar expansion
            AnimatedVisibility(
                visible = isSearchActive,
                enter = expandVertically() + fadeIn(),
                exit = shrinkVertically() + fadeOut()
            ) {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = onSearchChange,
                    placeholder = { Text("Search messages...", fontSize = 13.5.sp) },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = BrandGoldYellow,
                        unfocusedBorderColor = if (isDark) Color(0xFF333A48) else Color(0xFFE2E8F0)
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 14.dp, vertical = 6.dp)
                )
            }
        }
    }
}

/**
 * "Need quick help?" Banner Card
 * Golden cream background (#FFFBF0) with amber border (#FDE68A)
 * Black headset inside circular golden badge (#FEE89E)
 * "View FAQs >" golden rounded pill button
 */
@Composable
private fun QuickHelpBannerCard(
    onViewFaqs: () -> Unit,
    isDark: Boolean
) {
    Card(
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(
            containerColor = if (isDark) Color(0xFF1E222D) else Color(0xFFFFFBF0)
        ),
        border = BorderStroke(1.dp, if (isDark) Color(0xFF3D3522) else Color(0xFFFDE68A)),
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Yellow circular badge with black headset
            Box(
                modifier = Modifier
                    .size(38.dp)
                    .clip(CircleShape)
                    .background(if (isDark) Color(0xFF383214) else Color(0xFFFEE89E)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.HeadsetMic,
                    contentDescription = null,
                    tint = if (isDark) BrandGoldYellow else IconDarkColor,
                    modifier = Modifier.size(20.dp)
                )
            }

            Spacer(modifier = Modifier.width(12.dp))

            Column(
                modifier = Modifier.weight(1f)
            ) {
                Text(
                    text = "Need quick help?",
                    fontSize = 14.5.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (isDark) Color.White else TextDarkPrimary
                )
                Text(
                    text = "Our support team is here 24/7",
                    fontSize = 12.sp,
                    color = if (isDark) Color(0xFF94A3B8) else TextMutedSecondary
                )
            }

            Spacer(modifier = Modifier.width(8.dp))

            // "View FAQs >" Pill Button
            Surface(
                shape = RoundedCornerShape(20.dp),
                color = if (isDark) Color(0xFF2C2E38) else Color.White,
                border = BorderStroke(1.dp, if (isDark) Color(0xFF3D3522) else Color(0xFFFDE68A)),
                modifier = Modifier
                    .clip(RoundedCornerShape(20.dp))
                    .clickable { onViewFaqs() }
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                ) {
                    Text(
                        text = "View FAQs >",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (isDark) BrandGoldYellow else BrandGoldAmber
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
 * 4 Target Quick Action Chips
 * 1. Change Booking
 * 2. Cancel Booking
 * 3. Refund Status
 * 4. Other Help
 * Each chip has a circular golden badge containing a dark icon.
 */
@Composable
private fun ChatQuickActionChips(
    onChipClick: (String) -> Unit,
    isDark: Boolean
) {
    val chipBg = if (isDark) Color(0xFF191D26) else Color(0xFFF8FAFC)
    val chipBorder = if (isDark) Color(0xFF2C3240) else Color(0xFFE2E8F0)
    val badgeBg = if (isDark) Color(0xFF383214) else Color(0xFFFEE89E)
    val iconTint = if (isDark) BrandGoldYellow else IconDarkColor
    val textColor = if (isDark) Color.White else TextDarkPrimary

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 14.dp, vertical = 6.dp),
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
                        imageVector = Icons.Default.CalendarMonth,
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
