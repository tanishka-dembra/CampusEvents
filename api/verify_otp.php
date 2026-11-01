<?php
// ========================================================
// api/verify_otp.php - Validate OTP & start student session
// Reference: Lab 8 (JSON POST), Lab 10 (PHP Sessions), Extra Lab (SELECT, INSERT)
// ========================================================

require_once __DIR__ . '/db.php';

session_start();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Read JSON input (Lab 8 Manual, Section 5)
$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$email     = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$otpCode   = isset($input['otp_code']) ? trim((string)$input['otp_code']) : '';
$name      = isset($input['name']) ? trim($input['name']) : '';
$studentId = isset($input['student_id']) ? trim($input['student_id']) : '';

if (empty($email) || empty($otpCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and OTP code are required.']);
    exit;
}

try {
    // 1. Verify OTP from email_otps table (Extra Lab Manual, Section 6.2)
    $otpStmt = $pdo->prepare("
        SELECT id 
        FROM email_otps 
        WHERE email = :email AND otp_code = :otp AND is_used = 0 AND expires_at >= NOW()
        ORDER BY id DESC 
        LIMIT 1
    ");
    $otpStmt->execute([':email' => $email, ':otp' => $otpCode]);
    $otpRecord = $otpStmt->fetch();

    if (!$otpRecord) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid or expired OTP code. Please request a new one.'
        ]);
        exit;
    }

    // 2. Mark this OTP as used
    $updateStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE id = :id");
    $updateStmt->execute([':id' => $otpRecord['id']]);

    // 3. Find or Create User in the users table
    $userStmt = $pdo->prepare("SELECT id, name, student_id, email FROM users WHERE email = :email");
    $userStmt->execute([':email' => $email]);
    $user = $userStmt->fetch();

    if (!$user) {
        $assignedName = !empty($name) ? $name : explode('@', $email)[0];
        $assignedId   = !empty($studentId) ? $studentId : 'STU' . rand(1000, 9999);

        $createUserStmt = $pdo->prepare("INSERT INTO users (name, student_id, email) VALUES (:name, :sid, :email)");
        $createUserStmt->execute([
            ':name'  => $assignedName,
            ':sid'   => $assignedId,
            ':email' => $email
        ]);
        $newUserId = (int)$pdo->lastInsertId();

        $user = [
            'id'         => $newUserId,
            'name'       => $assignedName,
            'student_id' => $assignedId,
            'email'      => $email
        ];
    }

    // 4. Save authenticated user in PHP session (Lab 10 Manual, Section 6.2)
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['user_email'] = $user['email'];

    echo json_encode([
        'success' => true,
        'message' => 'Authentication successful! Welcome to CampusEvents.',
        'user'    => $user
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Verification error: ' . $e->getMessage()
    ]);
}
