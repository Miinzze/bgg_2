<?php
require_once 'config.php';
require_once 'functions.php';

// Kein Login erforderlich - öffentliche Ansicht!
// Token aus URL holen
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Kein Token angegeben');
}

// Marker mit Token laden
$stmt = $pdo->prepare("
    SELECT m.*, u.username as created_by_name,
           c.name as category_name, c.icon as category_icon, c.color as category_color
    FROM markers m
    LEFT JOIN users u ON m.created_by = u.id
    LEFT JOIN categories c ON m.category = c.name
    WHERE m.public_token = ? AND m.deleted_at IS NULL
");
$stmt->execute([$token]);
$marker = $stmt->fetch();

if (!$marker) {
    die('Marker nicht gefunden oder nicht öffentlich zugänglich');
}

// Bilder laden
$images = getMarkerImages($marker['id'], $pdo);

// Seriennummern laden (bei Multi-Device)
$serialNumbers = [];
if ($marker['is_multi_device']) {
    $serialNumbers = getMarkerSerialNumbers($marker['id'], $pdo);
}

// ÖFFENTLICHE Dokumente laden
$stmt = $pdo->prepare("
    SELECT id, document_name, document_path, file_size, public_description, uploaded_at
    FROM marker_documents
    WHERE marker_id = ? AND is_public = 1
    ORDER BY uploaded_at DESC
");
$stmt->execute([$marker['id']]);
$publicDocuments = $stmt->fetchAll();

// Wartungsstatus
$maintenanceStatus = getMaintenanceStatus($marker['next_maintenance']);
$rentalStatus = getRentalStatusLabel($marker['rental_status']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($marker['name']) ?> - Öffentliche Ansicht</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header .qr-code {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .info-card h3 {
            color: #495057;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .info-card .value {
            font-size: 20px;
            color: #212529;
            font-weight: bold;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #495057;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .image-gallery img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .image-gallery img:hover {
            transform: scale(1.05);
        }
        
        #map {
            height: 400px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        
        .document-list {
            display: grid;
            gap: 15px;
        }
        
        .document-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .document-item:hover {
            background: #e9ecef;
            border-color: #667eea;
        }
        
        .document-icon {
            font-size: 48px;
            color: #dc3545;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-info h4 {
            margin-bottom: 5px;
            color: #212529;
        }
        
        .document-info p {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .document-item a {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .document-item a:hover {
            background: #764ba2;
        }
        
        .serial-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .serial-list .serial-item {
            padding: 10px;
            background: white;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?= e($marker['name']) ?></h1>
            <div class="qr-code">QR: <?= e($marker['qr_code']) ?></div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Info-Karten -->
            <div class="info-grid">
                <?php if ($marker['category']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-tag"></i> Kategorie</h3>
                    <div class="value"><?= e($marker['category']) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!$marker['is_storage'] && !$marker['is_multi_device']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-circle"></i> Status</h3>
                    <div class="value">
                        <span class="badge badge-<?= $rentalStatus['class'] ?>">
                            <?= $rentalStatus['label'] ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($marker['serial_number']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-barcode"></i> Seriennummer</h3>
                    <div class="value"><?= e($marker['serial_number']) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!$marker['is_storage'] && !$marker['is_multi_device'] && $marker['next_maintenance']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-wrench"></i> Wartung</h3>
                    <div class="value">
                        <span class="badge badge-<?= $maintenanceStatus['class'] ?>">
                            <?= $maintenanceStatus['label'] ?>
                        </span>
                        <div style="font-size: 14px; margin-top: 5px; color: #6c757d;">
                            Nächste: <?= formatDate($marker['next_maintenance']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($marker['is_multi_device']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-layer-group"></i> Typ</h3>
                    <div class="value">
                        <span class="badge badge-info">Mehrgerät-Standort</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($marker['is_storage']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-warehouse"></i> Typ</h3>
                    <div class="value">
                        <span class="badge badge-success">Lagergerät</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Multi-Device Seriennummern -->
            <?php if ($marker['is_multi_device'] && !empty($serialNumbers)): ?>
            <div class="section">
                <h2><i class="fas fa-list"></i> Seriennummern an diesem Standort</h2>
                <div class="serial-list">
                    <?php foreach ($serialNumbers as $sn): ?>
                        <div class="serial-item">
                            <i class="fas fa-barcode"></i> <?= e($sn['serial_number']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Öffentliche Dokumente -->
            <?php if (!empty($publicDocuments)): ?>
            <div class="section">
                <h2><i class="fas fa-file-pdf"></i> Verfügbare Dokumente</h2>
                <div class="document-list">
                    <?php foreach ($publicDocuments as $doc): ?>
                        <div class="document-item">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h4><?= e($doc['document_name']) ?></h4>
                                <?php if ($doc['public_description']): ?>
                                    <p><?= e($doc['public_description']) ?></p>
                                <?php endif; ?>
                                <p>
                                    <i class="fas fa-clock"></i> <?= formatDateTime($doc['uploaded_at']) ?> | 
                                    <i class="fas fa-file"></i> <?= number_format($doc['file_size'] / 1024 / 1024, 2) ?> MB
                                </p>
                            </div>
                            <a href="download_public_document.php?id=<?= $doc['id'] ?>&token=<?= urlencode($token) ?>" target="_blank">
                                <i class="fas fa-download"></i> Herunterladen
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Bilder -->
            <?php if (!empty($images)): ?>
            <div class="section">
                <h2><i class="fas fa-images"></i> Bilder</h2>
                <div class="image-gallery">
                    <?php foreach ($images as $image): ?>
                        <img src="<?= e($image['image_path']) ?>" 
                             alt="Bild" 
                             onclick="window.open(this.src, '_blank')">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Karte -->
            <div class="section">
                <h2><i class="fas fa-map-marker-alt"></i> Standort</h2>
                <div id="map"></div>
                <p style="margin-top: 10px; color: #6c757d; font-size: 14px;">
                    <i class="fas fa-map-pin"></i> 
                    <?= number_format($marker['latitude'], 6) ?>, <?= number_format($marker['longitude'], 6) ?>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                <i class="fas fa-clock"></i> Erstellt am <?= formatDate($marker['created_at'], 'd.m.Y') ?>
                <?php if ($marker['created_by_name']): ?>
                    von <?= e($marker['created_by_name']) ?>
                <?php endif; ?>
            </p>
            <p style="margin-top: 10px; font-size: 12px;">
                Marker System - Öffentliche Ansicht
            </p>
        </div>
    </div>
    
    <script>
        // Karte initialisieren
        const map = L.map('map').setView([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Marker hinzufügen
        L.marker([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>])
            .addTo(map)
            .bindPopup('<strong><?= addslashes($marker['name']) ?></strong><br><?= addslashes($marker['category'] ?? '') ?>')
            .openPopup();
    </script>
</body>
</html>