<?php
require_once '../../config/config.php';
Helper::checkTeacher();

// Öğretmen bilgileri
$stmt = $db->prepare("SELECT tp.* FROM teachers_profiles tp WHERE tp.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

// İstatistikler
$stmt = $db->prepare("SELECT COUNT(DISTINCT subject_id) as subject_count FROM teacher_subjects WHERE teacher_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['subjects'] = $stmt->fetch()['subject_count'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT class_id) as class_count FROM teacher_subjects WHERE teacher_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['classes'] = $stmt->fetch()['class_count'];

$stmt = $db->prepare("SELECT COUNT(*) as grade_count FROM grades WHERE created_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['grades'] = $stmt->fetch()['grade_count'];

// Son not girişleri
$stmt = $db->prepare("
    SELECT g.*, s.first_name, s.last_name, sub.name as subject_name, p.name as period_name
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN subjects sub ON g.subject_id = sub.id
    JOIN periods p ON g.period_id = p.id
    WHERE g.created_by = ?
    ORDER BY g.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recentGrades = $stmt->fetchAll();

include '../../app/views/teacher/header.php';
?>

<!-- Font Awesome ve özel stil -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .dashboard-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out;
    }
    .dashboard-card:hover {
        transform: translateY(-5px);
    }
    .dashboard-icon {
        font-size: 3rem;
        opacity: 0.85;
    }
    .gradient-bg-1 { background: linear-gradient(135deg, #007bff, #00a8ff); }
    .gradient-bg-2 { background: linear-gradient(135deg, #28a745, #80d16c); }
    .gradient-bg-3 { background: linear-gradient(135deg, #17a2b8, #48c6ef); }
    .card-body h5 {
        font-weight: 600;
    }
</style>

<div class="container-fluid py-4">
    <h2 class="mb-4 fw-bold text-primary">
        <i class="fa-solid fa-chalkboard-teacher me-2"></i>
        Hoş Geldiniz, <?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>
    </h2>

    <?php Helper::displayAlert(); ?>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card text-white dashboard-card gradient-bg-1">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Derslerim</h5>
                        <p class="card-text display-5 fw-bold mb-0"><?php echo $stats['subjects']; ?></p>
                    </div>
                    <i class="fa-solid fa-book dashboard-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-white dashboard-card gradient-bg-2">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Sınıflarım</h5>
                        <p class="card-text display-5 fw-bold mb-0"><?php echo $stats['classes']; ?></p>
                    </div>
                    <i class="fa-solid fa-users dashboard-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-white dashboard-card gradient-bg-3">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Toplam Not Girişi</h5>
                        <p class="card-text display-5 fw-bold mb-0"><?php echo $stats['grades']; ?></p>
                    </div>
                    <i class="fa-solid fa-clipboard-check dashboard-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-light border-0 d-flex align-items-center">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>
            <h5 class="mb-0">Son Not Girişlerim</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead class="table-primary text-center">
                        <tr>
                            <th>Öğrenci</th>
                            <th>Ders</th>
                            <th>Dönem</th>
                            <th>Not</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php if (empty($recentGrades)): ?>
                        <tr>
                            <td colspan="5" class="text-muted py-4">Henüz not girişi yapmadınız.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentGrades as $grade): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($grade['period_name']); ?></td>
                            <td><strong><?php echo number_format($grade['grade'], 2); ?></strong></td>
                            <td><?php echo Helper::formatDate($grade['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../app/views/teacher/footer.php'; ?>
