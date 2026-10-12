<?php
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . BASE_URL . $url);
    exit;
}

function back() {
    $url = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/';
    header('Location: ' . $url);
    exit;
}

function old($key, $default = '') {
    return $_SESSION['_old'][$key] ?? $default;
}

function errors($key = null) {
    $errors = $_SESSION['_errors'] ?? [];
    if ($key === null) return $errors;
    return $errors[$key] ?? null;
}

function format_currency($amount, $decimals = 2) {
    $symbol = '$';
    return $symbol . number_format((float)$amount, $decimals, '.', ',');
}

function format_number($number, $decimals = 0) {
    return number_format((float)$number, $decimals, '.', ',');
}

function format_date($date, $format = 'M d, Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

function format_percentage($value, $decimals = 1) {
    return number_format((float)$value, $decimals) . '%';
}

function asset($path) {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function url($path) {
    return BASE_URL . '/' . ltrim($path, '/');
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text);
}

function limit_text($text, $limit = 100) {
    if (strlen($text) <= $limit) return $text;
    return substr($text, 0, $limit) . '...';
}

function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_string($str, $min = 1, $max = 255) {
    $len = strlen(trim($str));
    return $len >= $min && $len <= $max;
}

function validate_number($num, $min = null, $max = null) {
    if (!is_numeric($num)) return false;
    if ($min !== null && $num < $min) return false;
    if ($max !== null && $num > $max) return false;
    return true;
}

function upload_file($file, $dir, $allowedExtensions = ['csv','xlsx','xls']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error.'];
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'File too large.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Invalid file type.'];
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = rtrim($dir, '/') . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'name' => $file['name'],
            'filename' => $filename,
            'path' => $filepath,
            'size' => $file['size'],
            'type' => $ext
        ];
    }
    return ['success' => false, 'error' => 'Failed to save file.'];
}

function paginate($page, $perPage, $total, $url) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages,
        'offset' => $offset,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $totalPages,
        'prevPage' => $page - 1,
        'nextPage' => $page + 1,
        'pages' => range(1, $totalPages)
    ];
}

function create_notification($userId, $type, $title, $message = '', $link = '', $data = []) {
    db()->query(
        "INSERT INTO notifications (user_id, type, title, message, link, data) VALUES (?, ?, ?, ?, ?, ?)",
        [$userId, $type, $title, $message, $link, json_encode($data)]
    );
}

function get_setting($key, $default = null) {
    $row = db()->query("SELECT setting_value, setting_type FROM settings WHERE setting_key = ? LIMIT 1", [$key])->fetch();
    if (!$row) return $default;
    $value = $row['setting_value'];
    switch ($row['setting_type']) {
        case 'integer': return (int)$value;
        case 'float': return (float)$value;
        case 'boolean': return (bool)$value;
        case 'json': return json_decode($value, true);
        default: return $value;
    }
}

function set_setting($key, $value, $type = 'string', $description = '') {
    if ($type === 'json') $value = json_encode($value);
    db()->query("INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), description = VALUES(description)", [$key, (string)$value, $type, $description]);
}
