/**
 * Calendar View Component - FullCalendar Wrapper
 * @module CalendarView v2.3 - Optimized
 */

// Constantes globales
const DAY_NAME_TO_NUMBER = {
    'sunday': 0, 'monday': 1, 'tuesday': 2, 'wednesday': 3,
    'thursday': 4, 'friday': 5, 'saturday': 6
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
    
    customizeEvent(info) {
        const event = info.event;
        const element = info.el;
        
        const categoryIcons = {
            'ofertas': '🛒',
            'horarios': '🕐',
            'eventos': '🎉',
            'seguridad': '⚠️',
            'servicios': '🛎️',
            'emergencias': '🚨'
        };
        
        const icon = categoryIcons[event.extendedProps.category] || '📢';
        
        const titleEl = element.querySelector('.fc-event-title');
        if (titleEl) {
            titleEl.innerHTML = icon + ' ' + event.title;
        }
        
        this.addEventTooltip(element, event);
        
        if (event.extendedProps.priority >= 8) {
            element.classList.add('high-priority');
        }
    }
    
    addEventTooltip(element, event) {
        const tooltip = document.createElement('div');
        tooltip.className = 'event-tooltip';
        
        let tooltipContent = '<div class="tooltip-header">' + event.title + '</div>';
        tooltipContent += '<div class="tooltip-body">';
        
        const filename = (event.extendedProps && event.extendedProps.filename) || 
                        (event.extendedProps && event.extendedProps.file_path) || 
                        'Sin archivo';
        tooltipContent += '<p><strong>Archivo:</strong> ' + filename + '</p>';
        tooltipContent += '<p><strong>Hora:</strong> ' + this.formatTime(event.start) + '</p>';
        
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
            tooltip.style.position = 'absolute';
            tooltip.style.left = rect.left + 'px';
            tooltip.style.top = (rect.bottom + 5) + 'px';
            tooltip.style.zIndex = '9999';
            
            const tooltipRect = tooltip.getBoundingClientRect();
            if (tooltipRect.right > window.innerWidth) {
                tooltip.style.left = (window.innerWidth - tooltipRect.width - 10) + 'px';
            }
        });
        
        element.addEventListener('mouseleave', () => {
            if (tooltip.parentNode) {
                tooltip.remove();
            }
        });
    }
    
    setEvents(events) {
        this.calendar.removeAllEvents();
        
        events.forEach(event => {
            this.calendar.addEvent(event);
        });
        
        this.updateUpcomingEvents(events);
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
            
            return this.transformSchedulesToEvents(data.schedules || []);
            
        } catch (error) {
            console.error('[CalendarView] Error loading audio schedules:', error);
            return [];
        }
    }
    
    transformSchedulesToEvents(schedules) {
        const events = [];
        
        for (let i = 0; i < schedules.length; i++) {
            const schedule = schedules[i];
            
            if (!schedule.is_active) continue;
            
            try {
                const nextExecution = this.calculateNextExecution(schedule);
                
                if (!nextExecution) {
                    console.warn('[CalendarView] No next execution for schedule:', schedule.id);
                    continue;
                }
                
                const event = {
                    id: 'audio_schedule_' + schedule.id,
                    title: '🎵 ' + (schedule.title || schedule.filename),
                    start: nextExecution,
                    backgroundColor: '#e74c3c',
                    borderColor: '#c0392b',
                    textColor: '#ffffff',
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
                        createdAt: schedule.created_at
                    }
                };
                
                events.push(event);
                
            } catch (error) {
                console.error('[CalendarView] Error transforming schedule:', schedule.id, error);
            }
        }
        
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
                    
                case 'specific':
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
                    
                    for (let i = 0; i < 7; i++) {
                        const checkDate = new Date();
                        checkDate.setDate(checkDate.getDate() + i);
                        
                        // FIX TIMEZONE: Compensar por el offset
                        // FullCalendar está mostrando UTC como local, así que restamos 4 horas
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
    
    updateUpcomingEvents(events) {
        const container = document.getElementById('upcoming-events');
        if (!container) return;
        
        const now = new Date();
        const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
        
        const upcomingEvents = [];
        
        for (let i = 0; i < events.length; i++) {
            const event = events[i];
            const eventDate = new Date(event.start);
            if (eventDate >= now && eventDate <= tomorrow) {
                upcomingEvents.push(event);
            }
        }
        
        upcomingEvents.sort((a, b) => new Date(a.start) - new Date(b.start));
        
        if (upcomingEvents.length === 0) {
            container.innerHTML = '<div class="no-events">No hay anuncios programados para las próximas 24 horas</div>';
            return;
        }
        
        let html = '';
        for (let i = 0; i < upcomingEvents.length; i++) {
            const event = upcomingEvents[i];
            const categoryName = (event.extendedProps && event.extendedProps.type) === 'audio_schedule' 
                ? 'Schedule de Audio' 
                : this.getCategoryName(event.extendedProps && event.extendedProps.category);
                
            html += '<div class="upcoming-event-item" data-event-id="' + event.id + '">';
            html += '<div class="event-time">' + this.formatTime(event.start) + '</div>';
            html += '<div class="event-info">';
            html += '<div class="event-title">' + event.title + '</div>';
            html += '<div class="event-category" style="color: ' + event.backgroundColor + '">';
            html += categoryName;
            html += '</div></div></div>';
        }
        
        container.innerHTML = html;
    }
    
    filterByCategories(categories) {
        this.activeCategories = categories;
        
        const allEvents = this.calendar.getEvents();
        
        allEvents.forEach(event => {
            let isActive = false;
            for (let i = 0; i < this.activeCategories.length; i++) {
                if (event.extendedProps.category === this.activeCategories[i]) {
                    isActive = true;
                    break;
                }
            }
            
            event.setProp('display', isActive ? 'auto' : 'none');
        });
    }
    
    formatTime(date) {
        const d = new Date(date);
        return d.toLocaleTimeString('es-CL', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    }
    
    getCategoryName(categoryId) {
        const names = {
            'ofertas': 'Ofertas',
            'horarios': 'Horarios',
            'eventos': 'Eventos',
            'seguridad': 'Seguridad',
            'servicios': 'Servicios',
            'emergencias': 'Emergencias'
        };
        return names[categoryId] || 'General';
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