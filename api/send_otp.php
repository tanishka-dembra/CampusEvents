<?php
// ========================================================
// api/send_otp.php - Generate and dispatch 6-digit email OTP
// Reference: Lab 8 (JSON POST), Lab 9 (PHP random & variables), Extra Lab (INSERT)
// ========================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Read email from JSON POST request (Lab 8 Manual, Section 5)
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = isset($input['email']) ? trim($input['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid college email address.']);
    exit;
}

try {
    // 1. Generate a 6-digit OTP code using standard PHP rand()
    $otpCode = (string)rand(100000, 999999);

    // 2. Mark any previously sent unused OTPs as used
    $invalidateStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE email = :email AND is_used = 0");
    $invalidateStmt->execute([':email' => $email]);

    // 3. Insert new OTP with 10-minute expiry (Extra Lab Manual, Section 5)
    $insertStmt = $pdo->prepare("
        INSERT INTO email_otps (email, otp_code, expires_at, is_used)
        VALUES (:email, :otp, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)
    ");
    $insertStmt->execute([':email' => $email, ':otp' => $otpCode]);

    // 4. Send the verification email using Google SMTP
    $mailResult = sendOtpEmail($email, $otpCode);

    if ($mailResult['success']) {
        echo json_encode([
            'success' => true,
            'message' => "Verification code sent to $email! Please check your inbox (and Spam folder)."
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => "Failed to deliver verification email: " . $mailResult['error']
        ], JSON_PRETTY_PRINT);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error while generating OTP: ' . $e->getMessage()
    ]);
}
