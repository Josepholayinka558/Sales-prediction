<?php
ob_start();
require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/helpers/Database.php';
require_once dirname(__FILE__) . '/helpers/Session.php';
require_once dirname(__FILE__) . '/helpers/Csrf.php';
require_once dirname(__FILE__) . '/helpers/Auth.php';
require_once dirname(__FILE__) . '/helpers/Functions.php';
require_once dirname(__FILE__) . '/helpers/ForecastEngine.php';
require_once dirname(__FILE__) . '/helpers/DatasetImporter.php';
require_once dirname(__FILE__) . '/models/Model.php';
require_once dirname(__FILE__) . '/models/CoreModels.php';
require_once dirname(__FILE__) . '/models/Sale.php';
require_once dirname(__FILE__) . '/models/User.php';

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    if (APP_ENV === 'development') {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});
set_exception_handler(function ($e) {
    $logDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    file_put_contents($logDir . DIRECTORY_SEPARATOR . 'error-' . date('Y-m-d') . '.log', date('Y-m-d H:i:s') . ' | ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL, FILE_APPEND);
    if (APP_ENV === 'development') {
        echo '<h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p>' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
    } else {
        echo 'An error occurred. Please try again later.';
    }
    exit;
});

register_shutdown_function(function () {
    if (ob_get_level()) ob_end_flush();
});
