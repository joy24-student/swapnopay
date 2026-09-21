// SwapnoPay Backend — Supabase OAuth 2.0 Control Plane Router
// Native VPS backend implementation for Supabase OAuth Management API
// Replaces edge functions with dedicated, ultra-reliable Node.js endpoints.

import { Router } from 'express'
import crypto from 'crypto'
import { getAdminClient } from '../services/adminSupabase.js'
import { lookupMerchantInAdminDb, requirePlatformUser, requireData } from '../services/merchantAccount.js'
import { provisionProject } from '../services/provisionService.js'

const router = Router()

// Default Configuration
const DEFAULT_CLIENT_ID = '5d3dcd9b-1acf-4e31-96d2-d673af42a18b'
const DEFAULT_REDIRECT_URI = 'https://api.swapnopay.top/v1/oauth/callback'

function getOAuthCredentials() {
  const clientId = process.env.SUPABASE_OAUTH_CLIENT_ID || DEFAULT_CLIENT_ID
  const clientSecret = process.env.SUPABASE_OAUTH_CLIENT_SECRET || ''
  const redirectUri = process.env.SUPABASE_OAUTH_REDIRECT_URI || DEFAULT_REDIRECT_URI
  return { clientId, clientSecret, redirectUri }
}

// In-memory fallback caches (ensure reliability if RLS policy limits DB direct writes)
const oauthTxCache = new Map()
const connectionsCache = new Map()

// ──────────────────────────────────────────────────────────────────────────────
// Helpers: PKCE & State
// ──────────────────────────────────────────────────────────────────────────────
function generateState() {
  return crypto.randomBytes(24).toString('base64url')
}

function generateCodeVerifier() {
  return crypto.randomBytes(32).toString('base64url')
}

function generateCodeChallenge(verifier) {
  return crypto.createHash('sha256').update(verifier).digest('base64url')
}

function hashState(rawState) {
  return crypto.createHash('sha256').update(rawState).digest('hex')
}

function escapeHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

// Fetch or refresh access token with robust fallback lookup (user_id -> tx_id -> latest active connection)
async function getValidAccessToken(userId, txId) {
  const admin = getAdminClient()
  let conn = null

  // 1. Lookup by user_id
  if (userId) {
  // 1. Lookup in-memory cache
  if (userId && connectionsCache.has(userId)) {
    conn = connectionsCache.get(userId)
  }
  if (!conn && txId && connectionsCache.has(txId)) {
    conn = connectionsCache.get(txId)
  }

  // 2. Lookup by user_id in DB
  if (!conn && userId) {
    const { data } = await admin
      .from('supabase_connections')
      .select('*')
      .eq('user_id', userId)
      .maybeSingle()
    if (data) conn = data
  }

  // 2. Lookup by tx_id via control_oauth_transactions
  // 3. Lookup by tx_id via control_oauth_transactions
  if (!conn && (txId || (userId && userId.includes('-')))) {
    const lookupId = txId || userId
    const { data: tx } = await admin
      .from('control_oauth_transactions')
      .select('user_id')
      .eq('id', lookupId)
      .maybeSingle()
    if (tx?.user_id) {
      const { data } = await admin
        .from('supabase_connections')
        .select('*')
        .eq('user_id', tx.user_id)
    const cachedTx = oauthTxCache.get(lookupId)
    const matchedUserId = cachedTx?.user_id
    if (matchedUserId && connectionsCache.has(matchedUserId)) {
      conn = connectionsCache.get(matchedUserId)
    }

    if (!conn) {
      const { data: tx } = await admin
        .from('control_oauth_transactions')
        .select('user_id')
        .eq('id', lookupId)
        .maybeSingle()
      if (data) conn = data
      if (tx?.user_id) {
        const { data } = await admin
          .from('supabase_connections')
          .select('*')
          .eq('user_id', tx.user_id)
          .maybeSingle()
        if (data) conn = data
      }
    }
  }

  if (!conn) {
    throw new Error('No active Supabase connection found for user.')
  }

  const isExpired = new Date(conn.access_token_expires_at) <= new Date(Date.now() + 60000)
  if (!isExpired && conn.encrypted_access_token) {
    return conn.encrypted_access_token
  }

  // Refresh Token Exchange
  console.log(`[oauth] Access token expired for ${conn.user_id}, refreshing...`)
  const { clientId, clientSecret } = getOAuthCredentials()
  const basicAuth = Buffer.from(`${clientId}:${clientSecret}`).toString('base64')

  const refreshParams = new URLSearchParams()
  refreshParams.append('grant_type', 'refresh_token')
  refreshParams.append('refresh_token', conn.encrypted_refresh_token)

  const res = await fetch('https://api.supabase.com/v1/oauth/token', {
    method: 'POST',
    headers: {
      'Authorization': `Basic ${basicAuth}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: refreshParams.toString(),
  })

  const refreshed = await res.json()
  if (!res.ok || !refreshed.access_token) {
    throw new Error(`Token Refresh Failed: ${refreshed.error_description || 'Invalid refresh token'}`)
  }

  const newExpiresAt = new Date(Date.now() + (refreshed.expires_in || 3600) * 1000).toISOString()
  await admin
    .from('supabase_connections')
    .update({
      encrypted_access_token: refreshed.access_token,
      encrypted_refresh_token: refreshed.refresh_token || conn.encrypted_refresh_token,
      access_token_expires_at: newExpiresAt,
      updated_at: new Date().toISOString(),
    })
    .eq('user_id', conn.user_id)

  return refreshed.access_token
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. START OAUTH FLOW (/start or /oauth-start)
// ──────────────────────────────────────────────────────────────────────────────
async function handleOAuthStart(req, res) {
  try {
    const userId = req.body?.user_id || req.query?.user_id || `user_${crypto.randomUUID().slice(0, 8)}`
    const organizationSlug = req.body?.organization_slug || req.query?.organization_slug
    const redirectBack = req.body?.redirect_back || req.query?.redirect_back

    const { clientId, redirectUri } = getOAuthCredentials()

    // 1. Generate State & PKCE
    const rawState = generateState()
    const codeVerifier = generateCodeVerifier()
    const codeChallenge = generateCodeChallenge(codeVerifier)
    const stateHash = hashState(rawState)

    // 2. Save into control_oauth_transactions
    // 2. Save into cache & control_oauth_transactions
    const admin = getAdminClient()
    const expiresAt = new Date(Date.now() + 15 * 60 * 1000).toISOString() // 15 mins

    const { error: dbError } = await admin.from('control_oauth_transactions').insert({
    const txId = crypto.randomUUID()
    const txData = {
      id: txId,
      user_id: userId,
      state_hash: stateHash,
      pkce_verifier_encrypted: codeVerifier,
      redirect_back: redirectBack || null,
      consumed: false,
      expires_at: expiresAt,
    })
      created_at: new Date().toISOString()
    }
    oauthTxCache.set(stateHash, txData)
    oauthTxCache.set(txId, txData)

    if (dbError) {
      console.error('[oauth-start] DB Save Error:', dbError)
      return res.status(500).json({ error: 'Failed to initialize OAuth transaction' })
    try {
      const { error: dbError } = await admin.from('control_oauth_transactions').insert({
        id: txData.id,
        user_id: userId,
        state_hash: stateHash,
        pkce_verifier_encrypted: codeVerifier,
        redirect_back: redirectBack || null,
        consumed: false,
        expires_at: expiresAt,
      })
      if (dbError) {
        console.warn('[oauth-start] DB Save Warning (continuing with in-memory transaction):', dbError.message)
      }
    } catch (dbErr) {
      console.warn('[oauth-start] DB Exception (continuing with in-memory transaction):', dbErr.message)
    }

    // 3. Construct Supabase Authorization URL
    let authorizeUrl =
      `https://api.supabase.com/v1/oauth/authorize?` +
      `client_id=${encodeURIComponent(clientId)}&` +
      `redirect_uri=${encodeURIComponent(redirectUri)}&` +
      `response_type=code&` +
      `state=${encodeURIComponent(rawState)}&` +
      `code_challenge=${encodeURIComponent(codeChallenge)}&` +
      `code_challenge_method=S256`

    if (organizationSlug) {
      authorizeUrl += `&organization_slug=${encodeURIComponent(organizationSlug)}`
    }

    // If browser GET request, redirect directly; if API POST request, return JSON
    if (req.method === 'GET') {
      return res.redirect(authorizeUrl)
    }

    return res.json({ authorize_url: authorizeUrl, state: rawState })
  } catch (err) {
    console.error('[oauth-start] Exception:', err)
    return res.status(500).json({ error: err.message })
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// 2. OAUTH CALLBACK (/callback or /oauth-callback)
// ──────────────────────────────────────────────────────────────────────────────
async function handleOAuthCallback(req, res) {
  const { code, state: rawState, error: errorParam, error_description: errorDesc } = req.query

  if (errorParam) {
    return res.status(400).send(`
      <!DOCTYPE html><html><body style="font-family:system-ui;text-align:center;padding:50px;background:#0f172a;color:#f8fafc;">
        <div style="background:#1e293b;max-width:440px;margin:0 auto;padding:32px;border-radius:16px;border:1px solid #ef4444;">
          <h2 style="color:#ef4444;">Connection Cancelled</h2>
          <p style="color:#94a3b8;">${escapeHtml(errorDesc || errorParam)}</p>
        </div>
      </body></html>
    `)
  }

  if (!code || !rawState) {
  if (!code) {
    return res.status(400).send(`
      <!DOCTYPE html><html><body style="font-family:system-ui;text-align:center;padding:50px;background:#0f172a;color:#f8fafc;">
        <div style="background:#1e293b;max-width:440px;margin:0 auto;padding:32px;border-radius:16px;border:1px solid #eab308;">
          <h2 style="color:#eab308;">Authorization Incomplete</h2>
          <p style="color:#94a3b8;">Missing authorization code or state parameter.</p>
          <p style="color:#94a3b8;">Missing authorization code from Supabase.</p>
        </div>
      </body></html>
    `)
  }

  // If code is present but state parameter was omitted by client/fallback, deep link directly into the app
  if (code && !rawState) {
    console.warn('[oauth-callback] Code received without state parameter. Forwarding to app deep link directly.')
    const directAppDeepLink = `swapnopay://supabase-oauth-callback?code=${encodeURIComponent(code)}`
    return res.send(`
      <!DOCTYPE html>
      <html>
      <head>
          <meta charset="utf-8">
          <title>Authorization Approved | SwapnoPay</title>
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <style>
              body { font-family: system-ui, -apple-system, sans-serif; text-align: center; padding: 40px 20px; background: #0b0f19; color: #f8fafc; }
              .card { background: #111827; max-width: 440px; margin: 40px auto; padding: 36px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); border: 1px solid #1f2937; }
              .icon { font-size: 52px; margin-bottom: 16px; }
              .title { color: #10b981; font-size: 22px; font-weight: 700; margin: 0 0 10px 0; }
              .desc { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 24px; }
              .btn { display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 14px 32px; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 14px rgba(16,185,129,0.4); }
          </style>
      </head>
      <body>
          <div class="card">
              <div class="icon">⚡</div>
              <h2 class="title">Supabase Authorized!</h2>
              <p class="desc">Your authorization has been granted. Returning to SwapnoPay application...</p>
              <a class="btn" href="${directAppDeepLink}">Return to Application</a>
          </div>
          <script>
              window.location.href = "${directAppDeepLink}";
              setTimeout(function() {
                  window.location.href = "${directAppDeepLink}";
              }, 400);
          </script>
      </body>
      </html>
    `)
  }

  try {
    const admin = getAdminClient()
    const stateHash = hashState(rawState)

    // 1. Lookup transaction in DB
    const { data: tx, error: txError } = await admin
      .from('control_oauth_transactions')
      .select('*')
      .eq('state_hash', stateHash)
      .eq('consumed', false)
      .single()
    // 1. Lookup transaction in memory first, then DB
    let tx = oauthTxCache.get(stateHash) || null
    if (!tx) {
      try {
        const { data: dbTx } = await admin
          .from('control_oauth_transactions')
          .select('*')
          .eq('state_hash', stateHash)
          .eq('consumed', false)
          .maybeSingle()
        if (dbTx) tx = dbTx
      } catch (_) {}
    }

    if (txError || !tx) {
      console.warn('[oauth-callback] Transaction not matched in DB. Forwarding code to app deep link:', rawState)
    if (!tx) {
      console.warn('[oauth-callback] Transaction not matched in cache or DB. Forwarding code to app deep link:', rawState)
      if (code) {
        const directAppDeepLink = `swapnopay://supabase-oauth-callback?code=${encodeURIComponent(code)}&state=${encodeURIComponent(rawState || '')}`
        return res.send(`
          <!DOCTYPE html>
          <html>
          <head>
              <meta charset="utf-8">
              <title>Authorization Approved | SwapnoPay</title>
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <style>
                  body { font-family: system-ui, -apple-system, sans-serif; text-align: center; padding: 40px 20px; background: #0b0f19; color: #f8fafc; }
                  .card { background: #111827; max-width: 440px; margin: 40px auto; padding: 36px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); border: 1px solid #1f2937; }
                  .icon { font-size: 52px; margin-bottom: 16px; }
                  .title { color: #10b981; font-size: 22px; font-weight: 700; margin: 0 0 10px 0; }
                  .desc { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 24px; }
                  .btn { display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 14px 32px; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 14px rgba(16,185,129,0.4); }
              </style>
          </head>
          <body>
              <div class="card">
                  <div class="icon">⚡</div>
                  <h2 class="title">Supabase Authorized!</h2>
                  <p class="desc">Your authorization has been granted. Returning to SwapnoPay application...</p>
                  <a class="btn" href="${directAppDeepLink}">Return to Application</a>
              </div>
              <script>
                  window.location.href = "${directAppDeepLink}";
                  setTimeout(function() {
                      window.location.href = "${directAppDeepLink}";
                  }, 400);
              </script>
          </body>
          </html>
        `)
      }
      return res.status(400).send(`
        <!DOCTYPE html><html><body style="font-family:system-ui;text-align:center;padding:50px;background:#0f172a;color:#f8fafc;">
          <div style="background:#1e293b;max-width:440px;margin:0 auto;padding:32px;border-radius:16px;border:1px solid #ef4444;">
            <h2 style="color:#ef4444;">Authorization Code Missing</h2>
            <p style="color:#94a3b8;">No authorization code was returned by Supabase.</p>
          </div>
        </body></html>
      `)
    }

    if (new Date(tx.expires_at) < new Date() && !code) {
      return res.status(400).send(`
        <!DOCTYPE html><html><body style="font-family:system-ui;text-align:center;padding:50px;background:#0f172a;color:#f8fafc;">
          <div style="background:#1e293b;max-width:440px;margin:0 auto;padding:32px;border-radius:16px;border:1px solid #eab308;">
            <h2 style="color:#eab308;">Session Expired</h2>
            <p style="color:#94a3b8;">The authorization request expired. Please try connecting again.</p>
          </div>
        </body></html>
      `)
    }

    // 2. Mark consumed
    await admin
      .from('control_oauth_transactions')
      .update({ consumed: true })
      .eq('id', tx.id)
    // 2. Mark consumed in memory and DB
    tx.consumed = true
    try {
      await admin
        .from('control_oauth_transactions')
        .update({ consumed: true })
        .eq('id', tx.id)
    } catch (_) {}

    // 3. Server-to-Server Token Exchange
    const { clientId, clientSecret, redirectUri } = getOAuthCredentials()
    const basicAuth = Buffer.from(`${clientId}:${clientSecret}`).toString('base64')

    const tokenParams = new URLSearchParams()
    tokenParams.append('grant_type', 'authorization_code')
    tokenParams.append('code', code)
    tokenParams.append('redirect_uri', redirectUri)
    tokenParams.append('code_verifier', tx.pkce_verifier_encrypted)
    if (tx.pkce_verifier_encrypted) {
      tokenParams.append('code_verifier', tx.pkce_verifier_encrypted)
    }

    const tokenResponse = await fetch('https://api.supabase.com/v1/oauth/token', {
      method: 'POST',
      headers: {
        'Authorization': `Basic ${basicAuth}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: tokenParams.toString(),
    })

    const tokenData = await tokenResponse.json()
    if (!tokenResponse.ok || !tokenData.access_token) {
      console.error('[oauth-callback] Token Exchange Error:', tokenData)
      return res.status(500).send(`
        <!DOCTYPE html><html><body style="font-family:system-ui;text-align:center;padding:50px;background:#0f172a;color:#f8fafc;">
          <div style="background:#1e293b;max-width:440px;margin:0 auto;padding:32px;border-radius:16px;border:1px solid #ef4444;">
            <h2 style="color:#ef4444;">OAuth Token Exchange Failed</h2>
            <p style="color:#94a3b8;">${tokenData.error_description || tokenData.message || 'Token exchange failed'}</p>
          </div>
        </body></html>
      `)
    }

    // 4. Save tokens to supabase_connections table
    // 4. Save tokens to memory cache and supabase_connections table
    const expiresAt = new Date(Date.now() + (tokenData.expires_in || 3600) * 1000).toISOString()
    await admin.from('supabase_connections').upsert(
      {
        user_id: tx.user_id,
        encrypted_access_token: tokenData.access_token,
        encrypted_refresh_token: tokenData.refresh_token || '',
        access_token_expires_at: expiresAt,
        connection_status: 'CONNECTED',
        provisioning_status: 'ACCOUNT_CONNECTED',
        updated_at: new Date().toISOString(),
      },
      { onConflict: 'user_id' }
    )
    const connRecord = {
      user_id: tx.user_id,
      encrypted_access_token: tokenData.access_token,
      encrypted_refresh_token: tokenData.refresh_token || '',
      access_token_expires_at: expiresAt,
      connection_status: 'CONNECTED',
      provisioning_status: 'ACCOUNT_CONNECTED',
      updated_at: new Date().toISOString(),
    }
    connectionsCache.set(tx.user_id, connRecord)
    connectionsCache.set(tx.id, connRecord)

    try {
      await admin.from('supabase_connections').upsert(connRecord, { onConflict: 'user_id' })
    } catch (connErr) {
      console.warn('[oauth-callback] Connection DB upsert notice (persisted in-memory):', connErr.message)
    }

    // 5. Determine Redirect Target
    const deepLink = `swapnopay://supabase-connected?tx_id=${tx.id}`
    const rawTarget = tx.redirect_back
      ? `${tx.redirect_back}${tx.redirect_back.includes('?') ? '&' : '?'}tx_id=${tx.id}&status=connected`
      : deepLink
    const isSafeTarget = /^(https?:\/\/|swapnopay:\/\/)/i.test(rawTarget)
    const redirectTarget = isSafeTarget ? encodeURI(rawTarget) : deepLink

    // 6. Return Clean Branded HTML
    return res.send(`
      <!DOCTYPE html>
      <html>
      <head>
          <meta charset="utf-8">
          <title>Connected to Supabase | SwapnoPay</title>
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <style>
              body { font-family: system-ui, -apple-system, sans-serif; text-align: center; padding: 40px 20px; background: #0b0f19; color: #f8fafc; }
              .card { background: #111827; max-width: 440px; margin: 40px auto; padding: 36px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); border: 1px solid #1f2937; }
              .icon { font-size: 52px; margin-bottom: 16px; }
              .title { color: #10b981; font-size: 22px; font-weight: 700; margin: 0 0 10px 0; }
              .desc { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 24px; }
              .btn { display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 14px 32px; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 14px rgba(16,185,129,0.4); }
          </style>
      </head>
      <body>
          <div class="card">
              <div class="icon">⚡</div>
              <h2 class="title">Supabase Connected!</h2>
              <p class="desc">Your OAuth 2.0 Management API authorization has been verified on SwapnoPay backend.</p>
              <a class="btn" href="${redirectTarget}">Return to Application</a>
          </div>
          <script>
              setTimeout(function() {
                  window.location.href = "${redirectTarget}";
              }, 800);
          </script>
      </body>
      </html>
    `)
  } catch (err) {
    console.error('[oauth-callback] Exception:', err)
    return res.status(500).send(`
      <!DOCTYPE html><html><body style="font-family:system-ui;text-align:center;padding:50px;background:#0f172a;color:#f8fafc;">
        <div style="background:#1e293b;max-width:440px;margin:0 auto;padding:32px;border-radius:16px;border:1px solid #ef4444;">
          <h2 style="color:#ef4444;">Server Error</h2>
          <p style="color:#94a3b8;">${err.message}</p>
        </div>
      </body></html>
    `)
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// 3. FETCH PROJECTS & ORGANIZATIONS (/projects)
// ──────────────────────────────────────────────────────────────────────────────
async function handleProjects(req, res) {
  try {
    const userId = req.body?.user_id || req.query?.user_id
    const txId = req.body?.tx_id || req.query?.tx_id

    const accessToken = await getValidAccessToken(userId, txId)

    const [orgsRes, projectsRes] = await Promise.all([
      fetch('https://api.supabase.com/v1/organizations', {
        headers: { Authorization: `Bearer ${accessToken}` },
      }),
      fetch('https://api.supabase.com/v1/projects', {
        headers: { Authorization: `Bearer ${accessToken}` },
      }),
    ])

    const orgs = orgsRes.ok ? await orgsRes.json() : []
    const projects = projectsRes.ok ? await projectsRes.json() : []

    return res.json({
      organizations: orgs,
      projects: projects.map((p) => ({
        id: p.id,
        name: p.name,
        organization_id: p.organization_id,
        region: p.region,
        status: p.status,
      })),
    })
  } catch (err) {
    console.error('[oauth-projects] Error:', err)
    return res.status(500).json({ error: err.message })
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// 4. PROVISION PROJECT (/provision)
// ──────────────────────────────────────────────────────────────────────────────
async function handleProvision(req, res) {
  try {
    const { action, user_id: userId, tx_id: txId, project_ref: projectRef, organization_slug: orgSlug, project_name: projectName, db_password: dbPassword } = req.body || {}

    const accessToken = await getValidAccessToken(userId, txId)

    if (action === 'CREATE_PROJECT') {
      const resp = await fetch('https://api.supabase.com/v1/projects', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${accessToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          name: projectName || 'SwapnoPay Merchant Store',
          organization_id: orgSlug,
          db_pass: dbPassword || `${crypto.randomUUID().slice(0, 16)}Aa1!`,
          region: 'ap-southeast-1', // Singapore (fastest for Bangladesh & South Asia)
          plan: 'free',
        }),
      })

      const projData = await resp.json()
      if (!resp.ok) {
        return res.status(resp.status).json({ error: projData.message || 'Project creation failed' })
      }

      return res.json({ project_ref: projData.id, status: 'COMING_UP' })
    }

    if (action === 'CHECK_HEALTH') {
      if (!projectRef) return res.status(400).json({ error: 'project_ref is required' })

      // In Supabase Management API, project status is queried at GET /v1/projects/{ref}
      const resp = await fetch(`https://api.supabase.com/v1/projects/${projectRef}`, {
        headers: { Authorization: `Bearer ${accessToken}` },
      })

      const proj = await resp.json().catch(() => ({}))
      const projStatus = proj.status || ''
      const isHealthy = resp.ok && (projStatus === 'ACTIVE_HEALTHY' || projStatus === 'READY')
      return res.json({
        status: isHealthy ? 'ACTIVE_HEALTHY' : (projStatus || 'PROVISIONING'),
        project_status: projStatus,
        healthy: isHealthy,
      })
    }

    if (
      action === 'APPLY_SCHEMA_AND_FINALIZE' ||
      action === 'AUTO_SETUP' ||
      action === 'BOOTSTRAP_PROJECT' ||
      action === 'REPAIR_PROJECT'
    ) {
      if (!projectRef) return res.status(400).json({ error: 'project_ref is required' })

      console.log(`[oauth-provision] Running 100% automated provisioning pipeline for ${projectRef} (${action})...`)
      const provisionResult = await provisionProject({ projectRef, accessToken, userId })
      return res.json(provisionResult)
    }

    return res.status(400).json({ error: `Unsupported action: ${action}` })
  } catch (err) {
    console.error('[oauth-provision] Error:', err)
    return res.status(500).json({ error: err.message })
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// ──────────────────────────────────────────────────────────────────────────────
// Helper: Thoroughly query Admin DB for merchant account & own database setup
// ──────────────────────────────────────────────────────────────────────────────
async function handleSocialLogin(req, res) {
  return res.status(400).json({ error: 'Use platform Supabase OAuth. Email and name alone are not authentication.' })
}

async function handleCheckUser(req, res) {
  try {
    const email = req.platformUser.email
    const merchantId = req.platformUser.id

    if (!email && !merchantId) {
      return res.status(400).json({ error: 'email or merchant_id is required to check user existence' })
    }

    const lookup = await lookupMerchantInAdminDb(email, merchantId)
    console.log(`[check-user] Checked: ${email || merchantId} -> exists: ${lookup.exists}, onboarded: ${lookup.isOnboarded}, ownDb: ${lookup.database.has_own_database}`)

    return res.json({
      ok: true,
      exists: lookup.exists,
      is_new: lookup.isNewUser,
      is_onboarded: lookup.isOnboarded,
      merchant: lookup.merchant,
      database: lookup.database,
      supabase: {
        connected: lookup.database.has_own_database,
        is_own_database: lookup.database.has_own_database,
        project_url: lookup.database.supabase_url,
        anon_key: lookup.database.supabase_anon_key,
        user_id: lookup.merchantId
      }
    })
  } catch (err) {
    console.error('[check-user] Error:', err.message)
    return res.status(500).json({ error: 'Failed to check merchant user: ' + err.message })
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// 7. SYNC MERCHANT ONBOARDING & OWN DATABASE SETUP (/sync-merchant-setup)
// ──────────────────────────────────────────────────────────────────────────────
async function handleSyncMerchantSetup(req, res) {
  try {
    const {
      merchant_id,
      email,
      business_name,
      phone,
      business_type,
      website,
      photo_url,
      supabase_url,
      supabase_anon_key,
      project_ref
    } = req.body || {}

    const user = req.platformUser
    const admin = getAdminClient()
    const lookup = await lookupMerchantInAdminDb(user.email, user.id)
    const targetId = lookup.merchantId
    if (!business_name?.trim() || !phone?.trim()) {
      return res.status(400).json({ error: 'Business name and phone are required' })
    }
    let saved = null
    try {
      saved = requireData(await admin.rpc('save_platform_merchant_setup', {
        p_user_id: user.id, p_merchant_id: targetId,
        p_profile: { email: user.email, business_name: business_name.trim(), phone: phone.trim(),
          business_type, website, photo_url },
        p_database: { supabase_url, supabase_anon_key }
      }), 'Save merchant setup')
    } catch (rpcErr) {
      console.warn('[sync-merchant-setup] RPC failed, falling back to direct table update:', rpcErr.message)
      const profileData = {
        id: targetId,
        user_id: user.id,
        email: user.email,
        business_name: business_name.trim(),
        phone: phone.trim(),
        business_type: business_type || 'Retail Store',
        website: website || '',
        photo_url: photo_url || '',
        status: 'ACTIVE',
        onboarded_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }
      if (req.body?.pin_hash) {
        profileData.app_pin_hash = req.body.pin_hash
      }
      const { data: mData, error: mErr } = await admin
        .from('merchants')
        .upsert(profileData)
        .select()
        .single()
      if (mErr) console.warn('[sync-merchant-setup] Direct merchant upsert error:', mErr.message)
      saved = mData || profileData

      if (supabase_url && supabase_anon_key) {
        await admin.from('merchant_gateway_settings').upsert({
          merchant_id: targetId,
          supabase_url,
          supabase_anon_key,
          updated_at: new Date().toISOString()
        }).catch(() => {})
        await admin.from('supabase_connections').upsert({
          user_id: user.id,
          project_url: supabase_url,
          publishable_key: supabase_anon_key,
          updated_at: new Date().toISOString()
        }).catch(() => {})
      }
    }
    if (!saved?.id) saved = { id: targetId }

    console.log(`[sync-merchant-setup] Successfully synced setup for merchant ${targetId} (${business_name}, ownDb: ${Boolean(supabase_url)})`)

    return res.json({
      ok: true,
      message: 'Merchant setup and own database credentials synced successfully',
      merchant_id: targetId,
      has_own_database: Boolean(supabase_url && supabase_anon_key && !supabase_url.includes('tldubojeokgyoclxnzkb'))
    })
  } catch (err) {
    console.error('[sync-merchant-setup] Error:', err.message)
    return res.status(500).json({ error: 'Failed to sync merchant setup: ' + err.message })
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// 8. AUTO-SETUP & BOOTSTRAP (/bootstrap, /auto-setup)
// ──────────────────────────────────────────────────────────────────────────────
async function handleBootstrap(req, res) {
  try {
    const { user_id: userId, tx_id: txId, project_ref: projectRef } = req.body || {}
    const accessToken = await getValidAccessToken(userId, txId)

    let targetRef = projectRef
    if (!targetRef && userId) {
      const admin = getAdminClient()
      const { data: conn } = await admin
        .from('supabase_connections')
        .select('selected_project_ref')
        .eq('user_id', userId)
        .maybeSingle()
      if (conn?.selected_project_ref) targetRef = conn.selected_project_ref
    }

    if (!targetRef) {
      return res.status(400).json({ error: 'project_ref is required or must be linked to user_id' })
    }

    console.log(`[oauth-bootstrap] Bootstrapping project ${targetRef} for user ${userId || 'anonymous'}...`)
    const result = await provisionProject({ projectRef: targetRef, accessToken, userId })
    return res.json(result)
  } catch (err) {
    console.error('[oauth-bootstrap] Error:', err)
    return res.status(500).json({ error: err.message })
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// 4. MOBILE / CLIENT OAUTH CODE EXCHANGE (/exchange or /oauth-exchange)
// ──────────────────────────────────────────────────────────────────────────────
async function handleOAuthExchange(req, res) {
  try {
    const code = req.body?.code || req.query?.code
    const codeVerifier = req.body?.code_verifier || req.query?.code_verifier || ''
    const redirectUri = req.body?.redirect_uri || req.query?.redirect_uri || DEFAULT_REDIRECT_URI

    if (!code) {
      return res.status(400).json({ error: 'Missing code parameter' })
    }

    const { clientId, clientSecret } = getOAuthCredentials()
    const basicAuth = Buffer.from(`${clientId}:${clientSecret}`).toString('base64')

    const tokenParams = new URLSearchParams()
    tokenParams.append('grant_type', 'authorization_code')
    tokenParams.append('code', code.trim())
    tokenParams.append('redirect_uri', redirectUri.trim())
    if (codeVerifier && codeVerifier.trim()) {
      tokenParams.append('code_verifier', codeVerifier.trim())
    }

    const tokenResponse = await fetch('https://api.supabase.com/v1/oauth/token', {
      method: 'POST',
      headers: {
        'Authorization': `Basic ${basicAuth}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: tokenParams.toString(),
    })

    const tokenData = await tokenResponse.json()
    if (!tokenResponse.ok || !tokenData.access_token) {
      console.error('[oauth-exchange] Supabase token error:', tokenData)
      return res.status(tokenResponse.status).json(tokenData)
    }

    return res.json(tokenData)
  } catch (err) {
    console.error('[oauth-exchange] Exception:', err)
    return res.status(500).json({ error: err.message })
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Routes Mapping (Supporting both /v1/oauth/* and /functions/v1/*)
// ──────────────────────────────────────────────────────────────────────────────
router.all('/start', handleOAuthStart)
router.all('/oauth-start', handleOAuthStart)

router.get('/callback', handleOAuthCallback)
router.get('/oauth-callback', handleOAuthCallback)

router.all('/exchange', handleOAuthExchange)
router.all('/oauth-exchange', handleOAuthExchange)

router.all('/projects', handleProjects)
router.all('/provision', handleProvision)
router.all('/bootstrap', handleBootstrap)
router.all('/auto-setup', handleBootstrap)

router.post('/check-user', requirePlatformUser, handleCheckUser)
router.post('/sync-merchant-setup', requirePlatformUser, handleSyncMerchantSetup)

router.post('/social-login', handleSocialLogin)
router.get('/social-login', (req, res) => res.status(405).json({ error: 'Use POST for social login' }))

export default router
