<?php

require_once 'config.php';
require_once 'functions.php';
requireAdmin(); // Nur Admins können QR-Codes generieren

$message = '';
$messageType = '';
$generatedCodes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    validateCSRF();
    
    $count = intval($_POST['count'] ?? 0);
    $startNumber = intval($_POST['start_number'] ?? 1);
    $prefix = trim($_POST['prefix'] ?? 'QR');
    
    // Validierung
    if ($count < 1 || $count > 1000) {
        $message = 'Anzahl muss zwischen 1 und 1000 liegen';
        $messageType = 'danger';
    } elseif ($startNumber < 1) {
        $message = 'Startnummer muss mindestens 1 sein';
        $messageType = 'danger';
    } elseif (empty($prefix) || strlen($prefix) > 10) {
        $message = 'Prefix ist erforderlich (max. 10 Zeichen)';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            $batchId = 'BATCH_' . date('Y-m-d_His');
            $created = 0;
            $skipped = 0;
            
            for ($i = 0; $i < $count; $i++) {
                $number = $startNumber + $i;
                $qrCode = $prefix . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
                
                // Prüfen ob QR-Code bereits existiert
                $stmt = $pdo->prepare("SELECT id FROM qr_code_pool WHERE qr_code = ?");
                $stmt->execute([$qrCode]);
                
                if ($stmt->fetch()) {
                    $skipped++;
                    continue;
                }
                
                // QR-Code in Pool einfügen
                $stmt = $pdo->prepare("
                    INSERT INTO qr_code_pool (qr_code, print_batch) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$qrCode, $batchId]);
                $created++;
                
                $generatedCodes[] = $qrCode;
            }
            
            $pdo->commit();
            
            logActivity('qr_codes_generated', "Batch '$batchId': $created QR-Codes erstellt, $skipped übersprungen");
            
            $message = "✓ Erfolgreich $created QR-Codes erstellt!";
            if ($skipped > 0) {
                $message .= " ($skipped bereits vorhanden, übersprungen)";
            }
            $messageType = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Fehler: ' . e($e->getMessage());
            $messageType = 'danger';
        }
    }
}

// Statistiken abrufen
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(is_assigned) as assigned,
        COUNT(*) - SUM(is_assigned) as available
    FROM qr_code_pool
")->fetch();

// Letzte Batches
$batches = $pdo->query("
    SELECT 
        print_batch,
        COUNT(*) as count,
        MIN(created_at) as created_at,
        SUM(is_assigned) as assigned_count
    FROM qr_code_pool
    WHERE print_batch IS NOT NULL
    GROUP BY print_batch
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-Code Generator - Marker System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-qrcode"></i> QR-Code Generator</h1>
                <p>Erstellen Sie Blanko QR-Codes zum Vordrucken</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <!-- Statistiken -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="stat-card" style="background: #e3f2fd; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #1976d2;"><?= $stats['total'] ?></div>
                    <div style="color: #666; margin-top: 5px;">Gesamt QR-Codes</div>
                </div>
                
                <div class="stat-card" style="background: #e8f5e9; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #388e3c;"><?= $stats['available'] ?></div>
                    <div style="color: #666; margin-top: 5px;">Verfügbar</div>
                </div>
                
                <div class="stat-card" style="background: #fff3e0; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #f57c00;"><?= $stats['assigned'] ?></div>
                    <div style="color: #666; margin-top: 5px;">Zugewiesen</div>
                </div>
            </div>
            
            <!-- Generator-Formular -->
            <div class="form-section">
                <h2><i class="fas fa-magic"></i> Neue QR-Codes generieren</h2>
                
                <form method="POST" class="marker-form">
                    <?= csrf_field() ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prefix">Prefix</label>
                            <input type="text" id="prefix" name="prefix" value="QR" required maxlength="10">
                            <small>z.B. "QR" für QR-0001, QR-0002, ...</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_number">Startnummer</label>
                            <input type="number" id="start_number" name="start_number" value="1" min="1" required>
                            <small>Ab welcher Nummer beginnen?</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="count">Anzahl</label>
                        <input type="number" id="count" name="count" value="10" min="1" max="1000" required>
                        <small>Wie viele QR-Codes sollen erstellt werden? (max. 1000)</small>
                    </div>
                    
                    <div style="padding: 15px; background: #e3f2fd; border-left: 4px solid #1976d2; margin: 20px 0; border-radius: 4px;">
                        <strong><i class="fas fa-info-circle"></i> Vorschau:</strong><br>
                        <span id="preview">Es werden Codes von QR-0001 bis QR-0010 erstellt</span>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="generate" class="btn btn-primary btn-large">
                            <i class="fas fa-magic"></i> QR-Codes generieren
                        </button>
                        <a href="index.php" class="btn btn-secondary">Abbrechen</a>
                    </div>
                </form>
            </div>
            
            <!-- Generierte Codes anzeigen (wenn gerade erstellt) -->
            <?php if (!empty($generatedCodes)): ?>
            <div class="form-section">
                <h2><i class="fas fa-check-circle"></i> Neu generierte QR-Codes</h2>
                <p>Diese Codes können jetzt gedruckt und verwendet werden:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                    <?php foreach (array_slice($generatedCodes, 0, 50) as $code): ?>
                        <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 8px; border: 2px solid #dee2e6;">
                            <strong style="display: block; margin-bottom: 5px; color: #495057;"><?= e($code) ?></strong>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode($code) ?>" 
                                 alt="<?= e($code) ?>" 
                                 style="width: 100px; height: 100px;">
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($generatedCodes) > 50): ?>
                    <p style="text-align: center; margin-top: 20px; color: #666;">
                        ... und <?= count($generatedCodes) - 50 ?> weitere
                    </p>
                <?php endif; ?>
                
                <div style="margin-top: 30px; text-align: center;">
                    <a href="qr_code_print_batch.php?batch=<?= urlencode($batchId) ?>" class="btn btn-success btn-large" target="_blank">
                        <i class="fas fa-print"></i> Alle <?= count($generatedCodes) ?> Codes drucken
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Letzte Batches -->
            <?php if (!empty($batches)): ?>
            <div class="form-section">
                <h2><i class="fas fa-history"></i> Letzte Generierungen</h2>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Batch-ID</th>
                            <th>Erstellt am</th>
                            <th>Anzahl</th>
                            <th>Zugewiesen</th>
                            <th>Verfügbar</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><code><?= e($batch['print_batch']) ?></code></td>
                            <td><?= formatDateTime($batch['created_at']) ?></td>
                            <td><?= $batch['count'] ?></td>
                            <td><span style="color: #f57c00; font-weight: bold;"><?= $batch['assigned_count'] ?></span></td>
                            <td><span style="color: #388e3c; font-weight: bold;"><?= $batch['count'] - $batch['assigned_count'] ?></span></td>
                            <td>
                                <a href="qr_code_print_batch.php?batch=<?= urlencode($batch['print_batch']) ?>" 
                                   class="btn btn-sm btn-info" target="_blank" title="Batch drucken">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a href="qr_code_list.php?batch=<?= urlencode($batch['print_batch']) ?>" 
                                   class="btn btn-sm btn-secondary" title="Batch anzeigen">
                                    <i class="fas fa-list"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Pool-Übersicht -->
            <div class="form-section">
                <h2><i class="fas fa-warehouse"></i> QR-Code Pool verwalten</h2>
                
                <div class="button-group">
                    <a href="qr_code_list.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Alle QR-Codes anzeigen
                    </a>
                    <a href="qr_code_list.php?filter=available" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Verfügbare Codes (<?= $stats['available'] ?>)
                    </a>
                    <a href="qr_code_list.php?filter=assigned" class="btn btn-warning">
                        <i class="fas fa-tag"></i> Zugewiesene Codes (<?= $stats['assigned'] ?>)
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Live-Vorschau der generierten Codes
        function updatePreview() {
            const prefix = document.getElementById('prefix').value || 'QR';
            const start = parseInt(document.getElementById('start_number').value) || 1;
            const count = parseInt(document.getElementById('count').value) || 1;
            
            const end = start + count - 1;
            const startCode = prefix + '-' + String(start).padStart(4, '0');
            const endCode = prefix + '-' + String(end).padStart(4, '0');
            
            document.getElementById('preview').textContent = 
                `Es werden ${count} Codes von ${startCode} bis ${endCode} erstellt`;
        }
        
        // Event Listener
        document.getElementById('prefix').addEventListener('input', updatePreview);
        document.getElementById('start_number').addEventListener('input', updatePreview);
        document.getElementById('count').addEventListener('input', updatePreview);
        
        // Initiale Vorschau
        updatePreview();
    </script>
</body>
</html>