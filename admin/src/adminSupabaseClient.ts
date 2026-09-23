// Admin Panel — Admin Supabase Client
// This connects to the PLATFORM OWNER'S Supabase project (NOT merchant databases).
// Use this for: gateway_config, platform_api_keys, payment_events, admin_users.

import { supabase, SUPABASE_URL, SUPABASE_ANON_KEY, isSupabaseConfigured } from './supabaseClient'
export const adminSupabase = supabase
export const ADMIN_SUPABASE_URL = SUPABASE_URL
export const ADMIN_SUPABASE_ANON_KEY = SUPABASE_ANON_KEY
export const isAdminSupabaseConfigured = isSupabaseConfigured

export async function reviewMerchantIdentity(merchantId: string, action: 'APPROVE' | 'REJECT', reason = '') {
  const status = action === 'REJECT' ? 'REJECTED' : 'VERIFIED'
  const nowIso = new Date().toISOString()
  const updates: Record<string, any> = {
    kyc_status: status,
    kyc_reviewed_at: nowIso,
    kyc_reviewed_by: 'ADMIN',
    kyc_rejection_reason: action === 'REJECT' ? (reason || 'Documents did not meet criteria') : null,
    updated_at: nowIso,
  }
  if (status === 'VERIFIED') {
    updates.status = 'ACTIVE'
    updates.trial_ends_at = new Date(Date.now() + 90 * 86400000).toISOString()
  }

  // 1. Attempt backend API first if available
  try {
    const { data: { session } } = await adminSupabase.auth.getSession()
    const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
    const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
    const headers: Record<string, string> = { 'Content-Type': 'application/json' }

    if (masterSecret) {
      headers['X-Admin-Secret'] = masterSecret
    } else if (session?.access_token) {
      headers['Authorization'] = `Bearer ${session.access_token}`
    }

    const response = await fetch(`${base.replace(/\/$/, '')}/v1/admin/kyc/${encodeURIComponent(merchantId)}/review`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ action, reason }),
    })
    if (response.ok) {
      const result = await response.json()
      if (result.ok && result.merchant) {
        return result.merchant
      }
    }
  } catch (fetchErr) {
    console.warn('[reviewMerchantIdentity] Backend fetch notice (using direct Supabase update):', fetchErr)
  }

  // 2. Direct Supabase update fallback (guaranteed to execute even without backend server)
  const runDirectUpdate = async (payload: Record<string, any>) => {
    let res = await adminSupabase
      .from('merchants')
      .update(payload)
      .eq('id', merchantId)
      .select('*')
      .maybeSingle()

    if (!res.data && !res.error) {
      res = await adminSupabase
        .from('merchants')
        .update(payload)
        .eq('user_id', merchantId)
        .select('*')
        .maybeSingle()
    }
    return res
  }

  let updateFields = { ...updates }
  let { data: updatedMerchant, error: updateError } = await runDirectUpdate(updateFields)

  // Resilient fallback: if column doesn't exist in Supabase PostgREST schema cache (e.g. trial_ends_at)
  if (updateError && (updateError.message?.includes('trial_ends_at') || updateError.message?.includes('schema cache'))) {
    console.warn('[reviewMerchantIdentity] Schema cache column mismatch, retrying without trial_ends_at:', updateError.message)
    delete updateFields.trial_ends_at
    const retry = await runDirectUpdate(updateFields)
    updatedMerchant = retry.data
    updateError = retry.error
  }

  if (updateError) throw new Error('KYC review was not saved: ' + updateError.message)
  if (!updatedMerchant) {
    // If not found, return local representation with updates
    updatedMerchant = { id: merchantId, ...updateFields }
  }

  // Update audit trail in merchant_kyc_submissions
  try {
    await adminSupabase
      .from('merchant_kyc_submissions')
      .update({
        status: status === 'VERIFIED' ? 'APPROVED' : 'REJECTED',
        reviewed_at: nowIso,
        reviewed_by: 'ADMIN',
        rejection_reason: updates.kyc_rejection_reason,
        updated_at: nowIso,
      })
      .eq('merchant_id', updatedMerchant.id || merchantId)
  } catch (_) {}

  return updatedMerchant
}

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

export function formatKycImageUrl(url?: string | null): string | null {
  if (!url || typeof url !== 'string') return null
  const trimmed = url.trim()
  if (!trimmed || trimmed === 'null' || trimmed === 'undefined') return null

  // 1. Data URI
  if (trimmed.startsWith('data:image')) return trimmed

  // 2. Raw base64 string
  if (trimmed.startsWith('/9j/') || trimmed.startsWith('iVBORw0KGgo') || (trimmed.length > 200 && !trimmed.includes('/') && !trimmed.startsWith('http'))) {
    return `data:image/jpeg;base64,${trimmed}`
  }

  // 3. Absolute HTTP/HTTPS URLs
  if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
    return trimmed
  }

  // 4. Backend local upload path
  if (trimmed.startsWith('uploads/') || trimmed.startsWith('/uploads/')) {
    const backend = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
    return `${backend.replace(/\/$/, '')}/${trimmed.replace(/^\//, '')}`
  }

  // 5. Supabase storage object path (e.g. "kyc/merchant_123/front.jpg" or "merchant_123/front.jpg")
  const cleanPath = trimmed.replace(/^\/?(kyc-documents\/)?/, '')
  const supabaseUrl = ADMIN_SUPABASE_URL || 'https://tldubojeokgyoclxnzkb.supabase.co'
  return `${supabaseUrl.replace(/\/$/, '')}/storage/v1/object/public/kyc-documents/${cleanPath}`
}

export const GATEWAY_CONFIG_ID = '00000000-0000-0000-0000-000000000001'

export const RADYMATE_GALLERY_BUCKET = 'radymate-gallery'
export const RADYMATE_GALLERY_KEY = 'radymate_gallery'

export interface RadymateGalleryItem {
  title: string
  caption: string
  image: string
  uploaded_at?: string
  source?: 'storage' | 'fallback'
}

export interface RadymateGalleryConfig {
  items: RadymateGalleryItem[]
  updated_at?: string
}

export const DEFAULT_RADYMATE_GALLERY: RadymateGalleryItem[] = [
  {
    title: 'Launch Dashboard',
    caption: 'Storefront overview and order performance',
    image: 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80',
    source: 'fallback'
  },
  {
    title: 'Premium Storefront',
    caption: 'Professional eCommerce front-end showcase',
    image: 'https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=1200&q=80',
    source: 'fallback'
  },
  {
    title: 'Checkout Flow',
    caption: 'Fast and trusted conversion experience',
    image: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
    source: 'fallback'
  },
  {
    title: 'Merchant Console',
    caption: 'Insights and analytics dashboard for growth',
    image: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
    source: 'fallback'
  }
]

/** Fetch the singleton gateway config row */
export async function fetchGatewayConfig() {
  try {
    const { data, error } = await adminSupabase
      .from('gateway_config')
      .select('*')
      .eq('id', GATEWAY_CONFIG_ID)
      .single()
    if (!error && data) return data
  } catch (err: any) {
    console.warn('[adminSupabaseClient] Direct gateway_config notice:', err.message)
  }

  // Backend fallback
  try {
    const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
    const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
    const headers: Record<string, string> = { 'Accept': 'application/json' }
    if (masterSecret) headers['X-Admin-Secret'] = masterSecret
    const { data: { session } } = await adminSupabase.auth.getSession()
    if (session?.access_token) headers['Authorization'] = `Bearer ${session.access_token}`

    const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/gateway-settings`, { headers })
    if (res.ok) {
      const json = await res.json()
      if (json.ok && json.config) return json.config
    }
  } catch (bkErr) {
    console.warn('[adminSupabaseClient] Backend gateway-settings notice:', bkErr)
  }
  return null
}

/** Update the singleton gateway config row */
export async function updateGatewayConfig(updates: Record<string, unknown>) {
  try {
    const { data, error } = await adminSupabase
      .from('gateway_config')
      .update({ ...updates, updated_at: new Date().toISOString() })
      .eq('id', GATEWAY_CONFIG_ID)
      .select('*')
      .single()
    if (!error && data) return data
  } catch (err: any) {
    console.warn('[adminSupabaseClient] Direct updateGatewayConfig notice:', err.message)
  }

  // Backend fallback
  try {
    const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
    const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
    const headers: Record<string, string> = { 'Content-Type': 'application/json', 'Accept': 'application/json' }
    if (masterSecret) headers['X-Admin-Secret'] = masterSecret
    const { data: { session } } = await adminSupabase.auth.getSession()
    if (session?.access_token) headers['Authorization'] = `Bearer ${session.access_token}`

    const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/gateway-settings`, {
      method: 'POST',
      headers,
      body: JSON.stringify(updates),
    })
    if (res.ok) {
      const json = await res.json()
      if (json.ok && json.config) return json.config
    }
  } catch (bkErr) {
    console.warn('[adminSupabaseClient] Backend update gateway-settings notice:', bkErr)
  }
  return updates
}

/** Fetch recent platform payment events */
export async function fetchPaymentEvents(limit = 50) {
  try {
    const { data, error } = await adminSupabase
      .from('payment_events')
      .select('*')
      .order('recorded_at', { ascending: false })
      .limit(limit)
    if (!error && data) return data
  } catch (err: any) {
    console.warn('[adminSupabaseClient] Direct payment_events notice:', err.message)
  }

  // Backend fallback
  try {
    const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
    const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
    const headers: Record<string, string> = { 'Accept': 'application/json' }
    if (masterSecret) headers['X-Admin-Secret'] = masterSecret
    const { data: { session } } = await adminSupabase.auth.getSession()
    if (session?.access_token) headers['Authorization'] = `Bearer ${session.access_token}`

    const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/payment-events?limit=${limit}`, { headers })
    if (res.ok) {
      const json = await res.json()
      if (json.ok && Array.isArray(json.events)) return json.events
    }
  } catch (bkErr) {
    console.warn('[adminSupabaseClient] Backend payment-events notice:', bkErr)
  }
  return []
}

/** Fetch all API key records (no digest exposed) */
export async function fetchApiKeys() {
  const { data, error } = await adminSupabase
    .from('platform_api_keys')
    .select('id,merchant_id,merchant_name,label,key_preview,revoked,revoked_at,created_at')
    .order('created_at', { ascending: false })
  if (error) throw new Error('Failed to fetch API keys: ' + error.message)
  return data || []
}

/** Generate a dynamic API key via backend API (attaches session or master secret) */
export async function generateMerchantApiKey(merchantId: string, merchantName = 'Merchant', label = 'Admin Generated Dynamic Key') {
  const { data: { session } } = await adminSupabase.auth.getSession()
  const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
  const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
  const headers: Record<string, string> = { 'Content-Type': 'application/json' }

  if (masterSecret) {
    headers['X-Admin-Secret'] = masterSecret
  } else if (session?.access_token) {
    headers['Authorization'] = `Bearer ${session.access_token}`
  }

  const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/keys/generate`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      merchant_id: merchantId,
      merchant_name: merchantName,
      label,
    }),
  })

  const data = await res.json()
  if (!res.ok) throw new Error(data.error || 'Failed to generate API key')
  return data
}

/** Revoke an API key via backend API */
export async function revokeMerchantApiKey(keyId: string) {
  const { data: { session } } = await adminSupabase.auth.getSession()
  const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
  const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
  const headers: Record<string, string> = { 'Content-Type': 'application/json' }

  if (masterSecret) {
    headers['X-Admin-Secret'] = masterSecret
  } else if (session?.access_token) {
    headers['Authorization'] = `Bearer ${session.access_token}`
  }

  const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/keys/revoke`, {
    method: 'POST',
    headers,
    body: JSON.stringify({ key_id: keyId }),
  })

  const data = await res.json()
  if (!res.ok) throw new Error(data.error || 'Failed to revoke API key')
  return data
}

export async function fetchRadymateGalleryConfig(): Promise<RadymateGalleryConfig> {
  const { data, error } = await adminSupabase
    .from('showcase_config')
    .select('value')
    .eq('key', RADYMATE_GALLERY_KEY)
    .maybeSingle()

  if (error && error.code !== 'PGRST116') {
    throw new Error('Failed to fetch Radymate gallery config: ' + error.message)
  }

  const gallery = data?.value
  const items = Array.isArray(gallery?.items) && gallery.items.length > 0 ? gallery.items : DEFAULT_RADYMATE_GALLERY
  return {
    items: items.map((item: RadymateGalleryItem) => ({
      ...item,
      source: item.source || 'fallback'
    })),
    updated_at: gallery?.updated_at || new Date().toISOString()
  }
}

export async function upsertShowcaseConfig(key: string, value: any) {
  const now = new Date().toISOString()
  try {
    const { data: existing } = await adminSupabase
      .from('showcase_config')
      .select('id, key')
      .eq('key', key)
      .maybeSingle()

    if (existing) {
      const { data, error } = await adminSupabase
        .from('showcase_config')
        .update({ value, updated_at: now })
        .eq('key', key)
        .select('*')
        .single()
      if (!error) return data
      if (error && error.code !== 'PGRST116') throw error
    }

    const { data, error } = await adminSupabase
      .from('showcase_config')
      .upsert({ key, value, updated_at: now }, { onConflict: 'key' })
      .select('*')
      .single()

    if (!error) return data

    // Fallback: If upsert hit a duplicate key conflict, update the existing row directly
    const { data: updateData, error: updateError } = await adminSupabase
      .from('showcase_config')
      .update({ value, updated_at: now })
      .eq('key', key)
      .select('*')
      .single()

    if (updateError) throw updateError
    return updateData
  } catch (err: any) {
    throw new Error(err?.message || 'Database error')
  }
}

export async function saveRadymateGalleryConfig(items: RadymateGalleryItem[]) {
  const payload: RadymateGalleryConfig = {
    items: items.map((item) => ({ ...item, source: item.source || 'storage' })),
    updated_at: new Date().toISOString()
  }

  try {
    return await upsertShowcaseConfig(RADYMATE_GALLERY_KEY, payload)
  } catch (error: any) {
    throw new Error('Failed to save Radymate gallery: ' + error.message)
  }
}

export async function uploadRadymateGalleryImage(file: File, title: string, caption: string): Promise<RadymateGalleryItem> {
  if (!file) throw new Error('Please choose an image file first.')

  const safeName = file.name.replace(/[^a-zA-Z0-9._-]/g, '_')
  const objectPath = `radymate/${Date.now()}-${Math.random().toString(36).slice(2, 10)}-${safeName}`

  const { data: uploadData, error: uploadError } = await adminSupabase.storage
    .from(RADYMATE_GALLERY_BUCKET)
    .upload(objectPath, file, {
      cacheControl: '3600',
      upsert: false,
      contentType: file.type || 'image/jpeg'
    })

  if (uploadError) {
    throw new Error('Supabase upload failed: ' + uploadError.message)
  }

  const { data: publicUrlData } = adminSupabase.storage
    .from(RADYMATE_GALLERY_BUCKET)
    .getPublicUrl(uploadData?.path || objectPath)

  const imageUrl = publicUrlData?.publicUrl
  if (!imageUrl) {
    throw new Error('Uploaded image is missing a public URL. Make sure the bucket is public.')
  }

  return {
    title: title.trim() || 'Radymate storefront preview',
    caption: caption.trim() || 'Premium layout preview',
    image: imageUrl,
    uploaded_at: new Date().toISOString(),
    source: 'storage'
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// 3D App Showcase Gallery & Landing Page Controls
// ──────────────────────────────────────────────────────────────────────────────

export const APP_GALLERY_KEY = 'app_gallery'
export const LANDING_PAGE_KEY = 'landing_page_config'

export interface AppGalleryWidget {
  label: string
  value: string
}

export interface AppGalleryItem {
  id: string
  badge: string
  title: string
  description: string
  image?: string
  screen_bg?: string
  widgets: AppGalleryWidget[]
  active: boolean
  highlight?: boolean
}

export interface AppGalleryConfig {
  section_title: string
  section_subtitle: string
  items: AppGalleryItem[]
  updated_at?: string
}

export const DEFAULT_APP_GALLERY: AppGalleryConfig = {
  section_title: 'See SwapnoPay in action.',
  section_subtitle: 'Explore the app experience built for modern businesses.',
  items: [
    {
      id: 'card-dashboard',
      badge: 'Dashboard',
      title: 'Executive Dashboard',
      description: 'Instant overview of sales, profit, and merchant health.',
      screen_bg: 'linear-gradient(135deg, #1e1b4b, #312e81 40%, #0f172a)',
      widgets: [
        { label: 'Sales', value: '৳ 28K' },
        { label: 'Orders', value: '214' },
        { label: 'Win', value: '92%' }
      ],
      active: true
    },
    {
      id: 'card-pos',
      badge: 'POS',
      title: 'POS & Billing',
      description: 'Fast billing, payment capture, and instant checkout.',
      screen_bg: 'linear-gradient(135deg, #111827, #1e293b 55%, #0f172a)',
      widgets: [
        { label: 'Cart', value: '৳ 1,250' },
        { label: 'Items', value: '3' },
        { label: 'Paid', value: 'Cash' }
      ],
      active: true
    },
    {
      id: 'card-ai-growth',
      badge: 'Popular',
      title: 'AI + Growth Engine',
      description: 'Sales intelligence and recommendation workflows.',
      screen_bg: 'linear-gradient(135deg, #1a1203, #2a1d0d 50%, #0f172a)',
      widgets: [
        { label: 'Profit', value: '৳ 9.6K' },
        { label: 'Match', value: '98%' },
        { label: 'AI', value: 'Ready' }
      ],
      active: true,
      highlight: true
    },
    {
      id: 'card-ledger',
      badge: 'Ledger',
      title: 'Digital Ledger',
      description: 'Track cashflow, supplier dues, and customer balances.',
      screen_bg: 'linear-gradient(135deg, #0f172a, #112236 45%, #0b1120)',
      widgets: [
        { label: 'Due', value: '৳ 11K' },
        { label: 'Clients', value: '24' },
        { label: 'Alerts', value: '5' }
      ],
      active: true
    },
    {
      id: 'card-copilot',
      badge: 'AI',
      title: 'Merchant Copilot',
      description: 'Actionable suggestions in Bangla or English.',
      screen_bg: 'linear-gradient(135deg, #111827, #0f172a 50%, #1f2937)',
      widgets: [
        { label: 'Sales', value: '৳ 48K' },
        { label: 'Trend', value: '2.1x' },
        { label: 'Advice', value: 'Yes' }
      ],
      active: true
    }
  ]
}

export interface LandingPageConfig {
  announcement_badge: string
  announcement_text: string
  hero_title: string
  hero_highlight: string
  hero_subtitle: string
  apk_download_url: string
  play_store_url: string
  web_portal_url: string
  docs_url: string
  demo_video_url: string
  metric_settlement: string
  metric_match_rate: string
  metric_uptime: string
  metric_merchants: string
  status_text: string
  updated_at?: string
}

export const DEFAULT_LANDING_PAGE_CONFIG: LandingPageConfig = {
  announcement_badge: 'NEXT-GEN BANGLADESH PAYMENT PLATFORM',
  announcement_text: 'PAYMENTS FOR BANGLADESH',
  hero_title: 'Payments.',
  hero_highlight: 'Reimagined.',
  hero_subtitle: 'Payment gateway automation, POS billing, inventory and digital ledger (ব্যবসা খাতা) for businesses in Bangladesh. Manage bKash, Nagad, Rocket and Upay payments in one platform.',
  apk_download_url: '/swapnopay-debug.apk',
  play_store_url: 'https://play.google.com/store/apps/details?id=com.example.lenden23',
  web_portal_url: 'https://swapnopay.top/portal.html',
  docs_url: 'https://swapnopay.top/docs.html',
  demo_video_url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
  metric_settlement: '2.4s',
  metric_match_rate: '99.8%',
  metric_uptime: '99.99%',
  metric_merchants: '12,400+',
  status_text: 'Android Version 1.0.0 • Offline Ready & Bank-Grade Encrypted',
}

export async function fetchAppGalleryConfig(): Promise<AppGalleryConfig> {
  const { data, error } = await adminSupabase
    .from('showcase_config')
    .select('value')
    .eq('key', APP_GALLERY_KEY)
    .maybeSingle()

  if (error && error.code !== 'PGRST116') {
    console.warn('[fetchAppGalleryConfig] error:', error.message)
  }

  const raw = data?.value
  if (raw && Array.isArray(raw.items) && raw.items.length > 0) {
    return {
      section_title: raw.section_title || DEFAULT_APP_GALLERY.section_title,
      section_subtitle: raw.section_subtitle || DEFAULT_APP_GALLERY.section_subtitle,
      items: raw.items,
      updated_at: raw.updated_at
    }
  }

  return DEFAULT_APP_GALLERY
}

export async function saveAppGalleryConfig(config: AppGalleryConfig) {
  const payload: AppGalleryConfig = {
    section_title: config.section_title.trim() || DEFAULT_APP_GALLERY.section_title,
    section_subtitle: config.section_subtitle.trim() || DEFAULT_APP_GALLERY.section_subtitle,
    items: config.items,
    updated_at: new Date().toISOString()
  }

  try {
    return await upsertShowcaseConfig(APP_GALLERY_KEY, payload)
  } catch (error: any) {
    throw new Error('Failed to save App Gallery config: ' + error.message)
  }
}

export async function fetchLandingPageConfig(): Promise<LandingPageConfig> {
  const { data, error } = await adminSupabase
    .from('showcase_config')
    .select('value')
    .eq('key', LANDING_PAGE_KEY)
    .maybeSingle()

  if (error && error.code !== 'PGRST116') {
    console.warn('[fetchLandingPageConfig] error:', error.message)
  }

  const raw = data?.value
  if (raw && typeof raw === 'object') {
    return {
      ...DEFAULT_LANDING_PAGE_CONFIG,
      ...raw,
      updated_at: raw.updated_at
    }
  }

  return DEFAULT_LANDING_PAGE_CONFIG
}

export async function saveLandingPageConfig(config: LandingPageConfig) {
  const payload: LandingPageConfig = {
    ...config,
    updated_at: new Date().toISOString()
  }

  try {
    return await upsertShowcaseConfig(LANDING_PAGE_KEY, payload)
  } catch (error: any) {
    throw new Error('Failed to save Landing Page config: ' + error.message)
  }
}

export async function uploadAppGalleryScreenshot(file: File, folder = 'app-showcase'): Promise<string> {
  if (!file) throw new Error('Please choose a screenshot image first.')

  const safeName = file.name.replace(/[^a-zA-Z0-9._-]/g, '_')
  const objectPath = `${folder}/${Date.now()}-${Math.random().toString(36).slice(2, 10)}-${safeName}`

  const { data: uploadData, error: uploadError } = await adminSupabase.storage
    .from(RADYMATE_GALLERY_BUCKET)
    .upload(objectPath, file, {
      cacheControl: '3600',
      upsert: false,
      contentType: file.type || 'image/jpeg'
    })

  if (uploadError) {
    throw new Error('Supabase storage upload failed: ' + uploadError.message)
  }

  const { data: publicUrlData } = adminSupabase.storage
    .from(RADYMATE_GALLERY_BUCKET)
    .getPublicUrl(uploadData?.path || objectPath)

  const imageUrl = publicUrlData?.publicUrl
  if (!imageUrl) {
    throw new Error('Uploaded image is missing a public URL. Ensure bucket permissions allow public read.')
  }

  return imageUrl
}

// ─────────────────────────────────────────────────────────────
// MERCHANT APP PIN MANAGEMENT (Admin Panel)
// ─────────────────────────────────────────────────────────────

/** Get PIN status for a merchant — reads app_pin_hash and pin_reset_requested */
export async function getMerchantPinStatus(merchantId: string): Promise<{
  pin_set: boolean
  pin_reset_requested: boolean
}> {
  let { data, error } = await adminSupabase
    .from('merchants')
    .select('app_pin_hash, pin_reset_requested')
    .eq('id', merchantId)
    .maybeSingle()

  if (!data) {
    const res = await adminSupabase
      .from('merchants')
      .select('app_pin_hash, pin_reset_requested')
      .eq('user_id', merchantId)
      .maybeSingle()
    if (res.data) {
      data = res.data
      error = null
    }
  }

  if (error) throw new Error('Failed to get PIN status: ' + error.message)
  return {
    pin_set: !!data?.app_pin_hash,
    pin_reset_requested: data?.pin_reset_requested ?? false,
  }
}

/** Admin: clear a merchant's PIN hash (forces merchant to set a new PIN on next login) */
export async function clearMerchantPin(merchantId: string): Promise<void> {
  const nowIso = new Date().toISOString()
  const { error } = await adminSupabase
    .from('merchants')
    .update({
      app_pin_hash: null,
      pin_reset_requested: true,
      updated_at: nowIso,
    })
    .or(`id.eq.${merchantId},user_id.eq.${merchantId}`)

  if (error) {
    const res1 = await adminSupabase
      .from('merchants')
      .update({ app_pin_hash: null, pin_reset_requested: true, updated_at: nowIso })
      .eq('id', merchantId)
    if (res1.error) {
      const res2 = await adminSupabase
        .from('merchants')
        .update({ app_pin_hash: null, pin_reset_requested: true, updated_at: nowIso })
        .eq('user_id', merchantId)
      if (res2.error) throw new Error('Failed to clear merchant PIN: ' + res2.error.message)
    }
  }
}

/** Admin: set pin_reset_requested = true (marks PIN for forced reset) */
export async function forceRequestPinReset(merchantId: string): Promise<void> {
  const nowIso = new Date().toISOString()
  const { error } = await adminSupabase
    .from('merchants')
    .update({
      pin_reset_requested: true,
      updated_at: nowIso,
    })
    .or(`id.eq.${merchantId},user_id.eq.${merchantId}`)

  if (error) {
    const res1 = await adminSupabase
      .from('merchants')
      .update({ pin_reset_requested: true, updated_at: nowIso })
      .eq('id', merchantId)
    if (res1.error) {
      const res2 = await adminSupabase
        .from('merchants')
        .update({ pin_reset_requested: true, updated_at: nowIso })
        .eq('user_id', merchantId)
      if (res2.error) throw new Error('Failed to set PIN reset flag: ' + res2.error.message)
    }
  }
}

// ─────────────────────────────────────────────────────────────
// MERCHANT NOTIFICATIONS BROADCAST (Admin Panel)
// ─────────────────────────────────────────────────────────────

export type NotificationType = 'ANNOUNCEMENT' | 'ALERT' | 'SYSTEM' | 'PROMOTION' | 'INFO'
export type NotificationSeverity = 'INFO' | 'SUCCESS' | 'WARNING' | 'ERROR'

export interface BroadcastPayload {
  title: string
  message: string
  type: NotificationType
  severity: NotificationSeverity
  target: 'ALL' | 'ACTIVE' | string
  updateBanner?: boolean
}

export interface BroadcastResult {
  ok: boolean
  batch_id: string
  recipients_count: number
  target: string
  type: string
  severity: string
  title: string
  message: string
  banner_updated?: boolean
  created_at: string
  warning?: string
}

export interface BroadcastHistoryItem {
  batch_id: string
  title: string
  message: string
  type: NotificationType
  severity: NotificationSeverity
  created_at: string
  recipients_count: number
  read_count: number
}

function getBackendBaseUrl(): string {
  const envUrl = (import.meta as any).env?.VITE_BACKEND_URL
  if (envUrl && typeof envUrl === 'string') return envUrl.replace(/\/$/, '')
  return 'https://api.swapnopay.top'
}

async function getAdminHeaders(): Promise<Record<string, string>> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' }
  const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
  if (masterSecret) {
    headers['X-Admin-Secret'] = masterSecret
  } else {
    try {
      const { data: { session } } = await adminSupabase.auth.getSession()
      if (session?.access_token) {
        headers['Authorization'] = `Bearer ${session.access_token}`
      }
    } catch {
      // Ignore session retrieval failure
    }
  }
  return headers
}

/**
 * Broadcast an announcement or alert to merchant apps.
 * Automatically tries the backend API first, falling back to direct Supabase execution.
 */
export async function broadcastMerchantNotification(payload: BroadcastPayload): Promise<BroadcastResult> {
  const baseUrl = getBackendBaseUrl()
  const headers = await getAdminHeaders()

  // 1. Attempt Backend API dispatch
  try {
    const res = await fetch(`${baseUrl}/v1/admin/notifications/broadcast`, {
      method: 'POST',
      headers,
      body: JSON.stringify(payload),
    })
    if (res.ok) {
      const json = await res.json()
      if (json && json.ok) return json
    }
  } catch (backendErr) {
    console.warn('[broadcastMerchantNotification] Backend API offline/unreachable, falling back to direct Supabase:', backendErr)
  }

  // 2. Direct Supabase Fallback (Resilient dispatch)
  const batchId = typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `bcast-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  const nowIso = new Date().toISOString()
  const cleanTitle = payload.title.trim().slice(0, 255)
  const cleanMessage = payload.message.trim()
  const cleanType = payload.type || 'ANNOUNCEMENT'
  const cleanSeverity = payload.severity || 'INFO'

  let targetMerchants: Array<{ id: string }> = []

  if (payload.target && payload.target !== 'ALL' && payload.target !== 'ACTIVE') {
    targetMerchants = [{ id: payload.target }]
  } else {
    try {
      let query = adminSupabase.from('merchants').select('id, status')
      if (payload.target === 'ACTIVE') {
        query = query.eq('status', 'ACTIVE')
      }
      const { data: list, error: mErr } = await query
      if (!mErr && Array.isArray(list) && list.length > 0) {
        targetMerchants = list
      }
    } catch (e) {
      console.warn('[broadcastMerchantNotification] Supabase merchants query notice:', e)
    }
  }

  if (targetMerchants.length === 0 && payload.target && payload.target !== 'ALL') {
    targetMerchants = [{ id: payload.target }]
  }

  const rows = targetMerchants.map(m => ({
    id: typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `notif-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    merchant_id: m.id,
    type: cleanType,
    title: cleanTitle,
    message: cleanMessage,
    severity: cleanSeverity,
    entity_type: 'BROADCAST',
    entity_id: batchId,
    created_at: nowIso,
    read_at: null,
  }))

  let insertedCount = 0
  if (rows.length > 0) {
    const { error: insErr } = await adminSupabase.from('merchant_notifications').insert(rows)
    if (insErr) {
      console.warn('[broadcastMerchantNotification] Direct insert error (retrying with minimal payload):', insErr.message)
      const fallbackRows = rows.map(({ entity_id, entity_type, ...rest }) => rest)
      const { error: fbErr } = await adminSupabase.from('merchant_notifications').insert(fallbackRows)
      if (fbErr) throw new Error('Failed to insert notifications into database: ' + fbErr.message)
      insertedCount = fallbackRows.length
    } else {
      insertedCount = rows.length
    }
  }

  // Dual delivery: update dashboard banner if requested
  if (payload.updateBanner) {
    try {
      const { data: currentNotice } = await adminSupabase
        .from('showcase_config')
        .select('value')
        .eq('key', 'system_config')
        .maybeSingle()

      const currentVal = currentNotice?.value || {}
      await upsertShowcaseConfig('system_config', {
        ...currentVal,
        system_notice: cleanMessage,
        updated_at: nowIso,
      })
    } catch (bannerErr) {
      console.warn('[broadcastMerchantNotification] Notice banner update failed:', bannerErr)
    }
  }

  return {
    ok: true,
    batch_id: batchId,
    recipients_count: insertedCount || rows.length,
    target: payload.target,
    type: cleanType,
    severity: cleanSeverity,
    title: cleanTitle,
    message: cleanMessage,
    banner_updated: Boolean(payload.updateBanner),
    created_at: nowIso,
  }
}

/**
 * Fetch past broadcast notifications history.
 */
export async function fetchBroadcastHistory(limit = 50): Promise<BroadcastHistoryItem[]> {
  const baseUrl = getBackendBaseUrl()
  const headers = await getAdminHeaders()

  // 1. Attempt Backend API
  try {
    const res = await fetch(`${baseUrl}/v1/admin/notifications/broadcasts?limit=${limit}`, {
      headers,
    })
    if (res.ok) {
      const json = await res.json()
      if (json && json.ok && Array.isArray(json.broadcasts)) {
        return json.broadcasts
      }
    }
  } catch (backendErr) {
    console.warn('[fetchBroadcastHistory] Backend fetch notice (using Supabase query):', backendErr)
  }

  // 2. Direct Supabase Fallback Query
  try {
    const { data, error } = await adminSupabase
      .from('merchant_notifications')
      .select('id, merchant_id, type, title, message, severity, entity_type, entity_id, created_at, read_at')
      .eq('entity_type', 'BROADCAST')
      .order('created_at', { ascending: false })
      .limit(Math.min(limit * 25, 400))

    if (error) throw error

    const batchMap = new Map<string, BroadcastHistoryItem>()
    for (const row of (data || [])) {
      const key = (row as any).entity_id || (row as any).id
      if (!batchMap.has(key)) {
        batchMap.set(key, {
          batch_id: key,
          title: (row as any).title,
          message: (row as any).message,
          type: (row as any).type as NotificationType,
          severity: (row as any).severity as NotificationSeverity,
          created_at: (row as any).created_at,
          recipients_count: 0,
          read_count: 0,
        })
      }
      const b = batchMap.get(key)!
      b.recipients_count++
      if ((row as any).read_at) b.read_count++
    }

    return Array.from(batchMap.values()).slice(0, limit)
  } catch (supaErr) {
    console.warn('[fetchBroadcastHistory] Supabase direct query notice:', supaErr)
    return []
  }
}

/**
 * Delete a broadcast batch.
 */
export async function deleteBroadcastBatch(batchId: string): Promise<void> {
  if (!batchId) return
  const baseUrl = getBackendBaseUrl()
  const headers = await getAdminHeaders()

  try {
    const res = await fetch(`${baseUrl}/v1/admin/notifications/broadcasts/${encodeURIComponent(batchId)}`, {
      method: 'DELETE',
      headers,
    })
    if (res.ok) return
  } catch {
    // Fall back to direct Supabase delete
  }

  const { error } = await adminSupabase
    .from('merchant_notifications')
    .delete()
    .eq('entity_id', batchId)

  if (error) throw new Error('Failed to delete broadcast: ' + error.message)
}

// ─────────────────────────────────────────────────────────────
// SUBSCRIPTION & DYNAMIC PRICING CONFIGURATION (Admin Panel)
// ─────────────────────────────────────────────────────────────

export interface AdminSubscriptionConfig {
  monthly_fee: number
  quarterly_fee: number
  yearly_fee: number
  trial_days: number
  is_trial_enabled: boolean
  enforce_nid_verification: boolean
  updated_at?: string
}

export async function fetchSubscriptionConfig(): Promise<AdminSubscriptionConfig> {
  const baseUrl = getBackendBaseUrl()
  const headers = await getAdminHeaders()

  try {
    const res = await fetch(`${baseUrl}/v1/admin/subscription-config`, { headers })
    if (res.ok) {
      const json = await res.json()
      if (json.config) {
        return {
          monthly_fee: Number(json.config.monthly_fee) || 100,
          quarterly_fee: Number(json.config.quarterly_fee) || 250,
          yearly_fee: Number(json.config.yearly_fee) || 650,
          trial_days: Number(json.config.trial_days) || 90,
          is_trial_enabled: json.config.is_trial_enabled ?? true,
          enforce_nid_verification: json.config.enforce_nid_verification ?? true,
          updated_at: json.config.updated_at,
        }
      }
    }
  } catch (err) {
    console.warn('[fetchSubscriptionConfig] Backend notice:', err)
  }

  // Fallback 1: platform_subscription_config table
  try {
    const { data } = await adminSupabase
      .from('platform_subscription_config')
      .select('*')
      .eq('id', 'default_config')
      .maybeSingle()

    if (data) {
      return {
        monthly_fee: Number(data.monthly_fee) || 100,
        quarterly_fee: Number(data.quarterly_fee) || 250,
        yearly_fee: Number(data.yearly_fee) || 650,
        trial_days: Number(data.trial_days) || 90,
        is_trial_enabled: data.is_trial_enabled ?? true,
        enforce_nid_verification: data.enforce_nid_verification ?? true,
        updated_at: data.updated_at,
      }
    }
  } catch (e) {}

  // Fallback 2: showcase_config table
  try {
    const { data } = await adminSupabase
      .from('showcase_config')
      .select('value')
      .eq('key', 'subscription_config')
      .maybeSingle()

    if (data?.value) {
      const v = data.value
      return {
        monthly_fee: Number(v.monthly_fee) || 100,
        quarterly_fee: Number(v.quarterly_fee) || 250,
        yearly_fee: Number(v.yearly_fee) || 650,
        trial_days: Number(v.trial_days) || 90,
        is_trial_enabled: v.is_trial_enabled ?? true,
        enforce_nid_verification: v.enforce_nid_verification ?? true,
        updated_at: v.updated_at,
      }
    }
  } catch (e) {}

  return {
    monthly_fee: 100,
    quarterly_fee: 250,
    yearly_fee: 650,
    trial_days: 90,
    is_trial_enabled: true,
    enforce_nid_verification: true,
  }
}

export async function saveSubscriptionConfig(config: AdminSubscriptionConfig): Promise<AdminSubscriptionConfig> {
  const baseUrl = getBackendBaseUrl()
  const headers = await getAdminHeaders()
  const payload: AdminSubscriptionConfig = {
    monthly_fee: Number(config.monthly_fee),
    quarterly_fee: Number(config.quarterly_fee),
    yearly_fee: Number(config.yearly_fee),
    trial_days: Number(config.trial_days),
    is_trial_enabled: Boolean(config.is_trial_enabled),
    enforce_nid_verification: Boolean(config.enforce_nid_verification),
    updated_at: new Date().toISOString(),
  }

  // 1. Try Backend POST
  try {
    await fetch(`${baseUrl}/v1/admin/subscription-config`, {
      method: 'POST',
      headers: { ...headers, 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
  } catch (err) {
    console.warn('[saveSubscriptionConfig] Backend save notice:', err)
  }

  // 2. Direct Supabase platform_subscription_config
  try {
    await adminSupabase
      .from('platform_subscription_config')
      .upsert({
        id: 'default_config',
        ...payload,
      })
  } catch (err) {
    console.warn('[saveSubscriptionConfig] platform_subscription_config notice:', err)
  }

  // 3. Direct Supabase showcase_config
  try {
    await upsertShowcaseConfig('subscription_config', payload)
  } catch (err) {
    console.warn('[saveSubscriptionConfig] showcase_config notice:', err)
  }

  return payload
}


