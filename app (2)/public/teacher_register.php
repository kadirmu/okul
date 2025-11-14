<?php
require_once '../config/config.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helper::validateCSRF($_POST['csrf_token'])) {
        $error = 'Geçersiz istek';
    } else {
        $email = Helper::sanitize($_POST['email']);
        $password = $_POST['password'];
        $firstName = Helper::sanitize($_POST['first_name']);
        $lastName = Helper::sanitize($_POST['last_name']);
        $phone = Helper::sanitize($_POST['phone']);

        // Email kontrolü
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Bu e-posta adresi zaten kayıtlı';
        } else {
            try {
                $db->beginTransaction();

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (email, password, role, status) VALUES (?, ?, 'teacher', 'pending')");
                $stmt->execute([$email, $hashedPassword]);
                $userId = $db->lastInsertId();

                $stmt = $db->prepare("INSERT INTO teachers_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $firstName, $lastName, $phone]);

                Helper::createLog($db, $userId, 'teacher_register', 'users', $userId);

                $db->commit();

                $success = 'Kaydınız başarıyla oluşturuldu. Onay bekliyor.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğretmen Kaydı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4" style="color: #0a3255;">Öğretmen Kaydı</h3>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary">Giriş Yap</a>
                            </div>
                        <?php else: ?>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <div class="mb-3">
                                <label class="form-label">Ad</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Soyad</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">E-posta</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Telefon</label>
                                <input type="text" name="phone" class="form-control">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Şifre</label>
                                <input type="password" name="password" class="form-control" minlength="6" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Kayıt Ol</button>
                        </form>

                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <a href="../index.php" class="text-muted">Ana Sayfaya Dön</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>