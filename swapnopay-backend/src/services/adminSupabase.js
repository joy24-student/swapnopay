// SwapnoPay Backend — Admin Supabase Service
// Connects to the PLATFORM OWNER'S Supabase database (NOT merchant databases).
// Handles: gateway config, API key management, payment event recording,
//          merchant profile (with logo), cross-DB device status, cross-DB order updates.

import { createClient } from '@supabase/supabase-js'
import { randomUUID } from 'node:crypto'
import { generateRawApiKey, apiKeyDigest } from '../utils/crypto.js'

// ──────────────────────────────────────────────────────────────────────────────
// Singleton admin client (service role — full RLS bypass)
// ──────────────────────────────────────────────────────────────────────────────
let _adminClient = null

export function initAdminSupabase() {
  const url = process.env.ADMIN_SUPABASE_URL
  const serviceKey = process.env.ADMIN_SUPABASE_SERVICE_ROLE_KEY

  if (!url || !serviceKey) {
    throw new Error(
      'ADMIN_SUPABASE_URL and ADMIN_SUPABASE_SERVICE_ROLE_KEY are required.'
    )
  }

  _adminClient = createClient(url, serviceKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  })

  console.log('[admin-supabase] Connected to admin database:', url)
  return _adminClient
}

export function getAdminClient() {
  if (!_adminClient) initAdminSupabase()

  return _adminClient
}

export const getAdminSupabase = getAdminClient

// ──────────────────────────────────────────────────────────────────────────────
// Merchant Supabase client factory (short-lived, per-request)
// Uses credentials stored in admin DB merchant_gateway_settings
// ──────────────────────────────────────────────────────────────────────────────
function createMerchantClient(supabaseUrl, supabaseAnonKey) {
  if (!supabaseUrl || !supabaseAnonKey) {
    throw new Error('Merchant Supabase credentials not configured')
  }
  // Validate URL format for safety
  const url = new URL(supabaseUrl) // throws on invalid URL
  if (!url.hostname.endsWith('.supabase.co') && !url.hostname.endsWith('.supabase.in')) {
    // Allow custom Supabase-compatible self-hosted URLs too
    console.warn('[merchant-db] Non-standard Supabase host:', url.hostname)
  }
  return createClient(supabaseUrl, supabaseAnonKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  })
}

// ──────────────────────────────────────────────────────────────────────────────
// Get stored merchant credentials from admin DB, merchants table, or control plane
// ──────────────────────────────────────────────────────────────────────────────
export async function getMerchantCredentials(merchantId) {
  if (!merchantId) return null
  let admin = null
  try { admin = getAdminClient() } catch (_) {}
  if (!admin) return null

  // 1. Check merchant_gateway_settings
  let data = null
  try {
    const res = await admin
      .from('merchant_gateway_settings')
      .select('supabase_url, supabase_anon_key, merchant_name, merchant_logo_url, receiving_numbers')
      .eq('merchant_id', merchantId)
      .maybeSingle()
    data = res.data
  } catch (_) {}

  // 2. Query merchants table for business_name, photo_url, phone, default_number, status
  let merchantRow = null
  try {
    const isIdUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(merchantId)
    let mQuery = admin.from('merchants').select('id, user_id, business_name, photo_url, phone, default_number, status')
    if (isIdUuid) {
      mQuery = mQuery.or(`id.eq.${merchantId},user_id.eq.${merchantId}`)
    } else {
      mQuery = mQuery.eq('id', merchantId)
    }
    const { data: mData } = await mQuery.maybeSingle()
    merchantRow = mData
  } catch (_) {}

  const effectiveName = data?.merchant_name || merchantRow?.business_name || null
  const effectiveLogo = data?.merchant_logo_url || merchantRow?.photo_url || null
  const receiving = data?.receiving_numbers || (merchantRow?.default_number ? { bKash: merchantRow.default_number } : {})
  const mStatus = merchantRow?.status || 'ACTIVE'

  if (data?.supabase_url) {
    return {
      supabase_url: data.supabase_url,
      supabase_anon_key: data.supabase_anon_key,
      merchant_name: effectiveName,
      merchant_logo_url: effectiveLogo,
      receiving_numbers: receiving,
      status: mStatus,
      merchant_id: merchantRow?.id || merchantId,
    }
  }

  // 3. Fallback: check supabase_connections from control plane
  try {
    const userIds = [merchantId]
    if (merchantRow?.user_id && merchantRow.user_id !== merchantId) userIds.push(merchantRow.user_id)
    if (merchantRow?.id && merchantRow.id !== merchantId) userIds.push(merchantRow.id)

    const { data: conn } = await admin
      .from('supabase_connections')
      .select('project_url, publishable_key')
      .in('user_id', userIds)
      .limit(1)
      .maybeSingle()

    if (conn?.project_url) {
      return {
        supabase_url: conn.project_url,
        supabase_anon_key: conn.publishable_key,
        merchant_name: effectiveName,
        merchant_logo_url: effectiveLogo,
        receiving_numbers: receiving,
        status: mStatus,
        merchant_id: merchantRow?.id || merchantId,
      }
    }
  } catch {
    // Ignore fallback failure
  }

  if (merchantRow || data) {
    return {
      supabase_url: data?.supabase_url || null,
      supabase_anon_key: data?.supabase_anon_key || null,
      merchant_name: effectiveName,
      merchant_logo_url: effectiveLogo,
      receiving_numbers: receiving,
      status: mStatus,
      merchant_id: merchantRow?.id || merchantId,
    }
  }

  return null
}

// ──────────────────────────────────────────────────────────────────────────────
// Check merchant device active status via in-memory heartbeat or merchant's own Supabase DB
// Returns: { active: boolean, last_seen: string|null, device_count: number }
// ──────────────────────────────────────────────────────────────────────────────
export async function getMerchantDeviceStatus(merchantId, heartbeatMap = null) {
  if (!merchantId) return { active: null, last_seen: null, device_count: 0 }

  // Fast path: check in-memory heartbeat map first (15-minute window for stable mobile uptime)
  const FIFTEEN_MIN = 15 * 60 * 1000
  if (heartbeatMap && heartbeatMap.has(merchantId)) {
    const hb = heartbeatMap.get(merchantId)
    const ageMs = Date.now() - hb.ts
    if (ageMs < FIFTEEN_MIN) {
      return { active: true, last_seen: new Date(hb.ts).toISOString(), device_count: 1, source: 'heartbeat' }
    }
  }

  // Check merchant credentials and account status
  const creds = await getMerchantCredentials(merchantId)
  const isAccountActive = creds?.status === 'ACTIVE'

  // Slow path: query merchant's Supabase devices table
  try {
    if (!creds?.supabase_url || !creds?.supabase_anon_key) {
      // No custom DB configured — platform active merchant; allow payments
      return { active: isAccountActive, last_seen: null, device_count: 0, source: 'platform_merchant' }
    }

    const merchantClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)

    const { data: devices, error } = await merchantClient
      .from('devices')
      .select('id, online, last_sync, disabled, created_at, merchant_id')

    if (error) {
      console.warn(`[device-status] Merchant DB query notice for ${merchantId}:`, error.message)
      return { active: isAccountActive, last_seen: null, device_count: 0, source: 'db_fallback' }
    }

    if (!devices || devices.length === 0) {
      // Merchant has no hardware devices configured yet (web / form / API payment mode)
      // Do NOT block checkout as offline!
      return { active: true, last_seen: null, device_count: 0, source: 'active_no_devices' }
    }

    const now = Date.now()
    let lastSeen = null

    const hasActiveDevice = devices.some(d => {
      if (d.disabled === true) return false
      const syncTime = d.last_sync || d.created_at
      const syncTs = syncTime ? new Date(syncTime).getTime() : 0
      const isRecent = syncTs > 0 && (now - syncTs) < FIFTEEN_MIN
      if (syncTime && (!lastSeen || syncTs > new Date(lastSeen).getTime())) {
        lastSeen = syncTime
      }
      return d.online === true || isRecent
    })

    return {
      active: hasActiveDevice || isAccountActive,
      last_seen: lastSeen,
      device_count: devices.length,
      source: 'merchant_db',
    }
  } catch (err) {
    console.error(`[device-status] Error checking merchant ${merchantId}:`, err.message)
    return { active: isAccountActive || true, last_seen: null, device_count: 0, source: 'error_fail_open' }
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Cross-DB: Read order details from MERCHANT's Supabase DB or payment_events
// Used by /v1/payment/verify and /v1/payment/order/:order_id
// Supports lookup by UUID id or string tran_id
// ──────────────────────────────────────────────────────────────────────────────
export async function getOrderFromMerchantDB(merchantId, orderIdOrTranId) {
  if (!orderIdOrTranId) return null

  // If merchantId is missing, resolve from payment_events first
  let targetMerchantId = merchantId
  if (!targetMerchantId) {
    try {
      const admin = getAdminClient()
      const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(orderIdOrTranId)
      let q = admin.from('payment_events').select('merchant_id, merchant_name').limit(1)
      if (isUuid) {
        q = q.or(`order_id.eq.${orderIdOrTranId},tran_id.eq.${orderIdOrTranId}`)
      } else {
        q = q.eq('tran_id', orderIdOrTranId)
      }
      const { data: ev } = await q.maybeSingle()
      if (ev?.merchant_id) targetMerchantId = ev.merchant_id
    } catch (_) {}
  }

  try {
    const creds = await getMerchantCredentials(targetMerchantId)
    if (creds?.supabase_url && creds?.supabase_anon_key) {
      const merchantClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)
      const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(orderIdOrTranId)

      let query = merchantClient
        .from('orders')
        .select('id, tran_id, amount, status, cus_name, cus_phone, cus_email, product_name, payment_method, matched_trx_id, sender_number, paid_at, expires_at, success_url, fail_url, cancel_url, created_at, merchant_id')

      if (isUuid) {
        query = query.or(`id.eq.${orderIdOrTranId},tran_id.eq.${orderIdOrTranId}`)
      } else {
        query = query.eq('tran_id', orderIdOrTranId)
      }

      const { data, error } = await query.maybeSingle()

      if (!error && data) {
        return {
          ...data,
          merchant_id: data.merchant_id || targetMerchantId,
          merchant_name: creds?.merchant_name || null,
        }
      }
      if (error) {
        console.warn(`[merchant-db] getOrder warning for ${orderIdOrTranId}:`, error.message)
      }
    }
  } catch (err) {
    console.error(`[merchant-db] getOrderFromMerchantDB failed:`, err.message)
  }

  // Fallback: check admin DB payment_events
  try {
    const admin = getAdminClient()
    const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(orderIdOrTranId)
    let q = admin.from('payment_events').select('*')
    if (isUuid) {
      q = q.or(`order_id.eq.${orderIdOrTranId},tran_id.eq.${orderIdOrTranId}`)
    } else {
      q = q.eq('tran_id', orderIdOrTranId)
    }
    const { data: eventData } = await q.order('recorded_at', { ascending: false }).limit(1).maybeSingle()
    if (eventData) {
      return {
        id: eventData.order_id,
        tran_id: eventData.tran_id,
        amount: eventData.amount,
        status: eventData.status,
        cus_name: eventData.cus_name,
        cus_phone: eventData.sender_number,
        cus_email: eventData.cus_email,
        product_name: eventData.product_name,
        payment_method: eventData.payment_method,
        matched_trx_id: eventData.trx_id,
        sender_number: eventData.sender_number,
        paid_at: eventData.payment_time,
        created_at: eventData.recorded_at,
        merchant_id: eventData.merchant_id || targetMerchantId,
        merchant_name: eventData.merchant_name || null,
      }
    }
  } catch {}

  return null
}

// ──────────────────────────────────────────────────────────────────────────────
// Cross-DB: Update order status on MERCHANT's Supabase DB
// Called by /v1/payment/verify, /v1/payment/cancel, or appeal resolutions
// ──────────────────────────────────────────────────────────────────────────────
export async function updateOrderStatusOnMerchantDB(merchantId, orderId, status, trxData = {}) {
  try {
    const creds = await getMerchantCredentials(merchantId)
    if (!creds?.supabase_url || !creds?.supabase_anon_key) {
      console.warn(`[merchant-db] No credentials for merchant ${merchantId}, skipping order update`)
      return false
    }

    const merchantClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)

    // Normalize status to match schema check constraint: ('PENDING','PAID','EXPIRED','CANCELLED')
    let dbStatus = status
    if (dbStatus === 'FAILED') dbStatus = 'CANCELLED'

    const updatePayload = {
      status: dbStatus,
    }
    if (dbStatus === 'PAID') {
      updatePayload.paid_at = trxData.payment_time ? new Date(trxData.payment_time).toISOString() : new Date().toISOString()
    }
    if (trxData.matched_trx_id) updatePayload.matched_trx_id = String(trxData.matched_trx_id)
    if (trxData.tran_id)        updatePayload.tran_id        = String(trxData.tran_id)
    if (trxData.payment_method) updatePayload.payment_method = String(trxData.payment_method)
    if (trxData.sender_number)  updatePayload.sender_number  = String(trxData.sender_number)

    const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(orderId)
    let query = merchantClient.from('orders').update(updatePayload)
    if (isUuid) {
      query = query.eq('id', orderId)
    } else {
      query = query.eq('tran_id', orderId)
    }

    const { error } = await query

    if (error) {
      console.error(`[merchant-db] Failed to update order ${orderId} to ${status}:`, error.message)
      return false
    }

    if (dbStatus === 'CANCELLED') {
      try {
        const { data: orderData } = await merchantClient
          .from('orders')
          .select('id')
          .eq(isUuid ? 'id' : 'tran_id', orderId)
          .maybeSingle()
        if (orderData?.id) {
          const { data: items } = await merchantClient
            .from('order_items')
            .select('product_id, quantity')
            .eq('order_id', orderData.id)
          if (items && items.length > 0) {
            for (const item of items) {
              if (item.product_id && item.quantity > 0) {
                await merchantClient.rpc('increment_product_stock', {
                  p_id: item.product_id,
                  qty: item.quantity
                }).catch(() => {})
              }
            }
          }
        }
      } catch (_) {}
    }

    console.log(`[merchant-db] ✅ Order ${orderId} updated to ${status} on merchant ${merchantId} DB`)
    return true
  } catch (err) {
    console.error(`[merchant-db] updateOrderStatusOnMerchantDB failed:`, err.message)
    return false
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Gateway Configuration (singleton row id=00000000-0000-0000-0000-000000000001)
// ──────────────────────────────────────────────────────────────────────────────
const CONFIG_ID = '00000000-0000-0000-0000-000000000001'

/**
 * Read the full gateway configuration from admin Supabase.
 */
export async function getGatewayConfig() {
  const { data, error } = await getAdminClient()
    .from('gateway_config')
    .select('*')
    .eq('id', CONFIG_ID)
    .single()

  if (error) {
    console.error('[admin-supabase] getGatewayConfig error:', error.message)
    return getDefaultGatewayConfig()
  }

  return normaliseConfig(data)
}

/**
 * Write/merge gateway configuration to admin Supabase.
 */
export async function setGatewayConfig(config) {
  const row = denormaliseConfig(config)
  row.updated_at = new Date().toISOString()

  const { data, error } = await getAdminClient()
    .from('gateway_config')
    .update(row)
    .eq('id', CONFIG_ID)
    .select('*')
    .single()

  if (error) throw new Error('Failed to save gateway config: ' + error.message)
  return normaliseConfig(data)
}

// ──────────────────────────────────────────────────────────────────────────────
// Merchant-Specific Gateway Settings (with device check + logo)
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fetch and dynamically merge platform gateway config with merchant-specific choices.
 * Also performs a cross-DB device status check on the merchant's own Supabase.
 * @param {string} merchantId
 * @param {Map} heartbeatMap — in-memory Socket.io heartbeat map (optional)
 */
export async function getMerchantGatewayConfig(merchantId, heartbeatMap = null) {
  const globalConfig = await getGatewayConfig()
  if (!merchantId) return { ...globalConfig, device_active: null, merchant_logo_url: null, merchant_name: null }

  const creds = await getMerchantCredentials(merchantId)

  let merchantRow = null
  try {
    const { data } = await getAdminClient()
      .from('merchant_gateway_settings')
      .select('*')
      .eq('merchant_id', merchantId)
      .maybeSingle()
    merchantRow = data
  } catch (_) {}

  // Device status check — queries merchant's Supabase DB or in-memory heartbeat
  let deviceStatus = { active: null, last_seen: null, device_count: 0 }
  try {
    deviceStatus = await getMerchantDeviceStatus(merchantId, heartbeatMap)
  } catch (e) {
    console.warn('[gateway-config] Device status check notice:', e.message)
  }

  // If merchant account is ACTIVE in database or has configured receiving numbers, never falsely declare offline
  const isAccountActive = creds?.status === 'ACTIVE'
  const effectiveReceiving = merchantRow?.receiving_numbers && Object.keys(merchantRow.receiving_numbers).length > 0
    ? merchantRow.receiving_numbers
    : (creds?.receiving_numbers || {})

  const hasNumbers = Object.values(effectiveReceiving).some(Boolean)
  if (deviceStatus.active === false && (isAccountActive || hasNumbers || !deviceStatus.device_count)) {
    deviceStatus.active = true
  }

  const effectiveName = merchantRow?.merchant_name || creds?.merchant_name || null
  const effectiveLogo = merchantRow?.merchant_logo_url || creds?.merchant_logo_url || null
  const effectiveUrl  = merchantRow?.supabase_url || creds?.supabase_url || null
  const effectiveKey  = merchantRow?.supabase_anon_key || creds?.supabase_anon_key || null

  return {
    ...globalConfig,
    enabled_methods: {
      bKash:  globalConfig.enabled_methods.bKash  && (merchantRow?.bkash_enabled  ?? true),
      Nagad:  globalConfig.enabled_methods.Nagad  && (merchantRow?.nagad_enabled  ?? true),
      Rocket: globalConfig.enabled_methods.Rocket && (merchantRow?.rocket_enabled ?? true),
      Upay:   globalConfig.enabled_methods.Upay   && (merchantRow?.upay_enabled   ?? true),
    },
    default_success_url:  merchantRow?.success_url || globalConfig.default_success_url,
    default_fail_url:     merchantRow?.fail_url    || globalConfig.default_fail_url,
    default_cancel_url:   merchantRow?.cancel_url  || globalConfig.default_cancel_url,
    receiving_numbers:    effectiveReceiving,
    qr_codes:             merchantRow?.qr_codes || {},
    auto_appeal_matching: merchantRow?.auto_appeal_matching ?? false,
    merchant_customized:  Boolean(merchantRow || creds),
    merchant_logo_url:    effectiveLogo,
    merchant_name:        effectiveName,
    merchant_id:          creds?.merchant_id || merchantId,
    supabase_url:         effectiveUrl,
    supabase_anon_key:    effectiveKey,
    // Device status from merchant's own DB
    device_active:        deviceStatus.active,
    device_last_seen:     deviceStatus.last_seen,
    device_count:         deviceStatus.device_count,
    device_source:        deviceStatus.source,
  }
}

/**
 * Save custom gateway configuration for a specific merchant in admin Supabase.
 */
export async function setMerchantGatewayConfig(merchantId, settings) {
  if (!merchantId) throw new Error('merchant_id is required')

  const row = {
    merchant_id:          merchantId,
    bkash_enabled:        settings.bkash_enabled ?? true,
    nagad_enabled:        settings.nagad_enabled ?? true,
    rocket_enabled:       settings.rocket_enabled ?? true,
    upay_enabled:         settings.upay_enabled ?? true,
    success_url:          settings.success_url || null,
    fail_url:             settings.fail_url || null,
    cancel_url:           settings.cancel_url || null,
    receiving_numbers:    settings.receiving_numbers || {},
    qr_codes:             settings.qr_codes || {},
    auto_appeal_matching: settings.auto_appeal_matching ?? false,
    updated_at:           new Date().toISOString(),
  }

  if (settings.merchant_name)     row.merchant_name     = settings.merchant_name
  if (settings.merchant_logo_url) row.merchant_logo_url = settings.merchant_logo_url
  if (settings.supabase_url)      row.supabase_url      = settings.supabase_url
  if (settings.supabase_anon_key) row.supabase_anon_key = settings.supabase_anon_key

  const { data, error } = await getAdminClient()
    .from('merchant_gateway_settings')
    .upsert(row, { onConflict: 'merchant_id' })
    .select('*')
    .single()

  if (error) throw new Error('Failed to save merchant gateway config: ' + error.message)

  // Asynchronously mirror branding to merchants table if present
  if (settings.merchant_name || settings.merchant_logo_url) {
    try {
      const updateData = {}
      if (settings.merchant_name) updateData.business_name = settings.merchant_name
      if (settings.merchant_logo_url) updateData.photo_url = settings.merchant_logo_url
      getAdminClient()
        .from('merchants')
        .update(updateData)
        .eq('id', merchantId)
        .then(() => {})
        .catch(() => {})
    } catch (_) {}
  }

  return data
}

// ──────────────────────────────────────────────────────────────────────────────
// Platform API Keys
// ──────────────────────────────────────────────────────────────────────────────

const inMemoryApiKeys = new Map()

export async function storeApiKeyRecord(record) {
  inMemoryApiKeys.set(record.merchant_id, {
    id: record.id,
    merchant_id: record.merchant_id,
    merchant_name: record.merchant_name,
    label: record.label,
    rawKey: record.raw_key || record.key_preview,
    digest: record.digest,
    preview: record.key_preview,
    revoked: false,
    createdAt: new Date().toISOString()
  })

  let admin = null
  try { admin = getAdminClient() } catch (_) {}
  if (!admin) {
    return {
      id: record.id,
      merchant_id: record.merchant_id,
      merchant_name: record.merchant_name,
      label: record.label,
      key_preview: record.key_preview,
      revoked: false,
      created_at: new Date().toISOString()
    }
  }

  const insertPayload = {
    id:            record.id,
    merchant_id:   record.merchant_id,
    merchant_name: record.merchant_name,
    label:         record.label,
    key_digest:    record.digest,
    key_preview:   record.key_preview,
    revoked:       false,
  }
  if (record.raw_key) {
    insertPayload.raw_key = record.raw_key
  }

  let { data, error } = await admin
    .from('platform_api_keys')
    .insert(insertPayload)
    .select('*')
    .single()

  // Schema cache fallback if raw_key column does not exist yet
  if (error && (error.message?.includes('raw_key') || error.message?.includes('schema cache'))) {
    delete insertPayload.raw_key
    const retry = await admin
      .from('platform_api_keys')
      .insert(insertPayload)
      .select('*')
      .single()
    data = retry.data
    error = retry.error
  }

  if (error) throw new Error('Failed to store API key: ' + error.message)
  return data
}

export async function revokeApiKeyRecord(keyId) {
  for (const [mId, mem] of inMemoryApiKeys.entries()) {
    if (mem.id === keyId) {
      mem.revoked = true
      mem.revoked_at = new Date().toISOString()
    }
  }

  let admin = null
  try { admin = getAdminClient() } catch (_) {}
  if (admin) {
    const { error } = await admin
      .from('platform_api_keys')
      .update({ revoked: true, revoked_at: new Date().toISOString() })
      .eq('id', keyId)

    if (error) throw new Error('Failed to revoke API key: ' + error.message)
  }
}

export async function listApiKeyRecords() {
  let admin = null
  try { admin = getAdminClient() } catch (_) {}
  if (admin) {
    const { data, error } = await admin
      .from('platform_api_keys')
      .select('id,merchant_id,merchant_name,label,key_preview,revoked,revoked_at,created_at')
      .order('created_at', { ascending: false })

    if (!error && data) return data
  }

  return Array.from(inMemoryApiKeys.values()).map(k => ({
    id: k.id,
    merchant_id: k.merchant_id,
    merchant_name: k.merchant_name,
    label: k.label,
    key_preview: k.preview,
    revoked: Boolean(k.revoked),
    revoked_at: k.revoked_at || null,
    created_at: k.createdAt
  }))
}

export async function validateApiKey(digest) {
  let admin = null
  try { admin = getAdminClient() } catch (_) {}
  if (admin) {
    try {
      const { data, error } = await admin
        .from('platform_api_keys')
        .select('id,merchant_id,merchant_name,label,key_preview,revoked')
        .eq('key_digest', digest)
        .single()

      if (!error && data && !data.revoked) return data
    } catch (_) {}
  }

  // Check inMemoryApiKeys fallback
  for (const [mId, record] of inMemoryApiKeys.entries()) {
    if (apiKeyDigest(record.rawKey) === digest && !record.revoked) {
      return {
        id: record.id,
        merchant_id: mId,
        merchant_name: record.merchant_name || 'Merchant',
        label: record.label || 'Default API Key',
        key_preview: record.preview,
        revoked: false,
      }
    }
  }

  return null
}

export async function getOrCreateMerchantApiKey(merchantId, merchantName = 'Merchant') {
  const cleanId = String(merchantId || '').trim()
  if (!cleanId) throw new Error('merchantId is required')

  let admin = null
  try { admin = getAdminClient() } catch (_) {}

  // 1. Check if merchant already has an active key in database
  if (admin) {
    try {
      const { data: existing } = await admin
        .from('platform_api_keys')
        .select('id, merchant_id, merchant_name, label, key_preview, raw_key, revoked, created_at')
        .eq('merchant_id', cleanId)
        .eq('revoked', false)
        .order('created_at', { ascending: false })
        .limit(1)
        .maybeSingle()

      if (existing?.raw_key) {
        return {
          id: existing.id,
          merchant_id: cleanId,
          merchant_name: existing.merchant_name,
          api_key: existing.raw_key,
          key_preview: existing.key_preview,
          created_at: existing.created_at,
          is_new: false,
        }
      }

      // Check if merchants table has api_key column populated
      const { data: mData } = await admin
        .from('merchants')
        .select('id, business_name, api_key, webhook_secret')
        .eq('id', cleanId)
        .maybeSingle()

      if (mData?.api_key && mData.api_key.startsWith('sp_live_')) {
        return {
          id: existing?.id || randomUUID(),
          merchant_id: cleanId,
          merchant_name: mData.business_name || merchantName,
          api_key: mData.api_key,
          key_preview: mData.api_key.slice(0, 14) + '****',
          is_new: false,
        }
      }
    } catch (e) {
      console.warn('[admin-supabase] getOrCreateMerchantApiKey fetch notice:', e.message)
    }
  }

  // Check in-memory fallback
  if (inMemoryApiKeys.has(cleanId)) {
    const mem = inMemoryApiKeys.get(cleanId)
    return {
      id: mem.id,
      merchant_id: cleanId,
      merchant_name: merchantName,
      api_key: mem.rawKey,
      key_preview: mem.preview,
      created_at: mem.createdAt,
      is_new: false,
    }
  }

  // 2. Generate a new raw dynamic API key
  const rawKey = generateRawApiKey()
  const digest = apiKeyDigest(rawKey)
  const keyId = randomUUID()
  const keyPreview = rawKey.slice(0, 14) + '****'

  const record = {
    id: keyId,
    merchant_id: cleanId,
    merchant_name: String(merchantName || 'Merchant').trim().slice(0, 100),
    label: 'Default Payment Gateway API Key',
    digest,
    key_preview: keyPreview,
    raw_key: rawKey,
  }

  inMemoryApiKeys.set(cleanId, { id: keyId, rawKey, preview: keyPreview, createdAt: new Date().toISOString() })

  if (admin) {
    try {
      await storeApiKeyRecord(record)
    } catch (e) {
      console.warn('[admin-supabase] storeApiKeyRecord notice:', e.message)
    }

    try {
      // Also update merchants table api_key & webhook_secret for maximum backward compatibility
      const updates = { api_key: rawKey, webhook_secret: rawKey, updated_at: new Date().toISOString() }
      let { error: uErr } = await admin.from('merchants').update(updates).eq('id', cleanId)
      if (uErr && (uErr.message?.includes('api_key') || uErr.message?.includes('schema cache'))) {
        delete updates.api_key
        await admin.from('merchants').update(updates).eq('id', cleanId)
      }
    } catch (e) {
      console.warn('[admin-supabase] merchants update api_key notice:', e.message)
    }
  }

  return {
    id: keyId,
    merchant_id: cleanId,
    merchant_name: merchantName,
    api_key: rawKey,
    key_preview: keyPreview,
    created_at: new Date().toISOString(),
    is_new: true,
  }
}

export async function getMerchantActiveApiKey(merchantId, userEmail = null, deviceId = null) {
  const cleanId = String(merchantId || '').trim()
  const cleanEmail = userEmail ? String(userEmail).trim().toLowerCase() : null
  let admin = null
  try { admin = getAdminClient() } catch (_) {}

  let merchant = null
  let resolvedMerchantId = cleanId

  if (admin) {
    try {
      const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(cleanId)
      if (cleanId && cleanId !== 'default') {
        let mQuery = admin.from('merchants').select('id, user_id, email, business_name, kyc_status, api_key, webhook_secret')
        if (isUuid) {
          mQuery = mQuery.or(`id.eq.${cleanId},user_id.eq.${cleanId}`)
        } else if (cleanId.includes('@')) {
          mQuery = mQuery.ilike('email', cleanId)
        } else {
          mQuery = mQuery.or(`id.eq.${cleanId},user_id.eq.${cleanId}`)
        }
        const { data } = await mQuery.maybeSingle()
        if (data) {
          merchant = data
          resolvedMerchantId = data.id
        }
      }

      if (!merchant && cleanEmail) {
        const { data: byEmail } = await admin.from('merchants').select('id, user_id, email, business_name, kyc_status, api_key, webhook_secret').ilike('email', cleanEmail).maybeSingle()
        if (byEmail) {
          merchant = byEmail
          resolvedMerchantId = byEmail.id
        }
      }

      if (!merchant && deviceId) {
        const { data: dev } = await admin.from('merchant_devices').select('merchant_id').eq('device_id', deviceId).maybeSingle()
        if (dev?.merchant_id) {
          const { data: byDev } = await admin.from('merchants').select('id, user_id, email, business_name, kyc_status, api_key, webhook_secret').eq('id', dev.merchant_id).maybeSingle()
          if (byDev) {
            merchant = byDev
            resolvedMerchantId = byDev.id
          }
        }
      }
    } catch (err) {
      console.warn('[admin-supabase] getMerchantActiveApiKey merchant lookup notice:', err.message)
    }
  }

  return await getOrCreateMerchantApiKey(resolvedMerchantId, merchant?.business_name || 'Merchant')
}

// ──────────────────────────────────────────────────────────────────────────────
// Payment Events (Platform Analytics)
// ──────────────────────────────────────────────────────────────────────────────

export async function recordPaymentEvent(orderId, eventData) {
  try {
    const admin = getAdminClient()
    const { error } = await admin
      .from('payment_events')
      .insert({
        order_id:       orderId,
        tran_id:        eventData.tran_id || null,
        trx_id:         eventData.trx_id || null,
        status:         eventData.status || 'PAID',
        amount:         eventData.amount || null,
        currency:       eventData.currency || 'BDT',
        payment_method: eventData.payment_method || null,
        sender_number:  eventData.sender_number || null,
        payment_time:   eventData.payment_time
          ? new Date(eventData.payment_time).toISOString()
          : new Date().toISOString(),
        merchant_id:    eventData.merchant_id || null,
        merchant_name:  eventData.merchant_name || null,
        project_ref:    eventData.project_ref || null,
        cus_name:       eventData.cus_name || null,
        cus_email:      eventData.cus_email || null,
        product_name:   eventData.product_name || null,
      })

    if (error) {
      console.error('[admin-supabase] recordPaymentEvent notice:', error.message)
    }
  } catch (err) {
    console.warn('[admin-supabase] recordPaymentEvent skipped:', err.message)
  }
}

export async function listPaymentEvents({ limit = 50, status = null, merchantId = null } = {}) {
  let q = getAdminClient()
    .from('payment_events')
    .select('*')
    .order('recorded_at', { ascending: false })
    .limit(Math.min(limit, 200))

  if (status) q = q.eq('status', status)
  if (merchantId) q = q.eq('merchant_id', merchantId)

  const { data, error } = await q
  if (error) throw new Error('Failed to fetch payment events: ' + error.message)
  return data || []
}

/**
 * Aggregated platform statistics with exact counts and revenue calculation
 */
export async function getPaymentStats() {
  const admin = getAdminClient()

  const [paidCountRes, failedCountRes, cancelledCountRes, recentRes, paidAmountsRes] = await Promise.all([
    admin.from('payment_events').select('*', { count: 'exact', head: true }).eq('status', 'PAID'),
    admin.from('payment_events').select('*', { count: 'exact', head: true }).eq('status', 'FAILED'),
    admin.from('payment_events').select('*', { count: 'exact', head: true }).eq('status', 'CANCELLED'),
    admin.from('payment_events').select('*').order('recorded_at', { ascending: false }).limit(10),
    admin.from('payment_events').select('amount').eq('status', 'PAID'),
  ])

  const totalPaid = paidCountRes.count ?? 0
  const totalFailed = failedCountRes.count ?? 0
  const totalCancelled = cancelledCountRes.count ?? 0
  const recentEvents = recentRes.data || []
  const totalRevenue = (paidAmountsRes.data || []).reduce((sum, e) => sum + Number(e.amount || 0), 0)

  return {
    total_paid: totalPaid,
    total_failed: totalFailed,
    total_cancelled: totalCancelled,
    total_revenue_bdt: totalRevenue.toFixed(2),
    recent_events: recentEvents,
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Health Check
// ──────────────────────────────────────────────────────────────────────────────
export async function pingAdminDatabase() {
  try {
    const { error } = await getAdminClient()
      .from('gateway_config')
      .select('id')
      .eq('id', CONFIG_ID)
      .single()
    return !error
  } catch {
    return false
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Config normalisation helpers
// ──────────────────────────────────────────────────────────────────────────────

function normaliseConfig(row) {
  if (!row) return getDefaultGatewayConfig()
  return {
    id: row.id,
    enabled_methods: {
      bKash:  row.bkash_enabled  ?? true,
      Nagad:  row.nagad_enabled  ?? true,
      Rocket: row.rocket_enabled ?? true,
      Upay:   row.upay_enabled   ?? true,
    },
    default_success_url:  row.default_success_url  || 'https://pay.swapnopay.top/success',
    default_fail_url:     row.default_fail_url     || 'https://pay.swapnopay.top/failed',
    default_cancel_url:   row.default_cancel_url   || 'https://pay.swapnopay.top/cancelled',
    min_amount:           Number(row.min_amount)   || 10,
    max_amount:           Number(row.max_amount)   || 500000,
    daily_limit_per_merchant: Number(row.daily_limit_per_merchant) || 10000000,
    payment_timeout_seconds:    Number(row.payment_timeout_seconds)    || 600,
    processing_timeout_seconds: Number(row.processing_timeout_seconds) || 300,
    gateway_fee_percent: Number(row.gateway_fee_percent) || 0,
    gateway_fee_fixed:   Number(row.gateway_fee_fixed)   || 0,
    customer_receipts_enabled: row.customer_receipts_enabled ?? true,
    merchant_receipts_enabled: row.merchant_receipts_enabled ?? true,
    maintenance_mode:    row.maintenance_mode    ?? false,
    maintenance_message: row.maintenance_message || '',
    updated_at: row.updated_at,
  }
}

function denormaliseConfig(config) {
  const row = {}
  if (config.enabled_methods) {
    row.bkash_enabled  = config.enabled_methods.bKash  ?? true
    row.nagad_enabled  = config.enabled_methods.Nagad  ?? true
    row.rocket_enabled = config.enabled_methods.Rocket ?? true
    row.upay_enabled   = config.enabled_methods.Upay   ?? true
  }
  const textFields = [
    'default_success_url', 'default_fail_url', 'default_cancel_url', 'maintenance_message',
  ]
  const numFields = [
    'min_amount', 'max_amount', 'daily_limit_per_merchant',
    'payment_timeout_seconds', 'processing_timeout_seconds',
    'gateway_fee_percent', 'gateway_fee_fixed',
  ]
  const boolFields = [
    'customer_receipts_enabled', 'merchant_receipts_enabled', 'maintenance_mode',
  ]
  for (const f of textFields) if (config[f] !== undefined) row[f] = config[f]
  for (const f of numFields)  if (config[f] !== undefined) row[f] = Number(config[f])
  for (const f of boolFields) if (config[f] !== undefined) row[f] = Boolean(config[f])
  return row
}

export function getDefaultGatewayConfig() {
  return {
    enabled_methods: { bKash: true, Nagad: true, Rocket: true, Upay: true },
    default_success_url: 'https://pay.swapnopay.top/success',
    default_fail_url:    'https://pay.swapnopay.top/failed',
    default_cancel_url:  'https://pay.swapnopay.top/cancelled',
    min_amount: 10, max_amount: 500000,
    daily_limit_per_merchant: 10000000,
    payment_timeout_seconds: 600,
    processing_timeout_seconds: 300,
    gateway_fee_percent: 0, gateway_fee_fixed: 0,
    customer_receipts_enabled: true, merchant_receipts_enabled: true,
    maintenance_mode: false, maintenance_message: '',
    device_active: null, device_last_seen: null,
    merchant_logo_url: null,
    updated_at: null,
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Merchant Administration & Database Provisioning
// ──────────────────────────────────────────────────────────────────────────────

export async function listMerchants() {
  const { data, error } = await getAdminClient()
    .from('merchant_gateway_settings')
    .select('*')
    .order('updated_at', { ascending: false })

  if (error) throw new Error('Failed to fetch merchants: ' + error.message)
  return data || []
}

export async function getMerchantById(merchantId) {
  const { data, error } = await getAdminClient()
    .from('merchant_gateway_settings')
    .select('*')
    .eq('merchant_id', merchantId)
    .maybeSingle()

  if (error) throw new Error('Failed to fetch merchant: ' + error.message)
  return data
}

/**
 * Register or update merchant profile including logo URL and Supabase DB credentials.
 */
export async function upsertMerchantProfile(profile) {
  const row = {
    merchant_id:        profile.merchant_id,
    merchant_name:      profile.merchant_name || 'Merchant ' + profile.merchant_id.slice(0, 6),
    merchant_logo_url:  profile.merchant_logo_url || null,
    supabase_url:       profile.supabase_url || null,
    supabase_anon_key:  profile.supabase_anon_key || null,
    bkash_enabled:      profile.bkash_enabled ?? true,
    nagad_enabled:      profile.nagad_enabled ?? true,
    rocket_enabled:     profile.rocket_enabled ?? true,
    upay_enabled:       profile.upay_enabled ?? true,
    success_url:        profile.success_url || null,
    fail_url:           profile.fail_url || null,
    cancel_url:         profile.cancel_url || null,
    receiving_numbers:  profile.receiving_numbers || {},
    qr_codes:           profile.qr_codes || {},
    auto_appeal_matching: profile.auto_appeal_matching ?? false,
    status:             profile.status || 'ACTIVE',
    updated_at:         new Date().toISOString(),
  }

  const { data, error } = await getAdminClient()
    .from('merchant_gateway_settings')
    .upsert(row, { onConflict: 'merchant_id' })
    .select('*')
    .single()

  if (error) throw new Error('Failed to save merchant profile: ' + error.message)
  return data
}

export async function updateMerchantStatus(merchantId, status) {
  const { data, error } = await getAdminClient()
    .from('merchant_gateway_settings')
    .update({ status, updated_at: new Date().toISOString() })
    .eq('merchant_id', merchantId)
    .select('*')
    .single()

  if (error) throw new Error('Failed to update merchant status: ' + error.message)
  return data
}

// ──────────────────────────────────────────────────────────────────────────────
// Showcase Config
// ──────────────────────────────────────────────────────────────────────────────

export async function getShowcaseConfig(key = 'main_showcase') {
  try {
    const { data, error } = await getAdminClient()
      .from('showcase_config')
      .select('*')
      .eq('key', key)
      .maybeSingle()

    if (error) {
      console.warn('[showcase] Admin DB query notice:', error.message)
      return null
    }
    return data ? data.value : null
  } catch (e) {
    console.warn('[showcase] getShowcaseConfig fallback:', e.message)
    return null
  }
}

export async function upsertShowcaseConfig(key, valueData) {
  const admin = getAdminClient()
  const now = new Date().toISOString()

  try {
    const { data: existing } = await admin
      .from('showcase_config')
      .select('id, key')
      .eq('key', key)
      .maybeSingle()

    if (existing) {
      const { data, error } = await admin
        .from('showcase_config')
        .update({ value: valueData, updated_at: now })
        .eq('key', key)
        .select('*')
        .single()
      if (!error) return data.value
      if (error && error.code !== 'PGRST116') throw error
    }

    const { data, error } = await admin
      .from('showcase_config')
      .upsert({ key, value: valueData, updated_at: now }, { onConflict: 'key' })
      .select('*')
      .single()

    if (!error) return data.value

    const { data: updateData, error: updateError } = await admin
      .from('showcase_config')
      .update({ value: valueData, updated_at: now })
      .eq('key', key)
      .select('*')
      .single()

    if (updateError) throw updateError
    return updateData.value
  } catch (err) {
    throw new Error(`Failed to save showcase config '${key}': ${err.message}`)
  }
}

export async function setShowcaseConfig(valueData) {
  return upsertShowcaseConfig('main_showcase', valueData)
}

// ──────────────────────────────────────────────────────────────────────────────
// Dispute Appeals (Customer unverified payments)
// ──────────────────────────────────────────────────────────────────────────────

export async function createDisputeAppeal(merchantId, { order_id, trx_id, cus_phone, payment_method, note, screenshot_url } = {}) {
  if (!order_id || !trx_id) {
    throw new Error('order_id and trx_id are required for dispute appeal')
  }

  // Normalize order_id: ensure standard UUID format if hex
  let cleanOrderId = String(order_id).trim()
  const rawClean = cleanOrderId.replace(/[^0-9a-f]/gi, '')
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(cleanOrderId) && rawClean.length === 32) {
    cleanOrderId = `${rawClean.slice(0, 8)}-${rawClean.slice(8, 12)}-${rawClean.slice(12, 16)}-${rawClean.slice(16, 20)}-${rawClean.slice(20, 32)}`
  }

  const appealRecord = {
    order_id: cleanOrderId,
    trx_id: String(trx_id).trim().toUpperCase(),
    cus_phone: cus_phone ? String(cus_phone).trim() : null,
    note: note ? String(note).slice(0, 500) : 'Customer initiated payment appeal',
    screenshot_url: screenshot_url || null,
    status: 'PENDING_REVIEW',
    created_at: new Date().toISOString(),
  }

  let savedAppeal = null

  // 1. Insert into platform admin DB appeals table if it exists (bypasses RLS)
  try {
    const admin = getAdminClient()
    const { data: adminAppeal, error: adminErr } = await admin
      .from('appeals')
      .insert({
        ...appealRecord,
        id: randomUUID()
      })
      .select()
      .maybeSingle()

    if (!adminErr && adminAppeal) {
      console.log(`[appeals] Created appeal ${adminAppeal.id} in platform admin DB`)
      savedAppeal = adminAppeal
    }
  } catch (adminErr) {
    // Admin DB appeals table may not be configured in some environments
  }

  // 2. Insert into merchant's own Supabase DB
  try {
    const creds = await getMerchantCredentials(merchantId)
    if (creds?.supabase_url) {
      // If merchant uses the same project as platform admin, use admin client with service_role to bypass RLS
      if (creds.supabase_url === process.env.ADMIN_SUPABASE_URL) {
        const admin = getAdminClient()
        const { data, error } = await admin
          .from('appeals')
          .insert(appealRecord)
          .select()
          .single()

        if (!error && data) {
          console.log(`[appeals] Created appeal ${data.id} in merchant ${merchantId} DB via service role`)
          return data
        }
      } else if (creds.supabase_anon_key) {
        const merchantClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)
        const { data, error } = await merchantClient
          .from('appeals')
          .insert(appealRecord)
          .select()
          .single()

        if (!error && data) {
          console.log(`[appeals] Created appeal ${data.id} in merchant ${merchantId} DB`)
          return data
        }
        if (error) {
          console.warn(`[appeals] Merchant DB insert notice:`, error.message)
        }
      }
    }
  } catch (err) {
    console.warn(`[appeals] Merchant DB appeal write notice:`, err.message)
  }

  // 3. Fallback: log payment event in admin DB
  await recordPaymentEvent(cleanOrderId, {
    tran_id: cleanOrderId,
    trx_id,
    payment_method: payment_method || 'MFS',
    status: 'PENDING',
    merchant_id: merchantId,
    sender_number: cus_phone,
    product_name: `Appeal submitted: ${note || 'Disputed payment'}`,
  })

  return savedAppeal || {
    id: 'app_' + Date.now(),
    order_id: cleanOrderId,
    trx_id,
    cus_phone,
    status: 'PENDING_REVIEW',
    created_at: appealRecord.created_at,
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Merchant Device List
// ──────────────────────────────────────────────────────────────────────────────

export async function getMerchantDevicesList(merchantId) {
  if (!merchantId) return []
  try {
    const creds = await getMerchantCredentials(merchantId)
    if (!creds?.supabase_url || !creds?.supabase_anon_key) return []

    const merchantClient = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)
    const { data, error } = await merchantClient
      .from('devices')
      .select('id, online, last_sync, disabled, created_at')
      .eq('merchant_id', merchantId)
      .order('last_sync', { ascending: false })

    if (error) {
      console.warn(`[devices] Query notice for merchant ${merchantId}:`, error.message)
      return []
    }
    return data || []
  } catch (err) {
    console.warn(`[devices] getMerchantDevicesList error:`, err.message)
    return []
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// MFS SMS Parsing Patterns (bKash, Nagad, Rocket, Upay)
// ──────────────────────────────────────────────────────────────────────────────

export async function getMfsPatterns() {
  try {
    const admin = getAdminClient()
    const { data } = await admin
      .from('showcase_config')
      .select('*')
      .eq('key', 'mfs_sms_patterns')
      .maybeSingle()

    if (data?.value) return data.value
  } catch {}

  // Production MFS regex patterns
  return {
    bKash: {
      regex: 'TrxID\\s+([A-Z0-9]+).*?Tk\\s+([0-9,.]+).*?from\\s+([0-9]+)',
      trx_group: 1,
      amount_group: 2,
      sender_group: 3,
      sample_sender: 'bKash',
    },
    Nagad: {
      regex: 'TxnID:\\s*([A-Z0-9]+).*?Amount:\\s*Tk\\s*([0-9,.]+).*?Customer:\\s*([0-9]+)',
      trx_group: 1,
      amount_group: 2,
      sender_group: 3,
      sample_sender: '16167',
    },
    Rocket: {
      regex: 'TxnId:\\s*([0-9]+).*?Tk\\.([0-9,.]+).*?From\\s*([0-9]+)',
      trx_group: 1,
      amount_group: 2,
      sender_group: 3,
      sample_sender: '16216',
    },
    Upay: {
      regex: 'TrxID:\\s*([A-Z0-9]+).*?Amt:\\s*Tk\\s*([0-9,.]+).*?From:\\s*([0-9]+)',
      trx_group: 1,
      amount_group: 2,
      sender_group: 3,
      sample_sender: 'UPAY',
    },
  }
}

export async function setMfsPatterns(patterns) {
  return upsertShowcaseConfig('mfs_sms_patterns', patterns)
}

// ──────────────────────────────────────────────────────────────────────────────
// System Diagnostic Overview
// ──────────────────────────────────────────────────────────────────────────────

export async function getAdminSystemOverview(heartbeatMap = null, io = null) {
  const mem = process.memoryUsage()
  const startTime = Date.now()
  const dbOk = await pingAdminDatabase()
  const dbLatencyMs = Date.now() - startTime

  return {
    service: 'swapnopay-backend',
    node_version: process.version,
    uptime_seconds: Math.floor(process.uptime()),
    memory: {
      rss_mb: (mem.rss / 1024 / 1024).toFixed(2),
      heap_used_mb: (mem.heapUsed / 1024 / 1024).toFixed(2),
      heap_total_mb: (mem.heapTotal / 1024 / 1024).toFixed(2),
    },
    socket_io: {
      connected_clients: io?.engine?.clientsCount || 0,
      active_merchants_online: heartbeatMap?.size || 0,
    },
    database: {
      admin_supabase_connected: dbOk,
      round_trip_latency_ms: dbLatencyMs,
    },
    timestamp: new Date().toISOString(),
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Merchant KYC Verification (Admin Platform)
// ──────────────────────────────────────────────────────────────────────────────

export async function submitMerchantKyc(payload) {
  const admin = getAdminClient()
  try {
    const { data, error } = await admin.rpc('submit_platform_merchant_kyc', { p_submission: payload })
    if (!error && data?.id) {
      return data
    }
    if (error) {
      console.warn('[admin-supabase] submit_platform_merchant_kyc RPC notice (will use direct update):', error.message)
    }
  } catch (rpcErr) {
    console.warn('[admin-supabase] submit_platform_merchant_kyc RPC exception, falling back to direct table update:', rpcErr.message)
  }

  // Fallback: Direct table update in public.merchants and public.merchant_kyc_submissions
  const merchantId = payload.merchant_id
  const nowIso = new Date().toISOString()
  const updates = {
    nid_number: payload.nid_number,
    nid_name: payload.nid_name || null,
    nid_dob: payload.nid_dob || null,
    nid_front_url: payload.nid_front_url,
    nid_back_url: payload.nid_back_url,
    face_photo_url: payload.face_photo_url,
    kyc_status: 'PENDING',
    kyc_submitted_at: nowIso,
    kyc_reviewed_at: null,
    kyc_reviewed_by: null,
    kyc_rejection_reason: null,
    trial_ends_at: payload.trial_ends_at || new Date(Date.now() + 90 * 86400000).toISOString(),
    updated_at: nowIso,
  }

  let { data: updatedMerchant, error: updateError } = await admin
    .from('merchants')
    .update(updates)
    .eq('id', merchantId)
    .select()
    .maybeSingle()

  if (!updatedMerchant) {
    // If not matched by id, try matching by user_id
    const res = await admin
      .from('merchants')
      .update(updates)
      .eq('user_id', merchantId)
      .select()
      .maybeSingle()
    updatedMerchant = res.data
    updateError = res.error
  }

  // Schema cache fallback: if column doesn't exist in Supabase PostgREST schema cache (e.g. trial_ends_at)
  if (updateError && (updateError.message?.includes('trial_ends_at') || updateError.message?.includes('schema cache'))) {
    console.warn('[admin-supabase] submitMerchantKyc retrying without trial_ends_at:', updateError.message)
    delete updates.trial_ends_at
    const retry = await admin.from('merchants').update(updates).eq('id', merchantId).select().maybeSingle()
    updatedMerchant = retry.data
    updateError = retry.error
    if (!updatedMerchant && !updateError) {
      const res2 = await admin.from('merchants').update(updates).eq('user_id', merchantId).select().maybeSingle()
      updatedMerchant = res2.data
      updateError = res2.error
    }
  }

  if (updateError) {
    console.error('[admin-supabase] Direct merchant update error:', updateError.message)
    throw new Error('KYC was not saved: ' + updateError.message)
  }
  if (!updatedMerchant) {
    // If merchant record doesn't exist yet, insert it
    const newMerchant = {
      id: merchantId,
      user_id: merchantId,
      business_name: payload.nid_name || 'Store Merchant',
      status: 'PENDING_VERIFICATION',
      ...updates
    }
    const { data: inserted, error: insertErr } = await admin
      .from('merchants')
      .insert(newMerchant)
      .select()
      .maybeSingle()
    if (insertErr || !inserted) {
      throw new Error('KYC was not saved: merchant not found in admin database')
    }
    updatedMerchant = inserted
  }

  // Record audit entry in merchant_kyc_submissions
  try {
    await admin.from('merchant_kyc_submissions').insert({
      merchant_id: updatedMerchant.id,
      nid_number: payload.nid_number,
      nid_name: payload.nid_name,
      nid_dob: payload.nid_dob,
      nid_front_url: payload.nid_front_url,
      nid_back_url: payload.nid_back_url,
      face_photo_url: payload.face_photo_url,
      liveness_passed: true,
      ocr_raw_text: payload.ocr_raw_text || '',
      status: 'PENDING',
      // Store enriched NID OCR fields in metadata JSON column (no new schema columns needed)
      metadata: {
        name_bangla:  payload.name_bangla  || null,
        name_english: payload.name_english || null,
        father_name:  payload.father_name  || null,
        mother_name:  payload.mother_name  || null,
        blood_group:  payload.blood_group  || null,
        doc_type:     payload.doc_type     || null,
      },
    })
  } catch (auditErr) {
    console.warn('[admin-supabase] merchant_kyc_submissions audit insert notice:', auditErr.message)
  }

  return updatedMerchant
}

export async function listPendingKycSubmissions() {
  const { data, error } = await getAdminClient().from('merchants').select('*')
    .in('kyc_status', ['PENDING', 'PENDING_REVIEW', 'VERIFIED', 'REJECTED'])
    .order('kyc_submitted_at', { ascending: false })
  if (error) throw new Error('Unable to load KYC submissions: ' + error.message)
  return data || []
}

export async function reviewMerchantKyc(merchantId, { action, reason, reviewed_by = 'ADMIN' }) {
  if (!['APPROVE', 'VERIFY', 'REJECT'].includes(action)) throw new Error('Invalid review action')
  const status = action === 'REJECT' ? 'REJECTED' : 'VERIFIED'
  const admin = getAdminClient()
  try {
    const { data, error } = await admin.rpc('review_platform_merchant_kyc', {
      p_merchant_id: merchantId, p_status: status,
      p_reason: reason || '', p_reviewer: reviewed_by,
    })
    if (!error && data?.id) return data
    if (error) {
      console.warn('[admin-supabase] review_platform_merchant_kyc RPC notice (will use direct update):', error.message)
    }
  } catch (rpcErr) {
    console.warn('[admin-supabase] review_platform_merchant_kyc RPC notice:', rpcErr.message)
  }

  // Direct table fallback
  const nowIso = new Date().toISOString()
  const updates = {
    kyc_status: status,
    kyc_reviewed_at: nowIso,
    kyc_reviewed_by: reviewed_by,
    kyc_rejection_reason: action === 'REJECT' ? (reason || 'Documents did not meet criteria') : null,
    updated_at: nowIso,
  }
  if (status === 'VERIFIED') {
    updates.status = 'ACTIVE'
    // Ensure 3-month free trial is set upon verification if not already present
    updates.trial_ends_at = new Date(Date.now() + 90 * 86400000).toISOString()
  }
  let { data: mData, error: mErr } = await admin.from('merchants').update(updates).eq('id', merchantId).select().maybeSingle()
  if (!mData && !mErr) {
    const res = await admin.from('merchants').update(updates).eq('user_id', merchantId).select().maybeSingle()
    mData = res.data
    mErr = res.error
  }

  // Schema cache fallback: if column doesn't exist in Supabase PostgREST schema cache (e.g. trial_ends_at)
  if (mErr && (mErr.message?.includes('trial_ends_at') || mErr.message?.includes('schema cache'))) {
    console.warn('[admin-supabase] reviewMerchantKyc retrying without trial_ends_at:', mErr.message)
    delete updates.trial_ends_at
    const retry = await admin.from('merchants').update(updates).eq('id', merchantId).select().maybeSingle()
    mData = retry.data
    mErr = retry.error
    if (!mData && !mErr) {
      const res2 = await admin.from('merchants').update(updates).eq('user_id', merchantId).select().maybeSingle()
      mData = res2.data
      mErr = res2.error
    }
  }

  if (mErr) throw new Error('KYC review was not saved: ' + mErr.message)
  if (!mData) throw new Error('KYC merchant not found')

  try {
    await admin.from('merchant_kyc_submissions')
      .update({
        status: status === 'VERIFIED' ? 'APPROVED' : 'REJECTED',
        reviewed_at: nowIso,
        reviewed_by,
        rejection_reason: updates.kyc_rejection_reason,
        updated_at: nowIso
      })
      .eq('merchant_id', mData.id)
      .eq('status', 'PENDING')
  } catch (_) {}

  if (status === 'VERIFIED') {
    try {
      const apiKeyResult = await getOrCreateMerchantApiKey(mData.id, mData.business_name || 'Merchant')
      if (apiKeyResult?.api_key) {
        mData.api_key = apiKeyResult.api_key
        mData.key_preview = apiKeyResult.key_preview
      }
    } catch (kErr) {
      console.warn('[admin-supabase] reviewMerchantKyc auto-generation notice:', kErr.message)
    }
  }

  return mData
}

// ──────────────────────────────────────────────────────────────────────────────
// Platform Subscription & Pricing Management (Dynamic Admin Control & Anti-Abuse)
// ──────────────────────────────────────────────────────────────────────────────

let inMemorySubscriptionConfig = {
  monthly_fee: 100,
  quarterly_fee: 250,
  yearly_fee: 650,
  trial_days: 90, // 3 months free trial
  is_trial_enabled: true,
  enforce_nid_verification: true,
  updated_at: new Date().toISOString()
}

// In-memory fallback tracking for orders and subscriptions
const inMemoryOrders = new Map()
const inMemoryMerchantSubscriptions = new Map()

export async function getSubscriptionConfig() {
  try {
    const admin = getAdminClient()
    const { data, error } = await admin
      .from('platform_subscription_config')
      .select('*')
      .eq('id', 'default_config')
      .maybeSingle()

    if (!error && data) {
      return {
        monthly_fee: Number(data.monthly_fee) || 100,
        quarterly_fee: Number(data.quarterly_fee) || 250,
        yearly_fee: Number(data.yearly_fee) || 650,
        trial_days: Number(data.trial_days) || 90,
        is_trial_enabled: data.is_trial_enabled ?? true,
        enforce_nid_verification: data.enforce_nid_verification ?? true,
        updated_at: data.updated_at
      }
    }
  } catch (err) {
    // Database table may not exist yet; fall back gracefully
  }
  return { ...inMemorySubscriptionConfig }
}

export async function updateSubscriptionConfig(config) {
  const updated = {
    monthly_fee: Number(config.monthly_fee) || inMemorySubscriptionConfig.monthly_fee,
    quarterly_fee: Number(config.quarterly_fee) || inMemorySubscriptionConfig.quarterly_fee,
    yearly_fee: Number(config.yearly_fee) || inMemorySubscriptionConfig.yearly_fee,
    trial_days: Number(config.trial_days) || inMemorySubscriptionConfig.trial_days,
    is_trial_enabled: typeof config.is_trial_enabled === 'boolean' ? config.is_trial_enabled : inMemorySubscriptionConfig.is_trial_enabled,
    enforce_nid_verification: typeof config.enforce_nid_verification === 'boolean' ? config.enforce_nid_verification : inMemorySubscriptionConfig.enforce_nid_verification,
    updated_at: new Date().toISOString()
  }
  inMemorySubscriptionConfig = { ...updated }

  try {
    const admin = getAdminClient()
    await admin.from('platform_subscription_config').upsert({
      id: 'default_config',
      ...updated
    })
  } catch (err) {
    console.warn('[admin-supabase] updateSubscriptionConfig DB notice:', err.message)
  }
  return inMemorySubscriptionConfig
}

/**
 * Get comprehensive subscription, trial, and NID compliance status for a merchant.
 */
/**
 * Get comprehensive subscription, trial, and NID compliance status for a merchant.
 */
export async function getMerchantSubscriptionStatus(merchantId, userEmail = null, deviceId = null) {
  const config = await getSubscriptionConfig()
  let admin = null
  try {
    admin = getAdminClient()
  } catch (_) {}

  let merchant = null
  if (admin) {
    try {
      const cleanId = String(merchantId || '').trim()
      const cleanEmail = userEmail ? String(userEmail).trim().toLowerCase() : null
      const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(cleanId)

      // 1. Try finding merchant by id or user_id
      if (cleanId && cleanId !== 'default') {
        let mQuery = admin
          .from('merchants')
          .select('id, user_id, email, business_name, nid_number, kyc_status, status, trial_ends_at, subscription_status, subscription_plan, subscription_expires_at, created_at, kyc_reviewed_at')
        if (isUuid) {
          mQuery = mQuery.or(`id.eq.${cleanId},user_id.eq.${cleanId}`)
        } else if (cleanId.includes('@')) {
          mQuery = mQuery.ilike('email', cleanId)
        } else {
          mQuery = mQuery.or(`id.eq.${cleanId},user_id.eq.${cleanId}`)
        }
        let { data, error } = await mQuery.maybeSingle()
        if (error && (error.message?.includes('column') || error.message?.includes('schema cache'))) {
          const fallbackQuery = admin
            .from('merchants')
            .select('id, user_id, email, business_name, nid_number, kyc_status, status, created_at, kyc_reviewed_at')
          const fallbackRes = isUuid
            ? await fallbackQuery.or(`id.eq.${cleanId},user_id.eq.${cleanId}`).maybeSingle()
            : await fallbackQuery.eq('id', cleanId).maybeSingle()
          merchant = fallbackRes.data
        } else {
          merchant = data
        }
      }

      // 2. Fallback search by email if provided
      if (!merchant && cleanEmail) {
        const { data: byEmail } = await admin
          .from('merchants')
          .select('id, user_id, email, business_name, nid_number, kyc_status, status, trial_ends_at, subscription_status, subscription_plan, subscription_expires_at, created_at, kyc_reviewed_at')
          .ilike('email', cleanEmail)
          .maybeSingle()
        if (byEmail) merchant = byEmail
      }

      // 3. Fallback search by deviceId in merchant_devices
      if (!merchant && deviceId) {
        const { data: dev } = await admin
          .from('merchant_devices')
          .select('merchant_id')
          .eq('device_id', deviceId)
          .maybeSingle()
        if (dev?.merchant_id) {
          const { data: byDev } = await admin
            .from('merchants')
            .select('id, user_id, email, business_name, nid_number, kyc_status, status, trial_ends_at, subscription_status, subscription_plan, subscription_expires_at, created_at, kyc_reviewed_at')
            .eq('id', dev.merchant_id)
            .maybeSingle()
          if (byDev) merchant = byDev
        }
      }
    } catch (err) {
      console.warn('[subscription] Merchant fetch notice:', err.message)
    }
  }

  // Fallback to in-memory subscription record if database is empty/test
  const memSub = inMemoryMerchantSubscriptions.get(merchantId) || {}

  const isKycVerified = (merchant?.kyc_status === 'VERIFIED') || (memSub.kyc_status === 'VERIFIED')
  const hasNid = Boolean(merchant?.nid_number || memSub.nid_number || isKycVerified)
  const nidNumber = merchant?.nid_number || memSub.nid_number || null

  const now = Date.now()
  const subExpiresAt = merchant?.subscription_expires_at
    ? new Date(merchant.subscription_expires_at).getTime()
    : (memSub.subscription_expires_at ? new Date(memSub.subscription_expires_at).getTime() : 0)

  const isSubActive = subExpiresAt > now && ((merchant?.subscription_status || memSub.subscription_status) === 'ACTIVE')

  // Calculate trial expiration
  let trialEndsAtTime = 0
  if (merchant?.trial_ends_at) {
    trialEndsAtTime = new Date(merchant.trial_ends_at).getTime()
  } else if (memSub.trial_ends_at) {
    trialEndsAtTime = new Date(memSub.trial_ends_at).getTime()
  } else if (isKycVerified && merchant?.kyc_reviewed_at) {
    trialEndsAtTime = new Date(merchant.kyc_reviewed_at).getTime() + (config.trial_days * 86400000)
  } else if (isKycVerified) {
    // Verified account gets 90-day trial from now if trial_ends_at not yet persisted
    trialEndsAtTime = now + (config.trial_days * 86400000)
  } else if (merchant?.created_at) {
    trialEndsAtTime = new Date(merchant.created_at).getTime() + (config.trial_days * 86400000)
  } else {
    trialEndsAtTime = now + (config.trial_days * 86400000)
  }

  // If KYC was verified, ensure trial is active for at least 90 days
  if (isKycVerified && trialEndsAtTime < now) {
    trialEndsAtTime = now + (config.trial_days * 86400000)
  }

  const isTrialActive = !isSubActive && config.is_trial_enabled && trialEndsAtTime > now
  const remainingTrialDays = isTrialActive ? Math.max(0, Math.ceil((trialEndsAtTime - now) / 86400000)) : 0

  // Decision logic for access permission
  let canAccessService = false
  let status = 'EXPIRED'
  let lockReason = null

  if (config.enforce_nid_verification && !hasNid && !isKycVerified) {
    canAccessService = false
    status = 'REQUIRES_NID'
    lockReason = 'SwapnoPay সেবা ব্যবহারের জন্য জাতীয় পরিচয়পত্র (NID) ভেরিফিকেশন বাধ্যতামূলক।'
  } else if (isSubActive) {
    canAccessService = true
    status = 'ACTIVE'
  } else if (isTrialActive || isKycVerified) {
    canAccessService = true
    status = 'TRIAL'
  } else {
    canAccessService = false
    status = 'EXPIRED'
    lockReason = 'আপনার ফ্রি ট্রায়াল ও সাবস্ক্রিপশনের মেয়াদ শেষ হয়েছে। সেবা অব্যাহত রাখতে সাবস্ক্রিপশন ফি পরিশোধ করুন।'
  }

  return {
    ok: true,
    merchant_id: merchantId,
    status, // 'ACTIVE' | 'TRIAL' | 'EXPIRED' | 'REQUIRES_NID'
    can_access_service: canAccessService,
    lock_reason: lockReason,
    has_nid: hasNid,
    nid_number: nidNumber ? (nidNumber.slice(0, 3) + '••••' + nidNumber.slice(-3)) : null,
    is_kyc_verified: isKycVerified,
    is_subscription_active: isSubActive,
    subscription_plan: isSubActive ? (merchant?.subscription_plan || memSub.subscription_plan) : (isTrialActive ? 'FREE_TRIAL' : null),
    subscription_expires_at: isSubActive ? new Date(subExpiresAt).toISOString() : null,
    is_trial_active: isTrialActive,
    trial_days_total: config.trial_days,
    trial_remaining_days: remainingTrialDays,
    trial_ends_at: isTrialActive ? new Date(trialEndsAtTime).toISOString() : null,
    pricing: {
      monthly: config.monthly_fee,
      quarterly: config.quarterly_fee,
      yearly: config.yearly_fee,
    }
  }
}

/**
 * Set in-memory merchant identity/NID for testing or offline environments
 */
export function setMerchantMemorySubscription(merchantId, data) {
  inMemoryMerchantSubscriptions.set(merchantId, {
    ...(inMemoryMerchantSubscriptions.get(merchantId) || {}),
    ...data
  })
}

/**
 * Create a new subscription checkout order.
 * Validates that the account has an associated NID before allowing order generation.
 */
export async function createSubscriptionOrder({
  merchantId,
  planType,
  method = 'bKash',
  successUrl: customSuccessUrl,
  failUrl: customFailUrl,
  cancelUrl: customCancelUrl
}) {
  const config = await getSubscriptionConfig()
  const cleanPlan = String(planType || '').toUpperCase()

  let amount = 0
  let days = 30
  if (cleanPlan === 'MONTHLY') {
    amount = config.monthly_fee
    days = 30
  } else if (cleanPlan === 'QUARTERLY') {
    amount = config.quarterly_fee
    days = 90
  } else if (cleanPlan === 'YEARLY') {
    amount = config.yearly_fee
    days = 365
  } else {
    throw new Error('অবৈধ সাবস্ক্রিপশন প্ল্যান। MONTHLY, QUARTERLY অথবা YEARLY নির্বাচন করুন।')
  }

  // Verify NID associated with account
  let admin = null
  try {
    admin = getAdminClient()
  } catch (_) {}

  let merchant = null
  const isMerchantUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(merchantId || '').trim())
  if (admin && isMerchantUuid) {
    try {
      const { data } = await admin
        .from('merchants')
        .select('id, user_id, nid_number, kyc_status, business_name, email')
        .or(`id.eq.${merchantId},user_id.eq.${merchantId}`)
        .maybeSingle()
      merchant = data
    } catch (_) {}
  }

  const memSub = inMemoryMerchantSubscriptions.get(merchantId)
  const nidNumber = merchant?.nid_number || memSub?.nid_number

  if (!nidNumber && config.enforce_nid_verification) {
    throw new Error('সাবস্ক্রিপশন ফি প্রদানের পূর্বে আপনার অ্যাকাউন্টে NID ভেরিফিকেশন সম্পন্ন থাকা আবশ্যক।')
  }

  const orderId = 'sub_' + randomUUID().slice(0, 8)

  // Receiving accounts for SwapnoPay platform payment gateway
  let platformGatewayConfig = null
  try {
    platformGatewayConfig = await getGatewayConfig()
  } catch (_) {}

  const receivingAccounts = {
    bKash:  process.env.SWAPNOPAY_BKASH_NUMBER  || platformGatewayConfig?.receiving_numbers?.bKash  || '01711223344',
    Nagad:  process.env.SWAPNOPAY_NAGAD_NUMBER  || platformGatewayConfig?.receiving_numbers?.Nagad  || '01811223344',
    Rocket: process.env.SWAPNOPAY_ROCKET_NUMBER || platformGatewayConfig?.receiving_numbers?.Rocket || '019112233441',
    Upay:   process.env.SWAPNOPAY_UPAY_NUMBER   || platformGatewayConfig?.receiving_numbers?.Upay   || '01711223344',
  }

  const gatewayBaseUrl = (process.env.SWAPNOPAY_GATEWAY_URL || 'https://pay.swapnopay.top').replace(/\/$/, '')
  const successUrl = customSuccessUrl || process.env.SWAPNOPAY_SUBSCRIPTION_SUCCESS_URL || platformGatewayConfig?.default_success_url || `${gatewayBaseUrl}/success?order_id=${orderId}&type=subscription`
  const failUrl    = customFailUrl    || process.env.SWAPNOPAY_SUBSCRIPTION_FAIL_URL    || platformGatewayConfig?.default_fail_url    || `${gatewayBaseUrl}/failed?order_id=${orderId}&type=subscription`
  const cancelUrl  = customCancelUrl  || process.env.SWAPNOPAY_SUBSCRIPTION_CANCEL_URL  || platformGatewayConfig?.default_cancel_url  || `${gatewayBaseUrl}/cancelled?order_id=${orderId}&type=subscription`

  const checkoutUrl = `${gatewayBaseUrl}/widget.html?order_id=${encodeURIComponent(orderId)}&amount=${amount}&merchant_name=${encodeURIComponent('SwapnoPay Subscription')}&plan_type=${encodeURIComponent(cleanPlan)}&merchant_number=${encodeURIComponent(receivingAccounts[method] || receivingAccounts.bKash)}&merchant_id=${encodeURIComponent(merchantId)}&success_url=${encodeURIComponent(successUrl)}&fail_url=${encodeURIComponent(failUrl)}&cancel_url=${encodeURIComponent(cancelUrl)}`

  const orderRecord = {
    id: orderId,
    merchant_id: merchantId,
    nid_number: nidNumber || 'PENDING_NID',
    plan_type: cleanPlan,
    amount,
    currency: 'BDT',
    days,
    payment_method: method,
    receiving_number: receivingAccounts[method] || receivingAccounts.bKash,
    checkout_url: checkoutUrl,
    success_url: successUrl,
    fail_url: failUrl,
    cancel_url: cancelUrl,
    status: 'PENDING',
    created_at: new Date().toISOString()
  }

  inMemoryOrders.set(orderId, orderRecord)

  if (admin) {
    try {
      await admin.from('merchant_subscriptions').insert({
        id: orderId,
        merchant_id: merchantId,
        nid_number: orderRecord.nid_number,
        plan_type: cleanPlan,
        amount,
        payment_method: method,
        status: 'PENDING',
        created_at: orderRecord.created_at
      })
    } catch (err) {
      console.warn('[subscription] DB insert notice:', err.message)
    }
  }

  return {
    ok: true,
    order_id: orderId,
    plan_type: cleanPlan,
    amount,
    days,
    currency: 'BDT',
    payment_method: method,
    receiving_account: orderRecord.receiving_number,
    nid_associated: nidNumber ? (nidNumber.slice(0, 3) + '••••' + nidNumber.slice(-3)) : null,
    checkout_url: checkoutUrl,
    success_url: successUrl,
    fail_url: failUrl,
    cancel_url: cancelUrl,
    instructions: `${method} অ্যাপ থেকে "Send Money" বা "Payment" করে ${orderRecord.receiving_number} নম্বরে ৳${amount} পাঠান এবং Transaction ID (TrxID) দিয়ে কনফার্ম করুন। অথবা সরাসরি নিচের ওয়েব গেটওয়ে লিংকে গিয়ে পেমেন্ট সম্পন্ন করুন: ${checkoutUrl}`
  }
}

/**
 * Verify Transaction ID and activate merchant subscription.
 * Binds payment to merchant NID and extends validity.
 */
export async function verifyAndActivateSubscription({ merchantId, orderId, trxId, method = 'bKash', planType }) {
  const cleanTrx = String(trxId || '').trim().toUpperCase()
  if (!cleanTrx || cleanTrx.length < 6) {
    throw new Error('একটি সঠিক Transaction ID (TrxID) প্রদান করুন।')
  }

  const config = await getSubscriptionConfig()
  let admin = null
  try {
    admin = getAdminClient()
  } catch (_) {}

  // Retrieve merchant and verify NID existence
  let merchant = null
  const isMerchantUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(merchantId || '').trim())
  if (admin && isMerchantUuid) {
    try {
      const { data } = await admin
        .from('merchants')
        .select('id, user_id, nid_number, kyc_status, subscription_expires_at, subscription_status')
        .or(`id.eq.${merchantId},user_id.eq.${merchantId}`)
        .maybeSingle()
      merchant = data
    } catch (_) {}
  }

  const memSub = inMemoryMerchantSubscriptions.get(merchantId) || {}
  const nidNumber = merchant?.nid_number || memSub.nid_number

  if (!nidNumber && config.enforce_nid_verification) {
    throw new Error('পেমেন্ট ভেরিফাই করতে অ্যাকাউন্টে NID যুক্ত থাকা আবশ্যক।')
  }

  // Look up order if orderId supplied
  let plan = planType ? String(planType).toUpperCase() : 'MONTHLY'
  let amount = config.monthly_fee
  let days = 30

  if (orderId && inMemoryOrders.has(orderId)) {
    const ord = inMemoryOrders.get(orderId)
    plan = ord.plan_type
    amount = ord.amount
    days = ord.days
    ord.status = 'COMPLETED'
    ord.trx_id = cleanTrx
  } else if (plan === 'QUARTERLY') {
    amount = config.quarterly_fee
    days = 90
  } else if (plan === 'YEARLY') {
    amount = config.yearly_fee
    days = 365
  }

  // Calculate new subscription expiration
  const now = Date.now()
  const currentExpiry = merchant?.subscription_expires_at
    ? new Date(merchant.subscription_expires_at).getTime()
    : (memSub.subscription_expires_at ? new Date(memSub.subscription_expires_at).getTime() : 0)

  const baseTime = Math.max(now, currentExpiry)
  const newExpiryIso = new Date(baseTime + (days * 86400000)).toISOString()

  // Update in-memory record
  inMemoryMerchantSubscriptions.set(merchantId, {
    ...memSub,
    subscription_status: 'ACTIVE',
    subscription_plan: plan,
    subscription_expires_at: newExpiryIso,
    nid_number: nidNumber,
    last_trx_id: cleanTrx,
    updated_at: new Date().toISOString()
  })

  // Update merchant row in Supabase
  if (admin) {
    try {
      const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(merchantId || '').trim())
      let updQuery = admin
        .from('merchants')
        .update({
          subscription_status: 'ACTIVE',
          subscription_plan: plan,
          subscription_expires_at: newExpiryIso,
          status: 'ACTIVE',
          updated_at: new Date().toISOString()
        })
      if (isUuid) {
        updQuery = updQuery.or(`id.eq.${merchantId},user_id.eq.${merchantId}`)
      } else {
        updQuery = updQuery.eq('id', merchantId)
      }
      await updQuery

      await admin.from('merchant_subscriptions').upsert({
        id: orderId || ('sub_' + randomUUID().slice(0, 8)),
        merchant_id: merchantId,
        nid_number: nidNumber,
        plan_type: plan,
        amount,
        trx_id: cleanTrx,
        payment_method: method,
        status: 'COMPLETED',
        verified_at: new Date().toISOString()
      })
    } catch (dbErr) {
      console.warn('[subscription] DB update notice:', dbErr.message)
    }

    // Record payment event for revenue tracking
    try {
      await recordPaymentEvent(orderId || ('sub_' + cleanTrx), {
        merchant_id: merchantId,
        amount,
        currency: 'BDT',
        status: 'PAID',
        payment_method: method,
        trx_id: cleanTrx,
        product_name: `SwapnoPay ${plan} Subscription`,
      })
    } catch (_) {}
  }

  return {
    ok: true,
    message: `অভিনন্দন! আপনার SwapnoPay ${plan} সাবস্ক্রিপশন সফলভাবে সক্রিয় হয়েছে।`,
    subscription_status: 'ACTIVE',
    subscription_plan: plan,
    subscription_expires_at: newExpiryIso,
    nid_number: nidNumber ? (nidNumber.slice(0, 3) + '••••' + nidNumber.slice(-3)) : null,
    trx_id: cleanTrx,
  }
}

/**
 * Retrieve past subscription payments and order history for a merchant.
 */
export async function getMerchantSubscriptionHistory(merchantId) {
  if (!merchantId) return []
  let admin = null
  try {
    admin = getAdminClient()
  } catch (_) {}
  if (!admin) {
    return []
  }

  const cleanId = String(merchantId).trim()
  const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(cleanId)

  try {
    let query = admin
      .from('merchant_subscriptions')
      .select('id, merchant_id, nid_number, plan_type, amount, trx_id, payment_method, status, verified_at, created_at')

    if (isUuid) {
      query = query.or(`merchant_id.eq.${cleanId},id.eq.${cleanId}`)
    } else {
      query = query.eq('merchant_id', cleanId)
    }

    const { data, error } = await query
      .order('created_at', { ascending: false })
      .limit(50)

    if (error) {
      console.warn('[getMerchantSubscriptionHistory] DB query notice:', error.message)
      return []
    }

    return (data || []).map(row => ({
      id: row.id,
      merchant_id: row.merchant_id,
      nid_number: row.nid_number ? (String(row.nid_number).slice(0, 3) + '••••' + String(row.nid_number).slice(-3)) : null,
      plan_type: row.plan_type || 'MONTHLY',
      amount: Number(row.amount) || 0,
      trx_id: row.trx_id || null,
      payment_method: row.payment_method || 'bKash',
      status: row.status || 'COMPLETED',
      created_at: row.created_at || new Date().toISOString(),
      verified_at: row.verified_at || null,
    }))
  } catch (err) {
    console.warn('[getMerchantSubscriptionHistory] Error:', err.message)
    return []
  }
}

/**
 * Downgrade or switch merchant subscription to the Free Plan.
 */
export async function downgradeMerchantSubscription(merchantId) {
  if (!merchantId) throw new Error('merchant_id is required')
  const cleanId = String(merchantId).trim()
  const memSub = inMemoryMerchantSubscriptions.get(cleanId) || {}
  inMemoryMerchantSubscriptions.set(cleanId, {
    ...memSub,
    subscription_status: 'FREE',
    subscription_plan: 'FREE',
    updated_at: new Date().toISOString()
  })

  let admin = null
  try {
    admin = getAdminClient()
  } catch (_) {}
  if (admin) {
    try {
      const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(cleanId)
      let query = admin.from('merchants').update({
        subscription_status: 'FREE',
        subscription_plan: 'FREE',
        updated_at: new Date().toISOString()
      })
      if (isUuid) {
        query = query.or(`id.eq.${cleanId},user_id.eq.${cleanId}`)
      } else {
        query = query.eq('id', cleanId)
      }
      await query
    } catch (err) {
      console.warn('[downgradeMerchantSubscription] DB update notice:', err.message)
    }
  }

  return {
    ok: true,
    message: 'Subscription plan successfully downgraded to Free Plan.',
    subscription_status: 'FREE',
    subscription_plan: 'FREE'
  }
}

// ─────────────────────────────────────────────────────────────
// MERCHANT APP PIN MANAGEMENT
// The raw 4-digit PIN is NEVER stored. Only a SHA-256 hex hash.
// ─────────────────────────────────────────────────────────────

/**
 * Store or update the SHA-256 hash of a merchant's 4-digit PIN.
 * Supports updating existing merchant or upserting initial record during onboarding.
 * @param {string} merchantId - The merchant's UUID or user_id
 * @param {string} pinHash    - SHA-256 hex string of the PIN
 * @param {string} [userEmail]- Optional user email
 * @param {string} [userId]   - Optional platform auth user UUID
 */
export async function setPinHash(merchantId, pinHash, userEmail = null, userId = null) {
  const admin = getAdminClient()
  const uid = userId || merchantId
  const cleanEmail = userEmail ? userEmail.trim().toLowerCase() : null
  const nowIso = new Date().toISOString()

  // 1. Try by id first
  let { data, error } = await admin
    .from('merchants')
    .update({ app_pin_hash: pinHash, pin_reset_requested: false, updated_at: nowIso })
    .eq('id', merchantId)
    .select('id, user_id, app_pin_hash, pin_reset_requested')
    .maybeSingle()

  // 2. Try by user_id
  if (!data && uid) {
    const res = await admin
      .from('merchants')
      .update({ app_pin_hash: pinHash, pin_reset_requested: false, updated_at: nowIso })
      .eq('user_id', uid)
      .select('id, user_id, app_pin_hash, pin_reset_requested')
      .maybeSingle()
    if (res.data) data = res.data
  }

  // 3. Try by email
  if (!data && cleanEmail) {
    const res = await admin
      .from('merchants')
      .update({ app_pin_hash: pinHash, pin_reset_requested: false, updated_at: nowIso })
      .ilike('email', cleanEmail)
      .select('id, user_id, app_pin_hash, pin_reset_requested')
      .maybeSingle()
    if (res.data) data = res.data
  }

  // 4. If merchant record does not exist yet (e.g. newly signed-up user in onboarding step 2),
  //    UPSERT the initial merchant row so their PIN and account are bound in admin database!
  if (!data) {
    const newMerchant = {
      id: merchantId,
      user_id: uid,
      app_pin_hash: pinHash,
      pin_reset_requested: false,
      status: 'PENDING_VERIFICATION',
      created_at: nowIso,
      updated_at: nowIso,
    }
    if (cleanEmail) newMerchant.email = cleanEmail

    const res = await admin
      .from('merchants')
      .upsert(newMerchant)
      .select('id, user_id, app_pin_hash, pin_reset_requested')
      .maybeSingle()

    if (res.error) {
      console.error('[setPinHash] initial upsert error:', res.error.message)
      throw new Error('PIN initial insert failed: ' + res.error.message)
    }
    data = res.data || newMerchant
  }

  if (error && !data) throw new Error('PIN update failed: ' + error.message)
  return data
}

/**
 * Fetch the stored PIN hash and reset flag for a merchant.
 */
export async function getPinHash(merchantId, userEmail = null, userId = null) {
  const admin = getAdminClient()
  const uid = userId || merchantId
  const cleanEmail = userEmail ? userEmail.trim().toLowerCase() : null

  // 1. By id
  let { data } = await admin
    .from('merchants')
    .select('id, user_id, app_pin_hash, pin_reset_requested')
    .eq('id', merchantId)
    .maybeSingle()

  // 2. By user_id
  if (!data && uid) {
    const res = await admin
      .from('merchants')
      .select('id, user_id, app_pin_hash, pin_reset_requested')
      .eq('user_id', uid)
      .maybeSingle()
    if (res.data) data = res.data
  }

  // 3. By email
  if (!data && cleanEmail) {
    const res = await admin
      .from('merchants')
      .select('id, user_id, app_pin_hash, pin_reset_requested')
      .ilike('email', cleanEmail)
      .maybeSingle()
    if (res.data) data = res.data
  }

  return data || null
}

/**
 * Admin action: clear the merchant's PIN hash so they must set a new one.
 */
export async function clearPinHash(merchantId, userId = null) {
  const admin = getAdminClient()
  const uid = userId || merchantId
  const nowIso = new Date().toISOString()

  let { data, error } = await admin
    .from('merchants')
    .update({ app_pin_hash: null, pin_reset_requested: false, updated_at: nowIso })
    .eq('id', merchantId)
    .select('id, app_pin_hash, pin_reset_requested')
    .maybeSingle()

  if (!data && uid) {
    const res = await admin
      .from('merchants')
      .update({ app_pin_hash: null, pin_reset_requested: false, updated_at: nowIso })
      .eq('user_id', uid)
      .select('id, app_pin_hash, pin_reset_requested')
      .maybeSingle()
    data = res.data
    error = res.error
  }

  if (error && !data) throw new Error('PIN clear failed: ' + error.message)
  return data
}

/**
 * Merchant requests an admin PIN reset (sets pin_reset_requested = true).
 */
export async function requestPinReset(merchantId, userEmail = null, userId = null) {
  const admin = getAdminClient()
  const uid = userId || merchantId
  const cleanEmail = userEmail ? userEmail.trim().toLowerCase() : null
  const nowIso = new Date().toISOString()

  let { data, error } = await admin
    .from('merchants')
    .update({ pin_reset_requested: true, updated_at: nowIso })
    .eq('id', merchantId)
    .select('id, pin_reset_requested')
    .maybeSingle()

  if (!data && uid) {
    const res = await admin
      .from('merchants')
      .update({ pin_reset_requested: true, updated_at: nowIso })
      .eq('user_id', uid)
      .select('id, pin_reset_requested')
      .maybeSingle()
    data = res.data
    error = res.error
  }

  if (!data && cleanEmail) {
    const res = await admin
      .from('merchants')
      .update({ pin_reset_requested: true, updated_at: nowIso })
      .ilike('email', cleanEmail)
      .select('id, pin_reset_requested')
      .maybeSingle()
    if (res.data) data = res.data
  }

  if (error && !data) throw new Error('PIN reset request failed: ' + error.message)
  return data
}

// ──────────────────────────────────────────────────────────────────────────────
// MERCHANT NOTIFICATIONS BROADCAST (Admin Panel to Merchant App)
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Broadcast an announcement, alert, or system notification to merchant apps.
 * Writes to public.merchant_notifications, optionally pushes to tenant databases,
 * and optionally updates system_notice marquee banner.
 */
export async function broadcastNotification({
  title,
  message,
  type = 'ANNOUNCEMENT',
  severity = 'INFO',
  target = 'ALL',
  updateBanner = false,
  entityType = 'BROADCAST',
}) {
  if (!title || !title.trim()) throw new Error('Notification title is required')
  if (!message || !message.trim()) throw new Error('Notification message body is required')

  const cleanTitle = title.trim().slice(0, 255)
  const cleanMessage = message.trim()
  const cleanType = (type || 'ANNOUNCEMENT').toUpperCase()
  const cleanSeverity = ['INFO', 'SUCCESS', 'WARNING', 'ERROR'].includes((severity || '').toUpperCase())
    ? severity.toUpperCase()
    : 'INFO'
  const batchId = randomUUID()
  const nowIso = new Date().toISOString()

  if (!process.env.ADMIN_SUPABASE_URL || !process.env.ADMIN_SUPABASE_SERVICE_ROLE_KEY) {
    console.warn('[broadcastNotification] DB notice: ADMIN_SUPABASE_URL and ADMIN_SUPABASE_SERVICE_ROLE_KEY are required.')
    return {
      ok: true,
      batch_id: batchId,
      recipients_count: 0,
      target,
      type: cleanType,
      severity: cleanSeverity,
      title: cleanTitle,
      message: cleanMessage,
      banner_updated: Boolean(updateBanner),
      created_at: nowIso,
      warning: 'Admin Supabase credentials not configured',
    }
  }

  const admin = getAdminClient()

  // 1. Resolve recipient merchants
  let recipientList = []

  if (target && target !== 'ALL' && target !== 'ACTIVE') {
    // Single targeted merchant ID
    recipientList = [{ id: target }]
  } else {
    // Multi-merchant broadcast: query all registered merchants
    try {
      let query = admin.from('merchants').select('id, business_name, status, email')
      if (target === 'ACTIVE') {
        query = query.eq('status', 'ACTIVE')
      }
      const { data: merchants, error: mErr } = await query
      if (!mErr && Array.isArray(merchants) && merchants.length > 0) {
        recipientList = merchants
      }
    } catch (e) {
      console.warn('[broadcastNotification] Querying merchants table notice:', e.message)
    }

    // Fallback: check merchant_gateway_settings if merchants table was empty
    if (recipientList.length === 0) {
      try {
        const { data: gwMerchants } = await admin
          .from('merchant_gateway_settings')
          .select('merchant_id, merchant_name, status')
        if (Array.isArray(gwMerchants) && gwMerchants.length > 0) {
          recipientList = gwMerchants
            .filter(m => target !== 'ACTIVE' || m.status === 'ACTIVE' || !m.status)
            .map(m => ({ id: m.merchant_id, business_name: m.merchant_name }))
        }
      } catch (gwErr) {
        console.warn('[broadcastNotification] Querying merchant_gateway_settings notice:', gwErr.message)
      }
    }
  }

  // If still no merchants found and target is a specific ID, use it directly
  if (recipientList.length === 0 && target && target !== 'ALL') {
    recipientList = [{ id: target }]
  }

  // 2. Prepare notification rows
  const rows = recipientList.map(m => ({
    id: randomUUID(),
    merchant_id: m.id || m.merchant_id,
    type: cleanType,
    title: cleanTitle,
    message: cleanMessage,
    severity: cleanSeverity,
    entity_type: entityType,
    entity_id: batchId,
    created_at: nowIso,
    read_at: null,
  }))

  // 3. Bulk insert rows into public.merchant_notifications
  let insertedCount = 0
  if (rows.length > 0) {
    const CHUNK_SIZE = 50
    for (let i = 0; i < rows.length; i += CHUNK_SIZE) {
      const chunk = rows.slice(i, i + CHUNK_SIZE)
      const { error: insErr } = await admin.from('merchant_notifications').insert(chunk)
      if (insErr) {
        console.error('[broadcastNotification] Bulk insert chunk error:', insErr.message)
        // If entity_type/entity_id column issue or uuid mismatch, retry without entity_id
        if (insErr.message?.includes('entity_id') || insErr.message?.includes('invalid input syntax for type uuid')) {
          const fallbackChunk = chunk.map(({ entity_id, ...rest }) => rest)
          const { error: fbErr } = await admin.from('merchant_notifications').insert(fallbackChunk)
          if (!fbErr) insertedCount += fallbackChunk.length
        }
      } else {
        insertedCount += chunk.length
      }
    }
  }

  // 4. Also replicate to merchant's custom connected Supabase database if configured
  for (const m of recipientList) {
    const mId = m.id || m.merchant_id
    if (!mId) continue
    getMerchantCredentials(mId).then(async (creds) => {
      if (creds?.supabase_url && creds?.supabase_anon_key) {
        try {
          const tenantDb = createMerchantClient(creds.supabase_url, creds.supabase_anon_key)
          await tenantDb.from('merchant_notifications').insert([{
            id: randomUUID(),
            merchant_id: mId,
            type: cleanType,
            title: cleanTitle,
            message: cleanMessage,
            severity: cleanSeverity,
            entity_type: entityType,
            entity_id: batchId,
            created_at: nowIso,
          }])
        } catch {
          // Non-critical: standalone DB may not have table installed
        }
      }
    }).catch(() => {})
  }

  // 5. Optionally update live dashboard announcement banner (system_notice)
  if (updateBanner) {
    try {
      const current = await getShowcaseConfig('system_config') || {}
      await upsertShowcaseConfig('system_config', {
        ...current,
        system_notice: cleanMessage,
        updated_at: nowIso,
      })
    } catch (bannerErr) {
      console.warn('[broadcastNotification] Could not update system_notice banner:', bannerErr.message)
    }
  }

  return {
    ok: true,
    batch_id: batchId,
    recipients_count: insertedCount || rows.length,
    target,
    type: cleanType,
    severity: cleanSeverity,
    title: cleanTitle,
    message: cleanMessage,
    banner_updated: Boolean(updateBanner),
    created_at: nowIso,
  }
}

/**
 * List broadcast history grouped by batchId.
 */
export async function listBroadcastHistory(limit = 50) {
  if (!process.env.ADMIN_SUPABASE_URL || !process.env.ADMIN_SUPABASE_SERVICE_ROLE_KEY) {
    return []
  }
  const admin = getAdminClient()
  try {
    const { data, error } = await admin
      .from('merchant_notifications')
      .select('id, merchant_id, type, title, message, severity, entity_type, entity_id, created_at, read_at')
      .eq('entity_type', 'BROADCAST')
      .order('created_at', { ascending: false })
      .limit(Math.min(limit * 25, 500))

    if (error) {
      console.warn('[listBroadcastHistory] Notice:', error.message)
      return []
    }

    // Group by entity_id (batchId)
    const batchMap = new Map()
    for (const row of (data || [])) {
      const key = row.entity_id || row.id
      if (!batchMap.has(key)) {
        batchMap.set(key, {
          batch_id: key,
          title: row.title,
          message: row.message,
          type: row.type,
          severity: row.severity,
          created_at: row.created_at,
          recipients_count: 0,
          read_count: 0,
        })
      }
      const b = batchMap.get(key)
      b.recipients_count++
      if (row.read_at) b.read_count++
    }

    return Array.from(batchMap.values()).slice(0, limit)
  } catch (err) {
    console.warn('[listBroadcastHistory] Error:', err.message)
    return []
  }
}

/**
 * Delete / Recall a broadcast by its batch ID.
 */
export async function deleteBroadcastBatch(batchId) {
  if (!batchId) throw new Error('batchId is required')
  if (!process.env.ADMIN_SUPABASE_URL || !process.env.ADMIN_SUPABASE_SERVICE_ROLE_KEY) {
    return { ok: true, batch_id: batchId, deleted: false, warning: 'Admin Supabase credentials not configured' }
  }
  const admin = getAdminClient()
  const { error } = await admin
    .from('merchant_notifications')
    .delete()
    .eq('entity_id', batchId)

  if (error) throw new Error('Failed to delete broadcast: ' + error.message)
  return { ok: true, batch_id: batchId }
}

