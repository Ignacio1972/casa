<?php
// Script de prueba para verificar formatos de audio de ElevenLabs

require_once __DIR__ . '/src/api/config.php';

function testElevenLabsFormat($format = null, $voiceId = null, $voiceName = null) {
    // Usar el voice ID especificado o Juan Carlos por defecto
    if (!$voiceId) {
        $voiceId = 'G4IAP30yc6c1gK0csDfu';
        $voiceName = 'Juan Carlos';
    }
    
    echo "🎤 Probando con voz: $voiceName ($voiceId)\n";
    
    $url = "https://api.elevenlabs.io/v1/text-to-speech/$voiceId";
    
    // Preparar datos con formato opcional
    $data = [
        'text' => 'Prueba de calidad de audio',
        'model_id' => 'eleven_multilingual_v2',
        'voice_settings' => [
            'stability' => 0.75,
            'similarity_boost' => 0.8
        ]
    ];
    
    // Agregar output_format si se especifica
    if ($format) {
        $data['output_format'] = $format;
        echo "Probando con output_format: $format\n";
    } else {
        // Usar el default explícitamente
        $data['output_format'] = 'mp3_44100_128';
        echo "Probando con output_format: mp3_44100_128 (default)\n";
    }
    
    // Configurar headers según el formato
    $accept = 'audio/mpeg';
    if ($format && strpos($format, 'pcm') !== false) {
        $accept = 'audio/wav';
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Accept: $accept",
            'Content-Type: application/json',
            'xi-api-key: ' . ELEVENLABS_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Content-Type: $contentType\n";
    
    if ($httpCode === 200) {
        $size = strlen($response);
        echo "✅ Éxito! Tamaño del audio: " . number_format($size) . " bytes\n";
        
        // Guardar para análisis
        $filename = '/tmp/test_' . ($format ?: 'default') . '_' . time() . '.mp3';
        file_put_contents($filename, $response);
        
        // Analizar con ffprobe
        $probe = shell_exec("ffprobe -v quiet -print_format json -show_streams $filename 2>&1");
        $info = json_decode($probe, true);
        
        if ($info && isset($info['streams'][0])) {
            $stream = $info['streams'][0];
            echo "\nAnálisis del audio:\n";
            echo "- Codec: " . ($stream['codec_name'] ?? 'unknown') . "\n";
            echo "- Bitrate: " . ($stream['bit_rate'] ?? 'unknown') . " bps\n";
            echo "- Sample Rate: " . ($stream['sample_rate'] ?? 'unknown') . " Hz\n";
            echo "- Channels: " . ($stream['channels'] ?? 'unknown') . "\n";
            echo "- Guardado en: $filename\n";
        }
    } else {
        echo "❌ Error HTTP $httpCode\n";
        if ($error) echo "Error CURL: $error\n";
        
        // Mostrar respuesta de error
        $errorData = json_decode($response, true);
        if ($errorData) {
            echo "Respuesta: " . json_encode($errorData, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

// Probar diferentes voces y formatos
echo "=== PRUEBA DE FORMATOS DE AUDIO ELEVENLABS ===\n\n";

// Probar con JEFRY diferentes formatos
echo "████ PRUEBAS CON VOZ JEFRY ████\n\n";
testElevenLabsFormat('mp3_44100_128', 'pXOYlNbO024q13bfqrw0', 'Jefry');
testElevenLabsFormat('mp3_44100_192', 'pXOYlNbO024q13bfqrw0', 'Jefry');
testElevenLabsFormat('mp3_44100_256', 'pXOYlNbO024q13bfqrw0', 'Jefry');
testElevenLabsFormat('pcm_44100', 'pXOYlNbO024q13bfqrw0', 'Jefry');

echo "\n=== FIN DE PRUEBAS ===\n";