<?php
require_once __DIR__ . '/../views/header.php';
require_login();
if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$deleted_at_exists = false;
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM assets LIKE 'deleted_at'");
    $deleted_at_exists = $col_check->rowCount() > 0;
} catch (Exception $e) {
    $deleted_at_exists = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && has_privilege('manage_assets')) {
    verify_csrf();
    $action = clean_input($_POST['bulk_action']);
    $asset_ids = $_POST['asset_ids'] ?? [];

    if (empty($asset_ids)) {
        $error = "No assets selected.";
    } elseif ($action === 'delete' && $deleted_at_exists) {
        $stmt = $pdo->prepare("UPDATE assets SET deleted_at = NOW() WHERE id IN (" . implode(',', array_fill(0, count($asset_ids), '?')) . ") AND deleted_at IS NULL");
        $stmt->execute($asset_ids);
        $success = count($asset_ids) . " assets deleted.";
        log_action($pdo, $_SESSION['user_id'], 'BULK_DELETE_ASSETS', "Bulk deleted " . count($asset_ids) . " assets");
        invalidate_dashboard_cache();
    } elseif ($action === 'restore' && $deleted_at_exists) {
        $stmt = $pdo->prepare("UPDATE assets SET deleted_at = NULL WHERE id IN (" . implode(',', array_fill(0, count($asset_ids), '?')) . ") AND deleted_at IS NOT NULL");
        $stmt->execute($asset_ids);
        $success = count($asset_ids) . " assets restored.";
        log_action($pdo, $_SESSION['user_id'], 'BULK_RESTORE_ASSETS', "Bulk restored " . count($asset_ids) . " assets");
        invalidate_dashboard_cache();
    } else {
        $error = "Invalid action.";
    }

    echo "<script>window.location.href='inventory.php';</script>";
}

if (isset($_POST['delete_id']) && has_privilege('manage_assets') && $deleted_at_exists) {
    verify_csrf();
    $id = $_POST['delete_id'];

    $stmt = $pdo->prepare("SELECT item_name, serial_number FROM assets WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $asset = $stmt->fetch();

    if ($asset) {
        $stmt = $pdo->prepare("UPDATE assets SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        log_action($pdo, $_SESSION['user_id'], 'DELETE_ASSET', "Deleted Asset: {$asset['item_name']} ({$asset['serial_number']})");
        invalidate_dashboard_cache();
    }

    echo "<script>window.location.href='inventory.php';</script>";
}

if (isset($_POST['restore_id']) && has_privilege('manage_assets') && $deleted_at_exists) {
    verify_csrf();
    $id = $_POST['restore_id'];

    $stmt = $pdo->prepare("SELECT item_name, serial_number FROM assets WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$id]);
    $asset = $stmt->fetch();

    if ($asset) {
        $stmt = $pdo->prepare("UPDATE assets SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        log_action($pdo, $_SESSION['user_id'], 'RESTORE_ASSET', "Restored Asset: {$asset['item_name']} ({$asset['serial_number']})");
    }

    echo "<script>window.location.href='inventory.php';</script>";
}



$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';
$search_query = $_GET['search'] ?? '';
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';


$sql = "SELECT a.*, e.full_name as encoder_name, d.full_name as deployer_name 
        FROM assets a 
        LEFT JOIN users e ON a.encoded_by = e.id 
        LEFT JOIN users d ON a.deployed_by = d.id
        WHERE 1=1";
$params = [];

if ($deleted_at_exists && !$show_deleted) {
    $sql .= " AND a.deleted_at IS NULL";
}

if ($category_filter) {
    $sql .= " AND a.category = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

if ($department_filter) {
    $sql .= " AND a.department LIKE ?";
    $params[] = "%$department_filter%";
}

if ($search_query) {
    $sql .= " AND (a.serial_number LIKE ? OR a.item_name LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.location LIKE ? OR a.received_by LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}


$items_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$sql .= " ORDER BY a.created_at DESC";


$count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as total_count";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);


$sql .= " LIMIT $items_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();
?>


<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Assets</h6>
        <h1 class="h2 mb-0 fw-bold text-white">IT Inventory</h1>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if (has_privilege('manage_assets')): ?>
            <div class="d-flex gap-2 justify-content-md-end">
                <?php
                $exportQuery = http_build_query([
                    'category' => $category_filter,
                    'status' => $status_filter,
                    'department' => $department_filter,
                    'search' => $search_query
                ]);
                ?>
                <a href="inventory_export.php?<?php echo $exportQuery; ?>"
                    class="btn btn-outline-secondary btn-sm flex-fill flex-md-grow-0">
                    <i class="fas fa-file-export me-2"></i>Export CSV
                </a>
                <a href="inventory_import.php" class="btn btn-outline-info btn-sm flex-fill flex-md-grow-0">
                    <i class="fas fa-file-import me-2"></i>Import
                </a>
                <a href="inventory_add.php" class="btn btn-primary btn-sm flex-fill flex-md-grow-0">
                    <i class="fas fa-plus me-2"></i>New Asset
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (has_privilege('manage_assets')): ?>
<div class="card mb-4 bg-dark border-secondary border-opacity-25">
    <div class="card-body">
        <form method="POST" id="bulk-asset-form">
            <?php csrf_field(); ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-white-50 small text-uppercase fw-bold">Bulk Action</label>
                    <select name="bulk_action" class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-25" required>
                        <option value="">Select Action...</option>
                        <option value="delete">Delete Selected</option>
                        <option value="restore">Restore Selected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-warning btn-sm w-100" onclick="return confirm('Apply bulk action to selected assets?')">
                        <i class="fas fa-bolt me-1"></i>Apply
                    </button>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-white-50 small" id="asset-selected-count">0 assets selected</span>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</div>


<div class="card mb-4 border-secondary border-opacity-10 bg-darker">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-white-50"><i
                            class="fas fa-search"></i></span>
                    <input type="text" name="search"
                        class="form-control bg-dark border-secondary border-opacity-25 text-white placeholder-secondary"
                        placeholder="Search serial, model..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="category"
                    class="form-select form-select-sm bg-dark border-secondary border-opacity-25 text-white">
                    <option value="" class="bg-dark">Category: All</option>
                    <?php
                    $cats = get_asset_categories($pdo);
                    foreach ($cats as $c) {
                        $name = $c['name'];
                        $sel = $category_filter == $name ? 'selected' : '';
                        echo "<option value='$name' $sel class='bg-dark text-white'>$name</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status"
                    class="form-select form-select-sm bg-dark border-secondary border-opacity-25 text-white">
                    <option value="" class="bg-dark">Status: All</option>
                    <option value="working" class="bg-dark text-white" <?php echo $status_filter == 'working' ? 'selected' : ''; ?>>Working</option>
                    <option value="repair" class="bg-dark text-white" <?php echo $status_filter == 'repair' ? 'selected' : ''; ?>>For Repair</option>
                    <option value="retired" class="bg-dark text-white" <?php echo $status_filter == 'retired' ? 'selected' : ''; ?>>Retired</option>
                    <option value="missing" class="bg-dark text-white" <?php echo $status_filter == 'missing' ? 'selected' : ''; ?>>Missing</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-white-50"><i
                            class="fas fa-building"></i></span>
                    <input type="text" name="department"
                        class="form-control bg-dark border-secondary border-opacity-25 text-white placeholder-secondary"
                        placeholder="Department" value="<?php echo htmlspecialchars($department_filter); ?>">
                </div>
            </div>
            <div class="col-12 col-md-2">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['show_deleted' => $show_deleted ? '0' : '1'])); ?>"
                   class="btn btn-sm w-100 <?php echo $show_deleted ? 'btn-outline-danger' : 'btn-outline-warning'; ?>">
                    <i class="fas <?php echo $show_deleted ? 'fa-eye-slash' : 'fa-trash-restore'; ?> me-1"></i>
                    <?php echo $show_deleted ? 'Hide Deleted' : 'Show Deleted'; ?>
                </a>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Apply Filters</button>
            </div>
        </form>
    </div>
</div>


<div class="card border-secondary border-opacity-10 bg-dark">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold"
                        style="letter-spacing: 0.05em; width: 20%;">Asset Details</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold"
                        style="letter-spacing: 0.05em; width: 10%;">Model</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold"
                        style="letter-spacing: 0.05em; width: 15%;">Department</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold"
                        style="letter-spacing: 0.05em; width: 15%;">Encoded By</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold"
                        style="letter-spacing: 0.05em; width: 15%;">Person Accountable</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold"
                        style="letter-spacing: 0.05em; width: 10%;">Status</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold"
                        style="letter-spacing: 0.05em; width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <?php if (has_privilege('manage_assets')): ?>
                                <input type="checkbox" class="asset-checkbox" name="asset_ids[]" value="<?php echo $asset['id']; ?>" onchange="updateAssetSelectedCount()">
                            <?php endif; ?>
                            <div class="d-flex align-items-center">
                                <div class="avatar-square flex-shrink-0 bg-dark border border-secondary border-opacity-25 rounded-3 d-flex align-items-center justify-content-center me-3"
                                    style="width:42px;height:42px;">
                                    <?php
                                    $icon = 'fa-box';
                                    if (str_contains(strtolower($asset['category']), 'laptop'))
                                        $icon = 'fa-laptop';
                                    elseif (str_contains(strtolower($asset['category']), 'desktop'))
                                        $icon = 'fa-desktop';
                                    elseif (str_contains(strtolower($asset['category']), 'monitor'))
                                        $icon = 'fa-tv';
                                    elseif (str_contains(strtolower($asset['category']), 'printer'))
                                        $icon = 'fa-print';
                                    elseif (str_contains(strtolower($asset['category']), 'network'))
                                        $icon = 'fa-network-wired';
                                    elseif (str_contains(strtolower($asset['category']), 'aio'))
                                        $icon = 'fa-desktop';
                                    echo "<i class='fas $icon text-primary opacity-75 fa-lg'></i>";
                                    ?>
                                </div>
                                <div style="min-width: 0;">
                                    <div class="text-white fw-bold fs-5 font-monospace">
                                        <?php echo htmlspecialchars($asset['serial_number']); ?>
                                    </div>
                                    <div class="text-white-50 small opacity-75 text-truncate">
                                        <?php echo htmlspecialchars($asset['category']); ?> •
                                        <?php echo htmlspecialchars($asset['brand']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3">
                            <span
                                class="text-white-50 small"><?php echo htmlspecialchars($asset['model'] ?: 'N/A'); ?></span>
                        </td>
                        <td class="py-3">
                            <div class="text-white small"><?php echo htmlspecialchars($asset['department'] ?? 'N/A'); ?>
                            </div>
                        </td>
                        <td class="py-3">
                            <span
                                class="text-white-50 small"><?php echo htmlspecialchars($asset['encoder_name'] ?? 'N/A'); ?></span>
                        </td>
                        <td class="py-3">
                            <span
                                class="text-white-50 small"><?php echo htmlspecialchars($asset['received_by'] ?? 'N/A'); ?></span>
                        </td>
                        <td class="py-3">
                            <?php
                            $statusOptions = [
                                'working' => ['label' => 'Working', 'class' => 'text-success'],
                                'repair' => ['label' => 'For Repair', 'class' => 'text-warning'],
                                'missing' => ['label' => 'Missing', 'class' => 'text-danger'],
                                'retired' => ['label' => 'Retired', 'class' => 'text-secondary']
                            ];
                            $currentStatus = $asset['status'];
                            $currentClass = $statusOptions[$currentStatus]['class'] ?? 'text-white';
                            ?>
                            <select
                                class="form-select form-select-sm bg-transparent border-0 fw-medium <?php echo $currentClass; ?> status-updater"
                                data-id="<?php echo $asset['id']; ?>" style="width: auto; cursor: pointer; padding: 0;"
                                onchange="updateStatus(this)">
                                <?php foreach ($statusOptions as $val => $opt): ?>
                                    <option value="<?php echo $val; ?>" class="<?php echo $opt['class']; ?> bg-dark" <?php echo ($currentStatus == $val) ? 'selected' : ''; ?>>
                                        <?php echo $opt['label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="pe-4 py-3 text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="inventory_details.php?id=<?php echo $asset['id']; ?>"
                                    class="btn btn-sm btn-icon btn-ghost-secondary" title="View"><i
                                        class="fas fa-eye"></i></a>
                                <a href="inventory_qr.php?id=<?php echo $asset['id']; ?>" target="_blank"
                                    class="btn btn-sm btn-icon btn-ghost-secondary" title="QR"><i
                                        class="fas fa-qrcode"></i></a>
                                <?php if (has_privilege('manage_assets')): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-icon btn-ghost-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow-lg" style="z-index: 1060; min-width: 10rem;">
                                            <li><a class="dropdown-item text-white" href="inventory_edit.php?id=<?php echo $asset['id']; ?>"><i class="fas fa-edit me-2 opacity-75"></i> Edit Asset</a></li>
                                            <li><hr class="dropdown-divider bg-secondary opacity-25"></li>
                                            <?php if ($show_deleted && !empty($asset['deleted_at'])): ?>
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('Restore this asset?');" style="display:block;">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="restore_id" value="<?php echo $asset['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-success"><i class="fas fa-trash-restore me-2 opacity-75"></i> Restore</button>
                                                    </form>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('Are you sure?');" style="display:block;">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="delete_id" value="<?php echo $asset['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-2 opacity-75"></i> Delete</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($assets)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-search fa-3x"></i></div>
                            <h6 class="text-white">No assets found</h6>
                            <p class="text-white-50 small mb-0">Adjust your filters to see more results</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-white-50 small">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of
            <?php echo $total_items; ?> assets
        </div>
        <nav aria-label="Inventory Pagination">
            <ul class="pagination pagination-sm mb-0">
                <?php
                $queryParams = $_GET;


                $queryParams['page'] = max(1, $page - 1);
                $prevUrl = '?' . http_build_query($queryParams);
                ?>
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link bg-dark border-secondary text-white" href="<?php echo $prevUrl; ?>"
                        tabindex="-1">Previous</a>
                </li>

                <?php

                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                if ($end_page - $start_page < 4) {
                    $start_page = max(1, $end_page - 4);
                }

                for ($i = $start_page; $i <= $end_page; $i++):
                    $queryParams['page'] = $i;
                    $pageUrl = '?' . http_build_query($queryParams);
                    ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link <?php echo ($page == $i) ? 'bg-primary border-primary' : 'bg-dark border-secondary text-white'; ?>"
                            href="<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php

                $queryParams['page'] = min($total_pages, $page + 1);
                $nextUrl = '?' . http_build_query($queryParams);
                ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link bg-dark border-secondary text-white" href="<?php echo $nextUrl; ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<script>
    async function updateStatus(selectElement) {
        const assetId = selectElement.getAttribute('data-id');
        const newStatus = selectElement.value;
        const originalColor = selectElement.style.color;


        selectElement.style.opacity = '0.5';

        try {
            const response = await fetch('api/update_asset_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: assetId,
                    status: newStatus
                })
            });

            const result = await response.json();

            if (result.success) {

                const statusClasses = {
                    'working': 'text-success',
                    'repair': 'text-warning',
                    'missing': 'text-danger',
                    'retired': 'text-secondary'
                };


                selectElement.classList.remove('text-success', 'text-warning', 'text-danger', 'text-secondary', 'text-white');
                selectElement.classList.add(statusClasses[newStatus] || 'text-white');
            } else {
                alert('Error: ' + result.message);

                location.reload();
            }
        } catch (error) {
            console.error('Error updating status:', error);
            alert('Failed to connect to server.');
        } finally {
            selectElement.style.opacity = '1';
        }
    }
}

function toggleAssetSelectAll(source) {
    document.querySelectorAll('.asset-checkbox').forEach(cb => cb.checked = source.checked);
    updateAssetSelectedCount();
}

function updateAssetSelectedCount() {
    const count = document.querySelectorAll('.asset-checkbox:checked').length;
    const countEl = document.getElementById('asset-selected-count');
    if (countEl) {
        countEl.textContent = count + ' asset' + (count !== 1 ? 's' : '') + ' selected';
    }
}
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>