<?php
// ========================================================
// api/cancel.php - Securely Cancel Registration
// Reference: Lab 8 Manual (JSON POST), Lab 10 (Sessions), Extra Lab (DELETE query)
// ========================================================

require_once __DIR__ . '/db.php';

session_start();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// 1. Verify that student is authenticated (Lab 10 Manual, Section 6.2)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login first.']);
    exit;
}

$userId = $_SESSION['user_id'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$regId  = isset($input['registration_id']) ? (int)$input['registration_id'] : 0;

if ($regId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid registration ID.']);
    exit;
}

try {
    // 2. Check ownership: Does this pass belong to the logged-in student?
    $checkStmt = $pdo->prepare("
        SELECT r.id, r.event_id, e.title 
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.id = :id AND r.user_id = :uid
    ");
    $checkStmt->execute([':id' => $regId, ':uid' => $userId]);
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

    // 3. Delete the registration to free up the seat (Extra Lab Manual, Section 3)
    $deleteStmt = $pdo->prepare("DELETE FROM registrations WHERE id = :id AND user_id = :uid");
    $deleteStmt->execute([':id' => $regId, ':uid' => $userId]);

    // 4. Calculate newly updated remaining seats
    $countStmt = $pdo->prepare("
        SELECT (e.capacity - COUNT(r.id)) AS remaining_seats
        FROM events e
        LEFT JOIN registrations r ON e.id = r.event_id
        WHERE e.id = :eid
        GROUP BY e.id
    ");
    $countStmt->execute([':eid' => $eventId]);
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
