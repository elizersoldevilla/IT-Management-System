<?php
require_once __DIR__ . '/../views/header.php';

require_login();
if (!has_role('admin') && !has_role('supervisor')) {
    redirect('dashboard.php');
}

$deleted_at_exists_u = false;
try {
    $col_check_u = $pdo->query("SHOW COLUMNS FROM users LIKE 'deleted_at'");
    $deleted_at_exists_u = $col_check_u->rowCount() > 0;
} catch (Exception $e) {
    $deleted_at_exists_u = false;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("
        SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at,
               s.position, s.specialization
        FROM users u 
        LEFT JOIN staff_details s ON u.id = s.user_id 
        WHERE u.role IN ('staff', 'technician', 'supervisor') 
        ORDER BY u.created_at DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['ID', 'Full Name', 'Email', 'Role', 'Status', 'Created At', 'Position', 'Specialization'];
    $data = array_map(function($r) {
        return [$r['id'], $r['full_name'], $r['email'], ucfirst($r['role']), ucfirst($r['status']), $r['created_at'], $r['position'] ?? '', $r['specialization'] ?? ''];
    }, $rows);
    export_csv('staff_directory', $headers, $data);
}

if (isset($_POST['delete_id'])) {
    verify_csrf();
    $id = $_POST['delete_id'];

    if ($deleted_at_exists_u) {
        $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            log_action($pdo, $_SESSION['user_id'], 'DELETE_USER', "Deleted User: {$user['username']} ({$user['role']})");
            invalidate_dashboard_cache();
        }
    }

    echo "<script>window.location.href='staff.php';</script>";
}

if (isset($_POST['restore_id'])) {
    verify_csrf();
    $id = $_POST['restore_id'];

    if ($deleted_at_exists_u) {
        $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            log_action($pdo, $_SESSION['user_id'], 'RESTORE_USER', "Restored User: {$user['username']} ({$user['role']})");
        }
    }

    echo "<script>window.location.href='staff.php';</script>";
}


$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';
$search_query = clean_input($_GET['search'] ?? '');

$sql = "SELECT u.*, s.position, s.specialization 
        FROM users u 
        LEFT JOIN staff_details s ON u.id = s.user_id 
        WHERE u.role IN ('staff', 'technician', 'supervisor')";

$params = [];

if (!$show_deleted && $deleted_at_exists_u) {
    $sql .= " AND u.deleted_at IS NULL";
}

if ($search_query) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staff_members = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Team</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Staff Management</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="?export=csv<?php echo $show_deleted ? '&show_deleted=1' : ''; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-export me-1"></i>Export
        </a>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['show_deleted' => $show_deleted ? '0' : '1'])); ?>"
           class="btn btn-sm <?php echo $show_deleted ? 'btn-outline-danger' : 'btn-outline-warning'; ?>">
            <i class="fas <?php echo $show_deleted ? 'fa-eye-slash' : 'fa-trash-restore'; ?> me-1"></i>
            <?php echo $show_deleted ? 'Hide Deleted' : 'Show Deleted'; ?>
        </a>
        <a href="staff_add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-2"></i>Add Staff Member</a>
    </div>
</div>

<div class="card border-secondary border-opacity-25" data-tilt data-tilt-max="1" data-tilt-speed="400" data-tilt-glare data-tilt-max-glare="0.05">
    <div class="card-header border-secondary border-opacity-25 bg-transparent py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-users-cog me-2 text-info"></i>Staff Directory</h5>
        <form method="GET" class="input-group input-group-sm w-auto">
            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fas fa-search"></i></span>
            <input type="text" name="search" class="form-control bg-dark border-secondary text-white" placeholder="Search staff..." value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
            <?php if ($show_deleted): ?>
                <input type="hidden" name="show_deleted" value="1">
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Staff Member</th>
                        <th>Role</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_members as $staff): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-dark border border-secondary text-white me-3 rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px; font-size: 1rem;">
                                    <?php echo substr($staff['full_name'], 0, 1); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-white"><?php echo htmlspecialchars($staff['full_name']); ?></div>
                                    <div class="small text-secondary"><?php echo htmlspecialchars($staff['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-info border border-info border-opacity-25 text-capitalize">
                                <?php echo ucfirst($staff['role']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="text-white-50 small">
                                <?php echo htmlspecialchars($staff['position'] ?? 'Not Specified'); ?>
                            </div>
                            <?php if(!empty($staff['specialization'])): ?>
                                <div class="text-secondary text-xs" style="font-size: 0.75rem;">
                                    <i class="fas fa-code-branch me-1 opacity-50"></i><?php echo htmlspecialchars($staff['specialization']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill <?php echo $staff['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 text-capitalize px-3">
                                <i class="fas fa-circle me-1" style="font-size: 6px; vertical-align: middle;"></i> <?php echo ucfirst($staff['status']); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                <a href="staff_edit.php?id=<?php echo $staff['id']; ?>" class="btn btn-icon btn-sm btn-ghost-secondary text-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($show_deleted && !empty($staff['deleted_at'])): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Restore this user?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="restore_id" value="<?php echo $staff['id']; ?>">
                                        <button type="submit" class="btn btn-icon btn-sm btn-ghost-secondary text-success" title="Restore">
                                            <i class="fas fa-trash-restore"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo $staff['id']; ?>">
                                        <button type="submit" class="btn btn-icon btn-sm btn-ghost-secondary text-danger" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
