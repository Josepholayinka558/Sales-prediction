<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('create_reports');
$currentPage = 'reports'; $pageTitle = 'Reports'; $pageIcon = 'fas fa-file-lines';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Management'], ['label' => 'Reports']];
$rm = new Report();
$reports = $rm->all();
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
$saleModel = new Sale();
$dr = $saleModel->getDateRange();
$types = ['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly','custom'=>'Custom'];
?>
<div class="fade-in">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-file-circle-plus text-primary me-2"></i>Generate New Report</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Report Type</label><select id="r-type" class="form-select"><?php foreach($types as $k=>$v): ?><option value="<?= $k ?>" <?= $k==='monthly'?'selected':'' ?>><?= $v ?> Report</option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Start Date</label><input type="date" id="r-start" class="form-control" value="<?= $dr['min_date'] ?? '' ?>"></div>
                <div class="col-md-3"><label class="form-label">End Date</label><input type="date" id="r-end" class="form-control" value="<?= $dr['max_date'] ?? '' ?>"></div>
                <div class="col-md-3"><label class="form-label">Name</label><div class="input-group"><input type="text" id="r-name" class="form-control" placeholder="Sales Report"><button class="btn btn-primary" onclick="genReport()"><i class="fas fa-wand-magic-sparkles me-2"></i>Generate</button></div></div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <a href="#" onclick="genReport('daily')" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-shadow text-center p-4 transition-all">
                    <div class="rounded-4 mx-auto d-flex align-items-center justify-content-center mb-3 bg-primary text-white" style="width:64px;height:64px;font-size:1.75rem;"><i class="fas fa-calendar-day"></i></div>
                    <h6 class="fw-bold mb-1">Daily Report</h6><small class="text-muted">Last 24 hours analytics</small>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="#" onclick="genReport('weekly')" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-shadow text-center p-4 transition-all">
                    <div class="rounded-4 mx-auto d-flex align-items-center justify-content-center mb-3 text-white" style="width:64px;height:64px;font-size:1.75rem;background:var(--accent);color:#1e1b4b !important;"><i class="fas fa-calendar-week"></i></div>
                    <h6 class="fw-bold mb-1">Weekly Report</h6><small class="text-muted">7-day performance overview</small>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="#" onclick="genReport('monthly')" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-shadow text-center p-4 transition-all">
                    <div class="rounded-4 mx-auto d-flex align-items-center justify-content-center mb-3 bg-success text-white" style="width:64px;height:64px;font-size:1.75rem;"><i class="fas fa-calendar"></i></div>
                    <h6 class="fw-bold mb-1">Monthly Report</h6><small class="text-muted">Full month comprehensive</small>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="#" onclick="genReport('yearly')" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-shadow text-center p-4 transition-all">
                    <div class="rounded-4 mx-auto d-flex align-items-center justify-content-center mb-3 bg-danger text-white" style="width:64px;height:64px;font-size:1.75rem;"><i class="fas fa-calendar-check"></i></div>
                    <h6 class="fw-bold mb-1">Yearly Report</h6><small class="text-muted">Annual summary report</small>
                </div>
            </a>
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-folder-open text-accent me-2"></i>Generated Reports</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table datatable mb-0" id="repTable" data-empty="No reports yet - generate your first above!"><thead><tr><th>Name</th><th>Type</th><th>Period</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
                <?php if (!empty($reports)): foreach($reports as $r): ?>
                    <tr>
                        <td><div class="d-flex align-items-center gap-2"><i class="fas fa-file-pdf text-danger"></i><div><div class="fw-semibold"><?= e($r['name']) ?></div><small class="text-muted">by <?= e($r['created_by_name'] ?? 'System') ?></small></div></div></td>
                        <td><span class="tag tag-primary text-capitalize"><?= e($r['report_type']) ?></span></td>
                        <td class="small"><?= $r['start_date']?format_date($r['start_date']).' → '.format_date($r['end_date']):'-' ?></td>
                        <td><span class="tag tag-<?= $r['status']==='completed'?'success':($r['status']==='generating'?'info':'danger') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td class="text-muted small"><?= format_date($r['created_at']) ?></td>
                        <td><div class="btn-group">
                            <button class="btn btn-sm btn-light" onclick="exportR('csv',<?= $r['id'] ?>)"><i class="fas fa-file-csv text-success"></i></button>
                            <button class="btn btn-sm btn-light" onclick="exportR('xlsx',<?= $r['id'] ?>)"><i class="fas fa-file-excel text-info"></i></button>
                            <button class="btn btn-sm btn-light" onclick="delR(<?= $r['id'] ?>)"><i class="fas fa-trash text-danger"></i></button>
                        </div></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
<script>
async function genReport(type) {
    if (window.event && window.event.preventDefault) window.event.preventDefault();
    const t = (typeof type === 'string' && type) ? type : document.getElementById('r-type').value;
    const name = document.getElementById('r-name').value.trim() || (t.charAt(0).toUpperCase() + t.slice(1) + ' Report ' + new Date().toLocaleDateString());
    const sd = document.getElementById('r-start').value, ed = document.getElementById('r-end').value;
    if (sd && ed && sd > ed) return Swal.fire('Check the dates', 'The start date must be before the end date.', 'warning');
    loadingSwal('Generating ' + t + ' report...');
    try {
        const r = await apiAction({ action: 'create_report', name, report_type: t, start_date: sd, end_date: ed });
        Swal.close();
        if (!r.success) return Swal.fire('Could not create report', r.error || 'Please try again.', 'error');
        const c = await Swal.fire({ icon: 'success', title: 'Report ready', html: '<strong>' + name.replace(/</g,'&lt;') + '</strong><br><small class="text-muted">Choose a format to download</small>',
            showDenyButton: true, showCancelButton: true, confirmButtonText: 'Download CSV', denyButtonText: 'Download Excel', cancelButtonText: 'Close' });
        if (c.isConfirmed) exportR('csv', r.id); else if (c.isDenied) exportR('xlsx', r.id);
        else location.reload();
        if (c.isConfirmed || c.isDenied) setTimeout(() => location.reload(), 1200);
    } catch (err) { Swal.close(); Swal.fire('Could not create report', err.message, 'error'); }
}
function exportR(type, id) {
    window.location.href = BASE_URL + '/api/export.php?type=report&format=' + type + '&id=' + id;
}
function delR(id) { deleteItem('report', id, 'this report', () => location.reload()); }
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
