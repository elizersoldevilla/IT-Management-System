<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('manage_consumables')) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$id = $_GET['id'] ?? null;

if (!$id) {
    redirect('consumables.php');
}

$stmt = $pdo->prepare("SELECT * FROM consumables WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    redirect('consumables.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $item_name = clean_input($_POST['item_name']);
    $category = validate_in($_POST['category'], ['Ink', 'Cartridge', 'Toner', 'Drum'], 'Ink');
    $brand = clean_input($_POST['brand']);
    $model_compatibility = clean_input($_POST['model_compatibility']);
    $quantity = (int)$_POST['quantity'];
    $min_quantity = (int)$_POST['min_quantity'];
    $unit = clean_input($_POST['unit']);
    $unit_cost = !empty($_POST['unit_cost']) ? (float) $_POST['unit_cost'] : null;
    $location = clean_input($_POST['location']);
    $remarks = clean_input($_POST['remarks']);

    if (empty($item_name) || empty($category)) {
        $error = "Item Name and Category are required.";
    } else {
        try {
            $changes = [];
            $fields = ['item_name', 'category', 'brand', 'model_compatibility', 'quantity', 'min_quantity', 'unit', 'unit_cost', 'location', 'remarks'];
            foreach ($fields as $field) {
                $old = $item[$field] ?? '';
                $new = ${$field} ?? '';
                if ((string)$old !== (string)$new) {
                    $changes[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . ($old ?: '(none)') . ' → ' . ($new ?: '(none)');
                }
            }

            $stmt = $pdo->prepare("UPDATE consumables SET item_name = ?, category = ?, brand = ?, model_compatibility = ?, quantity = ?, min_quantity = ?, unit = ?, unit_cost = ?, location = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$item_name, $category, $brand, $model_compatibility, $quantity, $min_quantity, $unit, $unit_cost, $location, $remarks, $id]);
            $success = "Consumable updated successfully!";
            
            $change_details = empty($changes) ? 'No field changes' : implode('; ', $changes);
            log_action($pdo, $_SESSION['user_id'], 'UPDATE_CONSUMABLE', "Updated Consumable: $item_name ($change_details)");
            invalidate_dashboard_cache();
            
            
            $stmt = $pdo->prepare("SELECT * FROM consumables WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database Error in consumables_edit: " . $e->getMessage());
            $error = "An internal database error occurred. Please try again.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Consumables</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Edit Consumable</h1>
        <p class="text-white-50 small mb-0"><?php echo htmlspecialchars($item['item_name']); ?></p>
    </div>
    <a href="consumables.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to List
    </a>
</div>

<div class="glass-panel p-4" style="max-width: 800px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success mb-4"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        <div class="row g-4">
            <div class="col-md-8">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Item Name *</label>
                <input type="text" name="item_name" class="form-control bg-dark text-white border-secondary border-opacity-25" required value="<?php echo htmlspecialchars($item['item_name']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Category *</label>
                <select name="category" class="form-select bg-dark text-white border-secondary border-opacity-25" required>
                    <option value="Ink" <?php echo $item['category'] == 'Ink' ? 'selected' : ''; ?>>Ink</option>
                    <option value="Cartridge" <?php echo $item['category'] == 'Cartridge' ? 'selected' : ''; ?>>Cartridge</option>
                    <option value="Toner" <?php echo $item['category'] == 'Toner' ? 'selected' : ''; ?>>Toner</option>
                    <option value="Drum" <?php echo $item['category'] == 'Drum' ? 'selected' : ''; ?>>Drum</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Brand</label>
                <input type="text" name="brand" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($item['brand']); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Unit</label>
                <input type="text" name="unit" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($item['unit']); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Unit Cost (₱)</label>
                <input type="number" step="0.01" name="unit_cost" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($item['unit_cost'] ?? ''); ?>" placeholder="0.00">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Current Stock</label>
                <div class="input-group">
                    <input type="number" name="quantity" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo $item['quantity']; ?>" min="0">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-white-50"><?php echo htmlspecialchars($item['unit']); ?>s</span>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Min Quantity (Alert)</label>
                <input type="number" name="min_quantity" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo $item['min_quantity']; ?>" min="0">
            </div>
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Printer Compatibility / Models</label>
                <textarea name="model_compatibility" class="form-control bg-dark text-white border-secondary border-opacity-25" rows="2" placeholder="List printer models this fits..."><?php echo htmlspecialchars($item['model_compatibility']); ?></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Storage Location</label>
                <input type="text" name="location" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($item['location']); ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Remarks</label>
                <textarea name="remarks" class="form-control bg-dark text-white border-secondary border-opacity-25" rows="2"><?php echo htmlspecialchars($item['remarks']); ?></textarea>
            </div>
        </div>

        <div class="mt-4 text-end">
            <a href="consumables.php" class="btn btn-ghost-secondary me-2">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Update Consumable</button>
        </div>
    </form>

    <div class="mt-5 pt-4 border-top border-secondary border-opacity-10">
        <h5 class="text-white mb-4"><i class="fas fa-history me-2 text-primary opacity-75"></i>Transaction History</h5>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 small">
                <thead class="bg-darker">
                    <tr>
                        <th class="ps-3 py-2 text-white-50">Date</th>
                        <th class="py-2 text-white-50">Action</th>
                        <th class="py-2 text-white-50 text-center">Qty</th>
                        <th class="py-2 text-white-50">Department</th>
                        <th class="py-2 text-white-50">Remarks</th>
                        <th class="pe-3 py-2 text-white-50">User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("SELECT l.*, u.full_name FROM consumable_logs l JOIN users u ON l.user_id = u.id WHERE l.consumable_id = ? ORDER BY l.created_at DESC LIMIT 10");
                    $stmt->execute([$id]);
                    $logs = $stmt->fetchAll();
                    
                    foreach ($logs as $log):
                        $actionClass = $log['action_type'] == 'ADD' ? 'text-success' : 'text-warning';
                    ?>
                        <tr>
                            <td class="ps-3 py-2 text-white-50"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                            <td class="py-2"><span class="<?php echo $actionClass; ?> fw-bold"><?php echo $log['action_type']; ?></span></td>
                            <td class="py-2 text-center"><?php echo $log['quantity']; ?></td>
                            <td class="py-2 text-white"><?php echo htmlspecialchars($log['department'] ?: '-'); ?></td>
                            <td class="py-2 text-white-50"><?php echo htmlspecialchars($log['remarks'] ?: '-'); ?></td>
                            <td class="pe-3 py-2 text-white-50"><?php echo htmlspecialchars($log['full_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-white-50">No transactions recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const btn = form?.querySelector('button[type="submit"]');
    if (form && btn) {
        form.addEventListener('submit', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
        });
    }
});
</script>

