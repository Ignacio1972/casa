<?php
// Debug del endpoint de volumen

// Simular el POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$input = [
    'action' => 'update_volume',
    'voice_key' => 'cristian', 
    'volume_adjustment' => -3
];

// Simular php://input
$inputJson = json_encode($input);

echo "=== TEST ENDPOINT UPDATE_VOLUME ===\n";
echo "Input: " . $inputJson . "\n\n";

// Código del endpoint
$voicesFile = __DIR__ . '/src/api/data/voices-config.json';

// Verificar archivo
if (!file_exists($voicesFile)) {
    echo "ERROR: No existe voices-config.json\n";
    exit(1);
}

// Decodificar input (simulando el endpoint)
$action = $input['action'];
$voiceKey = $input['voice_key'];
$volumeAdjustment = $input['volume_adjustment'] ?? 0;

echo "Action: $action\n";
echo "Voice Key: $voiceKey\n";
echo "Volume Adjustment: $volumeAdjustment\n\n";

// Cargar configuración
$config = json_decode(file_get_contents($voicesFile), true);
if (!$config) {
    echo "ERROR: No se pudo decodificar JSON\n";
    exit(1);
}

// Verificar que existe la voz
if (!isset($config['voices'][$voiceKey])) {
    echo "ERROR: Voice not found: $voiceKey\n";
    exit(1);
}

echo "Voz encontrada: " . $config['voices'][$voiceKey]['label'] . "\n";
echo "Volumen actual: " . ($config['voices'][$voiceKey]['volume_adjustment'] ?? 0) . " dB\n";

// Limitar el rango
$volumeAdjustment = max(-20, min(20, floatval($volumeAdjustment)));
echo "Volumen ajustado (limitado): $volumeAdjustment dB\n";

// Actualizar
$config['voices'][$voiceKey]['volume_adjustment'] = $volumeAdjustment;
$config['settings']['last_updated'] = date('c');

// Guardar
$jsonOutput = json_encode($config, JSON_PRETTY_PRINT);
$bytesWritten = file_put_contents($voicesFile, $jsonOutput);

if ($bytesWritten === false) {
    echo "ERROR: No se pudo escribir el archivo\n";
    exit(1);
}

echo "Bytes escritos: $bytesWritten\n";

// Verificar
$configVerify = json_decode(file_get_contents($voicesFile), true);
$newVolume = $configVerify['voices'][$voiceKey]['volume_adjustment'];

echo "\n=== VERIFICACIÓN ===\n";
echo "Volumen guardado: $newVolume dB\n";
echo ($newVolume == $volumeAdjustment) ? "✓ ÉXITO\n" : "✗ ERROR\n";