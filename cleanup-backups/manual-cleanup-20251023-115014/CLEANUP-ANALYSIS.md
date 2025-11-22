# Casa Costanera - Análisis de Limpieza

**Fecha:** 2025-10-23
**Status:** 🔍 ANÁLISIS COMPLETO - NO SE HA ELIMINADO NADA AÚN

---

## ⚠️ IMPORTANTE: PRECAUCIONES

- ✅ **Backup creado antes de eliminar**
- ✅ **Análisis detallado de dependencias**
- ✅ **Clasificación por nivel de riesgo**
- ❌ **NO eliminar sin revisar primero**

---

## 📊 RESUMEN

| Categoría | Cantidad | Espacio | Riesgo |
|-----------|----------|---------|--------|
| Archivos duplicados | ~8 | ~10 KB | 🟢 BAJO |
| Archivos de test | ~15 | ~50 KB | 🟢 BAJO |
| Backups antiguos | ~8 dirs | ~2 MB | 🟡 MEDIO |
| Dashboard old | 2 dirs | ~1 MB | 🔴 ALTO |

**Total recuperable:** ~3 MB

---

## 🟢 SEGUROS PARA ELIMINAR (Riesgo Bajo)

### 1. Archivos Duplicados de recent-messages.php

**Archivo CORRECTO (NO TOCAR):**
```
✅ /var/www/casa/src/api/recent-messages.php
✅ /var/www/casa/api/recent-messages.php (symlink al correcto)
```

**Archivos DUPLICADOS (seguros para eliminar):**
```
❌ /var/www/casa/recent-messages.php
   - Copia en raíz, path incorrecto
   - NO es usado por ningún módulo

❌ /var/www/casa/public/recent-messages.php
   - Test simple, no es el real
   - NO es usado por ningún módulo

❌ /var/www/casa/public/api/recent-messages.php
   - Duplicado innecesario
   - El dashboard usa /api/recent-messages.php que apunta a /src/api/
```

### 2. Archivos de Test

**Archivos de test seguros para eliminar:**
```
❌ /var/www/casa/api/test-messages.php
❌ /var/www/casa/src/api/test-jingle.php
❌ /var/www/casa/src/api/test-skip-api.php
❌ /var/www/casa/src/api/test-recent.php
❌ /var/www/casa/src/api/radio-test.php
❌ /var/www/casa/src/api/recent-messages-fix.php (intento de fix, ya no necesario)
❌ /var/www/casa/public/api/test-db.php
❌ /var/www/casa/public/test-php.php
```

**Directorio completo de tests:**
```
❌ /var/www/casa/src/api/v2/tests/
   - test-audio-processor.php
   - test-automatic-v2.php
   - test-v2-direct.php
   - test-volume-comparison.php
   - test-integration.php
   - test-rate-limiter.php
```

**Directorio de tests antiguos:**
```
❌ /var/www/casa/public/Tests Files/
   - test.php
   - test-generate.php
```

### 3. Archivos "old" renombrados

```
❌ /var/www/casa/src/modules/calendar/templates/calendar old.html
❌ /var/www/casa/src/modules/dashboard/services/messages-service old.js
❌ /var/www/casa/src/modules/dashboard-redesign/services/messages-service old.js
```

---

## 🟡 REVISAR ANTES DE ELIMINAR (Riesgo Medio)

### 4. Backups Antiguos de Módulos

**Backups de componentes internos:**
```
⚠️ /var/www/casa/src/modules/calendar/templates.backup-fase2-20250903_140805/
⚠️ /var/www/casa/src/modules/calendar/components.backup-fase2-20250903_140805/
⚠️ /var/www/casa/src/modules/calendar/services.backup-fase2-20250903_140805/
⚠️ /var/www/casa/src/modules/campaigns/services.backup-messageactions-20250903_150253/
```

**Análisis:** Backups de septiembre (1.5 meses). Si el sistema funciona bien, son seguros de eliminar.

### 5. Backup de Playground

```
⚠️ /var/www/casa/public/playground-backup-20250904_200620/
```

**Análisis:** Backup de septiembre. Si `/public/playground/` funciona correctamente, es seguro eliminar.

### 6. Directorios de Backups Generales

```
⚠️ /var/www/casa/storage/cleanup-backups/
⚠️ /var/www/casa/storage/backups/
⚠️ /var/www/casa/backups/
```

**Acción recomendada:** Revisar contenido antes de eliminar. Pueden tener backups importantes.

---

## 🔴 REVISAR CON CUIDADO (Riesgo Alto)

### 7. Dashboard Duplicado

```
🔴 /var/www/casa/src/modules/dashboard-redesign/ (492 KB)
   - Parece ser una versión alternativa del dashboard
   - ¿Está en uso? Verificar primero

🔴 /var/www/casa/src/modules/dashboard.backup-20250912-113625/ (540 KB)
   - Backup del dashboard de septiembre 12
   - SI el dashboard actual funciona bien, se puede eliminar
   - PERO hay que verificar que no se usa nada de aquí
```

**Pregunta crítica:**
- ¿Cuál dashboard está en uso? `/modules/dashboard/` o `/modules/dashboard-redesign/`?
- ¿El backup es de antes del "desastre" que mencionaste?

---

## 📋 PLAN DE LIMPIEZA SEGURO

### Fase 1: Limpieza Conservadora (🟢 Bajo Riesgo)

```bash
# 1. Crear backup completo primero
tar -czf casa-backup-$(date +%Y%m%d-%H%M%S).tar.gz /var/www/casa

# 2. Eliminar duplicados de recent-messages
rm /var/www/casa/recent-messages.php
rm /var/www/casa/public/recent-messages.php
rm /var/www/casa/public/api/recent-messages.php

# 3. Eliminar archivos de test
rm /var/www/casa/api/test-messages.php
rm /var/www/casa/src/api/test-*.php
rm /var/www/casa/src/api/radio-test.php
rm /var/www/casa/src/api/recent-messages-fix.php
rm /var/www/casa/public/api/test-db.php
rm /var/www/casa/public/test-php.php
rm -rf /var/www/casa/src/api/v2/tests/
rm -rf /var/www/casa/public/Tests\ Files/

# 4. Eliminar archivos "old"
find /var/www/casa/src/modules -name "*old.js" -o -name "*old.html" -delete
```

**Espacio recuperado:** ~100 KB
**Riesgo:** Muy bajo

### Fase 2: Limpieza de Backups Antiguos (🟡 Riesgo Medio)

```bash
# Solo ejecutar si el sistema funciona correctamente

# 1. Eliminar backups de componentes (Sep 2025)
rm -rf /var/www/casa/src/modules/calendar/templates.backup-*
rm -rf /var/www/casa/src/modules/calendar/components.backup-*
rm -rf /var/www/casa/src/modules/calendar/services.backup-*
rm -rf /var/www/casa/src/modules/campaigns/services.backup-*

# 2. Eliminar backup de playground (Sep 2025)
rm -rf /var/www/casa/public/playground-backup-*
```

**Espacio recuperado:** ~1.5 MB
**Riesgo:** Medio (asegurar que todo funcione primero)

### Fase 3: Dashboard Duplicado (🔴 ALTO RIESGO - REVISAR PRIMERO)

```bash
# ⚠️ NO EJECUTAR SIN VERIFICAR PRIMERO

# Verificar cuál dashboard está en uso:
# grep -r "dashboard-redesign" /var/www/casa/public/*.html

# Si dashboard-redesign NO está en uso:
# rm -rf /var/www/casa/src/modules/dashboard-redesign/

# Si dashboard actual funciona bien (más de 1 mes):
# rm -rf /var/www/casa/src/modules/dashboard.backup-*
```

**Espacio recuperado:** ~1 MB
**Riesgo:** Alto - Revisar primero

---

## ✅ VERIFICACIONES POST-LIMPIEZA

Después de cada fase, ejecutar:

```bash
# 1. Verificar que el sistema sigue funcionando
./health-check.sh

# 2. Verificar páginas principales
curl http://localhost:4000/index.html
curl http://localhost:4000/automatic-mode.html

# 3. Verificar APIs
curl -X POST http://localhost:4000/api/generate.php -d '{}'

# 4. Verificar dashboard carga correctamente
curl http://localhost:4000/api/recent-messages.php
```

---

## 🎯 RECOMENDACIÓN FINAL

1. **Empezar con Fase 1** (archivos de test y duplicados obvios)
2. **Esperar 1 semana** y verificar que todo funcione
3. **Si todo OK**, proceder con Fase 2 (backups antiguos)
4. **Dashboard duplicado**: Necesitamos **verificar primero** cuál está en uso

---

## 🔍 ANÁLISIS DEL CASO "recent-messages.php"

**Pregunta:** ¿Es necesario recent-messages.php en raíz?

**Respuesta:** ❌ NO

**Razón:**
- El dashboard usa `/api/recent-messages.php`
- Este es un **symlink** que apunta a `/src/api/recent-messages.php`
- El archivo en raíz y en public son **copias abandonadas**
- Path incorrecto: buscan `__DIR__ . '/database/'` (raíz) en vez de ruta correcta

**Conclusión:** Los 3 archivos en raíz/public son seguros de eliminar.

---

**Generado por:** Casa Costanera Health Check System
**Última actualización:** 2025-10-23
