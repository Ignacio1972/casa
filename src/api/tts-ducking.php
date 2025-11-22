<?php
/**
 * Servicio de TTS con Ducking para AzuraCast
 * Envía audio TTS a la cola de ducking sin interrumpir la música
 */

require_once __DIR__ . '/services/tts-service-unified.php';

// Función para enviar a la cola de ducking
function sendToDuckingQueue($audioFile) {
    // Construir el comando para la cola de ducking
    $fileUri = "file://" . realpath($audioFile);
    $command = "tts_ducking_queue.push $fileUri";
    
    // Ejecutar via Docker
    $dockerCommand = sprintf(
        'sudo docker exec azuracast bash -c \'echo "%s" | socat - UNIX-CONNECT:/var/azuracast/stations/mediaflow/config/liquidsoap.sock\'',
        addslashes($command)
    );
    
    $output = shell_exec($dockerCommand . ' 2>&1');
    
    // Log para debugging
    error_log("Ducking command: $command");
    error_log("Ducking output: $output");
    
    // Verificar si fue exitoso (debe devolver un ID numérico)
    $lines = explode("\n", trim($output));
    $requestId = isset($lines[0]) ? trim($lines[0]) : '';
    
    return [
        'success' => is_numeric($requestId),
        'request_id' => $requestId,
        'output' => $output
    ];
}

// Procesar request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Generar el TTS
        $text = $input['text'] ?? 'Mensaje de prueba con ducking';
        $voice = $input['voice'] ?? 'Rachel';
        
        // Usar la función existente del servicio unificado
        $ttsResult = generateEnhancedTTS($text, $voice, [
            'category' => 'ducking_test'
        ]);
        
        if (!isset($ttsResult['filepath'])) {
            throw new Exception('Error generando TTS');
        }
        
        // Enviar a la cola de ducking
        $duckingResult = sendToDuckingQueue($ttsResult['filepath']);
        
        echo json_encode([
            'success' => $duckingResult['success'],
            'message' => $duckingResult['success'] ? 
                'Audio enviado con ducking. La música bajará de volumen automáticamente.' : 
                'Error enviando a la cola de ducking',
            'audio_file' => $ttsResult['filepath'],
            'request_id' => $duckingResult['request_id'] ?? null,
            'debug' => $duckingResult['output']
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Si se ejecuta desde línea de comando para pruebas
if (php_sapi_name() === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
    echo "=== Test de Ducking TTS ===\n\n";
    
    // Usar juan_carlos que es la voz por defecto
    $testResult = generateEnhancedTTS(
        "Atención clientes, este es un mensaje de prueba del sistema de ducking. La música debe bajar de volumen mientras escuchan este mensaje.",
        "juan_carlos"
    );
    
    if (isset($testResult['filepath'])) {
        echo "✓ TTS generado: " . $testResult['filepath'] . "\n";
        
        // Enviar a ducking
        $duckResult = sendToDuckingQueue($testResult['filepath']);
        
        if ($duckResult['success']) {
            echo "✓ Enviado a cola de ducking. Request ID: " . $duckResult['request_id'] . "\n";
            echo "La música debería bajar de volumen ahora.\n";
        } else {
            echo "✗ Error enviando a cola de ducking\n";
            echo "Output: " . $duckResult['output'] . "\n";
        }
    } else {
        echo "✗ Error generando TTS\n";
    }
}