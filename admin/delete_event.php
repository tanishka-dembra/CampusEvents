<?php
// ========================================================
// admin/delete_event.php - Delete an Event with Confirmation
// ========================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../api/db.php';

$eventId = (int)($_GET['id'] ?? 0);

// Fetch the event to show its name
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: dashboard.php');
    exit;
}

// Handle confirmed deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Registrations will be auto-deleted due to ON DELETE CASCADE
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$eventId]);

    header('Location: dashboard.php?success=deleted');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Event | CampusConnect Admin</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="admin-navbar">
        <a href="dashboard.php" class="nav-brand">🎓 CampusConnect Admin</a>
        <div class="nav-links">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-container" style="display: flex; justify-content: center; margin-top: 80px;">

        <div class="confirm-card">
            <h2>⚠️ Delete Event?</h2>
            <p>You are about to permanently delete this event:</p>
            <div class="event-name"><?= htmlspecialchars($event['title']) ?></div>
            <p>This will also remove all student registrations for this event.</p>
            <p style="color: #fca5a5; font-weight: 600;">This action cannot be undone.</p>

            <div class="confirm-actions">
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="confirm_delete" value="1">
                    <button type="submit" class="btn btn-danger">🗑️ Yes, Delete</button>
                </form>
                <a href="dashboard.php" class="btn btn-primary">No, Go Back</a>
            </div>
        </div>

    </div>

</body>
</html>
