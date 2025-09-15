# Claude AI Service API

<endpoint_overview>
Servicio de integración con Claude AI para generación automática de contenido, 
sugerencias de anuncios y procesamiento inteligente de texto.
</endpoint_overview>

## POST /claude-service.php

<description>
Genera sugerencias de anuncios utilizando Claude AI basándose en contexto, 
categoría y parámetros específicos del negocio.
</description>

<parameters>
### Required Parameters
- `action` (string): Acción a ejecutar
  - `generate`: Generar sugerencias de anuncios
  - `getModels`: Obtener modelos disponibles
  - `checkStatus`: Verificar estado del servicio
- `client_id` (string): Identificador del cliente

### Parameters for generate
- `category` (string): Categoría del anuncio
- `context` (string): Contexto adicional para la generación
- `tone` (string): Tono del mensaje (formal, casual, urgente, amigable)
- `max_suggestions` (integer): Número de sugerencias (default: 3)
- `model` (string): Modelo de Claude a usar (default: claude-3-haiku-20240307)
- `temperature` (float): Creatividad 0.0-1.0 (default: 0.7)
</parameters>

<example_request>
```bash
# Generar sugerencias
curl -X POST "http://localhost:4000/src/api/claude-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "client_id": "casa",
    "category": "promociones",
    "context": "Black Friday en tienda de electrónica",
    "tone": "urgente",
    "max_suggestions": 5,
    "temperature": 0.8
  }'

# Obtener modelos disponibles
curl -X POST "http://localhost:4000/src/api/claude-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "getModels",
    "client_id": "casa"
  }'
```
</example_request>

<example_response>
```json
{
  "success": true,
  "data": {
    "suggestions": [
      {
        "text": "¡Atención! Solo por hoy, Black Friday en nuestra tienda de electrónica. Descuentos de hasta 70% en televisores, laptops y smartphones. ¡No te lo pierdas!",
        "tone": "urgente",
        "category": "promociones",
        "confidence": 0.95
      },
      {
        "text": "Última oportunidad del Black Friday. Encuentra los mejores precios del año en tecnología. Ofertas limitadas hasta agotar stock.",
        "tone": "urgente", 
        "category": "promociones",
        "confidence": 0.92
      }
    ],
    "model_used": "claude-3-haiku-20240307",
    "tokens_used": {
      "input": 145,
      "output": 238
    },
    "cost_estimate": 0.0012
  },
  "message": "Sugerencias generadas exitosamente"
}
```
</example_response>

<available_models>
## Claude Models

### claude-3-haiku-20240307
- **Velocidad**: Rápida
- **Calidad**: Buena
- **Costo Input**: $0.00025 por 1K tokens
- **Costo Output**: $0.00125 por 1K tokens
- **Uso recomendado**: Generación rápida de contenido simple

### claude-3-sonnet-20240229  
- **Velocidad**: Media
- **Calidad**: Excelente
- **Costo Input**: $0.003 por 1K tokens
- **Costo Output**: $0.015 por 1K tokens
- **Uso recomendado**: Balance entre calidad y velocidad

### claude-3-opus-20240229
- **Velocidad**: Lenta
- **Calidad**: Superior
- **Costo Input**: $0.015 por 1K tokens
- **Costo Output**: $0.075 por 1K tokens
- **Uso recomendado**: Contenido complejo que requiere máxima creatividad
</available_models>

<prompt_templates>
## System Prompts by Category

### Informativos
```
Genera anuncios informativos claros y concisos para un centro comercial.
Mantén un tono profesional y amigable.
```

### Promociones
```
Crea anuncios promocionales atractivos que generen urgencia.
Destaca los beneficios y el valor de las ofertas.
```

### Eventos
```
Redacta invitaciones a eventos que generen entusiasmo.
Incluye detalles importantes como fecha, hora y lugar.
```

### Seguridad
```
Genera mensajes de seguridad claros y directos.
Prioriza la claridad sobre la creatividad.
```
</prompt_templates>

<error_handling>
## Common Errors

### Missing API Key
```json
{
  "success": false,
  "error": "API key de Claude no configurada",
  "code": "CONFIG_ERROR"
}
```

### Rate Limited
```json
{
  "success": false,
  "error": "Límite de requests excedido",
  "code": "RATE_LIMIT",
  "details": {
    "retry_after": 60
  }
}
```

### Invalid Model
```json
{
  "success": false,
  "error": "Modelo no válido",
  "code": "INVALID_PARAM",
  "details": {
    "available_models": ["claude-3-haiku-20240307", "claude-3-sonnet-20240229"]
  }
}
```
</error_handling>

<integration_example>
## Integration with TTS Generation

```javascript
// 1. Obtener sugerencias de Claude
const suggestions = await fetch('/src/api/claude-service.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    action: 'generate',
    client_id: 'casa',
    category: 'promociones',
    context: 'Descuentos de temporada'
  })
});

// 2. Seleccionar una sugerencia
const selected = suggestions.data.suggestions[0];

// 3. Generar audio con TTS
const audio = await fetch('/src/api/generate.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    client_id: 'casa',
    text: selected.text,
    voice: 'Rachel',
    category: 'promociones'
  })
});
```
</integration_example>