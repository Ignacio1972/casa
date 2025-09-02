<?php
/**
 * API de Generación de Audio TTS - Casa Costanera
 * Sistema de Radio Automatizada
 */
require_once 'config.php';
require_once 'services/announcement-module/announcement-templates.php';
require_once 'services/announcement-module/announcement-generator.php';
require_once 'services/audio-processor.php';


// Función de logging
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/logs/tts-' . date('Y-m-d') . '.log';
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No se recibieron datos');
    }
    
    logMessage("Datos recibidos: " . json_encode($input));

      // ============= CÓDIGO DEL PLAYGROUND AQUÍ =============
    // Detectar si viene del playground
    $isPlayground = isset($input['source']) && $input['source'] === 'playground';
    
    if ($isPlayground) {
        // Logger especial para playground
        require_once __DIR__ . '/../playground/logger/tts-logger.php';
        $playgroundLogger = new TTSLogger('tts-playground', TTSLogger::LEVEL_DEBUG);
        $playgroundLogger->info('Request from playground', $input);
    }
    
    // Agregar action para listar voces
    if ($input["action"] === "list_voices") {
        // Sistema dinámico de voces desde archivo JSON
        $voicesFile = __DIR__ . '/data/voices-config.json';
        
        if (!file_exists($voicesFile)) {
            logMessage("ERROR: voices-config.json no encontrado");
            echo json_encode(['success' => false, 'error' => 'No voices configured']);
            exit;
        }
        
        $config = json_decode(file_get_contents($voicesFile), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("ERROR: voices-config.json mal formateado");
            echo json_encode(['success' => false, 'error' => 'Invalid voices configuration']);
            exit;
        }
        
        $activeVoices = [];
        
        // Solo retornar voces activas
        foreach ($config['voices'] as $key => $voice) {
            if (isset($voice['active']) && $voice['active'] === true) {
                $activeVoices[$key] = [
                    'id' => $voice['id'],
                    'label' => $voice['label'],
                    'gender' => $voice['gender']
                ];
            }
        }
        
        logMessage("Retornando " . count($activeVoices) . " voces activas");
        echo json_encode(['success' => true, 'voices' => $activeVoices]);
        exit;
    }
    // Lista de templates disponibles
    if ($input['action'] === 'list_templates') {
        $templates = AnnouncementTemplates::getAllTemplates();
        echo json_encode([
            'success' => true,
            'templates' => $templates
        ]);
        exit;
    }
    
    // Generar audio completo
    if ($input['action'] === 'generate_audio') {
        logMessage("Iniciando generación de audio");
        
        // Preparar opciones base - SOLO PARÁMETROS SOPORTADOS
        $generatorOptions = [];
        
        // Voice settings - SOLO los 4 parámetros válidos
        if (isset($input['voice_settings'])) {
            $generatorOptions['voice_settings'] = [
                'style' => $input['voice_settings']['style'] ?? 0.5,
                'stability' => $input['voice_settings']['stability'] ?? 0.75,
                'similarity_boost' => $input['voice_settings']['similarity_boost'] ?? 0.8,
                'use_speaker_boost' => $input['voice_settings']['use_speaker_boost'] ?? true
            ];
            logMessage("Voice settings recibidos: " . json_encode($generatorOptions['voice_settings']));
        }
        
        // Verificar si es template o texto directo
        if (!empty($input['template']) && !empty($input['template_category'])) {
            // Generar desde template
            logMessage("Generando desde template: {$input['template_category']}/{$input['template']}");
            
            $result = AnnouncementGenerator::generateFromTemplate(
                $input['template_category'],
                $input['template'],
                $input['template_variables'] ?? [],
                $input['voice'] ?? 'fernanda',
                $generatorOptions
            );
        } else if (!empty($input['text'])) {
            // Generar desde texto directo
            logMessage("Generando desde texto directo");
            
            $result = AnnouncementGenerator::generateSimple(
                $input['text'],
                $input['voice'] ?? 'fernanda',
                $generatorOptions
            );
        } else {
            throw new Exception('Debe proporcionar texto o seleccionar un template');
        }
        
        // Guardar archivo temporal LOCAL para preview
        $filename = 'tts' . date('YmdHis') . '.mp3';
        $filepath = UPLOAD_DIR . $filename;
        file_put_contents($filepath, $result['audio']);
        
        logMessage("Archivo temporal creado para preview: $filename");
        
        // ===== MANTENER TODA LA FUNCIONALIDAD DE AZURACAST =====
        require_once 'services/radio-service.php';
        require_once 'services/audio-processor.php';
        
        // Procesar audio (agregar silencios)
        $filepathCopy = copyFileForProcessing($filepath);
        $filepathWithSilence = addSilenceToAudio($filepathCopy);
        if ($filepathWithSilence === false) {
            $filepathWithSilence = $filepathCopy;
        }
        
        // Subir a AzuraCast
        $uploadResult = uploadFileToAzuraCast($filepathWithSilence, $filename);
        $actualFilename = $uploadResult['filename'];
        
        // Asignar a playlist
        assignFileToPlaylist($uploadResult['id']);
        
        // Limpiar archivos temporales DE PROCESAMIENTO (no el original)
        @unlink($filepathCopy);
        if ($filepathWithSilence !== $filepathCopy) {
            @unlink($filepathWithSilence);
        }
        
        logMessage("Audio generado y subido exitosamente: $actualFilename");

        // Guardar en base de datos para mensajes recientes
        try {
            $db = new PDO("sqlite:" . __DIR__ . "/../../database/casa.db");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $db->prepare("
                INSERT INTO audio_metadata 
                (filename, display_name, description, category, is_saved, saved_at, created_at, is_active) 
                VALUES (?, ?, ?, ?, 0, datetime('now'), datetime('now'), 1)
            ");
            
            // Usar el texto procesado del resultado
            $textUsed = $result['processed_text'] ?? $input['text'] ?? 'Mensaje generado';
            $words = explode(' ', $textUsed);
            $displayName = implode(' ', array_slice($words, 0, 5));
            if (count($words) > 5) $displayName .= '...'; // Primeros 100 caracteres
            $stmt->execute([
                $actualFilename,
                $displayName,
                $textUsed,
                $input['category'] ?? 'sin_categoria'
            ]);
            
            logMessage("Metadata guardada en BD para: $filename");
            logMessage("DB: Guardando con actualFilename=" . $actualFilename . " (original era " . $filename . ")");
        } catch (Exception $e) {
            logMessage("Error guardando metadata: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'filename' => $filename,           // Nombre local para preview
            'azuracast_filename' => $actualFilename,  // Nombre en AzuraCast
            'processed_text' => $result['processed_text']
        ]);
        exit;
    }
    
    // Enviar a radio - MANTENER INTACTO
    if ($input['action'] === 'send_to_radio') {
        logMessage("Procesando envío a radio");
        
        require_once 'services/radio-service.php';
        
        $filename = $input['filename'] ?? '';
        
        if (empty($filename)) {
            throw new Exception('No se especificó el archivo a enviar');
        }
        
        logMessage("Interrumpiendo radio con archivo: $filename");
        
        // Interrumpir la radio con el archivo
        $success = interruptRadio($filename);
        
        if ($success) {
            logMessage("Archivo enviado a radio exitosamente: $filename");
            echo json_encode([
                'success' => true,
                'message' => 'Anuncio enviado a la radio y reproduciéndose'
            ]);
        } else {
            throw new Exception('Error al interrumpir la radio');
        }
        exit;
    }
    
    // Si no es ninguna acción conocida
    throw new Exception('Acción no reconocida: ' . ($input['action'] ?? 'ninguna'));
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>