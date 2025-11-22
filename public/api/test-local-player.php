<?php
/**
 * Script de prueba para enviar TTS al Player Local
 * Permite probar el sistema sin modificar generate.php
 */

// Incluir el helper
require_once __DIR__ . '/../src/api/helpers/local-player-queue.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Acción por defecto
    $action = $input['action'] ?? 'add_test';
    
    switch ($action) {
        case 'add_test':
            // Agregar un TTS de prueba a la cola
            $testData = [
                'text' => $input['text'] ?? 'Este es un mensaje de prueba para el player local',
                'audio_path' => 'public/audio/generated/test_message_' . date('Ymd_His') . '.mp3',
                'category' => $input['category'] ?? 'informativos',
                'type' => 'test',
                'priority' => 'high',
                'voice_name' => 'Test Voice',
                'destination' => 'local_player'
            ];
            
            // Para pruebas, usar un archivo existente si está disponible
            $existingFiles = glob(__DIR__ . '/../public/audio/generated/*.mp3');
            if (!empty($existingFiles)) {
                // Usar el archivo más reciente
                usort($existingFiles, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $testData['audio_path'] = 'public/audio/generated/' . basename($existingFiles[0]);
            }
            
            if (addToLocalPlayerQueue($testData)) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => 'Test TTS added to local player queue',
                    'data' => $testData,
                    'queue_count' => countLocalPlayerQueue()
                ]);
            } else {
                throw new Exception('Failed to add to queue');
            }
            break;
            
        case 'check_queue':
            // Verificar el estado de la cola
            $count = countLocalPlayerQueue();
            echo json_encode([
                'status' => 'ok',
                'queue_count' => $count,
                'message' => "There are $count TTS messages in the local player queue"
            ]);
            break;
            
        case 'cleanup':
            // Limpiar archivos procesados antiguos
            cleanupLocalPlayerProcessed();
            echo json_encode([
                'status' => 'ok',
                'message' => 'Cleanup completed'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
