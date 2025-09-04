# 📋 HANDOVER TÉCNICO: REFACTORIZACIÓN MÓDULO CALENDAR

**Proyecto:** Casa Costanera - Sistema de Radio Automatizada  
**Módulo:** Calendar (Programación de Audios)  
**Estado:** Fase 1 COMPLETADA ✅ - Fase 2 y 3 PENDIENTES  
**Fecha:** 2025-09-03  
**Backup:** `index.js.backup-refactor-20250903_133143`

---

## 🎯 **RESUMEN EJECUTIVO**

La **Fase 1** de refactorización está **100% completada y testeada**. Se extrajeron exitosamente:
- ✅ `ScheduleService` (250+ líneas de lógica de negocio)
- ✅ `CalendarStateManager` (300+ líneas de gestión de estado)
- ✅ Tests automáticos ejecutados con éxito (4/4 passed)

**FALTA POR HACER:** Fase 2 (Componentes UI) y Fase 3 (Refactor del controlador principal)

---

## 🚨 **PUNTOS CRÍTICOS - NO ROMPER**

### **1. COMPATIBILIDAD ABSOLUTA**
- ❗ **JAMÁS cambiar la API pública del módulo** - otros módulos dependen de ella
- ❗ **Mantener mismo DOM structure** - CSS y otros scripts dependen de IDs específicos
- ❗ **Preservar event listeners globales** - `window.calendarModule.*` debe seguir funcionando
- ❗ **No modificar eventos emitidos** - `eventBus.emit('calendar:*')` deben mantenerse

### **2. DEPENDENCIAS CRÍTICAS**
- ❗ **Event Bus** - Sistema central de comunicación entre módulos
- ❗ **ApiClient** - NO usar fetch directo, usar apiClient para consistencia
- ❗ **FullCalendar** - Librería externa integrada en calendar-view.js
- ❗ **Styles-v5** - Sistema de CSS global, no introducir CSS inline

### **3. INTEGRACIONES EXTERNAS**
- ❗ **Campaign Module** - Emite eventos que Calendar escucha
- ❗ **Audio Scheduler API** - Backend crítico `/api/audio-scheduler.php`
- ❗ **Database** - SQLite con tablas `audio_schedule`, `audio_metadata`, `calendar_events`

---

## 📁 **ARCHIVOS CLAVE**

### **✅ COMPLETADOS (NO TOCAR)**
```
calendar/services/
├── schedule-service.js     ⭐ NUEVO - Lógica schedules (TESTEADO)
├── calendar-state.js       ⭐ NUEVO - Gestión estado (TESTEADO)
└── calendar-api.js         ✅ EXISTE - Mejorarlo si necesario

calendar/components/
├── calendar-view.js        ✅ OK - Mantener intacto (862 líneas)
└── calendar-filters.js     ✅ OK - Mantener intacto

calendar/templates/
└── calendar.html           ✅ OK - Mantener estructura DOM
```

### **⚠️ ARCHIVO PRINCIPAL (CUIDADO EXTREMO)**
```
calendar/index.js           🎯 TARGET - 1,189 líneas a refactorizar
```

### **❌ ARCHIVOS PROBLEMÁTICOS**
```
calendar/components/
├── event-modal.js          ❌ DISABLED - Stub vacío
└── event-list.js          ❌ EMPTY - Archivo vacío
```

---

## 🏗️ **FASE 2: COMPONENTES UI - PENDIENTE**

### **2.1 SchedulesList Component** ⭐ **CRÍTICO**
**Ubicación:** `calendar/components/schedules-list.js`

**Responsabilidad:**
- Renderizar tabla HTML de schedules (extraer de `index.js:315-420`)
- Manejar acciones de botones (toggle/delete/edit)
- Integrar con `ScheduleService`

**Puntos críticos:**
```javascript
// ❗ MANTENER estos IDs - CSS depende de ellos
const container = document.getElementById('schedules-table-container');

// ❗ PRESERVAR structure HTML exacto
<div class="schedule-item" data-id="${schedule.id}">
  <div class="schedule-header">...</div>
  <div class="schedule-content">...</div>
  <div class="schedule-actions">...</div>
</div>

// ❗ MANTENER window global methods
window.calendarModule.toggleScheduleStatus(id, activate)
window.calendarModule.deleteScheduleFromList(id)
window.calendarModule.editSchedule(id)
```

### **2.2 CalendarLoader Component** ⭐ **SIMPLE**
**Ubicación:** `calendar/components/calendar-loader.js`

**Responsabilidad:**
- Estados de loading (extraer de `index.js:1172`)
- Mensajes de error/éxito
- Spinners y feedback visual

**Puntos críticos:**
```javascript
// ❗ MANTENER estos selectores exactos
const loader = this.container.querySelector('.calendar-loading');
```

### **2.3 ScheduleActions Component** ⭐ **OPCIONAL**
**Ubicación:** `calendar/components/schedule-actions.js`

**Responsabilidad:**
- Botones de acción de schedules
- Confirmaciones y modales
- Feedback de acciones

---

## 🏗️ **FASE 3: REFACTOR CONTROLADOR - PENDIENTE**

### **3.1 Nuevo index.js (Coordinador Ligero)**

**Objetivo:** Reducir de 1,189 líneas a 200-300 líneas

**Estructura objetivo:**
```javascript
export default class CalendarModule {
    constructor() {
        // ❗ MANTENER estas propiedades públicas
        this.name = 'calendar';
        this.container = null;
        
        // ⭐ NUEVO: Inyección de dependencias
        this.scheduleService = new ScheduleService();
        this.stateManager = new CalendarStateManager();
        this.schedulesList = new SchedulesList();
        this.calendarView = new CalendarView();
        this.loader = new CalendarLoader();
    }
    
    // ❗ MANTENER API pública exacta
    getName() { return this.name; }
    async load(container) { /* Solo coordinación */ }
    async unload() { /* Limpieza */ }
}
```

### **Métodos a MIGRAR (no eliminar):**
```javascript
// ❗ ESTAS FUNCIONES DEBEN MANTENERSE (migrar a componentes)
loadSchedulesList()      → SchedulesList.load()
toggleScheduleStatus()   → ScheduleService.toggleStatus() 
deleteScheduleFromList() → SchedulesList.handleDelete()
confirmDeleteSchedule()  → ScheduleActions.confirmDelete()
editSchedule()          → ScheduleActions.openEditModal()
loadAvailableFiles()    → CalendarStateManager.loadFiles()
```

---

## 🔄 **ESTRATEGIA DE MIGRACIÓN SEGURA**

### **Paso 1: Desarrollo Paralelo** ✅ **OBLIGATORIO**
```javascript
// En index.js - Usar AMBOS sistemas temporalmente
constructor() {
    // ⭐ Código viejo (mantener funcionando)
    this.availableFiles = [];
    
    // ⭐ Código nuevo (agregar gradualmente)
    this.scheduleService = new ScheduleService();
    this.stateManager = new CalendarStateManager();
}
```

### **Paso 2: Migración por Método** ✅ **OBLIGATORIO**
```javascript
// Ejemplo de migración segura
async loadSchedulesList() {
    // FASE TRANSICIÓN: Usar nuevo servicio
    try {
        const schedules = await this.scheduleService.loadSchedulesList();
        // Llamar componente nuevo
        this.schedulesList.render(schedules);
    } catch (error) {
        // FALLBACK: Código viejo si falla
        return this.loadSchedulesListOLD();
    }
}
```

### **Paso 3: Verificación Continua** ✅ **OBLIGATORIO**
- ❗ **Testear después de cada método migrado**
- ❗ **Verificar console.log - no deben aparecer errores**
- ❗ **Verificar funcionalidad en browser**
- ❗ **Probar integración con Campaign Module**

---

## 🧪 **TESTING REQUERIDO**

### **Test Manual Obligatorio:**
```
1. ✅ Cargar módulo calendario - debe aparecer sin errores
2. ✅ Ver tabla de schedules - debe mostrar programaciones
3. ✅ Botón play/pause - debe cambiar estado
4. ✅ Botón eliminar - debe mostrar confirmación
5. ✅ Botón editar - debe abrir modal/funcionalidad
6. ✅ Cambio de categoría desde Campaign - debe reflejarse
7. ✅ Event listeners globales - window.calendarModule.*
```

### **Test de Integración:**
```javascript
// Verificar que estos eventos sigan funcionando
eventBus.emit('schedule:created')
eventBus.emit('schedule:updated') 
eventBus.emit('schedule:deleted')
eventBus.emit('schedule:category:updated')
```

---

## 💡 **RECOMENDACIONES TÉCNICAS**

### **1. Order de Implementación Sugerido:**
1. **SchedulesList** primero (más crítico)
2. **CalendarLoader** segundo (más simple)
3. **Refactor index.js** último (más complejo)

### **2. Patrón de Desarrollo:**
```javascript
// ✅ BUENO: Inyección de dependencias
class SchedulesList {
    constructor(container, scheduleService, eventBus) {
        this.container = container;
        this.scheduleService = scheduleService;
        this.eventBus = eventBus;
    }
}

// ❌ MALO: Dependencias hardcodeadas  
class SchedulesList {
    constructor() {
        this.scheduleService = new ScheduleService(); // ❌
    }
}
```

### **3. Manejo de Errores:**
```javascript
// ✅ SIEMPRE incluir fallback al código viejo
try {
    await this.newMethod();
} catch (error) {
    console.warn('[Calendar] New method failed, using fallback:', error);
    return this.oldMethod();
}
```

### **4. Performance:**
```javascript
// ✅ Lazy loading de componentes grandes
async initializeSchedulesList() {
    if (!this.schedulesList) {
        const { SchedulesList } = await import('./components/schedules-list.js');
        this.schedulesList = new SchedulesList(this.container, this.scheduleService);
    }
}
```

---

## 🚨 **SEÑALES DE ALERTA**

### **❌ SÍNTOMAS DE PROBLEMAS:**
- Console errors al cargar calendario
- Tabla de schedules no aparece
- Botones no responden
- Eventos desde Campaign no se reflejan
- Window.calendarModule.* undefined
- FullCalendar no se renderiza

### **🆘 ROLLBACK INMEDIATO SI:**
- Más de 2 funcionalidades rotas simultáneamente
- Calendar no carga completamente
- Errores de integración con otros módulos
- Performance significativamente degradada

**COMANDO ROLLBACK:**
```bash
cp /var/www/casa/src/modules/calendar/index.js.backup-refactor-20250903_133143 /var/www/casa/src/modules/calendar/index.js
```

---

## 📞 **CONTACTO Y DOCUMENTACIÓN**

### **Servicios ya implementados (consultar si necesario):**
- ✅ `ScheduleService` - Completamente testeado y funcional
- ✅ `CalendarStateManager` - Gestión de estado y cache operativo

### **Referencias clave:**
- **Event Bus:** `/src/core/event-bus.js`
- **API Client:** `/src/core/api-client.js` 
- **Original Calendar:** `index.js.backup-refactor-20250903_133143`

### **Logs de debugging importantes:**
```javascript
console.log('[Calendar] Loading module...'); // Inicio load
console.log('[Calendar] Module loaded successfully'); // Fin exitoso
console.error('[Calendar] Load failed:', error); // Error crítico
```

---

## ⏱️ **ESTIMACIÓN DE TIEMPO**

- **Fase 2 (Componentes UI):** 6-8 horas
- **Fase 3 (Refactor controlador):** 4-6 horas  
- **Testing y ajustes:** 2-3 horas
- **Total restante:** 12-17 horas

---

## 🎯 **CRITERIO DE ÉXITO**

### **Fase 2 Completa cuando:**
- ✅ SchedulesList renderiza tabla correctamente
- ✅ Todos los botones funcionan (play/pause/delete/edit)
- ✅ CalendarLoader muestra estados apropiados
- ✅ No hay regresiones funcionales

### **Fase 3 Completa cuando:**
- ✅ index.js reducido a 200-300 líneas
- ✅ Todas las funcionalidades preservadas
- ✅ Performance igual o mejor
- ✅ Código más mantenible y testeable

---

**¡La base está sólida! Los servicios funcionan perfectamente. Ahora es tiempo de construir los componentes UI sobre esta fundación sólida.** 🚀

---
*Documento generado el 2025-09-03 por Claude Code Assistant*