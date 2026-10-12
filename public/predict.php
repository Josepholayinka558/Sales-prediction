<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
require_once dirname(__FILE__, 2) . '/app/helpers/SalesPredictor.php';
auth()->authorize('view_forecasts');
$currentPage = 'predict'; $pageTitle = 'Sales Prediction'; $pageIcon = 'fas fa-wand-magic-sparkles';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Sales & Analytics'], ['label' => 'Sales Prediction']];
$opts = (new SalesPredictor())->options();
$preProduct = (int)($_GET['product_id'] ?? 0);
$preYear = (int)($_GET['year'] ?? 0);
$defaultYear = $opts['has_data'] ? ((int)substr($opts['range']['max'], 5, 2) === 12 ? $opts['data_last_year'] + 1 : $opts['data_last_year']) : (int)date('Y');
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<style>
    .pred-status { display:inline-block; padding:2px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
    .pred-status.actual { background:#dcfce7; color:#166534; }
    .pred-status.predicted { background:#ede9fe; color:#5b21b6; }
    .pred-status.none { background:#f1f5f9; color:#64748b; }
    .pred-hero { font-size:2.1rem; font-weight:800; line-height:1.1; }
    .pred-note { border-left:4px solid #f59e0b; background:#fffbeb; border-radius:8px; padding:10px 14px; font-size:.85rem; color:#78350f; }
    .clickable-row { cursor:pointer; }
    .clickable-row:hover { background:#f5f3ff; }
</style>
<div class="fade-in">
<?php if (!$opts['has_data']): ?>
    <div class="card"><div class="card-body text-center py-5">
        <i class="fas fa-database fa-3x text-muted mb-3 opacity-50"></i>
        <h5 class="fw-bold">No sales data yet</h5>
        <p class="text-muted">Import your sales history first, then come back to predict future sales.</p>
        <a href="<?= url('dataset.php') ?>" class="btn btn-primary"><i class="fas fa-file-import me-2"></i>Go to Datasets</a>
    </div></div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5><i class="fas fa-sliders text-primary me-2"></i>What do you want to predict?</h5>
            <span class="small text-muted"><i class="fas fa-database me-1"></i>Your data: <?= e(format_date($opts['range']['min'])) ?> &rarr; <?= e(format_date($opts['range']['max'])) ?></span>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Year</label>
                    <select id="p-year" class="form-select">
                        <?php foreach ($opts['years'] as $y): ?>
                            <option value="<?= $y ?>"><?= $y ?><?= $y <= $opts['data_last_year'] ? ' (your data)' : ' (future)' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Product</label>
                    <select id="p-scope" class="form-select">
                        <option value="all">All products (store total)</option>
                        <optgroup label="By category">
                            <?php foreach ($opts['categories'] as $c): ?><option value="c:<?= (int)$c['id'] ?>"><?= e($c['name']) ?> (whole category)</option><?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Single product">
                            <?php foreach ($opts['products'] as $p): ?><option value="p:<?= (int)$p['id'] ?>" <?= $preProduct === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?><?= $p['category'] ? ' — ' . e($p['category']) : '' ?></option><?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Show</label>
                    <select id="p-metric" class="form-select"><option value="revenue">Sales ($)</option><option value="units">Units sold</option></select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary" id="p-run" type="button"><i class="fas fa-wand-magic-sparkles me-2"></i>Predict</button>
                </div>
            </div>
        </div>
    </div>

    <div id="p-error" class="alert alert-warning d-none"></div>
    <div id="p-result" class="d-none">
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6"><div class="card h-100"><div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase" id="k-title">Predicted sales</div>
                <div class="pred-hero text-primary mt-1" id="k-total">-</div>
                <div class="small text-muted mt-2" id="k-range">-</div>
            </div></div></div>
            <div class="col-xl-2 col-md-6"><div class="card h-100"><div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Units</div>
                <div class="pred-hero mt-1" id="k-units" style="font-size:1.7rem">-</div>
                <div class="small text-muted mt-2">for the year</div>
            </div></div></div>
            <div class="col-xl-3 col-md-6"><div class="card h-100"><div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Avg per month</div>
                <div class="pred-hero mt-1" id="k-avg" style="font-size:1.7rem">-</div>
                <div class="small text-muted mt-2" id="k-mix">-</div>
            </div></div></div>
            <div class="col-xl-3 col-md-6"><div class="card h-100"><div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Back-test accuracy</div>
                <div class="pred-hero mt-1" id="k-acc" style="font-size:1.7rem">-</div>
                <div class="small text-muted mt-2" id="k-method">-</div>
            </div></div></div>
        </div>

        <div id="p-notes" class="mb-4"></div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8"><div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-chart-column text-primary me-2"></i><span id="c-title">Month by month</span></h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light" onclick="exportPred('csv')"><i class="fas fa-file-csv text-success me-1"></i>CSV</button>
                        <button class="btn btn-sm btn-light" onclick="exportPred('xlsx')"><i class="fas fa-file-excel text-info me-1"></i>Excel</button>
                    </div>
                </div>
                <div class="card-body"><div class="chart-container-lg"><canvas id="predChart"></canvas></div></div>
            </div></div>
            <div class="col-xl-4"><div class="card h-100">
                <div class="card-header"><h5><i class="fas fa-table text-accent me-2"></i>Monthly figures</h5></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Month</th><th></th><th class="text-end">Units</th><th class="text-end">Sales</th></tr></thead>
                    <tbody id="p-table"></tbody>
                </table></div></div>
            </div></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5><i class="fas fa-ranking-star text-success me-2"></i>All products &mdash; <span id="all-year"></span> predictions</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-light" onclick="exportAll('csv')"><i class="fas fa-file-csv text-success me-1"></i>CSV</button>
                <button class="btn btn-sm btn-light" onclick="exportAll('xlsx')"><i class="fas fa-file-excel text-info me-1"></i>Excel</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table align-middle mb-0" id="allTable">
                <thead><tr><th>#</th><th>Product</th><th>Category</th><th class="text-end">Predicted units</th><th class="text-end">Predicted sales</th><th>Trend</th></tr></thead>
                <tbody id="all-body"><tr><td colspan="6" class="text-center py-4 text-muted small">Loading...</td></tr></tbody>
            </table></div>
        </div>
        <div class="card-footer small text-muted">Click a product to see its month-by-month prediction. Each product is modelled on its own, so the rows may differ slightly from the store total.</div>
    </div>
<?php endif; ?>
</div>
<?php if ($opts['has_data']): ?>
<script>
window.addEventListener('load', function () {
    const C = chartColors;
    const PRE_YEAR = <?= (int)$preYear ?>;
    const LAST_DATA_YEAR = <?= (int)$opts['data_last_year'] ?>;
    let chart = null, current = null, allLoadedYear = null;
    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const fmtMoney = n => '$' + Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });

    // default year: the first year that still has months to predict
    $('p-year').value = PRE_YEAR || <?= (int)$defaultYear ?>;
    if (!$('p-year').value) $('p-year').selectedIndex = 0;

    function scopeParams() {
        const v = $('p-scope').value;
        if (v.startsWith('p:')) return '&product_id=' + v.slice(2);
        if (v.startsWith('c:')) return '&category_id=' + v.slice(2);
        return '';
    }

    async function run() {
        const year = $('p-year').value;
        $('p-error').classList.add('d-none');
        $('p-run').disabled = true;
        $('p-run').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Predicting...';
        try {
            const res = await ajaxRequest(BASE_URL + '/api/predict.php?action=predict&year=' + year + scopeParams());
            if (!res.success) { showError(res.error); return; }
            current = res.data; render(res.data);
            loadAll(year);
        } catch (err) {
            showError('Could not run the prediction: ' + err.message);
        } finally {
            $('p-run').disabled = false;
            $('p-run').innerHTML = '<i class="fas fa-wand-magic-sparkles me-2"></i>Predict';
        }
    }

    function showError(msg) {
        $('p-result').classList.add('d-none');
        const el = $('p-error'); el.textContent = msg; el.classList.remove('d-none');
    }

    function render(d) {
        $('p-result').classList.remove('d-none');
        const s = d.summary, m = d.model, metric = $('p-metric').value;
        const fullyActual = s.predicted_months === 0;
        $('k-title').textContent = (fullyActual ? 'Actual sales ' : 'Predicted sales ') + d.year + ' — ' + d.label;
        $('k-total').textContent = fmtMoney(s.total_revenue);
        $('k-range').innerHTML = fullyActual ? 'Based on your real data.' : 'Likely range: <b>' + fmtMoney(s.low_revenue) + '</b> to <b>' + fmtMoney(s.high_revenue) + '</b> (80% confidence)';
        $('k-units').textContent = Number(s.total_units).toLocaleString();
        $('k-avg').textContent = fmtMoney(s.avg_monthly_revenue);
        $('k-mix').textContent = s.actual_months + ' month(s) actual · ' + s.predicted_months + ' predicted';
        if (m.backtest) {
            $('k-acc').textContent = m.backtest.accuracy_pct + '%';
            $('k-method').textContent = 'Tested on the last ' + m.backtest.months_tested + ' months of your data · ' + m.method;
        } else {
            $('k-acc').textContent = 'n/a';
            $('k-method').textContent = 'Not enough history to test · ' + m.method;
        }
        $('p-notes').innerHTML = (d.notes || []).map(n => '<div class="pred-note mb-2"><i class="fas fa-circle-info me-2"></i>' + esc(n) + '</div>').join('');
        $('c-title').textContent = d.label + ' — ' + d.year + ' by month';

        // table
        $('p-table').innerHTML = d.labels.map((l, i) => {
            const st = d.status[i];
            const u = st === 'actual' ? d.actual_units[i] : d.pred_units[i];
            const r = st === 'actual' ? d.actual_revenue[i] : d.pred_revenue[i];
            const label = st === 'actual' ? 'Actual' : (st === 'predicted' ? 'Predicted' : 'No data');
            return '<tr><td class="fw-semibold">' + l + '</td><td><span class="pred-status ' + st + '">' + label + '</span></td><td class="text-end">' + (u == null ? '-' : Number(u).toLocaleString()) + '</td><td class="text-end fw-semibold">' + (r == null ? '-' : fmtMoney(r)) + '</td></tr>';
        }).join('') + '<tr class="table-light fw-bold"><td colspan="2">Total ' + d.year + '</td><td class="text-end">' + Number(s.total_units).toLocaleString() + '</td><td class="text-end">' + fmtMoney(s.total_revenue) + '</td></tr>';

        // chart
        const isRev = metric === 'revenue';
        const actual = isRev ? d.actual_revenue : d.actual_units;
        const pred = isRev ? d.pred_revenue : d.pred_units;
        const lo = isRev ? d.lower_revenue : d.lower_units;
        const hi = isRev ? d.upper_revenue : d.upper_units;
        if (chart) chart.destroy();
        chart = new Chart($('predChart'), {
            type: 'bar',
            data: { labels: d.labels, datasets: [
                { label: 'Actual', data: actual, backgroundColor: C.success, borderRadius: 8, barPercentage: .7 },
                { label: 'Predicted', data: pred, backgroundColor: C.primary + 'bb', borderColor: C.primary, borderWidth: 1, borderRadius: 8, barPercentage: .7 },
                { type: 'line', label: 'Likely high', data: hi, borderColor: C.accent, borderDash: [5, 4], pointRadius: 0, borderWidth: 2, fill: false },
                { type: 'line', label: 'Likely low', data: lo, borderColor: C.danger, borderDash: [5, 4], pointRadius: 0, borderWidth: 2, fill: false }
            ] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', align: 'end', labels: { usePointStyle: true, padding: 16 } },
                    tooltip: { backgroundColor: '#1e1b4b', padding: 12, cornerRadius: 10, callbacks: { label: c => c.parsed.y == null ? null : ' ' + c.dataset.label + ': ' + (isRev ? formatCurrency(c.parsed.y) : Number(c.parsed.y).toLocaleString() + ' units') } } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: v => isRev ? '$' + (v >= 1e6 ? (v / 1e6).toFixed(1) + 'M' : (v / 1000).toFixed(0) + 'k') : Number(v).toLocaleString() } } } }
        });
    }

    async function loadAll(year) {
        $('all-year').textContent = year;
        if (allLoadedYear === year) return;
        $('all-body').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted small">Loading...</td></tr>';
        try {
            const res = await ajaxRequest(BASE_URL + '/api/predict.php?action=all_products&year=' + year);
            if (!res.success) { $('all-body').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted small">' + esc(res.error) + '</td></tr>'; return; }
            allLoadedYear = year;
            const trendTag = t => t === 'rising' ? '<span class="tag tag-success"><i class="fas fa-arrow-trend-up me-1"></i>Rising</span>' : (t === 'falling' ? '<span class="tag tag-danger"><i class="fas fa-arrow-trend-down me-1"></i>Falling</span>' : '<span class="tag tag-info">Steady</span>');
            $('all-body').innerHTML = res.data.rows.map((r, i) =>
                '<tr class="clickable-row" data-pid="' + r.product_id + '"><td class="text-muted">' + (i + 1) + '</td><td class="fw-semibold">' + esc(r.product) + '</td><td>' + (r.category ? '<span class="tag tag-primary">' + esc(r.category) + '</span>' : '-') +
                '</td><td class="text-end">' + Number(r.units).toLocaleString() + '</td><td class="text-end fw-bold">' + fmtMoney(r.revenue) + '</td><td>' + trendTag(r.trend) + '</td></tr>').join('');
            document.querySelectorAll('#all-body tr[data-pid]').forEach(tr => tr.addEventListener('click', () => {
                $('p-scope').value = 'p:' + tr.dataset.pid; run(); window.scrollTo({ top: 0, behavior: 'smooth' });
            }));
        } catch (err) {
            $('all-body').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger small">Could not load: ' + esc(err.message) + '</td></tr>';
        }
    }

    window.exportPred = function (fmt) {
        if (!current) return;
        window.location.href = BASE_URL + '/api/export.php?type=prediction&format=' + fmt + '&year=' + current.year + scopeParams();
    };
    window.exportAll = function (fmt) {
        window.location.href = BASE_URL + '/api/export.php?type=prediction_all&format=' + fmt + '&year=' + $('p-year').value;
    };

    $('p-run').addEventListener('click', run);
    $('p-year').addEventListener('change', run);
    $('p-scope').addEventListener('change', run);
    $('p-metric').addEventListener('change', () => { if (current) render(current); });
    run();
});
</script>
<?php endif; ?>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
