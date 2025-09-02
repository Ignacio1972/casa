<?php
/**
 * Servicio de Procesamiento de Audio - VERSIÓN CORREGIDA
 * FIX: Mantiene características exactas del audio original (MONO, bitrate constante)
 */

/**
 * Agrega 3 segundos de silencio antes y después del audio
 * FIX: Preserva formato original (mono/stereo, bitrate, sample rate)
 */
function addSilenceToAudio($inputFile) {
    try {
        logMessage("=== Iniciando addSilenceToAudio con archivo: $inputFile");
        
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
        $bit_rate = $stream['bit_rate'];
        
        logMessage("Archivo original - Channels: $channels, Sample Rate: $sample_rate, Bitrate: $bit_rate");
        
        // Crear archivos temporales
        $silenceFile = UPLOAD_DIR . 'silence_' . uniqid() . '.mp3';
        $outputFile = str_replace('.mp3.copy', '_with_silence.mp3', $inputFile);
        
        // FIX: Crear silencio con EXACTAMENTE las mismas características
        $channel_layout = ($channels == 1) ? 'mono' : 'stereo';
        
        $cmdSilence = sprintf(
            'ffmpeg -f lavfi -i anullsrc=channel_layout=%s:sample_rate=%s -t 3 -c:a libmp3lame -b:a %s -ac %d -ar %s -y %s 2>&1',
            $channel_layout,
            $sample_rate,
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
        $fileList = "file '" . $silenceFile . "'\nfile '" . $inputFile . "'\nfile '" . $silenceFile . "'";
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