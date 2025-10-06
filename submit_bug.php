<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

// CSRF validieren
validateCSRF();

// Eingaben validieren
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$pageUrl = trim($_POST['page_url'] ?? '');
$browserInfo = trim($_POST['browser_info'] ?? '');
$priority = $_POST['priority'] ?? 'mittel';

$errors = [];

if (empty($title) || strlen($title) < 5) {
    $errors[] = 'Titel muss mindestens 5 Zeichen lang sein';
}

if (empty($description) || strlen($description) < 10) {
    $errors[] = 'Beschreibung muss mindestens 10 Zeichen lang sein';
}

if (!validateEmail($email)) {
    $errors[] = 'Ungültige E-Mail-Adresse';
}

if (!in_array($priority, ['niedrig', 'mittel', 'hoch', 'kritisch'])) {
    $priority = 'mittel';
}

if (!empty($errors)) {
    die(json_encode(['success' => false, 'message' => implode(', ', $errors)]));
}

// Screenshot hochladen
$screenshotPath = null;
if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($_FILES['screenshot']['type'], $allowedTypes)) {
        die(json_encode(['success' => false, 'message' => 'Nur JPG, PNG oder GIF erlaubt']));
    }
    
    if ($_FILES['screenshot']['size'] > $maxSize) {
        die(json_encode(['success' => false, 'message' => 'Screenshot zu groß (max. 5MB)']));
    }
    
    // Upload-Verzeichnis erstellen
    $uploadDir = UPLOAD_DIR . 'bug_screenshots/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
    $filename = 'bug_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $filepath)) {
        chmod($filepath, 0644);
        $screenshotPath = $filepath;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO bug_reports (
            title, description, email, phone, page_url, 
            browser_info, screenshot_path, priority, reported_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $reportedBy = $_SESSION['user_id'] ?? null;
    
    $stmt->execute([
        $title,
        $description,
        $email,
        $phone,
        $pageUrl,
        $browserInfo,
        $screenshotPath,
        $priority,
        $reportedBy
    ]);
    
    $bugId = $pdo->lastInsertId();
    
    // E-Mail an Admin senden
    sendBugReportEmail($bugId, $title, $description, $email, $priority);
    
    // Aktivitätslog
    logActivity('bug_report', "Bug gemeldet: $title", null);
    
    echo json_encode([
        'success' => true,
        'message' => 'Bug erfolgreich gemeldet. Vielen Dank!',
        'bug_id' => $bugId
    ]);
    
} catch (PDOException $e) {
    error_log('Bug Report Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
}

function sendBugReportEmail($bugId, $title, $description, $reporterEmail, $priority) {
    global $pdo;
    
    $settings = getSystemSettings();
    $adminEmail = $settings['bug_report_email'] ?? '';
    
    if (empty($adminEmail)) {
        return false;
    }
    
    $priorityLabels = [
        'niedrig' => '🟢 Niedrig',
        'mittel' => '🟡 Mittel',
        'hoch' => '🟠 Hoch',
        'kritisch' => '🔴 Kritisch'
    ];
    
    $subject = "[Bug #$bugId] $title";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #e63312; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .field { margin: 15px 0; }
            .label { font-weight: bold; color: #2c3e50; }
            .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🐛 Neuer Bug-Report</h2>
            </div>
            <div class='content'>
                <div class='field'>
                    <span class='label'>Bug-ID:</span> #$bugId
                </div>
                <div class='field'>
                    <span class='label'>Titel:</span> $title
                </div>
                <div class='field'>
                    <span class='label'>Priorität:</span> " . ($priorityLabels[$priority] ?? $priority) . "
                </div>
                <div class='field'>
                    <span class='label'>Beschreibung:</span><br>
                    " . nl2br(htmlspecialchars($description)) . "
                </div>
                <div class='field'>
                    <span class='label'>Gemeldet von:</span> $reporterEmail
                </div>
                <a href='" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/bug-admin/view_bug.php?id=$bugId' class='btn'>
                    Bug ansehen
                </a>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: RFID Bug System <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
    
    return mail($adminEmail, $subject, $message, $headers);
}