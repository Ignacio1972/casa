/**
 * Calendar API Service - Comunicación con backend
 * @module CalendarAPI
 */

import { apiClient } from '../../../shared/api-client.js';

export class CalendarAPI {
    constructor() {
        this.baseEndpoint = '/api/audio-scheduler.php';
        this.cache = new Map();
        this.cacheTimeout = 60000; // 1 minuto
    }
    
    /**
     * Obtiene todos los eventos del calendario
     * @param {Object} filters - Filtros opcionales
     * @returns {Promise<Array>} Lista de eventos
     */
    async getEvents(filters = {}) {
        try {
            const cacheKey = 'events_' + JSON.stringify(filters);
            const cached = this.getFromCache(cacheKey);
            if (cached) return cached;
            
            const response = await this.request('GET', {
                action: 'list',
                ...filters
            });
            
            if (response.success) {
                this.setCache(cacheKey, response.events);
                return response.events;
            } else {
                throw new Error(response.error || 'Error al obtener eventos');
            }
            
        } catch (error) {
            console.error('[CalendarAPI] Error getting events:', error);
            throw error;
        }
    }
    
    /**
     * Obtiene eventos en un rango de fechas
     * @param {Date} start - Fecha inicio
     * @param {Date} end - Fecha fin
     * @returns {Promise<Array>} Lista de eventos
     */
    async getEventsByDateRange(start, end) {
        const filters = {
            start_date: this.formatDate(start),
            end_date: this.formatDate(end)
        };
        
        return this.getEvents(filters);
    }
    
    /**
     * Obtiene eventos próximos (siguientes 24 horas)
     * @returns {Promise<Array>} Lista de eventos próximos
     */
    async getUpcomingEvents() {
        try {
            const response = await this.request('GET', {
                action: 'upcoming',
                hours: 24
            });
            
            if (response.success) {
                return response.events;
            } else {
                throw new Error(response.error || 'Error al obtener próximos eventos');
            }
            
        } catch (error) {
            console.error('[CalendarAPI] Error getting upcoming events:', error);
            return [];
        }
    }
    
    /**
     * Obtiene un evento por ID
     * @param {number} eventId - ID del evento
     * @returns {Promise<Object>} Datos del evento
     */
    async getEvent(eventId) {
        try {
            const response = await this.request('GET', {
                action: 'get',
                id: eventId
            });
            
            if (response.success) {
                return response.event;
            } else {
                throw new Error(response.error || 'Evento no encontrado');
            }
            
        } catch (error) {
            console.error('[CalendarAPI] Error getting event:', error);
            throw error;
        }
    }
    
    /**
     * Guarda un evento (crear o actualizar)
     * @param {Object} eventData - Datos del evento
     * @returns {Promise<Object>} Respuesta del servidor
     */
    async saveEvent(eventData) {
        try {
            // Validar datos requeridos
            this.validateEventData(eventData);
            
            // Determinar si es crear o actualizar
            const action = eventData.id ? 'update' : 'create';
            
            // Preparar datos para envío
            const data = {
                action: action,
                ...this.prepareEventData(eventData)
            };
            
            const response = await this.request('POST', data);
            
            if (response.success) {
                // Limpiar cache
                this.clearCache();
                
                return {
                    success: true,
                    event: response.event,
                    message: response.message || (action === 'create' ? 'Evento creado' : 'Evento actualizado')
                };
            } else {
                throw new Error(response.error || 'Error al guardar evento');
            }
            
        } catch (error) {
            console.error('[CalendarAPI] Error saving event:', error);
            throw error;
        }
    }
    
    /**
     * Elimina un evento
     * @param {number} eventId - ID del evento
     * @returns {Promise<Object>} Respuesta del servidor
     */
    async deleteEvent(eventId) {
        try {
            if (!eventId) {
                throw new Error('ID de evento requerido');
            }
            
            const response = await this.request('POST', {
                action: 'delete',
                id: eventId
            });
            
            if (response.success) {
                // Limpiar cache
                this.clearCache();
                
                return {
                    success: true,
                    message: response.message || 'Evento eliminado'
                };
            } else {
                throw new Error(response.error || 'Error al eliminar evento');
            }
            
        } catch (error) {
            console.error('[CalendarAPI] Error deleting event:', error);
            throw error;
        }
    }
    
    /**
     * Activa/desactiva un evento
     * @param {number} eventId - ID del evento
     * @param {boolean} active - Estado activo
     * @returns {Promise<Object>} Respuesta del servidor
     */
    async toggleEventStatus(eventId, active) {
        try {
            const response = await this.request('POST', {
                action: 'toggle_status',
                id: eventId,
                is_active: active ? 1 : 0
            });
            
            if (response.success) {
                this.clearCache();
                return response;
            } else {
                throw new Error(response.error || 'Error al cambiar estado');
            }
            
        } catch (error) {
            console.error('[CalendarAPI] Error toggling event status:', error);
            throw error;
        }
    }
    
    /**
     * Obtiene el historial de reproducción de un evento
     * @param {number} eventId - ID del evento
     * @returns {Promise<Array>} Historial de reproducciones
     */
    async getEventHistory(eventId) {
        try {
            const response = await this.request('GET', {
                action: 'history',
                event_id: eventId
            });
            
            if (response.success) {
                return response.history;
            } else {
                throw new Error(response.error || 'Error al obtener historial');
            }
            
        } catch (error) {
            console.error('[CalendarAPI] Error getting event history:', error);
            return [];
        }
    }
    
    /**
     * Obtiene estadísticas del calendario
     * @returns {Promise<Object>} Estadísticas
     */
    async getStats() {
        try {
            const cached = this.getFromCache('stats');
            if (cached) return cached;
            
            const response = await this.request('GET', {
                action: 'stats'
            });
            
            if (response.success) {
                this.setCache('stats', response.stats, 300000); // Cache 5 minutos
                return response.stats;
            } else {
                throw new Error(response.error || 'Error al obtener estadísticas');
            }
            
        } catch (error) {
            console.error('[CalendarAPI] Error getting stats:', error);
            return {
                total_events: 0,
                active_events: 0,
                today_events: 0,
                this_week_events: 0
            };
        }
    }
    
    /**
     * Verifica disponibilidad de archivos de audio
     * @param {string} filePath - Path del archivo
     * @returns {Promise<boolean>} Si el archivo existe
     */
    async verifyAudioFile(filePath) {
        try {
            const response = await this.request('POST', {
                action: 'verify_file',
                file_path: filePath
            });
            
            return response.success && response.exists;
            
        } catch (error) {
            console.error('[CalendarAPI] Error verifying file:', error);
            return false;
        }
    }
    
    /**
     * Ejecuta una petición al backend
     * @private
     */
    async request(method, data) {
        const url = this.baseEndpoint;
        
        if (method === 'GET') {
            // Construir query string para GET
            const params = new URLSearchParams(data);
            const response = await fetch(`${url}?${params}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
            
        } else {
            // POST request usando apiClient
            return await apiClient.post(this.baseEndpoint, data);
        }
    }
    
    /**
     * Valida datos del evento antes de enviar
     * @private
     */
    validateEventData(eventData) {
        const required = ['title', 'file_path', 'category', 'start_datetime'];
        const missing = required.filter(field => !eventData[field]);
        
        if (missing.length > 0) {
            throw new Error(`Campos requeridos faltantes: ${missing.join(', ')}`);
        }
        
        // Validar fecha no sea pasada para eventos nuevos
        if (!eventData.id) {
            const eventDate = new Date(eventData.start_datetime);
            const now = new Date();
            
            if (eventDate < now) {
                throw new Error('No se pueden programar eventos en el pasado');
            }
        }
        
        // Validar prioridad
        if (eventData.priority && (eventData.priority < 1 || eventData.priority > 10)) {
            throw new Error('La prioridad debe estar entre 1 y 10');
        }
    }
    
    /**
     * Prepara datos del evento para envío
     * @private
     */
    prepareEventData(eventData) {
        return {
            id: eventData.id || null,
            title: eventData.title.trim(),
            filename: eventData.filename,
            schedule_time: eventData.schedule_time,
            schedule_days: eventData.schedule_days || "once",
            start_date: eventData.start_date,
            end_date: eventData.end_date || null,
            priority: eventData.priority || 5,
            notes: eventData.notes || "",
            is_active: eventData.is_active !== false ? 1 : 0
        };
    }
    
    /**
     * Formatea fecha para el backend
     * @private
     */
    formatDate(date) {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        
        return `${year}-${month}-${day}`;
    }
    
    /**
     * Formatea fecha y hora para el backend
     * @private
     */
    formatDateTime(date) {
        const d = new Date(date);
        const dateStr = this.formatDate(d);
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        
        return `${dateStr} ${hours}:${minutes}:00`;
    }
    
    /**
     * Obtiene datos del cache
     * @private
     */
    getFromCache(key) {
        const cached = this.cache.get(key);
        
        if (cached && Date.now() - cached.timestamp < cached.timeout) {
            console.log(`[CalendarAPI] Cache hit for: ${key}`);
            return cached.data;
        }
        
        return null;
    }
    
    /**
     * Guarda datos en cache
     * @private
     */
    setCache(key, data, timeout = null) {
        this.cache.set(key, {
            data: data,
            timestamp: Date.now(),
            timeout: timeout || this.cacheTimeout
        });
    }
    
    /**
     * Limpia el cache
     * @private
     */
    clearCache() {
        console.log('[CalendarAPI] Clearing cache');
        this.cache.clear();
    }
}