<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// Öğretmen onaylama
if (isset($_POST['approve_teacher'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $teacherId = (int)$_POST['teacher_id'];
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'teacher'");
        if ($stmt->execute([$teacherId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'teacher_approved', 'users', $teacherId);
            Helper::showAlert('Öğretmen onaylandı', 'success');
        }
    }
    Helper::redirect('/public/admin/teachers.php');
}

// Öğretmen reddetme
if (isset($_POST['reject_teacher'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $teacherId = (int)$_POST['teacher_id'];
        $stmt = $db->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'teacher'");
        if ($stmt->execute([$teacherId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'teacher_rejected', 'users', $teacherId);
            Helper::showAlert('Öğretmen reddedildi', 'warning');
        }
    }
    Helper::redirect('/public/admin/teachers.php');
}

// Öğretmen askıya alma
if (isset($_POST['suspend_teacher'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $teacherId = (int)$_POST['teacher_id'];
        $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'teacher'");
        if ($stmt->execute([$teacherId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'teacher_suspended', 'users', $teacherId);
            Helper::showAlert('Öğretmen askıya alındı', 'warning');
        }
    }
    Helper::redirect('/public/admin/teachers.php');
}

// Öğretmen aktifleştirme
if (isset($_POST['activate_teacher'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $teacherId = (int)$_POST['teacher_id'];
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'teacher'");
        if ($stmt->execute([$teacherId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'teacher_activated', 'users', $teacherId);
            Helper::showAlert('Öğretmen aktifleştirildi', 'success');
        }
    }
    Helper::redirect('/public/admin/teachers.php');
}

// Öğretmen ders atama
if (isset($_POST['assign_subject'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $teacherId = (int)$_POST['teacher_id'];
        $subjectId = (int)$_POST['subject_id'];
        $classId = (int)$_POST['class_id'];

        try {
            $stmt = $db->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) VALUES (?, ?, ?)");
            $stmt->execute([$teacherId, $subjectId, $classId]);
            Helper::createLog($db, $_SESSION['user_id'], 'teacher_subject_assigned', 'teacher_subjects', $db->lastInsertId());
            Helper::showAlert('Ders atandı', 'success');
        } catch (Exception $e) {
            Helper::showAlert('Bu atama zaten mevcut', 'danger');
        }
    }
    Helper::redirect('/public/admin/teachers.php');
}

// Öğretmenleri getir
$stmt = $db->query("
    SELECT u.*, tp.first_name, tp.last_name, tp.phone
    FROM users u
    LEFT JOIN teachers_profiles tp ON u.id = tp.user_id
    WHERE u.role = 'teacher'
    ORDER BY u.created_at DESC
");
$teachers = $stmt->fetchAll();

// Dersler
$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

// Sınıflar
$classes = $db->query("SELECT * FROM classes ORDER BY name")->fetchAll();

include '../../app/views/admin/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">Öğretmen Yönetimi</h2>

    <?php Helper::displayAlert(); ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Ad Soyad</th>
                            <th>E-posta</th>
                            <th>Telefon</th>
                            <th>Durum</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['phone'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $statusClass = [
                                    'active' => 'success',
                                    'pending' => 'warning',
                                    'suspended' => 'danger',
                                    'rejected' => 'secondary'
                                ];
                                $statusText = [
                                    'active' => 'Aktif',
                                    'pending' => 'Bekliyor',
                                    'suspended' => 'Askıda',
                                    'rejected' => 'Reddedildi'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $statusClass[$teacher['status']]; ?>">
                                    <?php echo $statusText[$teacher['status']]; ?>
                                </span>
                            </td>
                            <td><?php echo Helper::formatDate($teacher['created_at']); ?></td>
                            <td>
                                <?php if ($teacher['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <button type="submit" name="approve_teacher" class="btn btn-sm btn-success">Onayla</button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <button type="submit" name="reject_teacher" class="btn btn-sm btn-danger">Reddet</button>
                                    </form>
                                <?php elseif ($teacher['status'] === 'active'): ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal<?php echo $teacher['id']; ?>">
                                        Ders Ata
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <button type="submit" name="suspend_teacher" class="btn btn-sm btn-warning">Askıya Al</button>
                                    </form>
                                <?php elseif ($teacher['status'] === 'suspended'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <button type="submit" name="activate_teacher" class="btn btn-sm btn-success">Aktifleştir</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Ders Atama Modal -->
                        <div class="modal fade" id="assignModal<?php echo $teacher['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Ders Ata</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">

                                            <div class="mb-3">
                                                <label class="form-label">Ders</label>
                                                <select name="subject_id" class="form-select" required>
                                                    <option value="">Seçiniz</option>
                                                    <?php foreach ($subjects as $subject): ?>
                                                        <option value="<?php echo $subject['id']; ?>">
                                                            <?php echo htmlspecialchars($subject['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Sınıf</label>
                                                <select name="class_id" class="form-select" required>
                                                    <option value="">Seçiniz</option>
                                                    <?php foreach ($classes as $class): ?>
                                                        <option value="<?php echo $class['id']; ?>">
                                                            <?php echo htmlspecialchars($class['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                            <button type="submit" name="assign_subject" class="btn btn-primary">Ata</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../app/views/admin/footer.php'; ?>