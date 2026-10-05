// SwapnoPay Backend — Email Receipt Service (Nodemailer + Gmail OAuth2)
// Sends payment confirmation emails to customer and merchant after successful payment.
// Uses the same Gmail OAuth2 approach as gateway-service/src/gmail.js.

import nodemailer from 'nodemailer'
import { generateInvoicePdf, buildInvoiceHtml, getInvoiceAttachments, getMerchantLogoBuffer } from './invoiceService.js'

let _transporter = null
let _fromEmail = ''
let _fromName = ''

/**
 * Initialize the Nodemailer transporter once.
 * Call this at startup.
 */
export function initMailer() {
  // Public From address seen by customer/merchant in the email header
  const fromEmail = (process.env.SMTP_FROM || process.env.GMAIL_FROM_EMAIL || process.env.EMAIL_FROM || 'payment@swapnopay.top').trim().toLowerCase()
  const fromName = process.env.GMAIL_FROM_NAME || process.env.EMAIL_FROM_NAME || 'SwapnoPay'
  
  // Dedicated SMTP authentication username (e.g. the Gmail account address when using smtp.gmail.com)
  const authUser = (process.env.SMTP_USER || process.env.GMAIL_USER || process.env.EMAIL_USER || fromEmail).trim().toLowerCase()
  const appPassword = (process.env.SMTP_PASS || process.env.GMAIL_APP_PASSWORD || process.env.EMAIL_APP_PASSWORD || '').replace(/\s+/g, '')

  _fromEmail = fromEmail
  _fromName = fromName

  // 1. Prefer App Password / SMTP if provided
  if (appPassword) {
    const host = process.env.SMTP_HOST || 'smtp.gmail.com'
    const port = Number(process.env.SMTP_PORT) || 465
    const secure = process.env.SMTP_SECURE !== undefined ? process.env.SMTP_SECURE !== 'false' : port === 465

    _transporter = nodemailer.createTransport({
      service: host.includes('gmail') ? 'gmail' : undefined,
      host,
      port,
      secure,
      auth: {
        user: authUser,
        pass: appPassword,
      },
      tls: {
        rejectUnauthorized: false
      }
    })
    console.log(`[mailer] SMTP transporter ready with ${host.includes('gmail') ? 'Gmail/App Password' : 'SMTP'}. Login user: ${authUser} | Display from: ${_fromEmail}`)
    return
  }

  // 2. Fall back to Gmail OAuth2 if credentials are provided
  const clientId = process.env.GMAIL_CLIENT_ID
  const clientSecret = process.env.GMAIL_CLIENT_SECRET
  const refreshToken = process.env.GMAIL_REFRESH_TOKEN

  if (clientId && clientSecret && refreshToken) {
    _transporter = nodemailer.createTransport({
      service: 'gmail',
      auth: {
        type: 'OAuth2',
        user: authUser,
        clientId,
        clientSecret,
        refreshToken,
      },
    })
    console.log('[mailer] Gmail OAuth2 transporter ready. Sending from:', _fromEmail)
    return
  }

  console.warn('[mailer] ⚠️  Email credentials not fully set -- email receipts disabled.')
  console.warn('[mailer] Set GMAIL_APP_PASSWORD (or SMTP_PASS) with SMTP_USER or GMAIL_CLIENT_ID/SECRET/REFRESH_TOKEN.')
}

export function isMailerReady() {
  return _transporter !== null
}

/**
 * Send a payment success receipt email.
 *
 * @param {'customer'|'merchant'} role
 * @param {string} toEmail
 * @param {object} paymentData
 */
export async function sendPaymentReceipt(role, toEmail, paymentData) {
  if (!_transporter) {
    console.warn('[mailer] Mailer not configured — skipping receipt for', role)
    return null
  }

  const normalizedTo = toEmail?.trim().toLowerCase()
  if (!normalizedTo || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedTo)) {
    console.warn('[mailer] Invalid email address for', role, ':', toEmail)
    return null
  }

  const subject =
    role === 'merchant'
      ? `[PAID] ${paymentData.tran_id} — BDT ${Number(paymentData.amount).toFixed(2)}`
      : `Payment Invoice ${paymentData.order_id || paymentData.tran_id} — ${paymentData.merchant_name || 'SwapnoPay Merchant'}`

  const merchantLogoBuf = await getMerchantLogoBuffer(paymentData.merchant_logo_url || paymentData.photo_url || paymentData.merchant_logo).catch(() => null)

  let pdfBuffer = null
  try {
    pdfBuffer = await generateInvoicePdf(paymentData)
  } catch (pdfErr) {
    console.warn(`[mailer] PDF invoice generation notice for ${role}:`, pdfErr.message)
  }

  const html = buildInvoiceHtml(paymentData, role, Boolean(merchantLogoBuf))
  const attachments = getInvoiceAttachments(paymentData, pdfBuffer, merchantLogoBuf)

  try {
    const info = await _transporter.sendMail({
      from: `"${_fromName}" <${_fromEmail}>`,
      to: normalizedTo,
      subject,
      html,
      attachments,
    })
    console.log(`[mailer] Official receipt & invoice PDF sent to ${role} (${normalizedTo}): messageId=${info.messageId} (pdf=${pdfBuffer ? 'attached' : 'none'}, merchantLogo=${merchantLogoBuf ? 'yes' : 'badge'})`)
    return info.messageId
  } catch (err) {
    console.error(`[mailer] Failed to send receipt to ${role} (${normalizedTo}):`, err.message)
    throw err
  }
}

/**
 * Send both customer and merchant receipts from a single payment event.
 * Non-blocking — logs errors but does not throw.
 */
export async function sendPaymentReceipts(paymentData) {
  const {
    customer_email,
    merchant_email,
    customer_receipts_enabled = true,
    merchant_receipts_enabled = true,
  } = paymentData

  console.log(`[mailer] 📧 sendPaymentReceipts triggered for order ${paymentData.order_id || paymentData.tran_id}:`, {
    customer_email: customer_email || '(none)',
    customer_receipts_enabled,
    merchant_email: merchant_email || '(none)',
    merchant_receipts_enabled,
    mailer_ready: isMailerReady()
  })

  if (!customer_email) {
    console.warn(`[mailer] ℹ️ Customer receipt skipped for order ${paymentData.order_id || paymentData.tran_id}: No customer email provided in order data.`)
  } else if (!customer_receipts_enabled) {
    console.warn(`[mailer] ℹ️ Customer receipt skipped for order ${paymentData.order_id || paymentData.tran_id}: customer_receipts_enabled is false in gateway config.`)
  }

  const tasks = []

  if (customer_receipts_enabled && customer_email) {
    tasks.push(
      sendPaymentReceipt('customer', customer_email, paymentData)
        .catch(err => console.error(`[mailer] ❌ Customer receipt failed for ${customer_email}:`, err.message))
    )
  }

  if (merchant_receipts_enabled && merchant_email && merchant_email !== customer_email) {
    tasks.push(
      sendPaymentReceipt('merchant', merchant_email, paymentData)
        .catch(err => console.error(`[mailer] ❌ Merchant receipt failed for ${merchant_email}:`, err.message))
    )
  }

  await Promise.allSettled(tasks)
}

// ─────────────────────────────────────────────────────────────────────────────
// HTML Email Template
// ─────────────────────────────────────────────────────────────────────────────
function buildReceiptHtml(role, d) {
  const esc = v => String(v || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const heading = role === 'merchant' ? 'Payment Received' : 'Payment Successful'
  const subheading =
    role === 'merchant'
      ? `A payment has been received and verified for your store.`
      : `Your payment to <strong>${esc(d.merchant_name)}</strong> has been verified.`

  const verification =
    d.verification === 'MERCHANT_APPROVED_APPEAL'
      ? 'Verified by merchant review'
      : 'Automatically verified via SMS matching'

  const merchantRows =
    role === 'merchant'
      ? `
      <tr><td style="padding:10px 0;border-bottom:1px solid #E2E8F0;color:#64748B">Customer Name</td>
          <td style="padding:10px 0;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600">${esc(d.cus_name || 'Customer')}</td></tr>
      <tr><td style="padding:10px 0;border-bottom:1px solid #E2E8F0;color:#64748B">Customer Phone</td>
          <td style="padding:10px 0;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600">${esc(d.cus_phone || 'N/A')}</td></tr>
      <tr><td style="padding:10px 0;border-bottom:1px solid #E2E8F0;color:#64748B">Product</td>
          <td style="padding:10px 0;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600">${esc(d.product_name || 'Payment')}</td></tr>`
      : ''

  return `<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:Arial,sans-serif;color:#0F172A">
  <div style="max-width:600px;margin:32px auto;background:#FFFFFF;border-radius:16px;overflow:hidden;border:1px solid #E2E8F0;box-shadow:0 4px 24px rgba(0,0,0,0.06)">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#0F172A 0%,#1E293B 100%);padding:28px 32px">
      <div style="font-size:13px;font-weight:800;color:#F59E0B;letter-spacing:2px;text-transform:uppercase">SwapnoPay</div>
      <h1 style="margin:8px 0 0;font-size:26px;color:#FFFFFF;font-weight:800">${heading}</h1>
    </div>

    <!-- Status Badge -->
    <div style="background:#ECFDF5;border-bottom:1px solid #A7F3D0;padding:14px 32px;display:flex;align-items:center;gap:10px">
      <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10B981"></span>
      <span style="font-weight:700;font-size:14px;color:#065F46">PAYMENT CONFIRMED</span>
      <span style="margin-left:auto;font-size:12px;color:#10B981;font-weight:600">BDT ${Number(d.amount || 0).toFixed(2)}</span>
    </div>

    <!-- Body -->
    <div style="padding:28px 32px">
      <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155">${subheading}</p>

      <!-- Receipt Table -->
      <table style="width:100%;border-collapse:collapse;font-size:14px">
        <tr><td style="padding:10px 0;border-bottom:1px solid #E2E8F0;color:#64748B">Order Reference</td>
            <td style="padding:10px 0;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:700;color:#0F172A">${esc(d.tran_id)}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #E2E8F0;color:#64748B">Transaction ID</td>
            <td style="padding:10px 0;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600">${esc(d.trx_id || 'N/A')}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #E2E8F0;color:#64748B">Amount Paid</td>
            <td style="padding:10px 0;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:800;font-size:16px;color:#10B981">BDT ${Number(d.amount || 0).toFixed(2)}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #E2E8F0;color:#64748B">Payment Method</td>
            <td style="padding:10px 0;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600">${esc(d.payment_method || 'MFS')}</td></tr>
        ${merchantRows}
        <tr><td style="padding:10px 0;border-bottom:1px solid #E2E8F0;color:#64748B">Paid At</td>
            <td style="padding:10px 0;border-bottom:1px solid #E2E8F0;text-align:right;color:#64748B">${esc(d.payment_time ? new Date(d.payment_time).toLocaleString() : new Date().toLocaleString())}</td></tr>
        <tr><td style="padding:10px 0;color:#64748B">Verification</td>
            <td style="padding:10px 0;text-align:right;color:#10B981;font-weight:600">${esc(verification)}</td></tr>
      </table>

      <!-- Merchant Info Box (customer email only) -->
      ${role === 'customer' ? `
      <div style="margin-top:20px;padding:14px 16px;background:#EFF6FF;border-radius:10px;border-left:4px solid #3B82F6">
        <div style="font-size:12px;font-weight:700;color:#1E40AF;margin-bottom:4px">MERCHANT</div>
        <div style="font-weight:700;font-size:15px;color:#1E293B">${esc(d.merchant_name || 'SwapnoPay Merchant')}</div>
      </div>` : ''}

      <p style="margin:22px 0 6px;font-size:12px;color:#94A3B8">
        Receipt ID: <code style="background:#F1F5F9;padding:2px 6px;border-radius:4px">${esc(d.order_id)}</code>
      </p>
      <p style="margin:0;font-size:11px;color:#CBD5E1;line-height:1.6">
        This is an automated transactional receipt from SwapnoPay. Never share your OTP, PIN, or passwords via email.
        If you did not authorize this transaction, contact support immediately.
      </p>
    </div>

    <!-- Footer -->
    <div style="background:#F8FAFC;border-top:1px solid #E2E8F0;padding:16px 32px;text-align:center">
      <p style="margin:0;font-size:11px;color:#94A3B8">
        © ${new Date().getFullYear()} SwapnoPay · Secure Mobile Financial Services Gateway
      </p>
    </div>
  </div>
</body>
</html>`
}

/**
 * Send an urgent offline alert to the merchant when a customer opens the checkout
 * while the merchant's Android SMS Gateway device is disconnected.
 */
export async function sendMerchantDeviceOfflineAlert({ toEmail, merchantName, orderId, amount, customerEmail }) {
  if (!_transporter) {
    console.warn('[mailer] Mailer not configured — skipping merchant offline alert.')
    return null
  }

  const normalizedTo = toEmail?.trim().toLowerCase()
  if (!normalizedTo || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedTo)) {
    console.warn('[mailer] Invalid email address for merchant offline alert:', toEmail)
    return null
  }

  const subject = `⚠️ [Action Required] Customer waiting to pay! Your SwapnoPay Gateway phone is Offline`
  const html = `<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:Arial,sans-serif;color:#0F172A">
  <div style="max-width:600px;margin:32px auto;background:#FFFFFF;border-radius:16px;overflow:hidden;border:1px solid #FED7AA;box-shadow:0 4px 24px rgba(0,0,0,0.06)">
    <div style="background:linear-gradient(135deg,#9A3412 0%,#C2410C 100%);padding:28px 32px">
      <div style="font-size:13px;font-weight:800;color:#FED7AA;letter-spacing:2px;text-transform:uppercase">SwapnoPay Gateway Alert</div>
      <h1 style="margin:8px 0 0;font-size:24px;color:#FFFFFF;font-weight:800">Your SMS Gateway Device is Offline!</h1>
    </div>
    <div style="background:#FFF7ED;border-bottom:1px solid #FFEDD5;padding:14px 32px;display:flex;align-items:center;gap:10px">
      <span style="font-weight:700;font-size:14px;color:#9A3412">⚠️ ACTION REQUIRED</span>
      <span style="margin-left:auto;font-size:13px;color:#C2410C;font-weight:700">Order: ${orderId ? String(orderId) : 'New Checkout'}</span>
    </div>
    <div style="padding:28px 32px">
      <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155">
        Hello <strong>${merchantName || 'Merchant'}</strong>,
      </p>
      <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569">
        A customer just opened your payment gateway to pay <strong>BDT ${amount ? Number(amount).toFixed(2) : '---'}</strong>, but your Android SMS Gateway phone is currently <strong>offline or disconnected</strong>.
      </p>
      <div style="margin:20px 0;padding:16px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:12px">
        <div style="font-size:13px;font-weight:700;color:#92400E;margin-bottom:8px">Please take these steps immediately:</div>
        <ol style="margin:0;padding-left:20px;font-size:13px;color:#78350F;line-height:1.7">
          <li>Ensure your Android Gateway phone is turned <strong>ON</strong>.</li>
          <li>Check that Mobile Data or Wi-Fi is active.</li>
          <li>Open the <strong>SwapnoPay</strong> app to re-establish live connection.</li>
        </ol>
      </div>
      ${customerEmail ? `<p style="margin:0 0 12px;font-size:12px;color:#64748B">Customer Email: <strong style="color:#0F172A">${customerEmail}</strong></p>` : ''}
      <p style="margin:24px 0 0;font-size:12px;color:#94A3B8;line-height:1.5">
        This automated notification is throttled to prevent spam. Once your device reconnects, automatic payment verification will immediately resume.
      </p>
    </div>
    <div style="background:#F8FAFC;border-top:1px solid #E2E8F0;padding:16px 32px;text-align:center">
      <p style="margin:0;font-size:11px;color:#94A3B8">© ${new Date().getFullYear()} SwapnoPay Platform</p>
    </div>
  </div>
</body>
</html>`

  try {
    const info = await _transporter.sendMail({
      from: `"${_fromName}" <${_fromEmail}>`,
      to: normalizedTo,
      subject,
      html,
    })
    console.log(`[mailer] Merchant offline alert sent to ${normalizedTo}: messageId=${info.messageId}`)
    return info.messageId
  } catch (err) {
    console.error(`[mailer] Failed to send merchant offline alert to ${normalizedTo}:`, err.message)
    return null
  }
}

/**
 * Send an email to a waiting customer when the merchant's SMS Gateway device comes back online.
 */
export async function sendCustomerMerchantBackOnlineAlert({ toEmail, merchantName, orderId, amount, checkoutUrl }) {
  if (!_transporter) {
    console.warn('[mailer] Mailer not configured — skipping customer back-online alert.')
    return null
  }

  const normalizedTo = toEmail?.trim().toLowerCase()
  if (!normalizedTo || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedTo)) {
    console.warn('[mailer] Invalid email address for customer alert:', toEmail)
    return null
  }

  const storeName = merchantName || 'The Merchant'
  const subject = `🎉 Good news! ${storeName} is back online — Complete your payment`
  const html = `<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:Arial,sans-serif;color:#0F172A">
  <div style="max-width:600px;margin:32px auto;background:#FFFFFF;border-radius:16px;overflow:hidden;border:1px solid #BAE6FD;box-shadow:0 4px 24px rgba(0,0,0,0.06)">
    <div style="background:linear-gradient(135deg,#0369A1 0%,#0284C7 100%);padding:28px 32px">
      <div style="font-size:13px;font-weight:800;color:#BAE6FD;letter-spacing:2px;text-transform:uppercase">SwapnoPay Gateway Update</div>
      <h1 style="margin:8px 0 0;font-size:24px;color:#FFFFFF;font-weight:800">${storeName} is Ready!</h1>
    </div>
    <div style="background:#F0FDF4;border-bottom:1px solid #BBF7D0;padding:14px 32px;display:flex;align-items:center;gap:10px">
      <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#16A34A"></span>
      <span style="font-weight:700;font-size:14px;color:#15803D">GATEWAY RECONNECTED & ACTIVE</span>
      ${amount ? `<span style="margin-left:auto;font-size:13px;color:#16A34A;font-weight:700">BDT ${Number(amount).toFixed(2)}</span>` : ''}
    </div>
    <div style="padding:28px 32px">
      <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155">
        Hello,
      </p>
      <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569">
        Great news! The payment gateway for <strong>${storeName}</strong> is back online and fully active. You can now complete your transaction with instant, automatic verification.
      </p>
      ${orderId ? `<p style="margin:0 0 20px;font-size:13px;color:#64748B">Order Reference: <strong style="color:#0F172A">${orderId}</strong></p>` : ''}
      <div style="text-align:center;margin:32px 0">
        <a href="${checkoutUrl}" style="display:inline-block;background:#0284C7;color:#FFFFFF;font-size:15px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:12px;box-shadow:0 4px 12px rgba(2,132,199,0.3)">
          👉 Return to Checkout & Complete Payment
        </a>
      </div>
      <p style="margin:20px 0 0;font-size:12px;color:#94A3B8;line-height:1.5;text-align:center">
        If the button above does not work, copy and paste this link into your browser:<br/>
        <a href="${checkoutUrl}" style="color:#0284C7;word-break:break-all">${checkoutUrl}</a>
      </p>
    </div>
    <div style="background:#F8FAFC;border-top:1px solid #E2E8F0;padding:16px 32px;text-align:center">
      <p style="margin:0;font-size:11px;color:#94A3B8">© ${new Date().getFullYear()} SwapnoPay Platform</p>
    </div>
  </div>
</body>
</html>`

  try {
    const info = await _transporter.sendMail({
      from: `"${_fromName}" <${_fromEmail}>`,
      to: normalizedTo,
      subject,
      html,
    })
    console.log(`[mailer] Customer back-online alert sent to ${normalizedTo}: messageId=${info.messageId}`)
    return info.messageId
  } catch (err) {
    console.error(`[mailer] Failed to send customer back-online alert to ${normalizedTo}:`, err.message)
    return null
  }
}

