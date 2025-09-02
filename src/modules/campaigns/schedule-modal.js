/**
 * Modal para programar reproducción automática de audios
 * @version 2.0 - Agregado soporte para categorías
 * @modified 2024-11-28 - Claude - Enviar categoría al crear schedule
 */

export class ScheduleModal {
    constructor() {
        this.modalId = 'scheduleModal';
        this.selectedFile = null;
        this.selectedTitle = null;
        this.selectedCategory = null; // NUEVO: Guardar categoría
        this.scheduleType = 'interval'; // interval, specific, once
    }
    
    /**
     * Mostrar modal
     * @param {string} filename - Nombre del archivo
     * @param {string} title - Título del audio
     * @param {string} category - Categoría del mensaje (NUEVO)
     */
    show(filename, title, category = null) {
        console.log('[ScheduleModal] Abriendo con categoría:', category);
        
        // MIGRADO: Los estilos ahora se cargan globalmente desde /styles-v5/main.css
        // No es necesario cargar estilos específicos del modal
        console.log('[ScheduleModal] Styles loaded from global styles-v5');
        
        this.selectedFile = filename;
        this.selectedTitle = title;
        this.selectedCategory = category || 'sin_categoria'; // NUEVO
        
        this.createModal();
        document.getElementById(this.modalId).style.display = 'block';
    }
    
    hide() {
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    createModal() {
        // Remover modal existente si hay
        const existing = document.getElementById(this.modalId);
        if (existing) existing.remove();
        
        // NUEVO: Obtener emoji y nombre de categoría
        const categoryInfo = this.getCategoryInfo(this.selectedCategory);
        
        const modal = document.createElement('div');
        modal.id = this.modalId;
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="schedule-modal">
                <div class="modal-header">
                    <h2>📅 Programar Mensaje</h2>
                    <button class="modal-close" onclick="window.scheduleModal.hide()">×</button>
                </div>
                <div class="modal-content">
                    <div class="schedule-info">
                        <strong>Mensaje:</strong> <span>${this.selectedTitle || this.selectedFile}</span>
                        <br>
                        <strong>Categoría:</strong> 
                        <span class="category-badge category-${this.selectedCategory}">
                            ${categoryInfo.emoji} ${categoryInfo.name}
                        </span>
                    </div>
                    
                    <!-- Tabs de tipo de programación -->
                    <div class="schedule-tabs">
                        <button class="schedule-tab active" onclick="window.scheduleModal.selectTab('interval', this)">🔁 Intervalo</button>
                        <button class="schedule-tab" onclick="window.scheduleModal.selectTab('specific', this)">📅 Específico</button>
                        <button class="schedule-tab" onclick="window.scheduleModal.selectTab('once', this)">⏰ Una vez</button>
                    </div>
                    
                    <!-- Contenido del tab -->
                    <div class="schedule-tab-content">
                        <!-- Configuración por intervalos -->
                        <div id="intervalConfig" class="config-section">
                            <div class="form-group">
                                <label class="form-label">Repetir cada:</label>
                                <select class="form-control form-select" id="intervalSelect">
                                    <option value="30">30 minutos</option>
                                    <option value="60">1 hora</option>
                                    <option value="120">2 horas</option>
                                    <option value="180">3 horas</option>
                                    <option value="240" selected>4 horas</option>
                                    <option value="360">6 horas</option>
                                    <option value="480">8 horas</option>
                                    <option value="720">12 horas</option>
                                    <option value="1440">24 horas</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Días de la semana:</label>
                                <div class="weekday-selector">
                                    ${this.createWeekdayButtons('interval')}
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Hora inicio:</label>
                                <input type="time" class="form-control" id="intervalStartTime" value="10:00">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Hora fin:</label>
                                <input type="time" class="form-control" id="intervalEndTime" value="20:00">
                            </div>
                        </div>
                        
                        <!-- Configuración días específicos -->
                        <div id="specificConfig" class="config-section" style="display:none;">
                            <div class="form-group">
                                <label class="form-label">Días de la semana:</label>
                                <div class="weekday-selector">
                                    ${this.createWeekdayButtons('specific')}
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Horas del día:</label>
                                <div id="timesContainer">
                                    <div class="time-input-group">
                                        <input type="time" class="form-control schedule-time" value="14:00">
                                        <button class="btn btn-icon" onclick="window.scheduleModal.addTimeSlot()">➕</button>
                                    </div>
                                </div>
                                <small class="form-text">Agrega múltiples horarios con el botón +</small>
                            </div>
                        </div>
                        
                        <!-- Configuración una vez -->
                        <div id="onceConfig" class="config-section" style="display:none;">
                            <div class="form-group">
                                <label class="form-label">Fecha y hora:</label>
                                <input type="datetime-local" class="form-control" id="onceDateTime">
                            </div>
                        </div>
                        
                        <!-- Configuración común -->
                        <div class="form-group">
                            <label class="form-label">Fecha inicio:</label>
                            <input type="date" class="form-control" id="startDate" value="${new Date().toISOString().split('T')[0]}">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Fecha fin (opcional):</label>
                            <input type="date" class="form-control" id="endDate">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Notas (opcional):</label>
                            <textarea class="form-control" id="scheduleNotes" rows="2" placeholder="Ej: Oferta especial de temporada"></textarea>
                        </div>
                        
                        <div class="form-actions" style="margin-top: var(--spacing-lg);">
                            <button class="btn btn-secondary" onclick="window.scheduleModal.hide()">Cancelar</button>
                            <button class="btn btn-primary" onclick="window.scheduleModal.save()">Guardar Programación</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Agregar event listener para cerrar al hacer clic fuera
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.hide();
            }
        });
    }
    
    /**
     * NUEVO: Obtener información de categoría
     */
    getCategoryInfo(category) {
        const categories = {
            'ofertas': { name: 'Ofertas', emoji: '🛒' },
            'eventos': { name: 'Eventos', emoji: '🎉' },
            'informacion': { name: 'Información', emoji: 'ℹ️' },
            'emergencias': { name: 'Emergencias', emoji: '🚨' },
            'servicios': { name: 'Servicios', emoji: '🛎️' },
            'horarios': { name: 'Horarios', emoji: '🕐' },
            'sin_categoria': { name: 'Sin categoría', emoji: '📁' }
        };
        
        return categories[category] || categories['sin_categoria'];
    }
    
    /**
     * NUEVO: Agregar estilos de categorías
     */
    addCategoryStyles() {
        if (document.getElementById('category-badge-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'category-badge-styles';
        styles.textContent = `
            .category-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 12px;
                border-radius: 16px;
                font-size: 0.9rem;
                font-weight: 500;
                gap: 4px;
            }
            
            .category-ofertas {
                background: rgba(34, 197, 94, 0.2);
                color: #22c55e;
                border: 1px solid rgba(34, 197, 94, 0.3);
            }
            
            .category-eventos {
                background: rgba(59, 130, 246, 0.2);
                color: #3b82f6;
                border: 1px solid rgba(59, 130, 246, 0.3);
            }
            
            .category-informacion {
                background: rgba(6, 182, 212, 0.2);
                color: #06b6d4;
                border: 1px solid rgba(6, 182, 212, 0.3);
            }
            
            .category-emergencias {
                background: rgba(239, 68, 68, 0.2);
                color: #ef4444;
                border: 1px solid rgba(239, 68, 68, 0.3);
            }
            
            .category-servicios {
                background: rgba(168, 85, 247, 0.2);
                color: #a855f7;
                border: 1px solid rgba(168, 85, 247, 0.3);
            }
            
            .category-horarios {
                background: rgba(245, 158, 11, 0.2);
                color: #f59e0b;
                border: 1px solid rgba(245, 158, 11, 0.3);
            }
            
            .category-sin_categoria {
                background: rgba(107, 114, 128, 0.2);
                color: #6b7280;
                border: 1px solid rgba(107, 114, 128, 0.3);
            }
            
            .info-row {
                margin-bottom: 8px;
            }
        `;
        document.head.appendChild(styles);
    }
    
    /**
     * Crear botones de días de la semana estilo elegante
     */
    createWeekdayButtons(prefix) {
        const days = [
            { value: 'monday', label: 'LU' },
            { value: 'tuesday', label: 'MA' },
            { value: 'wednesday', label: 'MI' },
            { value: 'thursday', label: 'JU' },
            { value: 'friday', label: 'VI' },
            { value: 'saturday', label: 'SA' },
            { value: 'sunday', label: 'DO' }
        ];
        
        // Por defecto, días laborales seleccionados
        const defaultSelected = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        
        return days.map(day => {
            const isSelected = defaultSelected.includes(day.value);
            return `
                <button class="weekday-btn ${isSelected ? 'selected' : ''}" 
                        data-day="${day.value}"
                        data-prefix="${prefix}"
                        onclick="window.scheduleModal.toggleDay('${day.value}', '${prefix}', this)">
                    ${day.label}
                </button>
            `;
        }).join('');
    }
    
    /**
     * Toggle día de la semana
     */
    toggleDay(dayValue, prefix, button) {
        button.classList.toggle('selected');
    }
    
    /**
     * Cambiar tab activo
     */
    selectTab(type, tabButton) {
        // Actualizar tipo de programación
        this.scheduleType = type;
        
        // Actualizar tabs
        document.querySelectorAll('.schedule-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        tabButton.classList.add('active');
        
        // Ocultar todas las secciones
        document.querySelectorAll('.config-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Mostrar la sección correspondiente
        if (type === 'interval') {
            document.getElementById('intervalConfig').style.display = 'block';
        } else if (type === 'specific') {
            document.getElementById('specificConfig').style.display = 'block';
        } else if (type === 'once') {
            document.getElementById('onceConfig').style.display = 'block';
        }
    }
    
    /**
     * Agregar slot de tiempo (para horarios específicos)
     */
    addTimeSlot() {
        const container = document.getElementById('timesContainer');
        const timeDiv = document.createElement('div');
        timeDiv.className = 'time-input-group';
        timeDiv.innerHTML = `
            <input type="time" class="form-control schedule-time" value="16:00">
            <button class="btn btn-icon" onclick="this.parentElement.remove()">➖</button>
        `;
        container.appendChild(timeDiv);
    }
    
    // Mantener compatibilidad con changeType por si algo lo usa
    changeType(type) {
        const tab = document.querySelector(`.schedule-tab[onclick*="${type}"]`);
        if (tab) {
            this.selectTab(type, tab);
        }
    }
    
    createDaysSelector() {
        // Método legacy mantenido por compatibilidad
        return this.createWeekdayButtons('legacy');
    }
    
    async save() {
        const data = {
            action: 'create',
            filename: this.selectedFile,
            title: this.selectedTitle,
            category: this.selectedCategory, // NUEVO: Enviar categoría
            schedule_type: this.scheduleType,
            start_date: document.getElementById('startDate').value,
            end_date: document.getElementById('endDate').value || null,
            notes: document.getElementById('scheduleNotes').value,
            is_active: true
        };
        
        console.log('[ScheduleModal] Guardando con categoría:', data.category);
        
        // Agregar configuración según el tipo
        if (this.scheduleType === 'interval') {
            // Obtener el valor del select en minutos y convertir a horas/minutos
            const totalMinutes = parseInt(document.getElementById('intervalSelect').value);
            data.interval_hours = Math.floor(totalMinutes / 60);
            data.interval_minutes = totalMinutes % 60;
            
            // Obtener días seleccionados para intervalo
            const days = [];
            document.querySelectorAll('#intervalConfig .weekday-btn.selected').forEach(btn => {
                days.push(btn.dataset.day);
            });
            data.schedule_days = days.length > 0 ? days : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            
            // Obtener horas de inicio y fin
            const startTime = document.getElementById('intervalStartTime').value;
            const endTime = document.getElementById('intervalEndTime').value;
            if (startTime && endTime) {
                data.schedule_times = [startTime, endTime]; // Usar como rango horario
            }
            
        } else if (this.scheduleType === 'specific') {
            // Obtener días seleccionados
            const days = [];
            document.querySelectorAll('#specificConfig .weekday-btn.selected').forEach(btn => {
                days.push(btn.dataset.day);
            });
            data.schedule_days = days;
            
            // Obtener horas
            const times = [];
            document.querySelectorAll('.schedule-time').forEach(input => {
                if (input.value) times.push(input.value);
            });
            data.schedule_times = times;
            
        } else if (this.scheduleType === 'once') {
            const dateTime = document.getElementById('onceDateTime').value;
            if (!dateTime) {
                this.showNotification('⚠️ Por favor selecciona fecha y hora', 'error');
                return; // Detener el guardado
            }
            // Enviar el datetime completo para tipo "once"
            data.once_datetime = dateTime;
            // También separar para compatibilidad
            const [date, time] = dateTime.split('T');
            data.start_date = date;
            data.schedule_times = [time];
        }
        
        try {
            const response = await fetch('api/audio-scheduler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('✅ Programación creada exitosamente', 'success');
                this.hide();
                
                // Emitir evento para actualizar calendario
                if (window.eventBus) {
                    window.eventBus.emit('schedule:created', {
                        scheduleId: result.schedule_id,
                        category: result.category
                    });
                }
                
                // Recargar lista si existe
                if (window.campaignLibrary && window.campaignLibrary.loadSchedules) {
                    window.campaignLibrary.loadSchedules();
                }
            } else {
                this.showNotification('Error: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error guardando programación:', error);
            this.showNotification('Error al guardar programación', 'error');
        }
    }
    
    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Exponer globalmente
window.scheduleModal = new ScheduleModal();

// Auto-registrar en window cuando se carga
if (typeof window !== "undefined") {
    window.ScheduleModal = ScheduleModal;
}