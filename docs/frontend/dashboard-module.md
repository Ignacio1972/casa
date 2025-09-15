# Dashboard Module Documentation

<module_overview>
El Dashboard es el módulo principal y más crítico del sistema Casa Costanera.
Integra todos los componentes necesarios para la generación, gestión y emisión de audio TTS.
</module_overview>

## Module Architecture

<architecture>
```
/src/modules/dashboard/
├── index.js                  # Módulo principal
├── template.html             # Template HTML del dashboard
├── components/               # Componentes reutilizables
│   ├── ai-suggestions.js     # Sugerencias IA con Claude
│   ├── jingle-controls.js    # Controles de jingles y música
│   ├── message-generator.js  # Generador de mensajes
│   ├── quota-chart.js        # Gráfico de uso de quota
│   ├── recent-messages.js    # Lista de mensajes recientes
│   └── voice-controls.js     # Controles de voz (sliders)
├── services/                 # Servicios del módulo
│   ├── messages-service.js   # Gestión de mensajes
│   └── quota-service.js      # Tracking de quota
└── styles/                   # Estilos del dashboard
    ├── dashboard.css         # Estilos principales
    └── components/           # Estilos por componente
```
</architecture>

---

## Main Dashboard Module

### File: `/src/modules/dashboard/index.js`

<module_structure>
```javascript
export default class DashboardV2Module {
    constructor() {
        this.name = 'dashboard-v2';
        this.state = {
            generating: false,
            currentAudio: null,
            voices: [],
            selectedVoice: 'juan_carlos',
            selectedCategory: 'sin_categoria',
            voiceSettings: {
                style: 0.5,
                stability: 0.75,
                similarity_boost: 0.8,
                use_speaker_boost: true
            },
            recentMessages: []
        };
    }
}
```
</module_structure>

<lifecycle_methods>
### Lifecycle Methods

#### load(container)
```javascript
async load(container) {
    // 1. Cargar estilos CSS
    this.loadStyles();
    
    // 2. Cargar template HTML
    await this.loadTemplate();
    
    // 3. Cachear elementos DOM
    this.cacheElements();
    
    // 4. Cargar voces disponibles
    await this.loadVoices();
    
    // 5. Cargar música disponible
    await this.loadMusicList();
    
    // 6. Configurar event listeners
    this.setupEventListeners();
    
    // 7. Inicializar componentes
    this.initializeAISuggestions();
    this.initializeJingleControls();
    
    // 8. Cargar mensajes recientes
    await this.loadRecentMessages();
    
    // 9. Iniciar actualizaciones periódicas
    this.startPeriodicUpdates();
}
```

#### unload()
```javascript
async unload() {
    // Limpiar intervalos
    if (this.messagesInterval) {
        clearInterval(this.messagesInterval);
    }
    
    // Detener audio si está reproduciéndose
    if (this.currentAudio) {
        this.currentAudio.pause();
    }
    
    // Limpiar componentes
    if (this.aiSuggestions) {
        this.aiSuggestions.destroy();
    }
    
    // Limpiar estado
    this.state = {};
    this.container = null;
}
```
</lifecycle_methods>

<core_methods>
### Core Methods

#### generateAudio()
Genera audio TTS con todos los parámetros configurados.

```javascript
async generateAudio(options = {}) {
    const {
        text = this.elements.textInput.value,
        voice = this.state.selectedVoice,
        category = this.state.selectedCategory,
        saveMessage = false,
        useJingle = false,
        jingleConfig = {}
    } = options;
    
    // Validaciones
    if (!text.trim()) {
        this.showError('Por favor ingrese un texto');
        return;
    }
    
    // Preparar request
    const requestData = {
        text,
        voice,
        category,
        voice_settings: this.state.voiceSettings,
        save_message: saveMessage,
        metadata: {
            generated_from: 'dashboard',
            timestamp: new Date().toISOString()
        }
    };
    
    // Si es jingle, usar servicio de jingles
    if (useJingle) {
        return await this.generateJingle(requestData, jingleConfig);
    }
    
    // Generar TTS normal
    const response = await apiClient.post('/generate.php', requestData);
    
    if (response.success) {
        // Reproducir preview
        this.playAudio(response.filename);
        
        // Actualizar UI
        this.showSuccess('Audio generado exitosamente');
        
        // Actualizar mensajes recientes
        await this.loadRecentMessages();
        
        // Emitir evento
        eventBus.emit('audio:generated', response);
    }
    
    return response;
}
```

#### interruptRadio()
Interrumpe la señal de radio con el audio actual.

```javascript
async interruptRadio(priority = 'high') {
    if (!this.currentAudioFile) {
        this.showError('No hay audio para transmitir');
        return;
    }
    
    const response = await apiClient.post('/services/radio-service.php', {
        action: 'interrupt',
        filename: this.currentAudioFile,
        priority: priority,
        fade_out_current: true,
        fade_duration: 2
    });
    
    if (response.success) {
        this.showSuccess('Señal interrumpida - Audio en emisión');
        eventBus.emit('radio:interrupted', {
            file: this.currentAudioFile,
            priority: priority
        });
    }
}
```
</core_methods>

---

## Component: AI Suggestions

### File: `/components/ai-suggestions.js`

<ai_component>
Sistema de sugerencias inteligentes usando Claude AI para generar contenido.
</ai_component>

<ai_features>
### Features
- **Context-aware suggestions**: Genera según categoría y contexto
- **Multiple tones**: Profesional, casual, urgente, amigable
- **Regeneration**: Regenerar sugerencias individuales
- **Quick apply**: Un click para usar sugerencia
- **Custom prompts**: Personalizar el prompt de generación
</ai_features>

<ai_methods>
### Key Methods

#### generateSuggestions()
```javascript
async generateSuggestions(context = {}) {
    const {
        category = this.dashboard.state.selectedCategory,
        tone = this.contextConfig.tone,
        keywords = this.contextConfig.keywords,
        duration = this.contextConfig.duration
    } = context;
    
    this.isGenerating = true;
    this.updateUI();
    
    const response = await llmService.generateSuggestions({
        category,
        tone,
        keywords,
        duration,
        max_suggestions: 3,
        language: 'es'
    });
    
    if (response.success) {
        this.suggestions = response.suggestions;
        this.renderSuggestions();
    }
    
    this.isGenerating = false;
}
```

#### applySuggestion(suggestionId)
```javascript
applySuggestion(suggestionId) {
    const suggestion = this.suggestions.find(s => s.id === suggestionId);
    
    if (suggestion) {
        // Aplicar texto al input principal
        this.dashboard.elements.textInput.value = suggestion.text;
        
        // Aplicar configuraciones recomendadas
        if (suggestion.recommended_voice) {
            this.dashboard.selectVoice(suggestion.recommended_voice);
        }
        
        if (suggestion.voice_settings) {
            this.dashboard.updateVoiceSettings(suggestion.voice_settings);
        }
        
        // Emitir evento
        eventBus.emit('ai:suggestion:applied', suggestion);
        
        // Feedback visual
        this.highlightAppliedSuggestion(suggestionId);
    }
}
```
</ai_methods>

<ai_configuration>
### Configuration Options
```javascript
{
    showAdvanced: false,          // Mostrar opciones avanzadas
    tone: 'profesional',          // Tono: profesional, casual, urgente, amigable
    duration: 30,                 // Duración objetivo en segundos
    keywords: [],                 // Palabras clave a incluir
    temperature: 0.7,             // Creatividad (0.0 - 1.0)
    model: 'claude-3-haiku'       // Modelo de IA a usar
}
```
</ai_configuration>

---

## Component: Jingle Controls

### File: `/components/jingle-controls.js`

<jingle_component>
Controles avanzados para creación de jingles con música de fondo.
</jingle_component>

<jingle_features>
### Features
- **Music selection**: Biblioteca de música categorizada
- **Volume controls**: Control independiente música/voz
- **Fade controls**: Fade in/out configurables
- **Ducking**: Reducción automática cuando habla
- **Preview**: Escuchar antes de generar
</jingle_features>

<jingle_interface>
### Interface Elements
```javascript
class JingleControls {
    render() {
        return `
            <!-- Music Selection -->
            <select id="musicSelect">
                <option value="">Sin música</option>
                <optgroup label="Comercial">
                    <option value="upbeat_commercial.mp3">Upbeat Commercial</option>
                    <option value="corporate_inspire.mp3">Corporate Inspire</option>
                </optgroup>
                <optgroup label="Seasonal">
                    <option value="christmas_bells.mp3">Christmas Bells</option>
                    <option value="summer_vibes.mp3">Summer Vibes</option>
                </optgroup>
            </select>
            
            <!-- Volume Controls -->
            <div class="volume-controls">
                <input type="range" id="musicVolume" min="0" max="1" step="0.05" value="0.3">
                <input type="range" id="voiceVolume" min="0" max="1" step="0.05" value="1.0">
            </div>
            
            <!-- Fade Controls -->
            <div class="fade-controls">
                <input type="number" id="fadeIn" min="0" max="10" value="2">
                <input type="number" id="fadeOut" min="0" max="10" value="2">
            </div>
            
            <!-- Ducking -->
            <label>
                <input type="checkbox" id="enableDucking" checked>
                Auto-duck music when voice plays
            </label>
        `;
    }
}
```
</jingle_interface>

<jingle_generation>
### Jingle Generation
```javascript
async generateJingle(textData, jingleConfig) {
    const {
        music_file = this.getSelectedMusic(),
        music_volume = this.getMusicVolume(),
        voice_volume = this.getVoiceVolume(),
        fade_in = this.getFadeIn(),
        fade_out = this.getFadeOut(),
        music_duck = this.isDuckingEnabled(),
        duck_level = 0.2
    } = jingleConfig;
    
    const response = await apiClient.post('/jingle-service.php', {
        action: 'generate',
        ...textData,
        music_file,
        music_volume,
        voice_volume,
        fade_in,
        fade_out,
        music_duck,
        duck_level
    });
    
    return response;
}
```
</jingle_generation>

---

## Component: Voice Controls

### File: `/components/voice-controls.js`

<voice_controls>
Sliders avanzados para control fino de parámetros de voz.
</voice_controls>

<voice_parameters>
### Voice Parameters

#### Stability (0.0 - 1.0)
- **0.0**: Máxima expresividad y variación
- **0.5**: Balance entre expresividad y consistencia
- **1.0**: Voz muy estable y monótona

#### Similarity Boost (0.0 - 1.0)
- **0.0**: Permite máxima variación de la voz original
- **0.75**: Recomendado para balance natural
- **1.0**: Máxima similitud con voz original

#### Style (0.0 - 1.0)
- **0.0**: Sin estilización adicional
- **0.5**: Estilización moderada
- **1.0**: Máxima estilización (puede ser más lento)

#### Speaker Boost (boolean)
- **true**: Mejora claridad y presencia de la voz
- **false**: Voz más natural y suave
</voice_parameters>

<voice_slider_component>
### AudioSlider Component
```javascript
class AudioSlider {
    constructor(options) {
        this.min = 0;
        this.max = 100;
        this.value = 50;
        this.step = 1;
        this.label = 'Control';
        this.icon = '🎚️';
        this.unit = '%';
        this.onChange = null;
    }
    
    updateValue(newValue) {
        this.value = Math.max(this.min, Math.min(this.max, newValue));
        this.updateUI();
        
        if (this.onChange) {
            this.onChange(this.value);
        }
        
        // Emitir evento
        eventBus.emit('voice:setting:changed', {
            setting: this.label.toLowerCase(),
            value: this.value
        });
    }
}
```
</voice_slider_component>

---

## Component: Recent Messages

### File: `/components/recent-messages.js`

<recent_messages>
Lista de mensajes generados recientemente con acciones rápidas.
</recent_messages>

<messages_features>
### Features
- **Auto-refresh**: Actualización cada 30 segundos
- **Quick actions**: Play, interrupt, save, delete
- **Filtering**: Por categoría, fecha, favoritos
- **Search**: Búsqueda en texto y metadata
- **Batch operations**: Selección múltiple
</messages_features>

<messages_interface>
### Message Card Interface
```javascript
renderMessageCard(message) {
    return `
        <div class="message-card" data-id="${message.id}">
            <div class="message-header">
                <span class="message-title">${message.title}</span>
                <span class="message-time">${this.formatTime(message.created_at)}</span>
            </div>
            
            <div class="message-content">
                <p>${message.text}</p>
            </div>
            
            <div class="message-metadata">
                <span class="voice-badge">${message.voice}</span>
                <span class="category-badge">${message.category}</span>
                <span class="duration">${message.duration}s</span>
            </div>
            
            <div class="message-actions">
                <button onclick="dashboard.playMessage('${message.id}')" title="Reproducir">
                    <i class="icon-play"></i>
                </button>
                <button onclick="dashboard.interruptWithMessage('${message.id}')" title="Interrumpir Radio">
                    <i class="icon-broadcast"></i>
                </button>
                <button onclick="dashboard.saveMessage('${message.id}')" title="Guardar">
                    <i class="icon-save"></i>
                </button>
                <button onclick="dashboard.deleteMessage('${message.id}')" title="Eliminar">
                    <i class="icon-delete"></i>
                </button>
            </div>
        </div>
    `;
}
```
</messages_interface>

---

## Component: Quota Chart

### File: `/components/quota-chart.js`

<quota_component>
Visualización del uso de quota de APIs (ElevenLabs, Claude).
</quota_component>

<quota_visualization>
### Quota Visualization
```javascript
class QuotaChart {
    constructor(container) {
        this.container = container;
        this.quotaData = {
            elevenlabs: {
                used: 0,
                limit: 10000,
                unit: 'characters'
            },
            claude: {
                used: 0,
                limit: 100000,
                unit: 'tokens'
            }
        };
    }
    
    render() {
        const elevenLabsPercent = (this.quotaData.elevenlabs.used / this.quotaData.elevenlabs.limit) * 100;
        const claudePercent = (this.quotaData.claude.used / this.quotaData.claude.limit) * 100;
        
        return `
            <div class="quota-chart">
                <!-- ElevenLabs Quota -->
                <div class="quota-item">
                    <div class="quota-header">
                        <span>ElevenLabs TTS</span>
                        <span>${this.quotaData.elevenlabs.used} / ${this.quotaData.elevenlabs.limit}</span>
                    </div>
                    <div class="quota-bar">
                        <div class="quota-fill ${this.getStatusClass(elevenLabsPercent)}" 
                             style="width: ${elevenLabsPercent}%"></div>
                    </div>
                </div>
                
                <!-- Claude Quota -->
                <div class="quota-item">
                    <div class="quota-header">
                        <span>Claude AI</span>
                        <span>${this.quotaData.claude.used} / ${this.quotaData.claude.limit}</span>
                    </div>
                    <div class="quota-bar">
                        <div class="quota-fill ${this.getStatusClass(claudePercent)}" 
                             style="width: ${claudePercent}%"></div>
                    </div>
                </div>
            </div>
        `;
    }
    
    getStatusClass(percent) {
        if (percent >= 90) return 'quota-critical';
        if (percent >= 75) return 'quota-warning';
        return 'quota-normal';
    }
}
```
</quota_visualization>

---

## Services

### Messages Service
<messages_service>
```javascript
// /services/messages-service.js
class MessagesService {
    async getRecentMessages(limit = 40) {
        return await apiClient.get('/recent-messages.php', { limit });
    }
    
    async saveMessage(messageData) {
        return await apiClient.post('/saved-messages.php', {
            action: 'save',
            ...messageData
        });
    }
    
    async deleteMessage(messageId) {
        return await apiClient.post('/saved-messages.php', {
            action: 'delete',
            message_id: messageId
        });
    }
}
```
</messages_service>

### Quota Service
<quota_service>
```javascript
// /services/quota-service.js
class QuotaService {
    async getQuotaUsage() {
        const [elevenlabs, claude] = await Promise.all([
            apiClient.get('/playground/api/quota-tracker.php', { service: 'elevenlabs' }),
            apiClient.get('/playground/api/quota-tracker.php', { service: 'claude' })
        ]);
        
        return {
            elevenlabs: elevenlabs.data,
            claude: claude.data
        };
    }
    
    trackUsage(service, amount) {
        // Local tracking for immediate UI update
        this.localUsage[service] = (this.localUsage[service] || 0) + amount;
        
        // Sync with server
        apiClient.post('/playground/api/quota-tracker.php', {
            action: 'track',
            service,
            amount
        });
    }
}
```
</quota_service>

---

## Event System Integration

<events>
### Emitted Events
```javascript
// Audio generation
'audio:generated'           // Audio generado exitosamente
'audio:generation:failed'   // Error en generación
'audio:playing'            // Audio reproduciéndose
'audio:stopped'            // Audio detenido

// Radio
'radio:interrupted'        // Señal interrumpida
'radio:interrupt:failed'   // Error en interrupción

// AI Suggestions
'ai:suggestions:requested' // Sugerencias solicitadas
'ai:suggestions:received'  // Sugerencias recibidas
'ai:suggestion:applied'    // Sugerencia aplicada

// Messages
'message:saved'           // Mensaje guardado
'message:deleted'         // Mensaje eliminado
'messages:refreshed'      // Lista actualizada

// Voice settings
'voice:changed'           // Voz cambiada
'voice:setting:changed'   // Configuración de voz cambiada
```

### Listened Events
```javascript
// From other modules
'category:changed'        // Categoría cambiada desde otro módulo
'template:selected'       // Template seleccionado
'schedule:created'        // Schedule creado para audio

// System events
'module:unloading'        // Módulo descargándose
'api:error'              // Error de API
```
</events>

---

## Styling System

<styling>
### CSS Architecture
```css
/* /styles/dashboard.css */

/* Layout Grid */
.dashboard-container {
    display: grid;
    grid-template-columns: 1fr 380px;
    grid-template-rows: auto 1fr auto;
    gap: var(--spacing-lg);
}

/* Component Containers */
.generator-section { grid-area: generator; }
.ai-panel { grid-area: ai; }
.messages-section { grid-area: messages; }
.controls-section { grid-area: controls; }

/* Responsive */
@media (max-width: 1200px) {
    .dashboard-container {
        grid-template-columns: 1fr;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .dashboard-container {
        --bg-primary: #1a1a1a;
        --text-primary: #ffffff;
    }
}
```
</styling>

---

## Configuration

<configuration>
### Default Configuration
```javascript
const DASHBOARD_CONFIG = {
    // Auto-save
    autoSave: true,
    autoSaveInterval: 30000,  // 30 seconds
    
    // Messages
    messagesRefreshInterval: 30000,
    messagesLimit: 40,
    
    // Audio
    defaultVoice: 'Rachel',
    defaultCategory: 'informativos',
    maxTextLength: 5000,
    
    // Voice Settings Defaults
    voiceDefaults: {
        style: 0.5,
        stability: 0.75,
        similarity_boost: 0.8,
        use_speaker_boost: true
    },
    
    // Jingle Defaults
    jingleDefaults: {
        music_volume: 0.3,
        voice_volume: 1.0,
        fade_in: 2,
        fade_out: 2,
        duck_enabled: true,
        duck_level: 0.2
    },
    
    // AI Suggestions
    aiSuggestions: {
        enabled: true,
        autoGenerate: false,
        maxSuggestions: 3,
        defaultTone: 'profesional'
    }
};
```
</configuration>

---

## Usage Examples

<usage_examples>
### Complete Generation Flow
```javascript
// 1. Initialize dashboard
const dashboard = new DashboardV2Module();
await dashboard.load(document.getElementById('app'));

// 2. Generate with AI suggestion
await dashboard.aiSuggestions.generateSuggestions({
    category: 'promociones',
    tone: 'urgente',
    keywords: ['black friday', '70% descuento']
});

// 3. Apply suggestion
dashboard.aiSuggestions.applySuggestion(0);

// 4. Adjust voice settings
dashboard.updateVoiceSettings({
    stability: 0.3,      // More expressive for urgent
    style: 0.7          // More stylized
});

// 5. Generate as jingle
const result = await dashboard.generateAudio({
    useJingle: true,
    jingleConfig: {
        music_file: 'urgent_promo.mp3',
        music_volume: 0.4,
        fade_in: 1,
        fade_out: 2
    },
    saveMessage: true
});

// 6. Interrupt radio if urgent
if (result.success) {
    await dashboard.interruptRadio('urgent');
}
```

### Custom Voice Configuration
```javascript
// Configure voice for different scenarios
const voicePresets = {
    emergency: {
        voice: 'Antoni',
        settings: {
            stability: 0.2,
            similarity_boost: 0.95,
            style: 0.8,
            use_speaker_boost: true
        }
    },
    informative: {
        voice: 'Rachel',
        settings: {
            stability: 0.8,
            similarity_boost: 0.75,
            style: 0.2,
            use_speaker_boost: true
        }
    },
    promotional: {
        voice: 'Domi',
        settings: {
            stability: 0.5,
            similarity_boost: 0.8,
            style: 0.6,
            use_speaker_boost: true
        }
    }
};

// Apply preset
dashboard.applyVoicePreset(voicePresets.emergency);
```
</usage_examples>

---

## Error Handling

<error_handling>
### Common Errors and Solutions

#### Text Too Long
```javascript
if (text.length > DASHBOARD_CONFIG.maxTextLength) {
    dashboard.showError(`Texto demasiado largo. Máximo ${DASHBOARD_CONFIG.maxTextLength} caracteres.`);
    
    // Offer to split
    const chunks = dashboard.splitTextIntoChunks(text);
    dashboard.offerBatchGeneration(chunks);
}
```

#### Quota Exceeded
```javascript
dashboard.on('quota:exceeded', (service) => {
    if (service === 'elevenlabs') {
        dashboard.showError('Quota de ElevenLabs agotada');
        dashboard.suggestAlternatives([
            'Usar voz en cache',
            'Esperar al próximo mes',
            'Contactar administrador'
        ]);
    }
});
```

#### Generation Failed
```javascript
dashboard.on('generation:failed', (error) => {
    console.error('Generation failed:', error);
    
    // Retry logic
    if (error.code === 'NETWORK_ERROR') {
        dashboard.retryGeneration();
    } else {
        dashboard.showError(error.message);
        dashboard.logError(error);
    }
});
```
</error_handling>

---

## Performance Optimization

<performance>
### Optimization Strategies

1. **Lazy Loading Components**
```javascript
// Load components only when needed
async initializeAISuggestions() {
    if (!this.aiSuggestions) {
        const { AISuggestionsComponent } = await import('./components/ai-suggestions.js');
        this.aiSuggestions = new AISuggestionsComponent(this);
    }
}
```

2. **Debounced Updates**
```javascript
// Debounce voice settings changes
this.updateVoiceSettings = debounce((settings) => {
    this.state.voiceSettings = settings;
    this.saveToLocalStorage();
}, 300);
```

3. **Virtual Scrolling for Messages**
```javascript
// Only render visible messages
class VirtualMessageList {
    renderVisibleMessages() {
        const visibleRange = this.getVisibleRange();
        return this.messages
            .slice(visibleRange.start, visibleRange.end)
            .map(msg => this.renderMessageCard(msg));
    }
}
```

4. **Cache Management**
```javascript
// Cache voices and music lists
const cache = {
    voices: null,
    music: null,
    ttl: 3600000  // 1 hour
};

async loadVoices() {
    if (cache.voices && Date.now() - cache.voices.timestamp < cache.ttl) {
        return cache.voices.data;
    }
    // Fetch and cache
}
```
</performance>

---

## Testing

<testing>
### Unit Tests
```javascript
describe('DashboardV2Module', () => {
    test('generates audio with correct parameters', async () => {
        const dashboard = new DashboardV2Module();
        const mockResponse = { success: true, filename: 'test.mp3' };
        
        apiClient.post = jest.fn().mockResolvedValue(mockResponse);
        
        const result = await dashboard.generateAudio({
            text: 'Test message',
            voice: 'Rachel'
        });
        
        expect(apiClient.post).toHaveBeenCalledWith('/generate.php', 
            expect.objectContaining({
                text: 'Test message',
                voice: 'Rachel'
            })
        );
        expect(result).toEqual(mockResponse);
    });
});
```

### Integration Tests
```javascript
describe('Dashboard Integration', () => {
    test('complete generation flow', async () => {
        // Setup
        const container = document.createElement('div');
        const dashboard = new DashboardV2Module();
        
        // Load module
        await dashboard.load(container);
        
        // Generate suggestions
        await dashboard.aiSuggestions.generateSuggestions();
        expect(dashboard.aiSuggestions.suggestions).toHaveLength(3);
        
        // Apply suggestion
        dashboard.aiSuggestions.applySuggestion(0);
        expect(dashboard.elements.textInput.value).toBeTruthy();
        
        // Generate audio
        const result = await dashboard.generateAudio();
        expect(result.success).toBe(true);
        
        // Cleanup
        await dashboard.unload();
    });
});
```
</testing>