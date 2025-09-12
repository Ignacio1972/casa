# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Starting the Server
```bash
# Start Node.js development server (port 4000)
node server.js

# Alternative: Use nginx (already configured)
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm
```

### Database Operations
```bash
# Access SQLite database
sqlite3 database/casa.db

# Common queries
sqlite3 database/casa.db "SELECT * FROM audio_metadata ORDER BY created_at DESC LIMIT 10;"
sqlite3 database/casa.db "SELECT * FROM audio_schedule WHERE active = 1;"
```

### Permissions Setup
```bash
# Required for audio file generation
chmod 777 database/
chmod 666 database/casa.db
chown -R www-data:www-data src/api/temp/
```

## Architecture Overview

### Module System
The application uses a **modular frontend architecture** with dynamic module loading:

- **Module Interface**: All modules in `/src/modules/` must implement `load()`, `unload()`, and `getName()` methods
- **Event Bus**: Central communication via `/src/core/event-bus.js` - modules emit/listen to events like `audio:generated`, `schedule:updated`
- **Module Loader**: `/src/core/module-loader.js` handles dynamic loading with CSS injection and cleanup
- **Router**: Hash-based routing (`#dashboard`, `#calendar`, etc.) managed by `/src/core/router.js`

### API Service Pattern
PHP backend follows a service-oriented architecture:

```
/src/api/
├── services/           # Business logic services
│   ├── tts-service.php        # ElevenLabs TTS integration
│   └── audio-processor.php    # Audio file processing
├── generate.php        # Main TTS generation endpoint
├── audio-scheduler.php # Schedule management
└── saved-messages.php  # Message CRUD operations
```

**API Request Flow**:
1. Frontend module emits event → API client makes request
2. PHP service processes → Updates SQLite database
3. Returns JSON response → Frontend updates via event bus

### Database Schema
SQLite database with multi-tenant support:

**Key Tables**:
- `audio_metadata`: TTS files with category, voice, metadata
- `audio_schedule`: Scheduling rules (interval/specific/once types)
- `categories`: Dynamic category system shared across modules
- `message_templates`: Reusable announcement templates

**Important Fields**:
- All tables use `client_id` for multi-tenancy
- JSON fields for flexible metadata storage
- Automatic timestamps via triggers

### Voice Configuration System
Voice settings cascade from multiple sources:

1. **Base Configuration**: `/src/api/data/voices-config.json` - ElevenLabs voice IDs and metadata
2. **Frontend Selection**: User selects voice in Dashboard module
3. **Dynamic Settings**: Voice stability/style sliders send `voice_settings` object
4. **PHP Processing**: `tts-service.php` merges all settings for API call

### Event Communication Pattern
Modules communicate through namespaced events:

```javascript
// Module emits
eventBus.emit('calendar:schedule:created', scheduleData);

// Another module listens
eventBus.on('calendar:schedule:created', (data) => {
    // React to schedule creation
});
```

**System Events**:
- `module:loaded` / `module:unloaded`
- `navigation:change`
- `audio:generated`
- `message:saved`

### Critical Files and Their Purposes

**Frontend Core**:
- `/src/core/api-client.js`: HTTP wrapper with error handling
- `/src/core/voice-service.js`: Voice data loading and caching
- `/src/core/storage-manager.js`: LocalStorage abstraction

**Module Components**:
- `/src/modules/dashboard/index.js`: Main TTS generation interface
- `/src/modules/dashboard/components/ai-suggestions.js`: Claude AI integration
- `/src/modules/calendar/services/schedule-service.js`: Schedule management

**API Services**:
- `/src/api/claude-service.php`: Claude API integration (check API key in .env)
- `/src/api/jingle-service.php`: Music + TTS jingle generation
- `/src/api/services/tts-service.php`: ElevenLabs TTS with voice settings

## Environment Configuration

**Required API Keys** (in `.env`):
```bash
ELEVENLABS_API_KEY=your_key_here
CLAUDE_API_KEY=your_key_here  # For AI suggestions
AZURACAST_API_KEY=your_key_here  # For radio integration
```

**Server Requirements**:
- PHP 8.1+ with SQLite3, CURL extensions
- nginx with PHP-FPM
- ffmpeg for audio processing
- Node.js for development server

## Common Development Tasks

### Adding a New Module
1. Create directory in `/src/modules/your-module/`
2. Implement module class with required methods
3. Add CSS in `/public/styles-v5/3-modules/your-module.css`
4. Register route in frontend router

### Modifying Voice Settings
1. Update `/src/api/data/voices-config.json` for new voices
2. Modify `/src/api/services/tts-service.php` for processing logic
3. Update Dashboard UI in `/src/modules/dashboard/index.js`

### Working with Schedules
- Schedule types: `interval` (recurring), `specific` (exact times), `once` (single execution)
- Days stored as numbers: 0=Sunday, 1=Monday, etc.
- Times in 24-hour format: "14:30"

### Database Migrations
No formal migration system - modify schema directly:
```bash
sqlite3 database/casa.db < your-migration.sql
```

## Security Considerations

- **NEVER** commit API keys - use environment variables
- PHP services validate all inputs before database operations
- File uploads restricted to audio formats
- CORS configured for local development only