#!/usr/bin/env php
<?php
/**
 * Test para verificar que el botón "Enviar a la radio" funciona
 * en el dashboard después de generar un mensaje o jingle
 */

echo "\n========================================\n";
echo "TEST: Botón 'Enviar a la radio' Dashboard\n";
echo "========================================\n\n";

// Configuración
$baseUrl = 'http://localhost:4000';

// Función para hacer requests
function makeRequest($url, $data) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

// Test 1: Generar un mensaje TTS normal
echo "[TEST 1] Generando mensaje TTS...\n";
$result = makeRequest($baseUrl . '/src/api/generate.php', [
    'action' => 'generate_audio',
    'text' => 'Prueba del botón enviar a radio desde el dashboard',
    'voice' => 'W9VDPF1LRwfMBQNvgpGO',  // Juan Carlos
    'category' => 'informacion'
]);

if ($result['code'] === 200 && $result['response']['success']) {
    $ttsFilename = $result['response']['azuracast_filename'] ?? $result['response']['filename'];
    echo "✅ Mensaje generado: $ttsFilename\n";
    
    // Test envío a radio
    echo "[TEST 1.1] Enviando mensaje TTS a radio...\n";
    $radioResult = makeRequest($baseUrl . '/src/api/generate.php', [
        'action' => 'send_to_radio',
        'filename' => $ttsFilename
    ]);
    
    if ($radioResult['code'] === 200 && $radioResult['response']['success']) {
        echo "✅ Mensaje TTS enviado a radio exitosamente\n";
    } else {
        echo "❌ Error enviando TTS a radio: " . json_encode($radioResult['response']) . "\n";
    }
} else {
    echo "❌ Error generando mensaje TTS: " . json_encode($result['response']) . "\n";
}

echo "\n";

// Test 2: Generar un jingle
echo "[TEST 2] Generando jingle...\n";
$result = makeRequest($baseUrl . '/src/api/jingle-service.php', [
    'action' => 'generate',
    'text' => 'Prueba de jingle con música desde el dashboard',
    'voice' => 'rachel',
    'category' => 'promociones',
    'options' => [
        'music_file' => 'upbeat.mp3',
        'music_volume' => 0.3,
        'voice_volume' => 1.0
    ]
]);

if ($result['code'] === 200 && $result['response']['success']) {
    $jingleFilename = $result['response']['filename'];
    echo "✅ Jingle generado: $jingleFilename\n";
    
    if ($jingleFilename) {
        // Test envío a radio
        echo "[TEST 2.1] Enviando jingle a radio...\n";
        $radioResult = makeRequest($baseUrl . '/src/api/generate.php', [
            'action' => 'send_to_radio',
            'filename' => $jingleFilename
        ]);
        
        if ($radioResult['code'] === 200 && $radioResult['response']['success']) {
            echo "✅ Jingle enviado a radio exitosamente\n";
        } else {
            echo "❌ Error enviando jingle a radio: " . json_encode($radioResult['response']) . "\n";
        }
    } else {
        echo "⚠️  Jingle generado pero sin filename para enviar a radio\n";
    }
} else {
    echo "❌ Error generando jingle: " . json_encode($result['response']) . "\n";
}

echo "\n========================================\n";
echo "Test completado\n";
echo "========================================\n\n";

// Instrucciones para probar manualmente
echo "PRUEBA MANUAL EN EL DASHBOARD:\n";
echo "1. Ve a http://51.222.25.222/\n";
echo "2. En el dashboard, escribe un mensaje\n";
echo "3. Haz clic en 'Generar Audio'\n";
echo "4. Cuando aparezca el reproductor, verifica que haya dos botones:\n";
echo "   - 💾 Guardar en Biblioteca\n";
echo "   - 📡 Enviar a la Radio\n";
echo "5. Haz clic en 'Enviar a la Radio'\n";
echo "6. Deberías ver una confirmación y escuchar el mensaje en la radio\n\n";

echo "Si el botón no funciona, revisa la consola del navegador (F12)\n";
echo "para ver si hay errores de JavaScript.\n\n";