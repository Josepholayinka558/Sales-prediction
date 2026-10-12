<?php
require_once dirname(__FILE__, 3) . '/app/bootstrap.php';
require_once dirname(__FILE__, 3) . '/app/models/Sale.php';
require_once dirname(__FILE__, 3) . '/app/models/CoreModels.php';

header('Content-Type: application/json');
if (!auth()->check()) { http_response_code(401); echo json_encode(['data' => []]); exit; }

$type = $_GET['type'] ?? '';
$draw = (int)($_GET['draw'] ?? 1);
$start = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 5000) $length = 5000;
if ($start < 0) $start = 0;
$search = $_GET['search']['value'] ?? '';
$order = $_GET['order'] ?? [];
$orderBy = '1';
$orderDir = 'DESC';
if (!empty($order)) {
    $colIdx = (int)($order[0]['column'] ?? 0);
    $od = strtolower($order[0]['dir'] ?? 'desc');
    $orderDir = in_array($od, ['asc','desc']) ? $od : 'desc';
}
$region = $_GET['region'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$role = $_GET['role'] ?? '';
$segment = $_GET['segment'] ?? '';

function whereAdd($clauses, $params, $cond, $val) {
    $clauses[] = $cond;
    if (is_array($val)) $params = array_merge($params, $val); else $params[] = $val;
    return [$clauses, $params];
}

function wrapCount($sql) { return "SELECT COUNT(*) FROM ($sql) t"; }

function runQuery($baseCount, $baseSelect, $clauses, $params, $limitSql, $orderSql, $draw) {
    $where = !empty($clauses) ? (' WHERE ' . implode(' AND ', $clauses)) : '';
    $total = (int)db()->query($baseCount)->fetchColumn();
    $filtered = (int)db()->query(wrapCount($baseSelect . $where), $params)->fetchColumn();
    $sql = $baseSelect . $where . ' ORDER BY ' . $orderSql . ' ' . $limitSql;
    $data = db()->query($sql, $params)->fetchAll();
    echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data], JSON_NUMERIC_CHECK);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['data' => []]); exit; }

switch ($type) {
    case 'table_meta':
        if (!auth()->hasRole('Administrator')) { http_response_code(403); echo json_encode(['data' => []]); break; }
        $meta = db()->query("SELECT TABLE_NAME AS name, TABLE_ROWS AS approx_rows, DATA_LENGTH + INDEX_LENGTH AS size, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")->fetchAll();
        foreach ($meta as &$m) { $m['rows'] = (int)db()->query("SELECT COUNT(*) FROM `" . str_replace('`', '', $m['name']) . "`")->fetchColumn(); }
        echo json_encode(['data' => $meta], JSON_NUMERIC_CHECK);
        break;

    case 'sales':
        $cols = ['s.id','o.order_code','s.sale_date','p.name','c.name','cu.name','r.name','s.quantity','s.unit_price','s.discount','s.sales_amount','s.profit'];
        $orderBy = 's.id'; if (!empty($order)) $orderBy = $cols[(int)($order[0]['column'] ?? 0)] ?? 's.id';
        $baseCount = "SELECT COUNT(*) FROM sales";
        $baseSelect = "SELECT s.id, o.order_code, s.sale_date, p.name as product, c.name as category, cu.name as customer, r.name as region,
            s.quantity, s.unit_price, s.discount, s.sales_amount, s.profit
            FROM sales s
            LEFT JOIN orders o ON s.order_id = o.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN categories c ON s.category_id = c.id
            LEFT JOIN customers cu ON s.customer_id = cu.id
            LEFT JOIN regions r ON s.region_id = r.id";
        $clauses = []; $params = [];
        if ($search) { $clauses[] = "(o.order_code LIKE ? OR p.name LIKE ? OR cu.name LIKE ? OR r.name LIKE ? OR c.name LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%","%$search%"]); }
        if ($region) { $clauses[] = "s.region_id = ?"; $params[] = $region; }
        if ($category) { $clauses[] = "s.category_id = ?"; $params[] = $category; }
        if ($startDate) { $clauses[] = "s.sale_date >= ?"; $params[] = $startDate; }
        if ($endDate) { $clauses[] = "s.sale_date <= ?"; $params[] = $endDate; }
        $limitSql = "LIMIT $length OFFSET $start";
        runQuery($baseCount, $baseSelect, $clauses, $params, $limitSql, "$orderBy $orderDir", $draw);
        break;

    case 'orders':
        $cols = ['o.id','o.order_code','o.order_date','c.name','r.name','o.status','o.ship_mode','o.priority','o.total_amount','o.total_profit'];
        $orderBy = 'o.id'; if (!empty($order)) $orderBy = $cols[(int)($order[0]['column'] ?? 0)] ?? 'o.id';
        $baseCount = "SELECT COUNT(*) FROM orders";
        $baseSelect = "SELECT o.*, c.name as customer_name, r.name as region_name
            FROM orders o LEFT JOIN customers c ON o.customer_id = c.id LEFT JOIN regions r ON o.region_id = r.id";
        $clauses = []; $params = [];
        if ($search) { $clauses[] = "(o.order_code LIKE ? OR c.name LIKE ? OR r.name LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
        if ($region) { $clauses[] = "o.region_id = ?"; $params[] = $region; }
        if ($status) { $clauses[] = "o.status = ?"; $params[] = $status; }
        if ($startDate) { $clauses[] = "o.order_date >= ?"; $params[] = $startDate; }
        if ($endDate) { $clauses[] = "o.order_date <= ?"; $params[] = $endDate; }
        runQuery($baseCount, $baseSelect, $clauses, $params, "LIMIT $length OFFSET $start", "$orderBy $orderDir", $draw);
        break;

    case 'products':
        $cols = ['p.id','p.product_code','p.name','c.name','p.sub_category','p.unit_price','p.stock_quantity'];
        $orderBy = 'p.id'; if (!empty($order)) $orderBy = $cols[(int)($order[0]['column'] ?? 0)] ?? 'p.id';
        $baseCount = "SELECT COUNT(*) FROM products";
        $baseSelect = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";
        $clauses = []; $params = [];
        if ($search) { $clauses[] = "(p.name LIKE ? OR p.product_code LIKE ? OR p.sub_category LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
        if ($category) { $clauses[] = "p.category_id = ?"; $params[] = $category; }
        runQuery($baseCount, $baseSelect, $clauses, $params, "LIMIT $length OFFSET $start", "$orderBy $orderDir", $draw);
        break;

    case 'customers':
        $cols = ['c.id','c.customer_code','c.name','c.segment','r.name','order_count','total_spent'];
        $orderBy = 'c.id'; if (!empty($order)) $orderBy = $cols[(int)($order[0]['column'] ?? 0)] ?? 'c.id';
        $baseCount = "SELECT COUNT(*) FROM customers";
        $baseSelect = "SELECT c.*, r.name as region_name, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) as order_count,
            COALESCE((SELECT SUM(s.sales_amount) FROM sales s WHERE s.customer_id = c.id),0) as total_spent
            FROM customers c LEFT JOIN regions r ON c.region_id = r.id";
        $clauses = []; $params = [];
        if ($search) { $clauses[] = "(c.name LIKE ? OR c.customer_code LIKE ? OR c.email LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
        if ($segment) { $clauses[] = "c.segment = ?"; $params[] = $segment; }
        if ($region) { $clauses[] = "c.region_id = ?"; $params[] = $region; }
        runQuery($baseCount, $baseSelect, $clauses, $params, "LIMIT $length OFFSET $start", "$orderBy $orderDir", $draw);
        break;

    case 'users':
        $cols = ['u.id','u.first_name','u.username','u.email','r.name','u.status','u.last_login','u.created_at'];
        $orderBy = 'u.id'; if (!empty($order)) $orderBy = $cols[(int)($order[0]['column'] ?? 0)] ?? 'u.id';
        $baseCount = "SELECT COUNT(*) FROM users";
        $baseSelect = "SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id";
        $clauses = []; $params = [];
        if ($search) { $clauses[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)"; $params = ["%$search%","%$search%","%$search%","%$search%"]; }
        if ($role) { $clauses[] = "u.role_id = ?"; $params[] = $role; }
        runQuery($baseCount, $baseSelect, $clauses, $params, "LIMIT $length OFFSET $start", "$orderBy $orderDir", $draw);
        break;

    case 'reports':
        $baseCount = "SELECT COUNT(*) FROM reports";
        $baseSelect = "SELECT r.*, u.username as created_by_name FROM reports r LEFT JOIN users u ON r.created_by = u.id";
        $clauses = []; $params = [];
        if ($search) { $clauses[] = "r.name LIKE ?"; $params[] = "%$search%"; }
        runQuery($baseCount, $baseSelect, $clauses, $params, "LIMIT $length OFFSET $start", "r.id $orderDir", $draw);
        break;

    default:
        echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
}
