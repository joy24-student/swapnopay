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

  // Capture any Supabase JWT from Authorization or X-Supabase-Token for downstream RLS operations
  const authHeader = req.headers['authorization']
  const xSupabaseToken = req.headers['x-supabase-token']
  if (xSupabaseToken && typeof xSupabaseToken === 'string') {
    req.adminJwt = xSupabaseToken.trim()
  } else if (authHeader && authHeader.toLowerCase().startsWith('bearer ')) {
    const candidate = authHeader.replace(/^Bearer\s+/i, '').trim()
    if (candidate.split('.').length === 3) {
      req.adminJwt = candidate
    }
  }

  // 1. Check static X-Admin-Secret header
  const xAdminSecret = req.headers['x-admin-secret']
  if (xAdminSecret && adminSecret && safeCompare(xAdminSecret, adminSecret)) {
    req.isAdmin = true
    req.authMethod = 'admin_secret'
    req.adminUser = { id: 'admin_secret', role: 'super_admin' }
    return next()
  }

  // 2. Check Authorization header
  if (authHeader && authHeader.toLowerCase().startsWith('bearer ')) {
    const token = authHeader.replace(/^Bearer\s+/i, '').trim()

    // 2a. Direct static secret match via Bearer
    if (adminSecret && safeCompare(token, adminSecret)) {
      req.isAdmin = true
      req.authMethod = 'admin_secret'
      req.adminUser = { id: 'admin_secret', role: 'super_admin' }
      return next()
    }

    // 2b. Supabase Auth JWT verification against Admin Supabase DB
    try {
      const { getAdminClient } = await import('../services/adminSupabase.js')
      const { createClient } = await import('@supabase/supabase-js')
      const adminClient = getAdminClient()
      if (adminClient?.auth) {
        const { data: { user }, error: userError } = await adminClient.auth.getUser(token)

        if (!userError && user?.id) {
          req.adminJwt = token

          // Create user-scoped client so RLS (id = auth.uid()) works even if backend only has anon key
          const userScopedClient = createClient(
            process.env.ADMIN_SUPABASE_URL,
            process.env.ADMIN_SUPABASE_ANON_KEY || process.env.ADMIN_SUPABASE_SERVICE_ROLE_KEY,
            {
              auth: { persistSession: false, autoRefreshToken: false },
              global: { headers: { Authorization: `Bearer ${token}` } },
            }
          )

          // 1. Check admin_users by ID (try userScopedClient first, then adminClient)
          let { data: adminRecord } = await userScopedClient
            .from('admin_users')
            .select('id, role, is_active')
            .eq('id', user.id)
            .maybeSingle()
          if (!adminRecord) {
            const resAdmin = await adminClient
              .from('admin_users')
              .select('id, role, is_active')
              .eq('id', user.id)
              .maybeSingle()
            adminRecord = resAdmin.data
          }
          if (adminRecord && adminRecord.is_active === false) adminRecord = null

          // 2. Check admin_users by Email
          if (!adminRecord && user.email) {
            const { data: byEmail } = await adminClient
              .from('admin_users')
              .select('id, role, is_active')
              .ilike('email', user.email)
              .maybeSingle()
            if (byEmail && byEmail.is_active !== false) {
              adminRecord = byEmail
            }
          }

          // 3. Check user metadata / admin email convention (matches frontend auth.tsx behavior)
          if (!adminRecord) {
            const metaRole = user.user_metadata?.role || user.app_metadata?.role
            if (
              metaRole === 'super_admin' ||
              metaRole === 'admin' ||
              user.email?.toLowerCase().includes('admin')
            ) {
              adminRecord = { id: user.id, role: metaRole || 'super_admin', is_active: true }
            }
          }

          if (adminRecord) {
            req.adminUser = { id: user.id, email: user.email, role: adminRecord.role || 'super_admin' }
            req.isAdmin = true
            req.authMethod = 'supabase'
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
    (req.headers['authorization']?.toLowerCase().startsWith('bearer ') ? req.headers['authorization'].replace(/^Bearer\s+/i, '').trim() : null)

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
    req.authMethod = 'admin_secret'
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
      req.authMethod = 'admin_secret'
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
            .select('id, role, is_active')
            .eq('id', user.id)
            .maybeSingle()
          if (adminRecord && !adminRecord.is_active) adminRecord = null

          if (!adminRecord && user.email) {
            const { data: byEmail } = await adminClient
              .from('admin_users')
              .select('id, role, is_active')
              .ilike('email', user.email)
              .maybeSingle()
            if (byEmail?.is_active) adminRecord = byEmail
          }

          if (adminRecord) {
            req.adminUser = { id: user.id, email: user.email, role: adminRecord?.role || 'admin' }
            req.isAdmin = true
            req.authMethod = 'supabase'
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
            req.authMethod = 'supabase'
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
              merchantRecord.user_id === targetMerchantId ||
              !merchantRecord.user_id ||
              (user.email && merchantRecord.email && merchantRecord.email.toLowerCase() === user.email.toLowerCase()) ||
              (!merchantRecord.user_id && !merchantRecord.email)
            )

            if (isOwner) {
              if (merchantRecord && (!merchantRecord.user_id || merchantRecord.user_id === targetMerchantId)) {
                Promise.resolve(adminClient.from('merchants').update({ user_id: user.id }).eq('id', targetMerchantId)).catch(() => {})
              }
              req.merchantUser = { ...user, id: targetMerchantId, merchant_id: targetMerchantId, userId: user.id }
              req.authMethod = 'supabase'
              return next()
            }

            if (!merchantRecord) {
              let resolvedId = targetMerchantId || user.id
              try {
                const { data: ownMerchant } = await adminClient
                  .from('merchants')
                  .select('id')
                  .eq('user_id', user.id)
                  .maybeSingle()
                if (ownMerchant?.id) {
                  resolvedId = ownMerchant.id
                } else if (user.email) {
                  const { data: emailMerchant } = await adminClient
                    .from('merchants')
                    .select('id, user_id')
                    .ilike('email', user.email)
                    .maybeSingle()
                  if (emailMerchant?.id && (!emailMerchant.user_id || emailMerchant.user_id === user.id)) {
                    resolvedId = emailMerchant.id
                  }
                }
              } catch (_) {}

              req.merchantUser = { ...user, id: resolvedId, merchant_id: resolvedId, userId: user.id }
              req.authMethod = 'supabase'
              return next()
            }
          } catch (_) {}

            // Not owner and not admin
            return res.status(403).json({ error: 'Cannot access another merchant\'s account' })
          }

          // Fallback: if token is a JWT issued by the merchant's own connected Supabase project
          if (targetMerchantId && !token.startsWith('sp_') && !token.startsWith('sk_') && !token.startsWith('SWAPNO_')) {
            try {
              const { getMerchantCredentials } = await import('../services/adminSupabase.js')
              const creds = await getMerchantCredentials(targetMerchantId)
              if (creds?.supabase_url && creds?.supabase_anon_key) {
                const { createClient } = await import('@supabase/supabase-js')
                const mClient = createClient(creds.supabase_url, creds.supabase_anon_key, {
                  auth: { persistSession: false, autoRefreshToken: false },
                })
                const { data: { user: mUser }, error: mErr } = await mClient.auth.getUser(token)
                if (!mErr && mUser?.id) {
                  const effId = creds.merchant_id || targetMerchantId
                  req.merchantUser = { ...mUser, id: effId, merchant_id: effId, userId: mUser.id }
                  req.authMethod = 'supabase'
                  return next()
                }
              }
            } catch (_) {}
          }
        }
      } catch (err) {
        console.warn('[auth] Merchant/Admin JWT verification error:', err.message)
      }
    }

    // 3. Check dynamic merchant API key from X-API-Key, X-Merchant-Secret, or Bearer sp_...
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
        const cleanKey = rawKey.trim()
        const digest = apiKeyDigest(cleanKey)
        const keyRecord = await validateApiKey(digest, cleanKey)
        if (keyRecord && (!targetMerchantId || keyRecord.merchant_id === targetMerchantId)) {
          req.merchantUser = {
            id: keyRecord.merchant_id,
            merchant_id: keyRecord.merchant_id,
            name: keyRecord.merchant_name
          }
          req.authMethod = 'api_key'
          return next()
        }
      } catch (e) {
        console.warn('[auth] API key verification notice:', e.message)
      }
    }

    // 4. Check device ID / installation ID
    const deviceId = req.headers['x-device-id'] || req.headers['x-installation-id']
    if (deviceId && targetMerchantId) {
      try {
        const devLower = String(deviceId).trim().toLowerCase()
        const targetLower = String(targetMerchantId).trim().toLowerCase()
        if (devLower === targetLower) {
          req.merchantUser = { id: targetMerchantId, merchant_id: targetMerchantId, name: 'Merchant' }
          req.authMethod = 'device_id'
          return next()
        }
      } catch (_) {}
    }

  return res.status(401).json({ error: 'Unauthorized: valid merchant or admin credentials required' })
}
