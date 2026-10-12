<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('view_dashboard');
$currentPage = 'analytics'; $pageTitle = 'Advanced Analytics'; $pageIcon = 'fas fa-chart-pie';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Sales & Analytics'], ['label' => 'Analytics']];
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<div class="fade-in">
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <h5><i class="fas fa-sliders text-primary me-2"></i>Analytics Configuration</h5>
            <div class="d-flex gap-2 flex-wrap">
                <select id="a-range" class="form-select form-select-sm" style="width:auto;">
                    <option value="6">Last 6 months</option><option value="12" selected>Last 12 months</option><option value="24">Last 24 months</option>
                </select>
                <button class="btn btn-sm btn-primary" onclick="loadAll()"><i class="fas fa-rotate me-1"></i>Refresh Charts</button>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-8"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-column text-primary me-2"></i>Monthly Comparison: Current vs Previous Year</h5></div><div class="card-body"><div class="chart-container-lg"><canvas id="yCompare"></canvas></div></div></div></div>
        <div class="col-xl-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-pie text-accent me-2"></i>Sales by Segment</h5></div><div class="card-body"><div class="chart-container"><canvas id="segChart"></canvas></div></div></div></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-6"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-area text-success me-2"></i>Profit Analysis (Revenue vs Cost vs Profit)</h5></div><div class="card-body"><div class="chart-container"><canvas id="profitChart"></canvas></div></div></div></div>
        <div class="col-xl-6"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-ship text-info me-2"></i>Sales by Ship Mode</h5></div><div class="card-body"><div class="chart-container"><div id="shipChart"></div></div></div></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-7"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-th text-warning me-2"></i>Top Sub-Categories</h5></div><div class="card-body"><div class="chart-container"><canvas id="subChart"></canvas></div></div></div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-map-location-dot text-danger me-2"></i>Regional Performance Heatmap</h5></div><div class="card-body"><div id="heatmap" class="chart-container p-2"></div></div></div></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-6"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-line text-primary me-2"></i>Yearly Trend</h5></div><div class="card-body"><div class="chart-container"><canvas id="yrChart"></canvas></div></div></div></div>
        <div class="col-xl-6"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-users text-accent me-2"></i>Customer Segments Distribution</h5></div><div class="card-body"><div class="chart-container"><div id="segDonut"></div></div></div></div>
    </div>
</div>
<script>
const C = chartColors;
const _charts = {};
function mk(id, cfg) { if (_charts[id]) _charts[id].destroy(); _charts[id] = new Chart(document.getElementById(id), cfg); return _charts[id]; }
function noData(id, msg) { const el = document.getElementById(id); el.innerHTML = '<div class="text-center text-muted small py-5"><i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>' + msg + '</div>'; }
function mkApex(id, cfg) { const el = document.getElementById(id); if (_charts[id]) { try { _charts[id].destroy(); } catch (e) {} } el.innerHTML = ''; _charts[id] = new ApexCharts(el, cfg); _charts[id].render(); }
function mg(id, c) { const x = document.getElementById(id).getContext('2d'); return chartGradients(x, c+'cc', c+'08'); }
async function loadAll() {
    const months = +document.getElementById('a-range').value;
    const mc = await ajaxRequest(BASE_URL+'/api/data.php?type=monthly_comparison');
    if (mc.data) {
        mk('yCompare', {
            type: 'bar',
            data: { labels: mc.data.labels, datasets: [
                { label: 'Previous Year (' + mc.data.previous_label + ')', data: mc.data.previous_year, backgroundColor: C.info, borderRadius: 10, barPercentage: 0.7 },
                { label: 'Latest Year (' + mc.data.current_label + ')', data: mc.data.current_year, backgroundColor: C.primary, borderRadius: 10, barPercentage: 0.7 }
            ]},
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position:'top', align:'end', labels:{usePointStyle:true, padding:15} }, tooltip: { backgroundColor:'#1e1b4b', padding:12, cornerRadius:10, callbacks: { label: c => ' ' + c.dataset.label + ': ' + formatCurrency(c.parsed.y) } } },
                scales: { x: { grid: { display: false } }, y: { grid: { color: '#f1f5f9' }, ticks: { callback: v => '$' + (v/1000)+'k' } } } }
        });
    }

    const cc = await ajaxRequest(BASE_URL+'/api/data.php?type=categories');
    const cats = cc.data || [];
    const cus = await ajaxRequest(BASE_URL+'/api/data.php?type=top_customers&limit=100');
    const segs = {}; (cus.data||[]).forEach(c => { const seg = c.region || 'Unknown'; segs[seg] = (segs[seg]||0) + (+c.revenue); });
    const segLabels = Object.keys(segs);
    mk('segChart', {
        type: 'polarArea',
        data: { labels: segLabels.length ? segLabels : cats.map(c => c.category), datasets: [{ data: segLabels.length ? Object.values(segs) : cats.map(c => c.revenue), backgroundColor: [C.primary,C.accent,C.success,C.info,C.warning,C.teal,C.pink,C.orange].map(c=>c+'cc'), borderWidth: 0 }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{position:'bottom', labels:{padding:15,usePointStyle:true}}, tooltip:{ backgroundColor:'#1e1b4b', padding:12, cornerRadius:10, callbacks:{label:c=>' '+c.label+': '+formatCurrency(c.parsed.r)} } }, scales:{ r:{ ticks:{display:false}, grid:{color:'#e2e8f0'} } } }
    });

    const pa = await ajaxRequest(BASE_URL+'/api/data.php?type=profit_analysis&months='+months);
    const paD = pa.data || [];
    mk('profitChart', {
        type: 'line',
        data: { labels: paD.map(p=>p.label), datasets: [
            { label:'Revenue', data: paD.map(p=>p.revenue), borderColor: C.primary, backgroundColor: mg('profitChart', C.primary), fill:true, tension:0.4, borderWidth:3, pointRadius:0 },
            { label:'Cost', data: paD.map(p=>p.cost), borderColor: C.danger, backgroundColor: mg('profitChart', C.danger), fill:true, tension:0.4, borderWidth:3, pointRadius:0 },
            { label:'Profit', data: paD.map(p=>p.profit), borderColor: C.success, backgroundColor: mg('profitChart', C.success), fill:true, tension:0.4, borderWidth:3, pointRadius:0 }
        ]},
        options: { responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false}, plugins:{legend:{position:'top',align:'end',labels:{usePointStyle:true,padding:15}}, tooltip:{backgroundColor:'#1e1b4b',padding:12,cornerRadius:10,callbacks:{label:c=>' '+c.dataset.label+': '+formatCurrency(c.parsed.y)}}},
            scales:{x:{grid:{display:false}}, y:{grid:{color:'#f1f5f9'}, ticks:{callback:v=>'$'+(v/1000)+'k'}}} }
    });

    const sm = await ajaxRequest(BASE_URL+'/api/data.php?type=ship_mode');
    const smD = sm.data || [];
    if (!smD.length) noData('shipChart', 'No ship-mode information in your imported data.'); else
    mkApex('shipChart', {
        series: smD.map(s=>({name:s.ship_mode||'Other', data:[+s.revenue]})),
        chart: { type:'bar', height: 280 }, plotOptions:{bar:{horizontal:true,barHeight:'50%'}}, colors:[C.primary,C.accent,C.success,C.info,C.warning,C.teal],
        dataLabels:{enabled:false}, legend:{position:'bottom'},
        xaxis:{ labels:{ formatter: v => '$' + (v/1000).toFixed(0) + 'k' } },
        tooltip:{ y:{ formatter: v => formatCurrency(v) } }
    });

    const sbc = await ajaxRequest(BASE_URL+'/api/data.php?type=sub_categories');
    const sbcD = (sbc.data || []).slice(0,15);
    mk('subChart', {
        type:'bar', data: { labels: sbcD.map(s=>s.sub_category||'Unknown'), datasets: [{ label:'Revenue', data: sbcD.map(s=>+s.revenue), backgroundColor: sbcD.map((_,i)=>[C.primary,C.accent,C.success,C.info,C.warning,C.teal,C.pink,C.orange,C.cyan,C.purple2,C.lime][i%11]), borderRadius:8, barPercentage:0.7 }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}, tooltip:{backgroundColor:'#1e1b4b',padding:12,cornerRadius:10}},
            scales: { x:{ grid:{display:false}, ticks:{maxRotation:45} }, y:{ grid:{color:'#f1f5f9'} } } }
    });

    const rq = await ajaxRequest(BASE_URL+'/api/data.php?type=region_quarters');
    const rqD = rq.data || [];
    if (!rqD.length) noData('heatmap', 'No regional sales data yet.'); else {
        const quarters = [...new Set(rqD.map(d => d.quarter))].sort();
        const regionsL = [...new Set(rqD.map(d => d.region))];
        const maxV = Math.max(...rqD.map(d => +d.revenue), 1);
        mkApex('heatmap', {
            series: regionsL.map(rg => ({ name: rg, data: quarters.map(q => { const f = rqD.find(d => d.region === rg && d.quarter === q); return { x: q, y: Math.round((f ? +f.revenue : 0) / 1000) }; }) })),
            chart: { type: 'heatmap', height: 280, toolbar: { show: false } }, dataLabels: { enabled: false },
            plotOptions: { heatmap: { radius: 8, colorScale: { ranges: [
                { from: 0, to: Math.round(maxV / 3000), name: 'Low', color: '#c4b5fd' },
                { from: Math.round(maxV / 3000) + 1, to: Math.round(maxV * 2 / 3000), name: 'Medium', color: '#8b5cf6' },
                { from: Math.round(maxV * 2 / 3000) + 1, to: Math.ceil(maxV / 1000) + 1, name: 'High', color: '#d4af37' }] } } },
            tooltip: { y: { formatter: v => '$' + (v || 0).toLocaleString() + 'k' } }
        });
    }

    const yc = await ajaxRequest(BASE_URL+'/api/data.php?type=yearly&years=5');
    const ycD = yc.data || [];
    mk('yrChart', {
        type:'bar', data: { labels: ycD.map(y=>y.year_key), datasets: [
            { type:'bar', label:'Revenue', data: ycD.map(y=>+y.revenue), backgroundColor: mg('yrChart', C.primary), borderRadius: 12, barPercentage: 0.55, order:2 },
            { type:'line', label:'Profit', data: ycD.map(y=>+y.profit), borderColor: C.accent, backgroundColor: C.accent, tension:0.4, borderWidth: 3, pointRadius: 6, pointBackgroundColor: '#fff', pointBorderColor: C.accent, pointBorderWidth: 3, yAxisID: 'y1', order:1 }
        ]},
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top',align:'end',labels:{usePointStyle:true,padding:15}}, tooltip:{backgroundColor:'#1e1b4b',padding:12,cornerRadius:10,callbacks:{label:c=>' '+c.dataset.label+': '+formatCurrency(c.parsed.y)}}},
            scales: { y:{ position:'left', grid:{color:'#f1f5f9'}, ticks:{callback:v=>'$'+(v/1000000).toFixed(1)+'M'} },
                     y1:{ display:true, position:'right', grid:{drawOnChartArea:false}, ticks:{callback:v=>'$'+(v/1000).toFixed(0)+'k'} },
                     x:{ grid:{display:false} } } }
    });

    const seg = await ajaxRequest(BASE_URL+'/api/data.php?type=segments');
    const sgD = seg.data || [];
    if (!sgD.length) { noData('segDonut', 'No customer segments in your data.'); return; }
    mkApex('segDonut', {
        series: sgD.map(s=>+s.count), labels: sgD.map(s=>s.segment), chart: { type:'donut', height:280 },
        colors: [C.primary, C.accent, C.success, C.info, C.warning, C.teal],
        plotOptions:{ pie:{ donut:{ size:'70%', labels:{ show:true, total:{ show:true, label:'Customers', formatter:()=>sgD.reduce((a,b)=>a+(+b.count),0).toLocaleString() } } } } },
        legend:{position:'bottom', labels:{useSeriesColors:true}}, dataLabels:{enabled:false}, stroke:{width:0}
    });
}
document.getElementById('a-range').addEventListener('change', loadAll);
loadAll();
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
