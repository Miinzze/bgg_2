<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('settings_manage');

$message = '';
$messageType = '';

// Einstellungen laden
$stmt = $pdo->query("SELECT * FROM escalation_settings ORDER BY id DESC LIMIT 1");
$settings = $stmt->fetch();

if (!$settings) {
    $stmt = $pdo->query("INSERT INTO escalation_settings (warning_days, overdue_days, critical_days, notification_emails) VALUES (7, 3, 7, '')");
    $stmt = $pdo->query("SELECT * FROM escalation_settings ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch();
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    validateCSRF();
    
    $warningDays = intval($_POST['warning_days']);
    $overdueDays = intval($_POST['overdue_days']);
    $criticalDays = intval($_POST['critical_days']);
    $notificationEmails = trim($_POST['notification_emails']);
    $enableEscalation = isset($_POST['enable_escalation']) ? 1 : 0;
    
    $errors = [];
    
    if ($warningDays < 1 || $warningDays > 30) {
        $errors[] = 'Warnung-Tage muss zwischen 1 und 30 liegen';
    }
    
    if ($overdueDays < 1 || $overdueDays > 14) {
        $errors[] = 'Überfällig-Tage muss zwischen 1 und 14 liegen';
    }
    
    if ($criticalDays < 1 || $criticalDays > 30) {
        $errors[] = 'Kritisch-Tage muss zwischen 1 und 30 liegen';
    }
    
    $emails = array_filter(array_map('trim', explode(',', $notificationEmails)));
    foreach ($emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Ungültige E-Mail: $email";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE escalation_settings 
                SET warning_days = ?, overdue_days = ?, critical_days = ?, 
                    notification_emails = ?, enable_escalation = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $warningDays, $overdueDays, $criticalDays,
                implode(',', $emails), $enableEscalation, $settings['id']
            ]);
            
            logActivity('escalation_settings_updated', 'Eskalations-Einstellungen aktualisiert');
            
            $message = 'Einstellungen erfolgreich gespeichert!';
            $messageType = 'success';
            
            $stmt = $pdo->query("SELECT * FROM escalation_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch();
            
        } catch (Exception $e) {
            $message = 'Fehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}

// Statistiken
$stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_notifications WHERE DATE(sent_at) = CURDATE()");
$todayNotifications = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_notifications WHERE notification_type = 'warning' AND DATE(sent_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$weekWarnings = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_notifications WHERE notification_type = 'overdue' AND DATE(sent_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$weekOverdue = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_notifications WHERE notification_type = 'critical' AND DATE(sent_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$weekCritical = $stmt->fetchColumn();

// Letzte Benachrichtigungen
$stmt = $pdo->prepare("
    SELECT mn.*, m.name as marker_name
    FROM maintenance_notifications mn
    LEFT JOIN markers m ON mn.marker_id = m.id
    ORDER BY mn.sent_at DESC
    LIMIT 20
");
$stmt->execute();
$recentNotifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskalations-Einstellungen</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-exclamation-triangle"></i> Wartungs-Eskalation</h1>
                <div class="header-actions">
                    <a href="settings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>
            
            <!-- Statistiken -->
            <div class="stats-summary">
                <div class="stat-box">
                    <div class="stat-box-value"><?= $todayNotifications ?></div>
                    <div class="stat-box-label">Heute</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-value" style="color: #ffc107;"><?= $weekWarnings ?></div>
                    <div class="stat-box-label">Warnungen (7 Tage)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-value" style="color: #dc3545;"><?= $weekOverdue ?></div>
                    <div class="stat-box-label">Überfällig (7 Tage)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-value" style="color: #8b0000;"><?= $weekCritical ?></div>
                    <div class="stat-box-label">Kritisch (7 Tage)</div>
                </div>
            </div>
            
            <div class="admin-grid">
                <!-- Einstellungen -->
                <div class="admin-section">
                    <h2><i class="fas fa-cog"></i> Einstellungen</h2>
                    
                    <form method="POST" class="marker-form" style="padding: 0;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="save_settings" value="1">
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_escalation" value="1" <?= $settings['enable_escalation'] ? 'checked' : '' ?>>
                                <span class="checkbox-text">
                                    <i class="fas fa-power-off"></i> Eskalations-System aktivieren
                                </span>
                            </label>
                            <small class="form-text text-muted">Wenn deaktiviert, werden keine E-Mails versendet</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="warning_days">Warnung (Tage vor Fälligkeit)</label>
                            <input type="number" id="warning_days" name="warning_days" min="1" max="30" value="<?= $settings['warning_days'] ?>" required>
                            <small>Standard: 7 Tage vor Wartung</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="overdue_days">Erste Eskalation (Tage nach Fälligkeit)</label>
                            <input type="number" id="overdue_days" name="overdue_days" min="1" max="14" value="<?= $settings['overdue_days'] ?>" required>
                            <small>Standard: 3 Tage nach Fälligkeit</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="critical_days">Kritische Eskalation (Tage nach Fälligkeit)</label>
                            <input type="number" id="critical_days" name="critical_days" min="1" max="30" value="<?= $settings['critical_days'] ?>" required>
                            <small>Standard: 7 Tage nach Fälligkeit</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="notification_emails">Benachrichtigungs-E-Mails</label>
                            <textarea id="notification_emails" name="notification_emails" rows="3" required placeholder="admin@example.com, wartung@example.com"><?= e($settings['notification_emails']) ?></textarea>
                            <small>Mehrere E-Mail-Adressen mit Komma trennen</small>
                        </div>
                        
                        <div class="info-box">
                            <h3>Cron-Job Info</h3>
                            <p><strong>Letzter Check:</strong> <?= $settings['last_check'] ? date('d.m.Y H:i', strtotime($settings['last_check'])) : 'Nie' ?></p>
                            <p><strong>Crontab Eintrag:</strong></p>
                            <code style="display: block; background: var(--light-gray); padding: 10px; border-radius: 4px; margin-top: 5px;">
                                0 8 * * * php /pfad/zum/projekt/cron_maintenance_check.php
                            </code>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Einstellungen speichern
                        </button>
                    </form>
                </div>
                
                <!-- Benachrichtigungs-Historie -->
                <div class="admin-section">
                    <h2><i class="fas fa-history"></i> Letzte Benachrichtigungen</h2>
                    
                    <?php if (empty($recentNotifications)): ?>
                        <p class="text-muted">Noch keine Benachrichtigungen versendet</p>
                    <?php else: ?>
                        <div style="max-height: 600px; overflow-y: auto;">
                            <?php foreach ($recentNotifications as $notif): ?>
                                <?php
                                $typeColors = [
                                    'warning' => 'warning',
                                    'overdue' => 'danger',
                                    'critical' => 'danger'
                                ];
                                $typeLabels = [
                                    'warning' => 'Warnung',
                                    'overdue' => 'Überfällig',
                                    'critical' => 'Kritisch'
                                ];
                                ?>
                                <div class="history-item">
                                    <div class="history-date">
                                        <?= date('d.m.Y', strtotime($notif['sent_at'])) ?>
                                    </div>
                                    <div class="history-content">
                                        <p>
                                            <strong><?= e($notif['marker_name'] ?? 'Unbekannt') ?></strong>
                                            <span class="badge badge-<?= $typeColors[$notif['notification_type']] ?>">
                                                <?= $typeLabels[$notif['notification_type']] ?? $notif['notification_type'] ?>
                                            </span>
                                        </p>
                                        <small>
                                            <?= date('H:i', strtotime($notif['sent_at'])) ?> Uhr
                                            <?php if ($notif['days_overdue'] > 0): ?>
                                                - <?= $notif['days_overdue'] ?> Tage überfällig
                                            <?php endif; ?>
                                            <?php if ($notif['email_sent']): ?>
                                                <span class="badge badge-success"><i class="fas fa-check"></i> Versendet</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger"><i class="fas fa-times"></i> Fehler</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>