// /var/www/tts-mall/v2/shared/storage-manager.js
/**
 * Storage Manager - Gestión de persistencia local
 * @module StorageManager
 */

import { eventBus } from './event-bus.js';

class StorageManager {
    constructor() {
        this.prefix = 'tts_mall_';
        this.cache = new Map();
    }
    
    /**
     * Guarda un mensaje/configuración
     */
    save(key, data) {
        const fullKey = this.prefix + key;
        try {
            const serialized = JSON.stringify(data);
            localStorage.setItem(fullKey, serialized);
            this.cache.set(key, data);
            
            eventBus.emit('storage:saved', { key, data });
            return true;
        } catch (error) {
            console.error('[StorageManager] Save error:', error);
            return false;
        }
    }
    
    /**
     * Obtiene un mensaje/configuración
     */
    get(key) {
        // Check cache first
        if (this.cache.has(key)) {
            return this.cache.get(key);
        }
        
        const fullKey = this.prefix + key;
        try {
            const item = localStorage.getItem(fullKey);
            if (!item) return null;
            
            const data = JSON.parse(item);
            this.cache.set(key, data);
            return data;
        } catch (error) {
            console.error('[StorageManager] Get error:', error);
            return null;
        }
    }
    
    /**
     * Lista todos los mensajes guardados
     */
    listMessages() {
        const messages = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith(this.prefix + 'message_')) {
                const data = this.get(key.replace(this.prefix, ''));
                if (data) messages.push(data);
            }
        }
        return messages.sort((a, b) => b.createdAt - a.createdAt);
    }
    
    /**
     * Elimina un mensaje
     */
    delete(key) {
        const fullKey = this.prefix + key;
        localStorage.removeItem(fullKey);
        this.cache.delete(key);
        eventBus.emit('storage:deleted', { key });
        return true;
    }
    
    /**
     * Guarda configuración de voz
     */
    saveVoiceProfile(profile) {
        return this.save('voice_profile_' + profile.id, profile);
    }
    
    /**
     * Lista perfiles de voz
     */
    listVoiceProfiles() {
        const profiles = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith(this.prefix + 'voice_profile_')) {
                const data = this.get(key.replace(this.prefix, ''));
                if (data) profiles.push(data);
            }
        }
        return profiles;
    }
}

export const storageManager = new StorageManager();