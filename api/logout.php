<?php
// ========================================================
// api/logout.php - Terminate student session
// Reference: Lab 10 Manual (Section 6.3 & 7.6 - End the Session)
// ========================================================

session_start();

// Clear all session variables and destroy session
session_unset();
session_destroy();

header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully.'
]);
