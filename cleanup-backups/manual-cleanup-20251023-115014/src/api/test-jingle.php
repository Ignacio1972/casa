<?php
/**
 * Script de prueba para el mezclado de jingles sin depender de ElevenLabs
 */

require_once __DIR__ . '/config.php';

// Función de logging
function logMessage($message) {
    error_log("[TestJingle] " . $message);
}

/**
 * Generar un jingle de prueba con audio sintético
 */
function generateTestJingle($options = []) {
    try {
        // Opciones por defecto
        $defaults = [
            'music_file' => 'Martin Roth - Just Sine Waves.mp3',
            'music_volume' => 0.4,
            'voice_volume' => 1.0,
            'fade_in' => 2,
            'fade_out' => 3,
            'music_duck' => false,
            'intro_silence' => 2,
            'outro_silence' => 5,
            'voice_duration' => 3  // Duración simulada de la voz
        ];
        
        $config = array_merge($defaults, $options);
        
        logMessage("Config: " . json_encode($config));
        
        // Crear directorio temporal
        $tempDir = sys_get_temp_dir() . '/test_jingles_' . uniqid();
        if (!mkdir($tempDir, 0777, true)) {
            throw new Exception("No se pudo crear directorio temporal");
        }
        
        // Generar audio de voz sintética (tono de prueba)
        $voiceFile = $tempDir . '/voice.mp3';
        $voiceDuration = $config['voice_duration'];
        
        // Generar tono de 440Hz como voz de prueba
        $toneCmd = sprintf(
            'ffmpeg -f lavfi -i "sine=frequency=440:duration=%.1f" ' .
            '-codec:a libmp3lame -b:a 128k "%s" 2>&1',
            $voiceDuration,
            $voiceFile
        );
        
        logMessage("Generando voz de prueba: " . $toneCmd);
        exec($toneCmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Error generando voz: " . implode("\n", $output));
        }
        
        // Validar archivo de música
        $musicDir = dirname(dirname(__DIR__)) . '/public/audio/music/';
        $musicFile = $musicDir . $config['music_file'];
        
        if (!file_exists($musicFile)) {
            throw new Exception("Archivo de música no encontrado: " . $musicFile);
        }
        
        // Calcular duraciones
        $voiceEndTime = $config['intro_silence'] + $voiceDuration;
        $totalDuration = $voiceEndTime + $config['outro_silence'];
        $fadeOutStart = $totalDuration - $config['fade_out'];
        
        logMessage("Duración voz: {$voiceDuration}s");
        logMessage("Voz termina en: {$voiceEndTime}s");
        logMessage("Duración total: {$totalDuration}s");
        logMessage("Fade out empieza en: {$fadeOutStart}s");
        
        // Archivo de salida
        $outputFile = $tempDir . '/jingle.mp3';
        
        // Construir comando ffmpeg mejorado
        $introMs = intval($config['intro_silence'] * 1000);
        
        if ($config['music_duck']) {
            // Con ducking
            $ffmpegCmd = sprintf(
                'ffmpeg -i "%s" -i "%s" -filter_complex ' .
                '"[0:a]aloop=loop=-1:size=2e+09,atrim=0:%.1f,volume=%.2f[music_loop];' .
                '[1:a]adelay=%d|%d,volume=%.2f,apad=whole_dur=%.1f[voice_pad];' .
                '[voice_pad]asplit=2[vo][vd];' .
                '[music_loop][vd]sidechaincompress=threshold=0.02:ratio=6:attack=5:release=200:makeup=1[music_ducked];' .
                '[music_ducked]afade=t=in:d=%.1f,afade=t=out:st=%.1f:d=%.1f[music_final];' .
                '[music_final][vo]amix=inputs=2:duration=longest:dropout_transition=3[out]" ' .
                '-map "[out]" -t %.1f -ac 2 -ar 44100 -codec:a libmp3lame -b:a 192k "%s" 2>&1',
                $musicFile,
                $voiceFile,
                $totalDuration,
                $config['music_volume'] * 0.6,
                $introMs,
                $introMs,
                $config['voice_volume'],
                $totalDuration,
                $config['fade_in'],
                $fadeOutStart,
                $config['fade_out'],
                $totalDuration,
                $outputFile
            );
        } else {
            // Sin ducking
            $ffmpegCmd = sprintf(
                'ffmpeg -i "%s" -i "%s" -filter_complex ' .
                '"[0:a]aloop=loop=-1:size=2e+09,atrim=0:%.1f,volume=%.2f,afade=t=in:d=%.1f,afade=t=out:st=%.1f:d=%.1f[music];' .
                '[1:a]adelay=%d|%d,volume=%.2f,apad=whole_dur=%.1f[voice];' .
                '[music][voice]amix=inputs=2:duration=longest:dropout_transition=3[out]" ' .
                '-map "[out]" -t %.1f -ac 2 -ar 44100 -codec:a libmp3lame -b:a 192k "%s" 2>&1',
                $musicFile,
                $voiceFile,
                $totalDuration,
                $config['music_volume'],
                $config['fade_in'],
                $fadeOutStart,
                $config['fade_out'],
                $introMs,
                $introMs,
                $config['voice_volume'],
                $totalDuration,
                $totalDuration,
                $outputFile
            );
        }
        
        logMessage("Ejecutando ffmpeg...");
        logMessage("Comando: " . $ffmpegCmd);
        exec($ffmpegCmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Error mezclando audio: " . implode("\n", $output));
        }
        
        // Verificar duración del archivo generado
        $actualDuration = floatval(trim(shell_exec(sprintf(
            'ffprobe -v error -show_entries format=duration -of csv=p=0 "%s" 2>&1',
            $outputFile
        ))));
        
        logMessage("Duración real del jingle: {$actualDuration}s");
        
        // Leer resultado
        $result = file_get_contents($outputFile);
        
        // Limpiar archivos temporales
        unlink($voiceFile);
        unlink($outputFile);
        rmdir($tempDir);
        
        return [
            'success' => true,
            'audio' => $result,
            'format' => 'mp3',
            'duration' => $actualDuration,
            'expected_duration' => $totalDuration,
            'size' => strlen($result)
        ];
        
    } catch (Exception $e) {
        logMessage("Error: " . $e->getMessage());
        
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

// Procesar request
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
    
    $result = generateTestJingle($input);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'audio' => base64_encode($result['audio']),
            'format' => $result['format'],
            'duration' => $result['duration'],
            'expected_duration' => $result['expected_duration']
        ]);
    } else {
        throw new Exception($result['error']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>