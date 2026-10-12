<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
auth()->authorize('view_dashboard');
$currentPage = 'marketing'; $pageTitle = 'Marketing Campaigns'; $pageIcon = 'fas fa-bullhorn';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Growth'], ['label' => 'Marketing']];
$saleModel = new Sale();
$topCats = $saleModel->byCategory();
$topRegs = $saleModel->byRegion();
include dirname(__FILE__, 2) . '/app/views/partials/header.php';
?>
<div class="fade-in">
    <div class="promo-card mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h4><i class="fas fa-rocket me-2"></i>Create a promotional campaign to attract more customers.</h4>
                <p>Design targeted campaigns, boost engagement, and increase your revenue with our smart marketing tools.</p>
                <button class="promo-btn" data-bs-toggle="modal" data-bs-target="#campaignModal"><i class="fas fa-plus-circle"></i>New Campaign</button>
            </div>
            <svg class="promo-art" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="pg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F98B2A" stop-opacity="0.25"/><stop offset="1" stop-color="#FFD369" stop-opacity="0.5"/></linearGradient></defs>
                <circle cx="150" cy="140" r="95" fill="url(#pg)"/>
                <path d="M70 70 L130 40 L145 85 L95 115 Z" stroke="#fff" stroke-width="3" fill="#fff" fill-opacity="0.25"/>
                <circle cx="105" cy="60" r="10" fill="#fff" fill-opacity="0.9"/>
                <path d="M160 95 L180 85 L175 110 Z" fill="#fff" fill-opacity="0.85"/>
            </svg>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="kpi-card h-100">
                <div class="kpi-top">
                    <div class="kpi-icon-box c1"><i class="fas fa-bullhorn"></i></div>
                    <span class="kpi-change up"><i class="fas fa-arrow-up"></i> 12.5%</span>
                </div>
                <div class="kpi-label">Active Campaigns</div>
                <div class="kpi-value">8</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card h-100">
                <div class="kpi-top">
                    <div class="kpi-icon-box c2"><i class="fas fa-eye"></i></div>
                    <span class="kpi-change up"><i class="fas fa-arrow-up"></i> 8.4%</span>
                </div>
                <div class="kpi-label">Total Impressions</div>
                <div class="kpi-value">428K</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card h-100">
                <div class="kpi-top">
                    <div class="kpi-icon-box c3"><i class="fas fa-mouse-pointer"></i></div>
                    <span class="kpi-change up"><i class="fas fa-arrow-up"></i> 3.2%</span>
                </div>
                <div class="kpi-label">Click Rate (CTR)</div>
                <div class="kpi-value">5.84%</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card h-100">
                <div class="kpi-top">
                    <div class="kpi-icon-box c4"><i class="fas fa-coins"></i></div>
                    <span class="kpi-change up"><i class="fas fa-arrow-up"></i> 18.0%</span>
                </div>
                <div class="kpi-label">ROI</div>
                <div class="kpi-value">324%</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-calendar-star text-primary me-2"></i>Campaign Schedule</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#campaignModal"><i class="fas fa-plus me-1"></i>New</button>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead><tr>
                            <th>Campaign</th><th>Type</th><th>Channel</th><th class="text-end">Budget</th><th class="text-center">Status</th><th></th>
                        </tr></thead>
                        <tbody>
                            <?php
                            $camps = [
                                ['name'=>'Summer Mega Sale','type'=>'Discount','channel'=>'Email & SMS','budget'=>12500,'status'=>'running','progress'=>72],
                                ['name'=>'Holiday Loyalty Program','type'=>'Loyalty','channel'=>'Email','budget'=>8400,'status'=>'running','progress'=>45],
                                ['name'=>'New Arrivals Launch','type'=>'Promotion','channel'=>'Social','budget'=>6800,'status'=>'scheduled','progress'=>0],
                                ['name'=>'Abandoned Cart Recovery','type'=>'Remarketing','channel'=>'SMS','budget'=>3200,'status'=>'running','progress'=>88],
                                ['name'=>'Referral Rewards','type'=>'Referral','channel'=>'In-app','budget'=>4500,'status'=>'draft','progress'=>0],
                                ['name'=>'Black Friday Teaser','type'=>'Flash Sale','channel'=>'All','budget'=>22000,'status'=>'scheduled','progress'=>0],
                            ];
                            foreach ($camps as $c):
                                $stCls = $c['status']==='running' ? 'completed' : ($c['status']==='scheduled' ? 'shipped' : 'pending');
                                $icons = ['Discount'=>'fa-tags','Loyalty'=>'fa-gift','Promotion'=>'fa-bullhorn','Remarketing'=>'fa-rotate','Referral'=>'fa-users','Flash Sale'=>'fa-bolt'];
                            ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <div class="product-thumb t3"><i class="fas <?= $icons[$c['type']] ?? 'fa-bullhorn' ?>" style="color:#D26911;"></i></div>
                                        <div><div class="product-name"><?= $c['name'] ?></div><small class="text-muted-sm"><?= $c['progress'] ?>% engagement</small></div>
                                    </div>
                                </td>
                                <td><span class="badge rounded-pill" style="background:var(--primary-soft);color:var(--primary-dark);"><?= $c['type'] ?></span></td>
                                <td class="text-muted-sm"><i class="fas fa-share-nodes me-1 text-primary"></i><?= $c['channel'] ?></td>
                                <td class="text-end text-money">$<?= number_format($c['budget']) ?></td>
                                <td class="text-center"><span class="status-pill <?= $stCls ?>"><?= ucfirst($c['status']) ?></span></td>
                                <td><div class="dropdown"><button class="action-menu" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="return campAction(event, 'view', <?= json_encode($c['name']) ?>, <?= json_encode($c['type'] . ' via ' . $c['channel'] . ' — budget $' . number_format($c['budget']) . ' — ' . $c['status']) ?>)"><i class="fas fa-eye"></i> View</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="return campAction(event, 'edit')"><i class="fas fa-pen"></i> Edit</a></li>
                                        <li><a class="dropdown-item" href="<?= url('analytics.php') ?>"><i class="fas fa-chart-line"></i> Analytics</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="return campAction(event, 'archive')"><i class="fas fa-trash"></i> Archive</a></li>
                                    </ul>
                                </div></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header"><h5><i class="fas fa-users-viewfinder me-2" style="color:#F98B2A;"></i>Top Segments</h5></div>
                <div class="card-body">
                    <div class="mb-4">
                        <?php foreach ($topCats as $i=>$c): $total = array_sum(array_column($topCats,'revenue')); $pct = round($c['revenue']*100/max(1,$total)); ?>
                        <div class="bsp-item">
                            <div class="bsp-label"><span><?= e($c['category']) ?></span><span>$<?= number_format($c['revenue']) ?> · <?= $pct ?>%</span></div>
                            <div class="bsp-bar"><div style="width:<?= min($pct*1.2,100) ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <h6 class="fw-semibold mb-3"><i class="fas fa-map-location-dot text-info me-2"></i>Top Regions</h6>
                    <?php foreach ($topRegs as $i=>$r): $totalR = array_sum(array_column($topRegs,'revenue')); $pctR = round($r['revenue']*100/max(1,$totalR)); $colors = ['#F98B2A','#FFB545','#3b82f6','#10b981']; ?>
                    <div class="bsp-item">
                        <div class="bsp-label"><span><?= e($r['region']) ?></span><span class="text-muted-sm">$<?= number_format($r['revenue']) ?></span></div>
                        <div class="bsp-bar"><div style="width:<?= min($pctR*1.5,100) ?>%;background:<?= $colors[$i] ?? $colors[0] ?>;"></div></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="promo-card promo-blue">
                <h4><i class="fas fa-compass-drafting me-2"></i>Expand your catalog to boost customer interest.</h4>
                <p>Add new trending products, upsell complementary items, and watch your average order value grow.</p>
                <button class="promo-btn" onclick="location.href='<?= url('products.php') ?>'"><i class="fas fa-boxes-stacked"></i>Browse Products</button>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><h5><i class="fas fa-envelope-open-text text-primary me-2"></i>Email Template Gallery</h5></div>
                <div class="card-body"><div class="row g-3">
                    <?php $tpls = [['Welcome','Welcome new users with onboarding series','fa-hand','#F98B2A'],['Abandoned Cart','Recover 30%+ lost sales','fa-cart-shopping','#3b82f6'],['Flash Sale','Time-limited discount blasts','fa-bolt','#ef4444'],['Newsletter','Monthly product updates','fa-newspaper','#10b981']];
                    foreach ($tpls as $t): ?>
                    <div class="col-md-6">
                        <div class="p-3 rounded-4 h-100" style="background:linear-gradient(135deg, #fff 0%, #fafbff 100%);border:1px solid var(--border);cursor:pointer;" onmouseover="this.style.borderColor='rgba(249,139,42,0.3)'" onmouseout="this.style.borderColor='var(--border)'">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width:34px;height:34px;background:<?= $t[3] ?>;"><i class="fas <?= $t[2] ?>"></i></div>
                                <h6 class="mb-0 fw-bold"><?= $t[0] ?></h6>
                            </div>
                            <p class="text-muted-sm mb-0"><?= $t[1] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><h5><i class="fas fa-bullseye text-accent me-2"></i>Channels Performance</h5></div>
                <div class="card-body">
                    <canvas id="channelChart" style="height:260px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="campaignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-5 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0"><div>
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i>Create New Campaign</h5>
                <small class="text-muted">Set up your next promotion in under 2 minutes.</small>
            </div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Campaign Name</label><input type="text" class="form-control" placeholder="e.g. Spring Flash Sale 2025" value=""></div>
                    <div class="col-md-4"><label class="form-label">Type</label><select class="form-select"><option>Discount</option><option>Flash Sale</option><option>Loyalty</option><option>Referral</option><option>Remarketing</option></select></div>
                    <div class="col-md-6"><label class="form-label">Channel</label><select class="form-select"><option>Email & SMS</option><option>Email only</option><option>SMS only</option><option>Social media</option><option>All channels</option></select></div>
                    <div class="col-md-3"><label class="form-label">Budget ($)</label><input type="number" class="form-control" value="5000"></div>
                    <div class="col-md-3"><label class="form-label">Discount %</label><input type="number" class="form-control" value="15"></div>
                    <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">End Date</label><input type="date" class="form-control"></div>
                    <div class="col-md-12"><label class="form-label">Target Audience</label>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <span class="badge rounded-pill py-2 px-3" style="background:var(--primary-soft);color:var(--primary-dark);cursor:pointer;"><i class="fas fa-check me-1"></i>All Customers</span>
                            <span class="badge rounded-pill py-2 px-3 border" style="cursor:pointer;background:#fff;color:var(--text-muted);">VIP Only</span>
                            <span class="badge rounded-pill py-2 px-3 border" style="cursor:pointer;background:#fff;color:var(--text-muted);">New Users</span>
                            <span class="badge rounded-pill py-2 px-3 border" style="cursor:pointer;background:#fff;color:var(--text-muted);">Churn Risk</span>
                            <span class="badge rounded-pill py-2 px-3 border" style="cursor:pointer;background:#fff;color:var(--text-muted);">By Region</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Save as draft</button>
                <div class="d-flex gap-2"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="toast('Campaign scheduled! 🎉','success'); setTimeout(()=>document.querySelectorAll('[data-bs-dismiss]').forEach(b=>b.click()), 300);"><i class="fas fa-rocket me-1"></i>Launch Campaign</button></div>
            </div>
        </div>
    </div>
</div>

<script>
const cc = document.getElementById('channelChart');
if (typeof Chart !== 'undefined' && cc) {
    const C = chartColors;
    new Chart(cc, {
        type:'bar',
        data: { labels:['Email','SMS','Social','Push','Ads'], datasets:[
            { label:'Revenue ($)', data:[42500,24800,32100,18400,28900], backgroundColor: chartGradients(cc.getContext('2d'), C.primary+'cc', C.primary+'15'), borderRadius: 14, barThickness: 38, borderSkipped:false },
            { label:'Conversions', data:[1280,820,1045,510,920], backgroundColor: C.success+'cc', borderRadius: 14, barThickness: 38, borderSkipped:false }
        ]},
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{position:'top',align:'end',labels:{usePointStyle:true,padding:15}} },
            scales:{ x:{grid:{display:false}}, y:{grid:{color:'#f1f5f9'}} } }
    });
}
</script>
<script>
function campAction(ev, kind, name, details) {
    ev.preventDefault();
    if (kind === 'view') { Swal.fire({ title: name, html: '<p class="mb-2">' + details + '</p><p class="small text-muted mb-0">These campaigns are sample data for layout purposes. They are not stored in your database.</p>', confirmButtonText: 'Close' }); }
    else { Swal.fire({ icon: 'info', title: 'Sample campaigns', text: 'The marketing list is demo content. Saving, editing and archiving campaigns is not part of this version.', confirmButtonText: 'OK' }); }
    return false;
}
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
