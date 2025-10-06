<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Ungültiger Zugriff');
}

// Marker über Token abrufen
$stmt = $pdo->prepare("SELECT * FROM markers WHERE public_token = ?");
$stmt->execute([$token]);
$marker = $stmt->fetch();

if (!$marker) {
    die('Marker nicht gefunden');
}

// Bilder abrufen
$stmt = $pdo->prepare("SELECT * FROM marker_images WHERE marker_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$marker['id']]);
$images = $stmt->fetchAll();

// Seriennummern bei Multi-Device
$serialNumbers = [];
if ($marker['is_multi_device']) {
    $stmt = $pdo->prepare("SELECT serial_number, created_at FROM marker_serial_numbers WHERE marker_id = ? ORDER BY created_at ASC");
    $stmt->execute([$marker['id']]);
    $serialNumbers = $stmt->fetchAll();
}

// Status-Label
function getStatusLabel($status) {
    $labels = [
        'verfuegbar' => ['label' => 'Verfügbar', 'class' => 'success'],
        'vermietet' => ['label' => 'Vermietet', 'class' => 'warning'],
        'wartung' => ['label' => 'Wartung', 'class' => 'danger']
    ];
    return $labels[$status] ?? ['label' => 'Unbekannt', 'class' => 'secondary'];
}

$statusInfo = getStatusLabel($marker['rental_status']);

function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($marker['name']) ?> - Info</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body {
            background: #f5f5f5;
        }
        .public-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .public-header h1 {
            margin: 0;
            font-size: 28px;
        }
        .public-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .readonly-badge {
            background: #ffc107;
            color: #333;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }
        .serial-numbers-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .serial-number-item {
            padding: 8px 12px;
            background: white;
            margin: 5px 0;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="public-header">
        <h1><i class="fas fa-info-circle"></i> Geräte-Information</h1>
        <p>Öffentliche Nur-Lese-Ansicht</p>
        <span class="readonly-badge">
            <i class="fas fa-eye"></i> Nur ansehen - Keine Bearbeitung möglich
        </span>

        <div style="margin-top: 15px;">
            <a href="login.php?redirect=<?= urlencode('view_marker.php?id=' . $marker['id']) ?>" 
            class="btn btn-primary"
            style="display: inline-block; background: white; color: #667eea; padding: 12px 25px; border-radius: 25px; text-decoration: none; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                <i class="fas fa-sign-in-alt"></i> Anmelden für vollständigen Zugriff
            </a>
        </div>
    </div>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="marker-details">
                <div class="info-card">
                    <h2><i class="fas fa-box"></i> <?= e($marker['name']) ?></h2>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Typ:</span>
                            <span class="value">
                                <?php if ($marker['is_multi_device']): ?>
                                    <span class="badge badge-info">Mehrere Geräte an einem Standort</span>
                                <?php elseif ($marker['is_storage']): ?>
                                    <span class="badge badge-info">Lagergerät</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Betriebsgerät</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="label">Kategorie:</span>
                            <span class="value"><?= e($marker['category']) ?></span>
                        </div>
                        
                        <?php if (!$marker['is_storage'] && !$marker['is_multi_device']): ?>
                        <div class="info-item">
                            <span class="label">Status:</span>
                            <span class="badge badge-<?= $statusInfo['class'] ?> large">
                                <?= $statusInfo['label'] ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($marker['is_multi_device']): ?>
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <span class="label">Seriennummern (<?= count($serialNumbers) ?> Geräte):</span>
                                <div class="serial-numbers-list">
                                    <?php if (empty($serialNumbers)): ?>
                                        <p style="color: #6c757d; margin: 0;">Keine Seriennummern erfasst</p>
                                    <?php else: ?>
                                        <?php foreach ($serialNumbers as $index => $sn): ?>
                                            <div class="serial-number-item">
                                                <strong><?= $index + 1 ?>.</strong> <?= e($sn['serial_number']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($marker['serial_number']): ?>
                            <div class="info-item">
                                <span class="label">Seriennummer:</span>
                                <span class="value"><?= e($marker['serial_number']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$marker['is_storage'] && !$marker['is_multi_device']): ?>
                        <div class="info-item">
                            <span class="label">Betriebsstunden:</span>
                            <span class="value"><?= e($marker['operating_hours']) ?> h</span>
                        </div>
                        
                        <div class="info-item">
                            <span class="label">Kraftstofffüllstand:</span>
                            <div class="fuel-indicator large">
                                <div class="fuel-bar" style="width: <?= $marker['fuel_level'] ?>%"></div>
                                <span><?= $marker['fuel_level'] ?>%</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!$marker['is_storage'] && !$marker['is_multi_device']): ?>
                <div class="info-card">
                    <h2><i class="fas fa-wrench"></i> Wartungsinformationen</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Wartungsintervall:</span>
                            <span class="value">Alle <?= $marker['maintenance_interval_months'] ?> Monate</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Letzte Wartung:</span>
                            <span class="value"><?= date('d.m.Y', strtotime($marker['last_maintenance'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Nächste Wartung:</span>
                            <span class="value"><?= date('d.m.Y', strtotime($marker['next_maintenance'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="info-card">
                    <h2><i class="fas fa-map-marker-alt"></i> Standort</h2>
                    <div id="markerMap" style="height: 400px; border-radius: 8px; border: 2px solid #dee2e6;"></div>
                    <p style="margin-top: 10px; color: #6c757d;">
                        <i class="fas fa-map-pin"></i> 
                        <?= number_format($marker['latitude'], 6) ?>, <?= number_format($marker['longitude'], 6) ?>
                    </p>
                </div>
                
                <?php if (!empty($images)): ?>
                <div class="info-card">
                    <h2><i class="fas fa-images"></i> Bilder</h2>
                    <div class="image-gallery">
                        <?php foreach ($images as $image): ?>
                            <a href="<?= e($image['image_path']) ?>" target="_blank">
                                <img src="<?= e($image['image_path']) ?>" alt="Marker Bild">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-info" style="margin-top: 20px;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Hinweis:</strong> Dies ist eine öffentliche Nur-Lese-Ansicht. 
                    Für vollständigen Zugriff und Bearbeitungsfunktionen melden Sie sich bitte im System an.
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        const map = L.map('markerMap').setView([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        L.marker([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>]).addTo(map)
            .bindPopup('<strong><?= e($marker['name']) ?></strong>').openPopup();
    </script>
</body>
</html>