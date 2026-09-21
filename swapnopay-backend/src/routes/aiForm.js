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
async function callGemini(apiKey, systemPrompt, userMessage) {
  if (!apiKey) return null
  const contents = [
    { role: 'user', parts: [{ text: systemPrompt + '\n\n---\n\n' + userMessage }] }
  ]
  const generationConfig = {
    temperature: 0.7,
    maxOutputTokens: 8192,
    responseMimeType: 'application/json'
  }
  for (const model of GEMINI_MODELS) {
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

// ── POST /v1/ai/generate-form ─────────────────────────────────────────────────
aiFormRouter.post('/generate-form', async (req, res) => {
  const { prompt, feedback, merchant_id, current_form } = req.body || {}

  if (!prompt || typeof prompt !== 'string' || prompt.trim().length < 3) {
    return res.status(400).json({ success: false, error: 'prompt is required (min 3 chars)' })
  }

  // Get API key: merchant key first, then server env
  const merchantKey = await getMerchantGeminiKey(merchant_id)
  const apiKey = merchantKey || process.env.GEMINI_API_KEY || ''

  if (!apiKey) {
    console.log('[aiForm] No Gemini API key available — telling client to use keyword fallback')
    return res.json({ success: false, fallback: true, reason: 'no_api_key' })
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

  try {
    const raw = await callGemini(apiKey, SYSTEM_PROMPT, userMessage)
    const form = parseAndValidateFormJson(raw)

    if (!form) {
      console.warn('[aiForm] Gemini returned unparseable response, telling client to use fallback')
      return res.json({ success: false, fallback: true, reason: 'parse_error' })
    }

    console.log(`[aiForm] Successfully generated form: "${form.title}" with ${form.pages.length} pages`)
    return res.json({ success: true, form })

  } catch (err) {
    console.error('[aiForm] Unexpected error:', err.message)
    return res.json({ success: false, fallback: true, reason: 'server_error' })
  }
})
