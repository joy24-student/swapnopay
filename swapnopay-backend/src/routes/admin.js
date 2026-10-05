// SwapnoPay Backend — Admin Routes
// All routes require X-Admin-Secret header
//
// GET  /v1/admin/gateway-settings         — read config from admin Supabase
// POST /v1/admin/gateway-settings         — write config to admin Supabase
// GET  /v1/admin/payment-events           — list recent platform payment events
// GET  /v1/admin/stats                    — aggregated platform statistics
// GET  /v1/admin/health                   — backend + admin Supabase health

import { Router } from 'express'
import { requireAdminSecret } from '../middleware/auth.js'
import {
  getGatewayConfig,
  setGatewayConfig,
  listPaymentEvents,
  pingAdminDatabase,
  listMerchants,
  getMerchantById,
  upsertMerchantProfile,
  updateMerchantStatus,
  deleteMerchantAccountPermanently,
  listApiKeyRecords,
  storeApiKeyRecord,
  revokeApiKeyRecord,
  getShowcaseConfig,
  setShowcaseConfig,
  upsertShowcaseConfig,
  uploadShowcaseImageToStorage,
  getPaymentStats,
  getMerchantDevicesList,
  getMfsPatterns,
  setMfsPatterns,
  getAdminSystemOverview,
  listPendingKycSubmissions,
  reviewMerchantKyc,
  getSubscriptionConfig,
  updateSubscriptionConfig,
  broadcastNotification,
  listBroadcastHistory,
  deleteBroadcastBatch,
} from '../services/adminSupabase.js'

const router = Router()
router.use((req, _res, next) => {
  if (req.path === '/keys' || req.path.startsWith('/keys/')) {
    return next('router')
  }
  next()
})
router.use(requireAdminSecret)

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/gateway-settings
// ────────────────────────────────────────────────────────────────────────────
router.get('/gateway-settings', async (_req, res) => {
  try {
    const config = await getGatewayConfig()
    res.json({ ok: true, config })
  } catch (err) {
    console.error('[admin/gateway-settings GET]', err.message)
    res.status(500).json({ error: 'Failed to read gateway configuration' })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// POST /v1/admin/gateway-settings
// ────────────────────────────────────────────────────────────────────────────
router.post('/gateway-settings', async (req, res) => {
  try {
    const incoming = req.body || {}

    // Validate enabled_methods
    if (incoming.enabled_methods) {
      const valid = ['bKash', 'Nagad', 'Rocket', 'Upay']
      for (const key of Object.keys(incoming.enabled_methods)) {
        if (!valid.includes(key)) {
          return res.status(400).json({ error: `Invalid payment method: ${key}` })
        }
      }
    }

    // Validate URLs
    for (const field of ['default_success_url', 'default_fail_url', 'default_cancel_url']) {
      if (incoming[field] && !isSafeHttpsUrl(incoming[field])) {
        return res.status(400).json({ error: `${field} must be a valid HTTPS URL` })
      }
    }

    // Validate numeric ranges
    if (incoming.min_amount !== undefined && (isNaN(incoming.min_amount) || incoming.min_amount < 1)) {
      return res.status(400).json({ error: 'min_amount must be at least 1' })
    }
    if (incoming.max_amount !== undefined && incoming.max_amount > 10_000_000) {
      return res.status(400).json({ error: 'max_amount must not exceed 10,000,000' })
    }

    const saved = await setGatewayConfig(incoming)
    console.log('[admin/gateway-settings POST] Config saved at', new Date().toISOString())
    res.json({ ok: true, config: saved })
  } catch (err) {
    console.error('[admin/gateway-settings POST]', err.message)
    res.status(500).json({ error: 'Failed to save gateway configuration: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/payment-events
// Query: ?limit=50&status=PAID&merchant_id=<uuid>
// ────────────────────────────────────────────────────────────────────────────
router.get('/payment-events', async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit || '50', 10), 200)
    const status = req.query.status || null
    const merchantId = req.query.merchant_id || null
    const events = await listPaymentEvents({ limit, status, merchantId })
    res.json({ ok: true, count: events.length, events })
  } catch (err) {
    console.error('[admin/payment-events]', err.message)
    res.status(500).json({ error: 'Failed to fetch payment events' })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/stats
// Returns aggregated platform statistics from admin Supabase
// ────────────────────────────────────────────────────────────────────────────
router.get('/stats', async (_req, res) => {
  try {
    const stats = await getPaymentStats()
    res.json({ ok: true, stats })
  } catch (err) {
    console.error('[admin/stats]', err.message)
    res.status(500).json({ error: 'Failed to compute stats' })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/health
// ────────────────────────────────────────────────────────────────────────────
router.get('/health', async (_req, res) => {
  const status = {
    backend: 'ok',
    admin_supabase: 'unknown',
    gateway_service: 'unknown',
    timestamp: new Date().toISOString(),
  }

  // Check admin Supabase
  try {
    const alive = await pingAdminDatabase()
    status.admin_supabase = alive ? 'ok' : 'error'
  } catch {
    status.admin_supabase = 'error'
  }

  // Check gateway-service (email receipt service)
  const receiptUrl = process.env.RECEIPT_SERVICE_URL
  if (receiptUrl) {
    try {
      const r = await fetch(`${receiptUrl}/healthz`, { signal: AbortSignal.timeout(3000) })
      status.gateway_service = r.ok ? 'ok' : `http_${r.status}`
    } catch {
      status.gateway_service = 'unreachable'
    }
  } else {
    status.gateway_service = 'not_configured'
  }

  const allOk = status.admin_supabase === 'ok'
  res.status(allOk ? 200 : 207).json(status)
})

// POST /v1/admin/test-email — Send a test payment receipt
router.post('/test-email', async (req, res) => {
  const { to, role = 'customer' } = req.body || {}
  const targetEmail = to || 'studentroutinemanager@gmail.com'
  try {
    const { sendPaymentReceipt, isMailerReady } = await import('../services/mailer.js')
    if (!isMailerReady()) {
      return res.status(503).json({ ok: false, error: 'Mailer transporter not ready. Check SMTP credentials in environment.' })
    }
    const messageId = await sendPaymentReceipt(role, targetEmail, {
      order_id: req.body?.order_id || 'TEST-ORD-' + Date.now(),
      tran_id: req.body?.tran_id || 'TEST-TRX-' + Date.now(),
      trx_id: req.body?.trx_id || '71QCLF08',
      amount: req.body?.amount || '2240.00',
      currency: req.body?.currency || 'BDT',
      payment_method: req.body?.payment_method || 'NAGAD',
      payment_time: req.body?.payment_time || new Date().toISOString(),
      merchant_name: req.body?.merchant_name || 'SwapnoPay Merchant Store',
      merchant_phone: req.body?.merchant_phone || '01700000000',
      merchant_address: req.body?.merchant_address || 'Level 4, Commercial Plaza, Dhanmondi, Dhaka, Bangladesh',
      merchant_website: req.body?.merchant_website || 'https://swapnopay.top',
      merchant_email: req.body?.merchant_email || 'support@swapnopay.top',
      merchant_logo_url: req.body?.merchant_logo_url || null,
      receiver_number: req.body?.receiver_number || '01711223344',
      cus_name: req.body?.cus_name || 'JOY SAHA',
      cus_phone: req.body?.cus_phone || '01735342839',
      customer_email: targetEmail,
      product_name: req.body?.product_name || 'E-commerce Purchase',
      verification: 'ADMIN_DIAGNOSTIC_TEST'
    })
    res.json({ ok: true, message: `Test receipt sent successfully to ${targetEmail}`, messageId })
  } catch (err) {
    console.error('[admin/test-email] Test email failed:', err.message)
    res.status(500).json({ ok: false, error: err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// MERCHANT MANAGEMENT API ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────

// GET /v1/admin/merchants — List all merchants
router.get('/merchants', async (_req, res) => {
  try {
    const list = await listMerchants()
    res.json({ ok: true, count: list.length, merchants: list })
  } catch (err) {
    console.error('[admin/merchants GET]', err.message)
    res.status(500).json({ error: 'Failed to fetch merchants: ' + err.message })
  }
})

// GET /v1/admin/merchants/:id — Get single merchant details
router.get('/merchants/:id', async (req, res) => {
  try {
    const merchant = await getMerchantById(req.params.id)
    if (!merchant) return res.status(404).json({ error: 'Merchant not found' })
    res.json({ ok: true, merchant })
  } catch (err) {
    console.error('[admin/merchants/:id GET]', err.message)
    res.status(500).json({ error: 'Failed to fetch merchant: ' + err.message })
  }
})

// POST /v1/admin/merchants — Register or update merchant profile & DB credentials
router.post('/merchants', async (req, res) => {
  try {
    const { merchant_id } = req.body
    if (!merchant_id) {
      return res.status(400).json({ error: 'merchant_id is required' })
    }
    const saved = await upsertMerchantProfile(req.body)
    res.json({ ok: true, merchant: saved })
  } catch (err) {
    console.error('[admin/merchants POST]', err.message)
    res.status(500).json({ error: 'Failed to save merchant: ' + err.message })
  }
})

const handleUpdateStatus = async (req, res) => {
  try {
    const { status } = req.body
    if (!status || !['ACTIVE', 'SUSPENDED', 'MAINTENANCE'].includes(status.toUpperCase())) {
      return res.status(400).json({ error: 'Valid status required (ACTIVE, SUSPENDED, MAINTENANCE)' })
    }
    const updated = await updateMerchantStatus(req.params.id, status.toUpperCase())
    res.json({ ok: true, merchant: updated })
  } catch (err) {
    console.error('[admin/merchants/:id/status]', err.message)
    res.status(500).json({ error: 'Failed to update status: ' + err.message })
  }
}
router.patch('/merchants/:id/status', handleUpdateStatus)
router.post('/merchants/:id/status', handleUpdateStatus)

// DELETE /v1/admin/merchants/:id — Permanently delete merchant account & data
router.delete('/merchants/:id', async (req, res) => {
  try {
    const merchantId = req.params.id
    if (!merchantId) {
      return res.status(400).json({ error: 'Merchant ID is required' })
    }
    const result = await deleteMerchantAccountPermanently(merchantId)
    res.json(result)
  } catch (err) {
    console.error('[admin/merchants/:id DELETE]', err.message)
    res.status(500).json({ error: 'Failed to delete merchant: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// API KEYS MANAGEMENT API ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────

// GET /v1/admin/api-keys — List all platform API keys
router.get('/api-keys', async (_req, res) => {
  try {
    const keys = await listApiKeyRecords()
    res.json({ ok: true, count: keys.length, api_keys: keys })
  } catch (err) {
    console.error('[admin/api-keys GET]', err.message)
    res.status(500).json({ error: 'Failed to list API keys: ' + err.message })
  }
})

// POST /v1/admin/api-keys/:id/revoke — Revoke API key
router.post('/api-keys/:id/revoke', async (req, res) => {
  try {
    await revokeApiKeyRecord(req.params.id)
    res.json({ ok: true, message: `API key ${req.params.id} revoked successfully` })
  } catch (err) {
    console.error('[admin/api-keys/:id/revoke POST]', err.message)
    res.status(500).json({ error: 'Failed to revoke API key: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/showcase — Get landing showcase config
// ────────────────────────────────────────────────────────────────────────────
router.get('/showcase', async (req, res) => {
  try {
    const key = req.query.key ? String(req.query.key) : 'main_showcase'
    const config = await getShowcaseConfig(key)
    res.json({ ok: true, key, config: config || {} })
  } catch (err) {
    console.error('[admin/showcase GET]', err.message)
    res.status(500).json({ error: 'Failed to read showcase configuration' })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// POST /v1/admin/showcase — Update/upgrade showcase images & content
// ────────────────────────────────────────────────────────────────────────────
router.post('/showcase', async (req, res) => {
  try {
    const payload = req.body || {}
    let saved
    if (payload.key && typeof payload.key === 'string' && payload.value !== undefined) {
      saved = await upsertShowcaseConfig(payload.key, payload.value)
      if (payload.key === 'system_config' && req.io) {
        const isBannerDisabled = payload.value?.notice_banner_enabled === false || payload.value?.system_notice_active === false || payload.value?.banner_enabled === false
        const rawNotice = typeof payload.value?.system_notice === 'string' ? payload.value.system_notice.trim() : ''
        const rawImageUrl = typeof payload.value?.banner_image_url === 'string' ? payload.value.banner_image_url.trim() : ''
        const rawLinkUrl = typeof payload.value?.banner_link_url === 'string' ? payload.value.banner_link_url.trim() : ''
        const rawTitle = typeof payload.value?.banner_title === 'string' ? payload.value.banner_title.trim() : ''
        const hasContent = Boolean(rawNotice || rawImageUrl)
        const isBannerEnabled = Boolean(!isBannerDisabled && hasContent)
        const notice = isBannerEnabled ? rawNotice : ''

        req.io.emit('system_notice', {
          system_notice: notice,
          notice_banner_enabled: isBannerEnabled,
          system_notice_active: isBannerEnabled,
          banner_image_url: isBannerEnabled ? rawImageUrl : '',
          banner_link_url: isBannerEnabled ? rawLinkUrl : '',
          banner_title: isBannerEnabled ? rawTitle : '',
          title: rawTitle || 'System Notice',
          message: notice,
          updated_at: new Date().toISOString()
        })
      }
    } else {
      saved = await setShowcaseConfig(payload)
    }
    console.log('[admin/showcase POST] Showcase config & images upgraded at', new Date().toISOString())
    res.json({ ok: true, config: saved })
  } catch (err) {
    console.error('[admin/showcase POST]', err.message)
    res.status(500).json({ error: 'Failed to upgrade showcase config: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// POST /v1/admin/showcase/upload — Upload real photo to Supabase Storage
// ────────────────────────────────────────────────────────────────────────────
router.post('/showcase/upload', async (req, res) => {
  try {
    const { base64, filename, content_type, folder } = req.body || {}
    const url = await uploadShowcaseImageToStorage({
      base64,
      filename,
      contentType: content_type,
      folder,
    })
    res.json({ ok: true, url })
  } catch (err) {
    console.error('[admin/showcase/upload POST]', err.message)
    res.status(500).json({ error: 'Failed to upload showcase photo: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/system-overview — Process memory, uptime, socket & DB telemetry
// ────────────────────────────────────────────────────────────────────────────
router.get('/system-overview', async (req, res) => {
  try {
    const overview = await getAdminSystemOverview(req.heartbeatMap, req.io)
    res.json({ ok: true, ...overview })
  } catch (err) {
    console.error('[admin/system-overview]', err.message)
    res.status(500).json({ error: 'Failed to fetch system overview: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/merchants/:id/devices — List paired devices for merchant
// ────────────────────────────────────────────────────────────────────────────
router.get('/merchants/:id/devices', async (req, res) => {
  try {
    const devices = await getMerchantDevicesList(req.params.id)
    res.json({ ok: true, merchant_id: req.params.id, count: devices.length, devices })
  } catch (err) {
    console.error('[admin/merchants/:id/devices]', err.message)
    res.status(500).json({ error: 'Failed to fetch merchant devices: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET & POST /v1/admin/mfs-patterns — Dynamic SMS parsing regex rules
// ────────────────────────────────────────────────────────────────────────────
router.get('/mfs-patterns', async (_req, res) => {
  try {
    const patterns = await getMfsPatterns()
    res.json({ ok: true, patterns })
  } catch (err) {
    console.error('[admin/mfs-patterns GET]', err.message)
    res.status(500).json({ error: 'Failed to fetch MFS patterns: ' + err.message })
  }
})

router.post('/mfs-patterns', async (req, res) => {
  try {
    const incoming = req.body || {}
    const saved = await setMfsPatterns(incoming)
    console.log('[admin/mfs-patterns POST] Patterns updated successfully')
    res.json({ ok: true, message: 'MFS patterns updated successfully', patterns: saved })
  } catch (err) {
    console.error('[admin/mfs-patterns POST]', err.message)
    res.status(500).json({ error: 'Failed to save MFS patterns: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET & POST /v1/admin/kyc — Merchant KYC Verifications & Review
// ────────────────────────────────────────────────────────────────────────────
router.get('/kyc/submissions', async (_req, res) => {
  try {
    const list = await listPendingKycSubmissions()
    res.json({ ok: true, count: list.length, submissions: list })
  } catch (err) {
    console.error('[admin/kyc/submissions GET]', err.message)
    res.status(500).json({ error: 'Failed to fetch KYC submissions: ' + err.message })
  }
})

router.post('/kyc/:id/review', async (req, res) => {
  try {
    const { action, reason, reviewed_by } = req.body || {}
    if (!action || !['APPROVE', 'REJECT', 'VERIFY'].includes(action.toUpperCase())) {
      return res.status(400).json({ error: 'Action must be APPROVE or REJECT' })
    }
    const updated = await reviewMerchantKyc(req.params.id, {
      action: action.toUpperCase(),
      reason,
      reviewed_by: req.adminUser?.id || 'PLATFORM_ADMIN',
    })

    if (req.io) {
      const status = (action.toUpperCase() === 'REJECT') ? 'REJECTED' : 'VERIFIED'
      req.io.to(`merchant:${updated.id}`).emit('merchant:kyc_status', {
        status,
        reason: reason || null,
        updated_at: new Date().toISOString(),
      })
      req.io.emit('admin:kyc_reviewed', {
        merchant_id: req.params.id,
        status,
        reviewed_at: new Date().toISOString(),
      })
    }

    res.json({ ok: true, message: `KYC verification status updated for merchant ${req.params.id}`, merchant: updated })
  } catch (err) {
    console.error('[admin/kyc/:id/review POST]', err.message)
    res.status(500).json({ error: 'Failed to review KYC: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/subscription-config
// ────────────────────────────────────────────────────────────────────────────
router.get('/subscription-config', async (_req, res) => {
  try {
    const config = await getSubscriptionConfig()
    res.json({ ok: true, config })
  } catch (err) {
    console.error('[admin/subscription-config GET]', err.message)
    res.status(500).json({ error: 'Failed to read subscription configuration' })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// POST /v1/admin/subscription-config
// ────────────────────────────────────────────────────────────────────────────
router.post('/subscription-config', async (req, res) => {
  try {
    const incoming = req.body || {}
    const updated = await updateSubscriptionConfig(incoming)
    res.json({ ok: true, config: updated, message: 'Subscription configuration updated successfully' })
  } catch (err) {
    console.error('[admin/subscription-config POST]', err.message)
    res.status(500).json({ error: 'Failed to update subscription configuration: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// POST /v1/admin/notifications/broadcast
// Broadcast notifications to all or targeted merchant apps
// ────────────────────────────────────────────────────────────────────────────
router.post('/notifications/broadcast', async (req, res) => {
  try {
    const { title, message, type = 'ANNOUNCEMENT', severity = 'INFO', target = 'ALL', updateBanner = false } = req.body || {}

    if (!title || typeof title !== 'string' || !title.trim()) {
      return res.status(400).json({ error: 'Notification title is required' })
    }
    if (title.trim().length > 255) {
      return res.status(400).json({ error: 'Notification title must not exceed 255 characters' })
    }
    if (!message || typeof message !== 'string' || !message.trim()) {
      return res.status(400).json({ error: 'Notification message body is required' })
    }

    const validTypes = ['ANNOUNCEMENT', 'ALERT', 'SYSTEM', 'PROMOTION', 'INFO']
    const cleanType = String(type).toUpperCase()
    if (!validTypes.includes(cleanType)) {
      return res.status(400).json({ error: `Invalid notification type: ${type}. Allowed: ${validTypes.join(', ')}` })
    }

    const validSeverities = ['INFO', 'SUCCESS', 'WARNING', 'ERROR']
    const cleanSeverity = String(severity).toUpperCase()
    if (!validSeverities.includes(cleanSeverity)) {
      return res.status(400).json({ error: `Invalid severity: ${severity}. Allowed: ${validSeverities.join(', ')}` })
    }

    const result = await broadcastNotification({
      title: title.trim(),
      message: message.trim(),
      type: cleanType,
      severity: cleanSeverity,
      target,
      updateBanner: Boolean(updateBanner),
      adminJwt: req.adminJwt || null,
    })

    // Real-time distribution via Socket.io
    if (req.io) {
      req.io.emit('broadcast:notification', result)
      if (Boolean(updateBanner)) {
        const bannerText = result.system_notice || (title.trim() ? `${title.trim()} — ${message.trim()}` : message.trim())
        req.io.emit('system_notice', {
          system_notice: bannerText,
          notice_banner_enabled: true,
          system_notice_active: true,
          title: cleanType,
          message: message.trim(),
          severity: cleanSeverity,
          banner_updated: true,
          created_at: result.created_at || new Date().toISOString(),
        })
      }
      if (target && target !== 'ALL' && target !== 'ACTIVE') {
        req.io.to(`merchant:${target}`).emit('merchant:notification', result)
      }
    }

    res.json(result)
  } catch (err) {
    console.error('[admin/notifications/broadcast POST]', err.message)
    res.status(500).json({ error: 'Failed to broadcast notification: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// GET /v1/admin/notifications/broadcasts
// List recent broadcast history with delivery counts
// ────────────────────────────────────────────────────────────────────────────
router.get('/notifications/broadcasts', async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit || '50', 10), 100)
    const broadcasts = await listBroadcastHistory(limit, req.adminJwt || null)
    res.json({ ok: true, broadcasts })
  } catch (err) {
    console.error('[admin/notifications/broadcasts GET]', err.message)
    res.status(500).json({ error: 'Failed to fetch broadcast history: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// DELETE /v1/admin/notifications/broadcasts/:batchId
// Delete or recall a sent broadcast
// ────────────────────────────────────────────────────────────────────────────
router.delete('/notifications/broadcasts/:batchId', async (req, res) => {
  try {
    const { batchId } = req.params
    if (!batchId) {
      return res.status(400).json({ error: 'Broadcast batch ID is required' })
    }
    const result = await deleteBroadcastBatch(batchId, req.adminJwt || null)
    if (req.io) {
      req.io.emit('broadcast:deleted', { batch_id: batchId })
      if (result?.notice_cleared) {
        req.io.emit('system_notice', {
          system_notice: '',
          title: '',
          message: '',
          deleted_batch_id: batchId,
          updated_at: new Date().toISOString()
        })
      }
    }
    res.json(result)
  } catch (err) {
    console.error('[admin/notifications/broadcasts/:batchId DELETE]', err.message)
    res.status(500).json({ error: 'Failed to delete broadcast: ' + err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// POST /v1/admin/notifications/clear-banner
// Turn off and clear live announcement banner across all apps
// ────────────────────────────────────────────────────────────────────────────
router.post('/notifications/clear-banner', async (req, res) => {
  try {
    const { clearSystemNoticeBanner } = await import('../services/adminSupabase.js')
    const result = await clearSystemNoticeBanner(req.adminJwt || null)
    if (req.io) {
      req.io.emit('system_notice', {
        system_notice: '',
        notice_banner_enabled: false,
        system_notice_active: false,
        title: '',
        message: '',
        updated_at: new Date().toISOString()
      })
    }
    res.json(result)
  } catch (err) {
    console.error('[admin/notifications/clear-banner POST]', err.message)
    res.status(500).json({ error: 'Failed to clear announcement banner: ' + err.message })
  }
})

export { router as adminRouter }

function isSafeHttpsUrl(value) {
  try {
    const url = new URL(value)
    if (url.protocol !== 'https:') return false
    const h = url.hostname.toLowerCase()
    if (h === 'localhost' || h.endsWith('.local')) return false
    if (/^(127\.|10\.|192\.168\.|169\.254\.)/.test(h)) return false
    return true
  } catch {
    return false
  }
}
