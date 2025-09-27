<?php
/**
 * Servicio de Procesamiento de Audio - VERSIÓN CORREGIDA
 * FIX: Mantiene características exactas del audio original (MONO, bitrate constante)
 */

// Función de logging si no existe
if (!function_exists('logMessage')) {
    function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logFile = dirname(__DIR__) . '/logs/tts-' . date('Y-m-d') . '.log';
        
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    }
}

// Definir UPLOAD_DIR si no existe
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', dirname(__DIR__) . '/temp/');
}

/**
 * Agrega silencios configurables antes y después del audio
 * FIX: Preserva formato original (mono/stereo, bitrate, sample rate)
 * @param string $inputFile Archivo de entrada
 * @param float $introSeconds Segundos de silencio al inicio (opcional, default desde config)
 * @param float $outroSeconds Segundos de silencio al final (opcional, default desde config)
 */
function addSilenceToAudio($inputFile, $introSeconds = null, $outroSeconds = null) {
    try {
        // Si no se especifican los silencios, leer de la configuración
        if ($introSeconds === null || $outroSeconds === null) {
            $configFile = dirname(__DIR__) . '/data/tts-config.json';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                if ($config && isset($config['silence'])) {
                    // Solo aplicar silencios si está habilitado
                    if ($config['silence']['add_silence'] ?? true) {
                        $introSeconds = $introSeconds ?? ($config['silence']['intro_seconds'] ?? 3);
                        $outroSeconds = $outroSeconds ?? ($config['silence']['outro_seconds'] ?? 3);
                    } else {
                        // Si los silencios están deshabilitados, usar 0
                        $introSeconds = 0;
                        $outroSeconds = 0;
                    }
                }
            }
            // Valores por defecto si no hay configuración
            $introSeconds = $introSeconds ?? 3;
            $outroSeconds = $outroSeconds ?? 3;
        }
        
        logMessage("=== Iniciando addSilenceToAudio con archivo: $inputFile");
        logMessage("Silencios configurados - Intro: {$introSeconds}s, Outro: {$outroSeconds}s");
        
        // Si ambos silencios son 0, devolver el archivo sin modificar
        if ($introSeconds == 0 && $outroSeconds == 0) {
            logMessage("No se agregarán silencios (ambos configurados en 0)");
            return $inputFile;
        }
        
        // Verificar que el archivo existe
        if (!file_exists($inputFile)) {
            logMessage("ERROR: Archivo no existe: $inputFile");
            return false;
        }
        
        // FIX: Analizar el archivo original para preservar sus características
        $probe_cmd = sprintf('ffprobe -v quiet -print_format json -show_streams %s', escapeshellarg($inputFile));
        $probe_output = shell_exec($probe_cmd);
        $audio_info = json_decode($probe_output, true);
        
        if (!$audio_info || !isset($audio_info['streams'][0])) {
            logMessage("ERROR: No se pudo analizar el archivo original");
            return false;
        }
        
        $stream = $audio_info['streams'][0];
        $channels = $stream['channels'];
        $sample_rate = $stream['sample_rate'];
        // Preservar el bitrate original (ahora será 192kbps con el nuevo formato)
        $bit_rate = $stream['bit_rate'] ?? '192000';
        
        logMessage("Archivo original - Channels: $channels, Sample Rate: $sample_rate, Bitrate: $bit_rate");
        
        // Crear archivos temporales
        $silenceFile = UPLOAD_DIR . 'silence_' . uniqid() . '.mp3';
        $outputFile = str_replace('.mp3.copy', '_with_silence.mp3', $inputFile);
        
        // FIX: Crear silencio con EXACTAMENTE las mismas características
        $channel_layout = ($channels == 1) ? 'mono' : 'stereo';
        
        // Crear silencio con duración configurable (intro)
        $silenceDuration = max($introSeconds, $outroSeconds); // Crear un solo archivo de silencio del máximo necesario
        $cmdSilence = sprintf(
            'ffmpeg -f lavfi -i anullsrc=channel_layout=%s:sample_rate=%s -t %.1f -c:a libmp3lame -b:a %s -ac %d -ar %s -y %s 2>&1',
            $channel_layout,
            $sample_rate,
            $silenceDuration,
            $bit_rate,
            $channels,
            $sample_rate,
            escapeshellarg($silenceFile)
        );
        
        logMessage("Ejecutando comando de silencio: $cmdSilence");
        $result = shell_exec($cmdSilence);
        
        if (!file_exists($silenceFile)) {
            logMessage("ERROR: No se pudo crear archivo de silencio");
            logMessage("Output: $result");
            return false;
        }
        
        logMessage("Archivo de silencio creado: $silenceFile");
        
        // FIX: Concatenar preservando características del archivo original
        $listFile = UPLOAD_DIR . 'concat_' . uniqid() . '.txt';
        
        // Crear lista de archivos con silencios configurables
        $fileList = "";
        
        // Agregar silencio inicial si es necesario
        if ($introSeconds > 0) {
            // Si necesitamos diferentes duraciones, crear archivos separados
            if ($introSeconds != $outroSeconds && $outroSeconds > 0) {
                $introSilenceFile = UPLOAD_DIR . 'silence_intro_' . uniqid() . '.mp3';
                $cmdIntroSilence = sprintf(
                    'ffmpeg -f lavfi -i anullsrc=channel_layout=%s:sample_rate=%s -t %.1f -c:a libmp3lame -b:a %s -ac %d -ar %s -y %s 2>&1',
                    $channel_layout,
                    $sample_rate,
                    $introSeconds,
                    $bit_rate,
                    $channels,
                    $sample_rate,
                    escapeshellarg($introSilenceFile)
                );
                shell_exec($cmdIntroSilence);
                $fileList .= "file '" . $introSilenceFile . "'\n";
            } else {
                $fileList .= "file '" . $silenceFile . "'\n";
            }
        }
        
        // Agregar archivo de audio principal
        $fileList .= "file '" . $inputFile . "'\n";
        
        // Agregar silencio final si es necesario
        if ($outroSeconds > 0) {
            if ($introSeconds != $outroSeconds && $introSeconds > 0) {
                $outroSilenceFile = UPLOAD_DIR . 'silence_outro_' . uniqid() . '.mp3';
                $cmdOutroSilence = sprintf(
                    'ffmpeg -f lavfi -i anullsrc=channel_layout=%s:sample_rate=%s -t %.1f -c:a libmp3lame -b:a %s -ac %d -ar %s -y %s 2>&1',
                    $channel_layout,
                    $sample_rate,
                    $outroSeconds,
                    $bit_rate,
                    $channels,
                    $sample_rate,
                    escapeshellarg($outroSilenceFile)
                );
                shell_exec($cmdOutroSilence);
                $fileList .= "file '" . $outroSilenceFile . "'";
            } else {
                $fileList .= "file '" . $silenceFile . "'";
            }
        }
        
        file_put_contents($listFile, $fileList);
        
        logMessage("Archivo de lista creado: $listFile");
        
        // FIX: Concatenar con parámetros que preservan el formato original
        $cmdConcat = sprintf(
            'ffmpeg -f concat -safe 0 -i %s -c:a libmp3lame -b:a %s -ac %d -ar %s -y %s 2>&1',
            escapeshellarg($listFile),
            $bit_rate,
            $channels,
            $sample_rate,
            escapeshellarg($outputFile)
        );
        
        logMessage("Ejecutando concat: $cmdConcat");
        $result2 = shell_exec($cmdConcat);
        
        // Limpiar archivos temporales
        @unlink($silenceFile);
        @unlink($listFile);
        // Limpiar archivos de silencio adicionales si se crearon
        if (isset($introSilenceFile)) @unlink($introSilenceFile);
        if (isset($outroSilenceFile)) @unlink($outroSilenceFile);
        
        // Verificar resultado
        if (file_exists($outputFile) && filesize($outputFile) > 0) {
            // FIX: Verificar que el archivo final mantiene las características
            $final_probe = shell_exec(sprintf('ffprobe -v quiet -print_format json -show_streams %s', escapeshellarg($outputFile)));
            $final_info = json_decode($final_probe, true);
            
            if ($final_info && isset($final_info['streams'][0])) {
                $final_stream = $final_info['streams'][0];
                logMessage("Archivo final - Channels: {$final_stream['channels']}, Sample Rate: {$final_stream['sample_rate']}");
                
                // Verificar que se mantuvieron las características
                if ($final_stream['channels'] == $channels && $final_stream['sample_rate'] == $sample_rate) {
                    logMessage("✅ Silencio agregado exitosamente manteniendo formato original. Tamaño: " . filesize($outputFile) . " bytes");
                    return $outputFile;
                } else {
                    logMessage("⚠️ ADVERTENCIA: El archivo final cambió de formato");
                }
            }
            
            logMessage("Silencio agregado. Tamaño: " . filesize($outputFile) . " bytes");
            return $outputFile;
        } else {
            logMessage("ERROR: No se creó el archivo con silencio");
            logMessage("Output concat: " . $result2);
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("ERROR en addSilenceToAudio: " . $e->getMessage());
        return false;
    }
}

/**
 * Guarda archivo de audio temporal
 */
function saveAudioFile($audioData, $prefix = 'tts') {
    $timestamp = date('YmdHis');
    $filename = $prefix . $timestamp . '.mp3';
    $filepath = UPLOAD_DIR . $filename;
    
    if (file_put_contents($filepath, $audioData) === false) {
        throw new Exception('Error al guardar archivo temporal');
    }
    
    logMessage("Archivo temporal creado: $filename");
    return $filepath;
}

/**
 * Copia archivo para procesamiento
 */
function copyFileForProcessing($filepath) {
    $copyPath = $filepath . '.copy';
    if (!copy($filepath, $copyPath)) {
        throw new Exception('Error al copiar archivo para procesamiento');
    }
    return $copyPath;
}
?>