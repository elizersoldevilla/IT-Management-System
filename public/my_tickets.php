<?php
require_once __DIR__ . '/../views/header.php';
require_login();

$user_id = $_SESSION['user_id'];

$items_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

$last_id = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
$cursor_mode = $last_id > 0;

if ($cursor_mode) {
    $stmt = $pdo->prepare("
        SELECT t.*, a.full_name as assignee 
        FROM tickets t 
        LEFT JOIN users a ON t.assigned_to = a.id 
        WHERE t.user_id = ? AND t.id < ? 
        ORDER BY t.id DESC 
        LIMIT $items_per_page
    ");
    $stmt->execute([$user_id, $last_id]);
    $tickets = $stmt->fetchAll();
    $next_cursor = !empty($tickets) ? $tickets[count($tickets) - 1]['id'] : 0;
} else {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    $stmt = $pdo->prepare("
        SELECT t.*, a.full_name as assignee 
        FROM tickets t 
        LEFT JOIN users a ON t.assigned_to = a.id 
        WHERE t.user_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT $items_per_page OFFSET $offset
    ");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll();
}
?>


<div class="row align-items-end mb-4">
    <div class="col-md-6">
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Support Console</h6>
        <h1 class="h2 mb-0 fw-bold text-white">My Support Tickets</h1>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="ticket_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-2"></i>Submit New Ticket</a>
    </div>
</div>


<div class="card border-secondary border-opacity-10 bg-dark">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 10%;">ID</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 35%;">Subject</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 15%;">Priority</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 15%;">Status</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 15%;">Assigned To</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 10%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td class="ps-4 py-3">
                        <span class="font-monospace text-white-50 small">#<?php echo $ticket['id']; ?></span>
                    </td>
                    <td class="py-3">
                        <div class="d-flex flex-column">
                            <span class="text-white fw-medium text-truncate me-2" style="max-width: 300px;"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                            <span class="text-white-50 small opacity-50" style="font-size: 0.75rem;"><i class="far fa-clock me-1"></i> <?php echo format_date($ticket['created_at']); ?></span>
                        </div>
                    </td>
                    <td class="py-3">
                        <?php 
                        $priorityClass = 'bg-secondary text-secondary';
                        $priorityIcon = 'fa-minus';
                        if($ticket['priority'] == 'high') { $priorityClass = 'bg-danger text-danger'; $priorityIcon = 'fa-arrow-up'; }
                        if($ticket['priority'] == 'medium') { $priorityClass = 'bg-warning text-warning'; $priorityIcon = 'fa-arrow-right'; }
                        if($ticket['priority'] == 'low') { $priorityClass = 'bg-success text-success'; $priorityIcon = 'fa-arrow-down'; }
                        ?>
                        <div class="d-flex align-items-center <?php echo str_replace('bg-', 'text-', $priorityClass); ?> small opacity-75">
                            <i class="fas <?php echo $priorityIcon; ?> me-2 small"></i>
                            <span class="fw-bold text-uppercase" style="font-size: 0.7rem;"><?php echo ucfirst($ticket['priority']); ?></span>
                        </div>
                    </td>
                    <td class="py-3">
                        <span class="badge bg-secondary bg-opacity-10 text-white-50 border border-secondary border-opacity-25 fw-normal text-capitalize">
                            <?php echo str_replace('_', ' ', $ticket['status']); ?>
                        </span>
                    </td>
                    <td class="py-3">
                        <?php if($ticket['assignee']): ?>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle flex-shrink-0 bg-dark border border-secondary border-opacity-25 text-white-50 rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px;height:24px;font-size:0.7rem;">
                                    <?php echo substr($ticket['assignee'], 0, 1); ?>
                                </div>
                                <span class="text-white-50 small"><?php echo htmlspecialchars($ticket['assignee']); ?></span>
                            </div>
                        <?php else: ?>
                            <span class="text-white-50 small fst-italic opacity-50">Pending Assignment</span>
                        <?php endif; ?>
                    </td>
                    <td class="pe-4 py-3 text-end">
                        <a href="ticket_details.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-icon btn-ghost-primary" title="View Details"><i class="fas fa-chevron-right"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-ticket-alt fa-3x"></i></div>
                        <h6 class="text-white">No tickets found</h6>
                        <p class="text-white-50 small mb-0">Need help? Submit a new ticket above.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($cursor_mode): ?>
<div class="d-flex justify-content-between align-items-center mt-4">
    <div class="text-white-50 small">
        Showing latest tickets via cursor pagination
    </div>
    <?php if ($next_cursor > 0): ?>
    <a href="?last_id=<?php echo $next_cursor; ?>" class="btn btn-sm btn-outline-primary">Load More</a>
    <?php endif; ?>
</div>
<?php elseif ($total_pages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-4">
    <div class="text-white-50 small">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> tickets
    </div>
    <nav aria-label="My Tickets Pagination">
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
