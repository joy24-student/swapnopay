import test from 'node:test'
import assert from 'node:assert/strict'
import express from 'express'
import { aiFormRouter } from './aiForm.js'

function createTestApp() {
  const app = express()
  app.use(express.json())
  app.use('/v1/ai', aiFormRouter)
  return app
}

test('AI Form Generator Route Suite', async (t) => {
  const app = createTestApp()
  const server = app.listen(0)
  const port = server.address().port
  const baseUrl = `http://127.0.0.1:${port}/v1/ai`

  t.after(() => {
    server.close()
  })

  await t.test('1. Rejects missing or too short prompt', async () => {
    const res = await fetch(`${baseUrl}/generate-form`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ prompt: 'ab' })
    })
    assert.strictEqual(res.status, 400)
    const data = await res.json()
    assert.strictEqual(data.success, false)
    assert.ok(data.error)
  })

  await t.test('2. Returns fallback: true when no Gemini key is configured', async () => {
    // Ensure no key is set for this test
    const origKey = process.env.GEMINI_API_KEY
    delete process.env.GEMINI_API_KEY

    try {
      const res = await fetch(`${baseUrl}/generate-form`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          prompt: 'Registration form for Cricket Tournament',
          merchant_id: 'non-existent-merchant-123'
        })
      })

      assert.strictEqual(res.status, 200)
      const data = await res.json()
      assert.strictEqual(data.success, false)
      assert.strictEqual(data.fallback, true)
      assert.strictEqual(data.reason, 'no_api_key')
    } finally {
      if (origKey) process.env.GEMINI_API_KEY = origKey
    }
  })
})
