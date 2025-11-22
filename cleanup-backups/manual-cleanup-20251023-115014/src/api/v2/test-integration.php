#!/usr/bin/env php
<?php
/**
 * Test de Integración - Sistema completo
 * Prueba con archivos reales del sistema
 */

require_once '/var/www/casa/src/api/v2/services/AudioProcessor.php';
require_once '/var/www/casa/src/api/v2/services/AudioProfiles.php';
require_once '/var/www/casa/src/api/v2/services/RateLimiter.php';

use App\Services\AudioProcessor;
use App\Services\AudioProfiles;
use App\Services\RateLimiter;

// Colores
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[0;33m";
$BLUE = "\033[0;34m";
$NC = "\033[0m";

echo "\n{$BLUE}=== TEST DE INTEGRACIÓN COMPLETO ==={$NC}\n\n";

// Test 1: Buscar un archivo MP3 existente en el sistema
echo "Test 1: Buscar archivo de audio existente... ";
$searchPaths = [
    '/var/www/casa/public/audio/',
    '/var/www/casa/src/api/temp/',
    '/var/www/casa/',
];

$testFile = null;
foreach ($searchPaths as $path) {
    if (is_dir($path)) {
        $files = glob($path . '*.mp3');
        if (!empty($files)) {
            $testFile = $files[0];
            break;
        }
    }
}

// Si no encontramos ninguno, buscar cualquier MP3 reciente
if (!$testFile) {
    exec('find /var/www/casa -name "*.mp3" -type f 2>/dev/null | head -1', $output);
    if (!empty($output[0])) {
        $testFile = $output[0];
    }
}

if ($testFile && file_exists($testFile)) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    echo "  Archivo encontrado: " . basename($testFile) . "\n";
    echo "  Tamaño: " . number_format(filesize($testFile) / 1024, 2) . " KB\n";
    $test1 = true;
} else {
    echo "{$YELLOW}⚠ WARNING{$NC}\n";
    echo "  No se encontró archivo MP3. Generando uno de prueba...\n";
    
    // Generar archivo de prueba con voz simulada
    $testFile = '/var/www/casa/src/api/v2/temp/test_voice.mp3';
    $cmd = 'ffmpeg -f lavfi -i "sine=frequency=300:duration=2" -filter_complex ';
    $cmd .= '"[0:a]aecho=0.8:0.88:60:0.4[a];[a]volume=0.8" ';
    $cmd .= "-c:a libmp3lame -b:a 192k -ar 44100 $testFile -y 2>/dev/null";
    exec($cmd);
    
    if (file_exists($testFile)) {
        echo "  Archivo de prueba generado\n";
        $test1 = true;
    } else {
        echo "  No se pudo generar archivo de prueba\n";
        $test1 = false;
    }
}

// Test 2: Analizar el archivo original
if ($test1) {
    echo "Test 2: Analizar audio original... ";
    try {
        $processor = new AudioProcessor();
        $originalAnalysis = $processor->analyzeAudio($testFile);
        
        echo "{$GREEN}✓ PASS{$NC}\n";
        echo "  Duración: {$originalAnalysis['duration']} segundos\n";
        echo "  LUFS: " . ($originalAnalysis['loudness']['integrated_lufs'] ?? 'N/A') . "\n";
        echo "  Sample Rate: {$originalAnalysis['sample_rate']} Hz\n";
        echo "  Bitrate: " . number_format($originalAnalysis['bitrate'] / 1000) . " kbps\n";
        $test2 = true;
    } catch (Exception $e) {
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $test2 = false;
    }
} else {
    echo "Test 2: SKIPPED\n";
    $test2 = false;
}

// Test 3: Normalizar con cada perfil
if ($test1 && $test2) {
    echo "Test 3: Normalizar con diferentes perfiles... \n";
    
    $profiles = ['message', 'jingle', 'emergency'];
    $results = [];
    $allPassed = true;
    
    foreach ($profiles as $profile) {
        echo "  Probando perfil '$profile'... ";
        
        $outputFile = "/var/www/casa/src/api/v2/temp/normalized_{$profile}.mp3";
        
        try {
            $result = $processor->normalize($testFile, $outputFile, $profile);
            
            if ($result['success']) {
                $targetLUFS = AudioProfiles::PROFILES[$profile]['target_lufs'];
                $finalLUFS = $result['metrics']['final']['integrated_lufs'];
                $diff = abs($finalLUFS - $targetLUFS);
                
                if ($diff < 2.0) {
                    echo "{$GREEN}✓{$NC} ";
                    echo "LUFS: $finalLUFS (target: $targetLUFS)\n";
                    $results[$profile] = [
                        'success' => true,
                        'lufs' => $finalLUFS,
                        'time' => $result['processing_time_ms']
                    ];
                } else {
                    echo "{$YELLOW}⚠{$NC} ";
                    echo "LUFS fuera de rango: $finalLUFS (target: $targetLUFS)\n";
                    $results[$profile] = ['success' => false, 'lufs' => $finalLUFS];
                    $allPassed = false;
                }
            } else {
                echo "{$RED}✗{$NC} Error en normalización\n";
                $allPassed = false;
            }
        } catch (Exception $e) {
            echo "{$RED}✗{$NC} " . $e->getMessage() . "\n";
            $allPassed = false;
        }
    }
    
    $test3 = $allPassed;
} else {
    echo "Test 3: SKIPPED\n";
    $test3 = false;
}

// Test 4: Verificar configuración de estaciones
echo "Test 4: Verificar configuración multi-radio... ";
$stationsFile = '/var/www/casa/src/api/v2/config/stations.json';
if (file_exists($stationsFile)) {
    $stations = json_decode(file_get_contents($stationsFile), true);
    
    if ($stations && isset($stations['stations']) && count($stations['stations']) === 3) {
        echo "{$GREEN}✓ PASS{$NC}\n";
        foreach ($stations['stations'] as $station) {
            echo "  - {$station['name']} ({$station['id']}): ";
            echo $station['enabled'] ? "{$GREEN}Activa{$NC}" : "{$RED}Inactiva{$NC}";
            echo " | LUFS target: {$station['audio_requirements']['lufs_target']}\n";
        }
        $test4 = true;
    } else {
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  Configuración inválida o incompleta\n";
        $test4 = false;
    }
} else {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  Archivo de configuración no encontrado\n";
    $test4 = false;
}

// Test 5: Simular flujo completo
echo "Test 5: Simular flujo completo de procesamiento... \n";
if ($test1 && $test2 && $test3 && $test4) {
    try {
        // Paso 1: Rate limiting check
        echo "  [1/4] Verificando rate limit... ";
        $limiter = new RateLimiter();
        $canProceed = $limiter->checkLimit('elevenlabs', 'test_integration');
        
        if ($canProceed['allowed']) {
            echo "{$GREEN}✓{$NC} (Remaining: {$canProceed['remaining']})\n";
        } else {
            echo "{$YELLOW}⚠{$NC} (Rate limited, retry in {$canProceed['retry_after']}s)\n";
        }
        
        // Paso 2: Auto-detectar perfil
        echo "  [2/4] Auto-detectando perfil... ";
        $context = ['category' => 'promociones', 'duration' => $originalAnalysis['duration']];
        $detectedProfile = AudioProfiles::autoDetectProfile($context);
        echo "{$GREEN}✓{$NC} Perfil: " . array_search($detectedProfile, AudioProfiles::PROFILES) . "\n";
        
        // Paso 3: Normalizar
        echo "  [3/4] Normalizando audio... ";
        $finalOutput = '/var/www/casa/src/api/v2/temp/final_output.mp3';
        $normResult = $processor->normalize(
            $testFile, 
            $finalOutput, 
            array_search($detectedProfile, AudioProfiles::PROFILES)
        );
        
        if ($normResult['success']) {
            echo "{$GREEN}✓{$NC} ";
            echo "LUFS: {$normResult['metrics']['final']['integrated_lufs']} ";
            echo "({$normResult['processing_time_ms']}ms)\n";
        } else {
            echo "{$RED}✗{$NC}\n";
        }
        
        // Paso 4: Validar resultado contra perfil
        echo "  [4/4] Validando contra perfil... ";
        $validation = AudioProfiles::validateAudioAgainstProfile(
            $normResult['metrics']['final'],
            array_search($detectedProfile, AudioProfiles::PROFILES)
        );
        
        if ($validation['valid']) {
            echo "{$GREEN}✓ VÁLIDO{$NC}\n";
        } else {
            echo "{$RED}✗ INVÁLIDO{$NC}\n";
            foreach ($validation['errors'] as $error) {
                echo "       Error: $error\n";
            }
        }
        
        $test5 = $normResult['success'] && $validation['valid'];
        
    } catch (Exception $e) {
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $test5 = false;
    }
} else {
    echo "Test 5: SKIPPED (dependencias no cumplidas)\n";
    $test5 = false;
}

// Test 6: Verificar logs generados
echo "Test 6: Verificar logs generados... ";
$logFiles = [
    'audio-processor.jsonl',
    'rate-limiter.jsonl'
];

$logsFound = 0;
$totalLines = 0;

foreach ($logFiles as $logFile) {
    $fullPath = "/var/www/casa/src/api/v2/logs/$logFile";
    if (file_exists($fullPath)) {
        $logsFound++;
        $lines = count(file($fullPath));
        $totalLines += $lines;
    }
}

if ($logsFound > 0) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    echo "  Archivos de log: $logsFound\n";
    echo "  Total de entradas: $totalLines\n";
    $test6 = true;
} else {
    echo "{$YELLOW}⚠ WARNING{$NC}\n";
    echo "  No se encontraron logs\n";
    $test6 = false;
}

// Limpiar archivos temporales
echo "\nLimpiando archivos temporales... ";
$tempFiles = glob('/var/www/casa/src/api/v2/temp/*.mp3');
foreach ($tempFiles as $file) {
    if (strpos(basename($file), 'test') !== false || strpos(basename($file), 'normalized') !== false) {
        unlink($file);
    }
}
echo "{$GREEN}✓{$NC}\n";

// Resumen final
echo "\n{$BLUE}=== RESUMEN DE INTEGRACIÓN ==={$NC}\n\n";

$tests = [
    'Archivo de prueba' => $test1,
    'Análisis de audio' => $test2,
    'Normalización multi-perfil' => $test3,
    'Configuración multi-radio' => $test4,
    'Flujo completo' => $test5,
    'Logging' => $test6
];

$passed = 0;
$failed = 0;

foreach ($tests as $name => $result) {
    if ($result === true) {
        echo "{$GREEN}✓{$NC} $name\n";
        $passed++;
    } else {
        echo "{$RED}✗{$NC} $name\n";
        $failed++;
    }
}

echo "\n{$YELLOW}=== ESTADÍSTICAS FINALES ==={$NC}\n";
echo "Tests pasados: $passed/" . ($passed + $failed) . "\n";
echo "Porcentaje de éxito: " . round(($passed / max(1, $passed + $failed)) * 100) . "%\n";

if ($passed === count($tests)) {
    echo "\n{$GREEN}✅ SISTEMA COMPLETAMENTE FUNCIONAL{$NC}\n";
    echo "Todos los componentes están trabajando correctamente.\n";
} elseif ($passed >= 4) {
    echo "\n{$YELLOW}⚠️  SISTEMA PARCIALMENTE FUNCIONAL{$NC}\n";
    echo "La mayoría de componentes funcionan pero hay algunos problemas.\n";
} else {
    echo "\n{$RED}❌ SISTEMA REQUIERE ATENCIÓN{$NC}\n";
    echo "Varios componentes críticos no están funcionando.\n";
}

echo "\n";
exit($failed > 0 ? 1 : 0);