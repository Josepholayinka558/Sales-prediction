<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
require_once dirname(__FILE__, 2) . '/app/models/CoreModels.php';
require_once dirname(__FILE__, 2) . '/app/models/Sale.php';
auth()->authorize('view_notifications');
$currentPage = 'chat'; $pageTitle = 'Chat Assistant'; $pageIcon = 'fas fa-comments';
$breadcrumbs = [['label' => 'Home', 'url' => 'dashboard.php'], ['label' => 'Communication'], ['label' => 'Chat Assistant']];
$uid = (int)Session::userId();
$cm = new ChatMessage();
$saleModel = new Sale();
$orderModel = new Order();
$custModel = new Customer();
$prodModel = new Product();
$topCat = $saleModel->byCategory()[0] ?? null;
$topReg = $saleModel->byRegion()[0] ?? null;
$topCust = $saleModel->byCustomer(1)[0] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    header('Content-Type: application/json');
    if (!Csrf::verifyRequest()) { echo json_encode(['success'=>false,'error'=>'Invalid token']); exit; }
    $msg = trim($_POST['message'] ?? '');
    if (!$msg) { echo json_encode(['success'=>false,'error'=>'Empty message']); exit; }
    $resp = generateAIResponse($msg);
    db()->query("INSERT INTO chat_messages (user_id, message, message_type) VALUES (?, ?, 'user')", [$uid, $msg]);
    db()->query("INSERT INTO chat_messages (user_id, message, response, message_type) VALUES (?, ?, ?, 'assistant')", [$uid, $msg, $resp]);
    echo json_encode(['success'=>true,'response'=>$resp]);
    exit;
}
include dirname(__FILE__, 2) . '/app/views/partials/header.php';


function generateAIResponse($msg) {
    $m = strtolower($msg);
    $saleModel = new Sale();
    $orderModel = new Order();
    $custModel = new Customer();
    $prodModel = new Product();
    if (preg_match('/\b(hello|hi|hey)\b/', $m)) return "Hello! I'm your Sales AI assistant. Ask me about:\n• Revenue and sales\n• Top products/customers\n• Forecasts\n• Reports\nOr type 'help' for commands.";
    if (str_contains($m, 'help')) return "Available commands:\n• revenue / total sales\n• profit\n• orders count\n• customers\n• top products\n• top customers\n• forecast (days)\n• summary / dashboard\n• categories\n• regions";
    if (str_contains($m, 'revenue') || str_contains($m, 'sales') || str_contains($m, 'turnover')) {
        $rev = $saleModel->totalRevenue();
        $prof = $saleModel->totalProfit();
        $margin = $rev > 0 ? number_format($prof/$rev*100,1) : 0;
        return "📊 Total Revenue: **$".number_format($rev,2)."**\n\n💰 Profit: **$".number_format($prof,2)."**\n📈 Profit Margin: **{$margin}%**";
    }
    if (str_contains($m, 'profit')) {
        $p = $saleModel->profitLossSummary();
        return "💵 Profit Analysis:\n• Revenue: **$".number_format($p['revenue'],2)."**\n• Cost: **$".number_format($p['cost'],2)."**\n• Profit: **$".number_format($p['profit'],2)."**\n• Margin: **".number_format($p['margin'],1)."%**";
    }
    if (str_contains($m, 'order')) {
        $n = $orderModel->totalOrders();
        $aov = $saleModel->averageOrderValue();
        return "🧾 **Orders Summary:**\n• Total orders: **".number_format($n)."**\n• Avg order value: **$".number_format($aov,2)."**";
    }
    if (str_contains($m, 'customer')) {
        $n = $custModel->totalCustomers();
        $top = $saleModel->byCustomer(3);
        $resp = "👥 **Customers:** $n total\n\n🏆 Top 3:\n";
        foreach ($top as $i=>$c) $resp .= ($i+1).". **{$c['customer']}**: $".number_format($c['revenue'],0)." ({$c['orders']} orders)\n";
        return $resp;
    }
    if (str_contains($m, 'product')) {
        $n = $prodModel->totalProducts();
        $top = $saleModel->topProducts(5);
        $resp = "📦 **Products:** $n total\n\n🔥 Top 5 by revenue:\n";
        foreach ($top as $i=>$p) $resp .= ($i+1).". **".limit_text($p['product'],40)."**: $".number_format($p['revenue'],0)."\n";
        return $resp;
    }
    if (str_contains($m, 'categor')) {
        $cats = $saleModel->byCategory();
        $resp = "📂 **Sales by Category:**\n";
        foreach ($cats as $c) $resp .= "• **{$c['category']}**: $".number_format($c['revenue'],0)." (".number_format($c['revenue']/max(1,array_sum(array_column($cats,'revenue')))*100,1)."%)\n";
        return $resp;
    }
    if (str_contains($m, 'region')) {
        $rr = $saleModel->byRegion();
        $resp = "🗺️ **Sales by Region:**\n";
        foreach ($rr as $r) $resp .= "• **{$r['region']}**: $".number_format($r['revenue'],0)."\n";
        return $resp;
    }
    if (str_contains($m, 'summary') || str_contains($m, 'dashboard') || str_contains($m, 'overview')) {
        $rev = $saleModel->totalRevenue();
        $prof = $saleModel->totalProfit();
        $ord = $orderModel->totalOrders();
        $cust = $custModel->totalCustomers();
        $prod = $prodModel->totalProducts();
        return "📊 **Quick Dashboard Summary:**\n\n💰 Revenue: **$".number_format($rev,2)."**\n💵 Profit: **$".number_format($prof,2)."**\n🧾 Orders: **".number_format($ord)."**\n👥 Customers: **".number_format($cust)."**\n📦 Products: **".number_format($prod)."**";
    }
    if (str_contains($m, 'forecast') || str_contains($m, 'predict') || str_contains($m, 'next year') || str_contains($m, 'next month')) {
        require_once dirname(__FILE__, 2) . '/app/helpers/SalesPredictor.php';
        $sp = new SalesPredictor();
        $opt = $sp->options();
        if (!$opt['has_data']) return "I need sales data first. Import a dataset, then ask me again.";
        // a year mentioned in the question, else the first year that still needs predicting
        $yr = preg_match('/\b(20\d{2})\b/', $msg, $ym) ? (int)$ym[1] : (str_contains($m, 'next year') ? (int)$opt['data_last_year'] + 1 : (int)substr($opt['range']['max'], 0, 4) + ((int)substr($opt['range']['max'], 5, 2) === 12 ? 1 : 0));
        $r = $sp->predictYear($yr);
        if (!$r['ok']) return $r['error'];
        $s = $r['summary'];
        $txt = "🔮 **Prediction for {$yr} (all products):**\n\n• Expected sales: **$" . number_format($s['total_revenue'], 0) . "**\n• Likely range: $" . number_format($s['low_revenue'], 0) . " – $" . number_format($s['high_revenue'], 0) . "\n• Expected units: **" . number_format($s['total_units'], 0) . "**\n• Based on " . $r['model']['months_of_history'] . " months of history (" . $r['model']['method'] . ")\n";
        if ($r['model']['backtest']) $txt .= "• Back-test accuracy: " . $r['model']['backtest']['accuracy_pct'] . "%\n";
        return $txt . "\n💡 Open **Sales Prediction** in the menu to pick a specific product and year.";
    }
    return "I understand your question about: **$msg**\n\nTry keywords: revenue, profit, orders, customers, products, categories, regions, forecast, or summary.\n\nOr click the Forecast menu for advanced predictions!";
}
?>
<div class="fade-in">
    <div class="row g-4" style="height: calc(100vh - 180px);">
        <div class="col-xl-9">
            <div class="card h-100 d-flex flex-column">
                <div class="card-header py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="position-relative">
                            <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width:48px;height:48px;background:linear-gradient(135deg,#6f42c1,#d4af37);font-size:1.1rem;">AI</div>
                            <span class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-white" style="width:12px;height:12px;"></span>
                        </div>
                        <div><h5 class="mb-0 fw-bold">Sales Assistant AI</h5><small class="text-success"><i class="fas fa-circle fa-xs me-1"></i>Online - Ready to help</small></div>
                    </div>
                </div>
                <div class="flex-grow-1 overflow-auto p-4" id="chatBody" style="background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);">
                    <?php
                        $hist = $cm->userHistory($uid, 50);
                        foreach ($hist as $h):
                            if ($h['message_type'] === 'user'):
                    ?>
                        <div class="d-flex justify-content-end mb-4"><div class="me-3 max-w-75" style="max-width:75%;">
                            <div class="bg-primary text-white p-3 rounded-4 rounded-tr-sm shadow-sm" style="word-wrap: break-word;"><?= nl2br(e($h['message'])) ?></div>
                            <div class="small text-muted mt-1 text-end">You · <?= date('H:i', strtotime($h['created_at'])) ?></div>
                        </div><div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;background:linear-gradient(135deg,#d4af37,#6f42c1);font-size:0.8rem;"><?= strtoupper(substr(Session::get('user_name','U'),0,1)) ?></div></div>
                    <?php else: ?>
                        <div class="d-flex justify-content-start mb-4"><div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;background:linear-gradient(135deg,#6f42c1,#8b5cf6);font-size:0.8rem;">AI</div><div class="ms-3 max-w-75" style="max-width:75%;">
                            <div class="bg-white border border-secondary-subtle p-3 rounded-4 rounded-tl-sm shadow-sm" style="word-wrap: break-word;"><?= nl2br(e($h['response'] ?? $h['message'])) ?></div>
                            <div class="small text-muted mt-1">Assistant · <?= date('H:i', strtotime($h['created_at'])) ?></div>
                        </div></div>
                    <?php endif; endforeach;
                        if (empty($hist)):
                    ?>
                        <div class="text-center py-5">
                            <div class="rounded-4 mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:80px;height:80px;background:linear-gradient(135deg,#6f42c1,#d4af37);"><i class="fas fa-comments text-white fa-2xl"></i></div>
                            <h5 class="fw-bold mb-2">Welcome to Sales Assistant AI!</h5>
                            <p class="text-muted small mb-4">Ask me anything about your sales data.</p>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <button class="btn btn-outline-primary btn-sm" onclick="quickAsk('What is our total revenue?')"><i class="fas fa-dollar-sign me-1"></i>Total Revenue</button>
                                <button class="btn btn-outline-primary btn-sm" onclick="quickAsk('Show me top products')"><i class="fas fa-box me-1"></i>Top Products</button>
                                <button class="btn btn-outline-primary btn-sm" onclick="quickAsk('Give me a summary')"><i class="fas fa-gauge me-1"></i>Dashboard Summary</button>
                                <button class="btn btn-outline-primary btn-sm" onclick="quickAsk('Forecast for next month')"><i class="fas fa-bullseye me-1"></i>30-Day Forecast</button>
                                <button class="btn btn-outline-primary btn-sm" onclick="quickAsk('Sales by category')"><i class="fas fa-chart-pie me-1"></i>By Category</button>
                                <button class="btn btn-outline-primary btn-sm" onclick="quickAsk('Top customers')"><i class="fas fa-trophy me-1"></i>Top Customers</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body py-3 border-top">
                    <form id="chatForm" onsubmit="sendChat(event)">
                        <div class="input-group gap-2">
                            <input type="text" class="form-control rounded-4" id="chatInput" placeholder="Ask about revenue, products, customers, forecasts..." autocomplete="off">
                            <button class="btn btn-primary rounded-4 px-4" type="submit"><i class="fas fa-paper-plane me-2"></i>Send</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card mb-3"><div class="card-header"><h6 class="fw-bold mb-0"><i class="fas fa-bolt text-accent me-2"></i>Quick Actions</h6></div><div class="card-body p-2">
                <?php $quick = [
                    ['icon'=>'fa-dollar-sign','label'=>'Revenue & Profit','q'=>'What is the total revenue and profit?'],
                    ['icon'=>'fa-trophy','label'=>'Top Products','q'=>'Show top products by revenue'],
                    ['icon'=>'fa-users','label'=>'Top Customers','q'=>'Who are our top customers?'],
                    ['icon'=>'fa-map','label'=>'By Region','q'=>'Show sales breakdown by region'],
                    ['icon'=>'fa-layer-group','label'=>'By Category','q'=>'Sales by category breakdown'],
                    ['icon'=>'fa-gauge-high','label'=>'Full Summary','q'=>'Give me a full dashboard summary'],
                    ['icon'=>'fa-bullseye','label'=>'30-Day Forecast','q'=>'What is the forecast for next 30 days?'],
                    ['icon'=>'fa-file','label'=>'Reports Help','q'=>'How do I generate reports?'],
                ];
                foreach ($quick as $qr): ?>
                    <button class="btn btn-light w-100 text-start mb-1 rounded-3 py-2" onclick="quickAsk('<?= e($qr['q']) ?>')"><i class="fas <?= $qr['icon'] ?> text-primary me-2"></i><small class="fw-semibold"><?= $qr['label'] ?></small></button>
                <?php endforeach; ?>
            </div></div>
            <div class="card"><div class="card-header"><h6 class="fw-bold mb-0"><i class="fas fa-lightbulb text-warning me-2"></i>Insights</h6></div><div class="card-body"><small class="text-muted mb-2 d-block">Based on current dataset:</small>
                <ul class="list-group list-group-flush px-0" style="font-size:0.85rem;">
                    <?php if ($topCat): ?><li class="list-group-item px-0"><i class="fas fa-star text-primary me-2"></i>Top category: <strong><?= e($topCat['category']) ?></strong></li><?php endif; ?>
                    <?php if ($topReg): ?><li class="list-group-item px-0"><i class="fas fa-location-dot text-success me-2"></i>Top region: <strong><?= e($topReg['region']) ?></strong></li><?php endif; ?>
                    <?php if ($topCust): ?><li class="list-group-item px-0"><i class="fas fa-user-tie text-accent me-2"></i>Top customer: <strong><?= e($topCust['customer']) ?></strong></li><?php endif; ?>
                    <li class="list-group-item px-0"><i class="fas fa-chart-line text-info me-2"></i>Ask about <strong>forecast</strong> for predictions.</li>
                </ul>
            </div></div>
        </div>
    </div>
</div>
<script>
const cb = document.getElementById('chatBody');
cb.scrollTop = cb.scrollHeight;

function quickAsk(q) { document.getElementById('chatInput').value = q; sendChat({preventDefault:()=>{}}); }

async function sendChat(e) {
    e.preventDefault();
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg) return;
    const time = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    const init = '<?= strtoupper(substr(Session::get('user_name','U'),0,1)) ?>';
    cb.insertAdjacentHTML('beforeend',
        `<div class="d-flex justify-content-end mb-4"><div class="me-3" style="max-width:75%;"><div class="bg-primary text-white p-3 rounded-4 rounded-tr-sm shadow-sm">${escapeHtml(msg)}</div><div class="small text-muted mt-1 text-end">You · ${time}</div></div><div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;background:linear-gradient(135deg,#d4af37,#6f42c1);font-size:0.8rem;">${init}</div></div>`);
    input.value = '';
    cb.scrollTop = cb.scrollHeight;
    const typerId = 'typer-' + Date.now();
    cb.insertAdjacentHTML('beforeend', `<div class="d-flex justify-content-start mb-4" id="${typerId}"><div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;background:linear-gradient(135deg,#6f42c1,#8b5cf6);font-size:0.8rem;">AI</div><div class="ms-3"><div class="bg-white border p-3 rounded-4 rounded-tl-sm shadow-sm d-flex gap-1 align-items-center"><span class="spinner-grow spinner-grow-sm text-primary"></span><span class="spinner-grow spinner-grow-sm text-primary" style="animation-delay:0.2s;"></span><span class="spinner-grow spinner-grow-sm text-primary" style="animation-delay:0.4s;"></span></div></div></div>`);
    cb.scrollTop = cb.scrollHeight;
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('message', msg);
    fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
    try {
        const res = await fetch('', { method:'POST', body: fd });
        const data = await res.json();
        const resp = data.response || 'Sorry, I could not process that.';
        document.getElementById(typerId).remove();
        const now = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        cb.insertAdjacentHTML('beforeend', `<div class="d-flex justify-content-start mb-4"><div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;background:linear-gradient(135deg,#6f42c1,#8b5cf6);font-size:0.8rem;">AI</div><div class="ms-3" style="max-width:75%;"><div class="bg-white border border-secondary-subtle p-3 rounded-4 rounded-tl-sm shadow-sm" style="white-space:pre-wrap;word-wrap:break-word;">${escapeHtml(resp)}</div><div class="small text-muted mt-1">Assistant · ${now}</div></div></div>`);
        cb.scrollTop = cb.scrollHeight;
    } catch(err) {
        document.getElementById(typerId).remove();
        toast('Error sending message','error');
    }
}
function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
document.getElementById('chatInput').addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(e); }});
</script>
<?php include dirname(__FILE__, 2) . '/app/views/partials/footer.php'; ?>
