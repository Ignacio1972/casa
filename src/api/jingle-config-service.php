<?php
/**
 * Servicio de Configuración de Jingles
 * Gestiona la configuración global de jingles desde el playground
 */

require_once __DIR__ . '/config.php';

$CONFIG_FILE = __DIR__ . '/data/jingle-config.json';

/**
 * Obtener configuración actual
 */
function getJingleConfig() {
    global $CONFIG_FILE;
    
    if (!file_exists($CONFIG_FILE)) {
        // Crear configuración por defecto
        $defaultConfig = [
            'jingle_defaults' => [
                'enabled_by_default' => false,
                'intro_silence' => 2,
                'outro_silence' => 4,
                'music_volume' => 0.3,
                'voice_volume' => 1.0,
                'fade_in' => 2,
                'fade_out' => 2,
                'ducking_enabled' => true,
                'duck_level' => 0.2,
                'default_music' => 'Martin Roth - Just Sine Waves.mp3'
            ],
            'allowed_music' => 'all',
            'user_can_override' => false
        ];
        
        file_put_contents($CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT));
        return $defaultConfig;
    }
    
    return json_decode(file_get_contents($CONFIG_FILE), true);
}

/**
 * Guardar configuración
 */
function saveJingleConfig($config) {
    global $CONFIG_FILE;
    
    // Validar configuración
    $validated = validateConfig($config);
    
    if (!$validated['valid']) {
        return [
            'success' => false,
            'error' => $validated['error']
        ];
    }
    
    // Guardar
    $result = file_put_contents($CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return [
            'success' => false,
            'error' => 'No se pudo guardar la configuración'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Configuración guardada exitosamente'
    ];
}

/**
 * Validar configuración
 */
function validateConfig($config) {
    // Validar estructura
    if (!isset($config['jingle_defaults'])) {
        return ['valid' => false, 'error' => 'Estructura de configuración inválida'];
    }
    
    $defaults = $config['jingle_defaults'];
    
    // Validar rangos
    if ($defaults['intro_silence'] < 0 || $defaults['intro_silence'] > 30) {
        return ['valid' => false, 'error' => 'intro_silence debe estar entre 0 y 30 segundos'];
    }
    
    if ($defaults['outro_silence'] < 0 || $defaults['outro_silence'] > 30) {
        return ['valid' => false, 'error' => 'outro_silence debe estar entre 0 y 30 segundos'];
    }
    
    if ($defaults['music_volume'] < 0 || $defaults['music_volume'] > 1) {
        return ['valid' => false, 'error' => 'music_volume debe estar entre 0 y 1'];
    }
    
    if ($defaults['voice_volume'] < 0 || $defaults['voice_volume'] > 2) {
        return ['valid' => false, 'error' => 'voice_volume debe estar entre 0 y 2'];
    }
    
    if ($defaults['fade_in'] < 0 || $defaults['fade_in'] > 10) {
        return ['valid' => false, 'error' => 'fade_in debe estar entre 0 y 10 segundos'];
    }
    
    if ($defaults['fade_out'] < 0 || $defaults['fade_out'] > 10) {
        return ['valid' => false, 'error' => 'fade_out debe estar entre 0 y 10 segundos'];
    }
    
    if ($defaults['duck_level'] < 0 || $defaults['duck_level'] > 1) {
        return ['valid' => false, 'error' => 'duck_level debe estar entre 0 y 1'];
    }
    
    return ['valid' => true];
}

/**
 * Restaurar configuración por defecto
 */
function resetToDefaults() {
    global $CONFIG_FILE;
    
    $defaultConfig = [
        'jingle_defaults' => [
            'enabled_by_default' => false,
            'intro_silence' => 2,
            'outro_silence' => 4,
            'music_volume' => 0.3,
            'voice_volume' => 1.0,
            'fade_in' => 2,
            'fade_out' => 2,
            'ducking_enabled' => true,
            'duck_level' => 0.2,
            'default_music' => 'Martin Roth - Just Sine Waves.mp3'
        ],
        'allowed_music' => 'all',
        'user_can_override' => false
    ];
    
    file_put_contents($CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT));
    
    return [
        'success' => true,
        'message' => 'Configuración restaurada a valores por defecto',
        'config' => $defaultConfig
    ];
}

// Procesar requests
if (basename($_SERVER['SCRIPT_NAME']) === 'jingle-config-service.php') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? $_GET['action'] ?? 'get';
        
        switch ($action) {
            case 'get':
                echo json_encode([
                    'success' => true,
                    'config' => getJingleConfig()
                ]);
                break;
                
            case 'save':
                if (!isset($input['config'])) {
                    throw new Exception('Configuración requerida');
                }
                
                $result = saveJingleConfig($input['config']);
                echo json_encode($result);
                break;
                
            case 'reset':
                $result = resetToDefaults();
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Acción no válida');
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