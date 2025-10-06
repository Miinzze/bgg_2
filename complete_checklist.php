<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('checklists_complete');

trackUsage('checklist_complete');

$markerId = $_GET['marker'] ?? 0;
$marker = getMarkerById($markerId, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

$message = '';
$messageType = '';

// Checkliste speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_checklist'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $templateId = $_POST['template_id'] ?? 0;
        $results = $_POST['checklist'] ?? [];
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            // Checkliste speichern
            $stmt = $pdo->prepare("
                INSERT INTO checklist_completions (marker_id, template_id, completed_by, results, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$markerId, $templateId, $_SESSION['user_id'], json_encode($results), $notes]);
            
            $completionId = $pdo->lastInsertId();
            
            // PDF generieren
            $pdfPath = generateChecklistPDF($completionId);
            
            // PDF-Pfad speichern
            $stmt = $pdo->prepare("UPDATE checklist_completions SET pdf_path = ? WHERE id = ?");
            $stmt->execute([$pdfPath, $completionId]);
            
            logActivity('checklist_completed', "Checkliste für '{$marker['name']}' ausgefüllt", $markerId);
            
            $message = 'Checkliste erfolgreich gespeichert!';
            $messageType = 'success';
            
            header("refresh:2;url=view_marker.php?id=$markerId");
            
        } catch (Exception $e) {
            $message = 'Fehler: ' . e($e->getMessage());
            $messageType = 'danger';
        }
    }
}

// Verfügbare Templates laden
$stmt = $pdo->query("SELECT * FROM checklist_templates ORDER BY category, name");
$templates = $stmt->fetchAll();

$selectedTemplate = null;
if (isset($_GET['template'])) {
    $stmt = $pdo->prepare("SELECT * FROM checklist_templates WHERE id = ?");
    $stmt->execute([$_GET['template']]);
    $selectedTemplate = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkliste ausfüllen - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .checklist-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .checklist-point {
            padding: 15px;
            background: #f8f9fa;
            margin: 10px 0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .checklist-point input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .checklist-point label {
            flex: 1;
            cursor: pointer;
            margin: 0;
        }
        .checklist-point.checked {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .template-selector {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-tasks"></i> Checkliste ausfüllen</h1>
                <h2><?= e($marker['name']) ?></h2>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <?php if (!$selectedTemplate): ?>
                <!-- Template auswählen -->
                <div class="template-selector">
                    <h2><i class="fas fa-list"></i> Template auswählen</h2>
                    <p>Wählen Sie eine Checkliste für dieses Gerät:</p>
                    
                    <?php if (empty($templates)): ?>
                        <div class="alert alert-warning">
                            Keine Templates verfügbar. 
                            <?php if (hasPermission('checklists_manage')): ?>
                                <a href="checklists_admin.php">Erstellen Sie zuerst ein Template</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; gap: 10px; margin-top: 15px;">
                            <?php
                            $groupedTemplates = [];
                            foreach ($templates as $template) {
                                $cat = $template['category'] ?: 'Allgemein';
                                $groupedTemplates[$cat][] = $template;
                            }
                            ?>
                            
                            <?php foreach ($groupedTemplates as $category => $catTemplates): ?>
                                <h3 style="margin-top: 15px; color: #e63312;"><?= e($category) ?></h3>
                                <?php foreach ($catTemplates as $template): ?>
                                    <a href="?marker=<?= $markerId ?>&template=<?= $template['id'] ?>" 
                                       style="display: block; padding: 15px; background: white; border-radius: 5px; text-decoration: none; color: inherit; border: 2px solid #dee2e6; transition: all 0.2s;"
                                       onmouseover="this.style.borderColor='#007bff'"
                                       onmouseout="this.style.borderColor='#dee2e6'">
                                        <strong><?= e($template['name']) ?></strong>
                                        <?php if ($template['description']): ?>
                                            <br><small style="color: #6c757d;"><?= e($template['description']) ?></small>
                                        <?php endif; ?>
                                        <br><small style="color: #6c757d;">
                                            <?php
                                            $items = json_decode($template['items'], true);
                                            echo count($items) . ' Prüfpunkte';
                                            ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Checkliste ausfüllen -->
                <div class="checklist-form">
                    <h2><?= e($selectedTemplate['name']) ?></h2>
                    <?php if ($selectedTemplate['description']): ?>
                        <p style="color: #6c757d;"><?= e($selectedTemplate['description']) ?></p>
                    <?php endif; ?>
                    
                    <form method="POST" id="checklistForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="template_id" value="<?= $selectedTemplate['id'] ?>">
                        
                        <div style="margin: 20px 0;">
                            <?php
                            $items = json_decode($selectedTemplate['items'], true);
                            foreach ($items as $index => $item):
                            ?>
                                <div class="checklist-point" id="point-<?= $index ?>">
                                    <input type="checkbox" 
                                           id="check-<?= $index ?>" 
                                           name="checklist[<?= $index ?>]" 
                                           value="1"
                                           onchange="togglePoint(<?= $index ?>)">
                                    <label for="check-<?= $index ?>">
                                        <strong><?= $index + 1 ?>.</strong> <?= e($item) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Anmerkungen / Besonderheiten</label>
                            <textarea id="notes" name="notes" rows="4" 
                                      placeholder="Optional: Besondere Feststellungen, durchgeführte Reparaturen, etc."></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_checklist" class="btn btn-success btn-large">
                                <i class="fas fa-save"></i> Checkliste speichern
                            </button>
                            <a href="?marker=<?= $markerId ?>" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Anderes Template wählen
                            </a>
                            <a href="view_marker.php?id=<?= $markerId ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    function togglePoint(index) {
        const point = document.getElementById('point-' + index);
        const checkbox = document.getElementById('check-' + index);
        
        if (checkbox.checked) {
            point.classList.add('checked');
        } else {
            point.classList.remove('checked');
        }
    }
    </script>

</body>
</html>