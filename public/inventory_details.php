<?php
require_once __DIR__ . '/../views/header.php';
require_login();
if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('inventory.php');
}

$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as assigned_user, d.full_name as deployer_name, e.full_name as encoder_name 
    FROM assets a 
    LEFT JOIN users u ON a.assigned_to = u.id 
    LEFT JOIN users d ON a.deployed_by = d.id
    LEFT JOIN users e ON a.encoded_by = e.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$asset = $stmt->fetch();

if (!$asset) {
    die("Asset not found.");
}


$stmt = $pdo->prepare("
    SELECT h.*, u.full_name as user_name 
    FROM asset_history h 
    JOIN users u ON h.user_id = u.id 
    WHERE h.asset_id = ? 
    ORDER BY h.created_at DESC
");
$stmt->execute([$id]);
$history = $stmt->fetchAll();
?>


<div class="mb-5 d-flex flex-column flex-md-row justify-content-between align-items-center">
    <div>
        <a href="inventory.php" class="btn btn-outline-light btn-sm mb-3 px-3 rounded-pill">
            <i class="fas fa-arrow-left me-2"></i>Back to Inventory
        </a>
        <h1 class="display-5 fw-bold text-white mb-0 font-monospace">
            <?php echo htmlspecialchars($asset['serial_number']); ?>
        </h1>
        <div class="text-white-50 mt-2">
            <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($asset['category']); ?> •
            <?php echo htmlspecialchars($asset['brand']); ?>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="inventory_qr.php?id=<?php echo $asset['id']; ?>" target="_blank"
            class="btn btn-dark text-white border-0 shadow-lg px-4 d-flex align-items-center gap-2">
            <i class="fas fa-qrcode"></i> <span>Print QR</span>
        </a>
        <?php if (has_role('admin') || has_role('supervisor') || has_role('technician')): ?>
            <a href="inventory_edit.php?id=<?php echo $asset['id']; ?>"
                class="btn btn-primary text-white shadow-lg px-4 d-flex align-items-center gap-2">
                <i class="fas fa-edit"></i> <span>Edit Asset</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-8">
        <div class="glass-panel p-4 h-100 position-relative overflow-hidden">

            <div class="position-absolute top-0 end-0 p-5 rounded-circle bg-primary opacity-10"
                style="filter: blur(100px); transform: translate(30%, -30%);"></div>

            <h4 class="mb-4 text-white fw-bold d-flex align-items-center">
                <i class="fas fa-info-circle me-3 text-primary"></i>Asset Information
            </h4>

            <div class="row g-4 mb-5">
                <div class="col-md-6">
                    <label class="d-block text-white-50 text-xs text-uppercase mb-1 tracking-wider">Category</label>
                    <div class="fs-5 text-white fw-medium"><?php echo htmlspecialchars($asset['category']); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="d-block text-white-50 text-xs text-uppercase mb-1 tracking-wider">Status</label>
                    <div>
                        <?php
                        $statusClass = match ($asset['status']) {
                            'working' => 'bg-success',
                            'repair' => 'bg-warning text-dark',
                            'missing' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                        ?>
                        <span class="badge rounded-pill <?php echo $statusClass; ?> px-3 py-2">
                            <i class="fas fa-circle text-xs me-1 small opacity-75"></i>
                            <?php echo ucfirst($asset['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="d-block text-white-50 text-xs text-uppercase mb-1 tracking-wider">Brand</label>
                    <div class="fs-5 text-white fw-medium"><?php echo htmlspecialchars($asset['brand']); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="d-block text-white-50 text-xs text-uppercase mb-1 tracking-wider">Model</label>
                    <div class="fs-5 text-white fw-medium"><?php echo htmlspecialchars($asset['model']); ?></div>
                </div>
            </div>

            <div class="mb-5">
                <label class="d-block text-white-50 text-xs text-uppercase mb-2 tracking-wider">Description /
                    Specs</label>
                <div
                    class="p-3 bg-black bg-opacity-25 rounded-3 border border-white border-opacity-10 text-white-50 lh-lg">
                    <?php echo nl2br(htmlspecialchars($asset['description'] ?? 'No description provided.')); ?>
                </div>
            </div>

            <div class="row g-4 border-top border-white border-opacity-10 pt-4">
                <div class="col-md-12">
                    <label class="d-block text-white-50 text-xs text-uppercase mb-1 tracking-wider">Delivery
                        Date</label>
                    <div class="text-white d-flex align-items-center gap-2">
                        <i class="far fa-calendar text-primary opacity-50"></i>
                        <?php echo $asset['purchase_date'] ? format_date($asset['purchase_date']) : '-'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="col-lg-4">

        <div class="glass-panel p-4 mb-4">
            <h5
                class="mb-4 text-white fw-bold d-flex align-items-center border-bottom border-white border-opacity-10 pb-3">
                <i class="fas fa-map-marker-alt me-2 text-info"></i>Location Information
            </h5>

            <div class="text-center py-3">
                <div class="avatar-circle mx-auto mb-3 bg-gradient-to-br from-blue-500 to-purple-600 rounded-circle d-flex align-items-center justify-content-center text-white display-6 fw-bold shadow-lg"
                    style="width: 80px; height: 80px;">
                    <?php echo $asset['encoder_name'] ? substr($asset['encoder_name'], 0, 1) : '?'; ?>
                </div>
                <?php if ($asset['encoder_name']): ?>
                    <h5 class="text-white mb-1"><?php echo htmlspecialchars($asset['encoder_name']); ?></h5>
                    <span class="badge bg-primary bg-opacity-25 text-primary rounded-pill px-3">
                        <i class="fas fa-edit me-1"></i> Data Encoder
                    </span>
                <?php else: ?>
                    <h5 class="text-white-50 mb-1">Unknown Encoder</h5>
                    <span class="badge bg-secondary rounded-pill px-3">System Record</span>
                <?php endif; ?>
            </div>

            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50">Department</span>
                    <span
                        class="text-white fw-medium"><?php echo htmlspecialchars($asset['department'] ?? '-'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50">Location</span>
                    <span class="text-white fw-medium">
                        <?php echo htmlspecialchars($asset['location'] ?? '-'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50">Person Accountable</span>
                    <span class="text-white fw-medium">
                        <?php echo htmlspecialchars($asset['received_by'] ?? '-'); ?>
                    </span>
                </div>
                <?php if ($asset['deployment_date']): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-white-50">Deployed On</span>
                        <span class="text-white fw-medium"><?php echo format_date($asset['deployment_date']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-white-50">Deployed By</span>
                        <span class="text-white fw-medium"><?php echo htmlspecialchars($asset['deployer_name']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <div class="glass-panel p-4">
            <h5
                class="mb-4 text-white fw-bold d-flex align-items-center border-bottom border-white border-opacity-10 pb-3">
                <i class="fas fa-history me-2 text-warning"></i>History Log
            </h5>
            <div class="history-timeline" style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($history as $log): ?>
                    <div
                        class="position-relative ps-4 pb-4 border-start border-white border-opacity-10 last:border-0 last:pb-0">
                        <div class="position-absolute top-0 start-0 translate-middle-x bg-dark border border-white border-opacity-25 rounded-circle p-1"
                            style="width: 12px; height: 12px; margin-top: 4px;"></div>
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="text-white fw-bold text-sm"><?php echo htmlspecialchars($log['action']); ?></span>
                            <span
                                class="text-white-50 text-xs"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></span>
                        </div>
                        <p class="text-white-50 text-sm mb-1 lh-sm"><?php echo htmlspecialchars($log['notes']); ?></p>
                        <div class="text-xs text-primary opacity-75">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($log['user_name']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                    <div class="text-center py-4 text-white-50 italic">
                        <i class="fas fa-wind d-block fs-4 mb-2 opacity-50"></i>
                        No history recorded.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>