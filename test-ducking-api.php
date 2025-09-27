<?php
// Test del servicio de ducking

require_once '/var/www/casa/src/api/tts-ducking-azuracast.php';

$service = new TTSDuckingAzuraCast();

echo "=== Probando TTS con Ducking ===\n\n";

// Generar un mensaje de prueba
$result = $service->generateAndQueue(
    "Atención estimados clientes, este es un mensaje de prueba del sistema de audio con ducking automático",
    "Rachel",
    ["immediate" => true, "category" => "test"]
);

if ($result['success']) {
    echo "✅ Mensaje enviado exitosamente!\n";
    echo "Archivo: " . $result['data']['audio_file'] . "\n";
    echo "Respuesta: " . json_encode($result['data']['azuracast_response'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Error: " . $result['error'] . "\n";
}
