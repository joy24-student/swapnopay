import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { PGlite } from '@electric-sql/pglite'
import { lookupMerchantInAdminDb, requirePlatformUser } from './merchantAccount.js'
import { inMemoryMerchantGatewaySettings } from './adminSupabase.js'

const uid = '11111111-1111-4111-8111-111111111111'
const mid = '22222222-2222-4222-8222-222222222222'
const platform = 'https://platform.supabase.co'
process.env.ADMIN_SUPABASE_URL = platform

function fakeAdmin(tables, failure) {
  return { from(table) {
    let rows = [...(tables[table] || [])]
    const q = {
      select: () => q, limit: n => { rows = rows.slice(0, n); return q },
      eq: (key, value) => { rows = rows.filter(row => row[key] === value); return q },
      ilike: (key, value) => { rows = rows.filter(row => row[key]?.toLowerCase() === value.toLowerCase()); return q },
      maybeSingle: async () => failure === table ? { error: { message: 'missing schema' } } : { data: rows[0] || null },
      then: (resolve, reject) => Promise.resolve(failure === table ? { error: { message: 'missing schema' } } : { data: rows }).then(resolve, reject),
    }
    return q
  } }
}

test('existing merchant keeps canonical ID and is onboarded without a dedicated DB', async () => {
  inMemoryMerchantGatewaySettings.delete(uid)
  inMemoryMerchantGatewaySettings.delete(mid)
  const result = await lookupMerchantInAdminDb('owner@example.com', uid, fakeAdmin({
    merchants: [{ id: mid, user_id: uid, business_name: 'Merchant Supplies', phone: '01700000000' }],
  }))
  assert.equal(result.merchantId, mid)
  assert.equal(result.isOnboarded, true)
  assert.equal(result.database.has_own_database, false)
})

test('connection uses Auth user ID and takes URL/key from the same record', async () => {
  const result = await lookupMerchantInAdminDb('owner@example.com', uid, fakeAdmin({
    merchants: [{ id: mid, user_id: uid }],
    merchant_gateway_settings: [{ merchant_id: mid, supabase_url: platform, supabase_anon_key: 'platform-key' }],
    supabase_connections: [{ user_id: uid, project_url: 'https://merchant.supabase.co', publishable_key: 'merchant-key' }],
  }))
  assert.equal(result.database.supabase_url, 'https://merchant.supabase.co')
  assert.equal(result.database.supabase_anon_key, 'merchant-key')
  assert.equal(result.isOnboarded, true)
})

test('missing schema fails instead of declaring an existing account new', async () => {
  await assert.rejects(lookupMerchantInAdminDb('owner@example.com', uid, fakeAdmin({}, 'merchants')), /missing schema/)
  await assert.rejects(lookupMerchantInAdminDb('owner@example.com', uid, fakeAdmin({}, 'supabase_connections')), /missing schema/)
})

test('an email match owned by another Auth user cannot be claimed', async () => {
  await assert.rejects(lookupMerchantInAdminDb('owner@example.com', uid, fakeAdmin({
    merchants: [{ id: mid, user_id: mid, email: 'owner@example.com' }],
  })), /ownership mismatch/)
})

test('fabricated merchant identity and unauthenticated requests are rejected', async () => {
  await assert.rejects(lookupMerchantInAdminDb('owner@example.com', 'm_fake', fakeAdmin({})), /Auth user ID/)
  let status
  await requirePlatformUser({ headers: {} }, { status(code) { status = code; return this }, json() {} }, () => assert.fail('must not authenticate'))
  assert.equal(status, 401)
})

test('platform migration is repeatable and setup/KYC writes are atomic', async () => {
  const db = new PGlite()
  try {
    await db.exec(`
      CREATE ROLE anon; CREATE ROLE authenticated; CREATE ROLE service_role;
      CREATE SCHEMA auth;
      CREATE FUNCTION auth.uid() RETURNS uuid LANGUAGE sql AS $$ SELECT NULL::uuid $$;
      CREATE TABLE auth.users(id uuid PRIMARY KEY);
      CREATE TABLE public.admin_users(id uuid PRIMARY KEY);
    `)
    const baseline = await readFile(new URL('../../../supabase/ADMIN_DATABASE_SCHEMA.sql', import.meta.url), 'utf8')
    for (const table of ['merchants', 'merchant_gateway_settings', 'supabase_connections']) {
      const definition = baseline.match(new RegExp(`create table if not exists ${table} \\([\\s\\S]*?\\n\\);`))?.[0]
      assert.ok(definition, `${table} definition must exist in the real admin schema`)
      await db.exec(definition)
    }
    await db.query('INSERT INTO auth.users(id) VALUES ($1)', [uid])
    const migration = await readFile(new URL('../../../supabase/migrations/20260916_platform_account_repair.sql', import.meta.url), 'utf8')
    await db.exec(migration)
    await db.exec(migration)
    await db.query('INSERT INTO merchants(id,user_id,business_name) VALUES ($1,$2,$3)', [mid, uid, 'Saved business'])
    const profile = { business_name: 'Saved business', email: 'OWNER@example.com', phone: '01700000000' }
    const setup = async database => db.query('SELECT save_platform_merchant_setup($1,$2,$3,$4) AS merchant', [uid, uid, profile, database])
    const saved = await setup({ supabase_url: 'https://merchant.supabase.co', supabase_anon_key: 'public-key' })
    assert.equal(saved.rows[0].merchant.id, mid)
    await setup({})
    assert.equal((await db.query('SELECT supabase_anon_key FROM merchant_gateway_settings')).rows[0].supabase_anon_key, 'public-key')
    assert.equal((await db.query('SELECT count(*)::int AS count FROM merchants')).rows[0].count, 1)
    assert.equal((await db.query('SELECT count(*)::int AS count FROM supabase_connections')).rows[0].count, 0)
    const submission = { merchant_id: mid, nid_number: '1234567890', nid_front_url: '/front.jpg',
      nid_back_url: '/back.jpg', face_photo_url: '/face.jpg', liveness_passed: true }
    await db.exec("ALTER TABLE merchant_kyc_submissions ADD CONSTRAINT simulate_audit_failure CHECK (nid_number <> '1234567890')")
    await assert.rejects(db.query('SELECT submit_platform_merchant_kyc($1)', [submission]), /simulate_audit_failure/)
    assert.equal((await db.query('SELECT kyc_status FROM merchants')).rows[0].kyc_status, 'UNVERIFIED')
    await db.exec('ALTER TABLE merchant_kyc_submissions DROP CONSTRAINT simulate_audit_failure')
    await db.query('SELECT submit_platform_merchant_kyc($1)', [submission])
    assert.equal((await db.query('SELECT kyc_status FROM merchants')).rows[0].kyc_status, 'PENDING')
    // Review by Auth user ID must resolve the distinct merchant ID.
    await db.query("SELECT review_platform_merchant_kyc($1,'VERIFIED','','admin')", [uid])
    assert.equal((await db.query('SELECT kyc_status FROM merchants')).rows[0].kyc_status, 'VERIFIED')
    assert.equal((await db.query('SELECT status FROM merchant_kyc_submissions')).rows[0].status, 'APPROVED')
    await assert.rejects(db.query('SELECT submit_platform_merchant_kyc($1)', [submission]), /already verified/)
    const grants = await db.query("SELECT has_function_privilege('authenticated','submit_platform_merchant_kyc(jsonb)','EXECUTE') AS allowed")
    assert.equal(grants.rows[0].allowed, false)
  } finally { await db.close() }
})
