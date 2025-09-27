<?php
/**
 * AudioProfiles Service
 * Define y gestiona perfiles de audio para diferentes contextos
 * 
 * @version 2.0.0
 */

namespace App\Services;

class AudioProfiles {
    
    // Perfiles estándar de audio según especificaciones del jefe
    const PROFILES = [
        'message' => [
            'name' => 'Mensaje Estándar',
            'description' => 'Para mensajes informativos generales',
            'target_lufs' => -16,
            'target_tp' => -1.5,
            'target_lra' => 7,
            'priority' => 'normal',
            'color' => '#4CAF50'
        ],
        'jingle' => [
            'name' => 'Jingle Musical',
            'description' => 'Para jingles con música de fondo',
            'target_lufs' => -14,
            'target_tp' => -1.5,
            'target_lra' => 10,
            'priority' => 'normal',
            'color' => '#2196F3'
        ],
        'emergency' => [
            'name' => 'Emergencia',
            'description' => 'Mensajes urgentes y de emergencia',
            'target_lufs' => -12,
            'target_tp' => -1.0,
            'target_lra' => 5,
            'priority' => 'high',
            'color' => '#F44336'
        ],
        'announcement' => [
            'name' => 'Anuncio Importante',
            'description' => 'Anuncios que requieren mayor presencia',
            'target_lufs' => -16,  // Cambiado para consistencia con el mapeo de categorías
            'target_tp' => -1.5,
            'target_lra' => 8,
            'priority' => 'normal',
            'color' => '#FF9800'
        ],
        'background' => [
            'name' => 'Música de Fondo',
            'description' => 'Música ambiental continua',
            'target_lufs' => -20,
            'target_tp' => -2.0,
            'target_lra' => 12,
            'priority' => 'low',
            'color' => '#9C27B0'
        ],
        'podcast' => [
            'name' => 'Podcast/Conversación',
            'description' => 'Contenido hablado largo',
            'target_lufs' => -16,
            'target_tp' => -1.0,
            'target_lra' => 9,
            'priority' => 'normal',
            'color' => '#795548'
        ]
    ];
    
    // Mapeo de categorías a perfiles
    const CATEGORY_MAPPING = [
        'promociones' => 'announcement',
        'informativos' => 'message',
        'eventos' => 'announcement',
        'emergencias' => 'emergency',
        'musica' => 'background',
        'jingles' => 'jingle'
    ];
    
    /**
     * Obtiene un perfil por su ID
     */
    public static function getProfile($profileId) {
        return self::PROFILES[$profileId] ?? self::PROFILES['message'];
    }
    
    /**
     * Obtiene perfil basado en categoría
     */
    public static function getProfileByCategory($category) {
        $profileId = self::CATEGORY_MAPPING[strtolower($category)] ?? 'message';
        return self::getProfile($profileId);
    }
    
    /**
     * Determina el perfil automáticamente basado en contexto
     */
    public static function autoDetectProfile($context = []) {
        // Si se especifica explícitamente
        if (isset($context['profile'])) {
            return self::getProfile($context['profile']);
        }
        
        // Por categoría
        if (isset($context['category'])) {
            return self::getProfileByCategory($context['category']);
        }
        
        // Por tipo de contenido
        if (isset($context['has_music']) && $context['has_music']) {
            return self::getProfile('jingle');
        }
        
        // Por prioridad
        if (isset($context['urgent']) && $context['urgent']) {
            return self::getProfile('emergency');
        }
        
        // Por duración (contenido largo = podcast)
        if (isset($context['duration']) && $context['duration'] > 60) {
            return self::getProfile('podcast');
        }
        
        // Default
        return self::getProfile('message');
    }
    
    /**
     * Valida si un audio cumple con el perfil
     */
    public static function validateAudioAgainstProfile($audioMetrics, $profileId) {
        $profile = self::getProfile($profileId);
        $tolerance = 2.0; // ±2 LUFS de tolerancia
        
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        // Verificar LUFS integrado
        $lufsDiff = abs($audioMetrics['integrated_lufs'] - $profile['target_lufs']);
        if ($lufsDiff > $tolerance) {
            $validation['valid'] = false;
            $validation['errors'][] = sprintf(
                'LUFS fuera de rango: %.1f (objetivo: %.1f ±%.1f)',
                $audioMetrics['integrated_lufs'],
                $profile['target_lufs'],
                $tolerance
            );
        } elseif ($lufsDiff > 1.0) {
            $validation['warnings'][] = sprintf(
                'LUFS cerca del límite: %.1f',
                $audioMetrics['integrated_lufs']
            );
        }
        
        // Verificar True Peak
        if ($audioMetrics['true_peak'] > $profile['target_tp']) {
            $validation['valid'] = false;
            $validation['errors'][] = sprintf(
                'True Peak excede límite: %.1f dB (máximo: %.1f dB)',
                $audioMetrics['true_peak'],
                $profile['target_tp']
            );
        }
        
        // Verificar LRA (Loudness Range)
        $lraDiff = abs($audioMetrics['lra'] - $profile['target_lra']);
        if ($lraDiff > 5.0) {
            $validation['warnings'][] = sprintf(
                'Rango dinámico atípico: %.1f LU (esperado: %.1f)',
                $audioMetrics['lra'],
                $profile['target_lra']
            );
        }
        
        return $validation;
    }
    
    /**
     * Obtiene recomendaciones de procesamiento para un audio
     */
    public static function getProcessingRecommendations($audioMetrics, $targetProfile) {
        $profile = self::getProfile($targetProfile);
        $recommendations = [];
        
        $lufsDiff = $audioMetrics['integrated_lufs'] - $profile['target_lufs'];
        
        if (abs($lufsDiff) > 0.5) {
            if ($lufsDiff > 0) {
                $recommendations[] = [
                    'action' => 'reduce_gain',
                    'amount_db' => -$lufsDiff,
                    'reason' => 'Audio demasiado alto'
                ];
            } else {
                $recommendations[] = [
                    'action' => 'increase_gain',
                    'amount_db' => abs($lufsDiff),
                    'reason' => 'Audio demasiado bajo'
                ];
            }
        }
        
        if ($audioMetrics['true_peak'] > $profile['target_tp']) {
            $recommendations[] = [
                'action' => 'apply_limiter',
                'ceiling_db' => $profile['target_tp'],
                'reason' => 'Prevenir clipping'
            ];
        }
        
        if ($audioMetrics['lra'] > $profile['target_lra'] + 3) {
            $recommendations[] = [
                'action' => 'apply_compression',
                'ratio' => '3:1',
                'reason' => 'Reducir rango dinámico'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Exporta configuración de perfiles para frontend
     */
    public static function exportForFrontend() {
        $export = [];
        
        foreach (self::PROFILES as $id => $profile) {
            $export[] = [
                'id' => $id,
                'name' => $profile['name'],
                'description' => $profile['description'],
                'priority' => $profile['priority'],
                'color' => $profile['color'],
                'technical' => [
                    'lufs' => $profile['target_lufs'],
                    'peak' => $profile['target_tp'],
                    'range' => $profile['target_lra']
                ]
            ];
        }
        
        return $export;
    }
    
    /**
     * Genera reporte de uso de perfiles
     */
    public static function generateUsageReport($startDate = null, $endDate = null) {
        // Este método se conectaría a la BD para generar estadísticas
        // Por ahora retorna datos de ejemplo
        return [
            'period' => [
                'start' => $startDate ?? date('Y-m-d', strtotime('-30 days')),
                'end' => $endDate ?? date('Y-m-d')
            ],
            'usage_by_profile' => [
                'message' => 245,
                'jingle' => 89,
                'emergency' => 3,
                'announcement' => 67,
                'background' => 12,
                'podcast' => 5
            ],
            'average_lufs_by_profile' => [
                'message' => -16.2,
                'jingle' => -14.1,
                'emergency' => -12.3,
                'announcement' => -14.5,
                'background' => -19.8,
                'podcast' => -16.4
            ],
            'compliance_rate' => 0.94 // 94% de audios dentro de especificaciones
        ];
    }
}