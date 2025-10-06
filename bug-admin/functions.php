<?php
// Bug-Admin Funktionen

function requireBugAdmin() {
    if (!isset($_SESSION['bug_admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function getBugAdminInfo($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM bug_admin_users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getAllBugs($pdo, $includeArchived = false) {
    $sql = "SELECT br.*, u.username as reporter_username, ba.username as assigned_username
            FROM bug_reports br
            LEFT JOIN users u ON br.reported_by = u.id
            LEFT JOIN bug_admin_users ba ON br.assigned_to = ba.id
            WHERE 1=1";
    
    if (!$includeArchived) {
        $sql .= " AND br.archived_at IS NULL";
    }
    
    $sql .= " ORDER BY 
              CASE br.status 
                  WHEN 'offen' THEN 1 
                  WHEN 'in_bearbeitung' THEN 2 
                  WHEN 'erledigt' THEN 3 
              END,
              CASE br.priority 
                  WHEN 'kritisch' THEN 1 
                  WHEN 'hoch' THEN 2 
                  WHEN 'mittel' THEN 3 
                  WHEN 'niedrig' THEN 4 
              END,
              br.created_at DESC";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getBugById($bugId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT br.*, u.username as reporter_username, ba.username as assigned_username
        FROM bug_reports br
        LEFT JOIN users u ON br.reported_by = u.id
        LEFT JOIN bug_admin_users ba ON br.assigned_to = ba.id
        WHERE br.id = ?
    ");
    $stmt->execute([$bugId]);
    return $stmt->fetch();
}

function getBugComments($bugId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT bc.*, ba.username, ba.full_name
        FROM bug_comments bc
        JOIN bug_admin_users ba ON bc.user_id = ba.id
        WHERE bc.bug_id = ?
        ORDER BY bc.created_at ASC
    ");
    $stmt->execute([$bugId]);
    return $stmt->fetchAll();
}

function updateBugStatus($bugId, $status, $pdo) {
    $allowedStatuses = ['offen', 'in_bearbeitung', 'erledigt'];
    
    if (!in_array($status, $allowedStatuses)) {
        return false;
    }
    
    // Bei "erledigt" archivieren
    if ($status === 'erledigt') {
        $stmt = $pdo->prepare("
            UPDATE bug_reports 
            SET status = ?, archived_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            UPDATE bug_reports 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
    }
    
    return $stmt->execute([$status, $bugId]);
}

function addBugComment($bugId, $userId, $comment, $isInternal, $pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO bug_comments (bug_id, user_id, comment, is_internal)
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$bugId, $userId, $comment, $isInternal ? 1 : 0]);
}

function assignBug($bugId, $userId, $pdo) {
    $stmt = $pdo->prepare("UPDATE bug_reports SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$userId, $bugId]);
}

function getBugStats($pdo) {
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bug_reports WHERE status = 'offen' AND archived_at IS NULL");
    $stats['offen'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bug_reports WHERE status = 'in_bearbeitung' AND archived_at IS NULL");
    $stats['in_bearbeitung'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bug_reports WHERE archived_at IS NOT NULL");
    $stats['archiviert'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bug_reports WHERE priority = 'kritisch' AND status != 'erledigt'");
    $stats['kritisch'] = $stmt->fetchColumn();
    
    return $stats;
}

function getStatusBadgeClass($status) {
    $classes = [
        'offen' => 'danger',
        'in_bearbeitung' => 'warning',
        'erledigt' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getPriorityBadgeClass($priority) {
    $classes = [
        'niedrig' => 'secondary',
        'mittel' => 'info',
        'hoch' => 'warning',
        'kritisch' => 'danger'
    ];
    return $classes[$priority] ?? 'secondary';
}

function getAllBugAdmins($pdo) {
    $stmt = $pdo->query("SELECT * FROM bug_admin_users WHERE is_active = 1 ORDER BY username");
    return $stmt->fetchAll();
}
?>