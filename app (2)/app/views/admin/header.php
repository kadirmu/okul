<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- $db değişkeni dashboard.php'den geliyor -->
    <title><?php echo Helper::getSetting($db, 'app_name'); ?> - Admin</title>
    
    <!-- CSS Dosyaları -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- İKONLAR İÇİN GEREKLİ CDN LİNKİ -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    
    <!-- Modern dashboard kartları için gerekli stiller -->
    <style>
        .border-start-primary { border-left: 4px solid var(--bs-primary) !important; }
        .border-start-success { border-left: 4px solid var(--bs-success) !important; }
        .border-start-info { border-left: 4px solid var(--bs-info) !important; }
        .border-start-warning { border-left: 4px solid var(--bs-warning) !important; }
        .border-start-danger { border-left: 4px solid var(--bs-danger) !important; }

        .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: #0a3255;">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/public/admin/dashboard.php">
                <i class="fa-solid fa-shield-halved me-2"></i>
                <?php echo Helper::getSetting($db, 'app_name'); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Ana Menü Linkleri -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/public/admin/dashboard.php">
                            <i class="fa-solid fa-gauge-high fa-fw me-1"></i>Ana Sayfa
                        </a>
                    </li>
                    
                    <!-- Yönetim Menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownYonetim" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-list-check fa-fw me-1"></i>Yönetim
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownYonetim">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/admin/teachers.php"><i class="fa-solid fa-chalkboard-user fa-fw me-2"></i>Öğretmenler</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/admin/students.php"><i class="fa-solid fa-user-graduate fa-fw me-2"></i>Öğrenciler</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/admin/classes.php"><i class="fa-solid fa-school fa-fw me-2"></i>Sınıflar</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/admin/subjects.php"><i class="fa-solid fa-book fa-fw me-2"></i>Dersler</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/admin/periods.php"><i class="fa-solid fa-calendar-days fa-fw me-2"></i>Dönemler</a></li>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/public/admin/grades.php">
                            <i class="fa-solid fa-marker fa-fw me-1"></i>Not Girişi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/public/admin/reports.php">
                            <i class="fa-solid fa-file-pdf fa-fw me-1"></i>Karneler
                        </a>
                    </li>

                    <!-- Sistem Menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownSistem" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-cogs fa-fw me-1"></i>Sistem
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownSistem">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/admin/logs.php"><i class="fa-solid fa-clipboard-list fa-fw me-2"></i>Loglar</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/admin/settings.php"><i class="fa-solid fa-sliders fa-fw me-2"></i>Ayarlar</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Kullanıcı Menüsü -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user-shield fa-fw me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUser">
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/admin/logout.php">
                                    <i class="fa-solid fa-right-from-bracket fa-fw me-2"></i>Çıkış Yap
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>

            </div>
        </div>
    </nav>

    <main class="py-4">