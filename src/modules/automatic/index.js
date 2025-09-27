/**
 * Automatic Mode Module
 * Módulo de generación automática de jingles con entrada de voz
 */

export default class AutomaticModeModule {
    constructor() {
        this.name = 'automatic-mode';
        this.container = null;
        this.eventBus = window.eventBus;
        this.apiClient = window.apiClient;
        
        // Estado del módulo
        this.state = {
            isRecording: false,
            isProcessing: false,
            currentAudio: null,
            selectedVoice: null,
            selectedMusic: null,  // Música seleccionada
            voices: [],
            musicList: [],  // Lista de música disponible
            audioBlob: null,
            generatedAudio: null,
            mediaRecorder: null,
            recordingTimer: null,
            recordingSeconds: 0,
            recognition: null,  // Web Speech API
            transcribedText: '', // Texto transcrito
            advancedMode: false  // Modo avanzado activo
        };
        
        // Referencias DOM
        this.elements = {};
        
        // Audio context para visualización
        this.audioContext = null;
        this.analyser = null;
        this.playerAnalyser = null;
        this.playerSource = null;
        this.animationId = null;
        this.isVisualizerActive = false;
        
        // Exponer para handlers
        window.automaticMode = this;
    }
    
    getName() {
        return this.name;
    }
    
    /**
     * Cargar estilos del módulo
     */
    loadStyles() {
        if (!document.getElementById('automatic-mode-styles')) {
            const link = document.createElement('link');
            link.id = 'automatic-mode-styles';
            link.rel = 'stylesheet';
            link.href = '/src/modules/automatic/styles/automatic.css';
            document.head.appendChild(link);
            console.log('Automatic mode styles loaded');
        }
    }
    
    /**
     * Cargar módulo
     */
    async load(container) {
        this.container = container;
        this.loadStyles();
        
        try {
            // 1. PRIMERO cargar template
            await this.loadTemplate();
            
            // 2. ESPERAR un tick para que el DOM se actualice completamente
            await new Promise(resolve => setTimeout(resolve, 10));
            
            // 3. LUEGO cachear elementos y verificar que existen
            this.cacheElements();
            
            // 4. Verificar elementos críticos antes de continuar
            if (!this.elements.recordButton || !this.elements.voicesList) {
                throw new Error('Elementos críticos del DOM no encontrados');
            }
            
            // 5. Continuar con la carga normal
            await this.loadVoices();
            await this.loadMusicList();
            this.setupEventListeners();
            
            console.log('Módulo Automático cargado exitosamente');
        } catch (error) {
            console.error('Error cargando módulo automático:', error);
            this.container.innerHTML = `
                <div class="error-message" style="text-align: center; padding: 2rem; color: #ef4444;">
                    Error cargando el módulo: ${error.message}
                </div>
            `;
        }
    }
    
    /**
     * Cargar template HTML
     */
    async loadTemplate() {
        try {
            const response = await fetch('/src/modules/automatic/template.html');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const html = await response.text();
            this.container.innerHTML = html;
            console.log('Automatic template loaded successfully');
        } catch (error) {
            console.error('Error loading automatic template:', error);
            this.container.innerHTML = '<div class="error-message">Error cargando el módulo automático. Por favor recarga la página.</div>';
        }
    }
    
    /**
     * Cachear elementos DOM
     */
    cacheElements() {
        this.elements = {
            recordButton: document.getElementById('record-button'),
            recordIcon: document.getElementById('record-icon'),
            recordText: document.getElementById('record-text'),
            timer: document.getElementById('recording-timer'),
            voicesList: document.getElementById('voices-list'),
            voicesSection: document.getElementById('voices-section'),
            statusMessage: document.getElementById('status-message'),
            audioPlayer: document.getElementById('generated-audio'),
            playerSection: document.getElementById('player-section'),
            sendToRadioBtn: document.getElementById('send-to-radio-btn'),
            visualizer: document.getElementById('audio-visualizer'),
            playerVisualizer: document.getElementById('player-visualizer'),
            playPauseBtn: document.getElementById('play-pause-btn'),
            playIcon: document.querySelector('.play-icon'),
            pauseIcon: document.querySelector('.pause-icon'),
            progressBar: document.querySelector('.progress-bar'),
            progressFill: document.getElementById('progress-fill'),
            currentTimeEl: document.getElementById('current-time'),
            durationEl: document.getElementById('duration'),
            advancedToggle: document.getElementById('advanced-toggle'),
            advancedOptions: document.getElementById('advanced-options'),
            musicSelect: document.getElementById('music-select'),
            durationSelect: document.getElementById('duration-select')
        };
        
        // Verificar elementos críticos y loguear los que faltan
        const missingElements = [];
        for (const [key, element] of Object.entries(this.elements)) {
            if (!element) {
                missingElements.push(key);
                console.warn(`Elemento no encontrado: ${key}`);
            }
        }
        
        if (missingElements.length > 0) {
            console.warn('Elementos DOM faltantes:', missingElements);
        }
    }
    
    /**
     * Cargar voces disponibles
     */
    async loadVoices() {
        try {
            const response = await fetch('/api/generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list_voices' })
            });
            
            const data = await response.json();
            if (data.success && data.voices) {
                // Ordenar voces y filtrar activas
                // Mantener tanto el key (juan_carlos) como el id real (G4IAP30yc6c1gK0csDfu)
                this.state.voices = Object.entries(data.voices)
                    .map(([key, voice]) => ({ 
                        key: key,  // juan_carlos, veronica, etc.
                        id: voice.id,  // ID real de ElevenLabs
                        ...voice 
                    }))
                    .filter(voice => voice.active !== false)  // Solo voces activas
                    .sort((a, b) => (a.order || 999) - (b.order || 999));
                
                this.renderVoices();
                this.populateVoiceSelect();
            }
        } catch (error) {
            console.error('Error cargando voces:', error);
        }
    }
    
    /**
     * Cargar lista de música disponible
     */
    async loadMusicList() {
        try {
            const response = await fetch('/api/music-service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list' })
            });
            
            const data = await response.json();
            if (data.success && data.music) {
                this.state.musicList = data.music;
                this.populateMusicSelect();
            }
        } catch (error) {
            console.error('Error cargando música:', error);
        }
    }
    
    /**
     * Popular select de voces manual - DEPRECADO
     */
    populateVoiceSelect() {
        // Función mantenida por compatibilidad pero ya no se usa
        // El selector de voz manual fue removido del template
        return;
    }
    
    /**
     * Popular select de música
     */
    populateMusicSelect() {
        if (!this.elements.musicSelect) return;
        
        this.elements.musicSelect.innerHTML = `
            <option value="">Por defecto (${this.getDefaultMusicName()})</option>
            <option value="none">Sin música</option>
            ${this.state.musicList.map(music => `
                <option value="${music.file}">${music.name || music.file.replace('.mp3', '')}</option>
            `).join('')}
        `;
    }
    
    /**
     * Obtener nombre de música por defecto
     */
    getDefaultMusicName() {
        // Intentar obtener de la configuración
        return 'Uplift.mp3';
    }
    
    /**
     * Renderizar lista de voces
     */
    renderVoices() {
        if (!this.elements.voicesList) {
            console.error('voicesList element not found');
            return;
        }
        
        this.elements.voicesList.innerHTML = this.state.voices.map(voice => `
            <div class="voice-card" 
                 data-voice="${voice.id}" 
                 data-voice-key="${voice.key || voice.id}" 
                 role="listitem"
                 tabindex="0"
                 aria-label="Voz ${voice.label}">
                <div class="voice-icon" aria-hidden="true">
                    ${voice.gender === 'M' || voice.gender === 'male' ? '👨' : '👩'}
                </div>
                <div class="voice-name">${voice.label}</div>
            </div>
        `).join('');
        
        // Agregar event listeners a las tarjetas de voz
        this.attachVoiceCardListeners();
    }
    
    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Verificar que los elementos existen antes de agregar listeners
        if (this.elements.recordButton) {
            // Usar tanto click como touch para mejor compatibilidad
            this.elements.recordButton.addEventListener('click', () => this.toggleRecording());
            this.elements.recordButton.addEventListener('touchend', (e) => {
                e.preventDefault(); // Prevenir delay de 300ms en móviles
                this.toggleRecording();
            }, { passive: false });
        } else {
            console.error('Record button not found');
        }
        
        if (this.elements.sendToRadioBtn) {
            this.elements.sendToRadioBtn.addEventListener('click', () => this.sendToRadio());
        }
        
        // Player controls
        if (this.elements.playPauseBtn) {
            this.elements.playPauseBtn.addEventListener('click', () => this.togglePlayPause());
        }
        
        // Canvas del visualizador - clickeable para play/pause
        if (this.elements.playerVisualizer) {
            this.elements.playerVisualizer.addEventListener('click', () => this.togglePlayPause());
            // También para touch en móviles
            this.elements.playerVisualizer.addEventListener('touchend', (e) => {
                e.preventDefault();
                this.togglePlayPause();
            }, { passive: false });
        }
        
        if (this.elements.progressBar) {
            this.elements.progressBar.addEventListener('click', (e) => this.seekAudio(e));
        }
        
        // Audio events
        if (this.elements.audioPlayer) {
            this.setupAudioEvents();
        }
        
        // Advanced options toggle
        if (this.elements.advancedToggle) {
            this.setupAdvancedOptions();
        }
        
        // Detectar orientación para ajustes
        window.addEventListener('orientationchange', () => this.handleOrientationChange());
        
        // Prevenir zoom en double tap
        this.preventDoubleTapZoom();
        
        // Solicitar permisos de micrófono al cargar
        this.checkMicrophonePermission();
    }
    
    /**
     * Verificar permisos de micrófono
     */
    async checkMicrophonePermission() {
        // Verificar que estamos en contexto seguro (HTTPS)
        if (!window.isSecureContext) {
            console.warn('Se requiere HTTPS para acceder al micrófono. Contexto actual no es seguro.');
            this.showStatus('Se requiere HTTPS para usar el micrófono. Usa https:// en la URL.', 'error');
            return;
        }
        
        // Verificar que mediaDevices está disponible
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            console.warn('getUserMedia no disponible en este navegador');
            this.showStatus('Tu navegador no soporta acceso al micrófono', 'error');
            return;
        }
        
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach(track => track.stop());
            console.log('Permisos de micrófono verificados correctamente');
        } catch (error) {
            console.error('Error con permisos de micrófono:', error);
            if (error.name === 'NotAllowedError') {
                this.showStatus('Por favor permite el acceso al micrófono en tu navegador', 'error');
            } else if (error.name === 'NotFoundError') {
                this.showStatus('No se encontró ningún micrófono disponible', 'error');
            } else {
                this.showStatus('Error al acceder al micrófono: ' + error.message, 'error');
            }
        }
    }
    
    /**
     * Toggle grabación
     */
    async toggleRecording() {
        if (this.state.isRecording) {
            this.stopRecording();
        } else {
            await this.startRecording();
        }
    }
    
    /**
     * Iniciar grabación con Web Speech API
     */
    async startRecording() {
        try {
            // Vibración táctil en móviles
            if ('vibrate' in navigator) {
                navigator.vibrate(50);
            }
            
            // Resetear estado
            this.resetState();
            this.state.transcribedText = '';
            
            // Verificar soporte de Web Speech API
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                this.showStatus('Tu navegador no soporta reconocimiento de voz. Usa Chrome o Edge.', 'error');
                return;
            }
            
            // Crear instancia de reconocimiento
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.state.recognition = new SpeechRecognition();
            
            // Configurar reconocimiento
            this.state.recognition.lang = 'es-CL'; // Español de Chile
            this.state.recognition.continuous = true; // Continuar escuchando
            this.state.recognition.interimResults = true; // Resultados parciales
            this.state.recognition.maxAlternatives = 1;
            
            let finalTranscript = '';
            let interimTranscript = '';
            
            // Eventos de reconocimiento
            this.state.recognition.onresult = (event) => {
                interimTranscript = '';
                
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript + ' ';
                    } else {
                        interimTranscript = transcript;
                    }
                }
                
                // Mostrar texto en tiempo real (opcional)
                const currentText = finalTranscript + interimTranscript;
                this.state.transcribedText = currentText.trim();
                
                // Actualizar UI con texto parcial
                if (this.elements.timer) {
                    this.elements.timer.textContent = `${this.state.recordingSeconds}s - "${currentText.slice(0, 50)}${currentText.length > 50 ? '...' : ''}"`;
                }
            };
            
            this.state.recognition.onerror = (event) => {
                console.error('Error en reconocimiento:', event.error);
                if (event.error === 'no-speech') {
                    this.showStatus('No se detectó voz. Intenta de nuevo.', 'error');
                } else if (event.error === 'not-allowed') {
                    this.showStatus('Permite el acceso al micrófono para continuar.', 'error');
                } else {
                    this.showStatus('Error en reconocimiento de voz: ' + event.error, 'error');
                }
                this.stopRecording();
            };
            
            this.state.recognition.onend = () => {
                // Si terminó por tiempo, procesar
                if (this.state.isRecording) {
                    this.stopRecording();
                }
            };
            
            // Iniciar reconocimiento
            this.state.recognition.start();
            this.state.isRecording = true;
            
            // Actualizar UI
            this.elements.recordButton.classList.add('recording');
            this.elements.recordButton.setAttribute('aria-pressed', 'true');
            this.elements.recordIcon.textContent = '⏹';
            this.elements.recordText.textContent = 'Detener';
            
            // Iniciar timer (máximo 20 segundos)
            this.startTimer();
            
            // Mostrar indicador de que está escuchando
            this.showStatus('Escuchando... Habla ahora', 'info');
            
        } catch (error) {
            console.error('Error iniciando grabación:', error);
            this.showStatus('Error al iniciar reconocimiento de voz', 'error');
        }
    }
    
    /**
     * Detener grabación
     */
    stopRecording() {
        if (this.state.recognition && this.state.isRecording) {
            this.state.recognition.stop();
            this.state.isRecording = false;
            
            // Detener timer
            if (this.state.recordingTimer) {
                clearInterval(this.state.recordingTimer);
                this.state.recordingTimer = null;
            }
            
            // Vibración táctil al detener
            if ('vibrate' in navigator) {
                navigator.vibrate([50, 30, 50]);
            }
            
            // Actualizar UI
            this.elements.recordButton.classList.remove('recording');
            this.elements.recordButton.setAttribute('aria-pressed', 'false');
            this.elements.recordIcon.textContent = '🎤';
            this.elements.recordText.textContent = 'Grabar';
            this.elements.timer.textContent = '';
            
            // Procesar si hay texto
            if (this.state.transcribedText && this.state.transcribedText.trim()) {
                this.processRecording();
            } else {
                this.showStatus('No se detectó ningún mensaje. Intenta de nuevo.', 'error');
            }
        }
    }
    
    /**
     * Timer de grabación
     */
    startTimer() {
        this.state.recordingSeconds = 0;
        this.elements.timer.textContent = '0s';
        
        this.state.recordingTimer = setInterval(() => {
            this.state.recordingSeconds++;
            this.elements.timer.textContent = `${this.state.recordingSeconds}s`;
            
            // Detener automáticamente a los 20 segundos
            if (this.state.recordingSeconds >= 20) {
                this.stopRecording();
            }
        }, 1000);
    }
    
    /**
     * Procesar grabación
     */
    processRecording() {
        // Mostrar sección de voces
        this.elements.voicesSection.style.display = 'block';
        this.showStatus('Selecciona una voz para continuar', 'info');
        
        // Scroll a voces
        this.elements.voicesSection.scrollIntoView({ behavior: 'smooth' });
    }
    
    /**
     * Seleccionar voz
     */
    async selectVoice(voiceKey, realVoiceId) {
        if (this.state.isProcessing) return;
        
        // Si ya hay un audio generado y se selecciona una voz diferente, regenerar
        const isRegenerating = this.state.generatedAudio && this.state.selectedVoice !== voiceKey;
        
        // Usar el voiceKey para el backend (juan_carlos, etc.)
        this.state.selectedVoice = voiceKey;
        this.state.selectedVoiceId = realVoiceId; // ID real de ElevenLabs
        
        // Marcar voz seleccionada
        document.querySelectorAll('.voice-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.querySelector(`[data-voice-key="${voiceKey}"]`).classList.add('selected');
        
        // Si es una regeneración, mostrar mensaje apropiado
        if (isRegenerating) {
            this.showStatus('Regenerando con nueva voz... ✨', 'processing');
        }
        
        // Procesar audio
        await this.processAudio();
    }
    
    /**
     * Procesar texto con servicios backend
     */
    async processAudio() {
        if (!this.state.transcribedText || !this.state.selectedVoice) return;
        
        this.state.isProcessing = true;
        const statusMsg = (this.state.selectedMusic === 'none') 
            ? 'Generando mensaje sin música... 🎙️' 
            : 'Haciendo la magia... ✨';
        this.showStatus(statusMsg, 'processing');
        
        // Auto-ocultar el mensaje después de 3 segundos
        setTimeout(() => {
            if (this.state.isProcessing) {
                this.hideStatus();
            }
        }, 3000);
        
        try {
            // Enviar texto directamente (Web Speech API ya lo transcribió)
            // Preparar datos para enviar
            const requestData = {
                text: this.state.transcribedText,  // Enviar texto en lugar de audio
                voice_id: this.state.selectedVoice  // Enviamos el key (juan_carlos, etc.)
            };
            
            // Manejar música: siempre enviar el campo para que el backend sepa qué hacer
            if (this.state.selectedMusic) {
                // Enviar el valor tal cual (incluyendo "none" si es sin música)
                requestData.music_file = this.state.selectedMusic;
            }
            // Si no hay selección (valor vacío ""), no enviar nada y usará default
            
            // Agregar duración seleccionada
            if (this.elements.durationSelect) {
                requestData.target_duration = parseInt(this.elements.durationSelect.value) || 20;
            }
            
            console.log('[Automatic] Enviando request con música:', requestData.music_file || 'DEFAULT', 'y duración:', requestData.target_duration || 20);
            
            // Temporalmente usar v1 mientras se debugea v2
            const response = await fetch('/api/automatic-jingle-service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.state.generatedAudio = data;
                this.playGeneratedAudio(data.audio_url);
                // No mostrar mensaje, ir directo al audio
            } else {
                // Manejo de errores específicos
                if (data.error_type === 'audio_quality') {
                    this.showStatus(data.error, 'error');
                    this.resetForNewRecording();
                } else {
                    this.showStatus('Error generando jingle: ' + data.error, 'error');
                }
            }
            
        } catch (error) {
            console.error('Error procesando audio:', error);
            this.showStatus('Error al procesar el audio', 'error');
        } finally {
            this.state.isProcessing = false;
        }
    }
    
    /**
     * Reproducir audio generado
     */
    playGeneratedAudio(audioUrl) {
        // Ocultar cualquier mensaje de estado
        this.hideStatus();
        
        // Mostrar reproductor
        this.elements.playerSection.style.display = 'block';
        
        // Configurar audio
        this.elements.audioPlayer.src = audioUrl;
        
        // Inicializar visualizador
        this.setupPlayerVisualizer();
        
        // Auto-play
        setTimeout(() => {
            this.elements.audioPlayer.play().catch(e => {
                console.error('Error reproduciendo audio:', e);
            });
        }, 500);
        
        // Scroll al reproductor
        this.elements.playerSection.scrollIntoView({ behavior: 'smooth' });
    }
    
    /**
     * Setup audio events
     */
    setupAudioEvents() {
        const audio = this.elements.audioPlayer;
        
        // Update time display
        audio.addEventListener('loadedmetadata', () => {
            this.elements.durationEl.textContent = this.formatTime(audio.duration);
        });
        
        audio.addEventListener('timeupdate', () => {
            const progress = (audio.currentTime / audio.duration) * 100;
            this.elements.progressFill.style.width = progress + '%';
            this.elements.currentTimeEl.textContent = this.formatTime(audio.currentTime);
        });
        
        audio.addEventListener('play', () => {
            this.elements.playPauseBtn.classList.add('playing');
            this.elements.playIcon.style.display = 'none';
            this.elements.pauseIcon.style.display = 'flex';
            this.startVisualizer();
        });
        
        audio.addEventListener('pause', () => {
            this.elements.playPauseBtn.classList.remove('playing');
            this.elements.playIcon.style.display = 'flex';
            this.elements.pauseIcon.style.display = 'none';
            this.stopVisualizer();
        });
        
        audio.addEventListener('ended', () => {
            this.elements.playPauseBtn.classList.remove('playing');
            this.elements.playIcon.style.display = 'flex';
            this.elements.pauseIcon.style.display = 'none';
            this.elements.progressFill.style.width = '0%';
            this.stopVisualizer();
        });
    }
    
    /**
     * Toggle play/pause
     */
    togglePlayPause() {
        const audio = this.elements.audioPlayer;
        if (audio.paused) {
            audio.play();
        } else {
            audio.pause();
        }
    }
    
    /**
     * Seek audio
     */
    seekAudio(e) {
        const audio = this.elements.audioPlayer;
        const rect = this.elements.progressBar.getBoundingClientRect();
        const percent = (e.clientX - rect.left) / rect.width;
        audio.currentTime = percent * audio.duration;
    }
    
    /**
     * Format time
     */
    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    /**
     * Setup player visualizer
     */
    setupPlayerVisualizer() {
        if (!this.audioContext) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        
        // Create analyser for player
        this.playerAnalyser = this.audioContext.createAnalyser();
        this.playerAnalyser.fftSize = 128; // Menos barras para mejor performance en móvil
        
        // Connect audio element to analyser
        if (!this.playerSource) {
            this.playerSource = this.audioContext.createMediaElementSource(this.elements.audioPlayer);
            this.playerSource.connect(this.playerAnalyser);
            this.playerAnalyser.connect(this.audioContext.destination);
        }
    }
    
    /**
     * Start visualizer animation
     */
    startVisualizer() {
        if (this.isVisualizerActive) return;
        this.isVisualizerActive = true;
        
        const canvas = this.elements.playerVisualizer;
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        
        const bufferLength = this.playerAnalyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        
        const draw = () => {
            if (!this.isVisualizerActive) return;
            
            this.animationId = requestAnimationFrame(draw);
            
            this.playerAnalyser.getByteFrequencyData(dataArray);
            
            // Clear canvas completamente
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Dibujar waveform simple en blanco
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            
            const sliceWidth = canvas.width / bufferLength;
            let x = 0;
            
            for (let i = 0; i < bufferLength; i++) {
                const v = dataArray[i] / 255;
                const y = canvas.height / 2 + (v - 0.5) * canvas.height * 0.8;
                
                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
                
                x += sliceWidth;
            }
            
            ctx.stroke();
        };
        
        draw();
    }
    
    /**
     * Stop visualizer
     */
    stopVisualizer() {
        this.isVisualizerActive = false;
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
    }
    
    
    /**
     * Enviar a radio
     */
    async sendToRadio() {
        if (!this.state.generatedAudio) return;
        
        this.showStatus('Enviando a la radio...', 'processing');
        
        try {
            const response = await fetch('/api/generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send_to_radio',
                    filename: this.state.generatedAudio.filename
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Animación de éxito
                this.showSuccessAnimation();
                
                // Toast de confirmación
                setTimeout(() => {
                    this.showStatus('✅ Tu aviso se envió a la radio', 'success');
                    
                    // Resetear después de 3 segundos
                    setTimeout(() => {
                        this.resetForNewRecording();
                    }, 3000);
                }, 1000);
            } else {
                this.showStatus('Error enviando a radio', 'error');
            }
            
        } catch (error) {
            console.error('Error enviando a radio:', error);
            this.showStatus('Error al enviar a la radio', 'error');
        }
    }
    
    /**
     * Animación de éxito
     */
    showSuccessAnimation() {
        const animation = document.createElement('div');
        animation.className = 'success-animation';
        animation.innerHTML = '📻';
        document.body.appendChild(animation);
        
        setTimeout(() => {
            animation.remove();
        }, 2000);
    }
    
    /**
     * Configurar visualizador de audio
     */
    setupVisualizer(stream) {
        if (!this.audioContext) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        
        this.analyser = this.audioContext.createAnalyser();
        const source = this.audioContext.createMediaStreamSource(stream);
        source.connect(this.analyser);
        
        this.analyser.fftSize = 256;
        const bufferLength = this.analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        
        const canvas = this.elements.visualizer;
        const canvasCtx = canvas.getContext('2d');
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        
        const draw = () => {
            if (!this.state.isRecording) return;
            
            requestAnimationFrame(draw);
            
            this.analyser.getByteFrequencyData(dataArray);
            
            canvasCtx.fillStyle = 'rgba(30, 31, 34, 0.5)';
            canvasCtx.fillRect(0, 0, canvas.width, canvas.height);
            
            const barWidth = (canvas.width / bufferLength) * 2.5;
            let barHeight;
            let x = 0;
            
            for (let i = 0; i < bufferLength; i++) {
                barHeight = (dataArray[i] / 255) * canvas.height;
                
                canvasCtx.fillStyle = `hsl(${i * 2}, 70%, 50%)`;
                canvasCtx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
                
                x += barWidth + 1;
            }
        };
        
        draw();
    }
    
    /**
     * Mostrar estado
     */
    showStatus(message, type = 'info') {
        if (!this.elements.statusMessage) {
            console.warn('Status message element not found, logging to console:', message);
            return;
        }
        
        this.elements.statusMessage.textContent = message;
        this.elements.statusMessage.className = `status-message ${type}`;
        this.elements.statusMessage.style.display = 'block';
        
        if (type !== 'processing') {
            setTimeout(() => {
                if (this.elements.statusMessage) {
                    this.elements.statusMessage.style.display = 'none';
                }
            }, 5000);
        }
    }
    
    /**
     * Ocultar mensaje de estado
     */
    hideStatus() {
        if (this.elements.statusMessage) {
            this.elements.statusMessage.style.display = 'none';
        }
    }
    
    /**
     * Resetear estado
     */
    resetState() {
        this.state.audioBlob = null;
        this.state.selectedVoice = null;
        this.state.generatedAudio = null;
        this.state.transcribedText = '';  // Limpiar texto transcrito
        this.elements.voicesSection.style.display = 'none';
        this.elements.playerSection.style.display = 'none';
        this.elements.statusMessage.style.display = 'none';
        
        // Limpiar selección de voces
        document.querySelectorAll('.voice-card').forEach(card => {
            card.classList.remove('selected');
        });
    }
    
    /**
     * Resetear para nueva grabación
     */
    resetForNewRecording() {
        this.resetState();
        this.elements.recordButton.classList.remove('recording');
        this.elements.recordIcon.textContent = '🎤';
        this.elements.recordText.textContent = 'Grabar';
        this.elements.timer.textContent = '';
    }
    
    /**
     * Setup advanced options
     */
    setupAdvancedOptions() {
        const toggle = this.elements.advancedToggle;
        const options = this.elements.advancedOptions;
        
        if (!toggle || !options) return;
        
        toggle.addEventListener('click', () => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !isExpanded);
            options.style.display = isExpanded ? 'none' : 'block';
            this.state.advancedMode = !isExpanded;
        });
        
        // Voice manual select - REMOVIDO
        // El selector manual de voz fue eliminado del template
        
        // Music select
        if (this.elements.musicSelect) {
            this.elements.musicSelect.addEventListener('change', (e) => {
                this.state.selectedMusic = e.target.value;
                console.log('Música seleccionada:', this.state.selectedMusic);
                console.log('Valor exacto:', e.target.value === 'none' ? 'SIN MÚSICA' : e.target.value || 'DEFAULT');
            });
        }
    }
    
    
    /**
     * Attach event listeners a voice cards
     */
    attachVoiceCardListeners() {
        const cards = this.elements.voicesList.querySelectorAll('.voice-card');
        cards.forEach(card => {
            const voiceKey = card.dataset.voiceKey;
            const voiceId = card.dataset.voice;
            
            // Flag para prevenir doble disparo
            let touchHandled = false;
            let touchStartTime = 0;
            let touchStartX = 0;
            let touchStartY = 0;
            
            // Touch start - registrar tiempo y posición
            card.addEventListener('touchstart', (e) => {
                touchStartTime = Date.now();
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                touchHandled = false;
            }, { passive: true });
            
            // Touch end - manejar solo si fue un tap sin movimiento significativo
            card.addEventListener('touchend', (e) => {
                const touchDuration = Date.now() - touchStartTime;
                const touchEndX = e.changedTouches[0].clientX;
                const touchEndY = e.changedTouches[0].clientY;
                const touchDistance = Math.sqrt(
                    Math.pow(touchEndX - touchStartX, 2) + 
                    Math.pow(touchEndY - touchStartY, 2)
                );
                
                // Solo procesar si fue un tap rápido (menos de 500ms) 
                // y sin mucho movimiento (menos de 10px)
                if (touchDuration < 500 && touchDistance < 10 && !touchHandled) {
                    e.preventDefault();
                    e.stopPropagation();
                    touchHandled = true;
                    this.selectVoice(voiceKey, voiceId);
                }
            }, { passive: false });
            
            // Click event - solo para desktop
            card.addEventListener('click', (e) => {
                // Ignorar clicks si fue manejado por touch
                if (!touchHandled) {
                    this.selectVoice(voiceKey, voiceId);
                }
                // Reset flag después de un tiempo
                setTimeout(() => { touchHandled = false; }, 100);
            });
            
            // Keyboard support
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.selectVoice(voiceKey, voiceId);
                }
            });
        });
    }
    
    /**
     * Manejar cambio de orientación
     */
    handleOrientationChange() {
        // Ajustar UI según orientación
        setTimeout(() => {
            if (window.orientation === 90 || window.orientation === -90) {
                // Landscape
                document.body.classList.add('landscape-mode');
            } else {
                // Portrait
                document.body.classList.remove('landscape-mode');
            }
        }, 100);
    }
    
    /**
     * Prevenir zoom en double tap
     */
    preventDoubleTapZoom() {
        let lastTouchEnd = 0;
        document.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, { passive: false });
    }
    
    /**
     * Descargar módulo
     */
    unload() {
        // Limpiar timers
        if (this.state.recordingTimer) {
            clearInterval(this.state.recordingTimer);
        }
        
        // Detener grabación si está activa
        if (this.state.isRecording) {
            this.stopRecording();
        }
        
        // Limpiar audio context
        if (this.audioContext) {
            this.audioContext.close();
        }
        
        // Stop visualizer
        this.stopVisualizer();
        
        // Limpiar referencias
        this.container.innerHTML = '';
        console.log('Módulo Automático descargado');
    }
}