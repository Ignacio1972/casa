<?php
/**
 * Script de prueba para el sistema de jingles
 * Verifica que todas las configuraciones y servicios estén funcionando correctamente
 */

require_once __DIR__ . '/src/api/config.php';

echo "=== TEST DEL SISTEMA DE JINGLES ===\n\n";

// 1. Verificar configuración de voces
echo "1. VERIFICANDO VOCES DISPONIBLES:\n";
$voicesFile = __DIR__ . '/src/api/data/voices-config.json';
if (file_exists($voicesFile)) {
    $voicesConfig = json_decode(file_get_contents($voicesFile), true);
    $activeVoices = [];
    foreach ($voicesConfig['voices'] as $key => $voice) {
        if ($voice['active']) {
            $activeVoices[] = $key . " (" . $voice['label'] . ")";
        }
    }
    echo "   Voces activas: " . implode(", ", $activeVoices) . "\n";
} else {
    echo "   ERROR: No se encuentra archivo de configuración de voces\n";
}

// 2. Verificar archivos de música
echo "\n2. VERIFICANDO MÚSICA DISPONIBLE:\n";
$musicDir = __DIR__ . '/public/audio/music/';
$musicFiles = glob($musicDir . "*.mp3");
$musicNames = array_map(function($file) {
    return basename($file);
}, $musicFiles);
echo "   Archivos de música: " . implode(", ", $musicNames) . "\n";

// 3. Verificar API de ElevenLabs
echo "\n3. VERIFICANDO CONEXIÓN CON ELEVENLABS:\n";
if (defined('ELEVENLABS_API_KEY') && !empty(ELEVENLABS_API_KEY)) {
    echo "   API Key configurada: SÍ\n";

    // Hacer una petición de prueba
    $url = ELEVENLABS_BASE_URL . "/user";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'xi-api-key: ' . ELEVENLABS_API_KEY
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $userData = json_decode($response, true);
        echo "   Conexión exitosa - Usuario: " . ($userData['xi_api_key']['name'] ?? 'N/A') . "\n";
    } else {
        echo "   ERROR: No se puede conectar con ElevenLabs (HTTP $httpCode)\n";
    }
} else {
    echo "   ERROR: API Key no configurada\n";
}

// 4. Hacer prueba de generación de jingle
echo "\n4. PRUEBA DE GENERACIÓN DE JINGLE:\n";
if (!empty($activeVoices) && !empty($musicNames)) {
    // Usar la primera voz activa y el primer archivo de música
    $testVoice = explode(" ", $activeVoices[0])[0];
    $testMusic = $musicNames[0];

    echo "   Generando jingle de prueba con voz '$testVoice' y música '$testMusic'...\n";

    $testData = [
        'action' => 'generate',
        'text' => 'Este es un mensaje de prueba del sistema de jingles',
        'voice' => $testVoice,
        'options' => [
            'music_file' => $testMusic,
            'music_volume' => 0.3,
            'voice_volume' => 1.0
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost:4000/api/jingle-service.php",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($testData),
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            echo "   ÉXITO: Jingle generado correctamente\n";
            echo "   - Duración: " . ($result['duration'] ?? 'N/A') . " segundos\n";
            echo "   - Formato: " . ($result['format'] ?? 'N/A') . "\n";
        } else {
            echo "   ERROR: " . ($result['error'] ?? 'Error desconocido') . "\n";
        }
    } else {
        echo "   ERROR: HTTP $httpCode - No se pudo conectar con el servicio\n";
        echo "   Respuesta: " . substr($response, 0, 200) . "\n";
    }
} else {
    echo "   No se puede hacer prueba: faltan voces o música\n";
}

// 5. Verificar permisos de directorios
echo "\n5. VERIFICANDO PERMISOS:\n";
$dirsToCheck = [
    __DIR__ . '/src/api/temp/',
    __DIR__ . '/database/',
    __DIR__ . '/src/api/logs/'
];

foreach ($dirsToCheck as $dir) {
    if (file_exists($dir)) {
        $writable = is_writable($dir);
        echo "   " . basename(dirname($dir)) . "/" . basename($dir) . ": " .
             ($writable ? "✓ Escritura OK" : "✗ Sin permisos de escritura") . "\n";
    } else {
        echo "   " . basename(dirname($dir)) . "/" . basename($dir) . ": ✗ No existe\n";
    }
}

echo "\n=== FIN DEL TEST ===\n";
echo "\nRECOMENDACIONES:\n";
echo "- Para usar jingles, utilice una de estas voces: " . implode(", ", array_slice($activeVoices, 0, 3)) . "\n";
echo "- Para música de fondo, use uno de estos archivos: " . implode(", ", array_slice($musicNames, 0, 3)) . "\n";
echo "- Si desea generar sin música, simplemente omita el parámetro 'music_file'\n";
?>