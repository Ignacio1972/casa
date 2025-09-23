<?php
/**
 * Lista de mensajes del modo automático con texto completo
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = new SQLite3('/var/www/casa/database/casa.db');
    
    // Parámetros
    $token = $_GET['token'] ?? null;
    $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    // Construir query
    $where = ["created_at > datetime('now', '-$days days')"];
    
    if ($token) {
        $where[] = "access_token = '" . $db->escapeString($token) . "'";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Obtener mensajes recientes con texto
    $query = $db->query("
        SELECT 
            t.created_at,
            t.access_token,
            t.ip_address,
            t.voice_used,
            t.audio_text as texto_original,
            t.success,
            t.error_message,
            -- Obtener el texto mejorado desde audio_metadata
            (SELECT description 
             FROM audio_metadata 
             WHERE category = 'automatic' 
             AND ABS(strftime('%s', audio_metadata.created_at) - strftime('%s', t.created_at)) <= 5
             ORDER BY created_at DESC 
             LIMIT 1) as texto_mejorado
        FROM automatic_usage_tracking t
        WHERE $whereClause
        ORDER BY t.created_at DESC
        LIMIT $limit
    ");
    
    $messages = [];
    while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
        // Formatear tiempo relativo
        $created = new DateTime($row['created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created->getTimestamp();
        
        if ($diff < 60) {
            $timeAgo = 'Hace ' . $diff . ' segundos';
        } elseif ($diff < 3600) {
            $timeAgo = 'Hace ' . floor($diff / 60) . ' minutos';
        } elseif ($diff < 86400) {
            $timeAgo = 'Hace ' . floor($diff / 3600) . ' horas';
        } else {
            $timeAgo = 'Hace ' . floor($diff / 86400) . ' días';
        }
        
        $messages[] = [
            'created_at' => $row['created_at'],
            'time_ago' => $timeAgo,
            'access_token' => $row['access_token'],
            'ip_address' => $row['ip_address'],
            'voice_used' => $row['voice_used'],
            'texto_original' => $row['texto_original'],
            'texto_mejorado' => $row['texto_mejorado'],
            'success' => $row['success'] == 1,
            'error' => $row['error_message']
        ];
    }
    
    // También obtener estadísticas rápidas
    $statsQuery = $db->query("
        SELECT 
            COUNT(*) as total,
            COUNT(DISTINCT ip_address) as unique_ips,
            COUNT(DISTINCT access_token) as unique_tokens,
            COUNT(DISTINCT voice_used) as unique_voices
        FROM automatic_usage_tracking
        WHERE $whereClause
    ");
    
    $stats = $statsQuery->fetchArray(SQLITE3_ASSOC);
    
    $db->close();
    
    echo json_encode([
        'messages' => $messages,
        'stats' => $stats,
        'filter' => [
            'token' => $token,
            'days' => $days,
            'limit' => $limit
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>