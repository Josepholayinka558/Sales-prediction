        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.0/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
<script>
(function(){
    const cdn1 = 'https://cdn.jsdelivr.net/npm/sheetjs@0.18.5/dist/xlsx.full.min.js';
    const cdn2 = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
    function load(src) {
        return new Promise(function(res, rej){
            var s = document.createElement('script');
            s.src = src; s.async = false;
            s.onload = res; s.onerror = rej;
            document.head.appendChild(s);
        });
    }
    load(cdn1).catch(function(){ return load(cdn2); }).catch(function(){});
})();
</script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';


// ---- shared header helpers ----
function copyPageLink() {
    const url = window.location.href;
    const done = () => toast('Link copied to clipboard', 'success');
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url).then(done, () => window.prompt('Copy this link:', url)); }
    else { const t = document.createElement('textarea'); t.value = url; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (e) { window.prompt('Copy this link:', url); } t.remove(); }
}
async function markAllNotifRead(ev) {
    if (ev) ev.preventDefault();
    try {
        const r = await ajaxRequest(BASE_URL + '/api/actions.php', 'POST', { action: 'notifications_read_all', csrf_token: CSRF_TOKEN });
        if (r.success) { document.querySelectorAll('.notification-item.unread').forEach(n => n.classList.remove('unread')); document.querySelectorAll('.icon-btn .badge').forEach(b => b.remove()); toast('All notifications marked as read', 'success'); }
    } catch (e) { toast('Could not update notifications', 'error'); }
}
(function () {
    const gs = document.getElementById('globalSearch');
    if (!gs) return;
    gs.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && gs.value.trim()) window.location.href = BASE_URL + '/sales.php?q=' + encodeURIComponent(gs.value.trim());
    });
})();
// shared safe delete/view helpers used by list pages
async function apiAction(payload) {
    return ajaxRequest(BASE_URL + '/api/actions.php', 'POST', Object.assign({ csrf_token: CSRF_TOKEN }, payload));
}
async function viewItem(entity, id) {
    try {
        const r = await ajaxRequest(BASE_URL + '/api/actions.php?action=view&entity=' + entity + '&id=' + id);
        if (!r.success) return Swal.fire('Not found', r.error || 'Could not load details.', 'error');
        Swal.fire({ title: r.title, html: r.html, width: 560, confirmButtonText: 'Close' });
    } catch (e) { Swal.fire('Error', 'Could not load details: ' + e.message, 'error'); }
}
async function deleteItem(entity, id, label, onDone, extraWarning) {
    const c = await Swal.fire({ title: 'Delete ' + label + '?', text: extraWarning || 'This cannot be undone.', icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#ef4444', cancelButtonColor: '#64748b', confirmButtonText: 'Yes, delete it' });
    if (!c.isConfirmed) return;
    try {
        const r = await apiAction({ action: 'delete', entity, id });
        if (r.success) { toast(r.message || 'Deleted', 'success'); if (onDone) onDone(); }
        else Swal.fire('Could not delete', r.error || 'Something went wrong.', 'error');
    } catch (e) { Swal.fire('Could not delete', e.message, 'error'); }
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function ajaxRequest(url, method = 'GET', data = null, options = {}) {
    const headers = { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' };
    const fetchOptions = { method, headers, credentials: 'same-origin' };
    if (data && !(data instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(data);
    } else if (data) {
        fetchOptions.body = data;
    }
    return fetch(url, fetchOptions).then(async r => {
        const ct = r.headers.get('content-type') || '';
        if (!r.ok) {
            // surface the server's own message (e.g. "You do not have permission") instead of a bare status code
            let msg = 'Request failed (' + r.status + ')';
            if (r.status === 401) msg = 'Your session expired. Please log in again.';
            try { if (ct.includes('json')) { const j = await r.json(); if (j && j.error) msg = j.error; } } catch (e) {}
            throw new Error(msg);
        }
        return ct.includes('json') ? r.json() : r.text();
    });
}

function formatCurrency(n, symbol = '$') {
    return symbol + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(n) {
    return Number(n || 0).toLocaleString('en-US');
}

function formatPercentage(n, d = 1) {
    return Number(n || 0).toFixed(d) + '%';
}

function formatDate(str) {
    if (!str) return '';
    const d = new Date(str);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function confirmDelete(message = 'Are you sure you want to delete this?') {
    return Swal.fire({
        title: 'Confirm Deletion', text: message, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!', cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' }
    });
}

function toast(message, type = 'success') {
    const bg = { success: '#10b981', error: '#ef4444', info: '#3b82f6', warning: '#f59e0b' }[type] || '#10b981';
    Swal.fire({ toast: true, position: 'top-end', icon: type, title: message, showConfirmButton: false, timer: 3000, timerProgressBar: true, background: bg, color: '#fff', iconColor: '#fff' });
}

function loadingSwal(title = 'Processing...') {
    return Swal.fire({ title, html: '<div class="loading-spinner" style="width:48px;height:48px;border-width:4px;margin:1rem auto;"></div>', showConfirmButton: false, allowOutsideClick: false, backdrop: true });
}

Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = '#64748b';
const chartColors = { primary: '#6f42c1', accent: '#d4af37', success: '#10b981', danger: '#ef4444', info: '#3b82f6', warning: '#f59e0b', teal: '#0d9488', pink: '#ec4899', orange: '#f97316', purple2: '#8b5cf6', cyan: '#06b6d4', lime: '#84cc16' };
const chartGradients = (ctx, color1, color2) => { const g = ctx.createLinearGradient(0, 0, 0, 320); g.addColorStop(0, color1); g.addColorStop(1, color2); return g; };

function loadXLSXScript(src) {
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = () => resolve(XLSX);
        s.onerror = () => reject(new Error('CDN failed: ' + src));
        document.head.appendChild(s);
    });
}
async function ensureXLSX(timeoutMs = 30000) {
    if (typeof XLSX !== 'undefined') return XLSX;
    const existing = document.querySelector('script[data-xlsx-loader]');
    if (existing) {
        const started = Date.now();
        while (Date.now() - started < timeoutMs) {
            if (typeof XLSX !== 'undefined') return XLSX;
            await new Promise(r => setTimeout(r, 100));
        }
        throw new Error('XLSX library load timed out. Please refresh and try again.');
    }
    const cdnList = [
        'https://cdn.jsdelivr.net/npm/sheetjs@0.18.5/dist/xlsx.full.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js',
        'https://unpkg.com/xlsx@0.18.5/dist/xlsx.full.min.js'
    ];
    let lastErr = new Error('All XLSX CDN hosts failed. Please check your internet connection.');
    for (let i = 0; i < cdnList.length; i++) {
        try {
            const src = cdnList[i];
            const probe = document.createElement('img'); probe.style.display='none'; probe.src = src.replace(/\/[^\/]+$/, '/xlsx.svg').replace(/\.js$/, '.svg');
            const tag = document.createElement('script'); tag.setAttribute('data-xlsx-loader', String(i));
            const result = await Promise.race([
                loadXLSXScript(src),
                new Promise((_, rej) => setTimeout(() => rej(new Error('Timeout on ' + src)), timeoutMs / cdnList.length))
            ]);
            if (typeof XLSX !== 'undefined') return XLSX;
            return result;
        } catch(e) { lastErr = e; }
    }
    throw lastErr;
}

$(document).ready(function () {
    $('.datatable').each(function () {
        const customEmpty = $(this).attr('data-empty');
        const lang = { searchPlaceholder: 'Search...', search: '' };
        if (customEmpty) {
            lang.emptyTable = customEmpty;
            lang.zeroRecords = customEmpty;
        }
        $(this).DataTable({ pageLength: 25, lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]], language: lang, dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-end'f>>rt<'row align-items-center mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>", responsive: true });
    });
});
</script>
</body>
</html>
