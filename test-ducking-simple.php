<?php
// Test simple de ducking usando la infraestructura existente

require_once '/var/www/casa/src/api/services/tts-service-unified.php';

echo "=== Test Ducking Simple ===\n\n";

// 1. Generar TTS con la función existente
$result = generateEnhancedTTS(
    "Atención, este es un mensaje de prueba. La música debe bajar su volumen automáticamente.",
    "juan_carlos"
);

if (isset($result['filepath'])) {
    echo "✓ TTS generado: " . $result['filepath'] . "\n";
    
    // 2. Enviar a la cola de ducking
    $fileUri = "file://" . realpath($result['filepath']);
    $command = "tts_ducking_queue.push $fileUri";
    
    echo "Comando: $command\n";
    
    // 3. Ejecutar via Docker
    $dockerCmd = sprintf(
        'sudo docker exec azuracast bash -c \'echo "%s" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock\'',
        addslashes($command)
    );
    
    echo "Ejecutando...\n";
    $output = shell_exec($dockerCmd . ' 2>&1');
    
    echo "Respuesta: $output\n";
    
    if (is_numeric(trim($output))) {
        echo "\n✅ ¡Éxito! Request ID: " . trim($output) . "\n";
        echo "La música debería bajar de volumen mientras se reproduce el mensaje.\n";
    } else {
        echo "\n❌ Error: No se pudo enviar a la cola de ducking\n";
    }
} else {
    echo "❌ Error generando TTS\n";
}
