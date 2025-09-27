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

echo "🔬 Comparación de rendimiento entre modelos disponibles\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Solo probar modelos que sabemos que funcionan
$modelsToTest = [
    'eleven_multilingual_v2' => 'Multilingual v2 (actual)',
    'eleven_turbo_v2_5' => 'Turbo v2.5 (recomendado)',
    'eleven_turbo_v2' => 'Turbo v2',
    'eleven_flash_v2_5' => 'Flash v2.5 (más rápido)',
];

// Obtener una voz activa
$voicesFile = __DIR__ . '/src/api/data/voices-config.json';
$voicesData = json_decode(file_get_contents($voicesFile), true);
$activeVoices = array_filter($voicesData['voices'], function($voice) {
    return $voice['active'] === true;
});
$testVoice = reset($activeVoices);

if (!$testVoice) {
    die("❌ ERROR: No hay voces activas configuradas\n");
}

echo "🎤 Voz de prueba: {$testVoice['label']} (ID: {$testVoice['id']})\n";
echo "📝 Texto de prueba: \"Bienvenidos a Mol Plaza, donde encuentras todo.\"\n\n";

$testText = "Bienvenidos a Mol Plaza, donde encuentras todo lo que necesitas.";
$results = [];

foreach ($modelsToTest as $modelId => $description) {
    echo "🧪 Probando {$description}...\n";
    
    $url = "https://api.elevenlabs.io/v1/text-to-speech/{$testVoice['id']}";
    
    $testData = [
        'text' => $testText,
        'model_id' => $modelId,
        'voice_settings' => [
            'stability' => 0.75,
            'similarity_boost' => 0.8,
            'style' => 0.5,
            'use_speaker_boost' => true
        ]
    ];
    
    $startTime = microtime(true);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: audio/mpeg',
        'Content-Type: application/json',
        'xi-api-key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000); // en milisegundos
    
    if ($httpCode === 200) {
        $results[$modelId] = [
            'success' => true,
            'duration' => $duration,
            'size' => $contentLength,
            'description' => $description
        ];
        
        echo "   ✅ Éxito\n";
        echo "   ⏱️  Tiempo: {$duration}ms\n";
        echo "   📦 Tamaño: " . number_format($contentLength) . " bytes\n";
        
        // Guardar audio para comparación (opcional)
        $filename = "test_{$modelId}_" . time() . ".mp3";
        file_put_contents("/var/www/casa/src/api/temp/{$filename}", $response);
        echo "   💾 Guardado como: {$filename}\n";
    } else {
        echo "   ❌ Error (HTTP $httpCode)\n";
        $results[$modelId] = [
            'success' => false,
            'description' => $description
        ];
    }
    
    echo "\n";
    sleep(1); // Esperar 1 segundo entre pruebas
}

echo "=" . str_repeat("=", 60) . "\n";
echo "📊 COMPARACIÓN DE RESULTADOS\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Ordenar por velocidad
$successfulResults = array_filter($results, function($r) { return $r['success']; });
usort($successfulResults, function($a, $b) { return $a['duration'] - $b['duration']; });

if (count($successfulResults) > 0) {
    echo "⚡ RANKING DE VELOCIDAD:\n\n";
    $position = 1;
    foreach ($successfulResults as $modelId => $result) {
        echo "{$position}. {$result['description']}\n";
        echo "   Tiempo: {$result['duration']}ms | ";
        echo "Tamaño: " . number_format($result['size']) . " bytes\n\n";
        $position++;
    }
    
    // Encontrar el más rápido
    $fastest = reset($successfulResults);
    $slowest = end($successfulResults);
    
    if ($fastest && $slowest && $fastest !== $slowest) {
        $speedup = round($slowest['duration'] / $fastest['duration'], 1);
        echo "💡 ANÁLISIS:\n";
        echo "   • Más rápido: {$fastest['description']} ({$fastest['duration']}ms)\n";
        echo "   • Más lento: {$slowest['description']} ({$slowest['duration']}ms)\n";
        echo "   • El más rápido es {$speedup}x más veloz\n\n";
    }
}

// Recomendación basada en resultados
echo "🎯 RECOMENDACIÓN:\n";
if (isset($results['eleven_turbo_v2_5']) && $results['eleven_turbo_v2_5']['success']) {
    echo "   Use 'eleven_turbo_v2_5' para el mejor balance calidad/velocidad\n";
} elseif (isset($results['eleven_flash_v2_5']) && $results['eleven_flash_v2_5']['success']) {
    echo "   Use 'eleven_flash_v2_5' si la velocidad es crítica\n";
} else {
    echo "   Continue con 'eleven_multilingual_v2' (estable y confiable)\n";
}

echo "\n📝 NOTA: Los archivos de audio se guardaron en /src/api/temp/ para comparación\n";
echo "\n🔍 Test completado con éxito\n";