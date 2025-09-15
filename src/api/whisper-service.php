<?php
/**
 * Whisper API Service - Casa Costanera
 * Servicio de transcripción de voz a texto usando OpenAI Whisper
 */

require_once 'config.php';

class WhisperService {
    private $apiKey;
    private $logFile;
    private $tempDir;
    
    public function __construct() {
        $this->apiKey = getenv('OPENAI_API_KEY') ?: '';
        $this->logFile = __DIR__ . '/logs/whisper-' . date('Y-m-d') . '.log';
        $this->tempDir = __DIR__ . '/temp/';
        
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
    
    private function log($message, $level = 'INFO') {
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Transcribir audio a texto usando Whisper API
     */
    public function transcribe($audioData, $format = 'webm') {
        try {
            $this->log("Iniciando transcripción de audio ($format)");
            
            // Guardar audio temporal
            $tempFile = $this->tempDir . 'whisper_' . uniqid() . '.' . $format;
            file_put_contents($tempFile, $audioData);
            $this->log("Archivo temporal creado: $tempFile");
            
            // Preparar la solicitud a Whisper
            $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
            
            $postFields = [
                'file' => new CURLFile($tempFile, "audio/$format", basename($tempFile)),
                'model' => 'whisper-1',
                'language' => 'es',
                'prompt' => 'Transcripción de anuncio para centro comercial Casa Costanera en Chile.'
            ];
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Limpiar archivo temporal
            @unlink($tempFile);
            
            if ($error) {
                throw new Exception("Error en cURL: " . $error);
            }
            
            if ($httpCode !== 200) {
                $errorData = json_decode($response, true);
                $errorMessage = $errorData['error']['message'] ?? "Error HTTP $httpCode";
                $this->log("Error en API: $errorMessage", 'ERROR');
                throw new Exception($errorMessage);
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['text'])) {
                throw new Exception("Respuesta inesperada de Whisper");
            }
            
            $transcription = trim($data['text']);
            $this->log("Transcripción exitosa: " . substr($transcription, 0, 100) . "...");
            
            // Validar calidad de transcripción
            if (strlen($transcription) < 10) {
                throw new Exception("Audio muy corto o inaudible");
            }
            
            return [
                'success' => true,
                'text' => $transcription,
                'language' => 'es',
                'duration' => $data['duration'] ?? null
            ];
            
        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage(), 'ERROR');
            
            // Limpiar archivo temporal si existe
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Si se llama directamente como API
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    try {
        // Obtener el audio del request
        $audioData = file_get_contents('php://input');
        
        if (empty($audioData)) {
            throw new Exception('No se recibió audio');
        }
        
        // Detectar formato (por defecto webm)
        $format = $_GET['format'] ?? 'webm';
        
        $service = new WhisperService();
        $result = $service->transcribe($audioData, $format);
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>