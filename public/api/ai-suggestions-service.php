<?php
/**
 * AI Suggestions Service
 * Servicio para guardar y recuperar sugerencias de IA
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuración de la base de datos SEPARADA para sugerencias
// IMPORTANTE: Usa una BD diferente a la de producción
$dbPath = realpath(__DIR__ . '/../../database/ai_suggestions.db');
if (!file_exists($dbPath)) {
    die(json_encode(['error' => 'AI Suggestions database not found']));
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Procesar según método HTTP
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Obtener sugerencias recientes
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        try {
            $stmt = $db->prepare("
                SELECT id, context, tone, duration, keywords, 
                       suggestion_text, category, used, 
                       created_at, updated_at
                FROM ai_suggestions
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar total de sugerencias
            $countStmt = $db->query("SELECT COUNT(*) as total FROM ai_suggestions");
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error fetching suggestions: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'POST':
        // Guardar nueva sugerencia o múltiples sugerencias
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            exit;
        }
        
        // Verificar si es una sugerencia única o múltiples
        $suggestions = isset($input['suggestions']) ? $input['suggestions'] : [$input];
        $savedSuggestions = [];
        
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_suggestions 
                (context, tone, duration, keywords, suggestion_text, category, used)
                VALUES (:context, :tone, :duration, :keywords, :suggestion_text, :category, :used)
            ");
            
            foreach ($suggestions as $suggestion) {
                // Validar campos requeridos
                if (empty($suggestion['suggestion_text'])) {
                    continue;
                }
                
                $stmt->execute([
                    ':context' => $suggestion['context'] ?? '',
                    ':tone' => $suggestion['tone'] ?? 'profesional',
                    ':duration' => $suggestion['duration'] ?? 30,
                    ':keywords' => is_array($suggestion['keywords']) ? 
                                   json_encode($suggestion['keywords']) : 
                                   ($suggestion['keywords'] ?? ''),
                    ':suggestion_text' => $suggestion['suggestion_text'],
                    ':category' => $suggestion['category'] ?? 'general',
                    ':used' => $suggestion['used'] ?? 0
                ]);
                
                $savedSuggestions[] = [
                    'id' => $db->lastInsertId(),
                    'suggestion_text' => $suggestion['suggestion_text']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Suggestions saved successfully',
                'saved' => $savedSuggestions
            ]);
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error saving suggestions: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'PUT':
        // Marcar sugerencia como usada
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID required']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                UPDATE ai_suggestions 
                SET used = 1, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $input['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Suggestion marked as used'
            ]);
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error updating suggestion: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>