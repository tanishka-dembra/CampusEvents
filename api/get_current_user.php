<?php
// ========================================================
// api/get_current_user.php - Check active student session
// Reference: Lab 10 Manual (Section 6 - PHP Sessions & $_SESSION)
// ========================================================

require_once __DIR__ . '/db.php';

// Start or resume session (Lab 10 Manual, Section 6.1)
session_start();

header('Content-Type: application/json; charset=UTF-8');

// If no session exists, student is not logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success'   => true,
        'logged_in' => false
    ]);
    exit;
}

try {
    // Read student record using prepared statement (Extra Lab Manual, Section 6.2)
    $stmt = $pdo->prepare("SELECT id, name, student_id, email FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode([
            'success'   => true,
            'logged_in' => true,
            'user'      => $user
        ], JSON_PRETTY_PRINT);
    } else {
        // If session exists but user is missing from DB, clear session (Lab 10 Manual, Section 6.3)
        session_unset();
        session_destroy();
        echo json_encode([
            'success'   => true,
            'logged_in' => false
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
