<?php
/**
 * Script de prueba para verificar el volumen de TTS
 */

// Configuración
$apiUrl = "http://localhost:4000/api/generate.php";

// Datos de prueba
$data = [
    "action" => "generate_audio",
    "text" => "Prueba de volumen al cincuenta por ciento. Este mensaje debería sonar más suave de lo normal.",
    "voice" => "juan_carlos",
    "category" => "test"
];

// Hacer la petición
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Decodificar respuesta
$result = json_decode($response, true);

if ($result && $result['success']) {
    echo "\n✅ Audio generado exitosamente!\n";
    echo "Archivo: " . $result['filename'] . "\n";

    // Verificar logs
    echo "\n📋 Últimas líneas del log relacionadas con volumen:\n";
    $logFile = "/var/www/casa/src/api/logs/tts-" . date('Y-m-d') . ".log";
    $lines = file($logFile);
    $relevantLines = [];

    foreach ($lines as $line) {
        if (stripos($line, 'volume') !== false || stripos($line, 'ffmpeg') !== false) {
            $relevantLines[] = $line;
        }
    }

    // Mostrar las últimas 10 líneas relevantes
    $relevantLines = array_slice($relevantLines, -10);
    foreach ($relevantLines as $line) {
        echo $line;
    }
} else {
    echo "\n❌ Error generando audio\n";
}
?>