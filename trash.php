<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$message = '';
$messageType = '';

// Wiederherstellen
if (isset($_POST['restore']) && isset($_POST['marker_id'])) {
    validateCSRF();
    $markerId = intval($_POST['marker_id']);
    
    try {
        $stmt = $pdo->prepare("UPDATE markers SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
        $stmt->execute([$markerId]);
        
        logActivity('marker_restored', 'Marker aus Papierkorb wiederhergestellt', $markerId);
        
        $message = 'Marker erfolgreich wiederhergestellt';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Fehler: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Endgültig löschen
if (isset($_POST['delete_permanent']) && isset($_POST['marker_id'])) {
    validateCSRF();
    $markerId = intval($_POST['marker_id']);
    
    if (!hasPermission('markers_delete')) {
        $message = 'Keine Berechtigung zum Löschen';
        $messageType = 'danger';
    } else {
        try {
            // Marker-Info vor dem Löschen holen
            $stmt = $pdo->prepare("SELECT qr_code, name FROM markers WHERE id = ?");
            $stmt->execute([$markerId]);
            $marker = $stmt->fetch();
            
            if ($marker) {
                $pdo->beginTransaction();
                
                // QR-Code im Pool freigeben
                $stmt = $pdo->prepare("
                    UPDATE qr_code_pool 
                    SET is_assigned = 0, marker_id = NULL, assigned_at = NULL
                    WHERE qr_code = ?
                ");
                $stmt->execute([$marker['qr_code']]);
                
                // Marker endgültig löschen (CASCADE löscht automatisch Bilder, Dokumente etc.)
                $stmt = $pdo->prepare("DELETE FROM markers WHERE id = ?");
                $stmt->execute([$markerId]);
                
                $pdo->commit();
                
                logActivity('marker_deleted_permanent', "Marker '{$marker['name']}' endgültig gelöscht", $markerId);
                
                $message = 'Marker endgültig gelöscht. QR-Code kann wiederverwendet werden.';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Fehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Papierkorb leeren
if (isset($_POST['empty_trash'])) {
    validateCSRF();
    
    if (!hasPermission('markers_delete')) {
        $message = 'Keine Berechtigung';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Alle gelöschten Marker holen
            $stmt = $pdo->query("SELECT id, qr_code FROM markers WHERE deleted_at IS NOT NULL");
            $deletedMarkers = $stmt->fetchAll();
            
            // QR-Codes freigeben
            foreach ($deletedMarkers as $marker) {
                $stmt = $pdo->prepare("
                    UPDATE qr_code_pool 
                    SET is_assigned = 0, marker_id = NULL, assigned_at = NULL
                    WHERE qr_code = ?
                ");
                $stmt->execute([$marker['qr_code']]);
            }
            
            // Alle endgültig löschen
            $stmt = $pdo->query("DELETE FROM markers WHERE deleted_at IS NOT NULL");
            $count = $stmt->rowCount();
            
            $pdo->commit();
            
            logActivity('trash_emptied', "$count Marker endgültig gelöscht");
            
            $message = "$count Marker endgültig gelöscht. Alle QR-Codes können wiederverwendet werden.";
            $messageType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Fehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
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
    <title>Papierkorb - Marker System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-trash"></i> Papierkorb</h1>
                <div class="header-actions">
                    <?php if (!empty($deletedMarkers) && hasPermission('markers_delete')): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Papierkorb wirklich leeren? Alle Marker werden endgültig gelöscht!')">
                            <?php include 'csrf_token.php'; ?>
                            <button type="submit" name="empty_trash" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> Papierkorb leeren
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="markers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>
            
            <?php if (empty($deletedMarkers)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #999;">
                    <i class="fas fa-trash" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>Papierkorb ist leer</h3>
                    <p>Es befinden sich keine gelöschten Marker im Papierkorb</p>
                </div>
            <?php else: ?>
                <div class="info-box" style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                    <strong><i class="fas fa-info-circle"></i> Hinweis:</strong>
                    Gelöschte Marker können wiederhergestellt werden. Bei endgültigem Löschen wird der QR-Code freigegeben und kann wiederverwendet werden.
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>QR-Code</th>
                            <th>Kategorie</th>
                            <th>Gelöscht am</th>
                            <th>Gelöscht von</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deletedMarkers as $marker): ?>
                        <tr>
                            <td><?= e($marker['name']) ?></td>
                            <td><code><?= e($marker['qr_code']) ?></code></td>
                            <td><?= e($marker['category'] ?? '-') ?></td>
                            <td><?= formatDateTime($marker['deleted_at']) ?></td>
                            <td><?= e($marker['deleted_by_name'] ?? '-') ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <?php include 'csrf_token.php'; ?>
                                    <input type="hidden" name="marker_id" value="<?= $marker['id'] ?>">
                                    <button type="submit" name="restore" class="btn btn-sm btn-success" title="Wiederherstellen">
                                        <i class="fas fa-undo"></i> Wiederherstellen
                                    </button>
                                </form>
                                
                                <?php if (hasPermission('markers_delete')): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Marker wirklich endgültig löschen? Dies kann nicht rückgängig gemacht werden!')">
                                    <?php include 'csrf_token.php'; ?>
                                    <input type="hidden" name="marker_id" value="<?= $marker['id'] ?>">
                                    <button type="submit" name="delete_permanent" class="btn btn-sm btn-danger" title="Endgültig löschen">
                                        <i class="fas fa-times"></i> Endgültig löschen
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; color: #666; font-size: 14px;">
                    <i class="fas fa-recycle"></i> <?= count($deletedMarkers) ?> Marker im Papierkorb
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>