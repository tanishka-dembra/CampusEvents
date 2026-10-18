<?php
// ========================================================
// admin/dashboard.php - Admin Dashboard (Placeholder)
// Will be built in Feature 2
// ========================================================

require_once __DIR__ . '/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CampusConnect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #0b0f19;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 20px;
        }
        h1 { font-size: 28px; }
        p { color: #94a3b8; }
        a {
            color: #6366f1;
            text-decoration: none;
            font-size: 14px;
        }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>✅ Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?>!</h1>
    <p>Admin dashboard coming in Feature 2.</p>
    <a href="logout.php">Logout →</a>
</body>
</html>
