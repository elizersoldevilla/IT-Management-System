<?php
require_once __DIR__ . '/../views/header.php';
require_login();

$stats = get_dashboard_stats($pdo);
$recent_tickets = get_recent_tickets($pdo, 5);
$recent_activity = get_recent_activity($pdo, 8);
$sla_stats = get_sla_stats($pdo);

$trend = $stats['trend'] ?? 0;
$trend_class = $trend >= 0 ? 'text-danger' : 'text-success';
$trend_icon = $trend >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
$trend_label = $trend >= 0 ? '+' . round($trend, 1) . '%' : round($trend, 1) . '%';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Overview</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Dashboard</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="reports_export.php" class="btn btn-outline-secondary btn-sm flex-fill flex-md-grow-0">
            <i class="fas fa-download me-2"></i>Export
        </a>
        <a href="ticket_create.php" class="btn btn-primary btn-sm flex-fill flex-md-grow-0">
            <i class="fas fa-plus me-2"></i>New Ticket
        </a>
    </div>
</div>


<div class="row g-3 g-lg-4 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="glass-card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon text-primary bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="badge bg-<?php echo $trend_class; ?> bg-opacity-10 text-<?php echo $trend_class; ?> rounded-pill px-3">
                        <i class="fas <?php echo $trend_icon; ?> me-1 small"></i><?php echo $trend_label; ?>
                    </div>
                </div>
                <h2 class="display-6 fw-bold text-white mb-1"><?php echo $stats['tickets_open']; ?></h2>
                <div class="text-secondary small">Total Open Tickets</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="glass-card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon text-danger bg-danger bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <?php if ($stats['tickets_urgent'] > 0): ?>
                        <div class="badge bg-danger rounded-pill px-3 animate-pulse">Action Req</div>
                    <?php endif; ?>
                </div>
                <h2 class="display-6 fw-bold text-white mb-1"><?php echo $stats['tickets_urgent']; ?></h2>
                <div class="text-secondary small">Urgent Issues</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="glass-card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon text-success bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <h2 class="display-6 fw-bold text-white mb-1"><?php echo $stats['staff_active']; ?></h2>
                <div class="text-secondary small">Active Tech Staff</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="glass-card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon text-warning bg-warning bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div class="text-success small fw-bold"><i class="fas fa-check me-1"></i>Healthy</div>
                </div>
                <h2 class="display-6 fw-bold text-white mb-1"><?php echo $stats['assets_total']; ?></h2>
                <div class="text-secondary small">Total Assets Managed</div>
            </div>
        </div>
    </div>
</div>

<?php
$low_stock_items = get_low_stock_consumables($pdo);
if (!empty($low_stock_items)):
?>
<div class="glass-card border-danger border-opacity-25 mb-4">
    <div class="card-header bg-danger bg-opacity-10 border-danger border-opacity-25 d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Low Stock Alert</h5>
        <a href="consumables.php" class="btn btn-sm btn-outline-danger">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Item</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-center">Min. Stock</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_items as $item): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="text-white fw-medium"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="text-white-50 small"><?php echo htmlspecialchars($item['brand']); ?> • <?php echo htmlspecialchars($item['category']); ?></div>
                            </td>
                            <td class="text-center">
                                <span class="text-danger fw-bold"><?php echo $item['quantity']; ?></span>
                                <span class="text-white-50 small"><?php echo htmlspecialchars($item['unit']); ?>s</span>
                            </td>
                            <td class="text-center">
                                <span class="text-white-50"><?php echo $item['min_quantity']; ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($item['quantity'] == 0): ?>
                                    <span class="badge bg-danger text-uppercase">Out of Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark text-uppercase">Low Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-secondary border-opacity-25">
    <div
        class="card-header border-secondary border-opacity-25 bg-transparent d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-history me-2 text-primary"></i>Recent Tickets</h5>
        <a href="tickets.php" class="btn btn-link btn-sm text-decoration-none text-primary fw-medium">View All <i
                class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Ticket</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reporter</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tickets as $ticket): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-dark border border-secondary text-secondary rounded-circle d-flex align-items-center justify-content-center me-3"
                                        style="width:36px;height:36px;font-size:0.75rem;">
                                        #<?php echo $ticket['id']; ?>
                                    </div>
                                    <div>
                                        <div class="text-white fw-medium">
                                            <?php echo htmlspecialchars($ticket['subject']); ?>
                                        </div>
                                        <div class="text-muted small" style="font-size: 0.75rem;">
                                            <?php echo format_date($ticket['created_at']); ?>
    </div>
                                </div>
                            </td>
                            <td><span
                                    class="badge rounded-pill <?php echo get_status_badge($ticket['priority']); ?> bg-opacity-10 text-uppercase"
                                    style="letter-spacing:0.05em;"><?php echo ucfirst($ticket['priority']); ?></span></td>
                            <td><span
                                    class="badge rounded-pill <?php echo get_status_badge($ticket['status']); ?> bg-opacity-10"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-secondary bg-opacity-25 text-white rounded-circle d-flex align-items-center justify-content-center me-2"
                                        style="width:24px;height:24px;font-size:0.7em;">
                                        <?php echo substr($ticket['creator'], 0, 1); ?>
                                    </div>
                                    <span
                                        class="text-secondary small"><?php echo htmlspecialchars($ticket['creator']); ?></span>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <a href="ticket_details.php?id=<?php echo $ticket['id']; ?>"
                                    class="btn btn-sm btn-icon btn-ghost-secondary text-primary"><i
                                        class="fas fa-external-link-alt"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_tickets)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-ticket-alt fa-3x mb-3 opacity-25"></i>
                                <p>No recent tickets found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-secondary border-opacity-25">
    <div class="card-header border-secondary border-opacity-25 bg-transparent d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-wave-square me-2 text-success"></i>Recent Activity</h5>
        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Live</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th class="text-end pe-4">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activity as $log): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-dark border border-secondary text-secondary rounded-circle d-flex align-items-center justify-content-center me-2"
                                        style="width:32px;height:32px;font-size:0.7rem;">
                                        <?php echo $log['full_name'] ? substr($log['full_name'], 0, 1) : '?'; ?>
                                    </div>
                                    <span class="small text-white"><?php echo htmlspecialchars($log['full_name'] ?: 'System'); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $action = strtoupper($log['action']);
                                    $badgeClass = 'bg-secondary text-secondary';
                                    if (str_contains($action, 'DELETE')) { $badgeClass = 'bg-danger text-danger'; }
                                    elseif (str_contains($action, 'CREATE') || str_contains($action, 'ADD')) { $badgeClass = 'bg-success text-success'; }
                                    elseif (str_contains($action, 'UPDATE') || str_contains($action, 'EDIT')) { $badgeClass = 'bg-warning text-warning'; }
                                    elseif (str_contains($action, 'LOGIN') || str_contains($action, 'LOGOUT')) { $badgeClass = 'bg-info text-info'; }
                                ?>
                                <span class="badge rounded-pill <?php echo $badgeClass; ?> bg-opacity-10 text-uppercase" style="font-size:0.65rem; letter-spacing:0.05em;">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-white-50 small text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($log['details']); ?>">
                                    <?php echo htmlspecialchars($log['details']); ?>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <span class="small text-white-50"><?php echo time_ago($log['created_at']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_activity)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fas fa-wave-square fa-3x mb-3 opacity-25"></i>
                                <p>No recent activity found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>