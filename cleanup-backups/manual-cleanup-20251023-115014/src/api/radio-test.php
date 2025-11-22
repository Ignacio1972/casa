<?php
/**
 * Endpoint para testing de conexión con radios
 * Tests básicos de conectividad con AzuraCast local y remoto
 * @version 1.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cargar variables de entorno
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"');
            $_ENV[$key] = $value;
        }
    }
}

function logTest($message) {
    $logFile = __DIR__ . '/logs/radio-test-' . date('Y-m-d') . '.log';
    $logMessage = date('Y-m-d H:i:s') . " [RadioTest] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    error_log("[RadioTest] $message");
}

/**
 * Test básico de conectividad
 */
function testConnection($config) {
    try {
        $url = $config['url'] . '/api/status';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'status' => 'Conectado',
                'version' => $data['version'] ?? 'Desconocida',
                'response_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME)
            ];
        } else {
            return [
                'success' => false,
                'status' => "Error HTTP $httpCode",
                'response' => $response
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'status' => 'Error de conexión',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Test de información de estación
 */
function testStationInfo($config) {
    try {
        $url = $config['url'] . '/api/station/' . $config['station_id'];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $config['api_key'],
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $station = json_decode($response, true);
            return [
                'success' => true,
                'name' => $station['name'] ?? 'Sin nombre',
                'description' => $station['description'] ?? '',
                'is_enabled' => $station['is_enabled'] ?? false,
                'listen_url' => $station['listen_url'] ?? ''
            ];
        } else {
            return [
                'success' => false,
                'error' => "Error HTTP $httpCode al obtener info de estación"
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
 * Test de upload de archivo
 */
function testFileUpload($config, $filePath) {
    try {
        if (!file_exists($filePath)) {
            throw new Exception("Archivo no encontrado: $filePath");
        }

        $url = $config['url'] . '/api/station/' . $config['station_id'] . '/files';
        
        $postData = [
            'file' => new CURLFile($filePath),
            'path' => '/test-tts/'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $config['api_key'],
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'file_id' => $result['id'] ?? null,
                'path' => $result['path'] ?? null,
                'size' => filesize($filePath),
                'filename' => basename($filePath),
                'message' => 'Archivo subido exitosamente'
            ];
        } else {
            return [
                'success' => false,
                'error' => "Error HTTP $httpCode",
                'response' => $response
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
 * Test de playlists
 */
function testPlaylists($config) {
    try {
        $url = $config['url'] . '/api/station/' . $config['station_id'] . '/playlists';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $config['api_key'],
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $playlists = json_decode($response, true);
            return [
                'success' => true,
                'count' => count($playlists),
                'playlists' => array_map(function($p) {
                    return [
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'is_enabled' => $p['is_enabled'] ?? false
                    ];
                }, $playlists)
            ];
        } else {
            return [
                'success' => false,
                'error' => "Error HTTP $httpCode al obtener playlists"
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Procesar request
try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    
    // Si no hay input, intentar desde stdin
    if (empty($input) && !empty($rawInput)) {
        $input = json_decode($rawInput, true) ?? [];
    }
    
    $action = $input['action'] ?? $_GET['action'] ?? 'test_all';

    $configs = [
        'local' => [
            'name' => 'Radio Mall (Local)',
            'url' => $_ENV['AZURACAST_BASE_URL'] ?? 'http://51.222.25.222',
            'api_key' => $_ENV['AZURACAST_API_KEY'] ?? '',
            'station_id' => $_ENV['AZURACAST_STATION_ID'] ?? 1
        ],
        'remote' => [
            'name' => 'Radio Test Isla Negra (Remoto)',
            'url' => $_ENV['AZURACAST_REMOTE_URL'] ?? 'http://51.91.252.76:8060',
            'api_key' => $_ENV['AZURACAST_REMOTE_API_KEY'] ?? '',
            'station_id' => $_ENV['AZURACAST_REMOTE_STATION_ID'] ?? 4
        ]
    ];

    logTest("Iniciando tests - Acción: $action");

    switch ($action) {
        case 'test_connection':
            $target = $input['target'] ?? 'remote';
            $config = $configs[$target];
            $result = testConnection($config);
            logTest("Test conexión [$target]: " . ($result['success'] ? 'OK' : 'FAIL'));
            echo json_encode([
                'action' => 'test_connection',
                'target' => $target,
                'config_name' => $config['name'],
                'result' => $result
            ]);
            break;

        case 'test_station':
            $target = $input['target'] ?? 'remote';
            $config = $configs[$target];
            $result = testStationInfo($config);
            logTest("Test estación [$target]: " . ($result['success'] ? 'OK' : 'FAIL'));
            echo json_encode([
                'action' => 'test_station',
                'target' => $target,
                'config_name' => $config['name'],
                'result' => $result
            ]);
            break;

        case 'test_playlists':
            $target = $input['target'] ?? 'remote';
            $config = $configs[$target];
            $result = testPlaylists($config);
            logTest("Test playlists [$target]: " . ($result['success'] ? 'OK' : 'FAIL'));
            echo json_encode([
                'action' => 'test_playlists',
                'target' => $target,
                'config_name' => $config['name'],
                'result' => $result
            ]);
            break;

        case 'test_upload':
            $target = $input['target'] ?? 'remote';
            $filePath = $input['file_path'] ?? '';
            
            if (empty($filePath)) {
                echo json_encode([
                    'action' => 'test_upload',
                    'error' => 'file_path es requerido'
                ]);
                break;
            }

            $config = $configs[$target];
            $result = testFileUpload($config, $filePath);
            logTest("Test upload [$target]: " . ($result['success'] ? 'OK' : 'FAIL') . 
                   " - Archivo: " . basename($filePath));
            
            echo json_encode([
                'action' => 'test_upload',
                'target' => $target,
                'config_name' => $config['name'],
                'file_path' => $filePath,
                'result' => $result
            ]);
            break;

        case 'test_all':
        default:
            $results = [];
            
            foreach ($configs as $key => $config) {
                logTest("Iniciando test completo para: " . $config['name']);
                
                $results[$key] = [
                    'name' => $config['name'],
                    'url' => $config['url'],
                    'station_id' => $config['station_id'],
                    'connection' => testConnection($config),
                    'station' => testStationInfo($config),
                    'playlists' => testPlaylists($config)
                ];
                
                $success = $results[$key]['connection']['success'] && 
                          $results[$key]['station']['success'] && 
                          $results[$key]['playlists']['success'];
                
                logTest("Test completo [$key]: " . ($success ? 'OK' : 'FAIL'));
            }
            
            echo json_encode([
                'action' => 'test_all',
                'timestamp' => date('Y-m-d H:i:s'),
                'results' => $results
            ]);
            break;
    }

} catch (Exception $e) {
    logTest("Error en tests: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}