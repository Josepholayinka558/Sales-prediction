<?php
require_once dirname(__FILE__) . '/Database.php';
require_once dirname(__FILE__) . '/Session.php';

class Auth {
    private $user = null;

    public function attempt($email, $password, $remember = false) {
        $db = db();
        $user = $db->query(
            "SELECT u.*, r.name as role_name, r.permissions 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE (u.email = ? OR u.username = ?) AND u.status = 'active' 
             LIMIT 1",
            [$email, $email]
        )->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        Session::regenerate(true);
        Session::set('user_id', $user['id']);
        Session::set('user_role', $user['role_name']);
        Session::set('user_role_id', $user['role_id']);
        Session::set('permissions', json_decode($user['permissions'] ?? '[]', true));
        Session::set('user_name', $user['first_name'] . ' ' . $user['last_name']);
        Session::set('user_email', $user['email']);
        Session::set('last_activity', time());

        $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        $this->logActivity('login', 'User logged in', $user['id']);

        if ($remember) {
            $this->setRememberCookie($user['id']);
        }

        return true;
    }

    private function setRememberCookie($userId) {
        $token = bin2hex(random_bytes(32));
        $expire = time() + (86400 * 30);
        db()->query("UPDATE users SET remember_token = ? WHERE id = ?", [password_hash($token, PASSWORD_DEFAULT), $userId]);
        setcookie('remember', $userId . ':' . $token, $expire, '/', '', isset($_SERVER['HTTPS']), true);
    }

    public function check() {
        if (Session::isLoggedIn()) {
            return Session::checkTimeout();
        }
        if (isset($_COOKIE['remember']) && strpos($_COOKIE['remember'], ':') !== false) {
            return $this->loginFromCookie();
        }
        return false;
    }

    private function loginFromCookie() {
        [$userId, $token] = explode(':', $_COOKIE['remember'], 2);
        $user = db()->query("SELECT u.*, r.name as role_name, r.permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.status = 'active' LIMIT 1", [$userId])->fetch();
        if ($user && password_verify($token, $user['remember_token'] ?? '')) {
            Session::regenerate(true);
            Session::set('user_id', $user['id']);
            Session::set('user_role', $user['role_name']);
            Session::set('user_role_id', $user['role_id']);
            Session::set('permissions', json_decode($user['permissions'] ?? '[]', true));
            Session::set('user_name', $user['first_name'] . ' ' . $user['last_name']);
            Session::set('user_email', $user['email']);
            Session::set('last_activity', time());
            db()->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            return true;
        }
        setcookie('remember', '', time() - 3600, '/');
        return false;
    }

    public function user() {
        if ($this->user === null && Session::userId()) {
            $this->user = db()->query("SELECT u.*, r.name as role_name, r.permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1", [Session::userId()])->fetch();
        }
        return $this->user;
    }

    public function hasRole($role) {
        if (is_array($role)) {
            return in_array(Session::userRole(), $role);
        }
        return Session::userRole() === $role;
    }

    public function hasPermission($permission) {
        $permissions = Session::get('permissions', []);
        if (in_array('all', $permissions, true)) {
            return true;
        }
        if (\Session::userRole() === 'Administrator') {
            return true;
        }
        return in_array($permission, $permissions, true);
    }

    public function authorize($permission) {
        if (!$this->check()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        if (\Session::userRole() === 'Administrator') {
            return true;
        }
        if (!$this->hasPermission($permission)) {
            http_response_code(403);
            Session::flash('error', 'Access denied. Missing permission: ' . htmlspecialchars($permission));
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
        return true;
    }

    public function logout() {
        $this->logActivity('logout', 'User logged out');
        db()->query("UPDATE users SET remember_token = NULL WHERE id = ?", [Session::userId()]);
        setcookie('remember', '', time() - 3600, '/');
        Session::destroy();
        $this->user = null;
    }

    public function sendResetLink($email) {
        $user = db()->query("SELECT * FROM users WHERE email = ? LIMIT 1", [$email])->fetch();
        if (!$user) return true;
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        db()->query("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?", [password_hash($token, PASSWORD_DEFAULT), $expires, $user['id']]);
        Session::flash('reset_token', $token);
        Session::flash('reset_email', $email);
        return true;
    }

    public function resetPassword($email, $token, $password) {
        $user = db()->query("SELECT * FROM users WHERE email = ? LIMIT 1", [$email])->fetch();
        if (!$user || !$user['reset_token'] || !$user['reset_expires']) return false;
        if (strtotime($user['reset_expires']) < time()) return false;
        if (!password_verify($token, $user['reset_token'])) return false;
        db()->query("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        return true;
    }

    public function logActivity($action, $description = '', $userId = null) {
        $userId = $userId ?? Session::userId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        db()->query("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)", [$userId, $action, $description, $ip, $ua]);
    }
}

function auth() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Auth();
    }
    return $instance;
}
