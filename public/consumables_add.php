<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('manage_consumables')) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

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
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO consumables (item_name, category, brand, model_compatibility, quantity, min_quantity, unit, unit_cost, location, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$item_name, $category, $brand, $model_compatibility, $quantity, $min_quantity, $unit, $unit_cost, $location, $remarks]);
            $consumable_id = (int)$pdo->lastInsertId();

            if ((int)$quantity > 0) {
                $stmt = $pdo->prepare("INSERT INTO consumable_logs (consumable_id, user_id, action_type, quantity, department, remarks, created_at) VALUES (?, ?, 'ADD', ?, '', ?, ?)");
                $stmt->execute([$consumable_id, $_SESSION['user_id'], (int)$quantity, 'Initial Stock', date('Y-m-d H:i:s')]);
            }

            $pdo->commit();
            $success = "Consumable added successfully!";
            log_action($pdo, $_SESSION['user_id'], 'ADD_CONSUMABLE', "Added Consumable: $item_name");
            invalidate_dashboard_cache();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database Error in consumables_add: " . $e->getMessage());
            $error = "An internal database error occurred. Please try again.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Consumables</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Add New Consumable</h1>
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
                <input type="text" name="item_name" class="form-control bg-dark text-white border-secondary border-opacity-25" required placeholder="e.g. HP 680 Black Ink">
            </div>
            <div class="col-md-4">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Category *</label>
                <select name="category" class="form-select bg-dark text-white border-secondary border-opacity-25" required>
                    <option value="">-- Select --</option>
                    <option value="Ink">Ink</option>
                    <option value="Cartridge">Cartridge</option>
                    <option value="Toner">Toner</option>
                    <option value="Drum">Drum</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Brand</label>
                <input type="text" name="brand" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="e.g. HP, Canon, Epson">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Unit</label>
                <input type="text" name="unit" class="form-control bg-dark text-white border-secondary border-opacity-25" value="Piece" placeholder="e.g. Piece, Bottle, Box">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Unit Cost (₱)</label>
                <input type="number" step="0.01" name="unit_cost" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="0.00">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Initial Quantity</label>
                <input type="number" name="quantity" class="form-control bg-dark text-white border-secondary border-opacity-25" value="0" min="0">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Min Quantity (Alert)</label>
                <input type="number" name="min_quantity" class="form-control bg-dark text-white border-secondary border-opacity-25" value="5" min="0">
            </div>
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Printer Compatibility / Models</label>
                <textarea name="model_compatibility" class="form-control bg-dark text-white border-secondary border-opacity-25" rows="2" placeholder="List printer models this fits..."></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Storage Location</label>
                <input type="text" name="location" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="e.g. IT Stock Room, Cabinet A">
            </div>
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Remarks</label>
                <textarea name="remarks" class="form-control bg-dark text-white border-secondary border-opacity-25" rows="2"></textarea>
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="reset" class="btn btn-ghost-secondary me-2">Clear Form</button>
            <button type="submit" class="btn btn-primary px-4" id="save-consumable-btn">Save Consumable</button>
        </div>

        <script>
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = document.getElementById('save-consumable-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
            }
        });
        </script>
    </form>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
