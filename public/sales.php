<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('view_sales');
$currentPage = 'sales'; $pageTitle = 'Sales Management'; $pageIcon = 'fas fa-cash-register';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Sales & Analytics'], ['label' => 'Sales']];
$canEdit = auth()->hasPermission('manage_sales');
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<div class="fade-in">
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="kpi-card revenue"><div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div><div class="kpi-value" id="kpi-rev"><?= format_currency((new Sale())->totalRevenue()) ?></div><div class="kpi-label">Total Revenue</div></div></div>
        <div class="col-md-3"><div class="kpi-card profit"><div class="kpi-icon"><i class="fas fa-chart-line"></i></div><div class="kpi-value" id="kpi-prof"><?= format_currency((new Sale())->totalProfit()) ?></div><div class="kpi-label">Total Profit</div></div></div>
        <div class="col-md-3"><div class="kpi-card sales"><div class="kpi-icon"><i class="fas fa-cash-register"></i></div><div class="kpi-value" id="kpi-cnt"><?= format_number((new Sale())->totalSalesCount()) ?></div><div class="kpi-label">Sales Rows</div></div></div>
        <div class="col-md-3"><div class="kpi-card aov"><div class="kpi-icon"><i class="fas fa-file-invoice-dollar"></i></div><div class="kpi-value" id="kpi-aov"><?= format_currency((new Sale())->averageOrderValue()) ?></div><div class="kpi-label">Avg Sale Line</div></div></div>
    </div>
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <h5><i class="fas fa-filter text-primary me-2"></i>Filters</h5>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <select class="form-select form-select-sm" id="f-region" style="width: auto;"><option value="">All Regions</option></select>
                <select class="form-select form-select-sm" id="f-category" style="width: auto;"><option value="">All Categories</option></select>
                <input type="date" class="form-control form-control-sm" id="f-start" style="width: auto;">
                <input type="date" class="form-control form-control-sm" id="f-end" style="width: auto;">
                <button class="btn btn-sm btn-light" onclick="resetFilters()"><i class="fas fa-rotate"></i></button>
                <?php if ($canEdit): ?>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-accent dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-download me-1"></i>Export</button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault();exportCsv()"><i class="fas fa-file-csv me-2"></i>Export CSV</a></li>
                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault();exportXlsx()"><i class="fas fa-file-excel me-2"></i>Export Excel</a></li>
                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault();window.print()"><i class="fas fa-print me-2"></i>Print</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-list text-accent me-2"></i>All Sales Records</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable mb-0" id="salesTable" style="width:100%;">
                    <thead>
                        <tr>
                            <th>ID</th><th>Order Code</th><th>Date</th><th>Product</th><th>Category</th>
                            <th>Customer</th><th>Region</th><th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th><th class="text-end">Disc%</th>
                            <th class="text-end">Amount</th><th class="text-end">Profit</th><th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
const regs = <?= json_encode((new Region())->all()) ?>;
const cats = <?= json_encode((new Category())->all()) ?>;
document.getElementById('f-region').innerHTML += regs.map(r => `<option value="${r.id}">${e(r.name)}</option>`).join('');
document.getElementById('f-category').innerHTML += cats.map(c => `<option value="${c.id}">${e(c.name)}</option>`).join('');

let dt;
function loadTable() {
    if (dt) dt.destroy();
    dt = $('#salesTable').DataTable({
        processing: true, serverSide: true, pageLength: 25, lengthMenu: [10, 25, 50, 100],
        search: { search: <?= json_encode($_GET['q'] ?? '') ?> },
        ajax: {
            url: BASE_URL + '/api/table.php?type=sales',
            type: 'GET',
            data: d => { d.region = document.getElementById('f-region').value; d.category = document.getElementById('f-category').value; d.start_date = document.getElementById('f-start').value; d.end_date = document.getElementById('f-end').value; },
            dataSrc: r => r.data || []
        },
        columns: [
            { data: 'id', width: '60px' },
            { data: 'order_code', render: v => `<div class="fw-semibold small">${e(v||'')}</div>` },
            { data: 'sale_date', render: v => formatDate(v) },
            { data: 'product', render: v => `<span title="${e(v||'')}" style="max-width:200px;display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${e(v||'N/A')}</span>` },
            { data: 'category', render: v => v ? `<span class="tag tag-primary">${e(v)}</span>` : '-' },
            { data: 'customer', render: v => e(v||'-') },
            { data: 'region', render: v => v ? `<span class="tag tag-info">${e(v)}</span>` : '-' },
            { data: 'quantity', className: 'text-end fw-semibold', render: v => formatNumber(v) },
            { data: 'unit_price', className: 'text-end', render: v => formatCurrency(v) },
            { data: 'discount', className: 'text-end', render: v => (v?Number(v).toFixed(1)+'%':'-') },
            { data: 'sales_amount', className: 'text-end fw-semibold', render: v => formatCurrency(v) },
            { data: 'profit', className: 'text-end fw-bold', render: v => `<span class="${(+v)>=0?'text-success':'text-danger'}">${formatCurrency(v)}</span>` },
            { data: 'id', orderable: false, width: '80px', className: 'text-end',
              render: id => `<div class="btn-group"><button class="btn btn-sm btn-light" onclick="viewSale(${id})"><i class="fas fa-eye"></i></button>
                <?php if ($canEdit): ?><button class="btn btn-sm btn-light text-danger" onclick="deleteSale(${id})"><i class="fas fa-trash"></i></button><?php endif; ?></div>` }
        ],
        order: [[0,'desc']]
    });
}
loadTable();
document.getElementById('f-region').addEventListener('change', loadTable);
document.getElementById('f-category').addEventListener('change', loadTable);
document.getElementById('f-start').addEventListener('change', loadTable);
document.getElementById('f-end').addEventListener('change', loadTable);
function resetFilters() {
    ['f-region','f-category','f-start','f-end'].forEach(id => document.getElementById(id).value = '');
    loadTable();
}
function viewSale(id) { viewItem('sale', id); }
function deleteSale(id) { deleteItem('sale', id, 'sale #' + id, () => dt.ajax.reload(null, false)); }
function exportQuery(fmt) {
    const q = new URLSearchParams({ type: 'sales', format: fmt, region: document.getElementById('f-region').value, category: document.getElementById('f-category').value,
        start_date: document.getElementById('f-start').value, end_date: document.getElementById('f-end').value });
    return BASE_URL + '/api/export.php?' + q.toString();
}
function exportCsv() { window.location.href = exportQuery('csv'); }
function exportXlsx() { window.location.href = exportQuery('xlsx'); }
function e(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
