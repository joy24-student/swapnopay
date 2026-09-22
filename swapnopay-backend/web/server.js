// SwapnoPay Web Frontend Static Server
// Serves widget.html, docs.html, portal.html, form.html, and branding assets

const express = require('express');
const cors = require('cors');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

// Enable CORS for embeddable checkout widget
app.use(cors());

// Keep public page aliases consistent with their canonical SEO URLs.
app.get(['/index.html', '/docs', '/sms-docs', '/portal'], (req, res) => {
  const target = req.path === '/index.html' ? '/' : `${req.path}.html`;
  const query = req.originalUrl.includes('?') ? req.originalUrl.slice(req.originalUrl.indexOf('?')) : '';
  res.redirect(301, target + query);
});

// Serve static web assets
app.use(express.static(path.join(__dirname)));

// Fallback routes for web apps and hosted forms
app.get('/widget', (_req, res) => res.sendFile(path.join(__dirname, 'widget.html')));
app.get(['/form', '/f/:id', '/form/:id', '/forms/:id'], (_req, res) => res.sendFile(path.join(__dirname, 'form.html')));

// Web Storefront & Admin panel convenience navigation
app.get(['/admin', '/admin/login'], (_req, res) => {
  const adminBase = process.env.ADMIN_BASE_URL || 'https://admin.swapnopay.top';
  res.redirect(adminBase);
});
app.get(['/shop-admin', '/shop/admin'], (_req, res) => {
  const shopBase = process.env.SHOP_BASE_URL || 'https://shop.swapnopay.top';
  res.redirect(`${shopBase}/admin/login.php`);
});
app.get('/shop', (_req, res) => {
  const shopBase = process.env.SHOP_BASE_URL || 'https://shop.swapnopay.top';
  res.redirect(shopBase);
});

app.listen(PORT, () => {
  console.log(`====================================================`);
  console.log(`🚀 SwapnoPay Web Frontend Server running on port ${PORT}`);
  console.log(`   - Checkout Widget:    http://localhost:${PORT}/widget.html`);
  console.log(`   - Payment Docs:       http://localhost:${PORT}/docs.html`);
  console.log(`   - SMS Gateway Docs:   http://localhost:${PORT}/sms-docs.html`);
  console.log(`   - Merchant Portal:    http://localhost:${PORT}/portal.html`);
  console.log(`====================================================`);
});
