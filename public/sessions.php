<?php
require_once __DIR__ . '/../views/header.php';
require_login();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_session_id'])) {
    verify_csrf();
    $session_id = clean_input($_POST['logout_session_id']);
    
    if ($session_id === session_id()) {
        $error = "You cannot log out your own current session from here.";
    } else {
        invalidate_session($pdo, $session_id);
        log_action($pdo, $user_id, 'SESSION_LOGOUT', "Force logged out session: $session_id");
        $success = "Session logged out successfully.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_all_others'])) {
    verify_csrf();
    invalidate_all_other_sessions($pdo, $user_id, session_id());
    log_action($pdo, $user_id, 'SESSION_LOGOUT_ALL', "Logged out all other sessions");
    $success = "All other sessions have been logged out.";
}

$active_sessions = get_active_sessions($pdo, $user_id);
$current_session_id = session_id();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Security</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Active Sessions</h1>
    </div>
</div>

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

<div class="card border-secondary border-opacity-10 bg-dark">
    <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-desktop me-2 text-primary"></i>Your Sessions</h5>
        <?php if (count($active_sessions) > 1): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('This will log out all other sessions. Continue?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="logout_all_others" value="1">
                <button type="submit" class="btn btn-danger btn-sm">Log Out All Other Sessions</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold">Status</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold">IP Address</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold">Device</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold">Last Active</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_sessions as $session): ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <?php if ($session['session_id'] === $current_session_id): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Current</span>
                            <?php else: ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small font-monospace"><?php echo htmlspecialchars($session['ip_address']); ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo htmlspecialchars($session['user_agent']); ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo format_date($session['last_activity']); ?></span>
                        </td>
                        <td class="pe-4 py-3 text-end">
                            <?php if ($session['session_id'] !== $current_session_id): ?>
                                <form method="POST" onsubmit="return confirm('Log out this session?');" style="display:inline;">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="logout_session_id" value="<?php echo htmlspecialchars($session['session_id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger bg-dark border-secondary border-opacity-25">
                                        <i class="fas fa-sign-out-alt me-1"></i> Log Out
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-white-50 small">This session</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($active_sessions)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-desktop fa-3x"></i></div>
                            <h6 class="text-white">No active sessions found</h6>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>