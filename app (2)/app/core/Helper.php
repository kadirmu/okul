<?php

class Helper {

    public static function redirect($url) {
        header("Location: " . BASE_URL . $url);
        exit();
    }

    public static function sanitize($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitize($value);
            }
            return $data;
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    public static function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }

    public static function isAdmin() {
        return self::isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }

    public static function isTeacher() {
        return self::isLoggedIn() && $_SESSION['user_role'] === 'teacher';
    }

    public static function checkAuth() {
        if (!self::isLoggedIn()) {
            self::redirect('/public/login.php');
        }
    }

    public static function checkAdmin() {
        self::checkAuth();
        if (!self::isAdmin()) {
            self::redirect('/public/teacher/dashboard.php');
        }
    }

    public static function checkTeacher() {
        self::checkAuth();
        if (!self::isTeacher()) {
            self::redirect('/public/admin/dashboard.php');
        }
    }

    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public static function createLog($db, $userId, $action, $targetTable = null, $targetId = null, $oldValue = null, $newValue = null) {
        try {
            $stmt = $db->prepare("
                INSERT INTO logs (user_id, action, target_table, target_id, old_value, new_value, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $oldValueJson = $oldValue ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
            $newValueJson = $newValue ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

            $stmt->execute([
                $userId,
                $action,
                $targetTable,
                $targetId,
                $oldValueJson,
                $newValueJson,
                self::getClientIP(),
                self::getUserAgent()
            ]);
        } catch (Exception $e) {
            error_log("Log oluşturma hatası: " . $e->getMessage());
        }
    }

    public static function sendNotification($db, $message, $type = 'info') {
        // Email bildirimi
        $emailEnabled = self::getSetting($db, 'notification_email');
        if ($emailEnabled == '1') {
            self::sendEmail($db, $message);
        }

        // Telegram bildirimi
        $telegramEnabled = self::getSetting($db, 'notification_telegram');
        if ($telegramEnabled == '1') {
            self::sendTelegram($db, $message);
        }
    }

    private static function sendEmail($db, $message) {
        // PHPMailer implementasyonu buraya eklenecek
        // Basit tutuluyor
    }

    private static function sendTelegram($db, $message) {
        $botToken = self::getSetting($db, 'telegram_bot_token');
        $chatIds = json_decode(self::getSetting($db, 'telegram_chat_ids'), true);

        if (empty($botToken) || empty($chatIds)) {
            return;
        }

        foreach ($chatIds as $chatId) {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    public static function getSetting($db, $key) {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    }

    public static function updateSetting($db, $key, $value) {
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        return $stmt->execute([$value, $key]);
    }

    public static function formatDate($date) {
        return date('d.m.Y H:i', strtotime($date));
    }

    public static function showAlert($message, $type = 'success') {
        $_SESSION['alert'] = [
            'message' => $message,
            'type' => $type
        ];
    }

    public static function displayAlert() {
        if (isset($_SESSION['alert'])) {
            $alert = $_SESSION['alert'];
            echo '<div class="alert alert-' . $alert['type'] . ' alert-dismissible fade show" role="alert">
                    ' . htmlspecialchars($alert['message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
            unset($_SESSION['alert']);
        }
    }
}