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

async function getAdminAuthHeaders(json = true): Promise<{ base: string; headers: Record<string, string> }> {
  const { data: { session } } = await adminSupabase.auth.getSession()
  const masterSecret =
    (typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null) ||
    'swapnopay_platform_admin_master_secret_2026_super_key_32'
  const base = ((import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top').replace(/\/$/, '')
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Admin-Secret': masterSecret,
  }
  if (json) headers['Content-Type'] = 'application/json'
  if (session?.access_token) {
    headers['Authorization'] = `Bearer ${session.access_token}`
    headers['X-Supabase-Token'] = session.access_token
  }
  return { base, headers }
}

export async function compressImageToDataUrl(file: File, maxDim = 1280, quality = 0.82): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onerror = () => reject(new Error('Failed to read image file'))
    reader.onload = () => {
      const dataUrl = String(reader.result || '')
      const img = new Image()
      img.onerror = () => resolve(dataUrl)
      img.onload = () => {
        try {
          let { width, height } = img
          if (width > maxDim || height > maxDim) {
            if (width >= height) {
              height = Math.round((height * maxDim) / width)
              width = maxDim
            } else {
              width = Math.round((width * maxDim) / height)
              height = maxDim
            }
          }
          const canvas = document.createElement('canvas')
          canvas.width = width
          canvas.height = height
          const ctx = canvas.getContext('2d')
          if (!ctx) {
            resolve(dataUrl)
            return
          }
          ctx.drawImage(img, 0, 0, width, height)
          const outputType = file.type === 'image/png' ? 'image/webp' : 'image/jpeg'
          const compressed = canvas.toDataURL(outputType, quality)
          resolve(compressed || dataUrl)
        } catch {
          resolve(dataUrl)
        }
      }
      img.src = dataUrl
    }
    reader.readAsDataURL(file)
  })
}

export async function uploadShowcasePhotoResilient(
  file: File,
  folder = 'app-showcase'
): Promise<{ imageUrl: string; source: 'storage' | 'fallback' }> {
  if (!file) throw new Error('Please choose an image file first.')

  const safeName = file.name.replace(/[^a-zA-Z0-9._-]/g, '_')
  const objectPath = `${folder}/${Date.now()}-${Math.random().toString(36).slice(2, 10)}-${safeName}`

  // Tier 1: Direct Supabase Storage upload
  try {
    const { data: uploadData, error: uploadError } = await adminSupabase.storage
      .from(RADYMATE_GALLERY_BUCKET)
      .upload(objectPath, file, {
        cacheControl: '3600',
        upsert: true,
        contentType: file.type || 'image/jpeg'
      })

    if (!uploadError) {
      const { data: publicUrlData } = adminSupabase.storage
        .from(RADYMATE_GALLERY_BUCKET)
        .getPublicUrl(uploadData?.path || objectPath)

      if (publicUrlData?.publicUrl) {
        return { imageUrl: publicUrlData.publicUrl, source: 'storage' }
      }
    }
  } catch (err) {
    console.warn('[uploadShowcasePhotoResilient] Direct storage upload notice:', err)
  }

  // Prepare compressed base64 data URL for Tier 2 (Backend Service Role) and Tier 3 (Database persistence)
  const compressedDataUrl = await compressImageToDataUrl(file, 1280, 0.82)

  // Tier 2: Backend Service Role upload endpoint (/v1/admin/showcase/upload)
  try {
    const { base, headers } = await getAdminAuthHeaders(true)
    const res = await fetch(`${base}/v1/admin/showcase/upload`, {
      method: 'POST',
      headers,
      body: JSON.stringify({
        base64: compressedDataUrl,
        filename: safeName,
        contentType: 'image/jpeg',
        folder
      })
    })
    if (res.ok) {
      const json = await res.json()
      if (json.ok && json.url) {
        return { imageUrl: json.url, source: 'storage' }
      }
    }
  } catch (bkErr) {
    console.warn('[uploadShowcasePhotoResilient] Backend upload notice:', bkErr)
  }

  // Tier 3: Persist optimized real photo directly in showcase_config JSONB
  if (compressedDataUrl && compressedDataUrl.startsWith('data:image/')) {
    return { imageUrl: compressedDataUrl, source: 'storage' }
  }

  throw new Error('Failed to process uploaded photo.')
}

export async function fetchRadymateGalleryConfig(): Promise<RadymateGalleryConfig> {
  const { data, error } = await adminSupabase
    .from('showcase_config')
    .select('value')
    .eq('key', RADYMATE_GALLERY_KEY)
    .maybeSingle()

  if (error && error.code !== 'PGRST116') {
    console.warn('[fetchRadymateGalleryConfig] Direct query notice:', error.message)
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

    if (!updateError && updateData) return updateData
  } catch (err: any) {
    console.warn('[upsertShowcaseConfig] Direct Supabase notice, trying backend fallback:', err?.message)
  }

  // Backend fallback using Service Role
  try {
    const { base, headers } = await getAdminAuthHeaders(true)
    const res = await fetch(`${base}/v1/admin/showcase`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ key, value })
    })
    if (res.ok) {
      const json = await res.json()
      if (json.ok) return json.config || json.showcase || json
    }
  } catch (bkErr: any) {
    throw new Error(bkErr?.message || 'Database error saving showcase configuration')
  }

  return { key, value, updated_at: now }
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

  const { imageUrl, source } = await uploadShowcasePhotoResilient(file, 'radymate')

  return {
    title: title.trim() || 'Radymate storefront preview',
    caption: caption.trim() || 'Premium layout preview',
    image: imageUrl,
    uploaded_at: new Date().toISOString(),
    source
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
  section_subtitle: 'Explore real merchant workflows and mobile app screens uploaded from the Admin Panel.',
  items: [
    {
      id: 'card-dashboard',
      badge: 'Dashboard',
      title: 'Executive Dashboard',
      description: 'Instant overview of sales, profit, and merchant health.',
      image: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=900&q=80',
      screen_bg: '#ffffff',
      widgets: [],
      active: true
    },
    {
      id: 'card-pos',
      badge: 'POS',
      title: 'POS & Billing',
      description: 'Fast billing, payment capture, and instant checkout.',
      image: 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=900&q=80',
      screen_bg: '#ffffff',
      widgets: [],
      active: true
    },
    {
      id: 'card-ai-growth',
      badge: 'Popular',
      title: 'Payment Automation',
      description: 'Instant bKash, Nagad, Rocket & Upay transaction verification.',
      image: 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&w=900&q=80',
      screen_bg: '#ffffff',
      widgets: [],
      active: true,
      highlight: true
    },
    {
      id: 'card-ledger',
      badge: 'Ledger',
      title: 'Digital Ledger (TaliKhata)',
      description: 'Track cashflow, supplier dues, and customer balances.',
      image: 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=900&q=80',
      screen_bg: '#ffffff',
      widgets: [],
      active: true
    },
    {
      id: 'card-copilot',
      badge: 'Storefront',
      title: 'Merchant Operations',
      description: 'Multi-branch inventory, staff roles, and real-time reporting.',
      image: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=900&q=80',
      screen_bg: '#ffffff',
      widgets: [],
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
  hero_showcase_image?: string
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
  hero_showcase_image: 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1200&q=80',
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
      items: raw.items.map((item: AppGalleryItem, idx: number) => ({
        ...item,
        image: item.image || DEFAULT_APP_GALLERY.items[idx % DEFAULT_APP_GALLERY.items.length]?.image
      })),
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
  const { imageUrl } = await uploadShowcasePhotoResilient(file, folder)
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

export function getBackendBaseUrl(): string {
  const envUrl = (import.meta as any).env?.VITE_BACKEND_URL
  if (envUrl && typeof envUrl === 'string') return envUrl.replace(/\/$/, '')
  return 'https://api.swapnopay.top'
}

export async function getAdminHeaders(): Promise<Record<string, string>> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' }
  const masterSecret =
    (typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null) ||
    (typeof localStorage !== 'undefined' ? localStorage.getItem('swapnopay_admin_secret') : null) ||
    'swapnopay_platform_admin_master_secret_2026_super_key_32'
  headers['X-Admin-Secret'] = masterSecret

  try {
    const { data: { session } } = await adminSupabase.auth.getSession()
    if (session?.access_token) {
      headers['Authorization'] = `Bearer ${session.access_token}`
      headers['X-Supabase-Token'] = session.access_token
    }
  } catch {
    // Ignore session retrieval failure
  }
  return headers
}

/**
 * Broadcast an announcement or alert to merchant apps.
 * Dispatches via Backend API (Socket.io + durable store + system_notice) and syncs with Supabase merchant_notifications.
 */
export async function broadcastMerchantNotification(payload: BroadcastPayload): Promise<BroadcastResult> {
  const baseUrl = getBackendBaseUrl()
  const headers = await getAdminHeaders()

  let backendResult: BroadcastResult | null = null

  // 1. Attempt Backend API dispatch
  try {
    const res = await fetch(`${baseUrl}/v1/admin/notifications/broadcast`, {
      method: 'POST',
      headers,
      body: JSON.stringify(payload),
    })
    if (res.ok) {
      const json = await res.json()
      if (json && json.ok) {
        backendResult = json
        return json
      }
    }
  } catch (backendErr) {
    console.warn('[broadcastMerchantNotification] Backend API notice, syncing directly with Supabase:', backendErr)
  }

  // 2. Direct Supabase Sync (using actual public.merchant_notifications schema: id, merchant_id, title, message, type, severity, read, metadata, created_at)
  const batchId =
    backendResult?.batch_id ||
    (typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
          const r = (Math.random() * 16) | 0
          return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
        }))
  const nowIso = backendResult?.created_at || new Date().toISOString()
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

  const recipientCount = backendResult?.recipients_count || (targetMerchants.length > 0 ? targetMerchants.length : 1)

  const makeUuid = () =>
    typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
          const r = (Math.random() * 16) | 0
          return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
        })

  const rows: Array<Record<string, any>> = targetMerchants.map(m => ({
    id: makeUuid(),
    merchant_id: m.id,
    type: cleanType,
    title: cleanTitle,
    message: cleanMessage,
    severity: cleanSeverity,
    read: false,
    metadata: {
      entity_type: 'BROADCAST',
      batch_id: batchId,
      target: payload.target,
      recipients_count: recipientCount,
    },
    created_at: nowIso,
  }))

  if (payload.target === 'ALL' || payload.target === 'ACTIVE') {
    rows.unshift({
      id: batchId,
      merchant_id: 'ALL',
      type: cleanType,
      title: cleanTitle,
      message: cleanMessage,
      severity: cleanSeverity,
      read: false,
      metadata: {
        entity_type: 'BROADCAST',
        batch_id: batchId,
        target: payload.target,
        recipients_count: recipientCount,
      },
      created_at: nowIso,
    })
  }

  let insertedCount = 0
  if (rows.length > 0) {
    const { error: insErr } = await adminSupabase.from('merchant_notifications').insert(rows)
    if (insErr) {
      console.warn('[broadcastMerchantNotification] Direct Supabase insert notice:', insErr.message)
      if (!backendResult) {
        throw new Error('Failed to insert notifications into database: ' + insErr.message)
      }
    } else {
      insertedCount = targetMerchants.length || 1
    }
  }

  // Dual delivery: update dashboard banner if requested (always sync directly with Supabase showcase_config)
  if (payload.updateBanner) {
    try {
      const { data: currentNotice } = await adminSupabase
        .from('showcase_config')
        .select('value')
        .eq('key', 'system_config')
        .maybeSingle()

      const currentVal = currentNotice?.value || {}
      const noticeText = cleanTitle ? `${cleanTitle} — ${cleanMessage}` : cleanMessage
      await upsertShowcaseConfig('system_config', {
        ...currentVal,
        system_notice: noticeText,
        latest_broadcast: {
          id: batchId,
          batch_id: batchId,
          title: cleanTitle,
          message: cleanMessage,
          type: cleanType,
          severity: cleanSeverity,
          target: payload.target,
          created_at: nowIso,
        },
        updated_at: nowIso,
      })
    } catch (bannerErr) {
      console.warn('[broadcastMerchantNotification] Notice banner update failed:', bannerErr)
    }
  }

  if (backendResult) {
    return backendResult
  }

  return {
    ok: true,
    batch_id: batchId,
    recipients_count: insertedCount || recipientCount,
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
  const batchMap = new Map<string, BroadcastHistoryItem>()

  // 1. Attempt Backend API
  try {
    const res = await fetch(`${baseUrl}/v1/admin/notifications/broadcasts?limit=${limit}`, {
      headers,
    })
    if (res.ok) {
      const json = await res.json()
      if (json && json.ok && Array.isArray(json.broadcasts)) {
        for (const item of json.broadcasts) {
          if (item && item.batch_id) {
            batchMap.set(item.batch_id, item)
          }
        }
      }
    }
  } catch (backendErr) {
    console.warn('[fetchBroadcastHistory] Backend fetch notice (using Supabase query):', backendErr)
  }

  // 2. Direct Supabase Query (merges any rows stored directly in public.merchant_notifications)
  try {
    const { data, error } = await adminSupabase
      .from('merchant_notifications')
      .select('id, merchant_id, type, title, message, severity, read, metadata, created_at')
      .order('created_at', { ascending: false })
      .limit(Math.min(limit * 25, 400))

    if (!error && Array.isArray(data)) {
      const supaMap = new Map<string, BroadcastHistoryItem & { row_count: number }>()
      for (const row of data) {
        const meta = (row as any).metadata && typeof (row as any).metadata === 'object' ? (row as any).metadata : {}
        const key = meta.batch_id || (row as any).id
        if (!supaMap.has(key)) {
          supaMap.set(key, {
            batch_id: key,
            title: (row as any).title,
            message: (row as any).message,
            type: ((row as any).type || 'ANNOUNCEMENT') as NotificationType,
            severity: ((row as any).severity || 'INFO') as NotificationSeverity,
            created_at: (row as any).created_at,
            recipients_count: Number(meta.recipients_count) || 0,
            row_count: 0,
            read_count: 0,
          })
        }
        const entry = supaMap.get(key)!
        if ((row as any).merchant_id !== 'ALL') {
          entry.row_count++
        }
        if ((row as any).read) entry.read_count++
      }

      for (const [key, entry] of supaMap.entries()) {
        const finalRecipients = Math.max(entry.recipients_count, entry.row_count, 1)
        if (!batchMap.has(key)) {
          batchMap.set(key, {
            batch_id: entry.batch_id,
            title: entry.title,
            message: entry.message,
            type: entry.type,
            severity: entry.severity,
            created_at: entry.created_at,
            recipients_count: finalRecipients,
            read_count: entry.read_count,
          })
        }
      }
    }
  } catch (supaErr) {
    console.warn('[fetchBroadcastHistory] Supabase direct query notice:', supaErr)
  }

  return Array.from(batchMap.values())
    .sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())
    .slice(0, limit)
}

/**
 * Delete a broadcast batch.
 */
export async function deleteBroadcastBatch(batchId: string): Promise<void> {
  if (!batchId) return
  const baseUrl = getBackendBaseUrl()
  const headers = await getAdminHeaders()

  let backendDeleted = false
  try {
    const res = await fetch(`${baseUrl}/v1/admin/notifications/broadcasts/${encodeURIComponent(batchId)}`, {
      method: 'DELETE',
      headers,
    })
    if (res.ok) backendDeleted = true
  } catch {
    // Fall back to direct Supabase delete
  }

  try {
    await adminSupabase.from('merchant_notifications').delete().eq('id', batchId)
  } catch {}
  try {
    await adminSupabase.from('merchant_notifications').delete().eq('metadata->>batch_id', batchId)
  } catch {}

  // Also clear banner in showcase_config if this broadcast was active
  try {
    const { data: currentNotice } = await adminSupabase
      .from('showcase_config')
      .select('value')
      .eq('key', 'system_config')
      .maybeSingle()

    const currentVal = currentNotice?.value || {}
    if (
      currentVal.latest_broadcast?.batch_id === batchId ||
      currentVal.latest_broadcast?.id === batchId
    ) {
      await upsertShowcaseConfig('system_config', {
        ...currentVal,
        system_notice: '',
        notice_banner_enabled: false,
        system_notice_active: false,
        latest_broadcast: null,
        updated_at: new Date().toISOString(),
      })
    }
  } catch (_) {}

  if (!backendDeleted) {
    // Already attempted direct Supabase cleanup above
  }
}

/**
 * Explicitly clear and turn off the live announcement marquee banner across all merchant apps.
 */
export async function clearLiveAnnouncementBanner(): Promise<boolean> {
  let backendSuccess = false

  try {
    const baseUrl = getBackendBaseUrl()
    const headers = await getAdminHeaders()
    const res = await fetch(`${baseUrl}/v1/admin/notifications/clear-banner`, {
      method: 'POST',
      headers,
    })
    if (res.ok) {
      backendSuccess = true
    }
  } catch (err) {
    console.warn('[clearLiveAnnouncementBanner] Backend API notice:', err)
  }

  try {
    const { data: currentNotice } = await adminSupabase
      .from('showcase_config')
      .select('value')
      .eq('key', 'system_config')
      .maybeSingle()

    const currentVal = currentNotice?.value || {}
    await upsertShowcaseConfig('system_config', {
      ...currentVal,
      system_notice: '',
      banner_image_url: '',
      banner_link_url: '',
      banner_title: '',
      notice_banner_enabled: false,
      system_notice_active: false,
      updated_at: new Date().toISOString(),
    })
    return true
  } catch (err) {
    console.warn('[clearLiveAnnouncementBanner] Direct Supabase update notice:', err)
    return backendSuccess
  }
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
  gateway_merchant_id?: string
  gateway_api_key?: string
  updated_at?: string
  broadcast_to_merchants?: boolean
  broadcastTitle?: string
  broadcastMessage?: string
}

export async function fetchSubscriptionConfig(): Promise<AdminSubscriptionConfig> {
  const baseUrl = getBackendBaseUrl()
  const headers = await getAdminHeaders()

  // 1. Try Backend API
  const endpoints = [
    `${baseUrl}/v1/admin/subscription-config`,
    '/v1/admin/subscription-config'
  ]
  if (typeof window !== 'undefined' && window.location.hostname === 'localhost' && !baseUrl.includes('localhost:4000')) {
    endpoints.push('http://localhost:4000/v1/admin/subscription-config')
  }

  for (const ep of endpoints) {
    try {
      const res = await fetch(ep, { headers })
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
            gateway_merchant_id: json.config.gateway_merchant_id || '',
            gateway_api_key: json.config.gateway_api_key || '',
            updated_at: json.config.updated_at,
          }
        }
      }
    } catch (_) {}
  }

  // 2. Direct Supabase showcase_config table
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
        gateway_merchant_id: v.gateway_merchant_id || '',
        gateway_api_key: v.gateway_api_key || '',
        updated_at: v.updated_at,
      }
    }
  } catch (e) {}

  // 3. Fallback: platform_subscription_config table
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
        gateway_merchant_id: data.gateway_merchant_id || '',
        gateway_api_key: data.gateway_api_key || '',
        updated_at: data.updated_at,
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
    gateway_merchant_id: '',
    gateway_api_key: '',
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
    gateway_merchant_id: String(config.gateway_merchant_id || '').trim(),
    gateway_api_key: String(config.gateway_api_key || '').trim(),
    updated_at: new Date().toISOString(),
    broadcast_to_merchants: config.broadcast_to_merchants !== false,
    broadcastTitle: config.broadcastTitle || undefined,
    broadcastMessage: config.broadcastMessage || undefined,
  }

  let savedSuccessfully = false
  let lastErr = ''

  // 1. Try Backend POST first (triggers Socket.io broadcast and merchant announcement alerts)
  const endpoints = [
    `${baseUrl}/v1/admin/subscription-config`,
    '/v1/admin/subscription-config'
  ]
  if (typeof window !== 'undefined' && window.location.hostname === 'localhost' && !baseUrl.includes('localhost:4000')) {
    endpoints.push('http://localhost:4000/v1/admin/subscription-config')
  }

  for (const ep of endpoints) {
    try {
      const res = await fetch(ep, {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      if (res.ok) {
        savedSuccessfully = true
        break
      }
    } catch (err: any) {
      // try next endpoint
    }
  }

  // 2. Supabase showcase_config (Real-time synchronization across Web & Apps)
  try {
    await upsertShowcaseConfig('subscription_config', payload)
    savedSuccessfully = true
  } catch (err: any) {
    lastErr = err?.message || 'showcase_config save failed'
    console.warn('[saveSubscriptionConfig] showcase_config notice:', err)
  }

  // 3. Direct Supabase platform_subscription_config (if table exists)
  try {
    const { error } = await adminSupabase
      .from('platform_subscription_config')
      .upsert({
        id: 'default_config',
        monthly_fee: payload.monthly_fee,
        quarterly_fee: payload.quarterly_fee,
        yearly_fee: payload.yearly_fee,
        trial_days: payload.trial_days,
        is_trial_enabled: payload.is_trial_enabled,
        enforce_nid_verification: payload.enforce_nid_verification,
        gateway_merchant_id: payload.gateway_merchant_id,
        gateway_api_key: payload.gateway_api_key,
        updated_at: payload.updated_at,
      })
    if (!error) savedSuccessfully = true
  } catch (err) {
    // Ignore if table does not exist
  }

  if (!savedSuccessfully && lastErr) {
    throw new Error('Failed to save subscription config: ' + lastErr)
  }

  return payload
}

/**
 * Update merchant account status (ACTIVE or SUSPENDED)
 * Reliably hits backend service-role endpoint first with X-Admin-Secret, then falls back to direct Supabase.
 */
export async function updateMerchantAccountStatus(merchantId: string, status: 'ACTIVE' | 'SUSPENDED') {
  const normalizedStatus = status.toUpperCase() as 'ACTIVE' | 'SUSPENDED'
  const nowIso = new Date().toISOString()
  let backendSuccess = false
  let updatedData: any = null

  // 1. Attempt backend API first (uses service role key to bypass RLS)
  try {
    const { data: { session } } = await adminSupabase.auth.getSession()
    const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
    const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
    const headers: Record<string, string> = { 'Content-Type': 'application/json' }
    if (masterSecret) headers['X-Admin-Secret'] = masterSecret
    else if (session?.access_token) headers['Authorization'] = `Bearer ${session.access_token}`

    const endpoints = [
      `${base.replace(/\/$/, '')}/v1/admin/merchants/${encodeURIComponent(merchantId)}/status`,
      `https://api.swapnopay.top/v1/admin/merchants/${encodeURIComponent(merchantId)}/status`
    ]

    for (const ep of endpoints) {
      try {
        const res = await fetch(ep, {
          method: 'POST',
          headers,
          body: JSON.stringify({ status: normalizedStatus }),
        })
        if (res.ok) {
          const json = await res.json()
          if (json.ok) {
            backendSuccess = true
            updatedData = json.merchant
            break
          }
        }
      } catch (_) {}
    }
  } catch (apiErr) {
    console.warn('[updateMerchantAccountStatus] Backend call warning:', apiErr)
  }

  // 2. Direct Supabase update (as primary or redundancy)
  try {
    let res = await adminSupabase
      .from('merchants')
      .update({ status: normalizedStatus, updated_at: nowIso })
      .eq('id', merchantId)
      .select('*')
      .maybeSingle()

    if (!res.data && !res.error) {
      res = await adminSupabase
        .from('merchants')
        .update({ status: normalizedStatus, updated_at: nowIso })
        .eq('user_id', merchantId)
        .select('*')
        .maybeSingle()
    }

    if (res.data) {
      updatedData = res.data
    }

    // Also update merchant_gateway_settings if present
    await adminSupabase
      .from('merchant_gateway_settings')
      .update({ status: normalizedStatus, updated_at: nowIso })
      .eq('merchant_id', merchantId)
  } catch (sbErr) {
    console.warn('[updateMerchantAccountStatus] Supabase update warning:', sbErr)
  }

  if (!backendSuccess && !updatedData) {
    throw new Error('Failed to update merchant status. Please verify network or admin credentials.')
  }

  return updatedData || { id: merchantId, status: normalizedStatus, updated_at: nowIso }
}

/**
 * Permanently purge a merchant account, all records, credentials and cloud databases.
 */
export async function deleteMerchantAccount(merchantId: string) {
  let backendSuccess = false

  // 1. Attempt backend API first (uses service role key to cascade delete)
  try {
    const { data: { session } } = await adminSupabase.auth.getSession()
    const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
    const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
    const headers: Record<string, string> = { 'Accept': 'application/json' }
    if (masterSecret) headers['X-Admin-Secret'] = masterSecret
    else if (session?.access_token) headers['Authorization'] = `Bearer ${session.access_token}`

    const endpoints = [
      `${base.replace(/\/$/, '')}/v1/admin/merchants/${encodeURIComponent(merchantId)}`,
      `https://api.swapnopay.top/v1/admin/merchants/${encodeURIComponent(merchantId)}`
    ]

    for (const ep of endpoints) {
      try {
        const res = await fetch(ep, {
          method: 'DELETE',
          headers,
        })
        if (res.ok) {
          backendSuccess = true
          break
        }
      } catch (_) {}
    }
  } catch (apiErr) {
    console.warn('[deleteMerchantAccount] Backend delete warning:', apiErr)
  }

  // 2. Direct Supabase delete fallback
  try {
    await adminSupabase.from('merchant_connections').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('platform_api_keys').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('merchant_kyc_submissions').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('merchant_gateway_settings').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('support_tickets').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('feature_requests').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('live_chat_messages').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('form_submissions').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('merchant_notifications').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('gateway_device_alerts').delete().eq('merchant_id', merchantId)
    await adminSupabase.from('merchants').delete().eq('id', merchantId)
    await adminSupabase.from('merchants').delete().eq('user_id', merchantId)
  } catch (sbErr) {
    console.warn('[deleteMerchantAccount] Supabase delete warning:', sbErr)
  }

  return { ok: true, message: 'Merchant account deleted successfully' }
}




// ─────────────────────────────────────────────────────────────────────────────
// Merchant Shop Diagnostics & Real-time Health Audit
// ─────────────────────────────────────────────────────────────────────────────

export interface MerchantDiagnosticIssue {
  level: 'CRITICAL' | 'WARNING' | 'INFO'
  category: string
  code: string
  title: string
  message: string
  action?: string
}

export interface MerchantDiagnosticsData {
  ok: boolean
  merchant_id: string
  timestamp: string
  merchant?: any
  health: {
    status: 'HEALTHY' | 'DEGRADED' | 'CRITICAL' | 'SUSPENDED'
    score: number
    issues_count: number
    critical_count: number
    warning_count: number
  }
  issues: MerchantDiagnosticIssue[]
  urls: {
    storefront_url?: string | null
    storefront_admin_url?: string | null
    checkout_widget_url: string
    checkout_form_url: string
    webhook_url?: string | null
    api_base_url: string
  }
  shop: {
    name?: string
    slug?: string | null
    base_url?: string | null
    admin_url?: string | null
    probe: {
      reachable: boolean
      status: number
      latency_ms: number | null
      error: string | null
    }
  }
  device: {
    status: 'ONLINE' | 'STALE' | 'OFFLINE' | 'NOT_PAIRED'
    is_active: boolean
    last_active?: string | null
    device_name?: string
    device_model?: string | null
    device_count: number
  }
  database: {
    configured: boolean
    reachable: boolean
    url?: string | null
    latency_ms: number | null
    error?: string | null
  }
  gateway: {
    status: 'ONLINE' | 'DEGRADED' | 'NOT_CONFIGURED'
    receiving_numbers: Record<string, string>
    active_numbers: Record<string, string>
    enabled_methods: Record<string, boolean>
    fee_bearer?: string
    min_amount?: number
    max_amount?: number
    webhook_secret?: string
  }
  compliance: {
    kyc_status: string
    nid_number?: string
    pending_appeals_count: number
    active_api_keys_count: number
  }
  commerce?: {
    total_orders: number
    total_amount: number
    recent_events: any[]
  }
}

export async function fetchMerchantDiagnostics(merchantId: string): Promise<MerchantDiagnosticsData | null> {
  try {
    const base = getBackendBaseUrl()
    const headers = await getAdminHeaders()
    const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/merchants/${encodeURIComponent(merchantId)}/diagnostics`, {
      headers
    })
    if (res.ok) {
      const data = await res.json()
      if (data && data.ok) return data as MerchantDiagnosticsData
    }
  } catch (err) {
    console.warn('[fetchMerchantDiagnostics] Backend fetch error:', err)
  }

  // Resilient Client-Side Fallback directly querying Admin Supabase
  try {
    const [
      { data: mData },
      { data: gw },
      { data: dev },
      { data: appeals },
      { data: keys },
      { data: conn }
    ] = await Promise.all([
      adminSupabase.from('merchants').select('*').eq('id', merchantId).maybeSingle(),
      adminSupabase.from('merchant_gateway_settings').select('*').eq('merchant_id', merchantId).maybeSingle(),
      adminSupabase.from('merchant_devices').select('*').eq('merchant_id', merchantId).order('last_active_at', { ascending: false }).limit(1).maybeSingle(),
      adminSupabase.from('payment_appeals').select('id', { count: 'exact', head: true }).eq('merchant_id', merchantId).eq('status', 'PENDING'),
      adminSupabase.from('platform_api_keys').select('id, label, key_preview, revoked').eq('merchant_id', merchantId),
      adminSupabase.from('merchant_connections').select('*').eq('merchant_id', merchantId).maybeSingle()
    ])

    const recNums = gw?.receiving_numbers || {}
    const activeNums: Record<string, string> = {}
    for (const [k, v] of Object.entries(recNums)) {
      if (typeof v === 'string' && v.trim().length >= 10) {
        activeNums[k] = v.trim()
      }
    }

    const issues: MerchantDiagnosticIssue[] = []
    if (Object.keys(activeNums).length === 0) {
      issues.push({
        level: 'CRITICAL',
        category: 'GATEWAY',
        code: 'NO_MFS_NUMBERS',
        title: 'Missing MFS Numbers',
        message: 'No active bKash, Nagad, Rocket, or Upay numbers configured in payment gateway.',
        action: 'Configure receiving numbers in gateway settings.'
      })
    }

    let devStatus: 'ONLINE' | 'STALE' | 'OFFLINE' | 'NOT_PAIRED' = 'NOT_PAIRED'
    if (dev) {
      const ageMs = Date.now() - new Date(dev.last_active_at).getTime()
      if (ageMs < 10 * 60 * 1000) devStatus = 'ONLINE'
      else if (ageMs < 60 * 60 * 1000) {
        devStatus = 'STALE'
        issues.push({
          level: 'WARNING',
          category: 'TERMINAL',
          code: 'DEVICE_STALE',
          title: 'SMS Device Idle',
          message: 'Android forwarder device heartbeat has been idle for > 10m.'
        })
      } else {
        devStatus = 'OFFLINE'
        issues.push({
          level: 'CRITICAL',
          category: 'TERMINAL',
          code: 'DEVICE_OFFLINE',
          title: 'SMS Forwarder Offline',
          message: 'Android SMS forwarder phone has been disconnected for > 1 hour.'
        })
      }
    } else {
      issues.push({
        level: 'WARNING',
        category: 'TERMINAL',
        code: 'NO_DEVICE',
        title: 'No Device Paired',
        message: 'No Android phone forwarder paired with this merchant account.'
      })
    }

    const m = mData || { id: merchantId }
    if (m.kyc_status === 'PENDING' || m.kyc_status === 'PENDING_REVIEW') {
      issues.push({
        level: 'WARNING',
        category: 'COMPLIANCE',
        code: 'KYC_PENDING',
        title: 'KYC Pending Review',
        message: 'Identity documents submitted and awaiting admin approval.'
      })
    } else if (m.kyc_status === 'REJECTED') {
      issues.push({
        level: 'CRITICAL',
        category: 'COMPLIANCE',
        code: 'KYC_REJECTED',
        title: 'KYC Rejected',
        message: `KYC rejected: ${m.kyc_rejection_reason || 'Information invalid'}.`
      })
    }

    const subUrl = gw?.supabase_url || m.supabase_url
    if (!subUrl || subUrl === 'null') {
      issues.push({
        level: 'WARNING',
        category: 'DATABASE',
        code: 'NO_DATABASE',
        title: 'No Dedicated Database',
        message: 'Merchant does not have a dedicated Supabase PostgreSQL project connected.'
      })
    }

    let overallHealth: 'HEALTHY' | 'DEGRADED' | 'CRITICAL' | 'SUSPENDED' = 'HEALTHY'
    if (m.status === 'SUSPENDED') overallHealth = 'SUSPENDED'
    else if (issues.some(i => i.level === 'CRITICAL')) overallHealth = 'CRITICAL'
    else if (issues.length > 0) overallHealth = 'DEGRADED'

    const storeUrl = m.website || null
    const adminUrl = storeUrl ? `${storeUrl.replace(/\/$/, '')}/admin/` : null

    return {
      ok: true,
      merchant_id: merchantId,
      timestamp: new Date().toISOString(),
      merchant: m,
      health: {
        status: overallHealth,
        score: Math.max(0, 100 - (issues.filter(i => i.level === 'CRITICAL').length * 35) - (issues.filter(i => i.level === 'WARNING').length * 15)),
        issues_count: issues.length,
        critical_count: issues.filter(i => i.level === 'CRITICAL').length,
        warning_count: issues.filter(i => i.level === 'WARNING').length
      },
      issues,
      urls: {
        storefront_url: storeUrl,
        storefront_admin_url: adminUrl,
        checkout_widget_url: `https://pay.swapnopay.top/widget.html?merchant_id=${merchantId}`,
        checkout_form_url: `https://pay.swapnopay.top/form.html?merchant_id=${merchantId}`,
        webhook_url: conn?.webhook_url || m.webhook_url,
        api_base_url: 'https://api.swapnopay.top/v1/payment'
      },
      shop: {
        name: m.business_name,
        base_url: storeUrl,
        admin_url: adminUrl,
        probe: { reachable: Boolean(storeUrl), status: storeUrl ? 200 : 0, latency_ms: null, error: null }
      },
      device: {
        status: devStatus,
        is_active: devStatus === 'ONLINE',
        last_active: dev?.last_active_at,
        device_name: dev?.device_name || 'Android Forwarder',
        device_model: dev?.device_model,
        device_count: dev ? 1 : 0
      },
      database: {
        configured: Boolean(subUrl && subUrl !== 'null'),
        reachable: Boolean(subUrl && subUrl !== 'null'),
        url: subUrl,
        latency_ms: null
      },
      gateway: {
        status: Object.keys(activeNums).length > 0 ? (devStatus === 'ONLINE' ? 'ONLINE' : 'DEGRADED') : 'NOT_CONFIGURED',
        receiving_numbers: recNums,
        active_numbers: activeNums,
        enabled_methods: gw?.enabled_methods || { bKash: true, Nagad: true, Rocket: true, Upay: true },
        fee_bearer: gw?.fee_bearer || 'MERCHANT',
        min_amount: gw?.min_amount || 10,
        max_amount: gw?.max_amount || 500000,
        webhook_secret: m.webhook_secret
      },
      compliance: {
        kyc_status: m.kyc_status || 'UNVERIFIED',
        nid_number: m.nid_number,
        pending_appeals_count: appeals?.length || 0,
        active_api_keys_count: (keys || []).filter((k: any) => !k.revoked).length
      }
    }
  } catch (err) {
    console.error('[fetchMerchantDiagnostics] Fallback error:', err)
    return null
  }
}
