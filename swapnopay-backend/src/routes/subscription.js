// SwapnoPay Backend — Subscription & Anti-Piracy Billing Routes
// Controls dynamic subscription pricing, anti-abuse NID verification,
// 3-month free trial allocation, and SwapnoPay native payment gateway checkout.

import { Router } from 'express'
import {
  getSubscriptionConfig,
  getMerchantSubscriptionStatus,
  createSubscriptionOrder,
  verifyAndActivateSubscription,
  getMerchantSubscriptionHistory,
  downgradeMerchantSubscription,
} from '../services/adminSupabase.js'
import { requirePlatformUser } from '../services/merchantAccount.js'

const router = Router()

// ────────────────────────────────────────────────────────────────────────────
// 1. GET /v1/subscription/config — Public / App Pricing Configuration
// ────────────────────────────────────────────────────────────────────────────
router.get('/config', async (_req, res) => {
  try {
    const config = await getSubscriptionConfig()
    return res.json({
      ok: true,
      config: {
        monthly_fee: config.monthly_fee,
        quarterly_fee: config.quarterly_fee,
        yearly_fee: config.yearly_fee,
        trial_days: config.trial_days,
        is_trial_enabled: config.is_trial_enabled,
        enforce_nid_verification: config.enforce_nid_verification,
        updated_at: config.updated_at
      }
    })
  } catch (err) {
    console.error('[subscription/config GET]', err.message)
    return res.status(500).json({ ok: false, error: err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// 2. GET /v1/subscription/status — Check Account Subscription, Trial & NID Gate
// ────────────────────────────────────────────────────────────────────────────
router.get('/status', async (req, res) => {
  try {
    const merchantId = req.query.merchant_id || req.headers['x-merchant-id'] || 'default'
    const email = req.query.email || null
    const deviceId = req.query.device_id || req.headers['x-device-id'] || null
    const status = await getMerchantSubscriptionStatus(merchantId, email, deviceId)
    return res.json(status)
  } catch (err) {
    console.error('[subscription/status GET]', err.message)
    return res.status(500).json({ ok: false, error: err.message })
  }
})

// Authenticated status endpoint
router.get('/my-status', requirePlatformUser, async (req, res) => {
  try {
    const merchantId = req.platformUser.id
    const email = req.platformUser.email
    const status = await getMerchantSubscriptionStatus(merchantId, email)
    return res.json(status)
  } catch (err) {
    console.error('[subscription/my-status GET]', err.message)
    return res.status(500).json({ ok: false, error: err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// 3. POST /v1/subscription/checkout — Initiate SwapnoPay Gateway Checkout
// Supports JSON API response for apps/SDKs or HTTP 302 browser redirect for web storefronts
// ────────────────────────────────────────────────────────────────────────────
const handleSubscriptionCheckout = async (req, res) => {
  try {
    const payload = req.method === 'GET' ? req.query : (req.body || {})
    const {
      merchant_id = 'default',
      plan_type = 'MONTHLY',
      payment_method = 'bKash',
      success_url,
      fail_url,
      cancel_url,
      redirect = false,
      format,
    } = payload

    const order = await createSubscriptionOrder({
      merchantId: merchant_id,
      planType: plan_type,
      method: payment_method,
      successUrl: success_url,
      failUrl: fail_url,
      cancelUrl: cancel_url,
    })

    const shouldRedirect = redirect === true || redirect === 'true' || format === 'redirect'
    if (shouldRedirect && order.checkout_url) {
      return res.redirect(302, order.checkout_url)
    }

    return res.json({
      ok: true,
      status: 'SUCCESS',
      ...order,
      redirect_url: order.checkout_url,
    })
  } catch (err) {
    console.error('[subscription/checkout]', err.message)
    return res.status(400).json({ ok: false, error: err.message })
  }
}

router.post('/checkout', handleSubscriptionCheckout)
router.get('/checkout', handleSubscriptionCheckout)

// ────────────────────────────────────────────────────────────────────────────
// 3.1 POST /v1/subscription/webhook — Instant Payment Notification (IPN) Webhook
// Mirrors standard merchant webhook listener for SwapnoPay payment gateway
// ────────────────────────────────────────────────────────────────────────────
router.post('/webhook', async (req, res) => {
  try {
    const {
      order_id,
      tran_id,
      trx_id,
      status = 'PAID',
      merchant_id = 'default',
      payment_method = 'bKash',
      plan_type,
    } = req.body || {}

    const resolvedOrderId = order_id || tran_id
    if (!resolvedOrderId) {
      return res.status(400).json({ ok: false, error: 'order_id is required' })
    }

    if (status === 'PAID') {
      const result = await verifyAndActivateSubscription({
        merchantId: merchant_id,
        orderId: resolvedOrderId,
        trxId: trx_id || resolvedOrderId,
        method: payment_method,
        planType: plan_type,
      })

      if (req.io) {
        req.io.to(`merchant:${merchant_id}`).emit('merchant:subscription_updated', result)
      }

      return res.json({ ok: true, status: 'ACTIVATED', result })
    }

    return res.json({ ok: true, status: 'IGNORED', message: `Order status is ${status}` })
  } catch (err) {
    console.error('[subscription/webhook POST]', err.message)
    return res.status(400).json({ ok: false, error: err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// 4. POST /v1/subscription/verify — Verify TrxID, Bind NID, and Activate
// ────────────────────────────────────────────────────────────────────────────
router.post('/verify', async (req, res) => {
  try {
    const {
      merchant_id = 'default',
      order_id,
      trx_id,
      payment_method = 'bKash',
      plan_type
    } = req.body || {}

    if (!trx_id) {
      return res.status(400).json({ ok: false, error: 'Transaction ID (TrxID) প্রদান করা আবশ্যক।' })
    }

    const result = await verifyAndActivateSubscription({
      merchantId: merchant_id,
      orderId: order_id,
      trxId: trx_id,
      method: payment_method,
      planType: plan_type
    })

    // Broadcast realtime event
    if (req.io) {
      req.io.to(`merchant:${merchant_id}`).emit('merchant:subscription_updated', result)
      req.io.to('admin').emit('admin:subscription_activated', {
        merchant_id,
        plan: result.subscription_plan,
        trx_id,
        activated_at: new Date().toISOString()
      })
    }

    return res.json(result)
  } catch (err) {
    console.error('[subscription/verify POST]', err.message)
    return res.status(400).json({ ok: false, error: err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// 5. GET /v1/subscription/history — Retrieve Past Subscription Payment Records
// ────────────────────────────────────────────────────────────────────────────
router.get('/history', async (req, res) => {
  try {
    const merchantId = req.query.merchant_id || req.headers['x-merchant-id']
    if (!merchantId) {
      return res.status(400).json({ ok: false, error: 'merchant_id is required' })
    }
    const history = await getMerchantSubscriptionHistory(merchantId)
    return res.json({ ok: true, history })
  } catch (err) {
    console.error('[subscription/history GET]', err.message)
    return res.status(500).json({ ok: false, error: err.message })
  }
})

// ────────────────────────────────────────────────────────────────────────────
// 6. POST /v1/subscription/downgrade — Switch Account to Free Plan
// ────────────────────────────────────────────────────────────────────────────
router.post('/downgrade', async (req, res) => {
  try {
    const { merchant_id = 'default' } = req.body || {}
    const result = await downgradeMerchantSubscription(merchant_id)
    if (req.io) {
      req.io.to(`merchant:${merchant_id}`).emit('merchant:subscription_updated', result)
    }
    return res.json(result)
  } catch (err) {
    console.error('[subscription/downgrade POST]', err.message)
    return res.status(400).json({ ok: false, error: err.message })
  }
})

export { router as subscriptionRouter }

