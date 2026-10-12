<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sales_forecast');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

header('Content-Type: text/plain; charset=utf-8');
echo "=== SalesForecast BI Database Installer ===\n\n";
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    echo "[OK] Connected to MySQL server at ".DB_HOST."\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] Database `".DB_NAME."` ensured\n";
    $pdo->exec("USE `".DB_NAME."`");
    $schemaFile = dirname(__FILE__) . '/database/sql/schema.sql';
    if (!file_exists($schemaFile)) { echo "[FAIL] schema.sql not found at $schemaFile\n"; exit(1); }
    $sql = file_get_contents($schemaFile);
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/^CREATE DATABASE .+?;$/m', '', $sql);
    $sql = preg_replace('/^USE .+?;$/m', '', $sql);
    $chunks = array_filter(array_map('trim', explode(";\n", $sql)));
    $stmts = [];
    foreach ($chunks as $c) {
        $c = trim($c);
        if ($c === '') continue;
        $stmts[] = $c;
    }
    $executed = 0; $warnings = 0;
    foreach ($stmts as $idx => $s) {
        try {
            $pdo->exec($s);
            $executed++;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'already exists') === false && strpos($msg, 'Duplicate entry') === false) {
                echo "[WARN] #$idx: " . substr($msg, 0, 160) . "\n";
                $warnings++;
            } else {
                $executed++;
            }
        }
    }
    echo "[OK] Executed $executed SQL statements ($warnings non-fatal warnings)\n\n";
    echo "=== Verifying Installation ===\n";
    $tables = ['roles','users','categories','regions','customers','products','orders','sales','dataset_imports','forecast_results','forecasts','reports','notifications','chat_messages','activity_logs','settings'];
    $allTablesOk = true;
    foreach ($tables as $t) {
        $c = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='".DB_NAME."' AND table_name='$t'")->fetchColumn();
        $ok = $c > 0;
        if (!$ok) $allTablesOk = false;
        echo ($ok ? '[OK] ' : '[MISSING] ') . "Table `$t`\n";
    }
    echo "\n=== Default Users (password is: password) ===\n";
    $res = $pdo->query("SELECT u.username, u.email, r.name as role, u.status FROM users u LEFT JOIN roles r ON u.role_id=r.id");
    $found = 0;
    foreach ($res as $r) {
        echo "  - " . $r['username'] . " / " . $r['email'] . " | " . $r['role'] . " | " . $r['status'] . "\n";
        $found++;
    }
    if ($found === 0) echo "  (no users found - insert failed or table empty)\n";
    echo "\n=== Default Settings Sample ===\n";
    $sets = $pdo->query("SELECT setting_key, setting_value FROM settings ORDER BY id LIMIT 10");
    foreach ($sets as $s) echo "  - " . $s['setting_key'] . " = " . $s['setting_value'] . "\n";
    try {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES ('db_installed', NOW(), 'string', 'DB install timestamp') ON DUPLICATE KEY UPDATE setting_value = NOW()");
    } catch(Exception $e) {}
    echo "\n🎉 DATABASE INSTALLATION COMPLETE! 🎉\n\n";
    echo "QUICK START:\n";
    echo "  1. Visit: http://localhost/Sales-prediction/public/dataset.php\n";
    echo "  2. Login: admin@salesforecast.local / password\n";
    echo "  3. Click [Auto-Import dataset.xlsx from /dataset folder]\n";
    exit(0);
} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
