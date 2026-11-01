<?php
// ========================================================
// api/my_registrations.php - Fetch active bookings for logged-in student
// Reference: Lab 10 Manual (Sessions) & Extra Lab Manual (JOIN with WHERE)
// ========================================================

require_once __DIR__ . '/db.php';

session_start();

header('Content-Type: application/json; charset=UTF-8');

// 1. Must be authenticated via session (Lab 10 Manual, Section 6.2)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success'       => false,
        'auth_required' => true,
        'message'       => 'Please login with your email OTP to view your active bookings.'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // 2. Fetch all registered events for this student using SQL JOIN (Extra Lab Manual, Section 3.1)
    $stmt = $pdo->prepare("
        SELECT 
            r.id AS registration_id,
            r.registered_at,
            u.name AS student_name,
            u.student_id,
            u.email AS student_email,
            e.id AS event_id,
            e.title,
            e.category,
            e.event_date,
            e.venue
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        JOIN users u ON r.user_id = u.id
        WHERE r.user_id = :user_id
        ORDER BY r.registered_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
    $registrations = $stmt->fetchAll();

    echo json_encode([
        'success'       => true,
        'count'         => count($registrations),
        'registrations' => $registrations
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to fetch bookings: ' . $e->getMessage()
    ]);
}
