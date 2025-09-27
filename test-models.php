<?php
// Probar diferentes modelos de ElevenLabs

require_once __DIR__ . '/src/api/config.php';

function testModel($modelId, $outputFormat = null) {
    $voiceId = 'pXOYlNbO024q13bfqrw0'; // Jefry
    
    echo "\n=== Probando modelo: $modelId ===\n";
    if ($outputFormat) {
        echo "Con output_format: $outputFormat\n";
    }
    
    $url = "https://api.elevenlabs.io/v1/text-to-speech/$voiceId";
    
    $data = [
        'text' => 'Prueba de calidad de audio con diferentes modelos',
        'model_id' => $modelId,
        'voice_settings' => [
            'stability' => 0.75,
            'similarity_boost' => 0.8,
            'style' => 0.5,
            'use_speaker_boost' => true
        ]
    ];
    
    // Agregar output_format si se especifica
    if ($outputFormat) {
        $data['output_format'] = $outputFormat;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: audio/mpeg',
            'Content-Type: application/json',
            'xi-api-key: ' . ELEVENLABS_API_KEY
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
        
        // Guardar y analizar
        $filename = '/tmp/test_' . str_replace('_', '', $modelId) . '_' . time() . '.mp3';
        file_put_contents($filename, $response);
        
        $probe = shell_exec("ffprobe -v quiet -print_format json -show_streams $filename 2>&1");
        $info = json_decode($probe, true);
        
        if ($info && isset($info['streams'][0])) {
            $stream = $info['streams'][0];
            echo "\n📊 Análisis del audio:\n";
            echo "- Codec: " . ($stream['codec_name'] ?? 'unknown') . "\n";
            echo "- Bitrate: " . ($stream['bit_rate'] ?? 'unknown') . " bps";
            
            $bitrate = intval($stream['bit_rate'] ?? 0);
            if ($bitrate > 0) {
                echo " (" . round($bitrate/1000) . " kbps)";
            }
            echo "\n";
            
            echo "- Sample Rate: " . ($stream['sample_rate'] ?? 'unknown') . " Hz\n";
            echo "- Channels: " . ($stream['channels'] ?? 'unknown');
            if ($stream['channels'] == 1) {
                echo " (MONO)";
            } elseif ($stream['channels'] == 2) {
                echo " (STEREO)";
            }
            echo "\n";
            echo "- Duración: " . ($stream['duration'] ?? 'unknown') . " segundos\n";
            echo "- Guardado en: $filename\n";
        }
    } else {
        echo "❌ Error HTTP $httpCode\n";
        $error = json_decode($response, true);
        if ($error) {
            echo "Error: " . json_encode($error, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    echo str_repeat('-', 60) . "\n";
}

echo "=== COMPARACIÓN DE MODELOS ELEVENLABS ===\n";
echo "Voz de prueba: Jefry (pXOYlNbO024q13bfqrw0)\n";
echo str_repeat('=', 60) . "\n";

// 1. Modelo multilingual v2 (estándar)
testModel('eleven_multilingual_v2');
testModel('eleven_multilingual_v2', 'mp3_44100_192');

// 2. Modelo v3 (si existe)
testModel('eleven_v3');
testModel('eleven_v3', 'mp3_44100_192');

// 3. Modelo turbo v2.5 (el que usamos actualmente)
testModel('eleven_turbo_v2_5');
testModel('eleven_turbo_v2_5', 'mp3_44100_192');

// 4. Probar monolingual si existe
testModel('eleven_monolingual_v1');

echo "\n=== FIN DE PRUEBAS ===\n";