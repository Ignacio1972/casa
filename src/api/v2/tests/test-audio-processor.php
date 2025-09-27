#!/usr/bin/env php
<?php
/**
 * Test Suite para AudioProcessor
 * Valida normalización LUFS y procesamiento de audio
 */

// Configurar autoload y namespaces
require_once '/var/www/casa/src/api/v2/services/AudioProcessor.php';
require_once '/var/www/casa/src/api/v2/services/AudioProfiles.php';

use App\Services\AudioProcessor;
use App\Services\AudioProfiles;

// Colores para output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[0;33m";
$NC = "\033[0m"; // No Color

echo "\n{$YELLOW}=== AUDIO PROCESSOR TEST SUITE ==={$NC}\n\n";

// Test 1: Verificar que AudioProcessor se inicializa correctamente
echo "Test 1: Inicialización de AudioProcessor... ";
try {
    $processor = new AudioProcessor();
    echo "{$GREEN}✓ PASS{$NC}\n";
    $test1 = true;
} catch (Exception $e) {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  Error: " . $e->getMessage() . "\n";
    $test1 = false;
}

// Test 2: Verificar perfiles de audio
echo "Test 2: Verificar perfiles de audio... ";
$profiles = AudioProfiles::PROFILES;
if (count($profiles) === 6 && isset($profiles['message']) && isset($profiles['emergency'])) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    echo "  Perfiles encontrados: " . implode(', ', array_keys($profiles)) . "\n";
    $test2 = true;
} else {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  Esperados 6 perfiles, encontrados: " . count($profiles) . "\n";
    $test2 = false;
}

// Test 3: Verificar detección automática de perfil
echo "Test 3: Auto-detección de perfiles... ";
$testCases = [
    ['context' => ['category' => 'emergencias'], 'expected' => 'emergency'],
    ['context' => ['has_music' => true], 'expected' => 'jingle'],
    ['context' => ['duration' => 120], 'expected' => 'podcast']
];

$allPassed = true;
foreach ($testCases as $case) {
    $detected = AudioProfiles::autoDetectProfile($case['context']);
    if ($detected['name'] !== AudioProfiles::PROFILES[$case['expected']]['name']) {
        $allPassed = false;
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  Contexto: " . json_encode($case['context']) . "\n";
        echo "  Esperado: {$case['expected']}, Detectado: " . array_search($detected, AudioProfiles::PROFILES) . "\n";
        break;
    }
}
if ($allPassed) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    $test3 = true;
} else {
    $test3 = false;
}

// Test 4: Generar archivo de audio de prueba
echo "Test 4: Crear archivo de audio de prueba... ";
$testAudioFile = '/var/www/casa/src/api/v2/temp/test_audio.mp3';

// Generar tono de prueba con FFmpeg (1 segundo, 440Hz)
$cmd = "ffmpeg -f lavfi -i \"sine=frequency=440:duration=1\" -c:a libmp3lame -b:a 192k -ar 44100 $testAudioFile -y 2>/dev/null";
exec($cmd, $output, $returnVar);

if ($returnVar === 0 && file_exists($testAudioFile)) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    echo "  Archivo creado: " . basename($testAudioFile) . " (" . filesize($testAudioFile) . " bytes)\n";
    $test4 = true;
} else {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  No se pudo crear archivo de prueba\n";
    $test4 = false;
}

// Test 5: Analizar audio sin normalizar
if ($test1 && $test4) {
    echo "Test 5: Analizar audio sin normalizar... ";
    try {
        $analysis = $processor->analyzeAudio($testAudioFile);
        
        if (isset($analysis['loudness']['integrated_lufs'])) {
            echo "{$GREEN}✓ PASS{$NC}\n";
            echo "  LUFS original: {$analysis['loudness']['integrated_lufs']}\n";
            echo "  Duración: {$analysis['duration']} segundos\n";
            echo "  Formato: {$analysis['format']}\n";
            $test5 = true;
        } else {
            echo "{$RED}✗ FAIL{$NC}\n";
            echo "  No se pudo obtener LUFS\n";
            $test5 = false;
        }
    } catch (Exception $e) {
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $test5 = false;
    }
} else {
    echo "Test 5: SKIPPED (dependencias no cumplidas)\n";
    $test5 = false;
}

// Test 6: Normalizar audio
if ($test1 && $test4) {
    echo "Test 6: Normalizar audio a -16 LUFS... ";
    $normalizedFile = '/var/www/casa/src/api/v2/temp/test_normalized.mp3';
    
    try {
        $result = $processor->normalize($testAudioFile, $normalizedFile, 'message');
        
        if ($result['success'] && file_exists($normalizedFile)) {
            $targetLUFS = -16;
            $finalLUFS = $result['metrics']['final']['integrated_lufs'];
            $difference = abs($finalLUFS - $targetLUFS);
            
            if ($difference < 2.0) { // Tolerancia de ±2 LUFS
                echo "{$GREEN}✓ PASS{$NC}\n";
                echo "  Original LUFS: {$result['metrics']['original']['integrated_lufs']}\n";
                echo "  Target LUFS: $targetLUFS\n";
                echo "  Final LUFS: $finalLUFS (diferencia: $difference)\n";
                echo "  Tiempo procesamiento: {$result['processing_time_ms']} ms\n";
                $test6 = true;
            } else {
                echo "{$YELLOW}⚠ WARNING{$NC}\n";
                echo "  LUFS fuera de tolerancia. Target: $targetLUFS, Final: $finalLUFS\n";
                $test6 = false;
            }
        } else {
            echo "{$RED}✗ FAIL{$NC}\n";
            echo "  No se pudo normalizar el archivo\n";
            $test6 = false;
        }
    } catch (Exception $e) {
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $test6 = false;
    }
} else {
    echo "Test 6: SKIPPED (dependencias no cumplidas)\n";
    $test6 = false;
}

// Test 7: Verificar diferentes perfiles
if ($test1 && $test4) {
    echo "Test 7: Probar diferentes perfiles de normalización... ";
    $profileTests = [
        'emergency' => -12,
        'jingle' => -14,
        'message' => -16
    ];
    
    $profilesPassed = true;
    foreach ($profileTests as $profile => $expectedLUFS) {
        $outputFile = "/var/www/casa/src/api/v2/temp/test_{$profile}.mp3";
        
        try {
            $result = $processor->normalize($testAudioFile, $outputFile, $profile);
            $finalLUFS = $result['metrics']['final']['integrated_lufs'];
            $diff = abs($finalLUFS - $expectedLUFS);
            
            if ($diff > 2.0) {
                $profilesPassed = false;
                echo "{$RED}✗ FAIL{$NC}\n";
                echo "  Perfil '$profile': esperado $expectedLUFS, obtenido $finalLUFS\n";
                break;
            }
        } catch (Exception $e) {
            $profilesPassed = false;
            echo "{$RED}✗ FAIL{$NC}\n";
            echo "  Error en perfil '$profile': " . $e->getMessage() . "\n";
            break;
        }
    }
    
    if ($profilesPassed) {
        echo "{$GREEN}✓ PASS{$NC}\n";
        echo "  Todos los perfiles normalizados correctamente\n";
        $test7 = true;
    } else {
        $test7 = false;
    }
} else {
    echo "Test 7: SKIPPED (dependencias no cumplidas)\n";
    $test7 = false;
}

// Test 8: Verificar logging
echo "Test 8: Verificar sistema de logging... ";
$logFile = '/var/www/casa/src/api/v2/logs/audio-processor.jsonl';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLine = end($lines);
    $logEntry = json_decode($lastLine, true);
    
    if ($logEntry && isset($logEntry['timestamp']) && isset($logEntry['service'])) {
        echo "{$GREEN}✓ PASS{$NC}\n";
        echo "  Último log: {$logEntry['message']} ({$logEntry['level']})\n";
        $test8 = true;
    } else {
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  Formato de log inválido\n";
        $test8 = false;
    }
} else {
    echo "{$YELLOW}⚠ WARNING{$NC}\n";
    echo "  Archivo de log no existe aún\n";
    $test8 = false;
}

// Limpiar archivos de prueba
echo "\nLimpiando archivos temporales... ";
$filesToClean = glob('/var/www/casa/src/api/v2/temp/test*.mp3');
foreach ($filesToClean as $file) {
    unlink($file);
}
echo "{$GREEN}✓{$NC}\n";

// Resumen de resultados
echo "\n{$YELLOW}=== RESUMEN DE RESULTADOS ==={$NC}\n\n";
$tests = [
    'Inicialización' => $test1,
    'Perfiles de audio' => $test2,
    'Auto-detección' => $test3,
    'Crear archivo prueba' => $test4,
    'Análisis LUFS' => $test5,
    'Normalización' => $test6,
    'Multi-perfiles' => $test7,
    'Logging' => $test8
];

$passed = 0;
$failed = 0;

foreach ($tests as $name => $result) {
    if ($result === true) {
        echo "{$GREEN}✓{$NC} $name\n";
        $passed++;
    } elseif ($result === false) {
        echo "{$RED}✗{$NC} $name\n";
        $failed++;
    }
}

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100) : 0;

echo "\n";
echo "Total: $passed/$total tests pasados ($percentage%)\n";

if ($percentage === 100) {
    echo "{$GREEN}¡Todos los tests pasaron exitosamente!{$NC}\n";
} elseif ($percentage >= 75) {
    echo "{$YELLOW}La mayoría de tests pasaron, pero hay algunos problemas.{$NC}\n";
} else {
    echo "{$RED}Varios tests fallaron. Revisar implementación.{$NC}\n";
}

echo "\n";
exit($failed > 0 ? 1 : 0);