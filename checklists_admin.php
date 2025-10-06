<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('checklists_manage');

trackUsage('checklists_admin_view');

$message = '';
$messageType = '';

// Template erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_template'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $items = array_filter($_POST['items'] ?? [], function($item) {
            return !empty(trim($item));
        });
        
        if (empty($name)) {
            $message = 'Name ist erforderlich';
            $messageType = 'danger';
        } elseif (empty($items)) {
            $message = 'Mindestens ein Prüfpunkt erforderlich';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare("INSERT INTO checklist_templates (name, description, category, items, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $category, json_encode(array_values($items)), $_SESSION['user_id']]);
            
            logActivity('checklist_template_created', "Checklisten-Template '{$name}' erstellt");
            
            $message = 'Template erfolgreich erstellt!';
            $messageType = 'success';
        }
    }
}

// Template löschen
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("SELECT name FROM checklist_templates WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $templateName = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("DELETE FROM checklist_templates WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    
    logActivity('checklist_template_deleted', "Template '{$templateName}' gelöscht");
    
    $message = 'Template gelöscht';
    $messageType = 'success';
}

// Alle Templates laden
$stmt = $pdo->query("SELECT ct.*, u.username as creator FROM checklist_templates ct LEFT JOIN users u ON ct.created_by = u.id ORDER BY ct.category, ct.name");
$templates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Checklisten verwalten</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .checklist-items {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .checklist-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .checklist-item input {
            flex: 1;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-tasks"></i> Wartungs-Checklisten</h1>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <div class="admin-grid">
                <div class="admin-section">
                    <h2>Neues Template erstellen</h2>
                    <form method="POST" id="templateForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-group">
                            <label for="name">Template-Name *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Kategorie</label>
                            <input type="text" id="category" name="category" placeholder="z.B. Bagger, Stapler">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Beschreibung</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Prüfpunkte *</label>
                            <div class="checklist-items" id="itemsContainer">
                                <div class="checklist-item">
                                    <input type="text" name="items[]" placeholder="z.B. Ölstand prüfen" required>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addItem()">
                                <i class="fas fa-plus"></i> Prüfpunkt hinzufügen
                            </button>
                        </div>
                        
                        <button type="submit" name="create_template" class="btn btn-primary">
                            <i class="fas fa-save"></i> Template erstellen
                        </button>
                    </form>
                </div>
                
                <div class="admin-section">
                    <h2>Vorhandene Templates (<?= count($templates) ?>)</h2>
                    <?php if (empty($templates)): ?>
                        <p style="color: #6c757d;">Noch keine Templates vorhanden</p>
                    <?php else: ?>
                        <?php
                        $groupedTemplates = [];
                        foreach ($templates as $template) {
                            $cat = $template['category'] ?: 'Allgemein';
                            $groupedTemplates[$cat][] = $template;
                        }
                        ?>
                        
                        <?php foreach ($groupedTemplates as $category => $catTemplates): ?>
                            <h3 style="margin-top: 20px; color: #e63312;"><?= e($category) ?></h3>
                            <?php foreach ($catTemplates as $template): ?>
                                <div style="background: white; padding: 15px; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid #007bff;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <h4 style="margin: 0 0 5px 0;"><?= e($template['name']) ?></h4>
                                            <?php if ($template['description']): ?>
                                                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                                    <?= e($template['description']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <small style="color: #6c757d;">
                                                <?php
                                                $items = json_decode($template['items'], true);
                                                echo count($items) . ' Prüfpunkte';
                                                ?>
                                                | Erstellt: <?= date('d.m.Y', strtotime($template['created_at'])) ?>
                                                <?php if ($template['creator']): ?>
                                                    von <?= e($template['creator']) ?>
                                                <?php endif; ?>
                                            </small>
                                            
                                            <details style="margin-top: 10px;">
                                                <summary style="cursor: pointer; color: #007bff;">Prüfpunkte anzeigen</summary>
                                                <ol style="margin: 10px 0; padding-left: 20px;">
                                                    <?php foreach ($items as $item): ?>
                                                        <li><?= e($item) ?></li>
                                                    <?php endforeach; ?>
                                                </ol>
                                            </details>
                                        </div>
                                        <div style="display: flex; gap: 5px;">
                                            <a href="?delete=<?= $template['id'] ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Template wirklich löschen?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    function addItem() {
        const container = document.getElementById('itemsContainer');
        const div = document.createElement('div');
        div.className = 'checklist-item';
        div.innerHTML = `
            <input type="text" name="items[]" placeholder="Prüfpunkt beschreiben" required>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(div);
    }
    
    function removeItem(button) {
        const container = document.getElementById('itemsContainer');
        if (container.children.length > 1) {
            button.closest('.checklist-item').remove();
        } else {
            alert('Mindestens ein Prüfpunkt erforderlich');
        }
    }
    </script>
</body>
</html>