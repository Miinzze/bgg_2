<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('reports_generate');

trackUsage('reports_view');

$message = '';
$messageType = '';

// Report-Statistiken
$stats = [
    'total_markers' => $pdo->query("SELECT COUNT(*) FROM markers")->fetchColumn(),
    'total_maintenances' => $pdo->query("SELECT COUNT(*) FROM maintenance_history")->fetchColumn(),
    'pending_maintenances' => $pdo->query("SELECT COUNT(*) FROM markers WHERE next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND is_storage = 0")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Reports & Exporte</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .report-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .report-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 18px;
        }
        .report-card p {
            color: #6c757d;
            font-size: 14px;
            margin: 0 0 15px 0;
        }
        .report-card .report-actions {
            display: flex;
            gap: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 36px;
        }
        .stat-box p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-chart-bar"></i> Reports & Exporte</h1>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>
            
            <!-- Statistik-Übersicht -->
            <div class="stats-grid">
                <div class="stat-box">
                    <h3><?= $stats['total_markers'] ?></h3>
                    <p>Gesamt Marker</p>
                </div>
                <div class="stat-box">
                    <h3><?= $stats['total_maintenances'] ?></h3>
                    <p>Wartungen durchgeführt</p>
                </div>
                <div class="stat-box">
                    <h3><?= $stats['pending_maintenances'] ?></h3>
                    <p>Wartungen fällig (30 Tage)</p>
                </div>
                <div class="stat-box">
                    <h3><?= $stats['total_users'] ?></h3>
                    <p>Aktive Benutzer</p>
                </div>
            </div>
            
            <!-- Report-Karten -->
            <h2>Verfügbare Reports</h2>
            <div class="report-grid">
                
                <!-- Marker-Übersicht -->
                <div class="report-card">
                    <h3><i class="fas fa-map-marker-alt"></i> Marker-Übersicht</h3>
                    <p>Vollständige Liste aller Marker mit Details</p>
                    <div class="report-actions">
                        <a href="export_markers.php?format=pdf" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="export_markers.php?format=excel" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="export_markers.php?format=csv" class="btn btn-sm btn-secondary">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    </div>
                </div>
                
                <!-- Wartungs-Report -->
                <div class="report-card">
                    <h3><i class="fas fa-wrench"></i> Wartungs-Historie</h3>
                    <p>Alle durchgeführten Wartungen mit Details</p>
                    <div class="report-actions">
                        <a href="export_maintenance.php?format=pdf" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="export_maintenance.php?format=excel" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                    </div>
                </div>
                
                <!-- Fällige Wartungen -->
                <div class="report-card">
                    <h3><i class="fas fa-exclamation-triangle"></i> Fällige Wartungen</h3>
                    <p>Marker mit anstehenden oder überfälligen Wartungen</p>
                    <div class="report-actions">
                        <a href="export_pending_maintenance.php?format=pdf" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="export_pending_maintenance.php?format=excel" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                    </div>
                </div>
                
                <!-- Nutzungsstatistik -->
                <div class="report-card">
                    <h3><i class="fas fa-chart-line"></i> Nutzungsstatistik</h3>
                    <p>Benutzeraktivitäten und System-Nutzung</p>
                    <div class="report-actions">
                        <a href="export_usage_stats.php?format=pdf" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="export_usage_stats.php?format=excel" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                    </div>
                </div>
                
                <!-- Aktivitätsprotokoll -->
                <?php if (hasPermission('activity_log_view')): ?>
                <div class="report-card">
                    <h3><i class="fas fa-history"></i> Aktivitätsprotokoll</h3>
                    <p>Detailliertes Log aller System-Aktivitäten</p>
                    <div class="report-actions">
                        <a href="export_activity_log.php?format=pdf" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="export_activity_log.php?format=excel" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Geräte-Auslastung -->
                <div class="report-card">
                    <h3><i class="fas fa-tachometer-alt"></i> Geräte-Auslastung</h3>
                    <p>Betriebsstunden und Nutzungsanalyse</p>
                    <div class="report-actions">
                        <a href="export_device_usage.php?format=pdf" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="export_device_usage.php?format=excel" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                    </div>
                </div>
                
                <!-- Einzelmarker-Report -->
                <div class="report-card">
                    <h3><i class="fas fa-info-circle"></i> Einzelmarker-Report</h3>
                    <p>Detaillierter Report für einen spezifischen Marker</p>
                    <div class="report-actions">
                        <a href="select_marker_report.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-right"></i> Marker wählen
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Custom Report Builder -->
            <div class="info-card" style="margin-top: 30px;">
                <h2><i class="fas fa-sliders-h"></i> Custom Report</h2>
                <p>Erstellen Sie einen individuellen Report mit benutzerdefinierten Filtern</p>
                
                <form action="export_custom_report.php" method="GET" style="margin-top: 20px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="report_type">Report-Typ</label>
                            <select id="report_type" name="type">
                                <option value="markers">Marker</option>
                                <option value="maintenance">Wartungen</option>
                                <option value="activity">Aktivitäten</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_from">Von Datum</label>
                            <input type="date" id="date_from" name="date_from">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">Bis Datum</label>
                            <input type="date" id="date_to" name="date_to">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="format">Export-Format</label>
                            <select id="format" name="format">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel (XLSX)</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Report erstellen
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>