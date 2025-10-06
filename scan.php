<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$message = '';
$messageType = '';
$marker = null;
$needsActivation = false;

// QR-Code gescannt?
if (isset($_GET['qr'])) {
    $scannedQR = trim($_GET['qr']);
    
    // Suche QR-Code im Pool
    $stmt = $pdo->prepare("SELECT * FROM qr_code_pool WHERE qr_code = ?");
    $stmt->execute([$scannedQR]);
    $qrCodeInfo = $stmt->fetch();
    
    if (!$qrCodeInfo) {
        $message = 'QR-Code nicht im System gefunden!';
        $messageType = 'danger';
    } elseif (!$qrCodeInfo['is_assigned']) {
        $message = 'Dieser QR-Code ist noch keinem Marker zugewiesen. Bitte erstellen Sie zuerst einen Marker im Büro.';
        $messageType = 'warning';
    } else {
        // Marker laden
        $marker = getMarkerById($qrCodeInfo['marker_id'], $pdo);
        
        if (!$marker) {
            $message = 'Marker nicht gefunden!';
            $messageType = 'danger';
        } elseif ($marker['is_activated']) {
            // Bereits aktiviert - zur Detailansicht
            header('Location: view_marker.php?id=' . $marker['id']);
            exit;
        } else {
            // Muss aktiviert werden
            $needsActivation = true;
        }
    }
}

// GPS-Position erfassen und aktivieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate'])) {
    validateCSRF();
    
    $markerId = intval($_POST['marker_id'] ?? 0);
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    if (!$latitude || !$longitude) {
        $message = 'Bitte GPS-Position erfassen';
        $messageType = 'danger';
    } elseif (!validateCoordinates($latitude, $longitude)) {
        $message = 'Ungültige GPS-Koordinaten';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Marker aktivieren und GPS setzen
            $stmt = $pdo->prepare("
                UPDATE markers 
                SET latitude = ?, longitude = ?, is_activated = 1, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([floatval($latitude), floatval($longitude), $markerId]);
            
            // QR-Code aktivieren
            $stmt = $pdo->prepare("
                UPDATE qr_code_pool 
                SET is_activated = 1 
                WHERE marker_id = ?
            ");
            $stmt->execute([$markerId]);
            
            $pdo->commit();
            
            $marker = getMarkerById($markerId, $pdo);
            logActivity('marker_activated', "Marker '{$marker['name']}' vor Ort aktiviert", $markerId);
            
            $message = '✓ Marker erfolgreich aktiviert!';
            $messageType = 'success';
            
            header("refresh:2;url=view_marker.php?id=$markerId");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Fehler: ' . e($e->getMessage());
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-Code scannen - Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/mobile-features.css">
    <script src="js/gps-helper.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        #qr-reader {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            border: 3px solid #007bff;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .marker-info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .marker-info-box h3 {
            margin-top: 0;
            font-size: 24px;
        }
        
        .marker-info-box p {
            margin: 10px 0;
            font-size: 16px;
        }
        
        .activation-required {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-qrcode"></i> QR-Code scannen</h1>
                <p>Scannen Sie den QR-Code am Gerät um es zu aktivieren</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <!-- QR-Scanner -->
            <?php if (!$marker): ?>
            <div class="form-section">
                <h2><i class="fas fa-camera"></i> QR-Code Scanner</h2>
                
                <div style="text-align: center;">
                    <button id="start-scan" class="btn btn-primary btn-large">
                        <i class="fas fa-camera"></i> Kamera starten
                    </button>
                    <button id="stop-scan" class="btn btn-danger" style="display: none;">
                        <i class="fas fa-stop"></i> Scanner stoppen
                    </button>
                </div>
                
                <div id="qr-reader" style="display: none;"></div>
                
                <div id="scan-result" style="display: none; margin: 20px 0; padding: 20px; background: #e3f2fd; border-left: 4px solid #1976d2; border-radius: 8px;">
                    <strong>QR-Code gescannt:</strong>
                    <div style="font-size: 24px; font-weight: bold; font-family: monospace; color: #1976d2; margin: 10px 0;" id="scanned-code"></div>
                    <p>Marker wird geladen...</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Marker muss aktiviert werden -->
            <?php if ($needsActivation && $marker): ?>
            
            <div class="marker-info-box">
                <h3><i class="fas fa-check-circle"></i> Marker gefunden!</h3>
                <p><strong>Name:</strong> <?= e($marker['name']) ?></p>
                <p><strong>QR-Code:</strong> <code style="background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 5px;"><?= e($marker['qr_code']) ?></code></p>
                <?php if ($marker['category']): ?>
                    <p><strong>Kategorie:</strong> <?= e($marker['category']) ?></p>
                <?php endif; ?>
                <?php if ($marker['serial_number']): ?>
                    <p><strong>Seriennummer:</strong> <?= e($marker['serial_number']) ?></p>
                <?php endif; ?>
            </div>

            <div class="activation-required">
                <h3><i class="fas fa-map-marker-alt"></i> Aktivierung erforderlich</h3>
                <p>Dieser Marker wurde im Büro erstellt und ist noch nicht aktiv. Bitte erfassen Sie jetzt die GPS-Position vor Ort, um den Marker zu aktivieren.</p>
            </div>

            <form method="POST" class="marker-form">
                <?= csrf_field() ?>
                <input type="hidden" name="marker_id" value="<?= $marker['id'] ?>">
                <input type="hidden" name="activate" value="1">
                
                <div class="form-section">
                    <h2><i class="fas fa-crosshairs"></i> GPS-Position erfassen</h2>
                    
                    <div style="margin: 20px 0;">
                        <button type="button" id="gpsButton" class="gps-button" onclick="getGPSPosition()">
                            <i class="fas fa-crosshairs"></i> GPS-Position automatisch erfassen
                        </button>
                        <div id="gpsStatus"></div>
                    </div>
                    
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    
                    <div id="miniMap" style="height: 400px; margin-top: 15px; border-radius: 8px; border: 2px solid #dee2e6;"></div>
                    
                    <p style="margin-top: 15px; color: #666;">
                        <i class="fas fa-info-circle"></i> Der Marker zeigt den erfassten Standort. 
                        Nutzen Sie den GPS-Button um die aktuelle Position zu erfassen.
                    </p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-large" id="activateBtn" disabled>
                        <i class="fas fa-check"></i> Marker aktivieren
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Abbrechen
                    </a>
                </div>
            </form>
            
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // QR-Code Scanner
        let html5QrCode = null;
        
        document.getElementById('start-scan')?.addEventListener('click', function() {
            document.getElementById('qr-reader').style.display = 'block';
            document.getElementById('start-scan').style.display = 'none';
            document.getElementById('stop-scan').style.display = 'inline-block';
            
            html5QrCode = new Html5Qrcode("qr-reader");
            
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    document.getElementById('scanned-code').textContent = decodedText;
                    document.getElementById('scan-result').style.display = 'block';
                    
                    html5QrCode.stop().then(() => {
                        window.location.href = 'scan.php?qr=' + encodeURIComponent(decodedText);
                    });
                }
            ).catch(err => {
                alert('Kamera-Zugriff fehlgeschlagen: ' + err);
            });
        });
        
        document.getElementById('stop-scan')?.addEventListener('click', function() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    document.getElementById('qr-reader').style.display = 'none';
                    document.getElementById('start-scan').style.display = 'inline-block';
                    document.getElementById('stop-scan').style.display = 'none';
                });
            }
        });

        <?php if ($needsActivation && $marker): ?>
        // Karte für Aktivierung
        const settings = <?= json_encode($settings) ?>;
        const miniMap = L.map('miniMap').setView([settings.map_default_lat, settings.map_default_lng], settings.map_default_zoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(miniMap);
        
        let marker = null;
        const gpsHelper = new GPSHelper();

        function getGPSPosition() {
            const button = document.getElementById('gpsButton');
            
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> GPS wird abgerufen...';
            
            gpsHelper.getCurrentPosition(
                (position) => {
                    document.getElementById('latitude').value = position.lat.toFixed(6);
                    document.getElementById('longitude').value = position.lng.toFixed(6);
                    
                    miniMap.setView([position.lat, position.lng], 16);
                    
                    if (marker) {
                        marker.setLatLng([position.lat, position.lng]);
                    } else {
                        marker = L.marker([position.lat, position.lng]).addTo(miniMap);
                    }
                    
                    // Aktivieren-Button freigeben
                    document.getElementById('activateBtn').disabled = false;
                    
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
        <?php endif; ?>
    </script>
</body>
</html>