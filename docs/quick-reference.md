# Casa Costanera - Quick Reference

<quick_overview>
Comandos más usados para el workflow diario de generación y programación de audio.
Ordenados por frecuencia de uso real.
</quick_overview>

## 🎯 Most Used (Copy & Paste)

### Generate Audio with AI Suggestion
```bash
# 1. Get AI suggestion
curl -X POST "http://localhost:4000/src/api/claude-service.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"generate","client_id":"casa","category":"promociones","context":"Black Friday","tone":"urgente"}'

# 2. Generate audio with selected text
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{"client_id":"casa","text":"[paste suggestion]","voice":"Rachel","category":"promociones"}'
```

### Generate with Custom Voice Settings
```bash
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "text": "Tu mensaje aquí",
    "voice": "Domi",
    "voice_settings": {
      "stability": 0.3,
      "similarity_boost": 0.85,
      "style": 0.6
    },
    "category": "promociones"
  }'
```

### Create Jingle with Music
```bash
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "client_id": "casa",
    "text": "Tu mensaje",
    "voice": "Rachel",
    "music_file": "upbeat_commercial.mp3",
    "music_volume": 0.3,
    "voice_volume": 1.0,
    "fade_in": 2,
    "fade_out": 3
  }'
```

### Schedule Audio (3 Types)

#### Every X Hours (Interval)
```bash
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "client_id": "casa",
    "audio_file": "audio.mp3",
    "schedule_type": "interval",
    "schedule_data": {
      "interval": 240,
      "days": [1,2,3,4,5],
      "start_time": "10:00",
      "end_time": "20:00"
    }
  }'
```

#### Specific Times Daily
```bash
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "client_id": "casa",
    "audio_file": "audio.mp3",
    "schedule_type": "specific",
    "schedule_data": {
      "times": ["10:00", "14:00", "18:00"],
      "days": [0,1,2,3,4,5,6]
    }
  }'
```

#### Once at Specific Time
```bash
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "client_id": "casa",
    "audio_file": "audio.mp3",
    "schedule_type": "once",
    "schedule_data": {
      "datetime": "2025-01-15T14:30:00"
    }
  }'
```

## 📊 Check Status

### Today's Schedule
```bash
curl "http://localhost:4000/src/api/calendar-api.php?action=today"
```

### Recent Messages (últimos 10)
```bash
curl "http://localhost:4000/src/api/recent-messages.php?limit=10"
```

### Database Stats
```bash
# Total audios por categoría
sqlite3 database/casa.db "SELECT category, COUNT(*) as total FROM audio_metadata GROUP BY category;"

# Audios generados hoy
sqlite3 database/casa.db "SELECT COUNT(*) FROM audio_metadata WHERE date(created_at) = date('now');"
```

### Check Logs
```bash
# Ver errores recientes
tail -f src/api/logs/tts-$(date +%Y-%m-%d).log | grep ERROR

# Ver toda actividad
tail -f src/api/logs/tts-$(date +%Y-%m-%d).log
```

## 🚨 Emergency Only

### Interrupt Radio NOW
```bash
curl -X POST "http://localhost:4000/src/api/services/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"interrupt","filename":"emergency.mp3","priority":"urgent"}'
```

### Emergency Broadcast
```bash
# Generar y emitir mensaje de emergencia
TEXT="ATENCIÓN: Evacuación inmediata. Diríjanse a las salidas." && \
AUDIO=$(curl -s -X POST http://localhost:4000/src/api/generate.php \
  -d "{\"text\":\"$TEXT\",\"voice\":\"Antoni\",\"category\":\"emergencias\",\"voice_settings\":{\"stability\":0.2,\"style\":0.9}}" \
  | jq -r '.data.filename') && \
curl -X POST http://localhost:4000/src/api/services/radio-service.php \
  -d "{\"action\":\"interrupt\",\"filename\":\"$AUDIO\",\"priority\":\"urgent\"}"
```

## 🎛️ Voice Settings Cheat Sheet

| Purpose | Stability | Similarity | Style | Voice |
|---------|-----------|------------|-------|-------|
| 🚨 Urgente | 0.2-0.3 | 0.9-0.95 | 0.7-0.8 | Antoni |
| 📢 Promoción | 0.3-0.4 | 0.8-0.85 | 0.5-0.6 | Domi |
| ℹ️ Información | 0.6-0.8 | 0.75 | 0.2-0.3 | Rachel |
| 🎵 Jingle | 0.5 | 0.8 | 0.5 | Bella |
| 🎄 Navideño | 0.6 | 0.85 | 0.4 | Charlotte |

## 📁 Quick Reference

### Categories
- `promociones` - Ofertas y descuentos
- `informativos` - Información general
- `eventos` - Eventos especiales
- `emergencias` - Avisos urgentes
- `servicios` - Servicios del mall
- `horarios` - Horarios y apertura

### Available Voices
**Female**: Rachel, Domi, Bella, Elli, Charlotte
**Male**: Antoni, Josh, Arnold, Adam, Sam

### Available Music
- `upbeat_commercial.mp3` - Alegre comercial
- `corporate_inspire.mp3` - Corporativo
- `urgent_promo.mp3` - Promoción urgente
- `soft_background.mp3` - Fondo suave

### Schedule Types
- **interval**: Cada X minutos/horas
- **specific**: Horas específicas del día
- **once**: Una sola vez

<environment_setup>
## Quick Environment Setup

```bash
# 1. Set environment variables
cat > .env << EOF
ELEVENLABS_API_KEY=your_key_here
CLAUDE_API_KEY=your_key_here
AZURACAST_API_KEY=your_key_here
EOF

# 2. Set permissions
chmod 777 database/
chmod 666 database/casa.db
chown -R www-data:www-data src/api/temp/

# 3. Start server
node server.js

# 4. Test connection
curl http://localhost:4000/src/api/generate.php
```
</environment_setup>

<debugging>
## Debug Commands

### View Recent Errors
```bash
# PHP errors
tail -n 50 /var/log/nginx/error.log

# API logs
grep ERROR src/api/logs/tts-$(date +%Y-%m-%d).log

# Database queries
sqlite3 database/casa.db ".mode column" ".headers on" \
  "SELECT id, text, voice, category, created_at FROM audio_metadata ORDER BY id DESC LIMIT 5;"
```

### Test Specific Components
```bash
# Test ElevenLabs connection
php -r "
require_once 'src/api/services/tts-service.php';
\$service = new TTSService();
\$result = \$service->testConnection();
echo json_encode(\$result);
"

# Test Claude API
php -r "
require_once 'src/api/claude-service.php';
\$service = new ClaudeService();
\$models = \$service->getAvailableModels();
print_r(\$models);
"
```
</debugging>

<database_queries>
## Useful Database Queries

### Audio Statistics
```sql
-- Total audios by category
SELECT category, COUNT(*) as total, 
       ROUND(SUM(duration), 2) as total_duration
FROM audio_metadata 
GROUP BY category 
ORDER BY total DESC;

-- Recent generations
SELECT datetime(created_at, 'localtime') as time, 
       substr(text, 1, 50) as text_preview,
       voice, category
FROM audio_metadata 
ORDER BY created_at DESC 
LIMIT 10;

-- Active schedules
SELECT s.id, s.schedule_type, s.active,
       a.filename, a.category
FROM audio_schedule s
JOIN audio_metadata a ON s.audio_id = a.id
WHERE s.active = 1;
```

### Usage Tracking
```sql
-- API usage by service
SELECT service, 
       COUNT(*) as requests,
       SUM(tokens_used) as total_tokens,
       ROUND(SUM(cost_estimate), 4) as total_cost
FROM api_usage
WHERE date(created_at) = date('now')
GROUP BY service;

-- Character usage for ElevenLabs
SELECT date(created_at) as date,
       SUM(characters_used) as chars_used
FROM api_usage
WHERE service = 'elevenlabs'
GROUP BY date(created_at)
ORDER BY date DESC
LIMIT 30;
```
</database_queries>

<frontend_integration>
## Frontend Integration Examples

### JavaScript API Client
```javascript
class CasaAPI {
  constructor(baseUrl = 'http://localhost:4000/src/api') {
    this.baseUrl = baseUrl;
    this.clientId = 'casa';
  }

  async generateTTS(text, voice = 'Rachel', category = 'informativos') {
    const response = await fetch(`${this.baseUrl}/generate.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        client_id: this.clientId,
        text, voice, category
      })
    });
    return response.json();
  }

  async getAISuggestions(category, context) {
    const response = await fetch(`${this.baseUrl}/claude-service.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'generateSuggestions',
        client_id: this.clientId,
        category, context
      })
    });
    return response.json();
  }

  async scheduleAudio(audioFile, scheduleType, scheduleData) {
    const response = await fetch(`${this.baseUrl}/audio-scheduler.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'create',
        client_id: this.clientId,
        audio_file: audioFile,
        schedule_type: scheduleType,
        schedule_data: scheduleData
      })
    });
    return response.json();
  }
}

// Usage
const api = new CasaAPI();
const result = await api.generateTTS('Prueba de audio', 'Rachel', 'test');
console.log('Audio URL:', result.data.audio_url);
```
</frontend_integration>

<monitoring>
## Monitoring & Health Checks

### System Health Check Script
```bash
#!/bin/bash
# health-check.sh

echo "=== Casa Costanera System Health Check ==="
echo ""

# Check services
echo "1. Checking web server..."
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:4000

echo ""
echo "2. Checking database..."
sqlite3 database/casa.db "SELECT 'Database OK' as status;" 2>/dev/null || echo "Database Error"

echo ""
echo "3. Recent API activity (last hour)..."
find src/api/logs -name "*.log" -mmin -60 | wc -l | xargs -I {} echo "{} log files updated"

echo ""
echo "4. Disk usage..."
df -h | grep -E "/$|/var/www"

echo ""
echo "5. Memory usage..."
free -h | grep -E "^Mem:"

echo ""
echo "=== Check Complete ==="
```

### Automated Monitoring
```bash
# Add to crontab for hourly checks
0 * * * * /var/www/casa/scripts/health-check.sh >> /var/log/casa-health.log 2>&1
```
</monitoring>