// SwapnoPay Enterprise SMS Gateway Router
// Exposes OTP verification & custom transactional SMS dispatch API for external websites,
// with WebSocket / HTTP polling bridge to merchant Android devices.

import express from 'express'
import crypto from 'node:crypto'

export function smsGatewayRouter(io, heartbeatMap = new Map()) {
  const router = express.Router()

  // In-memory OTP storage: Map<phone, OtpRecord>
  // OtpRecord: { otp_id, phone, code, purpose, merchant_id, expires_at, verified, attempts }
  const otpStore = new Map()

  // In-memory Gateway SMS Jobs Queue: Map<job_id, JobRecord>
  // JobRecord: { job_id, merchant_id, phone, message, type, status, webhook_url, created_at, sent_at, error }
  const jobQueue = new Map()

  // Clean expired OTPs every 5 minutes
  setInterval(() => {
    const now = Date.now()
    for (const [phone, record] of otpStore.entries()) {
      if (record.expires_at < now) {
        otpStore.delete(phone)
      }
    }
  }, 5 * 60 * 1000)

  // ── Authentication Middleware for External Websites ──
  const authenticateGatewayApiKey = (req, res, next) => {
    const rawHeader = req.headers['x-api-key'] || req.headers['authorization'] || ''
    const apiKey = rawHeader.startsWith('Bearer ') ? rawHeader.slice(7).trim() : rawHeader.trim()

    // If API key is present, extract or associate merchant
    if (apiKey) {
      req.merchant_id = apiKey.startsWith('sp_gw_m_')
        ? apiKey.replace('sp_gw_m_', '').split('_')[0]
        : apiKey
      return next()
    }

    // Allow sandbox / demo merchant ONLY in test or development environments
    if (process.env.NODE_ENV === 'test' || process.env.NODE_ENV === 'development') {
      req.merchant_id = req.query.merchant_id || req.body?.merchant_id || '00000000-0000-0000-0000-000000000001'
      return next()
    }

    return res.status(401).json({ ok: false, error: 'Unauthorized: Missing x-api-key header' })
  }

  // ────────────────────────────────────────────────────────────────────────────
  // 1. POST /v1/sms-gateway/send-otp
  // External websites request OTP generation and SIM dispatch
  // ────────────────────────────────────────────────────────────────────────────
  router.post('/send-otp', authenticateGatewayApiKey, async (req, res) => {
    try {
      const { phone, purpose = 'Verification', expiryMinutes = 5, length = 6, template } = req.body

      if (!phone || typeof phone !== 'string' || phone.trim().length < 10) {
        return res.status(400).json({ ok: false, error: 'Valid phone number is required' })
      }

      const cleanPhone = phone.trim().replace(/\s+/g, '')
      const codeLength = Math.min(Math.max(Number(length) || 6, 4), 8)
      
      // Generate cryptographically secure random numeric OTP
      const minNum = Math.pow(10, codeLength - 1)
      const maxNum = Math.pow(10, codeLength) - 1
      const otpCode = crypto.randomInt(minNum, maxNum + 1).toString()

      const ttlMs = (Number(expiryMinutes) || 5) * 60 * 1000
      const expiresAt = Date.now() + ttlMs
      const otpId = `otp_${crypto.randomUUID().replace(/-/g, '').slice(0, 16)}`
      const jobId = `job_${crypto.randomUUID().replace(/-/g, '').slice(0, 16)}`

      // Store in OTP registry
      otpStore.set(cleanPhone, {
        otp_id: otpId,
        phone: cleanPhone,
        code: otpCode,
        purpose: String(purpose).slice(0, 50),
        merchant_id: req.merchant_id,
        expires_at: expiresAt,
        verified: false,
        attempts: 0,
      })

      // Formulate Bengali / English dual message text
      const defaultMsg = `আপনার SwapnoPay যাচাইকরণ ওটিপি কোড হলো: ${otpCode}। কোডটির মেয়াদ ${expiryMinutes} মিনিট। কাউকে এটি জানাবেন না।`
      const messageText = template ? template.replace('{code}', otpCode).replace('{otp}', otpCode) : defaultMsg

      // Enqueue job for merchant Android device
      const jobRecord = {
        job_id: jobId,
        merchant_id: req.merchant_id,
        phone: cleanPhone,
        message: messageText,
        type: 'GATEWAY_OTP',
        status: 'QUEUED',
        created_at: Date.now(),
      }
      jobQueue.set(jobId, jobRecord)

      // Real-time Push via Socket.IO if device is connected
      if (io) {
        io.to(`merchant:${req.merchant_id}`).emit('sms_gateway_dispatch', {
          job_id: jobId,
          phone: cleanPhone,
          message: messageText,
          type: 'GATEWAY_OTP',
          created_at: Date.now(),
        })
      }

      return res.status(200).json({
        ok: true,
        otp_id: otpId,
        job_id: jobId,
        phone: cleanPhone,
        purpose,
        expires_in_seconds: Math.floor(ttlMs / 1000),
        message: 'OTP queued for SIM dispatch',
      })
    } catch (err) {
      console.error('[sms-gateway] send-otp error:', err)
      return res.status(500).json({ ok: false, error: err.message || 'Failed to dispatch OTP' })
    }
  })

  // ────────────────────────────────────────────────────────────────────────────
  // 2. POST /v1/sms-gateway/verify-otp
  // External websites verify the OTP code submitted by end-users
  // ────────────────────────────────────────────────────────────────────────────
  router.post('/verify-otp', authenticateGatewayApiKey, async (req, res) => {
    try {
      const { phone, code, purpose } = req.body

      if (!phone || !code) {
        return res.status(400).json({ ok: false, error: 'Phone and OTP code are required' })
      }

      const cleanPhone = phone.trim().replace(/\s+/g, '')
      const cleanCode = String(code).trim()
      const record = otpStore.get(cleanPhone)

      if (!record) {
        return res.status(404).json({ ok: false, verified: false, error: 'No active OTP request found for this phone number' })
      }

      if (record.verified) {
        return res.status(400).json({ ok: false, verified: false, error: 'This OTP has already been used' })
      }

      if (Date.now() > record.expires_at) {
        otpStore.delete(cleanPhone)
        return res.status(410).json({ ok: false, verified: false, error: 'OTP has expired. Please request a new code.' })
      }

      record.attempts += 1
      if (record.attempts > 5) {
        otpStore.delete(cleanPhone)
        return res.status(429).json({ ok: false, verified: false, error: 'Too many incorrect attempts. OTP invalidated.' })
      }

      if (record.code !== cleanCode) {
        return res.status(400).json({
          ok: false,
          verified: false,
          error: 'Incorrect OTP code',
          remaining_attempts: Math.max(0, 5 - record.attempts),
        })
      }

      // Mark verified & consume OTP
      record.verified = true
      otpStore.delete(cleanPhone)

      return res.status(200).json({
        ok: true,
        verified: true,
        phone: cleanPhone,
        purpose: record.purpose || purpose || 'Verification',
        message: 'OTP verified successfully',
      })
    } catch (err) {
      console.error('[sms-gateway] verify-otp error:', err)
      return res.status(500).json({ ok: false, error: err.message || 'Failed to verify OTP' })
    }
  })

  // ────────────────────────────────────────────────────────────────────────────
  // 3. POST /v1/sms-gateway/send
  // External websites send arbitrary transactional SMS through merchant's SIM
  // ────────────────────────────────────────────────────────────────────────────
  router.post('/send', authenticateGatewayApiKey, async (req, res) => {
    try {
      const { phone, message, priority = 'NORMAL', webhook_url } = req.body

      if (!phone || typeof phone !== 'string' || phone.trim().length < 10) {
        return res.status(400).json({ ok: false, error: 'Valid recipient phone is required' })
      }
      if (!message || typeof message !== 'string' || message.trim().length === 0) {
        return res.status(400).json({ ok: false, error: 'Message content cannot be empty' })
      }

      const cleanPhone = phone.trim().replace(/\s+/g, '')
      const jobId = `job_${crypto.randomUUID().replace(/-/g, '').slice(0, 16)}`

      const jobRecord = {
        job_id: jobId,
        merchant_id: req.merchant_id,
        phone: cleanPhone,
        message: message.trim(),
        priority,
        webhook_url: webhook_url || null,
        type: 'GATEWAY_CUSTOM',
        status: 'QUEUED',
        created_at: Date.now(),
      }
      jobQueue.set(jobId, jobRecord)

      // Real-time Push via Socket.IO
      if (io) {
        io.to(`merchant:${req.merchant_id}`).emit('sms_gateway_dispatch', {
          job_id: jobId,
          phone: cleanPhone,
          message: message.trim(),
          priority,
          type: 'GATEWAY_CUSTOM',
          created_at: Date.now(),
        })
      }

      return res.status(200).json({
        ok: true,
        job_id: jobId,
        phone: cleanPhone,
        status: 'QUEUED',
        message: 'Message queued for SIM cellular dispatch',
      })
    } catch (err) {
      console.error('[sms-gateway] send error:', err)
      return res.status(500).json({ ok: false, error: err.message || 'Failed to queue message' })
    }
  })

  // ────────────────────────────────────────────────────────────────────────────
  // 4. GET /v1/sms-gateway/device/pending
  // Android device polling fallback for pending SMS jobs
  // ────────────────────────────────────────────────────────────────────────────
  router.get('/device/pending', async (req, res) => {
    try {
      const merchantId = req.query.merchant_id || '00000000-0000-0000-0000-000000000001'
      const pendingJobs = []

      for (const [id, job] of jobQueue.entries()) {
        if (job.merchant_id === merchantId && job.status === 'QUEUED') {
          pendingJobs.push({
            job_id: job.job_id,
            phone: job.phone,
            message: job.message,
            type: job.type,
            created_at: job.created_at,
          })
          job.status = 'IN_PROGRESS'
          if (pendingJobs.length >= 20) break
        }
      }

      return res.status(200).json({ ok: true, jobs: pendingJobs, count: pendingJobs.length })
    } catch (err) {
      return res.status(500).json({ ok: false, error: err.message })
    }
  })

  // ────────────────────────────────────────────────────────────────────────────
  // 5. POST /v1/sms-gateway/device/status
  // Android device reports delivery outcome back to backend
  // ────────────────────────────────────────────────────────────────────────────
  router.post('/device/status', async (req, res) => {
    try {
      const { job_id, status, error, sent_at } = req.body

      if (!job_id) {
        return res.status(400).json({ ok: false, error: 'job_id is required' })
      }

      const job = jobQueue.get(job_id)
      if (job) {
        job.status = status || 'SENT'
        job.sent_at = sent_at || Date.now()
        job.error = error || null

        // Trigger external webhook if requested by external developer
        if (job.webhook_url && typeof fetch === 'function') {
          fetch(job.webhook_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              event: 'sms.status_update',
              job_id: job.job_id,
              phone: job.phone,
              status: job.status,
              sent_at: job.sent_at,
              error: job.error,
            }),
          }).catch(webhookErr => console.warn('[sms-gateway] Webhook dispatch error:', webhookErr.message))
        }
      }

      return res.status(200).json({ ok: true, updated: true })
    } catch (err) {
      return res.status(500).json({ ok: false, error: err.message })
    }
  })

  // ────────────────────────────────────────────────────────────────────────────
  // 6. GET /v1/sms-gateway/stats
  // Gateway throughput and device status summary
  // ────────────────────────────────────────────────────────────────────────────
  router.get('/stats', authenticateGatewayApiKey, (req, res) => {
    let queued = 0
    let sent = 0
    let failed = 0

    for (const job of jobQueue.values()) {
      if (job.merchant_id === req.merchant_id) {
        if (job.status === 'QUEUED' || job.status === 'IN_PROGRESS') queued++
        else if (job.status === 'SENT') sent++
        else if (job.status === 'FAILED') failed++
      }
    }

    const isOnline = heartbeatMap.has(req.merchant_id)

    return res.status(200).json({
      ok: true,
      merchant_id: req.merchant_id,
      device_online: isOnline,
      stats: {
        queued,
        sent,
        failed,
        total_handled: queued + sent + failed,
        active_otps: otpStore.size,
      },
    })
  })

  return router
}

export default smsGatewayRouter
