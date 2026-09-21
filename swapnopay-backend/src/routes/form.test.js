import test from 'node:test'
import assert from 'node:assert/strict'
import express from 'express'
import { formRouter, handleFormPaymentPaid, parseAmountFromText, orderToFormSubmissionMap } from './form.js'

function createTestApp() {
  const app = express()
  app.use(express.json())
  app.use('/v1', formRouter(null))
  return app
}

test('Form Router: Registration, Resolution, and Submissions', async (t) => {
  const app = createTestApp()
  const server = app.listen(0)
  const port = server.address().port
  const baseUrl = `http://127.0.0.1:${port}/v1`

  t.after(() => {
    server.close()
  })

  await t.test('1. Register branded form route with full snapshot', async () => {
    const res = await fetch(`${baseUrl}/routes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        form_id: 'test-form-uuid-001',
        slug: 'conference-2026',
        merchant_id: 'merchant-test-123',
        payload: {
          id: 'test-form-uuid-001',
          title: 'Annual Tech Summit 2026',
          description: 'Register for passes and workshop sessions.',
          slug: 'conference-2026',
          amount: 0,
          status: 'PUBLISHED',
          fields: [
            { id: 'f_prod', type: 'PRODUCT', label: 'VIP Pass', minValue: 1500.00, defaultValue: 'SKU-VIP-01', required: true },
            { id: 'f_dept', type: 'TEXT', label: 'Company / Dept', required: false }
          ],
          products: [],
          theme: {
            primary_color: '#10B981',
            enable_payment: true,
            redirect_type: 'SUCCESS_MSG',
            success_message: 'Your registration is complete!'
          }
        }
      })
    })

    assert.equal(res.status, 200)
    const data = await res.json()
    assert.equal(data.ok, true)
    assert.ok(data.public_url.includes('/f/conference-2026'))
  })

  await t.test('2. Resolve form by slug', async () => {
    const res = await fetch(`${baseUrl}/forms/conference-2026`)
    assert.equal(res.status, 200)
    const data = await res.json()
    assert.equal(data.ok, true)
    assert.equal(data.form.title, 'Annual Tech Summit 2026')
    assert.equal(data.form.fields.length, 2)
    assert.equal(data.form.fields[0].type, 'PRODUCT')
  })

  await t.test('3. Submit form with product block (payment required)', async () => {
    const res = await fetch(`${baseUrl}/forms/conference-2026/submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_name: 'Tanvir Ahmed',
        customer_phone: '01711223344',
        customer_email: 'tanvir@example.com',
        answers: {
          'f_prod': { product: 'VIP Pass', price: 1500.00, quantity: 2, total_bdt: 3000.00 },
          'f_dept': 'Engineering'
        },
        payment_method: 'bKash'
      })
    })

    assert.equal(res.status, 200)
    const data = await res.json()
    assert.equal(data.ok, true)
    assert.equal(data.payment_required, true)
    assert.equal(data.amount, 3000.00)
    assert.ok(data.order_id)
    assert.match(data.order_id, /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i)
    assert.ok(data.redirect_url.includes('widget.html'))
    assert.ok(data.redirect_url.includes('merchant_id=merchant-test-123'))
  })

  await t.test('4. Register & Submit non-payment form (contact / feedback)', async () => {
    await fetch(`${baseUrl}/routes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        form_id: 'free-form-uuid-002',
        slug: 'contact-inquiry',
        merchant_id: 'merchant-test-123',
        payload: {
          id: 'free-form-uuid-002',
          title: 'Business Inquiries',
          slug: 'contact-inquiry',
          amount: 0,
          status: 'PUBLISHED',
          fields: [
            { id: 'f_msg', type: 'NOTES', label: 'Your Message', required: true }
          ],
          products: [],
          theme: {
            enable_payment: false,
            redirect_type: 'REDIRECT_URL',
            redirect_url: 'https://myshop.com/thank-you',
            redirect_delay_seconds: 3
          }
        }
      })
    })

    const res = await fetch(`${baseUrl}/forms/contact-inquiry/submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_name: 'Sara Karim',
        customer_phone: '01899887766',
        answers: { 'f_msg': 'We would like a wholesale quote.' }
      })
    })

    assert.equal(res.status, 200)
    const data = await res.json()
    assert.equal(data.ok, true)
    assert.equal(data.payment_required, false)
    assert.equal(data.redirect_type, 'REDIRECT_URL')
    assert.equal(data.redirect_url, 'https://myshop.com/thank-you')
    assert.equal(data.redirect_delay, 3)
  })

  await t.test('5. Reject spam submission with honeypot', async () => {
    const res = await fetch(`${baseUrl}/forms/contact-inquiry/submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_name: 'Bot User',
        _hp_check: 'i-am-a-bot'
      })
    })

    assert.equal(res.status, 400)
    const data = await res.json()
    assert.equal(data.ok, false)
  })

  await t.test('6. Reject submission when deadline is expired', async () => {
    await fetch(`${baseUrl}/routes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        form_id: 'expired-form-uuid-003',
        slug: 'expired-scholarship',
        merchant_id: 'merchant-test-123',
        payload: {
          id: 'expired-form-uuid-003',
          title: 'Scholarship Form',
          slug: 'expired-scholarship',
          status: 'PUBLISHED',
          fields: [],
          products: [],
          theme: {
            enable_closing_timeline: true,
            closing_deadline_epoch: Date.now() - 60000, // 1 minute ago
            closed_message: 'Application deadline has passed.'
          }
        }
      })
    })

    const res = await fetch(`${baseUrl}/forms/expired-scholarship/submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ customer_name: 'Late Applicant' })
    })

    assert.equal(res.status, 409)
    const data = await res.json()
    assert.equal(data.ok, false)
    assert.ok(data.error.includes('deadline'))
  })

  await t.test('8. Upload product image via POST /v1/forms/upload-image', async () => {
    // 1x1 transparent PNG base64
    const sampleBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='

    const res = await fetch(`${baseUrl}/forms/upload-image`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        image: sampleBase64,
        filename: 'sample_product.png'
      })
    })

    assert.equal(res.status, 201)
    const data = await res.json()
    assert.equal(data.ok, true)
    assert.ok(data.url)
    assert.ok(data.filename)
    assert.ok(data.url.includes('/uploads/products/'))
  })

  await t.test('9. Option price parsing & form submission with radio/donation tiers', async () => {
    // Verify parseAmountFromText unit tests
    assert.equal(parseAmountFromText('VIP Pass (৳1500)'), 1500)
    assert.equal(parseAmountFromText('Batch 12 (৳4,500)'), 4500)
    assert.equal(parseAmountFromText('৳500 (Generous)'), 500)
    assert.equal(parseAmountFromText('1000 Tk'), 1000)
    assert.equal(parseAmountFromText('250 BDT'), 250)
    assert.equal(parseAmountFromText('Standard Free Option'), 0)

    // Register a form with radio options containing prices
    const regRes = await fetch(`${baseUrl}/routes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        form_id: 'pricing-form-uuid-004',
        slug: 'workshop-event',
        merchant_id: 'merchant-test-123',
        payload: {
          id: 'pricing-form-uuid-004',
          title: 'Full Stack Workshop',
          slug: 'workshop-event',
          status: 'PUBLISHED',
          amount: 0,
          fields: [
            {
              id: 'f_tier',
              type: 'RADIO',
              label: 'Ticket Tier',
              options: ['Standard Pass (৳1500)', 'VIP Pass (৳3500)'],
              required: true
            }
          ],
          products: [],
          theme: {
            enable_payment: true
          }
        }
      })
    })
    assert.equal(regRes.status, 200)

    // Submit with selected radio tier
    const subRes = await fetch(`${baseUrl}/forms/workshop-event/submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_name: 'Sadia Rahman',
        customer_phone: '01700000000',
        answers: {
          'f_tier': 'VIP Pass (৳3500)'
        },
        payment_method: 'Nagad'
      })
    })

    assert.equal(subRes.status, 200)
    const subData = await subRes.json()
    assert.equal(subData.ok, true)
    assert.equal(subData.payment_required, true)
    assert.equal(subData.amount, 3500)
    assert.ok(subData.order_id)
    assert.ok(subData.redirect_url.includes('amount=3500'))
    assert.ok(subData.redirect_url.includes('merchant_id=merchant-test-123'))
    assert.ok(subData.redirect_url.includes('success_url='))
  })

  await t.test('10. handleFormPaymentPaid marks submission PAID and records trx_id', async () => {
    // Grab the order mapping created from test 9
    let targetOrderId = null
    for (const [oId, mapping] of orderToFormSubmissionMap.entries()) {
      if (mapping.form_id === 'pricing-form-uuid-004') {
        targetOrderId = oId
        break
      }
    }
    assert.ok(targetOrderId, 'Expected orderId in orderToFormSubmissionMap')

    // Trigger payment paid handler
    const mockTrxId = 'TRX99887766'
    const updated = await handleFormPaymentPaid(targetOrderId, mockTrxId, 3500, null)
    assert.ok(updated)
    assert.equal(updated.payment_status, 'PAID')
    assert.equal(updated.trx_id, mockTrxId)
  })
})

