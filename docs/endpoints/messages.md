# Messages Management APIs

<endpoint_overview>
Sistema completo de gestión de mensajes que incluye mensajes guardados (biblioteca),
mensajes recientes y plantillas reutilizables.
</endpoint_overview>

## Message System Components

1. **Recent Messages** (`/recent-messages.php`): Últimos mensajes generados no guardados
2. **Saved Messages** (`/saved-messages.php`): Biblioteca de mensajes guardados
3. **Biblioteca** (`/biblioteca.php`): Gestión avanzada de archivos de audio
4. **Message Templates**: Plantillas reutilizables para generación

---

## Recent Messages API

### GET /recent-messages.php

<description>
Obtiene los últimos mensajes generados que no han sido guardados en la biblioteca.
Útil para recuperar y guardar mensajes recientes.
</description>

<parameters>
### Query Parameters
- `limit` (integer): Número de mensajes a retornar (default: 40, max: 100)
- `category` (string): Filtrar por categoría
- `hours` (integer): Mensajes de las últimas X horas (default: 24)
</parameters>

<example_request>
```bash
# Obtener últimos 40 mensajes recientes
curl "http://localhost:4000/src/api/recent-messages.php"

# Filtrar por categoría y tiempo
curl "http://localhost:4000/src/api/recent-messages.php?category=promociones&hours=12&limit=20"
```
</example_request>

<example_response>
```json
{
  "success": true,
  "messages": [
    {
      "id": "audio_tts20250112_143022_Rachel",
      "filename": "tts20250112_143022_Rachel.mp3",
      "title": "Promoción Black Friday",
      "content": "Gran venta de Black Friday con descuentos de hasta 70% en toda la tienda",
      "category": "promociones",
      "created_at": "2025-01-12 14:30:22"
    }
  ],
  "total": 40
}
```
</example_response>

---

## Saved Messages API

### POST /saved-messages.php

<description>
CRUD completo para mensajes guardados en la biblioteca.
Permite guardar, listar, actualizar y eliminar mensajes.
</description>

<operations>

### Save Message
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save",
    "client_id": "casa",
    "title": "Anuncio de Apertura",
    "text": "Buenos días, bienvenidos a Casa Costanera. Hoy abrimos a las 10 AM.",
    "voice": "Rachel",
    "category": "informativos",
    "audio_file": "/audio/tts20250112_090000_Rachel.mp3",
    "tags": ["apertura", "bienvenida", "diario"],
    "favorite": true,
    "metadata": {
      "schedule": "daily",
      "priority": "high"
    }
  }'
```

### List Saved Messages
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list",
    "client_id": "casa",
    "filters": {
      "category": "informativos",
      "favorite": true,
      "tags": ["diario"]
    },
    "sort": "created_at",
    "order": "DESC",
    "limit": 50,
    "offset": 0
  }'
```

### Update Message
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update",
    "client_id": "casa",
    "message_id": 123,
    "title": "Anuncio de Apertura - Actualizado",
    "tags": ["apertura", "bienvenida", "diario", "importante"],
    "favorite": true
  }'
```

### Delete Message
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete",
    "client_id": "casa",
    "message_id": 123
  }'
```

### Search Messages
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "search",
    "client_id": "casa",
    "query": "Black Friday",
    "search_in": ["title", "text", "tags"],
    "category": "promociones"
  }'
```

### Get Message Details
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get",
    "client_id": "casa",
    "message_id": 123
  }'
```

### Duplicate Message
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "duplicate",
    "client_id": "casa",
    "message_id": 123,
    "new_title": "Copia de Anuncio de Apertura"
  }'
```
</operations>

<response_format>
### List Response
```json
{
  "success": true,
  "messages": [
    {
      "id": 123,
      "client_id": "casa",
      "title": "Anuncio de Apertura",
      "text": "Buenos días, bienvenidos a Casa Costanera...",
      "voice": "Rachel",
      "category": "informativos",
      "audio_file": "/audio/tts20250112_090000_Rachel.mp3",
      "tags": ["apertura", "bienvenida", "diario"],
      "favorite": true,
      "usage_count": 45,
      "metadata": {
        "schedule": "daily",
        "priority": "high"
      },
      "created_at": "2025-01-01 09:00:00",
      "updated_at": "2025-01-12 10:30:00"
    }
  ],
  "total": 150,
  "filtered": 25,
  "page": 1,
  "per_page": 50
}
```
</response_format>

---

## Biblioteca API

### GET/POST /biblioteca.php

<description>
Sistema avanzado de gestión de archivos de audio con integración a AzuraCast.
Maneja archivos TTS y externos, con capacidades de streaming y descarga.
</description>

<operations>

### List Audio Files
```bash
curl -X POST "http://localhost:4000/src/api/biblioteca.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list",
    "client_id": "casa",
    "type": "all",
    "category": "promociones",
    "date_from": "2025-01-01",
    "date_to": "2025-01-31"
  }'
```

### Get Audio File
```bash
# Streaming directo
curl "http://localhost:4000/src/api/biblioteca.php?filename=tts20250112_143022_Rachel.mp3"

# Descarga
curl "http://localhost:4000/src/api/biblioteca.php?action=download&filename=tts20250112_143022_Rachel.mp3"
```

### Upload External Audio
```bash
curl -X POST "http://localhost:4000/src/api/biblioteca.php" \
  -F "action=upload" \
  -F "client_id=casa" \
  -F "audio=@/path/to/audio.mp3" \
  -F "title=Música de Fondo" \
  -F "category=musica" \
  -F "metadata={\"type\":\"background\",\"bpm\":120}"
```

### Delete Audio
```bash
curl -X POST "http://localhost:4000/src/api/biblioteca.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete",
    "client_id": "casa",
    "filename": "tts20250112_143022_Rachel.mp3",
    "remove_from_azuracast": true
  }'
```

### Sync with AzuraCast
```bash
curl -X POST "http://localhost:4000/src/api/biblioteca.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "sync_azuracast",
    "client_id": "casa",
    "station_id": 1,
    "playlist_id": 5
  }'
```

### Get Audio Metadata
```bash
curl -X POST "http://localhost:4000/src/api/biblioteca.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "metadata",
    "client_id": "casa",
    "filename": "tts20250112_143022_Rachel.mp3"
  }'
```

Response:
```json
{
  "success": true,
  "metadata": {
    "filename": "tts20250112_143022_Rachel.mp3",
    "size": 245760,
    "duration": 15.3,
    "bitrate": "128k",
    "sample_rate": 44100,
    "channels": 2,
    "format": "mp3",
    "created_at": "2025-01-12 14:30:22",
    "text": "Gran venta de Black Friday...",
    "voice": "Rachel",
    "category": "promociones",
    "play_count": 23,
    "last_played": "2025-01-12 20:00:00"
  }
}
```
</operations>

---

## Message Templates

<template_system>

### Create Template
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create_template",
    "client_id": "casa",
    "name": "Promoción Simple",
    "template_type": "oferta_simple",
    "template_text": "¡Atención! {{tienda}} tiene {{descuento}}% de descuento en {{categoria}} {{dias}}. No dejes pasar esta increíble oportunidad.",
    "variables": {
      "tienda": {
        "type": "string",
        "required": true,
        "description": "Nombre de la tienda"
      },
      "descuento": {
        "type": "number",
        "required": true,
        "description": "Porcentaje de descuento",
        "min": 5,
        "max": 90
      },
      "categoria": {
        "type": "string",
        "required": true,
        "description": "Categoría de productos"
      },
      "dias": {
        "type": "string",
        "required": false,
        "default": "este fin de semana",
        "description": "Período de la promoción"
      }
    },
    "category": "promociones"
  }'
```

### Use Template
```bash
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "use_template": true,
    "template_id": 5,
    "template_data": {
      "tienda": "Falabella",
      "descuento": 40,
      "categoria": "ropa de verano",
      "dias": "solo hoy"
    },
    "voice": "Rachel",
    "category": "promociones"
  }'
```

### List Templates
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list_templates",
    "client_id": "casa",
    "category": "promociones"
  }'
```
</template_system>

---

## Batch Operations

<batch_operations>

### Batch Save Recent to Library
```javascript
async function saveRecentToLibrary(messageIds) {
  const results = [];
  
  for (const id of messageIds) {
    const response = await fetch('/src/api/saved-messages.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_from_recent',
        client_id: 'casa',
        recent_message_id: id,
        add_tags: ['batch_saved', new Date().toISOString().split('T')[0]]
      })
    });
    
    results.push(await response.json());
  }
  
  return results;
}
```

### Batch Tag Update
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "batch_update",
    "client_id": "casa",
    "message_ids": [123, 124, 125],
    "operations": [
      {
        "type": "add_tags",
        "tags": ["navidad", "2025"]
      },
      {
        "type": "set_category",
        "category": "seasonal"
      }
    ]
  }'
```

### Batch Delete Old Messages
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "batch_delete",
    "client_id": "casa",
    "filters": {
      "older_than": "2024-01-01",
      "category": "test",
      "usage_count": 0
    },
    "confirm": true
  }'
```
</batch_operations>

<statistics>
## Message Statistics

### Get Usage Statistics
```bash
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "statistics",
    "client_id": "casa",
    "period": "month",
    "group_by": "category"
  }'
```

Response:
```json
{
  "success": true,
  "statistics": {
    "period": "2025-01",
    "total_messages": 450,
    "total_usage": 3250,
    "by_category": {
      "informativos": {
        "count": 150,
        "usage": 1200,
        "avg_usage": 8
      },
      "promociones": {
        "count": 200,
        "usage": 1800,
        "avg_usage": 9
      }
    },
    "most_used": [
      {
        "id": 123,
        "title": "Bienvenida General",
        "usage_count": 145
      }
    ],
    "recently_created": 45,
    "favorites": 23
  }
}
```
</statistics>

<error_handling>
## Error Handling

### Message Not Found
```json
{
  "success": false,
  "error": "Mensaje no encontrado",
  "code": "MESSAGE_NOT_FOUND",
  "details": {
    "message_id": 999
  }
}
```

### Duplicate Title
```json
{
  "success": false,
  "error": "Ya existe un mensaje con ese título",
  "code": "DUPLICATE_TITLE",
  "details": {
    "existing_id": 123,
    "title": "Anuncio de Apertura"
  }
}
```

### Invalid Template Variables
```json
{
  "success": false,
  "error": "Variables de plantilla inválidas",
  "code": "INVALID_TEMPLATE_VARS",
  "details": {
    "missing": ["tienda", "descuento"],
    "invalid": {
      "descuento": "Must be a number between 5 and 90"
    }
  }
}
```
</error_handling>