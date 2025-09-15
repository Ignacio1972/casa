<?php
/**
 * Servicio de Música - Gestión de archivos de música para jingles
 */

require_once __DIR__ . '/config.php';

/**
 * Obtener lista de música disponible con metadata
 */
function getAvailableMusic() {
    $musicDir = dirname(dirname(__DIR__)) . '/public/audio/music/';
    $music = [];
    
    // Lista predefinida de música con nombres amigables basada en archivos existentes
    $musicMetadata = [
        'Martin Roth - Just Sine Waves.mp3' => [
            'name' => 'Just Sine Waves - Relajante',
            'category' => 'ambient',
            'mood' => 'calm',
            'description' => 'Música ambiente suave ideal para mensajes informativos'
        ],
        'Martin Roth - The Silence Between the Notes.mp3' => [
            'name' => 'The Silence - Contemplativo',
            'category' => 'ambient',
            'mood' => 'peaceful',
            'description' => 'Música tranquila perfecta para mensajes reflexivos'
        ],
        'Charly García - Pasajera en Trance.mp3' => [
            'name' => 'Pasajera en Trance - Rock',
            'category' => 'rock',
            'mood' => 'energetic',
            'description' => 'Rock argentino con energía para mensajes dinámicos'
        ],
        'Lucie Treacher - Origata Atelier.mp3' => [
            'name' => 'Origata Atelier - Moderno',
            'category' => 'electronic',
            'mood' => 'creative',
            'description' => 'Música electrónica moderna para mensajes innovadores'
        ],
        'Maneesh de Moor - Oracle.mp3' => [
            'name' => 'Oracle - Místico',
            'category' => 'world',
            'mood' => 'mystical',
            'description' => 'Música world para mensajes espirituales o zen'
        ],
        'Mark Alow - En La Niebla (Oiseau De Nuit Remix).mp3' => [
            'name' => 'En La Niebla - Deep House',
            'category' => 'electronic',
            'mood' => 'deep',
            'description' => 'Deep house atmosférico para mensajes sofisticados'
        ],
        'Mark Isham - Raffles In Rio.mp3' => [
            'name' => 'Raffles In Rio - Jazz',
            'category' => 'jazz',
            'mood' => 'smooth',
            'description' => 'Jazz suave ideal para mensajes elegantes'
        ],
        'Max Cooper - Reflect.mp3' => [
            'name' => 'Reflect - Experimental',
            'category' => 'electronic',
            'mood' => 'introspective',
            'description' => 'Electrónica experimental para mensajes únicos'
        ]
    ];
    
    // Obtener TODOS los archivos de música de la carpeta
    if (is_dir($musicDir)) {
        $files = glob($musicDir . '*.{mp3,wav,ogg}', GLOB_BRACE);
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Si existe metadata predefinida, usarla. Si no, usar el nombre del archivo
            if (isset($musicMetadata[$filename])) {
                $music[] = array_merge($musicMetadata[$filename], [
                    'file' => $filename,
                    'size' => filesize($file),
                    'duration' => getMusicDuration($file)
                ]);
            } else {
                // Para archivos sin metadata, usar el nombre del archivo
                $music[] = [
                    'file' => $filename,
                    'name' => pathinfo($filename, PATHINFO_FILENAME),
                    'category' => 'general',
                    'mood' => 'neutral',
                    'description' => 'Música de fondo',
                    'size' => filesize($file),
                    'duration' => getMusicDuration($file)
                ];
            }
        }
    }
    
    return $music;
}

/**
 * Obtener duración de archivo de audio (requiere ffprobe)
 */
function getMusicDuration($file) {
    if (!file_exists($file)) return 0;
    
    $cmd = sprintf('ffprobe -v error -show_entries format=duration -of csv=p=0 "%s" 2>&1', $file);
    $duration = @shell_exec($cmd);
    
    if ($duration && is_numeric(trim($duration))) {
        return floatval(trim($duration));
    }
    
    return 0;
}

/**
 * Obtener música recomendada según categoría del mensaje
 */
function getRecommendedMusic($messageCategory) {
    $recommendations = [
        'ofertas' => ['Charly García - Pasajera en Trance.mp3', 'Max Cooper - Reflect.mp3'],
        'eventos' => ['Charly García - Pasajera en Trance.mp3', 'Mark Alow - En La Niebla (Oiseau De Nuit Remix).mp3'],
        'informacion' => ['Martin Roth - Just Sine Waves.mp3', 'Martin Roth - The Silence Between the Notes.mp3'],
        'servicios' => ['Mark Isham - Raffles In Rio.mp3', 'Lucie Treacher - Origata Atelier.mp3'],
        'horarios' => ['Martin Roth - Just Sine Waves.mp3', 'Mark Isham - Raffles In Rio.mp3'],
        'emergencias' => ['Max Cooper - Reflect.mp3'],
        'sin_categoria' => ['Martin Roth - Just Sine Waves.mp3', 'Maneesh de Moor - Oracle.mp3']
    ];
    
    $category = $messageCategory ?: 'sin_categoria';
    $recommendedFiles = $recommendations[$category] ?? $recommendations['sin_categoria'];
    
    $availableMusic = getAvailableMusic();
    $recommended = [];
    
    foreach ($recommendedFiles as $file) {
        foreach ($availableMusic as $music) {
            if ($music['file'] === $file) {
                $recommended[] = $music;
                break;
            }
        }
    }
    
    return $recommended;
}

// Procesar requests si se llama directamente
if (basename($_SERVER['SCRIPT_NAME']) === 'music-service.php') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                echo json_encode([
                    'success' => true,
                    'music' => getAvailableMusic()
                ]);
                break;
                
            case 'recommend':
                $category = $input['category'] ?? 'sin_categoria';
                echo json_encode([
                    'success' => true,
                    'music' => getRecommendedMusic($category),
                    'category' => $category
                ]);
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>