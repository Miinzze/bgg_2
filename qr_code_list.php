<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$message = '';
$messageType = '';

// QR-Code löschen
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    validateCSRF();
    
    $qrCode = $_GET['delete'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM qr_code_pool WHERE qr_code = ?");
        $stmt->execute([$qrCode]);
        $qrInfo = $stmt->fetch();
        
        if (!$qrInfo) {
            throw new Exception('QR-Code nicht gefunden');
        }
        
        if ($qrInfo['is_assigned']) {
            throw new Exception('QR-Code ist einem Marker zugewiesen und kann nicht gelöscht werden. Löschen Sie zuerst den Marker.');
        }
        
        $stmt = $pdo->prepare("DELETE FROM qr_code_pool WHERE qr_code = ?");
        $stmt->execute([$qrCode]);
        
        $pdo->commit();
        
        logActivity('qr_code_deleted', "QR-Code '$qrCode' gelöscht");
        
        $message = "QR-Code erfolgreich gelöscht!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Fehler: ' . e($e->getMessage());
        $messageType = 'danger';
    }
}

$filter = $_GET['filter'] ?? 'all';
$batch = $_GET['batch'] ?? '';

// Basis-Query
$sql = "SELECT qcp.*, m.name as marker_name, m.id as marker_id
        FROM qr_code_pool qcp
        LEFT JOIN markers m ON qcp.marker_id = m.id AND m.deleted_at IS NULL
        WHERE 1=1";

$params = [];

// Filter anwenden
if ($filter === 'available') {
    $sql .= " AND qcp.is_assigned = 0";
} elseif ($filter === 'assigned') {
    $sql .= " AND qcp.is_assigned = 1 AND qcp.is_activated = 0";
} elseif ($filter === 'activated') {
    $sql .= " AND qcp.is_activated = 1";
}

// Batch-Filter
if (!empty($batch)) {
    $sql .= " AND qcp.print_batch = ?";
    $params[] = $batch;
}

$sql .= " ORDER BY qcp.qr_code ASC";

// Paginierung
$page = $_GET['page'] ?? 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Gesamtanzahl ermitteln
$countStmt = $pdo->prepare(str_replace('qcp.*, m.name as marker_name, m.id as marker_id', 'COUNT(*)', $sql));
$countStmt->execute($params);
$totalCodes = $countStmt->fetchColumn();
$totalPages = ceil($totalCodes / $perPage);

// Daten abrufen
$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$codes = $stmt->fetchAll();

// Statistiken für alle Codes
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_assigned = 0 THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN is_assigned = 1 AND is_activated = 0 THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN is_activated = 1 THEN 1 ELSE 0 END) as activated
    FROM qr_code_pool
")->fetch();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-Code Verwaltung - Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dark-mode.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 8px var(--shadow);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-card .value {
            font-size: 42px;
            font-weight: bold;
            color: var(--text-color);
        }
        
        .qr-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .qr-status.available {
            background: #d4edda;
            color: #155724;
        }
        
        .qr-status.assigned {
            background: #fff3cd;
            color: #856404;
        }
        
        .qr-status.activated {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        body.dark-mode .qr-status.available {
            background: #1a4d2e;
            color: #9ff0c7;
        }
        
        body.dark-mode .qr-status.assigned {
            background: #4d3d1a;
            color: #ffd89f;
        }
        
        body.dark-mode .qr-status.activated {
            background: #1a3a52;
            color: #9fd3ff;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
            }
            
            .button-group .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-qrcode"></i> QR-Code Verwaltung</h1>
                <div class="header-actions">
                    <a href="qr_code_generator.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Neue QR-Codes generieren
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <!-- Statistiken -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-qrcode"></i> Gesamt</h3>
                    <div class="value"><?= $stats['total'] ?></div>
                </div>
                
                <div class="stat-card" style="border-left-color: #28a745;">
                    <h3><i class="fas fa-circle"></i> Verfügbar</h3>
                    <div class="value"><?= $stats['available'] ?></div>
                </div>
                
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <h3><i class="fas fa-link"></i> Zugewiesen</h3>
                    <div class="value"><?= $stats['assigned'] ?></div>
                </div>
                
                <div class="stat-card" style="border-left-color: #17a2b8;">
                    <h3><i class="fas fa-check-circle"></i> Aktiviert</h3>
                    <div class="value"><?= $stats['activated'] ?></div>
                </div>
            </div>
            
            <!-- Filter -->
            <div class="button-group" style="margin-bottom: 20px;">
                <a href="qr_code_list.php?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                    <i class="fas fa-list"></i> Alle (<?= $stats['total'] ?>)
                </a>
                <a href="qr_code_list.php?filter=available" class="btn <?= $filter === 'available' ? 'btn-success' : 'btn-secondary' ?>">
                    <i class="fas fa-circle"></i> Verfügbar (<?= $stats['available'] ?>)
                </a>
                <a href="qr_code_list.php?filter=assigned" class="btn <?= $filter === 'assigned' ? 'btn-warning' : 'btn-secondary' ?>">
                    <i class="fas fa-link"></i> Zugewiesen (<?= $stats['assigned'] ?>)
                </a>
                <a href="qr_code_list.php?filter=activated" class="btn <?= $filter === 'activated' ? 'btn-info' : 'btn-secondary' ?>">
                    <i class="fas fa-check-circle"></i> Aktiviert (<?= $stats['activated'] ?>)
                </a>
            </div>
            
            <?php if (!empty($batch)): ?>
                <div class="alert alert-info">
                    <strong><i class="fas fa-filter"></i> Batch-Filter aktiv:</strong> <?= e($batch) ?>
                    <a href="qr_code_list.php" style="margin-left: 10px;" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Filter entfernen
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Tabelle -->
            <div class="section">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">QR</th>
                                <th>QR-Code</th>
                                <th>Status</th>
                                <th>Marker</th>
                                <th>Batch</th>
                                <th>Erstellt</th>
                                <th>Zugewiesen am</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($codes)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i><br>
                                    Keine QR-Codes gefunden
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($codes as $code): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=50x50&data=<?= urlencode($code['qr_code']) ?>" 
                                         alt="QR" style="width: 40px; height: 40px; border-radius: 4px;">
                                </td>
                                <td>
                                    <code style="font-size: 16px; font-weight: 600; font-family: 'Courier New', monospace;">
                                        <?= e($code['qr_code']) ?>
                                    </code>
                                </td>
                                <td>
                                    <?php if ($code['is_activated']): ?>
                                        <span class="qr-status activated">
                                            <i class="fas fa-check-circle"></i> Aktiviert
                                        </span>
                                    <?php elseif ($code['is_assigned']): ?>
                                        <span class="qr-status assigned">
                                            <i class="fas fa-link"></i> Zugewiesen
                                        </span>
                                    <?php else: ?>
                                        <span class="qr-status available">
                                            <i class="fas fa-circle"></i> Verfügbar
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($code['marker_id']): ?>
                                        <a href="view_marker.php?id=<?= $code['marker_id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> <?= e($code['marker_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($code['print_batch']): ?>
                                        <a href="qr_code_list.php?batch=<?= urlencode($code['print_batch']) ?>" 
                                           style="color: var(--text-color); text-decoration: underline;">
                                            <?= e($code['print_batch']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= formatDateTime($code['created_at']) ?></small>
                                </td>
                                <td>
                                    <?php if ($code['assigned_at']): ?>
                                        <small><?= formatDateTime($code['assigned_at']) ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="print_qr.php?code=<?= urlencode($code['qr_code']) ?>" 
                                           class="btn btn-sm btn-secondary" target="_blank" title="QR-Code drucken">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        
                                        <?php if ($code['marker_id']): ?>
                                            <a href="view_marker.php?id=<?= $code['marker_id'] ?>" 
                                               class="btn btn-sm btn-info" title="Marker anzeigen">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php else: ?>
                                            <button onclick="deleteQRCode('<?= e($code['qr_code']) ?>')" 
                                                    class="btn btn-sm btn-danger" title="QR-Code löschen">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Paginierung -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination" style="margin-top: 20px; text-align: center; display: flex; justify-content: center; align-items: center; gap: 15px;">
                <?php if ($page > 1): ?>
                    <a href="?filter=<?= $filter ?>&batch=<?= urlencode($batch) ?>&page=<?= $page - 1 ?>" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Zurück
                    </a>
                <?php endif; ?>
                
                <span style="color: var(--text-secondary); font-weight: 600;">
                    Seite <?= $page ?> von <?= $totalPages ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?filter=<?= $filter ?>&batch=<?= urlencode($batch) ?>&page=<?= $page + 1 ?>" class="btn btn-secondary">
                        Weiter <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Info Box -->
            <div class="alert alert-info" style="margin-top: 30px;">
                <h3><i class="fas fa-info-circle"></i> Hinweise zur QR-Code Verwaltung</h3>
                <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                    <li><strong>Verfügbar:</strong> QR-Code wurde generiert, aber noch keinem Marker zugewiesen</li>
                    <li><strong>Zugewiesen:</strong> QR-Code wurde einem Marker im Büro zugewiesen, aber noch nicht vor Ort aktiviert</li>
                    <li><strong>Aktiviert:</strong> QR-Code wurde vor Ort gescannt und der Marker ist vollständig aktiv</li>
                    <li><strong>Löschen:</strong> Nur nicht zugewiesene QR-Codes können gelöscht werden</li>
                </ul>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        function deleteQRCode(qrCode) {
            if (confirm('QR-Code "' + qrCode + '" wirklich löschen?\n\nDieser Vorgang kann nicht rückgängig gemacht werden!')) {
                window.location.href = 'qr_code_list.php?delete=' + encodeURIComponent(qrCode) + 
                                      '&confirm=1&csrf_token=<?= $_SESSION['csrf_token'] ?>';
            }
        }
    </script>
    
    <script src="js/dark-mode.js"></script>
</body>
</html>