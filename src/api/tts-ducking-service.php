<?php
/**
 * TTS Ducking Service
 * Genera audio TTS y lo envía a Liquidsoap con autoducking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/services/tts-service.php';

class TTSDuckingService {
    private $ttsService;
    private $liquidsoap_socket = '/var/azuracast/stations/test/config/liquidsoap.sock';
    private $temp_dir = '/var/www/casa/src/api/temp/';
    
    public function __construct() {
        $this->ttsService = new TTSService();
        
        if (!file_exists($this->temp_dir)) {
            mkdir($this->temp_dir, 0777, true);
        }
    }
    
    /**
     * Genera TTS y lo envía con ducking
     */
    public function generateWithDucking($text, $voice = 'Rachel', $options = []) {
        try {
            // Generar el audio TTS
            $ttsResult = $this->ttsService->generateSpeech([
                'text' => $text,
                'voice' => $voice,
                'category' => $options['category'] ?? 'informativos'
            ]);
            
            if (!$ttsResult['success']) {
                throw new Exception($ttsResult['error'] ?? 'Error generando TTS');
            }
            
            $audioFile = $ttsResult['data']['file_path'];
            
            // Enviar a la cola de ducking en Liquidsoap
            $duckingResult = $this->sendToLiquidsoap($audioFile);
            
            return [
                'success' => true,
                'data' => [
                    'audio_file' => $audioFile,
                    'text' => $text,
                    'voice' => $voice,
                    'ducking_status' => $duckingResult,
                    'message' => 'Audio enviado con ducking activado'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envía el archivo a la cola de ducking de Liquidsoap
     */
    private function sendToLiquidsoap($audioFile) {
        try {
            // Verificar que el archivo existe
            if (!file_exists($audioFile)) {
                throw new Exception("Archivo de audio no encontrado: $audioFile");
            }
            
            // Comando para enviar a la cola de ducking
            $command = sprintf(
                'echo "tts_ducking_queue.push %s" | socat - UNIX-CONNECT:%s',
                escapeshellarg($audioFile),
                escapeshellarg($this->liquidsoap_socket)
            );
            
            // Ejecutar el comando
            $output = [];
            $return_var = 0;
            exec($command . ' 2>&1', $output, $return_var);
            
            if ($return_var !== 0) {
                // Si socat falla, intentar con telnet
                $telnetCommand = sprintf(
                    'echo "tts_ducking_queue.push %s" | nc -U %s',
                    escapeshellarg($audioFile),
                    escapeshellarg($this->liquidsoap_socket)
                );
                
                exec($telnetCommand . ' 2>&1', $output, $return_var);
                
                if ($return_var !== 0) {
                    throw new Exception("Error enviando a Liquidsoap: " . implode("\n", $output));
                }
            }
            
            return [
                'sent' => true,
                'file' => $audioFile,
                'output' => implode("\n", $output)
            ];
            
        } catch (Exception $e) {
            error_log("Error en sendToLiquidsoap: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Test de ducking con mensaje predefinido
     */
    public function testDucking() {
        $testMessages = [
            "Atención visitantes del centro comercial",
            "Les recordamos que el estacionamiento cierra a las 10 PM",
            "Gracias por su visita"
        ];
        
        $results = [];
        
        foreach ($testMessages as $message) {
            $result = $this->generateWithDucking($message, 'Rachel');
            $results[] = $result;
            
            // Esperar 2 segundos entre mensajes
            sleep(2);
        }
        
        return [
            'success' => true,
            'test_results' => $results
        ];
    }
}

// Procesar request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $service = new TTSDuckingService();
    
    $action = $input['action'] ?? 'generate';
    
    switch ($action) {
        case 'test':
            $response = $service->testDucking();
            break;
            
        case 'generate':
        default:
            $text = $input['text'] ?? '';
            $voice = $input['voice'] ?? 'Rachel';
            $options = [
                'category' => $input['category'] ?? 'informativos'
            ];
            
            if (empty($text)) {
                $response = [
                    'success' => false,
                    'error' => 'El texto es requerido'
                ];
            } else {
                $response = $service->generateWithDucking($text, $voice, $options);
            }
            break;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}