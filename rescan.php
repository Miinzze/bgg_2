<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$message = '';
$messageType = '';
$marker = null;

// QR-Code gescannt?
if (isset($_GET['qr'])) {
    $scannedQR = trim($_GET['qr']);
    
    // Suche Marker mit diesem QR-Code
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as created_by_name
        FROM markers m
        LEFT JOIN users u ON m.created_by = u.id
        WHERE m.qr_code = ? AND m.deleted_at IS NULL
    ");
    $stmt->execute([$scannedQR]);
    $marker = $stmt->fetch();
    
    if (!$marker) {
        $message = 'Kein Marker mit diesem QR-Code gefunden!';
        $messageType = 'danger';
    }
}

// Position aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_position'])) {
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
            $stmt = $pdo->prepare("
                UPDATE markers 
                SET latitude = ?, longitude = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                floatval($latitude),
                floatval($longitude),
                $markerId
            ]);
            
            logActivity('marker_position_updated', "Position aktualisiert", $markerId);
            
            $message = 'Position erfolgreich aktualisiert!';
            $messageType = 'success';
            
            // Marker neu laden
            $marker = getMarkerById($markerId, $pdo);
            
            header("refresh:2;url=view_marker.php?id=$markerId");
            
        } catch (Exception $e) {
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
    <title>Marker erneut scannen - Marker System</title>
    <link rel="stylesheet" href="css/style.css">
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
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        
        .marker-info-box h3 {
            margin-top: 0;
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-qrcode"></i> Marker erneut scannen</h1>
                <p>Scannen Sie einen QR-Code um die Position zu aktualisieren</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <!-- QR-Scanner -->
            <?php if (!$marker): ?>
            <div class="form-section">
                <h2><i class="fas fa-camera"></i> QR-Code Scanner</h2>
                
                <div style="text-align: center;">
                    <button id="start-scan" class="btn btn-primary btn-large" style="margin: 10px;">
                        <i class="fas fa-camera"></i> Kamera starten & QR-Code scannen
                    </button>
                    <button id="stop-scan" class="btn btn-danger" style="margin: 10px; display: none;">
                        <i class="fas fa-stop"></i> Scanner stoppen
                    </button>
                </div>
                
                <div id="qr-reader" style="display: none;"></div>
                
                <div id="scan-result" style="display: none; margin: 20px 0; padding: 20px; background: #e3f2fd; border-left: 4px solid #1976d2; border-radius: 4px;">
                    <strong>QR-Code gescannt:</strong>
                    <div style="font-size: 24px; font-weight: bold; font-family: 'Courier New', monospace; color: #1976d2; margin: 10px 0;" id="scanned-code"></div>
                    <p>Marker wird geladen...</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Marker gefunden - Position aktualisieren -->
            <?php if ($marker): ?>
            
            <div class="marker-info-box">
                <h3><i class="fas fa-check-circle"></i> Marker gefunden!</h3>
                <p><strong>Name:</strong> <?= e($marker['name']) ?></p>
                <p><strong>QR-Code:</strong> <code><?= e($marker['qr_code']) ?></code></p>
                <?php if ($marker['category']): ?>
                    <p><strong>Kategorie:</strong> <?= e($marker['category']) ?></p>
                <?php endif; ?>
                <?php if ($marker['serial_number']): ?>
                    <p><strong>Seriennummer:</strong> <?= e($marker['serial_number']) ?></p>
                <?php endif; ?>
                <p>
                    <strong>Aktuelle Position:</strong> 
                    <?= number_format($marker['latitude'], 6) ?>, <?= number_format($marker['longitude'], 6) ?>
                </p>
            </div>

            <form method="POST" class="marker-form">
                <?php include 'csrf_token.php'; ?>
                <input type="hidden" name="marker_id" value="<?= $marker['id'] ?>">
                
                <div class="form-section">
                    <h2><i class="fas fa-map-marker-alt"></i> Neue Position erfassen</h2>
                    
                    <div style="margin: 20px 0;">
                        <button type="button" id="gpsButton" class="gps-button" onclick="getGPSPosition()">
                            <i class="fas fa-crosshairs"></i> GPS-Position automatisch erfassen
                        </button>
                        <div id="gpsStatus"></div>
                    </div>
                    
                    <input type="hidden" name="latitude" id="latitude" value="<?= $marker['latitude'] ?>">
                    <input type="hidden" name="longitude" id="longitude" value="<?= $marker['longitude'] ?>">
                    
                    <div id="miniMap" style="height: 400px; margin-top: 15px; border-radius: 8px; border: 2px solid #dee2e6;"></div>
                    
                    <p style="margin-top: 15px; color: #666;">
                        <i class="fas fa-info-circle"></i> Der rote Marker zeigt die aktuelle Position. 
                        Nutzen Sie den GPS-Button um die Position zu aktualisieren.
                    </p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_position" class="btn btn-primary btn-large">
                        <i class="fas fa-save"></i> Position aktualisieren
                    </button>
                    <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> Marker anzeigen
                    </a>
                    <a href="rescan.php" class="btn btn-secondary">
                        <i class="fas fa-qrcode"></i> Anderen QR-Code scannen
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
        
        document.getElementById('start-scan').addEventListener('click', function() {
            document.getElementById('qr-reader').style.display = 'block';
            document.getElementById('start-scan').style.display = 'none';
            document.getElementById('stop-scan').style.display = 'inline-block';
            
            html5QrCode = new Html5Qrcode("qr-reader");
            
            html5QrCode.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                (decodedText) => {
                    document.getElementById('scanned-code').textContent = decodedText;
                    document.getElementById('scan-result').style.display = 'block';
                    
                    html5QrCode.stop().then(() => {
                        window.location.href = 'rescan.php?qr=' + encodeURIComponent(decodedText);
                    });
                },
                (errorMessage) => {
                    // Ignore scan errors
                }
            ).catch(err => {
                alert('Kamera-Zugriff fehlgeschlagen: ' + err);
            });
        });
        
        document.getElementById('stop-scan').addEventListener('click', function() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    document.getElementById('qr-reader').style.display = 'none';
                    document.getElementById('start-scan').style.display = 'inline-block';
                    document.getElementById('stop-scan').style.display = 'none';
                });
            }
        });

        <?php if ($marker): ?>
        // Karte initialisieren mit aktueller Position
        const currentLat = <?= $marker['latitude'] ?>;
        const currentLng = <?= $marker['longitude'] ?>;
        
        const miniMap = L.map('miniMap').setView([currentLat, currentLng], 16);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(miniMap);
        
        let marker = L.marker([currentLat, currentLng]).addTo(miniMap);
        
        // GPS-Helper
        const gpsHelper = new GPSHelper();

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
                    
                    miniMap.setView([lat, lng], 16);
                    marker.setLatLng([lat, lng]);
                    
                    button.disabled = false;
                    button.classList.add('active');
                    button.innerHTML = '<i class="fas fa-check"></i> Neue Position erfasst';
                    
                    gpsHelper.showStatus('gpsStatus', 
                        `Neue Position erfasst mit ${Math.round(position.accuracy)}m Genauigkeit`, 
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