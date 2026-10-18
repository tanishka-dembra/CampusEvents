<?php
// ========================================================
// api/send_otp.php - Generate, store & dispatch real email OTP
// ========================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = isset($input['email']) ? trim($input['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid college email address.']);
    exit;
}

try {
    // 1. Generate a cryptographically secure 6-digit numeric OTP
    $otpCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

    // 2. Invalidate any existing unused OTPs for this email
    $invalidateStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE LOWER(email) = LOWER(?) AND is_used = 0");
    $invalidateStmt->execute([$email]);

    // 3. Insert new OTP record using MySQL internal clock (valid for 10 minutes)
    $insertStmt = $pdo->prepare("
        INSERT INTO email_otps (email, otp_code, expires_at, is_used)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)
    ");
    $insertStmt->execute([$email, $otpCode]);

    // 4. Dispatch real email via Google SMTP (IIT ISM institutional account)
    $mailResult = sendOtpEmail($email, $otpCode);

    if ($mailResult['success']) {
        echo json_encode([
            'success'    => true,
            'message'    => "Verification code sent to $email! Please check your inbox (and Spam folder).",
            'expires_in' => '10 minutes',
            'mail_sent'  => true
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success'    => false,
            'message'    => "Failed to deliver verification email: " . $mailResult['error'],
            'mail_sent'  => false
        ], JSON_PRETTY_PRINT);
    }


} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error while generating OTP: ' . $e->getMessage()
    ]);
}
