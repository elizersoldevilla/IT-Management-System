<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS asset_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$error = '';
$success = '';

if (isset($_POST['add_category'])) {
    $name = clean_input($_POST['name']);
    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO asset_categories (name) VALUES (?)");
            $stmt->execute([$name]);
            $success = "Category added successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . ($e->getCode() == 23000 ? "Category already exists." : $e->getMessage());
        }
    }
}

if (isset($_POST['edit_category'])) {
    $id = $_POST['id'];
    $name = clean_input($_POST['name']);
    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE asset_categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $success = "Category updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . ($e->getCode() == 23000 ? "Category already exists." : $e->getMessage());
        }
    }
}

if (isset($_POST['delete_id'])) {
    verify_csrf();
    $id = $_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("SELECT name FROM asset_categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch();

        if ($cat) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE category = ?");
            $stmt->execute([$cat['name']]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Cannot delete category as it is currently in use by one or more assets.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM asset_categories WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Category deleted successfully!";
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$categories = $pdo->query("SELECT id, name FROM asset_categories ORDER BY name ASC")->fetchAll();

$defaults = ["Laptop", "Desktop", "Monitor", "Printer", "Networking", "Peripheral", "AIO", "Other"];
foreach ($defaults as $d) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO asset_categories (name) VALUES (?)");
    $stmt->execute([$d]);
}
$categories = $pdo->query("SELECT id, name FROM asset_categories ORDER BY name ASC")->fetchAll();
?>

<div class="row align-items-end mb-4">
    <div class="col-md-6">
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Settings</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Asset Categories</h1>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="glass-panel p-4">
            <h5 class="text-white mb-3">Add New Category</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-white-50">Category Name</label>
                    <input type="text" name="name" class="form-control bg-dark text-white border-secondary" required
                        placeholder="e.g. Server">
                </div>
                <button type="submit" name="add_category" class="btn btn-primary w-100">Add Category</button>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="glass-panel p-0 overflow-hidden">
            <table class="table table-dark table-hover mb-0">
                <thead class="bg-darker">
                    <tr>
                        <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold">Name</th>
                        <th class="py-3 text-white-50 text-uppercase small fw-bold">Assets Count</th>
                        <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <span class="text-white fw-medium">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <?php
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE category = ?");
                                $stmt->execute([$cat['name']]);
                                $count = $stmt->fetchColumn();
                                ?>
                                <span class="badge bg-secondary">
                                    <?php echo $count; ?> assets
                                </span>
                            </td>
                            <td class="pe-4 py-3 text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-sm btn-icon btn-ghost-secondary"
                                        onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this category?');"
                                        style="display:inline;">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="editModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title text-white">Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label text-white-50">Category Name</label>
                        <input type="text" name="name" id="edit_name"
                            class="form-control bg-dark text-white border-secondary" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-25">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editCategory(id, name) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
