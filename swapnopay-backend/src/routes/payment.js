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
import { requireAdminSecret, requireWebhookSecret, requireMerchantOrAdminAuth } from '../middleware/auth.js'
import {
  getGatewayConfig,
  getMerchantGatewayConfig,
  setMerchantGatewayConfig,
  recordPaymentEvent,
  getOrderFromMerchantDB,
  updateOrderStatusOnMerchantDB,
  getMerchantDeviceStatus,
  createDisputeAppeal,
} from '../services/adminSupabase.js'
import { verifyWebhookSignature } from '../utils/crypto.js'
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
      let merchantId = req.query.merchant_id || null
      const orderId = req.query.order_id || null

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

      const config = await getMerchantGatewayConfig(merchantId, heartbeatMap)

      res.json({
        ok: true,
        // Gateway options
        enabled_methods:            config.enabled_methods,
        payment_timeout_seconds:    config.payment_timeout_seconds,
        processing_timeout_seconds: config.processing_timeout_seconds,
        min_amount:                 config.min_amount,
        max_amount:                 config.max_amount,
        default_success_url:        config.default_success_url,
        default_fail_url:           config.default_fail_url,
        default_cancel_url:         config.default_cancel_url,
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
        enabled_methods: { bKash: true, Nagad: true, Rocket: true, Upay: true },
        payment_timeout_seconds: 600,
        processing_timeout_seconds: 300,
        maintenance_mode: false,
        maintenance_message: '',
        receiving_numbers: {},
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
  router.get('/transactions', async (req, res) => {
    try {
      const merchantId = req.query.merchant_id || req.headers['x-merchant-id']
      const limit = Math.min(parseInt(req.query.limit || '50', 10), 200)
      if (!merchantId) {
        return res.status(400).json({ ok: false, error: 'merchant_id is required' })
      }
      const { getAdminClient } = await import('../services/adminSupabase.js')
      const admin = getAdminClient()
      const { data, error } = await admin
        .from('payment_events')
        .select('*')
        .or(`merchant_id.eq.${merchantId},order_id.eq.${merchantId}`)
        .order('created_at', { ascending: false })
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
        timestamp: new Date(row.payment_time || row.created_at).getTime(),
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
      const { merchant_id, device_id, battery_level, status } = req.body || {}
      if (!merchant_id || typeof merchant_id !== 'string') {
        return res.status(400).json({ ok: false, error: 'merchant_id is required' })
      }

      heartbeatMap.set(merchant_id, {
        ts: Date.now(),
        socketId: null,
        deviceId: device_id || null,
        batteryLevel: battery_level ?? null,
        status: status || 'ONLINE',
      })

      // Broadcast to merchant room for real-time dashboard listeners
      if (io) {
        io.to(`merchant:${merchant_id}`).emit('merchant_heartbeat', {
          merchant_id,
          device_id: device_id || null,
          battery_level: battery_level ?? null,
          status: status || 'ONLINE',
          ts: Date.now(),
        })
      }

      // Notify waiting customers that merchant device is back online!
      notifyWaitingCustomersMerchantOnline(merchant_id)
        .catch(err => console.warn('[payment/heartbeat] Customer notify error:', err.message))

      res.json({
        ok: true,
        merchant_id,
        device_active: true,
        ts: Date.now(),
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
  // Directly records verified payment event (status: 'PAID') and triggers
  // instant verification if an order is waiting for this TrxID!
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/report-payment', async (req, res) => {
    try {
      const {
        merchant_id, trx_id, amount, payment_method, sender_number, timestamp, device_id
      } = req.body || {}

      if (!trx_id) {
        return res.status(400).json({ ok: false, error: 'trx_id is required' })
      }

      const cleanTrx = String(trx_id).trim().toUpperCase()
      const numAmount = parseFloat(amount) || 0

      console.log(`[payment/report-payment] 📲 Incoming payment reported from Android device: TrxID ${cleanTrx} | ৳${numAmount} | Method: ${payment_method || 'bKash'} | Merchant: ${merchant_id}`)

      // 1. Record in admin DB payment_events as PAID
      try {
        recordPaymentEvent(cleanTrx, {
          tran_id: cleanTrx,
          trx_id: cleanTrx,
          status: 'PAID',
          amount: numAmount,
          currency: 'BDT',
          payment_method: payment_method || 'bKash',
          sender_number: sender_number || null,
          merchant_id: merchant_id || null,
          payment_time: timestamp ? new Date(Number(timestamp)).toISOString() : new Date().toISOString(),
          product_name: `SMS Capture on Device: ${device_id || 'unknown'}`
        })
      } catch (recErr) {
        console.warn('[payment/report-payment] Event record notice:', recErr.message)
      }

      // 2. Check if an order in payment_events is waiting for this TrxID
      try {
        const { getAdminClient } = await import('../services/adminSupabase.js')
        const admin = getAdminClient()
        if (admin) {
          const { data: pendingEvents } = await admin.from('payment_events')
            .select('*')
            .eq('trx_id', cleanTrx)
            .eq('status', 'PENDING')
            .limit(5)

          if (pendingEvents && pendingEvents.length > 0) {
            for (const ev of pendingEvents) {
              const matchedOrderId = ev.order_id || ev.tran_id
              console.log(`[payment/report-payment] 🎯 Matched pending order: ${matchedOrderId} with TrxID: ${cleanTrx}`)
              recordPaymentEvent(matchedOrderId, {
                status: 'PAID',
                trx_id: cleanTrx,
                amount: numAmount || ev.amount,
                payment_method: payment_method || ev.payment_method
              })

              io.to(`order:${matchedOrderId}`).emit('payment_status', {
                order_id: matchedOrderId,
                status: 'PAID',
                trx_id: cleanTrx,
                amount: numAmount || ev.amount,
                paid_at: new Date().toISOString()
              })

              try {
                const { handleFormPaymentPaid } = await import('./form.js')
                await handleFormPaymentPaid(matchedOrderId, cleanTrx, numAmount || ev.amount, io)
              } catch (_) {}
            }
          }
        }
      } catch (matchErr) {
        console.warn('[payment/report-payment] Pending order matching notice:', matchErr.message)
      }

      // 3. Emit payment broadcast to merchant dashboard
      if (merchant_id) {
        io.to(`merchant:${merchant_id}`).emit('payment_received', {
          trx_id: cleanTrx,
          amount: numAmount,
          method: payment_method,
          sender: sender_number,
          time: new Date().toISOString()
        })
      }

      return res.json({ ok: true, message: 'Payment recorded and verified.', trx_id: cleanTrx })
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
    const { order_id, merchant_id, payment_method, customer_phone, trx_id } = req.body || {}

    if (!order_id || typeof order_id !== 'string' || order_id.length > 100) {
      return res.status(400).json({ error: 'order_id is required' })
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

    // Record or update payment event in platform DB
    try {
      recordPaymentEvent(order_id, {
        tran_id: order_id,
        trx_id: cleanTrx,
        status: 'PENDING',
        merchant_id: merchant_id || null,
        sender_number: customer_phone || null,
        payment_method: payment_method || 'unknown',
        product_name: cleanTrx ? `Customer submitted TrxID: ${cleanTrx}` : 'Customer reported payment transfer'
      })
    } catch {}

    // Emit to widget watching this order
    io.to(`order:${order_id}`).emit('payment_pending', notifyPayload)

    // Also emit to merchant Android app room for instant notification
    if (merchant_id) {
      io.to(`merchant:${merchant_id}`).emit('customer_payment_pending', notifyPayload)
      console.log(`[payment/notify] Forwarded to merchant room: merchant:${merchant_id}`)
    }

    // Check if there is an unassigned or matching verified SMS payment event for this merchant and TrxID
    if (cleanTrx && cleanTrx.length >= 6) {
      try {
        const { getAdminClient } = await import('../services/adminSupabase.js')
        const admin = getAdminClient()
        let query = admin.from('payment_events')
          .select('*')
          .eq('trx_id', cleanTrx)
        if (merchant_id) {
          query = query.or(`merchant_id.eq.${merchant_id},merchant_id.eq.merchant_default,merchant_id.eq.00000000-0000-0000-0000-000000000001,merchant_id.is.null`)
        }
        const { data: matchedEvents } = await query.limit(1)

        if (matchedEvents && matchedEvents.length > 0 && matchedEvents[0].status === 'PAID') {
          await updateOrderStatusOnMerchantDB(merchant_id, order_id, 'PAID', {
            matched_trx_id: cleanTrx,
            payment_method: payment_method || matchedEvents[0].payment_method,
            amount: matchedEvents[0].amount
          })
          try {
            const { handleFormPaymentPaid } = await import('./form.js')
            await handleFormPaymentPaid(order_id, cleanTrx, matchedEvents[0].amount, io)
          } catch (_) {}
          io.to(`order:${order_id}`).emit('payment_status', {
            order_id,
            status: 'PAID',
            trx_id: cleanTrx,
            amount: matchedEvents[0].amount,
            paid_at: new Date().toISOString()
          })
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
    recordPaymentEvent(order_id, {
      tran_id, trx_id, status, amount,
      currency: currency || 'BDT',
      payment_method, sender_number: maskPhone(sender_number),
      payment_time, merchant_id, merchant_name,
      project_ref, cus_name, cus_email, product_name,
    }) // non-blocking

    // ── Step 2: Cross-DB — Update order on MERCHANT'S Supabase DB ──
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
        amount,
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
    let redirectUrl = null
    if (status === 'PAID' && success_url) {
      redirectUrl = buildRedirectUrl(success_url, { status: 'PAID', order_id, trx_id, tran_id, amount })
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

  // ──────────────────────────────────────────────────────────────────────────
  // POST /v1/payment/cancel
  // Customer or merchant cancellation of pending order
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/cancel', async (req, res) => {
    const { order_id, merchant_id, reason } = req.body || {}
    if (!order_id || typeof order_id !== 'string') {
      return res.status(400).json({ ok: false, error: 'order_id is required' })
    }

    try {
      console.log(`[payment/cancel] Cancelling order ${order_id} (merchant: ${merchant_id || 'unknown'}) — reason: ${reason || 'Customer cancelled'}`)

      // 1. Update merchant DB if merchant_id provided
      if (merchant_id) {
        await updateOrderStatusOnMerchantDB(merchant_id, order_id, 'CANCELLED')
      }

      // 2. Record cancellation event in platform admin DB
      recordPaymentEvent(order_id, {
        tran_id: order_id,
        status: 'CANCELLED',
        merchant_id: merchant_id || null,
        product_name: `Cancelled: ${reason || 'User cancelled'}`,
      })

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
  // POST /v1/payment/form-submission
  // Relay endpoint for hosted form / web checkout submissions
  // ──────────────────────────────────────────────────────────────────────────
  router.post('/form-submission', async (req, res) => {
    const { form_id, form_slug, merchant_id, submission_id, data, total_amount, tran_id } = req.body || {}

    if (!form_id && !form_slug) {
      return res.status(400).json({ ok: false, error: 'form_id or form_slug is required' })
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
