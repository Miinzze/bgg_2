<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

$message = '';
$messageType = '';

// Patchnote erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_patchnote'])) {
    $version = trim($_POST['version'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $releaseDate = $_POST['release_date'] ?? date('Y-m-d');
    
    if (empty($version) || empty($title) || empty($description)) {
        $message = 'Bitte alle Felder ausfüllen';
        $messageType = 'danger';
    } else {
        $stmt = $pdo->prepare("INSERT INTO patchnotes (version, title, description, release_date) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$version, $title, $description, $releaseDate])) {
            $message = 'Patchnote erstellt!';
            $messageType = 'success';
        }
    }
}

// Patchnote löschen
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM patchnotes WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = 'Patchnote gelöscht';
    $messageType = 'success';
}

// Patchnote deaktivieren/aktivieren
if (isset($_GET['toggle'])) {
    $stmt = $pdo->prepare("UPDATE patchnotes SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_GET['toggle']]);
    $message = 'Status geändert';
    $messageType = 'success';
}

$stmt = $pdo->query("SELECT * FROM patchnotes ORDER BY release_date DESC, id DESC");
$patchnotes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Patchnotes verwalten</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-file-alt"></i> Patchnotes verwalten</h1>
                <div class="header-actions">
                    <a href="settings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                    <a href="patchnotes.php" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> Ansehen
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <div class="admin-grid">
                <div class="admin-section">
                    <h2>Neue Patchnote</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="form-group">
                            <label for="version">Version *</label>
                            <input type="text" id="version" name="version" placeholder="1.0.0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Titel *</label>
                            <input type="text" id="title" name="title" placeholder="Neue Features" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Beschreibung *</label>
                            <textarea id="description" name="description" rows="6" required 
                                      placeholder="- Feature 1&#10;- Feature 2&#10;- Bugfix 3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="release_date">Release-Datum</label>
                            <input type="date" id="release_date" name="release_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <button type="submit" name="create_patchnote" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Erstellen
                        </button>
                    </form>
                </div>
                
                <div class="admin-section">
                    <h2>Vorhandene Patchnotes (<?= count($patchnotes) ?>)</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Version</th>
                                    <th>Titel</th>
                                    <th>Datum</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patchnotes as $note): ?>
                                <tr>
                                    <td><strong>v<?= htmlspecialchars($note['version']) ?></strong></td>
                                    <td><?= htmlspecialchars($note['title']) ?></td>
                                    <td><?= date('d.m.Y', strtotime($note['release_date'])) ?></td>
                                    <td>
                                        <?php if ($note['is_active']): ?>
                                            <span class="badge badge-success">Aktiv</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inaktiv</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?toggle=<?= $note['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                        <a href="?delete=<?= $note['id'] ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Wirklich löschen?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>