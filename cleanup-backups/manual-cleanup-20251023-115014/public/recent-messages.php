<?php
// Test simple
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dbPath = '/var/www/casa/database/casa.db';

try {
    $db = new PDO("sqlite:$dbPath");
    $stmt = $db->query("SELECT COUNT(*) as total FROM audio_metadata WHERE is_saved = 0 AND is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'total' => $result['total']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}