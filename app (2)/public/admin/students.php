<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// Öğrenci ekleme
if (isset($_POST['add_student'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $firstName = Helper::sanitize($_POST['first_name']);
        $lastName = Helper::sanitize($_POST['last_name']);
        $gender = Helper::sanitize($_POST['gender']);
        $classId = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $studentNumber = Helper::sanitize($_POST['student_number']);

        try {
            $stmt = $db->prepare("INSERT INTO students (first_name, last_name, gender, class_id, student_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$firstName, $lastName, $gender, $classId, $studentNumber]);
            $studentId = $db->lastInsertId();

            Helper::createLog($db, $_SESSION['user_id'], 'student_created', 'students', $studentId, null, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $gender
            ]);

            Helper::showAlert('Öğrenci eklendi', 'success');
        } catch (Exception $e) {
            Helper::showAlert('Hata: ' . $e->getMessage(), 'danger');
        }
    }
    Helper::redirect('/public/admin/students.php');
}

// Öğrenci silme
if (isset($_POST['delete_student'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $studentId = (int)$_POST['student_id'];

        // Eski veriyi al
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $oldData = $stmt->fetch();

        $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
        if ($stmt->execute([$studentId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'student_deleted', 'students', $studentId, $oldData);
            Helper::showAlert('Öğrenci silindi', 'success');
        }
    }
    Helper::redirect('/public/admin/students.php');
}

// Öğrenci güncelleme
if (isset($_POST['update_student'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $studentId = (int)$_POST['student_id'];
        $firstName = Helper::sanitize($_POST['first_name']);
        $lastName = Helper::sanitize($_POST['last_name']);
        $gender = Helper::sanitize($_POST['gender']);
        $classId = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $studentNumber = Helper::sanitize($_POST['student_number']);

        // Eski veriyi al
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $oldData = $stmt->fetch();

        $stmt = $db->prepare("UPDATE students SET first_name = ?, last_name = ?, gender = ?, class_id = ?, student_number = ? WHERE id = ?");
        if ($stmt->execute([$firstName, $lastName, $gender, $classId, $studentNumber, $studentId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'student_updated', 'students', $studentId, $oldData, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $gender,
                'class_id' => $classId
            ]);
            Helper::showAlert('Öğrenci güncellendi', 'success');
        }
    }
    Helper::redirect('/public/admin/students.php');
}

// Öğrencileri getir
$search = isset($_GET['search']) ? Helper::sanitize($_GET['search']) : '';
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;

$query = "SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($classFilter) {
    $query .= " AND s.class_id = ?";
    $params[] = $classFilter;
}

$query .= " ORDER BY s.first_name, s.last_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Sınıfları getir
$classes = $db->query("SELECT * FROM classes ORDER BY name")->fetchAll();

include '../../app/views/admin/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Öğrenci Yönetimi</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            Yeni Öğrenci Ekle
        </button>
    </div>

    <?php Helper::displayAlert(); ?>

    <!-- Arama ve Filtreleme -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Ad, Soyad veya Numara ile ara..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="class" class="form-select">
                        <option value="0">Tüm Sınıflar</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $classFilter == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filtrele</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Numara</th>
                            <th>Ad Soyad</th>
                            <th>Cinsiyet</th>
                            <th>Sınıf</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td>
                                <?php
                                $genderText = ['male' => 'Erkek', 'female' => 'Kız', 'other' => 'Diğer'];
                                echo $genderText[$student['gender']];
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['class_name'] ?? 'Atanmamış'); ?></td>
                            <td><?php echo Helper::formatDate($student['created_at']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $student['id']; ?>">
                                    Düzenle
                                </button>
                                <a href="reports.php?student=<?php echo $student['id']; ?>" class="btn btn-sm btn-success">Notlar</a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <button type="submit" name="delete_student" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                            </td>
                        </tr>

                        <!-- Düzenleme Modal -->
                        <div class="modal fade" id="editModal<?php echo $student['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Öğrenci Düzenle</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">

                                            <div class="mb-3">
                                                <label class="form-label">Numara</label>
                                                <input type="text" name="student_number" class="form-control" value="<?php echo htmlspecialchars($student['student_number']); ?>" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Ad</label>
                                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Soyad</label>
                                                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Cinsiyet</label>
                                                <select name="gender" class="form-select" required>
                                                    <option value="male" <?php echo $student['gender'] == 'male' ? 'selected' : ''; ?>>Erkek</option>
                                                    <option value="female" <?php echo $student['gender'] == 'female' ? 'selected' : ''; ?>>Kız</option>
                                                    <option value="other" <?php echo $student['gender'] == 'other' ? 'selected' : ''; ?>>Diğer</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Sınıf</label>
                                                <select name="class_id" class="form-select">
                                                    <option value="">Seçiniz</option>
                                                    <?php foreach ($classes as $class): ?>
                                                        <option value="<?php echo $class['id']; ?>" <?php echo $student['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($class['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                            <button type="submit" name="update_student" class="btn btn-primary">Güncelle</button>
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
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Öğrenci Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mb-3">
                        <label class="form-label">Numara</label>
                        <input type="text" name="student_number" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ad</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Soyad</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cinsiyet</label>
                        <select name="gender" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <option value="male">Erkek</option>
                            <option value="female">Kız</option>
                            <option value="other">Diğer</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sınıf</label>
                        <select name="class_id" class="form-select">
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
                    <button type="submit" name="add_student" class="btn btn-primary">Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../app/views/admin/footer.php'; ?>