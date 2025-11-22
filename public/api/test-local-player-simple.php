<?php
/**
 * Test Simple: Agregar mensaje de prueba a la cola del Player Local
 */

require_once __DIR__ . '/../../src/api/helpers/local-player-queue.php';

echo "=== TEST: AGREGAR MENSAJE A COLA DEL PLAYER LOCAL ===\n\n";

// Datos del mensaje de prueba
$testMessage = [
    'text' => 'Prueba de TTS para Player Local desde el VPS. Este es un mensaje de prueba del sistema integrado.',
    'audio_path' => 'src/api/temp/test_mensaje_20251121.mp3',
    'category' => 'informativos',
    'type' => 'test',
    'priority' => 'high',
    'voice_name' => 'Juan Carlos',
    'destination' => 'local_player'
];

echo "Mensaje a agregar:\n";
echo json_encode($testMessage, JSON_PRETTY_PRINT) . "\n\n";

echo "Agregando a cola...\n";
$result = addToLocalPlayerQueue($testMessage);

if ($result) {
    echo "✓ Mensaje agregado exitosamente a la cola\n\n";

    $queueCount = countLocalPlayerQueue();
    echo "Mensajes en cola: $queueCount\n\n";

    // Mostrar archivos en cola
    $queueDir = __DIR__ . '/../../database/local-player-queue/';
    if (file_exists($queueDir)) {
        echo "Archivos en cola:\n";
        $files = glob($queueDir . '*.json');

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            echo "  - " . basename($file) . "\n";
            echo "    Texto: " . substr($data['text'], 0, 50) . "...\n";
            echo "    Categoría: " . $data['category'] . "\n";
            echo "    Prioridad: " . $data['priority'] . "\n";
            echo "\n";
        }
    }

    echo "\n✓ TEST EXITOSO\n";
    echo "\nEl player local debería detectar este mensaje en:\n";
    echo "  $queueDir\n";

} else {
    echo "✗ ERROR: No se pudo agregar mensaje a la cola\n";
    exit(1);
}
?>
