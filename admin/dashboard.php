
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
    <title>Manage Events | CampusEvents Admin</title>
    <link rel="stylesheet" href="admin_style.css">

    <style>
        .dashboard-intro {
            color: var(--admin-muted, #526771);
            font-size: 14px;
            margin-top: 5px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 26px;
        }

        .stat-card {
            background: var(--admin-surface, #fff);
            border: 1px solid var(--admin-border, #dce5e8);
            border-radius: 12px;
            padding: 20px 22px;
            box-shadow: var(--admin-shadow, 0 4px 14px rgba(23,49,61,.08));
        }

        .stat-label {
            color: var(--admin-muted, #526771);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-value {
            color: var(--admin-text, #17313d);
            font-size: 28px;
            font-weight: 750;
            line-height: 1.2;
        }

        .table-heading {
            color: var(--admin-text, #17313d);
            font-size: 17px;
            font-weight: 700;
            margin: 0 0 14px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
        }

        .events-table {
            min-width: 850px;
        }

        .event-id {
            color: var(--admin-muted, #526771);
            font-weight: 600;
        }

        .booked-count {
            font-weight: 700;
            color: var(--admin-text, #17313d);
        }

        .empty-state {
            background: var(--admin-surface, #fff);
            border: 1px dashed var(--admin-border, #dce5e8);
            border-radius: 12px;
            padding: 42px 20px;
            text-align: center;
            color: var(--admin-muted, #526771);
        }

        .empty-state h2 {
            color: var(--admin-text, #17313d);
            font-size: 18px;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            margin-bottom: 18px;
        }

        @media (max-width: 650px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 16px 18px;
            }

            .stat-value {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="admin-navbar">
        <a href="dashboard.php" class="nav-brand">CampusEvents Admin</a>

        <div class="nav-links">
            <a href="../index.html">View Website</a>
            <a href="logout.php">
                Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)
            </a>
        </div>
    </nav>

    <main class="admin-container">

        <!-- Page Header -->
        <header class="page-header">
            <div>
                <h1>Manage Events</h1>
                <p class="dashboard-intro">
                    Manage campus events, monitor registrations, and update event details.
                </p>
            </div>

            <a href="add_event.php" class="btn btn-primary">
                + Add New Event
            </a>
        </header>

        <!-- Success Messages -->
        <?php if ($success === 'added'): ?>
            <div class="alert alert-success" role="status">
                Event added successfully!
            </div>
        <?php elseif ($success === 'updated'): ?>
            <div class="alert alert-success" role="status">
                Event updated successfully!
            </div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success" role="status">
                Event deleted successfully!
            </div>
        <?php endif; ?>

        <!-- Dashboard Statistics -->
        <section class="dashboard-stats" aria-label="Event statistics">
            <div class="stat-card">
                <div class="stat-label">Total Events</div>
                <div class="stat-value"><?= count($events) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Registrations</div>
                <div class="stat-value">
                    <?= array_sum(array_map(
                        static fn($event) => (int) $event['registered_count'],
                        $events
                    )) ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Upcoming Events</div>
                <div class="stat-value">
                    <?php
                    $upcomingCount = 0;
                    foreach ($events as $event) {
                        if (strtotime($event['event_date']) >= time()) {
                            $upcomingCount++;
                        }
                    }
                    echo $upcomingCount;
                    ?>
                </div>
            </div>
        </section>

        <!-- Events List -->
        <section aria-label="All campus events">
            <h2 class="table-heading">All Events</h2>

            <?php if (count($events) === 0): ?>

                <div class="empty-state">
                    <h2>No events yet</h2>
                    <p>Create your first campus event to get started.</p>
                    <a href="add_event.php" class="btn btn-primary">
                        + Add New Event
                    </a>
                </div>

            <?php else: ?>

                <div class="table-wrapper">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Event Title</th>
                                <th>Category</th>
                                <th>Date &amp; Time</th>
                                <th>Venue</th>
                                <th>Capacity</th>
                                <th>Booked</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td class="event-id">
                                        <?= (int) $event['id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($event['title']) ?>
                                    </td>

                                    <td>
                                        <span class="category-badge">
                                            <?= htmlspecialchars($event['category']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php
                                        $eventTimestamp = strtotime($event['event_date']);
                                        echo $eventTimestamp !== false
                                            ? date('M d, Y h:i A', $eventTimestamp)
                                            : 'Date unavailable';
                                        ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($event['venue']) ?>
                                    </td>

                                    <td>
                                        <?= (int) $event['capacity'] ?>
                                    </td>

                                    <td class="booked-count">
                                        <?= (int) $event['registered_count'] ?>
                                    </td>

                                    <td>
                                        <div class="actions-cell">
                                            <a
                                                href="view_registrations.php?id=<?= (int) $event['id'] ?>"
                                                class="btn btn-primary btn-sm"
                                            >View</a>

                                            <a
                                                href="edit_event.php?id=<?= (int) $event['id'] ?>"
                                                class="btn btn-warning btn-sm"
                                            >Edit</a>

                                            <a
                                                href="delete_event.php?id=<?= (int) $event['id'] ?>"
                                                class="btn btn-danger btn-sm"
                                            >Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </section>

    </main>

</body>
</html>
