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

// Wartungshistorie laden
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

// Kommentare laden
$stmt = $pdo->prepare("
    SELECT mc.*, u.username
    FROM marker_comments mc
    LEFT JOIN users u ON mc.user_id = u.id
    WHERE mc.marker_id = ?
    ORDER BY mc.created_at DESC
");
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

// Status ermitteln
$rentalStatus = getRentalStatusLabel($marker['rental_status']);
$maintenanceStatus = getMaintenanceStatus($marker['next_maintenance']);

// Seriennummern bei Multi-Device
$serialNumbers = [];
if ($marker['is_multi_device']) {
    $serialNumbers = getMarkerSerialNumbers($id, $pdo);
}

// Custom Fields Werte
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .qr-code-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .qr-code-box .qr-code {
            font-size: 32px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
        }
        
        .qr-actions {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><?= e($marker['name']) ?></h1>
                <div class="page-actions">
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
                           onclick="return confirm('Marker wirklich löschen?')">
                            <i class="fas fa-trash"></i> Löschen
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- QR-Code Anzeige -->
            <div class="qr-code-box">
                <div><i class="fas fa-qrcode" style="font-size: 48px;"></i></div>
                <div class="qr-code"><?= e($marker['qr_code']) ?></div>
                <div class="qr-actions">
                    <a href="print_qr.php?id=<?= $marker['id'] ?>" class="btn btn-light btn-sm" target="_blank">
                        <i class="fas fa-print"></i> Drucken
                    </a>
                    <?php if ($marker['public_token']): ?>
                        <a href="public_view.php?token=<?= $marker['public_token'] ?>" class="btn btn-light btn-sm" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Öffentliche Ansicht
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
                    <h3><i class="fas fa-wrench"></i> Nächste Wartung</h3>
                    <div class="value">
                        <?= formatDate($marker['next_maintenance']) ?>
                        <br>
                        <span class="badge badge-<?= $maintenanceStatus['class'] ?>">
                            <?= $maintenanceStatus['label'] ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!$marker['is_storage'] && !$marker['is_multi_device']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-gas-pump"></i> Kraftstoff</h3>
                    <div class="value"><?= $marker['fuel_level'] ?>%</div>
                    <div class="progress" style="margin-top: 10px;">
                        <div class="progress-bar" style="width: <?= $marker['fuel_level'] ?>%"></div>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-clock"></i> Betriebsstunden</h3>
                    <div class="value"><?= number_format($marker['operating_hours'], 2) ?> h</div>
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
            
            <!-- Custom Fields -->
            <?php if (!empty($customFieldValues)): ?>
            <div class="section">
                <h2><i class="fas fa-list"></i> Zusätzliche Informationen</h2>
                <table class="info-table">
                    <?php foreach ($customFieldValues as $field): ?>
                    <tr>
                        <th><?= e($field['field_label']) ?></th>
                        <td><?= e($field['field_value']) ?></td>
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
                        <div class="document-item <?= $doc['is_public'] ? 'public' : '' ?>">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <strong><?= e($doc['document_name']) ?></strong>
                                <?php if ($doc['is_public']): ?>
                                    <span class="badge badge-success" style="margin-left: 10px;">
                                        <i class="fas fa-globe"></i> Öffentlich
                                    </span>
                                <?php endif; ?>
                                <br>
                                <small>
                                    <?= number_format($doc['file_size'] / 1024 / 1024, 2) ?> MB | 
                                    <?= formatDateTime($doc['uploaded_at']) ?>
                                </small>
                                <?php if ($doc['is_public'] && $doc['public_description']): ?>
                                    <br>
                                    <small style="color: #666;">
                                        <i class="fas fa-info-circle"></i> <?= e($doc['public_description']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="document-actions">
                                <a href="<?= e($doc['document_path']) ?>" target="_blank" class="btn btn-sm btn-info">
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
                             onclick="window.open(this.src, '_blank')" style="cursor: pointer;">
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
                            <th>Durchgeführt von</th>
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
            <div class="section">
                <h2><i class="fas fa-map-marker-alt"></i> Standort</h2>
                <div id="map" style="height: 400px; border-radius: 10px;"></div>
                <p style="margin-top: 10px; color: #666;">
                    <i class="fas fa-map-pin"></i> 
                    <?= number_format($marker['latitude'], 6) ?>, <?= number_format($marker['longitude'], 6) ?>
                </p>
            </div>
            
            <!-- Kommentare -->
            <div class="section">
                <h2><i class="fas fa-comments"></i> Kommentare</h2>
                
                <?php if (hasPermission('comments_add')): ?>
                <form method="POST" action="add_comment.php" class="comment-form">
                    <input type="hidden" name="marker_id" value="<?= $marker['id'] ?>">
                    <textarea name="comment" placeholder="Kommentar hinzufügen..." required></textarea>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Kommentar hinzufügen
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="comments-list">
                    <?php if (empty($comments)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">
                            Noch keine Kommentare vorhanden
                        </p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-header">
                                    <strong><?= e($comment['username'] ?? 'Unbekannt') ?></strong>
                                    <span class="comment-date"><?= formatDateTime($comment['created_at']) ?></span>
                                </div>
                                <div class="comment-body">
                                    <?= nl2br(e($comment['comment'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Karte initialisieren
        const map = L.map('map').setView([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Marker hinzufügen
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
</body>
</html>