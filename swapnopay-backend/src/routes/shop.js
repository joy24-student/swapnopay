import { Router } from 'express'
import crypto from 'node:crypto'
import { rateLimit } from 'express-rate-limit'
import { requireShopAuth } from '../middleware/shopAuth.js'
import { getShopService } from '../services/shopService.js'
import { ShopError, merchantId, hostname } from '../services/shopValidation.js'

export function createShopRouter({ service = getShopService, authenticate = requireShopAuth } = {}) {
  const router = Router()
  const wrap = handler => async (req, res) => {
    try { await handler(req, res) } catch (error) {
      console.error('[shop/api]', error.code || 'STORE_REQUEST_FAILED', error.message, error.stack)
      if (error instanceof ShopError) {
        res.status(error.status || 400).json({ ok: false, code: error.code || 'STORE_REQUEST_FAILED', error: error.message })
      } else {
        res.status(503).json({ ok: false, code: 'STORE_REQUEST_FAILED', error: 'The storefront service is unavailable: request failed.' })
      }
    }
  }
  router.use((_req, res, next) => { res.set('Cache-Control', 'no-store'); next() })
  // Caddy asks before issuing a certificate. This reveals no merchant data or secrets.
  router.get('/tls/authorize', wrap(async (req, res) => {
    const rawDomain = req.query.domain
    if (!rawDomain || typeof rawDomain !== 'string') return res.sendStatus(400)
    let domain
    try {
      domain = hostname(rawDomain)
    } catch {
      return res.sendStatus(400)
    }
    const shop = service()
    await shop.initialize()
    const result = await shop.pool.query(`SELECT 1 FROM shop_control.launches WHERE (tls_allowed=true OR custom_domain IS NULL)
      AND (custom_domain=$1 OR shop_slug || '.' || $2=$1)
      AND status IN ('PROVISIONING','WAITING_TLS','WAITING_DNS','LIVE') LIMIT 1`, [domain, shop.config.baseDomain])
    res.sendStatus(result.rowCount ? 204 : 403)
  }))
  // Normalize the merchant selector before authentication; body/query conflicts must
  // never authorize one merchant and execute a request for a different merchant.
  router.use((req, res, next) => {
    try {
      const rawParam = ['GET','DELETE'].includes(req.method)
        ? (req.query.merchant_id || req.headers['x-merchant-id'])
        : (req.body?.merchant_id || req.headers['x-merchant-id'] || req.query.merchant_id)
      const target = merchantId(rawParam)
      if (req.query.merchant_id && merchantId(req.query.merchant_id) !== target) {
        throw new ShopError(400, 'MERCHANT_MISMATCH', 'Conflicting merchant identifiers.')
      }
      if (req.body?.merchant_id && merchantId(req.body.merchant_id) !== target) {
        throw new ShopError(400, 'MERCHANT_MISMATCH', 'Conflicting merchant identifiers.')
      }
      req.shopMerchantId = target
      if (req.body && typeof req.body === 'object') {
        req.body.merchant_id = target
      }
      next()
    } catch(error) { res.status(400).json({ok:false,error:error.message}) }
  })
  router.use(authenticate)
  const id = req => req.shopMerchantId
  const existing = async req => {
    const row = await service().row(id(req))
    if (!row) throw new ShopError(409, 'STORE_NOT_READY', 'Launch your storefront first.')
    return row
  }
  router.get('/status', wrap(async (req,res) => res.json(await service().status(id(req)))))
  // Link the merchant's own Supabase project to its gateway before a site is launched.
  router.post('/connect-database', wrap(async (req,res) => {
    if (!req.isAdmin && !['api_key', 'supabase', 'merchant_supabase'].includes(req.authMethod)) {
      throw new ShopError(403, 'ACCOUNT_AUTH_REQUIRED', 'Sign in to your merchant account before connecting a database.')
    }
    const projectUrl = String(req.body.supabase_url || '').trim().replace(/\/$/, '')
    const anonKey = String(req.body.supabase_anon_key || '').trim()
    let parsed
    try { parsed = new URL(projectUrl) } catch { throw new ShopError(400, 'INVALID_SUPABASE_URL', 'Enter a valid Supabase project URL.') }
    const permittedHosts = (process.env.SHOP_AUTH_HOSTS || '').split(',').map(host => host.trim().toLowerCase()).filter(Boolean)
    if (parsed.protocol !== 'https:' || parsed.username || parsed.password ||
      !(parsed.hostname.endsWith('.supabase.co') || permittedHosts.includes(parsed.hostname))) {
      throw new ShopError(400, 'INVALID_SUPABASE_URL', 'Supabase must use HTTPS and a verified Supabase project host.')
    }
    if (anonKey.length < 20 || anonKey.length > 4096) throw new ShopError(400, 'INVALID_SUPABASE_KEY', 'Enter the project publishable or anon key.')
    if (/^(sb_secret_|service_role_)/i.test(anonKey)) {
      throw new ShopError(400, 'SERVICE_ROLE_KEY_FORBIDDEN', 'Never connect a service-role or secret key. Use the project publishable or anon key.')
    }
    const jwtPayload = anonKey.split('.')[1]
    if (jwtPayload) {
      try {
        const decoded = JSON.parse(Buffer.from(jwtPayload, 'base64url').toString('utf8'))
        if (decoded.role && decoded.role !== 'anon') {
          throw new ShopError(400, 'SERVICE_ROLE_KEY_FORBIDDEN', 'Use the project anon key, not an elevated database key.')
        }
      } catch (error) {
        if (error instanceof ShopError) throw error
        throw new ShopError(400, 'INVALID_SUPABASE_KEY', 'The Supabase key must be a valid publishable or anon key.')
      }
    }
    if (!req.isAdmin && req.merchantUser?.id !== id(req)) throw new ShopError(403, 'MERCHANT_MISMATCH', 'Database credentials must belong to this merchant.')
    const cleanProjectUrl = parsed.href.replace(/\/$/, '')
    const extractedRef = parsed.hostname.endsWith('.supabase.co') ? parsed.hostname.replace(/\.supabase\.co$/i, '') : (req.body.project_ref || '')
    const { getAdminClient, getMerchantCredentials, setMerchantGatewayConfig } = await import('../services/adminSupabase.js')
    const { getDefaultProjectDbPassword } = await import('../services/provisionService.js')
    const existingCreds = await getMerchantCredentials(id(req)).catch(() => null)
    const dbPassword = req.body.db_password || existingCreds?.db_password || (extractedRef ? getDefaultProjectDbPassword(extractedRef) : '')
    const poolerHost = req.body.db_host || existingCreds?.db_host || process.env.SUPABASE_DEFAULT_POOLER_HOST || 'aws-0-ap-southeast-1.pooler.supabase.com'
    const databaseUrl = req.body.database_url || existingCreds?.database_url || (extractedRef && dbPassword
      ? `postgresql://postgres.${extractedRef}:${encodeURIComponent(dbPassword)}@${poolerHost}:5432/postgres`
      : '')
    await setMerchantGatewayConfig(id(req), {
      supabase_url: cleanProjectUrl,
      supabase_anon_key: anonKey,
      project_ref: extractedRef,
      ...(dbPassword ? { db_password: dbPassword } : {}),
      ...(databaseUrl ? { database_url: databaseUrl, db_host: poolerHost, db_port: 5432, db_user: `postgres.${extractedRef}`, db_name: 'postgres' } : {}),
    })
    const { data, error } = await getAdminClient().from('merchants')
      .update({ supabase_url: cleanProjectUrl, supabase_anon_key: anonKey, updated_at: new Date().toISOString() })
      .eq('id', id(req)).select('id').maybeSingle()
    if (error && !existingCreds) throw error
    if (!data && !existingCreds) throw new ShopError(404, 'MERCHANT_NOT_FOUND', 'The signed-in merchant account was not found.')
    res.json({ ok: true, merchant_id: id(req), connected: true, project_url: cleanProjectUrl, database_url_configured: Boolean(databaseUrl) })
  }))
  router.post('/deploy', rateLimit({windowMs:60000,limit:10,standardHeaders:'draft-8',legacyHeaders:false}), wrap(async (req,res) => {
    const result = await service().enqueue(req.body)
    res.status(result.deployed ? 200 : 202).json(result)
  }))
  const updateDomainHandler = wrap(async (req,res) => {
    await existing(req);
    let domainVal = req.body.custom_domain
    if (typeof domainVal === 'string') {
      domainVal = domainVal.trim()
      if (!domainVal || domainVal.toLowerCase() === 'null' || domainVal.toLowerCase() === 'none' || domainVal.toLowerCase() === 'undefined') {
        domainVal = null
      }
    }
    res.status(202).json(await service().enqueue({merchant_id:id(req),custom_domain:domainVal || null}))
  })
  router.post('/domain', updateDomainHandler)
  router.patch('/domain', updateDomainHandler)
  router.get('/settings', wrap(async (req,res) => {
    const row = await existing(req)
    res.json({ok:true,settings:{store_name:row.store_name,shop_slug:row.shop_slug,custom_domain:row.custom_domain,theme_color:row.theme_color,currency_code:row.currency,admin_email:row.admin_email}})
  }))
  router.post('/settings', wrap(async (req,res) => {
    await existing(req)
    res.status(202).json(await service().enqueue({merchant_id:id(req),store_name:req.body.store_name || req.body.site_title,theme_color:req.body.theme_color,primary_currency:req.body.currency_code || req.body.currency || req.body.primary_currency}))
  }))
  router.post('/admin/credentials', wrap(async (req,res) => {
    await existing(req)
    const adminPassword = req.body.admin_password || req.body.password
    const adminEmail = req.body.admin_email || req.body.email
    if (!adminPassword) throw new ShopError(400,'PASSWORD_REQUIRED','Enter a new password of at least 12 characters.')
    res.status(202).json(await service().enqueue({merchant_id:id(req),admin_email:adminEmail,admin_password:adminPassword}))
  }))
  router.post('/sync-inventory', wrap(async(req,res) => res.json(await service().sync(id(req),req.body.items))))
  router.post('/images',rateLimit({windowMs:60000,limit:60,standardHeaders:'draft-8',legacyHeaders:false}),wrap(async(req,res)=>res.status(201).json(await service().uploadImage(id(req),req.body))))
  router.post('/products', wrap(async(req,res) => res.status(201).json(await service().sync(id(req),[{
    source_id:req.body.source_id || req.body.sku || req.body.id || crypto.randomUUID(),name:req.body.name || req.body.p_name,
    price:req.body.price ?? req.body.p_current_price,stock:req.body.stock ?? req.body.p_qty,
    description:req.body.description || req.body.p_description || '',is_featured:req.body.is_featured,
  }]))))
  router.get('/products', wrap(async(req,res) => {
    const limit=Number(req.query.limit || 100), offset=Number(req.query.offset || 0)
    if (!Number.isInteger(limit) || limit<1 || limit>250 || !Number.isInteger(offset) || offset<0) throw new ShopError(400,'INVALID_PAGE','Invalid product pagination.')
    const result=await service().tenant(id(req),client=>client.query(`SELECT p_id AS id,source_id,p_name AS name,p_current_price AS price,p_qty AS stock,p_description AS description,p_featured_photo AS image FROM tbl_product WHERE p_is_active=1 ORDER BY p_id DESC LIMIT $1 OFFSET $2`,[limit,offset]))
    res.json({ok:true,products:result.rows})
  }))
  router.get('/products/:id', wrap(async(req,res) => {
    if (!/^\d+$/.test(req.params.id)) throw new ShopError(400,'INVALID_PRODUCT','Invalid product identifier.')
    const result=await service().tenant(id(req),client=>client.query('SELECT * FROM tbl_product WHERE p_id=$1 AND p_is_active=1',[req.params.id]))
    if (!result.rowCount) throw new ShopError(404,'PRODUCT_NOT_FOUND','Product not found.')
    res.json({ok:true,product:result.rows[0]})
  }))
  router.delete('/products/:id', wrap(async(req,res) => {
    if (!/^\d+$/.test(req.params.id)) throw new ShopError(400,'INVALID_PRODUCT','Invalid product identifier.')
    const result=await service().tenant(id(req),client=>client.query('UPDATE tbl_product SET p_is_active=0 WHERE p_id=$1 AND p_is_active=1',[req.params.id]))
    if (!result.rowCount) throw new ShopError(404,'PRODUCT_NOT_FOUND','Product not found.')
    res.json({ok:true})
  }))
  router.get('/orders', wrap(async(req,res) => {
    const result=await service().tenant(id(req),client=>client.query(`SELECT payment_id AS id,customer_name AS cus_name,customer_email AS cus_email,paid_amount AS amount,payment_status AS status,shipping_status,payment_method,payment_date AS created_at FROM tbl_payment ORDER BY id DESC LIMIT 100`))
    res.json({ok:true,orders:result.rows})
  }))
  router.post('/orders/status', wrap(async(req,res) => {
    const status=String(req.body.status || '').toUpperCase(), orderId=String(req.body.order_id || req.body.tran_id || '')
    if (!['PAID','SHIPPED','DELIVERED'].includes(status) || !orderId || orderId.length>100) throw new ShopError(400,'INVALID_STATUS','Choose a valid order and status.')
    await service().tenant(id(req),async client=>{
      const row=(await client.query('SELECT * FROM tbl_payment WHERE payment_id=$1 FOR UPDATE',[orderId])).rows[0]
      if (!row) throw new ShopError(404,'ORDER_NOT_FOUND','Order not found.')
      if (row.payment_status==='Cancelled' || row.shipping_status==='Cancelled') throw new ShopError(409,'INVALID_TRANSITION','A cancelled order cannot be updated.')
      const cash=['COD','Cash on Delivery','Cash'].includes(row.payment_method)
      if (status==='PAID' && !cash || status!=='PAID' && !cash && row.payment_status!=='Completed') throw new ShopError(409,'PAYMENT_UNVERIFIED','Online payments must be verified by the payment provider before fulfillment.')
      if (row.shipping_status==='Delivered' && status==='SHIPPED') throw new ShopError(409,'INVALID_TRANSITION','A delivered order cannot return to shipped.')
      if (status==='PAID') await client.query("UPDATE tbl_payment SET payment_status='Completed' WHERE payment_id=$1",[orderId])
      else await client.query('UPDATE tbl_payment SET shipping_status=$2 WHERE payment_id=$1',[orderId,status==='SHIPPED' ? 'Shipped' : 'Delivered'])
    })
    res.json({ok:true})
  }))
  router.get('/vps-config', wrap(async(req,res) => { const row=await existing(req); res.json({ok:true,domain:row.custom_domain || `${row.shop_slug}.${service().config.baseDomain}`,message:'Routing and certificates are managed automatically by the storefront service.'}) }))
  return router
}

export default createShopRouter()
