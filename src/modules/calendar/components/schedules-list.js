/**
 * SchedulesList Component - Maneja el renderizado y actualización de la lista de schedules
 * @module SchedulesList
 * 
 * Responsabilidades:
 * - Renderizar tabla/cards de schedules
 * - Manejar estados de loading y empty
 * - Integración con ScheduleService
 * - Mantener compatibilidad con window.calendarModule
 */

export class SchedulesList {
    constructor(container, scheduleService, eventBus) {
        this.container = container;
        this.scheduleService = scheduleService;
        this.eventBus = eventBus;
        
        // Referencias DOM
        this.tableContainer = null;
        this.countElement = null;
        
        // Estado
        this.schedules = [];
        this.isLoading = false;
        
        // Callbacks para acciones (serán proporcionados por el módulo principal)
        this.onToggleStatus = null;
        this.onDelete = null;
        this.onEdit = null;
        this.onPreviewAudio = null;
    }
    
    /**
     * Inicializa el componente
     */
    async init() {
        // Obtener referencias DOM
        this.tableContainer = document.getElementById('schedules-table-container');
        this.countElement = document.getElementById('schedulesCount');
        
        if (!this.tableContainer) {
            console.error('[SchedulesList] Container not found');
            return false;
        }
        
        return true;
    }
    
    /**
     * Carga y renderiza la lista de schedules
     */
    async load() {
        this.setLoadingState(true);
        
        try {
            const schedules = await this.scheduleService.loadSchedulesList();
            this.schedules = schedules;
            this.render(schedules);
            return schedules;
        } catch (error) {
            console.error('[SchedulesList] Error loading schedules:', error);
            this.renderError('Error al cargar programaciones');
            throw error;
        } finally {
            this.setLoadingState(false);
        }
    }
    
    /**
     * Renderiza la tabla de schedules
     */
    render(schedules) {
        if (!this.tableContainer) return;
        
        // Actualizar contador
        if (this.countElement) {
            this.countElement.textContent = schedules ? schedules.length : 0;
        }
        
        // Estado vacío
        if (!schedules || schedules.length === 0) {
            this.renderEmptyState();
            return;
        }
        
        // Renderizar cards de schedules
        let schedulesHTML = '';
        
        schedules.forEach(schedule => {
            schedulesHTML += this.renderScheduleCard(schedule);
        });
        
        this.tableContainer.innerHTML = schedulesHTML;
        
        // Mantener compatibilidad con window.calendarModule
        this.setupGlobalFunctions();
    }
    
    /**
     * Renderiza una card individual de schedule
     */
    renderScheduleCard(schedule) {
        const type = this.getScheduleTypeLabel(schedule);
        const timing = this.getScheduleTimingForTable(schedule);
        const displayName = schedule.title || schedule.filename || 'Sin archivo';
        const category = schedule.category || 'sin_categoria';
        const categoryBadge = this.getCategoryBadge(category);
        
        return `
            <div class="schedule-card">
                <div class="schedule-time-block">
                    <div class="schedule-time">${this.getScheduleTime(schedule)}</div>
                    <div class="schedule-frequency">${this.getScheduleFrequency(schedule)}</div>
                </div>
                
                <div class="schedule-content">
                    <div class="schedule-header">
                        <span class="category-dot category-dot-${category}"></span>
                        <h3 class="schedule-title">${this.truncateText(displayName, 35)}</h3>
                    </div>
                    <p class="schedule-message">
                        ${this.getScheduleDescription(schedule)}
                    </p>
                    <div class="schedule-meta">
                        <div class="schedule-meta-item">
                            <span>📅</span>
                            <span>${timing}</span>
                        </div>
                        <div class="schedule-meta-item">
                            <span>🔄</span>
                            <span>${type}</span>
                        </div>
                        ${this.getScheduleDays(schedule)}
                    </div>
                </div>
                
                <div class="schedule-actions">
                    <div class="schedule-status ${schedule.is_active ? 'active' : ''}">
                        <span>●</span>
                        <span>${schedule.is_active ? 'Activo' : 'Inactivo'}</span>
                    </div>
                    <div class="schedule-btn-group">
                        ${schedule.is_active ? `
                        <button class="btn-icon btn-icon--small" 
                                onclick="window.calendarModule.toggleScheduleStatus(${schedule.id}, false)"
                                title="Pausar">⏸️</button>
                        ` : `
                        <button class="btn-icon btn-icon--small" 
                                onclick="window.calendarModule.toggleScheduleStatus(${schedule.id}, true)"
                                title="Activar">▶️</button>
                        `}
                        <button class="btn-icon btn-icon--small" 
                                onclick="window.calendarModule.editSchedule(${schedule.id})"
                                title="Editar">✏️</button>
                        <button class="btn-icon btn-icon--small btn-delete" 
                                onclick="window.calendarModule.deleteScheduleFromList(${schedule.id})"
                                title="Eliminar">🗑️</button>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Renderiza el estado vacío
     */
    renderEmptyState() {
        if (!this.tableContainer) return;
        
        this.tableContainer.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-title">
                    No hay programaciones activas configuradas
                </div>
                <div class="empty-state-description">
                    Las programaciones aparecerán aquí cuando se creen desde Campaign Library
                </div>
            </div>
        `;
    }
    
    /**
     * Renderiza estado de error
     */
    renderError(message) {
        if (!this.tableContainer) return;
        
        this.tableContainer.innerHTML = `<p class="error-message">${message}</p>`;
    }
    
    /**
     * Establece el estado de carga
     */
    setLoadingState(loading) {
        this.isLoading = loading;
        
        if (!this.tableContainer) return;
        
        if (loading) {
            this.tableContainer.innerHTML = `
                <div class="loading-state">
                    <div class="loading-spinner"></div>
                    <p>Cargando programaciones...</p>
                </div>
            `;
        }
    }
    
    /**
     * Configura las funciones globales para compatibilidad
     */
    setupGlobalFunctions() {
        // Mantener compatibilidad con onclick handlers existentes
        if (!window.calendarModule) {
            window.calendarModule = {};
        }
        
        // Las funciones reales serán proporcionadas por el módulo principal
        // Este componente solo se asegura de que exista el objeto global
    }
    
    // ========================================
    // HELPERS DE FORMATEO (migrados de index.js)
    // ========================================
    
    /**
     * Obtiene el badge de categoría
     */
    getCategoryBadge(category) {
        const categories = {
            'ofertas': 'OFERTAS',
            'eventos': 'EVENTOS', 
            'informacion': 'INFO',
            'emergencias': 'EMERGENCIA',
            'servicios': 'SERVICIOS',
            'horarios': 'HORARIOS',
            'sin_categoria': 'GENERAL'
        };
        const label = categories[category] || 'GENERAL';
        return `<span class="badge badge-${category}">${label}</span>`;
    }
    
    /**
     * Obtiene el tiempo de la programación
     */
    getScheduleTime(schedule) {
        if (schedule.schedule_time) {
            if (typeof schedule.schedule_time === 'string' && schedule.schedule_time.startsWith('[')) {
                try {
                    const times = JSON.parse(schedule.schedule_time);
                    return Array.isArray(times) ? times[0] : schedule.schedule_time;
                } catch(e) {
                    return schedule.schedule_time;
                }
            }
            return schedule.schedule_time;
        }
        return '00:00';
    }
    
    /**
     * Obtiene la frecuencia del schedule
     */
    getScheduleFrequency(schedule) {
        switch(schedule.schedule_type) {
            case 'interval':
                const hours = schedule.interval_hours || 0;
                const minutes = schedule.interval_minutes || 0;
                if (hours > 0) {
                    return `Cada ${hours}h${minutes > 0 ? ' ' + minutes + 'min' : ''}`;
                }
                return `Cada ${minutes} min`;
            case 'daily':
                return 'Diario';
            case 'weekly':
                return 'Semanal';
            case 'once':
                return 'Una vez';
            default:
                return schedule.schedule_type || 'Manual';
        }
    }
    
    /**
     * Obtiene la descripción del schedule
     */
    getScheduleDescription(schedule) {
        const filename = schedule.filename || 'Sin archivo';
        const category = schedule.category || 'sin_categoria';
        
        // Si hay un mensaje personalizado, usarlo
        if (schedule.message) {
            return schedule.message;
        }
        
        // Generar descripción basada en el tipo
        switch(schedule.schedule_type) {
            case 'interval':
                return `Reproducir "${filename}" con frecuencia regular`;
            case 'daily':
                return `Reproducir "${filename}" todos los días`;
            case 'weekly':
                return `Reproducir "${filename}" días específicos`;
            case 'once':
                return `Reproducir "${filename}" una sola vez`;
            default:
                return `Archivo: ${filename}`;
        }
    }
    
    /**
     * Obtiene los días del schedule
     */
    getScheduleDays(schedule) {
        if (!schedule.schedule_days || schedule.schedule_type !== 'weekly') {
            return '';
        }
        
        try {
            const days = JSON.parse(schedule.schedule_days);
            const dayNames = {
                'monday': 'Lun', 'tuesday': 'Mar', 'wednesday': 'Mié',
                'thursday': 'Jue', 'friday': 'Vie', 'saturday': 'Sáb', 'sunday': 'Dom'
            };
            
            const daysList = days.map(d => dayNames[d] || d).join(', ');
            
            return `
                <div class="schedule-meta-item">
                    <span>📆</span>
                    <span>${daysList}</span>
                </div>
            `;
        } catch(e) {
            return '';
        }
    }
    
    /**
     * Obtiene el label del tipo de schedule
     */
    getScheduleTypeLabel(schedule) {
        const labels = {
            'interval': 'Intervalo',
            'daily': 'Diario',
            'weekly': 'Semanal',
            'once': 'Única vez'
        };
        return labels[schedule.schedule_type] || schedule.schedule_type || 'Manual';
    }
    
    /**
     * Formatea el timing para la tabla
     */
    getScheduleTimingForTable(schedule) {
        try {
            const type = schedule.schedule_type;
            
            switch(type) {
                case 'interval':
                    const hours = schedule.interval_hours || 0;
                    const minutes = schedule.interval_minutes || 0;
                    
                    if (hours > 0 && minutes > 0) {
                        return `${hours}h ${minutes}min`;
                    } else if (hours > 0) {
                        return `${hours} ${hours === 1 ? 'hora' : 'horas'}`;
                    } else {
                        return `${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}`;
                    }
                    
                case 'daily':
                    const time = schedule.schedule_time || '00:00';
                    return `Diario - ${time}`;
                    
                case 'weekly':
                    const days = JSON.parse(schedule.schedule_days || '[]');
                    const daysMap = {
                        'monday': 'Lun', 'tuesday': 'Mar', 'wednesday': 'Mié',
                        'thursday': 'Jue', 'friday': 'Vie', 'saturday': 'Sáb', 'sunday': 'Dom'
                    };
                    
                    let daysStr = '';
                    if (days.length === 7) {
                        daysStr = 'Todos los días';
                    } else if (days.length === 0) {
                        daysStr = 'Sin días';
                    } else {
                        daysStr = days.map(d => daysMap[d] || d).join(', ');
                    }
                    
                    return daysStr;
                    
                case 'once':
                    const date = schedule.start_date ? 
                        new Date(schedule.start_date).toLocaleDateString('es-CL') : 
                        'Fecha no definida';
                    return date;
                    
                default:
                    return 'No configurado';
            }
        } catch (error) {
            console.error('[SchedulesList] Error formatting timing:', error);
            return 'Error en formato';
        }
    }
    
    /**
     * Trunca texto largo
     */
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }
    
    /**
     * Limpieza del componente
     */
    destroy() {
        this.tableContainer = null;
        this.countElement = null;
        this.schedules = [];
    }
}