<?php
require_once __DIR__ . '/../views/header.php';
require_login();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $full_name = clean_input($_POST['full_name']);
    $email = clean_input($_POST['email']);
    
    if (empty($full_name) || empty($email)) {
        $error = 'Name and Email are required.';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Email is already taken.';
        } else {
            $changes = [];
            $old_user = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $old_user->execute([$user_id]);
            $old = $old_user->fetch();

            if ($old && $old['full_name'] !== $full_name) {
                $changes[] = 'Name: ' . ($old['full_name'] ?: '(none)') . ' → ' . ($full_name ?: '(none)');
            }
            if ($old && $old['email'] !== $email) {
                $changes[] = 'Email: ' . ($old['email'] ?: '(none)') . ' → ' . ($email ?: '(none)');
            }

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $email, $user_id])) {
                $_SESSION['full_name'] = $full_name;
                $success = 'Profile updated successfully.';
                $change_details = empty($changes) ? 'No field changes' : implode('; ', $changes);
                log_action($pdo, $user_id, 'UPDATE_PROFILE', "User updated profile details ($change_details)");
            } else {
                $error = 'Failed to update profile.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt_created = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
$stmt_created->execute([$user_id]);
$stmt_resolved = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status = 'resolved'");
$stmt_resolved->execute([$user_id]);
$stmt_last_login = $pdo->prepare("SELECT created_at FROM audit_logs WHERE user_id = ? AND action = 'LOGIN' ORDER BY created_at DESC LIMIT 1");
$stmt_last_login->execute([$user_id]);

$stats = [
    'tickets_created' => $stmt_created->fetchColumn(),
    'tickets_resolved' => $stmt_resolved->fetchColumn(),
    'last_login' => $stmt_last_login->fetchColumn()
];

$stmt_activity = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt_activity->execute([$user_id]);
$activities = $stmt_activity->fetchAll();

$mfa_stmt = $pdo->prepare("SELECT mfa_enabled, mfa_secret FROM staff_details WHERE user_id = ?");
$mfa_stmt->execute([$user_id]);
$mfa = $mfa_stmt->fetch();
$mfa_enabled = $mfa && !empty($mfa['mfa_enabled']);
$mfa_secret = $mfa['mfa_secret'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mfa_action'])) {
    verify_csrf();
    $mfa_action = clean_input($_POST['mfa_action']);

    if ($mfa_action === 'enable') {
        $secret = generate_base32_secret(20);
        $stmt = $pdo->prepare("INSERT INTO staff_details (user_id, mfa_secret, mfa_enabled) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE mfa_secret = ?, mfa_enabled = 0");
        $stmt->execute([$user_id, $secret, $secret]);
        $mfa_secret = $secret;
        $mfa_enabled = false;
        $success = 'MFA secret generated. Enter the code from your authenticator app to enable MFA.';
        log_action($pdo, $user_id, 'MFA_SETUP_STARTED', 'MFA setup started');
    } elseif ($mfa_action === 'verify') {
        $code = clean_input($_POST['mfa_code']);
        if (verify_totp($mfa_secret, $code)) {
            $stmt = $pdo->prepare("UPDATE staff_details SET mfa_enabled = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $mfa_enabled = true;
            $success = 'MFA has been enabled successfully.';
            log_action($pdo, $user_id, 'MFA_ENABLED', 'MFA enabled for account');
        } else {
            $error = 'Invalid MFA code. Please try again.';
        }
    } elseif ($mfa_action === 'disable') {
        $stmt = $pdo->prepare("UPDATE staff_details SET mfa_enabled = 0, mfa_secret = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $mfa_enabled = false;
        $mfa_secret = '';
        $success = 'MFA has been disabled.';
        log_action($pdo, $user_id, 'MFA_DISABLED', 'MFA disabled for account');
    }
}
?>


<div class="position-relative mb-5">
    <div class="profile-cover rounded-4 overflow-hidden" style="height: 200px; background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);">
        <div class="position-absolute w-100 h-100" style="background: url('assets/img/grid.svg') center/cover opacity-20;"></div>
    </div>
    
    <div class="px-4 d-flex align-items-end position-relative" style="margin-top: -60px; z-index: 10;">
        <div class="avatar-circle bg-dark border border-4 border-dark rounded-circle d-flex align-items-center justify-content-center shadow-lg" style="width: 120px; height: 120px; font-size: 3rem;">
            <span class="text-white fw-bold"><?php echo substr($user['full_name'], 0, 1); ?></span>
        </div>
        <div class="ms-4 mb-2">
            <h2 class="h3 fw-bold text-white mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <div class="d-flex align-items-center text-white-50 small gap-3">
                <span><i class="fas fa-user-shield me-1"></i> <?php echo ucfirst($user['role']); ?></span>
                <span><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?></span>
            </div>
        </div>
        <div class="ms-auto mb-3">
            <a href="settings.php" class="btn btn-outline-light rounded-pill btn-sm px-4 backdrop-blur-md">
                <i class="fas fa-cog me-2"></i>Settings
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    
    <div class="col-lg-8">
        
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 bg-dark bg-opacity-50 h-100" data-tilt data-tilt-scale="1.02">
                    <div class="card-body d-flex align-items-center p-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3 text-primary">
                            <i class="fas fa-ticket-alt fa-lg"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0 fw-bold text-white"><?php echo $stats['tickets_created']; ?></div>
                            <div class="small text-secondary">Tickets Created</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-dark bg-opacity-50 h-100" data-tilt data-tilt-scale="1.02">
                    <div class="card-body d-flex align-items-center p-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3 text-success">
                            <i class="fas fa-check-circle fa-lg"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0 fw-bold text-white"><?php echo $stats['tickets_resolved']; ?></div>
                            <div class="small text-secondary">Resolved Issues</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-dark bg-opacity-50 h-100" data-tilt data-tilt-scale="1.02">
                    <div class="card-body d-flex align-items-center p-3">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3 text-info">
                            <i class="fas fa-clock fa-lg"></i>
                        </div>
                        <div>
                            <div class="small fw-bold text-white">Last Login</div>
                            <div class="small text-secondary" style="font-size: 0.75rem;">
                                <?php echo $stats['last_login'] ? format_date($stats['last_login']) : 'Never'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="card border-secondary border-opacity-25" data-tilt data-tilt-glare data-tilt-max-glare="0.1">
            <div class="card-header border-secondary border-opacity-25 bg-transparent py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-user-edit me-2 text-primary"></i>Edit Profile</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small text-uppercase fw-bold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small text-uppercase fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small text-uppercase fw-bold">Username</label>
                            <input type="text" class="form-control bg-dark text-muted" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <div class="form-text text-white-50 small">Username cannot be changed.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small text-uppercase fw-bold">Role</label>
                            <input type="text" class="form-control bg-dark text-muted text-capitalize" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4 shadow-lg hover-scale">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (get_mfa_requirements()): ?>
        <div class="card border-secondary border-opacity-25 mt-4" data-tilt data-tilt-glare data-tilt-max-glare="0.1">
            <div class="card-header border-secondary border-opacity-25 bg-transparent py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-shield-alt me-2 text-warning"></i>Multi-Factor Authentication</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$mfa_enabled && empty($mfa_secret)): ?>
                    <p class="text-secondary small">MFA is not enabled. Generate a secret and scan the QR code with Google Authenticator or Authy.</p>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="mfa_action" value="enable">
                        <button type="submit" class="btn btn-warning px-4">Enable MFA</button>
                    </form>
                <?php elseif (!$mfa_enabled && !empty($mfa_secret)): ?>
                    <p class="text-secondary small mb-2">Scan this QR code or enter the secret manually in your authenticator app.</p>
                    <div class="mb-3">
                        <code class="text-white bg-dark p-2 d-block" style="word-break: break-all;"><?php echo htmlspecialchars($mfa_secret); ?></code>
                    </div>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="mfa_action" value="verify">
                        <div class="row g-3 align-items-end">
                            <div class="col-auto">
                                <label class="form-label text-white small">Enter 6-digit code</label>
                                <input type="text" name="mfa_code" class="form-control" required maxlength="6" pattern="\d{6}">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-success">Verify & Enable</button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="fas fa-check-circle me-2"></i> MFA is enabled for your account.
                    </div>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="mfa_action" value="disable">
                        <button type="submit" class="btn btn-danger">Disable MFA</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    
    <div class="col-lg-4">
        <div class="card border-secondary border-opacity-25 h-100">
            <div class="card-header border-secondary border-opacity-25 bg-transparent py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-history me-2 text-info"></i>Recent Activity</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush rounded-bottom-4">
                    <?php foreach ($activities as $log): ?>
                    <div class="list-group-item bg-transparent border-secondary border-opacity-25 px-4 py-3">
                        <div class="d-flex w-100 justify-content-between mb-1">
                            <h6 class="mb-0 text-white small fw-bold"><?php echo htmlspecialchars($log['action']); ?></h6>
                            <small class="text-secondary" style="font-size: 0.7rem;"><?php echo date('M d H:i', strtotime($log['created_at'])); ?></small>
                        </div>
                        <p class="mb-1 text-secondary small text-truncate"><?php echo htmlspecialchars($log['details']); ?></p>
                        <small class="text-white-50 opacity-50 font-monospace" style="font-size: 0.7rem;">IP: <?php echo htmlspecialchars($log['ip_address']); ?></small>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($activities)): ?>
                        <div class="p-4 text-center text-muted small">No recent activity.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-transparent border-secondary border-opacity-25 text-center py-3">
                <a href="#" class="btn btn-link btn-sm text-decoration-none text-primary">View Audit Logs</a>
            </div>
        </div>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
