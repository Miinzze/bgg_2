<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

trackUsage('setup_2fa');

$message = '';
$messageType = '';

// Prüfen ob Benutzer bereits 2FA hat
$stmt = $pdo->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$existing2FA = $stmt->fetch();

// Secret generieren wenn noch nicht vorhanden
if (!$existing2FA) {
    $secret = generate2FASecret();
    
    // Temporär in Session speichern
    $_SESSION['temp_2fa_secret'] = $secret;
} else {
    $secret = $existing2FA['secret'];
}

// QR-Code URL generieren
$username = $_SESSION['username'];
$issuer = 'RFID_Marker_System';
$qrUrl = "otpauth://totp/{$issuer}:{$username}?secret={$secret}&issuer={$issuer}";
$qrCodeImage = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrUrl);

// 2FA aktivieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_2fa'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $code = trim($_POST['code'] ?? '');
        $tempSecret = $_SESSION['temp_2fa_secret'] ?? $secret;
        
        if (verify2FACode($tempSecret, $code)) {
            // Backup-Codes generieren
            $backupCodes = [];
            for ($i = 0; $i < 10; $i++) {
                $backupCodes[] = strtoupper(bin2hex(random_bytes(4)));
            }
            
            try {
                // 2FA in Datenbank speichern
                $stmt = $pdo->prepare("INSERT INTO user_2fa (user_id, secret, enabled, backup_codes) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE secret = ?, enabled = 1, backup_codes = ?");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $tempSecret,
                    json_encode($backupCodes),
                    $tempSecret,
                    json_encode($backupCodes)
                ]);
                
                // User-Status aktualisieren
                $stmt = $pdo->prepare("UPDATE users SET has_2fa_enabled = 1 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                logActivity('2fa_enabled', '2FA aktiviert');
                
                $_SESSION['backup_codes'] = $backupCodes;
                unset($_SESSION['temp_2fa_secret']);
                
                header('Location: setup_2fa.php?success=1');
                exit;
                
            } catch (Exception $e) {
                $message = 'Fehler beim Aktivieren: ' . e($e->getMessage());
                $messageType = 'danger';
            }
        } else {
            $message = 'Ungültiger Code. Bitte versuchen Sie es erneut.';
            $messageType = 'danger';
        }
    }
}

// 2FA deaktivieren
if (isset($_GET['disable'])) {
    $stmt = $pdo->prepare("DELETE FROM user_2fa WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    $stmt = $pdo->prepare("UPDATE users SET has_2fa_enabled = 0 WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    logActivity('2fa_disabled', '2FA deaktiviert');
    
    header('Location: setup_2fa.php?disabled=1');
    exit;
}

// Benutzer-Info laden
$stmt = $pdo->prepare("SELECT require_2fa FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userInfo = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Zwei-Faktor-Authentifizierung</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .qr-container {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        .backup-codes {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        .backup-code {
            padding: 15px;
            background: #fff;
            border: 2px dashed #007bff;
            border-radius: 5px;
            font-family: monospace;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-shield-alt"></i> Zwei-Faktor-Authentifizierung (2FA)</h1>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success']) && isset($_SESSION['backup_codes'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <strong>2FA erfolgreich aktiviert!</strong>
                </div>
                
                <div class="info-card">
                    <h2><i class="fas fa-key"></i> Ihre Backup-Codes</h2>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Wichtig:</strong> Speichern Sie diese Codes an einem sicheren Ort! 
                        Sie können diese Codes verwenden, wenn Sie keinen Zugriff auf Ihre Authenticator-App haben.
                        <br>Jeder Code kann nur einmal verwendet werden.
                    </div>
                    
                    <div class="backup-codes">
                        <?php foreach ($_SESSION['backup_codes'] as $code): ?>
                            <div class="backup-code"><?= $code ?></div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button onclick="printBackupCodes()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Backup-Codes drucken
                    </button>
                    
                    <button onclick="copyBackupCodes()" class="btn btn-secondary">
                        <i class="fas fa-copy"></i> In Zwischenablage kopieren
                    </button>
                </div>
                
                <script>
                function printBackupCodes() {
                    window.print();
                }
                
                function copyBackupCodes() {
                    const codes = <?= json_encode($_SESSION['backup_codes']) ?>;
                    const text = 'RFID Marker System - 2FA Backup Codes\n\n' + codes.join('\n');
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Backup-Codes in Zwischenablage kopiert!');
                    });
                }
                </script>
                
                <?php unset($_SESSION['backup_codes']); ?>
                
            <?php elseif (isset($_GET['disabled'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 2FA wurde deaktiviert
                </div>
                
            <?php endif; ?>
            
            <?php if (!$existing2FA || !$existing2FA['enabled']): ?>
                <!-- 2FA Einrichtung -->
                <div class="info-card">
                    <h2>2FA aktivieren</h2>
                    
                    <?php if ($userInfo['require_2fa']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>2FA ist für Ihr Konto verpflichtend!</strong> 
                            Bitte richten Sie 2FA ein, um weiterhin Zugriff zu haben.
                        </div>
                    <?php endif; ?>
                    
                    <p>Scannen Sie den QR-Code mit einer Authenticator-App wie Google Authenticator, Microsoft Authenticator oder Authy:</p>
                    
                    <div class="qr-container">
                        <img src="<?= $qrCodeImage ?>" alt="2FA QR Code">
                        <p style="margin-top: 15px;">
                            <strong>Oder geben Sie diesen Code manuell ein:</strong><br>
                            <code style="font-size: 18px; padding: 10px; background: white; display: inline-block; margin-top: 10px;">
                                <?= $secret ?>
                            </code>
                        </p>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-group">
                            <label for="code">Bestätigungscode aus Ihrer Authenticator-App *</label>
                            <input type="text" id="code" name="code" required 
                                   pattern="[0-9]{6}" maxlength="6" 
                                   placeholder="000000"
                                   style="font-size: 24px; text-align: center; letter-spacing: 5px;">
                            <small>Geben Sie den 6-stelligen Code aus Ihrer App ein</small>
                        </div>
                        
                        <button type="submit" name="activate_2fa" class="btn btn-success btn-large">
                            <i class="fas fa-check"></i> 2FA aktivieren
                        </button>
                    </form>
                </div>
                
            <?php else: ?>
                <!-- 2FA ist bereits aktiviert -->
                <div class="info-card">
                    <h2>2FA Status</h2>
                    
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        <strong>2FA ist aktiv!</strong> Ihr Konto ist durch Zwei-Faktor-Authentifizierung geschützt.
                    </div>
                    
                    <p><strong>Aktiviert seit:</strong> <?= date('d.m.Y H:i', strtotime($existing2FA['created_at'])) ?> Uhr</p>
                    
                    <?php if ($existing2FA['last_used']): ?>
                        <p><strong>Zuletzt verwendet:</strong> <?= date('d.m.Y H:i', strtotime($existing2FA['last_used'])) ?> Uhr</p>
                    <?php endif; ?>
                    
                    <?php if (!$userInfo['require_2fa']): ?>
                        <div style="margin-top: 30px;">
                            <h3>2FA deaktivieren</h3>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Wenn Sie 2FA deaktivieren, ist Ihr Konto weniger sicher geschützt.
                            </div>
                            
                            <a href="?disable=1" class="btn btn-danger"
                               onclick="return confirm('2FA wirklich deaktivieren? Ihr Konto wird weniger geschützt sein!')">
                                <i class="fas fa-times"></i> 2FA deaktivieren
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" style="margin-top: 20px;">
                            <i class="fas fa-lock"></i> 
                            2FA ist für Ihr Konto verpflichtend und kann nicht deaktiviert werden.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-card">
                <h2><i class="fas fa-question-circle"></i> Hilfe</h2>
                
                <h3>Was ist 2FA?</h3>
                <p>Zwei-Faktor-Authentifizierung (2FA) fügt eine zusätzliche Sicherheitsebene zu Ihrem Konto hinzu. 
                Nach der Eingabe Ihres Passworts müssen Sie einen zeitbasierten 6-stelligen Code aus Ihrer Authenticator-App eingeben.</p>
                
                <h3>Welche Apps kann ich nutzen?</h3>
                <ul>
                    <li><strong>Google Authenticator</strong> (iOS/Android)</li>
                    <li><strong>Microsoft Authenticator</strong> (iOS/Android)</li>
                    <li><strong>Authy</strong> (iOS/Android/Desktop)</li>
                    <li><strong>1Password</strong> (mit TOTP-Unterstützung)</li>
                </ul>
                
                <h3>Was wenn ich keinen Zugriff auf meine App habe?</h3>
                <p>Verwenden Sie einen Ihrer Backup-Codes oder kontaktieren Sie einen Administrator, der 2FA für Ihr Konto zurücksetzen kann.</p>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>