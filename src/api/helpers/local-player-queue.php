<?php
/**
 * Helper para agregar TTS a la cola del Player Local
 * Se puede incluir en generate.php sin afectar el flujo actual
 */

/**
 * Agrega un TTS a la cola del player local
 * 
 * @param array $data Datos del TTS
 * @return bool True si se agregó correctamente
 */
function addToLocalPlayerQueue($data) {
    try {
        // Directorio de cola
        $queueDir = __DIR__ . '/../../../database/local-player-queue/';
        
        // Crear directorio si no existe
        if (!file_exists($queueDir)) {
            mkdir($queueDir, 0777, true);
        }
        
        // Generar ID único para el archivo
        $queueId = date('Ymd-His') . '_' . uniqid();
        $queueFile = $queueDir . $queueId . '.json';
        
        // Preparar datos para guardar
        $queueData = [
            'id' => $queueId,
            'text' => $data['text'] ?? '',
            'audio_path' => $data['audio_path'] ?? '',
            'category' => $data['category'] ?? 'sin_categoria',
            'type' => $data['type'] ?? 'announcement',
            'priority' => $data['priority'] ?? 'normal',
            'duration' => $data['duration'] ?? null,
            'voice_name' => $data['voice_name'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'destination' => $data['destination'] ?? 'local_player'
        ];
        
        // Guardar en archivo JSON
        file_put_contents($queueFile, json_encode($queueData, JSON_PRETTY_PRINT));
        
        // Log
        $logMessage = "Added to local player queue: $queueId (" . $data['text'] . ")";
        error_log($logMessage);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error adding to local player queue: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si hay TTS pendientes en la cola local
 * 
 * @return int Número de TTS pendientes
 */
function countLocalPlayerQueue() {
    $queueDir = __DIR__ . '/../../../database/local-player-queue/';
    
    if (!file_exists($queueDir)) {
        return 0;
    }
    
    $files = glob($queueDir . '*.json');
    return count($files);
}

/**
 * Limpia archivos procesados antiguos (más de 24 horas)
 */
function cleanupLocalPlayerProcessed() {
    $processedDir = __DIR__ . '/../../../database/local-player-processed/';
    
    if (!file_exists($processedDir)) {
        return;
    }
    
    $files = glob($processedDir . '*.json');
    $now = time();
    $deleted = 0;
    
    foreach ($files as $file) {
        if ($now - filemtime($file) > 86400) { // 24 horas
            unlink($file);
            $deleted++;
        }
    }
    
    if ($deleted > 0) {
        error_log("Cleaned up $deleted old processed files from local player queue");
    }
}
?>
