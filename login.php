<?php
ob_start();
require_once 'config.php';
require_once 'functions.php';

// Remember Me Token prüfen
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    $stmt = $pdo->prepare("
        SELECT rt.*, u.* 
        FROM remember_tokens rt
        JOIN users u ON rt.user_id = u.id
        WHERE rt.token = ? AND rt.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $rememberData = $stmt->fetch();
    
    if ($rememberData) {
        // Auto-Login
        $_SESSION['user_id'] = $rememberData['user_id'];
        $_SESSION['username'] = $rememberData['username'];
        $_SESSION['role'] = $rememberData['role'];
        $_SESSION['role_id'] = $rememberData['role_id'];
        
        session_regenerate_id(true);
        
        logActivity('auto_login', 'Auto-Login via Remember Me');
        
        ob_end_clean();
        header('Location: index.php');
        exit;
    } else {
        // Token ungültig/abgelaufen - Cookie löschen
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$max_attempts = 5;
$lockout_time = 900;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = time();
}

if ($_SESSION['login_attempts'] >= $max_attempts) {
    $time_passed = time() - $_SESSION['last_attempt'];
    if ($time_passed < $lockout_time) {
        $remaining = ceil(($lockout_time - $time_passed) / 60);
        $error = "Zu viele Anmeldeversuche. Bitte warten Sie $remaining Minuten.";
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

$error = '';
$show2FA = false;

// 2FA Verifizierung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    if (!isset($_SESSION['pending_user_id'])) {
        $error = 'Session abgelaufen. Bitte erneut anmelden.';
    } else {
        $code = trim($_POST['2fa_code'] ?? '');
        $userId = $_SESSION['pending_user_id'];
        $rememberMe = isset($_SESSION['pending_remember_me']) && $_SESSION['pending_remember_me'];
        
        $stmt = $pdo->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user2fa = $stmt->fetch();
        
        if (!$user2fa) {
            $error = '2FA nicht eingerichtet';
        } else {
            $isValid = false;
            
            if (verify2FACode($user2fa['secret'], $code)) {
                $isValid = true;
                $stmt = $pdo->prepare("UPDATE user_2fa SET last_used = NOW() WHERE user_id = ?");
                $stmt->execute([$userId]);
            } else {
                $backupCodes = json_decode($user2fa['backup_codes'], true);
                if ($backupCodes && in_array(strtoupper($code), $backupCodes)) {
                    $isValid = true;
                    $backupCodes = array_diff($backupCodes, [strtoupper($code)]);
                    $stmt = $pdo->prepare("UPDATE user_2fa SET backup_codes = ?, last_used = NOW() WHERE user_id = ?");
                    $stmt->execute([json_encode(array_values($backupCodes)), $userId]);
                }
            }
            
            if ($isValid) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['login_attempts'] = 0;
                
                // Remember Me Token erstellen
                if ($rememberMe) {
                    createRememberToken($user['id'], $pdo);
                }
                
                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_remember_me']);
                
                session_regenerate_id(true);
                
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                logActivity('login', 'Benutzer angemeldet (mit 2FA)');
                
                ob_end_clean();
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt'] = time();
                $error = 'Ungültiger Code';
                $show2FA = true;
            }
        }
    }
}

// Normaler Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && empty($error)) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = 'Bitte alle Felder ausfüllen';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $stmt = $pdo->prepare("SELECT * FROM user_2fa WHERE user_id = ? AND is_enabled = 1");
            $stmt->execute([$user['id']]);
            $user2fa = $stmt->fetch();
            
            if ($user2fa) {
                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_remember_me'] = $rememberMe;
                $_SESSION['login_attempts'] = 0;
                $show2FA = true;
            } else {
                $_SESSION['login_attempts'] = 0;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['role_id'] = $user['role_id'];
                
                // Remember Me Token erstellen
                if ($rememberMe) {
                    createRememberToken($user['id'], $pdo);
                }
                
                session_regenerate_id(true);
                
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                logActivity('login', 'Benutzer angemeldet');
                
                ob_end_clean();
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
            $error = 'Ungültige Anmeldedaten';
        }
    }
}

// Helper-Funktion für Remember Token
function createRememberToken($userId, $pdo) {
    // Alte Tokens löschen
    $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? OR expires_at < NOW()");
    $stmt->execute([$userId]);
    
    // Neuen Token erstellen
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 Tage
    
    $stmt = $pdo->prepare("
        INSERT INTO remember_tokens (user_id, token, expires_at, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $token,
        $expiresAt,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // Cookie setzen (30 Tage)
    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
}

// Neueste Patchnote
$latestPatchnote = null;
try {
    $stmt = $pdo->query("SELECT * FROM patchnotes WHERE is_active = 1 ORDER BY release_date DESC LIMIT 1");
    $latestPatchnote = $stmt->fetch();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RFID Marker System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
        .patchnote-box {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
        .patchnote-box h4 {
            margin: 0 0 10px 0;
            color: #007bff;
            font-size: 14px;
        }
        .patchnote-box .version {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 8px;
        }
        .patchnote-box p {
            margin: 10px 0;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-line;
        }
        .patchnote-box .patchnote-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }
        .patchnote-box .release-date {
            color: #6c757d;
        }
        .patchnote-box a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .patchnote-box a:hover {
            text-decoration: underline;
        }
        .fa-2-code {
            font-size: 48px;
            text-align: center;
            letter-spacing: 8px;
            font-family: monospace;
        }
        .code-input {
            font-size: 32px !important;
            text-align: center;
            letter-spacing: 10px;
            font-family: monospace;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo-section">
                <h1>RFID Marker System</h1>
                <p>Geräte- und Wartungsverwaltung</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            
            <?php if ($show2FA): ?>
                <div style="text-align: center; margin: 30px 0;">
                    <i class="fas fa-shield-alt" style="font-size: 64px; color: #007bff;"></i>
                    <h2 style="margin: 20px 0 10px 0;">Zwei-Faktor-Authentifizierung</h2>
                    <p style="color: #6c757d;">Geben Sie den 6-stelligen Code ein</p>
                </div>
                
                <form method="POST" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <input type="text" 
                               id="2fa_code" 
                               name="2fa_code" 
                               style="font-size: 32px; text-align: center; letter-spacing: 10px; font-family: monospace;"
                               required 
                               pattern="[0-9]{6,8}"
                               maxlength="8"
                               placeholder="000000"
                               autofocus
                               autocomplete="off">
                        <small style="display: block; text-align: center; margin-top: 10px;">
                            Oder verwenden Sie einen Backup-Code
                        </small>
                    </div>
                    
                    <button type="submit" name="verify_2fa" class="btn btn-primary btn-block">
                        <i class="fas fa-check"></i> Code bestätigen
                    </button>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="login.php" style="color: #6c757d; font-size: 14px;">
                            <i class="fas fa-arrow-left"></i> Zurück zum Login
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label for="username">Benutzername oder E-Mail</label>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember_me" value="1">
                            <span class="checkbox-text">
                                <i class="fas fa-history"></i> Angemeldet bleiben (30 Tage)
                            </span>
                        </label>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Anmelden
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if ($latestPatchnote && !$show2FA): ?>
            <div class="patchnote-box">
                <h4>
                    <i class="fas fa-rocket"></i> Was ist neu?
                    <span class="version">v<?= htmlspecialchars($latestPatchnote['version']) ?></span>
                    <?= htmlspecialchars($latestPatchnote['title']) ?>
                </h4>
                <p><?= htmlspecialchars($latestPatchnote['description']) ?></p>
                <div class="patchnote-footer">
                    <span class="release-date">
                        <i class="fas fa-calendar"></i> 
                        <?= date('d.m.Y', strtotime($latestPatchnote['release_date'])) ?>
                    </span>
                    <a href="patchnotes.php">
                        <i class="fas fa-list"></i> Alle Updates anzeigen
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    const codeInput = document.getElementById('2fa_code');
    if (codeInput) {
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                setTimeout(() => this.form.submit(), 300);
            }
        });
    }
    </script>
</body>
</html>