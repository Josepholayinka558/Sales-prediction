<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
require_once dirname(__FILE__, 2) . '/app/helpers/SalesPredictor.php';
auth()->authorize('view_dashboard');

$currentPage = 'dashboard';
$pageTitle = 'Dashboard';
$pageIcon = 'fas fa-gauge-high';

$saleModel = new Sale();
$orderModel = new Order();
$productModel = new Product();
$sp = new SalesPredictor();

// ---------- everything below is REAL data from the imported dataset ----------
$totalRevenue = (float)$saleModel->totalRevenue();
$totalProfit  = (float)$saleModel->totalProfit();
$salesCount   = (int)db()->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$totalOrders  = (int)db()->query("SELECT COUNT(DISTINCT order_id) FROM sales")->fetchColumn();
$totalUnits   = (float)$saleModel->totalUnits();
$avgOrderValue = $totalOrders ? $totalRevenue / $totalOrders : 0;
$totalProducts = (int)db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
$range = $sp->dataRange();
$hasData = $salesCount > 0;

// complete months only, newest last (last 8 for sparklines)
list($allMonths, $fitMonths) = $sp->monthWindow();
$monthly = [];
if ($hasData) {
    foreach (db()->query("SELECT DATE_FORMAT(sale_date,'%Y-%m') ym, SUM(sales_amount) rev, SUM(quantity) units, COUNT(DISTINCT order_id) orders FROM sales GROUP BY ym ORDER BY ym")->fetchAll() as $r) $monthly[$r['ym']] = $r;
}
$series = function ($field) use ($fitMonths, $monthly) {
    $out = [];
    foreach (array_slice($fitMonths, -8) as $m) $out[] = (float)($monthly[$m][$field] ?? 0);
    return $out;
};
$changeOf = function ($arr) {
    $n = count($arr);
    if ($n < 2 || $arr[$n - 2] <= 0) return null;
    return round(($arr[$n - 1] - $arr[$n - 2]) / $arr[$n - 2] * 100, 1);
};
$revS = $series('rev'); $ordS = $series('orders'); $unitS = $series('units');
$aovS = []; foreach ($revS as $i => $v) $aovS[] = $ordS[$i] > 0 ? $v / $ordS[$i] : 0;

function sparkArr($vals) {
    $max = max(array_merge($vals, [1]));
    $out = [];
    foreach ($vals as $v) $out[] = max(12, (int)round($v / $max * 100));
    return $out ?: [12, 12, 12];
}
function sparkHtml($arr, $dir = 'up') {
    $html = '<div class="kpi-spark">';
    foreach ($arr as $v) $html .= '<span style="height:' . (int)$v . '%;"></span>';
    return $html . '</div>';
}
function changeHtml($pct) {
    if ($pct === null) return '<div class="kpi-change up" style="background:#f3f4f6;color:#6b7280;">vs previous month: n/a</div>';
    $cls = $pct >= 0 ? 'up' : 'down';
    return '<div class="kpi-change ' . $cls . '"><i class="fas fa-arrow-' . ($pct >= 0 ? 'up' : 'down') . '"></i> ' . ($pct >= 0 ? '+' : '') . $pct . '% vs previous month</div>';
}

// revenue chart data: months of each year
$years = $saleModel->yearsAvailable();
$yearData = [];
foreach (array_slice($years, -5) as $y) $yearData[$y] = $saleModel->monthsOfYear($y)['revenue'];
$selYears = array_slice(array_keys($yearData), -2);

// year progress: actual so far vs predicted full year
$focusYear = $years ? (int)end($years) : (int)date('Y');
if ($hasData && (int)substr($range['max'], 5, 2) === 12 && (int)substr($range['max'], 8, 2) >= 28) $focusYear++;
$yp = $hasData ? $sp->predictYear($focusYear) : ['ok' => false];
$ypActual = 0; $ypTotal = 0; $ypPct = 0;
if (!empty($yp['ok'])) {
    $ypTotal = (float)$yp['summary']['total_revenue'];
    foreach ($yp['actual_revenue'] as $v) $ypActual += (float)($v ?? 0);
    $ypPct = $ypTotal > 0 ? min(100, round($ypActual / $ypTotal * 100, 1)) : 0;
}

// revenue by category (donut)
$palette = ['#7c3aed', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#94a3b8'];
$catRows = db()->query("SELECT COALESCE(c.name,'Uncategorised') name, SUM(s.sales_amount) rev, SUM(s.quantity) units FROM sales s LEFT JOIN categories c ON s.category_id = c.id GROUP BY c.id, c.name ORDER BY rev DESC")->fetchAll();
$devices = []; $other = 0; $catTotal = array_sum(array_map(fn($r) => (float)$r['rev'], $catRows));
foreach ($catRows as $i => $r) {
    if ($i < 5) $devices[] = ['name' => $r['name'], 'pct' => $catTotal ? round($r['rev'] / $catTotal * 100, 1) : 0, 'color' => $palette[$i]];
    else $other += (float)$r['rev'];
}
if ($other > 0) $devices[] = ['name' => 'Other', 'pct' => round($other / $catTotal * 100, 1), 'color' => $palette[5]];
$devicesTotal = $totalUnits;

// best selling products (top 5 by revenue)
$bsp = [];
$topP = db()->query("SELECT p.name, SUM(s.sales_amount) rev FROM sales s JOIN products p ON s.product_id = p.id GROUP BY p.id, p.name ORDER BY rev DESC LIMIT 5")->fetchAll();
$topMax = $topP ? max(1, (float)$topP[0]['rev']) : 1;
foreach ($topP as $r) $bsp[] = ['name' => $r['name'], 'pct' => round($r['rev'] / $topMax * 100, 1), 'rev' => (float)$r['rev']];

// latest sales
$orders = [];
$statusMap = ['delivered' => 'completed', 'completed' => 'completed', 'pending' => 'pending', 'processing' => 'pending', 'shipped' => 'shipped', 'cancelled' => 'refund', 'returned' => 'refund'];
foreach (db()->query("SELECT s.id, s.sale_date, s.quantity, s.sales_amount, s.profit, p.name pname, o.status FROM sales s LEFT JOIN products p ON s.product_id = p.id LEFT JOIN orders o ON s.order_id = o.id ORDER BY s.sale_date DESC, s.id DESC LIMIT 9")->fetchAll() as $i => $r) {
    $st = strtolower($r['status'] ?? 'completed');
    $orders[] = ['id' => $r['id'], 'name' => $r['pname'] ?? 'Unknown product', 'thumb' => 't' . (($i % 5) + 1), 'badge' => strtoupper(substr($r['pname'] ?? '?', 0, 3)),
                 'qty' => 'x' . (int)$r['quantity'], 'date' => date('M d, Y', strtotime($r['sale_date'])), 'revenue' => '$' . number_format($r['sales_amount'], 2),
                 'profit' => '$' . number_format($r['profit'], 2), 'status' => ucfirst($st === 'delivered' ? 'completed' : $st), 'cls' => $statusMap[$st] ?? 'shipped'];
}

// AI prediction preview: next 12 months vs last 12 months
$pvTotal = 0; $pvPrev = 0; $pvAcc = null; $pvMonths = 0;
if ($hasData && $fitMonths) {
    $revAll = array_column(array_values($sp->monthlySeries($fitMonths)), 'revenue');
    $f = $sp->forecastSeries($revAll, (int)substr($fitMonths[0], 5, 2), 12);
    $pvTotal = array_sum($f['pred']);
    $last = array_slice($revAll, -12); $pvMonths = count($last); $pvPrev = array_sum($last);
    $bt = $sp->backtest($revAll, (int)substr($fitMonths[0], 5, 2)); $pvAcc = $bt['accuracy_pct'] ?? null;
}
$pvChange = $pvPrev > 0 ? round(($pvTotal - $pvPrev) / $pvPrev * 100 * (12 / max(1, $pvMonths)), 1) : null;

include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<div class="fade-in">
    <div class="page-head">
        <h1>Dashboard</h1>
        <div class="date-filter">
            <i class="far fa-calendar me-1"></i>
            <?= $hasData ? date('M d, Y', strtotime($range['min'])) . ' – ' . date('M d, Y', strtotime($range['max'])) : 'No data imported yet' ?>
        </div>
    </div>

    <?php if (!$hasData): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center"><span><i class="fas fa-circle-info me-2"></i>No sales data yet. Import your dataset to see real numbers here.</span><a href="<?= url('dataset.php') ?>" class="btn btn-sm btn-primary">Import data</a></div>
    <?php endif; ?>
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6"><div class="kpi-card"><div class="kpi-top"><div class="kpi-label">Total Sales</div><?= sparkHtml(sparkArr($revS)) ?></div>
            <div class="kpi-value">$<?= number_format($totalRevenue, 0) ?></div><?= changeHtml($changeOf($revS)) ?></div></div>
        <div class="col-xl-3 col-md-6"><div class="kpi-card"><div class="kpi-top"><div class="kpi-label">Orders</div><?= sparkHtml(sparkArr($ordS)) ?></div>
            <div class="kpi-value"><?= number_format($totalOrders) ?></div><?= changeHtml($changeOf($ordS)) ?></div></div>
        <div class="col-xl-3 col-md-6"><div class="kpi-card"><div class="kpi-top"><div class="kpi-label">Units Sold</div><?= sparkHtml(sparkArr($unitS)) ?></div>
            <div class="kpi-value"><?= number_format($totalUnits) ?></div><?= changeHtml($changeOf($unitS)) ?></div></div>
        <div class="col-xl-3 col-md-6"><div class="kpi-card"><div class="kpi-top"><div class="kpi-label">Avg Order Value</div><?= sparkHtml(sparkArr($aovS)) ?></div>
            <div class="kpi-value">$<?= number_format($avgOrderValue, 2) ?></div><?= changeHtml($changeOf($aovS)) ?></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="revenue-top">
                        <div class="revenue-info">
                            <div class="kpi-label">Revenue</div>
                            <h3 id="rev-total">$0</h3>
                            <div class="rev-change" id="rev-change"></div>
                        </div>
                        <div class="revenue-actions">
                            <div class="year-tabs" id="year-tabs"></div>
                            <button class="add-year-btn" id="add-year" type="button">+ Add a year</button>
                            <button class="interval-drop" id="interval" type="button"><span id="interval-label">Monthly</span> <i class="fas fa-caret-down ms-1" style="font-size:0.65rem;"></i></button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div style="width:100%;height:280px;padding-top:10px;">
                        <div style="display:flex;flex-direction:column;height:100%;">
                            <div id="rev-bars" style="flex:1;display:grid;gap:10px;align-items:end;padding:0 10px 10px;border-bottom:1px solid #f3f4f6;border-left:1px solid #f3f4f6;"></div>
                            <div id="rev-labels" style="display:grid;gap:10px;padding-top:8px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card side-card sales-target">
                <div class="side-card-title">
                    <h5>Year Progress <?= (int)$focusYear ?></h5>
                    <a class="dot-btn" href="<?= url('predict.php?year=' . (int)$focusYear) ?>" title="Open the prediction for <?= (int)$focusYear ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-ellipsis-h"></i></a>
                </div>
                <div class="target-value">$<?= number_format($ypActual, 0) ?></div>
                <div class="target-sub">actual so far / $<?= number_format($ypTotal, 0) ?> predicted for the year</div>
                <div class="progress-xl"><div class="progress-bar" style="width:<?= $ypPct ?>%;"></div></div>
                <div class="progress-meta"><span>Actual vs. predicted full year</span><span><?= $ypPct ?>%</span></div>
            </div>

            <div class="card side-card">
                <div class="side-card-title">
                    <h5>Revenue by category</h5>
                    <a href="<?= url('analytics.php') ?>" class="text-link" style="font-size:0.78rem;">Details →</a>
                </div>
                <?php
                    $totalAngle = 0;
                    $segs = [];
                    foreach ($devices as $d){
                        $segs[] = [$d['color'], $totalAngle, $totalAngle + ($d['pct']*3.6)];
                        $totalAngle += $d['pct']*3.6;
                    }
                    $grad = [];
                    foreach ($segs as $s){
                        for ($a = $s[1]; $a < $s[2]; $a += 0.6){
                            $grad[] = $s[0] . ' ' . $a . 'deg';
                        }
                    }
                    $bg = 'conic-gradient(' . implode(', ', $grad) . ')';
                ?>
                <div class="donut-wrap">
                    <div style="width:180px;height:180px;border-radius:50%;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;">
                        <div style="width:130px;height:130px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;">
                            <div class="donut-center">
                                <div class="num"><?= number_format($devicesTotal) ?></div>
                                <div class="lbl">Units sold</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="legend">
                    <?php foreach ($devices as $d): ?>
                        <div class="legend-item">
                            <div class="legend-left">
                                <span class="legend-dot" style="background:<?= $d['color'] ?>;"></span>
                                <span><?= e($d['name']) ?></span>
                            </div>
                            <span class="legend-pct"><?= $d['pct'] ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card side-card bsp-wrap">
                <div class="side-card-title">
                    <h5>Best Selling Products</h5>
                    <i class="fas fa-filter" style="color:#9ca3af;font-size:0.85rem;"></i>
                </div>
                <div class="bsp-hint">Top 5 products by revenue</div>
                <?php foreach ($bsp as $b): ?>
                    <div class="bsp-item">
                        <div class="bsp-label">
                            <span><?= e($b['name']) ?></span>
                            <span style="color:#6b7280;">$<?= number_format($b['rev'], 0) ?></span>
                        </div>
                        <div class="bsp-bar">
                            <div style="width:<?= $b['pct'] ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 style="font-weight:500;color:#6b7280;font-size:0.85rem;">Latest Sales</h5>
            <div class="search-box d-none d-md-block" style="position:relative;">
                <i class="fas fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.8rem;"></i>
                <input type="text" id="latestSearch" placeholder="Search" style="padding:8px 14px 8px 36px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;font-size:0.78rem;min-width:200px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>QTY</th>
                            <th>Date</th>
                            <th>Revenue</th>
                            <th>Net Profit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="latestBody">
                        <?php foreach ($orders as $o):
                            $sClass = $o['cls'];
                        ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <div class="product-thumb <?= $o['thumb'] ?>">
                                            <span style="font-size:0.65rem;font-weight:700;color:#92400e;"><?= $o['badge'] ?></span>
                                        </div>
                                        <div class="product-name"><?= e($o['name']) ?></div>
                                    </div>
                                </td>
                                <td><?= $o['qty'] ?></td>
                                <td class="text-muted-sm"><?= $o['date'] ?></td>
                                <td class="text-money"><?= $o['revenue'] ?></td>
                                <td class="text-money" style="color:#6b7280;"><?= $o['profit'] ?></td>
                                <td>
                                    <span class="status-pill <?= $sClass ?>"><?= e($o['status']) ?></span>
                                </td>
                                <td><button class="action-menu" type="button" title="View details" onclick="viewItem('sale', <?= (int)$o['id'] ?>)"><i class="fas fa-ellipsis-h"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card side-card sales-target">
                <div class="side-card-title">
                    <h5>AI Sales Prediction</h5>
                    <a href="<?= url('predict.php') ?>" class="text-link" style="font-size:0.78rem;">Open predictions →</a>
                </div>
                <p class="mb-3 text-muted-sm">Expected sales over the next 12 months, based on your history</p>
                <div class="kpi-value">$<?= number_format($pvTotal, 0) ?><?php if ($pvChange !== null): ?><span style="font-size:0.85rem;font-weight:600;color:<?= $pvChange >= 0 ? '#10b981' : '#ef4444' ?>;margin-left:8px;"><?= $pvChange >= 0 ? '+' : '' ?><?= $pvChange ?>%</span><?php endif; ?></div>
                <div style="margin-top:18px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.78rem;color:#6b7280;margin-bottom:6px;">
                        <span>Back-test accuracy</span><span class="fw-bold text-dark"><?= $pvAcc !== null ? $pvAcc . '%' : 'n/a (needs more history)' ?></span>
                    </div>
                    <div class="progress-xl"><div class="progress-bar" style="width:<?= (float)($pvAcc ?? 0) ?>%;background:linear-gradient(90deg,#10b981,#34d399);"></div></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card side-card">
                <div class="side-card-title">
                    <h5>System Stats</h5>
                    <i class="fas fa-chart-line" style="color:#7c3aed;"></i>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted-sm" style="font-size:0.75rem;">Dataset Records</div>
                        <div class="kpi-value" style="font-size:1.35rem;"><?= number_format($salesCount) ?></div>
                        <div class="kpi-change up" style="font-size:0.65rem;padding:2px 8px;">
                            <i class="fas fa-database me-1"></i>sales
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted-sm" style="font-size:0.75rem;">Avg Order Value</div>
                        <div class="kpi-value" style="font-size:1.35rem;">$<?= number_format($avgOrderValue, 0) ?></div>
                        <div class="kpi-change up" style="font-size:0.65rem;padding:2px 8px;">
                            <i class="fas fa-tag me-1"></i>AOV
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted-sm" style="font-size:0.75rem;">Products / Categories</div>
                        <div class="kpi-value" style="font-size:1.35rem;"><?= $totalProducts ?> <span style="font-size:0.7rem;color:#6b7280;">items</span></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted-sm" style="font-size:0.75rem;">Total Profit</div>
                        <div class="kpi-value" style="font-size:1.35rem;">$<?= number_format($totalProfit, 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
(function () {
    const YEAR_DATA = <?= json_encode($yearData) ?>;
    let selected = <?= json_encode(array_map('strval', $selYears)) ?>;
    let quarterly = false;
    const COLORS = ['#e5e7eb', '#7c3aed', '#f59e0b', '#10b981', '#3b82f6'];
    const MON = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const money = n => '$' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
    const years = Object.keys(YEAR_DATA);

    function series(y) {
        const m = YEAR_DATA[y];
        if (!quarterly) return m;
        return [0, 1, 2, 3].map(q => m[q * 3] + m[q * 3 + 1] + m[q * 3 + 2]);
    }
    function render() {
        selected = selected.filter(y => years.includes(y)).sort();
        const cols = quarterly ? 4 : 12;
        const labs = quarterly ? ['Q1', 'Q2', 'Q3', 'Q4'] : MON;
        const bars = document.getElementById('rev-bars'), lab = document.getElementById('rev-labels');
        bars.style.gridTemplateColumns = lab.style.gridTemplateColumns = 'repeat(' + cols + ', 1fr)';
        const all = selected.map(series);
        const max = Math.max(1, ...all.flat());
        const w = Math.floor(84 / Math.max(1, selected.length));
        bars.innerHTML = Array.from({ length: cols }, (_, i) => '<div style="display:flex;gap:3px;align-items:end;height:100%;justify-content:center;">' +
            selected.map((y, k) => { const v = all[k][i]; const c = COLORS[(years.indexOf(y) + 1) % COLORS.length]; const col = selected.length === 1 ? '#7c3aed' : (k === selected.length - 1 ? '#7c3aed' : (k === 0 ? '#d1d5db' : c));
                return '<div title="' + y + ' ' + labs[i] + ': ' + money(v) + '" style="width:' + w + '%;background:' + col + ';border-radius:5px 5px 0 0;height:' + (v / max * 100) + '%;min-height:' + (v > 0 ? 2 : 0) + 'px;"></div>'; }).join('') + '</div>').join('');
        lab.innerHTML = labs.map(l => '<div style="text-align:center;font-size:0.72rem;color:#9ca3af;font-weight:500;">' + l + '</div>').join('');
        // tabs
        document.getElementById('year-tabs').innerHTML = selected.map((y, k) => '<div class="year-tab ' + (k === selected.length - 1 ? 'yd' : 'yl') + ' active" title="Click to remove" style="cursor:pointer" data-y="' + y + '">' + y + '</div>').join('');
        document.querySelectorAll('#year-tabs .year-tab').forEach(t => t.onclick = () => { if (selected.length > 1) { selected = selected.filter(y => y !== t.dataset.y); render(); } });
        document.getElementById('add-year').style.display = years.length > selected.length ? '' : 'none';
        // totals: latest selected year; change vs previous selected year over the same months
        const latest = selected[selected.length - 1];
        const lm = YEAR_DATA[latest];
        document.getElementById('rev-total').textContent = money(lm.reduce((a, b) => a + b, 0)) + ' (' + latest + ')';
        const ch = document.getElementById('rev-change');
        if (selected.length > 1) {
            const prev = selected[selected.length - 2], pm = YEAR_DATA[prev];
            let a = 0, b = 0; lm.forEach((v, i) => { if (v > 0) { a += v; b += pm[i]; } });
            if (b > 0) { const p = (a - b) / b * 100; ch.innerHTML = '<i class="fas fa-arrow-trend-' + (p >= 0 ? 'up' : 'down') + '"></i> ' + Math.abs(p).toFixed(1) + '% ' + (p >= 0 ? 'higher' : 'lower') + ' than ' + prev + ' (same months)'; ch.style.color = p >= 0 ? '' : '#ef4444'; }
            else ch.textContent = '';
        } else ch.textContent = 'Only one year of data imported';
    }
    document.getElementById('interval').onclick = function () { quarterly = !quarterly; document.getElementById('interval-label').textContent = quarterly ? 'Quarterly' : 'Monthly'; render(); };
    document.getElementById('add-year').onclick = async function () {
        const avail = years.filter(y => !selected.includes(y));
        if (!avail.length) return;
        const opts = {}; avail.forEach(y => opts[y] = y);
        const r = await Swal.fire({ title: 'Add a year to compare', input: 'select', inputOptions: opts, showCancelButton: true, confirmButtonText: 'Add' });
        if (r.isConfirmed && r.value) { selected.push(r.value); if (selected.length > 3) selected.shift(); render(); }
    };
    const ls = document.getElementById('latestSearch');
    if (ls) ls.addEventListener('input', () => { const q = ls.value.toLowerCase(); document.querySelectorAll('#latestBody tr').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none'); });
    if (years.length) render(); else document.getElementById('rev-total').textContent = '$0';
})();
</script>

<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
