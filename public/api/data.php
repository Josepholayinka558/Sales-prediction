<?php
require_once dirname(__FILE__, 3) . '/app/bootstrap.php';
require_once dirname(__FILE__, 3) . '/app/models/Sale.php';
require_once dirname(__FILE__, 3) . '/app/models/CoreModels.php';
require_once dirname(__FILE__, 3) . '/app/helpers/ForecastEngine.php';

header('Content-Type: application/json');

$sendJson = function ($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_NUMERIC_CHECK);
    exit;
};

try {
    if (!auth()->check()) {
        $sendJson(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $jsonBody = file_get_contents('php://input');
        if ($jsonBody) {
            $jsonArr = json_decode($jsonBody, true);
            if (is_array($jsonArr)) {
                $_POST = array_merge($_POST, $jsonArr);
            }
        }
    }

    $type = $_GET['type'] ?? '';
    $saleModel = new Sale();
    $orderModel = new Order();
    $forecastEngine = new ForecastEngine();
    $result = ['success' => true, 'type' => $type, 'data' => []];

    $methodMap = [
        'moving_average' => 'movingAverage',
        'weighted_moving_average' => 'weightedMovingAverage',
        'linear_regression' => 'linearRegression',
        'exponential_smoothing' => 'exponentialSmoothing',
    ];
    $callForecast = function ($engine, $method, ...$args) use ($methodMap) {
        $realMethod = $methodMap[$method] ?? $method;
        if (!method_exists($engine, $realMethod)) {
            throw new Exception("Forecast method '$method' ($realMethod) not available.");
        }
        return $engine->$realMethod(...$args);
    };

    switch ($type) {
        case 'kpi':
            $start = $_GET['start'] ?? null;
            $end = $_GET['end'] ?? null;
            $result['data'] = [
                'revenue' => $start && $end ? $saleModel->revenueBetween($start, $end) : $saleModel->totalRevenue(),
                'profit' => $saleModel->totalProfit(),
                'orders' => $orderModel->totalOrders(),
                'customers' => (new Customer())->totalCustomers(),
                'products' => (new Product())->totalProducts(),
                'aov' => $saleModel->averageOrderValue(),
                'sales_count' => $saleModel->totalSalesCount()
            ];
            break;

        case 'monthly':
            $months = (int)($_GET['months'] ?? 12);
            $result['data'] = $saleModel->monthlyRevenue($months);
            break;

        case 'yearly':
            $years = (int)($_GET['years'] ?? 5);
            $result['data'] = $saleModel->yearlyRevenue($years);
            break;

        case 'categories':
            $result['data'] = $saleModel->byCategory();
            break;

        case 'regions':
            $result['data'] = $saleModel->byRegion();
            break;

        case 'top_customers':
            $limit = (int)($_GET['limit'] ?? 10);
            $result['data'] = $saleModel->byCustomer($limit);
            break;

        case 'top_products':
            $limit = (int)($_GET['limit'] ?? 10);
            $result['data'] = $saleModel->topProducts($limit);
            break;

        case 'recent_transactions':
            $limit = (int)($_GET['limit'] ?? 10);
            $result['data'] = $saleModel->recentTransactions($limit);
            break;

        case 'order_status':
            $result['data'] = $orderModel->byStatus();
            break;

        case 'ship_mode':
            $result['data'] = $orderModel->shipModeStats();
            break;

        case 'segments':
            $result['data'] = (new Customer())->bySegment();
            break;

        case 'daily_series':
            $anchor = $saleModel->anchorDate();
            $end = $_GET['end'] ?? $anchor;
            $start = $_GET['start'] ?? date('Y-m-d', strtotime('-90 days', strtotime($end)));
            $result['data'] = $saleModel->getDailySeries($start, $end);
            break;

        case 'sub_categories':
            $result['data'] = (new Product())->bySubCategory();
            break;

        case 'profit_analysis':
            $months = (int)($_GET['months'] ?? 12);
            $monthly = $saleModel->monthlyRevenue($months);
            $result['data'] = array_map(function($m) {
                return ['label' => $m['month_label'], 'revenue' => (float)$m['revenue'], 'profit' => (float)$m['profit'], 'cost' => (float)$m['revenue'] - (float)$m['profit']];
            }, $monthly);
            break;

        case 'monthly_comparison':
            $years = $saleModel->yearsAvailable();
            $cy = $years ? end($years) : (int)date('Y');
            $py = $cy - 1;
            $cur = $saleModel->monthsOfYear($cy)['revenue'];
            $prev = $saleModel->monthsOfYear($py)['revenue'];
            $result['data'] = ['labels' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                               'current_year' => $cur, 'previous_year' => $prev, 'current_label' => (string)$cy, 'previous_label' => (string)$py,
                               'has_previous' => array_sum($prev) > 0];
            break;

        case 'region_quarters':
            $result['data'] = $saleModel->regionQuarters();
            break;

        case 'forecast_preview':
            $targetType = $_GET['target'] ?? 'revenue';
            $period = $_GET['period'] ?? '30_days';
            $modelType = $_GET['model'] ?? 'linear_regression';
            $dr = $saleModel->getDateRange();
            $series = $forecastEngine->getDailySeries($dr['min_date'] ?: date('Y-m-d', strtotime('-2 years')), $dr['max_date'] ?: date('Y-m-d'), $targetType);
            $periodDays = ['7_days'=>7,'30_days'=>30,'90_days'=>90,'365_days'=>365][$period] ?? 30;
            $maPeriod = 7;
            $alpha = 0.3;
            $prediction = $callForecast($forecastEngine, $modelType, $series, $periodDays, $modelType === 'moving_average' ? $maPeriod : $alpha);
            $actualData = array_slice($series, -90);
            $labels = array_merge(
                array_map(function($s) { return date('M d', strtotime($s['date'])); }, $actualData),
                array_map(function($s) { return date('M d', strtotime($s['date'])); }, $prediction['forecast'])
            );
            $actual = array_merge(array_map(fn($s) => (float)$s['value'], $actualData), array_fill(0, count($prediction['forecast']), null));
            $predicted = array_merge(array_fill(0, count($actualData), null), array_map(fn($s) => (float)$s['value'], $prediction['forecast']));
            $upper = array_merge(array_fill(0, count($actualData), null), array_map(fn($s) => (float)$s['upper'], $prediction['forecast']));
            $lower = array_merge(array_fill(0, count($actualData), null), array_map(fn($s) => (float)$s['lower'], $prediction['forecast']));
            $lastActual = end($actualData) ? (float)$actualData[count($actualData)-1]['value'] : 0;
            $lastIdx = count($actualData) - 1;
            if ($lastIdx >= 0) {
                $actual[$lastIdx] = $lastActual;
                $predicted[$lastIdx] = $lastActual;
                $upper[$lastIdx] = $lastActual;
                $lower[$lastIdx] = $lastActual;
            }
            $result['data'] = ['labels' => $labels, 'actual' => $actual, 'predicted' => $predicted, 'upper' => $upper, 'lower' => $lower, 'metrics' => ['mae' => $prediction['mae'], 'rmse' => $prediction['rmse'], 'mape' => $prediction['mape'], 'accuracy' => $prediction['accuracy']]];
            break;

        case 'run_forecast':
            $modelType = $_POST['model'] ?? 'linear_regression';
            $periodDays = ['7_days'=>7,'30_days'=>30,'90_days'=>90,'365_days'=>365][$_POST['period'] ?? '30_days'] ?? 30;
            $target = $_POST['target'] ?? 'revenue';
            $maPeriod = (int)($_POST['ma_period'] ?? 7);
            $alpha = (float)($_POST['alpha'] ?? 0.3);
            $dr = $saleModel->getDateRange();
            $minDate = $dr['min_date'] ?: date('Y-m-d', strtotime('-2 years'));
            $maxDate = $dr['max_date'] ?: date('Y-m-d');
            $series = $forecastEngine->getDailySeries($minDate, $maxDate, $target);
            if (!in_array($modelType, ['moving_average','weighted_moving_average','linear_regression','exponential_smoothing'])) $modelType = 'linear_regression';
            $param = $modelType === 'moving_average' || $modelType === 'weighted_moving_average' ? $maPeriod : $alpha;
            $out = $callForecast($forecastEngine, $modelType, $series, $periodDays, $param);
            if (empty($out['forecast'])) throw new Exception("Forecast produced no output. Ensure there is enough sales data (at least 7 days).");
            $name = ucwords(str_replace('_',' ',$modelType)) . ' - ' . $periodDays . ' Days';
            if (empty($_POST['save'])) {   // preview only: do not write to forecast history
                $result['data'] = ['id' => null, 'forecast' => $out['forecast'], 'metrics' => ['mae' => $out['mae'], 'mse' => $out['mse'], 'rmse' => $out['rmse'], 'mape' => $out['mape'], 'accuracy' => $out['accuracy']]];
                break;
            }
            $fModel = new Forecast();
            $fId = $fModel->create([
                'name' => $name,
                'model_type' => $modelType,
                'forecast_period' => $_POST['period'] ?? '30_days',
                'target_type' => $target,
                'start_date' => $out['forecast'][0]['date'] ?? null,
                'end_date' => end($out['forecast'])['date'] ?? null,
                'ma_period' => $maPeriod,
                'alpha' => $alpha,
                'mae' => $out['mae'],
                'mse' => $out['mse'],
                'rmse' => $out['rmse'],
                'mape' => $out['mape'],
                'accuracy_score' => $out['accuracy'],
                'status' => 'completed',
                'created_by' => Session::userId()
            ]);
            foreach ($out['forecast'] as $row) {
                db()->query("INSERT INTO forecast_results (forecast_id, model_name, forecast_date, forecast_value, lower_bound, upper_bound) VALUES (?, ?, ?, ?, ?, ?)",
                    [$fId, $modelType, $row['date'], $row['value'], $row['lower'], $row['upper']]);
            }
            auth()->logActivity('forecast', 'Forecast generated: ' . $name);
            create_notification(Session::userId(), 'forecast', 'Forecast Complete', $name . ' completed with ' . $out['accuracy'] . '% accuracy.', url('forecast.php?id=' . $fId));
            $result['data'] = ['id' => $fId, 'forecast' => $out['forecast'], 'metrics' => ['mae' => $out['mae'], 'mse' => $out['mse'], 'rmse' => $out['rmse'], 'mape' => $out['mape'], 'accuracy' => $out['accuracy']]];
            break;

        case 'compare_forecasts':
            $periodDays = ['7_days'=>7,'30_days'=>30,'90_days'=>90,'365_days'=>365][$_GET['period'] ?? '30_days'] ?? 30;
            $target = $_GET['target'] ?? 'revenue';
            $dr = $saleModel->getDateRange();
            $series = $forecastEngine->getDailySeries($dr['min_date'] ?: date('Y-m-d', strtotime('-2 years')), $dr['max_date'] ?: date('Y-m-d'), $target);
            $models = ['moving_average', 'weighted_moving_average', 'linear_regression', 'exponential_smoothing'];
            $result['data'] = ['labels' => [], 'series' => [], 'metrics' => []];
            $firstForecast = null;
            foreach ($models as $m) {
                $param = $m === 'linear_regression' ? 0.3 : ($m === 'exponential_smoothing' ? 0.3 : 7);
                $out = $callForecast($forecastEngine, $m, $series, $periodDays, $param);
                if ($firstForecast === null) {
                    $firstForecast = $out['forecast'];
                    $result['data']['labels'] = array_map(fn($s) => date('M d', strtotime($s['date'])), $out['forecast']);
                }
                $result['data']['series'][] = ['name' => ucwords(str_replace('_',' ',$m)), 'data' => array_map(fn($s) => (float)$s['value'], $out['forecast'])];
                $result['data']['metrics'][] = ['model' => $m, 'mae' => $out['mae'], 'rmse' => $out['rmse'], 'mape' => $out['mape'], 'accuracy' => $out['accuracy']];
            }
            break;

        case 'activities':
            $limit = (int)($_GET['limit'] ?? 20);
            $result['data'] = (new ActivityLog())->recent($limit);
            break;

        case 'notifications':
            $limit = (int)($_GET['limit'] ?? 20);
            $user = Session::userId();
            $result['data'] = db()->query("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?", [$user, $limit])->fetchAll();
            $result['unread_count'] = (int)db()->query("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$user])->fetchColumn();
            break;

        case 'mark_notifs_read':
            db()->query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [Session::userId()]);
            $result['message'] = 'Marked as read';
            break;

        case 'dataset_summary':
            $recent = (new DatasetImport())->recent(5);
            $result['data'] = [
                'recent' => $recent,
                'total_imported' => array_sum(array_map(fn($r) => $r['imported_rows'] ?? 0, $recent)),
                'total_sales' => $saleModel->totalSalesCount(),
                'total_orders' => $orderModel->totalOrders(),
                'total_products' => (new Product())->totalProducts(),
                'total_customers' => (new Customer())->totalCustomers(),
                'date_range' => $saleModel->getDateRange()
            ];
            break;

        default:
            $sendJson(['success' => false, 'error' => 'Unknown type: ' . $type], 400);
    }

    echo json_encode($result, JSON_NUMERIC_CHECK);
} catch (Throwable $e) {
    $logDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'error-' . date('Y-m-d') . '.log', date('Y-m-d H:i:s') . ' | [data.php] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL, FILE_APPEND);
    $sendJson([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => APP_ENV === 'development' ? $e->getTraceAsString() : null,
    ], 500);
}
