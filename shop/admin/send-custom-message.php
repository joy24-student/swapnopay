<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sendType = (string)($_POST['send_type'] ?? 'email');
    $recipient = trim((string)($_POST['recipient'] ?? ''));
    $recipientName = trim((string)($_POST['recipient_name'] ?? 'Customer'));
    $subject = trim((string)($_POST['subject'] ?? 'Notification from ShopNext'));
    $message = trim((string)($_POST['message'] ?? ''));
    $redirect = (string)($_POST['redirect'] ?? 'order.php');

    // Prevent open redirect security vulnerability
    if (!str_contains($redirect, '.php') || str_contains($redirect, '://')) {
        $redirect = 'order.php';
    }

    if (empty($recipient) || empty($message)) {
        $_SESSION['order_error'] = 'Recipient and message content are required.';
        header('Location: ' . $redirect);
        exit;
    }

    try {
        if ($sendType === 'sms') {
            $sent = sendSMS($recipient, $message);
            if ($sent) {
                $_SESSION['order_notice'] = 'Custom SMS sent successfully to ' . htmlspecialchars($recipient);
            } else {
                $_SESSION['order_error'] = 'Unable to send SMS. Please verify your SMS gateway settings.';
            }
        } else {
            // HTML formatted email body
            $emailBody = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #1e293b; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;'>
                    <h3 style='color: #0f172a; margin-top: 0;'>" . htmlspecialchars($subject) . "</h3>
                    <p>Dear <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                    <div style='background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid #cbd5e1; margin: 15px 0; line-height: 1.6;'>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                    <p style='font-size: 12px; color: #64748b;'>This email was sent by the store administrator.</p>
                </div>
            ";

            $sent = send_email($recipient, $recipientName, $subject, $emailBody);
            if ($sent) {
                $_SESSION['order_notice'] = 'Custom Email sent successfully to ' . htmlspecialchars($recipient);
            } else {
                $_SESSION['order_error'] = 'Unable to send Email. Please check your SMTP settings in admin settings.';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['order_error'] = 'Error sending message: ' . $e->getMessage();
    }

    header('Location: ' . $redirect);
    exit;
}

header('Location: order.php');
exit;
