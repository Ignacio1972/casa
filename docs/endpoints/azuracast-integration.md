# AzuraCast Integration Documentation

<endpoint_overview>
Sistema completo de integración con AzuraCast para gestión automatizada de radio.
Incluye upload de archivos, gestión de playlists, interrupción de señal en vivo y preview.
</endpoint_overview>

## Radio Service API

### Core Endpoint: `/services/radio-service.php`

<description>
Servicio principal para todas las operaciones con AzuraCast.
Maneja el ciclo completo desde generación hasta emisión en radio.
</description>

---

## File Naming System

<file_naming>
El sistema utiliza diferentes convenciones de nombrado según el contexto:

### 1. Archivos TTS Generados
```
Formato local: tts{timestamp}_{voice}.mp3
Ejemplo: tts20250112143022_Rachel.mp3

Formato AzuraCast: tts{timestamp}.mp3
Ejemplo: tts20250112143022.mp3
```

### 2. Archivos de Jingles
```
Formato: jingle_{timestamp}_{voice}.mp3
Ejemplo: jingle_20250112160000_Bella.mp3
```

### 3. Archivos con Categoría
```
Formato: {category}_{timestamp}_{voice}.mp3
Ejemplo: promociones_20250112180000_Antoni.mp3
```

### Proceso de Renombrado
```php
// Local (con información completa)
$localFilename = sprintf('tts%s_%s.mp3', 
    date('YmdHis'), 
    $voice
);

// AzuraCast (simplificado para compatibilidad)
$radioFilename = sprintf('tts%s.mp3', 
    date('YmdHis')
);
```
</file_naming>

---

## File Storage Structure in AzuraCast

<storage_structure>
Los archivos se organizan en carpetas específicas dentro de AzuraCast:

```
/var/azuracast/stations/{station_name}/media/
├── Grabaciones/              # TTS generados (principal)
│   ├── tts20250112143022.mp3
│   ├── tts20250112150000.mp3
│   └── tts20250112160000.mp3
├── Jingles/                  # Jingles con música
│   ├── jingle_20250112_morning.mp3
│   └── jingle_20250112_evening.mp3
├── Promociones/              # Categoría promociones
│   └── promo_blackfriday.mp3
├── Informativos/             # Categoría informativos
│   └── info_horarios.mp3
├── Emergencias/              # Mensajes de emergencia
│   └── emergency_evacuation.mp3
└── Musica/                   # Biblioteca musical
    ├── upbeat_commercial.mp3
    └── corporate_inspire.mp3
```

### Asignación Automática de Carpetas
```php
function determineAzuracastFolder($category, $type) {
    $folderMap = [
        'jingles' => 'Jingles/',
        'promociones' => 'Promociones/',
        'informativos' => 'Informativos/',
        'emergencias' => 'Emergencias/',
        'musica' => 'Musica/'
    ];
    
    // Default: Grabaciones
    return $folderMap[$category] ?? 'Grabaciones/';
}
```
</storage_structure>

---

## Upload Process to AzuraCast

<upload_process>
### Step 1: File Upload
```bash
curl -X POST "http://localhost:4000/src/api/services/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "upload",
    "filepath": "/tmp/tts20250112143022.mp3",
    "category": "informativos",
    "metadata": {
      "title": "Anuncio de Apertura",
      "artist": "Casa Costanera",
      "album": "Informativos Enero 2025"
    }
  }'
```

### Upload Function Implementation
```php
function uploadFileToAzuraCast($filepath, $originalFilename) {
    $url = AZURACAST_BASE_URL . '/api/station/' . AZURACAST_STATION_ID . '/files';
    
    // Determinar carpeta destino
    $folder = determineAzuracastFolder($category, $type);
    $radioPath = $folder . $radioFilename;
    
    // Codificar archivo en base64
    $fileContent = file_get_contents($filepath);
    $base64Content = base64_encode($fileContent);
    
    $data = [
        'path' => $radioPath,
        'file' => $base64Content
    ];
    
    // Enviar a AzuraCast
    $response = azuracastApiCall('POST', $url, $data);
    
    return [
        'id' => $response['id'],
        'path' => $radioPath,
        'filename' => $radioFilename
    ];
}
```

### Response
```json
{
  "success": true,
  "azuracast": {
    "id": 12345,
    "path": "Grabaciones/tts20250112143022.mp3",
    "filename": "tts20250112143022.mp3",
    "size": 245760,
    "duration": 15.3
  }
}
```
</upload_process>

---

## Playlist Assignment

<playlist_management>
### Available Playlists
```php
define('PLAYLIST_ID_GENERAL', 1);        // Rotación general
define('PLAYLIST_ID_GRABACIONES', 2);    // Mensajes TTS
define('PLAYLIST_ID_JINGLES', 3);        // Jingles
define('PLAYLIST_ID_PROMOCIONES', 4);    // Promociones
define('PLAYLIST_ID_EMERGENCIAS', 5);    // Emergencias
```

### Auto-Assignment to Playlist
```bash
curl -X POST "http://localhost:4000/src/api/services/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "assign_playlist",
    "file_id": 12345,
    "playlist_ids": [2, 4],
    "weight": 5,
    "cue_in": 0.0,
    "cue_out": 15.3,
    "fade_in": 0.5,
    "fade_out": 2.0
  }'
```

### Assignment Function
```php
function assignFileToPlaylist($fileId, $playlistIds = null) {
    // Auto-determinar playlist si no se especifica
    if (!$playlistIds) {
        $playlistIds = determinePlaylistByCategory($category);
    }
    
    $url = AZURACAST_BASE_URL . '/api/station/' . AZURACAST_STATION_ID . '/file/' . $fileId;
    
    $playlists = array_map(function($id) use ($weight) {
        return [
            'id' => $id,
            'weight' => $weight ?? 3  // Peso default: 3
        ];
    }, $playlistIds);
    
    $data = [
        'playlists' => $playlists,
        'cue_in' => $cue_in ?? 0.0,
        'cue_out' => $cue_out ?? null,
        'fade_in' => $fade_in ?? 0.0,
        'fade_out' => $fade_out ?? 0.0
    ];
    
    return azuracastApiCall('PUT', $url, $data);
}
```

### Playlist Rules
```json
{
  "playlist_rules": {
    "grabaciones": {
      "type": "scheduled",
      "schedule": "*/30 * * * *",
      "order": "shuffle",
      "avoid_duplicates": true,
      "duplicate_prevention_time": 180
    },
    "promociones": {
      "type": "scheduled",
      "schedule": "*/15 9-21 * * *",
      "priority": "high",
      "interrupting": false
    },
    "emergencias": {
      "type": "immediate",
      "priority": "urgent",
      "interrupting": true,
      "override_all": true
    }
  }
}
```
</playlist_management>

---

## Radio Signal Interruption System

<interruption_system>
Sistema crítico para interrumpir la programación regular con mensajes urgentes.

### Interruption Levels
1. **URGENT**: Interrumpe inmediatamente (emergencias)
2. **HIGH**: Interrumpe al final de la canción actual
3. **NORMAL**: Se agrega a la cola con prioridad
4. **LOW**: Se programa normalmente

### Interrupt Radio Signal
```bash
curl -X POST "http://localhost:4000/src/api/services/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "interrupt",
    "filename": "tts20250112143022.mp3",
    "priority": "urgent",
    "fade_out_current": true,
    "fade_duration": 2,
    "return_to_playlist": true
  }'
```

### Interruption Implementation
```php
function interruptRadio($filename, $priority = 'high') {
    logMessage("Interrumpiendo radio con archivo: $filename");
    
    // Construir URI del archivo
    $fileUri = "file:///var/azuracast/stations/test/media/Grabaciones/" . $filename;
    
    // Comando según prioridad
    switch($priority) {
        case 'urgent':
            $command = "interrupting_requests.push $fileUri";
            break;
        case 'high':
            $command = "requests.queue 0 $fileUri";
            break;
        default:
            $command = "requests.queue 1 $fileUri";
    }
    
    // Ejecutar en Liquidsoap via Docker
    $dockerCommand = sprintf(
        'sudo docker exec azuracast bash -c \'echo "%s" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock\'',
        $command
    );
    
    $output = shell_exec($dockerCommand . ' 2>&1');
    
    // Verificar resultado
    $outputLines = explode("\n", trim($output));
    $requestId = isset($outputLines[0]) ? trim($outputLines[0]) : '';
    
    if (is_numeric($requestId)) {
        logMessage("Interrupción exitosa! Request ID: " . $requestId);
        return [
            'success' => true,
            'request_id' => $requestId,
            'status' => 'playing'
        ];
    }
    
    throw new Exception("Error en interrupción: " . $output);
}
```

### Emergency Interruption Protocol
```php
function emergencyBroadcast($message, $repeat = 3) {
    // 1. Generar audio de emergencia
    $audio = generateEmergencyAudio($message);
    
    // 2. Subir a AzuraCast
    $file = uploadFileToAzuraCast($audio['path'], 'emergency');
    
    // 3. Interrumpir inmediatamente
    for ($i = 0; $i < $repeat; $i++) {
        interruptRadio($file['filename'], 'urgent');
        sleep(30); // Esperar 30 segundos entre repeticiones
    }
    
    // 4. Registrar en log de emergencias
    logEmergency($message, $file, $repeat);
}
```

### Liquidsoap Commands
```bash
# Interrumpir inmediatamente
echo "interrupting_requests.push file:///path/to/file.mp3" | socat - UNIX-CONNECT:/liquidsoap.sock

# Agregar a cola prioritaria
echo "requests.queue 0 file:///path/to/file.mp3" | socat - UNIX-CONNECT:/liquidsoap.sock

# Ver cola actual
echo "requests.queue" | socat - UNIX-CONNECT:/liquidsoap.sock

# Saltar canción actual
echo "skip" | socat - UNIX-CONNECT:/liquidsoap.sock
```
</interruption_system>

---

## Audio Preview System

<preview_system>
Sistema para previsualizar audio antes de emitirlo en radio.

### Generate Preview
```bash
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "preview",
    "text": "Este es un mensaje de prueba",
    "voice": "Rachel",
    "preview_only": true,
    "return_format": "base64"
  }'
```

### Preview Response
```json
{
  "success": true,
  "preview": {
    "filename": "preview_tts20250112143022.mp3",
    "local_path": "/tmp/preview_tts20250112143022.mp3",
    "audio_base64": "data:audio/mp3;base64,//uQxAAA...",
    "duration": 5.2,
    "expires_in": 300
  }
}
```

### Preview Workflow
```php
function generatePreview($text, $voice, $settings = []) {
    // 1. Generar audio TTS
    $audio = generateTTS($text, $voice, $settings);
    
    // 2. Guardar temporalmente (NO en AzuraCast)
    $previewPath = sys_get_temp_dir() . '/preview_' . uniqid() . '.mp3';
    file_put_contents($previewPath, $audio);
    
    // 3. Convertir a base64 para reproducción en browser
    $base64 = 'data:audio/mp3;base64,' . base64_encode($audio);
    
    // 4. Programar eliminación automática
    scheduleFileCleanup($previewPath, 300); // 5 minutos
    
    return [
        'local_path' => $previewPath,
        'base64' => $base64,
        'duration' => getAudioDuration($previewPath)
    ];
}
```

### Browser Preview Player
```html
<!-- Preview en el frontend -->
<audio id="previewPlayer" controls></audio>

<script>
function playPreview(base64Audio) {
    const player = document.getElementById('previewPlayer');
    player.src = base64Audio;
    player.play();
}

// Recibir preview del API
fetch('/api/generate.php', {
    method: 'POST',
    body: JSON.stringify({
        action: 'preview',
        text: 'Mensaje de prueba',
        voice: 'Rachel'
    })
})
.then(res => res.json())
.then(data => {
    if (data.success) {
        playPreview(data.preview.audio_base64);
    }
});
</script>
```

### Preview-to-Production Flow
```javascript
async function previewAndPublish(text, voice) {
    // 1. Generar preview
    const preview = await generatePreview(text, voice);
    
    // 2. Usuario aprueba
    if (confirm('¿Publicar este audio?')) {
        // 3. Generar versión final
        const final = await generateFinal(text, voice);
        
        // 4. Subir a AzuraCast
        const uploaded = await uploadToAzuraCast(final);
        
        // 5. Asignar a playlist
        await assignToPlaylist(uploaded.id);
        
        // 6. Limpiar preview
        await cleanupPreview(preview.id);
    }
}
```
</preview_system>

---

## Complete Workflow Example

<complete_workflow>
### From Text to Radio: Complete Process

```javascript
async function completeRadioWorkflow(announcement) {
    try {
        // 1. PREVIEW: Generar preview para aprobación
        console.log('1. Generando preview...');
        const preview = await fetch('/api/generate.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'preview',
                text: announcement.text,
                voice: announcement.voice,
                preview_only: true
            })
        }).then(r => r.json());
        
        // 2. APROBAR: Usuario escucha y aprueba
        console.log('2. Preview disponible:', preview.preview.local_path);
        // ... reproducir preview en UI ...
        
        // 3. GENERAR: Crear versión final
        console.log('3. Generando audio final...');
        const audio = await fetch('/api/generate.php', {
            method: 'POST',
            body: JSON.stringify({
                text: announcement.text,
                voice: announcement.voice,
                category: announcement.category,
                client_id: 'casa'
            })
        }).then(r => r.json());
        
        // 4. SUBIR: Upload a AzuraCast
        console.log('4. Subiendo a AzuraCast...');
        const uploaded = await fetch('/api/services/radio-service.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'upload',
                filepath: audio.filepath,
                category: announcement.category,
                metadata: {
                    title: announcement.title,
                    artist: 'Casa Costanera'
                }
            })
        }).then(r => r.json());
        
        // 5. PLAYLIST: Asignar a playlist correcta
        console.log('5. Asignando a playlist...');
        const playlist = await fetch('/api/services/radio-service.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'assign_playlist',
                file_id: uploaded.azuracast.id,
                playlist_ids: [2], // Grabaciones
                weight: 5
            })
        }).then(r => r.json());
        
        // 6. PROGRAMAR: Configurar horario si aplica
        if (announcement.schedule) {
            console.log('6. Programando emisión...');
            await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'create',
                    audio_file: uploaded.azuracast.path,
                    schedule_type: 'specific',
                    schedule_data: announcement.schedule
                })
            });
        }
        
        // 7. INTERRUMPIR: Si es urgente, interrumpir ahora
        if (announcement.urgent) {
            console.log('7. Interrumpiendo señal...');
            await fetch('/api/services/radio-service.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'interrupt',
                    filename: uploaded.azuracast.filename,
                    priority: 'urgent'
                })
            });
        }
        
        return {
            success: true,
            audio_id: audio.id,
            azuracast_id: uploaded.azuracast.id,
            filename: uploaded.azuracast.filename,
            status: announcement.urgent ? 'playing' : 'scheduled'
        };
        
    } catch (error) {
        console.error('Error en workflow:', error);
        throw error;
    }
}

// Uso
const result = await completeRadioWorkflow({
    text: 'Atención visitantes, el estacionamiento nivel 3 está cerrado',
    voice: 'Rachel',
    category: 'informativos',
    title: 'Aviso Estacionamiento',
    urgent: true,
    schedule: null
});
```
</complete_workflow>

---

## Configuration & Environment

<configuration>
### Required Environment Variables
```bash
# .env
AZURACAST_BASE_URL=http://localhost:8080
AZURACAST_API_KEY=your_api_key_here
AZURACAST_STATION_ID=1

# Playlist IDs
PLAYLIST_ID_GENERAL=1
PLAYLIST_ID_GRABACIONES=2
PLAYLIST_ID_JINGLES=3
PLAYLIST_ID_PROMOCIONES=4
PLAYLIST_ID_EMERGENCIAS=5
```

### Docker Access Configuration
```bash
# Permitir acceso a Liquidsoap socket
sudo usermod -a -G docker www-data
sudo chmod 666 /var/run/docker.sock

# Verificar acceso
sudo -u www-data docker ps
```

### File Permissions
```bash
# Permisos para carpetas de media
sudo chown -R azuracast:azuracast /var/azuracast/stations/test/media/
sudo chmod -R 775 /var/azuracast/stations/test/media/Grabaciones/
```
</configuration>

---

## Error Handling

<error_handling>
### Common Errors and Solutions

#### Upload Failed
```json
{
  "success": false,
  "error": "Error subiendo archivo a AzuraCast",
  "code": "UPLOAD_FAILED",
  "details": {
    "http_code": 413,
    "message": "File too large",
    "max_size": "50MB"
  }
}
```

#### Interruption Failed
```json
{
  "success": false,
  "error": "No se pudo interrumpir la señal",
  "code": "INTERRUPT_FAILED",
  "details": {
    "reason": "Liquidsoap socket not accessible",
    "command": "interrupting_requests.push file://...",
    "output": "Connection refused"
  }
}
```

#### Playlist Assignment Failed
```json
{
  "success": false,
  "error": "Error asignando a playlist",
  "code": "PLAYLIST_ERROR",
  "details": {
    "playlist_id": 2,
    "reason": "Playlist not found or inactive"
  }
}
```

### Retry Logic
```php
function retryOperation($operation, $maxRetries = 3) {
    $attempt = 0;
    $lastError = null;
    
    while ($attempt < $maxRetries) {
        try {
            return $operation();
        } catch (Exception $e) {
            $lastError = $e;
            $attempt++;
            
            // Exponential backoff
            $waitTime = pow(2, $attempt);
            sleep($waitTime);
            
            logMessage("Retry $attempt/$maxRetries after {$waitTime}s");
        }
    }
    
    throw new Exception("Operation failed after $maxRetries attempts: " . $lastError->getMessage());
}
```
</error_handling>

---

## Monitoring & Logs

<monitoring>
### Log Locations
```bash
# Application logs
/var/www/casa/src/api/logs/radio-service-{date}.log
/var/www/casa/src/api/logs/interruptions-{date}.log

# AzuraCast logs
/var/azuracast/stations/test/config/liquidsoap.log
/var/azuracast/www_tmp/app.log

# System logs
/var/log/nginx/error.log
```

### Health Check Endpoint
```bash
curl -X POST "http://localhost:4000/src/api/services/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "health_check"
  }'
```

Response:
```json
{
  "success": true,
  "status": {
    "azuracast": {
      "connected": true,
      "version": "0.17.6",
      "station_online": true
    },
    "liquidsoap": {
      "connected": true,
      "uptime": 86400,
      "current_track": "song.mp3"
    },
    "storage": {
      "used": "45GB",
      "available": "155GB",
      "files_count": 4523
    },
    "last_upload": "2025-01-12 14:30:22",
    "last_interruption": "2025-01-12 13:15:00"
  }
}
```
</monitoring>