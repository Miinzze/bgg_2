<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

// Pagination-Einstellungen
$itemsPerPage = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Filter
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// SQL Query aufbauen
$sql = "SELECT m.* FROM markers m WHERE 1=1";
$params = [];

if ($searchTerm) {
    $sql .= " AND (m.name LIKE ? OR m.serial_number LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if ($categoryFilter) {
    $sql .= " AND m.category = ?";
    $params[] = $categoryFilter;
}

if ($statusFilter) {
    $sql .= " AND m.rental_status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter) {
    if ($typeFilter === 'storage') {
        $sql .= " AND m.is_storage = 1";
    } elseif ($typeFilter === 'multi') {
        $sql .= " AND m.is_multi_device = 1";
    } elseif ($typeFilter === 'rental') {
        $sql .= " AND m.is_storage = 0 AND m.is_multi_device = 0";
    }
}

// Gesamt-Anzahl für Pagination
$countSql = str_replace("SELECT m.*", "SELECT COUNT(*)", $sql);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Daten mit Limit laden
$sql .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$markers = $stmt->fetchAll();

// Für Multi-Device: Seriennummern zählen
$serialNumberCounts = [];
foreach ($markers as $marker) {
    if ($marker['is_multi_device']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marker_serial_numbers WHERE marker_id = ?");
        $stmt->execute([$marker['id']]);
        $serialNumberCounts[$marker['id']] = $stmt->fetchColumn();
    }
}

// Kategorien für Filter
$stmt = $pdo->query("SELECT DISTINCT category FROM markers WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alle Marker - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            padding: 8px 15px;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--text-color);
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 600;
        }
        
        .pagination-btn:hover:not(.disabled):not(.active) {
            background: var(--light-gray);
            border-color: var(--primary-color);
        }
        
        .pagination-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-info {
            padding: 8px 15px;
            color: var(--medium-gray);
            font-size: 14px;
        }
        
        .items-per-page {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .items-per-page select {
            padding: 8px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-map-marker-alt"></i> Alle Marker</h1>
                <div class="header-actions">
                    <a href="export_markers.php" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Exportieren
                    </a>
                    <a href="import_markers.php" class="btn btn-info">
                        <i class="fas fa-file-import"></i> Importieren
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-map"></i> Zur Karte
                    </a>
                </div>
            </div>
            
            <!-- Statistik -->
            <div class="stats-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="stat-box" style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #007bff;"><?= $totalItems ?></div>
                    <div style="font-size: 14px; color: #6c757d;">Gesamt Marker</div>
                </div>
            </div>
            
            <!-- Items per Page -->
            <div class="items-per-page">
                <label for="itemsPerPage">Anzeigen:</label>
                <select id="itemsPerPage" onchange="changeItemsPerPage(this.value)">
                    <option value="10" <?= $itemsPerPage == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $itemsPerPage == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $itemsPerPage == 100 ? 'selected' : '' ?>>100</option>
                </select>
                <span>pro Seite</span>
            </div>
            
            <!-- Filter -->
            <div class="filter-bar" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <input type="search" 
                       id="searchInput" 
                       placeholder="Suche nach Name, Seriennummer..." 
                       value="<?= e($searchTerm) ?>"
                       style="flex: 1; min-width: 250px; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                
                <select id="categoryFilter" style="padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                    <option value="">Alle Kategorien</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="statusFilter" style="padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                    <option value="">Alle Status</option>
                    <option value="verfuegbar" <?= $statusFilter === 'verfuegbar' ? 'selected' : '' ?>>Verfügbar</option>
                    <option value="vermietet" <?= $statusFilter === 'vermietet' ? 'selected' : '' ?>>Vermietet</option>
                    <option value="wartung" <?= $statusFilter === 'wartung' ? 'selected' : '' ?>>In Wartung</option>
                </select>
                
                <select id="typeFilter" style="padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                    <option value="">Alle Typen</option>
                    <option value="rental" <?= $typeFilter === 'rental' ? 'selected' : '' ?>>Mietgeräte</option>
                    <option value="storage" <?= $typeFilter === 'storage' ? 'selected' : '' ?>>Lagergeräte</option>
                    <option value="multi" <?= $typeFilter === 'multi' ? 'selected' : '' ?>>Mehrgerät-Standorte</option>
                </select>
                
                <button class="btn btn-primary" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Filtern
                </button>
                <button class="btn btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Zurücksetzen
                </button>
            </div>
            
            <!-- Marker Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;" id="markerGrid">
                <?php if (empty($markers)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; background: white; border-radius: 8px; color: #666;">
                        <i class="fas fa-search" style="font-size: 48px; opacity: 0.3; margin-bottom: 20px;"></i>
                        <h3>Keine Marker gefunden</h3>
                        <p>Versuchen Sie andere Filtereinstellungen</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($markers as $marker): ?>
                        <?php
                        $cardClass = '';
                        if ($marker['is_multi_device']) $cardClass = 'multi-device';
                        elseif ($marker['is_storage']) $cardClass = 'storage';
                        elseif ($marker['rental_status'] === 'vermietet') $cardClass = 'rented';
                        elseif ($marker['rental_status'] === 'wartung') $cardClass = 'maintenance';
                        ?>
                        <div class="marker-card <?= $cardClass ?>" style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s; border-left: 4px solid #007bff;">
                            <h3 style="margin: 0 0 10px 0; color: #333; font-size: 18px;">
                                <?php if ($marker['is_multi_device']): ?>
                                    <i class="fas fa-layer-group"></i>
                                <?php elseif ($marker['is_storage']): ?>
                                    <i class="fas fa-warehouse"></i>
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                                <?= e($marker['name']) ?>
                            </h3>
                            
                            <div style="margin-bottom: 15px; font-size: 14px; color: #666;">
                                <?php if ($marker['category']): ?>
                                    <div style="margin-bottom: 8px;">
                                        <i class="fas fa-tag" style="width: 20px; color: #007bff;"></i>
                                        <?= e($marker['category']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($marker['serial_number']): ?>
                                    <div style="margin-bottom: 8px;">
                                        <i class="fas fa-barcode" style="width: 20px; color: #007bff;"></i>
                                        <?= e($marker['serial_number']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div>
                                    <i class="fas fa-calendar" style="width: 20px; color: #007bff;"></i>
                                    <?= date('d.m.Y', strtotime($marker['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 10px; padding-top: 15px; border-top: 1px solid #eee;">
                                <a href="view_marker.php?id=<?= $marker['id'] ?>" class="btn btn-sm btn-primary" style="flex: 1;">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                                <?php if (hasPermission('markers_edit')): ?>
                                    <a href="edit_marker.php?id=<?= $marker['id'] ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?= buildQueryString(['page']) ?>" class="pagination-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?= $page - 1 ?><?= buildQueryString(['page']) ?>" class="pagination-btn">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-angle-double-left"></i>
                        </span>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-angle-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Seite <?= $page ?> von <?= $totalPages ?> 
                        (<?= $totalItems ?> Marker)
                    </span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= buildQueryString(['page']) ?>" class="pagination-btn">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?= $totalPages ?><?= buildQueryString(['page']) ?>" class="pagination-btn">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-angle-right"></i>
                        </span>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-angle-double-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    function buildUrl(params) {
        const url = new URL(window.location);
        Object.keys(params).forEach(key => {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        url.searchParams.delete('page'); // Reset zur ersten Seite bei Filteränderung
        return url.toString();
    }
    
    function applyFilters() {
        const params = {
            search: document.getElementById('searchInput').value,
            category: document.getElementById('categoryFilter').value,
            status: document.getElementById('statusFilter').value,
            type: document.getElementById('typeFilter').value
        };
        window.location.href = buildUrl(params);
    }
    
    function resetFilters() {
        window.location.href = 'markers.php';
    }
    
    function changeItemsPerPage(value) {
        const url = new URL(window.location);
        url.searchParams.set('per_page', value);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }
    
    // Enter-Taste im Suchfeld
    document.getElementById('searchInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
    </script>
</body>
</html>

<?php
// Helper-Funktion für Query-String
function buildQueryString($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return $params ? '&' . http_build_query($params) : '';
}
?>