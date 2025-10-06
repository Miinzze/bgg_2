<?php
session_start();
require_once '../config.php';
require_once 'functions.php';

requireBugAdmin();

$message = '';
$messageType = '';

// Neuen Benutzer erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    $errors = [];
    
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Ungültige E-Mail-Adresse';
    }
    
    if (strlen($password) < 8) {
        $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein';
    }
    
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwörter stimmen nicht überein';
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bug_admin_users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Benutzername bereits vergeben';
    }
    
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO bug_admin_users (username, password, email, full_name)
                VALUES (?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $hashedPassword, $email, $fullName])) {
                $message = 'Benutzer erfolgreich erstellt';
                $messageType = 'success';
            } else {
                $message = 'Fehler beim Erstellen des Benutzers';
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Datenbankfehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}

// Benutzer bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Ungültige E-Mail-Adresse';
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bug_admin_users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $userId]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Benutzername bereits vergeben';
    }
    
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 8) {
            $errors[] = 'Neues Passwort muss mindestens 8 Zeichen lang sein';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Passwörter stimmen nicht überein';
        }
    }
    
    if (empty($errors)) {
        try {
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE bug_admin_users 
                    SET username = ?, email = ?, full_name = ?, password = ?
                    WHERE id = ?
                ");
                $result = $stmt->execute([$username, $email, $fullName, $hashedPassword, $userId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE bug_admin_users 
                    SET username = ?, email = ?, full_name = ?
                    WHERE id = ?
                ");
                $result = $stmt->execute([$username, $email, $fullName, $userId]);
            }
            
            if ($result) {
                if ($userId == $_SESSION['bug_admin_id']) {
                    $_SESSION['bug_admin_username'] = $username;
                    $_SESSION['bug_admin_email'] = $email;
                }
                
                $message = 'Benutzer erfolgreich aktualisiert';
                $messageType = 'success';
            } else {
                $message = 'Fehler beim Aktualisieren des Benutzers';
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Datenbankfehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}

// Benutzer deaktivieren/aktivieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    $isActive = intval($_POST['is_active'] ?? 0);
    
    if ($userId == $_SESSION['bug_admin_id']) {
        $message = 'Sie können sich nicht selbst deaktivieren';
        $messageType = 'warning';
    } else {
        $stmt = $pdo->prepare("UPDATE bug_admin_users SET is_active = ? WHERE id = ?");
        if ($stmt->execute([$isActive ? 0 : 1, $userId])) {
            $message = 'Benutzerstatus geändert';
            $messageType = 'success';
        }
    }
}

// Benutzer löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($userId == $_SESSION['bug_admin_id']) {
        $message = 'Sie können sich nicht selbst löschen';
        $messageType = 'warning';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bug_reports WHERE assigned_to = ?");
        $stmt->execute([$userId]);
        $assignedBugs = $stmt->fetchColumn();
        
        if ($assignedBugs > 0) {
            $message = "Benutzer kann nicht gelöscht werden - hat noch $assignedBugs zugewiesene Bugs";
            $messageType = 'warning';
        } else {
            $stmt = $pdo->prepare("DELETE FROM bug_admin_users WHERE id = ?");
            if ($stmt->execute([$userId])) {
                $message = 'Benutzer erfolgreich gelöscht';
                $messageType = 'success';
            }
        }
    }
}

$stmt = $pdo->query("SELECT * FROM bug_admin_users ORDER BY username");
$users = $stmt->fetchAll();

$pageTitle = 'Benutzerverwaltung';
include 'header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>Benutzerverwaltung</h1>
        <h2>Bug-Admin-Benutzer verwalten</h2>
    </div>
    <div class="header-actions">
        <button onclick="openCreateModal()" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Neuer Benutzer
        </button>
    </div>
</div>

<div class="admin-section">
    <h2>Benutzer-Liste</h2>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Benutzername</th>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Status</th>
                    <th>Erstellt am</th>
                    <th>Letzter Login</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                            <?php if ($user['id'] == $_SESSION['bug_admin_id']): ?>
                                <span class="badge badge-info">Sie</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($user['full_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge badge-success">Aktiv</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inaktiv</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Nie' ?>
                        </td>
                        <td>
                            <button onclick='openEditModal(<?= json_encode([
                                "id" => $user["id"],
                                "username" => $user["username"],
                                "full_name" => $user["full_name"] ?? "",
                                "email" => $user["email"]
                            ]) ?>)' 
                                    class="btn btn-sm btn-primary" 
                                    title="Bearbeiten">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <?php if ($user['id'] != $_SESSION['bug_admin_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $user['is_active'] ?>">
                                    <button type="submit" 
                                            name="toggle_active" 
                                            class="btn btn-sm btn-<?= $user['is_active'] ? 'warning' : 'success' ?>"
                                            title="<?= $user['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                        <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?>"></i>
                                    </button>
                                </form>
                                
                                <form method="POST" 
                                      style="display: inline;" 
                                      onsubmit="return confirm('Benutzer wirklich löschen?')">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" 
                                            name="delete_user" 
                                            class="btn btn-sm btn-danger"
                                            title="Löschen">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Neuer Benutzer -->
<div id="createUserModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('createUserModal')">&times;</span>
        <h2><i class="fas fa-user-plus"></i> Neuen Benutzer erstellen</h2>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Benutzername *</label>
                    <input type="text" id="username" name="username" required minlength="3" maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="full_name">Vollständiger Name</label>
                    <input type="text" id="full_name" name="full_name" maxlength="100">
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">E-Mail *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Passwort *</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <small>Mindestens 8 Zeichen</small>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Passwort bestätigen *</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="create_user" class="btn btn-primary">
                    <i class="fas fa-save"></i> Benutzer erstellen
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Benutzer bearbeiten -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editUserModal')">&times;</span>
        <h2><i class="fas fa-user-edit"></i> Benutzer bearbeiten</h2>
        
        <form method="POST">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_username">Benutzername *</label>
                    <input type="text" id="edit_username" name="username" required minlength="3" maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="edit_full_name">Vollständiger Name</label>
                    <input type="text" id="edit_full_name" name="full_name" maxlength="100">
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit_email">E-Mail *</label>
                <input type="email" id="edit_email" name="email" required>
            </div>
            
            <hr style="margin: 20px 0; border: none; border-top: 2px solid #dee2e6;">
            
            <div class="info-box" style="background: #d1ecf1; border-left-color: #17a2b8;">
                <h3 style="margin-top: 0; color: #0c5460;">
                    <i class="fas fa-info-circle"></i> Passwort ändern (optional)
                </h3>
                <p style="margin: 0; color: #0c5460;">Lassen Sie die Felder leer, um das Passwort nicht zu ändern.</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_new_password">Neues Passwort</label>
                    <input type="password" id="edit_new_password" name="new_password" minlength="8">
                    <small>Mindestens 8 Zeichen (leer lassen um nicht zu ändern)</small>
                </div>
                
                <div class="form-group">
                    <label for="edit_confirm_password">Passwort bestätigen</label>
                    <input type="password" id="edit_confirm_password" name="confirm_password" minlength="8">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="edit_user" class="btn btn-primary">
                    <i class="fas fa-save"></i> Änderungen speichern
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.6);
}

.modal.show {
    display: block !important;
}
</style>

<script>
function openCreateModal() {
    document.getElementById('createUserModal').classList.add('show');
}

function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_new_password').value = '';
    document.getElementById('edit_confirm_password').value = '';
    
    document.getElementById('editUserModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Modal schließen bei Klick außerhalb
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}

// ESC-Taste zum Schließen
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
        });
    }
});
</script>

<?php include 'footer.php'; ?>