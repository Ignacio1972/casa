<?php
/**
 * Monitor de uso del Modo Automático
 * Permite ver estadísticas de uso por access_token, IP, cliente, etc.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar autenticación simple (puedes cambiar esto por algo más seguro)
$adminKey = $_GET['admin_key'] ?? '';
if ($adminKey !== 'casa-admin-2024') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = new SQLite3('/var/www/casa/database/casa.db');
    
    // Tipo de reporte
    $reportType = $_GET['report'] ?? 'summary';
    $accessToken = $_GET['access_token'] ?? null;
    $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
    
    switch($reportType) {
        case 'summary':
            // Resumen general
            $result = [];
            
            // Total de generaciones
            $query = $db->query("
                SELECT 
                    COUNT(*) as total_generations,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT client_id) as unique_clients,
                    COUNT(DISTINCT access_token) as unique_tokens,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed
                FROM automatic_usage_tracking
                WHERE created_at > datetime('now', '-$days days')
            ");
            $result['overall'] = $query->fetchArray(SQLITE3_ASSOC);
            
            // Por access_token
            $query = $db->query("
                SELECT 
                    access_token,
                    COUNT(*) as generations,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT session_id) as sessions,
                    MIN(created_at) as first_use,
                    MAX(created_at) as last_use
                FROM automatic_usage_tracking
                WHERE created_at > datetime('now', '-$days days')
                GROUP BY access_token
                ORDER BY generations DESC
            ");
            $result['by_token'] = [];
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                $result['by_token'][] = $row;
            }
            
            // Por IP (top 10 más activas)
            $query = $db->query("
                SELECT 
                    ip_address,
                    COUNT(*) as generations,
                    COUNT(DISTINCT access_token) as tokens_used,
                    COUNT(DISTINCT client_id) as clients_used,
                    MAX(created_at) as last_activity
                FROM automatic_usage_tracking
                WHERE created_at > datetime('now', '-$days days')
                GROUP BY ip_address
                ORDER BY generations DESC
                LIMIT 10
            ");
            $result['top_ips'] = [];
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                $result['top_ips'][] = $row;
            }
            
            // Por voz utilizada
            $query = $db->query("
                SELECT 
                    voice_used,
                    COUNT(*) as uses
                FROM automatic_usage_tracking
                WHERE created_at > datetime('now', '-$days days')
                GROUP BY voice_used
                ORDER BY uses DESC
            ");
            $result['by_voice'] = [];
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                $result['by_voice'][] = $row;
            }
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'token_detail':
            // Detalle por access_token específico
            if (!$accessToken) {
                throw new Exception('access_token parameter required');
            }
            
            $stmt = $db->prepare("
                SELECT 
                    created_at,
                    client_id,
                    ip_address,
                    voice_used,
                    music_file,
                    duration_seconds,
                    success,
                    error_message,
                    substr(audio_text, 1, 100) as text_preview
                FROM automatic_usage_tracking
                WHERE access_token = ?
                AND created_at > datetime('now', '-$days days')
                ORDER BY created_at DESC
            ");
            $stmt->bindValue(1, $accessToken, SQLITE3_TEXT);
            
            $result = [];
            $queryResult = $stmt->execute();
            while ($row = $queryResult->fetchArray(SQLITE3_ASSOC)) {
                $result[] = $row;
            }
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'hourly':
            // Uso por hora en los últimos días
            $query = $db->query("
                SELECT 
                    strftime('%Y-%m-%d %H:00', created_at) as hour,
                    COUNT(*) as generations,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM automatic_usage_tracking
                WHERE created_at > datetime('now', '-$days days')
                GROUP BY hour
                ORDER BY hour DESC
            ");
            
            $result = [];
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                $result[] = $row;
            }
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'suspicious':
            // Actividad sospechosa (muchas generaciones desde una IP o token)
            $threshold = 50; // Más de 50 generaciones se considera sospechoso
            
            $query = $db->query("
                SELECT 
                    ip_address,
                    access_token,
                    COUNT(*) as generations,
                    COUNT(DISTINCT client_id) as clients,
                    MIN(created_at) as first_use,
                    MAX(created_at) as last_use,
                    ROUND((julianday(MAX(created_at)) - julianday(MIN(created_at))) * 24, 2) as hours_active
                FROM automatic_usage_tracking
                WHERE created_at > datetime('now', '-$days days')
                GROUP BY ip_address, access_token
                HAVING generations > $threshold
                ORDER BY generations DESC
            ");
            
            $result = [];
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                // Calcular tasa de generación por hora
                if ($row['hours_active'] > 0) {
                    $row['rate_per_hour'] = round($row['generations'] / $row['hours_active'], 2);
                } else {
                    $row['rate_per_hour'] = $row['generations'];
                }
                $result[] = $row;
            }
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        default:
            throw new Exception('Invalid report type');
    }
    
    $db->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>