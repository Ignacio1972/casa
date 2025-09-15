<?php
/**
* Servicio TTS Enhanced - Versión Mejorada
* Ahora respeta los voice_settings que vienen del frontend
*/

// Incluir configuración
require_once dirname(__DIR__) . '/config.php';

// Función de logging
if (!function_exists('logMessage')) {
    function logMessage($message) {
        error_log($message);
    }
}

/**
* Genera audio TTS con las voces nuevas
*/
function generateEnhancedTTS($text, $voice, $options = []) {
   logMessage("=== TTS-SERVICE: Voice requested: $voice");
   logMessage("=== TTS-SERVICE: Options recibidas: " . json_encode($options));
   
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
   
   logMessage("TTS Enhanced - Final Voice ID: $voiceId");
   
   // URL de ElevenLabs
   $url = ELEVENLABS_BASE_URL . "/text-to-speech/$voiceId";
   
   // CAMBIO PRINCIPAL: Construir voice_settings respetando valores del frontend
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
   $voiceSettings['use_speaker_boost'] = (bool)$voiceSettings['use_speaker_boost'];
   
   // Determinar modelo a usar (v2 o v3 alpha)
   logMessage("DEBUG: Checking use_v3 - isset: " . (isset($options['use_v3']) ? 'true' : 'false') . ", value: " . json_encode($options['use_v3'] ?? 'not set'));
   
   // Si se solicita v3 alpha, usar el modelo turbo v2.5
   if (isset($options['use_v3']) && $options['use_v3'] === true) {
       $modelId = 'eleven_turbo_v2_5'; // Modelo más reciente y rápido de v3
       logMessage("✅ Usando API v3 (alpha) con modelo turbo v2.5");
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
   
   // Log de respuesta
   logMessage("Respuesta HTTP: $httpCode");
   
   if ($error) {
       logMessage("Error CURL: $error");
       throw new Exception("Error de conexión: $error");
   }
   
   if ($httpCode !== 200) {
       // Log más detallado para errores
       $errorInfo = json_decode($response, true);
       if ($errorInfo) {
           logMessage("Error ElevenLabs detallado: " . json_encode($errorInfo));
       } else {
           logMessage("Error ElevenLabs: HTTP $httpCode - Response: " . substr($response, 0, 200));
       }
       
       // Mensajes de error más específicos
       switch ($httpCode) {
           case 401:
               throw new Exception("API Key inválida o expirada");
           case 422:
               throw new Exception("Parámetros inválidos: " . ($errorInfo['detail']['message'] ?? 'Verificar texto o voz'));
           case 429:
               throw new Exception("Límite de rate excedido. Intente en unos segundos");
           default:
               throw new Exception("Error ElevenLabs API: HTTP $httpCode");
       }
   }
   
   if (!$response) {
       throw new Exception('Respuesta vacía de ElevenLabs');
   }
   
   logMessage("Audio generado exitosamente, tamaño: " . strlen($response) . " bytes");
   
   return $response;
}

/**
* Función helper para convertir valores del frontend (0-100) a API (0.0-1.0)
* Por si el frontend envía porcentajes
*/
function normalizeVoiceValue($value) {
   if ($value > 1 && $value <= 100) {
       // Si parece ser un porcentaje, convertir
       return $value / 100;
   }
   return $value;
}
?>