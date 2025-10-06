<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$filter = $_GET['filter'] ?? 'all';
$batch = $_GET['batch'] ?? '';

// Basis-Query
$sql = "SELECT qcp.*, m.name as marker_name, m.id as marker_id
        FROM qr_code_pool qcp
        LEFT JOIN markers m ON qcp.marker_id = m.id
        WHERE 1=1";

$params = [];

// Filter anwenden
if ($filter === 'available') {
    $sql .= " AND qcp.is_assigned = 0";
} elseif ($filter === 'assigned') {
    $sql .= " AND qcp.is_assigned = 1";
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-Code Liste - Marker System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-list"></i> QR-Code Übersicht</h1>
                <p>Alle QR-Codes im System</p>
            </div>
            
            <!-- Filter -->
            <div class="button-group" style="margin-bottom: 20px;">
                <a href="qr_code_list.php?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                    <i class="fas fa-list"></i> Alle (<?= $totalCodes ?>)
                </a>
                <a href="qr_code_list.php?filter=available" class="btn <?= $filter === 'available' ? 'btn-success' : 'btn-secondary' ?>">
                    <i class="fas fa-check-circle"></i> Verfügbar
                </a>
                <a href="qr_code_list.php?filter=assigned" class="btn <?= $filter === 'assigned' ? 'btn-warning' : 'btn-secondary' ?>">
                    <i class="fas fa-tag"></i> Zugewiesen
                </a>
                <a href="qr_code_generator.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Neue QR-Codes erstellen
                </a>
            </div>
            
            <?php if (!empty($batch)): ?>
                <div class="alert alert-info">
                    <strong>Batch-Filter aktiv:</strong> <?= e($batch) ?>
                    <a href="qr_code_list.php" style="margin-left: 10px;">Filter entfernen</a>
                </div>
            <?php endif; ?>
            
            <!-- Tabelle -->
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
                            <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px;"></i><br>
                                Keine QR-Codes gefunden
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($codes as $code): ?>
                        <tr>
                            <td style="text-align: center;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=50x50&data=<?= urlencode($code['qr_code']) ?>" 
                                     alt="QR" style="width: 40px; height: 40px;">
                            </td>
                            <td>
                                <code style="font-size: 16px; font-weight: bold;"><?= e($code['qr_code']) ?></code>
                            </td>
                            <td>
                                <?php if ($code['is_assigned']): ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-tag"></i> Zugewiesen
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Verfügbar
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($code['marker_id']): ?>
                                    <a href="view_marker.php?id=<?= $code['marker_id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> <?= e($code['marker_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($code['print_batch']): ?>
                                    <a href="qr_code_list.php?batch=<?= urlencode($code['print_batch']) ?>">
                                        <?= e($code['print_batch']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDateTime($code['created_at']) ?></td>
                            <td>
                                <?php if ($code['assigned_at']): ?>
                                    <?= formatDateTime($code['assigned_at']) ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="print_qr.php?code=<?= urlencode($code['qr_code']) ?>" 
                                   class="btn btn-sm btn-secondary" target="_blank" title="QR-Code drucken">
                                    <i class="fas fa-print"></i>
                                </a>
                                <?php if (!$code['is_assigned']): ?>
                                <a href="scan.php?qr=<?= urlencode($code['qr_code']) ?>" 
                                   class="btn btn-sm btn-primary" title="Aktivieren">
                                    <i class="fas fa-plus"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginierung -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination" style="margin-top: 20px; text-align: center;">
                <?php if ($page > 1): ?>
                    <a href="?filter=<?= $filter ?>&batch=<?= urlencode($batch) ?>&page=<?= $page - 1 ?>" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Zurück
                    </a>
                <?php endif; ?>
                
                <span style="margin: 0 15px; color: #666;">
                    Seite <?= $page ?> von <?= $totalPages ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?filter=<?= $filter ?>&batch=<?= urlencode($batch) ?>&page=<?= $page + 1 ?>" class="btn btn-secondary">
                        Weiter <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>