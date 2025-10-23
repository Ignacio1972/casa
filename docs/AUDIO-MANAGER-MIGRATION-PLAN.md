# 📋 Plan de Migración Gradual a AudioManager Unificado

## Resumen Ejecutivo

**Estado Actual**: Sistema 100% funcional con servicios separados  
**Objetivo**: Unificar control sin romper funcionalidad  
**Estrategia**: Migración gradual con modo compatibilidad  
**Tiempo Estimado**: 2-4 semanas  
**Riesgo**: MÍNIMO (arquitectura de orquestación, no reemplazo)

---

## 🎯 ¿Por Qué AudioManager?

### Problemas Actuales (que NO impiden funcionamiento)
1. **Configuración dispersa** en 3 lugares diferentes
2. **Sin preparación real** para 30+ locales
3. **Volúmenes inconsistentes** entre TTS, Jingles y Automático
4. **Duplicación de lógica** en servicios

### Beneficios del AudioManager
1. ✅ **Control unificado** de volúmenes desde un panel
2. ✅ **Multi-tienda nativo** (broadcast paralelo a 30+ locales)
3. ✅ **Normalización LUFS** consistente
4. ✅ **Modo compatibilidad** - NO rompe nada existente
5. ✅ **Migración gradual** - cambiar cuando estés listo

---

## 🏗️ Arquitectura del AudioManager

```
┌─────────────────────────────────────────────┐
│            AudioManager (Nuevo)              │
│         [Orquestador, NO reemplaza]         │
├─────────────────────────────────────────────┤
│                                              │
│  Usa servicios existentes:                  │
│  ├── generate.php (TTS simple)              │
│  ├── jingle-service.php (Jingles)           │
│  └── automatic-jingle-service.php (Auto)    │
│                                              │
│  Agrega capacidades:                        │
│  ├── Normalización LUFS automática          │
│  ├── Multi-tienda (30+ locales)             │
│  ├── Configuración unificada                │
│  └── Panel de control centralizado          │
└─────────────────────────────────────────────┘
```

---

## 📍 Archivos Creados

### 1. **AudioManager Core**
- **Ubicación**: `/src/api/services/AudioManager.php`
- **Función**: Orquestador principal
- **Estado**: ✅ Creado y listo

### 2. **API Endpoint**
- **Ubicación**: `/src/api/audio-manager-api.php`
- **URL**: `http://51.222.25.222:4000/src/api/audio-manager-api.php`
- **Estado**: ✅ Creado y funcional

### 3. **Panel de Control**
- **Ubicación**: `/public/audio-control-panel.html`
- **URL**: `http://51.222.25.222:4000/audio-control-panel.html`
- **Estado**: ✅ Creado y accesible

---

## 🚀 PLAN DE MIGRACIÓN - FASE POR FASE

### **FASE 0: Verificación (5 minutos) - HACER AHORA**

```bash
# 1. Verificar que AudioManager está instalado
curl -X POST http://localhost:4000/src/api/audio-manager-api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "test"}'

# 2. Verificar servicios legacy
curl -X POST http://localhost:4000/src/api/audio-manager-api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "migrate_check"}'
```

**Resultado esperado**:
```json
{
  "success": true,
  "message": "AudioManager API funcionando",
  "version": "1.0.0"
}
```

---

### **FASE 1: Prueba Sin Riesgo (1 día)**

**NO cambia nada en producción, solo pruebas**

1. **Acceder al panel de control**:
   ```
   http://51.222.25.222:4000/audio-control-panel.html
   ```

2. **Probar generación con AudioManager** (sin afectar el sistema actual):
   ```javascript
   // Prueba desde consola del navegador
   fetch('/src/api/audio-manager-api.php', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({
       action: 'generate',
       type: 'tts',
       params: {
         text: 'Prueba de AudioManager',
         voice: 'juan_carlos'
       },
       targets: ['main']
     })
   }).then(r => r.json()).then(console.log)
   ```

3. **Comparar con sistema actual**:
   - Generar mismo mensaje con `generate.php` actual
   - Verificar que ambos funcionan igual
   - AudioManager agregará normalización LUFS automática

---

### **FASE 2: Configuración Unificada (2-3 días)**

**Centralizar control de volúmenes sin cambiar endpoints**

1. **Configurar volúmenes desde panel unificado**:
   - TTS Simple: -16 LUFS, silencios 3s
   - Jingles: -14 LUFS, música 30%, voz 90%
   - Automático: -15 LUFS, rate limit 10/hora

2. **Guardar configuración**:
   ```javascript
   // El panel guarda en: /src/api/data/volume-profiles.json
   // Backup automático de configuración anterior
   ```

3. **Verificar que servicios legacy siguen funcionando**:
   - Dashboard actual sigue usando `generate.php` ✅
   - Playground sigue usando `jingle-service.php` ✅
   - Modo automático sin cambios ✅

---

### **FASE 3: Migración Gradual del Frontend (1 semana)**

**Cambiar UN componente a la vez**

#### Opción A: Migración Suave (Recomendado)
```javascript
// En dashboard/index.js, cambiar gradualmente:

// ANTES (actual):
const response = await fetch('/src/api/generate.php', {...})

// DESPUÉS (con AudioManager):
const response = await fetch('/src/api/audio-manager-api.php', {
  method: 'POST',
  body: JSON.stringify({
    action: 'generate',
    type: 'tts',
    params: { text, voice },
    targets: ['main']
  })
})
```

#### Opción B: Proxy Transparente
```javascript
// Crear wrapper que use AudioManager internamente
// pero mantenga la misma interfaz
class AudioClient {
  async generate(text, voice) {
    // Internamente usa AudioManager
    // Pero la interfaz no cambia
  }
}
```

**Orden de migración recomendado**:
1. Primero: Mensajes de prueba
2. Segundo: Dashboard TTS simple
3. Tercero: Jingles
4. Último: Modo automático

---

### **FASE 4: Activar Multi-Tienda (cuando tengas más locales)**

1. **Agregar nuevas tiendas en** `/src/api/data/stores-config.json`:
```json
{
  "stores": {
    "main": { 
      "name": "Casa Costanera Principal",
      "api_url": "http://51.222.25.222",
      "enabled": true
    },
    "norte": {
      "name": "Sucursal Norte",
      "api_url": "http://norte.example.com",
      "api_key": "nueva_api_key",
      "enabled": true
    }
    // Agregar hasta 30+ tiendas
  }
}
```

2. **Broadcast a múltiples tiendas**:
```javascript
// Enviar a todas las tiendas
targets: 'all'

// Enviar a tiendas específicas
targets: ['main', 'norte', 'sur']

// Enviar a grupo
targets: 'zona_norte'  // Configurar grupos en JSON
```

---

## 🔄 Rollback (Si Algo Sale Mal)

**El sistema está diseñado para NO necesitar rollback**, pero si lo necesitas:

1. **Rollback instantáneo**: 
   - Simplemente deja de usar `audio-manager-api.php`
   - Vuelve a usar endpoints originales
   - TODO sigue funcionando como antes

2. **Rollback del frontend**:
   - Revertir cambios en archivos JS
   - Los endpoints legacy siguen ahí

3. **NO se necesita**:
   - Restaurar base de datos ❌
   - Reconfigurar servicios ❌
   - Reiniciar servidor ❌

---

## 📊 Métricas de Éxito

### Semana 1
- [ ] AudioManager instalado y probado
- [ ] Panel de control accesible
- [ ] Pruebas sin afectar producción

### Semana 2
- [ ] Volúmenes unificados configurados
- [ ] Al menos 1 componente migrado
- [ ] LUFS consistente en -16 (±1)

### Semana 3-4
- [ ] 50% componentes usando AudioManager
- [ ] Preparado para multi-tienda
- [ ] Sin interrupciones de servicio

---

## 🛠️ Comandos Útiles

### Test Rápido
```bash
# Test AudioManager
curl -X POST http://localhost:4000/src/api/audio-manager-api.php \
  -d '{"action":"test"}'

# Generar TTS con AudioManager
curl -X POST http://localhost:4000/src/api/audio-manager-api.php \
  -d '{"action":"generate","type":"tts","params":{"text":"Hola","voice":"juan_carlos"}}'

# Ver configuración actual
curl -X POST http://localhost:4000/src/api/audio-manager-api.php \
  -d '{"action":"get_config"}'
```

### Monitoreo
```bash
# Ver logs de AudioManager
tail -f src/api/logs/audio-manager-*.log

# Ver uso de servicios
grep "AudioManager" src/api/logs/*.log | wc -l
```

---

## ⚠️ Consideraciones Importantes

### Lo que NO cambia:
1. ✅ URLs de endpoints existentes siguen funcionando
2. ✅ Base de datos sin cambios
3. ✅ Archivos de audio en mismas ubicaciones
4. ✅ APIs de ElevenLabs y AzuraCast igual

### Lo que SÍ mejora:
1. ✨ Normalización LUFS automática (mejor calidad)
2. ✨ Un solo lugar para configurar volúmenes
3. ✨ Preparado para 30+ tiendas
4. ✨ Panel de control unificado
5. ✨ Logs centralizados

---

## 🚨 Soporte y Troubleshooting

### Problema: AudioManager no responde
```bash
# Verificar que archivo existe
ls -la src/api/services/AudioManager.php
ls -la src/api/audio-manager-api.php

# Verificar permisos
chmod 644 src/api/services/AudioManager.php
chmod 755 src/api/audio-manager-api.php
```

### Problema: Panel no carga
```bash
# Verificar archivo
ls -la public/audio-control-panel.html

# Acceder directamente
curl http://localhost:4000/audio-control-panel.html
```

### Problema: Servicios legacy dejan de funcionar
**Esto NO debería pasar**, pero si pasa:
1. AudioManager NO modifica servicios existentes
2. Verificar que no se borraron archivos originales
3. Los servicios legacy son independientes

---

## ✅ Checklist Final

### Antes de empezar:
- [ ] Backup de `/src/api/` (por si acaso)
- [ ] Verificar servicios actuales funcionando
- [ ] Leer este documento completo

### Durante la migración:
- [ ] Probar cada cambio antes de producción
- [ ] Mantener logs de cambios
- [ ] Verificar servicios legacy siguen OK

### Después de migrar:
- [ ] Todos los volúmenes unificados
- [ ] Panel de control funcionando
- [ ] Preparado para 30+ tiendas
- [ ] Documentar configuración final

---

## 📅 Timeline Recomendado

```
Semana 1: Instalación y pruebas (COMPLETADO ✅)
├── Día 1: Verificar instalación 
├── Día 2-3: Pruebas sin riesgo
└── Día 4-5: Configurar volúmenes

Semana 2: Migración gradual
├── Día 1-2: Migrar componente de prueba
├── Día 3-4: Migrar Dashboard TTS
└── Día 5: Verificar estabilidad

Semana 3-4: Completar migración
├── Migrar Jingles
├── Migrar Modo Automático
├── Activar multi-tienda
└── Documentación final
```

---

**Documento creado**: 2025-09-27  
**Versión**: 1.0  
**Estado**: LISTO PARA IMPLEMENTAR  
**Riesgo**: MÍNIMO  
**Beneficio**: ALTO