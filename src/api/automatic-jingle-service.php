<?php
/**
 * Automatic Jingle Service - Casa Costanera
 * Servicio orquestador para generación automática de jingles desde voz
 */

require_once 'config.php';
require_once 'whisper-service.php';
require_once 'claude-service.php';
require_once 'jingle-service.php';

class AutomaticJingleService {
    private $whisperService;
    private $claudeService;
    private $logFile;
    
    public function __construct() {
        $this->whisperService = new WhisperService();
        $this->claudeService = new ClaudeService();
        // jingle-service.php usa funciones, no clases
        $this->logFile = __DIR__ . '/logs/automatic-' . date('Y-m-d') . '.log';
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
     * Obtener configuración completa de jingles
     */
    private function getJingleConfig() {
        $configFile = __DIR__ . '/data/jingle-config.json';
        
        // Configuración por defecto
        $defaultConfig = [
            'music_file' => 'Uplift.mp3',
            'music_volume' => 0.3,
            'voice_volume' => 1.0,
            'fade_in' => 2,
            'fade_out' => 2,
            'music_duck' => true,
            'duck_level' => 0.2,
            'intro_silence' => 2,
            'outro_silence' => 4,
            'voice_settings' => [
                'stability' => 0.75,
                'similarity_boost' => 0.8,
                'style' => 0.5,
                'use_speaker_boost' => true
            ]
        ];
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if (isset($config['jingle_defaults'])) {
                // Combinar configuración del archivo con valores por defecto
                $jingleConfig = array_merge($defaultConfig, [
                    'music_file' => $config['jingle_defaults']['default_music'] ?? $defaultConfig['music_file'],
                    'music_volume' => $config['jingle_defaults']['music_volume'] ?? $defaultConfig['music_volume'],
                    'voice_volume' => $config['jingle_defaults']['voice_volume'] ?? $defaultConfig['voice_volume'],
                    'fade_in' => $config['jingle_defaults']['fade_in'] ?? $defaultConfig['fade_in'],
                    'fade_out' => $config['jingle_defaults']['fade_out'] ?? $defaultConfig['fade_out'],
                    'music_duck' => $config['jingle_defaults']['ducking_enabled'] ?? $defaultConfig['music_duck'],
                    'duck_level' => $config['jingle_defaults']['duck_level'] ?? $defaultConfig['duck_level'],
                    'intro_silence' => $config['jingle_defaults']['intro_silence'] ?? $defaultConfig['intro_silence'],
                    'outro_silence' => $config['jingle_defaults']['outro_silence'] ?? $defaultConfig['outro_silence'],
                    'voice_settings' => $config['jingle_defaults']['voice_settings'] ?? $defaultConfig['voice_settings']
                ]);
                
                $this->log("Configuración cargada desde jingle-config.json");
                $this->log("intro_silence: " . $jingleConfig['intro_silence'] . "s, outro_silence: " . $jingleConfig['outro_silence'] . "s");
                return $jingleConfig;
            }
        }
        
        $this->log("Usando configuración por defecto (archivo no encontrado o sin configuración válida)");
        return $defaultConfig;
    }
    
    /**
     * Proceso completo: Texto → Mejora → Jingle
     */
    public function processAutomatic($textOrAudio, $voiceId, $isText = false, $musicFile = null) {
        try {
            $this->log("=== Iniciando proceso automático ===");
            $this->log("Voz seleccionada: $voiceId");
            $this->log("Modo: " . ($isText ? "Texto directo" : "Audio"));
            
            // Paso 1: Obtener texto (desde audio con Whisper o directo)
            if ($isText) {
                // Texto directo desde Web Speech API
                $originalText = $textOrAudio;
                $this->log("Texto recibido: $originalText");
                
                // Validar que el texto no esté vacío
                if (empty(trim($originalText))) {
                    return [
                        'success' => false,
                        'error' => 'No se detectó ningún mensaje. Por favor intenta de nuevo.',
                        'error_type' => 'empty_text'
                    ];
                }
            } else {
                // Transcribir audio con Whisper (modo original)
                $this->log("Paso 1: Transcribiendo audio...");
                $transcriptionResult = $this->whisperService->transcribe($textOrAudio, 'webm');
                
                if (!$transcriptionResult['success']) {
                    // Error específico para audio inaudible
                    if (strpos($transcriptionResult['error'], 'inaudible') !== false || 
                        strpos($transcriptionResult['error'], 'corto') !== false) {
                        return [
                            'success' => false,
                            'error' => 'No se escucha bien. Por favor vuelve a decirlo',
                            'error_type' => 'audio_quality'
                        ];
                    }
                    throw new Exception($transcriptionResult['error']);
                }
                
                $originalText = $transcriptionResult['text'];
            }
            $this->log("Transcripción: $originalText");
            
            // Paso 2: Mejorar texto con Claude (15-35 palabras)
            $this->log("Paso 2: Mejorando texto con IA...");
            $claudeParams = [
                'context' => $originalText,
                'category' => 'automatic',
                'mode' => 'automatic',
                'word_limit' => [15, 35]
            ];
            
            $claudeResult = $this->claudeService->generateAnnouncements($claudeParams);
            
            if (!$claudeResult['success']) {
                throw new Exception("Error en IA: " . $claudeResult['error']);
            }
            
            // Tomar la primera sugerencia
            $improvedText = $claudeResult['suggestions'][0]['text'] ?? $originalText;
            $this->log("Texto mejorado: $improvedText");
            
            // Paso 3: Generar jingle con configuración completa
            $this->log("Paso 3: Generando jingle con configuración del sistema...");
            
            // Obtener configuración completa desde jingle-config.json
            $jingleOptions = $this->getJingleConfig();
            
            // Si se especificó música personalizada, usarla
            if ($musicFile !== null) {
                if ($musicFile === '' || $musicFile === 'none') {
                    // Sin música
                    $jingleOptions['music_file'] = null;
                    $this->log("Generando sin música de fondo (selección del usuario)");
                } else {
                    // Música personalizada
                    $jingleOptions['music_file'] = $musicFile;
                    $this->log("Usando música personalizada: $musicFile");
                }
            } else {
                $this->log("Usando música por defecto: " . $jingleOptions['music_file']);
            }
            
            $this->log("Opciones del jingle: " . json_encode($jingleOptions));
            
            // generateJingle retorna array con audio binario
            $jingleResult = generateJingle($improvedText, $voiceId, $jingleOptions);
            
            if (!$jingleResult['success']) {
                throw new Exception("Error generando jingle: " . $jingleResult['error']);
            }
            
            // Guardar el audio en un archivo
            $timestamp = date('Ymd_His');
            $filename = "jingle_auto_{$timestamp}_{$voiceId}.mp3";
            $tempPath = __DIR__ . '/temp/' . $filename;
            
            // Asegurar que el directorio temp existe
            if (!file_exists(__DIR__ . '/temp')) {
                mkdir(__DIR__ . '/temp', 0777, true);
            }
            
            // Guardar el audio
            file_put_contents($tempPath, $jingleResult['audio']);
            $this->log("Audio guardado como: " . $filename);
            
            $this->log("=== Proceso completado exitosamente ===");
            
            // Guardar en base de datos
            $this->saveToDatabase($originalText, $improvedText, $voiceId, $filename);
            
            return [
                'success' => true,
                'original_text' => $originalText,
                'improved_text' => $improvedText,
                'voice_used' => $voiceId,
                'audio_url' => '/src/api/temp/' . $filename,
                'filename' => $filename,
                'duration' => $jingleResult['duration'] ?? null
            ];
            
        } catch (Exception $e) {
            $this->log("Error en proceso: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => 'processing'
            ];
        }
    }
    
    /**
     * Guardar registro en base de datos
     */
    private function saveToDatabase($originalText, $improvedText, $voiceId, $filename) {
        try {
            $db = new SQLite3('/var/www/casa/database/casa.db');
            
            $stmt = $db->prepare("
                INSERT INTO audio_metadata 
                (filename, display_name, description, category, metadata, created_at, is_active) 
                VALUES (?, ?, ?, 'automatic', ?, datetime('now'), 1)
            ");
            
            $metadata = json_encode([
                'original_text' => $originalText,
                'improved_text' => $improvedText,
                'voice' => $voiceId,
                'mode' => 'automatic'
            ]);
            
            $displayName = substr($improvedText, 0, 50) . '...';
            
            $stmt->bindValue(1, $filename, SQLITE3_TEXT);
            $stmt->bindValue(2, $displayName, SQLITE3_TEXT);
            $stmt->bindValue(3, $improvedText, SQLITE3_TEXT);
            $stmt->bindValue(4, $metadata, SQLITE3_TEXT);
            
            $stmt->execute();
            $db->close();
            
            $this->log("Registro guardado en BD");
            
        } catch (Exception $e) {
            $this->log("Error guardando en BD: " . $e->getMessage(), 'WARNING');
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
        // Obtener parámetros
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['voice_id'])) {
            throw new Exception('Falta parámetro requerido: voice_id');
        }
        
        $voiceId = $input['voice_id'];
        $service = new AutomaticJingleService();
        
        // Obtener música si se especificó
        $musicFile = isset($input['music_file']) ? $input['music_file'] : null;
        
        // Verificar si es texto directo o audio
        if (isset($input['text'])) {
            // Modo texto directo (Web Speech API)
            $result = $service->processAutomatic($input['text'], $voiceId, true, $musicFile);
        } elseif (isset($input['audio'])) {
            // Modo audio (Whisper)
            $audioData = base64_decode($input['audio']);
            $result = $service->processAutomatic($audioData, $voiceId, false, $musicFile);
        } else {
            throw new Exception('Debe proporcionar texto o audio');
        }
        
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