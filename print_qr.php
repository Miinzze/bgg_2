<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

// Zwei Modi: Marker-ID ODER QR-Code direkt
$markerId = $_GET['id'] ?? 0;
$qrCode = $_GET['code'] ?? '';

$marker = null;
$isBlankCode = false;

if ($markerId) {
    // Marker mit ID laden
    $marker = getMarkerById($markerId, $pdo);
    if (!$marker) {
        die('Marker nicht gefunden');
    }
    $qrCode = $marker['qr_code'];
    $publicUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/public_view.php?token=' . $marker['public_token'];
} elseif ($qrCode) {
    // Blanko QR-Code drucken (noch nicht zugewiesen)
    $stmt = $pdo->prepare("SELECT * FROM qr_code_pool WHERE qr_code = ?");
    $stmt->execute([$qrCode]);
    $poolCode = $stmt->fetch();
    
    if (!$poolCode) {
        die('QR-Code nicht gefunden');
    }
    
    $isBlankCode = true;
    $publicUrl = $qrCode; // Nur der Code selbst
} else {
    die('Keine ID oder QR-Code angegeben');
}

$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data=' . urlencode($publicUrl);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-Code - <?= $isBlankCode ? e($qrCode) : e($marker['name']) ?></title>
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
        
        .qr-code-number {
            font-size: 28px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            color: #1976d2;
            margin: 20px 0;
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
        
        .blank-code-instructions {
            font-size: 16px;
            color: #1976d2;
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #1976d2;
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
        <?php if ($isBlankCode): ?>
            <!-- Blanko QR-Code -->
            <div class="qr-code-number"><?= e($qrCode) ?></div>
            
            <div class="blank-code-instructions">
                <strong>BLANKO QR-CODE</strong><br>
                Noch nicht zugewiesen
            </div>
            
            <div class="qr-code">
                <img src="<?= $qrApiUrl ?>" alt="QR Code">
            </div>
            
            <div class="instructions">
                <strong>So aktivieren Sie diesen QR-Code:</strong><br>
                1. QR-Code am Gerät/Standort anbringen<br>
                2. Mit Smartphone scannen<br>
                3. GPS-Position erfassen<br>
                4. Gerätedaten eingeben<br>
                5. Marker wird automatisch aktiviert
            </div>
            
        <?php else: ?>
            <!-- Zugewiesener Marker -->
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
                
                QR-Code: <code style="font-family: 'Courier New', monospace; font-weight: bold;"><?= e($marker['qr_code']) ?></code>
            </div>
            
            <div class="qr-code">
                <img src="<?= $qrApiUrl ?>" alt="QR Code">
            </div>
            
            <div class="instructions">
                <strong>Scannen Sie diesen QR-Code mit Ihrem Smartphone</strong><br>
                für sofortigen Zugriff auf alle Geräteinformationen,<br>
                Wartungshistorie und Standortdaten.
            </div>
        <?php endif; ?>
        
        <div class="footer">
            Marker System<br>
            Erstellt am: <?= date('d.m.Y H:i') ?> Uhr<br>
            <?php if (!$isBlankCode): ?>
                Marker-ID: <?= $marker['id'] ?>
            <?php endif; ?>
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