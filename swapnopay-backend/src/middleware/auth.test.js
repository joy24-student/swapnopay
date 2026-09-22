import test from 'node:test'
import assert from 'node:assert/strict'
import { safeCompare, requireAdminSecret, requireWebhookSecret, requireMerchantOrAdminAuth } from './auth.js'

test('Auth Middleware Test Suite', async (t) => {
  const origAdminSecret = process.env.ADMIN_SECRET
  const origWebhookSecret = process.env.PAYMENT_WEBHOOK_SECRET
  process.env.ADMIN_SECRET = 'test_admin_secret_super_secure_32_chars'
  process.env.PAYMENT_WEBHOOK_SECRET = 'test_webhook_secret_super_key_32_chars'

  t.after(() => {
    process.env.ADMIN_SECRET = origAdminSecret
    process.env.PAYMENT_WEBHOOK_SECRET = origWebhookSecret
  })

  await t.test('1. safeCompare timing-safe string comparison', () => {
    assert.equal(safeCompare('hello', 'hello'), true)
    assert.equal(safeCompare('hello', 'world'), false)
    assert.equal(safeCompare('hello', 'hello!'), false)
    assert.equal(safeCompare('hello', null), false)
    assert.equal(safeCompare(123, 123), false)
    assert.equal(safeCompare('', ''), true)
  })

  await t.test('2. requireAdminSecret with valid X-Admin-Secret', async () => {
    const req = { headers: { 'x-admin-secret': 'test_admin_secret_super_secure_32_chars' } }
    let nextCalled = false
    const res = {
      status(code) { this.statusCode = code; return this },
      json(data) { this.data = data; return this }
    }
    await requireAdminSecret(req, res, () => { nextCalled = true })
    assert.equal(nextCalled, true)
    assert.equal(req.isAdmin, true)
    assert.equal(req.adminUser?.role, 'super_admin')
  })

  await t.test('3. requireAdminSecret with valid Bearer secret', async () => {
    const req = { headers: { authorization: 'Bearer test_admin_secret_super_secure_32_chars' } }
    let nextCalled = false
    const res = {
      status(code) { this.statusCode = code; return this },
      json(data) { this.data = data; return this }
    }
    await requireAdminSecret(req, res, () => { nextCalled = true })
    assert.equal(nextCalled, true)
    assert.equal(req.isAdmin, true)
  })

  await t.test('4. requireAdminSecret rejects invalid credentials with 401', async () => {
    const req = { headers: { 'x-admin-secret': 'wrong_secret' } }
    let nextCalled = false
    const res = {
      status(code) { this.statusCode = code; return this },
      json(data) { this.data = data; return this }
    }
    await requireAdminSecret(req, res, () => { nextCalled = true })
    assert.equal(nextCalled, false)
    assert.equal(res.statusCode, 401)
  })

  await t.test('5. requireAdminSecret respects existing req.isAdmin', async () => {
    const req = { isAdmin: true, headers: {} }
    let nextCalled = false
    const res = {
      status(code) { this.statusCode = code; return this },
      json(data) { this.data = data; return this }
    }
    await requireAdminSecret(req, res, () => { nextCalled = true })
    assert.equal(nextCalled, true)
  })

  await t.test('6. requireWebhookSecret validates X-Webhook-Secret', () => {
    const req = { headers: { 'x-webhook-secret': 'test_webhook_secret_super_key_32_chars' } }
    let nextCalled = false
    const res = {
      status(code) { this.statusCode = code; return this },
      json(data) { this.data = data; return this }
    }
    requireWebhookSecret(req, res, () => { nextCalled = true })
    assert.equal(nextCalled, true)
  })

  await t.test('7. requireWebhookSecret validates body.webhook_secret', () => {
    const req = { headers: {}, body: { webhook_secret: 'test_webhook_secret_super_key_32_chars' } }
    let nextCalled = false
    const res = {
      status(code) { this.statusCode = code; return this },
      json(data) { this.data = data; return this }
    }
    requireWebhookSecret(req, res, () => { nextCalled = true })
    assert.equal(nextCalled, true)
  })

  await t.test('8. requireWebhookSecret rejects invalid secret', () => {
    const req = { headers: { 'x-webhook-secret': 'wrong_secret' } }
    let nextCalled = false
    const res = {
      status(code) { this.statusCode = code; return this },
      json(data) { this.data = data; return this }
    }
    requireWebhookSecret(req, res, () => { nextCalled = true })
    assert.equal(nextCalled, false)
    assert.equal(res.statusCode, 401)
  })

  await t.test('9. requireMerchantOrAdminAuth allows existing req.isAdmin or req.merchantUser', async () => {
    const reqAdmin = { isAdmin: true, headers: {} }
    let nextCalledAdmin = false
    await requireMerchantOrAdminAuth(reqAdmin, {}, () => { nextCalledAdmin = true })
    assert.equal(nextCalledAdmin, true)

    const reqMerchant = { merchantUser: { id: 'm1' }, headers: {} }
    let nextCalledMerchant = false
    await requireMerchantOrAdminAuth(reqMerchant, {}, () => { nextCalledMerchant = true })
    assert.equal(nextCalledMerchant, true)
  })

  await t.test('10. requireMerchantOrAdminAuth allows admin via X-Admin-Secret', async () => {
    const req = { headers: { 'x-admin-secret': 'test_admin_secret_super_secure_32_chars' } }
    let nextCalled = false
    await requireMerchantOrAdminAuth(req, {}, () => { nextCalled = true })
    assert.equal(nextCalled, true)
    assert.equal(req.isAdmin, true)
  })

  await t.test('11. requireMerchantOrAdminAuth rejects request with no credentials', async () => {
    const req = { headers: {} }
    let nextCalled = false
    const res = {
      status(code) { this.statusCode = code; return this },
      json(data) { this.data = data; return this }
    }
    await requireMerchantOrAdminAuth(req, res, () => { nextCalled = true })
    assert.equal(nextCalled, false)
    assert.equal(res.statusCode, 401)
  })
})

