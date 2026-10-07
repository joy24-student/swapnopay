<?php
require_once('header.php');

// Check customer login status
if (!isset($_SESSION['customer'])) {
    header('location: ' . BASE_URL . 'logout.php');
    exit;
} else {
    $statement = $pdo->prepare("SELECT cust_status FROM tbl_customer WHERE cust_id = ? AND cust_status = ?");
    $statement->execute([$_SESSION['customer']['cust_id'], 0]);
    if ($statement->rowCount()) {
        header('location: ' . BASE_URL . 'logout.php');
        exit;
    }
}

$cust_id = (int)$_SESSION['customer']['cust_id'];
$error_message = '';
$success_message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
    if (!$csrf->checkToken()) {
        $error_message = 'Form session expired. Please refresh the page and try again.';
    } else {
        $target_slot  = trim(strip_tags($_POST['target_slot'] ?? 'default'));
        $addr_name    = trim(strip_tags($_POST['addr_name'] ?? ''));
        $addr_phone   = trim(strip_tags($_POST['addr_phone'] ?? ''));
        $addr_line1   = trim(strip_tags($_POST['addr_line1'] ?? ''));
        $addr_line2   = trim(strip_tags($_POST['addr_line2'] ?? ''));
        $addr_city    = trim(strip_tags($_POST['addr_city'] ?? ''));
        $addr_country = trim(strip_tags($_POST['addr_country'] ?? 'Bangladesh'));
        $addr_zip     = trim(strip_tags($_POST['addr_zip'] ?? ''));
        $is_default   = isset($_POST['is_default']) ? 1 : 0;

        $full_addr = $addr_line1;
        if (!empty($addr_line2)) {
            $full_addr .= ', ' . $addr_line2;
        }

        if (empty($addr_name)) {
            $error_message = 'Full name is required.';
        } elseif (empty($addr_phone)) {
            $error_message = 'Phone number is required.';
        } elseif (empty($addr_line1)) {
            $error_message = 'Address line 1 is required.';
        } elseif (empty($addr_city)) {
            $error_message = 'City / District is required.';
        } else {
            // Update database according to target slot
            try {
                if ($target_slot === 'home' || $target_slot === 'billing') {
                    $stmt = $pdo->prepare("
                        UPDATE tbl_customer 
                        SET cust_b_name = ?, cust_b_phone = ?, cust_b_address = ?, cust_b_city = ?, cust_b_country = ?, cust_b_zip = ?
                        WHERE cust_id = ?
                    ");
                    $stmt->execute([$addr_name, $addr_phone, $full_addr, $addr_city, $addr_country, $addr_zip, $cust_id]);
                    $_SESSION['customer']['cust_b_name'] = $addr_name;
                    $_SESSION['customer']['cust_b_phone'] = $addr_phone;
                    $_SESSION['customer']['cust_b_address'] = $full_addr;
                    $_SESSION['customer']['cust_b_city'] = $addr_city;
                    $_SESSION['customer']['cust_b_country'] = $addr_country;
                    $_SESSION['customer']['cust_b_zip'] = $addr_zip;
                } elseif ($target_slot === 'office' || $target_slot === 'shipping') {
                    $stmt = $pdo->prepare("
                        UPDATE tbl_customer 
                        SET cust_s_name = ?, cust_s_phone = ?, cust_s_address = ?, cust_s_city = ?, cust_s_country = ?, cust_s_zip = ?
                        WHERE cust_id = ?
                    ");
                    $stmt->execute([$addr_name, $addr_phone, $full_addr, $addr_city, $addr_country, $addr_zip, $cust_id]);
                    $_SESSION['customer']['cust_s_name'] = $addr_name;
                    $_SESSION['customer']['cust_s_phone'] = $addr_phone;
                    $_SESSION['customer']['cust_s_address'] = $full_addr;
                    $_SESSION['customer']['cust_s_city'] = $addr_city;
                    $_SESSION['customer']['cust_s_country'] = $addr_country;
                    $_SESSION['customer']['cust_s_zip'] = $addr_zip;
                } else {
                    // Default account address
                    $stmt = $pdo->prepare("
                        UPDATE tbl_customer 
                        SET cust_name = ?, cust_phone = ?, cust_address = ?, cust_city = ?, cust_country = ?, cust_zip = ?
                        WHERE cust_id = ?
                    ");
                    $stmt->execute([$addr_name, $addr_phone, $full_addr, $addr_city, $addr_country, $addr_zip, $cust_id]);
                    $_SESSION['customer']['cust_name'] = $addr_name;
                    $_SESSION['customer']['cust_phone'] = $addr_phone;
                    $_SESSION['customer']['cust_address'] = $full_addr;
                    $_SESSION['customer']['cust_city'] = $addr_city;
                    $_SESSION['customer']['cust_country'] = $addr_country;
                    $_SESSION['customer']['cust_zip'] = $addr_zip;
                }

                if ($is_default && $target_slot !== 'default') {
                    $stmt_def = $pdo->prepare("
                        UPDATE tbl_customer 
                        SET cust_name = ?, cust_phone = ?, cust_address = ?, cust_city = ?, cust_country = ?, cust_zip = ?
                        WHERE cust_id = ?
                    ");
                    $stmt_def->execute([$addr_name, $addr_phone, $full_addr, $addr_city, $addr_country, $addr_zip, $cust_id]);
                    $_SESSION['customer']['cust_name'] = $addr_name;
                    $_SESSION['customer']['cust_phone'] = $addr_phone;
                    $_SESSION['customer']['cust_address'] = $full_addr;
                    $_SESSION['customer']['cust_city'] = $addr_city;
                    $_SESSION['customer']['cust_country'] = $addr_country;
                    $_SESSION['customer']['cust_zip'] = $addr_zip;
                }

                $success_message = 'Address successfully updated!';
            } catch (PDOException $e) {
                $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Fetch fresh customer details
$stmt_cust = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ?");
$stmt_cust->execute([$cust_id]);
$cust = $stmt_cust->fetch(PDO::FETCH_ASSOC) ?: $_SESSION['customer'];

// Primary / Default Address Data
$def_name    = !empty($cust['cust_name']) ? $cust['cust_name'] : '';
$def_phone   = !empty($cust['cust_phone']) ? $cust['cust_phone'] : '';
$def_address = !empty($cust['cust_address']) ? $cust['cust_address'] : '';
$def_city    = !empty($cust['cust_city']) ? $cust['cust_city'] : '';
$def_zip     = !empty($cust['cust_zip']) ? $cust['cust_zip'] : '';
$def_country = !empty($cust['cust_country']) ? $cust['cust_country'] : 'Bangladesh';

// Home / Billing Address Data
$home_name    = !empty($cust['cust_b_name']) ? $cust['cust_b_name'] : '';
$home_phone   = !empty($cust['cust_b_phone']) ? $cust['cust_b_phone'] : '';
$home_address = !empty($cust['cust_b_address']) ? $cust['cust_b_address'] : '';
$home_city    = !empty($cust['cust_b_city']) ? $cust['cust_b_city'] : '';
$home_zip     = !empty($cust['cust_b_zip']) ? $cust['cust_b_zip'] : '';
$home_country = !empty($cust['cust_b_country']) ? $cust['cust_b_country'] : 'Bangladesh';

// Office / Shipping Address Data
$off_name    = !empty($cust['cust_s_name']) ? $cust['cust_s_name'] : '';
$off_phone   = !empty($cust['cust_s_phone']) ? $cust['cust_s_phone'] : '';
$off_address = !empty($cust['cust_s_address']) ? $cust['cust_s_address'] : '';
$off_city    = !empty($cust['cust_s_city']) ? $cust['cust_s_city'] : '';
$off_zip     = !empty($cust['cust_s_zip']) ? $cust['cust_s_zip'] : '';
$off_country = !empty($cust['cust_s_country']) ? $cust['cust_s_country'] : 'Bangladesh';

// Bangladesh prominent districts
$bd_districts = [
    'Dhaka', 'Chattogram', 'Rajshahi', 'Khulna', 'Barishal', 'Sylhet', 'Rangpur', 'Mymensingh',
    'Lalmonirhat', 'Lakshmipur', 'Comilla', 'Gazipur', 'Narayanganj', 'Bogra', 'Jessore', 'Dinajpur'
];
?>

<!-- Portal Modern Stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_portal_modern.css?v=<?= time() ?>">

<style>
/* Exact styling matching media_1790349809642.png */
.sn-addr-card-selected {
    border: 2px solid #2563eb !important;
    background: #f8faff !important;
    border-radius: 16px;
    padding: 24px;
    position: relative;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.05);
}

.sn-addr-card-standard {
    border: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    border-radius: 16px;
    padding: 24px;
    position: relative;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
}

.sn-addr-card-standard:hover {
    border-color: #cbd5e1 !important;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
}

.sn-addr-card-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.sn-badge-pill-default {
    background: #2563eb;
    color: #ffffff;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 12px;
    border-radius: 999px;
    display: inline-block;
}

.sn-badge-pill-home {
    background: #eefbf3;
    color: #15803d;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 12px;
    border-radius: 999px;
    display: inline-block;
}

.sn-badge-pill-office {
    background: #f3e8ff;
    color: #7e22ce;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 12px;
    border-radius: 999px;
    display: inline-block;
}

.sn-addr-icon-circle-blue {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.sn-addr-icon-circle-purple {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #faf5ff;
    color: #9333ea;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.sn-btn-action-edit {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 12.5px;
    font-weight: 600;
    color: #2563eb;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.sn-btn-action-edit:hover {
    background: #f1f5f9;
    color: #1d4ed8;
}

.sn-btn-action-dots {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 8px;
    font-size: 13px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}

.sn-btn-action-dots:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.sn-addr-form-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
}

.sn-addr-form-icon-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
}

.sn-form-input-styled {
    width: 100%;
    height: 44px;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 13.5px;
    color: #0f172a;
    background: #ffffff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-sizing: border-box;
    font-family: inherit;
}

.sn-form-input-styled:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.sn-btn-save-addr-yellow {
    background: #f5b800;
    color: #1e293b;
    font-weight: 700;
    border-radius: 10px;
    padding: 13px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    font-size: 14.5px;
    transition: background 0.15s ease, transform 0.1s ease;
}

.sn-btn-save-addr-yellow:hover {
    background: #e6ab00;
    color: #0f172a;
}
</style>

<div class="sn-portal-wrapper">
    <div class="sn-portal-container">
        <div class="sn-portal-layout">
            <!-- Left Shared Navigation Sidebar -->
            <?php require_once('customer-sidebar.php'); ?>

            <!-- Right Addresses Content -->
            <main class="sn-portal-main">
                <!-- Breadcrumbs -->
                <nav class="sn-breadcrumb">
                    <a href="index.php">Home</a>
                    <i class="fa-solid fa-chevron-right"></i>
                    <span>Addresses</span>
                </nav>

                <!-- Page Header -->
                <div style="margin-bottom: 24px;">
                    <h1 class="sn-portal-title">My Addresses</h1>
                    <p class="sn-portal-subtitle">Manage your delivery addresses for faster and easier shopping.</p>
                </div>

                <!-- Notification Alerts -->
                <?php if (!empty($error_message)): ?>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #ef4444; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= $error_message ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= $success_message ?></span>
                    </div>
                <?php endif; ?>

                <!-- Two Column Layout: Address Cards on Left, Add Form on Right -->
                <div class="sn-addresses-layout">
                    <!-- Left: Saved Address Cards Column -->
                    <div class="sn-addresses-list">
                        <!-- Card 1: Default Address (Blue Border & Glow) -->
                        <div class="sn-addr-card-selected" id="card-default">
                            <div class="sn-addr-card-top-row">
                                <span class="sn-badge-pill-default">Default</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <button type="button" class="sn-btn-action-edit" onclick="populateAddressForm('default')">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <button type="button" class="sn-btn-action-dots" title="More options" onclick="populateAddressForm('default')">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="display: flex; gap: 16px; align-items: flex-start;">
                                <div class="sn-addr-icon-circle-blue">
                                    <i class="fa-solid fa-house"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 8px 0; font-size: 15.5px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($def_name ?: 'Default Address') ?></h4>
                                    <?php if (!empty($def_address)): ?>
                                        <p style="margin: 0 0 6px 0; font-size: 13px; color: #475569; line-height: 1.5;">
                                            <i class="fa-solid fa-location-dot" style="color: #2563eb; margin-right: 6px; font-size: 12px;"></i>
                                            <?= htmlspecialchars($def_address) ?><br>
                                            <span style="padding-left: 18px;"><?= htmlspecialchars($def_city) ?><?= !empty($def_zip) ? ', ' . htmlspecialchars($def_zip) : '' ?></span><br>
                                            <span style="padding-left: 18px;"><?= htmlspecialchars($def_country) ?></span>
                                        </p>
                                        <?php if (!empty($def_phone)): ?>
                                            <p style="margin: 0; font-size: 12.5px; color: #64748b; padding-left: 18px;">
                                                <i class="fa-solid fa-phone" style="margin-right: 4px; font-size: 11px;"></i>
                                                <?= htmlspecialchars($def_phone) ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p style="margin: 0 0 6px 0; font-size: 13px; color: #94a3b8; font-style: italic;">
                                            No default address saved yet. Click Edit to add.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Home Address (Green Pill) -->
                        <div class="sn-addr-card-standard" id="card-home">
                            <div class="sn-addr-card-top-row">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <!-- Empty space on left for clean look -->
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span class="sn-badge-pill-home">Home</span>
                                    <button type="button" class="sn-btn-action-edit" onclick="populateAddressForm('home')">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <button type="button" class="sn-btn-action-dots" title="More options" onclick="populateAddressForm('home')">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="display: flex; gap: 16px; align-items: flex-start;">
                                <div class="sn-addr-icon-circle-blue">
                                    <i class="fa-solid fa-house"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 8px 0; font-size: 15.5px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($home_name ?: 'Home Address') ?></h4>
                                    <?php if (!empty($home_address)): ?>
                                        <p style="margin: 0 0 6px 0; font-size: 13px; color: #475569; line-height: 1.5;">
                                            <i class="fa-solid fa-location-dot" style="color: #2563eb; margin-right: 6px; font-size: 12px;"></i>
                                            <?= htmlspecialchars($home_address) ?><br>
                                            <span style="padding-left: 18px;"><?= htmlspecialchars($home_city) ?><?= !empty($home_zip) ? ', ' . htmlspecialchars($home_zip) : '' ?></span><br>
                                            <span style="padding-left: 18px;"><?= htmlspecialchars($home_country) ?></span>
                                        </p>
                                        <?php if (!empty($home_phone)): ?>
                                            <p style="margin: 0; font-size: 12.5px; color: #64748b; padding-left: 18px;">
                                                <i class="fa-solid fa-phone" style="margin-right: 4px; font-size: 11px;"></i>
                                                <?= htmlspecialchars($home_phone) ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p style="margin: 0 0 6px 0; font-size: 13px; color: #94a3b8; font-style: italic;">
                                            No home/billing address saved yet. Click Edit to add.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Office Address (Purple Icon) -->
                        <div class="sn-addr-card-standard" id="card-office">
                            <div class="sn-addr-card-top-row">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <!-- Empty space on left -->
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <button type="button" class="sn-btn-action-edit" onclick="populateAddressForm('office')">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <button type="button" class="sn-btn-action-dots" title="More options" onclick="populateAddressForm('office')">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="display: flex; gap: 16px; align-items: flex-start;">
                                <div class="sn-addr-icon-circle-purple">
                                    <i class="fa-solid fa-building"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 8px 0; font-size: 15.5px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($off_name ?: 'Office Address') ?></h4>
                                    <?php if (!empty($off_address)): ?>
                                        <p style="margin: 0 0 6px 0; font-size: 13px; color: #475569; line-height: 1.5;">
                                            <i class="fa-solid fa-location-dot" style="color: #2563eb; margin-right: 6px; font-size: 12px;"></i>
                                            <?= htmlspecialchars($off_address) ?><br>
                                            <span style="padding-left: 18px;"><?= htmlspecialchars($off_city) ?><?= !empty($off_zip) ? ', ' . htmlspecialchars($off_zip) : '' ?></span><br>
                                            <span style="padding-left: 18px;"><?= htmlspecialchars($off_country) ?></span>
                                        </p>
                                        <?php if (!empty($off_phone)): ?>
                                            <p style="margin: 0; font-size: 12.5px; color: #64748b; padding-left: 18px;">
                                                <i class="fa-solid fa-phone" style="margin-right: 4px; font-size: 11px;"></i>
                                                <?= htmlspecialchars($off_phone) ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p style="margin: 0 0 6px 0; font-size: 13px; color: #94a3b8; font-style: italic;">
                                            No office/shipping address saved yet. Click Edit to add.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Add / Edit Address Form Column -->
                    <div class="sn-address-form-box">
                        <div class="sn-addr-form-card">
                            <div class="sn-addr-form-icon-circle">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <h3 id="formTitle" style="margin: 0 0 4px 0; font-size: 18px; font-weight: 700; color: #0f172a;">Add New Address</h3>
                            <p id="formSubtitle" style="margin: 0 0 20px 0; font-size: 13px; color: #64748b; line-height: 1.45;">Save a new delivery address to make your shopping experience smoother.</p>

                            <form action="" method="post" id="addressForm">
                                <?php $csrf->echoInputField(); ?>
                                <input type="hidden" name="save_address" value="1">
                                <input type="hidden" name="target_slot" id="targetSlot" value="default">

                                <!-- Full Name -->
                                <div style="margin-bottom: 16px;">
                                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                                        Full Name *
                                    </label>
                                    <input 
                                        type="text" 
                                        name="addr_name" 
                                        id="addrName" 
                                        class="sn-form-input-styled" 
                                        placeholder="Enter your name" 
                                        required
                                    >
                                </div>

                                <!-- Phone Number -->
                                <div style="margin-bottom: 16px;">
                                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                                        Phone Number *
                                    </label>
                                    <input 
                                        type="text" 
                                        name="addr_phone" 
                                        id="addrPhone" 
                                        class="sn-form-input-styled" 
                                        placeholder="e.g. +880 1712 345678" 
                                        required
                                    >
                                </div>

                                <!-- Address Line 1 -->
                                <div style="margin-bottom: 16px;">
                                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                                        Address Line 1 *
                                    </label>
                                    <input 
                                        type="text" 
                                        name="addr_line1" 
                                        id="addrLine1" 
                                        class="sn-form-input-styled" 
                                        placeholder="House, Road, Area" 
                                        required
                                    >
                                </div>

                                <!-- Address Line 2 (Optional) -->
                                <div style="margin-bottom: 16px;">
                                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                                        Address Line 2 (Optional)
                                    </label>
                                    <input 
                                        type="text" 
                                        name="addr_line2" 
                                        id="addrLine2" 
                                        class="sn-form-input-styled" 
                                        placeholder="Landmark, Building, Floor"
                                    >
                                </div>

                                <!-- City / District & Country in 2 columns -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                                    <div>
                                        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                                            City / District *
                                        </label>
                                        <select name="addr_city" id="addrCity" class="sn-form-input-styled" required>
                                            <option value="">Select city</option>
                                            <?php foreach ($bd_districts as $dst): ?>
                                                <option value="<?= htmlspecialchars($dst) ?>"><?= htmlspecialchars($dst) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                                            Country *
                                        </label>
                                        <select name="addr_country" id="addrCountry" class="sn-form-input-styled" required>
                                            <option value="Bangladesh" selected>Bangladesh</option>
                                            <option value="India">India</option>
                                            <option value="United States">United States</option>
                                            <option value="United Kingdom">United Kingdom</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Address Slot / Type Selector -->
                                <div style="margin-bottom: 16px;">
                                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                                        Address Label / Slot
                                    </label>
                                    <div style="display: flex; gap: 10px;">
                                        <label style="flex: 1; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;" id="lbl-default">
                                            <input type="radio" name="slot_selector" value="default" checked onchange="setTargetSlot('default')">
                                            Default
                                        </label>
                                        <label style="flex: 1; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;" id="lbl-home">
                                            <input type="radio" name="slot_selector" value="home" onchange="setTargetSlot('home')">
                                            Home
                                        </label>
                                        <label style="flex: 1; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;" id="lbl-office">
                                            <input type="radio" name="slot_selector" value="office" onchange="setTargetSlot('office')">
                                            Office
                                        </label>
                                    </div>
                                </div>

                                <!-- Set as default checkbox -->
                                <div style="margin-bottom: 24px;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; cursor: pointer; font-weight: 500;">
                                        <input type="checkbox" name="is_default" id="isDefault" value="1" checked style="width: 16px; height: 16px; accent-color: #2563eb;">
                                        <span>Set as default address</span>
                                    </label>
                                </div>

                                <!-- Save Address Button -->
                                <button type="submit" class="sn-btn-save-addr-yellow">
                                    <i class="fa-solid fa-plus"></i> <span id="saveBtnText">Save Address</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<script>
// Address data store for interactive editing
const addressData = {
    default: {
        name: <?= json_encode($def_name) ?>,
        phone: <?= json_encode($def_phone) ?>,
        address: <?= json_encode($def_address) ?>,
        city: <?= json_encode($def_city) ?>,
        country: <?= json_encode($def_country) ?>,
        zip: <?= json_encode($def_zip) ?>
    },
    home: {
        name: <?= json_encode($home_name) ?>,
        phone: <?= json_encode($home_phone) ?>,
        address: <?= json_encode($home_address) ?>,
        city: <?= json_encode($home_city) ?>,
        country: <?= json_encode($home_country) ?>,
        zip: <?= json_encode($home_zip) ?>
    },
    office: {
        name: <?= json_encode($off_name) ?>,
        phone: <?= json_encode($off_phone) ?>,
        address: <?= json_encode($off_address) ?>,
        city: <?= json_encode($off_city) ?>,
        country: <?= json_encode($off_country) ?>,
        zip: <?= json_encode($off_zip) ?>
    }
};

function setTargetSlot(slot) {
    document.getElementById('targetSlot').value = slot;
    const isDef = (slot === 'default');
    document.getElementById('isDefault').checked = isDef;
}

function populateAddressForm(slot) {
    const data = addressData[slot];
    if (!data) return;

    document.getElementById('formTitle').textContent = 'Edit ' + (slot.charAt(0).toUpperCase() + slot.slice(1)) + ' Address';
    document.getElementById('formSubtitle').textContent = 'Update your delivery details for this address.';
    document.getElementById('saveBtnText').textContent = 'Update Address';
    
    document.getElementById('targetSlot').value = slot;
    document.getElementById('addrName').value = data.name || '';
    document.getElementById('addrPhone').value = data.phone || '';
    document.getElementById('addrLine1').value = data.address || '';
    document.getElementById('addrLine2').value = '';
    
    // Set city dropdown
    const citySelect = document.getElementById('addrCity');
    let cityMatched = false;
    for (let i = 0; i < citySelect.options.length; i++) {
        if (citySelect.options[i].value.toLowerCase() === (data.city || '').toLowerCase()) {
            citySelect.selectedIndex = i;
            cityMatched = true;
            break;
        }
    }
    if (!cityMatched && data.city) {
        const opt = document.createElement('option');
        opt.value = data.city;
        opt.textContent = data.city;
        opt.selected = true;
        citySelect.appendChild(opt);
    }

    // Set Country
    const countrySelect = document.getElementById('addrCountry');
    for (let i = 0; i < countrySelect.options.length; i++) {
        if (countrySelect.options[i].value.toLowerCase() === (data.country || '').toLowerCase()) {
            countrySelect.selectedIndex = i;
            break;
        }
    }

    // Set Radio
    const radios = document.getElementsByName('slot_selector');
    radios.forEach(r => {
        r.checked = (r.value === slot);
    });

    document.getElementById('isDefault').checked = (slot === 'default');

    if (window.innerWidth < 1024) {
        document.getElementById('addressForm').scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<?php require_once('footer.php'); ?>