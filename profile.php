<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

trackUsage('profile_view');

$message = '';
$messageType = '';

// Benutzer-Daten laden
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Profil aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    validateCSRF();
    
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (!validateEmail($email)) {
        $message = 'Gültige E-Mail-Adresse erforderlich';
        $messageType = 'danger';
    } elseif (!empty($phone) && !preg_match('/^[\d\s\+\-\/\(\)]+$/', $phone)) {
        $message = 'Ungültiges Telefonnummer-Format';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $email, $phone, $_SESSION['user_id']]);
            
            logActivity('profile_updated', 'Profil aktualisiert');
            
            $message = 'Profil erfolgreich aktualisiert!';
            $messageType = 'success';
            
            // Neu laden
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
        } catch (Exception $e) {
            $message = 'Fehler: ' . e($e->getMessage());
            $messageType = 'danger';
        }
    }
}

// Passwort ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    validateCSRF();
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!password_verify($currentPassword, $user['password'])) {
        $message = 'Aktuelles Passwort ist falsch';
        $messageType = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Neue Passwörter stimmen nicht überein';
        $messageType = 'danger';
    } else {
        $pwCheck = validatePasswordStrength($newPassword);
        if (!$pwCheck['valid']) {
            $message = $pwCheck['message'];
            $messageType = 'danger';
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                logActivity('password_changed', 'Passwort geändert');
                
                $message = 'Passwort erfolgreich geändert!';
                $messageType = 'success';
                
            } catch (Exception $e) {
                $message = 'Fehler: ' . e($e->getMessage());
                $messageType = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mein Profil - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-user-circle"></i> Mein Profil</h1>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <div class="admin-grid">
                <!-- Profil-Informationen -->
                <div class="admin-section">
                    <h2>Profil-Informationen</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-group">
                            <label>Benutzername</label>
                            <input type="text" value="<?= e($user['username']) ?>" disabled>
                            <small>Benutzername kann nicht geändert werden</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">Vorname</label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?= e($user['first_name']) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Nachname</label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?= e($user['last_name']) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-Mail *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= e($user['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefonnummer</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?= e($user['phone']) ?>"
                                   placeholder="+49 123 456789">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Profil speichern
                        </button>
                    </form>
                </div>
                
                <!-- Passwort ändern -->
                <div class="admin-section">
                    <h2>Passwort ändern</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-group">
                            <label for="current_password">Aktuelles Passwort *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Neues Passwort *</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small>Mindestens 8 Zeichen, 1 Großbuchstabe, 1 Kleinbuchstabe, 1 Zahl</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Neues Passwort wiederholen *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-success">
                            <i class="fas fa-key"></i> Passwort ändern
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Sicherheit -->
            <div class="info-card">
                <h2><i class="fas fa-shield-alt"></i> Sicherheit</h2>
                
                <div style="margin: 20px 0;">
                    <p><strong>Zwei-Faktor-Authentifizierung (2FA):</strong></p>
                    <?php
                    $stmt = $pdo->prepare("SELECT enabled FROM user_2fa WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $has2FA = $stmt->fetchColumn();
                    ?>
                    
                    <?php if ($has2FA): ?>
                        <span class="badge badge-success">Aktiviert</span>
                        <a href="setup_2fa.php" class="btn btn-secondary" style="margin-left: 10px;">
                            <i class="fas fa-cog"></i> 2FA Verwalten
                        </a>
                    <?php else: ?>
                        <span class="badge badge-secondary">Nicht aktiviert</span>
                        <a href="setup_2fa.php" class="btn btn-primary" style="margin-left: 10px;">
                            <i class="fas fa-shield-alt"></i> 2FA Einrichten
                        </a>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                    <p><strong>Letzter Login:</strong> 
                        <?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) . ' Uhr' : 'Noch nie' ?>
                    </p>
                    <p><strong>Konto erstellt:</strong> 
                        <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?> Uhr
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>