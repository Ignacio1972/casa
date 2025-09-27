<?php
// Prueba final del sistema con 192kbps

require_once __DIR__ . '/src/api/config.php';

// Función de log temporal
function logMessage($msg) {
    echo "[LOG] $msg\n";
}

require_once __DIR__ . '/src/api/services/tts-service-enhanced.php';

echo "=== PRUEBA FINAL 192kbps ===\n\n";

// Generar audio con el sistema actualizado
$text = "Prueba final de audio en alta calidad ciento noventa y dos kilobits por segundo";
$voice = "G4IAP30yc6c1gK0csDfu"; // Juan Carlos

echo "Generando audio...\n";
$audioData = generateEnhancedTTS($text, $voice, [
    'model_id' => 'eleven_multilingual_v2'
]);

if ($audioData) {
    $filename = '/tmp/test_final_192_' . time() . '.mp3';
    file_put_contents($filename, $audioData);
    echo "✅ Audio generado! Tamaño: " . number_format(strlen($audioData)) . " bytes\n";
    
    // Analizar con ffprobe
    $probe = shell_exec("ffprobe -v quiet -print_format json -show_streams $filename 2>&1");
    $info = json_decode($probe, true);
    
    if ($info && isset($info['streams'][0])) {
        $stream = $info['streams'][0];
        $bitrate = intval($stream['bit_rate'] ?? 0);
        $kbps = round($bitrate/1000);
        
        echo "\n📊 ANÁLISIS DEL AUDIO:\n";
        echo "========================\n";
        echo "🎯 Bitrate: $bitrate bps ($kbps kbps) ";
        
        if ($kbps >= 192) {
            echo "✅ ¡ALTA CALIDAD 192kbps CONFIRMADA!\n";
        } else if ($kbps == 128) {
            echo "❌ Sigue en 128kbps\n";
        }
        
        echo "🔊 Canales: " . $stream['channels'];
        echo ($stream['channels'] == 2) ? " (ESTÉREO)\n" : " (MONO)\n";
        echo "📻 Sample Rate: " . $stream['sample_rate'] . " Hz\n";
        echo "💾 Archivo guardado: $filename\n";
    }
} else {
    echo "❌ Error generando audio\n";
}

// Verificar logs
echo "\n=== ÚLTIMAS LÍNEAS DEL LOG ===\n";
$logFile = '/var/www/casa/src/api/logs/tts-' . date('Y-m-d') . '.log';
$lastLines = shell_exec("tail -n 10 $logFile | grep -E 'ALTA CALIDAD|192kbps|Output format|URL final' 2>/dev/null");
if ($lastLines) {
    echo $lastLines;
} else {
    echo "No se encontraron logs de alta calidad\n";
}