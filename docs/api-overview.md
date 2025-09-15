# Casa Costanera API Documentation

<api_overview>
Sistema de Radio Automatizada con generación de audio TTS (Text-to-Speech), gestión de schedules, 
y integración con servicios de IA para generación de contenido dinámico.

Base URL: http://localhost:4000/src/api/ (desarrollo)
Base URL: /src/api/ (producción - ruta relativa)
</api_overview>

<authentication>
Actualmente el sistema utiliza autenticación basada en cliente_id:
- Cada request debe incluir el parámetro `client_id` 
- Los clientes válidos están configurados en `/config/clients/`
- Ejemplo: `client_id=casa`
</authentication>

<rate_limits>
- ElevenLabs API: 10,000 caracteres/mes (tier gratuito)
- Claude API: Según plan configurado
- Sistema local: Sin límites específicos
</rate_limits>

<response_format>
Todas las respuestas siguen el formato JSON estándar:

## Respuesta exitosa
```json
{
  "success": true,
  "data": {
    // Datos específicos del endpoint
  },
  "message": "Operación completada"
}
```

## Respuesta de error
```json
{
  "success": false,
  "error": "Descripción del error",
  "code": "ERROR_CODE",
  "details": {} // Información adicional opcional
}
```
</response_format>

<error_codes>
## Códigos de Error Comunes
- `MISSING_PARAMS`: Faltan parámetros requeridos
- `INVALID_CLIENT`: Cliente no válido o no autorizado
- `API_ERROR`: Error en servicio externo (ElevenLabs, Claude)
- `DATABASE_ERROR`: Error en operación de base de datos
- `FILE_ERROR`: Error en procesamiento de archivos
- `RATE_LIMIT`: Límite de uso excedido
</error_codes>

## Core Services

### TTS Generation Service
- **Endpoint**: `/generate.php`
- **Purpose**: Generar audio TTS con ElevenLabs
- **Documentation**: @docs/endpoints/tts-generation.md

### Claude AI Service
- **Endpoint**: `/claude-service.php`
- **Purpose**: Integración con Claude AI para generación de contenido
- **Documentation**: @docs/endpoints/claude-ai.md

### Jingle Service
- **Endpoint**: `/jingle-service.php`
- **Purpose**: Generación profesional de jingles con música, fade in/out, ducking
- **Features**: Control de volumen, mezcla avanzada, efectos de audio
- **Documentation**: @docs/endpoints/jingle.md

## Scheduling & Calendar

### Audio Scheduler Service
- **Endpoint**: `/audio-scheduler.php`
- **Purpose**: Gestión de programación de audio
- **Documentation**: @docs/endpoints/audio-scheduler.md

### Calendar Service
- **Endpoints**: `/calendar-api.php`, `/calendar-service.php`
- **Purpose**: Gestión de eventos, campañas y programación especial
- **Features**: Eventos recurrentes, vinculación con audio
- **Documentation**: @docs/endpoints/calendar.md

## Message Management

### Recent Messages
- **Endpoint**: `/recent-messages.php`
- **Purpose**: Obtener mensajes recientes no guardados
- **Documentation**: @docs/endpoints/messages.md#recent-messages-api

### Saved Messages Library
- **Endpoint**: `/saved-messages.php`
- **Purpose**: CRUD completo de mensajes guardados
- **Features**: Tags, favoritos, plantillas, búsqueda
- **Documentation**: @docs/endpoints/messages.md#saved-messages-api

### Biblioteca (Audio Library)
- **Endpoint**: `/biblioteca.php`
- **Purpose**: Gestión avanzada de archivos de audio
- **Features**: Integración AzuraCast, streaming, metadatos
- **Documentation**: @docs/endpoints/messages.md#biblioteca-api

## Voice & Audio Configuration

### Voice Manager
- **Endpoint**: `/playground/api/voice-manager.php`
- **Purpose**: Gestión de voces personalizadas y clonadas
- **Features**: Voice cloning, configuración por voz, orden personalizado
- **Documentation**: @docs/endpoints/voice-manager.md

### Voice Admin
- **Endpoint**: `/playground/api/voice-admin.php`
- **Purpose**: Panel administrativo de voces y quotas
- **Features**: Estadísticas, límites, backup/restore
- **Documentation**: @docs/endpoints/voice-manager.md#voice-admin-api

## Radio Integration

### Radio Service (AzuraCast) ⚡ CRITICAL SYSTEM
- **Endpoint**: `/services/radio-service.php`
- **Purpose**: Integración completa con AzuraCast para radio automatizada
- **Features**: 
  - **File Upload**: Sistema automático con nomenclatura específica
  - **Folder Structure**: Organización por categorías (Grabaciones/, Jingles/, etc.)
  - **Playlist Management**: Asignación automática con pesos y reglas
  - **Signal Interruption**: Sistema de interrupción por prioridad (urgent/high/normal)
  - **Preview System**: Preview con base64 antes de emisión
  - **Liquidsoap Control**: Comandos directos via Docker socket
- **Documentation**: @docs/endpoints/azuracast-integration.md

### Station Management
- **Features**: Control de estación, gestión de playlists
- **Auto-sync**: Sincronización automática de archivos
- **Preview**: Sistema de preview antes de emisión

## Audio Processing Services

### Audio Processor
- **Service**: `/services/audio-processor.php`
- **Purpose**: Procesamiento post-generación de audio
- **Features**: Silence padding, normalización, preservación de formato
- **Documentation**: @docs/endpoints/audio-services.md#audio-processor-service

### TTS Service Enhanced
- **Service**: `/services/tts-service-enhanced.php`
- **Purpose**: Versión mejorada con sistema dinámico de voces
- **Features**: Voice config JSON, respeta frontend settings, fallback support
- **Documentation**: @docs/endpoints/audio-services.md#tts-service-enhanced

### Announcement Generator
- **Service**: `/services/announcement-module/announcement-generator.php`
- **Purpose**: Generación avanzada con templates y optimización
- **Features**: Templates system, voice optimization, multi-language
- **Documentation**: @docs/endpoints/audio-services.md#announcement-generator-module

### Announcement Templates
- **Service**: `/services/announcement-module/announcement-templates.php`
- **Purpose**: Sistema de plantillas predefinidas
- **Features**: Categorías, variables dinámicas, custom templates
- **Documentation**: @docs/endpoints/audio-services.md#announcement-templates-system

## Utility Services

### Quota Tracker
- **Endpoint**: `/playground/api/quota-tracker.php`
- **Purpose**: Seguimiento de uso de APIs

### Config Service
- **Endpoint**: `/jingle-config-service.php`
- **Purpose**: Configuración de jingles y música

### Music Service
- **Endpoint**: `/music-service.php`
- **Purpose**: Gestión de biblioteca musical para jingles

## Critical Remote Configuration Systems ⚡

### Voice Configuration System
- **Purpose**: Control qué voces aparecen en Dashboard desde Playground
- **Config File**: `/src/api/data/voices-config.json`
- **Admin Endpoint**: `/playground/api/voice-admin.php`
- **Key Feature**: Toggle `active` field to show/hide voices remotely
- **Documentation**: @docs/critical-systems/remote-configuration.md#voice-configuration-system

### Clients Service
- **Endpoint**: `/src/api/clients-service.php`
- **Purpose**: Gestión de clientes y contextos multi-tenant para Claude AI
- **Critical**: Controla el contexto global del sistema
- **Config File**: `/src/api/data/clients-config.json`
- **Key Feature**: Switch `active_client` to change AI context globally
- **Full Documentation**: @docs/endpoints/clients-service.md
- **Remote Config Docs**: @docs/critical-systems/remote-configuration.md#multi-client-context-system

### Jingle Configuration System
- **Purpose**: Control remoto de parámetros de jingles (silencios, volúmenes, fades)
- **Config File**: `/src/api/data/jingle-config.json`
- **Config UI**: `http://51.222.25.222:4000/playground/jingle-config.html`
- **Service**: `/src/api/jingle-config-service.php`
- **Key Features**: 
  - Intro/outro silence (0-30s)
  - Music/voice volume control
  - Fade in/out duration
  - Ducking configuration
- **Documentation**: @docs/critical-systems/remote-configuration.md#jingle-configuration-system

## Frontend Modules

### Dashboard Module ⭐ MAIN INTERFACE
- **Location**: `/src/modules/dashboard/`
- **Purpose**: Interfaz principal para generación y gestión de audio
- **Components**:
  - AI Suggestions (Claude integration)
  - Jingle Controls (music mixing)
  - Voice Controls (fine-tuning)
  - Recent Messages (message management)
  - Quota Chart (usage tracking)
- **Documentation**: @docs/frontend/dashboard-module.md

## Quick Start

<quick_test>
# Test de conexión básica
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "text": "Prueba de audio",
    "voice": "21m00Tcm4TlvDq8ikWAM",
    "category": "test"
  }'
</quick_test>

## Environment Variables

<required_env>
# .env file required at project root
ELEVENLABS_API_KEY=your_elevenlabs_key
CLAUDE_API_KEY=your_claude_key
AZURACAST_API_KEY=your_azuracast_key
CLAUDE_MODEL=claude-3-haiku-20240307
CLAUDE_MAX_TOKENS=500
</required_env>

## Database Schema
Documentation: @docs/schemas/database.md

## Common Workflows
- Generate announcement: @docs/examples/generate-announcement.md
- Schedule audio: @docs/examples/schedule-audio.md
- AI suggestions: @docs/examples/ai-suggestions.md