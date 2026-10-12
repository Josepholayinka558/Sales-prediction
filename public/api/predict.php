<?php
require_once dirname(__FILE__, 3) . '/app/bootstrap.php';
require_once dirname(__FILE__, 3) . '/app/helpers/SalesPredictor.php';

header('Content-Type: application/json');

$sendJson = function ($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
};

try {
    if (!auth()->check()) $sendJson(['success' => false, 'error' => 'Please log in again.'], 401);
    if (!auth()->hasPermission('view_forecasts') && !auth()->hasPermission('create_forecasts')) {
        $sendJson(['success' => false, 'error' => 'You do not have permission to view predictions.'], 403);
    }

    $p = new SalesPredictor();
    $action = $_GET['action'] ?? 'options';

    switch ($action) {
        case 'options':
            $sendJson(['success' => true, 'data' => $p->options()]);

        case 'predict':
            $year = (int)($_GET['year'] ?? date('Y'));
            if ($year < 1990 || $year > 2100) $sendJson(['success' => false, 'error' => 'Please choose a valid year.'], 400);
            $pid = ($_GET['product_id'] ?? '') !== '' ? (int)$_GET['product_id'] : null;
            $cid = ($_GET['category_id'] ?? '') !== '' ? (int)$_GET['category_id'] : null;
            $res = $p->predictYear($year, $pid, $cid);
            if (!$res['ok']) $sendJson(['success' => false, 'error' => $res['error']], 200);
            // label of what was predicted
            $label = 'All products (store total)';
            if ($pid) { $r = db()->query("SELECT name FROM products WHERE id = ?", [$pid])->fetch(); $label = $r['name'] ?? 'Product'; }
            elseif ($cid) { $r = db()->query("SELECT name FROM categories WHERE id = ?", [$cid])->fetch(); $label = 'Category: ' . ($r['name'] ?? ''); }
            $res['label'] = $label;
            auth()->logActivity('predict', "Sales prediction viewed: $label, $year");
            $sendJson(['success' => true, 'data' => $res]);

        case 'all_products':
            $year = (int)($_GET['year'] ?? date('Y'));
            $res = $p->predictAllProducts($year);
            if (!$res['ok']) $sendJson(['success' => false, 'error' => $res['error']], 200);
            $sendJson(['success' => true, 'data' => $res]);

        default:
            $sendJson(['success' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    $logDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'error-' . date('Y-m-d') . '.log', date('Y-m-d H:i:s') . ' | [predict.php] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL, FILE_APPEND);
    $sendJson(['success' => false, 'error' => $e->getMessage()], 500);
}
