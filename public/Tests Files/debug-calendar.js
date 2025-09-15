// Debug script para el calendario
// Ejecuta estos comandos en la consola del navegador

// 1. Ver todos los schedules cargados
console.log('=== VERIFICANDO SCHEDULES ===');
fetch('/api/schedules.php')
    .then(r => r.json())
    .then(data => {
        console.log('Schedules totales:', data.data.length);
        
        // Buscar el schedule más reciente (el que acabas de crear)
        const recent = data.data
            .filter(s => s.schedule_type === 'specific')
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];
        
        if (recent) {
            console.log('Schedule más reciente:', recent);
            console.log('- ID:', recent.id);
            console.log('- Tipo:', recent.schedule_type);
            console.log('- Días programados:', recent.schedule_days);
            console.log('- Horas:', recent.schedule_time);
            console.log('- Título:', recent.title);
            console.log('- Archivo:', recent.filename);
            
            // Verificar qué tipo de dato es schedule_days
            console.log('- Tipo de schedule_days:', typeof recent.schedule_days);
            console.log('- Es array?:', Array.isArray(recent.schedule_days));
            
            // Si es string, intentar parsearlo
            if (typeof recent.schedule_days === 'string') {
                try {
                    const parsed = JSON.parse(recent.schedule_days);
                    console.log('- Días parseados:', parsed);
                } catch(e) {
                    console.log('- No se pudo parsear como JSON');
                }
            }
        }
    });

// 2. Ver eventos del calendario
console.log('=== VERIFICANDO EVENTOS DEL CALENDARIO ===');
setTimeout(() => {
    // Buscar el calendario en el DOM
    const calendarEl = document.querySelector('#calendar-container .fc');
    if (calendarEl && calendarEl.__fullCalendar) {
        const calendar = calendarEl.__fullCalendar;
        const events = calendar.getEvents();
        
        console.log('Eventos en el calendario:', events.length);
        
        // Filtrar eventos de tipo audio_schedule
        const audioEvents = events.filter(e => 
            e.extendedProps && e.extendedProps.type === 'audio_schedule'
        );
        
        console.log('Eventos de audio:', audioEvents.length);
        
        audioEvents.forEach((event, index) => {
            console.log(`Evento ${index + 1}:`);
            console.log('- Título:', event.title);
            console.log('- Fecha:', event.start);
            console.log('- Día de la semana:', event.start.getDay(), 
                ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][event.start.getDay()]);
            console.log('- Props:', event.extendedProps);
        });
    } else {
        console.log('No se encontró el calendario. Intenta desde el módulo:');
        console.log('Si tienes acceso al módulo, prueba:');
        console.log('window.calendarModule?.calendarView?.calendar?.getEvents()');
    }
}, 2000);

// 3. Función de debug para procesar días
window.debugScheduleDays = function(scheduleDays) {
    console.log('=== DEBUG DE DÍAS ===');
    console.log('Input:', scheduleDays);
    console.log('Tipo:', typeof scheduleDays);
    console.log('Es array?:', Array.isArray(scheduleDays));
    
    const today = new Date();
    const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    
    for (let i = 0; i < 7; i++) {
        const checkDate = new Date();
        checkDate.setDate(today.getDate() + i);
        const dayOfWeek = checkDate.getDay();
        const dayName = dayNames[dayOfWeek];
        
        let isDayScheduled = false;
        
        if (Array.isArray(scheduleDays)) {
            isDayScheduled = scheduleDays.includes(dayName) || 
                           scheduleDays.includes(String(dayOfWeek));
        } else if (typeof scheduleDays === 'string') {
            // Si es string, intentar parsearlo primero
            try {
                const parsed = JSON.parse(scheduleDays);
                if (Array.isArray(parsed)) {
                    isDayScheduled = parsed.includes(dayName) || 
                                   parsed.includes(String(dayOfWeek));
                }
            } catch(e) {
                // Si no es JSON, verificar si contiene el día
                isDayScheduled = scheduleDays.includes(dayName) || 
                               scheduleDays.includes(String(dayOfWeek));
            }
        }
        
        console.log(`${checkDate.toLocaleDateString()} (${dayName}): ${isDayScheduled ? '✅' : '❌'}`);
    }
};

// 4. Test con días de sábado y domingo
console.log('=== TEST CON SÁBADO Y DOMINGO ===');
window.debugScheduleDays(['saturday', 'sunday']);
window.debugScheduleDays('["saturday", "sunday"]');

// 5. Verificar el procesamiento actual
console.log('=== INSTRUCCIONES ===');
console.log('1. Ejecuta este script en la consola');
console.log('2. Busca el schedule más reciente y verifica los días');
console.log('3. Si schedule_days es un string JSON, necesita ser parseado');
console.log('4. Verifica qué días aparecen como ✅ en el debug');
console.log('5. Compara con lo que ves en el calendario visual');

// 6. Función para forzar recarga del calendario
window.reloadCalendar = function() {
    console.log('Recargando calendario...');
    window.eventBus.emit('calendar:reload');
    // O si tienes acceso directo:
    if (window.calendarModule && window.calendarModule.loadSchedules) {
        window.calendarModule.loadSchedules();
    }
};

console.log('Debug cargado. Usa window.debugScheduleDays() y window.reloadCalendar()');