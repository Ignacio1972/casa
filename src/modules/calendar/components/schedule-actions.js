/**
 * ScheduleActions Component - Maneja las acciones sobre schedules
 * @module ScheduleActions
 * 
 * Responsabilidades:
 * - Acciones de toggle estado (activar/pausar)
 * - Eliminación con confirmación
 * - Edición de schedules
 * - Preview de audio
 * - Confirmaciones y modales
 */

export class ScheduleActions {
    constructor(scheduleService, calendarLoader, eventBus) {
        this.scheduleService = scheduleService;
        this.calendarLoader = calendarLoader;
        this.eventBus = eventBus;
        
        // Referencias a componentes
        this.audioPlayer = null;
        this.confirmModal = null;
        
        // Callbacks para actualización de UI
        this.onScheduleUpdated = null;
        this.onScheduleDeleted = null;
    }
    
    /**
     * Inicializa el componente
     */
    init() {
        // Configurar modal de confirmación si no existe
        if (!document.getElementById('schedule-confirm-modal')) {
            this.createConfirmModal();
        }
        
        return true;
    }
    
    /**
     * Alterna el estado activo/inactivo de una programación
     */
    async toggleScheduleStatus(scheduleId, activate) {
        try {
            // Mostrar loading
            this.calendarLoader.showLoading(activate ? 'Activando programación...' : 'Pausando programación...');
            
            // Realizar la operación
            const success = await this.scheduleService.toggleScheduleStatus(scheduleId, activate);
            
            if (success) {
                // Notificar éxito
                this.calendarLoader.showSuccess(
                    activate ? '✅ Programación activada' : '⏸️ Programación pausada'
                );
                
                // Emitir evento para actualizar UI
                if (this.eventBus) {
                    this.eventBus.emit('schedule:updated', { scheduleId, isActive: activate });
                }
                
                // Callback para actualizar lista
                if (this.onScheduleUpdated) {
                    await this.onScheduleUpdated();
                }
                
                return true;
            } else {
                throw new Error('Error al cambiar estado');
            }
        } catch (error) {
            console.error('[ScheduleActions] Error toggling schedule:', error);
            this.calendarLoader.showError('Error al cambiar estado de la programación');
            return false;
        } finally {
            this.calendarLoader.hideLoading();
        }
    }
    
    /**
     * Elimina una programación con confirmación
     */
    async deleteSchedule(scheduleId) {
        // Mostrar confirmación
        const confirmed = await this.confirmDeleteSchedule(scheduleId);
        
        if (confirmed) {
            try {
                // Mostrar loading
                this.calendarLoader.showLoading('Eliminando programación...');
                
                // Realizar eliminación
                const success = await this.scheduleService.deleteSchedule(scheduleId);
                
                if (success) {
                    // Notificar éxito
                    this.calendarLoader.showSuccess('🗑️ Programación eliminada correctamente');
                    
                    // Emitir evento
                    if (this.eventBus) {
                        this.eventBus.emit('schedule:deleted', { scheduleId });
                    }
                    
                    // Callback para actualizar lista
                    if (this.onScheduleDeleted) {
                        await this.onScheduleDeleted();
                    }
                    
                    return true;
                } else {
                    throw new Error('Error al eliminar');
                }
            } catch (error) {
                console.error('[ScheduleActions] Error deleting schedule:', error);
                this.calendarLoader.showError('Error al eliminar la programación');
                return false;
            } finally {
                this.calendarLoader.hideLoading();
            }
        }
        
        return false;
    }
    
    /**
     * Muestra confirmación de eliminación
     */
    async confirmDeleteSchedule(scheduleId) {
        return new Promise((resolve) => {
            const modal = document.getElementById('schedule-confirm-modal');
            if (!modal) {
                // Si no hay modal, usar confirm nativo
                resolve(confirm('¿Estás seguro de que deseas eliminar esta programación?'));
                return;
            }
            
            // Configurar modal
            const titleElement = modal.querySelector('.modal-title');
            const messageElement = modal.querySelector('.modal-message');
            const confirmBtn = modal.querySelector('.confirm-btn');
            const cancelBtn = modal.querySelector('.cancel-btn');
            
            titleElement.textContent = '⚠️ Confirmar Eliminación';
            messageElement.textContent = '¿Estás seguro de que deseas eliminar esta programación? Esta acción no se puede deshacer.';
            
            // Mostrar modal
            modal.style.display = 'flex';
            
            // Handlers
            const handleConfirm = () => {
                modal.style.display = 'none';
                cleanup();
                resolve(true);
            };
            
            const handleCancel = () => {
                modal.style.display = 'none';
                cleanup();
                resolve(false);
            };
            
            const cleanup = () => {
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
            };
            
            // Agregar listeners
            confirmBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
        });
    }
    
    /**
     * Edita una programación
     */
    async editSchedule(scheduleId) {
        // Por ahora mostrar mensaje informativo
        // TODO: Implementar edición completa cuando se defina la UI
        this.calendarLoader.showInfo('La edición de programaciones estará disponible próximamente');
        
        // Emitir evento para que otro módulo maneje la edición si está disponible
        if (this.eventBus) {
            this.eventBus.emit('schedule:edit:requested', { scheduleId });
        }
    }
    
    /**
     * Preview de audio inline
     */
    previewAudio(filename, buttonElement) {
        if (!filename) {
            this.calendarLoader.showError('Archivo no disponible');
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
                z-index: 10000;
                max-width: 350px;
                color: var(--text-primary, #ffffff);
            ">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong style="font-size: 0.9rem;">🎵 ${this.truncateFilename(filename, 30)}</strong>
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
    
    /**
     * Crea el modal de confirmación
     */
    createConfirmModal() {
        const modal = document.createElement('div');
        modal.id = 'schedule-confirm-modal';
        modal.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10001;
            align-items: center;
            justify-content: center;
        `;
        
        modal.innerHTML = `
            <div style="
                background: var(--bg-primary, #1e293b);
                border: 1px solid var(--border-color, rgba(255,255,255,0.1));
                border-radius: 12px;
                padding: 2rem;
                max-width: 400px;
                width: 90%;
                box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                animation: modalSlideIn 0.3s ease-out;
            ">
                <h3 class="modal-title" style="
                    color: var(--text-primary, #ffffff);
                    margin: 0 0 1rem 0;
                    font-size: 1.25rem;
                ">⚠️ Confirmar Acción</h3>
                
                <p class="modal-message" style="
                    color: var(--text-secondary, #94a3b8);
                    margin: 0 0 1.5rem 0;
                    line-height: 1.5;
                ">¿Estás seguro de que deseas continuar?</p>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button class="cancel-btn" style="
                        background: var(--bg-secondary, #334155);
                        color: var(--text-primary, #ffffff);
                        border: 1px solid var(--border-color, rgba(255,255,255,0.1));
                        padding: 0.5rem 1rem;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 0.95rem;
                        transition: all 0.2s;
                    ">Cancelar</button>
                    
                    <button class="confirm-btn" style="
                        background: #ef4444;
                        color: white;
                        border: none;
                        padding: 0.5rem 1rem;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 0.95rem;
                        font-weight: 500;
                        transition: all 0.2s;
                    ">Eliminar</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Click fuera para cerrar
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    /**
     * Ver detalles de un schedule
     */
    async viewScheduleDetails(scheduleId) {
        try {
            // Cargar detalles del schedule
            const schedules = await this.scheduleService.loadSchedulesList();
            const schedule = schedules.find(s => s.id === scheduleId);
            
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
                    createdAt: schedule.created_at,
                    category: schedule.category
                };
                
                // Emitir evento para mostrar modal de detalles
                if (this.eventBus) {
                    this.eventBus.emit('schedule:view:details', eventData);
                } else {
                    // Fallback: mostrar alerta con información básica
                    this.showScheduleInfo(eventData);
                }
            } else {
                this.calendarLoader.showError('No se encontraron detalles del schedule');
            }
        } catch (error) {
            console.error('[ScheduleActions] Error loading schedule details:', error);
            this.calendarLoader.showError('Error al cargar detalles del schedule');
        }
    }
    
    /**
     * Muestra información básica del schedule (fallback)
     */
    showScheduleInfo(schedule) {
        const info = `
📅 Programación: ${schedule.title || schedule.filename}
🔄 Tipo: ${schedule.scheduleType}
${schedule.isActive ? '✅ Estado: Activo' : '⏸️ Estado: Pausado'}
📁 Archivo: ${schedule.filename}
🏷️ Categoría: ${schedule.category || 'Sin categoría'}
        `;
        
        this.calendarLoader.showInfo(info, 5000);
    }
    
    /**
     * Trunca el nombre del archivo si es muy largo
     */
    truncateFilename(filename, maxLength) {
        if (filename.length <= maxLength) return filename;
        
        const extension = filename.split('.').pop();
        const nameWithoutExt = filename.slice(0, -(extension.length + 1));
        const truncatedName = nameWithoutExt.slice(0, maxLength - extension.length - 4);
        
        return `${truncatedName}...${extension}`;
    }
    
    /**
     * Limpieza del componente
     */
    destroy() {
        // Remover modal de confirmación si fue creado
        const modal = document.getElementById('schedule-confirm-modal');
        if (modal) {
            modal.remove();
        }
        
        // Remover cualquier player de audio activo
        const audioPlayer = document.querySelector('.inline-audio-player');
        if (audioPlayer) {
            audioPlayer.remove();
        }
        
        // Limpiar referencias
        this.audioPlayer = null;
        this.confirmModal = null;
    }
}

// Agregar estilos de animación para el modal
if (!document.getElementById('schedule-actions-animations')) {
    const style = document.createElement('style');
    style.id = 'schedule-actions-animations';
    style.textContent = `
        @keyframes modalSlideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
}