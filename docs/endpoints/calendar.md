# Calendar API

<endpoint_overview>
Sistema de calendario para gestión de eventos, campañas y programación especial.
Soporta eventos únicos, recurrentes y vinculación con audio programado.
</endpoint_overview>

## Endpoints Overview

### GET/POST /calendar-api.php
Endpoint REST principal para operaciones del calendario.

### POST /calendar-service.php
Servicio interno para lógica de negocio del calendario.

<description>
Gestiona eventos del calendario con soporte para recurrencia,
categorización y vinculación con archivos de audio.
</description>

## GET Operations

<get_operations>

### List Events
```bash
curl "http://localhost:4000/src/api/calendar-api.php?action=list&start_date=2025-01-01&end_date=2025-01-31"
```

#### Parameters
- `action` (string): `list` - Listar eventos
- `start_date` (date): Fecha inicio del rango
- `end_date` (date): Fecha fin del rango
- `category` (string): Filtrar por categoría
- `is_active` (boolean): Solo eventos activos

#### Response
```json
{
  "success": true,
  "events": [
    {
      "id": 1,
      "title": "Black Friday Sale",
      "description": "Gran venta de Black Friday en todas las tiendas",
      "event_type": "campaign",
      "start_date": "2025-01-24",
      "end_date": "2025-01-26",
      "start_time": "09:00",
      "end_time": "22:00",
      "recurrence_rule": null,
      "audio_ids": [123, 124, 125],
      "active": true,
      "metadata": {
        "color": "#FF5733",
        "priority": "high",
        "stores": ["Falabella", "Ripley", "Paris"]
      }
    }
  ],
  "count": 1
}
```

### Get Upcoming Events
```bash
curl "http://localhost:4000/src/api/calendar-api.php?action=upcoming&hours=24"
```

#### Parameters
- `action` (string): `upcoming` - Eventos próximos
- `hours` (integer): Horas hacia adelante (default: 24)

### Get Today's Events
```bash
curl "http://localhost:4000/src/api/calendar-api.php?action=today"
```

### Get Active Campaigns
```bash
curl "http://localhost:4000/src/api/calendar-api.php?action=active_campaigns"
```
</get_operations>

## POST Operations

<post_operations>

### Create Event
```bash
curl -X POST "http://localhost:4000/src/api/calendar-api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "client_id": "casa",
    "title": "Venta de Verano",
    "description": "Gran liquidación de temporada de verano",
    "event_type": "campaign",
    "start_date": "2025-02-01",
    "end_date": "2025-02-28",
    "start_time": "10:00",
    "end_time": "21:00",
    "category": "promociones",
    "metadata": {
      "color": "#00B4D8",
      "icon": "sun",
      "discount": "hasta 70%",
      "participating_stores": ["Todas"]
    }
  }'
```

### Create Recurring Event
```bash
curl -X POST "http://localhost:4000/src/api/calendar-api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "client_id": "casa",
    "title": "Happy Hour Patio de Comidas",
    "description": "2x1 en bebidas seleccionadas",
    "event_type": "recurring",
    "start_date": "2025-01-15",
    "end_date": "2025-12-31",
    "start_time": "17:00",
    "end_time": "19:00",
    "recurrence_rule": {
      "frequency": "WEEKLY",
      "interval": 1,
      "by_day": ["MO", "TU", "WE", "TH", "FR"],
      "exceptions": ["2025-12-25", "2025-01-01"]
    },
    "audio_ids": [456, 457]
  }'
```

### Update Event
```bash
curl -X POST "http://localhost:4000/src/api/calendar-api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update",
    "client_id": "casa",
    "event_id": 123,
    "title": "Venta de Verano Extended",
    "end_date": "2025-03-15"
  }'
```

### Delete Event
```bash
curl -X POST "http://localhost:4000/src/api/calendar-api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete",
    "client_id": "casa",
    "event_id": 123
  }'
```

### Link Audio to Event
```bash
curl -X POST "http://localhost:4000/src/api/calendar-api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "link_audio",
    "client_id": "casa",
    "event_id": 123,
    "audio_ids": [789, 790, 791],
    "play_schedule": {
      "interval": 30,
      "unit": "minutes",
      "randomize": true
    }
  }'
```
</post_operations>

<event_types>
## Event Types

### announcement
Anuncios puntuales o informativos
```json
{
  "event_type": "announcement",
  "metadata": {
    "importance": "high",
    "departments": ["customer_service", "security"]
  }
}
```

### campaign
Campañas de marketing y promociones
```json
{
  "event_type": "campaign",
  "metadata": {
    "campaign_id": "BF2025",
    "budget": 50000,
    "target_audience": "all",
    "kpis": {
      "expected_traffic": 10000,
      "conversion_target": 0.15
    }
  }
}
```

### holiday
Días festivos y celebraciones
```json
{
  "event_type": "holiday",
  "metadata": {
    "is_national": true,
    "special_hours": {
      "open": "12:00",
      "close": "20:00"
    }
  }
}
```

### maintenance
Mantenimiento y cierres temporales
```json
{
  "event_type": "maintenance",
  "metadata": {
    "affected_areas": ["parking_level_3", "escalator_north"],
    "estimated_duration": "4 hours",
    "alternative_routes": true
  }
}
```
</event_types>

<recurrence_rules>
## Recurrence Rules (RRULE Format)

### Daily Recurrence
```json
{
  "frequency": "DAILY",
  "interval": 1,
  "count": 30  // Repeat 30 times
}
```

### Weekly Recurrence
```json
{
  "frequency": "WEEKLY",
  "interval": 1,
  "by_day": ["MO", "WE", "FR"],
  "until": "2025-12-31"
}
```

### Monthly Recurrence
```json
{
  "frequency": "MONTHLY",
  "interval": 1,
  "by_month_day": [1, 15],  // 1st and 15th of each month
  "months": 12
}
```

### Complex Pattern
```json
{
  "frequency": "MONTHLY",
  "interval": 1,
  "by_set_pos": 1,  // First occurrence
  "by_day": ["SA"],  // First Saturday of each month
  "exceptions": ["2025-07-05", "2025-12-06"]  // Skip specific dates
}
```
</recurrence_rules>

<integration_examples>
## Integration with Audio System

### Auto-generate Audio for Event
```javascript
async function createEventWithAudio(eventData) {
  // 1. Generate audio announcements
  const audioIds = [];
  
  const announcementTexts = [
    `${eventData.title} comienza el ${eventData.start_date}`,
    `No te pierdas ${eventData.title}. ${eventData.description}`,
    `Último día de ${eventData.title}. ¡Apresúrate!`
  ];
  
  for (const text of announcementTexts) {
    const audio = await fetch('/src/api/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        client_id: 'casa',
        text: text,
        voice: 'Rachel',
        category: eventData.category || 'eventos'
      })
    });
    
    const audioResult = await audio.json();
    if (audioResult.success) {
      audioIds.push(audioResult.data.audio_id);
    }
  }
  
  // 2. Create event with audio links
  const event = await fetch('/src/api/calendar-api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      ...eventData,
      action: 'create',
      audio_ids: audioIds
    })
  });
  
  return event.json();
}
```

### Schedule Event Reminders
```javascript
async function scheduleEventReminders(eventId) {
  const reminders = [
    { days_before: 7, text: "En una semana" },
    { days_before: 3, text: "En tres días" },
    { days_before: 1, text: "Mañana" },
    { days_before: 0, text: "Hoy" }
  ];
  
  const event = await getEvent(eventId);
  
  for (const reminder of reminders) {
    const reminderDate = new Date(event.start_date);
    reminderDate.setDate(reminderDate.getDate() - reminder.days_before);
    
    await fetch('/src/api/audio-scheduler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'create',
        client_id: 'casa',
        audio_id: event.audio_ids[0],
        schedule_type: 'once',
        schedule_data: {
          date: reminderDate.toISOString().split('T')[0],
          time: '10:00'
        }
      })
    });
  }
}
```
</integration_examples>

<calendar_views>
## Calendar View Formats

### Month View Data
```bash
curl "http://localhost:4000/src/api/calendar-api.php?action=month_view&year=2025&month=1"
```

Response format:
```json
{
  "success": true,
  "calendar": {
    "year": 2025,
    "month": 1,
    "weeks": [
      {
        "week_number": 1,
        "days": [
          {
            "date": "2025-01-01",
            "day_of_week": 3,
            "events": [
              {
                "id": 1,
                "title": "Año Nuevo",
                "type": "holiday",
                "all_day": true
              }
            ]
          }
        ]
      }
    ]
  }
}
```

### Week View Data
```bash
curl "http://localhost:4000/src/api/calendar-api.php?action=week_view&date=2025-01-15"
```

### Day View Data
```bash
curl "http://localhost:4000/src/api/calendar-api.php?action=day_view&date=2025-01-15"
```
</calendar_views>

<error_handling>
## Error Handling

### Date Conflict
```json
{
  "success": false,
  "error": "Conflicto de fechas con evento existente",
  "code": "DATE_CONFLICT",
  "details": {
    "conflicting_event_id": 45,
    "conflicting_event_title": "Evento existente"
  }
}
```

### Invalid Recurrence Rule
```json
{
  "success": false,
  "error": "Regla de recurrencia inválida",
  "code": "INVALID_RRULE",
  "details": {
    "error": "BY_DAY values must be MO, TU, WE, TH, FR, SA, or SU"
  }
}
```

### Audio Not Found
```json
{
  "success": false,
  "error": "Audio files no encontrados",
  "code": "AUDIO_NOT_FOUND",
  "details": {
    "missing_ids": [789, 790],
    "found_ids": [791]
  }
}
```
</error_handling>