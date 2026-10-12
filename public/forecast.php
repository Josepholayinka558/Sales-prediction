<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
require_once dirname(__FILE__, 2) . '/app/helpers/ForecastEngine.php';
auth()->authorize('create_forecasts');
$currentPage = 'forecast'; $pageTitle = 'AI Forecasting'; $pageIcon = 'fas fa-bullseye';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Sales & Analytics'], ['label' => 'Forecast']];
$f = new Forecast();
$past = $f->allWithCreator();
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<div class="fade-in">
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><span><i class="fas fa-lightbulb me-2"></i>Want to know how much <b>one product</b> will sell in a <b>chosen year</b>?</span><a href="<?= url('predict.php') ?>" class="btn btn-sm btn-primary"><i class="fas fa-wand-magic-sparkles me-1"></i>Open Sales Prediction</a></div>
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <h5><i class="fas fa-wand-magic-sparkles text-primary me-2"></i>Forecast Configuration</h5>
            <button class="btn btn-primary" onclick="runForecast()"><i class="fas fa-play me-2"></i>Run Forecast</button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Target Metric</label>
                    <select id="f-target" class="form-select">
                        <option value="revenue">Revenue ($)</option>
                        <option value="profit">Profit ($)</option>
                        <option value="orders">Orders Count</option>
                        <option value="sales">Sales Items Count</option>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Forecast Horizon</label>
                    <select id="f-period" class="form-select">
                        <option value="7_days">7 Days</option><option value="30_days" selected>30 Days</option>
                        <option value="90_days">90 Days</option><option value="365_days">365 Days</option>
                    </select></div>
                <div class="col-md-3"><label class="form-label">MA Period (for MA models)</label>
                    <input type="number" id="f-ma" class="form-control" value="7" min="2" max="90"></div>
                <div class="col-md-3"><label class="form-label">Alpha (ES smoothing factor)</label>
                    <input type="number" id="f-alpha" class="form-control" value="0.3" step="0.05" min="0.05" max="0.95"></div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-8"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-line text-primary me-2"></i>Forecast Comparison: All 4 Models</h5></div><div class="card-body"><div class="chart-container-lg"><canvas id="compChart"></canvas></div></div></div></div>
        <div class="col-xl-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-microchip text-accent me-2"></i>Model Accuracy Comparison</h5></div><div class="card-body"><div id="metricTable" class="py-3"><div class="text-center py-5"><div class="loading-spinner"></div></div></div></div></div></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-6"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-brain text-success me-2"></i>Linear Regression - Full Forecast with Confidence</h5></div><div class="card-body"><div class="chart-container"><canvas id="lrChart"></canvas></div></div></div></div>
        <div class="col-xl-6"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-wave-square text-info me-2"></i>Exponential Smoothing (Holt)</h5></div><div class="card-body"><div class="chart-container"><canvas id="esChart"></canvas></div></div></div></div>
    </div>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-clock-rotate-left text-warning me-2"></i>Forecast History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Name</th><th>Model</th><th>Period</th><th>Target</th><th class="text-end">MAE</th><th class="text-end">RMSE</th><th class="text-end">MAPE%</th><th class="text-end">Accuracy</th><th>Status</th><th>Created</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($past)): ?><tr><td colspan="11" class="text-center py-4 text-muted small">No forecast history yet. Run your first forecast above.</td></tr>
                        <?php else: foreach ($past as $p): ?>
                            <tr>
                                <td><div class="fw-semibold"><?= e($p['name']) ?></div><small class="text-muted">by <?= e($p['username'] ?? 'System') ?></small></td>
                                <td><span class="tag tag-primary"><?= e(ucwords(str_replace('_',' ',$p['model_type']))) ?></span></td>
                                <td><?= e(str_replace('_',' ',$p['forecast_period'])) ?></td>
                                <td><span class="tag tag-accent"><?= e(ucfirst($p['target_type'])) ?></span></td>
                                <td class="text-end"><?= number_format($p['mae'] ?? 0, 2) ?></td>
                                <td class="text-end"><?= number_format($p['rmse'] ?? 0, 2) ?></td>
                                <td class="text-end"><?= number_format($p['mape'] ?? 0, 2) ?>%</td>
                                <td class="text-end fw-bold"><?= number_format($p['accuracy_score'] ?? 0, 1) ?>%</td>
                                <td><span class="tag tag-<?= $p['status']==='completed'?'success':($p['status']==='failed'?'danger':'info') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                                <td class="small text-muted"><?= format_date($p['created_at']) ?></td>
                                <td><button class="btn btn-sm btn-light" onclick="deleteForecast(<?= $p['id'] ?>)"><i class="fas fa-trash text-danger"></i></button></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
window.addEventListener('load', function() {
    const C = chartColors;
    let compInst, lrInst, esInst;
    async function loadComparison() {
        try {
            const target = document.getElementById('f-target').value;
            const period = document.getElementById('f-period').value;
            const res = await ajaxRequest(BASE_URL + '/api/data.php?type=compare_forecasts&period=' + period + '&target=' + target);
            const d = res.data || { labels: [], series: [], metrics: [] };
            if (compInst) compInst.destroy();
            const colors = [C.primary, C.accent, C.success, C.info];
            compInst = new Chart(document.getElementById('compChart'), {
                type: 'line', data: { labels: d.labels, datasets: d.series.map((s,i)=>({
                    label: s.name, data: s.data, borderColor: colors[i%4], tension: 0.35, borderWidth: i===2?4:2,
                    pointRadius:0, pointHoverRadius:6, borderDash: i%2?[6,4]:[]
                })) },
                options: { responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
                    plugins: { legend: { position:'top', align:'end', labels:{usePointStyle:true,padding:20,font:{size:12,weight:600}} },
                        tooltip:{backgroundColor:'#1e1b4b',padding:14,cornerRadius:10,callbacks:{label:c=>' '+c.dataset.label+': '+formatCurrency(c.parsed.y)}} },
                    scales: { x:{ grid:{display:false}, ticks:{maxTicksLimit:15} }, y:{ grid:{color:'#f1f5f9'}, ticks:{callback:v=>'$'+(v/1000).toFixed(0)+'k'} } } }
            });
            const m = d.metrics || [];
            const hasData = m.length && d.labels && d.labels.length;
            document.getElementById('metricTable').innerHTML = hasData ? `
                <table class="table table-sm mb-0">
                    <thead><tr><th>Model</th><th class="text-end">MAE</th><th class="text-end">RMSE</th><th class="text-end">MAPE</th><th class="text-end">Accuracy</th><th class="text-center">Rank</th></tr></thead>
                    <tbody>${m.sort((a,b)=>b.accuracy-a.accuracy).map((x,i)=>`<tr class="${i===0?'fw-bold':''}">
                        <td><div class="d-flex align-items-center gap-2"><span class="d-inline-block rounded-circle" style="width:12px;height:12px;background:${[C.primary,C.accent,C.success,C.info][['moving_average','weighted_moving_average','linear_regression','exponential_smoothing'].indexOf(x.model)]}"></span>${ucwords(x.model.replace(/_/g,' '))}</div></td>
                        <td class="text-end">${formatNumber(x.mae)}</td><td class="text-end">${formatNumber(x.rmse)}</td>
                        <td class="text-end">${formatPercentage(x.mape)}</td>
                        <td class="text-end"><span class="tag ${x.accuracy>=85?'tag-success':(x.accuracy>=65?'tag-warning':'tag-danger')}" style="font-size:0.78rem;">${formatPercentage(x.accuracy,1)}</span></td>
                        <td class="text-center">${i===0?'<i class="fas fa-trophy text-accent"></i>':'#'+(i+1)}</td>
                    </tr>`).join('')}</tbody>
                </table>
                ${m.length?`<div class="mt-3 p-3 rounded-3 bg-light"><i class="fas fa-circle-info text-info me-2"></i>Based on accuracy, <strong class="text-primary">${ucwords(m.slice().sort((a,b)=>b.accuracy-a.accuracy)[0].model.replace(/_/g,' '))}</strong> is recommended for this dataset.</div>`:''}` :
                `<div class="text-center py-5 text-muted small"><i class="fas fa-database fa-2xl d-block mb-2 opacity-25"></i>No data available. Import sales data first, then run the forecast.</div>`;
        } catch(e) {
            console.error('loadComparison error:', e);
            const el = document.getElementById('metricTable');
            if (el) el.innerHTML = `<div class="alert alert-danger small mb-0"><i class="fas fa-triangle-exclamation me-2"></i>Failed to load comparison: ${String(e)}</div>`;
            throw e;
        }
    }
    function ucwords(s) { return s.replace(/\b\w/g, c => c.toUpperCase()); }

    async function loadIndividual(save = false) {
        try {
            const target = document.getElementById('f-target').value;
            const period = document.getElementById('f-period').value;
            const ma = +document.getElementById('f-ma').value;
            const alpha = +document.getElementById('f-alpha').value;
            const lr = await ajaxRequest(BASE_URL + '/api/data.php?type=run_forecast', 'POST', { model:'linear_regression', period, target, ma_period:ma, alpha, save: save ? 1 : 0, '<?= CSRF_TOKEN_NAME ?>': CSRF_TOKEN });
            if (!lr || !lr.success) throw new Error(lr?.error || 'Linear regression forecast failed');
            drawLR(lr);
            const es = await ajaxRequest(BASE_URL + '/api/data.php?type=run_forecast', 'POST', { model:'exponential_smoothing', period, target, ma_period:ma, alpha, save: save ? 1 : 0, '<?= CSRF_TOKEN_NAME ?>': CSRF_TOKEN });
            if (!es || !es.success) throw new Error(es?.error || 'Exponential smoothing forecast failed');
            drawES(es);
        } catch(e) {
            console.error('loadIndividual error:', e);
            throw e;
        }
    }
    function drawLR(res) {
        if (!res.data || !res.data.forecast || !res.data.forecast.length) return;
        const labels = res.data.forecast.map(f => formatDate(f.date));
        const pred = res.data.forecast.map(f => +f.value);
        const up = res.data.forecast.map(f => +f.upper);
        const lo = res.data.forecast.map(f => +f.lower);
        const ctx = document.getElementById('lrChart');
        if (lrInst) lrInst.destroy();
        lrInst = new Chart(ctx, { type:'line', data:{ labels, datasets:[
            { label:'Forecast', data:pred, borderColor:C.primary, backgroundColor:C.primary+'22', fill:true, tension:0.4, borderWidth:3, pointRadius:0 },
            { label:'Upper 95%', data:up, borderColor:C.primary+'55', backgroundColor:C.primary+'11', fill:'-1', tension:0.4, borderWidth:1, pointRadius:0, borderDash:[4,4] },
            { label:'Lower 95%', data:lo, borderColor:'transparent', backgroundColor:'transparent', tension:0.4, borderWidth:0, pointRadius:0 }
        ] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#1e1b4b',padding:12,cornerRadius:10,callbacks:{label:c=>{ if(c.dataset.label==='Lower 95%') return false; return ' '+c.dataset.label+': '+formatCurrency(c.parsed.y); }}} },
            scales:{x:{grid:{display:false},ticks:{maxTicksLimit:10,maxRotation:45}}, y:{grid:{color:'#f1f5f9'}, ticks:{callback:v=>'$'+(v/1000).toFixed(0)+'k'}}} }});
    }
    function drawES(res) {
        if (!res.data || !res.data.forecast || !res.data.forecast.length) return;
        const labels = res.data.forecast.map(f => formatDate(f.date));
        const pred = res.data.forecast.map(f => +f.value);
        const up = res.data.forecast.map(f => +f.upper);
        const lo = res.data.forecast.map(f => +f.lower);
        if (esInst) esInst.destroy();
        esInst = new Chart(document.getElementById('esChart'), { type:'line', data:{ labels, datasets:[
            { label:'Holt Forecast', data:pred, borderColor:C.accent, backgroundColor:C.accent+'22', fill:true, tension:0.4, borderWidth:3, pointRadius:0 },
            { label:'Upper 95%', data:up, borderColor:C.accent+'55', backgroundColor:C.accent+'11', fill:'-1', tension:0.4, borderWidth:1, pointRadius:0, borderDash:[4,4] },
            { label:'Lower 95%', data:lo, borderColor:'transparent', backgroundColor:'transparent', tension:0.4, borderWidth:0, pointRadius:0 }
        ] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#1e1b4b',padding:12,cornerRadius:10,callbacks:{label:c=>{ if(c.dataset.label==='Lower 95%') return false; return ' '+c.dataset.label+': '+formatCurrency(c.parsed.y); }}} },
            scales:{x:{grid:{display:false},ticks:{maxTicksLimit:10,maxRotation:45}}, y:{grid:{color:'#f1f5f9'}, ticks:{callback:v=>'$'+(v/1000).toFixed(0)+'k'}}} }});
    }
    async function runForecast() {
        loadingSwal('Running all 4 forecasting models...');
        try {
            await loadComparison();
            await loadIndividual(true);
            Swal.close();
            toast('Forecast complete! Results saved to history.', 'success');
            setTimeout(() => location.reload(), 1500);
        } catch(err) {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Forecast Failed',
                html: String(err) + '<br><br><small class="text-muted">Tip: Make sure you have imported sales data in the Datasets page first using the 1-Click Server Import button.</small>',
                footer: '<a href="' + BASE_URL + '/dataset.php" class="text-link" style="font-weight:600;"><i class="fas fa-database me-1"></i>Go to Datasets page</a>'
            });
        }
    }
    function deleteForecast(id) {
        deleteItem('forecast', id, 'forecast #' + id, () => location.reload());
    }

    window.runForecast = runForecast;
    window.deleteForecast = deleteForecast;

    loadComparison().catch(e => console.warn('Initial comparison load:', e));
    loadIndividual(false).catch(e => console.warn('Initial individual load:', e));
});
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
