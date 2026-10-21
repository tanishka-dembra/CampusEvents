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

    // Simple validation
    if ($formData['title'] === '' || $formData['category'] === '' || $formData['description'] === '' 
        || $formData['event_date'] === '' || $formData['venue'] === '' || $formData['capacity'] === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!is_numeric($formData['capacity']) || (int)$formData['capacity'] < 1) {
        $error = 'Capacity must be a positive number.';
    } else {
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO events (title, category, description, event_date, venue, capacity, banner_image)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $formData['title'],
            $formData['category'],
            $formData['description'],
            $formData['event_date'],
            $formData['venue'],
            (int)$formData['capacity'],
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
    <title>Add Event | CampusConnect Admin</title>
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

    <div class="admin-container">

        <div class="page-header">
            <h1>➕ Add New Event</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="">

                <div class="form-group">
                    <label for="title">Event Title *</label>
                    <input type="text" id="title" name="title" placeholder="e.g. AI Hackathon 2026" required
                           value="<?= htmlspecialchars($formData['title']) ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">Select category</option>
                            <option value="Tech" <?= $formData['category'] === 'Tech' ? 'selected' : '' ?>>Tech</option>
                            <option value="Workshop" <?= $formData['category'] === 'Workshop' ? 'selected' : '' ?>>Workshop</option>
                            <option value="Cultural" <?= $formData['category'] === 'Cultural' ? 'selected' : '' ?>>Cultural</option>
                            <option value="Sports" <?= $formData['category'] === 'Sports' ? 'selected' : '' ?>>Sports</option>
                            <option value="Career" <?= $formData['category'] === 'Career' ? 'selected' : '' ?>>Career</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="event_date">Event Date & Time *</label>
                        <input type="datetime-local" id="event_date" name="event_date" required
                               value="<?= htmlspecialchars($formData['event_date']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" placeholder="Describe the event..." required><?= htmlspecialchars($formData['description']) ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="venue">Venue *</label>
                        <input type="text" id="venue" name="venue" placeholder="e.g. Tech Auditorium A" required
                               value="<?= htmlspecialchars($formData['venue']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="capacity">Capacity *</label>
                        <input type="number" id="capacity" name="capacity" placeholder="e.g. 50" min="1" required
                               value="<?= htmlspecialchars($formData['capacity']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="banner_image">Banner Image Filename (optional)</label>
                    <input type="text" id="banner_image" name="banner_image" placeholder="e.g. hackathon.jpg"
                           value="<?= htmlspecialchars($formData['banner_image']) ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">✅ Create Event</button>
                    <a href="dashboard.php" class="btn btn-danger">Cancel</a>
                </div>

            </form>
        </div>

    </div>

</body>
</html>
