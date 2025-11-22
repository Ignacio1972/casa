<?php
/**
 * API Endpoint para obtener TTS pendientes para el Player Local
 * Sistema de integración con Mediaflow Player local
 * 
 * Este endpoint permite al player local consultar si hay mensajes TTS
 * pendientes para reproducir, sin interferir con AzuraCast.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Función de logging
function logPendingTTS($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../src/api/logs/pending-tts-' . date('Y-m-d') . '.log';
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    logPendingTTS("Request received from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Directorios de cola para el player local
    $queueDir = __DIR__ . '/../database/local-player-queue/';
    $processedDir = __DIR__ . '/../database/local-player-processed/';
    
    // Crear directorios si no existen
    if (!file_exists($queueDir)) {
        mkdir($queueDir, 0777, true);
    }
    if (!file_exists($processedDir)) {
        mkdir($processedDir, 0777, true);
    }
    
    // Buscar archivos .json en la cola
    $queueFiles = glob($queueDir . '*.json');
    
    if (empty($queueFiles)) {
        // No hay TTS pendientes
        echo json_encode([
            'status' => 'ok',
            'pending' => false,
            'message' => 'No pending TTS messages for local player'
        ]);
        logPendingTTS("No pending messages");
        exit;
    }
    
    // Ordenar por fecha de creación (FIFO)
    usort($queueFiles, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    // Tomar el archivo más antiguo
    $oldestFile = $queueFiles[0];
    $data = json_decode(file_get_contents($oldestFile), true);
    
    if (!$data) {
        logPendingTTS("Error reading queue file: $oldestFile");
        unlink($oldestFile); // Eliminar archivo corrupto
        echo json_encode([
            'status' => 'error',
            'message' => 'Corrupted queue file'
        ]);
        exit;
    }
    
    // Verificar que el archivo de audio existe
    $audioPath = __DIR__ . '/../' . $data['audio_path'];
    if (!file_exists($audioPath)) {
        logPendingTTS("Audio file not found: " . $data['audio_path']);
        unlink($oldestFile); // Eliminar entrada de la cola
        echo json_encode([
            'status' => 'error',
            'message' => 'Audio file not found'
        ]);
        exit;
    }
    
    // Preparar respuesta
    $response = [
        'status' => 'ok',
        'pending' => true,
        'tts' => [
            'id' => basename($oldestFile, '.json'),
            'text' => $data['text'] ?? '',
            'type' => $data['type'] ?? 'announcement',
            'priority' => $data['priority'] ?? 'normal',
            'audio_url' => 'http://148.113.205.115/casa/' . $data['audio_path'],
            'duration' => $data['duration'] ?? null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'category' => $data['category'] ?? 'sin_categoria'
        ]
    ];
    
    // Si el request incluye 'action=mark_as_delivered', mover a procesados
    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'mark_as_delivered') {
        $processedFile = $processedDir . date('Ymd-His-') . basename($oldestFile);
        rename($oldestFile, $processedFile);
        logPendingTTS("TTS marked as delivered: " . basename($oldestFile));
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'TTS marked as delivered'
        ]);
        exit;
    }
    
    logPendingTTS("TTS pending: " . basename($oldestFile) . " (" . $data['text'] . ")");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logPendingTTS("Error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
