<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

$message = '';
$messageType = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $operatingHours = $_POST['operating_hours'] ?? 0;
    $fuelLevel = $_POST['fuel_level'] ?? 0;
    $maintenanceInterval = $_POST['maintenance_interval'] ?? 6;
    $lastMaintenance = $_POST['last_maintenance'] ?? null;
    
    if (empty($name)) {
        $message = 'Name ist erforderlich';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Nächste Wartung berechnen
            $nextMaintenance = null;
            if (!$marker['is_storage'] && !$marker['is_multi_device'] && $lastMaintenance && $maintenanceInterval > 0) {
                $nextMaintenance = calculateNextMaintenance($lastMaintenance, $maintenanceInterval);
            }
            
            // Marker aktualisieren
            $stmt = $pdo->prepare("
                UPDATE markers SET
                    name = ?,
                    category = ?,
                    serial_number = ?,
                    operating_hours = ?,
                    fuel_level = ?,
                    maintenance_interval_months = ?,
                    last_maintenance = ?,
                    next_maintenance = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name,
                $category,
                $serialNumber,
                floatval($operatingHours),
                intval($fuelLevel),
                intval($maintenanceInterval),
                $lastMaintenance,
                $nextMaintenance,
                $id
            ]);
            
            // Custom Fields aktualisieren
            if (!empty($_POST['custom_fields'])) {
                foreach ($_POST['custom_fields'] as $fieldId => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO marker_custom_values (marker_id, field_id, field_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)
                    ");
                    $stmt->execute([$id, $fieldId, $value]);
                }
            }
            
            // Öffentliche Dokumente aktualisieren
            if (hasPermission('documents_upload')) {
                // Erst alle auf privat setzen
                $stmt = $pdo->prepare("UPDATE marker_documents SET is_public = 0 WHERE marker_id = ?");
                $stmt->execute([$id]);
                
                // Dann die ausgewählten auf öffentlich setzen
                if (!empty($_POST['public_docs'])) {
                    foreach ($_POST['public_docs'] as $docId => $value) {
                        $description = $_POST['public_descriptions'][$docId] ?? null;
                        
                        $stmt = $pdo->prepare("
                            UPDATE marker_documents 
                            SET is_public = 1, public_description = ?
                            WHERE id = ? AND marker_id = ?
                        ");
                        $stmt->execute([$description, $docId, $id]);
                    }
                }
            }
            
            // Neue Bilder hochladen
            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                    if (!empty($tmpName)) {
                        $file = [
                            'name' => $_FILES['images']['name'][$key],
                            'type' => $_FILES['images']['type'][$key],
                            'tmp_name' => $tmpName,
                            'size' => $_FILES['images']['size'][$key]
                        ];
                        
                        $result = uploadImage($file, $id);
                        if ($result['success']) {
                            $stmt = $pdo->prepare("INSERT INTO marker_images (marker_id, image_path) VALUES (?, ?)");
                            $stmt->execute([$id, $result['path']]);
                        }
                    }
                }
            }
            
            // Neue Dokumente hochladen
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
                            $isPublic = isset($_POST['new_doc_is_public'][$key]) ? 1 : 0;
                            $publicDesc = $_POST['new_doc_public_desc'][$key] ?? null;
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO marker_documents 
                                (marker_id, document_name, document_path, file_size, uploaded_by, is_public, public_description) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $id,
                                $_FILES['documents']['name'][$key],
                                $result['path'],
                                $result['size'],
                                $_SESSION['user_id'],
                                $isPublic,
                                $publicDesc
                            ]);
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

// Bilder, Dokumente und Custom Fields laden
$images = getMarkerImages($id, $pdo);

$stmt = $pdo->prepare("SELECT * FROM marker_documents WHERE marker_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$id]);
$documents = $stmt->fetchAll();

$customFields = $pdo->query("SELECT * FROM custom_fields ORDER BY display_order, id")->fetchAll();
$customValues = [];
if (!empty($customFields)) {
    $stmt = $pdo->prepare("SELECT field_id, field_value FROM marker_custom_values WHERE marker_id = ?");
    $stmt->execute([$id]);
    $customValues = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marker bearbeiten - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .document-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .document-item.public {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .public-toggle {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        
        .public-description {
            margin-top: 10px;
        }
        
        .qr-code-display {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        
        .qr-code-display code {
            font-size: 18px;
            font-weight: bold;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-edit"></i> Marker bearbeiten</h1>
                <p><?= e($marker['name']) ?></p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <!-- QR-Code Anzeige (nicht änderbar) -->
            <div class="qr-code-display">
                <strong><i class="fas fa-qrcode"></i> QR-Code:</strong>
                <code><?= e($marker['qr_code']) ?></code>
                <span style="color: #666; margin-left: 15px;">
                    <i class="fas fa-info-circle"></i> QR-Codes können nicht geändert werden
                </span>
                <a href="print_qr.php?id=<?= $marker['id'] ?>" class="btn btn-sm btn-secondary" target="_blank" style="float: right;">
                    <i class="fas fa-print"></i> QR-Code drucken
                </a>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="marker-form">
                <?= csrf_field() ?>
                
                <div class="form-section">
                    <h2>Grunddaten</h2>
                    
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" value="<?= e($marker['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Kategorie</label>
                        <select id="category" name="category">
                            <option value="">-- Keine --</option>
                            <option value="Generator" <?= $marker['category'] === 'Generator' ? 'selected' : '' ?>>Generator</option>
                            <option value="Kompressor" <?= $marker['category'] === 'Kompressor' ? 'selected' : '' ?>>Kompressor</option>
                            <option value="Pumpe" <?= $marker['category'] === 'Pumpe' ? 'selected' : '' ?>>Pumpe</option>
                            <option value="Fahrzeug" <?= $marker['category'] === 'Fahrzeug' ? 'selected' : '' ?>>Fahrzeug</option>
                            <option value="Werkzeug" <?= $marker['category'] === 'Werkzeug' ? 'selected' : '' ?>>Werkzeug</option>
                            <option value="Lager" <?= $marker['category'] === 'Lager' ? 'selected' : '' ?>>Lager</option>
                            <option value="Sonstiges" <?= $marker['category'] === 'Sonstiges' ? 'selected' : '' ?>>Sonstiges</option>
                        </select>
                    </div>
                </div>
                
                <?php if (!$marker['is_multi_device'] && !$marker['is_storage']): ?>
                <div class="form-section">
                    <h2>Gerätedaten</h2>
                    
                    <div class="form-group">
                        <label for="serial_number">Seriennummer</label>
                        <input type="text" id="serial_number" name="serial_number" 
                               value="<?= e($marker['serial_number']) ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="operating_hours">Betriebsstunden</label>
                            <input type="number" id="operating_hours" name="operating_hours" 
                                   value="<?= $marker['operating_hours'] ?>" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="fuel_level">Kraftstofffüllstand (%)</label>
                            <input type="range" id="fuel_level" name="fuel_level" 
                                   min="0" max="100" value="<?= $marker['fuel_level'] ?>" 
                                   oninput="document.getElementById('fuelValue').textContent = this.value">
                            <div class="fuel-display">
                                <span id="fuelValue"><?= $marker['fuel_level'] ?></span>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Wartung</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="maintenance_interval">Wartungsintervall (Monate)</label>
                            <input type="number" id="maintenance_interval" name="maintenance_interval" 
                                   value="<?= $marker['maintenance_interval_months'] ?>" min="1">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_maintenance">Letzte Wartung</label>
                            <input type="date" id="last_maintenance" name="last_maintenance" 
                                   value="<?= $marker['last_maintenance'] ?>">
                        </div>
                    </div>
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
                            $value = $customValues[$field['id']] ?? '';
                            ?>
                            
                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <textarea id="custom_<?= $field['id'] ?>" 
                                        name="custom_fields[<?= $field['id'] ?>]" 
                                        rows="4"
                                        <?= $field['required'] ? 'required' : '' ?>><?= e($value) ?></textarea>
                            
                            <?php elseif ($field['field_type'] === 'number'): ?>
                                <input type="number" 
                                    id="custom_<?= $field['id'] ?>" 
                                    name="custom_fields[<?= $field['id'] ?>]"
                                    value="<?= e($value) ?>"
                                    step="any"
                                    <?= $field['required'] ? 'required' : '' ?>>
                            
                            <?php elseif ($field['field_type'] === 'date'): ?>
                                <input type="date" 
                                    id="custom_<?= $field['id'] ?>" 
                                    name="custom_fields[<?= $field['id'] ?>]"
                                    value="<?= e($value) ?>"
                                    <?= $field['required'] ? 'required' : '' ?>>
                            
                            <?php else: ?>
                                <input type="text" 
                                    id="custom_<?= $field['id'] ?>" 
                                    name="custom_fields[<?= $field['id'] ?>]"
                                    value="<?= e($value) ?>"
                                    <?= $field['required'] ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Dokumente -->
                <?php if (hasPermission('documents_upload')): ?>
                <div class="form-section">
                    <h2><i class="fas fa-file-pdf"></i> Dokumente</h2>
                    
                    <?php if (!empty($documents)): ?>
                        <h3>Vorhandene Dokumente</h3>
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-item <?= $doc['is_public'] ? 'public' : '' ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><?= e($doc['document_name']) ?></strong>
                                        <br>
                                        <small>
                                            <?= number_format($doc['file_size'] / 1024 / 1024, 2) ?> MB | 
                                            <?= formatDateTime($doc['uploaded_at']) ?>
                                        </small>
                                    </div>
                                    <div>
                                        <a href="<?= e($doc['document_path']) ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> Anzeigen
                                        </a>
                                        <button type="button" onclick="deleteDocument(<?= $doc['id'] ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Löschen
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- ÖFFENTLICH-TOGGLE -->
                                <div class="public-toggle">
                                    <input type="checkbox" 
                                           id="public_<?= $doc['id'] ?>" 
                                           name="public_docs[<?= $doc['id'] ?>]"
                                           value="1"
                                           <?= $doc['is_public'] ? 'checked' : '' ?>
                                           onchange="togglePublicDescription(<?= $doc['id'] ?>)">
                                    <label for="public_<?= $doc['id'] ?>">
                                        <i class="fas fa-globe"></i> <strong>Öffentlich sichtbar</strong>
                                        <small>(erscheint in Public View ohne Login)</small>
                                    </label>
                                    
                                    <div id="public_desc_<?= $doc['id'] ?>" 
                                         class="public-description"
                                         style="display: <?= $doc['is_public'] ? 'block' : 'none' ?>;">
                                        <label for="desc_<?= $doc['id'] ?>">Öffentliche Beschreibung (optional):</label>
                                        <input type="text" 
                                               id="desc_<?= $doc['id'] ?>"
                                               name="public_descriptions[<?= $doc['id'] ?>]"
                                               placeholder="z.B. Bedienungsanleitung Generator XY123"
                                               value="<?= e($doc['public_description'] ?? '') ?>"
                                               style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <h3 style="margin-top: 30px;">Neue Dokumente hochladen</h3>
                    <div class="form-group">
                        <input type="file" id="documents" name="documents[]" multiple accept=".pdf,application/pdf">
                        <small>Nur PDF-Dateien (max. 10MB pro Datei)</small>
                    </div>
                    
                    <div id="newDocOptions" style="display: none; margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <div style="margin-bottom: 10px;">
                            <input type="checkbox" id="new_doc_public" name="new_doc_is_public[0]" value="1">
                            <label for="new_doc_public">
                                <i class="fas fa-globe"></i> Als öffentlich markieren
                            </label>
                        </div>
                        <input type="text" 
                               name="new_doc_public_desc[0]" 
                               placeholder="Öffentliche Beschreibung (optional)"
                               style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Bilder -->
                <div class="form-section">
                    <h2><i class="fas fa-images"></i> Bilder</h2>
                    
                    <?php if (!empty($images)): ?>
                        <div class="image-gallery">
                            <?php foreach ($images as $img): ?>
                                <div class="image-item">
                                    <img src="<?= e($img['image_path']) ?>" alt="Bild">
                                    <button type="button" onclick="deleteImage(<?= $img['id'] ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Löschen
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label for="images">Neue Bilder hochladen</label>
                        <input type="file" id="images" name="images[]" multiple accept="image/*">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
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
        function togglePublicDescription(docId) {
            const checkbox = document.getElementById('public_' + docId);
            const descDiv = document.getElementById('public_desc_' + docId);
            descDiv.style.display = checkbox.checked ? 'block' : 'none';
        }
        
        function deleteImage(imageId) {
            if (confirm('Bild wirklich löschen?')) {
                window.location.href = 'delete_image.php?id=' + imageId + '&marker_id=<?= $marker['id'] ?>';
            }
        }
        
        function deleteDocument(docId) {
            if (confirm('Dokument wirklich löschen?')) {
                window.location.href = 'delete_document.php?id=' + docId + '&marker_id=<?= $marker['id'] ?>';
            }
        }
        
        // Neue Dokumente: Öffentlich-Optionen anzeigen
        document.getElementById('documents').addEventListener('change', function(e) {
            const optionsDiv = document.getElementById('newDocOptions');
            optionsDiv.style.display = e.target.files.length > 0 ? 'block' : 'none';
        });
    </script>
</body>
</html>