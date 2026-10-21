
<?php
// ========================================================
// admin/login.php - Admin Login Page
// Simple form + PHP handler on the same page
// ========================================================

session_start();

// If already logged in, go to dashboard
if (
    isset($_SESSION['admin_logged_in']) &&
    $_SESSION['admin_logged_in'] === true
) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$inputUser = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../api/db.php';

    $inputUser = trim($_POST['username'] ?? '');
    $inputPass = trim($_POST['password'] ?? '');

    if ($inputUser === '' || $inputPass === '') {
        $error = 'Please enter both username and password.';
    } else {
        // Look up admin by username
        $stmt = $pdo->prepare(
            "SELECT * FROM admins WHERE username = ?"
        );
        $stmt->execute([$inputUser]);
        $admin = $stmt->fetch();

        if (
            $admin &&
            password_verify($inputPass, $admin['password_hash'])
        ) {
            // Login successful
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | CampusEvents</title>

    <style>
        :root {
            --page-bg: #f4f7f8;
            --surface: #ffffff;
            --border: #dce5e8;
            --text: #17313d;
            --muted: #526771;
            --primary: #0f766e;
            --primary-hover: #115e59;
            --focus-ring: rgba(15, 118, 110, 0.14);
            --shadow: 0 16px 40px rgba(23, 49, 61, 0.09);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
                Roboto, sans-serif;
            background: var(--page-bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 40px;
            box-shadow: var(--shadow);
        }

        .brand {
            display: inline-block;
            color: var(--primary);
            font-size: 12px;
            font-weight: 750;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .login-header {
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: var(--text);
            font-size: 28px;
            font-weight: 750;
            letter-spacing: -0.7px;
            margin-bottom: 9px;
        }

        .login-header p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 21px;
        }

        .form-group label {
            display: block;
            color: var(--text);
            font-size: 13px;
            font-weight: 650;
            margin-bottom: 8px;
        }

        .form-group input {
            display: block;
            width: 100%;
            min-height: 46px;
            padding: 12px 14px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 9px;
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input::placeholder {
            color: #8a9aa1;
        }

        .form-group input:hover {
            border-color: #b8c9ce;
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--focus-ring);
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 22px;
        }

        .btn-login {
            width: 100%;
            min-height: 47px;
            padding: 12px 18px;
            background: var(--primary);
            color: #ffffff;
            border: 1px solid var(--primary);
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s,
                box-shadow 0.2s;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.17);
            transform: translateY(-1px);
        }

        .btn-login:focus-visible,
        .back-link:focus-visible {
            outline: 3px solid rgba(15, 118, 110, 0.3);
            outline-offset: 3px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary);
        }

        .login-footer {
            border-top: 1px solid #edf1f2;
            margin-top: 28px;
            padding-top: 18px;
            color: #71838b;
            text-align: center;
            font-size: 11px;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .login-container {
                padding: 28px 22px;
                border-radius: 14px;
            }

            .login-header h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>
    <main class="login-container">

        <div class="brand">CampusEvents</div>

        <header class="login-header">
            <h1>Admin Login</h1>
            <p>
                Sign in to manage campus events and registrations.
            </p>
        </header>

        <?php if ($error): ?>
            <div class="error-message" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter admin username"
                    autocomplete="username"
                    required
                    value="<?= htmlspecialchars($inputUser) ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">
                Sign In
            </button>
        </form>

        <a href="../index.html" class="back-link">
            Back to Event Portal
        </a>

        <footer class="login-footer">
            CampusEvents Admin Portal
        </footer>

    </main>
</body>
</html>
