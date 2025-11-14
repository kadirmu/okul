<?php
require_once '../../config/config.php';
Helper::checkAdmin();

// Genel ayarlar güncelleme
if (isset($_POST['update_general'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $appName = Helper::sanitize($_POST['app_name']);

        Helper::updateSetting($db, 'app_name', $appName);
        Helper::createLog($db, $_SESSION['user_id'], 'settings_updated', 'settings', null, null, ['app_name' => $appName]);
        Helper::showAlert('Ayarlar güncellendi', 'success');
    }
    Helper::redirect('/public/admin/settings.php');
}

// SMTP ayarları
if (isset($_POST['update_smtp'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        Helper::updateSetting($db, 'smtp_host', Helper::sanitize($_POST['smtp_host']));
        Helper::updateSetting($db, 'smtp_port', Helper::sanitize($_POST['smtp_port']));
        Helper::updateSetting($db, 'smtp_username', Helper::sanitize($_POST['smtp_username']));
        Helper::updateSetting($db, 'smtp_encryption', Helper::sanitize($_POST['smtp_encryption']));
        Helper::updateSetting($db, 'smtp_from_email', Helper::sanitize($_POST['smtp_from_email']));
        Helper::updateSetting($db, 'smtp_from_name', Helper::sanitize($_POST['smtp_from_name']));

        if (!empty($_POST['smtp_password'])) {
            Helper::updateSetting($db, 'smtp_password', Helper::sanitize($_POST['smtp_password']));
        }

        Helper::createLog($db, $_SESSION['user_id'], 'smtp_settings_updated', 'settings', null);
        Helper::showAlert('SMTP ayarları güncellendi', 'success');
    }
    Helper::redirect('/public/admin/settings.php');
}

// Telegram ayarları
if (isset($_POST['update_telegram'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $botToken = Helper::sanitize($_POST['telegram_bot_token']);
        $chatIds = Helper::sanitize($_POST['telegram_chat_ids']);

        // Chat ID'leri JSON array'e çevir
        $chatIdsArray = array_filter(array_map('trim', explode(',', $chatIds)));
        $chatIdsJson = json_encode($chatIdsArray);

        Helper::updateSetting($db, 'telegram_bot_token', $botToken);
        Helper::updateSetting($db, 'telegram_chat_ids', $chatIdsJson);

        Helper::createLog($db, $_SESSION['user_id'], 'telegram_settings_updated', 'settings', null);
        Helper::showAlert('Telegram ayarları güncellendi', 'success');
    }
    Helper::redirect('/public/admin/settings.php');
}

// Bildirim ayarları
if (isset($_POST['update_notifications'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        $emailEnabled = isset($_POST['notification_email']) ? '1' : '0';
        $telegramEnabled = isset($_POST['notification_telegram']) ? '1' : '0';

        Helper::updateSetting($db, 'notification_email', $emailEnabled);
        Helper::updateSetting($db, 'notification_telegram', $telegramEnabled);

        Helper::createLog($db, $_SESSION['user_id'], 'notification_settings_updated', 'settings', null);
        Helper::showAlert('Bildirim ayarları güncellendi', 'success');
    }
    Helper::redirect('/public/admin/settings.php');
}

// Yedekleme
if (isset($_POST['backup_database'])) {
    if (Helper::validateCSRF($_POST['csrf_token'])) {
        try {
            $backupFile = BASE_PATH . '/backups/backup_' . date('Y-m-d_H-i-s') . '.sql';

            if (!is_dir(BASE_PATH . '/backups')) {
                mkdir(BASE_PATH . '/backups', 0755, true);
            }

            $tables = [];
            $result = $db->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $sqlContent = '';
            foreach ($tables as $table) {
                $result = $db->query("SELECT * FROM {$table}");
                $numColumns = $result->columnCount();

                $sqlContent .= "DROP TABLE IF EXISTS {$table};\n";

                $result2 = $db->query("SHOW CREATE TABLE {$table}");
                $row2 = $result2->fetch(PDO::FETCH_NUM);
                $sqlContent .= $row2[1] . ";\n\n";

                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $sqlContent .= "INSERT INTO {$table} VALUES(";
                    for ($j = 0; $j < $numColumns; $j++) {
                        $row[$j] = $row[$j] ? addslashes($row[$j]) : '';
                        $sqlContent .= '"' . $row[$j] . '"';
                        if ($j < ($numColumns - 1)) {
                            $sqlContent .= ',';
                        }
                    }
                    $sqlContent .= ");\n";
                }
                $sqlContent .= "\n\n";
            }

            file_put_contents($backupFile, $sqlContent);

            Helper::createLog($db, $_SESSION['user_id'], 'database_backup_created', 'settings', null);

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
            readfile($backupFile);
            exit;
        } catch (Exception $e) {
            Helper::showAlert('Yedekleme hatası: ' . $e->getMessage(), 'danger');
        }
    }
    Helper::redirect('/public/admin/settings.php');
}

// Ayarları getir
$appName = Helper::getSetting($db, 'app_name');
$smtpHost = Helper::getSetting($db, 'smtp_host');
$smtpPort = Helper::getSetting($db, 'smtp_port');
$smtpUsername = Helper::getSetting($db, 'smtp_username');
$smtpEncryption = Helper::getSetting($db, 'smtp_encryption');
$smtpFromEmail = Helper::getSetting($db, 'smtp_from_email');
$smtpFromName = Helper::getSetting($db, 'smtp_from_name');
$telegramBotToken = Helper::getSetting($db, 'telegram_bot_token');
$telegramChatIds = json_decode(Helper::getSetting($db, 'telegram_chat_ids'), true);
$notificationEmail = Helper::getSetting($db, 'notification_email');
$notificationTelegram = Helper::getSetting($db, 'notification_telegram');

include '../../app/views/admin/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">Sistem Ayarları</h2>

    <?php Helper::displayAlert(); ?>

    <!-- Genel Ayarlar -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Genel Ayarlar</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label class="form-label">Uygulama Adı</label>
                    <input type="text" name="app_name" class="form-control" value="<?php echo htmlspecialchars($appName); ?>" required>
                </div>

                <button type="submit" name="update_general" class="btn btn-primary">Güncelle</button>
            </form>
        </div>
    </div>

    <!-- SMTP Ayarları -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>SMTP Ayarları</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($smtpHost); ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($smtpPort); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($smtpUsername); ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Şifre</label>
                        <input type="password" name="smtp_password" class="form-control" placeholder="Değiştirmek için girin">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Şifreleme</label>
                        <select name="smtp_encryption" class="form-select">
                            <option value="tls" <?php echo $smtpEncryption == 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo $smtpEncryption == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Gönderen E-posta</label>
                        <input type="email" name="smtp_from_email" class="form-control" value="<?php echo htmlspecialchars($smtpFromEmail); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Gönderen Adı</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?php echo htmlspecialchars($smtpFromName); ?>">
                    </div>
                </div>

                <button type="submit" name="update_smtp" class="btn btn-primary">Güncelle</button>
            </form>
        </div>
    </div>

    <!-- Telegram Ayarları -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Telegram Ayarları</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label class="form-label">Bot Token</label>
                    <input type="text" name="telegram_bot_token" class="form-control" value="<?php echo htmlspecialchars($telegramBotToken); ?>">
                    <small class="text-muted">BotFather'dan alınan token</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Chat ID'ler (virgülle ayırın)</label>
                    <input type="text" name="telegram_chat_ids" class="form-control" value="<?php echo htmlspecialchars(implode(', ', $telegramChatIds ?? [])); ?>">
                    <small class="text-muted">Örn: 123456789, 987654321</small>
                </div>

                <button type="submit" name="update_telegram" class="btn btn-primary">Güncelle</button>
            </form>
        </div>
    </div>

    <!-- Bildirim Ayarları -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Bildirim Ayarları</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="notification_email" class="form-check-input" id="notifEmail" <?php echo $notificationEmail == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notifEmail">E-posta Bildirimleri</label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="notification_telegram" class="form-check-input" id="notifTelegram" <?php echo $notificationTelegram == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notifTelegram">Telegram Bildirimleri</label>
                    </div>
                </div>

                <button type="submit" name="update_notifications" class="btn btn-primary">Güncelle</button>
            </form>
        </div>
    </div>

    <!-- Yedekleme -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Veritabanı Yedekleme</h5>
        </div>
        <div class="card-body">
            <form method="POST" onsubmit="return confirm('Yedek oluşturulsun mu?');">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <p>Veritabanının tam yedeğini SQL formatında indirebilirsiniz.</p>
                <button type="submit" name="backup_database" class="btn btn-success">Yedek Oluştur ve İndir</button>
            </form>
        </div>
    </div>
</div>

<?php include '../../app/views/admin/footer.php'; ?>