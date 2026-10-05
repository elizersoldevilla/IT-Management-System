<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== CRON_SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$reminders_sent = 0;

$accountability_columns_exist = false;
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM assets LIKE 'accountability_due_date'");
    $accountability_columns_exist = $col_check->rowCount() > 0;
} catch (Exception $e) {
    $accountability_columns_exist = false;
}

if (!$accountability_columns_exist) {
    echo "Accountability columns not found. Skipping reminders.\n";
    exit;
}

try {
    $upcoming = $pdo->prepare("
        SELECT a.id, a.item_name, a.serial_number, a.accountability_due_date, u.full_name, u.email
        FROM assets a
        JOIN users u ON a.received_by = u.id
        WHERE a.deleted_at IS NULL 
        AND a.accountability_due_date IS NOT NULL
        AND a.accountability_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND a.accountability_signed_by IS NOT NULL
    ");
    $upcoming->execute();
    $upcoming_assets = $upcoming->fetchAll();

    foreach ($upcoming_assets as $asset) {
        $subject = "Reminder: Asset Accountability Due Soon - " . $asset['accountability_due_date'];
        $body = "Hello {$asset['full_name']},\n\n";
        $body .= "This is a reminder that your asset accountability is due soon.\n\n";
        $body .= "Asset: {$asset['item_name']} (Serial: {$asset['serial_number']})\n";
        $body .= "Due Date: {$asset['accountability_due_date']}\n\n";
        $body .= "Please ensure all assigned assets are accounted for by the due date.\n\n";
        $body .= "IT Management System";

        if (send_email($asset['email'], $subject, $body)) {
            $reminders_sent++;
        }
    }

    $overdue = $pdo->prepare("
        SELECT a.id, a.item_name, a.serial_number, a.accountability_due_date, u.full_name, u.email
        FROM assets a
        JOIN users u ON a.received_by = u.id
        WHERE a.deleted_at IS NULL 
        AND a.accountability_due_date IS NOT NULL
        AND a.accountability_due_date < CURDATE()
        AND a.accountability_signed_by IS NOT NULL
    ");
    $overdue->execute();
    $overdue_assets = $overdue->fetchAll();

    foreach ($overdue_assets as $asset) {
        $subject = "URGENT: Asset Accountability Overdue - " . $asset['accountability_due_date'];
        $body = "Hello {$asset['full_name']},\n\n";
        $body .= "This is an URGENT reminder that your asset accountability is OVERDUE.\n\n";
        $body .= "Asset: {$asset['item_name']} (Serial: {$asset['serial_number']})\n";
        $body .= "Due Date: {$asset['accountability_due_date']}\n";
        $body .= "Days Overdue: " . ceil((time() - strtotime($asset['accountability_due_date'])) / 86400) . "\n\n";
        $body .= "Please contact the IT department immediately to resolve this.\n\n";
        $body .= "IT Management System";

        if (send_email($asset['email'], $subject, $body)) {
            $reminders_sent++;
        }
    }

    echo "Accountability reminders sent: $reminders_sent\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
