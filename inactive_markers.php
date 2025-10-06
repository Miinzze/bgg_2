<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

// Berechtigung prüfen - z.B. markers_edit oder neue Permission
if (!hasPermission('inactive_markers_view')) {
    die('<h1>Zugriff verweigert</h1><p>Sie haben keine Berechtigung, nicht-aktivierte Marker zu sehen.</p><a href="index.php">Zur Übersicht</a>');
}

// Filter und Suche
$searchTerm = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

// SQL Query aufbauen
$sql = "SELECT * FROM markers WHERE is_activated = 0 AND deleted_at IS NULL";
$params = [];

// Suche
if ($searchTerm) {
    $sql .= " AND (name LIKE ? OR serial_number LIKE ? OR qr_code LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

// Kategorie-Filter
if ($categoryFilter) {
    $sql .= " AND category = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY created_at DESC";

// Daten abrufen
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inactiveMarkers = $stmt->fetchAll();

// Statistiken
$totalInactive = count($inactiveMarkers);

$stmt = $pdo->prepare("
    SELECT category, COUNT(*) as count 
    FROM markers 
    WHERE is_activated = 0 AND deleted_at IS NULL 
    GROUP BY category
");
$stmt->execute();
$categoryStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Kategorien für Filter
$stmt = $pdo->query("SELECT DISTINCT category FROM markers WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Mobile Detection
$isMobile = isMobileDevice();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nicht aktivierte Marker - RFID Marker System</title>
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
            border-left: 4px solid #ffc107;
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
        
        .filter-bar {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px var(--shadow);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-row input,
        .filter-row select {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--input-bg);
            color: var(--text-color);
        }
        
        .marker-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
            box-shadow: 0 2px 4px var(--shadow);
            transition: all 0.3s;
        }
        
        .marker-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        .marker-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .marker-title {
            flex: 1;
        }
        
        .marker-title h3 {
            margin: 0 0 5px 0;
            color: var(--text-color);
            font-size: 20px;
        }
        
        .marker-title .qr-code {
            font-family: 'Courier New', monospace;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
        }
        
        .marker-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item i {
            color: #ffc107;
            width: 20px;
            text-align: center;
        }
        
        .info-item .label {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .info-item .value {
            color: var(--text-color);
            font-weight: 600;
        }
        
        .marker-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .warning-badge {
            background: #fff3cd;
            color: #856404;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        body.dark-mode .warning-badge {
            background: #4d3d1a;
            color: #ffd89f;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 10px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: var(--text-secondary);
            opacity: 0.3;
            margin-bottom: 20px;
        }
        
        .mobile-scan-hint {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: none;
        }
        
        @media (max-width: 768px) {
            .mobile-scan-hint {
                display: block;
            }
            
            .marker-info {
                grid-template-columns: 1fr;
            }
            
            .marker-actions {
                flex-direction: column;
            }
            
            .marker-actions .btn {
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
                <h1><i class="fas fa-hourglass-half"></i> Nicht aktivierte Marker</h1>
                <div class="header-actions">
                    <a href="qr_code_list.php" class="btn btn-secondary">
                        <i class="fas fa-qrcode"></i> QR-Code Verwaltung
                    </a>
                    <a href="index.php" class="btn btn-info">
                        <i class="fas fa-map"></i> Zur Karte
                    </a>
                </div>
            </div>
            
            <?php if ($isMobile): ?>
            <div class="mobile-scan-hint">
                <h3 style="margin-top: 0;"><i class="fas fa-mobile-alt"></i> Mobiles Gerät erkannt</h3>
                <p>
                    <i class="fas fa-info-circle"></i> 
                    Um einen Marker zu aktivieren, bearbeiten Sie ihn und erfassen Sie die GPS-Position vor Ort am Gerät.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Statistiken -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-hourglass-half"></i> Warten auf Aktivierung</h3>
                    <div class="value"><?= $totalInactive ?></div>
                </div>
                
                <?php foreach ($categoryStats as $cat => $count): ?>
                    <?php if ($cat): ?>
                    <div class="stat-card">
                        <h3><i class="fas fa-tag"></i> <?= e($cat) ?></h3>
                        <div class="value"><?= $count ?></div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Filter -->
            <div class="filter-bar">
                <form method="GET" class="filter-row">
                    <input type="search" 
                           name="search" 
                           placeholder="Suche: Name, Seriennummer, QR-Code..." 
                           value="<?= e($searchTerm) ?>">
                    
                    <select name="category">
                        <option value="">Alle Kategorien</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                                <?= e($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtern
                    </button>
                    
                    <?php if ($searchTerm || $categoryFilter): ?>
                    <a href="inactive_markers.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Zurücksetzen
                    </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Info Box -->
            <div class="alert alert-info" style="margin-bottom: 25px;">
                <h3><i class="fas fa-info-circle"></i> Über nicht-aktivierte Marker</h3>
                <p>
                    Diese Marker wurden im Büro erstellt, aber noch nicht vor Ort am Gerät aktiviert. 
                    Um einen Marker zu aktivieren:
                </p>
                <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                    <li>QR-Code am Gerät anbringen</li>
                    <li>Mit mobilem Gerät "Bearbeiten" öffnen</li>
                    <li>GPS-Position vor Ort erfassen</li>
                    <li>Speichern → Marker wird aktiviert und erscheint auf der Karte</li>
                </ol>
            </div>
            
            <!-- Marker Liste -->
            <?php if (empty($inactiveMarkers)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h2>Keine nicht-aktivierten Marker</h2>
                    <p>
                        <?php if ($searchTerm || $categoryFilter): ?>
                            Keine Marker gefunden, die den Filterkriterien entsprechen.
                        <?php else: ?>
                            Alle erstellten Marker wurden bereits aktiviert!
                        <?php endif; ?>
                    </p>
                    <?php if ($searchTerm || $categoryFilter): ?>
                        <a href="inactive_markers.php" class="btn btn-secondary" style="margin-top: 15px;">
                            <i class="fas fa-redo"></i> Filter zurücksetzen
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($inactiveMarkers as $marker): ?>
                    <div class="marker-card">
                        <div class="marker-header">
                            <div class="marker-title">
                                <h3>
                                    <?php if ($marker['is_multi_device']): ?>
                                        <i class="fas fa-layer-group"></i>
                                    <?php elseif ($marker['is_storage']): ?>
                                        <i class="fas fa-warehouse"></i>
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                    <?= e($marker['name']) ?>
                                </h3>
                                <div class="qr-code">
                                    <i class="fas fa-qrcode"></i> QR: <?= e($marker['qr_code']) ?>
                                </div>
                            </div>
                            <span class="warning-badge">
                                <i class="fas fa-exclamation-triangle"></i> Nicht aktiviert
                            </span>
                        </div>
                        
                        <div class="marker-info">
                            <?php if ($marker['category']): ?>
                            <div class="info-item">
                                <i class="fas fa-tag"></i>
                                <div>
                                    <div class="label">Kategorie</div>
                                    <div class="value"><?= e($marker['category']) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($marker['serial_number']): ?>
                            <div class="info-item">
                                <i class="fas fa-barcode"></i>
                                <div>
                                    <div class="label">Seriennummer</div>
                                    <div class="value"><?= e($marker['serial_number']) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <i class="fas fa-calendar"></i>
                                <div>
                                    <div class="label">Erstellt am</div>
                                    <div class="value"><?= formatDate($marker['created_at'], 'd.m.Y') ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-user"></i>
                                <div>
                                    <div class="label">Erstellt von</div>
                                    <div class="value">
                                        <?php
                                        if ($marker['created_by']) {
                                            $userInfo = getUserInfo($marker['created_by'], $pdo);
                                            echo e($userInfo['username'] ?? 'Unbekannt');
                                        } else {
                                            echo 'System';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="marker-actions">
                            <a href="edit_marker.php?id=<?= $marker['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> 
                                <?= $isMobile ? 'Bearbeiten & Aktivieren' : 'Bearbeiten' ?>
                            </a>
                            
                            <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> Details
                            </a>
                            
                            <a href="print_qr.php?id=<?= $marker['id'] ?>" class="btn btn-info" target="_blank">
                                <i class="fas fa-print"></i> QR-Code drucken
                            </a>
                            
                            <?php if (hasPermission('markers_delete')): ?>
                            <a href="delete_marker.php?id=<?= $marker['id'] ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('Marker wirklich löschen?')">
                                <i class="fas fa-trash"></i> Löschen
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Zusätzliche Info -->
            <?php if (!empty($inactiveMarkers)): ?>
            <div class="alert alert-warning" style="margin-top: 30px;">
                <h3><i class="fas fa-lightbulb"></i> Tipp zur Aktivierung</h3>
                <p>
                    <strong>Desktop:</strong> Öffnen Sie den Marker zum Bearbeiten, geben Sie manuell GPS-Koordinaten ein oder nutzen Sie die Karte.<br>
                    <strong>Mobil:</strong> Öffnen Sie den Marker vor Ort am Gerät, lassen Sie die GPS-Position automatisch erfassen und speichern Sie.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="js/dark-mode.js"></script>
</body>
</html>