# Audio Scheduler API

<endpoint_overview>
Sistema de programación de audio para reproducción automatizada.
Soporta múltiples tipos de programación: intervalos, horarios específicos y eventos únicos.
</endpoint_overview>

## POST /audio-scheduler.php

<description>
Gestiona la programación de archivos de audio para reproducción automática
según diferentes estrategias de scheduling.
</description>

<parameters>
### Required Parameters
- `action` (string): Acción a ejecutar
  - `create`: Crear nueva programación
  - `list`: Listar programaciones activas
  - `update`: Actualizar programación existente
  - `delete`: Eliminar programación
  - `activate`: Activar programación
  - `deactivate`: Desactivar programación
- `client_id` (string): Identificador del cliente

### Parameters for 'create' action
- `audio_file` (string): Ruta del archivo de audio
- `schedule_type` (string): Tipo de programación
  - `interval`: Repetir cada X minutos/horas
  - `specific`: Horarios específicos del día
  - `once`: Una sola vez
- `schedule_data` (object): Configuración según tipo

### Schedule Data for 'interval'
```json
{
  "interval": 30,
  "unit": "minutes",
  "start_time": "09:00",
  "end_time": "21:00",
  "days": [1, 2, 3, 4, 5]  // 0=Domingo, 6=Sábado
}
```

### Schedule Data for 'specific'
```json
{
  "times": ["10:00", "14:30", "18:00"],
  "days": [1, 2, 3, 4, 5],
  "repeat": true
}
```

### Schedule Data for 'once'
```json
{
  "date": "2025-01-15",
  "time": "15:30"
}
```
</parameters>

<example_request>
```bash
# Crear programación por intervalo
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "client_id": "casa",
    "audio_file": "/audio/tts_20250112_143022_Rachel.mp3",
    "schedule_type": "interval",
    "schedule_data": {
      "interval": 60,
      "unit": "minutes",
      "start_time": "09:00",
      "end_time": "21:00",
      "days": [1, 2, 3, 4, 5, 6]
    },
    "metadata": {
      "name": "Anuncio horario",
      "category": "informativos",
      "priority": "normal"
    }
  }'

# Listar programaciones activas
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list",
    "client_id": "casa",
    "filters": {
      "active": true,
      "schedule_type": "interval"
    }
  }'
```
</example_request>

<example_response>
```json
{
  "success": true,
  "data": {
    "schedule_id": 456,
    "audio_file": "/audio/tts_20250112_143022_Rachel.mp3",
    "schedule_type": "interval",
    "schedule_data": {
      "interval": 60,
      "unit": "minutes",
      "start_time": "09:00",
      "end_time": "21:00",
      "days": [1, 2, 3, 4, 5, 6]
    },
    "active": true,
    "next_play": "2025-01-12 10:00:00",
    "created_at": "2025-01-12 09:30:00"
  },
  "message": "Programación creada exitosamente"
}
```
</example_response>

<schedule_management>
## Schedule Management Operations

### Update Schedule
```bash
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update",
    "client_id": "casa",
    "schedule_id": 456,
    "schedule_data": {
      "interval": 30,
      "unit": "minutes"
    }
  }'
```

### Activate/Deactivate Schedule
```bash
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "deactivate",
    "client_id": "casa",
    "schedule_id": 456
  }'
```

### Delete Schedule
```bash
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete",
    "client_id": "casa",
    "schedule_id": 456
  }'
```
</schedule_management>

<playlist_integration>
## Playlist Generation

### Generate M3U Playlist
```bash
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate_playlist",
    "client_id": "casa",
    "filters": {
      "active": true,
      "date": "2025-01-15"
    },
    "format": "m3u"
  }'
```

Response:
```json
{
  "success": true,
  "data": {
    "playlist_url": "/playlists/casa_20250115.m3u",
    "total_tracks": 24,
    "duration": "02:15:30"
  }
}
```
</playlist_integration>

<cron_integration>
## Cron Job Setup

Para ejecutar el scheduler automáticamente:

```bash
# Agregar a crontab
*/1 * * * * php /var/www/casa/src/api/scheduler.php >> /var/log/casa-scheduler.log 2>&1
```

El script `scheduler.php` verifica las programaciones activas y ejecuta las que correspondan.
</cron_integration>

<error_handling>
## Common Errors

### Invalid Schedule Type
```json
{
  "success": false,
  "error": "Tipo de programación no válido",
  "code": "INVALID_PARAM",
  "details": {
    "valid_types": ["interval", "specific", "once"]
  }
}
```

### Audio File Not Found
```json
{
  "success": false,
  "error": "Archivo de audio no encontrado",
  "code": "FILE_ERROR",
  "details": {
    "file": "/audio/missing.mp3"
  }
}
```

### Schedule Conflict
```json
{
  "success": false,
  "error": "Conflicto con programación existente",
  "code": "SCHEDULE_CONFLICT",
  "details": {
    "conflicting_schedule_id": 123
  }
}
```
</error_handling>