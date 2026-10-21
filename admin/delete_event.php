
<?php
// ========================================================
// admin/delete_event.php - Delete an Event with Confirmation
// ========================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../api/db.php';

$eventId = (int) ($_GET['id'] ?? 0);

// Fetch the event to show its name
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: dashboard.php');
    exit;
}

// Handle confirmed deletion
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['confirm_delete'])
) {
    // Registrations are deleted through ON DELETE CASCADE
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

    <title>Delete Event | CampusEvents Admin</title>
    <link rel="stylesheet" href="admin_style.css">

    <style>
        .delete-page {
            display: flex;
            justify-content: center;
            padding: 54px 0;
        }

        .confirm-card {
            width: 100%;
            max-width: 520px;
            background: var(--admin-surface, #fff);
            border: 1px solid var(--admin-border, #dce5e8);
            border-radius: 16px;
            padding: 34px;
            text-align: center;
            box-shadow: var(--admin-shadow, 0 4px 14px rgba(23,49,61,.08));
        }

        .warning-mark {
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 28px;
            font-weight: 700;
        }

        .confirm-card h1 {
            color: var(--admin-text, #17313d);
            font-size: 23px;
            font-weight: 750;
            margin-bottom: 12px;
        }

        .confirm-description {
            color: var(--admin-muted, #526771);
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        .event-name {
            background: #f4f7f8;
            border: 1px solid var(--admin-border, #dce5e8);
            border-radius: 10px;
            padding: 15px;
            margin: 18px 0;
            color: var(--admin-text, #17313d);
            font-size: 17px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .delete-warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 9px;
            padding: 13px 15px;
            margin: 20px 0 24px;
            color: #9a3412;
            font-size: 13px;
            line-height: 1.6;
            text-align: left;
        }

        .confirm-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .confirm-actions form {
            margin: 0;
        }

        .btn-delete {
            background: #b91c1c;
            color: #fff;
            border: 1px solid #b91c1c;
        }

        .btn-delete:hover {
            background: #991b1b;
        }

        .btn-cancel {
            background: #fff;
            color: var(--admin-text, #17313d);
            border: 1px solid var(--admin-border, #dce5e8);
        }

        .btn-cancel:hover {
            background: #f4f7f8;
        }

        @media (max-width: 480px) {
            .delete-page {
                padding: 24px 0;
            }

            .confirm-card {
                padding: 26px 20px;
            }

            .confirm-actions {
                flex-direction: column;
            }

            .confirm-actions form,
            .confirm-actions .btn,
            .confirm-actions button {
                width: 100%;
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

    <main class="admin-container delete-page">

        <section class="confirm-card" aria-labelledby="delete-heading">

            <div class="warning-mark" aria-hidden="true">!</div>

            <h1 id="delete-heading">Delete Event?</h1>

            <p class="confirm-description">
                You are about to permanently delete the following event.
                Please review the details before continuing.
            </p>

            <div class="event-name">
                <?= htmlspecialchars($event['title']) ?>
            </div>

            <div class="delete-warning">
                <strong>This action cannot be undone.</strong>
                Deleting this event will also remove its student registrations
                if the database's cascade-delete relationship is configured.
            </div>

            <div class="confirm-actions">

                <form method="POST" action="">
                    <input
                        type="hidden"
                        name="confirm_delete"
                        value="1"
                    >

                    <button type="submit" class="btn btn-delete">
                        Yes, Delete Event
                    </button>
                </form>

                <a href="dashboard.php" class="btn btn-cancel">
                    Cancel
                </a>

            </div>

        </section>

    </main>

</body>
</html>