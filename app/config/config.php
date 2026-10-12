<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sales_forecast');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_ROOT', dirname(__FILE__, 3));
define('PUBLIC_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'public');
define('APP_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'app');
define('STORAGE_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'storage');
define('ASSET_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets');
define('DATASET_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'dataset');
define('REPORT_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'reports');
define('UPLOAD_PATH', ASSET_PATH . DIRECTORY_SEPARATOR . 'uploads');

define('BASE_URL', 'http://localhost/Sales-prediction/public');
define('APP_NAME', 'SalesForecast BI');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development');

define('SESSION_TIMEOUT', 3600);
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_NAME', 'csrf_token');

define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024);
define('ALLOWED_UPLOAD_TYPES', ['csv','xlsx','xls','pdf','jpg','jpeg','png','gif']);

date_default_timezone_set('UTC');
error_reporting(APP_ENV === 'development' ? E_ALL : 0);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
