<?php
require_once __DIR__ . '/../views/header.php';

require_login();
if (!has_role('admin') && !has_role('supervisor')) {
    redirect('dashboard.php');
}

if (isset($_POST['delete_id'])) {
    verify_csrf();
    $id = $_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>window.location.href='users.php';</script>";
}


$stmt = $pdo->query("
    SELECT id, username, full_name, email, role, status, created_at
    FROM users
    WHERE role = 'staff'
    ORDER BY created_at DESC
");
$users = $stmt->fetchAll();
?>


<div class="row align-items-end mb-4">
    <div class="col-md-6">
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Access Control</h6>
        <h1 class="h2 mb-0 fw-bold text-white">User Management</h1>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="staff_add.php?role=staff" class="btn btn-primary btn-sm"><i class="fas fa-plus me-2"></i>Add New User</a>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 35%;">User</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 25%;">Email</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 15%;">Role</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 10%;">Status</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold" style="letter-spacing: 0.05em; width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td class="ps-4 py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle flex-shrink-0 bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;font-size:1rem;">
                                <?php echo substr($user['full_name'], 0, 1); ?>
                            </div>
                            <div>
                                <div class="text-white fw-medium"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                <div class="text-white-50 small opacity-50">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="py-3">
                        <span class="text-white-50 small"><?php echo htmlspecialchars($user['email']); ?></span>
                    </td>
                    <td class="py-3">
                         <span class="badge bg-secondary bg-opacity-10 text-white-50 border border-secondary border-opacity-25 fw-normal text-capitalize">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td class="py-3">
                        <?php if($user['status'] === 'active'): ?>
                            <div class="d-flex align-items-center text-success small">
                                <i class="fas fa-check-circle me-2"></i> Active
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center text-danger small">
                                <i class="fas fa-times-circle me-2"></i> Inactive
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="pe-4 py-3 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="staff_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-icon btn-ghost-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" class="d-inline">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="delete_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-users-slash fa-3x"></i></div>
                        <h6 class="text-white">No users found</h6>
                        <p class="text-white-50 small mb-0">Use the 'Add New User' button to get started.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php';