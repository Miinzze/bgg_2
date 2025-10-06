<?php
/**
 * Cron Job für Wartungsbenachrichtigungen
 * 
 * Dieser Cron-Job sollte täglich ausgeführt werden, z.B.:
 * 0 8 * * * /usr/bin/php /pfad/zu/cron_maintenance.php
 * 
 * Funktion:
 * - Prüft alle Geräte auf fällige Wartungen
 * - Sendet E-Mails an Benutzer, die Benachrichtigungen aktiviert haben
 * - Protokolliert alle Benachrichtigungen
 */

require_once 'config.php';

// Logging-Funktion
function logMessage($message) {
    $logFile = __DIR__ . '/logs/maintenance_cron.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// E-Mail senden Funktion
function sendMaintenanceEmail($to, $toName, $devices) {
    $subject = "Wartungserinnerung - RFID System";
    
    // HTML-E-Mail erstellen
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; margin-top: 20px; }
            .device { background: white; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; }
            .device-name { font-weight: bold; font-size: 18px; color: #dc3545; }
            .device-info { margin: 5px 0; color: #666; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .urgent { background: #fff3cd; border-left-color: #ffc107; }
            .overdue { background: #f8d7da; border-left-color: #dc3545; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>⚠️ Wartungserinnerung</h1>
            </div>
            <div class='content'>
                <p>Hallo " . htmlspecialchars($toName) . ",</p>
                <p>folgende Geräte benötigen demnächst oder sofort eine Wartung:</p>
    ";
    
    foreach ($devices as $device) {
        $daysUntil = (strtotime($device['next_maintenance_due']) - time()) / (60 * 60 * 24);
        $urgencyClass = '';
        $urgencyText = '';
        
        if ($daysUntil < 0) {
            $urgencyClass = 'overdue';
            $urgencyText = '<strong style="color: #dc3545;">ÜBERFÄLLIG seit ' . abs(round($daysUntil)) . ' Tagen!</strong>';
        } elseif ($daysUntil <= 7) {
            $urgencyClass = 'urgent';
            $urgencyText = '<strong style="color: #ffc107;">In ' . round($daysUntil) . ' Tagen fällig</strong>';
        } else {
            $urgencyText = 'In ' . round($daysUntil) . ' Tagen fällig';
        }
        
        $message .= "
                <div class='device $urgencyClass'>
                    <div class='device-name'>" . htmlspecialchars($device['name']) . "</div>
                    <div class='device-info'>📋 Kategorie: " . htmlspecialchars($device['category']) . "</div>
                    <div class='device-info'>🔢 Seriennummer: " . htmlspecialchars($device['serial_number']) . "</div>
                    <div class='device-info'>⏰ Fällig am: " . date('d.m.Y', strtotime($device['next_maintenance_due'])) . " ($urgencyText)</div>
                </div>
        ";
    }
    
    $message .= "
                <p style='margin-top: 20px;'>
                    <a href='" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/index.php' 
                       style='display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        Zur Übersicht
                    </a>
                </p>
            </div>
            <div class='footer'>
                <p>Dies ist eine automatische E-Mail vom RFID Wartungssystem.</p>
                <p>Sie erhalten diese E-Mail, weil Sie Wartungsbenachrichtigungen aktiviert haben.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // E-Mail Header
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: RFID System <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
    
    // E-Mail senden
    $success = mail($to, $subject, $message, $headers);
    
    if ($success) {
        logMessage("E-Mail erfolgreich gesendet an: $to");
    } else {
        logMessage("FEHLER: E-Mail konnte nicht gesendet werden an: $to");
    }
    
    return $success;
}

// Script Start
logMessage("=== Cron Job gestartet ===");

try {
    // 1. Fällige Wartungen finden (bis zu 14 Tage im Voraus)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            category,
            serial_number,
            next_maintenance_due,
            DATEDIFF(next_maintenance_due, CURDATE()) as days_until
        FROM map_objects 
        WHERE next_maintenance_due IS NOT NULL 
        AND next_maintenance_due <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        ORDER BY next_maintenance_due ASC
    ");
    $stmt->execute();
    $dueDevices = $stmt->fetchAll();
    
    logMessage("Gefunden: " . count($dueDevices) . " Geräte mit fälliger Wartung");
    
    if (count($dueDevices) > 0) {
        // 2. Benutzer mit aktivierten E-Mail-Benachrichtigungen laden
        $stmt = $pdo->prepare("
            SELECT id, username, email 
            FROM users 
            WHERE receive_maintenance_emails = 1 
            AND email IS NOT NULL 
            AND email != ''
        ");
        $stmt->execute();
        $notifyUsers = $stmt->fetchAll();
        
        logMessage("Gefunden: " . count($notifyUsers) . " Benutzer mit aktivierten Benachrichtigungen");
        
        // 3. E-Mails an alle benachrichtigungs-berechtigten Benutzer senden
        $emailsSent = 0;
        foreach ($notifyUsers as $user) {
            if (sendMaintenanceEmail($user['email'], $user['username'], $dueDevices)) {
                $emailsSent++;
            }
        }
        
        logMessage("E-Mails gesendet: $emailsSent von " . count($notifyUsers));
        
        // 4. Benachrichtigung in Datenbank protokollieren
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_notifications (sent_at, devices_count, users_notified)
            VALUES (NOW(), ?, ?)
        ");
        $stmt->execute([count($dueDevices), $emailsSent]);
        
    } else {
        logMessage("Keine fälligen Wartungen gefunden");
    }
    
    logMessage("=== Cron Job erfolgreich beendet ===\n");
    
} catch (Exception $e) {
    logMessage("FEHLER: " . $e->getMessage());
    logMessage("=== Cron Job mit Fehler beendet ===\n");
}
?>