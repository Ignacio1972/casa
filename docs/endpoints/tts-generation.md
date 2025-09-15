# TTS Generation API

<endpoint_overview>
Endpoint principal para generación de audio Text-to-Speech usando ElevenLabs API.
Soporta múltiples voces, categorías y configuraciones de estilo.
</endpoint_overview>

## POST /generate.php

<description>
Genera audio TTS a partir de texto, con opciones de voz, categoría y configuraciones avanzadas.
</description>

<parameters>
### Required Parameters
- `text` (string): Texto a convertir en audio (máximo 5000 caracteres)
- `voice` (string): ID de voz de ElevenLabs o nombre de voz predefinida
- `category` (string): Categoría del audio (informativos, promociones, eventos, etc.)
- `client_id` (string): Identificador del cliente

### Optional Parameters
- `voice_settings` (object): Configuraciones de voz
  - `stability` (float): 0.0 a 1.0 (default: 0.5)
  - `similarity_boost` (float): 0.0 a 1.0 (default: 0.75)
  - `style` (float): 0.0 a 1.0 (default: 0.0)
  - `use_speaker_boost` (boolean): default: true
- `template_type` (string): Tipo de plantilla a usar
- `template_data` (object): Datos para la plantilla
- `source` (string): Origen de la petición (dashboard, playground, api)
- `save_message` (boolean): Guardar mensaje en biblioteca (default: false)
- `metadata` (object): Metadatos adicionales
</parameters>

<example_request>
```bash
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "text": "Bienvenidos a Casa Costanera, el mejor centro comercial de la ciudad",
    "voice": "Rachel",
    "category": "informativos",
    "voice_settings": {
      "stability": 0.5,
      "similarity_boost": 0.75,
      "style": 0.3
    },
    "save_message": true,
    "metadata": {
      "campaign": "bienvenida",
      "priority": "high"
    }
  }'
```
</example_request>

<example_response>
```json
{
  "success": true,
  "data": {
    "audio_url": "/audio/tts_20250112_143022_Rachel.mp3",
    "filename": "tts_20250112_143022_Rachel.mp3",
    "duration": 5.2,
    "text": "Bienvenidos a Casa Costanera, el mejor centro comercial de la ciudad",
    "voice": "Rachel",
    "category": "informativos",
    "message_id": 123,
    "character_count": 68,
    "quota_remaining": 9932
  },
  "message": "Audio generado exitosamente"
}
```
</example_response>

<error_handling>
## Common Errors

### Missing Parameters
```json
{
  "success": false,
  "error": "Faltan parámetros requeridos: text, voice",
  "code": "MISSING_PARAMS"
}
```

### ElevenLabs API Error
```json
{
  "success": false,
  "error": "Error en API de ElevenLabs: voice_not_found",
  "code": "API_ERROR",
  "details": {
    "service": "elevenlabs",
    "status_code": 404
  }
}
```

### Quota Exceeded
```json
{
  "success": false,
  "error": "Cuota de caracteres excedida para este mes",
  "code": "RATE_LIMIT",
  "details": {
    "used": 10000,
    "limit": 10000,
    "reset_date": "2025-02-01"
  }
}
```
</error_handling>

<voice_configuration>
## Available Voices

### Female Voices
- `Rachel` (21m00Tcm4TlvDq8ikWAM): Voz profesional, clara
- `Domi` (AZnzlk1XvdvUeBnXmlld): Voz joven, enérgica  
- `Bella` (EXAVITQu4vr4xnSDxMaL): Voz suave, amigable
- `Elli` (MF3mGyEYCl7XYWbV9V6O): Voz madura, confiable
- `Charlotte` (XB0fDUnXU5powFXDhCwa): Voz elegante

### Male Voices  
- `Antoni` (ErXwobaYiN019PkySvjV): Voz profesional masculina
- `Josh` (TxGEqnHWrfWFTfGW9XjX): Voz joven masculina
- `Arnold` (VR6AewLTigWG4xSOukaG): Voz profunda
- `Adam` (pNInz6obpgDQGcFmaJgB): Voz versátil
- `Sam` (yoZ06aMxZJJ28mfd3POQ): Voz casual
</voice_configuration>

<categories>
## Available Categories
- `informativos`: Anuncios informativos generales
- `promociones`: Ofertas y promociones
- `eventos`: Anuncios de eventos
- `seguridad`: Mensajes de seguridad
- `navidad`: Contenido navideño
- `verano`: Contenido de temporada de verano
- `test`: Pruebas y desarrollo
</categories>

<template_types>
## Template Types
Cuando se usa `template_type`, el texto se genera automáticamente:

- `oferta_simple`: Promoción básica
- `evento_proximo`: Anuncio de evento próximo
- `horario_especial`: Cambio de horarios
- `seguridad_recordatorio`: Recordatorio de seguridad
- `bienvenida_general`: Mensaje de bienvenida

Ejemplo con template:
```json
{
  "client_id": "casa",
  "template_type": "oferta_simple",
  "template_data": {
    "tienda": "Falabella",
    "descuento": "30%",
    "categoria": "ropa de verano"
  },
  "voice": "Rachel",
  "category": "promociones"
}
```
</template_types>

<playground_mode>
## Playground Mode
Cuando `source: "playground"`, se activan características especiales:
- Logging detallado en `/playground/logger/`
- Tracking de cuota separado
- Configuraciones de voz avanzadas
- Debug mode activado
</playground_mode>