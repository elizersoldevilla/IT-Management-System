<?php
require_once __DIR__ . '/../views/header.php';

require_login();
if (!has_role('admin') && !has_role('supervisor')) {
    redirect('dashboard.php');
}

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('staff.php');
}


$stmt = $pdo->prepare("SELECT u.*, s.position, s.specialization, s.skills, s.schedule FROM users u LEFT JOIN staff_details s ON u.id = s.user_id WHERE u.id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('staff.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $full_name = clean_input($_POST['full_name']);
    $email = clean_input($_POST['email']);
    $role = validate_in($_POST['role'], ['staff', 'technician', 'supervisor', 'admin'], 'staff');
    $status = validate_in($_POST['status'], ['active', 'inactive'], 'active');
    $position = clean_input($_POST['position']);
    $specialization = clean_input($_POST['specialization']);
    $skills = clean_input($_POST['skills']);
    $schedule = clean_input($_POST['schedule']);
    $contact_no = clean_input($_POST['contact_no']);
    $address = clean_input($_POST['address']);
    $privileges = isset($_POST['privileges']) && is_array($_POST['privileges']) ? $_POST['privileges'] : [];
    $privileges_json = json_encode($privileges);

    $password_sql = "";
    $main_params = [
        $full_name, 
        $email, 
        $role, 
        $status,
        encrypt_data($contact_no),
        encrypt_data($address),
        $privileges_json
    ];
    
    if (!empty($_POST['password'])) {
        if (!is_password_strong($_POST['password'])) {
            $error = "New password must be at least 10 characters long and include uppercase, lowercase, numbers, and special characters.";
        } else {
            $password_sql = ", password = ?, last_password_change = CURRENT_TIMESTAMP";
            $main_params[] = hash_password($_POST['password']);
        }
    }

    $main_params[] = $id;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM users u LEFT JOIN staff_details s ON u.id = s.user_id WHERE u.id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $locked_user = $stmt->fetch();

        if (!$locked_user) {
            $pdo->rollBack();
            $error = "User not found.";
        } else {
            $changes = [];
            $user_fields = [
                'full_name' => $full_name,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'privileges' => implode(', ', $privileges)
            ];
            foreach ($user_fields as $field => $new_val) {
                $old_val = $locked_user[$field] ?? '';
                if ($field === 'privileges') {
                    $old_val = is_array($old_val) ? implode(', ', $old_val) : ($old_val ?: '');
                }
                if ((string)$old_val !== (string)$new_val) {
                    $changes[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . ($old_val ?: '(none)') . ' → ' . ($new_val ?: '(none)');
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, status = ?, contact_no = ?, address = ?, privileges = ? $password_sql WHERE id = ?");
            $stmt->execute($main_params);

            $staff_fields = ['position' => $position, 'specialization' => $specialization, 'skills' => $skills, 'schedule' => $schedule];
            foreach ($staff_fields as $field => $new_val) {
                $old_val = $locked_user[$field] ?? '';
                if ((string)$old_val !== (string)$new_val) {
                    $changes[] = ucfirst($field) . ': ' . ($old_val ?: '(none)') . ' → ' . ($new_val ?: '(none)');
                }
            }

            $check = $pdo->prepare("SELECT user_id FROM staff_details WHERE user_id = ?");
            $check->execute([$id]);
            
            if ($check->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE staff_details SET position = ?, specialization = ?, skills = ?, schedule = ? WHERE user_id = ?");
                $stmt->execute([$position, $specialization, $skills, $schedule, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO staff_details (user_id, position, specialization, skills, schedule) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id, $position, $specialization, $skills, $schedule]);
            }

            $change_details = empty($changes) ? 'No field changes' : implode('; ', $changes);
            log_action($pdo, $_SESSION['user_id'], 'UPDATE_USER', "Updated User: {$user['username']} ($role) ($change_details)");
            invalidate_dashboard_cache();

            $pdo->commit();
            $success = "Staff member updated successfully!";
            

            $stmt = $pdo->prepare("SELECT u.*, s.position, s.specialization, s.skills, s.schedule FROM users u LEFT JOIN staff_details s ON u.id = s.user_id WHERE u.id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database Error in staff_edit: " . $e->getMessage());
        $error = "An unexpected error occurred. Please try again.";
    }
}
?>

<div class="mb-4">
    <h1 class="h3 mb-0">Edit Staff Member</h1>
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
                <label class="block mb-2 font-medium">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Username</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">New Password (Leave blank to keep current)</label>
                <input type="password" name="password" class="form-control">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Role</label>
                <select name="role" class="form-control">
                    <option value="staff" <?php echo $user['role'] == 'staff' ? 'selected' : ''; ?>>Staff (User)</option>
                    <option value="technician" <?php echo $user['role'] == 'technician' ? 'selected' : ''; ?>>Technician</option>
                    <option value="supervisor" <?php echo $user['role'] == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Position</label>
                <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Specialization</label>
                <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($user['specialization'] ?? ''); ?>">
            </div>
            <div class="mb-3" style="grid-column: span 2;">
                <label class="block mb-2 font-medium">Skills</label>
                <textarea name="skills" class="form-control" rows="2"><?php echo htmlspecialchars($user['skills'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3" style="grid-column: span 2;">
                <label class="block mb-2 font-medium">Schedule</label>
                <textarea name="schedule" class="form-control" rows="2"><?php echo htmlspecialchars($user['schedule'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium text-info"><i class="fas fa-shield-alt me-1"></i> Contact No (Encrypted)</label>
                <input type="text" name="contact_no" class="form-control" value="<?php echo htmlspecialchars(decrypt_data($user['contact_no'] ?? '')); ?>" placeholder="Optional">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium text-info"><i class="fas fa-shield-alt me-1"></i> Residential Address (Encrypted)</label>
                <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars(decrypt_data($user['address'] ?? '')); ?>" placeholder="Optional">
            </div>
        </div>
        
        <?php $user_privs = json_decode($user['privileges'] ?? '[]', true) ?: []; ?>
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
                    $checked = in_array($key, $user_privs) ? 'checked' : '';
                ?>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input bg-dark border-secondary" type="checkbox" name="privileges[]" value="<?php echo $key; ?>" id="priv_<?php echo $key; ?>" <?php echo $checked; ?>>
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
            <button type="submit" class="btn btn-primary">Update Staff</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
