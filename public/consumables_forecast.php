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

$forecast_months = isset($_GET['months']) ? (int)$_GET['months'] : 3;
if ($forecast_months < 1) $forecast_months = 1;
if ($forecast_months > 12) $forecast_months = 12;

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-12 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$items = $pdo->query("SELECT id, item_name, brand, category, quantity, min_quantity, unit FROM consumables WHERE deleted_at IS NULL ORDER BY item_name ASC")->fetchAll();

$forecast_data = [];
$alerts = [];

foreach ($items as $item) {
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(quantity) as qty 
        FROM consumable_logs 
        WHERE consumable_id = ? AND action_type = 'DEDUCT' AND DATE(created_at) >= ? AND DATE(created_at) <= ?
        GROUP BY month 
        ORDER BY month ASC
    ");
    $stmt->execute([$item['id'], $start_date, $end_date]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $history_months = [];
    $history_values = [];
    
    foreach ($history as $row) {
        $history_months[] = $row['month'];
        $history_values[] = (int)$row['qty'];
    }

    $total_used = array_sum($history_values);
    $month_count = max(count($history_values), 1);
    $avg_monthly = $total_used / $month_count;

    $trend = 0;
    if (count($history_values) >= 2) {
        $n = count($history_values);
        $sum_x = 0;
        $sum_y = 0;
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_x += $i;
            $sum_y += $history_values[$i];
            $sum_xy += $i * $history_values[$i];
            $sum_x2 += $i * $i;
        }
        
        $denominator = ($n * $sum_x2 - $sum_x * $sum_x);
        if ($denominator != 0) {
            $slope = ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
            $trend = $slope;
        }
    }

    $forecast = [];
    $last_known = $avg_monthly;
    
    for ($m = 1; $m <= $forecast_months; $m++) {
        $predicted = $last_known + ($trend * $m);
        if ($predicted < 0) $predicted = 0;
        $forecast[] = round($predicted, 1);
    }

    $total_forecast = array_sum($forecast);
    $current_stock = (int)$item['quantity'];
    $days_of_stock = $avg_monthly > 0 ? round($current_stock / ($avg_monthly / 30)) : 9999;
    $will_run_out = $days_of_stock < ($forecast_months * 30);

    if ($current_stock == 0) {
        $alerts[] = [
            'item' => $item['item_name'],
            'type' => 'out_of_stock',
            'message' => "OUT OF STOCK: {$item['item_name']} has zero stock!"
        ];
    } elseif ($will_run_out && $avg_monthly > 0) {
        $alerts[] = [
            'item' => $item['item_name'],
            'type' => 'low_stock',
            'message' => "LOW STOCK: {$item['item_name']} will run out in approximately {$days_of_stock} days (current: {$current_stock}, monthly avg: " . round($avg_monthly, 1) . ")"
        ];
    }

    $forecast_data[] = [
        'id' => $item['id'],
        'item_name' => $item['item_name'],
        'brand' => $item['brand'],
        'category' => $item['category'],
        'current_stock' => $current_stock,
        'min_quantity' => $item['min_quantity'],
        'unit' => $item['unit'],
        'history_months' => $history_months,
        'history_values' => $history_values,
        'avg_monthly' => round($avg_monthly, 1),
        'trend' => round($trend, 2),
        'forecast' => $forecast,
        'total_forecast' => round($total_forecast, 1),
        'days_of_stock' => $days_of_stock,
        'will_run_out' => $will_run_out,
        'status' => $current_stock == 0 ? 'out' : ($will_run_out ? 'critical' : ($current_stock <= $item['min_quantity'] ? 'low' : 'ok'))
    ];
}

usort($forecast_data, function($a, $b) {
    $order = ['out' => 0, 'critical' => 1, 'low' => 2, 'ok' => 3];
    return $order[$a['status']] <=> $order[$b['status']];
});

$critical_items = array_filter($forecast_data, fn($i) => $i['status'] == 'out' || $i['status'] == 'critical' || $i['status'] == 'low');
$ok_items = array_filter($forecast_data, fn($i) => $i['status'] == 'ok');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Analytics</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Consumable Demand Forecast</h1>
        <p class="text-white-50 small mb-0">Predictive stock analysis based on historical usage patterns</p>
    </div>
    <div class="d-flex gap-2">
        <a href="consumables_analytics.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-chart-bar me-2"></i>Usage Trends
        </a>
        <a href="consumables_report.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-alt me-2"></i>Transaction Report
        </a>
    </div>
</div>

<?php if (!empty($alerts)): ?>
<div class="alert alert-danger border-danger border-opacity-25 mb-4">
    <h6 class="text-white fw-bold mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Critical Alerts</h6>
    <?php foreach ($alerts as $alert): ?>
    <div class="small text-white-80 mb-1"><i class="fas fa-chevron-right me-2 text-danger"></i><?php echo $alert['message']; ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-danger border-opacity-25 bg-dark">
            <div class="card-body text-center">
                <div class="text-danger small text-uppercase fw-bold">Critical / Out</div>
                <div class="display-6 fw-bold text-white"><?php echo count(array_filter($forecast_data, fn($i) => $i['status'] == 'out' || $i['status'] == 'critical')); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning border-opacity-25 bg-dark">
            <div class="card-body text-center">
                <div class="text-warning small text-uppercase fw-bold">Low Stock</div>
                <div class="display-6 fw-bold text-white"><?php echo count(array_filter($forecast_data, fn($i) => $i['status'] == 'low')); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success border-opacity-25 bg-dark">
            <div class="card-body text-center">
                <div class="text-success small text-uppercase fw-bold">Adequate Stock</div>
                <div class="display-6 fw-bold text-white"><?php echo count($ok_items); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-secondary border-opacity-25 bg-dark">
            <div class="card-body text-center">
                <div class="text-white-50 small text-uppercase fw-bold">Total Items</div>
                <div class="display-6 fw-bold text-white"><?php echo count($forecast_data); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 border-secondary border-opacity-10 bg-darker">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-white-50 small fw-bold">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small fw-bold">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-white-50 small fw-bold">Forecast Period</label>
                <select name="months" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $forecast_months == $m ? 'selected' : ''; ?>><?php echo $m; ?> Month<?php echo $m > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Update Forecast</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark mb-4">
    <div class="card-header bg-darker border-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-chart-line me-2 text-primary"></i>Demand Forecast Overview</h5>
        <span class="badge bg-primary bg-opacity-10 text-primary"><?php echo $forecast_months; ?> month forecast</span>
    </div>
    <div class="card-body">
        <div id="forecastChart" style="width: 100%; height: 400px;"></div>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark mb-4">
    <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-clipboard-list me-2 text-warning"></i>Stock Status Summary</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead class="bg-darker">
                    <tr>
                        <th class="ps-4 py-3 text-white-50 small fw-bold">Item</th>
                        <th class="py-3 text-white-50 small fw-bold">Current Stock</th>
                        <th class="py-3 text-white-50 small fw-bold">Monthly Avg</th>
                        <th class="py-3 text-white-50 small fw-bold">Trend</th>
                        <th class="py-3 text-white-50 small fw-bold text-center">Days Left</th>
                        <th class="py-3 text-white-50 small fw-bold">Forecast (<?php echo $forecast_months; ?>m)</th>
                        <th class="py-3 text-white-50 small fw-bold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forecast_data as $item): ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="text-white fw-medium"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div class="text-white-50 small"><?php echo htmlspecialchars($item['brand']); ?> • <?php echo htmlspecialchars($item['category']); ?></div>
                        </td>
                        <td class="py-3">
                            <span class="fw-bold text-<?php echo $item['current_stock'] == 0 ? 'danger' : 'white'; ?>"><?php echo $item['current_stock']; ?></span>
                            <span class="text-white-50 small"><?php echo htmlspecialchars($item['unit']); ?>s</span>
                        </td>
                        <td class="py-3 text-white-50"><?php echo $item['avg_monthly']; ?> <?php echo htmlspecialchars($item['unit']); ?>s/mo</td>
                        <td class="py-3">
                            <?php if ($item['trend'] > 0.5): ?>
                                <span class="text-danger"><i class="fas fa-arrow-up me-1"></i>+<?php echo $item['trend']; ?></span>
                            <?php elseif ($item['trend'] < -0.5): ?>
                                <span class="text-success"><i class="fas fa-arrow-down me-1"></i><?php echo $item['trend']; ?></span>
                            <?php else: ?>
                                <span class="text-white-50">Stable</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-center">
                            <?php if ($item['days_of_stock'] >= 9999): ?>
                                <span class="text-white-50">N/A</span>
                            <?php else: ?>
                                <span class="fw-bold text-<?php echo $item['days_of_stock'] < 7 ? 'danger' : ($item['days_of_stock'] < 30 ? 'warning' : 'success'); ?>"><?php echo $item['days_of_stock']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-white-50"><?php echo $item['total_forecast']; ?> <?php echo htmlspecialchars($item['unit']); ?>s</td>
                        <td class="py-3">
                            <?php
                            $statusBadge = 'bg-secondary text-white-50';
                            $statusLabel = 'Unknown';
                            if ($item['status'] == 'out') {
                                $statusBadge = 'bg-danger text-danger';
                                $statusLabel = 'Out of Stock';
                            } elseif ($item['status'] == 'critical') {
                                $statusBadge = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                                $statusLabel = 'Critical';
                            } elseif ($item['status'] == 'low') {
                                $statusBadge = 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
                                $statusLabel = 'Low Stock';
                            } elseif ($item['status'] == 'ok') {
                                $statusBadge = 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                                $statusLabel = 'Adequate';
                            }
                            ?>
                            <span class="badge rounded-pill px-3 py-2 <?php echo $statusBadge; ?>"><?php echo $statusLabel; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-secondary border-opacity-10 bg-dark">
            <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-chart-bar me-2 text-danger"></i>Items at Risk</h5>
            </div>
            <div class="card-body">
                <div id="riskChart" style="width: 100%; height: 350px;"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-secondary border-opacity-10 bg-dark">
            <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-chart-area me-2 text-info"></i>Monthly Usage Trends</h5>
            </div>
            <div class="card-body">
                <div id="trendChart" style="width: 100%; height: 350px;"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<script>
const riskChart = echarts.init(document.getElementById('riskChart'), 'dark');
const criticalData = <?php echo json_encode(array_values(array_filter($forecast_data, fn($i) => $i['status'] == 'out' || $i['status'] == 'critical' || $i['status'] == 'low'))); ?>;
const riskLabels = <?php echo json_encode(array_map(fn($i) => $i['item_name'], array_slice($forecast_data, 0, 15))); ?>;
const riskValues = <?php echo json_encode(array_map(fn($i) => $i['days_of_stock'] >= 9999 ? 0 : $i['days_of_stock'], array_slice($forecast_data, 0, 15))); ?>;

if (riskLabels.length > 0) {
    riskChart.setOption({
        backgroundColor: 'transparent',
        tooltip: { 
            trigger: 'axis', 
            axisPointer: { type: 'shadow' },
            formatter: function(params) {
                const item = params[0];
                const dataItem = criticalData[item.dataIndex];
                if (dataItem) {
                    return `<strong>${dataItem.item_name}</strong><br/>
                            Current: ${dataItem.current_stock} ${dataItem.unit}s<br/>
                            Monthly Avg: ${dataItem.avg_monthly} ${dataItem.unit}s<br/>
                            Days Left: ${dataItem.days_of_stock >= 9999 ? 'N/A' : dataItem.days_of_stock}<br/>
                            Forecast: ${dataItem.total_forecast} ${dataItem.unit}s`;
                }
                return item.name;
            }
        },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { 
            type: 'category', 
            data: riskLabels,
            axisLabel: { color: '#ccc', rotate: 45 }
        },
        yAxis: { 
            type: 'value', 
            name: 'Days of Stock',
            axisLabel: { color: '#ccc' },
            splitLine: { lineStyle: { color: '#333' } }
        },
        series: [{
            name: 'Days of Stock',
            type: 'bar',
            data: riskValues,
            itemStyle: {
                color: function(params) {
                    const val = params.value;
                    if (val === 0) return '#dc3545';
                    if (val < 7) return '#dc3545';
                    if (val < 30) return '#ffc107';
                    return '#198754';
                }
            }
        }]
    });
} else {
    riskChart.showLoading({
        text: 'No data available',
        color: '#ccc',
        textColor: '#ccc',
        maskColor: 'rgba(0, 0, 0, 0.3)'
    });
}

const trendChart = echarts.init(document.getElementById('trendChart'), 'dark');
const trendData = <?php echo json_encode(array_slice($forecast_data, 0, 8)); ?>;
const forecastLabels = <?php
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$currentMonth = (int)(new DateTime())->format('n');
$labels = [];
for ($m = 1; $m <= $forecast_months; $m++) {
    $monthIndex = ($currentMonth + $m - 1) % 12;
    $labels[] = $months[$monthIndex] . ' (F)';
}
echo json_encode($labels);
?>;

if (trendData.length > 0 && forecastLabels.length > 0) {
    trendChart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis' },
        legend: { textStyle: { color: '#ccc' }, type: 'scroll', top: 0 },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true, top: 40 },
        xAxis: { 
            type: 'category', 
            data: forecastLabels,
            axisLabel: { color: '#ccc' }
        },
        yAxis: { 
            type: 'value', 
            name: 'Predicted Demand',
            axisLabel: { color: '#ccc' },
            splitLine: { lineStyle: { color: '#333' } }
        },
        series: trendData.map(item => ({
            name: item.item_name,
            type: 'line',
            smooth: true,
            data: item.forecast,
            lineStyle: { width: 3 }
        }))
    });
} else {
    trendChart.showLoading({
        text: 'No forecast data available',
        color: '#ccc',
        textColor: '#ccc',
        maskColor: 'rgba(0, 0, 0, 0.3)'
    });
}

window.addEventListener('resize', () => {
    riskChart.resize();
    trendChart.resize();
});
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
