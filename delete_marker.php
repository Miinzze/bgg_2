<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('markers_delete');

$id = $_GET['id'] ?? 0;
$marker = getMarkerById($id, $pdo);

if (!$marker) {
    die('Marker nicht gefunden');
}

// Prüfen ob bereits gelöscht
if ($marker['deleted_at']) {
    header('Location: trash.php?already_deleted=1');
    exit;
}

// Soft Delete - Marker in Papierkorb verschieben
try {
    $stmt = $pdo->prepare("UPDATE markers SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $id]);
    
    logActivity('marker_deleted_soft', "Marker '{$marker['name']}' in Papierkorb verschoben", $id);
    
    header('Location: index.php?deleted=1');
    exit;
    
} catch (Exception $e) {
    header('Location: view_marker.php?id=' . $id . '&error=' . urlencode($e->getMessage()));
    exit;
}