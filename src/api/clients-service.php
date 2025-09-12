<?php
/**
 * Servicio de gestión de clientes y contextos
 * Maneja el cliente activo del sistema y la configuración de contextos
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class ClientsService {
    private $configFile;
    private $config;
    
    public function __construct() {
        $this->configFile = __DIR__ . '/data/clients-config.json';
        $this->loadConfig();
    }
    
    /**
     * Cargar configuración desde archivo
     */
    private function loadConfig() {
        if (file_exists($this->configFile)) {
            $content = file_get_contents($this->configFile);
            $this->config = json_decode($content, true);
        } else {
            // Configuración por defecto si no existe el archivo
            $this->config = [
                'active_client' => 'casa_costanera',
                'clients' => [
                    'casa_costanera' => [
                        'id' => 'casa_costanera',
                        'name' => 'Casa Costanera',
                        'context' => 'Eres un experto creando anuncios para Casa Costanera, un moderno centro comercial en Chile.',
                        'category' => 'centro_comercial',
                        'active' => true
                    ]
                ]
            ];
            $this->saveConfig();
        }
    }
    
    /**
     * Guardar configuración en archivo
     */
    private function saveConfig() {
        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->configFile, $json);
        return true;
    }
    
    /**
     * Obtener cliente activo
     */
    public function getActiveClient() {
        $activeId = $this->config['active_client'];
        if (isset($this->config['clients'][$activeId])) {
            return $this->config['clients'][$activeId];
        }
        // Fallback al primero disponible
        return reset($this->config['clients']);
    }
    
    /**
     * Establecer cliente activo
     */
    public function setActiveClient($clientId) {
        if (isset($this->config['clients'][$clientId])) {
            $this->config['active_client'] = $clientId;
            $this->saveConfig();
            return [
                'success' => true,
                'active_client' => $this->config['clients'][$clientId]
            ];
        }
        return [
            'success' => false,
            'error' => 'Cliente no encontrado'
        ];
    }
    
    /**
     * Listar todos los clientes
     */
    public function listClients() {
        return [
            'success' => true,
            'active_client' => $this->config['active_client'],
            'clients' => $this->config['clients']
        ];
    }
    
    /**
     * Obtener un cliente específico
     */
    public function getClient($clientId) {
        if (isset($this->config['clients'][$clientId])) {
            return [
                'success' => true,
                'client' => $this->config['clients'][$clientId]
            ];
        }
        return [
            'success' => false,
            'error' => 'Cliente no encontrado'
        ];
    }
    
    /**
     * Guardar/actualizar un cliente
     */
    public function saveClient($clientData) {
        $clientId = $clientData['id'] ?? null;
        
        if (!$clientId) {
            $clientId = 'custom_' . time();
            $clientData['id'] = $clientId;
        }
        
        // Agregar timestamp si es nuevo
        if (!isset($this->config['clients'][$clientId])) {
            $clientData['created_at'] = date('Y-m-d\TH:i:s\Z');
        } else {
            $clientData['updated_at'] = date('Y-m-d\TH:i:s\Z');
        }
        
        $this->config['clients'][$clientId] = $clientData;
        $this->saveConfig();
        
        return [
            'success' => true,
            'client' => $clientData
        ];
    }
    
    /**
     * Eliminar un cliente
     */
    public function deleteClient($clientId) {
        // No permitir eliminar el cliente activo
        if ($this->config['active_client'] === $clientId) {
            return [
                'success' => false,
                'error' => 'No se puede eliminar el cliente activo'
            ];
        }
        
        // No permitir eliminar si es el único cliente
        if (count($this->config['clients']) <= 1) {
            return [
                'success' => false,
                'error' => 'Debe existir al menos un cliente'
            ];
        }
        
        if (isset($this->config['clients'][$clientId])) {
            unset($this->config['clients'][$clientId]);
            $this->saveConfig();
            return [
                'success' => true,
                'message' => 'Cliente eliminado correctamente'
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Cliente no encontrado'
        ];
    }
}

// Procesar request
$service = new ClientsService();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? null;

try {
    switch ($action) {
        case 'get_active':
            $response = ['success' => true, 'client' => $service->getActiveClient()];
            break;
            
        case 'set_active':
            $clientId = $input['client_id'] ?? null;
            if (!$clientId) {
                throw new Exception('client_id es requerido');
            }
            $response = $service->setActiveClient($clientId);
            break;
            
        case 'list':
            $response = $service->listClients();
            break;
            
        case 'get':
            $clientId = $input['client_id'] ?? $_GET['client_id'] ?? null;
            if (!$clientId) {
                throw new Exception('client_id es requerido');
            }
            $response = $service->getClient($clientId);
            break;
            
        case 'save':
            $clientData = $input['client'] ?? null;
            if (!$clientData) {
                throw new Exception('client data es requerido');
            }
            $response = $service->saveClient($clientData);
            break;
            
        case 'delete':
            $clientId = $input['client_id'] ?? null;
            if (!$clientId) {
                throw new Exception('client_id es requerido');
            }
            $response = $service->deleteClient($clientId);
            break;
            
        default:
            $response = [
                'success' => false,
                'error' => 'Acción no válida',
                'available_actions' => ['get_active', 'set_active', 'list', 'get', 'save', 'delete']
            ];
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>