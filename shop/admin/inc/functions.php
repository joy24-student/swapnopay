<?php
// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Ensure vendor/autoload.php is included if PHPMailer is installed via Composer
// Adjusted path to go up two directories from admin/inc/ to reach the main vendor folder
@require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Saves the current user's cart data from session to the database.
 * Requires $_SESSION['customer']['cust_id'] to be set.
 *
 * @param PDO $pdo The PDO database connection object.
 * @return void
 */
function saveCartToDatabase($pdo, $customer_id, $session_data) {
    try {
        // IMPORTANT: Do NOT delete existing cart items from database!
        // Instead, use INSERT OR UPDATE logic to preserve database cart
        // while syncing any session changes.
        
        // Save current session items using INSERT OR UPDATE (no delete!)
        if (isset($session_data['cart_p_id']) && is_array($session_data['cart_p_id'])) {
            $total_items = count($session_data['cart_p_id']);
            for ($i = 0; $i < $total_items; $i++) {
                // Ensure all necessary cart data exists for the current item
                $product_id = $session_data['cart_p_id'][$i] ?? null;
                $size_id = $session_data['cart_size_id'][$i] ?? null;
                $size_name = $session_data['cart_size_name'][$i] ?? '';
                $color_id = $session_data['cart_color_id'][$i] ?? null;
                $color_name = $session_data['cart_color_name'][$i] ?? '';
                $quantity = $session_data['cart_p_qty'][$i] ?? 0;
                $price_at_add = $session_data['cart_p_current_price'][$i] ?? 0.00;
                $product_name = $session_data['cart_p_name'][$i] ?? '';
                $product_photo = $session_data['cart_p_featured_photo'][$i] ?? null;

                if ($product_id !== null && $quantity > 0) {
                    // Check if item already exists in database
                    $check_stmt = $pdo->prepare("SELECT cart_id FROM tbl_customer_carts 
                        WHERE customer_id = ? AND product_id = ? AND size_id = ? AND color_id = ?");
                    $check_stmt->execute([$customer_id, $product_id, $size_id, $color_id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        // Item exists, update it
                        $update_statement = $pdo->prepare("UPDATE tbl_customer_carts 
                            SET quantity = ?, price_at_add = ?, product_name = ?, product_photo = ?,
                                updated_at = NOW()
                            WHERE customer_id = ? AND product_id = ? AND size_id = ? AND color_id = ?");
                        
                        $update_statement->execute([
                            $quantity, $price_at_add, $product_name, $product_photo,
                            $customer_id, $product_id, $size_id, $color_id
                        ]);
                    } else {
                        // Item doesn't exist, insert it
                        $insert_statement = $pdo->prepare("INSERT INTO tbl_customer_carts (
                            customer_id, product_id, size_id, size_name, color_id, color_name,
                            quantity, price_at_add, product_name, product_photo
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        $insert_statement->execute([
                            $customer_id, $product_id, $size_id, $size_name, $color_id, $color_name,
                            $quantity, $price_at_add, $product_name, $product_photo
                        ]);
                    }
                }
            }
        }
        error_log("Cart saved (without deleting) for customer ID: " . $customer_id);
        return true;
    } catch (PDOException $e) {
        error_log("Error saving cart to database for customer ID " . $customer_id . ": " . $e->getMessage());
        return false;
    }
}

/**
 * Loads cart data from the database into the session.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $customer_id The ID of the customer.
 * @return bool True on success, false on failure.
 */
function loadCartFromDatabase($pdo, $customer_id) {
    try {
        // Clear current session cart data before loading from DB to avoid duplicates
        $_SESSION['cart_p_id'] = [];
        $_SESSION['cart_size_id'] = [];
        $_SESSION['cart_size_name'] = [];
        $_SESSION['cart_color_id'] = [];
        $_SESSION['cart_color_name'] = [];
        $_SESSION['cart_p_qty'] = [];
        $_SESSION['cart_p_current_price'] = [];
        $_SESSION['cart_p_name'] = [];
        $_SESSION['cart_p_featured_photo'] = [];

        $statement = $pdo->prepare("SELECT
            product_id, size_id, size_name, color_id, color_name,
            quantity, price_at_add, product_name, product_photo
            FROM tbl_customer_carts
            WHERE customer_id = ? ORDER BY added_at ASC");
        $statement->execute([$customer_id]);
        $cart_items = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($cart_items)) {
            $index = 1;
            foreach ($cart_items as $item) {
                $_SESSION['cart_p_id'][$index] = $item['product_id'];
                $_SESSION['cart_size_id'][$index] = $item['size_id'];
                $_SESSION['cart_size_name'][$index] = $item['size_name'];
                $_SESSION['cart_color_id'][$index] = $item['color_id'];
                $_SESSION['cart_color_name'][$index] = $item['color_name'];
                $_SESSION['cart_p_qty'][$index] = $item['quantity'];
                $_SESSION['cart_p_current_price'][$index] = $item['price_at_add'];
                $_SESSION['cart_p_name'][$index] = $item['product_name'];
                $_SESSION['cart_p_featured_photo'][$index] = $item['product_photo'];
                $index++;
            }
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error loading cart from database for customer ID " . $customer_id . ": " . $e->getMessage());
        return false;
    }
}

/**
 * Clears all cart items for a customer from the database.
 * Used after successful order placement to ensure the customer's
 * persistent cart is emptied.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $customer_id The customer ID.
 * @return bool True on success, false on failure.
 */
function clearCartFromDatabase($pdo, $customer_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_customer_carts WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error clearing cart from database for customer ID " . $customer_id . ": " . $e->getMessage());
        return false;
    }
}

/**
 * Adds or updates a cart item in the database.
 * Used when adding products to cart in real-time.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $customer_id The customer ID.
 * @param int $product_id The product ID.
 * @param int $size_id The size ID (nullable).
 * @param string $size_name The size name.
 * @param int $color_id The color ID (nullable).
 * @param string $color_name The color name.
 * @param int $quantity The quantity.
 * @param float $price The price.
 * @param string $product_name The product name.
 * @param string $product_photo The product photo.
 * @return bool True on success, false on failure.
 */
function addOrUpdateCartItem($pdo, $customer_id, $product_id, $size_id, $size_name, $color_id, $color_name, $quantity, $price, $product_name, $product_photo) {
    try {
        // Check if item already exists
        $check_stmt = $pdo->prepare("SELECT cart_id, quantity FROM tbl_customer_carts 
            WHERE customer_id = ? AND product_id = ? AND size_id = ? AND color_id = ?");
        $check_stmt->execute([$customer_id, $product_id, $size_id, $color_id]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing item
            $new_quantity = $existing['quantity'] + $quantity;
            $update_stmt = $pdo->prepare("UPDATE tbl_customer_carts 
                SET quantity = ?, updated_at = NOW() 
                WHERE cart_id = ?");
            $update_stmt->execute([$new_quantity, $existing['cart_id']]);
        } else {
            // Insert new item
            $insert_stmt = $pdo->prepare("INSERT INTO tbl_customer_carts (
                customer_id, product_id, size_id, size_name, color_id, color_name,
                quantity, price_at_add, product_name, product_photo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $insert_stmt->execute([
                $customer_id, $product_id, $size_id, $size_name, $color_id, $color_name,
                $quantity, $price, $product_name, $product_photo
            ]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error adding/updating cart item: " . $e->getMessage());
        return false;
    }
}

/**
 * Removes a cart item from the database.
 * Used when deleting products from cart.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $customer_id The customer ID.
 * @param int $product_id The product ID.
 * @param int $size_id The size ID (nullable).
 * @param int $color_id The color ID (nullable).
 * @return bool True on success, false on failure.
 */
function removeCartItem($pdo, $customer_id, $product_id, $size_id, $color_id) {
    try {
        $delete_stmt = $pdo->prepare("DELETE FROM tbl_customer_carts 
            WHERE customer_id = ? AND product_id = ? AND size_id = ? AND color_id = ?");
        $delete_stmt->execute([$customer_id, $product_id, $size_id, $color_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error removing cart item: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates cart item quantity in the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $customer_id The customer ID.
 * @param int $product_id The product ID.
 * @param int $size_id The size ID (nullable).
 * @param int $color_id The color ID (nullable).
 * @param int $quantity The new quantity.
 * @return bool True on success, false on failure.
 */
function updateCartItemQuantity($pdo, $customer_id, $product_id, $size_id, $color_id, $quantity) {
    try {
        if ($quantity <= 0) {
            // Delete if quantity is 0 or less
            return removeCartItem($pdo, $customer_id, $product_id, $size_id, $color_id);
        }
        
        $update_stmt = $pdo->prepare("UPDATE tbl_customer_carts 
            SET quantity = ?, updated_at = NOW() 
            WHERE customer_id = ? AND product_id = ? AND size_id = ? AND color_id = ?");
        $update_stmt->execute([$quantity, $customer_id, $product_id, $size_id, $color_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating cart item quantity: " . $e->getMessage());
        return false;
    }
}
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        $currency_symbol = defined('LANG_VALUE_1') ? LANG_VALUE_1 : '$';
        return $currency_symbol . number_format($amount, 2);
    }
}

/**
 * Sends an SMS message using either SwapnoPay SMS Gateway or Bulk SMS BD.
 *
 * @param string $to The recipient's phone number (e.g., 01XXXXXXXXX, +8801XXXXXXXXX).
 * @param string $message The message content.
 * @param string|null $api_key Optional API Key override.
 * @param string|null $sender_id Optional Sender ID override.
 * @param string|null $provider Optional provider override ('swapnopay' or 'bulk').
 * @return bool True on success, false on failure.
 */
function sendSMS($to, $message, $api_key = null, $sender_id = null, $provider = null) {
    global $pdo;

    if (empty($to) || empty($message)) {
        error_log("[sendSMS] Recipient or message is empty.");
        return false;
    }

    // Clean and normalize phone number
    $phone = preg_replace('/[^\d+]/', '', (string)$to);
    if (str_starts_with($phone, '+880')) {
        $phone = substr($phone, 1);
    } elseif (str_starts_with($phone, '01')) {
        $phone = '88' . $phone;
    }

    // Retrieve settings
    $settings = null;
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1 LIMIT 1");
            $settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        } catch (Throwable $e) {
            error_log("[sendSMS] Failed to read tbl_settings: " . $e->getMessage());
        }
    }
    if (!$settings && !empty($GLOBALS['STORE_SETTINGS'])) {
        $settings = $GLOBALS['STORE_SETTINGS'];
    }

    $activeProvider = $provider ?: (!empty($settings['sms_provider']) ? $settings['sms_provider'] : 'swapnopay');
    $smsEnabled = isset($settings['sms_feature_on_off']) ? (int)$settings['sms_feature_on_off'] : 1;

    if (!$smsEnabled) {
        error_log("[sendSMS] SMS feature is turned off in settings. Skipping dispatch to {$phone}.");
        return false;
    }

    if ($activeProvider === 'swapnopay') {
        $endpoint = !empty($settings['swapnopay_sms_api_url'])
            ? trim($settings['swapnopay_sms_api_url'])
            : 'https://api.swapnopay.top/v1/sms-gateway/send';

        $apiKey = !empty($api_key)
            ? $api_key
            : (!empty($settings['swapnopay_sms_api_key'])
                ? trim($settings['swapnopay_sms_api_key'])
                : ($GLOBALS['runtime']['gateway_api_key'] ?? ''));

        $senderId = !empty($sender_id)
            ? $sender_id
            : (!empty($settings['swapnopay_sms_sender_id'])
                ? trim($settings['swapnopay_sms_sender_id'])
                : ($GLOBALS['runtime']['store_name'] ?? 'SwapnoPay'));

        $deviceId = !empty($settings['swapnopay_sms_device_id'])
            ? trim($settings['swapnopay_sms_device_id'])
            : '';

        $postData = [
            'phone' => $phone,
            'message' => $message,
            'priority' => 'HIGH',
            'sender_id' => $senderId,
            'device_id' => $deviceId
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        if (!empty($apiKey)) {
            $headers[] = 'x-api-key: ' . $apiKey;
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("[sendSMS][SwapnoPay] cURL error: " . $curlErr);
            return false;
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && (!empty($decoded['ok']) || !empty($decoded['success']) || !empty($decoded['job_id']))) {
            error_log("[sendSMS][SwapnoPay] SMS dispatched to {$phone}: Job " . ($decoded['job_id'] ?? 'OK'));
            return true;
        }

        error_log("[sendSMS][SwapnoPay] Failed (HTTP {$httpCode}): " . $response);
        return false;

    } else {
        // Bulk SMS BD (bulksmsbd.net)
        $apiKey = !empty($api_key) ? $api_key : ($settings['sms_api_key'] ?? '');
        $senderId = !empty($sender_id) ? $sender_id : ($settings['sms_sender_id'] ?? '');

        if (empty($apiKey)) {
            error_log("[sendSMS][BulkSMSBD] Missing API key.");
            return false;
        }

        $url = 'https://bulksmsbd.net/api/smsapi';
        $params = [
            'api_key' => $apiKey,
            'type' => 'text',
            'number' => $phone,
            'senderid' => $senderId,
            'message' => $message
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("[sendSMS][BulkSMSBD] cURL error: " . $curlErr);
            return false;
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && (isset($decoded['response_code']) && ($decoded['response_code'] == 202 || $decoded['response_code'] == 200))) {
            error_log("[sendSMS][BulkSMSBD] SMS sent successfully to {$phone}.");
            return true;
        }

        error_log("[sendSMS][BulkSMSBD] Failed (HTTP {$httpCode}): " . $response);
        return false;
    }
}


/**
 * Loads cart data from the database into the session.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $customer_id The ID of the customer.
 * @return bool True on success, false on failure.
 */


/**
 * Sends an email using PHPMailer.
 * Retrieves SMTP settings from tbl_settings.
 *
 * @param string $to_email The recipient's email address.
 * @param string $to_name The recipient's name.
 * @param string $subject The email subject.
 * @param string $message_body The HTML content of the email.
 * @return bool True on success, false on failure.
 */
if (!function_exists('send_email')) {
    function send_email($to_email, $to_name, $subject, $message_body) {
        global $pdo; // Access the PDO object from the global scope

        if (!$pdo) {
            error_log("PDO object not available for send_email function.");
            return false;
        }

        try {
            // Fetch SMTP settings from tbl_settings
            $statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
            $statement->execute();
            $settings_data = $statement->fetch(PDO::FETCH_ASSOC);

            $smtp_host = $settings_data['smtp_host'] ?? '';
            $smtp_username = $settings_data['smtp_username'] ?? '';
            $smtp_password = $settings_data['smtp_password'] ?? '';
            $smtp_encryption = $settings_data['smtp_encryption'] ?? 'NONE'; // 'ssl', 'tls', or 'NONE'
            $smtp_port = $settings_data['smtp_port'] ?? 587;
            $smtp_from_email = $settings_data['smtp_from_email'] ?? 'no-reply@yourdomain.com';
            $smtp_from_name = $settings_data['smtp_from_name'] ?? 'Your Website Name';

            // Initialize PHPMailer
            $mail = new PHPMailer(true); // Enable exceptions

            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;

            if (strtolower($smtp_encryption) === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif (strtolower($smtp_encryption) === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false; // No encryption
            }
            $mail->Port = $smtp_port;

            // Allow self-signed certificates (if needed for localhost/testing, but avoid in production)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $mail->setFrom($smtp_from_email, $smtp_from_name);
            $mail->addAddress($to_email, $to_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message_body;

            $mail->send();
            return true; // Email sent successfully

        } catch (Exception $e) {
            error_log("PHPMailer Error in send_email function: {$mail->ErrorInfo} - {$e->getMessage()}");
            return false; // Email sending failed
        }
    }
}
?>
