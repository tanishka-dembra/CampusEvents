<?php
// ========================================================
// api/my_registrations.php - Fetch active bookings for logged-in student
// ========================================================

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 1. Must be authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success'       => false,
        'auth_required' => true,
        'message'       => 'Please login with your email OTP to view your active bookings.'
    ]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // 2. Fetch all registered events for this logged-in student
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
        WHERE r.user_id = ?
        ORDER BY r.registered_at DESC
    ");
    $stmt->execute([$userId]);
    $registrations = $stmt->fetchAll();

    foreach ($registrations as &$reg) {
        $reg['registration_id'] = (int)$reg['registration_id'];
        $reg['event_id']        = (int)$reg['event_id'];
    }

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
