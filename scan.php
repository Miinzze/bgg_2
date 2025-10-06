<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $rfid = trim($_POST['rfid_chip'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $isStorage = isset($_POST['is_storage']);
    $isMultiDevice = isset($_POST['is_multi_device']);
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    // ===== INPUT VALIDIERUNG =====
    
    // RFID validieren
    if (empty($rfid)) {
        $message = 'RFID-Chip ID ist erforderlich';
        $messageType = 'danger';
    } elseif (!validateRFID($rfid)) {
        $message = 'Ungültiges RFID-Format (mindestens 8 Zeichen)';
        $messageType = 'danger';
    }
    // Name validieren
    elseif (empty($name) || !validateStringLength($name, 1, 100)) {
        $message = 'Name ist erforderlich und darf maximal 100 Zeichen lang sein';
        $messageType = 'danger';
    }
    // Kategorie validieren
    elseif (!empty($category) && !validateStringLength($category, 1, 50)) {
        $message = 'Kategorie darf maximal 50 Zeichen lang sein';
        $messageType = 'danger';
    }
    // Koordinaten validieren
    elseif (!$latitude || !$longitude) {
        $message = 'Bitte Standort auf Karte wählen';
        $messageType = 'danger';
    } elseif (!validateCoordinates($latitude, $longitude)) {
        $message = 'Ungültige GPS-Koordinaten';
        $messageType = 'danger';
    } else {
        // Prüfen ob RFID bereits existiert
        $stmt = $pdo->prepare("SELECT id FROM markers WHERE rfid_chip = ?");
        $stmt->execute([$rfid]);
        
        if ($stmt->fetch()) {
            $message = 'Dieser RFID-Chip ist bereits registriert';
            $messageType = 'warning';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Marker erstellen
                $rentalStatus = $isStorage ? null : 'verfuegbar';
                
                $stmt = $pdo->prepare("
                    INSERT INTO markers (rfid_chip, name, category, is_storage, is_multi_device, rental_status,
                                       latitude, longitude, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $rfid, $name, $category, $isStorage ? 1 : 0, $isMultiDevice ? 1 : 0, $rentalStatus,
                    floatval($latitude), floatval($longitude), $_SESSION['user_id']
                ]);
                
                $markerId = $pdo->lastInsertId();

                // Public Token generieren
                $publicToken = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE markers SET public_token = ? WHERE id = ?");
                $stmt->execute([$publicToken, $markerId]);
                
                // Bei Multi-Device: Mehrere Seriennummern speichern
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
                    // Einzelgerät: Normale Felder
                    $serialNumber = trim($_POST['serial_number'] ?? '');
                    $operatingHours = $_POST['operating_hours'] ?? 0;
                    $fuelLevel = $_POST['fuel_level'] ?? 0;
                    $maintenanceInterval = $_POST['maintenance_interval'] ?? 6;
                    $lastMaintenance = $_POST['last_maintenance'] ?? date('Y-m-d');
                    
                    // Validierung der Einzelgerät-Felder
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
                    if (!validateDate($lastMaintenance)) {
                        throw new Exception('Ungültiges Datum für letzte Wartung');
                    }
                    
                    // Nächste Wartung berechnen
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
                
                // Bilder hochladen (bereits validiert in uploadImage-Funktion)
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
                
                // Custom Fields speichern
                if (!empty($_POST['custom_fields'])) {
                    $stmt = $pdo->prepare("INSERT INTO marker_custom_values (marker_id, field_id, field_value) VALUES (?, ?, ?)");
                    foreach ($_POST['custom_fields'] as $fieldId => $value) {
                        if (!empty($value)) {
                            $stmt->execute([$markerId, $fieldId, $value]);
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

                // Activity Log
                logActivity('marker_created', "Marker '{$name}' erstellt", $markerId);
                $message = 'Marker erfolgreich erstellt!';
                $messageType = 'success';
                
                header("refresh:2;url=view_marker.php?id=$markerId");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Fehler: ' . e($e->getMessage());
                $messageType = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Scannen - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/mobile-features.css">
    <script src="js/gps-helper.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .serial-number-group {
            margin-bottom: 10px;
        }
        .serial-numbers-container {
            display: none;
        }
        .disabled-field {
            background-color: #e9ecef !important;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>RFID-Chip scannen</h1>
                <p>Scannen Sie den RFID-Chip und erfassen Sie die Gerätedaten</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['mobile_blocked'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Mobiler Modus:</strong> Das manuelle Erstellen von Markern ist nur auf Desktop-Geräten verfügbar. 
                    Bitte nutzen Sie die RFID-Scan-Funktion oder das erneute Scannen.
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="marker-form" id="scanForm">
                <div class="form-section">
                    <h2>RFID & Standort</h2>
                    
                    <div class="form-group">
                        <label for="rfid_chip">RFID-Chip ID *</label>
                        <input type="text" id="rfid_chip" name="rfid_chip" required 
                            placeholder="Chip scannen oder ID eingeben">
                        <small>Format: mindestens 8 alphanumerische Zeichen</small>
                    </div>
                    
                    <!-- GPS Auto-Erfassung -->
                    <div style="margin: 20px 0;">
                        <button type="button" id="gpsButton" class="gps-button" onclick="getGPSPosition()">
                            <i class="fas fa-crosshairs"></i> GPS-Position automatisch erfassen
                        </button>
                        <div id="gpsStatus"></div>
                    </div>
                    
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    
                    <div id="miniMap" style="height: 300px; margin-top: 15px; border-radius: 8px; border: 2px solid #dee2e6; display: none;"></div>
                </div>
                
                <div class="form-section">
                    <h2>Geräteinformationen</h2>
                    
                    <div class="form-group">
                        <label for="name">Name des Markers *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Kategorie</label>
                        <select id="category" name="category">
                            <option value="Generator">Generator</option>
                            <option value="Kompressor">Kompressor</option>
                            <option value="Pumpe">Pumpe</option>
                            <option value="Fahrzeug">Fahrzeug</option>
                            <option value="Werkzeug">Werkzeug</option>
                            <option value="Lager">Lager</option>
                            <option value="Sonstiges">Sonstiges</option>
                        </select>
                    </div>
                    
                    <!-- Multi-Device Checkbox -->
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_multi_device" name="is_multi_device">
                            <label for="is_multi_device">
                                <strong>Mehrere Geräte an diesem Standort</strong>
                                <br><small>Wenn mehrere Geräte am selben Ort stehen</small>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Lagergerät Checkbox -->
                    <div class="form-group" id="storage_checkbox_container">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_storage" name="is_storage">
                            <label for="is_storage">
                                <strong>Lagergerät</strong>
                                <br><small>Keine Wartung/kein Kraftstoff erforderlich</small>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Einzelne Seriennummer (Standard) -->
                    <div class="form-group" id="single_serial_container">
                        <label for="serial_number">Seriennummer</label>
                        <input type="text" id="serial_number" name="serial_number" 
                               pattern="[0-9]+" 
                               inputmode="numeric"
                               title="Bitte nur Zahlen eingeben"
                               placeholder="z.B. 123456">
                        <small>Nur Zahlen erlaubt</small>
                    </div>
                    
                    <!-- Mehrere Seriennummern (Multi-Device) -->
                    <div class="serial-numbers-container" id="multi_serial_container">
                        <label class="form-label">Seriennummern</label>
                        <div id="serial_numbers_list">
                            <div class="serial-number-group">
                                <input type="text" class="form-control" name="serial_numbers[]" 
                                       placeholder="Seriennummer 1" style="width: 100%; padding: 10px; border: 2px solid #dee2e6; border-radius: 5px; margin-bottom: 10px;">
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="add_serial">
                            + Weitere Seriennummer hinzufügen
                        </button>
                    </div>
                    
                    <div class="form-group" id="operating_hours_group">
                        <label for="operating_hours">Betriebsstunden</label>
                        <input type="number" id="operating_hours" name="operating_hours" 
                               value="0" step="0.01" min="0">
                    </div>
                    
                    <div class="form-group" id="fuel_group">
                        <label for="fuel_level">Kraftstofffüllstand (%)</label>
                        <input type="range" id="fuel_level" name="fuel_level" 
                               min="0" max="100" value="100" oninput="updateFuelDisplay(this.value)">
                        <div class="fuel-display">
                            <span id="fuelValue">100</span>%
                        </div>
                    </div>
                </div>

                <!-- Custom Fields -->
                <?php
                $customFields = $pdo->query("SELECT * FROM custom_fields ORDER BY display_order, id")->fetchAll();
                if (!empty($customFields)):
                ?>
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
                            
                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <textarea id="custom_<?= $field['id'] ?>" 
                                        name="custom_fields[<?= $field['id'] ?>]" 
                                        rows="4"
                                        <?= $field['required'] ? 'required' : '' ?>></textarea>
                            
                            <?php elseif ($field['field_type'] === 'number'): ?>
                                <input type="number" 
                                    id="custom_<?= $field['id'] ?>" 
                                    name="custom_fields[<?= $field['id'] ?>]"
                                    step="any"
                                    <?= $field['required'] ? 'required' : '' ?>>
                            
                            <?php elseif ($field['field_type'] === 'date'): ?>
                                <input type="date" 
                                    id="custom_<?= $field['id'] ?>" 
                                    name="custom_fields[<?= $field['id'] ?>]"
                                    <?= $field['required'] ? 'required' : '' ?>>
                            
                            <?php else: ?>
                                <input type="text" 
                                    id="custom_<?= $field['id'] ?>" 
                                    name="custom_fields[<?= $field['id'] ?>]"
                                    <?= $field['required'] ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-section" id="maintenance_section">
                    <h2>Wartung</h2>
                    
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

                <!-- PDF Dokumente -->
                <?php if (hasPermission('documents_upload')): ?>
                <div class="form-section">
                    <h2><i class="fas fa-file-pdf"></i> PDF-Dokumente</h2>
                    
                    <div class="form-group">
                        <label for="documents">PDF-Dokumente hochladen (mehrere möglich)</label>
                        <input type="file" id="documents" name="documents[]" multiple accept=".pdf,application/pdf">
                        <small>Nur PDF-Dateien (max. 10MB pro Datei)</small>
                    </div>
                    
                    <div id="pdfPreview" style="margin-top: 10px;"></div>
                </div>
                <?php endif; ?>
                
                <!-- Bilder -->
                <div class="form-section">
                    <h2><i class="fas fa-camera"></i> Bilder</h2>
                    
                    <!-- Kamera-Upload für Mobile -->
                    <div class="form-group">
                        <button type="button" id="openCamera" class="btn btn-info btn-block" style="margin-bottom: 15px;">
                            <i class="fas fa-camera"></i> Foto mit Kamera aufnehmen
                        </button>
                    </div>
                    
                    <!-- Kamera-Container (versteckt) -->
                    <div id="cameraContainer" style="display: none; margin-bottom: 20px;">
                        <video id="cameraVideo" autoplay playsinline style="width: 100%; max-width: 500px; border-radius: 8px; background: #000;"></video>
                        <div style="margin-top: 10px; display: flex; gap: 10px; justify-content: center;">
                            <button type="button" id="capturePhoto" class="btn btn-success">
                                <i class="fas fa-camera"></i> Foto aufnehmen
                            </button>
                            <button type="button" id="closeCamera" class="btn btn-danger">
                                <i class="fas fa-times"></i> Kamera schließen
                            </button>
                        </div>
                    </div>
                    
                    <canvas id="photoCanvas" style="display: none;"></canvas>
                    
                    <!-- Normale Datei-Upload -->
                    <div class="form-group">
                        <label for="images">Bilder hochladen (mehrere möglich)</label>
                        <input type="file" id="images" name="images[]" multiple accept="image/*" capture="environment">
                        <small>Erlaubt: JPG, PNG, GIF, WebP (max. 5MB pro Bild)</small>
                    </div>
                    
                    <div id="imagePreview" class="image-preview"></div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">Marker erstellen</button>
                    <a href="index.php" class="btn btn-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        let miniMap = null;
        let marker = null;
        
                document.getElementById('documents').addEventListener('change', function(e) {
                    const previewDiv = document.getElementById('pdfPreview');
                    previewDiv.innerHTML = '';
                    
                    Array.from(e.target.files).forEach(file => {
                        const item = document.createElement('div');
                        item.style.cssText = 'padding: 10px; background: #f8f9fa; margin: 5px 0; border-radius: 5px; display: flex; align-items: center; gap: 10px;';
                        item.innerHTML = `
                            <i class="fas fa-file-pdf" style="color: #dc3545; font-size: 24px;"></i>
                            <div style="flex: 1;">
                                <strong>${file.name}</strong><br>
                                <small>${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                            </div>
                        `;
                        previewDiv.appendChild(item);
                    });
                });

        // GPS-Helper initialisieren
        const gpsHelper = new GPSHelper();
        let miniMap = null;
        let marker = null;

        // GPS Position abrufen
        function getGPSPosition() {
            const button = document.getElementById('gpsButton');
            
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> GPS wird abgerufen...';
            
            gpsHelper.getCurrentPosition(
                (position) => {
                    const lat = position.lat;
                    const lng = position.lng;
                    
                    document.getElementById('latitude').value = lat.toFixed(6);
                    document.getElementById('longitude').value = lng.toFixed(6);
                    
                    // Mini-Karte anzeigen
                    const mapDiv = document.getElementById('miniMap');
                    mapDiv.style.display = 'block';
                    
                    if (!miniMap) {
                        miniMap = L.map('miniMap').setView([lat, lng], 15);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap contributors'
                        }).addTo(miniMap);
                        marker = L.marker([lat, lng]).addTo(miniMap);
                    } else {
                        miniMap.setView([lat, lng], 15);
                        marker.setLatLng([lat, lng]);
                    }
                    
                    button.disabled = false;
                    button.classList.add('active');
                    button.innerHTML = '<i class="fas fa-check"></i> GPS-Position erfasst';
                    
                    gpsHelper.showStatus('gpsStatus', 
                        `Position erfasst mit ${Math.round(position.accuracy)}m Genauigkeit`, 
                        'success'
                    );
                    
                    setTimeout(() => {
                        button.classList.remove('active');
                        button.innerHTML = '<i class="fas fa-crosshairs"></i> GPS-Position erneut erfassen';
                    }, 3000);
                },
                (error) => {
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-crosshairs"></i> GPS-Position automatisch erfassen';
                    gpsHelper.showStatus('gpsStatus', error, 'error');
                }
            );
        }
        
        // Multi-Device und Storage Logic
        const multiDeviceCheckbox = document.getElementById('is_multi_device');
        const storageCheckbox = document.getElementById('is_storage');
        const singleSerialContainer = document.getElementById('single_serial_container');
        const multiSerialContainer = document.getElementById('multi_serial_container');
        const fuelGroup = document.getElementById('fuel_group');
        const maintenanceSection = document.getElementById('maintenance_section');
        const operatingHoursGroup = document.getElementById('operating_hours_group');
        const storageCheckboxContainer = document.getElementById('storage_checkbox_container');
        
        const fuelLevelInput = document.getElementById('fuel_level');
        const maintenanceIntervalInput = document.getElementById('maintenance_interval');
        const lastMaintenanceInput = document.getElementById('last_maintenance');
        const singleSerialInput = document.getElementById('serial_number');
        const operatingHoursInput = document.getElementById('operating_hours');
        
        function updateFormFields() {
            const isMultiDevice = multiDeviceCheckbox.checked;
            const isStorage = storageCheckbox.checked;
            
            if (isMultiDevice) {
                // Multi-Device aktiviert
                singleSerialContainer.style.display = 'none';
                multiSerialContainer.style.display = 'block';
                fuelGroup.style.display = 'none';
                maintenanceSection.style.display = 'none';
                operatingHoursGroup.style.display = 'none';
                storageCheckboxContainer.style.display = 'none';
                
                // Felder deaktivieren
                singleSerialInput.disabled = true;
                fuelLevelInput.disabled = true;
                maintenanceIntervalInput.disabled = true;
                lastMaintenanceInput.disabled = true;
                operatingHoursInput.disabled = true;
                storageCheckbox.disabled = true;
                storageCheckbox.checked = false;
            } else {
                // Einzelgerät
                singleSerialContainer.style.display = 'block';
                multiSerialContainer.style.display = 'none';
                storageCheckboxContainer.style.display = 'block';
                
                singleSerialInput.disabled = false;
                storageCheckbox.disabled = false;
                
                if (isStorage) {
                    // Lagergerät
                    fuelGroup.style.display = 'none';
                    maintenanceSection.style.display = 'none';
                    operatingHoursGroup.style.display = 'none';
                    
                    fuelLevelInput.disabled = true;
                    maintenanceIntervalInput.disabled = true;
                    lastMaintenanceInput.disabled = true;
                    operatingHoursInput.disabled = true;
                } else {
                    // Normales Einzelgerät
                    fuelGroup.style.display = 'block';
                    maintenanceSection.style.display = 'block';
                    operatingHoursGroup.style.display = 'block';
                    
                    fuelLevelInput.disabled = false;
                    maintenanceIntervalInput.disabled = false;
                    lastMaintenanceInput.disabled = false;
                    operatingHoursInput.disabled = false;
                }
            }
        }
        
        multiDeviceCheckbox.addEventListener('change', updateFormFields);
        storageCheckbox.addEventListener('change', updateFormFields);
        
        // Weitere Seriennummer hinzufügen
        let serialCount = 1;
        document.getElementById('add_serial').addEventListener('click', function() {
            serialCount++;
            const newSerial = document.createElement('div');
            newSerial.className = 'serial-number-group';
            newSerial.innerHTML = `
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <input type="text" class="form-control" name="serial_numbers[]" 
                           placeholder="Seriennummer ${serialCount}"
                           style="flex: 1; padding: 10px; border: 2px solid #dee2e6; border-radius: 5px;">
                    <button type="button" class="btn btn-danger remove-serial" style="padding: 10px 15px;">×</button>
                </div>
            `;
            document.getElementById('serial_numbers_list').appendChild(newSerial);
        });
        
        // Seriennummer entfernen
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-serial')) {
                e.target.closest('.serial-number-group').remove();
            }
        });
        
        // Kraftstoffanzeige aktualisieren
        function updateFuelDisplay(value) {
            document.getElementById('fuelValue').textContent = value;
        }
        
        // ===== KAMERA-FUNKTIONALITÄT =====
        let cameraStream = null;

        document.getElementById('openCamera').addEventListener('click', async function() {
            try {
                const constraints = {
                    video: {
                        facingMode: 'environment', // Rückkamera bevorzugen
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    }
                };
                
                cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
                document.getElementById('cameraVideo').srcObject = cameraStream;
                document.getElementById('cameraContainer').style.display = 'block';
                this.style.display = 'none';
            } catch (error) {
                alert('Kamera-Zugriff fehlgeschlagen: ' + error.message + '\n\nStellen Sie sicher, dass Sie den Kamera-Zugriff erlaubt haben.');
            }
        });

        document.getElementById('closeCamera').addEventListener('click', function() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            document.getElementById('cameraContainer').style.display = 'none';
            document.getElementById('openCamera').style.display = 'block';
        });

        document.getElementById('capturePhoto').addEventListener('click', function() {
            const video = document.getElementById('cameraVideo');
            const canvas = document.getElementById('photoCanvas');
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);
            
            canvas.toBlob(function(blob) {
                const file = new File([blob], 'camera_photo_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                
                // Bestehende Dateien beibehalten
                const existingFiles = document.getElementById('images').files;
                for (let i = 0; i < existingFiles.length; i++) {
                    dataTransfer.items.add(existingFiles[i]);
                }
                
                // Neues Foto hinzufügen
                dataTransfer.items.add(file);
                document.getElementById('images').files = dataTransfer.files;
                
                // Preview aktualisieren
                updateImagePreview();
                
                alert('✓ Foto aufgenommen und zur Upload-Liste hinzugefügt!');
            }, 'image/jpeg', 0.85);
        });

        function updateImagePreview() {
            const previewDiv = document.getElementById('imagePreview');
            previewDiv.innerHTML = '';
            
            const files = document.getElementById('images').files;
            Array.from(files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '150px';
                    img.style.margin = '10px';
                    img.style.borderRadius = '8px';
                    img.style.border = '2px solid #dee2e6';
                    previewDiv.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        }

        // Event Listener für normalen Upload
        document.getElementById('images').addEventListener('change', updateImagePreview);

        // Kamera schließen beim Verlassen der Seite
        window.addEventListener('beforeunload', function() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
            }
        });
    
        // Bildvorschau
        document.getElementById('images').addEventListener('change', function(e) {
            const previewDiv = document.getElementById('imagePreview');
            previewDiv.innerHTML = '';
            
            Array.from(e.target.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '150px';
                    img.style.margin = '10px';
                    previewDiv.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });
        
        // Initiale Anzeige
        updateFormFields();
        
        // NFC-Unterstützung prüfen (experimentell)
        if ('NDEFReader' in window) {
            const rfidInput = document.getElementById('rfid_chip');
            const scanBtn = document.createElement('button');
            scanBtn.type = 'button';
            scanBtn.className = 'btn btn-secondary btn-sm';
            scanBtn.textContent = 'NFC Scannen';
            scanBtn.style.marginLeft = '10px';
            
            rfidInput.parentElement.appendChild(scanBtn);
            
            scanBtn.addEventListener('click', async function() {
                try {
                    const ndef = new NDEFReader();
                    await ndef.scan();
                    scanBtn.textContent = 'Bereit zum Scannen...';
                    
                    ndef.addEventListener('reading', ({ serialNumber }) => {
                        rfidInput.value = serialNumber;
                        scanBtn.textContent = 'NFC Scannen';
                    });
                } catch (error) {
                    alert('NFC-Scan fehlgeschlagen: ' + error);
                }
            });
        }
    </script>
</body>
</html>