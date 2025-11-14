<?php
require_once '../config/config.php';

if (Helper::isLoggedIn()) {
    if (Helper::isAdmin()) {
        Helper::redirect('/public/admin/dashboard.php');
    } else {
        Helper::redirect('/public/teacher/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helper::validateCSRF($_POST['csrf_token'])) {
        $error = 'Geçersiz istek';
    } else {
        $email = Helper::sanitize($_POST['email']);
        $password = $_POST['password'];

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending') {
                $error = 'Hesabınız henüz onaylanmamış';
            } elseif ($user['status'] === 'suspended') {
                $error = 'Hesabınız askıya alınmış';
            } elseif ($user['status'] === 'rejected') {
                $error = 'Hesabınız reddedilmiş';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                // Session log
                $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $user['id'],
                    session_id(),
                    Helper::getClientIP(),
                    Helper::getUserAgent()
                ]);

                Helper::createLog($db, $user['id'], 'login', 'users', $user['id']);

                if ($user['role'] === 'admin') {
                    Helper::redirect('/public/admin/dashboard.php');
                } else {
                    Helper::redirect('/public/teacher/dashboard.php');
                }
            }
        } else {
            $error = 'E-posta veya şifre hatalı';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container vh-100 d-flex align-items-center justify-content-center">
        <div class="card shadow" style="max-width: 400px; width: 100%;">
            <div class="card-body p-5">
                <h3 class="text-center mb-4" style="color: #0a3255;">Giriş Yap</h3>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Şifre</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                </form>

                <div class="text-center mt-3">
                    <a href="../index.php" class="text-muted">Ana Sayfaya Dön</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>