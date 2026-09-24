<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
if (!has_privilege('view_reports')) {
    redirect('dashboard.php');
}


if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="consumables_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Item Name', 'Category', 'Brand', 'Quantity', 'Unit', 'Action', 'Current Stock', 'Department', 'Remarks', 'User']);
    
    $export_sql = "SELECT l.*, c.item_name, c.category, c.brand, c.unit, c.quantity as current_stock, u.full_name as user_name 
            FROM consumable_logs l 
            JOIN consumables c ON l.consumable_id = c.id 
            JOIN users u ON l.user_id = u.id 
            WHERE l.action_type IN ('ADD', 'DEDUCT') AND c.deleted_at IS NULL ORDER BY l.created_at DESC";
    
    $stmt = $pdo->query($export_sql);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['created_at'],
            $row['item_name'],
            $row['category'],
            $row['brand'],
            $row['quantity'],
            $row['unit'],
            ucfirst($row['action_type']),
            $row['current_stock'] ?? 0,
            $row['department'],
            $row['remarks'],
            $row['user_name']
        ]);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/../views/header.php';


$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$dept_filter = $_GET['department'] ?? '';
$search_query = $_GET['search'] ?? '';
$item_filter = $_GET['item_id'] ?? '';
$action_filter = $_GET['action_type'] ?? '';

$items_per_page = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

$sql = "SELECT l.*, c.item_name, c.category, c.brand, c.unit, c.quantity as current_stock, u.full_name as user_name 
        FROM consumable_logs l 
        JOIN consumables c ON l.consumable_id = c.id 
        JOIN users u ON l.user_id = u.id 
        WHERE l.action_type IN ('ADD', 'DEDUCT') AND c.deleted_at IS NULL";

$params = [];

if ($start_date) {
    $sql .= " AND DATE(l.created_at) >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $sql .= " AND DATE(l.created_at) <= ?";
    $params[] = $end_date;
}
if ($dept_filter) {
    $sql .= " AND l.department LIKE ?";
    $params[] = "%$dept_filter%";
}
if ($item_filter) {
    $sql .= " AND l.consumable_id = ?";
    $params[] = (int)$item_filter;
}
if ($action_filter) {
    $sql .= " AND l.action_type = ?";
    $params[] = $action_filter;
}
if ($search_query) {
    $sql .= " AND (c.item_name LIKE ? OR c.brand LIKE ? OR l.remarks LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$count_sql = "SELECT COUNT(*) FROM consumable_logs l 
              JOIN consumables c ON l.consumable_id = c.id 
              JOIN users u ON l.user_id = u.id 
              WHERE l.action_type IN ('ADD', 'DEDUCT') AND c.deleted_at IS NULL";
$count_params = [];
if ($start_date) { $count_sql .= " AND DATE(l.created_at) >= ?"; $count_params[] = $start_date; }
if ($end_date) { $count_sql .= " AND DATE(l.created_at) <= ?"; $count_params[] = $end_date; }
if ($dept_filter) { $count_sql .= " AND l.department LIKE ?"; $count_params[] = "%$dept_filter%"; }
if ($item_filter) { $count_sql .= " AND l.consumable_id = ?"; $count_params[] = (int)$item_filter; }
if ($action_filter) { $count_sql .= " AND l.action_type = ?"; $count_params[] = $action_filter; }
if ($search_query) {
    $count_sql .= " AND (c.item_name LIKE ? OR c.brand LIKE ? OR l.remarks LIKE ?)";
    $term = "%$search_query%";
    $count_params[] = $term; $count_params[] = $term; $count_params[] = $term;
}

$total_logs = 0;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_logs = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_logs / $items_per_page);

$sql .= " ORDER BY l.created_at DESC LIMIT $items_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$sum_sql = "SELECT 
            SUM(CASE WHEN l.action_type = 'ADD' THEN l.quantity ELSE 0 END) as total_adds,
            SUM(CASE WHEN l.action_type = 'DEDUCT' THEN l.quantity ELSE 0 END) as total_deducts
            FROM consumable_logs l 
            JOIN consumables c ON l.consumable_id = c.id 
            JOIN users u ON l.user_id = u.id 
            WHERE l.action_type IN ('ADD', 'DEDUCT') AND c.deleted_at IS NULL";
$sum_params = [];
if ($start_date) { $sum_sql .= " AND DATE(l.created_at) >= ?"; $sum_params[] = $start_date; }
if ($end_date) { $sum_sql .= " AND DATE(l.created_at) <= ?"; $sum_params[] = $end_date; }
if ($dept_filter) { $sum_sql .= " AND l.department LIKE ?"; $sum_params[] = "%$dept_filter%"; }
if ($item_filter) { $sum_sql .= " AND l.consumable_id = ?"; $sum_params[] = (int)$item_filter; }
if ($action_filter) { $sum_sql .= " AND l.action_type = ?"; $sum_params[] = $action_filter; }
if ($search_query) {
    $sum_sql .= " AND (c.item_name LIKE ? OR c.brand LIKE ? OR l.remarks LIKE ?)";
    $term = "%$search_query%";
    $sum_params[] = $term; $sum_params[] = $term; $sum_params[] = $term;
}

$sum_stmt = $pdo->prepare($sum_sql);
$sum_stmt->execute($sum_params);
$sums = $sum_stmt->fetch();
$total_adds = (int)($sums['total_adds'] ?? 0);
$total_deducts = (int)($sums['total_deducts'] ?? 0);
$net_change = $total_adds - $total_deducts;

$depts = $pdo->query("SELECT DISTINCT department FROM consumable_logs WHERE action_type IN ('ADD', 'DEDUCT') AND department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

$all_items = $pdo->query("SELECT id, item_name, brand FROM consumables WHERE deleted_at IS NULL ORDER BY item_name ASC")->fetchAll();

$can_edit = has_privilege('edit_consumable_logs');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4 no-print">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Reports</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Consumables Transaction Report</h1>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-print me-2"></i>Print
        </button>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-file-csv me-2"></i>Export CSV
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-success border-opacity-25 bg-dark">
            <div class="card-body text-center">
                <div class="text-success small text-uppercase fw-bold">Total Added</div>
                <div class="display-6 fw-bold text-white">+<?php echo $total_adds; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning border-opacity-25 bg-dark">
            <div class="card-body text-center">
                <div class="text-warning small text-uppercase fw-bold">Total Deducted</div>
                <div class="display-6 fw-bold text-white">-<?php echo $total_deducts; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-<?php echo $net_change >= 0 ? 'success' : 'danger'; ?> border-opacity-25 bg-dark">
            <div class="card-body text-center">
                <div class="small text-uppercase fw-bold text-<?php echo $net_change >= 0 ? 'success' : 'danger'; ?>">Net Change</div>
                <div class="display-6 fw-bold text-white"><?php echo ($net_change >= 0 ? '+' : '') . $net_change; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-secondary border-opacity-25 bg-dark">
            <div class="card-body text-center">
                <div class="text-white-50 small text-uppercase fw-bold">Transactions</div>
                <div class="display-6 fw-bold text-white"><?php echo $total_logs; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 border-secondary border-opacity-10 bg-darker no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label text-white-50 small fw-bold">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-white-50 small fw-bold">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-white-50 small fw-bold">Item</label>
                <select name="item_id" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Items</option>
                    <?php foreach ($all_items as $i): ?>
                        <option value="<?php echo $i['id']; ?>" <?php echo $item_filter == $i['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($i['item_name'] . ' (' . $i['brand'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-white-50 small fw-bold">Action</label>
                <select name="action_type" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Actions</option>
                    <option value="ADD" <?php echo $action_filter == 'ADD' ? 'selected' : ''; ?>>Add Stock</option>
                    <option value="DEDUCT" <?php echo $action_filter == 'DEDUCT' ? 'selected' : ''; ?>>Deduct</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-white-50 small fw-bold">Department</label>
                <select name="department" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $dept_filter == $d ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-white-50 small fw-bold">Search</label>
                <input type="text" name="search" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Item, brand..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Filter</button>
            </div>
            <?php if ($start_date || $end_date || $dept_filter || $item_filter || $action_filter || $search_query): ?>
                <div class="col-md-2">
                    <a href="consumables_report.php" class="btn btn-outline-danger btn-sm w-100">Reset</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="glass-card border-secondary border-opacity-10 bg-dark print-m-0">
    <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-list me-2 text-primary"></i>Transaction Logs</h5>
        <span class="badge bg-primary bg-opacity-10 text-primary"><?php echo $total_logs; ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 small fw-bold">Timestamp</th>
                    <th class="py-3 text-white-50 small fw-bold">Item & Brand</th>
                    <th class="py-3 text-white-50 small fw-bold text-center">Action</th>
                    <th class="py-3 text-white-50 small fw-bold text-center">Qty</th>
                    <th class="py-3 text-white-50 small fw-bold text-center">Current Stock</th>
                    <th class="py-3 text-white-50 small fw-bold">Department</th>
                    <th class="py-3 text-white-50 small fw-bold">Remarks</th>
                    <th class="py-3 text-white-50 small fw-bold">User</th>
                    <?php if ($can_edit): ?>
                        <th class="pe-4 py-3 text-white-50 small fw-bold text-end">Action</th>
                    <?php else: ?>
                        <th class="pe-4 py-3"></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="ps-4 py-3 small font-monospace text-white-50">
                            <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="py-3">
                            <div class="text-white fw-medium"><?php echo htmlspecialchars($log['item_name']); ?></div>
                            <div class="text-white-50 small opacity-75"><?php echo htmlspecialchars($log['category']); ?> • <?php echo htmlspecialchars($log['brand']); ?></div>
                        </td>
                        <td class="py-3 text-center">
                            <?php if ($log['action_type'] == 'ADD'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-uppercase" style="font-size: 0.7rem;">Add Stock</span>
                            <?php else: ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 text-uppercase" style="font-size: 0.7rem;">Deduct</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-center">
                            <span class="text-<?php echo $log['action_type'] == 'ADD' ? 'success' : 'warning'; ?> fw-bold"><?php echo $log['quantity']; ?></span>
                            <span class="text-white-50 small"><?php echo htmlspecialchars($log['unit']); ?></span>
                        </td>
                        <td class="py-3 text-center">
                            <?php 
                                $stock = (int)($log['current_stock'] ?? 0);
                                $stockClass = $stock <= 0 ? 'text-danger' : 'text-white';
                            ?>
                            <span class="fw-bold <?php echo $stockClass; ?>"><?php echo $stock; ?></span>
                            <span class="text-white-50 small"><?php echo htmlspecialchars($log['unit']); ?>s</span>
                        </td>
                        <td class="py-3">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2">
                                <?php echo htmlspecialchars($log['department'] ?: 'N/A'); ?>
                            </span>
                        </td>
                        <td class="py-3">
                            <div class="text-white-50 small text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($log['remarks']); ?>">
                                <?php echo htmlspecialchars($log['remarks'] ?: '-'); ?>
                            </div>
                        </td>
                        <td class="py-3">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle-sm bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px; height:24px; font-size: 0.7rem;">
                                    <?php echo substr($log['user_name'], 0, 1); ?>
                                </div>
                                <span class="small text-white-50"><?php echo htmlspecialchars($log['user_name']); ?></span>
                            </div>
                        </td>
                        <?php if ($can_edit): ?>
                            <td class="pe-4 py-3 text-end">
                                <a href="consumable_log_edit.php?id=<?php echo $log['id']; ?>" class="btn btn-sm btn-outline-primary bg-dark border-secondary border-opacity-25" title="Edit Log">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="<?php echo $can_edit ? '9' : '8'; ?>" class="text-center py-5">
                            <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-file-invoice fa-3x"></i></div>
                            <h6 class="text-white">No records found for the selected criteria</h6>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<div class="card-footer bg-darker border-top border-secondary border-opacity-10 py-3 no-print">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-white-50 small">
            Showing <span class="text-white fw-medium"><?php echo $total_logs > 0 ? $offset + 1 : 0; ?></span> to <span class="text-white fw-medium"><?php echo min($offset + $items_per_page, $total_logs); ?></span> of <span class="text-white fw-medium"><?php echo $total_logs; ?></span> logs
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $query_params = $_GET;
                $query_params['page'] = $page - 1;
                $prev_disabled = $page <= 1 ? 'disabled' : '';
                ?>
                <li class="page-item <?php echo $prev_disabled; ?>">
                    <a class="page-link bg-dark border-secondary border-opacity-25 text-white-50 hover-text-white" href="<?php echo $page > 1 ? '?' . http_build_query($query_params) : '#'; ?>">Previous</a>
                </li>
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for($i = $start; $i <= $end; $i++): 
                    $query_params['page'] = $i;
                ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link <?php echo $page == $i ? 'bg-primary border-primary text-white' : 'bg-dark border-secondary border-opacity-25 text-white-50 hover-text-white'; ?>" href="?<?php echo http_build_query($query_params); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php
                $query_params['page'] = $page + 1;
                $next_disabled = $page >= $total_pages ? 'disabled' : '';
                ?>
                <li class="page-item <?php echo $next_disabled; ?>">
                    <a class="page-link bg-dark border-secondary border-opacity-25 text-white-50 hover-text-white" href="<?php echo $page < $total_pages ? '?' . http_build_query($query_params) : '#'; ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .card { border: none !important; background: transparent !important; }
    .table-dark { color: black !important; background: white !important; }
    .table-dark th { background: #eee !important; color: black !important; border-bottom: 2px solid #333 !important; }
    .table-dark td { border-bottom: 1px solid #ddd !important; color: black !important; }
    .badge { border: 1px solid #333 !important; color: black !important; background: transparent !important; }
    .text-white-50, .text-white { color: black !important; }
    .bg-darker { background: #f8f9fa !important; }
    body { background: white !important; }
}
</style>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
