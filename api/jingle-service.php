<?php
/**
 * Servicio de Jingles - Mezcla música con mensajes TTS
 * Sistema para crear jingles combinando música de fondo con voces generadas
 */

require_once __DIR__ . '/../src/api/config.php';
require_once __DIR__ . '/../src/api/services/tts-service.php';

// Función de logging
if (!function_exists('logMessage')) {
    function logMessage($message) {
        error_log("[JingleService] " . $message);
    }
}

/**
 * Genera un jingle mezclando música con mensaje TTS
 * @param string $text Texto del mensaje
 * @param string $voice Voz a usar
 * @param array $options Opciones de generación
 * @return array Resultado con el archivo generado
 */
function generateJingle($text, $voice, $options = []) {
    try {
        // Opciones por defecto
        $defaults = [
            'music_file' => null,           // Archivo de música de fondo
            'music_volume' => 0.3,           // Volumen de música (0.0 - 1.0)
            'voice_volume' => 1.0,           // Volumen de voz (0.0 - 1.0)
            'fade_in' => 2,                  // Segundos de fade in
            'fade_out' => 2,                 // Segundos de fade out
            'music_duck' => true,            // Reducir música cuando habla
            'duck_level' => 0.2,             // Nivel de ducking (0.0 - 1.0)
            'intro_silence' => 1,            // Silencio antes del mensaje
            'outro_silence' => 1,            // Silencio después del mensaje
            'output_format' => 'mp3',        // Formato de salida
            'voice_settings' => []           // Settings para TTS
        ];
        
        $config = array_merge($defaults, $options);
        
        logMessage("[JingleService] Iniciando generación de jingle");
        logMessage("[JingleService] Config: " . json_encode($config));
        
        // 1. Generar el audio TTS
        $ttsAudio = generateEnhancedTTS($text, $voice, $config['voice_settings']);
        
        // Crear directorio temporal
        $tempDir = sys_get_temp_dir() . '/jingles_' . uniqid();
        if (!mkdir($tempDir, 0777, true)) {
            throw new Exception("No se pudo crear directorio temporal");
        }
        
        // Guardar audio TTS temporalmente
        $ttsFile = $tempDir . '/voice.mp3';
        file_put_contents($ttsFile, $ttsAudio);
        
        logMessage("[JingleService] TTS generado: " . filesize($ttsFile) . " bytes");
        
        // 2. Si no hay música, devolver solo el TTS con silencios
        if (empty($config['music_file'])) {
            $outputFile = $tempDir . '/jingle.' . $config['output_format'];
            
            // Agregar silencios al inicio y final
            $ffmpegCmd = sprintf(
                'ffmpeg -f lavfi -i anullsrc=r=44100:cl=stereo -t %d -i "%s" -f lavfi -i anullsrc=r=44100:cl=stereo -t %d ' .
                '-filter_complex "[0:a][1:a][2:a]concat=n=3:v=0:a=1[out]" ' .
                '-map "[out]" -codec:a libmp3lame -b:a 192k "%s" 2>&1',
                $config['intro_silence'],
                $ttsFile,
                $config['outro_silence'],
                $outputFile
            );
            
            exec($ffmpegCmd, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("Error procesando audio: " . implode("\n", $output));
            }
            
            $result = file_get_contents($outputFile);
            
            // Limpiar archivos temporales
            unlink($ttsFile);
            unlink($outputFile);
            rmdir($tempDir);
            
            return [
                'success' => true,
                'audio' => $result,
                'format' => $config['output_format'],
                'duration' => getDuration($outputFile)
            ];
        }
        
        // 3. Procesar con música de fondo
        $musicFile = validateMusicFile($config['music_file']);
        $outputFile = $tempDir . '/jingle.' . $config['output_format'];
        
        // Obtener duración del mensaje
        $voiceDuration = getDuration($ttsFile);
        $totalDuration = $config['intro_silence'] + $voiceDuration + $config['outro_silence'];
        
        logMessage("[JingleService] Duración voz: {$voiceDuration}s, Total: {$totalDuration}s");
        
        // 4. Construir comando ffmpeg para mezcla
        if ($config['music_duck']) {
            // Con ducking automático
            $ffmpegCmd = buildDuckingCommand(
                $musicFile,
                $ttsFile,
                $outputFile,
                $config,
                $voiceDuration,
                $totalDuration
            );
        } else {
            // Mezcla simple sin ducking
            $ffmpegCmd = buildSimpleMixCommand(
                $musicFile,
                $ttsFile,
                $outputFile,
                $config,
                $totalDuration
            );
        }
        
        logMessage("[JingleService] Ejecutando ffmpeg...");
        exec($ffmpegCmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Error mezclando audio: " . implode("\n", $output));
        }
        
        // Leer resultado
        $result = file_get_contents($outputFile);
        
        // Limpiar archivos temporales
        unlink($ttsFile);
        unlink($outputFile);
        rmdir($tempDir);
        
        logMessage("[JingleService] Jingle generado exitosamente");
        
        return [
            'success' => true,
            'audio' => $result,
            'format' => $config['output_format'],
            'duration' => $totalDuration,
            'size' => strlen($result)
        ];
        
    } catch (Exception $e) {
        logMessage("[JingleService] Error: " . $e->getMessage());
        
        // Limpiar en caso de error
        if (isset($tempDir) && is_dir($tempDir)) {
            array_map('unlink', glob("$tempDir/*"));
            rmdir($tempDir);
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Construir comando ffmpeg con ducking
 */
function buildDuckingCommand($musicFile, $voiceFile, $outputFile, $config, $voiceDuration, $totalDuration) {
    // Ducking: reducir música cuando hay voz
    $duckStart = $config['intro_silence'];
    $duckEnd = $duckStart + $voiceDuration;
    
    $cmd = sprintf(
        'ffmpeg -i "%s" -i "%s" -filter_complex ' .
        '"[0:a]atrim=0:%d,afade=t=in:d=%d,afade=t=out:st=%d:d=%d,' .
        'volume=%.2f[music];' .
        '[music]sidechaincompress=threshold=0.1:ratio=4:attack=0.1:release=0.2:detection=peak[ducked];' .
        '[1:a]adelay=%d|%d,volume=%.2f[voice];' .
        '[ducked][voice]amix=inputs=2:duration=longest[out]" ' .
        '-map "[out]" -codec:a libmp3lame -b:a 192k "%s" 2>&1',
        $musicFile,
        $voiceFile,
        $totalDuration,
        $config['fade_in'],
        $totalDuration - $config['fade_out'],
        $config['fade_out'],
        $config['music_volume'],
        $config['intro_silence'] * 1000,
        $config['intro_silence'] * 1000,
        $config['voice_volume'],
        $outputFile
    );
    
    return $cmd;
}

/**
 * Construir comando ffmpeg para mezcla simple
 */
function buildSimpleMixCommand($musicFile, $voiceFile, $outputFile, $config, $totalDuration) {
    $cmd = sprintf(
        'ffmpeg -i "%s" -i "%s" -filter_complex ' .
        '"[0:a]atrim=0:%d,afade=t=in:d=%d,afade=t=out:st=%d:d=%d,volume=%.2f[music];' .
        '[1:a]adelay=%d|%d,volume=%.2f[voice];' .
        '[music][voice]amix=inputs=2:duration=longest[out]" ' .
        '-map "[out]" -codec:a libmp3lame -b:a 192k "%s" 2>&1',
        $musicFile,
        $voiceFile,
        $totalDuration,
        $config['fade_in'],
        $totalDuration - $config['fade_out'],
        $config['fade_out'],
        $config['music_volume'],
        $config['intro_silence'] * 1000,
        $config['intro_silence'] * 1000,
        $config['voice_volume'],
        $outputFile
    );
    
    return $cmd;
}

/**
 * Obtener duración de archivo de audio
 */
function getDuration($file) {
    $cmd = sprintf('ffprobe -v error -show_entries format=duration -of csv=p=0 "%s" 2>&1', $file);
    $duration = trim(shell_exec($cmd));
    return floatval($duration);
}

/**
 * Validar archivo de música
 */
function validateMusicFile($musicPath) {
    // Si es una ruta relativa, buscar en directorio de música
    if (!file_exists($musicPath)) {
        $musicDir = dirname(dirname(__DIR__)) . '/public/audio/music/';
        $fullPath = $musicDir . $musicPath;
        
        if (file_exists($fullPath)) {
            return $fullPath;
        }
        
        throw new Exception("Archivo de música no encontrado: $musicPath");
    }
    
    return $musicPath;
}

/**
 * Obtener lista de música disponible
 */
function getAvailableMusic() {
    $musicDir = dirname(dirname(__DIR__)) . '/public/audio/music/';
    $music = [];
    
    if (is_dir($musicDir)) {
        $files = glob($musicDir . '*.{mp3,wav,ogg}', GLOB_BRACE);
        foreach ($files as $file) {
            $name = basename($file);
            $music[] = [
                'file' => $name,
                'name' => pathinfo($name, PATHINFO_FILENAME),
                'duration' => getDuration($file),
                'size' => filesize($file)
            ];
        }
    }
    
    return $music;
}

// Procesar requests si se llama directamente
if (basename($_SERVER['SCRIPT_NAME']) === 'jingle-service.php') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'generate':
                $text = $input['text'] ?? '';
                $voice = $input['voice'] ?? 'mateo';
                $options = $input['options'] ?? [];
                
                if (empty($text)) {
                    throw new Exception('Texto requerido');
                }
                
                $result = generateJingle($text, $voice, $options);
                
                if ($result['success']) {
                    // Guardar en la base de datos para que aparezca en mensajes recientes
                    try {
                        $dbPath = __DIR__ . '/../database/casa.db';
                        $db = new PDO("sqlite:$dbPath");
                        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Generar nombre de archivo único
                        $timestamp = date('Ymd_His');
                        $filename = "jingle_{$timestamp}_{$voice}.mp3";
                        
                        // Guardar el archivo en el directorio temporal
                        $tempPath = __DIR__ . '/../src/api/temp/' . $filename;
                        if (!file_exists(__DIR__ . '/../src/api/temp')) {
                            mkdir(__DIR__ . '/../src/api/temp', 0777, true);
                        }
                        file_put_contents($tempPath, base64_decode(base64_encode($result['audio'])));
                        
                        // Preparar datos para la base de datos
                        $words = explode(' ', $text);
                        $displayName = implode(' ', array_slice($words, 0, 5));
                        if (count($words) > 5) $displayName .= '...';
                        
                        // Insertar en la base de datos
                        $stmt = $db->prepare("
                            INSERT INTO audio_metadata 
                            (filename, display_name, description, category, is_saved, saved_at, created_at, is_active) 
                            VALUES (?, ?, ?, ?, 0, datetime('now'), datetime('now'), 1)
                        ");
                        
                        $stmt->execute([
                            $filename,
                            $displayName,
                            $text,
                            $input['category'] ?? 'sin_categoria'
                        ]);
                        
                        logMessage("[JingleService] Jingle guardado en BD: " . $filename);
                    } catch (Exception $dbError) {
                        // No fallar si hay error en DB, solo loguear
                        logMessage("[JingleService] Error guardando en BD: " . $dbError->getMessage());
                    }
                    
                    // Devolver audio como base64
                    echo json_encode([
                        'success' => true,
                        'audio' => base64_encode($result['audio']),
                        'format' => $result['format'],
                        'duration' => $result['duration'],
                        'filename' => isset($filename) ? $filename : null
                    ]);
                } else {
                    throw new Exception($result['error']);
                }
                break;
                
            case 'list_music':
                echo json_encode([
                    'success' => true,
                    'music' => getAvailableMusic()
                ]);
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>