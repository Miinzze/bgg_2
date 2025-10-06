<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

// Bilder laden
$images = getMarkerImages($id, $pdo);

// Dokumente laden
$stmt = $pdo->prepare("SELECT * FROM marker_documents WHERE marker_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$id]);
$documents = $stmt->fetchAll();

// Wartungshistorie
$stmt = $pdo->prepare("
    SELECT mh.*, u.username as performed_by_name
    FROM maintenance_history mh
    LEFT JOIN users u ON mh.performed_by = u.id
    WHERE mh.marker_id = ?
    ORDER BY mh.maintenance_date DESC
    LIMIT 10
");
$stmt->execute([$id]);
$maintenanceHistory = $stmt->fetchAll();

// Kommentare
$stmt = $pdo->prepare("
    SELECT mc.*, u.username
    FROM marker_comments mc
    LEFT JOIN users u ON mc.user_id = u.id
    WHERE mc.marker_id = ?
    ORDER BY mc.created_at DESC
");
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

// Status
$rentalStatus = getRentalStatusLabel($marker['rental_status']);
$maintenanceStatus = getMaintenanceStatus($marker['next_maintenance']);

// Seriennummern bei Multi-Device
$serialNumbers = [];
if ($marker['is_multi_device']) {
    $serialNumbers = getMarkerSerialNumbers($id, $pdo);
}

// Custom Fields
$stmt = $pdo->prepare("
    SELECT cf.field_label, cf.field_type, mcv.field_value
    FROM marker_custom_values mcv
    JOIN custom_fields cf ON mcv.field_id = cf.id
    WHERE mcv.marker_id = ?
    ORDER BY cf.display_order, cf.id
");
$stmt->execute([$id]);
$customFieldValues = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($marker['name']) ?> - Marker Details</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dark-mode.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .qr-code-box {
            background: linear-gradient(135deg, #e63216ff 0%, #9c210eff 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .qr-code-box .qr-code {
            font-size: 36px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            display: inline-block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #e63216ff;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .info-card h3 {
            color: var(--text-secondary);
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .info-card .value {
            font-size: 24px;
            font-weight: bold;
            color: var(--text-color);
        }
        
        .section {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .section h2 {
            color: var(--text-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .activation-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .activation-warning h3 {
            color: #856404;
            margin-top: 0;
        }
        
        .serial-list {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
        }
        
        .serial-item {
            padding: 12px;
            background: var(--card-bg);
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 3px solid #e63216ff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .document-list {
            display: grid;
            gap: 15px;
        }
        
        .document-item {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .document-item:hover {
            border-color: #e63216ff;
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        .document-icon {
            font-size: 48px;
            color: #dc3545;
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
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.3s;
            border: 2px solid var(--border-color);
        }
        
        .image-gallery img:hover {
            transform: scale(1.05);
        }
        
        .comment-form {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .comment-form textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            resize: vertical;
            min-height: 100px;
            background: var(--input-bg);
            color: var(--text-color);
        }
        
        .comments-list {
            margin-top: 20px;
        }
        
        .comment-item {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 3px solid #e63216ff;
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .comment-date {
            color: var(--text-secondary);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-info-circle"></i> <?= e($marker['name']) ?></h1>
                <div class="header-actions">
                    <?php if (hasPermission('markers_edit')): ?>
                        <a href="edit_marker.php?id=<?= $marker['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Bearbeiten
                        </a>
                    <?php endif; ?>
                    
                    <a href="print_qr.php?id=<?= $marker['id'] ?>" class="btn btn-secondary" target="_blank">
                        <i class="fas fa-qrcode"></i> QR-Code drucken
                    </a>
                    
                    <?php if (hasPermission('markers_delete')): ?>
                        <a href="delete_marker.php?id=<?= $marker['id'] ?>" class="btn btn-danger" 
                           onclick="return confirm('Marker wirklich lÃ¶schen?')">
                            <i class="fas fa-trash"></i> LÃ¶schen
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Aktivierungswarnung -->
            <?php if (!$marker['is_activated']): ?>
            <div class="activation-warning">
                <h3><i class="fas fa-exclamation-triangle"></i> Marker noch nicht aktiviert</h3>
                <p>Dieser Marker wurde im BÃ¼ro erstellt, ist aber noch nicht vor Ort aktiviert. Scannen Sie den QR-Code am GerÃ¤t, um die GPS-Position zu erfassen und den Marker zu aktivieren.</p>
                <a href="scan.php" class="btn btn-warning" style="margin-top: 10px;">
                    <i class="fas fa-qrcode"></i> Jetzt aktivieren
                </a>
            </div>
            <?php endif; ?>
            
            <!-- QR-Code Anzeige -->
            <div class="qr-code-box">
                <div><i class="fas fa-qrcode" style="font-size: 48px;"></i></div>
                <div class="qr-code"><?= e($marker['qr_code']) ?></div>
                <div style="margin-top: 15px;">
                    <a href="print_qr.php?id=<?= $marker['id'] ?>" class="btn btn-light btn-sm" target="_blank">
                        <i class="fas fa-print"></i> Drucken
                    </a>
                    <?php if ($marker['public_token']): ?>
                        <a href="public_view.php?token=<?= $marker['public_token'] ?>" class="btn btn-light btn-sm" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Ã–ffentliche Ansicht
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
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
                        <span class="badge badge-<?= $rentalStatus['class'] ?> large">
                            <?= $rentalStatus['label'] ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($marker['serial_number']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-barcode"></i> Seriennummer</h3>
                    <div class="value" style="font-family: 'Courier New', monospace;"><?= e($marker['serial_number']) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!$marker['is_storage'] && !$marker['is_multi_device'] && $marker['next_maintenance']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-wrench"></i> NÃ¤chste Wartung</h3>
                    <div class="value" style="font-size: 18px;">
                        <?= formatDate($marker['next_maintenance']) ?>
                        <br>
                        <span class="badge badge-<?= $maintenanceStatus['class'] ?>" style="margin-top: 10px;">
                            <?= $maintenanceStatus['label'] ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!$marker['is_storage'] && !$marker['is_multi_device']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-gas-pump"></i> Kraftstoff</h3>
                    <div class="value"><?= $marker['fuel_level'] ?>%</div>
                    <div style="margin-top: 10px; background: var(--bg-secondary); height: 10px; border-radius: 5px; overflow: hidden;">
                        <div style="background: linear-gradient(90deg, #dc3545 0%, #ffc107 50%, #28a745 100%); height: 100%; width: <?= $marker['fuel_level'] ?>%; transition: width 0.3s;"></div>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-clock"></i> Betriebsstunden</h3>
                    <div class="value"><?= number_format($marker['operating_hours'], 2) ?> h</div>
                </div>
                <?php endif; ?>
                
                <?php if ($marker['is_multi_device']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-layer-group"></i> Typ</h3>
                    <div class="value">
                        <span class="badge badge-info large">MehrgerÃ¤t-Standort</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($marker['is_storage']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-warehouse"></i> Typ</h3>
                    <div class="value">
                        <span class="badge badge-success large">LagergerÃ¤t</span>
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
                            <i class="fas fa-barcode" style="font-size: 20px;"></i> 
                            <strong style="font-family: 'Courier New', monospace; font-size: 16px;"><?= e($sn['serial_number']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Custom Fields -->
            <?php if (!empty($customFieldValues)): ?>
            <div class="section">
                <h2><i class="fas fa-list"></i> ZusÃ¤tzliche Informationen</h2>
                <table class="data-table">
                    <?php foreach ($customFieldValues as $field): ?>
                    <tr>
                        <th style="width: 30%;"><?= e($field['field_label']) ?></th>
                        <td><?= nl2br(e($field['field_value'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Dokumente -->
            <?php if (!empty($documents)): ?>
            <div class="section">
                <h2><i class="fas fa-file-pdf"></i> Dokumente</h2>
                <div class="document-list">
                    <?php foreach ($documents as $doc): ?>
                        <div class="document-item">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info" style="flex: 1;">
                                <strong style="font-size: 16px;"><?= e($doc['document_name']) ?></strong>
                                <?php if ($doc['is_public']): ?>
                                    <span class="badge badge-success" style="margin-left: 10px;">
                                        <i class="fas fa-globe"></i> Ã–ffentlich
                                    </span>
                                <?php endif; ?>
                                <br>
                                <small style="color: var(--text-secondary);">
                                    <?= number_format($doc['file_size'] / 1024 / 1024, 2) ?> MB | 
                                    <?= formatDateTime($doc['uploaded_at']) ?>
                                </small>
                                <?php if ($doc['is_public'] && $doc['public_description']): ?>
                                    <br>
                                    <small style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle"></i> <?= e($doc['public_description']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="<?= e($doc['document_path']) ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Anzeigen
                                </a>
                            </div>
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
                    <?php foreach ($images as $img): ?>
                        <img src="<?= e($img['image_path']) ?>" alt="Marker Bild" 
                             onclick="window.open(this.src, '_blank')">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Wartungshistorie -->
            <?php if (!empty($maintenanceHistory)): ?>
            <div class="section">
                <h2><i class="fas fa-history"></i> Wartungshistorie</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Beschreibung</th>
                            <th>DurchgefÃ¼hrt von</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintenanceHistory as $maint): ?>
                        <tr>
                            <td><?= formatDate($maint['maintenance_date']) ?></td>
                            <td><?= e($maint['description']) ?></td>
                            <td><?= e($maint['performed_by_name'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Karte -->
            <?php if ($marker['is_activated'] && $marker['latitude'] && $marker['longitude']): ?>
            <div class="section">
                <h2><i class="fas fa-map-marker-alt"></i> Standort</h2>
                <div id="map" style="height: 400px; border-radius: 10px; border: 2px solid var(--border-color);"></div>
                <p style="margin-top: 15px; color: var(--text-secondary); font-family: 'Courier New', monospace;">
                    <i class="fas fa-map-pin"></i> 
                    <?= number_format($marker['latitude'], 6) ?>, <?= number_format($marker['longitude'], 6) ?>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Kommentare -->
            <div class="section">
                <h2><i class="fas fa-comments"></i> Kommentare</h2>
                
                <?php if (hasPermission('comments_add')): ?>
                <form method="POST" action="add_comment.php" class="comment-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="marker_id" value="<?= $marker['id'] ?>">
                    <textarea name="comment" placeholder="Kommentar hinzufÃ¼gen..." required></textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-paper-plane"></i> Kommentar hinzufÃ¼gen
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="comments-list">
                    <?php if (empty($comments)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 20px;">
                            <i class="fas fa-comment-slash"></i> Noch keine Kommentare vorhanden
                        </p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-header">
                                    <strong><i class="fas fa-user"></i> <?= e($comment['username'] ?? 'Unbekannt') ?></strong>
                                    <span class="comment-date"><i class="fas fa-clock"></i> <?= formatDateTime($comment['created_at']) ?></span>
                                </div>
                                <div><?= nl2br(e($comment['comment'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <?php if ($marker['is_activated'] && $marker['latitude'] && $marker['longitude']): ?>
    <script>
        const map = L.map('map').setView([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], 16);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Â© OpenStreetMap'
        }).addTo(map);
        
        const markerIcon = L.divIcon({
            html: '<div style="background-color: <?= getMarkerColor($marker) ?>; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
            className: '',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        L.marker([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], { icon: markerIcon })
            .addTo(map)
            .bindPopup('<strong><?= addslashes($marker['name']) ?></strong>')
            .openPopup();
    </script>
    <?php endif; ?>
    
    <script src="js/dark-mode.js"></script>
</body>
</html>