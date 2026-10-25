
<?php
// ========================================================
// admin/view_registrations.php - View Students Registered for an Event
// ========================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../api/db.php';

$eventId = (int) ($_GET['id'] ?? 0);

// Fetch the event
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: dashboard.php');
    exit;
}

// Fetch registrations with student details
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

$capacity = (int) $event['capacity'];
$registeredCount = count($registrations);
$remaining = max(0, $capacity - $registeredCount);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registrations | CampusEvents Admin</title>
    <link rel="stylesheet" href="admin_style.css">

    <style>
        .page-intro {
            color: var(--admin-muted, #526771);
            font-size: 14px;
            margin-top: 5px;
        }

        .event-info-card {
            background: var(--admin-surface, #fff);
            border: 1px solid var(--admin-border, #dce5e8);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            box-shadow: var(--admin-shadow, 0 4px 14px rgba(23,49,61,.08));
        }

        .info-left {
            flex: 1;
            min-width: 220px;
        }

        .event-category {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            background: #e6f2f0;
            color: #0f766e;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .info-left h2 {
            color: var(--admin-text, #17313d);
            font-size: 21px;
            line-height: 1.4;
            margin-bottom: 8px;
            overflow-wrap: anywhere;
        }

        .event-detail {
            color: var(--admin-muted, #526771);
            font-size: 13px;
            line-height: 1.8;
        }

        .stats-boxes {
            display: grid;
            grid-template-columns: repeat(3, minmax(95px, 1fr));
            gap: 12px;
        }

        .stat-box {
            min-width: 95px;
            padding: 16px 18px;
            text-align: center;
            border: 1px solid var(--admin-border, #dce5e8);
            border-radius: 10px;
            background: #f8fbfb;
        }

        .stat-num {
            font-size: 25px;
            font-weight: 750;
            line-height: 1.3;
            color: var(--admin-text, #17313d);
        }

        .stat-num.teal {
            color: #0f766e;
        }

        .stat-num.orange {
            color: #b45309;
        }

        .stat-num.green {
            color: #15803d;
        }

        .stat-label {
            color: var(--admin-muted, #526771);
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .section-heading h2 {
            color: var(--admin-text, #17313d);
            font-size: 18px;
            font-weight: 700;
        }

        .registration-count {
            color: var(--admin-muted, #526771);
            font-size: 13px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
        }

        .events-table {
            min-width: 760px;
        }

        .student-number {
            color: var(--admin-muted, #526771);
            font-weight: 600;
        }

        .student-name {
            color: var(--admin-text, #17313d);
            font-weight: 600;
        }

        .student-email {
            color: #0f766e;
            overflow-wrap: anywhere;
        }

        .empty-state {
            background: var(--admin-surface, #fff);
            border: 1px dashed var(--admin-border, #dce5e8);
            border-radius: 12px;
            padding: 42px 20px;
            text-align: center;
        }

        .empty-state h3 {
            color: var(--admin-text, #17313d);
            font-size: 17px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--admin-muted, #526771);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        @media (max-width: 760px) {
            .event-info-card {
                align-items: stretch;
                padding: 20px;
            }

            .stats-boxes {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
            }

            .stat-box {
                min-width: 0;
                padding: 13px 8px;
            }

            .stat-num {
                font-size: 22px;
            }
        }

        @media (max-width: 480px) {
            .stats-boxes {
                grid-template-columns: 1fr;
            }

            .stat-box {
                display: flex;
                align-items: center;
                justify-content: space-between;
                text-align: left;
                padding: 12px 16px;
            }

            .stat-label {
                margin-top: 0;
            }

            .info-left h2 {
                font-size: 19px;
            }
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <nav class="admin-navbar">
        <a href="dashboard.php" class="nav-brand">
            CampusEvents Admin
        </a>

        <div class="nav-links">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <main class="admin-container">

        <!-- Page Header -->
        <header class="page-header">
            <div>
                <h1>Event Registrations</h1>
                <p class="page-intro">
                    View student details and registration totals for this event.
                </p>
            </div>
        </header>

        <!-- Event Details and Statistics -->
        <section class="event-info-card" aria-label="Event summary">

            <div class="info-left">
                <span class="event-category">
                    <?= htmlspecialchars($event['category']) ?>
                </span>

                <h2><?= htmlspecialchars($event['title']) ?></h2>

                <p class="event-detail">
                    <strong>Date:</strong>
                    <?php
                    $eventTimestamp = strtotime($event['event_date']);
                    echo $eventTimestamp !== false
                        ? date('M d, Y h:i A', $eventTimestamp)
                        : 'Date unavailable';
                    ?>
                    <br>

                    <strong>Venue:</strong>
                    <?= htmlspecialchars($event['venue']) ?>
                </p>
            </div>

            <div class="stats-boxes">

                <div class="stat-box">
                    <div class="stat-num teal">
                        <?= $capacity ?>
                    </div>
                    <div class="stat-label">Capacity</div>
                </div>

                <div class="stat-box">
                    <div class="stat-num orange">
                        <?= $registeredCount ?>
                    </div>
                    <div class="stat-label">Registered</div>
                </div>

                <div class="stat-box">
                    <div class="stat-num green">
                        <?= $remaining ?>
                    </div>
                    <div class="stat-label">Seats Remaining</div>
                </div>

            </div>
        </section>

        <!-- Registration List -->
        <section aria-label="Registered students">

            <div class="section-heading">
                <h2>Registered Students</h2>

                <span class="registration-count">
                    <?= $registeredCount ?>
                    <?= $registeredCount === 1 ? 'student' : 'students' ?>
                </span>
            </div>

            <?php if ($registeredCount === 0): ?>

                <div class="empty-state">
                    <h3>No registrations yet</h3>
                    <p>
                        Students who register for this event will appear here.
                    </p>

                    <a href="dashboard.php" class="btn btn-primary">
                        Back to Events
                    </a>
                </div>

            <?php else: ?>

                <div class="table-wrapper">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Email Address</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($registrations as $i => $reg): ?>
                                <tr>
                                    <td class="student-number">
                                        <?= $i + 1 ?>
                                    </td>

                                    <td class="student-name">
                                        <?= htmlspecialchars($reg['name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reg['student_id']) ?>
                                    </td>

                                    <td class="student-email">
                                        <?= htmlspecialchars($reg['email']) ?>
                                    </td>

                                    <td>
                                        <?php
                                        $registeredTimestamp = strtotime($reg['registered_at']);
                                        echo $registeredTimestamp !== false
                                            ? date('M d, Y h:i A', $registeredTimestamp)
                                            : 'Date unavailable';
                                        ?>
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
