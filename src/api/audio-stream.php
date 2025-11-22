<?php
/**
 * Endpoint simple para servir archivos de audio sin autenticación
 * Uso: /api/audio-stream.php?file=path/to/file.mp3
 */

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Obtener el path del archivo
$filePath = $_GET['file'] ?? '';

if (empty($filePath)) {
    http_response_code(400);
    die('No file specified');
}

// Base path del proyecto
$basePath = '/var/www/casa/';

// Construir path completo (prevenir directory traversal)
$fullPath = $basePath . str_replace('..', '', $filePath);

// Verificar que el archivo existe
if (!file_exists($fullPath)) {
    http_response_code(404);
    die('File not found');
}

// Verificar que es un archivo de audio
$mimeType = mime_content_type($fullPath);
$allowedMimes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/aac'];

if (!in_array($mimeType, $allowedMimes)) {
    // Si no detecta bien el MIME type, verificar extensión
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp3', 'wav', 'ogg', 'aac', 'm4a'])) {
        http_response_code(403);
        die('Not an audio file');
    }
    // Forzar MIME type basado en extensión
    $mimeType = $ext === 'mp3' ? 'audio/mpeg' : 'audio/' . $ext;
}

// Obtener tamaño del archivo
$fileSize = filesize($fullPath);

// Headers para streaming de audio
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: no-cache');

// Manejar Range requests para seeking (opcional pero útil)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    list($start, $end) = explode('-', substr($range, 6));
    
    $start = intval($start);
    $end = $end ? intval($end) : $fileSize - 1;
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$fileSize");
    
    $fp = fopen($fullPath, 'rb');
    fseek($fp, $start);
    $remaining = $end - $start + 1;
    
    while ($remaining > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $remaining));
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($fp);
} else {
    // Servir archivo completo
    readfile($fullPath);
}
