<?php
// ========================================================
// api/db.php - Central Database Connection
// Reference: Extra Lab Manual (Section 6 - Connecting PHP to MariaDB with PDO)
// ========================================================

$host     = "127.0.0.1";
$dbname   = "campus_events";
$user     = "root";
$password = "";
$dsn      = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    // Create PDO connection instance
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return rows as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false                   // Use native prepared statements
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Could not connect to the database: ' . $e->getMessage()
    ]);
    exit;
}

// Quick self-test when opened directly in browser
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'db.php') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success'  => true,
        'message'  => 'Database connected successfully via PHP PDO!',
        'database' => $dbname
    ], JSON_PRETTY_PRINT);
}
