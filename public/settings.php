<?php
require_once __DIR__ . '/../views/header.php';
require_login();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if (isset($_GET['expired'])) {
    $error = "Your password has expired (90-day policy). Please change it to continue using the system.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();

        if (!password_verify($current_password, $user_data['password'])) {
            $error = 'Incorrect current password.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (!is_password_strong($new_password)) {
            $error = 'Password must be at least 10 characters long and include uppercase, lowercase, numbers, and special characters.';
        } else {
            $hashed_password = hash_password($new_password);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, last_password_change = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user_id])) {
                $success = 'Password changed successfully.';
                log_action($pdo, $user_id, 'CHANGE_PASSWORD', "User changed their password");
            } else {
                $error = 'Failed to update password.';
            }
        }
    }
    
    if (isset($_POST['save_preferences'])) {
        $_SESSION['pref_email_tickets'] = isset($_POST['pref_email_tickets']);
        $_SESSION['pref_email_news'] = isset($_POST['pref_email_news']);
        $success = "Preferences updated successfully.";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold ls-wider">Account</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Settings</h1>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="list-group bg-transparent border-0 stick-top" style="top: 2rem;">
            <a href="#security" class="list-group-item list-group-item-action active bg-primary border-0 rounded-3 mb-2 px-3 py-3 d-flex align-items-center shadow-lg" data-bs-toggle="list">
                <i class="fas fa-shield-alt w-25px"></i>
                <span class="fw-semibold">Security</span>
            </a>
            <a href="#notifications" class="list-group-item list-group-item-action bg-transparent text-secondary border-0 px-3 py-3 d-flex align-items-center hover-text-white" data-bs-toggle="list">
                <i class="fas fa-bell w-25px"></i>
                <span>Notifications</span>
            </a>
            <a href="#appearance" class="list-group-item list-group-item-action bg-transparent text-secondary border-0 px-3 py-3 d-flex align-items-center hover-text-white" data-bs-toggle="list">
                <i class="fas fa-desktop w-25px"></i>
                <span>Appearance</span>
            </a>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="tab-content">
            
            <div class="tab-pane fade show active" id="security">
                <div class="card border-secondary border-opacity-25 mb-4" data-tilt data-tilt-glare data-tilt-max-glare="0.05">
                    <div class="card-header border-secondary border-opacity-25 bg-transparent py-3">
                        <h5 class="mb-0 text-white fw-bold">Password & Security</h5>
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
                            <input type="hidden" name="change_password" value="1">
                            <div class="mb-4">
                                <label class="form-label text-secondary small text-uppercase fw-bold">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small text-uppercase fw-bold">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small text-uppercase fw-bold">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary px-4 shadow-lg">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="notifications">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="save_preferences" value="1">
                    <div class="card border-secondary border-opacity-25 mb-4">
                        <div class="card-header border-secondary border-opacity-25 bg-transparent py-3">
                            <h5 class="mb-0 text-white fw-bold">Email Notifications</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <div class="text-white fw-medium">Ticket Updates</div>
                                    <div class="text-secondary small">Receive emails when your tickets are updated.</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pref_email_tickets" <?php echo ($_SESSION['pref_email_tickets'] ?? true) ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-white fw-medium">System Announcements</div>
                                    <div class="text-secondary small">Receive news about system maintenance.</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pref_email_news" <?php echo ($_SESSION['pref_email_news'] ?? true) ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="mt-4 text-end">
                                <button type="submit" class="btn btn-primary px-4">Save Preferences</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="tab-pane fade" id="appearance">
                <div class="card border-secondary border-opacity-25">
                     <div class="card-header border-secondary border-opacity-25 bg-transparent py-3">
                        <h5 class="mb-0 text-white fw-bold">Appearance</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Theme customization options will be available in a future update. The system currently uses the Cosmic dark theme.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var triggerTabList = [].slice.call(document.querySelectorAll('.list-group-item'))
    triggerTabList.forEach(function (triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl)
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault()
            tabTrigger.show()
            
            // Update active state visuals
            triggerTabList.forEach(el => {
                el.classList.remove('active', 'bg-primary', 'shadow-lg');
                el.classList.add('bg-transparent', 'text-secondary');
            });
            this.classList.add('active', 'bg-primary', 'shadow-lg');
            this.classList.remove('bg-transparent', 'text-secondary');
        })
    })
});
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
