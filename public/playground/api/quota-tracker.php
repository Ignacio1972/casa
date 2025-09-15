<?php
/**
 * Quota Tracker for ElevenLabs API
 * Tracks character usage and provides statistics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Archivo de tracking de quota
$quotaFile = __DIR__ . '/../../../src/api/data/quota-usage.json';

// Límites de quota por mes (ElevenLabs Free Tier)
define('MONTHLY_CHARACTER_LIMIT', 10000); // 10K caracteres gratis por mes
define('RESET_DAY', 1); // Día del mes que se resetea

// Asegurar que existe el archivo
if (!file_exists($quotaFile)) {
    $initialData = [
        'current_month' => date('Y-m'),
        'usage' => [
            'characters' => 0,
            'requests' => 0,
            'generations' => []
        ],
        'last_updated' => date('c')
    ];
    file_put_contents($quotaFile, json_encode($initialData, JSON_PRETTY_PRINT));
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Función para verificar si necesitamos resetear el contador mensual
function checkMonthlyReset($data) {
    $currentMonth = date('Y-m');
    if ($data['current_month'] !== $currentMonth) {
        // Nuevo mes, resetear contadores
        return [
            'current_month' => $currentMonth,
            'usage' => [
                'characters' => 0,
                'requests' => 0,
                'generations' => []
            ],
            'last_updated' => date('c')
        ];
    }
    return $data;
}

// Función para calcular la fecha de reset
function getResetDate() {
    $currentDay = date('j');
    $currentMonth = date('n');
    $currentYear = date('Y');
    
    if ($currentDay >= RESET_DAY) {
        // El reset es el próximo mes
        if ($currentMonth == 12) {
            $resetMonth = 1;
            $resetYear = $currentYear + 1;
        } else {
            $resetMonth = $currentMonth + 1;
            $resetYear = $currentYear;
        }
    } else {
        // El reset es este mes
        $resetMonth = $currentMonth;
        $resetYear = $currentYear;
    }
    
    return date('Y-m-d', mktime(0, 0, 0, $resetMonth, RESET_DAY, $resetYear));
}

switch($action) {
    case 'get_quota':
        // Obtener información actual de quota
        $data = json_decode(file_get_contents($quotaFile), true);
        $data = checkMonthlyReset($data);
        
        $used = $data['usage']['characters'];
        $limit = MONTHLY_CHARACTER_LIMIT;
        $remaining = max(0, $limit - $used);
        $percentage = ($used / $limit) * 100;
        
        $response = [
            'success' => true,
            'quota' => [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'percentage' => round($percentage, 2),
                'requests' => $data['usage']['requests'],
                'reset_date' => getResetDate(),
                'last_updated' => $data['last_updated']
            ]
        ];
        
        // Guardar datos actualizados si hubo reset
        file_put_contents($quotaFile, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode($response);
        break;
        
    case 'track_usage':
        // Registrar uso de caracteres
        $data = json_decode(file_get_contents($quotaFile), true);
        $data = checkMonthlyReset($data);
        
        $characters = intval($input['characters'] ?? 0);
        $voice = $input['voice'] ?? 'unknown';
        $success = $input['success'] ?? true;
        
        if ($characters > 0) {
            $data['usage']['characters'] += $characters;
            $data['usage']['requests']++;
            
            // Agregar a historial de generaciones (mantener últimas 100)
            $generation = [
                'timestamp' => date('c'),
                'characters' => $characters,
                'voice' => $voice,
                'success' => $success
            ];
            
            array_unshift($data['usage']['generations'], $generation);
            $data['usage']['generations'] = array_slice($data['usage']['generations'], 0, 100);
            
            $data['last_updated'] = date('c');
            
            file_put_contents($quotaFile, json_encode($data, JSON_PRETTY_PRINT));
            
            echo json_encode([
                'success' => true,
                'message' => 'Usage tracked',
                'current_usage' => $data['usage']['characters']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid character count'
            ]);
        }
        break;
        
    case 'get_statistics':
        // Obtener estadísticas detalladas
        $data = json_decode(file_get_contents($quotaFile), true);
        $data = checkMonthlyReset($data);
        
        // Analizar generaciones
        $generations = $data['usage']['generations'] ?? [];
        $voiceStats = [];
        $dailyStats = [];
        
        foreach ($generations as $gen) {
            // Estadísticas por voz
            $voice = $gen['voice'];
            if (!isset($voiceStats[$voice])) {
                $voiceStats[$voice] = [
                    'count' => 0,
                    'characters' => 0
                ];
            }
            $voiceStats[$voice]['count']++;
            $voiceStats[$voice]['characters'] += $gen['characters'];
            
            // Estadísticas por día
            $day = date('Y-m-d', strtotime($gen['timestamp']));
            if (!isset($dailyStats[$day])) {
                $dailyStats[$day] = [
                    'count' => 0,
                    'characters' => 0
                ];
            }
            $dailyStats[$day]['count']++;
            $dailyStats[$day]['characters'] += $gen['characters'];
        }
        
        echo json_encode([
            'success' => true,
            'statistics' => [
                'total_characters' => $data['usage']['characters'],
                'total_requests' => $data['usage']['requests'],
                'voice_stats' => $voiceStats,
                'daily_stats' => $dailyStats,
                'recent_generations' => array_slice($generations, 0, 10),
                'month' => $data['current_month']
            ]
        ]);
        break;
        
    case 'reset_quota':
        // Reset manual (solo para testing)
        if (isset($input['confirm']) && $input['confirm'] === true) {
            $data = [
                'current_month' => date('Y-m'),
                'usage' => [
                    'characters' => 0,
                    'requests' => 0,
                    'generations' => []
                ],
                'last_updated' => date('c')
            ];
            file_put_contents($quotaFile, json_encode($data, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'message' => 'Quota reset successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Confirmation required']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action: ' . $action]);
}
?>