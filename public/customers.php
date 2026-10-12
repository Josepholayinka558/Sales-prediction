<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('view_customers');
$currentPage = 'customers'; $pageTitle = 'Customers'; $pageIcon = 'fas fa-users';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Sales & Analytics'], ['label' => 'Customers']];
$cm = new Customer();
$totalCustomers = $cm->totalCustomers();
$segments = $cm->bySegment();
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<div class="fade-in">
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="kpi-card customers"><div class="kpi-icon"><i class="fas fa-users"></i></div><div class="kpi-value"><?= format_number($totalCustomers) ?></div><div class="kpi-label">Total Customers</div></div></div>
        <div class="col-md-3"><div class="kpi-card revenue"><div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div><div class="kpi-value"><?= format_currency((new Sale())->byCustomer(1)[0]['revenue'] ?? 0) ?></div><div class="kpi-label">Top Customer Revenue</div></div></div>
        <div class="col-md-3"><div class="kpi-card orders"><div class="kpi-icon"><i class="fas fa-shapes"></i></div><div class="kpi-value"><?= count($segments) ?></div><div class="kpi-label">Segments</div></div></div>
        <div class="col-md-3"><div class="kpi-card aov"><div class="kpi-icon"><i class="fas fa-chart-pie"></i></div><div class="kpi-value"><?= format_number(count((new Region())->all())) ?></div><div class="kpi-label">Regions Covered</div></div></div>
    </div>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5><i class="fas fa-user-group text-success me-2"></i>Customer Database</h5>
            <div class="d-flex gap-2">
                <select id="f-seg" class="form-select form-select-sm" style="width:auto;"><option value="">All Segments</option><?php foreach($segments as $s): ?><option><?= e($s['segment']) ?></option><?php endforeach; ?></select>
                <select id="f-reg" class="form-select form-select-sm" style="width:auto;"><option value="">All Regions</option><?php foreach((new Region())->all() as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table datatable mb-0" id="cTable"><thead><tr><th>ID</th><th>Code</th><th>Name</th><th>Segment</th><th>Region</th><th class="text-end">Orders</th><th class="text-end">Total Spent</th><th></th></tr></thead></table></div>
        </div>
    </div>
</div>
<script>
let cdt;
function loadC() {
    if (cdt) cdt.destroy();
    cdt = $('#cTable').DataTable({ processing: true, serverSide: true, pageLength: 25, lengthMenu: [10, 25, 50, 100],
        ajax: { url: BASE_URL + '/api/table.php?type=customers', data: d => { d.segment = document.getElementById('f-seg').value; d.region = document.getElementById('f-reg').value; }, dataSrc: 'data' },
        columns: [
            { data: 'id', width:'60px' },
            { data: 'customer_code', render: v => `<span class="fw-semibold tag tag-info">${e(v)}</span>` },
            { data: 'name', render: v => { const i = (v||'?').split(' ').map(p => p[0]).join('').substring(0,2).toUpperCase(); return `<div class="d-flex align-items-center gap-2"><div class="rounded-3 d-flex align-items-center justify-content-center fw-bold text-white" style="width:32px;height:32px;background:linear-gradient(135deg,#6f42c1,#d4af37);font-size:0.8rem;">${i}</div><div class="fw-semibold">${e(v)}</div></div>`; } },
            { data: 'segment', render: v => v ? `<span class="tag tag-accent">${e(v)}</span>` : '-' },
            { data: 'region_name', render: v => v ? `<span class="tag tag-info">${e(v)}</span>` : '-' },
            { data: 'order_count', className:'text-end fw-semibold', render: v => formatNumber(v) },
            { data: 'total_spent', className:'text-end fw-bold', render: v => `<span class="text-primary">${formatCurrency(v)}</span>` },
            { data: 'id', orderable:false, width:'80px', className:'text-end', render: id => `<div class="btn-group"><button class="btn btn-sm btn-light" onclick="viewC(${id})"><i class="fas fa-eye"></i></button><button class="btn btn-sm btn-light text-danger" onclick="delC(${id})"><i class="fas fa-trash"></i></button></div>` }
        ], order: [[6,'desc']]
    });
}
loadC();
document.getElementById('f-seg').addEventListener('change', loadC);
document.getElementById('f-reg').addEventListener('change', loadC);
function viewC(id) { viewItem('customer', id); }
function delC(id) { deleteItem('customer', id, 'customer #' + id, () => cdt.ajax.reload(null, false), 'Their sales stay in your data but will no longer be linked to this customer.'); }
function e(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
