/**
 * Calendar View Component - FullCalendar Wrapper
 * @module CalendarView v2.4 - Con colores por categoría
 * @modified 2024-11-28 - Claude - Aplicar colores según categoría del contenido
 */

// Constantes globales
const DAY_NAME_TO_NUMBER = {
    'sunday': 0, 'monday': 1, 'tuesday': 2, 'wednesday': 3,
    'thursday': 4, 'friday': 5, 'saturday': 6
};

// NUEVO: Definición de colores por categoría (7 categorías según test)
const CATEGORY_COLORS = {
    'ofertas': { 
        bg: '#10b981',  // Verde (OK - matches badges)
        border: '#059669',
        text: '#ffffff',
        emoji: '🛒'
    },
    'eventos': { 
        bg: '#8b5cf6',  // Púrpura (como en badges)
        border: '#7c3aed',
        text: '#ffffff',
        emoji: '🎉'
    },
    'informacion': { 
        bg: '#0891b2',  // Cyan (como en badges)
        border: '#06b6d4',
        text: '#ffffff',
        emoji: 'ℹ️'
    },
    'servicios': { 
        bg: '#f59e0b',  // Naranja (como en badges)
        border: '#d97706',
        text: '#ffffff',
        emoji: '🛎️'
    },
    'horarios': { 
        bg: '#6366f1',  // Azul índigo (como en badges)
        border: '#4f46e5',
        text: '#ffffff',
        emoji: '🕐'
    },
    'emergencias': { 
        bg: '#dc2626',  // Rojo (como en badges)
        border: '#b91c1c',
        text: '#ffffff',
        emoji: '🚨'
    },
    'sin_categoria': { 
        bg: '#6b7280', 
        border: '#4b5563',
        text: '#ffffff',
        emoji: '📁'
    },
    'general': { // Alias para sin_categoria
        bg: '#6b7280', 
        border: '#4b5563',
        text: '#ffffff',
        emoji: '📢'
    }
};

// Función auxiliar para parsear schedule_time
function parseScheduleTime(scheduleTime) {
    let timeString = scheduleTime;
    
    if (typeof timeString === 'string' && timeString.charAt(0) === '[') {
        try {
            const parsed = JSON.parse(timeString);
            timeString = Array.isArray(parsed) ? parsed[0] : parsed;
        } catch(e) {
            // Mantener como está si no se puede parsear
        }
    } else if (Array.isArray(timeString)) {
        timeString = timeString[0];
    }
    
    return String(timeString);
}

export class CalendarView {
    constructor(container, options = {}) {
        this.container = container;
        this.options = options;
        this.calendar = null;
        this.currentView = 'dayGridMonth';
        this.activeCategories = ['ofertas', 'horarios', 'eventos', 'emergencias', 'servicios', 'seguridad'];
        this.pendingEvents = null; // Para guardar eventos si llegan antes de que esté listo
        
        if (typeof FullCalendar === 'undefined') {
            this.loadFullCalendar().then(() => this.initialize());
        } else {
            this.initialize();
        }
    }
    
    async loadFullCalendar() {
        const cssLink = document.createElement('link');
        cssLink.rel = 'stylesheet';
        cssLink.href = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css';
        document.head.appendChild(cssLink);
        
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    initialize() {
        this.calendar = new FullCalendar.Calendar(this.container, {
            initialView: this.currentView,
            locale: 'es',
            firstDay: 1, // Empezar la semana en lunes (1 = lunes, 0 = domingo)
            timeZone: 'America/Santiago',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            views: {
                dayGridMonth: { buttonText: 'Mes' },
                timeGridWeek: { buttonText: 'Semana' },
                timeGridDay: { buttonText: 'Día' },
                listWeek: { buttonText: 'Lista' }
            },
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            slotMinTime: '00:00:00',
            slotMaxTime: '24:00:00',
            slotDuration: '00:15:00',
            slotLabelInterval: '01:00',
            events: [],
            eventClick: (info) => {
                const result = this.handleEventClick(info);
                if (result === false) return false;
            },
            dateClick: (info) => this.handleDateClick(info),
            eventDidMount: (info) => this.customizeEvent(info),
            editable: false,
            eventInteractive: true,
            navLinks: false,
            dayMaxEvents: true,
            nowIndicator: true,
            businessHours: {
                daysOfWeek: [0, 1, 2, 3, 4, 5, 6],
                startTime: '10:00',
                endTime: '22:00'
            }
        });
        
        this.calendar.render();
        this.attachViewButtons();
        
        // Aplicar estilos del header después del render
        this.applyHeaderStyles();
        
        // Si hay eventos pendientes, aplicarlos ahora
        if (this.pendingEvents) {
            console.log('[CalendarView] Applying pending events:', this.pendingEvents.length);
            this.setEvents(this.pendingEvents);
            this.pendingEvents = null;
        }
    }
    
    /**
     * Aplica estilos críticos para el header del calendario
     * @private
     */
    applyHeaderStyles() {
        // Aplicar estilos directamente a los elementos del DOM
        setTimeout(() => {
            // Buscar todos los elementos del header
            const headerCells = document.querySelectorAll('.fc-col-header-cell, .fc th, .fc-scrollgrid-sync-table th');
            headerCells.forEach(cell => {
                cell.style.backgroundColor = '#4a5568';
                cell.style.background = '#4a5568';
            });
            
            // Buscar el texto dentro de los headers
            const headerText = document.querySelectorAll('.fc-col-header-cell-cushion, .fc-col-header-cell a, .fc-col-header-cell span');
            headerText.forEach(text => {
                text.style.color = '#ffffff';
                text.style.fontWeight = '700';
                text.style.opacity = '1';
            });
            
            // Aplicar al contenedor del header
            const headerContainers = document.querySelectorAll('.fc-col-header, .fc-scrollgrid-sync-table thead');
            headerContainers.forEach(container => {
                container.style.backgroundColor = '#4a5568';
                container.style.background = '#4a5568';
            });
            
            console.log('[CalendarView] Header styles applied directly to DOM');
        }, 200);
    }
    
    attachViewButtons() {
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.target.dataset.view;
                this.changeView(view);
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
            });
        });
    }
    
    changeView(viewName) {
        const viewMap = {
            'month': 'dayGridMonth',
            'week': 'timeGridWeek',
            'day': 'timeGridDay',
            'list': 'listWeek'
        };
        
        const fcView = viewMap[viewName] || viewName;
        this.calendar.changeView(fcView);
        this.currentView = fcView;
        
        // Re-aplicar estilos después del cambio de vista
        this.applyHeaderStyles();
    }
    
    handleEventClick(info) {
        if (info.jsEvent) {
            info.jsEvent.preventDefault();
            info.jsEvent.stopPropagation();
        }
        
        info.el.style.cursor = 'pointer';
        
        const eventData = Object.assign({
            id: info.event.id,
            title: info.event.title,
            start: info.event.start,
            end: info.event.end
        }, info.event.extendedProps || {});
        
        if (this.options.onEventClick) {
            this.options.onEventClick(eventData);
        }
        
        return false;
    }
    
    handleDateClick(info) {
        if (this.currentView === 'dayGridMonth') {
            this.calendar.changeView('timeGridDay', info.dateStr);
            return;
        }
        
        if (this.options.onDateClick) {
            this.options.onDateClick(info.date);
        }
    }
    
    /**
     * MODIFICADO: Personalizar evento con emoji de categoría
     */
    customizeEvent(info) {
        const event = info.event;
        const element = info.el;
        
        // Obtener categoría del evento
        const category = event.extendedProps.category || 'sin_categoria';
        const categoryInfo = CATEGORY_COLORS[category] || CATEGORY_COLORS['sin_categoria'];
        
        // Actualizar el título con el emoji de la categoría
        const titleEl = element.querySelector('.fc-event-title');
        if (titleEl) {
            titleEl.innerHTML = event.title.replace(/^🎵\s*/, '');
        }
        
        this.addEventTooltip(element, event);
        
        // Marcar eventos de alta prioridad
        if (event.extendedProps.priority >= 8) {
            element.classList.add('high-priority');
        }
        
        // Agregar clase de categoría para estilos adicionales
        element.classList.add(`event-category-${category}`);
    }
    
    addEventTooltip(element, event) {
        const tooltip = document.createElement('div');
        tooltip.className = 'event-tooltip';
        
        // Obtener información de categoría
        const category = event.extendedProps.category || 'sin_categoria';
        const categoryInfo = CATEGORY_COLORS[category] || CATEGORY_COLORS['sin_categoria'];
        
        let tooltipContent = '<div class="tooltip-header">' + event.title + '</div>';
        tooltipContent += '<div class="tooltip-body">';
        
        // Mostrar categoría
        tooltipContent += '<p><strong>Categoría:</strong> ' + this.getCategoryName(category) + '</p>';
        
        const filename = (event.extendedProps && event.extendedProps.filename) || 
                        (event.extendedProps && event.extendedProps.file_path) || 
                        'Sin archivo';
        tooltipContent += '<p><strong>Archivo:</strong> ' + filename + '</p>';
        
        // Para eventos recurrentes, usar startTime; para eventos únicos, usar start
        let displayTime = '';
        
        // Debug: mostrar toda la información disponible
        console.log('[CalendarView] Tooltip time debug:', {
            eventTitle: event.title,
            eventStart: event.start,
            eventStartTime: event.startTime,
            extendedProps: event.extendedProps,
            originalTime: event.extendedProps?.originalTime,
            schedule_times: event.extendedProps?.schedule_times
        });
        
        // Primero verificar si hay originalTime en extendedProps (más directo)
        if (event.extendedProps && event.extendedProps.originalTime) {
            displayTime = event.extendedProps.originalTime;
        } else if (event.extendedProps && event.extendedProps.schedule_times) {
            const times = event.extendedProps.schedule_times;
            
            if (event.extendedProps.scheduleType === 'interval' && Array.isArray(times) && times.length >= 2) {
                // Para interval, mostrar rango horario
                displayTime = `${times[0]} - ${times[1]}`;
            } else if (event.extendedProps.scheduleType === 'specific' && Array.isArray(times)) {
                // Para specific, mostrar la primera hora (o todas si son pocas)
                if (times.length === 1) {
                    displayTime = times[0];
                } else if (times.length <= 3) {
                    displayTime = times.join(', ');
                } else {
                    displayTime = `${times[0]} (y ${times.length - 1} más)`;
                }
            } else if (typeof times === 'string') {
                displayTime = times;
            }
        } else if (event.startTime) {
            // Evento recurrente - la hora está en startTime (formato string "HH:MM")
            displayTime = event.startTime;
        } else if (event.start) {
            // Evento único - formatear la fecha/hora con timezone correcto
            displayTime = this.formatTime(event.start);
        } else {
            displayTime = 'No especificada';
        }
        
        tooltipContent += '<p><strong>Hora:</strong> ' + displayTime + '</p>';
        
        const eventType = (event.extendedProps && event.extendedProps.scheduleType) || 
                         (event.extendedProps && event.extendedProps.type) || 
                         'Evento';
        tooltipContent += '<p><strong>Tipo:</strong> ' + eventType + '</p>';
        
        if (event.extendedProps && event.extendedProps.notes) {
            tooltipContent += '<p><strong>Notas:</strong> ' + event.extendedProps.notes + '</p>';
        }
        
        tooltipContent += '</div>';
        tooltip.innerHTML = tooltipContent;
        
        element.addEventListener('mouseenter', (e) => {
            document.body.appendChild(tooltip);
            
            const rect = element.getBoundingClientRect();
            
            // Posicionamiento mejorado considerando scroll
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
            
            tooltip.style.position = 'absolute';
            tooltip.style.left = (rect.left + scrollLeft) + 'px';
            tooltip.style.top = (rect.bottom + scrollTop + 5) + 'px';
            tooltip.style.zIndex = '9999';
            
            const tooltipRect = tooltip.getBoundingClientRect();
            
            // Ajustar si se sale por la derecha
            if (tooltipRect.right > window.innerWidth) {
                tooltip.style.left = (window.innerWidth - tooltipRect.width - 10 + scrollLeft) + 'px';
            }
            
            // Ajustar si se sale por abajo (mostrar arriba del elemento)
            if (tooltipRect.bottom > window.innerHeight) {
                tooltip.style.top = (rect.top + scrollTop - tooltipRect.height - 5) + 'px';
                tooltip.classList.add('tooltip-above');
            }
        });
        
        element.addEventListener('mouseleave', () => {
            if (tooltip.parentNode) {
                tooltip.remove();
            }
        });
    }
    
    setEvents(events) {
        console.log('[CalendarView] setEvents called with', events.length, 'events');
        
        // Si el calendario no está listo aún, guardar los eventos para después
        if (!this.calendar) {
            console.log('[CalendarView] Calendar not ready yet, storing events for later');
            this.pendingEvents = events;
            return;
        }
        
        console.log('[CalendarView] Adding events to calendar');
        this.calendar.removeAllEvents();
        
        events.forEach(event => {
            this.calendar.addEvent(event);
        });
        
        const totalEvents = this.calendar.getEvents().length;
        console.log('[CalendarView] Total events in calendar:', totalEvents);
    }
    
    async loadAudioSchedules() {
        try {
            const response = await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list' })
            });
            
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Error al cargar schedules');
            }
            
            console.log('[CalendarView] Schedules cargados con categorías:', data.schedules.map(s => ({
                id: s.id,
                title: s.title,
                category: s.category
            })));
            
            return this.transformSchedulesToEvents(data.schedules || []);
            
        } catch (error) {
            console.error('[CalendarView] Error loading audio schedules:', error);
            return [];
        }
    }
    
    /**
     * MODIFICADO: Transformar schedules a eventos con colores por categoría
     */
    transformSchedulesToEvents(schedules) {
        const events = [];
        
        for (let i = 0; i < schedules.length; i++) {
            const schedule = schedules[i];
            
            if (!schedule.is_active) continue;
            
            // Obtener categoría y colores
            const category = schedule.category || 'sin_categoria';
            const categoryColors = CATEGORY_COLORS[category] || CATEGORY_COLORS['sin_categoria'];
            
            console.log('[CalendarView] Procesando schedule:', {
                id: schedule.id,
                title: schedule.title,
                category: category,
                colors: categoryColors
            });
            
            try {
                // Detectar si es tipo 'once' (una sola vez)
                if (schedule.schedule_type === 'once' && schedule.schedule_time) {
                    // Parsear las horas (pueden venir como JSON string)
                    let scheduleTimes = [];
                    if (typeof schedule.schedule_time === 'string' && schedule.schedule_time.startsWith('[')) {
                        try {
                            scheduleTimes = JSON.parse(schedule.schedule_time);
                        } catch(e) {
                            scheduleTimes = [schedule.schedule_time];
                        }
                    } else if (Array.isArray(schedule.schedule_time)) {
                        scheduleTimes = schedule.schedule_time;
                    } else {
                        scheduleTimes = [schedule.schedule_time];
                    }
                    
                    // Para eventos 'once', usar la fecha start_date o hoy
                    // Importante: Crear la fecha en hora local, no UTC
                    let eventDate;
                    if (schedule.start_date) {
                        // Parsear la fecha como local (agregando hora para evitar UTC)
                        const [year, month, day] = schedule.start_date.split('-');
                        eventDate = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
                    } else {
                        eventDate = new Date();
                    }
                    
                    scheduleTimes.forEach(time => {
                        const [hours, minutes] = time.split(':');
                        const eventDateTime = new Date(eventDate);
                        eventDateTime.setHours(parseInt(hours), parseInt(minutes), 0, 0);
                        
                        // Comparar con el momento actual para eventos futuros
                        const now = new Date();
                        
                        // Mostrar si:
                        // 1. El evento es en el futuro (después de ahora)
                        // 2. O es hoy pero aún no ha pasado la hora
                        if (eventDateTime > now) {
                            const event = {
                                id: 'audio_schedule_once_' + schedule.id + '_' + time,
                                title: schedule.title || schedule.filename,
                                start: eventDateTime,
                                backgroundColor: categoryColors.bg,
                                borderColor: categoryColors.border,
                                textColor: categoryColors.text,
                                extendedProps: {
                                    type: 'audio_schedule',
                                    scheduleId: schedule.id,
                                    filename: schedule.filename,
                                    scheduleType: 'once',
                                    scheduleTime: time,
                                    startDate: schedule.start_date,
                                    isActive: schedule.is_active,
                                    createdAt: schedule.created_at,
                                    category: category
                                }
                            };
                            events.push(event);
                        }
                    });
                    
                    continue; // Saltar el procesamiento normal
                    
                } else if (schedule.schedule_type === 'specific' && 
                    schedule.schedule_days && 
                    schedule.schedule_time) {
                    
                    // Debug para ver qué datos llegan
                    console.log('[CalendarView] Procesando schedule específico:', {
                        id: schedule.id,
                        title: schedule.title,
                        schedule_days: schedule.schedule_days,
                        schedule_time: schedule.schedule_time,
                        type_of_days: typeof schedule.schedule_days
                    });
                    
                    // Parsear los días (ya vienen como array)
                    const scheduleDays = schedule.schedule_days;
                    
                    // Parsear las horas (pueden venir como JSON string)
                    let scheduleTimes = [];
                    if (typeof schedule.schedule_time === 'string' && schedule.schedule_time.startsWith('[')) {
                        try {
                            scheduleTimes = JSON.parse(schedule.schedule_time);
                        } catch(e) {
                            scheduleTimes = [schedule.schedule_time];
                        }
                    } else if (Array.isArray(schedule.schedule_time)) {
                        scheduleTimes = schedule.schedule_time;
                    } else {
                        scheduleTimes = [schedule.schedule_time];
                    }
                    
                    // Generar eventos para los próximos 7 días
                    const today = new Date();
                    const maxDays = 7;
                    
                    for (let dayOffset = 0; dayOffset < maxDays; dayOffset++) {
                        const checkDate = new Date();
                        checkDate.setDate(today.getDate() + dayOffset);
                        
                        // Verificar si este día está en la programación
                        const dayOfWeek = checkDate.getDay();
                        const dayName = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][dayOfWeek];
                        
                        // Verificar si el día está programado
                        let isDayScheduled = false;
                        if (Array.isArray(scheduleDays)) {
                            // Los días pueden venir como números o strings
                            isDayScheduled = scheduleDays.includes(dayName) || 
                                           scheduleDays.includes(String(dayOfWeek)) ||
                                           scheduleDays.includes(dayOfWeek); // IMPORTANTE: comparar también como número
                        } else if (typeof scheduleDays === 'string') {
                            // Si es string, intentar parsearlo como JSON
                            try {
                                const parsedDays = JSON.parse(scheduleDays);
                                if (Array.isArray(parsedDays)) {
                                    isDayScheduled = parsedDays.includes(dayName) || 
                                                   parsedDays.includes(String(dayOfWeek)) ||
                                                   parsedDays.includes(dayOfWeek); // IMPORTANTE: comparar también como número
                                } else {
                                    isDayScheduled = scheduleDays.includes(dayName) || 
                                                   scheduleDays.includes(String(dayOfWeek));
                                }
                            } catch(e) {
                                isDayScheduled = scheduleDays.includes(dayName) || 
                                               scheduleDays.includes(String(dayOfWeek));
                            }
                        }
                        
                        // Debug de días
                        if (dayOffset === 0) { // Solo logear una vez
                            console.log(`[CalendarView] Día ${dayOfWeek} (${dayName}): ${isDayScheduled ? '✅' : '❌'}`, {
                                checkDate: checkDate.toLocaleDateString(),
                                scheduleDays: scheduleDays
                            });
                        }
                        
                        if (!isDayScheduled) {
                            continue; // Este día no está programado
                        }
                        
                        // Verificar start_date
                        if (schedule.start_date) {
                            const startDate = new Date(schedule.start_date);
                            if (checkDate < startDate) {
                                continue; // Aún no ha comenzado
                            }
                        }
                        
                        // Verificar end_date
                        if (schedule.end_date) {
                            const endDate = new Date(schedule.end_date);
                            if (checkDate > endDate) {
                                continue; // Ya terminó
                            }
                        }
                        
                        // Crear un evento por cada hora programada
                        for (let timeIndex = 0; timeIndex < scheduleTimes.length; timeIndex++) {
                            const time = scheduleTimes[timeIndex];
                            const timeParts = time.split(':');
                            const hours = parseInt(timeParts[0]);
                            const minutes = parseInt(timeParts[1] || '0');
                            
                            // Crear fecha/hora específica
                            const eventDate = new Date(checkDate);
                            eventDate.setHours(hours - 4); // Aplicar offset UTC-4
                            eventDate.setMinutes(minutes);
                            eventDate.setSeconds(0);
                            eventDate.setMilliseconds(0);
                            
                            // Crear el evento con colores de categoría
                            const event = {
                                id: `audio_schedule_${schedule.id}_${dayOffset}_${timeIndex}`,
                                title: schedule.title || schedule.filename,
                                start: eventDate,
                                backgroundColor: categoryColors.bg,
                                borderColor: categoryColors.border,
                                textColor: categoryColors.text,
                                extendedProps: {
                                    type: 'audio_schedule',
                                    scheduleId: schedule.id,
                                    filename: schedule.filename,
                                    scheduleType: schedule.schedule_type,
                                    scheduleDays: schedule.schedule_days,
                                    scheduleTime: time,
                                    startDate: schedule.start_date,
                                    endDate: schedule.end_date,
                                    isActive: schedule.is_active,
                                    createdAt: schedule.created_at,
                                    category: category // Incluir categoría
                                }
                            };
                            
                            events.push(event);
                        }
                    }
                    
                    // Saltar el procesamiento normal para este schedule
                    continue;
                    
                } else {
                    // Comportamiento original para otros tipos de schedule
                    const nextExecution = this.calculateNextExecution(schedule);
                    
                    if (!nextExecution) {
                        console.warn('[CalendarView] No next execution for schedule:', schedule.id);
                        continue;
                    }
                    
                    const event = {
                        id: 'audio_schedule_' + schedule.id,
                        title: schedule.title || schedule.filename,
                        start: nextExecution,
                        backgroundColor: categoryColors.bg,
                        borderColor: categoryColors.border,
                        textColor: categoryColors.text,
                        extendedProps: {
                            type: 'audio_schedule',
                            scheduleId: schedule.id,
                            filename: schedule.filename,
                            scheduleType: schedule.schedule_type,
                            intervalMinutes: schedule.interval_minutes,
                            intervalHours: schedule.interval_hours,
                            scheduleDays: schedule.schedule_days,
                            scheduleTime: schedule.schedule_time,
                            startDate: schedule.start_date,
                            endDate: schedule.end_date,
                            isActive: schedule.is_active,
                            createdAt: schedule.created_at,
                            category: category // Incluir categoría
                        }
                    };
                    
                    events.push(event);
                }
                
            } catch (error) {
                console.error('[CalendarView] Error transforming schedule:', schedule.id, error);
            }
        }
        
        console.log('[CalendarView] Total eventos generados:', events.length);
        return events;
    }
    
    calculateNextExecution(schedule) {
        const now = new Date();
        
        try {
            switch (schedule.schedule_type) {
                case 'once':
                    if (schedule.start_date && schedule.schedule_time) {
                        const timeStr = parseScheduleTime(schedule.schedule_time);
                        const executeAt = new Date(schedule.start_date + ' ' + timeStr);
                        
                        // FIX TIMEZONE: Compensar por el offset para caso 'once'
                        executeAt.setHours(executeAt.getHours() - 4); // Restar 4 horas para UTC-4
                        
                        return executeAt > now ? executeAt : null;
                    }
                    break;
                    
                case 'interval':
                    const intervalHours = parseInt(schedule.interval_hours || 0);
                    const intervalMinutes = parseInt(schedule.interval_minutes || 0);
                    const intervalMs = (intervalHours * 60 + intervalMinutes) * 60 * 1000;
                    
                    if (intervalMs <= 0) return null;
                    
                    // IMPORTANTE: Para intervalos, también verificar los días programados
                    if (schedule.schedule_days) {
                        // Obtener los días programados
                        let scheduledDays = [];
                        
                        if (Array.isArray(schedule.schedule_days)) {
                            scheduledDays = schedule.schedule_days;
                        } else if (typeof schedule.schedule_days === 'string') {
                            try {
                                scheduledDays = JSON.parse(schedule.schedule_days);
                            } catch(e) {
                                console.error('[CalendarView] Error parsing schedule_days:', e);
                                scheduledDays = [];
                            }
                        }
                        
                        // Buscar el próximo día válido
                        let checkDate = new Date(now);
                        let foundValidDay = false;
                        const maxDaysToCheck = 7; // Máximo una semana
                        
                        for (let i = 0; i < maxDaysToCheck; i++) {
                            const dayOfWeek = checkDate.getDay();
                            
                            // Verificar si este día está en los días programados
                            // Los días pueden estar como números o strings
                            const isDayScheduled = scheduledDays.includes(dayOfWeek) || 
                                                  scheduledDays.includes(String(dayOfWeek)) ||
                                                  scheduledDays.includes(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][dayOfWeek]);
                            
                            if (isDayScheduled) {
                                foundValidDay = true;
                                break;
                            }
                            
                            // Avanzar al siguiente día
                            checkDate.setDate(checkDate.getDate() + 1);
                            checkDate.setHours(0, 0, 0, 0); // Reset hora al inicio del día
                        }
                        
                        if (!foundValidDay) {
                            console.warn('[CalendarView] No valid day found for interval schedule:', schedule.id);
                            return null;
                        }
                        
                        // Establecer la hora del próximo intervalo
                        let nextTime = new Date(checkDate.getTime() + intervalMs);
                        
                        if (schedule.start_date) {
                            const startDate = new Date(schedule.start_date);
                            if (nextTime < startDate) nextTime = startDate;
                        }
                        
                        if (schedule.end_date) {
                            const endDate = new Date(schedule.end_date);
                            if (nextTime > endDate) return null;
                        }
                        
                        return nextTime;
                    } else {
                        // Si no hay días específicos, funcionar como antes
                        let nextTime = new Date(now.getTime() + intervalMs);
                        
                        if (schedule.start_date) {
                            const startDate = new Date(schedule.start_date);
                            if (nextTime < startDate) nextTime = startDate;
                        }
                        
                        if (schedule.end_date) {
                            const endDate = new Date(schedule.end_date);
                            if (nextTime > endDate) return null;
                        }
                        
                        return nextTime;
                    }
                    
                case 'specific':
                    console.log('[TEST] Specific schedule:', {
                        id: schedule.id,
                        start_date: schedule.start_date,
                        schedule_days: schedule.schedule_days,
                        schedule_time: schedule.schedule_time,
                        schedule_time_type: typeof schedule.schedule_time
                    });
                    
                    if (!schedule.schedule_days || !schedule.schedule_time) return null;
                    
                    const targetDays = [];
                    
                    if (Array.isArray(schedule.schedule_days)) {
                        for (let i = 0; i < schedule.schedule_days.length; i++) {
                            const day = schedule.schedule_days[i];
                            if (typeof day === 'string') {
                                const dayLower = day.toLowerCase().trim();
                                const dayNumber = DAY_NAME_TO_NUMBER[dayLower];
                                if (dayNumber !== undefined) {
                                    targetDays.push(dayNumber);
                                } else {
                                    const parsed = parseInt(day);
                                    if (!isNaN(parsed) && parsed >= 0 && parsed <= 6) {
                                        targetDays.push(parsed);
                                    }
                                }
                            } else if (typeof day === 'number') {
                                targetDays.push(day);
                            }
                        }
                    }
                    
                    if (targetDays.length === 0) {
                        console.warn('[CalendarView] No valid days for schedule:', schedule.id);
                        return null;
                    }
                    
                    const timeString = parseScheduleTime(schedule.schedule_time);
                    console.log('[TEST] Parsed time:', {
                        original: schedule.schedule_time,
                        parsed: timeString,
                        is_array: Array.isArray(schedule.schedule_time)
                    });
                    
                    if (!timeString || timeString.indexOf(':') === -1) {
                        console.warn('[CalendarView] Invalid time format:', timeString);
                        return null;
                    }
                    
                    const timeParts = timeString.split(':');
                    const hours = parseInt(timeParts[0]);
                    const minutes = parseInt(timeParts[1] || '0');
                    
                    if (isNaN(hours) || isNaN(minutes)) {
                        console.warn('[CalendarView] Invalid time values:', timeString);
                        return null;
                    }
                    
                    for (let i = 0; i < 60; i++) { // Buscar hasta 60 días
                        const checkDate = new Date();
                        checkDate.setDate(checkDate.getDate() + i);
                        
                        console.log('[TEST] Checking day:', {
                            iteration: i,
                            checkDate: checkDate.toDateString(),
                            dayOfWeek: checkDate.getDay(),
                            targetDays: targetDays,
                            start_date: schedule.start_date,
                            is_after_start: checkDate >= new Date(schedule.start_date)
                        });
                        
                        // Validar contra start_date si existe
                        if (schedule.start_date) {
                            const startDate = new Date(schedule.start_date);
                            if (checkDate < startDate) {
                                continue; // Saltar fechas anteriores al inicio
                            }
                        }
                        
                        // FIX TIMEZONE: Compensar por el offset
                        const adjustedHours = hours - 4; // Restar 4 horas para UTC-4
                        
                        checkDate.setHours(adjustedHours);
                        checkDate.setMinutes(minutes);
                        checkDate.setSeconds(0);
                        checkDate.setMilliseconds(0);
                        
                        const dayOfWeek = checkDate.getDay();
                        
                        let isDayValid = false;
                        for (let j = 0; j < targetDays.length; j++) {
                            if (targetDays[j] === dayOfWeek) {
                                isDayValid = true;
                                break;
                            }
                        }
                        
                        if (isDayValid && checkDate > now) {
                            return checkDate;
                        }
                    }
                    
                    console.warn('[CalendarView] No future execution found for schedule:', schedule.id);
                    break;
                    
                default:
                    console.warn('[CalendarView] Unknown schedule type:', schedule.schedule_type);
                    return null;
            }
        } catch (error) {
            console.error('[CalendarView] Error in calculateNextExecution:', error);
        }
        
        return null;
    }
    
    filterByCategory(activeCategories) {
        const allEvents = this.calendar.getEvents();
        let visibleCount = 0;
        let totalCount = 0;
        
        console.log('[CalendarView] Filtering by categories:', activeCategories);
        
        allEvents.forEach(event => {
            // Filtrar eventos que tengan categoría (audio_schedule o cualquier evento con categoría)
            if (event.extendedProps && 
                (event.extendedProps.type === 'audio_schedule' || event.extendedProps.category)) {
                totalCount++;
                
                const category = event.extendedProps.category || 'sin_categoria';
                const isVisible = activeCategories.includes(category);
                
                // Usar display para ocultar/mostrar eventos
                event.setProp('display', isVisible ? 'auto' : 'none');
                
                if (isVisible) {
                    visibleCount++;
                }
                
                // Debug
                if (totalCount <= 5) { // Solo los primeros 5 para no llenar la consola
                    console.log(`[CalendarView] Event ${event.id}: category=${category}, visible=${isVisible}`);
                }
            }
        });
        
        console.log(`[CalendarView] Filter result: ${visibleCount}/${totalCount} events visible`);
        return { visible: visibleCount, total: totalCount };
    }
    
    formatTime(date) {
        // Si date es un string de hora simple (ej: "14:00"), devolverlo tal cual
        if (typeof date === 'string' && /^\d{2}:\d{2}$/.test(date)) {
            return date;
        }
        
        // Si es un objeto Date o string de fecha, formatearlo con timezone correcto
        const d = new Date(date);
        return d.toLocaleTimeString('es-CL', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
            timeZone: 'America/Santiago'
        });
    }
    
    /**
     * Obtener nombre legible de categoría
     */
    getCategoryName(categoryId) {
        const names = {
            'ofertas': 'Ofertas',
            'horarios': 'Horarios',
            'eventos': 'Eventos',
            'informacion': 'Información',
            'emergencias': 'Emergencias',
            'servicios': 'Servicios',
            'sin_categoria': 'Sin categoría',
            'general': 'General'
        };
        return names[categoryId] || 'Sin categoría';
    }
    
    async refreshAudioSchedules() {
        try {
            const scheduleEvents = await this.loadAudioSchedules();
            
            const existingEvents = this.calendar.getEvents();
            for (let i = 0; i < existingEvents.length; i++) {
                const event = existingEvents[i];
                if (event.id && event.id.indexOf('audio_schedule_') === 0) {
                    event.remove();
                }
            }
            
            for (let i = 0; i < scheduleEvents.length; i++) {
                this.calendar.addEvent(scheduleEvents[i]);
            }
            
        } catch (error) {
            console.error('[CalendarView] Error refreshing schedules:', error);
        }
    }
    
    destroy() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }
}