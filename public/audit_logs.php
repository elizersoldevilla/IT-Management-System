<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
if (!has_role('admin')) {
    redirect('dashboard.php');
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "SELECT a.created_at, u.full_name, u.role, a.action, a.details, a.ip_address 
            FROM audit_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            WHERE 1=1";
    $params = [];
    
    if ($start_date) {
        $sql .= " AND DATE(a.created_at) >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql .= " AND DATE(a.created_at) <= ?";
        $params[] = $end_date;
    }
    if ($user_id !== '' && $user_id !== null) {
        $sql .= " AND a.user_id = ?";
        $params[] = (int)$user_id;
    }
    if ($action) {
        $sql .= " AND a.action = ?";
        $params[] = $action;
    }
    if ($search) {
        $sql .= " AND (a.details LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ?)";
        $term = "%$search%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['Timestamp', 'User', 'Role', 'Action', 'Details', 'IP Address'];
    $data = array_map(function($r) {
        return [$r['created_at'], $r['full_name'] ?: 'System', $r['role'] ?: '-', $r['action'], $r['details'], $r['ip_address'] ?? ''];
    }, $rows);
    export_csv('audit_logs', $headers, $data);
}

require_once __DIR__ . '/../views/header.php';

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;


$sql = "SELECT a.*, u.full_name, u.role 
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($start_date) {
    $sql .= " AND DATE(a.created_at) >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $sql .= " AND DATE(a.created_at) <= ?";
    $params[] = $end_date;
}
if ($user_id !== '' && $user_id !== null) {
    $sql .= " AND a.user_id = ?";
    $params[] = (int)$user_id;
}
if ($action) {
    $sql .= " AND a.action = ?";
    $params[] = $action;
}
if ($search) {
    $sql .= " AND (a.details LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$count_sql = "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1";
$count_params = [];
if ($start_date) { $count_sql .= " AND DATE(a.created_at) >= ?"; $count_params[] = $start_date; }
if ($end_date) { $count_sql .= " AND DATE(a.created_at) <= ?"; $count_params[] = $end_date; }
if ($user_id !== '' && $user_id !== null) { $count_sql .= " AND a.user_id = ?"; $count_params[] = (int)$user_id; }
if ($action) { $count_sql .= " AND a.action = ?"; $count_params[] = $action; }
if ($search) {
    $count_sql .= " AND (a.details LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ?)";
    $term = "%$search%";
    $count_params[] = $term; $count_params[] = $term; $count_params[] = $term;
}
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_logs = $count_stmt->fetchColumn();

$sql .= " ORDER BY a.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$total_pages = ceil($total_logs / $limit);

$users = $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row align-items-end mb-4">
    <div class="col-md-6">
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Security & Activity</h6>
        <h1 class="h2 mb-0 fw-bold text-white">System Audit Logs</h1>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="?export=csv<?php
            echo ($start_date ? '&start_date=' . urlencode($start_date) : '');
            echo ($end_date ? '&end_date=' . urlencode($end_date) : '');
            echo ($user_id !== '' ? '&user_id=' . urlencode($user_id) : '');
            echo ($action ? '&action=' . urlencode($action) : '');
            echo ($search ? '&search=' . urlencode($search) : '');
        ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-export me-2"></i>Export CSV
        </a>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark mb-4">
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
            <div class="col-md-3">
                <label class="form-label text-white-50 small fw-bold">User</label>
                <select name="user_id" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user_id == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo ucfirst($u['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-white-50 small fw-bold">Action</label>
                <select name="action" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $action === $a ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-white-50 small fw-bold">Search</label>
                <input type="text" name="search" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Details or IP..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Filter</button>
            </div>
            <?php if ($start_date || $end_date || $user_id !== '' || $action || $search): ?>
                <div class="col-md-1">
                    <a href="audit_logs.php" class="btn btn-outline-danger btn-sm w-100">Reset</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 20%;">Time</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 15%;">User</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 15%;">Action</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 35%;">Details</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 15%;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <?php
                    $action_raw = strtoupper($log['action']);
                    $is_update = str_contains($action_raw, 'UPDATE') || str_contains($action_raw, 'EDIT');
                    $row_class = $is_update ? 'cursor-pointer hover-bg-light-10' : '';
                    $row_attrs = $is_update ? 'data-bs-toggle="modal" data-bs-target="#logDetailModal" data-details="' . htmlspecialchars($log['details'], ENT_QUOTES) . '" data-action="' . htmlspecialchars($log['action'], ENT_QUOTES) . '"' : '';
                ?>
                <tr class="<?php echo $row_class; ?>" <?php echo $row_attrs; ?>>
                    <td class="ps-4 py-3">
                        <div class="d-flex flex-column">
                            <span class="text-white small fw-medium"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></span>
                            <span class="text-white-50 small opacity-50"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle flex-shrink-0 bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:0.8rem;">
                                <?php echo $log['full_name'] ? substr($log['full_name'], 0, 1) : '?'; ?>
                            </div>
                            <div class="text-truncate">
                                <?php if ($log['full_name']): ?>
                                    <div class="text-white small fw-medium"><?php echo htmlspecialchars($log['full_name']); ?></div>
                                    <div class="text-white-50 small opacity-50" style="font-size: 0.7rem;"><?php echo ucfirst($log['role']); ?></div>
                                <?php else: ?>
                                    <span class="text-white-50 small fst-italic">System / Unknown</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="py-3">
                        <?php
                        $action = strtoupper($log['action']);
                        $badgeClass = 'bg-secondary text-secondary';
                        $icon = 'fa-circle-info';

                        if (str_contains($action, 'DELETE')) { $badgeClass = 'bg-danger text-danger'; $icon = 'fa-trash'; }
                        elseif (str_contains($action, 'CREATE') || str_contains($action, 'ADD')) { $badgeClass = 'bg-success text-success'; $icon = 'fa-plus'; }
                        elseif (str_contains($action, 'UPDATE') || str_contains($action, 'EDIT')) { $badgeClass = 'bg-warning text-warning'; $icon = 'fa-pen'; }
                        elseif (str_contains($action, 'LOGIN') || str_contains($action, 'LOGOUT')) { $badgeClass = 'bg-info text-info'; $icon = 'fa-key'; }
                        ?>
                        <div class="d-flex align-items-center <?php echo str_replace('bg-', 'text-', $badgeClass); ?> opacity-75">
                            <i class="fas <?php echo $icon; ?> me-2 small"></i>
                            <span class="small fw-bold text-uppercase" style="font-size: 0.7rem;"><?php echo htmlspecialchars($log['action']); ?></span>
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="text-white-50 small" style="white-space: normal; word-break: break-word; line-height: 1.5;">
                            <?php echo htmlspecialchars($log['details']); ?>
                        </div>
                    </td>
                    <td class="pe-4 py-3 text-end">
                        <span class="font-monospace text-white-50 small bg-dark px-2 py-1 rounded border border-secondary border-opacity-10">
                            <?php echo htmlspecialchars($log['ip_address']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-shield-alt fa-3x"></i></div>
                        <h6 class="text-white">No audit logs found</h6>
                        <p class="text-white-50 small mb-0">System activity will appear here.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-darker border-top border-secondary border-opacity-10 py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-white-50 small">
                Showing <span class="text-white fw-medium"><?php echo $total_logs > 0 ? $offset + 1 : 0; ?></span> to <span class="text-white fw-medium"><?php echo min($offset + $limit, $total_logs); ?></span> of <span class="text-white fw-medium"><?php echo $total_logs; ?></span> logs
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
</div>

<div class="modal fade" id="logDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary border-opacity-25">
            <div class="modal-header border-secondary border-opacity-25 bg-secondary bg-opacity-10">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-file-invoice me-2 text-primary"></i>Log Detail</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="logDetailContent" class="text-white-50"></div>
            </div>
            <div class="modal-footer border-secondary border-opacity-25 bg-secondary bg-opacity-10">
                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var logModal = document.getElementById('logDetailModal');
    var logContent = document.getElementById('logDetailContent');

    if (logModal && logContent) {
        logModal.addEventListener('show.bs.modal', function (event) {
            var row = event.relatedTarget;
            var details = row.getAttribute('data-details') || '';
            var action = row.getAttribute('data-action') || '';

            var html = '<div class="mb-2"><span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 small text-uppercase fw-bold">' + action + '</span></div>';

            if (details.indexOf(' → ') !== -1 || details.indexOf(';') !== -1) {
                var changes = details.split('; ');
                html += '<div class="table-responsive"><table class="table table-dark table-hover align-middle mb-0">';
                html += '<thead class="bg-darker"><tr><th class="text-white-50 small fw-bold ps-3">Field</th><th class="text-white-50 small fw-bold">Before</th><th class="text-white-50 small fw-bold">After</th></tr></thead><tbody>';

                changes.forEach(function (change) {
                    if (!change.trim()) return;
                    var field = '';
                    var before = '';
                    var after = '';

                    if (change.indexOf(' → ') !== -1) {
                        var parts = change.split(' → ');
                        var leftParts = parts[0].split(': ');
                        field = leftParts[0] || '';
                        before = leftParts.slice(1).join(': ') || '(none)';
                        after = parts.slice(1).join(' → ') || '(none)';
                    } else {
                        field = change;
                        before = '-';
                        after = 'Updated';
                    }

                    html += '<tr>';
                    html += '<td class="text-white small fw-medium ps-3">' + field + '</td>';
                    html += '<td class="text-white-50 small">' + before + '</td>';
                    html += '<td class="text-white small fw-medium">' + after + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table></div>';
            } else {
                html += '<p class="text-white-50 small mb-0" style="white-space: pre-wrap;">' + details + '</p>';
            }

            logContent.innerHTML = html;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
