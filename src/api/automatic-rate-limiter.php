<?php
/**
 * Rate Limiter para Modo Automático
 * Limita el número de generaciones por IP/token
 */

class AutomaticRateLimiter {
    private $db;
    
    // Configuración de límites
    private $limits = [
        'per_minute' => 5,      // Máximo 5 generaciones por minuto
        'per_hour' => 30,       // Máximo 30 generaciones por hora  
        'per_day' => 100,       // Máximo 100 generaciones por día
        'per_token_day' => 150  // Máximo 150 por token por día
    ];
    
    public function __construct() {
        $this->db = new SQLite3('/var/www/casa/database/casa.db');
    }
    
    /**
     * Verificar si se puede hacer una nueva generación
     */
    public function canGenerate($ipAddress, $accessToken = null) {
        $errors = [];
        
        // Verificar límite por minuto (por IP)
        $count = $this->getCount($ipAddress, null, 1);
        if ($count >= $this->limits['per_minute']) {
            $errors[] = "Límite por minuto excedido ({$this->limits['per_minute']} generaciones)";
        }
        
        // Verificar límite por hora (por IP)
        $count = $this->getCount($ipAddress, null, 60);
        if ($count >= $this->limits['per_hour']) {
            $errors[] = "Límite por hora excedido ({$this->limits['per_hour']} generaciones)";
        }
        
        // Verificar límite por día (por IP)
        $count = $this->getCount($ipAddress, null, 1440);
        if ($count >= $this->limits['per_day']) {
            $errors[] = "Límite diario excedido ({$this->limits['per_day']} generaciones)";
        }
        
        // Verificar límite por token si existe
        if ($accessToken && $accessToken !== 'direct') {
            $count = $this->getCount(null, $accessToken, 1440);
            if ($count >= $this->limits['per_token_day']) {
                $errors[] = "Límite diario por token excedido ({$this->limits['per_token_day']} generaciones)";
            }
        }
        
        if (!empty($errors)) {
            return [
                'allowed' => false,
                'errors' => $errors,
                'retry_after' => $this->getRetryAfter($ipAddress)
            ];
        }
        
        return [
            'allowed' => true,
            'remaining' => [
                'minute' => $this->limits['per_minute'] - $this->getCount($ipAddress, null, 1),
                'hour' => $this->limits['per_hour'] - $this->getCount($ipAddress, null, 60),
                'day' => $this->limits['per_day'] - $this->getCount($ipAddress, null, 1440)
            ]
        ];
    }
    
    /**
     * Obtener conteo de generaciones
     */
    private function getCount($ipAddress = null, $accessToken = null, $minutes = 60) {
        $conditions = [];
        $params = [];
        
        if ($ipAddress) {
            $conditions[] = "ip_address = ?";
            $params[] = $ipAddress;
        }
        
        if ($accessToken) {
            $conditions[] = "access_token = ?";
            $params[] = $accessToken;
        }
        
        $conditions[] = "created_at > datetime('now', '-$minutes minutes')";
        $conditions[] = "success = 1"; // Solo contar las exitosas
        
        $whereClause = implode(' AND ', $conditions);
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM automatic_usage_tracking
            WHERE $whereClause
        ");
        
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param, SQLITE3_TEXT);
        }
        
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        return $row['count'] ?? 0;
    }
    
    /**
     * Calcular tiempo hasta poder reintentar
     */
    private function getRetryAfter($ipAddress) {
        // Buscar la generación más reciente
        $stmt = $this->db->prepare("
            SELECT created_at
            FROM automatic_usage_tracking
            WHERE ip_address = ?
            AND success = 1
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        $stmt->bindValue(1, $ipAddress, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row) {
            $lastGeneration = strtotime($row['created_at']);
            $now = time();
            $elapsed = $now - $lastGeneration;
            
            // Si han pasado menos de 60 segundos, esperar el resto
            if ($elapsed < 60) {
                return 60 - $elapsed;
            }
        }
        
        return 60; // Por defecto, esperar 1 minuto
    }
    
    /**
     * Obtener estadísticas de uso para un IP/token
     */
    public function getUsageStats($ipAddress = null, $accessToken = null) {
        $stats = [
            'last_24h' => $this->getCount($ipAddress, $accessToken, 1440),
            'last_hour' => $this->getCount($ipAddress, $accessToken, 60),
            'last_minute' => $this->getCount($ipAddress, $accessToken, 1),
            'limits' => $this->limits
        ];
        
        return $stats;
    }
    
    /**
     * Bloquear IP temporalmente
     */
    public function blockIP($ipAddress, $reason, $hours = 24) {
        $stmt = $this->db->prepare("
            INSERT INTO ip_blocks (ip_address, reason, blocked_until, created_at)
            VALUES (?, ?, datetime('now', '+$hours hours'), datetime('now'))
        ");
        
        $stmt->bindValue(1, $ipAddress, SQLITE3_TEXT);
        $stmt->bindValue(2, $reason, SQLITE3_TEXT);
        
        return $stmt->execute();
    }
    
    /**
     * Verificar si una IP está bloqueada
     */
    public function isBlocked($ipAddress) {
        // Primero crear la tabla si no existe
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ip_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                reason TEXT,
                blocked_until TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $this->db->prepare("
            SELECT reason, blocked_until
            FROM ip_blocks
            WHERE ip_address = ?
            AND blocked_until > datetime('now')
            ORDER BY blocked_until DESC
            LIMIT 1
        ");
        
        $stmt->bindValue(1, $ipAddress, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row) {
            return [
                'blocked' => true,
                'reason' => $row['reason'],
                'until' => $row['blocked_until']
            ];
        }
        
        return ['blocked' => false];
    }
    
    public function __destruct() {
        $this->db->close();
    }
}

// Si se llama directamente para verificar límites
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $accessToken = $_GET['access_token'] ?? $_POST['access_token'] ?? 'direct';
    
    $limiter = new AutomaticRateLimiter();
    
    // Verificar si está bloqueada
    $blockStatus = $limiter->isBlocked($ipAddress);
    if ($blockStatus['blocked']) {
        http_response_code(403);
        echo json_encode([
            'error' => 'IP bloqueada',
            'reason' => $blockStatus['reason'],
            'until' => $blockStatus['until']
        ]);
        exit;
    }
    
    // Verificar límites
    $result = $limiter->canGenerate($ipAddress, $accessToken);
    
    if (!$result['allowed']) {
        http_response_code(429);
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'errors' => $result['errors'],
            'retry_after' => $result['retry_after']
        ]);
    } else {
        echo json_encode([
            'allowed' => true,
            'remaining' => $result['remaining'],
            'stats' => $limiter->getUsageStats($ipAddress, $accessToken)
        ]);
    }
}
?>