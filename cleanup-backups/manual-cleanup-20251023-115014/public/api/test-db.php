<?php
header('Content-Type: application/json');

$dbPath = '/var/www/casa/database/casa.db';

try {
    if (!file_exists($dbPath)) {
        throw new Exception("Database file not found: $dbPath");
    }
    
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM audio_metadata WHERE is_saved = 0 AND is_active = 1");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => $count['count'],
        'db_path' => $dbPath
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}