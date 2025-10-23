# Dashboard Redesign - Consideraciones Técnicas y Arquitectura

## 🏗️ Arquitectura General

### Principios Fundamentales
1. **NO CREAR ARCHIVOS MONOLÍTICOS** - Separar en componentes pequeños y reutilizables
2. **REVISAR EXHAUSTIVAMENTE** antes de crear cualquier archivo nuevo
3. **REUTILIZAR** todo lo posible del dashboard actual
4. **CSS LIMPIO** sin overlaps ni conflictos con el dashboard en producción
5. **PREGUNTAR ANTES DE CREAR** - Siempre consultar antes de crear nuevos módulos

### Stack Tecnológico
- **Frontend**: JavaScript vanilla con módulos ES6
- **Backend**: PHP 8.1
- **Base de datos**: SQLite
- **APIs**: ElevenLabs (TTS), Claude (IA), AzuraCast (Radio)
- **Sin frameworks**: No React, no Vue - mantener consistencia con el proyecto actual

---

## 💾 BASE DE DATOS

### Tablas Nuevas Requeridas

#### 1. `ai_sessions`
```sql
CREATE TABLE ai_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,           -- Primeras palabras del input
    initial_context TEXT NOT NULL, -- Texto inicial del usuario
    duration INTEGER DEFAULT 15,   -- Duración seleccionada
    tone TEXT DEFAULT 'profesional', -- Tono seleccionado
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL      -- Para soft delete
);
```

#### 2. `ai_suggestions`
```sql
CREATE TABLE ai_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    text TEXT NOT NULL,
    tone TEXT NOT NULL,
    duration INTEGER NOT NULL,
    order_position INTEGER NOT NULL,  -- Orden en la sesión
    is_regenerated BOOLEAN DEFAULT 0, -- Si fue regenerada
    parent_suggestion_id INTEGER NULL, -- ID de la sugerencia original si fue regenerada
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES ai_sessions(id)
);
```

#### 3. `audio_versions`
```sql
CREATE TABLE audio_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    suggestion_id INTEGER NOT NULL,
    text TEXT NOT NULL,              -- Texto final usado (puede ser editado)
    voice TEXT NOT NULL,
    music_file TEXT,
    file_path TEXT NOT NULL,
    duration REAL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (suggestion_id) REFERENCES ai_suggestions(id)
);
```

### Modificaciones a Tablas Existentes
- **audio_metadata**: Agregar columna `session_id` para vincular con las sesiones
- **Índices**: Crear índices en `session_id` y `created_at` para búsquedas rápidas

---

## 🧩 ARQUITECTURA MODULAR

### Estructura de Archivos Propuesta
```
/src/modules/dashboard-redesign/
├── index.js                          # Punto de entrada principal
├── components/
│   ├── session-manager/
│   │   ├── session-manager.js        # Gestión de sesiones
│   │   ├── session-list.js           # Lista de sesiones UI
│   │   └── session-search.js         # Búsqueda de sesiones
│   ├── ai-generator/
│   │   ├── ai-generator.js           # Lógica de generación IA
│   │   ├── suggestion-card.js        # Card individual de sugerencia
│   │   ├── suggestion-list.js        # Lista de sugerencias
│   │   └── input-controls.js         # Controles de duración/tono
│   ├── audio-generator/
│   │   ├── audio-generator.js        # Lógica de generación audio
│   │   ├── audio-player.js           # Player de preview
│   │   ├── audio-history.js          # Historial de audios
│   │   └── audio-controls.js         # Controles voz/música
│   └── shared/
│       ├── toast-manager.js          # Sistema de notificaciones
│       └── scroll-manager.js         # Gestión de scroll entre fases
├── services/
│   ├── session-service.js            # API calls para sesiones
│   ├── ai-service.js                 # API calls para IA
│   └── audio-service.js              # API calls para audio
├── styles/
│   └── dashboard-redesign.css        # Estilos específicos del redesign
└── templates/
    └── dashboard-redesign.html       # Template HTML
```

### ⚠️ IMPORTANTE: Antes de crear estos archivos
1. **REVISAR** `/src/modules/dashboard/components/` - muchos componentes pueden ser reutilizables
2. **VERIFICAR** si existe algo similar que se pueda adaptar
3. **PREGUNTAR** al equipo antes de crear estructuras nuevas

---

## 🔄 COMPONENTES A REUTILIZAR

### Del Dashboard Actual

#### APIs y Servicios
- **`/src/api/claude-service.php`** - Servicio de IA actual
- **`/src/api/generate.php`** - Generación TTS
- **`/src/api/jingle-service.php`** - Jingles con música
- **`/src/api/recent-messages.php`** - Mensajes recientes
- **`/src/api/audio-scheduler.php`** - Programación de audios

#### Componentes UI
- **`/src/modules/dashboard/components/recent-messages.js`** - Base para historial
- **Modal de programación de Campaign** - Usar el existente
- **Sistema de toasts** - Ya implementado
- **Player de audio** - Verificar si existe uno reutilizable

#### Configuraciones
- **Voces**: Configurables desde `/playground/voice-admin.php`
- **Música**: Configurables desde `/playground/jingle-config.html`
- **Contexto IA**: Configurable remotamente desde playground

### NO CREAR desde cero:
- Sistema de notificaciones (existe)
- Modal de programación (existe en campaign)
- API de Claude (existe y funciona)
- Sistema de voces (existe y es configurable)

---

## 🎨 CSS - ARQUITECTURA LIMPIA

### Principios CSS
1. **Namespace único**: Todos los estilos bajo `.dashboard-redesign`
2. **Variables CSS**: Usar variables para colores y espaciados
3. **BEM naming**: `.block__element--modifier`
4. **Sin !important**: Excepto para overrides específicos
5. **Mobile-first**: Media queries para desktop

### Estructura CSS
```css
/* Variables específicas del redesign */
.dashboard-redesign {
    --dr-primary: #00ff88;
    --dr-column-left: 30%;
    --dr-column-right: 70%;
}

/* Namespace para evitar conflictos */
.dashboard-redesign .dr-session-list { }
.dashboard-redesign .dr-suggestion-card { }
.dashboard-redesign .dr-audio-player { }
```

### Evitar Overlaps
- **NO modificar** clases globales del sistema
- **NO usar** selectores muy genéricos (`button`, `input`)
- **Prefijar** todas las clases con `dr-` (dashboard-redesign)
- **Aislar** completamente del dashboard en producción

---

## 🔗 INTEGRACIONES

### AzuraCast
- **Endpoint existente**: Verificar en `/src/api/`
- **Método**: POST para interrumpir transmisión
- **Autenticación**: API Key en `.env`

### Campaign/Biblioteca
- **Reutilizar**: Modal de guardado existente
- **Tabla**: `campaign_messages` o similar
- **Categorías**: Implementar selector de categorías al guardar

### Calendario
- **Modal existente**: En módulo Campaign
- **No recrear**: Usar el mismo modal y lógica
- **API**: `/src/api/audio-scheduler.php`

---

## 📊 GESTIÓN DE ESTADO

### Estado de Sesión
```javascript
// Estado global de la sesión activa
const sessionState = {
    currentSession: null,
    suggestions: [],
    selectedSuggestion: null,
    audioVersions: []
};
```

### Persistencia
- **Auto-guardado**: Cada interacción se guarda inmediatamente
- **Sin localStorage**: Todo en base de datos
- **Recuperación**: Al recargar, recuperar sesión activa de BD

---

## 🔍 AGRUPACIONES Y LÓGICA

### Agrupación de Sesiones
- **Por fecha**: Sesiones del mismo día agrupadas
- **Título automático**: Primeras 50 caracteres del input
- **Orden**: Más recientes primero
- **Paginación**: 20 sesiones por página

### Agrupación de Audios
- **Por TEXTO**: Mismo texto = mismo grupo (importante!)
- **NO por voz**: La voz no determina la agrupación
- **Versiones**: Todas las variaciones del mismo texto juntas
- **Metadata**: Mostrar voz, música y timestamp de cada versión

### Lógica de Regeneración
- Cuando se regenera una sugerencia individual:
  1. Mantener la original en el historial
  2. Crear nueva con `parent_suggestion_id`
  3. Reemplazar en la UI pero guardar ambas en BD
  4. La sesión mantiene todas las versiones

---

## 🚀 ENDPOINTS API

### Nuevos Endpoints Necesarios

#### `/api/sessions/`
- `GET /api/sessions/list` - Lista paginada de sesiones
- `POST /api/sessions/create` - Nueva sesión
- `DELETE /api/sessions/{id}` - Soft delete
- `GET /api/sessions/{id}/suggestions` - Sugerencias de una sesión

#### `/api/suggestions/`
- `POST /api/suggestions/generate` - Generar 3 sugerencias
- `PUT /api/suggestions/{id}` - Actualizar texto editado
- `POST /api/suggestions/{id}/regenerate` - Regenerar individual

#### `/api/audio-versions/`
- `GET /api/audio-versions/grouped` - Audios agrupados por texto
- `POST /api/audio-versions/generate` - Generar nueva versión

### Adaptar Existentes
- Modificar `/src/api/claude-service.php` para soportar sesiones
- Extender `/src/api/generate.php` para versiones múltiples

---

## 🔒 CONSIDERACIONES DE SEGURIDAD

### Validaciones
- Sanitizar todo input del usuario
- Límites de caracteres en textareas
- Rate limiting en generación de IA
- Validar permisos antes de soft delete

### Soft Delete
- Usuario normal: Marca como eliminado (`deleted_at`)
- Admin desde playground: Puede ver y recuperar todo
- Implementar vista admin en `/playground/admin-sessions.php`

---

## 📱 RESPONSIVE DESIGN

### Breakpoints
- Desktop: > 1024px (layout 2 columnas)
- Tablet: 768px - 1024px (ajustar proporciones)
- Mobile: < 768px (layout 1 columna)

### Consideraciones Mobile
- Columnas se apilan verticalmente
- Historiales colapsables
- Touch-friendly (botones mínimo 44x44px)
- Scroll suave entre secciones

---

## ⚡ OPTIMIZACIÓN Y PERFORMANCE

### Lazy Loading
- Cargar sesiones bajo demanda
- Paginación de 20 items
- Infinite scroll opcional

### Caching
- Cache de sugerencias no usadas (para recuperar)
- Cache de configuraciones (voces, música)
- Invalidar cache al cambiar configuración

### Debouncing
- Búsqueda con debounce de 300ms
- Auto-guardado con debounce de 1000ms

---

## 🧪 TESTING

### Casos de Prueba Críticos
1. Generación de múltiples sugerencias
2. Soft delete y recuperación
3. Agrupación correcta de audios por texto
4. Transición entre fases
5. Persistencia de sesiones
6. Responsive en diferentes dispositivos

---

## 📝 NOTAS FINALES IMPORTANTES

1. **SIEMPRE** revisar código existente antes de crear nuevo
2. **NUNCA** modificar archivos del dashboard en producción
3. **PREGUNTAR** antes de tomar decisiones arquitectónicas
4. **DOCUMENTAR** cada decisión técnica tomada
5. **TESTEAR** en ambiente aislado antes de integrar
6. **MANTENER** consistencia con el estilo de código existente

### Archivos de Referencia
- **Mockup visual**: `/public/dashboard-redesign-mockup.html`
- **CSS del mockup**: `/public/dashboard-redesign-mockup.css`
- **Dashboard actual**: `/src/modules/dashboard/`
- **APIs existentes**: `/src/api/`
- **Playground admin**: `/src/playground/`

### Orden de Implementación Sugerido
1. Base de datos y migraciones
2. APIs y endpoints
3. Componente de sesiones
4. Generador de sugerencias IA
5. Generador de audio
6. Integraciones (Radio, Campaign, Calendar)
7. Polish y optimizaciones
8. Testing completo

---

## 🚨 RECORDATORIOS CRÍTICOS

- **El contexto de IA se configura REMOTAMENTE** - No agregar UI para esto
- **Sin toggle de opciones avanzadas** - Decisión del cliente
- **Agrupación por TEXTO, no por voz** - Muy importante
- **Preview automático de audio** - Se reproduce al generarse
- **Soft delete con recuperación admin** - No delete real
- **CSS completamente aislado** - No tocar estilos globales