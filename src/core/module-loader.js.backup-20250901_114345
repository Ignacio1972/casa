// /var/www/tts-mall/v2/shared/module-loader.js
/**
 * Module Loader - Sistema de carga dinámica de módulos
 * @module ModuleLoader
 */

import { eventBus, SystemEvents } from './event-bus.js';

class ModuleLoader {
    constructor() {
        this.modules = new Map();
        this.currentModule = null;
        this.container = null;
        this.loadingState = new Map();
    }
    
    /**
     * Inicializa el loader con el contenedor principal
     * @param {HTMLElement} container - Elemento DOM contenedor
     */
    init(container) {
        this.container = container;
        console.log('[ModuleLoader] Initialized');
    }
    
    /**
     * Carga los estilos CSS de un módulo
     * @param {string} moduleName - Nombre del módulo
     * @private
     */
    async loadModuleStyles(moduleName) {
        const styleId = `module-style-${moduleName}`;
        
        // Remover estilos previos de otros módulos
        document.querySelectorAll('[id^="module-style-"]').forEach(link => {
            if (link.id !== styleId) {
                link.remove();
            }
        });
        
        // Verificar si ya existe
        let styleLink = document.getElementById(styleId);
        
        if (!styleLink) {
            // Crear nuevo link
            styleLink = document.createElement('link');
            styleLink.id = styleId;
            styleLink.rel = 'stylesheet';
            styleLink.href = `/modules/${moduleName}/style.css`;
            
            console.log(`[ModuleLoader] Loading styles for ${moduleName}`);
            
            // Agregar al head
            document.head.appendChild(styleLink);
            
            // Esperar a que cargue
            await new Promise((resolve, reject) => {
                styleLink.onload = () => {
                    console.log(`[ModuleLoader] Styles loaded for ${moduleName}`);
                    resolve();
                };
                styleLink.onerror = () => {
                    console.warn(`[ModuleLoader] No style.css found for ${moduleName}`);
                    resolve(); // No es error crítico
                };
                
                // Timeout fallback
                setTimeout(resolve, 1000);
            });
        }
    }
    
    /**
     * Carga un módulo dinámicamente
     * @param {string} moduleName - Nombre del módulo
     * @returns {Promise<Object>} Instancia del módulo
     */
    async loadModule(moduleName) {
        console.log(`[ModuleLoader] Loading module: ${moduleName}`);
        
        // Si ya está cargado, retornarlo
        if (this.modules.has(moduleName)) {
            console.log(`[ModuleLoader] Module ${moduleName} already cached`);
            return this.modules.get(moduleName);
        }
        
        // Prevenir carga duplicada
        if (this.loadingState.get(moduleName) === 'loading') {
            console.warn(`[ModuleLoader] Module ${moduleName} is already loading`);
            return null;
        }
        
        this.loadingState.set(moduleName, 'loading');
        
        try {
            // Path actualizado para v2
            const modulePath = `/modules/${moduleName}/index.js`;
            
            // Import dinámico
            const moduleExport = await import(modulePath);
            const ModuleClass = moduleExport.default;
            
            // Validar que es un módulo válido
            if (!ModuleClass || typeof ModuleClass !== 'function') {
                throw new Error(`Invalid module export from ${moduleName}`);
            }
            
            // Crear instancia
            const moduleInstance = new ModuleClass();
            
            // Validar interfaz del módulo
            this.validateModuleInterface(moduleInstance, moduleName);
            
            // Cachear módulo
            this.modules.set(moduleName, moduleInstance);
            this.loadingState.set(moduleName, 'loaded');
            
            // Emitir evento
            eventBus.emit(SystemEvents.MODULE_LOADED, {
                name: moduleName,
                instance: moduleInstance
            });
            
            console.log(`[ModuleLoader] Module ${moduleName} loaded successfully`);
            return moduleInstance;
            
        } catch (error) {
            console.error(`[ModuleLoader] Failed to load module ${moduleName}:`, error);
            this.loadingState.set(moduleName, 'error');
            
            eventBus.emit(SystemEvents.MODULE_ERROR, {
                name: moduleName,
                error: error.message
            });
            
            throw error;
        }
    }
    
    /**
     * Descarga un módulo y limpia recursos
     * @param {string} moduleName - Nombre del módulo
     */
    async unloadModule(moduleName) {
        const module = this.modules.get(moduleName);
        
        if (!module) {
            console.warn(`[ModuleLoader] Module ${moduleName} not loaded`);
            return;
        }
        
        console.log(`[ModuleLoader] Unloading module: ${moduleName}`);
        
        try {
            // Llamar al método unload del módulo
            if (typeof module.unload === 'function') {
                await module.unload();
            }
            
            // Si es el módulo actual, limpiar container
            if (this.currentModule === module) {
                this.clearContainer();
                this.currentModule = null;
            }
            
            // Eliminar del cache
            this.modules.delete(moduleName);
            this.loadingState.delete(moduleName);
            
            // Limpiar eventos del módulo
            eventBus.clear(`${moduleName}:*`);
            
            // Emitir evento
            eventBus.emit(SystemEvents.MODULE_UNLOADED, { name: moduleName });
            
            console.log(`[ModuleLoader] Module ${moduleName} unloaded`);
            
        } catch (error) {
            console.error(`[ModuleLoader] Error unloading module ${moduleName}:`, error);
        }
    }
    
    /**
     * Cambia al módulo especificado
     * @param {string} moduleName - Nombre del módulo
     */
    async switchTo(moduleName) {
        console.log(`[ModuleLoader] Switching to module: ${moduleName}`);
        
        try {
            // Emitir evento de loading
            eventBus.emit('ui:loading:start', { module: moduleName });
            
            // Descargar módulo actual si existe
            if (this.currentModule) {
                const currentName = this.getCurrentModuleName();
                if (currentName) {
                    await this.unloadModule(currentName);
                }
            }
            
            // NUEVO: Cargar estilos del módulo ANTES de cargar el módulo
            await this.loadModuleStyles(moduleName);
            
            // Cargar nuevo módulo
            const module = await this.loadModule(moduleName);
            
            if (!module) {
                throw new Error(`Failed to load module ${moduleName}`);
            }
            
            // Limpiar container
            this.clearContainer();
            
            // Montar nuevo módulo
            if (typeof module.load === 'function') {
                await module.load(this.container);
            }
            
            this.currentModule = module;
            
            console.log(`[ModuleLoader] Switched to module: ${moduleName}`);
            
        } catch (error) {
            console.error(`[ModuleLoader] Error switching to module ${moduleName}:`, error);
            
            // Mostrar error en container
            this.showErrorInContainer(moduleName, error);
            
            throw error;
        } finally {
            // Emitir evento de loading end
            eventBus.emit('ui:loading:end', { module: moduleName });
        }
    }
    
    /**
     * Muestra un error en el container cuando falla la carga
     * @private
     */
    showErrorInContainer(moduleName, error) {
        if (!this.container) return;
        
        this.clearContainer();
        
        const errorHtml = `
            <div style="
                padding: 40px;
                text-align: center;
                color: #dc3545;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            ">
                <h2 style="margin-bottom: 20px;">
                    ⚠️ Error al cargar módulo
                </h2>
                <p style="color: #6c757d; margin-bottom: 10px;">
                    No se pudo cargar el módulo: <strong>${moduleName}</strong>
                </p>
                <p style="color: #6c757d; font-size: 14px;">
                    ${error.message || 'Error desconocido'}
                </p>
                <button onclick="window.location.hash = '/configuracion'" style="
                    margin-top: 20px;
                    padding: 10px 20px;
                    background: #007bff;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                ">
                    Volver al inicio
                </button>
            </div>
        `;
        
        this.container.innerHTML = errorHtml;
    }
    
    /**
     * Obtiene el nombre del módulo actual
     * @returns {string|null} Nombre del módulo actual
     */
    getCurrentModuleName() {
        if (!this.currentModule) return null;
        
        for (const [name, module] of this.modules.entries()) {
            if (module === this.currentModule) {
                return name;
            }
        }
        
        return null;
    }
    
    /**
     * Valida que el módulo implemente la interfaz requerida
     * @private
     */
    validateModuleInterface(module, moduleName) {
        const requiredMethods = ['load', 'unload', 'getName'];
        const missing = requiredMethods.filter(method => 
            typeof module[method] !== 'function'
        );
        
        if (missing.length > 0) {
            throw new Error(
                `Module ${moduleName} missing required methods: ${missing.join(', ')}`
            );
        }
    }
    
    /**
     * Limpia el contenedor
     * @private
     */
    clearContainer() {
        if (this.container) {
            this.container.innerHTML = '';
            // Remover todos los event listeners del container
            const newContainer = this.container.cloneNode(false);
            this.container.parentNode.replaceChild(newContainer, this.container);
            this.container = newContainer;
        }
    }
    
    /**
     * Obtiene información de todos los módulos cargados
     */
    getLoadedModules() {
        const info = {};
        this.modules.forEach((module, name) => {
            info[name] = {
                loaded: true,
                current: module === this.currentModule,
                state: this.loadingState.get(name) || 'unknown'
            };
        });
        return info;
    }
}

// Singleton
const moduleLoader = new ModuleLoader();

export { moduleLoader };