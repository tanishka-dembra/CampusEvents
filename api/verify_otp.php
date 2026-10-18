<?php
// ========================================================
// api/verify_otp.php - Validate OTP & establish student session
// ========================================================

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email     = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$otpCode   = isset($input['otp_code']) ? trim(str_replace([' ', '-'], '', (string)$input['otp_code'])) : '';
$name      = isset($input['name']) ? trim($input['name']) : '';
$studentId = isset($input['student_id']) ? trim($input['student_id']) : '';


if (empty($email) || empty($otpCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and OTP code are required.']);
    exit;
}

try {
    // 1. Verify OTP code against email_otps table
    $otpStmt = $pdo->prepare("
        SELECT id, expires_at 
        FROM email_otps 
        WHERE LOWER(email) = ? AND otp_code = ? AND is_used = 0 AND expires_at >= NOW()
        ORDER BY id DESC 
        LIMIT 1
    ");
    $otpStmt->execute([$email, $otpCode]);
    $otpRecord = $otpStmt->fetch();

    if (!$otpRecord) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid or expired OTP code. Please request a new one.'
        ]);
        exit;
    }

    // 2. Mark this OTP as consumed/used
    $updateStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
    $updateStmt->execute([$otpRecord['id']]);

    // 3. Find or Create User in the `users` table
    $userStmt = $pdo->prepare("SELECT id, name, student_id, email FROM users WHERE LOWER(email) = ?");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();

    if (!$user) {
        // First-time user registration
        $assignedName = !empty($name) ? $name : explode('@', $email)[0];
        $assignedId   = !empty($studentId) ? $studentId : 'STU' . random_int(1000, 9999);

        $createUserStmt = $pdo->prepare("
            INSERT INTO users (name, student_id, email)
            VALUES (?, ?, ?)
        ");
        $createUserStmt->execute([$assignedName, $assignedId, $email]);
        $newUserId = (int)$pdo->lastInsertId();

        $user = [
            'id'         => $newUserId,
            'name'       => $assignedName,
            'student_id' => $assignedId,
            'email'      => $email
        ];
    }

    // 4. Bind Authenticated Student to PHP Session
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['user_email'] = $user['email'];

    echo json_encode([
        'success' => true,
        'message' => 'Authentication successful! Welcome to CampusEvents.',
        'user'    => [
            'id'         => (int)$user['id'],
            'name'       => $user['name'],
            'student_id' => $user['student_id'],
            'email'      => $user['email']
        ]
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Verification error: ' . $e->getMessage()
    ]);
}
