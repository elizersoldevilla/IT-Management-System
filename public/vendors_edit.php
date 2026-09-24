<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect('vendors.php');
}

$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$id]);
$vendor = $stmt->fetch();

if (!$vendor) {
    redirect('vendors.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = clean_input($_POST['name']);
    $contact_person = clean_input($_POST['contact_person']);
    $email = clean_input($_POST['email'], 'email');
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $website = clean_input($_POST['website'], 'url');
    $notes = clean_input($_POST['notes']);

    if (empty($name)) {
        $error = "Vendor name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE vendors SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, website = ?, notes = ? WHERE id = ?");
            $stmt->execute([$name, $contact_person, $email, $phone, $address, $website, $notes, $id]);
            $success = "Vendor updated successfully!";
            log_action($pdo, $_SESSION['user_id'], 'UPDATE_VENDOR', "Updated Vendor: $name");
            invalidate_dashboard_cache();
            echo "<script>window.location.href='vendors.php';</script>";
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Vendor name already exists.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Procurement</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Edit Vendor</h1>
    </div>
    <a href="vendors.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to List
    </a>
</div>

<div class="glass-panel p-4" style="max-width: 800px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success mb-4"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Vendor Name *</label>
                <input type="text" name="name" class="form-control bg-dark text-white border-secondary border-opacity-25" required value="<?php echo htmlspecialchars($vendor['name']); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Contact Person</label>
                <input type="text" name="contact_person" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($vendor['contact_person'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Email</label>
                <input type="email" name="email" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Phone</label>
                <input type="text" name="phone" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Address</label>
                <textarea name="address" rows="2" class="form-control bg-dark text-white border-secondary border-opacity-25"><?php echo htmlspecialchars($vendor['address'] ?? ''); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Website</label>
                <input type="url" name="website" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($vendor['website'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Notes</label>
                <input type="text" name="notes" class="form-control bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($vendor['notes'] ?? ''); ?>">
            </div>
        </div>

        <div class="mt-4 text-end">
            <a href="vendors.php" class="btn btn-ghost-secondary me-2">Cancel</a>
            <button type="submit" class="btn btn-primary px-4" id="update-vendor-btn">Save Changes</button>
        </div>
    </form>
</div>

<script>
document.querySelector('form')?.addEventListener('submit', function() {
    const btn = document.getElementById('update-vendor-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
    }
});
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>