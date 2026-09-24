<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'fetch') {
    $limit = (int)($_GET['limit'] ?? 20);
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$_SESSION['user_id'], $limit]);
    $notifications = $stmt->fetchAll();

    $unread_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $unread_count->execute([$_SESSION['user_id']]);
    $unread_count = $unread_count->fetchColumn();

    foreach ($notifications as &$n) {
        $n['display_date'] = format_date($n['created_at']);
        $n['time_ago'] = time_ago($n['created_at']);
    }

    echo json_encode(['notifications' => $notifications, 'unread_count' => $unread_count]);
    exit;
}

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid Action']);
?>
