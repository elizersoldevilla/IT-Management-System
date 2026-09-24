<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
if (!has_privilege('view_reports')) {
    die("Access Denied");
}

require_once __DIR__ . '/../views/header.php';


$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$item_filter = $_GET['item_name'] ?? '';


$items = $pdo->query("SELECT DISTINCT item_name FROM consumables ORDER BY item_name ASC")->fetchAll(PDO::FETCH_COLUMN);


$where = "WHERE l.action_type = 'DEDUCT'";
$params = [];

if ($start_date) {
    $where .= " AND DATE(l.created_at) >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $where .= " AND DATE(l.created_at) <= ?";
    $params[] = $end_date;
}
if ($item_filter) {
    $where .= " AND c.item_name = ?";
    $params[] = $item_filter;
}


$stmt = $pdo->prepare("
    SELECT l.department, SUM(l.quantity) as count 
    FROM consumable_logs l
    JOIN consumables c ON l.consumable_id = c.id
    $where
    GROUP BY l.department 
    ORDER BY count DESC
");
$stmt->execute($params);
$dept_data = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(l.created_at, '%Y-%m') as month, c.item_name, SUM(l.quantity) as count 
    FROM consumable_logs l
    JOIN consumables c ON l.consumable_id = c.id
    $where
    GROUP BY month, c.item_name
    ORDER BY month ASC, count DESC
");
$stmt->execute($params);
$monthly_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);


$months = array_unique(array_column($monthly_raw, 'month'));
sort($months);
$monthly_items = array_unique(array_column($monthly_raw, 'item_name'));
$monthly_series = [];

foreach ($monthly_items as $item) {
    $data = [];
    foreach ($months as $month) {
        $found = false;
        foreach ($monthly_raw as $row) {
            if ($row['month'] === $month && $row['item_name'] === $item) {
                $data[] = (int)$row['count'];
                $found = true;
                break;
            }
        }
        if (!$found) $data[] = 0;
    }
    $monthly_series[] = [
        'name' => $item,
        'type' => 'bar',
        'stack' => 'total',
        'emphasis' => ['focus' => 'series'],
        'data' => $data
    ];
}


$stmt = $pdo->prepare("
    SELECT c.category, SUM(l.quantity) as count 
    FROM consumable_logs l
    JOIN consumables c ON l.consumable_id = c.id
    $where
    GROUP BY c.category
");
$stmt->execute($params);
$category_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);


$stmt = $pdo->prepare("
    SELECT c.item_name, SUM(l.quantity) as count 
    FROM consumable_logs l
    JOIN consumables c ON l.consumable_id = c.id
    $where
    GROUP BY c.item_name
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute($params);
$top_items = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Analytics</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Consumables Usage Analytics</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="consumables_report.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-file-alt me-2"></i>Detailed Report
        </a>
        <button onclick="location.reload()" class="btn btn-dark border-secondary btn-sm">
            <i class="fas fa-sync-alt me-2"></i>Refresh
        </button>
    </div>
</div>


<div class="card mb-4 border-secondary border-opacity-10 bg-darker">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-white-50 small fw-bold">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small fw-bold">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label text-white-50 small fw-bold">Filter by Item Name</label>
                <select name="item_name" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Items</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $item_filter == $item ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    
    <div class="col-lg-8">
        <div class="card border-secondary border-opacity-10 shadow-lg h-100 bg-dark">
            <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-chart-bar me-2 text-info"></i>Monthly Items Deployed</h5>
            </div>
            <div class="card-body">
                <div id="trendChart" style="width: 100%; height: 350px;"></div>
            </div>
        </div>
    </div>
    
    
    <div class="col-lg-4">
        <div class="card border-secondary border-opacity-10 shadow-lg h-100 bg-dark">
            <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>Category Mix</h5>
            </div>
            <div class="card-body">
                <div id="categoryChart" style="width: 100%; height: 350px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    
    <div class="col-lg-6">
        <div class="card border-secondary border-opacity-10 shadow-lg bg-dark">
            <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-building me-2 text-success"></i>Department Allocation</h5>
            </div>
            <div class="card-body">
                <div id="deptChart" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    </div>
    
    
    <div class="col-lg-6">
        <div class="card border-secondary border-opacity-10 shadow-lg bg-dark">
            <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-crown me-2 text-warning"></i>Most Issued Items</h5>
            </div>
            <div class="card-body">
                <div id="topItemsChart" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<script>
    // --- Monthly Item Trend Chart ---
    const trendChart = echarts.init(document.getElementById('trendChart'), 'dark');
    trendChart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: { textStyle: { color: '#ccc' }, type: 'scroll', top: 0 },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true, top: 40 },
        xAxis: { type: 'category', data: <?php echo json_encode(array_values($months)); ?>, axisLabel: { color: '#ccc' } },
        yAxis: { type: 'value', axisLabel: { color: '#ccc' }, splitLine: { lineStyle: { color: '#333' } } },
        series: <?php echo json_encode($monthly_series); ?>
    });

    // --- Category Chart ---
    const categoryChart = echarts.init(document.getElementById('categoryChart'), 'dark');
    categoryChart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'item' },
            series: [{
                type: 'pie',
                radius: ['40%', '70%'],
                data: <?php echo json_encode(array_map(function($k, $v){ return ['name'=>$k, 'value'=>$v]; }, array_keys($category_data), array_values($category_data))); ?>,
                itemStyle: { borderRadius: 5, borderColor: '#1e1e2f', borderWidth: 2, cursor: 'pointer' },
                emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0,0,0,0.3)' } }
            }]
    });

    // --- Department Chart ---
    const deptChart = echarts.init(document.getElementById('deptChart'), 'dark');
    deptChart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'value', axisLabel: { color: '#ccc' } },
        yAxis: { type: 'category', data: <?php echo json_encode(array_column($dept_data, 'department')); ?>, axisLabel: { color: '#ccc' } },
        series: [{
            name: 'Issued Qty',
            type: 'bar',
            data: <?php echo json_encode(array_column($dept_data, 'count')); ?>,
            itemStyle: { color: '#198754', borderRadius: [0, 5, 5, 0], cursor: 'pointer' }
        }]
    });

    // --- Top Items Chart ---
    const topItemsChart = echarts.init(document.getElementById('topItemsChart'), 'dark');
    topItemsChart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis' },
        xAxis: { type: 'category', data: <?php echo json_encode(array_keys($top_items)); ?>, axisLabel: { rotate: 45, color: '#ccc' } },
        yAxis: { type: 'value', axisLabel: { color: '#ccc' } },
        series: [{
            data: <?php echo json_encode(array_values($top_items)); ?>,
            type: 'bar',
            itemStyle: { color: '#ffc107', borderRadius: [5, 5, 0, 0], cursor: 'pointer' }
        }]
    });

    const baseParams = new URLSearchParams({
        start_date: <?php echo json_encode($start_date); ?>,
        end_date: <?php echo json_encode($end_date); ?>
    });

    deptChart.on('click', function(params) {
        if (params.name) {
            const url = 'consumables_report.php?' + baseParams.toString() + '&department=' + encodeURIComponent(params.name);
            window.location.href = url;
        }
    });

    categoryChart.on('click', function(params) {
        if (params.name) {
            const url = 'consumables_report.php?' + baseParams.toString() + '&search=' + encodeURIComponent(params.name);
            window.location.href = url;
        }
    });

    topItemsChart.on('click', function(params) {
        if (params.name) {
            const url = 'consumables_report.php?' + baseParams.toString() + '&search=' + encodeURIComponent(params.name);
            window.location.href = url;
        }
    });

    window.addEventListener('resize', () => {
        trendChart.resize();
        categoryChart.resize();
        deptChart.resize();
        topItemsChart.resize();
    });
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
