<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('statistics_view');

trackUsage('export_usage_stats');

$format = $_GET['format'] ?? 'pdf';

// Statistiken berechnen
$stats = [
    'total_actions' => $pdo->query("SELECT COUNT(*) FROM usage_statistics")->fetchColumn(),
    'unique_users' => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM usage_statistics WHERE user_id IS NOT NULL")->fetchColumn(),
    'today_actions' => $pdo->query("SELECT COUNT(*) FROM usage_statistics WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'week_actions' => $pdo->query("SELECT COUNT(*) FROM usage_statistics WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
];

// Top Benutzer
$topUsers = $pdo->query("
    SELECT u.username, COUNT(*) as action_count
    FROM usage_statistics us
    JOIN users u ON us.user_id = u.id
    GROUP BY us.user_id
    ORDER BY action_count DESC
    LIMIT 10
")->fetchAll();

// Top Aktionen
$topActions = $pdo->query("
    SELECT action_type, COUNT(*) as count
    FROM usage_statistics
    GROUP BY action_type
    ORDER BY count DESC
    LIMIT 10
")->fetchAll();

// Aktivität pro Tag (letzte 30 Tage)
$dailyStats = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM usage_statistics
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
")->fetchAll();

if ($format === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=nutzungsstatistik_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Nutzungsstatistik - ' . date('d.m.Y H:i')], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Übersicht'], ';');
    fputcsv($output, ['Gesamt Aktionen', $stats['total_actions']], ';');
    fputcsv($output, ['Aktive Benutzer', $stats['unique_users']], ';');
    fputcsv($output, ['Heute', $stats['today_actions']], ';');
    fputcsv($output, ['Diese Woche', $stats['week_actions']], ';');
    fputcsv($output, [], ';');
    
    fputcsv($output, ['Top Benutzer'], ';');
    fputcsv($output, ['Benutzer', 'Aktionen'], ';');
    foreach ($topUsers as $user) {
        fputcsv($output, [$user['username'], $user['action_count']], ';');
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
            h1, h2 { color: #e63312; }
            h1 { border-bottom: 3px solid #e63312; padding-bottom: 10px; }
            .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
            .stat-box { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; }
            .stat-box h3 { margin: 0; font-size: 36px; color: #e63312; }
            .stat-box p { margin: 5px 0 0 0; color: #6c757d; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #e63312; color: white; padding: 10px; text-align: left; }
            td { border: 1px solid #ddd; padding: 8px; }
            tr:nth-child(even) { background: #f8f9fa; }
        </style>
    </head>
    <body>
        <h1>Nutzungsstatistik</h1>
        <div style="color: #6c757d; margin-bottom: 20px;">
            Erstellt am: <?= date('d.m.Y H:i') ?> Uhr
        </div>
        
        <div class="stats-grid">
            <div class="stat-box">
                <h3><?= $stats['total_actions'] ?></h3>
                <p>Gesamt Aktionen</p>
            </div>
            <div class="stat-box">
                <h3><?= $stats['unique_users'] ?></h3>
                <p>Aktive Benutzer</p>
            </div>
            <div class="stat-box">
                <h3><?= $stats['today_actions'] ?></h3>
                <p>Heute</p>
            </div>
            <div class="stat-box">
                <h3><?= $stats['week_actions'] ?></h3>
                <p>Diese Woche</p>
            </div>
        </div>
        
        <h2>Top 10 Benutzer</h2>
        <table>
            <thead>
                <tr>
                    <th>Rang</th>
                    <th>Benutzer</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topUsers as $index => $user): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= e($user['username']) ?></strong></td>
                    <td><?= $user['action_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Meistgenutzte Funktionen</h2>
        <table>
            <thead>
                <tr>
                    <th>Aktion</th>
                    <th>Anzahl</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topActions as $action): ?>
                <tr>
                    <td><?= e($action['action_type']) ?></td>
                    <td><?= $action['count'] ?></td>
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