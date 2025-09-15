<?php
/**
 * Save AI Suggestions Service
 * Servicio separado para guardar sugerencias desde claude-service
 * USA UNA BD SEPARADA - NO AFECTA PRODUCCIÓN
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Only POST method allowed']));
}

// IMPORTANTE: Base de datos SEPARADA de producción
$dbPath = __DIR__ . '/../../database/ai_suggestions.db';

if (!file_exists($dbPath)) {
    // Si no existe la BD, la creamos
    try {
        $db = new SQLite3($dbPath);
        $db->exec("CREATE TABLE IF NOT EXISTS ai_suggestions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            context TEXT NOT NULL,
            tone VARCHAR(50),
            duration INTEGER,
            keywords TEXT,
            suggestion_text TEXT NOT NULL,
            category VARCHAR(50),
            used BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_suggestions_created ON ai_suggestions(created_at DESC)");
        $db->close();
        chmod($dbPath, 0666);
    } catch (Exception $e) {
        die(json_encode(['error' => 'Could not create database: ' . $e->getMessage()]));
    }
}

try {
    $db = new SQLite3($dbPath);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['suggestions']) || !is_array($input['suggestions'])) {
        die(json_encode(['error' => 'Invalid input']));
    }
    
    $stmt = $db->prepare("
        INSERT INTO ai_suggestions 
        (context, tone, duration, keywords, suggestion_text, category, used)
        VALUES (:context, :tone, :duration, :keywords, :suggestion_text, :category, 0)
    ");
    
    $saved = 0;
    foreach ($input['suggestions'] as $suggestion) {
        if (is_string($suggestion)) {
            // Si es solo el texto
            $stmt->bindValue(':context', $input['context'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':tone', $input['tone'] ?? 'profesional', SQLITE3_TEXT);
            $stmt->bindValue(':duration', $input['duration'] ?? 30, SQLITE3_INTEGER);
            $stmt->bindValue(':keywords', isset($input['keywords']) ? json_encode($input['keywords']) : '', SQLITE3_TEXT);
            $stmt->bindValue(':suggestion_text', $suggestion, SQLITE3_TEXT);
            $stmt->bindValue(':category', $input['category'] ?? 'general', SQLITE3_TEXT);
        } else {
            // Si es un objeto con propiedades
            $stmt->bindValue(':context', $suggestion['context'] ?? $input['context'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':tone', $suggestion['tone'] ?? $input['tone'] ?? 'profesional', SQLITE3_TEXT);
            $stmt->bindValue(':duration', $suggestion['duration'] ?? $input['duration'] ?? 30, SQLITE3_INTEGER);
            $stmt->bindValue(':keywords', isset($suggestion['keywords']) ? json_encode($suggestion['keywords']) : '', SQLITE3_TEXT);
            $stmt->bindValue(':suggestion_text', $suggestion['text'] ?? $suggestion['suggestion_text'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':category', $suggestion['category'] ?? $input['category'] ?? 'general', SQLITE3_TEXT);
        }
        
        if ($stmt->execute()) {
            $saved++;
        }
        $stmt->reset();
    }
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'saved' => $saved,
        'message' => "$saved suggestions saved successfully"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error saving suggestions: ' . $e->getMessage()
    ]);
}
?>