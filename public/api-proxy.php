<?php
/**
 * Proxy para servicios API
 * Redirige las peticiones al servidor Node.js en puerto 4000
 */

// Obtener la ruta solicitada
$path = $_GET['path'] ?? '';

// URL del servidor Node.js
$nodeUrl = 'http://localhost:4000/api/' . $path;

// Obtener método y datos
$method = $_SERVER['REQUEST_METHOD'];
$inputData = file_get_contents('php://input');

// Configurar headers para la petición
$headers = [
    'Content-Type: application/json',
];

// Inicializar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $nodeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Configurar método y datos según corresponda
if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $inputData);
} elseif ($method === 'GET') {
    // GET ya está configurado
} else {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if (!empty($inputData)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $inputData);
    }
}

// Ejecutar petición
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Devolver respuesta
if ($contentType) {
    header('Content-Type: ' . $contentType);
}
http_response_code($httpCode);
echo $response;
?>