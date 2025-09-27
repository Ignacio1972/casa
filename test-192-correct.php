<?php
// Probar MP3 192kbps con el método correcto (query parameter)

require_once __DIR__ . '/src/api/config.php';

function test192Correct($useQueryParam = true) {
    $voiceId = 'pXOYlNbO024q13bfqrw0'; // Jefry
    
    echo "\n=== Probando MP3 192kbps ";
    echo $useQueryParam ? "(Query Parameter - CORRECTO)" : "(Body - INCORRECTO)";
    echo " ===\n";
    
    // Construir URL con o sin query parameter
    $url = "https://api.elevenlabs.io/v1/text-to-speech/$voiceId";
    
    $data = [
        'text' => 'Prueba de audio en alta calidad con ciento noventa y dos kilobits por segundo',
        'model_id' => 'eleven_multilingual_v2',
        'voice_settings' => [
            'stability' => 0.5,
            'similarity_boost' => 0.8
        ]
    ];
    
    if ($useQueryParam) {
        // MÉTODO CORRECTO: output_format como query parameter
        $url .= "?output_format=mp3_44100_192";
        echo "URL: $url\n";
    } else {
        // MÉTODO INCORRECTO: output_format en el body
        $data['output_format'] = 'mp3_44100_192';
        echo "URL: $url (formato en body)\n";
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $duration = round($endTime - $startTime, 2);
    echo "HTTP Code: $httpCode\n";
    echo "Tiempo: {$duration}s\n";
    
    if ($httpCode === 200) {
        $size = strlen($response);
        echo "✅ Éxito! Tamaño: " . number_format($size) . " bytes\n";
        
        $filename = '/tmp/test_192_' . ($useQueryParam ? 'query' : 'body') . '_' . time() . '.mp3';
        file_put_contents($filename, $response);
        
        $probe = shell_exec("ffprobe -v quiet -print_format json -show_streams $filename 2>&1");
        $info = json_decode($probe, true);
        
        if ($info && isset($info['streams'][0])) {
            $stream = $info['streams'][0];
            $bitrate = intval($stream['bit_rate'] ?? 0);
            $kbps = round($bitrate/1000);
            
            echo "\n🎯 RESULTADO:\n";
            echo "====================\n";
            echo "📊 Bitrate: $bitrate bps ($kbps kbps) ";
            
            if ($kbps >= 192) {
                echo "✅ ¡ALTA CALIDAD LOGRADA!\n";
            } else {
                echo "❌ Sigue en calidad estándar\n";
            }
            
            echo "🔊 Canales: " . $stream['channels'];
            echo ($stream['channels'] == 2) ? " (ESTÉREO) 🎧\n" : " (MONO)\n";
            echo "📻 Sample Rate: " . $stream['sample_rate'] . " Hz\n";
            echo "💾 Archivo: $filename\n";
            
            // Comparación de tamaño
            if ($kbps >= 192) {
                $expectedIncrease = 192 / 128; // 1.5x
                echo "\n📈 El archivo debería ser ~" . round($expectedIncrease * 100 - 100) . "% más grande\n";
            }
        }
    } else {
        echo "❌ Error HTTP $httpCode\n";
    }
    
    echo str_repeat('-', 60) . "\n";
}

echo "=== PRUEBA CORRECTA DE MP3 192kbps ===\n";
echo "Plan: CREATOR (soporta 192kbps)\n";
echo str_repeat('=', 60) . "\n";

// Probar ambos métodos para comparar
test192Correct(false); // Body (incorrecto)
test192Correct(true);  // Query parameter (correcto)

// También probar 256kbps si el plan lo permite
echo "\n=== PROBANDO 256kbps (si está disponible) ===\n";
$url = "https://api.elevenlabs.io/v1/text-to-speech/pXOYlNbO024q13bfqrw0?output_format=mp3_44100_256";

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
    CURLOPT_POSTFIELDS => json_encode([
        'text' => 'Prueba de máxima calidad',
        'model_id' => 'eleven_multilingual_v2',
        'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.8]
    ]),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ 256kbps también funciona!\n";
    $size = strlen($response);
    echo "Tamaño: " . number_format($size) . " bytes\n";
} else {
    echo "❌ 256kbps no disponible en plan Creator\n";
}