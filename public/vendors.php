<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$deleted_at_exists = false;
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM vendors LIKE 'deleted_at'");
    $deleted_at_exists = $col_check->rowCount() > 0;
} catch (Exception $e) {
    $deleted_at_exists = false;
}

if (isset($_POST['delete_id']) && $deleted_at_exists) {
    verify_csrf();
    $id = $_POST['delete_id'];

    $stmt = $pdo->prepare("SELECT name FROM vendors WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch();

    if ($vendor) {
        $stmt = $pdo->prepare("UPDATE vendors SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        log_action($pdo, $_SESSION['user_id'], 'DELETE_VENDOR', "Deleted Vendor: {$vendor['name']}");
        invalidate_dashboard_cache();
    }

    echo "<script>window.location.href='vendors.php';</script>";
}

if (isset($_POST['restore_id']) && $deleted_at_exists) {
    verify_csrf();
    $id = $_POST['restore_id'];

    $stmt = $pdo->prepare("SELECT name FROM vendors WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch();

    if ($vendor) {
        $stmt = $pdo->prepare("UPDATE vendors SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        log_action($pdo, $_SESSION['user_id'], 'RESTORE_VENDOR', "Restored Vendor: {$vendor['name']}");
    }

    echo "<script>window.location.href='vendors.php';</script>";
}

$search_query = $_GET['search'] ?? '';
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

$sql = "SELECT * FROM vendors WHERE 1=1";
$params = [];

if (!$show_deleted && $deleted_at_exists) {
    $sql .= " AND deleted_at IS NULL";
}

if ($search_query) {
    $sql .= " AND (name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY name ASC";

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
$vendors = $stmt->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Procurement</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Vendors & Suppliers</h1>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="vendors_add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-2"></i>Add Vendor
        </a>
    </div>
</div>

<div class="card mb-4 border-secondary border-opacity-10 bg-darker">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-12 col-md-8">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-white-50"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-dark border-secondary border-opacity-25 text-white placeholder-secondary" placeholder="Search vendors..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['show_deleted' => $show_deleted ? '0' : '1'])); ?>" class="btn btn-sm w-100 <?php echo $show_deleted ? 'btn-outline-danger' : 'btn-outline-warning'; ?>">
                    <i class="fas <?php echo $show_deleted ? 'fa-eye-slash' : 'fa-trash-restore'; ?> me-1"></i>
                    <?php echo $show_deleted ? 'Hide Deleted' : 'Show Deleted'; ?>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold" style="width: 25%;">Name</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="width: 20%;">Contact</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="width: 15%;">Phone</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="width: 20%;">Email</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold" style="width: 20%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $vendor): ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="text-white fw-bold"><?php echo htmlspecialchars($vendor['name']); ?></div>
                            <div class="text-white-50 small opacity-75"><?php echo htmlspecialchars($vendor['address'] ?: 'No address'); ?></div>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo htmlspecialchars($vendor['contact_person'] ?: 'N/A'); ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo htmlspecialchars($vendor['phone'] ?: 'N/A'); ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo htmlspecialchars($vendor['email'] ?: 'N/A'); ?></span>
                        </td>
                        <td class="pe-4 py-3 text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="vendors_edit.php?id=<?php echo $vendor['id']; ?>" class="btn btn-sm btn-icon btn-ghost-secondary" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php if ($show_deleted && !empty($vendor['deleted_at'])): ?>
                                    <form method="POST" onsubmit="return confirm('Restore this vendor?');" style="display:inline;">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="restore_id" value="<?php echo $vendor['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-ghost-success" title="Restore"><i class="fas fa-trash-restore"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure?');" style="display:inline;">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo $vendor['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($vendors)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-building fa-3x"></i></div>
                            <h6 class="text-white">No vendors found</h6>
                            <p class="text-white-50 small mb-0">Add a vendor to track suppliers and contacts.</p>
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
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> vendors
    </div>
    <nav aria-label="Vendors Pagination">
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

<?php require_once __DIR__ . '/../views/footer.php'; ?>