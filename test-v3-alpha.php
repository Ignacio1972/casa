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

echo "🔍 Test exhaustivo de modelos ElevenLabs (incluyendo v3 alpha)\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Lista de todos los posibles modelos a probar
$models = [
    'eleven_monolingual_v1' => 'Monolingual v1 (básico)',
    'eleven_multilingual_v1' => 'Multilingual v1',
    'eleven_multilingual_v2' => 'Multilingual v2 (estándar)',
    'eleven_turbo_v2' => 'Turbo v2 (rápido)',
    'eleven_turbo_v2_5' => 'Turbo v2.5 (mejorado)',
    'eleven_monolingual_v2' => 'Monolingual v2',
    'eleven_multilingual_v3' => 'Multilingual v3',
    'eleven_turbo_v3' => 'Turbo v3',
    'eleven_multilingual_v3_alpha' => 'Multilingual v3 Alpha',
    'eleven_turbo_v3_alpha' => 'Turbo v3 Alpha',
    'eleven_flash_v2' => 'Flash v2',
    'eleven_flash_v2_5' => 'Flash v2.5',
];

// Obtener una voz activa para las pruebas
$voicesFile = __DIR__ . '/src/api/data/voices-config.json';
$voicesData = json_decode(file_get_contents($voicesFile), true);
$activeVoices = array_filter($voicesData['voices'], function($voice) {
    return $voice['active'] === true;
});

// Tomar la primera voz activa
$testVoice = reset($activeVoices);
if (!$testVoice) {
    die("❌ ERROR: No hay voces activas configuradas\n");
}

echo "🎤 Usando voz de prueba: {$testVoice['label']} (ID: {$testVoice['id']})\n\n";
echo "📋 Probando " . count($models) . " modelos diferentes...\n";
echo "-" . str_repeat("-", 60) . "\n\n";

$results = [];
$availableModels = [];

foreach ($models as $modelId => $description) {
    echo "🧪 Probando: {$description}\n";
    echo "   Modelo ID: {$modelId}\n";
    
    $url = "https://api.elevenlabs.io/v1/text-to-speech/{$testVoice['id']}";
    
    $testData = [
        'text' => 'Test model.',
        'model_id' => $modelId,
        'voice_settings' => [
            'stability' => 0.5,
            'similarity_boost' => 0.5
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
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ DISPONIBLE";
        if ($contentLength > 0) {
            echo " (Audio: " . number_format($contentLength) . " bytes)";
        }
        echo "\n";
        $results[$modelId] = true;
        $availableModels[] = $modelId;
    } else {
        echo "   ❌ NO DISPONIBLE (HTTP $httpCode)";
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['detail']['message'])) {
                $errorMsg = $errorData['detail']['message'];
                // Simplificar mensaje de error
                if (strpos($errorMsg, 'does not exist') !== false) {
                    echo " - Modelo no existe";
                } elseif (strpos($errorMsg, 'not authorized') !== false) {
                    echo " - No autorizado";
                } else {
                    echo " - " . substr($errorMsg, 0, 50);
                }
            }
        }
        echo "\n";
        $results[$modelId] = false;
    }
    
    echo "\n";
    usleep(500000); // Esperar medio segundo entre pruebas
}

echo "=" . str_repeat("=", 60) . "\n";
echo "📊 RESUMEN DE RESULTADOS\n";
echo "=" . str_repeat("=", 60) . "\n\n";

if (count($availableModels) > 0) {
    echo "✅ MODELOS DISPONIBLES (" . count($availableModels) . "):\n";
    foreach ($availableModels as $modelId) {
        echo "   • {$modelId} - {$models[$modelId]}\n";
    }
    echo "\n";
} else {
    echo "⚠️  NO HAY MODELOS DISPONIBLES\n\n";
}

$unavailableModels = array_keys(array_filter($results, function($v) { return !$v; }));
if (count($unavailableModels) > 0) {
    echo "❌ MODELOS NO DISPONIBLES (" . count($unavailableModels) . "):\n";
    foreach ($unavailableModels as $modelId) {
        echo "   • {$modelId} - {$models[$modelId]}\n";
    }
    echo "\n";
}

// Análisis específico de v3
$v3Models = array_filter($availableModels, function($m) { 
    return strpos($m, 'v3') !== false; 
});

if (count($v3Models) > 0) {
    echo "🎉 ¡TIENES ACCESO A MODELOS V3!\n";
    echo "   Modelos v3 disponibles:\n";
    foreach ($v3Models as $modelId) {
        echo "   • {$modelId}\n";
    }
} else {
    echo "⚠️  NO TIENES ACCESO A MODELOS V3\n";
    echo "   Esto es normal para voces clonadas/personalizadas\n";
}

echo "\n";

// Recomendación
if (in_array('eleven_turbo_v2_5', $availableModels)) {
    echo "💡 RECOMENDACIÓN: Usa 'eleven_turbo_v2_5' para mejor rendimiento\n";
} elseif (in_array('eleven_multilingual_v2', $availableModels)) {
    echo "💡 RECOMENDACIÓN: Usa 'eleven_multilingual_v2' (tu modelo actual)\n";
}

echo "\n🔍 Test completado con éxito\n";