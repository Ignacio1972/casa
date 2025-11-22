<?php
/**
 * API Endpoint: Test de cola del Player Local
 * Accesible vía HTTP
 */

require_once __DIR__ . '/helpers/local-player-queue.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'add_test';

    switch ($action) {
        case 'add_test':
            // Agregar mensaje de prueba a la cola
            $testMessage = [
                'text' => $input['text'] ?? 'Prueba de TTS para Player Local desde el VPS. Este es un mensaje de prueba del sistema integrado.',
                'audio_path' => 'src/api/temp/test_mensaje_' . date('Ymd_His') . '.mp3',
                'category' => $input['category'] ?? 'informativos',
                'type' => 'test',
                'priority' => 'high',
                'voice_name' => 'Juan Carlos',
                'destination' => 'local_player'
            ];

            $result = addToLocalPlayerQueue($testMessage);

            if ($result) {
                $queueCount = countLocalPlayerQueue();

                echo json_encode([
                    'success' => true,
                    'message' => 'Mensaje de prueba agregado exitosamente a la cola del Player Local',
                    'queue_count' => $queueCount,
                    'test_data' => $testMessage
                ], JSON_PRETTY_PRINT);
            } else {
                throw new Exception('Error al agregar mensaje a la cola');
            }
            break;

        case 'check_queue':
            // Ver estado de la cola
            $queueCount = countLocalPlayerQueue();
            $queueDir = __DIR__ . '/../../database/local-player-queue/';

            $messages = [];
            if (file_exists($queueDir)) {
                $files = glob($queueDir . '*.json');
                foreach ($files as $file) {
                    $data = json_decode(file_get_contents($file), true);
                    $messages[] = [
                        'id' => $data['id'],
                        'text' => substr($data['text'], 0, 80) . '...',
                        'category' => $data['category'],
                        'priority' => $data['priority'],
                        'created_at' => $data['created_at']
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'queue_count' => $queueCount,
                'messages' => $messages,
                'queue_dir' => $queueDir
            ], JSON_PRETTY_PRINT);
            break;

        case 'cleanup':
            // Limpiar archivos procesados antiguos
            cleanupLocalPlayerProcessed();

            echo json_encode([
                'success' => true,
                'message' => 'Limpieza de archivos procesados completada'
            ], JSON_PRETTY_PRINT);
            break;

        default:
            throw new Exception('Acción no válida. Usa: add_test, check_queue, cleanup');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
