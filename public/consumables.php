<?php
require_once __DIR__ . '/../views/header.php';
require_login();
if (!has_privilege('manage_consumables')) {
    redirect('dashboard.php');
}


try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS consumables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        category ENUM('Ink', 'Cartridge', 'Toner', 'Drum') NOT NULL,
        brand VARCHAR(100),
        model_compatibility TEXT,
        quantity INT DEFAULT 0,
        min_quantity INT DEFAULT 5,
        unit VARCHAR(50) DEFAULT 'Piece',
        location VARCHAR(100),
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    
    $stmt = $pdo->query("DESCRIBE consumables");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = [
        'item_name' => "VARCHAR(255) NOT NULL AFTER id",
        'category' => "ENUM('Ink', 'Cartridge', 'Toner', 'Drum') NOT NULL AFTER item_name",
        'brand' => "VARCHAR(100) AFTER category",
        'model_compatibility' => "TEXT AFTER brand",
        'quantity' => "INT DEFAULT 0 AFTER model_compatibility",
        'min_quantity' => "INT DEFAULT 5 AFTER quantity",
        'unit' => "VARCHAR(50) DEFAULT 'Piece' AFTER min_quantity",
        'location' => "VARCHAR(100) AFTER unit",
        'remarks' => "TEXT AFTER location",
        'deleted_at' => "TIMESTAMP NULL DEFAULT NULL AFTER remarks"
    ];

    foreach ($required_columns as $col => $definition) {
        if (!in_array($col, $existing_columns)) {
            $pdo->exec("ALTER TABLE consumables ADD COLUMN $col $definition");
        }
    }

    if (in_array('category', $existing_columns)) {
        $pdo->exec("ALTER TABLE consumables MODIFY COLUMN category ENUM('Ink', 'Cartridge', 'Toner', 'Drum') NOT NULL");
    }

    
    $pdo->exec("CREATE TABLE IF NOT EXISTS consumable_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        consumable_id INT NOT NULL,
        user_id INT NOT NULL,
        action_type ENUM('ADD', 'DEDUCT') NOT NULL,
        quantity INT NOT NULL,
        department VARCHAR(100),
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE CASCADE
    )");

} catch (Exception $e) {
    error_log("Database Initialization Error in consumables: " . $e->getMessage());
    echo "<div class='alert alert-danger'>A database error occurred. Please contact the administrator.</div>";
}


if (isset($_POST['deduct_stock'])) {
    verify_csrf();
    $id = $_POST['consumable_id'];
    $remove_qty = (int)$_POST['quantity'];
    $dept = clean_input($_POST['department']);
    $remarks = clean_input($_POST['remarks']);
    
    $issued_by = $_SESSION['user_id'];
    $created_at = date('Y-m-d H:i:s');
    
    if (has_privilege('manage_consumables')) {
        if (!empty($_POST['issued_by'])) {
            $issued_by = (int)$_POST['issued_by'];
        }
        if (!empty($_POST['created_at'])) {
            $created_at = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $_POST['created_at'])));
        }
    }

    if ($remove_qty <= 0) {
        $error = "Quantity must be greater than zero.";
    } else {
        $stmt = $pdo->prepare("SELECT quantity, item_name FROM consumables WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if ($item && $item['quantity'] >= $remove_qty) {
            $new_qty = $item['quantity'] - $remove_qty;
            
            $pdo->beginTransaction();
            try {
                
                $stmt = $pdo->prepare("UPDATE consumables SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_qty, $id]);

                
                $stmt = $pdo->prepare("INSERT INTO consumable_logs (consumable_id, user_id, action_type, quantity, department, remarks, created_at) VALUES (?, ?, 'DEDUCT', ?, ?, ?, ?)");
                $stmt->execute([$id, $issued_by, $remove_qty, $dept, $remarks, $created_at]);

                log_action($pdo, $_SESSION['user_id'], 'DEDUCT_CONSUMABLE', "Deducted $remove_qty from {$item['item_name']} for $dept department");

                $pdo->commit();
                $success = "Stock deducted successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Deduction Error: " . $e->getMessage());
                $error = "An error occurred during deduction.";
            }
        } else {
            $error = "Insufficient stock.";
        }
    }
}


if (isset($_POST['add_stock'])) {
    verify_csrf();
    $id = $_POST['consumable_id'];
    $add_qty = (int)$_POST['quantity'];
    $remarks = clean_input($_POST['remarks']);
    
    $added_by = $_SESSION['user_id'];
    $created_at = date('Y-m-d H:i:s');
    
    if (has_privilege('manage_consumables')) {
        if (!empty($_POST['added_by'])) {
            $added_by = (int)$_POST['added_by'];
        }
        if (!empty($_POST['created_at'])) {
            $created_at = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $_POST['created_at'])));
        }
    }

    if ($add_qty <= 0) {
        $error = "Quantity must be greater than zero.";
    } else {
        $stmt = $pdo->prepare("SELECT quantity, item_name FROM consumables WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if ($item) {
            $new_qty = $item['quantity'] + $add_qty;
            
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE consumables SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_qty, $id]);

                $stmt = $pdo->prepare("INSERT INTO consumable_logs (consumable_id, user_id, action_type, quantity, department, remarks, created_at) VALUES (?, ?, 'ADD', ?, '', ?, ?)");
                $stmt->execute([$id, $added_by, $add_qty, $remarks, $created_at]);

                log_action($pdo, $_SESSION['user_id'], 'ADD_CONSUMABLE_STOCK', "Added $add_qty to {$item['item_name']}");
                invalidate_dashboard_cache();

                $pdo->commit();
                $success = "Stock added successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Add Stock Error: " . $e->getMessage());
                $error = "An error occurred while adding stock.";
            }
        } else {
            $error = "Item not found.";
        }
    }
}



if (isset($_POST['delete_id']) && has_privilege('manage_consumables')) {
    verify_csrf();
    $id = $_POST['delete_id'];

    if ($deleted_at_exists_c) {
        $stmt = $pdo->prepare("SELECT item_name FROM consumables WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if ($item) {
            $stmt = $pdo->prepare("UPDATE consumables SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            log_action($pdo, $_SESSION['user_id'], 'DELETE_CONSUMABLE', "Deleted Consumable: {$item['item_name']}");
            invalidate_dashboard_cache();
        }
    }

    echo "<script>window.location.href='consumables.php';</script>";
}


$category_filter = $_GET['category'] ?? '';
$search_query = $_GET['search'] ?? '';
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

$deleted_at_exists_c = false;
try {
    $col_check_c = $pdo->query("SHOW COLUMNS FROM consumables LIKE 'deleted_at'");
    $deleted_at_exists_c = $col_check_c->rowCount() > 0;
} catch (Exception $e) {
    $deleted_at_exists_c = false;
}

$sql = "SELECT * FROM consumables WHERE 1=1";
$params = [];

if (!$show_deleted && $deleted_at_exists_c) {
    $sql .= " AND deleted_at IS NULL";
}

if ($category_filter) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
}

if ($search_query) {
    $sql .= " AND (item_name LIKE ? OR brand LIKE ? OR model_compatibility LIKE ? OR location LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY item_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$consumables = $stmt->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Inventory</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Printer Consumables</h1>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if (has_privilege('manage_consumables')): ?>
            <a href="consumables_add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-2"></i>New Consumable
            </a>
            <?php if (has_role('admin') || has_privilege('edit_consumable_logs')): ?>
            <a href="consumables_report.php" class="btn btn-outline-secondary btn-sm ms-2">
                <i class="fas fa-list me-2"></i>Transaction History
            </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4 border-secondary border-opacity-10 bg-darker">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-white-50"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-dark border-secondary border-opacity-25 text-white placeholder-secondary" 
                           placeholder="Search name, brand, compatibility..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="category" class="form-select form-select-sm bg-dark border-secondary border-opacity-25 text-white">
                    <option value="" class="bg-dark">Category: All</option>
                    <option value="Ink" class="bg-dark text-white" <?php echo $category_filter == 'Ink' ? 'selected' : ''; ?>>Ink</option>
                    <option value="Cartridge" class="bg-dark text-white" <?php echo $category_filter == 'Cartridge' ? 'selected' : ''; ?>>Cartridge</option>
                    <option value="Toner" class="bg-dark text-white" <?php echo $category_filter == 'Toner' ? 'selected' : ''; ?>>Toner</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

<div class="glass-card border-secondary border-opacity-10 bg-dark">
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold">Item Name</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold">Category</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold">Compatibility</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold text-center">Stock</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold text-end">Unit Cost</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold text-end">Total Value</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold">Location</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consumables as $item): ?>
                    <?php 
                        $lowStock = $item['quantity'] <= $item['min_quantity'];
                        $stockClass = $lowStock ? 'text-danger fw-bold' : 'text-success';
                    ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="text-white fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div class="text-white-50 small opacity-75"><?php echo htmlspecialchars($item['brand']); ?></div>
                        </td>
                        <td class="py-3">
                            <span class="badge bg-secondary bg-opacity-25 border border-secondary border-opacity-25 text-white-50">
                                <?php echo htmlspecialchars($item['category']); ?>
                            </span>
                        </td>
                        <td class="py-3">
                            <div class="text-white-50 small" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($item['model_compatibility']); ?>">
                                <?php echo htmlspecialchars($item['model_compatibility'] ?: 'Generic'); ?>
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            <div class="<?php echo $stockClass; ?> fs-5">
                                <?php echo $item['quantity']; ?>
                                <span class="small opacity-50"><?php echo htmlspecialchars($item['unit']); ?>s</span>
                            </div>
                            <?php if ($lowStock): ?>
                                <span class="badge bg-danger text-uppercase" style="font-size: 0.6rem;">Low Stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-end">
                            <span class="text-white-50 small"><?php echo $item['unit_cost'] ? '₱' . number_format($item['unit_cost'], 2) : 'N/A'; ?></span>
                        </td>
                        <td class="py-3 text-end">
                            <span class="text-white fw-medium"><?php echo $item['unit_cost'] ? '₱' . number_format($item['total_value'] ?? ($item['quantity'] * $item['unit_cost']), 2) : 'N/A'; ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo htmlspecialchars($item['location'] ?: 'N/A'); ?></span>
                        </td>
                        <td class="pe-4 py-3 text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-icon btn-ghost-success" 
                                    onclick='openAddStockModal(<?php echo json_encode($item); ?>)' title="Add Stock">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-icon btn-ghost-warning" 
                                    onclick='openDeductModal(<?php echo json_encode($item); ?>)' title="Deduct Stock">
                                <i class="fas fa-minus"></i>
                            </button>
                            <a href="consumables_report.php?item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-icon btn-ghost-secondary" title="Transaction History">
                                <i class="fas fa-history"></i>
                            </a>
                            <?php if (has_privilege('manage_consumables')): ?>
                                <form method="POST" onsubmit="return confirm('Delete this item?');" style="display:inline;">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($consumables)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-ink-well fa-3x"></i></div>
                            <h6 class="text-white">No consumables found</h6>
                            <p class="text-white-50 small mb-0">Start by adding printer ink, cartridges or toners.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 

$depts = $pdo->query("SELECT DISTINCT department FROM assets WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

$can_manage = has_privilege('manage_consumables');
$users = [];
if ($can_manage) {
    $users = $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
}
?>


<div class="modal fade" id="deductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-secondary">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title text-white">Deduct Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="consumable_id" id="modal_consumable_id">
                    <div class="mb-3">
                        <label class="form-label text-white-50">Item</label>
                        <input type="text" id="modal_item_name" class="form-control bg-darker border-0 text-white" readonly>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label text-white-50 small text-uppercase fw-bold">Quantity to Issue</label>
                            <input type="number" name="quantity" class="form-control bg-dark border-secondary text-white" required min="1" id="modal_max_qty">
                            <div class="small text-white-50 mt-1">Available: <span id="modal_available_qty">0</span></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-white-50 small text-uppercase fw-bold">Department</label>
                            <input type="text" name="department" class="form-control bg-dark border-secondary text-white" required list="deptList" placeholder="e.g. Nursing">
                            <datalist id="deptList">
                                <?php foreach ($depts as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-white-50 small text-uppercase fw-bold">Remarks</label>
                        <textarea name="remarks" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="Who requested? Any specific notes?"></textarea>
                    </div>
                    <?php if ($can_manage): ?>
                        <div class="row g-3 mt-1">
                            <div class="col-6">
                                <label class="form-label text-white-50 small text-uppercase fw-bold">Date Deployed</label>
                                <input type="datetime-local" name="created_at" class="form-control bg-dark border-secondary text-white" value="<?php echo date('Y-m-d\TH:i'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50 small text-uppercase fw-bold">Issued By</label>
                                <select name="issued_by" class="form-select bg-dark border-secondary text-white">
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo $_SESSION['user_id'] == $u['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo ucfirst($u['role']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-secondary border-opacity-25">
                    <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="deduct_stock" class="btn btn-warning px-4">Deduct Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-secondary">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title text-white">Add Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="consumable_id" id="add_modal_consumable_id">
                    <div class="mb-3">
                        <label class="form-label text-white-50">Item</label>
                        <input type="text" id="add_modal_item_name" class="form-control bg-darker border-0 text-white" readonly>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label text-white-50 small text-uppercase fw-bold">Quantity to Add</label>
                            <input type="number" name="quantity" class="form-control bg-dark border-secondary text-white" required min="1" id="add_modal_qty">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-white-50 small text-uppercase fw-bold">Remarks</label>
                            <input type="text" name="remarks" class="form-control bg-dark border-secondary text-white" placeholder="Supplier, PO number, etc.">
                        </div>
                    </div>
                    <?php if ($can_manage): ?>
                        <div class="row g-3 mt-1">
                            <div class="col-6">
                                <label class="form-label text-white-50 small text-uppercase fw-bold">Date Received</label>
                                <input type="datetime-local" name="created_at" class="form-control bg-dark border-secondary text-white" value="<?php echo date('Y-m-d\TH:i'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50 small text-uppercase fw-bold">Received By</label>
                                <select name="added_by" class="form-select bg-dark border-secondary text-white">
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo $_SESSION['user_id'] == $u['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo ucfirst($u['role']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-secondary border-opacity-25">
                    <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_stock" class="btn btn-success px-4">Add Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openDeductModal(item) {
        document.getElementById('modal_consumable_id').value = item.id;
        document.getElementById('modal_item_name').value = item.item_name;
        document.getElementById('modal_available_qty').textContent = item.quantity;
        const qtyInput = document.getElementById('modal_max_qty');
        qtyInput.max = item.quantity;
        qtyInput.value = 1;
        
        new bootstrap.Modal(document.getElementById('deductModal')).show();
    }
    
    function openAddStockModal(item) {
        document.getElementById('add_modal_consumable_id').value = item.id;
        document.getElementById('add_modal_item_name').value = item.item_name;
        document.getElementById('add_modal_qty').value = 1;
        
        new bootstrap.Modal(document.getElementById('addStockModal')).show();
    }
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>

