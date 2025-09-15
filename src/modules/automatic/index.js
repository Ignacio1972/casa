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
            voices: [],
            audioBlob: null,
            generatedAudio: null,
            mediaRecorder: null,
            recordingTimer: null,
            recordingSeconds: 0,
            recognition: null,  // Web Speech API
            transcribedText: '' // Texto transcrito
        };
        
        // Referencias DOM
        this.elements = {};
        
        // Audio context para visualización
        this.audioContext = null;
        this.analyser = null;
        
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
            regenerateBtn: document.getElementById('regenerate-btn'),
            sendToRadioBtn: document.getElementById('send-to-radio-btn'),
            visualizer: document.getElementById('audio-visualizer')
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
            }
        } catch (error) {
            console.error('Error cargando voces:', error);
        }
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
            <div class="voice-card" data-voice="${voice.id}" data-voice-key="${voice.key || voice.id}" onclick="automaticMode.selectVoice('${voice.key || voice.id}', '${voice.id}')">
                <div class="voice-icon">
                    ${voice.gender === 'M' || voice.gender === 'male' ? '👨' : '👩'}
                </div>
                <div class="voice-name">${voice.label}</div>
            </div>
        `).join('');
    }
    
    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Verificar que los elementos existen antes de agregar listeners
        if (this.elements.recordButton) {
            this.elements.recordButton.addEventListener('click', () => this.toggleRecording());
        } else {
            console.error('Record button not found');
        }
        
        if (this.elements.regenerateBtn) {
            this.elements.regenerateBtn.addEventListener('click', () => this.regenerate());
        }
        
        if (this.elements.sendToRadioBtn) {
            this.elements.sendToRadioBtn.addEventListener('click', () => this.sendToRadio());
        }
        
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
            this.elements.recordIcon.textContent = '⏹';
            this.elements.recordText.textContent = 'Detener';
            
            // Iniciar timer (máximo 10 segundos)
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
            
            // Actualizar UI
            this.elements.recordButton.classList.remove('recording');
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
            
            // Detener automáticamente a los 10 segundos
            if (this.state.recordingSeconds >= 10) {
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
        
        // Usar el voiceKey para el backend (juan_carlos, etc.)
        this.state.selectedVoice = voiceKey;
        this.state.selectedVoiceId = realVoiceId; // ID real de ElevenLabs
        
        // Marcar voz seleccionada
        document.querySelectorAll('.voice-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.querySelector(`[data-voice-key="${voiceKey}"]`).classList.add('selected');
        
        // Procesar audio
        await this.processAudio();
    }
    
    /**
     * Procesar texto con servicios backend
     */
    async processAudio() {
        if (!this.state.transcribedText || !this.state.selectedVoice) return;
        
        this.state.isProcessing = true;
        this.showStatus('Haciendo la magia... ✨', 'processing');
        
        try {
            // Enviar texto directamente (Web Speech API ya lo transcribió)
            const response = await fetch('/api/automatic-jingle-service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    text: this.state.transcribedText,  // Enviar texto en lugar de audio
                    voice_id: this.state.selectedVoice  // Enviamos el key (juan_carlos, etc.)
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.state.generatedAudio = data;
                this.playGeneratedAudio(data.audio_url);
                this.showStatus('¡Jingle generado exitosamente!', 'success');
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
        // Mostrar reproductor
        this.elements.playerSection.style.display = 'block';
        
        // Configurar y reproducir
        this.elements.audioPlayer.src = audioUrl;
        this.elements.audioPlayer.play().catch(e => {
            console.error('Error reproduciendo audio:', e);
        });
        
        // Scroll al reproductor
        this.elements.playerSection.scrollIntoView({ behavior: 'smooth' });
    }
    
    /**
     * Regenerar jingle
     */
    regenerate() {
        // Resetear para nueva grabación
        this.resetForNewRecording();
        this.showStatus('Graba un nuevo mensaje', 'info');
        
        // Scroll al botón de grabación
        this.elements.recordButton.scrollIntoView({ behavior: 'smooth' });
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
        
        // Limpiar referencias
        this.container.innerHTML = '';
        console.log('Módulo Automático descargado');
    }
}