<?php
// Probar eleven_v3 con valores correctos de stability

require_once __DIR__ . '/src/api/config.php';

function testV3($stability, $outputFormat = null) {
    $voiceId = 'pXOYlNbO024q13bfqrw0'; // Jefry
    
    $stabilityLabel = [
        '0.0' => 'Creative',
        '0.5' => 'Natural',
        '1.0' => 'Robust'
    ];
    
    echo "\n=== Modelo eleven_v3 - Stability: {$stability} ({$stabilityLabel[$stability]}) ===\n";
    if ($outputFormat) {
        echo "Con output_format: $outputFormat\n";
    }
    
    $url = "https://api.elevenlabs.io/v1/text-to-speech/$voiceId";
    
    $data = [
        'text' => 'Prueba de calidad de audio con modelo v3',
        'model_id' => 'eleven_v3',
        'voice_settings' => [
            'stability' => floatval($stability),
            'similarity_boost' => 0.8,
            'style' => 0.5,
            'use_speaker_boost' => true
        ]
    ];
    
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
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Tiempo de respuesta: {$duration}s\n";
    
    if ($httpCode === 200) {
        $size = strlen($response);
        echo "✅ Éxito! Tamaño: " . number_format($size) . " bytes\n";
        
        $filename = '/tmp/test_v3_' . str_replace('.', '', $stability) . '_' . time() . '.mp3';
        file_put_contents($filename, $response);
        
        $probe = shell_exec("ffprobe -v quiet -print_format json -show_streams $filename 2>&1");
        $info = json_decode($probe, true);
        
        if ($info && isset($info['streams'][0])) {
            $stream = $info['streams'][0];
            echo "\n📊 Análisis del audio:\n";
            echo "- Bitrate: " . ($stream['bit_rate'] ?? 'unknown') . " bps";
            
            $bitrate = intval($stream['bit_rate'] ?? 0);
            if ($bitrate > 0) {
                echo " (" . round($bitrate/1000) . " kbps)";
            }
            echo "\n";
            
            echo "- Channels: " . ($stream['channels'] ?? 'unknown');
            if ($stream['channels'] == 1) {
                echo " (MONO)";
            } elseif ($stream['channels'] == 2) {
                echo " (STEREO)";
            }
            echo "\n";
            echo "- Sample Rate: " . ($stream['sample_rate'] ?? 'unknown') . " Hz\n";
            echo "- Guardado en: $filename\n";
        }
    } else {
        echo "❌ Error HTTP $httpCode\n";
    }
    
    echo str_repeat('-', 60) . "\n";
}

echo "=== PRUEBA MODELO ELEVEN_V3 CON VALORES CORRECTOS ===\n";
echo str_repeat('=', 60) . "\n";

// Probar los 3 valores permitidos de stability
testV3('0.0'); // Creative
testV3('0.5'); // Natural  
testV3('1.0'); // Robust

// Probar con output_format
testV3('0.5', 'mp3_44100_192');
testV3('0.5', 'mp3_44100_256');

echo "\n=== FIN DE PRUEBAS ===\n";