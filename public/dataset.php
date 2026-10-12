<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('manage_dataset');

$currentPage = 'dataset';
$pageTitle = 'Dataset Manager';
$pageIcon = 'fas fa-database';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Management'], ['label' => 'Dataset Manager']];

$imports = (new DatasetImport())->recent(50);
$saleModel = new Sale();

$dsFiles = [];
$scanDirs = [DATASET_PATH, dirname(APP_ROOT)];
foreach ($scanDirs as $sd) if (is_dir($sd)) {
    foreach (glob($sd . DIRECTORY_SEPARATOR . '{*.xlsx,*.csv,*.xls}', GLOB_BRACE) as $f) {
        $bn = basename($f);
        if (!isset($dsFiles[$bn])) $dsFiles[$bn] = ['path' => $f, 'size' => filesize($f), 'mtime' => filemtime($f), 'ext' => strtolower(pathinfo($f, PATHINFO_EXTENSION))];
    }
}

$summary = [
    'sales' => $saleModel->totalSalesCount(),
    'orders' => (new Order())->totalOrders(),
    'products' => (new Product())->totalProducts(),
    'customers' => (new Customer())->totalCustomers(),
    'categories' => count((new Category())->all()),
    'regions' => count((new Region())->all()),
    'range' => $saleModel->getDateRange()
];
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<div class="fade-in">
    <div class="row g-4 mb-4">
        <div class="col-md-2"><div class="kpi-card sales"><div class="kpi-icon"><i class="fas fa-cash-register"></i></div><div class="kpi-value"><?= format_number($summary['sales']) ?></div><div class="kpi-label">Sales Rows</div></div></div>
        <div class="col-md-2"><div class="kpi-card orders"><div class="kpi-icon"><i class="fas fa-receipt"></i></div><div class="kpi-value"><?= format_number($summary['orders']) ?></div><div class="kpi-label">Orders</div></div></div>
        <div class="col-md-2"><div class="kpi-card customers"><div class="kpi-icon"><i class="fas fa-users"></i></div><div class="kpi-value"><?= format_number($summary['customers']) ?></div><div class="kpi-label">Customers</div></div></div>
        <div class="col-md-2"><div class="kpi-card profit"><div class="kpi-icon"><i class="fas fa-boxes"></i></div><div class="kpi-value"><?= format_number($summary['products']) ?></div><div class="kpi-label">Products</div></div></div>
        <div class="col-md-2"><div class="kpi-card aov"><div class="kpi-icon"><i class="fas fa-layer-group"></i></div><div class="kpi-value"><?= format_number($summary['categories']) ?></div><div class="kpi-label">Categories</div></div></div>
        <div class="col-md-2"><div class="kpi-card growth"><div class="kpi-icon"><i class="fas fa-location-dot"></i></div><div class="kpi-value"><?= format_number($summary['regions']) ?></div><div class="kpi-label">Regions</div></div></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header"><h5><i class="fas fa-cloud-arrow-up text-primary me-2"></i>Upload & Import Dataset</h5></div>
                <div class="card-body">
                    <form id="uploadForm" method="POST" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <div class="mb-3">
                            <div id="dropZone" class="p-5 rounded-4 text-center border-2 border-dashed cursor-pointer transition-all" style="border-color: #e2e8f0; background: #f8fafc;">
                                <i class="fas fa-file-excel fa-3x mb-3" style="color: #10b981;"></i>
                                <h6 class="fw-bold mb-1">Drag & Drop your file here</h6>
                                <p class="text-muted small mb-3">or click to browse - Supports CSV, XLSX, XLS</p>
                                <input type="file" name="dataset_file" id="fileInput" accept=".csv,.xlsx,.xls" class="d-none">
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()"><i class="fas fa-folder-open me-2"></i>Choose File</button>
                            </div>
                            <div id="selectedFile" class="mt-3 d-none"></div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="truncate" id="truncate">
                                <label class="form-check-label fw-semibold" for="truncate" style="font-size: 0.85rem;">Clear existing data before import (refresh)</label>
                            </div>
                            <div class="text-muted small">If unchecked, duplicates will be skipped and new records added.</div>
                        </div>
                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="uploadBtn"><i class="fas fa-upload me-2"></i>Upload & Import</button>
                            <button type="button" class="btn btn-accent btn-lg" onclick="autoImport()"><i class="fas fa-bolt me-2"></i>Auto-Import dataset.xlsx (Client Parse)</button>
                            <button type="button" class="btn btn-success btn-lg" onclick="serverImport('dataset.xlsx')"><i class="fas fa-server me-2"></i>🚀 1-Click Server Import dataset.xlsx</button>
                        </div>
                        <?php if (!empty($dsFiles)): ?>
                        <div class="mb-3 p-3 rounded-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-folder-open text-success"></i>
                                <span class="fw-semibold small text-success">Files found on server (click to import server-side):</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($dsFiles as $name => $info):
                                    $badgeCls = $info['ext'] === 'xlsx' ? 'bg-success' : ($info['ext'] === 'csv' ? 'bg-info' : 'bg-warning text-dark');
                                    $iconCls = $info['ext'] === 'xlsx' ? 'fa-file-excel text-success' : ($info['ext'] === 'csv' ? 'fa-file-csv text-info' : 'fa-file-spreadsheet text-warning');
                                ?>
                                    <button type="button" class="btn btn-sm btn-light border d-flex align-items-center gap-2 px-3 py-2 hover:shadow" style="transition: all 0.15s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(16,185,129,0.25)'" onmouseout="this.style.boxShadow='none'" onclick="serverImport('<?= e($name) ?>')">
                                        <i class="fas <?= $iconCls ?>"></i>
                                        <div class="text-start">
                                            <div class="fw-semibold small text-start"><?= e($name) ?></div>
                                            <small class="text-muted"><?= number_format($info['size']/1024/1024, 2) ?> MB · <?= date('M d H:i', $info['mtime']) ?></small>
                                        </div>
                                        <span class="badge rounded-pill ms-2 <?= $badgeCls ?>"><?= strtoupper($info['ext']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mb-3 p-3 rounded-3" style="background: #fff7ed; border: 1px solid #fed7aa;">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fas fa-circle-info text-warning mt-1"></i>
                                <div class="small text-warning-emphasis">
                                    <div class="fw-semibold">No dataset.xlsx found on server.</div>
                                    Place <code>dataset.xlsx</code> in <code><?= e(DATASET_PATH) ?></code> or project root, then refresh this page to see the 1-Click button.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                    <div id="progressArea" class="d-none">
                        <div class="mb-2 d-flex justify-content-between align-items-center small"><span id="progressLabel">Parsing file...</span><span id="progressPct">0%</span></div>
                        <div class="progress mb-3"><div id="progressBar" class="progress-bar" style="width: 0%"></div></div>
                        <div id="stepInfo" class="small text-muted"></div>
                    </div>
                    <div id="importResult" class="d-none"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-eye text-accent me-2"></i>Data Preview</h5>
                    <button class="btn btn-sm btn-light" onclick="refreshPreview()"><i class="fas fa-rotate me-1"></i>Refresh</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                        <table class="table table-sm mb-0" id="previewTable">
                            <thead class="sticky-top"><tr>
                                <th>Order</th><th>Date</th><th>Product</th><th>Customer</th><th>Region</th><th class="text-end">Qty</th><th class="text-end">Amount</th><th class="text-end">Profit</th>
                            </tr></thead>
                            <tbody id="previewBody">
                                <tr><td colspan="8" class="text-center py-4"><div class="loading-spinner"></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-clock-rotate-left text-info me-2"></i>Recent Imports</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table align-middle mb-0">
                            <thead class="sticky-top">
                                <tr><th>File</th><th>Type</th><th class="text-end">Total</th><th class="text-end">Imported</th><th class="text-end">Duplicates</th><th class="text-end">Errors</th><th>Status</th><th>Date</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($imports)): ?>
                                    <tr><td colspan="9" class="text-center py-4 text-muted small">No imports yet</td></tr>
                                <?php else: foreach ($imports as $i):
                                    $sBadge = match($i['status']) { 'completed' => 'tag-success', 'partial' => 'tag-warning', 'failed' => 'tag-danger', 'processing' => 'tag-info', default => 'tag-primary' };
                                ?>
                                    <tr>
                                        <td><div class="d-flex align-items-center gap-2"><i class="fas fa-file-excel text-success"></i><div><div class="fw-semibold small"><?= e($i['file_name']) ?></div><small class="text-muted"><?= e($i['username']) ? 'by ' . e($i['username']) : '' ?></small></div></div></td>
                                        <td><span class="tag tag-primary text-uppercase" style="font-size: 0.65rem;"><?= e($i['file_type']) ?></span></td>
                                        <td class="text-end fw-semibold"><?= format_number($i['total_rows']) ?></td>
                                        <td class="text-end fw-semibold text-success"><?= format_number($i['imported_rows']) ?></td>
                                        <td class="text-end text-warning"><?= format_number($i['duplicate_rows']) ?></td>
                                        <td class="text-end text-danger"><?= format_number($i['error_rows']) ?></td>
                                        <td><span class="tag <?= $sBadge ?>"><?= e(ucfirst($i['status'])) ?></span></td>
                                        <td class="small text-muted"><?= format_date($i['created_at']) ?></td>
                                        <td><button class="btn btn-sm btn-light" onclick="deleteImport(<?= $i['id'] ?>)"><i class="fas fa-trash text-danger"></i></button></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const dz = document.getElementById('dropZone');
const fi = document.getElementById('fileInput');
const sf = document.getElementById('selectedFile');
dz.addEventListener('click', () => fi.click());
['dragenter','dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.style.background = '#eef2ff'; dz.style.borderColor = '#6f42c1'; }));
['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.style.background = '#f8fafc'; dz.style.borderColor = '#e2e8f0'; }));
dz.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { fi.files = ev.dataTransfer.files; showFile(); }});
fi.addEventListener('change', showFile);

function showFile() {
    if (!fi.files.length) return;
    const f = fi.files[0];
    sf.className = 'mt-3';
    sf.innerHTML = `<div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light"><div class="d-flex align-items-center gap-3"><i class="fas fa-file-excel text-success fa-2xl"></i><div><div class="fw-semibold">${f.name}</div><div class="small text-muted">${(f.size/1024/1024).toFixed(2)} MB</div></div></div><button class="btn btn-sm btn-danger" onclick="clearFile()"><i class="fas fa-times"></i></button></div>`;
}
function clearFile() { fi.value = ''; sf.className = 'd-none'; sf.innerHTML = ''; }

document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!fi.files.length) { Swal.fire('No file', 'Please select a file first.', 'warning'); return; }
    const formData = new FormData();
    formData.append('dataset_file', fi.files[0]);
    formData.append('truncate', document.getElementById('truncate').checked ? '1' : '0');
    formData.append('action', 'upload_import');
    formData.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
    await doImport(formData, BASE_URL + '/api/import.php');
});

async function autoImport() {
    const truncate = document.getElementById('truncate').checked ? '1' : '0';
    const formData = new FormData();
    formData.append('action', 'auto_import');
    formData.append('truncate', truncate);
    formData.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
    loadingSwal('Looking for dataset.xlsx...');
    try {
        const res = await ajaxRequest(BASE_URL + '/api/import.php', 'POST', formData);
        Swal.close();
        if (res.needs_parse) {
            await parseAndImport(res.file_path, res.filename, res.truncate, true);
        } else if (res.success) {
            showResult(res);
        } else {
            Swal.fire('Error', res.error || 'Import failed', 'error');
        }
    } catch(e) { Swal.close(); Swal.fire('Error', String(e), 'error'); }
}

async function doImport(formData, url) {
    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('progressArea').classList.remove('d-none');
    setProgress(10, 'Uploading file...');
    try {
        const res = await ajaxRequest(url, 'POST', formData);
        if (res.needs_parse) {
            await parseAndImport(null, res.filename, res.truncate, false, fi.files[0]);
        } else if (res.success) {
            setProgress(100, 'Import complete!');
            showResult(res);
        } else {
            Swal.fire('Error', res.error || 'Import failed', 'error');
        }
    } catch(err) {
        Swal.fire('Error', String(err), 'error');
    } finally {
        document.getElementById('uploadBtn').disabled = false;
    }
}

async function parseAndImport(filePath, filename, truncate, isServer, fileObj = null) {
    setProgress(15, 'Loading SheetJS library...');
    try { await ensureXLSX(); } catch(e) { Swal.close(); Swal.fire('Library Error', String(e), 'error'); return; }
    setProgress(20, 'Parsing Excel file (SheetJS)...');
    let data;
    try {
        if (isServer) {
            const resp = await fetch(filePath.includes('http') ? filePath : ('<?= url('dataset') ?>/' + filename));
            if (!resp.ok) throw new Error('File request failed: ' + resp.status);
            const ab = await resp.arrayBuffer();
            data = ab;
        } else {
            data = await fileObj.arrayBuffer();
        }
    } catch(e) {
        Swal.fire('Error', 'Could not read the file: ' + e, 'error'); return;
    }
    setProgress(40, 'Reading workbook...');
    await new Promise(r => setTimeout(r, 100));
    let rows;
    try {
        const wb = XLSX.read(data, { type: 'array', cellDates: true });
        const ws = wb.Sheets[wb.SheetNames[0]];
        setProgress(60, 'Converting rows to JSON...');
        rows = XLSX.utils.sheet_to_json(ws, { defval: '' });
    } catch(e) {
        Swal.fire('Parse Error', 'Failed to parse Excel file: ' + e, 'error'); return;
    }
    if (!rows || !rows.length) {
        Swal.fire('Empty File', 'The spreadsheet has no data rows.', 'warning'); return;
    }
    setProgress(75, `Found ${rows.length.toLocaleString()} rows. Sending to server...`);
    await new Promise(r => setTimeout(r, 100));
    const batch = 2000;
    let allStats = { total: 0, imported: 0, duplicates: 0, errors: 0 };
    let lastResult = null;
    try {
        for (let i = 0; i < rows.length; i += batch) {
            const slice = rows.slice(i, i + batch);
            const fd = new FormData();
            fd.append('action', 'import_json');
            fd.append('filename', filename);
            if (i === 0) fd.append('truncate', truncate ? '1' : '0');
            fd.append('rows', JSON.stringify(slice));
            fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
            const pct = Math.round(75 + ((i + slice.length) / rows.length) * 22);
            setProgress(pct, `Importing rows ${(i+1).toLocaleString()} - ${Math.min(i+batch, rows.length).toLocaleString()} of ${rows.length.toLocaleString()}...`);
            const res = await ajaxRequest(BASE_URL + '/api/import.php', 'POST', fd);
            lastResult = res;
            if (res.stats) {
                allStats.total += res.stats.total || 0;
                allStats.imported += res.stats.imported || 0;
                allStats.duplicates += res.stats.duplicates || 0;
                allStats.errors += res.stats.errors || 0;
            }
        }
    } catch(e) {
        Swal.fire('Import Error', String(e), 'error'); return;
    }
    setProgress(100, 'Done!');
    showResult(Object.assign({}, lastResult, { stats: allStats }));
}

function setProgress(pct, label) {
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressPct').textContent = pct + '%';
    document.getElementById('progressLabel').textContent = label;
    document.getElementById('stepInfo').textContent = label;
}

function showResult(res) {
    const r = document.getElementById('importResult');
    r.classList.remove('d-none');
    const s = res.stats || {};
    const cls = s.errors > 0 ? (s.imported > 0 ? 'alert-warning' : 'alert-danger') : 'alert-success';
    r.innerHTML = `<div class="alert ${cls} border-0 rounded-4 shadow-sm p-4 mb-4"><h6 class="fw-bold mb-3">
        <i class="fas fa-circle-check me-2"></i>Import ${s.errors > 0 ? (s.imported > 0 ? 'Partially Completed' : 'Failed') : 'Completed Successfully'}</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="bg-white rounded-3 p-3 text-center"><div class="fs-4 fw-bold">${formatNumber(s.total||0)}</div><div class="text-muted small">Total rows</div></div></div>
            <div class="col-md-3"><div class="bg-white rounded-3 p-3 text-center"><div class="fs-4 fw-bold text-success">${formatNumber(s.imported||0)}</div><div class="text-muted small">Imported</div></div></div>
            <div class="col-md-3"><div class="bg-white rounded-3 p-3 text-center"><div class="fs-4 fw-bold text-warning">${formatNumber(s.duplicates||0)}</div><div class="text-muted small">Skipped duplicates</div></div></div>
            <div class="col-md-3"><div class="bg-white rounded-3 p-3 text-center"><div class="fs-4 fw-bold text-danger">${formatNumber(s.errors||0)}</div><div class="text-muted small">Errors</div></div></div>
        </div>
        ${res.errors && res.errors.length ? `<div class="bg-white rounded-3 p-3 mb-3"><div class="fw-semibold text-danger mb-2"><i class="fas fa-triangle-exclamation me-1"></i>Sample Errors:</div><div class="small text-muted" style="max-height: 120px; overflow-y: auto;">${res.errors.map(e=>`<div>${e}</div>`).join('')}</div></div>` : ''}
        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="fas fa-refresh me-1"></i>Refresh to see updated data</button>
    </div>`;
    if ((s.imported||0) > 0) toast(`${formatNumber(s.imported)} rows imported successfully!`, 'success');
    refreshPreview();
}

async function serverImport(fileName) {
    const truncate = document.getElementById('truncate').checked ? '1' : '0';
    loadingSwal('Server importing ' + fileName + '...');
    try {
        setProgress(5, 'Preparing import...');
        document.getElementById('progressArea').classList.remove('d-none');
        const fd = new FormData();
        fd.append('action', 'server_import_file');
        fd.append('file', fileName);
        fd.append('truncate', truncate);
        fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
        setProgress(15, 'Reading file from disk...');
        const res = await ajaxRequest(BASE_URL + '/api/import.php', 'POST', fd);
        setProgress(50, 'Inserting rows into database...');
        Swal.close();
        if (!res.success) throw new Error(res.error || 'Import failed');
        await new Promise(r => setTimeout(r, 200));
        showResult(Object.assign({ stats: {
            total: res.total_rows ?? 0,
            imported: res.imported_rows ?? 0,
            duplicates: res.duplicate_rows ?? 0,
            errors: res.error_rows ?? 0,
        } }, res));
        setProgress(100, 'Import complete');
    } catch(err) {
        Swal.close();
        setProgress(0, 'Failed');
        Swal.fire({ icon: 'error', title: 'Server Import Failed', html: String(err).replace(/\n/g,'<br>'), footer: 'You can still try the "Upload & Import" or "Client Parse" buttons below.' });
    }
}

async function refreshPreview() {
    const pb = document.getElementById('previewBody');
    pb.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="loading-spinner"></div></td></tr>';
    const res = await ajaxRequest(BASE_URL + '/api/data.php?type=recent_transactions&limit=30');
    const rows = res.data || [];
    if (!rows.length) {
        pb.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted small">No data yet. Import the dataset to see a preview.</td></tr>';
        return;
    }
    pb.innerHTML = rows.map(r => {
        const pc = (+r.profit) >= 0 ? 'text-success' : 'text-danger';
        return `<tr>
            <td><div class="fw-semibold small">${e(r.order_code||('#'+r.id))}</div><small class="text-muted">${formatDate(r.sale_date)}</small></td>
            <td class="small">${formatDate(r.sale_date)}</td>
            <td><div class="small" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${e(r.product||'N/A')}</div></td>
            <td class="small">${e(r.customer||'-')}</td>
            <td><span class="tag tag-info">${e(r.region||'-')}</span></td>
            <td class="text-end fw-semibold small">${formatNumber(r.quantity)}</td>
            <td class="text-end fw-semibold small">${formatCurrency(r.sales_amount)}</td>
            <td class="text-end fw-bold small ${pc}">${formatCurrency(r.profit)}</td>
        </tr>`;
    }).join('');
}
refreshPreview();

function deleteImport(id) {
    confirmDelete('Delete this import record? The actual data will remain.').then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'delete_import');
        fd.append('id', id);
        fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
        ajaxRequest(BASE_URL + '/api/import.php', 'POST', fd).then(res => { if (res.success) { toast('Deleted','success'); location.reload(); }});
    });
}

function e(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
