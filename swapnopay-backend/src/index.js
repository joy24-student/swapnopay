// SwapnoPay Backend — Main Server
// Express.js + Socket.io
// Bridges Admin Supabase (platform config) ↔ Merchant Supabase (payments) ↔ Web Widget

import 'dotenv/config'
import { createServer } from 'node:http'
import express from 'express'
import { Server as SocketIOServer } from 'socket.io'
import cors from 'cors'
import helmet from 'helmet'
import { rateLimit } from 'express-rate-limit'

import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

import { initAdminSupabase } from './services/adminSupabase.js'
import { initMailer } from './services/mailer.js'
import { paymentRouter } from './routes/payment.js'
import { adminRouter } from './routes/admin.js'
import { keysRouter } from './routes/keys.js'
import shopRouter from './routes/shop.js'
import { kycRouter } from './routes/kyc.js'
import { merchantRouter } from './routes/merchant.js'
import { requirePlatformUser } from './services/merchantAccount.js'
import oauthRouter from './routes/oauth.js'
import { smsGatewayRouter } from './routes/smsGateway.js'
import { startShopWorker } from './services/shopService.js'
import { formRouter } from './routes/form.js'
import { aiVoiceRouter } from './routes/aiVoice.js'
import { subscriptionRouter } from './routes/subscription.js'
import employeeRouter from './routes/employee.js'
import { pinRouter } from './routes/pin.js'
import { aiFormRouter } from './routes/aiForm.js'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

// ──────────────────────────────────────────────────────────────────────────────
// Validate required environment variables (with development defaults)
// ──────────────────────────────────────────────────────────────────────────────
if (!process.env.ADMIN_SUPABASE_URL) {
  process.env.ADMIN_SUPABASE_URL = 'https://tldubojeokgyoclxnzkb.supabase.co'
}
if (!process.env.ADMIN_SUPABASE_ANON_KEY) {
  process.env.ADMIN_SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRsZHVib2plb2tneW9jbHhuemtiIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODc3NjcwODMsImV4cCI6MjEwMzM0MzA4M30.vlgmNEJ0_DpdbsZEQMA2Z82vwY4hwTxpgS4o9p5oEb0'
}
if (!process.env.ADMIN_SUPABASE_SERVICE_ROLE_KEY) {
  process.env.ADMIN_SUPABASE_SERVICE_ROLE_KEY = process.env.ADMIN_SUPABASE_ANON_KEY
}
if (!process.env.ADMIN_SECRET) {
  process.env.ADMIN_SECRET = 'swapnopay_platform_admin_master_secret_2026_super_key_32'
}
if (!process.env.API_KEY_PEPPER) {
  process.env.API_KEY_PEPPER = 'swapnopay_api_key_pepper_cryptographic_secret_salt_32'
}
if (!process.env.PAYMENT_WEBHOOK_SECRET) {
  process.env.PAYMENT_WEBHOOK_SECRET = 'swapnopay_payment_webhook_secret_signature_key_32'
}

const REQUIRED_ENV = [
  'ADMIN_SUPABASE_URL',
  'ADMIN_SUPABASE_SERVICE_ROLE_KEY',
  'ADMIN_SUPABASE_ANON_KEY',
  'ADMIN_SECRET',
  'API_KEY_PEPPER',
  'PAYMENT_WEBHOOK_SECRET',
]
const missingEnv = REQUIRED_ENV.filter(k => !process.env[k])
if (missingEnv.length > 0) {
  console.error('[startup] ❌ Missing required environment variables:', missingEnv.join(', '))
  process.exit(1)
}
if ((process.env.ADMIN_SECRET || '').length < 32) {
  console.error('[startup] ❌ ADMIN_SECRET must be at least 32 characters')
  process.exit(1)
}
if ((process.env.API_KEY_PEPPER || '').length < 32) {
  console.error('[startup] ❌ API_KEY_PEPPER must be at least 32 characters')
  process.exit(1)
}

// ──────────────────────────────────────────────────────────────────────────────
// Initialise Admin Supabase (platform owner's database)
// ──────────────────────────────────────────────────────────────────────────────
try {
  initAdminSupabase()
} catch (err) {
  console.error('[startup] ❌ Admin Supabase initialisation failed:', err.message)
  console.error('[startup]    Set ADMIN_SUPABASE_URL and ADMIN_SUPABASE_SERVICE_ROLE_KEY')
  process.exit(1)
}

// ──────────────────────────────────────────────────────────────────────────────
// Initialise Email Mailer (Nodemailer + Gmail OAuth2) — Non-fatal
// ──────────────────────────────────────────────────────────────────────────────
try {
  initMailer()
} catch (err) {
  console.warn('[startup] ⚠️  Mailer initialisation warning:', err.message)
}

// ──────────────────────────────────────────────────────────────────────────────
// Parse allowed CORS origins and enforce safe defaults
// ──────────────────────────────────────────────────────────────────────────────
const allowedOriginsEnv = (process.env.ALLOWED_ORIGINS || '').trim()
const allowedOrigins = allowedOriginsEnv ? allowedOriginsEnv.split(',').map(s => s.trim()).filter(Boolean) : []

if (allowedOrigins.length === 0) {
  allowedOrigins.push(
    'https://swapnopay.top',
    'https://www.swapnopay.top',
    'https://pay.swapnopay.top',
    'https://api.swapnopay.top',
    'https://admin.swapnopay.top',
    'https://shop.swapnopay.top',
    'http://localhost:5173',
    'http://127.0.0.1:5173'
  )
}

const corsOptions = {
  origin: (origin, cb) => {
    // Allow non-browser requests (curl, server-to-server) which have no Origin header
    if (!origin) return cb(null, true)
    if (allowedOrigins.includes(origin)) return cb(null, true)
    cb(new Error(`CORS: origin ${origin} is not allowed`))
  },
  credentials: true,
  methods: ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
}

// ──────────────────────────────────────────────────────────────────────────────
// Express App
// ──────────────────────────────────────────────────────────────────────────────
const app = express()
const httpServer = createServer(app)

app.use(helmet({ contentSecurityPolicy: false }))
app.set('trust proxy', Number(process.env.TRUST_PROXY_HOPS || 1))
app.disable('x-powered-by')

app.use(cors(corsOptions))
app.options('*', cors(corsOptions))

// Capture raw body for HMAC verification on /v1/payment/verify
// Three base64 documents can exceed the normal API body limit. Authenticate
// before reading this larger payload and keep other routes at 2 MB.
app.use('/v1/kyc/submit', requirePlatformUser, express.json({ limit: '26mb', strict: true }))
app.use(['/v1/forms/upload-image', '/v1/forms/routes', '/routes', '/v1/payment/merchant-config'], express.json({ limit: '26mb', strict: true }))
app.use(express.json({ limit: '10mb', strict: true, verify: (req, _res, buffer) => { req.rawBody = buffer.toString('utf8') } }))

// Global rate limiting
app.use(rateLimit({
  windowMs: 60_000,
  limit: 200,
  standardHeaders: 'draft-8',
  legacyHeaders: false,
  message: { error: 'Too many requests — please slow down' },
}))

// Stricter limit on payment/verify (webhook)
const webhookLimiter = rateLimit({
  windowMs: 60_000,
  limit: 60,
  message: { error: 'Webhook rate limit exceeded' },
})

// ──────────────────────────────────────────────────────────────────────────────
// In-Memory Merchant Heartbeat Map
// Tracks which merchant devices are live via Socket.io (O(1) device check)
// Structure: merchantId → { ts: number, socketId: string, deviceId: string|null }
// ──────────────────────────────────────────────────────────────────────────────
export const merchantHeartbeatMap = new Map()

const HEARTBEAT_GRACE_MS = 3 * 60 * 1000   // 3 minutes — device considered stale
const CLEANUP_INTERVAL_MS = 5 * 60 * 1000  // Clean up stale entries every 5 minutes

setInterval(() => {
  const now = Date.now()
  let cleaned = 0
  for (const [merchantId, hb] of merchantHeartbeatMap.entries()) {
    if (now - hb.ts > HEARTBEAT_GRACE_MS * 2) {
      merchantHeartbeatMap.delete(merchantId)
      cleaned++
    }
  }
  if (cleaned > 0) console.log(`[heartbeat] Cleaned ${cleaned} stale merchant heartbeat(s)`)
}, CLEANUP_INTERVAL_MS)

// ──────────────────────────────────────────────────────────────────────────────
// Socket.io
// ──────────────────────────────────────────────────────────────────────────────
const io = new SocketIOServer(httpServer, {
  cors: corsOptions,
  transports: ['websocket', 'polling'],
  pingTimeout: 30000,
  pingInterval: 15000,   // 15-second ping for fast disconnection detection
  upgradeTimeout: 10000,
  maxHttpBufferSize: 1e6,
})

io.on('connection', (socket) => {
  console.log(`[socket.io] Client connected: ${socket.id}`)

  // ── Widget joins a room for a specific order ──
  socket.on('join_order', ({ order_id } = {}) => {
    if (!order_id || typeof order_id !== 'string' || order_id.length > 100) return
    const room = `order:${order_id}`
    socket.join(room)
    console.log(`[socket.io] ${socket.id} joined order room: ${room}`)
    socket.emit('room_joined', { room, order_id, ts: Date.now() })
  })

  // ── Android merchant app joins a merchant room + marks as online ──
  socket.on('join_merchant', ({ merchant_id, device_id } = {}) => {
    if (!merchant_id || typeof merchant_id !== 'string' || merchant_id.length > 100) return
    const room = `merchant:${merchant_id}`
    socket.join(room)

    // Track merchant as live in heartbeat map
    merchantHeartbeatMap.set(merchant_id, {
      ts: Date.now(),
      socketId: socket.id,
      deviceId: device_id || null,
    })

    console.log(`[socket.io] ${socket.id} joined merchant room: ${room} | device: ${device_id || 'unknown'}`)
    socket.emit('room_joined', { room, merchant_id, ts: Date.now() })

    // Store merchant_id on socket for cleanup on disconnect
    socket.data.merchant_id = merchant_id
    socket.data.device_id = device_id || null
  })

  // ── Merchant device heartbeat — keeps device_active=true in memory ──
  socket.on('merchant_heartbeat', ({ merchant_id, device_id } = {}) => {
    if (!merchant_id || typeof merchant_id !== 'string') return
    merchantHeartbeatMap.set(merchant_id, {
      ts: Date.now(),
      socketId: socket.id,
      deviceId: device_id || socket.data.device_id || null,
    })
    // Acknowledge heartbeat with server timestamp
    socket.emit('heartbeat_ack', { ts: Date.now(), merchant_id })
  })

  // ── Widget pings backend to confirm connection is alive ──
  socket.on('ping_backend', (cb) => {
    if (typeof cb === 'function') cb({ ts: Date.now(), ok: true })
    else socket.emit('pong_backend', { ts: Date.now(), ok: true })
  })

  socket.on('disconnect', reason => {
    console.log(`[socket.io] Client disconnected: ${socket.id} — reason: ${reason}`)

    // Mark merchant offline after grace period if it was the last socket
    const merchant_id = socket.data.merchant_id
    if (merchant_id) {
      // Give 5 minutes grace before deleting in-memory heartbeat (handles Android backgrounding, network handover, and quick reconnects)
      setTimeout(() => {
        const hb = merchantHeartbeatMap.get(merchant_id)
        if (hb && hb.socketId === socket.id) {
          // Check if last recorded heartbeat is older than 5 minutes
          if (Date.now() - (hb.lastSeen || 0) > 5 * 60 * 1000) {
            merchantHeartbeatMap.delete(merchant_id)
            console.log(`[heartbeat] Merchant ${merchant_id} marked offline after disconnect (5min grace elapsed)`)
          }
        }
      }, 5 * 60 * 1000)
    }
  })

  socket.on('error', err => {
    console.error(`[socket.io] Socket error: ${socket.id}:`, err.message)
  })
})

// ──────────────────────────────────────────────────────────────────────────────
// Routes
// ──────────────────────────────────────────────────────────────────────────────

// Middleware: Attach Socket.io to req
app.use((req, _res, next) => {
  req.io = io
  next()
})

// Static file hosting for uploads (KYC docs, receipts, shop assets)
app.use('/uploads', express.static(path.join(__dirname, '../uploads')))

// Direct API Gateway Welcome / Info route (for https://api.swapnopay.top/)
app.get('/', (req, res, next) => {
  const host = (req.get('host') || '').toLowerCase()
  if (host.startsWith('api.') || (req.headers.accept && req.headers.accept.includes('application/json'))) {
    return res.json({
      ok: true,
      service: 'SwapnoPay Central REST & WebSocket Gateway',
      version: '3.0.0',
      status: 'healthy',
      endpoints: {
        health: '/healthz',
        v1: '/v1',
        payment: '/v1/payment',
        forms: '/v1/forms',
        docs: 'https://pay.swapnopay.top/docs.html',
        portal: 'https://pay.swapnopay.top/portal.html'
      },
      timestamp: new Date().toISOString()
    })
  }
  next()
})

// Serve web root (checkout widget, hosted forms runner, docs)
const webDirCandidates = [
  path.resolve(__dirname, '../web'),
  path.resolve(__dirname, '../../web'),
  '/var/www/swapnopay/web',
  path.resolve(process.cwd(), 'web')
]
const webDir = webDirCandidates.find(p => fs.existsSync(p)) || path.resolve(__dirname, '../web')
app.use(express.static(webDir))

// Friendly aliases for Documentation, Developer Console and Widget
app.get(['/portal', '/console', '/developer', '/dev', '/sandbox', '/keys'], (_req, res) => {
  res.redirect(301, '/portal.html')
})
app.get(['/docs', '/doc', '/documentation'], (_req, res) => {
  res.redirect(301, '/docs.html')
})
app.get(['/widget'], (_req, res) => {
  res.redirect(301, '/widget.html')
})
app.get(['/voice-call', '/call', '/voice-calling', '/voice-agent'], (req, res) => {
  const queryStr = req.url.includes('?') ? req.url.slice(req.url.indexOf('?')) : ''
  res.redirect(301, `/voice-call.html${queryStr}`)
})

// Online MCQ & Quiz Exam Portal (/exam, /quiz, /assessment)
app.get(['/exam', '/exams', '/quiz', '/assessment'], (req, res) => {
  const queryStr = req.url.includes('?') ? req.url.slice(req.url.indexOf('?')) : ''
  res.redirect(301, `/exam.html${queryStr}`)
})

// Hosted Checkout Form Dynamic Slugs (/f/:slug, /forms/:slug, /form/:slug)
app.get(['/f/:slug', '/forms/:slug', '/form/:slug'], (req, res) => {
  const formHtml = path.join(webDir, 'form.html')
  if (fs.existsSync(formHtml)) {
    return res.sendFile(formHtml)
  }
  res.redirect(`/form.html?slug=${encodeURIComponent(req.params.slug)}`)
})

// Public health check
const healthCheckHandler = (_req, res) => {
  res.json({
    ok: true,
    status: 'healthy',
    service: 'swapnopay-backend',
    version: '3.0.0',
    database: 'admin-supabase',
    socket_io_clients: io.engine.clientsCount,
    merchant_online_count: merchantHeartbeatMap.size,
    timestamp: new Date().toISOString(),
  })
}
app.get('/healthz', healthCheckHandler)
app.get('/health', healthCheckHandler)

// Payment routes (pass both io + heartbeatMap)
app.use('/v1/payment', webhookLimiter, paymentRouter(io, merchantHeartbeatMap))

// Public Showcase config route
app.get('/v1/showcase', async (_req, res) => {
  try {
    const { getShowcaseConfig } = await import('./services/adminSupabase.js')
    const config = await getShowcaseConfig()
    res.json({ ok: true, config: config || {} })
  } catch (err) {
    res.json({ ok: true, config: {} })
  }
})

// Public System Notice / Announcement route (accessible by mobile apps without requiring user token)
app.get('/v1/system-notice', async (_req, res) => {
  try {
    const { getShowcaseConfig } = await import('./services/adminSupabase.js')
    const config = await getShowcaseConfig('system_config') || {}
    res.json({
      ok: true,
      system_notice: config.system_notice || '',
      maintenance_mode: Boolean(config.maintenance_mode),
      maintenance_message: config.maintenance_message || '',
      support_hotline: config.support_hotline || '',
      support_email: config.support_email || '',
      support_whatsapp: config.support_whatsapp || '',
      updated_at: config.updated_at || new Date().toISOString(),
    })
  } catch (err) {
    res.json({ ok: true, system_notice: '', maintenance_mode: false })
  }
})

// Admin routes (protected by ADMIN_SECRET)
app.use('/v1/admin', adminRouter)
app.use('/v1/admin/keys', keysRouter)
app.use('/v1/shop', shopRouter)
startShopWorker()

// KYC identity verification routes
app.use('/v1/kyc', kycRouter)
app.use('/v1/merchant', merchantRouter)

// Supabase OAuth 2.0 control plane routes
app.use('/v1/oauth', oauthRouter)
app.use('/functions/v1', oauthRouter)

// Branded Hosted Forms Route Registration & Form Processing API
const hostedFormRoutes = formRouter(io)
app.use('/v1', hostedFormRoutes)
app.use('/', hostedFormRoutes)

// Enterprise SMS Gateway & OTP Verification API
app.use('/v1/sms-gateway', smsGatewayRouter(io, merchantHeartbeatMap))

// AI Voice Calling & Automated Receptionist API
app.use('/v1/voice', aiVoiceRouter(io))

// Platform Subscription & Anti-Piracy Billing API
app.use('/v1/subscription', subscriptionRouter)

// Employee App, Staff Portal & Live Monitor API
app.use('/v1/employee', employeeRouter)

// Merchant App PIN management (cloud-synced, hash-only)
app.use('/v1/pin', pinRouter)

// AI Form Generator (Gemini LLM) API
app.use('/v1/ai', aiFormRouter)

// 404
app.use((_req, res) => {
  res.status(404).json({ error: 'Not found' })
})

// Error handler
app.use((err, _req, res, _next) => {
  console.error('[express] Unhandled error:', err.message)
  res.status(err.status || 500).json({ error: err.message || 'Internal server error' })
})

// ──────────────────────────────────────────────────────────────────────────────
// Start Server
// ──────────────────────────────────────────────────────────────────────────────
const PORT = Number(process.env.PORT || 4000)
httpServer.listen(PORT, '0.0.0.0', () => {
  console.log(`
╔══════════════════════════════════════════════════════════════════╗
║       SwapnoPay Backend v3 — Production Mode                     ║
╠══════════════════════════════════════════════════════════════════╣
║  Admin DB     → Admin Supabase (platform owner DB)               ║
║  Merchant DB  → Per-merchant Supabase (cross-DB bridge)          ║
║  HTTP/WS      → http://0.0.0.0:${PORT}                            ║
║  Health       → GET  /healthz                                    ║
║  Config       → GET  /v1/payment/config?merchant_id=<id>         ║
║  Device Check → GET  /v1/payment/device-status?merchant_id=<id>  ║
║  Notify       → POST /v1/payment/notify                          ║
║  Verify       → POST /v1/payment/verify  (webhook secret)        ║
║  Admin        → GET/POST /v1/admin/gateway-settings              ║
║  API Keys     → GET/POST /v1/admin/keys                          ║
╚══════════════════════════════════════════════════════════════════╝
`)
})

// Graceful shutdown
for (const signal of ['SIGTERM', 'SIGINT']) {
  process.on(signal, () => {
    console.log(`[shutdown] Received ${signal} — closing server`)
    httpServer.close(() => {
      console.log('[shutdown] HTTP server closed')
      process.exit(0)
    })
  })
}
