<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $asset_id = $data['id'] ?? null;
    $status = $data['status'] ?? null;

    if (!$asset_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    
    $valid_statuses = ['working', 'repair', 'missing', 'retired'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    try {
        
        $stmt = $pdo->prepare("SELECT status, item_name FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();

        if (!$asset) {
            echo json_encode(['success' => false, 'message' => 'Asset not found']);
            exit;
        }

        if ($asset['status'] === $status) {
            echo json_encode(['success' => true, 'message' => 'No change needed']);
            exit;
        }

        
        $stmt = $pdo->prepare("UPDATE assets SET status = ? WHERE id = ?");
        $stmt->execute([$status, $asset_id]);

        
        log_action($pdo, $_SESSION['user_id'], 'UPDATE_ASSET_STATUS', "Changed status of {$asset['item_name']} to $status");
        log_asset_history($pdo, $asset_id, $_SESSION['user_id'], 'STATUS_CHANGE', "Status changed from {$asset['status']} to $status via table");

        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } catch (PDOException $e) {
        error_log("Update Asset Status Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An internal database error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
