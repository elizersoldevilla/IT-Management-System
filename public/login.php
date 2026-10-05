<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';


if (!is_ip_whitelisted()) {
    http_response_code(403);
    die("Access Denied: This system is restricted to local server access only.");
}

$error = '';


if (isset($_SESSION['_timeout_message'])) {
    $error = $_SESSION['_timeout_message'];
    unset($_SESSION['_timeout_message']);
}

if (is_logged_in() || check_remember_me($pdo)) {
    $_SESSION['show_privacy_notice'] = true;
    if ($_SESSION['role'] === 'staff') {
        redirect('my_tickets.php');
    } else {
        redirect('dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        
        $rate_key = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (is_rate_limited($pdo, $rate_key, 3, 7200)) {
            $error = "Too many login attempts. Please try again in 2 hours.";
        } else {
            $stmt = $pdo->prepare("SELECT id, username, password, role, full_name, status, last_password_change, last_ip, last_user_agent, email, privileges FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (needs_password_rehash($user['password'])) {
                    $new_hash = hash_password($password);
                    $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upd->execute([$new_hash, $user['id']]);
                }

                if ($user['status'] === 'inactive') {
                    $error = "Your account is inactive. Please contact the administrator.";
                } else {
                    
                    $last_change = strtotime($user['last_password_change']);
                    $days_passed = (time() - $last_change) / 86400;
                    
                    if ($days_passed > 90) {
                        $_SESSION['force_password_change'] = true;
                    }

                    
                    $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    
                    if (($user['last_ip'] && $user['last_ip'] !== $current_ip) || 
                        ($user['last_user_agent'] && $user['last_user_agent'] !== $current_ua)) {
                        
                        $subject = "Security Alert: New Login Detected";
                        $body = "Hello {$user['full_name']},\n\nWe detected a login to your account from a new IP address or device.\n\nIP: $current_ip\nDevice: $current_ua\nTime: " . date('Y-m-d H:i:s') . "\n\nIf this wasn't you, please change your password immediately.";
                        send_email($user['email'], $subject, $body);
                        
                        log_action($pdo, $user['id'], 'SECURITY_ALERT', "Login from new IP/Device: $current_ip");
                    }

                    
                    $upd = $pdo->prepare("UPDATE users SET last_ip = ?, last_user_agent = ? WHERE id = ?");
                    $upd->execute([$current_ip, $current_ua, $user['id']]);

                     
                     clear_rate_limit($pdo, $rate_key);

                     
                     $mfa_stmt = $pdo->prepare("SELECT mfa_enabled, mfa_secret FROM staff_details WHERE user_id = ?");
                     $mfa_stmt->execute([$user['id']]);
                     $mfa = $mfa_stmt->fetch();

                     if ($mfa && !empty($mfa['mfa_enabled']) && !empty($mfa['mfa_secret'])) {
                         $_SESSION['mfa_user_id'] = $user['id'];
                         $_SESSION['mfa_username'] = $user['username'];
                         $_SESSION['mfa_role'] = $user['role'];
                         $_SESSION['mfa_full_name'] = $user['full_name'];
                         $_SESSION['mfa_privileges'] = json_decode($user['privileges'] ?? '[]', true) ?: [];
                         $_SESSION['mfa_required'] = true;
                     } else {
                     session_regenerate_id(true);

                     $_SESSION['user_id'] = $user['id'];
                     $_SESSION['username'] = $user['username'];
                     $_SESSION['role'] = $user['role'];
                     $_SESSION['full_name'] = $user['full_name'];
                     $_SESSION['privileges'] = json_decode($user['privileges'] ?? '[]', true) ?: [];
                     $_SESSION['_last_activity'] = time();
                     $_SESSION['show_privacy_notice'] = true;

                     track_session($pdo, $user['id']);

                         if (isset($_SESSION['force_password_change'])) {
                             redirect('settings.php?expired=1');
                         }

                         if ($user['role'] === 'staff') {
                             redirect('my_tickets.php');
                         } else {
                             redirect('dashboard.php');
                         }
                     }
                }
            } else {
                $error = "Invalid username or password.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mfa_code']) && isset($_SESSION['mfa_required']) && $_SESSION['mfa_required'] === true) {
    verify_csrf();
    $mfa_code = clean_input($_POST['mfa_code']);
    
    $mfa_stmt = $pdo->prepare("SELECT mfa_secret FROM staff_details WHERE user_id = ? AND mfa_enabled = 1");
    $mfa_stmt->execute([$_SESSION['mfa_user_id']]);
    $mfa = $mfa_stmt->fetch();

    if ($mfa && verify_totp($mfa['mfa_secret'], $mfa_code)) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $_SESSION['mfa_user_id'];
        $_SESSION['username'] = $_SESSION['mfa_username'];
        $_SESSION['role'] = $_SESSION['mfa_role'];
        $_SESSION['full_name'] = $_SESSION['mfa_full_name'];
        $_SESSION['privileges'] = $_SESSION['mfa_privileges'];
        $_SESSION['_last_activity'] = time();
        $_SESSION['show_privacy_notice'] = true;

        track_session($pdo, $_SESSION['mfa_user_id']);

        unset($_SESSION['mfa_user_id'], $_SESSION['mfa_username'], $_SESSION['mfa_role'], $_SESSION['mfa_full_name'], $_SESSION['mfa_privileges'], $_SESSION['mfa_required']);

        if (isset($_SESSION['force_password_change'])) {
            redirect('settings.php?expired=1');
        }

        if ($_SESSION['role'] === 'staff') {
            redirect('my_tickets.php');
        } else {
            redirect('dashboard.php');
        }
    } else {
        $error = "Invalid MFA code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IT Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .split-layout {
            height: 100vh;
            display: flex;
            position: relative;
            z-index: 1;
        }

        .brand-panel {
            flex: 1.2;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(2px);
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .login-panel {
            flex: 1;
            background: rgba(15, 23, 42, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            backdrop-filter: blur(20px);
            border-left: 1px solid rgba(255, 255, 255, 0.05);
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            background: transparent;
            position: relative;
            z-index: 10;
        }

        .form-floating>.form-control {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid var(--glass-border);
            color: #fff;
            height: 3.5rem;
            border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-floating>.form-control:focus {
            background: rgba(30, 41, 59, 0.8);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1), 0 0 20px rgba(59, 130, 246, 0.2);
            transform: scale(1.02);
        }

        .form-floating>label {
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

        .btn-primary-glow::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: skewX(-25deg);
            transition: 0.5s;
        }

        .btn-primary-glow:hover::after {
            left: 150%;
            transition: 0.7s ease-in-out;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.03);
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }

        .feature-item:hover {
            transform: translateX(10px);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(59, 130, 246, 0.3);
        }

        @media (max-width: 992px) {
            .split-layout {
                flex-direction: column;
            }

            .brand-panel {
                display: none;
            }

            .login-panel {
                flex: 1;
                height: 100%;
            }
        }
    </style>
</head>

<body>

    <div id="vanta-canvas"></div>

    <div class="split-layout">
        <div class="brand-panel">
            <div class="brand-content px-5">
                <div class="mb-5">
                    <div
                        class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 p-3 rounded-4 mb-4 border border-primary border-opacity-25 shadow-[0_0_30px_rgba(59,130,246,0.3)]">
                        <i class="fas fa-cube fa-2x text-primary"></i>
                    </div>
                    <h1 class="display-4 fw-bold mb-3 ls-tight">Manage IT with <br><span
                            class="text-primary text-glowable">Confidence</span></h1>
                    <p class="lead text-secondary mb-0 w-75">Secure enterprise access to your organization's complete IT
                        infrastructure and support systems.</p>
                </div>

                <div class="row g-3">
                    <div class="col-lg-10">
                        <div class="feature-item">
                            <div class="bg-blue-500 bg-opacity-20 p-2 rounded-circle text-primary">
                                <i class="fas fa-shield-alt fa-fw"></i>
                            </div>
                            <div>
                                <h5 class="fw-semibold mb-1">Enterprise Security</h5>
                                <p class="text-secondary small mb-0">Role-based access control and comprehensive audit
                                    logging.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-10">
                        <div class="feature-item">
                            <div class="bg-purple-500 bg-opacity-20 p-2 rounded-circle text-info">
                                <i class="fas fa-bolt fa-fw"></i>
                            </div>
                            <div>
                                <h5 class="fw-semibold mb-1">Real-time Operations</h5>
                                <p class="text-secondary small mb-0">Instant ticket updates, asset tracking, and live
                                    monitoring.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-auto pt-5">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex position-relative">
                            <img src="https://ui-avatars.com/api/?name=Admin&background=0D8ABC&color=fff"
                                class="rounded-circle border border-2 border-dark" width="40" alt="">
                            <img src="https://ui-avatars.com/api/?name=Staff&background=6366f1&color=fff"
                                class="rounded-circle border border-2 border-dark" width="40" alt=""
                                style="margin-left: -10px">
                            <img src="https://ui-avatars.com/api/?name=User&background=10b981&color=fff"
                                class="rounded-circle border border-2 border-dark" width="40" alt=""
                                style="margin-left: -10px">
                        </div>
                        <div class="text-secondary small">
                            <strong class="text-white d-block">Trusted by IT Teams</strong>
                            Streamlining operations daily
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-panel">
            <div class="login-card" data-tilt data-tilt-max="5" data-tilt-speed="400" data-tilt-glare
                data-tilt-max-glare="0.2">
                <div class="text-center mb-5">
                    <h2 class="h3 fw-bold mb-2">Welcome Back</h2>
                    <p class="text-secondary">Please sign in to your dashboard</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center border-0 bg-danger bg-opacity-10 text-danger mb-4 shadow-sm"
                        role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="form-floating mb-4">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username"
                            required autocomplete="off">
                        <label for="username">Username</label>
                    </div>

                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password"
                            required>
                        <label for="password">Password</label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input bg-dark border-secondary" type="checkbox" id="remember"
                                name="remember">
                            <label class="form-check-label text-secondary small" for="remember">
                                Remember me
                            </label>
                        </div>
                        <a href="forgot_password.php" class="text-primary text-decoration-none small fw-medium">Forgot
                            password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary-glow w-100 mb-4">
                        Sign In to Dashboard <i class="fas fa-arrow-right ms-2 opacity-50"></i>
                    </button>

                    <div class="text-center">
                        <p class="text-secondary small mb-0">Don't have an account? <a href="#"
                                class="text-white text-decoration-none fw-medium">Contact Admin</a></p>
                    </div>
                </form>

                <?php if (isset($_SESSION['mfa_required']) && $_SESSION['mfa_required'] === true): ?>
                    <form action="" method="POST" class="mt-4">
                        <?php csrf_field(); ?>
                        <div class="form-floating mb-4">
                            <input type="text" class="form-control" id="mfa_code" name="mfa_code" placeholder="123456" required maxlength="6" pattern="\d{6}">
                            <label for="mfa_code">MFA Code</label>
                        </div>
                        <button type="submit" class="btn btn-primary-glow w-100 mb-4">
                            Verify Code <i class="fas fa-shield-alt ms-2 opacity-50"></i>
                        </button>
                    </form>
                <?php endif; ?>

                <div class="mt-4 text-center">
                    <p class="text-white-50 small mb-0">Forgotten your password? A verification code will be emailed
                        to the address on your account.</p>
                </div>

                <div class="mt-5 text-center">
                    <p class="text-white-50 small" style="font-size: 0.75rem;">
                        &copy; <?php echo date('Y'); ?> IT Management System. <br>Secure Access Encrypted.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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