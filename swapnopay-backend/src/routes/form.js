// SwapnoPay Backend — Form Handling & Routing Router
// Provides:
// POST   /v1/routes                  — Register/persist branded form route & cached definition
// DELETE /v1/routes/:id              — Unregister route
// GET    /v1/forms/:slugOrId         — Resolve form by slug or ID (returns fields, products, theme, status)
// POST   /v1/forms/:slugOrId/submit  — Secure submission handler (service_role bypass, order creation, notifications)
// GET    /v1/forms/:slugOrId/submissions — Fetch form responses

import { Router } from 'express'
import fs from 'fs'
import path from 'path'
import crypto from 'crypto'
import { fileURLToPath } from 'url'
import { getAdminClient, getMerchantCredentials, getMerchantGatewayConfig, recordPaymentEvent } from '../services/adminSupabase.js'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

// Persistent route storage file path
const DATA_DIR = path.resolve(__dirname, '../../data')
const ROUTES_FILE = path.join(DATA_DIR, 'form_routes.json')

// In-memory route and form cache
const routeBySlug = new Map()
const routeById = new Map()
const formSubmissionsMemory = new Map() // formId -> array of submissions
export const orderToFormSubmissionMap = new Map() // orderId -> { form_id, submission_id, merchant_id, form_slug }

/**
 * Robust currency and price parser for option labels
 * Handles formats like: 'VIP Pass (৳1500)', '৳500 (Generous)', 'Batch 12 (৳4,500)', '1000 Tk', '500 BDT'
 */
export function parseAmountFromText(val) {
  if (typeof val === 'number' && !isNaN(val)) return val
  if (!val) return 0
  if (typeof val === 'object') {
    if (val.price !== undefined) return Number(val.price || 0)
    if (val.amount !== undefined) return Number(val.amount || 0)
    if (val.total_bdt !== undefined) return Number(val.total_bdt || 0)
  }
  const str = String(val).trim()
  if (!str) return 0

  // 1. Currency prefix: ৳, BDT, Tk, TK, Tk.
  const prefixMatch = str.match(/(?:৳|BDT|TK\.?|Tk\.?)\s*([0-9,]+(?:\.[0-9]{1,2})?)/i)
  if (prefixMatch) {
    const num = parseFloat(prefixMatch[1].replace(/,/g, ''))
    if (!isNaN(num) && num > 0) return num
  }

  // 2. Currency suffix: 500৳, 500 BDT, 500 Tk, 500Tk
  const suffixMatch = str.match(/([0-9,]+(?:\.[0-9]{1,2})?)\s*(?:৳|BDT|TK\.?|Tk\.?)/i)
  if (suffixMatch) {
    const num = parseFloat(suffixMatch[1].replace(/,/g, ''))
    if (!isNaN(num) && num > 0) return num
  }

  // 3. Standalone number in parentheses: (1500)
  const parenMatch = str.match(/\(\s*([0-9,]+(?:\.[0-9]{1,2})?)\s*\)/)
  if (parenMatch) {
    const num = parseFloat(parenMatch[1].replace(/,/g, ''))
    if (!isNaN(num) && num > 0) return num
  }

  // 4. Pure number
  const clean = str.replace(/,/g, '')
  if (/^[0-9]+(?:\.[0-9]{1,2})?$/.test(clean)) {
    const num = parseFloat(clean)
    if (!isNaN(num) && num > 0) return num
  }
  return 0
}

// Ensure data directory and persistent file exist
function initPersistence() {
  try {
    if (!fs.existsSync(DATA_DIR)) {
      fs.mkdirSync(DATA_DIR, { recursive: true })
    }
    if (fs.existsSync(ROUTES_FILE)) {
      const raw = fs.readFileSync(ROUTES_FILE, 'utf8')
      const items = JSON.parse(raw || '[]')
      if (Array.isArray(items)) {
        for (const item of items) {
          if (item.slug) routeBySlug.set(item.slug.toLowerCase(), item)
          if (item.form_id) {
            const cleanId = item.form_id.toLowerCase().replace(/-/g, '')
            routeById.set(cleanId, item)
            routeById.set(item.form_id.toLowerCase(), item)
          }
        }
        console.log(`[form-router] Loaded ${items.length} persistent form routes from disk.`)
      }
    } else {
      fs.writeFileSync(ROUTES_FILE, JSON.stringify([], null, 2), 'utf8')
    }
  } catch (err) {
    console.warn('[form-router] Failed to initialize persistent storage:', err.message)
  }
}

initPersistence()

function saveRoutesToDisk() {
  try {
    const all = []
    const seen = new Set()
    for (const item of routeBySlug.values()) {
      const key = item.form_id || item.slug
      if (!seen.has(key)) {
        seen.add(key)
        all.push(item)
      }
    }
    for (const item of routeById.values()) {
      const key = item.form_id || item.slug
      if (!seen.has(key)) {
        seen.add(key)
        all.push(item)
      }
    }
    const tempFile = `${ROUTES_FILE}.tmp.${Date.now()}`
    fs.writeFileSync(tempFile, JSON.stringify(all, null, 2), 'utf8')
    try {
      if (fs.existsSync(ROUTES_FILE)) fs.unlinkSync(ROUTES_FILE)
    } catch (_) {}
    fs.renameSync(tempFile, ROUTES_FILE)
  } catch (err) {
    console.error('[form-router] Error saving routes to disk:', err.message)
  }
}

function normalizeUuid(val) {
  if (!val) return null
  const str = String(val).trim().toLowerCase()
  if (/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(str)) {
    return str
  }
  const clean = str.replace(/[^0-9a-f]/g, '')
  if (clean.length === 32) {
    return `${clean.slice(0, 8)}-${clean.slice(8, 12)}-${clean.slice(12, 16)}-${clean.slice(16, 20)}-${clean.slice(20, 32)}`
  }
  return null
}

function deterministicUuid(str) {
  if (!str) return '00000000-0000-0000-0000-000000000001'
  const hash = crypto.createHash('md5').update(String(str)).digest('hex')
  return `${hash.slice(0, 8)}-${hash.slice(8, 12)}-4${hash.slice(13, 16)}-8${hash.slice(17, 20)}-${hash.slice(20, 32)}`
}

export function formRouter(io = null) {
  const router = Router()

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/routes (also mounted on /v1/routes directly)
  // Branded Form Route Registration
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/routes', async (req, res) => {
    try {
      const {
        form_id,
        slug,
        project_url,
        publishable_key,
        merchant_id,
        payload,
        form_data
      } = req.body || {}

      const cleanFormId = String(form_id || '').trim()
      const rawSlug = String(slug || cleanFormId || '').trim()
      const normalizedSlug = rawSlug.toLowerCase().replace(/[^a-z0-9_-]/g, '-')

      if (!cleanFormId && !normalizedSlug) {
        return res.status(400).json({ ok: false, error: 'form_id or slug is required' })
      }

      const formSnapshot = payload || form_data || null
      const effectiveMerchantId = merchant_id || (formSnapshot && typeof formSnapshot === 'object' ? formSnapshot.merchant_id : null) || null
      if (formSnapshot && typeof formSnapshot === 'object' && effectiveMerchantId && !formSnapshot.merchant_id) {
        formSnapshot.merchant_id = effectiveMerchantId
      }

      const routeRecord = {
        form_id: cleanFormId,
        slug: normalizedSlug,
        project_url: project_url || null,
        publishable_key: publishable_key || null,
        merchant_id: effectiveMerchantId,
        payload: formSnapshot,
        updated_at: new Date().toISOString()
      }

      // Store in memory
      if (normalizedSlug) routeBySlug.set(normalizedSlug, routeRecord)
      if (cleanFormId) {
        const compactId = cleanFormId.toLowerCase().replace(/-/g, '')
        routeById.set(compactId, routeRecord)
        routeById.set(cleanFormId.toLowerCase(), routeRecord)
      }

      // Persist to disk
      saveRoutesToDisk()

      // Also persist to admin database payment_forms if full payload exists
      try {
        if (formSnapshot && typeof formSnapshot === 'object') {
          const admin = getAdminClient()
          const effectiveFormUuid = normalizeUuid(cleanFormId) || deterministicUuid(cleanFormId || normalizedSlug)
          const effectiveMerchantUuid = normalizeUuid(formSnapshot.merchant_id || merchant_id) || deterministicUuid(formSnapshot.merchant_id || merchant_id || '00000000-0000-0000-0000-000000000001')
          const dbRow = {
            id: effectiveFormUuid,
            merchant_id: effectiveMerchantUuid,
            title: formSnapshot.title || 'Hosted Payment Form',
            description: formSnapshot.description || '',
            slug: normalizedSlug,
            amount: Number(formSnapshot.amount || 0),
            status: formSnapshot.status || 'PUBLISHED',
            fields: typeof formSnapshot.fields === 'string' ? formSnapshot.fields : JSON.stringify(formSnapshot.fields || []),
            products: typeof formSnapshot.products === 'string' ? formSnapshot.products : JSON.stringify(formSnapshot.products || []),
            pages: typeof formSnapshot.pages === 'string' ? formSnapshot.pages : JSON.stringify(formSnapshot.pages || []),
            theme: typeof formSnapshot.theme === 'string' ? formSnapshot.theme : (formSnapshot.theme || {}),
            logo_url: formSnapshot.logo_url || (formSnapshot.theme && formSnapshot.theme.logo_url) || null,
            banner_url: formSnapshot.banner_url || (formSnapshot.theme && formSnapshot.theme.banner_url) || null,
            updated_at: new Date().toISOString()
          }

          admin.from('payment_forms').upsert(dbRow, { onConflict: 'id' }).then(({ error }) => {
            if (error) console.warn('[form-router] Admin DB upsert warning:', error.message)
            else console.log('[form-router] Form synced to Admin DB payment_forms:', normalizedSlug)
          }).catch(e => console.warn('[form-router] DB sync caught:', e.message))
        }
      } catch (dbErr) {
        console.warn('[form-router] Admin DB sync error:', dbErr.message)
      }

      const reqOrigin = req.get('host') ? `${req.protocol}://${req.get('host')}` : null
      const publicOrigin = process.env.PAYMENT_ROUTER_ORIGIN || reqOrigin || 'https://pay.swapnopay.top'
      const publicUrl = `${publicOrigin}/f/${normalizedSlug || cleanFormId}`

      console.log(`[form-router] Route registered: ${publicUrl}`)
      return res.json({
        ok: true,
        public_id: cleanFormId,
        public_url: publicUrl,
        slug_url: publicUrl
      })
    } catch (err) {
      console.error('[form-router] Error registering route:', err.message)
      return res.status(500).json({ ok: false, error: err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // DELETE /v1/routes/:id and DELETE /v1/forms/:id
  // ──────────────────────────────────────────────────────────────────────────
  function handleDeleteFormRoute(req, res) {
    const id = (req.params.id || '').toLowerCase().trim()
    const compactId = id.replace(/-/g, '')
    const existing = routeById.get(compactId) || routeBySlug.get(id)
    if (existing) {
      if (existing.slug) routeBySlug.delete(existing.slug.toLowerCase())
      if (existing.form_id) {
        routeById.delete(existing.form_id.toLowerCase())
        routeById.delete(existing.form_id.toLowerCase().replace(/-/g, ''))
      }
      saveRoutesToDisk()
    }
    return res.status(200).json({ ok: true })
  }

  router.delete('/routes/:id', handleDeleteFormRoute)
  router.delete('/forms/:id', handleDeleteFormRoute)

  // ──────────────────────────────────────────────────────────────────────────
  // PATCH /v1/forms/:slugOrId & POST /v1/forms/:slugOrId/update
  // Allows customizing style, uploading product image, toggling/removing sections,
  // editing fields, swatches, specs, and prices dynamically
  // ──────────────────────────────────────────────────────────────────────────
  async function handleUpdateForm(req, res) {
    try {
      const identifier = String(req.params.slugOrId || '').trim()
      const normalizedIdentifier = identifier.toLowerCase()
      const compactId = normalizedIdentifier.replace(/-/g, '')

      let route = routeBySlug.get(normalizedIdentifier) || routeById.get(compactId) || routeById.get(normalizedIdentifier)
      const existingPayload = (route && route.payload) ? { ...route.payload } : { id: identifier, slug: identifier }

      const updates = req.body || {}

      const updatedPayload = {
        ...existingPayload,
        ...updates,
        theme: {
          ...(existingPayload.theme || existingPayload.theme_config || {}),
          ...(updates.theme || updates.theme_config || {})
        },
        theme_config: {
          ...(existingPayload.theme_config || existingPayload.theme || {}),
          ...(updates.theme_config || updates.theme || {})
        },
        updated_at: new Date().toISOString()
      }

      if (updates.image_url) updatedPayload.image_url = updates.image_url
      if (updates.products) updatedPayload.products = updates.products
      if (updates.fields) updatedPayload.fields = updates.fields
      if (updates.title) updatedPayload.title = updates.title
      if (updates.description !== undefined) updatedPayload.description = updates.description
      if (updates.amount !== undefined) updatedPayload.amount = Number(updates.amount)

      const cleanFormId = updatedPayload.id || identifier
      const cleanSlug = updatedPayload.slug || identifier

      const routeRecord = {
        form_id: cleanFormId,
        slug: cleanSlug.toLowerCase(),
        merchant_id: updatedPayload.merchant_id || route?.merchant_id || null,
        payload: updatedPayload,
        updated_at: new Date().toISOString()
      }

      if (cleanSlug) routeBySlug.set(cleanSlug.toLowerCase(), routeRecord)
      if (cleanFormId) {
        routeById.set(cleanFormId.toLowerCase().replace(/-/g, ''), routeRecord)
        routeById.set(cleanFormId.toLowerCase(), routeRecord)
      }

      saveRoutesToDisk()

      // Also sync to Supabase payment_forms if available
      try {
        const admin = getAdminClient()
        if (admin) {
          const effectiveFormUuid = normalizeUuid(cleanFormId) || deterministicUuid(cleanFormId)
          await admin.from('payment_forms').update({
            title: updatedPayload.title,
            description: updatedPayload.description,
            amount: Number(updatedPayload.amount || 0),
            image_url: updatedPayload.image_url || null,
            fields: typeof updatedPayload.fields === 'string' ? updatedPayload.fields : JSON.stringify(updatedPayload.fields || []),
            products: typeof updatedPayload.products === 'string' ? updatedPayload.products : JSON.stringify(updatedPayload.products || []),
            theme: updatedPayload.theme,
            updated_at: new Date().toISOString()
          }).eq('id', effectiveFormUuid)
        }
      } catch (dbErr) {
        console.warn('[form-router] Update DB sync notice:', dbErr.message)
      }

      return res.json({ ok: true, form: updatedPayload })
    } catch (err) {
      console.error('[form-router] Error updating form:', err.message)
      return res.status(500).json({ ok: false, error: err.message })
    }
  }

  router.patch('/forms/:slugOrId', handleUpdateForm)
  router.post('/forms/:slugOrId/update', handleUpdateForm)

  // ──────────────────────────────────────────────────────────────────────────
  // GET /v1/forms/:slugOrId
  // Resolves form config, fields, products, theme, and closing status
  // ──────────────────────────────────────────────────────────────────────────
  router.get(['/forms/:slugOrId', '/hosted-forms/:slugOrId'], async (req, res) => {
    const identifier = String(req.params.slugOrId || '').trim()
    const normalizedIdentifier = identifier.toLowerCase()
    const compactId = normalizedIdentifier.replace(/-/g, '')

    console.log(`[form-router] Resolving form: ${identifier}`)

    let form = null

    // 1. Check in-memory route cache
    let route = routeBySlug.get(normalizedIdentifier) || routeById.get(compactId) || routeById.get(normalizedIdentifier)
    if (!route) {
      const altSlug = normalizedIdentifier.startsWith('pay-') ? normalizedIdentifier.replace(/^pay-/, '') : `pay-${normalizedIdentifier}`
      route = routeBySlug.get(altSlug)
    }

    // 1b. Reload from disk if not found in memory (in case written by another worker or process)
    if (!route) {
      try {
        if (fs.existsSync(ROUTES_FILE)) {
          const raw = fs.readFileSync(ROUTES_FILE, 'utf8')
          const items = JSON.parse(raw || '[]')
          for (const item of items) {
            if (item.slug) routeBySlug.set(item.slug.toLowerCase(), item)
            if (item.form_id) {
              const cleanId = item.form_id.toLowerCase().replace(/-/g, '')
              routeById.set(cleanId, item)
              routeById.set(item.form_id.toLowerCase(), item)
            }
          }
          route = routeBySlug.get(normalizedIdentifier) || routeById.get(compactId) || routeById.get(normalizedIdentifier)
          if (!route) {
            const altSlug = normalizedIdentifier.startsWith('pay-') ? normalizedIdentifier.replace(/^pay-/, '') : `pay-${normalizedIdentifier}`
            route = routeBySlug.get(altSlug)
          }
        }
      } catch (_) {}
    }

    if (route && route.payload) {
      form = { ...route.payload, merchant_id: route.payload.merchant_id || route.merchant_id || null }
    }

    // 2. Query Admin DB payment_forms if not found in cache or payload missing
    if (!form) {
      try {
        const admin = getAdminClient()
        const parsedUuid = normalizeUuid(identifier)
        let query = admin.from('payment_forms').select('*')
        if (parsedUuid) {
          query = query.or(`id.eq.${parsedUuid},slug.eq.${identifier}`)
        } else {
          query = query.eq('slug', identifier)
        }

        let { data, error } = await query.maybeSingle()
        if ((error || !data) && !parsedUuid) {
          // Try alt slug in DB
          const altSlug = identifier.startsWith('pay-') ? identifier.replace(/^pay-/, '') : `pay-${identifier}`
          const altRes = await admin.from('payment_forms').select('*').eq('slug', altSlug).maybeSingle()
          if (!altRes.error && altRes.data) {
            data = altRes.data
            error = null
          }
        }

        if (!error && data) {
          form = data
          // Update cache
          if (form.slug) routeBySlug.set(form.slug.toLowerCase(), { form_id: form.id, slug: form.slug, payload: form })
          if (form.id) routeById.set(form.id.toLowerCase().replace(/-/g, ''), { form_id: form.id, slug: form.slug, payload: form })
        }
      } catch (err) {
        console.warn(`[form-router] Admin DB lookup error for ${identifier}:`, err.message)
      }
    }

    // 3. Fallback: check merchant DB if credentials exist
    if (!form && route && route.project_url && route.publishable_key) {
      try {
        const headers = {
          apikey: route.publishable_key,
          Authorization: `Bearer ${route.publishable_key}`
        }
        const resp = await fetch(`${route.project_url}/rest/v1/payment_forms?slug=eq.${encodeURIComponent(identifier)}&select=*`, { headers })
        if (resp.ok) {
          const list = await resp.json()
          if (list && list.length > 0) form = list[0]
        }
      } catch (e) {
        console.warn('[form-router] Merchant DB direct lookup warning:', e.message)
      }
    }

    if (form) {
      form.merchant_id = form.merchant_id || route?.merchant_id || null
    }

    if (!form) {
      return res.status(404).json({ ok: false, error: 'Payment form not found or inactive.' })
    }

    // Parse fields, products, theme if stringified
    try {
      if (typeof form.fields === 'string') form.fields = JSON.parse(form.fields)
    } catch { form.fields = [] }

    try {
      if (typeof form.products === 'string') form.products = JSON.parse(form.products)
    } catch { form.products = [] }

    try {
      if (typeof form.pages === 'string') form.pages = JSON.parse(form.pages)
    } catch { form.pages = [] }

    const theme = (form.theme && typeof form.theme === 'object')
      ? form.theme
      : (typeof form.theme === 'string' ? JSON.parse(form.theme || '{}') : {})

    form.theme = theme

    // Check closing status
    const deadlineEpoch = Number(theme.closing_deadline_epoch || theme.closingDeadlineEpoch || 0)
    const isTimelineClosed = (theme.enable_closing_timeline === true || theme.enableClosingTimeline === true) && deadlineEpoch > 0 && Date.now() > deadlineEpoch
    const maxResponses = Number(theme.max_responses || theme.maxResponses || 1000)
    const isLimitClosed = (theme.close_after_limit === true || theme.closeAfterLimit === true) && Number(form.submissions_count || 0) >= Math.max(1, maxResponses)

    const isClosed = isTimelineClosed || isLimitClosed
    const closedMessage = theme.closed_message || theme.closedMessage || 'This form is no longer accepting responses.'

    // Increment view count asynchronously
    try {
      form.views_count = (form.views_count || 0) + 1
      const admin = getAdminClient()
      admin.from('payment_forms')
        .update({ views_count: form.views_count })
        .eq('id', form.id)
        .then(() => {})
        .catch(() => {})
    } catch {}

    // Fetch live merchant payment gateway settings (allowed methods, receiving numbers)
    let gatewayConfig = null
    try {
      if (form.merchant_id) {
        gatewayConfig = await getMerchantGatewayConfig(form.merchant_id)
      }
    } catch (gwErr) {
      console.warn(`[form-router] Gateway config lookup notice for merchant ${form.merchant_id}:`, gwErr.message)
    }

    return res.json({
      ok: true,
      form: {
        id: form.id,
        merchant_id: form.merchant_id || null,
        title: form.title || 'Hosted Payment Form',
        description: form.description || '',
        slug: form.slug,
        amount: Number(form.amount || 0),
        status: form.status || 'PUBLISHED',
        fields: form.fields || [],
        products: form.products || [],
        pages: form.pages || [],
        theme: form.theme || {},
        logo_url: form.logo_url || theme.logo_url || null,
        banner_url: form.banner_url || theme.banner_url || null,
        submissions_count: form.submissions_count || 0,
        views_count: form.views_count || 0,
        gateway_config: gatewayConfig ? {
          enabled_methods: gatewayConfig.enabled_methods || { bKash: true, Nagad: true, Rocket: true, Upay: true },
          receiving_numbers: gatewayConfig.receiving_numbers || {},
          qr_codes: gatewayConfig.qr_codes || {},
          merchant_name: gatewayConfig.merchant_name || form.title,
          merchant_logo_url: gatewayConfig.merchant_logo_url || form.logo_url
        } : null,
        closing_status: {
          closed: isClosed,
          message: closedMessage,
          is_timeline_closed: isTimelineClosed,
          is_limit_closed: isLimitClosed,
          deadline_epoch: deadlineEpoch
        }
      }
    })
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/forms/:slugOrId/submit & /submissions
  // Secure Form Submission Handler
  // ──────────────────────────────────────────────────────────────────────────
  router.post(['/forms/:slugOrId/submit', '/forms/:slugOrId/submissions', '/hosted-forms/:slugOrId/submit', '/hosted-forms/:slugOrId/submissions'], async (req, res) => {
    try {
      const identifier = String(req.params.slugOrId || '').trim()
      const normalizedIdentifier = identifier.toLowerCase()
      const compactId = normalizedIdentifier.replace(/-/g, '')

      const {
        answers = {},
        customer_phone = '',
        customer_email = '',
        customer_name = '',
        selected_product_id = null,
        quantity = 1,
        payment_method = 'bKash',
        _hp_check = '',
        request_id = null
      } = req.body || {}

      // 1. Anti-spam honeypot
      if (_hp_check) {
        return res.status(400).json({ ok: false, error: 'Spam submission detected.' })
      }

      // 2. Resolve form
      let form = null
      const route = routeBySlug.get(normalizedIdentifier) || routeById.get(compactId) || routeById.get(normalizedIdentifier)
      if (route && route.payload) form = { ...route.payload, merchant_id: route.payload.merchant_id || route.merchant_id || null }

      if (!form) {
        const admin = getAdminClient()
        const parsedUuid = normalizeUuid(identifier)
        let query = admin.from('payment_forms').select('*')
        if (parsedUuid) query = query.or(`id.eq.${parsedUuid},slug.eq.${identifier}`)
        else query = query.eq('slug', identifier)
        const { data } = await query.maybeSingle()
        if (data) form = data
      }

      if (form) {
        form.merchant_id = form.merchant_id || route?.merchant_id || null
      }

      if (!form) {
        return res.status(404).json({ ok: false, error: 'Form not found or has been removed.' })
      }

      // Parse theme
      const theme = (form.theme && typeof form.theme === 'object')
        ? form.theme
        : (typeof form.theme === 'string' ? JSON.parse(form.theme || '{}') : {})

      // 3. Check closing status
      const deadlineEpoch = Number(theme.closing_deadline_epoch || theme.closingDeadlineEpoch || 0)
      if ((theme.enable_closing_timeline === true || theme.enableClosingTimeline === true) && deadlineEpoch > 0 && Date.now() > deadlineEpoch) {
        return res.status(409).json({ ok: false, error: theme.closed_message || 'This form is no longer accepting responses (Deadline expired).' })
      }
      const maxResponses = Number(theme.max_responses || theme.maxResponses || 1000)
      if ((theme.close_after_limit === true || theme.closeAfterLimit === true) && Number(form.submissions_count || 0) >= Math.max(1, maxResponses)) {
        return res.status(409).json({ ok: false, error: theme.closed_message || 'This form has reached its maximum allowed responses.' })
      }

      // 4. Calculate amount
      const isPaymentEnabled = theme.enable_payment !== false && theme.enablePayment !== false
      let calculatedAmount = 0
      let productName = form.title || 'Form Order'

      if (isPaymentEnabled) {
        let products = []
        try {
          products = typeof form.products === 'string' ? JSON.parse(form.products) : (form.products || [])
        } catch {}

        // Check if selected products array or single product exists
        const selectedProductsList = Array.isArray(req.body.selected_products) ? req.body.selected_products : []
        if (selectedProductsList.length > 0 && products.length > 0) {
          for (const sp of selectedProductsList) {
            const prod = products.find(p => String(p.id) === String(sp.id))
            const uPrice = Number(sp.price || sp.unit_price || (prod ? (prod.sale_price > 0 ? prod.sale_price : prod.price) : 0))
            const uQty = Math.max(1, Number(sp.quantity || sp.qty || 1))
            calculatedAmount += (uPrice * uQty)
          }
          if (selectedProductsList[0]?.title) {
            productName = selectedProductsList.map(p => p.title || p.name).filter(Boolean).join(', ')
          }
        } else if (selected_product_id && products.length > 0) {
          const prod = products.find(p => String(p.id) === String(selected_product_id))
          if (prod) {
            const unitPrice = prod.sale_price > 0 ? prod.sale_price : (prod.salePrice > 0 ? prod.salePrice : prod.price)
            calculatedAmount = Number(unitPrice || 0) * Math.max(1, Number(quantity || 1))
            productName = prod.title || productName
          }
        }

        // Check product component and priced options in fields
        if (form.fields) {
          const fields = Array.isArray(form.fields) ? form.fields : []
          for (const f of fields) {
            const fType = String(f.type || '').toUpperCase()
            const ansVal = answers[f.id]

            if (fType === 'PRODUCT' || fType === 'PRODUCT_LIST') {
              if (ansVal && typeof ansVal === 'object') {
                const pPrice = Number(ansVal.price || ansVal.unit_price || f.minValue || f.defaultValue || 0)
                const pQty = Number(ansVal.quantity || ansVal.qty || 1)
                calculatedAmount += (pPrice * pQty)
              } else if (f.minValue && Number(f.minValue) > 0 && calculatedAmount === 0) {
                calculatedAmount += Number(f.minValue)
              }
            } else if (fType === 'CUSTOM_AMOUNT' && ansVal) {
              const custVal = parseFloat(ansVal)
              if (!isNaN(custVal) && custVal > 0) calculatedAmount += custVal
            } else if (fType === 'DONATION' && ansVal) {
              const donationAmt = parseAmountFromText(ansVal)
              if (donationAmt > 0) calculatedAmount += donationAmt
            } else if ((fType === 'RADIO' || fType === 'DROPDOWN' || fType === 'MCQ') && ansVal) {
              const optAmt = parseAmountFromText(ansVal)
              if (optAmt > 0) {
                const qMultiplier = Math.max(1, Number(quantity || answers['quantity'] || 1))
                calculatedAmount += (optAmt * qMultiplier)
              }
            } else if ((fType === 'CHECKBOX' || fType === 'CHECKLIST' || fType === 'MULTI_SELECT') && Array.isArray(ansVal)) {
              for (const item of ansVal) {
                const optAmt = parseAmountFromText(item)
                if (optAmt > 0) calculatedAmount += optAmt
              }
            }
          }
        }

        // Fallback to submitted amount or form-level amount
        if (calculatedAmount === 0 && Number(req.body.amount || req.body.calculated_amount) > 0) {
          calculatedAmount = Number(req.body.amount || req.body.calculated_amount)
        } else if (calculatedAmount === 0 && Number(form.amount) > 0) {
          calculatedAmount = Number(form.amount)
        }

        // Tax calculation
        const taxPercent = Number(theme.tax_percent || theme.taxPercent || 0)
        if (taxPercent > 0 && calculatedAmount > 0) {
          calculatedAmount += (calculatedAmount * taxPercent / 100)
        }
        // Coupon / Promo Code discount calculation
        const couponCode = req.body.coupon_code || answers['coupon_code'] || answers['promo_code'] || null
        let discountAmount = parseFloat(req.body.discount_amount || 0) || 0

        if (couponCode && calculatedAmount > 0) {
          if (discountAmount <= 0) {
            const couponFields = (form.fields || []).filter(f => String(f.type || '').toUpperCase() === 'COUPON')
            let matchedCoupon = null
            for (const cf of couponFields) {
              const opts = Array.isArray(cf.options) ? cf.options : []
              for (const opt of opts) {
                const parts = String(opt).split(':').map(s => s.trim())
                if (parts[0] && parts[0].toUpperCase() === String(couponCode).trim().toUpperCase()) {
                  matchedCoupon = parts
                  break
                }
              }
              if (matchedCoupon) break
            }
            if (matchedCoupon) {
              const discVal = matchedCoupon[1] || '0'
              if (discVal.endsWith('%')) {
                const pct = parseFloat(discVal) || 0
                discountAmount = Math.round(calculatedAmount * (pct / 100))
              } else {
                discountAmount = parseFloat(discVal) || 0
              }
            }
          }
          if (discountAmount > 0) {
            calculatedAmount = Math.max(0, Math.round((calculatedAmount - discountAmount) * 100) / 100)
            answers['applied_coupon'] = {
              code: String(couponCode).trim().toUpperCase(),
              discount: discountAmount
            }
          }
        }
      }

      calculatedAmount = Math.round(calculatedAmount * 100) / 100
      const paymentRequired = isPaymentEnabled && calculatedAmount > 0

      // 5. Build submission payload
      const submissionId = 'sub_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7)
      const clientName = customer_name || answers['name'] || answers['customer_name'] || 'Customer'
      const clientPhone = customer_phone || answers['phone'] || answers['mobile'] || 'N/A'
      const clientEmail = customer_email || answers['email'] || ''

      const submissionRecord = {
        id: submissionId,
        form_id: form.id,
        form_slug: form.slug,
        merchant_id: form.merchant_id,
        customer_name: clientName,
        customer_phone: clientPhone,
        customer_email: clientEmail,
        answers: answers,
        amount: calculatedAmount,
        payment_required: paymentRequired,
        payment_method: payment_method,
        created_at: new Date().toISOString()
      }

      // Store submission in memory and try DB
      if (!formSubmissionsMemory.has(form.id)) formSubmissionsMemory.set(form.id, [])
      formSubmissionsMemory.get(form.id).unshift(submissionRecord)

      // Increment form submission count
      form.submissions_count = (form.submissions_count || 0) + 1
      try {
        const admin = getAdminClient()
        admin.from('payment_forms')
          .update({ submissions_count: form.submissions_count })
          .eq('id', form.id)
          .then(() => {})
          .catch(() => {})

        admin.from('form_submissions')
          .insert({
            id: normalizeUuid(submissionId) || undefined,
            form_id: form.id,
            request_id: request_id || normalizeUuid(submissionId) || undefined,
            customer_name: clientName,
            customer_phone: clientPhone,
            customer_email: clientEmail,
            amount: calculatedAmount,
            answers: answers,
            payment_method: payment_method,
            payment_status: paymentRequired ? 'PENDING' : 'FREE'
          })
          .then(() => {})
          .catch(() => {})
      } catch {}

      // Mirror submission to merchant's own Supabase DB so Android app can read it
      try {
        if (form.merchant_id) {
          const creds = await getMerchantCredentials(form.merchant_id)
          if (creds && creds.supabase_url && creds.supabase_anon_key) {
            const { createClient } = await import('@supabase/supabase-js')
            const mClient = createClient(creds.supabase_url, creds.supabase_anon_key, {
              auth: { persistSession: false, autoRefreshToken: false }
            })
            await mClient.from('form_submissions').insert({
              id: normalizeUuid(submissionId) || undefined,
              form_id: form.id,
              form_slug: form.slug,
              customer_name: clientName,
              customer_phone: clientPhone,
              customer_email: clientEmail,
              answers: answers,
              amount: calculatedAmount,
              payment_method: payment_method,
              payment_status: paymentRequired ? 'PENDING' : 'FREE',
              created_at: new Date().toISOString()
            })
            await mClient.from('merchant_notifications').insert({
              id: crypto.randomUUID(),
              merchant_id: form.merchant_id,
              type: 'FORM_SUBMISSION',
              title: `New form response: ${form.title || 'Your Form'}`,
              message: `${clientName} submitted a response${paymentRequired ? ` for ৳${calculatedAmount}` : ''}`,
              severity: 'INFO',
              entity_type: 'FORM_SUBMISSION',
              entity_id: normalizeUuid(submissionId) || undefined,
              created_at: new Date().toISOString(),
              read_at: null
            })
            console.log('[form-router] Submission & notification mirrored to merchant DB')
          }
        }
      } catch (mSubErr) {
        console.warn('[form-router] Merchant DB submission mirror notice:', mSubErr.message)
      }

      // Emit realtime WebSocket event to merchant dashboard
      if (io && form.merchant_id) {
        io.to(`merchant:${form.merchant_id}`).emit('form_submission_received', {
          submission_id: submissionId,
          form_id: form.id,
          form_title: form.title,
          customer_name: clientName,
          customer_phone: clientPhone,
          amount: calculatedAmount,
          answers,
          payment_required: paymentRequired,
          timestamp: new Date().toISOString()
        })
        console.log(`[form-router] Realtime submission broadcasted to merchant:${form.merchant_id}`)
      }

      // Insert in-app notification for merchant
      try {
        if (form.merchant_id) {
          const admin = getAdminClient()
          await admin.from('merchant_notifications').insert({
            id: crypto.randomUUID(),
            merchant_id: form.merchant_id,
            type: 'FORM_SUBMISSION',
            title: `New form response: ${form.title || 'Your Form'}`,
            message: `${clientName} submitted a response${paymentRequired ? ` for ৳${calculatedAmount}` : ''}`,
            severity: 'INFO',
            entity_type: 'FORM_SUBMISSION',
            entity_id: submissionId,
            created_at: new Date().toISOString(),
            read_at: null
          })
          console.log('[form-router] Merchant notification inserted')
        }
      } catch (notifErr) {
        console.warn('[form-router] Notification insert notice:', notifErr.message)
      }

      // Send SMS Notification if configured
      if (theme.sms_notifications && theme.notification_sms_number && io) {
        try {
          io.emit('form_sms_alert', {
            recipient: theme.notification_sms_number,
            message: `SwapnoPay: New form submission on '${form.title}' from ${clientName} (${clientPhone}). Amount: BDT ${calculatedAmount}.`
          })
          console.log(`[form-router] Dispatched SMS notification to ${theme.notification_sms_number}`)
        } catch (smsErr) {
          console.warn('[form-router] SMS notification warning:', smsErr.message)
        }
      }

      // Webhook Callback if configured
      if (theme.payment_callback_enabled && theme.payment_callback_url) {
        fetch(theme.payment_callback_url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            event: 'form_submission',
            form_id: form.id,
            form_slug: form.slug,
            submission_id: submissionId,
            customer_name: clientName,
            customer_phone: clientPhone,
            customer_email: clientEmail,
            amount: calculatedAmount,
            answers: answers,
            payment_required: paymentRequired,
            timestamp: new Date().toISOString()
          })
        }).catch(cbErr => console.warn('[form-router] Webhook callback failed:', cbErr.message))
      }

      // 6. Handle Payment Order Creation if required
      if (paymentRequired) {
        const orderUuid = crypto.randomUUID()
        const tranId = 'TRX-' + Date.now()

        const orderRecord = {
          id: orderUuid,
          tran_id: tranId,
          merchant_id: form.merchant_id,
          amount: calculatedAmount,
          status: 'PENDING',
          cus_name: clientName,
          cus_phone: clientPhone,
          cus_email: clientEmail,
          product_name: productName,
          payment_method: payment_method,
          created_at: new Date().toISOString()
        }

        // Create order in platform admin DB
        try {
          const admin = getAdminClient()
          await admin.from('orders').insert(orderRecord)
        } catch (ordErr) {
          console.warn('[form-router] Admin order insert notice:', ordErr.message)
        }

        // Record pending payment event in admin DB
        try {
          await recordPaymentEvent(orderUuid, {
            tran_id: tranId,
            status: 'PENDING',
            amount: calculatedAmount,
            merchant_id: form.merchant_id,
            cus_name: clientName,
            sender_number: clientPhone,
            cus_email: clientEmail,
            product_name: productName,
            payment_method: payment_method
          })
        } catch (evtErr) {
          console.warn('[form-router] Payment event record notice:', evtErr.message)
        }

        // Mirror order into merchant's own Supabase DB so foreign keys for appeals succeed
        try {
          if (form.merchant_id) {
            const creds = await getMerchantCredentials(form.merchant_id)
            if (creds?.supabase_url && creds?.supabase_anon_key) {
              const { createClient } = await import('@supabase/supabase-js')
              const mClient = createClient(creds.supabase_url, creds.supabase_anon_key, {
                auth: { persistSession: false, autoRefreshToken: false }
              })
              await mClient.from('orders').insert(orderRecord)
            }
          }
        } catch (mOrdErr) {
          console.warn('[form-router] Merchant DB order mirror notice:', mOrdErr.message)
        }

        orderToFormSubmissionMap.set(orderUuid, {
          form_id: form.id,
          submission_id: submissionId,
          merchant_id: form.merchant_id,
          form_slug: form.slug,
          amount: calculatedAmount
        })
        orderToFormSubmissionMap.set(tranId, {
          form_id: form.id,
          submission_id: submissionId,
          merchant_id: form.merchant_id,
          form_slug: form.slug,
          amount: calculatedAmount
        })

        const reqOrigin = req.get('host') ? `${req.protocol}://${req.get('host')}` : null
        const publicOrigin = process.env.PAYMENT_ROUTER_ORIGIN || reqOrigin || 'https://pay.swapnopay.top'
        let merchantParam = form.merchant_id ? `&merchant_id=${encodeURIComponent(form.merchant_id)}` : ''
        const methodParam = payment_method ? `&method=${encodeURIComponent(payment_method)}` : ''
        let numberParam = ''
        let accTypeParam = ''
        let logoParam = ''

        // Resolve merchant receiving numbers, account types, and logo for instant display
        try {
          const { getMerchantGatewayConfig } = await import('../services/adminSupabase.js')
          const mConfig = await getMerchantGatewayConfig(form.merchant_id || null)
          if (mConfig) {
            if (!merchantParam && mConfig.merchant_id) {
              merchantParam = `&merchant_id=${encodeURIComponent(mConfig.merchant_id)}`
            }
            const activeMethod = payment_method || 'bKash'
            const activeNum = (mConfig.receiving_numbers && mConfig.receiving_numbers[activeMethod]) || (mConfig.receiving_numbers && Object.values(mConfig.receiving_numbers)[0])
            if (activeNum) {
              numberParam = `&merchant_number=${encodeURIComponent(activeNum)}`
            }
            const activeType = (mConfig.account_types && mConfig.account_types[activeMethod]) || 'personal'
            if (activeType) {
              accTypeParam = `&account_type=${encodeURIComponent(activeType)}`
            }
            if (mConfig.merchant_logo_url && !mConfig.merchant_logo_url.startsWith('/data/')) {
              logoParam = `&merchant_logo=${encodeURIComponent(mConfig.merchant_logo_url)}`
            }
          }
        } catch (_) {}

        // Build return URLs so customer returns to the form on success or cancel
        const defaultSuccessUrl = (theme.redirect_type === 'REDIRECT_URL' && theme.redirect_url)
          ? theme.redirect_url
          : `${publicOrigin}/f/${form.slug || form.id}?status=paid&order_id=${encodeURIComponent(orderUuid)}&amount=${calculatedAmount}&trx_id=${encodeURIComponent(tranId)}`
        const defaultCancelUrl = `${publicOrigin}/f/${form.slug || form.id}?status=cancelled`
        const successParam = `&success_url=${encodeURIComponent(defaultSuccessUrl)}`
        const cancelParam = `&cancel_url=${encodeURIComponent(defaultCancelUrl)}`
        const redirectUrl = `/widget.html?order_id=${encodeURIComponent(orderUuid)}&amount=${calculatedAmount}&merchant_name=${encodeURIComponent(form.title || 'SwapnoPay')}&cus_name=${encodeURIComponent(clientName)}&cus_phone=${encodeURIComponent(clientPhone)}${merchantParam}${methodParam}${numberParam}${accTypeParam}${logoParam}${successParam}${cancelParam}`

        return res.json({
          ok: true,
          payment_required: true,
          order_id: orderUuid,
          merchant_id: form.merchant_id || null,
          transaction_id: tranId,
          amount: calculatedAmount,
          merchant_name: form.title,
          redirect_url: redirectUrl,
          gateway_url: redirectUrl
        })
      }

      // 7. Non-Payment Form: handle post-submission settings
      const redirectType = String(theme.redirect_type || theme.redirectType || 'SUCCESS_MSG').toUpperCase()
      const redirectUrl = theme.redirect_url || theme.redirectUrl || ''
      const redirectDelay = Number(theme.redirect_delay_seconds || theme.redirectDelaySec || 0)
      const successMessage = theme.success_message || theme.successMessage || 'Thank you! Your response has been submitted successfully.'
      const customHtml = theme.custom_html || theme.customHtmlContent || ''

      return res.json({
        ok: true,
        payment_required: false,
        submission_id: submissionId,
        redirect_type: redirectType,
        redirect_url: (redirectType === 'REDIRECT_URL' && redirectUrl) ? redirectUrl : null,
        redirect_delay: redirectDelay,
        success_message: successMessage,
        custom_html: (redirectType === 'CUSTOM_HTML' && customHtml) ? customHtml : null
      })
    } catch (err) {
      console.error('[form-router] Submission processing error:', err.message)
      return res.status(500).json({ ok: false, error: 'Failed to process form submission: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // GET /v1/forms/:slugOrId/submissions
  // Fetch responses for a form
  // ──────────────────────────────────────────────────────────────────────────
  router.get(['/forms/:slugOrId/submissions', '/hosted-forms/:slugOrId/submissions'], async (req, res) => {
    const identifier = String(req.params.slugOrId || '').trim()
    const memoryList = formSubmissionsMemory.get(identifier) || []

    try {
      const admin = getAdminClient()
      const parsedUuid = normalizeUuid(identifier)
      let q = admin.from('form_submissions').select('*').order('created_at', { ascending: false }).limit(100)
      if (parsedUuid) q = q.eq('form_id', parsedUuid)

      const { data, error } = await q
      if (!error && data && data.length > 0) {
        return res.json({ ok: true, submissions: data })
      }
    } catch {}

    return res.json({ ok: true, submissions: memoryList })
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/forms/upload-image
  // Upload product image or media image for form builder
  // Accepts: { image: base64String, filename?: string }
  // Returns: { ok: true, url: string, filename: string }
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/forms/upload-image', async (req, res) => {
    try {
      const { image, filename } = req.body || {}
      if (!image || typeof image !== 'string') {
        return res.status(400).json({ ok: false, error: 'Missing image base64 data' })
      }

      const cleanBase64 = image.replace(/^data:image\/\w+;base64,/, '')
      const buffer = Buffer.from(cleanBase64, 'base64')
      if (buffer.length === 0 || buffer.length > 10 * 1024 * 1024) {
        return res.status(400).json({ ok: false, error: 'Image size must be between 1 byte and 10 MB' })
      }

      const ext = (filename && path.extname(filename)) || '.jpg'
      const safeExt = ['.jpg', '.jpeg', '.png', '.webp'].includes(ext.toLowerCase()) ? ext.toLowerCase() : '.jpg'
      const generatedName = `prod_${Date.now()}_${crypto.randomUUID().substring(0, 8)}${safeExt}`

      // 1. Try Supabase Storage bucket 'products' if admin client is available
      try {
        const admin = getAdminClient()
        if (admin && admin.storage) {
          const mimeType = safeExt === '.png' ? 'image/png' : (safeExt === '.webp' ? 'image/webp' : 'image/jpeg')
          const { data: uploadData, error: uploadErr } = await admin.storage
            .from('products')
            .upload(generatedName, buffer, {
              contentType: mimeType,
              upsert: true
            })
          if (!uploadErr && uploadData?.path) {
            const { data: publicUrlData } = admin.storage.from('products').getPublicUrl(uploadData.path)
            if (publicUrlData?.publicUrl) {
              return res.status(201).json({ ok: true, url: publicUrlData.publicUrl, filename: generatedName })
            }
          }
        }
      } catch (storageErr) {
        console.warn('[form-upload] Supabase storage notice:', storageErr.message)
      }

      // 2. Fallback to local uploads/products directory
      const uploadsProductsDir = path.resolve(__dirname, '../../uploads/products')
      if (!fs.existsSync(uploadsProductsDir)) {
        fs.mkdirSync(uploadsProductsDir, { recursive: true })
      }
      const localFilePath = path.join(uploadsProductsDir, generatedName)
      fs.writeFileSync(localFilePath, buffer)

      const backendUrl = process.env.BACKEND_PUBLIC_URL || process.env.API_BASE_URL || 'https://api.swapnopay.top'
      const publicUrl = `${backendUrl.replace(/\/$/, '')}/uploads/products/${generatedName}`
      return res.status(201).json({ ok: true, url: publicUrl, filename: generatedName })
    } catch (err) {
      console.error('[form-upload] Error uploading image:', err.message)
      return res.status(500).json({ ok: false, error: 'Failed to upload image: ' + err.message })
    }
  })

  return router
}

/**
 * Updates form submission and payment_forms stats when an order is verified as PAID
 */
export async function handleFormPaymentPaid(orderId, trxId, amount, io = null) {
  if (!orderId) return false
  const cleanId = String(orderId).trim()
  const mapping = orderToFormSubmissionMap.get(cleanId) || orderToFormSubmissionMap.get(cleanId.toLowerCase())
  if (!mapping) return false

  const { form_id, submission_id, merchant_id, form_slug } = mapping
  console.log(`[form-router] 💳 Processing form payment completion for order ${cleanId} (Form: ${form_id}, Sub: ${submission_id})`)

  // 0. Update in-memory submission record
  let updatedRecord = null
  const memList = formSubmissionsMemory.get(form_id) || []
  for (const item of memList) {
    if (item.id === submission_id || item.order_id === cleanId) {
      item.payment_status = 'PAID'
      item.trx_id = trxId || 'PAID_GATEWAY'
      item.updated_at = new Date().toISOString()
      updatedRecord = item
      break
    }
  }
  if (!updatedRecord) {
    updatedRecord = {
      id: submission_id,
      form_id,
      order_id: cleanId,
      payment_status: 'PAID',
      trx_id: trxId || 'PAID_GATEWAY',
      amount: Number(amount || 0),
      updated_at: new Date().toISOString()
    }
    if (!formSubmissionsMemory.has(form_id)) {
      formSubmissionsMemory.set(form_id, [])
    }
    formSubmissionsMemory.get(form_id).unshift(updatedRecord)
  }

  // 1. Update form_submissions and payment_forms in admin DB
  try {
    const admin = getAdminClient()
    if (admin) {
      const parsedSubUuid = normalizeUuid(submission_id)
      const parsedOrderUuid = normalizeUuid(cleanId)
      let q = admin.from('form_submissions').update({
        payment_status: 'PAID',
        trx_id: trxId || 'PAID_GATEWAY',
        updated_at: new Date().toISOString()
      })
      if (parsedSubUuid && parsedOrderUuid) {
        q = q.or(`id.eq.${parsedSubUuid},request_id.eq.${parsedOrderUuid}`)
      } else if (parsedSubUuid) {
        q = q.eq('id', parsedSubUuid)
      } else if (parsedOrderUuid) {
        q = q.eq('request_id', parsedOrderUuid)
      }
      await q

      // Increment total_revenue on payment_forms
      const parsedFormUuid = normalizeUuid(form_id) || deterministicUuid(form_id || form_slug)
      const { data: currentForm } = await admin.from('payment_forms')
        .select('total_revenue, submissions_count')
        .eq('id', parsedFormUuid)
        .maybeSingle()

      if (currentForm) {
        const newRevenue = Number(currentForm.total_revenue || 0) + Number(amount || 0)
        await admin.from('payment_forms')
          .update({
            total_revenue: newRevenue,
            updated_at: new Date().toISOString()
          })
          .eq('id', parsedFormUuid)
      }
    }
  } catch (adminErr) {
    console.warn('[form-router] Admin DB payment update notice:', adminErr.message)
  }

  // 2. Mirror update to merchant's own Supabase DB
  if (merchant_id) {
    try {
      const creds = await getMerchantCredentials(merchant_id)
      if (creds?.supabase_url && creds?.supabase_anon_key) {
        const { createClient } = await import('@supabase/supabase-js')
        const mClient = createClient(creds.supabase_url, creds.supabase_anon_key, {
          auth: { persistSession: false, autoRefreshToken: false }
        })
        const parsedSubUuid = normalizeUuid(submission_id)
        const parsedOrderUuid = normalizeUuid(cleanId)
        let mq = mClient.from('form_submissions').update({
          payment_status: 'PAID',
          trx_id: trxId || 'PAID_GATEWAY',
          updated_at: new Date().toISOString()
        })
        if (parsedSubUuid && parsedOrderUuid) {
          mq = mq.or(`id.eq.${parsedSubUuid},request_id.eq.${parsedOrderUuid}`)
        } else if (parsedSubUuid) {
          mq = mq.eq('id', parsedSubUuid)
        } else if (parsedOrderUuid) {
          mq = mq.eq('request_id', parsedOrderUuid)
        }
        await mq
      }
    } catch (mErr) {
      console.warn('[form-router] Merchant DB submission payment update notice:', mErr.message)
    }

    // 3. Emit realtime event to merchant dashboard & app
    if (io) {
      try {
        io.to(`merchant:${merchant_id}`).emit('form_submission_paid', {
          order_id: cleanId,
          form_id,
          submission_id,
          amount: Number(amount || 0),
          trx_id: trxId,
          timestamp: new Date().toISOString()
        })
      } catch (ioErr) {
        console.warn('[form-router] Realtime emit notice:', ioErr.message)
      }
    }
  }

  return updatedRecord
}
