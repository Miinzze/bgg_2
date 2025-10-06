<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('maintenance_add');

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token validieren
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        $message = 'Sicherheitstoken fehlt';
        $messageType = 'danger';
    } elseif (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        $maintenanceDate = $_POST['maintenance_date'] ?? date('Y-m-d');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($description)) {
            $message = 'Bitte eine Beschreibung eingeben';
            $messageType = 'danger';
        } elseif (!validateDate($maintenanceDate)) {
            $message = 'Ungültiges Datum';
            $messageType = 'danger';
        } else {
            try {
                // Wartung in Historie eintragen
                $stmt = $pdo->prepare("
                    INSERT INTO maintenance_history (marker_id, maintenance_date, description, performed_by)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$id, $maintenanceDate, $description, $_SESSION['user_id']]);
                
                // Marker aktualisieren
                $nextMaintenance = calculateNextMaintenance($maintenanceDate, $marker['maintenance_interval_months']);
                
                $stmt = $pdo->prepare("
                    UPDATE markers SET last_maintenance = ?, next_maintenance = ?, maintenance_required = FALSE WHERE id = ?
                ");
                $stmt->execute([$maintenanceDate, $nextMaintenance, $id]);
                
                $message = 'Wartung erfolgreich eingetragen!';
                $messageType = 'success';
                
                header("refresh:2;url=view_marker.php?id=$id");
                logActivity('maintenance_added', "Wartung durchgeführt", $id);
            } catch (Exception $e) {
                $message = 'Fehler: ' . e($e->getMessage());
                $messageType = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartung durchführen - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-wrench"></i> Wartung durchführen</h1>
                <h2><?= e($marker['name']) ?></h2>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="marker-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-section">
                    <div class="form-group">
                        <label for="maintenance_date">Wartungsdatum *</label>
                        <input type="date" id="maintenance_date" name="maintenance_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Beschreibung der durchgeführten Arbeiten *</label>
                        <textarea id="description" name="description" rows="6" required 
                                  placeholder="z.B. Ölwechsel durchgeführt, Filter erneuert, Funktion geprüft..."></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-large">
                        <i class="fas fa-check"></i> Wartung eintragen
                    </button>
                    <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>

</body>
</html>