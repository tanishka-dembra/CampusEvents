<?php
// ========================================================
// admin/logout.php - Destroy admin session and redirect
// ========================================================

session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
