# Casa Costanera - Sistema de Radio Automatizada

Sistema de generación y programación de audio TTS para radio automatizada.

## 🚀 Quick Start

### Generar Audio
```bash
# Generar con voz y categoría
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -d '{"text":"Tu mensaje","voice":"Rachel","category":"promociones"}'

# Crear Jingle con música
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -d '{"action":"generate","text":"Tu mensaje","voice":"Rachel","music_file":"upbeat.mp3"}'
```

### Programar Emisión
```bash
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -d '{"audio_file":"audio.mp3","schedule_type":"interval","interval":240}'
```

## 📁 Estructura Principal

```
/var/www/casa/
├── src/
│   ├── api/              # APIs PHP
│   │   ├── generate.php          # Generación TTS
│   │   ├── jingle-service.php    # Jingles con música
│   │   ├── claude-service.php    # IA para sugerencias
│   │   └── audio-scheduler.php   # Programación
│   ├── modules/
│   │   └── dashboard/     # Interfaz principal
│   └── core/             # Servicios JS
├── database/
│   └── casa.db           # SQLite database
└── docs/                 # 📚 DOCUMENTACIÓN COMPLETA DISPONIBLE AQUÍ
```

## 🔧 Servicios Principales

### TTS Generation
- **Archivo**: `/src/api/generate.php`
- **Voces disponibles**: Rachel, Domi, Bella, Antoni, Josh
- **Categorías**: promociones, informativos, eventos, emergencias

### Jingle Service  
- **Archivo**: `/src/api/jingle-service.php`
- **Música**: En `/public/audio/music/`
- **Configuración remota**: `/src/api/data/jingle-config.json`

### Claude AI Service
- **Archivo**: `/src/api/claude-service.php`
- **Modelo**: Configurado en servidor (claude-3-haiku)
- **Multi-cliente**: `/src/api/data/clients-config.json`

### Dashboard
- **Ubicación**: `/src/modules/dashboard/`
- **Componentes**: AI suggestions, jingle controls, voice controls

## 🛠️ Comandos Útiles

### Base de Datos
```bash
# Ver mensajes recientes
sqlite3 database/casa.db "SELECT * FROM audio_metadata ORDER BY created_at DESC LIMIT 10;"

# Ver programaciones activas
sqlite3 database/casa.db "SELECT * FROM audio_schedule WHERE active = 1;"
```

### Servidor
```bash
# Iniciar servidor Node.js
node server.js

# Ver logs
tail -f src/api/logs/tts-$(date +%Y-%m-%d).log
```

## ⚙️ Configuración

### Variables de Entorno (.env)
```bash
ELEVENLABS_API_KEY=tu_clave
CLAUDE_API_KEY=tu_clave
AZURACAST_API_KEY=tu_clave
```

### Permisos Requeridos
```bash
chmod 777 database/
chmod 666 database/casa.db
chown -R www-data:www-data src/api/temp/
```

## 🎯 Contexto del Sistema

- **Propósito**: Radio automatizada para centro comercial
- **Stack**: PHP 8.1, SQLite, Node.js, nginx
- **APIs**: ElevenLabs (TTS), Claude (AI), AzuraCast (Radio)
- **Frontend**: Módulos JS vanilla con event bus

## 💡 Tips

- El modelo de IA se configura solo desde el servidor
- Las voces se pueden activar/desactivar desde `/playground/voice-admin.php`
- La configuración de jingles se ajusta desde `/playground/jingle-config.html`

## 📚 Documentación Adicional

Para información detallada sobre cualquier componente, consulta la carpeta `/docs/`:

- **Workflows**: `/docs/workflows/` - Flujos completos de trabajo
- **Endpoints**: `/docs/endpoints/` - Documentación de APIs
- **Components**: `/docs/components/` - Componentes del frontend
- **Examples**: `/docs/examples/` - Ejemplos de uso
- **Schemas**: `/docs/schemas/` - Estructura de base de datos


