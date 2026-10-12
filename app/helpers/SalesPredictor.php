<?php
require_once dirname(__FILE__) . '/Database.php';

/**
 * SalesPredictor
 * --------------
 * Predicts monthly sales (units and revenue) for a chosen YEAR, for one product,
 * one category, or the whole store, using the imported sales history.
 *
 * The method adapts to how much history exists, and always says what it did:
 *   - 24+ complete months : trend + seasonal pattern learned from the data
 *   - 12-23 months        : gentle seasonal pattern (shrunk toward flat) + trend
 *   - under 12 months     : trend / run-rate only (seasonality cannot be learned)
 * A trend is only used when it is statistically meaningful (|t| >= 2); otherwise the
 * forecast is a flat recent run-rate. Trends are damped so long horizons stay sane.
 */
class SalesPredictor {

    const Z80 = 1.2816; // 80% prediction interval

    /* ------------------------------------------------------------------ data */

    /** Overall date range + month coverage of the imported data. */
    public function dataRange() {
        $r = db()->query("SELECT MIN(sale_date) mn, MAX(sale_date) mx FROM sales")->fetch();
        return ['min' => $r['mn'] ?? null, 'max' => $r['mx'] ?? null];
    }

    /**
     * Complete months available in the dataset, as ['2024-01', ...].
     * A first/last month is dropped from fitting if the data covers < 85% of its days.
     * Returns [allMonths, fitMonths, notes].
     */
    public function monthWindow() {
        $r = $this->dataRange();
        if (!$r['min']) return [[], [], []];
        $min = new DateTime($r['min']); $max = new DateTime($r['max']);
        $all = [];
        $cur = new DateTime($min->format('Y-m-01'));
        $end = new DateTime($max->format('Y-m-01'));
        while ($cur <= $end) { $all[] = $cur->format('Y-m'); $cur->modify('+1 month'); }
        $fit = $all; $notes = [];
        // coverage of first month
        $firstDays = (int)(new DateTime($all[0] . '-01'))->format('t');
        $firstCover = ($firstDays - (int)$min->format('j') + 1) / $firstDays;
        if (count($fit) > 1 && $firstCover < 0.85) { array_shift($fit); $notes[] = "The first month ({$all[0]}) is only partly covered by the data, so it was left out of the model."; }
        $last = end($all);
        $lastDays = (int)(new DateTime($last . '-01'))->format('t');
        $lastCover = (int)$max->format('j') / $lastDays;
        if (count($fit) > 1 && $lastCover < 0.85) { array_pop($fit); $notes[] = "The last month ($last) is only partly covered by the data, so it was left out of the model."; }
        return [$all, $fit, $notes];
    }

    /** Monthly totals for a scope, keyed by 'YYYY-MM', for the given months (zero-filled). */
    public function monthlySeries($months, $productId = null, $categoryId = null) {
        $where = []; $params = [];
        if ($productId) { $where[] = 'product_id = ?'; $params[] = $productId; }
        if ($categoryId) { $where[] = 'category_id = ?'; $params[] = $categoryId; }
        $sql = "SELECT DATE_FORMAT(sale_date,'%Y-%m') ym, COALESCE(SUM(quantity),0) units, COALESCE(SUM(sales_amount),0) revenue, COALESCE(SUM(profit),0) profit
                FROM sales" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " GROUP BY ym";
        $rows = db()->query($sql, $params)->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['ym']] = ['units' => (float)$r['units'], 'revenue' => (float)$r['revenue'], 'profit' => (float)$r['profit']];
        $out = [];
        foreach ($months as $m) $out[$m] = $map[$m] ?? ['units' => 0.0, 'revenue' => 0.0, 'profit' => 0.0];
        return $out;
    }

    /** One query: monthly units+revenue for EVERY product (used for the all-products table). */
    public function monthlyByProduct($months) {
        $rows = db()->query("SELECT product_id, DATE_FORMAT(sale_date,'%Y-%m') ym, SUM(quantity) units, SUM(sales_amount) revenue
                             FROM sales WHERE product_id IS NOT NULL GROUP BY product_id, ym")->fetchAll();
        $by = [];
        foreach ($rows as $r) $by[(int)$r['product_id']][$r['ym']] = ['units' => (float)$r['units'], 'revenue' => (float)$r['revenue']];
        return $by;
    }

    /* ----------------------------------------------------------------- maths */

    private function mean($v) { return count($v) ? array_sum($v) / count($v) : 0.0; }

    private function std($v) {
        $n = count($v); if ($n < 2) return 0.0;
        $m = $this->mean($v); $s = 0.0;
        foreach ($v as $x) $s += ($x - $m) * ($x - $m);
        return sqrt($s / ($n - 1));
    }

    /** OLS on index 0..n-1 -> [slope, intercept, t-stat of the slope, residual std]. */
    private function ols($y) {
        $n = count($y);
        if ($n < 3) return ['slope' => 0.0, 'intercept' => $this->mean($y), 't' => 0.0, 'resid' => $this->std($y)];
        $mx = ($n - 1) / 2; $my = $this->mean($y);
        $sxx = 0.0; $sxy = 0.0;
        for ($i = 0; $i < $n; $i++) { $sxx += ($i - $mx) * ($i - $mx); $sxy += ($i - $mx) * ($y[$i] - $my); }
        $slope = $sxx > 0 ? $sxy / $sxx : 0.0;
        $icpt = $my - $slope * $mx;
        $sse = 0.0;
        for ($i = 0; $i < $n; $i++) { $e = $y[$i] - ($icpt + $slope * $i); $sse += $e * $e; }
        $resid = sqrt($sse / max(1, $n - 2));
        $se = ($sxx > 0) ? $resid / sqrt($sxx) : INF;
        if ($se > 1e-9 && is_finite($se)) $t = $slope / $se;
        else $t = (abs($slope) > 1e-9) ? ($slope > 0 ? 99.0 : -99.0) : 0.0;   // perfectly straight line
        return ['slope' => $slope, 'intercept' => $icpt, 't' => $t, 'resid' => $resid];
    }

    /** Seasonal indices (12 values, mean 1) from >=24 months via ratio to centred 12-month MA. */
    private function seasonalFull($y, $firstMonthNum) {
        $n = count($y);
        $ratios = array_fill(1, 12, []);
        for ($i = 6; $i < $n - 6; $i++) {
            $ma = 0.0;
            for ($k = -5; $k <= 5; $k++) $ma += $y[$i + $k];
            $ma += 0.5 * ($y[$i - 6] + $y[$i + 6]);
            $ma /= 12;
            if ($ma > 0) { $m = (($firstMonthNum - 1 + $i) % 12) + 1; $ratios[$m][] = $y[$i] / $ma; }
        }
        return $this->normalizeIndices($ratios);
    }

    /** Seasonal indices from 12-23 months: month value / mean, shrunk 50% toward 1. */
    private function seasonalShort($y, $firstMonthNum) {
        $n = count($y); $mu = $this->mean($y);
        $ratios = array_fill(1, 12, []);
        if ($mu > 0) for ($i = 0; $i < $n; $i++) { $m = (($firstMonthNum - 1 + $i) % 12) + 1; $ratios[$m][] = $y[$i] / $mu; }
        $idx = $this->normalizeIndices($ratios);
        foreach ($idx as $m => $v) $idx[$m] = 1 + 0.5 * ($v - 1);
        return $idx;
    }

    private function normalizeIndices($ratios) {
        $idx = [];
        foreach ($ratios as $m => $arr) $idx[$m] = count($arr) ? $this->mean($arr) : 1.0;
        $avg = $this->mean(array_values($idx));
        if ($avg > 0) foreach ($idx as $m => $v) $idx[$m] = $v / $avg;
        return $idx;
    }

    /**
     * Core forecaster. $y = chronological monthly values (complete months only),
     * $firstMonthNum = calendar month number (1-12) of $y[0], $h = months ahead.
     * Returns ['pred'=>[h], 'lower'=>[h], 'upper'=>[h], 'method'=>..., 'trend'=>..., 'seasonal'=>bool, 'sigma'=>float]
     */
    public function forecastSeries($y, $firstMonthNum, $h) {
        $n = count($y);
        if ($n === 0) return ['pred' => array_fill(0, $h, 0.0), 'lower' => array_fill(0, $h, 0.0), 'upper' => array_fill(0, $h, 0.0), 'method' => 'none', 'trend' => 'none', 'seasonal' => false, 'sigma' => 0.0];

        // 1. seasonality
        $seasonal = false; $S = array_fill(1, 12, 1.0);
        if ($n >= 24) { $S = $this->seasonalFull($y, $firstMonthNum); $seasonal = true; }
        elseif ($n >= 12) { $S = $this->seasonalShort($y, $firstMonthNum); $seasonal = true; }

        // 2. deseasonalise
        $d = [];
        for ($i = 0; $i < $n; $i++) { $m = (($firstMonthNum - 1 + $i) % 12) + 1; $d[] = $S[$m] > 0 ? $y[$i] / $S[$m] : $y[$i]; }

        // 3. level + (significant, damped) trend on the most recent window
        $win = $n >= 12 ? min($n, 24) : $n;
        $w = array_slice($d, -$win);
        $fit = $this->ols($w);
        $useTrend = ($win >= 4) && abs($fit['t']) >= 2.0;
        $phi = 0.9; $trendWord = 'flat';
        if ($useTrend) {
            $level = $fit['intercept'] + $fit['slope'] * ($win - 1);   // fitted level at last month
            $slope = $fit['slope'];
            $trendWord = $slope > 0 ? 'rising' : 'falling';
        } else {
            $recent = array_slice($d, -min($n, 6));
            $level = $this->mean($recent);
            $slope = 0.0;
        }

        // 4. spread of the model's own errors (in-sample, deseasonalised)
        $sigma = $useTrend ? $fit['resid'] : $this->std($w);
        if ($n < 4) $sigma = max($sigma, 0.35 * $this->mean($y));

        $pred = []; $lo = []; $hi = [];
        $cum = 0.0;
        for ($k = 1; $k <= $h; $k++) {
            $cum += pow($phi, $k);
            $base = $level + $slope * $cum;
            $m = (($firstMonthNum - 1 + $n - 1 + $k) % 12) + 1;
            $val = max(0.0, $base * $S[$m]);
            $band = self::Z80 * $sigma * sqrt(1 + 0.08 * $k) * $S[$m];
            $pred[] = $val; $lo[] = max(0.0, $val - $band); $hi[] = $val + $band;
        }
        $method = $n >= 24 ? 'trend + learned seasonality' : ($n >= 12 ? 'trend + light seasonality' : 'recent run-rate' . ($useTrend ? ' + trend' : ''));
        return ['pred' => $pred, 'lower' => $lo, 'upper' => $hi, 'method' => $method, 'trend' => $trendWord, 'seasonal' => $seasonal, 'sigma' => $sigma];
    }

    /** Back-test: hold out the last k months, forecast them from the rest, return MAPE/accuracy (or null). */
    public function backtest($y, $firstMonthNum) {
        $n = count($y);
        $k = $n >= 12 ? 3 : ($n >= 6 ? 2 : 0);
        if ($k === 0 || $n - $k < 3) return null;
        $train = array_slice($y, 0, $n - $k); $test = array_slice($y, -$k);
        $f = $this->forecastSeries($train, $firstMonthNum, $k);
        $sumAbs = 0.0; $sumAct = 0.0;
        for ($i = 0; $i < $k; $i++) { $sumAbs += abs($test[$i] - $f['pred'][$i]); $sumAct += $test[$i]; }
        if ($sumAct <= 0) return null;
        $wape = $sumAbs / $sumAct * 100;          // weighted absolute % error
        return ['months_tested' => $k, 'error_pct' => round($wape, 1), 'accuracy_pct' => round(max(0, 100 - $wape), 1)];
    }

    /** 'actual' = month is in the data (complete, or a partial month before the model window); 'none' = before data starts; else 'predicted'. */
    private function classify($key, $all, $fit) {
        if (in_array($key, $fit, true)) return 'actual';
        if ($key < $all[0]) return 'none';
        if ($key < $fit[0] && in_array($key, $all, true)) return 'actual';
        return 'predicted';
    }

    /* ------------------------------------------------------------ public API */

    /**
     * Predict a calendar year for a scope. Months already in the data are shown as ACTUAL,
     * later months are PREDICTED.
     */
    public function predictYear($year, $productId = null, $categoryId = null) {
        $year = (int)$year;
        list($all, $fit, $winNotes) = $this->monthWindow();
        if (!$all) return ['ok' => false, 'error' => 'No sales data yet. Import a dataset first.'];
        $firstYear = (int)substr($all[0], 0, 4);
        $lastMonth = end($all);
        $lastYear = (int)substr($lastMonth, 0, 4);
        if ($year < $firstYear) return ['ok' => false, 'error' => "Your data starts in $firstYear, so there is no history to show or predict for $year."];

        $series = $this->monthlySeries($all, $productId, $categoryId);
        $fitSeries = array_intersect_key($series, array_flip($fit));
        $firstMonthNum = (int)substr($fit[0], 5, 2);

        $units = array_column(array_values($fitSeries), 'units');
        $rev = array_column(array_values($fitSeries), 'revenue');
        $prof = array_column(array_values($fitSeries), 'profit');

        // horizon: from first month after the last FIT month to Dec of target year
        $lastFit = end($fit);
        $lastFitDt = new DateTime($lastFit . '-01');
        $target = new DateTime($year . '-12-01');
        $h = 0;
        if ($target > $lastFitDt) { $h = ((int)$target->format('Y') - (int)$lastFitDt->format('Y')) * 12 + ((int)$target->format('n') - (int)$lastFitDt->format('n')); }

        $fu = $this->forecastSeries($units, $firstMonthNum, max(1, $h));
        $fr = $this->forecastSeries($rev, $firstMonthNum, max(1, $h));
        $fp = $this->forecastSeries($prof, $firstMonthNum, max(1, $h));

        $labels = []; $monthKeys = []; $actualU = []; $actualR = []; $predU = []; $predR = []; $loR = []; $hiR = []; $loU = []; $hiU = [];
        $status = []; $profit = [];
        $names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        for ($mo = 1; $mo <= 12; $mo++) {
            $key = sprintf('%04d-%02d', $year, $mo);
            $labels[] = $names[$mo - 1]; $monthKeys[] = $key;
            $cls = $this->classify($key, $all, $fit);
            if ($cls === 'actual') {
                $actualU[] = round($series[$key]['units'], 0); $actualR[] = round($series[$key]['revenue'], 2); $profit[] = round($series[$key]['profit'], 2);
                $predU[] = null; $predR[] = null; $loR[] = null; $hiR[] = null; $loU[] = null; $hiU[] = null; $status[] = 'actual';
            } elseif ($cls === 'none') {
                $actualU[] = null; $actualR[] = null; $predU[] = null; $predR[] = null; $loR[] = null; $hiR[] = null; $loU[] = null; $hiU[] = null; $status[] = 'none'; $profit[] = null;
            } else {
                $steps = ((int)substr($key, 0, 4) - (int)$lastFitDt->format('Y')) * 12 + ($mo - (int)$lastFitDt->format('n'));
                $i = $steps - 1;
                $actualU[] = null; $actualR[] = null;
                $predU[] = round($fu['pred'][$i], 0); $predR[] = round($fr['pred'][$i], 2);
                $loU[] = round($fu['lower'][$i], 0); $hiU[] = round($fu['upper'][$i], 0);
                $loR[] = round($fr['lower'][$i], 2); $hiR[] = round($fr['upper'][$i], 2);
                $profit[] = round($fp['pred'][$i], 2);
                $status[] = 'predicted';
            }
        }

        $sum = function ($a) { return array_sum(array_map(fn($v) => $v ?? 0, $a)); };
        $nPred = count(array_filter($status, fn($s) => $s === 'predicted'));
        $nAct = count(array_filter($status, fn($s) => $s === 'actual'));

        $totalU = $sum($actualU) + $sum($predU);
        $totalR = $sum($actualR) + $sum($predR);
        $totalLoR = $sum($actualR) + $sum($loR);
        $totalHiR = $sum($actualR) + $sum($hiR);

        $notes = $winNotes;
        $nFit = count($fit);
        if ($nFit < 12) $notes[] = "Your data covers only $nFit complete months, so the model cannot learn seasonal patterns (e.g. busy holiday months). Predictions are based on your recent run-rate" . ($fr['trend'] !== 'flat' ? " and a $fr[trend] trend" : '') . ". Import 12-24+ months of history for a stronger forecast.";
        elseif ($nFit < 24) $notes[] = "With $nFit months of history the seasonal pattern is only lightly applied. 24+ months gives much more reliable seasonality.";
        if ($nPred === 0) $notes[] = "$year is fully covered by your data, so these are actual figures, not predictions.";
        if ($h > 12) $notes[] = "Predicting " . ($h) . " months beyond your last data point: uncertainty grows the further ahead you look. Use the range, not just the single number.";

        $bt = $this->backtest($rev, $firstMonthNum);

        return [
            'ok' => true,
            'year' => $year,
            'scope' => ['product_id' => $productId, 'category_id' => $categoryId],
            'labels' => $labels, 'month_keys' => $monthKeys, 'status' => $status,
            'actual_units' => $actualU, 'actual_revenue' => $actualR,
            'pred_units' => $predU, 'pred_revenue' => $predR,
            'lower_units' => $loU, 'upper_units' => $hiU,
            'lower_revenue' => $loR, 'upper_revenue' => $hiR,
            'summary' => [
                'total_units' => round($totalU, 0), 'total_revenue' => round($totalR, 2),
                'low_revenue' => round($totalLoR, 2), 'high_revenue' => round($totalHiR, 2),
                'actual_months' => $nAct, 'predicted_months' => $nPred,
                'avg_monthly_revenue' => round($totalR / 12, 2),
            ],
            'model' => ['method' => $fr['method'], 'trend' => $fr['trend'], 'months_of_history' => $nFit,
                        'history_from' => $fit[0], 'history_to' => $lastFit, 'seasonality' => $fr['seasonal'], 'backtest' => $bt],
            'history' => ['months' => array_keys($fitSeries), 'revenue' => array_map(fn($v) => round($v, 2), $rev), 'units' => array_map(fn($v) => round($v, 0), $units)],
            'notes' => $notes,
        ];
    }

    /** Predicted units + revenue for EVERY product for a year (table view). */
    public function predictAllProducts($year) {
        $year = (int)$year;
        list($all, $fit, $notes) = $this->monthWindow();
        if (!$all) return ['ok' => false, 'error' => 'No sales data yet.'];
        $firstMonthNum = (int)substr($fit[0], 5, 2);
        $lastFit = end($fit); $lastFitDt = new DateTime($lastFit . '-01');
        $target = new DateTime($year . '-12-01');
        $h = max(0, ((int)$target->format('Y') - (int)$lastFitDt->format('Y')) * 12 + ((int)$target->format('n') - (int)$lastFitDt->format('n')));
        $by = $this->monthlyByProduct($all);
        $prods = db()->query("SELECT p.id, p.name, p.product_code, c.name category FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name")->fetchAll();
        $rows = [];
        foreach ($prods as $p) {
            $m = $by[(int)$p['id']] ?? [];
            $u = []; $r = [];
            foreach ($fit as $k) { $u[] = $m[$k]['units'] ?? 0.0; $r[] = $m[$k]['revenue'] ?? 0.0; }
            $actU = 0.0; $actR = 0.0; $predU = 0.0; $predR = 0.0;
            $fu = $this->forecastSeries($u, $firstMonthNum, max(1, $h));
            $fr = $this->forecastSeries($r, $firstMonthNum, max(1, $h));
            for ($mo = 1; $mo <= 12; $mo++) {
                $key = sprintf('%04d-%02d', $year, $mo);
                $cls = $this->classify($key, $all, $fit);
                if ($cls === 'actual') {
                    $actU += $m[$key]['units'] ?? 0; $actR += $m[$key]['revenue'] ?? 0;
                } elseif ($cls === 'predicted') {
                    $steps = ($year - (int)$lastFitDt->format('Y')) * 12 + ($mo - (int)$lastFitDt->format('n'));
                    $predU += $fu['pred'][$steps - 1]; $predR += $fr['pred'][$steps - 1];
                }
            }
            $rows[] = ['product_id' => (int)$p['id'], 'product' => $p['name'], 'code' => $p['product_code'], 'category' => $p['category'],
                       'units' => round($actU + $predU, 0), 'revenue' => round($actR + $predR, 2),
                       'actual_revenue' => round($actR, 2), 'predicted_revenue' => round($predR, 2), 'trend' => $fr['trend']];
        }
        usort($rows, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return ['ok' => true, 'year' => $year, 'rows' => $rows, 'months_of_history' => count($fit)];
    }

    /** Dropdown options: years you can pick + products/categories that have sales. */
    public function options() {
        $r = $this->dataRange();
        if (!$r['min']) return ['has_data' => false, 'years' => [], 'products' => [], 'categories' => [], 'range' => $r];
        $y0 = (int)substr($r['min'], 0, 4); $y1 = (int)substr($r['max'], 0, 4);
        $years = range($y0, $y1 + 5);
        $products = db()->query("SELECT p.id, p.name, p.product_code, c.name category FROM products p LEFT JOIN categories c ON p.category_id = c.id
                                 WHERE EXISTS (SELECT 1 FROM sales s WHERE s.product_id = p.id) ORDER BY p.name")->fetchAll();
        $cats = db()->query("SELECT c.id, c.name FROM categories c WHERE EXISTS (SELECT 1 FROM sales s WHERE s.category_id = c.id) ORDER BY c.name")->fetchAll();
        return ['has_data' => true, 'years' => $years, 'data_last_year' => $y1, 'products' => $products, 'categories' => $cats, 'range' => $r];
    }
}
