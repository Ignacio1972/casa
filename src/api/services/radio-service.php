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
    
    // Usar el nombre original si se proporciona, o generar uno descriptivo
    if (!empty($originalFilename) && $originalFilename !== 'temp.mp3') {
        // Si ya tiene un nombre descriptivo, usarlo
        $radioFilename = $originalFilename;
    } else {
        // Generar nombre descriptivo: mensaje_YYYYMMDD_HHMMSS.mp3
        $timestamp = date('Ymd_His');
        $radioFilename = 'mensaje_' . $timestamp . '.mp3';
    }
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
    
    // Si la respuesta es numérica (Request ID clásico), es exitoso
    if (is_numeric($firstLine)) {
        logMessage("¡Interrupción exitosa! RID: " . $firstLine);
        return true;
    }
    
    // Si no hay respuesta o es vacía, pero tampoco hay error explícito, considerar exitoso
    // (Algunas versiones/configuraciones de Liquidsoap no devuelven ID)
    if ($output === null || trim($output) === '' || trim($output) === "\n") {
        logMessage("Interrupción ejecutada (sin ID de confirmación, pero sin error)");
        return true;
    }
    
    // Buscar mensajes de error explícitos
    $outputLower = strtolower($output);
    if (strpos($outputLower, 'error') !== false || 
        strpos($outputLower, 'failed') !== false ||
        strpos($outputLower, 'refused') !== false ||
        strpos($outputLower, 'not found') !== false) {
        logMessage("Error detectado en la interrupción: " . trim($output));
        return false;
    }
    
    // Si llegamos aquí, hay respuesta pero no es estándar
    // Asumimos éxito si no hay error explícito
    logMessage("Interrupción con respuesta no estándar (asumiendo éxito): " . trim($output));
    return true;
}

/**
 * Obtiene la duración de un archivo de audio usando ffprobe
 * @param string $filepath Ruta al archivo de audio
 * @return float Duración en segundos, o 30 si no se puede determinar
 */
function getAudioDuration($filepath) {
    // Verificar si el archivo existe
    if (!file_exists($filepath)) {
        logMessage("Archivo no existe para obtener duración: $filepath");
        return 30; // Default 30 segundos
    }
    
    // Usar ffprobe para obtener duración exacta
    $cmd = sprintf(
        'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
        escapeshellarg($filepath)
    );
    
    $duration = shell_exec($cmd);
    
    if ($duration && is_numeric(trim($duration))) {
        $seconds = floatval($duration);
        logMessage("Duración detectada: {$seconds} segundos para $filepath");
        return $seconds;
    }
    
    logMessage("No se pudo obtener duración, usando default 30s para $filepath");
    return 30; // Default si no se puede obtener
}

/**
 * Interrumpe la radio con skip automático después del mensaje
 * @param string $filename Nombre del archivo en AzuraCast
 * @param float|null $duration Duración del audio en segundos (opcional)
 * @param bool $skipAfter Si debe hacer skip después del mensaje (default: true)
 * @return bool
 */
function interruptRadioWithSkip($filename, $duration = null, $skipAfter = true) {
    logMessage("=== INTERRUPT WITH SKIP ===");
    logMessage("Archivo: $filename");
    logMessage("Skip después: " . ($skipAfter ? 'SÍ' : 'NO'));
    
    // Primero, hacer la interrupción normal
    $interruptSuccess = interruptRadio($filename);
    
    if (!$interruptSuccess) {
        logMessage("Fallo la interrupción inicial, abortando");
        return false;
    }
    
    // Si no queremos skip después, terminar aquí
    if (!$skipAfter) {
        logMessage("Skip desactivado, terminando");
        return true;
    }
    
    // Si no tenemos duración, intentar obtenerla
    if ($duration === null) {
        // Intentar múltiples rutas donde podría estar el archivo
        $possiblePaths = [
            "/var/azuracast/stations/test/media/Grabaciones/" . $filename,
            "/var/www/casa/src/api/temp/" . $filename,
            "/tmp/" . $filename
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $duration = getAudioDuration($path);
                break;
            }
        }
        
        // Si aún no tenemos duración, usar un default
        if ($duration === null) {
            logMessage("No se encontró el archivo para calcular duración, usando 15 segundos");
            $duration = 15;
        }
    }
    
    // Agregar 2 segundos de buffer para asegurar que termine el mensaje
    $waitTime = ceil($duration) + 2;
    logMessage("Programando skip en {$waitTime} segundos (duración: {$duration}s + 2s buffer)");
    
    // Programar el skip para que se ejecute después del mensaje
    // IMPORTANTE: El comando correcto es playlist_default.skip
    // Simplificamos el comando para evitar problemas con nohup
    $skipCommand = sprintf(
        '(sleep %d && echo "playlist_default.skip" | sudo docker exec -i azuracast socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock) > /dev/null 2>&1 &',
        $waitTime
    );
    
    shell_exec($skipCommand);
    logMessage("Skip programado exitosamente para ejecutarse en {$waitTime} segundos");
    logMessage("Comando ejecutado: " . substr($skipCommand, 0, 150));
    
    // Log adicional para debugging
    logMessage("Comando de skip programado: " . substr($skipCommand, 0, 200));
    
    return true;
}

/**
 * Envía comando skip inmediatamente
 * Útil para pruebas o skip manual
 * @return bool
 */
function skipSongNow() {
    logMessage("Ejecutando skip inmediato");
    
    // Usar el comando correcto: playlist_default.skip
    $skipCommand = 'echo "playlist_default.skip" | sudo docker exec azuracast socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock 2>&1';
    $output = shell_exec($skipCommand);
    
    logMessage("Respuesta del skip: " . trim($output));
    
    // El comando devuelve "OK" cuando es exitoso
    return strpos($output, 'OK') !== false;
}
?>
