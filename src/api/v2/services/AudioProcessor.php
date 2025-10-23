<?php
/**
 * AudioProcessor Service v2
 * Normalización profesional de audio usando FFmpeg loudnorm (EBU R128)
 * 
 * @author Sistema v2
 * @version 2.0.0
 */

namespace App\Services;

class AudioProcessor {
    
    private const FFMPEG_PATH = '/usr/bin/ffmpeg';
    private const FFPROBE_PATH = '/usr/bin/ffprobe';
    
    // Perfiles de audio según tu especificación
    private const AUDIO_PROFILES = [
        'message' => [
            'target_lufs' => -16,
            'target_tp' => -1.5,
            'target_lra' => 7,
            'name' => 'Mensaje estándar'
        ],
        'jingle' => [
            'target_lufs' => -14,
            'target_tp' => -1.5,
            'target_lra' => 10,
            'name' => 'Jingle con música'
        ],
        'emergency' => [
            'target_lufs' => -12,
            'target_tp' => -1.0,
            'target_lra' => 5,
            'name' => 'Mensaje de emergencia'
        ]
    ];
    
    private $logger;
    private $voiceConfigs;
    private $tempDir;
    
    public function __construct() {
        $this->tempDir = '/var/www/casa/src/api/v2/temp/';
        $this->loadVoiceConfigs();
        $this->initLogger();
    }
    
    /**
     * Normaliza audio usando two-pass loudnorm para máxima precisión
     * 
     * @param string $inputFile Archivo de entrada
     * @param string $outputFile Archivo de salida
     * @param string $profile Perfil de audio (message|jingle|emergency)
     * @param string|null $voiceId ID de voz para ajuste adicional
     * @return array Resultado con métricas
     */
    public function normalize($inputFile, $outputFile, $profile = 'message', $voiceId = null) {
        $startTime = microtime(true);
        
        // Validar entrada
        if (!file_exists($inputFile)) {
            throw new \Exception("Input file not found: $inputFile");
        }
        
        // Obtener perfil
        $audioProfile = self::AUDIO_PROFILES[$profile] ?? self::AUDIO_PROFILES['message'];
        
        // PASO 1: Analizar audio actual (first pass)
        $this->log('info', 'Starting loudnorm analysis', [
            'file' => basename($inputFile),
            'profile' => $profile,
            'voice' => $voiceId
        ]);
        
        $analysis = $this->analyzeLoudness($inputFile);
        
        if (!$analysis) {
            throw new \Exception("Failed to analyze audio loudness");
        }
        
        // Obtener ajuste de volumen por voz si existe
        $voiceAdjustment = 0;
        if ($voiceId && isset($this->voiceConfigs[$voiceId])) {
            $voiceAdjustment = $this->voiceConfigs[$voiceId]['volume_adjustment'] ?? 0;
        }
        
        // PASO 2: Aplicar normalización con valores medidos (second pass)
        $normalizationResult = $this->applyLoudnorm(
            $inputFile,
            $outputFile,
            $audioProfile,
            $analysis,
            $voiceAdjustment
        );
        
        // PASO 3: Verificar resultado
        $finalAnalysis = $this->analyzeLoudness($outputFile);
        
        // Calcular tiempo de procesamiento
        $processingTime = round(microtime(true) - $startTime, 3);
        
        // Log resultado
        $this->log('success', 'Normalization completed', [
            'original_lufs' => $analysis['input_i'],
            'target_lufs' => $audioProfile['target_lufs'],
            'final_lufs' => $finalAnalysis['input_i'],
            'voice_adjustment' => $voiceAdjustment,
            'processing_time' => $processingTime
        ]);
        
        return [
            'success' => true,
            'profile' => $profile,
            'metrics' => [
                'original' => [
                    'integrated_lufs' => $analysis['input_i'],
                    'true_peak' => $analysis['input_tp'],
                    'lra' => $analysis['input_lra']
                ],
                'target' => [
                    'integrated_lufs' => $audioProfile['target_lufs'],
                    'true_peak' => $audioProfile['target_tp'],
                    'lra' => $audioProfile['target_lra']
                ],
                'final' => [
                    'integrated_lufs' => $finalAnalysis['input_i'],
                    'true_peak' => $finalAnalysis['input_tp'],
                    'lra' => $finalAnalysis['input_lra']
                ]
            ],
            'voice_adjustment_db' => $voiceAdjustment,
            'processing_time_ms' => $processingTime * 1000,
            'output_file' => $outputFile
        ];
    }
    
    /**
     * Analiza loudness usando FFmpeg ebur128
     */
    private function analyzeLoudness($file) {
        $cmd = sprintf(
            '%s -hide_banner -i %s -af loudnorm=print_format=json -f null - 2>&1',
            self::FFMPEG_PATH,
            escapeshellarg($file)
        );
        
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            $this->log('error', 'FFmpeg analysis failed', [
                'command' => $cmd,
                'return_code' => $returnVar
            ]);
            return null;
        }
        
        // Buscar el JSON en el output
        $jsonStr = '';
        $inJson = false;
        
        foreach ($output as $line) {
            if (strpos($line, '{') !== false) {
                $inJson = true;
            }
            if ($inJson) {
                $jsonStr .= $line . "\n";
            }
            if (strpos($line, '}') !== false && $inJson) {
                break;
            }
        }
        
        $metrics = json_decode($jsonStr, true);
        
        if (!$metrics) {
            $this->log('error', 'Failed to parse loudnorm JSON', [
                'raw_output' => implode("\n", $output)
            ]);
            return null;
        }
        
        return $metrics;
    }
    
    /**
     * Aplica normalización two-pass con loudnorm
     */
    /**
     * Normaliza audio con target LUFS personalizado
     * Método simplificado para uso directo con target específico
     */
    public function normalizeToTarget($inputFile, $outputFile, $targetLufs = -16) {
        // Método simplificado: aplicar normalización directa sin análisis previo
        // Esto es más rápido y funcional para el caso de jingles
        
        // Aplicar normalización con loudnorm en modo single-pass
        $cmd = sprintf(
            '%s -i %s -af "loudnorm=I=%d:TP=-1.0:LRA=11.0" -codec:a libmp3lame -b:a 192k -ar 44100 %s 2>&1',
            self::FFMPEG_PATH,
            escapeshellarg($inputFile),
            $targetLufs,
            escapeshellarg($outputFile)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'error' => 'Normalization failed'];
        }
        
        // Analizar el resultado para obtener métricas
        $finalCmd = sprintf(
            '%s -i %s -af "loudnorm=I=%d:TP=-1.0:LRA=11.0:print_format=summary" -f null - 2>&1',
            self::FFMPEG_PATH,
            escapeshellarg($outputFile),
            $targetLufs
        );
        
        exec($finalCmd, $finalOutput);
        $finalLufs = $targetLufs; // Default to target
        
        // Buscar el valor real de LUFS en la salida
        foreach ($finalOutput as $line) {
            if (preg_match('/Input Integrated:\s+(-?\d+\.?\d*)\s+LUFS/i', $line, $matches)) {
                $finalLufs = floatval($matches[1]);
                break;
            }
        }
        
        return [
            'success' => true,
            'metrics' => [
                'original' => ['integrated_lufs' => 'N/A'],
                'final' => ['integrated_lufs' => $finalLufs]
            ]
        ];
    }
    
    private function applyLoudnorm($input, $output, $profile, $measured, $voiceAdjustment = 0) {
        // Construir filtro loudnorm con valores medidos (two-pass)
        $loudnormFilter = sprintf(
            'loudnorm=I=%d:TP=%.1f:LRA=%d:measured_I=%.1f:measured_TP=%.1f:measured_LRA=%.1f:measured_thresh=%.1f:offset=%.1f:linear=true:print_format=summary',
            $profile['target_lufs'],
            $profile['target_tp'],
            $profile['target_lra'],
            $measured['input_i'],
            $measured['input_tp'],
            $measured['input_lra'],
            $measured['input_thresh'],
            $measured['target_offset'] + $voiceAdjustment
        );
        
        // Comando FFmpeg completo con configuraciones de calidad
        $cmd = sprintf(
            '%s -y -i %s -af "%s" -c:a libmp3lame -b:a 192k -ar 44100 -joint_stereo 1 %s 2>&1',
            self::FFMPEG_PATH,
            escapeshellarg($input),
            $loudnormFilter,
            escapeshellarg($output)
        );
        
        exec($cmd, $output_lines, $returnVar);
        
        if ($returnVar !== 0) {
            $this->log('error', 'Normalization failed', [
                'command' => $cmd,
                'output' => implode("\n", $output_lines)
            ]);
            throw new \Exception("FFmpeg normalization failed");
        }
        
        return true;
    }
    
    /**
     * Obtiene duración del audio en segundos
     */
    public function getDuration($file) {
        $cmd = sprintf(
            '%s -i %s -show_entries format=duration -v quiet -of csv=p=0',
            self::FFPROBE_PATH,
            escapeshellarg($file)
        );
        
        $duration = trim(shell_exec($cmd));
        return floatval($duration);
    }
    
    /**
     * Analiza características del audio
     */
    public function analyzeAudio($file) {
        if (!file_exists($file)) {
            return ['error' => 'File not found'];
        }
        
        // Obtener información básica
        $cmd = sprintf(
            '%s -i %s -show_format -show_streams -v quiet -print_format json',
            self::FFPROBE_PATH,
            escapeshellarg($file)
        );
        
        $info = json_decode(shell_exec($cmd), true);
        
        // Obtener loudness
        $loudness = $this->analyzeLoudness($file);
        
        return [
            'file' => basename($file),
            'duration' => $this->getDuration($file),
            'format' => $info['format']['format_name'] ?? 'unknown',
            'bitrate' => $info['format']['bit_rate'] ?? 0,
            'sample_rate' => $info['streams'][0]['sample_rate'] ?? 0,
            'channels' => $info['streams'][0]['channels'] ?? 0,
            'loudness' => [
                'integrated_lufs' => $loudness['input_i'] ?? null,
                'true_peak_db' => $loudness['input_tp'] ?? null,
                'loudness_range_lu' => $loudness['input_lra'] ?? null
            ]
        ];
    }
    
    /**
     * Carga configuración de voces
     */
    private function loadVoiceConfigs() {
        $configFile = '/var/www/casa/src/api/data/voices-config.json';
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            $this->voiceConfigs = [];
            
            if (isset($config['voices'])) {
                foreach ($config['voices'] as $voice) {
                    $this->voiceConfigs[$voice['id']] = $voice;
                }
            }
        } else {
            $this->voiceConfigs = [];
        }
    }
    
    /**
     * Sistema de logging estructurado en JSON
     */
    private function initLogger() {
        $this->logger = function($level, $message, $context = []) {
            $logFile = '/var/www/casa/src/api/v2/logs/audio-processor.jsonl';
            
            $entry = [
                'timestamp' => date('c'),
                'level' => $level,
                'service' => 'AudioProcessor',
                'message' => $message,
                'context' => $context,
                'pid' => getmypid()
            ];
            
            file_put_contents(
                $logFile,
                json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND | LOCK_EX
            );
        };
    }
    
    private function log($level, $message, $context = []) {
        call_user_func($this->logger, $level, $message, $context);
    }
    
    /**
     * Obtiene perfiles disponibles
     */
    public static function getAvailableProfiles() {
        return self::AUDIO_PROFILES;
    }
    
    /**
     * Limpia archivos temporales antiguos (>1 hora)
     */
    public function cleanupTempFiles() {
        $files = glob($this->tempDir . '*.mp3');
        $now = time();
        $deleted = 0;
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > 3600) {
                unlink($file);
                $deleted++;
            }
        }
        
        $this->log('info', 'Temp files cleanup', ['deleted' => $deleted]);
        return $deleted;
    }
}