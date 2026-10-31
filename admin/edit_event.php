
<?php
// ========================================================
// admin/edit_event.php - Edit an Existing Event
// ========================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../api/db.php';

$error = '';
$eventId = (int) ($_GET['id'] ?? 0);

// Fetch existing event
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: dashboard.php');
    exit;
}

// Get current registration count for this event
$regStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ?");
$regStmt->execute([$eventId]);
$currentRegistrations = (int) $regStmt->fetchColumn();

// Use event data as default form values
$formData = [
    'title'        => $event['title'],
    'category'     => $event['category'],
    'description'  => $event['description'],
    'event_date'   => date('Y-m-d\TH:i', strtotime($event['event_date'])),
    'venue'        => $event['venue'],
    'capacity'     => $event['capacity'],
    'banner_image' => $event['banner_image'] ?? ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'title'        => trim($_POST['title'] ?? ''),
        'category'     => trim($_POST['category'] ?? ''),
        'description'  => trim($_POST['description'] ?? ''),
        'event_date'   => trim($_POST['event_date'] ?? ''),
        'venue'        => trim($_POST['venue'] ?? ''),
        'capacity'     => trim($_POST['capacity'] ?? ''),
        'banner_image' => trim($_POST['banner_image'] ?? '')
    ];

    // Validate required fields
    if (
        $formData['title'] === '' ||
        $formData['category'] === '' ||
        $formData['description'] === '' ||
        $formData['event_date'] === '' ||
        $formData['venue'] === '' ||
        $formData['capacity'] === ''
    ) {
        $error = 'Please fill in all required fields.';
    } elseif (
        !is_numeric($formData['capacity']) ||
        (int) $formData['capacity'] < 1
    ) {
        $error = 'Capacity must be a positive number.';
    } elseif ((int) $formData['capacity'] < $currentRegistrations) {
        $error = "Cannot set capacity to {$formData['capacity']}. There are already {$currentRegistrations} registered student(s).";
    } else {
        // Update event in the database
        $stmt = $pdo->prepare("
            UPDATE events
            SET title = ?, category = ?, description = ?,
                event_date = ?, venue = ?, capacity = ?, banner_image = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $formData['title'],
            $formData['category'],
            $formData['description'],
            $formData['event_date'],
            $formData['venue'],
            (int) $formData['capacity'],
            $formData['banner_image'] ?: null,
            $eventId
        ]);

        header('Location: dashboard.php?success=updated');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Event | CampusEvents Admin</title>
    <link rel="stylesheet" href="admin_style.css">

    <style>
        .form-intro {
            color: var(--admin-muted, #526771);
            font-size: 14px;
            margin-top: 5px;
        }

        .form-card {
            max-width: 850px;
            margin: 0 auto;
        }

        .form-section-title {
            color: var(--admin-text, #17313d);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-section-description {
            color: var(--admin-muted, #526771);
            font-size: 13px;
            margin-bottom: 22px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            min-height: 44px;
        }

        .form-group textarea {
            min-height: 130px;
            line-height: 1.6;
        }

        .required-note {
            color: var(--admin-muted, #526771);
            font-size: 12px;
            margin-bottom: 22px;
        }

        .form-actions {
            border-top: 1px solid var(--admin-border, #dce5e8);
            padding-top: 22px;
            margin-top: 28px;
        }

        .form-actions .btn {
            min-width: 135px;
        }

        .btn-cancel {
            background: #fff;
            color: var(--admin-text, #17313d);
            border: 1px solid var(--admin-border, #dce5e8);
        }

        .btn-cancel:hover {
            background: #f4f7f8;
        }

        .field-hint {
            color: var(--admin-muted, #526771);
            font-size: 12px;
            margin-top: 6px;
        }

        @media (max-width: 650px) {
            .form-card {
                padding: 22px 18px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
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

    <main class="admin-container">

        <!-- Page Header -->
        <header class="page-header">
            <div>
                <h1>Edit Event</h1>
                <p class="form-intro">
                    Update the details for event #<?= $eventId ?>.
                </p>
            </div>
        </header>

        <!-- Validation Error -->
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Edit Event Form -->
        <section class="form-card">

            <h2 class="form-section-title">Event Details</h2>

            <p class="form-section-description">
                Make the necessary changes to the event information below.
            </p>

            <p class="required-note">
                Fields marked with * are required.
            </p>

            <form method="POST" action="">

                <div class="form-group">
                    <label for="title">Event Title *</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        autocomplete="off"
                        placeholder="Enter event title"
                        required
                        value="<?= htmlspecialchars($formData['title']) ?>"
                    >
                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label for="category">Category *</label>

                        <select id="category" name="category" required>
                            <option value="">Select a category</option>

                            <option value="Tech"
                                <?= $formData['category'] === 'Tech' ? 'selected' : '' ?>>
                                Tech
                            </option>

                            <option value="Workshop"
                                <?= $formData['category'] === 'Workshop' ? 'selected' : '' ?>>
                                Workshop
                            </option>

                            <option value="Cultural"
                                <?= $formData['category'] === 'Cultural' ? 'selected' : '' ?>>
                                Cultural
                            </option>

                            <option value="Sports"
                                <?= $formData['category'] === 'Sports' ? 'selected' : '' ?>>
                                Sports
                            </option>

                            <option value="Career"
                                <?= $formData['category'] === 'Career' ? 'selected' : '' ?>>
                                Career
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event_date">Event Date and Time *</label>

                        <input
                            type="datetime-local"
                            id="event_date"
                            name="event_date"
                            required
                            value="<?= htmlspecialchars($formData['event_date']) ?>"
                        >
                    </div>

                </div>

                <div class="form-group">
                    <label for="description">Event Description *</label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe the event and what participants can expect..."
                        required
                    ><?= htmlspecialchars($formData['description']) ?></textarea>
                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label for="venue">Venue *</label>

                        <input
                            type="text"
                            id="venue"
                            name="venue"
                            placeholder="Enter event venue"
                            required
                            value="<?= htmlspecialchars($formData['venue']) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="capacity">Registration Capacity *</label>

                        <input
                            type="number"
                            id="capacity"
                            name="capacity"
                            placeholder="Maximum participants"
                            min="<?= $currentRegistrations > 0 ? $currentRegistrations : 1 ?>"
                            step="1"
                            required
                            value="<?= htmlspecialchars($formData['capacity']) ?>"
                        >

                        <p class="field-hint">
                            <?php if ($currentRegistrations > 0): ?>
                                Minimum <?= $currentRegistrations ?> (<?= $currentRegistrations ?> student(s) already registered).
                            <?php else: ?>
                                Maximum number of participants allowed.
                            <?php endif; ?>
                        </p>
                    </div>

                </div>

                <div class="form-group">
                    <label for="banner_image">Banner Image Filename</label>

                    <input
                        type="text"
                        id="banner_image"
                        name="banner_image"
                        placeholder="e.g. hackathon.jpg"
                        value="<?= htmlspecialchars($formData['banner_image']) ?>"
                    >

                    <p class="field-hint">
                        Optional. Leave blank if no banner image is required.
                    </p>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Save Changes
                    </button>

                    <a href="dashboard.php" class="btn btn-cancel">
                        Cancel
                    </a>
                </div>

            </form>

        </section>

    </main>

</body>
</html>
