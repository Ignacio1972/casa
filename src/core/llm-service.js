/**
 * LLM Service - Servicio de Inteligencia Artificial
 * Integración con Claude para generación de anuncios
 */

import { apiClient } from './api-client.js';
import { eventBus } from './event-bus.js';

export class LLMService {
    constructor() {
        // Como apiClient ya añade /api, solo necesitamos el nombre del archivo
        this.apiEndpoint = '/claude-service.php';
        this.suggestions = [];
        this.isGenerating = false;
        this.lastContext = null;
    }
    
    /**
     * Generar anuncios con IA
     */
    async generateAnnouncements(params) {
        if (this.isGenerating) {
            console.warn('[LLMService] Ya hay una generación en progreso');
            return null;
        }
        
        this.isGenerating = true;
        
        try {
            console.log('[LLMService] Generando anuncios con parámetros:', params);
            
            // Preparar contexto
            const context = this.buildContext(params);
            this.lastContext = context;
            
            // Llamar a la API
            console.log('[LLMService] Llamando a:', this.apiEndpoint, 'con contexto:', context);
            const response = await apiClient.post(this.apiEndpoint, {
                action: 'generate',
                ...context
            });
            
            if (response.success && response.suggestions) {
                this.suggestions = response.suggestions;
                
                // Emitir evento
                eventBus.emit('llm:suggestions:generated', {
                    suggestions: this.suggestions,
                    context: context
                });
                
                console.log('[LLMService] Generadas', this.suggestions.length, 'sugerencias');
                return this.suggestions;
            } else {
                throw new Error(response.error || 'Error generando sugerencias');
            }
            
        } catch (error) {
            console.error('[LLMService] Error:', error);
            eventBus.emit('llm:error', { error: error.message });
            throw error;
            
        } finally {
            this.isGenerating = false;
        }
    }
    
    /**
     * Construir contexto para la generación
     */
    buildContext(params) {
        const context = {
            category: params.category || 'general',
            context: params.context || '',
            keywords: params.keywords || [],
            tone: params.tone || 'profesional',
            duration: params.duration || 30,
            temperature: params.temperature || 0.8
            // El modelo se define en el servidor (claude-service.php)
        };
        
        // Agregar contexto específico por categoría
        const categoryContexts = {
            'ofertas': {
                keywords: ['descuento', 'ahorro', 'promoción', 'oferta'],
                tone: 'entusiasta'
            },
            'eventos': {
                keywords: ['evento', 'actividad', 'participar', 'diversión'],
                tone: 'emocionante'
            },
            'informacion': {
                keywords: ['informamos', 'importante', 'atención'],
                tone: 'informativo'
            },
            'servicios': {
                keywords: ['servicio', 'disponible', 'atención'],
                tone: 'servicial'
            },
            'horarios': {
                keywords: ['horario', 'abierto', 'atención'],
                tone: 'claro'
            },
            'emergencias': {
                keywords: ['urgente', 'importante', 'atención'],
                tone: 'serio'
            }
        };
        
        // Mezclar contexto específico de categoría
        if (categoryContexts[context.category]) {
            const catContext = categoryContexts[context.category];
            context.keywords = [...new Set([...context.keywords, ...catContext.keywords])];
            if (!params.tone) {
                context.tone = catContext.tone;
            }
        }
        
        return context;
    }
    
    /**
     * Obtener sugerencia por ID
     */
    getSuggestion(id) {
        return this.suggestions.find(s => s.id === id);
    }
    
    /**
     * Actualizar texto de una sugerencia
     */
    updateSuggestion(id, newText) {
        const suggestion = this.getSuggestion(id);
        if (suggestion) {
            suggestion.text = newText;
            suggestion.char_count = newText.length;
            suggestion.word_count = newText.split(/\s+/).filter(w => w).length;
            suggestion.edited = true;
            
            eventBus.emit('llm:suggestion:updated', { 
                id, 
                suggestion 
            });
            
            return suggestion;
        }
        return null;
    }
    
    /**
     * Regenerar una sugerencia específica
     */
    async regenerateSuggestion(id) {
        const suggestion = this.getSuggestion(id);
        if (!suggestion || !this.lastContext) {
            throw new Error('No se puede regenerar la sugerencia');
        }
        
        try {
            // Generar nueva sugerencia con el mismo contexto
            const newContext = {
                ...this.lastContext,
                context: `Genera UNA alternativa diferente para: "${suggestion.text.substring(0, 50)}..."`,
                temperature: 0.9 // Más creatividad para variación
            };
            
            const response = await apiClient.post(this.apiEndpoint, {
                action: 'generate',
                ...newContext
            });
            
            if (response.success && response.suggestions && response.suggestions.length > 0) {
                // Reemplazar la sugerencia
                const newSuggestion = response.suggestions[0];
                newSuggestion.id = id;
                const index = this.suggestions.findIndex(s => s.id === id);
                if (index !== -1) {
                    this.suggestions[index] = newSuggestion;
                }
                
                eventBus.emit('llm:suggestion:regenerated', {
                    id,
                    suggestion: newSuggestion
                });
                
                return newSuggestion;
            }
            
        } catch (error) {
            console.error('[LLMService] Error regenerando:', error);
            throw error;
        }
    }
    
    /**
     * Obtener estadísticas de uso
     */
    async getUsageStats(days = 30) {
        try {
            const response = await apiClient.post(this.apiEndpoint, {
                action: 'stats',
                days: days
            });
            
            if (response.success) {
                return response.stats;
            }
            
        } catch (error) {
            console.error('[LLMService] Error obteniendo stats:', error);
        }
        
        return [];
    }
    
    /**
     * Limpiar sugerencias
     */
    clearSuggestions() {
        this.suggestions = [];
        this.lastContext = null;
        eventBus.emit('llm:suggestions:cleared');
    }
    
    /**
     * Verificar si hay sugerencias disponibles
     */
    hasSuggestions() {
        return this.suggestions.length > 0;
    }
    
    /**
     * Obtener todas las sugerencias
     */
    getAllSuggestions() {
        return [...this.suggestions];
    }
    
    /**
     * Seleccionar sugerencia para usar
     */
    selectSuggestion(id) {
        const suggestion = this.getSuggestion(id);
        if (suggestion) {
            eventBus.emit('llm:suggestion:selected', {
                suggestion,
                context: this.lastContext
            });
            return suggestion;
        }
        return null;
    }
}

// Crear instancia singleton
const llmService = new LLMService();

// Exportar instancia y clase
export { llmService };
export default LLMService;