<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('bulk_operations');

trackUsage('bulk_operations');

$message = '';
$messageType = '';

// Bulk-Operation durchführen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $markerIds = $_POST['marker_ids'] ?? [];
        $action = $_POST['action'] ?? '';
        
        if (empty($markerIds)) {
            $message = 'Keine Marker ausgewählt';
            $messageType = 'warning';
        } else {
            $count = 0;
            
            switch ($action) {
                case 'change_category':
                    $newCategory = trim($_POST['new_category'] ?? '');
                    if (!empty($newCategory)) {
                        $placeholders = str_repeat('?,', count($markerIds) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE markers SET category = ? WHERE id IN ($placeholders)");
                        $params = array_merge([$newCategory], $markerIds);
                        $stmt->execute($params);
                        $count = $stmt->rowCount();
                        
                        logActivity('bulk_category_change', "Kategorie für {$count} Marker geändert zu '{$newCategory}'");
                    }
                    break;
                    
                case 'change_status':
                    $newStatus = $_POST['new_status'] ?? '';
                    if (in_array($newStatus, ['verfuegbar', 'vermietet', 'wartung'])) {
                        $placeholders = str_repeat('?,', count($markerIds) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE markers SET rental_status = ? WHERE id IN ($placeholders)");
                        $params = array_merge([$newStatus], $markerIds);
                        $stmt->execute($params);
                        $count = $stmt->rowCount();
                        
                        logActivity('bulk_status_change', "Status für {$count} Marker geändert zu '{$newStatus}'");
                    }
                    break;
                    
                case 'delete':
                    if (hasPermission('markers_delete')) {
                        $placeholders = str_repeat('?,', count($markerIds) - 1) . '?';
                        $stmt = $pdo->prepare("DELETE FROM markers WHERE id IN ($placeholders)");
                        $stmt->execute($markerIds);
                        $count = $stmt->rowCount();
                        
                        logActivity('bulk_delete', "{$count} Marker gelöscht");
                    }
                    break;
            }
            
            $message = "Aktion erfolgreich auf {$count} Marker angewendet!";
            $messageType = 'success';
        }
    }
}

// Alle Marker laden
$markers = getAllMarkers($pdo);

// Kategorien für Filter
$categories = $pdo->query("SELECT DISTINCT category FROM markers WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bulk-Operationen</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .marker-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .selected-row {
            background: #e7f3ff !important;
        }
        .bulk-actions {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .action-panel {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-tasks"></i> Bulk-Operationen</h1>
                <a href="markers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <div class="bulk-actions">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div>
                        <h3 style="margin: 0;">
                            <i class="fas fa-check-square"></i> 
                            <span id="selectedCount">0</span> Marker ausgewählt
                        </h3>
                    </div>
                    <div>
                        <button onclick="selectAll()" class="btn btn-sm btn-secondary">
                            <i class="fas fa-check-square"></i> Alle auswählen
                        </button>
                        <button onclick="deselectAll()" class="btn btn-sm btn-secondary">
                            <i class="fas fa-square"></i> Alle abwählen
                        </button>
                    </div>
                </div>
                
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label for="action">Aktion auswählen</label>
                        <select id="action" name="action" onchange="showActionPanel()" required>
                            <option value="">-- Bitte wählen --</option>
                            <option value="change_category">Kategorie ändern</option>
                            <option value="change_status">Status ändern</option>
                            <option value="delete">Löschen</option>
                        </select>
                    </div>
                    
                    <!-- Kategorie ändern -->
                    <div id="panel-change_category" class="action-panel">
                        <label for="new_category">Neue Kategorie</label>
                        <input type="text" id="new_category" name="new_category" list="categoryList">
                        <datalist id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <!-- Status ändern -->
                    <div id="panel-change_status" class="action-panel">
                        <label for="new_status">Neuer Status</label>
                        <select id="new_status" name="new_status">
                            <option value="verfuegbar">Verfügbar</option>
                            <option value="vermietet">Vermietet</option>
                            <option value="wartung">Wartung</option>
                        </select>
                    </div>
                    
                    <!-- Löschen Bestätigung -->
                    <div id="panel-delete" class="action-panel">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Achtung:</strong> Diese Aktion kann nicht rückgängig gemacht werden!
                        </div>
                    </div>
                    
                    <button type="submit" name="bulk_action" class="btn btn-primary" id="submitBtn" disabled>
                        <i class="fas fa-play"></i> Aktion ausführen
                    </button>
                </form>
            </div>
            
            <!-- Marker-Tabelle -->
            <div class="admin-section">
                <h2>Marker auswählen (<?= count($markers) ?>)</h2>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" id="selectAllCheckbox" onclick="selectAll()">
                            </th>
                            <th>Name</th>
                            <th>Kategorie</th>
                            <th>Status</th>
                            <th>RFID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($markers as $marker): ?>
                        <tr id="row-<?= $marker['id'] ?>">
                            <td>
                                <input type="checkbox" 
                                       class="marker-checkbox" 
                                       name="marker_ids[]" 
                                       value="<?= $marker['id'] ?>"
                                       form="bulkForm"
                                       onchange="updateSelection()">
                            </td>
                            <td><strong><?= e($marker['name']) ?></strong></td>
                            <td><?= e($marker['category']) ?></td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'verfuegbar' => ['label' => 'Verfügbar', 'class' => 'success'],
                                    'vermietet' => ['label' => 'Vermietet', 'class' => 'warning'],
                                    'wartung' => ['label' => 'Wartung', 'class' => 'danger']
                                ];
                                $status = $statusLabels[$marker['rental_status']] ?? ['label' => '-', 'class' => 'secondary'];
                                ?>
                                <span class="badge badge-<?= $status['class'] ?>"><?= $status['label'] ?></span>
                            </td>
                            <td><code><?= e($marker['rfid_chip']) ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    function updateSelection() {
        const checkboxes = document.querySelectorAll('.marker-checkbox:checked');
        const count = checkboxes.length;
        
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('submitBtn').disabled = count === 0;
        
        // Rows highlighten
        document.querySelectorAll('.marker-checkbox').forEach(cb => {
            const row = cb.closest('tr');
            if (cb.checked) {
                row.classList.add('selected-row');
            } else {
                row.classList.remove('selected-row');
            }
        });
    }
    
    function selectAll() {
        document.querySelectorAll('.marker-checkbox').forEach(cb => cb.checked = true);
        document.getElementById('selectAllCheckbox').checked = true;
        updateSelection();
    }
    
    function deselectAll() {
        document.querySelectorAll('.marker-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllCheckbox').checked = false;
        updateSelection();
    }
    
    function showActionPanel() {
        const action = document.getElementById('action').value;
        
        // Alle Panels verstecken
        document.querySelectorAll('.action-panel').forEach(panel => {
            panel.style.display = 'none';
        });
        
        // Ausgewähltes Panel anzeigen
        if (action) {
            const panel = document.getElementById('panel-' + action);
            if (panel) {
                panel.style.display = 'block';
            }
        }
    }
    
    // Form-Validierung
    document.getElementById('bulkForm').addEventListener('submit', function(e) {
        const action = document.getElementById('action').value;
        
        if (action === 'delete') {
            if (!confirm('Wirklich alle ausgewählten Marker unwiderruflich löschen?')) {
                e.preventDefault();
                return false;
            }
        }
        
        const count = document.querySelectorAll('.marker-checkbox:checked').length;
        if (!confirm(`Aktion auf ${count} Marker anwenden?`)) {
            e.preventDefault();
            return false;
        }
    });
    </script>

</body>
</html>