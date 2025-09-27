#!/usr/bin/php
<?php
/**
 * Prueba simple de generación
 */

echo "=== Test Simple de Generación ===\n\n";

// Verificar que los archivos necesarios existen
$files = [
    '/var/www/casa/src/api/generate.php',
    '/var/www/casa/src/api/services/radio-service.php',
    '/var/www/casa/src/api/services/tts-service-unified.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file existe\n";
    } else {
        echo "✗ $file NO existe\n";
    }
}

echo "\n=== Verificando configuración ===\n";

// Cargar config
require_once '/var/www/casa/src/api/config.php';

echo "AZURACAST_BASE_URL: " . AZURACAST_BASE_URL . "\n";
echo "AZURACAST_STATION_ID: " . AZURACAST_STATION_ID . "\n";
echo "API Key presente: " . (strlen(AZURACAST_API_KEY) > 0 ? "Sí" : "No") . "\n";
echo "ElevenLabs Key presente: " . (strlen(ELEVENLABS_API_KEY) > 0 ? "Sí" : "No") . "\n";

echo "\n=== Revisando últimos logs ===\n";
$logFile = '/var/www/casa/src/api/logs/tts-' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    $lastLines = array_slice(file($logFile), -5);
    foreach ($lastLines as $line) {
        echo "  " . trim($line) . "\n";
    }
} else {
    echo "No hay logs de hoy\n";
}

echo "\n=== Verificando función de nombres ===\n";

// Simular generación de nombre
$text = "Bienvenidos a casa costanera";
$voice = "rachel";

// Lógica del nombre
$words = explode(' ', $text);
$slug = implode('_', array_slice($words, 0, 3));
$slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug);
$slug = strtolower(substr($slug, 0, 20));

$timestamp = date('Ymd_His');
$descriptiveFilename = 'mensaje_' . (!empty($slug) ? $slug . '_' : '') . $voice . '_' . $timestamp . '.mp3';

echo "Texto: \"$text\"\n";
echo "Slug generado: \"$slug\"\n";
echo "Nombre generado: $descriptiveFilename\n";

if (preg_match('/^mensaje_/', $descriptiveFilename)) {
    echo "✓ Formato correcto\n";
} else {
    echo "✗ Formato incorrecto\n";
}

echo "\n=== Fin ===\n";
?>