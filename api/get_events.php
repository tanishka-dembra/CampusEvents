<?php
// ========================================================
// api/get_events.php - Fetch all events with live seat count
// Reference: Lab 8 Manual (JSON exchange) & Extra Lab Manual (JOIN, query, fetchAll)
// ========================================================

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    // Read optional category filter from GET query string (Lab 10 Manual, Section 3)
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';

    // Calculate remaining seats: capacity - COUNT(registrations)
    // Reference: Extra Lab Manual Section 3.1 (Combining Tables with JOIN)
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

    // If category is provided and not 'all', filter with prepared statement
    if (!empty($category) && strtolower($category) !== 'all') {
        $sql .= " WHERE e.category = :category GROUP BY e.id ORDER BY e.event_date ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':category' => $category]);
    } else {
        $sql .= " GROUP BY e.id ORDER BY e.event_date ASC";
        $stmt = $pdo->query($sql);
    }

    $events = $stmt->fetchAll();

    // Send JSON response to browser (Lab 8 Manual, Section 2)
    echo json_encode([
        'success' => true,
        'count'   => count($events),
        'events'  => $events
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to fetch events: ' . $e->getMessage()
    ]);
}
