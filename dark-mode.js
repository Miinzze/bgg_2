// Schnelle Toggle-Funktion für Header-Button
function toggleDarkModeQuick() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    
    // In localStorage speichern
    localStorage.setItem('darkMode', isDark);
    
    // An Server senden
    fetch('save_dark_mode.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            dark_mode: isDark
        })
    }).catch(err => console.log('Dark Mode Speicherung fehlgeschlagen'));
    
    // Icon wechseln
    const icon = document.querySelector('.header-dark-toggle i');
    if (icon) {
        icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Keyboard Shortcut: Ctrl/Cmd + Shift + D
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        toggleDarkModeQuick();
    }
});

// Icon beim Laden setzen
document.addEventListener('DOMContentLoaded', () => {
    const isDark = document.body.classList.contains('dark-mode');
    const icon = document.querySelector('.header-dark-toggle i');
    if (icon && isDark) {
        icon.className = 'fas fa-sun';
    }
});