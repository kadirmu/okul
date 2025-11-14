<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// Öğrenci seçimi
$studentId = isset($_GET['student']) ? (int)$_GET['student'] : 0;
$periodId = isset($_GET['period']) ? (int)$_GET['period'] : 0;

// JSON indirme
if (isset($_GET['download_json']) && $studentId && $periodId) {
    $stmt = $db->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT sub.name as subject_name, g.grade
        FROM grades g
        JOIN subjects sub ON g.subject_id = sub.id
        WHERE g.student_id = ? AND g.period_id = ?
        ORDER BY sub.name
    ");
    $stmt->execute([$studentId, $periodId]);
    $grades = $stmt->fetchAll();

    $data = [
        'student' => $student['first_name'] . ' ' . $student['last_name'],
        'grades' => $grades
    ];

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $student['first_name'] . '_' . $student['last_name'] . '_notlar.json"');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Karne oluştur
if (isset($_POST['generate_report'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $studentId = (int)$_POST['student_id'];
        $periodId = (int)$_POST['period_id'];

        // Öğrenci bilgileri
        $stmt = $db->prepare("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        // Dönem bilgisi
        $stmt = $db->prepare("SELECT * FROM periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch();

        // Notlar
        $stmt = $db->prepare("
            SELECT sub.name as subject_name, g.grade
            FROM grades g
            JOIN subjects sub ON g.subject_id = sub.id
            WHERE g.student_id = ? AND g.period_id = ?
            ORDER BY sub.name
        ");
        $stmt->execute([$studentId, $periodId]);
        $grades = $stmt->fetchAll();

        // Davranış notu
        $stmt = $db->prepare("SELECT note FROM behavior_notes WHERE student_id = ? AND period_id = ?");
        $stmt->execute([$studentId, $periodId]);
        $behaviorNote = $stmt->fetch();

        // Ortalama hesapla
        $total = 0;
        $count = count($grades);
        foreach ($grades as $grade) {
            $total += $grade['grade'];
        }
        $average = $count > 0 ? round($total / $count, 2) : 0;

        // Sınıf ortalaması
        $stmt = $db->prepare("
            SELECT AVG(g.grade) as class_avg
            FROM grades g
            JOIN students s ON g.student_id = s.id
            WHERE s.class_id = ? AND g.period_id = ?
        ");
        $stmt->execute([$student['class_id'], $periodId]);
        $classAvg = $stmt->fetch()['class_avg'];
        $classAvg = $classAvg ? round($classAvg, 2) : 0;

        // PDF oluştur
        require_once BASE_PATH . '/vendor/autoload.php';

        $html = generateReportHTML($student, $period, $grades, $average, $classAvg, $behaviorNote);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = $student['first_name'] . '_' . $student['last_name'] . '_Karne_' . $period['name'] . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);

        Helper::createLog($db, $_SESSION['user_id'], 'report_generated', 'students', $studentId);
        exit;
    }
}

// Öğrenciler
$students = $db->query("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.first_name, s.last_name")->fetchAll();

// Dönemler
$periods = $db->query("SELECT * FROM periods ORDER BY start_date DESC")->fetchAll();

// Seçili öğrenci detayı
$studentData = null;
$studentGrades = [];
if ($studentId) {
    $stmt = $db->prepare("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
    $stmt->execute([$studentId]);
    $studentData = $stmt->fetch();

    if ($periodId) {
        $stmt = $db->prepare("
            SELECT sub.name as subject_name, g.grade, g.created_at, u.email as teacher_email
            FROM grades g
            JOIN subjects sub ON g.subject_id = sub.id
            LEFT JOIN users u ON g.created_by = u.id
            WHERE g.student_id = ? AND g.period_id = ?
            ORDER BY sub.name
        ");
        $stmt->execute([$studentId, $periodId]);
        $studentGrades = $stmt->fetchAll();
    }
}

include '../../app/views/admin/header.php';

function generateReportHTML($student, $period, $grades, $average, $classAvg, $behaviorNote) {
    $color = $student['gender'] == 'female' ? '#ffb6c1' : '#add8e6';
    $borderStyle = $student['gender'] == 'female' ? 'border: 5px solid #ff69b4;' : 'border: 5px solid #4682b4;';

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background-color: ' . $color . '; }
            .container { background: white; padding: 30px; ' . $borderStyle . ' border-radius: 15px; }
            h1 { text-align: center; color: #0a3255; margin-bottom: 30px; }
            .info { margin-bottom: 20px; }
            .info p { margin: 5px 0; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #0a3255; color: white; }
            .summary { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
            .behavior { margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>' . Helper::getSetting($GLOBALS['db'], 'app_name') . '</h1>
            <h2 style="text-align: center; color: #0a3255;">KARNE</h2>

            <div class="info">
                <p><strong>Öğrenci:</strong> ' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . '</p>
                <p><strong>Numara:</strong> ' . htmlspecialchars($student['student_number']) . '</p>
                <p><strong>Sınıf:</strong> ' . htmlspecialchars($student['class_name'] ?? '-') . '</p>
                <p><strong>Dönem:</strong> ' . htmlspecialchars($period['name']) . '</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Ders</th>
                        <th style="text-align: center;">Not</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($grades as $grade) {
        $html .= '<tr>
            <td>' . htmlspecialchars($grade['subject_name']) . '</td>
            <td style="text-align: center;"><strong>' . number_format($grade['grade'], 2) . '</strong></td>
        </tr>';
    }

    $html .= '</tbody>
            </table>

            <div class="summary">
                <p><strong>Dönem Ortalaması:</strong> ' . number_format($average, 2) . '</p>
                <p><strong>Sınıf Ortalaması:</strong> ' . number_format($classAvg, 2) . '</p>
            </div>';

    if ($behaviorNote) {
        $html .= '<div class="behavior">
            <p><strong>Davranış Notu:</strong></p>
            <p>' . htmlspecialchars($behaviorNote['note']) . '</p>
        </div>';
    }

    $html .= '
            <div style="margin-top: 40px; text-align: center; color: #666;">
                <p>Tarih: ' . date('d.m.Y') . '</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}
?>

<div class="container-fluid">
    <h2 class="mb-4">Karne ve Raporlar</h2>

    <?php Helper::displayAlert(); ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Öğrenci Seç</h5>
                </div>
                <div class="card-body">
                    <div class="list-group" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($students as $student): ?>
                        <a href="?student=<?php echo $student['id']; ?>"
                           class="list-group-item list-group-item-action <?php echo $studentId == $student['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            <br>
                            <small><?php echo htmlspecialchars($student['class_name'] ?? 'Sınıf atanmamış'); ?></small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <?php if ($studentData): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Öğrenci Bilgileri</h5>
                </div>
                <div class="card-body">
                    <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($studentData['first_name'] . ' ' . $studentData['last_name']); ?></p>
                    <p><strong>Numara:</strong> <?php echo htmlspecialchars($studentData['student_number']); ?></p>
                    <p><strong>Sınıf:</strong> <?php echo htmlspecialchars($studentData['class_name'] ?? '-'); ?></p>
                    <p><strong>Cinsiyet:</strong> <?php echo ['male' => 'Erkek', 'female' => 'Kız', 'other' => 'Diğer'][$studentData['gender']]; ?></p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5>Dönem Seç ve İşlemler</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 mb-3">
                        <input type="hidden" name="student" value="<?php echo $studentId; ?>">
                        <div class="col-md-8">
                            <select name="period" class="form-select" required>
                                <option value="">Dönem Seçiniz</option>
                                <?php foreach ($periods as $period): ?>
                                    <option value="<?php echo $period['id']; ?>" <?php echo $periodId == $period['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($period['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Notları Göster</button>
                        </div>
                    </form>

                    <?php if ($periodId): ?>
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                            <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">
                            <button type="submit" name="generate_report" class="btn btn-success">
                                PDF Karne Oluştur
                            </button>
                        </form>
                        <a href="?student=<?php echo $studentId; ?>&period=<?php echo $periodId; ?>&download_json=1" class="btn btn-info">
                            JSON İndir
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($studentGrades)): ?>
            <div class="card">
                <div class="card-header">
                    <h5>Notlar</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Ders</th>
                                    <th>Not</th>
                                    <th>Tarih</th>
                                    <th>Öğretmen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total = 0;
                                foreach ($studentGrades as $grade):
                                    $total += $grade['grade'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                    <td><strong><?php echo number_format($grade['grade'], 2); ?></strong></td>
                                    <td><?php echo Helper::formatDate($grade['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($grade['teacher_email'] ?? 'Sistem'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-info">
                                    <td><strong>Ortalama</strong></td>
                                    <td colspan="3"><strong><?php echo number_format($total / count($studentGrades), 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-info">
                Lütfen sol menüden bir öğrenci seçiniz.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../app/views/admin/footer.php'; ?>