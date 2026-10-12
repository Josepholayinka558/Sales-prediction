<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
auth()->authorize('manage_profile');
$currentPage = 'profile'; $pageTitle = 'My Profile'; $pageIcon = 'fas fa-user-circle';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Settings'], ['label' => 'Profile']];
$user = auth()->user();
$userId = (int)Session::userId();
$um = new User();
$alModel = new ActivityLog();
$activities = $alModel->byUser($userId, 15);
$notifCount = (int)db()->query("SELECT COUNT(*) FROM notifications WHERE user_id = ?", [$userId])->fetchColumn();
$ordersCount = 0; $totalSpent = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!Csrf::verifyRequest()) { echo json_encode(['success'=>false,'error'=>'Invalid token']); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $fn = clean_input($_POST['first_name'] ?? '');
        $ln = clean_input($_POST['last_name'] ?? '');
        $em = clean_input($_POST['email'] ?? '');
        $ph = clean_input($_POST['phone'] ?? '');
        $un = clean_input($_POST['username'] ?? '');
        if (!validate_string($fn,1,100) || !validate_string($ln,1,100)) { echo json_encode(['success'=>false,'error'=>'Invalid name']); exit; }
        if (!validate_email($em)) { echo json_encode(['success'=>false,'error'=>'Invalid email']); exit; }
        db()->query("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, username = ? WHERE id = ?", [$fn,$ln,$em,$ph,$un,$userId]);
        Session::set('user_name', $fn . ' ' . $ln);
        Session::set('user_email', $em);
        auth()->logActivity('update', 'Updated profile information');
        echo json_encode(['success'=>true,'message'=>'Profile updated successfully!']);
        exit;
    }
    if ($action === 'change_password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $u = db()->query("SELECT password FROM users WHERE id = ? LIMIT 1", [$userId])->fetch();
        if (!$u || !password_verify($cur, $u['password'])) { echo json_encode(['success'=>false,'error'=>'Current password is incorrect.']); exit; }
        if (strlen($new) < PASSWORD_MIN_LENGTH) { echo json_encode(['success'=>false,'error'=>'New password must be at least '.PASSWORD_MIN_LENGTH.' characters.']); exit; }
        if ($new !== $conf) { echo json_encode(['success'=>false,'error'=>'Passwords do not match.']); exit; }
        db()->query("UPDATE users SET password = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $userId]);
        auth()->logActivity('update', 'Changed account password');
        echo json_encode(['success'=>true,'message'=>'Password changed successfully!']);
        exit;
    }
}
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
$initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
?>
<div class="fade-in">
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card mb-4 text-center overflow-hidden border-0 shadow">
                <div style="height:120px;background:linear-gradient(135deg,#6f42c1,#8b5cf6,#d4af37);"></div>
                <div class="card-body pt-0 px-4 pb-4">
                    <div class="mx-auto mb-3 mt-n5 rounded-4 d-flex align-items-center justify-content-center text-white fw-bold border border-white" style="width:96px;height:96px;background:linear-gradient(135deg,#d4af37,#6f42c1);font-size:2.25rem;box-shadow:0 10px 30px rgba(111,66,193,0.4);border-width:4px !important;"><?= $initials ?></div>
                    <h4 class="fw-bold mb-1"><?= e($user['first_name'].' '.$user['last_name']) ?></h4>
                    <span class="tag tag-primary mb-2"><?= e($user['role_name'] ?? 'User') ?></span>
                    <p class="text-muted small mb-3"><i class="fas fa-envelope me-1"></i><?= e($user['email']) ?></p>
                    <div class="row g-3 text-center py-3 border-top">
                        <div class="col-4"><div class="fw-bold text-primary fs-5"><?= format_number($notifCount) ?></div><small class="text-muted">Notifications</small></div>
                        <div class="col-4"><div class="fw-bold text-accent fs-5"><?= format_number(count($activities)) ?></div><small class="text-muted">Activities</small></div>
                        <div class="col-4"><div class="fw-bold text-success fs-5"><?= $user['last_login']?format_date($user['last_login'],'M d'):'Never' ?></div><small class="text-muted">Last Login</small></div>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom"><h5 class="mb-0 fw-bold"><i class="fas fa-key text-accent me-2"></i>Change Password</h5></div>
                <div class="card-body">
                    <form id="pwdForm" onsubmit="changePwd(event)">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required placeholder="••••••••"></div>
                        <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" id="np" class="form-control" required minlength="8" placeholder="••••••••"></div>
                        <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" id="cp" class="form-control" required minlength="8" placeholder="••••••••"></div>
                        <div class="d-grid"><button type="submit" class="btn btn-primary"><i class="fas fa-lock me-2"></i>Update Password</button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-user-pen text-primary me-2"></i>Edit Profile Information</h5>
                </div>
                <div class="card-body">
                    <form id="profileForm" onsubmit="updateProfile(event)">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" value="<?= e($user['first_name']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" value="<?= e($user['last_name']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Username</label><input type="text" name="username" class="form-control" value="<?= e($user['username']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Role</label><input type="text" class="form-control" value="<?= e($user['role_name'] ?? 'User') ?>" disabled></div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="reset" class="btn btn-light"><i class="fas fa-rotate me-2"></i>Reset</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-clock-rotate-left text-danger me-2"></i>Recent Activity</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                        <table class="table mb-0 align-middle">
                            <thead class="sticky-top bg-white"><tr><th style="width:40px;"></th><th>Action</th><th>Description</th><th>IP Address</th><th>Time</th></tr></thead>
                            <tbody>
                                <?php if (empty($activities)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted small">No activity yet.</td></tr>
                                <?php else:
                                    $aIcons = ['login'=>'fa-right-to-bracket text-success','logout'=>'fa-right-from-bracket text-secondary','create'=>'fa-plus text-primary','update'=>'fa-pencil text-warning','delete'=>'fa-trash text-danger','import'=>'fa-database text-info','export'=>'fa-file-export text-info','forecast'=>'fa-chart-line text-primary'];
                                    foreach($activities as $a):
                                        $ic = $aIcons[$a['action']] ?? ['fa-bolt text-primary'];
                                        $icon = is_array($ic) ? $ic[0] : $ic;
                                        $cl = is_array($ic) ? '' : (explode(' ', $aIcons[$a['action']] ?? 'text-primary ')[1] ?? 'text-primary');
                                ?>
                                    <tr>
                                        <td><div class="rounded-3 d-flex align-items-center justify-content-center" style="width:34px;height:34px;background:rgba(111,66,193,0.08);"><i class="fas <?= $icon ?> <?= $cl ?>"></i></div></td>
                                        <td><span class="fw-semibold text-capitalize small"><?= e(str_replace('_',' ',$a['action'])) ?></span></td>
                                        <td class="small text-muted"><?= e($a['description'] ?? '-') ?></td>
                                        <td class="small"><code><?= e($a['ip_address'] ?? '-') ?></code></td>
                                        <td class="small text-muted"><?= format_date($a['created_at'], 'M d, H:i') ?></td>
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
function updateProfile(e) {
    e.preventDefault();
    loadingSwal('Saving profile...');
    const fd = new FormData(document.getElementById('profileForm'));
    ajaxRequest('', 'POST', fd).then(r => {
        Swal.close();
        if (r.success) { toast(r.message,'success'); setTimeout(()=>location.reload(), 1000); }
        else Swal.fire('Error', r.error || 'Failed to save','error');
    }).catch(err => { Swal.close(); Swal.fire('Error', String(err),'error'); });
}
function changePwd(e) {
    e.preventDefault();
    if (document.getElementById('np').value !== document.getElementById('cp').value) { toast('Passwords do not match','warning'); return; }
    loadingSwal('Updating password...');
    const fd = new FormData(document.getElementById('pwdForm'));
    ajaxRequest('', 'POST', fd).then(r => {
        Swal.close();
        if (r.success) { toast(r.message,'success'); document.getElementById('pwdForm').reset(); }
        else Swal.fire('Error', r.error || 'Failed','error');
    }).catch(err => { Swal.close(); Swal.fire('Error', String(err),'error'); });
}
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
