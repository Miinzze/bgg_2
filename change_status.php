<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('markers_change_status');

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

if ($marker['is_storage']) {
    die('Lagergeräte haben keinen Status');
}

$message = '';
$messageType = '';

// Status ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['new_status'] ?? '';
    
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        $message = 'Sicherheitstoken fehlt';
        $messageType = 'danger';
    } elseif (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Ungültiges Sicherheitstoken';
        $messageType = 'danger';
    } else {
        if (in_array($newStatus, ['verfuegbar', 'vermietet', 'wartung'])) {
            if (changeRentalStatus($id, $newStatus, $pdo)) {
                $message = 'Status erfolgreich geändert!';
                $messageType = 'success';
                
                // Marker neu laden
                $marker = getMarkerById($id, $pdo);
                
                // Weiterleitung nach 2 Sekunden
                header("refresh:2;url=view_marker.php?id=$id");
            } else {
                $message = 'Fehler beim Ändern des Status';
                $messageType = 'danger';
            }
        }
    }
}

$statusInfo = getRentalStatusLabel($marker['rental_status']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status ändern - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .status-option {
            background: white;
            border: 3px solid var(--border-color);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin: 15px 0;
        }
        
        .status-option input[type="radio"] {
            display: none;
        }
        
        .status-option input[type="radio"]:checked + label {
            border-color: var(--primary-color);
        }
        
        .status-option label {
            cursor: pointer;
            display: block;
            width: 100%;
        }
        
        .status-option:hover {
            border-color: var(--primary-color);
            transform: scale(1.02);
        }
        
        .status-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .status-verfuegbar { border-color: #28a745; }
        .status-verfuegbar:hover,
        .status-verfuegbar input:checked + label { 
            border-color: #28a745;
            background: #d4edda;
        }
        
        .status-vermietet { border-color: #dc3545; }
        .status-vermietet:hover,
        .status-vermietet input:checked + label {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        .status-wartung { border-color: #ffc107; }
        .status-wartung:hover,
        .status-wartung input:checked + label {
            border-color: #ffc107;
            background: #fff3cd;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Status ändern</h1>
                <h2><?= e($marker['name']) ?></h2>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <p><strong>Aktueller Status:</strong> 
                    <span class="badge badge-<?= $statusInfo['class'] ?> large">
                        <?= $statusInfo['label'] ?>
                    </span>
                </p>
                <?php if ($marker['maintenance_required']): ?>
                    <p style="color: orange;"><strong>⚠️ Wartung erforderlich!</strong> 
                    <?php if ($marker['rental_status'] === 'vermietet'): ?>
                        Status wird automatisch auf "Wartung" gesetzt sobald das Gerät wieder verfügbar ist.
                    <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <form method="POST" class="marker-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <h2>Neuen Status wählen:</h2>
                
                <div class="status-option status-verfuegbar">
                    <input type="radio" id="status_verfuegbar" name="new_status" value="verfuegbar" 
                           <?= $marker['rental_status'] === 'verfuegbar' ? 'checked' : '' ?>>
                    <label for="status_verfuegbar">
                        <div class="status-icon">✅</div>
                        <h3>Verfügbar</h3>
                        <p>Gerät ist verfügbar und kann vermietet werden</p>
                    </label>
                </div>
                
                <div class="status-option status-vermietet">
                    <input type="radio" id="status_vermietet" name="new_status" value="vermietet"
                           <?= $marker['rental_status'] === 'vermietet' ? 'checked' : '' ?>>
                    <label for="status_vermietet">
                        <div class="status-icon">🔴</div>
                        <h3>Vermietet</h3>
                        <p>Gerät ist aktuell vermietet</p>
                    </label>
                </div>
                
                <div class="status-option status-wartung">
                    <input type="radio" id="status_wartung" name="new_status" value="wartung"
                           <?= $marker['rental_status'] === 'wartung' ? 'checked' : '' ?>>
                    <label for="status_wartung">
                        <div class="status-icon">🔧</div>
                        <h3>Wartung</h3>
                        <p>Gerät befindet sich in Wartung</p>
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">Status ändern</button>
                    <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        // Status-Optionen klickbar machen
        document.querySelectorAll('.status-option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
    </script>

</body>
</html>