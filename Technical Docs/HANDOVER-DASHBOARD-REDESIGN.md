# 🚀 Dashboard Redesign - Documento Maestro de Handover

## 📋 Índice
1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Mockup Visual y Demo](#mockup-visual-y-demo)
3. [Arquitectura del Sistema](#arquitectura-del-sistema)
4. [Workflow del Usuario](#workflow-del-usuario)
5. [Base de Datos](#base-de-datos)
6. [APIs y Endpoints](#apis-y-endpoints)
7. [Componentes Frontend](#componentes-frontend)
8. [CSS y Diseño Visual](#css-y-diseño-visual)
9. [Checklist de Implementación](#checklist-de-implementación)
10. [Advertencias y Mejores Prácticas](#advertencias-y-mejores-prácticas)

---

## 🎯 Resumen Ejecutivo

### Objetivo
Rediseñar completamente el dashboard de Casa Costanera con un flujo de trabajo inspirado en WhatsApp/ChatGPT Desktop, dividido en dos fases: generación de texto con IA y generación de audio.

### Estado Actual
- ✅ **Mockup visual completo**: `/public/dashboard-redesign-mockup.html`
- ✅ **CSS modular aislado**: `/public/dashboard-redesign-mockup.css`
- ✅ **Documentación técnica**: Este documento
- ✅ **Backup estable v2.1.0**: `/stable-releases/casa-stable-v2.1.0-20250913_135617.tar.gz`
- ⏳ **Implementación**: Pendiente

### Stack Tecnológico
- Frontend: JavaScript vanilla ES6 (sin frameworks)
- Backend: PHP 8.1
- Base de datos: SQLite
- APIs: ElevenLabs (TTS), Claude (IA), AzuraCast (Radio)

---

## 🎨 Mockup Visual y Demo

### Archivos del Mockup
```bash
# Mockup HTML - Vista interactiva del nuevo diseño
/public/dashboard-redesign-mockup.html

# CSS dedicado - Completamente aislado del sistema
/public/dashboard-redesign-mockup.css
```

### Cómo Visualizar el Mockup
1. Acceder a: `http://localhost:4000/public/dashboard-redesign-mockup.html`
2. El mockup muestra ambas fases del workflow
3. Los elementos son estáticos pero representan el diseño final

### Características Visuales Clave
- **Layout 2 columnas**: 30% izquierda (historial), 70% derecha (trabajo)
- **Colores primarios**: Verde #00ff88, Fondo #0f0f1e
- **Inspiración**: WhatsApp Web + ChatGPT Desktop
- **Responsive**: Desktop > 1024px, Tablet 768-1024px, Mobile < 768px

---

## 🏗️ Arquitectura del Sistema

### Principios Fundamentales
1. **NO CREAR ARCHIVOS MONOLÍTICOS** - Componentes pequeños y reutilizables
2. **REVISAR CÓDIGO EXISTENTE** antes de crear nuevo
3. **CSS AISLADO** con namespace `.dashboard-redesign`
4. **REUTILIZAR** APIs y servicios existentes
5. **NO MODIFICAR** el dashboard en producción

### Estructura de Archivos Propuesta
```
/src/modules/dashboard-redesign/
├── index.js                      # Punto de entrada
├── components/
│   ├── session-manager/          # Gestión de sesiones
│   ├── ai-generator/             # Generación IA
│   ├── audio-generator/          # Generación audio
│   └── shared/                   # Componentes compartidos
├── services/                     # Lógica de negocio
├── styles/                       # CSS específico
└── templates/                    # HTML templates
```

---

## 🔄 Workflow del Usuario

### FASE 1: Generación de Texto con IA

#### Flujo Principal
1. **Usuario escribe contexto** en textarea principal
2. **Selecciona duración y tono** via dropdowns inline
3. **Presiona botón enviar** (círculo con avión)
4. **Sistema genera 3 sugerencias** simultáneamente
5. **Usuario puede**:
   - Editar texto directamente (contenteditable)
   - Regenerar sugerencia individual (botón 🔄)
   - Generar 3 más (botón prominente)
   - Seleccionar para audio (botón ✓)

#### Gestión de Sesiones
- **Nueva sesión**: Se crea al escribir en input vacío
- **Título automático**: Primeras 50 caracteres del input
- **Persistencia**: Todo se guarda automáticamente
- **Historial**: Lista en columna izquierda con búsqueda

### FASE 2: Generación de Audio

#### Flujo Principal
1. **Transición automática** desde Fase 1 con scroll suave
2. **Texto editable** antes de generar audio
3. **Selección de voz y música** via dropdowns
4. **Generación con preview automático**
5. **Acciones disponibles**:
   - Enviar a Radio 📡
   - Guardar en Biblioteca 💾
   - Programar emisión 📅

#### Agrupación de Audios
- **POR TEXTO, NO POR VOZ** (crítico)
- Mismo texto = mismo grupo
- Múltiples versiones con diferentes voces/música

---

## 💾 Base de Datos

### Nuevas Tablas Requeridas

```sql
-- 1. Sesiones de IA
CREATE TABLE ai_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    initial_context TEXT NOT NULL,
    duration INTEGER DEFAULT 15,
    tone TEXT DEFAULT 'profesional',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL  -- Soft delete
);

-- 2. Sugerencias generadas
CREATE TABLE ai_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    text TEXT NOT NULL,
    tone TEXT NOT NULL,
    duration INTEGER NOT NULL,
    order_position INTEGER NOT NULL,
    is_regenerated BOOLEAN DEFAULT 0,
    parent_suggestion_id INTEGER NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES ai_sessions(id)
);

-- 3. Versiones de audio
CREATE TABLE audio_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    suggestion_id INTEGER NOT NULL,
    text TEXT NOT NULL,
    voice TEXT NOT NULL,
    music_file TEXT,
    file_path TEXT NOT NULL,
    duration REAL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (suggestion_id) REFERENCES ai_suggestions(id)
);

-- Índices para performance
CREATE INDEX idx_session_created ON ai_sessions(created_at);
CREATE INDEX idx_suggestion_session ON ai_suggestions(session_id);
CREATE INDEX idx_audio_suggestion ON audio_versions(suggestion_id);
```

---

## 🔌 APIs y Endpoints

### APIs Existentes a Reutilizar
```php
/src/api/claude-service.php      # Servicio IA - Adaptar para sesiones
/src/api/generate.php             # TTS - Extender para versiones
/src/api/jingle-service.php       # Jingles - Usar sin cambios
/src/api/audio-scheduler.php      # Programación - Usar sin cambios
```

### Nuevos Endpoints Necesarios

#### Sesiones
```javascript
GET  /api/sessions/list           // Lista paginada
POST /api/sessions/create         // Nueva sesión
DELETE /api/sessions/{id}         // Soft delete
GET  /api/sessions/{id}/suggestions // Sugerencias de sesión
```

#### Sugerencias
```javascript
POST /api/suggestions/generate    // Generar 3 sugerencias
PUT  /api/suggestions/{id}        // Actualizar texto
POST /api/suggestions/{id}/regenerate // Regenerar individual
```

#### Versiones de Audio
```javascript
GET  /api/audio-versions/grouped  // Agrupados por texto
POST /api/audio-versions/generate // Nueva versión
```

---

## 🎨 Componentes Frontend

### Componentes Principales

#### 1. SessionManager
```javascript
// /components/session-manager/session-manager.js
class SessionManager {
    constructor() {
        this.sessions = [];
        this.currentSession = null;
    }
    
    async loadSessions() { }
    async createSession(context) { }
    async deleteSession(id) { }  // Soft delete
    async searchSessions(query) { }
}
```

#### 2. AIGenerator
```javascript
// /components/ai-generator/ai-generator.js
class AIGenerator {
    async generateSuggestions(context, duration, tone) {
        // Genera 3 sugerencias simultáneamente
    }
    
    async regenerateSuggestion(id) {
        // Regenera una sugerencia individual
    }
}
```

#### 3. AudioGenerator
```javascript
// /components/audio-generator/audio-generator.js
class AudioGenerator {
    async generateAudio(text, voice, music) {
        // Genera audio con preview automático
    }
    
    async sendToRadio(audioId) { }
    async saveToLibrary(audioId) { }
    async scheduleAudio(audioId) { }
}
```

### Componentes a Reutilizar
- Modal de programación (de Campaign)
- Sistema de toasts (existente)
- Player de audio (verificar si existe)

---

## 🎨 CSS y Diseño Visual

### Arquitectura CSS
```css
/* Namespace único para aislamiento */
.dashboard-redesign {
    /* Variables específicas */
    --dr-primary: #00ff88;
    --dr-bg-dark: #0f0f1e;
    --dr-surface: #1a1a2e;
    --dr-text: #e0e0e0;
}

/* Prefijo dr- para todas las clases */
.dashboard-redesign .dr-session-list { }
.dashboard-redesign .dr-suggestion-card { }
.dashboard-redesign .dr-audio-player { }

/* NO usar selectores genéricos */
/* NO modificar clases globales */
/* NO usar !important excepto casos críticos */
```

### Responsive Breakpoints
```css
/* Desktop */
@media (min-width: 1024px) {
    .dr-layout { grid-template-columns: 30% 70%; }
}

/* Tablet */
@media (min-width: 768px) and (max-width: 1023px) {
    .dr-layout { grid-template-columns: 35% 65%; }
}

/* Mobile */
@media (max-width: 767px) {
    .dr-layout { grid-template-columns: 1fr; }
}
```

---

## ✅ Checklist de Implementación

### Fase 0: Preparación
- [ ] Crear backup del sistema actual
- [ ] Revisar y entender código existente
- [ ] Configurar entorno de desarrollo aislado
- [ ] Verificar acceso a APIs (ElevenLabs, Claude, AzuraCast)

### Fase 1: Base de Datos
- [ ] Crear migraciones para nuevas tablas
- [ ] Implementar índices para optimización
- [ ] Crear seeds con datos de prueba
- [ ] Verificar integridad referencial

### Fase 2: Backend APIs
- [ ] Implementar endpoint de sesiones
- [ ] Implementar endpoint de sugerencias
- [ ] Implementar endpoint de versiones de audio
- [ ] Adaptar claude-service.php para sesiones
- [ ] Extender generate.php para versiones múltiples
- [ ] Implementar soft delete con recuperación admin

### Fase 3: Frontend - Estructura Base
- [ ] Crear estructura de carpetas modular
- [ ] Implementar router para nueva ruta
- [ ] Crear template HTML base
- [ ] Implementar CSS con namespace aislado

### Fase 4: Frontend - Fase 1 (IA)
- [ ] Implementar SessionManager
- [ ] Crear lista de sesiones con búsqueda
- [ ] Implementar AIGenerator
- [ ] Crear cards de sugerencias editables
- [ ] Implementar regeneración individual
- [ ] Agregar botón "Generar 3 más"

### Fase 5: Frontend - Fase 2 (Audio)
- [ ] Implementar AudioGenerator
- [ ] Crear lista de audios agrupados por texto
- [ ] Implementar generación con preview automático
- [ ] Integrar con AzuraCast (enviar a radio)
- [ ] Integrar con Campaign (guardar en biblioteca)
- [ ] Reutilizar modal de programación existente

### Fase 6: Integración y Polish
- [ ] Implementar scroll suave entre fases
- [ ] Agregar animaciones y transiciones
- [ ] Implementar sistema de notificaciones toast
- [ ] Optimizar performance (lazy loading, debouncing)
- [ ] Implementar auto-guardado

### Fase 7: Testing
- [ ] Test de generación múltiple de sugerencias
- [ ] Test de soft delete y recuperación
- [ ] Test de agrupación de audios por texto
- [ ] Test de persistencia de sesiones
- [ ] Test responsive en diferentes dispositivos
- [ ] Test de integración con radio

### Fase 8: Deployment
- [ ] Crear build de producción
- [ ] Documentar configuración necesaria
- [ ] Preparar rollback plan
- [ ] Deploy en staging
- [ ] Validación con usuarios
- [ ] Deploy en producción

---

## ⚠️ Advertencias y Mejores Prácticas

### CRÍTICO - NO HACER
❌ **NO modificar** archivos del dashboard en producción
❌ **NO crear** archivos monolíticos (>500 líneas)
❌ **NO usar** !important en CSS (excepto casos justificados)
❌ **NO agrupar** audios por voz (agrupar por TEXTO)
❌ **NO exponer** claves API en el frontend
❌ **NO hacer** delete real de datos (usar soft delete)

### IMPORTANTE - SIEMPRE HACER
✅ **REVISAR** código existente antes de crear nuevo
✅ **REUTILIZAR** componentes y APIs existentes
✅ **MANTENER** CSS completamente aislado con namespace
✅ **IMPLEMENTAR** auto-guardado en todas las interacciones
✅ **VALIDAR** todo input del usuario
✅ **DOCUMENTAR** decisiones técnicas importantes

### Configuraciones Remotas (NO cambiar desde dashboard)
- **Contexto IA**: Se configura desde `/playground/`
- **Voces disponibles**: `/playground/voice-admin.php`
- **Música/Jingles**: `/playground/jingle-config.html`
- **Modelo IA**: Configurado en servidor (claude-3-haiku)

### Rendimiento
- Implementar paginación (20 items por página)
- Lazy loading para sesiones antiguas
- Debouncing en búsqueda (300ms)
- Cache de configuraciones (voces, música)

### Seguridad
- Sanitizar todo HTML en contenteditable
- Rate limiting en generación IA
- Validar permisos antes de soft delete
- No exponer rutas de archivos completas

### UX Crítica
- **Preview automático** de audio al generarse
- **Botón "Generar 3 más"** prominente (evita múltiples sesiones)
- **Scroll suave** entre fases
- **Toast notifications** para confirmaciones
- **Responsive** con layout adaptable

---

## 📞 Contacto y Soporte

### Archivos de Referencia
- **Mockup Visual**: `/public/dashboard-redesign-mockup.html`
- **CSS del Mockup**: `/public/dashboard-redesign-mockup.css`
- **Dashboard Actual**: `/src/modules/dashboard/`
- **APIs Existentes**: `/src/api/`
- **Documentación**: `/docs/`

### Recursos Adicionales
- **Backup Estable**: `/stable-releases/casa-stable-v2.1.0-20250913_135617.tar.gz`
- **Configuración Playground**: `/src/playground/`
- **Logs del Sistema**: `/src/api/logs/`

---

## 🚀 Inicio Rápido para el Desarrollador

```bash
# 1. Clonar repositorio
git clone [repository-url]

# 2. Instalar dependencias
npm install

# 3. Configurar .env
cp .env.example .env
# Editar con claves API

# 4. Verificar permisos
chmod 777 database/
chmod 666 database/casa.db

# 5. Ver mockup
# Abrir en navegador: http://localhost:4000/public/dashboard-redesign-mockup.html

# 6. Iniciar desarrollo
# Crear rama feature
git checkout -b feature/dashboard-redesign

# 7. Seguir checklist de implementación
```

---

*Documento creado: 2025-09-13*
*Versión estable de referencia: v2.1.0*
*Estado: Listo para implementación*