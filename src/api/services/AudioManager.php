<?php
/**
 * AudioManager - Orquestador Unificado del Sistema de Audio
 * 
 * Este manager NO reemplaza los servicios existentes, sino que los orquesta
 * para proporcionar una interfaz unificada y preparada para 30+ locales
 * 
 * @version 1.0.0
 * @author Casa Costanera Team
 */

namespace CasaCostanera\Audio;

class AudioManager {
    
    // Configuración centralizada de volúmenes
    private $volumeProfiles = [
        'tts_simple' => [
            'voice_volume' => 1.0,
            'normalization_target' => -16,  // LUFS
            'intro_silence' => 3,
            'outro_silence' => 3
        ],
        'jingle' => [
            'voice_volume' => 0.9,
            'music_volume' => 0.3,
            'normalization_target' => -14,  // LUFS
            'ducking_enabled' => true,
            'ducking_level' => -18
        ],
        'automatic' => [
            'voice_volume' => 0.85,
            'music_volume' => 0.35,
            'normalization_target' => -15,  // LUFS
            'rate_limit' => 10  // mensajes por hora
        ]
    ];
    
    // Servicios existentes que orquestamos
    private $ttsService;
    private $jingleService;
    private $automaticService;
    private $radioService;
    private $audioProcessor;
    
    // Configuración multi-local
    private $stores = [];
    private $defaultStore = 'main';
    
    /**
     * Constructor - Carga servicios existentes
     */
    public function __construct() {
        $this->loadExistingServices();
        $this->loadStoreConfiguration();
        $this->loadVolumeProfiles();
    }
    
    /**
     * Genera audio según tipo con configuración unificada
     * 
     * @param string $type Tipo de audio: 'tts', 'jingle', 'automatic'
     * @param array $params Parámetros del audio
     * @param array $targets Tiendas destino (IDs o 'all')
     * @return array Resultado de la generación
     */
    public function generate($type, array $params, $targets = ['main']) {
        try {
            // 1. Validar tipo y parámetros
            $this->validateRequest($type, $params);
            
            // 2. Aplicar perfil de volumen
            $params = $this->applyVolumeProfile($type, $params);
            
            // 3. Generar audio según tipo
            $audioFile = $this->generateAudio($type, $params);
            
            // 4. Normalizar audio
            $normalizedFile = $this->normalizeAudio($audioFile, $type);
            
            // 5. Distribuir a tiendas objetivo
            $broadcastResult = $this->broadcastToStores($normalizedFile, $targets, $type);
            
            // 6. Registrar en base de datos
            $this->logGeneration($type, $params, $broadcastResult);
            
            return [
                'success' => true,
                'type' => $type,
                'file' => basename($normalizedFile),
                'duration' => $this->getAudioDuration($normalizedFile),
                'lufs' => $this->getAudioLUFS($normalizedFile),
                'broadcast' => $broadcastResult,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->logError($e);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera audio usando servicios existentes
     */
    private function generateAudio($type, $params) {
        switch ($type) {
            case 'tts':
                // Usa generate.php existente
                return $this->ttsService->generate(
                    $params['text'],
                    $params['voice'],
                    $params['voice_settings'] ?? []
                );
                
            case 'jingle':
                // Usa jingle-service.php existente
                return $this->jingleService->createJingle(
                    $params['text'],
                    $params['voice'],
                    $params['music_file'],
                    $params['jingle_config'] ?? []
                );
                
            case 'automatic':
                // Usa automatic-jingle-service-v2.php
                return $this->automaticService->generateAutomatic(
                    $params['text'],
                    $params['context'] ?? 'general',
                    $params['auto_config'] ?? []
                );
                
            default:
                throw new \InvalidArgumentException("Tipo de audio no válido: $type");
        }
    }
    
    /**
     * Broadcast a múltiples tiendas/locales
     */
    private function broadcastToStores($audioFile, $targets, $type) {
        $results = [];
        
        // Resolver targets
        $targetStores = $this->resolveTargets($targets);
        
        // Preparar uploads paralelos
        $uploads = [];
        foreach ($targetStores as $storeId => $store) {
            if ($store['enabled']) {
                $uploads[$storeId] = [
                    'file' => $audioFile,
                    'store' => $store,
                    'priority' => $this->getPriorityForType($type)
                ];
            }
        }
        
        // Ejecutar uploads en paralelo (máximo 5 simultáneos)
        $chunks = array_chunk($uploads, 5, true);
        foreach ($chunks as $chunk) {
            $chunkResults = $this->executeParallelUploads($chunk);
            $results = array_merge($results, $chunkResults);
        }
        
        return [
            'total' => count($targetStores),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'details' => $results
        ];
    }
    
    /**
     * Configuración de volumen centralizada
     */
    public function configureVolume($type, $setting, $value) {
        // Validar tipo
        if (!isset($this->volumeProfiles[$type])) {
            throw new \InvalidArgumentException("Tipo no válido: $type");
        }
        
        // Actualizar configuración
        $this->volumeProfiles[$type][$setting] = $value;
        
        // Persistir en archivo de configuración
        $this->saveVolumeProfiles();
        
        return [
            'success' => true,
            'type' => $type,
            'setting' => $setting,
            'value' => $value
        ];
    }
    
    /**
     * Obtener configuración actual
     */
    public function getConfiguration() {
        return [
            'volume_profiles' => $this->volumeProfiles,
            'stores' => array_map(function($store) {
                return [
                    'id' => $store['id'],
                    'name' => $store['name'],
                    'enabled' => $store['enabled'],
                    'location' => $store['location'] ?? 'N/A'
                ];
            }, $this->stores),
            'services_status' => [
                'tts' => $this->ttsService !== null,
                'jingle' => $this->jingleService !== null,
                'automatic' => $this->automaticService !== null
            ],
            'capabilities' => [
                'max_parallel_uploads' => 5,
                'supported_formats' => ['mp3', 'wav'],
                'normalization_enabled' => true,
                'ducking_enabled' => true
            ]
        ];
    }
    
    /**
     * Normalización de audio con LUFS target
     */
    private function normalizeAudio($inputFile, $type) {
        $targetLUFS = $this->volumeProfiles[$type]['normalization_target'] ?? -16;
        
        // Usar audio-processor.php existente
        if ($this->audioProcessor) {
            return $this->audioProcessor->normalize($inputFile, $targetLUFS);
        }
        
        // Fallback: retornar archivo sin procesar
        return $inputFile;
    }
    
    /**
     * Cargar servicios existentes (proxy pattern)
     */
    private function loadExistingServices() {
        // Cargar servicios pero no instanciarlos hasta que se necesiten (lazy loading)
        $basePath = dirname(__DIR__);
        
        // TTS Service
        if (file_exists($basePath . '/generate.php')) {
            $this->ttsService = new \stdClass();
            $this->ttsService->generate = function($text, $voice, $settings) use ($basePath) {
                // Llamar a generate.php existente
                $_POST = [
                    'text' => $text,
                    'voice' => $voice,
                    'voice_settings' => $settings
                ];
                
                ob_start();
                include $basePath . '/generate.php';
                $response = ob_get_clean();
                
                $result = json_decode($response, true);
                return $result['file_path'] ?? null;
            };
        }
        
        // Jingle Service
        if (file_exists($basePath . '/jingle-service.php')) {
            require_once $basePath . '/jingle-service.php';
            // Usar la clase existente si está disponible
        }
        
        // Audio Processor
        if (file_exists($basePath . '/services/audio-processor.php')) {
            require_once $basePath . '/services/audio-processor.php';
        }
    }
    
    /**
     * Cargar configuración de tiendas
     */
    private function loadStoreConfiguration() {
        $configFile = dirname(__DIR__) . '/data/stores-config.json';
        
        // Si no existe, crear configuración inicial
        if (!file_exists($configFile)) {
            $this->stores = [
                'main' => [
                    'id' => 'main',
                    'name' => 'Casa Costanera Principal',
                    'enabled' => true,
                    'api_url' => 'http://51.222.25.222',
                    'api_key' => 'c3802cba5b5e61e8:fed31be9adb82ca57f1cf482d170851f',
                    'station_id' => 1
                ]
            ];
            
            // Guardar configuración inicial
            file_put_contents($configFile, json_encode([
                'stores' => $this->stores
            ], JSON_PRETTY_PRINT));
        } else {
            $config = json_decode(file_get_contents($configFile), true);
            $this->stores = $config['stores'] ?? [];
        }
    }
    
    /**
     * Resolver targets (all, array, o store específica)
     */
    private function resolveTargets($targets) {
        if ($targets === 'all') {
            return $this->stores;
        }
        
        if (is_string($targets)) {
            return [$targets => $this->stores[$targets] ?? null];
        }
        
        if (is_array($targets)) {
            return array_intersect_key($this->stores, array_flip($targets));
        }
        
        return ['main' => $this->stores['main']];
    }
    
    /**
     * Aplicar perfil de volumen a parámetros
     */
    private function applyVolumeProfile($type, $params) {
        $profile = $this->volumeProfiles[$type] ?? [];
        
        // Mezclar configuración del perfil con parámetros
        foreach ($profile as $key => $value) {
            if (!isset($params[$key])) {
                $params[$key] = $value;
            }
        }
        
        return $params;
    }
    
    /**
     * Obtener duración del audio
     */
    private function getAudioDuration($file) {
        $cmd = sprintf('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s', 
            escapeshellarg($file));
        
        $duration = trim(shell_exec($cmd));
        return floatval($duration);
    }
    
    /**
     * Obtener LUFS del audio
     */
    private function getAudioLUFS($file) {
        $cmd = sprintf('ffmpeg -i %s -af "ebur128=peak=true" -f null - 2>&1 | grep "I:" | tail -1', 
            escapeshellarg($file));
        
        $output = shell_exec($cmd);
        if (preg_match('/I:\s*(-?\d+\.?\d*)\s*LUFS/', $output, $matches)) {
            return floatval($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Log de generación en base de datos
     */
    private function logGeneration($type, $params, $result) {
        try {
            $db = new \SQLite3(dirname(dirname(dirname(__DIR__))) . '/database/casa.db');
            
            $stmt = $db->prepare('
                INSERT INTO audio_generations (
                    type, text, voice, stores_count, 
                    success_count, created_at
                ) VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->bindValue(1, $type);
            $stmt->bindValue(2, $params['text'] ?? '');
            $stmt->bindValue(3, $params['voice'] ?? '');
            $stmt->bindValue(4, $result['total'] ?? 1);
            $stmt->bindValue(5, $result['successful'] ?? 1);
            $stmt->bindValue(6, date('Y-m-d H:i:s'));
            
            $stmt->execute();
            $db->close();
        } catch (\Exception $e) {
            // Log silencioso
            error_log("AudioManager log error: " . $e->getMessage());
        }
    }
    
    /**
     * Log de errores
     */
    private function logError(\Exception $e) {
        $logFile = dirname(__DIR__) . '/logs/audio-manager-' . date('Y-m-d') . '.log';
        
        $log = sprintf("[%s] ERROR: %s\nTrace: %s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getTraceAsString()
        );
        
        error_log($log, 3, $logFile);
    }
    
    /**
     * Guardar perfiles de volumen
     */
    private function saveVolumeProfiles() {
        $configFile = dirname(__DIR__) . '/data/volume-profiles.json';
        
        file_put_contents($configFile, json_encode([
            'profiles' => $this->volumeProfiles,
            'updated_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));
    }
    
    /**
     * Cargar perfiles de volumen desde archivo
     */
    private function loadVolumeProfiles() {
        $configFile = dirname(__DIR__) . '/data/volume-profiles.json';
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if (isset($config['profiles'])) {
                // Mezclar con defaults
                foreach ($config['profiles'] as $type => $profile) {
                    if (isset($this->volumeProfiles[$type])) {
                        $this->volumeProfiles[$type] = array_merge(
                            $this->volumeProfiles[$type],
                            $profile
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Ejecutar uploads paralelos con cURL multi
     */
    private function executeParallelUploads($uploads) {
        $results = [];
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        
        // Inicializar handles
        foreach ($uploads as $storeId => $upload) {
            $ch = curl_init();
            
            // Configurar cURL para AzuraCast
            $store = $upload['store'];
            $url = $store['api_url'] . '/api/station/' . $store['station_id'] . '/files';
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $store['api_key'],
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'path' => 'Grabaciones/' . basename($upload['file']),
                    'file' => base64_encode(file_get_contents($upload['file']))
                ]),
                CURLOPT_TIMEOUT => 30
            ]);
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$storeId] = $ch;
        }
        
        // Ejecutar requests
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            if ($running) {
                curl_multi_select($multiHandle);
            }
        } while ($running > 0);
        
        // Recolectar resultados
        foreach ($curlHandles as $storeId => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            $results[$storeId] = [
                'success' => $httpCode === 200 || $httpCode === 201,
                'http_code' => $httpCode,
                'response' => json_decode($response, true),
                'error' => curl_error($ch)
            ];
            
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($multiHandle);
        
        return $results;
    }
    
    /**
     * Obtener prioridad según tipo de audio
     */
    private function getPriorityForType($type) {
        $priorities = [
            'automatic' => 1,  // Alta prioridad
            'jingle' => 2,     // Media prioridad
            'tts' => 3         // Prioridad normal
        ];
        
        return $priorities[$type] ?? 3;
    }
}