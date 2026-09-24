<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== CRON_SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$backup_dir = __DIR__ . '/../../' . env('BACKUP_DIR', 'backups');
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

if (!file_exists($mysqldump)) {
    http_response_code(500);
    echo json_encode(['error' => 'mysqldump not found']);
    exit;
}

$db_host = env('DB_HOST', 'localhost');
$db_name = env('DB_NAME', 'it_management_system');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');

$command = "\"$mysqldump\" -h $db_host -u $db_user " . ($db_pass ? "-p$db_pass " : "") . $db_name . " > \"" . $backup_file . "\"";
exec($command, $output, $return_var);

if ($return_var === 0 && file_exists($backup_file)) {
    $retention_days = (int) env('BACKUP_RETENTION_DAYS', 7);
    $cutoff = time() - ($retention_days * 86400);
    $deleted = 0;

    foreach (glob($backup_dir . '/backup_*.sql') as $old_backup) {
        if (filemtime($old_backup) < $cutoff) {
            @unlink($old_backup);
            $deleted++;
        }
    }

    echo json_encode([
        'success' => true,
        'backup' => basename($backup_file),
        'size' => filesize($backup_file),
        'deleted_old' => $deleted
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Backup failed', 'output' => $output]);
}
