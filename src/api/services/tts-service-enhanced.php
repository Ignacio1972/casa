<?php
/**
 * TTS Service Enhanced - Punto de entrada para compatibilidad
 * Este archivo ahora usa el servicio unificado
 * Mantiene compatibilidad con announcement-generator.php y otros servicios que lo requieren
 */

// Cargar el servicio unificado
require_once __DIR__ . '/tts-service-unified.php';

// El servicio unificado ya define las funciones:
// - generateTTS()
// - generateEnhancedTTS()

// Log para indicar que este archivo está siendo usado
if (function_exists('logMessage')) {
    logMessage("[tts-service-enhanced.php] Redirigiendo al servicio unificado");
}