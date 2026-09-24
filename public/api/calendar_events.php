<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode([]);
    exit;
}

$events = [];



$stmt = $pdo->query("SELECT id, subject, created_at, status, priority FROM tickets WHERE status IN ('open', 'in_progress')");
$tickets = $stmt->fetchAll();

foreach ($tickets as $ticket) {
    $color = '#3788d8'; 
    if ($ticket['priority'] == 'urgent') $color = '#dc3545';
    if ($ticket['priority'] == 'high') $color = '#fd7e14';
    
    $events[] = [
        'title' => "Ticket #{$ticket['id']}: {$ticket['subject']}",
        'start' => date('Y-m-d', strtotime($ticket['created_at'])), 
        'url' => "ticket_details.php?id={$ticket['id']}",
        'color' => $color
    ];
}


$stmt = $pdo->query("SELECT id, item_name, deployment_date FROM assets WHERE deployment_date IS NOT NULL");
$assets = $stmt->fetchAll();

foreach ($assets as $asset) {
    $events[] = [
        'title' => "Deployed: {$asset['item_name']}",
        'start' => $asset['deployment_date'],
        'url' => "inventory_edit.php?id={$asset['id']}",
        'color' => '#28a745' 
    ];
}

$stmt = $pdo->query("SELECT a.id, a.action, a.details, a.created_at, u.full_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 500");
$audits = $stmt->fetchAll();

foreach ($audits as $audit) {
    $events[] = [
        'title' => ($audit['full_name'] ?: 'System') . ': ' . $audit['action'],
        'start' => date('Y-m-d', strtotime($audit['created_at'])),
        'url' => 'audit_logs.php',
        'color' => '#6c757d'
    ];
}

echo json_encode($events);
