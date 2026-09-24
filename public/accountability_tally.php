<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

if (isset($_SESSION['accountability_success'])) {
    $success = $_SESSION['accountability_success'];
    unset($_SESSION['accountability_success']);
}
if (isset($_SESSION['accountability_error'])) {
    $error = $_SESSION['accountability_error'];
    unset($_SESSION['accountability_error']);
}


$items_per_page = 10;


$page_p = isset($_GET['page_p']) ? (int) $_GET['page_p'] : 1;
if ($page_p < 1)
    $page_p = 1;
$offset_p = ($page_p - 1) * $items_per_page;


$page_d = isset($_GET['page_d']) ? (int) $_GET['page_d'] : 1;
if ($page_d < 1)
    $page_d = 1;
$offset_d = ($page_d - 1) * $items_per_page;


$total_p_groups = $pdo->query("SELECT COUNT(DISTINCT received_by) FROM assets")->fetchColumn();
$total_d_groups = $pdo->query("SELECT COUNT(DISTINCT department) FROM assets")->fetchColumn();

$total_pages_p = ceil($total_p_groups / $items_per_page);
$total_pages_d = ceil($total_d_groups / $items_per_page);

$accountability_columns_exist = false;
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM assets LIKE 'accountability_due_date'");
    $accountability_columns_exist = $col_check->rowCount() > 0;
} catch (Exception $e) {
    $accountability_columns_exist = false;
}

$overdue_sql = $accountability_columns_exist ? "COUNT(CASE WHEN accountability_due_date IS NOT NULL AND accountability_due_date < CURDATE() THEN 1 END) as overdue_assets," : "0 as overdue_assets,";
$signed_sql = $accountability_columns_exist ? "COUNT(CASE WHEN accountability_signed_by IS NOT NULL THEN 1 END) as signed_assets" : "0 as signed_assets";

$sql = "SELECT 
            COALESCE(rb.full_name, a.received_by, 'Unassigned') as accountability_name, 
            COUNT(*) as total_assets,
            COUNT(CASE WHEN a.status = 'working' THEN 1 END) as working_assets,
            COUNT(CASE WHEN a.status = 'repair' THEN 1 END) as repair_assets,
            COUNT(CASE WHEN a.status = 'missing' THEN 1 END) as missing_assets,
            $overdue_sql
            $signed_sql
        FROM assets a
        LEFT JOIN users rb ON a.received_by_user_id = rb.id
        GROUP BY a.received_by_user_id, a.received_by 
        ORDER BY total_assets DESC
        LIMIT $items_per_page OFFSET $offset_p";

$stmt = $pdo->query($sql);
$tallies = $stmt->fetchAll();


$sql_dept = "SELECT 
                COALESCE(department, 'Unassigned') as department_name, 
                COUNT(*) as total_assets,
                COUNT(CASE WHEN status = 'working' THEN 1 END) as working_assets,
                COUNT(CASE WHEN status = 'repair' THEN 1 END) as repair_assets,
                COUNT(CASE WHEN status = 'missing' THEN 1 END) as missing_assets,
                $overdue_sql
                $signed_sql
            FROM assets 
            GROUP BY department 
            ORDER BY total_assets DESC
            LIMIT $items_per_page OFFSET $offset_d";

$stmt_dept = $pdo->query($sql_dept);
$dept_tallies = $stmt_dept->fetchAll();


$total_all_assets = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.03);
        --glass-border: rgba(255, 255, 255, 0.08);
        --glass-hover: rgba(255, 255, 255, 0.06);
    }

    .report-header {
        background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(0, 0, 0, 0));
        padding: 2rem;
        border-radius: 20px;
        margin-bottom: 2.5rem;
        border: 1px solid var(--glass-border);
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 18px;
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        height: 100%;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .glass-card:hover {
        background: var(--glass-hover);
        transform: translateY(-8px);
        border-color: rgba(255, 255, 255, 0.15);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 1rem;
        background: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
    }

    .stat-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: rgba(255,255,255,0.5);
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
        line-height: 1;
        font-family: 'Inter', sans-serif;
    }

    .tally-card {
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid var(--glass-border);
        background: rgba(0, 0, 0, 0.2) !important;
    }

    .card-header-premium {
        background: rgba(255, 255, 255, 0.02);
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--glass-border);
    }

    .table-premium thead th {
        background: rgba(0, 0, 0, 0.3);
        border: none;
        padding: 1rem 1.5rem;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
    }

    .table-premium tbody td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }

    @media print {
        @page { size: landscape; margin: 1cm; }
        body { background: white !important; color: black !important; padding: 0 !important; }
        .sidebar, .actions, .pagination, .btn, footer, header, .nav-item { display: none !important; }
        .tally-card { border: 1px solid #ddd !important; background: white !important; box-shadow: none !important; page-break-inside: avoid; margin-bottom: 2rem !important; }
        .card-header-premium { background: #f8f9fa !important; border-bottom: 1px solid #ddd !important; color: black !important; }
        .glass-card { border: 1px solid #ddd !important; background: white !important; color: black !important; }
        .stat-icon, .stat-label { color: #666 !important; background: none !important; }
        .stat-value, .text-white, h1, h2, h5 { color: black !important; }
        .table-premium { color: black !important; }
        .table-premium thead th { background: #eee !important; color: black !important; border-bottom: 2px solid black !important; }
        .table-premium td { border-bottom: 1px solid #eee !important; }
    }
</style>

<div class="report-header d-flex justify-content-between align-items-center">
    <div>
        <h6 class="text-primary text-uppercase mb-2 small fw-bold" style="letter-spacing: 0.2em;">Administrative Insights</h6>
        <h1 class="display-6 mb-0 fw-bold text-white">Summary Tally Reports</h1>
        <p class="text-white-50 mt-2 mb-0">Actionable intelligence on organizational asset distribution and health.</p>
    </div>
    <div class="actions">
        <button onclick="window.print()" class="btn btn-primary btn-lg rounded-pill px-4 shadow-lg d-flex align-items-center gap-2">
            <i class="fas fa-print"></i> <span>Print Report</span>
        </button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="glass-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fas fa-cubes"></i>
            </div>
            <div class="stat-label">Total Assets Tracked</div>
            <div class="stat-value text-white"><?php echo number_format($total_all_assets); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-label">Accountable Personnel</div>
            <div class="stat-value text-white"><?php echo number_format($total_p_groups); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="fas fa-sitemap"></i>
            </div>
            <div class="stat-label">Organizational Departments</div>
            <div class="stat-value text-white"><?php echo number_format($total_d_groups); ?></div>
        </div>
    </div>
</div>

<?php if (has_privilege('manage_assets') && $accountability_columns_exist): ?>
<div class="card tally-card mb-5">
    <div class="card-header-premium">
        <h5 class="text-white mb-0 fw-bold"><i class="fas fa-file-signature me-2 text-success opacity-75"></i>Sign for Assets</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="accountability_sign.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Personnel</label>
                <select name="user_id" class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-25" required>
                    <option value="">Select personnel...</option>
                    <?php
                    $users_list = $pdo->query("SELECT id, full_name, email FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
                    foreach ($users_list as $u) {
                        echo "<option value='{$u['id']}'>{$u['full_name']} ({$u['email']})</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Due Date</label>
                <input type="date" name="due_date" class="form-control form-control-sm bg-dark text-white border-secondary border-opacity-25" required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Notes</label>
                <input type="text" name="notes" class="form-control form-control-sm bg-dark text-white border-secondary border-opacity-25" placeholder="Optional notes...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-sm w-100">
                    <i class="fas fa-check me-1"></i>Sign
                </button>
            </div>
        </form>
        <p class="text-white-50 small mt-3 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            This will set a due date for all assets assigned to the selected personnel and log the signing action.
        </p>
    </div>
</div>
<?php endif; ?>

<div class="card tally-card mb-5">
    <div class="card-header-premium d-flex justify-content-between align-items-center">
        <h5 class="text-white mb-0 fw-bold"><i class="fas fa-user-shield me-2 text-primary opacity-75"></i>Personnel Accountability</h5>
        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 small fw-normal">Total: <?php echo $total_p_groups; ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 table-premium">
            <thead>
                <tr class="text-white-50 text-uppercase fw-bold">
                    <th class="ps-4">Personnel Information</th>
                    <th class="text-center">Total Assets</th>
                    <th class="text-center">Working</th>
                    <th class="text-center">For Repair</th>
                    <th class="text-center">Missing</th>
                    <?php if ($accountability_columns_exist): ?>
                    <th class="text-center">Overdue</th>
                    <th class="text-center">Signed</th>
                    <?php endif; ?>
                    <th class="pe-4 text-end">Management</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tallies) > 0): ?>
                        <?php foreach ($tallies as $tally): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3"
                                                style="width: 40px; height: 40px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="text-white fw-bold mb-0">
                                                    <?php echo htmlspecialchars($tally['accountability_name']); ?>
                                                </div>
                                                <small class="text-white-50">Authorized Personnel</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary bg-opacity-25 text-primary rounded-pill px-3">
                                            <?php echo $tally['total_assets']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-success fw-bold"><i class="fas fa-check-circle me-1 small"></i><?php echo $tally['working_assets']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-warning fw-bold"><i class="fas fa-tools me-1 small"></i><?php echo $tally['repair_assets']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1 small"></i><?php echo $tally['missing_assets']; ?></span>
                                    </td>
                                    <?php if ($accountability_columns_exist): ?>
                                    <td class="text-center">
                                        <?php if ($tally['overdue_assets'] > 0): ?>
                                            <span class="text-danger fw-bold"><i class="fas fa-clock me-1 small"></i><?php echo $tally['overdue_assets']; ?></span>
                                        <?php else: ?>
                                            <span class="text-success fw-bold"><i class="fas fa-check me-1 small"></i>0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-info fw-bold"><i class="fas fa-file-signature me-1 small"></i><?php echo $tally['signed_assets']; ?>/<?php echo $tally['total_assets']; ?></span>
                                    </td>
                                    <?php endif; ?>
                                    <td class="pe-4 text-end">
                                        <a href="inventory.php?search=<?php echo urlencode($tally['accountability_name'] === 'Unassigned' ? '' : $tally['accountability_name']); ?>"
                                            class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            Launch View <i class="fas fa-external-link-alt ms-1 small"></i>
                                        </a>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                <?php else: ?>
                        <tr>
                            <td colspan="<?php echo 5 + ($accountability_columns_exist ? 2 : 0); ?>" class="text-center py-5">
                                <div class="text-white-50 opacity-10 mb-3"><i class="fas fa-clipboard-list fa-4x"></i></div>
                                <h6 class="text-white opacity-50">No accountability records available</h6>
                            </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages_p > 1): ?>
            <div class="card-footer bg-transparent border-top border-secondary border-opacity-10 py-3">
                <nav aria-label="Personnel Pagination">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php
                        $queryParams = $_GET;
                        $queryParams['page_p'] = max(1, $page_p - 1);
                        $prevUrl = '?' . http_build_query($queryParams);
                        ?>
                        <li class="page-item <?php echo ($page_p <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link bg-dark border-secondary text-white rounded-start-pill px-3" href="<?php echo $prevUrl; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages_p; $i++):
                            $queryParams['page_p'] = $i;
                            $pageUrl = '?' . http_build_query($queryParams);
                            ?>
                                <li class="page-item <?php echo ($page_p == $i) ? 'active' : ''; ?>">
                                    <a class="page-link <?php echo ($page_p == $i) ? 'bg-primary border-primary' : 'bg-dark border-secondary text-white'; ?> px-3"
                                        href="<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
                                </li>
                        <?php endfor; ?>
                        <?php
                        $queryParams['page_p'] = min($total_pages_p, $page_p + 1);
                        $nextUrl = '?' . http_build_query($queryParams);
                        ?>
                        <li class="page-item <?php echo ($page_p >= $total_pages_p) ? 'disabled' : ''; ?>">
                            <a class="page-link bg-dark border-secondary text-white rounded-end-pill px-3" href="<?php echo $nextUrl; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
    <?php endif; ?>
</div>

<div class="card tally-card mb-4">
    <div class="card-header-premium d-flex justify-content-between align-items-center">
        <h5 class="text-white mb-0 fw-bold"><i class="fas fa-building-user me-2 text-info opacity-75"></i>Departmental Distribution</h5>
        <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-2 small fw-normal">Total: <?php echo $total_d_groups; ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 table-premium">
            <thead>
                <tr class="text-white-50 text-uppercase fw-bold">
                    <th class="ps-4">Department Unit</th>
                    <th class="text-center">Total Assets</th>
                    <th class="text-center">Working</th>
                    <th class="text-center">For Repair</th>
                    <th class="text-center">Missing</th>
                    <?php if ($accountability_columns_exist): ?>
                    <th class="text-center">Overdue</th>
                    <th class="text-center">Signed</th>
                    <?php endif; ?>
                    <th class="pe-4 text-end">Management</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($dept_tallies) > 0): ?>
                        <?php foreach ($dept_tallies as $tally): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center me-3"
                                                style="width: 40px; height: 40px;">
                                                <i class="fas fa-building"></i>
                                            </div>
                                            <div>
                                                <div class="text-white fw-bold mb-0">
                                                    <?php echo htmlspecialchars($tally['department_name']); ?>
                                                </div>
                                                <small class="text-white-50">Organizational Unit</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info bg-opacity-25 text-info rounded-pill px-3">
                                            <?php echo $tally['total_assets']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-success fw-bold"><i class="fas fa-check-circle me-1 small"></i><?php echo $tally['working_assets']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-warning fw-bold"><i class="fas fa-tools me-1 small"></i><?php echo $tally['repair_assets']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1 small"></i><?php echo $tally['missing_assets']; ?></span>
                                    </td>
                                    <?php if ($accountability_columns_exist): ?>
                                    <td class="text-center">
                                        <?php if ($tally['overdue_assets'] > 0): ?>
                                            <span class="text-danger fw-bold"><i class="fas fa-clock me-1 small"></i><?php echo $tally['overdue_assets']; ?></span>
                                        <?php else: ?>
                                            <span class="text-success fw-bold"><i class="fas fa-check me-1 small"></i>0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-info fw-bold"><i class="fas fa-file-signature me-1 small"></i><?php echo $tally['signed_assets']; ?>/<?php echo $tally['total_assets']; ?></span>
                                    </td>
                                    <?php endif; ?>
                                    <td class="pe-4 text-end">
                                        <a href="inventory.php?department=<?php echo urlencode($tally['department_name'] === 'Unassigned' ? '' : $tally['department_name']); ?>"
                                            class="btn btn-sm btn-outline-info rounded-pill px-3">
                                            Launch View <i class="fas fa-external-link-alt ms-1 small"></i>
                                        </a>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                <?php else: ?>
                        <tr>
                            <td colspan="<?php echo 5 + ($accountability_columns_exist ? 2 : 0); ?>" class="text-center py-5">
                                <div class="text-white-50 opacity-10 mb-3"><i class="fas fa-city fa-4x"></i></div>
                                <h6 class="text-white opacity-50">No departmental data found</h6>
                            </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages_d > 1): ?>
            <div class="card-footer bg-transparent border-top border-secondary border-opacity-10 py-3">
                <nav aria-label="Department Pagination">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php
                        $queryParams = $_GET;
                        $queryParams['page_d'] = max(1, $page_d - 1);
                        $prevUrl = '?' . http_build_query($queryParams);
                        ?>
                        <li class="page-item <?php echo ($page_d <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link bg-dark border-secondary text-white rounded-start-pill px-3" href="<?php echo $prevUrl; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages_d; $i++):
                            $queryParams['page_d'] = $i;
                            $pageUrl = '?' . http_build_query($queryParams);
                            ?>
                                <li class="page-item <?php echo ($page_d == $i) ? 'active' : ''; ?>">
                                    <a class="page-link <?php echo ($page_d == $i) ? 'bg-info border-info' : 'bg-dark border-secondary text-white'; ?> px-3"
                                        href="<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
                                </li>
                        <?php endfor; ?>
                        <?php
                        $queryParams['page_d'] = min($total_pages_d, $page_d + 1);
                        $nextUrl = '?' . http_build_query($queryParams);
                        ?>
                        <li class="page-item <?php echo ($page_d >= $total_pages_d) ? 'disabled' : ''; ?>">
                            <a class="page-link bg-dark border-secondary text-white rounded-end-pill px-3" href="<?php echo $nextUrl; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>