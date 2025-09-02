/**
 * Calendar View Component - Wrapper de FullCalendar
 * @module CalendarView
 * Version: 2.1 - Fixed schedule_days and schedule_time parsing for 'specific' type
 * @modified 2025-08-21 - Claude - Fix para schedules tipo 'specific' con días en formato string
 */

console.log('[CalendarView] Loading version 2.1 with specific schedule fixes');

export class CalendarView {
    constructor(container, options = {}) {
        this.container = container;
        this.options = options;
        this.calendar = null;
        this.currentView = 'dayGridMonth';
        this.activeCategories = ['ofertas', 'horarios', 'eventos', 'emergencias', 'servicios', 'seguridad'];
        
        // Verificar que FullCalendar esté disponible
        if (typeof FullCalendar === 'undefined') {
            this.loadFullCalendar().then(() => this.initialize());
        } else {
            this.initialize();
        }
    }
    
    async loadFullCalendar() {
        // Cargar CSS
        const cssLink = document.createElement('link');
        cssLink.rel = 'stylesheet';
        cssLink.href = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css';
        document.head.appendChild(cssLink);
        
        // Cargar JS
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    initialize() {
        // Configuración de FullCalendar en español
        this.calendar = new FullCalendar.Calendar(this.container, {
            initialView: this.currentView,
            locale: 'es',
            timeZone: 'America/Santiago',
            height: 'auto',
            
            // Header toolbar
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            
            // Configuración de vistas
            views: {
                dayGridMonth: {
                    buttonText: 'Mes'
                },
                timeGridWeek: {
                    buttonText: 'Semana'
                },
                timeGridDay: {
                    buttonText: 'Día'
                },
                listWeek: {
                    buttonText: 'Lista'
                }
            },
            
            // Formato de hora 24h
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            
            // Configuración de slots de tiempo
            slotMinTime: '00:00:00',
            slotMaxTime: '24:00:00',
            slotDuration: '00:15:00',
            slotLabelInterval: '01:00',
            
            // Eventos del calendario
            events: [],
            
            // Callbacks
            eventClick: (info) => {
                // Manejar click en evento y prevenir comportamiento por defecto
                const result = this.handleEventClick(info);
                // Si retorna false, prevenir navegación
                if (result === false) {
                    return false;
                }
            },
            dateClick: (info) => this.handleDateClick(info),
            eventDidMount: (info) => this.customizeEvent(info),
            
            // Configuración adicional
            editable: false,
            eventInteractive: true,  // Permitir interacción con eventos
            navLinks: false,          // Desactivar navegación automática
            dayMaxEvents: true,
            nowIndicator: true,
            businessHours: {
                daysOfWeek: [0, 1, 2, 3, 4, 5, 6],
                startTime: '10:00',
                endTime: '22:00'
            }
        });
        
        this.calendar.render();
        
        // Actualizar vista según botones externos
        this.attachViewButtons();
    }
    
    attachViewButtons() {
        // Conectar botones de vista personalizados
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.target.dataset.view;
                this.changeView(view);
                
                // Actualizar estado activo
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
        // Prevenir comportamiento por defecto de FullCalendar
        if (info.jsEvent) {
            info.jsEvent.preventDefault();
            info.jsEvent.stopPropagation();
        }
        
        // Prevenir cambio de vista
        info.el.style.cursor = 'pointer';
        
        // Extraer datos del evento
        const eventData = Object.assign({
            id: info.event.id,
            title: info.event.title,
            start: info.event.start,
            end: info.event.end
        }, info.event.extendedProps || {});
        console.log('[CalendarView] Event clicked, preventing default behavior');
        
        // Callback al módulo principal
        if (this.options.onEventClick) {
            this.options.onEventClick(eventData);
        }
        
        // Retornar false para prevenir comportamiento por defecto de FullCalendar
        return false;
    }
    
    handleDateClick(info) {
        // Solo permitir click en vista de día o semana
        if (this.currentView === 'dayGridMonth') {
            this.calendar.changeView('timeGridDay', info.dateStr);
            return;
        }
        
        // Callback para crear nuevo evento
        if (this.options.onDateClick) {
            this.options.onDateClick(info.date);
        }
    }
    
    customizeEvent(info) {
        const event = info.event;
        const element = info.el;
        
        // Agregar icono según categoría
        const categoryIcons = {
            'ofertas': '🛒',
            'horarios': '🕐',
            'eventos': '🎉',
            'seguridad': '⚠️',
            'servicios': '🛎️',
            'emergencias': '🚨'
        };
        
        const icon = categoryIcons[event.extendedProps.category] || '📢';
        
        // Personalizar el contenido del evento
        const titleEl = element.querySelector('.fc-event-title');
        if (titleEl) {
            titleEl.innerHTML = `${icon} ${event.title}`;
        }
        
        // Agregar tooltip con información adicional
        this.addEventTooltip(element, event);
        
        // Agregar clase para prioridad alta
        if (event.extendedProps.priority >= 8) {
            element.classList.add('high-priority');
        }
    }
    
    addEventTooltip(element, event) {
        const tooltip = document.createElement('div');
        tooltip.className = 'event-tooltip';
        tooltip.innerHTML = `
            <div class="tooltip-header">${event.title}</div>
            <div class="tooltip-body">
                <p><strong>Archivo:</strong> ${(event.extendedProps && event.extendedProps.filename) || (event.extendedProps && event.extendedProps.file_path) || 'Sin archivo'}</p>
                <p><strong>Hora:</strong> ${this.formatTime(event.start)}</p>
                <p><strong>Tipo:</strong> ${(event.extendedProps && event.extendedProps.scheduleType) || (event.extendedProps && event.extendedProps.type) || 'Evento'}</p>
                ${(event.extendedProps && event.extendedProps.notes) ? '<p><strong>Notas:</strong> ' + event.extendedProps.notes + '</p>' : ''}
            </div>
        `;
        
        // Mostrar tooltip en hover
        element.addEventListener('mouseenter', (e) => {
            document.body.appendChild(tooltip);
            
            const rect = element.getBoundingClientRect();
            tooltip.style.position = 'absolute';
            tooltip.style.left = rect.left + 'px';
            tooltip.style.top = (rect.bottom + 5) + 'px';
            tooltip.style.zIndex = '9999';
            
            // Ajustar si se sale de la pantalla
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
        // Limpiar eventos anteriores
        this.calendar.removeAllEvents();
        
        // Agregar nuevos eventos
        events.forEach(event => {
            this.calendar.addEvent(event);
        });
        
        // Actualizar lista de próximos eventos
        this.updateUpcomingEvents(events);
    }
    
    /**
     * Carga schedules desde el sistema de audio scheduling
     * @returns {Promise<Array>} Array de eventos para FullCalendar
     */
    async loadAudioSchedules() {
        try {
            console.log('[CalendarView] Loading audio schedules...');
            
            const response = await fetch('/api/audio-scheduler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'list'
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Error al cargar schedules');
            }
            
            console.log(`[CalendarView] Loaded ${(data.schedules && data.schedules.length) || 0} schedules`);
            
            // Transformar schedules a eventos FullCalendar
            const calendarEvents = this.transformSchedulesToEvents(data.schedules || []);
            
            console.log("[CalendarView] Final calendar events created:", calendarEvents.length);
            return calendarEvents;
            
        } catch (error) {
            console.error('[CalendarView] Error loading audio schedules:', error);
            return [];
        }
    }
    
    /**
     * Transforma schedules del backend a eventos FullCalendar
     * @param {Array} schedules - Array de schedules del backend
     * @returns {Array} Array de eventos FullCalendar
     */
    transformSchedulesToEvents(schedules) {
        console.log("[CalendarView] Total schedules before filter:", schedules.length);
        console.log("[CalendarView] Active schedules:", schedules.filter(s => s.is_active).length);
        
        return schedules
            .filter(schedule => schedule.is_active) // Solo schedules activos
            .map(schedule => {
                try {
                    // Calcular próxima ejecución basada en el tipo de schedule
                    const nextExecution = this.calculateNextExecution(schedule);
                    
                    if (!nextExecution) {
                        console.warn('[CalendarView] No next execution for schedule:', schedule.id);
                        return null;
                    }
                    
                    return {
                        id: `audio_schedule_${schedule.id}`,
                        title: `🎵 ${schedule.title || schedule.filename}`,
                        start: nextExecution,
                        backgroundColor: '#e74c3c', // Rojo para schedules de audio
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
                } catch (error) {
                    console.error('[CalendarView] Error transforming schedule:', schedule.id, error);
                    return null;
                }
            })
            .filter(event => event !== null); // Filtrar eventos nulos
    }
    
    /**
     * Calcula la próxima ejecución de un schedule
     * @param {Object} schedule - Schedule del backend
     * @returns {Date|null} Próxima fecha de ejecución
     * @modified 2025-08-21 - Claude - Fix completo para schedules tipo 'specific'
     */
    calculateNextExecution(schedule) {
        const now = new Date();
        
        try {
            switch (schedule.schedule_type) {
                case 'once':
                    // Para schedules de una vez, usar start_date + schedule_time
                    if (schedule.start_date && schedule.schedule_time) {
                        const executeAt = new Date(`${schedule.start_date}T${schedule.schedule_time}:00`);
                        return executeAt > now ? executeAt : null; // Solo si es futuro
                    }
                    break;
                    
                case 'interval':
                    // Para schedules por intervalos, calcular próxima ejecución
                    const intervalMs = (parseInt(schedule.interval_hours || 0) * 60 + parseInt(schedule.interval_minutes || 0)) * 60 * 1000;
                    
                    if (intervalMs <= 0) return null;
                    
                    // Buscar próxima ejecución desde ahora
                    let nextTime = new Date(now.getTime() + intervalMs);
                    
                    // Si hay fecha de inicio, respetarla
                    if (schedule.start_date) {
                        const startDate = new Date(schedule.start_date);
                        if (nextTime < startDate) {
                            nextTime = startDate;
                        }
                    }
                    
                    // Si hay fecha de fin, verificar que no la exceda
                    if (schedule.end_date) {
                        const endDate = new Date(schedule.end_date);
                        if (nextTime > endDate) {
                            return null;
                        }
                    }
                    
                    return nextTime;
                    
                case 'specific':
                    // INICIO DEL FIX - Procesamiento mejorado para schedules específicos
                    console.log("[CalendarView] Processing specific schedule ID:", schedule.id);
                    
                    // Validar que tengamos los datos necesarios
                    if (!schedule.schedule_days || !schedule.schedule_time) {
                        console.log("[CalendarView] Missing schedule_days or schedule_time for schedule:", schedule.id);
                        return null;
                    }
                    
                    // Mapeo de nombres de días en inglés a números (0=domingo, 6=sábado)
                    const dayNameToNumber = {
                        'sunday': 0, 
                        'monday': 1, 
                        'tuesday': 2, 
                        'wednesday': 3,
                        'thursday': 4, 
                        'friday': 5, 
                        'saturday': 6
                    };
                    
                    // Procesar schedule_days - puede venir como array de strings o string
                    let targetDays = [];
                    
                    if (Array.isArray(schedule.schedule_days)) {
                        // Si ya es array, mapear los nombres a números
                        targetDays = schedule.schedule_days.map(day => {
                            if (typeof day === 'string') {
                                const dayLower = day.toLowerCase().trim();
                                const dayNumber = dayNameToNumber[dayLower];
                                if (dayNumber !== undefined) {
                                    return dayNumber;
                                }
                                // Si no es un nombre válido, intentar parsearlo como número
                                const parsed = parseInt(day);
                                return isNaN(parsed) ? null : parsed;
                            }
                            // Si ya es número, usarlo directamente
                            return typeof day === 'number' ? day : parseInt(day);
                        }).filter(d => d !== null && !isNaN(d) && d >= 0 && d <= 6);
                        
                    } else if (typeof schedule.schedule_days === 'string') {
                        // Si es string, puede ser JSON o string separado por comas
                        try {
                            // Intentar parsear como JSON
                            const parsed = JSON.parse(schedule.schedule_days);
                            if (Array.isArray(parsed)) {
                                targetDays = parsed.map(day => {
                                    const dayLower = String(day).toLowerCase().trim();
                                    return dayNameToNumber[dayLower] !== undefined 
                                        ? dayNameToNumber[dayLower] 
                                        : parseInt(day);
                                }).filter(d => !isNaN(d) && d >= 0 && d <= 6);
                            }
                        } catch (e) {
                            // Si no es JSON, tratar como string separado por comas
                            const days = schedule.schedule_days.split(',');
                            targetDays = days.map(day => {
                                const trimmed = day.trim().toLowerCase();
                                if (dayNameToNumber[trimmed] !== undefined) {
                                    return dayNameToNumber[trimmed];
                                }
                                const parsed = parseInt(trimmed);
                                return isNaN(parsed) ? null : parsed;
                            }).filter(d => d !== null && !isNaN(d) && d >= 0 && d <= 6);
                        }
                    }
                    
                    console.log("[CalendarView] Processed targetDays for schedule", schedule.id, ":", targetDays);
                    
                    if (targetDays.length === 0) {
                        console.warn('[CalendarView] No valid days for specific schedule:', schedule.id);
                        return null;
                    }
                    
                    // Procesar schedule_time - puede venir como JSON string, array o string simple
                    let timeString;
                    
                    if (typeof schedule.schedule_time === 'string') {
                        // Si es string, verificar si es JSON
                        if (schedule.schedule_time.startsWith('[') || schedule.schedule_time.startsWith('{')) {
                            try {
                                const parsed = JSON.parse(schedule.schedule_time);
                                timeString = Array.isArray(parsed) ? parsed[0] : parsed;
                            } catch (e) {
                                // No es JSON válido, usar como está
                                timeString = schedule.schedule_time;
                            }
                        } else {
                            // String simple, usar directamente
                            timeString = schedule.schedule_time;
                        }
                    } else if (Array.isArray(schedule.schedule_time)) {
                        // Si ya es array, tomar el primer elemento
                        timeString = schedule.schedule_time[0];
                    } else {
                        // Otros casos, convertir a string
                        timeString = String(schedule.schedule_time);
                    }
                    
                    // Validar formato de hora HH:MM
                    if (!timeString || !timeString.includes(':')) {
                        console.warn('[CalendarView] Invalid time format for schedule:', schedule.id, timeString);
                        return null;
                    }
                    
                    const timeParts = timeString.split(':');
                    const hours = parseInt(timeParts[0]);
                    const minutes = parseInt(timeParts[1] || 0);
                    
                    if (isNaN(hours) || isNaN(minutes)) {
                        console.warn('[CalendarView] Invalid time values for schedule:', schedule.id, timeString);
                        return null;
                    }
                    
                    console.log("[CalendarView] Processed time for schedule", schedule.id, ":", timeString);
                    
                    // Buscar la próxima ocurrencia en los próximos 7 días
                    for (let i = 0; i < 7; i++) {
                        const checkDate = new Date(now);
                        checkDate.setDate(now.getDate() + i);
                        checkDate.setHours(hours, minutes, 0, 0);
                        
                        const dayOfWeek = checkDate.getDay();
                        
                        // Verificar si este día está en los días objetivo y es futuro
                        if (targetDays.includes(dayOfWeek) && checkDate > now) {
                            console.log("[CalendarView] Next execution for schedule", schedule.id, ":", 
                                checkDate.toLocaleString('es-CL'));
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
            console.error('[CalendarView] Error calculating next execution for schedule', schedule.id, ':', error);
        }
        
        return null;
    }
    
    updateUpcomingEvents(events) {
        const container = document.getElementById('upcoming-events');
        if (!container) return;
        
        // Filtrar eventos de las próximas 24 horas
        const now = new Date();
        const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
        
        const upcomingEvents = events
            .filter(event => {
                const eventDate = new Date(event.start);
                return eventDate >= now && eventDate <= tomorrow;
            })
            .sort((a, b) => new Date(a.start) - new Date(b.start));
        
        if (upcomingEvents.length === 0) {
            container.innerHTML = '<div class="no-events">No hay anuncios programados para las próximas 24 horas</div>';
            return;
        }
        
        container.innerHTML = upcomingEvents.map(event => `
            <div class="upcoming-event-item" data-event-id="${event.id}">
                <div class="event-time">${this.formatTime(event.start)}</div>
                <div class="event-info">
                    <div class="event-title">${event.title}</div>
                    <div class="event-category" style="color: ${event.backgroundColor}">
                        ${(event.extendedProps && event.extendedProps.type) === 'audio_schedule' ? 'Schedule de Audio' : this.getCategoryName(event.extendedProps && event.extendedProps.category)}
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    filterByCategories(categories) {
        this.activeCategories = categories;
        
        // Obtener todos los eventos
        const allEvents = this.calendar.getEvents();
        
        // Mostrar/ocultar según categorías activas
        allEvents.forEach(event => {
            if (this.activeCategories.includes(event.extendedProps.category)) {
                event.setProp('display', 'auto');
            } else {
                event.setProp('display', 'none');
            }
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
    
    /**
     * Refresca los schedules de audio en el calendario
     */
    async refreshAudioSchedules() {
        try {
            console.log('[CalendarView] Refreshing audio schedules...');
            const scheduleEvents = await this.loadAudioSchedules();
            
            // Remover eventos de schedules existentes
            const existingEvents = this.calendar.getEvents();
            existingEvents.forEach(event => {
                if (event.id && event.id.startsWith('audio_schedule_')) {
                    event.remove();
                }
            });
            
            // Agregar nuevos eventos
            scheduleEvents.forEach(event => {
                this.calendar.addEvent(event);
            });
            
            console.log(`[CalendarView] Refreshed ${scheduleEvents.length} schedule events`);
            
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