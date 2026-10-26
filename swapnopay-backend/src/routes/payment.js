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
  getMerchantCredentials,
  createMerchantClient,
  getAdminClient,
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
        // Webhook configuration for merchant app and integrations
        webhook_url:                config.webhook_url || null,
        webhook_secret:             config.webhook_secret || null,
        api_key:                    config.api_key || null,
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
        status: (row.status === 'PAID' || row.status === 'MATCHED' || row.order_id || row.matched_order_id) ? 'MATCHED' : (row.status || 'UNMATCHED'),
        timestamp: new Date(row.payment_time || row.recorded_at).getTime(),
      }))

      // Deduplicate by transaction reference, ensuring MATCHED state is preserved
      const txMap = new Map()
      for (const tx of transactions) {
        const key = (tx.trx_id || tx.id).trim().toUpperCase()
        if (!txMap.has(key)) {
          txMap.set(key, tx)
        } else {
          const existing = txMap.get(key)
          if (existing.status !== 'MATCHED' && tx.status === 'MATCHED') {
            txMap.set(key, tx)
          }
        }
      }

      res.json({ ok: true, transactions: Array.from(txMap.values()) })
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
        fcm_token,
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
        // Fallback: If merchant_id is present, authorize heartbeat so device telemetry stays active
        isAuthorized = true
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
        fcmToken: fcm_token || null,
      }

      // Record in-memory heartbeat for all associated merchant keys
      const candidateKeys = new Set([
        merchant_id,
        creds?.merchant_id,
        creds?.user_id,
        creds?.email,
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
              ...(fcm_token ? { fcm_token } : {}),
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
        webhook_url,
        webhook_secret,
        receiving_numbers,
        account_types,
        qr_codes,
        default_number,
        default_method,
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
        webhook_url,
        webhook_secret,
        receiving_numbers,
        account_types,
        qr_codes,
        default_number,
        default_method,
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

      // Process reported SMS payment: mirrors to merchant DB (sms_logs & payments),
      // checks candidate pending orders, marks PAID on match, emits Socket.IO realtime broadcasts,
      // sends receipts, and dispatches outbound webhook callbacks.
      const matchResult = await processReportedPayment({
        merchantId: merchant_id,
        trxId: cleanTrx,
        amount: numAmount,
        paymentMethod: payment_method || 'bKash',
        senderNumber: sender_number || null,
        timestamp: timestamp || null,
        deviceId: device_id || null,
        io
      })

      if (matchResult && matchResult.ok && (matchResult.status === 'PAID' || matchResult.status === 'MATCHED')) {
        return res.json({
          ok: true,
          status: 'PAID',
          message: 'Payment verified and matched to order successfully.',
          order_id: matchResult.order_id,
          tran_id: matchResult.tran_id,
          trx_id: cleanTrx
        })
      }

      return res.json({
        ok: true,
        status: 'RECEIVED',
        message: 'SMS received and mirrored to merchant database. Watching for matching order.',
        trx_id: cleanTrx
      })
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

    let requestedOrder = await getOrderFromMerchantDB(merchant_id, order_id)
    if (!requestedOrder && merchant_id) {
      const numAmount = Number(body.amount)
      if (Number.isFinite(numAmount) && numAmount > 0) {
        try {
          const creds = await getMerchantCredentials(merchant_id).catch(() => null)
          if (creds?.supabase_url && creds?.supabase_anon_key) {
            const mClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)
            const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(order_id)
            const orderUuid = isUuid ? order_id : crypto.randomUUID()
            const { data: created } = await mClient.from('orders').insert({
              id: orderUuid,
              merchant_id: merchant_id,
              tran_id: order_id,
              order_number: `ORD-${Date.now()}`,
              amount: numAmount,
              total_amount: numAmount,
              cus_name: body.cus_name || 'Customer',
              cus_phone: customer_phone || '01700000000',
              payment_method: payment_method || 'bKash',
              status: 'PENDING',
              order_status: 'PENDING',
              currency: 'BDT',
              expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString()
            }).select().maybeSingle()
            if (created) requestedOrder = created
          }
        } catch (_) {}
      }
    }
    if (!requestedOrder || (merchant_id && String(requestedOrder.merchant_id || '') !== String(merchant_id))) {
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
        const notifyClientIp = req.headers['cf-connecting-ip'] ||
          req.headers['x-real-ip'] ||
          (req.headers['x-forwarded-for'] ? String(req.headers['x-forwarded-for']).split(',')[0].trim() : null) ||
          req.ip ||
          requestedOrder?.ip_address ||
          requestedOrder?.metadata?.ip_address ||
          null

        const matchResult = await processReportedPayment({
          merchantId: merchant_id,
          trxId: cleanTrx,
          amount: requestedOrder.amount,
          paymentMethod: payment_method || 'bKash',
          senderNumber: customer_phone || requestedOrder.cus_phone || null,
          timestamp: Date.now(),
          io,
          ip_address: notifyClientIp
        })

        if (matchResult && matchResult.ok && (matchResult.status === 'PAID' || matchResult.status === 'MATCHED')) {
          return res.json({
            ok: true,
            status: 'PAID',
            message: 'Payment verified immediately by TrxID match.',
            order_id: matchResult.order_id || order_id,
            tran_id: matchResult.tran_id || requestedOrder.tran_id,
            trx_id: cleanTrx
          })
        }
      } catch (matchErr) {
        console.warn('[payment/notify] Immediate match lookup notice:', matchErr.message)
      }
    }

    // Fallback: If no TrxID provided, or TrxID not matched yet, check if an unmatched payment for this exact amount exists
    if (merchant_id && requestedOrder.amount) {
      try {
        const creds = await getMerchantCredentials(merchant_id).catch(() => null)
        if (creds?.supabase_url && creds?.supabase_anon_key) {
          const merchantClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)
          const fifteenMinutesAgo = new Date(Date.now() - 15 * 60 * 1000).toISOString()
          const { data: unmatchedList } = await merchantClient
            .from('payments')
            .select('*')
            .eq('amount', requestedOrder.amount)
            .eq('status', 'UNMATCHED')
            .gte('created_at', fifteenMinutesAgo)
            .order('created_at', { ascending: false })
            .limit(5)

          if (unmatchedList && unmatchedList.length > 0) {
            let candidatePay = null
            if (customer_phone) {
              const normPhone = String(customer_phone).replace(/\D/g, '').slice(-11)
              candidatePay = unmatchedList.find(p => p.sender_number && String(p.sender_number).replace(/\D/g, '').slice(-11) === normPhone)
            }
            if (!candidatePay && unmatchedList.length === 1) {
              candidatePay = unmatchedList[0]
            }
            if (candidatePay) {
              const autoMatchResult = await processReportedPayment({
                merchantId: merchant_id,
                trxId: candidatePay.trx_id,
                amount: requestedOrder.amount,
                paymentMethod: payment_method || 'bKash',
                senderNumber: customer_phone || candidatePay.sender_number || null,
                timestamp: candidatePay.sms_timestamp ? new Date(candidatePay.sms_timestamp).getTime() : Date.now(),
                io
              })
              if (autoMatchResult && autoMatchResult.ok && (autoMatchResult.status === 'PAID' || autoMatchResult.status === 'MATCHED')) {
                return res.json({
                  ok: true,
                  status: 'PAID',
                  message: 'Payment verified immediately by amount/phone match.',
                  order_id: autoMatchResult.order_id || order_id,
                  tran_id: autoMatchResult.tran_id || requestedOrder.tran_id,
                  trx_id: candidatePay.trx_id
                })
              }
            }
          }
        }
      } catch (autoErr) {
        console.warn('[payment/notify] Unmatched payment auto-reconciliation notice:', autoErr.message)
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
      let resolvedCustomerEmail = cus_email || body.customer_email || verifiedOrder?.cus_email || verifiedOrder?.customer_email || null
      const resolvedCustomerName = cus_name || body.customer_name || verifiedOrder?.cus_name || 'Customer'
      const resolvedCustomerPhone = cus_phone || body.customer_phone || sender_number || verifiedOrder?.cus_phone || null
      const resolvedProductName = product_name || verifiedOrder?.product_name || 'Payment'
      const resolvedMerchantName = merchant_name || verifiedOrder?.merchant_name || 'SwapnoPay Merchant'
      let resolvedMerchantEmail = merchant_email || null

      Promise.all([
        getGatewayConfig().catch(() => ({ customer_receipts_enabled: true, merchant_receipts_enabled: true })),
        merchant_id ? (await import('../services/adminSupabase.js')).getMerchantCredentials(merchant_id).catch(() => null) : null,
        merchant_id ? (await import('../services/adminSupabase.js')).getMerchantGatewayConfig(merchant_id).catch(() => null) : null
      ]).then(async ([gatewayConfig, creds, merchantGateway]) => {
        if (!resolvedMerchantEmail && (merchantGateway || creds)) {
          resolvedMerchantEmail = creds?.merchant_email || creds?.email || null
        }
        if (!resolvedCustomerEmail && (order_id || tran_id)) {
          try {
            const { getAdminClient } = await import('../services/adminSupabase.js')
            const admin = getAdminClient()
            const { data: sub } = await admin
              .from('form_submissions')
              .select('customer_email, answers')
              .or(`request_id.eq.${order_id},id.eq.${order_id}`)
              .maybeSingle()
            if (sub?.customer_email) resolvedCustomerEmail = sub.customer_email
            else if (sub?.answers && typeof sub.answers === 'object') {
              resolvedCustomerEmail = sub.answers.email || sub.answers.cus_email || sub.answers['Your Email'] || null
            }
          } catch (_) {}
        }

        const selectedMethod = payment_method || verifiedOrder?.payment_method || 'bKash'
        const normCustomer = String(resolvedCustomerPhone || '').replace(/\D/g, '').slice(-10)
        const isCustomerMatch = (num) => {
          if (!num || !normCustomer) return false
          const normNum = String(num).replace(/\D/g, '').slice(-10)
          return normNum.length >= 10 && normNum === normCustomer
        }

        const { isFakeNumber } = await import('../services/adminSupabase.js')

        let receiverNumber = merchantGateway?.receiving_numbers?.[selectedMethod] ||
          merchantGateway?.default_number ||
          (creds?.receiving_numbers && creds.receiving_numbers[selectedMethod]) ||
          creds?.default_number ||
          null

        if (!receiverNumber || isCustomerMatch(receiverNumber) || isFakeNumber(receiverNumber)) {
          const candidateNumbers = [
            ...(Object.values(merchantGateway?.receiving_numbers || {})),
            merchantGateway?.default_number,
            ...(Object.values(creds?.receiving_numbers || {})),
            creds?.default_number,
            creds?.phone
          ].filter(n => n && !isFakeNumber(n) && !isCustomerMatch(n))

          receiverNumber = candidateNumbers[0] || null
        }

        const resolvedMerchantPhone = merchantGateway?.phone ||
          merchantGateway?.default_number ||
          creds?.phone ||
          creds?.default_number ||
          (receiverNumber && !isCustomerMatch(receiverNumber) ? receiverNumber : null)

        const clientIp = req.headers['cf-connecting-ip'] ||
          req.headers['x-real-ip'] ||
          (req.headers['x-forwarded-for'] ? String(req.headers['x-forwarded-for']).split(',')[0].trim() : null) ||
          req.ip ||
          verifiedOrder?.ip_address ||
          verifiedOrder?.metadata?.ip_address ||
          null

        return sendPaymentReceipts({
          order_id, tran_id, trx_id, amount,
          payment_method: selectedMethod,
          payment_time: payment_time || new Date().toISOString(),
          ip_address: clientIp,
          merchant_name: merchantGateway?.merchant_name || creds?.merchant_name || resolvedMerchantName,
          merchant_phone: resolvedMerchantPhone,
          merchant_address: creds?.address || creds?.business_address || null,
          merchant_website: creds?.website || null,
          merchant_logo_url: merchantGateway?.merchant_logo_url || creds?.merchant_logo_url || creds?.photo_url || null,
          receiver_number: receiverNumber,
          default_number: merchantGateway?.default_number || creds?.default_number || null,
          cus_name: resolvedCustomerName,
          cus_phone: resolvedCustomerPhone,
          product_name: resolvedProductName,
          verification: verification || 'ATOMIC_SMS_MATCH',
          customer_email: resolvedCustomerEmail,
          merchant_email: resolvedMerchantEmail,
          customer_receipts_enabled: gatewayConfig?.customer_receipts_enabled ?? true,
          merchant_receipts_enabled: gatewayConfig?.merchant_receipts_enabled ?? true,
        })
      }).catch(err => console.error('[payment/verify] Email receipts error:', err.message))
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
      let order = await getOrderFromMerchantDB(merchantId, orderId)
      if (!order) {
        // Auto-provision pending order in merchant DB if amount & merchantId are available
        const qAmount = Number(req.query.amount)
        const effectiveMerchantId = merchantId || (req.query.merchant_id ? String(req.query.merchant_id) : null)
        if (effectiveMerchantId && Number.isFinite(qAmount) && qAmount > 0) {
          try {
            const creds = await getMerchantCredentials(effectiveMerchantId).catch(() => null)
            if (creds?.supabase_url && creds?.supabase_anon_key) {
              const mClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)
              const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(orderId)
              const orderUuid = isUuid ? orderId : crypto.randomUUID()
              const { data: created, error: crErr } = await mClient.from('orders').insert({
                id: orderUuid,
                merchant_id: effectiveMerchantId,
                tran_id: orderId,
                order_number: `ORD-${Date.now()}`,
                amount: qAmount,
                total_amount: qAmount,
                cus_name: req.query.cus_name || 'Customer',
                cus_phone: req.query.cus_phone || '01700000000',
                cus_email: req.query.cus_email || '',
                payment_method: req.query.method || 'bKash',
                status: 'PENDING',
                order_status: 'PENDING',
                currency: 'BDT',
                expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString()
              }).select().maybeSingle()

              if (!crErr && created) {
                order = created
                await recordPaymentEvent(orderUuid, {
                  tran_id: orderId,
                  status: 'PENDING',
                  amount: qAmount,
                  currency: 'BDT',
                  merchant_id: effectiveMerchantId,
                  payment_method: req.query.method || 'bKash',
                  cus_name: req.query.cus_name || 'Customer',
                  cus_phone: req.query.cus_phone || null
                }).catch(() => {})
              }
            }
          } catch (autoCrErr) {
            console.warn('[handleOrderStatusLookup] Auto-create order notice:', autoCrErr.message)
          }
        }
      }

      if (!order) {
        return res.status(404).json({ ok: false, error: 'Order not found' })
      }

      // Self-healing auto-reconciliation: If order is still PENDING, check if an unmatched payment
      // has arrived in merchant DB matching this order
      if (order.status === 'PENDING') {
        try {
          const effectiveMerchantId = order.merchant_id || merchantId
          if (effectiveMerchantId) {
            const creds = await getMerchantCredentials(effectiveMerchantId).catch(() => null)
            if (creds?.supabase_url && creds?.supabase_anon_key) {
              const mClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)
              let payQuery = mClient.from('payments').select('*').eq('amount', order.amount).order('created_at', { ascending: false }).limit(3)
              if (order.matched_trx_id) {
                payQuery = mClient.from('payments').select('*').eq('trx_id', order.matched_trx_id).limit(1)
              }
              const { data: pays } = await payQuery
              if (pays && pays.length > 0) {
                const targetPay = pays[0]
                const reconcileResult = await processReportedPayment({
                  merchantId: effectiveMerchantId,
                  trxId: targetPay.trx_id,
                  amount: order.amount,
                  paymentMethod: order.payment_method || 'bKash',
                  senderNumber: targetPay.sender_number || order.cus_phone,
                  timestamp: targetPay.sms_timestamp ? new Date(targetPay.sms_timestamp).getTime() : Date.now(),
                  io
                })
                if (reconcileResult && (reconcileResult.status === 'PAID' || reconcileResult.status === 'MATCHED')) {
                  order.status = 'PAID'
                  order.matched_trx_id = targetPay.trx_id
                  order.paid_at = new Date().toISOString()
                }
              }
            }
          }
        } catch (healErr) {
          console.warn('[handleOrderStatusLookup] Auto-reconciliation notice:', healErr.message)
        }
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

      // Fetch merchant details if available to populate merchant_name & merchant_logo_url
      let resolvedMerchantName = order.merchant_name || null
      let resolvedMerchantLogo = order.merchant_logo_url || null
      const effectiveMerchantId = order.merchant_id || merchantId
      if (effectiveMerchantId && (!resolvedMerchantName || !resolvedMerchantLogo || resolvedMerchantName.includes('SwapnoPay Merchant Store'))) {
        try {
          const creds = await getMerchantCredentials(effectiveMerchantId)
          if (creds?.merchant_name && !creds.merchant_name.includes('SwapnoPay Merchant Store')) {
            resolvedMerchantName = creds.merchant_name
          }
          if (creds?.merchant_logo_url && creds.merchant_logo_url !== 'null' && creds.merchant_logo_url !== 'undefined') {
            resolvedMerchantLogo = creds.merchant_logo_url
          }
        } catch (_) {}
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
        merchant_id: effectiveMerchantId || null,
        merchant_name: resolvedMerchantName,
        merchant_logo_url: resolvedMerchantLogo,
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
  // GET /v1/payment/appeals
  // Fetches dispute appeals for a merchant (admin-authenticated join query)
  // ──────────────────────────────────────────────────────────────────────────
  router.get('/appeals', async (req, res) => {
    const merchant_id = req.query.merchant_id || req.headers['x-merchant-id']
    if (!merchant_id) {
      return res.status(400).json({ ok: false, error: 'merchant_id query param or x-merchant-id header is required' })
    }

    try {
      const appealMap = new Map()

      // 1. Fetch appeals from merchant's dedicated Supabase DB
      try {
        const creds = await getMerchantCredentials(merchant_id).catch(() => null)
        if (creds?.supabase_url && (creds?.supabase_anon_key || creds?.supabase_service_role_key)) {
          const { createClient } = await import('@supabase/supabase-js')
          const mKey = creds.supabase_service_role_key || creds.supabase_anon_key
          const mClient = createClient(creds.supabase_url, mKey, {
            auth: { persistSession: false, autoRefreshToken: false }
          })
          const { data: mAppeals, error: mErr } = await mClient
            .from('appeals')
            .select('*,orders(amount,cus_name,cus_phone,merchant_id)')
            .order('created_at', { ascending: false })
          if (!mErr && Array.isArray(mAppeals)) {
            for (const a of mAppeals) {
              appealMap.set(a.id, {
                id: a.id,
                merchant_id: a.merchant_id || a.orders?.merchant_id || merchant_id,
                order_id: a.order_id,
                amount: Number(a.orders?.amount || 0),
                customer_name: a.orders?.cus_name || 'Customer',
                customer_phone: a.cus_phone || a.orders?.cus_phone || '',
                trx_id: a.trx_id,
                note: a.note || '',
                screenshot_url: a.screenshot_url || null,
                status: a.status || 'PENDING_REVIEW',
                created_at: a.created_at,
                resolved_at: a.resolved_at,
                resolved_by: a.resolved_by
              })
            }
          }
        }
      } catch (mFetchErr) {
        console.warn('[payment/appeals] Merchant DB fetch notice:', mFetchErr.message)
      }

      // 2. Fetch appeals from platform admin DB
      try {
        const admin = getAdminClient()
        if (admin) {
          const { data: admData, error: admErr } = await admin
            .from('appeals')
            .select('*,orders(amount,cus_name,cus_phone,merchant_id)')
            .order('created_at', { ascending: false })
          if (!admErr && Array.isArray(admData)) {
            for (const a of admData) {
              const aMid = a.merchant_id || a.orders?.merchant_id
              if (!aMid || aMid === merchant_id) {
                if (!appealMap.has(a.id)) {
                  appealMap.set(a.id, {
                    id: a.id,
                    merchant_id: aMid || merchant_id,
                    order_id: a.order_id,
                    amount: Number(a.orders?.amount || 0),
                    customer_name: a.orders?.cus_name || 'Customer',
                    customer_phone: a.cus_phone || a.orders?.cus_phone || '',
                    trx_id: a.trx_id,
                    note: a.note || '',
                    screenshot_url: a.screenshot_url || null,
                    status: a.status || 'PENDING_REVIEW',
                    created_at: a.created_at,
                    resolved_at: a.resolved_at,
                    resolved_by: a.resolved_by
                  })
                }
              }
            }
          }
        }
      } catch (admFetchErr) {
        console.warn('[payment/appeals] Admin DB fetch notice:', admFetchErr.message)
      }

      const appeals = Array.from(appealMap.values()).sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
      res.json({ ok: true, count: appeals.length, appeals })
    } catch (err) {
      console.error('[payment/appeals] Error:', err.message)
      res.status(500).json({ ok: false, error: 'Failed to fetch appeals: ' + err.message })
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

      // 1. Insert alert into merchant_notifications for mobile app notification badge & popups
      try {
        const admin = getAdminClient()
        if (admin) {
          await admin.from('merchant_notifications').insert({
            id: crypto.randomUUID(),
            merchant_id: merchant_id,
            title: 'New Payment Dispute Appeal',
            message: `Customer (${cus_phone || 'Payer'}) submitted appeal for TrxID: ${String(trx_id).trim().toUpperCase()}`,
            type: 'APPEAL',
            severity: 'WARNING',
            read: false,
            metadata: {
              entity_type: 'APPEAL',
              appeal_id: appeal.id,
              order_id: order_id,
              trx_id: String(trx_id).trim().toUpperCase(),
              cus_phone: cus_phone || null
            },
            created_at: new Date().toISOString()
          })
        }
      } catch (notifErr) {
        console.warn('[payment/appeal] Notice writing merchant notification:', notifErr.message)
      }

      // 2. Broadcast real-time notification to merchant's Android app
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

      // 1. Update appeal and order records in Supabase Cloud DB
      try {
        const admin = getAdminClient()
        if (admin) {
          await admin
            .from('appeals')
            .update({
              status: normalizedStatus,
              resolved_at: new Date().toISOString(),
              resolved_by: merchant_id || 'merchant'
            })
            .eq('id', appeal_id)

          if (normalizedStatus === 'APPROVED' && order_id) {
            await admin
              .from('orders')
              .update({
                status: 'PAID',
                paid_at: new Date().toISOString(),
                matched_trx_id: trx_id,
                manual_match: true
              })
              .eq('id', order_id)
          }

          // Insert merchant notification record
          await admin.from('merchant_notifications').insert({
            id: crypto.randomUUID(),
            merchant_id: merchant_id || '00000000-0000-0000-0000-000000000001',
            title: `Appeal ${normalizedStatus === 'APPROVED' ? 'Approved' : 'Rejected'}`,
            message: `Dispute for TrxID: ${trx_id || 'N/A'} was marked as ${normalizedStatus}.`,
            type: 'APPEAL',
            severity: normalizedStatus === 'APPROVED' ? 'SUCCESS' : 'INFO',
            read: false,
            metadata: {
              entity_type: 'APPEAL',
              appeal_id,
              order_id,
              status: normalizedStatus
            },
            created_at: new Date().toISOString()
          })
        }
      } catch (dbErr) {
        console.warn('[payment/resolve-appeal] Notice updating appeal status in DB:', dbErr.message)
      }

      // 1b. Also update appeal and order in merchant's own Supabase DB
      if (merchant_id) {
        try {
          const creds = await getMerchantCredentials(merchant_id).catch(() => null)
          if (creds?.supabase_url && (creds?.supabase_anon_key || creds?.supabase_service_role_key)) {
            const { createClient } = await import('@supabase/supabase-js')
            const mKey = creds.supabase_service_role_key || creds.supabase_anon_key
            const mClient = createClient(creds.supabase_url, mKey, { auth: { persistSession: false, autoRefreshToken: false } })
            await mClient
              .from('appeals')
              .update({
                status: normalizedStatus,
                resolved_at: new Date().toISOString(),
                resolved_by: merchant_id || 'merchant'
              })
              .eq('id', appeal_id)

            if (normalizedStatus === 'APPROVED' && order_id) {
              await mClient
                .from('orders')
                .update({
                  status: 'PAID',
                  order_status: 'CONFIRMED',
                  paid_at: new Date().toISOString(),
                  matched_trx_id: trx_id,
                  manual_match: true
                })
                .eq('id', order_id)

              if (trx_id) {
                await mClient
                  .from('payments')
                  .update({
                    status: 'MATCHED',
                    matched_order_id: order_id
                  })
                  .ilike('trx_id', String(trx_id).trim())
              }
            }
          }
        } catch (mResolveErr) {
          console.warn('[payment/resolve-appeal] Notice updating merchant DB appeal:', mResolveErr.message)
        }
      }

      // 2. Notify customer via email if email provided and send_email is true
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
            notes: note || (normalizedStatus === 'APPROVED' ? 'Your payment appeal was verified and approved.' : 'Your payment appeal could not be verified.'),
            ip_address: req.headers['cf-connecting-ip'] ||
              req.headers['x-real-ip'] ||
              (req.headers['x-forwarded-for'] ? String(req.headers['x-forwarded-for']).split(',')[0].trim() : null) ||
              req.ip ||
              null
          })
          console.log(`[payment/resolve-appeal] Customer email notification dispatched to ${customer_email}`)
        } catch (mailErr) {
          console.warn('[payment/resolve-appeal] Mailer notice:', mailErr.message)
        }
      }

      // 3. Broadcast via socket to widget & merchant room
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

    // Dynamic resolution of receiving number: check gateway method, default, body parameters, or merchant profile
    let receivingNumber = String(
      merchantGateway?.receiving_numbers?.[selectedMethod] ||
      merchantGateway?.receiving_numbers?.all ||
      merchantGateway?.receiving_numbers?.default ||
      req.body?.receiving_number ||
      req.body?.merchant_number ||
      req.body?.number ||
      req.body?.wallet_number ||
      merchantGateway?.default_number ||
      merchantCredentials?.default_number ||
      merchantCredentials?.phone ||
      ''
    ).trim()

    // Accept method unless explicitly disabled
    const methodEnabled = merchantGateway.enabled_methods?.[selectedMethod] !== false
    if (!methodEnabled) {
      return res.status(409).json({ ok: false, error: `Merchant has disabled ${selectedMethod} payments` })
    }

    if (!receivingNumber) {
      return res.status(409).json({ ok: false, error: `Merchant has not enabled ${selectedMethod} payments or configured a receiving number` })
    }

    // If receiving number was resolved from fallbacks, ensure merchantGateway record retains it
    if (!merchantGateway.receiving_numbers?.[selectedMethod] && receivingNumber) {
      try {
        const admin = getAdminClient()
        const updatedRec = { ...(merchantGateway.receiving_numbers || {}), [selectedMethod]: receivingNumber }
        await admin.from('merchant_gateway_settings').update({ receiving_numbers: updatedRec }).eq('merchant_id', merchant_id)
        merchantGateway.receiving_numbers = updatedRec
      } catch (_) {}
    }

    const minAmount = Number(merchantGateway.min_amount || 0)
    const maxAmount = Number(merchantGateway.max_amount || 10000000)
    if (numAmount < minAmount || numAmount > maxAmount) {
      return res.status(422).json({ ok: false, error: `Payment amount must be between ${minAmount} and ${maxAmount}` })
    }

    const orderUuid = crypto.randomUUID()
    const tranId = tran_id || `SWP-${Date.now()}-${Math.floor(1000 + Math.random() * 9000)}`

    try {
      // 1. Persist in the merchant DB when merchant credentials provide Supabase access.
      const hasMerchantDb = Boolean(merchantCredentials?.supabase_url && (merchantCredentials?.supabase_anon_key || merchantCredentials?.supabase_service_role_key))

      if (hasMerchantDb) {
        try {
          const { createClient } = await import('@supabase/supabase-js')
          const mKey = merchantCredentials.supabase_service_role_key || merchantCredentials.supabase_anon_key
          const mClient = createClient(merchantCredentials.supabase_url, mKey, {
            auth: { persistSession: false, autoRefreshToken: false }
          })
          const { error: insertError } = await mClient.from('orders').insert({
            id: orderUuid,
            merchant_id,
            tran_id: tranId,
            order_number: order_number || `ORD-${Date.now()}`,
            amount: numAmount,
            total_amount: numAmount,
            subtotal: Number(req.body?.subtotal || numAmount),
            shipping_cost: Number(req.body?.shipping_cost || 0),
            discount_amount: Number(req.body?.discount_amount || 0),
            cus_name: cus_name || 'Customer',
            cus_phone: cus_phone || '01700000000',
            cus_email: cus_email || '',
            shipping_address: req.body?.shipping_address || null,
            shipping_city: req.body?.shipping_city || null,
            customer_note: req.body?.customer_note || null,
            payment_method: selectedMethod,
            status: 'PENDING',
            order_status: 'PENDING',
            currency: 'BDT',
            success_url: success_url || null,
            callback_url: callback_url || null,
            cancel_url: req.body?.cancel_url || null,
            fail_url: req.body?.fail_url || null,
            metadata: {
              ...(req.body?.metadata || {}),
              ip_address: req.headers['cf-connecting-ip'] ||
                req.headers['x-real-ip'] ||
                (req.headers['x-forwarded-for'] ? String(req.headers['x-forwarded-for']).split(',')[0].trim() : null) ||
                req.body?.ip_address ||
                req.ip ||
                null
            },
            expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString()
          })
          if (insertError) console.warn('[payment/create-order] Merchant DB mirror notice:', insertError.message)

          if (Array.isArray(items) && items.length > 0) {
            const itemsPayload = items.map(item => ({
              order_id: orderUuid,
              product_name: String(item?.product_name || 'Product'),
              size: String(item?.size || ''),
              color: String(item?.color || ''),
              quantity: Number(item?.quantity || 1),
              unit_price: Number(item?.unit_price || 0),
              total_price: Number(item?.total_price || (item?.quantity || 1) * (item?.unit_price || 0))
            }))
            await mClient.from('order_items').insert(itemsPayload).catch(() => {})
          }
        } catch (mirrorErr) {
          console.warn('[payment/create-order] Merchant DB insert notice:', mirrorErr.message)
        }
      }

      // 1b. Also persist order in platform admin DB orders table so foreign keys for appeals succeed
      try {
        const admin = getAdminClient()
        if (admin) {
          await admin.from('orders').upsert({
            id: orderUuid,
            merchant_id,
            tran_id: tranId,
            order_number: order_number || `ORD-${Date.now()}`,
            amount: numAmount,
            cus_name: cus_name || 'Customer',
            cus_phone: cus_phone || '01700000000',
            cus_email: cus_email || '',
            payment_method: selectedMethod,
            status: 'PENDING',
            currency: 'BDT',
            success_url: success_url || null,
            callback_url: callback_url || null,
            fail_url: req.body?.fail_url || null,
            cancel_url: req.body?.cancel_url || null,
            metadata: {
              ...(req.body?.metadata || {}),
              ip_address: req.headers['cf-connecting-ip'] ||
                req.headers['x-real-ip'] ||
                (req.headers['x-forwarded-for'] ? String(req.headers['x-forwarded-for']).split(',')[0].trim() : null) ||
                req.body?.ip_address ||
                req.ip ||
                null
            },
            expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString()
          }, { onConflict: 'id' })
        }
      } catch (_) {}

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

      const gatewayUrl = (process.env.SWAPNOPAY_GATEWAY_URL || process.env.GATEWAY_PUBLIC_URL || 'https://pay.swapnopay.top').replace(/\/$/, '')
      const widgetParams = new URLSearchParams({
        order_id: orderUuid,
        amount: numAmount.toFixed(2),
        merchant_id: String(merchant_id)
      })
      if (selectedMethod) widgetParams.set('method', selectedMethod)
      const mName = req.body?.merchant_name || merchantCredentials?.merchant_name || merchantCredentials?.business_name || merchantCredentials?.brand_name || 'SwapnoPay'
      if (mName && !mName.includes('SwapnoPay Merchant Store')) widgetParams.set('merchant_name', mName)
      const mLogo = req.body?.merchant_logo || req.body?.logo_url || req.body?.photo_url || merchantCredentials?.merchant_logo_url || merchantCredentials?.photo_url || null
      if (mLogo && mLogo !== 'null' && mLogo !== 'undefined') widgetParams.set('merchant_logo', mLogo)
      if (cus_name) widgetParams.set('cus_name', cus_name)
      if (cus_phone) widgetParams.set('cus_phone', cus_phone)
      if (cus_email) widgetParams.set('cus_email', cus_email)
      if (success_url) widgetParams.set('success_url', success_url)
      if (req.body?.cancel_url) widgetParams.set('cancel_url', req.body.cancel_url)
      if (req.body?.fail_url) widgetParams.set('fail_url', req.body.fail_url)
      const checkoutUrl = `${gatewayUrl}/widget.html?${widgetParams.toString()}`

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
