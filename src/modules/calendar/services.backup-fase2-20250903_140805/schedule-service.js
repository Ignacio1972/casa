/**
 * Schedule Service - Manejo de programaciones del calendario
 * @module ScheduleService
 * 
 * Extrae toda la lógica relacionada con schedules del CalendarModule
 * para mejorar la separación de responsabilidades y mantenibilidad.
 */

// Note: Using fetch directly instead of apiClient for consistency with original code

export class ScheduleService {
    constructor() {
        this.cache = new Map();
        this.cacheTimeout = 30000; // 30 segundos
    }

    /**
     * Carga la lista de programaciones activas
     * @returns {Promise<Array>} Lista de schedules
     */
    async loadSchedulesList() {
        try {
            const response = await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                return data.schedules || [];
            } else {
                throw new Error(data.error || 'Error al cargar programaciones');
            }
        } catch (error) {
            console.error('[ScheduleService] Error loading schedules:', error);
            throw error;
        }
    }

    /**
     * Cambia el estado activo/inactivo de una programación
     * @param {number} scheduleId - ID del schedule
     * @param {boolean} activate - true para activar, false para desactivar
     * @returns {Promise<boolean>} Success status
     */
    async toggleScheduleStatus(scheduleId, activate) {
        try {
            const action = activate ? 'activate' : 'deactivate';
            const response = await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: action, 
                    id: scheduleId 
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                return true;
            } else {
                throw new Error(data.error || 'Error al cambiar estado');
            }
        } catch (error) {
            console.error('[ScheduleService] Error toggling schedule status:', error);
            throw error;
        }
    }

    /**
     * Elimina una programación
     * @param {number} scheduleId - ID del schedule a eliminar
     * @returns {Promise<boolean>} Success status
     */
    async deleteSchedule(scheduleId) {
        try {
            const response = await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'delete', 
                    id: scheduleId 
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                return true;
            } else {
                throw new Error(data.error || 'Error al eliminar programación');
            }
        } catch (error) {
            console.error('[ScheduleService] Error deleting schedule:', error);
            throw error;
        }
    }

    /**
     * Obtiene detalles de una programación específica
     * @param {number} scheduleId - ID del schedule
     * @returns {Promise<Object>} Datos del schedule
     */
    async getScheduleDetails(scheduleId) {
        try {
            const response = await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'get', 
                    id: scheduleId 
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                return data.schedule;
            } else {
                throw new Error(data.error || 'Error al cargar detalles');
            }
        } catch (error) {
            console.error('[ScheduleService] Error loading schedule details:', error);
            throw error;
        }
    }

    // ========================================
    // MÉTODOS DE FORMATEO (extraídos de index.js)
    // ========================================

    /**
     * Formatea el tiempo de programación para mostrar
     * @param {Object} schedule - Datos del schedule
     * @returns {string} Tiempo formateado
     */
    getScheduleTime(schedule) {
        if (schedule.schedule_time) {
            if (typeof schedule.schedule_time === 'string' && schedule.schedule_time.startsWith('[')) {
                try {
                    const times = JSON.parse(schedule.schedule_time);
                    return times.map(t => t.substring(0, 5)).join(', ');
                } catch (e) {
                    console.error('[ScheduleService] Error parsing schedule_time JSON:', e);
                    return schedule.schedule_time;
                }
            }
            return schedule.schedule_time.substring(0, 5);
        }
        return 'No definido';
    }

    /**
     * Obtiene la descripción de la programación
     * @param {Object} schedule - Datos del schedule
     * @returns {string} Descripción formateada
     */
    getScheduleDescription(schedule) {
        // Si notes es un JSON string, intentar parsearlo
        if (schedule.notes && typeof schedule.notes === 'string') {
            try {
                const parsedNotes = JSON.parse(schedule.notes);
                if (parsedNotes.userText) {
                    return parsedNotes.userText;
                }
            } catch (e) {
                // Si no es JSON válido, usar como texto plano
                return schedule.notes;
            }
        }
        
        // Fallback basado en tipo de programación
        const type = this.getScheduleTypeLabel(schedule);
        return `Programación ${type.toLowerCase()}`;
    }

    /**
     * Obtiene la frecuencia de repetición
     * @param {Object} schedule - Datos del schedule
     * @returns {string} Frecuencia formateada
     */
    getScheduleFrequency(schedule) {
        if (schedule.schedule_type === 'interval') {
            const h = parseInt(schedule.interval_hours) || 0;
            const m = parseInt(schedule.interval_minutes) || 0;
            
            if (h > 0 && m > 0) {
                return `Cada ${h}h ${m}m`;
            } else if (h > 0) {
                return `Cada ${h}h`;
            } else if (m > 0) {
                return `Cada ${m}m`;
            }
            return 'Sin intervalo';
        }
        return 'Una vez';
    }

    /**
     * Obtiene los días de programación
     * @param {Object} schedule - Datos del schedule
     * @returns {string} Días formateados
     */
    getScheduleDays(schedule) {
        if (schedule.schedule_type !== 'specific' && schedule.schedule_type !== 'interval') {
            return '';
        }
        
        if (schedule.schedule_days) {
            try {
                const days = JSON.parse(schedule.schedule_days);
                const dayNames = {
                    0: 'Dom', 1: 'Lun', 2: 'Mar', 3: 'Mié', 
                    4: 'Jue', 5: 'Vie', 6: 'Sáb'
                };
                
                if (Array.isArray(days)) {
                    return days.map(d => dayNames[d]).join(', ');
                }
            } catch (e) {
                console.error('[ScheduleService] Error parsing schedule_days:', e);
            }
        }
        return 'Todos los días';
    }

    /**
     * Obtiene la etiqueta del tipo de programación
     * @param {Object} schedule - Datos del schedule
     * @returns {string} Etiqueta del tipo
     */
    getScheduleTypeLabel(schedule) {
        const types = {
            'interval': '⏱️ Intervalo',
            'specific': '📅 Días específicos',
            'daily': '📅 Diario',
            'weekly': '📅 Semanal'
        };
        return types[schedule.schedule_type] || '📅 Programado';
    }

    /**
     * Obtiene el timing formateado para tabla
     * @param {Object} schedule - Datos del schedule
     * @returns {string} Timing formateado
     */
    getScheduleTimingForTable(schedule) {
        try {
            switch(schedule.schedule_type) {
                case 'interval':
                    const h = parseInt(schedule.interval_hours) || 0;
                    const m = parseInt(schedule.interval_minutes) || 0;
                    if (h > 0 && m > 0) return `${h}h ${m}m`;
                    if (h > 0) return `${h}h`;
                    if (m > 0) return `${m}m`;
                    return 'N/A';
                    
                case 'specific':
                    return this.getScheduleTime(schedule);
                    
                default:
                    return 'Una vez';
            }
        } catch (error) {
            console.error('[ScheduleService] Error formatting timing:', error);
            return 'N/A';
        }
    }

    /**
     * Obtiene el badge HTML para categoría
     * @param {string} category - Categoría del schedule
     * @returns {string} HTML del badge
     */
    getCategoryBadge(category) {
        const labels = {
            'ofertas': '🛒 Ofertas',
            'eventos': '🎉 Eventos', 
            'informacion': 'ℹ️ Info',
            'emergencias': '🚨 Urgente',
            'servicios': '🛠️ Servicios',
            'horarios': '🕐 Horarios',
            'sin_categoria': '📁 Sin Cat.'
        };
        
        const label = labels[category] || labels['sin_categoria'];
        return `<span class="message-badge badge-${category || 'sin-categoria'}">${label}</span>`;
    }
}