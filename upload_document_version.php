<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('documents_upload');

$markerId = $_GET['marker'] ?? 0;
$marker = getMarkerById($markerId, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

$message = '';
$messageType = '';

// Dokument hochladen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_version'])) {
    validateCSRF();
    
    $documentType = $_POST['document_type'] ?? 'other';
    $notes = $_POST['notes'] ?? '';
    $replaceVersion = isset($_POST['replace_version']) ? intval($_POST['replace_version']) : null;
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $result = uploadPDF($_FILES['document'], $markerId);
        
        if ($result['success']) {
            if ($replaceVersion) {
                $stmt = $pdo->prepare("UPDATE document_versions SET is_current = 0 WHERE id = ?");
                $stmt->execute([$replaceVersion]);
            }
            
            $stmt = $pdo->prepare("
                SELECT MAX(version_number) as max_version 
                FROM document_versions 
                WHERE marker_id = ? AND document_type = ?
            ");
            $stmt->execute([$markerId, $documentType]);
            $maxVersion = $stmt->fetchColumn() ?: 0;
            $newVersion = $maxVersion + 1;
            
            $stmt = $pdo->prepare("
                INSERT INTO document_versions 
                (marker_id, document_type, filename, original_filename, file_path, file_size, 
                 version_number, uploaded_by, notes, replaced_version_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $markerId, $documentType, basename($result['path']), $_FILES['document']['name'],
                $result['path'], $result['size'], $newVersion, $_SESSION['user_id'],
                $notes, $replaceVersion
            ]);
            
            logActivity('document_version_uploaded', "Dokument-Version {$newVersion} hochgeladen", $markerId);
            
            $message = 'Dokument-Version erfolgreich hochgeladen!';
            $messageType = 'success';
            
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    } else {
        $message = 'Keine Datei ausgewählt';
        $messageType = 'danger';
    }
}

// Version wiederherstellen
if (isset($_GET['restore'])) {
    $versionId = intval($_GET['restore']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE document_versions 
            SET is_current = 0 
            WHERE marker_id = ? AND document_type = (
                SELECT document_type FROM document_versions WHERE id = ?
            )
        ");
        $stmt->execute([$markerId, $versionId]);
        
        $stmt = $pdo->prepare("UPDATE document_versions SET is_current = 1 WHERE id = ?");
        $stmt->execute([$versionId]);
        
        logActivity('document_version_restored', "Version wiederhergestellt", $markerId);
        
        $message = 'Version erfolgreich wiederhergestellt!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Fehler: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Alle Versionen laden
$stmt = $pdo->prepare("
    SELECT dv.*, u.username as uploaded_by_name
    FROM document_versions dv
    LEFT JOIN users u ON dv.uploaded_by = u.id
    WHERE dv.marker_id = ?
    ORDER BY dv.document_type, dv.version_number DESC
");
$stmt->execute([$markerId]);
$allVersions = $stmt->fetchAll();

$versionsByType = [];
foreach ($allVersions as $version) {
    $versionsByType[$version['document_type']][] = $version;
}

$documentTypes = [
    'manual' => 'Bedienungsanleitung',
    'certificate' => 'Zertifikate',
    'inspection' => 'Prüfberichte',
    'other' => 'Sonstige'
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokument-Versionen - <?= e($marker['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .version-timeline {
            position: relative;
            padding-left: 40px;
            margin-top: 20px;
        }
        
        .version-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }
        
        .version-item {
            position: relative;
            margin-bottom: 20px;
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
        }
        
        .version-item.current {
            border-color: var(--success-color);
            background: #d4edda;
        }
        
        .version-item::before {
            content: '';
            position: absolute;
            left: -33px;
            top: 20px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--border-color);
            border: 3px solid var(--card-bg);
        }
        
        .version-item.current::before {
            background: var(--success-color);
        }
        
        .version-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .version-number {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
        }
        
        .version-item.current .version-number {
            color: var(--success-color);
        }
        
        .version-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .doc-type-section {
            margin-bottom: 30px;
        }
        
        .doc-type-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-file-archive"></i> Dokument-Versionen</h1>
                <div class="header-actions">
                    <a href="view_marker.php?id=<?= $markerId ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong><i class="fas fa-box"></i> Marker:</strong> <?= e($marker['name']) ?>
                <?php if ($marker['serial_number']): ?>
                    | <strong><i class="fas fa-barcode"></i> SN:</strong> <?= e($marker['serial_number']) ?>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>
            
            <!-- Upload-Formular -->
            <div class="marker-form">
                <h2><i class="fas fa-upload"></i> Neue Version hochladen</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="upload_version" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="document_type">Dokumenttyp *</label>
                            <select id="document_type" name="document_type" required>
                                <option value="">-- Bitte wählen --</option>
                                <?php foreach ($documentTypes as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="document">PDF-Dokument *</label>
                            <input type="file" id="document" name="document" accept=".pdf" required>
                            <small>Nur PDF-Dateien, max. 10 MB</small>
                        </div>
                    </div>
                    
                    <div class="form-group" id="replace_group" style="display: none;">
                        <label class="checkbox-label">
                            <input type="checkbox" id="replace_checkbox" onchange="toggleReplace()">
                            <span class="checkbox-text">Bestehende Version ersetzen</span>
                        </label>
                        <select id="replace_version" name="replace_version" disabled style="margin-top: 10px;">
                            <option value="">-- Version wählen --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notizen / Änderungen</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="z.B. Aktualisierte Wartungsanleitung Kapitel 3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Version hochladen
                    </button>
                </form>
            </div>
            
            <!-- Versionen nach Typ gruppiert -->
            <?php if (empty($versionsByType)): ?>
                <div class="info-card">
                    <p style="text-align: center; color: var(--medium-gray); padding: 40px;">
                        <i class="fas fa-folder-open" style="font-size: 48px; opacity: 0.3;"></i><br>
                        Noch keine Dokument-Versionen vorhanden
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($versionsByType as $type => $versions): ?>
                    <div class="doc-type-section">
                        <div class="doc-type-header">
                            <i class="fas fa-folder"></i>
                            <h2 style="margin: 0;"><?= $documentTypes[$type] ?? $type ?></h2>
                            <span class="badge" style="background: white; color: #667eea; margin-left: auto;">
                                <?= count($versions) ?> Version<?= count($versions) > 1 ? 'en' : '' ?>
                            </span>
                        </div>
                        
                        <div class="version-timeline">
                            <?php foreach ($versions as $version): ?>
                                <div class="version-item <?= $version['is_current'] ? 'current' : '' ?>">
                                    <div class="version-header">
                                        <div>
                                            <div class="version-number">
                                                Version <?= $version['version_number'] ?>
                                                <?php if ($version['is_current']): ?>
                                                    <span class="badge badge-success">Aktuell</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 14px; color: var(--medium-gray); margin-top: 5px;">
                                                <i class="fas fa-file-pdf"></i> <?= e($version['original_filename']) ?>
                                                <span style="margin-left: 15px;">
                                                    <?= number_format($version['file_size'] / 1024 / 1024, 2) ?> MB
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="version-actions">
                                            <a href="<?= e($version['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary" title="Öffnen">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= e($version['file_path']) ?>" download class="btn btn-sm btn-secondary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if (!$version['is_current']): ?>
                                                <a href="?marker=<?= $markerId ?>&restore=<?= $version['id'] ?>" 
                                                   class="btn btn-sm btn-success"
                                                   onclick="return confirm('Diese Version wiederherstellen?')"
                                                   title="Wiederherstellen">
                                                    <i class="fas fa-undo"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="font-size: 13px; color: var(--medium-gray); margin-top: 10px;">
                                        <i class="fas fa-user"></i> <?= e($version['uploaded_by_name']) ?>
                                        <span style="margin-left: 15px;">
                                            <i class="fas fa-clock"></i> <?= date('d.m.Y H:i', strtotime($version['uploaded_at'])) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($version['notes']): ?>
                                        <div style="margin-top: 10px; padding: 10px; background: rgba(0,123,255,0.1); border-radius: 4px;">
                                            <strong style="font-size: 12px; color: var(--medium-gray);">Änderungsnotizen:</strong>
                                            <p style="margin: 5px 0 0 0; font-size: 13px; white-space: pre-line; color: var(--text-color);">
                                                <?= e($version['notes']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($version['replaced_version_id']): ?>
                                        <div style="margin-top: 8px; font-size: 12px; color: var(--medium-gray);">
                                            <i class="fas fa-history"></i> Ersetzt Version #<?= $version['replaced_version_id'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    document.getElementById('document_type').addEventListener('change', function() {
        const type = this.value;
        const replaceGroup = document.getElementById('replace_group');
        const replaceSelect = document.getElementById('replace_version');
        
        if (!type) {
            replaceGroup.style.display = 'none';
            return;
        }
        
        const versions = <?= json_encode($versionsByType) ?>;
        
        if (versions[type] && versions[type].length > 0) {
            replaceGroup.style.display = 'block';
            
            replaceSelect.innerHTML = '<option value="">-- Version wählen --</option>';
            versions[type].forEach(v => {
                replaceSelect.innerHTML += `<option value="${v.id}">Version ${v.version_number} - ${v.original_filename}</option>`;
            });
        } else {
            replaceGroup.style.display = 'none';
        }
    });
    
    function toggleReplace() {
        const checkbox = document.getElementById('replace_checkbox');
        const select = document.getElementById('replace_version');
        select.disabled = !checkbox.checked;
        
        if (!checkbox.checked) {
            select.value = '';
        }
    }
    </script>
</body>
</html>