/**
 * MBI-v3 API Playground - Main Controller
 * @version 1.0.0
 * @date 2024-11-29
 */

class PlaygroundApp {
    constructor() {
        this.currentSection = 'tts-tester';
        this.voices = {};
        this.logs = [];
        this.quotaInfo = null;
        this.audioContext = null;
        
        this.init();
    }
    
    async init() {
        console.log('🧪 Playground initializing...');
        
        // Cargar datos iniciales
        await this.loadVoices();
        await this.updateQuota();
        
        // Inicializar componentes
        this.initNavigation();
        this.initTTSTester();
        this.initVoiceExplorer();
        this.initLogViewer();
        this.initThemeToggle();
        this.initTools();
        
        // Iniciar monitoreo
        this.startMonitoring();
        
        console.log('✅ Playground ready!');
    }
    
    async loadVoices() {
        try {
            const response = await fetch('/api/generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list_voices' })
            });
            
            if (!response.ok) throw new Error('Failed to load voices');
            
            const data = await response.json();
            this.voices = data.voices || {};
            
            // Poblar selector de voces
            const voiceSelect = document.getElementById('voice-select');
            if (voiceSelect) {
                voiceSelect.innerHTML = '';
                
                Object.entries(this.voices).forEach(([id, voice]) => {
                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = `${voice.label} (${voice.gender})`;
                    voiceSelect.appendChild(option);
                });
                
                // Seleccionar Fernanda por defecto
                voiceSelect.value = 'fernanda';
            }
            
        } catch (error) {
            console.error('Error loading voices:', error);
            this.addLog('Error cargando voces: ' + error.message, 'error');
        }
    }
    
    async updateQuota() {
        try {
            const response = await fetch('/playground/api/quota-tracker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_quota' })
            });
            const data = await response.json();
            
            if (data.success) {
                this.quotaInfo = data.quota;
                
                const quotaDisplay = document.getElementById('quota-display');
                if (quotaDisplay) {
                    const percentage = this.quotaInfo.percentage;
                    
                    quotaDisplay.innerHTML = `
                        📊 ${this.quotaInfo.used.toLocaleString()} / ${this.quotaInfo.limit.toLocaleString()} chars (${percentage}%)
                    `;
                    
                    // Cambiar color según uso
                    if (percentage > 90) {
                        quotaDisplay.style.background = 'rgba(239, 68, 68, 0.2)';
                    } else if (percentage > 70) {
                        quotaDisplay.style.background = 'rgba(245, 158, 11, 0.2)';
                    } else {
                        quotaDisplay.style.background = 'rgba(16, 185, 129, 0.2)';
                    }
                }
            }
            
        } catch (error) {
            console.error('Error updating quota:', error);
        }
    }
    
    initNavigation() {
        const navButtons = document.querySelectorAll('.nav-btn');
        const sections = document.querySelectorAll('.content-section');
        
        navButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetSection = btn.dataset.section;
                
                // Actualizar botones
                navButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Actualizar secciones
                sections.forEach(s => s.classList.remove('active'));
                const target = document.getElementById(targetSection);
                if (target) target.classList.add('active');
                
                this.currentSection = targetSection;
                this.addLog(`Switched to section: ${targetSection}`, 'info');
                
                // Si es la sección de voice-admin, inicializar
                if (targetSection === 'voice-admin' && typeof voiceAdmin !== 'undefined') {
                    voiceAdmin.init();
                }
            });
        });
    }
    
    initTTSTester() {
        // Contador de caracteres
        const textInput = document.getElementById('tts-text');
        const charCount = document.getElementById('char-count');
        
        if (textInput && charCount) {
            textInput.addEventListener('input', () => {
                const length = textInput.value.length;
                charCount.textContent = length;
                
                if (length > 4500) {
                    charCount.style.color = '#ef4444';
                } else if (length > 4000) {
                    charCount.style.color = '#f59e0b';
                } else {
                    charCount.style.color = '#94a3b8';
                }
            });
        }
        
        // Sliders
        this.initSlider('style');
        this.initSlider('stability');
        this.initSlider('similarity');
        
        // Botón generar
        const generateBtn = document.getElementById('generate-btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => this.generateAudio());
        }
    }
    
    initSlider(name) {
        const slider = document.getElementById(`${name}-slider`);
        const value = document.getElementById(`${name}-value`);
        
        if (slider && value) {
            slider.addEventListener('input', () => {
                value.textContent = slider.value;
            });
        }
    }
    
    async generateAudio() {
        const generateBtn = document.getElementById('generate-btn');
        const statusDiv = document.getElementById('generation-status');
        const audioPlayer = document.getElementById('audio-player');
        const audioInfo = document.getElementById('audio-info');
        const apiResponse = document.getElementById('api-response');
        
        // Obtener valores
        const text = document.getElementById('tts-text').value.trim();
        const voice = document.getElementById('voice-select').value;
        const style = document.getElementById('style-slider').value / 100;
        const stability = document.getElementById('stability-slider').value / 100;
        const similarity = document.getElementById('similarity-slider').value / 100;
        const speakerBoost = document.getElementById('speaker-boost').checked;
        
        // Validar
        if (!text) {
            this.showNotification('Por favor ingresa un texto', 'error');
            return;
        }
        
        // Preparar UI
        generateBtn.disabled = true;
        generateBtn.textContent = '⏳ Generando...';
        statusDiv.innerHTML = '<div class="loading">Procesando...</div>';
        audioPlayer.style.display = 'none';
        
        const startTime = Date.now();
        
        try {
            this.addLog(`Starting TTS generation: ${text.substring(0, 50)}...`, 'info');
            
            const response = await fetch('/api/generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_audio',
                    text: text,
                    voice: voice,
                    voice_settings: {
                        style: style,
                        stability: stability,
                        similarity_boost: similarity,
                        use_speaker_boost: speakerBoost
                    },
                    source: 'playground'
                })
            });
            
            const data = await response.json();
            const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
            this.lastGenerationTime = parseFloat(elapsed);
            
            if (data.success) {
                // Mostrar audio
                audioPlayer.src = `/api/temp/${data.filename}`;
                audioPlayer.style.display = 'block';
                audioPlayer.play();
                
                // Mostrar info
                statusDiv.innerHTML = `
                    <div class="success-message">
                        ✅ Audio generado en ${elapsed}s
                    </div>
                `;
                
                audioInfo.innerHTML = `
                    <div class="info-grid">
                        <div><strong>Archivo:</strong> ${data.filename}</div>
                        <div><strong>Duración:</strong> ${data.duration || 'N/A'}</div>
                        <div><strong>Tamaño:</strong> ${data.filesize || 'N/A'}</div>
                        <div><strong>Voz:</strong> ${this.voices[voice]?.label}</div>
                    </div>
                `;
                
                // Mostrar respuesta API
                apiResponse.innerHTML = `
                    <details>
                        <summary>API Response</summary>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </details>
                `;
                
                this.addLog(`TTS generated successfully in ${elapsed}s`, 'success');
                
                // Trackear uso de caracteres
                await this.trackUsage(text.length, voice, true);
                
                // Actualizar quota
                await this.updateQuota();
                
            } else {
                throw new Error(data.error || 'Generation failed');
            }
            
        } catch (error) {
            statusDiv.innerHTML = `
                <div class="error-message">
                    ❌ Error: ${error.message}
                </div>
            `;
            
            this.addLog(`TTS generation failed: ${error.message}`, 'error');
            
        } finally {
            generateBtn.disabled = false;
            generateBtn.textContent = '🎵 Generar Audio';
        }
    }
    
    // ========== VOICE EXPLORER ==========
    initVoiceExplorer() {
        const generateAllBtn = document.getElementById('generate-all-samples');
        if (generateAllBtn) {
            generateAllBtn.addEventListener('click', () => this.generateAllVoiceSamples());
        }
        
        // Renderizar grid de voces cuando se active la sección
        const voiceExplorerBtn = document.querySelector('[data-section="voice-explorer"]');
        if (voiceExplorerBtn) {
            voiceExplorerBtn.addEventListener('click', () => {
                setTimeout(() => this.renderVoicesGrid(), 100);
            });
        }
    }
    
    renderVoicesGrid() {
        const grid = document.getElementById('voices-grid');
        if (!grid) return;
        
        grid.innerHTML = '';
        
        Object.entries(this.voices).forEach(([id, voice]) => {
            const card = document.createElement('div');
            card.className = 'voice-card';
            card.innerHTML = `
                <div class="voice-header">
                    <span class="voice-name">${voice.label}</span>
                    <span class="voice-gender ${voice.gender}">${voice.gender}</span>
                </div>
                <div class="voice-player" id="player-${id}">
                    <div class="voice-status">Click "Generar Todas" para crear muestras</div>
                </div>
                <button class="btn btn-secondary btn-small" onclick="playground.testSingleVoice('${id}')">
                    🔊 Probar Esta Voz
                </button>
            `;
            grid.appendChild(card);
        });
    }
    
    async testSingleVoice(voiceId) {
        const text = document.getElementById('sample-text').value;
        const playerDiv = document.getElementById(`player-${voiceId}`);
        
        playerDiv.innerHTML = '<div class="loading-spinner"></div> Generando...';
        
        try {
            const response = await fetch('/api/generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_audio',
                    text: text,
                    voice: voiceId,
                    voice_settings: {
                        style: 0.5,
                        stability: 0.75,
                        similarity_boost: 0.8,
                        use_speaker_boost: true
                    },
                    source: 'playground-voice-explorer'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                playerDiv.innerHTML = `
                    <audio controls style="width: 100%;">
                        <source src="/api/temp/${data.filename}" type="audio/mpeg">
                    </audio>
                `;
                this.addLog(`Voice sample generated: ${this.voices[voiceId].label}`, 'success');
            } else {
                playerDiv.innerHTML = `<div class="error-message">Error: ${data.error}</div>`;
            }
        } catch (error) {
            playerDiv.innerHTML = `<div class="error-message">Error: ${error.message}</div>`;
        }
    }
    
    async generateAllVoiceSamples() {
        const text = document.getElementById('sample-text').value;
        const btn = document.getElementById('generate-all-samples');
        
        btn.disabled = true;
        btn.textContent = '⏳ Generando muestras...';
        
        for (const [id, voice] of Object.entries(this.voices)) {
            await this.testSingleVoice(id);
            // Esperar un poco entre requests para no saturar
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        btn.disabled = false;
        btn.textContent = '🎵 Generar Todas las Muestras';
        
        this.showNotification('✅ Todas las muestras generadas', 'success');
    }
    
    initLogViewer() {
        const toggleBtn = document.getElementById('toggle-logs');
        const logViewer = document.getElementById('log-viewer');
        const closeBtn = document.getElementById('close-logs');
        const clearBtn = document.getElementById('clear-logs');
        
        if (toggleBtn && logViewer) {
            toggleBtn.addEventListener('click', () => {
                logViewer.classList.toggle('hidden');
            });
        }
        
        if (closeBtn && logViewer) {
            closeBtn.addEventListener('click', () => {
                logViewer.classList.add('hidden');
            });
        }
        
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.logs = [];
                this.renderLogs();
            });
        }
        
        // Cargar logs iniciales
        this.loadRecentLogs();
    }
    
    async loadRecentLogs() {
        try {
            const response = await fetch('/playground/api/logs.php?action=recent');
            const data = await response.json();
            
            if (data.logs) {
                this.logs = data.logs;
                this.renderLogs();
            }
        } catch (error) {
            console.error('Error loading logs:', error);
        }
    }
    
    addLog(message, level = 'info') {
        const log = {
            timestamp: new Date().toISOString(),
            message: message,
            level: level
        };
        
        this.logs.unshift(log);
        if (this.logs.length > 100) this.logs.pop();
        
        this.renderLogs();
        
        // Enviar al servidor
        fetch('/playground/api/logs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'log', ...log })
        }).catch(console.error);
    }
    
    renderLogs() {
        const logContent = document.getElementById('log-content');
        if (!logContent) return;
        
        logContent.innerHTML = this.logs.map(log => {
            const time = new Date(log.timestamp).toLocaleTimeString();
            const levelClass = log.level || 'info';
            
            return `
                <div class="log-entry ${levelClass}">
                    <span class="log-time">${time}</span>
                    <span class="log-message">${log.message}</span>
                </div>
            `;
        }).join('');
    }
    
    initThemeToggle() {
        const toggleBtn = document.getElementById('toggle-dark');
        
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                document.body.classList.toggle('light-theme');
                toggleBtn.textContent = document.body.classList.contains('light-theme') ? '🌞' : '🌙';
            });
        }
    }
    
    
    initTools() {
        // LEGACY SYSTEM REMOVED: Voice Manager buttons no longer needed
        // Users should use "Admin Voces" menu for voice management
        
        // API Config - PRESERVED: This functionality is still needed
        const saveApiConfigBtn = document.getElementById("save-api-config");
        if (saveApiConfigBtn) {
            saveApiConfigBtn.addEventListener("click", () => this.saveApiConfig());
        }
        
        // Cargar configuración de API al iniciar
        this.loadApiConfig();
        
        // LEGACY: loadCustomVoices removed - handled by Admin Voces
    }
    
    // LEGACY FUNCTIONS REMOVED
    // Voice management functionality has been moved to Admin Voces section
    // These functions are no longer needed:
    // - addCustomVoice()
    // - loadCustomVoices()  
    // - deleteCustomVoice()
    // Users should use the "Admin Voces" menu option for all voice management
    async loadApiConfig() {
        try {
            const response = await fetch('/playground/api/voice-manager.php?action=get_config');
            const data = await response.json();
            
            if (data.success && data.config) {
                const checkbox = document.getElementById('elevenlabs-v3-api');
                if (checkbox) {
                    checkbox.checked = data.config.use_v3_api || false;
                }
            }
        } catch (error) {
            console.error('Error loading API config:', error);
        }
    }
    
    async saveApiConfig() {
        const checkbox = document.getElementById('elevenlabs-v3-api');
        const useV3 = checkbox ? checkbox.checked : false;
        
        try {
            const response = await fetch('/playground/api/voice-manager.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_config',
                    config: {
                        use_v3_api: useV3
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('✅ Configuración guardada', 'success');
            } else {
                throw new Error(data.error || 'Error al guardar configuración');
            }
        } catch (error) {
            this.showNotification('Error: ' + error.message, 'error');
        }
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    async trackUsage(characters, voice, success = true) {
        try {
            const response = await fetch('/playground/api/quota-tracker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'track_usage',
                    characters: characters,
                    voice: voice,
                    success: success
                })
            });
            
            const data = await response.json();
            
            if (!this.generationHistory) {
                this.generationHistory = [];
            }
            
            // Agregar al historial local para monitors
            this.generationHistory.unshift({
                text: document.getElementById('tts-text').value,
                voice: voice,
                time: new Date().toLocaleTimeString(),
                success: success,
                duration: this.lastGenerationTime || 0,
                characters: characters
            });
            
            // Mantener solo últimas 50 generaciones
            this.generationHistory = this.generationHistory.slice(0, 50);
            
            // Actualizar performance data
            if (!this.performanceData) {
                this.performanceData = [];
            }
            if (this.lastGenerationTime) {
                this.performanceData.push(this.lastGenerationTime);
                // Mantener solo últimos 20 tiempos
                this.performanceData = this.performanceData.slice(-20);
            }
            
            console.log('Usage tracked:', data);
            
        } catch (error) {
            console.error('Error tracking usage:', error);
        }
    }
    
    startMonitoring() {
        // Inicializar tiempo de sesión
        window.sessionStart = Date.now();
        
        // Actualizar quota cada minuto
        setInterval(() => this.updateQuota(), 60000);
        
        // Ping para mantener sesión activa
        setInterval(() => {
            fetch('/playground/api/ping.php').catch(() => {});
        }, 30000);
    }
}

// CSS para notificaciones
const style = document.createElement('style');
style.textContent = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        transform: translateX(400px);
        transition: transform 0.3s;
        z-index: 2000;
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success {
        background: rgba(16, 185, 129, 0.2);
        border-color: #10b981;
        color: #10b981;
    }
    
    .notification-error {
        background: rgba(239, 68, 68, 0.2);
        border-color: #ef4444;
        color: #ef4444;
    }
    
    .loading {
        padding: 1rem;
        text-align: center;
        color: var(--text-secondary);
    }
    
    .success-message {
        padding: 1rem;
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid #10b981;
        border-radius: 0.375rem;
        color: #10b981;
    }
    
    .error-message {
        padding: 1rem;
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid #ef4444;
        border-radius: 0.375rem;
        color: #ef4444;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: 1rem;
        padding: 1rem;
        background: var(--bg-tertiary);
        border-radius: 0.375rem;
    }
    
    details {
        margin-top: 1rem;
    }
    
    details summary {
        cursor: pointer;
        padding: 0.5rem;
        background: var(--bg-tertiary);
        border-radius: 0.375rem;
    }
    
    details pre {
        margin-top: 0.5rem;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 0.375rem;
        overflow-x: auto;
        font-size: 0.875rem;
    }
    
    .log-time {
        color: var(--text-secondary);
        margin-right: 0.5rem;
    }
    
    .btn-small {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Iniciar aplicación
document.addEventListener('DOMContentLoaded', () => {
    window.playground = new PlaygroundApp();
});