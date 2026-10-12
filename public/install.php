<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
$pageTitle = 'System Installation';
$step = (int)($_GET['step'] ?? 1);
$errors = [];
$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'test_connection') {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $message = 'Database connection successful!';
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Connection failed: ' . $e->getMessage();
        }
    }
    if ($action === 'install') {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            $sqlFile = dirname(__FILE__) . '/database/sql/schema.sql';
            $sql = file_get_contents($sqlFile);
            $sql = preg_replace('/--.*$/m', '', $sql);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $executed = 0; $failed = [];
            foreach ($statements as $stmt) {
                if (empty($stmt)) continue;
                try { $pdo->exec($stmt); $executed++; }
                catch (Exception $e) { $failed[] = $e->getMessage(); }
            }
            set_setting('db_installed', date('Y-m-d H:i:s'));
            $step = 3;
            $success = true;
            $message = "Installation complete! Executed $executed SQL statements.";
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { font-family:'Inter',sans-serif; }
body { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; background: linear-gradient(135deg,#1e1b4b 0%,#4c1d95 60%,#6f42c1 100%); }
.card { border:none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.35); }
.step-badge { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; font-weight:700; }
.step-active { background:#6f42c1; color:#fff; }
.step-inactive { background:#e2e8f0; color:#64748b; }
.step-line { height:3px; flex:1; border-radius:2px; margin: 0 0.75rem; background:#e2e8f0; align-self:center;}
.step-line.done { background:#6f42c1; }
.btn-primary { background:#6f42c1; border-color:#6f42c1; }
.btn-primary:hover { background:#5a32a3; border-color:#5a32a3; }
.progress { height: 10px; border-radius: 50px;}
code { background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:0.85rem;}
</style>
</head>
<body>
<div class="container" style="max-width: 820px;">
    <div class="card overflow-hidden">
        <div class="p-5 text-white text-center" style="background: linear-gradient(135deg,#1e1b4b,#6f42c1);">
            <h1 class="fw-bold mb-1"><i class="fas fa-chart-line me-2" style="color:#d4af37;"></i><?= APP_NAME ?></h1>
            <p class="mb-0 opacity-75">Sales Forecasting & Analytics Dashboard</p>
        </div>
        <div class="card-body p-5">
            <div class="d-flex mb-5">
                <div class="step-badge <?= $step>=1?'step-active':'step-inactive' ?>">1</div>
                <div class="step-line <?= $step>=2?'done':'' ?>"></div>
                <div class="step-badge <?= $step>=2?'step-active':'step-inactive' ?>">2</div>
                <div class="step-line <?= $step>=3?'done':'' ?>"></div>
                <div class="step-badge <?= $step>=3?'step-active':'step-inactive' ?>">3</div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger rounded-3 border-0 mb-4">
                    <h6 class="fw-bold"><i class="fas fa-triangle-exclamation me-2"></i>Errors occurred:</h6>
                    <ul class="mb-0 ps-3 small"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <?php if ($success && $message): ?>
                <div class="alert alert-success rounded-3 border-0 mb-4"><i class="fas fa-circle-check me-2"></i><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <h3 class="fw-bold mb-2"><i class="fas fa-server text-primary me-2"></i>Step 1: Verify Requirements</h3>
                <p class="text-muted mb-4">Please verify the following requirements before proceeding with installation.</p>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered rounded-3 overflow-hidden">
                        <thead class="table-light"><tr><th>Requirement</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr><td><code>PHP 8.0+</code></td><td><?php $ok = version_compare(PHP_VERSION, '8.0.0', '>='); ?><span class="badge bg-<?= $ok?'success':'danger' ?>"><?= $ok?'OK: '.PHP_VERSION:'FAIL: '.PHP_VERSION ?></span></td></tr>
                            <tr><td><code>PDO MySQL Extension</code></td><td><?php $ok = extension_loaded('pdo_mysql'); ?><span class="badge bg-<?= $ok?'success':'danger' ?>"><?= $ok?'INSTALLED':'MISSING' ?></span></td></tr>
                            <tr><td><code>JSON Extension</code></td><td><?php $ok = extension_loaded('json'); ?><span class="badge bg-<?= $ok?'success':'danger' ?>"><?= $ok?'INSTALLED':'MISSING' ?></span></td></tr>
                            <tr><td><code>FileInfo Extension</code></td><td><?php $ok = extension_loaded('fileinfo'); ?><span class="badge bg-<?= $ok?'success':'danger' ?>"><?= $ok?'INSTALLED':'MISSING' ?></span></td></tr>
                            <tr><td>MySQL Server (host=<?= DB_HOST ?>)</td><td><span class="badge bg-info">Will be tested next</span></td></tr>
                            <tr><td>Database Name: <code><?= DB_NAME ?></code></td><td><span class="badge bg-info">Configured</span></td></tr>
                            <tr><td>storage/ directory writable</td><td><?php $ok = is_writable(STORAGE_PATH); ?><span class="badge bg-<?= $ok?'success':'danger' ?>"><?= $ok?'WRITABLE':'NOT WRITABLE' ?></span></td></tr>
                            <tr><td>public/uploads/ directory writable</td><td><?php $ok = is_writable(UPLOAD_PATH); ?><span class="badge bg-<?= $ok?'success':'danger' ?>"><?= $ok?'WRITABLE':'NOT WRITABLE' ?></span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 p-4 mb-4">
                    <h6 class="fw-bold text-warning"><i class="fas fa-circle-info me-2"></i>Configuration Note</h6>
                    <p class="small mb-0">To change database credentials, edit <code>app/config/config.php</code>. Default XAMPP values: host=localhost, user=root, password="", db=sales_forecast.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="test_connection">
                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-plug me-2"></i>Test DB Connection</button>
                        <a href="?step=2" class="btn btn-primary"><i class="fas fa-arrow-right me-2"></i>Proceed to Install</a>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($step === 2): ?>
                <h3 class="fw-bold mb-2"><i class="fas fa-database text-primary me-2"></i>Step 2: Database Installation</h3>
                <p class="text-muted mb-4">This will create all required tables, roles, and a default admin user.</p>
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-list-check me-2 text-primary"></i>What will be created:</h6>
                        <ul class="small mb-0">
                            <li><strong>18 Database Tables:</strong> users, roles, sales, orders, products, categories, customers, regions, forecasts, forecast_results, reports, notifications, chat_messages, activity_logs, settings, dataset_imports</li>
                            <li><strong>3 Default Roles:</strong> Administrator, Analyst, Manager</li>
                            <li><strong>3 Demo Users:</strong> admin / analyst / manager (all with password: <code>password</code>)</li>
                            <li><strong>Default System Settings:</strong> currencies, timezones, forecast parameters</li>
                        </ul>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="install">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="?step=1" class="btn btn-light"><i class="fas fa-arrow-left me-2"></i>Back</a>
                        <button class="btn btn-primary" type="submit" onclick="return confirm('This will create/modify tables in the \'<?= DB_NAME ?>\' database. Continue?')"><i class="fas fa-play me-2"></i>Run Installation</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($step === 3): ?>
                <div class="text-center py-4">
                    <div class="mx-auto mb-4 rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center text-success" style="width:100px;height:100px;"><i class="fas fa-check fa-3x"></i></div>
                    <h2 class="fw-bold mb-2">Installation Complete! 🎉</h2>
                    <p class="text-muted mb-4">Your Sales Forecasting Dashboard is ready to use.</p>
                    <div class="card bg-light border-0 text-start mb-4 mx-auto" style="max-width:520px;">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3"><i class="fas fa-info-circle text-primary me-2"></i>Next Steps:</h6>
                            <ol class="mb-0 small">
                                <li class="mb-2">Go to <a href="login.php"><strong>Login page</strong></a> and sign in with demo credentials:</li>
                            </ol>
                            <div class="mt-2 mb-3 p-3 bg-white rounded-3">
                                <div class="mb-2"><i class="fas fa-crown text-accent me-2"></i><strong>Administrator:</strong> <code>admin@salesforecast.local</code> / <code>password</code></div>
                                <div class="mb-2"><i class="fas fa-chart-bar text-info me-2"></i><strong>Analyst:</strong> <code>analyst@salesforecast.local</code> / <code>password</code></div>
                                <div><i class="fas fa-user-tie text-warning me-2"></i><strong>Manager:</strong> <code>manager@salesforecast.local</code> / <code>password</code></div>
                            </div>
                            <ol start="2" class="mb-0 small"><li>Navigate to <a href="dataset.php"><strong>Dataset Manager</strong></a> and import the <code>dataset.xlsx</code> from the <code>/dataset</code> folder.</li></ol>
                        </div>
                    </div>
                    <a href="login.php" class="btn btn-primary btn-lg px-5"><i class="fas fa-arrow-right-to-bracket me-2"></i>Go to Login</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-light border-top-0 text-center small text-muted py-3">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> v<?= APP_VERSION ?> - Built with Pure Vanilla PHP
        </div>
    </div>
</div>
</body>
</html>
