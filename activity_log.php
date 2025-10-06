<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('activity_log_view');

// Filter
$filterUser = $_GET['user'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDate = $_GET['date'] ?? '';
$limit = $_GET['limit'] ?? 100;

// Query aufbauen
$sql = "SELECT al.*, m.name as marker_name 
        FROM activity_log al 
        LEFT JOIN markers m ON al.marker_id = m.id 
        WHERE 1=1";
$params = [];

if ($filterUser) {
    $sql .= " AND al.username LIKE ?";
    $params[] = "%$filterUser%";
}

if ($filterAction) {
    $sql .= " AND al.action LIKE ?";
    $params[] = "%$filterAction%";
}

if ($filterDate) {
    $sql .= " AND DATE(al.created_at) = ?";
    $params[] = $filterDate;
}

$sql .= " ORDER BY al.created_at DESC LIMIT ?";
$params[] = intval($limit);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Unique Actions für Filter
$actionsStmt = $pdo->query("SELECT DISTINCT action FROM activity_log ORDER BY action");
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Aktivitätsprotokoll</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .log-entry {
            padding: 15px;
            background: white;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .log-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .log-user {
            font-weight: 600;
            color: #2c3e50;
        }
        .log-time {
            color: #6c757d;
            font-size: 13px;
        }
        .log-action {
            display: inline-block;
            padding: 3px 8px;
            background: #e7f3ff;
            color: #007bff;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }
        .log-details {
            color: #495057;
            font-size: 14px;
            margin-top: 5px;
        }
        .log-meta {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-history"></i> Aktivitätsprotokoll</h1>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <!-- Filter -->
            <div class="filter-bar" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" name="user" placeholder="Benutzer..." 
                           value="<?= e($filterUser) ?>" style="flex: 1; min-width: 150px;">
                    
                    <select name="action" style="flex: 1; min-width: 150px;">
                        <option value="">Alle Aktionen</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= e($action) ?>" <?= $filterAction === $action ? 'selected' : '' ?>>
                                <?= e($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="date" value="<?= e($filterDate) ?>" style="flex: 1; min-width: 150px;">
                    
                    <select name="limit" style="width: 120px;">
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 Einträge</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 Einträge</option>
                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500 Einträge</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtern
                    </button>
                    <a href="activity_log.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Zurücksetzen
                    </a>
                </form>
            </div>
            
            <!-- Log Einträge -->
            <div class="admin-section">
                <h2>Aktivitäten (<?= count($logs) ?>)</h2>
                
                <?php if (empty($logs)): ?>
                    <p style="color: #6c757d;">Keine Aktivitäten gefunden</p>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="log-entry">
                            <div class="log-header">
                                <div>
                                    <span class="log-user">
                                        <i class="fas fa-user"></i> <?= e($log['username']) ?>
                                    </span>
                                    <span class="log-action"><?= e($log['action']) ?></span>
                                </div>
                                <span class="log-time">
                                    <i class="fas fa-clock"></i> <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                                </span>
                            </div>
                            
                            <?php if ($log['details']): ?>
                                <div class="log-details">
                                    <?= e($log['details']) ?>
                                    <?php if ($log['marker_name']): ?>
                                        <a href="view_marker.php?id=<?= $log['marker_id'] ?>" style="margin-left: 10px;">
                                            <i class="fas fa-external-link-alt"></i> <?= e($log['marker_name']) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="log-meta">
                                <i class="fas fa-network-wired"></i> IP: <?= e($log['ip_address']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>