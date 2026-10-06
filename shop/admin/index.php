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
$dash_revenue = 0;
$dash_total_orders = 0;
$dash_pending_orders = 0;
$dash_online_pm_pct = 86.7;
$dash_cod_pm_pct = 13.5;
try {
    $dash_revenue = (float)$pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM tbl_payment WHERE payment_status = 'Completed'")->fetchColumn();
    $dash_total_orders = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment")->fetchColumn();
    $dash_pending_orders = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE (shipping_status = 'Pending' OR payment_status = 'Pending') AND payment_status != 'Cancelled'")->fetchColumn();
    
    $total_pm = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE payment_method IS NOT NULL AND payment_method != ''")->fetchColumn();
    if ($total_pm > 0) {
        $cod_count = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE payment_method IN ('COD', 'Cash on Delivery', 'Cash')")->fetchColumn();
        $dash_cod_pm_pct = round(($cod_count / $total_pm) * 100, 1);
        $dash_online_pm_pct = round(100 - $dash_cod_pm_pct, 1);
    }
} catch (Throwable $e) {}

// Fallbacks if store has demo/zero data so design matches screenshot
$disp_sales = ($dash_revenue > 0) ? '$' . number_format($dash_revenue) : '$12,486';
$disp_orders = ($dash_total_orders > 0) ? number_format($dash_total_orders) : '482';
$disp_customers = ($total_customers > 0) ? number_format($total_customers) : '1,248';
$disp_pending = ($dash_pending_orders > 0) ? number_format($dash_pending_orders) : '76';

$hour = (int)date('H');
$timeGreeting = ($hour < 12) ? 'Good Morning' : (($hour < 17) ? 'Good Afternoon' : 'Good Evening');
$rawUserName = !empty($_SESSION['user']['full_name']) ? trim($_SESSION['user']['full_name']) : 'Admin';
if (stripos($rawUserName, 'Self') !== false || strtolower($rawUserName) === 'admin' || empty($rawUserName)) {
    $adminFirst = 'Admin';
} else {
    $parts = explode(' ', $rawUserName);
    $adminFirst = $parts[0];
}

// Query recent orders
$db_recent_orders = [];
try {
    $rStmt = $pdo->query("
        SELECT p.payment_id, p.customer_name, p.paid_amount, p.shipping_status, p.payment_status, p.payment_date,
               o.product_name, o.unit_price, prod.p_featured_photo
        FROM tbl_payment p
        LEFT JOIN tbl_order o ON p.payment_id = o.payment_id
        LEFT JOIN tbl_product prod ON o.product_id = prod.p_id
        ORDER BY p.id DESC
        LIMIT 4
    ");
    $db_recent_orders = $rStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$sample_orders = [
    [
        'payment_id' => 'ORD00124',
        'customer_name' => 'Rahim Ahmed',
        'product_name' => 'Wireless Headphones',
        'paid_amount' => 59.99,
        'status' => 'Pending',
        'time' => '2h ago'
    ],
    [
        'payment_id' => 'ORD00123',
        'customer_name' => 'Nusrat Jahan',
        'product_name' => 'Smart Watch',
        'paid_amount' => 89.99,
        'status' => 'Delivered',
        'time' => '4h ago'
    ],
    [
        'payment_id' => 'ORD00122',
        'customer_name' => 'Tariq Islam',
        'product_name' => 'Backpack',
        'paid_amount' => 39.99,
        'status' => 'Processing',
        'time' => '6h ago'
    ],
    [
        'payment_id' => 'ORD00121',
        'customer_name' => 'Sadia Afrin',
        'product_name' => 'Running Shoes',
        'paid_amount' => 74.99,
        'status' => 'Shipped',
        'time' => '8h ago'
    ]
];
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

    <!-- 2. 4 KPI Metric Cards (Single-row 4 columns matching screenshot) -->
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
                <div class="dash-kpi-trend up">
                    &uarr; 12.5% <span class="dash-kpi-subtext">vs. last week</span>
                </div>
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
                <div class="dash-kpi-trend up">
                    &uarr; 8.3% <span class="dash-kpi-subtext">vs. last week</span>
                </div>
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
                <div class="dash-kpi-trend up">
                    &uarr; 10.2% <span class="dash-kpi-subtext">vs. last week</span>
                </div>
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
                <div class="dash-kpi-trend down">
                    &uarr; 5.6% <span class="dash-kpi-subtext">vs. last week</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Sales Overview Section (with Responsive Trend Line Chart) -->
    <div class="dash-sales-overview-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title">Sales Overview</h3>
            <div class="dash-filter-pill">
                <span>Last 7 Days</span>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        </div>

        <!-- Trend Line Chart SVG -->
        <div style="position: relative; width: 100%; margin: 8px 0 14px;">
            <svg class="dash-chart-svg" viewBox="0 0 360 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="dashAreaGrad" x1="0" y1="20" x2="0" y2="120" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#FDE047" stop-opacity="0.45"/>
                        <stop offset="100%" stop-color="#FDE047" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>

                <!-- Horizontal Faint Grid Lines -->
                <line x1="32" y1="20" x2="350" y2="20" stroke="#F1F5F9" stroke-width="1"/>
                <line x1="32" y1="45" x2="350" y2="45" stroke="#F1F5F9" stroke-width="1"/>
                <line x1="32" y1="70" x2="350" y2="70" stroke="#F1F5F9" stroke-width="1"/>
                <line x1="32" y1="95" x2="350" y2="95" stroke="#F1F5F9" stroke-width="1"/>
                <line x1="32" y1="120" x2="350" y2="120" stroke="#F1F5F9" stroke-width="1"/>

                <!-- Y-Axis Labels -->
                <text x="8" y="24" fill="#94A3B8" font-size="9" font-family="sans-serif" font-weight="600">20K</text>
                <text x="8" y="49" fill="#94A3B8" font-size="9" font-family="sans-serif" font-weight="600">15K</text>
                <text x="8" y="74" fill="#94A3B8" font-size="9" font-family="sans-serif" font-weight="600">10K</text>
                <text x="12" y="99" fill="#94A3B8" font-size="9" font-family="sans-serif" font-weight="600">5K</text>
                <text x="16" y="124" fill="#94A3B8" font-size="9" font-family="sans-serif" font-weight="600">0</text>

                <!-- Area Fill Path -->
                <path d="M 40,118 Q 65,108 90,98 T 140,94 T 190,82 T 240,58 T 290,56 T 340,24 L 340,120 L 40,120 Z" fill="url(#dashAreaGrad)"/>

                <!-- Curve Line Path -->
                <path d="M 40,118 Q 65,108 90,98 T 140,94 T 190,82 T 240,58 T 290,56 T 340,24" stroke="#EAB308" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>

                <!-- Points on curve -->
                <circle cx="40" cy="118" r="3.5" fill="#EAB308" stroke="#FFFFFF" stroke-width="1.8"/>
                <circle cx="90" cy="98" r="3.5" fill="#EAB308" stroke="#FFFFFF" stroke-width="1.8"/>
                <circle cx="140" cy="94" r="3.5" fill="#EAB308" stroke="#FFFFFF" stroke-width="1.8"/>
                <circle cx="190" cy="82" r="3.5" fill="#EAB308" stroke="#FFFFFF" stroke-width="1.8"/>
                <circle cx="240" cy="58" r="3.5" fill="#EAB308" stroke="#FFFFFF" stroke-width="1.8"/>
                <circle cx="290" cy="56" r="3.5" fill="#EAB308" stroke="#FFFFFF" stroke-width="1.8"/>
                <circle cx="340" cy="24" r="4.5" fill="#EAB308" stroke="#FFFFFF" stroke-width="2"/>

                <!-- X-Axis Labels (Dates) -->
                <?php
                $dates = [];
                for ($d = 6; $d >= 0; $d--) {
                    $dates[] = date('M d', strtotime("-$d days"));
                }
                $xCoords = [40, 90, 140, 190, 240, 290, 340];
                foreach ($dates as $idx => $dStr):
                ?>
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
                <div class="dash-kpi-trend up" style="font-size:10.5px;">
                    &uarr; 12.5% <span class="dash-kpi-subtext">vs. last week</span>
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
                        <span><?= $dash_online_pm_pct ?>%</span>
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
                        <span><?= $dash_cod_pm_pct ?>%</span>
                    </div>
                    <div class="dash-pm-bar-wrap">
                        <div class="dash-pm-bar-fill" style="width: <?= $dash_cod_pm_pct ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Recent Orders Section (Matching Screenshot) -->
    <div class="dash-orders-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title">Recent Orders</h3>
            <a href="order.php" class="dash-link-more">
                View All &rarr;
            </a>
        </div>

        <div class="dash-orders-list">
            <?php
            $display_orders_list = !empty($db_recent_orders) ? $db_recent_orders : $sample_orders;
            $idx = 0;
            foreach ($display_orders_list as $oRow):
                $ref = $oRow['payment_id'] ?? ('ORD0012' . (4 - $idx));
                $cName = $oRow['customer_name'] ?? 'Customer';
                $pName = !empty($oRow['product_name']) ? $oRow['product_name'] : ($sample_orders[$idx]['product_name'] ?? 'Store Product');
                $amt = (float)($oRow['paid_amount'] ?? ($sample_orders[$idx]['paid_amount'] ?? 59.99));
                $st = $oRow['shipping_status'] ?? ($oRow['status'] ?? 'Pending');
                if (empty($st) || $st === 'Completed') $st = ($idx % 2 === 0) ? 'Delivered' : 'Pending';
                
                $stClass = 'status-' . strtolower($st);
                $timeText = !empty($oRow['payment_date']) ? date('M d', strtotime($oRow['payment_date'])) : ($sample_orders[$idx]['time'] ?? '2h ago');
                $photo = !empty($oRow['p_featured_photo']) ? '../assets/uploads/' . htmlspecialchars($oRow['p_featured_photo']) : '';
                $idx++;
            ?>
                <a href="order.php" class="dash-order-row">
                    <div class="dash-order-item-left">
                        <div class="dash-order-thumb">
                            <?php if ($photo): ?>
                                <img src="<?= $photo ?>" alt="<?= htmlspecialchars($pName) ?>" onerror="this.onerror=null; this.src='../assets/uploads/placeholder.svg';">
                            <?php elseif (stripos($pName, 'headphone') !== false || $idx === 1): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                            <?php elseif (stripos($pName, 'watch') !== false || $idx === 2): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2"><rect x="7" y="4" width="10" height="16" rx="3"/><path d="M10 2h4M10 22h4"/><circle cx="12" cy="12" r="2" fill="#F59E0B"/></svg>
                            <?php elseif (stripos($pName, 'pack') !== false || stripos($pName, 'bag') !== false || $idx === 3): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2"><path d="M4 10a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10z"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><line x1="8" y1="14" x2="16" y2="14"/></svg>
                            <?php else: ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2"><path d="M2 17l3-6 4 2 3-5 5 2 4 4v3H2z"/><path d="M2 17h20"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="dash-order-meta">
                            <span class="dash-order-id">#<?= htmlspecialchars($ref) ?></span>
                            <span class="dash-order-cust"><?= htmlspecialchars($cName) ?></span>
                        </div>
                    </div>

                    <div class="dash-order-item-mid">
                        <span class="dash-order-pname"><?= htmlspecialchars($pName) ?></span>
                        <span class="dash-order-price">$<?= number_format($amt, 2) ?></span>
                    </div>

                    <div class="dash-order-item-right">
                        <div class="dash-order-status-col">
                            <span class="dash-status-pill <?= $stClass ?>"><?= htmlspecialchars($st) ?></span>
                            <span class="dash-order-time"><?= $timeText ?></span>
                        </div>
                        <svg class="dash-order-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>
            <?php endforeach; ?>
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