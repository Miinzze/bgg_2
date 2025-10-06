<?php
session_start();
require_once '../config.php';
require_once 'functions.php';

requireBugAdmin();

$bugId = intval($_GET['id'] ?? 0);

if (!$bugId) {
    header('Location: index.php');
    exit;
}

$bug = getBugById($bugId, $pdo);

if (!$bug) {
    die('Bug nicht gefunden');
}

// Status ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $newStatus = $_POST['status'] ?? '';
    if (updateBugStatus($bugId, $newStatus, $pdo)) {
        $_SESSION['success_message'] = 'Status erfolgreich geändert';
        header('Location: view_bug.php?id=' . $bugId);
        exit;
    }
}

// Kommentar hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment'] ?? '');
    $isInternal = isset($_POST['is_internal']);
    
    if (!empty($comment)) {
        if (addBugComment($bugId, $_SESSION['bug_admin_id'], $comment, $isInternal, $pdo)) {
            $_SESSION['success_message'] = 'Kommentar hinzugefügt';
            header('Location: view_bug.php?id=' . $bugId);
            exit;
        }
    }
}

// Bug zuweisen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_bug'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    if (assignBug($bugId, $userId, $pdo)) {
        $_SESSION['success_message'] = 'Bug zugewiesen';
        header('Location: view_bug.php?id=' . $bugId);
        exit;
    }
}

$comments = getBugComments($bugId, $pdo);
$allAdmins = getAllBugAdmins($pdo);

$pageTitle = 'Bug #' . $bugId;
include 'header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>Bug #<?= $bug['id'] ?>: <?= htmlspecialchars($bug['title']) ?></h1>
        <h2>
            <span class="badge badge-<?= getPriorityBadgeClass($bug['priority']) ?>">
                <?= ucfirst($bug['priority']) ?>
            </span>
            <span class="badge badge-<?= getStatusBadgeClass($bug['status']) ?>">
                <?= ucfirst(str_replace('_', ' ', $bug['status'])) ?>
            </span>
        </h2>
    </div>
    <div class="header-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Zurück
        </a>
    </div>
</div>

<div class="admin-grid" style="grid-template-columns: 2fr 1fr;">
    <!-- Hauptbereich -->
    <div>
        <!-- Bug-Details -->
        <div class="admin-section" style="margin-bottom: 20px;">
            <h2>Details</h2>
            
            <div class="info-grid" style="grid-template-columns: 1fr;">
                <div class="info-item">
                    <span class="label">Beschreibung</span>
                    <div class="value" style="white-space: pre-wrap; background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 10px;">
                        <?= htmlspecialchars($bug['description']) ?>
                    </div>
                </div>
                
                <?php if ($bug['page_url']): ?>
                <div class="info-item">
                    <span class="label">Seite</span>
                    <div class="value">
                        <a href="<?= htmlspecialchars($bug['page_url']) ?>" target="_blank">
                            <?= htmlspecialchars($bug['page_url']) ?>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($bug['browser_info']): ?>
                <div class="info-item">
                    <span class="label">Browser</span>
                    <div class="value" style="font-size: 12px; color: #6c757d;">
                        <?= htmlspecialchars($bug['browser_info']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($bug['screenshot_path'] && file_exists('../' . $bug['screenshot_path'])): ?>
                <div class="info-item">
                    <span class="label">Screenshot</span>
                    <div class="value">
                        <a href="../<?= htmlspecialchars($bug['screenshot_path']) ?>" target="_blank">
                            <img src="../<?= htmlspecialchars($bug['screenshot_path']) ?>" 
                                 alt="Screenshot" 
                                 style="max-width: 100%; border-radius: 8px; border: 2px solid #dee2e6; margin-top: 10px;">
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Kommentare -->
        <div class="admin-section">
            <h2>Kommentare & Verlauf</h2>
            
            <form method="POST" style="margin-bottom: 30px;">
                <div class="form-group">
                    <label for="comment">Neuer Kommentar</label>
                    <textarea id="comment" name="comment" rows="4" required placeholder="Kommentar eingeben..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_internal" value="1">
                        <span class="checkbox-text">
                            <i class="fas fa-lock"></i> Interner Kommentar (nur für Admins sichtbar)
                        </span>
                    </label>
                </div>
                
                <button type="submit" name="add_comment" class="btn btn-primary">
                    <i class="fas fa-comment"></i> Kommentar hinzufügen
                </button>
            </form>
            
            <?php if (empty($comments)): ?>
                <p class="text-muted">Noch keine Kommentare vorhanden.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($comments as $comment): ?>
                        <div style="background: <?= $comment['is_internal'] ? '#fff3cd' : '#f8f9fa' ?>; padding: 15px; border-radius: 8px; border-left: 4px solid <?= $comment['is_internal'] ? '#ffc107' : '#007bff' ?>;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <strong>
                                    <?= htmlspecialchars($comment['full_name'] ?? $comment['username']) ?>
                                    <?php if ($comment['is_internal']): ?>
                                        <span class="badge badge-warning" style="margin-left: 8px;">
                                            <i class="fas fa-lock"></i> Intern
                                        </span>
                                    <?php endif; ?>
                                </strong>
                                <small class="text-muted">
                                    <?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?>
                                </small>
                            </div>
                            <div style="white-space: pre-wrap;">
                                <?= htmlspecialchars($comment['comment']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div>
        <!-- Kontaktinformationen -->
        <div class="admin-section" style="margin-bottom: 20px;">
            <h2>Kontakt</h2>
            
            <div class="info-item" style="margin-bottom: 15px;">
                <span class="label">E-Mail</span>
                <div class="value">
                    <a href="mailto:<?= htmlspecialchars($bug['email']) ?>">
                        <?= htmlspecialchars($bug['email']) ?>
                    </a>
                </div>
            </div>
            
            <?php if ($bug['phone']): ?>
            <div class="info-item" style="margin-bottom: 15px;">
                <span class="label">Telefon</span>
                <div class="value">
                    <a href="tel:<?= htmlspecialchars($bug['phone']) ?>">
                        <?= htmlspecialchars($bug['phone']) ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($bug['reporter_username']): ?>
            <div class="info-item">
                <span class="label">System-Benutzer</span>
                <div class="value">
                    <?= htmlspecialchars($bug['reporter_username']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Status ändern -->
        <div class="admin-section" style="margin-bottom: 20px;">
            <h2>Status ändern</h2>
            
            <form method="POST">
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="offen" <?= $bug['status'] === 'offen' ? 'selected' : '' ?>>Offen</option>
                        <option value="in_bearbeitung" <?= $bug['status'] === 'in_bearbeitung' ? 'selected' : '' ?>>In Bearbeitung</option>
                        <option value="erledigt" <?= $bug['status'] === 'erledigt' ? 'selected' : '' ?>>Erledigt (archiviert)</option>
                    </select>
                </div>
                
                <button type="submit" name="change_status" class="btn btn-primary btn-block">
                    <i class="fas fa-sync"></i> Status aktualisieren
                </button>
            </form>
        </div>
        
        <!-- Bug zuweisen -->
        <div class="admin-section" style="margin-bottom: 20px;">
            <h2>Zuweisen</h2>
            
            <form method="POST">
                <div class="form-group">
                    <select name="user_id" class="form-control">
                        <option value="">Nicht zugewiesen</option>
                        <?php foreach ($allAdmins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= $bug['assigned_to'] == $admin['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" name="assign_bug" class="btn btn-primary btn-block">
                    <i class="fas fa-user-check"></i> Zuweisen
                </button>
            </form>
        </div>
        
        <!-- Meta-Informationen -->
        <div class="admin-section">
            <h2>Informationen</h2>
            
            <div class="info-item" style="margin-bottom: 10px;">
                <span class="label">Erstellt am</span>
                <div class="value">
                    <?= date('d.m.Y H:i', strtotime($bug['created_at'])) ?>
                </div>
            </div>
            
            <div class="info-item" style="margin-bottom: 10px;">
                <span class="label">Zuletzt aktualisiert</span>
                <div class="value">
                    <?= date('d.m.Y H:i', strtotime($bug['updated_at'])) ?>
                </div>
            </div>
            
            <?php if ($bug['archived_at']): ?>
            <div class="info-item">
                <span class="label">Archiviert am</span>
                <div class="value">
                    <?= date('d.m.Y H:i', strtotime($bug['archived_at'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>