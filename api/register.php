<?php
// ========================================================
// api/register.php - Authenticated Event Registration
// Uses secure PHP Session ($_SESSION['user_id']) & row lock
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

// 1. Verify that the student is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success'       => false,
        'auth_required' => true,
        'message'       => 'Please login or sign in with your email OTP to register for events.'
    ]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$eventId = isset($input['event_id']) ? (int)$input['event_id'] : 0;

if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event selected.']);
    exit;
}

try {
    // 2. Start Atomic Database Transaction
    $pdo->beginTransaction();

    // 3. Duplicate Registration Check: Has this user registered for this event already?
    $dupStmt = $pdo->prepare("SELECT id FROM registrations WHERE event_id = ? AND user_id = ?");
    $dupStmt->execute([$eventId, $userId]);
    if ($dupStmt->fetch()) {
        $pdo->rollBack();
        http_response_code(409); // Conflict
        echo json_encode([
            'success' => false,
            'message' => 'You are already registered for this event!'
        ]);
        exit;
    }

    // 4. Over-Capacity Check with Row-Level Locking (FOR UPDATE)
    $capStmt = $pdo->prepare("
        SELECT 
            e.title,
            e.capacity,
            COUNT(r.id) AS registered_count
        FROM events e
        LEFT JOIN registrations r ON e.id = r.event_id
        WHERE e.id = ?
        GROUP BY e.id
        FOR UPDATE
    ");
    $capStmt->execute([$eventId]);
    $event = $capStmt->fetch();

    if (!$event) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found.']);
        exit;
    }

    $capacity   = (int)$event['capacity'];
    $registered = (int)$event['registered_count'];
    $remaining  = $capacity - $registered;

    if ($remaining <= 0) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Sorry! This event has reached full capacity. No seats remaining.'
        ]);
        exit;
    }

    // 5. Insert New Registration Record linking (event_id, user_id)
    $insertStmt = $pdo->prepare("INSERT INTO registrations (event_id, user_id) VALUES (?, ?)");
    $insertStmt->execute([$eventId, $userId]);
    $registrationId = (int)$pdo->lastInsertId();

    // 6. Commit Transaction (Locks released, data safely written)
    $pdo->commit();

    echo json_encode([
        'success'         => true,
        'message'         => 'Registration confirmed successfully!',
        'registration_id' => $registrationId,
        'event_id'        => $eventId,
        'event_title'     => $event['title'],
        'remaining_seats' => $remaining - 1
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Duplicate registration rejected: You already hold a pass for this event.'
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Registration failed: ' . $e->getMessage()
    ]);
}
