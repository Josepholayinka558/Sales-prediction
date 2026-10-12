<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('all');
$currentPage = 'settings'; $pageTitle = 'System Settings'; $pageIcon = 'fas fa-gear';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Settings'], ['label' => 'System Settings']];
$user = auth()->user();
$isAdmin = auth()->hasRole('Administrator');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!Csrf::verifyRequest()) { echo json_encode(['success'=>false,'error'=>'Invalid token']); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'save_settings') {
        $keys = ['company_name','company_email','company_address','currency','currency_symbol','timezone','date_format','forecast_ma_period','forecast_alpha','session_timeout','pagination_limit'];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) {
                $type = in_array($k,['forecast_ma_period','session_timeout','pagination_limit']) ? 'integer'
                    : (in_array($k,['forecast_alpha']) ? 'float' : 'string');
                set_setting($k, $_POST[$k], $type);
            }
        }
        auth()->logActivity('update', 'Updated system settings');
        echo json_encode(['success'=>true,'message'=>'Settings saved successfully!']);
        exit;
    }
    if ($action === 'backup_db') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }
        $tables = ['roles','users','categories','regions','customers','products','orders','sales','forecasts','forecast_results','reports','notifications','chat_messages','activity_logs','settings','dataset_imports'];
        $sql = "-- SalesForecast BI Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        try {
            foreach ($tables as $table) {
                $cols = db()->query("DESCRIBE `$table`")->fetchAll();
                $create = db()->query("SHOW CREATE TABLE `$table`")->fetch();
                $sql .= "-- Table: $table\nDROP TABLE IF EXISTS `$table`;\n\n" . $create['Create Table'] . ";\n\n";
                $rows = db()->query("SELECT * FROM `$table`")->fetchAll();
                foreach (array_chunk($rows, 100) as $chunk) {
                    if (empty($chunk)) continue;
                    $vals = [];
                    foreach ($chunk as $r) {
                        $esc = array_map(function($v) use($cols) {
                            if ($v === null) return 'NULL';
                            return "'" . str_replace(["'","\\","\n","\r"], ["\\'","\\\\","\\n","\\r"], (string)$v) . "'";
                        }, $r);
                        $vals[] = '(' . implode(',', $esc) . ')';
                    }
                    $sql .= "INSERT INTO `$table` (`" . implode('`,`', array_keys($chunk[0])) . "`) VALUES\n" . implode(",\n", $vals) . ";\n\n";
                }
            }
            $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
            $fname = 'backup-' . date('Y-m-d-His') . '.sql';
            $fpath = REPORT_PATH . DIRECTORY_SEPARATOR . $fname;
            file_put_contents($fpath, $sql);
            create_notification(Session::userId(), 'system', 'Backup Complete', "Database backup: $fname", url('settings.php'));
            auth()->logActivity('export', "Database backup created: $fname");
            echo json_encode(['success'=>true,'file'=>$fname,'size'=>filesize($fpath)]);
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        exit;
    }
    if ($action === 'restore_db') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }
        echo json_encode(['success'=>false,'error'=>'Restore is done in phpMyAdmin (Import tab).']);
        exit;
    }
}
include dirname(__FILE__, 2) . '/app/views/partials/header.php';

function gs($k, $d=null) { return get_setting($k, $d); }
?>
<div class="fade-in">
    <div class="row">
        <div class="col-xl-3">
            <div class="card sticky-top mb-3" style="top:90px;">
                <div class="list-group list-group-flush">
                    <?php $tabs = [
                        ['company','Company','fa-building',true],
                        ['currency','Currency & Format','fa-coins',true],
                        ['forecast','Forecasting','fa-bullseye',true],
                        ['app','Application','fa-gear',true],
                        ['backup','Backup & Restore','fa-database',$isAdmin],
                        ['theme','Theme','fa-palette',true],
                    ];
                    $firstTab = true;
                    foreach($tabs as $t): if(!$t[3]) continue;
                    ?>
                        <a href="#tab-<?= $t[0] ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $t[0] ?>" class="list-group-item list-group-item-action border-0 py-3 px-4 d-flex align-items-center gap-2 tab-link <?= $firstTab?'active':'' ?>">
                            <i class="fas <?= $t[2] ?> text-primary me-2"></i><span class="fw-semibold small"><?= $t[1] ?></span>
                        </a>
                    <?php $firstTab = false; endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-9">
            <form id="settingsForm" onsubmit="saveSettings(event)" class="mb-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save_settings">
                <div class="tab-content">
                    <div class="card mb-4 tab-pane active show" id="tab-company">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-building text-primary me-2"></i>Company Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Company Name</label><input type="text" name="company_name" class="form-control" value="<?= e(gs('company_name','SalesForecast BI')) ?>"></div>
                                <div class="col-md-6"><label class="form-label">Contact Email</label><input type="email" name="company_email" class="form-control" value="<?= e(gs('company_email','')) ?>"></div>
                                <div class="col-md-12"><label class="form-label">Company Address</label><textarea name="company_address" class="form-control" rows="3"><?= e(gs('company_address','')) ?></textarea></div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4 tab-pane" id="tab-currency">
                        <div class="card-header"><h5><i class="fas fa-coins text-accent me-2"></i>Currency & Date Format</h5></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Currency Code (ISO)</label><input type="text" name="currency" class="form-control" value="<?= e(gs('currency','USD')) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Currency Symbol</label><input type="text" name="currency_symbol" class="form-control" value="<?= e(gs('currency_symbol','$')) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Timezone</label>
                                <select name="timezone" class="form-select">
                                    <?php $tzlist = ['UTC','America/New_York','America/Los_Angeles','America/Chicago','Europe/London','Europe/Paris','Asia/Singapore','Asia/Dubai','Asia/Kolkata','Asia/Tokyo','Australia/Sydney'];
                                    $cur = gs('timezone','UTC');
                                    foreach($tzlist as $t): ?><option value="<?= $t ?>" <?= $t===$cur?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">Date Format</label>
                                <select name="date_format" class="form-select">
                                    <?php $df = ['Y-m-d'=>'2024-01-15','m/d/Y'=>'01/15/2024','d/m/Y'=>'15/01/2024','M d, Y'=>'Jan 15, 2024'];
                                    $cdf = gs('date_format','Y-m-d');
                                    foreach($df as $k=>$v): ?><option value="<?= $k ?>" <?= $k===$cdf?'selected':'' ?>><?= $v ?> (<?= $k ?>)</option><?php endforeach; ?>
                                </select>
                            </div>
                        </div></div>
                    </div>
                    <div class="card mb-4 tab-pane" id="tab-forecast">
                        <div class="card-header"><h5><i class="fas fa-bullseye text-success me-2"></i>Forecast Parameters</h5></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Default MA Period (Moving Average Window)</label><input type="number" name="forecast_ma_period" class="form-control" min="2" max="180" value="<?= e(gs('forecast_ma_period',14)) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Default Exponential Smoothing Alpha (0.05 - 0.95)</label><input type="number" name="forecast_alpha" class="form-control" min="0.05" max="0.95" step="0.05" value="<?= e(gs('forecast_alpha',0.30)) ?>"></div>
                            <div class="col-md-12"><div class="bg-light rounded-3 p-3 small text-muted"><i class="fas fa-circle-info me-2 text-info"></i>Alpha closer to 1 weights recent data more heavily; alpha closer to 0 smooths more aggressively.</div></div>
                        </div></div>
                    </div>
                    <div class="card mb-4 tab-pane" id="tab-app">
                        <div class="card-header"><h5><i class="fas fa-gear text-info me-2"></i>Application</h5></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Session Timeout (seconds)</label><input type="number" name="session_timeout" class="form-control" min="300" max="86400" value="<?= e(gs('session_timeout',3600)) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Default Pagination Limit</label><input type="number" name="pagination_limit" class="form-control" min="5" max="500" value="<?= e(gs('pagination_limit',25)) ?>"></div>
                        </div></div>
                    </div>
                    <div class="card mb-4 tab-pane" id="tab-theme">
                        <div class="card-header"><h5><i class="fas fa-palette me-2" style="color:#F98B2A;"></i>Theme Settings</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Accent Color</label>
                                    <div class="d-flex gap-2 mb-2 flex-wrap">
                                        <button type="button" class="theme-swatch" style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#F98B2A,#FFB545);border:3px solid #fff;box-shadow:0 0 0 2px #F98B2A;" title="Orange" onclick="setTheme('#F98B2A','#FFB545')"></button>
                                        <button type="button" class="theme-swatch" style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#a78bfa);border:3px solid #fff;" title="Purple" onclick="setTheme('#7c3aed','#a78bfa')"></button>
                                        <button type="button" class="theme-swatch" style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#0d9488,#2dd4bf);border:3px solid #fff;" title="Teal" onclick="setTheme('#0d9488','#2dd4bf')"></button>
                                        <button type="button" class="theme-swatch" style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#ef4444,#f87171);border:3px solid #fff;" title="Red" onclick="setTheme('#ef4444','#f87171')"></button>
                                        <button type="button" class="theme-swatch" style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#60a5fa);border:3px solid #fff;" title="Blue" onclick="setTheme('#3b82f6','#60a5fa')"></button>
                                    </div>
                                    <div class="text-muted small">Click a color to preview. Orange is the active theme.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="card mb-4 tab-pane" id="tab-backup">
                        <div class="card-header"><h5><i class="fas fa-database text-warning me-2"></i>Backup & Restore Database</h5></div>
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6">
                                    <div class="p-4 rounded-3 border border-dashed" style="border-color:#e2e8f0;">
                                        <i class="fas fa-cloud-arrow-up fa-2xl text-primary mb-2"></i>
                                        <h6 class="fw-bold">Export Full Backup</h6>
                                        <p class="text-muted small mb-3">Download complete SQL dump including all data, settings, and history.</p>
                                        <button type="button" class="btn btn-primary" onclick="doBackup()"><i class="fas fa-download me-2"></i>Backup Now</button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-4 rounded-3 border border-dashed" style="border-color:#e2e8f0;">
                                        <i class="fas fa-cloud-arrow-down fa-2xl text-accent mb-2"></i>
                                        <h6 class="fw-bold">Restore Database</h6>
                                        <p class="text-muted small mb-3">Upload a .sql file to restore entire database. <span class="text-danger">Warning: This will overwrite existing data.</span></p>
                                        <button type="button" class="btn btn-accent" onclick="doRestore()"><i class="fas fa-upload me-2"></i>Restore SQL</button>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-semibold"><i class="fas fa-eye text-info me-2"></i>Database Preview</h6>
                                <button type="button" class="btn btn-sm btn-light" onclick="refreshDbPreview()"><i class="fas fa-refresh me-1"></i>Refresh</button>
                            </div>
                            <div class="table-responsive" style="max-height:320px;overflow:auto;border:1px solid var(--border);border-radius:12px;">
                                <table class="table table-sm mb-0">
                                    <thead class="sticky-top" style="background:#f8fafc;"><tr>
                                        <th>Table</th><th class="text-end">Rows</th><th class="text-end">Size</th><th>Engine</th><th>Updated</th>
                                    </tr></thead>
                                    <tbody id="dbPreviewBody">
                                        <tr><td colspan="5" class="text-center py-4"><div class="loading-spinner"></div></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="reset" class="btn btn-light"><i class="fas fa-rotate me-2"></i>Reset</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save All Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function saveSettings(e) {
    e.preventDefault();
    loadingSwal('Saving settings...');
    const fd = new FormData(document.getElementById('settingsForm'));
    ajaxRequest('', 'POST', fd).then(r => { Swal.close(); if (r.success) { toast(r.message || 'Saved!','success'); setTimeout(()=>location.reload(), 600); } else { Swal.fire('Error', r.error || 'Failed','error'); }}).catch(err=>{ Swal.close(); Swal.fire('Error', String(err),'error'); });
}
function doBackup() {
    loadingSwal('Creating database backup...');
    const fd = new FormData();
    fd.append('action','backup_db');
    fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
    ajaxRequest('', 'POST', fd).then(r => {
        Swal.close();
        if (r.success) {
            Swal.fire({ icon:'success', title:'Backup Complete!', html: `<strong>${r.file}</strong><br><small class="text-muted">${Math.round((r.size||0)/1024)} KB</small>`, showDenyButton:true, confirmButtonText:'<i class="fas fa-download me-1"></i> Download SQL', denyButtonText:'Close' })
                .then(res => { if (res.isConfirmed) { downloadBackup(r.file); }});
        } else Swal.fire('Error', r.error || 'Backup failed','error');
    }).catch(err=>{ Swal.close(); Swal.fire('Error', String(err),'error'); });
}
function downloadBackup(file) {
    window.location.href = BASE_URL + '/api/export.php?type=backup&file=' + encodeURIComponent(file);
}
function doRestore() {
    Swal.fire({ icon:'info', title:'Restore a backup', html:'<p class="text-start small">For safety, restoring is done outside the app:</p><ol class="text-start small"><li>Open <b>phpMyAdmin</b> and select the <code>sales_forecast</code> database.</li><li>Go to the <b>Import</b> tab and choose your <code>backup-....sql</code> file.</li><li>Click <b>Go</b>, then log in again.</li></ol>', confirmButtonText:'Got it' });
}
function setTheme(c1, c2) {
    document.documentElement.style.setProperty('--primary', c1);
    document.documentElement.style.setProperty('--primary-light', c2);
    toast('Theme updated (preview)','success');
}
async function refreshDbPreview() {
    const tb = document.getElementById('dbPreviewBody');
    if (!tb) return;
    tb.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="loading-spinner"></div></td></tr>';
    try {
        const res = await ajaxRequest(BASE_URL + '/api/table.php?type=table_meta');
        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        if (res && res.data && res.data.length) {
            tb.innerHTML = res.data.map(t => `<tr><td class="fw-semibold">${esc(t.name)}</td><td class="text-end">${formatNumber(t.rows)}</td><td class="text-end">${Math.round((t.size||0)/1024)} KB</td><td>${esc(t.engine)}</td><td class="text-muted small">Active</td></tr>`).join('');
            return;
        }
        tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No tables found.</td></tr>';
    } catch (err) {
        tb.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Could not load table information.</td></tr>';
    }
}
setTimeout(refreshDbPreview, 200);
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
