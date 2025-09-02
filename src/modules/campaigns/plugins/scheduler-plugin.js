/**
 * Plugin de Programación para Campaign Library
 * Separado del módulo principal para mantener código modular
 */

export class SchedulerPlugin {
    constructor(campaignLibrary) {
        this.library = campaignLibrary;
        this.enabled = false;
    }
    
    /**
     * Inicializar el plugin
     */
    init() {
        if (this.enabled) return;
        
        // Agregar método al módulo principal
        this.library.scheduleAudio = this.scheduleAudio.bind(this);
        
        // Exponer en window
        if (window.campaignLibrary) {
            window.campaignLibrary.scheduleAudio = (id, title) => {
                this.scheduleAudio(id, title);
            };
        }
        
        // Inyectar botón en las tarjetas existentes
        this.injectScheduleButtons();
        
        this.enabled = true;
        console.log('[SchedulerPlugin] Inicializado');
    }
    
    /**
     * Programar un audio
     */
    async scheduleAudio(id, title) {
        // Buscar el mensaje
        const message = this.library.messages.find(m => m.id === id);
        
        if (!message || message.type !== 'audio') {
            this.library.showError('Solo se pueden programar archivos de audio');
            return;
        }
        
        // Cargar modal si no existe
        if (!window.scheduleModal) {
            await this.loadScheduleModal();
        }
        
        // Mostrar modal
        if (window.scheduleModal) {
            window.scheduleModal.show(message.filename, title || message.title);
        }
    }
    
    /**
     * Cargar el modal de programación
     */
    async loadScheduleModal() {
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.type = 'module';
            script.src = '/modules/campaigns/schedule-modal.js';
            script.onload = () => {
                setTimeout(resolve, 100);
            };
            document.head.appendChild(script);
        });
    }
    
    /**
     * Inyectar botones en las tarjetas
     */
    injectScheduleButtons() {
        // Observar cambios en el DOM
        const observer = new MutationObserver(() => {
            this.addButtonsToCards();
        });
        
        const container = document.querySelector('#messages-grid');
        if (container) {
            observer.observe(container, { childList: true });
            this.addButtonsToCards(); // Agregar a las tarjetas existentes
        }
    }
    
    /**
     * Agregar botón a cada tarjeta de audio
     */
    addButtonsToCards() {
        document.querySelectorAll('.message-card.audio-card').forEach(card => {
            // Si ya tiene botón, saltar
            if (card.querySelector('.btn-schedule')) return;
            
            const id = card.dataset.id;
            const message = this.library.messages.find(m => m.id === id);
            
            if (message && message.type === 'audio') {
                const actionsDiv = card.querySelector('.message-actions');
                if (actionsDiv) {
                    // Insertar botón antes del último (eliminar)
                    const deleteBtn = actionsDiv.querySelector('.btn-danger');
                    const scheduleBtn = document.createElement('button');
                    scheduleBtn.className = 'btn-icon btn-schedule';
                    scheduleBtn.innerHTML = '🕐';
                    scheduleBtn.title = 'Programar reproducción';
                    scheduleBtn.onclick = () => {
                        this.scheduleAudio(id, message.title);
                    };
                    
                    actionsDiv.insertBefore(scheduleBtn, deleteBtn);
                }
            }
        });
    }
    
    /**
     * Cargar programaciones activas
     */
    async loadSchedules() {
        try {
            const response = await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list' })
            });
            
            const result = await response.json();
            if (result.success) {
                return result.schedules;
            }
        } catch (error) {
            console.error('[SchedulerPlugin] Error cargando programaciones:', error);
        }
        return [];
    }
}

// Auto-inicializar cuando el módulo campaigns esté listo
document.addEventListener('DOMContentLoaded', () => {
    // Esperar a que campaigns esté disponible
    const checkInterval = setInterval(() => {
        if (window.campaignLibrary && window.campaignLibrary.messages) {
            clearInterval(checkInterval);
            
            // Crear e inicializar el plugin
            const schedulerPlugin = new SchedulerPlugin(window.campaignLibrary);
            schedulerPlugin.init();
            
            // Exponer globalmente para debug
            window.schedulerPlugin = schedulerPlugin;
        }
    }, 500);
});