# Schedule Modal Component

<component_overview>
Modal crítico para programar la reproducción automática de mensajes de audio.
Permite tres tipos de programación: intervalos regulares, horarios específicos y ejecución única.
Ubicación: /src/modules/campaigns/schedule-modal.js
</component_overview>

<critical>
⚡ Este es el componente más importante después de la generación de audio.
Los usuarios pasan mucho tiempo configurando programaciones precisas para sus campañas.
</critical>

## Tipos de Programación

<scheduling_types>

### 🔁 Interval (Más usado)
Reproduce el mensaje cada X minutos/horas dentro de un rango horario.

<parameters>
- interval: 30, 60, 120, 180, 240, 360, 480, 720, 1440 minutos
- days: Array de días [0-6] donde 0=Domingo
- start_time: Hora inicio (ej: "10:00")
- end_time: Hora fin (ej: "20:00")
</parameters>

<example>
// Cada 4 horas, de lunes a viernes, entre 10:00 y 20:00
{
  "schedule_type": "interval",
  "interval": 240,
  "days": [1, 2, 3, 4, 5],
  "start_time": "10:00",
  "end_time": "20:00"
}
</example>

### 📅 Specific Times
Reproduce en horarios exactos específicos.

<parameters>
- times: Array de horas ["14:00", "18:00", "20:00"]
- days: Array de días [0-6]
</parameters>

<example>
// A las 14:00, 18:00 y 20:00 todos los días
{
  "schedule_type": "specific",
  "times": ["14:00", "18:00", "20:00"],
  "days": [0, 1, 2, 3, 4, 5, 6]
}
</example>

### ⏰ Once
Ejecución única en fecha y hora específica.

<parameters>
- datetime: Fecha y hora ISO (ej: "2025-01-15T14:30:00")
</parameters>

<example>
// Una sola vez el 15 de enero a las 14:30
{
  "schedule_type": "once",
  "datetime": "2025-01-15T14:30:00"
}
</example>
</scheduling_types>

## Workflow de Uso

<workflow>
1. Usuario genera audio con TTS/Jingle
2. En Recent Messages, click en botón "📅 Programar"
3. Modal se abre mostrando:
   - Título del mensaje
   - Categoría con emoji y color
   - Tabs para tipo de programación
4. Usuario selecciona tipo y configura
5. Click en "Guardar Programación"
6. Schedule se crea y aparece en calendario
</workflow>

## API Integration

<api_calls>
// Al guardar, el modal llama a:
POST /src/api/audio-scheduler.php
{
  "action": "create",
  "client_id": "casa",
  "audio_file": "tts20250112_143022_Rachel.mp3",
  "schedule_type": "interval",
  "schedule_data": {
    "interval": 240,
    "days": [1,2,3,4,5],
    "start_time": "10:00",
    "end_time": "20:00"
  },
  "category": "ofertas",
  "notes": "Oferta especial de temporada"
}
</api_calls>

## UI Components

<ui_elements>
- **Weekday Selector**: Botones toggle para cada día
- **Time Slots**: Inputs de hora con botón + para agregar más
- **Date Range**: Selector de fecha inicio/fin
- **Notes Field**: Campo opcional para contexto
</ui_elements>

## Category Integration

<categories>
El modal muestra la categoría del mensaje con:
- Emoji distintivo
- Color de fondo específico
- Badge estilizado

Categorías disponibles:
- 🛒 Ofertas (verde)
- 🎉 Eventos (azul)
- ℹ️ Información (cyan)
- 🚨 Emergencias (rojo)
- 🛎️ Servicios (púrpura)
- 🕐 Horarios (naranja)
</categories>

## Common Use Cases

<use_cases>

### Promoción Diaria
```javascript
{
  "schedule_type": "interval",
  "interval": 240,  // Cada 4 horas
  "days": [1, 2, 3, 4, 5, 6],  // Lun-Sáb
  "start_time": "10:00",
  "end_time": "20:00"
}
```

### Anuncio de Almuerzo
```javascript
{
  "schedule_type": "specific",
  "times": ["12:00", "12:30", "13:00"],
  "days": [1, 2, 3, 4, 5]  // Días laborales
}
```

### Evento Especial
```javascript
{
  "schedule_type": "once",
  "datetime": "2025-01-20T15:00:00"
}
```
</use_cases>

## Error Handling

<error_handling>
El modal valida:
- Al menos un día debe estar seleccionado
- Hora fin debe ser mayor que hora inicio
- Al menos un horario en modo "specific"
- Fecha/hora válida en modo "once"

Errores comunes:
- "No se seleccionaron días"
- "Hora de fin debe ser posterior a hora de inicio"
- "Debe agregar al menos un horario"
</error_handling>

## Technical Implementation

<implementation>
```javascript
// Clase principal
export class ScheduleModal {
    constructor() {
        this.modalId = 'scheduleModal';
        this.scheduleType = 'interval';
    }
    
    show(filename, title, category) {
        // Abre modal con datos del mensaje
    }
    
    save() {
        // Valida y envía a API
    }
    
    selectTab(type) {
        // Cambia entre interval/specific/once
    }
}

// Inicialización global
window.scheduleModal = new ScheduleModal();
```
</implementation>