<?php
session_start();

require_once '../config.php';

// Separate Session für Bug-Admin
if (!isset($_SESSION['bug_csrf'])) {
    $_SESSION['bug_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Bitte alle Felder ausfüllen';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM bug_admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['bug_admin_id'] = $user['id'];
                $_SESSION['bug_admin_username'] = $user['username'];
                $_SESSION['bug_admin_email'] = $user['email'];
                
                session_regenerate_id(true);
                
                $stmt = $pdo->prepare("UPDATE bug_admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Ungültige Anmeldedaten';
            }
        } catch (PDOException $e) {
            $error = 'Fehler bei der Anmeldung';
            error_log('Bug Admin Login Error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bug-Verwaltung Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo-section">
                <h1><i class="fas fa-bug"></i> Bug-Verwaltung</h1>
                <p>RFID Marker System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Benutzername</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Anmelden
                </button>
            </form>
            
            <div class="login-footer">
                <a href="../index.php" style="color: #6c757d;">
                    <i class="fas fa-arrow-left"></i> Zurück zur Hauptseite
                </a>
            </div>
        </div>
    </div>
</body>
</html>