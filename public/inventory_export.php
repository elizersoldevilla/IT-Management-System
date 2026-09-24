<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!has_privilege('manage_assets')) {
    die("Access Denied");
}

require_login();


$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';
$search_query = $_GET['search'] ?? '';


$sql = "SELECT a.*, e.full_name as encoder_name, d.full_name as deployer_name 
        FROM assets a 
        LEFT JOIN users e ON a.encoded_by = e.id 
        LEFT JOIN users d ON a.deployed_by = d.id
        WHERE 1=1";
$params = [];

if ($category_filter) {
    $sql .= " AND a.category = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

if ($department_filter) {
    $sql .= " AND a.department LIKE ?";
    $params[] = "%$department_filter%";
}

if ($search_query) {
    $sql .= " AND (a.serial_number LIKE ? OR a.item_name LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.location LIKE ? OR a.received_by LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();


header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=IT_Inventory_Export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');


fputcsv($output, [
    'Serial Number',
    'Category',
    'Brand',
    'Model',
    'Status',
    'Department',
    'Location',
    'Encoded By',
    'Person Accountable',
    'Delivery Date',
    'Deployment Date',
    'Created At'
]);


foreach ($assets as $asset) {
    fputcsv($output, [
        $asset['serial_number'],
        $asset['category'],
        $asset['brand'],
        $asset['model'],
        ucfirst($asset['status']),
        $asset['department'] ?? 'N/A',
        $asset['location'] ?? 'N/A',
        $asset['encoder_name'] ?? 'System',
        $asset['received_by'] ?? 'N/A',
        $asset['purchase_date'],
        $asset['deployment_date'],
        $asset['created_at']
    ]);
}

fclose($output);
exit;
