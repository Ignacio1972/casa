<?php
/**
 * Calendar Scheduler - Script ejecutado por cron cada minuto
 * Procesa eventos programados y los envía a AzuraCast
 */

// Prevenir ejecución desde web
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse desde línea de comandos');
}

// Configuración de error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Includes necesarios
require_once __DIR__ . '/calendar-service.php';
require_once __DIR__ . '/services/radio-service.php';

class CalendarScheduler {
    private $db;
    private $service;
    private $startTime;
    private $lockFile;
    private $logFile;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->lockFile = __DIR__ . '/scheduler.lock';
        $this->initLogger();
        
        try {
            $this->service = new CalendarService();
            $this->initDatabase();
            $this->log('INFO', 'Scheduler iniciado');
        } catch (Exception $e) {
            $this->log('ERROR', 'Error iniciando scheduler: ' . $e->getMessage());
            exit(1);
        }
    }
    
    /**
     * Inicializar logger
     */
    private function initLogger() {
        $logDir = __DIR__ . '/../../logs/scheduler';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $this->logFile = $logDir . '/' . date('Y-m-d') . '.log';
    }
    
    /**
     * Inicializar conexión directa a DB
     */
    private function initDatabase() {
        $dbPath = __DIR__ . '/../../database/casa.db';
        $this->db = new PDO('sqlite:' . $dbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    /**
     * Ejecutar proceso principal
     */
    public function run() {
        // Verificar si el scheduler está habilitado
        if (!$this->isSchedulerEnabled()) {
            $this->log('INFO', 'Scheduler deshabilitado en configuración');
            return;
        }
        
        // Verificar lock para evitar ejecuciones concurrentes
        if (!$this->acquireLock()) {
            $this->log('WARNING', 'Otra instancia del scheduler está ejecutándose');
            return;
        }
        
        try {
            // Procesar eventos próximos
            $this->processUpcomingEvents();
            
            // Limpiar eventos antiguos bloqueados
            $this->cleanupLockedEvents();
            
            // Recuperar eventos perdidos (si el sistema estuvo caído)
            $this->recoverMissedEvents();
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error en scheduler: ' . $e->getMessage());
        } finally {
            $this->releaseLock();
            $executionTime = round((microtime(true) - $this->startTime) * 1000, 2);
            $this->log('INFO', "Scheduler finalizado. Tiempo: {$executionTime}ms");
        }
    }
    
    /**
     * Procesar eventos próximos
     */
    private function processUpcomingEvents() {
        $now = new DateTime();
        $lookAhead = $this->service->getConfig('look_ahead_seconds', 90);
        $until = clone $now;
        $until->add(new DateInterval('PT' . $lookAhead . 'S'));
        
        $this->log('INFO', sprintf(
            'Buscando eventos entre %s y %s',
            $now->format('Y-m-d H:i:s'),
            $until->format('Y-m-d H:i:s')
        ));
        
        // Obtener eventos pendientes con lock
        $sql = "SELECT * FROM calendar_events 
                WHERE start_datetime BETWEEN ? AND ?
                AND is_active = 1
                AND status = 'pending'
                AND (locked_until IS NULL OR locked_until < ?)
                ORDER BY priority DESC, start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $now->format('Y-m-d H:i:s'),
            $until->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s')
        ]);
        
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($events)) {
            $this->log('INFO', 'No hay eventos próximos');
            return;
        }
        
        $this->log('INFO', sprintf('Encontrados %d eventos para procesar', count($events)));
        
        foreach ($events as $event) {
            $this->processEvent($event);
        }
    }
    
    /**
     * Procesar un evento individual
     */
    private function processEvent($event) {
        $this->log('INFO', sprintf(
            'Procesando evento: ID=%d, Título="%s", Hora=%s',
            $event['id'],
            $event['title'],
            $event['start_datetime']
        ));
        
        // Adquirir lock del evento
        if (!$this->lockEvent($event['id'])) {
            $this->log('WARNING', "No se pudo bloquear evento ID={$event['id']}");
            return;
        }
        
        $startTime = microtime(true);
        $status = 'failed';
        $errorMessage = null;
        $azuracastResponse = null;
        
        try {
            // Verificar que el archivo existe
            if (!$this->verifyFileExists($event['file_path'])) {
                throw new Exception('Archivo no encontrado: ' . $event['file_path']);
            }
            
            // Determinar método de reproducción según prioridad
            if ($event['priority'] >= 8 || $event['category'] === 'emergencias') {
                // Alta prioridad: interrumpir inmediatamente
                $this->log('INFO', 'Usando interrupción inmediata (alta prioridad)');
                $result = $this->playImmediate($event);
            } else {
                // Prioridad normal: agregar a cola
                $this->log('INFO', 'Agregando a cola normal');
                $result = $this->queueForPlayback($event);
            }
            
            if ($result['success']) {
                $status = 'success';
                $azuracastResponse = json_encode($result);
                $this->log('SUCCESS', "Evento reproducido exitosamente: {$event['title']}");
            } else {
                throw new Exception($result['error'] ?? 'Error desconocido');
            }
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->log('ERROR', "Error procesando evento ID={$event['id']}: $errorMessage");
        }
        
        // Registrar en historial
        $this->recordPlayback($event, $status, $errorMessage, $azuracastResponse, $startTime);
        
        // Actualizar estado del evento
        $this->updateEventStatus($event['id'], $status === 'success' ? 'completed' : 'failed');
    }
    
    /**
     * Bloquear evento para procesamiento
     */
    private function lockEvent($eventId) {
        $lockTimeout = $this->service->getConfig('lock_timeout_seconds', 300);
        $lockUntil = date('Y-m-d H:i:s', time() + $lockTimeout);
        
        $sql = "UPDATE calendar_events 
                SET locked_until = ?, status = 'processing'
                WHERE id = ? 
                AND (locked_until IS NULL OR locked_until < CURRENT_TIMESTAMP)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lockUntil, $eventId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Verificar que el archivo existe
     */
    private function verifyFileExists($filePath) {
        // Verificar en el sistema local
        $localPath = dirname(dirname(dirname(__DIR__))) . '/api/temp/' . basename($filePath);
        
        if (file_exists($localPath)) {
            $this->log('DEBUG', "Archivo encontrado localmente: $localPath");
            return true;
        }
        
        // Si no está local, verificar en AzuraCast
        // (Aquí podrías agregar verificación via API si es necesario)
        
        $this->log('WARNING', "Archivo no encontrado: $filePath");
        return false;
    }
    
    /**
     * Reproducir inmediatamente (interrumpir radio)
     */
    private function playImmediate($event) {
        try {
            // Usar la función existente del sistema
            $filename = basename($event['file_path']);
            $success = interruptRadio($filename);
            
            if ($success) {
                return [
                    'success' => true,
                    'method' => 'interrupt',
                    'filename' => $filename
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al interrumpir la radio'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Agregar a cola de reproducción normal
     */
    private function queueForPlayback($event) {
        // Por ahora, usar el mismo método de interrupción
        // En el futuro, podrías implementar una cola real en AzuraCast
        return $this->playImmediate($event);
    }
    
    /**
     * Registrar reproducción en historial
     */
    private function recordPlayback($event, $status, $errorMessage, $azuracastResponse, $startTime) {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $sql = "INSERT INTO playback_history 
                (event_id, scheduled_time, actual_time, status, error_message, 
                 azuracast_response, execution_time_ms)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $event['id'],
            $event['start_datetime'],
            date('Y-m-d H:i:s'),
            $status,
            $errorMessage,
            $azuracastResponse,
            $executionTime
        ]);
    }
    
    /**
     * Actualizar estado del evento
     */
    private function updateEventStatus($eventId, $status) {
        $sql = "UPDATE calendar_events 
                SET status = ?, locked_until = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $eventId]);
    }
    
    /**
     * Limpiar eventos bloqueados antiguos
     */
    private function cleanupLockedEvents() {
        $sql = "UPDATE calendar_events 
                SET locked_until = NULL, status = 'pending'
                WHERE locked_until < CURRENT_TIMESTAMP
                AND status = 'processing'";
        
        $stmt = $this->db->exec($sql);
        
        if ($stmt > 0) {
            $this->log('INFO', "Liberados $stmt eventos bloqueados");
        }
    }
    
    /**
     * Recuperar eventos perdidos
     */
    private function recoverMissedEvents() {
        // Buscar eventos que debieron ejecutarse en los últimos 5 minutos
        $sql = "SELECT ce.* FROM calendar_events ce
                LEFT JOIN playback_history ph ON ce.id = ph.event_id 
                    AND ph.scheduled_time = ce.start_datetime
                WHERE ce.start_datetime BETWEEN datetime('now', '-5 minutes') AND datetime('now')
                AND ce.is_active = 1
                AND ce.status = 'pending'
                AND ph.id IS NULL
                LIMIT 5";
        
        $stmt = $this->db->query($sql);
        $missedEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($missedEvents)) {
            $this->log('WARNING', sprintf(
                'Encontrados %d eventos perdidos, procesando...',
                count($missedEvents)
            ));
            
            foreach ($missedEvents as $event) {
                $this->log('INFO', sprintf(
                    'Recuperando evento perdido: ID=%d, Hora original=%s',
                    $event['id'],
                    $event['start_datetime']
                ));
                
                $this->processEvent($event);
            }
        }
    }
    
    /**
     * Verificar si el scheduler está habilitado
     */
    private function isSchedulerEnabled() {
        return $this->service->getConfig('scheduler_enabled', '1') === '1';
    }
    
    /**
     * Adquirir lock del scheduler
     */
    private function acquireLock() {
        if (file_exists($this->lockFile)) {
            // Verificar edad del lock
            $lockAge = time() - filemtime($this->lockFile);
            
            // Si el lock tiene más de 5 minutos, considerarlo obsoleto
            if ($lockAge > 300) {
                $this->log('WARNING', 'Lock obsoleto encontrado, eliminando...');
                unlink($this->lockFile);
            } else {
                return false;
            }
        }
        
        return touch($this->lockFile);
    }
    
    /**
     * Liberar lock del scheduler
     */
    private function releaseLock() {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
    
    /**
     * Escribir en log
     */
    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $pid = getmypid();
        $memory = round(memory_get_usage() / 1024 / 1024, 2);
        
        $logEntry = sprintf(
            "[%s] [%s] [PID:%d] [MEM:%sMB] %s\n",
            $timestamp,
            $level,
            $pid,
            $memory,
            $message
        );
        
        // Escribir en archivo
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // También mostrar en consola
        echo $logEntry;
    }
}

// Ejecutar scheduler
try {
    $scheduler = new CalendarScheduler();
    $scheduler->run();
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>