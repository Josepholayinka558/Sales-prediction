<?php
require_once dirname(__FILE__, 3) . '/app/bootstrap.php';
require_once dirname(__FILE__, 3) . '/app/models/Sale.php';
require_once dirname(__FILE__, 3) . '/app/models/CoreModels.php';
require_once dirname(__FILE__, 3) . '/app/helpers/XlsxWriter.php';
require_once dirname(__FILE__, 3) . '/app/helpers/SalesPredictor.php';

function export_fail($msg, $code = 400) { http_response_code($code); header('Content-Type: text/plain; charset=utf-8'); echo $msg; exit; }

try {
    if (!auth()->check()) export_fail('Please log in again.', 401);
    $type = $_GET['type'] ?? '';
    $format = ($_GET['format'] ?? 'csv') === 'xlsx' ? 'xlsx' : 'csv';
    $stamp = date('Ymd-His');
    $headers = []; $rows = []; $name = 'export'; $sheet = 'Data';

    if ($type === 'backup') {
        if (!auth()->hasRole('Administrator')) export_fail('Permission denied.', 403);
        $f = basename($_GET['file'] ?? '');
        if (!preg_match('/^backup-[0-9-]+\.sql$/', $f) || !is_file(REPORT_PATH . DIRECTORY_SEPARATOR . $f)) export_fail('Backup file not found.', 404);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/sql'); header('Content-Disposition: attachment; filename="' . $f . '"');
        readfile(REPORT_PATH . DIRECTORY_SEPARATOR . $f); exit;
    }

    switch ($type) {
        case 'sales':
            if (!auth()->hasPermission('view_sales')) export_fail('Permission denied.', 403);
            $where = []; $p = [];
            if (!empty($_GET['region'])) { $where[] = 's.region_id = ?'; $p[] = (int)$_GET['region']; }
            if (!empty($_GET['category'])) { $where[] = 's.category_id = ?'; $p[] = (int)$_GET['category']; }
            if (!empty($_GET['start_date'])) { $where[] = 's.sale_date >= ?'; $p[] = $_GET['start_date']; }
            if (!empty($_GET['end_date'])) { $where[] = 's.sale_date <= ?'; $p[] = $_GET['end_date']; }
            $sql = "SELECT o.order_code, s.sale_date, p.name product, c.name category, cu.name customer, r.name region, s.quantity, s.unit_price, s.discount, s.sales_amount, s.profit
                    FROM sales s LEFT JOIN orders o ON s.order_id = o.id LEFT JOIN products p ON s.product_id = p.id LEFT JOIN categories c ON s.category_id = c.id
                    LEFT JOIN customers cu ON s.customer_id = cu.id LEFT JOIN regions r ON s.region_id = r.id"
                 . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY s.sale_date, s.id';
            $headers = ['Order', 'Date', 'Product', 'Category', 'Customer', 'Region', 'Quantity', 'Unit Price', 'Discount %', 'Sales Amount', 'Profit'];
            $rows = db()->query($sql, $p)->fetchAll(PDO::FETCH_NUM);
            $name = "sales-$stamp"; $sheet = 'Sales';
            break;

        case 'prediction':
            if (!auth()->hasPermission('view_forecasts')) export_fail('Permission denied.', 403);
            $year = (int)($_GET['year'] ?? date('Y'));
            $pid = ($_GET['product_id'] ?? '') !== '' ? (int)$_GET['product_id'] : null;
            $cid = ($_GET['category_id'] ?? '') !== '' ? (int)$_GET['category_id'] : null;
            $r = (new SalesPredictor())->predictYear($year, $pid, $cid);
            if (!$r['ok']) export_fail($r['error']);
            $label = 'All products';
            if ($pid) $label = db()->query("SELECT name FROM products WHERE id = ?", [$pid])->fetchColumn() ?: 'Product';
            elseif ($cid) $label = 'Category: ' . db()->query("SELECT name FROM categories WHERE id = ?", [$cid])->fetchColumn();
            $headers = ['Scope', 'Month', 'Type', 'Units', 'Revenue', 'Revenue (low)', 'Revenue (high)'];
            foreach ($r['labels'] as $i => $m) {
                $st = $r['status'][$i];
                if ($st === 'none') continue;
                $isA = $st === 'actual';
                $rows[] = [$label, "$m $year", $isA ? 'Actual' : 'Predicted',
                           $isA ? $r['actual_units'][$i] : $r['pred_units'][$i], $isA ? $r['actual_revenue'][$i] : $r['pred_revenue'][$i],
                           $isA ? '' : $r['lower_revenue'][$i], $isA ? '' : $r['upper_revenue'][$i]];
            }
            $rows[] = [$label, "TOTAL $year", '', $r['summary']['total_units'], $r['summary']['total_revenue'], $r['summary']['low_revenue'], $r['summary']['high_revenue']];
            $name = "prediction-$year-" . preg_replace('/[^a-z0-9]+/i', '-', $label); $sheet = "Prediction $year";
            break;

        case 'prediction_all':
            if (!auth()->hasPermission('view_forecasts')) export_fail('Permission denied.', 403);
            $year = (int)($_GET['year'] ?? date('Y'));
            $r = (new SalesPredictor())->predictAllProducts($year);
            if (!$r['ok']) export_fail($r['error']);
            $headers = ['Product', 'Code', 'Category', "Units $year", "Revenue $year", 'Of which actual', 'Of which predicted', 'Trend'];
            foreach ($r['rows'] as $x) $rows[] = [$x['product'], $x['code'], $x['category'], $x['units'], $x['revenue'], $x['actual_revenue'], $x['predicted_revenue'], $x['trend']];
            $name = "predictions-all-products-$year"; $sheet = "Products $year";
            break;

        case 'report':
            if (!auth()->hasPermission('view_reports')) export_fail('Permission denied.', 403);
            $rid = (int)($_GET['id'] ?? 0);
            $rtype = $_GET['report_type'] ?? 'monthly'; $start = $_GET['start'] ?? ''; $end = $_GET['end'] ?? '';
            if ($rid) {
                $rep = db()->query("SELECT * FROM reports WHERE id = ?", [$rid])->fetch();
                if (!$rep) export_fail('Report not found.', 404);
                $rtype = $rep['report_type']; $start = $rep['start_date'] ?: ''; $end = $rep['end_date'] ?: '';
            }
            $fmt = ['daily' => '%Y-%m-%d', 'custom' => '%Y-%m-%d', 'weekly' => '%x-W%v', 'monthly' => '%Y-%m', 'yearly' => '%Y'][$rtype] ?? '%Y-%m';
            $periodExpr = $rtype === 'quarterly' ? "CONCAT(YEAR(sale_date), '-Q', QUARTER(sale_date))" : "DATE_FORMAT(sale_date, '$fmt')";
            $where = []; $p = [];
            if ($start) { $where[] = 'sale_date >= ?'; $p[] = $start; }
            if ($end) { $where[] = 'sale_date <= ?'; $p[] = $end; }
            $sql = "SELECT $periodExpr period, SUM(sales_amount) revenue, SUM(quantity) units, COUNT(DISTINCT order_id) orders, SUM(profit) profit
                    FROM sales" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " GROUP BY period ORDER BY period";
            $headers = [ucfirst($rtype) . ' period', 'Revenue', 'Units sold', 'Orders', 'Profit'];
            $rows = array_map(fn($r) => [$r['period'], round($r['revenue'], 2), (int)$r['units'], (int)$r['orders'], round($r['profit'], 2)], db()->query($sql, $p)->fetchAll());
            $name = "$rtype-report-$stamp"; $sheet = ucfirst($rtype) . ' report';
            break;

        default:
            export_fail('Unknown export type.');
    }

    if (!$rows) export_fail('There is no data to export for this selection.');
    auth()->logActivity('export', "Exported $type as $format");
    if ($format === 'xlsx') XlsxWriter::download("$name.xlsx", $sheet, $headers, $rows);
    XlsxWriter::csv("$name.csv", $headers, $rows);
} catch (Throwable $e) {
    export_fail('Export failed: ' . $e->getMessage(), 500);
}
