<?php
/**
 * Servicio Web para Gestión de Música
 * Backend para la interfaz web del gestor de música
 */

// Debug mode - log all errors
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar en pantalla
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/casa/src/api/logs/music-manager-' . date('Y-m-d') . '.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('MUSIC_DIR', dirname(dirname(__DIR__)) . '/public/audio/music/');

// Función de logging
function logMessage($message) {
    error_log("[MusicManager] " . $message);
}

// Función para obtener lista de canciones
function getMusicList() {
    $files = array_merge(
        glob(MUSIC_DIR . '*.mp3') ?: [],
        glob(MUSIC_DIR . '*.wav') ?: []
    );
    $musicList = [];

    foreach ($files as $file) {
        $name = basename($file);
        $size = filesize($file);
        $sizeInMB = round($size / 1024 / 1024, 2);

        // Obtener duración usando ffmpeg
        $duration = null;
        $cmd = sprintf('ffprobe -v error -show_entries format=duration -of csv=p=0 "%s" 2>&1', $file);
        $durationOutput = trim(shell_exec($cmd));
        if (is_numeric($durationOutput)) {
            $duration = round(floatval($durationOutput), 1);
        }

        $musicList[] = [
            'name' => $name,
            'size' => $size,
            'sizeFormatted' => $sizeInMB . ' MB',
            'duration' => $duration,
            'path' => $file,
            'lastModified' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }

    // Ordenar por nombre
    usort($musicList, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $musicList;
}

// Función para subir archivo
function uploadMusic($file) {
    logMessage("Iniciando upload: " . json_encode([
        'name' => $file['name'] ?? 'N/A',
        'type' => $file['type'] ?? 'N/A',
        'size' => $file['size'] ?? 0,
        'tmp_name' => isset($file['tmp_name']) ? 'existe' : 'no existe'
    ]));

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('No se recibió ningún archivo válido');
    }

    // Verificar extensión primero (MP3 o WAV)
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    logMessage("Extensión detectada: $extension");

    $allowedExtensions = ['mp3', 'wav'];
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('El archivo debe tener extensión .mp3 o .wav');
    }

    // Verificar tipo MIME (más permisivo)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    logMessage("MIME type detectado: $mimeType");

    // Verificar magic bytes
    $handle = fopen($file['tmp_name'], 'rb');
    $header = fread($handle, 12);
    fclose($handle);
    logMessage("Magic bytes: " . bin2hex(substr($header, 0, 4)));

    $isValid = false;

    if ($extension === 'mp3') {
        // Verificar MP3 por MIME type
        if (in_array($mimeType, ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/x-mp3'])) {
            $isValid = true;
            logMessage("MP3 validado por MIME type");
        }

        // Verificar MP3 por magic bytes (ID3v2: "ID3" o MPEG sync: 0xFF 0xFB/0xFA/0xF3/0xF2)
        $firstThree = substr($header, 0, 3);
        if ($firstThree === 'ID3' || (ord($header[0]) === 0xFF && (ord($header[1]) & 0xE0) === 0xE0)) {
            $isValid = true;
            logMessage("MP3 validado por magic bytes");
        }

        // Si el MIME es genérico, validar con ffprobe
        if (!$isValid && $mimeType === 'application/octet-stream') {
            logMessage("Intentando validar MP3 con ffprobe...");
            $cmd = sprintf('ffprobe -v error -select_streams a:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 "%s" 2>&1', $file['tmp_name']);
            $codec = trim(shell_exec($cmd));
            logMessage("Codec detectado por ffprobe: $codec");
            if (in_array($codec, ['mp3', 'mp3float'])) {
                $isValid = true;
                logMessage("MP3 detectado mediante ffprobe");
            }
        }

        if (!$isValid) {
            logMessage("Archivo rechazado - no es MP3 válido");
            throw new Exception('El archivo debe ser un MP3 válido (tipo detectado: ' . $mimeType . ')');
        }
    }
    elseif ($extension === 'wav') {
        // Verificar WAV por MIME type
        if (in_array($mimeType, ['audio/wav', 'audio/wave', 'audio/x-wav', 'audio/vnd.wave'])) {
            $isValid = true;
            logMessage("WAV validado por MIME type");
        }

        // Verificar WAV por magic bytes (RIFF header)
        if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WAVE') {
            $isValid = true;
            logMessage("WAV validado por magic bytes (RIFF/WAVE)");
        }

        // Si el MIME es genérico, validar con ffprobe
        if (!$isValid && in_array($mimeType, ['application/octet-stream', 'audio/x-wav'])) {
            logMessage("Intentando validar WAV con ffprobe...");
            $cmd = sprintf('ffprobe -v error -select_streams a:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 "%s" 2>&1', $file['tmp_name']);
            $codec = trim(shell_exec($cmd));
            logMessage("Codec detectado por ffprobe: $codec");
            if (in_array($codec, ['pcm_s16le', 'pcm_s24le', 'pcm_s32le', 'pcm_u8', 'pcm_f32le', 'pcm_f64le'])) {
                $isValid = true;
                logMessage("WAV detectado mediante ffprobe");
            }
        }

        if (!$isValid) {
            logMessage("Archivo rechazado - no es WAV válido");
            throw new Exception('El archivo debe ser un WAV válido (tipo detectado: ' . $mimeType . ')');
        }
    }

    logMessage("Validación de audio exitosa ($extension)");

    // Verificar tamaño (máximo 50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        throw new Exception('El archivo es demasiado grande (máximo 50MB)');
    }

    // Generar nombre seguro
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $destPath = MUSIC_DIR . $filename;

    // Verificar si ya existe
    if (file_exists($destPath)) {
        throw new Exception('Ya existe un archivo con ese nombre');
    }

    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Error al guardar el archivo');
    }

    // Establecer permisos
    chmod($destPath, 0644);

    // Verificar con ffmpeg que es válido
    exec("ffmpeg -i \"$destPath\" -f null - 2>&1", $output, $returnVar);
    if ($returnVar !== 0) {
        // Si hay problema, eliminar el archivo
        unlink($destPath);
        throw new Exception('El archivo MP3 no es válido o está corrupto');
    }

    logMessage("Archivo subido exitosamente: $filename");

    return [
        'name' => $filename,
        'size' => $file['size'],
        'path' => $destPath
    ];
}

// Función para eliminar archivo
function deleteMusic($filename) {
    // Validar nombre de archivo
    if (empty($filename) || preg_match('/[\/\\\\]/', $filename)) {
        throw new Exception('Nombre de archivo inválido');
    }

    $filePath = MUSIC_DIR . $filename;

    if (!file_exists($filePath)) {
        throw new Exception('El archivo no existe');
    }

    // Verificar que es un archivo MP3 en el directorio correcto
    if (dirname(realpath($filePath)) !== realpath(MUSIC_DIR)) {
        throw new Exception('Ruta de archivo inválida');
    }

    if (!unlink($filePath)) {
        throw new Exception('No se pudo eliminar el archivo');
    }

    logMessage("Archivo eliminado: $filename");

    return true;
}

// Función para reiniciar servicios (simplificada - solo limpia caché)
function restartServices() {
    // Limpiar todos los cachés de PHP
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    clearstatcache(true);

    logMessage("Caché limpiado");

    // Nota: Para evitar errores 502, no reiniciamos PHP-FPM desde la web
    // Los cambios se detectan automáticamente al limpiar el caché

    return true;
}

// Función para validar una canción
function validateMusic($filename) {
    $filePath = MUSIC_DIR . $filename;

    if (!file_exists($filePath)) {
        return ['valid' => false, 'error' => 'Archivo no encontrado'];
    }

    // Verificar con ffmpeg
    exec("ffmpeg -i \"$filePath\" -f null - 2>&1", $output, $returnVar);

    if ($returnVar === 0) {
        // Obtener información adicional
        $cmd = sprintf('ffprobe -v quiet -print_format json -show_format -show_streams "%s" 2>&1', $filePath);
        $info = shell_exec($cmd);
        $jsonInfo = json_decode($info, true);

        return [
            'valid' => true,
            'info' => [
                'duration' => $jsonInfo['format']['duration'] ?? null,
                'bitrate' => $jsonInfo['format']['bit_rate'] ?? null,
                'format' => $jsonInfo['format']['format_long_name'] ?? null
            ]
        ];
    } else {
        return [
            'valid' => false,
            'error' => 'El archivo MP3 parece estar corrupto o no es válido'
        ];
    }
}

// Procesar request
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Si no hay acción en GET/POST, intentar leer del body JSON
    if (empty($action)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        logMessage("Action from JSON body: $action");
    }

    logMessage("Processing action: $action");

    switch ($action) {
        case 'list':
            $musicList = getMusicList();
            echo json_encode([
                'success' => true,
                'music' => $musicList,
                'count' => count($musicList)
            ]);
            break;

        case 'upload':
            if (!isset($_FILES['music'])) {
                throw new Exception('No se recibió ningún archivo');
            }

            $result = uploadMusic($_FILES['music']);

            // Limpiar cachés para aplicar cambios
            try {
                restartServices();
                $servicesRestarted = true;
                $serviceMessage = 'Cachés limpiados. Cambios aplicados.';
            } catch (Exception $e) {
                $servicesRestarted = false;
                $serviceMessage = 'Los cambios se aplicarán automáticamente en breve.';
            }

            echo json_encode([
                'success' => true,
                'message' => 'Archivo subido exitosamente',
                'file' => $result,
                'servicesRestarted' => $servicesRestarted,
                'serviceMessage' => $serviceMessage
            ]);
            break;

        case 'delete':
            // Si no tenemos $input ya definido, leer del body
            if (!isset($input)) {
                $input = json_decode(file_get_contents('php://input'), true);
            }
            $filename = $input['filename'] ?? '';

            logMessage("Delete request for file: $filename");

            if (empty($filename)) {
                throw new Exception('Debe especificar el archivo a eliminar');
            }

            deleteMusic($filename);

            // Intentar reiniciar servicios
            try {
                restartServices();
                $servicesRestarted = true;
            } catch (Exception $e) {
                $servicesRestarted = false;
                $serviceError = $e->getMessage();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Archivo eliminado exitosamente',
                'servicesRestarted' => $servicesRestarted,
                'serviceError' => $serviceError ?? null
            ]);
            break;

        case 'validate':
            $filename = $_GET['filename'] ?? '';

            if (empty($filename)) {
                throw new Exception('Debe especificar el archivo a validar');
            }

            $validation = validateMusic($filename);

            echo json_encode([
                'success' => true,
                'validation' => $validation
            ]);
            break;

        case 'restart':
            // Llamar a la función de reinicio (que es asíncrona)
            restartServices();

            // Devolver respuesta inmediata (el reinicio ocurrirá en background)
            echo json_encode([
                'success' => true,
                'message' => 'Servicios programados para reinicio. Los cambios se aplicarán en 2-3 segundos.'
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>