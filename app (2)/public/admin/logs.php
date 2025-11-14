<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// Filtreler
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$action = isset($_GET['action']) ? Helper::sanitize($_GET['action']) : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$query = "SELECT l.*, u.email FROM logs l LEFT JOIN users u ON l.user_id = u.id WHERE 1=1";
$params = [];

if ($dateFrom) {
    $query .= " AND DATE(l.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $query .= " AND DATE(l.created_at) <= ?";
    $params[] = $dateTo;
}

if ($action) {
    $query .= " AND l.action LIKE ?";
    $params[] = "%{$action}%";
}

if ($userId) {
    $query .= " AND l.user_id = ?";
    $params[] = $userId;
}

$query .= " ORDER BY l.created_at DESC LIMIT 500";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Kullanıcılar
$users = $db->query("SELECT id, email FROM users ORDER BY email")->fetchAll();

include '../../app/views/admin/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">İşlem Logları</h2>

    <?php Helper::displayAlert(); ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Başlangıç Tarihi</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Bitiş Tarihi</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">İşlem</label>
                    <input type="text" name="action" class="form-control" placeholder="Ara..." value="<?php echo htmlspecialchars($action); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Kullanıcı</label>
                    <select name="user_id" class="form-select">
                        <option value="0">Tümü</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filtrele</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Kullanıcı</th>
                            <th>İşlem</th>
                            <th>Tablo</th>
                            <th>ID</th>
                            <th>IP</th>
                            <th>Detay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo Helper::formatDate($log['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($log['email'] ?? 'Sistem'); ?></td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['target_table'] ?? '-'); ?></td>
                            <td><?php echo $log['target_id'] ?? '-'; ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td>
                                <?php if ($log['old_value'] || $log['new_value']): ?>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#logModal<?php echo $log['id']; ?>">
                                    Görüntüle
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Log Detay Modal -->
                        <?php if ($log['old_value'] || $log['new_value']): ?>
                        <div class="modal fade" id="logModal<?php echo $log['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Log Detayı</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if ($log['old_value']): ?>
                                        <h6>Eski Değer:</h6>
                                        <pre class="bg-light p-3"><?php echo htmlspecialchars($log['old_value']); ?></pre>
                                        <?php endif; ?>

                                        <?php if ($log['new_value']): ?>
                                        <h6>Yeni Değer:</h6>
                                        <pre class="bg-light p-3"><?php echo htmlspecialchars($log['new_value']); ?></pre>
                                        <?php endif; ?>

                                        <h6>User Agent:</h6>
                                        <p class="text-muted"><?php echo htmlspecialchars($log['user_agent']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../app/views/admin/footer.php'; ?>