/**
 * Dashboard v2 Module
 * Módulo principal del nuevo dashboard con diseño actualizado
 * Integra generación TTS, controles de voz, quota chart y mensajes recientes
 */

import { getCategoryShortLabel } from '../campaigns/utils/formatters.js';
import { VoiceService } from '../../core/voice-service.js';
import { AISuggestionsComponent } from './components/ai-suggestions.js';
import { JingleControls } from './components/jingle-controls.js';

export default class DashboardV2Module {
    constructor() {
        this.name = 'dashboard-v2';
        this.container = null;
        this.eventBus = window.eventBus;
        this.apiClient = window.apiClient;
        
        // Componente de sugerencias IA
        this.aiSuggestions = null;
        
        // Componente de controles de jingle
        this.jingleControls = null;
        
        // Estado del módulo
        this.state = {
            generating: false,
            currentAudio: null,
            controlsVisible: false,
            voices: [],
            selectedVoice: 'juan_carlos',
            selectedCategory: localStorage.getItem('mbi_selectedCategory') || 'sin_categoria',
            voiceSettings: {
                style: 0.15,  // Valor por defecto: expresividad 15%
                stability: 1.0,  // Valor por defecto: estabilidad 100%
                similarity_boost: 0.5,  // Valor por defecto: similitud 50%
                use_speaker_boost: true  // Siempre activo
            },
            useDefaultValues: true,  // Estado del toggle de valores por defecto
            recentMessages: [],
            lastGeneratedFilename: null  // Para guardar el filename del último audio generado
        };
        
        // Referencias a elementos DOM
        this.elements = {};
        
        // Intervalo para actualizaciones
        this.messagesInterval = null;
        
        // Módulo automático
        this.automaticModule = null;
        
        // Exponer para onclick handlers
        window.dashboardV2 = this;
    }
    
    /**
     * Obtiene el nombre del módulo
     */
    getName() {
        return this.name;
    }
    
    /**
     * Carga los estilos necesarios para el dashboard v2
     * MIGRADO: Los estilos ahora se cargan globalmente desde /styles-v5/main.css
     */
    loadStyles() {
        // Cargar CSS del dashboard dinámicamente solo cuando se necesita
        if (!document.getElementById('dashboard-module-styles')) {
            const link = document.createElement('link');
            link.id = 'dashboard-module-styles';
            link.rel = 'stylesheet';
            link.href = '/styles-v5/3-modules/dashboard.css';
            document.head.appendChild(link);
            console.log('Dashboard styles loaded dynamically');
        }
    }
    
    /**
     * Carga el módulo en el contenedor
     */
    async load(container) {
        this.container = container;
        
        // Cargar los CSS del nuevo diseño
        this.loadStyles();
        
        try {
            // 1. Cargar el template HTML
            await this.loadTemplate();
            
            // 2. Obtener referencias a elementos DOM
            this.cacheElements();
            
            // 3. Cargar voces disponibles
            await this.loadVoices();
            
            // 3.5. Cargar música disponible
            await this.loadMusicList();
            
            // 4. Configurar event listeners
            this.setupEventListeners();
            
            // 5. Inicializar componente de sugerencias IA
            this.initializeAISuggestions();
            
            // 6. Inicializar controles de jingle
            this.initializeJingleControls();
            
            // 7. Cargar datos iniciales
            await this.loadInitialData();
            
            // 8. Iniciar actualizaciones periódicas
            this.startPeriodicUpdates();
            
            // 9. Emitir evento de módulo cargado
            this.eventBus.emit('module:loaded', { module: this.name });
            
        } catch (error) {
            console.error('[Dashboard v2] Error loading module:', error);
            this.showError('Error al cargar el módulo');
        }
    }
    
    /**
     * Carga el template HTML
     */
    async loadTemplate() {
        const response = await fetch('/modules/dashboard/template.html');
        if (!response.ok) {
            throw new Error('Failed to load template');
        }
        const html = await response.text();
        this.container.innerHTML = html;

        // Esperar un tick para que el DOM se actualice completamente
        await new Promise(resolve => setTimeout(resolve, 0));
    }
    
    /**
     * Cachea referencias a elementos DOM
     */
    cacheElements() {
        this.elements = {
            // Generador
            messageText: document.getElementById('messageText'),
            voiceSelect: document.getElementById('voiceSelect'),
            musicSelect: document.getElementById('musicSelect'),
            generateBtn: document.getElementById('generateBtn'),
            messageForm: document.getElementById('messageForm'),
            
            // Controles avanzados (siempre visibles)
            controlsSection: document.getElementById('controlsSection'),
            // Controles de voz reactivados
            styleSlider: document.getElementById('styleSlider'),
            styleValue: document.getElementById('styleValue'),
            styleTrack: document.getElementById('styleTrack'),
            stabilitySlider: document.getElementById('stabilitySlider'),
            stabilityValue: document.getElementById('stabilityValue'),
            stabilityTrack: document.getElementById('stabilityTrack'),
            claritySlider: document.getElementById('claritySlider'),
            clarityValue: document.getElementById('clarityValue'),
            clarityTrack: document.getElementById('clarityTrack'),
            
            // Toggle de valores por defecto
            defaultValuesToggle: document.getElementById('defaultValuesToggle'),
            
            // Quota chart eliminado - ya no se usa
            // quotaProgressCircle: document.getElementById('quotaProgressCircle'),
            // quotaPercentage: document.getElementById('quotaPercentage'),
            // quotaUsed: document.getElementById('quotaUsed'),
            // quotaRemaining: document.getElementById('quotaRemaining'),
            // quotaResetDate: document.getElementById('quotaResetDate'),
            
            // Mensajes
            messageList: document.getElementById('messageList'),
            refreshMessages: document.getElementById('refreshMessages')
        };
    }
    
    /**
     * Carga las voces disponibles usando VoiceService
     */
    async loadVoices() {
        try {
            // Usar VoiceService para cargar voces (igual que message-configurator)
            const voicesData = await VoiceService.loadVoices();
            
            // Convertir objeto a array con el formato esperado
            this.state.voices = Object.entries(voicesData).map(([key, voice]) => ({
                key: key,
                id: voice.id,
                label: voice.label,
                gender: voice.gender,
                is_default: voice.is_default || false,
                order: voice.order || 999
            }));
            
            // Ordenar voces por el campo 'order'
            this.state.voices.sort((a, b) => a.order - b.order);
            
            // Poblar el selector de voces
            this.populateVoiceSelector();
            
            // Buscar y establecer la voz por defecto
            const defaultVoice = this.state.voices.find(v => v.is_default);
            if (defaultVoice) {
                this.state.selectedVoice = defaultVoice.key;
                this.elements.voiceSelect.value = defaultVoice.key;
                console.log('[Dashboard v2] Voz por defecto establecida:', defaultVoice.label);
            } else if (this.state.voices.length > 0) {
                // Si no hay voz por defecto, usar la primera
                this.state.selectedVoice = this.state.voices[0].key;
                this.elements.voiceSelect.value = this.state.voices[0].key;
            }
            
        } catch (error) {
            console.error('[Dashboard v2] Error loading voices:', error);
            // Voz por defecto si falla
            this.state.voices = [{
                key: 'juan_carlos',
                label: 'Juan Carlos',
                id: 'G4IAP30yc6c1gK0csDfu',
                gender: 'M'
            }];
            this.populateVoiceSelector();
        }
    }
    
    /**
     * Pobla el selector de voces
     */
    populateVoiceSelector() {
        this.elements.voiceSelect.innerHTML = '';
        
        this.state.voices.forEach(voice => {
            const option = document.createElement('option');
            option.value = voice.key;
            option.textContent = voice.label;
            this.elements.voiceSelect.appendChild(option);
        });
    }
    
    /**
     * Carga la lista de música disponible
     */
    async loadMusicList() {
        try {
            const response = await fetch('/api/jingle-service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list_music' })
            });

            const data = await response.json();
            
            if (data.success && data.music && this.elements.musicSelect) {
                // Mantener la opción "Sin música"
                this.elements.musicSelect.innerHTML = '<option value="">-- Sin música --</option>';
                
                data.music.forEach(music => {
                    const displayName = music.file ? music.file.replace('.mp3', '').replace('.wav', '').replace('.ogg', '') : music.name;
                    const option = document.createElement('option');
                    option.value = music.file;
                    option.textContent = displayName;
                    this.elements.musicSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('[Dashboard] Error cargando música:', error);
        }
    }
    
    /**
     * Inicializa los controles de jingle
     */
    initializeJingleControls() {
        // Crear una instancia del componente JingleControls pero sin renderizarlo
        // Solo lo necesitamos para cargar y gestionar la configuración
        const hiddenContainer = document.createElement('div');
        hiddenContainer.style.display = 'none';
        this.jingleControls = new JingleControls(hiddenContainer);
        console.log('[Dashboard] JingleControls inicializado para gestión de configuración');
    }
    
    /**
     * Configura los event listeners
     */
    setupEventListeners() {
        // Botón generar
        if (this.elements.generateBtn) {
            this.elements.generateBtn.addEventListener('click', () => this.handleGenerate());
        }

        // Controles de voz reactivados

        // Toggle de valores por defecto
        if (this.elements.defaultValuesToggle) {
            this.elements.defaultValuesToggle.addEventListener('change', (e) => {
                this.handleDefaultValuesToggle(e.target.checked);
            });
        }

        // Sliders
        this.setupSlider('style', 'Style', value => this.state.voiceSettings.style = value / 100);
        this.setupSlider('stability', 'Stability', value => this.state.voiceSettings.stability = value / 100);
        this.setupSlider('clarity', 'Similarity', value => this.state.voiceSettings.similarity_boost = value / 100);

        // Selector de voz
        if (this.elements.voiceSelect) {
            this.elements.voiceSelect.addEventListener('change', (e) => {
                this.state.selectedVoice = e.target.value;
            });
        }

        // Inicializar categoría moderna
        this.initializeCategory();

        // Refrescar mensajes (verificar que exista)
        if (this.elements.refreshMessages) {
            this.elements.refreshMessages.addEventListener('click', () => this.loadRecentMessages());
        }

        // Escuchar eventos del sistema
        this.eventBus.on('message:saved:library', () => this.loadRecentMessages());
    }
    
    /**
     * Maneja el toggle de valores por defecto
     */
    handleDefaultValuesToggle(isChecked) {
        this.state.useDefaultValues = isChecked;
        
        if (isChecked) {
            // Resetear a valores por defecto
            const defaults = {
                style: 15,      // Expresividad 15%
                stability: 100,  // Estabilidad 100%
                similarity: 50  // Similitud 50%
            };
            
            // Actualizar sliders
            this.elements.styleSlider.value = defaults.style;
            this.elements.styleValue.textContent = defaults.style + '%';
            this.elements.styleTrack.style.width = defaults.style + '%';
            
            this.elements.stabilitySlider.value = defaults.stability;
            this.elements.stabilityValue.textContent = defaults.stability + '%';
            this.elements.stabilityTrack.style.width = defaults.stability + '%';
            
            this.elements.claritySlider.value = defaults.similarity;
            this.elements.clarityValue.textContent = defaults.similarity + '%';
            this.elements.clarityTrack.style.width = defaults.similarity + '%';
            
            // Actualizar estado (convertir porcentajes a valores decimales)
            this.state.voiceSettings.style = defaults.style / 100;  // 0.15
            this.state.voiceSettings.stability = defaults.stability / 100;  // 1.0
            this.state.voiceSettings.similarity_boost = defaults.similarity / 100;  // 0.5
            
            console.log('[Dashboard v2] Valores reseteados a default:', this.state.voiceSettings);
        }
    }
    
    /**
     * Configura un slider
     */
    setupSlider(name, setting, callback) {
        const slider = this.elements[`${name}Slider`];
        const value = this.elements[`${name}Value`];
        const track = this.elements[`${name}Track`];

        // Verificar que todos los elementos existen
        if (!slider || !value || !track) {
            console.warn(`[Dashboard v2] Slider ${name} elements not found`);
            return;
        }

        slider.addEventListener('input', (e) => {
            const val = e.target.value;
            value.textContent = val + '%';
            track.style.width = val + '%';
            callback(val);

            // Desactivar toggle de valores por defecto al cambiar un slider
            if (this.state.useDefaultValues && this.elements.defaultValuesToggle) {
                this.state.useDefaultValues = false;
                this.elements.defaultValuesToggle.checked = false;
            }
        });
    }
    
    /**
     * Maneja la generación de audio
     */
    async handleGenerate() {
        if (this.state.generating) return;
        
        const text = this.elements.messageText.value.trim();
        if (!text) {
            this.showError('Por favor ingrese un texto');
            return;
        }
        
        this.state.generating = true;
        this.elements.generateBtn.disabled = true;
        this.elements.generateBtn.textContent = 'Generando...';
        
        try {
            // Verificar si se debe generar un jingle - ahora verificamos el selector de música directamente
            const selectedMusic = this.elements.musicSelect ? this.elements.musicSelect.value : '';
            
            // Si hay música seleccionada, actualizar el jingleControls
            if (selectedMusic && this.jingleControls) {
                this.jingleControls.selectedMusic = selectedMusic;
                this.jingleControls.enabled = true;
            } else if (this.jingleControls) {
                // Si no hay música, desactivar jingle
                this.jingleControls.selectedMusic = null;
                this.jingleControls.enabled = false;
            }
            
            const jingleOptions = (selectedMusic && this.jingleControls) ? this.jingleControls.getJingleOptions() : null;
            
            // Si hay música seleccionada o jingle controls está activo
            if (selectedMusic || jingleOptions) {
                // Generar jingle
                
                // Usar las opciones del jingleControls si existen, sino usar valores por defecto
                const finalJingleOptions = jingleOptions || {
                    music_volume: 0.3,
                    voice_volume: 1.0,
                    fade_in: 2,
                    fade_out: 2,
                    music_duck: true,
                    duck_level: 0.2,
                    intro_silence: 2,
                    outro_silence: 4
                };
                
                // Si hay música seleccionada en el dropdown y no viene del jingleControls, usarla
                if (selectedMusic && !jingleOptions) {
                    finalJingleOptions.music_file = selectedMusic;
                }
                
                console.log('[Dashboard] Generando jingle con opciones:', finalJingleOptions);
                
                // Agregar voice_settings a las opciones del jingle
                const voiceSettings = finalJingleOptions.voice_settings || {
                    style: this.state.voiceSettings.style,
                    stability: this.state.voiceSettings.stability,
                    similarity_boost: this.state.voiceSettings.similarity_boost,
                    use_speaker_boost: this.state.voiceSettings.use_speaker_boost
                };
                
                const jingleOptionsWithVoice = {
                    ...finalJingleOptions,
                    voice_settings: voiceSettings
                };
                
                const response = await this.apiClient.post('/api/jingle-service.php', {
                    action: 'generate',
                    text: text,
                    voice: this.state.selectedVoice,
                    category: this.state.selectedCategory, // Agregar categoría para que se guarde en BD
                    options: jingleOptionsWithVoice
                });
                
                if (response.success && response.audio) {
                    // Crear URL del audio base64
                    const audioUrl = 'data:audio/mp3;base64,' + response.audio;
                    this.playAudio(audioUrl);
                    this.showSuccess(`¡Jingle generado! Duración: ${response.duration?.toFixed(1) || 'N/A'}s`);

                    // Guardar el filename del jingle para poder enviarlo después
                    if (response.filename) {
                        this.state.lastGeneratedFilename = response.filename;
                    }

                    // Actualizar mensajes recientes para que aparezca el jingle
                    await this.loadRecentMessages();
                } else {
                    throw new Error(response.error || 'Error al generar jingle');
                }
            } else {
                // Generación normal de TTS
                const voice = this.state.voices.find(v => v.key === this.state.selectedVoice);
                console.log('Voz seleccionada:', this.state.selectedVoice);
                console.log('Voz encontrada:', voice);
                console.log('Categoría seleccionada:', this.state.selectedCategory);
                
                if (!voice) {
                    this.showError('No se encontró la voz seleccionada');
                    return;
                }
                
                const response = await this.apiClient.post('/api/generate.php', {
                    action: 'generate_audio',
                    text: text,
                    voice: voice.id,
                    category: this.state.selectedCategory,
                    voice_settings: {
                        style: this.state.voiceSettings.style,
                        stability: this.state.voiceSettings.stability,
                        similarity_boost: this.state.voiceSettings.similarity_boost,
                        use_speaker_boost: this.state.voiceSettings.use_speaker_boost
                    }
                });
                
                // La API devuelve filename, no audio_url
                if (response.success && (response.audio_url || response.filename)) {
                    const audioUrl = response.audio_url || `/api/temp/${response.filename}`;
                    this.playAudio(audioUrl);
                    this.showSuccess('Audio generado exitosamente');

                    // Guardar el filename para poder enviarlo después
                    this.state.lastGeneratedFilename = response.filename;

                    // Actualizar mensajes recientes (quota eliminado)
                    await this.loadRecentMessages();
                } else {
                    throw new Error(response.error || 'Error al generar audio');
                }
            }
            
        } catch (error) {
            console.error('[Dashboard v2] Generation error:', error);
            this.showError('Error al generar el audio: ' + error.message);
        } finally {
            this.state.generating = false;
            this.elements.generateBtn.disabled = false;
            this.elements.generateBtn.innerHTML = '<span>🎙️</span> Generar Audio';
        }
    }
    
    /**
     * Reproduce el audio generado
     */
    playAudio(url, filename = null) {
        // Crear reproductor si no existe
        let playerContainer = document.getElementById('audioPlayerContainer');
        if (!playerContainer) {
            playerContainer = document.createElement('div');
            playerContainer.id = 'audioPlayerContainer';
            playerContainer.className = 'audio-player-container';
            playerContainer.style.marginTop = '1rem';
            playerContainer.innerHTML = `
                <audio id="audioPlayer" controls class="audio-player" style="width: 100%;"></audio>
                <div class="player-actions" style="margin-top: 1rem; display: flex; gap: 1rem; align-items: center; justify-content: flex-end;">
                    <button type="button" id="saveToLibraryBtn" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                        💾 Guardar en Biblioteca
                    </button>
                    <button type="button" id="sendToLocalPlayerBtn" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                        🖥️ Enviar a Máquina Local
                    </button>
                    <button type="button" id="sendToAzuracastBtn" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor">
                            <path d="M480-120q-42 0-71-29t-29-71q0-42 29-71t71-29q42 0 71 29t29 71q0 42-29 71t-71 29ZM254-346l-84-86q59-59 138.5-93.5T480-560q92 0 171.5 35T790-430l-84 84q-44-44-102-69t-124-25q-66 0-124 25t-102 69ZM84-516 0-600q92-94 215-147t265-53q142 0 265 53t215 147l-84 84q-77-77-178.5-120.5T480-680q-116 0-217.5 43.5T84-516Z"/>
                        </svg>
                        Enviar a AzuraCast
                    </button>
                </div>
            `;
            this.elements.messageForm.parentNode.appendChild(playerContainer);
        }

        const audio = playerContainer.querySelector('#audioPlayer');

        // Resetear todos los botones antes de reproducir
        this.resetAllPlayButtons();

        // Remover event listeners anteriores para evitar duplicados
        const newAudio = audio.cloneNode(true);
        audio.parentNode.replaceChild(newAudio, audio);

        newAudio.src = url;
        newAudio.play();
        playerContainer.style.display = 'block';

        // Guardar referencia al filename actual
        newAudio.dataset.currentFilename = filename;

        // Event listeners para sincronizar botones
        if (filename) {
            newAudio.addEventListener('play', () => {
                this.updatePlayButton(filename, 'playing');
            });

            newAudio.addEventListener('pause', () => {
                this.updatePlayButton(filename, 'paused');
            });

            newAudio.addEventListener('ended', () => {
                this.updatePlayButton(filename, 'paused');
            });
        }

        // Configurar botón de guardar
        const saveBtn = playerContainer.querySelector('#saveToLibraryBtn');
        saveBtn.onclick = () => this.saveToLibrary(url);

        // Configurar botón de enviar a Máquina Local
        const sendToLocalPlayerBtn = playerContainer.querySelector('#sendToLocalPlayerBtn');
        sendToLocalPlayerBtn.onclick = () => this.sendToLocalPlayer();

        // Configurar botón de enviar a AzuraCast
        const sendToAzuracastBtn = playerContainer.querySelector('#sendToAzuracastBtn');
        sendToAzuracastBtn.onclick = () => this.sendToAzuracast();
    }
    
    /**
     * Guarda el mensaje en la biblioteca
     */
    async saveToLibrary(audioUrl) {
        // 1. Validar que existe un archivo de audio generado
        const filename = this.state.lastGeneratedFilename;

        if (!filename) {
            this.showToast('No hay audio disponible para guardar', 'error');
            return;
        }

        // 2. Obtener título del mensaje (truncar a 50 caracteres)
        const text = this.elements.messageText.value.trim();
        const title = text ? text.substring(0, 50) : 'Audio sin título';

        // 3. Obtener categoría seleccionada
        const category = this.state.selectedCategory || 'sin_categoria';

        try {
            // 4. Guardar en la base de datos mediante API
            const response = await this.apiClient.post('/api/saved-messages.php', {
                action: 'mark_as_saved',
                filename: filename,
                category: category,
                title: title
            });

            if (response.success) {
                // 5. Emitir evento para Campaign Library con datos completos
                this.eventBus.emit('message:saved:library', {
                    id: response.data.id,
                    filename: response.data.filename,
                    title: response.data.display_name || title,
                    category: response.data.category,
                    type: 'audio',
                    savedAt: response.data.saved_at
                });

                // 6. Mostrar toast de éxito (igual que saveToFavorites)
                this.showToast('✓ Mensaje guardado exitosamente', 'success');

                console.log('[Dashboard v2] Mensaje guardado en biblioteca:', {
                    filename: filename,
                    title: title,
                    category: category
                });
            } else {
                throw new Error(response.error || 'Error al guardar en la base de datos');
            }

        } catch (error) {
            console.error('[Dashboard v2] Error guardando en biblioteca:', error);
            this.showToast('Error al guardar el mensaje', 'error');
        }
    }
    
    /**
     * Envía el mensaje generado a Máquina Local (Player Local)
     */
    async sendToLocalPlayer() {
        const filename = this.state.lastGeneratedFilename;

        if (!filename) {
            this.showError('No hay audio disponible para enviar');
            return;
        }

        if (!confirm('¿Quiere enviar este mensaje a 🖥️ Máquina Local?')) return;

        try {
            const response = await this.apiClient.post('/api/generate.php', {
                action: 'send_to_radio',
                filename: filename,
                destination: 'local_player'
            });

            if (response.success) {
                this.showSuccess('¡Mensaje enviado a 🖥️ Máquina Local!');
                await this.loadRecentMessages();
            } else {
                throw new Error(response.error || 'Error desconocido');
            }
        } catch (error) {
            console.error('[Dashboard v2] Error enviando a Máquina Local:', error);
            this.showError('Error al enviar a Máquina Local: ' + error.message);
        }
    }

    /**
     * Envía el mensaje generado a AzuraCast (Radio)
     */
    async sendToAzuracast() {
        const filename = this.state.lastGeneratedFilename;

        if (!filename) {
            this.showError('No hay audio disponible para enviar');
            return;
        }

        if (!confirm('¿Quiere que este mensaje suene ahora mismo en 📻 AzuraCast?')) return;

        try {
            const response = await this.apiClient.post('/api/generate.php', {
                action: 'send_to_radio',
                filename: filename,
                destination: 'azuracast'
            });

            if (response.success) {
                this.showSuccess('¡Mensaje enviado a 📻 AzuraCast!');
                await this.loadRecentMessages();
            } else {
                throw new Error(response.error || 'Error desconocido');
            }
        } catch (error) {
            console.error('[Dashboard v2] Error enviando a AzuraCast:', error);
            this.showError('Error al enviar a AzuraCast: ' + error.message);
        }
    }
    
    /**
     * Controles avanzados siempre visibles - método eliminado
     */
    
    // Función removida - quota chart ya no se usa
    
    /**
     * Carga los mensajes recientes
     */
    async loadRecentMessages() {
        try {
            const response = await fetch('/api/recent-messages.php');
            const data = await response.json();
            
            if (data.success) {
                this.state.recentMessages = data.messages || [];
                this.renderMessages();
            }
            
        } catch (error) {
            console.error('[Dashboard v2] Error loading messages:', error);
            // Mostrar mensajes de ejemplo si falla
            this.renderExampleMessages();
        }
    }
    
    /**
     * Renderiza los mensajes
     */
    renderMessages() {
        if (!this.state.recentMessages.length) {
            this.elements.messageList.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    No hay mensajes recientes
                </div>
            `;
            return;
        }
        
        this.elements.messageList.innerHTML = this.state.recentMessages.map(msg => `
            <div class="message-item" data-id="${msg.id}">
                <div class="message-header">
                    <span class="message-title">${this.truncateText(msg.title || msg.content || 'Audio', 30)}</span>
                    <span class="message-badge badge-${msg.category || 'sin-categoria'}">${this.getCategoryLabel(msg.category)}</span>
                </div>
                <div class="message-preview">${this.truncateText(msg.notes || msg.content || 'Archivo de audio guardado', 100)}</div>
                <div class="message-footer">
                    <div class="message-actions">
                        ${msg.filename ? `<button class="btn-icon play-btn" data-filename="${msg.filename}" title="Reproducir" onclick="window.dashboardV2.togglePlayPause('${msg.filename}')">▶</button>` : ''}
                        <button class="btn-icon btn-save" title="Guardar" onclick="window.dashboardV2.saveToFavorites('${msg.id}', '${msg.filename || ''}', '${(msg.title || '').replace(/'/g, "\\'")}', '${msg.category || 'sin_categoria'}')">✓</button>
                        <button class="btn-icon btn-delete" title="Eliminar" onclick="window.dashboardV2.removeMessage('${msg.id}')">🗑️</button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    /**
     * Método para alternar play/pause en mensajes recientes
     */
    togglePlayPause(filename) {
        if (!filename) {
            this.showError('Audio no disponible');
            return;
        }

        const audioPlayer = document.getElementById('audioPlayer');
        const currentUrl = `/api/biblioteca.php?filename=${filename}`;

        // Si existe un player y está reproduciendo este mismo archivo
        if (audioPlayer && audioPlayer.src === window.location.origin + currentUrl) {
            if (audioPlayer.paused) {
                audioPlayer.play();
                this.updatePlayButton(filename, 'playing');
            } else {
                audioPlayer.pause();
                this.updatePlayButton(filename, 'paused');
            }
        } else {
            // Reproducir nuevo audio
            this.playMessageAudio(filename);
        }
    }

    // Método para actualizar el icono del botón
    updatePlayButton(filename, state) {
        const button = this.elements.messageList.querySelector(`button[data-filename="${filename}"]`);
        if (button) {
            if (state === 'playing') {
                button.innerHTML = '⏸';
                button.title = 'Pausar';
            } else {
                button.innerHTML = '▶';
                button.title = 'Reproducir';
            }
        }
    }

    // Resetear todos los botones de play a estado inicial
    resetAllPlayButtons() {
        const playButtons = this.elements.messageList.querySelectorAll('.play-btn');
        playButtons.forEach(btn => {
            btn.innerHTML = '▶';
            btn.title = 'Reproducir';
        });
    }

    // Método para reproducir audio
    playMessageAudio(filename) {
        if (!filename) {
            this.showError('Audio no disponible');
            return;
        }
        const audioUrl = `/api/biblioteca.php?filename=${filename}`;
        this.playAudio(audioUrl, filename);
    }

    // Método para guardar en favoritos
    async saveToFavorites(id, filename, title, originalCategory = null) {
        const messageCard = this.container.querySelector(`[data-id="${id}"]`);
        
        // 1. Animar slide hacia la derecha
        if (messageCard) {
            messageCard.style.transition = 'transform 0.4s ease, opacity 0.4s ease';
            messageCard.style.transform = 'translateX(100%)';
            messageCard.style.opacity = '0';
        }
        
        try {
            // 2. Guardar en background
            // Usar la categoría original del mensaje si existe, sino usar la seleccionada (para mensajes nuevos generados)
            const response = await this.apiClient.post('/api/saved-messages.php', {
                action: 'mark_as_saved',
                id: id,
                filename: filename,
                category: originalCategory || this.state.selectedCategory,
                title: title
            });
            
            if (response.success) {
                // Emitir evento para Campaign Library
                this.eventBus.emit('message:saved:library', {
                    id: response.data.id,
                    filename: response.data.filename,
                    title: response.data.display_name || title,
                    category: response.data.category,
                    type: 'audio',
                    savedAt: response.data.saved_at
                });
                
                // Mostrar toast de éxito
                this.showToast('✓ Mensaje guardado exitosamente', 'success');
            }
        } catch (error) {
            console.error('Error guardando mensaje:', error);
            this.showToast('Error al guardar el mensaje', 'error');
        }
        
        // 3. Remover card después de la animación
        if (messageCard) {
            setTimeout(() => messageCard.remove(), 400);
        }
    }

    // Método para eliminar mensaje (soft delete)
    async removeMessage(id) {
        if (confirm('¿Archivar este mensaje?')) {
            try {
                // Llamar API para soft delete
                const response = await this.apiClient.post('/api/saved-messages.php', {
                    action: 'soft_delete',
                    id: id
                });
                
                if (response.success) {
                    // Animar y remover de UI
                    const card = this.elements.messageList.querySelector(`[data-id="${id}"]`);
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(-20px)';
                        setTimeout(() => {
                            this.loadRecentMessages(); // Recargar lista
                        }, 300);
                    }
                    this.showSuccess('Mensaje archivado');
                }
            } catch (error) {
                console.error('Error archivando mensaje:', error);
                this.showError('Error al archivar el mensaje');
            }
        }
    }
    
    /**
     * Renderiza mensajes de ejemplo
     */
    renderExampleMessages() {
        const examples = [
            { id: 'example_1', title: 'Oferta Black Friday', content: 'Atención visitantes, este viernes...', category: 'ofertas', created_at: new Date() },
            { id: 'example_2', title: 'Cambio de Horario', content: 'Informamos a nuestros visitantes...', category: 'informacion', created_at: new Date(Date.now() - 3600000) },
            { id: 'example_3', title: 'Evento Musical', content: 'Este sábado presentamos música en vivo...', category: 'eventos', created_at: new Date(Date.now() - 7200000) }
        ];
        
        this.state.recentMessages = examples;
        this.renderMessages();
    }
    
    /**
     * Carga datos iniciales
     */
    async loadInitialData() {
        // Controles avanzados siempre visibles - no necesita localStorage
        
        // Inicializar valores de los sliders con los valores por defecto
        if (this.state.useDefaultValues) {
            // Aplicar valores por defecto
            const defaults = {
                style: 15,      // Expresividad 15%
                stability: 100,  // Estabilidad 100%
                similarity: 50  // Similitud 50%
            };
            
            // Actualizar sliders
            if (this.elements.styleSlider) {
                this.elements.styleSlider.value = defaults.style;
                this.elements.styleValue.textContent = defaults.style + '%';
                this.elements.styleTrack.style.width = defaults.style + '%';
            }
            
            if (this.elements.stabilitySlider) {
                this.elements.stabilitySlider.value = defaults.stability;
                this.elements.stabilityValue.textContent = defaults.stability + '%';
                this.elements.stabilityTrack.style.width = defaults.stability + '%';
            }
            
            if (this.elements.claritySlider) {
                this.elements.claritySlider.value = defaults.similarity;
                this.elements.clarityValue.textContent = defaults.similarity + '%';
                this.elements.clarityTrack.style.width = defaults.similarity + '%';
            }
            
            // Marcar el toggle como activo
            if (this.elements.defaultValuesToggle) {
                this.elements.defaultValuesToggle.checked = true;
            }
        }
        
        // Cargar mensajes recientes
        await this.loadRecentMessages();
    }
    
    /**
     * Inicia actualizaciones periódicas
     */
    startPeriodicUpdates() {
        
        // Actualizar mensajes cada minuto
        this.messagesInterval = setInterval(() => this.loadRecentMessages(), 60000);
    }
    
    /**
     * Cambiar entre tabs
     */
    async switchTab(tabName) {
        console.log('Switching to tab:', tabName);
        
        // Actualizar botones de tab
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            }
        });
        
        // Cambiar contenido
        if (tabName === 'manual') {
            document.getElementById('manual-tab-content').style.display = 'block';
            document.getElementById('automatic-tab-content').style.display = 'none';
            
            // Descargar módulo automático si está cargado
            if (this.automaticModule) {
                this.automaticModule.unload();
                this.automaticModule = null;
            }
        } else if (tabName === 'automatic') {
            document.getElementById('manual-tab-content').style.display = 'none';
            const automaticContainer = document.getElementById('automatic-tab-content');
            automaticContainer.style.display = 'block';
            
            // Cargar módulo automático si no está cargado
            if (!this.automaticModule) {
                try {
                    // Mostrar mensaje de carga temporal
                    automaticContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: #a6bdc9;">Cargando módulo automático...</div>';
                    
                    const { default: AutomaticModeModule } = await import('../automatic/index.js');
                    this.automaticModule = new AutomaticModeModule();
                    await this.automaticModule.load(automaticContainer);
                } catch (error) {
                    console.error('Error loading automatic module:', error);
                    automaticContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">Error al cargar el módulo automático. Por favor recarga la página.</div>';
                }
            }
        }
    }
    
    /**
     * Descarga el módulo
     */
    async unload() {
        // Descargar módulo automático si está cargado
        if (this.automaticModule) {
            this.automaticModule.unload();
            this.automaticModule = null;
        }
        
        // Limpiar estilos del módulo
        const moduleStyles = document.querySelectorAll(`link[data-module="${this.name}"]`);
        moduleStyles.forEach(link => link.remove());
        
        // Remover CSS específico del dashboard
        const dashboardStyles = document.getElementById('dashboard-module-styles');
        if (dashboardStyles) {
            dashboardStyles.remove();
            console.log('[Dashboard v2] Styles removed');
        }
        
        // Limpiar intervalo
        if (this.messagesInterval) clearInterval(this.messagesInterval);
        
        // Limpiar event listeners
        this.eventBus.off('message:saved:library');
        
        // Limpiar contenedor
        if (this.container) {
            this.container.innerHTML = '';
        }
        
        // Emitir evento de módulo descargado
        this.eventBus.emit('module:unloaded', { module: this.name });
    }
    
    // ========== Utilidades ==========
    
    formatNumber(num) {
        if (num > 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num > 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    }
    
    truncateText(text, length) {
        if (!text) return '';
        return text.length > length ? text.substring(0, length) + '...' : text;
    }
    
    getCategoryLabel(category) {
        const labels = {
            'ofertas': 'Ofertas',
            'eventos': 'Eventos',
            'informacion': 'Info',
            'servicios': 'Servicios',
            'horarios': 'Horarios',
            'emergencias': 'Urgente',
            'sin_categoria': 'Sin Cat.'
        };
        return labels[category] || 'Sin Cat.';
    }
    
    getRelativeTime(date) {
        if (!date) return 'Ahora';
        
        const now = new Date();
        const past = new Date(date);
        const diff = Math.floor((now - past) / 1000);
        
        if (diff < 60) return 'Hace un momento';
        if (diff < 3600) return `Hace ${Math.floor(diff / 60)} minutos`;
        if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} horas`;
        return `Hace ${Math.floor(diff / 86400)} días`;
    }
    
    showSuccess(message) {
        // Aquí podrías implementar un toast notification
        console.log('[Dashboard v2] Success:', message);
    }
    
    showError(message) {
        // Aquí podrías implementar un toast notification
        console.error('[Dashboard v2] Error:', message);
        alert(message); // Temporal
    }
    
    showToast(message, type = 'success') {
        // Crear toast notification
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10b981' : '#ef4444'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            font-weight: 500;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        // Animar entrada
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 10);
        
        // Animar salida y remover
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    /**
     * Maneja el dropdown de categorías
     */
    toggleCategoryDropdown(event) {
        event.stopPropagation();
        
        const dropdown = document.getElementById('categoryDropdown');
        if (dropdown) {
            dropdown.classList.toggle('active');
        }
        
        // Cerrar dropdown al hacer clic fuera
        if (!this.clickOutsideHandler) {
            this.clickOutsideHandler = (e) => {
                if (!e.target.closest('.category-badge-container')) {
                    dropdown.classList.remove('active');
                }
            };
            document.addEventListener('click', this.clickOutsideHandler);
        }
    }
    
    /**
     * Actualiza la categoría seleccionada
     */
    updateCategory(category) {
        // Actualizar estado
        this.state.selectedCategory = category;
        localStorage.setItem('mbi_selectedCategory', category);
        
        // Actualizar UI del badge
        const badge = document.getElementById('categoryBadge');
        const dropdown = document.getElementById('categoryDropdown');
        
        if (badge) {
            badge.textContent = getCategoryShortLabel(category);
            badge.className = `message-badge badge-${category}`;
            badge.setAttribute('data-category', category);
        }
        
        // Cerrar dropdown
        if (dropdown) {
            dropdown.classList.remove('active');
        }
        
        console.log('[Dashboard v2] Categoría actualizada a:', category);
    }
    
    /**
     * Inicializa la categoría desde localStorage
     */
    initializeCategory() {
        const savedCategory = this.state.selectedCategory;
        this.updateCategory(savedCategory);
    }
    
    /**
     * Inicializar componente de sugerencias IA
     */
    initializeAISuggestions() {
        try {
            // Crear instancia del componente
            this.aiSuggestions = new AISuggestionsComponent(this);
            
            // Montar el componente
            this.aiSuggestions.mount('messageForm');
            
            // Escuchar eventos de sugerencias seleccionadas
            this.eventBus.on('llm:suggestion:selected', (data) => {
                // El texto ya se pone automáticamente en el campo
                console.log('[Dashboard v2] Sugerencia seleccionada:', data.suggestion.text.substring(0, 50) + '...');
            });
            
            console.log('[Dashboard v2] Componente IA inicializado');
            
        } catch (error) {
            console.error('[Dashboard v2] Error inicializando IA:', error);
            // No es crítico, el dashboard puede funcionar sin IA
        }
    }
}