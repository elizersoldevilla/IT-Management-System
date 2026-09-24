<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== CRON_SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$days = 90;
$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

try {
    $pdo->beginTransaction();

    $count = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at < '{$cutoff}'")->fetchColumn();
    
    if ($count > 0) {
        $pdo->exec("INSERT INTO audit_logs_archive SELECT * FROM audit_logs WHERE created_at < '{$cutoff}'");
        $pdo->exec("DELETE FROM audit_logs WHERE created_at < '{$cutoff}'");
        $pdo->commit();
        echo json_encode(['success' => true, 'archived' => (int)$count]);
    } else {
        $pdo->commit();
        echo json_encode(['success' => true, 'archived' => 0]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}