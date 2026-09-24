<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('edit_consumable_logs')) {
    redirect('dashboard.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect('consumables_report.php');
}


$stmt = $pdo->prepare("SELECT l.*, c.item_name FROM consumable_logs l JOIN consumables c ON l.consumable_id = c.id WHERE l.id = ?");
$stmt->execute([$id]);
$log = $stmt->fetch();

if (!$log) {
    redirect('consumables_report.php');
}


$users_stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name ASC");
$users = $users_stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $new_quantity = (int)$_POST['quantity'];
    $department = clean_input($_POST['department']);
    $remarks = clean_input($_POST['remarks']);
    $created_at = clean_input($_POST['created_at']);
    $issued_by = (int)$_POST['user_id'];

    if ($created_at) {
        $created_at = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $created_at)));
    } else {
        $created_at = $log['created_at'];
    }

    if ($new_quantity <= 0) {
        $error = "Quantity must be greater than zero.";
    } elseif (!$issued_by) {
        $error = "Please select who issued the consumable.";
    } elseif ($created_at !== $log['created_at']) {
        $error = "Changing the transaction date is not allowed.";
    } elseif ($issued_by != $log['user_id']) {
        $error = "Changing the issued-by user is not allowed.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT l.*, c.item_name FROM consumable_logs l JOIN consumables c ON l.consumable_id = c.id WHERE l.id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $locked_log = $stmt->fetch();

            if (!$locked_log) {
                $pdo->rollBack();
                $error = "Log entry not found or was deleted.";
            } else {
                $old_quantity = $locked_log['quantity'];
                $qty_diff = $old_quantity - $new_quantity;

                $changes = [];
                if ((string)$old_quantity !== (string)$new_quantity) {
                    $changes[] = "Quantity: {$old_quantity} → {$new_quantity}";
                }
                if ($locked_log['department'] !== $department) {
                    $changes[] = "Department: " . ($locked_log['department'] ?: '(none)') . " → " . ($department ?: '(none)');
                }
                if ($locked_log['remarks'] !== $remarks) {
                    $changes[] = "Remarks updated";
                }
                if ($locked_log['created_at'] !== $created_at) {
                    $changes[] = "Date: " . date('M d, Y H:i', strtotime($locked_log['created_at'])) . " → " . date('M d, Y H:i', strtotime($created_at));
                }
                if ((string)$locked_log['user_id'] !== (string)$issued_by) {
                    $old_user = $locked_log['user_id'] ? ('User #' . $locked_log['user_id']) : '(none)';
                    $new_user = 'User #' . $issued_by;
                    $changes[] = "Issued By: {$old_user} → {$new_user}";
                }

                $stmt = $pdo->prepare("UPDATE consumable_logs SET quantity = ?, department = ?, remarks = ?, created_at = ?, user_id = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $department, $remarks, $created_at, $issued_by, $id]);

                
                if ($qty_diff !== 0) {
                    $current_stock = $pdo->query("SELECT quantity FROM consumables WHERE id = {$locked_log['consumable_id']}")->fetchColumn();
                    if ($locked_log['action_type'] === 'DEDUCT') {
                        $new_stock = $current_stock + $qty_diff;
                    } else {
                        $new_stock = $current_stock - $qty_diff;
                    }

                    if ($new_stock < 0) {
                        $pdo->rollBack();
                        $error = "Stock adjustment would result in negative stock. Current: $current_stock, Adjustment: " . ($qty_diff > 0 ? '+' : '') . $qty_diff . ", Result: $new_stock";
                    } else {
                        if ($locked_log['action_type'] === 'DEDUCT') {
                            $stmt = $pdo->prepare("UPDATE consumables SET quantity = quantity + ? WHERE id = ?");
                            $stmt->execute([$qty_diff, $locked_log['consumable_id']]);
                        } elseif ($locked_log['action_type'] === 'ADD') {
                            $stmt = $pdo->prepare("UPDATE consumables SET quantity = quantity - ? WHERE id = ?");
                            $stmt->execute([$qty_diff, $locked_log['consumable_id']]);
                        }
                    }
                }

                $change_details = empty($changes) ? "No field changes (only stock adjustment)" : implode('; ', $changes);
                log_action($pdo, $_SESSION['user_id'], 'UPDATE_CONSUMABLE_LOG', "Updated log #$id for {$locked_log['item_name']}: {$change_details}");
                invalidate_dashboard_cache();

                $pdo->commit();
                $success = "Log entry updated successfully!";
                
                
                $stmt = $pdo->prepare("SELECT l.*, c.item_name FROM consumable_logs l JOIN consumables c ON l.consumable_id = c.id WHERE l.id = ?");
                $stmt->execute([$id]);
                $log = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database Error in consumable_log_edit: " . $e->getMessage());
            $error = "An internal database error occurred. Please try again.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Consumables</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Edit <?php echo ucfirst(strtolower($log['action_type'])); ?> Log</h1>
        <p class="text-white-50 small mb-0"><?php echo htmlspecialchars($log['item_name']); ?> (<?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?>)</p>
    </div>
    <a href="consumables_report.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to Report
    </a>
</div>

<div class="glass-panel p-4" style="max-width: 600px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success mb-4"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Date & Time</label>
                <input type="datetime-local" name="created_at" class="form-control bg-dark text-white border-secondary border-opacity-25" required value="<?php echo date('Y-m-d\TH:i', strtotime($log['created_at'])); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Issued By</label>
                <select name="user_id" class="form-select bg-dark text-white border-secondary border-opacity-25" required>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $log['user_id'] == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo ucfirst($u['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Quantity</label>
                <input type="number" name="quantity" class="form-control bg-dark text-white border-secondary border-opacity-25" required min="1" value="<?php echo $log['quantity']; ?>">
                <div class="form-text text-white-50">Editing this will adjust the current stock.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Department</label>
                <input type="text" name="department" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($log['department']); ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Remarks</label>
                <textarea name="remarks" class="form-control bg-dark text-white border-secondary border-opacity-25" rows="3"><?php echo htmlspecialchars($log['remarks']); ?></textarea>
            </div>
        </div>

        <div class="mt-4 text-end">
            <a href="consumables_report.php" class="btn btn-ghost-secondary me-2">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Update Log</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
