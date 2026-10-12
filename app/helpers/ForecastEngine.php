<?php
require_once dirname(__FILE__) . '/Database.php';
require_once dirname(__FILE__) . '/Session.php';

class ForecastEngine {

    public function getDailySeries($startDate, $endDate, $targetType = 'revenue') {
        $start = $startDate ?: date('Y-m-d', strtotime('-1 year'));
        $end = $endDate ?: date('Y-m-d');
        $select = match($targetType) {
            'profit' => 'COALESCE(SUM(profit), 0)',
            'orders' => 'COUNT(DISTINCT order_id)',
            'sales' => 'COUNT(*)',
            default => 'COALESCE(SUM(sales_amount), 0)'
        };
        $rows = db()->query(
            "SELECT sale_date as date, {$select} as value 
             FROM sales 
             WHERE sale_date BETWEEN ? AND ? 
             GROUP BY sale_date 
             ORDER BY sale_date ASC",
            [$start, $end]
        )->fetchAll();
        $filled = [];
        $current = strtotime($start);
        $endT = strtotime($end);
        $rowMap = [];
        foreach ($rows as $r) $rowMap[$r['date']] = (float)$r['value'];
        while ($current <= $endT) {
            $key = date('Y-m-d', $current);
            $filled[] = ['date' => $key, 'value' => $rowMap[$key] ?? 0.0];
            $current = strtotime('+1 day', $current);
        }
        $values = array_column($filled, 'value');
        $count = count($values);
        if ($count < 2) return $filled;
        $smoothed = $filled;
        $window = 3;
        for ($i = 0; $i < $count; $i++) {
            $sum = 0; $n = 0;
            for ($j = max(0, $i - $window); $j <= min($count - 1, $i + $window); $j++) {
                $sum += $values[$j];
                $n++;
            }
            if ($n > 0) $smoothed[$i]['value'] = $sum / $n;
        }
        return $smoothed;
    }

    public function movingAverage($series, $periods = 30, $maPeriod = 7) {
        $values = array_column($series, 'value');
        $n = count($values);
        if ($n < 2) return $this->emptyResult($periods);
        $maPeriod = max(2, min($maPeriod, max(2, (int)($n / 3))));
        $forecast = [];
        $trainSize = max(1, (int)($n * 0.8));
        $train = array_slice($values, 0, $trainSize);
        $test = array_slice($values, $trainSize);
        $lastDate = end($series)['date'];
        $ma = [];
        for ($i = $maPeriod - 1; $i < $trainSize; $i++) {
            $slice = array_slice($train, $i - $maPeriod + 1, $maPeriod);
            $ma[] = array_sum($slice) / $maPeriod;
        }
        $predTest = [];
        $maV = array_slice($train, -$maPeriod);
        $lastMA = array_sum($maV) / count($maV);
        for ($i = 0; $i < count($test); $i++) {
            $predTest[] = $lastMA;
        }
        $metrics = $this->calculateMetrics($test, $predTest);
        $maValues = array_slice($values, -$maPeriod);
        $startValue = array_sum($maValues) / count($maValues);
        $current = strtotime($lastDate);
        $window = 7;
        $recent = array_slice($values, -min(30, $n));
        $dailyGrowth = 0;
        if (count($recent) > 1) {
            $trend = $this->linearTrend($recent);
            $dailyGrowth = $trend['slope'];
        }
        for ($i = 1; $i <= $periods; $i++) {
            $current = strtotime('+' . $i . ' day', strtotime($lastDate));
            $growthFactor = 1 + ($dailyGrowth / max(1, $startValue)) * $i;
            $val = $startValue * $growthFactor;
            if ($val < 0) $val = 0;
            $std = $this->stdDev(array_slice($values, -min(90, $n)));
            $ci = 1.96 * $std;
            $forecast[] = ['date' => date('Y-m-d', $current), 'value' => round($val, 2), 'lower' => round(max(0, $val - $ci), 2), 'upper' => round($val + $ci, 2)];
        }
        return array_merge($metrics, ['forecast' => $forecast]);
    }

    public function weightedMovingAverage($series, $periods = 30, $maPeriod = 7) {
        $values = array_column($series, 'value');
        $n = count($values);
        if ($n < 2) return $this->emptyResult($periods);
        $maPeriod = max(2, min($maPeriod, max(2, (int)($n / 3))));
        $weights = range(1, $maPeriod);
        $weightSum = array_sum($weights);
        $lastDate = end($series)['date'];
        $trainSize = max(1, (int)($n * 0.8));
        $train = array_slice($values, 0, $trainSize);
        $test = array_slice($values, $trainSize);
        $wma = [];
        for ($i = $maPeriod - 1; $i < $trainSize; $i++) {
            $slice = array_slice($train, $i - $maPeriod + 1, $maPeriod);
            $sum = 0;
            foreach ($slice as $k => $v) $sum += $v * $weights[$k];
            $wma[] = $sum / $weightSum;
        }
        $maV = array_slice($train, -$maPeriod);
        $lastWMA = 0;
        foreach ($maV as $k => $v) $lastWMA += $v * $weights[$k];
        $lastWMA /= $weightSum;
        $predTest = array_fill(0, count($test), $lastWMA);
        $metrics = $this->calculateMetrics($test, $predTest);
        $lastVals = array_slice($values, -$maPeriod);
        $startValue = 0;
        foreach ($lastVals as $k => $v) $startValue += $v * $weights[$k];
        $startValue /= $weightSum;
        $recent = array_slice($values, -min(30, $n));
        $trend = $this->linearTrend($recent);
        $dailyGrowth = $trend['slope'];
        $forecast = [];
        $std = $this->stdDev(array_slice($values, -min(90, $n)));
        $ci = 1.96 * $std;
        for ($i = 1; $i <= $periods; $i++) {
            $d = strtotime('+' . $i . ' day', strtotime($lastDate));
            $growthFactor = 1 + ($dailyGrowth / max(1, $startValue)) * $i;
            $val = max(0, $startValue * $growthFactor);
            $forecast[] = ['date' => date('Y-m-d', $d), 'value' => round($val, 2), 'lower' => round(max(0, $val - $ci), 2), 'upper' => round($val + $ci, 2)];
        }
        return array_merge($metrics, ['forecast' => $forecast]);
    }

    public function linearRegression($series, $periods = 30, $unused = 0) {
        $values = array_column($series, 'value');
        $n = count($values);
        if ($n < 2) return $this->emptyResult($periods);
        $trend = $this->linearTrend($values);
        $slope = $trend['slope'];
        $intercept = $trend['intercept'];
        $lastDate = end($series)['date'];
        $trainSize = max(1, (int)($n * 0.8));
        $train = array_slice($values, 0, $trainSize);
        $test = array_slice($values, $trainSize);
        $trainTrend = $this->linearTrend($train);
        $predTest = [];
        for ($i = 0; $i < count($test); $i++) {
            $x = $trainSize + $i;
            $predTest[] = $trainTrend['slope'] * $x + $trainTrend['intercept'];
        }
        $metrics = $this->calculateMetrics($test, $predTest);
        $std = $this->stdDev(array_slice($values, -min(90, $n)));
        $baseCI = 1.96 * $std;
        $forecast = [];
        for ($i = 1; $i <= $periods; $i++) {
            $x = $n + $i;
            $val = $slope * $x + $intercept;
            if ($val < 0) $val = 0;
            $d = strtotime('+' . $i . ' day', strtotime($lastDate));
            $ci = $baseCI * (1 + $i * 0.03);
            $forecast[] = ['date' => date('Y-m-d', $d), 'value' => round($val, 2), 'lower' => round(max(0, $val - $ci), 2), 'upper' => round($val + $ci, 2)];
        }
        return array_merge($metrics, ['forecast' => $forecast]);
    }

    public function exponentialSmoothing($series, $periods = 30, $alpha = 0.3) {
        $values = array_column($series, 'value');
        $n = count($values);
        if ($n < 2) return $this->emptyResult($periods);
        $alpha = max(0.05, min(0.95, $alpha));
        $beta = 0.1;
        $level = $values[0];
        $trend = ($values[min(5, $n - 1)] - $values[0]) / min(5, $n - 1);
        $smoothed = [$level];
        for ($i = 1; $i < $n; $i++) {
            $prevLevel = $level;
            $level = $alpha * $values[$i] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $prevLevel) + (1 - $beta) * $trend;
            $smoothed[] = $level;
        }
        $trainSize = max(1, (int)($n * 0.8));
        $lvl = $values[0];
        $tr = ($values[min(5, $trainSize - 1)] - $values[0]) / min(5, $trainSize - 1);
        for ($i = 1; $i < $trainSize; $i++) {
            $pl = $lvl;
            $lvl = $alpha * $values[$i] + (1 - $alpha) * ($lvl + $tr);
            $tr = $beta * ($lvl - $pl) + (1 - $beta) * $tr;
        }
        $test = array_slice($values, $trainSize);
        $predTest = [];
        for ($i = 1; $i <= count($test); $i++) {
            $predTest[] = max(0, $lvl + $tr * $i);
        }
        $metrics = $this->calculateMetrics($test, $predTest);
        $lastDate = end($series)['date'];
        $std = $this->stdDev(array_slice($values, -min(90, $n)));
        $baseCI = 1.96 * $std;
        $forecast = [];
        for ($i = 1; $i <= $periods; $i++) {
            $val = $level + $trend * $i;
            if ($val < 0) $val = 0;
            $d = strtotime('+' . $i . ' day', strtotime($lastDate));
            $ci = $baseCI * (1 + $i * 0.05);
            $forecast[] = ['date' => date('Y-m-d', $d), 'value' => round($val, 2), 'lower' => round(max(0, $val - $ci), 2), 'upper' => round($val + $ci, 2)];
        }
        return array_merge($metrics, ['forecast' => $forecast]);
    }

    private function linearTrend($values) {
        $n = count($values);
        if ($n < 2) return ['slope' => 0, 'intercept' => $values[0] ?? 0, 'r2' => 0];
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $sumX += $x;
            $sumY += $values[$i];
            $sumXY += $x * $values[$i];
            $sumX2 += $x * $x;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if ($denom == 0) return ['slope' => 0, 'intercept' => $sumY / $n, 'r2' => 0];
        $slope = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;
        $ssTot = 0; $ssRes = 0;
        $mean = $sumY / $n;
        for ($i = 0; $i < $n; $i++) {
            $pred = $slope * $i + $intercept;
            $ssTot += pow($values[$i] - $mean, 2);
            $ssRes += pow($values[$i] - $pred, 2);
        }
        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;
        return ['slope' => $slope, 'intercept' => $intercept, 'r2' => $r2];
    }

    private function stdDev($values) {
        $n = count($values);
        if ($n < 2) return 0;
        $mean = array_sum($values) / $n;
        $variance = 0;
        foreach ($values as $v) $variance += pow($v - $mean, 2);
        return sqrt($variance / ($n - 1));
    }

    private function calculateMetrics($actual, $predicted) {
        $n = count($actual);
        if ($n === 0) return ['mae' => 0, 'mse' => 0, 'rmse' => 0, 'mape' => 0, 'accuracy' => 100];
        $mae = $mse = $mapeSum = 0;
        $validCount = 0;
        for ($i = 0; $i < $n; $i++) {
            $a = $actual[$i] ?? 0;
            $p = $predicted[$i] ?? 0;
            $err = abs($a - $p);
            $mae += $err;
            $mse += $err * $err;
            if ($a != 0) {
                $mapeSum += abs($err / $a) * 100;
                $validCount++;
            }
        }
        $mae /= $n;
        $mse /= $n;
        $rmse = sqrt($mse);
        $mape = $validCount > 0 ? $mapeSum / $validCount : 0;
        $accuracy = max(0, min(100, 100 - $mape));
        if ($mape > 100) $accuracy = max(0, 100 - sqrt($mape));
        $meanActual = $n > 0 ? array_sum($actual) / $n : 0;
        if ($meanActual > 0 && $mae / $meanActual < 0.1) {
            $accuracy = max($accuracy, 100 - ($mae / $meanActual * 100));
        }
        return [
            'mae' => round($mae, 4),
            'mse' => round($mse, 4),
            'rmse' => round($rmse, 4),
            'mape' => round(min(100, $mape), 2),
            'accuracy' => round($accuracy, 2)
        ];
    }

    private function emptyResult($periods) {
        $forecast = [];
        for ($i = 1; $i <= $periods; $i++) {
            $d = strtotime('+' . $i . ' days');
            $forecast[] = ['date' => date('Y-m-d', $d), 'value' => 0, 'lower' => 0, 'upper' => 0];
        }
        return ['mae' => 0, 'mse' => 0, 'rmse' => 0, 'mape' => 0, 'accuracy' => 0, 'forecast' => $forecast];
    }
}
