<?php
// Modern ShopNext account redirector
$mode = isset($_GET['mode']) ? strtolower($_GET['mode']) : ($accountMode ?? 'login');
$target = ($mode === 'register' || $mode === 'registration') ? 'registration.php' : 'login.php';
header("Location: " . $target);
exit;
