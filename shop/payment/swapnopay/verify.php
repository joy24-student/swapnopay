<?php
ob_start();
session_start();
require_once("../../admin/inc/config.php");
require_once("../../admin/inc/functions.php");

$tran_id = strip_tags($_GET['tran_id'] ?? ($_SESSION['pending_tran_id'] ?? ''));
$gateway_order_id = strip_tags($_SESSION['pending_gateway_order_id'] ?? $tran_id);
$method = strip_tags($_GET['method'] ?? ($_SESSION['pending_method'] ?? 'bKash'));
$allowedMethods = ['bKash', 'Nagad', 'Rocket', 'Upay'];
if (!in_array($method, $allowedMethods, true)) {
    $method = 'bKash';
}
$amount = (float)($_SESSION['pending_amount'] ?? 0);

if (empty($tran_id) || !preg_match('/^[A-Za-z0-9_-]{1,120}$/', $tran_id)) {
    header('location: ../../checkout.php');
    exit;
}
if (!empty($_SESSION['pending_tran_id']) && !hash_equals((string)$_SESSION['pending_tran_id'], $tran_id)) {
    http_response_code(403);
    exit('This payment session does not match the requested order.');
}

// Fetch merchant payment receiving number
$receiving_number = null;
$account_type = 'Personal';

$supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : getenv('SUPABASE_URL');
$supabase_service_key = defined('SUPABASE_SERVICE_KEY') && !empty(SUPABASE_SERVICE_KEY) 
    ? SUPABASE_SERVICE_KEY 
    : (defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : getenv('SUPABASE_ANON_KEY'));
$supabase_anon_key = defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : (getenv('SUPABASE_ANON_KEY') ?: '');
$api_url = defined('SWAPNOPAY_API_URL') && !empty(SWAPNOPAY_API_URL) ? SWAPNOPAY_API_URL : 'https://api.swapnopay.top';
$merchant_id = defined('MERCHANT_ID') && !empty(MERCHANT_ID) ? MERCHANT_ID : ($runtime['merchant_id'] ?? null);

try {
    $stmt_sett = $pdo->query("SELECT swapnopay_merchant_id, swapnopay_api_key, swapnopay_api_url FROM tbl_settings WHERE id=1");
    if ($stmt_sett && $sett_row = $stmt_sett->fetch(PDO::FETCH_ASSOC)) {
        if (empty($merchant_id) && !empty($sett_row['swapnopay_merchant_id'])) {
            $merchant_id = trim($sett_row['swapnopay_merchant_id']);
        }
        if (!empty($sett_row['swapnopay_api_url'])) {
            $api_url = rtrim(trim($sett_row['swapnopay_api_url']), '/');
        }
    }
} catch (Throwable $e) {}

if (!empty($supabase_url) && !empty($supabase_service_key)) {
    $clean_supabase_url = rtrim($supabase_url, '/');
    $numberQuery = 'type=eq.' . rawurlencode($method) . '&active=eq.true&select=number,is_default&limit=1';
    if (!empty($merchant_id)) $numberQuery .= '&merchant_id=eq.' . rawurlencode($merchant_id);
    $ch = curl_init("{$clean_supabase_url}/rest/v1/merchant_numbers?{$numberQuery}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: {$supabase_service_key}",
        "Authorization: Bearer {$supabase_service_key}"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $numbers = json_decode($res, true);
    if (!empty($numbers[0]['number'])) {
        $receiving_number = $numbers[0]['number'];
    }
}

if (empty($receiving_number) && $merchant_id) {
    // Hosted Storefront Mode or Fallback: Query SwapnoPay Central Gateway
    $ch = curl_init("{$api_url}/v1/payment/config?merchant_id=" . urlencode($merchant_id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    curl_close($ch);
    $config_res = json_decode($res, true);
    if (!empty($config_res['receiving_numbers'][$method])) {
        $receiving_number = $config_res['receiving_numbers'][$method];
    } elseif (!empty($config_res['default_number'])) {
        $receiving_number = $config_res['default_number'];
    }
}
$is_number_available = !empty($receiving_number);

// Method branding colors
$branding = [
    'bKash' => ['bg' => '#e2136e', 'icon' => 'bKash', 'textColor' => '#fff'],
    'Nagad' => ['bg' => '#f7941d', 'icon' => 'Nagad', 'textColor' => '#fff'],
    'Rocket' => ['bg' => '#8c3494', 'icon' => 'Rocket', 'textColor' => '#fff'],
    'Upay' => ['bg' => '#ffcb05', 'icon' => 'Upay', 'textColor' => '#000']
];
$brand = $branding[$method] ?? $branding['bKash'];

// Handle Manual TrxID Submission
$trx_message = '';
if (isset($_POST['submit_trx'])) {
    $user_trx = strip_tags($_POST['trx_id'] ?? '');
    $user_sender = strip_tags($_POST['sender_number'] ?? '');
    if (!empty($user_trx)) {
        if (!empty($supabase_url) && !empty($supabase_service_key)) {
            // Update order with customer-provided TrxID for faster matching/appeal
            $patch_payload = json_encode([
                'sender_number' => $user_sender,
                'matched_trx_id' => $user_trx
            ]);
            $orderQuery = 'tran_id=eq.' . rawurlencode($tran_id);
            if (!empty($merchant_id)) $orderQuery .= '&merchant_id=eq.' . rawurlencode($merchant_id);
            $ch = curl_init("{$clean_supabase_url}/rest/v1/orders?{$orderQuery}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $patch_payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: {$supabase_service_key}",
                "Authorization: Bearer {$supabase_service_key}",
                "Content-Type: application/json"
            ]);
            $patch_response = curl_exec($ch);
            $patch_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $patch_error = curl_error($ch);
            curl_close($ch);
            if ($patch_response === false || $patch_code < 200 || $patch_code >= 300) {
                error_log('SwapnoPay order TrxID update failed: ' . ($patch_error ?: 'Merchant database rejected the update'));
                $trx_message = 'We could not save your TrxID. Please try again or contact store support.';
            } else {
                $trx_message = 'TrxID received. The merchant payment system is checking the transaction.';
            }
        } else if ($merchant_id) {
            // Hosted Storefront Mode: Relay to Central Gateway /notify
            $notify_payload = json_encode([
                'merchant_id' => $merchant_id,
                'order_id' => $gateway_order_id,
                'tran_id' => $tran_id,
                'trx_id' => $user_trx,
                'customer_phone' => $user_sender,
                'payment_method' => $method
            ]);
            $ch_n = curl_init("{$api_url}/v1/payment/notify");
            curl_setopt($ch_n, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_n, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch_n, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch_n, CURLOPT_POST, true);
            curl_setopt($ch_n, CURLOPT_POSTFIELDS, $notify_payload);
            curl_setopt($ch_n, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            $notify_response = curl_exec($ch_n);
            $notify_code = (int)curl_getinfo($ch_n, CURLINFO_HTTP_CODE);
            $notify_error = curl_error($ch_n);
            curl_close($ch_n);
            $notify_data = json_decode((string)$notify_response, true);
            if ($notify_response === false || $notify_code < 200 || $notify_code >= 300 || empty($notify_data['ok'])) {
                error_log('SwapnoPay notify failed: ' . ($notify_error ?: ($notify_data['error'] ?? 'Invalid gateway response')));
                $trx_message = 'We could not reach the payment verifier. Your TrxID was not submitted; please try again.';
            } else {
                $trx_message = 'TrxID received. The merchant payment system is checking the transaction.';
            }
        } else {
            $trx_message = 'Payment verification is not configured for this store. Please contact store support.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payment - <?php echo htmlspecialchars($method); ?></title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/font-awesome.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <style>
        body {
            background-color: #f4f6f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .pay-card {
            background: #ffffff;
            max-width: 460px;
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            text-align: center;
        }
        .pay-header {
            background: <?php echo $brand['bg']; ?>;
            color: <?php echo $brand['textColor']; ?>;
            padding: 24px;
        }
        .pay-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .pay-body {
            padding: 24px;
        }
        .amount-box {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .amount-val {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
        }
        .number-box {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .number-val {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: 1px;
        }
        .btn-copy {
            background: <?php echo $brand['bg']; ?>;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 600;
        }
        .instructions {
            text-align: left;
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 20px;
            background: #fafafa;
            padding: 14px;
            border-radius: 8px;
        }
        .instructions ol {
            margin: 0;
            padding-left: 20px;
        }
        .pulsing-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse 1.5s infinite;
            margin-right: 6px;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            background: #ecfdf5;
            color: #065f46;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .form-control-custom {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 14px;
            text-align: center;
        }
        .btn-verify {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="pay-card">
    <div class="pay-header">
        <h2>Pay with <?php echo htmlspecialchars($method); ?></h2>
        <p style="margin: 4px 0 0; opacity: 0.9; font-size: 14px;">Instant SMS Automated Verification</p>
    </div>

    <div class="pay-body">
        <div class="status-badge">
            <span class="pulsing-dot"></span> Waiting for payment SMS detection...
        </div>

        <div class="amount-box">
            <div style="font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 600;">Total Amount</div>
            <div class="amount-val">৳ <?php echo number_format($amount, 2); ?></div>
        </div>

        <?php if (!$is_number_available): ?>
        <div style="background: #fef2f2; border: 1.5px solid #ef4444; border-radius: 10px; padding: 14px; margin-bottom: 20px; color: #991b1b; font-size: 13px; line-height: 1.5;">
            <strong>⚠️ Configuration Alert:</strong> The merchant has not configured a receiving number for <strong><?php echo htmlspecialchars($method); ?></strong>. Please contact store support or use an alternative payment option.
        </div>
        <?php else: ?>
        <div class="number-box">
            <div>
                <div style="font-size: 11px; text-transform: uppercase; color: #64748b;">Send Money To (<?php echo $account_type; ?>)</div>
                <div class="number-val" id="recNumber"><?php echo htmlspecialchars($receiving_number); ?></div>
            </div>
            <button type="button" class="btn-copy" onclick="copyNumber()">Copy</button>
        </div>
        <?php endif; ?>

        <div class="instructions">
            <ol>
                <li>Open your <b><?php echo htmlspecialchars($method); ?></b> app or dial USSD.</li>
                <li>Choose <b>Send Money</b> and enter the number above.</li>
                <li>Enter exact amount <b>৳ <?php echo number_format($amount, 2); ?></b>.</li>
                <li>Use Reference: <b><?php echo substr($tran_id, -6); ?></b></li>
                <li>Once sent, this screen will <b>automatically update</b> via live SMS sync!</li>
            </ol>
        </div>

        <?php if (!empty($trx_message)): ?>
            <div class="alert alert-info" style="font-size: 13px;"><?php echo htmlspecialchars($trx_message); ?></div>
        <?php endif; ?>

        <form method="post" style="border-top: 1px solid #f1f5f9; padding-top: 16px;">
            <p style="font-size: 12px; color: #64748b; margin-bottom: 8px;">Already sent money? Enter TrxID to speed up verification:</p>
            <input type="text" name="sender_number" class="form-control-custom" placeholder="Your Sender Phone (01XXXXXXXXX)" required>
            <input type="text" name="trx_id" class="form-control-custom" placeholder="Transaction ID (e.g. 9A8B7C6D)" required>
            <button type="submit" name="submit_trx" class="btn-verify">Submit Transaction ID</button>
        </form>
    </div>
</div>

<script>
const tranId = "<?php echo $tran_id; ?>";
const amount = "<?php echo $amount; ?>";
const supabaseUrl = "<?php echo htmlspecialchars($supabase_url, ENT_QUOTES, 'UTF-8'); ?>";
const supabaseAnonKey = "<?php echo htmlspecialchars($supabase_anon_key, ENT_QUOTES, 'UTF-8'); ?>";

function copyNumber() {
    const num = document.getElementById('recNumber').innerText;
    navigator.clipboard.writeText(num).then(() => {
        alert("Number copied: " + num);
    });
}

function handlePaymentSuccess() {
    window.location.href = "../../payment_success.php?method=swapnopay&amount=" + amount + "&payment_id=" + encodeURIComponent(tranId);
}

// 1. Supabase Realtime WebSocket Connection
if (supabaseUrl && supabaseAnonKey && typeof supabase !== 'undefined') {
    try {
        const client = supabase.createClient(supabaseUrl, supabaseAnonKey);
        client
            .channel('order-status-' + tranId)
            .on('postgres_changes', {
                event: 'UPDATE',
                schema: 'public',
                table: 'orders',
                filter: 'tran_id=eq.' + tranId
            }, (payload) => {
                if (payload.new && payload.new.status === 'PAID') {
                    handlePaymentSuccess();
                }
            })
            .subscribe((status) => {
                console.log("Supabase Realtime Status:", status);
            });
    } catch(e) {
        console.error("Supabase Realtime error:", e);
    }
}

// 2. Socket.IO Real-time Connection to SwapnoPay Backend
(function() {
    const apiUrl = "<?php echo htmlspecialchars($api_url, ENT_QUOTES, 'UTF-8'); ?>";
    const orderId = "<?php echo htmlspecialchars($gateway_order_id, ENT_QUOTES, 'UTF-8'); ?>";
    if (apiUrl && orderId) {
        try {
            const ioScript = document.createElement('script');
            ioScript.src = apiUrl + '/socket.io/socket.io.js';
            ioScript.onload = function() {
                if (typeof io === 'undefined') return;
                const socket = io(apiUrl, { transports: ['websocket', 'polling'] });
                socket.on('connect', function() {
                    console.log('SwapnoPay Socket.IO connected');
                    socket.emit('join_order', orderId);
                });
                socket.on('payment_status', function(data) {
                    console.log('SwapnoPay payment_status event:', data);
                    if (data && (data.status === 'PAID' || data.status === 'Completed')) {
                        handlePaymentSuccess();
                    }
                });
                socket.on('disconnect', function() {
                    console.log('SwapnoPay Socket.IO disconnected');
                });
            };
            ioScript.onerror = function() {
                console.log('SwapnoPay Socket.IO script load failed, relying on polling');
            };
            document.head.appendChild(ioScript);
        } catch(e) {
            console.error('SwapnoPay Socket.IO init error:', e);
        }
    }
})();

// 3. HTTP Polling Fallback (every 3 seconds)
setInterval(() => {
    fetch("check_status.php?tran_id=" + encodeURIComponent(tranId))
        .then(res => res.json())
        .then(data => {
            if (data && data.status === 'PAID') {
                handlePaymentSuccess();
            }
        })
        .catch(err => console.log("Poll error:", err));
}, 3000);
</script>

</body>
</html>

