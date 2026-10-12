<?php
require_once dirname(__FILE__) . '/Database.php';
require_once dirname(__FILE__) . '/Session.php';

class DatasetImporter {
    private $errors = [];
    private $stats = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'errors' => 0, 'categories' => 0, 'regions' => 0, 'customers' => 0, 'products' => 0, 'orders' => 0, 'sales' => 0];
    private $importId = null;
    private $seen = ['orders' => [], 'customers' => [], 'products' => [], 'categories' => [], 'regions' => []];

    public function importCSV($filepath, $filename, $userId = null, $truncate = false) {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        $userId = $userId ?? Session::userId();
        $this->importId = $this->createImportRecord($filename, $filepath, 'csv', filesize($filepath), $userId);
        try {
            $handle = fopen($filepath, 'r');
            if (!$handle) throw new Exception('Cannot open file');
            $headers = fgetcsv($handle, 0, ',', '"');
            if (!$headers) throw new Exception('Empty CSV file');
            $headers = array_map(fn($h) => strtolower(trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF")), $headers);
            $mapping = $this->mapColumns($headers);
            if ($truncate) $this->truncateData();
            $rowNum = 0;
            db()->beginTransaction();
            while (($row = fgetcsv($handle, 0, ',', '"')) !== FALSE) {
                $rowNum++;
                $this->stats['total']++;
                try {
                    $data = [];
                    foreach ($mapping as $key => $idx) {
                        if (isset($row[$idx])) $data[$key] = trim($row[$idx]);
                    }
                    if (empty($data) || (empty($data['order_id']) && empty($data['row_id']))) {
                        $this->stats['errors']++;
                        continue;
                    }
                    $result = $this->importRow($data);
                    if ($result === 'duplicate') $this->stats['duplicates']++;
                    elseif ($result) $this->stats['imported']++;
                    else $this->stats['errors']++;
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    $this->errors[] = "Row $rowNum: " . $e->getMessage();
                }
            }
            fclose($handle);
            db()->commit();
            $status = $this->stats['errors'] > 0 ? ($this->stats['imported'] > 0 ? 'partial' : 'failed') : 'completed';
            $this->updateImportRecord($status, $this->stats);
            auth()->logActivity('import', "Dataset imported: $filename. Imported {$this->stats['imported']} rows.");
            create_notification($userId, 'dataset', 'Dataset Import Complete', "$filename: {$this->stats['imported']} rows imported, {$this->stats['duplicates']} duplicates, {$this->stats['errors']} errors.", url('dataset.php'));
            return ['success' => true, 'stats' => $this->stats, 'errors' => array_slice($this->errors, 0, 50), 'import_id' => $this->importId];
        } catch (Exception $e) {
            db()->rollBack();
            $this->updateImportRecord('failed', $this->stats, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'stats' => $this->stats, 'errors' => array_slice($this->errors, 0, 50), 'import_id' => $this->importId];
        }
    }

    public function importJSONRows($rows, $filename, $userId = null, $truncate = false) {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        $userId = $userId ?? Session::userId();
        $estimatedSize = strlen(json_encode($rows));
        $this->importId = $this->createImportRecord($filename, 'memory:json', 'xlsx', $estimatedSize, $userId);
        try {
            if ($truncate) $this->truncateData();
            db()->beginTransaction();
            foreach ($rows as $rowNum => $rawRow) {
                $this->stats['total']++;
                try {
                    $data = $this->normalizeData($rawRow);
                    if (empty($data) || (empty($data['order_id']) && empty($data['row_id']))) {
                        $this->stats['errors']++;
                        continue;
                    }
                    $result = $this->importRow($data);
                    if ($result === 'duplicate') $this->stats['duplicates']++;
                    elseif ($result) $this->stats['imported']++;
                    else $this->stats['errors']++;
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    $this->errors[] = "Row " . ($rowNum + 1) . ": " . $e->getMessage();
                }
            }
            db()->commit();
            $status = $this->stats['errors'] > 0 ? ($this->stats['imported'] > 0 ? 'partial' : 'failed') : 'completed';
            $this->updateImportRecord($status, $this->stats);
            auth()->logActivity('import', "Dataset imported: $filename. Imported {$this->stats['imported']} rows.");
            create_notification($userId, 'dataset', 'Dataset Import Complete', "$filename: {$this->stats['imported']} rows imported.", url('dataset.php'));
            return ['success' => true, 'stats' => $this->stats, 'errors' => array_slice($this->errors, 0, 50), 'import_id' => $this->importId];
        } catch (Exception $e) {
            db()->rollBack();
            $this->updateImportRecord('failed', $this->stats, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'stats' => $this->stats, 'errors' => array_slice($this->errors, 0, 50), 'import_id' => $this->importId];
        }
    }

    private function mapColumns($headers) {
        $map = [];
        $aliases = [
            'order_id' => ['order id','order_id','orderid','order no','orderno','order number'],
            'row_id' => ['row id','row_id','rowid','row','id'],
            'order_date' => ['order date','order_date','orderdate','order_date_pst'],
            'ship_date' => ['ship date','ship_date','shipdate','ship_date_pst'],
            'ship_mode' => ['ship mode','ship_mode','shipmode','shipping mode'],
            'customer_id' => ['customer id','customer_id','cust_id','customerid','customer number'],
            'customer_name' => ['customer name','customer_name','customername','customer','name'],
            'segment' => ['segment','customer_segment','customer segment'],
            'country' => ['country','country/region'],
            'city' => ['city'],
            'state' => ['state','province','state/province'],
            'postal_code' => ['postal code','postal_code','postcode','postalcode','zip','zip code','zipcode'],
            'region' => ['region','market','region/area'],
            'product_id' => ['product id','product_id','productid','prod_id','item id'],
            'category' => ['category','main_category','product category'],
            'sub_category' => ['sub-category','sub_category','subcategory','sub category'],
            'product_name' => ['product name','product_name','productname','product','item','item name'],
            'sales' => ['sales','sales_amount','salesamount','revenue','amount','total'],
            'quantity' => ['quantity','qty','count','units'],
            'discount' => ['discount','disc','discount_rate'],
            'profit' => ['profit','income','net_profit','earnings'],
            'priority' => ['order priority','priority','order_priority','delivery priority'],
            'unit_price' => ['unit price','unit_price','price','unitprice','cost_per_unit'],
            'cost' => ['cost','cogs','unit_cost','cost_price']
        ];
        foreach ($headers as $i => $h) {
            $h = strtolower(trim($h));
            foreach ($aliases as $key => $list) {
                if (in_array($h, $list) || $h === $key) { $map[$key] = $i; break; }
            }
        }
        return $map;
    }

    private $jsonMap = null;
    private function normalizeData($rawRow) {
        // Map spreadsheet headers (any alias/case/spacing) to internal keys, same as the CSV path
        if ($this->jsonMap === null) {
            $keys = array_keys($rawRow);
            $lower = array_map(fn($h) => strtolower(trim((string)$h, " \t\n\r\0\x0B\xEF\xBB\xBF")), $keys);
            $this->jsonMap = [];
            foreach ($this->mapColumns($lower) as $internal => $idx) $this->jsonMap[$internal] = $keys[$idx];
        }
        $data = [];
        foreach ($this->jsonMap as $internal => $origKey) {
            if (isset($rawRow[$origKey])) $data[$internal] = trim((string)$rawRow[$origKey]);
        }
        return $data;
    }

    private function importRow($data) {
        $catName = $this->nonEmpty($data, 'category');
        $subCat = $this->nonEmpty($data, 'sub_category');
        $regName = $this->nonEmpty($data, 'region') ?: ($this->nonEmpty($data, 'country') ?: 'Unknown');
        $country = $this->nonEmpty($data, 'country') ?: 'United States';
        $state = $this->nonEmpty($data, 'state');
        $city = $this->nonEmpty($data, 'city');
        $postal = $this->nonEmpty($data, 'postal_code');

        $categoryId = null;
        if ($catName) {
            if (isset($this->seen['categories'][$catName])) {
                $categoryId = $this->seen['categories'][$catName];
            } else {
                $existing = db()->query("SELECT id FROM categories WHERE name = ? LIMIT 1", [$catName])->fetch();
                if ($existing) {
                    $categoryId = $existing['id'];
                } else {
                    db()->query("INSERT INTO categories (name, description) VALUES (?, ?)", [$catName, $subCat ? "Main category: $catName, primary sub: $subCat" : null]);
                    $categoryId = db()->lastInsertId();
                    $this->stats['categories']++;
                }
                $this->seen['categories'][$catName] = $categoryId;
            }
        }

        $regionId = null;
        $regKey = ($regName ?? 'Unknown') . '|' . ($country ?? '') . '|' . ($state ?? '') . '|' . ($city ?? '');
        if (isset($this->seen['regions'][$regKey])) {
            $regionId = $this->seen['regions'][$regKey];
        } else {
            $existing = db()->query("SELECT id FROM regions WHERE name <=> ? AND country <=> ? AND state <=> ? AND city <=> ? LIMIT 1", [$regName, $country, $state, $city])->fetch();
            if ($existing) {
                $regionId = $existing['id'];
            } else {
                db()->query("INSERT INTO regions (name, country, state, city, postal_code) VALUES (?, ?, ?, ?, ?)", [$regName, $country, $state, $city, $postal]);
                $regionId = db()->lastInsertId();
                $this->stats['regions']++;
            }
            $this->seen['regions'][$regKey] = $regionId;
        }

        $custCode = $this->nonEmpty($data, 'customer_id') ?: ('CUST-' . substr(preg_replace('/[^A-Za-z0-9]/', '', $data['customer_name'] ?? ''), 0, 10));
        $custName = $this->nonEmpty($data, 'customer_name') ?: 'Unknown Customer';
        $customerId = null;
        if (isset($this->seen['customers'][$custCode])) {
            $customerId = $this->seen['customers'][$custCode];
        } else {
            $existing = db()->query("SELECT id FROM customers WHERE customer_code = ? LIMIT 1", [$custCode])->fetch();
            if ($existing) {
                $customerId = $existing['id'];
            } else {
                db()->query("INSERT INTO customers (customer_code, name, segment, region_id) VALUES (?, ?, ?, ?)", [$custCode, $custName, $this->nonEmpty($data, 'segment'), $regionId]);
                $customerId = db()->lastInsertId();
                $this->stats['customers']++;
            }
            $this->seen['customers'][$custCode] = $customerId;
        }

        $prodCode = $this->nonEmpty($data, 'product_id') ?: ('PROD-' . substr(md5(($data['product_name'] ?? '') . '|' . ($subCat ?? '')), 0, 12));
        $prodName = $this->nonEmpty($data, 'product_name') ?: 'Unknown Product';
        $productId = null;
        if (isset($this->seen['products'][$prodCode])) {
            $productId = $this->seen['products'][$prodCode];
        } else {
            $existing = db()->query("SELECT id FROM products WHERE product_code = ? LIMIT 1", [$prodCode])->fetch();
            if ($existing) {
                $productId = $existing['id'];
            } else {
                $unitPrice = (float)($data['unit_price'] ?? 0) ?: ($this->parseMoney($data, 'sales') / max(1, (int)($data['quantity'] ?? 1)));
                db()->query("INSERT INTO products (product_code, name, description, category_id, sub_category, unit_price, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, 100)", [$prodCode, $prodName, $prodName, $categoryId, $subCat, $unitPrice]);
                $productId = db()->lastInsertId();
                $this->stats['products']++;
            }
            $this->seen['products'][$prodCode] = $productId;
        }

        $orderCode = $this->nonEmpty($data, 'order_id') ?: ('ORD-' . ($this->nonEmpty($data, 'row_id') ?: uniqid()));
        $orderDate = $this->parseDate($data, 'order_date');
        $shipDate = $this->parseDate($data, 'ship_date');
        $shipMode = $this->nonEmpty($data, 'ship_mode');
        $priority = $this->nonEmpty($data, 'priority');

        $orderId = null;
        if (isset($this->seen['orders'][$orderCode])) {
            $orderId = $this->seen['orders'][$orderCode];
        } else {
            $existing = db()->query("SELECT id, total_amount, total_profit FROM orders WHERE order_code = ? LIMIT 1", [$orderCode])->fetch();
            if ($existing) {
                $orderId = $existing['id'];
            } else {
                db()->query("INSERT INTO orders (order_code, order_date, ship_date, ship_mode, customer_id, region_id, total_amount, total_profit, status, priority) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 'delivered', ?)", [$orderCode, $orderDate, $shipDate, $shipMode, $customerId, $regionId, $priority]);
                $orderId = db()->lastInsertId();
                $this->stats['orders']++;
            }
            $this->seen['orders'][$orderCode] = $orderId;
        }

        $quantity = (isset($data['quantity']) && $data['quantity'] !== '') ? max(0, (int)$data['quantity']) : 1;
        $unitPrice = (float)($data['unit_price'] ?? 0);
        $salesAmount = $this->parseMoney($data, 'sales');
        if (!$unitPrice && $salesAmount > 0 && $quantity > 0) {
            $unitPrice = $salesAmount / $quantity;
        }
        if (!$salesAmount && $unitPrice > 0 && $quantity > 0) {
            $salesAmount = $unitPrice * $quantity;
        }
        $discount = (float)($data['discount'] ?? 0);
        if ($discount > 0 && $discount < 1) $discount = $discount * 100;
        $profit = $this->parseMoney($data, 'profit');
        $costRatio = 0.6;
        if (abs($profit) < 0.01 && $salesAmount > 0) {
            $costAmount = $salesAmount * $costRatio;
            $profit = $salesAmount - $costAmount;
        } elseif ($salesAmount > 0) {
            $costAmount = $salesAmount - $profit;
        } else {
            $costAmount = 0;
        }
        if ($unitPrice == 0 && $quantity > 0) {
            $unitPrice = $salesAmount / $quantity;
        }

        $dupKey = $orderId . '|' . $productId . '|' . $salesAmount . '|' . $quantity;
        $existingSale = db()->query("SELECT id FROM sales WHERE order_id = ? AND product_id = ? AND ROUND(sales_amount,2) = ROUND(?,2) AND quantity = ? LIMIT 1", [$orderId, $productId, $salesAmount, $quantity])->fetch();
        if ($existingSale) {
            return 'duplicate';
        }

        $saleDate = $orderDate ?: date('Y-m-d');
        db()->query("INSERT INTO sales (order_id, product_id, quantity, unit_price, discount, sales_amount, cost_amount, profit, sale_date, region_id, customer_id, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$orderId, $productId, $quantity, $unitPrice, $discount, $salesAmount, $costAmount ?? 0, $profit, $saleDate, $regionId, $customerId, $categoryId]);
        $this->stats['sales']++;

        db()->query("UPDATE orders SET total_amount = total_amount + ?, total_profit = total_profit + ? WHERE id = ?", [$salesAmount, $profit, $orderId]);
        return true;
    }

    private function nonEmpty($arr, $key) {
        if (!isset($arr[$key])) return null;
        $v = trim($arr[$key]);
        if ($v === '' || $v === null || $v === false) return null;
        return $v;
    }

    private function parseMoney($data, $key) {
        if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) return 0;
        $v = trim($data[$key]);
        $v = preg_replace('/[^0-9.\-]/', '', $v);
        return (float)$v;
    }

    private function parseDate($data, $key) {
        if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) return null;
        $v = trim($data[$key]);
        if (is_numeric($v)) {
            $days = (int)$v;
            if ($days > 10000) {
                $ts = ($days - 25569) * 86400;
                return date('Y-m-d', $ts);
            }
        }
        $ts = strtotime($v);
        if ($ts === false) {
            $patterns = ['d/m/Y','m/d/Y','Y-m-d','d-M-Y','M d, Y','M d Y'];
            foreach ($patterns as $p) {
                $dt = DateTime::createFromFormat($p, $v);
                if ($dt) return $dt->format('Y-m-d');
            }
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function truncateData() {
        db()->query("SET FOREIGN_KEY_CHECKS = 0");
        db()->query("TRUNCATE TABLE sales");
        db()->query("TRUNCATE TABLE orders");
        db()->query("TRUNCATE TABLE products");
        db()->query("TRUNCATE TABLE customers");
        db()->query("TRUNCATE TABLE categories");
        db()->query("TRUNCATE TABLE regions");
        db()->query("TRUNCATE TABLE forecasts");
        db()->query("TRUNCATE TABLE forecast_results");
        db()->query("SET FOREIGN_KEY_CHECKS = 1");
        $this->seen = ['orders' => [], 'customers' => [], 'products' => [], 'categories' => [], 'regions' => []];
    }

    private function createImportRecord($filename, $filepath, $type, $size, $userId) {
        db()->query("INSERT INTO dataset_imports (file_name, file_path, file_type, file_size, status, imported_by) VALUES (?, ?, ?, ?, 'processing', ?)", [$filename, $filepath, $type, $size, $userId]);
        return db()->lastInsertId();
    }

    private function updateImportRecord($status, $stats, $errorLog = null) {
        if ($this->importId) {
            db()->query("UPDATE dataset_imports SET status = ?, total_rows = ?, imported_rows = ?, duplicate_rows = ?, error_rows = ?, error_log = ? WHERE id = ?",
                [$status, $stats['total'], $stats['imported'], $stats['duplicates'], $stats['errors'], $errorLog ?: (count($this->errors) ? json_encode(array_slice($this->errors, 0, 100)) : null), $this->importId]);
        }
    }

    public function getStats() { return $this->stats; }
    public function getErrors() { return $this->errors; }
}
