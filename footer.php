<?php
// footer.php - System-Footer mit Bug-Report
$settings = getSystemSettings();
$bugReportEnabled = $settings['bug_report_enabled'] ?? '1';
?>

<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <p><?= e($settings['footer_copyright'] ?? '© 2025 RFID Marker System') ?></p>
                <p><?= e($settings['footer_company'] ?? 'Ihr Firmenname') ?></p>
            </div>
            
            <div class="footer-links">
                <a href="<?= e($settings['impressum_url'] ?? '/impressum.php') ?>">Impressum</a>
                <a href="<?= e($settings['datenschutz_url'] ?? '/datenschutz.php') ?>">Datenschutz</a>
                <?php if ($bugReportEnabled == '1' && isLoggedIn()): ?>
                    <a href="#" onclick="openBugReport(); return false;">
                        <i class="fas fa-bug"></i> Bug melden
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<!-- Bug-Report Modal -->
<?php if ($bugReportEnabled == '1' && isLoggedIn()): ?>
<div id="bugReportModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeBugReport()">&times;</span>
        <h2><i class="fas fa-bug"></i> Bug melden</h2>
        
        <form id="bugReportForm" method="POST" action="submit_bug.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="page_url" id="bug_page_url" value="">
            <input type="hidden" name="browser_info" id="bug_browser_info" value="">
            
            <div class="form-group">
                <label for="bug_title">Titel *</label>
                <input type="text" id="bug_title" name="title" required placeholder="Kurze Beschreibung des Problems">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="bug_email">E-Mail *</label>
                    <input type="email" id="bug_email" name="email" required value="<?= e($_SESSION['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="bug_phone">Telefon (optional)</label>
                    <input type="tel" id="bug_phone" name="phone" placeholder="Für Rückfragen">
                </div>
            </div>
            
            <div class="form-group">
                <label for="bug_description">Detaillierte Beschreibung *</label>
                <textarea id="bug_description" name="description" rows="5" required placeholder="Was ist passiert? Wie kann man das Problem reproduzieren?"></textarea>
            </div>
            
            <div class="form-group">
                <label for="bug_priority">Priorität</label>
                <select id="bug_priority" name="priority">
                    <option value="niedrig">Niedrig</option>
                    <option value="mittel" selected>Mittel</option>
                    <option value="hoch">Hoch</option>
                    <option value="kritisch">Kritisch</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="bug_screenshot">Screenshot (optional)</label>
                <input type="file" id="bug_screenshot" name="screenshot" accept="image/*">
                <small>PNG, JPG oder GIF, max. 5MB</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-paper-plane"></i> Bug melden
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBugReport()">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Cookie-Hinweis -->
<div id="cookieNotice" class="cookie-notice">
    <div class="cookie-content">
        <p>
            <i class="fas fa-cookie-bite"></i>
            <strong>Cookie-Hinweis:</strong> Diese Website verwendet Cookies, um Ihnen die bestmögliche Nutzererfahrung zu bieten. 
            Durch die weitere Nutzung stimmen Sie der Verwendung von Cookies zu.
            <a href="<?= e($settings['datenschutz_url'] ?? '/datenschutz.php') ?>" style="color: white; text-decoration: underline;">
                Mehr erfahren
            </a>
        </p>
        <button onclick="acceptCookies()" class="btn btn-primary btn-sm">
            <i class="fas fa-check"></i> Verstanden
        </button>
    </div>
</div>

<style>
.main-footer {
    background: var(--secondary-color);
    color: white;
    padding: 30px 0;
    margin-top: 50px;
}

.footer-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.footer-section p {
    margin: 5px 0;
    font-size: 14px;
}

.footer-links {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.footer-links a {
    color: white;
    text-decoration: none;
    font-size: 14px;
    transition: opacity 0.3s;
}

.footer-links a:hover {
    opacity: 0.8;
}

/* Cookie Notice */
.cookie-notice {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    color: white;
    padding: 20px;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
    z-index: 9999;
    display: none;
}

.cookie-notice.show {
    display: block;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}

.cookie-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.cookie-content p {
    margin: 0;
    flex: 1;
    font-size: 14px;
    line-height: 1.6;
}

/* Bug Report Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.6);
    animation: fadeIn 0.3s;
}

.modal.show {
    display: block;
}

@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        text-align: center;
    }
    
    .footer-links {
        justify-content: center;
    }
    
    .cookie-content {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
// Bug Report Modal
function openBugReport() {
    const modal = document.getElementById('bugReportModal');
    if (modal) {
        // Automatisch aktuelle Seite und Browser-Info erfassen
        document.getElementById('bug_page_url').value = window.location.href;
        document.getElementById('bug_browser_info').value = navigator.userAgent;
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeBugReport() {
    const modal = document.getElementById('bugReportModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Modal schließen bei Klick außerhalb
window.onclick = function(event) {
    const modal = document.getElementById('bugReportModal');
    if (event.target == modal) {
        closeBugReport();
    }
}

// Cookie-Hinweis
function acceptCookies() {
    localStorage.setItem('cookies_accepted', '1');
    document.getElementById('cookieNotice').classList.remove('show');
}

// Cookie-Hinweis anzeigen wenn nicht akzeptiert
window.addEventListener('DOMContentLoaded', function() {
    if (!localStorage.getItem('cookies_accepted')) {
        setTimeout(() => {
            document.getElementById('cookieNotice').classList.add('show');
        }, 1000);
    }
});

document.getElementById('bugReportForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Wird gesendet...';
    
    fetch('submit_bug.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bug erfolgreich gemeldet! Vielen Dank für Ihr Feedback.');
            closeBugReport();
            document.getElementById('bugReportForm').reset();
        } else {
            alert('Fehler: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});
</script>