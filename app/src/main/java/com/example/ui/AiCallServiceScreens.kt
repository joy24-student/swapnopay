@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
package com.example.ui

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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.horizontalScroll
import android.content.Intent
import android.net.Uri
import android.speech.RecognizerIntent
import android.speech.tts.TextToSpeech
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import java.util.Locale
import android.widget.Toast

// ── Models for AI Call Service ──
data class AiCallRecord(
    val id: String,
    val merchantId: String = "default",
    val direction: String = "inbound", // "inbound" | "outbound"
    val from: String = "",
    val to: String = "",
    val customerName: String = "",
    val purpose: String = "",
    val status: String = "completed",
    val duration: String = "0m 45s",
    val createdAt: String = "",
    val summary: String = "",
    val transcript: List<AiCallTurn> = emptyList()
)

data class AiCallTurn(
    val role: String, // "assistant" | "customer"
    val text: String,
    val time: String = ""
)

data class AiCampaignFeedbackItem(
    val id: String,
    val customerName: String = "",
    val customerPhone: String = "",
    val campaignTitle: String = "",
    val campaignType: String = "GENERAL", // "MEETING_INVITE" | "DISCOUNT_OFFER" | "GENERAL"
    val decision: String = "PENDING",     // "ATTENDING" | "INTERESTED" | "DECLINED" | "PENDING"
    val feedbackText: String = "",
    val sentiment: String = "NEUTRAL",    // "POSITIVE" | "NEUTRAL" | "NEGATIVE"
    val callStatus: String = "COMPLETED", // "COMPLETED" | "PENDING" | "FAILED"
    val callDuration: String = "0m 45s",
    val createdAt: String = ""
)

data class AiVoiceSettingsState(
    val agentName: String = "তানিয়া (Tania)",
    val language: String = "bn-BD",
    val voiceGender: String = "female",
    val autoAnswer: Boolean = true,
    val businessName: String = "স্বপ্নপে স্টোর",
    val greetingBn: String = "আসসালামু আলাইকুম! স্বপ্নপে কাস্টমার কেয়ারে আপনাকে স্বাগতম। আমি আপনার এআই প্রতিনিধি। আজ আপনাকে কীভাবে সাহায্য করতে পারি?",
    val dueReminderScript: String = "আসসালামু আলাইকুম {customer_name}, {business_name} থেকে বলছি। আপনার {due_amount} টাকা বকেয়া রয়েছে। আপনি কি আগামীকালের মধ্যে পরিশোধ করতে পারবেন?",
    val callerNumber: String = "+8809612345678"
)

// ══════════════════════════════════════════════════════════════════════════════
// Main AI Call Center Hub Screen
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun AiCallCenterScreen(viewModel: AppViewModel) {
    val context = LocalContext.current
    val isDarkMode by viewModel.isDarkMode.collectAsState()
    val language by viewModel.language.collectAsState()
    val isBangla = language == "Bangla"

    val callLogs by viewModel.aiCallLogs.collectAsState()
    val campaignFeedbacks by viewModel.aiCampaignFeedbacks.collectAsState()
    val voiceSettings by viewModel.aiVoiceSettings.collectAsState()
    val isActionLoading by viewModel.isTriggeringAiCall.collectAsState()

    // Dialog & Navigation states
    var showDueCallDialog by remember { mutableStateOf(false) }
    var showOrderCallDialog by remember { mutableStateOf(false) }
    var showTestCallDialog by remember { mutableStateOf(false) }
    var showInstantVoiceRecordDialog by remember { mutableStateOf(false) }
    var showCampaignDialog by remember { mutableStateOf(false) }

    // Feature Dialog States matching the 4 Configuration cards & Details
    var showNumberConfigDialog by remember { mutableStateOf(false) }
    var showAgentConfigDialog by remember { mutableStateOf(false) }
    var showRoutingConfigDialog by remember { mutableStateOf(false) }
    var showScriptConfigDialog by remember { mutableStateOf(false) }
    var showAnalyticsDetailsDialog by remember { mutableStateOf(false) }
    var showCallRecordsDialog by remember { mutableStateOf(false) }
    var showSettingsFullDialog by remember { mutableStateOf(false) }
    var showQuickActionDialog by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        viewModel.fetchAiCallLogs()
        viewModel.fetchCampaignFeedbacks()
        viewModel.fetchAiVoiceSettings()
    }

    val bgColor = if (isDarkMode) Color(0xFF0F1117) else Color(0xFFF8F9FA)
    val cardBg = if (isDarkMode) Color(0xFF161B26) else Color.White
    val cardBorder = if (isDarkMode) Color(0xFF262F40) else Color(0xFFE2E8F0)
    val textPrimary = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDarkMode) Color(0xFF94A3B8) else Color(0xFF64748B)

    val totalCalls = callLogs.size
    val inboundCalls = callLogs.count { it.direction == "inbound" }
    val outboundCalls = callLogs.count { it.direction == "outbound" }
    val avgCallDuration = if (callLogs.isEmpty()) "0s" else "45s"

    Scaffold(
        containerColor = bgColor,
        bottomBar = {
            // ── Pixel-Perfect Bottom Navigation Bar ──
            Surface(
                modifier = Modifier.fillMaxWidth(),
                color = if (isDarkMode) Color(0xFF111319) else Color.White,
                border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF262F40) else Color(0xFFF0F0F2))
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .height(62.dp)
                        .padding(horizontal = 12.dp),
                    horizontalArrangement = Arrangement.SpaceAround,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // 1. Home
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier
                            .clickable {
                                viewModel.navigateTo("Main")
                                viewModel.setTab("Dashboard")
                            }
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Home,
                            contentDescription = "Home",
                            tint = textSecondary,
                            modifier = Modifier.size(22.dp)
                        )
                        Spacer(modifier = Modifier.height(2.dp))
                        Text("Home", fontSize = 10.5.sp, color = textSecondary)
                    }

                    // 2. Payments
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier
                            .clickable { viewModel.navigateTo("Transactions") }
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.CreditCard,
                            contentDescription = "Payments",
                            tint = textSecondary,
                            modifier = Modifier.size(22.dp)
                        )
                        Spacer(modifier = Modifier.height(2.dp))
                        Text("Payments", fontSize = 10.5.sp, color = textSecondary)
                    }

                    // 3. Center (+) Action FAB
                    Box(
                        modifier = Modifier
                            .size(46.dp)
                            .shadow(6.dp, CircleShape, ambientColor = Color(0xFFF59E0B), spotColor = Color(0xFFF59E0B))
                            .clip(CircleShape)
                            .background(Brush.linearGradient(listOf(Color(0xFFF5C054), Color(0xFFD97706))))
                            .clickable { showQuickActionDialog = true },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Add,
                            contentDescription = "New Call",
                            tint = Color(0xFF0F172A),
                            modifier = Modifier.size(26.dp)
                        )
                    }

                    // 4. AI Voice (Selected Active Tab)
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Headset,
                            contentDescription = "AI Voice",
                            tint = Color(0xFFF59E0B),
                            modifier = Modifier.size(22.dp)
                        )
                        Spacer(modifier = Modifier.height(2.dp))
                        Text(
                            text = "AI Voice",
                            fontSize = 10.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color(0xFFF59E0B)
                        )
                    }

                    // 5. Profile
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier
                            .clickable { viewModel.navigateTo("More") }
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.PersonOutline,
                            contentDescription = "Profile",
                            tint = textSecondary,
                            modifier = Modifier.size(22.dp)
                        )
                        Spacer(modifier = Modifier.height(2.dp))
                        Text("Profile", fontSize = 10.5.sp, color = textSecondary)
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
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            item { Spacer(modifier = Modifier.height(2.dp)) }

            // ── Top Bar: Back arrow, Title & Refresh Icon ──
            item {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 4.dp, bottom = 4.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        IconButton(
                            onClick = { viewModel.goBack() },
                            modifier = Modifier.size(36.dp)
                        ) {
                            Icon(
                                imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                                contentDescription = "Back",
                                tint = textPrimary,
                                modifier = Modifier.size(22.dp)
                            )
                        }

                        Spacer(modifier = Modifier.width(6.dp))

                        Column {
                            Text(
                                text = "এআই ভয়েস কল সেবা",
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold,
                                color = textPrimary
                            )
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(
                                text = "বাস্তবসম্মত ইনবাউন্ড, আউটবাউন্ড ও গণ কলসেন্টার",
                                fontSize = 12.sp,
                                color = textSecondary
                            )
                        }
                    }

                    IconButton(
                        onClick = {
                            viewModel.fetchAiCallLogs()
                            viewModel.fetchCampaignFeedbacks()
                            viewModel.fetchAiVoiceSettings()
                            Toast.makeText(context, "তথ্য সফলভাবে রিফ্রেশ করা হয়েছে", Toast.LENGTH_SHORT).show()
                        },
                        modifier = Modifier.size(36.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Refresh,
                            contentDescription = "Refresh",
                            tint = textPrimary,
                            modifier = Modifier.size(22.dp)
                        )
                    }
                }
            }

            // ── Top Hero Card: AI Voice Call Summary Card ──
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder),
                    elevation = CardDefaults.cardElevation(defaultElevation = 0.5.dp)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Left: Circular Phone Icon & Info
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.weight(1.1f)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(54.dp)
                                    .clip(CircleShape)
                                    .background(Color(0xFF707DF6)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Call,
                                    contentDescription = "Phone",
                                    tint = Color.White,
                                    modifier = Modifier.size(26.dp)
                                )
                            }

                            Spacer(modifier = Modifier.width(12.dp))

                            Column {
                                Text(
                                    text = "AI Voice Call",
                                    fontSize = 17.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "আপনার ব্যবসার জন্য স্মার্ট ভয়েস কল সলিউশন",
                                    fontSize = 11.5.sp,
                                    color = textSecondary,
                                    maxLines = 2,
                                    lineHeight = 15.sp
                                )
                                Spacer(modifier = Modifier.height(6.dp))
                                Surface(
                                    shape = RoundedCornerShape(12.dp),
                                    color = Color(0xFFDCFCE7)
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.5.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Box(
                                            modifier = Modifier
                                                .size(6.dp)
                                                .background(Color(0xFF10B981), CircleShape)
                                        )
                                        Spacer(modifier = Modifier.width(5.dp))
                                        Text(
                                            text = "সক্রিয়",
                                            color = Color(0xFF10B981),
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }
                                }
                            }
                        }

                        Spacer(modifier = Modifier.width(8.dp))

                        // Right: 3 Stats in a light rounded box
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = if (isDarkMode) Color(0xFF1E293B) else Color(0xFFF1F5F9)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 8.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                // মোট কল
                                Column(
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    modifier = Modifier.width(48.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Call,
                                        contentDescription = null,
                                        tint = Color(0xFF2563EB),
                                        modifier = Modifier.size(15.dp)
                                    )
                                    Spacer(modifier = Modifier.height(2.dp))
                                    Text("মোট কল", fontSize = 10.sp, color = textSecondary)
                                    Text(
                                        text = "$totalCalls",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 15.sp,
                                        color = textPrimary
                                    )
                                }

                                Box(
                                    modifier = Modifier
                                        .height(32.dp)
                                        .width(1.dp)
                                        .background(if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0))
                                )

                                // ইনবাউন্ড
                                Column(
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    modifier = Modifier.width(48.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.GraphicEq,
                                        contentDescription = null,
                                        tint = Color(0xFF2563EB),
                                        modifier = Modifier.size(15.dp)
                                    )
                                    Spacer(modifier = Modifier.height(2.dp))
                                    Text("ইনবাউন্ড", fontSize = 10.sp, color = textSecondary)
                                    Text(
                                        text = "$inboundCalls",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 15.sp,
                                        color = textPrimary
                                    )
                                }

                                Box(
                                    modifier = Modifier
                                        .height(32.dp)
                                        .width(1.dp)
                                        .background(if (isDarkMode) Color(0xFF334155) else Color(0xFFE2E8F0))
                                )

                                // আউটবাউন্ড
                                Column(
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    modifier = Modifier.width(48.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.AutoMirrored.Filled.CallMade,
                                        contentDescription = null,
                                        tint = Color(0xFF2563EB),
                                        modifier = Modifier.size(15.dp)
                                    )
                                    Spacer(modifier = Modifier.height(2.dp))
                                    Text("আউটবাউন্ড", fontSize = 10.sp, color = textSecondary)
                                    Text(
                                        text = "$outboundCalls",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 15.sp,
                                        color = textPrimary
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // ── Section 1: কল সেন্টার (কনফিগারেশন) ──
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        // Header
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    imageVector = Icons.Default.Build,
                                    contentDescription = null,
                                    tint = Color(0xFF2563EB),
                                    modifier = Modifier.size(19.dp)
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Column {
                                    Text(
                                        text = "কল সেন্টার (কনফিগারেশন)",
                                        fontSize = 15.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = textPrimary
                                    )
                                    Text(
                                        text = "কল সেন্টার সেটআপ, নম্বর, এজেন্ট এবং কল রাউটিং পরিচালনা করুন।",
                                        fontSize = 11.sp,
                                        color = textSecondary
                                    )
                                }
                            }

                            OutlinedButton(
                                onClick = { showSettingsFullDialog = true },
                                shape = RoundedCornerShape(8.dp),
                                border = BorderStroke(1.dp, Color(0xFFCBD5E1)),
                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp),
                                colors = ButtonDefaults.outlinedButtonColors(containerColor = Color.Transparent)
                            ) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(
                                        imageVector = Icons.Default.Settings,
                                        contentDescription = null,
                                        tint = Color(0xFF475569),
                                        modifier = Modifier.size(13.dp)
                                    )
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text(
                                        text = "সেটিংস",
                                        fontSize = 11.5.sp,
                                        color = Color(0xFF475569),
                                        fontWeight = FontWeight.Medium
                                    )
                                    Spacer(modifier = Modifier.width(2.dp))
                                    Icon(
                                        imageVector = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                                        contentDescription = null,
                                        tint = Color(0xFF475569),
                                        modifier = Modifier.size(13.dp)
                                    )
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        // Horizontal Row of 4 Action Cards
                        LazyRow(
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            // Card 1: কল নম্বর
                            item {
                                Surface(
                                    modifier = Modifier
                                        .width(125.dp)
                                        .clickable { showNumberConfigDialog = true },
                                    shape = RoundedCornerShape(12.dp),
                                    color = if (isDarkMode) Color(0xFF1E2433) else Color.White,
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Column(
                                        modifier = Modifier
                                            .padding(12.dp)
                                            .height(115.dp),
                                        verticalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Column {
                                            Box(
                                                modifier = Modifier
                                                    .size(32.dp)
                                                    .clip(CircleShape)
                                                    .background(Color(0xFFEFF6FF)),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.Call,
                                                    contentDescription = null,
                                                    tint = Color(0xFF3B82F6),
                                                    modifier = Modifier.size(16.dp)
                                                )
                                            }
                                            Spacer(modifier = Modifier.height(8.dp))
                                            Text(
                                                text = "কল নম্বর",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 13.sp,
                                                color = textPrimary
                                            )
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Text(
                                                text = "আপনার ভার্চুয়াল নম্বর পরিচালনা করুন",
                                                fontSize = 10.sp,
                                                color = textSecondary,
                                                lineHeight = 13.sp
                                            )
                                        }

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.End
                                        ) {
                                            Icon(
                                                imageVector = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                                                contentDescription = null,
                                                tint = Color(0xFF94A3B8),
                                                modifier = Modifier.size(14.dp)
                                            )
                                        }
                                    }
                                }
                            }

                            // Card 2: এজেন্ট
                            item {
                                Surface(
                                    modifier = Modifier
                                        .width(125.dp)
                                        .clickable { showAgentConfigDialog = true },
                                    shape = RoundedCornerShape(12.dp),
                                    color = if (isDarkMode) Color(0xFF1E2433) else Color.White,
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Column(
                                        modifier = Modifier
                                            .padding(12.dp)
                                            .height(115.dp),
                                        verticalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Column {
                                            Box(
                                                modifier = Modifier
                                                    .size(32.dp)
                                                    .clip(CircleShape)
                                                    .background(Color(0xFFDCFCE7)),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.Person,
                                                    contentDescription = null,
                                                    tint = Color(0xFF10B981),
                                                    modifier = Modifier.size(16.dp)
                                                )
                                            }
                                            Spacer(modifier = Modifier.height(8.dp))
                                            Text(
                                                text = "এজেন্ট",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 13.sp,
                                                color = textPrimary
                                            )
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Text(
                                                text = "কল এজেন্ট তৈরি ও ম্যানেজ করুন",
                                                fontSize = 10.sp,
                                                color = textSecondary,
                                                lineHeight = 13.sp
                                            )
                                        }

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.End
                                        ) {
                                            Icon(
                                                imageVector = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                                                contentDescription = null,
                                                tint = Color(0xFF94A3B8),
                                                modifier = Modifier.size(14.dp)
                                            )
                                        }
                                    }
                                }
                            }

                            // Card 3: কল রাউটিং
                            item {
                                Surface(
                                    modifier = Modifier
                                        .width(125.dp)
                                        .clickable { showRoutingConfigDialog = true },
                                    shape = RoundedCornerShape(12.dp),
                                    color = if (isDarkMode) Color(0xFF1E2433) else Color.White,
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Column(
                                        modifier = Modifier
                                            .padding(12.dp)
                                            .height(115.dp),
                                        verticalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Column {
                                            Box(
                                                modifier = Modifier
                                                    .size(32.dp)
                                                    .clip(CircleShape)
                                                    .background(Color(0xFFF3E8FF)),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.Hub,
                                                    contentDescription = null,
                                                    tint = Color(0xFF9333EA),
                                                    modifier = Modifier.size(16.dp)
                                                )
                                            }
                                            Spacer(modifier = Modifier.height(8.dp))
                                            Text(
                                                text = "কল রাউটিং",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 13.sp,
                                                color = textPrimary
                                            )
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Text(
                                                text = "কল ফ্লো সেটআপ করুন",
                                                fontSize = 10.sp,
                                                color = textSecondary,
                                                lineHeight = 13.sp
                                            )
                                        }

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.End
                                        ) {
                                            Icon(
                                                imageVector = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                                                contentDescription = null,
                                                tint = Color(0xFF94A3B8),
                                                modifier = Modifier.size(14.dp)
                                            )
                                        }
                                    }
                                }
                            }

                            // Card 4: কাস্টম স্ক্রিপ্ট
                            item {
                                Surface(
                                    modifier = Modifier
                                        .width(125.dp)
                                        .clickable { showScriptConfigDialog = true },
                                    shape = RoundedCornerShape(12.dp),
                                    color = if (isDarkMode) Color(0xFF1E2433) else Color.White,
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Column(
                                        modifier = Modifier
                                            .padding(12.dp)
                                            .height(115.dp),
                                        verticalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Column {
                                            Box(
                                                modifier = Modifier
                                                    .size(32.dp)
                                                    .clip(CircleShape)
                                                    .background(Color(0xFFFEF3C7)),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.Settings,
                                                    contentDescription = null,
                                                    tint = Color(0xFFD97706),
                                                    modifier = Modifier.size(16.dp)
                                                )
                                            }
                                            Spacer(modifier = Modifier.height(8.dp))
                                            Text(
                                                text = "কাস্টম স্ক্রিপ্ট",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 13.sp,
                                                color = textPrimary
                                            )
                                            Spacer(modifier = Modifier.height(2.dp))
                                            Text(
                                                text = "স্বাগতম বার্তা ও স্ক্রিপ্ট সেট করুন",
                                                fontSize = 10.sp,
                                                color = textSecondary,
                                                lineHeight = 13.sp
                                            )
                                        }

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.End
                                        ) {
                                            Icon(
                                                imageVector = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                                                contentDescription = null,
                                                tint = Color(0xFF94A3B8),
                                                modifier = Modifier.size(14.dp)
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // ── Section 2: কল এনালিটিক্স ──
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        // Header
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    imageVector = Icons.Default.BarChart,
                                    contentDescription = null,
                                    tint = Color(0xFFF59E0B),
                                    modifier = Modifier.size(20.dp)
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Column {
                                    Text(
                                        text = "কল এনালিটিক্স",
                                        fontSize = 15.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = textPrimary
                                    )
                                    Text(
                                        text = "কলের পারফরম্যান্স, রেকর্ড ও রিপোর্ট দেখুন।",
                                        fontSize = 11.sp,
                                        color = textSecondary
                                    )
                                }
                            }

                            OutlinedButton(
                                onClick = { showAnalyticsDetailsDialog = true },
                                shape = RoundedCornerShape(8.dp),
                                border = BorderStroke(1.dp, Color(0xFFCBD5E1)),
                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp),
                                colors = ButtonDefaults.outlinedButtonColors(containerColor = Color.Transparent)
                            ) {
                                Text(
                                    text = "বিস্তারিত দেখুন >",
                                    fontSize = 11.5.sp,
                                    color = Color(0xFF475569),
                                    fontWeight = FontWeight.Medium
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        // 4 Equal Stat Metric Cards in a single row
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            // 1. মোট কল
                            Surface(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp),
                                color = if (isDarkMode) Color(0xFF1E2433) else Color.White,
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(28.dp)
                                            .clip(CircleShape)
                                            .background(Color(0xFFDCFCE7)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Call,
                                            contentDescription = null,
                                            tint = Color(0xFF10B981),
                                            modifier = Modifier.size(14.dp)
                                        )
                                    }
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Column {
                                        Text("মোট কল", fontSize = 9.5.sp, color = textSecondary)
                                        Text("$totalCalls", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = textPrimary)
                                    }
                                }
                            }

                            // 2. ইনবাউন্ড
                            Surface(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp),
                                color = if (isDarkMode) Color(0xFF1E2433) else Color.White,
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(28.dp)
                                            .clip(CircleShape)
                                            .background(Color(0xFFEFF6FF)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.PhoneCallback,
                                            contentDescription = null,
                                            tint = Color(0xFF3B82F6),
                                            modifier = Modifier.size(14.dp)
                                        )
                                    }
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Column {
                                        Text("ইনবাউন্ড", fontSize = 9.5.sp, color = textSecondary)
                                        Text("$inboundCalls", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = textPrimary)
                                    }
                                }
                            }

                            // 3. আউটবাউন্ড
                            Surface(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp),
                                color = if (isDarkMode) Color(0xFF1E2433) else Color.White,
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(28.dp)
                                            .clip(CircleShape)
                                            .background(Color(0xFFF3E8FF)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.SwapHoriz,
                                            contentDescription = null,
                                            tint = Color(0xFF9333EA),
                                            modifier = Modifier.size(14.dp)
                                        )
                                    }
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Column {
                                        Text("আউটবাউন্ড", fontSize = 9.5.sp, color = textSecondary)
                                        Text("$outboundCalls", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = textPrimary)
                                    }
                                }
                            }

                            // 4. গড় কল সময়
                            Surface(
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp),
                                color = if (isDarkMode) Color(0xFF1E2433) else Color.White,
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(28.dp)
                                            .clip(CircleShape)
                                            .background(Color(0xFFFEF3C7)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.AccessTime,
                                            contentDescription = null,
                                            tint = Color(0xFFD97706),
                                            modifier = Modifier.size(14.dp)
                                        )
                                    }
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Column {
                                        Text("গড় কল সময়", fontSize = 9.5.sp, color = textSecondary)
                                        Text(avgCallDuration, fontWeight = FontWeight.Bold, fontSize = 15.sp, color = textPrimary)
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // ── Section 3: কল রেকর্ড ──
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
                            .padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(36.dp)
                                    .clip(CircleShape)
                                    .background(if (isDarkMode) Color(0xFF1E2433) else Color(0xFFEFF6FF)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Headset,
                                    contentDescription = null,
                                    tint = textPrimary,
                                    modifier = Modifier.size(20.dp)
                                )
                            }
                            Spacer(modifier = Modifier.width(10.dp))
                            Column {
                                Text(
                                    text = "কল রেকর্ড",
                                    fontSize = 15.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Text(
                                    text = "সকল কলের রেকর্ড শুনুন, ডাউনলোড করুন এবং বিশ্লেষণ করুন।",
                                    fontSize = 11.sp,
                                    color = textSecondary
                                )
                            }
                        }

                        OutlinedButton(
                            onClick = { showCallRecordsDialog = true },
                            shape = RoundedCornerShape(8.dp),
                            border = BorderStroke(1.dp, Color(0xFFCBD5E1)),
                            contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp),
                            colors = ButtonDefaults.outlinedButtonColors(containerColor = Color.Transparent)
                        ) {
                            Text(
                                text = "রেকর্ড দেখুন >",
                                fontSize = 11.5.sp,
                                color = Color(0xFF475569),
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }
                }
            }

            // ── Section 4: Empty State or Call Records List ──
            if (callLogs.isEmpty()) {
                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (isDarkMode) Color(0xFF1E1B16) else Color(0xFFFFFDF5)
                        ),
                        border = BorderStroke(1.dp, if (isDarkMode) Color(0xFF382F19) else Color(0xFFFEF3C7))
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 26.dp, horizontal = 16.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Box(contentAlignment = Alignment.BottomEnd) {
                                Icon(
                                    imageVector = Icons.Default.Article,
                                    contentDescription = null,
                                    tint = Color(0xFF94A3B8),
                                    modifier = Modifier.size(44.dp)
                                )
                                Box(
                                    modifier = Modifier
                                        .size(18.dp)
                                        .background(Color(0xFFF59E0B), CircleShape)
                                        .padding(3.dp),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Phone,
                                        contentDescription = null,
                                        tint = Color.White,
                                        modifier = Modifier.size(11.dp)
                                    )
                                }
                            }

                            Spacer(modifier = Modifier.height(12.dp))

                            Text(
                                text = "এখনও কোনো কল করা হয়নি",
                                fontSize = 15.sp,
                                fontWeight = FontWeight.Bold,
                                color = textPrimary
                            )

                            Spacer(modifier = Modifier.height(4.dp))

                            Text(
                                text = "আপনার সকল কলের ইতিহাস এখানে দেখানো হবে",
                                fontSize = 12.sp,
                                color = textSecondary
                            )
                        }
                    }
                }
            } else {
                items(callLogs) { record ->
                    AiCallLogCard(
                        record = record,
                        isDarkMode = isDarkMode,
                        isBangla = isBangla
                    )
                }
            }

            item { Spacer(modifier = Modifier.height(16.dp)) }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. VIRTUAL CALL NUMBER CONFIGURATION DIALOG
    // ──────────────────────────────────────────────────────────────────────────
    if (showNumberConfigDialog) {
        var tempNumber by remember { mutableStateOf(voiceSettings.callerNumber) }
        AlertDialog(
            onDismissRequest = { showNumberConfigDialog = false },
            title = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Call, null, tint = Color(0xFF3B82F6))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("কল নম্বর পরিচালনা", fontWeight = FontWeight.Bold)
                }
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("আপনার ভার্চুয়াল ব্যবসায়িক নম্বর:", fontSize = 12.sp, color = textSecondary)
                    OutlinedTextField(
                        value = tempNumber,
                        onValueChange = { tempNumber = it },
                        label = { Text("ভার্চুয়াল ফোন নম্বর (DID)") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
                    )
                    Card(
                        colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF1E2433) else Color(0xFFEFF6FF)),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Column(modifier = Modifier.padding(10.dp)) {
                            Text("সার্ভার স্ট্যাটাস: সক্রিয় (Active)", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = Color(0xFF2563EB))
                            Text("টেলকো গেটওয়ে: Robi / Banglalink Cloud SIP Trunk", fontSize = 11.sp, color = textSecondary)
                        }
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val updated = voiceSettings.copy(callerNumber = tempNumber)
                        viewModel.updateAiVoiceSettings(updated) {
                            showNumberConfigDialog = false
                            Toast.makeText(context, "নম্বর সফলভাবে সংরক্ষিত হয়েছে", Toast.LENGTH_SHORT).show()
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF3B82F6))
                ) {
                    Text("সংরক্ষণ করুন")
                }
            },
            dismissButton = {
                TextButton(onClick = { showNumberConfigDialog = false }) {
                    Text("বাতিল")
                }
            }
        )
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. AGENT PERSONA CONFIGURATION DIALOG
    // ──────────────────────────────────────────────────────────────────────────
    if (showAgentConfigDialog) {
        var tempName by remember { mutableStateOf(voiceSettings.agentName) }
        var tempGender by remember { mutableStateOf(voiceSettings.voiceGender) }
        AlertDialog(
            onDismissRequest = { showAgentConfigDialog = false },
            title = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Person, null, tint = Color(0xFF10B981))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("কল এজেন্ট ম্যানেজমেন্ট", fontWeight = FontWeight.Bold)
                }
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = tempName,
                        onValueChange = { tempName = it },
                        label = { Text("এজেন্টের নাম") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
                    )

                    Text("ভয়েস জেন্ডার নির্বাচন করুন:", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        FilterChip(
                            selected = tempGender == "female",
                            onClick = { tempGender = "female" },
                            label = { Text("মহিলা কণ্ঠ (Female)") }
                        )
                        FilterChip(
                            selected = tempGender == "male",
                            onClick = { tempGender = "male" },
                            label = { Text("পুরুষ কণ্ঠ (Male)") }
                        )
                    }

                    Text("ভাষা: বাংলা (বাংলাদেশ) - bn-BD", fontSize = 12.sp, color = textSecondary)
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val updated = voiceSettings.copy(agentName = tempName, voiceGender = tempGender)
                        viewModel.updateAiVoiceSettings(updated) {
                            showAgentConfigDialog = false
                            Toast.makeText(context, "এজেন্ট তথ্য সংরক্ষিত হয়েছে", Toast.LENGTH_SHORT).show()
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981))
                ) {
                    Text("সংরক্ষণ করুন")
                }
            },
            dismissButton = {
                TextButton(onClick = { showAgentConfigDialog = false }) {
                    Text("বাতিল")
                }
            }
        )
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. CALL ROUTING CONFIGURATION DIALOG
    // ──────────────────────────────────────────────────────────────────────────
    if (showRoutingConfigDialog) {
        var tempAutoAnswer by remember { mutableStateOf(voiceSettings.autoAnswer) }
        AlertDialog(
            onDismissRequest = { showRoutingConfigDialog = false },
            title = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Hub, null, tint = Color(0xFF9333EA))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("কল রাউটিং ও ফ্লো", fontWeight = FontWeight.Bold)
                }
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text("স্বয়ংক্রিয় রিসিভ (Auto-Answer)", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                            Text("গ্রাহক কল করলে এআই নিজে থেকেই রিসিভ করে কথা বলবে।", fontSize = 11.sp, color = textSecondary)
                        }
                        Switch(
                            checked = tempAutoAnswer,
                            onCheckedChange = { tempAutoAnswer = it }
                        )
                    }

                    HorizontalDivider(color = cardBorder)

                    Text("ফেলওভার কল ফরোয়ার্ডিং:", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                    Text("এআই ব্যর্থ হলে বা জটিল প্রশ্নে মার্চেন্টের ব্যক্তিগত নম্বরে কল ফরোয়ার্ড হবে।", fontSize = 11.sp, color = textSecondary)
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val updated = voiceSettings.copy(autoAnswer = tempAutoAnswer)
                        viewModel.updateAiVoiceSettings(updated) {
                            showRoutingConfigDialog = false
                            Toast.makeText(context, "রাউটিং সংরক্ষিত হয়েছে", Toast.LENGTH_SHORT).show()
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF9333EA))
                ) {
                    Text("সংরক্ষণ করুন")
                }
            },
            dismissButton = {
                TextButton(onClick = { showRoutingConfigDialog = false }) {
                    Text("বাতিল")
                }
            }
        )
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. CUSTOM SCRIPT CONFIGURATION DIALOG
    // ──────────────────────────────────────────────────────────────────────────
    if (showScriptConfigDialog) {
        var tempGreeting by remember { mutableStateOf(voiceSettings.greetingBn) }
        var tempDueScript by remember { mutableStateOf(voiceSettings.dueReminderScript) }
        AlertDialog(
            onDismissRequest = { showScriptConfigDialog = false },
            title = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Settings, null, tint = Color(0xFFD97706))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("কাস্টম স্ক্রিপ্ট ও বার্তা", fontWeight = FontWeight.Bold)
                }
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("স্বাগত বার্তা (কল শুরুর কথা):", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                    OutlinedTextField(
                        value = tempGreeting,
                        onValueChange = { tempGreeting = it },
                        modifier = Modifier.fillMaxWidth(),
                        maxLines = 3,
                        shape = RoundedCornerShape(10.dp)
                    )

                    Spacer(modifier = Modifier.height(4.dp))
                    Text("বকেয়া তাগাদা স্ক্রিপ্ট:", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                    OutlinedTextField(
                        value = tempDueScript,
                        onValueChange = { tempDueScript = it },
                        modifier = Modifier.fillMaxWidth(),
                        maxLines = 3,
                        shape = RoundedCornerShape(10.dp)
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val updated = voiceSettings.copy(greetingBn = tempGreeting, dueReminderScript = tempDueScript)
                        viewModel.updateAiVoiceSettings(updated) {
                            showScriptConfigDialog = false
                            Toast.makeText(context, "স্ক্রিপ্ট সংরক্ষিত হয়েছে", Toast.LENGTH_SHORT).show()
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFD97706))
                ) {
                    Text("সংরক্ষণ করুন")
                }
            },
            dismissButton = {
                TextButton(onClick = { showScriptConfigDialog = false }) {
                    Text("বাতিল")
                }
            }
        )
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. CALL ANALYTICS DETAILS DIALOG
    // ──────────────────────────────────────────────────────────────────────────
    if (showAnalyticsDetailsDialog) {
        AlertDialog(
            onDismissRequest = { showAnalyticsDetailsDialog = false },
            title = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.BarChart, null, tint = Color(0xFFF59E0B))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("কল এনালিটিক্স রিপোর্ট", fontWeight = FontWeight.Bold)
                }
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = if (isDarkMode) Color(0xFF1E2433) else Color(0xFFF8FAFC)),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("মোট সম্পন্ন কল:", fontSize = 12.sp, color = textSecondary)
                                Text("$totalCalls টি", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                            }
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("ইনবাউন্ড কল রিসিভ:", fontSize = 12.sp, color = textSecondary)
                                Text("$inboundCalls টি", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                            }
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("আউটবাউন্ড তাগাদা কল:", fontSize = 12.sp, color = textSecondary)
                                Text("$outboundCalls টি", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                            }
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("গড় কল স্থায়িত্ব:", fontSize = 12.sp, color = textSecondary)
                                Text(avgCallDuration, fontWeight = FontWeight.Bold, fontSize = 12.sp)
                            }
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("কল সাকসেস রেট:", fontSize = 12.sp, color = textSecondary)
                                Text("১০০%", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = Color(0xFF10B981))
                            }
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { showAnalyticsDetailsDialog = false }) {
                    Text("ঠিক আছে")
                }
            }
        )
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. CALL RECORDS FULL LIST DIALOG
    // ──────────────────────────────────────────────────────────────────────────
    if (showCallRecordsDialog) {
        AlertDialog(
            onDismissRequest = { showCallRecordsDialog = false },
            title = {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("সকল কল রেকর্ড", fontWeight = FontWeight.Bold)
                    IconButton(onClick = { viewModel.fetchAiCallLogs() }, modifier = Modifier.size(28.dp)) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh", modifier = Modifier.size(18.dp))
                    }
                }
            },
            text = {
                Box(modifier = Modifier.heightIn(max = 400.dp).fillMaxWidth()) {
                    if (callLogs.isEmpty()) {
                        Column(
                            modifier = Modifier.fillMaxWidth().padding(20.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Text("এখনও কোনো কল রেকর্ড সংরক্ষিত নেই", color = textSecondary, fontSize = 13.sp)
                        }
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(callLogs) { rec ->
                                AiCallLogCard(
                                    record = rec,
                                    isDarkMode = isDarkMode,
                                    isBangla = isBangla
                                )
                            }
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { showCallRecordsDialog = false }) {
                    Text("বন্ধ করুন")
                }
            }
        )
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. SETTINGS FULL DIALOG (Top right button in Section 1)
    // ──────────────────────────────────────────────────────────────────────────
    if (showSettingsFullDialog) {
        var tempName by remember { mutableStateOf(voiceSettings.agentName) }
        var tempNumber by remember { mutableStateOf(voiceSettings.callerNumber) }
        var tempAutoAnswer by remember { mutableStateOf(voiceSettings.autoAnswer) }
        AlertDialog(
            onDismissRequest = { showSettingsFullDialog = false },
            title = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Settings, null, tint = Color(0xFF2563EB))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("এআই ভয়েস সেটিংস", fontWeight = FontWeight.Bold)
                }
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = tempName,
                        onValueChange = { tempName = it },
                        label = { Text("এজেন্ট নাম") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = tempNumber,
                        onValueChange = { tempNumber = it },
                        label = { Text("ভার্চুয়াল কলার নম্বর") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("অটো অ্যানসার মোড")
                        Switch(checked = tempAutoAnswer, onCheckedChange = { tempAutoAnswer = it })
                    }

                    Spacer(modifier = Modifier.height(6.dp))
                    OutlinedButton(
                        onClick = {
                            showSettingsFullDialog = false
                            showTestCallDialog = true
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Icon(Icons.Default.Phone, null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text("সরাসরি ভয়েস কল টেস্ট করুন")
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val updated = voiceSettings.copy(
                            agentName = tempName,
                            callerNumber = tempNumber,
                            autoAnswer = tempAutoAnswer
                        )
                        viewModel.updateAiVoiceSettings(updated) {
                            showSettingsFullDialog = false
                            Toast.makeText(context, "সকল সেটিংস সংরক্ষিত হয়েছে", Toast.LENGTH_SHORT).show()
                        }
                    }
                ) {
                    Text("সংরক্ষণ করুন")
                }
            },
            dismissButton = {
                TextButton(onClick = { showSettingsFullDialog = false }) {
                    Text("বাতিল")
                }
            }
        )
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. QUICK ACTION DIALOG (Center '+' FAB Action)
    // ──────────────────────────────────────────────────────────────────────────
    if (showQuickActionDialog) {
        AlertDialog(
            onDismissRequest = { showQuickActionDialog = false },
            title = {
                Text("নতুন ভয়েস কল অ্যাকশন", fontWeight = FontWeight.Bold)
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(
                        onClick = {
                            showQuickActionDialog = false
                            showDueCallDialog = true
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444))
                    ) {
                        Icon(Icons.Default.PhoneCallback, null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("বকেয়া তাগাদা কল (Due Reminder)", fontWeight = FontWeight.Bold)
                    }

                    Button(
                        onClick = {
                            showQuickActionDialog = false
                            showOrderCallDialog = true
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981))
                    ) {
                        Icon(Icons.Default.Call, null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("অর্ডার কনফার্মেশন কল", fontWeight = FontWeight.Bold)
                    }

                    OutlinedButton(
                        onClick = {
                            showQuickActionDialog = false
                            showTestCallDialog = true
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Icon(Icons.Default.Headset, null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("এআই ভয়েস টেস্ট কল")
                    }

                    OutlinedButton(
                        onClick = {
                            showQuickActionDialog = false
                            showInstantVoiceRecordDialog = true
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Icon(Icons.Default.Mic, null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("ইনস্ট্যান্ট ভয়েস ইনপুট")
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { showQuickActionDialog = false }) {
                    Text("বন্ধ করুন")
                }
            }
        )
    }

    // ── Pre-existing Outbound Dialogs ──
    if (showDueCallDialog) {
        OutboundDueCallDialog(
            isDarkMode = isDarkMode,
            isBangla = isBangla,
            isLoading = isActionLoading,
            onDismiss = { showDueCallDialog = false },
            onDirectDial = { phone ->
                showDueCallDialog = false
                viewModel.dialCustomerPhone(context, phone)
            },
            onConfirm = { phone, name, amount ->
                viewModel.triggerDueReminderCall(phone, name, amount) { success, msg ->
                    showDueCallDialog = false
                    Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                }
            }
        )
    }

    if (showOrderCallDialog) {
        OutboundOrderCallDialog(
            isDarkMode = isDarkMode,
            isBangla = isBangla,
            isLoading = isActionLoading,
            onDismiss = { showOrderCallDialog = false },
            onConfirm = { phone, name, orderId, amount ->
                viewModel.triggerOrderConfirmCall(phone, name, orderId, amount) { success, msg ->
                    showOrderCallDialog = false
                    Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                }
            }
        )
    }

    if (showTestCallDialog) {
        InteractiveVoiceTestDialog(
            isDarkMode = isDarkMode,
            isBangla = isBangla,
            voiceSettings = voiceSettings,
            viewModel = viewModel,
            onDismiss = { showTestCallDialog = false }
        )
    }

    if (showInstantVoiceRecordDialog) {
        InstantVoiceRecordToAiDialog(
            isDarkMode = isDarkMode,
            isBangla = isBangla,
            voiceSettings = voiceSettings,
            viewModel = viewModel,
            onDismiss = { showInstantVoiceRecordDialog = false }
        )
    }

    if (showCampaignDialog) {
        CampaignBroadcastDialog(
            isDarkMode = isDarkMode,
            isBangla = isBangla,
            isLoading = isActionLoading,
            viewModel = viewModel,
            onDismiss = { showCampaignDialog = false }
        )
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// Component: AI Call Log Card with Expandable Transcript
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun AiCallLogCard(
    record: AiCallRecord,
    isDarkMode: Boolean,
    isBangla: Boolean
) {
    var expanded by remember { mutableStateOf(false) }

    val cardBg = if (isDarkMode) Color(0xFF181524) else Color(0xFFFFFFFF)
    val cardBorder = if (isDarkMode) Color(0xFF2C2640) else Color(0xFFE2E8F0)
    val textPrimary = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDarkMode) Color.White.copy(alpha = 0.7f) else Color(0xFF64748B)

    val isInbound = record.direction == "inbound"
    val badgeBg = if (isInbound) Color(0xFF10B981).copy(alpha = 0.15f) else Color(0xFF4F46E5).copy(alpha = 0.15f)
    val badgeTextColor = if (isInbound) Color(0xFF10B981) else Color(0xFF6366F1)
    val badgeIcon = if (isInbound) Icons.Default.CallReceived else Icons.Default.CallMade

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { expanded = !expanded },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            // Header Row: Type chip, Customer name/phone, Duration
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .background(badgeBg, RoundedCornerShape(8.dp))
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(badgeIcon, contentDescription = null, tint = badgeTextColor, modifier = Modifier.size(12.dp))
                            Text(
                                text = if (isInbound) (if (isBangla) "ইনবাউন্ড কল" else "Inbound") else (if (isBangla) "তাগাদা কল" else "Outbound Due"),
                                color = badgeTextColor,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }

                    Text(
                        text = record.customerName.ifBlank { record.from },
                        color = textPrimary,
                        fontWeight = FontWeight.Bold,
                        fontSize = 13.5.sp
                    )
                }

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Icon(Icons.Default.Schedule, null, tint = textSecondary, modifier = Modifier.size(12.dp))
                    Text(record.duration, color = textSecondary, fontSize = 11.sp)
                }
            }

            // Purpose & Summary
            Text(
                text = record.purpose,
                color = textSecondary,
                fontSize = 12.sp,
                fontWeight = FontWeight.Medium
            )

            if (record.summary.isNotBlank()) {
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = if (isDarkMode) Color(0xFF221D33) else Color(0xFFF1F5F9)
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Icon(Icons.Default.AutoAwesome, null, tint = BrandPurple, modifier = Modifier.size(14.dp))
                        Text(
                            text = record.summary,
                            color = textPrimary,
                            fontSize = 11.5.sp,
                            lineHeight = 15.sp
                        )
                    }
                }
            }

            // Expandable Conversation Transcript
            AnimatedVisibility(visible = expanded) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Divider(color = cardBorder, thickness = 0.8.dp)
                    Text(
                        text = if (isBangla) "💬 কথোপকথনের পুরো ট্রানস্ক্রিপ্ট:" else "💬 Full Call Transcript:",
                        color = textPrimary,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )

                    if (record.transcript.isEmpty()) {
                        Text(
                            text = if (isBangla) "কোনো কথাবলার রেকর্ড পাওয়া যায়নি।" else "No transcript available.",
                            color = textSecondary,
                            fontSize = 11.sp
                        )
                    } else {
                        record.transcript.forEach { turn ->
                            val isAi = turn.role == "assistant"
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = if (isAi) Arrangement.Start else Arrangement.End
                            ) {
                                Surface(
                                    shape = RoundedCornerShape(12.dp),
                                    color = if (isAi)
                                        (if (isDarkMode) Color(0xFF261D45) else Color(0xFFEEF2FF))
                                    else
                                        (if (isDarkMode) Color(0xFF1E293B) else Color(0xFFE2E8F0)),
                                    modifier = Modifier.fillMaxWidth(0.85f)
                                ) {
                                    Column(modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween
                                        ) {
                                            Text(
                                                text = if (isAi) (if (isBangla) "🤖 এআই প্রতিনিধি" else "🤖 AI Assistant") else (if (isBangla) "👤 কাস্টমার" else "👤 Customer"),
                                                fontSize = 10.5.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (isAi) BrandPurple else textPrimary
                                            )
                                            Text(turn.time, fontSize = 9.5.sp, color = textSecondary)
                                        }
                                        Spacer(modifier = Modifier.height(2.dp))
                                        Text(turn.text, fontSize = 11.5.sp, color = textPrimary, lineHeight = 15.sp)
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Expand Prompt Hint
            Text(
                text = if (expanded)
                    (if (isBangla) "▲ ট্রানস্ক্রিপ্ট লুকান" else "▲ Hide Transcript")
                else
                    (if (isBangla) "▼ ট্রানস্ক্রিপ্ট ও অডিও বিবরণ দেখুন" else "▼ View Full Conversation Transcript"),
                color = BrandPurple,
                fontSize = 11.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.align(Alignment.End)
            )
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// Component: AI Persona & Voice Settings Tab
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun AiVoiceSettingsTab(
    settings: AiVoiceSettingsState,
    isDarkMode: Boolean,
    isBangla: Boolean,
    onSave: (AiVoiceSettingsState) -> Unit
) {
    var agentName by remember(settings) { mutableStateOf(settings.agentName) }
    var language by remember(settings) { mutableStateOf(settings.language) }
    var voiceGender by remember(settings) { mutableStateOf(settings.voiceGender) }
    var autoAnswer by remember(settings) { mutableStateOf(settings.autoAnswer) }
    var businessName by remember(settings) { mutableStateOf(settings.businessName) }
    var greetingBn by remember(settings) { mutableStateOf(settings.greetingBn) }
    var dueScript by remember(settings) { mutableStateOf(settings.dueReminderScript) }

    val cardBg = if (isDarkMode) Color(0xFF181524) else Color(0xFFFFFFFF)
    val cardBorder = if (isDarkMode) Color(0xFF2C2640) else Color(0xFFE2E8F0)
    val textPrimary = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDarkMode) Color.White.copy(alpha = 0.7f) else Color(0xFF64748B)

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Toggle 24/7 Receptionist
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = cardBg),
            border = BorderStroke(1.dp, cardBorder)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(14.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = if (isBangla) "স্বয়ংক্রিয় কল রিসিভ (২৪/৭ AI Receptionist)" else "Auto Call Reception (24/7 AI Receptionist)",
                        fontWeight = FontWeight.Bold,
                        fontSize = 13.5.sp,
                        color = textPrimary
                    )
                    Text(
                        text = if (isBangla)
                            "চালু থাকলে কাস্টমার কল করলে এআই প্রতিনিধি ফোন রিসিভ করে তথ্য প্রদান করবে।"
                        else
                            "Automatically answers inbound calls and answers customer queries using Gemini.",
                        fontSize = 11.sp,
                        color = textSecondary,
                        lineHeight = 15.sp
                    )
                }
                Switch(
                    checked = autoAnswer,
                    onCheckedChange = { autoAnswer = it },
                    colors = SwitchDefaults.colors(checkedThumbColor = Color.White, checkedTrackColor = Color(0xFF10B981))
                )
            }
        }

        // Persona Configuration
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = cardBg),
            border = BorderStroke(1.dp, cardBorder)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                Text(
                    text = if (isBangla) "এআই প্রতিনিধির পরিচয়" else "AI Agent Persona",
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp,
                    color = textPrimary
                )

                // Agent Name
                OutlinedTextField(
                    value = agentName,
                    onValueChange = { agentName = it },
                    label = { Text(if (isBangla) "প্রতিনিধির নাম" else "Agent Name") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp)
                )

                // Business Name
                OutlinedTextField(
                    value = businessName,
                    onValueChange = { businessName = it },
                    label = { Text(if (isBangla) "প্রতিষ্ঠানের নাম" else "Business Name") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp)
                )

                // Voice Gender Selection
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    listOf("female" to if (isBangla) "মহিলা কণ্ঠ (Female)" else "Female Voice",
                           "male" to if (isBangla) "পুরুষ কণ্ঠ (Male)" else "Male Voice").forEach { (valKey, label) ->
                        val isSelected = voiceGender == valKey
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .height(42.dp)
                                .clip(RoundedCornerShape(10.dp))
                                .background(if (isSelected) BrandPurple else cardBg)
                                .border(1.dp, if (isSelected) BrandPurple else cardBorder, RoundedCornerShape(10.dp))
                                .clickable { voiceGender = valKey },
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = label,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = if (isSelected) Color.White else textPrimary
                            )
                        }
                    }
                }
            }
        }

        // Voice Scripts
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = cardBg),
            border = BorderStroke(1.dp, cardBorder)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                Text(
                    text = if (isBangla) "কথাবার্তার স্ক্রিপ্ট ও শুভেচ্ছা বার্তা" else "Voice Scripts & Greetings",
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp,
                    color = textPrimary
                )

                // Greeting Script
                OutlinedTextField(
                    value = greetingBn,
                    onValueChange = { greetingBn = it },
                    label = { Text(if (isBangla) "ইনকামিং কল সম্ভাষণ (Greeting)" else "Inbound Greeting Script") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    maxLines = 3
                )

                // Due Reminder Script
                OutlinedTextField(
                    value = dueScript,
                    onValueChange = { dueScript = it },
                    label = { Text(if (isBangla) "বকেয়া তাগাদা কল স্ক্রিপ্ট" else "Due Reminder Script") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    maxLines = 3
                )
            }
        }

        // Save Button
        Button(
            onClick = {
                onSave(
                    settings.copy(
                        agentName = agentName,
                        businessName = businessName,
                        voiceGender = voiceGender,
                        autoAnswer = autoAnswer,
                        greetingBn = greetingBn,
                        dueReminderScript = dueScript
                    )
                )
            },
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = BrandPurple)
        ) {
            Icon(Icons.Default.Save, null, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text(
                text = if (isBangla) "সেটিংস সংরক্ষণ করুন" else "Save AI Settings",
                fontSize = 13.5.sp,
                fontWeight = FontWeight.Bold
            )
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// Dialog 1: Outbound Due Collection Call
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun OutboundDueCallDialog(
    isDarkMode: Boolean,
    isBangla: Boolean,
    isLoading: Boolean,
    onDismiss: () -> Unit,
    onDirectDial: ((phone: String) -> Unit)? = null,
    onConfirm: (phone: String, name: String, amount: Double) -> Unit
) {
    var customerPhone by remember { mutableStateOf("") }
    var customerName by remember { mutableStateOf("") }
    var dueAmountStr by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = { if (!isLoading) onDismiss() },
        title = {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Icon(Icons.Default.PhoneCallback, null, tint = Color(0xFFEF4444))
                Text(
                    text = if (isBangla) "স্বয়ংক্রিয় বকেয়া তাগাদা কল" else "Outbound Due Reminder Call",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(
                    text = if (isBangla)
                        "এআই কাস্টমারকে সরাসরি কল করে বকেয়ার পরিমাণ জানাবে এবং টাকা দেওয়ার তারিখ রেকর্ড করবে।"
                    else
                        "AI dials customer to inform them of due balance and captures their repayment commitment.",
                    fontSize = 12.sp,
                    color = Color.Gray
                )
                OutlinedTextField(
                    value = customerPhone,
                    onValueChange = { customerPhone = it },
                    label = { Text(if (isBangla) "কাস্টমারের ফোন নম্বর" else "Customer Phone") },
                    placeholder = { Text("017XXXXXXXX") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )
                OutlinedTextField(
                    value = customerName,
                    onValueChange = { customerName = it },
                    label = { Text(if (isBangla) "কাস্টমারের নাম" else "Customer Name") },
                    placeholder = { Text("আব্দুল রহিম") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )
                OutlinedTextField(
                    value = dueAmountStr,
                    onValueChange = { dueAmountStr = it },
                    label = { Text(if (isBangla) "বকেয়া টাকা (৳)" else "Due Amount (৳)") },
                    placeholder = { Text("1500") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    val amt = dueAmountStr.toDoubleOrNull() ?: 0.0
                    onConfirm(customerPhone, customerName.ifBlank { "সম্মানিত গ্রাহক" }, amt)
                },
                enabled = customerPhone.isNotBlank() && !isLoading,
                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444))
            ) {
                if (isLoading) {
                    CircularProgressIndicator(modifier = Modifier.size(16.dp), color = Color.White, strokeWidth = 2.dp)
                } else {
                    Text(if (isBangla) "এখনই এআই কল দিন" else "Start AI Call", color = Color.White)
                }
            }
        },
        dismissButton = {
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = onDismiss, enabled = !isLoading) {
                    Text(if (isBangla) "বাতিল" else "Cancel")
                }
                if (onDirectDial != null) {
                    OutlinedButton(
                        onClick = { onDirectDial(customerPhone) },
                        enabled = customerPhone.isNotBlank() && !isLoading,
                        shape = RoundedCornerShape(8.dp),
                        contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Icon(Icons.Default.Phone, null, modifier = Modifier.size(14.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(if (isBangla) "সরাসরি ডায়াল" else "Direct Dial", fontSize = 11.sp)
                    }
                }
            }
        }
    )
}

// ══════════════════════════════════════════════════════════════════════════════
// Dialog 2: Outbound Order Confirmation Call
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun OutboundOrderCallDialog(
    isDarkMode: Boolean,
    isBangla: Boolean,
    isLoading: Boolean,
    onDismiss: () -> Unit,
    onConfirm: (phone: String, name: String, orderId: String, amount: Double) -> Unit
) {
    var phone by remember { mutableStateOf("") }
    var name by remember { mutableStateOf("") }
    var orderId by remember { mutableStateOf("#ORD-") }
    var amountStr by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = { if (!isLoading) onDismiss() },
        title = {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Icon(Icons.Default.CheckCircleOutline, null, tint = Color(0xFF10B981))
                Text(
                    text = if (isBangla) "অর্ডার নিশ্চিতকরণ কল" else "Order Confirmation Call",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(
                    value = phone,
                    onValueChange = { phone = it },
                    label = { Text(if (isBangla) "কাস্টমার ফোন নম্বর" else "Customer Phone") },
                    placeholder = { Text("018XXXXXXXX") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )
                OutlinedTextField(
                    value = name,
                    onValueChange = { name = it },
                    label = { Text(if (isBangla) "কাস্টমার নাম" else "Customer Name") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )
                OutlinedTextField(
                    value = orderId,
                    onValueChange = { orderId = it },
                    label = { Text(if (isBangla) "অর্ডার আইডি" else "Order ID") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )
                OutlinedTextField(
                    value = amountStr,
                    onValueChange = { amountStr = it },
                    label = { Text(if (isBangla) "মোট বিল (৳)" else "Total Bill (৳)") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp)
                )
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    val amt = amountStr.toDoubleOrNull() ?: 0.0
                    onConfirm(phone, name.ifBlank { "সম্মানিত গ্রাহক" }, orderId, amt)
                },
                enabled = phone.isNotBlank() && !isLoading,
                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981))
            ) {
                if (isLoading) {
                    CircularProgressIndicator(modifier = Modifier.size(16.dp), color = Color.White, strokeWidth = 2.dp)
                } else {
                    Text(if (isBangla) "কনফার্ম কল দিন" else "Confirm Call", color = Color.White)
                }
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !isLoading) {
                Text(if (isBangla) "বাতিল" else "Cancel")
            }
        }
    )
}

// ══════════════════════════════════════════════════════════════════════════════
// Dialog 3: Live In-App Voice AI Tester
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun InteractiveVoiceTestDialog(
    isDarkMode: Boolean,
    isBangla: Boolean,
    voiceSettings: AiVoiceSettingsState,
    viewModel: AppViewModel,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    var queryText by remember { mutableStateOf("") }
    var isLoading by remember { mutableStateOf(false) }
    var conversation by remember {
        mutableStateOf(
            listOf(
                AiCallTurn("assistant", voiceSettings.greetingBn, "00:01")
            )
        )
    }

    // TTS Engine initialization for speaking AI response aloud
    var tts by remember { mutableStateOf<TextToSpeech?>(null) }
    DisposableEffect(Unit) {
        var engineRef: TextToSpeech? = null
        val engine = TextToSpeech(context) { status ->
            if (status == TextToSpeech.SUCCESS) {
                val locale = if (voiceSettings.language.startsWith("bn")) Locale("bn", "BD") else Locale.US
                engineRef?.language = locale
            }
        }
        engineRef = engine
        tts = engine
        onDispose {
            engine.stop()
            engine.shutdown()
        }
    }

    // Android Native Speech Recognizer Launcher
    val speechLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == android.app.Activity.RESULT_OK) {
            val spoken = result.data?.getStringArrayListExtra(RecognizerIntent.EXTRA_RESULTS)?.firstOrNull()
            if (!spoken.isNullOrBlank()) {
                queryText = spoken
            }
        }
    }

    val launchSpeech = {
        try {
            val intent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
                putExtra(RecognizerIntent.EXTRA_LANGUAGE, if (voiceSettings.language.startsWith("bn")) "bn-BD" else "en-US")
                putExtra(RecognizerIntent.EXTRA_PROMPT, if (isBangla) "এআই এর সাথে কথা বলুন..." else "Speak to AI...")
            }
            speechLauncher.launch(intent)
        } catch (e: Exception) {
            Toast.makeText(context, "Speech recognition not available", Toast.LENGTH_SHORT).show()
        }
    }

    fun sendQuestion(text: String) {
        if (text.isBlank() || isLoading) return
        val userQ = text.trim()
        queryText = ""
        val updated = conversation + AiCallTurn("customer", userQ, "00:05")
        conversation = updated
        isLoading = true

        viewModel.sendInstantRecordToAi(userQ) { success, _, reply ->
            isLoading = false
            val aiReply = if (success) reply else "দুঃখিত, উত্তর পেতে সমস্যা হয়েছে: $reply"
            conversation = updated + AiCallTurn("assistant", aiReply, "00:10")
            // Speak reply aloud via Android TTS
            tts?.speak(aiReply, TextToSpeech.QUEUE_FLUSH, null, "ai_interactive_test")
        }
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Icon(Icons.Default.RecordVoiceOver, null, tint = BrandPurple)
                Text(
                    text = if (isBangla) "এআই ভয়েস সহকারী পরীক্ষা (Live Test)" else "Test AI Voice Agent",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(max = 380.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Text(
                    text = if (isBangla)
                        "দোকানের সময়, বকেয়া বা পণ্য নিয়ে প্রশ্ন লিখুন বা মাইকে বলুন—এআই বাস্তব উত্তর দেবে ও মুখে বলবে:"
                    else
                        "Ask questions by typing or speaking—AI responds intelligently with voice playback:",
                    fontSize = 11.5.sp,
                    color = Color.Gray
                )

                // Conversation Box
                Surface(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f, fill = false),
                    shape = RoundedCornerShape(12.dp),
                    color = if (isDarkMode) Color(0xFF1A162B) else Color(0xFFF1F5F9)
                ) {
                    Column(
                        modifier = Modifier
                            .padding(10.dp)
                            .verticalScroll(rememberScrollState()),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        conversation.forEach { turn ->
                            val isAi = turn.role == "assistant"
                            Text(
                                text = "${if (isAi) "🤖 এআই: " else "👤 আপনি: "}${turn.text}",
                                fontSize = 12.sp,
                                color = if (isAi) BrandPurple else if (isDarkMode) Color.White else Color(0xFF0F172A),
                                fontWeight = if (isAi) FontWeight.SemiBold else FontWeight.Normal
                            )
                        }
                    }
                }

                // Input Box
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    OutlinedTextField(
                        value = queryText,
                        onValueChange = { queryText = it },
                        placeholder = { Text(if (isBangla) "প্রশ্ন লিখুন..." else "Ask question...") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                    IconButton(
                        onClick = { launchSpeech() },
                        modifier = Modifier
                            .size(42.dp)
                            .clip(CircleShape)
                            .background(BrandPurple.copy(alpha = 0.12f))
                    ) {
                        Icon(Icons.Default.Mic, contentDescription = "Voice Input", tint = BrandPurple)
                    }
                    Button(
                        onClick = { sendQuestion(queryText) },
                        enabled = queryText.isNotBlank() && !isLoading,
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                        if (isLoading) {
                            CircularProgressIndicator(modifier = Modifier.size(16.dp), color = Color.White, strokeWidth = 2.dp)
                        } else {
                            Text(if (isBangla) "পাঠান" else "Send")
                        }
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text(if (isBangla) "বন্ধ করুন" else "Close")
            }
        }
    )
}

// ══════════════════════════════════════════════════════════════════════════════
// Dialog 4: Instant Voice Record to AI (100% Twilio-Free)
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun InstantVoiceRecordToAiDialog(
    isDarkMode: Boolean,
    isBangla: Boolean,
    voiceSettings: AiVoiceSettingsState,
    viewModel: AppViewModel,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    var isListening by remember { mutableStateOf(false) }
    var statusText by remember { mutableStateOf(if (isBangla) "মাইক্রোফোনে ট্যাপ করে কথা বলুন..." else "Tap microphone to speak...") }
    var capturedSpeech by remember { mutableStateOf("") }
    var aiReplyText by remember { mutableStateOf("") }
    var isProcessing by remember { mutableStateOf(false) }

    // TTS Engine initialization for speaking AI response aloud
    var tts by remember { mutableStateOf<TextToSpeech?>(null) }
    DisposableEffect(Unit) {
        var engineRef: TextToSpeech? = null
        val engine = TextToSpeech(context) { status ->
            if (status == TextToSpeech.SUCCESS) {
                val locale = if (voiceSettings.language.startsWith("bn")) Locale("bn", "BD") else Locale.US
                engineRef?.language = locale
            }
        }
        engineRef = engine
        tts = engine
        onDispose {
            engine.stop()
            engine.shutdown()
        }
    }

    // Android Native Speech Recognizer Launcher (Zero Twilio Dependency!)
    val speechLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) { result ->
        isListening = false
        if (result.resultCode == android.app.Activity.RESULT_OK) {
            val spoken = result.data?.getStringArrayListExtra(RecognizerIntent.EXTRA_RESULTS)?.firstOrNull()
            if (!spoken.isNullOrBlank()) {
                capturedSpeech = spoken
                statusText = if (isBangla) "এআই প্রসেস করছে..." else "Processing with AI..."
                isProcessing = true
                viewModel.sendInstantRecordToAi(spoken) { success, _, reply ->
                    isProcessing = false
                    aiReplyText = reply
                    statusText = if (success) (if (isBangla) "এআই উত্তর দিয়েছে" else "AI Responded") else (if (isBangla) "ত্রুটি হয়েছে" else "Error")
                    // Speak aloud via TTS
                    tts?.speak(reply, TextToSpeech.QUEUE_FLUSH, null, "ai_voice_reply")
                }
            } else {
                statusText = if (isBangla) "কোনো কথা শোনা যায়নি, আবার চেষ্টা করুন।" else "No speech detected, try again."
            }
        }
    }

    val launchSpeech = {
        try {
            isListening = true
            val intent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
                putExtra(RecognizerIntent.EXTRA_LANGUAGE, if (voiceSettings.language.startsWith("bn")) "bn-BD" else "en-US")
                putExtra(RecognizerIntent.EXTRA_PROMPT, if (isBangla) "দোকানের সময়, অর্ডার বা হিসাব নিয়ে কথা বলুন..." else "Speak your question...")
            }
            speechLauncher.launch(intent)
        } catch (e: Exception) {
            isListening = false
            Toast.makeText(context, "Speech recognition not available on this device", Toast.LENGTH_SHORT).show()
        }
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Icon(Icons.Default.Mic, contentDescription = null, tint = Color(0xFF10B981))
                Text(
                    text = if (isBangla) "ইনস্ট্যান্ট ভয়েস রেকর্ড → এআই" else "Instant Voice Record → AI",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        text = {
            Column(
                modifier = Modifier.fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                Text(
                    text = statusText,
                    fontSize = 12.sp,
                    color = if (isDarkMode) Color.White.copy(alpha = 0.8f) else Color(0xFF475569),
                    textAlign = TextAlign.Center
                )

                // Large Glowing Record Button
                Box(
                    modifier = Modifier
                        .size(80.dp)
                        .clip(CircleShape)
                        .background(
                            if (isListening || isProcessing)
                                Brush.radialGradient(listOf(Color(0xFFEF4444), Color(0xFFDC2626)))
                            else
                                Brush.linearGradient(listOf(Color(0xFF4F46E5), Color(0xFF7C3AED)))
                        )
                        .clickable { if (!isProcessing) launchSpeech() },
                    contentAlignment = Alignment.Center
                ) {
                    if (isProcessing) {
                        CircularProgressIndicator(color = Color.White, modifier = Modifier.size(36.dp), strokeWidth = 3.dp)
                    } else {
                        Icon(
                            imageVector = if (isListening) Icons.Default.GraphicEq else Icons.Default.Mic,
                            contentDescription = "Record",
                            tint = Color.White,
                            modifier = Modifier.size(38.dp)
                        )
                    }
                }

                Text(
                    text = if (isBangla) "মাইক্রোফোনে ট্যাপ করে কথা বলুন (টুইলিও ছাড়া ১০০% ফ্রি)" else "Tap to Speak (100% Free, Zero Twilio)",
                    fontSize = 10.5.sp,
                    color = Color.Gray
                )

                // Captured User Speech Box
                if (capturedSpeech.isNotBlank()) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        color = if (isDarkMode) Color(0xFF1E293B) else Color(0xFFF1F5F9)
                    ) {
                        Column(modifier = Modifier.padding(10.dp)) {
                            Text(
                                text = if (isBangla) "👤 আপনি বলেছেন:" else "👤 You said:",
                                fontSize = 10.5.sp,
                                fontWeight = FontWeight.Bold,
                                color = BrandPurple
                            )
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(capturedSpeech, fontSize = 12.sp)
                        }
                    }
                }

                // AI Spoken Reply Box
                if (aiReplyText.isNotBlank()) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        color = if (isDarkMode) Color(0xFF261D45) else Color(0xFFEEF2FF),
                        border = BorderStroke(1.dp, BrandPurple.copy(alpha = 0.3f))
                    ) {
                        Column(modifier = Modifier.padding(10.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "🤖 ${voiceSettings.agentName} (এআই ভয়েস উত্তর):",
                                    fontSize = 10.5.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color(0xFF10B981)
                                )
                                IconButton(
                                    onClick = { tts?.speak(aiReplyText, TextToSpeech.QUEUE_FLUSH, null, null) },
                                    modifier = Modifier.size(24.dp)
                                ) {
                                    Icon(Icons.Default.VolumeUp, null, tint = Color(0xFF10B981), modifier = Modifier.size(16.dp))
                                }
                            }
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(aiReplyText, fontSize = 12.sp, lineHeight = 16.sp)
                        }
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text(if (isBangla) "সমাপ্ত" else "Done")
            }
        }
    )
}

// ══════════════════════════════════════════════════════════════════════════════
// Component: AI Mass Campaign Feedback Spreadsheet View
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun CampaignFeedbackSpreadsheetView(
    feedbacks: List<AiCampaignFeedbackItem>,
    isDarkMode: Boolean,
    isBangla: Boolean,
    viewModel: AppViewModel,
    onOpenNewCampaign: () -> Unit
) {
    val context = LocalContext.current
    var searchQuery by remember { mutableStateOf("") }
    var selectedFilter by remember { mutableStateOf("ALL") } // "ALL", "ATTENDING", "INTERESTED", "DECLINED", "PENDING"
    var selectedFeedbackForDetail by remember { mutableStateOf<AiCampaignFeedbackItem?>(null) }

    val exportCsvLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.CreateDocument("text/csv")
    ) { uri: Uri? ->
        if (uri != null) {
            viewModel.exportCampaignFeedbackCsv(context, uri) { success, msg ->
                Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
            }
        }
    }

    val cardBg = if (isDarkMode) Color(0xFF181524) else Color(0xFFFFFFFF)
    val cardBorder = if (isDarkMode) Color(0xFF2C2640) else Color(0xFFE2E8F0)
    val textPrimary = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDarkMode) Color.White.copy(alpha = 0.7f) else Color(0xFF64748B)

    val attendingCount = feedbacks.count { it.decision == "ATTENDING" }
    val interestedCount = feedbacks.count { it.decision == "INTERESTED" }
    val declinedCount = feedbacks.count { it.decision == "DECLINED" }
    val pendingCount = feedbacks.count { it.decision == "PENDING" }

    val filteredList = feedbacks.filter { item ->
        val matchesSearch = if (searchQuery.isBlank()) true else {
            item.customerName.contains(searchQuery, ignoreCase = true) ||
            item.customerPhone.contains(searchQuery) ||
            item.campaignTitle.contains(searchQuery, ignoreCase = true) ||
            item.feedbackText.contains(searchQuery, ignoreCase = true)
        }
        val matchesFilter = when (selectedFilter) {
            "ATTENDING" -> item.decision == "ATTENDING"
            "INTERESTED" -> item.decision == "INTERESTED"
            "DECLINED" -> item.decision == "DECLINED"
            "PENDING" -> item.decision == "PENDING"
            else -> true
        }
        matchesSearch && matchesFilter
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        // ── 1. KPI Summary Banner Card ──
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = cardBg),
                border = BorderStroke(1.dp, cardBorder)
            ) {
                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Icon(Icons.Default.TableChart, null, tint = BrandPurple, modifier = Modifier.size(20.dp))
                            Text(
                                text = if (isBangla) "ফিডব্যাক স্প্রেডশিট সামারি" else "Feedback Spreadsheet Summary",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = textPrimary
                            )
                        }
                        Surface(
                            shape = RoundedCornerShape(8.dp),
                            color = BrandPurple.copy(alpha = 0.15f)
                        ) {
                            Text(
                                text = if (isBangla) "মোট কল: ${feedbacks.size}" else "Total: ${feedbacks.size}",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = BrandPurple,
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                            )
                        }
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        // Attending
                        Surface(
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(10.dp),
                            color = Color(0xFF10B981).copy(alpha = 0.12f),
                            border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.3f))
                        ) {
                            Column(modifier = Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                Text("$attendingCount", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = Color(0xFF10B981))
                                Text(if (isBangla) "উপস্থিত" else "Attending", fontSize = 10.sp, color = textSecondary)
                            }
                        }
                        // Interested
                        Surface(
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(10.dp),
                            color = Color(0xFF3B82F6).copy(alpha = 0.12f),
                            border = BorderStroke(1.dp, Color(0xFF3B82F6).copy(alpha = 0.3f))
                        ) {
                            Column(modifier = Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                Text("$interestedCount", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = Color(0xFF3B82F6))
                                Text(if (isBangla) "আগ্রহী" else "Interested", fontSize = 10.sp, color = textSecondary)
                            }
                        }
                        // Declined
                        Surface(
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(10.dp),
                            color = Color(0xFFEF4444).copy(alpha = 0.12f),
                            border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.3f))
                        ) {
                            Column(modifier = Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                Text("$declinedCount", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = Color(0xFFEF4444))
                                Text(if (isBangla) "অনুপস্থিত" else "Declined", fontSize = 10.sp, color = textSecondary)
                            }
                        }
                        // Pending
                        Surface(
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(10.dp),
                            color = Color(0xFFF59E0B).copy(alpha = 0.12f),
                            border = BorderStroke(1.dp, Color(0xFFF59E0B).copy(alpha = 0.3f))
                        ) {
                            Column(modifier = Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                Text("$pendingCount", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = Color(0xFFF59E0B))
                                Text(if (isBangla) "অপেক্ষমান" else "Pending", fontSize = 10.sp, color = textSecondary)
                            }
                        }
                    }
                }
            }
        }

        // ── 2. Action Bar: New Campaign, Export CSV/Excel, Share ──
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Launch New Campaign
                Button(
                    onClick = onOpenNewCampaign,
                    modifier = Modifier
                        .weight(1.3f)
                        .height(42.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                    contentPadding = PaddingValues(horizontal = 8.dp)
                ) {
                    Icon(Icons.Default.Campaign, null, modifier = Modifier.size(16.dp), tint = Color.White)
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = if (isBangla) "+ নতুন ক্যাম্পেইন কল" else "+ New Campaign",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                }

                // Export CSV / Excel Button
                Button(
                    onClick = {
                        exportCsvLauncher.launch("SwapnoPay_AI_Feedback_Spreadsheet_${System.currentTimeMillis()}.csv")
                    },
                    modifier = Modifier
                        .weight(1.2f)
                        .height(42.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981)),
                    contentPadding = PaddingValues(horizontal = 8.dp)
                ) {
                    Icon(Icons.Default.FileDownload, null, modifier = Modifier.size(16.dp), tint = Color.White)
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = if (isBangla) "এক্সপোর্ট CSV" else "Export CSV",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                }

                // Share Summary Report
                OutlinedButton(
                    onClick = {
                        val report = StringBuilder()
                        report.append("📊 স্বপ্নপে এআই কল ক্যাম্পেইন ও ফিডব্যাক রিপোর্ট\n")
                        report.append("═══════════════════════════════\n")
                        report.append("মোট কল সংখ্যা: ${feedbacks.size}\n")
                        report.append("✅ অংশগ্রহণ করবেন: $attendingCount\n")
                        report.append("👍 আগ্রহী: $interestedCount\n")
                        report.append("❌ অনিচ্ছুক: $declinedCount\n")
                        report.append("⏳ অপেক্ষমান: $pendingCount\n\n")
                        report.append("গ্রাহকদের সরাসরি মতামত তালিকা:\n")
                        feedbacks.take(10).forEachIndexed { i, fb ->
                            report.append("${i + 1}. ${fb.customerName} (${fb.customerPhone}): [${fb.decision}] \"${fb.feedbackText}\"\n")
                        }
                        try {
                            val intent = Intent(Intent.ACTION_SEND).apply {
                                type = "text/plain"
                                putExtra(Intent.EXTRA_SUBJECT, "SwapnoPay AI Feedback Report")
                                putExtra(Intent.EXTRA_TEXT, report.toString())
                            }
                            context.startActivity(Intent.createChooser(intent, "রিপোর্ট শেয়ার করুন"))
                        } catch (e: Exception) {
                            Toast.makeText(context, "শেয়ার করা যায়নি", Toast.LENGTH_SHORT).show()
                        }
                    },
                    modifier = Modifier.height(42.dp),
                    shape = RoundedCornerShape(10.dp),
                    border = BorderStroke(1.dp, BrandPurple),
                    contentPadding = PaddingValues(horizontal = 10.dp)
                ) {
                    Icon(Icons.Default.Share, null, modifier = Modifier.size(16.dp), tint = BrandPurple)
                }
            }
        }

        // ── 3. Search & Filter Bar ──
        item {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { searchQuery = it },
                    placeholder = {
                        Text(if (isBangla) "কাস্টমার নাম, ফোন বা ক্যাম্পেইন খুঁজুন..." else "Search customer, phone or feedback...", fontSize = 12.sp)
                    },
                    leadingIcon = {
                        Icon(Icons.Default.Search, null, modifier = Modifier.size(18.dp), tint = textSecondary)
                    },
                    trailingIcon = {
                        if (searchQuery.isNotEmpty()) {
                            IconButton(onClick = { searchQuery = "" }) {
                                Icon(Icons.Default.Close, null, modifier = Modifier.size(16.dp))
                            }
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = cardBg,
                        unfocusedContainerColor = cardBg,
                        focusedBorderColor = BrandPurple,
                        unfocusedBorderColor = cardBorder
                    ),
                    singleLine = true
                )

                // Horizontal Filter Chips
                LazyRow(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    val filters = listOf(
                        "ALL" to (if (isBangla) "সকল (${feedbacks.size})" else "All (${feedbacks.size})"),
                        "ATTENDING" to (if (isBangla) "উপস্থিত ($attendingCount)" else "Attending ($attendingCount)"),
                        "INTERESTED" to (if (isBangla) "আগ্রহী ($interestedCount)" else "Interested ($interestedCount)"),
                        "DECLINED" to (if (isBangla) "অনিচ্ছুক ($declinedCount)" else "Declined ($declinedCount)"),
                        "PENDING" to (if (isBangla) "অপেক্ষমান ($pendingCount)" else "Pending ($pendingCount)")
                    )

                    items(filters) { (code, label) ->
                        val isSelected = selectedFilter == code
                        Surface(
                            shape = RoundedCornerShape(20.dp),
                            color = if (isSelected) BrandPurple else cardBg,
                            border = BorderStroke(1.dp, if (isSelected) BrandPurple else cardBorder),
                            modifier = Modifier.clickable { selectedFilter = code }
                        ) {
                            Text(
                                text = label,
                                fontSize = 11.5.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                color = if (isSelected) Color.White else textPrimary,
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                            )
                        }
                    }
                }
            }
        }

        // ── 4. Spreadsheet Table Rows ──
        if (filteredList.isEmpty()) {
            item {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 40.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Icon(Icons.Default.FilterListOff, null, modifier = Modifier.size(44.dp), tint = textSecondary)
                        Text(
                            text = if (isBangla) "কোনো ফলাফল পাওয়া যায়নি" else "No matching feedback found",
                            color = textSecondary,
                            fontSize = 13.sp
                        )
                        Button(
                            onClick = onOpenNewCampaign,
                            colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                            shape = RoundedCornerShape(10.dp)
                        ) {
                            Text(if (isBangla) "নতুন ক্যাম্পেইন চালু করুন" else "Start New Campaign", fontSize = 12.sp)
                        }
                    }
                }
            }
        } else {
            items(filteredList) { item ->
                CampaignFeedbackCard(
                    item = item,
                    isDarkMode = isDarkMode,
                    isBangla = isBangla,
                    onClick = { selectedFeedbackForDetail = item }
                )
            }
        }
    }

    // Detail Dialog
    if (selectedFeedbackForDetail != null) {
        CampaignFeedbackDetailDialog(
            item = selectedFeedbackForDetail!!,
            isDarkMode = isDarkMode,
            isBangla = isBangla,
            onDismiss = { selectedFeedbackForDetail = null }
        )
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// Component: AI Campaign Feedback Card with Color-Coded Decisions & Quotes
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun CampaignFeedbackCard(
    item: AiCampaignFeedbackItem,
    isDarkMode: Boolean,
    isBangla: Boolean,
    onClick: () -> Unit
) {
    val context = LocalContext.current
    val cardBg = if (isDarkMode) Color(0xFF181524) else Color(0xFFFFFFFF)
    val cardBorder = if (isDarkMode) Color(0xFF2C2640) else Color(0xFFE2E8F0)
    val textPrimary = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDarkMode) Color.White.copy(alpha = 0.7f) else Color(0xFF64748B)

    val decisionColor = when (item.decision) {
        "ATTENDING" -> Color(0xFF10B981)
        "INTERESTED" -> Color(0xFF3B82F6)
        "DECLINED" -> Color(0xFFEF4444)
        else -> Color(0xFFF59E0B)
    }

    val decisionText = when (item.decision) {
        "ATTENDING" -> if (isBangla) "উপস্থিত হবেন" else "Attending"
        "INTERESTED" -> if (isBangla) "আগ্রহী" else "Interested"
        "DECLINED" -> if (isBangla) "অনিচ্ছুক" else "Declined"
        else -> if (isBangla) "অপেক্ষমান" else "Pending"
    }

    val decisionIcon = when (item.decision) {
        "ATTENDING" -> Icons.Default.CheckCircle
        "INTERESTED" -> Icons.Default.ThumbUp
        "DECLINED" -> Icons.Default.Cancel
        else -> Icons.Default.HourglassEmpty
    }

    val sentimentEmoji = when (item.sentiment) {
        "POSITIVE" -> "😊 ইতিবাচক"
        "NEGATIVE" -> "🙁 নেতিবাচক"
        else -> "😐 নিরপেক্ষ"
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder)
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            // Row 1: Customer Name, Phone & Decision Badge
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
                            .background(BrandPurple.copy(alpha = 0.15f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Default.Person, null, tint = BrandPurple, modifier = Modifier.size(20.dp))
                    }
                    Column {
                        Text(
                            text = item.customerName,
                            fontWeight = FontWeight.Bold,
                            fontSize = 14.sp,
                            color = textPrimary
                        )
                        Text(
                            text = item.customerPhone,
                            fontSize = 11.5.sp,
                            color = textSecondary
                        )
                    }
                }

                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = decisionColor.copy(alpha = 0.15f),
                    border = BorderStroke(1.dp, decisionColor.copy(alpha = 0.4f))
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(decisionIcon, null, tint = decisionColor, modifier = Modifier.size(13.dp))
                        Text(
                            text = decisionText,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = decisionColor
                        )
                    }
                }
            }

            // Row 2: Campaign Title & Sentiment
            Surface(
                shape = RoundedCornerShape(6.dp),
                color = if (isDarkMode) Color(0xFF231E33) else Color(0xFFF1F5F9),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                        Icon(Icons.Default.Campaign, null, tint = BrandPurple, modifier = Modifier.size(14.dp))
                        Text(
                            text = item.campaignTitle,
                            fontSize = 11.5.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = textPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    Text(
                        text = sentimentEmoji,
                        fontSize = 10.5.sp,
                        color = textSecondary
                    )
                }
            }

            // Row 3: Verbatim Customer Spoken Feedback
            Surface(
                shape = RoundedCornerShape(8.dp),
                color = if (isDarkMode) Color(0xFF1E1A2C) else Color(0xFFF8FAFC),
                border = BorderStroke(1.dp, cardBorder.copy(alpha = 0.7f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(10.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.Top
                ) {
                    Icon(
                        Icons.Default.FormatQuote,
                        null,
                        tint = BrandPurple.copy(alpha = 0.6f),
                        modifier = Modifier.size(18.dp)
                    )
                    Text(
                        text = if (item.feedbackText.isNotBlank()) item.feedbackText else (if (isBangla) "কোনো মতামত পাওয়া যায়নি" else "No feedback recorded"),
                        fontSize = 12.sp,
                        color = textPrimary,
                        lineHeight = 17.sp
                    )
                }
            }

            // Row 4: Duration, Time & Quick Call
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                        Icon(Icons.Default.Schedule, null, tint = textSecondary, modifier = Modifier.size(12.dp))
                        Text(item.callDuration, fontSize = 11.sp, color = textSecondary)
                    }
                    Text("•", fontSize = 10.sp, color = textSecondary)
                    Text(item.createdAt, fontSize = 11.sp, color = textSecondary)
                }

                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    if (item.customerPhone.isNotBlank()) {
                        IconButton(
                            onClick = {
                                try {
                                    val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:${item.customerPhone}"))
                                    context.startActivity(intent)
                                } catch (e: Exception) {
                                    Toast.makeText(context, "কল করা সম্ভব হয়নি", Toast.LENGTH_SHORT).show()
                                }
                            },
                            modifier = Modifier.size(32.dp)
                        ) {
                            Icon(Icons.Default.Phone, null, tint = Color(0xFF10B981), modifier = Modifier.size(18.dp))
                        }
                    }
                }
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// Component: AI Feedback Detail Dialog
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun CampaignFeedbackDetailDialog(
    item: AiCampaignFeedbackItem,
    isDarkMode: Boolean,
    isBangla: Boolean,
    onDismiss: () -> Unit
) {
    val textPrimary = if (isDarkMode) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDarkMode) Color.White.copy(alpha = 0.7f) else Color(0xFF64748B)

    val decisionColor = when (item.decision) {
        "ATTENDING" -> Color(0xFF10B981)
        "INTERESTED" -> Color(0xFF3B82F6)
        "DECLINED" -> Color(0xFFEF4444)
        else -> Color(0xFFF59E0B)
    }

    val decisionText = when (item.decision) {
        "ATTENDING" -> if (isBangla) "উপস্থিত হবেন (Attending)" else "Attending"
        "INTERESTED" -> if (isBangla) "আগ্রহী (Interested)" else "Interested"
        "DECLINED" -> if (isBangla) "অনিচ্ছুক (Declined)" else "Declined"
        else -> if (isBangla) "অপেক্ষমান (Pending)" else "Pending"
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Icon(Icons.Default.RecordVoiceOver, null, tint = BrandPurple)
                Text(
                    text = if (isBangla) "গ্রাহক ফিডব্যাক বিবরণ" else "Customer Feedback Details",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                Surface(
                    shape = RoundedCornerShape(10.dp),
                    color = decisionColor.copy(alpha = 0.12f),
                    border = BorderStroke(1.dp, decisionColor.copy(alpha = 0.3f)),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(10.dp)) {
                        Text(
                            text = if (isBangla) "এআই সনাক্তকৃত সিদ্ধান্ত:" else "AI Detected Decision:",
                            fontSize = 11.sp,
                            color = textSecondary
                        )
                        Text(
                            text = decisionText,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = decisionColor
                        )
                    }
                }

                Text(
                    text = "${if (isBangla) "গ্রাহক:" else "Customer:"} ${item.customerName} (${item.customerPhone})",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = textPrimary
                )

                Text(
                    text = "${if (isBangla) "ক্যাম্পেইন:" else "Campaign:"} ${item.campaignTitle}",
                    fontSize = 12.sp,
                    color = textSecondary
                )

                Surface(
                    shape = RoundedCornerShape(10.dp),
                    color = if (isDarkMode) Color(0xFF231E33) else Color(0xFFF1F5F9),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(10.dp)) {
                        Text(
                            text = if (isBangla) "🗣️ গ্রাহকের মূল মন্তব্য:" else "🗣️ Spoken Feedback:",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = BrandPurple
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = item.feedbackText,
                            fontSize = 13.sp,
                            lineHeight = 18.sp,
                            color = textPrimary
                        )
                    }
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(
                        text = "${if (isBangla) "কল সময়কাল:" else "Duration:"} ${item.callDuration}",
                        fontSize = 11.sp,
                        color = textSecondary
                    )
                    Text(
                        text = "${if (isBangla) "তারিখ:" else "Date:"} ${item.createdAt}",
                        fontSize = 11.sp,
                        color = textSecondary
                    )
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text(if (isBangla) "বন্ধ করুন" else "Close")
            }
        }
    )
}

// ══════════════════════════════════════════════════════════════════════════════
// Component: AI Mass Campaign Broadcast Dialog
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun CampaignBroadcastDialog(
    isDarkMode: Boolean,
    isBangla: Boolean,
    isLoading: Boolean,
    viewModel: AppViewModel,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    val customers by viewModel.customers.collectAsState()

    var campaignType by remember { mutableStateOf("MEETING_INVITE") } // "MEETING_INVITE", "DISCOUNT_OFFER", "GENERAL"
    var title by remember { mutableStateOf("বার্ষিক মার্চেন্ট ও গ্রাহক সম্মেলন ২০২৬") }
    var script by remember {
        mutableStateOf("আসসালামু আলাইকুম {customer_name}, {business_name} থেকে বলছি। আমাদের আগামী ব্যবসায়িক সম্মেলনে আপনাকে সাদর আমন্ত্রণ জানাচ্ছি। আপনি কি উক্ত অনুষ্ঠানে সশরীরে উপস্থিত থাকতে পারবেন?")
    }
    var audienceMode by remember { mutableStateOf("ALL_DEBTORS") } // "ALL_DEBTORS", "ALL_CUSTOMERS", "SAMPLE_TEST"
    var isBroadcasting by remember { mutableStateOf(false) }

    val activeRecipients = remember(audienceMode, customers) {
        when (audienceMode) {
            "ALL_DEBTORS" -> {
                customers.filter { it.currentBalance > 0 && it.phone.isNotBlank() }.map { it.name to it.phone }
            }
            "ALL_CUSTOMERS" -> {
                customers.filter { it.phone.isNotBlank() }.map { it.name to it.phone }
            }
            else -> {
                val firstWithPhone = customers.firstOrNull { it.phone.isNotBlank() }
                if (firstWithPhone != null) listOf(firstWithPhone.name to firstWithPhone.phone) else emptyList()
            }
        }
    }

    AlertDialog(
        onDismissRequest = { if (!isBroadcasting) onDismiss() },
        title = {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Icon(Icons.Default.Campaign, null, tint = BrandPurple)
                Text(
                    text = if (isBangla) "গণ এআই ভয়েস ক্যাম্পেইন ব্রডকাস্ট" else "Mass Voice Campaign Broadcast",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text(
                    text = if (isBangla)
                        "এআই স্বয়ংক্রিয়ভাবে প্রতিটি কাস্টমারকে ফোন করবে, মিটিংয়ে আমন্ত্রণ জানাবে বা ডিসকাউন্ট অফার শেয়ার করবে এবং গ্রাহকের মতামত রেকর্ড করে স্প্রেডশিটে জমা করবে।"
                    else
                        "AI calls each customer, announces your meeting or discount offer, and records their verbal feedback into a spreadsheet.",
                    fontSize = 11.5.sp,
                    color = if (isDarkMode) Color.White.copy(alpha = 0.7f) else Color(0xFF64748B)
                )

                // 1. Campaign Type Selection
                Text(
                    text = if (isBangla) "ক্যাম্পেইনের ধরণ নির্বাচন করুন:" else "Select Campaign Type:",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold
                )
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    // Meeting Invite
                    Surface(
                        modifier = Modifier
                            .weight(1f)
                            .clickable {
                                campaignType = "MEETING_INVITE"
                                title = "বার্ষিক মার্চেন্ট ও গ্রাহক সম্মেলন ২০২৬"
                                script = "আসসালামু আলাইকুম {customer_name}, {business_name} থেকে বলছি। আমাদের আগামী ব্যবসায়িক সম্মেলনে আপনাকে সাদর আমন্ত্রণ জানাচ্ছি। আপনি কি উক্ত অনুষ্ঠানে সশরীরে উপস্থিত থাকতে পারবেন?"
                            },
                        shape = RoundedCornerShape(8.dp),
                        color = if (campaignType == "MEETING_INVITE") BrandPurple.copy(alpha = 0.15f) else Color.Transparent,
                        border = BorderStroke(1.dp, if (campaignType == "MEETING_INVITE") BrandPurple else Color.Gray.copy(alpha = 0.3f))
                    ) {
                        Column(modifier = Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Default.Groups, null, tint = if (campaignType == "MEETING_INVITE") BrandPurple else Color.Gray, modifier = Modifier.size(20.dp))
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(if (isBangla) "মিটিং আমন্ত্রণ" else "Meeting", fontSize = 10.5.sp, fontWeight = FontWeight.Bold)
                        }
                    }

                    // Discount Offer
                    Surface(
                        modifier = Modifier
                            .weight(1f)
                            .clickable {
                                campaignType = "DISCOUNT_OFFER"
                                title = "বৈশাখী মেগা ২৫% ডিসকাউন্ট ক্যাম্পেইন"
                                script = "আসসালামু আলাইকুম {customer_name}, {business_name} থেকে একটি বিশেষ উপহার! আমাদের প্রতিষ্ঠানে চলছে মেগা ২৫% ছাড়। আপনি কি অফারের নতুন ক্যাটালগ দেখতে চান?"
                            },
                        shape = RoundedCornerShape(8.dp),
                        color = if (campaignType == "DISCOUNT_OFFER") BrandPurple.copy(alpha = 0.15f) else Color.Transparent,
                        border = BorderStroke(1.dp, if (campaignType == "DISCOUNT_OFFER") BrandPurple else Color.Gray.copy(alpha = 0.3f))
                    ) {
                        Column(modifier = Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Default.LocalOffer, null, tint = if (campaignType == "DISCOUNT_OFFER") BrandPurple else Color.Gray, modifier = Modifier.size(20.dp))
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(if (isBangla) "ডিসকাউন্ট অফার" else "Discount", fontSize = 10.5.sp, fontWeight = FontWeight.Bold)
                        }
                    }

                    // General Notice
                    Surface(
                        modifier = Modifier
                            .weight(1f)
                            .clickable {
                                campaignType = "GENERAL"
                                title = "জরুরি গ্রাহক নোটিশ ও শুভেচ্ছা বার্তা"
                                script = "আসসালামু আলাইকুম {customer_name}, {business_name} থেকে একটি জরুরি নোটিশ ও শুভেচ্ছা বার্তা জানাতে ফোন করেছি..."
                            },
                        shape = RoundedCornerShape(8.dp),
                        color = if (campaignType == "GENERAL") BrandPurple.copy(alpha = 0.15f) else Color.Transparent,
                        border = BorderStroke(1.dp, if (campaignType == "GENERAL") BrandPurple else Color.Gray.copy(alpha = 0.3f))
                    ) {
                        Column(modifier = Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Default.Campaign, null, tint = if (campaignType == "GENERAL") BrandPurple else Color.Gray, modifier = Modifier.size(20.dp))
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(if (isBangla) "ঘোষণা বার্তা" else "Notice", fontSize = 10.5.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }

                // 2. Campaign Title Field
                OutlinedTextField(
                    value = title,
                    onValueChange = { title = it },
                    label = { Text(if (isBangla) "ক্যাম্পেইন শিরোনাম" else "Campaign Title") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    singleLine = true
                )

                // 3. AI Voice Script Field
                OutlinedTextField(
                    value = script,
                    onValueChange = { script = it },
                    label = { Text(if (isBangla) "এআই কথা বলার স্ক্রিপ্ট (বাংলা)" else "AI Voice Script") },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(110.dp),
                    shape = RoundedCornerShape(10.dp),
                    maxLines = 5
                )

                // 4. Audience Target Selection
                Text(
                    text = if (isBangla) "গ্রাহক প্রাপক তালিকা (${activeRecipients.size} জন):" else "Audience Target (${activeRecipients.size}):",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold
                )
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Surface(
                        shape = RoundedCornerShape(16.dp),
                        color = if (audienceMode == "ALL_DEBTORS") BrandPurple else Color.Transparent,
                        border = BorderStroke(1.dp, if (audienceMode == "ALL_DEBTORS") BrandPurple else Color.Gray.copy(alpha = 0.4f)),
                        modifier = Modifier.clickable { audienceMode = "ALL_DEBTORS" }
                    ) {
                        Text(
                            text = if (isBangla) "বকেয়া গ্রাহক" else "All Debtors",
                            fontSize = 11.sp,
                            color = if (audienceMode == "ALL_DEBTORS") Color.White else Color.Gray,
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                        )
                    }
                    Surface(
                        shape = RoundedCornerShape(16.dp),
                        color = if (audienceMode == "ALL_CUSTOMERS") BrandPurple else Color.Transparent,
                        border = BorderStroke(1.dp, if (audienceMode == "ALL_CUSTOMERS") BrandPurple else Color.Gray.copy(alpha = 0.4f)),
                        modifier = Modifier.clickable { audienceMode = "ALL_CUSTOMERS" }
                    ) {
                        Text(
                            text = if (isBangla) "সকল গ্রাহক" else "All Customers",
                            fontSize = 11.sp,
                            color = if (audienceMode == "ALL_CUSTOMERS") Color.White else Color.Gray,
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                        )
                    }
                    Surface(
                        shape = RoundedCornerShape(16.dp),
                        color = if (audienceMode == "SAMPLE_TEST") BrandPurple else Color.Transparent,
                        border = BorderStroke(1.dp, if (audienceMode == "SAMPLE_TEST") BrandPurple else Color.Gray.copy(alpha = 0.4f)),
                        modifier = Modifier.clickable { audienceMode = "SAMPLE_TEST" }
                    ) {
                        Text(
                            text = if (isBangla) "টেস্ট রান (১ জন)" else "Test Run (1)",
                            fontSize = 11.sp,
                            color = if (audienceMode == "SAMPLE_TEST") Color.White else Color.Gray,
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                        )
                    }
                }

                if (activeRecipients.isEmpty()) {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = Color(0xFFEF4444).copy(alpha = 0.12f),
                        border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.4f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = if (isBangla) "কোনো ফোন নম্বর যুক্ত গ্রাহক পাওয়া যায়নি। অনুগ্রহ করে কাস্টমার সেকশনে গিয়ে গ্রাহকদের নাম ও মোবাইল নম্বর সংরক্ষণ করুন।"
                                   else "No customers with valid phone numbers found. Please add customer phone numbers first.",
                            fontSize = 11.5.sp,
                            color = Color(0xFFDC2626),
                            modifier = Modifier.padding(10.dp)
                        )
                    }
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    if (title.isBlank() || script.isBlank()) {
                        Toast.makeText(context, if (isBangla) "শিরোনাম ও স্ক্রিপ্ট পূরণ করুন" else "Please fill title & script", Toast.LENGTH_SHORT).show()
                        return@Button
                    }
                    if (activeRecipients.isEmpty()) {
                        Toast.makeText(context, if (isBangla) "প্রাপক তালিকা খালি" else "Recipient list is empty", Toast.LENGTH_SHORT).show()
                        return@Button
                    }
                    isBroadcasting = true
                    viewModel.startMassVoiceCampaign(
                        title = title,
                        type = campaignType,
                        script = script,
                        recipients = activeRecipients
                    ) { success, msg ->
                        isBroadcasting = false
                        onDismiss()
                        Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                    }
                },
                enabled = !isBroadcasting && !isLoading && activeRecipients.isNotEmpty(),
                colors = ButtonDefaults.buttonColors(containerColor = BrandPurple),
                shape = RoundedCornerShape(10.dp)
            ) {
                if (isBroadcasting || isLoading) {
                    CircularProgressIndicator(color = Color.White, modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(if (isBangla) "কলিং চলছে..." else "Calling...", fontSize = 12.sp)
                } else {
                    Icon(Icons.Default.PhoneCallback, null, modifier = Modifier.size(16.dp), tint = Color.White)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(if (isBangla) "এআই কল ব্রডকাস্ট শুরু" else "Start AI Broadcast", fontSize = 12.sp)
                }
            }
        },
        dismissButton = {
            if (!isBroadcasting) {
                TextButton(onClick = onDismiss) {
                    Text(if (isBangla) "বাতিল" else "Cancel")
                }
            }
        }
    )
}

