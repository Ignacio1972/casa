# Voice Manager API

<endpoint_overview>
Sistema de gestión de voces personalizadas para ElevenLabs.
Permite agregar, configurar y gestionar voces custom además de las predefinidas.
</endpoint_overview>

## POST /playground/api/voice-manager.php

<description>
Gestiona voces personalizadas y configuraciones de API para el sistema TTS.
Integra voces clonadas o adicionales de ElevenLabs.
</description>

<parameters>
### Required Parameters
- `action` (string): Acción a ejecutar
  - `list`: Listar voces personalizadas
  - `add`: Agregar nueva voz
  - `delete`: Eliminar voz
  - `test`: Probar voice ID
  - `get_config`: Obtener configuración
  - `update_config`: Actualizar configuración
  - `set_order`: Configurar orden de voces

### Parameters for 'add'
- `voice_id` (string): ID de voz de ElevenLabs
- `voice_name` (string): Nombre display de la voz
- `voice_gender` (string): Género (M/F) (default: F)
- `voice_key` (string): Clave única (auto-generada si no se provee)
- `voice_settings` (object): Configuraciones predeterminadas opcionales
</parameters>

<example_request>
```bash
# Listar voces personalizadas
curl -X POST "http://localhost:4000/public/playground/api/voice-manager.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list"
  }'

# Agregar voz clonada
curl -X POST "http://localhost:4000/public/playground/api/voice-manager.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add",
    "voice_id": "IKne3meq5aSn9XLyUdCD",
    "voice_name": "Carlos - Voz Corporativa",
    "voice_gender": "M",
    "voice_key": "carlos_corporate",
    "voice_settings": {
      "stability": 0.75,
      "similarity_boost": 0.8,
      "style": 0.2
    }
  }'

# Probar voice ID
curl -X POST "http://localhost:4000/public/playground/api/voice-manager.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "test",
    "voice_id": "IKne3meq5aSn9XLyUdCD",
    "test_text": "Prueba de voz personalizada"
  }'
```
</example_request>

<example_response>
```json
{
  "success": true,
  "voices": {
    "carlos_corporate": {
      "id": "IKne3meq5aSn9XLyUdCD",
      "label": "Carlos - Voz Corporativa",
      "gender": "M",
      "custom": true,
      "added_date": "2025-01-12 15:30:00",
      "voice_settings": {
        "stability": 0.75,
        "similarity_boost": 0.8,
        "style": 0.2
      }
    },
    "maria_soft": {
      "id": "ThT5KcBeYPX3keUQqHPh",
      "label": "María - Voz Suave",
      "gender": "F",
      "custom": true,
      "added_date": "2025-01-10 10:15:00"
    }
  }
}
```
</example_response>

<voice_configuration>
## Voice Configuration Management

### Update Voice Settings
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-manager.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_voice",
    "voice_key": "carlos_corporate",
    "voice_settings": {
      "stability": 0.6,
      "similarity_boost": 0.85,
      "style": 0.3,
      "use_speaker_boost": true
    },
    "metadata": {
      "description": "Voz masculina profesional para anuncios corporativos",
      "best_for": ["informativos", "corporativo"],
      "sample_rate": 44100
    }
  }'
```

### Set Voice Order
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-manager.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "set_order",
    "voice_order": [
      "Rachel",
      "carlos_corporate",
      "Antoni",
      "maria_soft",
      "Domi"
    ]
  }'
```

### Get API Configuration
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-manager.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_config"
  }'
```

Response:
```json
{
  "success": true,
  "config": {
    "use_v3_api": true,
    "api_endpoint": "https://api.elevenlabs.io/v1",
    "default_model": "eleven_multilingual_v2",
    "output_format": "mp3_44100_128",
    "optimize_streaming_latency": 0,
    "voice_defaults": {
      "stability": 0.5,
      "similarity_boost": 0.75,
      "style": 0.0,
      "use_speaker_boost": true
    }
  }
}
```
</voice_configuration>

<voice_cloning>
## Voice Cloning Integration

### Upload Voice Sample for Cloning
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-manager.php" \
  -F "action=clone_voice" \
  -F "voice_name=CEO Voice" \
  -F "voice_description=Voz del CEO para anuncios especiales" \
  -F "voice_sample=@/path/to/voice_sample.mp3" \
  -F "voice_labels={\"accent\":\"spanish\",\"gender\":\"male\",\"age\":\"middle_aged\"}"
```

### Check Cloning Status
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-manager.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "clone_status",
    "clone_request_id": "clone_abc123"
  }'
```
</voice_cloning>

---

## Voice Admin API

### POST /playground/api/voice-admin.php

<description>
Panel administrativo avanzado para gestión de voces, quotas y configuraciones.
</description>

<admin_operations>

### Get Voice Statistics
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-admin.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "statistics",
    "period": "month"
  }'
```

Response:
```json
{
  "success": true,
  "statistics": {
    "period": "2025-01",
    "voice_usage": {
      "Rachel": {
        "count": 450,
        "characters": 125000,
        "percentage": 35
      },
      "Antoni": {
        "count": 280,
        "characters": 89000,
        "percentage": 22
      }
    },
    "total_characters": 358000,
    "quota_remaining": 642000,
    "most_used_voice": "Rachel",
    "custom_voices_usage": 15
  }
}
```

### Manage Voice Quotas
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-admin.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "set_quota",
    "voice_key": "carlos_corporate",
    "monthly_limit": 50000,
    "daily_limit": 2000
  }'
```

### Backup Voice Configuration
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-admin.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "backup",
    "include_custom": true,
    "include_settings": true
  }'
```

### Import Voice Configuration
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-admin.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "import",
    "config_data": {
      "voices": {...},
      "settings": {...}
    },
    "merge": true
  }'
```
</admin_operations>

---

## AzuraCast Integration

<azuracast_integration>
Sistema completo de integración con AzuraCast para gestión de radio automatizada.
</azuracast_integration>

### File Storage in AzuraCast

<file_storage>
Los archivos de audio se almacenan automáticamente en AzuraCast siguiendo esta estructura:

```
/var/azuracast/stations/{station_name}/media/
├── Grabaciones/          # Archivos TTS generados
│   ├── tts20250112_143022_Rachel.mp3
│   ├── tts20250112_150000_Antoni.mp3
│   └── jingle_20250112_160000_Bella.mp3
├── Jingles/             # Jingles con música
├── Promociones/         # Categoría específica
└── Informativos/        # Categoría específica
```

### Auto-upload Process
```php
// Proceso automático al generar audio
1. Generate TTS -> /src/api/temp/audio.mp3
2. Copy to AzuraCast -> /var/azuracast/stations/test/media/Grabaciones/
3. Register in database -> audio_metadata table
4. Trigger AzuraCast scan -> API call to refresh media
5. Add to playlist -> Auto-assign based on category
```
</file_storage>

### Audio Preview System

<audio_preview>
```bash
# Preview antes de guardar
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "preview",
    "client_id": "casa",
    "text": "Texto de prueba para preview",
    "voice": "Rachel",
    "preview_duration": 10,
    "return_base64": true
  }'
```

Response:
```json
{
  "success": true,
  "preview": {
    "audio_base64": "data:audio/mp3;base64,//uQx...",
    "duration": 10,
    "full_duration": 25.3,
    "preview_range": "0:00-0:10"
  }
}
```

### Browser Preview Player
```javascript
// Reproducir preview en navegador
function playPreview(base64Audio) {
  const audio = new Audio(base64Audio);
  audio.controls = true;
  audio.play();
  return audio;
}
```
</audio_preview>

### Radio Interruption System

<radio_interruption>
Sistema para interrumpir la programación regular con anuncios urgentes.

```bash
# Interrumpir con mensaje urgente
curl -X POST "http://localhost:4000/src/api/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "interrupt",
    "priority": "emergency",
    "audio_file": "/audio/emergency_announcement.mp3",
    "fade_out_music": true,
    "fade_duration": 2,
    "return_to_regular": true,
    "log_interruption": true
  }'
```

### Priority Levels
- `emergency`: Interrumpe inmediatamente (evacuación, seguridad)
- `high`: Interrumpe al final de la canción actual
- `normal`: Se agrega a la cola prioritaria
- `low`: Se programa para el próximo bloque

### Interruption Configuration
```json
{
  "interruption_rules": {
    "emergency": {
      "max_duration": 300,
      "require_confirmation": false,
      "override_all": true,
      "alert_admin": true
    },
    "high": {
      "max_duration": 180,
      "require_confirmation": true,
      "respect_current_song": true
    }
  }
}
```
</radio_interruption>

### Station & Playlist Management

<station_management>
```bash
# Get station info
curl -X POST "http://localhost:4000/src/api/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_station",
    "station_id": 1
  }'
```

Response:
```json
{
  "success": true,
  "station": {
    "id": 1,
    "name": "Casa Costanera Radio",
    "shortcode": "casa_radio",
    "playlists": [
      {
        "id": 1,
        "name": "General Rotation",
        "type": "default",
        "schedule": "always"
      },
      {
        "id": 2,
        "name": "Mensajes TTS",
        "type": "scheduled",
        "schedule": "*/30 * * * *",
        "folder": "Grabaciones"
      },
      {
        "id": 3,
        "name": "Jingles",
        "type": "scheduled",
        "schedule": "0 * * * *",
        "folder": "Jingles"
      },
      {
        "id": 4,
        "name": "Promociones",
        "type": "scheduled",
        "schedule": "*/15 9-21 * * *",
        "folder": "Promociones"
      }
    ]
  }
}
```

### Add to Playlist
```bash
curl -X POST "http://localhost:4000/src/api/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_to_playlist",
    "station_id": 1,
    "playlist_id": 2,
    "audio_file": "tts20250112_143022_Rachel.mp3",
    "cue_in": 0.5,
    "cue_out": 29.5,
    "fade_in": 0.5,
    "fade_out": 2,
    "schedule_rules": {
      "start_date": "2025-01-15",
      "end_date": "2025-01-30",
      "time_ranges": ["09:00-12:00", "15:00-18:00"],
      "days_of_week": [1,2,3,4,5]
    }
  }'
```
</station_management>

### Media Processing Queue

<media_queue>
```bash
# Check processing queue
curl -X POST "http://localhost:4000/src/api/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "queue_status"
  }'
```

Response:
```json
{
  "success": true,
  "queue": {
    "pending": 5,
    "processing": 2,
    "completed": 145,
    "failed": 0,
    "items": [
      {
        "id": "q_123",
        "file": "tts20250112_180000_Rachel.mp3",
        "status": "processing",
        "progress": 65,
        "operation": "normalize_loudness",
        "target_lufs": -16
      }
    ]
  }
}
```
</media_queue>

### Batch Upload to AzuraCast

<batch_upload>
```javascript
async function batchUploadToAzuraCast(audioFiles) {
  const results = [];
  
  for (const file of audioFiles) {
    const response = await fetch('/src/api/radio-service.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'upload_batch',
        station_id: 1,
        files: [
          {
            path: file.path,
            playlist_id: file.playlist,
            metadata: {
              title: file.title,
              artist: 'Casa Costanera',
              album: file.category,
              genre: 'Announcement'
            }
          }
        ],
        process_audio: true,
        normalize: true
      })
    });
    
    results.push(await response.json());
  }
  
  // Trigger media scan
  await fetch('/src/api/radio-service.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'scan_media',
      station_id: 1
    })
  });
  
  return results;
}
```
</batch_upload>

<error_handling>
## Error Handling

### Invalid Voice ID
```json
{
  "success": false,
  "error": "Voice ID no válido en ElevenLabs",
  "code": "INVALID_VOICE_ID",
  "details": {
    "voice_id": "invalid_id",
    "suggestion": "Verifique el ID en su cuenta de ElevenLabs"
  }
}
```

### AzuraCast Connection Error
```json
{
  "success": false,
  "error": "No se pudo conectar con AzuraCast",
  "code": "AZURACAST_ERROR",
  "details": {
    "endpoint": "http://localhost:8080/api/station/1",
    "error": "Connection refused"
  }
}
```

### Quota Exceeded
```json
{
  "success": false,
  "error": "Cuota de voz excedida",
  "code": "VOICE_QUOTA_EXCEEDED",
  "details": {
    "voice": "carlos_corporate",
    "used": 50000,
    "limit": 50000,
    "reset_date": "2025-02-01"
  }
}
```
</error_handling>