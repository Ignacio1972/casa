<?php
/**
 * Calendar API - Endpoints REST para el calendario
 */

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Cargar servicio
require_once __DIR__ . '/calendar-service.php';

try {
    $service = new CalendarService();
    
    // Determinar acción
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Router simple
    switch ($method) {
        case 'GET':
            handleGetRequest($service, $action);
            break;
            
        case 'POST':
            handlePostRequest($service, $action);
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Manejar peticiones GET
 */
function handleGetRequest($service, $action) {
    switch ($action) {
        case 'list':
            // Listar eventos con filtros opcionales
            $filters = [
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'category' => $_GET['category'] ?? null,
                'is_active' => isset($_GET['is_active']) ? filter_var($_GET['is_active'], FILTER_VALIDATE_BOOLEAN) : null
            ];
            
            $events = $service->listEvents(array_filter($filters));
            
            echo json_encode([
                'success' => true,
                'events' => $events,
                'count' => count($events)
            ]);
            break;
            
        case 'upcoming':
            // Obtener eventos próximos
            $hours = intval($_GET['hours'] ?? 24);
            $events = $service->getUpcomingEvents($hours);
            
            echo json_encode([
                'success' => true,
                'events' => $events,
                'count' => count($events)
            ]);
            break;
            
        case 'get':
            // Obtener un evento específico
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID de evento requerido');
            }
            
            $event = $service->getEvent($id);
            if (!$event) {
                throw new Exception('Evento no encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'event' => $event
            ]);
            break;
            
        case 'history':
            // Obtener historial de un evento
            $eventId = intval($_GET['event_id'] ?? 0);
            if (!$eventId) {
                throw new Exception('ID de evento requerido');
            }
            
            $history = $service->getEventHistory($eventId);
            
            echo json_encode([
                'success' => true,
                'history' => $history,
                'count' => count($history)
            ]);
            break;
            
        case 'stats':
            // Obtener estadísticas
            $stats = $service->getStats();
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
}

/**
 * Manejar peticiones POST
 */
function handlePostRequest($service, $action) {
    // Obtener datos del body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    switch ($action) {
        case 'create':
            // Crear nuevo evento
            $event = $service->createEvent($input);
            
            echo json_encode([
                'success' => true,
                'message' => 'Evento creado exitosamente',
                'event' => $event
            ]);
            break;
            
        case 'update':
            // Actualizar evento existente
            $id = intval($input['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID de evento requerido');
            }
            
            unset($input['id']); // Remover ID de los datos
            $event = $service->updateEvent($id, $input);
            
            echo json_encode([
                'success' => true,
                'message' => 'Evento actualizado exitosamente',
                'event' => $event
            ]);
            break;
            
        case 'delete':
            // Eliminar evento
            $id = intval($input['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID de evento requerido');
            }
            
            $service->deleteEvent($id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Evento eliminado exitosamente'
            ]);
            break;
            
        case 'toggle_status':
            // Cambiar estado activo/inactivo
            $id = intval($input['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID de evento requerido');
            }
            
            $active = filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $event = $service->toggleEventStatus($id, $active);
            
            echo json_encode([
                'success' => true,
                'message' => $active ? 'Evento activado' : 'Evento desactivado',
                'event' => $event
            ]);
            break;
            
        case 'verify_file':
            // Verificar si un archivo existe
            $filePath = $input['file_path'] ?? '';
            if (!$filePath) {
                throw new Exception('Path de archivo requerido');
            }
            
            $exists = $service->verifyAudioFile($filePath);
            
            echo json_encode([
                'success' => true,
                'exists' => $exists
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
}
?>