<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('documents_delete');

$id = $_GET['id'] ?? 0;
$markerId = $_GET['marker'] ?? 0;
$redirect = $_GET['redirect'] ?? 'view';

$stmt = $pdo->prepare("SELECT * FROM marker_documents WHERE id = ?");
$stmt->execute([$id]);
$document = $stmt->fetch();

if ($document) {
    // Datei löschen
    if (file_exists($document['document_path'])) {
        unlink($document['document_path']);
    }
    
    // Eintrag aus Datenbank löschen
    $stmt = $pdo->prepare("DELETE FROM marker_documents WHERE id = ?");
    $stmt->execute([$id]);
    
    logActivity('document_deleted', "Dokument '{$document['document_name']}' gelöscht", $markerId);
}

// Redirect zurück zur richtigen Seite
if ($redirect === 'edit') {
    header("Location: edit_marker.php?id=$markerId");
} else {
    header("Location: view_marker.php?id=$markerId");
}
exit;