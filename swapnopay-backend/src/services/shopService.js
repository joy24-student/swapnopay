import { Pool } from 'pg'
import bcrypt from 'bcryptjs'
import crypto from 'node:crypto'
import fs from 'node:fs/promises'
import { productDetails, saveCatalogItems, storeProductImage } from './shopCatalog.js'
import path from 'node:path'
import net from 'node:net'
import { fileURLToPath } from 'node:url'
import { ShopError, merchantId, hostname, launchInput, schemaName, identifier, sqlLiteral, generatedPassword, encryptConfig, decryptConfig, inventoryItems } from './shopValidation.js'
import { checkShopDns, probeStore } from './shopReadiness.js'

const base = path.dirname(fileURLToPath(import.meta.url))
const busyStates = ['QUEUED', 'PROVISIONING']
const schemaFile = path.join(base, '../../sql/storefront.sql')
const publicFields = 'merchant_id,store_name,shop_slug,custom_domain,currency,theme_color,admin_email,status,message,job_id,updated_at'

export function shopConfiguration(env = process.env) {
  const defaultKey = crypto.createHash('sha256').update(env.ADMIN_SECRET || 'swapnopay-default-shop-config-secret-key-32').digest('hex')
  const key = (/^[0-9a-f]{64}$/i.test(env.SHOP_CONFIG_KEY || '')) ? env.SHOP_CONFIG_KEY : defaultKey
  const rawAddresses = (env.SHOP_SERVER_IPS || '127.0.0.1').split(',').map(x => x.trim()).filter(Boolean)
  const addresses = rawAddresses.length && rawAddresses.every(x => net.isIP(x)) ? rawAddresses : ['127.0.0.1']

  const defaultTemplate = path.resolve(base, '../../../shop')
  const defaultRuntime = path.resolve(base, '../../data/shop-runtime')
  const defaultSites = path.resolve(base, '../../data/shop-sites')

  const runtime = path.resolve(env.SHOP_RUNTIME_DIR || defaultRuntime)
  const sites = path.resolve(env.SHOP_SITES_DIR || defaultSites)
  const template = path.resolve(env.SHOP_TEMPLATE_DIR || defaultTemplate)

  const rawDbUrl = env.SHOP_DATABASE_URL || env.DATABASE_URL || env.POSTGRES_URL || ''
  const isServerRuntime = Boolean(env.PORT || env.ADMIN_SUPABASE_URL || env.ADMIN_SECRET)
  const allowEmbedded = env.ALLOW_EMBEDDED_SHOP_DB === 'true' || (isServerRuntime && env.NODE_ENV !== 'test')
  if (!rawDbUrl && !allowEmbedded) {
    throw new ShopError(503, 'SHOP_NOT_CONFIGURED', 'Website hosting is not configured yet. The platform operator must complete the storefront setup.')
  }

  let connectionString = ''
  let dbHost = '127.0.0.1', dbPort = 5432, dbName = 'swapnopay_shop'

  if (rawDbUrl) {
    try {
      const url = new URL(rawDbUrl)
      if (['postgres:', 'postgresql:'].includes(url.protocol)) {
        connectionString = url.href
        dbHost = env.SHOP_PHP_DB_HOST || url.hostname
        dbPort = Number(env.SHOP_PHP_DB_PORT || url.port || 5432)
        dbName = decodeURIComponent(url.pathname.slice(1)) || 'swapnopay_shop'
      } else {
        throw new ShopError(503, 'SHOP_NOT_CONFIGURED', 'Website hosting database is not configured: must be PostgreSQL')
      }
    } catch (err) {
      if (err instanceof ShopError) throw err
      throw new ShopError(503, 'SHOP_NOT_CONFIGURED', 'Website hosting database is not configured correctly: ' + err.message)
    }
  }

  return {
    connectionString,
    useEmbedded: !rawDbUrl,
    key, addresses,
    baseDomain: hostname(env.SHOP_BASE_DOMAIN || 'shop.swapnopay.top'),
    runtime, sites, template,
    dbHost, dbPort, dbName,
    sslmode: env.SHOP_DB_SSLMODE || 'require',
    group: env.SHOP_RUNTIME_GID ? Number(env.SHOP_RUNTIME_GID) : undefined,
    backendUrl: env.SHOP_BACKEND_URL || 'https://api.swapnopay.top',
    vendor: env.SHOP_VENDOR_DIR || '',
  }
}

export class ShopService {
  constructor(config, dependencies = {}) {
    this.config = config
    this.dns = dependencies.dns || ((host, addresses, lookup) => checkShopDns(host, addresses, lookup, config.baseDomain))
    this.probe = dependencies.probe || probeStore
    this.initialized = null
    this.processing = false

    if (dependencies.pool) {
      this.pool = dependencies.pool
    } else if (config.connectionString) {
      this.pool = new Pool({ connectionString: config.connectionString, max: 6, connectionTimeoutMillis: 8000, idleTimeoutMillis: 30000 })
    } else if (config.useEmbedded) {
      const dbPath = path.resolve(config.runtime, 'shop-db')
      let dbInstance = null
      const getDb = async () => {
        if (!dbInstance) {
          const { PGlite } = await import('@electric-sql/pglite')
          await fs.mkdir(dbPath, { recursive: true })
          dbInstance = new PGlite(dbPath)
        }
        return dbInstance
      }
      const query = async (sql, params = []) => {
        const db = await getDb()
        if (sql.includes('pg_try_advisory_lock')) return { rows: [{ locked: true }], rowCount: 1 }
        if (sql.includes('pg_advisory_')) return { rows: [], rowCount: 1 }
        if (sql.includes('CREATE ROLE') || sql.includes('ALTER ROLE')) return { rows: [], rowCount: 1 }
        if (!params.length && sql.includes(';')) {
          let result
          try {
            result = await db.exec(sql)
          } catch (error) {
            if (error.message && (error.message.includes('role') || error.message.includes('permission denied'))) {
              return { rows: [], rowCount: 1 }
            }
            throw error
          }
          const last = Array.isArray(result) ? result.at(-1) : result
          return { ...last, rowCount: last?.affectedRows ?? last?.rows?.length ?? 0 }
        }
        const result = await db.query(sql, params)
        return { ...result, rowCount: result.affectedRows ?? result.rows.length }
      }
      const client = { query, release() {} }
      this.pool = { query, connect: async () => client }
    } else {
      this.pool = null
    }
  }
  async initialize() {
    if (!this.pool) return
    if (!this.initialized) this.initialized = this.pool.query(`
      CREATE SCHEMA IF NOT EXISTS shop_control;
      REVOKE ALL ON SCHEMA shop_control FROM PUBLIC;
      CREATE TABLE IF NOT EXISTS shop_control.launches (
        merchant_id uuid PRIMARY KEY, store_name text NOT NULL, shop_slug text UNIQUE NOT NULL,
        custom_domain text UNIQUE, currency text NOT NULL, theme_color text NOT NULL, admin_email text NOT NULL,
        status text NOT NULL DEFAULT 'QUEUED', message text NOT NULL DEFAULT '',
        job_id uuid NOT NULL, secret_config text NOT NULL, schema_ready boolean NOT NULL DEFAULT false,
        tls_allowed boolean NOT NULL DEFAULT false, attempts integer NOT NULL DEFAULT 0,
        next_attempt timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
      ); REVOKE ALL ON shop_control.launches FROM PUBLIC;
      CREATE TABLE IF NOT EXISTS shop_control.domains (hostname text PRIMARY KEY, merchant_id uuid NOT NULL REFERENCES shop_control.launches(merchant_id));
      REVOKE ALL ON shop_control.domains FROM PUBLIC;
    `).catch(error => { this.initialized = null; throw error })
    await this.initialized
  }
  async row(id) {
    if (!this.pool) return null
    await this.initialize()
    return (await this.pool.query(`SELECT * FROM shop_control.launches WHERE merchant_id=$1`, [merchantId(id)])).rows[0] || null
  }
  publicStatus(row, counts = {}) {
    if (!row) {
      const url = `https://${this.config.baseDomain}`
      return {
        ok: true, deployed: false, status: 'NOT_DEPLOYED',
        shop_url: url, admin_url: `${url}/admin`, admin_login_url: `${url}/admin/login.php`,
        base_domain: this.config.baseDomain, ssl_active: false,
        message: 'Set up your store and launch when ready.'
      }
    }
    const domain = row.custom_domain || `${row.shop_slug}.${this.config.baseDomain}`
    const url = `https://${domain}`
    return {
      ok: true, merchant_id: row.merchant_id, deployed: row.status === 'LIVE', status: row.status,
      job_id: row.job_id, message: row.message, shop_url: url, shop_slug: row.shop_slug,
      admin_url: `${url}/admin`, admin_login_url: `${url}/admin/login.php`,
      admin_credentials: { email: row.admin_email, login_url: `${url}/admin/login.php`, role: 'Top Admin', has_custom_password: true },
      store_name: row.store_name, custom_domain: row.custom_domain, currency: row.currency, theme_color: row.theme_color,
      base_domain: this.config.baseDomain, ssl_active: row.status === 'LIVE',
      vps_status: row.status === 'LIVE' ? 'VERIFIED' : 'UNVERIFIED', gateway_connected: false,
      dns_records: { a_record: { type: 'A', host: domain, target: this.config.addresses[0] } },
      last_updated: row.updated_at, products_count: 0, orders_count: 0, total_revenue: 0, ...counts,
    }
  }
  async status(id) {
    if (!this.pool) return this.publicStatus(null)
    const row = await this.row(id)
    if (row && ['LIVE','DEGRADED'].includes(row.status) && Date.now()-new Date(row.updated_at).getTime()>60000) {
      const host=row.custom_domain || `${row.shop_slug}.${this.config.baseDomain}`
      const ready=await this.dns(host,this.config.addresses) && (await this.probe(host,this.config.addresses[0],id)).ready
      row.status=ready ? 'LIVE' : 'DEGRADED'
      row.message=ready ? 'Your storefront and admin login are ready over HTTPS.' : 'The storefront could not be reached over HTTPS. Check your domain and hosting, then refresh.'
      const saved=await this.pool.query("UPDATE shop_control.launches SET status=$2,message=$3,updated_at=now() WHERE merchant_id=$1 AND job_id=$4 AND status IN ('LIVE','DEGRADED') RETURNING updated_at",[id,row.status,row.message,row.job_id])
      if(!saved.rowCount) return this.status(id)
      row.updated_at=saved.rows[0].updated_at
    }
    if (!row?.schema_ready) return this.publicStatus(row)
    const s = identifier(schemaName(id))
    const { rows } = await this.pool.query(`SELECT
      (SELECT count(*)::int FROM ${s}.tbl_product WHERE p_is_active=1) AS products_count,
      (SELECT count(*)::int FROM ${s}.tbl_payment) AS orders_count,
      (SELECT coalesce(sum(paid_amount),0) FROM ${s}.tbl_payment WHERE payment_status='Completed') AS total_revenue`)
    return this.publicStatus(row, rows[0])
  }
  async enqueue(body) {
    if (!this.pool) {
      throw new ShopError(503, 'SHOP_DATABASE_REQUIRED', 'PostgreSQL database connection (SHOP_DATABASE_URL) is required to deploy storefront instances.')
    }
    await this.initialize()
    const id = merchantId(body.merchant_id)
    const client = await this.pool.connect()
    let initialPassword
    try {
      await client.query('BEGIN')
      await client.query('SELECT pg_advisory_xact_lock(hashtextextended($1,0))', [id])
      const existing = (await client.query('SELECT * FROM shop_control.launches WHERE merchant_id=$1 FOR UPDATE', [id])).rows[0]
      const input = launchInput(body, existing)
      const sameSettings = existing && ['store_name','shop_slug','custom_domain','currency','theme_color','admin_email'].every(key => existing[key] === input[key])
      if (existing && busyStates.includes(existing.status)) {
        const samePassword=!input.password || await bcrypt.compare(input.password,decryptConfig(existing.secret_config,this.config.key).adminHash)
        if (!sameSettings || !samePassword) throw new ShopError(409,'LAUNCH_IN_PROGRESS','A launch is already in progress. Wait for it to finish before changing settings.')
        await client.query('COMMIT'); return this.publicStatus(existing)
      }
      if (input.custom_domain && (input.custom_domain === this.config.baseDomain || input.custom_domain.endsWith(`.${this.config.baseDomain}`) || input.custom_domain === 'swapnopay.top' || input.custom_domain.endsWith('.swapnopay.top'))) {
        input.custom_domain = null
      }
      const configuration = existing ? decryptConfig(existing.secret_config, this.config.key) : { dbPassword: generatedPassword() }
      configuration.resetAdminPassword=Boolean(input.password || !existing)
      if (input.password || !existing) {
        initialPassword = input.password || generatedPassword()
        configuration.adminHash = await bcrypt.hash(initialPassword, 12)
      }
      if (sameSettings && !input.password && existing.status === 'LIVE') { await client.query('COMMIT'); return this.publicStatus(existing) }
      const values = [id,input.store_name,input.shop_slug,input.custom_domain,input.currency,input.theme_color,input.admin_email,crypto.randomUUID(),encryptConfig(configuration,this.config.key)]
      const result = await client.query(`INSERT INTO shop_control.launches
        (merchant_id,store_name,shop_slug,custom_domain,currency,theme_color,admin_email,job_id,secret_config)
        VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)
        ON CONFLICT(merchant_id) DO UPDATE SET store_name=$2,custom_domain=$4,currency=$5,theme_color=$6,admin_email=$7,
          job_id=$8,secret_config=$9,status='QUEUED',message='Preparing your storefront.',tls_allowed=false,attempts=0,next_attempt=now(),updated_at=now()
        RETURNING ${publicFields}`, values)
      for (const host of new Set([`${input.shop_slug}.${this.config.baseDomain}`,input.custom_domain].filter(Boolean))) {
        await client.query('INSERT INTO shop_control.domains(hostname,merchant_id) VALUES($1,$2) ON CONFLICT DO NOTHING',[host,id])
        const owner=(await client.query('SELECT merchant_id FROM shop_control.domains WHERE hostname=$1',[host])).rows[0]
        if(owner.merchant_id !== id) throw new ShopError(409,'ADDRESS_TAKEN','This domain already belongs to another store.')
      }
      await client.query('COMMIT')
      const status = this.publicStatus(result.rows[0])
      if (initialPassword && !input.password) status.admin_credentials.initial_password = initialPassword
      return status
    } catch (error) {
      await client.query('ROLLBACK')
      if (error.code === '23505') throw new ShopError(409, 'ADDRESS_TAKEN', 'This store address or custom domain already belongs to another store.')
      throw error
    } finally { client.release() }
  }
  async writePrivate(file, data) {
    await fs.mkdir(path.dirname(file), { recursive: true, mode: 0o750 })
    if (this.config.group !== undefined && process.platform !== 'win32') await fs.chown(path.dirname(file),-1,this.config.group)
    const temporary = `${file}.${crypto.randomUUID()}.tmp`
    await fs.writeFile(temporary, data, { mode: 0o640 })
    if (this.config.group !== undefined && process.platform !== 'win32') await fs.chown(temporary, -1, this.config.group)
    await fs.rename(temporary, file)
  }
  async publishFiles(row, secrets) {
    const tenantDir = path.join(this.config.sites, 'stores', row.merchant_id)
    const marker = path.join(tenantDir, '.swapnopay-ready')
    try { await fs.access(marker) } catch {
      const stage = `${tenantDir}.${row.job_id}.tmp`
      await fs.mkdir(path.dirname(stage), { recursive: true })
      const denied = /(?:^|[/\\])(?:\.[^/\\]+|DATABASE FILE|uploads|test[^/\\]*|debug[^/\\]*|setup[^/\\]*|migration[^/\\]*|install[^/\\]*|fix_[^/\\]*|create_[^/\\]*|seo-manager.php|seo-verify.php|speed-manager.php|security-dashboard.php|email_tester.php|smtp_test.php|01test.php|phpinfo.php|Dockerfile|.*\.(?:sql|log|zip|bak|txt|md))$/i
      await fs.cp(this.config.template, stage, { recursive: true, filter: source => !denied.test(path.relative(this.config.template,source)) })
      if (this.config.vendor) await fs.cp(this.config.vendor,path.join(stage,'vendor'),{recursive:true})
      await fs.mkdir(path.join(stage,'assets','uploads'), { recursive: true, mode: 0o770 })
      await fs.cp(path.join(this.config.template,'assets','store-defaults'),path.join(stage,'assets','uploads'),{recursive:true})
      if (this.config.group !== undefined && process.platform !== 'win32') {
        await fs.chown(path.join(stage,'assets','uploads'),-1,this.config.group)
        await fs.chmod(path.join(stage,'assets','uploads'),0o2770)
      }
      await fs.writeFile(path.join(stage,'.swapnopay-ready'), row.merchant_id, { mode: 0o640 })
      // An incomplete staging directory never becomes the public document root.
      await fs.rename(stage, tenantDir)
    }
    const domain = row.custom_domain || `${row.shop_slug}.${this.config.baseDomain}`
    const schema = schemaName(row.merchant_id)
    const runtime = {
      merchant_id: row.merchant_id, base_url: `https://${domain}/`, store_name: row.store_name,
      db: { host: this.config.dbHost, port: this.config.dbPort, database: this.config.dbName, user: schema, password: secrets.dbPassword, sslmode: this.config.sslmode },
      backend_url: this.config.backendUrl,
    }
    // The permanent platform URL keeps working while a custom domain is being verified.
    for (const host of new Set([`${row.shop_slug}.${this.config.baseDomain}`, domain])) {
      const link = path.join(this.config.sites,'hosts',host)
      await fs.mkdir(path.dirname(link), { recursive: true })
      try { await fs.symlink(tenantDir,link,process.platform === 'win32' ? 'junction' : 'dir') } catch (error) {
        if (error.code !== 'EEXIST' || await fs.realpath(link) !== await fs.realpath(tenantDir)) throw error
      }
      await this.writePrivate(path.join(this.config.runtime,'hosts',`${host}.json`), JSON.stringify({...runtime,base_url:`https://${host}/`}))
    }
    const priorHosts=(await this.pool.query('SELECT hostname FROM shop_control.domains WHERE merchant_id=$1',[row.merchant_id])).rows
    for(const {hostname:host} of priorHosts) if(![`${row.shop_slug}.${this.config.baseDomain}`,domain].includes(host)) {
      await fs.unlink(path.join(this.config.sites,'hosts',host)).catch(error=>{if(error.code!=='ENOENT') throw error})
      await fs.unlink(path.join(this.config.runtime,'hosts',`${host}.json`)).catch(error=>{if(error.code!=='ENOENT') throw error})
    }
  }
  async provision(client, row) {
    const schema = schemaName(row.merchant_id), quoted = identifier(schema)
    const secrets = decryptConfig(row.secret_config,this.config.key)
    await client.query('BEGIN')
    try {
      if (!row.schema_ready) {
        await client.query(`CREATE SCHEMA IF NOT EXISTS ${quoted}; REVOKE ALL ON SCHEMA ${quoted} FROM PUBLIC`)
        await client.query(`SET LOCAL search_path TO ${quoted},pg_catalog`)
        await client.query(await fs.readFile(schemaFile,'utf8'))
        try {
          const role = await client.query('SELECT 1 FROM pg_roles WHERE rolname=$1',[schema])
          if (!role.rowCount) await client.query(`CREATE ROLE ${quoted} LOGIN PASSWORD ${sqlLiteral(secrets.dbPassword)} NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION`)
          await client.query(`ALTER ROLE ${quoted} SET search_path TO ${quoted},pg_catalog;
            GRANT USAGE ON SCHEMA ${quoted} TO ${quoted};
            GRANT SELECT,INSERT,UPDATE,DELETE ON ALL TABLES IN SCHEMA ${quoted} TO ${quoted};
            GRANT USAGE,SELECT ON ALL SEQUENCES IN SCHEMA ${quoted} TO ${quoted};`)
        } catch (roleErr) {
          console.warn('[shop/provision] Role creation notice:', roleErr.message)
        }
      }
      await client.query(`SET LOCAL search_path TO ${quoted},pg_catalog`)
      await client.query(`UPDATE tbl_settings SET meta_title_home=$1,meta_description_home=$2,contact_email=$3,receive_email=$3,"BASE_URL"=$4,theme_color=$5,currency_code=$6 WHERE id=1`,
        [row.store_name,`Shop online with ${row.store_name}.`,row.admin_email,`https://${row.custom_domain || `${row.shop_slug}.${this.config.baseDomain}`}/`,row.theme_color,row.currency])
      if (!row.schema_ready || row.status === 'QUEUED' && secrets.resetAdminPassword) await client.query(`INSERT INTO tbl_user(id,full_name,email,phone,password,role,status) VALUES(1,$1,$2,'',$3,'Top Admin','Active')
        ON CONFLICT(id) DO UPDATE SET full_name=$1,email=$2,password=$3,role='Top Admin',status='Active'`,[`${row.store_name} Administrator`,row.admin_email,secrets.adminHash])
      else await client.query('UPDATE tbl_user SET email=$1 WHERE id=1',[row.admin_email])
      await client.query(`UPDATE shop_control.launches SET schema_ready=true WHERE merchant_id=$1`,[row.merchant_id])
      await client.query('COMMIT')
    } catch(error) { await client.query('ROLLBACK'); throw error }
    await this.publishFiles(row,secrets)

    // Sync storefront website URL to Supabase merchants record
    try {
      const { getAdminClient } = await import('./adminSupabase.js')
      const adminClient = getAdminClient()
      if (adminClient) {
        const storeUrl = `https://${row.custom_domain || `${row.shop_slug}.${this.config.baseDomain}`}`
        await adminClient
          .from('merchants')
          .update({ website: storeUrl })
          .eq('id', row.merchant_id)
      }
    } catch (syncErr) {
      console.warn('[shop/provision] Supabase merchant website sync notice:', syncErr.message)
    }
  }
  async tick() {
    if (this.processing) return
    this.processing = true
    let client, lockedId
    try {
      await this.initialize()
      client = await this.pool.connect()
      const candidates = (await client.query(`SELECT * FROM shop_control.launches
        WHERE status IN ('QUEUED','PROVISIONING','WAITING_DNS','WAITING_TLS') AND next_attempt<=now() ORDER BY next_attempt LIMIT 10`)).rows
      for (const candidate of candidates) {
        const row = candidate
        const lock = await client.query('SELECT pg_try_advisory_lock(hashtextextended($1,0)) AS locked',[row.merchant_id])
        if (!lock.rows[0].locked) continue
        lockedId = row.merchant_id
        const fresh=(await client.query("SELECT * FROM shop_control.launches WHERE merchant_id=$1 AND job_id=$2 AND status IN ('QUEUED','PROVISIONING','WAITING_DNS','WAITING_TLS') AND next_attempt<=now()",[row.merchant_id,row.job_id])).rows[0]
        if (!fresh) { await client.query('SELECT pg_advisory_unlock(hashtextextended($1,0))',[lockedId]); lockedId=null; continue }
        Object.assign(row,fresh)
        await client.query(`UPDATE shop_control.launches SET status='PROVISIONING',message='Preparing files and database.',next_attempt=now()+interval '30 seconds',updated_at=now() WHERE merchant_id=$1`,[row.merchant_id])
        try {
          await this.provision(client,row)
          const isPlatform = !row.custom_domain || row.custom_domain === this.config.baseDomain || row.custom_domain.endsWith('.' + this.config.baseDomain) || row.custom_domain.endsWith('.swapnopay.top')
          const host = row.custom_domain || `${row.shop_slug}.${this.config.baseDomain}`
          let status='WAITING_DNS', message=isPlatform
            ? `Store instance ready! Securing SSL certificate for https://${host}...`
            : `Point ${host} A-record to ${this.config.addresses[0]}. We will check again automatically.`
          const dnsReady = await this.dns(host,this.config.addresses)
          await client.query('UPDATE shop_control.launches SET tls_allowed=$2 WHERE merchant_id=$1',[row.merchant_id, Boolean(dnsReady || isPlatform)])
          if (dnsReady) {
            const health = await this.probe(host,this.config.addresses[0],row.merchant_id)
            status=health.ready ? 'LIVE' : 'WAITING_TLS'
            message=health.ready ? 'Your storefront and admin login are ready over HTTPS.' : (isPlatform ? `Storefront provisioned! Securing SSL certificate for https://${host}...` : health.message)
          }
          await client.query(`UPDATE shop_control.launches SET status=$2,message=$3,attempts=attempts+1,next_attempt=now()+interval '30 seconds',updated_at=now() WHERE merchant_id=$1`,[row.merchant_id,status,message])
        } catch (error) {
          console.error('[shop/worker] Launch failed', { merchant: row.merchant_id, code: error.code || 'PROVISIONING_FAILED' })
          await client.query(`UPDATE shop_control.launches SET status='FAILED',message='Store preparation failed. Retry the launch or contact support with the launch ID.',updated_at=now() WHERE merchant_id=$1`,[row.merchant_id])
        }
        break
      }
    } finally {
      if (client && lockedId) await client.query('SELECT pg_advisory_unlock(hashtextextended($1,0))',[lockedId]).catch(()=>{})
      client?.release()
      this.processing=false
    }
  }
  async tenant(id, fn) {
    const row = await this.row(id)
    if (!row?.schema_ready) throw new ShopError(409,'STORE_NOT_READY','Launch your storefront before managing its catalog.')
    const client = await this.pool.connect()
    try {
      await client.query('BEGIN')
      await client.query(`SET LOCAL search_path TO ${identifier(schemaName(id))},pg_catalog`)
      const result = await fn(client,row)
      await client.query('COMMIT')
      return result
    } catch(error) { await client.query('ROLLBACK'); throw error } finally { client.release() }
  }
  async sync(id, items) {
    const records=inventoryItems(items)
    records.forEach((record,index)=>{record.details=productDetails(items[index].storefront)})
    return this.tenant(id,async client=>{
      await saveCatalogItems(client,records,path.join(this.config.sites,'stores',merchantId(id),'assets','uploads'))
      const count=await client.query('SELECT count(*)::int AS count FROM tbl_product WHERE p_is_active=1')
      return {ok:true,synced_count:records.length,products_count:count.rows[0].count}
    })
  }
  async uploadImage(id,body) {
    const row=await this.row(id)
    if(!row?.schema_ready) throw new ShopError(409,'STORE_NOT_READY','Launch the store before uploading product images.')
    const result=await storeProductImage(path.join(this.config.sites,'stores',merchantId(id),'assets','uploads'),body)
    if (this.config.group !== undefined && process.platform !== 'win32') {
      const file=path.join(this.config.sites,'stores',id,'assets','uploads',result.relative_path)
      await fs.chown(path.dirname(file),-1,this.config.group)
      await fs.chmod(path.dirname(file),0o2770)
      await fs.chown(file,-1,this.config.group)
    }
    const domain=row.custom_domain || `${row.shop_slug}.${this.config.baseDomain}`
    return {...result,url:`https://${domain}/assets/uploads/${result.relative_path}`}
  }
}

let service
export function getShopService() { return service ||= new ShopService(shopConfiguration()) }
export function startShopWorker() {
  try {
    const cfg = shopConfiguration()
    if (!cfg.connectionString && !cfg.useEmbedded) return null
    const poll=()=>{ try { getShopService().tick().catch(error=>console.error('[shop/worker]',error.code || 'Hosting configuration error')) } catch(error) { console.error('[shop/worker]',error.code || 'Hosting configuration error') } }
    const timer=setInterval(poll,5000)
    timer.unref()
    poll()
    return timer
  } catch {
    return null
  }
}
