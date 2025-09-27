<?php
// Test de volumen con voz Veronica (+5 dB)
require_once __DIR__ . '/src/api/services/tts-service-unified.php';

$text = "Este es un test con la voz de Veronica con ajuste de volumen de más cinco decibeles.";
$voice = "veronica";

echo "Generando audio con voz '$voice' (ajuste de +5 dB)...\n";

try {
    $audio = generateTTS($text, $voice);
    $outputFile = __DIR__ . '/test-veronica-5db-' . time() . '.mp3';
    file_put_contents($outputFile, $audio);
    
    echo "✓ Audio generado: $outputFile\n";
    echo "  Tamaño: " . number_format(strlen($audio) / 1024, 2) . " KB\n";
    
    // Verificar si se aplicó el ajuste de volumen
    $logFile = __DIR__ . '/src/api/logs/tts-' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        $log = file_get_contents($logFile);
        if (strpos($log, 'Aplicando ajuste de volumen: 5 dB') !== false) {
            echo "✓ Ajuste de volumen aplicado correctamente\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}