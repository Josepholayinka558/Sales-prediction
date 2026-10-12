<?php
require_once dirname(__FILE__) . '/Model.php';

class Sale extends Model {
    protected $table = 'sales';
    protected $fillable = ['order_id', 'product_id', 'quantity', 'unit_price', 'discount', 'sales_amount', 'cost_amount', 'profit', 'sale_date', 'region_id', 'customer_id', 'category_id'];

    public function totalRevenue() {
        return (float)db()->query("SELECT COALESCE(SUM(sales_amount), 0) FROM sales")->fetchColumn();
    }

    public function totalProfit() {
        return (float)db()->query("SELECT COALESCE(SUM(profit), 0) FROM sales")->fetchColumn();
    }

    public function totalSalesCount() {
        return (int)db()->query("SELECT COUNT(*) FROM sales")->fetchColumn();
    }

    public function averageOrderValue() {
        return (float)db()->query("SELECT COALESCE(AVG(order_total), 0) FROM (SELECT order_id, SUM(sales_amount) as order_total FROM sales GROUP BY order_id) t")->fetchColumn();
    }

    public function revenueBetween($startDate, $endDate) {
        return (float)db()->query("SELECT COALESCE(SUM(sales_amount), 0) FROM sales WHERE sale_date BETWEEN ? AND ?", [$startDate, $endDate])->fetchColumn();
    }

    /** Latest sale date in the data (falls back to today when empty). All "last N months" views anchor here, so old datasets still show. */
    public function anchorDate() {
        $d = db()->query("SELECT MAX(sale_date) FROM sales")->fetchColumn();
        return $d ?: date('Y-m-d');
    }

    public function monthlyRevenue($months = 12) {
        $anchor = $this->anchorDate();
        $sql = "SELECT 
                    DATE_FORMAT(sale_date, '%Y-%m') as month_key,
                    DATE_FORMAT(sale_date, '%b %Y') as month_label,
                    COALESCE(SUM(sales_amount), 0) as revenue,
                    COALESCE(SUM(profit), 0) as profit,
                    COUNT(DISTINCT order_id) as orders
                FROM sales
                WHERE sale_date > DATE_SUB(?, INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
                ORDER BY month_key ASC";
        return db()->query($sql, [$anchor, $months])->fetchAll();
    }

    public function yearlyRevenue($years = 5) {
        $anchor = $this->anchorDate();
        $sql = "SELECT 
                    DATE_FORMAT(sale_date, '%Y') as year_key,
                    COALESCE(SUM(sales_amount), 0) as revenue,
                    COALESCE(SUM(profit), 0) as profit,
                    COUNT(DISTINCT order_id) as orders
                FROM sales
                WHERE sale_date > DATE_SUB(?, INTERVAL ? YEAR)
                GROUP BY DATE_FORMAT(sale_date, '%Y')
                ORDER BY year_key ASC";
        return db()->query($sql, [$anchor, $years])->fetchAll();
    }

    public function byCategory() {
        $sql = "SELECT c.name as category, COALESCE(SUM(s.sales_amount), 0) as revenue, COALESCE(SUM(s.profit), 0) as profit
                FROM sales s
                LEFT JOIN categories c ON s.category_id = c.id
                GROUP BY s.category_id, c.name
                ORDER BY revenue DESC";
        return db()->query($sql)->fetchAll();
    }

    public function byRegion() {
        $sql = "SELECT r.name as region, COALESCE(SUM(s.sales_amount), 0) as revenue, COALESCE(SUM(s.profit), 0) as profit
                FROM sales s
                LEFT JOIN regions r ON s.region_id = r.id
                GROUP BY s.region_id, r.name
                ORDER BY revenue DESC";
        return db()->query($sql)->fetchAll();
    }

    public function byCustomer($limit = 10) {
        $sql = "SELECT cu.id, cu.name as customer, r.name as region, COALESCE(SUM(s.sales_amount), 0) as revenue, COUNT(DISTINCT s.order_id) as orders
                FROM sales s
                LEFT JOIN customers cu ON s.customer_id = cu.id
                LEFT JOIN regions r ON s.region_id = r.id
                GROUP BY s.customer_id, cu.id, cu.name, r.name
                ORDER BY revenue DESC
                LIMIT ?";
        return db()->query($sql, [$limit])->fetchAll();
    }

    public function topProducts($limit = 10) {
        $sql = "SELECT p.id, p.name as product, c.name as category, COALESCE(SUM(s.sales_amount), 0) as revenue, 
                       COALESCE(SUM(s.quantity), 0) as quantity, COALESCE(SUM(s.profit), 0) as profit
                FROM sales s
                LEFT JOIN products p ON s.product_id = p.id
                LEFT JOIN categories c ON s.category_id = c.id
                GROUP BY s.product_id, p.id, p.name, c.name
                ORDER BY revenue DESC
                LIMIT ?";
        return db()->query($sql, [$limit])->fetchAll();
    }

    public function recentTransactions($limit = 10) {
        $sql = "SELECT s.*, o.order_code, p.name as product, cu.name as customer, r.name as region
                FROM sales s
                LEFT JOIN orders o ON s.order_id = o.id
                LEFT JOIN products p ON s.product_id = p.id
                LEFT JOIN customers cu ON s.customer_id = cu.id
                LEFT JOIN regions r ON s.region_id = r.id
                ORDER BY s.created_at DESC
                LIMIT ?";
        return db()->query($sql, [$limit])->fetchAll();
    }

    public function getDailySeries($startDate, $endDate) {
        $sql = "SELECT sale_date, COALESCE(SUM(sales_amount), 0) as revenue, COALESCE(SUM(profit), 0) as profit
                FROM sales
                WHERE sale_date BETWEEN ? AND ?
                GROUP BY sale_date
                ORDER BY sale_date ASC";
        return db()->query($sql, [$startDate, $endDate])->fetchAll();
    }

    public function getDateRange() {
        return db()->query("SELECT MIN(sale_date) as min_date, MAX(sale_date) as max_date FROM sales")->fetch();
    }

    public function profitLossSummary() {
        $totalRevenue = $this->totalRevenue();
        $totalProfit = $this->totalProfit();
        $totalCost = $totalRevenue - $totalProfit;
        $margin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
        return [
            'revenue' => $totalRevenue,
            'profit' => $totalProfit,
            'cost' => $totalCost,
            'margin' => $margin
        ];
    }

    /** Revenue / units / orders / profit per calendar month for one year -> arrays of 12. */
    public function monthsOfYear($year) {
        $rows = db()->query("SELECT MONTH(sale_date) m, SUM(sales_amount) revenue, SUM(quantity) units, COUNT(DISTINCT order_id) orders, SUM(profit) profit
                             FROM sales WHERE YEAR(sale_date) = ? GROUP BY MONTH(sale_date)", [(int)$year])->fetchAll();
        $out = ['revenue' => array_fill(0, 12, 0.0), 'units' => array_fill(0, 12, 0.0), 'orders' => array_fill(0, 12, 0), 'profit' => array_fill(0, 12, 0.0)];
        foreach ($rows as $r) { $i = (int)$r['m'] - 1; $out['revenue'][$i] = (float)$r['revenue']; $out['units'][$i] = (float)$r['units']; $out['orders'][$i] = (int)$r['orders']; $out['profit'][$i] = (float)$r['profit']; }
        return $out;
    }

    public function yearsAvailable() {
        return array_map('intval', db()->query("SELECT DISTINCT YEAR(sale_date) y FROM sales ORDER BY y")->fetchAll(PDO::FETCH_COLUMN));
    }

    public function totalUnits() {
        return (float)db()->query("SELECT COALESCE(SUM(quantity),0) FROM sales")->fetchColumn();
    }

    /** Revenue per region per quarter (for the analytics heatmap). */
    public function regionQuarters() {
        return db()->query("SELECT COALESCE(r.name,'Unknown') region, CONCAT(YEAR(s.sale_date),' Q',QUARTER(s.sale_date)) quarter, SUM(s.sales_amount) revenue
                            FROM sales s LEFT JOIN regions r ON s.region_id = r.id
                            GROUP BY region, quarter ORDER BY quarter, region")->fetchAll();
    }
}
