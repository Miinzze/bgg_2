<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

trackUsage('edit_user');

$userId = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die('Benutzer nicht gefunden');
}

$message = '';
$messageType = '';

// Benutzer aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $roleId = $_POST['role_id'] ?? null;
        $receiveMaintenanceEmails = isset($_POST['receive_maintenance_emails']) ? 1 : 0;
        $require2fa = isset($_POST['require_2fa']) ? 1 : 0;
        $newPassword = $_POST['new_password'] ?? '';
        
        if (!validateEmail($email)) {
            $message = 'Gültige E-Mail-Adresse erforderlich';
            $messageType = 'danger';
        } elseif (!empty($phone) && !preg_match('/^[\d\s\+\-\/\(\)]+$/', $phone)) {
            $message = 'Ungültiges Telefonnummer-Format';
            $messageType = 'danger';
        } elseif (!validateInteger($roleId, 1)) {
            $message = 'Gültige Rolle erforderlich';
            $messageType = 'danger';
        } else {
            try {
                if (!empty($newPassword)) {
                    $pwCheck = validatePasswordStrength($newPassword);
                    if (!$pwCheck['valid']) {
                        throw new Exception($pwCheck['message']);
                    }
                    
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users SET 
                            email = ?, 
                            first_name = ?, 
                            last_name = ?, 
                            phone = ?, 
                            role_id = ?, 
                            receive_maintenance_emails = ?, 
                            require_2fa = ?, 
                            password = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $email, 
                        $firstName, 
                        $lastName, 
                        $phone, 
                        intval($roleId), 
                        $receiveMaintenanceEmails, 
                        $require2fa, 
                        $hashedPassword, 
                        $userId
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET 
                            email = ?, 
                            first_name = ?, 
                            last_name = ?, 
                            phone = ?, 
                            role_id = ?, 
                            receive_maintenance_emails = ?, 
                            require_2fa = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $email, 
                        $firstName, 
                        $lastName, 
                        $phone, 
                        intval($roleId), 
                        $receiveMaintenanceEmails, 
                        $require2fa, 
                        $userId
                    ]);
                }
                
                // Rolle-String aktualisieren
                $stmt = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
                $stmt->execute([$roleId]);
                $roleName = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$roleName, $userId]);
                
                logActivity('user_updated', "Benutzer '{$user['username']}' aktualisiert");
                
                $message = 'Benutzer erfolgreich aktualisiert!';
                $messageType = 'success';
                
                // Benutzer neu laden
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                $message = 'Fehler: ' . e($e->getMessage());
                $messageType = 'danger';
            }
        }
    }
}

// 2FA zurücksetzen
if (isset($_GET['reset_2fa'])) {
    $stmt = $pdo->prepare("DELETE FROM user_2fa WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $stmt = $pdo->prepare("UPDATE users SET has_2fa_enabled = 0 WHERE id = ?");
    $stmt->execute([$userId]);
    
    logActivity('2fa_reset', "2FA für Benutzer '{$user['username']}' zurückgesetzt");
    
    header("Location: edit_user.php?id=$userId&reset_success=1");
    exit;
}

// Rollen laden
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Benutzer bearbeiten</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-user-edit"></i> Benutzer bearbeiten</h1>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['reset_success'])): ?>
                <div class="alert alert-success">2FA erfolgreich zurückgesetzt!</div>
            <?php endif; ?>
            
            <div class="admin-grid">
                <div class="admin-section">
                    <h2>Benutzerdaten</h2>
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
                                    value="<?= e($user['first_name']) ?>"
                                    placeholder="Max">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Nachname</label>
                                <input type="text" id="last_name" name="last_name" 
                                    value="<?= e($user['last_name']) ?>"
                                    placeholder="Mustermann">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-Mail *</label>
                            <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefonnummer</label>
                            <input type="tel" id="phone" name="phone" 
                                value="<?= e($user['phone']) ?>"
                                placeholder="+49 123 456789">
                            <small>Optional - für Benachrichtigungen</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="role_id">Rolle *</label>
                            <select id="role_id" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= $user['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                        <?= e($role['display_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" name="receive_maintenance_emails" 
                                    <?= $user['receive_maintenance_emails'] ? 'checked' : '' ?>>
                                <span>Wartungs-E-Mails empfangen</span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" name="require_2fa" 
                                    <?= $user['require_2fa'] ? 'checked' : '' ?>>
                                <span>
                                    <strong>Zwei-Faktor-Authentifizierung (2FA) verpflichtend</strong><br>
                                    <small>Benutzer muss 2FA aktivieren und bei jedem Login verwenden</small>
                                </span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Neues Passwort</label>
                            <input type="password" id="new_password" name="new_password">
                            <small>Leer lassen, um Passwort nicht zu ändern. Mindestens 8 Zeichen, 1 Großbuchstabe, 1 Kleinbuchstabe, 1 Zahl</small>
                        </div>
                        
                        <button type="submit" name="update_user" class="btn btn-success">
                            <i class="fas fa-save"></i> Änderungen speichern
                        </button>
                    </form>
                </div>
                
                <div class="admin-section">
                    <h2>2FA Status</h2>
                    
                    <div style="margin-bottom: 20px;">
                        <p><strong>2FA Pflicht:</strong> 
                            <?php if ($user['require_2fa']): ?>
                                <span class="badge badge-warning">Ja</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Nein</span>
                            <?php endif; ?>
                        </p>
                        
                        <p><strong>2FA Aktiviert:</strong> 
                            <?php if ($user['has_2fa_enabled']): ?>
                                <span class="badge badge-success">Ja</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Nein</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if ($user['has_2fa_enabled']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Wenn Sie 2FA zurücksetzen, muss der Benutzer beim nächsten Login 2FA neu einrichten.
                        </div>
                        
                        <a href="?id=<?= $userId ?>&reset_2fa=1" class="btn btn-danger"
                           onclick="return confirm('2FA wirklich zurücksetzen? Der Benutzer muss es neu einrichten!')">
                            <i class="fas fa-undo"></i> 2FA zurücksetzen
                        </a>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Benutzer hat 2FA noch nicht eingerichtet.
                            <?php if ($user['require_2fa']): ?>
                                <br>Da 2FA verpflichtend ist, wird der Benutzer beim nächsten Login zur Einrichtung aufgefordert.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 style="margin-top: 30px;">Letzte Aktivität</h3>
                    <p><strong>Letzter Login:</strong> 
                        <?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) . ' Uhr' : 'Noch nie' ?>
                    </p>
                    <p><strong>Erstellt am:</strong> 
                        <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?> Uhr
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>