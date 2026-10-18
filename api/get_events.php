<?php
// ========================================================
// api/get_events.php - Fetch all events with live remaining seats
// ========================================================

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Optional category filter: ?category=Tech
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    
    // SQL query dynamically calculating: remaining_seats = capacity - COUNT(registrations)
    $sql = "
        SELECT 
            e.id,
            e.title,
            e.category,
            e.description,
            e.event_date,
            e.venue,
            e.capacity,
            e.banner_image,
            COUNT(r.id) AS registered_count,
            (e.capacity - COUNT(r.id)) AS remaining_seats
        FROM events e
        LEFT JOIN registrations r ON e.id = r.event_id
    ";

    $params = [];
    if (!empty($category) && strtolower($category) !== 'all') {
        $sql .= " WHERE LOWER(e.category) = LOWER(?) ";
        $params[] = $category;
    }

    $sql .= " GROUP BY e.id ORDER BY e.event_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    // Type cast numeric values for clean JSON output
    foreach ($events as &$event) {
        $event['id']               = (int)$event['id'];
        $event['capacity']         = (int)$event['capacity'];
        $event['registered_count'] = (int)$event['registered_count'];
        $event['remaining_seats']  = (int)$event['remaining_seats'];
    }

    echo json_encode([
        'success' => true,
        'count'   => count($events),
        'events'  => $events
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to fetch events: ' . $e->getMessage()
    ]);
}
