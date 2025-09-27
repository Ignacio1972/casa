# Documentación Técnica: Sistema TTS y Radio Integration

## 1. Arquitectura de Servicios TTS

### ⚠️ IMPORTANTE: Existen DOS servicios TTS diferentes

El sistema tiene dos archivos de servicio TTS que pueden causar confusión:

```
/src/api/services/
├── tts-service.php           # Servicio básico (usado por jingles)
└── tts-service-enhanced.php  # Servicio principal (usado por generate.php)
```

### 1.1 tts-service.php (Básico)
**Usado por:** `jingle-service.php`
**Función principal:** `generateTTS()`
**Características:**
- Versión más simple
- No tiene configuración de output_format
- Usado únicamente para generar jingles

### 1.2 tts-service-enhanced.php (Principal) ⭐
**Usado por:** `announcement-generator.php` → `generate.php`
**Función principal:** `generateEnhancedTTS()`
**Características:**
- Versión completa con todas las features
- Soporta configuración de calidad de audio
- Maneja voice settings avanzados
- **ESTE ES EL QUE SE USA PARA LA MAYORÍA DE GENERACIONES**

### Flujo de llamadas:
```
Dashboard/API → generate.php 
    → AnnouncementGenerator::generateSimple() 
    → generateEnhancedTTS() [tts-service-enhanced.php]
```

## 2. Configuración de Calidad de Audio

### 2.1 Output Format (192kbps)

La calidad del audio se configura en `tts-service-enhanced.php`:

```php
// Líneas 37-44 en tts-service-enhanced.php
$outputFormat = $options['output_format'] ?? 'mp3_44100_192';
$url = ELEVENLABS_BASE_URL . "/text-to-speech/$voiceId?output_format=" . $outputFormat;
```

### ⚠️ CONSIDERACIONES CRÍTICAS:

1. **El formato DEBE ir como query parameter en la URL**, NO en el body del request:
   ```php
   // ✅ CORRECTO
   $url = "https://api.elevenlabs.io/v1/text-to-speech/$voiceId?output_format=mp3_44100_192";
   
   // ❌ INCORRECTO (será ignorado)
   $data['output_format'] = 'mp3_44100_192';
   ```

2. **Restricciones por plan de suscripción:**
   - Plan **Free/Basic**: Solo 128kbps
   - Plan **Creator**: Hasta 192kbps ✅ (Plan actual)
   - Plan **Pro**: PCM y formatos superiores

3. **Formatos disponibles:**
   ```
   mp3_44100_128  # Default (128kbps, mono)
   mp3_44100_192  # Alta calidad (192kbps, mono) - USADO ACTUALMENTE
   mp3_44100_256  # Requiere plan superior
   pcm_44100      # Requiere plan Pro
   ```

### 2.2 Procesamiento Post-Generación

El audio generado pasa por `audio-processor.php`:

```php
// addSilenceToAudio() preserva el bitrate original
$bit_rate = $stream['bit_rate'] ?? '192000';  // Línea 35

// FFmpeg mantiene la calidad al agregar silencios
ffmpeg -c:a libmp3lame -b:a $bit_rate -ac $channels -ar $sample_rate
```

### Verificación de calidad:
```bash
# Verificar bitrate de un archivo generado
ffprobe -v quiet -print_format json -show_streams archivo.mp3 | grep bit_rate
# Debería mostrar: "bit_rate": "192000"
```

## 3. Sistema de Interrupción de Radio

### 3.1 Función interruptRadio()

Ubicación: `/src/api/services/radio-service.php`

```php
function interruptRadio($filename) {
    // 1. Construir URI del archivo en AzuraCast
    $fileUri = "file:///var/azuracast/stations/test/media/Grabaciones/" . $filename;
    
    // 2. Comando Liquidsoap para interrumpir
    $command = "interrupting_requests.push $fileUri";
    
    // 3. Ejecutar vía Docker
    $dockerCommand = 'sudo docker exec azuracast bash -c \'echo "' . $command . '" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock\'';
    
    // 4. Verificar respuesta (debe ser numérica = Request ID)
    $output = shell_exec($dockerCommand);
    return is_numeric(trim($output));
}
```

### ⚠️ IMPORTANTE: No complicar esta función

La función es deliberadamente simple y **NO DEBE**:
- Verificar si el archivo existe localmente
- Intentar subir archivos
- Hacer validaciones complejas

**¿Por qué?** Los archivos ya están en AzuraCast cuando se generan (vía `uploadFileToAzuraCast()` en generate.php)

### 3.2 Flujos de Interrupción

**Desde campañas:**
```
campaigns/index.js 
    → message-actions.js: sendToRadio()
    → POST /api/biblioteca.php {action: 'send_library_to_radio'}
    → sendLibraryToRadio()
    → interruptRadio()
```

**Desde generate.php:**
```
generate.php {action: 'send_to_radio'}
    → interruptRadio() directamente
```

### 3.3 Troubleshooting Interrupción

Si la interrupción falla, verificar:

1. **El archivo existe en Docker:**
   ```bash
   sudo docker exec azuracast ls -la /var/azuracast/stations/test/media/Grabaciones/ | grep "nombre_archivo"
   ```

2. **Liquidsoap está funcionando:**
   ```bash
   sudo docker exec azuracast bash -c 'echo "help" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock'
   ```

3. **Logs de la aplicación:**
   ```bash
   tail -f /var/www/casa/src/api/logs/tts-$(date +%Y-%m-%d).log | grep -i interrupt
   ```

## 4. Diagrama de Arquitectura

```
┌─────────────────────┐
│     Dashboard       │
└──────────┬──────────┘
           │
           ▼
    ┌──────────────┐
    │ generate.php │
    └──────┬───────┘
           │
           ▼
┌──────────────────────┐        ┌────────────────────┐
│ AnnouncementGenerator│───────►│ tts-service-       │
│                      │        │ enhanced.php       │
└──────────────────────┘        └─────────┬──────────┘
                                          │
                                          ▼
                               ┌──────────────────┐
                               │ ElevenLabs API   │
                               │ ?output_format=  │
                               │ mp3_44100_192    │
                               └─────────┬────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ audio-processor  │
                              │ (mantiene 192k)  │
                              └─────────┬────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │ uploadFileToAzuraCast│
                            └──────────────────────┘
```

## 5. Checklist de Verificación

### Para verificar que el audio está en 192kbps:

1. **En logs:** Buscar "CONFIGURANDO ALTA CALIDAD 192kbps"
2. **En archivo generado:**
   ```bash
   ffprobe archivo.mp3 2>&1 | grep "192 kb/s"
   ```

### Para cambiar la calidad:

1. Editar `tts-service-enhanced.php` línea 39:
   ```php
   $outputFormat = $options['output_format'] ?? 'mp3_44100_192';
   // Cambiar a 'mp3_44100_128' para volver a 128kbps
   ```

2. Recargar PHP-FPM si hay opcache:
   ```bash
   sudo service php8.1-fpm reload
   ```

## 6. Notas Finales

- **NO** modificar `tts-service.php` si quieres cambiar la calidad general
- **NO** complicar `interruptRadio()` con verificaciones innecesarias
- **SIEMPRE** usar query parameters para output_format con ElevenLabs
- El sistema ya maneja correctamente el flujo: generar → subir → interrumpir
- Los archivos en 192kbps son ~40-50% más grandes que 128kbps

## 7. Variables de Entorno Relevantes

```bash
# .env o config.php
ELEVENLABS_API_KEY=       # Debe ser de plan Creator o superior para 192kbps
ELEVENLABS_BASE_URL=https://api.elevenlabs.io/v1
AZURACAST_BASE_URL=http://localhost:8080
AZURACAST_STATION_ID=1
PLAYLIST_ID_GRABACIONES=2
```

---

**Última actualización:** 2025-09-17
**Bitrate actual del sistema:** 192kbps mono
**Plan ElevenLabs:** Creator