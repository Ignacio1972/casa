# MediaFlow - Sistema de Radio Automatizada

> Sistema completo de generación, gestión y programación de audio TTS para radio automatizada con IA.

**Stack**: PHP 8.1 • SQLite • JavaScript ES6+ • nginx • FFmpeg
**Puerto**: 2082 • **Cliente**: Casa Costanera • **Versión**: 1.0.0

---

## 🚀 Quick Start

### Generar Audio TTS
```bash
# Generación básica
curl -X POST "http://localhost:2082/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate_audio",
    "text": "Bienvenidos a Casa Costanera",
    "voice": "G4IAP30yc6c1gK0csDfu",
    "category": "informativos"
  }'

# Con configuración de voz personalizada
curl -X POST "http://localhost:2082/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate_audio",
    "text": "Gran oferta del día",
    "voice": "G4IAP30yc6c1gK0csDfu",
    "category": "ofertas",
    "voice_settings": {
      "style": 0.15,
      "stability": 1.0,
      "similarity_boost": 0.5,
      "use_speaker_boost": true
    }
  }'
```

### Generar Jingle con Música
```bash
curl -X POST "http://localhost:2082/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "text": "Visítanos hoy en Casa Costanera",
    "voice": "G4IAP30yc6c1gK0csDfu",
    "music_file": "Uplift.mp3",
    "music_volume": 1.65,
    "voice_volume": 2.8,
    "ducking_enabled": true
  }'
```

### Programar Emisión Automática
```bash
# Cada 2 horas, de lunes a viernes, de 9 AM a 6 PM
curl -X POST "http://localhost:2082/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create_schedule",
    "filename": "mensaje_promocion_20251121_143022.mp3",
    "title": "Promoción del día",
    "schedule_time": ["09:00", "18:00"],
    "schedule_days": [1,2,3,4,5],
    "notes": {"type":"interval","interval_hours":2}
  }'
```

### Enviar a Radio en Vivo
```bash
curl -X POST "http://localhost:2082/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "send_to_radio",
    "filename": "mensaje_promocion_20251121_143022.mp3"
  }'
```

---

## 📁 Estructura del Proyecto

```
/var/www/casa/
├── config/                           # Configuraciones del sistema
│   ├── app.config.php               # Config principal (puerto, timezone)
│   ├── database.php                 # Configuración SQLite
│   └── clients/
│       └── casa.config.php          # Config específica del cliente
│
├── database/                         # Base de datos SQLite
│   ├── casa.db                      # BD principal (audio_metadata, schedules)
│   ├── ai_suggestions.db            # BD de sugerencias IA
│   └── migrations/                  # Scripts de migración
│
├── src/
│   ├── api/                         # Backend PHP (45+ servicios)
│   │   ├── generate.php             # ⭐ Generación TTS principal
│   │   ├── jingle-service.php       # ⭐ Jingles con música
│   │   ├── claude-service.php       # ⭐ Sugerencias con IA
│   │   ├── radio-service.php        # ⭐ Integración AzuraCast
│   │   ├── scheduler-cron.php       # ⭐ Programador automático
│   │   ├── biblioteca.php           # Gestión de biblioteca
│   │   ├── audio-scheduler.php      # API de programación
│   │   ├── saved-messages.php       # Mensajes guardados
│   │   ├── ducking-service.php      # Audio sobre música
│   │   ├── music-manager-service.php # Gestión de música
│   │   ├── tts-config-service.php   # Config TTS dinámica
│   │   ├── jingle-config-service.php # Config jingles dinámica
│   │   ├── calendar-service.php     # Gestión de eventos
│   │   │
│   │   ├── services/                # Servicios compartidos
│   │   │   ├── radio-service.php    # Funciones AzuraCast
│   │   │   └── tts-service.php      # Funciones TTS
│   │   │
│   │   ├── v2/services/             # Servicios v2
│   │   │   └── AudioProcessor.php   # ⭐ Normalización LUFS profesional
│   │   │
│   │   ├── data/                    # Configuraciones JSON
│   │   │   ├── voices-config.json   # ⭐ Voces TTS (dinámico)
│   │   │   ├── tts-config.json      # ⭐ Config TTS global
│   │   │   ├── jingle-config.json   # ⭐ Config jingles global
│   │   │   └── clients-config.json  # Multi-cliente IA
│   │   │
│   │   ├── temp/                    # Archivos TTS temporales
│   │   ├── logs/                    # Logs diarios por servicio
│   │   └── v2/logs/                 # Logs v2 (JSON Lines)
│   │
│   ├── modules/                     # Módulos frontend (SPA)
│   │   ├── dashboard/               # ⭐ Dashboard principal
│   │   │   ├── index.js             # Módulo principal
│   │   │   ├── components/          # Componentes (AI, jingle, voice)
│   │   │   └── services/            # Servicios (messages, quota)
│   │   │
│   │   ├── campaigns/               # ⭐ Biblioteca de mensajes
│   │   │   ├── index.js             # Lista/grid de mensajes
│   │   │   ├── services/            # Actions, upload manager
│   │   │   └── plugins/             # Scheduler plugin
│   │   │
│   │   ├── calendar/                # ⭐ Calendario y programación
│   │   │   ├── index.js             # Vista calendario
│   │   │   ├── components/          # Calendar view, schedule list
│   │   │   └── services/            # API, state management
│   │   │
│   │   └── automatic/               # ⭐ Modo automático IA
│   │       └── index.js             # Generación automática
│   │
│   └── core/                        # Servicios JS compartidos
│       ├── event-bus.js             # Event bus global
│       ├── router.js                # SPA router
│       └── api-client.js            # Cliente HTTP
│
├── public/
│   ├── index.html                   # ⭐ Aplicación principal (SPA)
│   │
│   ├── audio/music/                 # Música de fondo (6 tracks)
│   │   ├── Cool.mp3                 # Fondo cool/relajado
│   │   ├── Kids.mp3                 # Infantil/alegre
│   │   ├── Pop.mp3                  # Pop moderno
│   │   ├── Slow.mp3                 # Lento/emocional
│   │   ├── Smooth.mp3               # Suave/profesional
│   │   └── Uplift.mp3               # ⭐ Default - Energético
│   │
│   ├── playground/                  # ⭐ Herramientas de administración
│   │   ├── index.html               # Dashboard playground
│   │   ├── jingle-config.html       # ⭐ Configurador de jingles
│   │   ├── tts-config.html          # ⭐ Configurador TTS
│   │   ├── test-voice-admin.html    # ⭐ Administrador de voces
│   │   ├── music-manager.html       # ⭐ Gestor de música
│   │   ├── jingle-studio.html       # Estudio de jingles avanzado
│   │   ├── claude.html              # Configurador Claude AI
│   │   └── test-integration.html    # Tests de integración
│   │
│   └── styles-v5/                   # Sistema CSS modular
│       ├── core.css                 # Estilos base
│       ├── dashboard.css            # Dashboard
│       └── components.css           # Componentes
│
├── docs/                            # 📚 Documentación técnica (40+ archivos)
│   ├── endpoints/                   # Documentación de APIs
│   ├── workflows/                   # Flujos de trabajo
│   ├── technical/                   # Docs técnicas
│   └── schemas/                     # Esquemas de BD
│
└── scripts/                         # Scripts de utilidad
    └── cleanup-old-files.php        # Limpieza automática
```

---

## 🎯 Servicios Backend (APIs PHP)

### 1. generate.php - Generación TTS Principal

**Endpoints**:
- `list_voices` - Lista voces activas desde voices-config.json
- `generate_audio` - Genera audio TTS con ElevenLabs
- `send_to_radio` - Envía audio a AzuraCast vía interrupción

**Características**:
- Integración con ElevenLabs API v1
- Procesamiento con FFmpeg (silencios intro/outro)
- Normalización LUFS opcional (AudioProcessor v2)
- Upload automático a AzuraCast vía docker cp
- Guardado de metadata en SQLite
- Ajuste de volumen por voz (volume_adjustment)

**Voces Disponibles** (desde `/src/api/data/voices-config.json`):
- **juan_carlos** (G4IAP30yc6c1gK0csDfu) - Masculino, Default ⭐
- **yorman/Mario** (J2Jb9yZNvpXUNAL3a2bw) - Masculino
- **veronica/Francisca** (Obg6KIFo8Md4PUo1m2mR) - Femenino, +7dB
- **cristian/Jose Miguel** (nNS8uylvF9GBWVSiIt5h) - Masculino, +0.5dB
- **sandra/Titi** (rEVYTKPqwSMhytFPayIb) - Femenino, -0.5dB

**Categorías Soportadas**:
- ofertas (rojo)
- eventos (morado)
- informativos (azul)
- servicios (azul claro)
- horarios (verde azulado)
- emergencias (naranja)
- sin_categoria (gris)

### 2. jingle-service.php - Generación de Jingles con Música

**Endpoints**:
- `generate` - Crea jingle con música de fondo
- `list_music` - Lista música disponible
- `send_to_radio` - Envía jingle a emisión

**Características Avanzadas**:
- **Ducking Automático**: sidechaincompress con FFmpeg
- **Normalización LUFS**: Opcional (target -14 LUFS para jingles)
- **Configuración Remota**: jingle-config.json editable en tiempo real
- **Fades**: Fade in/out configurables
- **Silencios**: Intro/outro ajustables
- **Volumes**: Música y voz independientes

**Parámetros** (todos opcionales, usa jingle-config.json como default):
```json
{
  "music_file": "Uplift.mp3",
  "music_volume": 1.65,
  "voice_volume": 2.8,
  "fade_in": 1.5,
  "fade_out": 4.5,
  "music_duck": true,
  "duck_level": 0.95,
  "intro_silence": 7,
  "outro_silence": 4.5
}
```

### 3. claude-service.php - Sugerencias con IA

**Modelos Disponibles**:
- claude-3-haiku-20240307 (rápido, económico)
- claude-3-5-haiku-20241022 (mejorado)
- claude-3-7-sonnet-20250219 (balance)
- **claude-sonnet-4-20250514** (avanzado - DEFAULT) ⭐
- claude-opus-4-1-20250805 (superior)

**Funcionalidades**:
- Generación de 2 sugerencias (modo normal)
- Generación de 1 sugerencia con límite de palabras (modo automático)
- Multi-cliente (contextos personalizados)
- Tonos configurables: profesional, entusiasta, amigable, urgente, informativo
- Tracking de uso en tabla statistics

**Contexto Multi-Cliente** (clients-config.json):
- Contextos personalizados por cliente
- Selección de modelo por cliente
- Tonos y límites de palabras configurables

### 4. radio-service.php - Integración con AzuraCast

**Funciones Principales**:
- `uploadFileToAzuraCast()` - Upload vía docker cp
- `interruptRadio()` - Interrupción inmediata
- `interruptRadioWithSkip()` - Interrupción + skip automático
- `skipSongNow()` - Skip manual

**Método de Upload** (docker cp es más confiable que API):
```bash
docker cp [local] azuracast:/var/azuracast/stations/mediaflow/media/Grabaciones/
docker exec azuracast chown azuracast:azuracast [path]
```

**Sistema de Interrupción** (vía socket UNIX):
```bash
echo "interrupting_requests.push file://[path]" | \
socat - UNIX-CONNECT:/var/azuracast/stations/mediaflow/config/liquidsoap.sock
```

### 5. scheduler-cron.php - Programador Automático

**Ejecución**: Cada minuto vía cron

**Tipos de Programación**:
1. **Interval**: Cada N horas/minutos (con rango horario opcional)
2. **Specific**: Horas específicas en días específicos
3. **Once**: Ejecución única

**Características**:
- Verificación de existencia de archivos en Docker
- Detección de duración con ffprobe
- Log de ejecuciones en audio_schedule_log
- Manejo de rangos horarios (ej: solo entre 9 AM - 6 PM)
- Sistema de locks para evitar duplicados

**Ejemplo de Configuración** (campo notes):
```json
{
  "type": "interval",
  "interval_hours": 2,
  "interval_minutes": 0
}
```

### 6. biblioteca.php - Gestión de Biblioteca

**Endpoints GET**:
- `?filename=X` - Stream/download de audio
- `?action=download&filename=X` - Descarga forzada

**Endpoints POST**:
- `list_library` - Lista archivos (últimos 50)
- `delete_library_file` - Elimina archivo
- `send_library_to_radio` - Envía a emisión
- `rename_file` - Renombra con descripción
- `uploadExternalFile` - **Nuevo**: Upload de archivos externos

**Upload de Archivos Externos** (Nuevo Feature):
- **Formatos**: MP3, WAV, FLAC, AAC, Ogg, M4A, Opus
- **Tamaño máximo**: 50MB
- **Validación multi-nivel**:
  1. Extensión de archivo
  2. MIME type
  3. Magic bytes (primeros bytes del archivo)
  4. ffprobe (codec detection)
  5. ffmpeg (validación de integridad)
- **Guardado**: audio_metadata con source='upload'
- **Tags automáticos**: categoria, descripción, metadata

### 7. music-manager-service.php - Gestión de Música

**Nuevo Servicio** (2024-11) para administrar música de fondo.

**Endpoints**:
- `list` - Lista MP3/WAV con metadatos completos
- `upload` - Sube nuevos archivos de música
- `delete` - Elimina archivo
- `validate` - Valida integridad con ffmpeg
- `restart` - Limpia cachés PHP opcache

**Validaciones de Upload**:
1. Extensión (.mp3 o .wav)
2. MIME type (audio/mpeg, audio/wav, etc.)
3. Magic bytes (ID3 para MP3, RIFF/WAVE para WAV)
4. ffprobe si MIME es genérico
5. ffmpeg para validación de integridad
6. Tamaño máximo: 50MB

### 8. AudioProcessor.php (v2) - Normalización LUFS Profesional

**Nuevo Servicio v2** con normalización EBU R128.

**Características**:
- Normalización LUFS two-pass (máxima precisión)
- Perfiles predefinidos (message, jingle, emergency)
- Ajustes dinámicos por voz (volume_adjustment)
- Logging estructurado (JSON Lines)

**Perfiles de Audio**:
```php
'message' => [
    'target_lufs' => -16,
    'target_tp' => -1.5,
    'target_lra' => 7
],
'jingle' => [
    'target_lufs' => -14,
    'target_tp' => -1.5,
    'target_lra' => 10
],
'emergency' => [
    'target_lufs' => -12,
    'target_tp' => -1.0,
    'target_lra' => 5
]
```

**Uso**:
```php
use App\Services\AudioProcessor;
$processor = new AudioProcessor();
$result = $processor->normalizeToTarget($input, $output, -16);
```

### 9. ducking-service.php - Audio sobre Música

Reproduce TTS sobre música sin detenerla.

**Características**:
- Cola `tts_ducking_queue` en Liquidsoap
- Limpieza automática de archivos >24h
- Test mode incluido

### 10. audio-scheduler.php - API de Programación

**Acciones**:
- `create_schedule` - Crea nueva programación
- `list_schedules` - Lista programaciones activas
- `update_schedule` - Actualiza programación
- `delete_schedule` - Elimina programación
- `toggle_schedule` - Activa/desactiva
- `update_category_by_filename` - Sincroniza categoría

---

## 🎨 Módulos Frontend (JavaScript ES6+)

### 1. Dashboard Module (`/src/modules/dashboard/`)

**Pantalla Principal** de generación de audio.

**Componentes**:
- **message-generator.js** - Generador de mensajes con textarea
- **ai-suggestions.js** - Sugerencias con Claude AI
- **voice-controls.js** - Selector de voz y voice settings
- **jingle-controls.js** - Configuración de jingles
- **recent-messages.js** - Lista de últimos 10 mensajes

**Funcionalidades Principales**:
1. **Generación de Audio TTS**
   - Selector de voz dinámico (desde voices-config.json)
   - Controles de voice settings (style, stability, similarity)
   - Toggle "Valores por Defecto" (15% style, 100% stability, 50% similarity)
   - Selector de categoría con badges de colores

2. **Integración con Jingles**
   - Selector de música de fondo
   - Configuración automática desde jingle-config.json
   - Preview de audio generado

3. **Sugerencias con IA**
   - Generación con Claude AI
   - Selección de tono
   - Inserción automática en campo de texto

4. **Mensajes Recientes**
   - Play/pause inline
   - Guardar en biblioteca
   - Enviar a radio
   - Archivar (soft delete)

5. **Player de Audio**
   - Reproductor integrado
   - Botones "Guardar en Biblioteca" y "Enviar a la Radio"
   - Sincronización de estado

**Event Bus**:
- `module:loaded`
- `message:saved:library`
- `llm:suggestion:selected`

### 2. Campaigns Module (`/src/modules/campaigns/`)

**Biblioteca de Mensajes Guardados** (is_saved=1).

**Estructura**:
- `index.js` - Módulo principal con grid de cards
- `services/message-actions.js` - Acciones sobre mensajes
- `services/file-upload-manager.js` - **Nuevo**: Upload de archivos
- `plugins/scheduler-plugin.js` - Integración con calendario

**Funcionalidades**:

1. **Grid de Mensajes**
   - Cards responsivos con preview
   - Filtrado por categoría
   - Búsqueda expandible (estilo Apple)
   - Ordenamiento por fecha (asc/desc)

2. **Acciones sobre Mensajes**
   - Play/pause con player flotante
   - Editar título
   - Cambiar categoría (dropdown inline) ⭐
   - Programar (integración con calendario)
   - Enviar a radio
   - Eliminar (soft delete)

3. **Selección Múltiple** ⭐ Nuevo
   - Modo de selección con checkboxes
   - Eliminación masiva
   - Contador de seleccionados
   - Visual feedback (borde azul)

4. **Upload de Archivos Externos** ⭐ Nuevo
   - Drag & drop o click to upload
   - Progress modal con indicador de velocidad
   - Formatos: MP3, WAV, FLAC, AAC, Ogg, M4A, Opus
   - Máximo 50MB
   - Validación exhaustiva

5. **Búsqueda Expandible** ⭐
   - Estilo Apple Search Bar
   - Expand/collapse animado
   - Búsqueda en tiempo real
   - Colapso automático si vacío

### 3. Calendar Module (`/src/modules/calendar/`)

**Calendario y Programación Automática**.

**Componentes**:
- `components/calendar-view.js` - Vista mensual
- `components/schedules-list.js` - Lista de programaciones
- `components/event-list.js` - Lista de eventos
- `components/schedule-actions.js` - Acciones

**Funcionalidades**:

1. **Vista de Calendario**
   - Vista mensual interactiva
   - Eventos coloreados por categoría
   - Tooltip con detalles al hover
   - Navegación mes a mes

2. **Programación de Eventos**
   - Creación de schedules (interval/specific/once)
   - Selección de días de la semana
   - Múltiples horarios por día
   - **Rango horario opcional** ⭐ Nuevo (ej: solo de 9 AM a 6 PM)
   - Rango de fechas (start_date - end_date)

3. **Gestión de Schedules**
   - Lista completa con filtros
   - Activar/desactivar toggle
   - Editar programación
   - Duplicar schedule
   - Eliminar

4. **Historial de Ejecuciones**
   - Tabla de ejecuciones pasadas
   - Estado (success/error)
   - Mensajes de error

### 4. Automatic Module (`/src/modules/automatic/`)

**Modo Automático** de generación continua con IA.

**Funcionalidades**:
- Generación periódica automática
- Selección de intervalo
- Límite de palabras configurable
- Monitoreo de uso
- Rate limiting integrado

---

## 🛠️ Playground - Herramientas de Administración

**Ubicación**: `/public/playground/`

### 1. index.html - Dashboard del Playground

**Descripción**: Punto de entrada con links a todas las herramientas.

### 2. jingle-config.html ⭐ - Configurador de Jingles

**Funcionalidades**:
- Ajuste de todos los parámetros de jingle
- Preview en tiempo real
- Guardado de configuración remota (jingle-config.json)
- Prueba de diferentes músicas
- Visualización de forma de onda

**Parámetros Editables**:
- intro_silence, outro_silence
- music_volume, voice_volume
- fade_in, fade_out
- ducking_enabled, duck_level
- default_music
- normalization_settings
- compressor_settings

### 3. tts-config.html ⭐ - Configurador TTS

**Funcionalidades**:
- Ajuste de voice_settings globales
- Configuración de silencios (intro/outro)
- Normalización LUFS (enabled, target_lufs, output_volume)
- Guardado en tts-config.json

**Parámetros Editables**:
- voice_settings (style, stability, similarity_boost, use_speaker_boost)
- silence (add_silence, intro_seconds, outro_seconds)
- normalization (enabled, target_lufs, output_volume, enable_compression)

### 4. test-voice-admin.html ⭐ - Administrador de Voces

**Funcionalidades**:
- Activar/desactivar voces
- Cambiar orden de aparición
- Establecer voz por defecto
- Ajustar volume_adjustment por voz (-∞ a +∞ dB)
- Vista previa de voces
- Guardado en voices-config.json

### 5. music-manager.html ⭐ - Gestor de Música

**Funcionalidades**:
- Upload de archivos MP3/WAV
- Validación de audio (extensión, MIME, magic bytes, ffprobe, ffmpeg)
- Lista de música con metadatos (duración, tamaño, bitrate)
- Preview inline
- Eliminación con confirmación

### 6. jingle-studio.html - Estudio Avanzado de Jingles

**Funcionalidades**:
- Creación de jingles complejos
- Múltiples capas de audio
- Efectos avanzados
- Export en diferentes formatos

### 7. claude.html - Configurador de Claude AI

**Funcionalidades**:
- Selección de modelo
- Configuración de contexto por cliente
- Ajuste de tonos
- Prueba de prompts
- Visualización de costos

### 8. test-integration.html - Tests de Integración

**Funcionalidades**:
- Test de endpoints
- Validación de flujos completos
- Debugging de APIs

---

## 📊 Base de Datos (SQLite)

### Tabla: audio_metadata

**Descripción**: Metadata de todos los archivos de audio.

**Campos Principales**:
- `filename` - Nombre único del archivo
- `display_name` - Título visible
- `description` - Descripción generada
- `category` - Categoría (ofertas, eventos, etc.)
- `voice_id`, `voice_name` - Voz utilizada
- `is_saved` - Si está en biblioteca (0/1)
- `is_active` - Soft delete (0/1)
- `source` - 'tts' o 'upload'
- `play_count` - Veces reproducido
- `radio_sent_count` - Veces enviado a radio
- `client_id` - Cliente multi-tenant

**Índices**:
```sql
CREATE INDEX idx_audio_metadata_category ON audio_metadata(category);
CREATE INDEX idx_audio_metadata_is_saved ON audio_metadata(is_saved);
CREATE INDEX idx_audio_metadata_created_at ON audio_metadata(created_at);
```

### Tabla: audio_schedule

**Descripción**: Programaciones automáticas.

**Campos Principales**:
- `filename` - Archivo a reproducir
- `title` - Título del schedule
- `schedule_time` - JSON: ["09:00"] o ["09:00","18:00"]
- `schedule_days` - JSON: [1,2,3,4,5] (1=Lun, 7=Dom)
- `is_active` - Activo/inactivo
- `notes` - JSON con type, interval_hours, etc.
- `category` - Categoría (sincronizada con audio_metadata)

**Ejemplo de notes**:
```json
{
  "type": "interval",
  "interval_hours": 2,
  "interval_minutes": 0
}
```

### Tabla: audio_schedule_log

**Descripción**: Historial de ejecuciones.

**Campos**:
- `schedule_id` - FK a audio_schedule
- `executed_at` - Timestamp de ejecución
- `status` - 'success' o 'error'
- `message` - Mensaje de error (si aplica)

### Tabla: calendar_events

**Descripción**: Eventos únicos del calendario.

**Campos Principales**:
- `title` - Título del evento
- `file_path` - Ruta del archivo
- `category` - Categoría
- `start_datetime` - Fecha y hora de inicio
- `status` - pending, playing, completed, error
- `priority` - Prioridad (1-10)

### Tabla: statistics

**Descripción**: Métricas y estadísticas del sistema.

**Campos**:
- `date` - Fecha de la métrica
- `metric_name` - Nombre (claude_generations, tts_usage, etc.)
- `metric_value` - Valor numérico
- `metadata` - JSON con datos adicionales
- `client_id` - Cliente

### Tabla: automatic_usage_tracking

**Descripción**: Tracking de uso del modo automático.

**Campos**:
- `client_id`, `access_token` - Identificación
- `ip_address`, `user_agent` - Info de cliente
- `audio_text`, `voice_used`, `music_file` - Parámetros
- `duration_seconds` - Duración del audio
- `success` - Si fue exitoso
- `session_id` - ID de sesión

---

## 🔌 Integraciones Externas

### 1. ElevenLabs TTS API

**Configuración**:
```php
ELEVENLABS_API_KEY=sk_f5d2f711a5cb2c117a2c6e2a00ab50bf34dbaec234bc61b2
ELEVENLABS_BASE_URL=https://api.elevenlabs.io/v1
ELEVENLABS_MODEL_ID=eleven_multilingual_v2
```

**Endpoint**:
```
POST https://api.elevenlabs.io/v1/text-to-speech/{voice_id}
```

**Costo**: ~$0.30 por 1,000 caracteres

### 2. Claude AI API

**Configuración**:
```php
CLAUDE_MODEL=claude-sonnet-4-20250514  // Configurable
CLAUDE_MAX_TOKENS=500
```

**Endpoint**:
```
POST https://api.anthropic.com/v1/messages
```

**Headers**:
```
x-api-key: [API_KEY]
anthropic-version: 2023-06-01
```

### 3. AzuraCast Radio Platform

**Configuración**:
```php
AZURACAST_BASE_URL=http://51.222.25.222
AZURACAST_API_KEY=c3802cba5b5e61e8:...
AZURACAST_STATION_ID=1
AZURACAST_MEDIA_PATH=/var/azuracast/stations/mediaflow/media/Grabaciones/
```

**Métodos de Integración**:
1. **Docker CP** - Upload de archivos (método preferido)
2. **Socket UNIX** - Comandos a Liquidsoap
3. **API REST** - Gestión de playlists (opcional)

**Arquitectura Docker**:
- Contenedor: `azuracast`
- Station: `mediaflow`
- Socket: `/var/azuracast/stations/mediaflow/config/liquidsoap.sock`

### 4. FFmpeg

**Usos en el Sistema**:
1. **Silencios**: `adelay`, `apad`
2. **Normalización LUFS**: `loudnorm` (two-pass)
3. **Ducking**: `sidechaincompress`
4. **Detección de duración**: `ffprobe`
5. **Validación**: `ffmpeg -f null`

---

## 🛠️ Comandos Útiles

### Base de Datos

```bash
# Ver mensajes recientes
sqlite3 database/casa.db "
  SELECT filename, display_name, category, created_at
  FROM audio_metadata
  ORDER BY created_at DESC
  LIMIT 10;
"

# Ver schedules activos
sqlite3 database/casa.db "
  SELECT id, title, schedule_time, schedule_days, is_active
  FROM audio_schedule
  WHERE is_active = 1;
"

# Ver últimas ejecuciones
sqlite3 database/casa.db "
  SELECT s.title, l.executed_at, l.status
  FROM audio_schedule_log l
  JOIN audio_schedule s ON s.id = l.schedule_id
  ORDER BY l.executed_at DESC
  LIMIT 20;
"

# Estadísticas de Claude AI
sqlite3 database/casa.db "
  SELECT date, metric_value
  FROM statistics
  WHERE metric_name = 'claude_generations'
  ORDER BY date DESC
  LIMIT 30;
"

# Mensajes guardados por categoría
sqlite3 database/casa.db "
  SELECT category, COUNT(*) as total
  FROM audio_metadata
  WHERE is_saved = 1 AND is_active = 1
  GROUP BY category;
"
```

### Logs

```bash
# Ver logs de TTS
tail -f src/api/logs/tts-$(date +%Y-%m-%d).log

# Ver logs de scheduler
tail -f src/api/logs/scheduler-cron.log

# Ver logs de jingle service
tail -f src/api/logs/jingle-service-$(date +%Y-%m-%d).log

# Ver logs de AudioProcessor v2 (JSON Lines)
tail -f src/api/v2/logs/audio-processor.jsonl | jq

# Buscar errores en logs
grep -i "error" src/api/logs/*.log | tail -20
```

### AzuraCast

```bash
# Listar archivos en Grabaciones
sudo docker exec azuracast ls -lah \
  /var/azuracast/stations/mediaflow/media/Grabaciones/

# Verificar existencia de archivo
sudo docker exec azuracast test -f \
  /var/azuracast/stations/mediaflow/media/Grabaciones/mensaje.mp3 \
  && echo "EXISTS" || echo "NOT_FOUND"

# Obtener duración
sudo docker exec azuracast ffprobe -v error \
  -show_entries format=duration -of csv=p=0 \
  /var/azuracast/stations/mediaflow/media/Grabaciones/mensaje.mp3

# Skip manual
echo "playlist_default.skip" | \
sudo docker exec -i azuracast socat - \
  UNIX-CONNECT:/var/azuracast/stations/mediaflow/config/liquidsoap.sock

# Interrupción manual
echo "interrupting_requests.push file:///var/azuracast/stations/mediaflow/media/Grabaciones/mensaje.mp3" | \
sudo docker exec -i azuracast socat - \
  UNIX-CONNECT:/var/azuracast/stations/mediaflow/config/liquidsoap.sock
```

### Limpieza

```bash
# Limpiar archivos temporales >1 hora
find src/api/temp/ -name "*.mp3" -mmin +60 -delete

# Limpiar logs >30 días
find src/api/logs/ -name "*.log" -mtime +30 -delete

# Limpiar ducking antiguos (>1 día)
sudo docker exec azuracast find \
  /var/azuracast/stations/mediaflow/media/Grabaciones/ \
  -name "ducking_*.mp3" -mtime +1 -delete
```

### Servidor

```bash
# Ver estado nginx
systemctl status nginx

# Reiniciar nginx
sudo systemctl restart nginx

# Ver estado PHP-FPM
systemctl status php8.1-fpm

# Reiniciar PHP-FPM
sudo systemctl restart php8.1-fpm

# Ver logs de nginx
tail -f /var/log/nginx/casa-access.log
tail -f /var/log/nginx/casa-error.log
```

---

## ⚙️ Configuración

### Variables de Entorno (.env)

```env
# Configuración General
APP_NAME=MediaFlow
APP_VERSION=1.0.0
APP_ENV=production
APP_PORT=2082
TIMEZONE=America/Santiago

# ElevenLabs TTS
ELEVENLABS_API_KEY=sk_f5d2f711a5cb2c117a2c6e2a00ab50bf34dbaec234bc61b2

# Claude AI
CLAUDE_API_KEY=[TU_CLAVE]
CLAUDE_MODEL=claude-sonnet-4-20250514
CLAUDE_MAX_TOKENS=500

# AzuraCast
AZURACAST_BASE_URL=http://51.222.25.222
AZURACAST_API_KEY=c3802cba5b5e61e8:fed31be9adb82ca57f1cf482d170851f
AZURACAST_STATION_ID=1

# Logs
LOG_LEVEL=INFO
LOG_PATH=/var/www/casa/src/api/logs

# Cache
CACHE_ENABLED=false
```

### Permisos Requeridos

```bash
# Base de datos
chmod 777 database/
chmod 666 database/casa.db

# Temporales
chown -R www-data:www-data src/api/temp/
chmod 755 src/api/temp/

# Logs
chown -R www-data:www-data src/api/logs/
chmod 755 src/api/logs/

# Música
chown -R www-data:www-data public/audio/music/
chmod 755 public/audio/music/
```

### Cron Jobs

```cron
# Scheduler (cada minuto)
* * * * * /usr/bin/php /var/www/casa/src/api/scheduler-cron.php >> /var/www/casa/src/api/logs/scheduler-cron.log 2>&1

# Cleanup (diario a las 3 AM)
0 3 * * * /usr/bin/php /var/www/casa/scripts/cleanup-old-files.php
```

---

## 🎯 Flujos de Trabajo Principales

### 1. Generación de Audio TTS Simple

```
Usuario en Dashboard
  ↓
Ingresa texto + selecciona voz + categoría
  ↓
Click "Generar Audio"
  ↓
POST a /api/generate.php
  ↓
ElevenLabs API → genera MP3
  ↓
FFmpeg → agrega silencios
  ↓
Opcional: AudioProcessor v2 → normaliza LUFS
  ↓
docker cp → sube a AzuraCast
  ↓
INSERT en audio_metadata
  ↓
Retorna filename + audio base64
  ↓
Dashboard muestra player con opciones
```

### 2. Generación de Jingle con Música

```
Usuario selecciona música en Dashboard
  ↓
Ingresa texto
  ↓
Click "Generar Audio"
  ↓
POST a /api/jingle-service.php
  ↓
1. Genera TTS con ElevenLabs
2. Opcional: normaliza TTS
3. FFmpeg mezcla música + voz con ducking
  ↓
docker cp → sube a AzuraCast
  ↓
INSERT en audio_metadata
  ↓
Retorna jingle completo
```

### 3. Programación Automática

```
Usuario en Calendar
  ↓
Selecciona mensaje de biblioteca
  ↓
Click "Programar"
  ↓
Modal: configura tipo, días, horas, rango
  ↓
POST a /api/audio-scheduler.php
  ↓
INSERT en audio_schedule
  ↓
Cron cada minuto:
  ↓
scheduler-cron.php
  ↓
Verifica schedules activos
  ↓
Calcula si debe ejecutar
  ↓
interruptRadioWithSkip()
  ↓
INSERT en audio_schedule_log
```

### 4. Envío a Radio en Vivo

```
Usuario click "Enviar a Radio"
  ↓
Confirmación
  ↓
POST a /api/generate.php (send_to_radio)
  ↓
Verifica archivo existe en AzuraCast
  ↓
Envía comando a Liquidsoap socket
  ↓
Radio interrumpe y reproduce mensaje
  ↓
UPDATE radio_sent_count en audio_metadata
```

---

## 💡 Tips y Mejores Prácticas

### Generación de Audio

1. **Usa "Valores por Defecto"** para mensajes estándar (15% style, 100% stability)
2. **Ajusta volume_adjustment** por voz en voice-admin.html
3. **Normalización LUFS** es opcional pero recomendada para consistencia
4. **Silencios**: 3s intro + 5s outro es óptimo para la mayoría de casos

### Jingles

1. **Música Default**: Uplift.mp3 es la más versátil
2. **Ducking**: Mantén duck_level en 0.95 para balance óptimo
3. **Volumes**: music_volume=1.65, voice_volume=2.8 son valores probados
4. **Normalización**: -14 LUFS para jingles (vs -16 para mensajes)

### Programación

1. **Interval con rango horario** es ideal para mensajes corporativos
2. **Prioridad**: Usa 1-3 para urgentes, 5 para normales, 7-10 para opcionales
3. **End date**: Siempre configúralo para promociones temporales
4. **Historial**: Revisa audio_schedule_log para debugging

### Biblioteca

1. **Categorías**: Asigna correctamente para mejor organización
2. **Upload externos**: Valida siempre antes de enviar a radio
3. **Búsqueda**: Usa la barra expandible para filtrar rápido
4. **Selección múltiple**: Útil para limpiar mensajes antiguos

### Playground

1. **jingle-config.html**: Ajusta configuración global una vez
2. **voice-admin.html**: Activa solo las voces que realmente usas
3. **music-manager.html**: Sube música profesional (no copyrighted)
4. **tts-config.html**: Cambia voice_settings globales con cuidado

---

## 📚 Documentación Adicional

Para información detallada sobre cualquier componente, consulta:

### Endpoints (APIs)
- `/docs/endpoints/tts-generation.md` - Generación TTS completa
- `/docs/endpoints/jingle.md` - Sistema de jingles
- `/docs/endpoints/audio-scheduler.md` - Programación
- `/docs/endpoints/claude-ai.md` - Integración Claude
- `/docs/endpoints/azuracast-integration.md` - AzuraCast
- `/docs/endpoints/calendar.md` - Calendario
- `/docs/endpoints/messages.md` - Gestión de mensajes

### Workflows
- `/docs/workflows/complete-generation-flow.md` - Flujo completo
- `/docs/workflows/voice-experimentation.md` - Experimentación de voces

### Technical
- `/docs/technical/AUDIO-NORMALIZATION-SYSTEM.md` - Normalización LUFS
- `/docs/technical/DUCKING-SYSTEM-TECHNICAL-DOCUMENTATION.md` - Ducking
- `/docs/technical/AUDIO-SYSTEM-ARCHITECTURE.md` - Arquitectura
- `/docs/technical/DEVELOPER_QUICK_START.md` - Inicio rápido para devs

### Schemas
- `/docs/schemas/` - Estructura de base de datos

---

## 🚨 Troubleshooting

### Audio no se genera
- Verifica ELEVENLABS_API_KEY en .env
- Revisa logs: `tail -f src/api/logs/tts-*.log`
- Verifica permisos en src/api/temp/

### Audio no llega a AzuraCast
- Verifica contenedor azuracast: `docker ps`
- Verifica permisos en media path
- Revisa logs de radio-service

### Schedule no se ejecuta
- Verifica cron está corriendo: `systemctl status cron`
- Revisa logs: `tail -f src/api/logs/scheduler-cron.log`
- Verifica is_active=1 en audio_schedule

### Jingle sin música
- Verifica archivos en /public/audio/music/
- Revisa jingle-config.json
- Verifica FFmpeg instalado: `ffmpeg -version`

---

## 📊 Números Clave del Sistema

- **45+** servicios PHP en /src/api/
- **28** módulos JS
- **40+** archivos de documentación
- **8** herramientas en playground
- **13** tablas en base de datos
- **6** pistas de música disponibles
- **11** voces configuradas (5 activas por defecto)
- **7** categorías de mensajes

---

## 🎯 Contexto del Sistema

- **Propósito**: Sistema de radio automatizada multi-propósito
- **Cliente Principal**: Casa Costanera (mall)
- **Stack**: PHP 8.1, SQLite, JavaScript ES6+, nginx, FFmpeg
- **APIs**: ElevenLabs (TTS), Claude AI (sugerencias), AzuraCast (radio)
- **Frontend**: SPA con módulos JS vanilla y event bus
- **Puerto**: 2082
- **Base de datos**: SQLite (casa.db)

---

**Última actualización**: 2025-11-21
**Versión**: 1.0.0
**Mantenido por**: Sistema MediaFlow
