/**
 * Recent AI Suggestions Component
 * Componente para mostrar sugerencias de IA recientes
 */

export class RecentSuggestionsComponent {
    constructor(dashboard) {
        this.dashboard = dashboard;
        this.container = null;
        this.suggestions = [];
        this.isLoading = false;
        this.currentPage = 0;
        this.pageSize = 10;
    }
    
    /**
     * Renderizar el componente completo
     */
    render() {
        return `
            <div class="card recent-suggestions">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="card-icon">💡</span>
                        Sugerencias Recientes
                    </h2>
                    <button class="btn-icon" id="refreshSuggestions" title="Actualizar">🔄</button>
                </div>
                <div class="suggestions-list" id="suggestionsList">
                    <div class="loading-suggestions" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        Cargando sugerencias...
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Inicializar el componente
     */
    async init(container) {
        this.container = container;
        this.container.innerHTML = this.render();
        
        // Configurar event listeners
        this.setupEventListeners();
        
        // Cargar sugerencias iniciales
        await this.loadSuggestions();
    }
    
    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        const refreshBtn = document.getElementById('refreshSuggestions');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadSuggestions());
        }
    }
    
    /**
     * Cargar sugerencias desde el servidor
     */
    async loadSuggestions() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        const listContainer = document.getElementById('suggestionsList');
        
        try {
            const response = await fetch(`/api/ai-suggestions-service.php?limit=${this.pageSize}&offset=${this.currentPage * this.pageSize}`);
            const data = await response.json();
            
            if (data.success && data.suggestions) {
                this.suggestions = data.suggestions;
                this.renderSuggestions();
            } else {
                this.showError('No se pudieron cargar las sugerencias');
            }
        } catch (error) {
            console.error('Error loading suggestions:', error);
            this.showError('Error al cargar las sugerencias');
        } finally {
            this.isLoading = false;
        }
    }
    
    /**
     * Renderizar la lista de sugerencias
     */
    renderSuggestions() {
        const listContainer = document.getElementById('suggestionsList');
        
        if (this.suggestions.length === 0) {
            listContainer.innerHTML = `
                <div class="empty-state" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    <p>No hay sugerencias recientes</p>
                    <small>Las sugerencias aparecerán aquí cuando generes nuevas con el asistente IA</small>
                </div>
            `;
            return;
        }
        
        const suggestionsHTML = this.suggestions.map(suggestion => {
            const date = new Date(suggestion.created_at);
            const timeAgo = this.getTimeAgo(date);
            const categoryClass = `badge-${suggestion.category || 'general'}`;
            const usedClass = suggestion.used ? 'suggestion-used' : '';
            
            return `
                <div class="suggestion-item ${usedClass}" data-id="${suggestion.id}">
                    <div class="suggestion-header">
                        <div class="suggestion-meta">
                            <span class="suggestion-time" title="${date.toLocaleString()}">${timeAgo}</span>
                            ${suggestion.category ? `<span class="message-badge ${categoryClass}">${this.getCategoryLabel(suggestion.category)}</span>` : ''}
                            ${suggestion.used ? '<span class="used-badge">✓ Usada</span>' : ''}
                        </div>
                        <div class="suggestion-actions">
                            <button class="btn-icon-small use-suggestion" data-text="${this.escapeHtml(suggestion.suggestion_text)}" title="Usar sugerencia">
                                📝
                            </button>
                            <button class="btn-icon-small copy-suggestion" data-text="${this.escapeHtml(suggestion.suggestion_text)}" title="Copiar">
                                📋
                            </button>
                        </div>
                    </div>
                    <div class="suggestion-text">
                        ${this.escapeHtml(suggestion.suggestion_text)}
                    </div>
                    ${suggestion.context ? `
                        <div class="suggestion-context">
                            <small>Contexto: ${this.escapeHtml(suggestion.context)}</small>
                        </div>
                    ` : ''}
                    <div class="suggestion-details">
                        ${suggestion.tone ? `<span class="detail-badge">🎭 ${suggestion.tone}</span>` : ''}
                        ${suggestion.duration ? `<span class="detail-badge">⏱️ ${suggestion.duration}s</span>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        listContainer.innerHTML = suggestionsHTML;
        
        // Agregar event listeners a los botones
        this.attachItemEventListeners();
    }
    
    /**
     * Adjuntar event listeners a los items
     */
    attachItemEventListeners() {
        // Botones de usar sugerencia
        document.querySelectorAll('.use-suggestion').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const text = btn.dataset.text;
                this.useSuggestion(text);
            });
        });
        
        // Botones de copiar
        document.querySelectorAll('.copy-suggestion').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const text = btn.dataset.text;
                this.copySuggestion(text);
            });
        });
        
        // Click en el item completo
        document.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                const text = item.querySelector('.suggestion-text').textContent;
                this.useSuggestion(text);
            });
        });
    }
    
    /**
     * Usar una sugerencia
     */
    useSuggestion(text) {
        // Enviar el texto al campo de mensaje del generador
        const messageText = document.getElementById('messageText');
        if (messageText) {
            messageText.value = text;
            messageText.focus();
            
            // Scroll al generador si es necesario
            const generatorCard = document.querySelector('.create-message');
            if (generatorCard) {
                generatorCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Notificar al usuario
            this.showNotification('Sugerencia aplicada al generador');
        }
    }
    
    /**
     * Copiar sugerencia al portapapeles
     */
    async copySuggestion(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showNotification('Sugerencia copiada al portapapeles');
        } catch (err) {
            console.error('Error al copiar:', err);
            this.showNotification('Error al copiar la sugerencia', 'error');
        }
    }
    
    /**
     * Mostrar notificación
     */
    showNotification(message, type = 'success') {
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${type === 'success' ? 'var(--success-color, #10b981)' : 'var(--error-color, #ef4444)'};
            color: white;
            border-radius: 8px;
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        // Remover después de 3 segundos
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    /**
     * Calcular tiempo transcurrido
     */
    getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        
        if (seconds < 60) return 'Hace un momento';
        
        const intervals = {
            año: 31536000,
            mes: 2592000,
            semana: 604800,
            día: 86400,
            hora: 3600,
            minuto: 60
        };
        
        for (const [name, secondsInInterval] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / secondsInInterval);
            if (interval >= 1) {
                return `Hace ${interval} ${name}${interval > 1 ? (name === 'mes' ? 'es' : 's') : ''}`;
            }
        }
        
        return 'Hace un momento';
    }
    
    /**
     * Obtener etiqueta de categoría
     */
    getCategoryLabel(category) {
        const labels = {
            'ofertas': 'Ofertas',
            'eventos': 'Eventos',
            'informacion': 'Info',
            'servicios': 'Servicios',
            'horarios': 'Horarios',
            'emergencias': 'Emergencia',
            'general': 'General'
        };
        return labels[category] || category;
    }
    
    /**
     * Escapar HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Mostrar error
     */
    showError(message) {
        const listContainer = document.getElementById('suggestionsList');
        listContainer.innerHTML = `
            <div class="error-state" style="text-align: center; padding: 2rem; color: var(--error-color, #ef4444);">
                <p>❌ ${message}</p>
            </div>
        `;
    }
}