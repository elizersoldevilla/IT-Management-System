<?php
require_once __DIR__ . '/../views/header.php';

require_login();
if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('inventory.php');
}

$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
$stmt->execute([$id]);
$asset = $stmt->fetch();

if (!$asset) {
    redirect('inventory.php');
}


$pdo->exec("CREATE TABLE IF NOT EXISTS asset_categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$users = $pdo->query("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name")->fetchAll();
$categories = $pdo->query("SELECT name FROM asset_categories ORDER BY name")->fetchAll();

if (empty($categories)) {
    $defaults = ["Laptop", "Desktop", "Monitor", "Printer", "Networking", "Peripheral", "AIO", "Other"];
    foreach ($defaults as $d) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO asset_categories (name) VALUES (?)");
        $stmt->execute([$d]);
    }
    $categories = $pdo->query("SELECT name FROM asset_categories ORDER BY name")->fetchAll();
} else {
    $defaults = ["Laptop", "Desktop", "Monitor", "Printer", "Networking", "Peripheral", "AIO", "Other"];
    foreach ($defaults as $d) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO asset_categories (name) VALUES (?)");
        $stmt->execute([$d]);
    }
    $categories = $pdo->query("SELECT name FROM asset_categories ORDER BY name")->fetchAll();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial_number = clean_input($_POST['serial_number']);
    $category = clean_input($_POST['category']);
    $brand = clean_input($_POST['brand']);
    $model = clean_input($_POST['model']);
    $purchase_date = $_POST['purchase_date'];
    $item_name = trim($brand . ' ' . $model);
    $warranty_expiry = null;
    $status = $_POST['status'];
    $encoded_by = !empty($_POST['encoded_by']) ? $_POST['encoded_by'] : $asset['encoded_by'];
    $assigned_to = $asset['assigned_to'];


    $deployment_date = !empty($_POST['deployment_date']) ? $_POST['deployment_date'] : null;
    $department = clean_input($_POST['department'] ?? '');
    $location = clean_input($_POST['location'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $received_by = clean_input($_POST['received_by'] ?? '');
    $received_by_user_id = !empty($_POST['received_by_user_id']) ? (int) $_POST['received_by_user_id'] : null;
    $vendor_id = !empty($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : null;


    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE serial_number = ? AND id != ?");
    $stmt_check->execute([$serial_number, $id]);
    if ($stmt_check->fetchColumn() > 0) {
        $error = "Error: Serial number '$serial_number' is already in use by another asset.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $locked_asset = $stmt->fetch();

            if (!$locked_asset) {
                $pdo->rollBack();
                $error = "Asset not found or was deleted.";
            } else {
                $changes = [];
                $map = [
                    'serial_number' => $serial_number,
                    'item_name' => $item_name,
                    'category' => $category,
                    'brand' => $brand,
                    'model' => $model,
                    'purchase_date' => $purchase_date,
                    'warranty_expiry' => $warranty_expiry,
                    'status' => $status,
                    'encoded_by' => $encoded_by,
                    'deployment_date' => $deployment_date,
                    'department' => $department,
                    'location' => $location,
                    'description' => $description,
                    'received_by' => $received_by,
                    'received_by_user_id' => $received_by_user_id,
                    'vendor_id' => $vendor_id
                ];
                foreach ($map as $field => $new_val) {
                    $old_val = $locked_asset[$field] ?? '';
                    if ((string)$old_val !== (string)$new_val) {
                        $changes[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . ($old_val ?: '(none)') . ' → ' . ($new_val ?: '(none)');
                    }
                }

                $stmt = $pdo->prepare("UPDATE assets SET serial_number = ?, item_name = ?, category = ?, brand = ?, model = ?, purchase_date = ?, warranty_expiry = ?, status = ?, encoded_by = ?, deployment_date = ?, department = ?, location = ?, description = ?, received_by = ?, received_by_user_id = ?, vendor_id = ? WHERE id = ?");
                $stmt->execute([$serial_number, $item_name, $category, $brand, $model, $purchase_date, $warranty_expiry, $status, $encoded_by, $deployment_date, $department, $location, $description, $received_by, $received_by_user_id, $vendor_id, $id]);

                $change_details = empty($changes) ? 'No field changes' : implode('; ', $changes);
                log_action($pdo, $_SESSION['user_id'], 'UPDATE_ASSET', "Updated Asset: $item_name ($serial_number) ($change_details)");
                invalidate_dashboard_cache();


                if ($status != $locked_asset['status']) {
                    log_asset_history($pdo, $id, $_SESSION['user_id'], 'STATUS_CHANGE', "Status changed from {$locked_asset['status']} to $status");
                }
                if ($assigned_to != $locked_asset['assigned_to']) {
                    $old_assign = $locked_asset['assigned_to'] ? "User #{$locked_asset['assigned_to']}" : "Unassigned";
                    $new_assign = $assigned_to ? "User #$assigned_to" : "Unassigned";
                    log_asset_history($pdo, $id, $_SESSION['user_id'], 'ASSIGNMENT_CHANGE', "Assigned changed from $old_assign to $new_assign");
                }

                $success = "Asset updated successfully!";

                $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
                $stmt->execute([$id]);
                $asset = $stmt->fetch();

                $pdo->commit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<div class="mb-4 d-flex align-items-center justify-content-between">
    <h1 class="h3 mb-0 text-white fw-bold">Edit Asset</h1>
    <a href="inventory_details.php?id=<?php echo $id; ?>" class="btn btn-outline-light btn-sm rounded-pill"><i
            class="fas fa-arrow-left me-1"></i> Back to Details</a>
</div>

<div class="glass-panel p-4" style="max-width: 900px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div id="success-alert" class="alert alert-success d-flex align-items-center mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-4">
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Serial Number</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-white-50"><i
                            class="fas fa-barcode"></i></span>
                    <input type="text" name="serial_number" class="form-control bg-dark text-white border-secondary"
                        value="<?php echo htmlspecialchars($asset['serial_number']); ?>" required>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Category</label>
                <select name="category" class="form-select bg-dark text-white border-secondary" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo $asset['category'] == $cat['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Brand</label>
                <input type="text" name="brand" class="form-control bg-dark text-white border-secondary"
                    value="<?php echo htmlspecialchars($asset['brand']); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Model</label>
                <input type="text" name="model" class="form-control bg-dark text-white border-secondary"
                    value="<?php echo htmlspecialchars($asset['model']); ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Delivery Date</label>
                <input type="date" name="purchase_date" class="form-control bg-dark text-white border-secondary"
                    value="<?php echo $asset['purchase_date']; ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Status</label>
                <select name="status" class="form-select bg-dark text-white border-secondary">
                    <option value="working" <?php echo $asset['status'] == 'working' ? 'selected' : ''; ?>>Working
                    </option>
                    <option value="repair" <?php echo $asset['status'] == 'repair' ? 'selected' : ''; ?>>For Repair
                    </option>
                    <option value="retired" <?php echo $asset['status'] == 'retired' ? 'selected' : ''; ?>>Retired
                    </option>
                    <option value="missing" <?php echo $asset['status'] == 'missing' ? 'selected' : ''; ?>>Missing
                    </option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Encoded By</label>
                <select name="encoded_by" class="form-select bg-dark text-white border-secondary">
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $asset['encoded_by'] == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <hr class="border-secondary opacity-50 my-2">
                <h6 class="text-white mb-3"><i class="fas fa-clipboard-list me-2 text-primary"></i>Additional Details
                </h6>
            </div>

            <div class="col-md-3">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Department</label>
                <input type="text" name="department" class="form-control bg-dark text-white border-secondary"
                    value="<?php echo htmlspecialchars($asset['department'] ?? ''); ?>" placeholder="e.g. HR, Finance">
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Location</label>
                <input type="text" name="location" class="form-control bg-dark text-white border-secondary"
                    value="<?php echo htmlspecialchars($asset['location'] ?? ''); ?>"
                    placeholder="e.g. IT Office, Nurse Station">
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Deployment Date</label>
                <input type="date" name="deployment_date" class="form-control bg-dark text-white border-secondary"
                    value="<?php echo $asset['deployment_date']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Person Accountable</label>
                <select name="received_by_user_id" class="form-control bg-dark text-white border-secondary">
                    <option value="">-- Select Person --</option>
                    <?php
                    $users_list = $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
                    $current_user_id = $asset['received_by_user_id'] ?? null;
                    foreach ($users_list as $u):
                    ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $current_user_id == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name'] . ' (' . ucfirst($u['role']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="received_by" value="<?php echo htmlspecialchars($asset['received_by'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Vendor / Supplier</label>
                <select name="vendor_id" class="form-control bg-dark text-white border-secondary">
                    <option value="">-- Select Vendor --</option>
                    <?php
                    $vendors_list = $pdo->query("SELECT id, name FROM vendors WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
                    $current_vendor_id = $asset['vendor_id'] ?? null;
                    foreach ($vendors_list as $v):
                    ?>
                        <option value="<?php echo $v['id']; ?>" <?php echo $current_vendor_id == $v['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($v['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Description / Notes</label>
                <textarea name="description" class="form-control bg-dark text-white border-secondary"
                    rows="3"><?php echo htmlspecialchars($asset['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="mt-5 d-flex justify-content-end gap-2">
            <a href="inventory.php" class="btn btn-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 shadow-lg">Update Asset</button>
        </div>
    </form>
</div>


<?php
$stmt = $pdo->prepare("
    SELECT h.*, u.full_name 
    FROM asset_history h 
    LEFT JOIN users u ON h.user_id = u.id 
    WHERE h.asset_id = ? 
    ORDER BY h.created_at DESC
");
$stmt->execute([$id]);
$history = $stmt->fetchAll();
?>

<div class="mt-4 mb-4" style="margin-top: 2rem;">
    <h3 class="h4 mb-3 text-white">Asset History</h3>
    <div class="glass-panel p-0 overflow-hidden">
        <div class="table-container" style="margin: 0; box-shadow: none;">
            <table class="table table-dark table-hover mb-0">
                <thead class="bg-dark bg-opacity-50">
                    <tr>
                        <th class="text-white-50 font-medium py-3 ps-4">Date</th>
                        <th class="text-white-50 font-medium py-3">User</th>
                        <th class="text-white-50 font-medium py-3">Action</th>
                        <th class="text-white-50 font-medium py-3 pe-4">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($history) > 0): ?>
                        <?php foreach ($history as $log): ?>
                            <tr>
                                <td class="text-sm text-white-50 ps-4"><?php echo format_date($log['created_at']); ?></td>
                                <td class="text-white"><?php echo htmlspecialchars($log['full_name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                <td class="text-sm text-white-50 pe-4"><?php echo htmlspecialchars($log['notes']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-white-50 py-4">No history recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const successAlert = document.getElementById('success-alert');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 1s ease';
                successAlert.style.opacity = '0';
                setTimeout(() => {
                    successAlert.remove();
                }, 1000);
            }, 3000);
        }
    });
});

const editForm = document.querySelector('form');
const editSubmitBtn = document.querySelector('button[type="submit"]');

if (editForm && editSubmitBtn) {
    editForm.addEventListener('submit', function() {
        editSubmitBtn.disabled = true;
        editSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
    });
}
</script>
<?php require_once __DIR__ . '/../views/footer.php'; ?>