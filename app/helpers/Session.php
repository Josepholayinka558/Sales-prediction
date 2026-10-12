<?php
class Session {
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            ini_set('session.use_only_cookies', 1);
            session_start();
        }
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public static function destroy() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function regenerate($deleteOld = true) {
        return session_regenerate_id($deleteOld);
    }

    public static function flash($key, $value = null) {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return;
        }
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        return null;
    }

    public static function hasFlash($key) {
        return isset($_SESSION['_flash'][$key]);
    }

    public static function isLoggedIn() {
        return self::has('user_id') && self::has('user_role');
    }

    public static function userId() {
        return self::get('user_id');
    }

    public static function userRole() {
        return self::get('user_role');
    }

    public static function checkTimeout() {
        $timeout = self::get('session_timeout', SESSION_TIMEOUT);
        $lastActivity = self::get('last_activity');
        if ($lastActivity && (time() - $lastActivity) > $timeout) {
            return false;
        }
        self::set('last_activity', time());
        return true;
    }
}
Session::init();
