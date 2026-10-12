<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
require_once dirname(__FILE__, 2) . '/app/models/User.php';
auth()->authorize('all');
$currentPage = 'users'; $pageTitle = 'User Management'; $pageIcon = 'fas fa-shield-halved';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Settings'], ['label' => 'Users']];
$um = new User();
$roles = db()->query("SELECT * FROM roles")->fetchAll();
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<div class="fade-in">
    <div class="row g-4 mb-4">
        <?php $roleCounts = []; foreach ($roles as $r): $roleCounts[$r['id']] = (int)db()->query("SELECT COUNT(*) FROM users WHERE role_id = ?", [$r['id']])->fetchColumn(); ?>
            <div class="col-md-4"><div class="kpi-card revenue" style="border-left-color: <?= ['#6f42c1','#06b6d4','#d4af37'][$r['id']-1] ?? '#6f42c1' ?>;">
                <div class="kpi-icon" style="background:rgba(111,66,193,0.1);color:#6f42c1;"><i class="fas <?= ['fa-crown','fa-chart-bar','fa-user-tie'][$r['id']-1] ?? 'fa-user' ?>"></i></div>
                <div class="kpi-value"><?= format_number($roleCounts[$r['id']]) ?></div><div class="kpi-label"><?= e($r['name']) ?>s</div>
            </div></div>
        <?php endforeach; ?>
    </div>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-user-plus text-primary me-2"></i>Add New User</h5>
        </div>
        <div class="card-body">
            <form id="addUserForm" onsubmit="addUser(event)">
                <div class="row g-3">
                    <div class="col-md-2"><input type="text" id="u-username" class="form-control" placeholder="Username" required></div>
                    <div class="col-md-3"><input type="email" id="u-email" class="form-control" placeholder="Email address" required></div>
                    <div class="col-md-2"><input type="text" id="u-fname" class="form-control" placeholder="First name" required></div>
                    <div class="col-md-2"><input type="text" id="u-lname" class="form-control" placeholder="Last name" required></div>
                    <div class="col-md-2"><select id="u-role" class="form-select" required><?php foreach($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="fas fa-plus"></i></button></div>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5><i class="fas fa-users text-accent me-2"></i>All Users</h5>
            <select id="u-filter-role" class="form-select form-select-sm" style="width:auto;"><option value="">All Roles</option><?php foreach($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable mb-0" id="uTable">
                    <thead><tr>
                        <th></th><th>User</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Joined</th><th></th>
                    </tr></thead>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
let udt;
function loadU() {
    if (udt) udt.destroy();
    udt = $('#uTable').DataTable({ processing: true, serverSide: true, pageLength: 25, lengthMenu: [10, 25, 50, 100],
        ajax: { url: BASE_URL+'/api/table.php?type=users', data: d => d.role = document.getElementById('u-filter-role').value, dataSrc: 'data' },
        columns: [
            { data: null, orderable:false, width:'50px', render: (d,t,r) => { const n = ((r.first_name||'U')[0]+(r.last_name||'')[0]).toUpperCase(); return `<div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;background:linear-gradient(135deg,#6f42c1,#d4af37);font-size:0.85rem;">${n}</div>`; }},
            { data: null, render: (d,t,r) => `<div class="fw-semibold">${e(r.first_name+' '+r.last_name)}</div>` },
            { data: 'username', render: v => `<span class="tag tag-info">${e(v)}</span>` },
            { data: 'email', render: v => `<a href="mailto:${e(v)}" class="text-decoration-none">${e(v)}</a>` },
            { data: 'role_name', render: v => { const c = {Administrator:'tag-danger',Analyst:'tag-info',Manager:'tag-accent'}[v] || 'tag-primary'; return `<span class="tag ${c}">${e(v||'User')}</span>`; }},
            { data: 'status', render: v => `<span class="tag ${v==='active'?'tag-success':'tag-danger'} text-uppercase">${e(v)}</span>` },
            { data: 'last_login', render: v => v ? formatDate(v) : '<span class="text-muted small">Never</span>' },
            { data: 'created_at', render: v => formatDate(v) },
            { data: 'id', orderable:false, width:'100px', className:'text-end', render: id => `<div class="btn-group">
                <button class="btn btn-sm btn-light" onclick="editU(${id})"><i class="fas fa-pencil"></i></button>
                <button class="btn btn-sm btn-light text-primary" onclick="resetPwd(${id})"><i class="fas fa-key"></i></button>
                <button class="btn btn-sm btn-light text-danger" onclick="delU(${id})"><i class="fas fa-trash"></i></button>
            </div>` }
        ], order: [[7,'desc']]
    });
}
loadU();
document.getElementById('u-filter-role').addEventListener('change', loadU);
async function addUser(ev) {
    ev.preventDefault();
    loadingSwal('Creating user...');
    try {
        const r = await apiAction({ action: 'user_create',
            username: document.getElementById('u-username').value.trim(), email: document.getElementById('u-email').value.trim(),
            first_name: document.getElementById('u-fname').value.trim(), last_name: document.getElementById('u-lname').value.trim(),
            role_id: +document.getElementById('u-role').value });
        Swal.close();
        if (r.success) {
            document.getElementById('addUserForm').reset();
            loadU();
            Swal.fire({ icon: 'success', title: 'User created', html: 'Give them this temporary password (shown only once):<br><code style="font-size:1.2rem;user-select:all">' + e(r.temp_password) + '</code>' });
        } else Swal.fire('Could not create user', r.error || 'Please check the details.', 'error');
    } catch (err) { Swal.close(); Swal.fire('Could not create user', err.message, 'error'); }
}
async function editU(id) {
    try {
        const r = await apiAction({ action: 'user_get', id });
        if (!r.success) return Swal.fire('Error', r.error, 'error');
        const u = r.user;
        const roleOpts = r.roles.map(x => `<option value="${x.id}" ${x.id == u.role_id ? 'selected' : ''}>${e(x.name)}</option>`).join('');
        const res = await Swal.fire({ title: 'Edit ' + e(u.username), width: 520, showCancelButton: true, confirmButtonText: 'Save changes',
            html: `<div class="text-start">
                <label class="form-label small mb-1">First name</label><input id="eu-fn" class="form-control mb-2" value="${e(u.first_name)}">
                <label class="form-label small mb-1">Last name</label><input id="eu-ln" class="form-control mb-2" value="${e(u.last_name)}">
                <label class="form-label small mb-1">Email</label><input id="eu-em" type="email" class="form-control mb-2" value="${e(u.email)}">
                <label class="form-label small mb-1">Role</label><select id="eu-role" class="form-select mb-2">${roleOpts}</select>
                <label class="form-label small mb-1">Status</label><select id="eu-st" class="form-select">${['active','inactive','suspended'].map(x => `<option ${x === u.status ? 'selected' : ''}>${x}</option>`).join('')}</select></div>`,
            preConfirm: () => ({ first_name: document.getElementById('eu-fn').value, last_name: document.getElementById('eu-ln').value, email: document.getElementById('eu-em').value,
                role_id: +document.getElementById('eu-role').value, status: document.getElementById('eu-st').value }) });
        if (!res.isConfirmed) return;
        const sv = await apiAction(Object.assign({ action: 'user_update', id }, res.value));
        if (sv.success) { toast('User updated', 'success'); loadU(); } else Swal.fire('Could not save', sv.error, 'error');
    } catch (err) { Swal.fire('Error', err.message, 'error'); }
}
async function resetPwd(id) {
    const c = await Swal.fire({ title: 'Reset password?', text: 'A new temporary password will be generated.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Reset' });
    if (!c.isConfirmed) return;
    try {
        const r = await apiAction({ action: 'user_reset_password', id });
        if (r.success) Swal.fire({ icon: 'success', title: 'Password reset', html: 'New temporary password (shown only once):<br><code style="font-size:1.2rem;user-select:all">' + e(r.temp_password) + '</code>' });
        else Swal.fire('Error', r.error, 'error');
    } catch (err) { Swal.fire('Error', err.message, 'error'); }
}
function delU(id) {
    if (id === <?= (int)Session::userId() ?>) { Swal.fire('Cannot delete', 'You cannot delete your own account.', 'warning'); return; }
    deleteItem('user', id, 'user #' + id, loadU);
}
function e(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
