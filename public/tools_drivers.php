<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('manage_assets') && !has_privilege('view_tickets')) {
    redirect('dashboard.php');
}

$resource_categories = ['Driver', 'Tool', 'Firmware', 'Utility'];

$system_resources_table_exists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'system_resources'");
    $system_resources_table_exists = $check->rowCount() > 0;
} catch (Exception $e) {
    $system_resources_table_exists = false;
}

if (!$system_resources_table_exists) {
    echo '<div class="alert alert-warning"><strong>Setup required:</strong> The repository database table has not been created yet. <a href="update_db.php" class="alert-link">Run database update</a> to continue.</div>';
    require_once __DIR__ . '/../views/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['upload'])) {
        if (empty($_FILES['resource_file']['tmp_name']) || $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK) {
            set_toast('Please select a file to upload.', 'danger');
        } else {
            $saved = secure_upload($_FILES['resource_file'], __DIR__ . '/../uploads/');
            if ($saved) {
                $name = clean_input($_POST['name']);
                $category = clean_input($_POST['category']);
                $version = clean_input($_POST['version']);
                $description = clean_input($_POST['description']);
                $related_asset_id = !empty($_POST['related_asset_id']) ? (int) $_POST['related_asset_id'] : null;

                $stmt = $pdo->prepare("INSERT INTO system_resources (name, category, version, file_path, description, related_asset_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $category, $version, $saved, $description, $related_asset_id, $_SESSION['user_id']]);
                log_action($pdo, $_SESSION['user_id'], 'UPLOAD_RESOURCE', "Uploaded resource: $name ($category)");
                set_toast('Resource uploaded successfully.', 'success');
            } else {
                set_toast('Upload failed. Ensure file type is allowed and size is under 5MB.', 'danger');
            }
        }
        echo "<script>window.location.href='tools_drivers.php';</script>";
        exit;
    }

    if (isset($_POST['delete_id'])) {
        verify_csrf();
        $id = (int) $_POST['delete_id'];
        $stmt = $pdo->prepare("SELECT name FROM system_resources WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $resource = $stmt->fetch();
        if ($resource) {
            $stmt = $pdo->prepare("UPDATE system_resources SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            log_action($pdo, $_SESSION['user_id'], 'DELETE_RESOURCE', "Deleted resource: {$resource['name']}");
            set_toast('Resource deleted.', 'info');
        }
        echo "<script>window.location.href='tools_drivers.php';</script>";
        exit;
    }

    if (isset($_POST['restore_id'])) {
        verify_csrf();
        $id = (int) $_POST['restore_id'];
        $stmt = $pdo->prepare("SELECT name FROM system_resources WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$id]);
        $resource = $stmt->fetch();
        if ($resource) {
            $stmt = $pdo->prepare("UPDATE system_resources SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            log_action($pdo, $_SESSION['user_id'], 'RESTORE_RESOURCE', "Restored resource: {$resource['name']}");
            set_toast('Resource restored.', 'success');
        }
        echo "<script>window.location.href='tools_drivers.php';</script>";
        exit;
    }
}

$category_filter = $_GET['category'] ?? '';
$search_query = $_GET['search'] ?? '';
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

$sql = "SELECT r.*, u.full_name as uploader_name FROM system_resources r LEFT JOIN users u ON r.uploaded_by = u.id WHERE 1=1";
$params = [];

if (!$show_deleted) {
    $sql .= " AND r.deleted_at IS NULL";
}

if ($category_filter) {
    $sql .= " AND r.category = ?";
    $params[] = $category_filter;
}

if ($search_query) {
    $sql .= " AND (r.name LIKE ? OR r.description LIKE ? OR r.version LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY r.created_at DESC";

$items_per_page = 12;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

$count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as total_count";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$sql .= " LIMIT $items_per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

echo '<div style="background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;margin-bottom:12px;font-family:monospace;font-size:12px;">';
echo '<strong>DEBUG tools_drivers</strong><br>';
echo 'Table exists: ' . ($system_resources_table_exists ? 'yes' : 'no') . '<br>';
echo 'SQL: ' . htmlspecialchars($sql) . '<br>';
echo 'Params: ' . htmlspecialchars(json_encode($params)) . '<br>';
echo 'Total items: ' . htmlspecialchars((string)$total_items) . '<br>';
echo 'Resources fetched: ' . count($resources) . '<br>';
if ($resources) {
    echo 'First resource: ' . htmlspecialchars($resources[0]['name'] ?? '') . ' | file=' . htmlspecialchars($resources[0]['file_path'] ?? '') . ' | deleted=' . htmlspecialchars($resources[0]['deleted_at'] ?? '') . '<br>';
}
echo '</div>';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Repository</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Tools & Drivers</h1>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if (has_privilege('manage_assets')): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-upload me-2"></i>Upload Resource
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4 border-secondary border-opacity-10 bg-darker">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-white-50"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-dark border-secondary border-opacity-25 text-white placeholder-secondary" placeholder="Search resources..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="category" class="form-select form-select-sm bg-dark border-secondary border-opacity-25 text-white">
                    <option value="" class="bg-dark">Category: All</option>
                    <?php foreach ($resource_categories as $cat): ?>
                        <option value="<?php echo $cat; ?>" class="bg-dark text-white" <?php echo $category_filter === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['show_deleted' => $show_deleted ? '0' : '1'])); ?>" class="btn btn-sm w-100 <?php echo $show_deleted ? 'btn-outline-danger' : 'btn-outline-warning'; ?>">
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
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 25%;">Name</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 12%;">Category</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 10%;">Version</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 25%;">Description</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 12%;">Uploaded By</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 16%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resources as $resource): ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="text-white fw-bold"><?php echo htmlspecialchars($resource['name']); ?></div>
                            <div class="text-white-50 small opacity-75"><?php echo date('M d, Y', strtotime($resource['created_at'])); ?></div>
                        </td>
                        <td class="py-3">
                            <?php
                                $badge = 'bg-secondary text-white-50';
                                if ($resource['category'] === 'Driver') $badge = 'bg-primary text-white bg-opacity-75';
                                elseif ($resource['category'] === 'Tool') $badge = 'bg-success text-white bg-opacity-75';
                                elseif ($resource['category'] === 'Firmware') $badge = 'bg-warning text-dark bg-opacity-75';
                                elseif ($resource['category'] === 'Utility') $badge = 'bg-info text-dark bg-opacity-75';
                            ?>
                            <span class="badge rounded-pill <?php echo $badge; ?>"><?php echo htmlspecialchars($resource['category']); ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small font-monospace"><?php echo htmlspecialchars($resource['version'] ?: 'N/A'); ?></span>
                        </td>
                        <td class="py-3">
                            <div class="text-white-50 small text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($resource['description']); ?>">
                                <?php echo htmlspecialchars($resource['description'] ?: '-'); ?>
                            </div>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo htmlspecialchars($resource['uploader_name'] ?? 'System'); ?></span>
                        </td>
                        <td class="pe-4 py-3 text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="serve_file.php?file=<?php echo urlencode($resource['file_path']); ?>" target="_blank" class="btn btn-sm btn-icon btn-ghost-secondary" title="Download"><i class="fas fa-download"></i></a>
                                <?php if (has_privilege('manage_assets')): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-icon btn-ghost-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow-lg" style="z-index: 1060; min-width: 10rem;">
                                            <?php if ($show_deleted && !empty($resource['deleted_at'])): ?>
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('Restore this resource?');" style="display:block;">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="restore_id" value="<?php echo $resource['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-success"><i class="fas fa-trash-restore me-2 opacity-75"></i> Restore</button>
                                                    </form>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('Are you sure?');" style="display:block;">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="delete_id" value="<?php echo $resource['id']; ?>">
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
                <?php if (empty($resources)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-box-open fa-3x"></i></div>
                            <h6 class="text-white">No resources found</h6>
                            <p class="text-white-50 small mb-0">Upload a file or adjust your filters</p>
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
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> resources
        </div>
        <nav aria-label="Resources Pagination">
            <ul class="pagination pagination-sm mb-0">
                <?php
                $queryParams = $_GET;
                $queryParams['page'] = max(1, $page - 1);
                $prevUrl = '?' . http_build_query($queryParams);
                ?>
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link bg-dark border-secondary text-white" href="<?php echo $prevUrl; ?>" tabindex="-1">Previous</a>
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
                        <a class="page-link <?php echo ($page == $i) ? 'bg-primary border-primary' : 'bg-dark border-secondary text-white'; ?>" href="<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
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

<?php if (has_privilege('manage_assets')): ?>
<div class="modal fade" id="uploadModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="background:#1e293b; color:#e2e8f0;">
            <div class="modal-header border-secondary border-opacity-25" style="background:#0f172a;">
                <h5 class="modal-title fw-bold text-white mb-0" id="uploadModalLabel"><i class="fas fa-upload me-2 text-primary"></i>Upload Resource</h5>
                <button type="button" class="btn btn-icon btn-sm btn-ghost-secondary text-white" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label text-white small fw-bold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-dark border-secondary border-opacity-25 text-white" required placeholder="e.g. Canon Printer Driver">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-white small fw-bold">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select form-select-sm bg-dark border-secondary border-opacity-25 text-white" required>
                                <?php foreach ($resource_categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" class="bg-dark text-white"><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white small fw-bold">Version</label>
                            <input type="text" name="version" class="form-control bg-dark border-secondary border-opacity-25 text-white" placeholder="e.g. 3.2.1">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-white small fw-bold">Description</label>
                        <textarea name="description" rows="3" class="form-control bg-dark border-secondary border-opacity-25 text-white" placeholder="Brief description or usage notes"></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-white small fw-bold">File <span class="text-danger">*</span></label>
                        <input type="file" name="resource_file" class="form-control bg-dark border-secondary border-opacity-25 text-white" required>
                        <div class="form-text text-white-50">Max file size: 5MB. All file types are allowed.</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-25" style="background:#0f172a;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload" class="btn btn-primary btn-sm">Upload Resource</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../views/footer.php'; ?>