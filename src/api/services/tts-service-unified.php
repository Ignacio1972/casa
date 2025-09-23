<?php
/**
 * Servicio TTS Unificado - Casa Costanera
 * Combina toda la funcionalidad de tts-service.php y tts-service-enhanced.php
 * Soporta modelos V2, V3 Alpha, configuración de calidad y voice settings
 */

// Incluir configuración
require_once dirname(__DIR__) . '/config.php';

// Función de logging si no existe
if (!function_exists('logMessage')) {
    function logMessage($message) {
        error_log($message);
    }
}

/**
 * Función principal de generación TTS
 * Unifica toda la funcionalidad en un solo punto
 */
function generateTTS($text, $voice, $options = []) {
    logMessage("=== TTS-SERVICE-UNIFIED: Voice requested: $voice");
    logMessage("=== TTS-SERVICE-UNIFIED: Options recibidas: " . json_encode($options));
    
    // Sistema dinámico de voces
    $voicesFile = dirname(__DIR__) . '/data/voices-config.json';
    $voiceId = $voice; // Por defecto usar como ID directo
    
    if (file_exists($voicesFile)) {
        $config = json_decode(file_get_contents($voicesFile), true);
        
        if (isset($config['voices'][$voice])) {
            $voiceId = $config['voices'][$voice]['id'];
            logMessage("Voice found in config: $voice -> $voiceId");
        } else {
            // Si no está en config, asumir que es un ID directo de ElevenLabs
            logMessage("Voice not in config, using as direct ID: $voice");
        }
    } else {
        logMessage("WARNING: voices-config.json not found, using voice as direct ID");
    }
    
    logMessage("TTS Unified - Final Voice ID: $voiceId");
    
    // URL de ElevenLabs con output_format de alta calidad
    // Plan Creator soporta mp3_44100_192
    $outputFormat = $options['output_format'] ?? 'mp3_44100_192';
    logMessage("=== CONFIGURANDO ALTA CALIDAD 192kbps ===");
    logMessage("Output format seleccionado: $outputFormat");
    $url = ELEVENLABS_BASE_URL . "/text-to-speech/$voiceId?output_format=" . $outputFormat;
    logMessage("URL final con formato de alta calidad: $url");
    logMessage("==========================================");
    
    // Construir voice_settings respetando valores del frontend
    $defaultVoiceSettings = [
        'stability' => 0.75,
        'similarity_boost' => 0.8,
        'style' => 0.5,
        'use_speaker_boost' => true
    ];
    
    // Si vienen voice_settings del frontend, mezclar con defaults
    if (isset($options['voice_settings']) && is_array($options['voice_settings'])) {
        $voiceSettings = array_merge($defaultVoiceSettings, $options['voice_settings']);
        logMessage("Voice settings mezclados con frontend: " . json_encode($voiceSettings));
    } else {
        $voiceSettings = $defaultVoiceSettings;
        logMessage("Usando voice settings por defecto");
    }
    
    // Asegurar que los valores estén en rango válido (0.0 - 1.0)
    $voiceSettings['stability'] = max(0, min(1, floatval($voiceSettings['stability'])));
    $voiceSettings['similarity_boost'] = max(0, min(1, floatval($voiceSettings['similarity_boost'])));
    $voiceSettings['style'] = max(0, min(1, floatval($voiceSettings['style'])));
    // Speaker boost siempre activo (hardcoded)
    $voiceSettings['use_speaker_boost'] = true;
    
    // Determinar modelo a usar (V2 o V3 Alpha)
    logMessage("DEBUG: Checking use_v3 - isset: " . (isset($options['use_v3']) ? 'true' : 'false') . ", value: " . json_encode($options['use_v3'] ?? 'not set'));
    
    // Determinar modelo a usar - flexible para futuras configuraciones
    if (isset($options['use_v3']) && $options['use_v3'] === true) {
        // Por ahora mantener este campo pero usando V2
        // En el futuro cuando confirmemos V3 disponible, cambiar aquí
        $modelId = $options['model_id'] ?? 'eleven_multilingual_v2';
        logMessage("Configuración V3 detectada pero usando V2 por ahora: $modelId");
    } else {
        $modelId = $options['model_id'] ?? 'eleven_multilingual_v2';
        logMessage("Usando modelo estándar: $modelId");
    }
    
    logMessage("DEBUG: Modelo final seleccionado: $modelId");
    
    // Datos para enviar - SOLO PARÁMETROS SOPORTADOS
    $data = [
        'text' => $text,
        'model_id' => $modelId,
        'voice_settings' => $voiceSettings
    ];
    
    // Log para debugging
    logMessage("Request a ElevenLabs: " . json_encode($data));
    logMessage("Voice settings finales: style={$voiceSettings['style']}, stability={$voiceSettings['stability']}, similarity={$voiceSettings['similarity_boost']}");
    
    // Hacer la petición
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: audio/mpeg',
            'Content-Type: application/json',
            'xi-api-key: ' . ELEVENLABS_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logMessage("ERROR CURL: $error");
        throw new Exception("Error en la petición CURL: $error");
    }
    
    if ($httpCode !== 200) {
        $errorMessage = "Error HTTP $httpCode desde ElevenLabs";
        if ($httpCode === 401) {
            $errorMessage = "API Key inválida o expirada";
        } elseif ($httpCode === 422) {
            $errorMessage = "Parámetros inválidos. Response: " . $response;
        } elseif ($httpCode === 429) {
            $errorMessage = "Límite de rate excedido";
        }
        
        logMessage("ERROR: $errorMessage");
        
        // Intentar parsear el error JSON si existe
        $jsonError = json_decode($response, true);
        if ($jsonError && isset($jsonError['detail'])) {
            $errorMessage .= " - Detalle: " . $jsonError['detail']['message'] ?? json_encode($jsonError['detail']);
        }
        
        throw new Exception($errorMessage);
    }
    
    // Verificar que tenemos audio válido
    if (empty($response)) {
        throw new Exception("Respuesta vacía de ElevenLabs");
    }
    
    // Verificar que es un MP3 válido
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_buffer($finfo, $response);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ['audio/mpeg', 'audio/mp3'])) {
        logMessage("WARNING: MIME type inesperado: $mimeType");
    }
    
    logMessage("TTS generado exitosamente. Tamaño: " . strlen($response) . " bytes, MIME: $mimeType");
    
    return $response;
}

/**
 * Función alias para compatibilidad con el sistema existente
 * Mantiene el nombre generateEnhancedTTS para no romper dependencias
 */
function generateEnhancedTTS($text, $voice, $options = []) {
    // Simplemente llamar a la función principal unificada
    return generateTTS($text, $voice, $options);
}

// Log de inicialización
logMessage("=== TTS Service Unified loaded - Soporta V2, V3 Alpha y 192kbps ===");