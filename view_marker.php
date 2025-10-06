<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

// Prüfen ob Marker gelöscht ist
if ($marker['deleted_at']) {
    header('Location: trash.php');
    exit;
}

$message = '';
$messageType = '';

// Status ändern (nur zwischen Verfügbar und Vermietet)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $newStatus = $_POST['new_status'] ?? '';
    
    if (in_array($newStatus, ['verfuegbar', 'vermietet']) && hasPermission('markers_change_status')) {
        $stmt = $pdo->prepare("UPDATE markers SET rental_status = ? WHERE id = ?");
        if ($stmt->execute([$newStatus, $id])) {
            $message = 'Status erfolgreich geändert!';
            $messageType = 'success';
            
            // Marker neu laden
            $marker = getMarkerById($id, $pdo);
        } else {
            $message = 'Fehler beim Ändern des Status';
            $messageType = 'danger';
        }
    }
}

$images = getMarkerImages($id, $pdo);
$status = getMaintenanceStatus($marker['next_maintenance']);

// Bei Multi-Device: Alle Seriennummern laden
$serialNumbers = [];
if ($marker['is_multi_device']) {
    $stmt = $pdo->prepare("SELECT serial_number, created_at FROM marker_serial_numbers WHERE marker_id = ? ORDER BY created_at ASC");
    $stmt->execute([$id]);
    $serialNumbers = $stmt->fetchAll();
}

// Wartungshistorie abrufen
$stmt = $pdo->prepare("
    SELECT mh.*, u.username 
    FROM maintenance_history mh 
    LEFT JOIN users u ON mh.performed_by = u.id 
    WHERE mh.marker_id = ? 
    ORDER BY mh.maintenance_date DESC
");
$stmt->execute([$id]);
$maintenanceHistory = $stmt->fetchAll();

$rentalStatusInfo = getRentalStatusLabel($marker['rental_status']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($marker['name']) ?> - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .serial-numbers-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .serial-number-item {
            padding: 8px 12px;
            background: white;
            margin: 5px 0;
            border-radius: 4px;
            border-left: 3px solid #007bff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .serial-number-item strong {
            color: #007bff;
            min-width: 30px;
        }
        .multi-device-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        /* Status-Änderung Card */
        .status-change-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .status-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .status-btn {
            flex: 1;
            padding: 15px;
            border: 3px solid #dee2e6;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .status-btn.active {
            border-color: #007bff;
            background: #e7f3ff;
            color: #007bff;
        }
        
        .status-btn.verfuegbar {
            border-color: #28a745;
        }
        
        .status-btn.verfuegbar.active {
            background: #d4edda;
            color: #28a745;
            border-color: #28a745;
        }
        
        .status-btn.vermietet {
            border-color: #ffc107;
        }
        
        .status-btn.vermietet.active {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }
        
        .status-btn i {
            display: block;
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        @media (max-width: 768px) {
            .status-buttons {
                flex-direction: column;
            }
            
            .status-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><?= e($marker['name']) ?></h1>
                <div class="header-actions">
                    <?php if (hasPermission('markers_edit')): ?>
                        <a href="edit_marker.php?id=<?= $marker['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Bearbeiten
                        </a>
                        <a href="inspections.php?marker_id=<?= $marker['id'] ?>" class="btn btn-info">
                            <i class="fas fa-clipboard-check"></i> Prüffristen
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('markers_delete')): ?>
                        <a href="delete_marker.php?id=<?= $marker['id'] ?>" 
                        class="btn btn-danger"
                        onclick="return confirm('Marker wirklich in den Papierkorb verschieben?\n\nName: <?= e($marker['name']) ?>\n<?= $marker['serial_number'] ? 'SN: ' . e($marker['serial_number']) : '' ?>')">
                            <i class="fas fa-trash"></i> Löschen
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
            
            <?php if (!$marker['is_storage'] && !$marker['is_multi_device'] && hasPermission('markers_change_status')): ?>
            <!-- Quick Status Change -->
            <div class="status-change-card">
                <h3 style="margin-top: 0; margin-bottom: 10px; color: #2c3e50;">
                    <i class="fas fa-exchange-alt"></i> Status ändern
                </h3>
                <p style="color: #6c757d; margin-bottom: 15px; font-size: 14px;">
                    Aktueller Status: <strong class="badge badge-<?= $rentalStatusInfo['class'] ?>"><?= $rentalStatusInfo['label'] ?></strong>
                </p>
                
                <form method="POST" id="statusForm">
                    <input type="hidden" name="change_status" value="1">
                    <input type="hidden" name="new_status" id="new_status" value="">
                    
                    <div class="status-buttons">
                        <button type="button" 
                                class="status-btn verfuegbar <?= $marker['rental_status'] === 'verfuegbar' ? 'active' : '' ?>"
                                onclick="setStatus('verfuegbar')">
                            <i class="fas fa-check-circle"></i>
                            <div>Verfügbar</div>
                        </button>
                        
                        <button type="button" 
                                class="status-btn vermietet <?= $marker['rental_status'] === 'vermietet' ? 'active' : '' ?>"
                                onclick="setStatus('vermietet')">
                            <i class="fas fa-handshake"></i>
                            <div>Vermietet</div>
                        </button>
                    </div>
                </form>
                
                <?php if ($marker['rental_status'] === 'wartung'): ?>
                    <div class="alert alert-warning" style="margin-top: 15px; margin-bottom: 0;">
                        <i class="fas fa-info-circle"></i> 
                        Gerät ist derzeit in Wartung. Ändern Sie den Status, wenn die Wartung abgeschlossen ist.
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="marker-details">
                <div class="details-grid">
                    <div class="details-main">
                        <div class="info-card">
                            <h2>Geräteinformationen</h2>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="label">RFID-Chip:</span>
                                    <span class="value"><?= e($marker['rfid_chip']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Typ:</span>
                                    <span class="value">
                                        <?php if ($marker['is_multi_device']): ?>
                                            <span class="badge badge-info">Mehrere Geräte an einem Standort</span>
                                        <?php elseif ($marker['is_storage']): ?>
                                            <span class="badge badge-info">Lagergerät</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Betriebsgerät</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Kategorie:</span>
                                    <span class="value"><?= e($marker['category']) ?></span>
                                </div>
                                
                                <?php if ($marker['is_multi_device']): ?>
                                    <!-- Multi-Device: Alle Seriennummern anzeigen -->
                                    <div class="info-item" style="grid-column: 1 / -1;">
                                        <span class="label">Seriennummern (<?= count($serialNumbers) ?> Geräte):</span>
                                        <div class="serial-numbers-list">
                                            <?php if (empty($serialNumbers)): ?>
                                                <p style="color: #6c757d; font-style: italic; margin: 0;">Keine Seriennummern erfasst</p>
                                            <?php else: ?>
                                                <?php foreach ($serialNumbers as $index => $sn): ?>
                                                    <div class="serial-number-item">
                                                        <strong><?= $index + 1 ?>.</strong>
                                                        <span><?= e($sn['serial_number']) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Einzelgerät: Eine Seriennummer -->
                                    <div class="info-item">
                                        <span class="label">Seriennummer:</span>
                                        <span class="value"><?= e($marker['serial_number']) ?: 'Nicht erfasst' ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Betriebsstunden:</span>
                                        <span class="value"><?= e($marker['operating_hours']) ?> h</span>
                                    </div>
                                    <?php if (!$marker['is_storage']): ?>
                                    <div class="info-item">
                                        <span class="label">Kraftstofffüllstand:</span>
                                        <div class="fuel-indicator large">
                                            <div class="fuel-bar" style="width: <?= $marker['fuel_level'] ?>%"></div>
                                            <span><?= $marker['fuel_level'] ?>%</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <span class="label">Erstellt von:</span>
                                    <span class="value"><?= e($marker['created_by_name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Erstellt am:</span>
                                    <span class="value"><?= date('d.m.Y H:i', strtotime($marker['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Custom Fields anzeigen -->
                        <?php
                        $customValues = $pdo->prepare("
                            SELECT cf.field_label, mcv.field_value, cf.field_type
                            FROM marker_custom_values mcv
                            JOIN custom_fields cf ON mcv.field_id = cf.id
                            WHERE mcv.marker_id = ?
                            ORDER BY cf.display_order, cf.id
                        ");
                        $customValues->execute([$id]);
                        $customData = $customValues->fetchAll();

                        if (!empty($customData)):
                        ?>
                        <div class="info-card">
                            <h2><i class="fas fa-info-circle"></i> Zusätzliche Informationen</h2>
                            <div class="info-grid">
                                <?php foreach ($customData as $data): ?>
                                    <div class="info-item">
                                        <span class="label"><?= e($data['field_label']) ?>:</span>
                                        <span class="value">
                                            <?php if ($data['field_type'] === 'date' && $data['field_value']): ?>
                                                <?= date('d.m.Y', strtotime($data['field_value'])) ?>
                                            <?php else: ?>
                                                <?= nl2br(e($data['field_value'])) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- PDF Dokumente anzeigen -->
                        <?php
                        $documents = $pdo->prepare("
                            SELECT md.*, u.username as uploaded_by_name
                            FROM marker_documents md
                            LEFT JOIN users u ON md.uploaded_by = u.id
                            WHERE md.marker_id = ?
                            ORDER BY md.uploaded_at DESC
                        ");
                        $documents->execute([$id]);
                        $docs = $documents->fetchAll();

                        if (!empty($docs) || hasPermission('documents_upload')):
                        ?>
                        <div class="info-card">
                            <h2><i class="fas fa-file-pdf"></i> Dokumente</h2>
                            
                            <?php if (!empty($docs)): ?>
                                <div style="display: grid; gap: 10px; margin-bottom: 20px;">
                                    <?php foreach ($docs as $doc): ?>
                                        <div style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #dc3545;">
                                            <i class="fas fa-file-pdf" style="color: #dc3545; font-size: 32px; margin-right: 15px;"></i>
                                            <div style="flex: 1;">
                                                <strong><?= e($doc['document_name']) ?></strong><br>
                                                <small style="color: #6c757d;">
                                                    <?= number_format($doc['file_size'] / 1024 / 1024, 2) ?> MB |
                                                    Hochgeladen: <?= date('d.m.Y H:i', strtotime($doc['uploaded_at'])) ?>
                                                    <?php if ($doc['uploaded_by_name']): ?>
                                                        von <?= e($doc['uploaded_by_name']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div style="display: flex; gap: 5px;">
                                                <a href="<?= e($doc['document_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Öffnen
                                                </a>
                                                <a href="<?= e($doc['document_path']) ?>" download class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                                <?php if (hasPermission('documents_delete')): ?>
                                                    <a href="delete_document.php?id=<?= $doc['id'] ?>&marker=<?= $marker['id'] ?>" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Dokument wirklich löschen?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('documents_upload')): ?>
                                <form method="POST" enctype="multipart/form-data" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="upload_documents" value="1">
                                    
                                    <div class="form-group">
                                        <label for="new_documents">Weitere Dokumente hochladen</label>
                                        <input type="file" id="new_documents" name="documents[]" multiple accept=".pdf,application/pdf">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Hochladen
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!$marker['is_storage'] && !$marker['is_multi_device']): ?>
                        <div class="info-card">
                            <h2>Wartungsinformationen</h2>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="label">Wartungsintervall:</span>
                                    <span class="value">Alle <?= $marker['maintenance_interval_months'] ?> Monate</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Letzte Wartung:</span>
                                    <span class="value"><?= date('d.m.Y', strtotime($marker['last_maintenance'])) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Nächste Wartung:</span>
                                    <span class="value"><?= date('d.m.Y', strtotime($marker['next_maintenance'])) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Status:</span>
                                    <span class="badge badge-<?= $status['class'] ?> large">
                                        <?= $status['label'] ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (hasPermission('maintenance_add')): ?>
                                <div class="maintenance-action">
                                    <a href="add_maintenance.php?id=<?= $marker['id'] ?>" class="btn btn-success">
                                        <i class="fas fa-wrench"></i> Wartung durchführen
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($marker['is_multi_device']): ?>
                        <div class="info-card">
                            <h2>Hinweis zu Mehrgerät-Standorten</h2>
                            <p style="color: #6c757d; line-height: 1.6;">
                                An diesem Standort befinden sich mehrere Geräte. Wartungs- und Betriebsinformationen 
                                werden für Mehrgerät-Standorte nicht zentral erfasst. Jedes Gerät sollte individuell 
                                dokumentiert werden.
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Checklisten -->
                        <?php
                        $checklists = $pdo->prepare("
                            SELECT cc.*, ct.name as template_name, u.username as completed_by_name
                            FROM checklist_completions cc
                            JOIN checklist_templates ct ON cc.template_id = ct.id
                            LEFT JOIN users u ON cc.completed_by = u.id
                            WHERE cc.marker_id = ?
                            ORDER BY cc.completion_date DESC
                        ");
                        $checklists->execute([$id]);
                        $completedChecklists = $checklists->fetchAll();

                        if (hasPermission('checklists_complete') || !empty($completedChecklists)):
                        ?>
                        <div class="info-card">
                            <h2><i class="fas fa-tasks"></i> Wartungs-Checklisten</h2>
                            
                            <?php if (!empty($completedChecklists)): ?>
                                <div style="margin-bottom: 20px;">
                                    <?php foreach ($completedChecklists as $checklist): ?>
                                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid #28a745;">
                                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                                <div style="flex: 1;">
                                                    <strong><?= e($checklist['template_name']) ?></strong><br>
                                                    <small style="color: #6c757d;">
                                                        Ausgefüllt: <?= date('d.m.Y H:i', strtotime($checklist['completion_date'])) ?>
                                                        <?php if ($checklist['completed_by_name']): ?>
                                                            von <?= e($checklist['completed_by_name']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                    <?php if ($checklist['notes']): ?>
                                                        <p style="margin: 10px 0 0 0; font-size: 14px;">
                                                            <strong>Anmerkungen:</strong> <?= nl2br(e($checklist['notes'])) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($checklist['pdf_path'] && file_exists($checklist['pdf_path'])): ?>
                                                        <a href="<?= e($checklist['pdf_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-file-pdf"></i> PDF öffnen
                                                        </a>
                                                        <a href="<?= e($checklist['pdf_path']) ?>" download class="btn btn-sm btn-secondary">
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('checklists_complete')): ?>
                                <a href="complete_checklist.php?marker=<?= $marker['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Neue Checkliste ausfüllen
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="info-card">
                            <h2>Standort</h2>
                            <div id="markerMap" style="height: 400px;"></div>
                            <p style="margin-top: 10px;">
                                <strong>Koordinaten:</strong> 
                                <?= e($marker['latitude']) ?>, <?= e($marker['longitude']) ?>
                            </p>
                        </div>

                        <!-- QR-Code Bereich für Checkout/Checkin -->
                        <div class="info-card">
                            <h2><i class="fas fa-qrcode"></i> QR-Code für Checkout/Checkin</h2>
                            <p style="color: #6c757d; margin-bottom: 15px;">
                                Scannen Sie den QR-Code mit dem Smartphone, um das Gerät auszuleihen oder zurückzugeben.
                            </p>
                            
                            <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                                <?php
                                $checkoutUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/checkout.php?token=' . $marker['public_token'];
                                $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode($checkoutUrl);
                                ?>
                                
                                <img src="<?= $qrApiUrl ?>" 
                                    alt="QR Code für <?= e($marker['name']) ?>"
                                    style="max-width: 300px; border: 2px solid #dee2e6; border-radius: 8px; padding: 10px; background: white;">
                                
                                <p style="margin: 15px 0 10px 0; color: #6c757d; font-size: 12px;">
                                    <i class="fas fa-mobile-alt"></i> Checkout/Checkin via Smartphone
                                </p>
                                
                                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 15px;">
                                    <a href="<?= $qrApiUrl ?>&format=png&download=1" 
                                    download="checkout_qr_<?= $marker['id'] ?>_<?= urlencode($marker['name']) ?>.png" 
                                    class="btn btn-secondary">
                                        <i class="fas fa-download"></i> QR-Code herunterladen
                                    </a>
                                    
                                    <a href="print_qr.php?id=<?= $marker['id'] ?>&type=checkout" 
                                    target="_blank" 
                                    class="btn btn-primary">
                                        <i class="fas fa-print"></i> QR drucken
                                    </a>
                                    
                                    <a href="<?= $checkoutUrl ?>" 
                                    target="_blank" 
                                    class="btn btn-info">
                                        <i class="fas fa-external-link-alt"></i> Checkout-Seite öffnen
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Checkout-Status anzeigen -->
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT * FROM checkout_history 
                                WHERE marker_id = ? AND status = 'active' 
                                ORDER BY checkout_date DESC LIMIT 1
                            ");
                            $stmt->execute([$marker['id']]);
                            $activeCheckout = $stmt->fetch();
                            
                            if ($activeCheckout):
                            ?>
                                <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; border-radius: 8px;">
                                    <h3 style="margin: 0 0 10px 0;">
                                        <i class="fas fa-user-check"></i> Aktuell ausgecheckt
                                    </h3>
                                    <p style="margin: 0;"><strong><?= e($activeCheckout['checked_out_by']) ?></strong></p>
                                    <p style="margin: 5px 0 0 0; font-size: 14px;">
                                        Seit: <?= date('d.m.Y H:i', strtotime($activeCheckout['checkout_date'])) ?> Uhr
                                    </p>
                                    <?php if ($activeCheckout['expected_return_date']): ?>
                                        <p style="margin: 5px 0 0 0; font-size: 14px;">
                                            Geplante Rückgabe: <?= date('d.m.Y', strtotime($activeCheckout['expected_return_date'])) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($activeCheckout['checked_out_by_email']): ?>
                                        <p style="margin: 5px 0 0 0; font-size: 13px;">
                                            <i class="fas fa-envelope"></i> <?= e($activeCheckout['checked_out_by_email']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($activeCheckout['checked_out_by_phone']): ?>
                                        <p style="margin: 5px 0 0 0; font-size: 13px;">
                                            <i class="fas fa-phone"></i> <?= e($activeCheckout['checked_out_by_phone']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Prüffristen anzeigen -->
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT * FROM inspection_schedules 
                            WHERE marker_id = ? 
                            ORDER BY next_inspection ASC
                        ");
                        $stmt->execute([$marker['id']]);
                        $inspections = $stmt->fetchAll();

                        if (!empty($inspections) || hasPermission('markers_edit')):
                        ?>
                        <div class="info-card">
                            <h2><i class="fas fa-clipboard-check"></i> Prüffristen (TÜV, UVV, DGUV)</h2>
                            
                            <?php if (empty($inspections)): ?>
                                <p style="color: #6c757d;">Noch keine Prüffristen erfasst</p>
                            <?php else: ?>
                                <?php foreach ($inspections as $insp): ?>
                                    <?php
                                    $daysUntil = $insp['next_inspection'] ? (strtotime($insp['next_inspection']) - time()) / 86400 : 999;
                                    $statusClass = $daysUntil < 0 ? 'danger' : ($daysUntil <= 30 ? 'warning' : 'success');
                                    ?>
                                    <div style="padding: 12px; background: var(--light-gray); border-radius: 5px; margin-bottom: 10px; border-left: 4px solid var(--<?= $statusClass === 'danger' ? 'danger' : ($statusClass === 'warning' ? 'warning' : 'success') ?>-color);">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div>
                                                <strong><?= e($insp['inspection_type']) ?></strong>
                                                <?php if ($insp['next_inspection']): ?>
                                                    <br><small>Nächste Prüfung: <?= date('d.m.Y', strtotime($insp['next_inspection'])) ?></small>
                                                <?php endif; ?>
                                                <?php if ($insp['certificate_number']): ?>
                                                    <br><small style="color: #6c757d;">Zertifikat: <?= e($insp['certificate_number']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge badge-<?= $statusClass ?>">
                                                <?= $daysUntil < 0 ? 'ÜBERFÄLLIG' : ($daysUntil <= 30 ? 'FÄLLIG' : 'OK') ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('markers_edit')): ?>
                                <a href="inspections.php?marker=<?= $marker['id'] ?>" class="btn btn-primary" style="margin-top: 15px;">
                                    <i class="fas fa-plus"></i> Prüffristen verwalten
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($images)): ?>
                            <div class="info-card">
                                <h2>Bilder</h2>
                                <div class="image-gallery">
                                    <?php foreach ($images as $image): ?>
                                        <a href="<?= e($image['image_path']) ?>" target="_blank">
                                            <img src="<?= e($image['image_path']) ?>" alt="Marker Bild">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($maintenanceHistory)): ?>
                            <div class="info-card">
                                <h2>Wartungshistorie</h2>
                                <div class="maintenance-history">
                                    <?php foreach ($maintenanceHistory as $maint): ?>
                                        <div class="history-item">
                                            <div class="history-date">
                                                <?= date('d.m.Y', strtotime($maint['maintenance_date'])) ?>
                                            </div>
                                            <div class="history-content">
                                                <p><?= e($maint['description']) ?></p>
                                                <small>Durchgeführt von: <?= e($maint['username']) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Kommentare -->
                        <?php
                        $comments = $pdo->prepare("
                            SELECT mc.*, u.username as user_name
                            FROM marker_comments mc
                            LEFT JOIN users u ON mc.user_id = u.id
                            WHERE mc.marker_id = ?
                            ORDER BY mc.created_at DESC
                        ");
                        $comments->execute([$id]);
                        $markerComments = $comments->fetchAll();

                        if (hasPermission('comments_add') || !empty($markerComments)):
                        ?>
                        <div class="info-card">
                            <h2><i class="fas fa-comments"></i> Kommentare & Notizen</h2>
                            
                            <!-- Kommentare anzeigen -->
                            <?php if (!empty($markerComments)): ?>
                                <div style="margin-bottom: 20px;">
                                    <?php foreach ($markerComments as $comment): ?>
                                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid #007bff;">
                                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                                <div>
                                                    <strong><?= e($comment['user_name'] ?? $comment['username']) ?></strong>
                                                    <small style="color: #6c757d; margin-left: 10px;">
                                                        <?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?>
                                                        <?php if ($comment['updated_at'] != $comment['created_at']): ?>
                                                            (bearbeitet)
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <?php if ($comment['user_id'] == $_SESSION['user_id'] && hasPermission('comments_edit')): ?>
                                                    <div>
                                                        <button onclick="editComment(<?= $comment['id'] ?>, '<?= e($comment['comment']) ?>')" 
                                                                class="btn btn-sm btn-secondary">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if (hasPermission('comments_delete')): ?>
                                                            <a href="delete_comment.php?id=<?= $comment['id'] ?>&marker=<?= $marker['id'] ?>" 
                                                            class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Kommentar löschen?')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <p style="margin: 0; white-space: pre-line;" id="comment-text-<?= $comment['id'] ?>">
                                                <?= e($comment['comment']) ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #6c757d; margin-bottom: 20px;">Noch keine Kommentare vorhanden</p>
                            <?php endif; ?>
                            
                            <!-- Neuen Kommentar hinzufügen -->
                            <?php if (hasPermission('comments_add')): ?>
                                <form method="POST" id="commentForm">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="add_comment" value="1">
                                    <input type="hidden" name="edit_comment_id" id="edit_comment_id" value="">
                                    
                                    <div class="form-group">
                                        <label for="comment">Kommentar hinzufügen</label>
                                        <textarea id="comment" name="comment" rows="3" required placeholder="Ihre Notiz oder Kommentar..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="fas fa-comment"></i> Kommentar hinzufügen
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="cancelEditBtn" style="display: none;" onclick="cancelEdit()">
                                        <i class="fas fa-times"></i> Abbrechen
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        // Karte mit Marker anzeigen
        const map = L.map('markerMap').setView([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        L.marker([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>]).addTo(map)
            .bindPopup('<strong><?= e($marker['name']) ?></strong>').openPopup();
        
        // Status-Änderung
        function setStatus(status) {
            if (confirm('Status wirklich auf "' + (status === 'verfuegbar' ? 'Verfügbar' : 'Vermietet') + '" ändern?')) {
                document.getElementById('new_status').value = status;
                document.getElementById('statusForm').submit();
            }
        }

        function editComment(id, text) {
            document.getElementById('comment').value = text;
            document.getElementById('edit_comment_id').value = id;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Änderungen speichern';
            document.getElementById('cancelEditBtn').style.display = 'inline-block';
            document.getElementById('comment').focus();
        }

        function cancelEdit() {
            document.getElementById('comment').value = '';
            document.getElementById('edit_comment_id').value = '';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-comment"></i> Kommentar hinzufügen';
            document.getElementById('cancelEditBtn').style.display = 'none';
        }

        // Link kopieren Funktion
        function copyQRLink() {
            const link = '<?= $qrUrl ?>';
            navigator.clipboard.writeText(link).then(() => {
                alert('Link in Zwischenablage kopiert!');
            }).catch(err => {
                console.error('Fehler:', err);
                // Fallback für ältere Browser
                const temp = document.createElement('input');
                document.body.appendChild(temp);
                temp.value = link;
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                alert('Link kopiert!');
            });
        }
    </script>
</body>
</html>