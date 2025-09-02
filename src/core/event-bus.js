// /var/www/tts-mall/shared/event-bus.js
/**
 * Event Bus - Sistema central de eventos
 * Maneja toda la comunicación entre módulos
 * @module EventBus
 */

class EventBus {
    constructor() {
        this.events = new Map();
        this.onceEvents = new Set();
        
        // Debug mode para desarrollo
        this.debug = true;
    }
    
    /**
     * Emite un evento con datos opcionales
     * @param {string} event - Nombre del evento (soporta namespace module:event)
     * @param {*} data - Datos a pasar con el evento
     */
    emit(event, data = null) {
        if (this.debug) {
            console.log(`[EventBus] Emit: ${event}`, data);
        }
        
        if (!this.events.has(event)) return;
        
        const listeners = this.events.get(event);
        const listenersArray = Array.from(listeners);
        
        listenersArray.forEach(listener => {
            try {
                listener.callback(data);
                
                // Si es once, eliminar después de ejecutar
                if (listener.once) {
                    this.off(event, listener.callback);
                }
            } catch (error) {
                console.error(`[EventBus] Error in listener for ${event}:`, error);
            }
        });
    }
    
    /**
     * Suscribe a un evento
     * @param {string} event - Nombre del evento
     * @param {Function} callback - Función a ejecutar
     * @returns {Function} Función para desuscribirse
     */
    on(event, callback) {
        if (!this.events.has(event)) {
            this.events.set(event, new Set());
        }
        
        const listener = { callback, once: false };
        this.events.get(event).add(listener);
        
        // Retornar función de limpieza
        return () => this.off(event, callback);
    }
    
    /**
     * Suscribe a un evento una sola vez
     * @param {string} event - Nombre del evento
     * @param {Function} callback - Función a ejecutar
     */
    once(event, callback) {
        if (!this.events.has(event)) {
            this.events.set(event, new Set());
        }
        
        const listener = { callback, once: true };
        this.events.get(event).add(listener);
    }
    
    /**
     * Desuscribe de un evento
     * @param {string} event - Nombre del evento
     * @param {Function} callback - Función a desuscribir
     */
    off(event, callback) {
        if (!this.events.has(event)) return;
        
        const listeners = this.events.get(event);
        const toRemove = Array.from(listeners).find(l => l.callback === callback);
        
        if (toRemove) {
            listeners.delete(toRemove);
            
            // Limpiar Map si no hay más listeners
            if (listeners.size === 0) {
                this.events.delete(event);
            }
        }
    }
    
    /**
     * Elimina todos los listeners de un evento o namespace
     * @param {string} pattern - Evento o patrón (ej: "module:*")
     */
    clear(pattern = null) {
        if (!pattern) {
            this.events.clear();
            return;
        }
        
        // Soporte para wildcards en namespace
        if (pattern.includes('*')) {
            const prefix = pattern.replace('*', '');
            const toDelete = [];
            
            this.events.forEach((_, key) => {
                if (key.startsWith(prefix)) {
                    toDelete.push(key);
                }
            });
            
            toDelete.forEach(key => this.events.delete(key));
        } else {
            this.events.delete(pattern);
        }
    }
    
    /**
     * Debug: Lista todos los eventos activos
     */
    listEvents() {
        const summary = {};
        this.events.forEach((listeners, event) => {
            summary[event] = listeners.size;
        });
        return summary;
    }
}

// Singleton global
const eventBus = new EventBus();

// Eventos del sistema predefinidos
const SystemEvents = {
    MODULE_LOADED: 'module:loaded',
    MODULE_UNLOADED: 'module:unloaded',
    MODULE_ERROR: 'module:error',
    NAVIGATION_CHANGE: 'navigation:change',
    AUDIO_GENERATED: 'audio:generated',
    MESSAGE_SAVED: 'message:saved',
    MESSAGE_DELETED: 'message:deleted',
    API_REQUEST: 'api:request',
    API_RESPONSE: 'api:response',
    API_ERROR: 'api:error'
};

// Export
export { eventBus, SystemEvents };