// SwapnoPay Backend — Merchant App PIN Routes
// POST /v1/pin/set            — Set or change PIN hash (authenticated merchant)
// GET  /v1/pin/sync           — Fetch PIN hash + reset flag (authenticated merchant)
// POST /v1/pin/request-reset  — Merchant requests admin to clear their PIN (auth or identifier)
// POST /v1/pin/admin-clear    — Admin clears a merchant PIN (admin auth only)
//
// SECURITY: Raw PINs are NEVER sent to this server.
//           Only SHA-256 hex hashes are transmitted and stored.

import { Router } from 'express'
import { requirePlatformUser, lookupMerchantInAdminDb } from '../services/merchantAccount.js'
import { requireAdminSecret } from '../middleware/auth.js'
import {
  getAdminClient,
  setPinHash,
  getPinHash,
  clearPinHash,
  requestPinReset,
} from '../services/adminSupabase.js'

export const pinRouter = Router()

function isValidSha256Hex(str) {
  return typeof str === 'string' && /^[0-9a-f]{64}$/i.test(str)
}

// POST /v1/pin/admin-clear
// Admin-only: immediately clear a merchant PIN hash.
// Body: { merchant_id: "..." }
pinRouter.post('/admin-clear', requireAdminSecret, async (req, res) => {
  try {
    const { merchant_id } = req.body || {}
    if (!merchant_id) return res.status(400).json({ error: 'merchant_id is required' })
    const result = await clearPinHash(merchant_id)
    return res.json({
      ok: true,
      message: 'Merchant PIN cleared. Merchant must set a new PIN on next login.',
      merchant_id: result?.id || merchant_id,
      pin_set: false,
      pin_reset_requested: true,
    })
  } catch (err) {
    console.error('[pin] /admin-clear error:', err.message)
    return res.status(500).json({ error: err.message })
  }
})

// POST /v1/pin/request-reset
// Merchant requests admin to clear their PIN.
// Works both when authenticated (from settings/lockscreen) and unauthenticated (by email/merchant_id).
pinRouter.post('/request-reset', async (req, res) => {
  try {
    let user = req.platformUser
    if (!user) {
      const token = req.headers.authorization?.match(/^Bearer\s+(.+)$/i)?.[1]?.trim()
      if (token) {
        try {
          const { data } = await getAdminClient().auth.getUser(token)
          if (data?.user?.id) user = data.user
        } catch (_) {}
      }
    }

    const body = req.body || {}
    let merchantId = user?.id || body.merchant_id || null
    let email = user?.email || body.email || null
    let userId = user?.id || null

    if (!merchantId && !email) {
      return res.status(400).json({ error: 'Merchant identifier or email is required' })
    }

    if (email && (!merchantId || merchantId === 'default_merchant')) {
      try {
        const lookup = await lookupMerchantInAdminDb(email, userId || '00000000-0000-0000-0000-000000000000')
        if (lookup?.merchantId) {
          merchantId = lookup.merchantId
          userId = lookup.merchant?.user_id || userId
        }
      } catch (_) {}
    } else if (merchantId && !email) {
      try {
        const admin = getAdminClient()
        const { data: m } = await admin.from('merchants').select('email, user_id').eq('id', merchantId).maybeSingle()
        if (m) {
          email = m.email || email
          userId = m.user_id || userId
        }
      } catch (_) {}
    }

    await requestPinReset(merchantId || email, email, userId)
    return res.json({
      ok: true,
      message: 'PIN reset request sent to admin. Your PIN will be cleared once the admin approves.',
    })
  } catch (err) {
    console.error('[pin] /request-reset error:', err.message)
    return res.status(500).json({ error: err.message })
  }
})

// Merchant authenticated endpoints require a valid platform auth token
pinRouter.use(requirePlatformUser)

// POST /v1/pin/set
// Body: { pin_hash: "<sha256-hex>" }
pinRouter.post('/set', async (req, res) => {
  try {
    const user = req.platformUser
    if (!user?.id) return res.status(401).json({ error: 'Merchant not authenticated' })

    let merchantId = user.id
    try {
      const lookup = await lookupMerchantInAdminDb(user.email, user.id)
      if (lookup?.merchantId) {
        merchantId = lookup.merchantId
      }
    } catch (_) {}

    const { pin_hash } = req.body || {}
    if (!pin_hash) return res.status(400).json({ error: 'pin_hash is required' })
    if (!isValidSha256Hex(pin_hash)) {
      return res.status(400).json({ error: 'pin_hash must be a valid SHA-256 hex string (64 chars)' })
    }

    const result = await setPinHash(merchantId, pin_hash, user.email, user.id)
    return res.json({
      ok: true,
      message: 'PIN updated successfully',
      pin_set: !!result?.app_pin_hash,
      pin_reset_requested: result?.pin_reset_requested || false,
    })
  } catch (err) {
    console.error('[pin] /set error:', err.message)
    return res.status(500).json({ error: err.message })
  }
})

// GET /v1/pin/sync
// Called after every successful password login to fetch cloud PIN hash.
pinRouter.get('/sync', async (req, res) => {
  try {
    const user = req.platformUser
    if (!user?.id) return res.status(401).json({ error: 'Merchant not authenticated' })

    let merchantId = user.id
    try {
      const lookup = await lookupMerchantInAdminDb(user.email, user.id)
      if (lookup?.merchantId) {
        merchantId = lookup.merchantId
      }
    } catch (_) {}

    const data = await getPinHash(merchantId, user.email, user.id)
    return res.json({
      ok: true,
      pin_hash: data?.app_pin_hash || null,
      pin_set: !!data?.app_pin_hash,
      pin_reset_requested: data?.pin_reset_requested || false,
    })
  } catch (err) {
    console.error('[pin] /sync error:', err.message)
    return res.status(500).json({ error: err.message })
  }
})
