<?php
// Probar con nueva API key

$NEW_API_KEY = 'sk_fdea7277f86413944955135401ee682be927a89f8d3da167';

function testWithNewKey($modelId, $outputFormat, $apiKey) {
    $voiceId = 'pXOYlNbO024q13bfqrw0'; // Jefry
    
    echo "\n=== Probando con nueva API key ===\n";
    echo "Modelo: $modelId\n";
    echo "Formato solicitado: $outputFormat\n";
    
    $url = "https://api.elevenlabs.io/v1/text-to-speech/$voiceId";
    
    $data = [
        'text' => 'Prueba con nueva API key para alta calidad de audio',
        'model_id' => $modelId,
        'output_format' => $outputFormat,
        'voice_settings' => [
            'stability' => 0.5,
            'similarity_boost' => 0.8
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: audio/mpeg',
            'Content-Type: application/json',
            'xi-api-key: ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Content-Type: $contentType\n";
    echo "Tiempo de respuesta: {$duration}s\n";
    
    if ($httpCode === 200) {
        $size = strlen($response);
        echo "✅ Éxito! Tamaño: " . number_format($size) . " bytes\n";
        
        $filename = '/tmp/test_newapi_' . time() . '.mp3';
        file_put_contents($filename, $response);
        
        // Analizar con ffprobe
        $probe = shell_exec("ffprobe -v quiet -print_format json -show_streams $filename 2>&1");
        $info = json_decode($probe, true);
        
        if ($info && isset($info['streams'][0])) {
            $stream = $info['streams'][0];
            echo "\n📊 ANÁLISIS DEL AUDIO:\n";
            echo "================================\n";
            echo "✨ Bitrate REAL: " . ($stream['bit_rate'] ?? 'unknown') . " bps";
            
            $bitrate = intval($stream['bit_rate'] ?? 0);
            if ($bitrate > 0) {
                $kbps = round($bitrate/1000);
                echo " ($kbps kbps)";
                
                // Verificar si es el bitrate solicitado
                if (strpos($outputFormat, '192') !== false && $kbps >= 192) {
                    echo " ✅ ALTA CALIDAD!";
                } elseif (strpos($outputFormat, '256') !== false && $kbps >= 256) {
                    echo " ✅ MUY ALTA CALIDAD!";
                } elseif (strpos($outputFormat, '320') !== false && $kbps >= 320) {
                    echo " ✅ CALIDAD MÁXIMA!";
                }
            }
            echo "\n";
            
            echo "🔊 Canales: " . ($stream['channels'] ?? 'unknown');
            if ($stream['channels'] == 1) {
                echo " (MONO)";
            } elseif ($stream['channels'] == 2) {
                echo " 🎧 (STEREO)";
            }
            echo "\n";
            
            echo "📻 Sample Rate: " . ($stream['sample_rate'] ?? 'unknown') . " Hz\n";
            echo "⏱️ Duración: " . ($stream['duration'] ?? 'unknown') . " segundos\n";
            echo "💾 Guardado en: $filename\n";
        }
        
        // Comparar tamaños
        echo "\n📈 Comparación de tamaños:\n";
        echo "- Con API antigua (128k): ~45,000 bytes\n";
        echo "- Con API nueva: " . number_format($size) . " bytes\n";
        if ($size > 50000) {
            $increase = round(($size - 45000) / 45000 * 100);
            echo "- Incremento: +" . $increase . "% (indica mayor calidad)\n";
        }
        
    } else {
        echo "❌ Error HTTP $httpCode\n";
        $error = json_decode($response, true);
        if ($error) {
            echo "Error: " . json_encode($error, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    echo str_repeat('=', 60) . "\n";
}

echo "=== PRUEBA CON NUEVA API KEY ===\n";
echo "API Key: sk_fdea7...8d3da167\n";
echo str_repeat('=', 60) . "\n";

// Probar diferentes formatos de calidad
testWithNewKey('eleven_multilingual_v2', 'mp3_44100_128', $NEW_API_KEY);
testWithNewKey('eleven_multilingual_v2', 'mp3_44100_192', $NEW_API_KEY);
testWithNewKey('eleven_multilingual_v2', 'mp3_44100_256', $NEW_API_KEY);
testWithNewKey('eleven_multilingual_v2', 'mp3_44100_320', $NEW_API_KEY);

// Probar PCM de alta calidad
testWithNewKey('eleven_multilingual_v2', 'pcm_44100', $NEW_API_KEY);

echo "\n=== FIN DE PRUEBAS ===\n";