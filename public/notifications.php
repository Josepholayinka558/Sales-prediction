<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('view_notifications');
$currentPage = 'notifications'; $pageTitle = 'Notifications'; $pageIcon = 'fas fa-bell';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Communication'], ['label' => 'Notifications']];
$uid = (int)Session::userId();
db()->query("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$uid]);
$ns = db()->query("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100", [$uid])->fetchAll();
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
$typeIcon = ['forecast'=>'fa-chart-line bg-primary text-white','dataset'=>'fa-database','report'=>'fa-file-lines bg-success text-white','user'=>'fa-user bg-info text-white','system'=>'fa-gear bg-warning','activity'=>'fa-clock-rotate-left text-danger'];
?>
<div class="fade-in">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-bell text-primary me-2"></i>All Notifications <span class="badge rounded-pill bg-primary ms-2"><?= count($ns) ?></span></h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="markAll()"><i class="fas fa-check-double me-1"></i>Mark all read</button>
                <button class="btn btn-sm btn-danger" onclick="clearAll()"><i class="fas fa-trash-can me-1"></i>Clear all</button>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xl-3">
            <div class="card mb-4 sticky-top" style="top:90px;">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php
                            $types = ['all'=>['All',count($ns),'fa-bell']];
                            foreach ($ns as $n) { $types[$n['type']] = $types[$n['type']] ?? [ucfirst($n['type']),0,$typeIcon[$n['type']][0]??'fa-bell']; $types[$n['type']][1]++; }
                            foreach ($types as $k=>$t):
                        ?>
                            <a href="#" onclick="filterType('<?= $k ?>',event)" class="nf list-group-item list-group-item-action d-flex justify-content-between align-items-center border-0 py-3 px-4" data-type="<?= $k ?>" style="cursor:pointer;">
                                <div class="d-flex align-items-center gap-2"><i class="fas <?= $t[2] ?> text-primary"></i><span class="fw-semibold small"><?= $t[0] ?></span></div>
                                <span class="badge rounded-pill bg-light text-dark"><?= $t[1] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-9">
            <div id="notifContainer">
            <?php if (empty($ns)): ?>
                <div class="card"><div class="card-body text-center py-5"><i class="fas fa-inbox text-muted fa-3x mb-3 d-block opacity-25"></i><h5 class="text-muted">All caught up!</h5><p class="text-muted small mb-0">You don't have any notifications yet.</p></div></div>
            <?php else:
                foreach ($ns as $n):
                    $ic = $typeIcon[$n['type']] ?? ['fa-bell bg-primary text-white'];
                    $iconClass = is_array($ic) ? $ic[0] . ' ' . ($ic[1] ?? '') : $ic;
            ?>
                <div class="card mb-3 n-item border-0 shadow-sm overflow-hidden" data-type="<?= $n['type'] ?>">
                    <div class="row g-0">
                        <div class="col-auto d-flex align-items-stretch">
                            <div class="d-flex align-items-center justify-content-center h-100 px-4" style="background: linear-gradient(135deg, #6f42c1, #8b5cf6);color:#fff;">
                                <i class="fas <?= explode(' ', $iconClass)[0] ?>" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="mb-0 fw-bold"><?= e($n['title']) ?></h6>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i><?= date('M d, H:i', strtotime($n['created_at'])) ?></small>
                                </div>
                                <p class="mb-2 small text-muted"><?= e($n['message']) ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="tag tag-primary text-uppercase" style="font-size:0.7rem;"><?= e($n['type']) ?></span>
                                    <div>
                                        <?php if ($n['link']): ?><a href="<?= url($n['link']) ?>" class="btn btn-sm btn-outline-primary">View</a><?php endif; ?>
                                        <button class="btn btn-sm btn-light" onclick="delN(<?= $n['id'] ?>)"><i class="fas fa-trash text-danger"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
function filterType(t, e) { if (e) e.preventDefault(); document.querySelectorAll('.nf').forEach(el=>el.classList.remove('active','text-white','bg-primary')); event?.currentTarget?.classList?.add('active','bg-primary','text-white'); const cards = document.querySelectorAll('.n-item'); cards.forEach(c => { if(t==='all'||c.dataset.type===t){ c.style.display=''; } else { c.style.display='none'; } }); }
async function markAll() {
    try { const r = await apiAction({ action: 'notifications_read_all' }); if (r.success) toast('All notifications marked as read', 'success'); else toast(r.error || 'Failed', 'error'); }
    catch (e) { toast(e.message, 'error'); }
}
function clearAll() {
    Swal.fire({ title: 'Clear ALL notifications?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, clear them' })
        .then(async c => { if (!c.isConfirmed) return; try { const r = await apiAction({ action: 'notifications_clear' }); if (r.success) location.reload(); else Swal.fire('Error', r.error, 'error'); } catch (e) { Swal.fire('Error', e.message, 'error'); } });
}
function delN(id) { deleteItem('notification', id, 'this notification', () => location.reload()); }
document.querySelector('.nf[data-type="all"]')?.classList.add('bg-light');
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
