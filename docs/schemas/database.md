# Database Schema Documentation

<database_overview>
Sistema de base de datos SQLite con soporte multi-tenant.
Ubicación: /var/www/casa/database/casa.db
Engine: SQLite 3
</database_overview>

## Core Tables

<table_schema>

### audio_metadata
Almacena información de todos los archivos de audio generados.

```sql
CREATE TABLE audio_metadata (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id VARCHAR(50) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    text TEXT NOT NULL,
    voice VARCHAR(100),
    voice_id VARCHAR(100),
    category VARCHAR(100),
    duration FLOAT,
    file_size INTEGER,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audio_client ON audio_metadata(client_id);
CREATE INDEX idx_audio_category ON audio_metadata(category);
CREATE INDEX idx_audio_created ON audio_metadata(created_at);
```

### audio_schedule
Gestiona la programación de reproducción de audio.

```sql
CREATE TABLE audio_schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id VARCHAR(50) NOT NULL,
    audio_id INTEGER,
    schedule_type VARCHAR(20) NOT NULL, -- interval, specific, once
    schedule_data JSON NOT NULL,
    active BOOLEAN DEFAULT 1,
    last_played TIMESTAMP,
    next_play TIMESTAMP,
    play_count INTEGER DEFAULT 0,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audio_id) REFERENCES audio_metadata(id)
);

CREATE INDEX idx_schedule_client ON audio_schedule(client_id);
CREATE INDEX idx_schedule_active ON audio_schedule(active);
CREATE INDEX idx_schedule_next ON audio_schedule(next_play);
```

### categories
Sistema dinámico de categorías compartido entre módulos.

```sql
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7), -- Hex color
    icon VARCHAR(50),
    parent_id INTEGER,
    sort_order INTEGER DEFAULT 0,
    active BOOLEAN DEFAULT 1,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(client_id, slug)
);

CREATE INDEX idx_category_client ON categories(client_id);
CREATE INDEX idx_category_slug ON categories(slug);
```

### message_templates
Plantillas reutilizables para generación de anuncios.

```sql
CREATE TABLE message_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id VARCHAR(50) NOT NULL,
    name VARCHAR(200) NOT NULL,
    template_type VARCHAR(50) NOT NULL,
    template_text TEXT NOT NULL,
    variables JSON, -- Variables disponibles en la plantilla
    category_id INTEGER,
    usage_count INTEGER DEFAULT 0,
    last_used TIMESTAMP,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE INDEX idx_template_client ON message_templates(client_id);
CREATE INDEX idx_template_type ON message_templates(template_type);
```

### saved_messages
Biblioteca de mensajes guardados para reutilización.

```sql
CREATE TABLE saved_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    text TEXT NOT NULL,
    voice VARCHAR(100),
    category VARCHAR(100),
    audio_file VARCHAR(255),
    tags JSON, -- Array de tags
    favorite BOOLEAN DEFAULT 0,
    usage_count INTEGER DEFAULT 0,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_saved_client ON saved_messages(client_id);
CREATE INDEX idx_saved_category ON saved_messages(category);
CREATE INDEX idx_saved_favorite ON saved_messages(favorite);
```

### calendar_events
Eventos del calendario para programación especial.

```sql
CREATE TABLE calendar_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type VARCHAR(50), -- announcement, campaign, holiday
    start_date DATE NOT NULL,
    end_date DATE,
    start_time TIME,
    end_time TIME,
    recurrence_rule JSON, -- RRULE format
    audio_ids JSON, -- Array of audio IDs
    active BOOLEAN DEFAULT 1,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_event_client ON calendar_events(client_id);
CREATE INDEX idx_event_dates ON calendar_events(start_date, end_date);
CREATE INDEX idx_event_type ON calendar_events(event_type);
```

### api_usage
Tracking de uso de APIs externas.

```sql
CREATE TABLE api_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id VARCHAR(50) NOT NULL,
    service VARCHAR(50) NOT NULL, -- elevenlabs, claude, azuracast
    endpoint VARCHAR(255),
    tokens_used INTEGER,
    characters_used INTEGER,
    cost_estimate DECIMAL(10,6),
    request_data JSON,
    response_data JSON,
    status_code INTEGER,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_usage_client ON api_usage(client_id);
CREATE INDEX idx_usage_service ON api_usage(service);
CREATE INDEX idx_usage_created ON api_usage(created_at);
```

</table_schema>

<json_fields>
## JSON Field Structures

### audio_metadata.metadata
```json
{
  "voice_settings": {
    "stability": 0.5,
    "similarity_boost": 0.75,
    "style": 0.3
  },
  "campaign": "black_friday_2025",
  "generated_by": "dashboard",
  "ai_generated": true,
  "model": "claude-3-haiku"
}
```

### audio_schedule.schedule_data
```json
// For type='interval'
{
  "interval": 30,
  "unit": "minutes",
  "start_time": "09:00",
  "end_time": "21:00",
  "days": [1, 2, 3, 4, 5]
}

// For type='specific'
{
  "times": ["10:00", "14:30", "18:00"],
  "days": [0, 1, 2, 3, 4, 5, 6]
}

// For type='once'
{
  "date": "2025-01-15",
  "time": "15:30"
}
```

### message_templates.variables
```json
{
  "tienda": {
    "type": "string",
    "required": true,
    "description": "Nombre de la tienda"
  },
  "descuento": {
    "type": "number",
    "required": true,
    "description": "Porcentaje de descuento"
  },
  "fecha_limite": {
    "type": "date",
    "required": false,
    "description": "Fecha límite de la promoción"
  }
}
```
</json_fields>

<triggers>
## Database Triggers

### Auto-update timestamps
```sql
CREATE TRIGGER update_audio_metadata_timestamp 
AFTER UPDATE ON audio_metadata
BEGIN
  UPDATE audio_metadata 
  SET updated_at = CURRENT_TIMESTAMP 
  WHERE id = NEW.id;
END;

-- Similar triggers for all tables with updated_at
```

### Increment usage counters
```sql
CREATE TRIGGER increment_template_usage
AFTER INSERT ON audio_metadata
WHEN NEW.metadata LIKE '%template_id%'
BEGIN
  UPDATE message_templates 
  SET usage_count = usage_count + 1,
      last_used = CURRENT_TIMESTAMP
  WHERE id = json_extract(NEW.metadata, '$.template_id');
END;
```
</triggers>

<migrations>
## Migration Examples

### Add new column
```sql
-- Add jingle support
ALTER TABLE audio_metadata 
ADD COLUMN has_music BOOLEAN DEFAULT 0;

ALTER TABLE audio_metadata 
ADD COLUMN music_file VARCHAR(255);
```

### Create new index
```sql
-- Improve query performance
CREATE INDEX idx_audio_voice_category 
ON audio_metadata(voice, category);
```

### Migrate data
```sql
-- Migrate old category format
UPDATE audio_metadata 
SET category = 'informativos' 
WHERE category IN ('info', 'informativo', 'information');
```
</migrations>

<backup_strategy>
## Backup Strategy

### Daily Backup Script
```bash
#!/bin/bash
# /var/www/casa/scripts/backup-db.sh

DB_PATH="/var/www/casa/database/casa.db"
BACKUP_DIR="/var/www/casa/backups/database"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup
sqlite3 $DB_PATH ".backup $BACKUP_DIR/casa_$DATE.db"

# Keep only last 30 days
find $BACKUP_DIR -name "casa_*.db" -mtime +30 -delete

# Verify backup
sqlite3 $BACKUP_DIR/casa_$DATE.db "PRAGMA integrity_check"
```

### Restore from Backup
```bash
# Restore database
cp /var/www/casa/backups/database/casa_20250112.db /var/www/casa/database/casa.db
chmod 666 /var/www/casa/database/casa.db
```
</backup_strategy>