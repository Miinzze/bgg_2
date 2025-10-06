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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $operatingHours = $_POST['operating_hours'] ?? 0;
    $fuelLevel = $_POST['fuel_level'] ?? 0;
    $maintenanceInterval = $_POST['maintenance_interval'] ?? 6;
    $lastMaintenance = $_POST['last_maintenance'] ?? null;
    
    // GPS-Position
    $latitude = $_POST['latitude'] ?? $marker['latitude'];
    $longitude = $_POST['longitude'] ?? $marker['longitude'];
    $updateGPS = isset($_POST['update_gps']) && $_POST['update_gps'] == '1';
    
    if (empty($name)) {
        $message = 'Name ist erforderlich';
        $messageType = 'danger';
    } elseif (!validateSerialNumber($serialNumber)) {
        $message = 'Seriennummer darf nur Zahlen enthalten';
        $messageType = 'danger';
    } elseif ($updateGPS && !validateCoordinates($latitude, $longitude)) {
        $message = 'Ungültige GPS-Koordinaten';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            $nextMaintenance = null;
            if (!$marker['is_storage'] && !$marker['is_multi_device'] && $lastMaintenance && $maintenanceInterval > 0) {
                $nextMaintenance = calculateNextMaintenance($lastMaintenance, $maintenanceInterval);
            }
            
            // Wenn GPS aktualisiert wird und Marker noch nicht aktiviert war -> aktivieren
            $isActivated = $marker['is_activated'];
            if ($updateGPS && $latitude && $longitude) {
                $isActivated = 1;
            }
            
            $stmt = $pdo->prepare("
                UPDATE markers SET
                    name = ?,
                    category = ?,
                    serial_number = ?,
                    operating_hours = ?,
                    fuel_level = ?,
                    maintenance_interval_months = ?,
                    last_maintenance = ?,
                    next_maintenance = ?,
                    latitude = ?,
                    longitude = ?,
                    is_activated = ?
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
                $latitude,
                $longitude,
                $isActivated,
                $id
            ]);
            
            // Custom Fields
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
            
            // Öffentliche Dokumente
            if (hasPermission('documents_upload')) {
                $stmt = $pdo->prepare("UPDATE marker_documents SET is_public = 0 WHERE marker_id = ?");
                $stmt->execute([$id]);
                
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
            
            // Neue Bilder
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
            
            // Neue Dokumente
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
            
            if ($updateGPS && !$marker['is_activated'] && $isActivated) {
                $message = 'Marker erfolgreich aktualisiert und aktiviert!';
            } else {
                $message = 'Marker erfolgreich aktualisiert!';
            }
            $messageType = 'success';
            
            $marker = getMarkerById($id, $pdo);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Fehler: ' . e($e->getMessage());
            $messageType = 'danger';
        }
    }
}

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

$settings = getSystemSettings();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marker bearbeiten - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dark-mode.css">
    <link rel="stylesheet" href="css/mobile-features.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .qr-code-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .qr-code-display code {
            font-size: 24px;
            font-weight: bold;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            display: inline-block;
            margin: 10px 0;
        }
        
        .document-item {
            background: var(--bg-secondary);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .document-item.public {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        body.dark-mode .document-item.public {
            background: #1a4d2e;
        }
        
        .public-toggle {
            margin: 10px 0;
            padding: 10px;
            background: var(--card-bg);
            border-radius: 5px;
        }
        
        .image-item {
            position: relative;
            display: inline-block;
        }
        
        .image-item img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--border-color);
        }
        
        .image-item .btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
        }
        
        .gps-section {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .gps-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .gps-coordinates {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        #editMap {
            height: 400px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            margin-top: 15px;
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
            
            <div class="qr-code-display">
                <div><i class="fas fa-qrcode" style="font-size: 36px;"></i></div>
                <strong>QR-Code:</strong>
                <code><?= e($marker['qr_code']) ?></code>
                <div style="margin-top: 15px;">
                    <span style="opacity: 0.9;">
                        <i class="fas fa-info-circle"></i> QR-Codes können nicht geändert werden
                    </span>
                    <a href="print_qr.php?id=<?= $marker['id'] ?>" class="btn btn-light btn-sm" target="_blank" style="margin-left: 15px;">
                        <i class="fas fa-print"></i> QR-Code drucken
                    </a>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="marker-form">
                <?= csrf_field() ?>
                
                <div class="form-section">
                    <h2><i class="fas fa-info-circle"></i> Grunddaten</h2>
                    
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
                
                <!-- GPS-Position Section -->
                <div class="form-section">
                    <h2><i class="fas fa-map-marker-alt"></i> GPS-Position</h2>
                    
                    <?php if ($marker['is_activated'] && $marker['latitude'] && $marker['longitude']): ?>
                        <div class="alert alert-info">
                            <strong><i class="fas fa-check-circle"></i> Aktuelle Position:</strong>
                            <div class="gps-coordinates">
                                <i class="fas fa-map-pin"></i> 
                                <?= number_format($marker['latitude'], 6) ?>, <?= number_format($marker['longitude'], 6) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong><i class="fas fa-exclamation-triangle"></i> Marker nicht aktiviert</strong>
                            <p>Dieser Marker hat noch keine GPS-Position. Erfassen Sie die Position vor Ort!</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="gps-section">
                        <div class="gps-info">
                            <div>
                                <h3 style="margin: 0 0 10px 0;"><i class="fas fa-crosshairs"></i> Position aktualisieren</h3>
                                <p style="color: var(--text-secondary); margin: 0;">
                                    Ändern Sie die Position, wenn das Gerät umgestellt wurde.
                                </p>
                            </div>
                            <button type="button" class="gps-button" onclick="captureGPS()">
                                <i class="fas fa-satellite-dish"></i> GPS erfassen
                            </button>
                        </div>
                        
                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group">
                                <label for="latitude">Breitengrad</label>
                                <input type="number" id="latitude" name="latitude" 
                                       value="<?= $marker['latitude'] ?>" 
                                       step="0.000001" 
                                       placeholder="z.B. 49.995567"
                                       readonly>
                            </div>
                            
                            <div class="form-group">
                                <label for="longitude">Längengrad</label>
                                <input type="number" id="longitude" name="longitude" 
                                       value="<?= $marker['longitude'] ?>" 
                                       step="0.000001" 
                                       placeholder="z.B. 9.073127"
                                       readonly>
                            </div>
                        </div>
                        
                        <input type="hidden" id="update_gps" name="update_gps" value="0">
                        
                        <div id="gps-status" class="gps-status"></div>
                        
                        <!-- Karte zur Positionsauswahl -->
                        <div id="editMap"></div>
                        
                        <small style="color: var(--text-secondary); margin-top: 10px; display: block;">
                            <i class="fas fa-info-circle"></i> Klicken Sie auf die Karte oder nutzen Sie GPS, um die Position zu setzen
                        </small>
                    </div>
                </div>
                
                <?php if (!$marker['is_multi_device'] && !$marker['is_storage']): ?>
                <div class="form-section">
                    <h2><i class="fas fa-cog"></i> Gerätedaten</h2>
                    
                    <div class="form-group">
                        <label for="serial_number">Seriennummer</label>
                        <input type="text" id="serial_number" name="serial_number" 
                               value="<?= e($marker['serial_number']) ?>"
                               pattern="[0-9]+" 
                               inputmode="numeric"
                               title="Bitte nur Zahlen eingeben"
                               placeholder="Nur Zahlen erlaubt">
                        <small>Nur Zahlen erlaubt</small>
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
                    <h2><i class="fas fa-wrench"></i> Wartung</h2>
                    
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
                            
                            <?php $value = $customValues[$field['id']] ?? ''; ?>
                            
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
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="public-toggle">
                                    <label>
                                        <input type="checkbox" 
                                               id="public_<?= $doc['id'] ?>" 
                                               name="public_docs[<?= $doc['id'] ?>]"
                                               value="1"
                                               <?= $doc['is_public'] ? 'checked' : '' ?>
                                               onchange="togglePublicDescription(<?= $doc['id'] ?>)">
                                        <i class="fas fa-globe"></i> <strong>Öffentlich sichtbar</strong>
                                        <small>(erscheint in Public View)</small>
                                    </label>
                                    
                                    <div id="public_desc_<?= $doc['id'] ?>" 
                                         style="display: <?= $doc['is_public'] ? 'block' : 'none' ?>; margin-top: 10px;">
                                        <label for="desc_<?= $doc['id'] ?>">Öffentliche Beschreibung:</label>
                                        <input type="text" 
                                               id="desc_<?= $doc['id'] ?>"
                                               name="public_descriptions[<?= $doc['id'] ?>]"
                                               placeholder="z.B. Bedienungsanleitung"
                                               value="<?= e($doc['public_description'] ?? '') ?>"
                                               style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <h3 style="margin-top: 30px;">Neue Dokumente hochladen</h3>
                    <div class="form-group">
                        <input type="file" id="documents" name="documents[]" multiple accept=".pdf">
                        <small>Nur PDF-Dateien (max. 10MB)</small>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Bilder -->
                <div class="form-section">
                    <h2><i class="fas fa-images"></i> Bilder</h2>
                    
                    <?php if (!empty($images)): ?>
                        <div class="image-gallery" style="margin-bottom: 20px;">
                            <?php foreach ($images as $img): ?>
                                <div class="image-item">
                                    <img src="<?= e($img['image_path']) ?>" alt="Bild">
                                    <button type="button" onclick="deleteImage(<?= $img['id'] ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
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
    
    <script src="js/gps-helper.js"></script>
    
    <script>
        // Karte initialisieren
        const currentLat = <?= $marker['latitude'] ?: $settings['map_default_lat'] ?>;
        const currentLng = <?= $marker['longitude'] ?: $settings['map_default_lng'] ?>;
        
        const editMap = L.map('editMap').setView([currentLat, currentLng], 16);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(editMap);
        
        let marker = L.marker([currentLat, currentLng], { draggable: true }).addTo(editMap);
        
        // Marker-Position bei Drag aktualisieren
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('latitude').value = pos.lat.toFixed(6);
            document.getElementById('longitude').value = pos.lng.toFixed(6);
            document.getElementById('update_gps').value = '1';
        });
        
        // Bei Klick auf Karte Marker verschieben
        editMap.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
            document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
            document.getElementById('update_gps').value = '1';
        });
        
        // GPS-Erfassung
        function captureGPS() {
            const gpsHelper = new GPSHelper();
            const button = document.querySelector('.gps-button');
            const statusDiv = document.getElementById('gps-status');
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Erfasse Position...';
            button.disabled = true;
            
            gpsHelper.getCurrentPosition(
                (position) => {
                    document.getElementById('latitude').value = position.lat.toFixed(6);
                    document.getElementById('longitude').value = position.lng.toFixed(6);
                    document.getElementById('update_gps').value = '1';
                    
                    marker.setLatLng([position.lat, position.lng]);
                    editMap.setView([position.lat, position.lng], 16);
                    
                    statusDiv.innerHTML = `
                        <div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 5px; margin: 10px 0; color: #155724;">
                            <i class="fas fa-check-circle"></i> GPS-Position erfasst!
                            <br><small>Genauigkeit: ${position.accuracy.toFixed(0)}m</small>
                        </div>
                    `;
                    
                    button.innerHTML = '<i class="fas fa-check"></i> Position erfasst';
                    button.style.background = '#28a745';
                    
                    setTimeout(() => {
                        button.innerHTML = '<i class="fas fa-satellite-dish"></i> GPS erfassen';
                        button.style.background = '';
                        button.disabled = false;
                    }, 2000);
                },
                (error) => {
                    statusDiv.innerHTML = `
                        <div style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 5px; margin: 10px 0; color: #721c24;">
                            <i class="fas fa-exclamation-circle"></i> ${error}
                        </div>
                    `;
                    button.innerHTML = '<i class="fas fa-satellite-dish"></i> GPS erfassen';
                    button.disabled = false;
                }
            );
        }
        
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
    </script>
    
    <script src="js/dark-mode.js"></script>
</body>
</html>