<?php
/**
 * Test del sistema de ajuste de volumen por voz
 */

// Incluir el servicio TTS
require_once __DIR__ . '/src/api/services/tts-service-unified.php';

// Colores para output
$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$reset = "\033[0m";

echo "\n{$yellow}=== TEST DE AJUSTE DE VOLUMEN POR VOZ ==={$reset}\n\n";

// 1. Verificar configuración de voces
echo "1. Verificando configuración de voces...\n";
$voicesFile = __DIR__ . '/src/api/data/voices-config.json';
if (!file_exists($voicesFile)) {
    echo "{$red}ERROR: No existe el archivo voices-config.json{$reset}\n";
    exit(1);
}

$config = json_decode(file_get_contents($voicesFile), true);
if (!$config) {
    echo "{$red}ERROR: No se pudo leer voices-config.json{$reset}\n";
    exit(1);
}

echo "{$green}✓ Archivo de configuración encontrado{$reset}\n";
echo "  Voces totales: " . count($config['voices']) . "\n";

// Mostrar voces con ajuste de volumen
echo "\n2. Voces con ajuste de volumen configurado:\n";
$hasVolumeAdjustment = false;
foreach ($config['voices'] as $key => $voice) {
    if (isset($voice['volume_adjustment']) && $voice['volume_adjustment'] != 0) {
        echo "  - {$voice['label']} ({$key}): {$voice['volume_adjustment']} dB\n";
        $hasVolumeAdjustment = true;
    }
}

if (!$hasVolumeAdjustment) {
    echo "{$yellow}  Ninguna voz tiene ajuste de volumen configurado (todas en 0 dB){$reset}\n";
}

// 3. Verificar FFmpeg
echo "\n3. Verificando FFmpeg...\n";
exec('which ffmpeg 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "{$green}✓ FFmpeg está instalado{$reset}\n";
    exec('ffmpeg -version 2>&1', $versionOutput);
    echo "  Versión: " . $versionOutput[0] . "\n";
} else {
    echo "{$red}ERROR: FFmpeg no está instalado{$reset}\n";
    echo "  Instala con: sudo apt-get install ffmpeg\n";
    exit(1);
}

// 4. Test de generación con volumen ajustado
echo "\n4. Test de generación TTS con ajuste de volumen:\n";
echo "  Selecciona una voz para probar:\n";
$activeVoices = [];
foreach ($config['voices'] as $key => $voice) {
    if ($voice['active']) {
        $activeVoices[] = $key;
        echo "  - {$key}: {$voice['label']} (Volumen: " . ($voice['volume_adjustment'] ?? 0) . " dB)\n";
    }
}

if (empty($activeVoices)) {
    echo "{$red}ERROR: No hay voces activas{$reset}\n";
    exit(1);
}

// Usar la primera voz activa para la prueba
$testVoice = $activeVoices[0];
$testText = "Este es un mensaje de prueba del sistema de ajuste de volumen.";

echo "\n  Generando audio con voz '{$testVoice}'...\n";

try {
    // Generar audio
    $audio = generateTTS($testText, $testVoice);
    
    if ($audio) {
        // Guardar archivo de prueba
        $outputFile = __DIR__ . '/test-volume-' . $testVoice . '-' . time() . '.mp3';
        file_put_contents($outputFile, $audio);
        
        echo "{$green}✓ Audio generado exitosamente{$reset}\n";
        echo "  Archivo guardado en: {$outputFile}\n";
        echo "  Tamaño: " . number_format(strlen($audio) / 1024, 2) . " KB\n";
        
        // Verificar el archivo con FFprobe
        exec("ffprobe -i {$outputFile} -show_format -v quiet -print_format json", $probeOutput);
        $audioInfo = json_decode(implode('', $probeOutput), true);
        
        if ($audioInfo && isset($audioInfo['format'])) {
            echo "  Duración: " . round($audioInfo['format']['duration'], 2) . " segundos\n";
            echo "  Bitrate: " . round($audioInfo['format']['bit_rate'] / 1000) . " kbps\n";
        }
    } else {
        echo "{$red}ERROR: No se pudo generar el audio{$reset}\n";
    }
} catch (Exception $e) {
    echo "{$red}ERROR: " . $e->getMessage() . "{$reset}\n";
}

// 5. Verificar logs
echo "\n5. Últimas líneas del log TTS:\n";
$logFile = __DIR__ . '/src/api/logs/tts-' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    $logLines = file($logFile);
    $lastLines = array_slice($logLines, -5);
    foreach ($lastLines as $line) {
        echo "  " . trim($line) . "\n";
    }
} else {
    echo "{$yellow}  No se encontró archivo de log para hoy{$reset}\n";
}

echo "\n{$green}=== TEST COMPLETADO ==={$reset}\n";
echo "\nPara ajustar el volumen de una voz:\n";
echo "1. Ve a /playground/ > Admin Voces\n";
echo "2. Usa el slider de volumen (-20 a +20 dB)\n";
echo "3. El cambio se aplicará automáticamente a todos los audios generados\n\n";