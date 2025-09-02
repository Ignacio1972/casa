/**
 * Calendar Module - Sistema de Programación de Anuncios
 * @module CalendarModule
 * Version: 2.1 - Added schedules list table
 */

console.log('[CalendarModule] Loading version 2.1 with schedules list');

import { eventBus } from '../../shared/event-bus.js';
import { apiClient } from '../../shared/api-client.js';
import { CalendarView } from './components/calendar-view.js';
import { CalendarFilters } from './components/calendar-filters.js';

export default class CalendarModule {
    constructor() {
        this.name = 'calendar';
        this.container = null;
        this.calendarView = null;
        this.calendarFilters = null;
        
        // Cache de archivos disponibles
        this.availableFiles = [];
    }
    
    getName() {
        return this.name;
    }
    
    /**
     * Carga estilos adicionales del tooltip
     * @private
     */
    loadTooltipStyles() {
        // Verificar si ya existe
        // Cargar estilos de tooltips
        if (!document.getElementById('calendar-tooltip-styles')) {
            const link = document.createElement('link');
            link.id = 'calendar-tooltip-styles';
            link.rel = 'stylesheet';
            link.href = '/modules/calendar/styles/calendar-tooltips.css';
            document.head.appendChild(link);
            console.log('[Calendar] Tooltip styles loaded');
        }
    }
    
    /**
     * Carga los estilos principales del módulo Calendar
     * @private
     */
    async loadMainStyles() {
        if (!document.querySelector('#calendar-main-styles')) {
            const link = document.createElement('link');
            link.id = 'calendar-main-styles';
            link.rel = 'stylesheet';
            link.href = '/modules/calendar/style.css';
            document.head.appendChild(link);
            
            await new Promise((resolve) => {
                link.onload = resolve;
                link.onerror = () => {
                    console.error('[Calendar] Failed to load main styles');
                    resolve();
                };
            });
            console.log('[Calendar] Main styles loaded');
        }
        
        // Cargar también los estilos de Audio Archive para la lista de archivos
        if (!document.querySelector('#calendar-audio-archive-styles')) {
            const link = document.createElement('link');
            link.id = 'calendar-audio-archive-styles';
            link.rel = 'stylesheet';
            link.href = '/modules/audio-archive/styles.css';
            document.head.appendChild(link);
            
            await new Promise((resolve) => {
                link.onload = resolve;
                link.onerror = () => {
                    console.error('[Calendar] Failed to load Audio Archive styles');
                    resolve();
                };
            });
            console.log('[Calendar] Audio Archive styles loaded for file list');
        }
    }
    
    /**
     * Aplica estilos críticos para el header del calendario
     * @private
     */
    applyHeaderStyles() {
        // Crear o actualizar estilos inline para el header
        let styleEl = document.getElementById('calendar-header-override-styles');
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = 'calendar-header-override-styles';
            document.head.appendChild(styleEl);
        }
        
        styleEl.textContent = `
            /* Override crítico para header de días */
            .fc .fc-col-header-cell,
            .fc-theme-standard .fc-col-header-cell,
            .fc th.fc-col-header-cell,
            .fc-col-header th,
            .fc-scrollgrid-sync-table th {
                background-color: #4a5568 !important;
                background: #4a5568 !important;
            }
            
            .fc .fc-col-header-cell-cushion,
            .fc-col-header-cell a,
            .fc-col-header-cell span {
                color: #ffffff !important;
                font-weight: 700 !important;
                opacity: 1 !important;
            }
            
            .fc .fc-col-header,
            .fc-scrollgrid-sync-table thead,
            .fc-scrollgrid-sync-table tbody tr:first-child {
                background-color: #4a5568 !important;
                background: #4a5568 !important;
            }
            
            /* Asegurar que la tabla del header tenga fondo */
            .fc-scrollgrid-sync-table {
                background-color: #4a5568 !important;
            }
            
            /* Para vista de semana y día */
            .fc-timegrid .fc-col-header-cell,
            .fc-timegrid-axis-cushion {
                background-color: #4a5568 !important;
                color: #ffffff !important;
            }
        `;
        
        console.log('[Calendar] Header styles applied');
    }
    
    /**
     * Aplica estilos agresivos para las celdas del calendario
     * @private
     */
    applyCellStyles() {
        // Crear o actualizar estilos inline para las celdas
        let styleEl = document.getElementById('calendar-cells-override-styles');
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = 'calendar-cells-override-styles';
            document.head.appendChild(styleEl);
        }
        
        styleEl.textContent = `
            /* CALENDARIO MODERNO Y SOBRIO - Tonos Grises Elegantes */
            
            /* Container principal */
            .audio-archive-module {
                max-width: 1400px !important;
                margin: 0 auto !important;
                padding: var(--space-xl) !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            
            /* Contenedor del calendario con rounded corners */
            .archive-list {
                border-radius: 16px !important;
                overflow: hidden !important;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12) !important;
                background: linear-gradient(145deg, #2d3748, #1a202c) !important;
            }
            
            /* Celdas principales - Gris sobrio moderno */
            .fc-daygrid-day,
            .fc .fc-daygrid-day,
            .fc-theme-standard .fc-daygrid-day,
            .calendar-view .fc-daygrid-day {
                background: linear-gradient(145deg, #374151, #2d3748) !important;
                border: 1px solid rgba(75, 85, 99, 0.3) !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            
            /* Hover effect elegante */
            .fc-daygrid-day:hover,
            .fc .fc-daygrid-day:hover,
            .fc-theme-standard .fc-daygrid-day:hover {
                background: linear-gradient(145deg, #4b5563, #374151) !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
            }
            
            /* Día actual - Accent moderno */
            .fc-day-today,
            .fc .fc-day-today,
            .fc-theme-standard .fc-day-today {
                background: linear-gradient(145deg, #3b82f6, #2563eb) !important;
                border: 2px solid #60a5fa !important;
                border-radius: 12px !important;
                box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3) !important;
            }
            
            /* Headers con estilo moderno */
            .fc-col-header-cell,
            .fc .fc-col-header-cell,
            .fc-theme-standard .fc-col-header-cell {
                background: linear-gradient(145deg, #1f2937, #111827) !important;
                border-bottom: 2px solid rgba(75, 85, 99, 0.4) !important;
                color: #9ca3af !important;
                font-weight: 600 !important;
                font-size: 0.75rem !important;
                text-transform: uppercase !important;
                letter-spacing: 0.1em !important;
                padding: 16px 12px !important;
            }
            
            /* Números de días */
            .fc-daygrid-day-number {
                color: #e5e7eb !important;
                font-weight: 500 !important;
                font-size: 0.9rem !important;
                padding: 8px !important;
            }
            
            /* Eventos con diseño moderno */
            .fc-event,
            .fc .fc-event {
                background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
                border: none !important;
                border-radius: 8px !important;
                color: white !important;
                font-weight: 500 !important;
                font-size: 0.75rem !important;
                padding: 4px 8px !important;
                box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3) !important;
                transition: all 0.2s ease !important;
            }
            
            .fc-event:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4) !important;
                background: linear-gradient(135deg, #7c3aed, #6366f1) !important;
            }
            
            /* Botones de navegación modernos */
            .fc-button,
            .fc .fc-button {
                background: linear-gradient(145deg, #374151, #2d3748) !important;
                border: 1px solid rgba(75, 85, 99, 0.3) !important;
                border-radius: 10px !important;
                color: #e5e7eb !important;
                font-weight: 500 !important;
                padding: 10px 16px !important;
                transition: all 0.2s ease !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            }
            
            .fc-button:hover,
            .fc .fc-button:hover {
                background: linear-gradient(145deg, #4b5563, #374151) !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            }
            
            .fc-button-active,
            .fc-button:active,
            .fc .fc-button-active {
                background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
                border-color: #6366f1 !important;
                color: white !important;
                box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3) !important;
            }
            
            /* Título del calendario */
            .fc-toolbar-title {
                color: #f9fafb !important;
                font-weight: 700 !important;
                font-size: 1.5rem !important;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
            }
            
            /* Bordes suaves en todo el calendario */
            .fc,
            .fc .fc-scrollgrid,
            .fc-theme-standard .fc-scrollgrid {
                border-radius: 16px !important;
                overflow: hidden !important;
            }
        `;
        
        console.log('[Calendar] Cell styles applied');
    }

    
    async load(container) {
        console.log('[Calendar] Loading module...');
        this.container = container;
        
        try {
            // Cargar template HTML
            await this.loadTemplate();

            // Cargar estilos principales (incluyendo modal)
            await this.loadMainStyles();

            // Cargar estilos del tooltip
            this.loadTooltipStyles();
            
            // Aplicar estilos del header
            this.applyHeaderStyles();
            
            // Cargar archivos disponibles de la biblioteca
            await this.loadAvailableFiles();
            
            // Inicializar componentes
            await this.initializeComponents();
            
            // Cargar eventos del calendario
            await this.loadCalendarEvents();
            
            // Cargar lista de schedules
            await this.loadSchedulesList();
            
            // Adjuntar event listeners
            this.attachEventListeners();
            
            // Re-aplicar estilos después de que FullCalendar se renderice
            setTimeout(() => {
                this.applyHeaderStyles();
                this.applyCellStyles();
            }, 100);
            
            // Re-aplicar estilos periódicamente para asegurar que se mantengan
            setTimeout(() => {
                this.applyHeaderStyles();
                this.applyCellStyles();
            }, 500);
            
            setTimeout(() => {
                this.applyHeaderStyles();
                this.applyCellStyles();
            }, 1000);
            
            eventBus.emit('calendar:loaded');
            console.log('[Calendar] Module loaded successfully');
            
        } catch (error) {
            console.error('[Calendar] Load failed:', error);
            this.showError('Error al cargar el calendario: ' + error.message);
        }
    }
    
    async unload() {
        console.log('[Calendar] Unloading module...');
        
        // Limpiar componentes
        if (this.calendarView) {
            this.calendarView.destroy();
        }
        
        // Limpiar filtros
        if (this.calendarFilters) {
            this.calendarFilters.destroy();
            this.calendarFilters = null;
        }
        
        // Limpiar event listeners
        eventBus.clear('calendar:*');
        
        this.container = null;
    }
    
    async loadTemplate() {
        const response = await fetch('/modules/calendar/templates/calendar.html');
        const html = await response.text();
        this.container.innerHTML = html;
    }
    
    async loadAvailableFiles() {
        try {
            // Usar la API existente de biblioteca
            const response = await apiClient.post('/biblioteca.php', {
                action: 'list_library'
            });
            
            if (response.success) {
                this.availableFiles = response.files.map(file => ({
                    value: file.filename,
                    label: file.filename,
                    duration: file.duration || 0,
                    size: file.size,
                    date: file.date
                }));
                
                console.log(`[Calendar] Loaded ${this.availableFiles.length} audio files`);
            }
        } catch (error) {
            console.error('[Calendar] Error loading files:', error);
            this.availableFiles = [];
        }
    }
    
    async initializeComponents() {
        // Inicializar vista de calendario
        const calendarContainer = this.container.querySelector('#calendar-container');
        this.calendarView = new CalendarView(calendarContainer, {
            onEventClick: (event) => this.handleEventClick(event),
            onDateClick: (date) => this.handleDateClick(date)
        });
        
        // Inicializar filtros si existe el contenedor
        const filtersContainer = this.container.querySelector('#calendar-filters');
        if (filtersContainer) {
            this.calendarFilters = new CalendarFilters(filtersContainer, this.calendarView, {
                // Ya no pasamos categories, el componente usa scheduleTypes internamente
            });
        }
    }
    
    async loadCalendarEvents() {
        try {
            this.showLoading(true);
            
            // Cargar schedules de audio desde el sistema funcional
            const audioSchedules = await this.calendarView.loadAudioSchedules();
            
            // Establecer eventos en el calendario
            this.calendarView.setEvents(audioSchedules);
            
            // Actualizar contador de resultados
            this.updateCalendarResultsCount(audioSchedules.length);
            
            console.log(`[Calendar] Loaded ${audioSchedules.length} audio schedules`);
            
        } catch (error) {
            console.error('[Calendar] Error loading events:', error);
            this.showError('Error al cargar eventos');
        } finally {
            this.showLoading(false);
        }
    }
    
    /**
     * Actualiza el contador de eventos en el calendario
     */
    updateCalendarResultsCount(eventsCount) {
        const counter = document.getElementById('resultsCount');
        if (counter) {
            if (eventsCount === 0) {
                counter.innerHTML = `
                    <div class="archive-empty">
                        <span>📅</span>
                        <span>No hay eventos programados en el calendario</span>
                    </div>
                `;
            } else {
                counter.innerHTML = `
                    Mostrando <span class="archive-count-number">${eventsCount}</span> 
                    evento${eventsCount !== 1 ? 's' : ''} programado${eventsCount !== 1 ? 's' : ''}
                `;
            }
        }
    }
    
    /**
     * Carga y muestra la lista de todos los schedules
     */
    async loadSchedulesList() {
        try {
            const container = document.getElementById('schedules-table-container');
            if (!container) return;
            
            // Mostrar loading
            container.innerHTML = '<div class="loading-state"><div class="loading-spinner"></div><p>Cargando programaciones...</p></div>';
            
            const response = await fetch('/api/audio-scheduler.php'
, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list', active_only: true })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displaySchedulesTable(data.schedules || []);
            } else {
                container.innerHTML = '<p class="error-message">Error al cargar programaciones</p>';
            }
        } catch (error) {
            console.error('[Calendar] Error loading schedules list:', error);
            const container = document.getElementById('schedules-table-container');
            if (container) {
                container.innerHTML = '<p class="error-message">Error al cargar programaciones</p>';
            }
        }
    }
    
    /**
     * Muestra la tabla de schedules
     */
    displaySchedulesTable(schedules) {
        const container = document.getElementById('schedules-table-container');
        if (!container) return;
        
        if (!schedules || schedules.length === 0) {
            container.innerHTML = `
                <div class="archive-list" style="padding: 3rem; text-align: center;">
                    <div class="archive-empty">
                        <div class="archive-empty-icon" style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                        <div style="color: var(--text-primary); font-size: 16px; font-weight: 500; margin-bottom: 8px;">
                            No hay programaciones activas configuradas
                        </div>
                        <div style="color: var(--text-secondary); font-size: 14px;">
                            Las programaciones aparecerán aquí cuando se creen desde "Mensajes Guardados"
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        
        // Crear tabla HTML con estética de Audio Archive
        let tableHTML = `
            <div class="archive-list">
                <!-- Header de la tabla -->
                <div class="archive-list-header" style="grid-template-columns: 50px 2fr 120px 150px 100px 120px;">
                    <div></div>
                    <div>Archivo</div>
                    <div>Tipo</div>
                    <div>Programación</div>
                    <div>Estado</div>
                    <div>Acciones</div>
                </div>
                <!-- Items de la lista -->
                <div class="archive-list-items">
        `;
        
        schedules.forEach(schedule => {
            const type = this.getScheduleTypeLabel(schedule);
            const timing = this.getScheduleTimingForTable(schedule);
            const status = schedule.is_active ? 
                '<span class="archive-status-badge archive-status-active" style="padding: 2px 8px; background: var(--primary); color: white; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 500;">Activo</span>' : 
                '<span class="archive-status-badge archive-status-inactive" style="padding: 2px 8px; background: var(--bg-tertiary); color: var(--text-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 500;">Inactivo</span>';
            
            // Título o nombre del archivo
            const displayName = schedule.title || schedule.filename || 'Sin archivo';
            
            tableHTML += `
                <div class="archive-list-item" style="grid-template-columns: 50px 2fr 120px 150px 100px 120px;">
                    <!-- Botón de preview -->
                    ${schedule.filename ? `
                    <button class="archive-btn-preview" onclick="window.calendarModule.previewAudio('${schedule.filename}', this)" 
                            title="Preview audio">
                        ▶
                    </button>
                    ` : `<div></div>`}
                    
                    <!-- Título del archivo -->
                    <div class="archive-item-title" title="${schedule.filename || ''}">
                        ${this.truncateText(displayName, 35)}
                    </div>
                    
                    <!-- Tipo -->
                    <div class="archive-item-voice">${type}</div>
                    
                    <!-- Programación -->
                    <div class="archive-item-date">${timing}</div>
                    
                    <!-- Estado -->
                    <div>${status}</div>
                    
                    <!-- Acciones -->
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        <button onclick="window.calendarModule.viewScheduleFromList(${schedule.id})" 
                                title="Ver detalles" style="
                                    width: 28px; height: 28px; 
                                    border-radius: var(--radius-sm); 
                                    border: 1px solid var(--border-color); 
                                    background: var(--bg-tertiary); 
                                    color: var(--text-secondary); 
                                    display: flex; align-items: center; justify-content: center; 
                                    cursor: pointer; transition: all 0.2s; font-size: 0.8rem;
                                "
                                onmouseover="this.style.background='var(--primary)'; this.style.color='white'"
                                onmouseout="this.style.background='var(--bg-tertiary)'; this.style.color='var(--text-secondary)'">
                            👁️
                        </button>
                        <button onclick="window.calendarModule.deleteScheduleFromList(${schedule.id})" 
                                title="Eliminar" style="
                                    width: 28px; height: 28px; 
                                    border-radius: var(--radius-sm); 
                                    border: 1px solid var(--border-color); 
                                    background: var(--bg-tertiary); 
                                    color: var(--text-secondary); 
                                    display: flex; align-items: center; justify-content: center; 
                                    cursor: pointer; transition: all 0.2s; font-size: 0.8rem;
                                "
                                onmouseover="this.style.background='#dc2626'; this.style.color='white'"
                                onmouseout="this.style.background='var(--bg-tertiary)'; this.style.color='var(--text-secondary)'">
                            🗑️
                        </button>
                    </div>
                </div>
            `;
        });
        
        tableHTML += `
                </div>
            </div>
            <div class="archive-results-count" style="
                padding: var(--space-md); 
                background: var(--bg-tertiary); 
                border-radius: var(--radius-md); 
                margin-top: var(--space-lg); 
                font-size: 0.875rem; 
                color: var(--text-secondary);
            ">
                Total: <span style="font-weight: 600; color: var(--text-primary);">${schedules.length}</span> programación${schedules.length !== 1 ? 'es' : ''} activa${schedules.length !== 1 ? 's' : ''}
            </div>
        `;
        
        container.innerHTML = tableHTML;
        
        // Hacer disponibles las funciones globalmente para onclick
        window.calendarModule = {
            viewScheduleFromList: (id) => this.viewScheduleFromList(id),
            deleteScheduleFromList: (id) => this.deleteScheduleFromList(id),
            previewAudio: (filename, buttonElement) => this.previewAudio(filename, buttonElement)
        };
    }
    
    /**
     * Obtiene el label del tipo de schedule
     */
    getScheduleTypeLabel(schedule) {
        const types = {
            'interval': '⏱️ Intervalo',
            'specific': '📅 Días específicos',
            'once': '1️⃣ Una vez'
        };
        return types[schedule.schedule_type] || '❓ Desconocido';
    }
    
    /**
     * Formatea el timing del schedule para la tabla
     */
    getScheduleTimingForTable(schedule) {
        try {
            switch(schedule.schedule_type) {
                case 'interval':
                    const h = parseInt(schedule.interval_hours) || 0;
                    const m = parseInt(schedule.interval_minutes) || 0;
                    if (h > 0 && m > 0) {
                        return `Cada ${h}h ${m}min`;
                    } else if (h > 0) {
                        return `Cada ${h} hora${h > 1 ? 's' : ''}`;
                    } else if (m > 0) {
                        return `Cada ${m} minuto${m > 1 ? 's' : ''}`;
                    }
                    return 'No configurado';
                    
                case 'specific':
                    // Mapeo de días en inglés a español
                    const dayMap = {
                        'monday': 'Lun', 'tuesday': 'Mar', 'wednesday': 'Mié',
                        'thursday': 'Jue', 'friday': 'Vie', 'saturday': 'Sáb', 'sunday': 'Dom'
                    };
                    
                    let days = [];
                    if (Array.isArray(schedule.schedule_days)) {
                        days = schedule.schedule_days.map(d => dayMap[d.toLowerCase()] || d);
                    } else if (schedule.schedule_days) {
                        // Intentar parsear si es string
                        try {
                            const parsed = JSON.parse(schedule.schedule_days);
                            if (Array.isArray(parsed)) {
                                days = parsed.map(d => dayMap[d.toLowerCase()] || d);
                            }
                        } catch(e) {
                            days = [schedule.schedule_days];
                        }
                    }
                    
                    const daysStr = days.length > 0 ? days.join(', ') : 'Sin días';
                    
                    // Obtener hora
                    let time = '00:00';
                    if (schedule.schedule_time) {
                        if (typeof schedule.schedule_time === 'string' && schedule.schedule_time.startsWith('[')) {
                            try {
                                const parsed = JSON.parse(schedule.schedule_time);
                                time = Array.isArray(parsed) ? parsed[0] : parsed;
                            } catch(e) {
                                time = schedule.schedule_time;
                            }
                        } else {
                            time = schedule.schedule_time;
                        }
                    }
                    
                    return `${daysStr} a las ${time}`;
                    
                case 'once':
                    const date = schedule.start_date ? 
                        new Date(schedule.start_date).toLocaleDateString('es-CL') : 
                        'Fecha no definida';
                    return date;
                    
                default:
                    return 'No configurado';
            }
        } catch (error) {
            console.error('[Calendar] Error formatting schedule timing:', error);
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
     * Ver detalles de un schedule desde la lista
     */
    viewScheduleFromList(scheduleId) {
        // Buscar el schedule en los datos
        fetch('/api/audio-scheduler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list' })
        })
        .then(response => response.json())
        .then(data => {
            const schedule = data.schedules.find(s => s.id === scheduleId);
            if (schedule) {
                // Convertir a formato esperado por el modal
                const eventData = {
                    id: `audio_schedule_${schedule.id}`,
                    type: 'audio_schedule',
                    scheduleId: schedule.id,
                    filename: schedule.filename,
                    title: schedule.title,
                    scheduleType: schedule.schedule_type,
                    intervalMinutes: schedule.interval_minutes,
                    intervalHours: schedule.interval_hours,
                    scheduleDays: schedule.schedule_days,
                    scheduleTime: schedule.schedule_time,
                    startDate: schedule.start_date,
                    endDate: schedule.end_date,
                    isActive: schedule.is_active,
                    createdAt: schedule.created_at
                };
                this.showScheduleInfoModal(eventData);
            }
        })
        .catch(error => {
            console.error('[Calendar] Error loading schedule details:', error);
            this.showError('Error al cargar detalles del schedule');
        });
    }
    
    /**
     * Eliminar schedule desde la lista
     */
    async deleteScheduleFromList(scheduleId) {
        await this.confirmDeleteSchedule(scheduleId, () => {
            // Refrescar tanto la lista como el calendario
            this.loadSchedulesList();
            this.loadCalendarEvents();
        });
    }
    
    /**
     * Preview de audio inline en la tabla
     */
    previewAudio(filename, buttonElement) {
        if (!filename) {
            this.showError('Archivo no disponible');
            return;
        }
        
        // Buscar si ya existe un player activo y pausarlo
        const existingPlayer = document.querySelector('.inline-audio-player');
        if (existingPlayer) {
            const audio = existingPlayer.querySelector('audio');
            if (audio) {
                audio.pause();
                audio.currentTime = 0;
            }
            existingPlayer.remove();
        }
        
        // Crear player inline
        const audioUrl = `/api/biblioteca.php?filename=${filename}`;
        const playerHTML = `
            <div class="inline-audio-player" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--bg-primary, #1e293b);
                border: 1px solid var(--border-color, rgba(255,255,255,0.1));
                border-radius: 8px;
                padding: 1rem;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 1000;
                max-width: 350px;
                color: var(--text-primary, #ffffff);
            ">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong style="font-size: 0.9rem;">🎵 ${filename}</strong>
                    <button onclick="this.parentElement.parentElement.remove()" style="
                        background: none;
                        border: none;
                        color: var(--text-secondary, #94a3b8);
                        cursor: pointer;
                        font-size: 1.2rem;
                        padding: 0;
                    ">&times;</button>
                </div>
                <audio controls autoplay style="width: 100%; height: 32px;">
                    <source src="${audioUrl}" type="audio/mpeg">
                    Tu navegador no soporta reproducción de audio.
                </audio>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', playerHTML);
        
        // Auto-cerrar después de 30 segundos si no se usa
        setTimeout(() => {
            const player = document.querySelector('.inline-audio-player');
            if (player) {
                const audio = player.querySelector('audio');
                if (audio && (audio.paused || audio.ended)) {
                    player.remove();
                }
            }
        }, 30000);
    }
    
    attachEventListeners() {
        // Event listeners removidos: create-event-btn y refresh-calendar-btn (ya no existen en el HTML)
        
        // Botón actualizar lista de schedules
        const refreshSchedulesBtn = this.container.querySelector('#refresh-schedules-btn');
        refreshSchedulesBtn?.addEventListener('click', () => {
            this.loadSchedulesList();
        });
        
        // Filtros de categoría removidos (ya no existen en el HTML)
        
        // Escuchar eventos del sistema de scheduling
        eventBus.on('library:file:added', () => {
            this.loadAvailableFiles();
        });
        
        // Escuchar cuando se crean/modifican schedules
        eventBus.on('schedule:created', () => {
            console.log('[Calendar] Schedule created, refreshing calendar...');
            this.loadCalendarEvents();
            this.loadSchedulesList();
        });
        
        eventBus.on('schedule:updated', () => {
            console.log('[Calendar] Schedule updated, refreshing calendar...');
            this.loadCalendarEvents();
            this.loadSchedulesList();
        });
        
        eventBus.on('schedule:deleted', () => {
            console.log('[Calendar] Schedule deleted, refreshing calendar...');
            this.loadCalendarEvents();
            this.loadSchedulesList();
        });
        
        // NUEVO: Escuchar cambios de categoría desde Campaign Library
        eventBus.on('schedule:category:updated', (data) => {
            console.log('[Calendar] Category updated for:', data.filename, '→', data.category);
            console.log('[Calendar] Refreshing calendar and schedules list...');
            this.loadCalendarEvents();
            this.loadSchedulesList();
        });
    }
    
    handleEventClick(event) {
        console.log('[Calendar] Event clicked:', event);
        console.log('[Calendar] Event full object:', JSON.stringify(event, null, 2));
        
        // Verificar múltiples formas de detectar un audio schedule
        const isAudioSchedule = 
            (event && event.type === 'audio_schedule') ||
            (event && event.id && String(event.id).includes('audio_schedule')) ||
            (event && event.scheduleId !== undefined) ||
            (event && event.filename); // También verificar si tiene filename
        
        console.log('[Calendar] Is audio schedule?', isAudioSchedule);
        console.log('[Calendar] Event type:', event.type);
        console.log('[Calendar] Event id:', event.id);
        console.log('[Calendar] Has scheduleId?', event.scheduleId !== undefined);
        console.log('[Calendar] Has filename?', event.filename !== undefined);
        
        if (isAudioSchedule) {
            console.log('[Calendar] Audio schedule detected, opening modal');
            
            // Asegurarnos de que el modal se abra
            try {
                this.showScheduleInfoModal(event);
            } catch (error) {
                console.error('[Calendar] Error showing modal:', error);
                this.showError('Error al mostrar información del schedule: ' + error.message);
            }
        } else {
            console.log('[Calendar] Not an audio schedule - ignoring click');
        }
    }
    
    /**
     * Muestra modal con información del schedule y opciones de gestión
     */
    showScheduleInfoModal(scheduleData) {
        console.log('[Calendar] Showing schedule info modal:', scheduleData);
        
        // Formatear información del schedule
        const scheduleInfo = this.formatScheduleInfo(scheduleData);
        
        // Crear HTML del modal
        const modalHTML = this.createScheduleModalHTML(scheduleData, scheduleInfo);
        
        // Insertar modal en el DOM
        const existingModal = document.getElementById('schedule-info-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Obtener referencias a elementos del modal
        const modal = document.getElementById('schedule-info-modal');
        const overlay = modal.querySelector('.modal-overlay');
        const closeBtn = modal.querySelector('.close-modal');
        const deleteBtn = modal.querySelector('.delete-schedule-btn');
        const editBtn = modal.querySelector('.edit-schedule-btn');
        const audioElement = modal.querySelector('audio');
        
        // Event listeners
        const closeModal = () => {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
        };
        
        overlay.addEventListener('click', closeModal);
        closeBtn.addEventListener('click', closeModal);
        
        // Botón eliminar
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                this.confirmDeleteSchedule(scheduleData.scheduleId, closeModal);
            });
        }
        
        // Botón editar
        if (editBtn) {
            editBtn.addEventListener('click', () => {
                closeModal();
                this.editSchedule(scheduleData);
            });
        }
        
        // Manejar error de carga de audio
        if (audioElement) {
            audioElement.addEventListener('error', () => {
                const audioContainer = modal.querySelector('.audio-preview');
                audioContainer.innerHTML = '<p class="audio-error">⚠️ No se puede cargar el audio</p>';
            });
        }
        
        // Mostrar modal con animación
        console.log('[Calendar] Modal element:', modal);
        console.log('[Calendar] Adding active class...');
        
        requestAnimationFrame(() => {
            modal.classList.add('active');
            console.log('[Calendar] Active class added, modal should be visible now');
        });
    }
    
    /**
     * Formatea la información del schedule para mostrar
     */
    formatScheduleInfo(scheduleData) {
        const { scheduleType, intervalMinutes, intervalHours, scheduleDays, scheduleTime, startDate, endDate } = scheduleData;
        
        let info = '';
        
        switch(scheduleType) {
            case 'interval':
                const hours = intervalHours || 0;
                const minutes = intervalMinutes || 0;
                if (hours > 0 && minutes > 0) {
                    info = `Cada ${hours} hora${hours > 1 ? 's' : ''} y ${minutes} minuto${minutes > 1 ? 's' : ''}`;
                } else if (hours > 0) {
                    info = `Cada ${hours} hora${hours > 1 ? 's' : ''}`;
                } else {
                    info = `Cada ${minutes} minuto${minutes > 1 ? 's' : ''}`;
                }
                break;
                
            case 'specific':
                const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                // Asegurar que scheduleDays sea string
                const daysString = scheduleDays ? String(scheduleDays) : '';
                const dayNames = daysString ? daysString.split(',')
                    .map(d => days[parseInt(d.trim())])
                    .filter(Boolean)
                    .join(', ') : 'Días no especificados';
                info = `${dayNames} a las ${scheduleTime || '00:00'}`;
                break;
                
            case 'once':
                const dateStr = startDate ? new Date(startDate).toLocaleDateString('es-CL') : 'Fecha no especificada';
                info = `Una vez el ${dateStr} a las ${scheduleTime || '00:00'}`;
                break;
                
            default:
                info = 'Programación desconocida';
        }
        
        // Agregar periodo si existe
        if (startDate || endDate) {
            info += '<br><small>📅 Periodo: ';
            if (startDate) info += `Desde ${new Date(startDate).toLocaleDateString('es-CL')}`;
            if (endDate) info += ` hasta ${new Date(endDate).toLocaleDateString('es-CL')}`;
            info += '</small>';
        }
        
        return info;
    }
    
    /**
     * Crea el HTML del modal de información
     */
    createScheduleModalHTML(scheduleData, scheduleInfo) {
        const { filename, scheduleId, isActive, createdAt } = scheduleData;
        const title = scheduleData.title || filename || 'Sin título';
        
        // URL del audio
        const audioUrl = `/api/biblioteca.php?filename=${filename}`;
        
        // Formatear fecha de creación
        const createdDate = createdAt ? new Date(createdAt).toLocaleString('es-CL') : 'Fecha desconocida';
        
        return `
            <div id="schedule-info-modal" class="schedule-info-modal">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>🎵 ${title}</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="schedule-details">
                            <div class="detail-item">
                                <strong>📁 Archivo:</strong> ${filename || 'No especificado'}
                            </div>
                            
                            <div class="detail-item">
                                <strong>📅 Programación:</strong>
                                <div class="schedule-timing">${scheduleInfo}</div>
                            </div>
                            
                            <div class="detail-item">
                                <strong>📊 Estado:</strong>
                                <span class="status-badge ${isActive ? 'active' : 'inactive'}">
                                    ${isActive ? '✅ Activo' : '⏸️ Inactivo'}
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>🕐 Creado:</strong> ${createdDate}
                            </div>
                            
                            ${filename ? `
                            <div class="detail-item audio-section">
                                <strong>🎧 Preview:</strong>
                                <div class="audio-preview">
                                    <audio controls preload="metadata">
                                        <source src="${audioUrl}" type="audio/mpeg">
                                        Tu navegador no soporta reproducción de audio.
                                    </audio>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button class="btn btn-secondary close-modal">Cerrar</button>
                        <button class="btn btn-primary edit-schedule-btn">
                            ✏️ Editar
                        </button>
                        <button class="btn btn-danger delete-schedule-btn">
                            🗑️ Eliminar
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Confirma y elimina un schedule
     */
    async confirmDeleteSchedule(scheduleId, onSuccess) {
        const confirmed = confirm('¿Estás seguro de que quieres eliminar este schedule?\n\nEsta acción no se puede deshacer.');
        
        if (!confirmed) return;
        
        try {
            console.log('[Calendar] Deleting schedule:', scheduleId);
            
            const response = await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: scheduleId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Schedule eliminado correctamente');
                
                // Cerrar modal
                if (onSuccess) onSuccess();
                
                // Refrescar calendario
                this.loadCalendarEvents();
                
                // Emitir evento
                eventBus.emit('schedule:deleted', { scheduleId });
            } else {
                throw new Error(result.error || 'Error al eliminar');
            }
            
        } catch (error) {
            console.error('[Calendar] Error deleting schedule:', error);
            this.showError('Error al eliminar el schedule: ' + error.message);
        }
    }
    
    /**
     * Abre el modal de edición para un schedule existente
     */
    async editSchedule(scheduleData) {
        try {
            console.log('[Calendar] Opening schedule for editing:', scheduleData);
            
            // Por ahora, mostrar mensaje informativo
            // TODO: Implementar edición completa cuando schedule-modal.js soporte modo edición
            this.showError('La edición de schedules se implementará próximamente. Por ahora, puedes eliminar y crear uno nuevo.');
            
        } catch (error) {
            console.error('[Calendar] Error opening edit modal:', error);
            this.showError('Error al abrir el editor');
        }
    }
    
    handleDateClick(date) {
        console.log('[Calendar] Date clicked:', date);
        // Por ahora, redirigir a mensajes guardados para crear schedules
        this.showSuccess('Ve a "Mensajes Guardados" para programar un nuevo audio');
        
        // Opcional: navegar automáticamente
        setTimeout(() => {
            eventBus.emit('navigate', { module: 'campaign-library' });
        }, 1500);
    }
    
    /**
     * Abre el modal de scheduling para editar un schedule existente
     * @param {string} filename - Nombre del archivo de audio
     * @param {string} title - Título del schedule
     * @param {number} scheduleId - ID del schedule (opcional, para edición)
     */
    async openScheduleModal(filename, title, scheduleId = null) {
        try {
            console.log('[Calendar] Opening schedule modal:', { filename, title, scheduleId });
            
            // Cargar el modal dinámicamente desde campaign-library
            if (!window.ScheduleModal) {
                const module = await import('../campaign-library/schedule-modal.js');
                window.ScheduleModal = module.ScheduleModal || module.default;
            }
            
            // Crear instancia del modal
            const modal = new window.ScheduleModal();
            
            if (scheduleId) {
                // TODO: Para edición, cargar datos existentes del schedule
                console.log('[Calendar] TODO: Load existing schedule data for editing');
                // Por ahora, mostrar modal vacío con aviso
                modal.show(filename, title);
                this.showError('Edición desde calendario próximamente. Usa "Mensajes Guardados" para editar.');
            } else {
                // Para nuevo schedule
                modal.show(filename, title);
            }
            
            // Escuchar cuando se guarde para refrescar calendario
            window.scheduleModal = modal;
            
        } catch (error) {
            console.error('[Calendar] Error loading schedule modal:', error);
            this.showError('Error al abrir editor. Usa "Mensajes Guardados" para programar.');
        }
    }
    
    // Función updateCategoryFilters removida (filtros ya no existen)
    
    // Método getCategoryColor removido - ya no se usan categorías
    
    // UI Helpers
    showLoading(show) {
        const loader = this.container.querySelector('.calendar-loading');
        if (loader) {
            loader.style.display = show ? 'flex' : 'none';
        }
    }
    
    showSuccess(message) {
        eventBus.emit('ui:notification', { 
            message, 
            type: 'success' 
        });
    }
    
    showError(message) {
        eventBus.emit('ui:notification', { 
            message, 
            type: 'error' 
        });
    }
}