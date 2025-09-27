<?php
/**
 * Servicio de Ducking para TTS en AzuraCast
 * Reproduce mensajes TTS sobre la música sin interrumpirla
 */

require_once __DIR__ . '/services/tts-service-unified.php';

class DuckingService {
    private $media_path = '/var/azuracast/stations/test/media/Grabaciones/';
    
    /**
     * Envía un mensaje TTS con ducking
     */
    public function sendWithDucking($text, $voice = 'juan_carlos') {
        try {
            // 1. Generar TTS
            error_log("[Ducking] Generando TTS: $text");
            $audio = @generateEnhancedTTS($text, $voice);
            
            if (!$audio || strlen($audio) < 100) {
                throw new Exception("Error generando TTS");
            }
            
            // 2. Guardar temporalmente
            $tempFile = '/tmp/ducking_' . time() . '_' . rand(1000, 9999) . '.mp3';
            file_put_contents($tempFile, $audio);
            error_log("[Ducking] TTS guardado temporalmente: $tempFile (" . filesize($tempFile) . " bytes)");
            
            // 3. Copiar al contenedor de Docker
            $filename = 'ducking_' . date('Ymd_His') . '.mp3';
            $dockerPath = $this->media_path . $filename;
            
            $copyCmd = "sudo docker cp $tempFile azuracast:$dockerPath 2>&1";
            exec($copyCmd, $output, $return);
            
            if ($return !== 0) {
                throw new Exception("Error copiando archivo al contenedor: " . implode("\n", $output));
            }
            
            // 4. Ajustar permisos
            exec("sudo docker exec azuracast chown azuracast:azuracast $dockerPath 2>&1");
            
            // 5. Enviar a la cola de ducking
            $queueCmd = "tts_ducking_queue.push file://$dockerPath";
            $sendCmd = sprintf(
                'sudo docker exec azuracast bash -c \'echo "%s" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock 2>&1\'',
                addslashes($queueCmd)
            );
            
            $result = shell_exec($sendCmd);
            $lines = explode("\n", trim($result));
            $requestId = is_numeric(trim($lines[0])) ? trim($lines[0]) : null;
            
            // 6. Limpiar archivo temporal
            @unlink($tempFile);
            
            error_log("[Ducking] Enviado a Liquidsoap. Request ID: $requestId");
            
            return [
                'success' => $requestId !== null,
                'request_id' => $requestId,
                'filename' => $filename,
                'duration' => $this->estimateDuration(strlen($audio)),
                'message' => $requestId ? 'Mensaje enviado con ducking' : 'Error enviando a Liquidsoap'
            ];
            
        } catch (Exception $e) {
            error_log("[Ducking] Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Estima la duración del audio basado en el tamaño
     */
    private function estimateDuration($size) {
        // Aproximadamente 192kbps = 24KB/seg
        return round($size / 24000, 1);
    }
    
    /**
     * Limpia archivos antiguos de ducking
     */
    public function cleanup($hoursOld = 24) {
        $cmd = sprintf(
            'sudo docker exec azuracast find %s -name "ducking_*.mp3" -mtime +%d -delete 2>&1',
            $this->media_path,
            $hoursOld / 24
        );
        exec($cmd, $output, $return);
        return $return === 0;
    }
}

// Manejar peticiones HTTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $service = new DuckingService();
    
    $action = $input['action'] ?? 'send';
    
    switch ($action) {
        case 'send':
            $text = $input['text'] ?? '';
            $voice = $input['voice'] ?? 'juan_carlos';
            
            if (empty($text)) {
                $response = ['success' => false, 'error' => 'Texto requerido'];
            } else {
                $response = $service->sendWithDucking($text, $voice);
            }
            break;
            
        case 'test':
            $response = $service->sendWithDucking(
                "Prueba del sistema de ducking. La música debe bajar automáticamente.",
                'juan_carlos'
            );
            break;
            
        case 'cleanup':
            $success = $service->cleanup();
            $response = [
                'success' => $success,
                'message' => $success ? 'Limpieza completada' : 'Error en limpieza'
            ];
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Acción no válida'];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// CLI para pruebas
if (php_sapi_name() === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
    $service = new DuckingService();
    
    echo "=== Test de Ducking ===\n\n";
    
    $result = $service->sendWithDucking(
        "Atención clientes. Prueba del sistema de ducking. Este mensaje suena sobre la música.",
        'juan_carlos'
    );
    
    if ($result['success']) {
        echo "✅ Éxito!\n";
        echo "   Request ID: " . $result['request_id'] . "\n";
        echo "   Archivo: " . $result['filename'] . "\n";
        echo "   Duración estimada: " . $result['duration'] . " segundos\n";
    } else {
        echo "❌ Error: " . $result['error'] . "\n";
    }
}