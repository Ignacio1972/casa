<?php
/**
 * Servicio de Configuración de TTS
 * Gestiona la configuración global de mensajes TTS simples (sin música)
 */

require_once __DIR__ . '/config.php';

$CONFIG_FILE = __DIR__ . '/data/tts-config.json';

/**
 * Configuración por defecto
 */
function getDefaultConfig() {
    return [
        'silence' => [
            'add_silence' => true,
            'intro_seconds' => 3.0,
            'outro_seconds' => 3.0
        ],
        'normalization' => [
            'enabled' => true,
            'target_lufs' => -16,
            'output_volume' => 1.0,
            'enable_compression' => true
        ],
        'voice_settings' => [
            'style' => 0.5,
            'stability' => 0.75,
            'similarity_boost' => 0.8,
            'use_speaker_boost' => true
        ]
    ];
}

/**
 * Obtener configuración actual
 */
function getTTSConfig() {
    global $CONFIG_FILE;
    
    if (!file_exists($CONFIG_FILE)) {
        // Crear configuración por defecto
        $defaultConfig = getDefaultConfig();
        file_put_contents($CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT));
        return $defaultConfig;
    }
    
    $config = json_decode(file_get_contents($CONFIG_FILE), true);
    
    // Verificar que tenga todas las secciones necesarias
    $defaultConfig = getDefaultConfig();
    foreach ($defaultConfig as $key => $value) {
        if (!isset($config[$key])) {
            $config[$key] = $value;
        }
    }
    
    return $config;
}

/**
 * Guardar configuración
 */
function saveTTSConfig($config) {
    global $CONFIG_FILE;
    
    // Validar configuración
    $validated = validateConfig($config);
    
    if (!$validated['valid']) {
        return [
            'success' => false,
            'error' => $validated['error']
        ];
    }
    
    // Asegurar que el directorio existe
    $dir = dirname($CONFIG_FILE);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Guardar
    $result = file_put_contents($CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return [
            'success' => false,
            'error' => 'No se pudo guardar la configuración'
        ];
    }
    
    // Log del cambio
    logConfigChange($config);
    
    return [
        'success' => true,
        'message' => 'Configuración guardada exitosamente'
    ];
}

/**
 * Validar configuración
 */
function validateConfig($config) {
    // Validar estructura básica
    if (!isset($config['silence']) || !isset($config['normalization']) || !isset($config['voice_settings'])) {
        return [
            'valid' => false,
            'error' => 'Estructura de configuración inválida'
        ];
    }
    
    // Validar silencios
    if (isset($config['silence']['intro_seconds'])) {
        $intro = $config['silence']['intro_seconds'];
        if ($intro < 0 || $intro > 10) {
            return [
                'valid' => false,
                'error' => 'El silencio inicial debe estar entre 0 y 10 segundos'
            ];
        }
    }
    
    if (isset($config['silence']['outro_seconds'])) {
        $outro = $config['silence']['outro_seconds'];
        if ($outro < 0 || $outro > 10) {
            return [
                'valid' => false,
                'error' => 'El silencio final debe estar entre 0 y 10 segundos'
            ];
        }
    }
    
    // Validar normalización
    if (isset($config['normalization']['target_lufs'])) {
        $lufs = $config['normalization']['target_lufs'];
        if ($lufs < -30 || $lufs > -10) {
            return [
                'valid' => false,
                'error' => 'El target LUFS debe estar entre -30 y -10'
            ];
        }
    }
    
    if (isset($config['normalization']['output_volume'])) {
        $volume = $config['normalization']['output_volume'];
        if ($volume < 0.5 || $volume > 1.5) {
            return [
                'valid' => false,
                'error' => 'El volumen de salida debe estar entre 50% y 150%'
            ];
        }
    }
    
    // Validar voice settings
    $voiceParams = ['style', 'stability', 'similarity_boost'];
    foreach ($voiceParams as $param) {
        if (isset($config['voice_settings'][$param])) {
            $value = $config['voice_settings'][$param];
            if ($value < 0 || $value > 1) {
                return [
                    'valid' => false,
                    'error' => "El parámetro $param debe estar entre 0 y 1"
                ];
            }
        }
    }
    
    return ['valid' => true];
}

/**
 * Registrar cambios en configuración
 */
function logConfigChange($config) {
    $logFile = __DIR__ . '/logs/tts-config-' . date('Y-m-d') . '.log';
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'config_update',
        'config' => $config
    ];
    
    // Crear directorio de logs si no existe
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Resetear a valores por defecto
 */
function resetTTSConfig() {
    global $CONFIG_FILE;
    
    $defaultConfig = getDefaultConfig();
    
    $result = file_put_contents($CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return [
            'success' => false,
            'error' => 'No se pudo resetear la configuración'
        ];
    }
    
    logConfigChange($defaultConfig);
    
    return [
        'success' => true,
        'config' => $defaultConfig,
        'message' => 'Configuración restaurada a valores por defecto'
    ];
}

// Procesar requests si se llama directamente
if (basename($_SERVER['SCRIPT_NAME']) === 'tts-config-service.php') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'get':
                $config = getTTSConfig();
                echo json_encode([
                    'success' => true,
                    'config' => $config
                ]);
                break;
                
            case 'save':
                if (!isset($input['config'])) {
                    throw new Exception('Configuración no proporcionada');
                }
                
                $result = saveTTSConfig($input['config']);
                echo json_encode($result);
                break;
                
            case 'reset':
                $result = resetTTSConfig();
                echo json_encode($result);
                break;
                
            case 'validate':
                if (!isset($input['config'])) {
                    throw new Exception('Configuración no proporcionada');
                }
                
                $validation = validateConfig($input['config']);
                echo json_encode([
                    'success' => $validation['valid'],
                    'error' => $validation['error'] ?? null
                ]);
                break;
                
            default:
                throw new Exception('Acción no válida: ' . $action);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>