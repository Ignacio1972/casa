<?php
/**
 * Test de integración: Generar TTS y enviarlo al Player Local
 */

// Simular request HTTP
$_SERVER['REQUEST_METHOD'] = 'POST';

// Datos de prueba
$testData = [
    'action' => 'generate_audio',
    'text' => 'Prueba de TTS para Player Local desde el VPS. Este es un mensaje de prueba del sistema integrado.',
    'voice' => 'G4IAP30yc6c1gK0csDfu', // juan_carlos
    'category' => 'informativos',
    'destination' => 'local_player'
];

// Simular input POST
$_POST = $testData;
file_put_contents('php://input', json_encode($testData));

echo "=== TEST DE INTEGRACIÓN: LOCAL PLAYER ===\n\n";
echo "Parámetros del test:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";
echo "Ejecutando generación...\n\n";
echo "--- Salida de generate.php ---\n";

// Incluir generate.php
ob_start();
include 'src/api/generate.php';
$output = ob_get_clean();

echo $output . "\n";

echo "\n--- Verificando cola del player local ---\n";

// Verificar cola
$queueDir = __DIR__ . '/database/local-player-queue/';
if (file_exists($queueDir)) {
    $files = glob($queueDir . '*.json');
    echo "Archivos en cola: " . count($files) . "\n\n";

    if (count($files) > 0) {
        echo "Último mensaje agregado:\n";
        $lastFile = end($files);
        $queueData = json_decode(file_get_contents($lastFile), true);
        echo json_encode($queueData, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "ERROR: Directorio de cola no existe\n";
}

echo "\n=== TEST COMPLETADO ===\n";
?>
