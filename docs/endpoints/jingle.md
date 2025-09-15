# Jingle Service API

<endpoint_overview>
Sistema avanzado de generación de jingles que combina mensajes TTS con música de fondo.
Incluye control completo de mezcla de audio: volumen, fade in/out, ducking automático y más.
</endpoint_overview>

## POST /api/jingle-service.php

<description>
Genera jingles profesionales mezclando voz TTS con música de fondo, 
con control granular sobre todos los aspectos del audio.
**NOTA**: Los jingles generados ahora se guardan automáticamente en la base de datos
y aparecen en los mensajes recientes del dashboard.
</description>

<parameters>
### Required Parameters
- `action` (string): Acción a ejecutar
  - `generate`: Generar nuevo jingle
  - `list_music`: Listar música disponible
- `text` (string): Texto para el mensaje TTS (requerido para action=generate)
- `voice` (string): Voz a utilizar (ej: Rachel, Antoni, etc.) - default: 'mateo'

### Optional Parameters
- `category` (string): Categoría del jingle para organización (default: 'sin_categoria')
- `options` (object): Objeto con todas las opciones de audio:

### Options Object Parameters
Todos los parámetros de audio deben enviarse dentro del objeto `options`:
- `music_file` (string): Archivo de música de fondo
- `music_volume` (float): Volumen de música 0.0-1.0 (default: 0.3)
- `voice_volume` (float): Volumen de voz 0.0-1.0 (default: 1.0)
- `fade_in` (integer): Segundos de fade in (default: 2)
- `fade_out` (integer): Segundos de fade out (default: 2)
- `music_duck` (boolean): Reducir música cuando habla (default: true)
- `duck_level` (float): Nivel de ducking 0.0-1.0 (default: 0.2)
- `intro_silence` (integer): Silencio antes del mensaje en segundos (default: 1)
- `outro_silence` (integer): Silencio después del mensaje en segundos (default: 1)
- `voice_settings` (object): Configuraciones de voz para TTS

### Advanced Parameters
- `output_format` (string): Formato de salida (mp3, wav) (default: mp3)
- `bitrate` (string): Bitrate del audio (128k, 192k, 320k) (default: 192k)
- `normalize` (boolean): Normalizar niveles de audio (default: true)
- `compression` (object): Configuración de compresión
  - `threshold` (float): Umbral en dB (default: -20)
  - `ratio` (float): Radio de compresión (default: 4)
  - `attack` (float): Tiempo de ataque en ms (default: 5)
  - `release` (float): Tiempo de release en ms (default: 50)
</parameters>

<example_request>
```bash
# Jingle completo con música y efectos
curl -X POST "http://localhost:4000/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "text": "Casa Costanera, tu destino de compras favorito. Visítanos de lunes a domingo.",
    "voice": "Rachel",
    "category": "promociones",
    "options": {
      "music_file": "Cool.mp3",
      "music_volume": 0.25,
      "voice_volume": 1.0,
      "fade_in": 3,
      "fade_out": 4,
      "music_duck": true,
      "duck_level": 0.15,
      "intro_silence": 2,
      "outro_silence": 2,
      "voice_settings": {
        "stability": 0.5,
        "similarity_boost": 0.75,
        "style": 0.3,
        "use_speaker_boost": true
      }
    }
  }'
```
</example_request>

<example_response>
```json
{
  "success": true,
  "audio": "base64_encoded_audio_data_here...",
  "format": "mp3",
  "duration": 15.5,
  "filename": "jingle_20250112_180000_Rachel.mp3"
}
```

**Nota**: El audio se devuelve como base64 en el campo `audio`, no como una URL.
El archivo también se guarda automáticamente en la base de datos y aparece en los mensajes recientes.
</example_response>

<music_library>
## Available Music Files

### Commercial/Upbeat
- `upbeat_commercial.mp3`: Música alegre comercial (120 BPM)
- `corporate_inspire.mp3`: Corporativo inspiracional (100 BPM)
- `retail_energy.mp3`: Energético para retail (128 BPM)
- `happy_shopping.mp3`: Compras felices (115 BPM)

### Seasonal
- `christmas_bells.mp3`: Navidad con campanas
- `summer_vibes.mp3`: Vibras veraniegas
- `autumn_warm.mp3`: Otoño cálido
- `spring_fresh.mp3`: Primavera fresca

### Ambient/Background
- `soft_ambient.mp3`: Ambiente suave
- `minimal_tech.mp3`: Tecnológico minimalista
- `elegant_piano.mp3`: Piano elegante
- `smooth_jazz.mp3`: Jazz suave

### Get Music List
```bash
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list_music",
    "client_id": "casa"
  }'
```
</music_library>

<ducking_explained>
## Audio Ducking System

El sistema de ducking reduce automáticamente el volumen de la música cuando la voz está presente:

### How it Works
1. **Detection**: Sistema detecta cuando hay voz presente
2. **Reduction**: Música baja a `music_volume * duck_level`
3. **Recovery**: Música vuelve gradualmente al volumen original

### Example Settings
```json
{
  "music_volume": 0.5,      // Volumen base de música: 50%
  "music_duck": true,       // Activar ducking
  "duck_level": 0.3         // Durante voz: música a 15% (0.5 * 0.3)
}
```

### Visual Representation
```
Music: ████████░░░░░░░░████████
Voice:         ████████
Time:  -------|---------|-------
       Normal  Ducked    Normal
```
</ducking_explained>

<advanced_examples>
## Advanced Use Cases

### Radio Station ID
```bash
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "client_id": "casa",
    "text": "Casa Costanera Radio, 24 horas contigo",
    "voice": "Antoni",
    "music_file": "station_id.mp3",
    "music_volume": 0.4,
    "voice_volume": 1.0,
    "fade_in": 0.5,
    "fade_out": 1,
    "intro_silence": 0.5,
    "outro_silence": 0.5,
    "compression": {
      "threshold": -15,
      "ratio": 6,
      "attack": 2,
      "release": 30
    }
  }'
```

### Event Announcement with Echo
```bash
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "client_id": "casa",
    "text": "Gran evento este sábado. No te lo pierdas.",
    "voice": "Domi",
    "music_file": "event_epic.mp3",
    "effects": {
      "echo": {
        "delay": 0.3,
        "decay": 0.4,
        "mix": 0.2
      },
      "reverb": {
        "room_size": 0.7,
        "damping": 0.5,
        "mix": 0.15
      }
    }
  }'
```

### Whisper Announcement
```bash
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "client_id": "casa",
    "text": "Ofertas exclusivas solo para ti",
    "voice": "Bella",
    "voice_settings": {
      "stability": 0.8,
      "similarity_boost": 0.4,
      "style": 0.9,
      "use_speaker_boost": false
    },
    "music_file": "mysterious.mp3",
    "music_volume": 0.15,
    "voice_volume": 0.8
  }'
```
</advanced_examples>

<batch_processing>
## Batch Jingle Generation

### Generate Multiple Jingles
```javascript
const jingles = [
  {
    text: "Buenos días, bienvenidos",
    music: "morning_fresh.mp3",
    schedule: "09:00"
  },
  {
    text: "Hora del almuerzo en nuestro patio de comidas",
    music: "lunch_time.mp3",
    schedule: "12:00"
  },
  {
    text: "Gracias por visitarnos, hasta mañana",
    music: "closing_time.mp3",
    schedule: "21:00"
  }
];

async function generateJingleBatch(jingles) {
  const results = [];
  
  for (const jingle of jingles) {
    const response = await fetch('/src/api/jingle-service.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'generate',
        client_id: 'casa',
        text: jingle.text,
        voice: 'Rachel',
        music_file: jingle.music,
        music_volume: 0.3,
        fade_in: 2,
        fade_out: 3
      })
    });
    
    const result = await response.json();
    results.push({
      ...result,
      schedule: jingle.schedule
    });
  }
  
  return results;
}
```
</batch_processing>

<presets>
## Jingle Presets

### Preset Configurations
```json
{
  "commercial_standard": {
    "music_volume": 0.3,
    "voice_volume": 1.0,
    "fade_in": 2,
    "fade_out": 2,
    "duck_level": 0.2,
    "intro_silence": 1,
    "outro_silence": 1
  },
  "dramatic_announcement": {
    "music_volume": 0.5,
    "voice_volume": 1.0,
    "fade_in": 4,
    "fade_out": 5,
    "duck_level": 0.15,
    "intro_silence": 2,
    "outro_silence": 3
  },
  "quick_promo": {
    "music_volume": 0.25,
    "voice_volume": 1.0,
    "fade_in": 0.5,
    "fade_out": 1,
    "duck_level": 0.3,
    "intro_silence": 0.5,
    "outro_silence": 0.5
  },
  "background_info": {
    "music_volume": 0.15,
    "voice_volume": 0.9,
    "fade_in": 3,
    "fade_out": 3,
    "duck_level": 0.5,
    "intro_silence": 1,
    "outro_silence": 2
  }
}
```

### Using Presets
```bash
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "client_id": "casa",
    "text": "Grandes ofertas este fin de semana",
    "voice": "Rachel",
    "music_file": "promo.mp3",
    "preset": "commercial_standard"
  }'
```
</presets>

<error_handling>
## Error Handling

### Music File Not Found
```json
{
  "success": false,
  "error": "Archivo de música no encontrado",
  "code": "MUSIC_NOT_FOUND",
  "details": {
    "requested_file": "missing.mp3",
    "available_files": ["upbeat_commercial.mp3", "corporate_inspire.mp3"]
  }
}
```

### Invalid Volume Settings
```json
{
  "success": false,
  "error": "Configuración de volumen inválida",
  "code": "INVALID_VOLUME",
  "details": {
    "music_volume": "Debe estar entre 0.0 y 1.0",
    "voice_volume": "Debe estar entre 0.0 y 1.0"
  }
}
```

### Processing Error
```json
{
  "success": false,
  "error": "Error procesando audio",
  "code": "PROCESSING_ERROR",
  "details": {
    "ffmpeg_error": "Invalid audio stream",
    "command": "ffmpeg -i input.mp3 ..."
  }
}
```
</error_handling>