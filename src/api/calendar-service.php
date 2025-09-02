<?php
/**
 * Calendar Service - Lógica de negocio del calendario
 */

class CalendarService {
    private $db;
    private $azuracastApi;
    
    public function __construct() {
        $this->initDatabase();
        $this->initAzuracast();
    }
    
    /**
     * Inicializar conexión a base de datos
     */
    private function initDatabase() {
        $dbPath = __DIR__ . '/../../database/casa.db';
        
        if (!file_exists($dbPath)) {
            throw new Exception('Base de datos no encontrada. Ejecute init-db.php primero.');
        }
        
        $this->db = new PDO('sqlite:' . $dbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Configurar timezone
        date_default_timezone_set($this->getConfig('timezone', 'America/Santiago'));
    }
    
    /**
     * Inicializar API de AzuraCast
     */
    private function initAzuracast() {
        // Cargar configuración desde el sistema principal
        $configPath = __DIR__ . '/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }
        
        $this->azuracastApi = [
            'base_url' => defined('AZURACAST_BASE_URL') ? AZURACAST_BASE_URL : 'http://51.222.25.222',
            'api_key' => defined('AZURACAST_API_KEY') ? AZURACAST_API_KEY : '',
            'station_id' => defined('AZURACAST_STATION_ID') ? AZURACAST_STATION_ID : 1
        ];
    }
    
    /**
     * Obtener configuración del sistema
     */
    public function getConfig($key, $default = null) {
        $stmt = $this->db->prepare("SELECT value FROM system_config WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['value'] : $default;
    }
    
    /**
     * Listar eventos con filtros
     */
    public function listEvents($filters = []) {
        $sql = "SELECT * FROM calendar_events WHERE 1=1";
        $params = [];
        
        // Filtro por rango de fechas
        if (!empty($filters['start_date'])) {
            $sql .= " AND start_datetime >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND start_datetime <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        
        // Filtro por categoría
        if (!empty($filters['category'])) {
            $sql .= " AND category = ?";
            $params[] = $filters['category'];
        }
        
        // Filtro por estado activo
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        }
        
        // Ordenar por fecha
        $sql .= " ORDER BY start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener eventos próximos
     */
    public function getUpcomingEvents($hours = 24) {
        $now = new DateTime();
        $until = clone $now;
        $until->add(new DateInterval('PT' . $hours . 'H'));
        
        $sql = "SELECT * FROM calendar_events 
                WHERE start_datetime BETWEEN ? AND ?
                AND is_active = 1
                AND status = 'pending'
                ORDER BY start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $now->format('Y-m-d H:i:s'),
            $until->format('Y-m-d H:i:s')
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener un evento por ID
     */
    public function getEvent($id) {
        $stmt = $this->db->prepare("SELECT * FROM calendar_events WHERE id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nuevo evento
     */
    public function createEvent($data) {
        // Validar datos requeridos
        $required = ['title', 'file_path', 'category', 'start_datetime'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo requerido: $field");
            }
        }
        
        // Validar que no sea fecha pasada
        $eventDate = new DateTime($data['start_datetime']);
        if ($eventDate < new DateTime()) {
            throw new Exception('No se pueden programar eventos en el pasado');
        }
        
        // Verificar que el archivo existe
        if (!$this->verifyAudioFile($data['file_path'])) {
            throw new Exception('El archivo de audio no existe en la biblioteca');
        }
        
        // Insertar evento
        $sql = "INSERT INTO calendar_events 
                (title, file_path, category, start_datetime, priority, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['file_path'],
            $data['category'],
            $data['start_datetime'],
            $data['priority'] ?? 5,
            $data['notes'] ?? '',
            $data['created_by'] ?? 'sistema'
        ]);
        
        $eventId = $this->db->lastInsertId();
        
        // Log
        $this->log('event_created', "Evento creado: ID=$eventId, Title={$data['title']}");
        
        return $this->getEvent($eventId);
    }
    
    /**
     * Actualizar evento existente
     */
    public function updateEvent($id, $data) {
        // Verificar que existe
        $event = $this->getEvent($id);
        if (!$event) {
            throw new Exception('Evento no encontrado');
        }
        
        // Construir SQL dinámicamente
        $fields = [];
        $params = [];
        
        $allowedFields = ['title', 'file_path', 'category', 'start_datetime', 
                         'priority', 'notes', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return $event; // Nada que actualizar
        }
        
        // Agregar updated_at
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        
        // Agregar ID al final de params
        $params[] = $id;
        
        $sql = "UPDATE calendar_events SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        // Log
        $this->log('event_updated', "Evento actualizado: ID=$id");
        
        return $this->getEvent($id);
    }
    
    /**
     * Eliminar evento
     */
    public function deleteEvent($id) {
        // Verificar que existe
        $event = $this->getEvent($id);
        if (!$event) {
            throw new Exception('Evento no encontrado');
        }
        
        $stmt = $this->db->prepare("DELETE FROM calendar_events WHERE id = ?");
        $stmt->execute([$id]);
        
        // Log
        $this->log('event_deleted', "Evento eliminado: ID=$id, Title={$event['title']}");
        
        return true;
    }
    
    /**
     * Cambiar estado activo de un evento
     */
    public function toggleEventStatus($id, $active) {
        return $this->updateEvent($id, ['is_active' => $active ? 1 : 0]);
    }
    
    /**
     * Verificar que un archivo existe en AzuraCast
     */
    public function verifyAudioFile($filePath) {
        try {
            // Primero verificar localmente
            $localPath = dirname(dirname(dirname(__DIR__))) . '/api/temp/' . basename($filePath);
            if (file_exists($localPath)) {
                return true;
            }
            
            // Luego verificar en AzuraCast via API
            $url = $this->azuracastApi['base_url'] . '/api/station/' . 
                   $this->azuracastApi['station_id'] . '/files';
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->azuracastApi['api_key']
                ],
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $files = json_decode($response, true);
                foreach ($files as $file) {
                    if (strpos($file['path'], basename($filePath)) !== false) {
                        return true;
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log('error', 'Error verificando archivo: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Obtener historial de un evento
     */
    public function getEventHistory($eventId) {
        $sql = "SELECT * FROM playback_history 
                WHERE event_id = ? 
                ORDER BY scheduled_time DESC 
                LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$eventId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener estadísticas del calendario
     */
    public function getStats() {
        $stats = [];
        
        // Total de eventos
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM calendar_events");
        $stats['total_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Eventos activos
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM calendar_events WHERE is_active = 1");
        $stats['active_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Eventos de hoy
        $today = date('Y-m-d');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM calendar_events 
             WHERE DATE(start_datetime) = ? AND is_active = 1"
        );
        $stmt->execute([$today]);
        $stats['today_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Eventos esta semana
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM calendar_events 
             WHERE DATE(start_datetime) BETWEEN ? AND ? AND is_active = 1"
        );
        $stmt->execute([$weekStart, $weekEnd]);
        $stats['this_week_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Reproducciones exitosas últimas 24h
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM playback_history 
             WHERE status = 'success' AND actual_time > ?"
        );
        $stmt->execute([$yesterday]);
        $stats['successful_plays_24h'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return $stats;
    }
    
    /**
     * Registrar en log
     */
    private function log($type, $message) {
        $logDir = dirname(dirname(__DIR__)) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/calendar_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . " [$type] $message\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>