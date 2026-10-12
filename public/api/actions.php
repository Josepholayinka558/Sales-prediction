<?php
require_once dirname(__FILE__, 3) . '/app/bootstrap.php';
require_once dirname(__FILE__, 3) . '/app/models/Sale.php';
require_once dirname(__FILE__, 3) . '/app/models/CoreModels.php';

header('Content-Type: application/json');

function out($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }
function need($perm) { if (!auth()->hasPermission($perm)) out(['success' => false, 'error' => 'You do not have permission to do that.'], 403); }
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v) { return '$' . number_format((float)$v, 2); }
function row($label, $val) { return '<tr><th class="text-muted fw-normal" style="width:42%">' . h($label) . '</th><td class="fw-semibold">' . $val . '</td></tr>'; }
function tempPassword() { return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10) . '7a'; }

try {
    if (!auth()->check()) out(['success' => false, 'error' => 'Please log in again.'], 401);

    // read JSON or form body
    $in = $_POST;
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $j = json_decode(file_get_contents('php://input'), true);
        if (is_array($j)) $in = array_merge($in, $j);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') $in = array_merge($in, $_GET);
    $action = $in['action'] ?? ($_GET['action'] ?? '');
    $readOnly = in_array($action, ['view'], true);
    if (!$readOnly) {
        $token = $in[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::verify($token)) out(['success' => false, 'error' => 'Your session expired. Please refresh the page and try again.'], 419);
    }
    $uid = (int)Session::userId();
    $entity = $in['entity'] ?? '';
    $id = (int)($in['id'] ?? 0);

    switch ($action) {

        /* ------------------------------------------------------------ VIEW */
        case 'view':
            if ($entity === 'product') {
                need('view_products');
                $p = (new Product())->withCategory($id);
                if (!$p) out(['success' => false, 'error' => 'Product not found.'], 404);
                $s = db()->query("SELECT COUNT(*) n, COALESCE(SUM(quantity),0) units, COALESCE(SUM(sales_amount),0) rev, COALESCE(SUM(profit),0) prof, MIN(sale_date) d1, MAX(sale_date) d2 FROM sales WHERE product_id = ?", [$id])->fetch();
                $html = '<table class="table table-sm mb-0 text-start">' . row('Code', h($p['product_code'])) . row('Name', h($p['name'])) . row('Category', h($p['category_name'] ?? '-'))
                      . row('Sub-category', h($p['sub_category'] ?? '-')) . row('List price', money($p['unit_price'])) . row('Sales records', number_format($s['n']))
                      . row('Units sold', number_format($s['units'])) . row('Revenue', money($s['rev'])) . row('Profit', money($s['prof']))
                      . row('Sold between', $s['d1'] ? h($s['d1'] . ' to ' . $s['d2']) : '-') . '</table>'
                      . '<a class="btn btn-primary btn-sm mt-3" href="' . h(url('predict.php?product_id=' . $id)) . '"><i class="fas fa-wand-magic-sparkles me-1"></i>Predict this product</a>';
                out(['success' => true, 'title' => $p['name'], 'html' => $html]);
            }
            if ($entity === 'customer') {
                need('view_customers');
                $c = (new Customer())->summary($id);
                if (!$c) out(['success' => false, 'error' => 'Customer not found.'], 404);
                $s = db()->query("SELECT COUNT(DISTINCT order_id) orders, COALESCE(SUM(sales_amount),0) spent, COALESCE(SUM(profit),0) prof FROM sales WHERE customer_id = ?", [$id])->fetch();
                $html = '<table class="table table-sm mb-0 text-start">' . row('Code', h($c['customer_code'])) . row('Name', h($c['name'])) . row('Segment', h($c['segment'] ?? '-'))
                      . row('Region', h($c['region_name'] ?? '-')) . row('Email', h($c['email'] ?: '-')) . row('Orders', number_format($s['orders']))
                      . row('Total spent', money($s['spent'])) . row('Profit', money($s['prof'])) . row('Last order', h($c['last_order_date'] ?? '-')) . '</table>';
                out(['success' => true, 'title' => $c['name'], 'html' => $html]);
            }
            if ($entity === 'order') {
                need('view_orders');
                $o = (new Order())->withDetails($id);
                if (!$o) out(['success' => false, 'error' => 'Order not found.'], 404);
                $items = (new Order())->items($id);
                $html = '<table class="table table-sm text-start">' . row('Order', h($o['order_code'])) . row('Date', h($o['order_date'])) . row('Customer', h($o['customer_name'] ?? '-'))
                      . row('Region', h($o['region_name'] ?? '-')) . row('Status', h(ucfirst($o['status']))) . row('Total', money($o['total_amount'])) . row('Profit', money($o['total_profit'])) . '</table>'
                      . '<div class="fw-semibold text-start mb-1">Items</div><table class="table table-sm table-bordered mb-0 text-start"><thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Amount</th></tr></thead><tbody>';
                foreach ($items as $it) $html .= '<tr><td>' . h($it['product_name'] ?? 'N/A') . '</td><td class="text-end">' . (int)$it['quantity'] . '</td><td class="text-end">' . money($it['sales_amount']) . '</td></tr>';
                $html .= '</tbody></table>';
                out(['success' => true, 'title' => 'Order ' . $o['order_code'], 'html' => $html]);
            }
            if ($entity === 'sale') {
                need('view_sales');
                $s = db()->query("SELECT s.*, o.order_code, p.name product, c.name category, cu.name customer, r.name region FROM sales s LEFT JOIN orders o ON s.order_id=o.id
                                  LEFT JOIN products p ON s.product_id=p.id LEFT JOIN categories c ON s.category_id=c.id LEFT JOIN customers cu ON s.customer_id=cu.id LEFT JOIN regions r ON s.region_id=r.id WHERE s.id = ?", [$id])->fetch();
                if (!$s) out(['success' => false, 'error' => 'Sale not found.'], 404);
                $html = '<table class="table table-sm mb-0 text-start">' . row('Order', h($s['order_code'])) . row('Date', h($s['sale_date'])) . row('Product', h($s['product'] ?? '-')) . row('Category', h($s['category'] ?? '-'))
                      . row('Customer', h($s['customer'] ?? '-')) . row('Region', h($s['region'] ?? '-')) . row('Quantity', number_format($s['quantity'])) . row('Unit price', money($s['unit_price']))
                      . row('Discount', h($s['discount']) . '%') . row('Sales amount', money($s['sales_amount'])) . row('Profit', money($s['profit'])) . '</table>';
                out(['success' => true, 'title' => 'Sale #' . $id, 'html' => $html]);
            }
            out(['success' => false, 'error' => 'Unknown item type.'], 400);

        /* ---------------------------------------------------------- DELETE */
        case 'delete':
            if ($id <= 0) out(['success' => false, 'error' => 'Missing item.'], 400);
            $map = ['product' => ['products', 'manage_products'], 'customer' => ['customers', 'manage_customers'], 'order' => ['orders', 'manage_orders'],
                    'sale' => ['sales', 'manage_sales'], 'report' => ['reports', 'create_reports'], 'forecast' => ['forecasts', 'create_forecasts']];
            if ($entity === 'notification') {
                db()->query("DELETE FROM notifications WHERE id = ? AND user_id = ?", [$id, $uid]);
                out(['success' => true, 'message' => 'Notification deleted.']);
            }
            if ($entity === 'user') {
                need('all');
                if ($id === $uid) out(['success' => false, 'error' => 'You cannot delete your own account.'], 400);
                $admins = (int)db()->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.id WHERE r.name='Administrator'")->fetchColumn();
                $isAdmin = (int)db()->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=? AND r.name='Administrator'", [$id])->fetchColumn();
                if ($isAdmin && $admins <= 1) out(['success' => false, 'error' => 'You cannot delete the last administrator.'], 400);
                db()->query("DELETE FROM users WHERE id = ?", [$id]);
                auth()->logActivity('delete', "Deleted user #$id");
                out(['success' => true, 'message' => 'User deleted.']);
            }
            if (!isset($map[$entity])) out(['success' => false, 'error' => 'Unknown item type.'], 400);
            need($map[$entity][1]);
            $table = $map[$entity][0];
            if ($entity === 'forecast') db()->query("DELETE FROM forecast_results WHERE forecast_id = ?", [$id]);
            db()->query("DELETE FROM $table WHERE id = ?", [$id]);
            auth()->logActivity('delete', "Deleted $entity #$id");
            out(['success' => true, 'message' => ucfirst($entity) . ' deleted.']);

        /* ---------------------------------------------------- NOTIFICATIONS */
        case 'notifications_read_all':
            db()->query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$uid]);
            out(['success' => true, 'message' => 'All notifications marked as read.']);

        case 'notifications_clear':
            db()->query("DELETE FROM notifications WHERE user_id = ?", [$uid]);
            out(['success' => true, 'message' => 'All notifications cleared.']);

        /* ---------------------------------------------------------- REPORTS */
        case 'create_report':
            need('create_reports');
            $type = in_array($in['report_type'] ?? '', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'], true) ? $in['report_type'] : 'monthly';
            $name = trim($in['name'] ?? '') ?: ucfirst($type) . ' Report ' . date('M d, Y');
            $sd = !empty($in['start_date']) ? $in['start_date'] : null; $ed = !empty($in['end_date']) ? $in['end_date'] : null;
            if ($sd && $ed && $sd > $ed) out(['success' => false, 'error' => 'The start date must be before the end date.'], 400);
            $rid = (new Report())->create(['name' => $name, 'report_type' => $type, 'start_date' => $sd, 'end_date' => $ed, 'status' => 'completed', 'created_by' => $uid,
                                           'parameters' => json_encode(['type' => $type, 'start' => $sd, 'end' => $ed])]);
            auth()->logActivity('report', "Report created: $name");
            out(['success' => true, 'id' => (int)$rid, 'message' => 'Report created.']);

        /* ------------------------------------------------------------ USERS */
        case 'user_create':
            need('all');
            $username = trim($in['username'] ?? ''); $email = trim($in['email'] ?? '');
            $fn = trim($in['first_name'] ?? ''); $ln = trim($in['last_name'] ?? ''); $role = (int)($in['role_id'] ?? 0);
            if (!$username || !$email || !$fn || !$ln || !$role) out(['success' => false, 'error' => 'Please fill in all fields.'], 400);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['success' => false, 'error' => 'Please enter a valid email address.'], 400);
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) out(['success' => false, 'error' => 'Username must be 3-50 letters, numbers, dots, dashes or underscores.'], 400);
            if (!db()->query("SELECT id FROM roles WHERE id = ?", [$role])->fetch()) out(['success' => false, 'error' => 'Invalid role.'], 400);
            if (db()->query("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email])->fetch()) out(['success' => false, 'error' => 'That username or email is already in use.'], 400);
            $pw = tempPassword();
            (new User())->create(['username' => $username, 'email' => $email, 'password' => password_hash($pw, PASSWORD_DEFAULT), 'first_name' => $fn, 'last_name' => $ln, 'role_id' => $role, 'status' => 'active']);
            auth()->logActivity('create', "Created user $username");
            out(['success' => true, 'message' => 'User created.', 'temp_password' => $pw]);

        case 'user_get':
            need('all');
            $u = db()->query("SELECT id, username, email, first_name, last_name, role_id, status FROM users WHERE id = ?", [$id])->fetch();
            if (!$u) out(['success' => false, 'error' => 'User not found.'], 404);
            out(['success' => true, 'user' => $u, 'roles' => db()->query("SELECT id, name FROM roles ORDER BY id")->fetchAll()]);

        case 'user_update':
            need('all');
            $u = db()->query("SELECT * FROM users WHERE id = ?", [$id])->fetch();
            if (!$u) out(['success' => false, 'error' => 'User not found.'], 404);
            $email = trim($in['email'] ?? $u['email']); $fn = trim($in['first_name'] ?? $u['first_name']); $ln = trim($in['last_name'] ?? $u['last_name']);
            $role = (int)($in['role_id'] ?? $u['role_id']); $status = in_array($in['status'] ?? '', ['active', 'inactive', 'suspended'], true) ? $in['status'] : $u['status'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['success' => false, 'error' => 'Please enter a valid email address.'], 400);
            if (db()->query("SELECT id FROM users WHERE email = ? AND id <> ?", [$email, $id])->fetch()) out(['success' => false, 'error' => 'That email is already in use.'], 400);
            if ($id === $uid && ($status !== 'active')) out(['success' => false, 'error' => 'You cannot deactivate your own account.'], 400);
            if ($id === $uid && $role !== (int)$u['role_id']) out(['success' => false, 'error' => 'You cannot change your own role.'], 400);
            (new User())->update($id, ['email' => $email, 'first_name' => $fn, 'last_name' => $ln, 'role_id' => $role, 'status' => $status]);
            auth()->logActivity('update', "Updated user #$id");
            out(['success' => true, 'message' => 'User updated.']);

        case 'user_reset_password':
            need('all');
            if (!db()->query("SELECT id FROM users WHERE id = ?", [$id])->fetch()) out(['success' => false, 'error' => 'User not found.'], 404);
            $pw = tempPassword();
            db()->query("UPDATE users SET password = ? WHERE id = ?", [password_hash($pw, PASSWORD_DEFAULT), $id]);
            auth()->logActivity('update', "Reset password for user #$id");
            out(['success' => true, 'message' => 'Password reset.', 'temp_password' => $pw]);

        default:
            out(['success' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    $logDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'error-' . date('Y-m-d') . '.log', date('Y-m-d H:i:s') . ' | [actions.php] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL, FILE_APPEND);
    out(['success' => false, 'error' => 'Something went wrong: ' . $e->getMessage()], 500);
}
