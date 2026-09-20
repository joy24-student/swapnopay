import crypto from 'node:crypto'
import { domainToASCII } from 'node:url'

export class ShopError extends Error {
  constructor(status, code, message) { super(message); this.status = status; this.code = code }
}
export const merchantId = value => {
  if (typeof value !== 'string' || !value.trim()) {
    throw new ShopError(400, 'INVALID_MERCHANT', 'A valid merchant ID is required.')
  }
  const str = value.trim().toLowerCase()
  if (/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(str)) {
    return str
  }
  // Deterministic valid UUID fallback for mobile merchant strings
  const hex = crypto.createHash('md5').update(str).digest('hex')
  return `${hex.slice(0,8)}-${hex.slice(8,12)}-4${hex.slice(13,16)}-a${hex.slice(17,20)}-${hex.slice(20,32)}`
}
export function hostname(value) {
  if (typeof value !== 'string' || /[\s/:@?#\\]/.test(value)) throw new ShopError(400, 'INVALID_DOMAIN', 'Enter a domain name without a protocol, path or port.')
  const host = domainToASCII(value.toLowerCase())
  if (host.length > 253 || !/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(host)) {
    throw new ShopError(400, 'INVALID_DOMAIN', 'Enter a valid public domain name (e.g. yourbrand.com or shop.yourbrand.com).')
  }
  return host
}
const reserved = new Set(['www','api','admin','pay','shop','shops','mail','smtp','ftp','localhost','portal','docs','status'])
export function launchInput(body, existing = null) {
  const id = merchantId(body.merchant_id)
  const name = String(body.store_name ?? existing?.store_name ?? '').trim()
  const slug = String(body.shop_slug ?? existing?.shop_slug ?? `store-${id.slice(0,8)}`).trim().toLowerCase()
  if (!name || name.length > 100 || /[\u0000-\u001f<>]/.test(name)) throw new ShopError(400, 'INVALID_NAME', 'Use a store name between 1 and 100 characters.')
  if (!/^[a-z0-9](?:[a-z0-9-]{1,46})[a-z0-9]$/.test(slug) || reserved.has(slug)) throw new ShopError(400, 'INVALID_SLUG', 'Use 3–48 lowercase letters, numbers or hyphens for the store address.')
  if (existing && slug !== existing.shop_slug) throw new ShopError(409, 'SLUG_LOCKED', 'The store address cannot change after its first launch. You can connect a custom domain.')
  const currency = String(body.primary_currency ?? existing?.currency ?? 'BDT').toUpperCase()
  if (currency !== 'BDT') throw new ShopError(400, 'UNSUPPORTED_CURRENCY', 'The storefront payment integration currently supports BDT.')
  const color = body.theme_color ?? existing?.theme_color ?? '#4F46E5'
  if (!/^#[0-9a-f]{6}$/i.test(color)) throw new ShopError(400, 'INVALID_COLOR', 'Choose a valid six-digit theme color.')
  const email = String(body.admin_email ?? existing?.admin_email ?? '').trim().toLowerCase()
  if (email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw new ShopError(400, 'INVALID_EMAIL', 'Enter the store administrator’s email address.')
  const password = body.admin_password || ''
  if (typeof password !== 'string' || (password && (password.length < 12 || Buffer.byteLength(password) > 72))) throw new ShopError(400, 'WEAK_PASSWORD', 'Use a password of at least 12 characters (at most 72 UTF-8 bytes), or leave it blank to generate one.')
  let customDomain = null
  if (body.custom_domain !== undefined) {
    if (body.custom_domain && typeof body.custom_domain === 'string') {
      const clean = body.custom_domain.trim()
      if (clean && clean.toLowerCase() !== 'null' && clean.toLowerCase() !== 'none' && clean.toLowerCase() !== 'undefined') {
        customDomain = hostname(clean)
      }
    }
  } else {
    customDomain = existing?.custom_domain || null
  }
  return { merchant_id: id, store_name: name, shop_slug: slug, currency, theme_color: color, admin_email: email, custom_domain: customDomain, password }
}
export const schemaName = id => `store_${merchantId(id).replaceAll('-', '')}`
export const identifier = value => {
  if (!/^[a-z_][a-z0-9_]{0,62}$/.test(value)) throw new Error('Invalid database identifier')
  return `"${value}"`
}
export const sqlLiteral = value => `'${String(value).replaceAll("'", "''")}'`
export const generatedPassword = () => crypto.randomBytes(24).toString('base64url')
export function encryptConfig(value, key) {
  const iv = crypto.randomBytes(12)
  const cipher = crypto.createCipheriv('aes-256-gcm', Buffer.from(key, 'hex'), iv)
  const data = Buffer.concat([cipher.update(JSON.stringify(value)), cipher.final()])
  return [iv, cipher.getAuthTag(), data].map(x => x.toString('base64')).join('.')
}
export function decryptConfig(value, key) {
  const [iv, tag, data] = value.split('.').map(x => Buffer.from(x, 'base64'))
  const cipher = crypto.createDecipheriv('aes-256-gcm', Buffer.from(key, 'hex'), iv)
  cipher.setAuthTag(tag)
  return JSON.parse(Buffer.concat([cipher.update(data), cipher.final()]).toString())
}
export function inventoryItems(items) {
  if (!Array.isArray(items) || items.length < 1 || items.length > 250) throw new ShopError(400, 'INVALID_INVENTORY', 'Send between 1 and 250 products per batch.')
  const ids = new Set()
  return items.map(item => {
    const sourceId = String(item.id || item.source_id || item.sku || '').trim()
    const name = String(item.name || '').trim()
    const price = Number(item.price)
    const stock = Number(item.stock ?? item.quantity ?? 0)
    if (!sourceId || sourceId.length > 128 || ids.has(sourceId)) throw new ShopError(400, 'INVALID_PRODUCT_ID', 'Every product needs a unique, stable ID or SKU.')
    if (!name || name.length > 255 || /[<>\u0000-\u001f]/.test(name) || !Number.isFinite(price) || price < 0 || price > 99999999 || !Number.isSafeInteger(stock) || stock < 0 || stock>2147483647) throw new ShopError(400, 'INVALID_PRODUCT', 'Check product names, prices and whole-number stock quantities.')
    ids.add(sourceId)
    return { sourceId, name, price, stock, description: String(item.description || '').slice(0,20000), featured: item.isFeatured || item.is_featured ? 1 : 0 }
  })
}
