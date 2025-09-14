/**
 * Componente de Controles de Jingle para Dashboard
 * Versión simplificada para usuarios finales
 */

export class JingleControls {
    constructor(container) {
        this.container = container;
        this.enabled = true;
        this.selectedMusic = null;
        this.musicList = [];
        this.config = null;
        
        this.init();
    }

    async init() {
        this.render();
        await this.loadConfig();
        await this.loadMusicList();
        this.attachEventListeners();
    }
    
    async loadConfig() {
        try {
            const response = await fetch(window.location.protocol + '//' + window.location.hostname + ':4000/api/jingle-config-service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get' })
            });

            const data = await response.json();
            
            if (data.success && data.config) {
                this.config = data.config.jingle_defaults;
                console.log('[JingleControls] Configuración cargada:', this.config);
                this.updatePresetInfo();
            }
        } catch (error) {
            console.error('[JingleControls] Error cargando configuración:', error);
            // Usar valores por defecto si falla
            this.config = {
                intro_silence: 2,
                outro_silence: 4,
                music_volume: 0.3,
                voice_volume: 1.0,
                fade_in: 2,
                fade_out: 2,
                ducking_enabled: true,
                duck_level: 0.2
            };
        }
    }

    render() {
        this.container.innerHTML = `
            <div class="jingle-controls">
                <div class="jingle-header">
                    <span class="toggle-label">🎵 Jingle</span>
                    <span class="jingle-info" title="Agrega música de fondo a tu mensaje">ℹ️</span>
                </div>
                
                <div class="jingle-options" id="jingleOptions" style="display: block;">
                    <div class="music-selector">
                        <label class="music-label">Música de fondo:</label>
                        <select id="musicSelect" class="music-select">
                            <option value="">Cargando música...</option>
                        </select>
                        <button type="button" class="preview-btn" id="previewMusic" title="Escuchar música">
                            ▶️
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    async loadMusicList() {
        try {
            const response = await fetch(window.location.protocol + '//' + window.location.hostname + ':4000/api/jingle-service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list_music' })
            });

            const data = await response.json();
            
            if (data.success && data.music) {
                this.musicList = data.music;
                this.updateMusicSelector();
            }
        } catch (error) {
            console.error('Error cargando música:', error);
            this.musicList = [];
            this.updateMusicSelector();
        }
    }

    updateMusicSelector() {
        const select = document.getElementById('musicSelect');
        if (!select) return;

        select.innerHTML = `
            <option value="">-- Seleccionar música --</option>
            ${this.musicList.map(music => {
                // Usar el nombre del archivo sin la extensión como display
                const displayName = music.file ? music.file.replace('.mp3', '').replace('.wav', '').replace('.ogg', '') : music.name;
                return `<option value="${music.file}">${displayName}</option>`;
            }).join('')}
        `;

        // Seleccionar la primera música por defecto
        if (this.musicList.length > 0) {
            select.value = this.musicList[0].file;
            this.selectedMusic = this.musicList[0].file;
        }
    }

    attachEventListeners() {
        // Ya no necesitamos el toggle, siempre está habilitado
        this.enabled = true;
        
        // Disparar evento para notificar al dashboard que está habilitado
        this.container.dispatchEvent(new CustomEvent('jingle-toggle', {
            detail: { enabled: true }
        }));

        // Selector de música
        const musicSelect = document.getElementById('musicSelect');
        if (musicSelect) {
            musicSelect.addEventListener('change', (e) => {
                this.selectedMusic = e.target.value;
            });
        }

        // Preview música
        const previewBtn = document.getElementById('previewMusic');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => {
                this.previewMusic();
            });
        }
    }

    previewMusic() {
        if (!this.selectedMusic) {
            alert('Por favor selecciona una música primero');
            return;
        }

        // Crear reproductor temporal
        const audio = new Audio(`/audio/music/${this.selectedMusic}`);
        audio.volume = 0.3;
        
        const previewBtn = document.getElementById('previewMusic');
        previewBtn.textContent = '⏸️';
        previewBtn.disabled = true;

        audio.play();

        // Detener después de 10 segundos
        setTimeout(() => {
            audio.pause();
            previewBtn.textContent = '▶️';
            previewBtn.disabled = false;
        }, 10000);
    }

    updatePresetInfo() {
        const details = document.getElementById('presetDetails');
        if (details && this.config) {
            details.textContent = `${this.config.intro_silence}s intro • Voz clara • ${this.config.outro_silence}s outro`;
        }
    }

    getJingleOptions() {
        if (!this.enabled) return null;

        // Usar la configuración cargada del servidor
        const options = {
            music_file: this.selectedMusic || this.config?.default_music,
            music_volume: this.config?.music_volume || 0.3,
            voice_volume: this.config?.voice_volume || 1.0,
            fade_in: this.config?.fade_in || 2,
            fade_out: this.config?.fade_out || 2,
            music_duck: this.config?.ducking_enabled !== undefined ? this.config.ducking_enabled : true,
            duck_level: this.config?.duck_level || 0.2,
            intro_silence: this.config?.intro_silence || 2,
            outro_silence: this.config?.outro_silence || 4
        };
        
        // Incluir voice_settings si están en la configuración
        if (this.config?.voice_settings) {
            options.voice_settings = this.config.voice_settings;
        }
        
        return options;
    }

    reset() {
        // Mantener siempre habilitado
        this.enabled = true;
        
        const options = document.getElementById('jingleOptions');
        if (options) {
            options.style.display = 'block';
        }

        const musicSelect = document.getElementById('musicSelect');
        if (musicSelect && this.musicList.length > 0) {
            musicSelect.value = this.musicList[0].file;
            this.selectedMusic = this.musicList[0].file;
        }
    }
}

// Estilos CSS inline para el componente
const style = document.createElement('style');
style.textContent = `
.jingle-controls {
    padding: 1rem;
    background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
    border-radius: 8px;
    margin-top: 1rem;
}

.jingle-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.jingle-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.jingle-toggle input[type="checkbox"] {
    display: none;
}

.toggle-slider {
    width: 44px;
    height: 24px;
    background: #ccc;
    border-radius: 12px;
    position: relative;
    transition: background 0.3s;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    top: 3px;
    left: 3px;
    transition: transform 0.3s;
}

.jingle-toggle input:checked + .toggle-slider {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.jingle-toggle input:checked + .toggle-slider::after {
    transform: translateX(20px);
}

.toggle-label {
    font-weight: 500;
    color: var(--text-primary);
}

.jingle-info {
    cursor: help;
    opacity: 0.6;
}

.jingle-options {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.music-selector {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.music-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.music-select {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: white;
}

.preview-btn {
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.preview-btn:hover:not(:disabled) {
    background: var(--bg-hover);
    transform: scale(1.05);
}

.preview-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.jingle-presets {
    padding: 0.75rem;
    background: white;
    border-radius: 4px;
    border-left: 3px solid #667eea;
}

.preset-info {
    display: block;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.preset-details {
    color: #667eea;
    font-weight: 500;
}
`;
document.head.appendChild(style);