<?php
require_once('header.php');

// Check if customer is logged in
if (!isset($_SESSION['customer'])) {
    header('location: ' . BASE_URL . 'logout.php');
    exit;
} else {
    // If customer is inactive, force logout
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
if (isset($_POST['form1'])) {
    if (!$csrf->checkToken()) {
        $error_message = 'Form session expired. Please refresh the page and try again.';
    } else {
        $first_name = trim(strip_tags($_POST['first_name'] ?? ''));
        $last_name = trim(strip_tags($_POST['last_name'] ?? ''));
        $full_name = trim($first_name . ' ' . $last_name);
        if (empty($full_name)) {
            $full_name = trim(strip_tags($_POST['cust_name'] ?? ''));
        }
        
        $phone = trim(strip_tags($_POST['cust_phone'] ?? ''));
        $dob = trim(strip_tags($_POST['cust_dob'] ?? ''));
        $gender = trim(strip_tags($_POST['cust_gender'] ?? ''));
        $country = trim(strip_tags($_POST['cust_country'] ?? ''));
        $city = trim(strip_tags($_POST['cust_city'] ?? ''));

        if (empty($full_name)) {
            $error_message = 'Name is required.';
        } elseif (empty($phone)) {
            $error_message = 'Phone number is required.';
        } else {
            // Update database
            $stmt_update = $pdo->prepare("
                UPDATE tbl_customer 
                SET cust_name = ?, cust_phone = ?, cust_dob = ?, cust_gender = ?, cust_country = ?, cust_city = ? 
                WHERE cust_id = ?
            ");
            $stmt_update->execute([$full_name, $phone, $dob ?: null, $gender ?: null, $country ?: null, $city ?: null, $cust_id]);

            // Update session
            $_SESSION['customer']['cust_name'] = $full_name;
            $_SESSION['customer']['cust_phone'] = $phone;
            $_SESSION['customer']['cust_dob'] = $dob;
            $_SESSION['customer']['cust_gender'] = $gender;
            $_SESSION['customer']['cust_country'] = $country;
            $_SESSION['customer']['cust_city'] = $city;

            $success_message = 'Your profile has been successfully updated!';
        }
    }
}

// Fetch Fresh Customer Data
$stmt = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ?");
$stmt->execute([$cust_id]);
$cust = $stmt->fetch(PDO::FETCH_ASSOC) ?: $_SESSION['customer'];

// Parse First and Last Name
$name_parts = explode(' ', trim($cust['cust_name'] ?? ''));
$first_name = $name_parts[0] ?? '';
$last_name = isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : '';

// Member since
$member_since = !empty($cust['cust_datetime']) 
    ? date('F Y', strtotime($cust['cust_datetime'])) 
    : 'Recent';

// Profile completion calculation
$fields_to_check = [
    $cust['cust_name'] ?? '',
    $cust['cust_email'] ?? '',
    $cust['cust_phone'] ?? '',
    $cust['cust_dob'] ?? '',
    $cust['cust_gender'] ?? '',
    $cust['cust_country'] ?? '',
    $cust['cust_city'] ?? '',
    $cust['cust_address'] ?? ''
];
$filled_count = 0;
foreach ($fields_to_check as $f) {
    if (!empty($f) && $f !== 'Prefer not to say') {
        $filled_count++;
    }
}
$completion_pct = round(($filled_count / count($fields_to_check)) * 100);
$completion_pct = max(20, min(100, $completion_pct));

// Fetch Countries
$countries = [];
try {
    $stmt_co = $pdo->query("SELECT country_id, country_name FROM tbl_country ORDER BY country_name ASC");
    $countries = $stmt_co->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>

<!-- Portal Modern Stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_portal_modern.css?v=<?= time() ?>">

<div class="sn-portal-wrapper">
    <div class="sn-portal-container">
        <div class="sn-portal-layout">
            <!-- Left Shared Navigation Sidebar -->
            <?php require_once('customer-sidebar.php'); ?>

            <!-- Right Profile Content -->
            <main class="sn-portal-main">
                <!-- Breadcrumbs -->
                <div class="sn-breadcrumb">
                    <a href="<?= BASE_URL ?>index.php">Home</a>
                    <i class="fa-solid fa-chevron-right"></i>
                    <a href="dashboard.php">My Account</a>
                    <i class="fa-solid fa-chevron-right"></i>
                    <span>Profile</span>
                </div>

                <!-- Page Header & Action -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h1 class="sn-portal-title">My Profile</h1>
                        <p class="sn-portal-subtitle">Manage your personal information and account preferences.</p>
                    </div>
                    <button type="button" class="sn-btn-light" onclick="document.getElementById('first_name').focus();" style="border-radius: 8px;">
                        <i class="fa-solid fa-pen" style="color: var(--sn-primary); font-size: 13px;"></i> Edit Profile
                    </button>
                </div>

                <!-- Alerts -->
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

                <!-- Top Profile Completion Card -->
                <div class="sn-profile-top-card">
                    <div class="sn-profile-user-left">
                        <div class="sn-profile-avatar-large">
                            <?= htmlspecialchars(strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) ?: 'CU') ?>
                        </div>
                        <div class="sn-profile-title-block">
                            <h3><?= htmlspecialchars($cust['cust_name'] ?: 'Customer') ?></h3>
                            <p><?= htmlspecialchars($cust['cust_email'] ?: '') ?></p>
                            <span class="sn-verified-pill"><i class="fa-solid fa-circle-check"></i> Verified</span>
                            <div class="sn-profile-member-since" style="margin-top: 6px;">
                                Member since <?= $member_since ?>
                            </div>
                        </div>
                    </div>

                    <div class="sn-profile-progress-box">
                        <div class="sn-progress-label-row">
                            <span style="color: var(--sn-dark);">Profile Completion</span>
                            <span style="color: var(--sn-primary); font-weight: 800; font-size: 14px;"><?= $completion_pct ?>%</span>
                        </div>
                        <div class="sn-progress-bar-bg">
                            <div class="sn-progress-bar-fill" style="width: <?= $completion_pct ?>%;"></div>
                        </div>
                        <div class="sn-progress-tip">
                            Complete your profile to unlock personalized shopping recommendations.
                        </div>
                    </div>
                </div>

                <!-- Personal Information Form Card -->
                <div class="sn-card" style="margin-bottom: 24px;">
                    <div class="sn-form-section-title">
                        <i class="fa-regular fa-user" style="color: var(--sn-primary); font-size: 18px;"></i>
                        <div>
                            <div>Personal Information</div>
                            <small style="font-size: 12px; color: var(--sn-muted); font-weight: 400;">Your basic information</small>
                        </div>
                    </div>

                    <form action="" method="post">
                        <?php $csrf->echoInputField(); ?>

                        <div class="sn-form-grid">
                            <!-- First Name -->
                            <div class="sn-form-group">
                                <label class="sn-label" for="first_name">First Name</label>
                                <div class="sn-input-wrap">
                                    <i class="fa-regular fa-user prefix-icon"></i>
                                    <input type="text" id="first_name" name="first_name" class="sn-input" value="<?= htmlspecialchars($first_name) ?>" required />
                                </div>
                            </div>

                            <!-- Last Name -->
                            <div class="sn-form-group">
                                <label class="sn-label" for="last_name">Last Name</label>
                                <div class="sn-input-wrap">
                                    <i class="fa-regular fa-user prefix-icon"></i>
                                    <input type="text" id="last_name" name="last_name" class="sn-input" value="<?= htmlspecialchars($last_name) ?>" required />
                                </div>
                            </div>

                            <!-- Email Address (Verified) -->
                            <div class="sn-form-group">
                                <label class="sn-label" for="cust_email">Email Address</label>
                                <div class="sn-input-wrap">
                                    <i class="fa-regular fa-envelope prefix-icon"></i>
                                    <input type="email" id="cust_email" name="cust_email" class="sn-input" value="<?= htmlspecialchars($cust['cust_email'] ?: '') ?>" readonly style="background: #f8fafc; cursor: not-allowed; padding-right: 90px;" />
                                    <span class="sn-input-pill-inside"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                </div>
                            </div>

                            <!-- Phone Number -->
                            <div class="sn-form-group">
                                <label class="sn-label" for="cust_phone">Phone Number</label>
                                <div class="sn-input-wrap">
                                    <i class="fa-solid fa-phone prefix-icon"></i>
                                    <input type="text" id="cust_phone" name="cust_phone" class="sn-input" value="<?= htmlspecialchars($cust['cust_phone'] ?: '') ?>" placeholder="e.g. +880 1712 345678" style="padding-right: 80px;" required />
                                    <span class="sn-input-action-inside" onclick="document.getElementById('cust_phone').focus();">
                                        <i class="fa-solid fa-pen"></i> Change
                                    </span>
                                </div>
                            </div>

                            <!-- Date of Birth -->
                            <div class="sn-form-group">
                                <label class="sn-label" for="cust_dob">Date of Birth</label>
                                <div class="sn-input-wrap">
                                    <i class="fa-regular fa-calendar prefix-icon"></i>
                                    <input type="date" id="cust_dob" name="cust_dob" class="sn-input" value="<?= htmlspecialchars($cust['cust_dob'] ?: '') ?>" />
                                </div>
                            </div>

                            <!-- Gender -->
                            <div class="sn-form-group">
                                <label class="sn-label" for="cust_gender">Gender</label>
                                <div class="sn-input-wrap">
                                    <i class="fa-solid fa-venus-mars prefix-icon"></i>
                                    <select id="cust_gender" name="cust_gender" class="sn-select">
                                        <option value="Male" <?= ($cust['cust_gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($cust['cust_gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($cust['cust_gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                        <option value="Prefer not to say" <?= ($cust['cust_gender'] ?? 'Prefer not to say') === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Country -->
                            <div class="sn-form-group">
                                <label class="sn-label" for="cust_country">Country</label>
                                <div class="sn-input-wrap">
                                    <i class="fa-solid fa-globe prefix-icon"></i>
                                    <select id="cust_country" name="cust_country" class="sn-select">
                                        <?php if (!empty($countries)): ?>
                                            <?php foreach ($countries as $c): ?>
                                                <option value="<?= $c['country_id'] ?>" <?= ($cust['cust_country'] == $c['country_id'] || $c['country_name'] === 'Bangladesh') ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['country_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="Bangladesh" selected>Bangladesh</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- City -->
                            <div class="sn-form-group">
                                <label class="sn-label" for="cust_city">City</label>
                                <div class="sn-input-wrap">
                                    <i class="fa-solid fa-location-dot prefix-icon"></i>
                                    <input type="text" id="cust_city" name="cust_city" class="sn-input" value="<?= htmlspecialchars($cust['cust_city'] ?: 'Lalmonirhat') ?>" placeholder="Enter city / district" />
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <button type="submit" name="form1" class="sn-btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> Save Changes
                            </button>
                            <a href="dashboard.php" class="sn-btn-light">Cancel</a>
                        </div>
                    </form>
                </div>

                <!-- Contact Support Bottom Card -->
                <div style="background: linear-gradient(135deg, #f0f7ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; border-radius: 12px; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 44px; height: 44px; border-radius: 50%; background: #ffffff; color: #0284c7; font-size: 18px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.12);">
                            <i class="fa-solid fa-headset"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 2px 0; font-size: 14px; font-weight: 700; color: var(--sn-dark);">Contact Help?</h4>
                            <p style="margin: 0; font-size: 12.5px; color: #475569;">Our support team is here for you 24/7.</p>
                        </div>
                    </div>
                    <a href="contact.php" class="sn-btn-light" style="border-radius: 8px; color: var(--sn-primary); font-size: 12.5px; border-color: #bae6fd;">
                        Contact Support &rarr;
                    </a>
                </div>
            </main>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>