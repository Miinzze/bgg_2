<?php
require_once 'config.php';
require_once 'functions.php';

// Alle Patchnotes laden
$stmt = $pdo->query("SELECT * FROM patchnotes WHERE is_active = 1 ORDER BY release_date DESC, id DESC");
$patchnotes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Patchnotes - RFID System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .patchnote-item {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .patchnote-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .version-badge {
            background: #007bff;
            color: white;
            padding: 5px 12px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 14px;
        }
        .patchnote-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .patchnote-date {
            color: #6c757d;
            font-size: 13px;
            margin-left: auto;
        }
        .patchnote-description {
            color: #495057;
            line-height: 1.6;
            white-space: pre-line;
        }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
        <?php include 'header.php'; ?>
    <?php endif; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-history"></i> Update-Verlauf</h1>
                <div class="header-actions">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <a href="patchnotes_admin.php" class="btn btn-secondary">
                                <i class="fas fa-cog"></i> Verwalten
                            </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Zurück
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Zum Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($patchnotes)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Noch keine Updates verfügbar
                </div>
            <?php else: ?>
                <?php foreach ($patchnotes as $note): ?>
                <div class="patchnote-item">
                    <div class="patchnote-header">
                        <span class="version-badge">v<?= htmlspecialchars($note['version']) ?></span>
                        <h2 class="patchnote-title"><?= htmlspecialchars($note['title']) ?></h2>
                        <span class="patchnote-date">
                            <i class="fas fa-calendar"></i>
                            <?= date('d.m.Y', strtotime($note['release_date'])) ?>
                        </span>
                    </div>
                    <div class="patchnote-description">
                        <?= htmlspecialchars($note['description']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>