<?php
/**
 * API Endpoint para obtener TTS pendientes para el Player Local
 * Versión simplificada con URL directa al audio
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

// Base path
$basePath = '/var/www/casa';

// Función de logging
function logPendingTTS($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = '/var/www/casa/logs/pending-tts-' . date('Y-m-d') . '.log';
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    logPendingTTS("Request received from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Directorios de cola
    $queueDir = $basePath . '/database/local-player-queue/';
    $processedDir = $basePath . '/database/local-player-processed/';
    
    // Crear directorios si no existen
    if (!file_exists($queueDir)) {
        mkdir($queueDir, 0777, true);
    }
    if (!file_exists($processedDir)) {
        mkdir($processedDir, 0777, true);
    }
    
    // Si es una petición para marcar como entregado
    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'mark_as_delivered' && isset($_REQUEST['id'])) {
        $messageId = $_REQUEST['id'];
        $queueFile = $queueDir . $messageId . (strpos($messageId, ".json") === false ? ".json" : "");
        
        if (file_exists($queueFile)) {
            // Mover a procesados
            $processedFile = $processedDir . date('Ymd-His_') . $messageId . '.json';
            if (rename($queueFile, $processedFile)) {
                logPendingTTS("TTS marked as delivered: $messageId");
                echo json_encode([
                    'status' => 'ok',
                    'message' => 'TTS marked as delivered'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to mark as delivered'
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Message not found'
            ]);
        }
        exit;
    }
    
    // Buscar archivos .json en la cola
    $queueFiles = glob($queueDir . '*.json');
    
    if (empty($queueFiles)) {
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
        unlink($oldestFile);
        echo json_encode([
            'status' => 'error',
            'message' => 'Corrupted queue file'
        ]);
        exit;
    }
    
    // Verificar que el archivo de audio existe
    $audioPath = $basePath . '/' . $data['audio_path'];
    if (!file_exists($audioPath)) {
        logPendingTTS("Audio file not found: $audioPath");
        unlink($oldestFile);
        echo json_encode([
            'status' => 'error',
            'message' => 'Audio file not found'
        ]);
        exit;
    }
    
    // Construir URL directa al audio usando el nuevo endpoint
    $audioUrl = 'http://148.113.205.115:2082/api/audio-stream.php?file=' . urlencode($data['audio_path']);
    
    // Preparar respuesta con URL directa
    $response = [
        'status' => 'ok',
        'pending' => true,
        'tts' => [
            'id' => basename($oldestFile, '.json'),
            'text' => $data['text'] ?? '',
            'type' => $data['type'] ?? 'announcement',
            'priority' => $data['priority'] ?? 'normal',
            'audio_url' => $audioUrl,  // URL directa al streaming endpoint
            'duration' => $data['duration'] ?? null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'category' => $data['category'] ?? 'sin_categoria'
        ]
    ];
    
    logPendingTTS("TTS pending with audio URL: " . basename($oldestFile) . " (" . $data['text'] . ")");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logPendingTTS("Error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
