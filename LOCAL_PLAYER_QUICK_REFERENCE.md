# Player Local - Referencia Rápida

**Fecha**: 2025-11-21 | **Estado**: ✅ Funcional

---

## 📋 Resumen

Sistema implementado para enviar mensajes TTS a un **Player Local** en lugar de AzuraCast. Los mensajes se agregan a una cola basada en archivos JSON que el player local debe monitorear.

---

## 🗂️ Archivos Clave

### Backend Modificado:
```
/var/www/casa/src/api/generate.php
├─ Línea 10: require helper local-player-queue.php
├─ Línea 232: Detecta destination (azuracast o local_player)
├─ Línea 234-275: Lógica de envío a Player Local
└─ Línea 390-454: Nueva acción send_to_local_player
```

### Helper de Cola:
```
/var/www/casa/src/api/helpers/local-player-queue.php
├─ addToLocalPlayerQueue($data) → Agrega mensaje a cola
├─ countLocalPlayerQueue() → Cuenta mensajes pendientes
└─ cleanupLocalPlayerProcessed() → Limpia archivos >24h
```

### Herramientas:
```
/var/www/casa/test-queue.sh → Script de gestión de cola
/var/www/casa/LOCAL_PLAYER_IMPLEMENTATION.md → Documentación completa
```

---

## 📂 Rutas Importantes

### Cola de Mensajes:
```bash
/var/www/casa/database/local-player-queue/
├─ 20251121-222338_6920e66a8103e.json
├─ 20251121-223247_6920e88fa5f89.json
├─ 20251121-224228_6920ead47c99d.json
├─ 20251121-224432_6920eb50cb067.json
└─ 20251121-224452_6920eb643fc0b.json

Total: 5 mensajes pendientes
```

### Archivos Procesados:
```bash
/var/www/casa/database/local-player-processed/
└─ (archivos movidos aquí después de ser reproducidos)
```

### Audio TTS:
```bash
/var/www/casa/src/api/temp/
└─ mensaje_*.mp3 (archivos de audio generados)
```

---

## 🔧 Uso

### 1. Generar TTS para Player Local:
```bash
curl -X POST "http://VPS_IP:2082/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate_audio",
    "text": "Tu mensaje",
    "voice": "G4IAP30yc6c1gK0csDfu",
    "destination": "local_player"
  }'
```

### 2. Enviar archivo existente:
```bash
curl -X POST "http://VPS_IP:2082/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "send_to_local_player",
    "filename": "mensaje_prueba_20251121.mp3"
  }'
```

### 3. Gestionar cola (vía SSH):
```bash
/var/www/casa/test-queue.sh check    # Ver cola
/var/www/casa/test-queue.sh add      # Agregar test
/var/www/casa/test-queue.sh clear    # Limpiar todo
```

---

## 🧪 Resultados de Tests

### Test 1: Helper de Cola
```bash
$ php public/api/test-local-player-simple.php

✓ Mensaje agregado exitosamente
Mensajes en cola: 5
```

### Test 2: Script de Gestión
```bash
$ test-queue.sh check

=== COLA DEL PLAYER LOCAL ===

Total: 5 mensajes

[20251121-222338_6920e66a8103e]
  Texto: Este es un mensaje de prueba...
  Categoría: informativos | Creado: 2025-11-21 22:23:38

[20251121-223247_6920e88fa5f89]
  Texto: Prueba de TTS para Player Local...
  Categoría: informativos | Creado: 2025-11-21 22:32:47

... (3 mensajes más)
```

### Test 3: Estructura de Archivo JSON
```json
{
  "id": "20251121-223247_6920e88fa5f89",
  "text": "Prueba de TTS para Player Local desde el VPS",
  "audio_path": "src/api/temp/mensaje_prueba_20251121_223247.mp3",
  "category": "informativos",
  "type": "announcement",
  "priority": "high",
  "voice_name": "Juan Carlos",
  "created_at": "2025-11-21 22:32:47",
  "destination": "local_player"
}
```

---

## 🎯 Flujo de Trabajo

```
Usuario solicita TTS
        ↓
generate.php detecta destination="local_player"
        ↓
Genera audio con ElevenLabs
        ↓
Procesa audio (silencios)
        ↓
Guarda en /src/api/temp/
        ↓
addToLocalPlayerQueue() crea JSON
        ↓
Archivo guardado en /database/local-player-queue/
        ↓
Player Local monitorea directorio (cada 5s)
        ↓
Lee JSON, descarga audio, reproduce
        ↓
Mueve JSON a /database/local-player-processed/
```

---

## 🔑 Funciones del Helper

### addToLocalPlayerQueue($data)
**Parámetros**:
```php
[
  'text' => string,           // Texto del mensaje
  'audio_path' => string,     // Ruta relativa al audio
  'category' => string,       // informativos, ofertas, etc.
  'type' => string,           // announcement, test
  'priority' => string,       // high, normal
  'voice_name' => string,     // Nombre de la voz
  'destination' => string     // local_player
]
```
**Retorna**: `bool` (true si OK)

### countLocalPlayerQueue()
**Retorna**: `int` (cantidad de archivos .json en cola)

### cleanupLocalPlayerProcessed()
**Acción**: Elimina archivos procesados con >24h de antigüedad

---

## 📊 Estado del Sistema

| Componente | Estado | Archivos |
|------------|--------|----------|
| Helper de Cola | ✅ Funcional | 1 PHP |
| generate.php | ✅ Modificado | +45 líneas |
| Cola de Mensajes | ✅ 5 pendientes | 5 JSON |
| Tests | ✅ Pasando | 3/3 |
| Documentación | ✅ Completa | 2 MD |

---

## ⚠️ Notas Importantes

1. **nginx**: No configurado para servir /src/api/ vía HTTP directo
   - Solución: Usar script `test-queue.sh` o llamar a `generate.php`

2. **Permisos**: La cola debe ser escribible por www-data
   ```bash
   chmod 777 /var/www/casa/database/local-player-queue/
   ```

3. **Limpieza**: Los archivos procesados se eliminan automáticamente después de 24h

4. **Audio**: Los archivos MP3 permanecen en `/src/api/temp/` hasta limpieza manual

---

## 🚀 Pendiente

- [ ] Implementar Player Local (monitoreo de cola)
- [ ] Actualizar Dashboard UI con botón "Enviar a Player Local"
- [ ] Agregar opción en Campaigns Library
- [ ] Sistema de confirmación de reproducción

---

**Versión**: 1.0.0 | **Implementado por**: Claude AI | **Fecha**: 2025-11-21
