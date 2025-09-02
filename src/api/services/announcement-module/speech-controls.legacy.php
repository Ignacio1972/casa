<?php
/**
 * Speech Controls - Control de velocidad, énfasis, pausas y modulación
 * VERSIÓN COMPLETA CON TODAS LAS FUNCIONES CONECTADAS
 */

// Función de logging si no existe
if (!function_exists('logMessage')) {
    function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logFile = dirname(__DIR__, 2) . '/logs/tts-' . date('Y-m-d') . '.log';
        
        // Crear directorio de logs si no existe
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, "[$timestamp] [TEST] $message\n", FILE_APPEND | LOCK_EX);
    }
}

class SpeechControls {
    
    // Velocidades predefinidas - CORREGIDO PARA ELEVENLABS
    const SPEEDS = [
        'muy_lento' => '50%',
        'lento' => '75%',
        'normal' => '100%',
        'rapido' => '125%',
        'muy_rapido' => '150%',
        'emergencia' => '150%'
    ];
    
    // Palabras a enfatizar por idioma - AMPLIADAS
    const EMPHASIS_WORDS = [
        'es' => [
            // Descuentos y ofertas
            'gratis', 'descuento', 'oferta', 'promoción', 'rebaja', 'liquidación',
            '%', 'por ciento', 'mitad de precio', '2x1', '3x2', 'regalo',
            // Urgencia
            'hoy', 'ahora', 'último', 'última', 'últimos', 'últimas',
            'solo hoy', 'termina', 'finaliza', 'apúrate', 'corre', 'imperdible',
            // Importancia
            'importante', 'atención', 'aviso', 'urgente', 'emergencia',
            'nuevo', 'nueva', 'exclusivo', 'exclusiva', 'limitado', 'limitada',
            // Celebraciones
            'feliz', 'felicidades', 'celebra', 'celebramos', 'especial', 'gran', 
            'increíble', 'maravilloso', 'fantástico', 'espectacular',
            // Mall específico
            'mol', 'barrio independencia', 'evento', 'show', 'estreno'
        ],
        'en' => [
            'free', 'discount', 'sale', 'offer', 'promotion', 'clearance',
            '%', 'percent', 'half price', 'buy one get one',
            'today', 'now', 'last', 'final', 'hurry', 'limited',
            'important', 'attention', 'notice', 'urgent', 'emergency',
            'new', 'exclusive', 'special', 'happy', 'celebrate', 'amazing'
        ]
    ];
    
    /**
     * Aplica control de velocidad al texto
     */
    public static function applySpeed($text, $speed = 'normal') {
        $speedValue = self::SPEEDS[$speed] ?? self::SPEEDS['normal'];
        logMessage("[SPEECH-CONTROLS] Aplicando velocidad: $speed = $speedValue");
    //    return sprintf('<prosody rate="%s">%s</prosody>', $speedValue, $text);
    return $text;
    }
    
    /**
     * Aplica énfasis automático a palabras clave - MEJORADO
     */
    public static function applyEmphasis($text, $language = 'es', $customWords = []) {
        // Combinar palabras predefinidas con personalizadas
        $words = array_merge(
            self::EMPHASIS_WORDS[$language] ?? self::EMPHASIS_WORDS['es'],
            $customWords
        );
        
        // Aplicar énfasis a cada palabra
        foreach ($words as $word) {
            // Patrón para palabra completa (case insensitive)
            $pattern = '/\b(' . preg_quote($word, '/') . ')\b/iu';
            $text = preg_replace(
                $pattern,
                '<emphasis level="strong">$1</emphasis>',
                $text
            );
        }
        
        // Énfasis especial para precios
        $text = preg_replace(
            '/\$\s*(\d+(?:[.,]\d{2})?)/u',
            '<emphasis level="strong"><say-as interpret-as="currency">$$$1</say-as></emphasis>',
            $text
        );
        
        // Énfasis para porcentajes
        $text = preg_replace(
            '/(\d+)\s*%/u',
            '<emphasis level="strong"><say-as interpret-as="percentage">$1%</say-as></emphasis>',
            $text
        );
        
        // Énfasis para signos de exclamación (más emoción)
        $text = preg_replace(
            '/¡([^!]+)!/u',
            '<prosody pitch="+10%" volume="+2dB">¡$1!</prosody>',
            $text
        );
        
        return $text;
    }
    
    /**
     * Aplica énfasis avanzado con niveles personalizables
     */
    public static function applyAdvancedEmphasis($text, $emphasisSettings, $language = 'es') {
        // Si el énfasis está deshabilitado, retornar texto sin cambios
        if (!$emphasisSettings['enabled']) {
            return $text;
        }
        
        // Obtener nivel de énfasis (soft, medium, strong)
        $level = $emphasisSettings['level'] ?? 'medium';
        
        // Combinar palabras predefinidas con custom
        $words = array_merge(
            self::EMPHASIS_WORDS[$language] ?? self::EMPHASIS_WORDS['es'],
            $emphasisSettings['custom_words'] ?? []
        );
        
        // Aplicar énfasis a cada palabra con el nivel especificado
        foreach ($words as $word) {
            $pattern = '/\b(' . preg_quote($word, '/') . ')\b/iu';
            $text = preg_replace(
                $pattern,
                '<emphasis level="' . $level . '">$1</emphasis>',
                $text
            );
        }
        
        // Énfasis automático para números si está habilitado
        if ($emphasisSettings['auto_numbers'] ?? true) {
            $text = preg_replace(
                '/\b(\d+)\b/u',
                '<emphasis level="' . $level . '">$1</emphasis>',
                $text
            );
        }
        
        // Énfasis automático para precios si está habilitado
        if ($emphasisSettings['auto_prices'] ?? true) {
            $text = preg_replace(
                '/\$\s*(\d+(?:[.,]\d{2})?)/u',
                '<emphasis level="' . $level . '"><say-as interpret-as="currency">$$$1</say-as></emphasis>',
                $text
            );
        }
        
        // Mantener el énfasis especial para exclamaciones
        $text = preg_replace(
            '/¡([^!]+)!/u',
            '<prosody pitch="+10%" volume="+2dB">¡$1!</prosody>',
            $text
        );
        
        return $text;
    }
    
    /**
     * Agrega pausas inteligentes - MEJORADAS
     */
    public static function addPauses($text, $pauseLength = 'medium') {
        $pauses = [
            'short' => '0.3s',
            'medium' => '0.5s',
            'long' => '1s'
        ];
        
        $pause = $pauses[$pauseLength] ?? $pauses['medium'];
        
        // Pausas después de puntuación
        $text = str_replace('. ', '. <break time="' . $pause . '"/> ', $text);
        $text = str_replace('! ', '! <break time="' . $pause . '"/> ', $text);
        $text = str_replace('? ', '? <break time="' . $pause . '"/> ', $text);
        $text = str_replace(', ', ', <break time="200ms"/> ', $text);
        $text = str_replace(': ', ': <break time="300ms"/> ', $text);
        
        // Pausa especial después de "Mol,"
        $text = str_replace('Mol,', 'Mol<break time="100ms"/>,', $text);
        
        return $text;
    }
    
    /**
     * Aplica pausas avanzadas con duraciones personalizables
     */
    public static function applyAdvancedPauses($text, $pauseSettings) {
        // Si las pausas están deshabilitadas, retornar texto sin cambios
        if (!$pauseSettings['enabled']) {
            return $text;
        }
        
        // Obtener duraciones personalizadas o usar defaults
        $sentencePause = $pauseSettings['sentence'] ?? 0.5;
        $commaPause = $pauseSettings['comma'] ?? 0.2;
        
        // Pausas después de puntuación con valores custom
        $text = str_replace('. ', '. <break time="' . $sentencePause . 's"/> ', $text);
        $text = str_replace('! ', '! <break time="' . $sentencePause . 's"/> ', $text);
        $text = str_replace('? ', '? <break time="' . $sentencePause . 's"/> ', $text);
        $text = str_replace(', ', ', <break time="' . $commaPause . 's"/> ', $text);
        
        // Pausas para dos puntos (mitad entre coma y punto)
        $colonPause = ($sentencePause + $commaPause) / 2;
        $text = str_replace(': ', ': <break time="' . $colonPause . 's"/> ', $text);
        
        // Marcadores personalizados || para pausas de 1 segundo
        if (isset($pauseSettings['custom_markers']) && is_array($pauseSettings['custom_markers'])) {
            // Si hay marcadores en el array, procesarlos
            foreach ($pauseSettings['custom_markers'] as $marker) {
                $text = str_replace($marker, '<break time="1s"/>', $text);
            }
        }
        // También procesar || como marcador estándar
        $text = str_replace('||', '<break time="1s"/>', $text);
        
        // Mantener pausa especial para "Mol,"
        $text = str_replace('Mol,', 'Mol<break time="100ms"/>,', $text);
        
        return $text;
    }
    
    /**
     * NUEVO: Aplica modulación de voz (pitch y volume)
     */
    public static function applyModulation($text, $modulationSettings) {
        // Obtener valores o usar defaults
        $pitch = $modulationSettings['pitch'] ?? 0;
        $volume = $modulationSettings['volume'] ?? 0;
        $dynamicRate = $modulationSettings['dynamic_rate'] ?? false;
        
        logMessage("Aplicando modulación: pitch=$pitch%, volume=$volume dB, dynamic=$dynamicRate");
        
        // Si no hay cambios, retornar texto sin modificar
        if ($pitch == 0 && $volume == 0 && !$dynamicRate) {
            return $text;
        }
        
        // Construir atributos de prosody
        $prosodyAttrs = [];
        
        // Pitch (-20% a +20%)
        if ($pitch != 0) {
            $pitchStr = $pitch > 0 ? "+{$pitch}%" : "{$pitch}%";
            $prosodyAttrs[] = 'pitch="' . $pitchStr . '"';
        }
        
        // Volume (-6dB a +6dB)
        if ($volume != 0) {
            $volumeStr = $volume > 0 ? "+{$volume}dB" : "{$volume}dB";
            $prosodyAttrs[] = 'volume="' . $volumeStr . '"';
        }
        
        // Si hay rate dinámico, aplicar variaciones
        if ($dynamicRate) {
            // Dividir el texto en frases y aplicar velocidades variables
            $sentences = preg_split('/(?<=[.!?])\s+/', $text);
            $processedSentences = [];
            
            foreach ($sentences as $index => $sentence) {
                if (empty(trim($sentence))) continue;
                
                // Alternar velocidades para crear dinamismo
                $rates = ['90%', '100%', '110%', '95%', '105%'];
                $rate = $rates[$index % count($rates)];
                
                if (!empty($prosodyAttrs)) {
                    $processedSentences[] = sprintf(
                        '<prosody %s rate="%s">%s</prosody>',
                        implode(' ', $prosodyAttrs),
                        $rate,
                        $sentence
                    );
                } else {
                    $processedSentences[] = sprintf(
                        '<prosody rate="%s">%s</prosody>',
                        $rate,
                        $sentence
                    );
                }
            }
            
            return implode(' ', $processedSentences);
        }
        
        // Aplicar prosody normal (sin rate dinámico)
        if (!empty($prosodyAttrs)) {
            return sprintf(
                '<prosody %s>%s</prosody>',
                implode(' ', $prosodyAttrs),
                $text
            );
        }
        
        return $text;
    }
    
    /**
     * Procesa texto completo con todos los controles - ACTUALIZADO
     */
    public static function processText($text, $options = []) {
        logMessage("[SPEECH-CONTROLS] processText llamado");
        logMessage("[SPEECH-CONTROLS] Options: " . json_encode($options));
        logMessage("[SPEECH-CONTROLS] Speed: " . ($options['speed'] ?? 'NO DEFINIDO'));
        
        $defaults = [
            'speed' => 'normal',
            'language' => 'es',
            'emphasis' => true,
            'emphasis_words' => [],
            'pauses' => true,
            'pause_length' => 'medium',
            'emotion' => 'neutral'
        ];
        
        $options = array_merge($defaults, $options);
        
        // Log para debugging
        logMessage("Speech Controls - processText options: " . json_encode($options));
        
        // NUEVO: Aplicar modulación PRIMERO (antes de otros efectos)
      //  if (isset($options['modulation_settings'])) {
        //    logMessage("Procesando modulación de voz");
          //  $text = self::applyModulation($text, $options['modulation_settings']);
        // }
        
        // Aplicar emoción según preset
        $text = self::applyEmotion($text, $options['emotion']);
        
        // Aplicar énfasis - ACTUALIZADO para usar método avanzado si está disponible
        if ($options['emphasis']) {
            if (isset($options['emphasis_settings'])) {
                logMessage("Usando énfasis avanzado");
                $text = self::applyAdvancedEmphasis($text, $options['emphasis_settings'], $options['language']);
            } else {
                logMessage("Usando énfasis simple");
                $text = self::applyEmphasis($text, $options['language'], $options['emphasis_words']);
            }
        }
        
        // Agregar pausas - ACTUALIZADO para usar método avanzado si está disponible
        if ($options['pauses']) {
            if (isset($options['pause_settings'])) {
                logMessage("Usando pausas avanzadas");
                $text = self::applyAdvancedPauses($text, $options['pause_settings']);
            } else {
                logMessage("Usando pausas simples");
                $text = self::addPauses($text, $options['pause_length']);
            }
        }
        
        // Aplicar velocidad AL FINAL (envuelve todo)
        $text = self::applySpeed($text, $options['speed']);
        
        // Log del texto final procesado
        logMessage("[SPEECH-CONTROLS] Texto procesado final: " . $text);
        
        // Envolver en SSML
        return '<speak>' . $text . '</speak>';
    }
    
    /**
     * Aplica características emocionales al texto
     */
    public static function applyEmotion($text, $emotion) {
        $emotions = [
            'celebracion' => [
                'pitch' => '+10%',
                'volume' => '+2dB',
                'rate' => '105%'
            ],
            'urgente' => [
                'pitch' => '+5%',
                'volume' => '+3dB',
                'rate' => '110%'
            ],
            'calma' => [
                'pitch' => '-5%',
                'volume' => 'medium',
                'rate' => '95%'
            ],
            'formal' => [
                'pitch' => 'medium',
                'volume' => 'medium',
                'rate' => '100%'
            ]
        ];
        
        if (isset($emotions[$emotion])) {
            $emo = $emotions[$emotion];
            $text = sprintf(
                '<prosody pitch="%s" volume="%s" rate="%s">%s</prosody>',
                $emo['pitch'],
                $emo['volume'],
                $emo['rate'],
                $text
            );
        }
        
        return $text;
    }
    
    /**
     * Presets para diferentes tipos de anuncios - MEJORADOS
     */
    public static function getPreset($type) {
        $presets = [
            'oferta' => [
                'speed' => 'normal',
                'emphasis' => true,
                'pauses' => true,
                'pause_length' => 'medium',
                'emotion' => 'urgente'
            ],
            'emergencia' => [
                'speed' => 'rapido',
                'emphasis' => true,
                'pauses' => false,
                'pause_length' => 'short',
                'emotion' => 'urgente'
            ],
            'informativo' => [
                'speed' => 'normal',
                'emphasis' => false,
                'pauses' => true,
                'pause_length' => 'long',
                'emotion' => 'formal'
            ],
            'cierre' => [
                'speed' => 'lento',
                'emphasis' => true,
                'pauses' => true,
                'pause_length' => 'long',
                'emotion' => 'calma'
            ],
            'celebracion' => [
                'speed' => 'normal',
                'emphasis' => true,
                'pauses' => true,
                'pause_length' => 'medium',
                'emotion' => 'celebracion'
            ],
            'recordatorio' => [
                'speed' => 'lento',
                'emphasis' => false,
                'pauses' => true,
                'pause_length' => 'long',
                'emotion' => 'calma'
            ]
        ];
        
        return $presets[$type] ?? $presets['informativo'];
    }
}
?>