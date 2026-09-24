<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';


if (!is_ip_whitelisted()) {
    http_response_code(403);
    die("Access Denied: This system is restricted to local server access only.");
}

$error = '';
$success = '';

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = clean_input($_POST['username_or_email']);
    
    if (empty($input)) {
        $error = "Please enter your username or email.";
    } else {
        
        $rate_key = 'forgot_pw_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (is_rate_limited($pdo, $rate_key, 3, 900)) {
            $error = "Too many requests. Please try again in 15 minutes.";
        } else {
            
            $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE username = :input OR email = :input");
            $stmt->execute(['input' => $input]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);

                $stmt = $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);

                $reset_link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/IT%20Management%20System/public/reset_password.php?token=' . $token;
                $subject = "Password Reset Request";
                $body = "Hello {$user['full_name']},\n\nClick the link below to reset your password:\n\n$reset_link\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.";

                send_email($user['email'], $subject, $body);
                log_action($pdo, $user['id'], 'PASSWORD_RESET_REQUESTED', "Password reset requested for {$user['username']}");
            }
            
            $success = "If an account matches that username or email, a password reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - IT Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanta/0.5.21/vanta.net.min.js"></script>
    
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
            <h2 class="h3 fw-bold mb-2">Forgot Password?</h2>
            <p class="text-secondary">Enter your username or email to request a reset.</p>
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
            <div class="text-center mt-4">
                <a href="login.php" class="btn btn-outline-light w-100 py-3 rounded-3 fw-medium">
                    <i class="fas fa-arrow-left me-2"></i> Return to Login
                </a>
            </div>
        <?php else: ?>

            <form action="" method="POST">
                <div class="form-floating mb-4">
                    <input type="text" class="form-control" id="username_or_email" name="username_or_email" placeholder="Username or Email" required autocomplete="off">
                    <label for="username_or_email">Username or Email</label>
                </div>

                <button type="submit" class="btn btn-primary-glow w-100 mb-4">
                    Submit Request <i class="fas fa-paper-plane ms-2 opacity-50"></i>
                </button>

                <div class="text-center">
                    <a href="login.php" class="text-secondary text-decoration-none small fw-medium hover-text-white">
                        <i class="fas fa-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            </form>

        <?php endif; ?>
        
        <div class="mt-5 text-center">
            <p class="text-white-50 small" style="font-size: 0.75rem;">
                &copy; <?php echo date('Y'); ?> IT Management System.
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Init 3D Background - NET Effect
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
