<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('reports_generate');

trackUsage('export_maintenance');

$format = $_GET['format'] ?? 'pdf';

// Wartungen laden
$stmt = $pdo->query("
    SELECT mh.*, m.name as marker_name, m.category, u.username as performed_by_name
    FROM maintenance_history mh
    JOIN markers m ON mh.marker_id = m.id
    LEFT JOIN users u ON mh.performed_by = u.id
    ORDER BY mh.maintenance_date DESC
");
$maintenances = $stmt->fetchAll();

if ($format === 'excel' || $format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=wartungen_export_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'ID',
        'Datum',
        'Marker',
        'Kategorie',
        'Beschreibung',
        'Durchgeführt von'
    ], ';');
    
    foreach ($maintenances as $maint) {
        fputcsv($output, [
            $maint['id'],
            date('d.m.Y', strtotime($maint['maintenance_date'])),
            $maint['marker_name'],
            $maint['category'],
            $maint['description'],
            $maint['performed_by_name']
        ], ';');
    }
    
    fclose($output);
    exit;
    
} else {
    // PDF/HTML
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #e63312; border-bottom: 3px solid #e63312; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 12px; }
            th { background: #e63312; color: white; padding: 10px; text-align: left; }
            td { border: 1px solid #ddd; padding: 8px; }
            tr:nth-child(even) { background: #f8f9fa; }
        </style>
    </head>
    <body>
        <h1>Wartungs-Historie</h1>
        <div style="color: #6c757d; margin-bottom: 20px;">
            Erstellt am: <?= date('d.m.Y H:i') ?> Uhr | 
            Anzahl Wartungen: <?= count($maintenances) ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Marker</th>
                    <th>Kategorie</th>
                    <th>Beschreibung</th>
                    <th>Durchgeführt von</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maintenances as $maint): ?>
                <tr>
                    <td><?= date('d.m.Y', strtotime($maint['maintenance_date'])) ?></td>
                    <td><strong><?= e($maint['marker_name']) ?></strong></td>
                    <td><?= e($maint['category']) ?></td>
                    <td><?= e($maint['description']) ?></td>
                    <td><?= e($maint['performed_by_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    echo ob_get_clean();
}
?>