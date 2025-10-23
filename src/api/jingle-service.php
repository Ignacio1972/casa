<?php
/**
 * Servicio de Jingles - Mezcla música con mensajes TTS
 * Sistema para crear jingles combinando música de fondo con voces generadas
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/tts-service.php';
require_once __DIR__ . '/services/radio-service.php';

// Incluir AudioProcessor para normalización LUFS si está disponible
$audioProcessorPath = __DIR__ . '/v2/services/AudioProcessor.php';
$audioProcessorAvailable = false;
if (file_exists($audioProcessorPath)) {
    require_once $audioProcessorPath;
    // La clase está en namespace App\Services
    $audioProcessorAvailable = class_exists('\\App\\Services\\AudioProcessor');
    if ($audioProcessorAvailable) {
        logMessage("[JingleService] AudioProcessor cargado correctamente");
    } else {
        logMessage("[JingleService] AudioProcessor no disponible (namespace issue)");
    }
}

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
        
        // Aplicar normalización LUFS si está configurada
        if (isset($config['normalization_settings']) && 
            $config['normalization_settings']['enabled'] && 
            class_exists('\\App\\Services\\AudioProcessor')) {
            
            logMessage("[JingleService] Aplicando normalización LUFS...");
            logMessage("[JingleService] Target LUFS: " . $config['normalization_settings']['target_lufs']);
            logMessage("[JingleService] Modo: " . $config['normalization_settings']['mode']);
            
            try {
                $audioProcessor = new \App\Services\AudioProcessor();
                $normalizedFile = $tempDir . '/voice_normalized.mp3';
                
                // Obtener el target LUFS de la configuración
                $targetLufs = $config['normalization_settings']['target_lufs'] ?? -16;
                
                // Usar el nuevo método normalizeToTarget con target personalizado
                $normResult = $audioProcessor->normalizeToTarget(
                    $ttsFile,
                    $normalizedFile,
                    $targetLufs
                );
                
                if ($normResult && $normResult['success'] && file_exists($normalizedFile)) {
                    // Reemplazar archivo TTS con el normalizado
                    unlink($ttsFile);
                    rename($normalizedFile, $ttsFile);
                    
                    logMessage("[JingleService] Normalización aplicada: " . 
                              ($normResult['metrics']['original']['integrated_lufs'] ?? 'N/A') . 
                              " LUFS -> " . 
                              ($normResult['metrics']['final']['integrated_lufs'] ?? $targetLufs) . 
                              " LUFS");
                } else {
                    logMessage("[JingleService] No se pudo aplicar normalización, usando audio original");
                }
            } catch (\Exception $e) {
                logMessage("[JingleService] Error en normalización: " . $e->getMessage());
                // Continuar sin normalización en caso de error
            }
        }
        
        // 2. Si no hay música, devolver solo el TTS con silencios
        if (empty($config['music_file'])) {
            $outputFile = $tempDir . '/jingle.' . $config['output_format'];
            
            // Agregar silencios al inicio y final
            if ($config['intro_silence'] > 0 || $config['outro_silence'] > 0) {
                // Usar adelay para agregar silencios de forma más simple
                $intro_ms = intval($config['intro_silence'] * 1000);
                $outro_pad_sec = floatval($config['outro_silence']);
                
                // Obtener duración del TTS
                $tts_duration = getDuration($ttsFile);
                $total_duration = $config['intro_silence'] + $tts_duration + $config['outro_silence'];
                
                $ffmpegCmd = sprintf(
                    'ffmpeg -i "%s" -af "adelay=%d|%d,apad=pad_dur=%.1f" ' .
                    '-t %.1f -codec:a libmp3lame -b:a 192k "%s" 2>&1',
                    $ttsFile,
                    $intro_ms,
                    $intro_ms,
                    $outro_pad_sec,
                    $total_duration,
                    $outputFile
                );
            } else {
                // Sin silencios, solo copiar el audio TTS
                $ffmpegCmd = sprintf(
                    'ffmpeg -i "%s" -codec:a libmp3lame -b:a 192k "%s" 2>&1',
                    $ttsFile,
                    $outputFile
                );
            }
            
            logMessage("[JingleService] Comando ffmpeg: " . $ffmpegCmd);
            
            exec($ffmpegCmd, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("Error procesando audio: " . implode("\n", $output));
            }
            
            // Obtener duración antes de leer el archivo
            $duration = getDuration($outputFile);
            $result = file_get_contents($outputFile);
            
            // Limpiar archivos temporales
            unlink($ttsFile);
            unlink($outputFile);
            rmdir($tempDir);
            
            return [
                'success' => true,
                'audio' => $result,
                'format' => $config['output_format'],
                'duration' => $duration
            ];
        }
        
        // 3. Procesar con música de fondo
        $musicFile = validateMusicFile($config['music_file']);
        $outputFile = $tempDir . '/jingle.' . $config['output_format'];
        
        // Obtener duración del mensaje
        $voiceDuration = getDuration($ttsFile);
        
        // Calcular duración total considerando el outro completo
        $voiceEndTime = $config['intro_silence'] + $voiceDuration;
        $totalDuration = $voiceEndTime + $config['outro_silence'];
        
        // Asegurar que el fade out ocurra al final, no cuando termina la voz
        $fadeOutStart = max($voiceEndTime, $totalDuration - $config['fade_out']);
        
        logMessage("[JingleService] Duración voz: {$voiceDuration}s");
        logMessage("[JingleService] Voz termina en: {$voiceEndTime}s");
        logMessage("[JingleService] Duración total: {$totalDuration}s");
        logMessage("[JingleService] Fade out empieza en: {$fadeOutStart}s");
        logMessage("[JingleService] Intro: {$config['intro_silence']}s, Outro: {$config['outro_silence']}s");
        
        // 4. Construir comando ffmpeg para mezcla
        if ($config['music_duck']) {
            // Con ducking automático
            $ffmpegCmd = buildDuckingCommand(
                $musicFile,
                $ttsFile,
                $outputFile,
                $config,
                $voiceDuration,
                $totalDuration,
                $fadeOutStart
            );
        } else {
            // Mezcla simple sin ducking
            $ffmpegCmd = buildSimpleMixCommand(
                $musicFile,
                $ttsFile,
                $outputFile,
                $config,
                $totalDuration,
                $fadeOutStart
            );
        }
        
        logMessage("[JingleService] Ejecutando ffmpeg...");
        logMessage("[JingleService] Comando: " . $ffmpegCmd);
        exec($ffmpegCmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Error mezclando audio: " . implode("\n", $output));
        }
        
        // Obtener duración real del archivo generado
        $actualDuration = getDuration($outputFile);
        
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
            'duration' => $actualDuration,
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
function buildDuckingCommand($musicFile, $voiceFile, $outputFile, $config, $voiceDuration, $totalDuration, $fadeOutStart) {
    // Ducking: reducir música cuando hay voz (pero no completamente)
    $musicVolume = $config['music_volume'];
    $voiceVolume = $config['voice_volume'];
    $introMs = intval($config['intro_silence'] * 1000);
    
    // Obtener configuración del compresor o usar valores por defecto
    $compressor = $config['compressor_settings'] ?? [];
    $compThreshold = $compressor['threshold'] ?? 0.02;
    $compRatio = $compressor['ratio'] ?? 6;
    $compAttack = $compressor['attack'] ?? 5;
    $compRelease = $compressor['release'] ?? 200;
    $compMakeup = $compressor['makeup'] ?? 1;
    $compBypass = $compressor['bypass'] ?? false;
    
    // Si el compresor está desactivado, usar mezcla simple sin sidechain
    if ($compBypass) {
        logMessage("[JingleService] Compresor desactivado - usando mezcla simple");
        // Mezcla simple sin compresión sidechain
        $cmd = sprintf(
            'ffmpeg -i "%s" -i "%s" -filter_complex ' .
            '"[0:a]aloop=loop=-1:size=2e+09,atrim=0:%.1f,volume=%.2f,afade=t=in:d=%.1f,afade=t=out:st=%.1f:d=%.1f[music];' .
            '[1:a]adelay=%d|%d,volume=%.2f,apad=whole_dur=%.1f[voice];' .
            '[music][voice]amix=inputs=2:duration=longest:dropout_transition=3[out]" ' .
            '-map "[out]" -t %.1f -ac 2 -ar 44100 -codec:a libmp3lame -b:a 192k "%s" 2>&1',
            $musicFile,
            $voiceFile,
            $totalDuration,
            $musicVolume,
            $config['fade_in'],
            $fadeOutStart,
            $config['fade_out'],
            $introMs,
            $introMs,
            $voiceVolume,
            $totalDuration,
            $totalDuration,
            $outputFile
        );
    } else {
        // Con compresión sidechain configurada
        logMessage("[JingleService] Usando compresor: threshold=$compThreshold, ratio=$compRatio:1, attack=$compAttack, release=$compRelease, makeup=$compMakeup");
        
        $cmd = sprintf(
            'ffmpeg -i "%s" -i "%s" -filter_complex ' .
            '"[0:a]aloop=loop=-1:size=2e+09,atrim=0:%.1f,volume=%.2f[music_loop];' .
            '[1:a]adelay=%d|%d,volume=%.2f,apad=whole_dur=%.1f[voice_pad];' .
            '[voice_pad]asplit=2[vo][vd];' .
            '[music_loop][vd]sidechaincompress=threshold=%.3f:ratio=%d:attack=%d:release=%d:makeup=%.1f[music_ducked];' .
            '[music_ducked]afade=t=in:d=%.1f,afade=t=out:st=%.1f:d=%.1f[music_final];' .
            '[music_final][vo]amix=inputs=2:duration=longest:dropout_transition=3[out]" ' .
            '-map "[out]" -t %.1f -ac 2 -ar 44100 -codec:a libmp3lame -b:a 192k "%s" 2>&1',
            $musicFile,
            $voiceFile,
            $totalDuration,
            $musicVolume,
            $introMs,
            $introMs,
            $voiceVolume,
            $totalDuration,
            $compThreshold,
            $compRatio,
            $compAttack,
            $compRelease,
            $compMakeup,
            $config['fade_in'],
            $fadeOutStart,
            $config['fade_out'],
            $totalDuration,
            $outputFile
        );
    }
    
    return $cmd;
}

/**
 * Construir comando ffmpeg para mezcla simple
 */
function buildSimpleMixCommand($musicFile, $voiceFile, $outputFile, $config, $totalDuration, $fadeOutStart) {
    $musicVolume = $config['music_volume'];
    $voiceVolume = $config['voice_volume'];
    $introMs = intval($config['intro_silence'] * 1000);
    
    // Sin ducking - usar aloop para asegurar que la música continúe
    $cmd = sprintf(
        'ffmpeg -i "%s" -i "%s" -filter_complex ' .
        '"[0:a]aloop=loop=-1:size=2e+09,atrim=0:%.1f,volume=%.2f,afade=t=in:d=%.1f,afade=t=out:st=%.1f:d=%.1f[music];' .
        '[1:a]adelay=%d|%d,volume=%.2f,apad=whole_dur=%.1f[voice];' .
        '[music][voice]amix=inputs=2:duration=longest:dropout_transition=3[out]" ' .
        '-map "[out]" -t %.1f -ac 2 -ar 44100 -codec:a libmp3lame -b:a 192k "%s" 2>&1',
        $musicFile,
        $voiceFile,
        $totalDuration,
        $musicVolume,
        $config['fade_in'],
        $fadeOutStart,
        $config['fade_out'],
        $introMs,
        $introMs,
        $voiceVolume,
        $totalDuration,
        $totalDuration,
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
function getAvailableMusicList() {
    // Usar el servicio de música para obtener la lista con metadata
    require_once __DIR__ . '/music-service.php';
    return getAvailableMusic();
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
                        $dbPath = __DIR__ . '/../../database/casa.db';
                        $db = new PDO("sqlite:$dbPath");
                        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Generar nombre de archivo único
                        $timestamp = date('Ymd_His');
                        $filename = "jingle_{$timestamp}_{$voice}.mp3";
                        
                        // Guardar el archivo en el directorio temporal
                        $tempPath = __DIR__ . '/temp/' . $filename;
                        if (!file_exists(__DIR__ . '/temp')) {
                            mkdir(__DIR__ . '/temp', 0777, true);
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
                        
                        // Subir a AzuraCast para que esté disponible para la radio
                        try {
                            // Usar el mismo método que uploadExternalFile (JSON con base64)
                            $azuracastUrl = AZURACAST_BASE_URL . '/api/station/' . AZURACAST_STATION_ID . '/files';
                            
                            // Leer archivo y convertir a base64
                            $fileContent = file_get_contents($tempPath);
                            $base64Content = base64_encode($fileContent);
                            
                            // Preparar datos con path completo
                            $azuracastPath = 'Grabaciones/' . $filename;
                            $postData = [
                                'path' => $azuracastPath,
                                'file' => $base64Content
                            ];
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $azuracastUrl);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'X-API-Key: ' . AZURACAST_API_KEY,
                                'Content-Type: application/json'
                            ]);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                            
                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            if ($httpCode === 200 || $httpCode === 201) {
                                logMessage("[JingleService] Jingle subido a AzuraCast exitosamente: " . $filename);
                                
                                // Obtener el ID del archivo desde la respuesta
                                $responseData = json_decode($response, true);
                                if (isset($responseData['id'])) {
                                    $fileId = $responseData['id'];
                                    
                                    // Asignar a playlist usando el ID del archivo
                                    $playlistUrl = AZURACAST_BASE_URL . '/api/station/' . AZURACAST_STATION_ID . '/file/' . $fileId;
                                    
                                    $playlistData = [
                                        'playlists' => [
                                            ['id' => PLAYLIST_ID_GRABACIONES]
                                        ]
                                    ];
                                    
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $playlistUrl);
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($playlistData));
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                        'X-API-Key: ' . AZURACAST_API_KEY,
                                        'Content-Type: application/json'
                                    ]);
                                    
                                    curl_exec($ch);
                                    $playlistHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    
                                    if ($playlistHttpCode === 200) {
                                        logMessage("[JingleService] Jingle agregado a playlist Grabaciones");
                                    } else {
                                        logMessage("[JingleService] No se pudo agregar a playlist (HTTP $playlistHttpCode), pero archivo subido");
                                    }
                                } else {
                                    logMessage("[JingleService] Archivo subido pero no se pudo obtener ID para playlist");
                                }
                            } else {
                                logMessage("[JingleService] Error subiendo jingle a AzuraCast: HTTP " . $httpCode);
                            }
                        } catch (Exception $azuraError) {
                            logMessage("[JingleService] Error con AzuraCast: " . $azuraError->getMessage());
                            // No fallar, continuar sin AzuraCast
                        }
                        
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
                $musicList = getAvailableMusicList();
                echo json_encode([
                    'success' => true,
                    'music' => $musicList
                ]);
                break;
                
            case 'send_to_radio':
                // Enviar jingle a la radio
                $filename = $input['filename'] ?? '';
                
                if (empty($filename)) {
                    throw new Exception('No se especificó el archivo a enviar');
                }
                
                logMessage("[JingleService] Enviando jingle a radio: $filename");
                
                // Usar la función de radio-service.php
                $success = interruptRadio($filename);
                
                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Jingle enviado a la radio y reproduciéndose'
                    ]);
                } else {
                    throw new Exception('Error al interrumpir la radio con el jingle');
                }
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