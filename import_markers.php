<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('markers_create');

$message = '';
$messageType = '';
$preview = null;

// Import durchführen
if (isset($_POST['import_confirm'])) {
    $importData = json_decode($_POST['import_data'], true);
    $overwriteExisting = isset($_POST['overwrite_existing']);
    
    if (!$importData || !isset($importData['markers'])) {
        $message = 'Ungültige Import-Daten';
        $messageType = 'danger';
    } else {
        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $errors = [];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($importData['markers'] as $markerData) {
                // Prüfen ob Marker bereits existiert (anhand RFID)
                $stmt = $pdo->prepare("SELECT id FROM markers WHERE rfid_chip = ?");
                $stmt->execute([$markerData['rfid_chip']]);
                $existingId = $stmt->fetchColumn();
                
                if ($existingId && !$overwriteExisting) {
                    $skipped++;
                    continue;
                }
                
                // Marker-Daten vorbereiten
                $markerFields = [
                    'rfid_chip', 'name', 'category', 'serial_number', 'latitude', 'longitude',
                    'operating_hours', 'fuel_level', 'is_storage', 'is_multi_device',
                    'rental_status', 'last_maintenance', 'next_maintenance', 'maintenance_interval_months'
                ];
                
                if ($existingId) {
                    // UPDATE
                    $sets = [];
                    $params = [];
                    foreach ($markerFields as $field) {
                        if (isset($markerData[$field])) {
                            $sets[] = "$field = ?";
                            $params[] = $markerData[$field];
                        }
                    }
                    $params[] = $existingId;
                    
                    $sql = "UPDATE markers SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $newMarkerId = $existingId;
                    $updated++;
                } else {
                    // INSERT
                    $fields = [];
                    $placeholders = [];
                    $params = [];
                    
                    foreach ($markerFields as $field) {
                        if (isset($markerData[$field])) {
                            $fields[] = $field;
                            $placeholders[] = '?';
                            $params[] = $markerData[$field];
                        }
                    }
                    
                    $fields[] = 'created_by';
                    $placeholders[] = '?';
                    $params[] = $_SESSION['user_id'];
                    
                    $sql = "INSERT INTO markers (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $newMarkerId = $pdo->lastInsertId();
                    $imported++;
                }
                
                // Seriennummern (bei Multi-Device)
                if (!empty($markerData['serial_numbers'])) {
                    // Alte löschen
                    $stmt = $pdo->prepare("DELETE FROM marker_serial_numbers WHERE marker_id = ?");
                    $stmt->execute([$newMarkerId]);
                    
                    // Neue einfügen
                    foreach ($markerData['serial_numbers'] as $sn) {
                        $stmt = $pdo->prepare("INSERT INTO marker_serial_numbers (marker_id, serial_number) VALUES (?, ?)");
                        $stmt->execute([$newMarkerId, $sn]);
                    }
                }
                
                // Custom Fields
                if (!empty($markerData['custom_fields'])) {
                    foreach ($markerData['custom_fields'] as $label => $value) {
                        // Field-ID finden oder erstellen
                        $stmt = $pdo->prepare("SELECT id FROM custom_fields WHERE field_label = ?");
                        $stmt->execute([$label]);
                        $fieldId = $stmt->fetchColumn();
                        
                        if (!$fieldId) {
                            $stmt = $pdo->prepare("INSERT INTO custom_fields (field_label, field_type) VALUES (?, 'text')");
                            $stmt->execute([$label]);
                            $fieldId = $pdo->lastInsertId();
                        }
                        
                        // Wert speichern
                        $stmt = $pdo->prepare("
                            INSERT INTO marker_custom_values (marker_id, field_id, field_value)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)
                        ");
                        $stmt->execute([$newMarkerId, $fieldId, $value]);
                    }
                }
                
                // Bilder
                if (!empty($markerData['images_base64'])) {
                    foreach ($markerData['images_base64'] as $imageData) {
                        $imageContent = base64_decode($imageData['data']);
                        $filename = 'imported_' . uniqid() . '_' . $newMarkerId . '.jpg';
                        $filepath = UPLOAD_DIR . $filename;
                        
                        if (file_put_contents($filepath, $imageContent)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO marker_images (marker_id, image_path, uploaded_by)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$newMarkerId, $filepath, $_SESSION['user_id']]);
                        }
                    }
                }
                
                // Dokumente
                if (!empty($markerData['documents_base64'])) {
                    $docDir = UPLOAD_DIR . 'documents/';
                    if (!is_dir($docDir)) mkdir($docDir, 0755, true);
                    
                    foreach ($markerData['documents_base64'] as $docData) {
                        $docContent = base64_decode($docData['data']);
                        $filename = 'imported_' . uniqid() . '.pdf';
                        $filepath = $docDir . $filename;
                        
                        if (file_put_contents($filepath, $docContent)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO marker_documents (marker_id, document_name, document_path, file_size, uploaded_by)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $newMarkerId,
                                $docData['filename'],
                                $filepath,
                                strlen($docContent),
                                $_SESSION['user_id']
                            ]);
                        }
                    }
                }
            }
            
            // Log
            $stmt = $pdo->prepare("
                INSERT INTO export_import_log (action_type, user_id, filename, marker_count, status)
                VALUES ('import', ?, ?, ?, 'success')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $_FILES['import_file']['name'] ?? 'unknown.json',
                $imported + $updated
            ]);
            
            $pdo->commit();
            
            logActivity('markers_imported', "Import: $imported neu, $updated aktualisiert, $skipped übersprungen");
            
            $message = "Import erfolgreich! $imported neue Marker, $updated aktualisiert, $skipped übersprungen.";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Import-Fehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Datei hochladen und Vorschau
if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
    $jsonContent = file_get_contents($_FILES['import_file']['tmp_name']);
    $importData = json_decode($jsonContent, true);
    
    if (!$importData || !isset($importData['markers'])) {
        $message = 'Ungültige JSON-Datei';
        $messageType = 'danger';
    } else {
        $preview = $importData;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marker importieren - RFID System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-file-import"></i> Marker importieren</h1>
                <div class="header-actions">
                    <a href="markers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>
            
            <?php if (!$preview): ?>
                <!-- Upload-Formular -->
                <div class="info-card" style="max-width: 800px; margin: 0 auto;">
                    <h2><i class="fas fa-upload"></i> JSON-Datei hochladen</h2>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-group">
                            <label for="import_file">JSON-Datei auswählen</label>
                            <input type="file" id="import_file" name="import_file" accept=".json" required>
                            <small>Nur JSON-Dateien vom Export-System</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-eye"></i> Datei analysieren
                        </button>
                    </form>
                    
                    <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin-top: 20px;">
                        <strong><i class="fas fa-info-circle"></i> Hinweis:</strong>
                        <p style="margin: 5px 0 0 0; font-size: 14px;">
                            Laden Sie nur JSON-Dateien hoch, die mit dem Export-System erstellt wurden.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Vorschau und Bestätigung -->
                <div class="info-card">
                    <h2><i class="fas fa-check-circle"></i> Import-Vorschau</h2>
                    
                    <div style="background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin-bottom: 20px;">
                        <strong>Datei erfolgreich analysiert!</strong>
                        <p style="margin: 5px 0 0 0;">
                            Exportiert am: <?= e($preview['export_date']) ?> von <?= e($preview['exported_by']) ?>
                        </p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center;">
                            <div style="font-size: 32px; font-weight: bold; color: #007bff;">
                                <?= count($preview['markers']) ?>
                            </div>
                            <div style="font-size: 14px; color: #6c757d;">Marker gesamt</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center;">
                            <div style="font-size: 32px; font-weight: bold; color: #28a745;">
                                <?php
                                $withImages = 0;
                                foreach ($preview['markers'] as $m) {
                                    if (!empty($m['images_base64'])) $withImages++;
                                }
                                echo $withImages;
                                ?>
                            </div>
                            <div style="font-size: 14px; color: #6c757d;">Mit Bildern</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center;">
                            <div style="font-size: 32px; font-weight: bold; color: #ffc107;">
                                <?php
                                $withDocs = 0;
                                foreach ($preview['markers'] as $m) {
                                    if (!empty($m['documents_base64'])) $withDocs++;
                                }
                                echo $withDocs;
                                ?>
                            </div>
                            <div style="font-size: 14px; color: #6c757d;">Mit Dokumenten</div>
                        </div>
                    </div>
                    
                    <!-- Marker-Liste -->
                    <h3 style="margin-top: 20px;">Marker-Übersicht:</h3>
                    <div style="max-height: 400px; overflow-y: auto; border: 2px solid #dee2e6; border-radius: 5px; padding: 15px; background: white;">
                        <?php foreach ($preview['markers'] as $index => $m): ?>
                            <div style="padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?= e($m['name']) ?></strong>
                                    <?php if (!empty($m['serial_number'])): ?>
                                        <small style="color: #6c757d;"> - SN: <?= e($m['serial_number']) ?></small>
                                    <?php endif; ?>
                                    <br>
                                    <small style="color: #6c757d;">
                                        RFID: <?= e($m['rfid_chip']) ?>
                                        <?php if (!empty($m['category'])): ?>
                                            | <?= e($m['category']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php
                                // Prüfen ob existiert
                                $stmt = $pdo->prepare("SELECT id FROM markers WHERE rfid_chip = ?");
                                $stmt->execute([$m['rfid_chip']]);
                                $exists = $stmt->fetchColumn();
                                ?>
                                <?php if ($exists): ?>
                                    <span class="badge badge-warning">Existiert bereits</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Neu</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Import-Optionen -->
                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="import_confirm" value="1">
                        <input type="hidden" name="import_data" value="<?= htmlspecialchars(json_encode($preview)) ?>">
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; padding: 12px; background: #fff3cd; border-radius: 5px; cursor: pointer;">
                                <input type="checkbox" name="overwrite_existing" value="1" style="margin-right: 10px;">
                                <span>
                                    <i class="fas fa-exclamation-triangle"></i> Existierende Marker überschreiben
                                    <small style="display: block; color: #6c757d; margin-top: 4px;">
                                        Wenn aktiviert, werden vorhandene Marker mit gleicher RFID aktualisiert
                                    </small>
                                </span>
                            </label>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-success" style="flex: 1;">
                                <i class="fas fa-check"></i> Import durchführen
                            </button>
                            <a href="import_markers.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>