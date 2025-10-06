<?php
session_start();
require_once '../config.php';
require_once 'functions.php';

requireBugAdmin();

$includeArchived = isset($_GET['archived']);
$bugs = getAllBugs($pdo, $includeArchived);
$stats = getBugStats($pdo);

$pageTitle = $includeArchived ? 'Bug-Archiv' : 'Bug-Übersicht';
include 'header.php';
?>

<div class="page-header">
    <div>
        <h1><?= $includeArchived ? 'Bug-Archiv' : 'Bug-Übersicht' ?></h1>
        <h2>Verwaltung aller gemeldeten Bugs</h2>
    </div>
    <div class="header-actions">
        <?php if ($includeArchived): ?>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> Aktive Bugs
            </a>
        <?php else: ?>
            <a href="index.php?archived=1" class="btn btn-secondary">
                <i class="fas fa-archive"></i> Archiv anzeigen
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiken -->
<?php if (!$includeArchived): ?>
<div class="stats-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card danger">
        <div class="stat-label">
            <i class="fas fa-exclamation-circle"></i> Offen
        </div>
        <div class="stat-value"><?= $stats['offen'] ?></div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-label">
            <i class="fas fa-cog"></i> In Bearbeitung
        </div>
        <div class="stat-value"><?= $stats['in_bearbeitung'] ?></div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-label">
            <i class="fas fa-archive"></i> Archiviert
        </div>
        <div class="stat-value"><?= $stats['archiviert'] ?></div>
    </div>
    
    <div class="stat-card <?= $stats['kritisch'] > 0 ? 'danger' : 'success' ?>">
        <div class="stat-label">
            <i class="fas fa-fire"></i> Kritisch
        </div>
        <div class="stat-value"><?= $stats['kritisch'] ?></div>
    </div>
</div>
<?php endif; ?>

<!-- Bug-Liste -->
<div class="admin-section">
    <h2>
        <?= $includeArchived ? 'Archivierte Bugs' : 'Aktive Bugs' ?>
        <span class="badge badge-secondary"><?= count($bugs) ?></span>
    </h2>
    
    <?php if (empty($bugs)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <?= $includeArchived ? 'Keine archivierten Bugs vorhanden.' : 'Keine aktiven Bugs vorhanden. Super!' ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titel</th>
                        <th>Priorität</th>
                        <th>Status</th>
                        <th>Gemeldet von</th>
                        <th>Zugewiesen an</th>
                        <th>Erstellt am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bugs as $bug): ?>
                        <tr>
                            <td>#<?= $bug['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($bug['title']) ?></strong>
                                <?php if ($bug['screenshot_path']): ?>
                                    <i class="fas fa-image" title="Hat Screenshot"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= getPriorityBadgeClass($bug['priority']) ?>">
                                    <?= ucfirst($bug['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= getStatusBadgeClass($bug['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $bug['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($bug['email']) ?>
                                <?php if ($bug['reporter_username']): ?>
                                    <br><small>(<?= htmlspecialchars($bug['reporter_username']) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $bug['assigned_username'] ? htmlspecialchars($bug['assigned_username']) : '-' ?>
                            </td>
                            <td><?= date('d.m.Y H:i', strtotime($bug['created_at'])) ?></td>
                            <td>
                                <a href="view_bug.php?id=<?= $bug['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>