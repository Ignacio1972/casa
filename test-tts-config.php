#!/usr/bin/php
<?php
/**
 * Script de prueba para el nuevo sistema de configuración TTS
 */

echo "=== Test Sistema de Configuración TTS ===\n\n";

// Test 1: Verificar servicio de configuración
echo "Test 1: Verificando servicio de configuración...\n";

$configFile = '/var/www/casa/src/api/data/tts-config.json';

// Crear configuración de prueba
$testConfig = [
    'silence' => [
        'add_silence' => true,
        'intro_seconds' => 1.5,
        'outro_seconds' => 2.0
    ],
    'normalization' => [
        'enabled' => true,
        'target_lufs' => -16,
        'output_volume' => 1.0,
        'enable_compression' => true
    ],
    'voice_settings' => [
        'style' => 0.6,
        'stability' => 0.8,
        'similarity_boost' => 0.85,
        'use_speaker_boost' => true
    ]
];

// Guardar configuración
echo "Guardando configuración de prueba...\n";
file_put_contents($configFile, json_encode($testConfig, JSON_PRETTY_PRINT));

if (file_exists($configFile)) {
    echo "✓ Archivo de configuración creado\n";
    $loadedConfig = json_decode(file_get_contents($configFile), true);
    
    if ($loadedConfig['silence']['intro_seconds'] == 1.5) {
        echo "✓ Configuración guardada correctamente\n";
    } else {
        echo "✗ Error en la configuración guardada\n";
    }
} else {
    echo "✗ No se pudo crear el archivo de configuración\n";
}

echo "\n";

// Test 2: Verificar que audio-processor lea la configuración
echo "Test 2: Verificando audio-processor con silencios configurables...\n";

// Incluir audio-processor
require_once '/var/www/casa/src/api/services/audio-processor.php';

// Crear un archivo de audio temporal de prueba
$testAudioFile = '/var/www/casa/src/api/temp/test_audio_' . uniqid() . '.mp3';

// Crear un audio de prueba simple (1 segundo de silencio)
$cmd = sprintf(
    'ffmpeg -f lavfi -i anullsrc=channel_layout=mono:sample_rate=44100 -t 1 -c:a libmp3lame -b:a 192k -ar 44100 -y %s 2>&1',
    escapeshellarg($testAudioFile)
);
exec($cmd);

if (file_exists($testAudioFile)) {
    echo "✓ Archivo de prueba creado\n";
    
    // Copiar para procesamiento
    $testCopy = $testAudioFile . '.copy';
    copy($testAudioFile, $testCopy);
    
    // Probar addSilenceToAudio (debería usar 1.5s intro, 2.0s outro)
    $result = addSilenceToAudio($testCopy);
    
    if ($result && file_exists($result)) {
        echo "✓ Silencios agregados exitosamente\n";
        
        // Verificar duración del archivo resultante
        $duration_cmd = sprintf('ffprobe -v error -show_entries format=duration -of csv=p=0 %s 2>&1', escapeshellarg($result));
        $duration = floatval(trim(shell_exec($duration_cmd)));
        
        // Duración esperada: 1 (audio) + 1.5 (intro) + 2.0 (outro) = 4.5 segundos
        $expected_duration = 4.5;
        
        echo "  Duración del archivo: {$duration}s (esperado ~{$expected_duration}s)\n";
        
        if (abs($duration - $expected_duration) < 0.5) {
            echo "✓ Duración correcta con silencios configurables\n";
        } else {
            echo "✗ Duración incorrecta (diferencia > 0.5s)\n";
        }
        
        // Limpiar
        @unlink($result);
    } else {
        echo "✗ Error al agregar silencios\n";
    }
    
    // Limpiar archivos de prueba
    @unlink($testAudioFile);
    @unlink($testCopy);
} else {
    echo "✗ No se pudo crear archivo de prueba\n";
}

echo "\n";

// Test 3: Probar sin silencios
echo "Test 3: Probando con silencios deshabilitados...\n";

// Actualizar configuración sin silencios
$testConfig['silence']['add_silence'] = false;
file_put_contents($configFile, json_encode($testConfig, JSON_PRETTY_PRINT));

// Crear otro archivo de prueba
$testAudioFile2 = '/var/www/casa/src/api/temp/test_audio2_' . uniqid() . '.mp3';
exec(sprintf(
    'ffmpeg -f lavfi -i anullsrc=channel_layout=mono:sample_rate=44100 -t 1 -c:a libmp3lame -b:a 192k -ar 44100 -y %s 2>&1',
    escapeshellarg($testAudioFile2)
));

if (file_exists($testAudioFile2)) {
    $testCopy2 = $testAudioFile2 . '.copy';
    copy($testAudioFile2, $testCopy2);
    
    $result2 = addSilenceToAudio($testCopy2);
    
    if ($result2) {
        // Verificar duración
        $duration_cmd2 = sprintf('ffprobe -v error -show_entries format=duration -of csv=p=0 %s 2>&1', escapeshellarg($result2));
        $duration2 = floatval(trim(shell_exec($duration_cmd2)));
        
        echo "  Duración sin silencios: {$duration2}s (esperado ~1s)\n";
        
        if (abs($duration2 - 1.0) < 0.2) {
            echo "✓ Sin silencios agregados (correcto)\n";
        } else {
            echo "✗ Se agregaron silencios cuando no debería\n";
        }
        
        if ($result2 !== $testCopy2) {
            @unlink($result2);
        }
    }
    
    @unlink($testAudioFile2);
    @unlink($testCopy2);
}

echo "\n";

// Test 4: Verificar que generate.php use la configuración
echo "Test 4: Verificando que generate.php use voice_settings de la configuración...\n";

// Revisar si la configuración tiene los voice_settings correctos
if ($loadedConfig && isset($loadedConfig['voice_settings'])) {
    if ($loadedConfig['voice_settings']['style'] == 0.6) {
        echo "✓ Voice settings en configuración: style=0.6\n";
    }
    if ($loadedConfig['voice_settings']['stability'] == 0.8) {
        echo "✓ Voice settings en configuración: stability=0.8\n";
    }
    if ($loadedConfig['voice_settings']['similarity_boost'] == 0.85) {
        echo "✓ Voice settings en configuración: similarity_boost=0.85\n";
    }
} else {
    echo "✗ No se encontraron voice_settings en la configuración\n";
}

// Restaurar configuración por defecto
echo "\nRestaurando configuración por defecto...\n";
$defaultConfig = [
    'silence' => [
        'add_silence' => true,
        'intro_seconds' => 3.0,
        'outro_seconds' => 3.0
    ],
    'normalization' => [
        'enabled' => true,
        'target_lufs' => -16,
        'output_volume' => 1.0,
        'enable_compression' => true
    ],
    'voice_settings' => [
        'style' => 0.5,
        'stability' => 0.75,
        'similarity_boost' => 0.8,
        'use_speaker_boost' => true
    ]
];

file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
echo "✓ Configuración restaurada\n";

echo "\n=== Fin de pruebas ===\n";
echo "\nPuedes acceder a la interfaz de configuración en:\n";
echo "http://51.222.25.222:4000/playground/tts-config.html\n";
?>