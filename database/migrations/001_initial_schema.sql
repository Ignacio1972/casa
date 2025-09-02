-- Schema inicial para Casa Costanera
-- Base de datos SQLite
-- Versión 1.0.0

-- Tabla principal de archivos de audio
CREATE TABLE IF NOT EXISTS audio_metadata (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT UNIQUE NOT NULL,
    display_name TEXT,
    category TEXT DEFAULT 'General',
    voice_id TEXT,
    voice_name TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    duration REAL,
    file_size INTEGER,
    is_saved BOOLEAN DEFAULT 0,
    tags TEXT,
    notes TEXT,
    play_count INTEGER DEFAULT 0,
    last_played DATETIME,
    source TEXT DEFAULT 'tts', -- 'tts' o 'upload'
    original_filename TEXT,
    client_id TEXT DEFAULT 'CASA',
    metadata JSON
);

-- Tabla de programaciones
CREATE TABLE IF NOT EXISTS audio_schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    schedule_type TEXT NOT NULL, -- 'interval', 'specific', 'once'
    schedule_time TEXT,
    schedule_days TEXT, -- JSON array de días
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_executed DATETIME,
    next_execution DATETIME,
    priority INTEGER DEFAULT 0,
    notes TEXT,
    end_date DATETIME,
    client_id TEXT DEFAULT 'CASA',
    FOREIGN KEY (filename) REFERENCES audio_metadata(filename) ON DELETE CASCADE
);

-- Tabla de logs de ejecución
CREATE TABLE IF NOT EXISTS audio_schedule_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    schedule_id INTEGER,
    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TEXT, -- 'success', 'failed', 'skipped'
    error_message TEXT,
    execution_time_ms INTEGER,
    FOREIGN KEY (schedule_id) REFERENCES audio_schedule(id) ON DELETE CASCADE
);

-- Tabla de categorías
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    color TEXT DEFAULT '#6b7280',
    icon TEXT DEFAULT 'folder',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    client_id TEXT DEFAULT 'CASA'
);

-- Tabla de plantillas de mensajes
CREATE TABLE IF NOT EXISTS message_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    content TEXT NOT NULL,
    category TEXT,
    variables TEXT, -- JSON array de variables
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    usage_count INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    client_id TEXT DEFAULT 'CASA'
);

-- Tabla de estadísticas
CREATE TABLE IF NOT EXISTS statistics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date DATE NOT NULL,
    metric_name TEXT NOT NULL,
    metric_value REAL,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    client_id TEXT DEFAULT 'CASA',
    UNIQUE(date, metric_name, client_id)
);

-- Tabla de configuración
CREATE TABLE IF NOT EXISTS system_config (
    key TEXT PRIMARY KEY,
    value TEXT,
    type TEXT DEFAULT 'string', -- 'string', 'number', 'boolean', 'json'
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    client_id TEXT DEFAULT 'CASA'
);

-- Índices para mejorar performance
CREATE INDEX IF NOT EXISTS idx_audio_metadata_category ON audio_metadata(category);
CREATE INDEX IF NOT EXISTS idx_audio_metadata_is_saved ON audio_metadata(is_saved);
CREATE INDEX IF NOT EXISTS idx_audio_metadata_created_at ON audio_metadata(created_at);
CREATE INDEX IF NOT EXISTS idx_audio_schedule_is_active ON audio_schedule(is_active);
CREATE INDEX IF NOT EXISTS idx_audio_schedule_next_execution ON audio_schedule(next_execution);
CREATE INDEX IF NOT EXISTS idx_schedule_log_executed_at ON audio_schedule_log(executed_at);
CREATE INDEX IF NOT EXISTS idx_statistics_date ON statistics(date);

-- Insertar categorías predeterminadas para Casa Costanera
INSERT OR IGNORE INTO categories (name, color, icon, description, client_id) VALUES 
    ('General', '#6b7280', 'folder', 'Mensajes generales', 'CASA'),
    ('Promociones', '#ef4444', 'megaphone', 'Ofertas y promociones', 'CASA'),
    ('Tiendas', '#10b981', 'store', 'Anuncios de tiendas', 'CASA'),
    ('Eventos', '#8b5cf6', 'calendar', 'Eventos especiales', 'CASA'),
    ('Seguridad', '#f59e0b', 'shield', 'Avisos de seguridad', 'CASA'),
    ('Servicios', '#3b82f6', 'info', 'Información de servicios', 'CASA'),
    ('Estacionamiento', '#14b8a6', 'car', 'Avisos de estacionamiento', 'CASA'),
    ('Música', '#ec4899', 'music', 'Contenido musical', 'CASA');

-- Insertar configuración inicial
INSERT OR IGNORE INTO system_config (key, value, type, description, client_id) VALUES
    ('system_version', '1.0.0', 'string', 'Versión del sistema', 'CASA'),
    ('client_name', 'Casa Costanera', 'string', 'Nombre del cliente', 'CASA'),
    ('max_tts_per_day', '100', 'number', 'Máximo de TTS por día', 'CASA'),
    ('max_scheduled_messages', '50', 'number', 'Máximo de mensajes programados', 'CASA'),
    ('theme_mode', 'dark', 'string', 'Modo de tema (dark/light)', 'CASA'),
    ('language', 'es-CL', 'string', 'Idioma del sistema', 'CASA');

-- Trigger para actualizar updated_at automáticamente
CREATE TRIGGER IF NOT EXISTS update_audio_schedule_timestamp 
AFTER UPDATE ON audio_schedule
BEGIN
    UPDATE audio_schedule SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS update_message_templates_timestamp 
AFTER UPDATE ON message_templates
BEGIN
    UPDATE message_templates SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- Configurar modo WAL para mejor concurrencia
PRAGMA journal_mode=WAL;
PRAGMA synchronous=NORMAL;
PRAGMA busy_timeout=5000;