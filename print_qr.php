<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

$publicUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/public_view.php?token=' . $marker['public_token'];
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data=' . urlencode($publicUrl);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-Code - <?= e($marker['name']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        
        .print-container {
            max-width: 600px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 30px;
            border-radius: 10px;
        }
        
        .marker-name {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #e63312;
        }
        
        .marker-info {
            font-size: 18px;
            margin-bottom: 30px;
            color: #666;
        }
        
        .qr-code {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .qr-code img {
            max-width: 400px;
            border: 3px solid #333;
            padding: 15px;
            background: white;
        }
        
        .instructions {
            font-size: 14px;
            color: #666;
            margin-top: 20px;
            line-height: 1.6;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            font-size: 12px;
            color: #999;
        }
        
        .no-print {
            margin-top: 30px;
        }
        
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="marker-name">
            <?= e($marker['name']) ?>
        </div>
        
        <div class="marker-info">
            <?php if ($marker['category']): ?>
                Kategorie: <?= e($marker['category']) ?><br>
            <?php endif; ?>
            
            <?php if ($marker['serial_number']): ?>
                Seriennummer: <?= e($marker['serial_number']) ?><br>
            <?php endif; ?>
            
            RFID: <?= e($marker['rfid_chip']) ?>
        </div>
        
        <div class="qr-code">
            <img src="<?= $qrApiUrl ?>" alt="QR Code">
        </div>
        
        <div class="instructions">
            <strong>Scannen Sie diesen QR-Code mit Ihrem Smartphone</strong><br>
            für sofortigen Zugriff auf alle Geräteinformationen,<br>
            Wartungshistorie und Standortdaten.
        </div>
        
        <div class="footer">
            RFID Marker System<br>
            Erstellt am: <?= date('d.m.Y H:i') ?> Uhr<br>
            ID: <?= $marker['id'] ?>
        </div>
    </div>
    
    <div class="no-print">
        <button onclick="window.print()" style="padding: 15px 30px; font-size: 16px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px; margin: 10px;">
            <i class="fas fa-print"></i> Drucken
        </button>
        <button onclick="window.close()" style="padding: 15px 30px; font-size: 16px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px; margin: 10px;">
            Schließen
        </button>
    </div>
</body>
</html>