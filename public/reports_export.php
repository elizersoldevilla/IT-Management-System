<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

if (!has_privilege('view_reports')) {
    die("Access Denied");
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="tickets_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');


fputcsv($output, ['ID', 'Subject', 'Category', 'Priority', 'Status', 'Creator', 'Assigned To', 'Created At']);


$stmt = $pdo->query("
    SELECT t.id, t.subject, t.category, t.priority, t.status, u.full_name as creator, a.full_name as assignee, t.created_at 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN users a ON t.assigned_to = a.id 
    ORDER BY t.created_at DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
