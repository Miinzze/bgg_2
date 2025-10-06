<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

trackUsage('advanced_search');

$message = '';
$messageType = '';

// Suchanfrage speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_search'])) {
    validateCSRF();
    
    $searchName = trim($_POST['search_name'] ?? '');
    $searchParams = $_POST['filters'] ?? [];
    
    if (empty($searchName)) {
        $message = 'Bitte geben Sie einen Namen für die Suche ein';
        $messageType = 'danger';
    } else {
        if (saveSearch($pdo, $_SESSION['user_id'], $searchName, $searchParams)) {
            $message = 'Suche erfolgreich gespeichert!';
            $messageType = 'success';
        } else {
            $message = 'Fehler beim Speichern der Suche';
            $messageType = 'danger';
        }
    }
}

// Gespeicherte Suche löschen
if (isset($_GET['delete_search'])) {
    if (deleteSavedSearch($pdo, $_GET['delete_search'], $_SESSION['user_id'])) {
        $message = 'Gespeicherte Suche gelöscht';
        $messageType = 'success';
    }
}

// Gespeicherte Suche laden
$loadedSearch = null;
if (isset($_GET['load_search'])) {
    $stmt = $pdo->prepare("SELECT * FROM saved_searches WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['load_search'], $_SESSION['user_id']]);
    $loadedSearch = $stmt->fetch();
    
    if ($loadedSearch) {
        updateSearchUsage($pdo, $loadedSearch['id']);
        $_GET = array_merge($_GET, json_decode($loadedSearch['search_params'], true));
    }
}

// Suchfilter sammeln
$filters = [
    'search' => $_GET['search'] ?? '',
    'category' => $_GET['category'] ?? '',
    'status' => $_GET['status'] ?? '',
    'maintenance_status' => $_GET['maintenance_status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'fuel_min' => $_GET['fuel_min'] ?? '',
    'fuel_max' => $_GET['fuel_max'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_dir' => $_GET['sort_dir'] ?? 'DESC'
];

// Nur suchen wenn Filter gesetzt sind
$results = [];
$searchPerformed = false;
if (!empty(array_filter($filters)) || isset($_GET['search_all'])) {
    $results = searchMarkers($pdo, $filters);
    $searchPerformed = true;
}

// Alle Kategorien für Filter
$categories = $pdo->query("SELECT DISTINCT category FROM markers WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Gespeicherte Suchen des Benutzers
$savedSearches = getSavedSearches($pdo, $_SESSION['user_id']);

// System-Einstellungen für Karte
$settings = getSystemSettings();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erweiterte Suche - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .search-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 0;
            height: calc(100vh - 90px);
            margin-top: 20px;
        }
        
        .search-sidebar {
            background: white;
            padding: 20px;
            overflow-y: auto;
            border-right: 2px solid var(--border-color);
            height: 100%;
        }
        
        .search-main {
            display: grid;
            grid-template-rows: 400px 1fr;
            gap: 0;
            height: 100%;
            overflow: hidden;
        }
        
        .map-section {
            position: relative;
            background: white;
            border-bottom: 2px solid var(--border-color);
        }
        
        #searchMap {
            width: 100%;
            height: 100%;
        }
        
        .results-section {
            background: white;
            padding: 20px;
            overflow-y: auto;
        }
        
        .filter-group {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .filter-group:last-child {
            border-bottom: none;
        }
        
        .filter-group h3 {
            margin: 0 0 12px 0;
            font-size: 13px;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: var(--medium-gray);
            font-weight: 500;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            font-size: 13px;
        }
        
        .range-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .result-card {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 12px;
            border-left: 4px solid var(--primary-color);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .result-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .result-card.active {
            border-left-color: var(--success-color);
            background: #e8f5e9;
        }
        
        .result-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary-color);
            margin: 0 0 8px 0;
        }
        
        .result-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 12px;
            color: var(--medium-gray);
            margin-bottom: 10px;
        }
        
        .result-actions {
            display: flex;
            gap: 8px;
        }
        
        .map-info-box {
            position: absolute;
            top: 15px;
            left: 15px;
            background: white;
            padding: 12px 18px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 1000;
            font-size: 14px;
            font-weight: 600;
        }
        
        .saved-searches {
            background: var(--light-gray);
            padding: 12px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .saved-search-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            margin-bottom: 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid var(--primary-color);
            font-size: 13px;
        }
        
        .saved-search-item:hover {
            background: var(--light-gray);
            transform: translateX(3px);
        }
        
        .no-results-empty {
            text-align: center;
            padding: 60px 20px;
            color: var(--medium-gray);
        }
        
        .no-results-empty i {
            font-size: 64px;
            opacity: 0.2;
            margin-bottom: 15px;
        }
        
        @media (max-width: 1024px) {
            .search-layout {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
                height: auto;
            }
            
            .search-sidebar {
                height: auto;
                border-right: none;
                border-bottom: 2px solid var(--border-color);
            }
            
            .search-main {
                grid-template-rows: 350px 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-search"></i> Erweiterte Suche</h1>
                    <p style="color: var(--medium-gray); margin-top: 5px;">Durchsuchen Sie alle Marker mit Kartenansicht</p>
                </div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <div class="search-layout">
                <!-- Filter Sidebar -->
                <div class="search-sidebar">
                    <form method="GET" id="searchForm">
                        <!-- Globale Suche -->
                        <div class="filter-group">
                            <h3><i class="fas fa-search"></i> Suche</h3>
                            <input type="text" 
                                   name="search" 
                                   placeholder="Name, RFID, Seriennummer..." 
                                   value="<?= e($filters['search']) ?>"
                                   id="globalSearch">
                        </div>
                        
                        <!-- Kategorie -->
                        <div class="filter-group">
                            <h3><i class="fas fa-tags"></i> Kategorie</h3>
                            <select name="category">
                                <option value="">Alle Kategorien</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat) ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>>
                                        <?= e($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Status -->
                        <div class="filter-group">
                            <h3><i class="fas fa-info-circle"></i> Status</h3>
                            <select name="status">
                                <option value="">Alle Status</option>
                                <option value="verfuegbar" <?= $filters['status'] === 'verfuegbar' ? 'selected' : '' ?>>Verfügbar</option>
                                <option value="vermietet" <?= $filters['status'] === 'vermietet' ? 'selected' : '' ?>>Vermietet</option>
                                <option value="wartung" <?= $filters['status'] === 'wartung' ? 'selected' : '' ?>>In Wartung</option>
                                <option value="storage" <?= $filters['status'] === 'storage' ? 'selected' : '' ?>>Lagergerät</option>
                                <option value="multi_device" <?= $filters['status'] === 'multi_device' ? 'selected' : '' ?>>Mehrgerät-Standort</option>
                            </select>
                        </div>
                        
                        <!-- Wartungsstatus -->
                        <div class="filter-group">
                            <h3><i class="fas fa-wrench"></i> Wartung</h3>
                            <select name="maintenance_status">
                                <option value="">Alle</option>
                                <option value="overdue" <?= $filters['maintenance_status'] === 'overdue' ? 'selected' : '' ?>>Überfällig</option>
                                <option value="due_soon" <?= $filters['maintenance_status'] === 'due_soon' ? 'selected' : '' ?>>Fällig (30 Tage)</option>
                                <option value="ok" <?= $filters['maintenance_status'] === 'ok' ? 'selected' : '' ?>>OK</option>
                            </select>
                        </div>
                        
                        <!-- Erstellungsdatum -->
                        <div class="filter-group">
                            <h3><i class="fas fa-calendar"></i> Datum</h3>
                            <label>Von:</label>
                            <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                            <label style="margin-top: 8px;">Bis:</label>
                            <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                        </div>
                        
                        <!-- Kraftstoff -->
                        <div class="filter-group">
                            <h3><i class="fas fa-gas-pump"></i> Kraftstoff (%)</h3>
                            <div class="range-inputs">
                                <div>
                                    <label>Min:</label>
                                    <input type="number" name="fuel_min" min="0" max="100" value="<?= e($filters['fuel_min']) ?>" placeholder="0">
                                </div>
                                <div>
                                    <label>Max:</label>
                                    <input type="number" name="fuel_max" min="0" max="100" value="<?= e($filters['fuel_max']) ?>" placeholder="100">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sortierung -->
                        <div class="filter-group">
                            <h3><i class="fas fa-sort"></i> Sortierung</h3>
                            <select name="sort_by" style="margin-bottom: 8px;">
                                <option value="created_at" <?= $filters['sort_by'] === 'created_at' ? 'selected' : '' ?>>Erstellungsdatum</option>
                                <option value="name" <?= $filters['sort_by'] === 'name' ? 'selected' : '' ?>>Name</option>
                                <option value="category" <?= $filters['sort_by'] === 'category' ? 'selected' : '' ?>>Kategorie</option>
                                <option value="next_maintenance" <?= $filters['sort_by'] === 'next_maintenance' ? 'selected' : '' ?>>Nächste Wartung</option>
                                <option value="fuel_level" <?= $filters['sort_by'] === 'fuel_level' ? 'selected' : '' ?>>Kraftstoff</option>
                            </select>
                            <select name="sort_dir">
                                <option value="ASC" <?= $filters['sort_dir'] === 'ASC' ? 'selected' : '' ?>>Aufsteigend</option>
                                <option value="DESC" <?= $filters['sort_dir'] === 'DESC' ? 'selected' : '' ?>>Absteigend</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-large" style="width: 100%; margin-bottom: 8px;">
                            <i class="fas fa-search"></i> Suchen
                        </button>
                        
                        <a href="advanced_search.php" class="btn btn-secondary" style="width: 100%; display: block; text-align: center;">
                            <i class="fas fa-redo"></i> Zurücksetzen
                        </a>
                    </form>
                    
                    <!-- Suche speichern -->
                    <?php if ($searchPerformed && !empty($results)): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid var(--border-color);">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="save_search" value="1">
                                <?php foreach ($filters as $key => $value): ?>
                                    <?php if (!empty($value)): ?>
                                        <input type="hidden" name="filters[<?= $key ?>]" value="<?= e($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 12px;">
                                    <i class="fas fa-save"></i> Suche speichern
                                </label>
                                <input type="text" 
                                       name="search_name" 
                                       placeholder="Name..." 
                                       required
                                       style="width: 100%; padding: 8px; margin-bottom: 8px; font-size: 13px;">
                                <button type="submit" class="btn btn-success" style="width: 100%; padding: 8px; font-size: 13px;">
                                    <i class="fas fa-save"></i> Speichern
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Gespeicherte Suchen -->
                    <?php if (!empty($savedSearches)): ?>
                        <div class="saved-searches">
                            <h3 style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600;">
                                <i class="fas fa-star"></i> Gespeicherte Suchen
                            </h3>
                            <?php foreach ($savedSearches as $saved): ?>
                                <div class="saved-search-item" onclick="window.location.href='?load_search=<?= $saved['id'] ?>'">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--primary-color);"><?= e($saved['search_name']) ?></div>
                                        <div style="font-size: 11px; color: var(--medium-gray);">
                                            <?= $saved['use_count'] ?>x verwendet
                                        </div>
                                    </div>
                                    <a href="?delete_search=<?= $saved['id'] ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="event.stopPropagation(); return confirm('Löschen?')"
                                       style="padding: 4px 8px; font-size: 11px;">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Main Content: Karte + Ergebnisse -->
                <div class="search-main">
                    <!-- Karte -->
                    <div class="map-section">
                        <div id="searchMap"></div>
                        <?php if ($searchPerformed): ?>
                            <div class="map-info-box">
                                <i class="fas fa-map-marker-alt"></i> <?= count($results) ?> Marker gefunden
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Ergebnisse -->
                    <div class="results-section">
                        <?php if ($searchPerformed): ?>
                            <?php if (empty($results)): ?>
                                <div class="no-results-empty">
                                    <i class="fas fa-search"></i>
                                    <h2>Keine Ergebnisse</h2>
                                    <p>Passen Sie Ihre Filter an</p>
                                </div>
                            <?php else: ?>
                                <h3 style="margin: 0 0 15px 0; color: var(--secondary-color);">
                                    <?= count($results) ?> Ergebnis<?= count($results) != 1 ? 'se' : '' ?>
                                </h3>
                                
                                <?php foreach ($results as $marker): ?>
                                    <?php 
                                    $status = getMaintenanceStatus($marker['next_maintenance']);
                                    $rentalStatus = getRentalStatusLabel($marker['rental_status']);
                                    ?>
                                    <div class="result-card" onclick="focusMarker(<?= $marker['id'] ?>)" id="result-<?= $marker['id'] ?>">
                                        <h3 class="result-title"><?= e($marker['name']) ?></h3>
                                        <div class="result-meta">
                                            <span><i class="fas fa-tag"></i> <?= e($marker['category']) ?></span>
                                            <span><i class="fas fa-microchip"></i> <?= e($marker['rfid_chip']) ?></span>
                                            <?php if ($marker['serial_number']): ?>
                                                <span><i class="fas fa-barcode"></i> <?= e($marker['serial_number']) ?></span>
                                            <?php endif; ?>
                                            <span class="badge badge-<?= $marker['is_storage'] ? 'info' : ($marker['is_multi_device'] ? 'info' : $rentalStatus['class']) ?>">
                                                <?= $marker['is_storage'] ? 'Lager' : ($marker['is_multi_device'] ? 'Mehrgerät' : $rentalStatus['label']) ?>
                                            </span>
                                        </div>
                                        <div class="result-actions">
                                            <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-sm btn-primary" onclick="event.stopPropagation()">
                                                <i class="fas fa-eye"></i> Details
                                            </a>
                                            <?php if (hasPermission('markers_edit')): ?>
                                                <a href="edit_marker.php?id=<?= $marker['id'] ?>" class="btn btn-sm btn-secondary" onclick="event.stopPropagation()">
                                                    <i class="fas fa-edit"></i> Bearbeiten
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="no-results-empty">
                                <i class="fas fa-filter"></i>
                                <h2>Willkommen</h2>
                                <p>Nutzen Sie die Filter links, um Marker zu suchen</p>
                                <a href="?search_all=1" class="btn btn-primary" style="margin-top: 15px;">
                                    <i class="fas fa-list"></i> Alle Marker anzeigen
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        // Karte initialisieren
        const map = L.map('searchMap').setView([<?= $settings['map_default_lat'] ?? 49.995567 ?>, <?= $settings['map_default_lng'] ?? 9.0731267 ?>], <?= $settings['map_default_zoom'] ?? 13 ?>);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Ergebnisse
        const results = <?= json_encode($results) ?>;
        const markerLayers = {};
        let activeMarkerId = null;
        
        // Funktion für farbige Marker-Icons
        function getColoredIcon(color) {
            return new L.Icon({
                iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${color}.png`,
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
        }
        
        const blueIcon = getColoredIcon('blue');
        const goldIcon = getColoredIcon('gold');
        const redIcon = getColoredIcon('red');
        const greenIcon = getColoredIcon('green');
        const violetIcon = getColoredIcon('violet');
        
        // Marker auf Karte platzieren
        if (results.length > 0) {
            const bounds = [];
            
            results.forEach(marker => {
                let icon = blueIcon;
                
                if (marker.is_multi_device) {
                    icon = violetIcon;
                } else if (marker.is_storage) {
                    icon = greenIcon;
                } else if (marker.rental_status === 'vermietet') {
                    icon = goldIcon;
                } else if (marker.rental_status === 'wartung') {
                    icon = redIcon;
                }
                
                const markerLayer = L.marker([marker.latitude, marker.longitude], { icon: icon })
                    .addTo(map)
                    .bindPopup(`
                        <div style="min-width: 200px;">
                            <h3 style="margin: 0 0 10px 0; color: var(--primary-color);">${marker.name}</h3>
                            <p style="margin: 5px 0;"><strong>Kategorie:</strong> ${marker.category || 'Keine'}</p>
                            ${marker.serial_number ? `<p style="margin: 5px 0;"><strong>SN:</strong> ${marker.serial_number}</p>` : ''}
                            <a href="view_marker.php?id=${marker.id}" class="btn btn-sm btn-primary" style="margin-top: 10px;">Details</a>
                        </div>
                    `);
                
                markerLayer.on('click', function() {
                    highlightResult(marker.id);
                });
                
                markerLayers[marker.id] = markerLayer;
                bounds.push([marker.latitude, marker.longitude]);
            });
            
            // Karte an alle Marker anpassen
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
            }
        }
        
        // Marker auf Karte fokussieren
        function focusMarker(markerId) {
            const marker = results.find(m => m.id === markerId);
            if (!marker || !markerLayers[markerId]) return;
            
            // Zoom zur Position
            map.setView([marker.latitude, marker.longitude], 17);
            
            // Popup öffnen
            markerLayers[markerId].openPopup();
            
            // Highlight Result-Card
            highlightResult(markerId);
        }
        
        // Result-Card highlighten
        function highlightResult(markerId) {
            // Alle Cards zurücksetzen
            document.querySelectorAll('.result-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Aktive Card markieren
            const resultCard = document.getElementById('result-' + markerId);
            if (resultCard) {
                resultCard.classList.add('active');
                resultCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            activeMarkerId = markerId;
        }
        
        // Auto-Submit bei Enter
        document.getElementById('globalSearch')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
            }
        });
    </script>
</body>
</html>