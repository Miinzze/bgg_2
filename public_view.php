<?php
require_once 'config.php';
require_once 'functions.php';

// Kein Login erforderlich - öffentliche Ansicht!
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
    WHERE m.public_token = ? AND m.deleted_at IS NULL AND m.is_activated = 1
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

// Mobile Detection
$isMobile = isMobileDevice();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($marker['name']) ?> - Öffentliche Ansicht</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            color: var(--text-color);
            background-color: #f5f5f5;
            line-height: 1.6;
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
            background: linear-gradient(135deg, #e63216ff 0%, #9c210eff 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .header .qr-code {
            font-family: 'Courier New', monospace;
            font-size: 20px;
            opacity: 0.95;
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
        }
        
        .mobile-login-button {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #e63216ff 0%, #9c210eff 100%);
            color: white;
            border: none;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
            cursor: pointer;
            font-size: 24px;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-login-button:active {
            transform: scale(0.95);
        }
        
        @media (max-width: 768px) {
            .mobile-login-button {
                display: flex;
            }
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border-left: 4px solid #e63216ff;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
        }
        
        .info-card h3 {
            color: #6c757d;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .info-card .value {
            font-size: 24px;
            color: #212529;
            font-weight: bold;
        }
        
        .badge {
            display: inline-block;
            padding: 8px 16px;
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
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #e9ecef;
            font-size: 22px;
            font-weight: 600;
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
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            border: 2px solid #e9ecef;
        }
        
        .image-gallery img:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        .document-list {
            display: grid;
            gap: 15px;
        }
        
        .document-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .document-item:hover {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateX(5px);
        }
        
        .document-icon {
            font-size: 48px;
            color: #dc3545;
            min-width: 60px;
            text-align: center;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-info h4 {
            margin-bottom: 8px;
            color: #212529;
            font-size: 18px;
        }
        
        .document-info p {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .document-item a {
            padding: 12px 24px;
            background: linear-gradient(135deg, #e63216ff 0%, #9c210eff 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .document-item a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .serial-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
        }
        
        .serial-item {
            padding: 15px;
            background: white;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 4px solid #e63216ff;
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: 600;
        }
        
        .serial-item:last-child {
            margin-bottom: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            border-top: 1px solid #e9ecef;
        }
        
        .footer p {
            margin: 8px 0;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .content {
                padding: 25px 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .document-item {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-info-circle"></i> <?= e($marker['name']) ?></h1>
            <div class="qr-code">QR: <?= e($marker['qr_code']) ?></div>
        </div>
        
        <!-- Mobiler Login-Button -->
        <?php if ($isMobile): ?>
        <a href="login.php?redirect=edit_marker.php?id=<?= $marker['id'] ?>" class="mobile-login-button" title="Anmelden & Bearbeiten">
            <i class="fas fa-sign-in-alt"></i>
        </a>
        <?php endif; ?>
        
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
                    <div class="value" style="font-family: 'Courier New', monospace;"><?= e($marker['serial_number']) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!$marker['is_storage'] && !$marker['is_multi_device'] && $marker['next_maintenance']): ?>
                <div class="info-card">
                    <h3><i class="fas fa-wrench"></i> Wartung</h3>
                    <div class="value" style="font-size: 16px;">
                        <span class="badge badge-<?= $maintenanceStatus['class'] ?>">
                            <?= $maintenanceStatus['label'] ?>
                        </span>
                        <div style="margin-top: 10px; font-size: 14px; color: #6c757d;">
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
                            <i class="fas fa-barcode" style="font-size: 20px; color: #667eea;"></i> 
                            <?= e($sn['serial_number']) ?>
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
                                    <p><i class="fas fa-info-circle"></i> <?= e($doc['public_description']) ?></p>
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
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                <i class="fas fa-calendar"></i> 
                Erstellt am <?= formatDate($marker['created_at'], 'd.m.Y') ?>
                <?php if ($marker['created_by_name']): ?>
                    von <?= e($marker['created_by_name']) ?>
                <?php endif; ?>
            </p>
            <?php if ($isMobile): ?>
            <p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef;">
                <i class="fas fa-info-circle"></i> 
                <a href="login.php?redirect=edit_marker.php?id=<?= $marker['id'] ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                    Anmelden um diesen Marker zu bearbeiten
                </a>
            </p>
            <?php endif; ?>
            <p style="margin-top: 15px; font-size: 12px; opacity: 0.7;">
                <i class="fas fa-qrcode"></i> Marker System - Öffentliche Ansicht
            </p>
        </div>
    </div>
</body>
</html>