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
$ticket_id = $_GET['ticket_id'] ?? null;

if (!$ticket_id) {
    echo json_encode(['error' => 'Missing Ticket ID']);
    exit;
}


$stmt = $pdo->prepare("SELECT user_id, assigned_to FROM tickets WHERE id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo json_encode(['error' => 'Ticket not found']);
    exit;
}

if ($_SESSION['role'] === 'staff' && $ticket['user_id'] != $_SESSION['user_id']) {
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

if ($action === 'fetch') {
    $stmt = $pdo->prepare("
        SELECT tu.*, u.full_name 
        FROM ticket_updates tu 
        JOIN users u ON tu.user_id = u.id 
        WHERE tu.ticket_id = ? 
        ORDER BY tu.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $updates = $stmt->fetchAll();
    
    
    foreach ($updates as &$update) {
        $update['formatted_date'] = format_date($update['created_at']);
        $update['message'] = nl2br(htmlspecialchars($update['message']));
    }
    
    echo json_encode(['updates' => $updates]);
    exit;
}

if ($action === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $message = clean_input($data['message'] ?? '');
    
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO ticket_updates (ticket_id, user_id, message, type) VALUES (?, ?, ?, 'comment')");
        $stmt->execute([$ticket_id, $_SESSION['user_id'], $message]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Empty message']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid Action']);
?>
