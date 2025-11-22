<?php
/**
 * API para obtener mensajes recientes (no guardados)
 * Retorna los últimos 40 mensajes con is_saved = 0
 */

// Solo enviar headers si no estamos en CLI
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Manejar OPTIONS para CORS
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Configuración
$dbPath = '/var/www/casa/database/casa.db';

try {
    if (!file_exists($dbPath)) {
        throw new Exception("Database not found at: $dbPath");
    }
    
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener los últimos 40 mensajes recientes (no guardados)
    $stmt = $db->prepare("
        SELECT 
            filename,
            display_name as title,
            description as content,
            category,
            created_at,
            'audio_' || REPLACE(filename, '.mp3', '') as id
        FROM audio_metadata 
        WHERE is_saved = 0 
            AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 40
    ");
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear respuesta
    $response = [
        'success' => true,
        'messages' => $messages,
        'total' => count($messages)
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>