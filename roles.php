<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('roles_manage');

$message = '';
$messageType = '';

// Rolle löschen
if (isset($_GET['delete'])) {
    $roleId = $_GET['delete'];
    
    // Prüfen ob System-Rolle
    $stmt = $pdo->prepare("SELECT is_system FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    if ($role && !$role['is_system']) {
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $message = 'Rolle gelöscht';
        $messageType = 'success';
    } else {
        $message = 'System-Rollen können nicht gelöscht werden';
        $messageType = 'danger';
    }
}

// Alle Rollen abrufen
$stmt = $pdo->query("
    SELECT r.*, COUNT(DISTINCT u.id) as user_count, COUNT(DISTINCT rp.permission_id) as permission_count
    FROM roles r
    LEFT JOIN users u ON r.id = u.role_id
    LEFT JOIN role_permissions rp ON r.id = rp.role_id
    GROUP BY r.id
    ORDER BY r.is_system DESC, r.role_name
");
$roles = $stmt->fetchAll();

// Alle Berechtigungen nach Kategorie gruppiert
$stmt = $pdo->query("SELECT * FROM permissions ORDER BY category, display_name");
$allPermissions = $stmt->fetchAll();

$permissionsByCategory = [];
foreach ($allPermissions as $perm) {
    $permissionsByCategory[$perm['category']][] = $perm;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rollenverwaltung - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .role-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }
        
        .role-card.system-role {
            border-left-color: var(--info-color);
        }
        
        .role-card h3 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .role-stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            font-size: 14px;
        }
        
        .role-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .permissions-list {
            max-height: 150px;
            overflow-y: auto;
            font-size: 12px;
            background: var(--light-gray);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .permission-category {
            font-weight: bold;
            color: var(--primary-color);
            margin-top: 8px;
        }
        
        .permission-item {
            margin-left: 15px;
            padding: 2px 0;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Rollenverwaltung</h1>
                <div class="header-actions">
                    <a href="create_role.php" class="btn btn-primary">
                        ➕ Neue Rolle erstellen
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>ℹ️ Über Rollen und Berechtigungen</h3>
                <p>Rollen ermöglichen es, Benutzern spezifische Berechtigungen zuzuweisen. Jede Rolle kann beliebige Kombinationen von Berechtigungen haben.</p>
                <p><strong>System-Rollen</strong> (Admin, Benutzer, Betrachter) sind vordefiniert und können nicht gelöscht werden.</p>
            </div>
            
            <h2>Vorhandene Rollen</h2>
            <div class="roles-grid">
                <?php foreach ($roles as $role): ?>
                    <?php
                    // Berechtigungen der Rolle abrufen
                    $stmt = $pdo->prepare("
                        SELECT p.* 
                        FROM permissions p
                        JOIN role_permissions rp ON p.id = rp.permission_id
                        WHERE rp.role_id = ?
                        ORDER BY p.category, p.display_name
                    ");
                    $stmt->execute([$role['id']]);
                    $rolePermissions = $stmt->fetchAll();
                    
                    $permsByCategory = [];
                    foreach ($rolePermissions as $perm) {
                        $permsByCategory[$perm['category']][] = $perm;
                    }
                    ?>
                    
                    <div class="role-card <?= $role['is_system'] ? 'system-role' : '' ?>">
                        <h3>
                            <?= e($role['display_name']) ?>
                            <?php if ($role['is_system']): ?>
                                <span class="badge badge-info">System</span>
                            <?php endif; ?>
                        </h3>
                        
                        <?php if ($role['description']): ?>
                            <p><?= e($role['description']) ?></p>
                        <?php endif; ?>
                        
                        <div class="role-stats">
                            <div class="role-stat">
                                <span>👥</span>
                                <span><?= $role['user_count'] ?> Benutzer</span>
                            </div>
                            <div class="role-stat">
                                <span>🔑</span>
                                <span><?= $role['permission_count'] ?> Berechtigungen</span>
                            </div>
                        </div>
                        
                        <div class="permissions-list">
                            <strong>Berechtigungen:</strong>
                            <?php if (empty($rolePermissions)): ?>
                                <p class="text-muted">Keine Berechtigungen</p>
                            <?php else: ?>
                                <?php foreach ($permsByCategory as $category => $perms): ?>
                                    <div class="permission-category"><?= e($category) ?>:</div>
                                    <?php foreach ($perms as $perm): ?>
                                        <div class="permission-item">✓ <?= e($perm['display_name']) ?></div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-actions">
                            <a href="edit_role.php?id=<?= $role['id'] ?>" class="btn btn-sm btn-secondary">
                                Bearbeiten
                            </a>
                            
                            <?php if (!$role['is_system']): ?>
                                <a href="?delete=<?= $role['id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Rolle wirklich löschen? Benutzer mit dieser Rolle verlieren alle Berechtigungen.')">
                                    Löschen
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="info-box" style="margin-top: 30px;">
                <h3>Verfügbare Berechtigungen</h3>
                <?php foreach ($permissionsByCategory as $category => $perms): ?>
                    <div style="margin: 15px 0;">
                        <h4 style="color: var(--primary-color);"><?= e($category) ?></h4>
                        <ul>
                            <?php foreach ($perms as $perm): ?>
                                <li>
                                    <strong><?= e($perm['display_name']) ?>:</strong> 
                                    <?= e($perm['description']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>