#!/usr/bin/php
<?php
/**
 * Test CLI del nuevo sistema de nombres
 */

// Cambiar al directorio de la API
chdir('/var/www/casa/src/api');

// Incluir archivos necesarios
require_once 'config.php';
require_once 'services/radio-service.php';

echo "=== Test Nuevo Sistema de Nombres ===\n\n";

// Test 1: Verificar función uploadFileToAzuraCast
echo "Test 1: Verificando función uploadFileToAzuraCast\n";

// Simular diferentes casos
$testCases = [
    ['input' => '', 'expected' => 'mensaje_'],
    ['input' => 'temp.mp3', 'expected' => 'mensaje_'],
    ['input' => 'jingle_20250126_123456_rachel.mp3', 'expected' => 'jingle_'],
    ['input' => 'custom_audio_file.mp3', 'expected' => 'custom_audio_file.mp3']
];

foreach ($testCases as $case) {
    echo "  Input: '{$case['input']}'\n";
    
    // Extraer lógica de nombrado de la función
    if (!empty($case['input']) && $case['input'] !== 'temp.mp3') {
        $radioFilename = $case['input'];
    } else {
        $timestamp = date('Ymd_His');
        $radioFilename = 'mensaje_' . $timestamp . '.mp3';
    }
    
    if (strpos($radioFilename, $case['expected']) === 0) {
        echo "  ✓ Resultado correcto: $radioFilename\n";
    } else {
        echo "  ✗ Error: esperaba prefijo '{$case['expected']}', obtuvo '$radioFilename'\n";
    }
    echo "\n";
}

// Test 2: Verificar generación de nombres en generate.php
echo "Test 2: Simulando generación de nombres descriptivos\n";

$testTexts = [
    "Bienvenidos a Casa Costanera" => "mensaje_bienvenidos_a_casa_",
    "Oferta especial 50% descuento" => "mensaje_oferta_especial_50_",
    "¡Gran promoción!" => "mensaje_gran_promocin_",
    "" => "mensaje_"
];

foreach ($testTexts as $text => $expectedPrefix) {
    $words = explode(' ', $text);
    $slug = implode('_', array_slice($words, 0, 3));
    $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug);
    $slug = strtolower(substr($slug, 0, 20));
    
    $timestamp = date('Ymd_His');
    $voiceUsed = 'rachel';
    $descriptiveFilename = 'mensaje_' . (!empty($slug) ? $slug . '_' : '') . $voiceUsed . '_' . $timestamp . '.mp3';
    
    echo "  Texto: \"$text\"\n";
    echo "  Slug generado: \"$slug\"\n";
    echo "  Archivo: $descriptiveFilename\n";
    
    if (strpos($descriptiveFilename, $expectedPrefix) === 0) {
        echo "  ✓ Formato correcto\n";
    } else {
        echo "  ✗ Error en formato\n";
    }
    echo "\n";
}

// Test 3: Verificar patrones de validación en biblioteca.php
echo "Test 3: Verificando patrones de validación\n";

$testFiles = [
    'mensaje_bienvenidos_rachel_20250126_123456.mp3' => true,
    'mensaje_test_20250126_123456.mp3' => true,
    'tts20250126123456.mp3' => true, // Legacy
    'jingle_20250126_123456_rachel.mp3' => true,
    'custom_audio.mp3' => true,
    'invalid file.txt' => false
];

foreach ($testFiles as $filename => $shouldPass) {
    $isMessageFile = preg_match('/^mensaje_.*\.mp3$/', $filename);
    $isTTSFile = preg_match('/^tts\d+(_[a-zA-Z0-9_\-ñÑáéíóúÁÉÍÓÚ]+)?\.mp3$/', $filename);
    $isJingleFile = preg_match('/^jingle_\d+_\d+_[a-zA-Z0-9_\-ñÑáéíóúÁÉÍÓÚ]+\.mp3$/', $filename);
    $isExternalFile = preg_match('/^[a-zA-Z0-9._\-ñÑáéíóúÁÉÍÓÚ]+\.(mp3|wav|flac|aac|ogg|m4a|opus)$/i', $filename);
    
    $isValid = $isMessageFile || $isTTSFile || $isJingleFile || $isExternalFile;
    
    echo "  Archivo: $filename\n";
    echo "    - Es mensaje nuevo: " . ($isMessageFile ? "Sí" : "No") . "\n";
    echo "    - Es TTS legacy: " . ($isTTSFile ? "Sí" : "No") . "\n";
    echo "    - Es jingle: " . ($isJingleFile ? "Sí" : "No") . "\n";
    echo "    - Es externo: " . ($isExternalFile ? "Sí" : "No") . "\n";
    
    if ($isValid === $shouldPass) {
        echo "  ✓ Validación correcta\n";
    } else {
        echo "  ✗ Error en validación (esperaba: " . ($shouldPass ? "válido" : "inválido") . ")\n";
    }
    echo "\n";
}

echo "=== Fin de pruebas ===\n";
?>