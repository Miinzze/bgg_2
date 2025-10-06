<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('markers_delete');

$message = '';
$messageType = '';

// Marker wiederherstellen
if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);
    
    try {
        $stmt = $pdo->prepare("UPDATE markers SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity('marker_restored', "Marker aus Papierkorb wiederhergestellt", $id);
        
        $message = 'Marker erfolgreich wiederhergestellt!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Fehler: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Marker endgültig löschen
if (isset($_GET['delete_permanent'])) {
    $id = intval($_GET['delete_permanent']);
    
    try {
        $pdo->beginTransaction();
        
        // Alle verknüpften Daten löschen
        $pdo->prepare("DELETE FROM marker_images WHERE marker_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM marker_documents WHERE marker_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM marker_serial_numbers WHERE marker_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM marker_custom_values WHERE marker_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM maintenance_history WHERE marker_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM checkout_history WHERE marker_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM inspection_schedules WHERE marker_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM marker_comments WHERE marker_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM checklist_completions WHERE marker_id = ?")->execute([$id]);
        
        // Marker löschen
        $pdo->prepare("DELETE FROM markers WHERE id = ?")->execute([$id]);
        
        $pdo->commit();
        
        logActivity('marker_deleted_permanent', "Marker endgültig gelöscht", $id);
        
        $message = 'Marker endgültig gelöscht!';
        $messageType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Fehler: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Papierkorb leeren
if (isset($_GET['empty_trash'])) {
    try {
        $pdo->beginTransaction();
        
        // Alle gelöschten Marker holen
        $stmt = $pdo->query("SELECT id FROM markers WHERE deleted_at IS NOT NULL");
        $deletedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($deletedIds as $id) {
            // Verknüpfte Daten löschen
            $pdo->prepare("DELETE FROM marker_images WHERE marker_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM marker_documents WHERE marker_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM marker_serial_numbers WHERE marker_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM marker_custom_values WHERE marker_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM maintenance_history WHERE marker_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM checkout_history WHERE marker_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM inspection_schedules WHERE marker_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM marker_comments WHERE marker_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM checklist_completions WHERE marker_id = ?")->execute([$id]);
        }
        
        // Alle gelöschten Marker endgültig entfernen
        $pdo->query("DELETE FROM markers WHERE deleted_at IS NOT NULL");
        
        $pdo->commit();
        
        logActivity('trash_emptied', "Papierkorb geleert (" . count($deletedIds) . " Marker)");
        
        $message = count($deletedIds) . ' Marker endgültig gelöscht!';
        $messageType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Fehler: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Gelöschte Marker laden
$stmt = $pdo->query("
    SELECT m.*, u.username as deleted_by_name 
    FROM markers m 
    LEFT JOIN users u ON m.deleted_by = u.id 
    WHERE m.deleted_at IS NOT NULL 
    ORDER BY m.deleted_at DESC
");
$deletedMarkers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papierkorb - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .trash-item {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid var(--danger-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .trash-item-info {
            flex: 1;
        }
        
        .trash-item-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 8px;
        }
        
        .trash-item-meta {
            font-size: 13px;
            color: var(--medium-gray);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .trash-actions {
            display: flex;
            gap: 10px;
        }
        
        .empty-trash {
            text-align: center;
            padding: 60px 20px;
            color: var(--medium-gray);
        }
        
        .empty-trash i {
            font-size: 64px;
            opacity: 0.2;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-trash"></i> Papierkorb</h1>
                    <p style="color: var(--medium-gray); margin-top: 5px;">
                        Gelöschte Marker können hier wiederhergestellt werden
                    </p>
                </div>
                <div class="header-actions">
                    <?php if (!empty($deletedMarkers)): ?>
                        <a href="?empty_trash=1" class="btn btn-danger"
                           onclick="return confirm('Papierkorb wirklich leeren? Alle Marker werden ENDGÜLTIG gelöscht!')">
                            <i class="fas fa-trash-alt"></i> Papierkorb leeren
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <?php if (empty($deletedMarkers)): ?>
                <div class="empty-trash">
                    <i class="fas fa-trash"></i>
                    <h2>Papierkorb ist leer</h2>
                    <p>Keine gelöschten Marker vorhanden</p>
                </div>
            <?php else: ?>
                <div class="info-box" style="margin-bottom: 20px;">
                    <p>
                        <i class="fas fa-info-circle"></i> 
                        <strong><?= count($deletedMarkers) ?></strong> gelöschte Marker im Papierkorb.
                        Diese können wiederhergestellt oder endgültig gelöscht werden.
                    </p>
                </div>
                
                <?php foreach ($deletedMarkers as $marker): ?>
                    <div class="trash-item">
                        <div class="trash-item-info">
                            <div class="trash-item-title">
                                <?= e($marker['name']) ?>
                                <?php if ($marker['is_storage']): ?>
                                    <span class="badge badge-info">Lager</span>
                                <?php elseif ($marker['is_multi_device']): ?>
                                    <span class="badge badge-info">Mehrgerät</span>
                                <?php endif; ?>
                            </div>
                            <div class="trash-item-meta">
                                <span><i class="fas fa-tag"></i> <?= e($marker['category']) ?></span>
                                <span><i class="fas fa-microchip"></i> <?= e($marker['rfid_chip']) ?></span>
                                <?php if ($marker['serial_number']): ?>
                                    <span><i class="fas fa-barcode"></i> <?= e($marker['serial_number']) ?></span>
                                <?php endif; ?>
                                <span>
                                    <i class="fas fa-trash-alt"></i> 
                                    Gelöscht: <?= date('d.m.Y H:i', strtotime($marker['deleted_at'])) ?> Uhr
                                </span>
                                <?php if ($marker['deleted_by_name']): ?>
                                    <span><i class="fas fa-user"></i> <?= e($marker['deleted_by_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="trash-actions">
                            <a href="?restore=<?= $marker['id'] ?>" 
                               class="btn btn-success"
                               onclick="return confirm('Marker wiederherstellen?')">
                                <i class="fas fa-undo"></i> Wiederherstellen
                            </a>
                            <a href="?delete_permanent=<?= $marker['id'] ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('Marker ENDGÜLTIG löschen? Diese Aktion kann nicht rückgängig gemacht werden!')">
                                <i class="fas fa-times"></i> Endgültig löschen
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>