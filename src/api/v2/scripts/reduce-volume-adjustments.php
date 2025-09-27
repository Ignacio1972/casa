#!/usr/bin/env php
<?php
/**
 * Script para reducir ajustes de volumen al 50%
 * Parte de la migración progresiva al sistema v2
 */

$configFile = '/var/www/casa/src/api/data/voices-config.json';

echo "\n\033[1;36m=== REDUCIENDO AJUSTES DE VOLUMEN AL 50% ===\033[0m\n\n";

// Leer configuración actual
$config = json_decode(file_get_contents($configFile), true);

if (!$config) {
    die("\033[0;31mError: No se pudo leer el archivo de configuración\033[0m\n");
}

echo "📊 AJUSTES ACTUALES → NUEVOS:\n";
echo "─────────────────────────────────\n";

$changes = [];

foreach ($config['voices'] as $voiceKey => &$voice) {
    $oldAdjustment = $voice['volume_adjustment'] ?? 0;
    
    // Reducir al 50%, redondeando a 1 decimal
    $newAdjustment = round($oldAdjustment * 0.5, 1);
    
    // Solo cambiar si hay diferencia
    if ($oldAdjustment != 0) {
        $voice['volume_adjustment'] = $newAdjustment;
        
        $label = $voice['label'] ?? $voiceKey;
        $active = $voice['active'] ?? false;
        
        if ($active) {
            $color = abs($newAdjustment) > 2 ? "\033[0;33m" : "\033[0;32m";
            
            printf("%-15s: %s%.1f dB → %.1f dB\033[0m %s\n",
                $label,
                $color,
                $oldAdjustment,
                $newAdjustment,
                $active ? "✓" : ""
            );
            
            $changes[] = [
                'voice' => $voiceKey,
                'label' => $label,
                'old' => $oldAdjustment,
                'new' => $newAdjustment
            ];
        }
    }
}

// Actualizar timestamp
$config['settings']['last_updated'] = date('c');
$config['settings']['migration_note'] = 'Volume adjustments reduced by 50% for v2 migration - ' . date('Y-m-d H:i:s');

// Guardar cambios
if (count($changes) > 0) {
    echo "\n\033[1;33m⚠️  Aplicando cambios...\033[0m\n";
    
    if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
        echo "\033[0;32m✓ Archivo actualizado exitosamente\033[0m\n";
        
        echo "\n📝 RESUMEN DE CAMBIOS:\n";
        echo "─────────────────────────────────\n";
        foreach ($changes as $change) {
            printf("• %s: %.1f → %.1f dB (reducción de %.1f dB)\n",
                $change['label'],
                $change['old'],
                $change['new'],
                abs($change['old'] - $change['new'])
            );
        }
        
        echo "\n\033[1;32m✅ MIGRACIÓN COMPLETADA\033[0m\n";
        echo "─────────────────────────────────\n";
        echo "• " . count($changes) . " voces actualizadas\n";
        echo "• Los ajustes se reducieron al 50%\n";
        echo "• La normalización LUFS hará el trabajo principal\n";
        echo "• Los ajustes ahora son solo para afinar\n";
        echo "• El playground sigue funcionando para ajustes futuros\n";
        
    } else {
        echo "\033[0;31m✗ Error al guardar el archivo\033[0m\n";
        exit(1);
    }
} else {
    echo "\n\033[0;33mNo hay ajustes que modificar\033[0m\n";
}

echo "\n";