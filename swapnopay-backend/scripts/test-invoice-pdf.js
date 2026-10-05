import PDFDocument from 'pdfkit'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const webDir = path.resolve(__dirname, '../web')

export async function generateInvoicePdf(d) {
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
      const merchantPhone = d.merchant_phone || '09638113399'
      const merchantEmail = d.merchant_email || 'support@swapnopay.top'
      const merchantWebsite = d.merchant_website || 'https://swapnopay.top'
      const merchantAddress = d.merchant_address || 'House 26A, Road 113, Gulshan 2, Dhaka, Bangladesh'
      const cusName = d.cus_name || 'Customer'
      const cusPhone = d.cus_phone || 'N/A'
      const cusEmail = d.customer_email || d.cus_email || 'N/A'
      const productName = d.product_name || 'Payment Services'

      const dateStr = d.payment_time
        ? new Date(d.payment_time).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }) + ' ' + new Date(d.payment_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' (GMT+6 Time)'
        : new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }) + ' ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' (GMT+6 Time)'

      // Logo paths
      const swapnopayLogoPath = fs.existsSync(path.join(webDir, 'swapnopay-logo.png')) ? path.join(webDir, 'swapnopay-logo.png') : path.join(webDir, 'logo_branding.png')
      const bkashLogoPath = path.join(webDir, 'bkash-logo.png')
      const nagadLogoPath = path.join(webDir, 'nagad-logo.png')
      const rocketLogoPath = fs.existsSync(path.join(webDir, 'rocket-logo.png')) ? path.join(webDir, 'rocket-logo.png') : path.join(webDir, 'Rocket.png')
      const upayLogoPath = fs.existsSync(path.join(webDir, 'upay-logo.png')) ? path.join(webDir, 'upay-logo.png') : path.join(webDir, 'upay-seeklogo.png')

      // Resolve payment method logo
      let methodLogoPath = null
      const lowerMethod = method.toLowerCase()
      if (lowerMethod.includes('bkash')) methodLogoPath = bkashLogoPath
      else if (lowerMethod.includes('nagad')) methodLogoPath = nagadLogoPath
      else if (lowerMethod.includes('rocket')) methodLogoPath = rocketLogoPath
      else if (lowerMethod.includes('upay')) methodLogoPath = upayLogoPath

      let curY = 30

      // ── Top Center: Merchant / SwapnoPay Logo & Title ──
      if (fs.existsSync(swapnopayLogoPath)) {
        doc.image(swapnopayLogoPath, pageWidth / 2 - 35, curY, { width: 70, height: 35, fit: [70, 35], align: 'center' })
        curY += 40
      }
      doc.font('Helvetica-Bold').fontSize(13).fillColor('#1E293B').text(merchantName, margin, curY, { width: contentWidth, align: 'center' })
      curY += 16
      doc.font('Helvetica').fontSize(10).fillColor('#475569').text(`Payment Invoice ${orderId}/${tranId}`, margin, curY, { width: contentWidth, align: 'center' })
      curY += 20

      // ── Outer Border Box ──
      const boxStartY = curY
      const boxPadding = 14
      let boxContentY = boxStartY + boxPadding

      // Left: Merchant info
      doc.font('Helvetica-Bold').fontSize(11).fillColor('#0F172A').text(merchantName, margin + boxPadding, boxContentY)
      boxContentY += 14
      doc.font('Helvetica').fontSize(8.5).fillColor('#334155')
        .text(merchantAddress, margin + boxPadding, boxContentY, { width: 280 })
      boxContentY += 22
      doc.text(`Dhaka, Bangladesh`, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.text(merchantPhone, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.text(`Date: ${dateStr}`, margin + boxPadding, boxContentY)
      boxContentY += 12
      doc.fillColor('#2563EB').text(merchantWebsite, margin + boxPadding, boxContentY, { link: merchantWebsite })

      // Right: Merchant / Gateway Logo
      if (fs.existsSync(swapnopayLogoPath)) {
        doc.image(swapnopayLogoPath, margin + contentWidth - boxPadding - 85, boxStartY + boxPadding, { width: 85, height: 42, fit: [85, 42] })
      }

      boxContentY += 20

      // ── Bar 1: ORDER #: ... | MTI: ... ──
      doc.rect(margin + boxPadding, boxContentY, contentWidth - boxPadding * 2, 20).fill('#E2E8F0')
      doc.font('Helvetica-Bold').fontSize(9).fillColor('#0F172A')
        .text(`ORDER #: ${orderId} | MTI: ${tranId}`, margin + boxPadding + 8, boxContentY + 5)
      boxContentY += 26

      // ── Order Summary Table (2 columns) ──
      const col1Width = 190
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
      const watermarkCenterY = boxContentY + 80
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

      doc.font('Helvetica').fontSize(8).fillColor('#334155')
      const payFields = [
        ['Transaction Type', 'E-commerce'],
        ['Payment Type', method.toUpperCase()],
        ['Trans Reference', cusPhone.length > 5 ? cusPhone.slice(0, 3) + '****' + cusPhone.slice(-4) : cusPhone],
        ['Merchant Bank ID', trxId],
        ['Approved Code', trxId],
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

      for (const [k, v] of payFields) {
        doc.text(`${k} : ${v}`, margin + boxPadding, boxContentY, { width: col1Width + 10 })
        boxContentY += 10.5
      }

      boxContentY += 8
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
        .text(`For refund policy related information please check the website (${merchantWebsite}) refund policy details`, margin + boxPadding, boxContentY, { width: contentWidth - boxPadding * 2 })
      boxContentY += 14
      doc.font('Helvetica-Bold').fontSize(8.5).fillColor('#0F172A')
        .text(`Questions? Email: ${merchantEmail} or payment@swapnopay.top or Call: ${merchantPhone}`, margin + boxPadding, boxContentY, { width: contentWidth - boxPadding * 2 })
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
      if (fs.existsSync(bkashLogoPath)) {
        doc.image(bkashLogoPath, logoX, logoY, { width: 36, height: logoH, fit: [36, logoH] })
        logoX += 42
      }
      // Nagad
      if (fs.existsSync(nagadLogoPath)) {
        doc.image(nagadLogoPath, logoX, logoY, { width: 36, height: logoH, fit: [36, logoH] })
        logoX += 42
      }
      // Rocket
      if (fs.existsSync(rocketLogoPath)) {
        doc.image(rocketLogoPath, logoX, logoY, { width: 36, height: logoH, fit: [36, logoH] })
        logoX += 42
      }
      // Upay
      if (fs.existsSync(upayLogoPath)) {
        doc.image(upayLogoPath, logoX, logoY, { width: 28, height: logoH, fit: [28, logoH] })
        logoX += 34
      }

      // Verified by SwapnoPay Badge on the right
      const verifiedBoxX = margin + contentWidth - boxPadding - 145
      doc.rect(verifiedBoxX, stripY + 4, 140, 28).fill('#F8FAFC').stroke('#E2E8F0')
      doc.font('Helvetica-Bold').fontSize(7.5).fillColor('#0284C7')
        .text('Verified by', verifiedBoxX + 6, stripY + 13)
      if (fs.existsSync(swapnopayLogoPath)) {
        doc.image(swapnopayLogoPath, verifiedBoxX + 54, stripY + 6, { width: 80, height: 24, fit: [80, 24] })
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

// Quick self-test if run directly
if (process.argv[1] && process.argv[1].endsWith('test-invoice-pdf.js')) {
  generateInvoicePdf({
    amount: '2240.00',
    order_id: '230228225744WKW9KTZJL6EW',
    tran_id: 'ZGG7E4JFLP',
    trx_id: '71QCLF08',
    payment_method: 'NAGAD',
    merchant_name: 'shikhotech',
    merchant_phone: '09638113399',
    merchant_website: 'https://shikho.tech/',
    merchant_email: 'team@shikho.tech',
    cus_name: 'JOY SAHA',
    cus_phone: '01735342839',
    customer_email: 'jsaha3741@gmail.com',
    product_name: 'ZGG7E4JFLP'
  }).then(buf => {
    fs.writeFileSync('test-invoice.pdf', buf)
    console.log('test-invoice.pdf written successfully, size:', buf.length)
  }).catch(console.error)
}
