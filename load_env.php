<?php
/**
 * Einfacher .env Loader ohne externe Dependencies
 * Speichert NUR in $_ENV, KEINE defines!
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env Datei nicht gefunden: ' . $path);
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Kommentare und leere Zeilen überspringen
        if (strpos(trim($line), '#') === 0 || trim($line) === '') {
            continue;
        }
        
        // KEY=VALUE parsen
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            
            $key = trim($key);
            $value = trim($value);
            
            // Anführungszeichen entfernen falls vorhanden
            $value = trim($value, '"\'');
            
            // NUR in $_ENV setzen, NICHT als Define!
            $_ENV[$key] = $value;
        }
    }
}

// .env laden
loadEnv(__DIR__ . '/.env');