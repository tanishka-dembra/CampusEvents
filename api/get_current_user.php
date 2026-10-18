<?php
// ========================================================
// api/get_current_user.php - Check active session status
// ========================================================

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success'   => true,
        'logged_in' => false
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, student_id, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode([
            'success'   => true,
            'logged_in' => true,
            'user'      => [
                'id'         => (int)$user['id'],
                'name'       => $user['name'],
                'student_id' => $user['student_id'],
                'email'      => $user['email']
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        // Stale session
        session_unset();
        session_destroy();
        echo json_encode([
            'success'   => true,
            'logged_in' => false
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
