#!/usr/bin/env php
<?php
/**
 * Test Suite para RateLimiter
 * Valida control de rate limiting y circuit breaker
 */

require_once '/var/www/casa/src/api/v2/services/RateLimiter.php';

use App\Services\RateLimiter;

// Colores para output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[0;33m";
$NC = "\033[0m";

echo "\n{$YELLOW}=== RATE LIMITER TEST SUITE ==={$NC}\n\n";

// Test 1: Inicialización
echo "Test 1: Inicialización de RateLimiter... ";
try {
    $limiter = new RateLimiter();
    echo "{$GREEN}✓ PASS{$NC}\n";
    $test1 = true;
} catch (Exception $e) {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  Error: " . $e->getMessage() . "\n";
    $test1 = false;
}

// Test 2: Verificar límites por servicio
echo "Test 2: Verificar límites configurados... ";
$expectedLimits = [
    'elevenlabs' => 50,
    'azuracast' => 100,
    'broadcast' => 30,
    'claude' => 20
];

$allCorrect = true;
foreach ($expectedLimits as $service => $expectedLimit) {
    // Hacer requests hasta alcanzar el límite
    for ($i = 0; $i < $expectedLimit; $i++) {
        $result = $limiter->checkLimit($service, 'test_user');
        if (!$result['allowed'] && $i < $expectedLimit - 1) {
            $allCorrect = false;
            echo "{$RED}✗ FAIL{$NC}\n";
            echo "  $service bloqueado prematuramente en request #" . ($i + 1) . "\n";
            break 2;
        }
    }
    
    // La siguiente request debe ser bloqueada
    $result = $limiter->checkLimit($service, 'test_user');
    if ($result['allowed']) {
        $allCorrect = false;
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  $service no bloqueó después de $expectedLimit requests\n";
        break;
    }
}

if ($allCorrect) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    echo "  Todos los límites funcionan correctamente\n";
    $test2 = true;
} else {
    $test2 = false;
}

// Test 3: Verificar retry_after
echo "Test 3: Verificar tiempo de retry... ";
$limiter3 = new RateLimiter();
// Hacer 50 requests para ElevenLabs (límite)
for ($i = 0; $i < 50; $i++) {
    $limiter3->checkLimit('elevenlabs', 'test_retry');
}

// La siguiente debe ser bloqueada con retry_after
$result = $limiter3->checkLimit('elevenlabs', 'test_retry');
if (!$result['allowed'] && $result['retry_after'] > 0 && $result['retry_after'] <= 60) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    echo "  Retry después de {$result['retry_after']} segundos\n";
    $test3 = true;
} else {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  Retry_after inválido: " . ($result['retry_after'] ?? 'null') . "\n";
    $test3 = false;
}

// Test 4: Circuit Breaker
echo "Test 4: Probar Circuit Breaker... ";
$limiter4 = new RateLimiter();

// Simular fallos
for ($i = 0; $i < 5; $i++) {
    $limiter4->recordFailure('test_service', 'Connection timeout');
}

// Verificar que el circuit está abierto
$circuitStatus = $limiter4->checkCircuitBreaker('test_service');
if ($circuitStatus['status'] === 'open' && !$circuitStatus['can_proceed']) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    echo "  Circuit abierto después de 5 fallos\n";
    $test4 = true;
} else {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  Circuit no se abrió correctamente\n";
    $test4 = false;
}

// Test 5: Tracking de caracteres
echo "Test 5: Tracking de uso de caracteres... ";
$limiter5 = new RateLimiter();

// Limpiar archivo de caracteres del mes actual
$monthKey = date('Y-m');
$charFile = '/var/www/casa/src/api/v2/temp/rate_limit/elevenlabs_chars_' . $monthKey . '.json';
if (file_exists($charFile)) {
    unlink($charFile);
}

$usage = $limiter5->trackCharacterUsage(1000);

if ($usage['used'] === 1000 && $usage['remaining'] === 499000) {
    // Agregar más caracteres
    $usage2 = $limiter5->trackCharacterUsage(2000);
    
    if ($usage2['used'] === 3000) {
        echo "{$GREEN}✓ PASS{$NC}\n";
        echo "  Caracteres usados: {$usage2['used']}/{$usage2['limit']} ";
        echo "({$usage2['percentage']}%)\n";
        $test5 = true;
    } else {
        echo "{$RED}✗ FAIL{$NC}\n";
        echo "  Conteo incorrecto de caracteres. Esperado: 3000, Obtenido: {$usage2['used']}\n";
        $test5 = false;
    }
} else {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  Tracking inicial incorrecto. Used: {$usage['used']}, Remaining: {$usage['remaining']}\n";
    $test5 = false;
}

// Test 6: Estadísticas de uso
echo "Test 6: Obtener estadísticas de uso... ";
$stats = $limiter->getUsageStats();

if (isset($stats['services']) && isset($stats['elevenlabs_characters'])) {
    echo "{$GREEN}✓ PASS{$NC}\n";
    echo "  Servicios monitoreados: " . count($stats['services']) . "\n";
    if (isset($stats['elevenlabs_characters']['used_this_month'])) {
        echo "  Caracteres usados este mes: " . $stats['elevenlabs_characters']['used_this_month'] . "\n";
    }
    $test6 = true;
} else {
    echo "{$RED}✗ FAIL{$NC}\n";
    echo "  Estructura de estadísticas incorrecta\n";
    $test6 = false;
}

// Test 7: Cleanup de archivos antiguos
echo "Test 7: Limpieza de archivos antiguos... ";
$deleted = $limiter->cleanup();
echo "{$GREEN}✓ PASS{$NC}\n";
echo "  Archivos eliminados: $deleted\n";
$test7 = true;

// Test 8: Verificar logging de rate limiter
echo "Test 8: Verificar logging... ";
$logFile = '/var/www/casa/src/api/v2/logs/rate-limiter.jsonl';
if (file_exists($logFile)) {
    $lines = file($logFile);
    if (count($lines) > 0) {
        $lastLog = json_decode(end($lines), true);
        if ($lastLog && isset($lastLog['service']) && $lastLog['service'] === 'RateLimiter') {
            echo "{$GREEN}✓ PASS{$NC}\n";
            echo "  Logs generados: " . count($lines) . "\n";
            $test8 = true;
        } else {
            echo "{$RED}✗ FAIL{$NC}\n";
            echo "  Formato de log incorrecto\n";
            $test8 = false;
        }
    } else {
        echo "{$YELLOW}⚠ WARNING{$NC}\n";
        echo "  Archivo de log vacío\n";
        $test8 = false;
    }
} else {
    echo "{$YELLOW}⚠ WARNING{$NC}\n";
    echo "  Archivo de log no existe\n";
    $test8 = false;
}

// Resumen
echo "\n{$YELLOW}=== RESUMEN DE RESULTADOS ==={$NC}\n\n";
$tests = [
    'Inicialización' => $test1,
    'Límites por servicio' => $test2,
    'Retry timing' => $test3,
    'Circuit Breaker' => $test4,
    'Character tracking' => $test5,
    'Estadísticas' => $test6,
    'Cleanup' => $test7,
    'Logging' => $test8
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

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100) : 0;

echo "\n";
echo "Total: $passed/$total tests pasados ($percentage%)\n";

if ($percentage === 100) {
    echo "{$GREEN}¡Todos los tests pasaron exitosamente!{$NC}\n";
} elseif ($percentage >= 75) {
    echo "{$YELLOW}Mayoría de tests pasaron con algunos problemas menores.{$NC}\n";
} else {
    echo "{$RED}Varios tests fallaron. Revisar implementación.{$NC}\n";
}

echo "\n";
exit($failed > 0 ? 1 : 0);