# LISTA DETALLADA DE PROBLEMAS - Casa Costanera

## ARCHIVOS PROBLEMÁTICOS ORDENADOS POR SEVERIDAD

### CRÍTICOS (Afectan seguridad o funcionalidad)

#### 1. jingle-service.php (653 líneas)
**Severidad:** CRÍTICA
**Problemas:**
- [ ] Línea 618: Path traversal sin validación en `$filename`
- [ ] Línea 476-490: Guardar archivo sin sanitizar nombre
- [ ] Línea 264-266: `exec()` sin timeout (cuelga si ffmpeg falla)
- [ ] Línea 527-541: CURL múltiples sin manejo de error central
- [ ] Línea 120-157: AudioProcessor está acoplado (namespaced)
- [ ] Línea 95-96: Llamada a función externa `generateEnhancedTTS()` acoplada

**Refactorización:**
- Crear `UnifiedAudioService.php` para consolidar
- Usar clase para gestionar dependencias
- Implementar timeouts y reintentos

#### 2. audio-scheduler.php (635 líneas)
**Severidad:** CRÍTICA
**Problemas:**
- [ ] Línea 28-34: PDO directa sin abstracción
- [ ] Línea 117-119: Logging a archivo manual sin formato consistente
- [ ] Línea 384: SELECT * sin especificar columnas
- [ ] Línea 93-146: Conversión de días está duplicada (copy-paste)
- [ ] Línea 198-276: Lógica de filtrado muy compleja sin funciones helper

**Refactorización:**
- Crear `ScheduleService.php` con repositorio pattern
- Extractar conversión de días a función reutilizable
- Crear `ScheduleFilter` class para lógica compleja

#### 3. generate.php (813 líneas)
**Severidad:** CRÍTICA
**Problemas:**
- [ ] Línea 50-52: Logger especial para playground anidado
- [ ] Línea 56-91: Lógica de voces repetida en múltiples archivos
- [ ] Línea 230-252: Radio service acoplado sin inyección
- [ ] Línea 286: Exposición de rutas en logs
- [ ] Línea 242: Si uploadFileToAzuraCast falla, continúa silencioso

**Refactorización:**
- Crear `VoiceConfigService` centralizado
- Implementar middleware de validación
- Usar try-catch con rollback

#### 4. biblioteca.php (659 líneas)
**Severidad:** CRÍTICA
**Problemas:**
- [ ] Múltiples problemas similares a generate.php
- [ ] Duplicación de lógica de upload
- [ ] Sin paginación para consultas grandes
- [ ] Sin índices en búsquedas

---

### ALTOS (Afectan mantenibilidad y escalabilidad)

#### 5. dashboard/index.js (1,198 líneas)
**Severidad:** ALTA
**Problemas:**
- [ ] Línea 12-55: Clase hace 30+ responsabilidades diferentes
- [ ] Línea 26-42: State con 15+ propiedades sin estructura
- [ ] Línea 81-150+: load() tiene lógica de inicialización compleja
- [ ] Línea ~300-400: Métodos generación audio repetido en otro lugar
- [ ] Línea ~500-600: Event listeners acoplados directamente

**Componentes:**
- MessageGeneratorComponent (200 líneas)
- VoiceControlsComponent (150 líneas)
- QuotaChartComponent (100 líneas)
- RecentMessagesComponent (150 líneas)
- AudioPlayerComponent (100 líneas)

#### 6. campaigns/index.js (1,155 líneas)
**Severidad:** ALTA
**Problemas:**
- [ ] Línea 22-36: Estado sin estructura clara
- [ ] Línea 42-71: load() muy compleja
- [ ] Línea ~200-250: scheduleMessage() DUPLICADA en recent-messages.js
- [ ] Línea ~400-450: Filtrado sin funciones helper
- [ ] Línea ~500-600: Renderizado sin separación de lógica

#### 7. automatic/index.js (1,140 líneas)
**Severidad:** ALTA
**Problemas:**
- [ ] Similar a dashboard/index.js
- [ ] Línea ~300-400: generateMessage() similar a dashboard
- [ ] Línea ~600-700: Lógica de timing muy compleja

#### 8. calendar/calendar-view.js (1,124 líneas)
**Severidad:** ALTA
**Problemas:**
- [ ] Vista monolítica con lógica de negocio
- [ ] Sin separación de event handling
- [ ] Lógica de renderizado mezclada con datos

---

### MEDIOS (Afectan calidad y mantenibilidad)

#### 9. message-actions.js (573 líneas)
**Severidad:** MEDIA
**Problemas:**
- [ ] Línea ~200-250: scheduleMessage() IDÉNTICO en recent-messages.js
- [ ] Método muy largo (>100 líneas) sin funciones helper
- [ ] Acoplamiento con componentes externos

#### 10. recent-messages.js (811 líneas)
**Severidad:** MEDIA
**Problemas:**
- [ ] Duplicación de scheduleMessage()
- [ ] Componente hace: render, filtrado, búsqueda, eventos
- [ ] Sin separación de responsabilidades

#### 11. ai-suggestions.js (580 líneas)
**Severidad:** MEDIA
**Problemas:**
- [ ] Lógica de generación mezclada con componente
- [ ] Sin abstracción de API calls
- [ ] Acoplamiento con claude-service.php

#### 12. schedule-modal.js (522 líneas)
**Severidad:** MEDIA
**Problemas:**
- [ ] Modal monolítico sin componentes hijos
- [ ] Lógica de validación sin abstracción
- [ ] Múltiples responsabilidades

---

## DUPLICACIÓN DE CÓDIGO DETECTADA

### Nivel 1: Código Exacto Duplicado

```
1. scheduleMessage() 
   - /src/modules/campaigns/services/message-actions.js (~50 líneas)
   - /src/modules/dashboard/components/recent-messages.js (~50 líneas)
   DIFERENCIA: 0 líneas - Código EXACTO

2. getDuration() para ffprobe
   - /src/api/jingle-service.php (línea 411-414)
   - /src/api/services/audio-processor.php (similar)
   DIFERENCIA: ~10% - Esencialmente igual

3. getAvailableMusic()
   - /src/api/jingle-service.php (línea 439-442)
   - /src/api/music-service.php (similar)
   DIFERENCIA: ~15% - Lógica duplicada

4. Validación de entrada
   - /src/api/generate.php (línea 36-40)
   - /src/api/jingle-service.php (línea 457-460)
   - /src/api/audio-scheduler.php (línea 583-586)
   DIFERENCIA: 0% - Código idéntico en 3 lugares
```

### Nivel 2: Código Similar (Refactorización Necesaria)

```
5. Generación de audio
   - /src/modules/dashboard/index.js (generateAudio)
   - /src/modules/automatic/index.js (generateMessage)
   - /src/modules/campaigns/index.js (similar)
   SIMILITUD: ~80% - Lógica básica igual, parámetros diferentes

6. Sistema de logging
   - /src/api/generate.php (logMessage)
   - /src/api/jingle-service.php (logMessage)
   - /src/api/audio-scheduler.php (error_log)
   - /src/api/claude-service.php ($this->log)
   - /src/api/automatic-jingle-service.php ($this->log)
   SIMILITUD: ~60% - Propósito igual, implementación diferente

7. Conexiones a BD
   - /src/api/jingle-service.php (línea 476)
   - /src/api/generate.php (línea 262)
   - /src/api/audio-scheduler.php (línea 28)
   - /src/api/claude-service.php (línea 373)
   - /src/api/saved-messages.php (similar)
   - /src/modules/calendar/services/calendar-api.php (similar)
   SIMILITUD: ~70% - Patrón PDO repetido sin abstracción
```

### Nivel 3: Servicios Duplicados

```
8. TTS Service (3 archivos)
   - /src/api/tts-service.php (549 líneas) - WRAPPER
   - /src/api/services/tts-service-unified.php (10,584 líneas) - ACTUAL
   - /src/api/services/tts-service-enhanced.php (575 líneas) - DEPRECATED
   SOLUCIÓN: Mantener solo tts-service-unified.php

9. Jingle Service (2 versiones)
   - /src/api/automatic-jingle-service.php (423 líneas) - V1
   - /src/api/automatic-jingle-service-v2.php (414 líneas) - V2 con LUFS
   DIFERENCIA: V2 usa AudioProcessor v2
   SOLUCIÓN: Consolidar a una versión, deprecar la otra

10. Music Service (3 archivos)
    - /src/api/music-service.php (6,902 líneas)
    - /src/api/music-manager-service.php (13,250 líneas)
    - /src/api/simple-music-restart.php (711 líneas)
    PROPÓSITO: Todos hacen gestión de música
    SOLUCIÓN: Consolidar en music-manager-service.php

11. Ducking Service (3 archivos)
    - /src/api/tts-ducking.php
    - /src/api/tts-ducking-service.php
    - /src/api/tts-ducking-azuracast.php
    PROPÓSITO: Todos reducen música cuando habla voz
    SOLUCIÓN: Consolidar en tts-ducking-service.php
```

---

## VIOLACIONES DE PRINCIPIOS DE DISEÑO

### Single Responsibility Principle (SRP)

```
1. jingle-service.php
   - Genera TTS ✓
   - Mezcla audio ✓
   - Guarda en BD ✓
   - Sube a AzuraCast ✓
   - Asigna a playlist ✓
   DEBERÍA: 5 servicios separados

2. dashboard/index.js
   - Gestiona estado ✓
   - Renderiza UI ✓
   - Genera audio ✓
   - Maneja eventos ✓
   - Carga datos ✓
   - Gestiona voces ✓
   DEBERÍA: 6 componentes separados

3. generate.php
   - Valida entrada ✓
   - Genera TTS ✓
   - Procesa audio ✓
   - Sube a radio ✓
   - Guarda en BD ✓
   DEBERÍA: 5 servicios separados
```

### Open/Closed Principle (OCP)

```
Problema: Agregar nuevo servicio de TTS requiere modificar:
- jingle-service.php (línea 96)
- generate.php (línea 179-183)
- automatic-jingle-service.php (línea 10)
- Múltiples módulos JS

Solución: Crear interfaz/abstract class AudioGeneratorService
```

### Dependency Inversion Principle (DIP)

```
Problema: jingle-service.php depende de:
- config.php (directo)
- services/tts-service.php (directo)
- services/radio-service.php (directo)
- v2/services/AudioProcessor.php (directo)

Solución: Inyectar dependencias en constructor
```

---

## PROBLEMAS DE PERFORMANCE

### 1. Lectura Repetida de Configuración

```php
// Ocurre en CADA REQUEST:
$voicesFile = __DIR__ . '/data/voices-config.json';
$config = json_decode(file_get_contents($voicesFile), true);
```

**Impacto:** Si hay 100 requests/hora = 100 lecturas de archivo
**Solución:** Cachear en APCu o Redis

### 2. Sin Paginación en Listados

```php
// En biblioteca.php y saved-messages.php
$stmt = $db->query("SELECT * FROM audio_metadata");
$messages = $stmt->fetchAll();
```

**Impacto:** Si hay 10,000 registros, cargar todos en memoria
**Solución:** Implementar LIMIT OFFSET

### 3. Queries Sin Índices

```sql
-- audio_metadata no tiene índice en estos campos usados frecuentemente:
- filename (está UNIQUE pero no es índice)
- category (usado en filtrado)
- is_saved (usado en filtrado)
- created_at (usado en ordenamiento)
```

**Solución:** Agregar índices faltantes

### 4. N+1 Queries Potencial

```javascript
// En dashboard.js load()
await this.loadVoices();     // 1 query
await this.loadMusicList();   // 1 query  
await this.loadMessages();    // 1 query
```

**Impacto:** Si se cargan simultáneamente, 3 requests
**Solución:** Agregar endpoint /api/dashboard que devuelva todo

---

## VULNERABILIDADES DE SEGURIDAD

### 1. CRÍTICO: Path Traversal

**Archivo:** jingle-service.php (línea 618-622)
```php
$filename = $input['filename'] ?? '';
// Sin validación
$tempPath = __DIR__ . '/temp/' . $filename;
```

**Ataque:** 
```
POST /api/jingle-service.php
{"action": "send_to_radio", "filename": "../../etc/passwd"}
```

**Fix:**
```php
$filename = basename($input['filename']);
if (!preg_match('/^[a-zA-Z0-9_\-\.mp3]+$/', $filename)) {
    throw new Exception('Invalid filename');
}
```

### 2. ALTO: Inyección SQL Potencial

**Archivo:** automatic-usage-simple.php
```php
$client_id = $_GET['client_id'] ?? 'default';
// Usado directamente en query sin sanitizar
```

**Fix:** Usar prepared statements siempre

### 3. ALTO: Exposición de Información Sensible

**Archivo:** generate.php (línea 286)
```php
logMessage("DB: Guardando con actualFilename=" . $actualFilename);
```

**Problema:** Rutas internas expuestas en logs
**Fix:** Usar claves genéricas en logs

### 4. MEDIO: CSRF No Mitigado

Todos los endpoints aceptan cualquier origen (CORS *)

**Fix:**
```php
header('Access-Control-Allow-Origin: https://trusted-domain.com');
// Validar token CSRF
```

### 5. MEDIO: Rate Limiting Débil

No hay rate limiting real en endpoints críticos

**Fix:** Implementar basado en IP + usuario

---

## BUGS POTENCIALES

### 1. Audio Colgado (Cuelga infinitamente)

**Archivo:** jingle-service.php (línea 264-266)
```php
exec($ffmpegCmd, $output, $returnVar);
// Si ffmpeg cuelga, usuario espera indefinidamente
```

**Fix:** Agregar timeout:
```php
exec("timeout 60 " . $ffmpegCmd, $output, $returnVar);
```

### 2. Silent Failures en Audio Processing

**Archivo:** generate.php (línea 235-240)
```php
$filepathWithSilence = addSilenceToAudio($filepathCopy);
if ($filepathWithSilence === false) {
    $filepathWithSilence = $filepathCopy;
    // Continúa sin notificar al usuario
}
```

**Fix:** Reporte de error o retry

### 3. Inconsistencia en Estado de BD

Cuando se genera audio:
- Se guarda en audio_metadata en generate.php
- Se guarda OTRA VEZ en jingle-service.php
- Se puede guardar en saved-messages.php

**Impacto:** Registros duplicados, histórico inconsistente

### 4. Race Condition en Scheduling

**Archivo:** audio-scheduler.php (línea 445-454)
```php
$last_executed = getLastExecution($schedule['id']);
// Aquí otra request podría ejecutar el mismo schedule
// No hay lock
$should_execute = true;
```

**Fix:** Usar transacciones SQLite con SERIALIZABLE

### 5. Timezone Issues

```php
date_default_timezone_set('America/Santiago');  // En generate.php
date_default_timezone_set('America/Santiago');  // En audio-scheduler.php
// Hardcodeado, no configurable
```

**Fix:** Usar config centralizado

---

## DEUDA TÉCNICA ESTIMADA

### Por Tipo

```
Duplicación de código:     35 horas
Refactorización de clases: 120 horas
Security fixes:            15 horas
Testing:                   80 horas
Database schema:           20 horas
Frontend refactor:         150 horas
Documentation:             40 horas
─────────────────────────
Total:                     460 horas
```

### Por Severidad

```
CRÍTICA (bloquea):    80 horas
ALTA (degrada):       180 horas
MEDIA (molesta):      150 horas
BAJA (técnica):       50 horas
```

### Timeline de Eliminación

```
Mes 1: Seguridad + Consolidación   (95h)
Mes 2: Arquitectura + BD           (80h)
Mes 3: Frontend                    (150h)
Mes 4: Testing + Docs              (135h)
```

