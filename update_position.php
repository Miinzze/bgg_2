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
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        $message = 'Sicherheitstoken fehlt';
        $messageType = 'danger';
    } elseif (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } elseif (!$latitude || !$longitude) {
        $message = 'Bitte erfassen Sie einen Standort';
        $messageType = 'danger';
    } elseif (!validateCoordinates($latitude, $longitude)) {
        $message = 'Ungültige GPS-Koordinaten';
        $messageType = 'danger';
    } else {
        $stmt = $pdo->prepare("UPDATE markers SET latitude = ?, longitude = ? WHERE id = ?");
        if ($stmt->execute([floatval($latitude), floatval($longitude), $id])) {
            $message = 'Position erfolgreich aktualisiert!';
            $messageType = 'success';
            
            $marker = getMarkerById($id, $pdo);
            header("refresh:2;url=view_marker.php?id=$id");
            logActivity('position_updated', "Position aktualisiert", $id);
        } else {
            $message = 'Fehler beim Aktualisieren der Position';
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
    <title>Position aktualisieren - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>📍 Position aktualisieren</h1>
                <h2><?= e($marker['name']) ?></h2>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="marker-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-section">
                    <h2>Aktuelle Position</h2>
                    <p>Lat: <?= $marker['latitude'] ?>, Lng: <?= $marker['longitude'] ?></p>
                    
                    <div id="currentMap" style="height: 300px; margin: 20px 0; border-radius: 5px;"></div>
                </div>
                
                <div class="form-section">
                    <h2>Neue Position erfassen</h2>
                    
                    <div class="location-capture">
                        <button type="button" class="btn btn-primary btn-large" id="captureLocation">
                            📍 GPS-Standort erfassen
                        </button>
                        <span id="locationStatus"></span>
                    </div>
                    
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    
                    <div id="newMap" style="height: 300px; margin-top: 20px; display: none; border-radius: 5px;"></div>
                    
                    <div id="coordinatesDisplay" style="padding: 10px; background: #f8f9fa; border-radius: 5px; margin-top: 10px; display: none;">
                        <strong>Neue Position:</strong> 
                        <span id="coordText"></span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-large" id="submitBtn" disabled>
                        Position aktualisieren
                    </button>
                    <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        // Aktuelle Position anzeigen
        const currentMap = L.map('currentMap').setView([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(currentMap);
        
        L.marker([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>]).addTo(currentMap)
            .bindPopup('Aktuelle Position').openPopup();
        
        // Neue Position erfassen
        let newMap = null;
        let newMarker = null;
        
        document.getElementById('captureLocation').addEventListener('click', function() {
            const statusEl = document.getElementById('locationStatus');
            statusEl.textContent = 'Standort wird erfasst...';
            statusEl.style.color = '#ffc107';
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;
                    document.getElementById('submitBtn').disabled = false;
                    
                    statusEl.textContent = '✓ Neuer Standort erfasst';
                    statusEl.style.color = '#28a745';
                    
                    // Neue Position anzeigen
                    const mapDiv = document.getElementById('newMap');
                    mapDiv.style.display = 'block';
                    
                    if (!newMap) {
                        newMap = L.map('newMap').setView([lat, lng], 16);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap contributors',
                            maxZoom: 19
                        }).addTo(newMap);
                        newMarker = L.marker([lat, lng]).addTo(newMap)
                            .bindPopup('Neue Position').openPopup();
                    } else {
                        newMap.setView([lat, lng], 16);
                        newMarker.setLatLng([lat, lng]);
                        newMarker.openPopup();
                    }
                    
                    document.getElementById('coordText').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
                    document.getElementById('coordinatesDisplay').style.display = 'block';
                    
                    // Zur neuen Karte scrollen
                    mapDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, function(error) {
                    statusEl.textContent = '✗ Standort konnte nicht erfasst werden: ' + error.message;
                    statusEl.style.color = '#dc3545';
                });
            } else {
                statusEl.textContent = '✗ Geolocation wird nicht unterstützt';
                statusEl.style.color = '#dc3545';
            }
        });
    </script>
</body>
</html>