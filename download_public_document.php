<?php
require_once 'config.php';
require_once 'functions.php';

// Kein Login erforderlich - öffentlicher Download
$docId = intval($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

if (empty($docId) || empty($token)) {
    die('Ungültige Parameter');
}

// Dokument laden und prüfen ob öffentlich
$stmt = $pdo->prepare("
    SELECT md.*, m.public_token
    FROM marker_documents md
    JOIN markers m ON md.marker_id = m.id
    WHERE md.id = ? AND md.is_public = 1 AND m.public_token = ? AND m.deleted_at IS NULL
");
$stmt->execute([$docId, $token]);
$document = $stmt->fetch();

if (!$document) {
    die('Dokument nicht gefunden oder nicht öffentlich zugänglich');
}

// Prüfen ob Datei existiert
if (!file_exists($document['document_path'])) {
    die('Datei nicht gefunden auf dem Server');
}

// Dateiname für Download
$filename = $document['document_name'];

// MIME-Type bestimmen
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $document['document_path']);
finfo_close($finfo);

// Sicherheitscheck: Nur PDFs erlauben
if ($mimeType !== 'application/pdf') {
    die('Nur PDF-Dokumente können heruntergeladen werden');
}

// Download-Header setzen
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($document['document_path']));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Datei ausgeben
readfile($document['document_path']);

// Download in Activity Log (optional)
try {
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, username, action, details, marker_id, ip_address)
        VALUES (NULL, 'Öffentlich', 'public_document_download', ?, ?, ?)
    ");
    $stmt->execute([
        'Dokument: ' . $filename,
        $document['marker_id'],
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
} catch (Exception $e) {
    // Fehler beim Logging nicht nach außen geben
}

exit;