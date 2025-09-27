<?php
require_once '/var/www/casa/src/api/services/tts-service-unified.php';

echo "=== Debug Ducking ===\n\n";

$result = generateEnhancedTTS(
    "Prueba de ducking",
    "juan_carlos"
);

echo "Resultado completo:\n";
print_r($result);

// Si hay contenido de audio, guardarlo manualmente
if (isset($result['audio_content'])) {
    $filepath = '/tmp/ducking_test_' . time() . '.mp3';
    file_put_contents($filepath, $result['audio_content']);
    echo "\nArchivo guardado en: $filepath\n";
    
    // Enviar a ducking
    $fileUri = "file://" . $filepath;
    $command = "tts_ducking_queue.push $fileUri";
    
    $dockerCmd = sprintf(
        'sudo docker exec azuracast bash -c \'echo "%s" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock\'',
        addslashes($command)
    );
    
    echo "Enviando a ducking...\n";
    $output = shell_exec($dockerCmd . ' 2>&1');
    echo "Respuesta: $output\n";
}
