<?php
/**
 * Announcement Generator - VERSIÓN SIMPLIFICADA
 * Solo procesa texto sin SSML, mantiene todo lo demás
 */

require_once __DIR__ . '/announcement-templates.php';
require_once __DIR__ . '/../tts-service-enhanced.php';

class AnnouncementGenerator {
    
    /**
     * Genera un anuncio - SIMPLIFICADO
     */
    public static function generate($options) {
        // Opciones por defecto
        $defaults = [
            'text' => null,
            'template' => null,
            'template_category' => null,
            'template_variables' => [],
            'voice' => 'fernanda',
            'model' => 'eleven_multilingual_v2',
            'voice_settings' => [
                'stability' => 0.75,
                'similarity_boost' => 0.8,
                'style' => 0.5,
                'use_speaker_boost' => true
            ]
        ];
        
        $options = array_merge($defaults, $options);
        
        // PASO 1: Obtener el texto (directo o desde template)
        if ($options['template'] && $options['template_category']) {
            $result = AnnouncementTemplates::generateFromTemplate(
                $options['template_category'],
                $options['template'],
                $options['template_variables']
            );
            
            $text = $result['text'];
            logMessage("Usando template: {$result['template_name']}");
        } else {
            $text = $options['text'];
            if (empty($text)) {
                throw new Exception('Debe proporcionar texto o seleccionar un template');
            }
        }
        
        logMessage("Texto a generar: $text");
        
        // PASO 2: Ajustar voice_settings según el tipo de anuncio
        $voiceSettings = self::optimizeVoiceSettings(
            $options['voice_settings'],
            $options['template'] ?? null
        );
        
        // PASO 3: Generar el audio con ElevenLabs
        $ttsOptions = [
            'model_id' => $options['model'],
            'voice_settings' => $voiceSettings
        ];

        // Agregar soporte para v3 si fue solicitado
        if (isset($options['use_v3']) && $options['use_v3'] === true) {
            $ttsOptions['use_v3'] = true;
        }

        // NUEVO: Pasar output_volume si está configurado
        if (isset($options['output_volume'])) {
            $ttsOptions['output_volume'] = $options['output_volume'];
            logMessage("Pasando output_volume al TTS: " . $options['output_volume']);
        }

        // Generar audio
        $audioData = generateEnhancedTTS($text, $options['voice'], $ttsOptions);
        
        return [
            'audio' => $audioData,
            'processed_text' => $text, // Ya no hay procesamiento SSML
            'original_text' => $text,
            'voice' => $options['voice'],
            'settings_used' => $voiceSettings
        ];
    }
    
    /**
     * Optimiza voice_settings según el tipo de anuncio
     * MANTIENE LA LÓGICA ORIGINAL PERO SIMPLIFICADA
     */
    private static function optimizeVoiceSettings($baseSettings, $template = null) {
        // Crear copia de settings base
        $settings = [
            'stability' => $baseSettings['stability'] ?? 0.75,
            'similarity_boost' => $baseSettings['similarity_boost'] ?? 0.8,
            'style' => $baseSettings['style'] ?? 0.5,
            'use_speaker_boost' => $baseSettings['use_speaker_boost'] ?? true
        ];
        
        // Solo ajustar style si viene un template específico
        if (!empty($template)) {
            logMessage("Optimizando style para template: $template");
            
            // Celebraciones - Muy expresivo (0.8)
            if (strpos($template, 'dia_madre') !== false || 
                strpos($template, 'dia_padre') !== false || 
                strpos($template, 'dia_nino') !== false ||
                strpos($template, 'celebracion') !== false ||
                strpos($template, 'evento_general') !== false ||
                strpos($template, 'evento_infantil') !== false ||
                strpos($template, 'show_infantil') !== false ||
                strpos($template, 'taller_infantil') !== false) {
                $settings['style'] = 0.8;
                logMessage("Aplicando style celebración: 0.8");
                
            // Ofertas y Cine - Energético (0.6)
            } elseif (strpos($template, 'oferta') !== false || 
                      strpos($template, 'descuento') !== false || 
                      strpos($template, 'liquidacion') !== false ||
                      strpos($template, 'dos_por_uno') !== false ||
                      strpos($template, 'estreno') !== false ||
                      strpos($template, 'promocion_cine') !== false) {
                $settings['style'] = 0.6;
                logMessage("Aplicando style oferta: 0.6");
                
            // Recordatorios - Formal (0.2)
            } elseif (strpos($template, 'recordatorio') !== false || 
                      strpos($template, 'seguridad') !== false ||
                      strpos($template, 'estacionamiento') !== false) {
                $settings['style'] = 0.2;
                logMessage("Aplicando style recordatorio: 0.2");
                
            // Emergencias - Urgente (0.7)
            } elseif (strpos($template, 'emergencia') !== false || 
                      strpos($template, 'evacuacion') !== false) {
                $settings['style'] = 0.7;
                logMessage("Aplicando style emergencia: 0.7");
                
            // Servicios - Claro (0.3)
            } elseif (strpos($template, 'llamado_caja') !== false || 
                      strpos($template, 'vehiculo') !== false ||
                      strpos($template, 'niño_perdido') !== false) {
                $settings['style'] = 0.3;
                logMessage("Aplicando style servicios: 0.3");
                
            // Horarios - Neutral (0.4)
            } elseif (strpos($template, 'apertura') !== false || 
                      strpos($template, 'cierre') !== false) {
                $settings['style'] = 0.4;
                logMessage("Aplicando style horarios: 0.4");
            }
        }
        
        // Asegurar que todos los valores estén en rango correcto
        $settings['stability'] = max(0.0, min(1.0, floatval($settings['stability'])));
        $settings['similarity_boost'] = max(0.0, min(1.0, floatval($settings['similarity_boost'])));
        $settings['style'] = max(0.0, min(1.0, floatval($settings['style'])));
        
        logMessage("Voice settings finales: " . json_encode($settings));
        
        return $settings;
    }
    
    /**
     * Genera anuncio simple (texto directo)
     */
    public static function generateSimple($text, $voice = 'fernanda', $options = []) {
        $fullOptions = array_merge([
            'text' => $text,
            'voice' => $voice
        ], $options);
        
        return self::generate($fullOptions);
    }
    
    /**
     * Genera anuncio desde template
     */
    public static function generateFromTemplate($category, $templateId, $variables, $voice = 'fernanda', $options = []) {
        $fullOptions = array_merge([
            'template_category' => $category,
            'template' => $templateId,
            'template_variables' => $variables,
            'voice' => $voice
        ], $options);
        
        return self::generate($fullOptions);
    }
}

/**
 * Función helper para uso rápido
 */
function generateAnnouncement($options) {
    try {
        $result = AnnouncementGenerator::generate($options);
        return [
            'success' => true,
            'audio' => $result['audio'],
            'text' => $result['original_text'],
            'processed_text' => $result['processed_text']
        ];
    } catch (Exception $e) {
        logMessage("Error en generateAnnouncement: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>