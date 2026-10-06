<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once('header.php'); ?>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_top_category");
$statement->execute();
$total_top_category = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_mid_category");
$statement->execute();
$total_mid_category = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_end_category");
$statement->execute();
$total_end_category = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_product");
$statement->execute();
$total_product = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_status='1'");
$statement->execute();
$total_customers = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_subscriber WHERE subs_active='1'");
$statement->execute();
$total_subscriber = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_shipping_cost");
$statement->execute();
$available_shipping = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=?");
$statement->execute(array('Completed'));
$total_order_completed = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE shipping_status=?");
$statement->execute(array('Completed'));
$total_shipping_completed = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=?");
$statement->execute(array('Pending'));
$total_order_pending = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=? AND shipping_status=?");
$statement->execute(array('Completed','Pending'));
$total_order_complete_shipping_pending = $statement->rowCount();

// Notification & FCM Stats
$total_notifications_sent = 0;
$total_fcm_devices = 0;
$recent_broadcasts = [];
try {
    $total_notifications_sent = (int)$pdo->query("SELECT COUNT(*) FROM tbl_notifications")->fetchColumn();
    $total_fcm_devices = (int)$pdo->query("SELECT COUNT(*) FROM tbl_fcm_tokens")->fetchColumn();
    $recent_broadcasts = $pdo->query("SELECT * FROM tbl_notifications ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>

<?php
// Store Currency Symbol
$currency_symbol = (defined('LANG_VALUE_1') && !empty(LANG_VALUE_1) && LANG_VALUE_1 !== '$') ? LANG_VALUE_1 : '৳';

// Real KPI Metrics from Database
$dash_revenue = 0.0;
$dash_total_orders = 0;
$dash_pending_orders = 0;
$dash_online_pm_pct = 0.0;
$dash_cod_pm_pct = 0.0;
$cod_count = 0;
$online_count = 0;

// Date boundaries for real week-over-week trends & chart
$sevenDaysAgo = date('Y-m-d 00:00:00', strtotime('-6 days'));
$fourteenDaysAgo = date('Y-m-d 00:00:00', strtotime('-13 days'));
$sevenDaysEnd = date('Y-m-d 23:59:59', strtotime('-7 days'));

$sales_growth = 0.0;
$orders_growth = 0.0;
$cust_growth = 0.0;
$pending_growth = 0.0;

try {
    // 1. Total Completed Revenue
    $dash_revenue = (float)$pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM tbl_payment WHERE payment_status = 'Completed'")->fetchColumn();
    
    // 2. Total Orders
    $dash_total_orders = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment")->fetchColumn();
    
    // 3. Total Pending / In-Fulfillment Orders
    $dash_pending_orders = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE (shipping_status = 'Pending' OR payment_status = 'Pending') AND payment_status != 'Cancelled'")->fetchColumn();
    
    // 4. Real Payment Breakdown
    $total_pm = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE payment_method IS NOT NULL AND payment_method != ''")->fetchColumn();
    if ($total_pm > 0) {
        $cod_count = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE payment_method IN ('COD', 'Cash on Delivery', 'Cash')")->fetchColumn();
        $online_count = max(0, $total_pm - $cod_count);
        $dash_cod_pm_pct = round(($cod_count / $total_pm) * 100, 1);
        $dash_online_pm_pct = round(100 - $dash_cod_pm_pct, 1);
    }

    // 5. Week-over-Week Calculations
    // Sales: Last 7 Days vs Previous 7 Days
    $s7Stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM tbl_payment WHERE payment_status = 'Completed' AND payment_date >= ?");
    $s7Stmt->execute([$sevenDaysAgo]);
    $sales_last_7 = (float)$s7Stmt->fetchColumn();

    $sp7Stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM tbl_payment WHERE payment_status = 'Completed' AND payment_date >= ? AND payment_date <= ?");
    $sp7Stmt->execute([$fourteenDaysAgo, $sevenDaysEnd]);
    $sales_prev_7 = (float)$sp7Stmt->fetchColumn();

    if ($sales_prev_7 > 0) {
        $sales_growth = round((($sales_last_7 - $sales_prev_7) / $sales_prev_7) * 100, 1);
    } elseif ($sales_last_7 > 0) {
        $sales_growth = 100.0;
    } else {
        $sales_growth = 0.0;
    }

    // Orders: Last 7 Days vs Previous 7 Days
    $o7Stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE payment_date >= ?");
    $o7Stmt->execute([$sevenDaysAgo]);
    $orders_last_7 = (int)$o7Stmt->fetchColumn();

    $op7Stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE payment_date >= ? AND payment_date <= ?");
    $op7Stmt->execute([$fourteenDaysAgo, $sevenDaysEnd]);
    $orders_prev_7 = (int)$op7Stmt->fetchColumn();

    if ($orders_prev_7 > 0) {
        $orders_growth = round((($orders_last_7 - $orders_prev_7) / $orders_prev_7) * 100, 1);
    } elseif ($orders_last_7 > 0) {
        $orders_growth = 100.0;
    } else {
        $orders_growth = 0.0;
    }

    // Customers: Last 7 Days vs Previous 7 Days
    $c7Stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_customer WHERE cust_datetime >= ?");
    $c7Stmt->execute([$sevenDaysAgo]);
    $cust_last_7 = (int)$c7Stmt->fetchColumn();

    $cp7Stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_customer WHERE cust_datetime >= ? AND cust_datetime <= ?");
    $cp7Stmt->execute([$fourteenDaysAgo, $sevenDaysEnd]);
    $cust_prev_7 = (int)$cp7Stmt->fetchColumn();

    if ($cust_prev_7 > 0) {
        $cust_growth = round((($cust_last_7 - $cust_prev_7) / $cust_prev_7) * 100, 1);
    } elseif ($cust_last_7 > 0) {
        $cust_growth = 100.0;
    } else {
        $cust_growth = 0.0;
    }

    // Pending Orders: Last 7 Days vs Previous 7 Days
    $p7Stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE (shipping_status = 'Pending' OR payment_status = 'Pending') AND payment_status != 'Cancelled' AND payment_date >= ?");
    $p7Stmt->execute([$sevenDaysAgo]);
    $pending_last_7 = (int)$p7Stmt->fetchColumn();

    $pp7Stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE (shipping_status = 'Pending' OR payment_status = 'Pending') AND payment_status != 'Cancelled' AND payment_date >= ? AND payment_date <= ?");
    $pp7Stmt->execute([$fourteenDaysAgo, $sevenDaysEnd]);
    $pending_prev_7 = (int)$pp7Stmt->fetchColumn();

    if ($pending_prev_7 > 0) {
        $pending_growth = round((($pending_last_7 - $pending_prev_7) / $pending_prev_7) * 100, 1);
    } elseif ($pending_last_7 > 0) {
        $pending_growth = 100.0;
    } else {
        $pending_growth = 0.0;
    }
} catch (Throwable $e) {}

// Real Formatted Strings (Zero Fallback to Fake Numbers)
$disp_sales = $currency_symbol . ' ' . number_format($dash_revenue, 2);
$disp_orders = number_format($dash_total_orders);
$disp_customers = number_format($total_customers);
$disp_pending = number_format($dash_pending_orders);

$hour = (int)date('H');
$timeGreeting = ($hour < 12) ? 'Good Morning' : (($hour < 17) ? 'Good Afternoon' : 'Good Evening');
$rawUserName = !empty($_SESSION['user']['full_name']) ? trim($_SESSION['user']['full_name']) : 'Admin';
if (stripos($rawUserName, 'Self') !== false || strtolower($rawUserName) === 'admin' || empty($rawUserName)) {
    $adminFirst = 'Admin';
} else {
    $parts = explode(' ', $rawUserName);
    $adminFirst = $parts[0];
}

// 6. Real Daily Sales Data for Last 7 Days Chart
$chart_dates = [];
$chart_daily_sales = [];
for ($d = 6; $d >= 0; $d--) {
    $dayKey = date('Y-m-d', strtotime("-$d days"));
    $chart_dates[] = date('M d', strtotime("-$d days"));
    $chart_daily_sales[$dayKey] = 0.0;
}

try {
    $chartStmt = $pdo->prepare("
        SELECT payment_date, paid_amount 
        FROM tbl_payment 
        WHERE payment_status = 'Completed' AND payment_date >= ?
    ");
    $chartStmt->execute([$sevenDaysAgo]);
    $chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chartRows as $cr) {
        $pDate = date('Y-m-d', strtotime($cr['payment_date']));
        if (isset($chart_daily_sales[$pDate])) {
            $chart_daily_sales[$pDate] += (float)$cr['paid_amount'];
        }
    }
} catch (Throwable $e) {}

$chartVals = array_values($chart_daily_sales);
$maxDailyVal = max($chartVals);
if ($maxDailyVal <= 0) {
    $chartCeil = 100;
} else {
    $pow10 = pow(10, max(0, floor(log10($maxDailyVal))));
    $chartCeil = ceil(($maxDailyVal * 1.15) / $pow10) * $pow10;
    if ($chartCeil < 10) $chartCeil = 10;
}

$xCoords = [40, 90, 140, 190, 240, 290, 340];
$points = [];
for ($i = 0; $i < 7; $i++) {
    $v = $chartVals[$i];
    $norm = ($chartCeil > 0) ? ($v / $chartCeil) : 0;
    $norm = max(0, min(1, $norm));
    $y = round(120 - ($norm * 96), 1);
    $points[] = ['x' => $xCoords[$i], 'y' => $y, 'val' => $v];
}

$linePath = "M {$points[0]['x']},{$points[0]['y']}";
for ($i = 0; $i < 6; $i++) {
    $p0 = $points[$i];
    $p1 = $points[$i + 1];
    $cx1 = $p0['x'] + 25;
    $cy1 = $p0['y'];
    $cx2 = $p1['x'] - 25;
    $cy2 = $p1['y'];
    $linePath .= " C $cx1,$cy1 $cx2,$cy2 {$p1['x']},{$p1['y']}";
}
$areaPath = $linePath . " L 340,120 L 40,120 Z";

function fmtChartAxis($num) {
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return round($num / 1000, 1) . 'K';
    return (string)round($num);
}

// 7. Query Real Recent Orders (Limit 5)
$db_recent_orders = [];
$recentOrderItems = [];
try {
    $rStmt = $pdo->query("
        SELECT id, payment_id, customer_name, customer_email, paid_amount, shipping_status, payment_status, payment_date
        FROM tbl_payment
        ORDER BY id DESC
        LIMIT 5
    ");
    $db_recent_orders = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($db_recent_orders)) {
        $pIds = array_filter(array_column($db_recent_orders, 'payment_id'));
        if (!empty($pIds)) {
            $inQuery = implode(',', array_fill(0, count($pIds), '?'));
            $iStmt = $pdo->prepare("
                SELECT o.payment_id, o.product_name, o.quantity, o.unit_price, p.p_featured_photo
                FROM tbl_order o
                LEFT JOIN tbl_product p ON o.product_id = p.p_id
                WHERE o.payment_id IN ($inQuery)
                ORDER BY o.id ASC
            ");
            $iStmt->execute(array_values($pIds));
            $iRows = $iStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($iRows as $ir) {
                $recentOrderItems[$ir['payment_id']][] = $ir;
            }
        }
    }
} catch (Throwable $e) {}

function dashFormatRelativeTime($dateStr) {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    if (!$time) return htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8');
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}

function renderTrendBadge($growth) {
    $isUp = $growth >= 0;
    $class = $isUp ? 'up' : 'down';
    $arrow = $isUp ? '&uarr;' : '&darr;';
    $formatted = abs($growth);
    return "<div class=\"dash-kpi-trend {$class}\">{$arrow} {$formatted}% <span class=\"dash-kpi-subtext\">vs. last week</span></div>";
}
?>

<section class="content">
<div class="dash-container">

    <!-- 1. Welcome Greeting Banner Card -->
    <div class="dash-welcome-card">
        <div class="dash-welcome-header">
            <h2 class="dash-welcome-title"><?= $timeGreeting ?>, <?= htmlspecialchars($adminFirst) ?>! 👋</h2>
            <div class="dash-date-pill">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span><?= date('M d, Y') ?></span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        </div>
        <p class="dash-welcome-sub">Here's a quick overview of your store today.</p>
        
        <!-- Decorative 3D Shopping Cart & Gifts Art -->
        <svg class="dash-welcome-art" width="125" height="95" viewBox="0 0 130 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="podiumGrad" x1="65" y1="72" x2="65" y2="92" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#FDE047"/>
                    <stop offset="1" stop-color="#EAB308"/>
                </linearGradient>
                <linearGradient id="boxYellow" x1="0" y1="0" x2="1" y2="1">
                    <stop stop-color="#FEF08A"/>
                    <stop offset="1" stop-color="#EAB308"/>
                </linearGradient>
                <linearGradient id="boxBlue" x1="0" y1="0" x2="1" y2="1">
                    <stop stop-color="#7DD3FC"/>
                    <stop offset="1" stop-color="#0284C7"/>
                </linearGradient>
                <linearGradient id="boxMint" x1="0" y1="0" x2="1" y2="1">
                    <stop stop-color="#A7F3D0"/>
                    <stop offset="1" stop-color="#059669"/>
                </linearGradient>
            </defs>
            <ellipse cx="65" cy="85" rx="46" ry="10" fill="#000000" opacity="0.08"/>
            <ellipse cx="65" cy="82" rx="42" ry="8" fill="url(#podiumGrad)" opacity="0.75"/>
            
            <g transform="translate(16, 20) scale(0.7)" opacity="0.85">
                <rect x="0" y="4" width="16" height="18" rx="3" fill="#FDE047"/>
                <path d="M4 4 C4 -1 12 -1 12 4" stroke="#B45309" stroke-width="2" fill="none"/>
            </g>
            <g transform="translate(98, 22) scale(0.7)" opacity="0.85">
                <rect x="0" y="4" width="16" height="18" rx="3" fill="#38BDF8"/>
                <path d="M4 4 C4 -1 12 -1 12 4" stroke="#0284C7" stroke-width="2" fill="none"/>
            </g>
            <g transform="translate(24, 60)">
                <rect x="0" y="0" width="13" height="13" rx="2" fill="url(#boxBlue)"/>
                <line x1="6.5" y1="0" x2="6.5" y2="13" stroke="#FFFFFF" stroke-width="1.8"/>
                <line x1="0" y1="6.5" x2="13" y2="6.5" stroke="#FFFFFF" stroke-width="1.8"/>
            </g>
            <g transform="translate(93, 62)">
                <rect x="0" y="0" width="12" height="12" rx="2" fill="url(#boxYellow)"/>
                <line x1="6" y1="0" x2="6" y2="12" stroke="#B45309" stroke-width="1.5"/>
                <line x1="0" y1="6" x2="12" y2="6" stroke="#B45309" stroke-width="1.5"/>
            </g>
            <g transform="translate(48, 38)">
                <rect x="2" y="2" width="22" height="22" rx="3" fill="url(#boxYellow)"/>
                <line x1="13" y1="2" x2="13" y2="24" stroke="#B45309" stroke-width="2"/>
                <line x1="2" y1="13" x2="24" y2="13" stroke="#B45309" stroke-width="2"/>
                <rect x="18" y="8" width="14" height="16" rx="2.5" fill="url(#boxMint)"/>
                <line x1="25" y1="8" x2="25" y2="24" stroke="#FFFFFF" stroke-width="1.8"/>
            </g>
            <circle cx="48" cy="78" r="4.5" fill="#1E293B"/>
            <circle cx="48" cy="78" r="2" fill="#FEF08A"/>
            <circle cx="76" cy="78" r="4.5" fill="#1E293B"/>
            <circle cx="76" cy="78" r="2" fill="#FEF08A"/>
            <path d="M48 76 L54 68 L74 68 L76 76" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            <path d="M38 46 L42 66 L82 66 L88 46 Z" fill="#FDE047" fill-opacity="0.3" stroke="#B45309" stroke-width="2.6" stroke-linejoin="round"/>
            <line x1="40" y1="53" x2="86" y2="53" stroke="#B45309" stroke-width="1.8"/>
            <line x1="41" y1="60" x2="84" y2="60" stroke="#B45309" stroke-width="1.8"/>
            <line x1="50" y1="46" x2="52" y2="66" stroke="#B45309" stroke-width="1.8"/>
            <line x1="62" y1="46" x2="62" y2="66" stroke="#B45309" stroke-width="1.8"/>
            <line x1="74" y1="46" x2="72" y2="66" stroke="#B45309" stroke-width="1.8"/>
            <path d="M38 46 L32 38 L26 38" stroke="#B45309" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        </svg>
    </div>

    <!-- 2. 4 KPI Metric Cards (Single-row 4 columns with Real Data) -->
    <div class="dash-kpi-grid">
        <!-- Total Sales -->
        <div class="dash-kpi-card kpi-yellow">
            <div class="dash-kpi-icon-wrap">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
            </div>
            <div>
                <div class="dash-kpi-label">Total Sales</div>
                <div class="dash-kpi-val"><?= $disp_sales ?></div>
                <?= renderTrendBadge($sales_growth) ?>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="dash-kpi-card kpi-sky">
            <div class="dash-kpi-icon-wrap">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                </svg>
            </div>
            <div>
                <div class="dash-kpi-label">Total Orders</div>
                <div class="dash-kpi-val"><?= $disp_orders ?></div>
                <?= renderTrendBadge($orders_growth) ?>
            </div>
        </div>

        <!-- Total Customers -->
        <div class="dash-kpi-card kpi-mint">
            <div class="dash-kpi-icon-wrap">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                </svg>
            </div>
            <div>
                <div class="dash-kpi-label">Total Customers</div>
                <div class="dash-kpi-val"><?= $disp_customers ?></div>
                <?= renderTrendBadge($cust_growth) ?>
            </div>
        </div>

        <!-- Pending Orders -->
        <div class="dash-kpi-card kpi-lavender">
            <div class="dash-kpi-icon-wrap">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            <div>
                <div class="dash-kpi-label">Pending Orders</div>
                <div class="dash-kpi-val"><?= $disp_pending ?></div>
                <?= renderTrendBadge($pending_growth) ?>
            </div>
        </div>
    </div>

    <!-- 3. Sales Overview Section (with Dynamic Real 7-Day Trend Line Chart) -->
    <div class="dash-sales-overview-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title">Sales Overview</h3>
            <div class="dash-filter-pill">
                <span>Last 7 Days</span>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        </div>

        <!-- Real Trend Line Chart SVG -->
        <div style="position: relative; width: 100%; margin: 8px 0 14px;">
            <svg class="dash-chart-svg" viewBox="0 0 360 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="dashAreaGrad" x1="0" y1="20" x2="0" y2="120" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#FDE047" stop-opacity="0.45"/>
                        <stop offset="100%" stop-color="#FDE047" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>

                <!-- Horizontal Faint Grid Lines -->
                <line x1="32" y1="24" x2="350" y2="24" stroke="#F1F5F9" stroke-width="1"/>
                <line x1="32" y1="48" x2="350" y2="48" stroke="#F1F5F9" stroke-width="1"/>
                <line x1="32" y1="72" x2="350" y2="72" stroke="#F1F5F9" stroke-width="1"/>
                <line x1="32" y1="96" x2="350" y2="96" stroke="#F1F5F9" stroke-width="1"/>
                <line x1="32" y1="120" x2="350" y2="120" stroke="#F1F5F9" stroke-width="1"/>

                <!-- Dynamic Y-Axis Labels based on Real Peak Sales -->
                <text x="8" y="28" fill="#94A3B8" font-size="8.5" font-family="sans-serif" font-weight="600"><?= fmtChartAxis($chartCeil) ?></text>
                <text x="8" y="52" fill="#94A3B8" font-size="8.5" font-family="sans-serif" font-weight="600"><?= fmtChartAxis($chartCeil * 0.75) ?></text>
                <text x="8" y="76" fill="#94A3B8" font-size="8.5" font-family="sans-serif" font-weight="600"><?= fmtChartAxis($chartCeil * 0.5) ?></text>
                <text x="8" y="100" fill="#94A3B8" font-size="8.5" font-family="sans-serif" font-weight="600"><?= fmtChartAxis($chartCeil * 0.25) ?></text>
                <text x="14" y="124" fill="#94A3B8" font-size="8.5" font-family="sans-serif" font-weight="600">0</text>

                <!-- Real Area Fill Path -->
                <path d="<?= $areaPath ?>" fill="url(#dashAreaGrad)"/>

                <!-- Real Curve Line Path -->
                <path d="<?= $linePath ?>" stroke="#EAB308" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>

                <!-- Real Points on curve -->
                <?php foreach ($points as $pt): ?>
                    <circle cx="<?= $pt['x'] ?>" cy="<?= $pt['y'] ?>" r="3.5" fill="#EAB308" stroke="#FFFFFF" stroke-width="1.8">
                        <title><?= $currency_symbol ?> <?= number_format($pt['val'], 2) ?></title>
                    </circle>
                <?php endforeach; ?>

                <!-- X-Axis Labels (Real Dates) -->
                <?php foreach ($chart_dates as $idx => $dStr): ?>
                    <text x="<?= $xCoords[$idx] ?>" y="136" fill="#94A3B8" font-size="8.5" font-family="sans-serif" font-weight="600" text-anchor="middle"><?= $dStr ?></text>
                <?php endforeach; ?>
            </svg>
        </div>

        <!-- Subcards below chart -->
        <div class="dash-sales-subgrid">
            <!-- Total Revenue Subcard -->
            <div class="dash-subcard-rev">
                <div class="dash-subcard-rev-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
                </div>
                <div class="dash-subcard-rev-label">Total Revenue</div>
                <div class="dash-subcard-rev-val"><?= $disp_sales ?></div>
                <div style="margin-top: 3px;">
                    <?= renderTrendBadge($sales_growth) ?>
                </div>
            </div>

            <!-- Payment Breakdown Subcard -->
            <div class="dash-subcard-pm">
                <div class="dash-pm-row">
                    <div class="dash-pm-info">
                        <span style="display:flex; align-items:center; gap:5px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                            Online Payment
                        </span>
                        <span><?= $dash_online_pm_pct ?>% <small style="color:#94a3b8; font-weight:500;">(<?= $online_count ?>)</small></span>
                    </div>
                    <div class="dash-pm-bar-wrap">
                        <div class="dash-pm-bar-fill" style="width: <?= $dash_online_pm_pct ?>%;"></div>
                    </div>
                </div>

                <div class="dash-pm-row">
                    <div class="dash-pm-info">
                        <span style="display:flex; align-items:center; gap:5px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            Cash on Delivery
                        </span>
                        <span><?= $dash_cod_pm_pct ?>% <small style="color:#94a3b8; font-weight:500;">(<?= $cod_count ?>)</small></span>
                    </div>
                    <div class="dash-pm-bar-wrap">
                        <div class="dash-pm-bar-fill" style="width: <?= $dash_cod_pm_pct ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Recent Orders Section (100% Real Customer Transactions) -->
    <div class="dash-orders-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title">Recent Orders</h3>
            <a href="order.php" class="dash-link-more">
                View All &rarr;
            </a>
        </div>

        <div class="dash-orders-list">
            <?php if (!empty($db_recent_orders)): ?>
                <?php foreach ($db_recent_orders as $oRow):
                    $ref = (string)($oRow['payment_id'] ?: $oRow['id']);
                    $cName = (string)($oRow['customer_name'] ?: 'Guest Customer');
                    $orderItems = $recentOrderItems[$oRow['payment_id']] ?? [];
                    
                    if (!empty($orderItems[0]['product_name'])) {
                        $pName = $orderItems[0]['product_name'];
                        if (count($orderItems) > 1) {
                            $pName .= ' (+' . (count($orderItems) - 1) . ' more)';
                        }
                    } else {
                        $pName = 'Order #' . $ref;
                    }
                    
                    $amt = (float)($oRow['paid_amount'] ?? 0);
                    $st = !empty($oRow['shipping_status']) ? $oRow['shipping_status'] : (!empty($oRow['payment_status']) ? $oRow['payment_status'] : 'Pending');
                    $stClass = 'status-' . strtolower($st);
                    $timeText = dashFormatRelativeTime($oRow['payment_date'] ?? '');
                    $photo = !empty($orderItems[0]['p_featured_photo']) ? '../assets/uploads/' . htmlspecialchars($orderItems[0]['p_featured_photo']) : '';
                ?>
                    <a href="order.php" class="dash-order-row">
                        <div class="dash-order-item-left">
                            <div class="dash-order-thumb">
                                <?php if ($photo): ?>
                                    <img src="<?= $photo ?>" alt="<?= htmlspecialchars($pName) ?>" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <span style="display:none;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                    </span>
                                <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                <?php endif; ?>
                            </div>
                            <div class="dash-order-meta">
                                <span class="dash-order-id">#<?= htmlspecialchars($ref) ?></span>
                                <span class="dash-order-cust"><?= htmlspecialchars($cName) ?></span>
                            </div>
                        </div>

                        <div class="dash-order-item-mid">
                            <span class="dash-order-pname" title="<?= htmlspecialchars($pName) ?>"><?= htmlspecialchars($pName) ?></span>
                            <span class="dash-order-price"><?= $currency_symbol ?> <?= number_format($amt, 2) ?></span>
                        </div>

                        <div class="dash-order-item-right">
                            <div class="dash-order-status-col">
                                <span class="dash-status-pill <?= $stClass ?>"><?= htmlspecialchars($st) ?></span>
                                <span class="dash-order-time"><?= htmlspecialchars($timeText) ?></span>
                            </div>
                            <svg class="dash-order-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 36px 16px; color: #64748B;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="2" style="margin-bottom: 8px; display: inline-block;">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <div style="font-weight: 700; color: #0F172A; font-size: 14px;">No orders recorded yet</div>
                    <div style="font-size: 12px; color: #94A3B8; margin-top: 4px;">Customer transactions will appear here automatically in real time.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. Secondary Admin Stats (Subscribers, Categories, FCM) -->
    <div class="hidden-xs" style="margin-bottom: 20px;">
        <div style="display:flex; gap:12px; flex-wrap:wrap; background:#FFFFFF; padding:12px 18px; border-radius:14px; border:1px solid #F1F5F9; font-size:12.5px; color:#475569;">
            <div>📦 Products: <strong style="color:#0F172A;"><?= $total_product ?></strong></div>
            <div>&bull;</div>
            <div>🗂️ Categories: <strong style="color:#0F172A;"><?= $total_top_category ?> Top / <?= $total_mid_category ?> Mid / <?= $total_end_category ?> End</strong></div>
            <div>&bull;</div>
            <div>✉️ Subscribers: <strong style="color:#0F172A;"><?= $total_subscriber ?></strong></div>
            <div>&bull;</div>
            <div>📱 FCM Devices: <strong style="color:#0F172A;"><?= $total_fcm_devices ?></strong></div>
            <div>&bull;</div>
            <div>🔔 Push Sent: <strong style="color:#0F172A;"><?= $total_notifications_sent ?></strong></div>
        </div>
    </div>

</div>

<!-- Broadcast Push Notification Center (Main Dashboard Section) -->
<div class="row hidden-xs" style="margin-top: 20px;">
    <!-- Broadcast Form -->
    <div class="col-md-7">
        <div class="box box-warning" style="border-top: 3px solid #f39c12; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="box-header with-border" style="display: flex; align-items: center; justify-content: space-between;">
                <h3 class="box-title" style="font-weight: 700;">
                    <i class="fa fa-bullhorn text-yellow" style="margin-right: 8px;"></i>
                    Broadcast Push Notification (App & Web)
                </h3>
                <a href="broadcast-notification.php" class="btn btn-xs btn-default" style="font-weight: 600;">
                    <i class="fa fa-sliders"></i> Full Manager & Settings
                </a>
            </div>

            <div class="box-body" style="padding: 20px;">
                <div id="dashBroadcastAlert" style="display:none;" class="alert"></div>

                <form id="dashBroadcastForm">
                    <div class="form-group">
                        <label for="dashNotifTitle" style="font-size: 13px; font-weight: 600;">Notification Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control input-lg" id="dashNotifTitle" name="title" placeholder="e.g. Flash Sale Live! 50% Off Everything Today" required style="border-radius: 6px;">
                    </div>

                    <div class="form-group">
                        <label for="dashNotifBody" style="font-size: 13px; font-weight: 600;">Message Body <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="dashNotifBody" name="body" rows="3" placeholder="e.g. Hurry, grab your favorite fashion and gadgets before deal ends tonight! Tap to view deals." required style="border-radius: 6px; resize: vertical;"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="dashNotifType" style="font-size: 13px; font-weight: 600;">Notification Category</label>
                                <select class="form-control" id="dashNotifType" name="type" style="border-radius: 6px;">
                                    <option value="broadcast" selected>📢 General Broadcast / Notice</option>
                                    <option value="promo">⚡ Flash Deal & Promotion</option>
                                    <option value="order">📦 Order & Shipping Alert</option>
                                    <option value="system">🛡️ System & Security Notice</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="dashNotifAudience" style="font-size: 13px; font-weight: 600;">Target Audience</label>
                                <select class="form-control" id="dashNotifAudience" name="target_audience" style="border-radius: 6px;" onchange="toggleDashTargetAudience(this.value)">
                                    <option value="all" selected>All App Users & Devices (Global)</option>
                                    <option value="specific">Specific Customer (Email / ID)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="dashSpecificCustWrap" style="display: none;">
                        <label for="dashCustTarget" style="font-size: 13px; font-weight: 600;">Customer Email or ID</label>
                        <input type="text" class="form-control" id="dashCustTarget" name="customer_target" placeholder="customer@example.com or Customer ID" style="border-radius: 6px;">
                    </div>

                    <div class="form-group">
                        <label for="dashNotifActionUrl" style="font-size: 13px; font-weight: 600;">Target Screen / Link URL (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-addon" style="background: #f4f6f9; font-size: 12px;"><?php echo BASE_URL; ?></span>
                            <input type="text" class="form-control" id="dashNotifActionUrl" name="action_url" placeholder="deals.php or product.php?id=12" style="border-radius: 0 6px 6px 0;">
                        </div>
                        <small class="text-muted">Where the customer lands when they tap the push notification banner.</small>
                    </div>

                    <button type="button" class="btn btn-warning btn-lg btn-block" id="btnSendDashBroadcast" onclick="submitDashBroadcast()" style="font-weight: 700; border-radius: 6px; padding: 12px; font-size: 15px; box-shadow: 0 2px 6px rgba(243, 156, 18, 0.4);">
                        <i class="fa fa-paper-plane" style="margin-right: 6px;"></i> Send Push Broadcast Now
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Live Smartphone Preview & Recent Broadcasts -->
    <div class="col-md-5">
        <!-- Live Push Mockup -->
        <div class="box box-default" style="border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); background: #ffffff;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-size: 14px; font-weight: 700;">
                    <i class="fa fa-mobile text-primary" style="font-size: 18px; margin-right: 6px;"></i>
                    Live Mobile Push Banner Preview
                </h3>
            </div>
            <div class="box-body" style="background: #eef2f6; padding: 25px 15px; border-radius: 0 0 8px 8px;">
                <!-- Phone Lockscreen Card -->
                <div style="background: #1e293b; border-radius: 18px; padding: 16px 14px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-width: 340px; margin: 0 auto; color: #fff;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 11px; color: #94a3b8;">
                        <span id="previewTime"><?php echo date('h:i A'); ?></span>
                        <span><i class="fa fa-wifi"></i> &bull; 100%</span>
                    </div>

                    <!-- Push Notification Banner inside lockscreen -->
                    <div style="background: rgba(255, 255, 255, 0.95); color: #0f172a; border-radius: 12px; padding: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.12);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="width: 18px; height: 18px; background: #fab802; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #000; font-weight bold;">
                                    <i class="fa fa-shopping-bag"></i>
                                </div>
                                <span style="font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase;">ShopNext Store</span>
                            </div>
                            <span style="font-size: 10px; color: #94a3b8;">now</span>
                        </div>
                        <div id="mockupTitle" style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px;">
                            Flash Sale Live! 50% Off Everything Today
                        </div>
                        <div id="mockupBody" style="font-size: 11.5px; color: #475569; line-height: 1.35;">
                            Hurry, grab your favorite fashion and gadgets before deal ends tonight! Tap to view deals.
                        </div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 10px; font-size: 11px; color: #64748b;">
                    <i class="fa fa-info-circle"></i> Real-time preview of native push on Android / iOS WebView
                </div>
            </div>
        </div>

        <!-- Recent Broadcasts List -->
        <div class="box box-solid" style="border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="box-header with-border" style="display: flex; align-items: center; justify-content: space-between;">
                <h3 class="box-title" style="font-size: 14px; font-weight: 700;">
                    <i class="fa fa-history text-muted" style="margin-right: 6px;"></i>
                    Recent Broadcast Activity
                </h3>
            </div>
            <div class="box-body no-padding">
                <table class="table table-striped table-condensed" style="font-size: 12px; margin: 0;">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_broadcasts)): ?>
                            <?php foreach ($recent_broadcasts as $rb): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars(mb_strimwidth($rb['title'], 0, 28, '...')); ?></strong>
                                    </td>
                                    <td>
                                        <span class="label label-<?php echo $rb['type'] === 'order' ? 'primary' : ($rb['type'] === 'promo' ? 'warning' : 'success'); ?>">
                                            <?php echo htmlspecialchars($rb['type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo date('M d, H:i', strtotime($rb['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted" style="padding: 15px;">No broadcasts sent yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time Push Mockup Sync
document.getElementById('dashNotifTitle').addEventListener('input', function(e) {
    document.getElementById('mockupTitle').textContent = e.target.value.trim() || 'Flash Sale Live! 50% Off Everything Today';
});
document.getElementById('dashNotifBody').addEventListener('input', function(e) {
    document.getElementById('mockupBody').textContent = e.target.value.trim() || 'Hurry, grab your favorite fashion and gadgets before deal ends tonight! Tap to view deals.';
});

function toggleDashTargetAudience(val) {
    var wrap = document.getElementById('dashSpecificCustWrap');
    wrap.style.display = (val === 'specific') ? 'block' : 'none';
}

function submitDashBroadcast() {
    var title = document.getElementById('dashNotifTitle').value.trim();
    var body = document.getElementById('dashNotifBody').value.trim();
    var type = document.getElementById('dashNotifType').value;
    var audience = document.getElementById('dashNotifAudience').value;
    var custTarget = document.getElementById('dashCustTarget').value.trim();
    var actionUrl = document.getElementById('dashNotifActionUrl').value.trim();
    var alertBox = document.getElementById('dashBroadcastAlert');
    var btn = document.getElementById('btnSendDashBroadcast');

    if (!title || !body) {
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = '<i class="fa fa-exclamation-circle"></i> Please fill in both the Title and Message Body.';
        alertBox.style.display = 'block';
        return;
    }

    if (audience === 'specific' && !custTarget) {
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = '<i class="fa fa-exclamation-circle"></i> Please provide a customer email or customer ID.';
        alertBox.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Broadcasting to devices...';
    alertBox.style.display = 'none';

    fetch('broadcast-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            title: title,
            body: body,
            type: type,
            target_audience: audience,
            customer_target: custTarget,
            action_url: actionUrl
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-paper-plane"></i> Send Push Broadcast Now';
        if (data.success) {
            alertBox.className = 'alert alert-success';
            var msg = '<i class="fa fa-check-circle"></i> ' + data.message;
            if (data.details && data.details.push_message) {
                msg += '<br><small>' + data.details.push_message + '</small>';
            }
            alertBox.innerHTML = msg;
            alertBox.style.display = 'block';

            // Reset title and body
            document.getElementById('dashNotifTitle').value = '';
            document.getElementById('dashNotifBody').value = '';
            document.getElementById('dashNotifActionUrl').value = '';
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ' + (data.error || 'Failed to broadcast notification.');
            alertBox.style.display = 'block';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-paper-plane"></i> Send Push Broadcast Now';
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Network error: ' + err.message;
        alertBox.style.display = 'block';
    });
}
</script>
</section>

<?php require_once('footer.php'); ?>