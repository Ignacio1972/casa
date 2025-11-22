# Implementación del Sistema de Player Local

## 📋 Resumen

Se implementó exitosamente el sistema para enviar mensajes TTS al **Player Local** en lugar de AzuraCast, permitiendo que el player local (corriendo en otro dispositivo) detecte y reproduzca los mensajes automáticamente.

---

## ✅ Cambios Implementados

### 1. Modificaciones en `generate.php`

#### **Helper agregado** (línea 10):
```php
require_once __DIR__ . '/helpers/local-player-queue.php';
```

#### **Nuevo parámetro `destination`** en `generate_audio`:

El endpoint `generate_audio` ahora acepta un parámetro opcional `destination`:

- **`azuracast`** (default): Comportamiento original - sube a AzuraCast
- **`local_player`**: Nueva funcionalidad - agrega a cola local

**Ejemplo de uso**:
```bash
curl -X POST "http://localhost:2082/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate_audio",
    "text": "Tu mensaje aquí",
    "voice": "G4IAP30yc6c1gK0csDfu",
    "category": "informativos",
    "destination": "local_player"
  }'
```

#### **Nueva acción `send_to_local_player`**:

Similar a `send_to_radio`, pero envía archivos ya existentes a la cola del player local.

**Ejemplo de uso**:
```bash
curl -X POST "http://localhost:2082/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "send_to_local_player",
    "filename": "mensaje_promocion_juan_carlos_20251121_143022.mp3"
  }'
```

**Respuesta**:
```json
{
  "success": true,
  "message": "Anuncio agregado a la cola del Player Local",
  "queue_count": 2
}
```

---

## 🔄 Flujo de Trabajo

### Opción 1: Generar y enviar directamente al Player Local

```
Usuario en Dashboard
  ↓
Selecciona "Enviar a Player Local"
  ↓
Genera TTS con ElevenLabs
  ↓
Procesa audio (silencios)
  ↓
Guarda archivo con nombre descriptivo
  ↓
Agrega a cola del Player Local
  ↓
Player Local detecta nuevo mensaje
  ↓
Reproduce automáticamente
```

### Opción 2: Enviar archivo ya existente al Player Local

```
Usuario tiene archivo en biblioteca
  ↓
Click "Enviar a Player Local"
  ↓
Sistema lee metadata de BD
  ↓
Agrega a cola del Player Local
  ↓
Player Local detecta y reproduce
```

---

## 📂 Estructura de la Cola

### Directorio de Cola:
```
/var/www/casa/database/local-player-queue/
```

### Formato de archivos:
Cada mensaje se guarda como un archivo JSON:

**Nombre**: `YYYYMMDD-HHMMSS_[unique_id].json`

**Ejemplo**: `20251121-223247_6920e88fa5f89.json`

**Contenido**:
```json
{
  "id": "20251121-223247_6920e88fa5f89",
  "text": "Prueba de TTS para Player Local desde el VPS",
  "audio_path": "src/api/temp/mensaje_prueba_juan_carlos_20251121_223247.mp3",
  "category": "informativos",
  "type": "announcement",
  "priority": "high",
  "duration": null,
  "voice_name": "Juan Carlos",
  "created_at": "2025-11-21 22:32:47",
  "destination": "local_player"
}
```

---

## 🎯 Lógica del Player Local (a implementar)

El player local debe:

1. **Monitorear** el directorio `/var/www/casa/database/local-player-queue/`
2. **Detectar** nuevos archivos `.json`
3. **Leer** el archivo JSON para obtener metadata
4. **Descargar/acceder** al archivo de audio usando `audio_path`
5. **Reproducir** el audio
6. **Mover** el archivo procesado a `/var/www/casa/database/local-player-processed/`

### Ejemplo de implementación (pseudocódigo):

```javascript
// En el player local
async function checkQueue() {
  const queueFiles = await fetchQueueFiles();

  for (const file of queueFiles) {
    const metadata = await fetchJSON(file);
    const audioUrl = `http://VPS_IP:2082/${metadata.audio_path}`;

    await playAudio(audioUrl, metadata);
    await markAsProcessed(file);
  }
}

// Ejecutar cada 5 segundos
setInterval(checkQueue, 5000);
```

---

## 🧪 Testing

### Test Manual Simple:

```bash
php /var/www/casa/test-local-player-simple.php
```

**Resultado esperado**:
```
✓ Mensaje agregado exitosamente a la cola
Mensajes en cola: 2
```

### Verificar Cola:

```bash
ls -la database/local-player-queue/
```

### Ver Contenido de un Mensaje:

```bash
cat database/local-player-queue/20251121-223247_6920e88fa5f89.json
```

---

## 📊 Estado Actual

✅ **Completado**:
- [x] Helper `local-player-queue.php` funcional
- [x] Modificación de `generate.php` con soporte de `destination`
- [x] Nueva acción `send_to_local_player`
- [x] Tests de integración exitosos
- [x] Cola funcionando correctamente

⏳ **Pendiente**:
- [ ] Actualizar Dashboard para incluir opción "Enviar a Player Local" en UI
- [ ] Implementar player local que monitoree la cola
- [ ] Agregar botón visual en Recent Messages
- [ ] Agregar opción en Campaigns Library

---

## 🔧 Funciones del Helper

### `addToLocalPlayerQueue($data)`

Agrega un mensaje a la cola del player local.

**Parámetros**:
```php
[
  'text' => 'Texto del mensaje',
  'audio_path' => 'ruta/al/archivo.mp3',
  'category' => 'informativos',
  'type' => 'announcement',
  'priority' => 'high',
  'voice_name' => 'Juan Carlos',
  'destination' => 'local_player'
]
```

**Retorna**: `true` si se agregó correctamente, `false` si hubo error.

### `countLocalPlayerQueue()`

Cuenta los mensajes pendientes en la cola.

**Retorna**: Número de archivos `.json` en la cola.

### `cleanupLocalPlayerProcessed()`

Limpia archivos procesados con más de 24 horas de antigüedad.

---

## 📝 Logs

Los logs del sistema se guardan en:

```
/var/www/casa/src/api/logs/tts-YYYY-MM-DD.log
```

Buscar entradas relacionadas con Player Local:

```bash
grep -i "player local" src/api/logs/tts-*.log
```

**Ejemplos de logs**:
```
[2025-11-21 22:32:47] Destino: Player Local - Agregando a cola local
[2025-11-21 22:32:47] Audio agregado a cola del Player Local exitosamente: mensaje_prueba_juan_carlos_20251121_223247.mp3
```

---

## 🚀 Próximos Pasos

1. **Actualizar Dashboard** para agregar botón "Enviar a Player Local" en la UI
2. **Implementar Player Local** (aplicación separada que monitorea la cola)
3. **Agregar opción en Campaigns** para enviar mensajes guardados al player local
4. **Implementar sistema de confirmación** cuando el player local procesa un mensaje
5. **Agregar métricas** de uso del player local

---

## 💡 Ventajas del Sistema

- ✅ **No afecta AzuraCast**: El flujo original se mantiene intacto
- ✅ **Flexible**: Puede enviar a radio O a player local
- ✅ **Simple**: Cola basada en archivos JSON (fácil de monitorear)
- ✅ **Escalable**: Múltiples players locales pueden leer la misma cola
- ✅ **Metadata completa**: Incluye toda la información del mensaje
- ✅ **Prioridades**: Soporte para alta/normal prioridad
- ✅ **Limpieza automática**: Archivos procesados se eliminan después de 24h

---

**Fecha de implementación**: 2025-11-21
**Versión**: 1.0.0
**Estado**: ✅ Funcional - Listo para integrar con Player Local
