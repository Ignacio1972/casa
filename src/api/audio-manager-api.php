<?php
/**
 * API Endpoint para AudioManager Unificado
 * 
 * Este endpoint coexiste con los existentes (generate.php, jingle-service.php)
 * permitiendo migración gradual sin romper funcionalidad
 */

// Configuración
define('DEBUG_MODE', false);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejo de OPTIONS para CORS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cargar AudioManager
require_once __DIR__ . '/services/AudioManager.php';

use CasaCostanera\Audio\AudioManager;

try {
    $audioManager = new AudioManager();
    
    // Obtener input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Si no hay JSON, intentar POST normal
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'generate':
            // Generar audio unificado
            $result = $audioManager->generate(
                $input['type'] ?? 'tts',
                $input['params'] ?? [],
                $input['targets'] ?? ['main']
            );
            break;
            
        case 'configure':
            // Configurar volúmenes
            $result = $audioManager->configureVolume(
                $input['type'],
                $input['setting'],
                $input['value']
            );
            break;
            
        case 'get_config':
            // Obtener configuración actual
            $result = $audioManager->getConfiguration();
            break;
            
        case 'test':
            // Endpoint de prueba
            $result = [
                'success' => true,
                'message' => 'AudioManager API funcionando',
                'version' => '1.0.0',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
            
        case 'migrate_check':
            // Verificar estado de migración
            $result = checkMigrationStatus();
            break;
            
        default:
            throw new Exception("Acción no válida: $action");
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
}

/**
 * Verificar estado de migración
 */
function checkMigrationStatus() {
    $services = [
        'generate.php' => file_exists(__DIR__ . '/generate.php'),
        'jingle-service.php' => file_exists(__DIR__ . '/jingle-service.php'),
        'automatic-jingle-service.php' => file_exists(__DIR__ . '/automatic-jingle-service.php'),
        'AudioManager.php' => file_exists(__DIR__ . '/services/AudioManager.php')
    ];
    
    $configs = [
        'voices-config.json' => file_exists(__DIR__ . '/data/voices-config.json'),
        'jingle-config.json' => file_exists(__DIR__ . '/data/jingle-config.json'),
        'clients-config.json' => file_exists(__DIR__ . '/data/clients-config.json'),
        'stores-config.json' => file_exists(__DIR__ . '/data/stores-config.json')
    ];
    
    return [
        'ready_for_migration' => $services['AudioManager.php'],
        'legacy_services_available' => $services['generate.php'] && $services['jingle-service.php'],
        'services' => $services,
        'configs' => $configs,
        'recommendation' => $services['AudioManager.php'] ? 
            'AudioManager listo. Puede comenzar migración gradual.' : 
            'AudioManager no encontrado. Instalar primero.'
    ];
}