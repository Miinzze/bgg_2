<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$batch = $_GET['batch'] ?? '';

if (empty($batch)) {
    die('Kein Batch angegeben');
}

// QR-Codes des Batches abrufen
$stmt = $pdo->prepare("
    SELECT qr_code, created_at, is_assigned
    FROM qr_code_pool 
    WHERE print_batch = ?
    ORDER BY qr_code ASC
");
$stmt->execute([$batch]);
$codes = $stmt->fetchAll();

if (empty($codes)) {
    die('Batch nicht gefunden oder keine Codes vorhanden');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-Codes drucken - <?= e($batch) ?></title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: white;
        }
        
        .print-header {
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        
        .print-header h1 {
            color: #e63312;
            margin-bottom: 5px;
        }
        
        .batch-info {
            color: #666;
            font-size: 14px;
        }
        
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 10px;
        }
        
        .qr-item {
            text-align: center;
            padding: 15px;
            border: 2px solid #333;
            border-radius: 8px;
            background: white;
            page-break-inside: avoid;
        }
        
        .qr-code-number {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            font-family: 'Courier New', monospace;
        }
        
        .qr-image {
            width: 120px;
            height: 120px;
            margin: 10px auto;
            padding: 5px;
            background: white;
            border: 1px solid #ddd;
        }
        
        .qr-instructions {
            font-size: 10px;
            color: #666;
            margin-top: 8px;
            line-height: 1.3;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            margin-top: 5px;
        }
        
        .status-available {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #2e7d32;
        }
        
        .status-assigned {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #e65100;
        }
        
        .no-print {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-header {
                border-bottom: 3px solid #000;
            }
            
            .qr-item {
                border: 3px solid #000;
            }
        }
        
        @media screen {
            body {
                background: #f5f5f5;
                padding: 20px;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary" style="padding: 15px 30px; font-size: 16px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px; margin-right: 10px;">
            <i class="fas fa-print"></i> Drucken
        </button>
        <button onclick="window.close()" class="btn btn-secondary" style="padding: 15px 30px; font-size: 16px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px;">
            Schließen
        </button>
    </div>
    
    <div class="container">
        <div class="print-header">
            <h1>QR-Code Druckvorlage</h1>
            <div class="batch-info">
                Batch: <?= e($batch) ?> | 
                Anzahl: <?= count($codes) ?> Codes | 
                Erstellt am: <?= formatDate($codes[0]['created_at'], 'd.m.Y H:i') ?>
            </div>
        </div>
        
        <div class="qr-grid">
            <?php foreach ($codes as $code): ?>
            <div class="qr-item">
                <div class="qr-code-number"><?= e($code['qr_code']) ?></div>
                
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=5&data=<?= urlencode($code['qr_code']) ?>" 
                     alt="QR Code <?= e($code['qr_code']) ?>"
                     class="qr-image">
                
                <div class="qr-instructions">
                    Scannen zum<br>Aktivieren
                </div>
                
                <span class="status-badge <?= $code['is_assigned'] ? 'status-assigned' : 'status-available' ?>">
                    <?= $code['is_assigned'] ? 'ZUGEWIESEN' : 'VERFÜGBAR' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center; font-size: 12px; color: #999;">
            QR-Code Marker System | <?= date('d.m.Y H:i') ?> Uhr
        </div>
    </div>
    
    <script>
        // Automatisch Druckdialog öffnen beim Laden (optional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // };
    </script>
</body>
</html>