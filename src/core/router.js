// /var/www/tts-mall/shared/router.js
/**
 * Router - Sistema de navegación hash-based
 * @module Router
 */

import { moduleLoader } from './module-loader.js';
import { eventBus, SystemEvents } from './event-bus.js';

class Router {
    constructor() {
        this.routes = new Map();
        this.currentRoute = null;
        this.defaultRoute = '/radio';
        this.initialized = false;
        this.routes.set('/calendario', 'calendar');
        // Agregar después de las otras rutas
 // this.routes.set('/calendario', 'calendar');


    }
    
    /**
     * Inicializa el router
     */
    init() {
        if (this.initialized) return;
        
        // Definir rutas
        this.routes.set('/configuracion', 'message-configurator');
        this.routes.set('/campanas', 'campaign-library');
        this.routes.set('/historial', 'audio-history');
        this.routes.set('/archivo', 'audio-archive');
        this.routes.set('/radio', 'radio'); 
        this.routes.set("/dashboard-v2", "dashboard-v2");
        
        // Escuchar cambios de hash
        window.addEventListener('hashchange', () => this.handleRoute());
        
        // Manejar ruta inicial
        this.handleRoute();
        
        this.initialized = true;
        console.log('[Router] Initialized');
    }
    
    /**
     * Navega a una ruta específica
     * @param {string} path - Ruta a navegar
     */
    navigate(path) {
        if (!this.routes.has(path)) {
            console.warn(`[Router] Unknown route: ${path}`);
            path = this.defaultRoute;
        }
        
        window.location.hash = path;
    }
    
    /**
     * Maneja cambio de ruta
     * @private
     */
    async handleRoute() {
        // Obtener ruta del hash
        let path = window.location.hash.slice(1) || this.defaultRoute;
        
        // Validar ruta
        if (!this.routes.has(path)) {
            console.warn(`[Router] Invalid route: ${path}, redirecting to default`);
            path = this.defaultRoute;
            window.location.hash = path;
            return;
        }
        
        // Si es la misma ruta, no hacer nada
        if (path === this.currentRoute) {
            return;
        }
        
        console.log(`[Router] Navigating to: ${path}`);
        
        // Obtener módulo correspondiente
        const moduleName = this.routes.get(path);
        
        try {
            // Cambiar al módulo
            await moduleLoader.switchTo(moduleName);
            
            // Actualizar ruta actual
            const previousRoute = this.currentRoute;
            this.currentRoute = path;
            
            // Emitir evento de navegación
            eventBus.emit(SystemEvents.NAVIGATION_CHANGE, {
                from: previousRoute,
                to: path,
                module: moduleName
            });
            
            // Actualizar título de página
            this.updatePageTitle(path);
            
        } catch (error) {
            console.error(`[Router] Error loading route ${path}:`, error);
            
            // Si falla, intentar ir a default
            if (path !== this.defaultRoute) {
                this.navigate(this.defaultRoute);
            }
        }
    }
    
    /**
     * Actualiza el título de la página según la ruta
     * @private
     */
    updatePageTitle(path) {
        const titles = {
            '/configuracion': 'Configuración de Mensajes - TTS Mall',
            '/campanas': 'Campañas y Mensajes - TTS Mall',
            '/historial': 'Historial de Audio - TTS Mall'
        };
        
        document.title = titles[path] || 'TTS Mall';
    }
    
    /**
     * Obtiene la ruta actual
     * @returns {string} Ruta actual
     */
    getCurrentRoute() {
        return this.currentRoute;
    }
    
    /**
     * Obtiene el módulo de una ruta
     * @param {string} path - Ruta
     * @returns {string|null} Nombre del módulo
     */
    getModuleForRoute(path) {
        return this.routes.get(path) || null;
    }
    
    /**
     * Agrega una ruta custom
     * @param {string} path - Ruta
     * @param {string} moduleName - Nombre del módulo
     */
    addRoute(path, moduleName) {
        this.routes.set(path, moduleName);
        console.log(`[Router] Added route: ${path} -> ${moduleName}`);
    }
    
    /**
     * Vuelve a la ruta anterior
     */
    back() {
        window.history.back();
    }
    
    /**
     * Avanza en el historial
     */
    forward() {
        window.history.forward();
    }
}

// Singleton
const router = new Router();

export { router };