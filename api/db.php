<?php
// ========================================================
// api/db.php - Central Database Connection using PHP PDO
// ========================================================

// Set system timezone to avoid any PHP/MySQL time discrepancy
date_default_timezone_set('Asia/Kolkata');

// Database configuration settings
$host     = 'localhost';
$dbname   = 'campus_events';
$username = 'root';
$password = ''; // Default XAMPP MySQL password is empty
$charset  = 'utf8mb4';


// Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO configuration options for security & error handling
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw PDOException on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch records as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Enforce native prepared statements (prevents SQL Injection)
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // If connection fails, send a structured JSON error response
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// --------------------------------------------------------
// Self-Test: When opened directly in browser, prove it works!
// (When included by other API files, this block is ignored)
// --------------------------------------------------------
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'db.php') {
    header('Content-Type: application/json; charset=UTF-8');
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    $eventCount = $stmt->fetchColumn();

    echo json_encode([
        'success'      => true,
        'message'      => 'Database connected successfully via PHP PDO!',
        'database'     => $dbname,
        'events_found' => (int)$eventCount
    ], JSON_PRETTY_PRINT);
}

