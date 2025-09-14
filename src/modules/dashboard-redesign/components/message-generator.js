/**
 * UI Handler - Manejo centralizado de eventos UI
 * VERSIÓN SIMPLIFICADA
 * @module UIHandler
 */

export class UIHandler {
    constructor(container, actions) {
        this.container = container;
        this.actions = actions;
        this.setupEventDelegation();
    }
    
    /**
     * Configura delegación de eventos
     */
    setupEventDelegation() {
        // Un solo listener para clicks
        this.container.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]')?.dataset.action;
            if (action && this.actions[action]) {
                e.preventDefault();
                this.actions[action](e);
            }
        });
        
        // Un solo listener para cambios en inputs
        this.container.addEventListener('change', (e) => {
            const field = e.target.dataset.field;
            if (field && this.actions.updateField) {
                this.actions.updateField(field, e.target.value);
            }
        });
        
        // Un solo listener para input de texto
        this.container.addEventListener('input', (e) => {
            if (e.target.id === 'message-text' && this.actions.updateText) {
                this.actions.updateText(e.target.value);
            }
        });
    }
    
    /**
     * Actualiza el contador de caracteres
     */
    updateCharCounter(length) {
        const counter = this.container.querySelector('#char-count');
        if (counter) {
            counter.textContent = length;
            counter.style.color = length > 450 ? '#dc3545' : 
                               length > 400 ? '#ffc107' : '#666';
        }
    }
    
    /**
     * Muestra estado/feedback
     */
    showStatus(message, type = 'info') {
        const statusEl = this.container.querySelector('#generation-status');
        if (statusEl) {
            statusEl.className = `status-container status-${type}`;
            statusEl.textContent = message;
            
            if (type !== 'loading') {
                setTimeout(() => {
                    statusEl.textContent = '';
                    statusEl.className = 'status-container';
                }, 5000);
            }
        }
    }
    
    /**
     * Actualiza el player de audio
     */
    showAudioPlayer(filename) {
        const player = this.container.querySelector('#audio-player');
        const playerContainer = this.container.querySelector('#audio-player-container');
        
        if (player && filename) {
            player.src = '/api/temp/' + filename;
            playerContainer.style.display = 'block';
            player.play().catch(e => console.log('Autoplay prevented:', e));
        }
    }
}