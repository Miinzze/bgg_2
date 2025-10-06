<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('roles_manage');

$message = '';
$messageType = '';

// Alle Berechtigungen abrufen
$stmt = $pdo->query("SELECT * FROM permissions ORDER BY category, display_name");
$allPermissions = $stmt->fetchAll();

$permissionsByCategory = [];
foreach ($allPermissions as $perm) {
    $permissionsByCategory[$perm['category']][] = $perm;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleName = $_POST['role_name'] ?? '';
    $displayName = $_POST['display_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $selectedPermissions = $_POST['permissions'] ?? [];
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        $message = 'Sicherheitstoken fehlt';
        $messageType = 'danger';
    } elseif (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } elseif (empty($roleName) || empty($displayName)) {
        $message = 'Bitte alle Pflichtfelder ausfüllen';
        $messageType = 'danger';
    } elseif (!preg_match('/^[a-z_]+$/', $roleName)) {
        $message = 'Rollenname darf nur Kleinbuchstaben und Unterstriche enthalten';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Rolle erstellen
            $stmt = $pdo->prepare("INSERT INTO roles (role_name, display_name, description, is_system) VALUES (?, ?, ?, FALSE)");
            $stmt->execute([$roleName, $displayName, $description]);
            $roleId = $pdo->lastInsertId();
            
            // Berechtigungen zuweisen
            if (!empty($selectedPermissions)) {
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($selectedPermissions as $permId) {
                    $stmt->execute([$roleId, $permId]);
                }
            }
            
            $pdo->commit();
            
            $message = 'Rolle erfolgreich erstellt!';
            $messageType = 'success';
            
            header("refresh:2;url=roles.php");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Fehler beim Erstellen der Rolle: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Rolle erstellen</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .permissions-section {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .permission-category {
            margin: 20px 0;
        }
        
        .permission-category h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .permission-checkbox {
            display: flex;
            align-items: flex-start;
            padding: 10px;
            background: white;
            margin: 8px 0;
            border-radius: 5px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .permission-checkbox:hover {
            border-color: var(--primary-color);
            background: #f8f8f8;
        }
        
        .permission-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            cursor: pointer;
        }
        
        .permission-checkbox input[type="checkbox"]:checked + .permission-info {
            font-weight: bold;
        }
        
        .permission-info {
            flex: 1;
        }
        
        .permission-name {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .permission-desc {
            font-size: 13px;
            color: var(--medium-gray);
        }
        
        .select-all-btn {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Neue Rolle erstellen</h1>
                <a href="roles.php" class="btn btn-secondary">Zurück zur Übersicht</a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="marker-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-section">
                    <h2>Rollen-Informationen</h2>
                    
                    <div class="form-group">
                        <label for="role_name">Rollenname (technisch) *</label>
                        <input type="text" id="role_name" name="role_name" required 
                               pattern="[a-z_]+" 
                               placeholder="z.B. mietmanager">
                        <small>Nur Kleinbuchstaben und Unterstriche, z.B. "mietmanager" oder "lagerverwalter"</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="display_name">Anzeigename *</label>
                        <input type="text" id="display_name" name="display_name" required
                               placeholder="z.B. Mietmanager">
                        <small>Dieser Name wird Benutzern angezeigt</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Beschreibung</label>
                        <textarea id="description" name="description" rows="3"
                                  placeholder="Kurze Beschreibung der Rolle und ihrer Aufgaben"></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Berechtigungen auswählen</h2>
                    <p>Wählen Sie die Berechtigungen aus, die Benutzer mit dieser Rolle haben sollen:</p>
                    
                    <div class="permissions-section">
                        <?php foreach ($permissionsByCategory as $category => $permissions): ?>
                            <div class="permission-category">
                                <h3>
                                    <?= e($category) ?>
                                    <button type="button" class="btn btn-sm btn-outline select-all-btn" 
                                            onclick="toggleCategory('<?= e($category) ?>')">
                                        Alle auswählen
                                    </button>
                                </h3>
                                
                                <?php foreach ($permissions as $perm): ?>
                                    <label class="permission-checkbox">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="<?= $perm['id'] ?>"
                                               data-category="<?= e($category) ?>">
                                        <div class="permission-info">
                                            <div class="permission-name"><?= e($perm['display_name']) ?></div>
                                            <div class="permission-desc"><?= e($perm['description']) ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">Rolle erstellen</button>
                    <a href="roles.php" class="btn btn-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        // Alle Berechtigungen einer Kategorie auswählen/abwählen
        function toggleCategory(category) {
            const checkboxes = document.querySelectorAll(`input[data-category="${category}"]`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
        }
        
        // Checkbox-Labels klickbar machen
        document.querySelectorAll('.permission-checkbox').forEach(label => {
            label.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                }
            });
        });
    </script>

</body>
</html>