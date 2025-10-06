<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
requirePermission('comments_delete');

$id = $_GET['id'] ?? 0;
$markerId = $_GET['marker'] ?? 0;

$stmt = $pdo->prepare("DELETE FROM marker_comments WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);

logActivity('comment_deleted', "Kommentar gelöscht", $markerId);

header("Location: view_marker.php?id=$markerId");
exit;