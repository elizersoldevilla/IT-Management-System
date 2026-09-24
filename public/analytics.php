<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_privilege('view_reports')) {
    redirect('dashboard.php');
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
    FROM tickets 
    WHERE created_at >= :start_date AND created_at <= :end_date
    GROUP BY month 
    ORDER BY month ASC
");
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$trend_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$status_data = $pdo->query("SELECT status, COUNT(*) as count FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$category_data = $pdo->query("SELECT category, COUNT(*) as count FROM tickets GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);

$priority_data = $pdo->query("SELECT priority, COUNT(*) as count FROM tickets GROUP BY priority")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Real-time Insights</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Analytics Dashboard</h1>
    </div>
    <form method="GET" class="d-flex gap-2">
        <input type="date" name="start_date" class="form-control form-control-sm bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($start_date); ?>">
        <input type="date" name="end_date" class="form-control form-control-sm bg-dark text-white border-secondary border-opacity-25" value="<?php echo htmlspecialchars($end_date); ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Apply</button>
        <a href="analytics.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times me-1"></i>Clear</a>
    </form>
</div>


<div class="card border-secondary border-opacity-25 shadow-lg mb-4">
    <div class="card-header bg-transparent border-secondary border-opacity-25 py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-chart-line me-2 text-primary"></i>Ticket Volume Trend</h5>
        <span class="text-white-50 small"><?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?></span>
    </div>
    <div class="card-body p-4">
        <div id="trendChart" style="width: 100%; height: 400px;"></div>
    </div>
</div>


<div class="row g-4">
    
    <div class="col-lg-4">
        <div class="card border-secondary border-opacity-25 h-100 shadow-lg">
            <div class="card-header bg-transparent border-secondary border-opacity-25">
                <h6 class="mb-0 text-white">Status Distribution</h6>
            </div>
            <div class="card-body">
                <div id="statusChart" style="width: 100%; height: 300px;"></div>
            </div>
        </div>
    </div>

    
    <div class="col-lg-4">
        <div class="card border-secondary border-opacity-25 h-100 shadow-lg">
            <div class="card-header bg-transparent border-secondary border-opacity-25">
                <h6 class="mb-0 text-white">Category Radar</h6>
            </div>
            <div class="card-body">
                <div id="categoryChart" style="width: 100%; height: 300px;"></div>
            </div>
        </div>
    </div>

    
    <div class="col-lg-4">
        <div class="card border-secondary border-opacity-25 h-100 shadow-lg">
            <div class="card-header bg-transparent border-secondary border-opacity-25">
                <h6 class="mb-0 text-white">Priority Levels</h6>
            </div>
            <div class="card-body">
                <div id="priorityChart" style="width: 100%; height: 300px;"></div>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<script>
    // --- Data ---
    const trendKeys = <?php echo json_encode(array_keys($trend_data)); ?>;
    const trendValues = <?php echo json_encode(array_values($trend_data)); ?>;

    const statusData = <?php echo json_encode(array_map(function($k, $v){ return ['name'=>$k, 'value'=>$v]; }, array_keys($status_data), array_values($status_data))); ?>;
    
    const categoryKeys = <?php echo json_encode(array_keys($category_data)); ?>;
    const categoryValues = <?php echo json_encode(array_values($category_data)); ?>;
    const maxCategory = Math.max(...categoryValues, 10); // For Radar Axis

    const priorityKeys = <?php echo json_encode(array_keys($priority_data)); ?>;
    const priorityValues = <?php echo json_encode(array_values($priority_data)); ?>;


    // --- 1. Main Trend Chart (Animated Line) ---
    const trendChart = echarts.init(document.getElementById('trendChart'), 'dark', {renderer: 'canvas'});
    const trendOption = {
        backgroundColor: 'transparent',
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross', label: { backgroundColor: '#6a7985' } }
        },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: trendKeys,
            axisLabel: { color: '#ccc' },
            axisLine: { lineStyle: { color: '#555' } }
        },
        yAxis: {
            type: 'value',
            axisLabel: { color: '#ccc' },
            splitLine: { lineStyle: { color: '#333' } }
        },
        series: [{
            name: 'Tickets',
            type: 'line',
            smooth: true, // Curved line
            symbol: 'circle',
            symbolSize: 8,
            sampling: 'average',
            itemStyle: { color: '#0dcaf0' },
            // Area Gradient
            areaStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: 'rgba(13, 202, 240, 0.5)' },
                    { offset: 1, color: 'rgba(13, 202, 240, 0.0)' }
                ])
            },
            data: trendValues,
            // Animation configs
            animationDuration: 2000,
            animationEasing: 'cubicOut'
        }]
    };
    trendChart.setOption(trendOption);


    // --- 2. Status Chart (Donut with Animation) ---
    const statusChart = echarts.init(document.getElementById('statusChart'), 'dark');
    statusChart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'item' },
        legend: { bottom: '0%', left: 'center', textStyle: { color: '#aaa' } },
        series: [{
            name: 'Status',
            type: 'pie',
            radius: ['40%', '70%'],
            center: ['50%', '45%'],
            itemStyle: {
                borderRadius: 5,
                borderColor: '#1e1e2f',
                borderWidth: 2
            },
            data: statusData,
            animationType: 'scale',
            animationEasing: 'elasticOut',
            animationDelay: function (idx) { return Math.random() * 200; }
        }]
    });

    // --- 3. Category Chart (Radar - Line based!) ---
    const categoryChart = echarts.init(document.getElementById('categoryChart'), 'dark');
    categoryChart.setOption({
        backgroundColor: 'transparent',
        tooltip: {},
        radar: {
            indicator: categoryKeys.map(k => ({ name: k, max: maxCategory })),
            shape: 'circle',
            splitArea: {
                areaStyle: {
                    color: ['rgba(114, 172, 209, 0.2)', 'rgba(114, 172, 209, 0.4)', 'rgba(114, 172, 209, 0.6)'],
                    shadowColor: 'rgba(0, 0, 0, 0.3)',
                    shadowBlur: 10
                }
            },
            axisName: { color: '#fff' }
        },
        series: [{
            name: 'Category',
            type: 'radar',
            data: [{
                value: categoryValues,
                name: 'Ticket Counts',
                areaStyle: { color: 'rgba(255, 193, 7, 0.5)' },
                lineStyle: { type: 'dashed' }
            }],
            animationDuration: 1500
        }]
    });

    // --- 4. Priority Chart (Pictorial Bar / Animated Bar) ---
    const priorityChart = echarts.init(document.getElementById('priorityChart'), 'dark');
    priorityChart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis' },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: priorityKeys, axisLabel: { color: '#ccc' } },
        yAxis: { type: 'value', axisLabel: { color: '#ccc' }, splitLine: { lineStyle: { color: '#333' } } },
        series: [{
            data: priorityValues,
            type: 'bar', // Using Bar for clarity, but animating it
            showBackground: true,
            backgroundStyle: { color: 'rgba(180, 180, 180, 0.2)' },
            itemStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: '#83bff6' },
                    { offset: 0.5, color: '#188df0' },
                    { offset: 1, color: '#188df0' }
                ])
            },
            animationDelay: function (idx) { return idx * 100 + 1000; } // Delay start
        }]
    });

    // Responsive
    window.addEventListener('resize', function() {
        trendChart.resize();
        statusChart.resize();
        categoryChart.resize();
        priorityChart.resize();
    });
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>