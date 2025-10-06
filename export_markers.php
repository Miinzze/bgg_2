<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('markers_edit');

$message = '';
$messageType = '';

// Export durchführen
if (isset($_POST['export'])) {
    $exportType = $_POST['export_type'] ?? 'all';
    $includeImages = isset($_POST['include_images']);
    $includeDocuments = isset($_POST['include_documents']);
    $selectedIds = $_POST['selected_markers'] ?? [];
    
    try {
        $markers = [];
        
        if ($exportType === 'selected' && !empty($selectedIds)) {
            $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
            $stmt = $pdo->prepare("SELECT * FROM markers WHERE id IN ($placeholders)");
            $stmt->execute($selectedIds);
            $markers = $stmt->fetchAll();
        } else {
            $stmt = $pdo->query("SELECT * FROM markers ORDER BY created_at DESC");
            $markers = $stmt->fetchAll();
        }
        
        $exportData = [
            'export_date' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['username'],
            'version' => '1.0',
            'marker_count' => count($markers),
            'markers' => []
        ];
        
        foreach ($markers as $marker) {
            $markerData = $marker;
            
            // Bilder hinzufügen
            if ($includeImages) {
                $stmt = $pdo->prepare("SELECT image_path FROM marker_images WHERE marker_id = ?");
                $stmt->execute([$marker['id']]);
                $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $markerData['images_base64'] = [];
                foreach ($images as $imagePath) {
                    if (file_exists($imagePath)) {
                        $imageData = file_get_contents($imagePath);
                        $markerData['images_base64'][] = [
                            'filename' => basename($imagePath),
                            'data' => base64_encode($imageData),
                            'mime' => mime_content_type($imagePath)
                        ];
                    }
                }
            }
            
            // Dokumente hinzufügen
            if ($includeDocuments) {
                $stmt = $pdo->prepare("SELECT document_path, document_name FROM marker_documents WHERE marker_id = ?");
                $stmt->execute([$marker['id']]);
                $documents = $stmt->fetchAll();
                
                $markerData['documents_base64'] = [];
                foreach ($documents as $doc) {
                    if (file_exists($doc['document_path'])) {
                        $docData = file_get_contents($doc['document_path']);
                        $markerData['documents_base64'][] = [
                            'filename' => $doc['document_name'],
                            'data' => base64_encode($docData),
                            'mime' => 'application/pdf'
                        ];
                    }
                }
            }
            
            // Seriennummern bei Multi-Device
            if ($marker['is_multi_device']) {
                $stmt = $pdo->prepare("SELECT serial_number FROM marker_serial_numbers WHERE marker_id = ?");
                $stmt->execute([$marker['id']]);
                $markerData['serial_numbers'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Custom Fields
            $stmt = $pdo->prepare("
                SELECT cf.field_label, mcv.field_value
                FROM marker_custom_values mcv
                JOIN custom_fields cf ON mcv.field_id = cf.id
                WHERE mcv.marker_id = ?
            ");
            $stmt->execute([$marker['id']]);
            $markerData['custom_fields'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $exportData['markers'][] = $markerData;
        }
        
        // Log in Datenbank
        $stmt = $pdo->prepare("
            INSERT INTO export_import_log (action_type, user_id, filename, marker_count, status)
            VALUES ('export', ?, ?, ?, 'success')
        ");
        $filename = 'rfid_export_' . date('Y-m-d_His') . '.json';
        $stmt->execute([$_SESSION['user_id'], $filename, count($markers)]);
        
        logActivity('markers_exported', count($markers) . ' Marker exportiert');
        
        // JSON Download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        $message = 'Export-Fehler: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Alle Marker für Auswahl laden
$stmt = $pdo->query("SELECT id, name, category, serial_number FROM markers ORDER BY name ASC");
$allMarkers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marker exportieren - RFID System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-file-export"></i> Marker exportieren</h1>
                <div class="header-actions">
                    <a href="markers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Export-Formular -->
                <div class="info-card">
                    <h2><i class="fas fa-download"></i> Export-Optionen</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="export" value="1">
                        
                        <div class="form-group">
                            <label>Export-Umfang</label>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <label style="display: flex; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 6px; cursor: pointer;">
                                    <input type="radio" name="export_type" value="all" checked style="margin-right: 10px;">
                                    <span>
                                        <i class="fas fa-list"></i> Alle Marker exportieren
                                        <small style="display: block; color: #6c757d; margin-top: 4px;">
                                            <?= count($allMarkers) ?> Marker
                                        </small>
                                    </span>
                                </label>
                                
                                <label style="display: flex; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 6px; cursor: pointer;">
                                    <input type="radio" name="export_type" value="selected" onclick="document.getElementById('markerSelection').style.display='block'" style="margin-right: 10px;">
                                    <span>
                                        <i class="fas fa-check-square"></i> Ausgewählte Marker
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <div id="markerSelection" style="display: none; margin-top: 15px; max-height: 300px; overflow-y: auto; border: 2px solid #dee2e6; border-radius: 5px; padding: 15px;">
                            <div style="margin-bottom: 10px;">
                                <button type="button" onclick="selectAll()" class="btn btn-sm btn-secondary">Alle auswählen</button>
                                <button type="button" onclick="deselectAll()" class="btn btn-sm btn-secondary">Keine</button>
                            </div>
                            <?php foreach ($allMarkers as $m): ?>
                                <label style="display: block; padding: 8px; border-bottom: 1px solid #eee; cursor: pointer;">
                                    <input type="checkbox" name="selected_markers[]" value="<?= $m['id'] ?>" style="margin-right: 8px;">
                                    <?= e($m['name']) ?>
                                    <?php if ($m['serial_number']): ?>
                                        <small style="color: #6c757d;"> (SN: <?= e($m['serial_number']) ?>)</small>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-group" style="margin-top: 20px;">
                            <label>Zusätzliche Daten</label>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <label style="display: flex; align-items: center; padding: 10px; background: #e7f3ff; border-radius: 5px; cursor: pointer;">
                                    <input type="checkbox" name="include_images" value="1" style="margin-right: 10px;">
                                    <span>
                                        <i class="fas fa-images"></i> Bilder einschließen
                                        <small style="display: block; color: #6c757d;">Erhöht die Dateigröße erheblich</small>
                                    </span>
                                </label>
                                
                                <label style="display: flex; align-items: center; padding: 10px; background: #fff3cd; border-radius: 5px; cursor: pointer;">
                                    <input type="checkbox" name="include_documents" value="1" style="margin-right: 10px;">
                                    <span>
                                        <i class="fas fa-file-pdf"></i> PDF-Dokumente einschließen
                                        <small style="display: block; color: #6c757d;">Kann zu sehr großen Dateien führen</small>
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-block" style="margin-top: 20px;">
                            <i class="fas fa-download"></i> Export als JSON herunterladen
                        </button>
                    </form>
                </div>
                
                <!-- Info -->
                <div class="info-card">
                    <h2><i class="fas fa-info-circle"></i> Export-Informationen</h2>
                    
                    <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin-bottom: 15px;">
                        <h4 style="margin-top: 0;">JSON-Format</h4>
                        <p style="margin-bottom: 0; font-size: 14px;">
                            Die Marker werden als JSON-Datei exportiert. Diese kann später wieder importiert werden.
                        </p>
                    </div>
                    
                    <h3 style="font-size: 16px; margin-top: 20px;">Exportierte Daten:</h3>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <i class="fas fa-check" style="color: #28a745; margin-right: 8px;"></i>
                            Marker-Grunddaten
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <i class="fas fa-check" style="color: #28a745; margin-right: 8px;"></i>
                            GPS-Koordinaten
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <i class="fas fa-check" style="color: #28a745; margin-right: 8px;"></i>
                            Wartungsinformationen
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <i class="fas fa-check" style="color: #28a745; margin-right: 8px;"></i>
                            Custom Fields
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <i class="fas fa-check" style="color: #28a745; margin-right: 8px;"></i>
                            Seriennummern (Multi-Device)
                        </li>
                        <li style="padding: 8px 0;">
                            <i class="fas fa-times" style="color: #dc3545; margin-right: 8px;"></i>
                            Wartungshistorie <small>(kann später hinzugefügt werden)</small>
                        </li>
                    </ul>
                    
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin-top: 20px;">
                        <strong><i class="fas fa-exclamation-triangle"></i> Hinweis:</strong>
                        <p style="margin: 5px 0 0 0; font-size: 14px;">
                            Bilder und Dokumente werden Base64-kodiert eingebettet. 
                            Dies kann zu sehr großen Dateien führen!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    function selectAll() {
        document.querySelectorAll('#markerSelection input[type="checkbox"]').forEach(cb => cb.checked = true);
    }
    
    function deselectAll() {
        document.querySelectorAll('#markerSelection input[type="checkbox"]').forEach(cb => cb.checked = false);
    }
    </script>
</body>
</html>