<?php
// WICHTIG: Diese Datei nach Verwendung LÖSCHEN!

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        echo "<h2>Passwort-Hash:</h2>";
        echo "<code style='background: #f4f4f4; padding: 10px; display: block; word-break: break-all;'>$hash</code>";
        echo "<p>Kopiere diesen Hash in dein SQL INSERT Statement.</p>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Passwort-Hash Generator</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        input, button { padding: 10px; margin: 10px 0; width: 100%; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="warning">
        <strong>WARNUNG:</strong> Lösche diese Datei sofort nach der Verwendung!
    </div>
    
    <h1>Bug-Admin Passwort-Hash Generator</h1>
    <form method="POST">
        <label>Passwort eingeben:</label>
        <input type="password" name="password" required minlength="8">
        <button type="submit">Hash generieren</button>
    </form>
</body>
</html>