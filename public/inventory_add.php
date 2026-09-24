<?php
require_once __DIR__ . '/../views/header.php';

require_login();
if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
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
    verify_csrf();
    $serial_number = clean_input($_POST['serial_number']);
    $category = clean_input($_POST['category']);
    $brand = clean_input($_POST['brand']);
    $model = clean_input($_POST['model']);
    $purchase_date = $_POST['purchase_date'];
    $item_name = trim($brand . ' ' . $model);
    $warranty_expiry = null;
    $status = $_POST['status'];
    $encoded_by = !empty($_POST['encoded_by']) ? $_POST['encoded_by'] : $_SESSION['user_id'];


    $deployment_date = !empty($_POST['deployment_date']) ? $_POST['deployment_date'] : null;
    $department = clean_input($_POST['department']);
    $location = clean_input($_POST['location']);
    $description = clean_input($_POST['description']);
    $received_by = clean_input($_POST['received_by'] ?? '');
    $received_by_user_id = !empty($_POST['received_by_user_id']) ? (int) $_POST['received_by_user_id'] : null;
    $deployed_by = $_SESSION['user_id'];
    $assigned_to = null;

    if (empty($serial_number) || empty($category) || empty($brand)) {
        $error = "Required fields missing.";
    } else {
        try {
            $vendor_id = !empty($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : null;
            $stmt = $pdo->prepare("INSERT INTO assets (serial_number, item_name, category, brand, model, purchase_date, warranty_expiry, status, assigned_to, deployment_date, department, location, deployed_by, encoded_by, description, received_by, received_by_user_id, vendor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$serial_number, $item_name, $category, $brand, $model, $purchase_date, $warranty_expiry, $status, $assigned_to, $deployment_date, $department, $location, $deployed_by, $encoded_by, $description, $received_by, $received_by_user_id, $vendor_id]);
            $success = "Asset added successfully!";
            log_action($pdo, $_SESSION['user_id'], 'ADD_ASSET', "Added Asset: $item_name ($serial_number)");
            invalidate_dashboard_cache();
            echo "<script>window.location.href='inventory.php';</script>";
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Serial Number already exists.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="mb-4">
    <h1 class="h3 mb-0">Add New Asset</h1>
</div>

<div class="glass-panel p-4" style="max-width: 800px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="bg-danger text-white p-3 rounded mb-4"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div id="success-alert" class="bg-success text-white p-3 rounded mb-4"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="mb-3">
                <label class="block mb-2 font-medium">Serial Number *</label>
                <input type="text" name="serial_number" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Category *</label>
                <select name="category" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Brand *</label>
                <input type="text" name="brand" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Model</label>
                <input type="text" name="model" class="form-control">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Delivery Date</label>
                <input type="date" name="purchase_date" class="form-control">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Status</label>
                <select name="status" class="form-control">
                    <option value="working">Working</option>
                    <option value="missing">Missing</option>
                    <option value="repair">For Repair</option>
                    <option value="retired">Retired</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Encoded By: </label>
                <select name="encoded_by" class="form-control">
                    <option value="<?php echo $_SESSION['user_id']; ?>">-- Select Encoder --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user['id'] == $_SESSION['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Vendor / Supplier</label>
                <select name="vendor_id" class="form-control">
                    <option value="">-- Select Vendor --</option>
                    <?php
                    $vendors_list = $pdo->query("SELECT id, name FROM vendors WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
                    foreach ($vendors_list as $v):
                    ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="mb-3">
                <label class="block mb-2 font-medium">Department</label>
                <input type="text" name="department" class="form-control" placeholder="e.g. HR, Finance">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Location</label>
                <input type="text" name="location" class="form-control" placeholder="e.g. IT Office, Nurse Station">
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Person Accountable</label>
                <select name="received_by_user_id" class="form-control">
                    <option value="">-- Select Person --</option>
                    <?php
                    $users_list = $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
                    foreach ($users_list as $u):
                    ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name'] . ' (' . ucfirst($u['role']) . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="received_by" value="">
            </div>
            <div class="mb-3" style="grid-column: span 2;">
                <label class="block mb-2 font-medium">Description / Notes</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
        </div>

        <div class="mt-4 text-right" style="margin-top: 1.5rem; text-align: right;">
            <a href="inventory.php" class="btn btn-secondary mr-2"
                style="margin-right: 0.5rem; background: #6b7280; color: white;">Cancel</a>
            <button type="submit" class="btn btn-primary" id="add-asset-btn">Add Asset</button>
        </div>

        <script>
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = document.getElementById('add-asset-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
            }
        });
        </script>
    </form>
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
</script>
<?php require_once __DIR__ . '/../views/footer.php'; ?>