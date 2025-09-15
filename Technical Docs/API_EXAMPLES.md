# CLAUDE AI API - EJEMPLOS COMPLETOS

## 📡 ENDPOINTS Y EJEMPLOS

### 1. CLIENTS SERVICE API

#### Listar todos los clientes
```bash
curl -X GET "http://localhost:4000/api/clients-service.php?action=list_clients"
```
**Response:**
```json
{
  "success": true,
  "clients": {
    "casa_costanera": {
      "id": "casa_costanera",
      "name": "Casa Costanera",
      "context": "Eres un experto creando anuncios para Casa Costanera...",
      "active": true,
      "category": "centro_comercial"
    },
    "generic": {
      "id": "generic",
      "name": "Cliente Genérico", 
      "context": "Eres un experto en crear anuncios comerciales...",
      "active": true,
      "category": "generico"
    }
  },
  "default_client": "casa_costanera"
}
```

#### Obtener cliente específico
```bash
curl -X GET "http://localhost:4000/api/clients-service.php?action=get_client&client_id=casa_costanera"
```
**Response:**
```json
{
  "success": true,
  "client": {
    "id": "casa_costanera",
    "name": "Casa Costanera",
    "context": "Eres un experto creando anuncios para Casa Costanera, un moderno centro comercial en Chile...",
    "active": true,
    "category": "centro_comercial",
    "created_at": "2025-09-11T00:00:00Z"
  }
}
```

#### Crear nuevo cliente
```bash
curl -X POST "http://localhost:4000/api/clients-service.php" \
-H "Content-Type: application/json" \
-d '{
  "action": "save_client",
  "client": {
    "id": "restaurant_pepita",
    "name": "Restaurante La Pepita", 
    "context": "Eres un experto creando anuncios para RESTAURANTE LA PEPITA, un restaurant familiar de comida italiana con 15 años de tradición en el barrio. Especialistas en pizzas artesanales y pasta fresca.",
    "category": "restaurante",
    "active": true
  }
}'
```
**Response:**
```json
{
  "success": true,
  "message": "Cliente guardado exitosamente",
  "client_id": "restaurant_pepita"
}
```

#### Eliminar cliente
```bash
curl -X POST "http://localhost:4000/api/clients-service.php" \
-H "Content-Type: application/json" \
-d '{
  "action": "delete_client",
  "client_id": "restaurant_pepita"
}'
```

### 2. CLAUDE SERVICE API

#### Generación con cliente específico
```bash
curl -X POST "http://localhost:4000/api/claude-service.php" \
-H "Content-Type: application/json" \
-d '{
  "action": "generate",
  "category": "ofertas",
  "context": "Promoción 2x1 en pizzas durante este fin de semana",
  "client_id": "restaurant_pepita",
  "tone": "amigable",
  "duration": 15,
  "keywords": ["2x1", "fin de semana", "pizza"],
  "temperature": 0.8,
  "model": "claude-3-haiku-20240307"
}'
```
**Response:**
```json
{
  "success": true,
  "suggestions": [
    {
      "id": "sug_abc123",
      "text": "¡Este fin de semana La Pepita te sorprende! Lleva 2 pizzas artesanales por el precio de 1. Una tradición de 15 años en sabor auténtico italiano. ¡Solo sábado y domingo!",
      "char_count": 158,
      "word_count": 28,
      "created_at": "2025-09-11 18:45:00"
    },
    {
      "id": "sug_def456", 
      "text": "¿Antojado de pizza italiana auténtica? La Pepita tiene la promoción perfecta: 2x1 en todas nuestras pizzas artesanales este fin de semana. ¡15 años de tradición familiar!",
      "char_count": 167,
      "word_count": 29,
      "created_at": "2025-09-11 18:45:00"
    }
  ],
  "client_used": "restaurant_pepita",
  "model": "claude-3-haiku-20240307",
  "tokens_used": 245,
  "generation_time": 1.2
}
```

## 🧪 TESTS DE DIFERENCIACIÓN

### Test A: Casa Costanera
```bash
curl -X POST "http://localhost:4000/api/claude-service.php" \
-H "Content-Type: application/json" \
-d '{
  "action": "generate",
  "category": "eventos", 
  "context": "Concierto de música clásica el próximo viernes",
  "client_id": "casa_costanera"
}'
```
**Resultado esperado:** Menciones a "centro comercial", "tiendas", "entretenimiento familiar"

### Test B: Restaurante
```bash
curl -X POST "http://localhost:4000/api/claude-service.php" \
-H "Content-Type: application/json" \
-d '{
  "action": "generate", 
  "category": "eventos",
  "context": "Concierto de música clásica el próximo viernes",
  "client_id": "restaurant_pepita"
}'
```
**Resultado esperado:** Menciones a "La Pepita", "comida italiana", "ambiente familiar"

## 🔧 JAVASCRIPT FRONTEND INTEGRATION

### Client Context Service Usage
```javascript
// Importar el servicio
import { ClientContextService } from '/src/core/client-context-service.js';

// Inicializar
const clientService = new ClientContextService();

// Cargar clientes disponibles
const clients = await clientService.loadClients();
console.log('Clientes disponibles:', clients);

// Seleccionar cliente
clientService.setSelectedClient('restaurant_pepita');

// Obtener cliente seleccionado
const selectedClient = clientService.getSelectedClient();
console.log('Cliente actual:', selectedClient);

// Generar con contexto específico
const suggestions = await llmService.generateAnnouncements({
  category: 'ofertas',
  context: 'Promoción especial de pizza',
  client_id: 'restaurant_pepita'
});
```

### Dashboard AI Suggestions Integration
```javascript
// En ai-suggestions.js
async generateSuggestions() {
  const selectedClientId = this.clientContextService.getSelectedClientId();
  const selectedClient = this.clientContextService.getSelectedClient();
  
  const params = {
    category: this.dashboard.state.selectedCategory,
    context: this.getContextFromInput(),
    client_id: selectedClientId,
    client_context: selectedClient?.context,
    tone: this.contextConfig.tone,
    duration: this.contextConfig.duration,
    keywords: this.contextConfig.keywords
  };
  
  const suggestions = await this.dashboard.llmService.generateAnnouncements(params);
  this.displaySuggestions(suggestions);
}
```

## 📱 FRONTEND UI EXAMPLES

### HTML Selector
```html
<div class="client-selector">
  <label for="clientSelect">Cliente / Contexto</label>
  <select id="clientSelect" class="client-select" onchange="handleClientChange()">
    <option value="">Cargando clientes...</option>
  </select>
  <button class="edit-context-btn" onclick="openContextEditor()">✏️ Editar</button>
  <div class="client-preview">
    <small id="clientPreview">Selecciona un cliente para ver el contexto</small>
  </div>
</div>
```

### JavaScript para poblar selector
```javascript
async function populateClientSelector() {
  try {
    const response = await fetch('/api/clients-service.php?action=list_clients');
    const data = await response.json();
    
    if (data.success) {
      const selector = document.getElementById('clientSelect');
      selector.innerHTML = '';
      
      Object.entries(data.clients).forEach(([id, client]) => {
        if (client.active) {
          const option = document.createElement('option');
          option.value = id;
          option.textContent = client.name;
          if (id === data.default_client) {
            option.selected = true;
          }
          selector.appendChild(option);
        }
      });
      
      // Actualizar preview
      updateClientPreview();
    }
  } catch (error) {
    console.error('Error cargando clientes:', error);
  }
}

function handleClientChange() {
  const selectedId = document.getElementById('clientSelect').value;
  localStorage.setItem('selected_client_id', selectedId);
  updateClientPreview();
  
  // Disparar evento para notificar cambio
  window.dispatchEvent(new CustomEvent('clientChanged', { 
    detail: { clientId: selectedId } 
  }));
}

async function updateClientPreview() {
  const selectedId = document.getElementById('clientSelect').value;
  if (!selectedId) return;
  
  try {
    const response = await fetch(`/api/clients-service.php?action=get_client&client_id=${selectedId}`);
    const data = await response.json();
    
    if (data.success && data.client) {
      const preview = document.getElementById('clientPreview');
      preview.textContent = data.client.context.substring(0, 100) + '...';
      preview.title = data.client.context; // Full context on hover
    }
  } catch (error) {
    console.error('Error cargando preview:', error);
  }
}
```

## 🔍 VALIDATION & ERROR HANDLING

### Validar respuestas del API
```javascript
function validateApiResponse(response, expectedKeys = []) {
  if (!response) {
    throw new Error('Respuesta vacía del API');
  }
  
  if (!response.success) {
    throw new Error(response.error || 'Error del servidor');
  }
  
  // Validar keys esperadas
  expectedKeys.forEach(key => {
    if (!(key in response)) {
      throw new Error(`Campo requerido '${key}' no encontrado en la respuesta`);
    }
  });
  
  return response;
}

// Uso
try {
  const response = await fetch('/api/clients-service.php?action=list_clients');
  const data = await response.json();
  validateApiResponse(data, ['clients', 'default_client']);
  // ... usar data
} catch (error) {
  console.error('Error validando respuesta:', error.message);
  // Handle error appropriately
}
```

### Error fallbacks
```javascript
async function getClientContext(clientId) {
  try {
    const response = await fetch(`/api/clients-service.php?action=get_client&client_id=${clientId}`);
    const data = await response.json();
    
    if (data.success && data.client) {
      return data.client.context;
    }
  } catch (error) {
    console.warn('Error obteniendo contexto de cliente:', error);
  }
  
  // Fallback a contexto por defecto
  return "Eres un experto en crear anuncios comerciales efectivos y atractivos.";
}
```

## 📊 MONITORING & DEBUGGING

### Logging de requests
```javascript
// Wrapper para API calls con logging
async function apiCall(url, options = {}) {
  const startTime = performance.now();
  
  console.log(`[API] ${options.method || 'GET'} ${url}`, options.body ? JSON.parse(options.body) : '');
  
  try {
    const response = await fetch(url, options);
    const data = await response.json();
    const endTime = performance.now();
    
    console.log(`[API] Response (${(endTime - startTime).toFixed(2)}ms):`, data);
    
    return data;
  } catch (error) {
    console.error(`[API] Error calling ${url}:`, error);
    throw error;
  }
}
```

### Performance metrics
```javascript
// Medir tiempo de generación de sugerencias
const generateWithMetrics = async (params) => {
  const startTime = Date.now();
  
  try {
    const suggestions = await llmService.generateAnnouncements(params);
    const endTime = Date.now();
    
    // Log métricas
    console.log('Generación completada:', {
      client_id: params.client_id,
      suggestions_count: suggestions.length,
      generation_time: endTime - startTime,
      avg_chars: suggestions.reduce((acc, s) => acc + s.char_count, 0) / suggestions.length
    });
    
    return suggestions;
  } catch (error) {
    console.error('Error en generación:', error);
    throw error;
  }
};
```

## 🎯 TESTING SCENARIOS

### Scenario 1: Cliente nuevo sin contexto
```javascript
// Crear cliente con contexto mínimo
const newClient = {
  id: 'test_client_' + Date.now(),
  name: 'Cliente de Prueba',
  context: 'Eres un experto en marketing.',
  active: true,
  category: 'test'
};

// Guardar y probar
await clientService.saveClient(newClient);
const suggestions = await generateWithClient(newClient.id);
console.log('Sugerencias para cliente nuevo:', suggestions);
```

### Scenario 2: Cliente con contexto largo
```javascript
const longContext = 'Eres un experto creando anuncios para ' + 'A'.repeat(1500);
// Test: ¿Se trunca? ¿Genera error? ¿Performance impact?
```

### Scenario 3: Fallback handling
```javascript
// Test con cliente inexistente
const suggestions = await generateWithClient('non_existent_client');
// Should fallback to default context
```

**¡READY FOR INTEGRATION!** 🚀