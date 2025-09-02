<?php
/**
 * Servicio Radio - AzuraCast
 * Maneja la comunicación con AzuraCast y la interrupción de radio
 */

/**
 * Sube archivo a AzuraCast y retorna info completa
 */
function uploadFileToAzuraCast($filepath, $originalFilename) {
    $url = AZURACAST_BASE_URL . '/api/station/' . AZURACAST_STATION_ID . '/files';
    
    // Nombre simple sin guiones
    $timestamp = date('YmdHis');
    $radioFilename = 'tts' . $timestamp . '.mp3';
    $radioPath = 'Grabaciones/' . $radioFilename;
    
    // Leer y codificar archivo
    $fileContent = file_get_contents($filepath);
    $base64Content = base64_encode($fileContent);
    
    $data = [
        'path' => $radioPath,
        'file' => $base64Content
    ];
    
    logMessage("Subiendo archivo a AzuraCast: $radioPath");
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . AZURACAST_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error subiendo archivo: HTTP $httpCode");
    }
    
    $responseData = json_decode($response, true);
    if (!$responseData || !isset($responseData['id'])) {
        throw new Exception('Respuesta inválida del servidor al subir archivo');
    }
    
    // Retornar ID y nombre real del archivo
    return [
        'id' => $responseData['id'],
        'filename' => $radioFilename
    ];
}

/**
 * Asigna archivo a playlist "grabaciones"
 */
function assignFileToPlaylist($fileId) {
    $url = AZURACAST_BASE_URL . '/api/station/' . AZURACAST_STATION_ID . '/file/' . $fileId;
    
    $data = [
        'playlists' => [
            ['id' => PLAYLIST_ID_GRABACIONES]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . AZURACAST_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error asignando a playlist: HTTP $httpCode");
    }
}

/**
 * Interrumpe la radio con el archivo especificado
 */
function interruptRadio($filename) {
    logMessage("Interrumpiendo radio con archivo: $filename");
    
    // Construir URI y ejecutar interrupción
    $fileUri = "file:///var/azuracast/stations/test/media/Grabaciones/" . $filename;
    $command = "interrupting_requests.push $fileUri";
    $dockerCommand = 'sudo docker exec azuracast bash -c \'echo "' . $command . '" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock\'';
    
    $output = shell_exec($dockerCommand . ' 2>&1');
    logMessage("Interrupción ejecutada. Respuesta: " . trim($output));
    
    // Verificar si fue exitoso
    $outputLines = explode("\n", trim($output));
    $firstLine = isset($outputLines[0]) ? trim($outputLines[0]) : '';
    
    if ($output !== null && is_numeric($firstLine)) {
        logMessage("¡Interrupción exitosa! RID: " . $firstLine);
        return true;
    } else {
        logMessage("Error en la interrupción: " . trim($output));
        return false;
    }
}
?>
