import { Router } from 'express'
import { getAdminClient } from '../services/adminSupabase.js'
import { requireData, requirePlatformUser, requirePlatformMerchant } from '../services/merchantAccount.js'

export const merchantRouter = Router()

// Public system config & notices — accessible to all app clients without requiring active session
merchantRouter.get('/system-config', async (_req, res) => {
  try {
    const row = requireData(await getAdminClient().from('showcase_config').select('value')
      .eq('key', 'system_config').maybeSingle(), 'Load official support settings')
    if (!row) return res.status(503).json({ error: 'Official support settings have not been configured' })
    res.json({ ok: true, config: row.value })
  } catch (error) { res.status(503).json({ error: error.message }) }
})

// Public / Direct Live Chat endpoints for merchant mobile app & admin helpdesk sync
merchantRouter.get('/support/chat', async (req, res) => {
  try {
    const merchantId = req.headers['x-merchant-id'] || req.query.merchant_id || req.headers['x-device-id']
    if (!merchantId) {
      return res.status(400).json({ error: 'Merchant identifier is required' })
    }
    const admin = getAdminClient()
    const { data: messages, error } = await admin
      .from('live_chat_messages')
      .select('*')
      .eq('merchant_id', merchantId)
      .order('created_at', { ascending: true })
      .limit(300)
    if (error) throw error
    res.json({ ok: true, messages: messages || [] })
  } catch (error) {
    res.status(503).json({ error: error.message })
  }
})

merchantRouter.post('/support/chat', async (req, res) => {
  try {
    const body = req.body || {}
    const merchantId = req.headers['x-merchant-id'] || body.merchant_id || req.headers['x-device-id']
    if (!merchantId) {
      return res.status(400).json({ error: 'Merchant identifier is required' })
    }
    const msg = body.message
    if (typeof msg !== 'string' || !msg.trim()) {
      return res.status(400).json({ error: 'Message content is required' })
    }
    const admin = getAdminClient()
    const row = {
      merchant_id: merchantId,
      sender: 'MERCHANT',
      message: msg.trim(),
      created_at: new Date().toISOString()
    }
    const { data: saved, error } = await admin
      .from('live_chat_messages')
      .insert(row)
      .select('*')
      .single()
    if (error) throw error
    res.status(201).json({ ok: true, record: saved })
  } catch (error) {
    res.status(503).json({ error: error.message })
  }
})

// Public / Direct Support Tickets endpoints for merchant mobile app & admin helpdesk sync
merchantRouter.get('/support/tickets', async (req, res) => {
  try {
    const merchantId = req.headers['x-merchant-id'] || req.query.merchant_id || req.headers['x-device-id']
    if (!merchantId) {
      return res.status(400).json({ error: 'Merchant identifier is required' })
    }
    const admin = getAdminClient()
    const { data: tickets, error } = await admin
      .from('support_tickets')
      .select('*')
      .eq('merchant_id', merchantId)
      .order('created_at', { ascending: false })
      .limit(100)
    if (error) throw error
    res.json({ ok: true, tickets: tickets || [] })
  } catch (error) {
    res.status(503).json({ error: error.message })
  }
})

merchantRouter.post('/support/tickets', async (req, res) => {
  try {
    const body = req.body || {}
    const merchantId = req.headers['x-merchant-id'] || body.merchant_id || req.headers['x-device-id']
    if (!merchantId) {
      return res.status(400).json({ error: 'Merchant identifier is required' })
    }
    const subject = body.subject
    const description = body.description
    if (typeof subject !== 'string' || !subject.trim() || typeof description !== 'string' || !description.trim()) {
      return res.status(400).json({ error: 'Subject and description are required' })
    }
    const admin = getAdminClient()
    const row = {
      merchant_id: merchantId,
      business_name: body.business_name || 'My Business',
      email: body.email || null,
      phone: body.phone || null,
      category: body.category || 'GENERAL',
      subject: subject.trim(),
      description: description.trim(),
      status: 'OPEN',
      created_at: new Date().toISOString()
    }
    const { data: saved, error } = await admin
      .from('support_tickets')
      .insert(row)
      .select('*')
      .single()
    if (error) throw error
    res.status(201).json({ ok: true, record: saved })
  } catch (error) {
    res.status(503).json({ error: error.message })
  }
})

merchantRouter.use(requirePlatformUser)
merchantRouter.use(requirePlatformMerchant)

merchantRouter.get('/support', async (req, res) => {
  try {
    const admin = getAdminClient()
    const id = req.platformAccount.merchantId
    const userId = req.platformAccount.merchant?.user_id || req.platformUser?.id

    let ticketQuery = admin.from('support_tickets').select('*')
    if (id && userId && id !== userId) {
      ticketQuery = ticketQuery.or(`merchant_id.eq.${id},merchant_id.eq.${userId}`)
    } else {
      ticketQuery = ticketQuery.eq('merchant_id', id)
    }
    const { data: tickets, error: ticketErr } = await ticketQuery.order('created_at', { ascending: false }).limit(100)
    if (ticketErr) throw ticketErr

    let messageQuery = admin.from('live_chat_messages').select('*')
    if (id && userId && id !== userId) {
      messageQuery = messageQuery.or(`merchant_id.eq.${id},merchant_id.eq.${userId}`)
    } else {
      messageQuery = messageQuery.eq('merchant_id', id)
    }
    const { data: messages, error: msgErr } = await messageQuery.order('created_at', { ascending: false }).limit(200)
    if (msgErr) throw msgErr

    res.json({ ok: true, tickets: tickets || [], messages: (messages || []).reverse() })
  } catch (error) { res.status(503).json({ error: error.message }) }
})

for (const [route, table] of [['messages', 'live_chat_messages'], ['features', 'feature_requests']]) {
  merchantRouter.post(`/support/${route}`, async (req, res) => {
    try {
      const body = req.body || {}
      const merchant = req.platformAccount.merchant
      const row = { merchant_id: merchant.id }
      if (route === 'messages') {
        if (typeof body.message !== 'string' || !body.message.trim() || body.message.length > 10000) {
          return res.status(400).json({ error: 'A message of 1–10000 characters is required' })
        }
        Object.assign(row, { sender: 'MERCHANT', message: body.message.trim() })
      } else {
        const title = route === 'tickets' ? 'subject' : 'title'
        if (typeof body[title] !== 'string' || !body[title].trim() || typeof body.description !== 'string' || !body.description.trim()) {
          return res.status(400).json({ error: 'Title and description are required' })
        }
        Object.assign(row, { business_name: merchant.business_name, email: merchant.email,
          category: body.category || 'GENERAL', [title]: body[title].trim(), description: body.description.trim() })
        if (route === 'tickets') row.phone = merchant.phone
        else row.priority = body.priority || 'MEDIUM'
      }
      const saved = requireData(await getAdminClient().from(table).insert(row).select('*').single(), 'Save support request')
      res.status(201).json({ ok: true, record: saved })
    } catch (error) { res.status(503).json({ error: error.message }) }
  })
}
