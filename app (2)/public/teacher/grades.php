<?php
require_once '../../config/config.php';
Helper::checkTeacher();

// Not ekleme/güncelleme
if (isset($_POST['save_grade'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $studentId = (int)$_POST['student_id'];
        $subjectId = (int)$_POST['subject_id'];
        $periodId = (int)$_POST['period_id'];
        $grade = (float)$_POST['grade'];

        // Öğretmenin bu dersi verme yetkisi var mı kontrol et
        $stmt = $db->prepare("SELECT * FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ?");
        $stmt->execute([$_SESSION['user_id'], $subjectId]);
        if (!$stmt->fetch()) {
            Helper::showAlert('Bu derse not girme yetkiniz yok!', 'danger');
            Helper::redirect('/public/teacher/grades.php');
        }

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
    Helper::redirect('/public/teacher/grades.php');
}

// Davranış notu ekleme/güncelleme
if (isset($_POST['save_behavior'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $studentId = (int)$_POST['student_id'];
        $periodId = (int)$_POST['period_id'];
        $note = Helper::sanitize($_POST['note']);

        try {
            $stmt = $db->prepare("SELECT * FROM behavior_notes WHERE student_id = ? AND period_id = ?");
            $stmt->execute([$studentId, $periodId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("UPDATE behavior_notes SET note = ?, created_by = ? WHERE id = ?");
                $stmt->execute([$note, $_SESSION['user_id'], $existing['id']]);
                Helper::showAlert('Davranış notu güncellendi', 'success');
            } else {
                $stmt = $db->prepare("INSERT INTO behavior_notes (student_id, period_id, note, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$studentId, $periodId, $note, $_SESSION['user_id']]);
                Helper::showAlert('Davranış notu eklendi', 'success');
            }

            Helper::createLog($db, $_SESSION['user_id'], 'behavior_note_saved', 'behavior_notes', $studentId);
        } catch (Exception $e) {
            Helper::showAlert('Hata: ' . $e->getMessage(), 'danger');
        }
    }
    Helper::redirect('/public/teacher/grades.php');
}

// Öğretmenin dersleri
$stmt = $db->prepare("
    SELECT DISTINCT ts.subject_id, ts.class_id, s.name as subject_name, c.name as class_name
    FROM teacher_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    JOIN classes c ON ts.class_id = c.id
    WHERE ts.teacher_id = ?
    ORDER BY c.name, s.name
");
$stmt->execute([$_SESSION['user_id']]);
$assignments = $stmt->fetchAll();

// Dönemler
$periods = $db->query("SELECT * FROM periods WHERE is_active = 1 ORDER BY start_date DESC")->fetchAll();

// Filtreler
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$periodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;

$students = [];
if ($classId && $subjectId && $periodId) {
    // Yetki kontrolü
    $stmt = $db->prepare("SELECT * FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ? AND class_id = ?");
    $stmt->execute([$_SESSION['user_id'], $subjectId, $classId]);
    if ($stmt->fetch()) {
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
}

include '../../app/views/teacher/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">Not Girişi</h2>

    <?php Helper::displayAlert(); ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Sınıf</label>
                    <select name="class_id" class="form-select" required onchange="this.form.submit()">
                        <option value="">Seçiniz</option>
                        <?php
                        $classes = [];
                        foreach ($assignments as $assignment) {
                            if (!isset($classes[$assignment['class_id']])) {
                                $classes[$assignment['class_id']] = $assignment['class_name'];
                            }
                        }
                        foreach ($classes as $cId => $cName):
                        ?>
                            <option value="<?php echo $cId; ?>" <?php echo $classId == $cId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Ders</label>
                    <select name="subject_id" class="form-select" required onchange="this.form.submit()">
                        <option value="">Seçiniz</option>
                        <?php
                        foreach ($assignments as $assignment) {
                            if ($classId == $assignment['class_id']) {
                        ?>
                            <option value="<?php echo $assignment['subject_id']; ?>" <?php echo $subjectId == $assignment['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($assignment['subject_name']); ?>
                            </option>
                        <?php
                            }
                        }
                        ?>
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
            <h5>Öğrenci Notları</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Numara</th>
                            <th>Öğrenci</th>
                            <th>Not</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                <button type="button" class="btn btn-sm btn-secondary ms-2" data-bs-toggle="modal" data-bs-target="#behaviorModal<?php echo $student['id']; ?>">
                                    Davranış Notu
                                </button>
                            </td>
                            <td>
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
                                    <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">

                                    <input type="number"
                                           name="grade"
                                           class="form-control"
                                           min="0"
                                           max="100"
                                           step="0.01"
                                           value="<?php echo $student['grade'] ?? ''; ?>"
                                           style="max-width: 150px;"
                                           required>

                                    <button type="submit" name="save_grade" class="btn btn-success">Kaydet</button>
                                </form>
                            </td>
                            <td>
                                <?php if ($student['grade']): ?>
                                    <span class="badge bg-info">Kayıtlı</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Davranış Notu Modal -->
                        <div class="modal fade" id="behaviorModal<?php echo $student['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Davranış Notu - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">

                                            <?php
                                            $stmt = $db->prepare("SELECT note FROM behavior_notes WHERE student_id = ? AND period_id = ?");
                                            $stmt->execute([$student['id'], $periodId]);
                                            $behaviorNote = $stmt->fetch();
                                            ?>

                                            <div class="mb-3">
                                                <label class="form-label">Davranış Notu</label>
                                                <textarea name="note" class="form-control" rows="5" required><?php echo htmlspecialchars($behaviorNote['note'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                            <button type="submit" name="save_behavior" class="btn btn-primary">Kaydet</button>
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
    <?php elseif ($classId && $subjectId && $periodId): ?>
    <div class="alert alert-warning">
        Bu sınıf ve ders kombinasyonu için yetkiniz bulunmamaktadır veya sınıfta öğrenci yoktur.
    </div>
    <?php endif; ?>
</div>

<?php include '../../app/views/teacher/footer.php'; ?>