<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('view_products');
$currentPage = 'products'; $pageTitle = 'Products'; $pageIcon = 'fas fa-boxes-stacked';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Sales & Analytics'], ['label' => 'Products']];
$pm = new Product();
$totalValue = $pm->inventoryValue();
$totalProducts = $pm->totalProducts();
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
$categories = (new Category())->allWithStats();
?>
<div class="fade-in">
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="kpi-card revenue"><div class="kpi-icon"><i class="fas fa-boxes"></i></div><div class="kpi-value"><?= format_number($totalProducts) ?></div><div class="kpi-label">Total Products</div></div></div>
        <div class="col-md-3"><div class="kpi-card orders"><div class="kpi-icon"><i class="fas fa-layer-group"></i></div><div class="kpi-value"><?= format_number(count($categories)) ?></div><div class="kpi-label">Categories</div></div></div>
        <div class="col-md-3"><div class="kpi-card profit"><div class="kpi-icon"><i class="fas fa-warehouse"></i></div><div class="kpi-value"><?= format_currency($totalValue) ?></div><div class="kpi-label">Inventory Value</div></div></div>
        <div class="col-md-3"><div class="kpi-card customers"><div class="kpi-icon"><i class="fas fa-bolt"></i></div><div class="kpi-value"><?= format_number(count($pm->lowStock())) ?></div><div class="kpi-label">Low Stock</div></div></div>
    </div>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5><i class="fas fa-box text-accent me-2"></i>Product Catalog</h5>
            <select id="f-cat" class="form-select form-select-sm" style="width:auto;"><option value="">All Categories</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table datatable mb-0" id="pTable"><thead><tr><th>ID</th><th>Code</th><th>Name</th><th>Category</th><th>Sub-Category</th><th class="text-end">Price</th><th class="text-end">Stock</th><th></th></tr></thead></table></div>
        </div>
    </div>
</div>
<script>
let pdt;
function loadP() {
    if (pdt) pdt.destroy();
    pdt = $('#pTable').DataTable({ processing: true, serverSide: true, pageLength: 25, lengthMenu: [10, 25, 50, 100],
        ajax: { url: BASE_URL + '/api/table.php?type=products', data: d => d.category = document.getElementById('f-cat').value, dataSrc: 'data' },
        columns: [
            { data: 'id', width:'60px' },
            { data: 'product_code', render: v => `<span class="fw-semibold tag tag-info">${e(v)}</span>` },
            { data: 'name', render: v => e(v) },
            { data: 'category_name', render: v => v ? `<span class="tag tag-primary">${e(v)}</span>` : '-' },
            { data: 'sub_category', render: v => e(v || '-') },
            { data: 'unit_price', className:'text-end fw-semibold', render: v => formatCurrency(v) },
            { data: 'stock_quantity', className:'text-end', render: v => { const n = +v; return `<span class="fw-bold ${n<10?'text-danger':(n<50?'text-warning':'text-success')}">${formatNumber(v)}</span>`; } },
            { data: 'id', orderable:false, width:'80px', className:'text-end', render: id => `<div class="btn-group"><button class="btn btn-sm btn-light" onclick="viewP(${id})"><i class="fas fa-eye"></i></button><button class="btn btn-sm btn-light text-danger" onclick="delP(${id})"><i class="fas fa-trash"></i></button></div>` }
        ], order: [[0,'desc']]
    });
}
loadP();
document.getElementById('f-cat').addEventListener('change', loadP);
function viewP(id) { viewItem('product', id); }
function delP(id) { deleteItem('product', id, 'product #' + id, () => pdt.ajax.reload(null, false), 'Its sales history stays in your data, but will no longer be linked to this product.'); }
function e(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
