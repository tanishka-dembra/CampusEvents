<?php
// ========================================================
// api/cancel.php - Securely Cancel Registration
// Only permits the authenticated student to cancel their own pass
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

// 1. Must be authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login first.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$regId  = isset($input['registration_id']) ? (int)$input['registration_id'] : 0;

if ($regId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid registration ID.']);
    exit;
}

try {
    // 2. Strict Check: Does this registration belong to THIS logged-in user?
    $checkStmt = $pdo->prepare("
        SELECT r.id, r.event_id, e.title 
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.id = ? AND r.user_id = ?
    ");
    $checkStmt->execute([$regId, $userId]);
    $registration = $checkStmt->fetch();

    if (!$registration) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Unauthorized or pass not found. You can only cancel your own registrations.'
        ]);
        exit;
    }

    $eventId = (int)$registration['event_id'];

    // 3. Delete the registration record (frees up the seat!)
    $deleteStmt = $pdo->prepare("DELETE FROM registrations WHERE id = ? AND user_id = ?");
    $deleteStmt->execute([$regId, $userId]);

    // 4. Query the newly incremented remaining seats
    $countStmt = $pdo->prepare("
        SELECT (e.capacity - COUNT(r.id)) AS remaining_seats
        FROM events e
        LEFT JOIN registrations r ON e.id = r.event_id
        WHERE e.id = ?
        GROUP BY e.id
    ");
    $countStmt->execute([$eventId]);
    $newRemaining = (int)$countStmt->fetchColumn();

    echo json_encode([
        'success'         => true,
        'message'         => 'Registration cancelled successfully. Your seat has been freed up!',
        'event_id'        => $eventId,
        'event_title'     => $registration['title'],
        'remaining_seats' => $newRemaining
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to cancel registration: ' . $e->getMessage()
    ]);
}
