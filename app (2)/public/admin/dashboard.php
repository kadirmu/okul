<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// İstatistikler
$stats = [];

$stmt = $db->query("SELECT COUNT(*) as total FROM students");
$stats['students'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'");
$stats['teachers'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM classes");
$stats['classes'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM subjects");
$stats['subjects'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'teacher' AND status = 'pending'");
$stats['pending_teachers'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT l.*, u.email FROM logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 10");
$recent_logs = $stmt->fetchAll();

include '../../app/views/admin/header.php';
?>

<!-- Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">

<style>
    .dashboard-card {
        border: none;
        border-radius: 1rem;
        color: #fff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    .icon-container {
        font-size: 2.5rem;
        opacity: 0.9;
    }
    .table td, .table th {
        vertical-align: middle;
    }
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fa-solid fa-chart-line text-primary me-2"></i>Yönetim Paneli</h2>
        <a href="logout.php" class="btn btn-outline-danger"><i class="fa-solid fa-right-from-bracket me-1"></i> Çıkış</a>
    </div>

    <?php Helper::displayAlert(); ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card dashboard-card bg-gradient-primary" style="background: linear-gradient(45deg, #4e73df, #224abe);">
                <div class="card-body text-center">
                    <div class="icon-container mb-2"><i class="fa-solid fa-user-graduate"></i></div>
                    <h6>Toplam Öğrenci</h6>
                    <h2 class="fw-bold"><?php echo $stats['students']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card" style="background: linear-gradient(45deg, #1cc88a, #17a673);">
                <div class="card-body text-center">
                    <div class="icon-container mb-2"><i class="fa-solid fa-chalkboard-teacher"></i></div>
                    <h6>Toplam Öğretmen</h6>
                    <h2 class="fw-bold"><?php echo $stats['teachers']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card" style="background: linear-gradient(45deg, #36b9cc, #2c9faf);">
                <div class="card-body text-center">
                    <div class="icon-container mb-2"><i class="fa-solid fa-school"></i></div>
                    <h6>Toplam Sınıf</h6>
                    <h2 class="fw-bold"><?php echo $stats['classes']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card" style="background: linear-gradient(45deg, #f6c23e, #dda20a);">
                <div class="card-body text-center">
                    <div class="icon-container mb-2"><i class="fa-solid fa-user-clock"></i></div>
                    <h6>Bekleyen Öğretmen</h6>
                    <h2 class="fw-bold"><?php echo $stats['pending_teachers']; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex align-items-center">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>
            <h5 class="mb-0 fw-bold">Son Aktiviteler</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fa-solid fa-user"></i> Kullanıcı</th>
                            <th><i class="fa-solid fa-bolt"></i> İşlem</th>
                            <th><i class="fa-solid fa-table"></i> Tablo</th>
                            <th><i class="fa-solid fa-calendar-day"></i> Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['email'] ?? 'Sistem'); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['target_table'] ?? '-'); ?></td>
                            <td><?php echo Helper::formatDate($log['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../app/views/admin/footer.php'; ?>
