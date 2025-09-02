// /var/www/tts-mall/v2/shared/api-client.js
/**
 * API Client - Comunicación con backend
 * @module APIClient
 */

import { eventBus } from './event-bus.js';

class APIClient {
    constructor() {
        this.baseURL = '/api';
        this.timeout = 30000;
    }
    
    /**
     * Realiza petición POST
     */
    async post(endpoint, data) {
        const url = this.baseURL + endpoint;
        
        eventBus.emit('api:request', { url, data });
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            eventBus.emit('api:response', { url, result });
            
            return result;
        } catch (error) {
            eventBus.emit('api:error', { url, error });
            throw error;
        }
    }
    
    /**
     * Obtiene lista de plantillas
     */
    async getTemplates() {
        return this.post('/generate.php', { action: 'list_templates' });
    }
    
    /**
     * Obtiene archivos de biblioteca
     */
    async getLibraryFiles() {
        return this.post('/biblioteca.php', { action: 'list_library' });
    }
    
    /**
     * Elimina archivo de biblioteca
     */
    async deleteLibraryFile(filename) {
        return this.post('/biblioteca.php', {
            action: 'delete_library_file',
            filename: filename
        });
    }
}

export const apiClient = new APIClient();