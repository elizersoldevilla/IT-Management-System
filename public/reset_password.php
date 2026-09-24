<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Invalid reset link.");
}

$stmt = $pdo->prepare("SELECT id, username, full_name, password_reset_expires FROM users WHERE password_reset_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("Invalid or expired reset link.");
}

if (strtotime($user['password_reset_expires']) < time()) {
    die("This reset link has expired. Please request a new one.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!has_strong_password($password)) {
        $error = "Password must be at least 10 characters long and contain uppercase, lowercase, number, and special character.";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
            $stmt->execute([$hashed_password, $user['id']]);

            log_action($pdo, $user['id'], 'PASSWORD_RESET_COMPLETED', "Password reset completed for {$user['username']}");
            $success = "Your password has been reset successfully. You can now <a href='login.php'>login</a>.";
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again.";
            error_log("Password Reset Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - IT Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #64748b;
            --dark-bg: #0f172a;
            --glass-bg: rgba(30, 41, 59, 0.4);
            --glass-border: rgba(255, 255, 255, 0.05);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--dark-bg);
            color: #fff;
            overflow-x: hidden;
            height: 100vh;
            margin: 0;
        }

        #vanta-canvas {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 0;
        }

        .login-panel {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            padding: 3rem;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            z-index: 10;
        }

        .form-floating > .form-control {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid var(--glass-border);
            color: #fff;
            height: 3.5rem;
            border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-floating > .form-control:focus {
            background: rgba(30, 41, 59, 0.8);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1), 0 0 20px rgba(59, 130, 246, 0.2);
            transform: scale(1.02);
        }

        .form-floating > label {
            color: var(--secondary);
        }

        .btn-primary-glow {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: 0;
            padding: 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(59, 130, 246, 0.5);
        }
    </style>
</head>
<body>

<div id="vanta-canvas"></div>

<div class="login-panel">
    <div class="login-card" data-tilt data-tilt-max="5" data-tilt-speed="400" data-tilt-glare data-tilt-max-glare="0.2">
        <div class="text-center mb-5">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 p-3 rounded-circle mb-4 border border-primary border-opacity-25 shadow-[0_0_30px_rgba(59,130,246,0.3)]">
                <i class="fas fa-lock-open fa-2x text-primary"></i>
            </div>
            <h2 class="h3 fw-bold mb-2">Reset Password</h2>
            <p class="text-secondary">Enter your new password below.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center border-0 bg-danger bg-opacity-10 text-danger mb-4 shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center border-0 bg-success bg-opacity-10 text-success mb-4 shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php else: ?>

            <form action="" method="POST">
                <?php csrf_field(); ?>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" placeholder="New Password" required minlength="10">
                    <label for="password">New Password</label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required minlength="10">
                    <label for="confirm_password">Confirm Password</label>
                </div>

                <button type="submit" class="btn btn-primary-glow w-100 mb-4">
                    Reset Password <i class="fas fa-key ms-2 opacity-50"></i>
                </button>
            </form>

        <?php endif; ?>
        
        <div class="mt-5 text-center">
            <p class="text-white-50 small" style="font-size: 0.75rem;">
                &copy; <?php echo date('Y'); ?> IT Management System.
            </p>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        try {
            VANTA.NET({
                el: "#vanta-canvas",
                mouseControls: true,
                touchControls: true,
                gyroControls: false,
                minHeight: 200.00,
                minWidth: 200.00,
                scale: 1.00,
                scaleMobile: 1.00,
                color: 0x3b82f6,
                backgroundColor: 0x0f172a,
                points: 12.00,
                maxDistance: 22.00,
                spacing: 18.00
            })
        } catch (e) {
            console.warn("WebGL not supported or Vanta error", e);
        }
    });
</script>
</body>
</html>
