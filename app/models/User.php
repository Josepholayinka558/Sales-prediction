<?php
require_once dirname(__FILE__) . '/Model.php';

class User extends Model {
    protected $table = 'users';
    protected $fillable = ['username', 'email', 'password', 'first_name', 'last_name', 'phone', 'avatar', 'role_id', 'status', 'remember_token', 'reset_token', 'reset_expires', 'last_login'];

    public function role() {
        if (!isset($this->id)) return null;
        return db()->query("SELECT r.* FROM roles r INNER JOIN users u ON u.role_id = r.id WHERE u.id = ? LIMIT 1", [$this->id])->fetch();
    }

    public function withRole($id) {
        return db()->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1", [$id])->fetch();
    }

    public function allWithRoles() {
        return db()->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC")->fetchAll();
    }

    public function activities($userId, $limit = 20) {
        return db()->query("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?", [$userId, $limit])->fetchAll();
    }

    public function notifications($userId, $limit = 20) {
        return db()->query("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?", [$userId, $limit])->fetchAll();
    }

    public function unreadNotificationCount($userId) {
        return db()->query("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$userId])->fetchColumn();
    }

    public function markNotificationsRead($userId) {
        return db()->query("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$userId]);
    }
}
