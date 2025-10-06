<?php
require_once 'config.php';
require_once 'functions.php';

// Kein Login erforderlich - funktioniert mit Token
$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? 'checkout'; // checkout oder checkin

if (empty($token)) {
    die('Ungültiger Zugriff');
}

// Marker über Token laden
$stmt = $pdo->prepare("SELECT * FROM markers WHERE public_token = ?");
$stmt->execute([$token]);
$marker = $stmt->fetch();

if (!$marker) {
    die('Marker nicht gefunden');
}

// Aktuellen Checkout-Status prüfen
$stmt = $pdo->prepare("
    SELECT * FROM checkout_history 
    WHERE marker_id = ? AND status = 'active' 
    ORDER BY checkout_date DESC LIMIT 1
");
$stmt->execute([$marker['id']]);
$activeCheckout = $stmt->fetch();

$message = '';
$messageType = '';

// Checkout verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'checkout') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $returnDate = $_POST['return_date'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($name)) {
        $message = 'Bitte geben Sie Ihren Namen ein';
        $messageType = 'danger';
    } elseif ($activeCheckout) {
        $message = 'Gerät ist bereits ausgecheckt';
        $messageType = 'warning';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Checkout erstellen
            $stmt = $pdo->prepare("
                INSERT INTO checkout_history 
                (marker_id, checked_out_by, checked_out_by_email, checked_out_by_phone, 
                 expected_return_date, checkout_notes, qr_scanned) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$marker['id'], $name, $email, $phone, $returnDate, $notes]);
            
            // Marker-Status auf 'vermietet' setzen
            $stmt = $pdo->prepare("UPDATE markers SET rental_status = 'vermietet' WHERE id = ?");
            $stmt->execute([$marker['id']]);
            
            $pdo->commit();
            
            $message = 'Erfolgreich ausgecheckt!';
            $messageType = 'success';
            
            // Aktiven Checkout neu laden
            $stmt = $pdo->prepare("
                SELECT * FROM checkout_history 
                WHERE marker_id = ? AND status = 'active' 
                ORDER BY checkout_date DESC LIMIT 1
            ");
            $stmt->execute([$marker['id']]);
            $activeCheckout = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Fehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Checkin verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'checkin') {
    $checkinNotes = trim($_POST['checkin_notes'] ?? '');
    
    if (!$activeCheckout) {
        $message = 'Gerät ist nicht ausgecheckt';
        $messageType = 'warning';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Checkout abschließen
            $stmt = $pdo->prepare("
                UPDATE checkout_history 
                SET checkin_date = NOW(), checkin_notes = ?, status = 'returned' 
                WHERE id = ?
            ");
            $stmt->execute([$checkinNotes, $activeCheckout['id']]);
            
            // Marker-Status auf 'verfuegbar' setzen
            $stmt = $pdo->prepare("UPDATE markers SET rental_status = 'verfuegbar' WHERE id = ?");
            $stmt->execute([$marker['id']]);
            
            $pdo->commit();
            
            $message = 'Erfolgreich zurückgegeben!';
            $messageType = 'success';
            $activeCheckout = null;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Fehler: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $activeCheckout ? 'Check-in' : 'Checkout' ?> - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .checkout-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .device-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 25px;
        }
        
        .device-header h1 {
            color: var(--primary-color);
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        
        .device-info {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .device-info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .device-info-item:last-child {
            border-bottom: none;
        }
        
        .status-active {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .status-active h3 {
            margin: 0 0 10px 0;
        }
        
        .checkout-form {
            margin-top: 20px;
        }
        
        .success-message {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .success-message i {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="device-header">
            <i class="fas fa-qrcode" style="font-size: 48px; color: var(--primary-color); margin-bottom: 15px;"></i>
            <h1><?= e($marker['name']) ?></h1>
            <p style="color: var(--medium-gray); margin: 0;">
                <?= e($marker['category']) ?>
                <?php if ($marker['serial_number']): ?>
                    • SN: <?= e($marker['serial_number']) ?>
                <?php endif; ?>
            </p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
        <?php endif; ?>
        
        <div class="device-info">
            <div class="device-info-item">
                <span><strong>RFID:</strong></span>
                <span><?= e($marker['rfid_chip']) ?></span>
            </div>
            <div class="device-info-item">
                <span><strong>Status:</strong></span>
                <span class="badge badge-<?= $activeCheckout ? 'warning' : 'success' ?>">
                    <?= $activeCheckout ? 'Ausgecheckt' : 'Verfügbar' ?>
                </span>
            </div>
            <?php if (!$marker['is_storage'] && !$marker['is_multi_device']): ?>
                <div class="device-info-item">
                    <span><strong>Kraftstoff:</strong></span>
                    <span><?= $marker['fuel_level'] ?>%</span>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($activeCheckout): ?>
            <!-- Checkin-Formular -->
            <div class="status-active">
                <h3><i class="fas fa-user"></i> Aktuell ausgecheckt</h3>
                <p style="margin: 0;"><strong><?= e($activeCheckout['checked_out_by']) ?></strong></p>
                <p style="margin: 5px 0 0 0; font-size: 14px;">
                    Seit: <?= date('d.m.Y H:i', strtotime($activeCheckout['checkout_date'])) ?> Uhr
                </p>
                <?php if ($activeCheckout['expected_return_date']): ?>
                    <p style="margin: 5px 0 0 0; font-size: 14px;">
                        Rückgabe: <?= date('d.m.Y', strtotime($activeCheckout['expected_return_date'])) ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <form method="POST" class="checkout-form">
                <input type="hidden" name="action" value="checkin">
                
                <div class="form-group">
                    <label for="checkin_notes">Anmerkungen zur Rückgabe (optional)</label>
                    <textarea id="checkin_notes" name="checkin_notes" rows="3" 
                              placeholder="z.B. Tankfüllung, Zustand, Schäden..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-success btn-large btn-block">
                    <i class="fas fa-check-circle"></i> Gerät zurückgeben (Check-in)
                </button>
            </form>
            
        <?php else: ?>
            <!-- Checkout-Formular -->
            <form method="POST" class="checkout-form">
                <input type="hidden" name="action" value="checkout">
                
                <div class="form-group">
                    <label for="name">Ihr Name *</label>
                    <input type="text" id="name" name="name" required 
                           placeholder="Max Mustermann">
                </div>
                
                <div class="form-group">
                    <label for="email">E-Mail (optional)</label>
                    <input type="email" id="email" name="email" 
                           placeholder="max@example.com">
                </div>
                
                <div class="form-group">
                    <label for="phone">Telefon (optional)</label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="+49 123 456789">
                </div>
                
                <div class="form-group">
                    <label for="return_date">Geplante Rückgabe (optional)</label>
                    <input type="date" id="return_date" name="return_date" 
                           min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label for="notes">Anmerkungen (optional)</label>
                    <textarea id="notes" name="notes" rows="3" 
                              placeholder="Verwendungszweck, Besonderheiten..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large btn-block">
                    <i class="fas fa-sign-out-alt"></i> Gerät ausleihen (Checkout)
                </button>
            </form>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <a href="public_view.php?token=<?= $token ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-info-circle"></i> Details anzeigen
            </a>
        </div>
        
        <?php if ($activeCheckout): ?>
            <!-- Checkout-Historie -->
            <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid var(--border-color);">
                <h3 style="font-size: 14px; color: var(--medium-gray); margin-bottom: 10px;">
                    <i class="fas fa-history"></i> CHECKOUT-DETAILS
                </h3>
                <?php if ($activeCheckout['checked_out_by_email']): ?>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <i class="fas fa-envelope"></i> <?= e($activeCheckout['checked_out_by_email']) ?>
                    </p>
                <?php endif; ?>
                <?php if ($activeCheckout['checked_out_by_phone']): ?>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <i class="fas fa-phone"></i> <?= e($activeCheckout['checked_out_by_phone']) ?>
                    </p>
                <?php endif; ?>
                <?php if ($activeCheckout['checkout_notes']): ?>
                    <p style="margin: 10px 0 0 0; font-size: 13px; background: var(--light-gray); padding: 10px; border-radius: 5px;">
                        <strong>Notizen:</strong><br>
                        <?= nl2br(e($activeCheckout['checkout_notes'])) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>