
<?php
// ========================================================
// admin/add_event.php - Add a New Event
// ========================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../api/db.php';

$error = '';

$formData = [
    'title' => '',
    'category' => '',
    'description' => '',
    'event_date' => '',
    'venue' => '',
    'capacity' => '',
    'banner_image' => ''
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
    } else {
        // Insert event into the database
        $stmt = $pdo->prepare("
            INSERT INTO events (
                title, category, description, event_date,
                venue, capacity, banner_image
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $formData['title'],
            $formData['category'],
            $formData['description'],
            $formData['event_date'],
            $formData['venue'],
            (int) $formData['capacity'],
            $formData['banner_image'] ?: null
        ]);

        header('Location: dashboard.php?success=added');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Event | CampusEvents Admin</title>

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
            margin: 0 0 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--admin-border, #dce5e8);
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
            border-color: var(--admin-border, #dce5e8);
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

    <!-- Navigation Bar -->
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
                <h1>Add New Event</h1>
                <p class="form-intro">
                    Create and publish an event for your campus community.
                </p>
            </div>
        </header>

        <!-- Validation Error -->
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Event Form -->
        <section class="form-card">

            <h2 class="form-section-title">Event Details</h2>

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
                        placeholder="e.g. AI Hackathon 2026"
                        maxlength="255"
                        autocomplete="off"
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
                        placeholder="Describe the event, its purpose, and what attendees can expect..."
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
                            placeholder="e.g. Tech Auditorium A"
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
                            placeholder="e.g. 100"
                            min="1"
                            step="1"
                            required
                            value="<?= htmlspecialchars($formData['capacity']) ?>"
                        >

                        <p class="field-hint">
                            Maximum number of participants allowed.
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
                        Optional. Enter the image filename used by your existing event system.
                    </p>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Create Event
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
