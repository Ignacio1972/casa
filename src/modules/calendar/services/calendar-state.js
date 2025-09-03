/**
 * Calendar State Manager - Gestión centralizada del estado del calendario
 * @module CalendarStateManager
 * 
 * Maneja el estado global del módulo calendario incluyendo:
 * - Cache de archivos disponibles
 * - Estado de carga
 * - Eventos y programaciones
 * - Configuración del usuario
 */

import { apiClient } from '../../../core/api-client.js';

export class CalendarStateManager {
    constructor() {
        // Estado principal
        this.availableFiles = [];
        this.schedules = [];
        this.events = [];
        
        // Estados de carga
        this.isLoading = false;
        this.loadingStates = {
            files: false,
            schedules: false,
            events: false
        };
        
        // Cache con TTL
        this.cache = new Map();
        this.cacheTimeout = 60000; // 1 minuto
        
        // Configuración
        this.config = {
            autoRefresh: true,
            refreshInterval: 30000,
            maxRetries: 3
        };
        
        // Event listeners para limpiar cache
        this.listeners = [];
    }

    // ========================================
    // GESTIÓN DE ARCHIVOS DISPONIBLES
    // ========================================

    /**
     * Carga la lista de archivos disponibles de la biblioteca
     * @returns {Promise<Array>} Lista de archivos disponibles
     */
    async loadAvailableFiles() {
        this.setLoadingState('files', true);
        
        try {
            // Verificar cache primero
            const cached = this.getFromCache('available_files');
            if (cached) {
                this.availableFiles = cached;
                return cached;
            }

            // Usar la API existente de biblioteca (misma llamada que el código original)
            const data = await apiClient.post('/biblioteca.php', {
                action: 'list_library'
            });
            
            if (data.success && data.files) {
                this.availableFiles = data.files.map(file => ({
                    value: file.filename,
                    label: file.filename,
                    duration: file.duration || 0,
                    size: file.size,
                    date: file.date
                }));
                
                // Guardar en cache
                this.setCache('available_files', this.availableFiles);
                
                console.log(`[CalendarState] Loaded ${this.availableFiles.length} audio files`);
                return this.availableFiles;
            } else {
                throw new Error('No se pudieron cargar los archivos');
            }
            
        } catch (error) {
            console.error('[CalendarState] Error loading files:', error);
            this.availableFiles = [];
            throw error;
        } finally {
            this.setLoadingState('files', false);
        }
    }

    /**
     * Obtiene la lista de archivos disponibles (desde cache si está disponible)
     * @returns {Array} Lista de archivos
     */
    getAvailableFiles() {
        return this.availableFiles;
    }

    /**
     * Filtra archivos por nombre
     * @param {string} query - Término de búsqueda
     * @returns {Array} Archivos filtrados
     */
    filterAvailableFiles(query) {
        if (!query) return this.availableFiles;
        
        const searchTerm = query.toLowerCase();
        return this.availableFiles.filter(file => 
            file.label.toLowerCase().includes(searchTerm)
        );
    }

    // ========================================
    // GESTIÓN DE ESTADO GENERAL
    // ========================================

    /**
     * Establece el estado de carga para una operación específica
     * @param {string} operation - Nombre de la operación
     * @param {boolean} isLoading - Estado de carga
     */
    setLoadingState(operation, isLoading) {
        this.loadingStates[operation] = isLoading;
        
        // Actualizar estado global
        this.isLoading = Object.values(this.loadingStates).some(state => state);
        
        // Emitir evento para UI
        this.emit('loading:changed', {
            operation,
            isLoading,
            globalLoading: this.isLoading
        });
    }

    /**
     * Verifica si una operación está cargando
     * @param {string} operation - Nombre de la operación
     * @returns {boolean} Estado de carga
     */
    isLoadingOperation(operation) {
        return this.loadingStates[operation] || false;
    }

    /**
     * Verifica si hay alguna operación cargando
     * @returns {boolean} Estado de carga global
     */
    isLoadingAny() {
        return this.isLoading;
    }

    // ========================================
    // GESTIÓN DE CACHE
    // ========================================

    /**
     * Guarda datos en cache con TTL
     * @param {string} key - Clave del cache
     * @param {*} data - Datos a guardar
     * @param {number} [ttl] - Tiempo de vida en ms
     */
    setCache(key, data, ttl = this.cacheTimeout) {
        this.cache.set(key, {
            data,
            timestamp: Date.now(),
            ttl
        });
    }

    /**
     * Obtiene datos del cache si no han expirado
     * @param {string} key - Clave del cache
     * @returns {*} Datos del cache o null
     */
    getFromCache(key) {
        const entry = this.cache.get(key);
        if (!entry) return null;
        
        if (Date.now() - entry.timestamp > entry.ttl) {
            this.cache.delete(key);
            return null;
        }
        
        return entry.data;
    }

    /**
     * Limpia el cache completamente
     */
    clearCache() {
        this.cache.clear();
        console.log('[CalendarState] Cache cleared');
    }

    /**
     * Limpia entrada específica del cache
     * @param {string} key - Clave a eliminar
     */
    clearCacheEntry(key) {
        this.cache.delete(key);
    }

    // ========================================
    // GESTIÓN DE CONFIGURACIÓN
    // ========================================

    /**
     * Actualiza configuración
     * @param {Object} newConfig - Nueva configuración
     */
    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
        this.emit('config:changed', this.config);
    }

    /**
     * Obtiene configuración actual
     * @returns {Object} Configuración
     */
    getConfig() {
        return { ...this.config };
    }

    // ========================================
    // SISTEMA DE EVENTOS SIMPLE
    // ========================================

    /**
     * Registra un listener de eventos
     * @param {string} event - Nombre del evento
     * @param {Function} callback - Función callback
     */
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    /**
     * Emite un evento
     * @param {string} event - Nombre del evento
     * @param {*} data - Datos del evento
     */
    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('[CalendarState] Error in event listener:', error);
                }
            });
        }
    }

    /**
     * Elimina un listener de eventos
     * @param {string} event - Nombre del evento
     * @param {Function} callback - Función a eliminar
     */
    off(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
    }

    // ========================================
    // LIMPIEZA Y DESTRUCCIÓN
    // ========================================

    /**
     * Limpia todos los recursos del state manager
     */
    destroy() {
        this.clearCache();
        this.listeners = [];
        this.availableFiles = [];
        this.schedules = [];
        this.events = [];
        console.log('[CalendarState] State manager destroyed');
    }

    // ========================================
    // MÉTODOS DE DEBUG
    // ========================================

    /**
     * Obtiene estadísticas del estado actual
     * @returns {Object} Estadísticas
     */
    getStats() {
        return {
            availableFiles: this.availableFiles.length,
            schedules: this.schedules.length,
            events: this.events.length,
            cacheEntries: this.cache.size,
            isLoading: this.isLoading,
            loadingStates: { ...this.loadingStates }
        };
    }

    /**
     * Imprime estadísticas en consola
     */
    logStats() {
        console.log('[CalendarState] Current stats:', this.getStats());
    }
}