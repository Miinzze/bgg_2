<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('markers_edit');

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

$message = '';
$messageType = '';

// Marker aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_marker'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if (empty($name) || !validateStringLength($name, 1, 100)) {
            $message = 'Name ist erforderlich und darf maximal 100 Zeichen lang sein';
            $messageType = 'danger';
        } elseif (!empty($category) && !validateStringLength($category, 1, 50)) {
            $message = 'Kategorie darf maximal 50 Zeichen lang sein';
            $messageType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Multi-Device: Nur Name, Kategorie und Custom Fields
                if ($marker['is_multi_device']) {
                    $stmt = $pdo->prepare("UPDATE markers SET name = ?, category = ? WHERE id = ?");
                    $stmt->execute([$name, $category, $id]);
                    
                } else {
                    // Normale Marker: Alle Felder
                    $serialNumber = trim($_POST['serial_number'] ?? '');
                    $operatingHours = $_POST['operating_hours'] ?? 0;
                    $fuelLevel = $_POST['fuel_level'] ?? 0;
                    $maintenanceInterval = $_POST['maintenance_interval'] ?? 6;
                    $lastMaintenance = $_POST['last_maintenance'] ?? '';
                    
                    if (!validateSerialNumber($serialNumber)) {
                        throw new Exception('Seriennummer darf nur Zahlen enthalten');
                    }
                    if (!validateFloat($operatingHours, 0)) {
                        throw new Exception('Ungültige Betriebsstunden');
                    }
                    if (!validateInteger($fuelLevel, 0, 100)) {
                        throw new Exception('Kraftstofffüllstand muss zwischen 0 und 100 liegen');
                    }
                    if (!validateInteger($maintenanceInterval, 1, 120)) {
                        throw new Exception('Wartungsintervall muss zwischen 1 und 120 Monaten liegen');
                    }
                    if (!empty($lastMaintenance) && !validateDate($lastMaintenance)) {
                        throw new Exception('Ungültiges Datum für letzte Wartung');
                    }
                    
                    $nextMaintenance = calculateNextMaintenance($lastMaintenance, $maintenanceInterval);
                    
                    $stmt = $pdo->prepare("
                        UPDATE markers SET 
                            name = ?, category = ?, serial_number = ?, operating_hours = ?,
                            fuel_level = ?, maintenance_interval_months = ?, last_maintenance = ?,
                            next_maintenance = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $name, $category, $serialNumber, floatval($operatingHours), intval($fuelLevel),
                        intval($maintenanceInterval), $lastMaintenance, $nextMaintenance, $id
                    ]);
                }
                
                // Custom Fields speichern/aktualisieren
                if (!empty($_POST['custom_fields'])) {
                    // Zuerst alte Werte löschen
                    $stmt = $pdo->prepare("DELETE FROM marker_custom_values WHERE marker_id = ?");
                    $stmt->execute([$id]);
                    
                    // Neue Werte einfügen
                    $stmt = $pdo->prepare("INSERT INTO marker_custom_values (marker_id, field_id, field_value) VALUES (?, ?, ?)");
                    foreach ($_POST['custom_fields'] as $fieldId => $value) {
                        if (!empty($value) || $value === '0') {
                            $stmt->execute([$id, $fieldId, $value]);
                        }
                    }
                }
                
                // PDF-Dokumente hochladen
                if (hasPermission('documents_upload') && !empty($_FILES['documents']['name'][0])) {
                    foreach ($_FILES['documents']['tmp_name'] as $key => $tmpName) {
                        if (!empty($tmpName)) {
                            $file = [
                                'name' => $_FILES['documents']['name'][$key],
                                'type' => $_FILES['documents']['type'][$key],
                                'tmp_name' => $tmpName,
                                'size' => $_FILES['documents']['size'][$key]
                            ];
                            
                            $result = uploadPDF($file, $id);
                            if ($result['success']) {
                                $stmt = $pdo->prepare("INSERT INTO marker_documents (marker_id, document_name, document_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $id,
                                    $_FILES['documents']['name'][$key],
                                    $result['path'],
                                    $result['size'],
                                    $_SESSION['user_id']
                                ]);
                                
                                logActivity('document_uploaded', "Dokument '{$_FILES['documents']['name'][$key]}' zu '{$name}' hochgeladen", $id);
                            }
                        }
                    }
                }
                
                $pdo->commit();
                
                logActivity('marker_updated', "Marker '{$name}' aktualisiert", $id);
                
                $message = 'Marker erfolgreich aktualisiert!';
                $messageType = 'success';
                
                // Marker neu laden
                $marker = getMarkerById($id, $pdo);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Fehler: ' . e($e->getMessage());
                $messageType = 'danger';
            }
        }
    }
}

// Custom Fields laden
$customFields = $pdo->query("SELECT * FROM custom_fields ORDER BY display_order, id")->fetchAll();

// Vorhandene Custom Values laden
$customValues = [];
if (!empty($customFields)) {
    $stmt = $pdo->prepare("SELECT field_id, field_value FROM marker_custom_values WHERE marker_id = ?");
    $stmt->execute([$id]);
    while ($row = $stmt->fetch()) {
        $customValues[$row['field_id']] = $row['field_value'];
    }
}

// Dokumente laden
$stmt = $pdo->prepare("
    SELECT md.*, u.username as uploaded_by_name
    FROM marker_documents md
    LEFT JOIN users u ON md.uploaded_by = u.id
    WHERE md.marker_id = ?
    ORDER BY md.uploaded_at DESC
");
$stmt->execute([$id]);
$documents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marker bearbeiten - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .document-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
            margin-bottom: 10px;
        }
        .document-item i {
            color: #dc3545;
            font-size: 32px;
            margin-right: 15px;
        }
        .document-info {
            flex: 1;
        }
        .document-actions {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-edit"></i> Marker bearbeiten</h1>
                <div class="header-actions">
                    <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="marker-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="update_marker" value="1">
                
                <!-- Grunddaten -->
                <div class="form-section">
                    <h2><i class="fas fa-info-circle"></i> Grunddaten</h2>
                    
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" value="<?= e($marker['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Kategorie</label>
                        <input type="text" id="category" name="category" value="<?= e($marker['category']) ?>" 
                               list="categoryList">
                        <datalist id="categoryList">
                            <?php
                            $stmt = $pdo->query("SELECT DISTINCT category FROM markers WHERE category IS NOT NULL AND category != '' ORDER BY category");
                            while ($cat = $stmt->fetch()):
                            ?>
                                <option value="<?= e($cat['category']) ?>">
                            <?php endwhile; ?>
                        </datalist>
                    </div>
                    
                    <?php if ($marker['is_multi_device']): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Multi-Device Marker:</strong> Seriennummern werden separat verwaltet.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Nur bei normalen Markern: Gerätedaten -->
                <?php if (!$marker['is_multi_device']): ?>
                <div class="form-section">
                    <h2><i class="fas fa-cog"></i> Gerätedaten</h2>
                    
                    <div class="form-group">
                        <label for="serial_number">Seriennummer</label>
                        <input type="text" id="serial_number" name="serial_number" 
                               value="<?= e($marker['serial_number']) ?>">
                    </div>
                    
                    <?php if (!$marker['is_storage']): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="operating_hours">Betriebsstunden</label>
                            <input type="number" id="operating_hours" name="operating_hours" 
                                   value="<?= e($marker['operating_hours']) ?>" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label for="fuel_level">Kraftstofffüllstand (%)</label>
                            <input type="number" id="fuel_level" name="fuel_level" 
                                   value="<?= e($marker['fuel_level']) ?>" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="maintenance_interval">Wartungsintervall (Monate)</label>
                            <input type="number" id="maintenance_interval" name="maintenance_interval" 
                                   value="<?= e($marker['maintenance_interval_months']) ?>" min="1" max="120">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_maintenance">Letzte Wartung</label>
                            <input type="date" id="last_maintenance" name="last_maintenance" 
                                   value="<?= e($marker['last_maintenance']) ?>">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Custom Fields -->
                <?php if (!empty($customFields)): ?>
                <div class="form-section">
                    <h2><i class="fas fa-list"></i> Zusätzliche Informationen</h2>
                    
                    <?php foreach ($customFields as $field): ?>
                        <div class="form-group">
                            <label for="custom_<?= $field['id'] ?>">
                                <?= e($field['field_label']) ?>
                                <?php if ($field['required']): ?>
                                    <span style="color: red;">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php
                            $currentValue = $customValues[$field['id']] ?? '';
                            ?>
                            
                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <textarea id="custom_<?= $field['id'] ?>" 
                                          name="custom_fields[<?= $field['id'] ?>]" 
                                          rows="4"
                                          <?= $field['required'] ? 'required' : '' ?>><?= e($currentValue) ?></textarea>
                            
                            <?php elseif ($field['field_type'] === 'number'): ?>
                                <input type="number" 
                                       id="custom_<?= $field['id'] ?>" 
                                       name="custom_fields[<?= $field['id'] ?>]"
                                       value="<?= e($currentValue) ?>"
                                       step="any"
                                       <?= $field['required'] ? 'required' : '' ?>>
                            
                            <?php elseif ($field['field_type'] === 'date'): ?>
                                <input type="date" 
                                       id="custom_<?= $field['id'] ?>" 
                                       name="custom_fields[<?= $field['id'] ?>]"
                                       value="<?= e($currentValue) ?>"
                                       <?= $field['required'] ? 'required' : '' ?>>
                            
                            <?php else: ?>
                                <input type="text" 
                                       id="custom_<?= $field['id'] ?>" 
                                       name="custom_fields[<?= $field['id'] ?>]"
                                       value="<?= e($currentValue) ?>"
                                       <?= $field['required'] ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Dokumente -->
                <?php if (hasPermission('documents_upload') || !empty($documents)): ?>
                <div class="form-section">
                    <h2><i class="fas fa-file-pdf"></i> PDF-Dokumente</h2>
                    
                    <!-- Vorhandene Dokumente -->
                    <?php if (!empty($documents)): ?>
                        <div style="margin-bottom: 20px;">
                            <h3 style="font-size: 16px; margin-bottom: 10px;">Vorhandene Dokumente (<?= count($documents) ?>)</h3>
                            <?php foreach ($documents as $doc): ?>
                                <div class="document-item">
                                    <i class="fas fa-file-pdf"></i>
                                    <div class="document-info">
                                        <strong><?= e($doc['document_name']) ?></strong><br>
                                        <small style="color: #6c757d;">
                                            <?= number_format($doc['file_size'] / 1024 / 1024, 2) ?> MB |
                                            Hochgeladen: <?= date('d.m.Y H:i', strtotime($doc['uploaded_at'])) ?>
                                            <?php if ($doc['uploaded_by_name']): ?>
                                                von <?= e($doc['uploaded_by_name']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="document-actions">
                                        <a href="<?= e($doc['document_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Öffnen
                                        </a>
                                        <a href="<?= e($doc['document_path']) ?>" download class="btn btn-sm btn-secondary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if (hasPermission('documents_delete')): ?>
                                            <a href="delete_document.php?id=<?= $doc['id'] ?>&marker=<?= $marker['id'] ?>&redirect=edit" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Dokument wirklich löschen?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Neue Dokumente hochladen -->
                    <?php if (hasPermission('documents_upload')): ?>
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 5px;">
                            <h3 style="font-size: 16px; margin-bottom: 10px;">Neue Dokumente hochladen</h3>
                            <div class="form-group">
                                <label for="documents">PDF-Dokumente (mehrere möglich)</label>
                                <input type="file" id="documents" name="documents[]" multiple accept=".pdf,application/pdf">
                                <small>Nur PDF-Dateien (max. 10MB pro Datei)</small>
                            </div>
                            
                            <div id="pdfPreview" style="margin-top: 10px;"></div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-large">
                        <i class="fas fa-save"></i> Änderungen speichern
                    </button>
                    <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    // PDF Preview
    const pdfInput = document.getElementById('documents');
    if (pdfInput) {
        pdfInput.addEventListener('change', function(e) {
            const previewDiv = document.getElementById('pdfPreview');
            previewDiv.innerHTML = '';
            
            if (e.target.files.length > 0) {
                previewDiv.innerHTML = '<h4 style="font-size: 14px; margin: 10px 0;">Neue Uploads:</h4>';
            }
            
            Array.from(e.target.files).forEach(file => {
                const item = document.createElement('div');
                item.style.cssText = 'padding: 10px; background: white; margin: 5px 0; border-radius: 5px; display: flex; align-items: center; gap: 10px; border: 1px solid #dee2e6;';
                item.innerHTML = `
                    <i class="fas fa-file-pdf" style="color: #dc3545; font-size: 24px;"></i>
                    <div style="flex: 1;">
                        <strong>${file.name}</strong><br>
                        <small style="color: #6c757d;">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    </div>
                    <i class="fas fa-check-circle" style="color: #28a745; font-size: 20px;"></i>
                `;
                previewDiv.appendChild(item);
            });
        });
    }
    </script>
</body>
</html>