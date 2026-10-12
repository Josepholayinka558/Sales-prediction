<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('view_orders');
$currentPage = 'orders'; $pageTitle = 'Orders'; $pageIcon = 'fas fa-receipt';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Sales & Analytics'], ['label' => 'Orders']];
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
$om = new Order();
?>
<div class="fade-in">
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="kpi-card orders"><div class="kpi-icon"><i class="fas fa-receipt"></i></div><div class="kpi-value"><?= format_number($om->totalOrders()) ?></div><div class="kpi-label">Total Orders</div></div></div>
        <div class="col-md-3"><div class="kpi-card revenue"><div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div><div class="kpi-value"><?= format_currency(array_sum(array_column($om->all(), 'total_amount'))) ?></div><div class="kpi-label">Order Amount</div></div></div>
        <div class="col-md-3"><div class="kpi-card profit"><div class="kpi-icon"><i class="fas fa-chart-line"></i></div><div class="kpi-value"><?= format_currency(array_sum(array_column($om->all(), 'total_profit'))) ?></div><div class="kpi-label">Order Profit</div></div></div>
        <div class="col-md-3"><div class="kpi-card growth"><div class="kpi-icon"><i class="fas fa-truck-fast"></i></div><div class="kpi-value"><?= count(array_filter($om->byStatus(), fn($s) => $s['status'] === 'delivered')) ?></div><div class="kpi-label">Ship Modes</div></div></div>
    </div>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5><i class="fas fa-list text-primary me-2"></i>All Orders</h5>
            <div class="d-flex gap-2"><select id="f-status" class="form-select form-select-sm" style="width:auto;"><option value="">All Status</option><option>pending</option><option>processing</option><option>shipped</option><option>delivered</option><option>cancelled</option><option>returned</option></select></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable mb-0" id="ordersTable">
                    <thead><tr>
                        <th>ID</th><th>Order Code</th><th>Order Date</th><th>Customer</th><th>Region</th>
                        <th>Status</th><th>Ship Mode</th><th>Priority</th><th class="text-end">Amount</th><th class="text-end">Profit</th><th></th>
                    </tr></thead>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
let odt;
function loadOT() {
    if (odt) odt.destroy();
    odt = $('#ordersTable').DataTable({ processing: true, serverSide: true, pageLength: 25, lengthMenu: [10, 25, 50, 100],
        ajax: { url: BASE_URL + '/api/table.php?type=orders', data: d => d.status = document.getElementById('f-status').value, dataSrc: 'data' },
        columns: [
            { data: 'id', width: '60px' },
            { data: 'order_code', render: v => `<div class="fw-semibold">${e(v)}</div>` },
            { data: 'order_date', render: v => formatDate(v) },
            { data: 'customer_name', render: v => e(v || '-') },
            { data: 'region_name', render: v => v ? `<span class="tag tag-info">${e(v)}</span>` : '-' },
            { data: 'status', render: v => { const c = { pending:'tag-warning', processing:'tag-info', shipped:'tag-primary', delivered:'tag-success', cancelled:'tag-danger', returned:'tag-danger' }[v] || 'tag-primary'; return `<span class="tag ${c} text-uppercase">${e(v)}</span>`; }},
            { data: 'ship_mode', render: v => e(v || '-') },
            { data: 'priority', render: v => e(v || 'Standard') },
            { data: 'total_amount', className:'text-end fw-semibold', render: v => formatCurrency(v) },
            { data: 'total_profit', className:'text-end fw-bold', render: v => `<span class="${(+v)>=0?'text-success':'text-danger'}">${formatCurrency(v)}</span>` },
            { data: 'id', orderable:false, width:'80px', className:'text-end', render: id => `<div class="btn-group"><button class="btn btn-sm btn-light" onclick="viewOrder(${id})"><i class="fas fa-eye"></i></button><button class="btn btn-sm btn-light text-danger" onclick="deleteOrder(${id})"><i class="fas fa-trash"></i></button></div>` }
        ], order: [[0,'desc']]
    });
}
loadOT();
document.getElementById('f-status').addEventListener('change', loadOT);
function viewOrder(id) { viewItem('order', id); }
function deleteOrder(id) { deleteItem('order', id, 'order #' + id, () => odt.ajax.reload(null, false), 'Its sale line items will be deleted too.'); }
function e(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
