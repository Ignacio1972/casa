<?php
/**
 * Automatic Jingle Service V2 - Casa Costanera
 * Versión con normalización LUFS profesional
 * Usa el sistema v2 de AudioProcessor
 */

// Cargar configuración y servicios v1 necesarios
require_once 'config.php';
require_once 'whisper-service.php';
require_once 'claude-service.php';
require_once 'automatic-usage-simple.php';

// Cargar servicios v2
require_once __DIR__ . '/v2/services/AudioProcessor.php';
require_once __DIR__ . '/v2/services/AudioProfiles.php';
require_once __DIR__ . '/v2/services/RateLimiter.php';

use App\Services\AudioProcessor;
use App\Services\AudioProfiles;
use App\Services\RateLimiter;

class AutomaticJingleServiceV2 {
    private $whisperService;
    private $claudeService;
    private $audioProcessor;  // v2 AudioProcessor
    private $rateLimiter;     // v2 RateLimiter
    private $logFile;
    
    public function __construct() {
        $this->whisperService = new WhisperService();
        $this->claudeService = new ClaudeService();
        $this->audioProcessor = new AudioProcessor();
        $this->rateLimiter = new RateLimiter();
        $this->logFile = __DIR__ . '/logs/automatic-v2-' . date('Y-m-d') . '.log';
    }
    
    /**
     * Determinar límites de palabras según duración objetivo
     */
    private function getWordLimits($targetDuration) {
        switch($targetDuration) {
            case 5:  return [5, 8];
            case 10: return [10, 15];
            case 15: return [15, 20];
            case 20: return [20, 30];
            case 25: return [30, 40];
            default: return [20, 30];
        }
    }
    
    private function log($message, $level = 'INFO') {
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] [V2] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Obtener configuración de jingles
     */
    private function getJingleConfig() {
        $configFile = __DIR__ . '/data/jingle-config.json';
        
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
                $jingleConfig = array_merge($defaultConfig, [
                    'music_file' => $config['jingle_defaults']['default_music'] ?? $defaultConfig['music_file'],
                    'music_volume' => $config['jingle_defaults']['music_volume'] ?? $defaultConfig['music_volume'],
                    'voice_volume' => $config['jingle_defaults']['voice_volume'] ?? $defaultConfig['voice_volume'],
                    'fade_in' => $config['jingle_defaults']['fade_in'] ?? $defaultConfig['fade_in'],
                    'fade_out' => $config['jingle_defaults']['fade_out'] ?? $defaultConfig['fade_out'],
                    'music_duck' => $config['jingle_defaults']['ducking_enabled'] ?? $defaultConfig['music_duck'],
                    'duck_level' => $config['jingle_defaults']['duck_level'] ?? $defaultConfig['duck_level'],
                    'intro_silence' => $config['jingle_defaults']['intro_silence'] ?? $defaultConfig['intro_silence'],
                    'outro_silence' => $config['jingle_defaults']['outro_silence'] ?? $defaultConfig['outro_silence']
                ]);
                
                $this->log("Configuración cargada desde jingle-config.json");
                return $jingleConfig;
            }
        }
        
        return $defaultConfig;
    }
    
    /**
     * Proceso principal orquestador
     */
    public function processAutomatic($audioData, $voiceId = null, $targetDuration = 20, $musicFile = null) {
        try {
            $this->log("=== Iniciando proceso automático V2 ===");
            
            // PASO 1: Verificar rate limits
            $rateLimitCheck = $this->rateLimiter->checkLimit('elevenlabs', 'automatic_mode');
            if (!$rateLimitCheck['allowed']) {
                throw new Exception("Rate limit excedido. Intenta en {$rateLimitCheck['retry_after']} segundos");
            }
            
            // PASO 2: Transcribir audio con Whisper
            $transcriptionResult = $this->whisperService->transcribe($audioData);
            
            if (!$transcriptionResult['success']) {
                throw new Exception("Error en transcripción: " . $transcriptionResult['error']);
            }
            
            $originalText = $transcriptionResult['text'];
            $this->log("Texto transcrito: " . $originalText);
            
            // PASO 3: Mejorar texto con Claude (con rate limiting)
            $claudeRateCheck = $this->rateLimiter->checkLimit('claude', 'automatic_mode');
            if (!$claudeRateCheck['allowed']) {
                $this->log("Claude rate limited, usando texto original", 'WARNING');
                $improvedText = $originalText;
            } else {
                $wordLimits = $this->getWordLimits($targetDuration);
                $claudeResult = $this->claudeService->improveText($originalText, [
                    'type' => 'jingle',
                    'min_words' => $wordLimits[0],
                    'max_words' => $wordLimits[1],
                    'client_type' => 'automatic_mode'
                ]);
                
                if ($claudeResult['success']) {
                    $improvedText = $claudeResult['improved_text'];
                    $this->log("Texto mejorado por Claude");
                } else {
                    $improvedText = $originalText;
                    $this->log("Usando texto original (Claude falló)", 'WARNING');
                }
            }
            
            // Registrar uso de caracteres
            $this->rateLimiter->trackCharacterUsage(strlen($improvedText));
            
            // PASO 4: Generar audio con ElevenLabs
            require_once 'services/tts-service-unified.php';
            $ttsService = new UnifiedTTSService();
            
            $ttsResult = $ttsService->generateSpeech($improvedText, $voiceId);
            
            if (!$ttsResult['success']) {
                throw new Exception("Error generando voz: " . $ttsResult['error']);
            }
            
            // Guardar archivo temporal de voz
            $tempVoiceFile = __DIR__ . '/v2/temp/voice_' . uniqid() . '.mp3';
            file_put_contents($tempVoiceFile, $ttsResult['audio']);
            
            $this->log("Voz generada, archivo temporal: " . basename($tempVoiceFile));
            
            // PASO 5: NORMALIZACIÓN LUFS CON SISTEMA V2
            $normalizedVoiceFile = __DIR__ . '/v2/temp/voice_normalized_' . uniqid() . '.mp3';
            
            // Determinar perfil basado en contexto
            $audioProfile = 'jingle'; // Para jingles con música
            if (!$musicFile || $musicFile === 'none') {
                $audioProfile = 'message'; // Para mensajes sin música
            }
            
            $normalizationResult = $this->audioProcessor->normalize(
                $tempVoiceFile,
                $normalizedVoiceFile,
                $audioProfile,
                $voiceId // El sistema aplicará el ajuste de volumen reducido
            );
            
            if (!$normalizationResult['success']) {
                throw new Exception("Error en normalización: " . json_encode($normalizationResult));
            }
            
            $this->log(sprintf(
                "Audio normalizado: %.1f LUFS → %.1f LUFS (perfil: %s)",
                $normalizationResult['metrics']['original']['integrated_lufs'],
                $normalizationResult['metrics']['final']['integrated_lufs'],
                $audioProfile
            ));
            
            // PASO 6: Si hay música, mezclar con jingle
            if ($musicFile && $musicFile !== 'none') {
                $jingleConfig = $this->getJingleConfig();
                
                // Override música si se especificó
                if ($musicFile !== 'default') {
                    $jingleConfig['music_file'] = $musicFile;
                }
                
                $this->log("Mezclando con música: " . $jingleConfig['music_file']);
                
                // Usar el archivo normalizado para la mezcla
                $finalFile = $this->mixWithMusic($normalizedVoiceFile, $jingleConfig);
                
                // Normalizar el jingle final
                $finalNormalizedFile = __DIR__ . '/temp/jingle_auto_' . date('Ymd_His') . '_' . $voiceId . '.mp3';
                
                $finalNormalization = $this->audioProcessor->normalize(
                    $finalFile,
                    $finalNormalizedFile,
                    'jingle', // Perfil para jingles
                    null // No aplicar ajuste de voz adicional
                );
                
                if ($finalNormalization['success']) {
                    $this->log("Jingle final normalizado a " . $finalNormalization['metrics']['final']['integrated_lufs'] . " LUFS");
                }
                
                $outputFile = $finalNormalizedFile;
            } else {
                // Sin música, usar el archivo normalizado directamente
                $outputFile = __DIR__ . '/temp/message_auto_' . date('Ymd_His') . '_' . $voiceId . '.mp3';
                copy($normalizedVoiceFile, $outputFile);
            }
            
            // Limpiar archivos temporales
            @unlink($tempVoiceFile);
            @unlink($normalizedVoiceFile);
            
            $this->log("=== Proceso V2 completado exitosamente ===");
            
            // Guardar en base de datos
            $this->saveToDatabase($originalText, $improvedText, $voiceId, basename($outputFile));
            
            return [
                'success' => true,
                'original_text' => $originalText,
                'improved_text' => $improvedText,
                'voice_used' => $voiceId,
                'audio_url' => '/src/api/temp/' . basename($outputFile),
                'filename' => basename($outputFile),
                'normalization' => [
                    'applied' => true,
                    'profile' => $audioProfile,
                    'final_lufs' => $normalizationResult['metrics']['final']['integrated_lufs'] ?? null
                ],
                'system_version' => 'v2'
            ];
            
        } catch (Exception $e) {
            $this->log("Error en proceso: " . $e->getMessage(), 'ERROR');
            
            // Registrar fallo en circuit breaker si es por servicio externo
            if (strpos($e->getMessage(), 'ElevenLabs') !== false) {
                $this->rateLimiter->recordFailure('elevenlabs');
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => 'processing',
                'system_version' => 'v2'
            ];
        }
    }
    
    /**
     * Mezclar voz con música
     */
    private function mixWithMusic($voiceFile, $config) {
        $musicPath = '/var/www/casa/public/audio/music/' . $config['music_file'];
        
        if (!file_exists($musicPath)) {
            $this->log("Música no encontrada: " . $config['music_file'], 'WARNING');
            return $voiceFile;
        }
        
        $outputFile = __DIR__ . '/v2/temp/jingle_' . uniqid() . '.mp3';
        
        // Comando FFmpeg para mezclar con ducking
        $cmd = sprintf(
            'ffmpeg -i %s -i %s -filter_complex ' .
            '"[1:a]volume=%.2f,afade=t=in:d=%.1f,afade=t=out:d=%.1f[music];' .
            '[0:a]volume=%.2f,adelay=%d|%d[voice];' .
            '[music][voice]amix=inputs=2:duration=longest:dropout_transition=3[out]" ' .
            '-map "[out]" -c:a libmp3lame -b:a 192k -ar 44100 %s 2>&1',
            escapeshellarg($voiceFile),
            escapeshellarg($musicPath),
            $config['music_volume'],
            $config['fade_in'],
            $config['fade_out'],
            $config['voice_volume'],
            $config['intro_silence'] * 1000,
            $config['intro_silence'] * 1000,
            escapeshellarg($outputFile)
        );
        
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            $this->log("Error mezclando audio: " . implode("\n", $output), 'ERROR');
            return $voiceFile;
        }
        
        return $outputFile;
    }
    
    /**
     * Subir archivo a AzuraCast
     */
    private function uploadToAzuracast($filePath, $filename) {
        try {
            require_once __DIR__ . '/services/radio-service.php';
            $radioService = new RadioService();
            
            $result = $radioService->uploadFileToAzuraCast($filePath, $filename);
            
            if ($result['success']) {
                $this->log("Archivo subido a AzuraCast: " . $filename);
            } else {
                $this->log("Error subiendo a AzuraCast: " . ($result['error'] ?? 'Unknown'), 'WARNING');
            }
            
            return $result;
        } catch (Exception $e) {
            $this->log("Excepción al subir a AzuraCast: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Guardar en base de datos
     */
    private function saveToDatabase($originalText, $improvedText, $voiceId, $filename) {
        try {
            $db = new SQLite3('/var/www/casa/database/casa.db');
            
            $stmt = $db->prepare("
                INSERT INTO audio_metadata 
                (filename, display_name, description, category, metadata, created_at, is_active) 
                VALUES (?, ?, ?, 'automatic_v2', ?, datetime('now'), 1)
            ");
            
            $metadata = json_encode([
                'original_text' => $originalText,
                'improved_text' => $improvedText,
                'voice' => $voiceId,
                'mode' => 'automatic',
                'system' => 'v2',
                'normalized' => true
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

// Procesar request
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['audio_data'])) {
        throw new Exception('No se recibió audio');
    }
    
    $service = new AutomaticJingleServiceV2();
    $result = $service->processAutomatic(
        $input['audio_data'],
        $input['voice_id'] ?? null,
        $input['target_duration'] ?? 20,
        $input['music_file'] ?? null
    );
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'system_version' => 'v2'
    ]);
}