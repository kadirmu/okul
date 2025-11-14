<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// Sınıf ekleme
if (isset($_POST['add_class'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $name = Helper::sanitize($_POST['name']);
        $description = Helper::sanitize($_POST['description']);

        try {
            $stmt = $db->prepare("INSERT INTO classes (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $classId = $db->lastInsertId();

            Helper::createLog($db, $_SESSION['user_id'], 'class_created', 'classes', $classId, null, ['name' => $name]);
            Helper::showAlert('Sınıf eklendi', 'success');
        } catch (Exception $e) {
            Helper::showAlert('Hata: ' . $e->getMessage(), 'danger');
        }
    }
    Helper::redirect('/public/admin/classes.php');
}

// Sınıf güncelleme
if (isset($_POST['update_class'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $classId = (int)$_POST['class_id'];
        $name = Helper::sanitize($_POST['name']);
        $description = Helper::sanitize($_POST['description']);

        $stmt = $db->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$classId]);
        $oldData = $stmt->fetch();

        $stmt = $db->prepare("UPDATE classes SET name = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $classId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'class_updated', 'classes', $classId, $oldData, ['name' => $name, 'description' => $description]);
            Helper::showAlert('Sınıf güncellendi', 'success');
        }
    }
    Helper::redirect('/public/admin/classes.php');
}

// Sınıf silme
if (isset($_POST['delete_class'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $classId = (int)$_POST['class_id'];

        $stmt = $db->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$classId]);
        $oldData = $stmt->fetch();

        $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
        if ($stmt->execute([$classId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'class_deleted', 'classes', $classId, $oldData);
            Helper::showAlert('Sınıf silindi', 'success');
        }
    }
    Helper::redirect('/public/admin/classes.php');
}

// Sınıfları getir
$stmt = $db->query("
    SELECT c.*, COUNT(s.id) as student_count
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id
    GROUP BY c.id
    ORDER BY c.name
");
$classes = $stmt->fetchAll();

include '../../app/views/admin/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Sınıf Yönetimi</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
            Yeni Sınıf Ekle
        </button>
    </div>

    <?php Helper::displayAlert(); ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sınıf Adı</th>
                            <th>Açıklama</th>
                            <th>Öğrenci Sayısı</th>
                            <th>Oluşturma Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['name']); ?></td>
                            <td><?php echo htmlspecialchars($class['description'] ?? '-'); ?></td>
                            <td><?php echo $class['student_count']; ?></td>
                            <td><?php echo Helper::formatDate($class['created_at']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $class['id']; ?>">
                                    Düzenle
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                    <button type="submit" name="delete_class" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                            </td>
                        </tr>

                        <!-- Düzenleme Modal -->
                        <div class="modal fade" id="editModal<?php echo $class['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Sınıf Düzenle</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">

                                            <div class="mb-3">
                                                <label class="form-label">Sınıf Adı</label>
                                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($class['name']); ?>" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Açıklama</label>
                                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($class['description'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                            <button type="submit" name="update_class" class="btn btn-primary">Güncelle</button>
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

<!-- Ekleme Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Sınıf Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mb-3">
                        <label class="form-label">Sınıf Adı</label>
                        <input type="text" name="name" class="form-control" placeholder="Örn: 1. Sınıf Kızlar" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="add_class" class="btn btn-primary">Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../app/views/admin/footer.php'; ?>