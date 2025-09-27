#!/usr/bin/env php
<?php
/**
 * Script de prueba para el comando skip de Liquidsoap
 * Verifica que podemos enviar comandos a Liquidsoap correctamente
 */

echo "\n========================================\n";
echo "TEST: Comando Skip de Liquidsoap\n";
echo "========================================\n\n";

// Test 1: Verificar que podemos conectarnos al socket de Liquidsoap
echo "[TEST 1] Probando conexión al socket de Liquidsoap...\n";

$testCommand = 'echo "help" | sudo docker exec azuracast socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock 2>&1';
$output = shell_exec($testCommand);

if ($output) {
    echo "✅ Conexión exitosa al socket\n";
    echo "Respuesta (primeras 200 chars): " . substr($output, 0, 200) . "...\n\n";
} else {
    echo "❌ No se pudo conectar al socket\n\n";
}

// Test 2: Probar comando skip
echo "[TEST 2] Probando comando 'skip'...\n";
echo "NOTA: Este comando intentará saltar a la siguiente canción\n";
echo "Presiona Enter para continuar o Ctrl+C para cancelar: ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

$skipCommand = 'echo "autodj.skip" | sudo docker exec azuracast socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock 2>&1';
$skipOutput = shell_exec($skipCommand);

echo "Respuesta del comando skip: " . trim($skipOutput) . "\n\n";

// Test 3: Probar variantes del comando
echo "[TEST 3] Probando variantes del comando skip...\n";

$commands = [
    'skip' => 'skip',
    'autodj.skip' => 'autodj.skip',
    'autodj.next' => 'autodj.next',
    'playlist_next' => 'playlist_next',
    'source.skip' => 'source.skip'
];

foreach ($commands as $name => $cmd) {
    echo "  Probando '$name': ";
    $testCmd = sprintf(
        'echo "%s" | sudo docker exec azuracast socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock 2>&1',
        $cmd
    );
    $result = shell_exec($testCmd);
    
    if (strpos(strtolower($result), 'error') !== false || 
        strpos(strtolower($result), 'no such') !== false ||
        strpos(strtolower($result), 'unknown') !== false) {
        echo "❌ No reconocido\n";
    } else {
        echo "✅ Posible comando válido (respuesta: " . substr(trim($result), 0, 50) . ")\n";
    }
}

echo "\n========================================\n";
echo "Test completado\n";
echo "========================================\n\n";

// Test 4: Función de duración
echo "[TEST 4] Probando obtención de duración de audio...\n";

function getAudioDuration($filepath) {
    if (!file_exists($filepath)) {
        return false;
    }
    
    $cmd = sprintf(
        'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
        escapeshellarg($filepath)
    );
    
    $duration = shell_exec($cmd);
    return $duration ? floatval($duration) : false;
}

// Buscar un archivo MP3 para probar
$testFile = shell_exec('find /var/www/casa -name "*.mp3" -type f | head -1');
$testFile = trim($testFile);

if ($testFile) {
    $duration = getAudioDuration($testFile);
    if ($duration !== false) {
        echo "✅ Duración de " . basename($testFile) . ": " . round($duration, 2) . " segundos\n";
    } else {
        echo "❌ No se pudo obtener duración\n";
    }
} else {
    echo "⚠️  No se encontraron archivos MP3 para probar\n";
}

echo "\n";