<?php
// ========================================================
// admin/auth_check.php - Admin Session Guard
// Include this at the top of every admin page to block
// unauthorized access. Redirects to login if not logged in.
// ========================================================

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
