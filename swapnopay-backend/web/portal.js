// SwapnoPay Developer Console & Sandbox Core Controller
// Features: Navigation, API Explorer, MFS SMS Simulator, Webhook Payload Inspector, Live Widget Modal, & SDK Tester.

let activeTab = 'dashboard';
let activeSdkLang = 'node';
let environment = 'sandbox';

const liveExamples = [
  {
    provider: 'bKash',
    mode: 'Production QA',
    order: 'ORD-7845',
    amount: 'Tk 1,500.00',
    phone: '01712345678',
    method: 'Gateway checkout + SMS verify',
    status: 'Ready to verify'
  },
  {
    provider: 'Nagad',
    mode: 'Production QA',
    order: 'ORD-7846',
    amount: 'Tk 2,250.00',
    phone: '01812345678',
    method: 'Merchant callback test',
    status: 'Awaiting callback'
  },
  {
    provider: 'Rocket',
    mode: 'Sandbox live mode',
    order: 'ORD-7847',
    amount: 'Tk 875.00',
    phone: '01912345678',
    method: 'Cash-in verification flow',
    status: 'Settlement active'
  },
  {
    provider: 'Upay',
    mode: 'Testing mode',
    order: 'ORD-7848',
    amount: 'Tk 3,140.00',
    phone: '01612345678',
    method: 'Full gateway inspection',
    status: 'Preview + debug'
  }
];

const gatewayPageLinks = [
  { label: 'Secure checkout', href: 'widget.html?merchant_id=00000000-0000-0000-0000-000000000001&amount=1500.00&order_id=ORD-7845', type: 'Main gateway page' },
  { label: 'Merchant form', href: 'form.html', type: 'Order initiation form' },
  { label: 'Transaction ledger', href: 'transactions.html', type: 'Payment audit & debug' }
];

const fullFlowSteps = [
  { label: 'Create order', detail: 'Merchant registers checkout intent with order metadata.' },
  { label: 'Request payment', detail: 'Customer chooses bKash / Nagad / Rocket and confirms amount.' },
  { label: 'Receive SMS', detail: 'App listens for the provider SMS and captures TrxID & amount.' },
  { label: 'Verify & match', detail: 'SwapnoPay validates the transaction against the pending order.' },
  { label: 'Webhook callback', detail: 'Merchant server receives a signed event and marks order PAID.' }
];

// Webhook events log store for payload inspection
let webhookLogStore = [];

// Templates for API Explorer
const explorerTemplates = {
  '/v1/payment/config': {
    merchant_id: "00000000-0000-0000-0000-000000000001"
  },
  '/v1/payment/merchant-config': {
    merchant_id: "00000000-0000-0000-0000-000000000001",
    bkash_enabled: true,
    nagad_enabled: true,
    rocket_enabled: true,
    upay_enabled: true,
    receiving_numbers: {
      bKash: "01712345678",
      Nagad: "01812345678",
      Rocket: "01912345678"
    },
    qr_codes: {
      bKash: "https://example.com/qr/bkash.png"
    }
  },
  '/v1/payment/verify': {
    merchant_id: "00000000-0000-0000-0000-000000000001",
    sender: "bKash",
    body: "You have received Tk 1500.00 from 01712345678. Fee Tk 0.00. Balance Tk 4500.00. TrxID 9A8B7C6D at 29/08/2026 19:30"
  },
  '/v1/payment/merchant-qr-codes': {
    merchant_id: "00000000-0000-0000-0000-000000000001",
    qr_codes: {
      bKash: "https://example.com/qr/bkash.png",
      Nagad: "https://example.com/qr/nagad.png"
    }
  },
  '/functions/v1/create-order': {
    tran_id: "ORD-9912",
    amount: 1500.00,
    cus_phone: "01712345678",
    cus_email: "customer@gmail.com",
    callback_url: "https://mystore.com/api/webhook",
    payment_method: "bKash"
  },
  '/functions/v1/resolve-appeal': {
    appeal_id: "550e8400-e29b-41d4-a716-446655440000",
    action: "APPROVED"
  }
};

// Initialization
document.addEventListener('DOMContentLoaded', () => {
  renderLiveExamples();
  renderFlowTimeline();
  loadExplorerTemplate();
  updateSdkCodes();
  logToConsole('OK', 'SwapnoPay Enterprise Console initialized in Sandbox Mode.');
});

function renderLiveExamples() {
  const container = document.getElementById('live-examples-list');
  const linkContainer = document.getElementById('gateway-demo-links');
  if (!container) return;

  container.innerHTML = liveExamples.map((example) => `
    <div class="status-item" style="padding: 14px 16px; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.35); min-height: 120px;">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom: 10px;">
        <span style="font-weight: 700; color:#f8fafc;">${example.provider}</span>
        <span style="font-size: 10px; color: var(--primary-gold); background: rgba(250, 204, 21, 0.12); border: 1px solid rgba(250, 204, 21, 0.35); padding: 3px 7px; border-radius: 999px;">${example.mode}</span>
      </div>
      <div style="font-size: 12px; color: var(--text-muted); line-height: 1.8;">
        <div><strong>Order:</strong> ${example.order}</div>
        <div><strong>Amount:</strong> ${example.amount}</div>
        <div><strong>Phone:</strong> ${example.phone}</div>
        <div><strong>Flow:</strong> ${example.method}</div>
      </div>
      <div style="margin-top: 10px; font-size: 11px; color: var(--success);">● ${example.status}</div>
    </div>
  `).join('');

  if (linkContainer) {
    linkContainer.innerHTML = gatewayPageLinks.map((page) => `
      <a href="${page.href}" target="_blank" style="display:block; padding:14px 16px; border:1px solid rgba(148,163,184,0.35); border-radius:12px; background: rgba(15,23,42,0.35); color: var(--text-main); text-decoration:none;">
        <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--primary-gold); margin-bottom:8px;">${page.type}</div>
        <div style="font-weight:700; font-size:14px;">${page.label}</div>
      </a>
    `).join('');
  }
}

function renderFlowTimeline() {
  const container = document.getElementById('sandbox-flow-sequence');
  if (!container) return;

  container.innerHTML = fullFlowSteps.map((step, index) => `
    <div class="timeline-item ${index === 0 ? 'active' : ''}" data-flow-step="${index}">
      <div class="timeline-dot"></div>
      <span class="timeline-header">${index + 1}. ${step.label}</span>
      <span class="timeline-time">${step.detail}</span>
    </div>
  `).join('');
}

// View switcher
function switchView(viewId, element) {
  if (!document.getElementById(`view-${viewId}`)) return;
  document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
  document.querySelectorAll('.panel-view').forEach(view => view.classList.remove('active'));

  if (element) {
    element.classList.add('active');
  } else {
    const matchingMenu = document.querySelector(`.menu-item[onclick*="${viewId}"]`);
    if (matchingMenu) matchingMenu.classList.add('active');
  }

  const targetView = document.getElementById(`view-${viewId}`);
  if (targetView) targetView.classList.add('active');
  
  activeTab = viewId;
  document.querySelectorAll('.menu-item').forEach(item => {
    if (item.classList.contains('active')) item.setAttribute('aria-current', 'page');
    else item.removeAttribute('aria-current');
  });
  const viewLabel = document.getElementById('workspace-view-label');
  if (viewLabel) viewLabel.textContent = document.querySelector('.menu-item.active span:last-child')?.textContent || viewId;
  logToConsole('INFO', `Switched workspace view to: ${viewId.toUpperCase()}`);
}

// Environment Switcher
function setEnv(env) {
  environment = env;
  document.querySelectorAll('.env-btn').forEach(btn => btn.classList.remove('active'));
  
  if (env === 'sandbox') {
    document.querySelector('.env-btn.sandbox').classList.add('active');
    document.getElementById('key-pk').innerText = 'pk_sandbox_swapnopay_88f92a1109';
    document.getElementById('key-sk').innerText = 'sk_sandbox_swapnopay_99a83b2201';
    logToConsole('WARN', 'Switched workspace to SANDBOX mode. Payments and SMS will be simulated.');
    showToast('🧪 Switched to Sandbox Mode');
  } else {
    document.querySelector('.env-btn.live').classList.add('active');
    document.getElementById('key-pk').innerText = 'pk_live_swapnopay_77a11b9920';
    document.getElementById('key-sk').innerText = 'sk_live_swapnopay_44b22c1108';
    logToConsole('INFO', 'Live gateway examples enabled. Full merchant checkout and callback QA is active.');
    showToast('Live gateway QA mode enabled.');
  }

  renderLiveExamples();
}

function openGatewayDemo() {
  const target = 'widget.html?merchant_id=00000000-0000-0000-0000-000000000001&amount=1500.00&order_id=ORD-7845';
  window.open(target, '_blank', 'noopener,noreferrer');
  logToConsole('INFO', 'Opened live checkout gateway demo in a new tab.');
  showToast('Opened live checkout demo', '🧾');
}

// Logger terminal helper
function logToConsole(type, message) {
  const consoleEl = document.getElementById('terminal-console');
  if (!consoleEl) return;

  const timestamp = new Date().toTimeString().split(' ')[0];
  
  let typeSpan = '';
  if (type === 'OK') typeSpan = `<span class="log-ok">[OK]</span>`;
  else if (type === 'WARN') typeSpan = `<span class="log-warn">[WARN]</span>`;
  else if (type === 'ERROR') typeSpan = `<span class="log-err">[ERROR]</span>`;
  else typeSpan = `<span class="log-info">[INFO]</span>`;

  const logLine = document.createElement('div');
  logLine.className = 'terminal-line';
  logLine.innerHTML = `<span class="log-time">[${timestamp}]</span> ${typeSpan} <span>${message}</span>`;
  
  consoleEl.appendChild(logLine);
  consoleEl.scrollTop = consoleEl.scrollHeight;
}

// Filter Console Logs
function filterConsoleLogs() {
  const query = document.getElementById('terminal-search').value.toLowerCase();
  document.querySelectorAll('.terminal-line').forEach(line => {
    line.style.display = line.innerText.toLowerCase().includes(query) ? 'flex' : 'none';
  });
}

// Clear Console
function clearConsole() {
  const consoleEl = document.getElementById('terminal-console');
  if (consoleEl) {
    consoleEl.innerHTML = '';
    logToConsole('OK', 'Terminal stream cleared.');
  }
}

// Toast helper
function showToast(message, icon = '✨') {
  const toast = document.getElementById('toast');
  if (!toast) return;
  document.getElementById('toast-msg').innerText = message;
  document.getElementById('toast-icon').innerText = icon;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3200);
}

// Copy Helper
function copyText(text) {
  navigator.clipboard.writeText(text);
  showToast('Copied to clipboard!', '📋');
}

// API Explorer Template loader
function loadExplorerTemplate() {
  const path = document.getElementById('exp-path').value;
  const bodyEl = document.getElementById('exp-body');
  if (bodyEl && explorerTemplates[path]) {
    bodyEl.value = JSON.stringify(explorerTemplates[path], null, 2);
  }
}

// Execute API requests from API Explorer
function executeApiRequest() {
  const path = document.getElementById('exp-path').value;
  const bodyVal = document.getElementById('exp-body').value;
  const authVal = document.getElementById('exp-auth').value;
  const responseEl = document.getElementById('exp-response');
  const statusEl = document.getElementById('exp-response-status');

  logToConsole('INFO', `Executing API Call: ${path}...`);
  responseEl.innerText = "Dispatching request to SwapnoPay edge network...";
  if (statusEl) statusEl.style.display = 'none';

  const startTime = Date.now();

  setTimeout(() => {
    try {
      const parsedBody = JSON.parse(bodyVal);
      const elapsed = Date.now() - startTime;

      let responseObj = {};
      
      if (path === '/v1/payment/config') {
        responseObj = {
          success: true,
          merchant_id: parsedBody.merchant_id || "00000000-0000-0000-0000-000000000001",
          active_methods: { bKash: true, Nagad: true, Rocket: true, Upay: true },
          receiving_numbers: { bKash: "01712345678", Nagad: "01812345678" },
          qr_codes: { bKash: "https://example.com/qr/bkash.png" },
          maintenance_mode: false
        };
      } else if (path === '/v1/payment/verify') {
        responseObj = {
          success: true,
          status: "MATCHED",
          tran_id: "9A8B7C6D",
          amount: 1500.00,
          verified_at: new Date().toISOString()
        };
      } else if (path === '/functions/v1/create-order') {
        responseObj = {
          status: "SUCCESS",
          order_id: "77a8b6f3-118e-4a6f-998c-ec8844d18bb2",
          merchant_number: "01712345678",
          expires_at: new Date(Date.now() + 10 * 60 * 1000).toISOString()
        };
        
        // Update wizard step progress
        const step2 = document.getElementById('wiz-step2');
        if (step2) {
          step2.classList.add('completed');
          document.getElementById('wiz-step2-status').innerText = 'Step complete: Order registered successfully';
        }
      } else {
        responseObj = {
          status: "SUCCESS",
          message: `Endpoint ${path} executed successfully in ${elapsed}ms`,
          timestamp: new Date().toISOString()
        };
      }

      responseEl.innerHTML = JSON.stringify(responseObj, null, 2);
      if (statusEl) {
        statusEl.innerText = `HTTP 200 OK (${elapsed}ms)`;
        statusEl.className = 'status-badge ok';
        statusEl.style.display = 'inline-block';
      }
      logToConsole('OK', `API Call ${path} returned HTTP 200 OK in ${elapsed}ms.`);
      showToast('API Endpoint Call Successful', '⚡');
    } catch (e) {
      responseEl.innerText = `Invalid JSON Body format: ${e.message}`;
      if (statusEl) {
        statusEl.innerText = `HTTP 400 Bad Request`;
        statusEl.className = 'status-badge err';
        statusEl.style.display = 'inline-block';
      }
      logToConsole('ERROR', `Request Syntax Error: ${e.message}`);
    }
  }, 450);
}

// Export OpenAPI Spec
function exportOpenApiSpec() {
  const openApiSpec = {
    openapi: "3.0.0",
    info: {
      title: "SwapnoPay Merchant Gateway API",
      version: "1.0.0",
      description: "Automated MFS Payment Matching & Webhook Gateway Specification"
    },
    servers: [{ url: "https://api.swapnopay.top" }],
    paths: {
      "/v1/payment/config": { get: { summary: "Fetch Merchant Gateway Configuration" } },
      "/v1/payment/verify": { post: { summary: "Verify SMS Payment Broadcast" } },
      "/v1/payment/merchant-config": { post: { summary: "Sync Merchant MFS Numbers & QR Codes" } }
    }
  };
  const jsonStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(openApiSpec, null, 2));
  const dlAnchorElem = document.createElement('a');
  dlAnchorElem.setAttribute("href", jsonStr);
  dlAnchorElem.setAttribute("download", "SwapnoPay_OpenAPI_3.0_Spec.json");
  dlAnchorElem.click();
  showToast('Exported OpenAPI 3.0 Spec JSON', '📄');
}

// Dynamic SDK code updates
function updateSdkCodes() {
  const amountVal = document.getElementById('sdk-amount') ? document.getElementById('sdk-amount').value : "1500.00";
  const amount = amountVal.replace(/,/g, '');
  const orderId = document.getElementById('sdk-order-id') ? document.getElementById('sdk-order-id').value : "ORD-7845";
  const phone = document.getElementById('sdk-phone') ? document.getElementById('sdk-phone').value : "01712345678";
  const callback = document.getElementById('sdk-callback') ? document.getElementById('sdk-callback').value : "https://mystore.com/api/webhook";

  const codeBlocks = {
    node: `const { SwapnoPayClient } = require('@swapnopay/sdk');

const client = new SwapnoPayClient({
  secretKey: 'sk_live_swapnopay_secret_key_here',
  backendUrl: 'https://api.swapnopay.top'
});

// Register checkout order
client.createOrder({
  tran_id: "${orderId}",
  amount: ${parseFloat(amount) || 0.0},
  cus_phone: "${phone}",
  callback_url: "${callback}"
})
.then(res => console.log('Order initialized:', res.order_id))
.catch(err => console.error('Verification failed:', err.message));`,

    php: `<?php
require_once 'vendor/autoload.php';

use SwapnoPay\\SwapnoPayClient;

$client = new SwapnoPayClient([
    'secretKey'  => 'sk_live_swapnopay_secret_key_here',
    'backendUrl' => 'https://api.swapnopay.top'
]);

$response = $client->createOrder([
    'tran_id'      => '${orderId}',
    'amount'       => ${parseFloat(amount) || 0.0},
    'cus_phone'    => '${phone}',
    'callback_url' => '${callback}'
]);

print_r($response);`,

    python: `from swapnopay import SwapnoPayClient

client = SwapnoPayClient(
    secret_key="sk_live_swapnopay_secret_key_here",
    backend_url="https://api.swapnopay.top"
)

response = client.create_order(
    tran_id="${orderId}",
    amount=${parseFloat(amount) || 0.0},
    cus_phone="${phone}",
    callback_url="${callback}"
)

print(response)`,

    go: `package main

import (
	"fmt"
	"github.com/swapnopay/sdk-go"
)

func main() {
	client, _ := swapnopay.NewClient(swapnopay.Config{
		SecretKey:  "sk_live_swapnopay_secret_key_here",
		BackendURL: "https://api.swapnopay.top",
	})

	res, _ := client.CreateOrder(swapnopay.CreateOrderRequest{
		TranID:      "${orderId}",
		Amount:      ${parseFloat(amount) || 0.0},
		CusPhone:    "${phone}",
		CallbackURL: "${callback}",
	})

	fmt.Printf("Registered Checkout Order ID: %s\\n", res.OrderID)
}`,

    curl: `curl -X POST https://api.swapnopay.top/v1/payment/merchant-config \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer sk_live_swapnopay_secret_key_here" \\
  -d '{
    "merchant_id": "00000000-0000-0000-0000-000000000001",
    "tran_id": "${orderId}",
    "amount": ${parseFloat(amount) || 0.0},
    "cus_phone": "${phone}",
    "callback_url": "${callback}"
  }'`,

    html: `<!-- SwapnoPay Inline Embedded Web Checkout Widget -->
<iframe 
  src="https://api.swapnopay.top/web/widget.html?merchant_id=00000000-0000-0000-0000-000000000001&amount=${parseFloat(amount) || 0.0}&order_id=${orderId}"
  width="100%" 
  height="620px" 
  style="border:none; border-radius:16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);"
  allow="clipboard-write">
</iframe>`
  };

  const block = document.getElementById('sdk-code-block');
  if (block) {
    block.innerText = codeBlocks[activeSdkLang];
  }
}

// SDK switch
function switchSdkLang(lang, element) {
  document.querySelectorAll('.code-tabs .tab-btn').forEach(btn => btn.classList.remove('active'));
  element.classList.add('active');
  activeSdkLang = lang;
  updateSdkCodes();
}

// Clipboard copy helper
function copySdkCode() {
  const block = document.getElementById('sdk-code-block');
  if (block) {
    navigator.clipboard.writeText(block.innerText);
    logToConsole('INFO', `Copied ${activeSdkLang.toUpperCase()} integration snippet to clipboard.`);
    showToast(`Copied ${activeSdkLang.toUpperCase()} code!`, '📋');
  }
}

// Test Run SDK Snippet
function runSdkSnippetTest() {
  logToConsole('INFO', `Simulating test execution of ${activeSdkLang.toUpperCase()} SDK snippet...`);
  showToast(`Running ${activeSdkLang.toUpperCase()} Snippet...`, '🚀');
  setTimeout(() => {
    logToConsole('OK', `SDK Snippet executed cleanly. Order initialized: 77a8b6f3-118e-4a6f-998c-ec8844d18bb2`);
  }, 750);
}

function runFullSandboxPaymentFlow() {
  const flowStatus = document.getElementById('sandbox-flow-status');
  const steps = document.querySelectorAll('#sandbox-flow-sequence .timeline-item');
  steps.forEach((step, index) => {
    step.classList.remove('active', 'completed');
    if (index === 0) step.classList.add('active');
  });

  if (flowStatus) flowStatus.textContent = 'Status: running full payment flow in sandbox';
  logToConsole('INFO', 'Starting full sandbox payment flow for a real checkout lifecycle.');
  showToast('Running full payment flow...', '🔄');

  const schedule = [
    { index: 0, label: 'Order created', status: 'Order ORD-7845 registered and awaiting payment.' },
    { index: 1, label: 'Customer selected payment', status: 'bKash payment intent prepared for Tk 1,500.00.' },
    { index: 2, label: 'SMS received', status: 'Incoming SMS matched via TrxID 9A8B7C6D.' },
    { index: 3, label: 'Payment verified', status: 'Merchant ledger updated and amount confirmed.' },
    { index: 4, label: 'Webhook delivered', status: 'Signed payment.success callback sent to merchant callback URL.' }
  ];

  schedule.forEach((item, idx) => {
    setTimeout(() => {
      const step = document.querySelector(`#sandbox-flow-sequence .timeline-item[data-flow-step="${item.index}"]`);
      if (step) {
        step.classList.remove('active');
        step.classList.add('completed');
      }

      if (idx < schedule.length - 1) {
        const nextStep = document.querySelector(`#sandbox-flow-sequence .timeline-item[data-flow-step="${item.index + 1}"]`);
        if (nextStep) nextStep.classList.add('active');
      }

      if (flowStatus) flowStatus.textContent = `Status: ${item.status}`;
      logToConsole('OK', `${item.label}: ${item.status}`);

      if (item.index === 4) {
        const webhookEvent = {
          id: `evt_${Date.now()}`,
          timestamp: new Date().toLocaleTimeString(),
          targetUrl: 'https://mystore.com/api/webhook',
          eventType: 'payment.success (bKash)',
          status: '200 OK',
          latency: '38ms',
          headers: {
            'x-swapnopay-signature': 't=17564600,v1=9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a',
            'content-type': 'application/json'
          },
          payload: {
            event: 'payment.success',
            order_id: 'ORD-7845',
            amount: 1500,
            customer_phone: '01712345678',
            mfs_provider: 'bKash',
            trx_id: '9A8B7C6D',
            status: 'PAID',
            timestamp: new Date().toISOString()
          }
        };

        webhookLogStore.unshift(webhookEvent);
        const tableEl = document.getElementById('webhook-logs-table');
        if (tableEl) {
          const newRow = document.createElement('tr');
          newRow.onclick = () => openPayloadModal(webhookEvent.id);
          newRow.innerHTML = `
            <td>${webhookEvent.timestamp}</td>
            <td>https://mystore.com/api/webhook</td>
            <td><code>payment.success (bKash)</code></td>
            <td><span class="status-badge ok">200 OK</span></td>
            <td>38ms</td>
            <td><button class="btn btn-secondary" style="padding:3px 8px; font-size:10px;" onclick="event.stopPropagation(); openPayloadModal('${webhookEvent.id}')">Inspect</button></td>
          `;
          if (tableEl.innerHTML.includes('No webhook events')) tableEl.innerHTML = '';
          tableEl.insertBefore(newRow, tableEl.firstChild);
        }

        showToast('Payment matched and webhook delivered!', '🎉');
      }
    }, 700 * (idx + 1));
  });
}

// Webhook simulation handler
function triggerMfsSim(mfs) {
  const amount = document.getElementById('sim-input-amount') ? document.getElementById('sim-input-amount').value : "1500.00";
  const phone = document.getElementById('sim-input-phone') ? document.getElementById('sim-input-phone').value : "01712345678";

  logToConsole('INFO', `Initializing simulation event: ${mfs.toUpperCase()} Payment for Tk ${amount}...`);
  showToast(`Simulating ${mfs.toUpperCase()} SMS...`, '📱');

  const timelineStep2 = document.getElementById('time-step2');
  const timelineStep3 = document.getElementById('time-step3');
  const timelineStep4 = document.getElementById('time-step4');
  
  if (timelineStep2) {
    timelineStep2.classList.add('active');
    timelineStep2.querySelector('.timeline-time').innerText = `Intercepted SMS at ${new Date().toLocaleTimeString()}`;
  }

  let mockTrxId = 'TXN' + Math.floor(Math.random() * 900000 + 100000);
  
  if (mfs === 'fail') {
    logToConsole('WARN', 'Order expiration reached: ORD-7845 cancelled due to timeout.');
    showToast('Order Expiration Timeout Simulated', '❌');
    return;
  } else if (mfs === 'appeal') {
    logToConsole('INFO', `Dispute Appeal submitted for TrxID ${mockTrxId} by customer ${phone}.`);
    showToast('Dispute Appeal Submitted', '⚖');
    return;
  }

  setTimeout(() => {
    logToConsole('OK', `Parsed raw SMS matching successfully. Amount: ${amount} BDT, Payer: ${phone}, TrxID: ${mockTrxId}.`);
    if (timelineStep3) {
      timelineStep3.classList.add('active');
      timelineStep3.querySelector('.timeline-time').innerText = `Matched at ${new Date().toLocaleTimeString()}`;
    }
  }, 900);

  setTimeout(() => {
    logToConsole('OK', `Webhook HMAC SHA256 signature verified. Delivered callback to target merchant server.`);
    
    if (timelineStep4) {
      timelineStep4.classList.add('completed');
      timelineStep4.querySelector('.timeline-time').innerText = `Delivered Status: HTTP 200 OK (38ms)`;
    }

    const timestamp = new Date().toLocaleTimeString();
    const eventObj = {
      id: `evt_${Date.now()}`,
      timestamp: timestamp,
      targetUrl: "https://mystore.com/api/webhook",
      eventType: `payment.success (${mfs})`,
      status: "200 OK",
      latency: "38ms",
      headers: {
        "x-swapnopay-signature": "t=17564600,v1=9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a",
        "content-type": "application/json"
      },
      payload: {
        event: "payment.success",
        tran_id: "ORD-7845",
        amount: parseFloat(amount),
        customer_phone: phone,
        mfs_provider: mfs,
        trx_id: mockTrxId,
        status: "PAID",
        timestamp: new Date().toISOString()
      }
    };
    webhookLogStore.unshift(eventObj);

    // Append to Webhooks ledger table
    const tableEl = document.getElementById('webhook-logs-table');
    if (tableEl) {
      const newRow = document.createElement('tr');
      newRow.onclick = () => openPayloadModal(eventObj.id);
      newRow.innerHTML = `
        <td>${timestamp}</td>
        <td>https://mystore.com/api/webhook</td>
        <td><code>payment.success (${mfs})</code></td>
        <td><span class="status-badge ok">200 OK</span></td>
        <td>38ms</td>
        <td><button class="btn btn-secondary" style="padding:3px 8px; font-size:10px;" onclick="event.stopPropagation(); openPayloadModal('${eventObj.id}')">Inspect</button></td>
      `;
      if (tableEl.innerHTML.includes('No webhook events')) {
        tableEl.innerHTML = '';
      }
      tableEl.insertBefore(newRow, tableEl.firstChild);
    }

    // Mark Onboarding Wizard Steps
    const wizStep3 = document.getElementById('wiz-step3');
    const wizStep4 = document.getElementById('wiz-step4');
    if (wizStep3) {
      wizStep3.classList.add('completed');
      document.getElementById('wiz-step3-status').innerText = 'Step complete: Simulated MFS event processed';
    }
    if (wizStep4) {
      wizStep4.classList.add('completed');
      document.getElementById('wiz-step4-status').innerText = 'Step complete: Webhook signature verified & dispatched';
    }

    showToast('Payment Matched & Webhook Dispatched!', '🎉');
  }, 1800);
}

// Live Widget Preview Controls
function openWidgetPreview() {
  document.getElementById('widget-modal').classList.add('active');
  logToConsole('INFO', 'Opened Live SwapnoPay Checkout Widget Preview modal.');
}
function closeWidgetPreview() {
  document.getElementById('widget-modal').classList.remove('active');
}

// Payload Inspector Modal Controls
function openPayloadModal(eventId) {
  const item = webhookLogStore.find(e => e.id === eventId) || webhookLogStore[0];
  if (!item) return;

  document.getElementById('inspect-event-id').innerText = item.id;
  document.getElementById('inspect-headers').innerText = JSON.stringify(item.headers, null, 2);
  document.getElementById('inspect-body').innerText = JSON.stringify(item.payload, null, 2);
  document.getElementById('payload-modal').classList.add('active');
  logToConsole('INFO', `Inspecting webhook payload for event ID: ${item.id}`);
}
function closePayloadModal() {
  document.getElementById('payload-modal').classList.remove('active');
}

// Replay Webhook Event
function replayWebhookEvent() {
  showToast('Replaying Webhook Callback...', '🔄');
  logToConsole('INFO', 'Replaying webhook callback dispatch to https://mystore.com/api/webhook...');
  setTimeout(() => {
    logToConsole('OK', 'Replayed webhook callback delivered successfully. HTTP 200 OK (32ms).');
    showToast('Webhook Replayed Successfully!', '🎉');
    closePayloadModal();
  }, 1000);
}

// Export Webhook Ledger CSV
function exportWebhookLedger() {
  if (webhookLogStore.length === 0) {
    showToast('No webhook logs to export', '⚠️');
    return;
  }
  let csv = "Timestamp,TargetURL,EventType,Status,Latency\n";
  webhookLogStore.forEach(item => {
    csv += `"${item.timestamp}","${item.targetUrl}","${item.eventType}","${item.status}","${item.latency}"\n`;
  });
  const jsonStr = "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
  const dlAnchorElem = document.createElement('a');
  dlAnchorElem.setAttribute("href", jsonStr);
  dlAnchorElem.setAttribute("download", "SwapnoPay_Webhook_Ledger.csv");
  dlAnchorElem.click();
  showToast('Exported Webhook Ledger CSV', '📊');
}
