# Clients Service API

<endpoint_overview>
Servicio de gestión de clientes y contextos para el sistema multi-tenant.
Controla qué cliente está activo y su contexto específico para Claude AI.
Este endpoint es crítico para cambiar el comportamiento global del sistema.
</endpoint_overview>

<critical>
⚡ Este servicio controla el contexto de Claude AI globalmente.
Cambiar el cliente activo afecta a TODOS los usuarios del sistema.
El contexto del cliente modifica cómo Claude genera sugerencias.
</critical>

## POST /src/api/clients-service.php

<description>
Gestiona clientes del sistema, permitiendo cambiar contextos de AI, 
crear nuevos clientes y administrar configuraciones específicas por cliente.
</description>

## Actions Disponibles

<actions_overview>
- `get_active` - Obtener el cliente actualmente activo
- `set_active` - Cambiar el cliente activo (afecta todo el sistema)
- `list` - Listar todos los clientes disponibles
- `get` - Obtener información de un cliente específico
- `save` - Crear o actualizar un cliente
- `delete` - Eliminar un cliente (con restricciones)
</actions_overview>

---

## Action: get_active

<action_description>
Obtiene la información completa del cliente actualmente activo en el sistema.
Este cliente determina el contexto usado por Claude AI.
</action_description>

<request>
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_active"
  }'
```
</request>

<response>
```json
{
  "success": true,
  "client": {
    "id": "casa_costanera",
    "name": "Casa Costanera",
    "context": "Eres un experto creando anuncios para Casa Costanera, un moderno centro comercial en Chile. Debes usar un tono profesional y amigable.",
    "category": "centro_comercial",
    "active": true,
    "settings": {
      "default_tone": "profesional",
      "language": "es-CL",
      "max_length": 200,
      "keywords": ["shopping", "familia", "entretenimiento"]
    },
    "custom_prompts": {
      "promociones": "Enfócate en crear urgencia y destacar los descuentos.",
      "eventos": "Menciona fecha, hora y lugar específico dentro del mall.",
      "informativos": "Sé claro y conciso, evita jerga técnica."
    }
  }
}
```
</response>

---

## Action: set_active

<action_description>
⚠️ CRÍTICO: Cambia el cliente activo del sistema.
Esto modifica inmediatamente el contexto de Claude AI para TODOS los usuarios.
</action_description>

<parameters>
- `client_id` (string, required): ID del cliente a activar
</parameters>

<request>
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "set_active",
    "client_id": "mallplaza"
  }'
```
</request>

<response>
```json
{
  "success": true,
  "active_client": {
    "id": "mallplaza",
    "name": "Mallplaza",
    "context": "Eres un experto en marketing para Mallplaza, una cadena premium de centros comerciales.",
    "category": "centro_comercial_premium",
    "active": true
  }
}
```
</response>

<error_response>
```json
{
  "success": false,
  "error": "Cliente no encontrado"
}
```
</error_response>

---

## Action: list

<action_description>
Lista todos los clientes disponibles en el sistema,
indicando cuál está actualmente activo.
</action_description>

<request>
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list"
  }'
```
</request>

<response>
```json
{
  "success": true,
  "active_client": "casa_costanera",
  "clients": {
    "casa_costanera": {
      "id": "casa_costanera",
      "name": "Casa Costanera",
      "context": "Eres un experto creando anuncios para Casa Costanera...",
      "category": "centro_comercial",
      "active": true
    },
    "mallplaza": {
      "id": "mallplaza",
      "name": "Mallplaza",
      "context": "Eres un experto en marketing para Mallplaza...",
      "category": "centro_comercial_premium",
      "active": true
    },
    "parque_arauco": {
      "id": "parque_arauco",
      "name": "Parque Arauco",
      "context": "Eres un especialista en contenido para Parque Arauco...",
      "category": "centro_comercial_luxury",
      "active": false
    }
  }
}
```
</response>

---

## Action: get

<action_description>
Obtiene la información detallada de un cliente específico.
</action_description>

<parameters>
- `client_id` (string, required): ID del cliente a consultar
</parameters>

<request>
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get",
    "client_id": "casa_costanera"
  }'
```
</request>

<response>
```json
{
  "success": true,
  "client": {
    "id": "casa_costanera",
    "name": "Casa Costanera",
    "context": "Eres un experto creando anuncios para Casa Costanera...",
    "category": "centro_comercial",
    "active": true,
    "created_at": "2024-01-01T10:00:00Z",
    "updated_at": "2025-01-12T15:30:00Z"
  }
}
```
</response>

---

## Action: save

<action_description>
Crea un nuevo cliente o actualiza uno existente.
Si no se proporciona ID, se genera uno automáticamente.
</action_description>

<parameters>
- `client` (object, required): Datos del cliente
  - `id` (string, optional): ID del cliente (se autogenera si no se proporciona)
  - `name` (string, required): Nombre display del cliente
  - `context` (string, required): Contexto para Claude AI
  - `category` (string, optional): Categoría del cliente
  - `active` (boolean, optional): Si el cliente está activo
  - `settings` (object, optional): Configuraciones específicas
  - `custom_prompts` (object, optional): Prompts personalizados por categoría
</parameters>

<request>
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save",
    "client": {
      "id": "nuevo_mall",
      "name": "Nuevo Mall Santiago",
      "context": "Eres un experto en crear contenido para Nuevo Mall Santiago, un centro comercial moderno y tecnológico. Usa un tono innovador y juvenil.",
      "category": "centro_comercial_tech",
      "active": true,
      "settings": {
        "default_tone": "juvenil",
        "language": "es-CL",
        "max_length": 180,
        "keywords": ["tecnología", "innovación", "experiencia digital"]
      },
      "custom_prompts": {
        "promociones": "Enfatiza la experiencia digital y las ofertas online.",
        "eventos": "Destaca los aspectos tecnológicos e interactivos.",
        "informativos": "Usa un lenguaje moderno y referencias tech."
      }
    }
  }'
```
</request>

<response>
```json
{
  "success": true,
  "client": {
    "id": "nuevo_mall",
    "name": "Nuevo Mall Santiago",
    "context": "Eres un experto en crear contenido para Nuevo Mall Santiago...",
    "category": "centro_comercial_tech",
    "active": true,
    "created_at": "2025-01-12T16:00:00Z"
  }
}
```
</response>

---

## Action: delete

<action_description>
Elimina un cliente del sistema.
No se puede eliminar el cliente activo ni el último cliente disponible.
</action_description>

<parameters>
- `client_id` (string, required): ID del cliente a eliminar
</parameters>

<request>
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete",
    "client_id": "cliente_antiguo"
  }'
```
</request>

<response>
```json
{
  "success": true,
  "message": "Cliente eliminado correctamente"
}
```
</response>

<error_responses>
```json
// Cliente activo
{
  "success": false,
  "error": "No se puede eliminar el cliente activo"
}

// Único cliente
{
  "success": false,
  "error": "Debe existir al menos un cliente"
}

// Cliente no encontrado
{
  "success": false,
  "error": "Cliente no encontrado"
}
```
</error_responses>

---

## Client Configuration Structure

<client_structure>
```json
{
  "id": "string",                    // Identificador único
  "name": "string",                  // Nombre para mostrar
  "context": "string",               // Contexto para Claude AI (crítico)
  "category": "string",              // Categoría del negocio
  "active": boolean,                 // Si está disponible
  "settings": {                      // Configuraciones opcionales
    "default_tone": "string",        // Tono por defecto
    "language": "string",            // Idioma (es-CL, es-MX, etc.)
    "max_length": number,            // Longitud máxima de mensajes
    "keywords": ["array"]            // Palabras clave a incluir
  },
  "custom_prompts": {                // Prompts específicos por categoría
    "promociones": "string",
    "eventos": "string",
    "informativos": "string",
    "emergencias": "string"
  },
  "created_at": "ISO 8601",         // Fecha de creación
  "updated_at": "ISO 8601"          // Última actualización
}
```
</client_structure>

## Integration with Claude AI

<claude_integration>
Cuando se genera una sugerencia con Claude, el sistema:

1. Consulta el cliente activo mediante `get_active`
2. Extrae el contexto del cliente
3. Aplica el contexto como system prompt en Claude
4. Si hay custom_prompts para la categoría, los añade
5. Claude genera sugerencias basadas en este contexto

Ejemplo de flujo:
```javascript
// 1. Dashboard solicita sugerencias
const suggestions = await claudeService.generateSuggestions({
  category: "promociones",
  context: "Black Friday"
});

// 2. Claude Service internamente:
const activeClient = await getActiveClient();
const systemPrompt = activeClient.context;
const categoryPrompt = activeClient.custom_prompts?.promociones;

// 3. Claude recibe:
// System: "Eres un experto creando anuncios para Casa Costanera..."
// + "Enfócate en crear urgencia y destacar los descuentos."
// User: "Genera sugerencias para Black Friday"
```
</claude_integration>

## Common Use Cases

<use_cases>

### Cambiar contexto para campaña especial
```bash
# 1. Crear cliente temporal para Navidad
curl -X POST .../clients-service.php -d '{
  "action": "save",
  "client": {
    "id": "casa_navidad",
    "name": "Casa Costanera - Navidad",
    "context": "Eres un experto creando mensajes navideños mágicos y emotivos para Casa Costanera. Usa referencias a Santa, regalos, familia y tradiciones chilenas.",
    "custom_prompts": {
      "promociones": "Menciona regalos perfectos y ofertas navideñas."
    }
  }
}'

# 2. Activar cliente navideño
curl -X POST .../clients-service.php -d '{
  "action": "set_active",
  "client_id": "casa_navidad"
}'

# 3. Todas las sugerencias ahora serán navideñas

# 4. Después de Navidad, volver al normal
curl -X POST .../clients-service.php -d '{
  "action": "set_active",
  "client_id": "casa_costanera"
}'
```

### Multi-mall management
```bash
# Listar todos los malls disponibles
curl -X POST .../clients-service.php -d '{"action": "list"}'

# Cambiar rápidamente entre malls
curl -X POST .../clients-service.php -d '{
  "action": "set_active",
  "client_id": "mallplaza"
}'
```
</use_cases>

## Error Handling

<error_handling>
### Missing Parameters
```json
{
  "success": false,
  "error": "client_id es requerido"
}
```

### Invalid Action
```json
{
  "success": false,
  "error": "Acción no válida",
  "available_actions": ["get_active", "set_active", "list", "get", "save", "delete"]
}
```

### Client Not Found
```json
{
  "success": false,
  "error": "Cliente no encontrado"
}
```

### Cannot Delete Active Client
```json
{
  "success": false,
  "error": "No se puede eliminar el cliente activo"
}
```
</error_handling>

## Security Considerations

<security>
⚠️ **IMPORTANTE**: Este endpoint controla el comportamiento global del sistema.

1. **Autenticación requerida**: Debería implementarse autenticación para este endpoint
2. **Logging de cambios**: Todos los cambios de cliente activo deberían registrarse
3. **Backup de configuración**: Hacer backup antes de cambios mayores
4. **Validación de contexto**: Verificar que el contexto no contenga instrucciones maliciosas

Ejemplo de validación recomendada:
```php
// Verificar permisos antes de cambiar cliente
if (!userHasAdminRole()) {
    throw new Exception("No autorizado para cambiar cliente");
}

// Log del cambio
logClientChange($oldClient, $newClient, $userId);
```
</security>

## Configuration File

<config_file>
Los datos se almacenan en: `/src/api/data/clients-config.json`

Estructura del archivo:
```json
{
  "active_client": "casa_costanera",
  "clients": {
    "casa_costanera": { ... },
    "mallplaza": { ... }
  }
}
```

⚠️ Este archivo debe tener permisos de escritura para el servidor web.
</config_file>