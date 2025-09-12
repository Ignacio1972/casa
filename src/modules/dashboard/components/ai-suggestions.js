/**
 * AI Suggestions Component
 * Componente para mostrar y gestionar sugerencias de IA
 */

import { llmService } from '../../../core/llm-service.js';
import { eventBus } from '../../../core/event-bus.js';

export class AISuggestionsComponent {
    constructor(dashboard) {
        this.dashboard = dashboard;
        this.container = null;
        this.suggestions = [];
        this.selectedSuggestion = null;
        this.isVisible = true;
        this.isGenerating = false;
        
        // Configuración de contexto
        this.contextConfig = {
            showAdvanced: false,
            tone: 'profesional',
            duration: 30,
            keywords: []
        };
        
        this.setupEventListeners();
    }
    
    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Escuchar eventos del LLM
        eventBus.on('llm:suggestions:generated', (data) => {
            this.handleSuggestionsGenerated(data);
        });
        
        eventBus.on('llm:suggestion:updated', (data) => {
            this.updateSuggestionCard(data.id, data.suggestion);
        });
        
        eventBus.on('llm:suggestion:regenerated', (data) => {
            this.updateSuggestionCard(data.id, data.suggestion);
        });
    }
    
    /**
     * Renderizar el componente completo
     */
    render() {
        return `
            <div id="aiSuggestionsContainer" class="card ai-suggestions-container visible">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="card-icon">🤖</span>
                        ¿Qué necesitas anunciar?
                    </h2>
                </div>
                <!-- Panel de configuración siempre visible -->
                <div id="aiPanel" class="ai-panel" style="display: block;">
                    ${this.renderConfigPanel()}
                    ${this.renderSuggestionsPanel()}
                </div>
            </div>
        `;
    }
    
    /**
     * Renderizar panel de configuración
     */
    renderConfigPanel() {
        return `
            <div class="ai-config-panel">
                <div class="ai-config-body">
                    <!-- Contexto principal -->
                    <div class="ai-field">
                        <textarea 
                            id="aiContext" 
                            class="ai-context-input"
                            placeholder="Ej: Promoción 2x1 en restaurantes este fin de semana, válido solo sábado y domingo"
                            rows="3"
                        ></textarea>
                    </div>
                    
                    <!-- Configuración rápida -->
                    <div class="ai-quick-config">
                        <div class="ai-field-row">
                            <div class="ai-field">
                                <label for="aiTone">Tono</label>
                                <select id="aiTone" class="ai-select">
                                    <option value="profesional">Profesional</option>
                                    <option value="entusiasta">Entusiasta</option>
                                    <option value="amigable">Amigable</option>
                                    <option value="urgente">Urgente</option>
                                    <option value="informativo">Informativo</option>
                                </select>
                            </div>
                            
                            <div class="ai-field">
                                <label for="aiDuration">Duración (seg)</label>
                                <select id="aiDuration" class="ai-select">
                                    <option value="5">5 segundos</option>
                                    <option value="10" selected>10 segundos</option>
                                    <option value="15">15 segundos</option>
                                    <option value="20">20 segundos</option>
                                    <option value="25">25 segundos</option>
                                </select>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Configuración avanzada (colapsable) -->
                    <div class="ai-advanced">
                        <button 
                            class="ai-advanced-toggle"
                            onclick="window.dashboardV2.aiSuggestions.toggleAdvanced()"
                        >
                            <span class="toggle-icon">▶</span> Opciones avanzadas
                        </button>
                        
                        <div id="aiAdvancedOptions" class="ai-advanced-options" style="display: none;">
                            <div class="ai-field">
                                <label for="aiKeywords">Palabras clave (separadas por coma)</label>
                                <input 
                                    type="text" 
                                    id="aiKeywords" 
                                    class="ai-input"
                                    placeholder="Ej: descuento, fin de semana, restaurantes"
                                />
                            </div>
                            
                            <div class="ai-field">
                                <label for="aiTemperature">Creatividad</label>
                                <div class="ai-slider-container">
                                    <input 
                                        type="range" 
                                        id="aiTemperature" 
                                        min="0" 
                                        max="100" 
                                        value="80"
                                        class="ai-slider"
                                    />
                                    <span id="aiTempValue">80%</span>
                                </div>
                                <small class="ai-help">Más alto = más creativo y variado</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botón generar -->
                    <button 
                        id="aiGenerateBtn" 
                        class="btn btn-ai-generate"
                        onclick="window.dashboardV2.aiSuggestions.generate()"
                        ${this.isGenerating ? 'disabled' : ''}
                    >
                        ${this.isGenerating ? 
                            '<span class="spinner"></span> Generando...' : 
                            '✨ Generar 3 Sugerencias'
                        }
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * Renderizar panel de sugerencias
     */
    renderSuggestionsPanel() {
        if (!this.suggestions || this.suggestions.length === 0) {
            return `
                <div id="aiSuggestionsPanel" class="ai-suggestions-panel empty">
                    <div class="ai-empty-state">
                        <span class="empty-icon">💡</span>
                        <p>Las sugerencias aparecerán aquí</p>
                        <small>Completa el contexto y presiona "Generar"</small>
                    </div>
                </div>
            `;
        }
        
        return `
            <div id="aiSuggestionsPanel" class="ai-suggestions-panel">
                <div class="ai-suggestions-header">
                    <h4>Sugerencias Generadas</h4>
                    <button 
                        class="btn-text"
                        onclick="window.dashboardV2.aiSuggestions.clearSuggestions()"
                    >
                        Limpiar
                    </button>
                </div>
                
                <div class="ai-suggestions-list">
                    ${this.suggestions.map(suggestion => this.renderSuggestionCard(suggestion)).join('')}
                </div>
            </div>
        `;
    }
    
    /**
     * Renderizar tarjeta de sugerencia
     */
    renderSuggestionCard(suggestion) {
        const isSelected = this.selectedSuggestion?.id === suggestion.id;
        
        return `
            <div class="ai-suggestion-card ${isSelected ? 'selected' : ''}" data-suggestion-id="${suggestion.id}">
                <div class="suggestion-content">
                    <div class="suggestion-text" contenteditable="true" data-id="${suggestion.id}">
                        ${suggestion.text}
                    </div>
                    <div class="suggestion-meta">
                        <span class="word-count">📝 ${suggestion.word_count} palabras</span>
                        <span class="char-count">📏 ${suggestion.char_count} caracteres</span>
                        ${suggestion.edited ? '<span class="edited-badge">✏️ Editado</span>' : ''}
                    </div>
                </div>
                
                <div class="suggestion-actions">
                    <button 
                        class="btn-suggestion-action btn-use"
                        onclick="window.dashboardV2.aiSuggestions.useSuggestion('${suggestion.id}')"
                        title="Usar este texto"
                    >
                        ✓ Usar
                    </button>
                    
                    <button 
                        class="btn-suggestion-action btn-regenerate"
                        onclick="window.dashboardV2.aiSuggestions.regenerateSuggestion('${suggestion.id}')"
                        title="Regenerar esta sugerencia"
                    >
                        🔄
                    </button>
                    
                    <button 
                        class="btn-suggestion-action btn-copy"
                        onclick="window.dashboardV2.aiSuggestions.copySuggestion('${suggestion.id}')"
                        title="Copiar al portapapeles"
                    >
                        📋
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * Mostrar/ocultar el panel
     */
    toggle() {
        this.isVisible = !this.isVisible;
        this.updateVisibility();
    }
    
    show() {
        this.isVisible = true;
        this.updateVisibility();
    }
    
    hide() {
        this.isVisible = false;
        this.updateVisibility();
    }
    
    updateVisibility() {
        const container = document.getElementById('aiSuggestionsContainer');
        if (container) {
            container.classList.toggle('visible', this.isVisible);
        }
    }
    
    /**
     * Mostrar/ocultar opciones avanzadas
     */
    toggleAdvanced() {
        this.contextConfig.showAdvanced = !this.contextConfig.showAdvanced;
        const advancedOptions = document.getElementById('aiAdvancedOptions');
        const toggleIcon = document.querySelector('.ai-advanced-toggle .toggle-icon');
        
        if (advancedOptions) {
            advancedOptions.style.display = this.contextConfig.showAdvanced ? 'block' : 'none';
        }
        
        if (toggleIcon) {
            toggleIcon.textContent = this.contextConfig.showAdvanced ? '▼' : '▶';
        }
    }
    
    /**
     * Generar sugerencias
     */
    async generate() {
        if (this.isGenerating) return;
        
        // Obtener valores del formulario
        const context = document.getElementById('aiContext').value.trim();
        if (!context) {
            alert('Por favor, describe qué necesitas anunciar');
            return;
        }
        
        this.isGenerating = true;
        this.updateGenerateButton();
        
        try {
            // Preparar parámetros
            const params = {
                context: context,
                category: this.dashboard.state.selectedCategory,
                tone: document.getElementById('aiTone').value,
                duration: parseInt(document.getElementById('aiDuration').value),
                // El modelo se define en el servidor (claude-service.php)
                keywords: document.getElementById('aiKeywords')?.value.split(',').map(k => k.trim()).filter(k => k) || [],
                temperature: (document.getElementById('aiTemperature')?.value || 80) / 100
            };
            
            // Generar con el servicio
            const suggestions = await llmService.generateAnnouncements(params);
            
            if (suggestions && suggestions.length > 0) {
                this.suggestions = suggestions;
                this.renderSuggestions();
            } else {
                throw new Error('No se generaron sugerencias');
            }
            
        } catch (error) {
            console.error('[AISuggestions] Error generando:', error);
            alert('Error al generar sugerencias: ' + error.message);
            
        } finally {
            this.isGenerating = false;
            this.updateGenerateButton();
        }
    }
    
    /**
     * Manejar sugerencias generadas
     */
    handleSuggestionsGenerated(data) {
        this.suggestions = data.suggestions;
        this.renderSuggestions();
    }
    
    /**
     * Renderizar sugerencias actualizadas
     */
    renderSuggestions() {
        const panel = document.getElementById('aiSuggestionsPanel');
        if (panel) {
            panel.outerHTML = this.renderSuggestionsPanel();
            this.attachEditListeners();
        }
    }
    
    /**
     * Actualizar botón de generar
     */
    updateGenerateButton() {
        const btn = document.getElementById('aiGenerateBtn');
        if (btn) {
            btn.disabled = this.isGenerating;
            btn.innerHTML = this.isGenerating ? 
                '<span class="spinner"></span> Generando...' : 
                '✨ Generar 3 Sugerencias';
        }
    }
    
    /**
     * Usar una sugerencia
     */
    useSuggestion(id) {
        const suggestion = llmService.selectSuggestion(id);
        if (suggestion) {
            // Poner el texto en el campo principal
            this.dashboard.elements.messageText.value = suggestion.text;
            
            // Marcar como seleccionada
            this.selectedSuggestion = suggestion;
            
            // Actualizar UI
            document.querySelectorAll('.ai-suggestion-card').forEach(card => {
                card.classList.toggle('selected', card.dataset.suggestionId === id);
            });
            
            // Opcionalmente cerrar el panel
            setTimeout(() => this.hide(), 500);
            
            // Enfocar el botón de generar audio
            this.dashboard.elements.generateBtn.focus();
        }
    }
    
    /**
     * Regenerar una sugerencia
     */
    async regenerateSuggestion(id) {
        const card = document.querySelector(`[data-suggestion-id="${id}"]`);
        if (card) {
            card.classList.add('regenerating');
        }
        
        try {
            await llmService.regenerateSuggestion(id);
        } catch (error) {
            console.error('[AISuggestions] Error regenerando:', error);
            alert('Error al regenerar la sugerencia');
        } finally {
            if (card) {
                card.classList.remove('regenerating');
            }
        }
    }
    
    /**
     * Copiar sugerencia al portapapeles
     */
    copySuggestion(id) {
        const suggestion = llmService.getSuggestion(id);
        if (suggestion) {
            navigator.clipboard.writeText(suggestion.text).then(() => {
                // Mostrar feedback visual
                const btn = document.querySelector(`[data-suggestion-id="${id}"] .btn-copy`);
                if (btn) {
                    const originalText = btn.textContent;
                    btn.textContent = '✅';
                    setTimeout(() => {
                        btn.textContent = originalText;
                    }, 1500);
                }
            });
        }
    }
    
    /**
     * Actualizar tarjeta de sugerencia
     */
    updateSuggestionCard(id, suggestion) {
        const card = document.querySelector(`[data-suggestion-id="${id}"]`);
        if (card) {
            card.outerHTML = this.renderSuggestionCard(suggestion);
            this.attachEditListeners();
        }
    }
    
    /**
     * Limpiar todas las sugerencias
     */
    clearSuggestions() {
        this.suggestions = [];
        this.selectedSuggestion = null;
        llmService.clearSuggestions();
        this.renderSuggestions();
    }
    
    /**
     * Adjuntar listeners para edición inline
     */
    attachEditListeners() {
        document.querySelectorAll('.suggestion-text[contenteditable="true"]').forEach(element => {
            element.addEventListener('blur', (e) => {
                const id = e.target.dataset.id;
                const newText = e.target.textContent.trim();
                
                if (newText) {
                    const updated = llmService.updateSuggestion(id, newText);
                    if (updated) {
                        // Actualizar metadata
                        const card = e.target.closest('.ai-suggestion-card');
                        if (card) {
                            const wordCount = card.querySelector('.word-count');
                            const charCount = card.querySelector('.char-count');
                            
                            if (wordCount) wordCount.textContent = `📝 ${updated.word_count} palabras`;
                            if (charCount) charCount.textContent = `📏 ${updated.char_count} caracteres`;
                            
                            // Agregar badge de editado si no existe
                            if (!card.querySelector('.edited-badge')) {
                                const meta = card.querySelector('.suggestion-meta');
                                if (meta) {
                                    meta.innerHTML += '<span class="edited-badge">✏️ Editado</span>';
                                }
                            }
                        }
                    }
                }
            });
        });
    }
    
    /**
     * Montar el componente en el dashboard
     */
    mount(containerId) {
        // Buscar el placeholder específico para el componente AI
        const placeholder = document.getElementById('ai-suggestions-placeholder');
        if (placeholder) {
            // Reemplazar el placeholder con el componente AI
            placeholder.innerHTML = this.render();
            
            // Configurar listeners del slider
            const tempSlider = document.getElementById('aiTemperature');
            if (tempSlider) {
                tempSlider.addEventListener('input', (e) => {
                    document.getElementById('aiTempValue').textContent = e.target.value + '%';
                });
            }
        } else {
            console.warn('[AI Suggestions] No se encontró el placeholder para montar el componente');
        }
    }
}

export default AISuggestionsComponent;