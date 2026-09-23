// SwapnoPay Backend — Real Gemini AI Form Generator
// POST /v1/ai/generate-form
//   Body: { prompt, feedback?, merchant_id, current_form? }
//   Returns: { success: true, form: { title, description, template_key, theme, pages, custom_variables } }
//            OR { success: false, fallback: true } if Gemini unavailable

import { Router } from 'express'
import { getAdminClient } from '../services/adminSupabase.js'

export const aiFormRouter = Router()

// ── Gemini model cascade (fastest first) ──────────────────────────────────────
const GEMINI_MODELS = [
  'gemini-2.0-flash',
  'gemini-2.0-flash-lite',
  'gemini-1.5-flash',
  'gemini-1.5-flash-8b',
  'gemini-1.5-pro'
]

// ── Fetch merchant's stored Gemini key from aiVoice settings ──────────────────
async function getMerchantGeminiKey(merchantId) {
  if (!merchantId) return null
  try {
    const admin = getAdminClient()
    if (!admin) return null
    const { data } = await admin
      .from('ai_voice_configs')
      .select('gemini_api_key')
      .eq('merchant_id', merchantId)
      .maybeSingle()
    return data?.gemini_api_key || null
  } catch {
    return null
  }
}

// ── Call Gemini with multi-model fallback ─────────────────────────────────────
async function callGemini(apiKey, systemPrompt, userMessage, preferredModel = null) {
  if (!apiKey) return null
  const contents = [
    { role: 'user', parts: [{ text: systemPrompt + '\n\n---\n\n' + userMessage }] }
  ]
  const generationConfig = {
    temperature: 0.7,
    maxOutputTokens: 8192,
    responseMimeType: 'application/json'
  }
  const models = preferredModel && GEMINI_MODELS.includes(preferredModel)
    ? [preferredModel, ...GEMINI_MODELS.filter(m => m !== preferredModel)]
    : (preferredModel ? [preferredModel, ...GEMINI_MODELS] : GEMINI_MODELS)

  for (const model of models) {
    try {
      const url = `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${apiKey}`
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contents, generationConfig })
      })
      if (response.ok) {
        const data = await response.json()
        let text = data?.candidates?.[0]?.content?.parts?.[0]?.text?.trim()
        if (text) {
          // Strip markdown fences if present
          text = text.replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/```\s*$/i, '').trim()
          return text
        }
      } else {
        const errBody = await response.text()
        console.warn(`[aiForm] Gemini ${model} HTTP ${response.status}:`, errBody.slice(0, 200))
      }
    } catch (e) {
      console.warn(`[aiForm] Gemini ${model} error:`, e.message)
    }
  }
  return null
}

// ── Call OpenRouter if configured by merchant ─────────────────────────────────
async function callOpenRouter(apiKey, systemPrompt, userMessage, model = 'google/gemini-2.0-flash-001') {
  if (!apiKey) return null
  try {
    const res = await fetch('https://openrouter.ai/api/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json',
        'HTTP-Referer': 'https://swapnopay.top',
        'X-Title': 'SwapnoPay Form Builder'
      },
      body: JSON.stringify({
        model: model || 'google/gemini-2.0-flash-001',
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: userMessage }
        ]
      })
    })
    if (res.ok) {
      const data = await res.json()
      let text = data?.choices?.[0]?.message?.content?.trim()
      if (text) {
        text = text.replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/```\s*$/i, '').trim()
        return text
      }
    } else {
      const err = await res.text()
      console.warn('[aiForm] OpenRouter HTTP', res.status, err.slice(0, 160))
    }
  } catch (e) {
    console.warn('[aiForm] OpenRouter call error:', e.message)
  }
  return null
}

// ── Full schema-aware system prompt ───────────────────────────────────────────
const SYSTEM_PROMPT = `You are an expert payment form designer for SwapnoPay — a Bangladeshi merchant payment platform.
Your job is to generate a COMPLETE, PRODUCTION-READY payment form as a single JSON object.

IMPORTANT RULES:
1. Always respond with ONLY valid JSON — no markdown, no explanation, no code fences
2. Generate real, contextually appropriate content (not placeholder text)
3. Support multi-page forms for complex use cases
4. Add custom HTML/CSS for thank-you pages when appropriate
5. Choose colors that match the form's mood and industry
6. Use Bengali text where appropriate (labels can be bilingual)
7. Only include field types from the allowed list below

ALLOWED FIELD TYPES (use these exact strings):
TEXT:    NAME, EMAIL, PHONE, ADDRESS, COMPANY, WEBSITE, NOTES, DATE
CHOICE:  DROPDOWN, RADIO, CHECKBOX, MULTI_SELECT, TOGGLE
MEDIA:   FILE_UPLOAD, CAMERA_UPLOAD, IMAGE, MEDIA_IMAGE
PAYMENT: PRODUCT, PRODUCT_LIST, QUANTITY, COUPON, DISCOUNT, SHIPPING, TAX, TIP, DONATION, CUSTOM_AMOUNT

REQUIRED JSON SCHEMA (return exactly this structure):
{
  "title": "Form title (concise, under 60 chars)",
  "description": "1-2 sentence description of the form",
  "template_key": "EVENT | EDUCATION | DONATION | APPOINTMENT | DIGITAL | CART | SINGLE_PRODUCT | MULTI_PRODUCT",
  "theme": {
    "primaryColorHex": "#RRGGBB",
    "backgroundColorHex": "#RRGGBB",
    "buttonShape": "PILL | ROUNDED | SQUARE",
    "fontFamily": "Inter | Roboto | Poppins",
    "isDarkMode": false,
    "showHeader": true,
    "backgroundStyle": "SOLID | GRADIENT",
    "gradientColorStart": "#RRGGBB",
    "gradientColorEnd": "#RRGGBB",
    "enablePayment": true,
    "currencyCode": "BDT",
    "redirectType": "SUCCESS_MSG",
    "successMessage": "Localized success message for the user (can be Bengali)",
    "enableAntiSpam": true,
    "isMultiPageForm": true,
    "progressTrackerStyle": "BAR | NUMBER | HIDE",
    "customCss": "",
    "customJs": "",
    "enableCustomJs": false
  },
  "pages": [
    {
      "title": "Page title",
      "subtitle": "Page subtitle or instruction",
      "isCustomHtml": false,
      "fields": [
        {
          "type": "FIELD_TYPE",
          "label": "Field label (can include Bengali)",
          "placeholder": "Helpful placeholder text",
          "helperText": "Optional helper text below field",
          "isRequired": true,
          "options": ["Option 1", "Option 2"],
          "defaultValue": ""
        }
      ],
      "customHtmlContent": "",
      "customCssContent": ""
    }
  ],
  "custom_variables": [
    { "key": "variable_name", "exampleValue": "example value", "source": "field" }
  ]
}

DESIGN GUIDELINES:
- Event/Conference: purple/indigo palette, PILL buttons, multi-page (attendee info → ticket → payment → thank-you HTML page)
- Education/Courses: violet/blue palette, ROUNDED buttons
- Donation/Charity: green/teal palette, PILL buttons, include donation tiers with BDT amounts
- E-commerce/Cart: blue/emerald palette, multi-page (products → shipping → payment)
- Appointments: indigo palette, include time slot dropdown
- Digital Products: amber/orange palette, always include EMAIL field for delivery
- For thank-you pages: use isCustomHtml=true with a styled HTML div (inline CSS, emoji, Bengali congratulations)

VARIABLE ASSIGNMENT: Auto-create custom_variables for important fields like name, phone, amount, product, order_id, etc.
Map them as: { "key": "customer_name", "exampleValue": "Mohammed Karim", "source": "field" }`

// ── Parse and validate Gemini's JSON output ───────────────────────────────────
function parseAndValidateFormJson(raw) {
  if (!raw) return null
  let parsed
  try {
    parsed = JSON.parse(raw)
  } catch {
    // Try to extract JSON from the text
    const match = raw.match(/\{[\s\S]*\}/)
    if (!match) return null
    try { parsed = JSON.parse(match[0]) } catch { return null }
  }

  // Ensure minimum required structure
  if (!parsed || typeof parsed !== 'object') return null
  if (!parsed.title || !parsed.pages || !Array.isArray(parsed.pages)) return null
  if (parsed.pages.length === 0) return null

  // Validate each page has fields or isCustomHtml
  for (const page of parsed.pages) {
    if (!page.title) page.title = 'Page'
    if (!page.subtitle) page.subtitle = ''
    if (page.isCustomHtml) {
      page.fields = []
    } else {
      if (!Array.isArray(page.fields)) page.fields = []
      // Ensure each field has required properties
      page.fields = page.fields.map(f => ({
        type: f.type || 'NAME',
        label: f.label || 'Field',
        placeholder: f.placeholder || '',
        helperText: f.helperText || '',
        isRequired: f.isRequired !== false,
        options: Array.isArray(f.options) ? f.options : [],
        defaultValue: f.defaultValue || ''
      }))
    }
    if (!page.customHtmlContent) page.customHtmlContent = ''
    if (!page.customCssContent) page.customCssContent = ''
  }

  // Ensure theme exists
  if (!parsed.theme || typeof parsed.theme !== 'object') {
    parsed.theme = {}
  }

  // Fill in theme defaults
  const theme = parsed.theme
  if (!theme.primaryColorHex) theme.primaryColorHex = '#7C3AED'
  if (!theme.backgroundColorHex) theme.backgroundColorHex = '#F6F7FB'
  if (!theme.buttonShape) theme.buttonShape = 'ROUNDED'
  if (!theme.fontFamily) theme.fontFamily = 'Inter'
  if (theme.isDarkMode === undefined) theme.isDarkMode = false
  if (theme.showHeader === undefined) theme.showHeader = true
  if (!theme.backgroundStyle) theme.backgroundStyle = 'SOLID'
  if (!theme.currencyCode) theme.currencyCode = 'BDT'
  if (!theme.redirectType) theme.redirectType = 'SUCCESS_MSG'
  if (!theme.successMessage) theme.successMessage = 'Thank you! Your submission was received. / ধন্যবাদ! আপনার তথ্য সফলভাবে জমা হয়েছে।'
  if (theme.enableAntiSpam === undefined) theme.enableAntiSpam = true
  if (theme.enablePayment === undefined) theme.enablePayment = true
  if (!theme.progressTrackerStyle) theme.progressTrackerStyle = 'BAR'
  if (theme.isMultiPageForm === undefined) theme.isMultiPageForm = parsed.pages.length > 1

  // Ensure custom_variables
  if (!Array.isArray(parsed.custom_variables)) parsed.custom_variables = []

  return parsed
}

// ── Intelligent Semantic Form Generator (Prompt-Aware, Guaranteed Fallback) ──
function generateSemanticFallbackForm(prompt, feedback = null, currentForm = null) {
  const p = (prompt || '').trim()
  const lc = p.toLowerCase()
  const fb = (feedback || '').toLowerCase()

  // Clean prompt for title
  let cleanTitle = p
    .replace(/^(create|make|build|generate|design)\s+(a|an|the)?\s*/i, '')
    .replace(/\s+(form|page|checkout|payment)\s*$/i, '')
    .trim()
  if (cleanTitle.length > 50) cleanTitle = cleanTitle.slice(0, 50).trim()
  if (cleanTitle) {
    cleanTitle = cleanTitle.charAt(0).toUpperCase() + cleanTitle.slice(1)
  } else {
    cleanTitle = 'Payment & Order Form'
  }

  // Detect domain
  const isExam = /exam|quiz|mcq|test|assessment|questionnaire|mock test/i.test(lc)
  const isEdu = !isExam && /course|tuition|student|school|college|university|academy|batch|class|admission|training|bootcamp/i.test(lc)
  const isDonation = /donation|mosque|masjid|madrasah|ngo|charity|zakat|sadaqah|relief|fundraiser|waqf|help/i.test(lc)
  const isEvent = /event|ticket|conference|webinar|seminar|meetup|summit|workshop|party|concert|fest/i.test(lc)
  const isAppointment = /appointment|booking|consult|doctor|clinic|lawyer|session|slot|schedule|advisor/i.test(lc)
  const isDigital = /digital|download|ebook|pdf|software|script|template|plugin|license|preset/i.test(lc)
  const isFood = /food|restaurant|burger|pizza|cafe|catering|bakery|meal|lunch|dinner|snack/i.test(lc)
  const isService = /service|repair|cleaning|design|development|photography|video|agency/i.test(lc)
  const isSub = /membership|subscription|gym|fitness|club|monthly|annual/i.test(lc)
  const isClothing = /shirt|pant|dress|panjabi|shoe|cloth|fashion|tshirt|hoodie|saree/i.test(lc)

  // Extract amount if present in prompt
  const amountMatch = lc.match(/(?:৳|tk|bdt|\$)\s*(\d+(?:,\d+)*(?:\.\d+)?)/i) || lc.match(/(\d+(?:,\d+)*(?:\.\d+)?)\s*(?:৳|tk|bdt|taka|dollars|\$)/i)
  const extractedAmount = amountMatch ? parseFloat(amountMatch[1].replace(/,/g, '')) : null

  let templateKey = 'SINGLE_PRODUCT'
  let primaryColor = '#4F46E5'
  let buttonShape = 'ROUNDED'
  let description = `Online form for ${cleanTitle}. Please fill in your details below.`
  let pages = []

  // Check special keywords in prompt
  const hasBkash = /bkash|nagad|rocket|trx|transaction|payment id|txn/i.test(lc) || /bkash|nagad|trx|transaction/i.test(fb)
  const hasUpload = /upload|file|pdf|cv|resume|screenshot|photo|image|picture/i.test(lc) || /upload|file|pdf|screenshot/i.test(fb)
  const hasAddress = /address|shipping|delivery|home delivery|courier/i.test(lc) || /address|shipping/i.test(fb)
  const hasCoupon = /coupon|promo|voucher|discount/i.test(lc) || /coupon|promo/i.test(fb)

  if (isExam) {
    templateKey = 'ONLINE_MCQ_EXAM'
    primaryColor = '#2856E0'
    buttonShape = 'ROUNDED'
    description = `Online assessment and timed multiple-choice exam for ${cleanTitle}.`

    pages = [
      {
        title: 'Candidate Verification',
        subtitle: 'Enter your credentials before beginning the assessment',
        isCustomHtml: false,
        fields: [
          { type: 'NAME', label: 'Candidate Full Name', placeholder: 'Enter your full legal name', isRequired: true },
          { type: 'NAME', label: 'Student / Roll ID', placeholder: 'e.g. STU-2026-9812', isRequired: true },
          { type: 'EMAIL', label: 'Registered Email Address', placeholder: 'candidate@example.com', isRequired: true },
          { type: 'PHONE', label: 'Mobile Contact Number', placeholder: '01XXXXXXXXX', isRequired: true }
        ],
        customHtmlContent: '',
        customCssContent: ''
      },
      {
        title: 'Core Concept MCQs',
        subtitle: 'Select the best answer for each question',
        isCustomHtml: false,
        fields: [
          {
            type: 'RADIO',
            label: 'Which methodology emphasizes iterative delivery in short cycles?',
            options: ['Waterfall', 'Agile', 'Critical Path Method', 'Six Sigma'],
            isRequired: true
          },
          {
            type: 'RADIO',
            label: 'A risk register should be updated only at project closure.',
            options: ['True', 'False'],
            isRequired: true
          },
          {
            type: 'CHECKBOX',
            label: 'Select all elements typically found in a project charter.',
            options: ['Business case', 'Stakeholder list', 'Detailed Gantt chart', 'High-level budget', 'Vendor invoices'],
            isRequired: true
          },
          {
            type: 'NAME',
            label: 'The process of identifying, analyzing, and responding to project risk is called risk ______.',
            placeholder: 'Fill in the blank...',
            isRequired: true
          }
        ],
        customHtmlContent: '',
        customCssContent: ''
      },
      {
        title: 'Scenario & Applied Knowledge',
        subtitle: 'Advanced conceptual evaluation',
        isCustomHtml: false,
        fields: [
          {
            type: 'RADIO',
            label: 'Which document formally authorizes a project to begin?',
            options: ['Project charter', 'Status report', 'Lessons learned register', 'RACI matrix'],
            isRequired: true
          },
          {
            type: 'DROPDOWN',
            label: 'Match: Uncontrolled expansion of project scope is known as...',
            options: ['Scope creep', 'Critical path', 'Milestone variance', 'Sprint backlog'],
            isRequired: true
          },
          {
            type: 'FILE_UPLOAD',
            label: 'Upload completed calculation sheet or supporting PDF (Optional)',
            isRequired: false
          }
        ],
        customHtmlContent: '',
        customCssContent: ''
      },
      {
        title: 'Exam Submitted',
        subtitle: 'Assessment responses locked',
        isCustomHtml: true,
        fields: [],
        customHtmlContent: `<div style="text-align: center; padding: 32px 16px; font-family: sans-serif;">
  <div style="font-size: 54px; margin-bottom: 12px;">🎓</div>
  <h2 style="color: #2856E0; margin: 0 0 8px 0; font-size: 24px;">পরীক্ষা সফলভাবে জমা হয়েছে!</h2>
  <p style="color: #4B5563; font-size: 15px; max-width: 440px; margin: 0 auto 20px auto; line-height: 1.6;">
    আপনার উত্তরপত্র সফলভাবে মূল্যায়ন ডাটাবেজে রেকর্ড করা হয়েছে। ফলাফল প্রকাশের পর নিবন্ধিত ইমেইল ও এসএমএসে ফলাফল পাঠানো হবে।
  </p>
  <div style="display: inline-block; padding: 10px 20px; background: #E8EDFC; border: 1px solid #BFDBFE; border-radius: 9999px; color: #1E40AF; font-size: 13px; font-weight: 600;">
    Responses Saved & Locked
  </div>
</div>`,
        customCssContent: ''
      }
    ]
  } else if (isDonation) {
    templateKey = 'DONATION'
    primaryColor = '#059669'
    buttonShape = 'PILL'
    description = 'Support our noble cause with your generous contribution. Select a donation tier or specify a custom amount.'
    const donationFields = [
      { type: 'NAME', label: 'Donor Name / দাতার নাম', placeholder: 'Enter your name (or leave blank for Anonymous)', isRequired: false },
      { type: 'PHONE', label: 'Mobile Number / মোবাইল নম্বর', placeholder: '01XXXXXXXXX', isRequired: true },
      { type: 'EMAIL', label: 'Email Address (for receipt)', placeholder: 'your@email.com', isRequired: false },
      { type: 'DONATION', label: 'Select Contribution Amount', options: ['৳100 (Sadaqah)', '৳500 (Supporter)', '৳1,000 (Generous)', '৳5,000 (Patron)'], isRequired: true },
      { type: 'CUSTOM_AMOUNT', label: 'Or Enter Custom Amount (BDT / টাকা)', placeholder: 'e.g. 2500', isRequired: false },
      { type: 'NOTES', label: 'Special Prayer / Dua Request or Purpose', placeholder: 'Any prayer request or message...', isRequired: false }
    ]
    if (hasBkash) donationFields.push({ type: 'NAME', label: 'bKash / Nagad Transaction ID (TrxID)', placeholder: 'e.g. 9J47KL89X', isRequired: true })
    if (hasUpload) donationFields.push({ type: 'FILE_UPLOAD', label: 'Payment Screenshot / Deposit Slip', isRequired: false })

    pages = [
      {
        title: 'Donation Details',
        subtitle: 'Every contribution makes a significant difference',
        isCustomHtml: false,
        fields: donationFields,
        customHtmlContent: '',
        customCssContent: ''
      },
      {
        title: 'Thank You',
        subtitle: 'Jazakallah Khair for your generosity',
        isCustomHtml: true,
        fields: [],
        customHtmlContent: `<div style="text-align: center; padding: 32px 16px; font-family: sans-serif;">
  <div style="font-size: 54px; margin-bottom: 12px;">🤲</div>
  <h2 style="color: #059669; margin: 0 0 8px 0; font-size: 24px;">জাযাকাল্লাহু খাইরান!</h2>
  <p style="color: #4B5563; font-size: 15px; max-width: 440px; margin: 0 auto 20px auto; line-height: 1.6;">
    আপনার অনুদানের জন্য আন্তরিক ধন্যবাদ। আল্লাহ আপনার দানকে কবুল করুন ও উত্তম প্রতিদান দান করুন।
  </p>
  <div style="display: inline-block; padding: 10px 20px; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 9999px; color: #065F46; font-size: 13px; font-weight: 600;">
    Donation Receipt Will Be Sent via SMS
  </div>
</div>`,
        customCssContent: ''
      }
    ]
  } else if (isEdu) {
    templateKey = 'EDUCATION'
    primaryColor = '#7C3AED'
    buttonShape = 'ROUNDED'
    description = 'Complete your course registration and fee payment to secure your seat in the batch.'
    const price = extractedAmount || 3500
    const eduFields = [
      { type: 'NAME', label: 'Student Full Name / শিক্ষার্থীর নাম', placeholder: 'e.g. Tanvir Hasan', isRequired: true },
      { type: 'PHONE', label: 'Student WhatsApp / Phone Number', placeholder: '01XXXXXXXXX', isRequired: true },
      { type: 'EMAIL', label: 'Email Address (for course portal)', placeholder: 'student@example.com', isRequired: true },
      { type: 'DROPDOWN', label: 'Select Course Batch / ব্যাচ নির্বাচন করুন', options: [`Upcoming Batch (৳${price})`, `Weekend Batch (৳${price})`, `Self-Paced Portal Access (৳${Math.round(price * 0.7)})`], isRequired: true },
      { type: 'NAME', label: 'Educational Background / Institution', placeholder: 'e.g. BUET / Dhaka University / College', isRequired: false }
    ]
    if (hasBkash) eduFields.push({ type: 'NAME', label: 'bKash / Nagad TrxID (Transaction ID)', placeholder: 'e.g. 8K42NX99', isRequired: true })
    if (hasUpload) eduFields.push({ type: 'FILE_UPLOAD', label: 'Upload Student ID / Certificate / Payment Proof', isRequired: false })
    if (hasCoupon) eduFields.push({ type: 'COUPON', label: 'Scholarship / Promo Code', placeholder: 'e.g. PROMO20', isRequired: false })

    pages = [
      {
        title: 'Student Registration',
        subtitle: 'Enter your academic and contact information',
        isCustomHtml: false,
        fields: eduFields,
        customHtmlContent: '',
        customCssContent: ''
      },
      {
        title: 'Registration Confirmed',
        subtitle: 'Welcome to the learning journey',
        isCustomHtml: true,
        fields: [],
        customHtmlContent: `<div style="text-align: center; padding: 32px 16px; font-family: sans-serif;">
  <div style="font-size: 52px; margin-bottom: 12px;">🎓</div>
  <h2 style="color: #7C3AED; margin: 0 0 8px 0; font-size: 24px;">অভিনন্দন! রেজিস্ট্রেশন সম্পন্ন হয়েছে</h2>
  <p style="color: #4B5563; font-size: 15px; max-width: 440px; margin: 0 auto 20px auto; line-height: 1.6;">
    আপনার আসনটি সফলভাবে নিশ্চিত করা হয়েছে। ব্যাচ শুরু হওয়ার পূর্বে ক্লাসের লিংক ও রুটিন ইমেইল এবং হোয়াটসঅ্যাপে পাঠানো হবে।
  </p>
  <div style="display: inline-block; padding: 10px 20px; background: #F5F3FF; border: 1px solid #DDD6FE; border-radius: 8px; color: #5B21B6; font-size: 13px; font-weight: 600;">
    Keep your phone active for class onboarding SMS
  </div>
</div>`,
        customCssContent: ''
      }
    ]
  } else if (isEvent) {
    templateKey = 'EVENT'
    primaryColor = '#EC4899'
    buttonShape = 'PILL'
    description = 'Register now for this premier event and receive your official digital pass via email and SMS.'
    const price = extractedAmount || 800
    const eventFields = [
      { type: 'NAME', label: 'Attendee Full Name', placeholder: 'e.g. Sadia Islam', isRequired: true },
      { type: 'EMAIL', label: 'Email Address (for Digital Ticket / QR Pass)', placeholder: 'sadia@example.com', isRequired: true },
      { type: 'PHONE', label: 'Phone Number', placeholder: '01XXXXXXXXX', isRequired: true },
      { type: 'COMPANY', label: 'Organization / University', placeholder: 'Company or University name', isRequired: false },
      { type: 'RADIO', label: 'Ticket Category', options: [`General Access (৳${price})`, `VIP All-Access Pass (৳${price * 2})`, `Student Pass (৳${Math.round(price * 0.5)})`], isRequired: true },
      { type: 'QUANTITY', label: 'Number of Tickets', defaultValue: '1', isRequired: true }
    ]
    if (hasBkash) eventFields.push({ type: 'NAME', label: 'bKash / Nagad Transaction ID', placeholder: 'e.g. 9B88CL21', isRequired: true })
    if (hasCoupon) eventFields.push({ type: 'COUPON', label: 'Vip / Discount Code', placeholder: 'e.g. EARLYBIRD', isRequired: false })

    pages = [
      {
        title: 'Attendee Information',
        subtitle: 'Fill in your details to reserve tickets',
        isCustomHtml: false,
        fields: eventFields,
        customHtmlContent: '',
        customCssContent: ''
      },
      {
        title: 'Pass Issued',
        subtitle: 'Your entry pass is ready',
        isCustomHtml: true,
        fields: [],
        customHtmlContent: `<div style="text-align: center; padding: 32px 16px; font-family: sans-serif;">
  <div style="font-size: 54px; margin-bottom: 12px;">🎟️</div>
  <h2 style="color: #EC4899; margin: 0 0 8px 0; font-size: 24px;">Ticket Reserved Successfully!</h2>
  <p style="color: #4B5563; font-size: 15px; max-width: 440px; margin: 0 auto 20px auto; line-height: 1.6;">
    Thank you for registering. Your digital entry ticket with QR verification code has been queued for email delivery.
  </p>
  <div style="display: inline-block; padding: 10px 20px; background: #FDF2F8; border: 1px solid #FBCFE8; border-radius: 9999px; color: #9D174D; font-size: 13px; font-weight: 600;">
    Show QR Code at the venue gate for instant check-in
  </div>
</div>`,
        customCssContent: ''
      }
    ]
  } else if (isAppointment) {
    templateKey = 'APPOINTMENT'
    primaryColor = '#0284C7'
    buttonShape = 'ROUNDED'
    description = 'Book a private consultation slot. Select your convenient date and preferred time window.'
    const price = extractedAmount || 1000
    pages = [
      {
        title: 'Appointment Booking',
        subtitle: 'Choose your desired consultation slot',
        isCustomHtml: false,
        fields: [
          { type: 'NAME', label: 'Client / Patient Name', placeholder: 'Full Name', isRequired: true },
          { type: 'PHONE', label: 'Contact Phone Number', placeholder: '01XXXXXXXXX', isRequired: true },
          { type: 'EMAIL', label: 'Email Address', placeholder: 'client@example.com', isRequired: false },
          { type: 'DROPDOWN', label: 'Consultation Type', options: [`General Session (৳${price})`, `Comprehensive In-depth (৳${price * 2})`, `Follow-up Review (৳${Math.round(price * 0.6)})`], isRequired: true },
          { type: 'DATE', label: 'Preferred Appointment Date', placeholder: 'YYYY-MM-DD', isRequired: true },
          { type: 'DROPDOWN', label: 'Preferred Time Window', options: ['Morning Slot (10:00 AM - 12:00 PM)', 'Afternoon Slot (02:30 PM - 05:00 PM)', 'Evening Slot (06:30 PM - 09:00 PM)'], isRequired: true },
          { type: 'NOTES', label: 'Reason for Visit / Special Notes', placeholder: 'Describe your requirements or concerns...', isRequired: false }
        ],
        customHtmlContent: '',
        customCssContent: ''
      }
    ]
  } else if (isDigital) {
    templateKey = 'DIGITAL'
    primaryColor = '#D97706'
    buttonShape = 'ROUNDED'
    description = 'Instant automated digital delivery. Download link and license key will be sent immediately upon payment.'
    const price = extractedAmount || 1200
    pages = [
      {
        title: 'Digital Order Details',
        subtitle: 'Enter your email for automated download link delivery',
        isCustomHtml: false,
        fields: [
          { type: 'NAME', label: 'Customer Name', placeholder: 'Your Name', isRequired: true },
          { type: 'EMAIL', label: 'Delivery Email Address (Required for file link)', placeholder: 'your@email.com', isRequired: true },
          { type: 'PHONE', label: 'WhatsApp / Phone Number', placeholder: '01XXXXXXXXX', isRequired: true },
          { type: 'DROPDOWN', label: 'License / Package Tier', options: [`Single Standard License (৳${price})`, `Commercial / Unlimited License (৳${price * 3})`], isRequired: true },
          { type: 'COUPON', label: 'Voucher / Discount Code', placeholder: 'e.g. LAUNCH50', isRequired: false }
        ],
        customHtmlContent: '',
        customCssContent: ''
      }
    ]
  } else {
    // E-Commerce / Physical Goods / Products / Default
    templateKey = (hasAddress || isClothing || isFood) ? 'SINGLE_PRODUCT' : 'CART'
    primaryColor = isFood ? '#EA580C' : (isClothing ? '#0F172A' : '#2563EB')
    buttonShape = 'ROUNDED'
    description = `Order ${cleanTitle} online with fast nationwide courier delivery and cash on delivery or online payment.`
    const price = extractedAmount || (isClothing ? 950 : (isFood ? 450 : 1250))
    const p1Fields = [
      { type: 'NAME', label: 'Customer Full Name / আপনার নাম', placeholder: 'e.g. Asif Mahmud', isRequired: true },
      { type: 'PHONE', label: 'Mobile Number / মোবাইল নম্বর', placeholder: '01XXXXXXXXX', isRequired: true },
      { type: 'ADDRESS', label: 'Full Delivery Address / পূর্ণ ঠিকানা', placeholder: 'House/Road, Area, District/Thana', isRequired: true }
    ]
    if (isClothing) {
      p1Fields.push({ type: 'DROPDOWN', label: 'Select Size / সাইজ নির্বাচন করুন', options: ['M (Medium)', 'L (Large)', 'XL (Extra Large)', 'XXL'], isRequired: true })
      p1Fields.push({ type: 'RADIO', label: 'Color Variant / কালার', options: ['Black', 'Navy Blue', 'Maroon', 'White'], isRequired: false })
    }
    p1Fields.push({ type: 'QUANTITY', label: 'Quantity / পরিমাণ', defaultValue: '1', isRequired: true })
    p1Fields.push({ type: 'SHIPPING', label: 'Delivery Area / ডেলিভারি এলাকা', options: ['Inside Dhaka (৳60)', 'Sub-Dhaka / Savar / Gazipur (৳100)', 'Outside Dhaka Nationwide (৳130)'], isRequired: true })
    if (hasCoupon) p1Fields.push({ type: 'COUPON', label: 'Discount Voucher Code', placeholder: 'e.g. SAVE10', isRequired: false })
    if (hasBkash) p1Fields.push({ type: 'NAME', label: 'bKash / Nagad Transaction ID (if paying online)', placeholder: 'Leave empty for Cash on Delivery', isRequired: false })
    if (hasUpload) p1Fields.push({ type: 'FILE_UPLOAD', label: 'Attachment / Reference Image', isRequired: false })

    pages = [
      {
        title: 'Order & Shipping Information',
        subtitle: 'Provide your delivery details to complete your order',
        isCustomHtml: false,
        fields: p1Fields,
        customHtmlContent: '',
        customCssContent: ''
      },
      {
        title: 'Order Received',
        subtitle: 'Thank you for shopping with us',
        isCustomHtml: true,
        fields: [],
        customHtmlContent: `<div style="text-align: center; padding: 32px 16px; font-family: sans-serif;">
  <div style="font-size: 54px; margin-bottom: 12px;">📦</div>
  <h2 style="color: ${primaryColor}; margin: 0 0 8px 0; font-size: 24px;">অর্ডার সফলভাবে গ্রহণ করা হয়েছে!</h2>
  <p style="color: #4B5563; font-size: 15px; max-width: 440px; margin: 0 auto 20px auto; line-height: 1.6;">
    আপনার অর্ডারটি সফলভাবে নিবন্ধিত হয়েছে। আমাদের টিম শিগগিরই কল বা এসএমএসের মাধ্যমে আপনার অর্ডার কনফার্ম করবে।
  </p>
  <div style="display: inline-block; padding: 10px 20px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 9999px; color: #334155; font-size: 13px; font-weight: 600;">
    Tracking SMS will be sent when dispatched
  </div>
</div>`,
        customCssContent: ''
      }
    ]
  }

  const customVariables = [
    { key: 'customer_name', exampleValue: 'Asif Mahmud', source: 'field' },
    { key: 'customer_phone', exampleValue: '01712345678', source: 'field' },
    { key: 'form_title', exampleValue: cleanTitle, source: 'system' }
  ]

  return {
    title: cleanTitle,
    description,
    template_key: templateKey,
    theme: {
      primaryColorHex: primaryColor,
      backgroundColorHex: '#F8FAFC',
      buttonShape,
      fontFamily: 'Inter',
      isDarkMode: false,
      showHeader: true,
      backgroundStyle: 'SOLID',
      gradientColorStart: primaryColor,
      gradientColorEnd: primaryColor,
      enablePayment: true,
      currencyCode: 'BDT',
      redirectType: 'SUCCESS_MSG',
      successMessage: `Thank you for your submission for ${cleanTitle}! / ধন্যবাদ! আপনার তথ্য সফলভাবে জমা হয়েছে।`,
      enableAntiSpam: true,
      isMultiPageForm: pages.length > 1,
      progressTrackerStyle: 'BAR',
      customCss: '',
      customJs: '',
      enableCustomJs: false
    },
    pages,
    custom_variables: customVariables
  }
}

// ── POST /v1/ai/generate-form ─────────────────────────────────────────────────
aiFormRouter.post('/generate-form', async (req, res) => {
  const { prompt, feedback, merchant_id, current_form, gemini_api_key, openrouter_api_key, gemini_model } = req.body || {}

  if (!prompt || typeof prompt !== 'string' || prompt.trim().length < 3) {
    return res.status(400).json({ success: false, error: 'prompt is required (min 3 chars)' })
  }

  // 1. Prioritize merchant-supplied key from request, then DB, then server env
  const clientGeminiKey = (gemini_api_key || req.headers['x-gemini-api-key'] || '').trim()
  const clientOpenRouterKey = (openrouter_api_key || req.headers['x-openrouter-api-key'] || '').trim()
  const preferredModel = (gemini_model || '').trim()

  const dbKey = await getMerchantGeminiKey(merchant_id)
  const apiKey = clientGeminiKey || dbKey || process.env.GEMINI_API_KEY || ''
  const openRouterKey = clientOpenRouterKey || process.env.OPENROUTER_API_KEY || ''

  if (!apiKey && !openRouterKey) {
    console.log('[aiForm] No AI API key available — returning semantic fallback form')
    const semanticForm = generateSemanticFallbackForm(prompt, feedback, current_form)
    return res.json({
      success: false,
      fallback: true,
      reason: 'no_api_key',
      form: semanticForm,
      engine: 'semantic_fallback',
      message: 'No AI API key configured. Generated using SwapnoPay Intelligent Semantic Form Engine.'
    })
  }

  // Build user message
  let userMessage = `Create a complete payment form for: "${prompt.trim()}"`
  if (feedback && typeof feedback === 'string' && feedback.trim()) {
    userMessage += `\n\nUser feedback / refinement request: "${feedback.trim()}"`
    if (current_form) {
      try {
        const existing = typeof current_form === 'string' ? JSON.parse(current_form) : current_form
        userMessage += `\n\nCurrent form to update:\n${JSON.stringify(existing, null, 2)}`
      } catch { /* ignore */ }
    }
    userMessage += '\n\nPlease update the form based on the feedback above. Keep everything else the same unless specified.'
  }

  console.log(`[aiForm] Generating form for merchant=${merchant_id || 'unknown'}, prompt="${prompt.slice(0, 80)}"`)

  // 2. Try Gemini if API key available
  if (apiKey) {
    try {
      const raw = await callGemini(apiKey, SYSTEM_PROMPT, userMessage, preferredModel)
      const form = parseAndValidateFormJson(raw)
      if (form) {
        console.log(`[aiForm] Successfully generated form with Gemini: "${form.title}" with ${form.pages.length} pages`)
        return res.json({ success: true, form, engine: 'gemini' })
      }
    } catch (err) {
      console.warn('[aiForm] Gemini call failed:', err.message)
    }
  }

  // 3. Try OpenRouter if key available
  if (openRouterKey) {
    try {
      const rawOr = await callOpenRouter(openRouterKey, SYSTEM_PROMPT, userMessage)
      const form = parseAndValidateFormJson(rawOr)
      if (form) {
        console.log(`[aiForm] Successfully generated form with OpenRouter: "${form.title}" with ${form.pages.length} pages`)
        return res.json({ success: true, form, engine: 'openrouter' })
      }
    } catch (err) {
      console.warn('[aiForm] OpenRouter call failed:', err.message)
    }
  }

  // 4. Intelligent Semantic Fallback Generator (guaranteed success & tailored to prompt)
  console.log('[aiForm] Using intelligent semantic fallback generator for prompt:', prompt.slice(0, 60))
  const semanticForm = generateSemanticFallbackForm(prompt, feedback, current_form)
  return res.json({
    success: false,
    fallback: true,
    reason: 'parse_error',
    form: semanticForm,
    engine: 'semantic_fallback',
    message: 'Generated using SwapnoPay Intelligent Semantic Form Engine'
  })
})

