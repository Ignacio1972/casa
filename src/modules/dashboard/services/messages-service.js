/**
 * Messages Service
 * Servicio para gestionar mensajes guardados en el sistema MBI
 * Conecta con /api/saved-messages.php y localStorage
 */

class MessagesService {
    constructor() {
        this.apiClient = window.apiClient || null;
        this.baseUrl = '/api/saved-messages.php';
        this.localStoragePrefix = 'tts_mall_library_message_';
        
        // Cache temporal de mensajes
        this.cache = {
            messages: [],
            lastFetch: null,
            ttl: 60000 // 1 minuto de cache
        };
    }
    
    /**
     * Obtiene mensajes recientes
     * @param {number} limit - Número de mensajes a obtener
     * @param {string} orderBy - Campo para ordenar (created_at, title, category)
     * @param {string} order - Dirección (ASC, DESC)
     */
    async getRecentMessages(limit = 6, orderBy = 'created_at', order = 'DESC') {
        try {
            // Verificar cache
            if (this.isCacheValid() && this.cache.messages.length >= limit) {
                return {
                    success: true,
                    data: this.cache.messages.slice(0, limit)
                };
            }
            
            // Llamar a la API
            const response = await this.apiClient.post(this.baseUrl, {
                action: 'list',
                limit: limit,
                orderBy: orderBy,
                order: order
            });
            
            if (response.success) {
                // Actualizar cache
                this.cache.messages = response.data || [];
                this.cache.lastFetch = Date.now();
                
                // Enriquecer con datos de localStorage si están disponibles
                this.enrichWithLocalData(response.data);
            }
            
            return response;
            
        } catch (error) {
            console.error('[MessagesService] Error fetching recent messages:', error);
            
            // Fallback a localStorage si la API falla
            return this.getMessagesFromLocalStorage(limit);
        }
    }
    
    /**
     * Obtiene todos los mensajes
     */
    async getAllMessages() {
        try {
            const response = await this.apiClient.post(this.baseUrl, {
                action: 'list'
            });
            
            if (response.success) {
                this.cache.messages = response.data || [];
                this.cache.lastFetch = Date.now();
            }
            
            return response;
            
        } catch (error) {
            console.error('[MessagesService] Error fetching all messages:', error);
            return this.getMessagesFromLocalStorage();
        }
    }
    
    /**
     * Obtiene un mensaje específico por ID
     * @param {string} messageId - ID del mensaje
     */
    async getMessage(messageId) {
        try {
            // Primero buscar en cache
            const cachedMessage = this.cache.messages.find(m => m.id === messageId);
            if (cachedMessage && this.isCacheValid()) {
                return {
                    success: true,
                    data: cachedMessage
                };
            }
            
            // Llamar a la API
            const response = await this.apiClient.post(this.baseUrl, {
                action: 'get',
                id: messageId
            });
            
            return response;
            
        } catch (error) {
            console.error('[MessagesService] Error fetching message:', error);
            
            // Intentar obtener de localStorage
            const localMessage = this.getMessageFromLocalStorage(messageId);
            if (localMessage) {
                return {
                    success: true,
                    data: localMessage
                };
            }
            
            return {
                success: false,
                error: 'Mensaje no encontrado'
            };
        }
    }
    
    /**
     * Guarda un nuevo mensaje
     * @param {Object} messageData - Datos del mensaje
     */
    async saveMessage(messageData) {
        try {
            // Generar ID si no existe
            if (!messageData.id) {
                messageData.id = this.generateMessageId();
            }
            
            // Añadir timestamp si no existe
            if (!messageData.created_at) {
                messageData.created_at = new Date().toISOString();
            }
            
            // Guardar en API
            const response = await this.apiClient.post(this.baseUrl, {
                action: 'save',
                data: messageData
            });
            
            if (response.success) {
                // Guardar también en localStorage
                this.saveToLocalStorage(messageData);
                
                // Actualizar cache
                this.cache.messages.unshift(messageData);
                
                // Emitir evento
                if (window.eventBus) {
                    window.eventBus.emit('message:saved', messageData);
                }
            }
            
            return response;
            
        } catch (error) {
            console.error('[MessagesService] Error saving message:', error);
            
            // Intentar guardar solo en localStorage como fallback
            this.saveToLocalStorage(messageData);
            
            return {
                success: true,
                data: messageData,
                warning: 'Guardado solo localmente'
            };
        }
    }
    
    /**
     * Actualiza un mensaje existente
     * @param {string} messageId - ID del mensaje
     * @param {Object} updates - Campos a actualizar
     */
    async updateMessage(messageId, updates) {
        try {
            const response = await this.apiClient.post(this.baseUrl, {
                action: 'update',
                id: messageId,
                data: updates
            });
            
            if (response.success) {
                // Actualizar en localStorage
                this.updateInLocalStorage(messageId, updates);
                
                // Actualizar cache
                const index = this.cache.messages.findIndex(m => m.id === messageId);
                if (index !== -1) {
                    this.cache.messages[index] = {
                        ...this.cache.messages[index],
                        ...updates
                    };
                }
            }
            
            return response;
            
        } catch (error) {
            console.error('[MessagesService] Error updating message:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }
    
    /**
     * Elimina un mensaje
     * @param {string} messageId - ID del mensaje
     */
    async deleteMessage(messageId) {
        try {
            const response = await this.apiClient.post(this.baseUrl, {
                action: 'delete',
                id: messageId
            });
            
            if (response.success) {
                // Eliminar de localStorage
                this.deleteFromLocalStorage(messageId);
                
                // Actualizar cache
                this.cache.messages = this.cache.messages.filter(m => m.id !== messageId);
            }
            
            return response;
            
        } catch (error) {
            console.error('[MessagesService] Error deleting message:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }
    
    /**
     * Busca mensajes por texto o categoría
     * @param {string} query - Término de búsqueda
     * @param {string} category - Categoría opcional
     */
    async searchMessages(query, category = null) {
        try {
            const response = await this.apiClient.post(this.baseUrl, {
                action: 'search',
                query: query,
                category: category
            });
            
            return response;
            
        } catch (error) {
            console.error('[MessagesService] Error searching messages:', error);
            
            // Búsqueda local como fallback
            return this.searchInLocalStorage(query, category);
        }
    }
    
    /**
     * Obtiene mensajes por categoría
     * @param {string} category - Categoría a filtrar
     */
    async getMessagesByCategory(category) {
        try {
            const response = await this.apiClient.post(this.baseUrl, {
                action: 'list',
                filter: {
                    category: category
                }
            });
            
            return response;
            
        } catch (error) {
            console.error('[MessagesService] Error fetching by category:', error);
            return this.getMessagesFromLocalStorage(null, category);
        }
    }
    
    // ========== Métodos de LocalStorage ==========
    
    /**
     * Obtiene mensajes desde localStorage
     */
    getMessagesFromLocalStorage(limit = null, category = null) {
        const messages = [];
        
        // Obtener todas las claves que coincidan con el patrón
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith(this.localStoragePrefix)) {
                try {
                    const messageData = JSON.parse(localStorage.getItem(key));
                    
                    // Filtrar por categoría si se especifica
                    if (!category || messageData.category === category) {
                        messages.push(messageData);
                    }
                } catch (e) {
                    console.error('[MessagesService] Error parsing localStorage message:', e);
                }
            }
        }
        
        // Ordenar por fecha (más recientes primero)
        messages.sort((a, b) => {
            const dateA = new Date(a.created_at || 0);
            const dateB = new Date(b.created_at || 0);
            return dateB - dateA;
        });
        
        // Aplicar límite si se especifica
        const finalMessages = limit ? messages.slice(0, limit) : messages;
        
        return {
            success: true,
            data: finalMessages,
            source: 'localStorage'
        };
    }
    
    /**
     * Obtiene un mensaje específico de localStorage
     */
    getMessageFromLocalStorage(messageId) {
        const key = this.localStoragePrefix + messageId;
        const data = localStorage.getItem(key);
        
        if (data) {
            try {
                return JSON.parse(data);
            } catch (e) {
                console.error('[MessagesService] Error parsing localStorage message:', e);
            }
        }
        
        return null;
    }
    
    /**
     * Guarda un mensaje en localStorage
     */
    saveToLocalStorage(messageData) {
        const key = this.localStoragePrefix + messageData.id;
        try {
            localStorage.setItem(key, JSON.stringify(messageData));
        } catch (e) {
            console.error('[MessagesService] Error saving to localStorage:', e);
            
            // Si está lleno, intentar limpiar mensajes antiguos
            if (e.name === 'QuotaExceededError') {
                this.cleanupOldMessages();
                // Reintentar
                try {
                    localStorage.setItem(key, JSON.stringify(messageData));
                } catch (e2) {
                    console.error('[MessagesService] Still cannot save after cleanup:', e2);
                }
            }
        }
    }
    
    /**
     * Actualiza un mensaje en localStorage
     */
    updateInLocalStorage(messageId, updates) {
        const key = this.localStoragePrefix + messageId;
        const existing = this.getMessageFromLocalStorage(messageId);
        
        if (existing) {
            const updated = { ...existing, ...updates };
            localStorage.setItem(key, JSON.stringify(updated));
        }
    }
    
    /**
     * Elimina un mensaje de localStorage
     */
    deleteFromLocalStorage(messageId) {
        const key = this.localStoragePrefix + messageId;
        localStorage.removeItem(key);
    }
    
    /**
     * Busca en localStorage
     */
    searchInLocalStorage(query, category = null) {
        const allMessages = this.getMessagesFromLocalStorage().data;
        
        const filtered = allMessages.filter(msg => {
            const matchesQuery = !query || 
                (msg.text && msg.text.toLowerCase().includes(query.toLowerCase())) ||
                (msg.title && msg.title.toLowerCase().includes(query.toLowerCase()));
            
            const matchesCategory = !category || msg.category === category;
            
            return matchesQuery && matchesCategory;
        });
        
        return {
            success: true,
            data: filtered,
            source: 'localStorage'
        };
    }
    
    /**
     * Enriquece datos de API con datos locales
     */
    enrichWithLocalData(messages) {
        if (!messages || !Array.isArray(messages)) return;
        
        messages.forEach(msg => {
            const localData = this.getMessageFromLocalStorage(msg.id);
            if (localData) {
                // Combinar datos, priorizando los de la API
                Object.keys(localData).forEach(key => {
                    if (!msg[key] && localData[key]) {
                        msg[key] = localData[key];
                    }
                });
            }
        });
    }
    
    /**
     * Limpia mensajes antiguos de localStorage
     */
    cleanupOldMessages(keepCount = 50) {
        const messages = this.getMessagesFromLocalStorage().data;
        
        if (messages.length > keepCount) {
            // Ordenar por fecha y mantener solo los más recientes
            const toDelete = messages.slice(keepCount);
            
            toDelete.forEach(msg => {
                this.deleteFromLocalStorage(msg.id);
            });
            
            console.log(`[MessagesService] Cleaned ${toDelete.length} old messages from localStorage`);
        }
    }
    
    // ========== Utilidades ==========
    
    /**
     * Genera un ID único para el mensaje
     */
    generateMessageId() {
        return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * Verifica si el cache es válido
     */
    isCacheValid() {
        return this.cache.lastFetch && 
               (Date.now() - this.cache.lastFetch) < this.cache.ttl;
    }
    
    /**
     * Limpia el cache
     */
    clearCache() {
        this.cache.messages = [];
        this.cache.lastFetch = null;
    }
}

// Exportar como singleton
export default new MessagesService();