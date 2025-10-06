<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$markerId = $_GET['marker'] ?? 0;
$marker = getMarkerById($markerId, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

$message = '';
$messageType = '';

// Neue Prüfung hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inspection'])) {
    validateCSRF();
    
    $type = $_POST['inspection_type'] ?? '';
    $interval = intval($_POST['inspection_interval'] ?? 12);
    $lastInspection = $_POST['last_inspection'] ?? null;
    $authority = trim($_POST['inspection_authority'] ?? '');
    $certNumber = trim($_POST['certificate_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($type)) {
        $message = 'Bitte wählen Sie einen Prüftyp';
        $messageType = 'danger';
    } elseif (!validateInteger($interval, 1, 120)) {
        $message = 'Prüfintervall muss zwischen 1 und 120 Monaten liegen';
        $messageType = 'danger';
    } else {
        // Nächste Prüfung berechnen
        $nextInspection = null;
        if ($lastInspection) {
            $nextInspection = date('Y-m-d', strtotime($lastInspection . ' + ' . $interval . ' months'));
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO inspection_schedules 
                (marker_id, inspection_type, inspection_interval_months, last_inspection, 
                 next_inspection, inspection_authority, certificate_number, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $markerId, $type, $interval, $lastInspection, 
                $nextInspection, $authority, $certNumber, $notes
            ]);
            
            logActivity('inspection_added', "Prüffrist '{$type}' für Marker '{$marker['name']}' hinzugefügt", $markerId);
            
            $message = 'Prüffrist erfolgreich hinzugefügt!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Fehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Prüfung löschen
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM inspection_schedules WHERE id = ? AND marker_id = ?");
        $stmt->execute([$id, $markerId]);
        
        logActivity('inspection_deleted', "Prüffrist gelöscht", $markerId);
        
        $message = 'Prüffrist gelöscht!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Fehler: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Alle Prüfungen für diesen Marker
$stmt = $pdo->prepare("
    SELECT * FROM inspection_schedules 
    WHERE marker_id = ? 
    ORDER BY next_inspection ASC
");
$stmt->execute([$markerId]);
$inspections = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prüffristen - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .inspection-card {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid var(--info-color);
        }
        
        .inspection-card.overdue {
            border-left-color: var(--danger-color);
            background: #ffe6e6;
        }
        
        .inspection-card.due-soon {
            border-left-color: var(--warning-color);
            background: #fff9e6;
        }
        
        .inspection-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .inspection-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .inspection-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .inspection-detail-item {
            font-size: 14px;
        }
        
        .inspection-detail-label {
            font-size: 12px;
            color: var(--medium-gray);
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .inspection-detail-value {
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-clipboard-check"></i> Prüffristen</h1>
                    <h2><?= e($marker['name']) ?></h2>
                </div>
                <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <!-- Neue Prüfung hinzufügen -->
            <div class="info-card">
                <h2><i class="fas fa-plus"></i> Neue Prüffrist hinzufügen</h2>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="add_inspection" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="inspection_type">Prüftyp *</label>
                            <select id="inspection_type" name="inspection_type" required>
                                <option value="">Bitte wählen...</option>
                                <option value="TÜV">TÜV (Technischer Überwachungsverein)</option>
                                <option value="UVV">UVV (Unfallverhütungsvorschrift)</option>
                                <option value="DGUV">DGUV (Deutsche Gesetzliche Unfallversicherung)</option>
                                <option value="Sicherheitsprüfung">Sicherheitsprüfung</option>
                                <option value="Sonstiges">Sonstiges</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="inspection_interval">Prüfintervall (Monate) *</label>
                            <input type="number" id="inspection_interval" name="inspection_interval" 
                                   value="12" min="1" max="120" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="last_inspection">Letzte Prüfung</label>
                            <input type="date" id="last_inspection" name="last_inspection" 
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="inspection_authority">Prüfstelle / Prüfer</label>
                            <input type="text" id="inspection_authority" name="inspection_authority" 
                                   placeholder="z.B. TÜV Süd, Name des Prüfers">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="certificate_number">Zertifikatsnummer / Prüfnummer</label>
                        <input type="text" id="certificate_number" name="certificate_number" 
                               placeholder="z.B. TÜV-123456789">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notizen / Besonderheiten</label>
                        <textarea id="notes" name="notes" rows="3" 
                                  placeholder="Zusätzliche Informationen zur Prüfung..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Prüffrist hinzufügen
                    </button>
                </form>
            </div>
            
            <!-- Vorhandene Prüfungen -->
            <h2 style="margin-top: 30px;">
                <i class="fas fa-list"></i> Vorhandene Prüffristen
                <?php if (!empty($inspections)): ?>
                    <span class="badge badge-info"><?= count($inspections) ?></span>
                <?php endif; ?>
            </h2>
            
            <?php if (empty($inspections)): ?>
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> Noch keine Prüffristen erfasst</p>
                </div>
            <?php else: ?>
                <?php foreach ($inspections as $inspection): ?>
                    <?php
                    $status = 'ok';
                    $statusLabel = 'OK';
                    $statusClass = 'success';
                    
                    if ($inspection['next_inspection']) {
                        $daysUntil = (strtotime($inspection['next_inspection']) - time()) / 86400;
                        
                        if ($daysUntil < 0) {
                            $status = 'overdue';
                            $statusLabel = 'ÜBERFÄLLIG';
                            $statusClass = 'danger';
                        } elseif ($daysUntil <= 30) {
                            $status = 'due-soon';
                            $statusLabel = 'DEMNÄCHST FÄLLIG';
                            $statusClass = 'warning';
                        }
                    }
                    ?>
                    
                    <div class="inspection-card <?= $status ?>">
                        <div class="inspection-header">
                            <div>
                                <div class="inspection-title">
                                    <i class="fas fa-certificate"></i> <?= e($inspection['inspection_type']) ?>
                                </div>
                                <div style="margin-top: 8px;">
                                    <span class="badge badge-<?= $statusClass ?>"><?= $statusLabel ?></span>
                                </div>
                            </div>
                            <a href="?marker=<?= $markerId ?>&delete=<?= $inspection['id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Prüffrist wirklich löschen?')">
                                <i class="fas fa-trash"></i> Löschen
                            </a>
                        </div>
                        
                        <div class="inspection-details">
                            <div class="inspection-detail-item">
                                <div class="inspection-detail-label">Prüfintervall</div>
                                <div class="inspection-detail-value">
                                    Alle <?= $inspection['inspection_interval_months'] ?> Monate
                                </div>
                            </div>
                            
                            <?php if ($inspection['last_inspection']): ?>
                                <div class="inspection-detail-item">
                                    <div class="inspection-detail-label">Letzte Prüfung</div>
                                    <div class="inspection-detail-value">
                                        <?= date('d.m.Y', strtotime($inspection['last_inspection'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($inspection['next_inspection']): ?>
                                <div class="inspection-detail-item">
                                    <div class="inspection-detail-label">Nächste Prüfung</div>
                                    <div class="inspection-detail-value">
                                        <?= date('d.m.Y', strtotime($inspection['next_inspection'])) ?>
                                        <?php if (isset($daysUntil)): ?>
                                            <br><small>(<?= $daysUntil > 0 ? 'in ' . ceil($daysUntil) . ' Tagen' : 'vor ' . abs(floor($daysUntil)) . ' Tagen' ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($inspection['inspection_authority']): ?>
                                <div class="inspection-detail-item">
                                    <div class="inspection-detail-label">Prüfstelle</div>
                                    <div class="inspection-detail-value">
                                        <?= e($inspection['inspection_authority']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($inspection['certificate_number']): ?>
                                <div class="inspection-detail-item">
                                    <div class="inspection-detail-label">Zertifikatsnummer</div>
                                    <div class="inspection-detail-value">
                                        <?= e($inspection['certificate_number']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($inspection['notes']): ?>
                            <div style="margin-top: 15px; padding: 12px; background: white; border-radius: 5px;">
                                <strong>Notizen:</strong><br>
                                <?= nl2br(e($inspection['notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>