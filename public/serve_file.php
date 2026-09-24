<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(404);
    die("File not specified.");
}


$filename = basename($filename);

$secure_dir = __DIR__ . '/../uploads/';
$filepath = $secure_dir . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    die("File not found.");
}


$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filepath);
finfo_close($finfo);


header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');

readfile($filepath);
exit;
