/**
 * MessageActions Service - Maneja todas las acciones sobre mensajes
 * Extraído del módulo CampaignLibrary para mejor separación de responsabilidades
 * @module MessageActions
 */

import { apiClient } from '../../../core/api-client.js';
import { eventBus } from '../../../core/event-bus.js';
import { storageManager } from '../../../core/storage-manager.js';
import { escapeHtml } from '../utils/formatters.js';

export class MessageActions {
    constructor(parent) {
        this.parent = parent; // Referencia al CampaignLibraryModule
    }

    /**
     * Obtener mensaje por ID
     * @private
     */
    getMessage(id) {
        return this.parent.messages.find(m => m.id === id);
    }

    /**
     * Reproducir mensaje de audio
     */
    async playMessage(id) {
        const message = this.getMessage(id);
        
        // Determinar el archivo de audio según el tipo
        let audioFilename;
        if (message.type === 'audio') {
            audioFilename = message.filename; // Archivos de audio guardados
        } else {
            audioFilename = message.audioFilename; // Mensajes de texto con audio generado
        }
        
        if (!message || !audioFilename) {
            this.parent.showError('Audio no disponible');
            return;
        }
        
        // Remover player anterior si existe
        const existingPlayer = document.querySelector('.floating-player');
        if (existingPlayer) {
            existingPlayer.remove();
        }
        
        // Crear player flotante
        const player = document.createElement('div');
        player.className = 'floating-player';
        player.innerHTML = `
            <div class="player-header">
                <span>🎵 ${escapeHtml(message.title)}</span>
                <button onclick="this.parentElement.parentElement.remove()">✕</button>
            </div>
            <audio controls autoplay>
                <source src="/api/biblioteca.php?filename=${audioFilename}" type="audio/mpeg">
                Tu navegador no soporta el elemento de audio.
            </audio>
        `;
        
        document.body.appendChild(player);
    }

    /**
     * Editar título del mensaje
     */
    async editMessage(id) {
        const message = this.getMessage(id);
        if (!message) return;
        
        const newTitle = prompt('Editar título del mensaje:', message.title);
        if (!newTitle || newTitle === message.title) return;
        
        if (newTitle.trim().length < 3) {
            this.parent.showError('El título debe tener al menos 3 caracteres');
            return;
        }
        
        const trimmedTitle = newTitle.trim();
        message.title = trimmedTitle;
        message.updatedAt = Date.now();
        
        // Si es un archivo de audio, actualizar en BD
        if (message.type === 'audio') {
            try {
                const response = await apiClient.post('/saved-messages.php', {
                    action: 'update_display_name',
                    id: message.id,
                    display_name: trimmedTitle
                });
                
                if (!response.success) {
                    throw new Error(response.error || 'Error actualizando nombre');
                }
            } catch (error) {
                console.error('Error actualizando nombre de audio:', error);
                this.parent.showError('Error al actualizar nombre del audio');
                return;
            }
        } else {
            // Para mensajes de texto, guardar localmente
            storageManager.save(`library_message_${message.id}`, message);
            
            // Guardar en backend
            try {
                await fetch('/api/library-metadata.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update',
                        id: message.id,
                        data: { title: trimmedTitle }
                    })
                });
            } catch (error) {
                console.error('Error actualizando en backend:', error);
            }
        }
        
        this.parent.displayMessages();
        this.parent.showSuccess('Título actualizado');
    }

    /**
     * Enviar mensaje a la radio
     */
    async sendToRadio(id) {
        const message = this.getMessage(id);
        
        // Determinar el archivo según el tipo
        let audioFilename;
        if (message.type === 'audio') {
            audioFilename = message.filename; // Archivos de audio guardados
        } else {
            audioFilename = message.azuracastFilename; // Mensajes de texto con audio
        }
        
        if (!message || !audioFilename) {
            this.parent.showError('Audio no disponible para enviar');
            return;
        }
        
        if (!confirm(`¿Quiere que este mensaje suene ahora mismo en la radio?`)) return;
        
        try {
            // Usar diferentes endpoints según el tipo
            const endpoint = message.type === 'audio' ? '/biblioteca.php' : '/generate.php';
            const action = message.type === 'audio' ? 'send_library_to_radio' : 'send_to_radio';
            
            const response = await apiClient.post(endpoint, {
                action: action,
                filename: audioFilename
            });
            
            if (response.success) {
                this.parent.showSuccess('¡Mensaje enviado a la radio!');
            } else {
                throw new Error(response.error);
            }
        } catch (error) {
            this.parent.showError('Error al enviar: ' + error.message);
        }
    }

    /**
     * Programar mensaje
     */
    async scheduleMessage(id, title) {
        console.log("[DEBUG] scheduleMessage - ID:", id, "Title:", title);
        const message = this.getMessage(id);
        
        if (!message) {
            console.error("[DEBUG] Mensaje no encontrado:", id);
            this.parent.showError('Mensaje no encontrado');
            return;
        }
        
        console.log("[DEBUG] Mensaje encontrado:", message);
        
        if (message.type !== 'audio') {
            this.parent.showError('Solo se pueden programar archivos de audio');
            return;
        }
        
        try {
            // Cargar el modal dinámicamente
            if (!window.ScheduleModal) {
                const module = await import('../schedule-modal.js');
                window.ScheduleModal = module.ScheduleModal || module.default;
            }
            
            window.scheduleModal = new window.ScheduleModal();
            const modal = window.scheduleModal;
            
            // IMPORTANTE: Pasar la categoría como tercer parámetro
            const category = message.category || 'sin_categoria';
            console.log("[DEBUG] Pasando al modal - filename:", message.filename, "title:", title || message.title, "category:", category);
            
            modal.show(message.filename, title || message.title, category);
            
        } catch (error) {
            console.error('Error al cargar modal:', error);
            eventBus.emit('navigate', { module: 'calendar' });
            this.parent.showSuccess('Usa el calendario para programar este audio');
        }
    }

    /**
     * Eliminar mensaje
     */
    async deleteMessage(id) {
        const message = this.getMessage(id);
        if (!message) return;
        
        if (!confirm(`¿Eliminar "${message.title}" permanentemente?\n\nEsta acción no se puede deshacer.`)) return;
        
        // Eliminar localmente
        storageManager.delete(`library_message_${message.id}`);
        
        // Eliminar del array
        this.parent.messages = this.parent.messages.filter(m => m.id !== id);
        
        // Eliminar en backend
        try {
            await fetch('/api/library-metadata.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: message.id
                })
            });
        } catch (error) {
            console.error('Error eliminando en backend:', error);
        }
        
        // Actualizar UI
        this.parent.updateFilterCounts();
        this.parent.displayMessages();
        this.parent.showSuccess('Mensaje eliminado');
    }

    /**
     * Toggle dropdown de categorías
     */
    toggleCategoryDropdown(event, messageId) {
        event.stopPropagation();
        
        // Cerrar otros dropdowns y remover clase dropdown-active
        document.querySelectorAll('.category-dropdown').forEach(dropdown => {
            if (dropdown.id !== `dropdown-${messageId}`) {
                dropdown.classList.remove('active');
                // Encontrar y remover clase del card padre
                const parentCard = dropdown.closest('.message-card');
                if (parentCard) {
                    parentCard.classList.remove('dropdown-active');
                }
            }
        });
        
        // Toggle el dropdown actual
        const dropdown = document.getElementById(`dropdown-${messageId}`);
        const currentCard = document.querySelector(`[data-id="${messageId}"]`);
        
        if (dropdown && currentCard) {
            const wasActive = dropdown.classList.contains('active');
            dropdown.classList.toggle('active');
            
            // Agregar/quitar clase al card para z-index
            if (wasActive) {
                currentCard.classList.remove('dropdown-active');
            } else {
                currentCard.classList.add('dropdown-active');
            }
        }
    }

    /**
     * Actualizar categoría de mensaje
     */
    async updateCategory(messageId, newCategory) {
        const message = this.getMessage(messageId);
        if (!message) return;
        
        const oldCategory = message.category;
        message.category = newCategory;
        
        // Si es un archivo de audio, actualizar en BD
        if (message.type === 'audio') {
            try {
                const response = await apiClient.post('/saved-messages.php', {
                    action: 'update_category',
                    id: message.id,
                    category: newCategory
                });
                
                if (!response.success) {
                    message.category = oldCategory; // Revertir si falla
                    throw new Error(response.error || 'Error actualizando categoría');
                }
                
                // Sincronizar con schedules si el mensaje tiene filename
                if (message.filename) {
                    await this.parent.syncCategoryToSchedules(message.filename, newCategory);
                }
            } catch (error) {
                console.error('Error actualizando categoría:', error);
                this.parent.showError('Error al actualizar categoría');
                return;
            }
        } else {
            // Para mensajes de texto, guardar localmente
            storageManager.save(`library_message_${message.id}`, message);
            
            // Sincronizar con schedules si tiene audio
            const audioFile = message.filename || message.audioFilename;
            if (audioFile) {
                await this.parent.syncCategoryToSchedules(audioFile, newCategory);
            }
        }
        
        // Cerrar dropdown
        document.querySelectorAll('.category-dropdown').forEach(d => {
            d.classList.remove('active');
            // Remover clase del card padre
            const parentCard = d.closest('.message-card');
            if (parentCard) {
                parentCard.classList.remove('dropdown-active');
            }
        });
        
        // Actualizar UI
        this.parent.updateFilterCounts();
        this.parent.displayMessages();
        
        // Animación de confirmación
        const badge = document.querySelector(`[data-id="${messageId}"] .message-badge`);
        if (badge) {
            badge.style.transform = 'scale(1.2)';
            setTimeout(() => {
                badge.style.transform = 'scale(1)';
            }, 200);
        }
    }

    /**
     * Cambiar categoría (método alternativo con prompt)
     */
    async changeCategory(id) {
        const message = this.getMessage(id);
        if (!message) return;
        
        const categories = {
            'sin_categoria': '📁 Sin categoría',
            'ofertas': '🛒 Ofertas',
            'eventos': '🎉 Eventos',
            'informacion': 'ℹ️ Información',
            'emergencias': '🚨 Emergencias',
            'servicios': '🛎️ Servicios',
            'horarios': '🕐 Horarios'
        };
        
        let options = 'Selecciona una categoría:\n\n';
        Object.keys(categories).forEach((key, index) => {
            options += `${index + 1}. ${categories[key]}\n`;
        });
        
        const selection = prompt(options + '\nIngresa el número (1-7):', '1');
        if (!selection) return;
        
        const categoryKeys = Object.keys(categories);
        const selectedIndex = parseInt(selection) - 1;
        
        if (selectedIndex < 0 || selectedIndex >= categoryKeys.length) {
            this.parent.showError('Selección inválida');
            return;
        }
        
        const newCategory = categoryKeys[selectedIndex];
        
        try {
            if (message.type === 'audio') {
                const response = await apiClient.post('/saved-messages.php', {
                    action: 'update_category',
                    id: message.id,
                    category: newCategory
                });
                
                if (response.success) {
                    message.category = newCategory;
                    
                    // NUEVO: También sincronizar en calendarios/schedules
                    await this.parent.syncCategoryToSchedules(message.filename, newCategory);
                    
                    this.parent.displayMessages();
                    this.parent.showSuccess('Categoría actualizada');
                }
            } else {
                message.category = newCategory;
                storageManager.save(`library_message_${message.id}`, message);
                
                // NUEVO: También sincronizar en calendarios/schedules para mensajes locales
                const audioFile = message.filename || message.audioFilename;
                if (audioFile) {
                    await this.parent.syncCategoryToSchedules(audioFile, newCategory);
                }
                
                this.parent.displayMessages();
                this.parent.showSuccess('Categoría actualizada');
            }
        } catch (error) {
            console.error('Error actualizando categoría:', error);
            this.parent.showError('Error al actualizar categoría');
        }
    }
}