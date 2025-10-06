<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('roles_manage');

$roleId = $_GET['id'] ?? 0;

// Rolle abrufen
$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$roleId]);
$role = $stmt->fetch();

if (!$role) {
    die('Rolle nicht gefunden');
}

$message = '';
$messageType = '';

// Alle Berechtigungen abrufen
$stmt = $pdo->query("SELECT * FROM permissions ORDER BY category, display_name");
$allPermissions = $stmt->fetchAll();

$permissionsByCategory = [];
foreach ($allPermissions as $perm) {
    $permissionsByCategory[$perm['category']][] = $perm;
}

// Aktuelle Berechtigungen der Rolle
$stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$stmt->execute([$roleId]);
$currentPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $displayName = $_POST['display_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $selectedPermissions = $_POST['permissions'] ?? [];
    
    if (empty($displayName)) {
        $message = 'Anzeigename ist erforderlich';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Rolle aktualisieren (nur display_name und description, nicht role_name)
            $stmt = $pdo->prepare("UPDATE roles SET display_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$displayName, $description, $roleId]);
            
            // Alle Berechtigungen entfernen
            $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);
            
            // Neue Berechtigungen hinzufügen
            if (!empty($selectedPermissions)) {
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($selectedPermissions as $permId) {
                    $stmt->execute([$roleId, $permId]);
                }
            }
            
            $pdo->commit();
            
            $message = 'Rolle erfolgreich aktualisiert!';
            $messageType = 'success';
            
            // Rolle neu laden
            $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);
            $role = $stmt->fetch();
            
            // Berechtigungen neu laden
            $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);
            $currentPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Fehler beim Aktualisieren der Rolle: ' . $e->getMessage();
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
    <title>Rolle bearbeiten - <?= e($role['display_name']) ?></title>
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
                <h1>Rolle bearbeiten: <?= e($role['display_name']) ?></h1>
                <?php if ($role['is_system']): ?>
                    <span class="badge badge-info large">System-Rolle</span>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <?php if ($role['is_system']): ?>
                <div class="info-box">
                    <p><strong>Hinweis:</strong> Dies ist eine System-Rolle. Der technische Name kann nicht geändert werden, aber Sie können Berechtigungen anpassen.</p>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="marker-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-section">
                    <h2>Rollen-Informationen</h2>
                    
                    <div class="form-group">
                        <label>Rollenname (technisch)</label>
                        <input type="text" value="<?= e($role['role_name']) ?>" disabled>
                        <small>Der technische Name kann nicht geändert werden</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="display_name">Anzeigename *</label>
                        <input type="text" id="display_name" name="display_name" 
                               value="<?= e($role['display_name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Beschreibung</label>
                        <textarea id="description" name="description" rows="3"><?= e($role['description']) ?></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Berechtigungen</h2>
                    <p>Wählen Sie die Berechtigungen aus, die Benutzer mit dieser Rolle haben sollen:</p>
                    
                    <div class="permissions-section">
                        <?php foreach ($permissionsByCategory as $category => $permissions): ?>
                            <div class="permission-category">
                                <h3>
                                    <?= e($category) ?>
                                    <button type="button" class="btn btn-sm btn-outline select-all-btn" 
                                            onclick="toggleCategory('<?= e($category) ?>')">
                                        Alle umschalten
                                    </button>
                                </h3>
                                
                                <?php foreach ($permissions as $perm): ?>
                                    <label class="permission-checkbox">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="<?= $perm['id'] ?>"
                                               data-category="<?= e($category) ?>"
                                               <?= in_array($perm['id'], $currentPermissions) ? 'checked' : '' ?>>
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
                    <button type="submit" class="btn btn-primary btn-large">Änderungen speichern</button>
                    <a href="roles.php" class="btn btn-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        // Alle Berechtigungen einer Kategorie umschalten
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