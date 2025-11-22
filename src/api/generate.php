<?php
/**
 * API de Generación de Audio TTS - Casa Costanera
 * Sistema de Radio Automatizada
 */
require_once 'config.php';
require_once 'services/announcement-module/announcement-templates.php';
require_once 'services/announcement-module/announcement-generator.php';
require_once 'services/audio-processor.php';
require_once __DIR__ . '/helpers/local-player-queue.php';


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
                    'gender' => $voice['gender'],
                    'is_default' => isset($voice['is_default']) ? $voice['is_default'] : false,
                    'order' => isset($voice['order']) ? $voice['order'] : 999
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
        
        // Cargar configuración TTS si existe
        $ttsConfigFile = __DIR__ . '/data/tts-config.json';
        $ttsConfig = null;
        if (file_exists($ttsConfigFile)) {
            $ttsConfig = json_decode(file_get_contents($ttsConfigFile), true);
            logMessage("Configuración TTS cargada desde archivo");
        }
        
        // Voice settings - Primero desde config TTS, luego desde request, luego defaults
        if (isset($input['voice_settings'])) {
            // Si vienen del request, usarlos
            $generatorOptions['voice_settings'] = [
                'style' => $input['voice_settings']['style'] ?? 0.5,
                'stability' => $input['voice_settings']['stability'] ?? 0.75,
                'similarity_boost' => $input['voice_settings']['similarity_boost'] ?? 0.8,
                'use_speaker_boost' => $input['voice_settings']['use_speaker_boost'] ?? true
            ];
            logMessage("Voice settings recibidos del request: " . json_encode($generatorOptions['voice_settings']));
        } elseif ($ttsConfig && isset($ttsConfig['voice_settings'])) {
            // Si no vienen del request pero hay config TTS, usar esos
            $generatorOptions['voice_settings'] = $ttsConfig['voice_settings'];
            logMessage("Voice settings desde configuración TTS: " . json_encode($generatorOptions['voice_settings']));
        } else {
            // Valores por defecto
            $generatorOptions['voice_settings'] = [
                'style' => 0.5,
                'stability' => 0.75,
                'similarity_boost' => 0.8,
                'use_speaker_boost' => true
            ];
            logMessage("Voice settings por defecto aplicados");
        }

        // NUEVO: Agregar output_volume desde la configuración TTS
        if ($ttsConfig && isset($ttsConfig['normalization']['output_volume'])) {
            $generatorOptions['output_volume'] = floatval($ttsConfig['normalization']['output_volume']);
            logMessage("Output volume desde configuración TTS: " . $generatorOptions['output_volume']);
        } else {
            // Valor por defecto (1.0 = sin cambio)
            $generatorOptions['output_volume'] = 1.0;
            logMessage("Output volume por defecto: 1.0");
        }
        
        // Leer configuración de API desde archivo
        $configFile = __DIR__ . '/data/api-config.json';
        if (file_exists($configFile)) {
            $apiConfig = json_decode(file_get_contents($configFile), true);
            if (isset($apiConfig['use_v3_api']) && $apiConfig['use_v3_api'] === true) {
                $generatorOptions['use_v3'] = true;
                logMessage("Usando ElevenLabs v3 (configurado desde playground)");
            }
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
        
        // Generar nombre descriptivo basado en el contenido
        $textUsed = $result['processed_text'] ?? $input['text'] ?? 'mensaje';
        $voiceUsed = $input['voice'] ?? 'voz';
        
        // Mapear ID de voz a nombre amigable si es necesario
        $voicesConfigFile = __DIR__ . '/data/voices-config.json';
        if (file_exists($voicesConfigFile)) {
            $voicesConfig = json_decode(file_get_contents($voicesConfigFile), true);
            if ($voicesConfig && isset($voicesConfig['voices'])) {
                // Buscar si el voice ID corresponde a alguna voz conocida
                foreach ($voicesConfig['voices'] as $voiceName => $voiceData) {
                    if ($voiceData['id'] === $voiceUsed) {
                        $voiceUsed = $voiceName;
                        break;
                    }
                }
            }
        }
        
        // Si el nombre de voz es muy largo (es un ID), acortarlo
        if (strlen($voiceUsed) > 15) {
            $voiceUsed = substr($voiceUsed, 0, 8);
        }
        
        // Crear slug del texto (primeras palabras)
        $words = explode(' ', $textUsed);
        $slug = implode('_', array_slice($words, 0, 3)); // Primeras 3 palabras
        // Limpiar caracteres especiales
        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug);
        $slug = strtolower(substr($slug, 0, 20)); // Máximo 20 caracteres
        
        // Formato: mensaje_slug_voz_YYYYMMDD_HHMMSS.mp3
        $timestamp = date('Ymd_His');
        $descriptiveFilename = 'mensaje_' . (!empty($slug) ? $slug . '_' : '') . $voiceUsed . '_' . $timestamp . '.mp3';
        
        // Guardar archivo temporal LOCAL para preview con nombre simple
        $tempFilename = 'temp_' . date('YmdHis') . '.mp3';
        $filepath = UPLOAD_DIR . $tempFilename;
        file_put_contents($filepath, $result['audio']);

        logMessage("Archivo temporal creado: $tempFilename, nombre descriptivo será: $descriptiveFilename");

        // ===== DETECTAR DESTINO: LOCAL PLAYER O AZURACAST =====
        // Si no se especifica destino, solo se guarda localmente (modo manual)
        $destination = $input['destination'] ?? null;

        if ($destination === 'local_player') {
            // ===== ENVIAR A PLAYER LOCAL =====
            logMessage("Destino: Player Local - Agregando a cola local");

            // Procesar audio (agregar silencios) solo si es necesario
            require_once 'services/audio-processor.php';
            $filepathCopy = copyFileForProcessing($filepath);
            $filepathWithSilence = addSilenceToAudio($filepathCopy);
            if ($filepathWithSilence === false) {
                $filepathWithSilence = $filepathCopy;
            }

            // Guardar con nombre descriptivo para el player local
            $localPlayerPath = UPLOAD_DIR . $descriptiveFilename;
            copy($filepathWithSilence, $localPlayerPath);

            // Agregar a la cola del player local
            $queueData = [
                'text' => $textUsed,
                'audio_path' => 'src/api/temp/' . $descriptiveFilename, // Ruta relativa
                'category' => $input['category'] ?? 'sin_categoria',
                'type' => 'announcement',
                'priority' => 'normal',
                'voice_name' => $voiceUsed,
                'destination' => 'local_player'
            ];

            $addedToQueue = addToLocalPlayerQueue($queueData);

            // Limpiar archivos temporales de procesamiento
            @unlink($filepathCopy);
            if ($filepathWithSilence !== $filepathCopy) {
                @unlink($filepathWithSilence);
            }

            $actualFilename = $descriptiveFilename;

            if ($addedToQueue) {
                logMessage("Audio agregado a cola del Player Local exitosamente: $actualFilename");
            } else {
                throw new Exception('Error al agregar a cola del Player Local');
            }

        } elseif ($destination === 'azuracast') {
            // ===== SUBIR A AZURACAST =====
            logMessage("Destino: AzuraCast - Subiendo a plataforma de radio");

            require_once 'services/radio-service.php';
            require_once 'services/audio-processor.php';

            // Procesar audio (agregar silencios)
            $filepathCopy = copyFileForProcessing($filepath);
            $filepathWithSilence = addSilenceToAudio($filepathCopy);
            if ($filepathWithSilence === false) {
                $filepathWithSilence = $filepathCopy;
            }

            // Subir a AzuraCast con nombre descriptivo
            $uploadResult = uploadFileToAzuraCast($filepathWithSilence, $descriptiveFilename);
            $actualFilename = $uploadResult['filename'];

            // Limpiar archivos temporales DE PROCESAMIENTO (no el original)
            @unlink($filepathCopy);
            if ($filepathWithSilence !== $filepathCopy) {
                @unlink($filepathWithSilence);
            }

            // Mantener una copia local con el nombre correcto para preview
            $localPreviewPath = UPLOAD_DIR . $actualFilename;
            copy($filepath, $localPreviewPath);

            logMessage("Audio generado y subido a AzuraCast exitosamente: $actualFilename");
        } else {
            // ===== MODO MANUAL: SOLO GUARDAR LOCALMENTE =====
            logMessage("Modo manual: Audio guardado solo localmente, sin envío automático");

            // Simplemente renombrar el archivo temporal con el nombre descriptivo
            $actualFilename = $descriptiveFilename;
            $finalPath = UPLOAD_DIR . $actualFilename;
            rename($filepath, $finalPath);

            logMessage("Archivo guardado localmente para envío manual: $actualFilename");
        }

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
            
            logMessage("Metadata guardada en BD para: $actualFilename");
            logMessage("DB: Guardando con actualFilename=" . $actualFilename . " (temp era " . $tempFilename . ")");
        } catch (Exception $e) {
            logMessage("Error guardando metadata: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'filename' => $actualFilename,     // Usar el nombre descriptivo real
            'azuracast_filename' => $actualFilename,  // Nombre en AzuraCast
            'processed_text' => $result['processed_text']
        ]);
        exit;
    }
    
    // Enviar a radio - MANTENER INTACTO
    if ($input['action'] === 'send_to_radio') {
        $destination = $input['destination'] ?? 'azuracast'; // Default: azuracast
        logMessage("Procesando envío - Destino: $destination");

        $filename = $input['filename'] ?? '';

        if (empty($filename)) {
            throw new Exception('No se especificó el archivo a enviar');
        }

        // Verificar si el archivo existe localmente
        $localPath = UPLOAD_DIR . $filename;
        if (!file_exists($localPath)) {
            logMessage("Archivo no encontrado en temp: $localPath");
            throw new Exception('Archivo no encontrado: ' . $filename);
        }

        // Redirigir según el destino
        if ($destination === 'local_player') {
            // ===== ENVIAR A PLAYER LOCAL =====
            logMessage("Agregando archivo a cola del Player Local: $filename");

            // Leer metadata del archivo de la BD si existe
            $textUsed = 'Mensaje de audio';
            $category = 'sin_categoria';
            $voiceName = 'Desconocida';

            try {
                $db = new PDO("sqlite:" . __DIR__ . "/../../database/casa.db");
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $db->prepare("SELECT description, category, voice_name FROM audio_metadata WHERE filename = ? LIMIT 1");
                $stmt->execute([$filename]);
                $metadata = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($metadata) {
                    $textUsed = $metadata['description'] ?? $textUsed;
                    $category = $metadata['category'] ?? $category;
                    $voiceName = $metadata['voice_name'] ?? $voiceName;
                }
            } catch (Exception $e) {
                logMessage("Error leyendo metadata: " . $e->getMessage());
            }

            // Agregar a la cola del player local
            $queueData = [
                'text' => $textUsed,
                'audio_path' => 'src/api/temp/' . $filename, // Ruta relativa
                'category' => $category,
                'type' => 'announcement',
                'priority' => 'normal',
                'voice_name' => $voiceName,
                'destination' => 'local_player'
            ];

            $addedToQueue = addToLocalPlayerQueue($queueData);

            if ($addedToQueue) {
                logMessage("Archivo agregado a cola del Player Local exitosamente: $filename");
                echo json_encode([
                    'success' => true,
                    'message' => 'Mensaje enviado al Player Local'
                ]);
            } else {
                throw new Exception('Error al agregar a cola del Player Local');
            }
        } else {
            // ===== ENVIAR A AZURACAST (DEFAULT) =====
            require_once 'services/radio-service.php';

            logMessage("Subiendo archivo a AzuraCast: $filename");

            // Subir archivo a AzuraCast primero
            $uploadResult = uploadFileToAzuraCast($localPath, $filename);
            $azuracastFilename = $uploadResult['filename'];

            logMessage("Archivo subido a AzuraCast como: $azuracastFilename");
            logMessage("Interrumpiendo radio con archivo: $azuracastFilename");

            // Interrumpir la radio con el archivo
            $success = interruptRadio($azuracastFilename);

            if ($success) {
                logMessage("Archivo enviado a radio exitosamente: $azuracastFilename");
                echo json_encode([
                    'success' => true,
                    'message' => 'Anuncio enviado a la radio y reproduciéndose'
                ]);
            } else {
                throw new Exception('Error al interrumpir la radio');
            }
        }
        exit;
    }

    // Enviar a Player Local
    if ($input['action'] === 'send_to_local_player') {
        logMessage("Procesando envío a Player Local");

        $filename = $input['filename'] ?? '';

        if (empty($filename)) {
            throw new Exception('No se especificó el archivo a enviar');
        }

        // Verificar si el archivo existe localmente
        $localPath = UPLOAD_DIR . $filename;
        if (!file_exists($localPath)) {
            logMessage("Archivo no encontrado en temp: $localPath");
            throw new Exception('Archivo no encontrado: ' . $filename);
        }

        logMessage("Agregando archivo a cola del Player Local: $filename");

        // Leer metadata del archivo de la BD si existe
        $textUsed = 'Mensaje de audio';
        $category = 'sin_categoria';
        $voiceName = 'Desconocida';

        try {
            $db = new PDO("sqlite:" . __DIR__ . "/../../database/casa.db");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("SELECT description, category, voice_name FROM audio_metadata WHERE filename = ? LIMIT 1");
            $stmt->execute([$filename]);
            $metadata = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($metadata) {
                $textUsed = $metadata['description'] ?? $textUsed;
                $category = $metadata['category'] ?? $category;
                $voiceName = $metadata['voice_name'] ?? $voiceName;
            }
        } catch (Exception $e) {
            logMessage("No se pudo leer metadata de BD: " . $e->getMessage());
        }

        // Agregar a la cola del player local
        $queueData = [
            'text' => $textUsed,
            'audio_path' => 'src/api/temp/' . $filename,
            'category' => $category,
            'type' => 'announcement',
            'priority' => 'high', // Alta prioridad para envíos manuales
            'voice_name' => $voiceName,
            'destination' => 'local_player'
        ];

        $addedToQueue = addToLocalPlayerQueue($queueData);

        if ($addedToQueue) {
            logMessage("Archivo agregado a cola del Player Local exitosamente: $filename");
            echo json_encode([
                'success' => true,
                'message' => 'Anuncio agregado a la cola del Player Local',
                'queue_count' => countLocalPlayerQueue()
            ]);
        } else {
            throw new Exception('Error al agregar a cola del Player Local');
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