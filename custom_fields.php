<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('custom_fields_manage');

$message = '';
$messageType = '';

// Feld erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_field'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $fieldName = trim($_POST['field_name'] ?? '');
        $fieldLabel = trim($_POST['field_label'] ?? '');
        $fieldType = $_POST['field_type'] ?? 'text';
        $required = isset($_POST['required']) ? 1 : 0;
        
        if (empty($fieldName) || empty($fieldLabel)) {
            $message = 'Name und Label sind erforderlich';
            $messageType = 'danger';
        } elseif (!preg_match('/^[a-z_]+$/', $fieldName)) {
            $message = 'Feldname darf nur Kleinbuchstaben und Unterstriche enthalten';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO custom_fields (field_name, field_label, field_type, required, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$fieldName, $fieldLabel, $fieldType, $required, $_SESSION['user_id']]);
                
                logActivity('custom_field_created', "Feld '{$fieldLabel}' erstellt");
                
                $message = 'Feld erfolgreich erstellt!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Fehler: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Feld löschen
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM custom_fields WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    
    logActivity('custom_field_deleted', "Custom Field ID {$_GET['delete']} gelöscht");
    
    $message = 'Feld gelöscht';
    $messageType = 'success';
}

// Alle Custom Fields laden
$stmt = $pdo->query("SELECT * FROM custom_fields ORDER BY display_order, id");
$fields = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Custom Fields</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-list"></i> Custom Fields verwalten</h1>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <p><i class="fas fa-info-circle"></i> Custom Fields erscheinen bei der Marker-Erstellung als zusätzliche Eingabefelder.</p>
            </div>
            
            <div class="admin-grid">
                <div class="admin-section">
                    <h2>Neues Feld erstellen</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-group">
                            <label for="field_name">Feldname (technisch) *</label>
                            <input type="text" id="field_name" name="field_name" required
                                   pattern="[a-z_]+" placeholder="z.B. projekt_nr">
                            <small>Nur Kleinbuchstaben und Unterstriche</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="field_label">Beschriftung *</label>
                            <input type="text" id="field_label" name="field_label" required
                                   placeholder="z.B. Projekt-Nummer">
                        </div>
                        
                        <div class="form-group">
                            <label for="field_type">Feldtyp</label>
                            <select id="field_type" name="field_type">
                                <option value="text">Text (einzeilig)</option>
                                <option value="textarea">Text (mehrzeilig)</option>
                                <option value="number">Zahl</option>
                                <option value="date">Datum</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" name="required">
                                <span>Pflichtfeld</span>
                            </label>
                        </div>
                        
                        <button type="submit" name="create_field" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Feld erstellen
                        </button>
                    </form>
                </div>
                
                <div class="admin-section">
                    <h2>Vorhandene Felder (<?= count($fields) ?>)</h2>
                    <?php if (empty($fields)): ?>
                        <p style="color: #6c757d;">Noch keine Custom Fields vorhanden</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Label</th>
                                    <th>Feldname</th>
                                    <th>Typ</th>
                                    <th>Pflicht</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td><strong><?= e($field['field_label']) ?></strong></td>
                                    <td><code><?= e($field['field_name']) ?></code></td>
                                    <td><?= e($field['field_type']) ?></td>
                                    <td>
                                        <?php if ($field['required']): ?>
                                            <span class="badge badge-warning">Ja</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Nein</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?delete=<?= $field['id'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Feld wirklich löschen?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>