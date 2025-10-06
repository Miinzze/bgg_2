<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

// Statistiken laden - NUR AKTIVIERTE MARKER
try {
    // LagergerĂ€te (aktiviert)
    $stmt = $pdo->query("SELECT COUNT(*) FROM markers WHERE is_storage = 1 AND is_activated = 1 AND deleted_at IS NULL");
    $storageCount = $stmt->fetchColumn();
    
    // VerfĂ¼gbare MietgerĂ€te (aktiviert)
    $stmt = $pdo->query("SELECT COUNT(*) FROM markers WHERE is_storage = 0 AND rental_status = 'verfuegbar' AND is_activated = 1 AND deleted_at IS NULL");
    $availableCount = $stmt->fetchColumn();
    
    // Vermietete GerĂ€te (aktiviert)
    $stmt = $pdo->query("SELECT COUNT(*) FROM markers WHERE is_storage = 0 AND rental_status = 'vermietet' AND is_activated = 1 AND deleted_at IS NULL");
    $rentedCount = $stmt->fetchColumn();
    
    // FĂ€llige Wartungen (aktiviert)
    $stmt = $pdo->query("SELECT COUNT(*) FROM markers WHERE next_maintenance <= CURDATE() AND next_maintenance IS NOT NULL AND is_activated = 1 AND deleted_at IS NULL");
    $maintenanceDueCount = $stmt->fetchColumn();
    
    // Wartungen diese Woche (aktiviert)
    $stmt = $pdo->query("SELECT COUNT(*) FROM markers WHERE next_maintenance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND is_activated = 1 AND deleted_at IS NULL");
    $maintenanceThisWeek = $stmt->fetchColumn();
    
    $totalRentalDevices = $availableCount + $rentedCount;
    $utilizationRate = $totalRentalDevices > 0 ? round(($rentedCount / $totalRentalDevices) * 100) : 0;
    
} catch (Exception $e) {
    $storageCount = 0;
    $availableCount = 0;
    $rentedCount = 0;
    $maintenanceDueCount = 0;
    $maintenanceThisWeek = 0;
    $totalRentalDevices = 0;
    $utilizationRate = 0;
}

// NUR AKTIVIERTE UND NICHT GELÖSCHTE MARKER LADEN
$stmt = $pdo->query("SELECT * FROM markers WHERE is_activated = 1 AND deleted_at IS NULL ORDER BY created_at DESC");
$markers = $stmt->fetchAll();

// Nicht-aktivierte Marker zÀhlen (fßr Info)
$stmt = $pdo->query("SELECT COUNT(*) FROM markers WHERE is_activated = 0 AND deleted_at IS NULL");
$inactiveCount = $stmt->fetchColumn();

$settings = getSystemSettings();
$showLegend = !empty($settings['show_map_legend']);
$showMessages = !empty($settings['show_system_messages']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Marker System - Übersicht</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="map-dashboard-container">
        <?php if ($showMessages): ?>
        <div class="system-message" style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1001; background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); max-width: 500px;">
            <strong>System bereit</strong> - <?= count($markers) ?> aktive Marker auf Karte
            <?php if ($inactiveCount > 0): ?>
                <br><small style="color: #666;"><?= $inactiveCount ?> Marker warten auf Aktivierung (QR-Code scannen)</small>
            <?php endif; ?>
            <button onclick="this.parentElement.style.display='none'" style="float: right; border: none; background: none; cursor: pointer; font-size: 18px;">Ă—</button>
        </div>
        <?php endif; ?>
        
        <div class="search-bar">
            <div class="search-input-group">
                <input type="text" id="searchInput" placeholder="Suche: Name, Seriennummer oder Status...">
                <button onclick="searchMarkers()"><i class="fas fa-search"></i></button>
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>
        
        <div class="map-container" id="mapContainer">
            <div id="map"></div>
            
        <?php if ($showLegend): ?>
        <!-- Legende - nur auf Desktop -->
        <div class="map-legend" id="mapLegend">
            <h4>Legende</h4>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #3388ff;"></div>
                <span>VerfĂ¼gbar</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ffc107;"></div>
                <span>Vermietet</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #dc3545;"></div>
                <span>Wartung</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #28a745;"></div>
                <span>Lager</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                <span>MehrgerĂ€t</span>
            </div>
        </div>
        <?php endif; ?>
            
            <!-- Dashboard Toggle Button -->
            <button class="dashboard-toggle-map" onclick="toggleDashboard()" style="position: absolute; right: 20px; top: 20px; z-index: 1001; width: 50px; height: 50px; background: white; border: 2px solid #007bff; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #007bff; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
                <i class="fas fa-chart-bar"></i>
            </button>
        </div>
        
        <div class="dashboard-sidebar collapsed" id="dashboard">
            <button class="dashboard-toggle" onclick="toggleDashboard()">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <div class="dashboard-header">
                <h2>Statistik-Dashboard</h2>
            </div>
            
            <div class="dashboard-content">
                <div class="dashboard-section">
                    <h3>Ăœbersicht</h3>
                    
                    <div class="stat-card success">
                        <div class="stat-label">
                            <i class="fas fa-warehouse"></i>
                            LagergerĂ€te Gesamt
                        </div>
                        <div class="stat-value"><?= $storageCount ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">
                            <i class="fas fa-check-circle"></i>
                            VerfĂ¼gbare MietgerĂ€te
                        </div>
                        <div class="stat-value"><?= $availableCount ?></div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-label">
                            <i class="fas fa-handshake"></i>
                            Vermietete GerĂ€te
                        </div>
                        <div class="stat-value"><?= $rentedCount ?></div>
                    </div>
                    
                    <div class="stat-card <?= $maintenanceDueCount > 0 ? 'danger' : '' ?>">
                        <div class="stat-label">
                            <i class="fas fa-wrench"></i>
                            FÀllige Wartungen
                        </div>
                        <div class="stat-value"><?= $maintenanceDueCount ?></div>
                    </div>
                    
                    <?php if ($inactiveCount > 0): ?>
                    <div class="stat-card" style="background: #f8f9fa; border-left: 4px solid #6c757d;">
                        <div class="stat-label">
                            <i class="fas fa-qrcode"></i>
                            Warten auf Aktivierung
                        </div>
                        <div class="stat-value"><?= $inactiveCount ?></div>
                        <small style="color: #666; font-size: 0.8em;">QR-Codes noch nicht gescannt</small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="dashboard-section">
                    <h3>Auslastung</h3>
                    <div class="chart-container">
                        <h3>Auslastungsrate: <?= $utilizationRate ?>%</h3>
                        <canvas id="utilizationChart" height="200"></canvas>
                    </div>
                </div>
                
                <div class="dashboard-section">
                    <h3>Wartungen</h3>
                    <div class="stat-card <?= $maintenanceThisWeek > 0 ? 'warning' : 'success' ?>">
                        <div class="stat-label">
                            <i class="fas fa-calendar-week"></i>
                            Wartungen diese Woche
                        </div>
                        <div class="stat-value"><?= $maintenanceThisWeek ?></div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="markers.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> Alle Marker anzeigen
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    const allMarkers = <?= json_encode($markers) ?>;
    let highlightedMarker = null;
    let markerObjects = {};
    
    function searchMarkers() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
        const resultsDiv = document.getElementById('searchResults');
        
        if (searchTerm.length < 2) {
            resultsDiv.classList.remove('active');
            return;
        }
        
        // NUR IN AKTIVIERTEN MARKERN SUCHEN
        const results = allMarkers.filter(marker => {
            const name = (marker.name || '').toLowerCase();
            const serial = (marker.serial_number || '').toLowerCase();
            const status = (marker.rental_status || '').toLowerCase();
            const category = (marker.category || '').toLowerCase();
            
            return name.includes(searchTerm) || 
                   serial.includes(searchTerm) || 
                   status.includes(searchTerm) ||
                   category.includes(searchTerm);
        });
        
        displaySearchResults(results);
    }
    
    function displaySearchResults(results) {
        const resultsDiv = document.getElementById('searchResults');
        
        if (results.length === 0) {
            resultsDiv.innerHTML = '<div class="no-results">Keine aktiven Marker gefunden</div>';
            resultsDiv.classList.add('active');
            return;
        }
        
        let html = '';
        results.forEach(marker => {
            const statusText = {
                'verfuegbar': 'VerfĂ¼gbar',
                'vermietet': 'Vermietet',
                'wartung': 'In Wartung'
            }[marker.rental_status] || marker.rental_status;
            
            const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
            
            html += `
                <div class="search-result-item">
                    <h4>${marker.name}</h4>
                    <p>
                        ${marker.serial_number ? 'SN: ' + marker.serial_number + ' | ' : ''}
                        ${marker.category || 'Keine Kategorie'} | 
                        ${marker.is_storage ? 'Lager' : statusText}
                    </p>
                    <div class="search-result-actions">
                        <button class="btn-show-map" onclick="showOnMap(${marker.id})">
                            <i class="fas fa-map-marker-alt"></i> Auf Karte
                        </button>
                        <button class="btn-show-details" onclick="window.location.href='view_marker.php?id=${marker.id}'">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                        ${isMobile ? 
                            `<button class="btn-edit" onclick="scanToEdit(${marker.id})">
                                <i class="fas fa-qrcode"></i> Scannen
                            </button>` :
                            `<button class="btn-edit" onclick="window.location.href='edit_marker.php?id=${marker.id}'">
                                <i class="fas fa-edit"></i> Bearbeiten
                            </button>`
                        }
                    </div>
                </div>
            `;
        });
        
        resultsDiv.innerHTML = html;
        resultsDiv.classList.add('active');
    }
    
    function showOnMap(markerId) {
        const marker = allMarkers.find(m => m.id === markerId);
        if (!marker) return;
        
        map.setView([marker.latitude, marker.longitude], 18);
        
        if (highlightedMarker) {
            map.removeLayer(highlightedMarker);
        }
        
        highlightedMarker = L.circle([marker.latitude, marker.longitude], {
            radius: 20,
            color: '#ff0000',
            fillColor: '#ff0000',
            fillOpacity: 0.3,
            weight: 3,
            className: 'highlight-pulse'
        }).addTo(map);
        
        if (markerObjects[markerId]) {
            markerObjects[markerId].openPopup();
        }
        
        setTimeout(() => {
            if (highlightedMarker) {
                map.removeLayer(highlightedMarker);
                highlightedMarker = null;
            }
        }, 10000);
        
        document.getElementById('searchResults').classList.remove('active');
    }
    
    function scanToEdit(markerId) {
        alert('Bitte scannen Sie den QR-Code des Markers, um ihn zu bearbeiten.');
        window.location.href = 'scan.php?edit=' + markerId;
    }
    
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            searchMarkers();
        } else {
            searchMarkers();
        }
    });
    
    const map = L.map('map').setView([<?= $settings['map_default_lat'] ?>, <?= $settings['map_default_lng'] ?>], <?= $settings['map_default_zoom'] ?>);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    const markers = <?= json_encode($markers) ?>;
    
    // Funktion zum Erstellen von farbigen Marker-Icons
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
    
    // Standard Leaflet Icons in verschiedenen Farben
    const blueIcon = getColoredIcon('blue');      // VerfĂ¼gbar
    const goldIcon = getColoredIcon('gold');      // Vermietet
    const redIcon = getColoredIcon('red');        // Wartung
    const greenIcon = getColoredIcon('green');    // Lager
    const violetIcon = getColoredIcon('violet');  // Multi-Device
    
    // NUR AKTIVIERTE MARKER AUF KARTE ANZEIGEN
    markers.forEach(marker => {
        let icon = blueIcon; // Default
        
        if (marker.is_multi_device) {
            icon = violetIcon;
        } else if (marker.is_storage) {
            icon = greenIcon;
        } else if (marker.rental_status === 'vermietet') {
            icon = goldIcon;
        } else if (marker.rental_status === 'wartung') {
            icon = redIcon;
        }
        
        const mapMarker = L.marker([marker.latitude, marker.longitude], { icon: icon })
            .addTo(map)
            .bindPopup(`
                <div class="marker-popup">
                    <h3>${marker.name}</h3>
                    <p><strong>Kategorie:</strong> ${marker.category || 'Keine'}</p>
                    <p><strong>Status:</strong> ${getStatusText(marker)}</p>
                    ${marker.serial_number ? `<p><strong>Seriennummer:</strong> ${marker.serial_number}</p>` : ''}
                    <a href="view_marker.php?id=${marker.id}" class="btn btn-sm btn-primary">Details</a>
                </div>
            `);
        
        markerObjects[marker.id] = mapMarker;
    });
    
    function getStatusText(marker) {
        if (marker.is_multi_device) return 'MehrgerĂ€t-Standort';
        if (marker.is_storage) return 'LagergerĂ€t';
        
        switch(marker.rental_status) {
            case 'verfuegbar': return 'VerfĂ¼gbar';
            case 'vermietet': return 'Vermietet';
            case 'wartung': return 'In Wartung';
            default: return marker.rental_status;
        }
    }
    
    function toggleDashboard() {
        const dashboard = document.getElementById('dashboard');
        const mapContainer = document.getElementById('mapContainer');
        const toggleButton = document.querySelector('.dashboard-toggle-map i');
        
        dashboard.classList.toggle('collapsed');
        mapContainer.classList.toggle('dashboard-open');
        
        if (dashboard.classList.contains('collapsed')) {
            toggleButton.className = 'fas fa-chart-bar';
        } else {
            toggleButton.className = 'fas fa-times';
        }
        
        setTimeout(() => {
            map.invalidateSize();
        }, 350);
    }
    
    const ctx = document.getElementById('utilizationChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['VerfĂ¼gbar', 'Vermietet'],
            datasets: [{
                data: [<?= $availableCount ?>, <?= $rentedCount ?>],
                backgroundColor: ['#007bff', '#ffc107'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
    
    setInterval(() => {
        location.reload();
    }, 5 * 60 * 1000);
    </script>

</body>
</html>