# Remote Configuration Systems Documentation

<critical_overview>
Documentación de los sistemas críticos que permiten controlar el Dashboard remotamente.
Estos sistemas permiten configurar voces disponibles y contextos de IA sin tocar el código del frontend.
</critical_overview>

---

## Voice Configuration System (Playground → Dashboard)

<voice_system_overview>
Sistema que permite controlar qué voces aparecen en el Dashboard desde el panel de administración del Playground.
La configuración se almacena en un archivo JSON compartido que el Dashboard lee al inicializarse.
</voice_system_overview>

### Architecture

<voice_architecture>
```
PLAYGROUND (Admin)                    SHARED CONFIG                    DASHBOARD (User)
┌──────────────────┐                ┌──────────────────┐            ┌──────────────────┐
│ voice-admin.php  │───[WRITE]──────▶│ voices-config.json│◀───[READ]───│ VoiceService.js  │
│                  │                │                  │            │                  │
│ - Add voices     │                │ {                │            │ - Load voices    │
│ - Toggle active  │                │   "voices": {    │            │ - Filter active  │
│ - Reorder        │                │     "rachel": {  │            │ - Display in UI  │
│ - Delete         │                │       "active":  │            │                  │
└──────────────────┘                │         true     │            └──────────────────┘
                                    │     }            │
                                    │   }              │
                                    └──────────────────┘
```
</voice_architecture>

### Configuration File Location
```
/src/api/data/voices-config.json
```

### Voice Configuration Structure
<voice_config_structure>
```json
{
  "voices": {
    "rachel": {
      "id": "21m00Tcm4TlvDq8ikWAM",    // ElevenLabs voice ID
      "label": "Rachel - Professional",  // Display name in Dashboard
      "gender": "F",                     // M or F
      "active": true,                    // ⭐ Controls visibility in Dashboard
      "category": "standard",            // standard, custom, premium
      "order": 1,                        // Display order
      "added_date": "2025-01-01 10:00:00",
      "defaults": {                      // Default settings for this voice
        "stability": 0.75,
        "similarity_boost": 0.8,
        "style": 0.5
      }
    },
    "custom_ceo": {
      "id": "IKne3meq5aSn9XLyUdCD",
      "label": "CEO Voice - Special",
      "gender": "M",
      "active": false,                   // ⚠️ Hidden in Dashboard
      "category": "custom",
      "order": 99
    }
  },
  "settings": {
    "default_voice": "rachel",
    "version": "2.0",
    "last_updated": "2025-01-12T10:30:00Z"
  }
}
```
</voice_config_structure>

### Voice Admin Operations (Playground)

<voice_admin_operations>
#### 1. Add New Voice
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-admin.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add",
    "voice_id": "IKne3meq5aSn9XLyUdCD",
    "label": "Carlos - Corporate",
    "gender": "M"
  }'
```

#### 2. Toggle Voice Visibility (CRITICAL)
```bash
# This controls whether the voice appears in Dashboard
curl -X POST "http://localhost:4000/public/playground/api/voice-admin.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "toggle",
    "voice_key": "custom_ceo"
  }'
```

#### 3. Reorder Voices
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-admin.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "reorder",
    "voice_order": ["rachel", "antoni", "custom_ceo", "domi"]
  }'
```

#### 4. Delete Voice
```bash
curl -X POST "http://localhost:4000/public/playground/api/voice-admin.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete",
    "voice_key": "old_voice"
  }'
```
</voice_admin_operations>

### Dashboard Voice Loading Process

<dashboard_voice_loading>
```javascript
// src/modules/dashboard/index.js
async loadVoices() {
    // 1. Load from VoiceService
    const voicesData = await VoiceService.loadVoices();
    
    // 2. VoiceService reads from voices-config.json
    // src/core/voice-service.js
    static async loadVoices() {
        const response = await fetch('/src/api/data/voices-config.json');
        const config = await response.json();
        
        // 3. ⭐ CRITICAL: Filter only ACTIVE voices
        const activeVoices = {};
        for (const [key, voice] of Object.entries(config.voices)) {
            if (voice.active === true) {  // ⚠️ Only show active voices
                activeVoices[key] = voice;
            }
        }
        
        return activeVoices;
    }
    
    // 4. Populate voice selector in Dashboard
    this.state.voices = Object.entries(voicesData).map(([key, voice]) => ({
        key: key,
        id: voice.id,
        label: voice.label,
        gender: voice.gender,
        order: voice.order || 999
    }));
    
    // 5. Sort by order
    this.state.voices.sort((a, b) => a.order - b.order);
    
    // 6. Update UI
    this.populateVoiceSelector();
}
```
</dashboard_voice_loading>

### Critical Flow: Remote Voice Control

<voice_control_flow>
```
1. Admin accesses Playground voice-admin panel
2. Admin toggles voice.active = false for "custom_ceo"
3. voices-config.json is updated immediately
4. User refreshes Dashboard
5. Dashboard loads voices via VoiceService
6. VoiceService filters out inactive voices
7. "custom_ceo" is NOT shown in voice selector
8. User cannot select hidden voices
```
</voice_control_flow>

---

## Multi-Client Context System (Claude AI)

<client_system_overview>
Sistema que permite cambiar el contexto de Claude AI según el cliente activo.
Cada cliente tiene su propio contexto personalizado que modifica cómo Claude genera sugerencias.
</client_system_overview>

### Architecture

<client_architecture>
```
ADMIN PANEL                        SHARED CONFIG                    CLAUDE SERVICE
┌──────────────────┐              ┌──────────────────┐            ┌──────────────────┐
│ clients-service  │──[WRITE]────▶│ clients-config.json│◀──[READ]──│ claude-service   │
│                  │              │                  │            │                  │
│ - Add client     │              │ {                │            │ - Get context    │
│ - Edit context   │              │   "active_client"│            │ - Apply to prompt│
│ - Set active     │              │   "clients": {   │            │ - Generate with  │
│ - Delete client  │              │     "casa": {    │            │   client context │
└──────────────────┘              │       "context"  │            └──────────────────┘
                                  │     }            │
                                  │   }              │
                                  └──────────────────┘
```
</client_architecture>

### Configuration File Location
```
/src/api/data/clients-config.json
```

### Client Configuration Structure
<client_config_structure>
```json
{
  "active_client": "casa_costanera",  // ⭐ CRITICAL: Current active client
  "clients": {
    "casa_costanera": {
      "id": "casa_costanera",
      "name": "Casa Costanera",
      "context": "Eres un experto creando anuncios para Casa Costanera, un moderno centro comercial en Chile. Debes usar un tono profesional y amigable. Menciona las tiendas ancla como Falabella, Ripley y Paris cuando sea relevante.",
      "category": "centro_comercial",
      "active": true,
      "settings": {
        "default_tone": "profesional",
        "language": "es-CL",
        "max_length": 200,
        "keywords": ["shopping", "familia", "entretenimiento"]
      },
      "custom_prompts": {
        "promociones": "Enfócate en crear urgencia y destacar los descuentos.",
        "eventos": "Menciona fecha, hora y lugar específico dentro del mall.",
        "informativos": "Sé claro y conciso, evita jerga técnica."
      }
    },
    "mallplaza": {
      "id": "mallplaza",
      "name": "Mallplaza",
      "context": "Eres un experto en marketing para Mallplaza, una cadena premium de centros comerciales. Usa un tono elegante y sofisticado. Enfócate en la experiencia de compra premium.",
      "category": "centro_comercial_premium",
      "active": true,
      "settings": {
        "default_tone": "elegante",
        "language": "es-CL",
        "max_length": 180
      }
    }
  }
}
```
</client_config_structure>

### Client Service Operations

<client_operations>
#### 1. Get Active Client
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_active"
  }'
```

Response:
```json
{
  "success": true,
  "client": {
    "id": "casa_costanera",
    "name": "Casa Costanera",
    "context": "Eres un experto creando anuncios para Casa Costanera..."
  }
}
```

#### 2. Set Active Client (CRITICAL)
```bash
# This changes the context for ALL users
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "set_active",
    "client_id": "mallplaza"
  }'
```

#### 3. Update Client Context
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update",
    "client_id": "casa_costanera",
    "context": "Nuevo contexto personalizado para Casa Costanera...",
    "settings": {
      "default_tone": "casual",
      "keywords": ["verano", "ofertas", "familia"]
    }
  }'
```

#### 4. Add New Client
```bash
curl -X POST "http://localhost:4000/src/api/clients-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add",
    "client": {
      "id": "parque_arauco",
      "name": "Parque Arauco",
      "context": "Eres un experto en crear contenido para Parque Arauco...",
      "category": "centro_comercial_luxury"
    }
  }'
```
</client_operations>

### Claude Service Context Application

<claude_context_application>
```php
// src/api/claude-service.php
class ClaudeService {
    
    public function generateSuggestions($params) {
        // 1. Load client configuration
        $clientConfig = $this->loadClientConfig();
        
        // 2. Get active client context
        $activeClient = $clientConfig['clients'][$clientConfig['active_client']];
        
        // 3. ⭐ CRITICAL: Build system prompt with client context
        $systemPrompt = $this->buildSystemPrompt($activeClient);
        
        // 4. Add category-specific prompts if available
        if (isset($activeClient['custom_prompts'][$params['category']])) {
            $systemPrompt .= "\n\n" . $activeClient['custom_prompts'][$params['category']];
        }
        
        // 5. Call Claude API with contextualized prompt
        $response = $this->callClaudeAPI([
            'model' => $this->model,
            'system' => $systemPrompt,  // ⚠️ Client-specific context applied here
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $this->buildUserPrompt($params)
                ]
            ],
            'max_tokens' => $this->maxTokens
        ]);
        
        return $this->parseSuggestions($response);
    }
    
    private function buildSystemPrompt($client) {
        $prompt = $client['context'];  // Base context
        
        // Add client-specific settings
        if (isset($client['settings']['language'])) {
            $prompt .= "\nResponde siempre en {$client['settings']['language']}.";
        }
        
        if (isset($client['settings']['max_length'])) {
            $prompt .= "\nLimita los anuncios a {$client['settings']['max_length']} caracteres.";
        }
        
        if (isset($client['settings']['keywords'])) {
            $keywords = implode(', ', $client['settings']['keywords']);
            $prompt .= "\nIncorpora estas palabras clave cuando sea relevante: {$keywords}.";
        }
        
        return $prompt;
    }
}
```
</claude_context_application>

### Dashboard Integration

<dashboard_client_integration>
```javascript
// src/modules/dashboard/components/ai-suggestions.js
class AISuggestionsComponent {
    
    async generateSuggestions(context = {}) {
        // 1. Get current client info (optional - for display)
        const clientInfo = await this.getCurrentClient();
        
        // 2. Show client context in UI (optional)
        if (clientInfo) {
            this.showClientBadge(clientInfo.name);
        }
        
        // 3. Call Claude service
        // ⚠️ The client context is applied server-side automatically
        const response = await llmService.generateSuggestions({
            category: context.category,
            tone: context.tone,
            keywords: context.keywords,
            // No need to send client_id - server uses active_client
        });
        
        // 4. Claude returns suggestions with client context applied
        this.suggestions = response.suggestions;
        this.renderSuggestions();
    }
    
    async getCurrentClient() {
        const response = await fetch('/src/api/clients-service.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_active' })
        });
        
        const data = await response.json();
        return data.client;
    }
}
```
</dashboard_client_integration>

### Critical Flow: Remote Context Change

<context_change_flow>
```
1. Admin accesses client management panel
2. Admin sets active_client = "mallplaza"
3. clients-config.json is updated immediately
4. User generates AI suggestion in Dashboard
5. Dashboard calls Claude service
6. Claude service reads active_client from config
7. Claude service applies Mallplaza context
8. Suggestions are generated with Mallplaza tone/style
9. User sees Mallplaza-specific suggestions
```
</context_change_flow>

---

## Jingle Configuration System (Playground → Dashboard)

<jingle_system_overview>
Sistema de configuración remota para controlar todos los parámetros de jingles desde el Playground.
Permite ajustar silencios, fade in/out, volúmenes y ducking sin tocar el código del Dashboard.
</jingle_system_overview>

### Architecture

<jingle_architecture>
```
PLAYGROUND CONFIG                    SHARED CONFIG                    DASHBOARD
┌──────────────────┐                ┌──────────────────┐            ┌──────────────────┐
│ jingle-config.html│───[WRITE]──────▶│ jingle-config.json│◀───[READ]───│ JingleControls.js│
│                  │                │                  │            │                  │
│ - Intro silence  │                │ {                │            │ - Load config    │
│ - Outro silence  │                │   "jingle_defaults":│          │ - Apply to audio │
│ - Music volume   │                │     "intro_silence":│          │ - Use in generation│
│ - Voice volume   │                │       8,         │            │                  │
│ - Fade in/out    │                │     "outro_silence":│          └──────────────────┘
│ - Ducking        │                │       13.5       │
└──────────────────┘                │   }              │
                                    └──────────────────┘
```
</jingle_architecture>

### Configuration Interface URL
```
http://51.222.25.222:4000/playground/jingle-config.html
```

### Configuration File Location
```
/src/api/data/jingle-config.json
```

### Jingle Configuration Structure
<jingle_config_structure>
```json
{
  "jingle_defaults": {
    // ⭐ SILENCE CONTROLS
    "intro_silence": 8,         // Segundos de silencio ANTES del mensaje
    "outro_silence": 13.5,      // Segundos de silencio DESPUÉS del mensaje
    
    // ⭐ VOLUME CONTROLS
    "music_volume": 0.3,        // Volumen de la música (0.0 - 1.0)
    "voice_volume": 0.4,        // Volumen de la voz (0.0 - 2.0)
    
    // ⭐ FADE CONTROLS
    "fade_in": 3.5,            // Segundos de fade in de la música
    "fade_out": 3.5,           // Segundos de fade out de la música
    
    // ⭐ DUCKING CONTROLS
    "ducking_enabled": true,   // Reducir música cuando habla
    "duck_level": 0.2,         // Nivel de reducción (0.0 - 1.0)
    
    // ⭐ DEFAULT MUSIC
    "default_music": "Charly García - Pasajera en Trance.mp3",
    
    // ⭐ VOICE SETTINGS (opcional)
    "voice_settings": {
      "style": 0.15,
      "stability": 1,
      "similarity_boost": 0.5,
      "use_speaker_boost": true
    },
    
    "enabled_by_default": false  // Si jingles están activos por defecto
  },
  "allowed_music": "all",         // Restricción de música disponible
  "user_can_override": false      // Si usuario puede cambiar config
}
```
</jingle_config_structure>

### Jingle Config Service API

<jingle_service_endpoint>
**Endpoint**: `/api/jingle-config-service.php`
</jingle_service_endpoint>

<jingle_service_operations>
#### 1. Get Current Configuration
```bash
curl -X POST "http://localhost:4000/api/jingle-config-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get"
  }'
```

Response:
```json
{
  "success": true,
  "config": {
    "jingle_defaults": {
      "intro_silence": 8,
      "outro_silence": 13.5,
      "music_volume": 0.3,
      "voice_volume": 0.4,
      "fade_in": 3.5,
      "fade_out": 3.5,
      "ducking_enabled": true,
      "duck_level": 0.2
    }
  }
}
```

#### 2. Save Configuration (CRITICAL)
```bash
# This changes jingle settings for ALL users
curl -X POST "http://localhost:4000/api/jingle-config-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save",
    "config": {
      "jingle_defaults": {
        "intro_silence": 5,
        "outro_silence": 10,
        "music_volume": 0.25,
        "voice_volume": 0.5,
        "fade_in": 2,
        "fade_out": 4,
        "ducking_enabled": true,
        "duck_level": 0.15
      }
    }
  }'
```

#### 3. Reset to Defaults
```bash
curl -X POST "http://localhost:4000/api/jingle-config-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "reset"
  }'
```

#### 4. Validate Configuration
```bash
curl -X POST "http://localhost:4000/api/jingle-config-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "validate",
    "config": {
      "jingle_defaults": {
        "intro_silence": 5,
        "outro_silence": 10
      }
    }
  }'
```
</jingle_service_operations>

### Configuration Validation Rules

<jingle_validation>
```php
// src/api/jingle-config-service.php
Validation Ranges:
- intro_silence: 0 - 30 seconds
- outro_silence: 0 - 30 seconds  
- music_volume: 0.0 - 1.0
- voice_volume: 0.0 - 2.0
- fade_in: 0 - 10 seconds
- fade_out: 0 - 10 seconds
- duck_level: 0.0 - 1.0
```
</jingle_validation>

### Dashboard Integration

<dashboard_jingle_integration>
```javascript
// src/modules/dashboard/components/jingle-controls.js
export class JingleControls {
    
    async loadConfig() {
        // 1. Load configuration from server
        const response = await fetch('/api/jingle-config-service.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get' })
        });
        
        const data = await response.json();
        
        if (data.success && data.config) {
            // 2. ⭐ Apply remote configuration
            this.config = data.config.jingle_defaults;
            
            // 3. Configuration is now available for jingle generation
            console.log('[JingleControls] Remote config loaded:', this.config);
        }
    }
    
    getJingleParameters() {
        // 4. Use remote configuration for jingle generation
        return {
            intro_silence: this.config.intro_silence,
            outro_silence: this.config.outro_silence,
            music_file: this.selectedMusic || this.config.default_music,
            music_volume: this.config.music_volume,
            voice_volume: this.config.voice_volume,
            fade_in: this.config.fade_in,
            fade_out: this.config.fade_out,
            music_duck: this.config.ducking_enabled,
            duck_level: this.config.duck_level
        };
    }
}
```
</dashboard_jingle_integration>

### Jingle Generation with Remote Config

<jingle_generation_flow>
```javascript
// When user generates a jingle in Dashboard
async function generateJingleWithRemoteConfig() {
    // 1. JingleControls loads remote config automatically
    await dashboard.jingleControls.loadConfig();
    
    // 2. Get parameters with remote values
    const jingleParams = dashboard.jingleControls.getJingleParameters();
    
    // 3. Generate jingle with remote configuration
    const response = await fetch('/api/jingle-service.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'generate',
            text: userText,
            voice: selectedVoice,
            ...jingleParams  // ⚠️ Remote config applied here
        })
    });
    
    // 4. Audio will have:
    // - intro_silence seconds of silence before
    // - outro_silence seconds of silence after
    // - music at music_volume level
    // - voice at voice_volume level
    // - fade_in/fade_out as configured
    // - ducking if enabled
}
```
</jingle_generation_flow>

### Critical Flow: Remote Jingle Control

<jingle_control_flow>
```
1. Admin accesses http://51.222.25.222:4000/playground/jingle-config.html
2. Admin adjusts sliders:
   - Intro silence: 5 seconds
   - Outro silence: 10 seconds
   - Music volume: 25%
   - Voice volume: 50%
3. Admin clicks "Guardar Configuración"
4. jingle-config.json is updated immediately
5. User generates jingle in Dashboard
6. Dashboard loads config from jingle-config.json
7. Jingle is generated with:
   - 5 seconds silence before voice
   - 10 seconds silence after voice
   - Music at 25% volume
   - Voice at 50% volume
8. All users get same configuration automatically
```
</jingle_control_flow>

### Configuration UI Controls

<jingle_ui_controls>
The Playground configuration page provides intuitive controls:

#### Silence Controls
- **Intro Silence** (0-30s): Slider for silence before voice
- **Outro Silence** (0-30s): Slider for silence after voice

#### Volume Controls  
- **Music Volume** (0-100%): Background music level
- **Voice Volume** (0-200%): Voice/narration level

#### Fade Controls
- **Fade In** (0-10s): Music fade in duration
- **Fade Out** (0-10s): Music fade out duration

#### Ducking Controls
- **Enable Ducking**: Checkbox to enable/disable
- **Duck Level** (0-100%): How much to reduce music during voice

#### Actions
- **💾 Save Configuration**: Save changes globally
- **🎤 Test with Real Voice**: Test current settings
- **🔄 Reset to Defaults**: Restore factory settings
</jingle_ui_controls>

### Visual Feedback in Playground

<jingle_visual_feedback>
```javascript
// jingle-config.html provides real-time feedback
function updateSliderValue(sliderId, value) {
    // Show current value in badge
    document.getElementById(sliderId + 'Value').textContent = value + 's';
    
    // Visual preview of timeline
    updateTimelinePreview({
        intro: introSilence,
        voice: estimatedVoiceDuration,
        outro: outroSilence
    });
    
    // Show total duration
    const totalDuration = introSilence + voiceDuration + outroSilence;
    document.getElementById('totalDuration').textContent = totalDuration + 's';
}
```
</jingle_visual_feedback>

### Testing Configuration

<jingle_testing>
```javascript
// Test configuration with real voice
async function testConfig() {
    const testText = "Esta es una prueba de configuración de jingle";
    
    const response = await fetch('/api/jingle-service.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'generate',
            text: testText,
            voice: 'Rachel',
            ...currentConfig.jingle_defaults,
            preview_only: true  // Don't save, just preview
        })
    });
    
    // Play preview
    const audio = new Audio(response.preview_url);
    audio.play();
}
```
</jingle_testing>

### Impact on Audio Output

<jingle_audio_impact>
#### Example Timeline with Configuration:
```
Config: intro=5s, outro=10s, fade_in=2s, fade_out=3s

Timeline:
|--5s silence--|--2s fade in--|--voice with music--|--3s fade out--|--10s silence--|

Total Duration: 5 + voice_duration + 10 = 15+ seconds added to audio
```

#### Ducking Behavior:
```
Without Ducking:
Music: ████████████████████████████████
Voice:      ████████████████

With Ducking (duck_level=0.2):
Music: ████▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓████
Voice:      ████████████████
```
</jingle_audio_impact>

### Monitoring Configuration Changes

<jingle_monitoring>
```bash
# Watch configuration file for changes
watch -n 1 'cat /src/api/data/jingle-config.json | jq .jingle_defaults'

# Monitor jingle generation with config
tail -f /src/api/logs/jingle-*.log | grep -E "intro_silence|outro_silence"

# Check current configuration via API
curl -X POST "http://localhost:4000/api/jingle-config-service.php" \
  -d '{"action":"get"}' | jq .config.jingle_defaults
```
</jingle_monitoring>

### Best Practices for Jingle Configuration

<jingle_best_practices>
1. **Standard Configurations by Use Case:**
   - Radio spots: intro=2s, outro=3s
   - Background announcements: intro=5s, outro=8s
   - Emergency: intro=0s, outro=0s

2. **Volume Guidelines:**
   - Music volume: 20-30% for clear voice
   - Voice volume: 80-100% for prominence
   - Duck level: 15-25% for smooth ducking

3. **Fade Recommendations:**
   - Fade in: 2-3s for smooth entry
   - Fade out: 3-5s for natural ending
   - Shorter fades for urgent content

4. **Testing Protocol:**
   - Always test with real voice after changes
   - Listen on different devices
   - Verify in actual broadcast environment
</jingle_best_practices>

---

## Security & Best Practices

<security_practices>
### Access Control
```php
// Implement authentication for admin endpoints
if (!isAdminAuthenticated()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}
```

### Configuration Validation
```javascript
// Always validate configuration before applying
function validateVoiceConfig(config) {
    if (!config.voices || typeof config.voices !== 'object') {
        throw new Error('Invalid voice configuration');
    }
    
    for (const [key, voice] of Object.entries(config.voices)) {
        if (!voice.id || !voice.label) {
            throw new Error(`Invalid voice configuration for ${key}`);
        }
    }
}
```

### Change Logging
```php
// Log all configuration changes
function logConfigChange($action, $details) {
    $log = [
        'timestamp' => date('c'),
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ];
    
    file_put_contents(
        __DIR__ . '/logs/config-changes.log',
        json_encode($log) . "\n",
        FILE_APPEND | LOCK_EX
    );
}
```

### Backup Strategy
```bash
# Backup configurations before changes
cp /src/api/data/voices-config.json /backups/voices-config-$(date +%Y%m%d-%H%M%S).json
cp /src/api/data/clients-config.json /backups/clients-config-$(date +%Y%m%d-%H%M%S).json
```
</security_practices>

---

## Monitoring & Debugging

<monitoring>
### Check Active Configuration
```bash
# View current voice configuration
cat /src/api/data/voices-config.json | jq '.voices | to_entries[] | select(.value.active == true) | .key'

# View active client
cat /src/api/data/clients-config.json | jq '.active_client'

# Monitor configuration changes
tail -f /src/api/logs/config-changes.log
```

### Debug Dashboard Loading
```javascript
// Add to Dashboard for debugging
console.log('Loading voices from:', '/src/api/data/voices-config.json');
console.log('Active voices:', this.state.voices.filter(v => v.active));
console.log('Current client context:', await this.getCurrentClient());
```

### Test Configuration Changes
```bash
# Test voice visibility toggle
./test-voice-toggle.sh rachel false
# Refresh Dashboard - Rachel should disappear

# Test client context change  
./test-client-change.sh mallplaza
# Generate suggestion - Should use Mallplaza context
```
</monitoring>

---

## Critical Considerations

<critical_notes>
### ⚠️ IMPORTANT: Remote Control Impact

1. **Voice Changes Are Immediate**
   - Toggling voice.active immediately affects ALL Dashboard users
   - No Dashboard code changes needed
   - Changes persist across sessions

2. **Client Context Is Global**
   - Setting active_client changes context for ALL users
   - All AI suggestions will use the active client's context
   - Consider multi-tenancy if needed

3. **Configuration Persistence**
   - Changes are saved to JSON files
   - Files must be writable by web server
   - Backup regularly

4. **No Frontend Deployment Needed**
   - Both systems work by modifying shared configuration
   - Dashboard reads configuration on load
   - Perfect for remote administration

5. **Caching Considerations**
   - Dashboard may cache voice list
   - Force refresh or clear cache after changes
   - Consider implementing config versioning
</critical_notes>

---

## Implementation Checklist

<implementation_checklist>
### For Voice Control
- [ ] Ensure voices-config.json is writable
- [ ] Implement voice-admin.php authentication
- [ ] Test voice toggle affects Dashboard
- [ ] Set up configuration backups
- [ ] Document which voices should be active

### For Client Context
- [ ] Create clients-config.json with initial clients
- [ ] Implement clients-service.php authentication  
- [ ] Test context switching in Claude responses
- [ ] Document each client's context requirements
- [ ] Set up monitoring for context changes

### For Both Systems
- [ ] Implement change logging
- [ ] Create admin UI for easier management
- [ ] Set up automated backups
- [ ] Document standard operating procedures
- [ ] Train administrators on usage
</implementation_checklist>