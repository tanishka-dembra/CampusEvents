<?php
// ========================================================
// admin/view_registrations.php - View Students Registered for an Event
// ========================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../api/db.php';

$eventId = (int)($_GET['id'] ?? 0);

// Fetch the event
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: dashboard.php');
    exit;
}

// Fetch all registrations for this event with student details
$stmt = $pdo->prepare("
    SELECT r.id AS registration_id, r.registered_at, 
           u.name, u.student_id, u.email
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    WHERE r.event_id = ?
    ORDER BY r.registered_at ASC
");
$stmt->execute([$eventId]);
$registrations = $stmt->fetchAll();

$remaining = $event['capacity'] - count($registrations);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrations - <?= htmlspecialchars($event['title']) ?> | Admin</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .event-info-card {
            background-color: #1a2234;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .event-info-card .info-left h2 {
            font-size: 20px;
            margin-bottom: 6px;
        }
        .event-info-card .info-left p {
            color: #94a3b8;
            font-size: 14px;
        }
        .stats-boxes {
            display: flex;
            gap: 15px;
        }
        .stat-box {
            background-color: #111827;
            border-radius: 8px;
            padding: 12px 20px;
            text-align: center;
        }
        .stat-box .stat-num {
            font-size: 24px;
            font-weight: 700;
        }
        .stat-box .stat-label {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }
        .stat-num.green { color: #22c55e; }
        .stat-num.blue { color: #38bdf8; }
        .stat-num.orange { color: #f59e0b; }

        .empty-state {
            text-align: center;
            color: #94a3b8;
            padding: 50px 20px;
            font-size: 15px;
        }
    </style>
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

    <div class="admin-container">

        <!-- Event Info Card -->
        <div class="event-info-card">
            <div class="info-left">
                <h2><?= htmlspecialchars($event['title']) ?></h2>
                <p>📅 <?= date('M d, Y h:i A', strtotime($event['event_date'])) ?> &nbsp;|&nbsp; 📍 <?= htmlspecialchars($event['venue']) ?></p>
            </div>
            <div class="stats-boxes">
                <div class="stat-box">
                    <div class="stat-num blue"><?= $event['capacity'] ?></div>
                    <div class="stat-label">Capacity</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num orange"><?= count($registrations) ?></div>
                    <div class="stat-label">Registered</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num green"><?= $remaining ?></div>
                    <div class="stat-label">Remaining</div>
                </div>
            </div>
        </div>

        <!-- Registrations Table -->
        <?php if (count($registrations) === 0): ?>
            <div class="empty-state">
                No students have registered for this event yet.
            </div>
        <?php else: ?>
            <table class="events-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Email</th>
                        <th>Registered At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $i => $reg): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($reg['name']) ?></td>
                            <td><?= htmlspecialchars($reg['student_id']) ?></td>
                            <td><?= htmlspecialchars($reg['email']) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($reg['registered_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

</body>
</html>
