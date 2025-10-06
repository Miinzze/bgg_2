<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('markers_edit');

trackUsage('rescan');

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        
        if (!$latitude || !$longitude) {
            $message = 'Bitte erfassen Sie einen Standort';
            $messageType = 'danger';
        } elseif (!validateCoordinates($latitude, $longitude)) {
            $message = 'Ungültige GPS-Koordinaten';
            $messageType = 'danger';
        } else {
            $oldLat = $marker['latitude'];
            $oldLng = $marker['longitude'];
            
            $stmt = $pdo->prepare("UPDATE markers SET latitude = ?, longitude = ? WHERE id = ?");
            if ($stmt->execute([floatval($latitude), floatval($longitude), $id])) {
                
                // Position-Historie speichern (optional)
                try {
                    $stmt = $pdo->prepare("INSERT INTO position_history (marker_id, latitude, longitude, changed_by) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id, floatval($oldLat), floatval($oldLng), $_SESSION['user_id']]);
                } catch (Exception $e) {
                    // Tabelle existiert eventuell nicht
                }
                
                logActivity('position_rescanned', "Position neu erfasst für '{$marker['name']}'", $id);
                
                $message = 'Position erfolgreich aktualisiert!';
                $messageType = 'success';
                
                $marker = getMarkerById($id, $pdo);
                header("refresh:2;url=view_marker.php?id=$id");
            } else {
                $message = 'Fehler beim Aktualisieren der Position';
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
    <title>Position neu erfassen - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/mobile-features.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/gps-helper.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-crosshairs"></i> Position neu erfassen</h1>
                <h2><?= e($marker['name']) ?></h2>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="marker-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-section">
                    <h2><i class="fas fa-map-marker-alt"></i> Neuer Standort</h2>
                    
                    <!-- GPS Auto-Erfassung -->
                    <div style="margin-bottom: 20px;">
                        <button type="button" id="gpsButton" class="gps-button" onclick="getGPSPosition()">
                            <i class="fas fa-crosshairs"></i> Aktuellen GPS-Standort erfassen
                        </button>
                        <div id="gpsStatus"></div>
                        
                        <p style="margin-top: 15px; color: #6c757d;">
                            <strong>Aktuelle Position:</strong><br>
                            Lat: <?= number_format($marker['latitude'], 6) ?>, 
                            Lng: <?= number_format($marker['longitude'], 6) ?>
                        </p>
                    </div>
                    
                    <!-- Manuelle Eingabe -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="latitude">Breitengrad *</label>
                            <input type="text" id="latitude" name="latitude" required 
                                   value="<?= e($marker['latitude']) ?>" step="any">
                        </div>
                        
                        <div class="form-group">
                            <label for="longitude">Längengrad *</label>
                            <input type="text" id="longitude" name="longitude" required 
                                   value="<?= e($marker['longitude']) ?>" step="any">
                        </div>
                    </div>
                    
                    <!-- Karte -->
                    <div id="map" style="height: 500px; border-radius: 8px; border: 2px solid #dee2e6;"></div>
                    <p style="margin-top: 10px; color: #6c757d; font-size: 14px;">
                        <i class="fas fa-info-circle"></i> 
                        Nutzen Sie GPS für automatische Erfassung oder verschieben Sie den Marker auf der Karte
                    </p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-large">
                        <i class="fas fa-save"></i> Position speichern
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
    const gpsHelper = new GPSHelper();
    
    // Karte initialisieren
    const map = L.map('map').setView([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    const markerIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    
    let marker = L.marker([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], {
        draggable: true,
        icon: markerIcon
    }).addTo(map);
    
    marker.on('dragend', function(e) {
        const pos = marker.getLatLng();
        document.getElementById('latitude').value = pos.lat.toFixed(6);
        document.getElementById('longitude').value = pos.lng.toFixed(6);
    });
    
    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
    });
    
    // GPS Position abrufen
    function getGPSPosition() {
        const button = document.getElementById('gpsButton');
        const statusDiv = document.getElementById('gpsStatus');
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> GPS wird abgerufen...';
        
        gpsHelper.getCurrentPosition(
            (position) => {
                document.getElementById('latitude').value = position.lat.toFixed(6);
                document.getElementById('longitude').value = position.lng.toFixed(6);
                
                map.setView([position.lat, position.lng], 16);
                marker.setLatLng([position.lat, position.lng]);
                
                button.disabled = false;
                button.classList.add('active');
                button.innerHTML = '<i class="fas fa-check"></i> GPS-Position erfasst';
                
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
                button.innerHTML = '<i class="fas fa-crosshairs"></i> Aktuellen GPS-Standort erfassen';
                gpsHelper.showStatus('gpsStatus', error, 'error');
            }
        );
    }
    </script>
</body>
</html>