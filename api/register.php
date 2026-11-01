<?php
// ========================================================
// api/register.php - Event Registration
// Reference: Lab 8 (JSON POST), Lab 10 (Sessions), Extra Lab (INSERT & 1062 Duplicate Check)
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
    echo json_encode([
        'success'       => false,
        'auth_required' => true,
        'message'       => 'Please login or sign in with your email OTP to register for events.'
    ]);
    exit;
}

$userId  = $_SESSION['user_id'];
$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$eventId = isset($input['event_id']) ? (int)$input['event_id'] : 0;

if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event selected.']);
    exit;
}

try {
    // 2. Check for duplicate registration before inserting
    $dupStmt = $pdo->prepare("SELECT id FROM registrations WHERE event_id = :eid AND user_id = :uid");
    $dupStmt->execute([':eid' => $eventId, ':uid' => $userId]);
    if ($dupStmt->fetch()) {
        http_response_code(409); // Conflict status for duplicate
        echo json_encode([
            'success' => false,
            'message' => 'You are already registered for this event!'
        ]);
        exit;
    }

    // 3. Check live capacity and calculate remaining seats (Extra Lab Manual, Section 3.1)
    $capStmt = $pdo->prepare("
        SELECT 
            e.title,
            e.capacity,
            COUNT(r.id) AS registered_count
        FROM events e
        LEFT JOIN registrations r ON e.id = r.event_id
        WHERE e.id = :eid
        GROUP BY e.id
    ");
    $capStmt->execute([':eid' => $eventId]);
    $event = $capStmt->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found.']);
        exit;
    }

    $capacity   = (int)$event['capacity'];
    $registered = (int)$event['registered_count'];
    $remaining  = $capacity - $registered;

    if ($remaining <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Sorry! This event has reached full capacity. No seats remaining.'
        ]);
        exit;
    }

    // 4. Insert registration record (Extra Lab Manual, Section 7.2)
    $insertStmt = $pdo->prepare("INSERT INTO registrations (event_id, user_id) VALUES (:eid, :uid)");
    $insertStmt->execute([':eid' => $eventId, ':uid' => $userId]);
    $registrationId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success'         => true,
        'message'         => 'Registration confirmed successfully!',
        'registration_id' => $registrationId,
        'event_id'        => $eventId,
        'event_title'     => $event['title'],
        'remaining_seats' => $remaining - 1
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    // Check MariaDB duplicate key error code 1062 (Extra Lab Manual, Section 7.2)
    if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
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
