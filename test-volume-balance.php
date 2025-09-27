<?php
require_once '/var/www/casa/src/api/ducking-service.php';

echo "=================================\n";
echo "  TEST DE BALANCE DE VOLÚMENES\n";
echo "=================================\n\n";

$service = new DuckingService();

$messages = [
    "Primer mensaje. Verificando niveles de audio.",
    "Segundo mensaje. La música debe estar al veinte por ciento.",
    "Tercer mensaje. Ajustando balance entre voz y música."
];

echo "Enviando 3 mensajes con 15 segundos de separación...\n\n";

foreach ($messages as $i => $msg) {
    echo "Mensaje " . ($i+1) . ": ";
    $result = $service->sendWithDucking($msg, 'juan_carlos');
    
    if ($result['success']) {
        echo "✓ ID: " . $result['request_id'] . "\n";
    } else {
        echo "✗ Error\n";
    }
    
    if ($i < count($messages) - 1) {
        echo "Esperando 15 segundos...\n\n";
        sleep(15);
    }
}

echo "\n=================================\n";
echo "EVALUACIÓN DE VOLÚMENES:\n";
echo "=================================\n";
echo "¿La música baja al 20%? (Sí/No)\n";
echo "¿El TTS se escucha claro? (Sí/No)\n";
echo "¿Necesitas ajustar algo? (Sí/No)\n";
echo "\nSi necesitas ajustar, edita en AzuraCast:\n";
echo "- Para música más baja: cambiar 0.2 a 0.1\n";
echo "- Para TTS más alto: cambiar amplify(1.0, tts) a amplify(1.3, tts)\n";
