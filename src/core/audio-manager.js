// /var/www/tts-mall/v2/shared/audio-manager.js
/**
 * Audio Manager - Gestión de reproducción y generación
 * @module AudioManager
 */

import { eventBus } from './event-bus.js';
import { apiClient } from './api-client.js';

class AudioManager {
    constructor() {
        this.currentAudio = null;
        this.audioCache = new Map();
    }
    
    /**
     * Genera audio desde texto
     */
    async generateAudio(params) {
        try {
            eventBus.emit('audio:generating', params);
            
            const response = await apiClient.post('/generate.php', {
                action: 'generate_audio',
                ...params
            });
            
            if (response.success) {
                eventBus.emit('audio:generated', response);
                return response;
            } else {
                throw new Error(response.error || 'Error generando audio');
            }
        } catch (error) {
            eventBus.emit('audio:error', error);
            throw error;
        }
    }
    
    /**
     * Reproduce audio desde URL
     */
    play(url) {
        if (this.currentAudio) {
            this.currentAudio.pause();
        }
        
        this.currentAudio = new Audio(url);
        this.currentAudio.play();
        
        eventBus.emit('audio:playing', { url });
        
        this.currentAudio.onended = () => {
            eventBus.emit('audio:ended', { url });
        };
        
        return this.currentAudio;
    }
    
    /**
     * Detiene reproducción actual
     */
    stop() {
        if (this.currentAudio) {
            this.currentAudio.pause();
            this.currentAudio.currentTime = 0;
            eventBus.emit('audio:stopped');
        }
    }
    
    /**
     * Envía audio a radio
     */
    async sendToRadio(filename) {
        try {
            eventBus.emit('radio:sending', { filename });
            
            const response = await apiClient.post('/generate.php', {
                action: 'send_to_radio',
                filename: filename
            });
            
            if (response.success) {
                eventBus.emit('radio:sent', response);
                return response;
            } else {
                throw new Error(response.error || 'Error enviando a radio');
            }
        } catch (error) {
            eventBus.emit('radio:error', error);
            throw error;
        }
    }
}

export const audioManager = new AudioManager();