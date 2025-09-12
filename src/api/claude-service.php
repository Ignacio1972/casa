<?php
/**
 * Claude API Service - Casa Costanera
 * Servicio para generación de anuncios con IA
 */

require_once 'config.php';

class ClaudeService {
    private $apiKey;
    private $model;
    private $maxTokens;
    private $logFile;
    
    public function __construct() {
        $this->apiKey = getenv('CLAUDE_API_KEY') ?: '';
        $this->model = getenv('CLAUDE_MODEL') ?: 'claude-3-haiku-20240307';
        $this->maxTokens = (int)(getenv('CLAUDE_MAX_TOKENS') ?: 500);
        $this->logFile = __DIR__ . '/logs/claude-' . date('Y-m-d') . '.log';
    }
    
    /**
     * Obtener modelos disponibles
     */
    public function getAvailableModels() {
        return [
            'claude-3-haiku-20240307' => [
                'name' => 'Claude 3 Haiku',
                'description' => 'Más rápido y económico',
                'cost_input' => 0.00025,  // por 1K tokens
                'cost_output' => 0.00125,
                'speed' => 'fast',
                'quality' => 'good'
            ],
            'claude-3-sonnet-20240229' => [
                'name' => 'Claude 3 Sonnet',
                'description' => 'Balance perfecto calidad/velocidad',
                'cost_input' => 0.003,
                'cost_output' => 0.015,
                'speed' => 'medium',
                'quality' => 'excellent'
            ],
            'claude-3-opus-20240229' => [
                'name' => 'Claude 3 Opus',
                'description' => 'Máxima calidad y creatividad',
                'cost_input' => 0.015,
                'cost_output' => 0.075,
                'speed' => 'slow',
                'quality' => 'best'
            ]
        ];
    }
    
    /**
     * Log de mensajes
     */
    private function log($message, $level = 'INFO') {
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Obtener prompt del sistema según categoría y contexto del cliente
     */
    private function getSystemPrompt($category = 'general', $clientContext = null) {
        // Contexto base o personalizado del cliente
        if ($clientContext && !empty($clientContext)) {
            $basePrompt = $clientContext . " ";
        } else {
            // Intentar obtener el cliente activo del sistema
            $clientsFile = __DIR__ . '/data/clients-config.json';
            if (file_exists($clientsFile)) {
                $config = json_decode(file_get_contents($clientsFile), true);
                $activeClientId = $config['active_client'] ?? 'casa_costanera';
                if (isset($config['clients'][$activeClientId])) {
                    $basePrompt = $config['clients'][$activeClientId]['context'] . " ";
                } else {
                    $basePrompt = "Eres un experto creando anuncios para Casa Costanera, un moderno centro comercial en Chile. ";
                }
            } else {
                $basePrompt = "Eres un experto creando anuncios para Casa Costanera, un moderno centro comercial en Chile. ";
            }
        }
        
        $basePrompt .= "Genera anuncios cortos (máximo 100 palabras), claros y atractivos en español chileno. ";
        $basePrompt .= "Usa un tono amigable y profesional. Evita usar emojis o caracteres especiales. ";
        
        $categoryPrompts = [
            'ofertas' => "Enfócate en el ahorro, descuentos y beneficios. Crea urgencia y emoción por la oferta.",
            'eventos' => "Destaca la experiencia única, la diversión y la importancia de asistir. Menciona fecha y hora si es relevante.",
            'informacion' => "Sé claro, directo y útil. Proporciona la información esencial de manera concisa.",
            'servicios' => "Resalta la calidad, conveniencia y beneficios del servicio. Invita a la acción.",
            'horarios' => "Comunica claramente los horarios, sé específico y menciona cualquier cambio importante.",
            'emergencias' => "Sé directo, claro y tranquilizador. Proporciona instrucciones específicas si es necesario.",
            'general' => "Mantén un tono versátil que se adapte a diferentes tipos de mensajes."
        ];
        
        $categoryInstruction = $categoryPrompts[$category] ?? $categoryPrompts['general'];
        
        return $basePrompt . $categoryInstruction;
    }
    
    /**
     * Construir prompt del usuario con contexto
     */
    private function buildUserPrompt($params) {
        $prompt = "Genera 3 opciones diferentes de anuncios para lo siguiente:\n\n";
        
        if (!empty($params['context'])) {
            $prompt .= "Contexto: " . $params['context'] . "\n";
        }
        
        if (!empty($params['keywords'])) {
            $prompt .= "Palabras clave a incluir: " . implode(', ', $params['keywords']) . "\n";
        }
        
        if (!empty($params['tone'])) {
            $prompt .= "Tono deseado: " . $params['tone'] . "\n";
        }
        
        if (!empty($params['duration'])) {
            $prompt .= "Duración aproximada al leer: " . $params['duration'] . " segundos\n";
        }
        
        $prompt .= "\nFormato de respuesta: Proporciona exactamente 3 opciones numeradas, ";
        $prompt .= "cada una en un párrafo separado. No incluyas títulos ni explicaciones adicionales.";
        
        return $prompt;
    }
    
    /**
     * Llamar a la API de Claude
     */
    public function generateAnnouncements($params) {
        try {
            $this->log("Iniciando generación con parámetros: " . json_encode($params));
            
            // Determinar modelo a usar
            $modelToUse = $params['model'] ?? $this->model;
            
            // Validar que el modelo existe
            $availableModels = $this->getAvailableModels();
            if (!isset($availableModels[$modelToUse])) {
                $this->log("Modelo no válido: $modelToUse, usando default: " . $this->model);
                $modelToUse = $this->model;
            }
            
            $this->log("Usando modelo: $modelToUse");
            
            // Obtener contexto del cliente si existe
            $systemPrompt = $this->getSystemPrompt($params['category'] ?? 'general', $params['client_context'] ?? null);
            $userPrompt = $this->buildUserPrompt($params);
            
            $requestData = [
                'model' => $modelToUse,
                'max_tokens' => $this->maxTokens,
                'temperature' => $params['temperature'] ?? 0.8,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ];
            
            // Hacer la llamada a la API
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("Error en cURL: " . $error);
            }
            
            if ($httpCode !== 200) {
                $errorDetail = json_decode($response, true);
                $errorMessage = $errorDetail['error']['message'] ?? "Error desconocido";
                $this->log("Error HTTP $httpCode: $errorMessage - Response: $response", 'ERROR');
                
                if ($httpCode === 404) {
                    throw new Exception("Modelo no disponible. Por favor usa Haiku.");
                } else {
                    throw new Exception("Error en API de Claude: $errorMessage");
                }
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['content'][0]['text'])) {
                throw new Exception("Respuesta inesperada de Claude");
            }
            
            // Procesar y formatear la respuesta
            $suggestions = $this->parseResponse($data['content'][0]['text']);
            
            // Guardar en base de datos para métricas
            $this->saveMetrics($params, $suggestions);
            
            $this->log("Generación exitosa: " . count($suggestions) . " sugerencias");
            
            return [
                'success' => true,
                'suggestions' => $suggestions,
                'model' => $this->model,
                'tokens_used' => $data['usage']['output_tokens'] ?? null
            ];
            
        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'suggestions' => []
            ];
        }
    }
    
    /**
     * Parsear la respuesta de Claude en sugerencias individuales
     */
    private function parseResponse($text) {
        $suggestions = [];
        
        // Dividir por números (1., 2., 3.) o por doble salto de línea
        $patterns = [
            '/\d+\.\s*(.+?)(?=\d+\.|$)/s',  // Numeradas con punto
            '/\d+\)\s*(.+?)(?=\d+\)|$)/s',   // Numeradas con paréntesis
            '/\n\n+(.+?)(?=\n\n+|$)/s'       // Separadas por doble salto
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleaned = trim($match);
                    if (!empty($cleaned) && strlen($cleaned) > 20) {
                        $suggestions[] = [
                            'id' => uniqid('sug_'),
                            'text' => $cleaned,
                            'char_count' => strlen($cleaned),
                            'word_count' => str_word_count($cleaned),
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                    }
                }
                if (count($suggestions) >= 3) break;
            }
        }
        
        // Si no encontramos 3 sugerencias estructuradas, dividir por párrafos
        if (count($suggestions) < 3) {
            $paragraphs = array_filter(explode("\n", $text), 'trim');
            foreach ($paragraphs as $paragraph) {
                if (strlen($paragraph) > 20) {
                    $suggestions[] = [
                        'id' => uniqid('sug_'),
                        'text' => trim($paragraph),
                        'char_count' => strlen(trim($paragraph)),
                        'word_count' => str_word_count(trim($paragraph)),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
                if (count($suggestions) >= 3) break;
            }
        }
        
        // Limitar a 3 sugerencias
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Guardar métricas de uso
     */
    private function saveMetrics($params, $suggestions) {
        try {
            $db = new SQLite3('/var/www/casa/database/casa.db');
            
            // Insertar en la tabla statistics
            $stmt = $db->prepare("
                INSERT INTO statistics (date, metric_name, metric_value, metadata, created_at, client_id)
                VALUES (DATE('now'), 'claude_generations', 1, :metadata, DATETIME('now'), 'CASA')
                ON CONFLICT(date, metric_name, client_id) 
                DO UPDATE SET metric_value = metric_value + 1
            ");
            
            $metadata = json_encode([
                'category' => $params['category'] ?? 'general',
                'suggestions_count' => count($suggestions),
                'model' => $this->model
            ]);
            
            $stmt->bindValue(':metadata', $metadata, SQLITE3_TEXT);
            $stmt->execute();
            
            $db->close();
            
        } catch (Exception $e) {
            $this->log("Error guardando métricas: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Obtener estadísticas de uso
     */
    public function getUsageStats($days = 30) {
        try {
            $db = new SQLite3('/var/www/casa/database/casa.db');
            
            $query = "
                SELECT 
                    date,
                    metric_value as generations,
                    metadata
                FROM statistics 
                WHERE metric_name = 'claude_generations' 
                    AND date >= DATE('now', '-$days days')
                    AND client_id = 'CASA'
                ORDER BY date DESC
            ";
            
            $result = $db->query($query);
            $stats = [];
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $stats[] = $row;
            }
            
            $db->close();
            
            return $stats;
            
        } catch (Exception $e) {
            $this->log("Error obteniendo estadísticas: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
}

// Si se llama directamente como API
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $service = new ClaudeService();
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'generate':
                $response = $service->generateAnnouncements($input);
                break;
                
            case 'stats':
                $response = [
                    'success' => true,
                    'stats' => $service->getUsageStats($input['days'] ?? 30)
                ];
                break;
                
            default:
                $response = [
                    'success' => false,
                    'error' => 'Acción no válida'
                ];
        }
    } else {
        $response = [
            'success' => false,
            'error' => 'No se especificó una acción'
        ];
    }
    
    echo json_encode($response);
}
?>