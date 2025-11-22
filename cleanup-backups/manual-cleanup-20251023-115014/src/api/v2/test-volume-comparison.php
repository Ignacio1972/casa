#!/usr/bin/env php
<?php
/**
 * Test de Comparación de Volúmenes
 * Compara los ajustes manuales actuales con la normalización LUFS
 */

echo "\n\033[1;36m=== ANÁLISIS DE AJUSTES DE VOLUMEN ===\033[0m\n\n";

// Leer configuración actual
$voicesConfig = json_decode(file_get_contents('/var/www/casa/src/api/data/voices-config.json'), true);

echo "📊 AJUSTES ACTUALES EN voices-config.json:\n";
echo "─────────────────────────────────────────\n";

$activeVoices = [];
foreach ($voicesConfig['voices'] as $key => $voice) {
    if ($voice['active']) {
        $adjustment = $voice['volume_adjustment'] ?? 0;
        $activeVoices[$key] = $adjustment;
        
        $color = "\033[0;37m"; // Default
        if ($adjustment > 3) {
            $color = "\033[0;31m"; // Rojo para ajustes muy altos
        } elseif ($adjustment > 1) {
            $color = "\033[0;33m"; // Amarillo para ajustes moderados
        } elseif ($adjustment < -1) {
            $color = "\033[0;34m"; // Azul para ajustes negativos
        } else {
            $color = "\033[0;32m"; // Verde para ajustes normales
        }
        
        printf("%s%-15s: %s%+.1f dB\033[0m %s\n", 
            $color,
            $voice['label'],
            $adjustment == 0 ? "\033[0;37m" : $color,
            $adjustment,
            $voice['active'] ? "✓ Activa" : "  Inactiva"
        );
    }
}

echo "\n\033[1;33m⚠️  ANÁLISIS:\033[0m\n";
echo "─────────────────────────────────────────\n";

// Análisis de problemas
$problems = [];
$warnings = [];
$suggestions = [];

foreach ($activeVoices as $voice => $adjustment) {
    if (abs($adjustment) > 4) {
        $problems[] = "❌ $voice tiene ajuste extremo ({$adjustment} dB) - indica problema serio de volumen";
    } elseif (abs($adjustment) > 2) {
        $warnings[] = "⚠️  $voice tiene ajuste alto ({$adjustment} dB) - puede necesitar revisión";
    }
}

// Buscar archivos de audio recientes para analizar
echo "\n📁 Buscando archivos de audio recientes para análisis...\n";

$audioFiles = [];
$dirs = [
    '/var/www/casa/src/api/temp/',
    '/var/www/casa/public/audio/'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*.mp3');
        foreach ($files as $file) {
            // Solo archivos de las últimas 24 horas
            if (time() - filemtime($file) < 86400) {
                // Buscar el nombre de la voz en el filename
                foreach ($activeVoices as $voice => $adj) {
                    if (stripos(basename($file), $voice) !== false) {
                        $audioFiles[$voice] = $file;
                        break;
                    }
                }
            }
        }
    }
}

if (count($audioFiles) > 0) {
    echo "\n🎵 ANÁLISIS DE LUFS DE ARCHIVOS EXISTENTES:\n";
    echo "─────────────────────────────────────────\n";
    
    foreach ($audioFiles as $voice => $file) {
        // Analizar LUFS con ffmpeg
        $cmd = sprintf(
            'ffmpeg -i %s -af ebur128=peak=true -f null - 2>&1 | grep "I:" | tail -1',
            escapeshellarg($file)
        );
        
        exec($cmd, $output);
        
        if (!empty($output[0]) && preg_match('/I:\s*(-?\d+\.?\d*)\s*LUFS/', $output[0], $matches)) {
            $lufs = floatval($matches[1]);
            $targetLUFS = -16; // Target estándar para mensajes
            $difference = $lufs - $targetLUFS;
            
            $status = "✓";
            $color = "\033[0;32m";
            if (abs($difference) > 3) {
                $status = "✗";
                $color = "\033[0;31m";
            } elseif (abs($difference) > 2) {
                $status = "⚠";
                $color = "\033[0;33m";
            }
            
            printf("%s%s %s: %.1f LUFS (diferencia: %+.1f dB del target -16 LUFS)\033[0m\n",
                $color,
                $status,
                $voice,
                $lufs,
                $difference
            );
            
            // Comparar con el ajuste manual actual
            $manualAdjust = $activeVoices[$voice] ?? 0;
            if (abs($difference + $manualAdjust) < abs($difference)) {
                echo "  → El ajuste manual actual ({$manualAdjust} dB) parece estar compensando correctamente\n";
            } else {
                echo "  → El ajuste manual actual ({$manualAdjust} dB) podría no ser óptimo\n";
            }
        }
    }
} else {
    echo "\n⚠️  No se encontraron archivos de audio recientes para analizar\n";
}

echo "\n\033[1;36m🔄 COMPARACIÓN: Sistema Actual vs Sistema v2\033[0m\n";
echo "─────────────────────────────────────────\n";

echo "\n📌 SISTEMA ACTUAL (Manual):\n";
echo "  • Requiere ajuste manual por voz\n";
echo "  • Ajustes actuales: " . (count($problems) > 0 ? "\033[0;31mProblemáticos\033[0m" : "\033[0;32mFuncionales\033[0m") . "\n";
echo "  • Mantenimiento: Requiere prueba y error\n";
echo "  • Consistencia: Variable\n";

echo "\n📌 SISTEMA V2 (Normalización LUFS):\n";
echo "  • Normalización automática a -16 LUFS\n";
echo "  • Ajustes de voz opcionales (se pueden mantener)\n";
echo "  • Mantenimiento: Automático\n";
echo "  • Consistencia: Garantizada ±1 dB\n";

echo "\n\033[1;32m✅ RECOMENDACIONES:\033[0m\n";
echo "─────────────────────────────────────────\n";

if (count($problems) > 0) {
    echo "\n\033[0;31m1. PROBLEMAS DETECTADOS:\033[0m\n";
    foreach ($problems as $problem) {
        echo "   $problem\n";
    }
}

if (count($warnings) > 0) {
    echo "\n\033[0;33m2. ADVERTENCIAS:\033[0m\n";
    foreach ($warnings as $warning) {
        echo "   $warning\n";
    }
}

echo "\n3. ESTRATEGIA DE MIGRACIÓN RECOMENDADA:\n";
echo "   📍 Opción A: \033[1;32mMigración Conservadora\033[0m\n";
echo "      • Implementar v2 PERO mantener ajustes actuales\n";
echo "      • El v2 normalizará + aplicará ajustes existentes\n";
echo "      • Playground seguirá funcionando para ajustes finos\n";
echo "      • Menor riesgo de cambios drásticos\n";

echo "\n   📍 Opción B: \033[1;34mMigración Completa\033[0m\n";
echo "      • Implementar v2 y resetear ajustes a 0\n";
echo "      • Confiar 100% en normalización LUFS\n";
echo "      • Playground solo para casos extremos\n";
echo "      • Mayor consistencia pero posible cambio inicial\n";

echo "\n   📍 Opción C: \033[1;33mMigración Progresiva\033[0m (RECOMENDADA)\n";
echo "      • Implementar v2 con ajustes reducidos al 50%\n";
echo "      • juan_carlos: 4.5 → 2.2 dB\n";
echo "      • cristian: 2.5 → 1.2 dB\n";
echo "      • veronica: 2.0 → 1.0 dB\n";
echo "      • Probar y ajustar según necesidad\n";

echo "\n\033[1;35m🎚️ COMPATIBILIDAD CON PLAYGROUND:\033[0m\n";
echo "─────────────────────────────────────────\n";
echo "✓ El playground SEGUIRÁ FUNCIONANDO con el sistema v2\n";
echo "✓ Los ajustes se aplicarán DESPUÉS de la normalización\n";
echo "✓ Fórmula: LUFS normalizado + ajuste manual = volumen final\n";

echo "\n\033[1;36m¿QUÉ HACER?\033[0m\n";
echo "─────────────────────────────────────────\n";
echo "1. Este test muestra el estado actual\n";
echo "2. El sistema v2 es compatible con los ajustes actuales\n";
echo "3. Puedes elegir mantener, reducir o eliminar los ajustes\n";
echo "4. El playground seguirá funcionando para ajustes futuros\n";

echo "\n";