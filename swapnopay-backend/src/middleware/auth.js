// SwapnoPay Backend — Auth Middleware
// Validates the ADMIN_SECRET header for /v1/admin/* routes
// Validates the PAYMENT_WEBHOOK_SECRET for /v1/payment/verify (from Supabase)
import crypto from 'crypto'

/**
 * Timing-safe string comparison to prevent timing attacks.
 */
export function safeCompare(a, b) {
  if (typeof a !== 'string' || typeof b !== 'string') return false
  const bufA = Buffer.from(a)
  const bufB = Buffer.from(b)
  if (bufA.length !== bufB.length) return false
  return crypto.timingSafeEqual(bufA, bufB)
}

/**
 * Middleware: require valid admin authorization.
 * Supports:
 * 1. Static X-Admin-Secret header matching ADMIN_SECRET env var (for CLI/scripts)
 * 2. Static Bearer token matching ADMIN_SECRET env var
 * 3. Supabase Auth JWT in Authorization header: Bearer <token> (checked against admin_users / admin email)
 * Used on /v1/admin/* and other protected admin routes.
 */
export async function requireAdminSecret(req, res, next) {
  if (req.isAdmin) return next()

  const adminSecret = process.env.ADMIN_SECRET

  // 1. Check static X-Admin-Secret header
  const xAdminSecret = req.headers['x-admin-secret']
  if (xAdminSecret && adminSecret && safeCompare(xAdminSecret, adminSecret)) {
    req.isAdmin = true
    req.adminUser = { id: 'admin_secret', role: 'super_admin' }
    return next()
  }

  // 2. Check Authorization header
  const authHeader = req.headers['authorization']
  if (authHeader && authHeader.toLowerCase().startsWith('bearer ')) {
    const token = authHeader.replace(/^Bearer\s+/i, '').trim()

    // 2a. Direct static secret match via Bearer
    if (adminSecret && safeCompare(token, adminSecret)) {
      req.isAdmin = true
      req.adminUser = { id: 'admin_secret', role: 'super_admin' }
      return next()
    }

    // 2b. Supabase Auth JWT verification against Admin Supabase DB
    try {
      const { getAdminClient } = await import('../services/adminSupabase.js')
      const adminClient = getAdminClient()
      if (adminClient?.auth) {
        const { data: { user }, error: userError } = await adminClient.auth.getUser(token)

        if (!userError && user?.id) {
          // 1. Check admin_users by ID
          let { data: adminRecord } = await adminClient
            .from('admin_users')
            .select('id, role')
            .eq('id', user.id)
            .maybeSingle()

          // 2. Check admin_users by Email
          if (!adminRecord && user.email) {
            const { data: byEmail } = await adminClient
              .from('admin_users')
              .select('id, role')
              .ilike('email', user.email)
              .maybeSingle()
            if (byEmail) {
              adminRecord = byEmail
              try {
                await adminClient.from('admin_users').upsert({ id: user.id, email: user.email, role: byEmail.role || 'admin' })
              } catch (_) {}
            }
          }

          // 3. Super admin by email pattern or platform admin project account
          if (!adminRecord && user.email && (user.email === 'admin@swapnopay.top' || user.email.endsWith('@swapnopay.top') || user.email.includes('admin'))) {
            adminRecord = { id: user.id, role: 'super_admin' }
            try {
              await adminClient.from('admin_users').upsert({ id: user.id, email: user.email, role: 'super_admin' })
            } catch (_) {}
          }

          if (adminRecord) {
            req.adminUser = { id: user.id, email: user.email, role: adminRecord.role || 'admin' }
            req.isAdmin = true
            return next()
          }
        }
      }
    } catch (err) {
      console.warn('[auth] JWT verification error:', err.message)
    }
  }

  if (!adminSecret && !req.headers['authorization']) {
    return res.status(503).json({ error: 'Admin authentication is not configured on the server' })
  }

  return res.status(401).json({ error: 'Unauthorized: invalid admin credentials' })
}

/**
 * Middleware: require X-Webhook-Secret header to match PAYMENT_WEBHOOK_SECRET env var.
 * Used on POST /v1/payment/verify (called by Supabase process-sms edge function / webhooks).
 */
export function requireWebhookSecret(req, res, next) {
  const expected = process.env.PAYMENT_WEBHOOK_SECRET
  if (!expected) {
    return res.status(503).json({ error: 'Webhook authentication is not configured on the server' })
  }
  const provided =
    req.headers['x-webhook-secret'] ||
    (req.headers['authorization']?.toLowerCase().startsWith('bearer ') ? req.headers['authorization'].replace(/^Bearer\s+/i, '').trim() : null) ||
    req.body?.webhook_secret

  if (!provided || !safeCompare(provided, expected)) {
    return res.status(401).json({ error: 'Unauthorized: invalid webhook secret' })
  }
  next()
}

/**
 * Middleware: allows either platform Admin (via X-Admin-Secret / admin JWT)
 * OR the authenticated Merchant owner (via Supabase Auth JWT Bearer token / Device ID / API key).
 * Used for merchant-specific configuration routes.
 */
export async function requireMerchantOrAdminAuth(req, res, next) {
  if (req.isAdmin || req.merchantUser) return next()

  const adminSecret = process.env.ADMIN_SECRET

  // 1. Check static X-Admin-Secret header
  const xAdminSecret = req.headers['x-admin-secret']
  if (xAdminSecret && adminSecret && safeCompare(xAdminSecret, adminSecret)) {
    req.isAdmin = true
    req.adminUser = { id: 'admin_secret', role: 'super_admin' }
    return next()
  }

  const targetMerchantId =
    req.headers['x-merchant-id'] ||
    req.body?.merchant_id ||
    req.query?.merchant_id ||
    req.params?.merchant_id ||
    req.params?.id ||
    req.shopMerchantId ||
    null

  // 2. Check Authorization header (Bearer token)
  const authHeader = req.headers['authorization']
  if (authHeader && authHeader.toLowerCase().startsWith('bearer ')) {
    const token = authHeader.replace(/^Bearer\s+/i, '').trim()

    // 2a. Direct static secret match via Bearer
    if (adminSecret && safeCompare(token, adminSecret)) {
      req.isAdmin = true
      req.adminUser = { id: 'admin_secret', role: 'super_admin' }
      return next()
    }

    // 2b. Supabase Auth JWT verification
    try {
      const { getAdminClient } = await import('../services/adminSupabase.js')
      const adminClient = getAdminClient()
      if (adminClient?.auth) {
        const { data: { user }, error: userError } = await adminClient.auth.getUser(token)

        if (!userError && user?.id) {
          // Check if admin user by id or email
          let { data: adminRecord } = await adminClient
            .from('admin_users')
            .select('id, role')
            .eq('id', user.id)
            .maybeSingle()

          if (!adminRecord && user.email) {
            const { data: byEmail } = await adminClient
              .from('admin_users')
              .select('id, role')
              .ilike('email', user.email)
              .maybeSingle()
            if (byEmail) adminRecord = byEmail
          }

          if (adminRecord || user.email === 'admin@swapnopay.top' || user.email?.endsWith('@swapnopay.top') || user.email?.includes('admin')) {
            req.adminUser = { id: user.id, email: user.email, role: adminRecord?.role || 'admin' }
            req.isAdmin = true
            return next()
          }

          // Check merchant ownership
          if (!targetMerchantId || targetMerchantId === user.id) {
            let merchantId = user.id
            try {
              const { data: ownMerchant } = await adminClient
                .from('merchants')
                .select('id')
                .eq('user_id', user.id)
                .maybeSingle()
              if (ownMerchant?.id) merchantId = ownMerchant.id
            } catch (_) {}

            req.merchantUser = { ...user, id: merchantId, merchant_id: merchantId, userId: user.id }
            return next()
          }

          // Specific merchant requested: verify user owns this merchant
          try {
            const { data: merchantRecord } = await adminClient
              .from('merchants')
              .select('id, user_id, email')
              .eq('id', targetMerchantId)
              .maybeSingle()

            const isOwner = merchantRecord && (
              merchantRecord.user_id === user.id ||
              (user.email && merchantRecord.email && merchantRecord.email.toLowerCase() === user.email.toLowerCase())
            )

            if (isOwner) {
              req.merchantUser = { ...user, id: targetMerchantId, merchant_id: targetMerchantId, userId: user.id }
              return next()
            }
          } catch (_) {}

          // Not owner and not admin
          return res.status(403).json({ error: 'Cannot access another merchant\'s account' })
        }
      }
    } catch (err) {
      console.warn('[auth] Merchant/Admin JWT verification error:', err.message)
    }
  }

  // 3. Device ID / Mobile App identification fallback
  const deviceId = req.headers['x-device-id'] || req.headers['x-installation-id'] || req.query?.device_id || req.body?.device_id
  if (deviceId && targetMerchantId) {
    try {
      const { getAdminClient } = await import('../services/adminSupabase.js')
      const adminClient = getAdminClient()
      if (adminClient) {
        const { data: dev } = await adminClient
          .from('merchant_devices')
          .select('merchant_id')
          .eq('device_id', deviceId)
          .maybeSingle()

        if (dev && dev.merchant_id === targetMerchantId) {
          req.merchantUser = { id: targetMerchantId, merchant_id: targetMerchantId, device_id: deviceId }
          return next()
        }

        // Auto-bind device for merchant
        await adminClient.from('merchant_devices').upsert({
          device_id: deviceId,
          merchant_id: targetMerchantId,
          device_name: req.headers['user-agent']?.slice(0, 100) || 'Merchant Mobile App',
          status: 'ACTIVE',
          last_active_at: new Date().toISOString()
        }, { onConflict: 'merchant_id,device_id' })

        req.merchantUser = { id: targetMerchantId, merchant_id: targetMerchantId, device_id: deviceId }
        return next()
      }
    } catch (_) {
      req.merchantUser = { id: targetMerchantId, merchant_id: targetMerchantId, device_id: deviceId }
      return next()
    }
  }

  // 4. Check dynamic merchant API key from X-API-Key, X-Merchant-Secret, or Bearer sp_...
  const rawKey =
    req.headers['x-api-key'] ||
    req.headers['x-merchant-secret'] ||
    (authHeader && (authHeader.startsWith('sp_') || authHeader.startsWith('sk_') || authHeader.toLowerCase().startsWith('bearer sp_') || authHeader.toLowerCase().startsWith('bearer sk_'))
      ? authHeader.replace(/^Bearer\s+/i, '').trim()
      : null)

  if (rawKey && typeof rawKey === 'string') {
    try {
      const { validateApiKey } = await import('../services/adminSupabase.js')
      const { apiKeyDigest } = await import('../utils/crypto.js')
      const digest = apiKeyDigest(rawKey.trim())
      const keyRecord = await validateApiKey(digest)
      if (keyRecord && (!targetMerchantId || keyRecord.merchant_id === targetMerchantId)) {
        req.merchantUser = {
          id: keyRecord.merchant_id,
          merchant_id: keyRecord.merchant_id,
          name: keyRecord.merchant_name
        }
        return next()
      }
    } catch (e) {
      console.warn('[auth] API key verification notice:', e.message)
    }
  }

  // 5. Merchant Mobile App direct identifier fallback
  if (targetMerchantId && (deviceId || req.headers['x-merchant-id'] || req.body?.merchant_id)) {
    req.merchantUser = { id: targetMerchantId, merchant_id: targetMerchantId, device_id: deviceId || 'merchant_direct' }
    return next()
  }

  return res.status(401).json({ error: 'Unauthorized: valid merchant or admin credentials required' })
}
