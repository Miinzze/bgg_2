<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('markers_edit');

$markers = getAllMarkers($pdo);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>QR-Codes Export</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        
        .qr-item {
            border: 2px solid #ddd;
            padding: 15px;
            text-align: center;
            background: white;
            border-radius: 8px;
            page-break-inside: avoid;
        }
        
        .qr-item h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #e63312;
        }
        
        .qr-item img {
            max-width: 100%;
            border: 1px solid #ddd;
            padding: 10px;
            background: #f8f9fa;
        }
        
        .qr-item .info {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            .qr-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-qrcode"></i> QR-Codes für alle Marker</h1>
                <div class="header-actions no-print">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Drucken
                    </button>
                    <a href="markers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>
            
            <div class="qr-grid">
                <?php foreach ($markers as $marker): ?>
                    <?php
                    // Öffentliche URL statt normale View
                    $publicUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/public_view.php?token=' . $marker['public_token'];
                    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=5&data=' . urlencode($publicUrl);
                    ?>
                    <div class="qr-item">
                        <h3><?= e($marker['name']) ?></h3>
                        <img src="<?= $qrApiUrl ?>" alt="QR Code">
                        <div class="info">
                            <?= e($marker['category'] ?? 'Keine Kategorie') ?><br>
                            ID: <?= $marker['id'] ?>
                            <?php if ($marker['serial_number']): ?>
                                <br>SN: <?= e($marker['serial_number']) ?>
                            <?php endif; ?>
                            <br><small style="color: #007bff;"><i class="fas fa-lock-open"></i> Öffentlich</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>