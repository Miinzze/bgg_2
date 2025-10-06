<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$darkMode = isset($input['dark_mode']) && $input['dark_mode'] === true ? 1 : 0;

try {
    saveUserPreference('dark_mode', $darkMode);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}