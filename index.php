<?php
$publicPath = __DIR__ . '/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$uri = preg_replace('#^/Sales-prediction#', '', $uri);
if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false;
}
$requested = $publicPath . rtrim($uri, '/');
if (is_dir($requested) && file_exists($requested . '/index.php')) {
    require_once $requested . '/index.php';
    exit;
}
if (file_exists($requested . '.php') && !is_dir($requested)) {
    require_once $requested . '.php';
    exit;
}
if (file_exists($publicPath . '/index.php')) {
    require_once $publicPath . '/index.php';
    exit;
}
http_response_code(404);
echo '404 Not Found';
