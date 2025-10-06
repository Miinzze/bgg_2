<?php
// Header wird in jeder Seite inkludiert
$isMobile = isMobileDevice();
?>
<header class="main-header">
    <div class="header-container">
        <div class="logo">
            <a href="index.php">
                <h1>RFID Marker System</h1>
            </a>
        </div>
        
        <nav class="main-nav">
            <ul>
                <li><a href="index.php">Übersicht</a></li>
                <li><a href="advanced_search.php">Erweiterte Suche</a></li>
                <?php if (hasPermission('markers_create')): ?>
                    <li class="mobile-only-nav"><a href="scan.php">RFID Scannen</a></li>
                    
                    <?php if (!$isMobile): ?>
                        <li><a href="create_marker.php">Marker erstellen</a></li>
                    <?php endif; ?>
                    
                    <li class="mobile-only-nav"><a href="rescan.php">Erneut Scannen</a></li>
                <?php endif; ?>
                
                <?php if (hasPermission('markers_delete')): ?>
                    <li><a href="trash.php">Papierkorb</a></li>
                <?php endif; ?>

                <?php if (!$isMobile): ?>
                    <?php if (hasPermission('users_manage') || hasPermission('roles_manage')): ?>
                        <li><a href="users.php">Benutzer</a></li>
                        <li><a href="roles.php">Rollen</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="user-menu">
            <!-- User Dropdown -->
            <div class="user-dropdown-container">
                <button class="user-dropdown-btn" onclick="toggleUserDropdown()">
                    <i class="fas fa-user-circle"></i>
                    <span class="username-text"><?= e($_SESSION['username']) ?></span>
                    <span class="badge badge-info"><?= e($_SESSION['role']) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <div class="dropdown-header">
                        <div>
                            <strong><?= e($_SESSION['username']) ?></strong>
                            <br><small style="color: #6c757d;"><?= e($_SESSION['role']) ?></small>
                        </div>
                    </div>
                    
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i> Mein Profil
                    </a>
                    
                    <a href="setup_2fa.php" class="dropdown-item">
                        <i class="fas fa-shield-alt"></i> Zwei-Faktor-Auth
                        <?php
                        $stmt = $pdo->prepare("SELECT has_2fa_enabled FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user2faStatus = $stmt->fetchColumn();
                        if ($user2faStatus):
                        ?>
                            <span class="badge badge-success badge-sm">Aktiv</span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (hasPermission('settings_manage')): ?>
                    <a href="settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i> Einstellungen
                    </a>
                    <?php endif; ?>
                    
                    <div class="dropdown-divider"></div>
                    
                    <a href="logout.php" class="dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt"></i> Abmelden
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* RFID-Navigationslinks nur auf Touch-Geräten anzeigen */
.mobile-only-nav {
    display: none;
}

/* Zeige RFID-Nav-Links nur auf Touch-Geräten */
@media (hover: none) and (pointer: coarse) {
    .mobile-only-nav {
        display: block;
    }
}

/* User Dropdown Container */
.user-dropdown-container {
    position: relative;
}

.user-dropdown-btn {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 8px 15px;
    border-radius: 25px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    font-size: 14px;
}

.user-dropdown-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.5);
}

.user-dropdown-btn .username-text {
    font-weight: 500;
}

.user-dropdown-btn .badge {
    font-size: 11px;
    padding: 3px 8px;
}

.user-dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 250px;
    display: none;
    z-index: 1000;
}

.user-dropdown-menu.show {
    display: block;
    animation: dropdownFade 0.2s ease;
}

@keyframes dropdownFade {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-header {
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    color: #2c3e50;
    text-decoration: none;
    transition: background 0.2s;
}

.dropdown-item:hover {
    background: #f8f9fa;
}

.dropdown-item i {
    width: 20px;
    text-align: center;
}

.dropdown-item.text-danger {
    color: #dc3545;
}

.dropdown-item.text-danger:hover {
    background: #fff5f5;
}

.dropdown-divider {
    height: 1px;
    background: #dee2e6;
    margin: 5px 0;
}

.badge-sm {
    font-size: 10px;
    padding: 2px 6px;
    margin-left: auto;
}

/* Mobile Anpassungen */
@media (max-width: 768px) {
    .user-dropdown-btn .username-text,
    .user-dropdown-btn .badge {
        display: none;
    }
    
    .user-dropdown-btn {
        padding: 8px 12px;
    }
}
</style>

<script>
// User Dropdown Toggle
function toggleUserDropdown() {
    const menu = document.getElementById('userDropdownMenu');
    menu.classList.toggle('show');
}

// Schließen beim Klick außerhalb
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-dropdown-container')) {
        const menu = document.getElementById('userDropdownMenu');
        if (menu) {
            menu.classList.remove('show');
        }
    }
});

</script>