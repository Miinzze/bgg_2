<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('users_manage');

$message = '';
$messageType = '';

// Alle Rollen abrufen
$stmt = $pdo->query("SELECT * FROM roles ORDER BY display_name");
$roles = $stmt->fetchAll();

// Neuen Benutzer erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    validateCSRF();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId = $_POST['role_id'] ?? null;
    $receiveMaintenanceEmails = isset($_POST['receive_maintenance_emails']) ? 1 : 0;
    $require2fa = isset($_POST['require_2fa']) ? 1 : 0;
    
    // Validierung
    if (!validateUsername($username)) {
        $message = 'Benutzername muss 3-50 Zeichen lang sein und darf nur Buchstaben, Zahlen und Unterstriche enthalten';
        $messageType = 'danger';
    } elseif (!validateEmail($email)) {
        $message = 'Gültige E-Mail-Adresse erforderlich';
        $messageType = 'danger';
    } elseif (!empty($phone) && !preg_match('/^[\d\s\+\-\/\(\)]+$/', $phone)) {
        $message = 'Ungültiges Telefonnummer-Format';
        $messageType = 'danger';
    } elseif (!validateInteger($roleId, 1)) {
        $message = 'Gültige Rolle erforderlich';
        $messageType = 'danger';
    } else {
        $pwCheck = validatePasswordStrength($password);
        if (!$pwCheck['valid']) {
            $message = $pwCheck['message'];
            $messageType = 'danger';
        } else {
            // Prüfen ob Benutzername oder E-Mail bereits existiert
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $message = 'Benutzername oder E-Mail bereits vergeben';
                $messageType = 'danger';
            } else {
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Rolle-String aus rolle_id ermitteln
                    $stmt = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
                    $stmt->execute([$roleId]);
                    $roleName = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, first_name, last_name, phone, password, role, role_id, receive_maintenance_emails, require_2fa) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $username, 
                        $email, 
                        $firstName, 
                        $lastName, 
                        $phone, 
                        $hashedPassword, 
                        $roleName, 
                        intval($roleId), 
                        $receiveMaintenanceEmails, 
                        $require2fa
                    ]);
                    
                    logActivity('user_created', "Benutzer '{$username}' erstellt");
                    
                    $message = 'Benutzer erfolgreich erstellt!';
                    $messageType = 'success';
                    
                    // Benutzer neu laden
                    $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
                    
                } catch (PDOException $e) {
                    $message = 'Fehler beim Erstellen: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Benutzer löschen
if (isset($_GET['delete'])) {
    $userId = $_GET['delete'];
    if ($userId != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $message = 'Benutzer gelöscht';
        $messageType = 'success';
    } else {
        $message = 'Sie können sich nicht selbst löschen';
        $messageType = 'danger';
    }
}

// Alle Benutzer abrufen mit Rollennamen
$stmt = $pdo->query("
    SELECT u.*, r.display_name as role_display_name, r.role_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzerverwaltung - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Benutzerverwaltung</h1>
                <a href="roles.php" class="btn btn-secondary">Rollenverwaltung</a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <div class="admin-grid">
                <div class="admin-section">
                    <h2>Neuen Benutzer erstellen</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Benutzername *</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">E-Mail *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">Vorname</label>
                                <input type="text" id="first_name" name="first_name" placeholder="Max">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Nachname</label>
                                <input type="text" id="last_name" name="last_name" placeholder="Mustermann">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefonnummer</label>
                            <input type="tel" id="phone" name="phone" placeholder="+49 123 456789">
                            <small>Optional - für Benachrichtigungen</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Passwort *</label>
                                <input type="password" id="password" name="password" required>
                                <small>Mindestens 8 Zeichen, 1 Großbuchstabe, 1 Kleinbuchstabe, 1 Zahl</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="role_id">Rolle *</label>
                                <select id="role_id" name="role_id" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['id'] ?>"><?= e($role['display_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" name="receive_maintenance_emails" checked>
                                <span>Wartungs-E-Mails empfangen</span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" name="require_2fa" id="require_2fa">
                                <span>
                                    <strong>Zwei-Faktor-Authentifizierung (2FA) verpflichtend</strong><br>
                                    <small>Benutzer muss 2FA aktivieren und bei jedem Login verwenden</small>
                                </span>
                            </label>
                        </div>
                        
                        <button type="submit" name="create_user" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Benutzer erstellen
                        </button>
                    </form>
                </div>
                
                <div class="admin-section">
                    <h2>Vorhandene Benutzer</h2>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Benutzername</th>
                                <th>Name</th>
                                <th>E-Mail</th>
                                <th>Telefon</th>
                                <th>Rolle</th>
                                <th>2FA</th>
                                <th>Letzter Login</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= e($user['id']) ?></td>
                                <td><strong><?= e($user['username']) ?></strong></td>
                                <td>
                                    <?php if ($user['first_name'] || $user['last_name']): ?>
                                        <?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($user['email']) ?></td>
                                <td>
                                    <?php if ($user['phone']): ?>
                                        <i class="fas fa-phone" style="color: #28a745;"></i>
                                        <?= e($user['phone']) ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'user' ? 'primary' : 'secondary') ?>">
                                        <?= e($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['require_2fa']): ?>
                                        <span class="badge badge-warning" title="2FA Pflicht">
                                            <i class="fas fa-exclamation-circle"></i> Pflicht
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($user['has_2fa_enabled']): ?>
                                        <span class="badge badge-success" title="2FA Aktiv">
                                            <i class="fas fa-check-circle"></i> Aktiv
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" title="2FA Inaktiv">
                                            <i class="fas fa-times-circle"></i> Inaktiv
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : '<span style="color: #6c757d;">Noch nie</span>' ?>
                                </td>
                                <td>
                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>Bearbeiten
                                    </a>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger" 
                                        onclick="return confirm('Benutzer <?= e($user['username']) ?> wirklich löschen?')"
                                        title="Löschen">
                                            <i class="fas fa-trash"></i>Löschen
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="info-box">
                <h3>Über Rollen</h3>
                <p>Jeder Benutzer benötigt eine Rolle. Rollen definieren, welche Aktionen ein Benutzer durchführen kann.</p>
                <p><a href="roles.php" class="btn btn-sm btn-primary">Zur Rollenverwaltung</a></p>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>