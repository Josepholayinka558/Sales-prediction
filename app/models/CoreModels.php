<?php
require_once dirname(__FILE__) . '/Model.php';

class Order extends Model {
    protected $table = 'orders';
    protected $fillable = ['order_code', 'order_date', 'ship_date', 'ship_mode', 'customer_id', 'region_id', 'total_amount', 'total_profit', 'status', 'priority', 'notes'];

    public function totalOrders() {
        return (int)db()->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    }

    public function withDetails($id) {
        return db()->query("SELECT o.*, c.name as customer_name, r.name as region_name, c.segment as segment
                            FROM orders o LEFT JOIN customers c ON o.customer_id = c.id LEFT JOIN regions r ON o.region_id = r.id
                            WHERE o.id = ? LIMIT 1", [$id])->fetch();
    }

    public function items($orderId) {
        return db()->query("SELECT s.*, p.name as product_name, p.product_code, cat.name as category
                            FROM sales s LEFT JOIN products p ON s.product_id = p.id LEFT JOIN categories cat ON s.category_id = cat.id
                            WHERE s.order_id = ?", [$orderId])->fetchAll();
    }

    public function byStatus() {
        $sql = "SELECT status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue
                FROM orders GROUP BY status ORDER BY count DESC";
        return db()->query($sql)->fetchAll();
    }

    public function shipModeStats() {
        $sql = "SELECT ship_mode, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue
                FROM orders WHERE ship_mode IS NOT NULL GROUP BY ship_mode ORDER BY count DESC";
        return db()->query($sql)->fetchAll();
    }

    public function countBetween($startDate, $endDate) {
        return (int)db()->query("SELECT COUNT(*) FROM orders WHERE order_date BETWEEN ? AND ?", [$startDate, $endDate])->fetchColumn();
    }
}

class Product extends Model {
    protected $table = 'products';
    protected $fillable = ['product_code', 'name', 'description', 'category_id', 'sub_category', 'unit_price', 'cost_price', 'stock_quantity', 'image'];

    public function totalProducts() {
        return (int)db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
    }

    public function withCategory($id = null) {
        $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";
        if ($id) {
            return db()->query($sql . " WHERE p.id = ? LIMIT 1", [$id])->fetch();
        }
        return db()->query($sql . " ORDER BY p.name ASC")->fetchAll();
    }

    public function bySubCategory() {
        $sql = "SELECT p.sub_category, COUNT(DISTINCT p.id) as count, COALESCE(SUM(s.sales_amount), 0) as revenue
                FROM products p LEFT JOIN sales s ON s.product_id = p.id
                WHERE p.sub_category IS NOT NULL AND p.sub_category <> '' GROUP BY p.sub_category ORDER BY revenue DESC LIMIT 20";
        return db()->query($sql)->fetchAll();
    }

    public function inventoryValue() {
        return (float)db()->query("SELECT COALESCE(SUM(unit_price * stock_quantity), 0) FROM products")->fetchColumn();
    }

    public function lowStock($threshold = 10) {
        return db()->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.stock_quantity < ? ORDER BY p.stock_quantity ASC", [$threshold])->fetchAll();
    }
}

class Customer extends Model {
    protected $table = 'customers';
    protected $fillable = ['customer_code', 'name', 'email', 'phone', 'address', 'segment', 'region_id'];

    public function totalCustomers() {
        return (int)db()->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    }

    public function withRegion($id = null) {
        $sql = "SELECT c.*, r.name as region_name, r.country FROM customers c LEFT JOIN regions r ON c.region_id = r.id";
        if ($id) {
            return db()->query($sql . " WHERE c.id = ? LIMIT 1", [$id])->fetch();
        }
        return db()->query($sql . " ORDER BY c.name ASC")->fetchAll();
    }

    public function bySegment() {
        $sql = "SELECT segment, COUNT(*) as count FROM customers WHERE segment IS NOT NULL GROUP BY segment ORDER BY count DESC";
        return db()->query($sql)->fetchAll();
    }

    public function summary($id) {
        $sql = "SELECT c.*, r.name as region_name,
                       COUNT(DISTINCT o.id) as total_orders,
                       COALESCE(SUM(s.sales_amount), 0) as total_spent,
                       COALESCE(SUM(s.profit), 0) as total_profit,
                       MAX(o.order_date) as last_order_date
                FROM customers c
                LEFT JOIN regions r ON c.region_id = r.id
                LEFT JOIN orders o ON o.customer_id = c.id
                LEFT JOIN sales s ON s.customer_id = c.id
                WHERE c.id = ?
                GROUP BY c.id";
        return db()->query($sql, [$id])->fetch();
    }
}

class Category extends Model {
    protected $table = 'categories';
    protected $fillable = ['name', 'description'];

    public function allWithStats() {
        return db()->query("SELECT c.*, COUNT(p.id) as product_count,
                           (SELECT COALESCE(SUM(s.sales_amount),0) FROM sales s WHERE s.category_id = c.id) as total_revenue
                           FROM categories c
                           LEFT JOIN products p ON p.category_id = c.id
                           GROUP BY c.id
                           ORDER BY c.name ASC")->fetchAll();
    }
}

class Region extends Model {
    protected $table = 'regions';
    protected $fillable = ['name', 'country', 'state', 'city', 'postal_code'];
}

class Report extends Model {
    protected $table = 'reports';
    protected $fillable = ['name', 'description', 'report_type', 'start_date', 'end_date', 'file_path', 'file_type', 'file_size', 'parameters', 'status', 'created_by'];
}

class Notification extends Model {
    protected $table = 'notifications';
    protected $fillable = ['user_id', 'type', 'title', 'message', 'data', 'is_read', 'link'];

    public function unreadNotificationCount($userId) {
        return db()->query("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$userId])->fetchColumn();
    }
}

class ChatMessage extends Model {
    protected $table = 'chat_messages';
    protected $fillable = ['user_id', 'message', 'response', 'message_type', 'context'];

    public function userHistory($userId, $limit = 50) {
        return db()->query("SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC LIMIT ?", [$userId, $limit])->fetchAll();
    }

    public function clearHistory($userId) {
        return db()->query("DELETE FROM chat_messages WHERE user_id = ?", [$userId]);
    }
}

class ActivityLog extends Model {
    protected $table = 'activity_logs';
    protected $fillable = ['user_id', 'action', 'model', 'model_id', 'description', 'ip_address', 'user_agent'];

    public function recent($limit = 20) {
        return db()->query("SELECT a.*, u.username, u.first_name, u.last_name, u.email
                            FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id
                            ORDER BY a.created_at DESC LIMIT ?", [$limit])->fetchAll();
    }

    public function byUser($userId, $limit = 20) {
        return db()->query("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?", [$userId, $limit])->fetchAll();
    }
}

class Forecast extends Model {
    protected $table = 'forecasts';
    protected $fillable = ['name', 'description', 'model_type', 'forecast_period', 'start_date', 'end_date', 'target_type', 'category_id', 'product_id', 'region_id', 'ma_period', 'alpha', 'beta', 'gamma', 'mae', 'mse', 'rmse', 'mape', 'accuracy_score', 'status', 'created_by'];

    public function results($forecastId) {
        return db()->query("SELECT * FROM forecast_results WHERE forecast_id = ? ORDER BY forecast_date ASC", [$forecastId])->fetchAll();
    }

    public function allWithCreator() {
        return db()->query("SELECT f.*, u.username, u.first_name, u.last_name
                            FROM forecasts f LEFT JOIN users u ON f.created_by = u.id
                            ORDER BY f.created_at DESC")->fetchAll();
    }
}

class DatasetImport extends Model {
    protected $table = 'dataset_imports';
    protected $fillable = ['file_name', 'file_path', 'file_type', 'file_size', 'total_rows', 'imported_rows', 'duplicate_rows', 'error_rows', 'status', 'error_log', 'imported_by'];

    public function recent($limit = 10) {
        return db()->query("SELECT d.*, u.username FROM dataset_imports d LEFT JOIN users u ON d.imported_by = u.id ORDER BY d.created_at DESC LIMIT ?", [$limit])->fetchAll();
    }
}
