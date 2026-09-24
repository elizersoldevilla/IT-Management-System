<?php
require_once __DIR__ . '/../views/header.php';
require_login();
if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$preview = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    verify_csrf();
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a valid CSV file.";
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if ($handle === FALSE) {
            $error = "Could not open uploaded file.";
        } else {
            $headers = [];
            $rows = [];
            $rowCount = 0;
            $headerRow = fgetcsv($handle, 0, ',');
            
            if ($headerRow === FALSE) {
                $error = "CSV file appears to be empty.";
            } else {
                $headers = array_map(function($h) {
                    return trim(strtolower(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
                }, $headerRow);
                
                while (($data = fgetcsv($handle, 0, ',')) !== FALSE) {
                    if (count($data) !== count($headers)) {
                        continue;
                    }
                    
                    $row = array_combine($headers, $data);
                    if ($row) {
                        $rows[] = $row;
                        $rowCount++;
                    }
                }
            }
            fclose($handle);
            
            if (empty($headers)) {
                $error = "CSV file appears to be empty or has no headers.";
            } else {
                $preview = ['headers' => $headers, 'rows' => $rows, 'total' => count($rows)];
                $_SESSION['import_headers'] = $headers;
                $_SESSION['import_rows'] = $rows;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    verify_csrf();
    
    $headers = $_SESSION['import_headers'] ?? [];
    $rows = $_SESSION['import_rows'] ?? [];
    
    if (empty($rows)) {
        $error = "No data to import. Please upload a file first.";
    } else {
        $inserted = 0;
        $skipped = 0;
        $skipReasons = [];
        $headerMap = [
            'serial_number' => ['serial number', 'serial', 'sn', 'serial #', 'serial#', 'barcode'],
            'item_name' => ['item name', 'item', 'name', 'product name', 'description'],
            'category' => ['category', 'type', 'asset type'],
            'brand' => ['brand', 'manufacturer', 'make'],
            'model' => ['model', 'model no', 'model number'],
            'status' => ['status', 'condition'],
            'department' => ['department', 'dept'],
            'location' => ['location', 'site', 'office'],
            'received_by' => ['received by', 'person accountable', 'accountable', 'assigned to', 'user'],
            'purchase_date' => ['delivery date', 'purchase date', 'purchase', 'date', 'delivery'],
            'deployment_date' => ['deployment date', 'install date', 'deployed'],
            'description' => ['description', 'remarks', 'notes']
        ];
        
        foreach ($rows as $idx => $row) {
            $data = [];
            foreach ($headers as $header) {
                $normalized = strtolower(trim($header));
                foreach ($headerMap as $field => $matches) {
                    if (in_array($normalized, $matches)) {
                        $data[$field] = trim($row[$header] ?? '');
                        break;
                    }
                }
            }
            
            $serial_number = $data['serial_number'] ?? '';
            $item_name = $data['item_name'] ?? '';
            $category = $data['category'] ?? '';
            $brand = $data['brand'] ?? '';
            $model = $data['model'] ?? '';
            $status = strtolower($data['status'] ?? 'working');
            $department = $data['department'] ?? '';
            $location = $data['location'] ?? '';
            $received_by = $data['received_by'] ?? '';
            $purchase_date = !empty($data['purchase_date']) ? date('Y-m-d', strtotime($data['purchase_date'])) : null;
            $deployment_date = !empty($data['deployment_date']) ? date('Y-m-d', strtotime($data['deployment_date'])) : null;
            $description = $data['description'] ?? '';
            
            if (empty($serial_number)) {
                $skipped++;
                $skipReasons[] = "Row " . ($idx + 1) . ": missing Serial Number";
                continue;
            }
            if (empty($category)) {
                $skipped++;
                $skipReasons[] = "Row " . ($idx + 1) . ": missing Category";
                continue;
            }
            
            try {
                $stmt = $pdo->prepare("INSERT INTO assets (serial_number, item_name, category, brand, model, status, department, location, received_by, purchase_date, deployment_date, description, encoded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $serial_number,
                    $item_name ?: $category,
                    $category,
                    $brand ?: null,
                    $model ?: null,
                    in_array($status, ['working', 'repair', 'retired', 'missing']) ? $status : 'working',
                    $department ?: null,
                    $location ?: null,
                    $received_by ?: null,
                    $purchase_date,
                    $deployment_date,
                    $description ?: null,
                    $_SESSION['user_id']
                ]);
                
                $asset_id = $pdo->lastInsertId();
                log_action($pdo, $_SESSION['user_id'], 'IMPORT_ASSET', "Imported Asset: $serial_number ($category)");
                $inserted++;
            } catch (PDOException $e) {
                $skipped++;
                error_log("Import Error Row " . ($idx + 1) . ": " . $e->getMessage());
            }
        }
        
        unset($_SESSION['import_headers'], $_SESSION['import_rows']);
        
        if ($inserted > 0) {
            $success = "Successfully imported $inserted assets.";
        }
        if ($skipped > 0) {
            $success .= ($success ? ' ' : '') . "$skipped skipped. See details: " . implode(', ', array_slice($skipReasons, 0, 5));
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Assets</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Import Assets</h1>
    </div>
    <a href="inventory.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to List
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success mb-4"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if (empty($preview)): ?>
<div class="glass-panel p-4" style="max-width: 800px; margin: 0 auto;">
    <div class="mb-4">
        <h5 class="text-white mb-2">Upload CSV File</h5>
        <p class="text-white-50 small mb-3">Upload a CSV file with asset data. The file should contain a header row with column names.</p>
        
        <div class="alert alert-info mb-3">
            <strong>Supported columns:</strong> Serial Number, Item Name, Category, Brand, Model, Status, Department, Location, Person Accountable, Delivery Date, Deployment Date, Description
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <?php csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label text-white-50 small text-uppercase fw-bold">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" class="form-control bg-dark text-white border-secondary border-opacity-25" required>
            </div>
            <div class="text-end">
                <button type="submit" name="import" class="btn btn-primary px-4">
                    <i class="fas fa-upload me-2"></i>Preview Import
                </button>
            </div>
        </form>
    </div>
    
    <div class="border-top border-secondary border-opacity-10 pt-4 mt-4">
        <h6 class="text-white-50 small fw-bold text-uppercase mb-3">CSV Format Example</h6>
        <div class="table-responsive">
            <table class="table table-dark table-sm mb-0">
                <thead class="bg-darker">
                    <tr>
                        <th class="text-white-50 small">Serial Number</th>
                        <th class="text-white-50 small">Category</th>
                        <th class="text-white-50 small">Brand</th>
                        <th class="text-white-50 small">Model</th>
                        <th class="text-white-50 small">Status</th>
                        <th class="text-white-50 small">Department</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="small">SN-2024-001</td>
                        <td class="small">Laptop</td>
                        <td class="small">Dell</td>
                        <td class="small">Latitude 5520</td>
                        <td class="small">working</td>
                        <td class="small">IT</td>
                    </tr>
                    <tr>
                        <td class="small">SN-2024-002</td>
                        <td class="small">Monitor</td>
                        <td class="small">LG</td>
                        <td class="small">27UK850</td>
                        <td class="small">working</td>
                        <td class="small">HR</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<div class="glass-panel p-4" style="max-width: 1000px; margin: 0 auto;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="text-white mb-1">Preview Import Data</h5>
            <p class="text-white-50 small mb-0">Showing all <?php echo $preview['total']; ?> rows</p>
        </div>
        <div class="d-flex gap-2">
            <a href="inventory_import.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('Import these assets?');">
                <?php csrf_field(); ?>
                <button type="submit" name="confirm_import" class="btn btn-success btn-sm">
                    <i class="fas fa-check me-2"></i>Confirm Import
                </button>
            </form>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="bg-darker">
                <tr>
                    <th class="ps-3 py-2 text-white-50 small">#</th>
                    <?php foreach ($preview['headers'] as $header): ?>
                        <th class="ps-3 py-2 text-white-50 small text-uppercase"><?php echo htmlspecialchars($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preview['rows'] as $i => $row): ?>
                    <tr>
                        <td class="ps-3 py-2 text-white-50 small"><?php echo $i + 1; ?></td>
                        <?php foreach ($row as $value): ?>
                            <td class="ps-3 py-2 small"><?php echo htmlspecialchars($value); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
