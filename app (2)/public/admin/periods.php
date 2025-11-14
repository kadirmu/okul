<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// Dönem ekleme
if (isset($_POST['add_period'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $name = Helper::sanitize($_POST['name']);
        $startDate = Helper::sanitize($_POST['start_date']);
        $endDate = Helper::sanitize($_POST['end_date']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $db->prepare("INSERT INTO periods (name, start_date, end_date, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $startDate, $endDate, $isActive]);
            $periodId = $db->lastInsertId();

            Helper::createLog($db, $_SESSION['user_id'], 'period_created', 'periods', $periodId, null, ['name' => $name]);
            Helper::showAlert('Dönem eklendi', 'success');
        } catch (Exception $e) {
            Helper::showAlert('Hata: ' . $e->getMessage(), 'danger');
        }
    }
    Helper::redirect('/public/admin/periods.php');
}

// Dönem güncelleme
if (isset($_POST['update_period'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $periodId = (int)$_POST['period_id'];
        $name = Helper::sanitize($_POST['name']);
        $startDate = Helper::sanitize($_POST['start_date']);
        $endDate = Helper::sanitize($_POST['end_date']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $db->prepare("SELECT * FROM periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $oldData = $stmt->fetch();

        $stmt = $db->prepare("UPDATE periods SET name = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?");
        if ($stmt->execute([$name, $startDate, $endDate, $isActive, $periodId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'period_updated', 'periods', $periodId, $oldData, ['name' => $name]);
            Helper::showAlert('Dönem güncellendi', 'success');
        }
    }
    Helper::redirect('/public/admin/periods.php');
}

// Dönem silme
if (isset($_POST['delete_period'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $periodId = (int)$_POST['period_id'];

        $stmt = $db->prepare("SELECT * FROM periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $oldData = $stmt->fetch();

        $stmt = $db->prepare("DELETE FROM periods WHERE id = ?");
        if ($stmt->execute([$periodId])) {
            Helper::createLog($db, $_SESSION['user_id'], 'period_deleted', 'periods', $periodId, $oldData);
            Helper::showAlert('Dönem silindi', 'success');
        }
    }
    Helper::redirect('/public/admin/periods.php');
}

// Dönemleri getir
$periods = $db->query("SELECT * FROM periods ORDER BY start_date DESC")->fetchAll();

include '../../app/views/admin/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Dönem Yönetimi</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPeriodModal">
            Yeni Dönem Ekle
        </button>
    </div>

    <?php Helper::displayAlert(); ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Dönem Adı</th>
                            <th>Başlangıç</th>
                            <th>Bitiş</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $period): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($period['name']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($period['start_date'])); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($period['end_date'])); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $period['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $period['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $period['id']; ?>">
                                    Düzenle
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                    <button type="submit" name="delete_period" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                            </td>
                        </tr>

                        <!-- Düzenleme Modal -->
                        <div class="modal fade" id="editModal<?php echo $period['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Dönem Düzenle</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">

                                            <div class="mb-3">
                                                <label class="form-label">Dönem Adı</label>
                                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($period['name']); ?>" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Başlangıç Tarihi</label>
                                                <input type="date" name="start_date" class="form-control" value="<?php echo $period['start_date']; ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Bitiş Tarihi</label>
                                                <input type="date" name="end_date" class="form-control" value="<?php echo $period['end_date']; ?>">
                                            </div>

                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_active" class="form-check-input" id="active<?php echo $period['id']; ?>" <?php echo $period['is_active'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="active<?php echo $period['id']; ?>">Aktif</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                            <button type="submit" name="update_period" class="btn btn-primary">Güncelle</button>
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
<div class="modal fade" id="addPeriodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Dönem Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mb-3">
                        <label class="form-label">Dönem Adı</label>
                        <input type="text" name="name" class="form-control" placeholder="Örn: 1. Dönem" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="activeNew" checked>
                            <label class="form-check-label" for="activeNew">Aktif</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="add_period" class="btn btn-primary">Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../app/views/admin/footer.php'; ?>