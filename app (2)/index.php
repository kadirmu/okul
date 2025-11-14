<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::getSetting($db, 'app_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container vh-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <h1 class="display-4 mb-4" style="color: #0a3255;"><?php echo Helper::getSetting($db, 'app_name'); ?></h1>
            <p class="lead mb-4">Hoş Geldiniz</p>
            <div class="d-grid gap-3 col-md-6 mx-auto">
                <a href="public/login.php" class="btn btn-primary btn-lg">Giriş Yap</a>
                <a href="public/teacher_register.php" class="btn btn-outline-primary btn-lg">Öğretmen Kaydı</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>