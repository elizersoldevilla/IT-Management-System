<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_role('admin')) {
    redirect('dashboard.php');
}

$backup_dir = rtrim(__DIR__ . '/../' . env('BACKUP_DIR', 'backups'), '/') . '/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    verify_csrf();
    
    $db_host = env('DB_HOST', 'localhost');
    $db_name = env('DB_NAME', 'it_management_system');
    $db_user = env('DB_USER', 'root');
    $db_pass = env('DB_PASS', '');
    
    $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (file_exists($mysqldump)) {
        $command = "\"$mysqldump\" -h $db_host -u $db_user " . ($db_pass ? "-p$db_pass " : "") . $db_name . " > \"" . $backup_file . "\"";
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && file_exists($backup_file)) {
            $success = 'Backup created successfully: ' . basename($backup_file);
            log_action($pdo, $_SESSION['user_id'], 'BACKUP_CREATED', "Created database backup: " . basename($backup_file));

            $retention_days = (int) env('BACKUP_RETENTION_DAYS', 7);
            $cutoff = time() - ($retention_days * 86400);
            $deleted = 0;

            foreach (glob($backup_dir . 'backup_*.sql') as $old_backup) {
                if (filemtime($old_backup) < $cutoff) {
                    @unlink($old_backup);
                    $deleted++;
                }
            }

            if ($deleted > 0) {
                $success .= " ($deleted old backup(s) removed)";
            }
        } else {
            $error = 'Backup failed. Please check database credentials.';
        }
    } else {
        $error = 'mysqldump not found. Please install XAMPP or configure path.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    verify_csrf();
    $file = basename($_POST['delete_backup']);
    $filepath = $backup_dir . $file;
    
    if (file_exists($filepath) && is_file($filepath)) {
        unlink($filepath);
        $success = 'Backup deleted: ' . $file;
        log_action($pdo, $_SESSION['user_id'], 'BACKUP_DELETED', "Deleted backup: $file");
    }
}

$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'name' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => date('Y-m-d H:i:s', filemtime($backup_dir . $file))
            ];
        }
    }
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">System</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Backup & Restore</h1>
    </div>
    <form method="POST" style="display:inline;">
        <?php csrf_field(); ?>
        <input type="hidden" name="create_backup" value="1">
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-database me-2"></i>Create Backup
        </button>
    </form>
</div>

<?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="card border-secondary border-opacity-10 bg-dark">
    <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-archive me-2 text-primary"></i>Available Backups</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-4 py-3 text-white-50 text-uppercase small fw-bold">Filename</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold">Size</th>
                    <th class="py-3 text-white-50 text-uppercase small fw-bold">Created</th>
                    <th class="pe-4 py-3 text-end text-white-50 text-uppercase small fw-bold">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <span class="text-white fw-medium"><?php echo htmlspecialchars($backup['name']); ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo number_format($backup['size'] / 1024, 2); ?> KB</span>
                        </td>
                        <td class="py-3">
                            <span class="text-white-50 small"><?php echo format_date($backup['date']); ?></span>
                        </td>
                        <td class="pe-4 py-3 text-end">
                            <a href="../backups/<?php echo urlencode($backup['name']); ?>" download class="btn btn-sm btn-outline-primary bg-dark border-secondary border-opacity-25">
                                <i class="fas fa-download me-1"></i> Download
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this backup?');" style="display:inline;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="delete_backup" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger bg-dark border-secondary border-opacity-25">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($backups)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-archive fa-3x"></i></div>
                            <h6 class="text-white">No backups found</h6>
                            <p class="text-white-50 small mb-0">Click "Create Backup" to generate a database backup.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark mt-4">
    <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-info-circle me-2 text-warning"></i>Backup Information</h5>
    </div>
    <div class="card-body">
        <p class="text-white-50 small mb-2"><strong class="text-white">Database:</strong> <?php echo DB_NAME; ?></p>
        <p class="text-white-50 small mb-2"><strong class="text-white">Host:</strong> <?php echo DB_HOST; ?></p>
        <p class="text-white-50 small mb-2"><strong class="text-white">Retention:</strong> <?php echo env('BACKUP_RETENTION_DAYS', 7); ?> days</p>
        <p class="text-white-50 small mb-0"><strong class="text-white">Total Backups:</strong> <?php echo count($backups); ?></p>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark mt-4">
    <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-clock me-2 text-info"></i>Scheduled Backups</h5>
    </div>
    <div class="card-body">
        <p class="text-white-50 small mb-2">Set up automated daily backups using Windows Task Scheduler or a cron service.</p>
        <p class="text-white-50 small mb-0"><strong class="text-white">Cron URL:</strong> <code><?php echo APP_URL; ?>/public/cron/backup.php?secret=<?php echo CRON_SECRET; ?></code></p>
        <p class="text-white-50 small mt-2 mb-0"><strong class="text-white">Windows Task Scheduler command:</strong></p>
        <code class="d-block bg-dark p-2 rounded small text-white-50 mt-1" style="word-break: break-all;">
            powershell -Command "Invoke-WebRequest -Uri '<?php echo APP_URL; ?>/public/cron/backup.php?secret=<?php echo CRON_SECRET; ?>' -UseBasicParsing"
        </code>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>