<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// Not ekleme/güncelleme
if (isset($_POST['save_grade'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $studentId = (int)$_POST['student_id'];
        $subjectId = (int)$_POST['subject_id'];
        $periodId = (int)$_POST['period_id'];
        $grade = (float)$_POST['grade'];

        try {
            // Mevcut notu kontrol et
            $stmt = $db->prepare("SELECT * FROM grades WHERE student_id = ? AND subject_id = ? AND period_id = ?");
            $stmt->execute([$studentId, $subjectId, $periodId]);
            $existingGrade = $stmt->fetch();

            if ($existingGrade) {
                // Güncelleme
                $stmt = $db->prepare("UPDATE grades SET grade = ?, created_by = ? WHERE id = ?");
                $stmt->execute([$grade, $_SESSION['user_id'], $existingGrade['id']]);

                // Revizyon kaydet
                $stmt = $db->prepare("INSERT INTO grade_revisions (grade_id, old_grade, new_grade, changed_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$existingGrade['id'], $existingGrade['grade'], $grade, $_SESSION['user_id']]);

                Helper::createLog($db, $_SESSION['user_id'], 'grade_updated', 'grades', $existingGrade['id'],
                    ['grade' => $existingGrade['grade']],
                    ['grade' => $grade]
                );

                Helper::showAlert('Not güncellendi', 'success');
            } else {
                // Yeni kayıt
                $stmt = $db->prepare("INSERT INTO grades (student_id, subject_id, period_id, grade, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$studentId, $subjectId, $periodId, $grade, $_SESSION['user_id']]);
                $gradeId = $db->lastInsertId();

                Helper::createLog($db, $_SESSION['user_id'], 'grade_created', 'grades', $gradeId, null, ['grade' => $grade]);

                // Bildirim gönder
                $stmt = $db->prepare("SELECT s.first_name, s.last_name, sub.name as subject_name FROM students s, subjects sub WHERE s.id = ? AND sub.id = ?");
                $stmt->execute([$studentId, $subjectId]);
                $info = $stmt->fetch();

                $message = "Yeni not girişi: {$info['first_name']} {$info['last_name']} - {$info['subject_name']}: {$grade}";
                Helper::sendNotification($db, $message);

                Helper::showAlert('Not eklendi', 'success');
            }
        } catch (Exception $e) {
            Helper::showAlert('Hata: ' . $e->getMessage(), 'danger');
        }
    }
    Helper::redirect('/public/admin/grades.php');
}

// Toplu not girişi
if (isset($_POST['bulk_save_grades'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $classId = (int)$_POST['class_id'];
        $subjectId = (int)$_POST['subject_id'];
        $periodId = (int)$_POST['period_id'];
        $grades = $_POST['grades'];

        try {
            $db->beginTransaction();

            foreach ($grades as $studentId => $grade) {
                if (!empty($grade)) {
                    $grade = (float)$grade;

                    $stmt = $db->prepare("SELECT * FROM grades WHERE student_id = ? AND subject_id = ? AND period_id = ?");
                    $stmt->execute([$studentId, $subjectId, $periodId]);
                    $existingGrade = $stmt->fetch();

                    if ($existingGrade) {
                        $stmt = $db->prepare("UPDATE grades SET grade = ?, created_by = ? WHERE id = ?");
                        $stmt->execute([$grade, $_SESSION['user_id'], $existingGrade['id']]);

                        $stmt = $db->prepare("INSERT INTO grade_revisions (grade_id, old_grade, new_grade, changed_by) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$existingGrade['id'], $existingGrade['grade'], $grade, $_SESSION['user_id']]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO grades (student_id, subject_id, period_id, grade, created_by) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$studentId, $subjectId, $periodId, $grade, $_SESSION['user_id']]);
                    }
                }
            }

            $db->commit();
            Helper::createLog($db, $_SESSION['user_id'], 'bulk_grades_saved', 'grades', null);
            Helper::showAlert('Notlar kaydedildi', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            Helper::showAlert('Hata: ' . $e->getMessage(), 'danger');
        }
    }
    Helper::redirect('/public/admin/grades.php');
}

// Filtreler
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$periodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;

// Sınıflar, dersler ve dönemler
$classes = $db->query("SELECT * FROM classes ORDER BY name")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$periods = $db->query("SELECT * FROM periods ORDER BY start_date DESC")->fetchAll();

$students = [];
if ($classId && $subjectId && $periodId) {
    $stmt = $db->prepare("
        SELECT s.*, g.grade, g.id as grade_id
        FROM students s
        LEFT JOIN grades g ON s.id = g.student_id AND g.subject_id = ? AND g.period_id = ?
        WHERE s.class_id = ?
        ORDER BY s.first_name, s.last_name
    ");
    $stmt->execute([$subjectId, $periodId, $classId]);
    $students = $stmt->fetchAll();
}

include '../../app/views/admin/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">Not Girişi</h2>

    <?php Helper::displayAlert(); ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Sınıf</label>
                    <select name="class_id" class="form-select" required>
                        <option value="">Seçiniz</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $classId == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Ders</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Seçiniz</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $subjectId == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Dönem</label>
                    <select name="period_id" class="form-select" required>
                        <option value="">Seçiniz</option>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?php echo $period['id']; ?>" <?php echo $periodId == $period['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($period['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Listele</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($students)): ?>
    <div class="card">
        <div class="card-header">
            <h5>Öğrenci Notları - Toplu Giriş</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
                <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Numara</th>
                                <th>Öğrenci</th>
                                <th>Not</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td>
                                    <input type="number"
                                           name="grades[<?php echo $student['id']; ?>]"
                                           class="form-control"
                                           min="0"
                                           max="100"
                                           step="0.01"
                                           value="<?php echo $student['grade'] ?? ''; ?>"
                                           style="max-width: 150px;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" name="bulk_save_grades" class="btn btn-success">Notları Kaydet</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../app/views/admin/footer.php'; ?>