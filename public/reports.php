<?php
require_once __DIR__ . '/../views/header.php';

require_login();
if (!has_privilege('view_reports')) {
    redirect('dashboard.php');
}


$total_tickets = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$resolved_tickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved' OR status = 'closed'")->fetchColumn();
$resolution_rate = $total_tickets > 0 ? round(($resolved_tickets / $total_tickets) * 100, 1) : 0;


$tickets_by_category = $pdo->query("SELECT category, COUNT(*) as count FROM tickets GROUP BY category")->fetchAll();


$tickets_by_priority = $pdo->query("SELECT priority, COUNT(*) as count FROM tickets GROUP BY priority")->fetchAll();


$staff_performance = $pdo->query("
    SELECT u.full_name, COUNT(t.id) as resolved_count 
    FROM users u 
    JOIN tickets t ON u.id = t.assigned_to 
    WHERE t.status = 'resolved' OR t.status = 'closed' 
    GROUP BY u.id 
    ORDER BY resolved_count DESC 
    LIMIT 5
")->fetchAll();

$sla_stats = get_sla_stats($pdo);

$depreciation_data = $pdo->query("
    SELECT 
        id,
        item_name,
        category,
        brand,
        purchase_date,
        warranty_expiry,
        status,
        CASE 
            WHEN purchase_date IS NULL THEN 100
            WHEN DATEDIFF(NOW(), purchase_date) <= 0 THEN 100
            ELSE ROUND(GREATEST(0, 100 - (DATEDIFF(NOW(), purchase_date) / 1825) * 100), 1)
        END as depreciation_pct
    FROM assets 
    WHERE deleted_at IS NULL 
    ORDER BY depreciation_pct ASC
    LIMIT 10
")->fetchAll();

$sla_stats = get_sla_stats($pdo);
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Analytics</h6>
        <h1 class="h2 mb-0 fw-bold text-white">System Reports</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="reports_export.php" class="btn btn-primary btn-sm"><i class="fas fa-download me-2"></i>Export CSV</a>
    </div>
</div>


<div class="row mb-4 g-4">
    <div class="col-md-4">
        <div class="stat-card h-100" data-tilt data-tilt-glare data-tilt-max-glare="0.5">
            <div class="d-flex justify-content-between mb-4">
                <div class="stat-icon text-primary bg-primary bg-opacity-10">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                
                <div class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Live</div>
            </div>
            <h2 class="display-6 fw-bold text-white mb-1"><?php echo $total_tickets; ?></h2>
            <div class="text-secondary small">Total Tickets</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card h-100" data-tilt data-tilt-glare data-tilt-max-glare="0.5">
            <div class="d-flex justify-content-between mb-4">
                <div class="stat-icon text-success bg-success bg-opacity-10">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <h2 class="display-6 fw-bold text-white mb-1"><?php echo $resolved_tickets; ?></h2>
            <div class="text-secondary small">Resolved Issues</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card h-100" data-tilt data-tilt-glare data-tilt-max-glare="0.5">
            <div class="d-flex justify-content-between mb-4">
                <div class="stat-icon text-info bg-info bg-opacity-10">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>
            <h2 class="display-6 fw-bold text-white mb-1"><?php echo $resolution_rate; ?>%</h2>
            <div class="text-secondary small">Resolution Rate</div>
        </div>
    </div>
</div>


<ul class="nav nav-pills mb-4" id="analyticsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="category-tab" data-bs-toggle="tab" data-bs-target="#category" type="button" role="tab" aria-controls="category" aria-selected="true">
            <i class="fas fa-tags me-2"></i>Category Distribution
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="priority-tab" data-bs-toggle="tab" data-bs-target="#priority" type="button" role="tab" aria-controls="priority" aria-selected="false">
            <i class="fas fa-layer-group me-2"></i>Priority Analysis
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab" aria-controls="staff" aria-selected="false">
            <i class="fas fa-users me-2"></i>Staff Performance
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="sla-tab" data-bs-toggle="tab" data-bs-target="#sla" type="button" role="tab" aria-controls="sla" aria-selected="false">
            <i class="fas fa-clock me-2"></i>SLA Compliance
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="depreciation-tab" data-bs-toggle="tab" data-bs-target="#depreciation" type="button" role="tab" aria-controls="depreciation" aria-selected="false">
            <i class="fas fa-chart-line me-2"></i>Depreciation
        </button>
    </li>
</ul>


<div class="tab-content" id="analyticsTabsContent">
    
    
    <div class="tab-pane fade show active" id="category" role="tabpanel" aria-labelledby="category-tab">
        <div class="card border-secondary border-opacity-25 shadow-lg">
            <div class="card-header bg-transparent border-secondary border-opacity-25 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white fw-bold">Ticket Categories (Pie)</h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#categoryData" aria-expanded="false">
                    <i class="fas fa-table me-1"></i> View Data
                </button>
            </div>
            <div class="card-body p-4">
                <div id="categoryChart" style="width: 100%; height: 500px;"></div>
            </div>
            <div class="collapse" id="categoryData">
                <div class="card-footer bg-transparent border-top border-secondary border-opacity-25">
                     <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 text-white">
                            <thead><tr><th>Category</th><th class="text-end">Count</th></tr></thead>
                            <tbody>
                                <?php foreach ($tickets_by_category as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td class="text-end"><?php echo $row['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="tab-pane fade" id="priority" role="tabpanel" aria-labelledby="priority-tab">
        <div class="card border-secondary border-opacity-25 shadow-lg">
             <div class="card-header bg-transparent border-secondary border-opacity-25 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white fw-bold">Ticket Priorities (3D)</h5>
                 <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#priorityData" aria-expanded="false">
                    <i class="fas fa-table me-1"></i> View Data
                </button>
            </div>
            <div class="card-body p-4">
                <div id="priorityChart" style="width: 100%; height: 500px;"></div>
            </div>
             <div class="collapse" id="priorityData">
                <div class="card-footer bg-transparent border-top border-secondary border-opacity-25">
                     <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 text-white">
                            <thead><tr><th>Priority</th><th class="text-end">Count</th></tr></thead>
                            <tbody>
                                <?php foreach ($tickets_by_priority as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['priority']); ?></td>
                                    <td class="text-end"><?php echo $row['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="tab-pane fade" id="staff" role="tabpanel" aria-labelledby="staff-tab">
        <div class="card border-secondary border-opacity-25 shadow-lg">
             <div class="card-header bg-transparent border-secondary border-opacity-25 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white fw-bold">Top Resolvers (Bar)</h5>
                 <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#staffData" aria-expanded="false">
                    <i class="fas fa-table me-1"></i> View Data
                </button>
            </div>
            <div class="card-body p-4">
                <div id="staffChart" style="width: 100%; height: 500px;"></div>
            </div>
             <div class="collapse" id="staffData">
                <div class="card-footer bg-transparent border-top border-secondary border-opacity-25">
                     <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 text-white">
                            <thead><tr><th>Staff</th><th class="text-end">Resolved</th></tr></thead>
                            <tbody>
                                <?php foreach ($staff_performance as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td class="text-end"><?php echo $row['resolved_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="sla" role="tabpanel" aria-labelledby="sla-tab">
        <div class="card border-secondary border-opacity-25 shadow-lg">
            <div class="card-header bg-transparent border-secondary border-opacity-25 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white fw-bold">SLA Compliance by Priority</h5>
                <div class="d-flex gap-2">
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">On Track: <?php echo $sla_stats['sla_ok']; ?></span>
                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">Breached: <?php echo $sla_stats['sla_breached']; ?></span>
                </div>
            </div>
            <div class="card-body p-4">
                <div id="slaChart" style="width: 100%; height: 500px;"></div>
            </div>
            <div class="collapse" id="slaData">
                <div class="card-footer bg-transparent border-top border-secondary border-opacity-25">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 text-white">
                            <thead><tr><th>Priority</th><th class="text-end">SLA Threshold</th><th class="text-end">Open Tickets</th><th class="text-end">Breached</th></tr></thead>
                            <tbody>
                                <?php
                                $priority_counts = $pdo->query("SELECT priority, COUNT(*) as count FROM tickets WHERE status IN ('open', 'in_progress') GROUP BY priority")->fetchAll();
                                foreach (['urgent', 'high', 'medium', 'low'] as $priority):
                                    $count = 0;
                                    foreach ($priority_counts as $pc) {
                                        if ($pc['priority'] === $priority) { $count = $pc['count']; break; }
                                    }
                                    $threshold_hours = get_sla_threshold_seconds($priority) / 3600;
                                ?>
                                <tr>
                                    <td class="text-uppercase"><?php echo $priority; ?></td>
                                    <td class="text-end"><?php echo $threshold_hours; ?>h</td>
                                    <td class="text-end"><?php echo $count; ?></td>
                                    <td class="text-end text-danger"><?php echo $count; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="depreciation" role="tabpanel" aria-labelledby="depreciation-tab">
        <div class="card border-secondary border-opacity-25 shadow-lg">
            <div class="card-header bg-transparent border-secondary border-opacity-25 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white fw-bold">Asset Depreciation (Top 10)</h5>
                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3">5-Year Lifecycle</span>
            </div>
            <div class="card-body p-4">
                <div id="depreciationChart" style="width: 100%; height: 500px;"></div>
            </div>
            <div class="collapse" id="depreciationData">
                <div class="card-footer bg-transparent border-top border-secondary border-opacity-25">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 text-white">
                            <thead><tr><th>Asset</th><th>Category</th><th>Purchase Date</th><th class="text-end">Depreciation</th></tr></thead>
                            <tbody>
                                <?php foreach ($depreciation_data as $asset): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($asset['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['category']); ?></td>
                                        <td><?php echo $asset['purchase_date'] ? format_date($asset['purchase_date']) : 'N/A'; ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-<?php echo $asset['depreciation_pct'] > 80 ? 'danger' : ($asset['depreciation_pct'] > 50 ? 'warning' : 'success'); ?> bg-opacity-10 text-<?php echo $asset['depreciation_pct'] > 80 ? 'danger' : ($asset['depreciation_pct'] > 50 ? 'warning' : 'success'); ?>">
                                                <?php echo $asset['depreciation_pct']; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/echarts-gl@2.0.9/dist/echarts-gl.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>

<script>
    // Data from PHP
    const categoryData = <?php echo json_encode(array_map(function($row) {
        return ['name' => ucfirst($row['category']), 'value' => $row['count']];
    }, $tickets_by_category)); ?>;

    const priorityLabels = <?php echo json_encode(array_column($tickets_by_priority, 'priority')); ?>;
    const priorityValues = <?php echo json_encode(array_column($tickets_by_priority, 'count')); ?>;

    const staffLabels = <?php echo json_encode(array_column($staff_performance, 'full_name')); ?>;
    const staffValues = <?php echo json_encode(array_column($staff_performance, 'resolved_count')); ?>;

    const colorPalette = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'];

    // --- Charts ---

    // 1. Category (Pie)
    const categoryChart = echarts.init(document.getElementById('categoryChart'), 'dark', {renderer: 'canvas'});
    const categoryOption = {
        backgroundColor: 'transparent',
        tooltip: { trigger: 'item', formatter: '{a} <br/>{b}: {c} ({d}%)' },
        legend: { top: 'bottom', textStyle: { color: '#ccc' } },
        series: [
            {
                name: 'Tickets',
                type: 'pie',
                radius: ['40%', '70%'],
                avoidLabelOverlap: false,
                itemStyle: {
                    borderRadius: 10,
                    borderColor: '#1e1e2f',
                    borderWidth: 2
                },
                label: { show: false, position: 'center' },
                emphasis: {
                    label: { show: true, fontSize: 24, fontWeight: 'bold' }
                },
                labelLine: { show: false },
                data: categoryData
            }
        ]
    };
    categoryChart.setOption(categoryOption);

    // 2. Priority (3D Bar)
    const priorityChart = echarts.init(document.getElementById('priorityChart'), 'dark');
    // Prepare 3D Data: [x, y, z] -> [index, 0, value]
    const priorityData3D = priorityValues.map((val, index) => {
        return {
            value: [index, 0, val],
            itemStyle: { color: colorPalette[index % colorPalette.length] }
        };
    });

    const priorityOption = {
        backgroundColor: 'transparent',
        tooltip: {},
        xAxis3D: {
            type: 'category',
            data: priorityLabels,
            name: 'Priority',
            axisLabel: { color: '#ccc', interval: 0 }
        },
        yAxis3D: {
            type: 'category',
            data: [''],
            axisTick: { show: false },
            axisLabel: { show: false }
        },
        zAxis3D: {
            type: 'value',
            name: 'Count',
            axisLabel: { color: '#ccc' }
        },
        grid3D: {
            boxWidth: 200,
            boxDepth: 20,
            viewControl: {
                autoRotate: true,
                alpha: 30,
                beta: 30,
                projection: 'perspective' // or 'orthographic'
            },
            light: {
                main: { intensity: 1.2, shadow: true },
                ambient: { intensity: 0.3 }
            }
        },
        series: [{
            type: 'bar3D',
            data: priorityData3D,
            shading: 'lambert',
            label: {
                show: true,
                fontSize: 14,
                borderWidth: 1,
                formatter: '{c}' // Show count
            },
            emphasis: { label: { show: true } }
        }]
    };
    priorityChart.setOption(priorityOption);

    // 3. Staff (Horizontal Bar)
    const staffChart = echarts.init(document.getElementById('staffChart'), 'dark');
    const staffOption = {
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'value', axisLabel: { color: '#ccc' }, splitLine: { lineStyle: { color: '#333' } } },
        yAxis: { type: 'category', data: staffLabels, axisLabel: { color: '#ccc' } },
        series: [{
            name: 'Resolved',
            type: 'bar',
            data: staffValues,
            itemStyle: {
                color: {
                    type: 'linear', x: 0, y: 0, x2: 1, y2: 0,
                    colorStops: [{ offset: 0, color: '#20c997' }, { offset: 1, color: '#198754' }]
                },
                borderRadius: [0, 5, 5, 0]
            }
        }]
    };
    staffChart.setOption(staffOption);

    // 4. SLA Compliance (Gauge)
    const slaChart = echarts.init(document.getElementById('slaChart'), 'dark');
    const slaOption = {
        backgroundColor: 'transparent',
        series: [
            {
                type: 'gauge',
                startAngle: 180,
                endAngle: 0,
                min: 0,
                max: 100,
                splitNumber: 5,
                axisLine: {
                    lineStyle: {
                        width: 10,
                        color: [
                            [0.7, '#dc3545'],
                            [0.9, '#ffc107'],
                            [1, '#198754']
                        ]
                    }
                },
                pointer: {
                    icon: 'path://M12.8,0.7l12,40.1H0.7L12.8,0.7z',
                    length: '12%',
                    width: 8,
                    offsetCenter: [0, '-60%'],
                    itemStyle: { color: 'auto' }
                },
                axisTick: { length: 8, lineStyle: { color: 'auto', width: 2 } },
                splitLine: { length: 15, lineStyle: { color: 'auto', width: 3 } },
                axisLabel: { color: '#ccc', fontSize: 14, distance: -60 },
                title: { offsetCenter: [0, '-20%'], fontSize: 20, color: '#fff' },
                detail: {
                    fontSize: 40,
                    offsetCenter: [0, '0%'],
                    valueAnimation: true,
                    formatter: '{value}%',
                    color: 'auto'
                },
                data: [
                    {
                        value: <?php echo $sla_stats['sla_compliance']; ?>,
                        name: 'SLA Compliance',
                        title: { offsetCenter: [0, '20%'], fontSize: 14, color: '#ccc' }
                    }
                ]
            }
        ]
    };
    slaChart.setOption(slaOption);

    // 5. Depreciation (Horizontal Bar)
    const depreciationData = <?php echo json_encode(array_map(function($row) {
        return ['name' => $row['item_name'], 'value' => $row['depreciation_pct']];
    }, $depreciation_data)); ?>;

    const depChart = echarts.init(document.getElementById('depreciationChart'), 'dark');
    const depOption = {
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'value', max: 100, axisLabel: { color: '#ccc', formatter: '{value}%' }, splitLine: { lineStyle: { color: '#333' } } },
        yAxis: { type: 'category', data: depreciationData.map(d => d.name), axisLabel: { color: '#ccc' } },
        series: [{
            name: 'Depreciation',
            type: 'bar',
            data: depreciationData.map(d => d.value),
            itemStyle: {
                color: {
                    type: 'linear', x: 0, y: 0, x2: 1, y2: 0,
                    colorStops: [
                        { offset: 0, color: '#198754' },
                        { offset: 0.5, color: '#ffc107' },
                        { offset: 1, color: '#dc3545' }
                    ]
                },
                borderRadius: [0, 5, 5, 0]
            }
        }]
    };
    depChart.setOption(depOption);

    // Resize logic
    const tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEls.forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', function (event) {
            categoryChart.resize();
            priorityChart.resize();
            staffChart.resize();
            slaChart.resize();
            depChart.resize();
        });
    });

    window.addEventListener('resize', function() {
        categoryChart.resize();
        priorityChart.resize();
        staffChart.resize();
        slaChart.resize();
        depChart.resize();
    });
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
