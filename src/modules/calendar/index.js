/**
 * Calendar Module - Sistema de Programación de Anuncios (Refactorizado)
 * @module CalendarModule
 * Version: 3.0 - Arquitectura modular con componentes separados
 */

console.log('[CalendarModule] Loading version 3.0 - Refactorizado con componentes modulares');

import { eventBus } from '../../core/event-bus.js';
import { apiClient } from '../../core/api-client.js';
import { CalendarView } from './components/calendar-view.js';
import { CalendarFilters } from './components/calendar-filters.js';

// Nuevos servicios y componentes
import { ScheduleService } from './services/schedule-service.js';
import { CalendarStateManager } from './services/calendar-state.js';
import { SchedulesList } from './components/schedules-list.js';
import { CalendarLoader } from './components/calendar-loader.js';
import { ScheduleActions } from './components/schedule-actions.js';

export default class CalendarModule {
    constructor() {
        // Propiedades públicas (MANTENER para compatibilidad)
        this.name = 'calendar';
        this.container = null;
        
        // Servicios (Fase 1 - YA EXISTEN)
        this.scheduleService = new ScheduleService();
        this.stateManager = new CalendarStateManager();
        
        // Componentes UI (Fase 2 - NUEVOS)
        this.schedulesList = null;
        this.calendarLoader = null;
        this.scheduleActions = null;
        
        // Componentes existentes
        this.calendarView = null;
        this.calendarFilters = null;
        
        // Cache de archivos disponibles (para compatibilidad)
        this.availableFiles = [];
        
        // Referencias a métodos antiguos para compatibilidad temporal
        this._legacyMethods = {};
    }
    
    /**
     * Obtiene el nombre del módulo
     */
    getName() {
        return this.name;
    }
    
    /**
     * Carga el módulo en el contenedor especificado
     */
    async load(container) {
        console.log('[Calendar] Loading module...');
        this.container = container;
        
        try {
            // Cargar template HTML
            await this.loadTemplate();
            
            // Inicializar componentes
            await this.initializeComponents();
            
            // Configurar event listeners
            this.attachEventListeners();
            
            // Configurar funciones globales para compatibilidad
            this.setupGlobalFunctions();
            
            // Cargar datos iniciales
            await this.loadInitialData();
            
            // Emitir evento de carga completa
            eventBus.emit('calendar:loaded');
            
            console.log('[Calendar] Module loaded successfully');
            return true;
            
        } catch (error) {
            console.error('[Calendar] Failed to load module:', error);
            this.showError('Error al cargar el módulo de calendario');
            return false;
        }
    }
    
    /**
     * Carga el template HTML del calendario
     */
    async loadTemplate() {
        try {
            const response = await fetch('/modules/calendar/templates/calendar.html');
            const html = await response.text();
            this.container.innerHTML = html;
            console.log('[Calendar] Template loaded');
        } catch (error) {
            console.error('[Calendar] Error loading template:', error);
            throw new Error('Failed to load calendar template');
        }
    }
    
    /**
     * Inicializa todos los componentes
     */
    async initializeComponents() {
        console.log('[Calendar] Initializing components...');
        
        try {
            // Inicializar loader primero
            this.calendarLoader = new CalendarLoader(this.container, eventBus);
            this.calendarLoader.init();
            
            // Inicializar servicios de acciones
            this.scheduleActions = new ScheduleActions(
                this.scheduleService,
                this.calendarLoader,
                eventBus
            );
            this.scheduleActions.init();
            
            // Inicializar lista de schedules
            this.schedulesList = new SchedulesList(
                this.container,
                this.scheduleService,
                eventBus
            );
            await this.schedulesList.init();
        } catch (error) {
            console.error('[Calendar] Error initializing core components:', error);
            throw error; // Re-throw porque estos son componentes críticos
        }
        
        // Inicializar vista de calendario
        const calendarContainer = document.getElementById('calendar-container');
        if (calendarContainer) {
            try {
                // CalendarView maneja la inicialización internamente en su constructor
                this.calendarView = new CalendarView(calendarContainer);
                console.log('[Calendar] CalendarView created successfully');
            } catch (error) {
                console.error('[Calendar] Error creating CalendarView:', error);
                // Continuar sin calendario visual, pero con la tabla de schedules
            }
        }
        
        // Inicializar filtros
        const filtersContainer = document.getElementById('calendar-filters');
        if (filtersContainer) {
            try {
                // CalendarFilters llama a init() internamente en su constructor
                this.calendarFilters = new CalendarFilters(filtersContainer, this.calendarView);
                console.log('[Calendar] CalendarFilters created successfully');
            } catch (error) {
                console.error('[Calendar] Error creating CalendarFilters:', error);
                // Continuar sin filtros
            }
        }
        
        // Configurar callbacks entre componentes
        this.setupComponentCallbacks();
        
        console.log('[Calendar] Components initialized');
    }
    
    /**
     * Configura callbacks entre componentes
     */
    setupComponentCallbacks() {
        // Callbacks para actualización de lista después de acciones
        this.scheduleActions.onScheduleUpdated = async () => {
            await this.loadSchedulesList();
            await this.loadCalendarEvents();
        };
        
        this.scheduleActions.onScheduleDeleted = async () => {
            await this.loadSchedulesList();
            await this.loadCalendarEvents();
        };
        
        // Callbacks de la lista de schedules
        this.schedulesList.onToggleStatus = (id, activate) => {
            return this.scheduleActions.toggleScheduleStatus(id, activate);
        };
        
        this.schedulesList.onDelete = (id) => {
            return this.scheduleActions.deleteSchedule(id);
        };
        
        this.schedulesList.onEdit = (id) => {
            return this.scheduleActions.editSchedule(id);
        };
        
        this.schedulesList.onPreviewAudio = (filename, button) => {
            return this.scheduleActions.previewAudio(filename, button);
        };
    }
    
    /**
     * Carga datos iniciales
     */
    async loadInitialData() {
        console.log('[Calendar] Loading initial data...');
        
        // Mostrar loading
        this.calendarLoader.showLoading('Cargando calendario...');
        
        try {
            // Cargar en paralelo para mejor performance
            const promises = [
                this.loadAvailableFiles(),
                this.loadSchedulesList(),
                this.loadCalendarEvents()
            ];
            
            await Promise.all(promises);
            
            console.log('[Calendar] Initial data loaded');
        } catch (error) {
            console.error('[Calendar] Error loading initial data:', error);
            // No fallar completamente, continuar con lo que se pudo cargar
        } finally {
            this.calendarLoader.hideLoading();
        }
    }
    
    /**
     * Carga la lista de archivos disponibles
     */
    async loadAvailableFiles() {
        try {
            this.availableFiles = await this.stateManager.loadAvailableFiles();
            console.log(`[Calendar] Loaded ${this.availableFiles.length} available files`);
            return this.availableFiles;
        } catch (error) {
            console.error('[Calendar] Error loading available files:', error);
            return [];
        }
    }
    
    /**
     * Carga la lista de schedules
     */
    async loadSchedulesList() {
        try {
            const schedules = await this.schedulesList.load();
            console.log(`[Calendar] Loaded ${schedules.length} schedules`);
            return schedules;
        } catch (error) {
            console.error('[Calendar] Error loading schedules list:', error);
            return [];
        }
    }
    
    /**
     * Carga eventos del calendario
     */
    async loadCalendarEvents() {
        if (!this.calendarView) {
            console.log('[Calendar] CalendarView not available, skipping calendar events');
            return [];
        }
        
        try {
            // Obtener schedules del servicio
            const schedules = await this.scheduleService.loadSchedulesList();
            
            // Convertir a eventos para FullCalendar
            const events = this.convertSchedulesToEvents(schedules);
            
            // CalendarView ahora maneja eventos pendientes internamente
            this.calendarView.setEvents(events);
            console.log(`[Calendar] Loaded ${events.length} calendar events`);
            
            return events;
        } catch (error) {
            console.error('[Calendar] Error loading calendar events:', error);
            return [];
        }
    }
    
    /**
     * Obtiene los colores para una categoría
     */
    getCategoryColors(category) {
        const colors = {
            'ofertas': { bg: '#10b981', border: '#059669' },        // Verde (OK)
            'eventos': { bg: '#8b5cf6', border: '#7c3aed' },        // Púrpura (como en badges)
            'informacion': { bg: '#0891b2', border: '#06b6d4' },    // Cyan (OK)
            'servicios': { bg: '#f59e0b', border: '#d97706' },      // Naranja (como en badges)
            'horarios': { bg: '#6366f1', border: '#4f46e5' },       // Azul índigo (como en badges)
            'emergencias': { bg: '#dc2626', border: '#b91c1c' },    // Rojo (OK)
            'sin_categoria': { bg: '#6b7280', border: '#4b5563' }   // Gris (OK)
        };
        return colors[category] || colors['sin_categoria'];
    }
    
    /**
     * Convierte schedules a formato de eventos para FullCalendar
     */
    convertSchedulesToEvents(schedules) {
        console.log('[Calendar] Converting schedules to events:', schedules);
        const events = [];
        
        schedules.forEach(schedule => {
            console.log('[Calendar] Processing schedule:', {
                id: schedule.id,
                type: schedule.schedule_type,
                title: schedule.title,
                filename: schedule.filename,
                is_active: schedule.is_active,
                schedule_time: schedule.schedule_time,
                schedule_days: schedule.schedule_days,
                start_date: schedule.start_date,
                category: schedule.category
            });
            
            // Obtener colores según categoría
            const categoryColors = this.getCategoryColors(schedule.category || 'sin_categoria');
            
            // Lógica de conversión según el tipo de schedule
            const baseEvent = {
                id: `schedule_${schedule.id}`,
                title: schedule.title || schedule.filename || 'Sin título',
                extendedProps: {
                    type: 'audio_schedule', // Necesario para los filtros
                    scheduleId: schedule.id,
                    filename: schedule.filename,
                    category: schedule.category || 'sin_categoria',
                    isActive: schedule.is_active
                }
            };
            
            // Agregar eventos según el tipo
            switch(schedule.schedule_type) {
                case 'daily':
                    // Eventos diarios
                    baseEvent.rrule = {
                        freq: 'daily',
                        dtstart: schedule.start_date || new Date().toISOString()
                    };
                    console.log('[Calendar] Created daily event:', baseEvent);
                    events.push(baseEvent);
                    break;
                    
                case 'weekly':
                    // Eventos semanales
                    try {
                        const days = JSON.parse(schedule.schedule_days || '[]');
                        if (days.length > 0) {
                            baseEvent.daysOfWeek = days.map(day => this.getDayNumber(day));
                            baseEvent.startTime = schedule.schedule_time || '00:00';
                            console.log('[Calendar] Created weekly event:', baseEvent);
                            events.push(baseEvent);
                        }
                    } catch(e) {
                        console.error('[Calendar] Error parsing weekly days:', e);
                    }
                    break;
                    
                case 'once':
                    // Evento único
                    baseEvent.start = schedule.start_date;
                    baseEvent.allDay = false;
                    console.log('[Calendar] Created once event:', baseEvent);
                    events.push(baseEvent);
                    break;
                    
                case 'interval':
                    // Para intervalos, crear un evento simple con fecha de inicio
                    // FullCalendar no maneja bien intervalos personalizados con rrule
                    const now = new Date();
                    baseEvent.start = schedule.start_date || now.toISOString();
                    baseEvent.title += ` (Cada ${schedule.interval_hours || 0}h ${schedule.interval_minutes || 0}m)`;
                    baseEvent.backgroundColor = categoryColors.bg;
                    baseEvent.borderColor = categoryColors.border;
                    console.log('[Calendar] Created interval event:', baseEvent);
                    events.push(baseEvent);
                    break;
                    
                case 'specific':
                    // Tipo específico con días y hora
                    try {
                        const days = typeof schedule.schedule_days === 'string' ? 
                                    JSON.parse(schedule.schedule_days) : 
                                    schedule.schedule_days || [];
                        const time = schedule.schedule_time || '00:00';
                        
                        if (days.length > 0) {
                            // Crear evento recurrente semanal
                            baseEvent.daysOfWeek = days.map(day => this.getDayNumber(day));
                            baseEvent.startTime = time;
                            baseEvent.backgroundColor = categoryColors.bg;
                            baseEvent.borderColor = categoryColors.border;
                            console.log('[Calendar] Created specific event:', baseEvent);
                            events.push(baseEvent);
                        } else {
                            // Si no hay días, crear evento único
                            baseEvent.start = schedule.start_date || new Date().toISOString();
                            baseEvent.backgroundColor = '#3b82f6';
                            baseEvent.borderColor = '#2563eb';
                            console.log('[Calendar] Created specific event (no days):', baseEvent);
                            events.push(baseEvent);
                        }
                    } catch(e) {
                        console.error('[Calendar] Error parsing specific schedule:', e);
                    }
                    break;
                    
                default:
                    console.warn('[Calendar] Unknown schedule type:', schedule.schedule_type);
                    // Crear evento por defecto
                    baseEvent.start = schedule.start_date || new Date().toISOString();
                    baseEvent.backgroundColor = '#6b7280'; // Gris para desconocidos
                    baseEvent.borderColor = '#4b5563';
                    events.push(baseEvent);
                    break;
            }
        });
        
        console.log('[Calendar] Total events created:', events.length, events);
        return events;
    }
    
    /**
     * Convierte nombre de día a número
     */
    getDayNumber(dayName) {
        const days = {
            'sunday': 0, 'monday': 1, 'tuesday': 2, 'wednesday': 3,
            'thursday': 4, 'friday': 5, 'saturday': 6
        };
        return days[dayName.toLowerCase()] || 0;
    }
    
    /**
     * Configura los event listeners
     */
    attachEventListeners() {
        // Botón actualizar lista de schedules
        const refreshSchedulesBtn = this.container.querySelector('#refresh-schedules-btn');
        refreshSchedulesBtn?.addEventListener('click', () => {
            this.loadSchedulesList();
        });
        
        // Escuchar eventos del sistema
        eventBus.on('library:file:added', () => {
            this.loadAvailableFiles();
        });
        
        eventBus.on('schedule:created', () => {
            this.loadSchedulesList();
            this.loadCalendarEvents();
        });
        
        eventBus.on('schedule:updated', () => {
            this.loadSchedulesList();
            this.loadCalendarEvents();
        });
        
        eventBus.on('schedule:deleted', () => {
            this.loadSchedulesList();
            this.loadCalendarEvents();
        });
        
        // Escuchar cambios de categoría desde Campaign
        eventBus.on('schedule:category:updated', async (data) => {
            console.log('[Calendar] Category updated event received:', data);
            await this.loadSchedulesList();
            await this.loadCalendarEvents();
        });
        
        // Configurar listener de filtros si existe
        if (this.calendarFilters && this.calendarView) {
            this.calendarFilters.onFilterChange = (filters) => {
                this.applyFilters(filters);
            };
        }
    }
    
    /**
     * Configura funciones globales para compatibilidad
     */
    setupGlobalFunctions() {
        // Mantener compatibilidad con window.calendarModule
        window.calendarModule = {
            // Funciones usadas por onclick handlers en la tabla
            toggleScheduleStatus: (id, activate) => {
                return this.toggleScheduleStatus(id, activate);
            },
            deleteScheduleFromList: (id) => {
                return this.deleteScheduleFromList(id);
            },
            editSchedule: (id) => {
                return this.editSchedule(id);
            },
            previewAudio: (filename, button) => {
                return this.previewAudio(filename, button);
            },
            viewScheduleFromList: (id) => {
                return this.viewScheduleFromList(id);
            },
            // Funciones de debug
            testAddEvent: () => {
                console.log('[TEST] Adding test event to calendar');
                if (this.calendarView && this.calendarView.calendar) {
                    const testEvent = {
                        id: 'test_' + Date.now(),
                        title: 'Evento de Prueba',
                        start: new Date().toISOString(),
                        backgroundColor: '#10b981',
                        borderColor: '#059669'
                    };
                    console.log('[TEST] Test event:', testEvent);
                    const result = this.calendarView.calendar.addEvent(testEvent);
                    console.log('[TEST] Add result:', result);
                    console.log('[TEST] All events:', this.calendarView.calendar.getEvents());
                } else {
                    console.error('[TEST] Calendar not available');
                }
            },
            getCalendarState: () => {
                return {
                    calendarView: !!this.calendarView,
                    calendar: !!(this.calendarView && this.calendarView.calendar),
                    events: this.calendarView && this.calendarView.calendar ? 
                            this.calendarView.calendar.getEvents().length : 0
                };
            },
            reloadEvents: () => {
                console.log('[TEST] Manually reloading events');
                return this.loadCalendarEvents();
            }
        };
        
        console.log('[Calendar] Global functions configured');
    }
    
    // ========================================
    // MÉTODOS PUENTE PARA COMPATIBILIDAD
    // ========================================
    
    /**
     * Toggle estado de schedule (compatibilidad)
     */
    async toggleScheduleStatus(scheduleId, activate) {
        return this.scheduleActions.toggleScheduleStatus(scheduleId, activate);
    }
    
    /**
     * Eliminar schedule (compatibilidad)
     */
    async deleteScheduleFromList(scheduleId) {
        await this.confirmDeleteSchedule(scheduleId, () => {
            this.loadSchedulesList();
            this.loadCalendarEvents();
        });
    }
    
    /**
     * Confirmar eliminación (compatibilidad)
     */
    async confirmDeleteSchedule(scheduleId, onSuccess) {
        const deleted = await this.scheduleActions.deleteSchedule(scheduleId);
        if (deleted && onSuccess) {
            onSuccess();
        }
    }
    
    /**
     * Editar schedule (compatibilidad)
     */
    editSchedule(scheduleId) {
        this.scheduleActions.editSchedule(scheduleId);
    }
    
    /**
     * Preview audio (compatibilidad)
     */
    previewAudio(filename, button) {
        this.scheduleActions.previewAudio(filename, button);
    }
    
    /**
     * Ver detalles de schedule (compatibilidad)
     */
    viewScheduleFromList(scheduleId) {
        this.scheduleActions.viewScheduleDetails(scheduleId);
    }
    
    /**
     * Aplica filtros al calendario
     */
    applyFilters(filters) {
        if (!this.calendarView) return;
        
        // Aplicar filtros a la vista del calendario
        const events = this.calendarView.getEvents();
        events.forEach(event => {
            const category = event.extendedProps?.category || 'sin_categoria';
            const isVisible = !filters || filters[category] !== false;
            event.setProp('display', isVisible ? 'auto' : 'none');
        });
    }
    
    /**
     * Muestra notificación de éxito
     */
    showSuccess(message) {
        this.calendarLoader.showSuccess(message);
    }
    
    /**
     * Muestra notificación de error
     */
    showError(message) {
        this.calendarLoader.showError(message);
    }
    
    /**
     * Muestra notificación informativa
     */
    showNotification(message, type = 'info') {
        this.calendarLoader.showNotification(message, type);
    }
    
    /**
     * Descarga el módulo
     */
    async unload() {
        console.log('[Calendar] Unloading module...');
        
        // Limpiar event listeners
        eventBus.off('library:file:added');
        eventBus.off('schedule:created');
        eventBus.off('schedule:updated');
        eventBus.off('schedule:deleted');
        eventBus.off('schedule:category:updated');
        
        // Destruir componentes
        if (this.calendarView) {
            this.calendarView.destroy();
            this.calendarView = null;
        }
        
        if (this.calendarFilters) {
            this.calendarFilters.destroy();
            this.calendarFilters = null;
        }
        
        if (this.schedulesList) {
            this.schedulesList.destroy();
            this.schedulesList = null;
        }
        
        if (this.scheduleActions) {
            this.scheduleActions.destroy();
            this.scheduleActions = null;
        }
        
        if (this.calendarLoader) {
            this.calendarLoader.destroy();
            this.calendarLoader = null;
        }
        
        // Limpiar funciones globales
        if (window.calendarModule) {
            delete window.calendarModule;
        }
        
        // Limpiar contenedor
        if (this.container) {
            this.container.innerHTML = '';
            this.container = null;
        }
        
        console.log('[Calendar] Module unloaded');
    }
}

console.log('[CalendarModule] Module definition complete - Ready for use');