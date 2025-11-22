<?php
// Test simple para verificar que PHP funciona
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dbPath = '/var/www/casa/database/casa.db';

try {
    $db = new PDO("sqlite:$dbPath");
    $stmt = $db->query("SELECT COUNT(*) as total FROM audio_metadata WHERE is_saved = 0 AND is_active = 1");
    $count = $stmt->fetchColumn();
    
    $stmt = $db->query("
        SELECT filename, display_name as title, category, created_at
        FROM audio_metadata 
        WHERE is_saved = 0 AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'test' => true,
        'total' => $count,
        'messages' => $messages
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}