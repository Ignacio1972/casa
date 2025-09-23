<?php
/**
 * Sistema Simple de Tracking para Modo Automático
 * Solo registra uso, sin rate limiting
 */

class SimpleUsageTracker {
    
    /**
     * Registrar uso en la base de datos
     */
    public static function track($text, $voiceId, $success = true, $error = null) {
        try {
            $db = new SQLite3('/var/www/casa/database/casa.db');
            
            // Obtener información básica
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $accessToken = $_GET['access'] ?? 'direct';
            
            // Insertar registro
            $stmt = $db->prepare("
                INSERT INTO automatic_usage_tracking 
                (client_id, access_token, ip_address, user_agent, audio_text, 
                 voice_used, success, error_message, created_at) 
                VALUES ('web', ?, ?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            
            $stmt->bindValue(1, $accessToken, SQLITE3_TEXT);
            $stmt->bindValue(2, $ipAddress, SQLITE3_TEXT);
            $stmt->bindValue(3, substr($userAgent, 0, 200), SQLITE3_TEXT);
            $stmt->bindValue(4, substr($text, 0, 500), SQLITE3_TEXT);
            $stmt->bindValue(5, $voiceId, SQLITE3_TEXT);
            $stmt->bindValue(6, $success ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(7, $error, SQLITE3_TEXT);
            
            $stmt->execute();
            $db->close();
            
            // Log para debug
            error_log("[Tracking] IP: $ipAddress, Token: $accessToken, Voice: $voiceId");
            
        } catch (Exception $e) {
            // Si falla el tracking, no interrumpir el servicio principal
            error_log("[Tracking Error] " . $e->getMessage());
        }
    }
    
    /**
     * Obtener estadísticas simples
     */
    public static function getStats($accessToken = null, $days = 7) {
        try {
            $db = new SQLite3('/var/www/casa/database/casa.db');
            
            $where = "created_at > datetime('now', '-$days days')";
            if ($accessToken) {
                $where .= " AND access_token = " . $db->escapeString($accessToken);
            }
            
            $query = $db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT DATE(created_at)) as days_active,
                    MAX(created_at) as last_use
                FROM automatic_usage_tracking
                WHERE $where
            ");
            
            $stats = $query->fetchArray(SQLITE3_ASSOC);
            $db->close();
            
            return $stats;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// Si se llama directamente para ver estadísticas
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    
    $token = $_GET['token'] ?? null;
    $days = $_GET['days'] ?? 7;
    
    echo json_encode(SimpleUsageTracker::getStats($token, $days), JSON_PRETTY_PRINT);
}
?>