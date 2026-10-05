// SwapnoPay Invoice & Receipt Service
// Generates official PDF invoice attachments and matching HTML email receipts
// Complies with requirements:
// 1. Top logo of the receipt is the merchant's own logo (or merchant brand badge if none uploaded, never SwapnoPay logo).
// 2. Full merchant own address (never SwapnoPay's platform address).
// 3. Highlighted Merchant Receiving Number and Transaction ID (TrxID).
// 4. Removed 'Merchant Bank ID' and 'Approved Code'.
// 5. SwapnoPay logo is exclusively featured in the 'Verified by SwapnoPay' footer trust badge.

import PDFDocument from 'pdfkit'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const webDir = path.resolve(__dirname, '../../web')

/**
 * Resolve local payment method logo paths with fallbacks
 */
export function getLogoPaths() {
  const swapnopay = fs.existsSync(path.join(webDir, 'swapnopay-logo.png'))
    ? path.join(webDir, 'swapnopay-logo.png')
    : path.join(webDir, 'logo_branding.png')

  const bkash = path.join(webDir, 'bkash-logo.png')
  const nagad = path.join(webDir, 'nagad-logo.png')

  const rocket = fs.existsSync(path.join(webDir, 'rocket-logo.png'))
    ? path.join(webDir, 'rocket-logo.png')
    : path.join(webDir, 'Rocket.png')

  const upay = fs.existsSync(path.join(webDir, 'upay-logo.png'))
    ? path.join(webDir, 'upay-logo.png')
    : path.join(webDir, 'upay-seeklogo.png')

  return { swapnopay, bkash, nagad, rocket, upay }
}

/**
 * Fetch and buffer merchant logo if provided (supports http/https, data URLs, or local files).
 * Returns null if not provided or failed.
 */
export async function getMerchantLogoBuffer(logoUrl) {
  if (!logoUrl || typeof logoUrl !== 'string') return null
  try {
    const trimmed = logoUrl.trim()
    if (trimmed.startsWith('data:image/')) {
      const base64Part = trimmed.split(',')[1]
      if (base64Part) return Buffer.from(base64Part, 'base64')
    }
    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      const res = await fetch(trimmed, { signal: AbortSignal.timeout(3500) })
      if (res.ok) {
        const arrayBuf = await res.arrayBuffer()
        const rawBuf = Buffer.from(arrayBuf)
        try {
          const sharp = (await import('sharp')).default
          return await sharp(rawBuf).resize(240, 90, { fit: 'inside' }).png().toBuffer()
        } catch (_) {
          return rawBuf
        }
      }
    }
    if (fs.existsSync(trimmed)) {
      return fs.readFileSync(trimmed)
    }
  } catch (err) {
    console.warn('[invoiceService] Failed to load merchant logo:', err.message)
  }
  return null
}

/**
 * Generate a PDF invoice matching the official format.
 *
 * @param {object} d Payment data object
 * @returns {Promise<Buffer>} PDF buffer
 */
export async function generateInvoicePdf(d) {
  const merchantLogoBuf = await getMerchantLogoBuffer(d.merchant_logo_url || d.photo_url || d.merchant_logo)

  return new Promise((resolve, reject) => {
    try {
      const doc = new PDFDocument({ size: 'A4', margin: 30, bufferPages: true })
      const chunks = []

      doc.on('data', chunk => chunks.push(chunk))
      doc.on('end', () => resolve(Buffer.concat(chunks)))
      doc.on('error', reject)

      const pageWidth = 595.28
      const margin = 30
      const contentWidth = pageWidth - margin * 2 // 535.28

      const amountFormatted = Number(d.amount || 0).toFixed(2)
      const orderId = d.order_id || 'N/A'
      const tranId = d.tran_id || orderId
      const trxId = d.trx_id || 'N/A'
      const method = (d.payment_method || 'bKash').trim()
      const merchantName = d.merchant_name || 'SwapnoPay Merchant'
      const merchantPhone = d.merchant_phone || d.phone || '01700000000'
      const merchantEmail = d.merchant_email || d.email || 'support@swapnopay.top'
      const merchantWebsite = d.merchant_website || d.website || ''
      const merchantAddress = d.merchant_address || d.business_address || d.address || ''
      const receiverNumber = d.receiver_number || d.receiving_number || d.merchant_number || d.receiver || 'N/A'

      const cusName = d.cus_name || 'Customer'
      const cusPhone = d.cus_phone || 'N/A'
      const cusEmail = d.customer_email || d.cus_email || 'N/A'
      const productName = d.product_name || 'Payment Services'

      const dateStr = d.payment_time
        ? new Date(d.payment_time).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }) + ' ' + new Date(d.payment_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' (GMT+6 Time)'
        : new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }) + ' ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' (GMT+6 Time)'

      const logos = getLogoPaths()

      let curY = 30

      // ── Top Center: MERCHANT OWN LOGO (or Merchant Brand Text Badge, never SwapnoPay) ──
      if (merchantLogoBuf) {
        doc.image(merchantLogoBuf, pageWidth / 2 - 45, curY, { width: 90, height: 40, fit: [90, 40], align: 'center' })
        curY += 45
      } else {
        // Draw merchant styled brand name header
        const badgeWidth = Math.min(contentWidth - 60, Math.max(140, merchantName.length * 10 + 24))
        const badgeX = pageWidth / 2 - badgeWidth / 2
        doc.rect(badgeX, curY, badgeWidth, 26).fill('#F1F5F9').stroke('#CBD5E1')
        doc.font('Helvetica-Bold').fontSize(12).fillColor('#0F172A').text(merchantName, badgeX, curY + 7, { width: badgeWidth, align: 'center' })
        curY += 34
      }

      doc.font('Helvetica-Bold').fontSize(13).fillColor('#1E293B').text(merchantName, margin, curY, { width: contentWidth, align: 'center' })
      curY += 16
      doc.font('Helvetica').fontSize(10).fillColor('#475569').text(`Payment Invoice ${orderId}/${tranId}`, margin, curY, { width: contentWidth, align: 'center' })
      curY += 20

      // ── Outer Border Box ──
      const boxStartY = curY
      const boxPadding = 14
      let boxContentY = boxStartY + boxPadding

      // Left: Merchant own full address & contact details
      doc.font('Helvetica-Bold').fontSize(11).fillColor('#0F172A').text(merchantName, margin + boxPadding, boxContentY)
      boxContentY += 14

      doc.font('Helvetica').fontSize(8.5).fillColor('#334155')
      if (merchantAddress) {
        doc.text(merchantAddress, margin + boxPadding, boxContentY, { width: 280 })
        boxContentY += 18
      }
      doc.text(`Phone: ${merchantPhone}`, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.text(`Email: ${merchantEmail}`, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.text(`Date: ${dateStr}`, margin + boxPadding, boxContentY)
      boxContentY += 12
      if (merchantWebsite) {
        doc.fillColor('#2563EB').text(merchantWebsite, margin + boxPadding, boxContentY, { link: merchantWebsite })
      } else {
        doc.fillColor('#64748B').text('Verified Merchant Store', margin + boxPadding, boxContentY)
      }

      // Right: Merchant own logo (or store badge, never SwapnoPay)
      if (merchantLogoBuf) {
        doc.image(merchantLogoBuf, margin + contentWidth - boxPadding - 90, boxStartY + boxPadding, { width: 90, height: 44, fit: [90, 44] })
      } else {
        const storeBadgeX = margin + contentWidth - boxPadding - 110
        doc.rect(storeBadgeX, boxStartY + boxPadding, 110, 36).fill('#F8FAFC').stroke('#CBD5E1')
        doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A')
          .text(merchantName, storeBadgeX + 6, boxStartY + boxPadding + 8, { width: 98, align: 'center' })
        doc.font('Helvetica').fontSize(7.5).fillColor('#64748B')
          .text('Official Store', storeBadgeX + 6, boxStartY + boxPadding + 22, { width: 98, align: 'center' })
      }

      boxContentY += 20

      // ── Bar 1: ORDER #: ... | MTI: ... ──
      doc.rect(margin + boxPadding, boxContentY, contentWidth - boxPadding * 2, 20).fill('#E2E8F0')
      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A')
        .text(`ORDER #: ${orderId} | MTI: ${tranId}`, margin + boxPadding + 8, boxContentY + 5)
      boxContentY += 26

      // ── Order Summary Table (2 columns) ──
      const col1Width = 200
      const col2X = margin + boxPadding + col1Width + 10
      const col2Width = contentWidth - boxPadding * 2 - col1Width - 10

      // Col 1: Customer Address
      const custStartY = boxContentY
      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A').text('Customer Address:', margin + boxPadding, boxContentY)
      boxContentY += 13
      doc.font('Helvetica').fontSize(8.5).fillColor('#334155').text(cusName, margin + boxPadding, boxContentY)
      boxContentY += 16
      doc.text(`Phone: ${cusPhone}`, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.text(`Email: ${cusEmail}`, margin + boxPadding, boxContentY)

      // Col 2: Product & Price
      let col2Y = custStartY
      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A')
      doc.text('Product Details', col2X, col2Y)
      doc.text('Price', col2X + col2Width - 60, col2Y, { width: 60, align: 'right' })
      col2Y += 14

      doc.font('Helvetica').fontSize(8.5).fillColor('#334155')
      doc.text(`1. Order desc/${productName.length > 28 ? productName.slice(0, 26) + '...' : productName}`, col2X, col2Y, { width: col2Width - 90 })
      doc.text(`BDT ${amountFormatted}`, col2X + col2Width - 90, col2Y, { width: 90, align: 'right' })
      col2Y += 14

      doc.text('Shipping & Handling:', col2X + col2Width - 180, col2Y, { width: 100, align: 'right' })
      doc.text('BDT 0.00', col2X + col2Width - 80, col2Y, { width: 80, align: 'right' })
      col2Y += 13

      doc.text('Subtotal:', col2X + col2Width - 180, col2Y, { width: 100, align: 'right' })
      doc.text(`BDT ${amountFormatted}`, col2X + col2Width - 80, col2Y, { width: 80, align: 'right' })
      col2Y += 12

      // Dotted line
      doc.dash(2, { space: 2 }).moveTo(col2X + col2Width - 120, col2Y).lineTo(col2X + col2Width, col2Y).stroke('#94A3B8').undash()
      col2Y += 5

      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A')
      doc.text('Total:', col2X + col2Width - 140, col2Y, { width: 50, align: 'right' })
      doc.text(`BDT ${amountFormatted}`, col2X + col2Width - 90, col2Y, { width: 90, align: 'right' })

      boxContentY = Math.max(boxContentY + 16, col2Y + 18)

      // ── Bar 2: Payment Information ──
      doc.rect(margin + boxPadding, boxContentY, contentWidth - boxPadding * 2, 20).fill('#E2E8F0')
      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A')
        .text('Payment Information', margin + boxPadding + 8, boxContentY + 5)
      boxContentY += 26

      // ── Faint Watermark "PAID" Across Payment Section ──
      const watermarkCenterY = boxContentY + 90
      doc.save()
      doc.translate(pageWidth / 2 - 20, watermarkCenterY)
      doc.rotate(-35)
      doc.font('Helvetica-Bold').fontSize(72).fillColor('#CBD5E1').opacity(0.35)
        .text('PAID', -90, -35, { align: 'center' })
      doc.restore()

      // ── Payment Information Details (2 columns) ──
      const payStartY = boxContentY

      // Left Column: Payment fields
      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A').text('Payment Method:', margin + boxPadding, boxContentY)
      boxContentY += 13

      doc.font('Helvetica').fontSize(8.5).fillColor('#334155')
      doc.text(`Transaction Type : E-commerce`, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.text(`Payment Type : ${method.toUpperCase()}`, margin + boxPadding, boxContentY)
      boxContentY += 14

      // ── HIGHLIGHTED TRANSACTION ID (TrxID) ──
      const trxHighlightW = 190
      doc.rect(margin + boxPadding, boxContentY, trxHighlightW, 16).fill('#FEF3C7').stroke('#FCD34D')
      doc.font('Helvetica-Bold').fontSize(8.5).fillColor('#92400E')
        .text(`TrxID: ${trxId}`, margin + boxPadding + 6, boxContentY + 3.5, { width: trxHighlightW - 12 })
      boxContentY += 20

      // ── HIGHLIGHTED MERCHANT RECEIVING NUMBER ──
      const recvHighlightW = 190
      doc.rect(margin + boxPadding, boxContentY, recvHighlightW, 16).fill('#DBEAFE').stroke('#93C5FD')
      doc.font('Helvetica-Bold').fontSize(8.5).fillColor('#1E40AF')
        .text(`Receiver: ${receiverNumber}`, margin + boxPadding + 6, boxContentY + 3.5, { width: recvHighlightW - 12 })
      boxContentY += 20

      // Remaining clean payment details (removed Bank ID and Approved Code)
      doc.font('Helvetica').fontSize(8).fillColor('#334155')
      const remainingFields = [
        ['Customer Sender', cusPhone.length > 5 ? cusPhone.slice(0, 3) + '****' + cusPhone.slice(-4) : cusPhone],
        ['Transaction Type', 'Purchase'],
        ['IP Address', '103.109.59.237'],
        ['Statement Show', merchantName.toUpperCase().replace(/\s+/g, '_')],
        ['Gateway Currency', 'BDT'],
        ['Transaction Amount', `BDT ${amountFormatted}`],
        ['Convertion Rate', '1.0000'],
        ['Request Amount', `BDT ${amountFormatted}`],
        ['Issuer Bank', method],
        ['Issuer Bank Location', 'Bangladesh'],
      ]

      for (const [k, v] of remainingFields) {
        doc.text(`${k} : ${v}`, margin + boxPadding, boxContentY, { width: col1Width + 10 })
        boxContentY += 10.5
      }

      boxContentY += 6
      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A').text('Billing Address:', margin + boxPadding, boxContentY)
      boxContentY += 13
      doc.font('Helvetica').fontSize(8.5).fillColor('#334155').text(cusName, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.text(`Phone: ${cusPhone}`, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.text(`Email: ${cusEmail}`, margin + boxPadding, boxContentY)

      // Right Column: Summary Table
      let payCol2Y = payStartY
      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A')
      doc.text('Product Details', col2X, payCol2Y)
      doc.text('Price', col2X + col2Width - 60, payCol2Y, { width: 60, align: 'right' })
      payCol2Y += 14

      doc.font('Helvetica').fontSize(8.5).fillColor('#334155')
      doc.text(`1. Order desc/${productName.length > 28 ? productName.slice(0, 26) + '...' : productName}`, col2X, payCol2Y, { width: col2Width - 90 })
      doc.text(`BDT ${amountFormatted}`, col2X + col2Width - 90, payCol2Y, { width: 90, align: 'right' })
      payCol2Y += 14

      doc.text('Shipping & Handling:', col2X + col2Width - 180, payCol2Y, { width: 100, align: 'right' })
      doc.text('BDT 0.00', col2X + col2Width - 80, payCol2Y, { width: 80, align: 'right' })
      payCol2Y += 18

      doc.dash(2, { space: 2 }).moveTo(col2X + col2Width - 120, payCol2Y).lineTo(col2X + col2Width, payCol2Y).stroke('#94A3B8').undash()
      payCol2Y += 6

      doc.font('Helvetica-Bold').fontSize(9.5).fillColor('#0F172A')
      doc.text('Grand Total:', col2X + col2Width - 180, payCol2Y, { width: 90, align: 'right' })
      doc.text(`BDT ${amountFormatted}`, col2X + col2Width - 90, payCol2Y, { width: 90, align: 'right' })
      payCol2Y += 16

      doc.dash(2, { space: 2 }).moveTo(col2X + col2Width - 120, payCol2Y).lineTo(col2X + col2Width, payCol2Y).stroke('#94A3B8').undash()
      payCol2Y += 6

      doc.font('Helvetica-Bold').fontSize(9.5).fillColor('#15803D')
      doc.text('Paid Amount:', col2X + col2Width - 180, payCol2Y, { width: 90, align: 'right' })
      doc.text(`BDT ${amountFormatted}`, col2X + col2Width - 90, payCol2Y, { width: 90, align: 'right' })

      boxContentY = Math.max(boxContentY + 12, payCol2Y + 28)

      // ── Refund Policy & Support ──
      doc.font('Helvetica-Bold').fontSize(8.5).fillColor('#0F172A')
        .text(`For refund policy related information please check the merchant store (${merchantWebsite || merchantEmail})`, margin + boxPadding, boxContentY, { width: contentWidth - boxPadding * 2 })
      boxContentY += 14
      doc.font('Helvetica-Bold').fontSize(8.5).fillColor('#0F172A')
        .text(`Questions? Email: ${merchantEmail} or Call: ${merchantPhone}`, margin + boxPadding, boxContentY, { width: contentWidth - boxPadding * 2 })
      boxContentY += 24

      // ── Footer Channel Logos Strip ──
      const stripY = boxContentY
      const stripHeight = 36
      doc.rect(margin + boxPadding, stripY, contentWidth - boxPadding * 2, stripHeight).stroke('#CBD5E1')

      doc.font('Helvetica').fontSize(7.5).fillColor('#64748B')
        .text('Pay With', margin + boxPadding + 6, stripY + 13)

      let logoX = margin + boxPadding + 46
      const logoY = stripY + 6
      const logoH = 24

      // bKash
      if (fs.existsSync(logos.bkash)) {
        doc.image(logos.bkash, logoX, logoY, { width: 36, height: logoH, fit: [36, logoH] })
        logoX += 42
      }
      // Nagad
      if (fs.existsSync(logos.nagad)) {
        doc.image(logos.nagad, logoX, logoY, { width: 36, height: logoH, fit: [36, logoH] })
        logoX += 42
      }
      // Rocket
      if (fs.existsSync(logos.rocket)) {
        doc.image(logos.rocket, logoX, logoY, { width: 36, height: logoH, fit: [36, logoH] })
        logoX += 42
      }
      // Upay
      if (fs.existsSync(logos.upay)) {
        doc.image(logos.upay, logoX, logoY, { width: 28, height: logoH, fit: [28, logoH] })
        logoX += 34
      }

      // Verified by SwapnoPay Badge on the right (SwapnoPay logo exclusively placed here)
      const verifiedBoxX = margin + contentWidth - boxPadding - 145
      doc.rect(verifiedBoxX, stripY + 4, 140, 28).fill('#F8FAFC').stroke('#E2E8F0')
      doc.font('Helvetica-Bold').fontSize(7.5).fillColor('#0284C7')
        .text('Verified by', verifiedBoxX + 6, stripY + 13)
      if (fs.existsSync(logos.swapnopay)) {
        doc.image(logos.swapnopay, verifiedBoxX + 54, stripY + 6, { width: 80, height: 24, fit: [80, 24] })
      }

      boxContentY += stripHeight + boxPadding

      // ── Stroke the entire Outer Box ──
      const boxTotalHeight = boxContentY - boxStartY
      doc.rect(margin, boxStartY, contentWidth, boxTotalHeight).stroke('#CBD5E1')

      doc.end()
    } catch (err) {
      reject(err)
    }
  })
}

/**
 * Build the HTML email body matching the official invoice layout.
 *
 * @param {object} d Payment data object
 * @param {'customer'|'merchant'} role
 * @param {boolean} hasMerchantLogo Whether merchant logo CID is available
 * @returns {string} HTML string
 */
export function buildInvoiceHtml(d, role = 'customer', hasMerchantLogo = false) {
  const esc = v => String(v || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

  const amountFormatted = Number(d.amount || 0).toFixed(2)
  const orderId = d.order_id || 'N/A'
  const tranId = d.tran_id || orderId
  const trxId = d.trx_id || 'N/A'
  const method = (d.payment_method || 'bKash').trim()
  const merchantName = d.merchant_name || 'SwapnoPay Merchant'
  const merchantPhone = d.merchant_phone || d.phone || '01700000000'
  const merchantEmail = d.merchant_email || d.email || 'support@swapnopay.top'
  const merchantWebsite = d.merchant_website || d.website || ''
  const merchantAddress = d.merchant_address || d.business_address || d.address || ''
  const receiverNumber = d.receiver_number || d.receiving_number || d.merchant_number || d.receiver || 'N/A'

  const cusName = d.cus_name || 'Customer'
  const cusPhone = d.cus_phone || 'N/A'
  const cusEmail = d.customer_email || d.cus_email || 'N/A'
  const productName = d.product_name || 'Payment Services'

  const dateStr = d.payment_time
    ? new Date(d.payment_time).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }) + ' ' + new Date(d.payment_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' (GMT+6 Time)'
    : new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }) + ' ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' (GMT+6 Time)'

  const maskedPhone = cusPhone && cusPhone.length > 5
    ? cusPhone.slice(0, 3) + '****' + cusPhone.slice(-4)
    : cusPhone

  // Merchant Header Display (Logo if available, otherwise stylish merchant brand badge, NEVER SwapnoPay logo)
  const topCenterLogoHtml = hasMerchantLogo
    ? `<img src="cid:merchant-logo" alt="${esc(merchantName)}" style="height: 48px; width: auto; max-width: 160px; display: inline-block; margin-bottom: 6px;" />`
    : `<div style="display: inline-block; padding: 8px 18px; background-color: #f1f5f9; border-radius: 6px; border: 1px solid #cbd5e1; margin-bottom: 6px;">
        <span style="font-size: 18px; font-weight: 800; color: #0f172a; letter-spacing: 0.5px;">${esc(merchantName)}</span>
       </div>`

  const topRightLogoHtml = hasMerchantLogo
    ? `<img src="cid:merchant-logo" alt="${esc(merchantName)}" style="max-height: 48px; max-width: 140px; display: inline-block;" />`
    : `<div style="display: inline-block; padding: 6px 12px; background-color: #f8fafc; border-radius: 4px; border: 1px solid #cbd5e1; text-align: center;">
        <div style="font-size: 11px; font-weight: 700; color: #0f172a;">${esc(merchantName)}</div>
        <div style="font-size: 9px; color: #64748b;">Official Store</div>
       </div>`

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Invoice ${esc(orderId)}</title>
</head>
<body style="margin: 0; padding: 24px 12px; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; -webkit-font-smoothing: antialiased;">

  <!-- Outer Center Container -->
  <div style="max-width: 680px; margin: 0 auto; background: #ffffff; padding: 24px; border: 1px solid #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.04);">

    <!-- Top Center: Merchant Logo & Invoice Title -->
    <div style="text-align: center; margin-bottom: 24px;">
      ${topCenterLogoHtml}
      <div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 4px;">${esc(merchantName)}</div>
      <div style="font-size: 12px; color: #475569;">Payment Invoice ${esc(orderId)}/${esc(tranId)}</div>
    </div>

    <!-- Main Bordered Box -->
    <div style="border: 1px solid #e2e8f0; padding: 20px; position: relative;">

      <!-- Top Row: Merchant Info Left & Logo Right -->
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 18px;">
        <tr>
          <td style="vertical-align: top; width: 65%;">
            <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">${esc(merchantName)}</div>
            <div style="font-size: 11.5px; line-height: 1.5; color: #334155;">
              ${merchantAddress ? `${esc(merchantAddress)}<br />` : ''}
              Phone: ${esc(merchantPhone)}<br />
              Email: ${esc(merchantEmail)}<br />
              Date: ${esc(dateStr)}<br />
              ${merchantWebsite ? `<a href="${esc(merchantWebsite)}" style="color: #2563eb; text-decoration: none;">${esc(merchantWebsite)}</a>` : '<span style="color: #64748b;">Verified Merchant</span>'}
            </div>
          </td>
          <td style="vertical-align: top; width: 35%; text-align: right;">
            ${topRightLogoHtml}
          </td>
        </tr>
      </table>

      <!-- Gray Bar 1: ORDER #: ... | MTI: ... -->
      <div style="background-color: #e2e8f0; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 14px; border-radius: 2px;">
        ORDER #: ${esc(orderId)} | MTI: ${esc(tranId)}
      </div>

      <!-- Two Column: Customer Address & Product Details -->
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 12px;">
        <tr>
          <!-- Customer Address -->
          <td style="vertical-align: top; width: 44%; padding-right: 14px; border-right: 1px solid #f1f5f9;">
            <div style="font-weight: 700; color: #0f172a; margin-bottom: 4px;">Customer Address:</div>
            <div style="color: #334155; line-height: 1.6;">
              <strong>${esc(cusName)}</strong><br />
              Phone: ${esc(cusPhone)}<br />
              Email: ${esc(cusEmail)}
            </div>
          </td>
          <!-- Product & Price Table -->
          <td style="vertical-align: top; width: 56%; padding-left: 14px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
              <tr>
                <td style="font-weight: 700; color: #0f172a; padding-bottom: 6px;">Product Details</td>
                <td style="font-weight: 700; color: #0f172a; text-align: right; padding-bottom: 6px;">Price</td>
              </tr>
              <tr>
                <td style="color: #334155; padding: 3px 0;">1. Order desc/${esc(productName)}</td>
                <td style="color: #334155; text-align: right; white-space: nowrap; padding: 3px 0;">BDT ${esc(amountFormatted)}</td>
              </tr>
              <tr>
                <td style="color: #64748b; padding: 3px 0; text-align: right;">Shipping &amp; Handling:</td>
                <td style="color: #334155; text-align: right; padding: 3px 0;">BDT 0.00</td>
              </tr>
              <tr>
                <td style="color: #64748b; padding: 3px 0; text-align: right;">Subtotal:</td>
                <td style="color: #334155; text-align: right; padding: 3px 0;">BDT ${esc(amountFormatted)}</td>
              </tr>
              <tr>
                <td colspan="2" style="border-bottom: 1px dashed #94a3b8; padding-top: 4px; height: 1px;"></td>
              </tr>
              <tr>
                <td style="font-weight: 700; color: #0f172a; text-align: right; padding-top: 6px;">Total:</td>
                <td style="font-weight: 700; color: #0f172a; text-align: right; padding-top: 6px;">BDT ${esc(amountFormatted)}</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Gray Bar 2: Payment Information -->
      <div style="background-color: #e2e8f0; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 14px; border-radius: 2px;">
        Payment Information
      </div>

      <!-- Payment Section With Watermark -->
      <div style="position: relative; margin-bottom: 16px;">
        <!-- Watermark "PAID" -->
        <div style="position: absolute; top: 15%; left: 22%; font-size: 72px; font-weight: 900; color: #cbd5e1; opacity: 0.35; transform: rotate(-30deg); pointer-events: none; z-index: 1;">
          PAID
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 11.5px; position: relative; z-index: 2;">
          <tr>
            <!-- Left: Payment details with highlighted TrxID and Receiver Number -->
            <td style="vertical-align: top; width: 44%; padding-right: 14px; line-height: 1.6; color: #334155;">
              <div style="font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">Payment Method:</div>
              <div>Transaction Type : <strong>E-commerce</strong></div>
              <div>Payment Type : <strong>${esc(method.toUpperCase())}</strong></div>

              <!-- HIGHLIGHTED TRANSACTION ID (TrxID) -->
              <div style="margin: 5px 0;">
                <span style="font-size: 11px; color: #475569;">Transaction ID (TrxID):</span><br />
                <span style="background-color: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 4px; font-weight: 800; font-family: monospace; font-size: 12.5px; border: 1px solid #fcd34d; display: inline-block;">
                  ${esc(trxId)}
                </span>
              </div>

              <!-- HIGHLIGHTED MERCHANT RECEIVING NUMBER -->
              <div style="margin: 5px 0;">
                <span style="font-size: 11px; color: #475569;">Merchant Receiving Number:</span><br />
                <span style="background-color: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 4px; font-weight: 800; font-family: monospace; font-size: 12.5px; border: 1px solid #93c5fd; display: inline-block;">
                  ${esc(receiverNumber)}
                </span>
              </div>

              <div>Customer Sender : <strong>${esc(maskedPhone)}</strong></div>
              <div>Transaction Type : Purchase</div>
              <div>IP Address : 103.109.59.237</div>
              <div>Statement Show : ${esc(merchantName.toUpperCase().replace(/\s+/g, '_'))}</div>
              <div>Gateway Currency : BDT</div>
              <div>Transaction Amount : BDT ${esc(amountFormatted)}</div>
              <div>Convertion Rate : 1.0000</div>
              <div>Request Amount : BDT ${esc(amountFormatted)}</div>
              <div>Issuer Bank : ${esc(method)}</div>
              <div>Issuer Bank Location : Bangladesh</div>

              <div style="margin-top: 10px; font-size: 12px; font-weight: 700; color: #0f172a;">Billing Address:</div>
              <div><strong>${esc(cusName)}</strong></div>
              <div>Phone: ${esc(cusPhone)}</div>
              <div>Email: ${esc(cusEmail)}</div>
            </td>

            <!-- Right: Product Details & Grand Total -->
            <td style="vertical-align: top; width: 56%; padding-left: 14px;">
              <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <tr>
                  <td style="font-weight: 700; color: #0f172a; padding-bottom: 6px;">Product Details</td>
                  <td style="font-weight: 700; color: #0f172a; text-align: right; padding-bottom: 6px;">Price</td>
                </tr>
                <tr>
                  <td style="color: #334155; padding: 3px 0;">1. Order desc/${esc(productName)}</td>
                  <td style="color: #334155; text-align: right; white-space: nowrap; padding: 3px 0;">BDT ${esc(amountFormatted)}</td>
                </tr>
                <tr>
                  <td style="color: #64748b; padding: 3px 0; text-align: right;">Shipping &amp; Handling:</td>
                  <td style="color: #334155; text-align: right; padding: 3px 0;">BDT 0.00</td>
                </tr>
                <tr>
                  <td colspan="2" style="border-bottom: 1px dashed #94a3b8; padding-top: 4px; height: 1px;"></td>
                </tr>
                <tr>
                  <td style="font-weight: 700; color: #0f172a; text-align: right; padding: 8px 0;">Grand Total:</td>
                  <td style="font-weight: 700; color: #0f172a; text-align: right; padding: 8px 0;">BDT ${esc(amountFormatted)}</td>
                </tr>
                <tr>
                  <td colspan="2" style="border-bottom: 1px dashed #94a3b8; height: 1px;"></td>
                </tr>
                <tr>
                  <td style="font-weight: 700; color: #15803d; text-align: right; padding: 8px 0; font-size: 13px;">Paid Amount:</td>
                  <td style="font-weight: 700; color: #15803d; text-align: right; padding: 8px 0; font-size: 13px;">BDT ${esc(amountFormatted)}</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </div>

      <!-- Refund policy and support lines -->
      <div style="font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 18px; line-height: 1.5;">
        For refund policy related information please check the merchant store (${merchantWebsite ? `<a href="${esc(merchantWebsite)}" style="color: #2563eb; text-decoration: none;">${esc(merchantWebsite)}</a>` : esc(merchantEmail)})
      </div>
      <div style="font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 6px; line-height: 1.5;">
        Questions? Email: <a href="mailto:${esc(merchantEmail)}" style="color: #2563eb; text-decoration: none;">${esc(merchantEmail)}</a> or Call: ${esc(merchantPhone)}
      </div>

      <!-- Bottom Channel Logos Strip -->
      <div style="margin-top: 20px; border: 1px solid #cbd5e1; padding: 8px 12px; background: #ffffff;">
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <td style="vertical-align: middle; width: 65px; font-size: 10px; color: #64748b;">
              Pay With
            </td>
            <td style="vertical-align: middle;">
              <img src="cid:bkash-logo" alt="bKash" style="height: 20px; margin-right: 8px; vertical-align: middle; display: inline-block;" />
              <img src="cid:nagad-logo" alt="Nagad" style="height: 20px; margin-right: 8px; vertical-align: middle; display: inline-block;" />
              <img src="cid:rocket-logo" alt="Rocket" style="height: 20px; margin-right: 8px; vertical-align: middle; display: inline-block;" />
              <img src="cid:upay-logo" alt="Upay" style="height: 20px; margin-right: 8px; vertical-align: middle; display: inline-block;" />
            </td>
            <td style="vertical-align: middle; text-align: right; width: 180px;">
              <div style="display: inline-block; padding: 3px 8px; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 4px;">
                <span style="font-size: 9px; font-weight: 700; color: #0284c7; vertical-align: middle; margin-right: 4px;">Verified by</span>
                <img src="cid:swapnopay-logo" alt="SwapnoPay" style="height: 16px; vertical-align: middle; display: inline-block;" />
              </div>
            </td>
          </tr>
        </table>
      </div>

    </div>
  </div>
</body>
</html>`
}

/**
 * Return array of Nodemailer attachments including the PDF invoice and inline CID images.
 *
 * @param {object} d Payment data object
 * @param {Buffer|null} pdfBuffer Pre-generated PDF invoice buffer
 * @param {Buffer|null} merchantLogoBuf Pre-loaded merchant logo buffer
 * @returns {Array<object>}
 */
export function getInvoiceAttachments(d, pdfBuffer = null, merchantLogoBuf = null) {
  const logos = getLogoPaths()
  const attachments = []

  // 1. PDF Invoice Attachment
  if (pdfBuffer && Buffer.isBuffer(pdfBuffer)) {
    const filename = `Invoice-${d.order_id || d.tran_id || 'receipt'}.pdf`
    attachments.push({
      filename,
      content: pdfBuffer,
      contentType: 'application/pdf',
    })
  }

  // 2. Merchant logo if available
  if (merchantLogoBuf && Buffer.isBuffer(merchantLogoBuf)) {
    attachments.push({
      filename: 'merchant-logo.png',
      content: merchantLogoBuf,
      cid: 'merchant-logo',
    })
  }

  // 3. Payment brand logos & trust badge (SwapnoPay logo exclusively in footer)
  if (fs.existsSync(logos.swapnopay)) {
    attachments.push({
      filename: 'swapnopay-logo.png',
      path: logos.swapnopay,
      cid: 'swapnopay-logo',
    })
  }
  if (fs.existsSync(logos.bkash)) {
    attachments.push({
      filename: 'bkash-logo.png',
      path: logos.bkash,
      cid: 'bkash-logo',
    })
  }
  if (fs.existsSync(logos.nagad)) {
    attachments.push({
      filename: 'nagad-logo.png',
      path: logos.nagad,
      cid: 'nagad-logo',
    })
  }
  if (fs.existsSync(logos.rocket)) {
    attachments.push({
      filename: 'rocket-logo.png',
      path: logos.rocket,
      cid: 'rocket-logo',
    })
  }
  if (fs.existsSync(logos.upay)) {
    attachments.push({
      filename: 'upay-logo.png',
      path: logos.upay,
      cid: 'upay-logo',
    })
  }

  return attachments
}
