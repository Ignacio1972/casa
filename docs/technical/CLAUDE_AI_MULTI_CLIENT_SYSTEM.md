# CLAUDE AI MULTI-CLIENT SYSTEM - ESPECIFICACIÓN TÉCNICA

## 📋 RESUMEN EJECUTIVO

**Objetivo:** Implementar sistema multi-cliente para Claude AI que permita seleccionar y administrar diferentes contextos de negocio desde el dashboard principal.

**Estado Actual:** El sistema siempre usa contexto hardcodeado de "Casa Costanera". El playground tiene selector funcional pero no está conectado al dashboard.

**Entregable:** Sistema completo de gestión de contextos de IA con interfaz administrativa.

---

## 🔍 ANÁLISIS DEL SISTEMA ACTUAL

### ✅ COMPONENTES FUNCIONALES
- **Claude API Service** (`/src/api/claude-service.php`) - Operativo al 100%
- **Playground Claude** (`/public/playground/claude.html`) - Selector funcional con 3 contextos
- **API Endpoint** - Maneja `client_context` correctamente
- **Generación diferenciada** - Comprobado con tests

### ❌ PROBLEMAS IDENTIFICADOS

#### 1. Dashboard sin selector de cliente
```javascript
// PROBLEMA en /src/modules/dashboard/components/ai-suggestions.js
// No incluye client_context en las llamadas
```

#### 2. LLM Service incompleto
```javascript
// PROBLEMA en /src/core/llm-service.js línea 71-80
buildContext(params) {
    const context = {
        category: params.category || 'general',
        context: params.context || '',
        // ❌ FALTA: client_context
    };
}
```

#### 3. Contexto hardcodeado en PHP
```php
// PROBLEMA en /src/api/claude-service.php línea 75
$basePrompt = "Eres un experto creando anuncios para Casa Costanera...";
// ❌ Solo fallback, pero necesita gestión dinámica
```

---

## 🧪 TESTS REALIZADOS - VALIDACIÓN DE FUNCIONALIDAD

### Test 1: Contexto Casa Costanera
```bash
curl -X POST "http://localhost:4000/api/claude-service.php" \
-H "Content-Type: application/json" \
-d '{
  "action": "generate",
  "category": "ofertas", 
  "context": "Promocion 2x1 en pizzas",
  "client_context": "Eres un experto creando anuncios para Casa Costanera"
}'
```
**Resultado:** ✅ Genera sugerencias con tono de centro comercial

### Test 2: Contexto Personalizado
```bash
curl -X POST "http://localhost:4000/api/claude-service.php" \
-H "Content-Type: application/json" \
-d '{
  "action": "generate",
  "category": "ofertas",
  "context": "Promocion 2x1 en pizzas", 
  "client_context": "Eres un experto creando anuncios para RESTAURANTE LA PEPITA, un restaurant familiar de comida italiana"
}'
```
**Resultado:** ✅ Menciona "La Pepita" específicamente en sugerencias

**CONCLUSIÓN:** El backend funciona perfectamente. El problema está en el frontend.

---

## 📐 ARQUITECTURA PROPUESTA

### 1. ESTRUCTURA DE DATOS
```json
// /src/api/data/clients-config.json
{
  "default_client": "casa_costanera",
  "clients": {
    "casa_costanera": {
      "id": "casa_costanera",
      "name": "Casa Costanera", 
      "context": "Eres un experto creando anuncios para Casa Costanera, un moderno centro comercial en Chile con más de 100 tiendas...",
      "active": true,
      "created_at": "2025-09-11T00:00:00Z",
      "category": "centro_comercial"
    },
    "generic": {
      "id": "generic",
      "name": "Cliente Genérico",
      "context": "Eres un experto en crear anuncios comerciales efectivos y atractivos.",
      "active": true,
      "created_at": "2025-09-11T00:00:00Z", 
      "category": "generico"
    },
    "custom_restaurant_001": {
      "id": "custom_restaurant_001",
      "name": "Restaurante La Pepita",
      "context": "Eres un experto creando anuncios para RESTAURANTE LA PEPITA, un restaurant familiar de comida italiana con 15 años de tradición...",
      "active": true,
      "created_at": "2025-09-11T18:45:00Z",
      "category": "restaurante"
    }
  }
}
```

### 2. API ENDPOINTS NECESARIOS
```php
// /src/api/clients-service.php - NUEVO ARCHIVO
POST /api/clients-service.php
Actions:
- list_clients    // Obtener lista de clientes
- get_client      // Obtener contexto específico  
- save_client     // Crear/actualizar cliente
- delete_client   // Eliminar cliente
- set_default     // Establecer cliente por defecto
```

### 3. FLUJO DE DATOS
```
Dashboard Input → Client Selector → llm-service.js → claude-service.php → Claude API
     ↓
localStorage persistence ← clients-config.json ← Admin Panel (Playground)
```

---

## 🛠️ PLAN DE IMPLEMENTACIÓN DETALLADO

### FASE 1: BACKEND FOUNDATION
**Duración estimada: 2-3 horas**

#### 1.1 Crear servicio de clientes
```php
// Archivo: /src/api/clients-service.php
<?php
class ClientsService {
    private $configFile = __DIR__ . '/data/clients-config.json';
    
    public function listClients() { /* ... */ }
    public function getClient($id) { /* ... */ }
    public function saveClient($data) { /* ... */ }
    public function deleteClient($id) { /* ... */ }
}
```

#### 1.2 Modificar claude-service.php
```php
// En línea ~70, función getSystemPrompt()
private function getSystemPrompt($category = 'general', $clientContext = null, $clientId = null) {
    // 1. Prioridad: clientContext directo
    if ($clientContext && !empty($clientContext)) {
        $basePrompt = $clientContext . " ";
    }
    // 2. Backup: Cargar desde clients-config.json por ID
    elseif ($clientId) {
        $clientData = $this->loadClientById($clientId);
        $basePrompt = $clientData['context'] . " ";
    }
    // 3. Fallback: Casa Costanera
    else {
        $basePrompt = "Eres un experto creando anuncios para Casa Costanera...";
    }
    // ... resto del método
}
```

### FASE 2: FRONTEND - CORE COMPONENTS  
**Duración estimada: 3-4 horas**

#### 2.1 Actualizar llm-service.js
```javascript
// En buildContext(), línea ~71
buildContext(params) {
    const context = {
        category: params.category || 'general',
        context: params.context || '',
        keywords: params.keywords || [],
        tone: params.tone || 'profesional',
        duration: params.duration || 30,
        temperature: params.temperature || 0.8,
        model: params.model || 'claude-3-haiku-20240307',
        
        // ✅ NUEVO: Agregar client_context
        client_context: params.client_context || null,
        client_id: params.client_id || localStorage.getItem('selected_client_id') || 'casa_costanera'
    };
    
    return context;
}
```

#### 2.2 Crear ClientContextService
```javascript
// Archivo: /src/core/client-context-service.js - NUEVO
export class ClientContextService {
    constructor() {
        this.clients = {};
        this.selectedClientId = localStorage.getItem('selected_client_id') || 'casa_costanera';
    }
    
    async loadClients() { /* fetch from /api/clients-service.php */ }
    async saveClient(clientData) { /* save to backend */ }
    setSelectedClient(clientId) { /* update selection */ }
    getSelectedClient() { /* return current client */ }
}
```

### FASE 3: DASHBOARD INTEGRATION
**Duración estimada: 2-3 horas**

#### 3.1 Modificar ai-suggestions.js
```javascript
// Agregar después de línea 24 (contextConfig)
this.clientContextService = new ClientContextService();
this.selectedClient = null;

// En renderConfigPanel(), agregar selector
renderClientSelector() {
    return `
        <div class="ai-field">
            <label for="aiClientSelect">Cliente / Contexto</label>
            <select id="aiClientSelect" class="ai-client-select" onchange="updateClientContext()">
                ${Object.entries(this.clients).map(([id, client]) => 
                    `<option value="${id}" ${id === this.selectedClientId ? 'selected' : ''}>${client.name}</option>`
                ).join('')}
            </select>
            <button class="ai-edit-context-btn" onclick="openContextEditor()">✏️ Editar</button>
        </div>
    `;
}
```

### FASE 4: PLAYGROUND ADMIN
**Duración estimada: 4-5 horas**

#### 4.1 Mejorar claude.html
```javascript
// Conectar con ClientContextService en lugar de localStorage local
// Agregar CRUD completo de contextos
// Vista previa de diferencias entre contextos
```

#### 4.2 Crear gestión avanzada
```html
<!-- Agregar sección en playground/index.html -->
<section id="client-contexts" class="content-section">
    <h2>🎯 Gestión de Contextos de IA</h2>
    <!-- CRUD interface -->
    <!-- Context editor con syntax highlighting -->
    <!-- Test generator con comparación de resultados -->
</section>
```

---

## 🎨 UX/UI CONSIDERATIONS

### Dashboard Selector
```css
.ai-client-select {
    background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    width: 200px;
}

.ai-edit-context-btn {
    margin-left: 8px;
    padding: 6px 12px;
    background: var(--color-primary);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}
```

### Indicadores visuales
- **Badge** con nombre del cliente activo
- **Color coding** por tipo de cliente (centro comercial = azul, restaurante = verde, etc.)
- **Preview** del contexto en tooltip
- **Confirmación** antes de cambiar contexto

---

## 🔧 CONFIGURACIÓN Y DEPLOYMENT

### Variables de entorno
```bash
# .env additions
CLAUDE_DEFAULT_CLIENT=casa_costanera
CLAUDE_ALLOW_CUSTOM_CONTEXTS=true
CLAUDE_MAX_CUSTOM_CONTEXTS=50
```

### Permisos de archivos
```bash
chmod 644 /src/api/data/clients-config.json
chown www-data:www-data /src/api/data/clients-config.json
```

### Database schema (opcional - para versión avanzada)
```sql
-- Si se quiere persistencia en DB en lugar de JSON
CREATE TABLE clients_contexts (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    context TEXT NOT NULL,
    category VARCHAR(100),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🧪 TESTING STRATEGY

### Unit Tests
```javascript
// tests/client-context-service.test.js
describe('ClientContextService', () => {
    test('should load clients from API', async () => { /* ... */ });
    test('should persist selected client to localStorage', () => { /* ... */ });
    test('should handle API errors gracefully', () => { /* ... */ });
});
```

### Integration Tests
```javascript
// tests/llm-service.test.js  
describe('LLM Service with Client Context', () => {
    test('should include client_context in API call', async () => { /* ... */ });
    test('should fall back to default client if none selected', () => { /* ... */ });
});
```

### E2E Tests
```javascript
// cypress/integration/client_context.spec.js
describe('Client Context Selection', () => {
    it('should persist client selection across page reloads', () => { /* ... */ });
    it('should generate different suggestions for different clients', () => { /* ... */ });
});
```

---

## 📊 MONITORING Y ANALYTICS

### Métricas a trackear
```javascript
// Analytics events
analytics.track('client_context_changed', {
    from_client: previousClientId,
    to_client: newClientId,
    timestamp: new Date().toISOString()
});

analytics.track('ai_suggestions_generated', {
    client_id: selectedClientId,
    category: category,
    suggestions_count: results.length,
    generation_time: responseTime
});
```

### Logs importantes
```php
// En claude-service.php
error_log(sprintf(
    "[Claude AI] Client: %s | Category: %s | Context Length: %d chars | Response Time: %dms",
    $clientId, $category, strlen($clientContext), $responseTime
));
```

---

## 🚨 EDGE CASES Y ERROR HANDLING

### Casos límite a considerar
1. **Cliente eliminado mientras está seleccionado** → Fallback a default
2. **Contexto vacío o corrupto** → Usar contexto genérico
3. **API Claude down** → Cache de última respuesta exitosa
4. **localStorage corrupto** → Reset a configuración default
5. **Contexto muy largo** → Truncar con warning
6. **Caracteres especiales** → Sanitización automática

### Validaciones necesarias
```javascript
// Client context validation
function validateClientContext(context) {
    if (!context || typeof context !== 'string') return false;
    if (context.length < 10 || context.length > 2000) return false;
    if (context.includes('<script>') || context.includes('<?php')) return false;
    return true;
}
```

---

## 📚 DOCUMENTACIÓN ADICIONAL

### Para el desarrollador
- **API Reference:** Todos los endpoints documentados con ejemplos
- **Component Props:** Interfaces TypeScript de todos los componentes
- **State Management:** Flujo de datos entre componentes
- **Error Codes:** Lista completa de códigos de error y soluciones

### Para el usuario final
- **Guía de uso:** Cómo crear y gestionar contextos
- **Mejores prácticas:** Tips para contextos efectivos
- **Troubleshooting:** Soluciones a problemas comunes

---

## ⚡ PERFORMANCE CONSIDERATIONS

### Optimizaciones
- **Lazy loading** de contextos no utilizados
- **Caching** de respuestas de Claude (5 min TTL)
- **Debouncing** en editor de contextos (500ms)
- **Compression** de respuestas JSON grandes

### Limits y quotas
```javascript
const LIMITS = {
    MAX_CONTEXT_LENGTH: 2000,
    MAX_CUSTOM_CLIENTS: 50,
    CACHE_TTL: 300000, // 5 minutes
    DEBOUNCE_DELAY: 500 // ms
};
```

---

## 🔐 SECURITY CONSIDERATIONS

### Validaciones de input
- **Sanitización** de contextos antes de enviar a Claude
- **Rate limiting** en endpoints de gestión de clientes
- **CSRF protection** en formularios de edición
- **Input validation** server-side y client-side

### Permisos y accesos
- Solo administradores pueden crear/eliminar contextos
- Usuarios normales solo pueden seleccionar contextos existentes
- Audit log de cambios en contextos

---

## 📈 ROADMAP FUTURO

### V2 Features
- **Templates de contexto** por industria
- **A/B testing** de contextos
- **Analytics** de efectividad por contexto
- **Import/Export** de configuraciones
- **Multiidioma** en contextos

### V3 Features  
- **Machine learning** para optimización automática de contextos
- **Integración** con CRM para contextos dinámicos
- **API pública** para terceros
- **Webhooks** para notificaciones de cambios

---

## 🎯 CRITERIOS DE ACEPTACIÓN

### Must Have
- ✅ Selector de cliente funcional en dashboard
- ✅ Persistencia de selección
- ✅ CRUD completo desde playground
- ✅ Backward compatibility con sistema actual
- ✅ Error handling robusto

### Should Have
- ✅ Preview de contextos
- ✅ Validación de inputs
- ✅ UI/UX intuitiva
- ✅ Performance optimizada

### Could Have
- ⚪ Export/Import de configuraciones
- ⚪ Templates predefinidos
- ⚪ Analytics básicos

---

**READY FOR IMPLEMENTATION** 🚀

**Estimación total:** 12-15 horas de desarrollo
**Prioridad:** Alta
**Complejidad:** Media
**Riesgo:** Bajo (sistema actual funciona como fallback)