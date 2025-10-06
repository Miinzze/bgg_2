<?php
// Bug-Admin Header - muss nach session_start() und require_once inkludiert werden
requireBugAdmin();
$currentUser = getBugAdminInfo($_SESSION['bug_admin_id'], $pdo);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Bug-Verwaltung' ?> - RFID System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dark-mode.css">
    <link rel="stylesheet" href="../css/mobile-features.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <a href="index.php">
                    <h1><i class="fas fa-bug"></i> Bug-Verwaltung</h1>
                </a>
            </div>
            
            <nav class="main-nav">
                <ul>
                    <li>
                        <a href="index.php">
                            <i class="fas fa-list"></i> Aktive Bugs
                        </a>
                    </li>
                    <li>
                        <a href="index.php?archived=1">
                            <i class="fas fa-archive"></i> Archiv
                        </a>
                    </li>
                    <li>
                        <a href="manage_users.php">
                            <i class="fas fa-users"></i> Benutzer
                        </a>
                    </li>
                        <a href="patchnotes_admin.php" class="btn btn-secondary">
                            <i class="fas fa-file-alt"></i> Patchnotes Erstellen
                        </a>
                    <li>
                        <a href="../index.php" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Hauptseite
                        </a>
                    </li>
                </ul>
            </nav>
            
            <button class="header-dark-toggle" onclick="toggleDarkModeQuick()" title="Dark Mode (Strg+Shift+D)">
                <i class="fas fa-moon"></i>
            </button>
            
            <div class="user-menu">
                <div class="user-info">
                    <span class="username">
                        <i class="fas fa-user-shield"></i> <?= htmlspecialchars($currentUser['username']) ?>
                    </span>
                    <small><?= htmlspecialchars($currentUser['email']) ?></small>
                </div>
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Abmelden
                </a>
            </div>
        </div>
    </header>
    
    <div class="main-container">
        <div class="content-wrapper">
            <!-- Content wird hier von den einzelnen Seiten eingefügt -->