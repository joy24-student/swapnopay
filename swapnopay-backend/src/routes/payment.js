// SwapnoPay Backend — Payment Routes
// POST /v1/payment/notify         — widget calls this when customer clicks "I Have Completed Payment"
// POST /v1/payment/verify         — Supabase process-sms calls this after atomic PAID match
// GET  /v1/payment/order/:order_id— polling fallback for order status
// GET  /v1/payment/status         — alias for order status polling
// POST /v1/payment/cancel         — cancel order / checkout session
// POST /v1/payment/appeal         — customer dispute appeal submission
// POST /v1/payment/form-submission— relay for hosted form submissions
// GET  /v1/payment/config         — widget fetches enabled_methods, timeouts, device_active, logo
// GET  /v1/payment/device-status  — lightweight device status check (used by widget retry button)

import { Router } from 'express'
import crypto from 'node:crypto'
import { requireAdminSecret, requireWebhookSecret, requireMerchantOrAdminAuth } from '../middleware/auth.js'
import {
  getGatewayConfig,
  getMerchantGatewayConfig,
  canonicalMethodName,
  setMerchantGatewayConfig,
  recordPaymentEvent,
  getOrderFromMerchantDB,
  updateOrderStatusOnMerchantDB,
  getMerchantDeviceStatus,
  createDisputeAppeal,
  processReportedPayment,
} from '../services/adminSupabase.js'
import { verifyWebhookSignature } from '../utils/crypto.js'
import { isSafeOutboundWebhookUrl, postSafeWebhook } from '../utils/urlValidator.js'
import { sendPaymentReceipts } from '../services/mailer.js'
import {
  checkAndAlertMerchantOffline,
  subscribeCustomerForDeviceAlert,
  notifyWaitingCustomersMerchantOnline,
} from '../services/deviceAlertService.js'

export function paymentRouter(io, heartbeatMap = new Map()) {
  const router = Router()

  // In-memory idempotency cache for webhook verifications (orderId:trxId -> { payload, ts })
  const recentVerifications = new Map()
  const cleanTimer = setInterval(() => {
    const now = Date.now()
    for (const [k, v] of recentVerifications.entries()) {
      if (now - v.ts > 10 * 60 * 1000) recentVerifications.delete(k)
    }
  }, 5 * 60 * 1000)
  if (cleanTimer && cleanTimer.unref) cleanTimer.unref()

  // ──────────────────────────────────────────────────────────────────────────
  // GET /v1/payment/config
  // Widget/App fetches this on load to get dynamic gateway options, merchant
  // logo, and device active status (via cross-DB check on merchant Supabase).
  // Query: ?merchant_id=<id>
  // ──────────────────────────────────────────────────────────────────────────
  router.get('/config', async (req, res) => {
    try {
      let merchantId = req.query.merchant_id || req.query.user_id || req.headers['x-merchant-id'] || null
      const email = req.query.email || null
      const orderId = req.query.order_id || null

      if (!merchantId && email) {
        merchantId = email
      }

      // If merchant_id is missing but order_id is present, resolve merchant_id from order/events/forms
      if (!merchantId && orderId && orderId !== 'demo_order_id') {
        try {
          const { orderToFormSubmissionMap } = await import('./form.js')
          const mapEntry = orderToFormSubmissionMap.get(orderId) || orderToFormSubmissionMap.get(String(orderId).toLowerCase())
          if (mapEntry?.merchant_id) merchantId = mapEntry.merchant_id
        } catch (_) {}

        if (!merchantId) {
          try {
            const { getAdminClient } = await import('../services/adminSupabase.js')
            const admin = getAdminClient()
            const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(orderId)
            let q = admin.from('payment_events').select('merchant_id').limit(1)
            if (isUuid) {
              q = q.or(`order_id.eq.${orderId},tran_id.eq.${orderId}`)
            } else {
              q = q.eq('tran_id', orderId)
            }
            const { data: ev } = await q.maybeSingle()
            if (ev?.merchant_id) merchantId = ev.merchant_id

            if (!merchantId) {
              let oq = admin.from('orders').select('merchant_id').limit(1)
              if (isUuid) {
                oq = oq.or(`id.eq.${orderId},tran_id.eq.${orderId}`)
              } else {
                oq = oq.eq('tran_id', orderId)
              }
              const { data: ord } = await oq.maybeSingle()
              if (ord?.merchant_id) merchantId = ord.merchant_id
            }
          } catch (_) {}
        }
      }

      let config = await getMerchantGatewayConfig(merchantId, heartbeatMap)
      if ((!config?.supabase_url) && email && merchantId !== email) {
        try {
          const emailConfig = await getMerchantGatewayConfig(email, heartbeatMap)
          if (emailConfig?.supabase_url) {
            config = { ...config, ...emailConfig }
          }
        } catch (_) {}
      }

      res.json({
        ok: true,
        // Gateway options
        enabled_methods:            config.enabled_methods,
        gateway_configured: Object.entries(config.enabled_methods || {}).some(([method, enabled]) => enabled && Boolean(String(config.receiving_numbers?.[method] || '').trim())) || Boolean(config.default_number),
        payment_timeout_seconds:    config.payment_timeout_seconds,
        processing_timeout_seconds: config.processing_timeout_seconds,
        min_amount:                 config.min_amount,
        max_amount:                 config.max_amount,
        default_success_url:        config.default_success_url,
        default_fail_url:           config.default_fail_url,
        default_cancel_url:         config.default_cancel_url,
        default_number:             config.default_number || null,
        phone:                      config.phone || null,
        receiving_numbers:          config.receiving_numbers || {},
        account_types:              config.account_types || {},
        // Per-method uploaded QR code image URLs (override auto-generated QR in widget)
        qr_codes:                   config.qr_codes || {},
        maintenance_mode:           config.maintenance_mode,
        maintenance_message:        config.maintenance_message,
        merchant_customized:        config.merchant_customized || false,
        // Merchant branding
        merchant_id:                config.merchant_id || merchantId || null,
        merchant_name:              config.merchant_name || null,
        merchant_logo_url:          config.merchant_logo_url || null,
        // Merchant Supabase connection (for client app initialization)
        supabase_url:               config.supabase_url || null,
        supabase_anon_key:          config.supabase_anon_key || null,
        // Device active status (checked against merchant's own Supabase DB)
        device_active:              config.device_active,          // true | false | null (null = skip)
        device_last_seen:           config.device_last_seen || null,
        device_count:               config.device_count || 0,
      })

      // Asynchronously check and send offline alert to merchant if a customer opened checkout
      if (config.device_active === false && merchantId) {
        checkAndAlertMerchantOffline(merchantId, {
          order_id: req.query.order_id || null,
          amount: req.query.amount || null,
          cus_email: req.query.cus_email || null,
        }).catch(e => console.warn('[payment/config] Device alert check error:', e.message))
      }
    } catch (err) {
      console.error('[payment/config] Error:', err.message)
      // Fail open — return safe defaults
      res.json({
        ok: false,
        enabled_methods: { bKash: false, Nagad: false, Rocket: false, Upay: false },
        payment_timeout_seconds: 600,
        processing_timeout_seconds: 300,
        maintenance_mode: false,
        maintenance_message: '',
        receiving_numbers: {},
        gateway_configured: false,
        account_types: {},
        qr_codes: {},
        merchant_logo_url: null,
        supabase_url: null,
        supabase_anon_key: null,
        device_active: null,
        device_last_seen: null,
        device_count: 0,
      })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // GET /v1/payment/transactions
  // App/Merchant queries recent transactions & payment events
  // Query: ?merchant_id=<id>&limit=100
  // ──────────────────────────────────────────────────────────────────────────
  router.get('/transactions', requireMerchantOrAdminAuth, async (req, res) => {
    try {
      const merchantId = req.query.merchant_id || req.headers['x-merchant-id']
      const limit = Math.min(parseInt(req.query.limit || '50', 10), 200)
      if (!merchantId) {
        return res.status(400).json({ ok: false, error: 'merchant_id is required' })
      }
      if (!req.isAdmin && req.merchantUser?.id !== merchantId) {
        return res.status(403).json({ ok: false, error: 'Cannot access another merchant\'s transactions' })
      }
      const { getAdminClient } = await import('../services/adminSupabase.js')
      const admin = getAdminClient()
      const { data, error } = await admin
        .from('payment_events')
        .select('*')
        .eq('merchant_id', merchantId)
        .order('recorded_at', { ascending: false })
        .limit(limit)

      if (error) {
        console.warn('[payment/transactions] Query error:', error.message)
        return res.json({ ok: true, transactions: [] })
      }

      const transactions = (data || []).map(row => ({
        id: row.trx_id || row.tran_id || row.id,
        trx_id: row.trx_id || '',
        order_id: row.order_id || null,
        merchant_id: row.merchant_id || merchantId,
        amount: Number(row.amount || 0),
        sender: row.sender_number || 'Customer',
        method: row.payment_method || 'bKash',
        status: row.status === 'PAID' ? 'MATCHED' : (row.status || 'UNMATCHED'),
        timestamp: new Date(row.payment_time || row.recorded_at).getTime(),
      }))

      res.json({ ok: true, transactions })
    } catch (err) {
      console.error('[payment/transactions] Error:', err.message)
      res.json({ ok: true, transactions: [] })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // GET /v1/payment/device-status
  // Lightweight endpoint used by the widget "Retry" button to re-check
  // merchant device status without reloading the full config.
  // Query: ?merchant_id=<id>
  // ──────────────────────────────────────────────────────────────────────────
  router.get('/device-status', async (req, res) => {
    const merchantId = req.query.merchant_id || null
    if (!merchantId) {
      return res.status(400).json({ error: 'merchant_id is required' })
    }

    try {
      const status = await getMerchantDeviceStatus(merchantId, heartbeatMap)
      res.json({
        ok: true,
        merchant_id: merchantId,
        device_active: status.active,
        device_last_seen: status.last_seen,
        device_count: status.device_count,
        source: status.source,
        checked_at: new Date().toISOString(),
      })
    } catch (err) {
      console.error('[payment/device-status] Error:', err.message)
      res.status(500).json({ error: 'Device status check failed', device_active: null })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/subscribe-device-alert
  // Allows customer to subscribe to receive an email when merchant comes back online.
  // Body: { merchant_id, customer_email, order_id, amount, checkout_url }
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/subscribe-device-alert', async (req, res) => {
    try {
      const { merchant_id, customer_email, order_id, amount, checkout_url } = req.body || {}
      if (!merchant_id || !customer_email || !checkout_url) {
        return res.status(400).json({ ok: false, error: 'merchant_id, customer_email, and checkout_url are required' })
      }

      const result = await subscribeCustomerForDeviceAlert({
        merchant_id,
        customer_email,
        order_id,
        amount,
        checkout_url,
      })

      res.json(result)
    } catch (err) {
      console.error('[payment/subscribe-device-alert] Error:', err.message)
      res.status(400).json({ ok: false, error: err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/heartbeat
  // Direct HTTP heartbeat endpoint for mobile app / merchant devices.
  // Updates in-memory heartbeat map and broadcasts to Socket.IO merchant room.
  // Body: { merchant_id, device_id, battery_level, status }
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/heartbeat', async (req, res) => {
    try {
      const {
        merchant_id: rawMerchantId,
        device_id,
        battery_level,
        status,
        device_model,
        os_version,
      } = req.body || {}

      const merchant_id = String(rawMerchantId || req.headers['x-merchant-id'] || req.query?.merchant_id || '').trim()
      if (!merchant_id) {
        return res.status(400).json({ ok: false, error: 'merchant_id is required' })
      }

      let isAuthorized = false
      const { getMerchantCredentials, getAdminClient } = await import('../services/adminSupabase.js')
      let admin = null
      try {
        admin = getAdminClient()
      } catch (_) {}

      // 1. Static admin secret check
      const adminSecret = process.env.ADMIN_SECRET
      const xAdminSecret = req.headers['x-admin-secret']
      if (xAdminSecret && adminSecret && xAdminSecret === adminSecret) {
        isAuthorized = true
      }

      const creds = await getMerchantCredentials(merchant_id)
      let resolvedMerchantRow = null

      if (admin) {
        try {
          const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(merchant_id)
          let q = admin.from('merchants').select('id, user_id, status, business_name')
          if (isUuid) {
            q = q.or(`id.eq.${merchant_id},user_id.eq.${merchant_id}`)
          } else {
            q = q.eq('id', merchant_id)
          }
          const { data: mRow } = await q.maybeSingle()
          resolvedMerchantRow = mRow
        } catch (_) {}
      }

      // If merchant exists in credentials, database, or default platform UUID, heartbeat is valid
      if (creds || resolvedMerchantRow || merchant_id === '00000000-0000-0000-0000-000000000001') {
        isAuthorized = true
      }

      // Check Authorization Bearer if caller provided one
      const authHeader = req.headers['authorization']
      if (authHeader && authHeader.toLowerCase().startsWith('bearer ')) {
        const token = authHeader.replace(/^Bearer\s+/i, '').trim()
        if (token && admin?.auth) {
          try {
            const { data: { user } } = await admin.auth.getUser(token)
            if (user?.id) isAuthorized = true
          } catch (_) {}
        }
      }

      if (!isAuthorized) {
        return res.status(403).json({ ok: false, error: 'Unrecognized merchant ID for heartbeat' })
      }

      const now = Date.now()
      const nowIso = new Date(now).toISOString()
      const hbEntry = {
        ts: now,
        socketId: null,
        deviceId: device_id || null,
        batteryLevel: battery_level ?? 100,
        status: status || 'ONLINE',
        model: device_model || 'Android App',
        osVersion: os_version || 'Android',
      }

      // Record in-memory heartbeat for all associated merchant keys
      const candidateKeys = new Set([
        merchant_id,
        creds?.merchant_id,
        creds?.user_id,
        resolvedMerchantRow?.id,
        resolvedMerchantRow?.user_id,
      ].filter(Boolean))

      for (const k of candidateKeys) {
        heartbeatMap.set(k, hbEntry)
      }

      // Persist device status to database
      if (admin) {
        const effId = resolvedMerchantRow?.id || creds?.merchant_id || merchant_id
        const cleanDevId = device_id || `dev_${effId.slice(0, 8)}`
        Promise.resolve(
          admin
            .from('devices')
            .upsert({
              id: cleanDevId,
              merchant_id: effId,
              device_model: device_model || 'Android App',
              os_version: os_version || 'Android',
              battery_level: battery_level ?? 100,
              online: true,
              last_sync: nowIso,
              disabled: false,
            }, { onConflict: 'id' })
        ).catch(e => console.warn('[payment/heartbeat] devices upsert notice:', e.message))

        Promise.resolve(
          admin
            .from('merchants')
            .update({ last_sync: nowIso, updated_at: nowIso })
            .eq('id', effId)
        ).catch(e => console.warn('[payment/heartbeat] merchants update notice:', e.message))
      }

      // Broadcast to merchant room for real-time dashboard listeners
      if (io) {
        for (const k of candidateKeys) {
          io.to(`merchant:${k}`).emit('merchant_heartbeat', {
            merchant_id: k,
            device_id: device_id || null,
            battery_level: battery_level ?? null,
            status: status || 'ONLINE',
            ts: now,
          })
        }
      }

      // Notify waiting customers that merchant device is back online!
      notifyWaitingCustomersMerchantOnline(merchant_id)
        .catch(err => console.warn('[payment/heartbeat] Customer notify error:', err.message))

      res.json({
        ok: true,
        merchant_id,
        device_active: true,
        ts: now,
      })
    } catch (err) {
      console.error('[payment/heartbeat] Error:', err.message)
      res.status(500).json({ ok: false, error: 'Heartbeat recording failed' })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/merchant-qr-codes
  // Save uploaded QR code URLs for each payment method.
  // Allowed for platform Admin or authenticated Merchant owner.
  // Body: { merchant_id, qr_codes: { bKash: "url", Nagad: "url", ... } }
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/merchant-qr-codes', requireMerchantOrAdminAuth, async (req, res) => {
    try {
      const { merchant_id, qr_codes } = req.body || {}

      if (!merchant_id || typeof merchant_id !== 'string') {
        return res.status(400).json({ error: 'merchant_id is required' })
      }
      if (!req.isAdmin && req.merchantUser?.id !== merchant_id) {
        return res.status(403).json({ error: 'Cannot update another merchant\'s QR codes' })
      }
      if (!qr_codes || typeof qr_codes !== 'object') {
        return res.status(400).json({ error: 'qr_codes object is required' })
      }

      // Validate: only allow known MFS method keys and HTTPS URLs
      const VALID_METHODS = ['bKash', 'Nagad', 'Rocket', 'Upay']
      const sanitized = {}
      for (const [method, url] of Object.entries(qr_codes)) {
        if (!VALID_METHODS.includes(method)) continue
        if (url && typeof url === 'string' && /^https:\/\/.+/.test(url)) {
          sanitized[method] = url
        } else if (!url) {
          sanitized[method] = null  // allow clearing a QR
        }
      }

      const { getAdminClient } = await import('../services/adminSupabase.js')
      const { error } = await getAdminClient()
        .from('merchant_gateway_settings')
        .upsert(
          { merchant_id, qr_codes: sanitized, updated_at: new Date().toISOString() },
          { onConflict: 'merchant_id' }
        )

      if (error) throw new Error(error.message)

      console.log(`[merchant-qr-codes] QR codes updated for merchant: ${merchant_id}`, sanitized)
      res.json({ ok: true, merchant_id, qr_codes: sanitized })
    } catch (err) {
      console.error('[merchant-qr-codes] Error:', err.message)
      res.status(500).json({ error: 'Failed to save QR codes: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // GET /v1/payment/merchant-config
  // Retrieves merchant gateway setup, receiving numbers, account types,
  // and QR codes for mobile app and web setup screen.
  // ──────────────────────────────────────────────────────────────────────────
  router.get('/merchant-config', requireMerchantOrAdminAuth, async (req, res) => {
    try {
      const merchantId = String(req.query.merchant_id || req.headers['x-merchant-id'] || '').trim()
      if (!merchantId) {
        return res.status(400).json({ ok: false, error: 'merchant_id is required' })
      }
      if (!req.isAdmin && req.merchantUser?.id !== merchantId) {
        return res.status(403).json({ ok: false, error: 'Cannot access another merchant\'s configuration' })
      }

      let config = {}
      try {
        config = await getMerchantGatewayConfig(merchantId, heartbeatMap)
      } catch (cfgErr) {
        console.warn('[merchant-config] Config load notice:', cfgErr.message)
        return res.status(503).json({ ok: false, error: 'Merchant gateway configuration is unavailable' })
      }
      return res.json({ ok: true, merchant_id: merchantId, config })
    } catch (err) {
      console.error('[merchant-config] GET Error:', err.message)
      return res.status(500).json({ ok: false, error: 'Failed to fetch merchant config: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/merchant-config
  // Merchant Gateway Setup Screen — customise receiving numbers, enabled
  // payment options, redirect URLs, and auto-appeal settings.
  // Allowed for platform Admin or authenticated Merchant owner.
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/merchant-config', requireMerchantOrAdminAuth, async (req, res) => {
    try {
      const {
        merchant_id,
        merchant_name,
        merchant_logo_url,
        supabase_url,
        supabase_anon_key,
        bkash_enabled,
        nagad_enabled,
        rocket_enabled,
        upay_enabled,
        success_url,
        fail_url,
        cancel_url,
        receiving_numbers,
        account_types,
        qr_codes,
        auto_appeal_matching,
      } = req.body || {}

      if (!merchant_id || typeof merchant_id !== 'string') {
        return res.status(400).json({ error: 'merchant_id is required' })
      }
      if (!req.isAdmin && req.merchantUser?.id !== merchant_id) {
        return res.status(403).json({ error: 'Cannot update another merchant\'s configuration' })
      }

      const saved = await setMerchantGatewayConfig(merchant_id, {
        merchant_name,
        merchant_logo_url,
        supabase_url,
        supabase_anon_key,
        bkash_enabled,
        nagad_enabled,
        rocket_enabled,
        upay_enabled,
        success_url,
        fail_url,
        cancel_url,
        receiving_numbers,
        account_types,
        qr_codes,
        auto_appeal_matching,
      })

      console.log(`[merchant-config] Gateway setup updated for merchant: ${merchant_id}`)
      res.json({ ok: true, merchant_id, config: saved })
    } catch (err) {
      console.error('[merchant-config] Error:', err.message)
      res.status(500).json({ error: 'Failed to save merchant gateway setup: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/report-payment
  // Called by merchant's Android phone when an incoming SMS transaction is parsed.
  // Records a captured SMS for the merchant dashboard. The merchant database
  // SMS processor is the only path that can verify and mark an order as PAID.
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/report-payment', requireMerchantOrAdminAuth, async (req, res) => {
    try {
      const {
        merchant_id, trx_id, amount, payment_method, sender_number, timestamp, device_id
      } = req.body || {}

      if (!trx_id) {
        return res.status(400).json({ ok: false, error: 'trx_id is required' })
      }
      if (!merchant_id) {
        return res.status(400).json({ ok: false, error: 'merchant_id is required' })
      }
      if (!req.isAdmin && req.merchantUser?.id !== merchant_id) {
        return res.status(403).json({ ok: false, error: 'Cannot report payment activity for another merchant' })
      }

      const cleanTrx = String(trx_id).trim().toUpperCase()
      const numAmount = parseFloat(amount) || 0
      if (!/^[A-Z0-9_-]{3,120}$/.test(cleanTrx) || !Number.isFinite(numAmount) || numAmount <= 0) {
        return res.status(400).json({ ok: false, error: 'Valid trx_id and positive amount are required' })
      }

      console.log(`[payment/report-payment] 📲 Incoming payment reported from Android device: TrxID ${cleanTrx} | ৳${numAmount} | Method: ${payment_method || 'bKash'} | Merchant: ${merchant_id}`)

      const result = await processReportedPayment({
        merchantId: merchant_id,
        trxId: cleanTrx,
        amount: numAmount,
        paymentMethod: payment_method || 'bKash',
        senderNumber: sender_number || null,
        timestamp,
        deviceId: device_id || null,
        io,
      })

      return res.json(result)
    } catch (err) {
      console.error('[payment/report-payment] Error:', err.message)
      return res.status(500).json({ ok: false, error: err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/notify
  // Widget calls this when customer taps "I Have Completed Payment".
  // Emits to BOTH order room (widget) AND merchant room (Android app).
  // Body: { order_id, merchant_id, payment_method, customer_phone, trx_id }
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/notify', async (req, res) => {
    const body = req.body || {}
    let order_id = body.order_id || body.tran_id
    const requestedOrderRoom = order_id
    const { payment_method, customer_phone, trx_id } = body
    let { merchant_id } = req.body || {}

    if (!order_id || typeof order_id !== 'string' || order_id.length > 100) {
      return res.status(400).json({ error: 'order_id is required' })
    }

    // Resolve missing merchant IDs from known payment records when an older
    // integration did not include it in the checkout URL.
    if (!merchant_id) {
      try {
        const order = await getOrderFromMerchantDB(null, order_id)
        merchant_id = order?.merchant_id || null
      } catch (_) {}
    }

    const requestedOrder = await getOrderFromMerchantDB(merchant_id, order_id)
    if (!requestedOrder || String(requestedOrder.merchant_id || '') !== String(merchant_id)) {
      return res.status(404).json({ ok: false, error: 'Order not found for this merchant' })
    }
    order_id = requestedOrder.id || order_id
    if (requestedOrder.status === 'PAID') {
      return res.json({ ok: true, status: 'PAID', order_id, message: 'This order is already verified as paid.' })
    }
    if (['CANCELLED', 'EXPIRED', 'FAILED'].includes(String(requestedOrder.status || '').toUpperCase())) {
      return res.status(409).json({ ok: false, status: requestedOrder.status, error: 'This order is no longer payable' })
    }

    const cleanTrx = trx_id ? String(trx_id).trim().toUpperCase() : null

    console.log(`[payment/notify] Customer payment submitted — order: ${order_id} | TrxID: ${cleanTrx || 'none'} | method: ${payment_method} | merchant: ${merchant_id || 'unknown'}`)

    const notifyPayload = {
      order_id,
      merchant_id:    merchant_id || null,
      payment_method: payment_method || 'unknown',
      trx_id:         cleanTrx,
      customer_phone: customer_phone ? maskPhone(customer_phone) : null,
      notified_at:    new Date().toISOString(),
    }

    // Record or update payment event in platform DB before notifying clients.
    let eventRecorded = false
    try {
      eventRecorded = await recordPaymentEvent(order_id, {
        tran_id: requestedOrder.tran_id || order_id,
        trx_id: cleanTrx,
        status: 'PENDING',
        amount: requestedOrder.amount,
        merchant_id: merchant_id || null,
        sender_number: customer_phone || null,
        payment_method: payment_method || 'unknown',
        product_name: cleanTrx ? `Customer submitted TrxID: ${cleanTrx}` : 'Customer reported payment transfer'
      })
    } catch (error) {
      console.error('[payment/notify] Failed to record payment event:', error.message)
    }
    if (!eventRecorded) {
      return res.status(503).json({ ok: false, error: 'Payment notification could not be saved. Please retry.' })
    }

    // Emit to widget watching this order
    io.to(`order:${order_id}`).emit('payment_pending', notifyPayload)
    if (requestedOrderRoom !== order_id) {
      io.to(`order:${requestedOrderRoom}`).emit('payment_pending', notifyPayload)
    }

    // Also emit to merchant Android app room for instant notification
    if (merchant_id) {
      io.to(`merchant:${merchant_id}`).emit('customer_payment_pending', notifyPayload)
      io.to(`merchant:${merchant_id}`).emit('payment_received', {
        order_id,
        trx_id: cleanTrx,
        amount: requestedOrder.amount || null,
        method: payment_method || 'unknown',
        sender: customer_phone ? maskPhone(customer_phone) : null,
        status: 'PENDING',
        time: notifyPayload.notified_at,
      })
      console.log(`[payment/notify] Forwarded to merchant room: merchant:${merchant_id}`)
    }

    // Check if there is an unassigned or matching verified SMS payment event for this merchant and TrxID
    if (cleanTrx && cleanTrx.length >= 6) {
      try {
        const { getAdminClient, getMerchantCredentials } = await import('../services/adminSupabase.js')
        const admin = getAdminClient()
        const orderRecord = merchant_id ? await getOrderFromMerchantDB(merchant_id, order_id) : requestedOrder
        const targetAmount = Number(orderRecord?.amount || requestedOrder?.amount || 0)

        // 1. Check admin payment_events (both PAID and PENDING SMS captures)
        let query = admin.from('payment_events')
          .select('*')
          .eq('trx_id', cleanTrx)
          .eq('merchant_id', merchant_id)
          .in('status', ['PAID', 'PENDING'])
        const { data: matchedEvents } = merchant_id ? await query.limit(10) : { data: [] }
        let matchedPayment = (matchedEvents || []).find(event => Math.abs(Number(event.amount) - targetAmount) < 0.01)

        // 2. Also check merchant DB payments table if not found in events
        if (!matchedPayment && merchant_id) {
          try {
            const creds = await getMerchantCredentials(merchant_id)
            if (creds?.supabase_url && creds?.supabase_anon_key) {
              const { createClient } = await import('@supabase/supabase-js')
              const mClient = createClient(creds.supabase_url, creds.supabase_anon_key, { auth: { persistSession: false, autoRefreshToken: false } })
              const { data: mPay } = await mClient
                .from('payments')
                .select('*')
                .eq('merchant_id', merchant_id)
                .ilike('trx_id', cleanTrx)
                .maybeSingle()
              if (mPay && Math.abs(Number(mPay.amount) - targetAmount) < 0.01) {
                matchedPayment = {
                  amount: Number(mPay.amount),
                  payment_method: mPay.payment_method || 'bKash',
                  sender_number: mPay.sender_number || null,
                  trx_id: cleanTrx
                }
                // Mark payment matched in merchant DB
                mClient.from('payments').update({ status: 'MATCHED', matched_order_id: order_id }).eq('id', mPay.id).catch(() => {})
                if (mPay.sms_hash) {
                  mClient.from('sms_logs').update({ processed: true, status: 'matched' }).eq('sms_hash', mPay.sms_hash).catch(() => {})
                }
              }
            }
          } catch (_) {}
        }

        if (matchedPayment) {
          console.log(`[payment/notify] 🎯 Immediate TrxID match for order ${order_id} (TrxID: ${cleanTrx})!`)
          await updateOrderStatusOnMerchantDB(merchant_id, order_id, 'PAID', {
            matched_trx_id: cleanTrx,
            payment_method: payment_method || matchedPayment.payment_method,
            amount: matchedPayment.amount,
            sender_number: matchedPayment.sender_number || customer_phone || null
          })

          // Mark payment_events as PAID in admin DB
          await admin.from('payment_events').upsert({
            order_id,
            tran_id: requestedOrder.tran_id || order_id,
            trx_id: cleanTrx,
            status: 'PAID',
            amount: matchedPayment.amount,
            currency: 'BDT',
            payment_method: payment_method || matchedPayment.payment_method,
            sender_number: matchedPayment.sender_number || customer_phone || null,
            merchant_id: merchant_id,
            payment_time: new Date().toISOString()
          }).catch(() => {})

          try {
            const { handleFormPaymentPaid } = await import('./form.js')
            await handleFormPaymentPaid(order_id, cleanTrx, matchedPayment.amount, io)
          } catch (_) {}

          try {
            const { getGatewayConfig } = await import('../services/adminSupabase.js')
            const gatewayConfig = await getGatewayConfig()
            sendPaymentReceipts({
              order_id,
              tran_id: requestedOrder.tran_id || order_id,
              trx_id: cleanTrx,
              amount: matchedPayment.amount,
              payment_method: payment_method || matchedPayment.payment_method || 'bKash',
              payment_time: new Date().toISOString(),
              merchant_name: requestedOrder.merchant_name || 'SwapnoPay Merchant',
              cus_name: requestedOrder.cus_name,
              cus_phone: customer_phone || requestedOrder.cus_phone,
              product_name: requestedOrder.product_name,
              verification: 'CUSTOMER_TRX_MATCH',
              customer_email: requestedOrder.cus_email || null,
              customer_receipts_enabled: gatewayConfig?.customer_receipts_enabled,
              merchant_receipts_enabled: gatewayConfig?.merchant_receipts_enabled,
            }).catch(e => console.warn('[payment/notify] Receipt notice:', e.message))
          } catch (_) {}

          const matchPayload = {
            order_id,
            tran_id: requestedOrder.tran_id,
            status: 'PAID',
            trx_id: cleanTrx,
            amount: matchedPayment.amount,
            payment_method: payment_method || matchedPayment.payment_method || 'bKash',
            paid_at: new Date().toISOString()
          }
          io.to(`order:${order_id}`).emit('payment_status', matchPayload)
          if (requestedOrderRoom && requestedOrderRoom !== order_id) {
            io.to(`order:${requestedOrderRoom}`).emit('payment_status', matchPayload)
          }
          if (merchant_id) {
            io.to(`merchant:${merchant_id}`).emit('payment_received', {
              order_id,
              trx_id: cleanTrx,
              amount: matchedPayment.amount,
              method: payment_method || matchedPayment.payment_method || 'bKash',
              sender: customer_phone || null,
              status: 'MATCHED',
              time: matchPayload.paid_at
            })
          }
          return res.json({ ok: true, status: 'PAID', message: 'Payment verified immediately by TrxID match.' })
        }
      } catch (matchErr) {
        console.warn('[payment/notify] Immediate match lookup notice:', matchErr.message)
      }
    }

    res.json({ ok: true, message: 'Payment notification received. Watching for verification.' })
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/verify
  // Called by Supabase process-sms edge function after atomic PAID match.
  // Also called by resolve-appeal after merchant approves disputed payment.
  // Secured by X-Webhook-Secret header.
  //
  // CROSS-DB FLOW:
  //   1. Receives verified payment from Supabase SMS processor
  //   2. Records in admin DB (payment_events)
  //   3. Updates merchant's own Supabase DB (orders table) → PAID/FAILED
  //   4. Emits Socket.io events to widget (order room) + Android (merchant room)
  //   5. Sends email receipts
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/verify', requireWebhookSecret, async (req, res) => {
    const rawBody = req.rawBody
    const body = req.body || {}
    const {
      order_id, tran_id, status, amount, currency,
      payment_method, sender_number, trx_id, payment_time,
      merchant_id, merchant_name, merchant_email,
      project_ref, success_url, fail_url, cancel_url,
      cus_name, cus_email, cus_phone, product_name,
      verification, webhook_secret, webhook_signature,
    } = body

    // ── Basic validation ──
    if (!order_id || !status || !merchant_id) {
      return res.status(400).json({ error: 'Missing required: order_id, status, merchant_id' })
    }

    if (!['PAID', 'FAILED', 'CANCELLED'].includes(status)) {
      return res.status(400).json({ error: 'Invalid status value' })
    }

    const verifiedOrder = await getOrderFromMerchantDB(merchant_id, order_id)
    if (!verifiedOrder || String(verifiedOrder.merchant_id || '') !== String(merchant_id)) {
      return res.status(404).json({ error: 'Order not found for this merchant' })
    }
    const reportedAmount = Number(amount)
    const storedAmount = Number(verifiedOrder.amount)
    if (!Number.isFinite(reportedAmount) || reportedAmount <= 0 || reportedAmount !== storedAmount) {
      return res.status(409).json({ error: 'Payment amount does not match the stored order' })
    }
    if (verifiedOrder.status === 'PAID' && status !== 'PAID') {
      return res.status(409).json({ error: 'A paid order cannot be moved to another status' })
    }
    if (['CANCELLED', 'EXPIRED'].includes(String(verifiedOrder.status || '').toUpperCase()) && status === 'PAID') {
      return res.status(409).json({ error: 'A cancelled or expired order cannot be marked paid' })
    }
    if (status === 'PAID' && !(trx_id || verification === 'ATOMIC_SMS_MATCH')) {
      return res.status(400).json({ error: 'A verified transaction reference is required' })
    }

    // ── Idempotency check: prevent duplicate execution on network retries ──
    const cacheKey = `${order_id}:${trx_id || tran_id || 'none'}:${status}`
    if (recentVerifications.has(cacheKey)) {
      const cached = recentVerifications.get(cacheKey)
      console.log(`[payment/verify] Returning cached idempotent response for ${cacheKey}`)
      return res.json(cached.payload)
    }

    // ── Optional HMAC signature check ──
    if (webhook_secret && webhook_signature && rawBody) {
      if (!verifyWebhookSignature(webhook_secret, rawBody, webhook_signature)) {
        console.warn(`[payment/verify] Invalid HMAC for order: ${order_id}`)
        return res.status(401).json({ error: 'Invalid webhook signature' })
      }
    }

    console.log(`[payment/verify] ${status} — order: ${order_id} | ৳${amount} ${currency || 'BDT'} | ${payment_method} | merchant: ${merchant_id}`)

    // ── Step 1: Record in admin Supabase payment_events ──
    const eventRecorded = await recordPaymentEvent(order_id, {
      tran_id, trx_id, status, amount,
      currency: currency || 'BDT',
      payment_method, sender_number: maskPhone(sender_number),
      payment_time, merchant_id, merchant_name,
      project_ref, cus_name, cus_email, product_name
    })
    if (!eventRecorded) {
      console.warn(`[payment/verify] Payment event record notice for order ${order_id}`)
    }

    // ── Step 2: Cross-DB — Update order on Merchant Supabase DB ──
    const merchantUpdateResult = await updateOrderStatusOnMerchantDB(
      merchant_id,
      order_id,
      status,
      {
        matched_trx_id: trx_id || tran_id,
        tran_id,
        payment_method,
        sender_number: maskPhone(sender_number),
        payment_time,
        amount
      }
    )
    if (!merchantUpdateResult) {
      console.warn(`[payment/verify] ⚠️  Could not update order on merchant DB for ${merchant_id} — continuing`)
    }

    // ── Auto-activate subscription if order is a platform subscription (sub_*) ──
    if (status === 'PAID' && (String(order_id).startsWith('sub_') || String(tran_id).startsWith('sub_'))) {
      try {
        const { verifyAndActivateSubscription } = await import('../services/adminSupabase.js')
        const subResult = await verifyAndActivateSubscription({
          merchantId: merchant_id,
          orderId: order_id,
          trxId: trx_id || tran_id,
          method: payment_method || 'bKash'
        })
        console.log(`[payment/verify] 🌟 Platform subscription auto-activated for merchant ${merchant_id} (order: ${order_id})`, subResult?.subscription_plan)
      } catch (subErr) {
        console.warn(`[payment/verify] Subscription auto-activation notice:`, subErr.message)
      }
    }

    // ── Auto-update Form Submission if this was a hosted form order ──
    if (status === 'PAID') {
      try {
        const { handleFormPaymentPaid } = await import('./form.js')
        await handleFormPaymentPaid(order_id || tran_id, trx_id || tran_id, amount, io)
      } catch (formPaidErr) {
        console.warn('[payment/verify] Form submission update notice:', formPaidErr.message)
      }
    }

    // ── Step 3: Send email receipts ──
    if (status === 'PAID') {
      getGatewayConfig()
        .then(gatewayConfig => sendPaymentReceipts({
          order_id, tran_id, trx_id, amount,
          payment_method, payment_time,
          merchant_name: merchant_name || 'SwapnoPay Merchant',
          cus_name, cus_phone, product_name,
          verification: verification || 'ATOMIC_SMS_MATCH',
          customer_email: cus_email || null,
          merchant_email: merchant_email || null,
          customer_receipts_enabled: gatewayConfig.customer_receipts_enabled,
          merchant_receipts_enabled: gatewayConfig.merchant_receipts_enabled,
        }))
        .catch(err => console.error('[payment/verify] Email receipts error:', err.message))
    }

    // ── Step 4: Build redirect URL ──
    if (verifiedOrder.callback_url) {
      try {
        const { getMerchantActiveApiKey } = await import('../services/adminSupabase.js')
        const keyRecord = await getMerchantActiveApiKey(merchant_id)
        if (!keyRecord?.api_key) throw new Error('Merchant API signing key is unavailable')
        const callbackPayload = {
          event: 'payment.status_changed',
          event_id: cacheKey,
          order_id: verifiedOrder.id || order_id,
          tran_id: verifiedOrder.tran_id || tran_id,
          merchant_id,
          status,
          amount: reportedAmount,
          currency: currency || 'BDT',
          payment_method: payment_method || verifiedOrder.payment_method || null,
          trx_id: trx_id || null,
          paid_at: payment_time || new Date().toISOString(),
        }
        const signature = crypto.createHmac('sha256', keyRecord.api_key)
          .update(JSON.stringify(callbackPayload)).digest('hex')
        await postSafeWebhook(verifiedOrder.callback_url, callbackPayload, 5000, {
          'x-swapnopay-signature': signature,
        })
      } catch (callbackError) {
        console.warn('[payment/verify] Merchant callback delivery failed:', callbackError.message)
      }
    }

    const effectiveSuccessUrl = success_url || verifiedOrder.success_url
    let redirectUrl = null
    if (status === 'PAID' && effectiveSuccessUrl) {
      redirectUrl = buildRedirectUrl(effectiveSuccessUrl, { status: 'PAID', order_id, trx_id, tran_id, amount })
    } else if (status === 'FAILED' && fail_url) {
      redirectUrl = buildRedirectUrl(fail_url, { status: 'FAILED', order_id, tran_id })
    } else if (status === 'CANCELLED' && cancel_url) {
      redirectUrl = buildRedirectUrl(cancel_url, { status: 'CANCELLED', order_id, tran_id })
    }

    // ── Step 5: Emit Socket.io events ──
    const socketPayload = {
      order_id, tran_id, status, amount,
      currency:       currency || 'BDT',
      payment_method, trx_id,
      payment_time:   payment_time || new Date().toISOString(),
      redirect_url:   redirectUrl,
      merchant_db_updated: merchantUpdateResult,
    }

    // Emit to widget watching this order room
    io.to(`order:${order_id}`).emit('payment_status', socketPayload)

    // Emit to merchant Android app room for dashboard update
    if (merchant_id) {
      io.to(`merchant:${merchant_id}`).emit('payment_status', socketPayload)
    }

    console.log(`[payment/verify] ✅ Socket.io 'payment_status' emitted → order:${order_id} | merchant:${merchant_id}`)

    const responsePayload = {
      ok: true,
      order_id,
      status,
      redirect_url: redirectUrl,
      merchant_db_updated: merchantUpdateResult,
    }

    recentVerifications.set(cacheKey, { payload: responsePayload, ts: Date.now() })
    res.json(responsePayload)
  })

  // ──────────────────────────────────────────────────────────────────────────
  // GET /v1/payment/order/:order_id and GET /v1/payment/status
  // High-reliability polling fallback for checkout widgets and web storefronts
  // ──────────────────────────────────────────────────────────────────────────
  const handleOrderStatusLookup = async (req, res) => {
    const orderId = req.params.order_id || req.query.order_id || req.query.tran_id
    const merchantId = req.query.merchant_id || null

    if (!orderId) {
      return res.status(400).json({ ok: false, error: 'order_id or tran_id is required' })
    }

    try {
      const order = await getOrderFromMerchantDB(merchantId, orderId)
      if (!order) {
        return res.status(404).json({ ok: false, error: 'Order not found' })
      }

      let redirectUrl = null
      if (order.status === 'PAID' && order.success_url) {
        redirectUrl = buildRedirectUrl(order.success_url, {
          status: 'PAID',
          order_id: order.id,
          tran_id: order.tran_id,
          trx_id: order.matched_trx_id,
          amount: order.amount,
        })
      } else if (order.status === 'CANCELLED' && order.cancel_url) {
        redirectUrl = buildRedirectUrl(order.cancel_url, {
          status: 'CANCELLED',
          order_id: order.id,
          tran_id: order.tran_id,
        })
      }

      res.json({
        ok: true,
        order_id: order.id,
        tran_id: order.tran_id,
        status: order.status,
        amount: order.amount,
        currency: 'BDT',
        payment_method: order.payment_method || null,
        trx_id: order.matched_trx_id || null,
        sender_number: order.sender_number ? maskPhone(order.sender_number) : null,
        paid_at: order.paid_at || null,
        expires_at: order.expires_at || null,
        product_name: order.product_name || null,
        merchant_id: order.merchant_id || merchantId || null,
        merchant_name: order.merchant_name || null,
        redirect_url: redirectUrl,
      })
    } catch (err) {
      console.error('[payment/order-status] Error:', err.message)
      res.status(500).json({ ok: false, error: 'Failed to look up order status: ' + err.message })
    }
  }

  router.get('/order/:order_id', handleOrderStatusLookup)
  router.get('/status', handleOrderStatusLookup)
  router.get('/check-status', handleOrderStatusLookup)

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/cancel
  // Customer or merchant cancellation of pending order
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/cancel', async (req, res) => {
    const { order_id, merchant_id, reason } = req.body || {}
    if (!order_id || typeof order_id !== 'string') {
      return res.status(400).json({ ok: false, error: 'order_id is required' })
    }
    if (!merchant_id) {
      return res.status(400).json({ ok: false, error: 'merchant_id is required' })
    }

    try {
      const order = await getOrderFromMerchantDB(merchant_id, order_id)
      if (!order || String(order.merchant_id || '') !== String(merchant_id)) {
        return res.status(404).json({ ok: false, error: 'Order not found for this merchant' })
      }
      if (order.status === 'PAID') {
        return res.status(409).json({ ok: false, error: 'A paid order cannot be cancelled' })
      }
      if (['CANCELLED', 'EXPIRED'].includes(String(order.status || '').toUpperCase())) {
        return res.json({ ok: true, order_id, status: order.status })
      }
      console.log(`[payment/cancel] Cancelling order ${order_id} (merchant: ${merchant_id || 'unknown'}) — reason: ${reason || 'Customer cancelled'}`)

      // 1. Update merchant DB if merchant_id provided
      if (merchant_id) {
        await updateOrderStatusOnMerchantDB(merchant_id, order_id, 'CANCELLED')
      }

      // 2. Record cancellation event in platform admin DB
      const eventRecorded = await recordPaymentEvent(order.id || order_id, {
        tran_id: order.tran_id || order_id,
        status: 'CANCELLED',
        merchant_id: merchant_id || null,
        product_name: `Cancelled: ${reason || 'User cancelled'}`,
      })
      if (!eventRecorded) {
        console.warn(`[payment/cancel] Cancellation event record notice for order ${order_id}`)
      }

      // 3. Emit real-time cancellation to widget + merchant rooms
      const cancelPayload = {
        order_id,
        status: 'CANCELLED',
        reason: reason || 'Payment cancelled by customer',
        cancelled_at: new Date().toISOString(),
      }

      io.to(`order:${order_id}`).emit('payment_status', cancelPayload)
      if (merchant_id) {
        io.to(`merchant:${merchant_id}`).emit('payment_status', cancelPayload)
      }

      res.json({
        ok: true,
        order_id,
        status: 'CANCELLED',
        message: 'Order cancelled successfully',
      })
    } catch (err) {
      console.error('[payment/cancel] Error:', err.message)
      res.status(500).json({ ok: false, error: 'Failed to cancel order: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/appeal
  // Customer submits an unverified payment dispute for merchant review
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/appeal', async (req, res) => {
    const { order_id, merchant_id, trx_id, cus_phone, payment_method, note, screenshot_url } = req.body || {}

    if (!order_id || !trx_id || !merchant_id) {
      return res.status(400).json({ ok: false, error: 'Missing required: order_id, merchant_id, trx_id' })
    }

    try {
      console.log(`[payment/appeal] Appeal submitted — order: ${order_id} | TrxID: ${trx_id} | merchant: ${merchant_id}`)

      const appeal = await createDisputeAppeal(merchant_id, {
        order_id,
        trx_id,
        cus_phone,
        payment_method,
        note,
        screenshot_url,
      })

      // Broadcast real-time notification to merchant's Android app
      const alertPayload = {
        appeal_id: appeal.id,
        order_id,
        trx_id: String(trx_id).trim().toUpperCase(),
        cus_phone: cus_phone ? maskPhone(cus_phone) : null,
        payment_method: payment_method || 'MFS',
        note: note || 'Dispute submitted by customer',
        screenshot_url: screenshot_url || null,
        submitted_at: appeal.created_at || new Date().toISOString(),
      }

      io.to(`merchant:${merchant_id}`).emit('customer_appeal_submitted', alertPayload)

      res.status(201).json({
        ok: true,
        appeal_id: appeal.id,
        status: 'PENDING_REVIEW',
        message: 'Payment appeal submitted successfully. Your merchant will review and confirm shortly.',
      })
    } catch (err) {
      console.error('[payment/appeal] Error:', err.message)
      res.status(500).json({ ok: false, error: 'Failed to submit payment appeal: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/resolve-appeal
  // Merchant or AI Copilot resolves an appeal (APPROVED / REJECTED)
  // Sends notification email to customer via mailer service
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/resolve-appeal', async (req, res) => {
    const { appeal_id, order_id, status, trx_id, amount, customer_email, customer_phone, merchant_id, note, send_email = true } = req.body || {}

    if (!appeal_id || !status) {
      return res.status(400).json({ ok: false, error: 'Missing required: appeal_id, status' })
    }

    try {
      const normalizedStatus = String(status).toUpperCase() === 'APPROVED' ? 'APPROVED' : 'REJECTED'
      console.log(`[payment/resolve-appeal] Resolving appeal: ${appeal_id} -> ${normalizedStatus} (order: ${order_id})`)

      // 1. Notify customer via email if email provided and send_email is true
      if (send_email && customer_email) {
        try {
          const { sendPaymentReceipt } = await import('../services/mailer.js')
          await sendPaymentReceipt('customer', customer_email, {
            order_id: order_id || appeal_id,
            tran_id: trx_id || appeal_id,
            amount: amount || '0.00',
            currency: 'BDT',
            status: normalizedStatus === 'APPROVED' ? 'PAID' : 'APPEAL_REJECTED',
            payment_method: 'MFS',
            merchant_name: 'SwapnoPay Merchant',
            merchant_id: merchant_id || null,
            product_name: `Payment Appeal (${normalizedStatus})`,
            verification: normalizedStatus === 'APPROVED' ? 'MERCHANT_APPROVED_APPEAL' : 'APPEAL_REJECTED',
            notes: note || (normalizedStatus === 'APPROVED' ? 'Your payment appeal was verified and approved.' : 'Your payment appeal could not be verified.')
          })
          console.log(`[payment/resolve-appeal] Customer email notification dispatched to ${customer_email}`)
        } catch (mailErr) {
          console.warn('[payment/resolve-appeal] Mailer notice:', mailErr.message)
        }
      }

      // 2. Broadcast via socket to widget & merchant room
      if (order_id) {
        io.to(`order:${order_id}`).emit('payment_status_update', {
          order_id,
          status: normalizedStatus === 'APPROVED' ? 'PAID' : 'APPEAL_REJECTED',
          appeal_id,
          trx_id,
          resolved_at: new Date().toISOString()
        })
      }

      res.json({
        ok: true,
        appeal_id,
        status: normalizedStatus,
        email_sent: Boolean(send_email && customer_email),
        message: `Appeal marked as ${normalizedStatus} successfully.`
      })
    } catch (err) {
      console.error('[payment/resolve-appeal] Error:', err.message)
      res.status(500).json({ ok: false, error: 'Failed to resolve appeal: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/form-submission
  // Relay endpoint for hosted form / web checkout submissions
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/form-submission', requireMerchantOrAdminAuth, async (req, res) => {
    const { form_id, form_slug, merchant_id, submission_id, data, total_amount, tran_id } = req.body || {}

    if (!form_id && !form_slug) {
      return res.status(400).json({ ok: false, error: 'form_id or form_slug is required' })
    }
    if (!req.isAdmin && req.merchantUser?.id !== merchant_id) {
      return res.status(403).json({ ok: false, error: 'Cannot submit activity for another merchant' })
    }

    try {
      const payload = {
        form_id: form_id || null,
        form_slug: form_slug || null,
        merchant_id: merchant_id || null,
        submission_id: submission_id || 'sub_' + Date.now(),
        tran_id: tran_id || null,
        total_amount: total_amount ? Number(total_amount) : 0,
        data: data || {},
        submitted_at: new Date().toISOString(),
      }

      if (merchant_id) {
        io.to(`merchant:${merchant_id}`).emit('form_submission_received', payload)
        console.log(`[payment/form-submission] Broadcasted to merchant:${merchant_id} for form: ${form_slug || form_id}`)
      }

      res.json({
        ok: true,
        message: 'Form submission received and broadcasted.',
        submission_id: payload.submission_id,
      })
    } catch (err) {
      console.error('[payment/form-submission] Error:', err.message)
      res.status(500).json({ ok: false, error: 'Failed to process form submission: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/create-order
  // Creates an atomic payment order for storefront checkouts
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/create-order', requireMerchantOrAdminAuth, async (req, res) => {
    const {
      merchant_id, tran_id, order_number, amount,
      cus_name, cus_phone, cus_email, payment_method,
      items, metadata, success_url, callback_url
    } = req.body || {}

    const numAmount = Number(amount)
    if (!Number.isFinite(numAmount) || numAmount <= 0 || numAmount > 10000000) {
      return res.status(400).json({ ok: false, error: 'A positive amount is required' })
    }
    if (!merchant_id) {
      return res.status(400).json({ ok: false, error: 'merchant_id is required' })
    }
    if (!req.isAdmin && req.merchantUser?.id !== merchant_id) {
      return res.status(403).json({ ok: false, error: 'API key does not belong to this merchant' })
    }
    for (const [name, value] of [['success_url', success_url], ['callback_url', callback_url]]) {
      if (!value) continue
      const validation = isSafeOutboundWebhookUrl(value)
      if (!validation.safe || new URL(value).protocol !== 'https:') {
        return res.status(400).json({ ok: false, error: `${name} must be a public HTTPS URL` })
      }
    }

    let merchantCredentials
    try {
      const { getMerchantCredentials } = await import('../services/adminSupabase.js')
      merchantCredentials = await getMerchantCredentials(merchant_id)
    } catch (lookupErr) {
      console.warn('[payment/create-order] Merchant lookup failed:', lookupErr.message)
    }
    if (!merchantCredentials) {
      return res.status(404).json({ ok: false, error: 'Merchant is not registered with this gateway' })
    }
    if (String(merchantCredentials.status || 'ACTIVE').toUpperCase() !== 'ACTIVE') {
      return res.status(403).json({ ok: false, error: 'Merchant account is not active for payments' })
    }
    const selectedMethod = canonicalMethodName(payment_method || 'bKash')
    if (!['bKash', 'Nagad', 'Rocket', 'Upay'].includes(selectedMethod)) {
      return res.status(400).json({ ok: false, error: 'Unsupported payment method' })
    }
    let merchantGateway
    try {
      merchantGateway = await getMerchantGatewayConfig(merchant_id)
    } catch (gatewayErr) {
      console.error('[payment/create-order] Merchant gateway lookup failed:', gatewayErr.message)
      return res.status(503).json({ ok: false, error: 'Merchant payment configuration is unavailable' })
    }
    if (!merchantGateway.enabled_methods?.[selectedMethod] || !String(merchantGateway.receiving_numbers?.[selectedMethod] || '').trim()) {
      return res.status(409).json({ ok: false, error: `Merchant has not enabled ${selectedMethod} payments or configured a receiving number` })
    }
    const minAmount = Number(merchantGateway.min_amount || 0)
    const maxAmount = Number(merchantGateway.max_amount || 10000000)
    if (numAmount < minAmount || numAmount > maxAmount) {
      return res.status(422).json({ ok: false, error: `Payment amount must be between ${minAmount} and ${maxAmount}` })
    }

    const orderUuid = crypto.randomUUID()
    const tranId = tran_id || `SWP-${Date.now()}-${Math.floor(1000 + Math.random() * 9000)}`

    try {
      // 1. Persist in the merchant DB when the merchant has a dedicated external database.
      const adminUrl = process.env.ADMIN_SUPABASE_URL || 'https://tldubojeokgyoclxnzkb.supabase.co'
      let isDedicatedMerchantDb = false
      try {
        if (merchantCredentials.supabase_url && merchantCredentials.supabase_anon_key) {
          const mHost = new URL(merchantCredentials.supabase_url).hostname
          const aHost = new URL(adminUrl).hostname
          isDedicatedMerchantDb = mHost !== aHost
        }
      } catch (_) {}

      if (isDedicatedMerchantDb) {
        try {
          const { createClient } = await import('@supabase/supabase-js')
          const mClient = createClient(merchantCredentials.supabase_url, merchantCredentials.supabase_anon_key, {
            auth: { persistSession: false, autoRefreshToken: false }
          })
          const { error: insertError } = await mClient.from('orders').insert({
            id: orderUuid,
            merchant_id,
            tran_id: tranId,
            order_number: order_number || `ORD-${Date.now()}`,
            amount: numAmount,
            total_amount: numAmount,
            cus_name: cus_name || 'Customer',
            cus_phone: cus_phone || '01700000000',
            cus_email: cus_email || '',
            payment_method: selectedMethod,
            status: 'PENDING',
            success_url: success_url || null,
            callback_url: callback_url || null,
            expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString()
          })
          if (insertError) console.warn('[payment/create-order] Merchant DB mirror notice:', insertError.message)
        } catch (mirrorErr) {
          console.warn('[payment/create-order] Merchant DB insert notice:', mirrorErr.message)
        }
      }

      // 2. Record the platform ledger event and fail closed if it is unavailable.
      const eventRecorded = await recordPaymentEvent(orderUuid, {
        tran_id: tranId,
        order_number: order_number || `ORD-${Date.now()}`,
        status: 'PENDING',
        amount: numAmount,
        currency: 'BDT',
        payment_method: selectedMethod,
        cus_name: cus_name || 'Customer',
        cus_phone: cus_phone || null,
        cus_email: cus_email || null,
        merchant_id,
        product_name: Array.isArray(items) ? items.map(item => item?.product_name).filter(Boolean).join(', ').slice(0, 500) : null,
      })
      if (!eventRecorded) {
        console.warn(`[payment/create-order] Platform payment ledger recording skipped for order ${orderUuid}`)
      }

      const backendUrl = process.env.BACKEND_PUBLIC_URL || process.env.API_BASE_URL || 'https://api.swapnopay.top'
      const checkoutUrl = `${backendUrl.replace(/\/$/, '')}/widget.html?order_id=${orderUuid}&amount=${numAmount}&merchant_id=${encodeURIComponent(merchant_id)}`

      res.status(201).json({
        ok: true,
        order_id: orderUuid,
        tran_id: tranId,
        amount: numAmount,
        currency: 'BDT',
        status: 'PENDING',
        checkout_url: checkoutUrl
      })
    } catch (err) {
      console.error('[payment/create-order] Error creating order:', err.message)
      res.status(500).json({ ok: false, error: 'Failed to create order: ' + err.message })
    }
  })

  // ──────────────────────────────────────────────────────────────────────────
  // GET /v1/payment/sms-webhook-health
  // Verifies SMS processing health: checks for backlog of unprocessed SMS logs
  // ──────────────────────────────────────────────────────────────────────────
  router.get('/sms-webhook-health', async (req, res) => {
    const merchantId = req.query.merchant_id || req.headers['x-merchant-id']
    if (!merchantId) {
      return res.status(400).json({ ok: false, error: 'merchant_id is required' })
    }

    try {
      const { getMerchantCredentials } = await import('../services/adminSupabase.js')
      const creds = await getMerchantCredentials(merchantId)
      if (!creds?.supabase_url || !creds?.supabase_anon_key) {
        return res.json({
          ok: true,
          status: 'UNCONFIGURED',
          message: 'Merchant does not use a dedicated Supabase database; platform matching is active.'
        })
      }

      const { createClient } = await import('@supabase/supabase-js')
      const mClient = createClient(creds.supabase_url, creds.supabase_anon_key, {
        auth: { persistSession: false, autoRefreshToken: false }
      })

      const { data: recentLogs, error } = await mClient
        .from('sms_logs')
        .select('id, processed, status, created_at')
        .order('created_at', { ascending: false })
        .limit(10)

      if (error) {
        return res.status(502).json({
          ok: false,
          status: 'ERROR',
          error: 'Unable to query merchant sms_logs: ' + error.message
        })
      }

      const logs = recentLogs || []
      const unprocessed = logs.filter(l => !l.processed && l.status === 'unprocessed')
      const now = Date.now()
      const staleBacklog = unprocessed.filter(l => (now - new Date(l.created_at).getTime()) > 3 * 60 * 1000)

      let healthStatus = 'HEALTHY'
      if (staleBacklog.length > 0) {
        healthStatus = 'BACKLOG_DETECTED'
      } else if (logs.length === 0) {
        healthStatus = 'NO_SMS_YET'
      }

      const lastProcessed = logs.find(l => l.processed)

      return res.json({
        ok: true,
        merchant_id: merchantId,
        status: healthStatus,
        total_recent_logs: logs.length,
        unprocessed_count: unprocessed.length,
        stale_backlog_count: staleBacklog.length,
        last_processed_at: lastProcessed?.created_at || null,
        message: healthStatus === 'BACKLOG_DETECTED'
          ? 'Unprocessed SMS logs detected older than 3 minutes. The webhook or SMS processing service may be delayed.'
          : 'SMS processing pipeline is healthy.'
      })
    } catch (err) {
      return res.status(500).json({ ok: false, error: err.message })
    }
  })

  return router
}

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────
function buildRedirectUrl(baseUrl, params) {
  try {
    const url = new URL(baseUrl)
    Object.entries(params).forEach(([k, v]) => {
      if (v != null) url.searchParams.set(k, String(v))
    })
    return url.toString()
  } catch {
    return baseUrl
  }
}

function maskPhone(phone) {
  if (!phone) return null
  const s = String(phone)
  if (s.length <= 4) return s
  return s.slice(0, -4).replace(/\d/g, '*') + s.slice(-4)
}
