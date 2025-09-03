/**
 * CalendarLoader Component - Maneja estados de carga y notificaciones
 * @module CalendarLoader
 * 
 * Responsabilidades:
 * - Estados de loading
 * - Mensajes de error/éxito
 * - Notificaciones del sistema
 * - Feedback visual
 */

export class CalendarLoader {
    constructor(container, eventBus) {
        this.container = container;
        this.eventBus = eventBus;
        
        // Referencias DOM
        this.loadingOverlay = null;
        this.notificationContainer = null;
        
        // Estado
        this.isLoading = false;
        this.activeNotifications = new Map();
        
        // Timeouts
        this.notificationTimeout = 3000;
    }
    
    /**
     * Inicializa el componente
     */
    init() {
        // Obtener o crear el overlay de loading
        this.loadingOverlay = this.container.querySelector('.calendar-loading');
        
        // Crear contenedor de notificaciones si no existe
        if (!document.getElementById('calendar-notifications')) {
            this.createNotificationContainer();
        }
        
        this.notificationContainer = document.getElementById('calendar-notifications');
        
        return true;
    }
    
    /**
     * Crea el contenedor de notificaciones
     */
    createNotificationContainer() {
        const container = document.createElement('div');
        container.id = 'calendar-notifications';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }
    
    /**
     * Muestra el overlay de loading
     */
    showLoading(message = 'Cargando...') {
        this.isLoading = true;
        
        if (this.loadingOverlay) {
            // Actualizar mensaje si es necesario
            const messageElement = this.loadingOverlay.querySelector('p');
            if (messageElement) {
                messageElement.textContent = message;
            }
            
            // Mostrar overlay
            this.loadingOverlay.style.display = 'flex';
        } else {
            // Crear overlay temporal si no existe
            this.createTemporaryLoader(message);
        }
    }
    
    /**
     * Oculta el overlay de loading
     */
    hideLoading() {
        this.isLoading = false;
        
        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = 'none';
        }
        
        // Remover cualquier loader temporal
        const tempLoader = document.getElementById('temp-calendar-loader');
        if (tempLoader) {
            tempLoader.remove();
        }
    }
    
    /**
     * Crea un loader temporal
     */
    createTemporaryLoader(message) {
        const loader = document.createElement('div');
        loader.id = 'temp-calendar-loader';
        loader.className = 'calendar-loading';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;
        
        loader.innerHTML = `
            <div style="background: var(--bg-primary, #1e293b); padding: 2rem; border-radius: 8px; text-align: center;">
                <div class="loading-spinner" style="margin: 0 auto 1rem;"></div>
                <p style="color: var(--text-primary, #ffffff); margin: 0;">${message}</p>
            </div>
        `;
        
        document.body.appendChild(loader);
    }
    
    /**
     * Muestra una notificación
     */
    showNotification(message, type = 'info', duration = null) {
        // Usar el event bus para notificaciones globales si está disponible
        if (this.eventBus) {
            this.eventBus.emit('ui:notification', { 
                message, 
                type, 
                duration: duration || this.notificationTimeout 
            });
            return;
        }
        
        // Fallback a notificación local
        this.showLocalNotification(message, type, duration);
    }
    
    /**
     * Muestra una notificación local
     */
    showLocalNotification(message, type = 'info', duration = null) {
        if (!this.notificationContainer) {
            console.warn('[CalendarLoader] Notification container not found');
            return;
        }
        
        const id = Date.now().toString();
        const notification = document.createElement('div');
        notification.id = `notification-${id}`;
        notification.className = `calendar-notification notification-${type}`;
        
        // Estilos según el tipo
        const styles = this.getNotificationStyles(type);
        notification.style.cssText = `
            ${styles}
            padding: 1rem 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 300px;
            max-width: 500px;
            animation: slideIn 0.3s ease-out;
            pointer-events: auto;
            cursor: pointer;
            transition: all 0.3s ease;
        `;
        
        // Icono según tipo
        const icon = this.getNotificationIcon(type);
        
        notification.innerHTML = `
            <span style="font-size: 1.25rem;">${icon}</span>
            <span style="flex: 1;">${message}</span>
            <button onclick="this.parentElement.remove()" style="
                background: none;
                border: none;
                color: inherit;
                cursor: pointer;
                font-size: 1.25rem;
                padding: 0;
                margin-left: 0.5rem;
                opacity: 0.7;
                transition: opacity 0.2s;
            " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">×</button>
        `;
        
        // Agregar al contenedor
        this.notificationContainer.appendChild(notification);
        this.activeNotifications.set(id, notification);
        
        // Click para cerrar
        notification.addEventListener('click', () => {
            this.removeNotification(id);
        });
        
        // Auto-remover después del timeout
        if (duration !== 0) {
            const timeout = duration || this.notificationTimeout;
            setTimeout(() => {
                this.removeNotification(id);
            }, timeout);
        }
    }
    
    /**
     * Obtiene los estilos según el tipo de notificación
     */
    getNotificationStyles(type) {
        const baseStyles = `
            background: var(--bg-primary, #1e293b);
            color: var(--text-primary, #ffffff);
            border: 1px solid var(--border-color, rgba(255,255,255,0.1));
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        
        switch(type) {
            case 'success':
                return `${baseStyles} background: #10b98120; border-color: #10b981;`;
            case 'error':
                return `${baseStyles} background: #ef444420; border-color: #ef4444;`;
            case 'warning':
                return `${baseStyles} background: #f59e0b20; border-color: #f59e0b;`;
            case 'info':
            default:
                return `${baseStyles} background: #3b82f620; border-color: #3b82f6;`;
        }
    }
    
    /**
     * Obtiene el icono según el tipo de notificación
     */
    getNotificationIcon(type) {
        switch(type) {
            case 'success': return '✅';
            case 'error': return '❌';
            case 'warning': return '⚠️';
            case 'info': 
            default: return 'ℹ️';
        }
    }
    
    /**
     * Remueve una notificación
     */
    removeNotification(id) {
        const notification = this.activeNotifications.get(id);
        if (notification) {
            // Animación de salida
            notification.style.animation = 'slideOut 0.3s ease-out';
            notification.style.opacity = '0';
            
            setTimeout(() => {
                notification.remove();
                this.activeNotifications.delete(id);
            }, 300);
        }
    }
    
    /**
     * Muestra un error
     */
    showError(message, duration = 5000) {
        this.showNotification(message, 'error', duration);
    }
    
    /**
     * Muestra éxito
     */
    showSuccess(message, duration = 3000) {
        this.showNotification(message, 'success', duration);
    }
    
    /**
     * Muestra advertencia
     */
    showWarning(message, duration = 4000) {
        this.showNotification(message, 'warning', duration);
    }
    
    /**
     * Muestra información
     */
    showInfo(message, duration = 3000) {
        this.showNotification(message, 'info', duration);
    }
    
    /**
     * Muestra estado de progreso con porcentaje
     */
    showProgress(message, percentage) {
        const progressId = 'calendar-progress';
        let progressBar = document.getElementById(progressId);
        
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.id = progressId;
            progressBar.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: var(--bg-primary, #1e293b);
                border: 1px solid var(--border-color, rgba(255,255,255,0.1));
                border-radius: 8px;
                padding: 1rem;
                min-width: 300px;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            `;
            document.body.appendChild(progressBar);
        }
        
        progressBar.innerHTML = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="color: var(--text-primary, #ffffff);">${message}</span>
                <span style="color: var(--text-secondary, #94a3b8);">${percentage}%</span>
            </div>
            <div style="background: var(--bg-secondary, #334155); height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="
                    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
                    height: 100%;
                    width: ${percentage}%;
                    transition: width 0.3s ease;
                "></div>
            </div>
        `;
        
        if (percentage >= 100) {
            setTimeout(() => {
                progressBar.remove();
            }, 1000);
        }
    }
    
    /**
     * Limpieza del componente
     */
    destroy() {
        // Limpiar notificaciones activas
        this.activeNotifications.forEach(notification => {
            notification.remove();
        });
        this.activeNotifications.clear();
        
        // Remover contenedor de notificaciones si fue creado
        if (this.notificationContainer && this.notificationContainer.id === 'calendar-notifications') {
            this.notificationContainer.remove();
        }
        
        // Remover cualquier loader temporal
        const tempLoader = document.getElementById('temp-calendar-loader');
        if (tempLoader) {
            tempLoader.remove();
        }
        
        // Remover barra de progreso si existe
        const progressBar = document.getElementById('calendar-progress');
        if (progressBar) {
            progressBar.remove();
        }
        
        // Limpiar referencias
        this.loadingOverlay = null;
        this.notificationContainer = null;
    }
}

// Agregar estilos de animación si no existen
if (!document.getElementById('calendar-loader-animations')) {
    const style = document.createElement('style');
    style.id = 'calendar-loader-animations';
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
}