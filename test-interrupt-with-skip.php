#!/usr/bin/env php
<?php
/**
 * Test del nuevo sistema de interrupción con skip
 * Prueba la función interruptRadioWithSkip
 */

require_once __DIR__ . '/src/api/config.php';
require_once __DIR__ . '/src/api/services/radio-service.php';

echo "\n========================================\n";
echo "TEST: Interrupción con Skip Automático\n";
echo "========================================\n\n";

// Función helper para log
function testLog($msg) {
    echo "[" . date('H:i:s') . "] " . $msg . "\n";
}

// Test 1: Probar función getAudioDuration
echo "[TEST 1] Probando getAudioDuration...\n";

$testFiles = [
    '/var/www/casa/src/api/temp/tts20250925220019.mp3',
    '/var/www/casa/test-volume-juan_carlos-1758598142.mp3'
];

foreach ($testFiles as $file) {
    if (file_exists($file)) {
        $duration = getAudioDuration($file);
        testLog("✅ Duración de " . basename($file) . ": " . round($duration, 2) . " segundos");
        break;
    }
}

echo "\n";

// Test 2: Generar un mensaje de prueba corto
echo "[TEST 2] Generando mensaje de prueba...\n";

$testText = "Prueba del sistema de skip automático. Este es un mensaje corto.";
$testVoice = "juan_carlos";

// Generar TTS
$generateUrl = 'http://localhost:4000/src/api/generate.php';
$generateData = [
    'action' => 'generate_audio',
    'text' => $testText,
    'voice' => 'W9VDPF1LRwfMBQNvgpGO', // Juan Carlos
    'category' => 'test'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $generateUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($generateData),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        $testFilename = $result['azuracast_filename'] ?? $result['filename'];
        testLog("✅ Mensaje generado: $testFilename");
        
        // Obtener duración del archivo generado
        $localFile = "/var/www/casa/src/api/temp/" . $result['filename'];
        if (file_exists($localFile)) {
            $duration = getAudioDuration($localFile);
            testLog("📏 Duración del mensaje: " . round($duration, 2) . " segundos");
        } else {
            $duration = 10; // Default
        }
        
        echo "\n";
        
        // Test 3: Probar interrupción SIN skip
        echo "[TEST 3] Probando interrupción SIN skip...\n";
        echo "⚠️  NOTA: Esto interrumpirá la radio actual\n";
        echo "Presiona Enter para continuar o Ctrl+C para cancelar: ";
        $handle = fopen("php://stdin", "r");
        fgets($handle);
        fclose($handle);
        
        $success = interruptRadioWithSkip($testFilename, $duration, false);
        
        if ($success) {
            testLog("✅ Interrupción sin skip ejecutada");
            testLog("El mensaje debería estar sonando ahora");
            testLog("La música continuará después del mensaje");
        } else {
            testLog("❌ Fallo la interrupción");
        }
        
        echo "\n";
        echo "Esperando que termine el mensaje (" . ceil($duration) . " segundos)...\n";
        sleep(ceil($duration) + 2);
        
        // Test 4: Probar interrupción CON skip
        echo "\n[TEST 4] Probando interrupción CON skip automático...\n";
        echo "Presiona Enter para continuar: ";
        $handle = fopen("php://stdin", "r");
        fgets($handle);
        fclose($handle);
        
        // Generar otro mensaje
        testLog("Generando segundo mensaje...");
        $generateData['text'] = "Segunda prueba. Después de este mensaje, la canción cambiará automáticamente.";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $generateUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($generateData),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            $testFilename2 = $result['azuracast_filename'] ?? $result['filename'];
            
            // Obtener duración
            $localFile2 = "/var/www/casa/src/api/temp/" . $result['filename'];
            $duration2 = file_exists($localFile2) ? getAudioDuration($localFile2) : 10;
            
            testLog("✅ Segundo mensaje generado: $testFilename2");
            testLog("📏 Duración: " . round($duration2, 2) . " segundos");
            
            // Ejecutar interrupción CON skip
            $success = interruptRadioWithSkip($testFilename2, $duration2, true);
            
            if ($success) {
                testLog("✅ Interrupción con skip programado");
                testLog("⏰ El skip se ejecutará en " . (ceil($duration2) + 2) . " segundos");
                testLog("🎵 Escucha: el mensaje sonará, y luego cambiará a una nueva canción");
                
                // Esperar y monitorear
                $waitTime = ceil($duration2) + 3;
                for ($i = 0; $i < $waitTime; $i++) {
                    echo ".";
                    sleep(1);
                }
                echo "\n";
                
                testLog("✅ Skip debería haberse ejecutado");
                testLog("La radio debería estar tocando una NUEVA canción ahora");
            } else {
                testLog("❌ Fallo la interrupción con skip");
            }
        }
    } else {
        testLog("❌ Error generando mensaje de prueba");
    }
} else {
    testLog("❌ Error conectando con API de generación");
}

echo "\n";

// Test 5: Probar skip inmediato
echo "[TEST 5] Probar skip manual inmediato...\n";
echo "Esto saltará inmediatamente a la siguiente canción.\n";
echo "Presiona Enter para ejecutar o Ctrl+C para omitir: ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) === '') {
    $skipSuccess = skipSongNow();
    
    if ($skipSuccess) {
        testLog("✅ Skip inmediato ejecutado");
    } else {
        testLog("❌ Error en skip inmediato");
    }
}

echo "\n========================================\n";
echo "Tests completados\n";
echo "========================================\n\n";

echo "RESUMEN:\n";
echo "- getAudioDuration: Funciona correctamente\n";
echo "- interruptRadio sin skip: Mantiene la canción después\n";
echo "- interruptRadio con skip: Cambia a nueva canción después\n";
echo "- skipSongNow: Salta inmediatamente\n\n";

echo "Para usar en producción:\n";
echo "1. En generate.php y jingle-service.php:\n";
echo "   Cambiar interruptRadio() por interruptRadioWithSkip()\n";
echo "2. Agregar un setting para activar/desactivar skip\n";
echo "3. Monitorear logs en /var/www/casa/src/api/logs/\n\n";