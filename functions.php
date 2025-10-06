<?php
// functions.php - Hilfsfunktionen

// Prüfen ob mobiles Gerät (Smartphone oder Tablet)
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Liste mobiler User-Agent-Patterns
    $mobilePatterns = [
        '/Android/i',
        '/webOS/i',
        '/iPhone/i',
        '/iPad/i',
        '/iPod/i',
        '/BlackBerry/i',
        '/Windows Phone/i',
        '/Mobile/i',
        '/Tablet/i'
    ];
    
    foreach ($mobilePatterns as $pattern) {
        if (preg_match($pattern, $userAgent)) {
            return true;
        }
    }
    
    return false;
}

// Prüfen ob Tablet (für spezifischere Unterscheidung wenn nötig)
function isTablet() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $userAgent);
}

// Prüfen ob Smartphone
function isSmartphone() {
    return isMobileDevice() && !isTablet();
}

// Prüfen ob Benutzer eingeloggt ist
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Prüfen ob Benutzer bestimmte Rolle hat
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Prüfen ob Benutzer Admin ist
function isAdmin() {
    return hasRole(ROLE_ADMIN);
}

// Prüfen ob Benutzer eine bestimmte Berechtigung hat
function hasPermission($permissionKey) {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admin hat immer alle Rechte
    if (isAdmin()) {
        return true;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users u
        JOIN role_permissions rp ON u.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE u.id = ? AND p.permission_key = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $permissionKey]);
    
    return $stmt->fetchColumn() > 0;
}

// Alle Berechtigungen eines Benutzers abrufen
function getUserPermissions($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.permission_key, p.display_name, p.category
        FROM users u
        JOIN role_permissions rp ON u.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE u.id = ?
        ORDER BY p.category, p.display_name
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetchAll();
}

// Berechtigung erforderlich - sonst Fehler
function requirePermission($permissionKey, $errorMessage = 'Keine Berechtigung für diese Aktion') {
    if (!hasPermission($permissionKey)) {
        die('<h1>Zugriff verweigert</h1><p>' . htmlspecialchars($errorMessage) . '</p><a href="index.php">Zur Übersicht</a>');
    }
}

// Benutzer zur Login-Seite umleiten
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Admin-Rechte erforderlich
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die('Zugriff verweigert. Admin-Rechte erforderlich.');
    }
}

// HTML escapen
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Erfolgs-/Fehlermeldungen anzeigen
function showMessage($type, $message) {
    return "<div class='alert alert-{$type}'>{$message}</div>";
}

// Bild hochladen
function uploadImage($file, $markerId) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // MIME-Type prüfen (kann gefälscht werden, aber erste Verteidigung)
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Ungültiger Dateityp'];
    }
    
    // Dateigröße prüfen
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Datei zu groß (max. 5MB)'];
    }
    
    // Extension prüfen
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'message' => 'Ungültige Dateiendung'];
    }
    
    // KRITISCH: Echten Bildtyp mit getimagesize prüfen
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'Keine gültige Bilddatei'];
    }
    
    // Nur erlaubte Bildtypen
    $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($imageInfo[2], $allowedImageTypes)) {
        return ['success' => false, 'message' => 'Bildtyp nicht erlaubt'];
    }
    
    // Sicheren Dateinamen generieren (ohne Original-Name!)
    $filename = uniqid('img_', true) . '_' . $markerId . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    // Upload-Verzeichnis sichern
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    // .htaccess erstellen um PHP-Ausführung zu verhindern
    $htaccess = UPLOAD_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "php_flag engine off\nOptions -Indexes");
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Dateiberechtigungen setzen
        chmod($filepath, 0644);
        return ['success' => true, 'path' => $filepath];
    }
    
    return ['success' => false, 'message' => 'Upload fehlgeschlagen'];
}

// Nächstes Wartungsdatum berechnen
function calculateNextMaintenance($lastMaintenance, $intervalMonths) {
    $date = new DateTime($lastMaintenance);
    $date->modify("+{$intervalMonths} months");
    return $date->format('Y-m-d');
}

// Wartungsstatus prüfen und automatisch setzen
function checkAndUpdateMaintenanceStatus($markerId, $pdo) {
    $marker = getMarkerById($markerId, $pdo);
    
    if (!$marker || $marker['is_storage'] || $marker['is_multi_device']) {
        return;
    }
    
    $today = new DateTime();
    $nextMaintenance = new DateTime($marker['next_maintenance']);
    
    // Wartung fällig?
    if ($nextMaintenance <= $today) {
        // Wenn vermietet: nur Flag setzen
        if ($marker['rental_status'] === 'vermietet') {
            $stmt = $pdo->prepare("UPDATE markers SET maintenance_required = TRUE WHERE id = ?");
            $stmt->execute([$markerId]);
        }
        // Wenn verfügbar: automatisch auf Wartung setzen
        elseif ($marker['rental_status'] === 'verfuegbar') {
            $stmt = $pdo->prepare("UPDATE markers SET rental_status = 'wartung', maintenance_required = TRUE WHERE id = ?");
            $stmt->execute([$markerId]);
        }
    }
}

// Status ändern mit Wartungslogik
function changeRentalStatus($markerId, $newStatus, $pdo) {
    $marker = getMarkerById($markerId, $pdo);
    
    if (!$marker || $marker['is_storage'] || $marker['is_multi_device']) {
        return false;
    }
    
    // Wenn von vermietet auf verfügbar und Wartung fällig → auf Wartung setzen
    if ($marker['rental_status'] === 'vermietet' && $newStatus === 'verfuegbar' && $marker['maintenance_required']) {
        $newStatus = 'wartung';
    }
    
    $stmt = $pdo->prepare("UPDATE markers SET rental_status = ? WHERE id = ?");
    return $stmt->execute([$newStatus, $markerId]);
}

// Marker-Farbe basierend auf Status
function getMarkerColor($marker) {
    if ($marker['is_multi_device']) {
        return '#764ba2'; // Lila für Multi-Device
    }
    
    if ($marker['is_storage']) {
        return '#28a745'; // Grün für Lager
    }
    
    if (empty($marker['rental_status'])) {
        return '#3388ff'; // Blau als Standard (Verfügbar)
    }
    
    switch ($marker['rental_status']) {
        case 'verfuegbar':
            return '#3388ff'; // Blau
        case 'vermietet':
            return '#ffc107'; // Gelb/Orange
        case 'wartung':
            return '#dc3545'; // Rot
        default:
            return '#e63312'; // Standard-Rot
    }
}

// Status-Label
function getRentalStatusLabel($status) {
    if (empty($status)) {
        return ['label' => 'Kein Status', 'class' => 'secondary'];
    }
    
    $labels = [
        'verfuegbar' => ['label' => 'Verfügbar', 'class' => 'success'],
        'vermietet' => ['label' => 'Vermietet', 'class' => 'warning'],
        'wartung' => ['label' => 'Wartung', 'class' => 'danger']
    ];
    
    return $labels[$status] ?? ['label' => 'Unbekannt', 'class' => 'secondary'];
}

// Wartungsstatus prüfen
function getMaintenanceStatus($nextMaintenance) {
    if (!$nextMaintenance) {
        return ['status' => 'none', 'label' => 'Keine', 'class' => 'secondary'];
    }
    
    $today = new DateTime();
    $next = new DateTime($nextMaintenance);
    $diff = $today->diff($next);
    
    if ($next < $today) {
        return ['status' => 'overdue', 'label' => 'Überfällig', 'class' => 'danger'];
    } elseif ($diff->days <= 30) {
        return ['status' => 'soon', 'label' => 'Bald fällig', 'class' => 'warning'];
    } else {
        return ['status' => 'ok', 'label' => 'OK', 'class' => 'success'];
    }
}

// System-Einstellungen aus Datenbank laden
function getSystemSettings() {
    global $pdo;
    
    // Default-Werte für ALLE Einstellungen
    $defaultSettings = [
        'map_default_lat' => '49.995567',
        'map_default_lng' => '9.0731267',
        'map_default_zoom' => '15',
        'marker_size' => 'medium',
        'marker_pulse' => '0',
        'marker_hover_scale' => '0',
        'show_map_legend' => '0',
        'show_system_messages' => '0',
        'system_name' => 'RFID Marker System',
        'system_logo' => '',
        'maintenance_check_days_before' => '7',
        'email_enabled' => '0',
        'email_from' => '',
        'email_from_name' => 'RFID System'
    ];
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // DB-Werte überschreiben Defaults
        return array_merge($defaultSettings, $dbSettings);
        
    } catch (PDOException $e) {
        error_log('Settings load error: ' . $e->getMessage());
        return $defaultSettings;
    }
}

// Einzelne Einstellung speichern
function saveSetting($key, $value) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        error_log('Setting save error: ' . $e->getMessage());
        return false;
    }
}

// Mehrere Einstellungen auf einmal speichern
function saveSettings($settings) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($settings as $key => $value) {
            // Boolean in String '1' oder '0' konvertieren
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Settings save error: ' . $e->getMessage());
        return false;
    }
}

// RFID-Chip validieren
function validateRFID($rfid) {
    // Mindestens 8 alphanumerische Zeichen
    return preg_match('/^[A-Za-z0-9]{8,}$/', $rfid);
}

// Seriennummer validieren (nur Zahlen)
function validateSerialNumber($serialNumber) {
    // Leer ist erlaubt
    if (empty($serialNumber)) {
        return true;
    }
    // Nur Zahlen erlaubt
    return preg_match('/^[0-9]+$/', $serialNumber);
}

// Benutzer-Informationen abrufen
function getUserInfo($userId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT u.*, r.display_name as role_display_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Alle Marker abrufen
function getAllMarkers($pdo) {
    $stmt = $pdo->query("
        SELECT m.*, u.username as created_by_name 
        FROM markers m 
        LEFT JOIN users u ON m.created_by = u.id 
        ORDER BY m.created_at DESC
    ");
    return $stmt->fetchAll();
}

// Marker nach ID abrufen
function getMarkerById($id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as created_by_name 
        FROM markers m 
        LEFT JOIN users u ON m.created_by = u.id 
        WHERE m.id = ? AND m.deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Bilder eines Markers abrufen
function getMarkerImages($markerId, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM marker_images WHERE marker_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$markerId]);
    return $stmt->fetchAll();
}

// Seriennummern eines Multi-Device Markers abrufen
function getMarkerSerialNumbers($markerId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT serial_number, created_at 
        FROM marker_serial_numbers 
        WHERE marker_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$markerId]);
    return $stmt->fetchAll();
}

// Datum formatieren (Deutsch)
function formatDate($date, $format = 'd.m.Y') {
    if (!$date) return '-';
    $dt = new DateTime($date);
    return $dt->format($format);
}

// Datum + Zeit formatieren (Deutsch)
function formatDateTime($datetime, $format = 'd.m.Y H:i') {
    if (!$datetime) return '-';
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

// Prüfen ob Marker Multi-Device ist
function isMultiDevice($marker) {
    return !empty($marker['is_multi_device']);
}

// Prüfen ob Marker Lagergerät ist
function isStorageDevice($marker) {
    return !empty($marker['is_storage']);
}

// Debug-Funktion (nur für Entwicklung)
function debug($data, $die = false) {
    echo '<pre style="background: #f4f4f4; padding: 10px; border: 1px solid #ddd; margin: 10px;">';
    print_r($data);
    echo '</pre>';
    if ($die) die();
}

// ==================== INPUT VALIDIERUNG ====================

// GPS-Koordinaten validieren
function validateCoordinates($lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return false;
    }
    $lat = floatval($lat);
    $lng = floatval($lng);
    
    if ($lat < -90 || $lat > 90) {
        return false;
    }
    if ($lng < -180 || $lng > 180) {
        return false;
    }
    return true;
}

// E-Mail validieren
function validateEmail($email) {
    if (empty($email)) {
        return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Integer validieren
function validateInteger($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $value = intval($value);
    
    if ($min !== null && $value < $min) {
        return false;
    }
    if ($max !== null && $value > $max) {
        return false;
    }
    return true;
}

// Float validieren
function validateFloat($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $value = floatval($value);
    
    if ($min !== null && $value < $min) {
        return false;
    }
    if ($max !== null && $value > $max) {
        return false;
    }
    return true;
}

// Datum validieren (Y-m-d Format)
function validateDate($date) {
    if (empty($date)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Benutzername validieren
function validateUsername($username) {
    if (empty($username)) {
        return false;
    }
    
    $length = strlen($username);
    
    // Länge: 3-50 Zeichen
    if ($length < 3 || $length > 50) {
        return false;
    }
    
    // Nur Buchstaben, Zahlen und Unterstriche
    return preg_match('/^[a-zA-Z0-9_]+$/', $username);
}

// Passwort-Stärke prüfen
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'Passwort muss mindestens 8 Zeichen lang sein'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'Passwort muss mindestens einen Großbuchstaben enthalten'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'Passwort muss mindestens einen Kleinbuchstaben enthalten'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Passwort muss mindestens eine Zahl enthalten'];
    }
    return ['valid' => true, 'message' => ''];
}

// String-Länge validieren
function validateStringLength($string, $min = 1, $max = 255) {
    $length = mb_strlen($string);
    return $length >= $min && $length <= $max;
}

// Zoom-Level validieren
function validateZoomLevel($zoom) {
    return validateInteger($zoom, 1, 19);
}

// Kategorie-Name validieren (nur alphanumerisch, Leerzeichen, Bindestriche)
function validateCategoryName($name) {
    if (empty($name) || !validateStringLength($name, 1, 50)) {
        return false;
    }
    return preg_match('/^[a-zA-ZäöüÄÖÜß0-9\s\-]+$/u', $name);
}

// ==================== AKTIVITÄTSPROTOKOLL ====================

function logActivity($action, $details = '', $markerId = null) {
    global $pdo;
    
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'System';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, username, action, details, marker_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $username,
            $action,
            $details,
            $markerId,
            $ipAddress,
            substr($userAgent, 0, 500) // Kürzen auf 500 Zeichen
        ]);
    } catch (Exception $e) {
        // Fehler nicht nach außen werfen, nur loggen
        error_log('Activity Log Fehler: ' . $e->getMessage());
    }
}

// PDF validieren
function validatePDF($file) {
    $allowedMime = ['application/pdf'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!in_array($file['type'], $allowedMime)) {
        return ['success' => false, 'message' => 'Nur PDF-Dateien erlaubt'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Datei zu groß (max. 10MB)'];
    }
    
    // Prüfen ob wirklich PDF
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if ($mimeType !== 'application/pdf') {
        return ['success' => false, 'message' => 'Keine gültige PDF-Datei'];
    }
    
    return ['success' => true];
}

// PDF hochladen
function uploadPDF($file, $markerId) {
    $validation = validatePDF($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    $uploadDir = UPLOAD_DIR . 'documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = 'doc_' . uniqid() . '_' . $markerId . '.pdf';
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        chmod($filepath, 0644);
        return ['success' => true, 'path' => $filepath, 'size' => $file['size']];
    }
    
    return ['success' => false, 'message' => 'Upload fehlgeschlagen'];
}

// ==================== USAGE TRACKING ====================

function trackUsage($actionType, $page = null) {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    if ($page === null) {
        $page = basename($_SERVER['PHP_SELF']);
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO usage_statistics (user_id, action_type, page) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $actionType, $page]);
    } catch (Exception $e) {
        error_log('Usage tracking failed: ' . $e->getMessage());
    }
}

// ==================== USER PREFERENCES ====================

function getUserPreferences($userId = null) {
    global $pdo;
    
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    
    if (!$userId) {
        return ['dark_mode' => false, 'language' => 'de', 'notifications_enabled' => true];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    
    if (!$prefs) {
        // Defaults erstellen
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        return ['dark_mode' => false, 'language' => 'de', 'notifications_enabled' => true];
    }
    
    return $prefs;
}

function saveUserPreference($key, $value, $userId = null) {
    global $pdo;
    
    if ($userId === null) {
        $userId = $_SESSION['user_id'];
    }
    
    // Sicherstellen dass Preferences existieren
    getUserPreferences($userId);
    
    $stmt = $pdo->prepare("UPDATE user_preferences SET $key = ? WHERE user_id = ?");
    $stmt->execute([$value, $userId]);
}

// ==================== GPS FUNKTIONEN ====================

function getGPSFromBrowser() {
    // JavaScript Funktion - wird in HTML verwendet
    return true;
}

// ==================== 2FA FUNKTIONEN ====================

function generate2FASecret() {
    // Einfacher Base32 Secret Generator
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 32; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

function verify2FACode($secret, $code) {
    // Vereinfachte TOTP Implementierung
    // In Production: Google Authenticator kompatible Library nutzen
    $timeSlice = floor(time() / 30);
    
    for ($i = -1; $i <= 1; $i++) {
        $calculatedCode = hash_hmac('sha1', pack('N*', 0) . pack('N*', $timeSlice + $i), base32_decode($secret), true);
        $calculatedCode = unpack('N', substr($calculatedCode, -4))[1] & 0x7FFFFFFF;
        $calculatedCode = str_pad($calculatedCode % 1000000, 6, '0', STR_PAD_LEFT);
        
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

function base32_decode($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0, $j = strlen($input); $i < $j; $i++) {
        $v <<= 5;
        $v += stripos($alphabet, $input[$i]);
        $vbits += 5;
        
        while ($vbits >= 8) {
            $vbits -= 8;
            $output .= chr($v >> $vbits);
            $v &= ((1 << $vbits) - 1);
        }
    }
    
    return $output;
}

// ==================== PDF GENERATION ====================

function generateChecklistPDF($completionId) {
    global $pdo;
    
    // Checklist Daten laden
    $stmt = $pdo->prepare("
        SELECT cc.*, ct.name as template_name, ct.items, m.name as marker_name, m.serial_number,
               u.username as completed_by_name
        FROM checklist_completions cc
        JOIN checklist_templates ct ON cc.template_id = ct.id
        JOIN markers m ON cc.marker_id = m.id
        LEFT JOIN users u ON cc.completed_by = u.id
        WHERE cc.id = ?
    ");
    $stmt->execute([$completionId]);
    $completion = $stmt->fetch();
    
    if (!$completion) {
        return false;
    }
    
    $items = json_decode($completion['items'], true);
    $results = json_decode($completion['results'], true);
    
    // PDF erstellen (mit TCPDF oder ähnlich)
    // Für jetzt: HTML zu PDF
    $html = generateChecklistHTML($completion, $items, $results);
    
    // Ordner erstellen
    $pdfDir = UPLOAD_DIR . 'checklists/' . $completion['serial_number'] . '/';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }
    
    $filename = 'checklist_' . $completionId . '_' . date('Y-m-d_His') . '.pdf';
    $filepath = $pdfDir . $filename;
    
    // HTML zu PDF konvertieren (wkhtmltopdf oder ähnlich nötig)
    // Alternativ: Browser Print to PDF
    file_put_contents($filepath, $html); // Temporär als HTML
    
    return $filepath;
}

function generateChecklistHTML($completion, $items, $results) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #e63312; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #f0f0f0; }
            .checked { color: green; font-weight: bold; }
            .unchecked { color: red; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>Wartungs-Checkliste</h1>
        <p><strong>Gerät:</strong> ' . htmlspecialchars($completion['marker_name']) . '</p>
        <p><strong>Seriennummer:</strong> ' . htmlspecialchars($completion['serial_number']) . '</p>
        <p><strong>Template:</strong> ' . htmlspecialchars($completion['template_name']) . '</p>
        <p><strong>Durchgeführt von:</strong> ' . htmlspecialchars($completion['completed_by_name']) . '</p>
        <p><strong>Datum:</strong> ' . date('d.m.Y H:i', strtotime($completion['completion_date'])) . '</p>
        
        <table>
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Prüfpunkt</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($items as $index => $item) {
        $checked = isset($results[$index]) && $results[$index] === true;
        $status = $checked ? '<span class="checked">✓ OK</span>' : '<span class="unchecked">✗ Nicht OK</span>';
        
        $html .= '<tr>
            <td>' . ($index + 1) . '</td>
            <td>' . htmlspecialchars($item) . '</td>
            <td>' . $status . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        </table>';
    
    if (!empty($completion['notes'])) {
        $html .= '<h3>Anmerkungen:</h3>
        <p>' . nl2br(htmlspecialchars($completion['notes'])) . '</p>';
    }
    
    $html .= '</body></html>';
    
    return $html;
}

// ==================== VERSCHLÜSSELUNG ====================

function encryptData($data, $key = null) {
    if ($key === null) {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-me';
    }
    
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    
    return base64_encode($encrypted . '::' . $iv);
}

function decryptData($data, $key = null) {
    if ($key === null) {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-me';
    }
    
    list($encrypted, $iv) = explode('::', base64_decode($data), 2);
    
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}

function searchMarkers($pdo, $filters = []) {
    $sql = "SELECT m.*, u.username as created_by_name 
            FROM markers m 
            LEFT JOIN users u ON m.created_by = u.id 
            WHERE 1=1";
    
    $params = [];
    
    // Globale Textsuche
    if (!empty($filters['search'])) {
        $sql .= " AND (m.name LIKE ? OR m.rfid_chip LIKE ? OR m.serial_number LIKE ? OR m.category LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Kategorie-Filter
    if (!empty($filters['category'])) {
        $sql .= " AND m.category = ?";
        $params[] = $filters['category'];
    }
    
    // Status-Filter
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'storage') {
            $sql .= " AND m.is_storage = 1";
        } elseif ($filters['status'] === 'multi_device') {
            $sql .= " AND m.is_multi_device = 1";
        } else {
            $sql .= " AND m.rental_status = ?";
            $params[] = $filters['status'];
        }
    }
    
    // Wartungsstatus-Filter
    if (!empty($filters['maintenance_status'])) {
        if ($filters['maintenance_status'] === 'overdue') {
            $sql .= " AND m.next_maintenance < CURDATE() AND m.is_storage = 0";
        } elseif ($filters['maintenance_status'] === 'due_soon') {
            $sql .= " AND m.next_maintenance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND m.is_storage = 0";
        } elseif ($filters['maintenance_status'] === 'ok') {
            $sql .= " AND (m.next_maintenance > DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR m.is_storage = 1)";
        }
    }
    
    // Datumsbereich
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(m.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(m.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Kraftstoff-Filter
    if (!empty($filters['fuel_min'])) {
        $sql .= " AND m.fuel_level >= ?";
        $params[] = intval($filters['fuel_min']);
    }
    
    if (!empty($filters['fuel_max'])) {
        $sql .= " AND m.fuel_level <= ?";
        $params[] = intval($filters['fuel_max']);
    }
    
    // Sortierung
    $orderBy = 'created_at';
    $orderDir = 'DESC';
    
    if (!empty($filters['sort_by'])) {
        $allowedSort = ['name', 'category', 'created_at', 'next_maintenance', 'fuel_level', 'rental_status'];
        if (in_array($filters['sort_by'], $allowedSort)) {
            $orderBy = $filters['sort_by'];
        }
    }
    
    if (!empty($filters['sort_dir']) && in_array(strtoupper($filters['sort_dir']), ['ASC', 'DESC'])) {
        $orderDir = strtoupper($filters['sort_dir']);
    }
    
    $sql .= " ORDER BY m.$orderBy $orderDir";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function saveSearch($pdo, $userId, $searchName, $searchParams) {
    $stmt = $pdo->prepare("INSERT INTO saved_searches (user_id, search_name, search_params) VALUES (?, ?, ?)");
    return $stmt->execute([$userId, $searchName, json_encode($searchParams)]);
}

function getSavedSearches($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM saved_searches WHERE user_id = ? ORDER BY last_used DESC, created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function updateSearchUsage($pdo, $searchId) {
    $stmt = $pdo->prepare("UPDATE saved_searches SET last_used = NOW(), use_count = use_count + 1 WHERE id = ?");
    $stmt->execute([$searchId]);
}

function deleteSavedSearch($pdo, $searchId, $userId) {
    $stmt = $pdo->prepare("DELETE FROM saved_searches WHERE id = ? AND user_id = ?");
    return $stmt->execute([$searchId, $userId]);
}
?>