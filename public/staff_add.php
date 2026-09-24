<?php
require_once __DIR__ . '/../views/header.php';

require_login();
if (!has_role('admin') && !has_role('supervisor')) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = clean_input($_POST['username']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $full_name = clean_input($_POST['full_name']);
    $role = validate_in($_POST['role'], ['staff', 'technician', 'supervisor', 'admin'], 'staff');
    $position = clean_input($_POST['position']);
    $specialization = clean_input($_POST['specialization']);
    $contact_no = clean_input($_POST['contact_no']);
    $address = clean_input($_POST['address']);
    $privileges = isset($_POST['privileges']) && is_array($_POST['privileges']) ? $_POST['privileges'] : [];
    $privileges_json = json_encode($privileges);


    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "All required fields must be filled.";
    } elseif (!is_password_strong($password)) {
        $error = "Password must be at least 10 characters long and include uppercase, lowercase, numbers, and special characters.";
    } else {
        try {
            $pdo->beginTransaction();


            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, contact_no, address, privileges) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $hashed_password = hash_password($password);
            $stmt->execute([
                $username, 
                $email, 
                $hashed_password, 
                $full_name, 
                $role,
                encrypt_data($contact_no),
                encrypt_data($address),
                $privileges_json
            ]);
            $user_id = $pdo->lastInsertId();


            $stmt = $pdo->prepare("INSERT INTO staff_details (user_id, position, specialization) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $position, $specialization]);

            log_action($pdo, $_SESSION['user_id'], 'CREATE_USER', "Created User: $username ($role)");
            invalidate_dashboard_cache();

            $pdo->commit();
            $success = "Staff member added successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $error = "Username or Email already exists.";
            } else {
                error_log("Database Error in staff_add: " . $e->getMessage());
                $error = "An unexpected error occurred. Please try again.";
            }
        }
    }
}
?>

<div class="mb-4">
    <h1 class="h3 mb-0">Add New Staff</h1>
</div>

<div class="glass-panel p-4" style="max-width: 800px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="bg-danger text-white p-3 rounded mb-4"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-success text-white p-3 rounded mb-4"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="mb-3">
                <label class="block mb-2 font-medium">Full Name *</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Email *</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Username *</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Password *</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Role *</label>
                <select name="role" class="form-control">
                    <option value="staff">Staff (User)</option>
                    <option value="technician">Technician</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Position</label>
                <input type="text" name="position" class="form-control">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Specialization</label>
                <input type="text" name="specialization" class="form-control">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium text-info"><i class="fas fa-shield-alt me-1"></i> Contact No (Encrypted)</label>
                <input type="text" name="contact_no" class="form-control" placeholder="Optional">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium text-info"><i class="fas fa-shield-alt me-1"></i> Residential Address (Encrypted)</label>
                <input type="text" name="address" class="form-control" placeholder="Optional">
            </div>
        </div>
        
        <div class="mt-4 mb-3 p-3 border border-secondary border-opacity-25 rounded bg-darker">
            <h5 class="mb-3 font-medium text-info"><i class="fas fa-key me-2"></i>System Privileges</h5>
            <p class="small text-white-50 mb-3">Select what this user is allowed to access. (Admins bypass these restrictions).</p>
            <div class="row g-3">
                <?php
                $available_privileges = [
                    'view_tickets' => 'View Tickets',
                    'create_tickets' => 'Create Tickets',
                    'manage_assets' => 'Manage IT Assets',
                    'manage_consumables' => 'Manage Consumables',
                    'edit_consumable_logs' => 'Edit Consumable Logs',
                    'view_reports' => 'View Reports & Analytics'
                ];
                foreach ($available_privileges as $key => $label):
                ?>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input bg-dark border-secondary" type="checkbox" name="privileges[]" value="<?php echo $key; ?>" id="priv_<?php echo $key; ?>">
                        <label class="form-check-label text-white-50" for="priv_<?php echo $key; ?>">
                            <?php echo $label; ?>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mt-4 text-right" style="margin-top: 1.5rem; text-align: right;">
            <a href="staff.php" class="btn btn-secondary mr-2" style="margin-right: 0.5rem; background: #6b7280; color: white;">Cancel</a>
            <button type="submit" class="btn btn-primary" id="create-account-btn">Create Account</button>
        </div>

        <script>
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = document.getElementById('create-account-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Creating...';
            }
        });
        </script>
    </form>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
