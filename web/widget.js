// SwapnoPay Secure Checkout — Production Widget Core
// v3.0 — WebSocket payment detection, merchant DB device check, logo support,
//         race-condition protection, heartbeat, dual-channel real-time

// ──────────────────────────────────────────────────────────────────────────────
// State
// ──────────────────────────────────────────────────────────────────────────────
let selectedMethod = "bKash";
let selectedColor  = "#E2125A";
let orderId        = "";
let payableAmount  = "";
let merchantId     = "";          // loaded from config
let merchantLogoUrl = "";
let merchantDefaultNumber = "017XXXXXXXX";
let supabaseUrl    = "";
let supabaseAnonKey = "";
let backendUrl     = "";
let successUrl     = "/";
let failUrl        = "/";
let cancelUrl      = "/";
let merchantReceivingNumbers = {};
let merchantAccountTypes = {};
let countdownSeconds = 600;
let timerInterval    = null;
let webSocket        = null;
let socketClient     = null;
let socketHeartbeatInterval = null;
let paymentResolved  = false;     // race-condition guard: only handle first resolution
let procSeconds = 300;
let procInterval = null;
let currentLang  = "en";

// ──────────────────────────────────────────────────────────────────────────────
// Multi-language translation database
// ──────────────────────────────────────────────────────────────────────────────
const translations = {
  en: {
    stepOf: "Step", of: "of",
    choosePay: "Choose Payment Method",
    selectPref: "Select your preferred payment option",
    payNumber: "Payment Number",
    enter11: "Enter 11 digit mobile number",
    continuePay: "Continue to Pay",
    payRequest: "You will receive the transaction reference number and merchant wallet account details on the next step.",
    scanPay: "Scan & Pay",
    scanHint: "Scan this QR code with your app to pay instantly.",
    merchantNum: "Merchant Number",
    amount: "Amount",
    copy: "Copy",
    timerHint: "Complete the payment within",
    btnTransferred: "I Have Completed Payment",
    btnProcessing: "Processing Transaction...",
    cancelPay: "Cancel Payment",
    pendingTitle: "Payment is Pending",
    pendingMsg: "We are still trying to verify your payment. You can appeal below, and we will notify you if your payment is verified.",
    payFailed: "Payment Failed?",
    appealDesc: "Don't worry! You can appeal here. Please provide the transaction details and screenshot.",
    trxLabel: "Transaction ID",
    uploadScreenshot: "Upload Screenshot",
    uploadTitle: "Click to upload screenshot",
    uploadSize: "PNG, JPG up to 5MB",
    notesLabel: "Additional Notes",
    notesOpt: "(Optional)",
    appealSubmit: "Submit Appeal",
    successTitle: "Payment Successful",
    successDesc: "Your transaction has been verified. Thank you for your purchase.",
    receiptTitle: "Transaction Receipt",
    paid: "PAID",
    receiptId: "Receipt / Order ID",
    receiptTrx: "Transaction Ref ID",
    receiptDate: "Date & Time",
    receiptGate: "Payment Gateway",
    amtPaid: "Amount Paid",
    btnReturn: "Return to Store",
    cancelTitle: "Cancel Payment?",
    cancelMsg: "Are you sure you want to cancel this payment? Stopping now will interrupt your purchase.",
    cancelConseq: "What happens if you cancel:",
    cancelC1: "The order will not be processed or shipped.",
    cancelC2: "Any pending transaction with SwapnoPay will be voided.",
    btnNoGoBack: "No, Go Back",
    btnYesCancel: "Yes, Cancel Order",
    cancelledTitle: "Payment Cancelled",
    cancelledMsg: "The transaction has been successfully voided. You can now safely return to the merchant's online store.",
    orderInfo: "Order Details",
    orderIdLabel: "Order ID",
    descLabel: "Description",
    verifiedSecure: "Verified Secure",
    securePay: "Secure Payment",
    sslEncrypt: "256-bit SSL Encrypted",
    processingTitle: "Verifying Payment",
    processingMsg: "Verifying payment automatically... Please do not close this tab.",
    timeLeft: "Time Remaining",
    waitApprove: "Please wait while we verify your transaction status.",
    phoneError: "Please enter a valid 11-digit mobile number.",
    appealTrxError: "Transaction ID is required.",
    appealFileError: "Screenshot upload is required.",
    helplineTitle: "Need Help?",
    helplineSub: "24/7 Helpline Support",
  },
  bn: {
    stepOf: "ধাপ", of: "এর",
    choosePay: "পেমেন্ট পদ্ধতি নির্বাচন করুন",
    selectPref: "আপনার পছন্দের পেমেন্ট অপশনটি সিলেক্ট করুন",
    payNumber: "পেমেন্ট মোবাইল নম্বর",
    enter11: "১১ ডিজিটের মোবাইল নম্বরটি লিখুন",
    continuePay: "পেমেন্ট করতে এগিয়ে যান",
    payRequest: "পরবর্তী ধাপে আপনি লেনদেনের রেফারেন্স নম্বর এবং মার্চেন্ট ওয়ালেট অ্যাকাউন্ট বিবরণ পাবেন।",
    scanPay: "স্ক্যান এবং পে করুন",
    scanHint: "তাত্ক্ষণিকভাবে অর্থ প্রদানের জন্য আপনার অ্যাপ দিয়ে এই QR কোডটি স্ক্যান করুন।",
    merchantNum: "মার্চেন্ট নম্বর",
    amount: "পরিমাণ",
    copy: "কপি",
    timerHint: "পেমেন্ট সম্পন্ন করুন এই সময়ের মধ্যে",
    btnTransferred: "আমি পেমেন্ট সম্পন্ন করেছি",
    btnProcessing: "পেমেন্ট যাচাই করা হচ্ছে...",
    cancelPay: "পেমেন্ট বাতিল করুন",
    pendingTitle: "পেমেন্ট পেন্ডিং রয়েছে",
    pendingMsg: "আমরা এখনও আপনার পেমেন্ট যাচাই করার চেষ্টা করছি। আপনি নিচে আপিল করতে পারেন এবং পেমেন্ট যাচাই করা হলে আপনাকে অবহিত করা হবে।",
    payFailed: "পেমেন্ট ব্যর্থ হয়েছে?",
    appealDesc: "চিন্তা করবেন না! আপনি এখানে আপিল করতে পারেন। অনুগ্রহ করে লেনদেনের বিবরণ এবং স্ক্রিনশট প্রদান করুন।",
    trxLabel: "লেনদেন (Trx) আইডি",
    uploadScreenshot: "স্ক্রিনশট আপলোড",
    uploadTitle: "স্ক্রিনশট আপলোড করতে ক্লিক করুন",
    uploadSize: "পিএনজি, জেপিজি সর্বোচ্চ ৫ মেগাবাইট",
    notesLabel: "অতিরিক্ত মন্তব্য",
    notesOpt: "(ঐচ্ছিক)",
    appealSubmit: "আপিল জমা দিন",
    successTitle: "পেমেন্ট সফল হয়েছে",
    successDesc: "আপনার লেনদেন যাচাই করা হয়েছে। আপনার ক্রয়ের জন্য ধন্যবাদ।",
    receiptTitle: "লেনদেন রসিদ",
    paid: "পরিশোধিত",
    receiptId: "রসিদ / অর্ডার আইডি",
    receiptTrx: "লেনদেন রেফারেন্স আইডি",
    receiptDate: "তারিখ ও সময়",
    receiptGate: "পেমেন্ট গেটওয়ে",
    amtPaid: "পরিশোধিত পরিমাণ",
    btnReturn: "স্টোরে ফিরে যান",
    cancelTitle: "পেমেন্ট বাতিল করবেন?",
    cancelMsg: "আপনি কি নিশ্চিত যে আপনি পেমেন্ট বাতিল করতে চান? এখন বাতিল করলে আপনার ক্রয় প্রক্রিয়া বাধাগ্রস্ত হবে।",
    cancelConseq: "বাতিল করলে কি ঘটবে:",
    cancelC1: "অর্ডারটি প্রক্রিয়াজাত বা পাঠানো হবে না।",
    cancelC2: "SwapnoPay-এর সাথে যেকোনো পেন্ডিং লেনদেন বাতিল করা হবে।",
    btnNoGoBack: "না, ফিরে যান",
    btnYesCancel: "হ্যাঁ, পেমেন্ট বাতিল করুন",
    cancelledTitle: "পেমেন্ট বাতিল করা হয়েছে",
    cancelledMsg: "লেনদেনটি সফলভাবে বাতিল করা হয়েছে। আপনি এখন নিরাপদে মার্চেন্টের অনলাইন স্টোরে ফিরে যেতে পারেন।",
    orderInfo: "অর্ডার বিবরণ",
    orderIdLabel: "অর্ডার আইডি",
    descLabel: "বিবরণ",
    verifiedSecure: "যাচাইকৃত নিরাপদ",
    securePay: "নিরাপদ পেমেন্ট",
    sslEncrypt: "২৫৬-বিট SSL এনক্রিপ্ট করা",
    processingTitle: "পেমেন্ট যাচাই করা হচ্ছে",
    processingMsg: "স্বয়ংক্রিয়ভাবে পেমেন্ট যাচাই করা হচ্ছে... অনুগ্রহ করে এই ট্যাবটি বন্ধ করবেন না।",
    timeLeft: "বাকি সময়",
    waitApprove: "আমরা আপনার লেনদেন যাচাই করার সময় অনুগ্রহ করে অপেক্ষা করুন।",
    phoneError: "অনুগ্রহ করে একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর লিখুন।",
    appealTrxError: "লেনদেন (Trx) আইডি আবশ্যক।",
    appealFileError: "স্ক্রিনশট আপলোড করা আবশ্যক।",
    helplineTitle: "সহায়তা প্রয়োজন?",
    helplineSub: "২৪/৭ হেল্পলাইন সাপোর্ট",
  }
};

// ──────────────────────────────────────────────────────────────────────────────
// Initialise on page load
// ──────────────────────────────────────────────────────────────────────────────
window.onload = function () {
  const params = new URLSearchParams(window.location.search);
  orderId         = params.get("order_id")        || "demo_order_id";
  supabaseUrl     = params.get("supabase_url")    || "";
  supabaseAnonKey = params.get("supabase_anon_key") || "demo_anon_key";
  backendUrl      = params.get("backend_url")     || (window.location.hostname.includes("swapnopay.top") ? "https://api.swapnopay.top" : window.location.origin);
  successUrl      = params.get("success_url")     || "/";
  failUrl         = params.get("fail_url")        || params.get("failure_url") || "/";
  cancelUrl       = params.get("cancel_url")      || "/";

  const amount         = params.get("amount")          || "1,500.00";
  const merchantName   = params.get("merchant_name")   || "DreamMart";
  const receiverNumber = params.get("merchant_number") || "017XXXXXXXX";
  merchantDefaultNumber = receiverNumber;

  const urlMerchantId = params.get("merchant_id") || null;
  if (urlMerchantId) {
    merchantId = urlMerchantId;
  }

  const initialMethod = params.get("method") || "bKash";
  const methodColorMap = { bKash: "#E2125A", Nagad: "#EC5A24", Rocket: "#8C3494", Upay: "#10B981" };

  setAmountDisplay(amount);
  setMerchantNameDisplay(merchantName);
  document.getElementById("merchant-num-display").value = receiverNumber;
  document.getElementById("summary-order-id").innerText =
    orderId !== "demo_order_id" ? `#${orderId.slice(0, 8)}...` : "#DEMO-9912";

  updateQrCode(receiverNumber);
  setLanguage("en");
  selectMFS(initialMethod, methodColorMap[initialMethod] || "#E2125A");
  goToStep(1);

  // ── Step 1: Connect Socket.io immediately if real order ──
  if (backendUrl && orderId && orderId !== "demo_order_id") {
    connectSwapnoPaySocket(backendUrl, orderId);
  }

  // ── Step 2: Load gateway config (fetches branding, Supabase DB credentials, receiving numbers, and status) ──
  loadGatewayConfig(merchantId || null);

  // ── Step 3: If real order credentials were in URL, fetch from merchant's Supabase immediately ──
  const isRealOrder = orderId !== "demo_order_id"
    && /^https:\/\/[a-z0-9.-]+$/i.test(supabaseUrl)
    && supabaseAnonKey !== "demo_anon_key";

  if (isRealOrder) {
    window._orderDataLoaded = true;
    fetchOrderFromMerchantDB();
    connectSupabaseRealtime(supabaseUrl, supabaseAnonKey, orderId);
  }
};

// ──────────────────────────────────────────────────────────────────────────────
// Fetch order + merchant data from merchant's own Supabase DB
// ──────────────────────────────────────────────────────────────────────────────
function fetchOrderFromMerchantDB() {
  if (!supabaseUrl || !supabaseAnonKey || supabaseAnonKey === "demo_anon_key") {
    fetchOrderFromBackendAPI();
    return;
  }

  const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(orderId);
  const filter = isUuid ? `or=(id.eq.${orderId},tran_id.eq.${orderId})` : `tran_id=eq.${orderId}`;

  fetch(`${supabaseUrl}/rest/v1/orders?${filter}&select=*,merchants(*),form_submissions(form_id,payment_forms(logo_url,description))`, {
    headers: {
      "apikey": supabaseAnonKey,
      "Authorization": `Bearer ${supabaseAnonKey}`
    }
  })
    .then(res => res.json())
    .then(orders => {
      if (!orders || orders.length === 0) {
        // Fallback to backend API if not found via direct Supabase REST
        fetchOrderFromBackendAPI();
        return;
      }
      const order = orders[0];

      if (order.amount) {
        const formatted = parseFloat(order.amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
        setAmountDisplay(formatted);
      }

      document.getElementById("summary-order-id").innerText =
        order.tran_id || `#${order.id.slice(0, 8)}...`;

      // Load form submission logo/description
      if (order.form_submissions && order.form_submissions.length > 0) {
        const sub = order.form_submissions[0];
        if (sub.payment_forms) {
          if (sub.payment_forms.logo_url && !merchantLogoUrl) {
            setMerchantLogo(sub.payment_forms.logo_url);
          }
          if (sub.payment_forms.description) {
            const descEl = document.getElementById("summary-desc");
            if (descEl) descEl.innerText = sub.payment_forms.description;
          }
        }
      }

      const merchant = order.merchants;
      if (merchant) {
        if (merchant.id) merchantId = merchant.id;
        const businessName  = merchant.business_name || "Merchant";
        const defaultNumber = merchant.default_number || merchantDefaultNumber;
        merchantDefaultNumber = defaultNumber;

        setMerchantNameDisplay(businessName);
        if (merchant.photo_url && !merchantLogoUrl) {
          setMerchantLogo(merchant.photo_url);
        }
        document.getElementById("merchant-num-display").value = defaultNumber;
        updateQrCode(defaultNumber);

        // Re-load gateway config with resolved merchant_id if needed
        if (merchant.id) {
          loadGatewayConfig(merchant.id);
        }
      }
    })
    .catch(err => {
      console.warn("[widget] Direct merchant DB fetch error, falling back to backend:", err.message);
      fetchOrderFromBackendAPI();
    });
}

// ──────────────────────────────────────────────────────────────────────────────
// Fallback: Fetch order details from SwapnoPay Backend API
// ──────────────────────────────────────────────────────────────────────────────
function fetchOrderFromBackendAPI() {
  if (!backendUrl || !orderId || orderId === "demo_order_id") return;
  const qMid = merchantId || new URLSearchParams(window.location.search).get("merchant_id") || "";
  const targetUrl = `${backendUrl}/v1/payment/order/${encodeURIComponent(orderId)}?merchant_id=${encodeURIComponent(qMid)}`;

  fetch(targetUrl, { signal: AbortSignal.timeout(6000) })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.ok) return;
      if (data.amount) {
        const formatted = parseFloat(data.amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
        setAmountDisplay(formatted);
      }
      if (data.tran_id) {
        document.getElementById("summary-order-id").innerText = data.tran_id;
      }
      if (data.product_name) {
        const descEl = document.getElementById("summary-desc");
        if (descEl) descEl.innerText = data.product_name;
      }
      if (data.merchant_name) {
        setMerchantNameDisplay(data.merchant_name);
      }
      if (data.merchant_id && !merchantId) {
        merchantId = data.merchant_id;
        loadGatewayConfig(merchantId);
      }
      if (data.status === 'PAID' && !paymentResolved) {
        paymentResolved = true;
        showSuccessScreen(data);
      }
    })
    .catch(err => console.warn('[widget] Backend order status fallback error:', err.message));
}

// ──────────────────────────────────────────────────────────────────────────────
// Load gateway config from SwapnoPay backend
// Includes: enabled_methods, timeouts, device_active, merchant_logo_url,
//           supabase_url, supabase_anon_key, receiving_numbers
// ──────────────────────────────────────────────────────────────────────────────
function loadGatewayConfig(merchantIdParam) {
  if (!backendUrl) return;
  const queryMerchantId = merchantIdParam || merchantId || null;
  const qParams = new URLSearchParams();
  if (queryMerchantId) qParams.set("merchant_id", queryMerchantId);
  if (orderId && orderId !== "demo_order_id") qParams.set("order_id", orderId);
  if (payableAmount) qParams.set("amount", payableAmount);
  const qEmail = new URLSearchParams(window.location.search).get("cus_email") || "";
  if (qEmail) qParams.set("cus_email", qEmail);

  const targetUrl = qParams.toString()
    ? `${backendUrl}/v1/payment/config?${qParams.toString()}`
    : `${backendUrl}/v1/payment/config`;

  fetch(targetUrl, { signal: AbortSignal.timeout(6000) })
    .then(r => r.json())
    .then(config => {
      // ── Maintenance mode check ──
      if (config.maintenance_mode) {
        const msg = config.maintenance_message || "Payment processing is temporarily unavailable. Please try again later.";
        document.body.innerHTML = `<div style="font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#F8FAFC">
          <div style="max-width:480px;text-align:center;padding:40px;background:white;border-radius:20px;box-shadow:0 8px 32px rgba(0,0,0,0.08);border:1px solid #E2E8F0">
            <div style="font-size:48px;margin-bottom:16px">🚧</div>
            <h2 style="font-size:22px;font-weight:800;color:#1E293B;margin-bottom:8px">Maintenance Mode</h2>
            <p style="color:#64748B;line-height:1.6">${msg}</p>
            <p style="margin-top:20px;font-size:12px;color:#94A3B8">Powered by SwapnoPay</p>
          </div></div>`;
        return;
      }

      // ── Merchant ID & DB credentials from backend config ──
      if (config.merchant_id && !merchantId) {
        merchantId = config.merchant_id;
      }
      if (config.supabase_url) {
        supabaseUrl = config.supabase_url;
      }
      if (config.supabase_anon_key && config.supabase_anon_key !== "demo_anon_key") {
        supabaseAnonKey = config.supabase_anon_key;
      }

      // ── Merchant logo from admin DB / merchant settings ──
      if (config.merchant_logo_url && !merchantLogoUrl) {
        setMerchantLogo(config.merchant_logo_url);
      }

      // ── Merchant name from admin DB / merchant settings ──
      if (config.merchant_name) {
        setMerchantNameDisplay(config.merchant_name);
      }

      // ── Receiving numbers per MFS method ──
      if (config.receiving_numbers) {
        merchantReceivingNumbers = config.receiving_numbers;
        if (merchantReceivingNumbers[selectedMethod]) {
          document.getElementById("merchant-num-display").value = merchantReceivingNumbers[selectedMethod];
          updateQrCode(merchantReceivingNumbers[selectedMethod]);
        }
      }

      // ── Account types (Personal vs Merchant) ──
      if (config.account_types) {
        merchantAccountTypes = config.account_types;
      }
      updateLabelsForMfs(selectedMethod);

      // ── Redirect URLs (respect query parameters if provided) ──
      const urlRedirectParams = new URLSearchParams(window.location.search);
      if (config.default_success_url && (!urlRedirectParams.get("success_url") || successUrl === "/")) successUrl = config.default_success_url;
      if (config.default_fail_url && (!urlRedirectParams.get("fail_url") && !urlRedirectParams.get("failure_url") || failUrl === "/")) failUrl = config.default_fail_url;
      if (config.default_cancel_url && (!urlRedirectParams.get("cancel_url") || cancelUrl === "/")) cancelUrl = config.default_cancel_url;

      // ── Countdown timer ──
      if (config.payment_timeout_seconds) {
        countdownSeconds = config.payment_timeout_seconds;
      }

      // ── Enabled payment methods ──
      const methods = config.enabled_methods || {};
      const methodMap = { bKash: 'opt-bkash', Nagad: 'opt-nagad', Rocket: 'opt-rocket', Upay: 'opt-upay' };
      let firstEnabled = null;
      Object.entries(methodMap).forEach(([method, id]) => {
        const el = document.getElementById(id);
        if (!el) return;
        const enabled = methods[method] !== false;
        el.style.display = enabled ? '' : 'none';
        if (enabled && !firstEnabled) firstEnabled = method;
      });

      if (firstEnabled) {
        const colors = { bKash: '#E2125A', Nagad: '#EC5A24', Rocket: '#8C3494', Upay: '#10B981' };
        const reqMethod = new URLSearchParams(window.location.search).get("method");
        const methodToSelect = (reqMethod && methods[reqMethod] !== false) ? reqMethod : firstEnabled;
        selectMFS(methodToSelect, colors[methodToSelect] || colors[firstEnabled]);
      }

      // ── Dynamically connect to Merchant DB / order info if not already loaded ──
      if (orderId && orderId !== "demo_order_id" && !window._orderDataLoaded) {
        window._orderDataLoaded = true;
        if (supabaseUrl && supabaseAnonKey && supabaseAnonKey !== "demo_anon_key") {
          fetchOrderFromMerchantDB();
          connectSupabaseRealtime(supabaseUrl, supabaseAnonKey, orderId);
        } else {
          fetchOrderFromBackendAPI();
        }
      }

      // ── Device active gate ──
      // device_active: true = proceed | false = show offline | null = skip check
      handleDeviceStatus(config.device_active, config.device_last_seen, config.device_count);
    })
    .catch(err => console.warn('[gateway-config] Could not load config:', err.message));
}

// ──────────────────────────────────────────────────────────────────────────────
// Device Active Gate — controls whether payment gateway is accessible
// ──────────────────────────────────────────────────────────────────────────────
function handleDeviceStatus(deviceActive, lastSeen, deviceCount) {
  const hasReceivingNumbers = Boolean(
    (merchantReceivingNumbers && Object.values(merchantReceivingNumbers).some(v => v && String(v).trim().length > 0)) ||
    (merchantDefaultNumber && merchantDefaultNumber !== "017XXXXXXXX")
  );

  if (deviceActive === false) {
    if (hasReceivingNumbers) {
      // Merchant has configured receiving numbers: do NOT block payment with full curtain!
      // Only display the non-blocking inline warning banner
      hideMerchantOfflineView();
      const banner = document.getElementById("merchant-offline-banner");
      if (banner) banner.classList.remove("hidden");
    } else {
      // Only show full blocking overlay if merchant has NO receiving numbers to pay to
      showMerchantOfflineView(lastSeen);
      const banner = document.getElementById("merchant-offline-banner");
      if (banner) banner.classList.remove("hidden");
    }

    const contactBtn  = document.getElementById("merchant-contact-btn");
    const offlineContactBtn = document.getElementById("offline-contact-btn");
    if (contactBtn) {
      contactBtn.href = `tel:${merchantDefaultNumber}`;
      contactBtn.innerText = `Call Merchant (${merchantDefaultNumber})`;
    }
    if (offlineContactBtn) {
      offlineContactBtn.href = `tel:${merchantDefaultNumber}`;
    }
  } else {
    // Device is active or unknown — hide offline overlay and banner
    hideMerchantOfflineView();
    const banner = document.getElementById("merchant-offline-banner");
    if (banner) banner.classList.add("hidden");
  }
}

function showMerchantOfflineView(lastSeen) {
  const offlineView = document.getElementById("merchant-offline-view");
  if (!offlineView) return;
  offlineView.classList.remove("hidden");

  // Set last seen text
  const lastSeenEl = document.getElementById("offline-last-seen-text");
  if (lastSeenEl && lastSeen) {
    const dt = new Date(lastSeen);
    lastSeenEl.innerText = `Last active: ${dt.toLocaleString()}`;
  }

  // Mirror merchant name/logo
  const offlineName = document.getElementById("offline-merchant-name");
  const offlineAvatar = document.getElementById("offline-avatar");
  const currentName = document.getElementById("merchant-name");
  if (offlineName && currentName) {
    offlineName.innerText = currentName.innerText.replace(/✔/g, '').trim();
  }
  if (offlineAvatar && merchantLogoUrl) {
    setAvatarWithLogo(offlineAvatar, merchantLogoUrl, 32);
  }

  // Pre-fill customer email for offline device alert if available
  const emailInput = document.getElementById("device-alert-customer-email");
  if (emailInput && !emailInput.value) {
    const qParams = new URLSearchParams(window.location.search);
    const qEmail = qParams.get("cus_email") || qParams.get("customer_email") || (window.orderRecord && window.orderRecord.cus_email) || "";
    if (qEmail) emailInput.value = qEmail;
  }
}

function hideMerchantOfflineView() {
  const offlineView = document.getElementById("merchant-offline-view");
  if (offlineView) offlineView.classList.add("hidden");
}

// ── Retry device status check ──
window.retryDeviceCheck = function() {
  if (!merchantId && !backendUrl) return;

  const spinner = document.getElementById("retry-spinner");
  const btnText = document.getElementById("retry-btn-text");
  if (spinner) spinner.classList.remove("hidden");
  if (btnText) btnText.innerText = "Checking...";

  const qMid = merchantId || (new URLSearchParams(window.location.search).get("merchant_id"));
  if (!qMid) {
    if (spinner) spinner.classList.add("hidden");
    if (btnText) btnText.innerText = "🔄 Retry Connection";
    return;
  }

  fetch(`${backendUrl}/v1/payment/device-status?merchant_id=${encodeURIComponent(qMid)}`, {
    signal: AbortSignal.timeout(5000)
  })
    .then(r => r.json())
    .then(data => {
      if (spinner) spinner.classList.add("hidden");
      if (btnText) btnText.innerText = "🔄 Retry Connection";
      handleDeviceStatus(data.device_active, data.device_last_seen, data.device_count);
    })
    .catch(() => {
      if (spinner) spinner.classList.add("hidden");
      if (btnText) btnText.innerText = "🔄 Retry Connection";
      alert("Could not reach the server. Please check your connection.");
    });
};

// ── Proceed despite offline (manual verification path) ──
window.proceedDespiteOffline = function() {
  hideMerchantOfflineView();
  goToStep(1);
};

// ── Subscribe customer to receive email alert when merchant comes online ──
window.subscribeForDeviceOnlineAlert = function() {
  const emailInput = document.getElementById("device-alert-customer-email");
  const errorEl = document.getElementById("device-alert-error");
  const subBox = document.getElementById("device-alert-subscribe-box");
  const successBox = document.getElementById("device-alert-success-box");
  const spinner = document.getElementById("alert-sub-spinner");
  const btnText = document.getElementById("alert-sub-btn-text");

  if (errorEl) errorEl.classList.add("hidden");
  const email = (emailInput ? emailInput.value : "").trim();
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    if (errorEl) {
      errorEl.innerText = "Please enter a valid email address.";
      errorEl.classList.remove("hidden");
    }
    return;
  }

  const qMid = merchantId || (new URLSearchParams(window.location.search).get("merchant_id"));
  const qOrderId = orderId || (new URLSearchParams(window.location.search).get("order_id"));
  const qAmount = payableAmount || (new URLSearchParams(window.location.search).get("amount"));

  if (!qMid) {
    if (errorEl) {
      errorEl.innerText = "Merchant identifier missing.";
      errorEl.classList.remove("hidden");
    }
    return;
  }

  if (spinner) spinner.classList.remove("hidden");
  if (btnText) btnText.innerText = "Subscribing...";

  const payload = {
    merchant_id: qMid,
    customer_email: email,
    order_id: qOrderId !== "demo_order_id" ? qOrderId : null,
    amount: qAmount ? Number(qAmount) : null,
    checkout_url: window.location.href,
  };

  fetch(`${backendUrl}/v1/payment/subscribe-device-alert`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  })
    .then(r => r.json())
    .then(res => {
      if (spinner) spinner.classList.add("hidden");
      if (btnText) btnText.innerText = "🔔 Notify Me";
      if (res.ok) {
        if (subBox) subBox.classList.add("hidden");
        if (successBox) successBox.classList.remove("hidden");
      } else {
        if (errorEl) {
          errorEl.innerText = res.error || "Subscription failed. Please try again.";
          errorEl.classList.remove("hidden");
        }
      }
    })
    .catch(err => {
      if (spinner) spinner.classList.add("hidden");
      if (btnText) btnText.innerText = "🔔 Notify Me";
      if (errorEl) {
        errorEl.innerText = "Network error. Please try again.";
        errorEl.classList.remove("hidden");
      }
    });
};

// ──────────────────────────────────────────────────────────────────────────────
// Merchant Logo Display
// ──────────────────────────────────────────────────────────────────────────────
function setMerchantLogo(logoUrl) {
  merchantLogoUrl = logoUrl;

  // List of all avatar element IDs + their sizes
  const avatarConfigs = [
    { id: 'avatar',          size: 40 },
    { id: 'success-avatar',  size: 32 },
    { id: 'cancel-avatar',   size: 32 },
    { id: 'cancelled-avatar',size: 32 },
    { id: 'proc-avatar',     size: 32 },
    { id: 'offline-avatar',  size: 32 },
  ];

  avatarConfigs.forEach(({ id, size }) => {
    const el = document.getElementById(id);
    if (!el) return;
    setAvatarWithLogo(el, logoUrl, size);
  });
}

function setAvatarWithLogo(el, logoUrl, size) {
  const sizeStr = `${size}px`;
  el.innerHTML = `<img src="${logoUrl}" alt="Merchant Logo"
    style="width:${sizeStr};height:${sizeStr};border-radius:8px;object-fit:cover;"
    onerror="this.parentNode.innerHTML=this.parentNode.getAttribute('data-initial')||'M'">`;
}

// ──────────────────────────────────────────────────────────────────────────────
// Socket.io — production connection with heartbeat + race-condition guard
// ──────────────────────────────────────────────────────────────────────────────
function connectSwapnoPaySocket(url, orderIdParam) {
  if (typeof io === 'undefined') {
    console.warn('[socket.io] Socket.io client not loaded');
    return;
  }
  try {
    socketClient = io(url, {
      transports: ['websocket', 'polling'],
      reconnection: true,
      reconnectionDelay: 1000,        // fast reconnect
      reconnectionDelayMax: 5000,
      reconnectionAttempts: 20,
      timeout: 10000,
    });

    socketClient.on('connect', () => {
      console.log('[socket.io] Connected to SwapnoPay backend:', socketClient.id);

      // Join order room immediately
      socketClient.emit('join_order', { order_id: orderIdParam });

      // Start 15-second heartbeat to keep connection alive and detect fast disconnect
      if (socketHeartbeatInterval) clearInterval(socketHeartbeatInterval);
      socketHeartbeatInterval = setInterval(() => {
        if (socketClient && socketClient.connected) {
          socketClient.emit('ping_backend');
        }
      }, 15000);
    });

    socketClient.on('room_joined', ({ room }) => {
      console.log('[socket.io] Joined room:', room);
    });

    socketClient.on('pong_backend', () => {
      // Heartbeat acknowledged — connection is alive
    });

    // ── Payment status event — emitted by backend after /v1/payment/verify ──
    socketClient.on('payment_status', (data) => {
      console.log('[socket.io] payment_status received:', data);
      if (paymentResolved) return;  // race-condition guard

      const { status, redirect_url } = data;

      if (status === 'PAID') {
        paymentResolved = true;
        showSuccessScreen(data);
        // Subscription orders receive their app callback in the checkout URL.
        // The normal payment verifier may not have a merchant-store order from
        // which to reconstruct redirect_url, so preserve that supplied callback.
        const target = redirect_url || successUrl;
        if (target && target !== '/') {
          setTimeout(() => { window.location.href = target; }, 4000);
        }
      } else if (status === 'FAILED') {
        paymentResolved = true;
        clearInterval(timerInterval);
        clearInterval(procInterval);
        const target = redirect_url || failUrl;
        if (target && target !== '/') {
          setTimeout(() => { window.location.href = target; }, 2000);
        }
      } else if (status === 'CANCELLED') {
        paymentResolved = true;
        goToCancelledScreen();
        const target = redirect_url || cancelUrl;
        if (target && target !== '/') {
          setTimeout(() => { window.location.href = target; }, 2000);
        }
      }
    });

    // ── Backend acknowledged payment_pending — show active matching indicator ──
    socketClient.on('payment_pending', (data) => {
      console.log('[socket.io] Backend acknowledged payment_pending:', data);
      // Show subtle "matching in progress" indicator in processing overlay
      const waitText = document.getElementById("proc-wait-text");
      if (waitText) {
        waitText.innerText = currentLang === 'en'
          ? '✅ Backend received your payment. Matching against SMS records...'
          : '✅ ব্যাকএন্ড পেমেন্ট পেয়েছে। SMS রেকর্ডের সাথে মিলানো হচ্ছে...';
        waitText.style.color = '#10b981';
      }
    });

    socketClient.on('disconnect', (reason) => {
      console.log('[socket.io] Disconnected:', reason);
      if (socketHeartbeatInterval) clearInterval(socketHeartbeatInterval);
      startStatusPolling();
    });

    socketClient.on('connect_error', (err) => {
      console.warn('[socket.io] Connection error:', err.message);
      startStatusPolling();
    });

  } catch (err) {
    console.warn('[socket.io] Setup failed:', err.message);
    startStatusPolling();
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// HTTP Status Polling Fallback (ensures completion even behind proxies/firewalls)
// ──────────────────────────────────────────────────────────────────────────────
let pollingInterval = null;

function startStatusPolling() {
  if (pollingInterval || paymentResolved) return;
  if (!backendUrl || !orderId || orderId === "demo_order_id") return;

  console.log('[polling] Starting HTTP status polling fallback every 4s...');
  pollingInterval = setInterval(() => {
    if (paymentResolved) {
      clearInterval(pollingInterval);
      pollingInterval = null;
      return;
    }

    const qMid = merchantId ? `&merchant_id=${encodeURIComponent(merchantId)}` : '';
    fetch(`${backendUrl}/v1/payment/status?order_id=${encodeURIComponent(orderId)}${qMid}`, {
      signal: AbortSignal.timeout(3500)
    })
      .then(r => r.json())
      .then(data => {
        if (!data || !data.ok || paymentResolved) return;
        if (data.status === 'PAID') {
          paymentResolved = true;
          clearInterval(pollingInterval);
          pollingInterval = null;
          showSuccessScreen(data.order || data);
          const target = data.redirect_url || successUrl;
          if (target && target !== '/') {
            setTimeout(() => { window.location.href = target; }, 3000);
          }
        } else if (data.status === 'CANCELLED') {
          paymentResolved = true;
          clearInterval(pollingInterval);
          pollingInterval = null;
          goToCancelledScreen();
        }
      })
      .catch(() => {
        // Polling network glitch, will retry next tick
      });
  }, 4000);
}

// ──────────────────────────────────────────────────────────────────────────────
// Supabase Realtime WebSocket — Fallback / dual-channel
// ──────────────────────────────────────────────────────────────────────────────
function connectSupabaseRealtime(url, key, id) {
  try {
    const cleanUrl = url.replace("https://", "").replace("http://", "");
    const wsUrl = `wss://${cleanUrl}/realtime/v1/websocket?apikey=${key}&vsn=1.0.0`;

    webSocket = new WebSocket(wsUrl);
    let pingInterval = null;

    webSocket.onopen = () => {
      // Join Phoenix channel for postgres_changes
      webSocket.send(JSON.stringify({ topic: "phoenix", event: "phx_join", payload: {}, ref: "1" }));

      webSocket.send(JSON.stringify({
        topic: "realtime:public",
        event: "phx_join",
        payload: {
          config: {
            postgres_changes: [
              { event: "UPDATE", schema: "public", table: "orders", filter: `id=eq.${id}` }
            ]
          }
        },
        ref: "2"
      }));

      // Phoenix heartbeat — keep WS alive
      pingInterval = setInterval(() => {
        if (webSocket.readyState === WebSocket.OPEN) {
          webSocket.send(JSON.stringify({ topic: "phoenix", event: "heartbeat", payload: {}, ref: "hb" }));
        }
      }, 20000);
    };

    webSocket.onmessage = (event) => {
      try {
        const msg = JSON.parse(event.data);
        if (msg.event === "postgres_changes" && msg.payload?.data?.record) {
          const orderRecord = msg.payload.data.record;
          if (orderRecord.status === "PAID" && !paymentResolved) {
            paymentResolved = true; // race-condition guard
            showSuccessScreen(orderRecord);
          } else if (orderRecord.status === "CANCELLED" && !paymentResolved) {
            paymentResolved = true;
            goToCancelledScreen();
          }
        }
      } catch (e) {
        // ignore parse errors
      }
    };

    webSocket.onclose = () => {
      if (pingInterval) clearInterval(pingInterval);
      // Reconnect after 5s if payment not resolved
      if (!paymentResolved) {
        setTimeout(() => connectSupabaseRealtime(url, key, id), 5000);
      }
    };

    webSocket.onerror = (err) => {
      console.warn('[supabase-realtime] WebSocket error:', err);
    };

  } catch (err) {
    console.error("[supabase-realtime] Setup error:", err);
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// UI Helpers
// ──────────────────────────────────────────────────────────────────────────────
function buildCallbackPayload() {
  const amountValue = Number((payableAmount || "1500").replace(/[^0-9.]/g, '')) || 1500;
  const orderValue = orderId && orderId !== 'demo_order_id' ? orderId : 'ORD-7845';
  const userPhone = document.getElementById('customer-phone')?.value?.trim() || '01712345678';
  const signature = `t=${Date.now()},v1=${[merchantId || 'merchant_01', orderValue, amountValue.toFixed(2), selectedMethod].join(':')}`;

  return {
    event: 'payment.success',
    merchant_id: merchantId || '00000000-0000-0000-0000-000000000001',
    order_id: orderValue,
    tran_id: orderValue,
    amount: Number(amountValue.toFixed(2)),
    currency: 'BDT',
    payment_method: selectedMethod,
    customer_phone: userPhone,
    status: 'PAID',
    gateway: selectedMethod,
    trx_id: `TXN${Math.floor(Math.random() * 900000 + 100000)}`,
    timestamp: new Date().toISOString(),
    ip: '203.0.113.42',
    signature: signature,
    callback_url: window.location.origin + '/api/webhook',
    source: 'swapnopay_widget'
  };
}

function showCallbackPayloadPreview() {
  const modal = document.getElementById('callback-preview-modal');
  const payloadEl = document.getElementById('callback-payload-content');
  const eventLabel = document.getElementById('callback-event-label');
  if (!modal || !payloadEl) return;

  const payload = buildCallbackPayload();
  if (eventLabel) eventLabel.innerText = payload.event;
  payloadEl.innerText = JSON.stringify(payload, null, 2);
  modal.classList.remove('hidden');
}

function closeCallbackPayloadPreview() {
  const modal = document.getElementById('callback-preview-modal');
  if (modal) modal.classList.add('hidden');
}

function copyCallbackPayload() {
  const payloadEl = document.getElementById('callback-payload-content');
  if (!payloadEl) return;
  navigator.clipboard.writeText(payloadEl.innerText)
    .then(() => alert('Callback payload copied to clipboard.'))
    .catch(() => alert('Unable to copy payload automatically.'));
}

function setAmountDisplay(amount) {
  payableAmount = String(amount).replace(/,/g, '');
  document.getElementById("amount-text").innerText = `৳${amount}`;
  document.getElementById("amount-text-input").value = `৳${amount}`;
  document.getElementById("receipt-amount").innerText = `৳${amount}`;
}

function setMerchantNameDisplay(name) {
  const checkSvg = `<svg class="w-4 h-4 text-blue-500 inline-block" fill="currentColor" viewBox="0 0 20 20"><path clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" fill-rule="evenodd"></path></svg>`;

  const avatarIds = ['avatar', 'success-avatar', 'cancel-avatar', 'cancelled-avatar', 'proc-avatar'];

  document.getElementById("merchant-name").innerHTML = `${name} ${checkSvg}`;
  document.getElementById("success-merchant-name").innerHTML = `${name} ${checkSvg}`;
  document.getElementById("cancel-merchant-name").innerHTML = `${name} ${checkSvg}`;
  document.getElementById("cancelled-merchant-name").innerHTML = `${name} ${checkSvg}`;
  document.getElementById("proc-merchant-name").innerHTML = `${name} ${checkSvg}`;

  // Set initial letter on avatar elements that have no logo yet
  if (!merchantLogoUrl) {
    const initial = name.charAt(0).toUpperCase();
    avatarIds.forEach(id => {
      const el = document.getElementById(id);
      if (el && !el.querySelector('img')) el.innerText = initial;
    });
  }
}

function selectMFS(method, color) {
  selectedMethod = method;
  selectedColor  = color;

  const mfsMethods = ["bKash", "Nagad", "Rocket", "Upay"];
  mfsMethods.forEach(m => {
    const card = document.getElementById(`opt-${m.toLowerCase()}`);
    if (card) {
      card.className = "border border-gray-100 rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer shadow-sm mfs-option";
    }
  });

  const borderMap = { bKash: 'border-pink-500 bg-pink-50/10', Nagad: 'border-orange-500 bg-orange-50/10', Rocket: 'border-purple-500 bg-purple-50/10', Upay: 'border-teal-500 bg-teal-50/10' };
  const activeCard = document.getElementById(`opt-${method.toLowerCase()}`);
  if (activeCard && borderMap[method]) {
    activeCard.className = `border ${borderMap[method]} rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer shadow-sm mfs-option`;
  }

  updateLabelsForMfs(method);

  const logoImg = document.getElementById("mfs-selected-logo");
  if (logoImg) {
    const logoSrc = { bKash: "BKash-Icon2-Logo.wine.svg", Nagad: "Nagad-Logo.wine.svg", Rocket: "Rocket.png", Upay: "upay-seeklogo.png" };
    logoImg.src = logoSrc[method] || "";
  }

  if (merchantReceivingNumbers && merchantReceivingNumbers[method]) {
    document.getElementById("merchant-num-display").value = merchantReceivingNumbers[method];
  }

  const num = document.getElementById("merchant-num-display").value;
  updateQrCode(num);
  updateLabelsForMfs(method);
}

function updateLabelsForMfs(method) {
  const nameEl = document.getElementById("mfs-selected-name");
  if (nameEl) nameEl.innerText = method;

  const phoneLabel   = document.getElementById("phone-label");
  const numLabel     = document.getElementById("merchant-num-label");
  const qrHint       = document.getElementById("qr-hint-text");
  const instructions = document.getElementById("instructions-list");
  const qrCard       = document.getElementById("qr-code-card");
  const instructionsHeader = document.querySelector('[data-translate="scanPay"]');

  const accType = (merchantAccountTypes && merchantAccountTypes[method]) ? String(merchantAccountTypes[method]).trim().toLowerCase() : "merchant";
  const isPersonal = (accType === "personal");
  const currentReceiverNum = (merchantReceivingNumbers && merchantReceivingNumbers[method]) ? merchantReceivingNumbers[method] : (document.getElementById("merchant-num-display")?.value || "");

  if (qrCard) {
    qrCard.style.display = isPersonal ? "none" : "";
  }

  if (currentLang === "en") {
    if (instructionsHeader) instructionsHeader.innerText = isPersonal ? "Send Money Instructions" : "Scan & Pay Instructions";
    if (phoneLabel) phoneLabel.innerText = `Payment Number (Your ${method} Number) *`;
    if (numLabel)   numLabel.innerText   = `${method} Merchant Number`;
    if (numLabel)   numLabel.innerText   = isPersonal ? `${method} Personal Number (Send Money)` : `${method} Merchant Number (Payment)`;
    if (qrHint)     qrHint.innerText     = `Scan this QR code with your ${method} app to pay instantly.`;
    if (instructions) instructions.innerHTML = `
      <li>1. Open your ${method} App</li>
      <li>2. Go to Send Money or Scan</li>
      <li>3. Enter the Merchant Wallet Number</li>
      <li>4. Input the exact Amount and confirm</li>
    `;
    if (instructions) {
      if (isPersonal) {
        instructions.innerHTML = `
          <li>1. Open your ${method} App</li>
          <li>2. Select <strong>Send Money</strong> (সেন্ড মানি)</li>
          <li>3. Enter Personal Number: <strong>${currentReceiverNum}</strong></li>
          <li>4. Input exact Amount and confirm transfer</li>
        `;
      } else {
        instructions.innerHTML = `
          <li>1. Open your ${method} App</li>
          <li>2. Scan QR or go to <strong>Payment</strong></li>
          <li>3. Enter Merchant Number: <strong>${currentReceiverNum}</strong></li>
          <li>4. Input exact Amount and confirm</li>
        `;
      }
    }
  } else {
    if (instructionsHeader) instructionsHeader.innerText = isPersonal ? "সেন্ড মানি করার নির্দেশিকা" : "পেমেন্ট নির্দেশিকা";
    if (phoneLabel) phoneLabel.innerText = `পেমেন্ট মোবাইল নম্বর (আপনার ${method} নম্বর) *`;
    if (numLabel)   numLabel.innerText   = `${method} মার্চেন্ট নম্বর`;
    if (numLabel)   numLabel.innerText   = isPersonal ? `${method} ব্যক্তিগত নম্বর (সেন্ড মানি)` : `${method} মার্চেন্ট নম্বর (পেমেন্ট)`;
    if (qrHint)     qrHint.innerText     = `তাত্ক্ষণিকভাবে অর্থ প্রদানের জন্য আপনার ${method} অ্যাপ দিয়ে এই QR কোডটি স্ক্যান করুন।`;
    if (instructions) instructions.innerHTML = `
      <li>১. আপনার ${method} অ্যাপ খুলুন</li>
      <li>২. 'সেন্ড মানি' বা 'স্ক্যান' অপশনে যান</li>
      <li>৩. মার্চেন্ট ওয়ালেট নম্বরটি লিখুন</li>
      <li>৪. সঠিক পরিমাণ লিখে পেমেন্ট নিশ্চিত করুন</li>
    `;
    if (instructions) {
      if (isPersonal) {
        instructions.innerHTML = `
          <li>১. আপনার ${method} অ্যাপ ওপেন করুন</li>
          <li>২. <strong>'সেন্ড মানি'</strong> অপশনে যান</li>
          <li>৩. ব্যক্তিগত নম্বরটি লিখুন: <strong>${currentReceiverNum}</strong></li>
          <li>৪. সঠিক টাকার পরিমাণ লিখে সেন্ড মানি করুন</li>
        `;
      } else {
        instructions.innerHTML = `
          <li>১. আপনার ${method} অ্যাপ ওপেন করুন</li>
          <li>২. কিউআর স্ক্যান করুন বা <strong>'পেমেন্ট'</strong> অপশনে যান</li>
          <li>৩. মার্চেন্ট নম্বরটি লিখুন: <strong>${currentReceiverNum}</strong></li>
          <li>৪. সঠিক টাকার পরিমাণ লিখে পেমেন্ট নিশ্চিত করুন</li>
        `;
      }
    }
  }
}

function updateQrCode(number) {
  const qrImage = document.getElementById("qr-image");
  if (qrImage) {
    qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=${encodeURIComponent(selectedMethod.toLowerCase() + "://pay?num=" + number)}`;
  }
}

function copyNumber() {
  const num = document.getElementById("merchant-num-display").value;
  const isPersonal = (merchantAccountTypes[selectedMethod] || '').toLowerCase() === 'personal';
  navigator.clipboard.writeText(num).then(() => {
    alert(currentLang === "en" ? "Merchant number copied!" : "মার্চেন্ট নম্বরটি কপি করা হয়েছে!");
    alert(currentLang === "en" ? (isPersonal ? "Personal number copied!" : "Merchant number copied!") : (isPersonal ? "ব্যক্তিগত নম্বরটি কপি করা হয়েছে!" : "মার্চেন্ট নম্বরটি কপি করা হয়েছে!"));
  });
}

function copyAmount() {
  const amtVal = document.getElementById("amount-text-input").value.replace("৳", "").trim();
  navigator.clipboard.writeText(amtVal).then(() => {
    alert(currentLang === "en" ? "Amount copied!" : "পরিমাণটি কপি করা হয়েছে!");
  });
}

function updateFlowStage(step) {
  const stages = Array.from(document.querySelectorAll('#flow-stage-strip .flow-stage-pill'));
  stages.forEach((item, idx) => {
    item.classList.remove('active', 'complete');
    if (idx < step) item.classList.add('complete');
    if (idx === step - 1) item.classList.add('active');
  });
}

function goToStep(step) {
  if (step === 2) {
    const phoneInput = document.getElementById("customer-phone").value.trim();
    if (!phoneInput || phoneInput.length < 10) {
      document.getElementById("phone-error-msg").classList.remove("hidden");
      return;
    } else {
      document.getElementById("phone-error-msg").classList.add("hidden");
    }
  }

  document.getElementById("view1").classList.add("hidden");
  document.getElementById("view2").classList.add("hidden");
  document.getElementById("view3").classList.add("hidden");
  document.getElementById(`view${step}`).classList.remove("hidden");

  const stepText = translations[currentLang].stepOf + ` ${step} ` + translations[currentLang].of + " 3";
  document.getElementById("step-title").innerText = stepText;

  ["dot1", "dot2", "dot3"].forEach((dot, idx) => {
    const el = document.getElementById(dot);
    el.className = idx < step
      ? "flex-grow flex-1 bg-blue-600 rounded-full transition-all duration-300"
      : "flex-grow flex-1 bg-gray-200 rounded-full transition-all duration-300";
  });

  if (step === 1) updateFlowStage(1);
  if (step === 2) {
    updateFlowStage(2);
    startTimer();
  }
  if (step === 3) {
    updateFlowStage(3);
    clearInterval(timerInterval);
  }
}

function startTimer() {
  if (timerInterval) clearInterval(timerInterval);

  const minEl  = document.getElementById("min");
  const secEl  = document.getElementById("sec");
  const minMob = document.getElementById("min-mobile");
  const secMob = document.getElementById("sec-mobile");

  timerInterval = setInterval(() => {
    countdownSeconds--;
    if (countdownSeconds <= 0) {
      clearInterval(timerInterval);
      [minEl, secEl, minMob, secMob].forEach(el => { if (el) el.innerText = "00"; });
      const btn = document.getElementById("complete-payment-btn");
      if (btn) btn.disabled = true;
      alert(currentLang === "en"
        ? "Verification window expired. Please submit an appeal."
        : "লেনদেন যাচাইয়ের সময় শেষ হয়েছে। অনুগ্রহ করে একটি আপিল জমা দিন।");
      goToStep(3);
      return;
    }
    const minutes = Math.floor(countdownSeconds / 60);
    const seconds = countdownSeconds % 60;
    const m = minutes.toString().padStart(2, '0');
    const s = seconds.toString().padStart(2, '0');
    if (minEl) minEl.innerText = m;
    if (secEl) secEl.innerText = s;
    if (minMob) minMob.innerText = m;
    if (secMob) secMob.innerText = s;
  }, 1000);
}

function updateProcessingScreenText() {
  const skipBtn  = document.getElementById("btn-skip-processing");
  const waitText = document.getElementById("proc-wait-text");
  if (!skipBtn || !waitText) return;
  const isLocked = skipBtn.disabled;
  if (currentLang === "en") {
    waitText.innerText  = isLocked ? "Please wait while we verify your transaction status." : "Automatic verification taking too long? You can skip to manual appeal now.";
    skipBtn.innerText   = isLocked ? "Verify Manually via Appeal (Locked)" : "Verify Manually via Appeal";
  } else {
    waitText.innerText  = isLocked ? "আপনার লেনদেনের স্ট্যাটাস যাচাই করার সময় অনুগ্রহ করে অপেক্ষা করুন।" : "স্বয়ংক্রিয় যাচাইয়ে অতিরিক্ত সময় লাগছে? আপনি এখন ম্যানুয়ালি আপিল করতে পারেন।";
    skipBtn.innerText   = isLocked ? "ম্যানুয়ালি আপিল করুন (লকড)" : "ম্যানুয়ালি আপিল জমা দিন";
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// "I Have Completed Payment" button
// Notifies backend (which forwards to merchant Android app + starts matching)
// ──────────────────────────────────────────────────────────────────────────────
function handleTransferred() {
  // Show processing overlay
  document.getElementById("processing-view").classList.remove("hidden");
  paymentResolved = false; // reset guard for this payment attempt
  updateFlowStage(3);

  procSeconds = 300;
  const timerDisplay = document.getElementById("proc-countdown-timer");
  const skipBtn      = document.getElementById("btn-skip-processing");

  skipBtn.disabled = true;
  skipBtn.className = "w-full py-2.5 bg-gray-100 text-gray-400 border border-gray-200 rounded-xl font-bold text-[11px] transition-colors cursor-not-allowed";
  updateProcessingScreenText();
  if (timerDisplay) timerDisplay.innerText = "05:00";

  // ── Notify backend: customer has paid ──
  startStatusPolling();
  const step2Trx = (document.getElementById("step2-trx-input")?.value || "").trim().toUpperCase();
  if (step2Trx) {
    const appealTrx = document.getElementById("appeal-trx");
    if (appealTrx) appealTrx.value = step2Trx;
  }

  if (backendUrl && orderId !== "demo_order_id") {
    fetch(`${backendUrl}/v1/payment/notify`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        order_id:        orderId,
        merchant_id:     merchantId || null,
        payment_method:  selectedMethod,
        trx_id:          step2Trx || null,
        customer_phone:  document.getElementById("customer-phone")?.value?.trim() || null,
      })
    })
      .then(r => r.json())
      .then(data => {
        console.log('[notify] Backend acknowledged:', data.message);
        if (data.status === 'PAID') {
          paymentResolved = true;
          if (pollingInterval) clearInterval(pollingInterval);
          showSuccessScreen(data);
          const target = data.redirect_url || successUrl;
          if (target && target !== '/') {
            setTimeout(() => { window.location.href = target; }, 3000);
          }
        }
      })
      .catch(err => console.warn('[notify] Backend notify failed:', err.message));
  }

  if (procInterval) clearInterval(procInterval);

  procInterval = setInterval(() => {
    procSeconds--;
    if (procSeconds <= 0) {
      clearInterval(procInterval);
      skipProcessingAndAppeal();
      return;
    }

    const mins = Math.floor(procSeconds / 60);
    const secs = procSeconds % 60;
    if (timerDisplay) timerDisplay.innerText = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

    // Enable skip after 3 minutes (120 seconds remaining)
    if (procSeconds <= 120 && skipBtn.disabled) {
      skipBtn.disabled = false;
      skipBtn.className = "w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-[11px] transition-colors cursor-pointer shadow-md";
      updateProcessingScreenText();
    }
  }, 1000);
}

function skipProcessingAndAppeal() {
  if (procInterval) clearInterval(procInterval);
  document.getElementById("processing-view").classList.add("hidden");
  goToStep(3);
}

function showSuccessScreen(orderRecord) {
  clearInterval(timerInterval);
  clearInterval(procInterval);
  if (pollingInterval) { clearInterval(pollingInterval); pollingInterval = null; }
  updateFlowStage(4);

  document.getElementById("processing-view").classList.add("hidden");
  document.getElementById("cancel-view").classList.add("hidden");

  const successView = document.getElementById("success-view");
  if (!successView) return;

  if (orderRecord) {
    document.getElementById("receipt-id").innerText      = orderRecord.tran_id || orderRecord.order_id || orderRecord.id || orderId;
    document.getElementById("receipt-trx").innerText     = orderRecord.trx_id || orderRecord.matched_trx_id || "MFS Transfer Direct";
    document.getElementById("receipt-gateway").innerText = `${selectedMethod} Mobile Wallet`;
    document.getElementById("receipt-date").innerText    = new Date().toLocaleString();
    document.getElementById("receipt-amount").innerText  = "৳" + parseFloat(orderRecord.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
  }

  const payloadEl = document.getElementById('callback-payload-content');
  if (payloadEl) payloadEl.innerText = JSON.stringify(buildCallbackPayload(), null, 2);
  successView.classList.remove("hidden");
}

// ──────────────────────────────────────────────────────────────────────────────
// Cancel flow
// ──────────────────────────────────────────────────────────────────────────────
let summaryExpanded = false;
function toggleSummaryMobile() {
  const panel      = document.getElementById("collapsible-summary");
  const toggleIcon = document.getElementById("summary-toggle-icon");
  summaryExpanded = !summaryExpanded;
  panel.classList.toggle("hidden", !summaryExpanded);
  if (toggleIcon) toggleIcon.innerText = summaryExpanded ? "expand_less" : "expand_more";
}

function showCancelCheck() { document.getElementById("cancel-view").classList.remove("hidden"); }
function hideCancelCheck() { document.getElementById("cancel-view").classList.add("hidden"); }

function cancelOrder() {
  hideCancelCheck();
  if (orderId === "demo_order_id") { goToCancelledScreen(); return; }

  // Update on merchant DB
  if (supabaseUrl && supabaseAnonKey !== "demo_anon_key") {
    fetch(`${supabaseUrl}/rest/v1/orders?id=eq.${orderId}`, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        "apikey": supabaseAnonKey,
        "Authorization": `Bearer ${supabaseAnonKey}`
      },
      body: JSON.stringify({ status: "CANCELLED" })
    }).catch(err => console.error("Error cancelling order:", err));
  }
  goToCancelledScreen();
}

function goToCancelledScreen() {
  if (pollingInterval) { clearInterval(pollingInterval); pollingInterval = null; }
  ["cancel-view", "success-view", "processing-view", "merchant-offline-view"].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.add("hidden");
  });
  document.getElementById("cancelled-view").classList.remove("hidden");
}

// ──────────────────────────────────────────────────────────────────────────────
// Language Switcher
// ──────────────────────────────────────────────────────────────────────────────
function setLanguage(lang) {
  currentLang = lang;

  const btnEn = document.getElementById("lang-en");
  const btnBn = document.getElementById("lang-bn");
  const activeClass   = "text-[10px] px-2 py-0.5 rounded font-black border transition-colors bg-blue-600 text-white border-blue-600";
  const inactiveClass = "text-[10px] px-2 py-0.5 rounded font-black border transition-colors bg-white text-gray-500 border-gray-200";
  if (btnEn) btnEn.className = lang === "en" ? activeClass : inactiveClass;
  if (btnBn) btnBn.className = lang === "bn" ? activeClass : inactiveClass;

  document.querySelectorAll("[data-translate]").forEach(el => {
    const key = el.getAttribute("data-translate");
    if (translations[lang]?.[key]) el.innerText = translations[lang][key];
  });

  const phoneInput = document.getElementById("customer-phone");
  if (phoneInput) phoneInput.placeholder = translations[lang].enter11;
  const appealTrx = document.getElementById("appeal-trx");
  if (appealTrx) appealTrx.placeholder = translations[lang].trxLabel;

  updateLabelsForMfs(selectedMethod);
  updateProcessingScreenText();
}

// ──────────────────────────────────────────────────────────────────────────────
// Appeal submission
// ──────────────────────────────────────────────────────────────────────────────
let uploadedFileName = "";
let uploadedFileData = null;

function triggerFileInput() { document.getElementById("file-input").click(); }

function handleFileSelect(event) {
  const file = event.target.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    const fileErr = document.getElementById("appeal-file-error-msg");
    fileErr.innerText = currentLang === "en" ? "File size exceeds 5MB limit." : "ফাইলের আকার ৫ মেগাবাইটের বেশি।";
    fileErr.classList.remove("hidden");
    return;
  }
  uploadedFileName = file.name;
  const reader = new FileReader();
  reader.onload = function(e) {
    uploadedFileData = e.target.result;
  };
  reader.readAsDataURL(file);

  const nameDisplay = document.getElementById("file-name");
  nameDisplay.innerText = `📎 Selected: ${file.name}`;
  nameDisplay.classList.remove("hidden");
  document.getElementById("appeal-file-error-msg").classList.add("hidden");
}

function submitAppeal() {
  const trxId = document.getElementById("appeal-trx").value.trim();
  const note  = document.getElementById("appeal-note").value.trim();
  let isValid = true;

  if (!trxId) {
    document.getElementById("appeal-trx-error-msg").classList.remove("hidden");
    isValid = false;
  } else {
    document.getElementById("appeal-trx-error-msg").classList.add("hidden");
  }

  if (!uploadedFileName) {
    document.getElementById("appeal-file-error-msg").classList.remove("hidden");
    isValid = false;
  } else {
    document.getElementById("appeal-file-error-msg").classList.add("hidden");
  }

  if (!isValid) return;

  const btnAppeal = document.getElementById("btn-appeal");
  if (btnAppeal) {
    btnAppeal.disabled = true;
    btnAppeal.innerText = currentLang === "en" ? "Submitting Appeal..." : "আপিল জমা দেওয়া হচ্ছে...";
  }

  const targetBackend = backendUrl || (window.location.hostname.includes('swapnopay.top') ? 'https://api.swapnopay.top' : window.location.origin);
  const appealPayload = {
    order_id:       orderId !== "demo_order_id" ? orderId : null,
    merchant_id:    merchantId || null,
    trx_id:         trxId,
    cus_phone:      document.getElementById("customer-phone").value.trim() || "017xxxxxxxx",
    payment_method: selectedMethod,
    note:           note || "Customer Dispute Appeal",
    screenshot_url: uploadedFileData,
  };

  fetch(`${targetBackend}/v1/payment/appeal`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(appealPayload)
  })
    .then(async res => {
      if (btnAppeal) {
        btnAppeal.disabled = false;
        btnAppeal.innerText = translations[currentLang]?.appealSubmit || "Submit Appeal";
      }
      const respData = await res.json().catch(() => ({}));
      if (res.ok && respData.ok) {
        alert(currentLang === "en"
          ? "Dispute appeal submitted successfully! Your merchant has been notified and will review your payment."
          : "আপিল সফলভাবে মার্চেন্টের কাছে পাঠানো হয়েছে! মার্চেন্ট পর্যালোচনা করে আপনার অর্ডারটি অনুমোদন করবেন।");
        goToStep(1);
      } else {
        alert((currentLang === "en" ? "Failed to submit appeal: " : "আপিল জমা দেওয়া ব্যর্থ হয়েছে: ") + (respData.error || "Please verify details."));
      }
    })
    .catch(err => {
      if (btnAppeal) {
        btnAppeal.disabled = false;
        btnAppeal.innerText = translations[currentLang]?.appealSubmit || "Submit Appeal";
      }
      console.error("[appeal] Submission failed:", err);
      alert(currentLang === "en"
        ? "Network error while submitting appeal. Please check your connection and try again."
        : "আপিল জমা দেওয়ার সময় নেটওয়ার্ক ত্রুটি দেখা দিয়েছে। অনুগ্রহ করে আপনার ইন্টারনেট সংযোগ পরীক্ষা করে পুনরায় চেষ্টা করুন।");
    });
}

function closeWidget() {
  const params = new URLSearchParams(window.location.search);
  let target = params.get("success_url") || successUrl || "/";
  const trxEl = document.getElementById("receipt-trx");
  const trxVal = trxEl ? trxEl.innerText.trim() : "";
  if (trxVal && trxVal !== "MFS Transfer Direct" && target.includes("trx_id=")) {
    target = target.replace(/trx_id=[^&]*/, "trx_id=" + encodeURIComponent(trxVal));
  } else if (trxVal && trxVal !== "MFS Transfer Direct" && target !== "/" && !target.includes("trx_id=")) {
    target += (target.includes("?") ? "&" : "?") + "trx_id=" + encodeURIComponent(trxVal);
  }
  window.location.href = target;
}

// ──────────────────────────────────────────────────────────────────────────────
// Help & support popup
// ──────────────────────────────────────────────────────────────────────────────
let supportPopupVisible = false;
function toggleSupportPopup() {
  const popup = document.getElementById("support-popup");
  const icon  = document.getElementById("support-icon-symbol");
  if (!popup || !icon) return;
  supportPopupVisible = !supportPopupVisible;
  popup.classList.toggle("hidden", !supportPopupVisible);
  icon.innerText = supportPopupVisible ? "close" : "support_agent";
}

// ──────────────────────────────────────────────────────────────────────────────
// Live input error clearing
// ──────────────────────────────────────────────────────────────────────────────
window.addEventListener("DOMContentLoaded", () => {
  document.getElementById("customer-phone")?.addEventListener("input", () => {
    document.getElementById("phone-error-msg")?.classList.add("hidden");
  });
  document.getElementById("appeal-trx")?.addEventListener("input", () => {
    document.getElementById("appeal-trx-error-msg")?.classList.add("hidden");
  });
});
