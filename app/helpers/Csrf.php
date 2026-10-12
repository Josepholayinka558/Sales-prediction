<?php
require_once dirname(__FILE__) . '/Session.php';

class Csrf {
    public static function generate() {
        if (!Session::has(CSRF_TOKEN_NAME)) {
            $token = bin2hex(random_bytes(32));
            Session::set(CSRF_TOKEN_NAME, $token);
        }
        return Session::get(CSRF_TOKEN_NAME);
    }

    public static function token() {
        return self::generate();
    }

    public static function field() {
        $token = self::generate();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }

    public static function verify($token, $regenerate = false) {
        $sessionToken = Session::get(CSRF_TOKEN_NAME);
        if (!$token || !$sessionToken) {
            return false;
        }
        $valid = hash_equals($sessionToken, $token);
        if ($valid && $regenerate) {
            $newToken = bin2hex(random_bytes(32));
            Session::set(CSRF_TOKEN_NAME, $newToken);
        }
        return $valid;
    }

    public static function verifyRequest() {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return self::verify($token);
    }
}
