// Voice Admin Manager
class VoiceAdminManager {
    constructor() {
        this.voicesConfig = null;
        this.apiUrl = '/playground/api/voice-admin.php';
    }

    async init() {
        await this.loadVoices();
        this.setupEventListeners();
    }

    async loadVoices() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list_all' })
            });

            const data = await response.json();
            if (data.success) {
                this.voicesConfig = data.data;
                this.updateUI();
            }
        } catch (error) {
            console.error('Error loading voices:', error);
            this.showNotification('Error cargando voces', 'error');
        }
    }

    updateUI() {
        if (!this.voicesConfig) return;

        const voices = Object.entries(this.voicesConfig.voices);
        
        // Update stats
        document.getElementById('total-voices').textContent = voices.length;
        document.getElementById('active-voices').textContent = 
            voices.filter(([k, v]) => v.active).length;
        document.getElementById('custom-voices').textContent = 
            voices.filter(([k, v]) => v.category === 'custom').length;

        // Update table
        const tbody = document.getElementById('voices-table-body');
        tbody.innerHTML = '';

        // Ordenar voces por el campo 'order'
        const sortedVoices = voices.sort((a, b) => {
            const orderA = a[1].order || 999;
            const orderB = b[1].order || 999;
            return orderA - orderB;
        });

        sortedVoices.forEach(([key, voice]) => {
            const row = document.createElement('tr');
            row.style.borderBottom = '1px solid #f0f0f0';
            
            row.innerHTML = `
                <td style="padding: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div>
                            <strong id="label-${key}">${voice.label}</strong>
                            <br><small style="color: #666;">${key}</small>
                        </div>
                        <button onclick="editVoiceName('${key}')" 
                                style="padding: 2px 6px; border: 1px solid #ddd; 
                                       background: white; border-radius: 3px; cursor: pointer;
                                       font-size: 0.8em;" title="Editar nombre">
                            ✏️
                        </button>
                    </div>
                </td>
                <td style="padding: 0.75rem;">
                    <code style="background: #f5f5f5; padding: 2px 4px; border-radius: 3px; font-size: 0.85em;">
                        ${voice.id.substring(0, 20)}...
                    </code>
                </td>
                <td style="text-align: center; padding: 0.75rem;">
                    <span style="padding: 2px 8px; background: ${voice.gender === 'M' ? '#e3f2fd' : '#fce4ec'}; 
                                 color: ${voice.gender === 'M' ? '#1976d2' : '#c2185b'}; 
                                 border-radius: 3px; font-size: 0.85em;">
                        ${voice.gender}
                    </span>
                </td>
                <td style="text-align: center; padding: 0.75rem;">
                    <button onclick="toggleVoice('${key}')" 
                            style="padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer;
                                   background: ${voice.active ? '#4caf50' : '#f44336'}; color: white;">
                        ${voice.active ? 'Activa' : 'Inactiva'}
                    </button>
                </td>
                <td style="text-align: center; padding: 0.75rem;">
                    <button onclick="reorderVoice('${key}', 'up')" 
                            style="padding: 4px 8px; border: 1px solid #ddd; 
                                   background: white; border-radius: 4px; cursor: pointer; margin-right: 4px;"
                            title="Subir">
                        ⬆️
                    </button>
                    <button onclick="reorderVoice('${key}', 'down')" 
                            style="padding: 4px 8px; border: 1px solid #ddd; 
                                   background: white; border-radius: 4px; cursor: pointer;"
                            title="Bajar">
                        ⬇️
                    </button>
                </td>
                <td style="text-align: center; padding: 0.75rem;">
                    ${voice.is_default 
                        ? '<span style="color: #4caf50;">⭐ Default</span>' 
                        : `<button onclick="setDefaultVoice('${key}')" 
                                   style="padding: 4px 8px; border: 1px solid #ddd; 
                                          background: white; border-radius: 4px; cursor: pointer;">
                                Hacer Default
                           </button>`
                    }
                </td>
                <td style="text-align: center; padding: 0.75rem;">
                    <button onclick="deleteVoice('${key}')" 
                            style="padding: 4px 12px; background: #ff5252; color: white; 
                                   border: none; border-radius: 4px; cursor: pointer;"
                            ${voice.is_default ? 'disabled title="No se puede eliminar la voz por defecto"' : ''}>
                        🗑️ Eliminar
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    async addVoice(label, voiceId, gender) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add',
                    label: label,
                    voice_id: voiceId,
                    gender: gender
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showNotification('Voz agregada exitosamente', 'success');
                await this.loadVoices();
                // Limpiar formulario
                document.getElementById('new-voice-label').value = '';
                document.getElementById('new-voice-id').value = '';
            } else {
                this.showNotification(data.error || 'Error agregando voz', 'error');
            }
        } catch (error) {
            console.error('Error adding voice:', error);
            this.showNotification('Error agregando voz', 'error');
        }
    }

    async toggleVoice(voiceKey) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle',
                    voice_key: voiceKey
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showNotification(`Voz ${data.active ? 'activada' : 'desactivada'}`, 'success');
                await this.loadVoices();
            }
        } catch (error) {
            console.error('Error toggling voice:', error);
            this.showNotification('Error cambiando estado', 'error');
        }
    }

    async deleteVoice(voiceKey) {
        if (!confirm(`¿Seguro que quieres eliminar la voz ${voiceKey}?`)) return;

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    voice_key: voiceKey
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showNotification('Voz eliminada', 'success');
                await this.loadVoices();
            } else {
                this.showNotification(data.error || 'Error eliminando voz', 'error');
            }
        } catch (error) {
            console.error('Error deleting voice:', error);
            this.showNotification('Error eliminando voz', 'error');
        }
    }

    async setDefaultVoice(voiceKey) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'set_default',
                    voice_key: voiceKey
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showNotification('Voz por defecto actualizada', 'success');
                await this.loadVoices();
            }
        } catch (error) {
            console.error('Error setting default:', error);
            this.showNotification('Error estableciendo voz por defecto', 'error');
        }
    }

    async reorderVoice(voiceKey, direction) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reorder',
                    voice_key: voiceKey,
                    direction: direction
                })
            });

            const data = await response.json();
            if (data.success) {
                await this.loadVoices();
            } else {
                this.showNotification(data.error || 'No se puede mover en esa dirección', 'warning');
            }
        } catch (error) {
            console.error('Error reordering voice:', error);
            this.showNotification('Error reordenando voz', 'error');
        }
    }

    async editVoiceName(voiceKey) {
        const currentLabel = this.voicesConfig.voices[voiceKey].label;
        const newLabel = prompt('Editar nombre de la voz:', currentLabel);
        
        if (newLabel && newLabel !== currentLabel) {
            try {
                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_label',
                        voice_key: voiceKey,
                        new_label: newLabel.trim()
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.showNotification('Nombre de voz actualizado', 'success');
                    await this.loadVoices();
                } else {
                    this.showNotification(data.error || 'Error actualizando nombre', 'error');
                }
            } catch (error) {
                console.error('Error updating voice name:', error);
                this.showNotification('Error actualizando nombre de voz', 'error');
            }
        }
    }

    showNotification(message, type = 'info') {
        // Crear notificación temporal
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
            color: white;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    setupEventListeners() {
        // Setup se hace desde las funciones globales
    }
}

// Instancia global
const voiceAdmin = new VoiceAdminManager();

// Funciones globales para onclick
function addNewVoice() {
    const label = document.getElementById('new-voice-label').value.trim();
    const voiceId = document.getElementById('new-voice-id').value.trim();
    const gender = document.getElementById('new-voice-gender').value;

    if (!label || !voiceId) {
        voiceAdmin.showNotification('Por favor completa todos los campos', 'error');
        return;
    }

    voiceAdmin.addVoice(label, voiceId, gender);
}

function toggleVoice(key) {
    voiceAdmin.toggleVoice(key);
}

function deleteVoice(key) {
    voiceAdmin.deleteVoice(key);
}

function setDefaultVoice(key) {
    voiceAdmin.setDefaultVoice(key);
}

function reorderVoice(key, direction) {
    voiceAdmin.reorderVoice(key, direction);
}

function editVoiceName(key) {
    voiceAdmin.editVoiceName(key);
}

// Inicializar cuando se muestre la sección
function initVoiceAdmin() {
    voiceAdmin.init();
}

// CSS para animaciones
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
