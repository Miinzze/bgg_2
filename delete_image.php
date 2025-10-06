<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

$id = $_GET['id'] ?? 0;
$markerId = $_GET['marker'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM marker_images WHERE id = ?");
$stmt->execute([$id]);
$image = $stmt->fetch();

if ($image) {
    // Datei löschen
    if (file_exists($image['image_path'])) {
        unlink($image['image_path']);
    }
    
    // Eintrag aus Datenbank löschen
    $stmt = $pdo->prepare("DELETE FROM marker_images WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: edit_marker.php?id=$markerId");
exit;
?>