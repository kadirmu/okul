<?php
session_start();

// Temel ayarlar
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'http://test.mutecno.com');

// Veritabanı bağlantısı
require_once BASE_PATH . '/config/database.php';
$database = new Database();
$db = $database->connect();

// Helper fonksiyonları
require_once BASE_PATH . '/app/core/Helper.php';

// CSRF Token oluştur
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Timezone
date_default_timezone_set('Europe/Istanbul');