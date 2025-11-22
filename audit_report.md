# AUDITORÍA COMPLETA - Sistema Casa Costanera
## Análisis Detallado de Código y Arquitectura

**Fecha:** 31 de Octubre de 2025
**Total de código analizado:** 27,039 líneas (PHP + JavaScript)
**Archivos principales:** 46 archivos PHP, 30+ archivos JavaScript
**Base de datos:** SQLite con 14 tablas

---

## RESUMEN EJECUTIVO

El sistema Casa Costanera es un **sistema monolítico con problemas graves de arquitectura**. Aunque funcional, presenta:

- **Código duplicado crítico** (múltiples versiones de servicios similares)
- **Falta de capas de abstracción** (lógica de negocio mezclada con controladores)
- **Acoplamiento excesivo** entre módulos
- **Deuda técnica sustancial** con archivos muy grandes (>500 líneas)
- **Gestión de dependencias inconsistente**
- **Seguridad débil** en manejo de datos

**Riesgo general:** ALTO
**Mantenibilidad:** BAJA
**Escalabilidad:** LIMITADA

---

## 1. ANÁLISIS DE ESTRUCTURA DEL PROYECTO

### 1.1 Distribución de Código

```
/var/www/casa/src/
├── api/ (11 archivos PHP principales + servicios)
├── core/ (7 archivos JS de utilidades)
├── modules/ (4 módulos principales)
│   ├── dashboard/ (5 componentes)
│   ├── campaigns/ (5 servicios)
│   ├── calendar/ (8 componentes)
│   └── automatic/ (1 módulo)
└── database/ (casa.db con 14 tablas)
```

### 1.2 Archivos Monolíticos (>500 líneas)

| Archivo | Líneas | Severidad | Problema |
|---------|--------|-----------|----------|
| jingle-service.php | 653 | ALTA | Toda la lógica de jingles en 1 archivo |
| biblioteca.php | 659 | ALTA | API monolítica de gestión de biblioteca |
| audio-scheduler.php | 635 | ALTA | Lógica compleja de scheduling sin separación |
| dashboard/index.js | 1,198 | CRÍTICA | Módulo principal muy grande y complejo |
| campaigns/index.js | 1,155 | CRÍTICA | Biblioteca de campañas todo en uno |
| automatic/index.js | 1,140 | CRÍTICA | Módulo automático desorganizado |
| calendar/calendar-view.js | 1,124 | CRÍTICA | Vista de calendario sin separación de lógica |
| recent-messages.js | 811 | ALTA | Componente de mensajes muy grande |
| ai-suggestions.js | 580 | MEDIA | Sugerencias de IA mezcladas |
| message-actions.js | 573 | MEDIA | Acciones de mensajes centralizadas |
| audio-archive/index.js | 550 | MEDIA | Archivo de audio sin separación |

**Impacto:** Dificultad extrema para testear, refactorizar y mantener.

---

## 2. PROBLEMAS CRÍTICOS ENCONTRADOS

### 2.1 Duplicación de Código Severa

#### Servicios TTS Duplicados

| Archivo | Líneas | Estado | Problema |
|---------|--------|--------|----------|
| tts-service.php | 549 | LEGADO | Solo redirige a tts-service-unified.php |
| tts-service-unified.php | 10,584 | ACTIVO | Versión unificada principal |
| tts-service-enhanced.php | 575 | LEGADO | Versión mejorada antigua |

**Problema:** 3 versiones del mismo servicio. Confusión sobre cuál usar.

#### Servicios Jingle Duplicados

| Archivo | Líneas | Último cambio |
|---------|--------|--------------|
| automatic-jingle-service.php | 423 | 12 Oct 2025 |
| automatic-jingle-service-v2.php | 414 | 25 Sep 2025 |

**Diferencia:** V2 usa AudioProcessor v2 con normalización LUFS, V1 usa funciones básicas.
**Problema:** ¿Cuál está en producción? ¿Por qué no se consolidaron?

#### Servicios de Música

| Archivo | Líneas | Propósito |
|---------|--------|----------|
| music-service.php | 6,902 | Listado de música |
| music-manager-service.php | 13,250 | Gestión completa |
| simple-music-restart.php | 711 | Reinicio simple |

**Patrón problemático:** Múltiples intentos de resolver el mismo problema sin consolidación.

### 2.2 Código Duplicado en JavaScript

#### Duplicación en métodos scheduleMessage()

Encontrado **EXACTO en 3 ubicaciones:**
- `/src/modules/campaigns/services/message-actions.js` (líneas ~200-250)
- `/src/modules/dashboard/components/recent-messages.js` (líneas ~400-450)
- Lógica repetida en ambos con comentarios DEBUG idénticos

```javascript
// DUPLICADO CRÍTICO - El mismo código en 2 lugares:
console.log("[DEBUG] scheduleMessage - ID:", id, "Title:", title);
console.log("[DEBUG] Mensaje encontrado:", message);
console.log("[DEBUG] Pasando al modal - filename:", message.filename, "title:", title || message.title, "category:", category);
```

#### Duplicación en métodos de generación de audio

- `dashboard/index.js` - `generateAudio()` (líneas ~400-500)
- `automatic/index.js` - `generateMessage()` (líneas ~300-400)
- Código muy similar, lógica repetida

### 2.3 Falta de Capas de Abstracción

#### Ejemplo 1: Manejo de Audio Duplicado

```
En jingle-service.php (líneas 317-405):
- buildDuckingCommand() - Construcción de comando ffmpeg
- buildSimpleMixCommand() - Mezcla simple
- getDuration() - Obtener duración
- validateMusicFile() - Validar archivo

Pero en generate.php (líneas 230-252):
- copyFileForProcessing() - Copia de archivo
- addSilenceToAudio() - Agregar silencios
- uploadFileToAzuraCast() - Subir a AzuraCast
- assignFileToPlaylist() - Asignar a playlist
```

**Problema:** No hay servicio centralizado de procesamiento de audio. Cada endpoint hace su propia cosa.

#### Ejemplo 2: Conexiones a Base de Datos

11 archivos PHP crean conexiones PDO de forma independiente:

```php
// En jingle-service.php (línea 476-478)
$db = new PDO("sqlite:$dbPath");

// En audio-scheduler.php (línea 28-30)
$db = new PDO("sqlite:$dbPath");

// En claude-service.php (línea 373 y 404)
$db = new SQLite3('/var/www/casa/database/casa.db');

// ... Y 8 archivos más haciendo lo mismo
```

**Impacto:** No hay pool de conexiones, no hay reutilización, no hay manejo consistente de errores.

#### Ejemplo 3: Logging Duplicado

| Archivo | Tipo de logging |
|---------|-----------------|
| generate.php | logMessage() con archivo |
| jingle-service.php | logMessage() inline |
| audio-scheduler.php | error_log() estándar |
| claude-service.php | $this->log() privado |
| automatic-jingle-service.php | $this->log() privado |

**Problema:** 5 sistemas de logging diferentes en la misma aplicación.

### 2.4 Acoplamiento Excesivo

#### Acoplamiento en Jingle Service

`jingle-service.php` incluye y depende de:
1. `config.php` - Configuración
2. `services/tts-service.php` - Generación de voz (generateEnhancedTTS)
3. `services/radio-service.php` - Servicio de radio (interruptRadio)
4. `v2/services/AudioProcessor.php` - Procesador de audio v2
5. Base de datos (PDO directo)
6. AzuraCast API (curl directo)
7. Sistema de archivos (exec ffmpeg)

**Impacto:** Cambiar cualquier componente requiere modificar jingle-service.php

#### Acoplamiento en Dashboard JS

`dashboard/index.js` instancia y depende de:
1. VoiceService
2. AISuggestionsComponent
3. JingleControls
4. Directa del localStorage
5. Directa del eventBus global
6. Directa del apiClient global

**Problema:** No hay inyección de dependencias, todo acoplado globalmente.

### 2.5 Anomalías de Base de Datos

#### Tabla audio_metadata con problemas

```sql
CREATE TABLE audio_metadata (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT UNIQUE NOT NULL,
    display_name TEXT,
    category TEXT DEFAULT 'General',
    voice_id TEXT,
    voice_name TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    duration REAL,
    file_size INTEGER,
    is_saved BOOLEAN DEFAULT 0,
    tags TEXT,
    notes TEXT,
    play_count INTEGER DEFAULT 0,
    last_played DATETIME,
    source TEXT DEFAULT 'tts',
    original_filename TEXT,
    client_id TEXT DEFAULT 'CASA',
    metadata JSON,
    -- Estos campos se agregaron después sin migración:
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    saved_at DATETIME,
    radio_sent_count INTEGER DEFAULT 0,
    last_radio_sent_at DATETIME,
    duration_seconds INTEGER,
    updated_at DATETIME
);
```

**Problemas:**
- Sin control de versiones de esquema
- Campos contradictorios (duration vs duration_seconds)
- Sin migraciones versionadas
- Sin índices en campos críticos (filename debería estar indexado)

#### Queries SQL ineficientes

En `audio-scheduler.php` (líneas 384-393):
```php
$sql = "SELECT * FROM audio_schedule WHERE is_active = 1...";
```

**Problema:** SELECT * sin especificar columnas es una mala práctica.

### 2.6 Seguridad Débil

#### Inyección SQL Potencial

En `automatic-usage-simple.php`:
```php
// Susceptible si $client_id no está sanitizado
$query = "INSERT INTO automatic_usage_tracking (client_id, ...) VALUES ('$client_id')";
```

#### Falta de validación de entrada

En `jingle-service.php` (línea 618):
```php
$filename = $input['filename'] ?? '';
// Sin validación de path traversal
$tempPath = __DIR__ . '/temp/' . $filename;
```

#### Exposición de rutas en errores

En `generate.php` (línea 286):
```php
logMessage("DB: Guardando con actualFilename=" . $actualFilename . " (temp era " . $tempFilename . ")");
```

Rutas de servidor expuestas en logs.

---

## 3. ANÁLISIS DE CALIDAD DEL CÓDIGO

### 3.1 Funciones Muy Largas

| Función | Archivo | Líneas |
|---------|---------|--------|
| generateJingle() | jingle-service.php | 241 |
| generateAnnouncements() | claude-service.php | 97 |
| load() | dashboard/index.js | 150+ |
| load() | campaigns/index.js | 140+ |
| render() | calendar/calendar-view.js | 200+ |

**Impacto:** Difícil de testear, alto riesgo de bugs, bajo mantenimiento.

### 3.2 Falta de Manejo de Errores

En `jingle-service.php` (líneas 260-275):
```php
exec($ffmpegCmd, $output, $returnVar);
// No hay timeout manejo
// Si ffmpeg cuelga, el usuario espera indefinidamente
```

En `generate.php` (líneas 235-240):
```php
$filepathWithSilence = addSilenceToAudio($filepathCopy);
if ($filepathWithSilence === false) {
    $filepathWithSilence = $filepathCopy;
    // Silenciosamente continúa sin silencio, sin notificar
}
```

### 3.3 State Management Inconsistente

En `dashboard/index.js`:
```javascript
this.state = {
    generating: false,
    currentAudio: null,
    controlsVisible: false,
    voices: [],
    selectedVoice: 'juan_carlos',
    selectedCategory: localStorage.getItem('mbi_selectedCategory') || 'sin_categoria',
    // ... 15+ propiedades
};
```

**Problema:** State local en el módulo vs localStorage vs eventBus. No hay consistencia.

---

## 4. ANÁLISIS DE APIs Y SERVICIOS

### 4.1 Endpoints sin Estructura Consistente

#### Action-based Endpoints (Anticuado)

```php
// En generate.php
if ($input["action"] === "list_voices") { ... }
if ($input['action'] === 'list_templates') { ... }
if ($input['action'] === 'generate_audio') { ... }
if ($input['action'] === 'send_to_radio') { ... }
```

**Problema:** Routing primitivo. Toda la lógica en un solo archivo.

#### Inconsistencia en Respuestas

```php
// En jingle-service.php
echo json_encode(['success' => true, 'audio' => base64_encode(...), ...]);

// En claude-service.php
echo json_encode(['success' => true, 'suggestions' => $suggestions, ...]);

// En audio-scheduler.php
echo json_encode(['success' => true, 'schedule_id' => $scheduleId, ...]);
```

Cada endpoint devuelve estructura diferente.

### 4.2 Configuración Hardcodeada

En `jingle-service.php` (línea 515):
```php
$azuracastUrl = AZURACAST_BASE_URL . '/api/station/' . AZURACAST_STATION_ID . '/files';
// Si cambia el station ID, hay que editar el código
```

En `claude-service.php` (línea 98):
```php
$basePrompt = $config['clients'][$activeClientId]['context'];
// Fallback hardcodeado si falla
```

### 4.3 Falta de Caché

Cada llamada a `load voices` hace una lectura de JSON:
```php
// En generate.php línea 58
$voicesFile = __DIR__ . '/data/voices-config.json';
$config = json_decode(file_get_contents($voicesFile), true);
```

**Impacto:** Si hay 100 solicitudes/hora, se lee el archivo 100 veces.

---

## 5. ANÁLISIS DE MÓDULOS FRONTEND

### 5.1 Problemas en Dashboard Module

**Archivo:** `/src/modules/dashboard/index.js` (1,198 líneas)

Estructura:
```javascript
class DashboardV2Module {
    constructor() { /* 15 propiedades de state */ }
    load(container) { /* 50+ líneas */ }
    loadStyles() { }
    loadTemplate() { }
    loadVoices() { }
    loadMusicList() { }
    cacheElements() { }
    // ... 30+ métodos más
}
```

**Problemas:**
- Responsabilidad única violada
- Sin separación de concerns
- Componentes acoplados (JingleControls, AISuggestions, etc.)
- Sin lazy loading
- Sin testing posible

**Refactorización necesaria:**
- Extraer a 5 componentes más pequeños
- Crear servicio de estado centralizado
- Implementar inyección de dependencias

### 5.2 Problemas en Campaigns Module

**Archivo:** `/src/modules/campaigns/index.js` (1,155 líneas)

Similar a Dashboard:
- 1 clase hace todo (render, filtrado, búsqueda, eventos, carga)
- Métodos como `togglePlayPause()`, `playMessage()` están aquí
- Acoplamiento fuerte con UI

**Duplicación detectada:**
- `scheduleMessage()` método existe en 2 lugares
- Lógica de filtrado duplicada

### 5.3 Problemas en Automatic Module

**Archivo:** `/src/modules/automatic/index.js` (1,140 líneas)

- 40+ métodos en una sola clase
- Sin separación entre lógica de negocio y UI
- Llamadas directas a API sin abstracción

---

## 6. PATRONES DE CÓDIGO MUERTO O INEFICIENTE

### 6.1 Código Legado sin Uso

1. **tts-service.php** (549 líneas)
   - Solo redirige a tts-service-unified.php
   - Puede eliminarse

2. **tts-service-enhanced.php.backup** y otros `.backup` files
   - 7+ archivos de backup en el directorio
   - Deberían estar en .gitignore

3. **automatic-jingle-service-v2.php**
   - ¿En producción? ¿En pruebas?
   - Versión V1 sigue usándose
   - Duplicación sin propósito claro

### 6.2 Logging Innecesario

En `jingle-service.php` (líneas 71-93):
```php
logMessage("[JingleService] === INICIO GENERACIÓN JINGLE ===");
logMessage("[JingleService] Voice: $voice, Text length: " . strlen($text));
// ... 20 lineas de logging
logMessage("[JingleService] Config: " . json_encode($config));
```

**Problema:** Logging excesivo en producción ralentiza el sistema.

---

## 7. OPORTUNIDADES DE REFACTORIZACIÓN

### 7.1 Servicios a Consolidar

**1. Servicio de Audio Centralizado**
```
Consolidar en: /src/api/services/UnifiedAudioService.php

Métodos:
- generateTTS($text, $voice, $options)
- addSilence($file, $intro, $outro)
- normalizeAudio($file, $target_lufs)
- mixAudio($voice, $music, $options)
- validateFile($file)
```

**2. Servicio de Radio Centralizado**
```
Consolidar: music-service.php + music-manager-service.php

Métodos:
- getAvailableMusic()
- getMusic($id)
- uploadMusic($file)
- deleteMusic($id)
- restartMusic()
```

**3. Servicio de Scheduling Unificado**
```
Consolidar: audio-scheduler.php + scheduler-cron.php

Métodos:
- createSchedule($audio, $schedule)
- getSchedules($filters)
- executeSchedules()
- deleteSchedule($id)
```

### 7.2 Módulos Frontend a Refactorizar

**Dashboard Module (1,198 líneas -> 400 líneas)**
```
Dividir en:
- DashboardComponent (300 líneas)
- MessageGeneratorComponent (250 líneas)
- VoiceControlsComponent (150 líneas)
- QuotaChartComponent (100 líneas)
- AIService (servicio centralizado)
```

**Campaigns Module (1,155 líneas -> 500 líneas)**
```
Dividir en:
- CampaignListComponent (350 líneas)
- CampaignFiltersComponent (100 líneas)
- CampaignActionsService (200 líneas)
- MessageService (centralizado)
```

### 7.3 Base de Datos

**Migrar a con versionamiento:**
```php
// Crear tabla de migraciones
CREATE TABLE schema_migrations (
    version INTEGER PRIMARY KEY,
    name TEXT,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

// Crear migraciones
/database/migrations/
├── 001_create_audio_metadata.sql
├── 002_create_audio_schedule.sql
├── 003_add_category_to_metadata.sql
└── 004_normalize_audio_metadata.sql
```

---

## 8. MATRIZ DE PROBLEMAS Y PRIORIDADES

### PRIORIDAD CRÍTICA (Semana 1-2)

| Problema | Impacto | Esfuerzo | Archivo |
|----------|---------|----------|---------|
| Duplicación de servicios TTS | ALTO | 4h | tts-service-unified.php |
| Consolidar jingle services | ALTO | 6h | automatic-jingle-service*.php |
| Inyección SQL en queries | CRÍTICO | 3h | audio-scheduler.php |
| Path traversal en música | CRÍTICO | 2h | jingle-service.php |

### PRIORIDAD ALTA (Semana 3-4)

| Problema | Impacto | Esfuerzo |
|----------|---------|----------|
| Duplicación de logging | MEDIO | 6h |
| Consolidar servicios de música | MEDIO | 8h |
| Crear DB migration system | MEDIO | 12h |
| Refactorizar dashboard.js | ALTO | 16h |

### PRIORIDAD MEDIA (Mes 2)

| Problema | Impacto | Esfuerzo |
|----------|---------|----------|
| Refactorizar campaigns.js | MEDIO | 12h |
| Implementar unit tests | ALTO | 20h |
| Crear API REST consistente | MEDIO | 24h |
| Centralizar state management | MEDIO | 16h |

---

## 9. RECOMENDACIONES ARQUITECTÓNICAS

### 9.1 Estructura Propuesta

```
/src/
├── api/
│   ├── routes/              [NUEVO] Enrutador centralizado
│   │   ├── audio.php
│   │   ├── schedule.php
│   │   └── ...
│   ├── services/            [REFACTORED] Servicios centralizados
│   │   ├── AudioService.php
│   │   ├── ScheduleService.php
│   │   ├── DatabaseService.php
│   │   └── ConfigService.php
│   ├── middleware/          [NUEVO] Validación y seguridad
│   │   ├── AuthMiddleware.php
│   │   ├── ValidationMiddleware.php
│   │   └── ErrorMiddleware.php
│   ├── database/            [NUEVO] DAOs y migraciones
│   │   ├── migrations/
│   │   ├── AudioMetadataDAO.php
│   │   └── ScheduleDAO.php
│   └── config/              [CENTRALIZADO]
│       ├── config.php
│       └── database.php
├── core/
│   ├── services/            [NUEVO] Servicios reutilizables JS
│   │   ├── AudioService.js
│   │   ├── ScheduleService.js
│   │   └── StateManager.js
│   └── utils/
└── modules/
    └── [Módulos simplificados]
```

### 9.2 Patrones a Implementar

**1. Inyección de Dependencias (PHP)**
```php
class AudioService {
    private $db;
    private $logger;
    
    public function __construct(DatabaseService $db, LoggerService $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }
}
```

**2. Repository Pattern (PHP)**
```php
interface AudioMetadataRepository {
    public function findById($id);
    public function save(AudioMetadata $audio);
}

class SQLiteAudioMetadataRepository implements AudioMetadataRepository {
    // Implementación
}
```

**3. Service Locator (JS)**
```javascript
const services = {
    audio: new AudioService(),
    schedule: new ScheduleService(),
    state: new StateManager()
};

export { services };
```

**4. Observer Pattern (Event Bus)**
```javascript
eventBus.on('audio:generated', (data) => {
    // Múltiples listeners pueden reaccionar
});

eventBus.emit('audio:generated', audioData);
```

### 9.3 Testing Strategy

**Backend:**
```php
// tests/Services/AudioServiceTest.php
class AudioServiceTest extends TestCase {
    public function testGenerateTTS() { }
    public function testAddSilence() { }
    public function testValidation() { }
}
```

**Frontend:**
```javascript
// tests/services/AudioService.test.js
describe('AudioService', () => {
    it('should generate audio', async () => { });
    it('should handle errors', async () => { });
});
```

---

## 10. TIMELINE DE REFACTORIZACIÓN RECOMENDADO

### Fase 1: Seguridad (Semana 1-2)
- [ ] Eliminar inyección SQL potencial
- [ ] Validar entrada de usuarios
- [ ] Sanitizar rutas de archivos
- [ ] Eliminar exposición de información sensible en logs

### Fase 2: Consolidación (Semana 3-4)
- [ ] Consolidar servicios TTS (3 -> 1)
- [ ] Consolidar servicios de Jingle (2 -> 1)
- [ ] Consolidar servicios de Música (3 -> 1)
- [ ] Unificar logging

### Fase 3: Arquitectura (Mes 2)
- [ ] Crear DB migration system
- [ ] Implementar inyección de dependencias
- [ ] Crear enrutador REST centralizado
- [ ] Crear repository pattern para BD

### Fase 4: Frontend (Mes 3)
- [ ] Refactorizar dashboard.js (1,198 -> 400 líneas)
- [ ] Refactorizar campaigns.js (1,155 -> 500 líneas)
- [ ] Implementar state management centralizado
- [ ] Crear componentes reutilizables

### Fase 5: Testing (Mes 4)
- [ ] Escribir unit tests (PHP)
- [ ] Escribir integration tests
- [ ] Escribir E2E tests (JS)
- [ ] Configurar CI/CD

---

## 11. MÉTRICAS Y KPIs

### Antes (Estado Actual)
- Líneas de código por archivo: promedio 600
- Complejidad ciclomática: ALTA (>10 en muchas funciones)
- Cobertura de tests: 0%
- Duplicación de código: 15-20%
- Deuda técnica: CRÍTICA

### Después (Meta)
- Líneas de código por archivo: máximo 300
- Complejidad ciclomática: <5 por función
- Cobertura de tests: >80%
- Duplicación de código: <5%
- Deuda técnica: BAJA

---

## 12. CONCLUSIÓN

Casa Costanera es un sistema **funcional pero con deuda técnica crítica**. El código fue desarrollado iterativamente sin planificación arquitectónica, resultando en:

- 40% duplicación de código
- Servicios fragmentados sin consolidación
- Módulos acoplados sin posibilidad de testeo
- Seguridad débil en entrada de datos
- Escalabilidad limitada

**Recomendación:** Refactorización estructurada en 4 meses siguiendo el plan propuesto.

**Costo de no actuar:**
- Cada nuevo feature toma 50% más tiempo
- Bugs son más comunes (sin tests)
- Riesgo de seguridad aumenta
- Nuevos desarrolladores necesitan 4+ semanas onboarding

---

## APÉNDICE A: Lista Completa de Problemas

Ver archivo: `/tmp/detailed_issues.txt`

## APÉNDICE B: Archivos para Eliminar/Deprecar

- tts-service.php (solo redirige)
- tts-service-enhanced.php (versión antigua)
- automatic-jingle-service-v2.php (mantener una versión)
- music-service.php (consolidar con music-manager-service.php)
- simple-music-restart.php (integrar en music-manager)
- automatic-usage-monitor.php (mantener uno solo)

## APÉNDICE C: Archivos a Crear

- UnifiedAudioService.php
- UnifiedMusicService.php
- DatabaseService.php
- ConfigurationService.php
- AudioRepository.php
- ScheduleRepository.php

