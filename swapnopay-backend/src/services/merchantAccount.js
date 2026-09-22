import { getAdminClient } from './adminSupabase.js'

export const isUuid = value => /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value || '')
export const databaseOrigin = value => {
  try { return new URL(value).origin.toLowerCase() } catch { return null }
}

export function requireData(result, operation) {
  if (result.error) throw new Error(`${operation}: ${result.error.message}`)
  return result.data
}

// Only call with the identity returned by platform Auth, never an unverified email.
export async function lookupMerchantInAdminDb(email, userId, admin = getAdminClient()) {
  if (!isUuid(userId)) throw new Error('A platform Auth user ID is required')
  const cleanEmail = (email || '').trim().toLowerCase()
  let merchant = requireData(await admin.from('merchants').select('*')
    .eq('user_id', userId).maybeSingle(), 'Load merchant owner')
  if (!merchant) {
    merchant = requireData(await admin.from('merchants').select('*')
      .eq('id', userId).maybeSingle(), 'Load legacy merchant')
    if (merchant?.user_id && merchant.user_id !== userId) throw new Error('Merchant ownership mismatch')
  }
  if (!merchant && cleanEmail) {
    const matches = requireData(await admin.from('merchants').select('*')
      .ilike('email', cleanEmail.replace(/[\\%_]/g, '\\$&')).limit(2), 'Load merchant email') || []
    if (matches.length > 1) throw new Error('Multiple merchant records match this account; admin must reconcile them')
    merchant = matches[0] || null
    if (merchant?.user_id && merchant.user_id !== userId) throw new Error('Merchant ownership mismatch')
  }
  // Claim only an unowned legacy row using an email already verified by Auth.
  if (merchant && !merchant.user_id) {
    merchant = requireData(await admin.from('merchants').update({ user_id: userId })
      .eq('id', merchant.id).is('user_id', null).select('*').single(), 'Link merchant owner')
  }
  const merchantId = merchant?.id || userId
  const gateway = requireData(await admin.from('merchant_gateway_settings').select('*')
    .eq('merchant_id', merchantId).maybeSingle(), 'Load merchant database settings')
  let connection = requireData(await admin.from('supabase_connections').select('*')
    .eq('user_id', userId).maybeSingle(), 'Load account connection')
  if (!connection && merchantId !== userId) {
    connection = requireData(await admin.from('supabase_connections').select('*')
      .eq('user_id', merchantId).maybeSingle(), 'Load legacy connection')
  }
  const platformOrigin = databaseOrigin(process.env.ADMIN_SUPABASE_URL)
  const candidates = [
    { url: gateway?.supabase_url, key: gateway?.supabase_anon_key },
    { url: connection?.project_url, key: connection?.publishable_key },
  ]
  const own = candidates.find(({ url, key }) => key && databaseOrigin(url) && databaseOrigin(url) !== platformOrigin)
  const name = merchant?.business_name || gateway?.merchant_name || ''
  const placeholder = /^(my store|my business|google user|facebook user|demo store|business setup required)$/i.test(name.trim())
  const hasValidName = Boolean(name.trim() && !placeholder)
  const onboarded = Boolean(
    merchant?.onboarded_at ||
    own ||
    (merchant?.status === 'ACTIVE' && hasValidName) ||
    (hasValidName && (merchant?.phone || merchant?.email))
  )
  if (onboarded && merchant && !merchant.onboarded_at) {
    try {
      const query = admin.from('merchants')
      if (typeof query.update === 'function') {
        const chain = query.update({ onboarded_at: new Date().toISOString() })
        if (chain && typeof chain.eq === 'function') {
          const req = chain.eq('id', merchant.id)
          if (req && typeof req.catch === 'function') req.catch(() => {})
        }
      }
    } catch (_) {}
  }

  let kycStatus = merchant?.kyc_status || 'UNVERIFIED'
  let nidNumber = merchant?.nid_number || ''
  let nidFront = merchant?.nid_front_url || ''
  let nidBack = merchant?.nid_back_url || ''
  let facePhoto = merchant?.face_photo_url || ''
  let rejectionReason = merchant?.kyc_rejection_reason || ''

  // Query merchant_kyc_submissions to ensure previous NID verification is restored on login
  try {
    const { data: latestKyc } = await admin
      .from('merchant_kyc_submissions')
      .select('*')
      .or(`merchant_id.eq.${merchantId},merchant_id.eq.${userId}`)
      .order('created_at', { ascending: false })
      .limit(1)
      .maybeSingle()

    if (latestKyc) {
      if (latestKyc.status === 'APPROVED' || latestKyc.status === 'VERIFIED') {
        kycStatus = 'VERIFIED'
      } else if (latestKyc.status === 'PENDING' && (!kycStatus || kycStatus === 'UNVERIFIED')) {
        kycStatus = 'PENDING'
      } else if (latestKyc.status === 'REJECTED' && (!kycStatus || kycStatus === 'UNVERIFIED')) {
        kycStatus = 'REJECTED'
        if (latestKyc.rejection_reason) rejectionReason = latestKyc.rejection_reason
      }
      if (!nidNumber && latestKyc.nid_number) nidNumber = latestKyc.nid_number
      if (!nidFront && latestKyc.nid_front_url) nidFront = latestKyc.nid_front_url
      if (!nidBack && latestKyc.nid_back_url) nidBack = latestKyc.nid_back_url
      if (!facePhoto && latestKyc.face_photo_url) facePhoto = latestKyc.face_photo_url
    }
  } catch (_) {
    // Non-blocking fallback
  }

  if (kycStatus === 'APPROVED') kycStatus = 'VERIFIED'

  return {
    exists: Boolean(merchant || gateway || connection), isOnboarded: onboarded, isNewUser: !onboarded,
    merchantId,
    merchant: {
      id: merchantId, user_id: userId, business_name: name, email: merchant?.email || cleanEmail,
      phone: merchant?.phone || '', business_type: merchant?.business_type || 'Retail Store',
      photo_url: facePhoto || merchant?.photo_url || gateway?.merchant_logo_url || '',
      account_holder: merchant?.account_holder || name, status: merchant?.status || 'PENDING_VERIFICATION',
      kyc_status: kycStatus, kyc_rejection_reason: rejectionReason,
      nid_number: nidNumber, nid_front_url: nidFront, nid_back_url: nidBack,
    },
    database: { has_own_database: Boolean(own), supabase_url: own?.url || '', supabase_anon_key: own?.key || '', project_ref: connection?.selected_project_ref || '' },
  }
}

export async function requirePlatformUser(req, res, next) {
  if (req.platformUser) return next()
  if (req.isAdmin) {
    if (!req.platformUser) req.platformUser = { id: 'admin_secret', email: 'admin@swapnopay.top' }
    return next()
  }
  const xAdminSecret = req.headers['x-admin-secret']
  const adminSecret = process.env.ADMIN_SECRET
  if (xAdminSecret && adminSecret && xAdminSecret === adminSecret) {
    req.isAdmin = true
    req.platformUser = { id: 'admin_secret', email: 'admin@swapnopay.top' }
    return next()
  }
  const token = req.headers.authorization?.match(/^Bearer\s+(.+)$/i)?.[1]
  if (!token) return res.status(401).json({ error: 'Sign in to your platform account first' })
  try {
    const { data, error } = await getAdminClient().auth.getUser(token)
    if (error || !data?.user?.id) {
      return res.status(401).json({ error: 'A valid, verified platform session is required' })
    }
    req.platformUser = data.user
    next()
  } catch (error) {
    res.status(503).json({ error: 'Platform authentication is unavailable' })
  }
}

export async function requirePlatformMerchant(req, res, next) {
  try {
    const account = await lookupMerchantInAdminDb(req.platformUser.email, req.platformUser.id)
    if (!account.exists) return res.status(409).json({ error: 'Save your business profile before continuing' })
    req.platformAccount = account
    next()
  } catch (error) {
    res.status(503).json({ error: error.message })
  }
}
