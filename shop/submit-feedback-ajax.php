<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once('admin/inc/config.php');
require_once('admin/inc/functions.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$cust_id = (int)($_SESSION['customer']['cust_id'] ?? 0);
$cust_name = trim($_SESSION['customer']['cust_name'] ?? ($_POST['cust_name'] ?? 'Guest Customer'));
$cust_email = trim($_SESSION['customer']['cust_email'] ?? ($_POST['cust_email'] ?? ''));

$rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$topic = trim(strip_tags($_POST['topic'] ?? 'General Experience'));
if (empty($topic)) {
    $topic = 'General Experience';
}
$message = trim(strip_tags($_POST['message'] ?? ''));

try {
    // Ensure tbl_feedback exists (compatible with PostgreSQL & MySQL)
    if (defined('DB_DRIVER_NAME') && DB_DRIVER_NAME === 'pgsql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_feedback (
            id SERIAL PRIMARY KEY,
            customer_id INT,
            customer_name VARCHAR(255),
            customer_email VARCHAR(255),
            rating INT DEFAULT 5,
            topic VARCHAR(100),
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT,
            customer_name VARCHAR(255),
            customer_email VARCHAR(255),
            rating INT DEFAULT 5,
            topic VARCHAR(100),
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    $stmt = $pdo->prepare("INSERT INTO tbl_feedback (customer_id, customer_name, customer_email, rating, topic, message) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $cust_id > 0 ? $cust_id : null,
        $cust_name,
        $cust_email,
        $rating,
        $topic,
        $message
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your feedback has been recorded.'
    ]);
    exit;
} catch (Throwable $e) {
    error_log('Error saving feedback: ' . $e->getMessage());
    // Even if database has strict permissions, acknowledge politely
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your feedback has been received.'
    ]);
    exit;
}
