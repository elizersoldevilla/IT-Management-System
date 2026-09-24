<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
header('Content-Type: application/json');

try {
    if (!is_logged_in()) {
        throw new Exception('Unauthorized: User not logged in');
    }

    $action = $_GET['action'] ?? '';

    if ($action === 'fetch') {
        $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        $before_id = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
        
        if ($before_id > 0) {
            $stmt = $pdo->prepare("
                SELECT c.*, u.full_name, DATE_FORMAT(c.created_at, '%h:%i %p') as formatted_time 
                FROM chat_messages c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.id < ? 
                ORDER BY c.id DESC 
                LIMIT 20
            ");
            $stmt->execute([$before_id]);
            $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($last_id === 0) {
             $stmt = $pdo->prepare("
                 SELECT * FROM (
                     SELECT c.*, u.full_name, DATE_FORMAT(c.created_at, '%h:%i %p') as formatted_time 
                     FROM chat_messages c 
                     JOIN users u ON c.user_id = u.id 
                     ORDER BY c.id DESC LIMIT 20
                 ) as recent_msgs ORDER BY id ASC
             ");
             $stmt->execute();
             $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
             $stmt = $pdo->prepare("
                 SELECT c.*, u.full_name, DATE_FORMAT(c.created_at, '%h:%i %p') as formatted_time 
                 FROM chat_messages c 
                 JOIN users u ON c.user_id = u.id 
                 WHERE c.id > ? 
                 ORDER BY c.id ASC
             ");
             $stmt->execute([$last_id]);
             $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['messages' => $messages]);
        exit;
    }

    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }

        $message = trim($data['message'] ?? '');
        
        if ($message) {
            try {
                $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, type) VALUES (?, ?, 'message')");
                $result = $stmt->execute([$_SESSION['user_id'], $message]);
                
                if (!$result) {
                    throw new Exception('Database insert failed');
                }
                
                echo json_encode(['success' => true]);
            } catch (Exception $dbEx) {
                throw $dbEx;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Empty message']);
        }
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);

} catch (Exception $e) {
    error_log("Chat Handler Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal server error occurred']);
}
?>
