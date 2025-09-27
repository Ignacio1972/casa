<?php
/**
 * RateLimiter Service
 * Control de rate limiting para APIs externas
 * 
 * @version 2.0.0
 */

namespace App\Services;

class RateLimiter {
    
    // Límites por servicio (requests por minuto)
    const MAX_REQUESTS_PER_MINUTE = [
        'elevenlabs' => 50,
        'azuracast' => 100,
        'broadcast' => 30,
        'claude' => 20
    ];
    
    // Límites adicionales
    const LIMITS = [
        'elevenlabs_characters_per_month' => 500000,
        'max_parallel_broadcasts' => 5,
        'max_audio_duration_seconds' => 300,
        'max_file_size_mb' => 50
    ];
    
    private $cacheDir;
    private $logger;
    
    public function __construct() {
        $this->cacheDir = '/var/www/casa/src/api/v2/temp/rate_limit/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        $this->initLogger();
    }
    
    /**
     * Verifica si se puede hacer una request
     * 
     * @param string $service Servicio a verificar
     * @param string $identifier Identificador único (IP, user_id, etc)
     * @return array ['allowed' => bool, 'retry_after' => int, 'remaining' => int]
     */
    public function checkLimit($service, $identifier = 'default') {
        $limit = self::MAX_REQUESTS_PER_MINUTE[$service] ?? 100;
        $window = 60; // 1 minuto en segundos
        
        $key = $this->getCacheKey($service, $identifier);
        $data = $this->getRequestData($key);
        
        $now = time();
        $windowStart = $now - $window;
        
        // Limpiar requests antiguas
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        $currentCount = count($data['requests']);
        $remaining = $limit - $currentCount;
        
        if ($currentCount >= $limit) {
            // Calcular tiempo hasta que se libere un slot
            $oldestRequest = min($data['requests']);
            $retryAfter = ($oldestRequest + $window) - $now;
            
            $this->log('warning', 'Rate limit exceeded', [
                'service' => $service,
                'identifier' => $identifier,
                'current_count' => $currentCount,
                'limit' => $limit,
                'retry_after' => $retryAfter
            ]);
            
            return [
                'allowed' => false,
                'retry_after' => $retryAfter,
                'remaining' => 0,
                'reset_at' => $oldestRequest + $window
            ];
        }
        
        // Registrar nueva request
        $data['requests'][] = $now;
        $this->saveRequestData($key, $data);
        
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => $remaining - 1,
            'reset_at' => $now + $window
        ];
    }
    
    /**
     * Registra uso de caracteres para ElevenLabs
     */
    public function trackCharacterUsage($characters) {
        $monthKey = date('Y-m');
        $file = $this->cacheDir . "elevenlabs_chars_{$monthKey}.json";
        
        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data)) {
                $data = [];
            }
        }
        
        // Asegurar que la estructura existe
        if (!isset($data['total'])) {
            $data['total'] = 0;
        }
        if (!isset($data['daily'])) {
            $data['daily'] = [];
        }
        
        $data['total'] = $data['total'] + $characters;
        $data['last_updated'] = date('c');
        $data['daily'][date('Y-m-d')] = ($data['daily'][date('Y-m-d')] ?? 0) + $characters;
        
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
        
        // Verificar si se acerca al límite
        $limit = self::LIMITS['elevenlabs_characters_per_month'];
        $percentage = round(($data['total'] / $limit) * 100, 2);
        
        if ($percentage > 90) {
            $this->log('critical', 'Character usage critical', [
                'used' => $data['total'],
                'limit' => $limit,
                'percentage' => $percentage
            ]);
        } elseif ($percentage > 75) {
            $this->log('warning', 'Character usage high', [
                'used' => $data['total'],
                'limit' => $limit,
                'percentage' => $percentage
            ]);
        }
        
        return [
            'used' => $data['total'],
            'limit' => $limit,
            'remaining' => $limit - $data['total'],
            'percentage' => $percentage
        ];
    }
    
    /**
     * Obtiene estadísticas de uso actual
     */
    public function getUsageStats() {
        $stats = [];
        
        // Stats por servicio
        foreach (self::MAX_REQUESTS_PER_MINUTE as $service => $limit) {
            $key = $this->getCacheKey($service, 'aggregate');
            $data = $this->getRequestData($key);
            
            $windowStart = time() - 60;
            $recentRequests = array_filter($data['requests'] ?? [], function($ts) use ($windowStart) {
                return $ts > $windowStart;
            });
            
            $stats['services'][$service] = [
                'current_rpm' => count($recentRequests),
                'limit_rpm' => $limit,
                'utilization' => (count($recentRequests) / $limit) * 100
            ];
        }
        
        // Character usage para ElevenLabs
        $monthKey = date('Y-m');
        $charFile = $this->cacheDir . "elevenlabs_chars_{$monthKey}.json";
        
        if (file_exists($charFile)) {
            $charData = json_decode(file_get_contents($charFile), true);
            $stats['elevenlabs_characters'] = [
                'used_this_month' => $charData['total'] ?? 0,
                'limit' => self::LIMITS['elevenlabs_characters_per_month'],
                'daily_breakdown' => $charData['daily'] ?? []
            ];
        }
        
        return $stats;
    }
    
    /**
     * Implementa circuit breaker para servicios
     */
    public function checkCircuitBreaker($service) {
        $file = $this->cacheDir . "circuit_{$service}.json";
        
        if (!file_exists($file)) {
            return ['status' => 'closed', 'can_proceed' => true];
        }
        
        $data = json_decode(file_get_contents($file), true);
        $now = time();
        
        // Circuit breaker states: closed (normal), open (blocked), half-open (testing)
        
        if ($data['state'] === 'open') {
            if ($now > $data['retry_after']) {
                // Cambiar a half-open para probar
                $data['state'] = 'half-open';
                $data['test_requests'] = 0;
                file_put_contents($file, json_encode($data));
                
                return ['status' => 'half-open', 'can_proceed' => true];
            }
            
            return [
                'status' => 'open',
                'can_proceed' => false,
                'retry_after' => $data['retry_after'] - $now,
                'reason' => $data['reason'] ?? 'Service temporarily unavailable'
            ];
        }
        
        if ($data['state'] === 'half-open') {
            // En half-open, permitimos algunas requests de prueba
            if ($data['test_requests'] < 3) {
                $data['test_requests']++;
                file_put_contents($file, json_encode($data));
                return ['status' => 'half-open', 'can_proceed' => true];
            }
            
            // Si ya probamos suficiente y sigue fallando, volver a open
            return ['status' => 'half-open', 'can_proceed' => false];
        }
        
        return ['status' => 'closed', 'can_proceed' => true];
    }
    
    /**
     * Registra fallo de servicio
     */
    public function recordFailure($service, $reason = null) {
        $file = $this->cacheDir . "circuit_{$service}.json";
        
        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }
        
        $data['failures'] = ($data['failures'] ?? 0) + 1;
        $data['last_failure'] = time();
        $data['reason'] = $reason;
        
        // Si hay muchos fallos, abrir el circuit
        if ($data['failures'] >= 5) {
            $data['state'] = 'open';
            $data['retry_after'] = time() + 60; // Bloquear por 1 minuto
            
            $this->log('error', 'Circuit breaker opened', [
                'service' => $service,
                'failures' => $data['failures'],
                'reason' => $reason
            ]);
        }
        
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Registra éxito de servicio
     */
    public function recordSuccess($service) {
        $file = $this->cacheDir . "circuit_{$service}.json";
        
        if (!file_exists($file)) {
            return;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if ($data['state'] === 'half-open') {
            // Si estaba en half-open y tuvo éxito, cerrar el circuit
            $data['state'] = 'closed';
            $data['failures'] = 0;
            
            $this->log('info', 'Circuit breaker closed', [
                'service' => $service
            ]);
        }
        
        $data['last_success'] = time();
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Limpia datos antiguos de rate limiting
     */
    public function cleanup() {
        $files = glob($this->cacheDir . '*.json');
        $now = time();
        $deleted = 0;
        
        foreach ($files as $file) {
            // Eliminar archivos de más de 24 horas
            if ($now - filemtime($file) > 86400) {
                unlink($file);
                $deleted++;
            }
        }
        
        $this->log('info', 'Rate limiter cleanup', ['deleted_files' => $deleted]);
        return $deleted;
    }
    
    private function getCacheKey($service, $identifier) {
        return md5($service . '_' . $identifier);
    }
    
    private function getRequestData($key) {
        $file = $this->cacheDir . $key . '.json';
        
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }
        
        return ['requests' => []];
    }
    
    private function saveRequestData($key, $data) {
        $file = $this->cacheDir . $key . '.json';
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Sistema de logging estructurado
     */
    private function initLogger() {
        $this->logger = function($level, $message, $context = []) {
            $logFile = '/var/www/casa/src/api/v2/logs/rate-limiter.jsonl';
            
            $entry = [
                'timestamp' => date('c'),
                'level' => $level,
                'service' => 'RateLimiter',
                'message' => $message,
                'context' => $context
            ];
            
            file_put_contents(
                $logFile,
                json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND | LOCK_EX
            );
        };
    }
    
    private function log($level, $message, $context = []) {
        call_user_func($this->logger, $level, $message, $context);
    }
}