<?php
require_once '/var/www/casa/src/api/services/tts-service-unified.php';

echo "=== Test Ducking Funcional ===\n\n";

// 1. Generar TTS
$result = generateEnhancedTTS("Atención clientes, probando sistema de ducking. La música bajará de volumen.", "juan_carlos");

if ($result && strlen($result) > 0) {
    // 2. Guardar el audio
    $filepath = '/tmp/ducking_' . time() . '.mp3';
    file_put_contents($filepath, $result);
    echo "✓ Audio guardado en: $filepath\n";
    echo "  Tamaño: " . filesize($filepath) . " bytes\n\n";
    
    // 3. Enviar a la cola de ducking
    $fileUri = "file://" . $filepath;
    $command = "tts_ducking_queue.push $fileUri";
    
    echo "Comando Liquidsoap: $command\n\n";
    
    // 4. Ejecutar via Docker
    $dockerCmd = 'sudo docker exec azuracast bash -c \'echo "' . addslashes($command) . '" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock 2>&1\'';
    
    echo "Enviando a AzuraCast...\n";
    $output = shell_exec($dockerCmd);
    
    echo "Respuesta: " . $output . "\n\n";
    
    if (is_numeric(trim($output))) {
        echo "✅ ¡ÉXITO! El audio se está reproduciendo con ducking.\n";
        echo "   Request ID: " . trim($output) . "\n";
        echo "   La música debe bajar al 20% mientras suena el mensaje.\n";
    } else {
        echo "⚠️ Respuesta no numérica. Verificando estado...\n";
        if (strpos($output, "error") === false && !empty($output)) {
            echo "   Posiblemente funcionó. Escucha la radio.\n";
        }
    }
} else {
    echo "❌ Error generando TTS\n";
}
