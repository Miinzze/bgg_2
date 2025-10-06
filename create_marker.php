<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

// Mobilen Zugriff blockieren
if (isMobileDevice()) {
    header('Location: scan.php?mobile_blocked=1');
    exit;
}

$message = '';
$messageType = '';
$settings = getSystemSettings();

// Verfügbare QR-Codes abrufen
$availableQRCodes = getAvailableQRCodes($pdo, 100);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $qrCode = trim($_POST['qr_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $isStorage = isset($_POST['is_storage']);
    $isMultiDevice = isset($_POST['is_multi_device']);
    
    // GPS ist jetzt OPTIONAL
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
    
    // Validierung
    if (empty($qrCode)) {
        $message = 'Bitte wählen Sie einen QR-Code aus';
        $messageType = 'danger';
    } elseif (!validateQRCode($qrCode)) {
        $message = 'Ungültiges QR-Code-Format';
        $messageType = 'danger';
    } elseif (empty($name) || !validateStringLength($name, 1, 100)) {
        $message = 'Name ist erforderlich und darf maximal 100 Zeichen lang sein';
        $messageType = 'danger';
    } elseif (!empty($category) && !validateStringLength($category, 1, 50)) {
        $message = 'Kategorie darf maximal 50 Zeichen lang sein';
        $messageType = 'danger';
    } elseif ($latitude && $longitude && !validateCoordinates($latitude, $longitude)) {
        $message = 'Ungültige GPS-Koordinaten';
        $messageType = 'danger';
    } else {
        // Prüfen ob QR-Code verfügbar ist
        $stmt = $pdo->prepare("SELECT * FROM qr_code_pool WHERE qr_code = ? AND is_assigned = 0");
        $stmt->execute([$qrCode]);
        
        if (!$stmt->fetch()) {
            $message = 'Dieser QR-Code ist nicht verfügbar oder bereits zugewiesen';
            $messageType = 'warning';
        } else {
            try {
                $pdo->beginTransaction();
                
                $rentalStatus = $isStorage ? null : 'verfuegbar';
                
                // Marker erstellen - GPS kann NULL sein
                $stmt = $pdo->prepare("
                    INSERT INTO markers (qr_code, name, category, is_storage, is_multi_device, rental_status,
                                       latitude, longitude, created_by, is_activated)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Marker ist nur aktiviert wenn GPS vorhanden
                $isActivated = ($latitude && $longitude) ? 1 : 0;
                
                $stmt->execute([
                    $qrCode, $name, $category, 
                    $isStorage ? 1 : 0, 
                    $isMultiDevice ? 1 : 0, 
                    $rentalStatus,
                    $latitude ? floatval($latitude) : null,
                    $longitude ? floatval($longitude) : null,
                    $_SESSION['user_id'],
                    $isActivated
                ]);
                
                $markerId = $pdo->lastInsertId();

                // Public Token generieren
                $publicToken = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE markers SET public_token = ? WHERE id = ?");
                $stmt->execute([$publicToken, $markerId]);
                
                // QR-Code im Pool als zugewiesen markieren
                $stmt = $pdo->prepare("
                    UPDATE qr_code_pool 
                    SET is_assigned = 1, is_activated = ?, marker_id = ?, assigned_at = NOW()
                    WHERE qr_code = ?
                ");
                $stmt->execute([$isActivated, $markerId, $qrCode]);
                
                // Multi-Device Seriennummern
                if ($isMultiDevice) {
                    $serial_numbers = $_POST['serial_numbers'] ?? [];
                    foreach ($serial_numbers as $serial) {
                        $serial = trim($serial);
                        if (!empty($serial)) {
                            if (!validateSerialNumber($serial)) {
                                throw new Exception('Seriennummer darf nur Zahlen enthalten: ' . $serial);
                            }
                            $stmt = $pdo->prepare("INSERT INTO marker_serial_numbers (marker_id, serial_number) VALUES (?, ?)");
                            $stmt->execute([$markerId, $serial]);
                        }
                    }
                } else {
                    // Einzelgerät
                    $serialNumber = trim($_POST['serial_number'] ?? '');
                    $operatingHours = $_POST['operating_hours'] ?? 0;
                    $fuelLevel = $_POST['fuel_level'] ?? 0;
                    $maintenanceInterval = $_POST['maintenance_interval'] ?? 6;
                    $lastMaintenance = $_POST['last_maintenance'] ?? date('Y-m-d');
                    
                    if (!validateSerialNumber($serialNumber)) {
                        throw new Exception('Seriennummer darf nur Zahlen enthalten');
                    }
                    
                    $nextMaintenance = null;
                    if (!$isStorage && $lastMaintenance && $maintenanceInterval > 0) {
                        $nextMaintenance = calculateNextMaintenance($lastMaintenance, $maintenanceInterval);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE markers SET 
                        serial_number = ?,
                        operating_hours = ?,
                        fuel_level = ?,
                        maintenance_interval_months = ?,
                        last_maintenance = ?,
                        next_maintenance = ?
                        WHERE id = ?");
                    $stmt->execute([
                        $serialNumber,
                        floatval($operatingHours),
                        intval($fuelLevel),
                        intval($maintenanceInterval),
                        $lastMaintenance,
                        $nextMaintenance,
                        $markerId
                    ]);
                }
                
                // Bilder hochladen
                if (!empty($_FILES['images']['name'][0])) {
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                        if (!empty($tmpName)) {
                            $file = [
                                'name' => $_FILES['images']['name'][$key],
                                'type' => $_FILES['images']['type'][$key],
                                'tmp_name' => $tmpName,
                                'size' => $_FILES['images']['size'][$key]
                            ];
                            
                            $result = uploadImage($file, $markerId);
                            if ($result['success']) {
                                $stmt = $pdo->prepare("INSERT INTO marker_images (marker_id, image_path) VALUES (?, ?)");
                                $stmt->execute([$markerId, $result['path']]);
                            }
                        }
                    }
                }
                
                // Custom Fields
                if (!empty($_POST['custom_fields'])) {
                    $stmt = $pdo->prepare("INSERT INTO marker_custom_values (marker_id, field_id, field_value) VALUES (?, ?, ?)");
                    foreach ($_POST['custom_fields'] as $fieldId => $value) {
                        if (!empty($value)) {
                            $stmt->execute([$markerId, $fieldId, $value]);
                        }
                    }
                }

                // Dokumente hochladen
                if (hasPermission('documents_upload') && !empty($_FILES['documents']['name'][0])) {
                    foreach ($_FILES['documents']['tmp_name'] as $key => $tmpName) {
                        if (!empty($tmpName)) {
                            $file = [
                                'name' => $_FILES['documents']['name'][$key],
                                'type' => $_FILES['documents']['type'][$key],
                                'tmp_name' => $tmpName,
                                'size' => $_FILES['documents']['size'][$key]
                            ];
                            
                            $result = uploadPDF($file, $markerId);
                            if ($result['success']) {
                                $stmt = $pdo->prepare("INSERT INTO marker_documents (marker_id, document_name, document_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $markerId,
                                    $_FILES['documents']['name'][$key],
                                    $result['path'],
                                    $result['size'],
                                    $_SESSION['user_id']
                                ]);
                            }
                        }
                    }
                }

                $pdo->commit();

                logActivity('marker_created', "Marker '{$name}' erstellt mit QR-Code '{$qrCode}'", $markerId);
                
                if ($isActivated) {
                    $message = 'Marker erfolgreich erstellt und aktiviert!';
                } else {
                    $message = 'Marker erfolgreich erstellt! Der QR-Code muss noch vor Ort gescannt werden, um den Marker zu aktivieren.';
                }
                $messageType = 'success';
                
                header("refresh:3;url=view_marker.php?id=$markerId");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Fehler: ' . e($e->getMessage());
                $messageType = 'danger';
            }
        }
    }
}

$customFields = $pdo->query("SELECT * FROM custom_fields ORDER BY display_order, id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marker erstellen - Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-plus-circle"></i> Neuen Marker erstellen</h1>
                <p>Erstellen Sie einen Marker im Büro - GPS-Position kann später vor Ort erfasst werden</p>
            </div>
            
            <?php if (empty($availableQRCodes)): ?>
                <div class="alert alert-warning">
                    <strong><i class="fas fa-exclamation-triangle"></i> Keine QR-Codes verfügbar!</strong><br>
                    Sie müssen zuerst QR-Codes generieren.<br>
                    <a href="qr_code_generator.php" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-qrcode"></i> QR-Codes generieren
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="marker-form">
                <?= csrf_field() ?>
                
                <div class="form-section">
                    <h2><i class="fas fa-qrcode"></i> QR-Code auswählen</h2>
                    <div class="form-group">
                        <label for="qr_code">QR-Code *</label>
                        <select id="qr_code" name="qr_code" required <?= empty($availableQRCodes) ? 'disabled' : '' ?>>
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($availableQRCodes as $code): ?>
                                <option value="<?= e($code['qr_code']) ?>">
                                    <?= e($code['qr_code']) ?>
                                    <?php if ($code['print_batch']): ?>
                                        (Batch: <?= e($code['print_batch']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Der QR-Code wird dem Gerät zugewiesen und kann später vor Ort aktiviert werden</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2><i class="fas fa-info-circle"></i> Grunddaten</h2>
                    
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" required placeholder="z.B. Generator 5kW">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Kategorie</label>
                        <select id="category" name="category">
                            <option value="">-- Keine --</option>
                            <option value="Generator">Generator</option>
                            <option value="Kompressor">Kompressor</option>
                            <option value="Pumpe">Pumpe</option>
                            <option value="Fahrzeug">Fahrzeug</option>
                            <option value="Werkzeug">Werkzeug</option>
                            <option value="Lager">Lager</option>
                            <option value="Sonstiges">Sonstiges</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="is_multi_device" name="is_multi_device">
                            <strong>Mehrere Geräte an diesem Standort</strong>
                        </label>
                    </div>
                    
                    <div class="form-group" id="storage_checkbox">
                        <label>
                            <input type="checkbox" id="is_storage" name="is_storage">
                            <strong>Lagergerät</strong> (keine Wartung/kein Kraftstoff)
                        </label>
                    </div>
                </div>
                
                <div class="form-section" id="device_data">
                    <h2><i class="fas fa-cog"></i> Gerätedaten</h2>
                    
                    <div id="single_device">
                        <div class="form-group">
                            <label for="serial_number">Seriennummer</label>
                            <input type="text" id="serial_number" name="serial_number" 
                                   pattern="[0-9]+" inputmode="numeric"
                                   placeholder="Nur Zahlen">
                        </div>
                        
                        <div class="form-group" id="operating_hours_group">
                            <label for="operating_hours">Betriebsstunden</label>
                            <input type="number" id="operating_hours" name="operating_hours" 
                                   value="0" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group" id="fuel_group">
                            <label for="fuel_level">Kraftstofffüllstand (%)</label>
                            <input type="range" id="fuel_level" name="fuel_level" 
                                   min="0" max="100" value="100" 
                                   oninput="document.getElementById('fuelValue').textContent = this.value">
                            <div class="fuel-display">
                                <span id="fuelValue">100</span>%
                            </div>
                        </div>
                    </div>
                    
                    <div id="multi_device" style="display: none;">
                        <div id="serial_numbers_list">
                            <div class="form-group">
                                <input type="text" name="serial_numbers[]" 
                                       placeholder="Seriennummer 1" 
                                       class="form-control">
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="add_serial">
                            <i class="fas fa-plus"></i> Weitere Seriennummer
                        </button>
                    </div>
                </div>
                
                <div class="form-section" id="maintenance_section">
                    <h2><i class="fas fa-wrench"></i> Wartung</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="maintenance_interval">Wartungsintervall (Monate)</label>
                            <input type="number" id="maintenance_interval" name="maintenance_interval" 
                                   value="6" min="1">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_maintenance">Letzte Wartung</label>
                            <input type="date" id="last_maintenance" name="last_maintenance" 
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($customFields)): ?>
                <div class="form-section">
                    <h2><i class="fas fa-list"></i> Zusätzliche Informationen</h2>
                    <?php foreach ($customFields as $field): ?>
                        <div class="form-group">
                            <label for="custom_<?= $field['id'] ?>">
                                <?= e($field['field_label']) ?>
                                <?php if ($field['required']): ?><span style="color: red;">*</span><?php endif; ?>
                            </label>
                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <textarea id="custom_<?= $field['id'] ?>" 
                                        name="custom_fields[<?= $field['id'] ?>]" 
                                        rows="4" <?= $field['required'] ? 'required' : '' ?>></textarea>
                            <?php elseif ($field['field_type'] === 'number'): ?>
                                <input type="number" id="custom_<?= $field['id'] ?>" 
                                       name="custom_fields[<?= $field['id'] ?>]" 
                                       step="any" <?= $field['required'] ? 'required' : '' ?>>
                            <?php elseif ($field['field_type'] === 'date'): ?>
                                <input type="date" id="custom_<?= $field['id'] ?>" 
                                       name="custom_fields[<?= $field['id'] ?>]" 
                                       <?= $field['required'] ? 'required' : '' ?>>
                            <?php else: ?>
                                <input type="text" id="custom_<?= $field['id'] ?>" 
                                       name="custom_fields[<?= $field['id'] ?>]" 
                                       <?= $field['required'] ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <h2><i class="fas fa-map-marker-alt"></i> Standort (optional)</h2>
                    <p class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Der Standort kann später beim Scannen des QR-Codes vor Ort automatisch erfasst werden.
                    </p>
                    
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    
                    <div id="createMap" style="height: 300px; border-radius: 8px; margin-top: 15px;"></div>
                    <p style="margin-top: 10px; font-size: 14px; color: #666;">
                        Klicken Sie auf die Karte um einen Standort festzulegen (optional)
                    </p>
                </div>
                
                <div class="form-section">
                    <h2><i class="fas fa-images"></i> Bilder</h2>
                    <div class="form-group">
                        <input type="file" id="images" name="images[]" multiple accept="image/*">
                    </div>
                    <div id="imagePreview"></div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large" <?= empty($availableQRCodes) ? 'disabled' : '' ?>>
                        <i class="fas fa-save"></i> Marker erstellen
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
    const MAP_LAT = <?= $settings['map_default_lat'] ?>;
    const MAP_LNG = <?= $settings['map_default_lng'] ?>;
    const MAP_ZOOM = <?= $settings['map_default_zoom'] ?>;
    
    const map = L.map('createMap').setView([MAP_LAT, MAP_LNG], MAP_ZOOM);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);
    
    let marker = null;
    
    map.on('click', function(e) {
        document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
        
        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng).addTo(map);
        }
    });
    
    // Multi-Device Toggle
    const multiDeviceCheckbox = document.getElementById('is_multi_device');
    const storageCheckbox = document.getElementById('is_storage');
    
    function updateFormFields() {
        const isMulti = multiDeviceCheckbox.checked;
        const isStorage = storageCheckbox.checked;
        
        document.getElementById('single_device').style.display = isMulti ? 'none' : 'block';
        document.getElementById('multi_device').style.display = isMulti ? 'block' : 'none';
        document.getElementById('storage_checkbox').style.display = isMulti ? 'none' : 'block';
        document.getElementById('maintenance_section').style.display = (isMulti || isStorage) ? 'none' : 'block';
        document.getElementById('fuel_group').style.display = isStorage ? 'none' : 'block';
        document.getElementById('operating_hours_group').style.display = isStorage ? 'none' : 'block';
    }
    
    multiDeviceCheckbox.addEventListener('change', updateFormFields);
    storageCheckbox.addEventListener('change', updateFormFields);
    
    // Seriennummern hinzufügen
    let serialCount = 1;
    document.getElementById('add_serial').addEventListener('click', function() {
        serialCount++;
        const div = document.createElement('div');
        div.className = 'form-group';
        div.innerHTML = `
            <div style="display: flex; gap: 10px;">
                <input type="text" name="serial_numbers[]" placeholder="Seriennummer ${serialCount}" class="form-control" style="flex: 1;">
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        document.getElementById('serial_numbers_list').appendChild(div);
    });
    
    // Image Preview
    document.getElementById('images').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        Array.from(e.target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '150px';
                img.style.margin = '10px';
                img.style.borderRadius = '8px';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });
    
    setTimeout(() => map.invalidateSize(), 200);
    </script>
</body>
</html>