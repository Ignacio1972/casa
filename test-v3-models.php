#!/usr/bin/env php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value, '"'));
        }
    }
}

$apiKey = getenv('ELEVENLABS_API_KEY');
if (!$apiKey) {
    die("❌ ERROR: No se encontró ELEVENLABS_API_KEY en .env\n");
}

echo "🔍 Test de acceso al modelo Eleven v3 Turbo\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$voicesFile = __DIR__ . '/src/api/data/voices-config.json';
$voicesData = json_decode(file_get_contents($voicesFile), true);

if (!$voicesData || !isset($voicesData['voices'])) {
    die("❌ ERROR: No se pudo leer voices-config.json\n");
}

$activeVoices = array_filter($voicesData['voices'], function($voice) {
    return $voice['active'] === true;
});

echo "📋 Voces activas encontradas: " . count($activeVoices) . "\n\n";

$results = [];
$modelV3Available = false;

foreach ($activeVoices as $voiceName => $voiceData) {
    echo "🎤 Probando voz: {$voiceData['label']} ({$voiceName})\n";
    echo "   ID: {$voiceData['id']}\n";
    
    $url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceData['id']}";
    
    $testData = [
        'text' => 'Test de modelo v3 turbo.',
        'model_id' => 'eleven_turbo_v2_5',
        'voice_settings' => [
            'stability' => 0.5,
            'similarity_boost' => 0.5,
            'style' => 0,
            'use_speaker_boost' => true
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: audio/mpeg',
        'Content-Type: application/json',
        'xi-api-key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ Modelo v2.5 turbo: DISPONIBLE\n";
        $results[$voiceName]['v2_5'] = true;
    } else {
        echo "   ❌ Modelo v2.5 turbo: NO DISPONIBLE (HTTP $httpCode)\n";
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['detail'])) {
                echo "   📝 Detalle: " . $errorData['detail']['message'] . "\n";
            }
        }
        $results[$voiceName]['v2_5'] = false;
    }
    
    $testDataV3 = $testData;
    $testDataV3['model_id'] = 'eleven_turbo_v3';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testDataV3));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: audio/mpeg',
        'Content-Type: application/json',
        'xi-api-key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ Modelo v3 turbo: DISPONIBLE\n";
        $results[$voiceName]['v3'] = true;
        $modelV3Available = true;
    } else {
        echo "   ⚠️  Modelo v3 turbo: NO DISPONIBLE (HTTP $httpCode)\n";
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['detail'])) {
                echo "   📝 Detalle: " . $errorData['detail']['message'] . "\n";
            }
        }
        $results[$voiceName]['v3'] = false;
    }
    
    echo "\n";
    usleep(500000);
}

echo "=" . str_repeat("=", 50) . "\n";
echo "📊 RESUMEN DE RESULTADOS\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$v2Count = 0;
$v3Count = 0;

foreach ($results as $voiceName => $modelSupport) {
    $voice = $activeVoices[$voiceName];
    echo "🎤 {$voice['label']} ({$voiceName}):\n";
    
    if ($modelSupport['v2_5']) {
        echo "   ✅ v2.5 turbo\n";
        $v2Count++;
    } else {
        echo "   ❌ v2.5 turbo\n";
    }
    
    if ($modelSupport['v3']) {
        echo "   ✅ v3 turbo\n";
        $v3Count++;
    } else {
        echo "   ⚠️  v3 turbo (no disponible)\n";
    }
    echo "\n";
}

echo "📈 ESTADÍSTICAS FINALES:\n";
echo "   • Voces con v2.5 turbo: $v2Count/" . count($activeVoices) . "\n";
echo "   • Voces con v3 turbo: $v3Count/" . count($activeVoices) . "\n\n";

if ($modelV3Available) {
    echo "✅ Al menos una voz tiene acceso al modelo v3 turbo\n";
} else {
    echo "⚠️  NINGUNA voz tiene acceso al modelo v3 turbo actualmente\n";
    echo "   Esto puede deberse a:\n";
    echo "   - Las voces clonadas pueden no tener acceso inmediato a v3\n";
    echo "   - El modelo v3 puede requerir una suscripción específica\n";
    echo "   - Las voces personalizadas pueden necesitar configuración adicional\n";
}

echo "\n🔍 Test completado con éxito\n";