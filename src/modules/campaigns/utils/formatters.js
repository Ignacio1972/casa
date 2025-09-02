/**
 * Utilidades de formateo para Campaign Library
 * Métodos puros sin dependencias externas
 */

/**
 * Formatea una fecha timestamp a formato legible
 * @param {string|number|Date} timestamp - Fecha a formatear
 * @returns {string} Fecha formateada
 */
export function formatDate(timestamp) {
    if (!timestamp) return 'Fecha desconocida';
    
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    // Si es de hoy
    if (diff < 86400000 && date.getDate() === now.getDate()) {
        return `Hoy ${date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}`;
    }
    
    // Si es de ayer
    if (diff < 172800000 && date.getDate() === now.getDate() - 1) {
        return `Ayer ${date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}`;
    }
    
    // Fecha completa
    return date.toLocaleDateString('es-CL', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Escapa HTML para prevenir XSS
 * @param {string} text - Texto a escapar
 * @returns {string} Texto escapado
 */
export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Obtiene la etiqueta completa de una categoría
 * @param {string} category - ID de la categoría
 * @returns {string} Etiqueta con emoji
 */
export function getCategoryLabel(category) {
    const labels = {
        'ofertas': '🛒 Ofertas',
        'eventos': '🎉 Eventos',
        'informacion': 'ℹ️ Información',
        'emergencias': '🚨 Emergencias',
        'servicios': '🛎️ Servicios',
        'horarios': '🕐 Horarios',
        'sin-categoria': '📁 Sin categoría'
    };
    
    return labels[category] || labels['sin-categoria'];
}

/**
 * Obtiene la etiqueta corta de una categoría
 * @param {string} category - ID de la categoría
 * @returns {string} Etiqueta sin emoji
 */
export function getCategoryShortLabel(category) {
    const labels = {
        'ofertas': 'Ofertas',
        'eventos': 'Eventos',
        'informacion': 'Info',
        'emergencias': 'Urgente',
        'servicios': 'Servicios',
        'horarios': 'Horarios',
        'sin-categoria': 'Sin Cat.'
    };
    
    return labels[category] || labels['sin-categoria'];
}

/**
 * Formatea bytes a tamaño legible
 * @param {number} bytes - Cantidad de bytes
 * @returns {string} Tamaño formateado (KB, MB, GB)
 */
export function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

/**
 * Formatea velocidad de transferencia
 * @param {number} bytesPerSecond - Bytes por segundo
 * @returns {string} Velocidad formateada
 */
export function formatSpeed(bytesPerSecond) {
    if (bytesPerSecond === 0) return '0 KB/s';
    
    const k = 1024;
    const sizes = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
    const i = Math.floor(Math.log(bytesPerSecond) / Math.log(k));
    
    return parseFloat((bytesPerSecond / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

/**
 * Objeto con todos los formatters para importación conveniente
 */
export const formatters = {
    formatDate,
    escapeHtml,
    getCategoryLabel,
    getCategoryShortLabel,
    formatFileSize,
    formatSpeed
};