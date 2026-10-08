package com.example.ui

import android.widget.Toast
import android.app.DatePickerDialog
import java.util.Calendar
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import coil.compose.AsyncImage
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import android.webkit.WebView
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.foundation.gestures.detectVerticalDragGestures

/**
 * Pixel-Perfect Form Builder Studio with 4 Navigation Tabs (Builder, Settings, Integrations, Responses)
 * plus Publish Flow & Live Preview modals. Matches exact uploaded design system.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FormBuilderStudioScreen(viewModel: AppViewModel) {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()
    SideEffect { isDarkModeGlobal = isDark }
    val currentRoute by viewModel.currentScreen.collectAsState()

    // Determine default tab based on incoming route
    val defaultTab = remember(currentRoute) {
        when (currentRoute) {
            "FormResponses", "FormSubmissions" -> "Responses"
            "FormSettings" -> "Settings"
            "FormIntegrations" -> "Integrations"
            else -> "Builder"
        }
    }

    var activeTab by remember { mutableStateOf(defaultTab) }
    var showPublishModal by remember { mutableStateOf(false) }
    var showPreviewModal by remember { mutableStateOf(false) }
    var showEditTitleModal by remember { mutableStateOf(false) }
    val clipboardManager = LocalClipboardManager.current
    var showMoreDropdown by remember { mutableStateOf(false) }
    var showMoreOptionsModal by remember { mutableStateOf(false) }
    var showExportJsonModal by remember { mutableStateOf(false) }
    var showImportJsonModal by remember { mutableStateOf(false) }
    var showResetFieldsDialog by remember { mutableStateOf(false) }
    var showDeleteFormDialog by remember { mutableStateOf(false) }
    var showWebAppPreviewModal by remember { mutableStateOf(false) }
    var showAiRefineModal by remember { mutableStateOf(false) }
    var aiRefineFeedbackText by remember { mutableStateOf("") }
    val isAiGenerating by viewModel.isAiFormGenerating.collectAsState()

    fun duplicateCurrentForm() {
        try {
            val currentId = viewModel.activeFormId.value
            val current = viewModel.hostedFormsList.value.find { it.id == currentId }
            val newId = java.util.UUID.randomUUID().toString()
            val currentTitle = viewModel.formTitle.value
            val newTitle = "${currentTitle.ifEmpty { "Form" }} (Copy)"
            val newSlug = "pay-${newTitle.replace(" ", "-").replace(Regex("[^a-zA-Z0-9-]"), "")}-${System.currentTimeMillis().toString().takeLast(4)}"
            val snapshot = (current ?: HostedFormModel(id = currentId)).copy(
                id = newId,
                title = newTitle,
                slug = newSlug,
                description = viewModel.formDescription.value,
                status = "DRAFT",
                templateKey = viewModel.formTemplateKey.value,
                themeConfig = viewModel.formThemeConfig.value.copy(),
                fields = viewModel.formFieldsList.value.map { it.copy(id = java.util.UUID.randomUUID().toString()) },
                products = viewModel.formProductsList.value.map { it.copy(id = java.util.UUID.randomUUID().toString()) },
                pages = viewModel.formPagesList.value.map { it.copy(id = java.util.UUID.randomUUID().toString()) }
            )
            viewModel.hostedFormsList.value = listOf(snapshot) + viewModel.hostedFormsList.value
            viewModel.selectHostedForm(newId)
            viewModel.saveActiveFormToHostedList()
            Toast.makeText(context, "ফর্ম ডুপ্লিকেট করা হয়েছে: $newTitle", Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Toast.makeText(context, "Error duplicating form: ${e.message}", Toast.LENGTH_SHORT).show()
        }
    }

    fun shareFormLink() {
        viewModel.saveActiveFormToHostedList()
        val form = viewModel.hostedFormsList.value.find { it.id == viewModel.activeFormId.value }
        if (form != null) {
            viewModel.registerBrandedHostedFormRoute(form)
        }
        val shareUrl = viewModel.hostedFormPublicUrl()
        if (shareUrl.isNotBlank()) {
            val currentTitle = viewModel.formTitle.value
            clipboardManager.setText(AnnotatedString(shareUrl))
            val intent = android.content.Intent(android.content.Intent.ACTION_SEND).apply {
                type = "text/plain"
                putExtra(android.content.Intent.EXTRA_SUBJECT, currentTitle)
                putExtra(android.content.Intent.EXTRA_TEXT, "Checkout using $currentTitle: $shareUrl")
            }
            context.startActivity(android.content.Intent.createChooser(intent, "Share Payment Form"))
            Toast.makeText(context, "লিংক কপি ও VPS-এ সিঙ্ক করা হয়েছে...", Toast.LENGTH_SHORT).show()
        } else {
            Toast.makeText(context, "Unable to generate form share link.", Toast.LENGTH_SHORT).show()
        }
    }

    // Form Customization State
    val formTitle by viewModel.formTitle.collectAsState()
    val formHeaderTitle = formTitle
    val formDescription by viewModel.formDescription.collectAsState()

    // Theme Color Palette
    val bgCanvas = if (isDark) Color(0xFF090806) else Color(0xFFF8F9FF)
    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFD4E4FC)
    val textPrimary = if (isDark) Color.White else Color(0xFF0D1C2E)
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val goldPrimary = if (isDark) Color(0xFFE5A93C) else Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)
    val goldDarkBg = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0)

    Scaffold(
        containerColor = bgCanvas,
        topBar = {
            Surface(
                color = cardBg,
                shadowElevation = 2.dp,
                border = BorderStroke(1.dp, cardBorder)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .statusBarsPadding()
                ) {
                    // Header Row
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 12.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        // Left: Back Arrow + Editable Form Name
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                            modifier = Modifier.weight(1f).padding(end = 6.dp)
                        ) {
                            IconButton(
                                onClick = { viewModel.navigateTo("PaymentForms") },
                                modifier = Modifier.size(36.dp)
                            ) {
                                Icon(
                                    Icons.AutoMirrored.Filled.ArrowBack,
                                    contentDescription = "Back",
                                    tint = textPrimary,
                                    modifier = Modifier.size(20.dp)
                                )
                            }

                            Text(
                                text = formTitle,
                                fontSize = 15.sp,
                                fontWeight = FontWeight.Bold,
                                color = textPrimary,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                                modifier = Modifier.weight(1f, fill = false)
                            )

                            IconButton(
                                onClick = { showEditTitleModal = true },
                                modifier = Modifier.size(28.dp)
                            ) {
                                Icon(
                                    Icons.Outlined.Edit,
                                    contentDescription = "Edit Title",
                                    tint = textSecondary,
                                    modifier = Modifier.size(15.dp)
                                )
                            }
                        }

                        // Right Top Action Icons (Preview Eye, Publish Rocket, Share, More)
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            // Live Preview Eye Icon Button
                            IconButton(
                                onClick = { showPreviewModal = true },
                                modifier = Modifier.size(36.dp)
                            ) {
                                Icon(
                                    Icons.Outlined.Visibility,
                                    contentDescription = "Live Preview",
                                    tint = textSecondary,
                                    modifier = Modifier.size(20.dp)
                                )
                            }

                            // AI Refine Button
                            Surface(
                                onClick = { showAiRefineModal = true },
                                shape = RoundedCornerShape(18.dp),
                                color = if (isDark) Color(0xFF2E1065) else Color(0xFFF3E8FF),
                                border = BorderStroke(1.dp, Color(0xFF8B5CF6))
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 9.dp, vertical = 5.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    Icon(
                                        Icons.Default.AutoAwesome,
                                        contentDescription = "AI Refine",
                                        tint = Color(0xFF8B5CF6),
                                        modifier = Modifier.size(15.dp)
                                    )
                                    Text(
                                        text = "AI Refine",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (isDark) Color(0xFFD8B4FE) else Color(0xFF6B21A8),
                                        softWrap = false
                                    )
                                }
                            }

                            // Publish Rocket Icon Button
                            Surface(
                                onClick = { showPublishModal = true },
                                shape = RoundedCornerShape(18.dp),
                                color = goldDarkBg,
                                border = BorderStroke(1.dp, goldPrimary)
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    Icon(
                                        Icons.Outlined.RocketLaunch,
                                        contentDescription = "Publish",
                                        tint = goldText,
                                        modifier = Modifier.size(15.dp)
                                    )
                                    Text(
                                        text = "Publish",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = goldText,
                                        softWrap = false
                                    )
                                }
                            }

                            // More Menu with Dropdown & Full Modal Options
                            Box {
                                IconButton(
                                    onClick = { showMoreDropdown = true },
                                    modifier = Modifier.size(36.dp)
                                ) {
                                    Icon(
                                        Icons.Default.MoreVert,
                                        contentDescription = "More Form Options",
                                        tint = textSecondary,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }

                                DropdownMenu(
                                    expanded = showMoreDropdown,
                                    onDismissRequest = { showMoreDropdown = false },
                                    modifier = Modifier
                                        .background(cardBg)
                                        .border(1.dp, cardBorder, RoundedCornerShape(12.dp))
                                ) {
                                    DropdownMenuItem(
                                        text = { Text("Edit Form Name & Info", color = textPrimary, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.Edit, contentDescription = null, tint = goldText, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            showEditTitleModal = true
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Duplicate Form", color = textPrimary, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.ContentCopy, contentDescription = null, tint = goldText, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            duplicateCurrentForm()
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Save Draft", color = textPrimary, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.Save, contentDescription = null, tint = goldText, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            viewModel.saveFormDraft()
                                            viewModel.saveActiveFormToHostedList()
                                            Toast.makeText(context, "খসড়া সংরক্ষিত হয়েছে (Draft Saved)", Toast.LENGTH_SHORT).show()
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Share Form Link", color = textPrimary, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.Share, contentDescription = null, tint = goldText, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            shareFormLink()
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Dynamic Web App Preview", color = textPrimary, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.Language, contentDescription = null, tint = goldText, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            showWebAppPreviewModal = true
                                        }
                                    )
                                    HorizontalDivider(color = cardBorder, modifier = Modifier.padding(vertical = 4.dp))
                                    DropdownMenuItem(
                                        text = { Text("Export Config (JSON)", color = textPrimary, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.Download, contentDescription = null, tint = textSecondary, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            showExportJsonModal = true
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Import Config (JSON)", color = textPrimary, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.Upload, contentDescription = null, tint = textSecondary, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            showImportJsonModal = true
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Reset All Fields", color = textPrimary, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.Refresh, contentDescription = null, tint = textSecondary, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            showResetFieldsDialog = true
                                        }
                                    )
                                    HorizontalDivider(color = cardBorder, modifier = Modifier.padding(vertical = 4.dp))
                                    DropdownMenuItem(
                                        text = { Text("All Form Options...", color = goldText, fontWeight = FontWeight.Bold, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Default.MoreVert, contentDescription = null, tint = goldText, modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            showMoreOptionsModal = true
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Delete Form", color = Color(0xFFEF4444), fontWeight = FontWeight.SemiBold, fontSize = 13.sp) },
                                        leadingIcon = { Icon(Icons.Outlined.Delete, contentDescription = null, tint = Color(0xFFEF4444), modifier = Modifier.size(18.dp)) },
                                        onClick = {
                                            showMoreDropdown = false
                                            showDeleteFormDialog = true
                                        }
                                    )
                                }
                            }
                        }
                    }

                    // Sub-navigation Tabs Row (Builder | Settings | Integrations | Responses)
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .horizontalScroll(rememberScrollState())
                            .padding(horizontal = 12.dp),
                        horizontalArrangement = Arrangement.spacedBy(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        listOf(
                            "Builder" to Icons.Outlined.Build,
                            "Settings" to Icons.Outlined.Settings,
                            "Integrations" to Icons.Outlined.Extension,
                            "Responses" to Icons.Outlined.BarChart
                        ).forEach { (tabName, tabIcon) ->
                            val isSelected = activeTab == tabName
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                modifier = Modifier
                                    .clickable { activeTab = tabName }
                                    .padding(vertical = 8.dp, horizontal = 4.dp)
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(5.dp)
                                ) {
                                    Icon(
                                        tabIcon,
                                        contentDescription = null,
                                        tint = if (isSelected) goldText else textSecondary,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Text(
                                        text = tabName,
                                        fontSize = 13.sp,
                                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                        color = if (isSelected) goldText else textSecondary,
                                        softWrap = false,
                                        maxLines = 1
                                    )
                                }

                                Spacer(modifier = Modifier.height(6.dp))

                                // Active indicator line
                                Box(
                                    modifier = Modifier
                                        .width(if (isSelected) 44.dp else 0.dp)
                                        .height(2.5.dp)
                                        .clip(RoundedCornerShape(2.dp))
                                        .background(if (isSelected) goldText else Color.Transparent)
                                )
                            }
                        }
                    }
                }
            }
        },
        bottomBar = {
            BottomNavigationBar(
                viewModel = viewModel,
                activeTab = "Forms",
                onTabSelected = { tab -> viewModel.setTab(tab) },
                onFabClick = { viewModel.navigateTo("PaymentForms") }
            )
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            AnimatedContent(
                targetState = activeTab,
                label = "FormTabTransition"
            ) { targetTab ->
                when (targetTab) {
                    "Builder" -> FormBuilderTab(
                        viewModel = viewModel,
                        formHeaderTitle = formHeaderTitle,
                        formDescription = formDescription,
                        onOpenPublish = { showPublishModal = true },
                        onOpenPreview = { showPreviewModal = true }
                    )

                    "Settings" -> FormSettingsTab(
                        viewModel = viewModel,
                        formTitle = formTitle,
                        onTitleChange = viewModel::updateHostedFormTitle,
                        formDescription = formDescription,
                        onDescriptionChange = viewModel::updateHostedFormDescription
                    )

                    "Integrations" -> FormIntegrationsTab(
                        viewModel = viewModel,
                        onOpenWebAppPreview = { showWebAppPreviewModal = true }
                    )

                    "Responses" -> FormResponsesTab(
                        viewModel = viewModel
                    )
                }
            }
        }
    }

    // ── AI REFINE WITH USER FEEDBACK MODAL ────────────────────────────────
    if (showAiRefineModal) {
        EnterpriseGestureModal(
            onDismissRequest = { if (!isAiGenerating) showAiRefineModal = false },
            title = "Refine with Gemini AI ✨",
            subtitle = "Tell AI what changes to make (fields, pages, themes, variables)",
            icon = Icons.Default.AutoAwesome
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(
                    "Describe changes in plain natural language (e.g. 'Add a promo coupon field and delivery notes', 'Change theme to dark green with pill buttons', 'Add a 2nd page for shipping details', 'Add thank-you HTML banner with {{customer_name}}'):",
                    fontSize = 12.sp,
                    color = textSecondary
                )

                OutlinedTextField(
                    value = aiRefineFeedbackText,
                    onValueChange = { aiRefineFeedbackText = it },
                    placeholder = { Text("e.g. Add a coupon code field and change primary color to emerald green") },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(110.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = goldPrimary,
                        unfocusedBorderColor = cardBorder,
                        focusedTextColor = textPrimary,
                        unfocusedTextColor = textPrimary
                    ),
                    shape = RoundedCornerShape(12.dp)
                )

                Text("Quick Suggestions:", fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = textSecondary)

                val suggestions = listOf(
                    "Add Promo / Coupon Code field",
                    "Add Delivery Address & Shipping Speed",
                    "Change to Dark Mode Theme",
                    "Add 2nd Page for Shipping Info",
                    "Add Styled Thank-You Banner HTML Page"
                )

                Row(
                    modifier = Modifier.horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    suggestions.forEach { suggestion ->
                        Surface(
                            onClick = { aiRefineFeedbackText = suggestion },
                            shape = RoundedCornerShape(16.dp),
                            color = if (isDark) Color(0xFF1E1E2E) else Color(0xFFF1F5F9),
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Text(
                                text = suggestion,
                                fontSize = 11.sp,
                                color = textPrimary,
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                            )
                        }
                    }
                }

                val merchantGeminiKey by viewModel.geminiApiKey.collectAsState()
                val selectedModel by viewModel.selectedGeminiModel.collectAsState()

                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = if (merchantGeminiKey.isNotBlank()) Color(0xFF10B981).copy(alpha = 0.12f) else Color(0xFF6366F1).copy(alpha = 0.10f),
                    border = BorderStroke(1.dp, if (merchantGeminiKey.isNotBlank()) Color(0xFF10B981).copy(alpha = 0.35f) else Color(0xFF6366F1).copy(alpha = 0.35f))
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 10.dp, vertical = 7.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                            Icon(
                                imageVector = if (merchantGeminiKey.isNotBlank()) Icons.Default.VpnKey else Icons.Default.AutoFixHigh,
                                contentDescription = null,
                                tint = if (merchantGeminiKey.isNotBlank()) Color(0xFF10B981) else Color(0xFF6366F1),
                                modifier = Modifier.size(15.dp)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = if (merchantGeminiKey.isNotBlank()) "Copilot Key: ${merchantGeminiKey.take(6)}... ($selectedModel)" else "Copilot Key Not Set (Using Semantic Engine)",
                                fontSize = 11.sp,
                                color = textPrimary,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                        if (merchantGeminiKey.isBlank()) {
                            Text(
                                text = "Setup",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = goldPrimary,
                                modifier = Modifier
                                    .clickable {
                                        showAiRefineModal = false
                                        viewModel.navigateTo("AiCopilot")
                                    }
                                    .padding(start = 6.dp)
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(6.dp))

                Button(
                    onClick = {
                        val fb = aiRefineFeedbackText.trim()
                        if (fb.isBlank()) {
                            Toast.makeText(context, "Please describe the changes you want", Toast.LENGTH_SHORT).show()
                            return@Button
                        }
                        val basePrompt = viewModel.formTitle.value.ifBlank { "Checkout Payment Form" }
                        viewModel.generateFormWithGemini(prompt = basePrompt, feedback = fb) {
                            showAiRefineModal = false
                            aiRefineFeedbackText = ""
                            Toast.makeText(context, "Form updated with Gemini AI!", Toast.LENGTH_SHORT).show()
                        }
                    },
                    enabled = !isAiGenerating,
                    colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().height(48.dp)
                ) {
                    if (isAiGenerating) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(20.dp),
                            color = Color.Black,
                            strokeWidth = 2.dp
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Gemini AI Updating Form...", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    } else {
                        Text("Apply AI Changes ✨", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    }
                }
            }
        }
    }

    // ── PUBLISH FLOW MODAL ────────────────────────────────────────────────
    if (showPublishModal) {
        PublishFlowModal(
            viewModel = viewModel,
            onDismiss = { showPublishModal = false }
        )
    }

    // ── LIVE PREVIEW MODAL ────────────────────────────────────────────────
    if (showPreviewModal) {
        LivePreviewModal(
            viewModel = viewModel,
            formTitle = formHeaderTitle,
            formDescription = formDescription,
            onDismiss = { showPreviewModal = false }
        )
    }

    // ── EDIT TITLE & DESCRIPTION MODAL ───────────────────────────────────
    if (showEditTitleModal) {
        EnterpriseGestureModal(
            onDismissRequest = { showEditTitleModal = false },
            title = "Edit Form Details",
            subtitle = "Change name and description for this form project",
            icon = Icons.Outlined.Edit
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                OutlinedTextField(
                    value = formTitle,
                    onValueChange = viewModel::updateHostedFormTitle,
                    label = { Text("Form Name / Title") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp)
                )

                OutlinedTextField(
                    value = formDescription,
                    onValueChange = viewModel::updateHostedFormDescription,
                    label = { Text("Form Description / Subtitle") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    maxLines = 3
                )

                Button(
                    onClick = {
                        viewModel.saveFormDraft()
                        showEditTitleModal = false
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                ) {
                    Text("Save Changes", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                }
            }
        }
    }

    // ── ALL MORE FORM OPTIONS MODAL ───────────────────────────────────────
    if (showMoreOptionsModal) {
        data class FormOptionCard(
            val title: String,
            val subtitle: String,
            val icon: androidx.compose.ui.graphics.vector.ImageVector,
            val tint: Color,
            val onClick: () -> Unit
        )

        val options = listOf(
            FormOptionCard(
                title = "Edit Form Name & Info",
                subtitle = "Change internal name and description for this form",
                icon = Icons.Outlined.Edit,
                tint = goldText,
                onClick = { showMoreOptionsModal = false; showEditTitleModal = true }
            ),
            FormOptionCard(
                title = "Duplicate Form Project",
                subtitle = "Create an exact clone of this form with all fields & products",
                icon = Icons.Outlined.ContentCopy,
                tint = goldText,
                onClick = { showMoreOptionsModal = false; duplicateCurrentForm() }
            ),
            FormOptionCard(
                title = "Save Form Draft",
                subtitle = "Save current progress and field configurations locally",
                icon = Icons.Outlined.Save,
                tint = Color(0xFF10B981),
                onClick = {
                    showMoreOptionsModal = false
                    viewModel.saveFormDraft()
                    viewModel.saveActiveFormToHostedList()
                    Toast.makeText(context, "খসড়া সংরক্ষিত হয়েছে (Draft Saved)", Toast.LENGTH_SHORT).show()
                }
            ),
            FormOptionCard(
                title = "Share Public Link",
                subtitle = "Copy checkout URL and share via messaging or social apps",
                icon = Icons.Outlined.Share,
                tint = Color(0xFF38BDF8),
                onClick = { showMoreOptionsModal = false; shareFormLink() }
            ),
            FormOptionCard(
                title = "Live Customer Preview",
                subtitle = "Test and preview checkout experience across device viewports",
                icon = Icons.Outlined.Visibility,
                tint = Color(0xFFA855F7),
                onClick = { showMoreOptionsModal = false; showPreviewModal = true }
            ),
            FormOptionCard(
                title = "Dynamic Web App Preview",
                subtitle = "Test hosted dynamic web page with live store database variables",
                icon = Icons.Outlined.Language,
                tint = Color(0xFF6366F1),
                onClick = { showMoreOptionsModal = false; showWebAppPreviewModal = true }
            ),
            FormOptionCard(
                title = "Publish & Hosting Studio",
                subtitle = "Deploy form to live web link, custom slug, and iframe embeds",
                icon = Icons.Outlined.RocketLaunch,
                tint = goldText,
                onClick = { showMoreOptionsModal = false; showPublishModal = true }
            ),
            FormOptionCard(
                title = "Export Config (JSON)",
                subtitle = "Export form structure to JSON for backup or migration",
                icon = Icons.Outlined.Download,
                tint = Color(0xFF6366F1),
                onClick = { showMoreOptionsModal = false; showExportJsonModal = true }
            ),
            FormOptionCard(
                title = "Import Config (JSON)",
                subtitle = "Restore form structure from a previously exported JSON backup",
                icon = Icons.Outlined.Upload,
                tint = Color(0xFFF97316),
                onClick = { showMoreOptionsModal = false; showImportJsonModal = true }
            ),
            FormOptionCard(
                title = "Reset All Fields",
                subtitle = "Clear all custom fields from this form builder project",
                icon = Icons.Outlined.Refresh,
                tint = Color(0xFFEF4444),
                onClick = { showMoreOptionsModal = false; showResetFieldsDialog = true }
            ),
            FormOptionCard(
                title = "Delete Form",
                subtitle = "Permanently delete this form project and return to list",
                icon = Icons.Outlined.Delete,
                tint = Color(0xFFEF4444),
                onClick = { showMoreOptionsModal = false; showDeleteFormDialog = true }
            )
        )

        EnterpriseGestureModal(
            onDismissRequest = { showMoreOptionsModal = false },
            title = "More Form Options",
            subtitle = "Form builder utilities, backups, and actions",
            icon = Icons.Default.MoreVert
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                options.forEach { opt ->
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { opt.onClick() },
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (isDark) Color(0xFF16130E) else Color(0xFFF8FAFC)
                        ),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(36.dp)
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(opt.tint.copy(alpha = 0.12f)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = opt.icon,
                                    contentDescription = null,
                                    tint = opt.tint,
                                    modifier = Modifier.size(20.dp)
                                )
                            }
                            Column(modifier = Modifier.weight(1f)) {
                                Text(
                                    text = opt.title,
                                    fontSize = 13.5.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = opt.subtitle,
                                    fontSize = 11.sp,
                                    color = textSecondary,
                                    lineHeight = 14.sp
                                )
                            }
                            Icon(
                                imageVector = Icons.Default.ChevronRight,
                                contentDescription = null,
                                tint = textSecondary,
                                modifier = Modifier.size(16.dp)
                            )
                        }
                    }
                }
            }
        }
    }

    // ── EXPORT FORM JSON MODAL ────────────────────────────────────────────
    if (showExportJsonModal) {
        val exportedJson = remember(showExportJsonModal) {
            try { viewModel.exportFormConfigToJson() } catch (e: Exception) { "{}" }
        }

        EnterpriseGestureModal(
            onDismissRequest = { showExportJsonModal = false },
            title = "Export Form JSON Config",
            subtitle = "Copy or backup your complete form configuration",
            icon = Icons.Outlined.Download
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text(
                    text = "This JSON structure contains all fields, products, pages, and theme settings of '$formTitle':",
                    fontSize = 12.sp,
                    color = textSecondary
                )

                OutlinedTextField(
                    value = exportedJson,
                    onValueChange = {},
                    readOnly = true,
                    maxLines = 10,
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 140.dp, max = 240.dp),
                    shape = RoundedCornerShape(12.dp),
                    textStyle = androidx.compose.ui.text.TextStyle(fontSize = 11.sp)
                )

                Button(
                    onClick = {
                        clipboardManager.setText(AnnotatedString(exportedJson))
                        Toast.makeText(context, "Form JSON copied to clipboard!", Toast.LENGTH_SHORT).show()
                        showExportJsonModal = false
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                ) {
                    Icon(Icons.Outlined.ContentCopy, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Copy JSON to Clipboard", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                }
            }
        }
    }

    // ── IMPORT FORM JSON MODAL ────────────────────────────────────────────
    if (showImportJsonModal) {
        var importJsonText by remember { mutableStateOf("") }

        EnterpriseGestureModal(
            onDismissRequest = { showImportJsonModal = false },
            title = "Import Form JSON Config",
            subtitle = "Paste a previously exported JSON configuration to load it",
            icon = Icons.Outlined.Upload
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text(
                    text = "Paste the exported Form JSON below to restore structure and fields:",
                    fontSize = 12.sp,
                    color = textSecondary
                )

                OutlinedTextField(
                    value = importJsonText,
                    onValueChange = { importJsonText = it },
                    placeholder = { Text("Paste JSON here...", fontSize = 12.sp, color = textSecondary) },
                    maxLines = 10,
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 140.dp, max = 240.dp),
                    shape = RoundedCornerShape(12.dp),
                    textStyle = androidx.compose.ui.text.TextStyle(fontSize = 11.sp)
                )

                Button(
                    onClick = {
                        if (importJsonText.isNotBlank()) {
                            val success = viewModel.importFormConfigFromJson(importJsonText)
                            if (success) {
                                Toast.makeText(context, "Form configuration imported successfully!", Toast.LENGTH_SHORT).show()
                                showImportJsonModal = false
                            } else {
                                Toast.makeText(context, "Invalid Form JSON format. Please check and try again.", Toast.LENGTH_LONG).show()
                            }
                        }
                    },
                    enabled = importJsonText.isNotBlank(),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                ) {
                    Icon(Icons.Outlined.Upload, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Import & Apply Config", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                }
            }
        }
    }

    // ── RESET FIELDS CONFIRMATION MODAL ───────────────────────────────────
    if (showResetFieldsDialog) {
        EnterpriseGestureModal(
            onDismissRequest = { showResetFieldsDialog = false },
            title = "Reset All Form Fields?",
            subtitle = "Remove all custom fields from this form",
            icon = Icons.Outlined.Refresh
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(
                    text = "Are you sure you want to reset all custom fields from '$formTitle'? This will clear the builder canvas so you can start fresh.",
                    fontSize = 13.sp,
                    color = textPrimary,
                    lineHeight = 18.sp
                )

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    OutlinedButton(
                        onClick = { showResetFieldsDialog = false },
                        modifier = Modifier
                            .weight(1f)
                            .height(46.dp),
                        shape = RoundedCornerShape(12.dp),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Text("Cancel", color = textPrimary, fontSize = 14.sp)
                    }

                    Button(
                        onClick = {
                            viewModel.formFieldsList.value = emptyList()
                            viewModel.saveActiveFormToHostedList()
                            Toast.makeText(context, "All form fields have been reset", Toast.LENGTH_SHORT).show()
                            showResetFieldsDialog = false
                        },
                        modifier = Modifier
                            .weight(1f)
                            .height(46.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444), contentColor = Color.White)
                    ) {
                        Text("Reset All", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    }
                }
            }
        }
    }

    // ── DYNAMIC WEB APP LIVE PREVIEW MODAL ───────────────────────────────
    if (showWebAppPreviewModal) {
        val compiledHtml = remember(showWebAppPreviewModal) {
            viewModel.compileCustomWebAppHtml()
        }
        var previewViewMode by remember { mutableStateOf("SIMULATOR") } // "SIMULATOR" or "SOURCE"

        EnterpriseGestureModal(
            onDismissRequest = { showWebAppPreviewModal = false },
            title = "Dynamic Web App Live Studio",
            subtitle = "Live compiled web page with active database variables & scripts",
            icon = Icons.Outlined.Language
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Card(
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1E293B) else Color(0xFFEEF2FF)),
                    border = BorderStroke(1.dp, Color(0xFF6366F1).copy(alpha = 0.3f))
                ) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Icon(Icons.Outlined.CheckCircle, null, tint = Color(0xFF6366F1), modifier = Modifier.size(20.dp))
                        Column {
                            Text("Live Database Variables & Scripts Compiled", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = textPrimary)
                            Text("Shop details, inventory metrics, CSV datasets, and event handlers are loaded and running.", fontSize = 11.sp, color = textSecondary)
                        }
                    }
                }

                // View Mode Tabs: Interactive Simulator vs Source Code
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    listOf(
                        "SIMULATOR" to "📱 Interactive Simulator (সরাসরি চালান)",
                        "SOURCE" to "📄 HTML Source Code"
                    ).forEach { (modeKey, modeTitle) ->
                        val isSel = previewViewMode == modeKey
                        Surface(
                            onClick = { previewViewMode = modeKey },
                            shape = RoundedCornerShape(10.dp),
                            color = if (isSel) goldPrimary else (if (isDark) Color(0xFF1B1B26) else Color(0xFFF1F5F9)),
                            border = BorderStroke(1.dp, if (isSel) goldPrimary else cardBorder),
                            modifier = Modifier.weight(1f).height(40.dp)
                        ) {
                            Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(horizontal = 6.dp)) {
                                Text(
                                    text = modeTitle,
                                    fontSize = 11.5.sp,
                                    fontWeight = if (isSel) FontWeight.Bold else FontWeight.Medium,
                                    color = if (isSel) Color.Black else textPrimary
                                )
                            }
                        }
                    }
                }

                if (previewViewMode == "SIMULATOR") {
                    Card(
                        shape = RoundedCornerShape(16.dp),
                        border = BorderStroke(1.5.dp, Color(0xFF6366F1).copy(alpha = 0.5f)),
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(440.dp)
                    ) {
                        androidx.compose.ui.viewinterop.AndroidView(
                            factory = { ctx ->
                                android.webkit.WebView(ctx).apply {
                                    settings.javaScriptEnabled = true
                                    settings.domStorageEnabled = true
                                    settings.loadWithOverviewMode = true
                                    settings.useWideViewPort = true
                                    webChromeClient = android.webkit.WebChromeClient()
                                    webViewClient = android.webkit.WebViewClient()
                                    loadDataWithBaseURL("https://swapnopay.top", compiledHtml, "text/html", "UTF-8", null)
                                }
                            },
                            update = { webView ->
                                webView.loadDataWithBaseURL("https://swapnopay.top", compiledHtml, "text/html", "UTF-8", null)
                            },
                            modifier = Modifier.fillMaxSize()
                        )
                    }
                } else {
                    OutlinedTextField(
                        value = compiledHtml,
                        onValueChange = {},
                        readOnly = true,
                        label = { Text("Compiled Dynamic Web Page Output") },
                        maxLines = 16,
                        modifier = Modifier
                            .fillMaxWidth()
                            .heightIn(min = 200.dp, max = 360.dp),
                        shape = RoundedCornerShape(12.dp),
                        textStyle = androidx.compose.ui.text.TextStyle(fontSize = 11.sp)
                    )
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    OutlinedButton(
                        onClick = {
                            clipboardManager.setText(AnnotatedString(compiledHtml))
                            Toast.makeText(context, "Compiled HTML copied to clipboard!", Toast.LENGTH_SHORT).show()
                        },
                        modifier = Modifier.weight(1f).height(46.dp),
                        shape = RoundedCornerShape(12.dp),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Icon(Icons.Outlined.ContentCopy, null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text("Copy HTML", fontSize = 13.sp, color = textPrimary)
                    }

                    Button(
                        onClick = { showWebAppPreviewModal = false },
                        modifier = Modifier.weight(1f).height(46.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                    ) {
                        Text("Close Preview", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                    }
                }
            }
        }
    }

    // ── DELETE FORM CONFIRMATION MODAL ────────────────────────────────────
    if (showDeleteFormDialog) {
        EnterpriseGestureModal(
            onDismissRequest = { showDeleteFormDialog = false },
            title = "Delete Form Project?",
            subtitle = "Permanently remove this payment form",
            icon = Icons.Outlined.Delete
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text(
                    text = "Choose whether to move '$formTitle' back to draft status (unpublish) or permanently delete it from local and cloud databases.",
                    fontSize = 13.sp,
                    color = textPrimary,
                    lineHeight = 18.sp
                )

                // Option 1: Move to Draft (Instant unpublish)
                Button(
                    onClick = {
                        val currentId = viewModel.activeFormId.value
                        viewModel.deletePaymentForm(currentId, setAsDraft = true) {
                            Toast.makeText(context, "Form '$formTitle' moved to Draft", Toast.LENGTH_SHORT).show()
                        }
                        showDeleteFormDialog = false
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
                        val currentId = viewModel.activeFormId.value
                        viewModel.deletePaymentForm(currentId, setAsDraft = false) {
                            Toast.makeText(context, "Form '$formTitle' deleted permanently", Toast.LENGTH_SHORT).show()
                        }
                        showDeleteFormDialog = false
                        viewModel.navigateTo("PaymentForms")
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
                    onClick = { showDeleteFormDialog = false },
                    modifier = Modifier.fillMaxWidth().height(42.dp),
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Text("Cancel", color = textPrimary, fontSize = 13.sp)
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB 1: FORM BUILDER TAB
// ═══════════════════════════════════════════════════════════════════════════
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FormBuilderTab(
    viewModel: AppViewModel,
    formHeaderTitle: String,
    formDescription: String,
    onOpenPublish: () -> Unit,
    onOpenPreview: () -> Unit
) {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()

    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0D1C2E)
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val goldPrimary = if (isDark) Color(0xFFE5A93C) else Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)
    val goldDarkBg = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0)

    val elementChips = remember {
        listOf(
            "Product" to Icons.Outlined.ShoppingBag,
            "Promo Code" to Icons.Outlined.LocalOffer,
            "Text Field" to Icons.Outlined.TextFields,
            "Email" to Icons.Outlined.Email,
            "Phone" to Icons.Outlined.Phone,
            "Amount" to Icons.Outlined.Payments,
            "Quantity" to Icons.Outlined.Tag,
            "Date" to Icons.Outlined.CalendarToday,
            "File" to Icons.Outlined.Upload,
            "Dropdown" to Icons.Outlined.ArrowDropDownCircle,
            "Radio" to Icons.Default.RadioButtonUnchecked,
            "Multi-Select" to Icons.Default.ListAlt,
            "Toggle" to Icons.Default.Tune,
            "Checkbox" to Icons.Outlined.CheckBox,
            "Custom Code" to Icons.Outlined.Code
        )
    }

    val formFields by viewModel.formFieldsList.collectAsState()
    val formProducts by viewModel.formProductsList.collectAsState()
    val themeConfig by viewModel.formThemeConfig.collectAsState()
    val formPages by viewModel.formPagesList.collectAsState()
    val activePageIndex by viewModel.activePageIndex.collectAsState()
    val formPrimaryColor = remember(themeConfig.primaryColorHex) { runCatching { Color(android.graphics.Color.parseColor(themeConfig.primaryColorHex)) }.getOrDefault(goldPrimary) }
    val formButtonShape = remember(themeConfig.buttonShape) { when(themeConfig.buttonShape) { "PILL" -> RoundedCornerShape(24.dp); "SQUARE" -> RoundedCornerShape(4.dp); else -> RoundedCornerShape(12.dp) } }
    var dynamicFieldValues by remember { mutableStateOf(mapOf<String, String>()) }
    var formErrors by remember { mutableStateOf(mapOf<String, String>()) }
    var expandedFieldId by remember { mutableStateOf<String?>(null) }
    var isShowcaseEditorExpanded by remember { mutableStateOf(false) }
    var showAddProductDialog by remember { mutableStateOf(false) }
    var showRenamePageDialog by remember { mutableStateOf<Int?>(null) }
    var renamePageTitle by remember { mutableStateOf("") }
    var renamePageSubtitle by remember { mutableStateOf("") }
    var productTitle by remember { mutableStateOf("") }
    var productPrice by remember { mutableStateOf("") }
    var productSalePrice by remember { mutableStateOf("") }
    var productSku by remember { mutableStateOf("") }
    var productImageUrl by remember { mutableStateOf("") }
    var isUploadingDialogImage by remember { mutableStateOf(false) }

    val dialogImagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            isUploadingDialogImage = true
            viewModel.uploadProductImage(uri, context) { success, msg, uploadedUrl ->
                isUploadingDialogImage = false
                if (success && !uploadedUrl.isNullOrBlank()) {
                    productImageUrl = uploadedUrl
                    Toast.makeText(context, "Product image attached", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(context, msg.ifBlank { "Upload failed" }, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    val showcaseImagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            isUploadingDialogImage = true
            viewModel.uploadProductImage(uri, context) { success, msg, uploadedUrl ->
                isUploadingDialogImage = false
                if (success && !uploadedUrl.isNullOrBlank()) {
                    viewModel.updateFormThemeConfig(themeConfig.copy(productImageUrl = uploadedUrl))
                    Toast.makeText(context, "Product showcase image updated", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(context, msg.ifBlank { "Upload failed" }, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
        contentPadding = PaddingValues(top = 14.dp, bottom = 24.dp)
    ) {
        // Validation Error Summary Banner (if errors present)
        if (formErrors.isNotEmpty()) {
            item {
                Card(
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF2C1517) else Color(0xFFFEF2F2)),
                    border = BorderStroke(1.dp, Color(0xFFEF4444)),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(Icons.Default.ErrorOutline, null, tint = Color(0xFFEF4444), modifier = Modifier.size(20.dp))
                        Text(
                            text = "Please fix ${formErrors.size} validation error(s) below before proceeding.",
                            color = if (isDark) Color(0xFFFCA5A5) else Color(0xFF991B1B),
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }
        }

        // 0. MULTI-PAGE FLOW BAR
        item {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "FORM PAGES (${formPages.size})",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = textSecondary,
                        letterSpacing = 0.5.sp
                    )

                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        // Add Standard Page
                        Surface(
                            onClick = { viewModel.addFormPage() },
                            shape = RoundedCornerShape(8.dp),
                            color = goldDarkBg,
                            border = BorderStroke(1.dp, goldPrimary)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(13.dp), tint = goldText)
                                Text("+ Add Page", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText)
                            }
                        }

                        // Add Custom HTML Page
                        Surface(
                            onClick = { viewModel.addFormPage(isCustomHtml = true) },
                            shape = RoundedCornerShape(8.dp),
                            color = Color(0xFF6366F1).copy(alpha = 0.12f),
                            border = BorderStroke(1.dp, Color(0xFF6366F1))
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Icon(Icons.Outlined.Code, contentDescription = null, modifier = Modifier.size(13.dp), tint = Color(0xFF6366F1))
                                Text("+ HTML Page", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFF6366F1))
                            }
                        }
                    }
                }

                // Horizontal Pages Navigation Bar
                LazyRow(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    itemsIndexed(formPages) { pageIdx, page ->
                        val isSelected = pageIdx == activePageIndex
                        Surface(
                            onClick = { viewModel.activePageIndex.value = pageIdx },
                            shape = RoundedCornerShape(12.dp),
                            color = if (isSelected) (if (page.isCustomHtml) Color(0xFF6366F1) else formPrimaryColor) else cardBg,
                            border = BorderStroke(1.dp, if (isSelected) (if (page.isCustomHtml) Color(0xFF6366F1) else formPrimaryColor) else cardBorder)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Icon(
                                    if (page.isCustomHtml) Icons.Outlined.Code else Icons.Outlined.Description,
                                    contentDescription = null,
                                    tint = if (isSelected) Color.White else textSecondary,
                                    modifier = Modifier.size(14.dp)
                                )
                                Text(
                                    text = page.title.ifBlank { "Page ${pageIdx + 1}" },
                                    fontSize = 12.sp,
                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                    color = if (isSelected) Color.White else textPrimary,
                                    maxLines = 1
                                )
                                if (isSelected) {
                                    Icon(
                                        Icons.Outlined.Edit,
                                        contentDescription = "Edit Page Title",
                                        tint = Color.White.copy(alpha = 0.85f),
                                        modifier = Modifier
                                            .size(13.dp)
                                            .clickable {
                                                renamePageTitle = page.title
                                                renamePageSubtitle = page.subtitle
                                                showRenamePageDialog = pageIdx
                                            }
                                    )
                                }
                                if (formPages.size > 1 && isSelected) {
                                    Icon(
                                        Icons.Default.Close,
                                        contentDescription = "Delete Page",
                                        tint = Color.White,
                                        modifier = Modifier
                                            .size(14.dp)
                                            .clickable { viewModel.removeFormPage(pageIdx) }
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }

        // 1. Horizontal Palette Chips Row
        item {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(
                    text = "ADD FORM ELEMENTS",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = textSecondary,
                    letterSpacing = 0.5.sp
                )

                LazyRow(
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items(elementChips) { (label, icon) ->
                        Surface(
                            onClick = {
                                when (label) {
                                    "Product" -> viewModel.addFormField(FormFieldType.PRODUCT, "Product Name", "Product details / SKU")
                                    "Promo Code" -> viewModel.addFormField(FormFieldType.COUPON, "Promo / Coupon Code", "Enter promo code (e.g. SAVE10)")
                                    "Text Field" -> viewModel.addFormField(FormFieldType.NAME, "Custom Text Field", "Enter value")
                                    "Email" -> viewModel.addFormField(FormFieldType.EMAIL, "Email Address", "name@example.com")
                                    "Phone" -> viewModel.addFormField(FormFieldType.PHONE, "Phone Number", "017XXXXXXXX")
                                    "Amount" -> viewModel.addFormField(FormFieldType.CUSTOM_AMOUNT, "Payment Amount (BDT)", "0.00")
                                    "Quantity" -> viewModel.addFormField(FormFieldType.QUANTITY, "Quantity", "1")
                                    "Date" -> viewModel.addFormField(FormFieldType.DATE, "Preferred Date", "Select Date (YYYY-MM-DD)")
                                    "File" -> viewModel.addFormField(FormFieldType.FILE_UPLOAD, "File Upload", "Upload attachment")
                                    "Dropdown" -> viewModel.addFormField(FormFieldType.DROPDOWN, "Option Selection", "Select an option")
                                    "Radio" -> viewModel.addFormField(FormFieldType.RADIO, "Choice Selection", "Choose an option")
                                    "Multi-Select" -> viewModel.addFormField(FormFieldType.MULTI_SELECT, "Multiple Selection", "Select options")
                                    "Toggle" -> viewModel.addFormField(FormFieldType.TOGGLE, "Enable Feature", "Toggle switch")
                                    "Checkbox" -> viewModel.addFormField(FormFieldType.CHECKBOX, "Terms Agreement", "I agree to terms")
                                    "Custom Code" -> viewModel.addFormField(FormFieldType.CUSTOM_CODE, "Custom Code Block", "HTML/CSS block")
                                    else -> viewModel.addFormField(FormFieldType.NAME, label, "Enter $label")
                                }
                                Toast.makeText(context, "Added $label to form!", Toast.LENGTH_SHORT).show()
                            },
                            shape = RoundedCornerShape(20.dp),
                            color = cardBg,
                            border = BorderStroke(1.dp, cardBorder)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Icon(
                                    icon,
                                    contentDescription = null,
                                    tint = goldText,
                                    modifier = Modifier.size(16.dp)
                                )
                                Text(
                                    text = label,
                                    fontSize = 12.5.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = textPrimary
                                )
                            }
                        }
                    }
                }
            }
        }

        // Main Interactive Form Card Preview
        item {
            val currentPage = formPages.getOrNull(activePageIndex) ?: formPages.firstOrNull() ?: FormPageItem()
            Card(
                shape = RoundedCornerShape(24.dp),
                colors = CardDefaults.cardColors(containerColor = cardBg),
                border = BorderStroke(1.dp, if (formErrors.isNotEmpty()) Color(0xFFEF4444).copy(alpha = 0.5f) else cardBorder),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    verticalArrangement = Arrangement.spacedBy(20.dp)
                ) {
                    if (currentPage.isCustomHtml) {
                        // Full Custom HTML Page Studio on Canvas
                        Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Box(
                                        modifier = Modifier.size(36.dp).clip(RoundedCornerShape(8.dp)).background(Color(0xFF6366F1)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(Icons.Outlined.Code, contentDescription = null, tint = Color.White, modifier = Modifier.size(20.dp))
                                    }
                                    Column {
                                        Text("Full Custom HTML Page", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = textPrimary)
                                        Text("Page ${activePageIndex + 1}: Dynamic HTML with live variables", fontSize = 11.5.sp, color = textSecondary)
                                    }
                                }
                            }

                            OutlinedTextField(
                                value = currentPage.title,
                                onValueChange = { newT ->
                                    viewModel.updateFormCustomHtmlPage(activePageIndex, newT, currentPage.subtitle, currentPage.customHtmlContent, currentPage.customCssContent)
                                },
                                label = { Text("Page Title") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )

                            OutlinedTextField(
                                value = currentPage.subtitle,
                                onValueChange = { newS ->
                                    viewModel.updateFormCustomHtmlPage(activePageIndex, currentPage.title, newS, currentPage.customHtmlContent, currentPage.customCssContent)
                                },
                                label = { Text("Page Subtitle / Instructions") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )

                            Text("INSERT DYNAMIC VARIABLES:", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                            Row(
                                modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                listOf(
                                    "{{form_title}}", "{{merchant_name}}", "{{merchant_phone}}",
                                    "{{total_price}}", "{{current_date}}", "{{current_time}}", "{{invoice_number}}"
                                ).forEach { varTag ->
                                    Surface(
                                        onClick = {
                                            val newHtml = (currentPage.customHtmlContent.ifBlank { "" }) + " " + varTag
                                            viewModel.updateFormCustomHtmlPage(activePageIndex, currentPage.title, currentPage.subtitle, newHtml, currentPage.customCssContent)
                                            Toast.makeText(context, "Inserted $varTag", Toast.LENGTH_SHORT).show()
                                        },
                                        shape = RoundedCornerShape(8.dp),
                                        color = if (isDark) Color(0xFF1E2333) else Color(0xFFEEF2FF),
                                        border = BorderStroke(1.dp, Color(0xFF6366F1).copy(alpha = 0.3f))
                                    ) {
                                        Text(varTag, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF6366F1), modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp))
                                    }
                                }
                            }

                            OutlinedTextField(
                                value = currentPage.customHtmlContent,
                                onValueChange = { newHtml ->
                                    viewModel.updateFormCustomHtmlPage(activePageIndex, currentPage.title, currentPage.subtitle, newHtml, currentPage.customCssContent)
                                },
                                label = { Text("Custom HTML Body") },
                                modifier = Modifier.fillMaxWidth(),
                                minLines = 8,
                                textStyle = androidx.compose.ui.text.TextStyle(fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace, fontSize = 12.sp)
                            )

                            OutlinedTextField(
                                value = currentPage.customCssContent,
                                onValueChange = { newCss ->
                                    viewModel.updateFormCustomHtmlPage(activePageIndex, currentPage.title, currentPage.subtitle, currentPage.customHtmlContent, newCss)
                                },
                                label = { Text("Custom CSS (Optional)") },
                                modifier = Modifier.fillMaxWidth(),
                                minLines = 3,
                                textStyle = androidx.compose.ui.text.TextStyle(fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace, fontSize = 12.sp)
                            )

                            // Live Render Preview of dynamic HTML
                            Card(
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1E1912) else Color(0xFFF1F5F9)),
                                border = BorderStroke(1.dp, cardBorder),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Text("Live Preview (Variables Resolved):", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                    Text(
                                        text = viewModel.resolveFormVariables(currentPage.customHtmlContent).ifBlank { "(No HTML entered yet)" },
                                        fontSize = 12.5.sp,
                                        color = textPrimary
                                    )
                                }
                            }
                        }
                    } else {
                        // Title & Description Header (Hidden if showHeader is false)
                        if (themeConfig.showHeader) {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Text(
                                    text = if (formPages.size > 1) currentPage.title.ifBlank { "Page ${activePageIndex + 1}" } else formHeaderTitle,
                                    fontSize = 20.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary,
                                    textAlign = TextAlign.Center
                                )
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(
                                    text = if (formPages.size > 1) currentPage.subtitle.ifBlank { formDescription } else formDescription,
                                    fontSize = 13.sp,
                                    color = textSecondary,
                                    textAlign = TextAlign.Center
                                )
                            }

                            HorizontalDivider(color = cardBorder, thickness = 1.dp)
                        }

                        // Product Showcase Studio Hero Card (for Product Forms)
                        val isProductForm = themeConfig.productImageUrl.isNotBlank() ||
                            formProducts.isNotEmpty() ||
                            viewModel.formTemplateKey.value in listOf("FLAGSHIP_PRODUCT", "SINGLE_PRODUCT")
                        if (isProductForm && activePageIndex == 0) {
                            val heroImageUrl = themeConfig.productImageUrl.ifBlank { formProducts.firstOrNull()?.imageUrl.orEmpty() }
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1E2433) else Color(0xFFF8FAFC)),
                                border = BorderStroke(1.dp, if (isDark) Color(0xFF334155) else Color(0xFFCBD5E1)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            Icon(Icons.Outlined.Storefront, null, tint = goldText, modifier = Modifier.size(18.dp))
                                            Surface(
                                                shape = RoundedCornerShape(4.dp),
                                                color = if (isDark) Color(0xFF281E0A) else Color(0xFFFFFBEB),
                                                border = BorderStroke(0.5.dp, goldPrimary.copy(alpha = 0.5f))
                                            ) {
                                                Text(
                                                    "#0 Hero",
                                                    fontSize = 10.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = goldText,
                                                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                )
                                            }
                                            Text("Product", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = textPrimary)
                                        }
                                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            Surface(
                                                shape = RoundedCornerShape(6.dp),
                                                color = formPrimaryColor.copy(alpha = 0.15f)
                                            ) {
                                                Text(
                                                    "shop",
                                                    fontSize = 10.5.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = formPrimaryColor,
                                                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp)
                                                )
                                            }
                                            IconButton(
                                                onClick = { isShowcaseEditorExpanded = !isShowcaseEditorExpanded },
                                                modifier = Modifier.size(28.dp)
                                            ) {
                                                Icon(
                                                    imageVector = if (isShowcaseEditorExpanded) Icons.Default.Close else Icons.Outlined.Tune,
                                                    contentDescription = if (isShowcaseEditorExpanded) "Close Editor" else "Edit Product Showcase",
                                                    tint = goldText,
                                                    modifier = Modifier.size(17.dp)
                                                )
                                            }
                                        }
                                    }

                                    // Image & Upload Row
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(12.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Box(
                                            modifier = Modifier
                                                .size(80.dp)
                                                .clip(RoundedCornerShape(10.dp))
                                                .background(if (isDark) Color(0xFF0F172A) else Color(0xFFE2E8F0))
                                                .border(1.dp, cardBorder, RoundedCornerShape(10.dp))
                                                .clickable { showcaseImagePicker.launch("image/*") },
                                            contentAlignment = Alignment.Center
                                        ) {
                                            if (heroImageUrl.isNotBlank()) {
                                                AsyncImage(
                                                    model = heroImageUrl,
                                                    contentDescription = "Showcase Image",
                                                    modifier = Modifier.fillMaxSize(),
                                                    contentScale = ContentScale.Crop
                                                )
                                            } else {
                                                Icon(Icons.Outlined.AddPhotoAlternate, null, tint = textSecondary, modifier = Modifier.size(28.dp))
                                            }
                                        }

                                        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                            if (themeConfig.eyebrowText.isNotBlank()) {
                                                Text(themeConfig.eyebrowText, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText)
                                            }
                                            Text(
                                                text = formHeaderTitle.ifBlank { "Flagship Product" },
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 14.sp,
                                                color = textPrimary,
                                                maxLines = 1,
                                                overflow = TextOverflow.Ellipsis
                                            )
                                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                                                val displayPriceVal = formProducts.firstOrNull()?.let { if (it.salePrice > 0.0) it.salePrice else it.price } ?: 0.0
                                                val curr = if (themeConfig.currencyCode == "USD") "$" else "৳"
                                                if (displayPriceVal > 0) {
                                                    Text("$curr${displayPriceVal.toInt()}", fontWeight = FontWeight.ExtraBold, fontSize = 14.sp, color = textPrimary)
                                                }
                                                if (themeConfig.wasPrice > displayPriceVal && displayPriceVal > 0) {
                                                    Text(
                                                        "$curr${themeConfig.wasPrice.toInt()}",
                                                        fontSize = 11.sp,
                                                        color = textSecondary,
                                                        textDecoration = androidx.compose.ui.text.style.TextDecoration.LineThrough
                                                    )
                                                }
                                            }
                                        }

                                        IconButton(
                                            onClick = { showcaseImagePicker.launch("image/*") },
                                            modifier = Modifier.size(36.dp)
                                        ) {
                                            Icon(Icons.Outlined.CloudUpload, contentDescription = "Upload Photo", tint = goldText, modifier = Modifier.size(20.dp))
                                        }
                                    }

                                    // Gallery previews if present
                                    val currentHeroProd = formProducts.firstOrNull()
                                    if (currentHeroProd != null && currentHeroProd.galleryUrls.isNotEmpty()) {
                                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                            Text("Gallery (${currentHeroProd.galleryUrls.size} photos):", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                            LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                                items(currentHeroProd.galleryUrls) { gUrl ->
                                                    Box(
                                                        modifier = Modifier
                                                            .size(44.dp)
                                                            .clip(RoundedCornerShape(8.dp))
                                                            .border(1.dp, cardBorder, RoundedCornerShape(8.dp))
                                                    ) {
                                                        AsyncImage(
                                                            model = gUrl,
                                                            contentDescription = "Gallery Thumbnail",
                                                            modifier = Modifier.fillMaxSize(),
                                                            contentScale = ContentScale.Crop
                                                        )
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    // Variants preview chips (Variant instead of color)
                                    if (currentHeroProd != null && currentHeroProd.productVariants.isNotEmpty() && !themeConfig.hideSwatches) {
                                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                            Text("Product Variants (${currentHeroProd.productVariants.size}):", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                            LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                                items(currentHeroProd.productVariants) { pv ->
                                                    Surface(
                                                        shape = RoundedCornerShape(6.dp),
                                                        color = if (isDark) Color(0xFF221A0C) else Color(0xFFFEFDF5),
                                                        border = BorderStroke(0.8.dp, goldPrimary.copy(alpha = 0.5f))
                                                    ) {
                                                        Row(
                                                            modifier = Modifier.padding(horizontal = 7.dp, vertical = 3.dp),
                                                            verticalAlignment = Alignment.CenterVertically,
                                                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                                                        ) {
                                                            Text(pv.name, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
                                                            if (pv.price > 0.0) {
                                                                val curr = if (themeConfig.currencyCode == "USD") "$" else "৳"
                                                                Text("$curr${pv.price.toInt()}", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = goldText)
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    // Quick visibility badges summary
                                    Row(
                                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                                        modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState())
                                    ) {
                                        listOf(
                                            "Header" to !themeConfig.hideHeader,
                                            "Variants" to !themeConfig.hideSwatches,
                                            "Chips" to !themeConfig.hideChips,
                                            "Rating" to !themeConfig.hideRating,
                                            "Assurances" to !themeConfig.hideAssurances,
                                            "Details" to !themeConfig.hideDetails,
                                            "Mobile Dock" to !themeConfig.hideMobileDock
                                        ).forEach { (label, active) ->
                                            Surface(
                                                shape = RoundedCornerShape(6.dp),
                                                color = if (active) Color(0xFF10B981).copy(alpha = 0.12f) else Color(0xFF6B7280).copy(alpha = 0.12f),
                                                border = BorderStroke(0.6.dp, if (active) Color(0xFF10B981).copy(alpha = 0.3f) else cardBorder)
                                            ) {
                                                Text(
                                                    text = if (active) "✓ $label" else "✕ $label",
                                                    fontSize = 10.sp,
                                                    fontWeight = FontWeight.SemiBold,
                                                    color = if (active) Color(0xFF10B981) else textSecondary,
                                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp)
                                                )
                                            }
                                        }
                                    }

                                    // Expanded Showcase Settings Editor
                                    if (isShowcaseEditorExpanded) {
                                        HorizontalDivider(color = cardBorder, thickness = 1.dp)
                                        ProductShowcaseSettingsEditor(
                                            themeConfig = themeConfig,
                                            formProducts = formProducts,
                                            isDark = isDark,
                                            viewModel = viewModel,
                                            onClose = { isShowcaseEditorExpanded = false }
                                        )
                                    }
                                }
                            }

                            HorizontalDivider(color = cardBorder, thickness = 1.dp)
                        }

                        val activePageFields = formFields.filter {
                            if (formPages.size <= 1) true else it.pageIndex == activePageIndex
                        }

                        if (activePageFields.isEmpty() && (activePageIndex != 0 || formProducts.isEmpty())) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 36.dp),
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                Icon(Icons.Outlined.DashboardCustomize, null, tint = goldText, modifier = Modifier.size(38.dp))
                                Text(if (formPages.size > 1) "Page ${activePageIndex + 1} is empty" else "Your form canvas is empty", fontWeight = FontWeight.Bold, color = textPrimary)
                                Text(
                                    "Add fields from the elements palette above.",
                                    fontSize = 12.sp,
                                    color = textSecondary,
                                    textAlign = TextAlign.Center
                                )
                            }
                        }

                        // Dynamically Added Form Fields
                        if (activePageFields.isNotEmpty()) {
                            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                                activePageFields.forEachIndexed { pageFieldIndex, field ->
                                    key(field.id) {
                                    val currentVal = dynamicFieldValues[field.id] ?: ""
                                    val fieldError = formErrors[field.id]

                                    Card(
                                        shape = RoundedCornerShape(14.dp),
                                        colors = CardDefaults.cardColors(
                                            containerColor = if (isDark) Color(0xFF18140E) else Color(0xFFF8FAFC)
                                        ),
                                    border = BorderStroke(1.dp, if (fieldError != null) Color(0xFFEF4444) else cardBorder),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Column(
                                        modifier = Modifier.padding(12.dp),
                                        verticalArrangement = Arrangement.spacedBy(6.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Row(
                                                modifier = Modifier.weight(1f).padding(end = 8.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                                            ) {
                                                var dragOffsetY by remember(field.id) { mutableFloatStateOf(0f) }
                                                val density = LocalDensity.current
                                                val itemHeightPx = with(density) { 80.dp.toPx() }

                                                Icon(
                                                    imageVector = Icons.Default.DragHandle,
                                                    contentDescription = "Drag to reorder",
                                                    tint = goldText,
                                                    modifier = Modifier
                                                        .size(22.dp)
                                                        .pointerInput(field.id, pageFieldIndex, activePageFields.size) {
                                                            detectVerticalDragGestures(
                                                                onDragStart = { dragOffsetY = 0f },
                                                                onDragEnd = { dragOffsetY = 0f },
                                                                onDragCancel = { dragOffsetY = 0f },
                                                                onVerticalDrag = { change, dragAmount ->
                                                                    change.consume()
                                                                    dragOffsetY += dragAmount
                                                                    if (dragOffsetY > itemHeightPx && pageFieldIndex < activePageFields.size - 1) {
                                                                        val nextField = activePageFields[pageFieldIndex + 1]
                                                                        val fromIdx = formFields.indexOfFirst { it.id == field.id }
                                                                        val toIdx = formFields.indexOfFirst { it.id == nextField.id }
                                                                        if (fromIdx != -1 && toIdx != -1) {
                                                                            viewModel.reorderFormField(fromIdx, toIdx)
                                                                        }
                                                                        dragOffsetY = 0f
                                                                    } else if (dragOffsetY < -itemHeightPx && pageFieldIndex > 0) {
                                                                        val prevField = activePageFields[pageFieldIndex - 1]
                                                                        val fromIdx = formFields.indexOfFirst { it.id == field.id }
                                                                        val toIdx = formFields.indexOfFirst { it.id == prevField.id }
                                                                        if (fromIdx != -1 && toIdx != -1) {
                                                                            viewModel.reorderFormField(fromIdx, toIdx)
                                                                        }
                                                                        dragOffsetY = 0f
                                                                    }
                                                                }
                                                            )
                                                        }
                                                )

                                                // Micro Move Up / Down Arrow buttons
                                                Row(verticalAlignment = Alignment.CenterVertically) {
                                                    IconButton(
                                                        onClick = {
                                                            if (pageFieldIndex > 0) {
                                                                val prevField = activePageFields[pageFieldIndex - 1]
                                                                val fromIdx = formFields.indexOfFirst { it.id == field.id }
                                                                val toIdx = formFields.indexOfFirst { it.id == prevField.id }
                                                                if (fromIdx != -1 && toIdx != -1) {
                                                                    viewModel.reorderFormField(fromIdx, toIdx)
                                                                }
                                                            }
                                                        },
                                                        enabled = pageFieldIndex > 0,
                                                        modifier = Modifier.size(24.dp)
                                                    ) {
                                                        Icon(
                                                            Icons.Default.KeyboardArrowUp,
                                                            contentDescription = "Move Up",
                                                            tint = if (pageFieldIndex > 0) goldText else cardBorder,
                                                            modifier = Modifier.size(18.dp)
                                                        )
                                                    }
                                                    IconButton(
                                                        onClick = {
                                                            if (pageFieldIndex < activePageFields.size - 1) {
                                                                val nextField = activePageFields[pageFieldIndex + 1]
                                                                val fromIdx = formFields.indexOfFirst { it.id == field.id }
                                                                val toIdx = formFields.indexOfFirst { it.id == nextField.id }
                                                                if (fromIdx != -1 && toIdx != -1) {
                                                                    viewModel.reorderFormField(fromIdx, toIdx)
                                                                }
                                                            }
                                                        },
                                                        enabled = pageFieldIndex < activePageFields.size - 1,
                                                        modifier = Modifier.size(24.dp)
                                                    ) {
                                                        Icon(
                                                            Icons.Default.KeyboardArrowDown,
                                                            contentDescription = "Move Down",
                                                            tint = if (pageFieldIndex < activePageFields.size - 1) goldText else cardBorder,
                                                            modifier = Modifier.size(18.dp)
                                                        )
                                                    }
                                                }

                                                Surface(
                                                    shape = RoundedCornerShape(4.dp),
                                                    color = if (isDark) Color(0xFF281E0A) else Color(0xFFFFFBEB),
                                                    border = BorderStroke(0.5.dp, goldPrimary.copy(alpha = 0.5f))
                                                ) {
                                                    Text(
                                                        "#${pageFieldIndex + 1}",
                                                        fontSize = 10.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = goldText,
                                                        modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                    )
                                                }

                                                Text(
                                                    text = field.label,
                                                    fontSize = 13.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = textPrimary,
                                                    maxLines = 1,
                                                    overflow = TextOverflow.Ellipsis,
                                                    modifier = Modifier.weight(1f, fill = false)
                                                )
                                                if (field.isRequired) {
                                                    Text(" *", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Color.Red, softWrap = false)
                                                }
                                            }

                                            Row(verticalAlignment = Alignment.CenterVertically) {
                                                IconButton(
                                                    onClick = { expandedFieldId = if (expandedFieldId == field.id) null else field.id },
                                                    modifier = Modifier.size(28.dp)
                                                ) {
                                                    Icon(
                                                        Icons.Outlined.Tune,
                                                        contentDescription = "Edit field settings",
                                                        tint = goldText,
                                                        modifier = Modifier.size(16.dp)
                                                    )
                                                }
                                                IconButton(
                                                    onClick = {
                                                        viewModel.removeFormField(field.id)
                                                        dynamicFieldValues = dynamicFieldValues - field.id
                                                        formErrors = formErrors - field.id
                                                        if (expandedFieldId == field.id) expandedFieldId = null
                                                    },
                                                    modifier = Modifier.size(28.dp)
                                                ) {
                                                    Icon(
                                                        Icons.Outlined.Delete,
                                                        contentDescription = "Remove Field",
                                                        tint = Color(0xFFEF4444),
                                                        modifier = Modifier.size(16.dp)
                                                    )
                                                }
                                            }
                                        }

                                        when (field.type) {
                                            FormFieldType.PRODUCT, FormFieldType.PRODUCT_LIST -> {
                                                Surface(
                                                    shape = RoundedCornerShape(14.dp),
                                                    color = if (isDark) Color(0xFF1E1912) else Color(0xFFF8FAFC),
                                                    border = BorderStroke(1.dp, if (fieldError != null) Color(0xFFEF4444) else cardBorder),
                                                    modifier = Modifier.fillMaxWidth()
                                                ) {
                                                    Row(
                                                        modifier = Modifier.padding(14.dp),
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                                                    ) {
                                                        Box(
                                                            modifier = Modifier
                                                                .size(48.dp)
                                                                .clip(RoundedCornerShape(10.dp))
                                                                .background(goldDarkBg),
                                                            contentAlignment = Alignment.Center
                                                        ) {
                                                            if (field.mediaUrl.isNotBlank()) {
                                                                AsyncImage(
                                                                    model = field.mediaUrl,
                                                                    contentDescription = field.label,
                                                                    modifier = Modifier.fillMaxSize(),
                                                                    contentScale = ContentScale.Crop
                                                                )
                                                            } else {
                                                                Icon(
                                                                    Icons.Outlined.ShoppingBag,
                                                                    contentDescription = null,
                                                                    tint = goldPrimary,
                                                                    modifier = Modifier.size(26.dp)
                                                                )
                                                            }
                                                        }
                                                        Column(modifier = Modifier.weight(1f)) {
                                                            Text(
                                                                field.label.ifBlank { "Product Item" },
                                                                fontWeight = FontWeight.Bold,
                                                                fontSize = 14.sp,
                                                                color = textPrimary
                                                            )
                                                            val priceVal = field.minValue ?: field.defaultValue.toDoubleOrNull() ?: 0.0
                                                            Text(
                                                                if (priceVal > 0) "BDT ${"%,.2f".format(priceVal)}" else (field.placeholder.ifBlank { "Price: BDT 0.00" }),
                                                                fontSize = 12.5.sp,
                                                                fontWeight = FontWeight.Bold,
                                                                color = goldText
                                                            )
                                                            if (field.helperText.isNotBlank()) {
                                                                Text(
                                                                    field.helperText,
                                                                    fontSize = 11.sp,
                                                                    color = textSecondary
                                                                )
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            FormFieldType.CUSTOM_CODE -> {
                                                val builtInVariables = listOf(
                                                    "{{form_title}}", "{{form_description}}", "{{name}}", "{{email}}", "{{phone}}",
                                                    "{{submission_id}}", "{{transaction_id}}", "{{payment_status}}", "{{payment_amount}}",
                                                    "{{payment_method}}", "{{created_at}}", "{{all_fields}}"
                                                )
                                                val fieldVariables = formFields.filterNot { it.type == FormFieldType.CUSTOM_CODE }.map { sourceField ->
                                                    val key = sourceField.label.lowercase().replace(Regex("[^a-z0-9]+"), "_").trim('_')
                                                        .ifBlank { sourceField.id.replace("-", "_") }
                                                    "{{field_$key}}"
                                                }
                                                val customVariables = themeConfig.customVariables.map { variable ->
                                                    "{{${variable.key.removePrefix("{{").removeSuffix("}}")}}}"
                                                }
                                                CustomCodeBlockEditor(
                                                    field = field,
                                                    availableVariables = (builtInVariables + fieldVariables + customVariables).distinct(),
                                                    isDark = isDark,
                                                    onUpdate = viewModel::updateFormField
                                                )
                                            }
                                            FormFieldType.CHECKBOX -> {
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                                ) {
                                                    val isChecked = currentVal == "true"
                                                    Checkbox(
                                                        checked = isChecked,
                                                        onCheckedChange = { checked ->
                                                            dynamicFieldValues = dynamicFieldValues + (field.id to checked.toString())
                                                            if (fieldError != null) formErrors = formErrors - field.id
                                                        }
                                                    )
                                                    Text(field.placeholder.ifBlank { "I agree and acknowledge" }, fontSize = 12.5.sp, color = textPrimary)
                                                }
                                            }
                                            FormFieldType.DATE -> {
                                                val calendar = Calendar.getInstance()
                                                val datePickerDialog = DatePickerDialog(
                                                    context,
                                                    { _, year, month, dayOfMonth ->
                                                        val formattedDate = String.format("%04d-%02d-%02d", year, month + 1, dayOfMonth)
                                                        dynamicFieldValues = dynamicFieldValues + (field.id to formattedDate)
                                                        if (fieldError != null) formErrors = formErrors - field.id
                                                    },
                                                    calendar.get(Calendar.YEAR),
                                                    calendar.get(Calendar.MONTH),
                                                    calendar.get(Calendar.DAY_OF_MONTH)
                                                )
                                                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                                    Surface(
                                                        onClick = { datePickerDialog.show() },
                                                        shape = RoundedCornerShape(10.dp),
                                                        color = cardBg,
                                                        border = BorderStroke(1.dp, if (fieldError != null) Color(0xFFEF4444) else cardBorder),
                                                        modifier = Modifier.fillMaxWidth()
                                                    ) {
                                                        Row(
                                                            modifier = Modifier.padding(horizontal = 14.dp, vertical = 14.dp),
                                                            verticalAlignment = Alignment.CenterVertically,
                                                            horizontalArrangement = Arrangement.SpaceBetween
                                                        ) {
                                                            Row(
                                                                verticalAlignment = Alignment.CenterVertically,
                                                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                                                            ) {
                                                                Icon(
                                                                    imageVector = Icons.Outlined.CalendarToday,
                                                                    contentDescription = null,
                                                                    tint = if (currentVal.isNotBlank()) goldPrimary else textSecondary,
                                                                    modifier = Modifier.size(18.dp)
                                                                )
                                                                Text(
                                                                    text = if (currentVal.isNotBlank()) currentVal else (field.placeholder.ifBlank { "Select Date (YYYY-MM-DD)" }),
                                                                    color = if (currentVal.isNotBlank()) textPrimary else textSecondary,
                                                                    fontSize = 13.sp
                                                                )
                                                            }
                                                            if (currentVal.isNotBlank()) {
                                                                IconButton(
                                                                    onClick = { dynamicFieldValues = dynamicFieldValues - field.id },
                                                                    modifier = Modifier.size(24.dp)
                                                                ) {
                                                                    Icon(Icons.Default.Clear, contentDescription = "Clear", tint = textSecondary, modifier = Modifier.size(16.dp))
                                                                }
                                                            }
                                                        }
                                                    }
                                                    if (fieldError != null) {
                                                        Text(fieldError, color = Color(0xFFEF4444), fontSize = 11.5.sp, modifier = Modifier.padding(start = 4.dp))
                                                    }
                                                }
                                            }
                                            FormFieldType.FILE_UPLOAD, FormFieldType.CAMERA_UPLOAD -> {
                                                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                                    Surface(
                                                        shape = RoundedCornerShape(12.dp),
                                                        color = if (isDark) Color(0xFF1A160F) else Color(0xFFFAFAFA),
                                                        border = BorderStroke(1.dp, if (fieldError != null) Color(0xFFEF4444) else cardBorder),
                                                        modifier = Modifier.fillMaxWidth()
                                                    ) {
                                                        Column(
                                                            modifier = Modifier.padding(16.dp),
                                                            horizontalAlignment = Alignment.CenterHorizontally,
                                                            verticalArrangement = Arrangement.spacedBy(6.dp)
                                                        ) {
                                                            Box(
                                                                modifier = Modifier
                                                                    .size(44.dp)
                                                                    .clip(CircleShape)
                                                                    .background(goldDarkBg),
                                                                contentAlignment = Alignment.Center
                                                            ) {
                                                                Icon(
                                                                    imageVector = if (field.type == FormFieldType.CAMERA_UPLOAD) Icons.Outlined.PhotoCamera else Icons.Outlined.CloudUpload,
                                                                    contentDescription = null,
                                                                    tint = goldPrimary,
                                                                    modifier = Modifier.size(22.dp)
                                                                )
                                                            }
                                                            Text(
                                                                text = if (currentVal.isNotBlank()) "Attached: $currentVal" else (field.placeholder.ifBlank { "Tap or drag file to upload" }),
                                                                fontWeight = FontWeight.SemiBold,
                                                                fontSize = 12.5.sp,
                                                                color = if (currentVal.isNotBlank()) goldPrimary else textPrimary,
                                                                textAlign = TextAlign.Center
                                                            )
                                                            Text(
                                                                text = "Supports PDF, JPG, PNG, DOCX (Max 10MB)",
                                                                fontSize = 10.5.sp,
                                                                color = textSecondary
                                                            )
                                                        }
                                                    }
                                                    if (fieldError != null) {
                                                        Text(fieldError, color = Color(0xFFEF4444), fontSize = 11.5.sp, modifier = Modifier.padding(start = 4.dp))
                                                    }
                                                }
                                            }
                                            FormFieldType.DROPDOWN -> {
                                                var expandedDropdown by remember { mutableStateOf(false) }
                                                val optionsList = field.options.ifEmpty { listOf("Option 1", "Option 2") }
                                                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                                    Box(modifier = Modifier.fillMaxWidth()) {
                                                        Surface(
                                                            onClick = { expandedDropdown = true },
                                                            shape = RoundedCornerShape(10.dp),
                                                            color = cardBg,
                                                            border = BorderStroke(1.dp, if (fieldError != null) Color(0xFFEF4444) else cardBorder),
                                                            modifier = Modifier.fillMaxWidth()
                                                        ) {
                                                            Row(
                                                                modifier = Modifier.padding(horizontal = 14.dp, vertical = 14.dp),
                                                                verticalAlignment = Alignment.CenterVertically,
                                                                horizontalArrangement = Arrangement.SpaceBetween
                                                            ) {
                                                                Text(
                                                                    text = if (currentVal.isNotBlank()) currentVal else (field.placeholder.ifBlank { "Select an option" }),
                                                                    color = if (currentVal.isNotBlank()) textPrimary else textSecondary,
                                                                    fontSize = 13.sp
                                                                )
                                                                Icon(
                                                                    imageVector = if (expandedDropdown) Icons.Default.ArrowDropUp else Icons.Default.ArrowDropDown,
                                                                    contentDescription = null,
                                                                    tint = textSecondary
                                                                )
                                                            }
                                                        }
                                                        DropdownMenu(
                                                            expanded = expandedDropdown,
                                                            onDismissRequest = { expandedDropdown = false }
                                                        ) {
                                                            optionsList.forEach { opt ->
                                                                DropdownMenuItem(
                                                                    text = { Text(opt, fontSize = 13.sp) },
                                                                    onClick = {
                                                                        dynamicFieldValues = dynamicFieldValues + (field.id to opt)
                                                                        if (fieldError != null) formErrors = formErrors - field.id
                                                                        expandedDropdown = false
                                                                    }
                                                                )
                                                            }
                                                        }
                                                    }
                                                    if (fieldError != null) {
                                                        Text(fieldError, color = Color(0xFFEF4444), fontSize = 11.5.sp, modifier = Modifier.padding(start = 4.dp))
                                                    }
                                                }
                                            }
                                            FormFieldType.RADIO -> {
                                                val radioOptions = field.options.ifEmpty { listOf("Option 1", "Option 2") }
                                                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                                    radioOptions.forEach { opt ->
                                                        Row(
                                                            verticalAlignment = Alignment.CenterVertically,
                                                            modifier = Modifier
                                                                .fillMaxWidth()
                                                                .clickable {
                                                                    dynamicFieldValues = dynamicFieldValues + (field.id to opt)
                                                                    if (fieldError != null) formErrors = formErrors - field.id
                                                                }
                                                                .padding(vertical = 4.dp),
                                                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                                                        ) {
                                                            RadioButton(
                                                                selected = currentVal == opt,
                                                                onClick = {
                                                                    dynamicFieldValues = dynamicFieldValues + (field.id to opt)
                                                                    if (fieldError != null) formErrors = formErrors - field.id
                                                                }
                                                            )
                                                            Text(opt, fontSize = 13.sp, color = textPrimary)
                                                        }
                                                    }
                                                    if (fieldError != null) {
                                                        Text(fieldError, color = Color(0xFFEF4444), fontSize = 11.5.sp, modifier = Modifier.padding(start = 4.dp))
                                                    }
                                                }
                                            }
                                            FormFieldType.MULTI_SELECT -> {
                                                val multiOptions = field.options.ifEmpty { listOf("Option 1", "Option 2") }
                                                val selectedSet = remember(currentVal) {
                                                    if (currentVal.isBlank()) emptySet()
                                                    else currentVal.split(", ").filter { it.isNotBlank() }.toSet()
                                                }
                                                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                                    multiOptions.forEach { opt ->
                                                        val isSelected = opt in selectedSet
                                                        Row(
                                                            verticalAlignment = Alignment.CenterVertically,
                                                            modifier = Modifier
                                                                .fillMaxWidth()
                                                                .clickable {
                                                                    val newSet = if (isSelected) selectedSet - opt else selectedSet + opt
                                                                    dynamicFieldValues = dynamicFieldValues + (field.id to newSet.joinToString(", "))
                                                                    if (fieldError != null) formErrors = formErrors - field.id
                                                                }
                                                                .padding(vertical = 4.dp),
                                                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                                                        ) {
                                                            Checkbox(
                                                                checked = isSelected,
                                                                onCheckedChange = { checked ->
                                                                    val newSet = if (checked) selectedSet + opt else selectedSet - opt
                                                                    dynamicFieldValues = dynamicFieldValues + (field.id to newSet.joinToString(", "))
                                                                    if (fieldError != null) formErrors = formErrors - field.id
                                                                }
                                                            )
                                                            Text(opt, fontSize = 13.sp, color = textPrimary)
                                                        }
                                                    }
                                                    if (fieldError != null) {
                                                        Text(fieldError, color = Color(0xFFEF4444), fontSize = 11.5.sp, modifier = Modifier.padding(start = 4.dp))
                                                    }
                                                }
                                            }
                                            FormFieldType.TOGGLE -> {
                                                val isToggled = currentVal == "true"
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(field.placeholder.ifBlank { "Enable" }, fontSize = 13.sp, color = textPrimary)
                                                    Switch(
                                                        checked = isToggled,
                                                        onCheckedChange = { toggled ->
                                                            dynamicFieldValues = dynamicFieldValues + (field.id to toggled.toString())
                                                            if (fieldError != null) formErrors = formErrors - field.id
                                                        }
                                                    )
                                                }
                                            }
                                            FormFieldType.CUSTOM_AMOUNT -> {
                                                val amtVal = currentVal.ifBlank { field.defaultValue.ifBlank { "500.00" } }
                                                val baseAmt = amtVal.toDoubleOrNull() ?: 0.0
                                                val couponAppliedCode = dynamicFieldValues["__applied_coupon"] ?: ""
                                                val couponDiscountPct = dynamicFieldValues["__applied_discount_pct"]?.toDoubleOrNull() ?: 0.0
                                                val couponDiscountFlat = dynamicFieldValues["__applied_discount_flat"]?.toDoubleOrNull() ?: 0.0
                                                val discountAmt = if (couponDiscountPct > 0) (baseAmt * couponDiscountPct / 100.0) else couponDiscountFlat
                                                val finalPayable = (baseAmt - discountAmt).coerceAtLeast(0.0)

                                                Surface(
                                                    shape = RoundedCornerShape(12.dp),
                                                    color = if (isDark) Color(0xFF1E1A12) else Color(0xFFFBFBFD),
                                                    border = BorderStroke(1.dp, if (fieldError != null) Color(0xFFEF4444) else cardBorder),
                                                    modifier = Modifier.fillMaxWidth()
                                                ) {
                                                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                        Row(
                                                            modifier = Modifier.fillMaxWidth(),
                                                            horizontalArrangement = Arrangement.SpaceBetween,
                                                            verticalAlignment = Alignment.CenterVertically
                                                        ) {
                                                            Text(
                                                                field.label.ifBlank { "Payment Amount (BDT)" },
                                                                fontSize = 13.sp,
                                                                fontWeight = FontWeight.Bold,
                                                                color = textPrimary
                                                            )
                                                            Surface(
                                                                shape = RoundedCornerShape(6.dp),
                                                                color = if (field.isFixedPrice) goldPrimary.copy(alpha = 0.15f) else Color(0xFF3B82F6).copy(alpha = 0.15f)
                                                            ) {
                                                                Text(
                                                                    if (field.isFixedPrice) "🔒 Fixed Price" else "✏️ Custom Amount",
                                                                    fontSize = 10.5.sp,
                                                                    fontWeight = FontWeight.Bold,
                                                                    color = if (field.isFixedPrice) goldText else Color(0xFF2563EB),
                                                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                                                )
                                                            }
                                                        }

                                                        if (field.isFixedPrice) {
                                                            Row(
                                                                verticalAlignment = Alignment.CenterVertically,
                                                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                                                            ) {
                                                                Text("৳", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = goldPrimary)
                                                                Text(
                                                                    "%,.2f".format(baseAmt),
                                                                    fontSize = 22.sp,
                                                                    fontWeight = FontWeight.ExtraBold,
                                                                    color = textPrimary
                                                                )
                                                                Text("BDT", fontSize = 12.sp, color = textSecondary)
                                                            }
                                                        } else {
                                                            OutlinedTextField(
                                                                value = amtVal,
                                                                onValueChange = { input ->
                                                                    val clean = input.filter { it.isDigit() || it in ".-" }
                                                                    dynamicFieldValues = dynamicFieldValues + (field.id to clean)
                                                                    if (fieldError != null) formErrors = formErrors - field.id
                                                                },
                                                                leadingIcon = { Text("৳", fontWeight = FontWeight.Bold, color = goldPrimary) },
                                                                label = { Text("Enter Amount") },
                                                                modifier = Modifier.fillMaxWidth(),
                                                                singleLine = true,
                                                                shape = RoundedCornerShape(10.dp)
                                                            )
                                                        }

                                                        if (couponAppliedCode.isNotBlank() && discountAmt > 0) {
                                                            Surface(
                                                                shape = RoundedCornerShape(8.dp),
                                                                color = Color(0xFF10B981).copy(alpha = 0.12f),
                                                                border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.35f)),
                                                                modifier = Modifier.fillMaxWidth()
                                                            ) {
                                                                Row(
                                                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                                    verticalAlignment = Alignment.CenterVertically
                                                                ) {
                                                                    Text("🏷️ Promo ($couponAppliedCode): -৳${"%,.2f".format(discountAmt)}", fontSize = 11.5.sp, color = Color(0xFF059669), fontWeight = FontWeight.SemiBold)
                                                                    Text("Payable: ৳${"%,.2f".format(finalPayable)}", fontSize = 12.sp, color = Color(0xFF059669), fontWeight = FontWeight.Bold)
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            FormFieldType.COUPON -> {
                                                var couponInput by remember { mutableStateOf("") }
                                                val activeCoupon = dynamicFieldValues["__applied_coupon"] ?: ""
                                                val configuredCoupons = field.options.ifEmpty { listOf("SAVE10:10%", "SAVE20:20%", "FLAT50:50") }

                                                Surface(
                                                    shape = RoundedCornerShape(12.dp),
                                                    color = if (isDark) Color(0xFF1E1A12) else Color(0xFFFBFBFD),
                                                    border = BorderStroke(1.dp, if (fieldError != null) Color(0xFFEF4444) else cardBorder),
                                                    modifier = Modifier.fillMaxWidth()
                                                ) {
                                                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                        Text(
                                                            field.label.ifBlank { "Promo / Coupon Code" },
                                                            fontSize = 13.sp,
                                                            fontWeight = FontWeight.Bold,
                                                            color = textPrimary
                                                        )
                                                        Row(
                                                            modifier = Modifier.fillMaxWidth(),
                                                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                                                            verticalAlignment = Alignment.CenterVertically
                                                        ) {
                                                            OutlinedTextField(
                                                                value = couponInput,
                                                                onValueChange = { couponInput = it.uppercase() },
                                                                placeholder = { Text(field.placeholder.ifBlank { "e.g. SAVE20" }, fontSize = 12.sp) },
                                                                modifier = Modifier.weight(1f),
                                                                singleLine = true,
                                                                shape = RoundedCornerShape(10.dp)
                                                            )
                                                            Button(
                                                                onClick = {
                                                                    val entered = couponInput.trim().uppercase()
                                                                    val match = configuredCoupons.find { it.split(':')[0].trim().equals(entered, ignoreCase = true) }
                                                                        ?: "$entered:10%"
                                                                    val discStr = match.split(':').getOrElse(1) { "10%" }.trim()
                                                                    if (discStr.endsWith("%")) {
                                                                        val pct = discStr.removeSuffix("%").toDoubleOrNull() ?: 10.0
                                                                        dynamicFieldValues = dynamicFieldValues + mapOf(
                                                                            "__applied_coupon" to entered,
                                                                            "__applied_discount_pct" to pct.toString(),
                                                                            "__applied_discount_flat" to "0"
                                                                        )
                                                                    } else {
                                                                        val flat = discStr.toDoubleOrNull() ?: 50.0
                                                                        dynamicFieldValues = dynamicFieldValues + mapOf(
                                                                            "__applied_coupon" to entered,
                                                                            "__applied_discount_pct" to "0",
                                                                            "__applied_discount_flat" to flat.toString()
                                                                        )
                                                                    }
                                                                },
                                                                colors = ButtonDefaults.buttonColors(containerColor = goldPrimary),
                                                                shape = RoundedCornerShape(10.dp)
                                                            ) {
                                                                Text("Apply", color = Color(0xFF1F1A0E), fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                                            }
                                                        }
                                                        if (activeCoupon.isNotBlank()) {
                                                            Row(
                                                                modifier = Modifier.fillMaxWidth(),
                                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                                verticalAlignment = Alignment.CenterVertically
                                                            ) {
                                                                Text("✓ Applied Code: $activeCoupon", fontSize = 11.5.sp, color = Color(0xFF059669), fontWeight = FontWeight.SemiBold)
                                                                TextButton(onClick = {
                                                                    dynamicFieldValues = dynamicFieldValues - setOf("__applied_coupon", "__applied_discount_pct", "__applied_discount_flat")
                                                                    couponInput = ""
                                                                }) {
                                                                    Text("Remove", fontSize = 11.sp, color = Color(0xFFEF4444))
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            else -> {
                                                OutlinedTextField(
                                                    value = currentVal,
                                                    onValueChange = {
                                                        dynamicFieldValues = dynamicFieldValues + (field.id to it)
                                                        if (fieldError != null) formErrors = formErrors - field.id
                                                    },
                                                    isError = fieldError != null,
                                                    supportingText = {
                                                        if (fieldError != null) {
                                                            Text(fieldError, color = Color(0xFFEF4444), fontSize = 11.5.sp)
                                                        }
                                                    },
                                                    placeholder = { Text(field.placeholder.ifBlank { "Enter ${field.label}" }, color = textSecondary, fontSize = 12.5.sp) },
                                                    modifier = Modifier.fillMaxWidth(),
                                                    shape = RoundedCornerShape(10.dp)
                                                )
                                            }
                                        }
                                        if (expandedFieldId == field.id) {
                                            AdvancedFieldSettingsEditor(
                                                field = field,
                                                allFields = formFields,
                                                pages = formPages,
                                                isDark = isDark,
                                                viewModel = viewModel,
                                                onUpdate = viewModel::updateFormField
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                    HorizontalDivider(color = cardBorder, thickness = 1.dp)

                    // Page Stepper & Bottom Action Bar
                    if (formPages.size > 1) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            if (activePageIndex > 0) {
                                OutlinedButton(
                                    onClick = { viewModel.activePageIndex.value = activePageIndex - 1 },
                                    shape = formButtonShape,
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null, modifier = Modifier.size(16.dp))
                                    Spacer(Modifier.width(6.dp))
                                    Text("Previous Page", fontSize = 12.sp, color = textPrimary)
                                }
                            } else {
                                Spacer(Modifier.width(1.dp))
                            }

                            if (activePageIndex < formPages.size - 1) {
                                Button(
                                    onClick = { viewModel.activePageIndex.value = activePageIndex + 1 },
                                    shape = formButtonShape,
                                    colors = ButtonDefaults.buttonColors(containerColor = formPrimaryColor)
                                ) {
                                    Text("Next Page →", fontSize = 12.sp, color = Color.White)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    }

    val renameTargetIdx = showRenamePageDialog
    if (renameTargetIdx != null) {
        val pageIdx = renameTargetIdx
        AlertDialog(
            onDismissRequest = { showRenamePageDialog = null },
            title = { Text("Edit Page Details") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = renamePageTitle,
                        onValueChange = { renamePageTitle = it.take(120) },
                        label = { Text("Page Title") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true
                    )
                    OutlinedTextField(
                        value = renamePageSubtitle,
                        onValueChange = { renamePageSubtitle = it.take(300) },
                        label = { Text("Page Subtitle / Note") },
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val page = formPages.getOrNull(pageIdx)
                        if (page != null) {
                            if (page.isCustomHtml) {
                                viewModel.updateFormCustomHtmlPage(pageIdx, renamePageTitle.trim(), renamePageSubtitle.trim(), page.customHtmlContent, page.customCssContent)
                            } else {
                                viewModel.updateFormPage(pageIdx, renamePageTitle.trim(), renamePageSubtitle.trim())
                            }
                        }
                        showRenamePageDialog = null
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = formPrimaryColor)
                ) { Text("Save", color = Color.White) }
            },
            dismissButton = {
                TextButton(onClick = { showRenamePageDialog = null }) { Text("Cancel") }
            }
        )
    }

    val resetProductDraft: () -> Unit = {
        productTitle = ""
        productPrice = ""
        productSalePrice = ""
        productSku = ""
        productImageUrl = ""
    }

    if (showAddProductDialog) {
        AlertDialog(
            onDismissRequest = {
                showAddProductDialog = false
                resetProductDraft()
            },
            title = { Text("Add payment product") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(
                        "Products are charged by the hosted checkout. Sale price and photo are optional.",
                        fontSize = 12.sp,
                        color = textSecondary
                    )
                    OutlinedTextField(
                        value = productTitle,
                        onValueChange = { productTitle = it.take(120) },
                        label = { Text("Product title") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = productPrice,
                        onValueChange = { value -> productPrice = value.filter { it.isDigit() || it == '.' }.take(12) },
                        label = { Text("Regular price (BDT)") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = productSalePrice,
                        onValueChange = { value -> productSalePrice = value.filter { it.isDigit() || it == '.' }.take(12) },
                        label = { Text("Sale price (optional)") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = productSku,
                        onValueChange = { productSku = it.take(64) },
                        label = { Text("SKU (optional)") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )

                    // Product Image Picker in Dialog
                    if (productImageUrl.isNotBlank()) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(120.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .background(if (isDark) Color(0xFF100D07) else Color(0xFFF1F5F9)),
                            contentAlignment = Alignment.Center
                        ) {
                            AsyncImage(
                                model = productImageUrl,
                                contentDescription = "Product Photo",
                                modifier = Modifier.fillMaxSize(),
                                contentScale = ContentScale.Fit
                            )
                            IconButton(
                                onClick = { productImageUrl = "" },
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(4.dp)
                                    .size(26.dp)
                                    .background(Color.Black.copy(alpha = 0.65f), CircleShape)
                            ) {
                                Icon(Icons.Default.Close, contentDescription = "Remove", tint = Color.White, modifier = Modifier.size(14.dp))
                            }
                        }
                    } else if (isUploadingDialogImage) {
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp, color = goldPrimary)
                            Spacer(Modifier.width(8.dp))
                            Text("Uploading photo...", fontSize = 12.sp, color = textSecondary)
                        }
                    } else {
                        OutlinedButton(
                            onClick = { dialogImagePicker.launch("image/*") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Icon(Icons.Outlined.AddPhotoAlternate, contentDescription = null, modifier = Modifier.size(16.dp), tint = goldText)
                            Spacer(Modifier.width(6.dp))
                            Text("Attach Product Photo", fontSize = 12.5.sp, color = textPrimary)
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        val regularPrice = productPrice.toDoubleOrNull()
                        val effectiveSalePrice = productSalePrice.toDoubleOrNull() ?: regularPrice
                        when {
                            productTitle.isBlank() -> Toast.makeText(context, "Enter a product title", Toast.LENGTH_SHORT).show()
                            regularPrice == null || regularPrice <= 0.0 -> Toast.makeText(context, "Enter a valid regular price", Toast.LENGTH_SHORT).show()
                            effectiveSalePrice == null || effectiveSalePrice <= 0.0 -> Toast.makeText(context, "Enter a valid sale price", Toast.LENGTH_SHORT).show()
                            effectiveSalePrice != null && regularPrice != null && effectiveSalePrice > regularPrice -> Toast.makeText(context, "Sale price cannot exceed regular price", Toast.LENGTH_SHORT).show()
                            else -> {
                                val confirmedRegularPrice = checkNotNull(regularPrice)
                                val confirmedSalePrice = checkNotNull(effectiveSalePrice)
                                viewModel.addFormProduct(
                                    title = productTitle.trim(),
                                    price = confirmedRegularPrice,
                                    salePrice = confirmedSalePrice,
                                    sku = productSku.trim(),
                                    stock = 100,
                                    category = "General",
                                    imageUrl = productImageUrl.trim(),
                                    isDigital = false
                                )
                                showAddProductDialog = false
                                resetProductDraft()
                            }
                        }
                    }
                ) { Text("Add") }
            },
            dismissButton = {
                TextButton(
                    onClick = {
                        showAddProductDialog = false
                        resetProductDraft()
                    }
                ) { Text("Cancel") }
            }
        )
    }
}

fun unescapeHtmlString(input: String): String {
    if (!input.contains("&lt;") && !input.contains("&gt;") && !input.contains("&#") && !input.contains("&amp;")) return input
    return input
        .replace("&lt;", "<")
        .replace("&gt;", ">")
        .replace("&quot;", "\"")
        .replace("&apos;", "'")
        .replace("&#39;", "'")
        .replace("&amp;lt;", "<")
        .replace("&amp;gt;", ">")
        .replace("&amp;", "&")
}

@Composable
fun FormHtmlPreview(
    htmlContent: String,
    cssContent: String = "",
    isDark: Boolean = false,
    modifier: Modifier = Modifier
) {
    val cleanHtml = remember(htmlContent) { unescapeHtmlString(htmlContent) }
    val textHex = if (isDark) "#F3F4F6" else "#1F2937"
    val fullDoc = remember(cleanHtml, cssContent, isDark) {
        """
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <style>
                * { box-sizing: border-box; }
                body {
                    margin: 0;
                    padding: 8px;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                    font-size: 13.5px;
                    color: $textHex;
                    background-color: transparent;
                    word-break: break-word;
                }
                $cssContent
            </style>
        </head>
        <body>
            $cleanHtml
        </body>
        </html>
        """.trimIndent()
    }

    AndroidView(
        modifier = modifier,
        factory = { ctx ->
            WebView(ctx).apply {
                settings.javaScriptEnabled = true
                settings.domStorageEnabled = true
                settings.loadWithOverviewMode = true
                settings.useWideViewPort = false
                setBackgroundColor(android.graphics.Color.TRANSPARENT)
            }
        },
        update = { webView ->
            webView.loadDataWithBaseURL("https://pay.swapnopay.top", fullDoc, "text/html", "UTF-8", null)
        }
    )
}

// ═══════════════════════════════════════════════════════════════════════════
@Composable
private fun CustomCodeBlockEditor(
    field: FormFieldItem,
    availableVariables: List<String>,
    isDark: Boolean,
    onUpdate: (FormFieldItem) -> Unit
) {
    val context = LocalContext.current
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)
    val goldBg = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0)
    val goldBorder = if (isDark) Color(0xFFE5A93C) else Color(0xFFFFC800)
    var html by remember(field.id) { mutableStateOf(field.customCodeHtml) }
    var css by remember(field.id) { mutableStateOf(field.customCodeCss) }
    var usedVariables by remember(field.id) { mutableStateOf(field.customVariables) }
    var variableMenuExpanded by remember(field.id) { mutableStateOf(false) }
    var assignmentDialogOpen by remember(field.id) { mutableStateOf(false) }
    var assignmentName by remember(field.id) { mutableStateOf("") }
    var assignmentExpression by remember(field.id) { mutableStateOf(availableVariables.firstOrNull() ?: "") }
    var uploadedName by remember(field.id) { mutableStateOf("") }

    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        runCatching {
            var displayName = "custom-code.html"
            context.contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)?.use { cursor ->
                if (cursor.moveToFirst()) {
                    displayName = cursor.getString(cursor.getColumnIndexOrThrow(OpenableColumns.DISPLAY_NAME))
                    val sizeIndex = cursor.getColumnIndex(OpenableColumns.SIZE)
                    if (sizeIndex >= 0 && !cursor.isNull(sizeIndex) && cursor.getLong(sizeIndex) > 262_144L) {
                        error("Custom-code files are limited to 256 KB")
                    }
                }
            }
            val content = context.contentResolver.openInputStream(uri)?.use { input ->
                val output = java.io.ByteArrayOutputStream()
                val buffer = ByteArray(8_192)
                var total = 0
                while (true) {
                    val count = input.read(buffer)
                    if (count < 0) break
                    total += count
                    if (total > 262_144) error("Custom-code files are limited to 256 KB")
                    output.write(buffer, 0, count)
                }
                output.toByteArray().toString(Charsets.UTF_8)
            } ?: error("Unable to read the selected file")
            if (displayName.endsWith(".css", ignoreCase = true)) css = content.take(100_000) else html = content.take(200_000)
            usedVariables = (usedVariables + Regex("""\{\{\s*([A-Za-z0-9_]+)\s*}}""")
                .findAll(html).map { match -> "{{${match.groupValues[1]}}}" }.toList()).distinct()
            uploadedName = displayName
            onUpdate(field.copy(customCodeHtml = html, customCodeCss = css, customVariables = usedVariables))
        }.onFailure { error ->
            Toast.makeText(context, error.message ?: "Unable to import custom code", Toast.LENGTH_LONG).show()
        }
    }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("HTML / CSS component", fontSize = 12.sp, color = textSecondary)
            Row(verticalAlignment = Alignment.CenterVertically) {
                Surface(
                    onClick = { launcher.launch(arrayOf("text/html", "text/css", "text/plain", "application/xhtml+xml")) },
                    shape = RoundedCornerShape(9.dp),
                    color = goldBg,
                    border = BorderStroke(1.dp, goldBorder)
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 9.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(Icons.Outlined.UploadFile, null, tint = goldText, modifier = Modifier.size(15.dp))
                        Text("Upload", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText, softWrap = false, maxLines = 1)
                    }
                }
                Box {
                    IconButton(onClick = { variableMenuExpanded = true }, modifier = Modifier.size(36.dp)) {
                        Icon(Icons.Default.MoreVert, contentDescription = "Insert custom-code variable", tint = textSecondary)
                    }
                    DropdownMenu(expanded = variableMenuExpanded, onDismissRequest = { variableMenuExpanded = false }) {
                        availableVariables.forEach { variable ->
                            DropdownMenuItem(
                                text = { Text(variable, fontSize = 12.sp) },
                                leadingIcon = { Icon(Icons.Outlined.DataObject, null, modifier = Modifier.size(16.dp)) },
                                onClick = {
                                    html += variable
                                    usedVariables = (usedVariables + variable).distinct()
                                    onUpdate(field.copy(customCodeHtml = html, customCodeCss = css, customVariables = usedVariables))
                                    variableMenuExpanded = false
                                }
                            )
                        }
                        Divider()
                        DropdownMenuItem(
                            text = { Text("Assign new variable", fontSize = 12.sp) },
                            leadingIcon = { Icon(Icons.Outlined.EditNote, null, modifier = Modifier.size(16.dp)) },
                            onClick = {
                                assignmentName = ""
                                assignmentExpression = availableVariables.firstOrNull() ?: ""
                                assignmentDialogOpen = true
                                variableMenuExpanded = false
                            }
                        )
                    }
                }
            }
        }
        if (assignmentDialogOpen) {
            AlertDialog(
                onDismissRequest = { assignmentDialogOpen = false },
                title = { Text("Assign response variable") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        OutlinedTextField(
                            value = assignmentName,
                            onValueChange = { assignmentName = it.filter { c -> c.isLetterOrDigit() || c == '_' } },
                            label = { Text("Variable name") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )
                        OutlinedTextField(
                            value = assignmentExpression,
                            onValueChange = { assignmentExpression = it },
                            label = { Text("Value / variable expression") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )
                        Text(
                            text = "Example: {{field_name}} or a fixed value like BDT 500",
                            fontSize = 11.sp,
                            color = textSecondary
                        )
                    }
                },
                confirmButton = {
                    TextButton(onClick = {
                        val normalizedName = assignmentName.trim().ifBlank { return@TextButton }
                        val variableTag = "{{${normalizedName}}}"
                        val nextAssignments = field.customVariableAssignments.toMutableMap()
                        nextAssignments[normalizedName] = assignmentExpression.trim()
                        html = if (html.contains(variableTag)) html else if (html.isBlank()) variableTag else "$html $variableTag"
                        usedVariables = (usedVariables + variableTag).distinct()
                        assignmentDialogOpen = false
                        onUpdate(
                            field.copy(
                                customCodeHtml = html,
                                customCodeCss = css,
                                customVariables = usedVariables,
                                customVariableAssignments = nextAssignments
                            )
                        )
                    }) {
                        Text("Add")
                    }
                },
                dismissButton = {
                    TextButton(onClick = { assignmentDialogOpen = false }) {
                        Text("Cancel")
                    }
                }
            )
        }
        if (uploadedName.isNotBlank()) {
            Text("Imported $uploadedName", fontSize = 11.sp, color = Color(0xFF10B981), fontWeight = FontWeight.SemiBold)
        }
        OutlinedTextField(
            value = html,
            onValueChange = { value ->
                html = value.take(200_000)
                usedVariables = Regex("""\{\{\s*([A-Za-z0-9_]+)\s*}}""")
                    .findAll(html).map { match -> "{{${match.groupValues[1]}}}" }.distinct().toList()
                onUpdate(field.copy(customCodeHtml = html, customCodeCss = css, customVariables = usedVariables))
            },
            label = { Text("HTML") },
            supportingText = { Text("Scripts, iframes, event handlers, and javascript: URLs are removed when hosted.") },
            modifier = Modifier.fillMaxWidth().height(150.dp),
            shape = RoundedCornerShape(10.dp),
            singleLine = false,
            maxLines = 12
        )
        OutlinedTextField(
            value = css,
            onValueChange = { value ->
                css = value.take(100_000)
                onUpdate(field.copy(customCodeHtml = html, customCodeCss = css, customVariables = usedVariables))
            },
            label = { Text("Scoped CSS") },
            modifier = Modifier.fillMaxWidth().height(110.dp),
            shape = RoundedCornerShape(10.dp),
            singleLine = false,
            maxLines = 8
        )
        if (usedVariables.isNotEmpty()) {
            Text("Used variables: ${usedVariables.joinToString(", ")}", fontSize = 11.sp, color = textSecondary)
        }
        if (html.isNotBlank()) {
            Spacer(modifier = Modifier.height(4.dp))
            Text("LIVE PREVIEW (লাইভ প্রিভিউ):", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
            Card(
                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF14120C) else Color(0xFFF8FAFC)),
                border = BorderStroke(1.dp, if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)),
                shape = RoundedCornerShape(10.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Box(modifier = Modifier.padding(8.dp)) {
                    FormHtmlPreview(
                        htmlContent = html,
                        cssContent = css,
                        isDark = isDark,
                        modifier = Modifier.fillMaxWidth().heightIn(min = 90.dp, max = 260.dp)
                    )
                }
            }
        }
    }
}

@Composable
private fun ProductShowcaseSettingsEditor(
    themeConfig: FormThemeConfig,
    formProducts: List<FormProductItem>,
    isDark: Boolean,
    viewModel: AppViewModel,
    onClose: () -> Unit
) {
    val context = LocalContext.current
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val textPrimary = if (isDark) Color(0xFFF3F4F6) else Color(0xFF111827)
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val goldPrimary = Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)
    val cardBg = if (isDark) Color(0xFF1F1A0E) else Color.White

    val primaryProduct = formProducts.firstOrNull() ?: FormProductItem(
        title = viewModel.formTitle.value.ifBlank { "Flagship Product" },
        imageUrl = themeConfig.productImageUrl
    )

    var activeTab by remember { mutableStateOf("DETAILS") } // "DETAILS", "GALLERY", "VARIANTS", "VISIBILITY"

    // Details states
    var titleInput by remember(primaryProduct.title, viewModel.formTitle.value) {
        mutableStateOf(primaryProduct.title.ifBlank { viewModel.formTitle.value })
    }
    var eyebrowInput by remember(themeConfig.eyebrowText) { mutableStateOf(themeConfig.eyebrowText) }
    var badgeInput by remember(themeConfig.badgeText) { mutableStateOf(themeConfig.badgeText) }
    var descInput by remember(primaryProduct.description, viewModel.formDescription.value) {
        mutableStateOf(primaryProduct.description.ifBlank { viewModel.formDescription.value })
    }
    var priceInput by remember(primaryProduct.price) {
        mutableStateOf(if (primaryProduct.price > 0) primaryProduct.price.toString() else "")
    }
    var wasPriceInput by remember(themeConfig.wasPrice) {
        mutableStateOf(if (themeConfig.wasPrice > 0) themeConfig.wasPrice.toString() else "")
    }
    var skuInput by remember(primaryProduct.sku) { mutableStateOf(primaryProduct.sku) }
    var stockInput by remember(primaryProduct.stock) { mutableStateOf(primaryProduct.stock.toString()) }
    var ratingScoreInput by remember(themeConfig.ratingScore) {
        mutableStateOf(if (themeConfig.ratingScore > 0) themeConfig.ratingScore.toString() else "")
    }
    var ratingCountInput by remember(themeConfig.ratingCount) {
        mutableStateOf(if (themeConfig.ratingCount > 0) themeConfig.ratingCount.toString() else "")
    }
    var currencyInput by remember(themeConfig.currencyCode) { mutableStateOf(themeConfig.currencyCode) }

    // Image Gallery states & launchers
    var isUploadingMainImage by remember { mutableStateOf(false) }
    var isUploadingGalleryImage by remember { mutableStateOf(false) }

    val mainImagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            isUploadingMainImage = true
            viewModel.uploadProductImage(uri, context) { success, msg, uploadedUrl ->
                isUploadingMainImage = false
                if (success && !uploadedUrl.isNullOrBlank()) {
                    viewModel.updateFormThemeConfig(themeConfig.copy(productImageUrl = uploadedUrl))
                    val updated = primaryProduct.copy(imageUrl = uploadedUrl)
                    viewModel.saveOrUpdateShowcaseProduct(updated)
                    Toast.makeText(context, "Showcase photo updated", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(context, msg.ifBlank { "Upload failed" }, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    val galleryImagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            isUploadingGalleryImage = true
            viewModel.uploadProductImage(uri, context) { success, msg, uploadedUrl ->
                isUploadingGalleryImage = false
                if (success && !uploadedUrl.isNullOrBlank()) {
                    val updatedGallery = (primaryProduct.galleryUrls + uploadedUrl).distinct()
                    val updated = primaryProduct.copy(galleryUrls = updatedGallery)
                    viewModel.saveOrUpdateShowcaseProduct(updated)
                    Toast.makeText(context, "Gallery photo added", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(context, msg.ifBlank { "Upload failed" }, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    // Delivery states
    var insideChargeInput by remember(primaryProduct.deliveryInfo.insideDhakaCharge) {
        mutableStateOf(primaryProduct.deliveryInfo.insideDhakaCharge.ifBlank { "60" })
    }
    var outsideChargeInput by remember(primaryProduct.deliveryInfo.outsideDhakaCharge) {
        mutableStateOf(primaryProduct.deliveryInfo.outsideDhakaCharge.ifBlank { "120" })
    }
    var timeInsideInput by remember(primaryProduct.deliveryInfo.deliveryTimeInside) {
        mutableStateOf(primaryProduct.deliveryInfo.deliveryTimeInside.ifBlank { "24-48 Hours" })
    }
    var timeOutsideInput by remember(primaryProduct.deliveryInfo.deliveryTimeOutside) {
        mutableStateOf(primaryProduct.deliveryInfo.deliveryTimeOutside.ifBlank { "2-4 Business Days" })
    }
    var isCodAvailableInput by remember(primaryProduct.deliveryInfo.isCodAvailable) {
        mutableStateOf(primaryProduct.deliveryInfo.isCodAvailable)
    }
    var returnPolicyInput by remember(primaryProduct.deliveryInfo.returnPolicy) {
        mutableStateOf(primaryProduct.deliveryInfo.returnPolicy.ifBlank { "7 Days Free Replacement Guarantee" })
    }
    var warrantyInput by remember(primaryProduct.deliveryInfo.warranty) {
        mutableStateOf(primaryProduct.deliveryInfo.warranty.ifBlank { "1 Year Official Brand Warranty" })
    }
    var deliveryNotesInput by remember(primaryProduct.deliveryInfo.notes) {
        mutableStateOf(primaryProduct.deliveryInfo.notes.ifBlank { "Check package in front of delivery person before payment" })
    }

    // Specifications states
    var showAddSpecDialog by remember { mutableStateOf(false) }
    var editingSpec by remember { mutableStateOf<ProductSpecItem?>(null) }
    var newSpecKey by remember { mutableStateOf("") }
    var newSpecValue by remember { mutableStateOf("") }

    // Variants states
    var showAddVariantDialog by remember { mutableStateOf(false) }
    var editingVariant by remember { mutableStateOf<ProductVariantItem?>(null) }
    var newVarName by remember { mutableStateOf("") }
    var newVarColorHex by remember { mutableStateOf("#141518") }
    var newVarPrice by remember { mutableStateOf("") }
    var newVarSku by remember { mutableStateOf("") }
    var newVarStock by remember { mutableStateOf("100") }
    var newVarDesc by remember { mutableStateOf("") }

    // Assurances & Identity states
    var brandNameInput by remember(themeConfig.brandName) { mutableStateOf(themeConfig.brandName.ifBlank { "NORTHWAVE" }) }
    var brandBadgeInput by remember(themeConfig.brandBadge) { mutableStateOf(themeConfig.brandBadge.ifBlank { "PRO" }) }
    var assurance1TitleInput by remember(themeConfig.assurance1Title) { mutableStateOf(themeConfig.assurance1Title.ifBlank { "Fast & Free Delivery" }) }
    var assurance1SubInput by remember(themeConfig.assurance1Subtitle) { mutableStateOf(themeConfig.assurance1Subtitle.ifBlank { "Complimentary express 2-day delivery across Bangladesh" }) }
    var assurance2TitleInput by remember(themeConfig.assurance2Title) { mutableStateOf(themeConfig.assurance2Title.ifBlank { "30-Day Risk Free" }) }
    var assurance2SubInput by remember(themeConfig.assurance2Subtitle) { mutableStateOf(themeConfig.assurance2Subtitle.ifBlank { "Keep only what fits your listening needs" }) }
    var assurance3TitleInput by remember(themeConfig.assurance3Title) { mutableStateOf(themeConfig.assurance3Title.ifBlank { "2-Year Protection" }) }
    var assurance3SubInput by remember(themeConfig.assurance3Subtitle) { mutableStateOf(themeConfig.assurance3Subtitle.ifBlank { "Full replacement manufacturer warranty" }) }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(if (isDark) Color(0xFF141A24) else Color(0xFFF1F5F9))
            .border(1.dp, cardBorder, RoundedCornerShape(14.dp))
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Header with title and close button
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Icon(Icons.Outlined.Tune, contentDescription = null, tint = goldText, modifier = Modifier.size(18.dp))
                Text("Customize Product Showcase", fontWeight = FontWeight.Bold, fontSize = 13.5.sp, color = textPrimary)
            }
            IconButton(onClick = onClose, modifier = Modifier.size(26.dp)) {
                Icon(Icons.Default.Close, contentDescription = "Close", tint = textSecondary, modifier = Modifier.size(16.dp))
            }
        }

        // 7 Category Tabs
        val tabs = listOf(
            "DETAILS" to "Details & Price",
            "DELIVERY" to "Delivery & Shipping",
            "SPECS" to "Specs (${primaryProduct.specifications.size})",
            "VARIANTS" to "Colors & Variants (${primaryProduct.productVariants.size})",
            "INFO" to "Assurances & Identity",
            "GALLERY" to "Image Gallery (${primaryProduct.galleryUrls.size})",
            "VISIBILITY" to "Section Visibility"
        )
        LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
            items(tabs) { (tabKey, tabLabel) ->
                val isSelected = activeTab == tabKey
                Surface(
                    onClick = { activeTab = tabKey },
                    shape = RoundedCornerShape(8.dp),
                    color = if (isSelected) (if (isDark) Color(0xFF281E0A) else Color(0xFFFFFBEB)) else cardBg,
                    border = BorderStroke(1.dp, if (isSelected) goldPrimary else cardBorder)
                ) {
                    Text(
                        text = tabLabel,
                        fontSize = 11.5.sp,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                        color = if (isSelected) goldText else textSecondary,
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                    )
                }
            }
        }

        HorizontalDivider(color = cardBorder, thickness = 0.8.dp)

        // TAB 1: DETAILS & PRICING
        if (activeTab == "DETAILS") {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(
                    value = titleInput,
                    onValueChange = {
                        titleInput = it.take(120)
                        val updated = primaryProduct.copy(title = titleInput)
                        viewModel.saveOrUpdateShowcaseProduct(updated)
                        viewModel.formTitle.value = titleInput
                    },
                    label = { Text("Product Title / Name") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    singleLine = true
                )

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = eyebrowInput,
                        onValueChange = {
                            eyebrowInput = it.take(60)
                            viewModel.updateFormThemeConfig(themeConfig.copy(eyebrowText = eyebrowInput))
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(category = eyebrowInput))
                        },
                        label = { Text("Category / Eyebrow") },
                        placeholder = { Text("e.g. Flagship Audio • 2026") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = badgeInput,
                        onValueChange = {
                            badgeInput = it.take(30)
                            viewModel.updateFormThemeConfig(themeConfig.copy(badgeText = badgeInput))
                        },
                        label = { Text("Badge Tag") },
                        placeholder = { Text("e.g. Best Seller") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                OutlinedTextField(
                    value = descInput,
                    onValueChange = {
                        descInput = it.take(600)
                        viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(description = descInput))
                        viewModel.formDescription.value = descInput
                    },
                    label = { Text("Product Description / Subtitle") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    maxLines = 3
                )

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = priceInput,
                        onValueChange = { v ->
                            priceInput = v.filter { ch -> ch.isDigit() || ch == '.' }.take(10)
                            val p = priceInput.toDoubleOrNull() ?: 0.0
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(price = p))
                        },
                        label = { Text("Regular Price (${themeConfig.currencyCode})") },
                        placeholder = { Text("e.g. 1999") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = wasPriceInput,
                        onValueChange = { v ->
                            wasPriceInput = v.filter { ch -> ch.isDigit() || ch == '.' }.take(10)
                            val wp = wasPriceInput.toDoubleOrNull() ?: 0.0
                            viewModel.updateFormThemeConfig(themeConfig.copy(wasPrice = wp))
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(salePrice = wp))
                        },
                        label = { Text("Was / Compare Price") },
                        placeholder = { Text("e.g. 2499") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = skuInput,
                        onValueChange = {
                            skuInput = it.take(50)
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(sku = skuInput))
                        },
                        label = { Text("Product SKU") },
                        placeholder = { Text("e.g. SKU-PROD-01") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = stockInput,
                        onValueChange = {
                            stockInput = it.filter(Char::isDigit).take(6)
                            val s = stockInput.toIntOrNull() ?: 0
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(stock = s))
                        },
                        label = { Text("Stock Quantity") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = ratingScoreInput,
                        onValueChange = { v ->
                            ratingScoreInput = v.filter { ch -> ch.isDigit() || ch == '.' }.take(4)
                            val num = (ratingScoreInput.toDoubleOrNull() ?: 0.0).coerceIn(0.0, 5.0)
                            viewModel.updateFormThemeConfig(themeConfig.copy(ratingScore = num))
                        },
                        label = { Text("Star Rating (0-5)") },
                        placeholder = { Text("e.g. 4.9") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = ratingCountInput,
                        onValueChange = { v ->
                            ratingCountInput = v.filter(Char::isDigit).take(6)
                            val num = ratingCountInput.toIntOrNull() ?: 0
                            viewModel.updateFormThemeConfig(themeConfig.copy(ratingCount = num))
                        },
                        label = { Text("Review Count") },
                        placeholder = { Text("e.g. 142") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                Text("CURRENCY CODE", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                    listOf("BDT", "USD", "EUR", "GBP", "INR").forEach { curr ->
                        val isCurrSel = themeConfig.currencyCode.equals(curr, ignoreCase = true)
                        Surface(
                            onClick = { viewModel.updateFormThemeConfig(themeConfig.copy(currencyCode = curr)) },
                            shape = RoundedCornerShape(8.dp),
                            color = if (isCurrSel) (if (isDark) Color(0xFF281E0A) else Color(0xFFFFFBEB)) else cardBg,
                            border = BorderStroke(1.dp, if (isCurrSel) goldPrimary else cardBorder),
                            modifier = Modifier.weight(1f)
                        ) {
                            Text(
                                text = curr,
                                fontSize = 11.5.sp,
                                fontWeight = if (isCurrSel) FontWeight.Bold else FontWeight.Normal,
                                color = if (isCurrSel) goldText else textPrimary,
                                textAlign = TextAlign.Center,
                                modifier = Modifier.padding(vertical = 8.dp)
                            )
                        }
                    }
                }
            }
        }

        // TAB 2: DELIVERY & SHIPPING CHARGES
        if (activeTab == "DELIVERY") {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("DELIVERY CHARGES & SPEED", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = insideChargeInput,
                        onValueChange = { v ->
                            insideChargeInput = v.filter { ch -> ch.isDigit() || ch == '.' }.take(6)
                            val updated = primaryProduct.deliveryInfo.copy(insideDhakaCharge = insideChargeInput)
                            viewModel.updateShowcaseDeliveryInfo(updated)
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                        },
                        label = { Text("Inside City Fee (${themeConfig.currencyCode})") },
                        placeholder = { Text("e.g. 60") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = outsideChargeInput,
                        onValueChange = { v ->
                            outsideChargeInput = v.filter { ch -> ch.isDigit() || ch == '.' }.take(6)
                            val updated = primaryProduct.deliveryInfo.copy(outsideDhakaCharge = outsideChargeInput)
                            viewModel.updateShowcaseDeliveryInfo(updated)
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                        },
                        label = { Text("Outside City Fee (${themeConfig.currencyCode})") },
                        placeholder = { Text("e.g. 120") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = timeInsideInput,
                        onValueChange = { v ->
                            timeInsideInput = v.take(30)
                            val updated = primaryProduct.deliveryInfo.copy(deliveryTimeInside = timeInsideInput)
                            viewModel.updateShowcaseDeliveryInfo(updated)
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                        },
                        label = { Text("Inside City Delivery Time") },
                        placeholder = { Text("e.g. 24-48 Hours") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = timeOutsideInput,
                        onValueChange = { v ->
                            timeOutsideInput = v.take(30)
                            val updated = primaryProduct.deliveryInfo.copy(deliveryTimeOutside = timeOutsideInput)
                            viewModel.updateShowcaseDeliveryInfo(updated)
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                        },
                        label = { Text("Outside City Delivery Time") },
                        placeholder = { Text("e.g. 2-4 Business Days") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                // COD Toggle
                Surface(
                    onClick = {
                        isCodAvailableInput = !isCodAvailableInput
                        val updated = primaryProduct.deliveryInfo.copy(isCodAvailable = isCodAvailableInput)
                        viewModel.updateShowcaseDeliveryInfo(updated)
                        viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                    },
                    shape = RoundedCornerShape(10.dp),
                    color = if (isCodAvailableInput) Color(0xFF10B981).copy(alpha = 0.12f) else (if (isDark) Color(0xFF1F1A0E) else Color(0xFFF1F5F9)),
                    border = BorderStroke(1.dp, if (isCodAvailableInput) Color(0xFF10B981) else cardBorder),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text("Cash on Delivery (COD) Nationwide", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                            Text("Allow customers to pay cash when package arrives", fontSize = 11.sp, color = textSecondary)
                        }
                        Switch(
                            checked = isCodAvailableInput,
                            onCheckedChange = {
                                isCodAvailableInput = it
                                val updated = primaryProduct.deliveryInfo.copy(isCodAvailable = isCodAvailableInput)
                                viewModel.updateShowcaseDeliveryInfo(updated)
                                viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                            }
                        )
                    }
                }

                HorizontalDivider(color = cardBorder, thickness = 0.8.dp)

                Text("DELIVERY INSTRUCTIONS & POLICIES", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)

                OutlinedTextField(
                    value = deliveryNotesInput,
                    onValueChange = { v ->
                        deliveryNotesInput = v.take(250)
                        val updated = primaryProduct.deliveryInfo.copy(notes = deliveryNotesInput)
                        viewModel.updateShowcaseDeliveryInfo(updated)
                        viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                    },
                    label = { Text("Delivery Notice / Instructions to Customer") },
                    placeholder = { Text("e.g. Check package in front of delivery person before payment") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    maxLines = 2
                )

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = returnPolicyInput,
                        onValueChange = { v ->
                            returnPolicyInput = v.take(120)
                            val updated = primaryProduct.deliveryInfo.copy(returnPolicy = returnPolicyInput)
                            viewModel.updateShowcaseDeliveryInfo(updated)
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                        },
                        label = { Text("Return & Replacement Policy") },
                        placeholder = { Text("e.g. 7 Days Free Replacement Guarantee") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = warrantyInput,
                        onValueChange = { v ->
                            warrantyInput = v.take(120)
                            val updated = primaryProduct.deliveryInfo.copy(warranty = warrantyInput)
                            viewModel.updateShowcaseDeliveryInfo(updated)
                            viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(deliveryInfo = updated))
                        },
                        label = { Text("Official Warranty") },
                        placeholder = { Text("e.g. 1 Year Official Brand Warranty") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                // Sync button to auto-update form's SHIPPING options
                Button(
                    onClick = {
                        val curr = if (themeConfig.currencyCode == "USD") "$" else "৳"
                        val newOpts = listOf(
                            "Standard Courier — $timeInsideInput ($curr$insideChargeInput)",
                            "Express Courier — $timeOutsideInput ($curr$outsideChargeInput)",
                            "Store Pickup — Free"
                        )
                        val fields = viewModel.formFieldsList.value.toMutableList()
                        val sIdx = fields.indexOfFirst { it.type == FormFieldType.SHIPPING }
                        if (sIdx != -1) {
                            fields[sIdx] = fields[sIdx].copy(options = newOpts)
                        } else {
                            fields.add(FormFieldItem(
                                type = FormFieldType.SHIPPING,
                                label = "Delivery Area & Speed",
                                options = newOpts,
                                isRequired = true
                            ))
                        }
                        viewModel.formFieldsList.value = fields
                        viewModel.saveActiveFormToHostedList()
                        Toast.makeText(context, "Delivery charges synced to checkout dropdown ($curr$insideChargeInput / $curr$outsideChargeInput)", Toast.LENGTH_SHORT).show()
                    },
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = if (isDark) Color(0xFF281E0A) else Color(0xFFFFFBEB), contentColor = goldText),
                    border = BorderStroke(1.dp, goldPrimary),
                    modifier = Modifier.fillMaxWidth().height(42.dp)
                ) {
                    Icon(Icons.Default.Sync, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("Sync Charges to Checkout Form Dropdown", fontSize = 11.5.sp, fontWeight = FontWeight.Bold)
                }
            }
        }

        // TAB 3: SPECIFICATIONS
        if (activeTab == "SPECS") {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(1f).padding(end = 8.dp)) {
                        Text(
                            "PRODUCT SPECIFICATIONS (${primaryProduct.specifications.size})",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary
                        )
                        Text(
                            "Key features and technical specifications shown in the customer details accordion.",
                            fontSize = 11.sp,
                            color = textSecondary
                        )
                    }

                    Surface(
                        onClick = {
                            newSpecKey = ""
                            newSpecValue = ""
                            editingSpec = null
                            showAddSpecDialog = true
                        },
                        shape = RoundedCornerShape(8.dp),
                        color = goldPrimary.copy(alpha = 0.15f),
                        border = BorderStroke(1.dp, goldPrimary)
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(Icons.Default.Add, contentDescription = null, tint = goldText, modifier = Modifier.size(13.dp))
                            Text("+ Add Spec", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText)
                        }
                    }
                }

                if (primaryProduct.specifications.isNotEmpty()) {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        primaryProduct.specifications.forEach { spec ->
                            Card(
                                shape = RoundedCornerShape(8.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1A1610) else Color.White),
                                border = BorderStroke(1.dp, cardBorder),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(spec.key, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = goldText)
                                        Text(spec.value, fontSize = 11.5.sp, color = textPrimary)
                                    }
                                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                        IconButton(
                                            onClick = {
                                                editingSpec = spec
                                                newSpecKey = spec.key
                                                newSpecValue = spec.value
                                            },
                                            modifier = Modifier.size(26.dp)
                                        ) {
                                            Icon(Icons.Default.Edit, contentDescription = "Edit Spec", tint = textSecondary, modifier = Modifier.size(14.dp))
                                        }
                                        IconButton(
                                            onClick = {
                                                val updated = primaryProduct.specifications.filterNot { it.id == spec.id }
                                                viewModel.updateShowcaseSpecifications(updated)
                                                viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(specifications = updated))
                                            },
                                            modifier = Modifier.size(26.dp)
                                        ) {
                                            Icon(Icons.Default.Close, contentDescription = "Remove Spec", tint = Color(0xFFEF4444), modifier = Modifier.size(14.dp))
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    Text(
                        "No specifications added yet. Tap '+ Add Spec' to add technical specs, dimensions, battery life, drivers, or materials.",
                        fontSize = 11.5.sp,
                        color = textSecondary
                    )
                }
            }
        }

        // TAB 4: COLORS & VARIANTS
        if (activeTab == "VARIANTS") {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(1f).padding(end = 8.dp)) {
                        Text(
                            "COLORS & PRODUCT VARIANTS (${primaryProduct.productVariants.size})",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary
                        )
                        Text(
                            "Custom colors, finish swatches, memory sizes, or bundles with individual pricing.",
                            fontSize = 11.sp,
                            color = textSecondary
                        )
                    }

                    Surface(
                        onClick = {
                            newVarName = ""
                            newVarColorHex = "#141518"
                            newVarPrice = ""
                            newVarSku = ""
                            newVarStock = "100"
                            editingVariant = null
                            showAddVariantDialog = true
                        },
                        shape = RoundedCornerShape(8.dp),
                        color = Color(0xFF10B981).copy(alpha = 0.12f),
                        border = BorderStroke(1.dp, Color(0xFF10B981))
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(Icons.Default.Add, contentDescription = null, tint = Color(0xFF10B981), modifier = Modifier.size(13.dp))
                            Text("+ Add Variant", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                        }
                    }
                }

                if (primaryProduct.productVariants.isNotEmpty()) {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        primaryProduct.productVariants.forEach { pv ->
                            Card(
                                shape = RoundedCornerShape(8.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1A1610) else Color.White),
                                border = BorderStroke(1.dp, cardBorder),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.weight(1f)) {
                                        val parsedColor = remember(pv.colorHex) {
                                            try {
                                                if (pv.colorHex.isNotBlank()) Color(android.graphics.Color.parseColor(pv.colorHex)) else Color(0xFF141518)
                                            } catch (_: Exception) { Color(0xFF141518) }
                                        }
                                        Box(
                                            modifier = Modifier
                                                .size(24.dp)
                                                .clip(CircleShape)
                                                .background(parsedColor)
                                                .border(1.5.dp, cardBorder, CircleShape)
                                        )

                                        Column {
                                            Text(pv.name, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                            val curr = if (themeConfig.currencyCode == "USD") "$" else "৳"
                                            Text(
                                                "$curr${pv.price.toInt()}${if (pv.sku.isNotBlank()) " | SKU: ${pv.sku}" else ""}${if (pv.stock > 0) " | Stock: ${pv.stock}" else ""}${if (pv.colorHex.isNotBlank()) " | ${pv.colorHex}" else ""}",
                                                fontSize = 11.sp,
                                                color = textSecondary
                                            )
                                        }
                                    }
                                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                        IconButton(
                                            onClick = {
                                                editingVariant = pv
                                                newVarName = pv.name
                                                newVarColorHex = pv.colorHex.ifBlank { "#141518" }
                                                newVarPrice = if (pv.price > 0) pv.price.toInt().toString() else ""
                                                newVarSku = pv.sku
                                                newVarStock = pv.stock.toString()
                                            },
                                            modifier = Modifier.size(26.dp)
                                        ) {
                                            Icon(Icons.Default.Edit, contentDescription = "Edit Variant", tint = textSecondary, modifier = Modifier.size(14.dp))
                                        }
                                        IconButton(
                                            onClick = {
                                                val updated = primaryProduct.productVariants.filterNot { it.id == pv.id }
                                                viewModel.updateShowcaseVariants(updated)
                                                viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(productVariants = updated))
                                            },
                                            modifier = Modifier.size(26.dp)
                                        ) {
                                            Icon(Icons.Default.Close, contentDescription = "Remove Variant", tint = Color(0xFFEF4444), modifier = Modifier.size(14.dp))
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    Text(
                        "No variants configured yet. Tap '+ Add Variant' to add colors, storage options, sizes, or models.",
                        fontSize = 11.5.sp,
                        color = textSecondary
                    )
                }
            }
        }

        // TAB 5: ASSURANCES & BRAND IDENTITY
        if (activeTab == "INFO") {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("STORE & BRAND IDENTITY", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = brandNameInput,
                        onValueChange = { v ->
                            brandNameInput = v.take(50)
                            viewModel.updateFormThemeConfig(themeConfig.copy(brandName = brandNameInput))
                        },
                        label = { Text("Brand / Store Name") },
                        placeholder = { Text("e.g. NORTHWAVE") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = brandBadgeInput,
                        onValueChange = { v ->
                            brandBadgeInput = v.take(20)
                            viewModel.updateFormThemeConfig(themeConfig.copy(brandBadge = brandBadgeInput))
                        },
                        label = { Text("Brand Badge") },
                        placeholder = { Text("e.g. PRO") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                HorizontalDivider(color = cardBorder, thickness = 0.8.dp)

                Text("3 TRUST ASSURANCE CARDS", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)

                // Card 1
                Card(
                    shape = RoundedCornerShape(8.dp),
                    colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1A1610) else Color.White),
                    border = BorderStroke(1.dp, cardBorder),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("Assurance #1 (Delivery)", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText)
                        OutlinedTextField(
                            value = assurance1TitleInput,
                            onValueChange = { v ->
                                assurance1TitleInput = v.take(60)
                                viewModel.updateFormThemeConfig(themeConfig.copy(assurance1Title = assurance1TitleInput))
                            },
                            label = { Text("Title") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = assurance1SubInput,
                            onValueChange = { v ->
                                assurance1SubInput = v.take(120)
                                viewModel.updateFormThemeConfig(themeConfig.copy(assurance1Subtitle = assurance1SubInput))
                            },
                            label = { Text("Subtitle") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                    }
                }

                // Card 2
                Card(
                    shape = RoundedCornerShape(8.dp),
                    colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1A1610) else Color.White),
                    border = BorderStroke(1.dp, cardBorder),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("Assurance #2 (Return / Guarantee)", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText)
                        OutlinedTextField(
                            value = assurance2TitleInput,
                            onValueChange = { v ->
                                assurance2TitleInput = v.take(60)
                                viewModel.updateFormThemeConfig(themeConfig.copy(assurance2Title = assurance2TitleInput))
                            },
                            label = { Text("Title") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = assurance2SubInput,
                            onValueChange = { v ->
                                assurance2SubInput = v.take(120)
                                viewModel.updateFormThemeConfig(themeConfig.copy(assurance2Subtitle = assurance2SubInput))
                            },
                            label = { Text("Subtitle") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                    }
                }

                // Card 3
                Card(
                    shape = RoundedCornerShape(8.dp),
                    colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1A1610) else Color.White),
                    border = BorderStroke(1.dp, cardBorder),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("Assurance #3 (Warranty / Protection)", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText)
                        OutlinedTextField(
                            value = assurance3TitleInput,
                            onValueChange = { v ->
                                assurance3TitleInput = v.take(60)
                                viewModel.updateFormThemeConfig(themeConfig.copy(assurance3Title = assurance3TitleInput))
                            },
                            label = { Text("Title") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = assurance3SubInput,
                            onValueChange = { v ->
                                assurance3SubInput = v.take(120)
                                viewModel.updateFormThemeConfig(themeConfig.copy(assurance3Subtitle = assurance3SubInput))
                            },
                            label = { Text("Subtitle") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                    }
                }
            }
        }

        // TAB 6: IMAGE GALLERY
        if (activeTab == "GALLERY") {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("MAIN SHOWCASE PHOTO", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)

                if (themeConfig.productImageUrl.isNotBlank()) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(140.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isDark) Color(0xFF0F172A) else Color(0xFFF1F5F9)),
                        contentAlignment = Alignment.Center
                    ) {
                        AsyncImage(
                            model = themeConfig.productImageUrl,
                            contentDescription = "Main Product Photo",
                            modifier = Modifier.fillMaxSize(),
                            contentScale = ContentScale.Fit
                        )
                        IconButton(
                            onClick = {
                                viewModel.updateFormThemeConfig(themeConfig.copy(productImageUrl = ""))
                                viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(imageUrl = ""))
                            },
                            modifier = Modifier
                                .align(Alignment.TopEnd)
                                .padding(6.dp)
                                .size(26.dp)
                                .background(Color.Black.copy(alpha = 0.65f), CircleShape)
                        ) {
                            Icon(Icons.Default.Close, contentDescription = "Clear", tint = Color.White, modifier = Modifier.size(14.dp))
                        }
                    }
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Button(
                        onClick = { mainImagePicker.launch("image/*") },
                        enabled = !isUploadingMainImage,
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = if (isDark) Color(0xFF281E0A) else Color(0xFFFFFBEB), contentColor = goldText),
                        border = BorderStroke(1.dp, goldPrimary),
                        modifier = Modifier.weight(1f).height(42.dp)
                    ) {
                        if (isUploadingMainImage) {
                            CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp, color = goldText)
                            Spacer(Modifier.width(6.dp))
                            Text("Uploading...", fontSize = 11.5.sp, fontWeight = FontWeight.Bold)
                        } else {
                            Icon(Icons.Outlined.CloudUpload, null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text(if (themeConfig.productImageUrl.isNotBlank()) "Change Main Photo" else "Upload Main Photo", fontSize = 11.5.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }

                HorizontalDivider(color = cardBorder, thickness = 0.8.dp)

                // Multi-Image Gallery List
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        "ADDITIONAL GALLERY PHOTOS (${primaryProduct.galleryUrls.size})",
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = textSecondary
                    )

                    Surface(
                        onClick = { galleryImagePicker.launch("image/*") },
                        shape = RoundedCornerShape(8.dp),
                        color = if (isDark) Color(0xFF221A0C) else Color(0xFFFEFDF5),
                        border = BorderStroke(1.dp, goldPrimary)
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(Icons.Outlined.AddPhotoAlternate, contentDescription = null, tint = goldText, modifier = Modifier.size(13.dp))
                            Text("+ Upload to Gallery", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText)
                        }
                    }
                }

                if (isUploadingGalleryImage) {
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp, color = goldPrimary)
                        Spacer(Modifier.width(6.dp))
                        Text("Uploading gallery photo...", fontSize = 11.5.sp, color = textSecondary)
                    }
                }

                if (primaryProduct.galleryUrls.isNotEmpty()) {
                    LazyRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        items(primaryProduct.galleryUrls) { gUrl ->
                            Box(
                                modifier = Modifier
                                    .size(76.dp)
                                    .clip(RoundedCornerShape(8.dp))
                                    .border(1.dp, cardBorder, RoundedCornerShape(8.dp))
                                    .background(if (isDark) Color(0xFF100D07) else Color.White)
                            ) {
                                AsyncImage(
                                    model = gUrl,
                                    contentDescription = "Gallery Thumbnail",
                                    modifier = Modifier.fillMaxSize(),
                                    contentScale = ContentScale.Crop
                                )
                                IconButton(
                                    onClick = {
                                        val updated = primaryProduct.galleryUrls.filterNot { it == gUrl }
                                        viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(galleryUrls = updated))
                                    },
                                    modifier = Modifier
                                        .align(Alignment.TopEnd)
                                        .size(20.dp)
                                        .background(Color.Black.copy(alpha = 0.65f), CircleShape)
                                ) {
                                    Icon(Icons.Default.Close, contentDescription = "Remove", tint = Color.White, modifier = Modifier.size(12.dp))
                                }
                            }
                        }
                    }
                } else {
                    Text(
                        "No additional gallery photos yet. Tap '+ Upload to Gallery' to add photos.",
                        fontSize = 11.5.sp,
                        color = textSecondary
                    )
                }
            }
        }

        // TAB 7: SECTION VISIBILITY & LAYOUT
        if (activeTab == "VISIBILITY") {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(
                    "Control which sections appear on the live storefront product hero. Toggle off any section to hide it completely from customers.",
                    fontSize = 11.5.sp,
                    color = textSecondary
                )

                SettingsToggleRow("Store Header (ব্র্যান্ড হেডার)", !themeConfig.hideHeader) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideHeader = !visible))
                }

                SettingsToggleRow("Eyebrow / Category Tag (ক্যাটাগরি ট্যাগ)", !themeConfig.hideEyebrow) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideEyebrow = !visible))
                }

                SettingsToggleRow("Rating & Reviews Badge (স্টার রেটিং)", !themeConfig.hideRating) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideRating = !visible))
                }

                SettingsToggleRow("Price & Discount Badge (মূল্য ও ডিসকাউন্ট)", !themeConfig.hidePrice) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hidePrice = !visible))
                }

                SettingsToggleRow("Product Variants (ভ্যারিয়েন্ট নির্বাচক)", !themeConfig.hideSwatches) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideSwatches = !visible))
                }

                SettingsToggleRow("Size / Profile Chips (সাইজ ও মডেল চিপস)", !themeConfig.hideChips) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideChips = !visible))
                }

                SettingsToggleRow("Quantity Selector (পরিমাণ নির্বাচক)", !themeConfig.hideQty) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideQty = !visible))
                }

                SettingsToggleRow("Delivery & Assurances (ডেলিভারি তথ্য)", !themeConfig.hideDelivery) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideDelivery = !visible))
                }

                SettingsToggleRow("Specifications Table (স্পেসিফিকেশন)", !themeConfig.hideSpecs) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideSpecs = !visible))
                }

                SettingsToggleRow("Order Summary Breakdown (অর্ডার সামারি)", !themeConfig.hideSummary) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideSummary = !visible))
                }

                SettingsToggleRow("Promo / Coupon Code Box (কুপন কোড বক্স)", !themeConfig.hidePromo) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hidePromo = !visible))
                }

                SettingsToggleRow("Trust Assurances Grid (নিরাপত্তা গ্যারান্টি)", !themeConfig.hideAssurances) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideAssurances = !visible))
                }

                SettingsToggleRow("Details Accordions (বিস্তারিত স্পেসিফিকেশন)", !themeConfig.hideDetails) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideDetails = !visible))
                }

                SettingsToggleRow("Mobile Sticky Bottom Dock (মোবাইল ডক বার)", !themeConfig.hideMobileDock) { visible ->
                    viewModel.updateFormThemeConfig(themeConfig.copy(hideMobileDock = !visible))
                }
            }
        }
    }

    // Dialog: Add / Edit Specification
    if (showAddSpecDialog || editingSpec != null) {
        val isEditing = editingSpec != null
        AlertDialog(
            onDismissRequest = {
                showAddSpecDialog = false
                editingSpec = null
            },
            title = { Text(if (isEditing) "Edit Specification" else "Add Specification", fontWeight = FontWeight.Bold, color = textPrimary) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Popular Suggestions:", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                    val specSuggestions = listOf("Connectivity", "Battery Life", "Noise Cancellation", "Drivers", "Charging", "Weight", "Bluetooth", "Warranty")
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                        items(specSuggestions) { sug ->
                            Surface(
                                onClick = { newSpecKey = sug },
                                shape = RoundedCornerShape(6.dp),
                                color = if (newSpecKey == sug) goldPrimary.copy(alpha = 0.2f) else (if (isDark) Color(0xFF221A0C) else Color(0xFFF1F5F9)),
                                border = BorderStroke(0.6.dp, if (newSpecKey == sug) goldPrimary else cardBorder)
                            ) {
                                Text(sug, fontSize = 10.sp, color = if (newSpecKey == sug) goldText else textPrimary, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                            }
                        }
                    }

                    OutlinedTextField(
                        value = newSpecKey,
                        onValueChange = { newSpecKey = it.take(60) },
                        label = { Text("Specification Key / Feature") },
                        placeholder = { Text("e.g. Battery Life") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true
                    )
                    OutlinedTextField(
                        value = newSpecValue,
                        onValueChange = { newSpecValue = it.take(200) },
                        label = { Text("Specification Value") },
                        placeholder = { Text("e.g. 40 Hours Playtime") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        if (newSpecKey.isNotBlank() && newSpecValue.isNotBlank()) {
                            if (isEditing && editingSpec != null) {
                                val updated = primaryProduct.specifications.map {
                                    if (it.id == editingSpec!!.id) it.copy(key = newSpecKey.trim(), value = newSpecValue.trim()) else it
                                }
                                viewModel.updateShowcaseSpecifications(updated)
                                viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(specifications = updated))
                                Toast.makeText(context, "Specification updated", Toast.LENGTH_SHORT).show()
                            } else {
                                val newSpec = ProductSpecItem(
                                    id = java.util.UUID.randomUUID().toString(),
                                    key = newSpecKey.trim(),
                                    value = newSpecValue.trim()
                                )
                                val updated = primaryProduct.specifications + newSpec
                                viewModel.updateShowcaseSpecifications(updated)
                                viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(specifications = updated))
                                Toast.makeText(context, "Specification added", Toast.LENGTH_SHORT).show()
                            }
                            showAddSpecDialog = false
                            editingSpec = null
                            newSpecKey = ""
                            newSpecValue = ""
                        } else {
                            Toast.makeText(context, "Enter both key and value", Toast.LENGTH_SHORT).show()
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                ) { Text(if (isEditing) "Save Changes" else "Add Specification", fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = {
                    showAddSpecDialog = false
                    editingSpec = null
                }) { Text("Cancel") }
            }
        )
    }

    // Dialog: Add / Edit Variant
    if (showAddVariantDialog || editingVariant != null) {
        val isEditing = editingVariant != null
        AlertDialog(
            onDismissRequest = {
                showAddVariantDialog = false
                editingVariant = null
            },
            title = { Text(if (isEditing) "Edit Product Variant" else "Add Color / Variant", fontWeight = FontWeight.Bold, color = textPrimary) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = newVarName,
                        onValueChange = { newVarName = it.take(60) },
                        label = { Text("Variant Name (e.g. Midnight Black, 128GB, XL)") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true
                    )

                    Text("Color Swatch Palette:", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                    val colorPalette = listOf(
                        "#141518" to "Black",
                        "#E4E0D7" to "Sandstone",
                        "#283A4F" to "Navy",
                        "#777B83" to "Grey",
                        "#94A3B8" to "Silver",
                        "#EF4444" to "Red",
                        "#10B981" to "Green",
                        "#3B82F6" to "Blue",
                        "#8B5CF6" to "Purple",
                        "#FFFFFF" to "White"
                    )
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        items(colorPalette) { (hex, _) ->
                            val isSel = newVarColorHex.equals(hex, ignoreCase = true)
                            val c = try { Color(android.graphics.Color.parseColor(hex)) } catch (_: Exception) { Color.Gray }
                            Box(
                                modifier = Modifier
                                    .size(28.dp)
                                    .clip(CircleShape)
                                    .background(c)
                                    .border(if (isSel) 2.5.dp else 1.dp, if (isSel) goldPrimary else cardBorder, CircleShape)
                                    .clickable { newVarColorHex = hex }
                            )
                        }
                    }

                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        OutlinedTextField(
                            value = newVarColorHex,
                            onValueChange = { newVarColorHex = it.take(10) },
                            label = { Text("Color Hex") },
                            placeholder = { Text("#141518") },
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )

                        OutlinedTextField(
                            value = newVarPrice,
                            onValueChange = { v -> newVarPrice = v.filter { ch -> ch.isDigit() || ch == '.' }.take(10) },
                            label = { Text("Price Override") },
                            placeholder = { Text(primaryProduct.price.toInt().toString()) },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )
                    }

                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        OutlinedTextField(
                            value = newVarSku,
                            onValueChange = { newVarSku = it.take(40) },
                            label = { Text("Variant SKU") },
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = newVarStock,
                            onValueChange = { newVarStock = it.filter(Char::isDigit).take(6) },
                            label = { Text("Stock Quantity") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        if (newVarName.isNotBlank()) {
                            val priceVal = newVarPrice.toDoubleOrNull() ?: primaryProduct.price
                            val stockVal = newVarStock.toIntOrNull() ?: 100
                            if (isEditing && editingVariant != null) {
                                val updated = primaryProduct.productVariants.map {
                                    if (it.id == editingVariant!!.id) {
                                        it.copy(
                                            name = newVarName.trim(),
                                            colorHex = newVarColorHex.trim(),
                                            price = priceVal,
                                            sku = newVarSku.trim(),
                                            stock = stockVal
                                        )
                                    } else it
                                }
                                viewModel.updateShowcaseVariants(updated)
                                viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(productVariants = updated))
                                Toast.makeText(context, "Variant updated", Toast.LENGTH_SHORT).show()
                            } else {
                                val newVariant = ProductVariantItem(
                                    id = java.util.UUID.randomUUID().toString(),
                                    name = newVarName.trim(),
                                    colorHex = newVarColorHex.trim(),
                                    price = priceVal,
                                    sku = newVarSku.trim(),
                                    stock = stockVal,
                                    description = newVarDesc.trim()
                                )
                                val updated = primaryProduct.productVariants + newVariant
                                viewModel.updateShowcaseVariants(updated)
                                viewModel.saveOrUpdateShowcaseProduct(primaryProduct.copy(productVariants = updated))
                                Toast.makeText(context, "Variant added", Toast.LENGTH_SHORT).show()
                            }
                            showAddVariantDialog = false
                            editingVariant = null
                            newVarName = ""
                            newVarPrice = ""
                            newVarSku = ""
                            newVarStock = "100"
                        } else {
                            Toast.makeText(context, "Enter variant name", Toast.LENGTH_SHORT).show()
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                ) { Text(if (isEditing) "Save Changes" else "Add Variant", fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = {
                    showAddVariantDialog = false
                    editingVariant = null
                }) { Text("Cancel") }
            }
        )
    }
}

@Composable
private fun AdvancedFieldSettingsEditor(
    field: FormFieldItem,
    allFields: List<FormFieldItem>,
    pages: List<FormPageItem>,
    isDark: Boolean,
    viewModel: AppViewModel,
    onUpdate: (FormFieldItem) -> Unit
) {
    val context = LocalContext.current
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val textPrimary = if (isDark) Color(0xFFF3F4F6) else Color(0xFF111827)
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val surfaceColor = if (isDark) Color(0xFF1F1A0E) else Color.White
    val cardBg = surfaceColor
    val goldPrimary = Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)
    var isUploadingProductImage by remember(field.id) { mutableStateOf(false) }
    var isUploadingGalleryImage by remember(field.id) { mutableStateOf(false) }
    var showAddVariantDialog by remember(field.id) { mutableStateOf(false) }
    var newVariantName by remember(field.id) { mutableStateOf("") }
    var newVariantPrice by remember(field.id) { mutableStateOf("") }
    var newVariantSku by remember(field.id) { mutableStateOf("") }
    var newVariantStock by remember(field.id) { mutableStateOf("100") }

    val productImagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            isUploadingProductImage = true
            viewModel.uploadProductImage(uri, context) { success, msg, uploadedUrl ->
                isUploadingProductImage = false
                if (success && !uploadedUrl.isNullOrBlank()) {
                    onUpdate(field.copy(mediaUrl = uploadedUrl))
                    Toast.makeText(context, "Product photo attached", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(context, msg.ifBlank { "Upload failed" }, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    val galleryImagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            isUploadingGalleryImage = true
            viewModel.uploadProductImage(uri, context) { success, msg, uploadedUrl ->
                isUploadingGalleryImage = false
                if (success && !uploadedUrl.isNullOrBlank()) {
                    val updated = (field.galleryUrls + uploadedUrl).distinct()
                    onUpdate(field.copy(galleryUrls = updated))
                    Toast.makeText(context, "Gallery photo added", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(context, msg.ifBlank { "Upload failed" }, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    var pageMenuExpanded by remember(field.id) { mutableStateOf(false) }
    var dependencyMenuExpanded by remember(field.id) { mutableStateOf(false) }
    var operatorMenuExpanded by remember(field.id) { mutableStateOf(false) }
    val dependency = allFields.firstOrNull { it.id == field.dependsOnFieldId }
    val supportsResponseValue = field.type !in listOf(
        FormFieldType.CUSTOM_CODE,
        FormFieldType.MEDIA_IMAGE,
        FormFieldType.MEDIA_VIDEO,
        FormFieldType.MEDIA_PDF,
        FormFieldType.IMAGE,
        FormFieldType.VIDEO,
        FormFieldType.PDF,
        FormFieldType.PRODUCT,
        FormFieldType.PRODUCT_LIST,
        FormFieldType.DISCOUNT,
        FormFieldType.SHIPPING,
        FormFieldType.TAX,
        FormFieldType.TIP,
        FormFieldType.CURRENCY
    )

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        HorizontalDivider(color = cardBorder)
        Text("ADVANCED FIELD SETTINGS", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
        OutlinedTextField(
            value = field.label,
            onValueChange = { onUpdate(field.copy(label = it.take(120))) },
            label = { Text("Field label") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true
        )
        OutlinedTextField(
            value = field.placeholder,
            onValueChange = { onUpdate(field.copy(placeholder = it.take(300))) },
            label = { Text("Placeholder") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true
        )
        OutlinedTextField(
            value = field.helperText,
            onValueChange = { onUpdate(field.copy(helperText = it.take(500))) },
            label = { Text("Helper text") },
            modifier = Modifier.fillMaxWidth(),
            maxLines = 2
        )
        if (supportsResponseValue) {
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text("Required response", modifier = Modifier.weight(1f), fontSize = 12.sp)
                Switch(checked = field.isRequired, onCheckedChange = { onUpdate(field.copy(isRequired = it)) })
            }
        }
        if (field.type in listOf(FormFieldType.DROPDOWN, FormFieldType.RADIO, FormFieldType.MULTI_SELECT)) {
            var optionsRawText by remember(field.id) { mutableStateOf(field.options.joinToString(", ")) }
            LaunchedEffect(field.options) {
                val currentParsed = optionsRawText.split(',').map(String::trim).filter(String::isNotBlank)
                if (currentParsed != field.options) {
                    optionsRawText = field.options.joinToString(", ")
                }
            }
            OutlinedTextField(
                value = optionsRawText,
                onValueChange = { value ->
                    optionsRawText = value
                    val parsed = value.split(',').map(String::trim).filter(String::isNotBlank).take(100)
                    onUpdate(field.copy(options = parsed))
                },
                label = { Text("Options (comma separated)") },
                modifier = Modifier.fillMaxWidth(),
                maxLines = 3
            )
        }
        if (field.type == FormFieldType.COUPON) {
            var couponRawText by remember(field.id) { mutableStateOf(field.options.joinToString(", ")) }
            LaunchedEffect(field.options) {
                val currentParsed = couponRawText.split(',').map(String::trim).filter(String::isNotBlank)
                if (currentParsed != field.options) {
                    couponRawText = field.options.joinToString(", ")
                }
            }
            Card(
                colors = CardDefaults.cardColors(containerColor = cardBg),
                border = BorderStroke(1.dp, goldPrimary.copy(alpha = 0.35f)),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(
                        "🎟️ Configured Promo Codes",
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = textPrimary
                    )
                    Text(
                        "Format: CODE:DISCOUNT (e.g. SAVE20:20% for 20% off, FLAT100:100 for ৳100 off). Separate multiple codes with commas.",
                        fontSize = 11.sp,
                        color = textSecondary,
                        lineHeight = 15.sp
                    )
                    OutlinedTextField(
                        value = couponRawText,
                        onValueChange = { value ->
                            couponRawText = value
                            val parsed = value.split(',').map(String::trim).filter(String::isNotBlank).take(50)
                            onUpdate(field.copy(options = parsed))
                        },
                        label = { Text("Coupon Codes (comma separated)") },
                        placeholder = { Text("e.g. SAVE10:10%, FLAT50:50, SWAPNO20:20%") },
                        modifier = Modifier.fillMaxWidth(),
                        maxLines = 3
                    )
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        listOf("SAVE10:10%", "SAVE20:20%", "FLAT50:50", "FLAT100:100").forEach { preset ->
                            Surface(
                                onClick = {
                                    val currentList = field.options.toMutableList()
                                    if (!currentList.contains(preset)) {
                                        currentList.add(preset)
                                        couponRawText = currentList.joinToString(", ")
                                        onUpdate(field.copy(options = currentList))
                                    }
                                },
                                shape = RoundedCornerShape(16.dp),
                                color = cardBg,
                                border = BorderStroke(1.dp, cardBorder)
                            ) {
                                Text(
                                    "+ $preset",
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                                    fontSize = 11.sp,
                                    color = textPrimary
                                )
                            }
                        }
                    }
                }
            }
        }
        if (field.type in listOf(FormFieldType.PRODUCT, FormFieldType.PRODUCT_LIST)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                OutlinedTextField(
                    value = field.minValue?.toString().orEmpty(),
                    onValueChange = { value -> onUpdate(field.copy(minValue = value.filter { it.isDigit() || it in ".-" }.toDoubleOrNull())) },
                    label = { Text("Price (BDT)") },
                    modifier = Modifier.weight(1f),
                    singleLine = true
                )
                OutlinedTextField(
                    value = field.defaultValue,
                    onValueChange = { onUpdate(field.copy(defaultValue = it.take(50))) },
                    label = { Text("Product SKU") },
                    modifier = Modifier.weight(1f),
                    singleLine = true
                )
            }

            // Dedicated Product Image Upload Card
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (isDark) Color(0xFF16120B) else Color(0xFFF8FAFC))
                    .border(1.dp, cardBorder, RoundedCornerShape(12.dp))
                    .padding(12.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Text(
                    text = "PRODUCT PHOTO",
                    fontSize = 10.5.sp,
                    fontWeight = FontWeight.Bold,
                    color = textSecondary
                )

                if (field.mediaUrl.isNotBlank()) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(140.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(if (isDark) Color(0xFF100D07) else Color(0xFFFFFFFF)),
                        contentAlignment = Alignment.Center
                    ) {
                        AsyncImage(
                            model = field.mediaUrl,
                            contentDescription = "Product Image",
                            modifier = Modifier.fillMaxSize(),
                            contentScale = ContentScale.Fit
                        )
                        IconButton(
                            onClick = { onUpdate(field.copy(mediaUrl = "")) },
                            modifier = Modifier
                                .align(Alignment.TopEnd)
                                .padding(6.dp)
                                .size(28.dp)
                                .background(Color.Black.copy(alpha = 0.65f), CircleShape)
                        ) {
                            Icon(Icons.Default.Close, contentDescription = "Remove Photo", tint = Color.White, modifier = Modifier.size(16.dp))
                        }
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedButton(
                            onClick = { productImagePicker.launch("image/*") },
                            enabled = !isUploadingProductImage,
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(8.dp),
                            contentPadding = PaddingValues(vertical = 6.dp)
                        ) {
                            Icon(Icons.Outlined.PhotoCamera, contentDescription = null, modifier = Modifier.size(16.dp), tint = goldText)
                            Spacer(Modifier.width(6.dp))
                            Text("Change Photo", fontSize = 12.sp, color = textPrimary)
                        }

                        OutlinedButton(
                            onClick = { onUpdate(field.copy(mediaUrl = "")) },
                            enabled = !isUploadingProductImage,
                            shape = RoundedCornerShape(8.dp),
                            contentPadding = PaddingValues(vertical = 6.dp)
                        ) {
                            Icon(Icons.Outlined.DeleteOutline, contentDescription = null, modifier = Modifier.size(16.dp), tint = Color(0xFFEF4444))
                            Spacer(Modifier.width(4.dp))
                            Text("Remove", fontSize = 12.sp, color = Color(0xFFEF4444))
                        }
                    }
                } else if (isUploadingProductImage) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(100.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        CircularProgressIndicator(
                            color = goldPrimary,
                            modifier = Modifier.size(28.dp),
                            strokeWidth = 2.5.dp
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "Uploading product photo...",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = textSecondary
                        )
                    }
                } else {
                    Surface(
                        onClick = { productImagePicker.launch("image/*") },
                        shape = RoundedCornerShape(10.dp),
                        color = if (isDark) Color(0xFF221A0C) else Color(0xFFFEFDF5),
                        border = BorderStroke(1.dp, goldPrimary.copy(alpha = 0.5f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Row(
                            modifier = Modifier.padding(14.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(40.dp)
                                    .clip(CircleShape)
                                    .background(goldPrimary.copy(alpha = 0.15f)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    Icons.Outlined.AddPhotoAlternate,
                                    contentDescription = null,
                                    tint = goldText,
                                    modifier = Modifier.size(22.dp)
                                )
                            }
                            Column(modifier = Modifier.weight(1f)) {
                                Text(
                                    "Upload Product Image",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = textPrimary
                                )
                                Text(
                                    "Tap to choose photo from camera or gallery",
                                    fontSize = 11.5.sp,
                                    color = textSecondary
                                )
                            }
                        }
                    }
                }
            }

            // Product Multi-Image Gallery
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (isDark) Color(0xFF16120B) else Color(0xFFF8FAFC))
                    .border(1.dp, cardBorder, RoundedCornerShape(12.dp))
                    .padding(12.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "PRODUCT GALLERY (${field.galleryUrls.size})",
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = textSecondary
                    )

                    Surface(
                        onClick = { galleryImagePicker.launch("image/*") },
                        shape = RoundedCornerShape(8.dp),
                        color = if (isDark) Color(0xFF221A0C) else Color(0xFFFEFDF5),
                        border = BorderStroke(1.dp, goldPrimary)
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(Icons.Outlined.AddPhotoAlternate, contentDescription = null, tint = goldText, modifier = Modifier.size(13.dp))
                            Text("+ Add Photo", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = goldText)
                        }
                    }
                }

                if (isUploadingGalleryImage) {
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = goldPrimary)
                        Spacer(Modifier.width(8.dp))
                        Text("Uploading gallery photo...", fontSize = 11.5.sp, color = textSecondary)
                    }
                }

                if (field.galleryUrls.isNotEmpty()) {
                    LazyRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        items(field.galleryUrls) { gUrl ->
                            Box(
                                modifier = Modifier
                                    .size(72.dp)
                                    .clip(RoundedCornerShape(8.dp))
                                    .border(1.dp, cardBorder, RoundedCornerShape(8.dp))
                                    .background(if (isDark) Color(0xFF100D07) else Color.White)
                            ) {
                                AsyncImage(
                                    model = gUrl,
                                    contentDescription = "Gallery Thumbnail",
                                    modifier = Modifier.fillMaxSize(),
                                    contentScale = ContentScale.Crop
                                )
                                IconButton(
                                    onClick = {
                                        onUpdate(field.copy(galleryUrls = field.galleryUrls.filterNot { it == gUrl }))
                                    },
                                    modifier = Modifier
                                        .align(Alignment.TopEnd)
                                        .size(20.dp)
                                        .background(Color.Black.copy(alpha = 0.65f), CircleShape)
                                ) {
                                    Icon(Icons.Default.Close, contentDescription = "Remove", tint = Color.White, modifier = Modifier.size(12.dp))
                                }
                            }
                        }
                    }
                } else {
                    Text(
                        "No additional gallery photos yet. Tap '+ Add Photo' to upload multiple photos.",
                        fontSize = 11.sp,
                        color = textSecondary
                    )
                }
            }

            // Product Variants Section
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (isDark) Color(0xFF16120B) else Color(0xFFF8FAFC))
                    .border(1.dp, cardBorder, RoundedCornerShape(12.dp))
                    .padding(12.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "PRODUCT VARIANTS (${field.productVariants.size})",
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = textSecondary
                    )

                    Surface(
                        onClick = { showAddVariantDialog = true },
                        shape = RoundedCornerShape(8.dp),
                        color = Color(0xFF10B981).copy(alpha = 0.12f),
                        border = BorderStroke(1.dp, Color(0xFF10B981))
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(Icons.Default.Add, contentDescription = null, tint = Color(0xFF10B981), modifier = Modifier.size(13.dp))
                            Text("+ Add Variant", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                        }
                    }
                }

                if (field.productVariants.isNotEmpty()) {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        field.productVariants.forEach { pv ->
                            Card(
                                shape = RoundedCornerShape(8.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1A1610) else Color.White),
                                border = BorderStroke(1.dp, cardBorder),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(pv.name, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                        Text(
                                            "BDT ${"%,.2f".format(pv.price)}${if (pv.sku.isNotBlank()) " | SKU: ${pv.sku}" else ""}${if (pv.stock > 0) " | Stock: ${pv.stock}" else ""}",
                                            fontSize = 11.sp,
                                            color = textSecondary
                                        )
                                    }
                                    IconButton(
                                        onClick = {
                                            onUpdate(field.copy(productVariants = field.productVariants.filterNot { it.id == pv.id }))
                                        },
                                        modifier = Modifier.size(24.dp)
                                    ) {
                                        Icon(Icons.Default.Close, contentDescription = "Remove Variant", tint = Color(0xFFEF4444), modifier = Modifier.size(14.dp))
                                    }
                                }
                            }
                        }
                    }
                } else {
                    Text(
                        "No variants configured. Tap '+ Add Variant' to add size, color, or spec options with different prices.",
                        fontSize = 11.sp,
                        color = textSecondary
                    )
                }
            }

            if (showAddVariantDialog) {
                AlertDialog(
                    onDismissRequest = { showAddVariantDialog = false },
                    title = { Text("Add Product Variant", fontWeight = FontWeight.Bold, color = textPrimary) },
                    text = {
                        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            OutlinedTextField(
                                value = newVariantName,
                                onValueChange = { newVariantName = it.take(60) },
                                label = { Text("Variant Name (e.g. XL / Black)") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )
                            OutlinedTextField(
                                value = newVariantPrice,
                                onValueChange = { newVariantPrice = it.filter { ch -> ch.isDigit() || ch == '.' }.take(10) },
                                label = { Text("Variant Price (BDT)") },
                                placeholder = { Text((field.minValue ?: field.defaultValue.toDoubleOrNull() ?: 0.0).toString()) },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )
                            OutlinedTextField(
                                value = newVariantSku,
                                onValueChange = { newVariantSku = it.take(40) },
                                label = { Text("SKU / Code (Optional)") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )
                            OutlinedTextField(
                                value = newVariantStock,
                                onValueChange = { newVariantStock = it.filter(Char::isDigit).take(6) },
                                label = { Text("Stock Quantity") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )
                        }
                    },
                    confirmButton = {
                        Button(
                            onClick = {
                                val defaultBase = field.minValue ?: field.defaultValue.toDoubleOrNull() ?: 0.0
                                val parsedPrice = newVariantPrice.toDoubleOrNull() ?: defaultBase
                                val parsedStock = newVariantStock.toIntOrNull() ?: 100
                                val variant = ProductVariantItem(
                                    id = "var_${System.currentTimeMillis()}",
                                    name = newVariantName.ifBlank { "Variant ${field.productVariants.size + 1}" },
                                    price = parsedPrice,
                                    sku = newVariantSku.trim(),
                                    stock = parsedStock
                                )
                                onUpdate(field.copy(productVariants = field.productVariants + variant))
                                newVariantName = ""
                                newVariantPrice = ""
                                newVariantSku = ""
                                newVariantStock = "100"
                                showAddVariantDialog = false
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981))
                        ) {
                            Text("Add Variant", color = Color.White, fontWeight = FontWeight.Bold)
                        }
                    },
                    dismissButton = {
                        TextButton(onClick = { showAddVariantDialog = false }) {
                            Text("Cancel", color = textSecondary)
                        }
                    }
                )
            }

            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text("Required Selection", modifier = Modifier.weight(1f), fontSize = 12.sp)
                Switch(checked = field.isRequired, onCheckedChange = { onUpdate(field.copy(isRequired = it)) })
            }
        }
        if (supportsResponseValue) {
            OutlinedTextField(
                value = field.defaultValue,
                onValueChange = { onUpdate(field.copy(defaultValue = it.take(10_000))) },
                label = { Text("Default value") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true
            )
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                OutlinedTextField(
                    value = field.minLength.takeIf { it > 0 }?.toString().orEmpty(),
                    onValueChange = { onUpdate(field.copy(minLength = it.filter(Char::isDigit).take(5).toIntOrNull()?.coerceIn(0, 10_000) ?: 0)) },
                    label = { Text("Min length") },
                    modifier = Modifier.weight(1f),
                    singleLine = true
                )
                OutlinedTextField(
                    value = field.maxLength.takeIf { it > 0 }?.toString().orEmpty(),
                    onValueChange = { onUpdate(field.copy(maxLength = it.filter(Char::isDigit).take(5).toIntOrNull()?.coerceIn(0, 10_000) ?: 0)) },
                    label = { Text("Max length") },
                    modifier = Modifier.weight(1f),
                    singleLine = true
                )
            }
        }
        if (field.type == FormFieldType.QUANTITY) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                OutlinedTextField(
                    value = field.minValue?.toString().orEmpty(),
                    onValueChange = { value -> onUpdate(field.copy(minValue = value.filter { it.isDigit() || it in ".-" }.toDoubleOrNull())) },
                    label = { Text("Minimum") },
                    modifier = Modifier.weight(1f),
                    singleLine = true
                )
                OutlinedTextField(
                    value = field.maxValue?.toString().orEmpty(),
                    onValueChange = { value -> onUpdate(field.copy(maxValue = value.filter { it.isDigit() || it in ".-" }.toDoubleOrNull())) },
                    label = { Text("Maximum") },
                    modifier = Modifier.weight(1f),
                    singleLine = true
                )
            }
        }
        if (field.type == FormFieldType.CUSTOM_AMOUNT) {
            Card(
                colors = CardDefaults.cardColors(containerColor = cardBg),
                border = BorderStroke(1.dp, goldPrimary.copy(alpha = 0.35f)),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text("💰 Amount & Pre-Price Settings", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                            Text("Configure fixed payable pre-price or customer entered amount", fontSize = 11.sp, color = textSecondary)
                        }
                    }

                    // Lock Fixed Price Toggle
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                "🔒 Lock as Fixed Price (নির্ধারিত মূল্য)",
                                fontSize = 12.5.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = textPrimary
                            )
                            Text(
                                if (field.isFixedPrice) "Customer cannot edit or alter this price. Charged as exact fixed amount."
                                else "Customer can enter custom amount (guided by Min/Max limits).",
                                fontSize = 11.sp,
                                color = textSecondary
                            )
                        }
                        Switch(
                            checked = field.isFixedPrice,
                            onCheckedChange = { locked ->
                                val currentDef = if (field.defaultValue.isBlank()) "500.00" else field.defaultValue
                                onUpdate(field.copy(
                                    isFixedPrice = locked,
                                    defaultValue = currentDef,
                                    minValue = currentDef.toDoubleOrNull() ?: field.minValue ?: 1.0
                                ))
                            }
                        )
                    }

                    // Fixed Price / Preset Amount Input
                    OutlinedTextField(
                        value = field.defaultValue,
                        onValueChange = { newVal ->
                            val clean = newVal.filter { it.isDigit() || it in ".-" }
                            val num = clean.toDoubleOrNull()
                            onUpdate(field.copy(
                                defaultValue = clean,
                                minValue = if (field.isFixedPrice) (num ?: field.minValue) else field.minValue
                            ))
                        },
                        label = { Text(if (field.isFixedPrice) "Fixed Pre-Price (BDT / টাকা)" else "Preset Default Amount (BDT)") },
                        leadingIcon = { Text("৳", fontWeight = FontWeight.Bold, color = goldPrimary, modifier = Modifier.padding(start = 12.dp)) },
                        placeholder = { Text("e.g. 500.00") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        shape = RoundedCornerShape(10.dp)
                    )

                    // Quick Preset Chips (৳100, ৳500, ৳1000, ৳2500, ৳5000)
                    Text("Quick Price Presets:", fontSize = 11.sp, fontWeight = FontWeight.Medium, color = textSecondary)
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        listOf("100.00", "500.00", "1000.00", "2500.00", "5000.00").forEach { preset ->
                            val isSelected = field.defaultValue == preset
                            Surface(
                                onClick = {
                                    val num = preset.toDoubleOrNull()
                                    onUpdate(field.copy(
                                        defaultValue = preset,
                                        minValue = if (field.isFixedPrice) (num ?: field.minValue) else field.minValue
                                    ))
                                },
                                shape = RoundedCornerShape(8.dp),
                                color = if (isSelected) goldPrimary.copy(alpha = 0.2f) else if (isDark) Color(0xFF262014) else Color(0xFFF1F5F9),
                                border = BorderStroke(1.dp, if (isSelected) goldPrimary else cardBorder)
                            ) {
                                Text(
                                    "৳${preset.substringBefore('.')}",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (isSelected) goldText else textPrimary,
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp)
                                )
                            }
                        }
                    }

                    // If not locked, show Min/Max bounds
                    if (!field.isFixedPrice) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                            OutlinedTextField(
                                value = field.minValue?.toString().orEmpty(),
                                onValueChange = { value -> onUpdate(field.copy(minValue = value.filter { it.isDigit() || it in ".-" }.toDoubleOrNull())) },
                                label = { Text("Minimum Amount") },
                                modifier = Modifier.weight(1f),
                                singleLine = true,
                                shape = RoundedCornerShape(10.dp)
                            )
                            OutlinedTextField(
                                value = field.maxValue?.toString().orEmpty(),
                                onValueChange = { value -> onUpdate(field.copy(maxValue = value.filter { it.isDigit() || it in ".-" }.toDoubleOrNull())) },
                                label = { Text("Maximum Amount") },
                                modifier = Modifier.weight(1f),
                                singleLine = true,
                                shape = RoundedCornerShape(10.dp)
                            )
                        }
                    }

                    // Promo Code Communication Notice
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = Color(0xFF3B82F6).copy(alpha = 0.1f),
                        border = BorderStroke(1.dp, Color(0xFF3B82F6).copy(alpha = 0.3f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Row(
                            modifier = Modifier.padding(8.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("🎟️", fontSize = 14.sp)
                            Text(
                                "Promo Code Compatible: Customer promo codes & coupons apply directly against this price, calculating percentage or flat discounts in real-time.",
                                fontSize = 11.sp,
                                color = if (isDark) Color(0xFF93C5FD) else Color(0xFF1D4ED8),
                                lineHeight = 14.sp
                            )
                        }
                    }
                }
            }
        }
        if (field.type in listOf(FormFieldType.FILE_UPLOAD, FormFieldType.CAMERA_UPLOAD)) {
            OutlinedTextField(
                value = field.allowedFileExtensions.joinToString(", "),
                onValueChange = { value ->
                    val extensions = value.split(',').map { it.trim().removePrefix(".").lowercase() }
                        .filter { it.matches(Regex("^[a-z0-9]{1,12}$")) }.distinct().take(30)
                    onUpdate(field.copy(allowedFileExtensions = extensions))
                },
                label = { Text("Allowed extensions") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true
            )
            OutlinedTextField(
                value = (field.maxFileSizeBytes / (1024L * 1024L)).coerceAtLeast(1L).toString(),
                onValueChange = { value ->
                    val megabytes = value.filter(Char::isDigit).take(2).toLongOrNull()?.coerceIn(1L, 10L) ?: 1L
                    onUpdate(field.copy(maxFileSizeBytes = megabytes * 1024L * 1024L))
                },
                label = { Text("Maximum file size (MB)") },
                supportingText = { Text("Private hosted uploads are capped at 10 MB.") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true
            )
        }
        if (supportsResponseValue) {
            OutlinedTextField(
                value = field.validationRegex,
                onValueChange = { onUpdate(field.copy(validationRegex = it.take(256))) },
                label = { Text("Validation pattern (regex)") },
                supportingText = { Text("Optional full-value pattern; unsafe expressions are blocked at publish time.") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true
            )
            OutlinedTextField(
                value = field.customErrorMessage,
                onValueChange = { onUpdate(field.copy(customErrorMessage = it.take(300))) },
                label = { Text("Custom validation error") },
                modifier = Modifier.fillMaxWidth(),
                maxLines = 2
            )
        }
        if (pages.isNotEmpty()) {
            Box {
                Surface(
                    onClick = { pageMenuExpanded = true },
                    color = surfaceColor,
                    border = BorderStroke(1.dp, cardBorder),
                    shape = RoundedCornerShape(10.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text("Page: ${pages.getOrNull(field.pageIndex)?.title ?: "Page 1"}", modifier = Modifier.padding(12.dp), fontSize = 12.sp)
                }
                DropdownMenu(expanded = pageMenuExpanded, onDismissRequest = { pageMenuExpanded = false }) {
                    pages.forEachIndexed { index, page ->
                        DropdownMenuItem(text = { Text(page.title.ifBlank { "Page ${index + 1}" }) }, onClick = {
                            onUpdate(field.copy(pageIndex = index)); pageMenuExpanded = false
                        })
                    }
                }
            }
        }
        Box {
            Surface(
                onClick = { dependencyMenuExpanded = true },
                color = surfaceColor,
                border = BorderStroke(1.dp, cardBorder),
                shape = RoundedCornerShape(10.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Text("Show when: ${dependency?.label ?: "Always"}", modifier = Modifier.padding(12.dp), fontSize = 12.sp)
            }
            DropdownMenu(expanded = dependencyMenuExpanded, onDismissRequest = { dependencyMenuExpanded = false }) {
                DropdownMenuItem(text = { Text("Always") }, onClick = {
                    onUpdate(field.copy(dependsOnFieldId = null, conditionValue = "")); dependencyMenuExpanded = false
                })
                allFields.filter { it.id != field.id && it.type != FormFieldType.CUSTOM_CODE }.forEach { candidate ->
                    DropdownMenuItem(text = { Text(candidate.label.ifBlank { candidate.type.displayName }) }, onClick = {
                        onUpdate(field.copy(dependsOnFieldId = candidate.id)); dependencyMenuExpanded = false
                    })
                }
            }
        }
        if (field.dependsOnFieldId != null) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                Box(modifier = Modifier.weight(1f)) {
                    Surface(
                        onClick = { operatorMenuExpanded = true }, color = surfaceColor,
                        border = BorderStroke(1.dp, cardBorder), shape = RoundedCornerShape(10.dp), modifier = Modifier.fillMaxWidth()
                    ) { Text(field.conditionOperator.replace('_', ' '), modifier = Modifier.padding(12.dp), fontSize = 12.sp) }
                    DropdownMenu(expanded = operatorMenuExpanded, onDismissRequest = { operatorMenuExpanded = false }) {
                        listOf("EQUALS", "NOT_EQUALS", "CONTAINS").forEach { operator ->
                            DropdownMenuItem(text = { Text(operator.replace('_', ' ')) }, onClick = {
                                onUpdate(field.copy(conditionOperator = operator)); operatorMenuExpanded = false
                            })
                        }
                    }
                }
                OutlinedTextField(
                    value = field.conditionValue,
                    onValueChange = { onUpdate(field.copy(conditionValue = it.take(500))) },
                    label = { Text("Value") },
                    modifier = Modifier.weight(1f),
                    singleLine = true
                )
            }
        }
    }
}

// TAB 2: UNIFIED FORM SETTINGS TAB
// ═══════════════════════════════════════════════════════════════════════════
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FormSettingsTab(
    viewModel: AppViewModel,
    formTitle: String,
    onTitleChange: (String) -> Unit,
    formDescription: String,
    onDescriptionChange: (String) -> Unit
) {
    val context = LocalContext.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val themeConfig by viewModel.formThemeConfig.collectAsState()
    val formSlug by viewModel.formSlug.collectAsState()
    val formPages by viewModel.formPagesList.collectAsState()

    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0D1C2E)
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val goldPrimary = if (isDark) Color(0xFFE5A93C) else Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)

    // Form Settings state
    var shuffleQuestions by remember(themeConfig.shuffleQuestionOrder) { mutableStateOf(themeConfig.shuffleQuestionOrder) }
    var oneResponsePerUser by remember(themeConfig.oneResponsePerUser) { mutableStateOf(themeConfig.oneResponsePerUser) }
    var multiPageForm by remember(themeConfig.isMultiPageForm) { mutableStateOf(themeConfig.isMultiPageForm) }
    var requiredFieldIndicator by remember(themeConfig.requiredFieldIndicator) { mutableStateOf(themeConfig.requiredFieldIndicator) }
    var enableTimer by remember(themeConfig.enableTimer) { mutableStateOf(themeConfig.enableTimer) }
    var timerMinutes by remember(themeConfig.timerMinutes) { mutableStateOf(themeConfig.timerMinutes.toString()) }
    var closeAfterLimit by remember(themeConfig.closeAfterLimit) { mutableStateOf(themeConfig.closeAfterLimit) }
    var maxResponses by remember(themeConfig.maxResponses) { mutableStateOf(themeConfig.maxResponses.toString()) }

    var enablePayment by remember(themeConfig.enablePayment) { mutableStateOf(themeConfig.enablePayment) }
    var paymentProvider by remember(themeConfig.paymentProvider) { mutableStateOf(themeConfig.paymentProvider) }
    var currency by remember(themeConfig.currencyCode) { mutableStateOf(themeConfig.currencyCode) }
    var requirePaymentBeforeSubmit by remember(themeConfig.requirePaymentBeforeSubmit) { mutableStateOf(themeConfig.requirePaymentBeforeSubmit) }
    var taxFeesPercent by remember(themeConfig.taxPercent) { mutableStateOf(themeConfig.taxPercent.toString()) }

    // Validation & Security Settings state
    var enforceRequiredFields by remember(themeConfig.enforceRequiredFields) { mutableStateOf(themeConfig.enforceRequiredFields) }
    var strictFormatValidation by remember(themeConfig.strictFormatValidation) { mutableStateOf(themeConfig.strictFormatValidation) }
    var enforceFileSizeLimit by remember(themeConfig.enforceFileSizeLimit) { mutableStateOf(themeConfig.enforceFileSizeLimit) }
    var enforceQuantityRange by remember(themeConfig.enforceQuantityRange) { mutableStateOf(themeConfig.enforceQuantityRange) }
    var minQuantityStr by remember(themeConfig.minQuantity) { mutableStateOf(themeConfig.minQuantity.toString()) }
    var maxQuantityStr by remember(themeConfig.maxQuantity) { mutableStateOf(themeConfig.maxQuantity.toString()) }
    var enableAntiSpam by remember(themeConfig.enableAntiSpam) { mutableStateOf(themeConfig.enableAntiSpam) }
    // Browser Lockdown & Anti-Cheating state
    var enableBrowserLockdown by remember(themeConfig.enableBrowserLockdown) { mutableStateOf(themeConfig.enableBrowserLockdown) }
    var lockdownRequireFullscreen by remember(themeConfig.lockdownRequireFullscreen) { mutableStateOf(themeConfig.lockdownRequireFullscreen) }
    var lockdownBlockTabSwitch by remember(themeConfig.lockdownBlockTabSwitch) { mutableStateOf(themeConfig.lockdownBlockTabSwitch) }
    var lockdownMaxViolationsStr by remember(themeConfig.lockdownMaxViolations) { mutableStateOf(themeConfig.lockdownMaxViolations.toString()) }
    var lockdownDisableCopyPaste by remember(themeConfig.lockdownDisableCopyPaste) { mutableStateOf(themeConfig.lockdownDisableCopyPaste) }
    var lockdownWatermark by remember(themeConfig.lockdownWatermark) { mutableStateOf(themeConfig.lockdownWatermark) }

    var formWidth by remember(themeConfig.formWidthPx) { mutableFloatStateOf(themeConfig.formWidthPx.toFloat()) }
    var pageMargin by remember(themeConfig.pageMarginPx) { mutableFloatStateOf(themeConfig.pageMarginPx.toFloat()) }
    var borderRadius by remember(themeConfig.borderRadiusDp) { mutableFloatStateOf(themeConfig.borderRadiusDp.toFloat()) }


    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
        contentPadding = PaddingValues(top = 14.dp, bottom = 24.dp)
    ) {
        // 1. GENERAL SETTINGS
        item {
            SettingsCardSection(
                title = "General Settings",
                icon = Icons.Outlined.Settings,
                isDark = isDark
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    OutlinedTextField(
                        value = formTitle,
                        onValueChange = onTitleChange,
                        label = { Text("Form Name") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp)
                    )

                    OutlinedTextField(
                        value = formSlug,
                        onValueChange = viewModel::updateHostedFormSlug,
                        label = { Text("Public URL Slug") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp)
                    )

                    OutlinedTextField(
                        value = formDescription,
                        onValueChange = onDescriptionChange,
                        label = { Text("Description") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        minLines = 2
                    )

                    // Toggles Row
                    var showHeaderToggle by remember(themeConfig.showHeader) { mutableStateOf(themeConfig.showHeader) }
                    SettingsToggleRow("Show Form Header (হেডার প্রদর্শন)", showHeaderToggle) {
                        showHeaderToggle = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(showHeader = it))
                    }
                    SettingsToggleRow("Shuffle Question Order", shuffleQuestions) {
                        shuffleQuestions = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(shuffleQuestionOrder = it))
                    }
                    SettingsToggleRow("One Response Per User", oneResponsePerUser) {
                        oneResponsePerUser = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(oneResponsePerUser = it))
                    }
                }
            }
        }

        // 2. APPEARANCE SETTINGS
        item {
            SettingsCardSection(
                title = "Appearance",
                icon = Icons.Outlined.Palette,
                isDark = isDark
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                    Text("BACKGROUND STYLE", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)

                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf("Solid", "Gradient", "Custom CSS").forEach { style ->
                            val persistedStyle = style.uppercase().replace(" ", "_")
                            val isSel = themeConfig.backgroundStyle == persistedStyle
                            Surface(
                                onClick = { viewModel.updateFormThemeConfig(themeConfig.copy(backgroundStyle = persistedStyle)) },
                                shape = RoundedCornerShape(10.dp),
                                color = if (isSel) Color(0xFF281E0A) else cardBg,
                                border = BorderStroke(1.dp, if (isSel) goldPrimary else cardBorder),
                                modifier = Modifier.weight(1f)
                            ) {
                                Text(
                                    text = style,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (isSel) goldText else textPrimary,
                                    textAlign = TextAlign.Center,
                                    modifier = Modifier.padding(vertical = 10.dp)
                                )
                            }
                        }
                    }

                    Text("FONT FAMILY (টাইপোগ্রাফি)", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        items(listOf("Inter", "Plus Jakarta Sans", "Outfit", "Poppins", "Roboto", "Playfair Display", "Monospace", "System")) { font ->
                            val selected = themeConfig.fontFamily.equals(font, ignoreCase = true)
                            Surface(
                                onClick = { viewModel.updateFormThemeConfig(themeConfig.copy(fontFamily = font)) },
                                shape = RoundedCornerShape(9.dp),
                                color = if (selected) (if (isDark) Color(0xFF281E0A) else Color(0xFFFFFBEB)) else cardBg,
                                border = BorderStroke(1.dp, if (selected) goldPrimary else cardBorder)
                            ) {
                                Text(
                                    font,
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                                    textAlign = TextAlign.Center,
                                    fontSize = 11.sp,
                                    fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal,
                                    color = if (selected) goldText else textPrimary
                                )
                            }
                        }
                    }

                    SettingsToggleRow("Dark Mode (ডার্ক থিম)", themeConfig.isDarkMode) { isDarkSel ->
                        viewModel.updateFormThemeConfig(themeConfig.copy(isDarkMode = isDarkSel))
                    }

                    Text("BUTTON SHAPE", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        listOf("ROUNDED", "PILL", "SQUARE").forEach { shape ->
                            val selected = themeConfig.buttonShape == shape
                            Surface(
                                onClick = { viewModel.updateFormThemeConfig(themeConfig.copy(buttonShape = shape)) },
                                shape = RoundedCornerShape(if (shape == "PILL") 20.dp else if (shape == "SQUARE") 0.dp else 9.dp),
                                color = if (selected) Color(0xFF281E0A) else cardBg,
                                border = BorderStroke(1.dp, if (selected) goldPrimary else cardBorder),
                                modifier = Modifier.weight(1f)
                            ) {
                                Text(shape.lowercase().replaceFirstChar { it.uppercase() }, modifier = Modifier.padding(vertical = 8.dp), textAlign = TextAlign.Center, fontSize = 11.sp)
                            }
                        }
                    }

                    Text("PRIMARY THEME PALETTE", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                    LazyRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        val paletteSwatches = listOf(
                            "#0D0F12" to "Obsidian",
                            "#15803D" to "Emerald",
                            "#2563EB" to "Royal Blue",
                            "#4F46E5" to "Indigo",
                            "#E5A93C" to "Gold",
                            "#7C3AED" to "Purple",
                            "#F43F5E" to "Rose",
                            "#0EA5E9" to "Sky",
                            "#334155" to "Slate"
                        )
                        items(paletteSwatches) { (hex, name) ->
                            val swatchColor = runCatching { Color(android.graphics.Color.parseColor(hex)) }.getOrDefault(Color(0xFFE5A93C))
                            val isSelected = themeConfig.primaryColorHex.equals(hex, ignoreCase = true)
                            Box(
                                modifier = Modifier
                                    .size(38.dp)
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(swatchColor)
                                    .border(
                                        width = if (isSelected) 2.5.dp else 1.dp,
                                        color = if (isSelected) (if (isDark) Color.White else Color.Black) else Color.Transparent,
                                        shape = RoundedCornerShape(8.dp)
                                    )
                                    .clickable {
                                        viewModel.updateFormThemeConfig(themeConfig.copy(primaryColorHex = hex))
                                    },
                                contentAlignment = Alignment.Center
                            ) {
                                if (isSelected) {
                                    Icon(
                                        Icons.Default.Check,
                                        contentDescription = name,
                                        tint = Color.White,
                                        modifier = Modifier.size(16.dp)
                                    )
                                }
                            }
                        }
                    }

                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        OutlinedTextField(
                            value = themeConfig.primaryColorHex,
                            onValueChange = { value -> viewModel.updateFormThemeConfig(themeConfig.copy(primaryColorHex = value.take(7))) },
                            label = { Text("Primary Color") },
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = themeConfig.backgroundColorHex,
                            onValueChange = { value -> viewModel.updateFormThemeConfig(themeConfig.copy(backgroundColorHex = value.take(7))) },
                            label = { Text("Background") },
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )
                    }

                    // Gradient Colors Bar Preview
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(42.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(
                                Brush.horizontalGradient(
                                    listOf(
                                        runCatching { Color(android.graphics.Color.parseColor(themeConfig.gradientColorStart)) }.getOrDefault(Color(0xFF5B7FFF)),
                                        runCatching { Color(android.graphics.Color.parseColor(themeConfig.gradientColorEnd)) }.getOrDefault(Color(0xFF7C4DFF))
                                    )
                                )
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            "${themeConfig.gradientColorStart} → ${themeConfig.gradientColorEnd}",
                            fontWeight = FontWeight.Bold,
                            color = Color.Black,
                            fontSize = 12.sp
                        )
                    }

                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        OutlinedTextField(
                            value = themeConfig.gradientColorStart,
                            onValueChange = { value -> viewModel.updateFormThemeConfig(themeConfig.copy(gradientColorStart = value.take(7))) },
                            label = { Text("Gradient Start") },
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = themeConfig.gradientColorEnd,
                            onValueChange = { value -> viewModel.updateFormThemeConfig(themeConfig.copy(gradientColorEnd = value.take(7))) },
                            label = { Text("Gradient End") },
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )
                    }

                    // Sliders
                    Column {
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Form Width", fontSize = 12.sp, color = textSecondary)
                            Text("${formWidth.toInt()}px", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = goldText)
                        }
                        Slider(
                            value = formWidth,
                            onValueChange = {
                                formWidth = it
                                viewModel.updateFormThemeConfig(themeConfig.copy(formWidthPx = it.toInt()))
                            },
                            valueRange = 320f..1200f,
                            colors = SliderDefaults.colors(thumbColor = goldPrimary, activeTrackColor = goldPrimary)
                        )
                    }

                    Column {
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Page Margin", fontSize = 12.sp, color = textSecondary)
                            Text("${pageMargin.toInt()}px", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = goldText)
                        }
                        Slider(
                            value = pageMargin,
                            onValueChange = {
                                pageMargin = it
                                viewModel.updateFormThemeConfig(themeConfig.copy(pageMarginPx = it.toInt()))
                            },
                            valueRange = 8f..64f,
                            colors = SliderDefaults.colors(thumbColor = goldPrimary, activeTrackColor = goldPrimary)
                        )
                    }

                    Column {
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Border Radius", fontSize = 12.sp, color = textSecondary)
                            Text("${borderRadius.toInt()}px", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = goldText)
                        }
                        Slider(
                            value = borderRadius,
                            onValueChange = {
                                borderRadius = it
                                viewModel.updateFormThemeConfig(themeConfig.copy(borderRadiusDp = it.toInt()))
                            },
                            valueRange = 0f..40f,
                            colors = SliderDefaults.colors(thumbColor = goldPrimary, activeTrackColor = goldPrimary)
                        )
                    }
                }
            }
        }



        // 3. FORM LOGIC SETTINGS
        item {
            SettingsCardSection(
                title = "Form Logic & Limiters",
                icon = Icons.Outlined.Description,
                isDark = isDark
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    SettingsToggleRow("Multi Page Form", multiPageForm) {
                        multiPageForm = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(isMultiPageForm = it))
                    }
                    if (multiPageForm) {
                        Text("FORM PAGES", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                        formPages.forEachIndexed { index, page ->
                            Card(
                                colors = CardDefaults.cardColors(containerColor = cardBg),
                                border = BorderStroke(1.dp, cardBorder),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Text("Page ${index + 1}", fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                                        if (formPages.size > 1) {
                                            IconButton(onClick = { viewModel.removeFormPage(index) }, modifier = Modifier.size(32.dp)) {
                                                Icon(Icons.Outlined.Delete, contentDescription = "Remove page", tint = Color(0xFFEF4444), modifier = Modifier.size(17.dp))
                                            }
                                        }
                                    }
                                    OutlinedTextField(
                                        value = page.title,
                                        onValueChange = { value -> viewModel.updateFormPage(index, value, page.subtitle) },
                                        label = { Text("Page title") },
                                        modifier = Modifier.fillMaxWidth(),
                                        singleLine = true
                                    )
                                    OutlinedTextField(
                                        value = page.subtitle,
                                        onValueChange = { value -> viewModel.updateFormPage(index, page.title, value) },
                                        label = { Text("Page subtitle") },
                                        modifier = Modifier.fillMaxWidth(),
                                        maxLines = 2
                                    )
                                }
                            }
                        }
                        OutlinedButton(onClick = { viewModel.addFormPage() }, modifier = Modifier.fillMaxWidth()) {
                            Icon(Icons.Outlined.Add, null, modifier = Modifier.size(17.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Add Page")
                        }
                        Text("PROGRESS TRACKER", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                            listOf("BAR", "NUMBER", "HIDE").forEach { style ->
                                val selected = themeConfig.progressTrackerStyle == style
                                Surface(
                                    onClick = { viewModel.updateFormThemeConfig(themeConfig.copy(progressTrackerStyle = style)) },
                                    shape = RoundedCornerShape(9.dp),
                                    color = if (selected) Color(0xFF281E0A) else cardBg,
                                    border = BorderStroke(1.dp, if (selected) goldPrimary else cardBorder),
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Text(style.lowercase().replaceFirstChar { it.uppercase() }, modifier = Modifier.padding(vertical = 8.dp), textAlign = TextAlign.Center, fontSize = 11.sp)
                                }
                            }
                        }
                    }
                    SettingsToggleRow("Required Field Indicator", requiredFieldIndicator) {
                        requiredFieldIndicator = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(requiredFieldIndicator = it))
                    }
                    SettingsToggleRow("Enable Timer", enableTimer) {
                        enableTimer = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enableTimer = it))
                    }

                    if (enableTimer) {
                        OutlinedTextField(
                            value = timerMinutes,
                            onValueChange = {
                                timerMinutes = it.filter(Char::isDigit).take(4)
                                viewModel.updateFormThemeConfig(themeConfig.copy(timerMinutes = timerMinutes.toIntOrNull()?.coerceIn(1, 1440) ?: 30))
                            },
                            label = { Text("Timer Limit (Minutes)") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp)
                        )
                    }

                    SettingsToggleRow("Close After Limit", closeAfterLimit) {
                        closeAfterLimit = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(closeAfterLimit = it))
                    }

                    if (closeAfterLimit) {
                        OutlinedTextField(
                            value = maxResponses,
                            onValueChange = {
                                maxResponses = it.filter(Char::isDigit).take(8)
                                viewModel.updateFormThemeConfig(themeConfig.copy(maxResponses = maxResponses.toIntOrNull()?.coerceIn(1, 10_000_000) ?: 1000))
                            },
                            label = { Text("Maximum Responses") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp)
                        )
                    }
                }
            }
        }

        // 3B. FORM CLOSING TIMELINE & EXPIRY
        item {
            SettingsCardSection(
                title = "Form Closing Timeline & Expiry (ফর্ম সমাপ্তির সময়সীমা)",
                icon = Icons.Outlined.CalendarToday,
                isDark = isDark
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(
                        text = "Set an automated deadline or expiration schedule. When the timer expires, submissions are disabled and the custom closing notice is shown.",
                        fontSize = 12.sp,
                        color = textSecondary
                    )

                    SettingsToggleRow(
                        title = "Enable Closing Timeline (স্বয়ংক্রিয় সমাপ্তি)",
                        checked = themeConfig.enableClosingTimeline
                    ) { enabled ->
                        viewModel.updateFormThemeConfig(themeConfig.copy(enableClosingTimeline = enabled))
                    }

                    if (themeConfig.enableClosingTimeline) {
                        Text("QUICK DEADLINE PRESETS (সময় নির্ধারণ করুন)", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            listOf(
                                "+1 Day" to 24,
                                "+3 Days" to 72,
                                "+7 Days" to 168,
                                "+30 Days" to 720
                            ).forEach { (label, hours) ->
                                Surface(
                                    onClick = {
                                        viewModel.setClosingTimelineHours(hours, themeConfig.showCountdownTimer, themeConfig.closedMessage)
                                        Toast.makeText(context, "Deadline set to $label from now", Toast.LENGTH_SHORT).show()
                                    },
                                    shape = RoundedCornerShape(10.dp),
                                    color = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0),
                                    border = BorderStroke(1.dp, goldPrimary),
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Text(
                                        text = label,
                                        modifier = Modifier.padding(vertical = 8.dp),
                                        textAlign = TextAlign.Center,
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = goldText
                                    )
                                }
                            }
                        }

                        if (themeConfig.closingDeadlineEpoch > 0L) {
                            Card(
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(
                                    containerColor = if (viewModel.isFormClosed()) Color(0xFFFEE2E2) else (if (isDark) Color(0xFF1E2438) else Color(0xFFEEF2FF))
                                ),
                                border = BorderStroke(1.dp, if (viewModel.isFormClosed()) Color(0xFFEF4444) else Color(0xFF6366F1).copy(alpha = 0.5f))
                            ) {
                                Row(
                                    modifier = Modifier.padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Outlined.CalendarToday,
                                        contentDescription = null,
                                        tint = if (viewModel.isFormClosed()) Color(0xFFDC2626) else Color(0xFF6366F1),
                                        modifier = Modifier.size(20.dp)
                                    )
                                    Column {
                                        Text(
                                            text = if (viewModel.isFormClosed()) "Form Status: EXPIRED (বন্ধ)" else "Closes: ${themeConfig.closingDeadlineStr}",
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 13.sp,
                                            color = if (viewModel.isFormClosed()) Color(0xFF991B1B) else textPrimary
                                        )
                                        Text(
                                            text = "Remaining: ${viewModel.getFormClosingRemainingFormatted()}",
                                            fontSize = 11.sp,
                                            color = textSecondary
                                        )
                                    }
                                }
                            }
                        }

                        SettingsToggleRow(
                            title = "Show Countdown Timer Banner (কাউন্টডাউন টাইমার)",
                            checked = themeConfig.showCountdownTimer
                        ) { showTimer ->
                            viewModel.updateFormThemeConfig(themeConfig.copy(showCountdownTimer = showTimer))
                        }

                        OutlinedTextField(
                            value = themeConfig.closedMessage,
                            onValueChange = { msg ->
                                viewModel.updateFormThemeConfig(themeConfig.copy(closedMessage = msg.take(200)))
                            },
                            label = { Text("Closed Notice Message (সমাপ্তি বার্তা)") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                            maxLines = 2
                        )
                    }
                }
            }
        }

        // 3C. CSV AS BACKEND DATABASE SECTION
        item {
            val csvFileLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
                if (uri == null) return@rememberLauncherForActivityResult
                runCatching {
                    val resolver = context.contentResolver
                    var displayName = "data.csv"
                    resolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)?.use { cursor ->
                        if (cursor.moveToFirst()) {
                            displayName = cursor.getString(cursor.getColumnIndexOrThrow(OpenableColumns.DISPLAY_NAME))
                        }
                    }
                    val stream = resolver.openInputStream(uri) ?: error("Unable to open selected file")
                    val rawText = stream.bufferedReader().use { it.readText() }
                    viewModel.importCsvBackend(displayName, rawText)
                    Toast.makeText(context, "CSV imported: $displayName", Toast.LENGTH_LONG).show()
                }.onFailure { error ->
                    Toast.makeText(context, error.message ?: "Failed to read CSV", Toast.LENGTH_LONG).show()
                }
            }

            var testLookupKey by remember { mutableStateOf("") }
            var testLookupResult by remember { mutableStateOf<Map<String, String>?>(null) }
            var showLookupColDropdown by remember { mutableStateOf(false) }

            SettingsCardSection(
                title = "CSV as Backend Database (সিএসভি ব্যাকএন্ড)",
                icon = Icons.Outlined.Storage,
                isDark = isDark
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(
                        text = "Use an uploaded CSV file as a backend database. Customers can enter an ID or key on the frontend to automatically look up and autofill their records.",
                        fontSize = 12.sp,
                        color = textSecondary
                    )

                    SettingsToggleRow(
                        title = "Enable CSV as Backend (সিএসভি ব্যাকএন্ড সক্রিয় করুন)",
                        checked = themeConfig.enableCsvBackend
                    ) { enabled ->
                        viewModel.updateFormThemeConfig(themeConfig.copy(enableCsvBackend = enabled))
                    }

                    if (themeConfig.enableCsvBackend) {
                        // Upload Button & Sample Presets
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            OutlinedButton(
                                onClick = {
                                    csvFileLauncher.launch(arrayOf("text/comma-separated-values", "text/csv", "application/csv", "text/plain"))
                                },
                                modifier = Modifier.weight(1f).height(44.dp),
                                shape = RoundedCornerShape(10.dp),
                                border = BorderStroke(1.dp, goldPrimary)
                            ) {
                                Icon(Icons.Outlined.UploadFile, null, tint = goldText, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("Upload CSV", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                            }

                            Button(
                                onClick = {
                                    viewModel.loadSampleCsvTemplate("STUDENT_PORTAL")
                                    Toast.makeText(context, "Loaded Student Portal CSV template", Toast.LENGTH_SHORT).show()
                                },
                                modifier = Modifier.weight(1f).height(44.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0), contentColor = goldText),
                                border = BorderStroke(1.dp, goldPrimary)
                            ) {
                                Text("🎓 Sample CSV", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }
                        }

                        // Quick presets chips
                        Text("TEMPLATE DATASETS:", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            listOf(
                                "🎓 Student Fees" to "STUDENT_PORTAL",
                                "📦 Inventory" to "PRODUCT_CATALOG",
                                "🎟️ Vouchers" to "VOUCHER_VERIFIER"
                            ).forEach { (label, key) ->
                                Surface(
                                    onClick = {
                                        viewModel.loadSampleCsvTemplate(key)
                                        Toast.makeText(context, "Loaded $label dataset", Toast.LENGTH_SHORT).show()
                                    },
                                    shape = RoundedCornerShape(8.dp),
                                    color = if (isDark) Color(0xFF1E2333) else Color(0xFFF1F5F9),
                                    border = BorderStroke(0.8.dp, cardBorder)
                                ) {
                                    Text(label, fontSize = 11.sp, color = textPrimary, modifier = Modifier.padding(horizontal = 8.dp, vertical = 6.dp))
                                }
                            }
                        }

                        if (themeConfig.csvRawData.isNotBlank()) {
                            val parsedRows = remember(themeConfig.csvRawData) { viewModel.getParsedCsvRows(10) }

                            Card(
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF161E2E) else Color(0xFFF0FDF4)),
                                border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.4f))
                            ) {
                                Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Icon(Icons.Outlined.CheckCircle, null, tint = Color(0xFF10B981), modifier = Modifier.size(18.dp))
                                        Text(
                                            text = "Connected: ${themeConfig.csvFileName.ifBlank { "custom_data.csv" }}",
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 12.5.sp,
                                            color = textPrimary
                                        )
                                    }
                                    Text(
                                        text = "${parsedRows.size} records loaded • ${themeConfig.csvHeaders.size} columns detected",
                                        fontSize = 11.sp,
                                        color = textSecondary
                                    )
                                }
                            }

                            // Primary Lookup Column Selector
                            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text("Primary Lookup Column (অনুসন্ধান কলাম)", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                Box {
                                    OutlinedButton(
                                        onClick = { showLookupColDropdown = true },
                                        modifier = Modifier.fillMaxWidth(),
                                        shape = RoundedCornerShape(10.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(
                                                text = themeConfig.csvLookupColumn.ifBlank { themeConfig.csvHeaders.firstOrNull() ?: "Select column" },
                                                fontWeight = FontWeight.Bold,
                                                color = textPrimary
                                            )
                                            Icon(Icons.Default.ArrowDropDown, null, tint = textSecondary)
                                        }
                                    }
                                    DropdownMenu(
                                        expanded = showLookupColDropdown,
                                        onDismissRequest = { showLookupColDropdown = false }
                                    ) {
                                        themeConfig.csvHeaders.forEach { header ->
                                            DropdownMenuItem(
                                                text = { Text(header) },
                                                onClick = {
                                                    viewModel.updateFormThemeConfig(themeConfig.copy(csvLookupColumn = header))
                                                    showLookupColDropdown = false
                                                }
                                            )
                                        }
                                    }
                                }
                            }

                            // CSV Column to Form Field Mapping Matrix
                            val formFieldsForMapping by viewModel.formFieldsList.collectAsState()
                            if (themeConfig.csvHeaders.isNotEmpty() && formFieldsForMapping.isNotEmpty()) {
                                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Text("COLUMN-TO-FIELD AUTOFILL MAPPING:", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                    themeConfig.csvHeaders.take(5).forEach { headerCol ->
                                        var colDropdownOpen by remember { mutableStateOf(false) }
                                        val mappedFieldId = themeConfig.csvColumnMappings[headerCol] ?: ""
                                        val mappedFieldName = formFieldsForMapping.firstOrNull { it.id == mappedFieldId }?.label ?: "Auto-detect by name"

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(headerCol, fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = textPrimary, modifier = Modifier.weight(1f))
                                            Box {
                                                Surface(
                                                    onClick = { colDropdownOpen = true },
                                                    shape = RoundedCornerShape(8.dp),
                                                    color = if (isDark) Color(0xFF1E2333) else Color(0xFFF1F5F9),
                                                    border = BorderStroke(0.8.dp, cardBorder)
                                                ) {
                                                    Row(
                                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 6.dp),
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                                    ) {
                                                        Text(mappedFieldName, fontSize = 11.sp, color = if (mappedFieldId.isNotBlank()) Color(0xFF10B981) else textSecondary)
                                                        Icon(Icons.Default.ArrowDropDown, null, tint = textSecondary, modifier = Modifier.size(14.dp))
                                                    }
                                                }
                                                DropdownMenu(
                                                    expanded = colDropdownOpen,
                                                    onDismissRequest = { colDropdownOpen = false }
                                                ) {
                                                    DropdownMenuItem(
                                                        text = { Text("Auto-detect by name") },
                                                        onClick = {
                                                            viewModel.updateFormThemeConfig(
                                                                themeConfig.copy(csvColumnMappings = themeConfig.csvColumnMappings - headerCol)
                                                            )
                                                            colDropdownOpen = false
                                                        }
                                                    )
                                                    formFieldsForMapping.forEach { f ->
                                                        DropdownMenuItem(
                                                            text = { Text(f.label.ifBlank { f.type.displayName }) },
                                                            onClick = {
                                                                viewModel.updateFormThemeConfig(
                                                                    themeConfig.copy(csvColumnMappings = themeConfig.csvColumnMappings + (headerCol to f.id))
                                                                )
                                                                colDropdownOpen = false
                                                            }
                                                        )
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            // Interactive Test Lookup Box
                            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Text("Test Frontend Lookup (যাচাই করুন)", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                                    OutlinedTextField(
                                        value = testLookupKey,
                                        onValueChange = { testLookupKey = it },
                                        placeholder = { Text("e.g. STU-1001", fontSize = 12.sp) },
                                        modifier = Modifier.weight(1f),
                                        shape = RoundedCornerShape(10.dp),
                                        singleLine = true
                                    )
                                    Button(
                                        onClick = {
                                            testLookupResult = viewModel.lookupCsvRecord(testLookupKey)
                                            if (testLookupResult == null) {
                                                Toast.makeText(context, "No matching record found", Toast.LENGTH_SHORT).show()
                                            }
                                        },
                                        shape = RoundedCornerShape(10.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                                    ) {
                                        Text("Search", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                    }
                                }

                                testLookupResult?.let { row ->
                                    Card(
                                        shape = RoundedCornerShape(10.dp),
                                        colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1E2438) else Color(0xFFEEF2FF)),
                                        border = BorderStroke(1.dp, Color(0xFF6366F1).copy(alpha = 0.4f)),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                            Text("✓ Match Found:", fontWeight = FontWeight.Bold, fontSize = 11.5.sp, color = Color(0xFF6366F1))
                                            row.forEach { (k, v) ->
                                                Text("$k: $v", fontSize = 11.sp, color = textPrimary)
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

        // 4. VALIDATION & SECURITY RULES SECTION
        item {
            SettingsCardSection(
                title = "Validation & Security Rules",
                icon = Icons.Outlined.VerifiedUser,
                isDark = isDark
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    SettingsToggleRow("Enforce Required Fields on Submit", enforceRequiredFields) {
                        enforceRequiredFields = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enforceRequiredFields = it))
                    }
                    SettingsToggleRow("Strict Email & BD Phone Verification", strictFormatValidation) {
                        strictFormatValidation = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(strictFormatValidation = it))
                    }
                    SettingsToggleRow("Enforce Per-Field Upload Limits", enforceFileSizeLimit) {
                        enforceFileSizeLimit = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enforceFileSizeLimit = it))
                    }
                    SettingsToggleRow("Quantity Min/Max Limiter", enforceQuantityRange) {
                        enforceQuantityRange = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enforceQuantityRange = it))
                    }

                    if (enforceQuantityRange) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            OutlinedTextField(
                                value = minQuantityStr,
                                onValueChange = {
                                    minQuantityStr = it.filter(Char::isDigit).take(6)
                                    viewModel.updateFormThemeConfig(themeConfig.copy(minQuantity = minQuantityStr.toIntOrNull()?.coerceAtLeast(0) ?: 1))
                                },
                                label = { Text("Min Qty") },
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp)
                            )
                            OutlinedTextField(
                                value = maxQuantityStr,
                                onValueChange = {
                                    maxQuantityStr = it.filter(Char::isDigit).take(7)
                                    viewModel.updateFormThemeConfig(themeConfig.copy(maxQuantity = maxQuantityStr.toIntOrNull()?.coerceAtLeast(1) ?: 1000))
                                },
                                label = { Text("Max Qty") },
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp)
                            )
                        }
                    }

                    SettingsToggleRow("Anti-Spam / Rate Limiting", enableAntiSpam) {
                        enableAntiSpam = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enableAntiSpam = it))
                    }
                }
            }
        }

        // 5. BROWSER LOCKDOWN & ANTI-CHEATING (EXAM PROCTORING)
        item {
            SettingsCardSection(
                title = "Browser Lockdown & Anti-Cheating (ব্রাউজার লকডাউন)",
                icon = Icons.Outlined.Shield,
                isDark = isDark
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(
                        "Enforce strict exam integrity and prevent cheating during online assessments, quizzes, and surveys. Monitors browser focus, fullscreen mode, and disables unauthorized copying.",
                        fontSize = 11.5.sp,
                        color = textSecondary,
                        lineHeight = 16.sp
                    )

                    SettingsToggleRow("Enable Browser Lockdown Mode", enableBrowserLockdown) {
                        enableBrowserLockdown = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enableBrowserLockdown = it))
                    }

                    if (enableBrowserLockdown) {
                        Surface(
                            shape = RoundedCornerShape(10.dp),
                            color = goldPrimary.copy(alpha = 0.08f),
                            border = BorderStroke(1.dp, goldPrimary.copy(alpha = 0.25f)),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                SettingsToggleRow("Enforce Full-Screen on Start (ফুল-স্ক্রিন)", lockdownRequireFullscreen) {
                                    lockdownRequireFullscreen = it
                                    viewModel.updateFormThemeConfig(themeConfig.copy(lockdownRequireFullscreen = it))
                                }
                                SettingsToggleRow("Detect & Warn on Tab Switch (ট্যাব পরিবর্তন সনাক্তকরণ)", lockdownBlockTabSwitch) {
                                    lockdownBlockTabSwitch = it
                                    viewModel.updateFormThemeConfig(themeConfig.copy(lockdownBlockTabSwitch = it))
                                }
                                if (lockdownBlockTabSwitch) {
                                    OutlinedTextField(
                                        value = lockdownMaxViolationsStr,
                                        onValueChange = {
                                            lockdownMaxViolationsStr = it.filter(Char::isDigit).take(2)
                                            val maxV = lockdownMaxViolationsStr.toIntOrNull()?.coerceIn(1, 20) ?: 3
                                            viewModel.updateFormThemeConfig(themeConfig.copy(lockdownMaxViolations = maxV))
                                        },
                                        label = { Text("Max Allowed Tab Violations Before Lock") },
                                        supportingText = { Text("Candidate is locked out after this many tab/app switches (Default: 3).") },
                                        modifier = Modifier.fillMaxWidth(),
                                        shape = RoundedCornerShape(10.dp),
                                        singleLine = true
                                    )
                                }
                                SettingsToggleRow("Disable Copy, Cut, Paste & Right-Click", lockdownDisableCopyPaste) {
                                    lockdownDisableCopyPaste = it
                                    viewModel.updateFormThemeConfig(themeConfig.copy(lockdownDisableCopyPaste = it))
                                }
                                SettingsToggleRow("Show Anti-Leak Watermark on Screen", lockdownWatermark) {
                                    lockdownWatermark = it
                                    viewModel.updateFormThemeConfig(themeConfig.copy(lockdownWatermark = it))
                                }
                            }
                        }
                    }
                }
            }
        }

        // 6. PAYMENT SETTINGS
        item {
            SettingsCardSection(
                title = "Payment Integration",
                icon = Icons.Outlined.Payments,
                isDark = isDark
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    SettingsToggleRow("Enable Payment", enablePayment) {
                        enablePayment = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enablePayment = it))
                    }

                    if (enablePayment) {
                        OutlinedTextField(
                            value = paymentProvider,
                            onValueChange = {
                                paymentProvider = it.take(30)
                                viewModel.updateFormThemeConfig(themeConfig.copy(paymentProvider = paymentProvider.uppercase()))
                            },
                            label = { Text("Payment Provider (AUTO, bKash, Nagad, Rocket, Upay)") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp)
                        )

                        OutlinedTextField(
                            value = currency,
                            onValueChange = {
                                currency = it.uppercase().filter(Char::isLetter).take(3)
                                viewModel.updateFormThemeConfig(themeConfig.copy(currencyCode = currency.ifBlank { "BDT" }))
                            },
                            label = { Text("Currency") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp)
                        )

                        OutlinedTextField(
                            value = taxFeesPercent,
                            onValueChange = {
                                taxFeesPercent = it.filter { char -> char.isDigit() || char == '.' }.take(6)
                                viewModel.updateFormThemeConfig(themeConfig.copy(taxPercent = taxFeesPercent.toDoubleOrNull()?.coerceIn(0.0, 100.0) ?: 0.0))
                            },
                            label = { Text("Tax / Fees (%)") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp)
                        )

                        SettingsToggleRow("Require Payment Before Submit", requirePaymentBeforeSubmit) {
                            requirePaymentBeforeSubmit = it
                            viewModel.updateFormThemeConfig(themeConfig.copy(requirePaymentBeforeSubmit = it))
                        }
                    }
                }
            }
        }
    }
}

// Helper Composable for Settings Sections
@Composable
private fun SettingsCardSection(
    title: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    isDark: Boolean,
    content: @Composable () -> Unit
) {
    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0D1C2E)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)

    Card(
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(icon, contentDescription = null, tint = goldText, modifier = Modifier.size(20.dp))
                Text(title, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = textPrimary)
            }

            HorizontalDivider(color = cardBorder, thickness = 1.dp)

            content()
        }
    }
}

@Composable
private fun SettingsToggleRow(
    title: String,
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = title,
            fontSize = 14.sp,
            fontWeight = FontWeight.Medium,
            modifier = Modifier.weight(1f).padding(end = 12.dp)
        )
        Switch(
            checked = checked,
            onCheckedChange = onCheckedChange,
            colors = SwitchDefaults.colors(
                checkedThumbColor = Color.White,
                uncheckedThumbColor = Color.White,
                checkedTrackColor = Color(0xFFFFC800)
            )
        )
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB 3: INTEGRATIONS TAB
// ═══════════════════════════════════════════════════════════════════════════
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FormIntegrationsTab(
    viewModel: AppViewModel,
    onOpenWebAppPreview: () -> Unit = {}
) {
    val context = LocalContext.current
    val clipboardManager = LocalClipboardManager.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0D1C2E)
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val goldPrimary = if (isDark) Color(0xFFE5A93C) else Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)
    val goldDarkBg = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0)

    val themeConfig by viewModel.formThemeConfig.collectAsState()
    val formFields by viewModel.formFieldsList.collectAsState()
    val activeFormId by viewModel.activeFormId.collectAsState()
    var isRedirectEnabled by remember(themeConfig.redirectType) { mutableStateOf(themeConfig.redirectType != "STAY_ON_FORM") }
    var redirectType by remember(themeConfig.redirectType) {
        mutableStateOf(
            when (themeConfig.redirectType) {
                "SUCCESS_MSG" -> "Success Msg"
                "CUSTOM_HTML" -> "Custom HTML"
                "STAY_ON_FORM" -> "Stay on Form"
                else -> "Redirect URL"
            }
        )
    }
    var redirectUrl by remember(themeConfig.redirectUrl) { mutableStateOf(themeConfig.redirectUrl) }
    var redirectDelay by remember(themeConfig.redirectDelaySec) { mutableStateOf(themeConfig.redirectDelaySec.toString()) }
    var openInNewTab by remember(themeConfig.openInNewTab) { mutableStateOf(themeConfig.openInNewTab) }
    var customHtml by remember(activeFormId) { mutableStateOf(themeConfig.customHtmlContent) }
    var customCss by remember(activeFormId) { mutableStateOf(themeConfig.customCss) }
    var customJs by remember(activeFormId) { mutableStateOf(themeConfig.customJs) }
    var newVarKey by remember { mutableStateOf("") }
    var newVarExample by remember { mutableStateOf("") }
    var variableMenuExpanded by remember { mutableStateOf(false) }
    var uploadedCodeName by remember { mutableStateOf("") }

    val builtInVariables = listOf(
        "{{form_title}}", "{{form_description}}", "{{name}}", "{{email}}", "{{phone}}", "{{submission_id}}",
        "{{transaction_id}}", "{{payment_status}}", "{{payment_amount}}",
        "{{payment_method}}", "{{created_at}}", "{{all_fields}}"
    )
    val fieldVariables = formFields.map { field ->
        val key = field.label.lowercase().replace(Regex("[^a-z0-9]+"), "_").trim('_').ifBlank { field.id.replace("-", "_") }
        "{{field_$key}}"
    }
    val availableVariables = (builtInVariables + fieldVariables + themeConfig.customVariables.map { variable ->
        val key = variable.key.removePrefix("{{").removeSuffix("}}")
        "{{$key}}"
    }).distinct()

    val codeFileLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        runCatching {
            val resolver = context.contentResolver
            var displayName = "custom-code.html"
            resolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)?.use { cursor ->
                if (cursor.moveToFirst()) {
                    displayName = cursor.getString(cursor.getColumnIndexOrThrow(OpenableColumns.DISPLAY_NAME))
                    val sizeIndex = cursor.getColumnIndex(OpenableColumns.SIZE)
                    if (sizeIndex >= 0 && !cursor.isNull(sizeIndex) && cursor.getLong(sizeIndex) > 262_144L) {
                        error("Custom-code files are limited to 256 KB")
                    }
                }
            }
            val bytes = resolver.openInputStream(uri)?.use { input ->
                val output = java.io.ByteArrayOutputStream()
                val buffer = ByteArray(8_192)
                var total = 0
                while (true) {
                    val read = input.read(buffer)
                    if (read < 0) break
                    total += read
                    if (total > 262_144) error("Custom-code files are limited to 256 KB")
                    output.write(buffer, 0, read)
                }
                output.toByteArray()
            } ?: error("Unable to read the selected file")
            val content = bytes.toString(Charsets.UTF_8)
            if (displayName.endsWith(".css", ignoreCase = true)) {
                customCss = content.take(100_000)
                viewModel.updateFormThemeConfig(themeConfig.copy(customCss = customCss))
            } else if (displayName.endsWith(".js", ignoreCase = true)) {
                customJs = content.take(100_000)
                viewModel.updateFormThemeConfig(themeConfig.copy(customJs = customJs, enableCustomJs = true))
            } else {
                customHtml = content.take(200_000)
                viewModel.updateFormThemeConfig(themeConfig.copy(customHtmlContent = customHtml, enableCustomHtml = true, redirectType = "CUSTOM_HTML"))
                redirectType = "Custom HTML"
            }
            uploadedCodeName = displayName
        }.onFailure { error ->
            Toast.makeText(context, error.message ?: "Unable to import custom code", Toast.LENGTH_LONG).show()
        }
    }

    var isEmailEnabled by remember(themeConfig.enableEmailNotifications) { mutableStateOf(themeConfig.enableEmailNotifications) }
    var isSmsEnabled by remember(themeConfig.enableSmsNotifications) { mutableStateOf(themeConfig.enableSmsNotifications) }
    var isPaymentCallbackEnabled by remember(themeConfig.enablePaymentCallback) { mutableStateOf(themeConfig.enablePaymentCallback) }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
        contentPadding = PaddingValues(top = 14.dp, bottom = 24.dp)
    ) {
        // Header Description
        item {
            Text(
                text = "Connect third-party services and manage what happens after form submission.",
                fontSize = 13.sp,
                color = textSecondary
            )
        }

        // 1. EXPANDED ACCORDION: REDIRECT
        item {
            Card(
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = cardBg),
                border = BorderStroke(1.dp, cardBorder),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(
                            modifier = Modifier.weight(1f).padding(end = 8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(40.dp)
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(Color(0xFFDCFCE7)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(Icons.Outlined.Language, null, tint = Color(0xFF15803D), modifier = Modifier.size(20.dp))
                            }

                            Column(modifier = Modifier.weight(1f, fill = false)) {
                                Text("Redirect", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                Text("Choose where to redirect users", fontSize = 12.sp, color = textSecondary)
                            }
                        }

                        Switch(
                            checked = isRedirectEnabled,
                            onCheckedChange = { enabled ->
                                isRedirectEnabled = enabled
                                viewModel.updateFormThemeConfig(
                                    themeConfig.copy(redirectType = if (enabled) "SUCCESS_MSG" else "STAY_ON_FORM")
                                )
                                if (enabled) redirectType = "Success Msg"
                            },
                            colors = SwitchDefaults.colors(
                                checkedThumbColor = Color.White,
                                uncheckedThumbColor = Color.White,
                                checkedTrackColor = Color(0xFFFFC800)
                            )
                        )
                    }

                    if (isRedirectEnabled) {
                        HorizontalDivider(color = cardBorder, thickness = 1.dp)

                        Text("REDIRECT TYPE", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textSecondary)

                        // 4 Redirect Type Options Grid
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            listOf(
                                "Success Msg" to Icons.Outlined.CheckCircle,
                                "Redirect URL" to Icons.Outlined.Link,
                                "Custom HTML" to Icons.Outlined.Code,
                                "Stay on Form" to Icons.Outlined.Description
                            ).forEach { (label, icon) ->
                                val isSel = redirectType == label
                                Surface(
                                    onClick = {
                                        redirectType = label
                                        val persistedType = when (label) {
                                            "Success Msg" -> "SUCCESS_MSG"
                                            "Custom HTML" -> "CUSTOM_HTML"
                                            "Stay on Form" -> "STAY_ON_FORM"
                                            else -> "REDIRECT_URL"
                                        }
                                        viewModel.updateFormThemeConfig(
                                            themeConfig.copy(
                                                redirectType = persistedType,
                                                enableCustomHtml = persistedType == "CUSTOM_HTML"
                                            )
                                        )
                                    },
                                    shape = RoundedCornerShape(12.dp),
                                    color = if (isSel) goldDarkBg else cardBg,
                                    border = BorderStroke(1.5.dp, if (isSel) goldPrimary else cardBorder),
                                    modifier = Modifier
                                        .weight(1f)
                                        .height(84.dp)
                                ) {
                                    Column(
                                        modifier = Modifier.padding(8.dp),
                                        horizontalAlignment = Alignment.CenterHorizontally,
                                        verticalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Icon(icon, null, tint = if (isSel) goldText else textSecondary, modifier = Modifier.size(20.dp))
                                        Text(
                                            text = label,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = if (isSel) goldText else textPrimary,
                                            textAlign = TextAlign.Center,
                                            maxLines = 2
                                        )
                                        Box(
                                            modifier = Modifier
                                                .size(12.dp)
                                                .clip(CircleShape)
                                                .background(if (isSel) goldPrimary else Color.Transparent)
                                                .border(1.dp, if (isSel) goldPrimary else cardBorder, CircleShape)
                                        )
                                    }
                                }
                            }
                        }

                        // Redirect URL Input
                        OutlinedTextField(
                            value = redirectUrl,
                            onValueChange = {
                                redirectUrl = it
                                viewModel.updateFormThemeConfig(themeConfig.copy(redirectUrl = it))
                            },
                            label = { Text("Redirect URL") },
                            leadingIcon = { Icon(Icons.Outlined.Link, null, tint = textSecondary) },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp)
                        )

                        // Custom Dynamic Web App & Code Studio
                        var isCustomWebApp by remember(themeConfig.isCustomWebApp) { mutableStateOf(themeConfig.isCustomWebApp) }
                        var activeCodeTab by remember { mutableStateOf("HTML") } // HTML, CSS, JS, VARIABLES
                        var showVarCategoryDropdown by remember { mutableStateOf(false) }

                        val dbVariables = remember { viewModel.getAvailableDatabaseVariables() }

                        fun loadCodeTemplate(templateKey: String) {
                            when (templateKey) {
                                "ECOMMERCE" -> {
                                    customHtml = """<div class="store-card">
  <div class="store-header">
    <h1>{{merchant_name}}</h1>
    <p>{{merchant_address}} | 📞 {{merchant_phone}}</p>
  </div>
  <div class="order-badge">Order Ref: {{invoice_number}} | {{current_date}}</div>
  <div class="product-banner">
    <h2>{{form_title}}</h2>
    <p>{{form_description}}</p>
    <div class="price-tag">{{currency}} <span id="total-price">1,500</span></div>
  </div>
  <div class="customer-box">
    <label>Customer Name:</label>
    <input type="text" id="cust-name" placeholder="Enter full name" />
    <label>Phone Number:</label>
    <input type="tel" id="cust-phone" placeholder="017XXXXXXXX" />
  </div>
  <button class="pay-btn" onclick="triggerCheckout()">Pay with bKash / Nagad</button>
</div>""".trimIndent()
                                    customCss = """.store-card { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 20px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); font-family: system-ui, sans-serif; }
.store-header h1 { font-size: 22px; color: #1e1b4b; margin-bottom: 4px; }
.store-header p { font-size: 12px; color: #64748b; }
.order-badge { background: #f1f5f9; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: bold; margin: 14px 0; display: inline-block; }
.product-banner { background: #faf5ff; border: 1.5px solid #d8b4fe; border-radius: 14px; padding: 16px; margin-bottom: 16px; }
.price-tag { font-size: 24px; font-weight: 800; color: #7e22ce; margin-top: 8px; }
.customer-box label { display: block; font-size: 12px; font-weight: 600; margin-top: 10px; color: #334155; }
.customer-box input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 10px; margin-top: 4px; font-size: 14px; }
.pay-btn { width: 100%; margin-top: 20px; padding: 14px; background: #e11d48; color: #ffffff; border: none; border-radius: 12px; font-size: 16px; font-weight: bold; cursor: pointer; }
.pay-btn:hover { background: #be123c; }""".trimIndent()
                                    customJs = """function triggerCheckout() {
  var name = document.getElementById('cust-name').value;
  var phone = document.getElementById('cust-phone').value;
  if (!name || !phone) { alert('Please enter your name and phone number'); return; }
  alert('Thank you ' + name + '! Processing order with {{merchant_name}}...');
}""".trimIndent()
                                }
                                "LEDGER" -> {
                                    customHtml = """<div class="ledger-portal">
  <div class="portal-header">
    <h2>{{merchant_name}} - Khata Portal</h2>
    <p>Live Business & Due Receivables Snapshot</p>
  </div>
  <div class="kpi-grid">
    <div class="kpi-box"><span>Outstanding Dues</span><strong>{{total_due_receivable}}</strong></div>
    <div class="kpi-box"><span>Total Sales</span><strong>{{total_sales}}</strong></div>
    <div class="kpi-box"><span>Active Customers</span><strong>{{total_customers}}</strong></div>
    <div class="kpi-box"><span>Catalog Items</span><strong>{{total_products}}</strong></div>
  </div>
  <div class="notice">Last synchronized: {{current_date}} at {{current_time}}</div>
  <button class="action-btn" onclick="openPaymentLink()">Pay Outstanding Due Online</button>
</div>""".trimIndent()
                                    customCss = """.ledger-portal { max-width: 580px; margin: 0 auto; background: #0f172a; color: #f8fafc; border-radius: 20px; padding: 24px; font-family: system-ui, sans-serif; }
.portal-header h2 { font-size: 20px; color: #38bdf8; }
.portal-header p { font-size: 12px; color: #94a3b8; margin-top: 2px; }
.kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; }
.kpi-box { background: #1e293b; padding: 16px; border-radius: 14px; border: 1px solid #334155; }
.kpi-box span { display: block; font-size: 11px; color: #94a3b8; text-transform: uppercase; }
.kpi-box strong { font-size: 18px; color: #facc15; margin-top: 4px; display: block; }
.notice { font-size: 11px; color: #64748b; text-align: center; }
.action-btn { width: 100%; margin-top: 16px; padding: 12px; background: #22c55e; color: #0f172a; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; }""".trimIndent()
                                    customJs = """function openPaymentLink() {
  window.location.href = '{{checkout_url}}';
}""".trimIndent()
                                }
                                "INVOICE" -> {
                                    customHtml = """<div class="invoice-box">
  <div class="inv-top">
    <div>
      <h1>{{merchant_name}}</h1>
      <p>{{merchant_address}}</p>
      <p>📞 {{merchant_phone}}</p>
    </div>
    <div class="inv-meta">
      <h2>INVOICE</h2>
      <p><strong>#{{invoice_number}}</strong></p>
      <p>{{current_date}}</p>
    </div>
  </div>
  <hr class="divider"/>
  <h3>{{form_title}}</h3>
  <p class="desc">{{form_description}}</p>
  <div class="total-bar">
    <span>Amount Payable:</span>
    <span class="due">{{currency}} 2,800.00</span>
  </div>
  <button class="print-btn" onclick="window.print()">🖨️ Print Invoice Receipt</button>
</div>""".trimIndent()
                                    customCss = """.invoice-box { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 28px; border-radius: 16px; border: 1px solid #e2e8f0; font-family: monospace, sans-serif; color: #0f172a; }
.inv-top { display: flex; justify-content: space-between; align-items: flex-start; }
.inv-top h1 { font-size: 20px; font-weight: bold; }
.inv-top p { font-size: 12px; color: #64748b; }
.inv-meta { text-align: right; }
.inv-meta h2 { font-size: 22px; color: #6366f1; }
.divider { margin: 18px 0; border: none; border-top: 1.5px dashed #cbd5e1; }
.total-bar { display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; padding: 14px; background: #f8fafc; border-radius: 10px; margin: 20px 0; }
.total-bar .due { color: #059669; }
.print-btn { width: 100%; padding: 12px; background: #0f172a; color: #ffffff; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; }""".trimIndent()
                                    customJs = """console.log('Invoice {{invoice_number}} ready for {{merchant_name}}');""".trimIndent()
                                }
                            }
                            viewModel.updateFormThemeConfig(
                                viewModel.formThemeConfig.value.copy(
                                    customHtmlContent = customHtml,
                                    customCss = customCss,
                                    customJs = customJs,
                                    enableCustomHtml = true,
                                    enableCustomJs = true,
                                    isCustomWebApp = true
                                )
                            )
                            isCustomWebApp = true
                            Toast.makeText(context, "Loaded template: $templateKey", Toast.LENGTH_SHORT).show()
                        }

                        Spacer(modifier = Modifier.height(10.dp))

                        // Custom Web App Master Switch
                        Card(
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = if (isCustomWebApp) {
                                    if (isDark) Color(0xFF1E1B4B) else Color(0xFFEEF2FF)
                                } else {
                                    if (isDark) Color(0xFF16130E) else Color(0xFFF8FAFC)
                                }
                            ),
                            border = BorderStroke(1.5.dp, if (isCustomWebApp) Color(0xFF6366F1) else cardBorder),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Row(
                                modifier = Modifier.padding(14.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Row(
                                    modifier = Modifier.weight(1f).padding(end = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(38.dp)
                                            .clip(RoundedCornerShape(10.dp))
                                            .background(if (isCustomWebApp) Color(0xFF6366F1) else Color(0xFF64748B)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(Icons.Outlined.Language, null, tint = Color.White, modifier = Modifier.size(20.dp))
                                    }
                                    Column {
                                        Text("Host as Custom Dynamic Web App", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                        Text("কাস্টম ওয়েব অ্যাপ ও ডাটাবেস ভেরিয়েবল দিয়ে হোস্ট করুন", fontSize = 11.sp, color = textSecondary)
                                    }
                                }
                                Switch(
                                    checked = isCustomWebApp,
                                    onCheckedChange = {
                                        isCustomWebApp = it
                                        viewModel.updateFormThemeConfig(
                                            themeConfig.copy(
                                                isCustomWebApp = it,
                                                enableCustomHtml = if (it) true else themeConfig.enableCustomHtml,
                                                enableCustomJs = if (it) true else themeConfig.enableCustomJs
                                            )
                                        )
                                    },
                                    colors = SwitchDefaults.colors(
                                        checkedThumbColor = Color.White,
                                        uncheckedThumbColor = Color.White,
                                        checkedTrackColor = Color(0xFF6366F1)
                                    )
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(8.dp))

                        // Actions Bar: Upload File + Presets + Preview Button
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("Code & Web Assets", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = textPrimary)

                            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                // Upload Button
                                Surface(
                                    onClick = {
                                        codeFileLauncher.launch(arrayOf("text/html", "text/css", "text/javascript", "application/javascript", "text/plain", "application/xhtml+xml", "application/json"))
                                    },
                                    shape = RoundedCornerShape(10.dp),
                                    color = goldDarkBg,
                                    border = BorderStroke(1.dp, goldPrimary)
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Icon(Icons.Outlined.Upload, null, tint = goldText, modifier = Modifier.size(15.dp))
                                        Text("Upload Code", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = goldText)
                                    }
                                }

                                // Preview Web Button
                                Surface(
                                    onClick = {
                                        onOpenWebAppPreview()
                                    },
                                    shape = RoundedCornerShape(10.dp),
                                    color = Color(0xFF6366F1).copy(alpha = 0.12f),
                                    border = BorderStroke(1.dp, Color(0xFF6366F1))
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Icon(Icons.Outlined.Visibility, null, tint = Color(0xFF6366F1), modifier = Modifier.size(15.dp))
                                        Text("Test Code", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFF6366F1))
                                    }
                                }
                            }
                        }

                        if (uploadedCodeName.isNotBlank()) {
                            Text("Imported: $uploadedCodeName", fontSize = 11.sp, color = Color(0xFF10B981), fontWeight = FontWeight.SemiBold)
                        }

                        // Ready Templates Chips
                        Text("Ready-Made Dynamic Web Templates:", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = textSecondary)
                        Row(
                            modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            listOf(
                                "⚡ Checkout Page" to "ECOMMERCE",
                                "📊 Live Khata Portal" to "LEDGER",
                                "🧾 Digital Invoice" to "INVOICE"
                            ).forEach { (label, key) ->
                                Surface(
                                    onClick = { loadCodeTemplate(key) },
                                    shape = RoundedCornerShape(8.dp),
                                    color = if (isDark) Color(0xFF1E2333) else Color(0xFFF1F5F9),
                                    border = BorderStroke(0.8.dp, cardBorder)
                                ) {
                                    Text(label, fontSize = 11.5.sp, color = textPrimary, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(6.dp))

                        var aiPromptInput by remember { mutableStateOf("") }
                        var aiGeneratedExplanation by remember { mutableStateOf("") }
                        val isAiGenerating by viewModel.isAiGeneratingFormCode.collectAsState()

                        // Category Tabs: AI Copilot | HTML | CSS | JavaScript | Variables | CSV Backend
                        Row(
                            modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            listOf(
                                "AI Copilot" to Icons.Outlined.Build,
                                "HTML" to Icons.Outlined.Html,
                                "CSS" to Icons.Outlined.Css,
                                "JavaScript" to Icons.Outlined.Code,
                                "Variables" to Icons.Outlined.DataObject,
                                "CSV Backend" to Icons.Outlined.Storage
                            ).forEach { (tabName, tabIcon) ->
                                val isSelected = activeCodeTab == tabName
                                Surface(
                                    onClick = { activeCodeTab = tabName },
                                    shape = RoundedCornerShape(10.dp),
                                    color = if (isSelected) goldPrimary else (if (isDark) Color(0xFF1A1610) else Color(0xFFF1F5F9)),
                                    border = BorderStroke(1.dp, if (isSelected) goldPrimary else cardBorder),
                                    modifier = Modifier.weight(1f).height(36.dp)
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 4.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.Center
                                    ) {
                                        Icon(tabIcon, null, tint = if (isSelected) Color.Black else textSecondary, modifier = Modifier.size(15.dp))
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text(
                                            text = tabName,
                                            fontSize = 10.5.sp,
                                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                            color = if (isSelected) Color.Black else textPrimary,
                                            maxLines = 1
                                        )
                                    }
                                }
                            }
                        }

                        // Content according to activeCodeTab
                        when (activeCodeTab) {
                            "AI Copilot" -> {
                                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Card(
                                        shape = RoundedCornerShape(14.dp),
                                        colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1B1832) else Color(0xFFEEF2FF)),
                                        border = BorderStroke(1.dp, Color(0xFF6366F1).copy(alpha = 0.4f))
                                    ) {
                                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                                Icon(Icons.Outlined.Build, null, tint = Color(0xFF6366F1), modifier = Modifier.size(20.dp))
                                                Text("AI Form & Code Studio (এআই বিল্ডার)", fontWeight = FontWeight.Bold, fontSize = 13.5.sp, color = textPrimary)
                                            }
                                            Text(
                                                "Type what you want to build. AI generates custom HTML structure, CSS styling, JavaScript logic, and dynamic variables automatically.",
                                                fontSize = 11.5.sp,
                                                color = textSecondary
                                            )
                                        }
                                    }

                                    Text("QUICK PROMPTS (১-ট্যাপে তৈরি করুন):", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                    Row(
                                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                                        modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState())
                                    ) {
                                        listOf(
                                            "⚡ E-Commerce with bKash" to "Create modern e-commerce checkout form with product card, quantity selector, bKash payment button, and customer inputs",
                                            "🔍 CSV Data Lookup" to "Create a CSV database search and autofill form where users enter student ID or code to view due amounts and pay",
                                            "🎨 Glassmorphism Dark" to "Build a sleek glassmorphic checkout card with neon accents, promo code discount calculation, and smooth shadows",
                                            "🍽️ Food Ordering" to "Create a restaurant delivery ordering form with menu items quantity counter, delivery address, and subtotal calculation"
                                        ).forEach { (chipLabel, chipPrompt) ->
                                            Surface(
                                                onClick = { aiPromptInput = chipPrompt },
                                                shape = RoundedCornerShape(8.dp),
                                                color = if (isDark) Color(0xFF1E2333) else Color(0xFFF1F5F9),
                                                border = BorderStroke(0.8.dp, cardBorder)
                                            ) {
                                                Text(chipLabel, fontSize = 11.sp, color = textPrimary, modifier = Modifier.padding(horizontal = 8.dp, vertical = 6.dp))
                                            }
                                        }
                                    }

                                    OutlinedTextField(
                                        value = aiPromptInput,
                                        onValueChange = { aiPromptInput = it },
                                        placeholder = { Text("e.g. Build a restaurant ordering form with bKash payment and delivery fee calculation...", fontSize = 12.sp) },
                                        modifier = Modifier.fillMaxWidth().heightIn(min = 90.dp),
                                        shape = RoundedCornerShape(12.dp),
                                        maxLines = 4
                                    )

                                    Button(
                                        onClick = {
                                            viewModel.generateAiFormCode(
                                                userPrompt = aiPromptInput.ifBlank { "Create an e-commerce checkout form with bKash payment" },
                                                onSuccess = { genHtml, genCss, genJs, explanation ->
                                                    customHtml = genHtml
                                                    customCss = genCss
                                                    customJs = genJs
                                                    aiGeneratedExplanation = explanation
                                                    viewModel.updateFormThemeConfig(
                                                        themeConfig.copy(
                                                            customHtmlContent = genHtml,
                                                            customCss = genCss,
                                                            customJs = genJs,
                                                            enableCustomHtml = true,
                                                            enableCustomJs = true,
                                                            isCustomWebApp = true
                                                        )
                                                    )
                                                    Toast.makeText(context, "AI code generated & applied successfully!", Toast.LENGTH_SHORT).show()
                                                },
                                                onError = { err -> Toast.makeText(context, err, Toast.LENGTH_SHORT).show() }
                                            )
                                        },
                                        enabled = !isAiGenerating,
                                        modifier = Modifier.fillMaxWidth().height(46.dp),
                                        shape = RoundedCornerShape(12.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF6366F1), contentColor = Color.White)
                                    ) {
                                        if (isAiGenerating) {
                                            CircularProgressIndicator(color = Color.White, modifier = Modifier.size(20.dp))
                                            Spacer(Modifier.width(8.dp))
                                            Text("Generating Code...", fontSize = 13.sp)
                                        } else {
                                            Icon(Icons.Outlined.Build, null, modifier = Modifier.size(18.dp))
                                            Spacer(Modifier.width(8.dp))
                                            Text("Generate with AI (এআই কোড তৈরি করুন)", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                        }
                                    }

                                    if (aiGeneratedExplanation.isNotBlank()) {
                                        Card(
                                            shape = RoundedCornerShape(12.dp),
                                            colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF13221C) else Color(0xFFECFDF5)),
                                            border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.5f))
                                        ) {
                                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                                    Icon(Icons.Outlined.CheckCircle, null, tint = Color(0xFF10B981), modifier = Modifier.size(18.dp))
                                                    Text("AI Code Applied to Form:", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = Color(0xFF065F46))
                                                }
                                                Text(aiGeneratedExplanation, fontSize = 11.5.sp, color = textPrimary)
                                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                                    Button(
                                                        onClick = { onOpenWebAppPreview() },
                                                        modifier = Modifier.weight(1f).height(38.dp),
                                                        shape = RoundedCornerShape(8.dp),
                                                        colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                                                    ) {
                                                        Text("👁️ Live Web Preview", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                                    }
                                                    OutlinedButton(
                                                        onClick = { activeCodeTab = "HTML" },
                                                        modifier = Modifier.weight(1f).height(38.dp),
                                                        shape = RoundedCornerShape(8.dp)
                                                    ) {
                                                        Text("Inspect HTML", fontSize = 12.sp)
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            "CSV Backend" -> {
                                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Card(
                                        shape = RoundedCornerShape(12.dp),
                                        colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1A1A28) else Color(0xFFF8FAFC)),
                                        border = BorderStroke(1.dp, cardBorder)
                                    ) {
                                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                            Text("CSV Backend Integration for Frontend", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = textPrimary)
                                            Text(
                                                "Status: ${if (themeConfig.enableCsvBackend) "ACTIVE (" + themeConfig.csvFileName + ")" else "INACTIVE"}",
                                                fontSize = 11.5.sp,
                                                color = if (themeConfig.enableCsvBackend) Color(0xFF10B981) else textSecondary
                                            )
                                            if (themeConfig.csvHeaders.isNotEmpty()) {
                                                Text("Detected Columns: ${themeConfig.csvHeaders.joinToString(", ")}", fontSize = 11.sp, color = textSecondary)
                                                Text("Client-Side Global: window.csvBackendData", fontSize = 11.sp, fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace, color = Color(0xFF6366F1))
                                            }
                                        }
                                    }

                                    Button(
                                        onClick = {
                                            viewModel.loadSampleCsvTemplate("STUDENT_PORTAL")
                                            Toast.makeText(context, "Loaded Student Portal dataset", Toast.LENGTH_SHORT).show()
                                        },
                                        modifier = Modifier.fillMaxWidth().height(42.dp),
                                        shape = RoundedCornerShape(10.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                                    ) {
                                        Text("Load Sample Dataset into CSV Backend", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                    }
                                }
                            }
                            "HTML" -> {
                                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("HTML Template Markup", fontSize = 12.sp, color = textSecondary, fontWeight = FontWeight.SemiBold)
                                        Box {
                                            TextButton(onClick = { showVarCategoryDropdown = true }) {
                                                Icon(Icons.Outlined.DataObject, null, tint = goldText, modifier = Modifier.size(16.dp))
                                                Spacer(modifier = Modifier.width(4.dp))
                                                Text("Insert Variable", fontSize = 11.5.sp, color = goldText)
                                            }
                                            DropdownMenu(
                                                expanded = showVarCategoryDropdown,
                                                onDismissRequest = { showVarCategoryDropdown = false }
                                            ) {
                                                dbVariables.forEach { (k, v) ->
                                                    DropdownMenuItem(
                                                        text = { Text("{{$k}}  ($v)", fontSize = 11.5.sp) },
                                                        onClick = {
                                                            customHtml += "{{$k}}"
                                                            viewModel.updateFormThemeConfig(viewModel.formThemeConfig.value.copy(customHtmlContent = customHtml))
                                                            showVarCategoryDropdown = false
                                                        }
                                                    )
                                                }
                                            }
                                        }
                                    }
                                    OutlinedTextField(
                                        value = customHtml,
                                        onValueChange = {
                                            customHtml = it.take(200_000)
                                            viewModel.updateFormThemeConfig(viewModel.formThemeConfig.value.copy(customHtmlContent = customHtml, enableCustomHtml = true))
                                        },
                                        placeholder = { Text("Enter HTML template code with {{variables}}...", fontSize = 12.sp) },
                                        modifier = Modifier.fillMaxWidth().height(160.dp),
                                        shape = RoundedCornerShape(12.dp),
                                        textStyle = androidx.compose.ui.text.TextStyle(fontSize = 11.5.sp),
                                        maxLines = 12
                                    )
                                }
                            }
                            "CSS" -> {
                                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Text("Custom CSS Stylesheet", fontSize = 12.sp, color = textSecondary, fontWeight = FontWeight.SemiBold)
                                    OutlinedTextField(
                                        value = customCss,
                                        onValueChange = {
                                            customCss = it.take(100_000)
                                            viewModel.updateFormThemeConfig(viewModel.formThemeConfig.value.copy(customCss = customCss))
                                        },
                                        placeholder = { Text("/* Custom CSS styles, animations & responsive media queries */", fontSize = 12.sp) },
                                        modifier = Modifier.fillMaxWidth().height(160.dp),
                                        shape = RoundedCornerShape(12.dp),
                                        textStyle = androidx.compose.ui.text.TextStyle(fontSize = 11.5.sp),
                                        maxLines = 12
                                    )
                                }
                            }
                            "JavaScript" -> {
                                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Text("Custom JavaScript Logic & Events", fontSize = 12.sp, color = textSecondary, fontWeight = FontWeight.SemiBold)
                                    OutlinedTextField(
                                        value = customJs,
                                        onValueChange = {
                                            customJs = it.take(100_000)
                                            viewModel.updateFormThemeConfig(viewModel.formThemeConfig.value.copy(customJs = customJs, enableCustomJs = true))
                                        },
                                        placeholder = { Text("// Custom scripts, DOM interactions, analytics & tracking", fontSize = 12.sp) },
                                        modifier = Modifier.fillMaxWidth().height(160.dp),
                                        shape = RoundedCornerShape(12.dp),
                                        textStyle = androidx.compose.ui.text.TextStyle(fontSize = 11.5.sp),
                                        maxLines = 12
                                    )
                                }
                            }
                            "Variables" -> {
                                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Text("Live Database Variables (Click to copy)", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                    LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                                        items(dbVariables) { (k, v) ->
                                            Surface(
                                                onClick = {
                                                    clipboardManager.setText(AnnotatedString("{{$k}}"))
                                                    Toast.makeText(context, "Copied {{$k}}", Toast.LENGTH_SHORT).show()
                                                },
                                                shape = RoundedCornerShape(8.dp),
                                                color = if (isDark) Color(0xFF1E2438) else Color(0xFFEEF2FF),
                                                border = BorderStroke(0.8.dp, Color(0xFF6366F1).copy(alpha = 0.4f))
                                            ) {
                                                Column(modifier = Modifier.padding(horizontal = 8.dp, vertical = 6.dp)) {
                                                    Text("{{$k}}", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFF6366F1))
                                                    Text(v, fontSize = 9.5.sp, color = textSecondary, maxLines = 1)
                                                }
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text("Custom User Variables", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                                        OutlinedTextField(
                                            value = newVarKey,
                                            onValueChange = { newVarKey = it },
                                            label = { Text("Key (e.g. discount_code)") },
                                            modifier = Modifier.weight(1f),
                                            shape = RoundedCornerShape(10.dp)
                                        )
                                        OutlinedTextField(
                                            value = newVarExample,
                                            onValueChange = { newVarExample = it },
                                            label = { Text("Default / Value") },
                                            modifier = Modifier.weight(1f),
                                            shape = RoundedCornerShape(10.dp)
                                        )
                                    }
                                    Button(
                                        onClick = {
                                            if (newVarKey.isNotBlank()) {
                                                viewModel.addCustomVariable(newVarKey, newVarExample)
                                                newVarKey = ""; newVarExample = ""
                                            }
                                        },
                                        modifier = Modifier.fillMaxWidth(),
                                        colors = ButtonDefaults.buttonColors(containerColor = goldPrimary, contentColor = Color.Black)
                                    ) {
                                        Text("Add Custom Variable", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                    }

                                    themeConfig.customVariables.forEach { cv ->
                                        Row(
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically,
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Text(
                                                text = "{{${cv.key}}}  =  ${cv.exampleValue}",
                                                fontSize = 12.sp,
                                                color = textPrimary,
                                                modifier = Modifier.weight(1f).padding(end = 8.dp)
                                            )
                                            IconButton(onClick = { viewModel.deleteCustomVariable(cv.key) }) {
                                                Icon(Icons.Outlined.Delete, null, tint = Color(0xFFEF4444), modifier = Modifier.size(16.dp))
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Delay & New Tab
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            OutlinedTextField(
                                value = redirectDelay,
                                onValueChange = {
                                    redirectDelay = it.filter(Char::isDigit).take(3)
                                    viewModel.updateFormThemeConfig(themeConfig.copy(redirectDelaySec = redirectDelay.toIntOrNull()?.coerceIn(0, 300) ?: 0))
                                },
                                label = { Text("Delay (sec)") },
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp)
                            )

                            Row(
                                modifier = Modifier.weight(1f),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text("New Tab", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                Switch(
                                    checked = openInNewTab,
                                    onCheckedChange = {
                                        openInNewTab = it
                                        viewModel.updateFormThemeConfig(themeConfig.copy(openInNewTab = it))
                                    },
                                    colors = SwitchDefaults.colors(
                                        checkedThumbColor = Color.White,
                                        uncheckedThumbColor = Color.White,
                                        checkedTrackColor = Color(0xFFFFC800)
                                    )
                                )
                            }
                        }
                    }
                }
            }
        }

        // 2. ACCORDION ITEMS: EMAIL, SMS, PAYMENT CALLBACK
        item {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                // Email Card
                IntegrationItemCard(
                    title = "Email Notifications",
                    subtitle = "Send email alerts on form submission",
                    icon = Icons.Outlined.Email,
                    iconBg = Color(0xFFDDE2F3),
                    iconTint = Color(0xFF585E6C),
                    enabled = isEmailEnabled,
                    onToggle = {
                        isEmailEnabled = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enableEmailNotifications = it))
                    },
                    isDark = isDark
                )
                if (isEmailEnabled) {
                    OutlinedTextField(
                        value = themeConfig.notificationEmail,
                        onValueChange = { value ->
                            viewModel.updateFormThemeConfig(themeConfig.copy(notificationEmail = value.trim().take(254)))
                        },
                        label = { Text("Notification recipient email") },
                        supportingText = { Text("Submission alerts are delivered through the configured Resend provider.") },
                        leadingIcon = { Icon(Icons.Outlined.Email, null) },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        singleLine = true
                    )
                }

                // SMS Card
                IntegrationItemCard(
                    title = "SMS Alerts",
                    subtitle = "Send SMS notifications to users or admins",
                    icon = Icons.Outlined.Sms,
                    iconBg = Color(0xFFD1FAE5),
                    iconTint = Color(0xFF047857),
                    enabled = isSmsEnabled,
                    onToggle = {
                        isSmsEnabled = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enableSmsNotifications = it))
                    },
                    isDark = isDark
                )
                if (isSmsEnabled) {
                    OutlinedTextField(
                        value = themeConfig.notificationSmsNumber,
                        onValueChange = { value ->
                            val normalized = value.filter { it.isDigit() || it == '+' }.take(16)
                            viewModel.updateFormThemeConfig(themeConfig.copy(notificationSmsNumber = normalized))
                        },
                        label = { Text("Notification recipient phone") },
                        supportingText = { Text("Uses the server-side SMS provider configured for hosted forms.") },
                        leadingIcon = { Icon(Icons.Outlined.Sms, null) },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        singleLine = true
                    )
                }

                // Payment Callback Card
                IntegrationItemCard(
                    title = "Payment Callback",
                    subtitle = "Configure webhooks for payment updates",
                    icon = Icons.Outlined.Payments,
                    iconBg = Color(0xFFFFEDD5),
                    iconTint = Color(0xFFC2410C),
                    enabled = isPaymentCallbackEnabled,
                    onToggle = {
                        isPaymentCallbackEnabled = it
                        viewModel.updateFormThemeConfig(themeConfig.copy(enablePaymentCallback = it))
                    },
                    isDark = isDark
                )
                if (isPaymentCallbackEnabled) {
                    OutlinedTextField(
                        value = themeConfig.paymentCallbackUrl,
                        onValueChange = { value ->
                            viewModel.updateFormThemeConfig(themeConfig.copy(paymentCallbackUrl = value.trim().take(2048)))
                        },
                        label = { Text("Signed payment webhook URL (HTTPS)") },
                        leadingIcon = { Icon(Icons.Outlined.Webhook, null) },
                        supportingText = { Text("Payment callbacks are signed with the merchant webhook secret and retried up to three times.") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        singleLine = true
                    )
                }
            }
        }

        // 3. AVAILABLE VARIABLES CARD
        item {
            Card(
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = goldDarkBg),
                border = BorderStroke(1.5.dp, goldPrimary),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(32.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .background(goldPrimary),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Outlined.Code, null, tint = Color.Black, modifier = Modifier.size(18.dp))
                        }

                        Column {
                            Text("Available Variables", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                            Text("Tap any variable to copy tag", fontSize = 11.sp, color = textSecondary)
                        }
                    }

                    // Variable Pills
                    Row(
                        modifier = Modifier.horizontalScroll(rememberScrollState()),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        availableVariables.forEach { tag ->
                            Surface(
                                onClick = {
                                    clipboardManager.setText(AnnotatedString(tag))
                                    Toast.makeText(context, "Copied $tag!", Toast.LENGTH_SHORT).show()
                                },
                                shape = RoundedCornerShape(20.dp),
                                color = cardBg,
                                border = BorderStroke(1.dp, goldPrimary)
                            ) {
                                Text(
                                    text = tag,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = goldText,
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun IntegrationItemCard(
    title: String,
    subtitle: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    iconBg: Color,
    iconTint: Color,
    enabled: Boolean,
    onToggle: (Boolean) -> Unit,
    isDark: Boolean
) {
    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0D1C2E)
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)

    Card(
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = cardBg),
        border = BorderStroke(1.dp, cardBorder),
        modifier = Modifier.fillMaxWidth()
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
                        .size(40.dp)
                        .clip(RoundedCornerShape(10.dp))
                        .background(iconBg),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(icon, null, tint = iconTint, modifier = Modifier.size(20.dp))
                }

                Column {
                    Text(title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    Text(subtitle, fontSize = 11.5.sp, color = textSecondary)
                }
            }

            Switch(
                checked = enabled,
                onCheckedChange = onToggle,
                colors = SwitchDefaults.colors(
                    checkedThumbColor = Color.White,
                    uncheckedThumbColor = Color.White,
                    checkedTrackColor = Color(0xFFFFC800)
                )
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB 4: FORM RESPONSES / SUBMISSIONS TAB (EXCEL TABLE & DATA ANALYSIS)
// ═══════════════════════════════════════════════════════════════════════════
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FormResponsesTab(
    viewModel: AppViewModel
) {
    val context = LocalContext.current
    val clipboardManager = LocalClipboardManager.current
    val isDark by viewModel.isDarkMode.collectAsState()
    val allSubmissions by viewModel.formSubmissions.collectAsState()
    val activeFormId by viewModel.activeFormId.collectAsState()
    val hostedForms by viewModel.hostedFormsList.collectAsState()
    val activeForm = remember(activeFormId, hostedForms) {
        hostedForms.find { it.id == activeFormId }
    }
    val activeFormSlug = activeForm?.slug.orEmpty()
    val formTitle = activeForm?.title?.ifBlank { "Hosted Form" } ?: "All Forms"
    var showAllForms by remember { mutableStateOf(false) }

    LaunchedEffect(hostedForms, activeFormId) {
        if (hostedForms.isNotEmpty() && hostedForms.none { it.id == activeFormId }) {
            viewModel.selectHostedForm(hostedForms.first().id)
        }
    }

    val submissions = remember(allSubmissions, activeFormId, activeFormSlug, showAllForms, activeForm) {
        if (showAllForms || activeForm == null || hostedForms.isEmpty()) {
            allSubmissions
        } else {
            allSubmissions.filter {
                val fId = it.optString("form_id")
                val fSlug = it.optString("form_slug")
                fId == activeFormId || (activeFormSlug.isNotEmpty() && (fSlug == activeFormSlug || fId == activeFormSlug))
            }
        }
    }
    LaunchedEffect(activeFormId) { viewModel.fetchFormSubmissions(activeFormId) }

    // UI State: Filters, Search, View Mode, Selection
    var searchQuery by remember { mutableStateOf("") }
    var statusFilter by remember { mutableStateOf("ALL") } // "ALL", "PAID", "PENDING", "FAILED"
    var viewMode by remember { mutableStateOf("table") } // "table" (Excel grid) or "cards"
    var selectedSubmission by remember { mutableStateOf<org.json.JSONObject?>(null) }

    // Theme palette
    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0D1C2E)
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val goldPrimary = if (isDark) Color(0xFFE5A93C) else Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)
    val goldDarkBg = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0)
    val tableHeaderBg = if (isDark) Color(0xFF1C1814) else Color(0xFFF1F5F9)
    val tableRowAltBg = if (isDark) Color(0xFF17130F) else Color(0xFFF8FAFC)
    val tableBorder = if (isDark) Color(0xFF261E14) else Color(0xFFE2E8F0)

    // Data Analysis Calculations
    val paidCount = remember(submissions) {
        submissions.count {
            val st = it.optString("payment_status", "").uppercase()
            st == "PAID" || st == "FREE" || st == "COMPLETED"
        }
    }
    val pendingCount = remember(submissions) {
        submissions.count {
            it.optString("payment_status", "PENDING").uppercase() == "PENDING"
        }
    }
    val failedCount = remember(submissions) {
        submissions.count {
            val st = it.optString("payment_status", "").uppercase()
            st == "FAILED" || st == "CANCELLED" || st == "EXPIRED"
        }
    }
    val totalRevenue = remember(submissions) {
        submissions.filter {
            val st = it.optString("payment_status", "").uppercase()
            st == "PAID" || st == "FREE" || st == "COMPLETED"
        }.sumOf { it.optDouble("amount_bdt", it.optDouble("amount", 0.0)) }
    }
    val conversionRate = remember(submissions, paidCount) {
        if (submissions.isNotEmpty()) (paidCount.toDouble() / submissions.size.toDouble()) * 100.0 else 0.0
    }

    // Filtered Submissions based on search and status
    val filteredSubmissions = remember(submissions, searchQuery, statusFilter) {
        submissions.filter { sub ->
            val st = sub.optString("payment_status", "PENDING").uppercase()
            val matchesStatus = when (statusFilter) {
                "PAID" -> (st == "PAID" || st == "FREE" || st == "COMPLETED")
                "PENDING" -> st == "PENDING"
                "FAILED" -> (st == "FAILED" || st == "CANCELLED" || st == "EXPIRED")
                else -> true
            }
            val matchesSearch = if (searchQuery.isBlank()) true else {
                val q = searchQuery.trim().lowercase()
                sub.optString("customer_name").lowercase().contains(q) ||
                sub.optString("customer_phone").lowercase().contains(q) ||
                sub.optString("customer_email").lowercase().contains(q) ||
                sub.optString("transaction_id").lowercase().contains(q) ||
                sub.optString("matched_trx_id").lowercase().contains(q) ||
                sub.optString("id").lowercase().contains(q)
            }
            matchesStatus && matchesSearch
        }
    }
    val filteredRevenue = remember(filteredSubmissions) {
        filteredSubmissions.filter {
            val st = it.optString("payment_status", "").uppercase()
            st == "PAID" || st == "FREE" || st == "COMPLETED"
        }.sumOf { it.optDouble("amount_bdt", it.optDouble("amount", 0.0)) }
    }

    // Helper: Print Native Submissions PDF Report
    fun printSubmissionsReportPdf() {
        try {
            val printManager = context.getSystemService(android.content.Context.PRINT_SERVICE) as? android.print.PrintManager
                ?: run {
                    Toast.makeText(context, "Print service is unavailable on this device", Toast.LENGTH_SHORT).show()
                    return
                }
            val webView = android.webkit.WebView(context).apply { settings.javaScriptEnabled = false }
            val rowsHtml = StringBuilder()
            filteredSubmissions.forEachIndexed { idx, sub ->
                val name = sub.optString("customer_name", "Anonymous")
                val contact = sub.optString("customer_phone", sub.optString("customer_email", "N/A"))
                val amt = sub.optDouble("amount_bdt", sub.optDouble("amount", 0.0))
                val st = sub.optString("payment_status", "PENDING").uppercase()
                val method = sub.optString("payment_method", "N/A")
                val trxId = sub.optString("transaction_id", sub.optString("matched_trx_id", "-"))
                val date = sub.optString("created_at", "").take(16).replace("T", " ")
                val statusColor = when (st) {
                    "PAID", "COMPLETED", "FREE" -> "#059669"
                    "PENDING" -> "#D97706"
                    else -> "#DC2626"
                }
                rowsHtml.append("""
                    <tr>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #E2E8F0; text-align: center; color: #64748B; font-size: 11px;">${idx + 1}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #E2E8F0; font-weight: 600; color: #0F172A;">${android.text.TextUtils.htmlEncode(name)}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #E2E8F0; color: #475569;">${android.text.TextUtils.htmlEncode(contact)}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #E2E8F0; font-weight: 700; color: #059669; text-align: right;">৳ ${"%,.0f".format(amt)}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #E2E8F0; text-align: center;">
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 700; color: white; background-color: $statusColor;">$st</span>
                        </td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #E2E8F0; color: #334155;">${android.text.TextUtils.htmlEncode(method)}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #E2E8F0; font-family: monospace; font-size: 11px; color: #475569;">${android.text.TextUtils.htmlEncode(trxId)}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #E2E8F0; color: #64748B; font-size: 11px;">$date</td>
                    </tr>
                """.trimIndent())
            }

            val html = """
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title>${android.text.TextUtils.htmlEncode(formTitle)} Report</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 24px; color: #0F172A; }
                        .header { border-bottom: 2px solid #E2E8F0; padding-bottom: 14px; margin-bottom: 16px; }
                        .title { font-size: 20px; font-weight: 800; color: #0F172A; margin: 0; }
                        .subtitle { font-size: 11.5px; color: #64748B; margin-top: 4px; }
                        .kpis { display: flex; gap: 12px; margin-bottom: 18px; }
                        .kpi-card { flex: 1; padding: 10px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; }
                        .kpi-label { font-size: 9.5px; font-weight: 700; color: #64748B; text-transform: uppercase; }
                        .kpi-val { font-size: 18px; font-weight: 800; color: #0F172A; margin-top: 2px; }
                        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
                        th { background-color: #F1F5F9; padding: 9px 10px; text-align: left; font-weight: 700; color: #334155; border-bottom: 2px solid #CBD5E1; }
                        tr:nth-child(even) { background-color: #F8FAFC; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1 class="title">${android.text.TextUtils.htmlEncode(formTitle)} — Submissions Report</h1>
                        <div class="subtitle">Generated on ${java.text.SimpleDateFormat("dd MMM yyyy, hh:mm a", java.util.Locale.US).format(java.util.Date())} • Total: ${filteredSubmissions.size} Submissions</div>
                    </div>
                    <div class="kpis">
                        <div class="kpi-card"><div class="kpi-label">Submissions</div><div class="kpi-val">${filteredSubmissions.size}</div></div>
                        <div class="kpi-card"><div class="kpi-label">Total Revenue</div><div class="kpi-val" style="color: #059669;">৳ ${"%,.0f".format(filteredRevenue)}</div></div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 30px;">#</th>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th style="text-align: right;">Amount</th>
                                <th style="text-align: center;">Status</th>
                                <th>Method</th>
                                <th>Trx ID</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            $rowsHtml
                        </tbody>
                    </table>
                </body>
                </html>
            """.trimIndent()

            webView.webViewClient = object : android.webkit.WebViewClient() {
                override fun onPageFinished(view: android.webkit.WebView?, url: String?) {
                    val printAdapter = webView.createPrintDocumentAdapter("Submissions_Report_${System.currentTimeMillis()}")
                    printManager.print("Submissions_Report", printAdapter, android.print.PrintAttributes.Builder().build())
                }
            }
            webView.loadDataWithBaseURL(null, html, "text/html", "UTF-8", null)
        } catch (e: Exception) {
            Toast.makeText(context, "Printing error: ${e.message}", Toast.LENGTH_SHORT).show()
        }
    }

    // Helper: Export CSV (Excel formatted with UTF-8 BOM)
    fun exportSubmissionsCsv() {
        if (filteredSubmissions.isEmpty()) {
            Toast.makeText(context, "No submissions to export", Toast.LENGTH_SHORT).show()
            return
        }
        val csv = StringBuilder("\uFEFF#,ID,Customer Name,Phone,Email,Amount BDT,Payment Status,Payment Method,Trx ID,Created At\n")
        filteredSubmissions.forEachIndexed { idx, sub ->
            val row = listOf(
                (idx + 1).toString(),
                sub.optString("id"),
                sub.optString("customer_name", "Anonymous"),
                sub.optString("customer_phone", ""),
                sub.optString("customer_email", ""),
                sub.optDouble("amount_bdt", sub.optDouble("amount", 0.0)).toString(),
                sub.optString("payment_status", "PENDING"),
                sub.optString("payment_method", "N/A"),
                sub.optString("transaction_id", sub.optString("matched_trx_id", "")),
                sub.optString("created_at", "")
            )
            csv.append(row.joinToString(",") { cell ->
                val clean = cell.replace("\"", "\"\"")
                "\"$clean\""
            }).append("\n")
        }
        val sendIntent = android.content.Intent().apply {
            action = android.content.Intent.ACTION_SEND
            putExtra(android.content.Intent.EXTRA_TEXT, csv.toString())
            type = "text/csv"
        }
        context.startActivity(android.content.Intent.createChooser(sendIntent, "Export $formTitle CSV"))
    }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
        contentPadding = PaddingValues(top = 14.dp, bottom = 32.dp)
    ) {
        // Form Selector Chips (if multiple forms exist)
        if (hostedForms.size > 1) {
            item {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    val allSelected = showAllForms
                    Surface(
                        onClick = { showAllForms = true },
                        shape = RoundedCornerShape(20.dp),
                        color = if (allSelected) goldDarkBg else cardBg,
                        border = BorderStroke(1.dp, if (allSelected) goldPrimary else cardBorder)
                    ) {
                        Text(
                            text = "All Forms (${allSubmissions.size})",
                            fontSize = 12.sp,
                            fontWeight = if (allSelected) FontWeight.Bold else FontWeight.Medium,
                            color = if (allSelected) goldText else textSecondary,
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                        )
                    }

                    hostedForms.forEach { form ->
                        val isSelected = !showAllForms && activeFormId == form.id
                        Surface(
                            onClick = {
                                showAllForms = false
                                viewModel.selectHostedForm(form.id)
                            },
                            shape = RoundedCornerShape(20.dp),
                            color = if (isSelected) goldDarkBg else cardBg,
                            border = BorderStroke(1.dp, if (isSelected) goldPrimary else cardBorder)
                        ) {
                            Text(
                                text = form.title.ifBlank { "Untitled" },
                                fontSize = 12.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSelected) goldText else textSecondary,
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }
                }
            }
        }

        // 1. DATA ANALYSIS & EXECUTIVE METRICS DASHBOARD
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = cardBg),
                border = BorderStroke(1.dp, cardBorder)
            ) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Text("📊", fontSize = 16.sp)
                            Text("Data Analysis & Summary", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = textPrimary)
                        }
                        Text(
                            text = "${filteredSubmissions.size} filtered",
                            fontSize = 11.5.sp,
                            color = textSecondary,
                            fontWeight = FontWeight.Medium
                        )
                    }

                    // 4 Metric KPI Cards
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        // Total Responses
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(12.dp))
                                .background(if (isDark) Color(0xFF1E1A14) else Color(0xFFF8FAFC))
                                .border(1.dp, cardBorder, RoundedCornerShape(12.dp))
                                .padding(10.dp)
                        ) {
                            Column {
                                Text("TOTAL RESPONSES", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                Spacer(Modifier.height(4.dp))
                                Text(submissions.size.toString(), fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = goldText)
                            }
                        }

                        // Total Revenue
                        Box(
                            modifier = Modifier
                                .weight(1.2f)
                                .clip(RoundedCornerShape(12.dp))
                                .background(if (isDark) Color(0xFF0F1E17) else Color(0xFFF0FDF4))
                                .border(1.dp, if (isDark) Color(0xFF164E33) else Color(0xFFBBF7D0), RoundedCornerShape(12.dp))
                                .padding(10.dp)
                        ) {
                            Column {
                                Text("TOTAL REVENUE", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = Color(0xFF16A34A))
                                Spacer(Modifier.height(4.dp))
                                Text("৳ ${"%,.0f".format(totalRevenue)}", fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF10B981))
                            }
                        }

                        // Conversion Rate
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(12.dp))
                                .background(if (isDark) Color(0xFF161E2E) else Color(0xFFEFF6FF))
                                .border(1.dp, if (isDark) Color(0xFF1E293B) else Color(0xFFBFDBFE), RoundedCornerShape(12.dp))
                                .padding(10.dp)
                        ) {
                            Column {
                                Text("PAID RATE", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = Color(0xFF2563EB))
                                Spacer(Modifier.height(4.dp))
                                Text("${"%.0f".format(conversionRate)}%", fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF3B82F6))
                            }
                        }

                        // Pending Orders
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(12.dp))
                                .background(if (isDark) Color(0xFF261D11) else Color(0xFFFFFBEB))
                                .border(1.dp, if (isDark) Color(0xFF452D12) else Color(0xFFFDE68A), RoundedCornerShape(12.dp))
                                .padding(10.dp)
                        ) {
                            Column {
                                Text("PENDING", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = Color(0xFFD97706))
                                Spacer(Modifier.height(4.dp))
                                Text(pendingCount.toString(), fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFFF59E0B))
                            }
                        }
                    }

                    // Interactive Filter Chips: ALL, PAID, PENDING, FAILED
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .horizontalScroll(rememberScrollState()),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        listOf(
                            "ALL" to "All (${submissions.size})",
                            "PAID" to "Paid ($paidCount)",
                            "PENDING" to "Pending ($pendingCount)",
                            "FAILED" to "Failed ($failedCount)"
                        ).forEach { (key, label) ->
                            val isSel = statusFilter == key
                            Surface(
                                onClick = { statusFilter = key },
                                shape = RoundedCornerShape(16.dp),
                                color = if (isSel) goldDarkBg else (if (isDark) Color(0xFF1E1A14) else Color(0xFFF1F5F9)),
                                border = BorderStroke(1.dp, if (isSel) goldPrimary else Color.Transparent)
                            ) {
                                Text(
                                    text = label,
                                    fontSize = 11.5.sp,
                                    fontWeight = if (isSel) FontWeight.Bold else FontWeight.Medium,
                                    color = if (isSel) goldText else textSecondary,
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                                )
                            }
                        }
                    }
                }
            }
        }

        // 2. SEARCH & TOOLBAR (Search, View Toggle, Print PDF, Export CSV, Refresh)
        item {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                // Search Input
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { searchQuery = it },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    placeholder = { Text("Search by customer, phone, trx id...", fontSize = 13.sp, color = textSecondary) },
                    leadingIcon = { Icon(Icons.Default.Search, null, tint = textSecondary, modifier = Modifier.size(18.dp)) },
                    trailingIcon = {
                        if (searchQuery.isNotBlank()) {
                            IconButton(onClick = { searchQuery = "" }) {
                                Icon(Icons.Default.Close, "Clear search", tint = textSecondary, modifier = Modifier.size(16.dp))
                            }
                        }
                    },
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = cardBg,
                        unfocusedContainerColor = cardBg,
                        focusedBorderColor = goldPrimary,
                        unfocusedBorderColor = cardBorder
                    ),
                    singleLine = true
                )

                // Toolbar: View mode buttons + Action buttons (Print, CSV, Refresh)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // View Mode Switcher: Excel Table vs Cards
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isDark) Color(0xFF1C1814) else Color(0xFFE2E8F0))
                            .padding(2.dp),
                        horizontalArrangement = Arrangement.spacedBy(2.dp)
                    ) {
                        Surface(
                            onClick = { viewMode = "table" },
                            shape = RoundedCornerShape(8.dp),
                            color = if (viewMode == "table") cardBg else Color.Transparent,
                            border = if (viewMode == "table") BorderStroke(1.dp, cardBorder) else null
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Icon(Icons.Default.TableChart, null, modifier = Modifier.size(15.dp), tint = if (viewMode == "table") goldText else textSecondary)
                                Text("Excel Grid", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = if (viewMode == "table") textPrimary else textSecondary)
                            }
                        }

                        Surface(
                            onClick = { viewMode = "cards" },
                            shape = RoundedCornerShape(8.dp),
                            color = if (viewMode == "cards") cardBg else Color.Transparent,
                            border = if (viewMode == "cards") BorderStroke(1.dp, cardBorder) else null
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Icon(Icons.Default.ViewAgenda, null, modifier = Modifier.size(15.dp), tint = if (viewMode == "cards") goldText else textSecondary)
                                Text("Cards", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = if (viewMode == "cards") textPrimary else textSecondary)
                            }
                        }
                    }

                    // Action buttons: Print PDF, CSV, Refresh
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                        // Print PDF Report Button
                        Surface(
                            onClick = { printSubmissionsReportPdf() },
                            shape = RoundedCornerShape(10.dp),
                            color = if (isDark) Color(0xFF1F2430) else Color(0xFFEFF6FF),
                            border = BorderStroke(1.dp, if (isDark) Color(0xFF2563EB) else Color(0xFFBFDBFE))
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Icon(Icons.Default.Print, null, modifier = Modifier.size(15.dp), tint = Color(0xFF2563EB))
                                Text("Print PDF", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFF2563EB))
                            }
                        }

                        // Export CSV Button
                        Surface(
                            onClick = { exportSubmissionsCsv() },
                            shape = RoundedCornerShape(10.dp),
                            color = goldDarkBg,
                            border = BorderStroke(1.dp, goldPrimary)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Icon(Icons.Outlined.FileDownload, null, modifier = Modifier.size(15.dp), tint = goldText)
                                Text("CSV", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = goldText)
                            }
                        }

                        // Refresh Button
                        IconButton(
                            onClick = { viewModel.fetchFormSubmissions(activeFormId) },
                            modifier = Modifier.size(32.dp)
                        ) {
                            Icon(Icons.Outlined.Refresh, "Refresh", tint = textSecondary, modifier = Modifier.size(18.dp))
                        }
                    }
                }
            }
        }

        if (filteredSubmissions.isEmpty()) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(32.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Text("📋", fontSize = 36.sp)
                        Text("No submissions match the current filter", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                        Text("Try selecting 'ALL' status or clearing your search keywords.", fontSize = 12.sp, color = textSecondary, textAlign = TextAlign.Center)
                    }
                }
            }
        }

        // 3. EXCEL SPREADSHEET TABLE GRID VIEW
        if (viewMode == "table" && filteredSubmissions.isNotEmpty()) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder)
                ) {
                    Column {
                        // Horizontally scrollable Excel table container
                        Box(modifier = Modifier.horizontalScroll(rememberScrollState())) {
                            Column {
                                // Sticky Excel Header Row
                                Row(
                                    modifier = Modifier
                                        .background(tableHeaderBg)
                                        .border(BorderStroke(1.dp, tableBorder))
                                        .padding(vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("#", modifier = Modifier.width(36.dp), textAlign = TextAlign.Center, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("CUSTOMER", modifier = Modifier.width(140.dp).padding(horizontal = 8.dp), fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("CONTACT", modifier = Modifier.width(130.dp).padding(horizontal = 8.dp), fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("AMOUNT", modifier = Modifier.width(90.dp).padding(horizontal = 8.dp), textAlign = TextAlign.End, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("STATUS", modifier = Modifier.width(90.dp), textAlign = TextAlign.Center, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("METHOD", modifier = Modifier.width(80.dp).padding(horizontal = 8.dp), textAlign = TextAlign.Center, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("TRX ID", modifier = Modifier.width(110.dp).padding(horizontal = 8.dp), fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("DATE", modifier = Modifier.width(105.dp).padding(horizontal = 8.dp), fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("ACTION", modifier = Modifier.width(70.dp), textAlign = TextAlign.Center, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                }

                                // Excel Table Data Rows
                                filteredSubmissions.forEachIndexed { index, sub ->
                                    val rowBg = if (index % 2 == 1) tableRowAltBg else cardBg
                                    val name = sub.optString("customer_name", "Anonymous")
                                    val contact = sub.optString("customer_phone", sub.optString("customer_email", "-"))
                                    val amt = sub.optDouble("amount_bdt", sub.optDouble("amount", 0.0))
                                    val st = sub.optString("payment_status", "PENDING").uppercase()
                                    val method = sub.optString("payment_method", "-")
                                    val trxId = sub.optString("transaction_id", sub.optString("matched_trx_id", "-"))
                                    val date = sub.optString("created_at", "").take(16).replace("T", " ")

                                    val statusBg = when (st) {
                                        "PAID", "COMPLETED", "FREE" -> Color(0xFF059669)
                                        "PENDING" -> Color(0xFFD97706)
                                        else -> Color(0xFFDC2626)
                                    }

                                    Row(
                                        modifier = Modifier
                                            .background(rowBg)
                                            .border(BorderStroke(0.5.dp, tableBorder))
                                            .clickable { selectedSubmission = sub }
                                            .padding(vertical = 9.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("${index + 1}", modifier = Modifier.width(36.dp), textAlign = TextAlign.Center, fontSize = 11.sp, color = textSecondary)
                                        Box(modifier = Modifier.width(1.dp).height(14.dp).background(tableBorder))

                                        Text(name, modifier = Modifier.width(140.dp).padding(horizontal = 8.dp), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = textPrimary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                        Box(modifier = Modifier.width(1.dp).height(14.dp).background(tableBorder))

                                        Text(contact, modifier = Modifier.width(130.dp).padding(horizontal = 8.dp), fontSize = 11.sp, color = textSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                        Box(modifier = Modifier.width(1.dp).height(14.dp).background(tableBorder))

                                        Text("৳ ${"%,.0f".format(amt)}", modifier = Modifier.width(90.dp).padding(horizontal = 8.dp), textAlign = TextAlign.End, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                                        Box(modifier = Modifier.width(1.dp).height(14.dp).background(tableBorder))

                                        Box(modifier = Modifier.width(90.dp), contentAlignment = Alignment.Center) {
                                            Surface(
                                                shape = RoundedCornerShape(9999.dp),
                                                color = statusBg.copy(alpha = 0.15f)
                                            ) {
                                                Text(st, fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = statusBg, modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp))
                                            }
                                        }
                                        Box(modifier = Modifier.width(1.dp).height(14.dp).background(tableBorder))

                                        Text(method, modifier = Modifier.width(80.dp).padding(horizontal = 8.dp), textAlign = TextAlign.Center, fontSize = 11.sp, color = textPrimary, maxLines = 1)
                                        Box(modifier = Modifier.width(1.dp).height(14.dp).background(tableBorder))

                                        Text(trxId, modifier = Modifier.width(110.dp).padding(horizontal = 8.dp), fontSize = 11.sp, fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace, color = textSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                        Box(modifier = Modifier.width(1.dp).height(14.dp).background(tableBorder))

                                        Text(date, modifier = Modifier.width(105.dp).padding(horizontal = 8.dp), fontSize = 10.5.sp, color = textSecondary, maxLines = 1)
                                        Box(modifier = Modifier.width(1.dp).height(14.dp).background(tableBorder))

                                        Box(modifier = Modifier.width(70.dp), contentAlignment = Alignment.Center) {
                                            Surface(
                                                onClick = { selectedSubmission = sub },
                                                shape = RoundedCornerShape(6.dp),
                                                color = goldDarkBg,
                                                border = BorderStroke(1.dp, goldPrimary)
                                            ) {
                                                Text("View", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = goldText, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
                                            }
                                        }
                                    }
                                }

                                // Excel Footer Row (=SUM Totals)
                                Row(
                                    modifier = Modifier
                                        .background(tableHeaderBg)
                                        .border(BorderStroke(1.dp, tableBorder))
                                        .padding(vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("∑", modifier = Modifier.width(36.dp), textAlign = TextAlign.Center, fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("Total (${filteredSubmissions.size} items)", modifier = Modifier.width(271.dp).padding(horizontal = 8.dp), fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("৳ ${"%,.0f".format(filteredRevenue)}", modifier = Modifier.width(90.dp).padding(horizontal = 8.dp), textAlign = TextAlign.End, fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF10B981))
                                    Box(modifier = Modifier.width(1.dp).height(16.dp).background(tableBorder))
                                    Text("", modifier = Modifier.width(455.dp))
                                }
                            }
                        }
                    }
                }
            }
        }

        // 4. CARDS VIEW (when viewMode == "cards")
        if (viewMode == "cards") {
            items(filteredSubmissions) { submission ->
                val name = submission.optString("customer_name", "Anonymous")
                val desc = submission.optString("customer_phone", submission.optString("customer_email", "N/A"))
                val amt = submission.optDouble("amount_bdt", submission.optDouble("amount", 0.0))
                val st = submission.optString("payment_status", "PENDING").uppercase()
                val method = submission.optString("payment_method", "N/A")
                val trxId = submission.optString("transaction_id", submission.optString("matched_trx_id", ""))
                val statusColor = when (st) {
                    "PAID", "COMPLETED", "FREE" -> Color(0xFF10B981)
                    "PENDING" -> Color(0xFFF59E0B)
                    else -> Color(0xFFEF4444)
                }

                Card(
                    onClick = { selectedSubmission = submission },
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = cardBg),
                    border = BorderStroke(1.dp, cardBorder),
                    modifier = Modifier.fillMaxWidth()
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
                                    .size(42.dp)
                                    .clip(CircleShape)
                                    .background(goldDarkBg),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = name.take(1).uppercase(),
                                    fontSize = 18.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = goldText
                                )
                            }

                            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                Text(name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                Text(desc, fontSize = 12.sp, color = textSecondary)
                                Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Text("৳ ${"%,.0f".format(amt)}", fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF10B981))
                                    Text("•", fontSize = 10.sp, color = textSecondary)
                                    Surface(shape = RoundedCornerShape(4.dp), color = statusColor.copy(alpha = 0.15f)) {
                                        Text(st, fontSize = 9.sp, fontWeight = FontWeight.Bold, color = statusColor, modifier = Modifier.padding(horizontal = 6.dp, vertical = 1.dp))
                                    }
                                    Text("via $method", fontSize = 11.sp, color = textSecondary)
                                }
                            }
                        }

                        Icon(Icons.Default.ChevronRight, null, tint = textSecondary, modifier = Modifier.size(20.dp))
                    }
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PROFESSIONAL STRUCTURED INSPECTION MODAL (NO RAW JSON DUMP)
    // ═══════════════════════════════════════════════════════════════════════════
    selectedSubmission?.let { submission ->
        val name = submission.optString("customer_name", "Anonymous")
        val phone = submission.optString("customer_phone", "")
        val email = submission.optString("customer_email", "")
        val amt = submission.optDouble("amount_bdt", submission.optDouble("amount", 0.0))
        val st = submission.optString("payment_status", "PENDING").uppercase()
        val method = submission.optString("payment_method", "N/A")
        val trxId = submission.optString("transaction_id", submission.optString("matched_trx_id", "N/A"))
        val subId = submission.optString("id", "")
        val orderId = submission.optString("order_id", "")
        val createdAt = submission.optString("created_at", "").take(19).replace("T", " ")

        val answers = submission.optJSONObject("answers") ?: org.json.JSONObject()
        val answersList = remember(answers) {
            val list = mutableListOf<Pair<String, String>>()
            val keys = answers.keys()
            while (keys.hasNext()) {
                val k = keys.next()
                val raw = answers.opt(k)
                val str = when (raw) {
                    is org.json.JSONObject -> {
                        if (raw.has("file_name")) "📎 ${raw.optString("file_name")}"
                        else raw.toString()
                    }
                    is org.json.JSONArray -> (0 until raw.length()).map { raw.optString(it) }.joinToString(", ")
                    else -> raw?.toString() ?: ""
                }
                list.add(k to str)
            }
            list
        }

        val attachments = remember(answers) {
            val list = mutableListOf<Pair<String, org.json.JSONObject>>()
            val keys = answers.keys()
            while (keys.hasNext()) {
                val fieldId = keys.next()
                val meta = answers.optJSONObject(fieldId) ?: continue
                if (meta.optString("object_path").isNotBlank() || meta.has("file_name")) {
                    list.add(fieldId to meta)
                }
            }
            list
        }

        val statusColor = when (st) {
            "PAID", "COMPLETED", "FREE" -> Color(0xFF10B981)
            "PENDING" -> Color(0xFFF59E0B)
            else -> Color(0xFFEF4444)
        }

        // Print Single Receipt PDF
        fun printReceiptPdf() {
            try {
                val printManager = context.getSystemService(android.content.Context.PRINT_SERVICE) as? android.print.PrintManager
                    ?: run {
                        Toast.makeText(context, "Print service unavailable", Toast.LENGTH_SHORT).show()
                        return
                    }
                val webView = android.webkit.WebView(context)
                val answerRowsHtml = StringBuilder()
                answersList.forEach { (q, a) ->
                    answerRowsHtml.append("""
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #E2E8F0; font-weight: 600; color: #475569; width: 35%;">${android.text.TextUtils.htmlEncode(q)}</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #E2E8F0; color: #0F172A;">${android.text.TextUtils.htmlEncode(a)}</td>
                        </tr>
                    """.trimIndent())
                }

                val html = """
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="utf-8">
                        <title>Receipt - $subId</title>
                        <style>
                            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 30px; color: #0F172A; }
                            .card { max-width: 600px; margin: 0 auto; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; }
                            .header { display: flex; justify-content: space-between; border-bottom: 2px solid #E2E8F0; padding-bottom: 14px; margin-bottom: 16px; }
                            .title { font-size: 20px; font-weight: 800; color: #0F172A; margin: 0; }
                            .sub { font-size: 11px; color: #64748B; margin-top: 4px; }
                            .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; color: white; }
                            .sec-title { font-size: 11.5px; font-weight: 700; color: #64748B; text-transform: uppercase; margin: 16px 0 8px 0; }
                            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                            .item-box { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px 12px; }
                            .item-label { font-size: 10px; color: #64748B; }
                            .item-val { font-size: 13.5px; font-weight: 700; color: #0F172A; margin-top: 2px; }
                            table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 6px; }
                        </style>
                    </head>
                    <body>
                        <div class="card">
                            <div class="header">
                                <div>
                                    <h1 class="title">${android.text.TextUtils.htmlEncode(formTitle)}</h1>
                                    <div class="sub">Receipt ID: $subId • $createdAt</div>
                                </div>
                                <div>
                                    <span class="badge" style="background: ${if (st == "PAID") "#059669" else "#D97706"};">$st</span>
                                </div>
                            </div>
                            <div class="sec-title">Customer Information</div>
                            <div class="grid">
                                <div class="item-box"><div class="item-label">Name</div><div class="item-val">${android.text.TextUtils.htmlEncode(name)}</div></div>
                                <div class="item-box"><div class="item-label">Phone</div><div class="item-val">${android.text.TextUtils.htmlEncode(phone.ifBlank { "N/A" })}</div></div>
                                <div class="item-box"><div class="item-label">Email</div><div class="item-val">${android.text.TextUtils.htmlEncode(email.ifBlank { "N/A" })}</div></div>
                                <div class="item-box"><div class="item-label">Amount Paid</div><div class="item-val" style="color: #059669;">৳ ${"%,.0f".format(amt)} ($method)</div></div>
                            </div>
                            <div class="sec-title">Payment & Transaction</div>
                            <div class="grid">
                                <div class="item-box"><div class="item-label">TrxID</div><div class="item-val" style="font-family: monospace;">$trxId</div></div>
                                <div class="item-box"><div class="item-label">Order ID</div><div class="item-val" style="font-family: monospace;">${orderId.ifBlank { "N/A" }}</div></div>
                            </div>
                            <div class="sec-title">Submitted Questionnaire Answers</div>
                            <table>
                                <tbody>
                                    $answerRowsHtml
                                </tbody>
                            </table>
                        </div>
                    </body>
                    </html>
                """.trimIndent()

                webView.webViewClient = object : android.webkit.WebViewClient() {
                    override fun onPageFinished(view: android.webkit.WebView?, url: String?) {
                        val printAdapter = webView.createPrintDocumentAdapter("Receipt_${subId.take(8)}")
                        printManager.print("Receipt_${subId.take(8)}", printAdapter, android.print.PrintAttributes.Builder().build())
                    }
                }
                webView.loadDataWithBaseURL(null, html, "text/html", "UTF-8", null)
            } catch (e: Exception) {
                Toast.makeText(context, "Printing error: ${e.message}", Toast.LENGTH_SHORT).show()
            }
        }

        AlertDialog(
            onDismissRequest = { selectedSubmission = null },
            modifier = Modifier.fillMaxWidth(0.96f),
            shape = RoundedCornerShape(20.dp),
            containerColor = cardBg,
            title = {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text("Submission Details", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = textPrimary)
                        Text(createdAt, fontSize = 11.sp, color = textSecondary)
                    }
                    Surface(
                        shape = RoundedCornerShape(9999.dp),
                        color = statusColor.copy(alpha = 0.15f)
                    ) {
                        Text(st, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = statusColor, modifier = Modifier.padding(horizontal = 10.dp, vertical = 3.dp))
                    }
                }
            },
            text = {
                Column(
                    modifier = Modifier
                        .verticalScroll(rememberScrollState())
                        .padding(top = 4.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    // 1. Customer Details Card with direct Call / Email actions
                    Card(
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1C1814) else Color(0xFFF8FAFC)),
                        border = BorderStroke(1.dp, cardBorder),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("👤 CUSTOMER INFORMATION", fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                    if (phone.isNotBlank()) Text("📞 $phone", fontSize = 12.sp, color = textSecondary)
                                    if (email.isNotBlank()) Text("✉️ $email", fontSize = 12.sp, color = textSecondary)
                                }

                                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                    if (phone.isNotBlank()) {
                                        IconButton(
                                            onClick = {
                                                try {
                                                    val intent = android.content.Intent(android.content.Intent.ACTION_DIAL, android.net.Uri.parse("tel:$phone"))
                                                    context.startActivity(intent)
                                                } catch (_: Exception) {}
                                            },
                                            modifier = Modifier.size(34.dp)
                                        ) {
                                            Icon(Icons.Default.Phone, "Call", tint = Color(0xFF10B981), modifier = Modifier.size(18.dp))
                                        }
                                    }
                                    if (email.isNotBlank()) {
                                        IconButton(
                                            onClick = {
                                                try {
                                                    val intent = android.content.Intent(android.content.Intent.ACTION_SENDTO, android.net.Uri.parse("mailto:$email"))
                                                    context.startActivity(intent)
                                                } catch (_: Exception) {}
                                            },
                                            modifier = Modifier.size(34.dp)
                                        ) {
                                            Icon(Icons.Default.Email, "Email", tint = Color(0xFF3B82F6), modifier = Modifier.size(18.dp))
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // 2. Payment & Transaction Card
                    Card(
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1C1814) else Color(0xFFF8FAFC)),
                        border = BorderStroke(1.dp, cardBorder),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("💳 PAYMENT & TRANSACTION", fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text("Amount Collected", fontSize = 10.5.sp, color = textSecondary)
                                    Text("৳ ${"%,.0f".format(amt)}", fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF10B981))
                                }
                                Surface(shape = RoundedCornerShape(8.dp), color = goldDarkBg, border = BorderStroke(1.dp, goldPrimary)) {
                                    Text(method, fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = goldText, modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp))
                                }
                            }

                            if (trxId != "N/A" && trxId != "-") {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(if (isDark) Color(0xFF13100C) else Color.White)
                                        .border(1.dp, cardBorder, RoundedCornerShape(8.dp))
                                        .padding(horizontal = 10.dp, vertical = 6.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("TRANSACTION ID (TRXID)", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = textSecondary)
                                        Text(trxId, fontSize = 12.sp, fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace, fontWeight = FontWeight.Bold, color = textPrimary)
                                    }
                                    IconButton(
                                        onClick = {
                                            clipboardManager.setText(AnnotatedString(trxId))
                                            Toast.makeText(context, "TrxID copied to clipboard", Toast.LENGTH_SHORT).show()
                                        },
                                        modifier = Modifier.size(28.dp)
                                    ) {
                                        Icon(Icons.Default.ContentCopy, "Copy TrxID", tint = textSecondary, modifier = Modifier.size(15.dp))
                                    }
                                }
                            }
                        }
                    }

                    // 3. Structured Questionnaire Answers Table (NO RAW JSON!)
                    if (answersList.isNotEmpty()) {
                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Text("📝 QUESTIONNAIRE ANSWERS", fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)

                            Card(
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = if (isDark) Color(0xFF1C1814) else Color(0xFFF8FAFC)),
                                border = BorderStroke(1.dp, cardBorder),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column {
                                    answersList.forEachIndexed { i, (question, answer) ->
                                        Row(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .padding(horizontal = 12.dp, vertical = 8.dp),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.Top
                                        ) {
                                            Text(
                                                text = question,
                                                fontSize = 11.5.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = textSecondary,
                                                modifier = Modifier.weight(1.2f).padding(end = 8.dp)
                                            )
                                            Text(
                                                text = answer.ifBlank { "-" },
                                                fontSize = 12.sp,
                                                fontWeight = FontWeight.Medium,
                                                color = textPrimary,
                                                modifier = Modifier.weight(1.8f)
                                            )
                                        }
                                        if (i < answersList.size - 1) {
                                            HorizontalDivider(color = cardBorder, thickness = 0.5.dp)
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // 4. Attachments Section (if files were uploaded)
                    if (attachments.isNotEmpty()) {
                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Text("📎 PRIVATE ATTACHMENTS", fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = textSecondary)
                            attachments.forEach { (fieldId, metadata) ->
                                val fileName = metadata.optString("file_name", "Open attachment")
                                OutlinedButton(
                                    onClick = { viewModel.openHostedFormAttachment(context, subId, fieldId) },
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(10.dp),
                                    border = BorderStroke(1.dp, cardBorder)
                                ) {
                                    Icon(Icons.Outlined.FileDownload, null, modifier = Modifier.size(16.dp), tint = textPrimary)
                                    Spacer(Modifier.width(8.dp))
                                    Text(fileName, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = textPrimary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                }
                            }
                        }
                    }
                }
            },
            confirmButton = {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(
                        onClick = { printReceiptPdf() },
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2563EB))
                    ) {
                        Icon(Icons.Default.Print, null, modifier = Modifier.size(15.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("Print PDF", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }

                    OutlinedButton(
                        onClick = { selectedSubmission = null },
                        shape = RoundedCornerShape(10.dp),
                        border = BorderStroke(1.dp, cardBorder)
                    ) {
                        Text("Close", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    }
                }
            }
        )
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// PUBLISH FLOW MODAL
// ═══════════════════════════════════════════════════════════════════════════
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PublishFlowModal(
    viewModel: AppViewModel,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    val clipboardManager = LocalClipboardManager.current
    val isDark by viewModel.isDarkMode.collectAsState()

    val cardBg = if (isDark) Color(0xFF13100C) else Color.White
    val cardBorder = if (isDark) Color(0xFF2C2213) else Color(0xFFE2E8F0)
    val textPrimary = if (isDark) Color.White else Color(0xFF0D1C2E)
    val textSecondary = if (isDark) Color(0xFF9CA3AF) else Color(0xFF585E6C)
    val goldPrimary = if (isDark) Color(0xFFE5A93C) else Color(0xFFFFC800)
    val goldText = if (isDark) Color(0xFFFACC15) else Color(0xFF705D00)
    val goldDarkBg = if (isDark) Color(0xFF221A0C) else Color(0xFFFFFDF0)

    EnterpriseGestureModal(
        onDismissRequest = onDismiss,
        title = "Publish Form",
        subtitle = "Share your form with the world or embed on website",
        icon = Icons.Outlined.RocketLaunch
    ) {
        Column(
            verticalArrangement = Arrangement.spacedBy(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Hero Circular Illustration Box
            Box(
                modifier = Modifier
                    .size(110.dp)
                    .clip(CircleShape)
                    .background(goldDarkBg)
                    .border(BorderStroke(2.dp, goldPrimary), CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Outlined.Send,
                    contentDescription = null,
                    tint = goldText,
                    modifier = Modifier.size(48.dp)
                )
            }

            Text(
                text = "Publish Your Form",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = textPrimary
            )

            // Publish Options List
            listOf(
                Triple("Share Link", "Get a shareable link to your form", Icons.Outlined.Link),
                Triple("Embed Code", "Add form to your website HTML", Icons.Outlined.Code),
                Triple("QR Link", "Copy the hosted URL for a QR generator", Icons.Outlined.QrCode)
            ).forEach { (optTitle, optSub, optIcon) ->
                Card(
                    onClick = {
                        viewModel.saveActiveFormToHostedList()
                        val form = viewModel.hostedFormsList.value.find { it.id == viewModel.activeFormId.value }
                        if (form != null) {
                            viewModel.registerBrandedHostedFormRoute(form)
                        }
                        val shareUrl = viewModel.hostedFormPublicUrl()
                        if (shareUrl.isBlank()) {
                            Toast.makeText(context, "Unable to generate share link. Check form settings.", Toast.LENGTH_LONG).show()
                            return@Card
                        }
                        when (optTitle) {
                            "Share Link" -> {
                                val intent = android.content.Intent(android.content.Intent.ACTION_SEND).apply {
                                    type = "text/plain"
                                    putExtra(android.content.Intent.EXTRA_SUBJECT, "Payment form")
                                    putExtra(android.content.Intent.EXTRA_TEXT, shareUrl)
                                }
                                context.start
... [truncated for diff preview]