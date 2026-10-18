<?php
// ========================================================
// admin/dashboard.php - Admin Event Management Dashboard
// Lists all events with Add / Edit / Delete actions
// ========================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../api/db.php';

// Fetch all events with registration counts
$stmt = $pdo->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registered_count
    FROM events e 
    ORDER BY e.event_date ASC
");
$events = $stmt->fetchAll();

// Check for success message from add/edit/delete
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CampusConnect</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="admin-navbar">
        <a href="dashboard.php" class="nav-brand">🎓 CampusConnect Admin</a>
        <div class="nav-links">
            <a href="../index.html">← View Site</a>
            <a href="logout.php">Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)</a>
        </div>
    </nav>

    <div class="admin-container">

        <!-- Page Header -->
        <div class="page-header">
            <h1>📋 Manage Events</h1>
            <a href="add_event.php" class="btn btn-primary">+ Add New Event</a>
        </div>

        <!-- Success Alert -->
        <?php if ($success === 'added'): ?>
            <div class="alert alert-success">✅ Event added successfully!</div>
        <?php elseif ($success === 'updated'): ?>
            <div class="alert alert-success">✅ Event updated successfully!</div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success">✅ Event deleted successfully!</div>
        <?php endif; ?>

        <!-- Events Table -->
        <?php if (count($events) === 0): ?>
            <p style="color: #94a3b8; text-align: center; margin-top: 60px;">No events found. Click "Add New Event" to create one.</p>
        <?php else: ?>
            <table class="events-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Capacity</th>
                        <th>Booked</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= $event['id'] ?></td>
                            <td><?= htmlspecialchars($event['title']) ?></td>
                            <td><span class="category-badge"><?= htmlspecialchars($event['category']) ?></span></td>
                            <td><?= date('M d, Y h:i A', strtotime($event['event_date'])) ?></td>
                            <td><?= htmlspecialchars($event['venue']) ?></td>
                            <td><?= $event['capacity'] ?></td>
                            <td><?= $event['registered_count'] ?></td>
                            <td>
                                <div class="actions-cell">
                                    <a href="edit_event.php?id=<?= $event['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="delete_event.php?id=<?= $event['id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

</body>
</html>
