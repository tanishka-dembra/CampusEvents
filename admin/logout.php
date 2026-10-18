<?php
// ========================================================
// admin/logout.php - Destroy admin session and redirect
// ========================================================

session_start();
session_destroy();
header('Location: login.php');
exit;
