<?php
require_once '../../config/config.php';
Helper::checkTeacher();

// Profil güncelleme
if (isset($_POST['update_profile'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $firstName = Helper::sanitize($_POST['first_name']);
        $lastName = Helper::sanitize($_POST['last_name']);
        $phone = Helper::sanitize($_POST['phone']);

        $stmt = $db->prepare("UPDATE teachers_profiles SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?");
        if ($stmt->execute([$firstName, $lastName, $phone, $_SESSION['user_id']])) {
            Helper::createLog($db, $_SESSION['user_id'], 'profile_updated', 'teachers_profiles', $_SESSION['user_id']);
            Helper::showAlert('Profil güncellendi', 'success');
        }
    }
    Helper::redirect('/public/teacher/profile.php');
}

// Şifre değiştirme
if (isset($_POST['change_password'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Mevcut şifreyi kontrol et
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!password_verify($currentPassword, $user['password'])) {
            Helper::showAlert('Mevcut şifre hatalı', 'danger');
        } elseif ($newPassword !== $confirmPassword) {
            Helper::showAlert('Yeni şifreler eşleşmiyor', 'danger');
        } elseif (strlen($newPassword) < 6) {
            Helper::showAlert('Yeni şifre en az 6 karakter olmalıdır', 'danger');
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                Helper::createLog($db, $_SESSION['user_id'], 'password_changed', 'users', $_SESSION['user_id']);
                Helper::showAlert('Şifre değiştirildi', 'success');
            }
        }
    }
    Helper::redirect('/public/teacher/profile.php');
}

// Profil bilgilerini getir
$stmt = $db->prepare("
    SELECT u.email, tp.first_name, tp.last_name, tp.phone
    FROM users u
    JOIN teachers_profiles tp ON u.id = tp.user_id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

// İşlem logları
$stmt = $db->prepare("
    SELECT * FROM logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id']]);
$logs = $stmt->fetchAll();

include '../../app/views/teacher/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">Profilim</h2>

    <?php Helper::displayAlert(); ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Profil Bilgileri</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['email']); ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ad</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($profile['first_name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Soyad</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($profile['last_name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone']); ?>">
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">Güncelle</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Şifre Değiştir</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="mb-3">
                            <label class="form-label">Mevcut Şifre</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Yeni Şifre (Tekrar)</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-warning">Şifre Değiştir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Son İşlemlerim</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>İşlem</th>
                            <th>Tablo</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo Helper::formatDate($log['created_at']); ?></td>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['target_table'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../app/views/teacher/footer.php'; ?>