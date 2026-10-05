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
$email = '';

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $code  = trim($_POST['verification_code'] ?? '');

    // Every rejected path below shares ONE message so this page cannot be used
    // to probe which codes exist, are expired, or belong to another account.
    $generic_error = "That verification code is not valid. Please check the code we emailed you and try again.";

    // Brute-force gate: a 6-digit code is guessable well within 15 minutes.
    $rate_key = 'verify_email_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (is_rate_limited($pdo, $rate_key, 5, 900)) {
        $error = "Too many attempts. Please wait 15 minutes and try again.";
    } elseif ($email === '' || $code === '') {
        $error = "Please enter your email address and the verification code.";
    } else {
        try {
            // Match on the stored hash so the index does the work.
            $stmt = $pdo->prepare("SELECT prr.id, prr.user_id, prr.status, prr.verification_expires,
                                          u.email, u.username, u.full_name
                                   FROM password_reset_requests prr
                                   JOIN users u ON u.id = prr.user_id
                                   WHERE prr.status = 'approved'
                                     AND prr.verification_code_hash = ?
                                     AND u.status = 'active'
                                   LIMIT 1");
            $stmt->execute([hash('sha256', $code)]);
            $row = $stmt->fetch();

            if (!$row) {
                // Unknown code, already-used code, or code belonging to an inactive user.
                $error = $generic_error;
            } elseif (strtotime($row['verification_expires']) < time()) {
                $pdo->prepare("UPDATE password_reset_requests SET status = 'expired' WHERE id = ?")->execute([$row['id']]);
                $error = "That verification code has expired. Please request a new one from the password reset page.";
            } elseif (!hash_equals(strtolower($row['email']), strtolower($email))) {
                // Real code, wrong account: indistinguishable from the generic case.
                $error = $generic_error;
            } else {
                $raw_token = mint_password_reset_token($pdo, $row['user_id']);

                $pdo->prepare("UPDATE password_reset_requests
                               SET status = 'email_verified', email_verified_at = NOW()
                               WHERE id = ?")->execute([$row['id']]);

                log_action($pdo, $row['user_id'], 'PASSWORD_RESET_EMAIL_VERIFIED', "Email verified for password reset: {$row['username']}");

                // Hand the raw token over in the session so it never touches a URL,
                // browser history, or the Apache access log.
                $_SESSION['verified_reset_token'] = $raw_token;
                $_SESSION['verified_reset_user'] = $row['username'];
                redirect('reset_password.php');
            }
        } catch (Exception $e) {
            error_log("Password Reset Verify Error: " . $e->getMessage());
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - IT Management System</title>
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
                <i class="fas fa-shield-alt fa-2x text-primary"></i>
            </div>
            <h2 class="h3 fw-bold mb-2">Verify Your Email</h2>
            <p class="text-secondary">Enter the 6-digit verification code that was emailed to you when you requested a password reset.</p>
            <p class="text-secondary small mt-2">The code expires in 15 minutes. If you do not have a code, request a new one from the password reset page.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center border-0 bg-danger bg-opacity-10 text-danger mb-4 shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div><?php echo esc($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center border-0 bg-success bg-opacity-10 text-success mb-4 shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?php echo esc($success); ?></div>
            </div>
            <div class="text-center mt-4">
                <a href="login.php" class="btn btn-outline-light w-100 py-3 rounded-3 fw-medium">
                    <i class="fas fa-arrow-left me-2"></i> Return to Login
                </a>
            </div>
        <?php else: ?>

            <form action="" method="POST">
                <?php csrf_field(); ?>
                <div class="form-floating mb-4">
                    <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required autocomplete="email" value="<?php echo esc($email); ?>">
                    <label for="email">Email Address</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="text" class="form-control" id="verification_code" name="verification_code" placeholder="Verification Code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" style="letter-spacing: 0.5em;">
                    <label for="verification_code">Verification Code</label>
                </div>

                <button type="submit" class="btn btn-primary-glow w-100 mb-4">
                    Verify Email <i class="fas fa-shield-alt ms-2 opacity-50"></i>
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