<?php
/**
 * API de prueba para el sistema de skip automático
 * Endpoint temporal para testing
 */

require_once 'config.php';
require_once 'services/radio-service.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'interrupt_with_skip':
            // Probar interrupción con skip automático
            $filename = $input['filename'] ?? '';
            
            if (empty($filename)) {
                throw new Exception('Filename requerido');
            }
            
            // Intentar obtener duración del archivo
            $possiblePaths = [
                "/var/azuracast/stations/test/media/Grabaciones/" . $filename,
                "/var/www/casa/src/api/temp/" . $filename,
                TEMP_DIR . '/' . $filename
            ];
            
            $duration = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $duration = getAudioDuration($path);
                    logMessage("Archivo encontrado en: $path, duración: $duration segundos");
                    break;
                }
            }
            
            if ($duration === null) {
                logMessage("Archivo no encontrado localmente, usando duración default");
                $duration = 15;
            }
            
            // Ejecutar interrupción con skip
            $success = interruptRadioWithSkip($filename, $duration, true);
            
            if ($success) {
                $skipIn = ceil($duration) + 2;
                echo json_encode([
                    'success' => true,
                    'message' => 'Interrupción con skip programado',
                    'filename' => $filename,
                    'duration' => round($duration, 2),
                    'skip_in' => $skipIn
                ]);
            } else {
                throw new Exception('Error en interrupción');
            }
            break;
            
        case 'interrupt_no_skip':
            // Probar interrupción normal sin skip
            $filename = $input['filename'] ?? '';
            
            if (empty($filename)) {
                throw new Exception('Filename requerido');
            }
            
            // Usar la función con skip desactivado
            $success = interruptRadioWithSkip($filename, null, false);
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Interrupción sin skip ejecutada',
                    'filename' => $filename
                ]);
            } else {
                throw new Exception('Error en interrupción');
            }
            break;
            
        case 'skip_now':
            // Ejecutar skip inmediato
            $success = skipSongNow();
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Skip ejecutado inmediatamente'
                ]);
            } else {
                throw new Exception('Error ejecutando skip');
            }
            break;
            
        case 'test_duration':
            // Probar obtención de duración
            $filename = $input['filename'] ?? '';
            $path = TEMP_DIR . '/' . $filename;
            
            if (file_exists($path)) {
                $duration = getAudioDuration($path);
                echo json_encode([
                    'success' => true,
                    'filename' => $filename,
                    'duration' => $duration,
                    'path' => $path
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Archivo no encontrado',
                    'path' => $path
                ]);
            }
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}