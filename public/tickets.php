<?php
require_once __DIR__ . '/../views/header.php';
require_login();
if (!has_privilege('view_tickets')) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && has_privilege('manage_tickets')) {
    verify_csrf();
    $action = clean_input($_POST['bulk_action']);
    $ticket_ids = $_POST['ticket_ids'] ?? [];

    if (empty($ticket_ids)) {
        $error = "No tickets selected.";
    } else {
        $status_map = [
            'status_open' => 'open',
            'status_in_progress' => 'in_progress',
            'status_resolved' => 'resolved',
            'status_closed' => 'closed'
        ];

        if (isset($status_map[$action])) {
            $new_status = $status_map[$action];
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_by = ? WHERE id IN (" . implode(',', array_fill(0, count($ticket_ids), '?')) . ")");
            $params = array_merge([$new_status, $_SESSION['user_id']], $ticket_ids);
            $stmt->execute($params);
            $success = count($ticket_ids) . " tickets updated to " . ucfirst(str_replace('_', ' ', $new_status)) . ".";
            log_action($pdo, $_SESSION['user_id'], 'BULK_UPDATE_TICKETS', "Bulk updated " . count($ticket_ids) . " tickets to $new_status");
            invalidate_dashboard_cache();
        }
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $status_filter = $_GET['status'] ?? '';
    $priority_filter = $_GET['priority'] ?? '';
    
    $sql = "SELECT t.id, t.subject, t.priority, t.status, t.due_date, u.full_name as creator, a.full_name as assignee, t.created_at
            FROM tickets t 
            JOIN users u ON t.user_id = u.id 
            LEFT JOIN users a ON t.assigned_to = a.id 
            WHERE 1=1";
    $params = [];
    
    if ($status_filter) {
        $sql .= " AND t.status = ?";
        $params[] = $status_filter;
    }
    if ($priority_filter) {
        $sql .= " AND t.priority = ?";
        $params[] = $priority_filter;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['ID', 'Subject', 'Priority', 'Status', 'Due Date', 'Created By', 'Assigned To', 'Created At'];
    $data = array_map(function($r) {
        return [$r['id'], $r['subject'], ucfirst($r['priority']), ucfirst(str_replace('_', ' ', $r['status'])), $r['due_date'] ?? '', $r['creator'], $r['assignee'] ?? 'Unassigned', $r['created_at']];
    }, $rows);
    export_csv('tickets', $headers, $data);
}


$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$sql = "SELECT t.*, u.full_name as creator, a.full_name as assignee 
        FROM tickets t 
        JOIN users u ON t.user_id = u.id 
        LEFT JOIN users a ON t.assigned_to = a.id 
        WHERE 1=1";
$params = [];

if ($status_filter) {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
}
if ($priority_filter) {
    $sql .= " AND t.priority = ?";
    $params[] = $priority_filter;
}

$sql .= " ORDER BY t.created_at DESC";

$items_per_page = 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

$last_id = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
$cursor_mode = $last_id > 0;

if ($cursor_mode) {
    $sql .= " AND t.id < :last_id";
    $params[':last_id'] = $last_id;
    $sql = str_replace('ORDER BY t.created_at DESC', 'ORDER BY t.id DESC', $sql);
} else {
    $count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as total_count";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);
    $sql .= " LIMIT $items_per_page OFFSET $offset";
}

$stmt = $pdo->prepare($sql);
if ($cursor_mode) {
    $stmt->execute($params);
} else {
    $stmt->execute($params);
}
$tickets = $stmt->fetchAll();

$next_cursor = !empty($tickets) ? $tickets[count($tickets) - 1]['id'] : 0;
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <h1 class="h3 mb-0 text-white fw-bold">Ticket Management</h1>
    <div class="d-flex gap-2">
        <a href="?export=csv<?php echo ($status_filter ? '&status=' . urlencode($status_filter) : '') . ($priority_filter ? '&priority=' . urlencode($priority_filter) : ''); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-export me-2"></i>Export CSV
        </a>
        <a href="ticket_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-2"></i>Create Ticket</a>
    </div>
</div>

<?php if (has_privilege('manage_tickets')): ?>
<div class="card mb-4 bg-dark border-secondary border-opacity-25">
    <div class="card-body">
        <form method="POST" id="bulk-action-form">
            <?php csrf_field(); ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-white-50 small text-uppercase fw-bold">Bulk Action</label>
                    <select name="bulk_action" class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-25" required>
                        <option value="">Select Action...</option>
                        <option value="status_open">Set Status: Open</option>
                        <option value="status_in_progress">Set Status: In Progress</option>
                        <option value="status_resolved">Set Status: Resolved</option>
                        <option value="status_closed">Set Status: Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-warning btn-sm w-100" onclick="return confirm('Apply bulk action to selected tickets?')">
                        <i class="fas fa-bolt me-1"></i>Apply
                    </button>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-white-50 small" id="selected-count">0 tickets selected</span>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<div class="card mb-4 bg-dark border-secondary border-opacity-25">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Status</label>
                <select name="status"
                    class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-25">
                    <option value="">All Statuses</option>
                    <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In
                        Progress</option>
                    <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved
                    </option>
                    <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Priority</label>
                <select name="priority"
                    class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-25">
                    <option value="">All Priorities</option>
                    <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="urgent" <?php echo $priority_filter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-secondary btn-sm w-100"><i class="fas fa-filter me-2"></i>Filter
                    Tickets</button>
            </div>
        </form>
    </div>
</div>

<div class="card bg-dark border-secondary">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">
                            <?php if (has_privilege('manage_tickets')): ?>
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                            <?php endif; ?>
                        </th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">ID</th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">Subject</th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">Priority</th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">Status</th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">SLA / Due</th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">Created By</th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">Assigned To</th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">Date</th>
                        <th class="border-secondary text-white opacity-75 small text-uppercase">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td class="border-secondary">
                                <?php if (has_privilege('manage_tickets')): ?>
                                    <input type="checkbox" class="ticket-checkbox" name="ticket_ids[]" value="<?php echo $ticket['id']; ?>" onchange="updateSelectedCount()">
                                <?php endif; ?>
                            </td>
                            <td class="border-secondary text-white-50">#<?php echo $ticket['id']; ?></td>
                            <td class="border-secondary fw-bold text-white">
                                <?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td class="border-secondary"><span
                                    class="badge rounded-pill <?php echo get_status_badge($ticket['priority']); ?>"><?php echo ucfirst($ticket['priority']); ?></span>
                            </td>
                            <td class="border-secondary"><span
                                    class="badge rounded-pill <?php echo get_status_badge($ticket['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                            </td>
                            <td class="text-nowrap border-secondary">
                                <?php
                                if ($ticket['status'] != 'resolved' && $ticket['status'] != 'closed' && !empty($ticket['due_date'])) {
                                    $due = strtotime($ticket['due_date']);
                                    $now = time();
                                    $diff = $due - $now;

                                    if ($diff < 0) {
                                        echo '<span class="badge bg-danger">Overdue</span>';
                                    } elseif ($diff < 3600) {
                                        echo '<span class="badge bg-warning text-dark">Due < 1h</span>';
                                    } else {
                                        echo '<span class="text-white-50 small">' . date('M d, H:i', $due) . '</span>';
                                    }
                                } else {
                                    echo '<span class="text-white-50 small">-</span>';
                                }
                                ?>
                            </td>
                            <td class="border-secondary text-white"><?php echo htmlspecialchars($ticket['creator']); ?></td>
                            <td class="border-secondary">
                                <?php if ($ticket['assignee']): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle-sm bg-secondary me-2 rounded-circle d-flex align-items-center justify-content-center text-white"
                                            style="width:24px;height:24px;font-size:10px;">
                                            <?php echo substr($ticket['assignee'], 0, 1); ?>
                                        </div>
                                        <span
                                            class="small text-white"><?php echo htmlspecialchars($ticket['assignee']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary opacity-50">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="border-secondary text-white-50 small">
                                <?php echo format_date($ticket['created_at']); ?></td>
                            <td class="border-secondary">
                                <a href="ticket_details.php?id=<?php echo $ticket['id']; ?>"
                                    class="btn btn-sm btn-outline-primary shadow-none">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-white-50">
                                <i class="fas fa-ticket-alt fa-3x mb-3 opacity-25"></i><br>
                                No tickets found matching your criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($cursor_mode): ?>
<div class="d-flex justify-content-between align-items-center mt-4">
    <div class="text-white-50 small">
        Showing latest tickets via cursor pagination
    </div>
    <?php if ($next_cursor > 0): ?>
    <a href="?last_id=<?php echo $next_cursor; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $priority_filter ? '&priority=' . urlencode($priority_filter) : ''; ?>" class="btn btn-sm btn-outline-primary">Load More</a>
    <?php endif; ?>
</div>
<?php elseif ($total_pages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-4">
    <div class="text-white-50 small">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> tickets
    </div>
    <nav aria-label="Ticket Pagination">
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

<script>
function toggleSelectAll(source) {
    document.querySelectorAll('.ticket-checkbox').forEach(cb => cb.checked = source.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.ticket-checkbox:checked').length;
    const countEl = document.getElementById('selected-count');
    if (countEl) {
        countEl.textContent = count + ' ticket' + (count !== 1 ? 's' : '') + ' selected';
    }
}
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>