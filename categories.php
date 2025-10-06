<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

$message = '';
$messageType = '';

// Kategorie erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    $name = trim($_POST['category_name'] ?? '');
    $color = trim($_POST['category_color'] ?? '#007bff');
    $description = trim($_POST['category_description'] ?? '');
    $iconType = $_POST['icon_type'] ?? 'fontawesome';
    $icon = '';
    
    if (empty($name)) {
        $message = 'Kategoriename erforderlich';
        $messageType = 'danger';
    } else {
        // Icon-Typ prüfen
        if ($iconType === 'fontawesome') {
            $icon = $_POST['category_icon'] ?? 'fa-tag';
        } elseif ($iconType === 'upload' && !empty($_FILES['icon_upload']['tmp_name'])) {
            // Icon hochladen
            $allowedTypes = ['image/png', 'image/svg+xml', 'image/jpeg'];
            $maxSize = 500 * 1024; // 500KB
            
            if (!in_array($_FILES['icon_upload']['type'], $allowedTypes)) {
                $message = 'Ungültiger Dateityp. Nur PNG, SVG, JPG erlaubt';
                $messageType = 'danger';
            } elseif ($_FILES['icon_upload']['size'] > $maxSize) {
                $message = 'Icon zu groß (max. 500KB)';
                $messageType = 'danger';
            } else {
                $uploadDir = 'uploads/icons/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['icon_upload']['name'], PATHINFO_EXTENSION);
                $filename = 'icon_' . uniqid() . '.' . $extension;
                $filepath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['icon_upload']['tmp_name'], $filepath)) {
                    $icon = 'custom:' . $filepath;
                } else {
                    $message = 'Fehler beim Icon-Upload';
                    $messageType = 'danger';
                }
            }
        } else {
            $icon = 'fa-tag'; // Fallback
        }
        
        if (empty($message)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, icon, color, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $icon, $color, $description]);
                $message = 'Kategorie erstellt!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Kategorie existiert bereits';
                $messageType = 'danger';
            }
        }
    }
}

// Kategorie löschen
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("SELECT is_system, icon FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    
    if ($cat && !$cat['is_system']) {
        // Custom Icon löschen
        if (strpos($cat['icon'], 'custom:') === 0) {
            $iconPath = substr($cat['icon'], 7);
            if (file_exists($iconPath)) {
                unlink($iconPath);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Kategorie gelöscht';
        $messageType = 'success';
    } else {
        $message = 'System-Kategorien können nicht gelöscht werden';
        $messageType = 'danger';
    }
}

// Kategorien laden
$stmt = $pdo->query("SELECT * FROM categories ORDER BY is_system DESC, name ASC");
$categories = $stmt->fetchAll();

// Vordefinierte Icons
$predefinedIcons = [
    'fa-tag', 'fa-bolt', 'fa-truck', 'fa-wrench', 'fa-car', 'fa-warehouse',
    'fa-tools', 'fa-hammer', 'fa-laptop', 'fa-mobile', 'fa-box', 'fa-cube',
    'fa-cogs', 'fa-industry', 'fa-hard-hat', 'fa-tractor', 'fa-bicycle',
    'fa-motorcycle', 'fa-ship', 'fa-plane', 'fa-helicopter', 'fa-rocket',
    'fa-bus', 'fa-train', 'fa-subway', 'fa-taxi', 'fa-ambulance', 'fa-fire-truck',
    'fa-bulldozer', 'fa-forklift', 'fa-pump', 'fa-drill', 'fa-screwdriver',
    'fa-paint-roller', 'fa-toolbox', 'fa-briefcase', 'fa-clipboard'
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kategorien</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .category-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .category-icon-display {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }
        .category-icon-display img {
            width: 30px;
            height: 30px;
            object-fit: contain;
        }
        .category-info {
            flex: 1;
        }
        
        /* Icon Selector */
        .icon-type-selector {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .icon-type-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        .icon-type-btn:hover {
            border-color: #007bff;
        }
        .icon-type-btn.active {
            border-color: #007bff;
            background: #e7f3ff;
        }
        .icon-type-btn input[type="radio"] {
            display: none;
        }
        
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 10px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .icon-option {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            font-size: 24px;
        }
        .icon-option:hover {
            border-color: #007bff;
            transform: scale(1.1);
        }
        .icon-option.selected {
            border-color: #007bff;
            background: #007bff;
            color: white;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
        }
        
        .icon-upload-section {
            display: none;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 15px 0;
        }
        .icon-upload-section.active {
            display: block;
        }
        
        .icon-preview {
            width: 80px;
            height: 80px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px 0;
            background: white;
        }
        .icon-preview img {
            max-width: 60px;
            max-height: 60px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="main-container">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-tags"></i> Kategorienverwaltung</h1>
                <div class="header-actions">
                    <a href="settings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <div class="admin-grid">
                <!-- Erstellen -->
                <div class="admin-section">
                    <h2>Neue Kategorie</h2>
                    <form method="POST" enctype="multipart/form-data" id="categoryForm">
                        <div class="form-group">
                            <label for="category_name">Name *</label>
                            <input type="text" id="category_name" name="category_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_description">Beschreibung</label>
                            <textarea id="category_description" name="category_description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_color">Farbe</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="color" id="category_color" name="category_color" value="#007bff">
                                <span id="color_value">#007bff</span>
                            </div>
                        </div>
                        
                        <!-- Icon-Typ Auswahl -->
                        <div class="form-group">
                            <label>Icon auswählen *</label>
                            <div class="icon-type-selector">
                                <label class="icon-type-btn active" onclick="switchIconType('fontawesome')">
                                    <input type="radio" name="icon_type" value="fontawesome" checked>
                                    <i class="fas fa-icons" style="font-size: 24px; display: block; margin-bottom: 5px;"></i>
                                    <strong>Icon-Bibliothek</strong>
                                </label>
                                <label class="icon-type-btn" onclick="switchIconType('upload')">
                                    <input type="radio" name="icon_type" value="upload">
                                    <i class="fas fa-upload" style="font-size: 24px; display: block; margin-bottom: 5px;"></i>
                                    <strong>Eigenes hochladen</strong>
                                </label>
                            </div>
                        </div>
                        
                        <!-- FontAwesome Icons -->
                        <div id="fontawesome_section" class="icon-section">
                            <input type="hidden" id="category_icon" name="category_icon" value="fa-tag">
                            <div class="icon-grid">
                                <?php foreach ($predefinedIcons as $i => $iconClass): ?>
                                <div class="icon-option <?= $i === 0 ? 'selected' : '' ?>" 
                                     onclick="selectIcon(this, '<?= $iconClass ?>')">
                                    <i class="fas <?= $iconClass ?>"></i>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Icon Upload -->
                        <div id="upload_section" class="icon-upload-section">
                            <label for="icon_upload">Icon hochladen (PNG, SVG, JPG - max. 500KB)</label>
                            <input type="file" id="icon_upload" name="icon_upload" accept="image/png,image/svg+xml,image/jpeg" onchange="previewIcon(this)">
                            <div class="icon-preview" id="icon_preview">
                                <i class="fas fa-image" style="font-size: 32px; color: #ccc;"></i>
                            </div>
                        </div>
                        
                        <button type="submit" name="create_category" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Kategorie erstellen
                        </button>
                    </form>
                </div>
                
                <!-- Liste -->
                <div class="admin-section">
                    <h2>Vorhandene Kategorien (<?= count($categories) ?>)</h2>
                    <?php foreach ($categories as $cat): ?>
                        <div class="category-card" style="border-left-color: <?= htmlspecialchars($cat['color']) ?>">
                            <div class="category-icon-display" style="background: <?= htmlspecialchars($cat['color']) ?>">
                                <?php if (strpos($cat['icon'], 'custom:') === 0): ?>
                                    <img src="<?= htmlspecialchars(substr($cat['icon'], 7)) ?>" alt="Icon">
                                <?php else: ?>
                                    <i class="fas <?= htmlspecialchars($cat['icon']) ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="category-info">
                                <h4>
                                    <?= htmlspecialchars($cat['name']) ?>
                                    <?php if ($cat['is_system']): ?>
                                        <span class="badge badge-secondary">System</span>
                                    <?php endif; ?>
                                </h4>
                                <p><?= htmlspecialchars($cat['description'] ?: '-') ?></p>
                            </div>
                            <?php if (!$cat['is_system']): ?>
                                <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Kategorie &quot;<?= htmlspecialchars($cat['name']) ?>&quot; wirklich löschen?')">
                                   <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    // Icon auswählen
    function selectIcon(element, iconClass) {
        document.querySelectorAll('.icon-option').forEach(icon => {
            icon.classList.remove('selected');
        });
        element.classList.add('selected');
        document.getElementById('category_icon').value = iconClass;
    }
    
    // Icon-Typ wechseln
    function switchIconType(type) {
        document.querySelectorAll('.icon-type-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.currentTarget.classList.add('active');
        
        if (type === 'fontawesome') {
            document.getElementById('fontawesome_section').style.display = 'block';
            document.getElementById('upload_section').classList.remove('active');
            document.getElementById('icon_upload').removeAttribute('required');
        } else {
            document.getElementById('fontawesome_section').style.display = 'none';
            document.getElementById('upload_section').classList.add('active');
            document.getElementById('icon_upload').setAttribute('required', 'required');
        }
    }
    
    // Icon-Vorschau
    function previewIcon(input) {
        const preview = document.getElementById('icon_preview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Vorschau">';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Farb-Preview
    document.getElementById('category_color').addEventListener('input', function() {
        document.getElementById('color_value').textContent = this.value;
    });
    </script>
</body>
</html>