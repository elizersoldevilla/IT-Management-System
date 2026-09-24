<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('view_reports')) {
    redirect('dashboard.php');
}

$stmt = $pdo->query("
    SELECT t.*, u.full_name as creator, a.full_name as assignee 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN users a ON t.assigned_to = a.id 
    WHERE t.status IN ('open', 'in_progress') 
    ORDER BY t.created_at ASC
");
$open_tickets = $stmt->fetchAll();

$aging_buckets = [
    '0-1 days' => ['min' => 0, 'max' => 1, 'count' => 0, 'tickets' => []],
    '2-3 days' => ['min' => 2, 'max' => 3, 'count' => 0, 'tickets' => []],
    '4-7 days' => ['min' => 4, 'max' => 7, 'count' => 0, 'tickets' => []],
    '8-14 days' => ['min' => 8, 'max' => 14, 'count' => 0, 'tickets' => []],
    '15+ days' => ['min' => 15, 'max' => PHP_INT_MAX, 'count' => 0, 'tickets' => []],
];

$now = time();
foreach ($open_tickets as $ticket) {
    $created = strtotime($ticket['created_at']);
    $age_days = floor(($now - $created) / 86400);

    foreach ($aging_buckets as $bucket_name => $bucket) {
        if ($age_days >= $bucket['min'] && $age_days <= $bucket['max']) {
            $bucket['count']++;
            $bucket['tickets'][] = $ticket;
            break;
        }
    }
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Analytics</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Ticket Aging Report</h1>
    </div>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to Reports
    </a>
</div>

<div class="row g-4 mb-4">
    <?php foreach ($aging_buckets as $bucket_name => $bucket): ?>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card h-100" data-tilt data-tilt-glare data-tilt-max-glare="0.5">
                <div class="d-flex justify-content-between mb-4">
                    <div class="stat-icon text-warning bg-warning bg-opacity-10">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <span class="badge bg-<?php echo $bucket['count'] > 0 ? 'danger' : 'success'; ?> bg-opacity-10 text-<?php echo $bucket['count'] > 0 ? 'danger' : 'success'; ?> rounded-pill px-3">
                        <?php echo $bucket['count']; ?> tickets
                    </span>
                </div>
                <h2 class="display-6 fw-bold text-white mb-1"><?php echo $bucket_name; ?></h2>
                <div class="text-secondary small">Days Open</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-secondary border-opacity-25 shadow-lg">
    <div class="card-header bg-transparent border-secondary border-opacity-25 py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-list me-2 text-primary"></i>Open Tickets by Age</h5>
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
                        <th>Age</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aging_buckets as $bucket_name => $bucket): ?>
                        <?php if (!empty($bucket['tickets'])): ?>
                            <tr class="table-active">
                                <td colspan="5" class="ps-4 py-2">
                                    <strong class="text-white"><?php echo $bucket_name; ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo $bucket['count']; ?></span>
                                </td>
                            </tr>
                            <?php foreach ($bucket['tickets'] as $ticket): ?>
                                <?php
                                $created = strtotime($ticket['created_at']);
                                $age_days = floor(($now - $created) / 86400);
                                $age_hours = floor((($now - $created) % 86400) / 3600);
                                $age_text = $age_days > 0 ? "$age_days day" . ($age_days > 1 ? 's' : '') : "$age_hours hour" . ($age_hours > 1 ? 's' : '');
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-dark border border-secondary text-secondary rounded-circle d-flex align-items-center justify-content-center me-3"
                                                style="width:36px;height:36px;font-size:0.75rem;">
                                                #<?php echo $ticket['id']; ?>
                                            </div>
                                            <div>
                                                <div class="text-white fw-medium"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                                <div class="text-muted small" style="font-size: 0.75rem;"><?php echo format_date($ticket['created_at']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge rounded-pill <?php echo get_status_badge($ticket['priority']); ?> bg-opacity-10 text-uppercase" style="letter-spacing:0.05em;"><?php echo ucfirst($ticket['priority']); ?></span></td>
                                    <td><span class="badge rounded-pill <?php echo get_status_badge($ticket['status']); ?> bg-opacity-10"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span></td>
                                    <td><span class="text-secondary small"><?php echo htmlspecialchars($ticket['creator']); ?></span></td>
                                    <td><span class="text-warning fw-bold"><?php echo $age_text; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (empty($open_tickets)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 opacity-25"></i>
                                <p>No open tickets found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>