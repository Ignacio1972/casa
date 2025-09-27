<?php
/**
 * TTS Ducking Service para AzuraCast
 * Usa la API de AzuraCast para enviar archivos a la cola
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/services/tts-service.php';

class TTSDuckingAzuraCast {
    private $ttsService;
    private $azuracast_url;
    private $api_key;
    private $station_id;
    private $temp_dir = '/var/www/casa/src/api/temp/';
    
    public function __construct() {
        $this->ttsService = new TTSService();
        
        // Cargar configuración desde .env
        $env = parse_ini_file('/var/www/casa/.env');
        $this->azuracast_url = $env['AZURACAST_BASE_URL'] ?? 'http://localhost';
        $this->api_key = $env['AZURACAST_API_KEY'] ?? '';
        $this->station_id = $env['AZURACAST_STATION_ID'] ?? 1;
        
        if (!file_exists($this->temp_dir)) {
            mkdir($this->temp_dir, 0777, true);
        }
    }
    
    /**
     * Genera TTS y lo envía a AzuraCast
     */
    public function generateAndQueue($text, $voice = 'Rachel', $options = []) {
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
            
            // Enviar a AzuraCast via API
            $queueResult = $this->sendToAzuraCast($audioFile, $options['immediate'] ?? true);
            
            return [
                'success' => true,
                'data' => [
                    'audio_file' => $audioFile,
                    'text' => $text,
                    'voice' => $voice,
                    'azuracast_response' => $queueResult,
                    'message' => 'Audio enviado a la cola de AzuraCast'
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
     * Envía archivo a la cola de AzuraCast
     */
    private function sendToAzuraCast($audioFile, $immediate = true) {
        try {
            if (!file_exists($audioFile)) {
                throw new Exception("Archivo no encontrado: $audioFile");
            }
            
            // Endpoint para enviar requests a la estación
            $endpoint = sprintf(
                '%s/api/station/%d/requests',
                rtrim($this->azuracast_url, '/'),
                $this->station_id
            );
            
            // Preparar el archivo para upload
            $ch = curl_init();
            
            // Configurar cURL para subir el archivo
            $postData = [
                'request' => new CURLFile($audioFile, 'audio/mpeg', basename($audioFile))
            ];
            
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-API-Key: ' . $this->api_key
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new Exception('cURL Error: ' . curl_error($ch));
            }
            
            curl_close($ch);
            
            if ($httpCode !== 200) {
                // Intentar método alternativo: cola de interrupciones
                return $this->sendViaInterruptQueue($audioFile);
            }
            
            return json_decode($response, true);
            
        } catch (Exception $e) {
            error_log("Error enviando a AzuraCast: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Método alternativo: Enviar via cola de interrupciones
     */
    private function sendViaInterruptQueue($audioFile) {
        try {
            // Usar el API interno de Liquidsoap
            $endpoint = sprintf(
                '%s/api/internal/%d/liquidsoap',
                rtrim($this->azuracast_url, '/'),
                $this->station_id
            );
            
            $postData = [
                'command' => 'interrupting_requests.push',
                'arg' => $audioFile
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-API-Key: ' . $this->api_key,
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return [
                'method' => 'interrupt_queue',
                'success' => $httpCode === 200,
                'response' => $response
            ];
            
        } catch (Exception $e) {
            error_log("Error con cola de interrupciones: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Test rápido del sistema
     */
    public function test() {
        $messages = [
            "Probando sistema de ducking.",
            "Este es un mensaje de prueba.",
            "La música debería reducirse automáticamente."
        ];
        
        $results = [];
        foreach ($messages as $index => $message) {
            $result = $this->generateAndQueue($message, 'Rachel', [
                'immediate' => true,
                'category' => 'test'
            ]);
            
            $results[] = [
                'message' => $message,
                'success' => $result['success'],
                'details' => $result['success'] ? $result['data'] : $result['error']
            ];
            
            if ($index < count($messages) - 1) {
                sleep(3);
            }
        }
        
        return [
            'success' => true,
            'test_results' => $results,
            'note' => 'Escucha la radio para verificar el funcionamiento'
        ];
    }
}

// Procesar request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $service = new TTSDuckingAzuraCast();
    
    $action = $input['action'] ?? 'generate';
    
    switch ($action) {
        case 'test':
            $response = $service->test();
            break;
            
        case 'generate':
        default:
            $text = $input['text'] ?? '';
            $voice = $input['voice'] ?? 'Rachel';
            
            if (empty($text)) {
                $response = [
                    'success' => false,
                    'error' => 'El texto es requerido'
                ];
            } else {
                $response = $service->generateAndQueue($text, $voice, $input);
            }
            break;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}