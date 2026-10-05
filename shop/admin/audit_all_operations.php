<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI execution only.');
}

$_SERVER['HTTP_HOST'] = 'shop.swapnopay.top';
$_SERVER['REQUEST_URI'] = '/self-hosted-supabase-store-0558/admin/settings.php';
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/catalog.php';
require_once __DIR__ . '/inc/orders.php';

echo "=====================================================\n";
echo "   COMPREHENSIVE SHOP & ADMIN SYSTEM AUDIT\n";
echo "=====================================================\n\n";

$passCount = 0;
$failCount = 0;

function report($name, $success, $details = '') {
    global $passCount, $failCount;
    if ($success) {
        $passCount++;
        echo "[PASS] $name\n";
    } else {
        $failCount++;
        echo "[FAIL] $name: $details\n";
    }
}

// -------------------------------------------------------------------
// TEST 1: Syntax check of all PHP files
// -------------------------------------------------------------------
echo "--- 1. PHP Syntax Check Across All Files ---\n";
$directory = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
$syntaxErrors = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getRealPath();
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($filePath) . " 2>&1", $output, $returnVar);
        if ($returnVar !== 0) {
            $syntaxErrors[] = $filePath . ": " . implode("\n", $output);
        }
    }
}
if (empty($syntaxErrors)) {
    report("All PHP files syntax check", true);
} else {
    report("PHP files syntax check", false, implode("\n", $syntaxErrors));
}

// -------------------------------------------------------------------
// TEST 2: Inspect & verify required tables & columns in DB
// -------------------------------------------------------------------
echo "\n--- 2. Database Schema Integrity ---\n";
$tables = [
    'tbl_product', 'tbl_product_photo', 'tbl_product_size', 'tbl_product_color',
    'tbl_top_category', 'tbl_mid_category', 'tbl_end_category',
    'tbl_customer', 'tbl_country', 'tbl_payment', 'tbl_order',
    'tbl_coupon', 'tbl_slider', 'tbl_rating', 'tbl_settings'
];

foreach ($tables as $tbl) {
    try {
        $stmt = $pdo->query("SELECT count(*) FROM $tbl");
        $cnt = (int)$stmt->fetchColumn();
        report("Table '$tbl' exists and readable (count: $cnt)", true);
    } catch (Throwable $e) {
        report("Table '$tbl' check", false, $e->getMessage());
    }
}

// Ensure necessary schema auto-migrations for tbl_product & tbl_customer
try {
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS business_id integer DEFAULT NULL");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_total_view integer DEFAULT 0");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS cust_id integer DEFAULT 0");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_video_link text DEFAULT ''");
    $pdo->exec("ALTER TABLE tbl_customer ADD COLUMN IF NOT EXISTS cust_country integer DEFAULT 0");
    $pdo->exec("ALTER TABLE tbl_customer ADD COLUMN IF NOT EXISTS cust_status smallint DEFAULT 1");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_businesses (business_id SERIAL PRIMARY KEY, business_name varchar(255), owner_user_id integer)");
    report("Schema auto-migrations applied safely", true);
} catch (Throwable $e) {
    report("Schema auto-migrations", false, $e->getMessage());
}

// -------------------------------------------------------------------
// TEST 3: Category Management Flow
// -------------------------------------------------------------------
echo "\n--- 3. Category CRUD Flow ---\n";
$testTcatId = 0;
$testMcatId = 0;
$testEcatId = 0;
try {
    // 3.1 Create top category
    $stmt = $pdo->prepare("INSERT INTO tbl_top_category (tcat_name, show_on_menu) VALUES (?, ?) " . (DB_DRIVER_NAME === 'pgsql' ? 'RETURNING tcat_id' : ''));
    $stmt->execute(['__TEST_TOP_CAT__', 1]);
    $testTcatId = (int)(DB_DRIVER_NAME === 'pgsql' ? $stmt->fetchColumn() : $pdo->lastInsertId());
    report("Top Category Insert (id: $testTcatId)", $testTcatId > 0);

    // 3.2 Create mid category
    $stmt = $pdo->prepare("INSERT INTO tbl_mid_category (mcat_name, tcat_id) VALUES (?, ?) " . (DB_DRIVER_NAME === 'pgsql' ? 'RETURNING mcat_id' : ''));
    $stmt->execute(['__TEST_MID_CAT__', $testTcatId]);
    $testMcatId = (int)(DB_DRIVER_NAME === 'pgsql' ? $stmt->fetchColumn() : $pdo->lastInsertId());
    report("Mid Category Insert (id: $testMcatId)", $testMcatId > 0);

    // 3.3 Create end category
    $stmt = $pdo->prepare("INSERT INTO tbl_end_category (ecat_name, mcat_id) VALUES (?, ?) " . (DB_DRIVER_NAME === 'pgsql' ? 'RETURNING ecat_id' : ''));
    $stmt->execute(['__TEST_END_CAT__', $testMcatId]);
    $testEcatId = (int)(DB_DRIVER_NAME === 'pgsql' ? $stmt->fetchColumn() : $pdo->lastInsertId());
    report("End Category Insert (id: $testEcatId)", $testEcatId > 0);

    // 3.4 Test get-mid-category & get-end-category query logic
    $mcatStmt = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id = ?");
    $mcatStmt->execute([$testTcatId]);
    $mats = $mcatStmt->fetchAll(PDO::FETCH_ASSOC);
    report("get-mid-category query (results: " . count($mats) . ")", count($mats) > 0);

    $ecatStmt = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id = ?");
    $ecatStmt->execute([$testMcatId]);
    $ecats = $ecatStmt->fetchAll(PDO::FETCH_ASSOC);
    report("get-end-category query (results: " . count($ecats) . ")", count($ecats) > 0);
} catch (Throwable $e) {
    report("Category CRUD Flow", false, $e->getMessage());
}

// -------------------------------------------------------------------
// TEST 4: Product Add, Edit, List & Delete Flow
// -------------------------------------------------------------------
echo "\n--- 4. Product Add, Edit, List & Delete Flow ---\n";
$testProductId = 0;
try {
    // 4.1 Test saveStoreProduct (Product Add)
    $productData = [
        'p_name' => '__TEST_PRODUCT__ ' . time(),
        'p_current_price' => '250.00',
        'p_old_price' => '300.00',
        'p_qty' => '50',
        'tcat_id' => (string)$testTcatId,
        'mcat_id' => (string)$testMcatId,
        'ecat_id' => (string)$testEcatId,
        'p_description' => 'Test description for automated verification',
        'p_short_description' => 'Test short description',
        'p_feature' => 'Test feature',
        'p_condition' => 'New',
        'p_return_policy' => '7 days',
        'p_is_featured' => '1',
        'p_is_active' => '1',
        'size' => [],
        'color' => []
    ];
    $productFiles = [
        'p_featured_photo' => ['error' => UPLOAD_ERR_NO_FILE],
        'photo' => ['tmp_name' => []]
    ];

    $testProductId = saveStoreProduct($pdo, $productData, $productFiles, null);
    report("Product Add via saveStoreProduct (id: $testProductId)", $testProductId > 0);

    // 4.2 Test saveStoreProduct (Product Edit)
    $productData['p_name'] = '__TEST_PRODUCT_UPDATED__';
    $productData['p_current_price'] = '220.00';
    $productData['p_qty'] = '45';
    $updatedId = saveStoreProduct($pdo, $productData, $productFiles, $testProductId);
    report("Product Edit via saveStoreProduct (id: $updatedId)", $updatedId === $testProductId);

    // Verify values updated in DB
    $checkProduct = $pdo->prepare("SELECT p_name, p_current_price, p_qty FROM tbl_product WHERE p_id = ?");
    $checkProduct->execute([$testProductId]);
    $pRow = $checkProduct->fetch(PDO::FETCH_ASSOC);
    report("Product data verified in DB (name: {$pRow['p_name']}, price: {$pRow['p_current_price']})", $pRow && $pRow['p_name'] === '__TEST_PRODUCT_UPDATED__');

    // 4.3 Test Product Listing Query in product.php
    $prodListStmt = $pdo->prepare("SELECT
        t1.p_id, t1.p_name, t1.p_old_price, t1.p_current_price, t1.p_qty, t1.p_featured_photo,
        t1.p_is_featured, t1.p_is_active, t1.ecat_id, t1.business_id,
        t2.ecat_id AS end_ecat_id, t2.ecat_name,
        t3.mcat_id, t3.mcat_name,
        t4.tcat_id, t4.tcat_name,
        b.business_name, b.owner_user_id
        FROM tbl_product t1
        LEFT JOIN tbl_end_category t2 ON t1.ecat_id = t2.ecat_id
        LEFT JOIN tbl_mid_category t3 ON t2.mcat_id = t3.mcat_id
        LEFT JOIN tbl_top_category t4 ON t3.tcat_id = t4.tcat_id
        LEFT JOIN tbl_businesses b ON t1.business_id = b.business_id
        ORDER BY t1.p_id DESC LIMIT 10");
    $prodListStmt->execute();
    $listedProds = $prodListStmt->fetchAll(PDO::FETCH_ASSOC);
    report("Product list query (product.php) execution", count($listedProds) > 0);

    // 4.4 Delete test product
    $pdo->prepare("DELETE FROM tbl_product WHERE p_id = ?")->execute([$testProductId]);
    report("Product Delete cleanup", true);
} catch (Throwable $e) {
    report("Product Add/Edit/List Flow", false, $e->getMessage());
}

// Clean up test categories
try {
    if ($testEcatId) $pdo->prepare("DELETE FROM tbl_end_category WHERE ecat_id = ?")->execute([$testEcatId]);
    if ($testMcatId) $pdo->prepare("DELETE FROM tbl_mid_category WHERE mcat_id = ?")->execute([$testMcatId]);
    if ($testTcatId) $pdo->prepare("DELETE FROM tbl_top_category WHERE tcat_id = ?")->execute([$testTcatId]);
} catch (Throwable $e) {}

// -------------------------------------------------------------------
// TEST 5: Customer View, Status Toggle & Delete Flow
// -------------------------------------------------------------------
echo "\n--- 5. Customer Operations Flow ---\n";
$testCustId = 0;
try {
    // 5.1 Test customer listing query
    $custQuery = $pdo->prepare("SELECT * FROM tbl_customer t1 LEFT JOIN tbl_country t2 ON t1.cust_country = t2.country_id LIMIT 10");
    $custQuery->execute();
    $customers = $custQuery->fetchAll(PDO::FETCH_ASSOC);
    report("Customer list query (customer.php) execution", true);

    // 5.2 Insert test customer
    $stmt = $pdo->prepare("INSERT INTO tbl_customer (cust_name, cust_email, cust_phone, cust_country, cust_status, cust_password) VALUES (?, ?, ?, ?, ?, ?) " . (DB_DRIVER_NAME === 'pgsql' ? 'RETURNING cust_id' : ''));
    $stmt->execute(['__TEST_CUSTOMER__', 'test_audit_' . time() . '@example.com', '01700000000', 1, 1, 'hash_pass']);
    $testCustId = (int)(DB_DRIVER_NAME === 'pgsql' ? $stmt->fetchColumn() : $pdo->lastInsertId());
    report("Customer creation (id: $testCustId)", $testCustId > 0);

    // 5.3 Toggle customer status (customer-change-status.php logic)
    $stmt = $pdo->prepare("SELECT cust_status FROM tbl_customer WHERE cust_id = ?");
    $stmt->execute([$testCustId]);
    $currentStatus = (int)$stmt->fetchColumn();
    $newStatus = $currentStatus === 1 ? 0 : 1;
    $pdo->prepare("UPDATE tbl_customer SET cust_status = ? WHERE cust_id = ?")->execute([$newStatus, $testCustId]);

    $checkStatus = (int)$pdo->query("SELECT cust_status FROM tbl_customer WHERE cust_id = $testCustId")->fetchColumn();
    report("Customer status toggle (from $currentStatus to $checkStatus)", $checkStatus === $newStatus);

    // 5.4 Cleanup test customer
    $pdo->prepare("DELETE FROM tbl_customer WHERE cust_id = ?")->execute([$testCustId]);
    report("Customer delete (customer-delete.php logic)", true);
} catch (Throwable $e) {
    report("Customer Operations Flow", false, $e->getMessage());
}

// -------------------------------------------------------------------
// TEST 6: Order Creation, Status & Delivery (Fulfillment) Flow
// -------------------------------------------------------------------
echo "\n--- 6. Order Creation & Delivery (Fulfillment) Flow ---\n";
$testPaymentRef = 'COD-AUDIT-' . time() . '-' . mt_rand(100, 999);
try {
    // 6.1 Create test order in tbl_payment
    $stmt = $pdo->prepare("INSERT INTO tbl_payment (
        customer_id, customer_name, customer_email, payment_date, 
        txnid, paid_amount, shipping_cost, coupon_code, coupon_discount, 
        payment_method, payment_status, shipping_status, payment_id, payment_note, 
        card_number, bank_transaction_info,                 
        billing_name, billing_email, billing_phone, billing_street, billing_city, 
        billing_state, billing_country, billing_zip, shipping_name, shipping_email, 
        shipping_phone, shipping_street, shipping_city, shipping_state, shipping_country, shipping_zip
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->execute([
        1, 'Audit Customer', 'audit@example.com', date('Y-m-d H:i:s'),
        '', 750.00, 60.00, '', 0,
        'Cash on Delivery', 'Pending', 'Pending', $testPaymentRef, 'Test order note',
        '', '',
        'Audit Customer', 'audit@example.com', '01711111111', '123 Test St', 'Dhaka',
        'Dhaka', '1', '1205',
        'Audit Customer', 'audit@example.com', '01711111111', '123 Test St', 'Dhaka',
        'Dhaka', '1', '1205'
    ]);
    report("Order payment record created in tbl_payment ($testPaymentRef)", true);

    // 6.2 Insert order items in tbl_order
    $orderItemStmt = $pdo->prepare("INSERT INTO tbl_order (
        cust_id, product_id, product_name, size, color, quantity, unit_price, payment_id, coupon_code, coupon_discount
    ) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $orderItemStmt->execute([1, 1, 'Sample Item', 'L', 'Blue', 2, 345.00, $testPaymentRef, '', 0]);
    report("Order item created in tbl_order", true);

    // 6.3 Test order listing in order.php
    $orderListQuery = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ?");
    $orderListQuery->execute([$testPaymentRef]);
    $foundOrder = $orderListQuery->fetch(PDO::FETCH_ASSOC);
    report("Order retrieved via order.php query", $foundOrder && $foundOrder['payment_id'] === $testPaymentRef);

    // 6.4 Test updateStoreOrder: Mark Paid (order-change-status.php)
    updateStoreOrder($pdo, $testPaymentRef, 'paid');
    $checkPaid = $pdo->query("SELECT payment_status FROM tbl_payment WHERE payment_id = '$testPaymentRef'")->fetchColumn();
    report("Order payment marked 'Completed' via updateStoreOrder", $checkPaid === 'Completed');

    // 6.5 Test updateStoreOrder: Mark Shipped (shipping-change-status.php)
    updateStoreOrder($pdo, $testPaymentRef, 'shipped');
    $checkShipped = $pdo->query("SELECT shipping_status FROM tbl_payment WHERE payment_id = '$testPaymentRef'")->fetchColumn();
    report("Order fulfillment marked 'Shipped' via updateStoreOrder", $checkShipped === 'Shipped');

    // 6.6 Test updateStoreOrder: Mark Delivered (shipping-change-status.php)
    updateStoreOrder($pdo, $testPaymentRef, 'delivered');
    $checkDelivered = $pdo->query("SELECT shipping_status FROM tbl_payment WHERE payment_id = '$testPaymentRef'")->fetchColumn();
    report("Order fulfillment marked 'Delivered' via updateStoreOrder", $checkDelivered === 'Delivered');

    // 6.7 Cleanup test order
    $pdo->prepare("DELETE FROM tbl_order WHERE payment_id = ?")->execute([$testPaymentRef]);
    $pdo->prepare("DELETE FROM tbl_payment WHERE payment_id = ?")->execute([$testPaymentRef]);
    report("Test order cleanup", true);
} catch (Throwable $e) {
    report("Order Creation & Delivery Flow", false, $e->getMessage());
}

// -------------------------------------------------------------------
// TEST 7: Shipping, Coupons, Sliders & Reviews
// -------------------------------------------------------------------
echo "\n--- 7. Other Essential Store Modules ---\n";
try {
    // 7.1 Shipping cost lookup
    $shipStmt = $pdo->query("SELECT * FROM tbl_shipping_cost_all WHERE sca_id=1");
    $shipAll = $shipStmt->fetch(PDO::FETCH_ASSOC);
    report("Shipping Cost General (tbl_shipping_cost_all)", !empty($shipAll));

    // 7.2 Coupon verification query
    $couponStmt = $pdo->query("SELECT * FROM tbl_coupon LIMIT 5");
    $coupons = $couponStmt->fetchAll(PDO::FETCH_ASSOC);
    report("Coupons table query (count: " . count($coupons) . ")", true);

    // 7.3 Sliders management query
    $sliderStmt = $pdo->query("SELECT * FROM tbl_slider ORDER BY slide_order ASC, id ASC");
    $sliders = $sliderStmt->fetchAll(PDO::FETCH_ASSOC);
    report("Sliders query (count: " . count($sliders) . ")", true);

    // 7.4 Product Reviews query
    $reviewStmt = $pdo->query("SELECT * FROM tbl_rating LIMIT 5");
    $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    report("Reviews query (tbl_rating)", true);
} catch (Throwable $e) {
    report("Other modules check", false, $e->getMessage());
}

echo "\n=====================================================\n";
echo "AUDIT SUMMARY: $passCount passed, $failCount failed.\n";
echo "=====================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
