/**
 * MENSAJES SELECCIONADOS - Campañas y mensajes para reutilizar
 * 
 * Esta sección contiene los mensajes TTS que han sido seleccionados 
 * y guardados intencionalmente para su reutilización, como:
 * - Campañas publicitarias recurrentes
 * - Anuncios de temporada 
 * - Plantillas de mensajes frecuentes
 * - Mensajes institucionales importantes
 * 
 * Nombre técnico del módulo: campaign-library
 * @module CampaignLibraryModule
 */

import { eventBus } from '../../shared/event-bus.js';
import { storageManager } from '../../shared/storage-manager.js';
import { apiClient } from '../../shared/api-client.js';
import { FileUploadManager } from './services/file-upload-manager.js';
import { formatDate, escapeHtml, getCategoryLabel, getCategoryShortLabel } from './utils/formatters.js';

export default class CampaignLibraryModule {
    constructor() {
        this.name = 'campaign-library';
        this.container = null;
        this.messages = [];
        this.filteredMessages = [];
        this.currentFilter = 'all';
        this.currentSort = 'date_desc';
        this.searchQuery = '';
        this.isLoading = false;
        this.fileUploadManager = null; // Se inicializa en load()
    }
    
    getName() {
        return this.name;
    }
    
    async load(container) {
        console.log('[CampaignLibrary] Loading...');
        this.container = container;
        
        try {
            // Renderizar estructura inicial
            this.render();
            
            // Inicializar FileUploadManager
            this.fileUploadManager = new FileUploadManager(this);
            
            // Cargar estilos
            await this.loadStyles();
            
            // Adjuntar eventos
            this.attachEvents();
            
            // Cargar mensajes
            await this.loadMessages();
            
            eventBus.emit('library:loaded');
            
        } catch (error) {
            console.error('[CampaignLibrary] Load failed:', error);
            this.showError('Error al cargar la biblioteca');
        }
    }
    
    async unload() {
        console.log('[CampaignLibrary] Unloading...');
        this.messages = [];
        this.container = null;
        
        // Cleanup del objeto global
        if (window.campaignLibrary) {
            delete window.campaignLibrary;
        }
    }
    
    async loadStyles() {
        // Cargar CSS externo
        if (!document.querySelector('#campaign-library-styles')) {
            const link = document.createElement('link');
            link.id = 'campaign-library-styles';
            link.rel = 'stylesheet';
            link.href = '/modules/campaign-library/styles/library.css';
            document.head.appendChild(link);
            
            // Esperar a que cargue
            await new Promise((resolve) => {
                link.onload = resolve;
                link.onerror = () => {
                    console.error('[CampaignLibrary] Failed to load styles');
                    resolve(); // Continuar de todos modos
                };
            });
        }
    }
    
  // En /v2/modules/campaign-library/index.js
// Actualizar el método render() para remover header y tabs:

render() {
    this.container.innerHTML = `
        <div class="campaign-library-module">
            <!-- Page Header con título y filtros -->
            <div class="page-header">
                <h1 class="page-title">
                    <span class="page-title-icon">💾</span>
                    Mensajes Guardados
                </h1>
                <div class="filter-bar">
                    <button class="btn btn-secondary" id="upload-audio-btn">
                        🎵 Subir Audio
                    </button>
                    <select id="library-filter" class="filter-select">
                        <option value="all">Todas las categorías</option>
                        <option value="ofertas">Ofertas</option>
                        <option value="eventos">Eventos</option>
                        <option value="informacion">Información</option>
                        <option value="servicios">Servicios</option>
                        <option value="horarios">Horarios</option>
                        <option value="emergencias">Emergencias</option>
                        <option value="sin-categoria">Sin Categoría</option>
                    </select>
                    <select id="library-sort" class="filter-select">
                        <option value="date_desc">Más recientes</option>
                        <option value="date_asc">Más antiguos</option>
                    </select>
                    
                    <!-- Search expandible estilo Apple -->
                    <div class="search-container">
                        <button id="search-toggle" class="search-toggle-btn" title="Buscar">
                            🔍
                        </button>
                        <div class="search-input-container">
                            <input type="text" 
                                   id="library-search" 
                                   class="search-input collapsed" 
                                   placeholder="Buscar mensajes...">
                            <button id="search-clear" class="search-clear-btn" title="Limpiar búsqueda">
                                ✕
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grid de mensajes -->
            <div id="messages-grid" class="messages-grid">
                <div class="loading-state">
                    <div class="loading-spinner"></div>
                    <p>Cargando mensajes...</p>
                </div>
            </div>
            
            <!-- Estado vacío -->
            <div id="empty-state" class="empty-state" style="display: none;">
                <div class="empty-state-icon">📭</div>
                <h3>No hay mensajes en la biblioteca</h3>
                <p>Crea tu primer mensaje para comenzar</p>
                <button class="btn btn-primary" id="create-first-btn">
                    ➕ Crear mi primer mensaje
                </button>
            </div>
            
            <!-- Input file oculto para upload -->
            <input type="file" id="audio-file-input" accept=".mp3,.wav,.flac,.aac,.ogg,.m4a,.opus" style="display: none;">
            
            <!-- Progress indicator modal -->
            <div id="upload-progress-modal" class="progress-modal" style="display: none;">
                <div class="progress-content">
                    <div class="progress-header">
                        <h3 class="progress-title">Subiendo archivo...</h3>
                        <button class="progress-close" id="progress-close-btn">✕</button>
                    </div>
                    
                    <div class="progress-info">
                        <div class="file-info">
                            <span class="file-icon">🎵</span>
                            <div class="file-details">
                                <div class="file-name" id="upload-file-name">archivo.mp3</div>
                                <div class="file-size" id="upload-file-size">2.5 MB</div>
                            </div>
                        </div>
                        
                        <div class="progress-stats">
                            <span class="progress-speed" id="upload-speed">0 KB/s</span>
                            <span class="progress-percentage" id="upload-percentage">0%</span>
                        </div>
                    </div>
                    
                    <div class="progress-bar-container">
                        <div class="progress-bar">
                            <div class="progress-fill" id="upload-progress-fill"></div>
                        </div>
                    </div>
                    
                    <div class="progress-status" id="upload-status">Preparando...</div>
                </div>
            </div>
        </div>
    `;
}
    
    attachEvents() {
        // Filtro de categorías (dropdown)
        const filterSelect = this.container.querySelector('#library-filter');
        if (filterSelect) {
            filterSelect.addEventListener('change', (e) => {
                this.setFilter(e.target.value);
            });
        }
        
        // Búsqueda expandible
        const searchToggle = this.container.querySelector('#search-toggle');
        const searchInput = this.container.querySelector('#library-search');
        const searchClear = this.container.querySelector('#search-clear');
        const searchContainer = this.container.querySelector('.search-container');
        
        if (searchToggle && searchInput && searchClear) {
            searchToggle.addEventListener('click', () => {
                const isCollapsed = searchInput.classList.contains('collapsed');
                
                if (isCollapsed) {
                    // Expandir
                    searchInput.classList.remove('collapsed');
                    searchInput.classList.add('expanded');
                    searchClear.classList.remove('collapsed');
                    searchClear.classList.add('expanded');
                    searchInput.focus();
                } else {
                    // Colapsar solo si está vacío
                    if (searchInput.value.trim() === '') {
                        this.collapseSearch();
                    }
                }
            });
            
            // Botón X para limpiar
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                this.searchMessages('');
                this.collapseSearch();
            });
            
            // Búsqueda en tiempo real
            searchInput.addEventListener('input', (e) => {
                this.searchMessages(e.target.value);
                
                // Mostrar/ocultar botón X según si hay texto
                if (e.target.value.trim() === '') {
                    searchClear.style.opacity = '0';
                } else {
                    searchClear.style.opacity = '1';
                }
            });
        }
        
        // Click fuera para colapsar search
        document.addEventListener('click', (e) => {
            if (searchContainer && !searchContainer.contains(e.target)) {
                if (searchInput && searchInput.classList.contains('expanded') && searchInput.value.trim() === '') {
                    this.collapseSearch();
                }
            }
            
            // También cerrar dropdowns de categorías al hacer click fuera
            if (!e.target.closest('.category-badge-container')) {
                document.querySelectorAll('.category-dropdown').forEach(d => {
                    d.classList.remove('active');
                });
            }
        });
        
        // Ordenamiento
        const sortSelect = this.container.querySelector('#library-sort');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.setSorting(e.target.value);
            });
        }
        
        // Botón de subir audio
        const uploadBtn = this.container.querySelector('#upload-audio-btn');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', () => {
                const fileInput = this.container.querySelector('#audio-file-input');
                if (fileInput) {
                    fileInput.click();
                }
            });
        }
        
        // Cerrar dropdowns al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.category-badge-container')) {
                document.querySelectorAll('.category-dropdown').forEach(d => {
                    d.classList.remove('active');
                    // Remover clase del card padre
                    const parentCard = d.closest('.message-card');
                    if (parentCard) {
                        parentCard.classList.remove('dropdown-active');
                    }
                });
            }
        });
        
        const createFirstBtn = this.container.querySelector('#create-first-btn');
        if (createFirstBtn) {
            createFirstBtn.addEventListener('click', () => {
                window.location.hash = '#/configuracion';
            });
        }
        
        // NUEVO: Input file change
        this.container.querySelector('#audio-file-input').addEventListener('change', (e) => {
            console.log('[CampaignLibrary] === EVENTO CHANGE DISPARADO ===');
            console.log('[CampaignLibrary] Files length:', e.target.files ? e.target.files.length : 0);
            
            if (e.target.files && e.target.files[0]) {
                console.log('[CampaignLibrary] Llamando handleFileSelected...');
                this.fileUploadManager.handleFileSelected(e.target.files[0]);
                
                // IMPORTANTE: Limpiar el input para evitar disparos múltiples
                e.target.value = '';
            } else {
                console.log('[CampaignLibrary] No hay archivos seleccionados');
            }
        });
        
        // Escuchar eventos de guardado
        eventBus.on('message:saved:library', (message) => {
            this.addMessage(message);
        });
    }
    
    async loadMessages() {
        this.isLoading = true;
        
        try {
            // Cargar desde localStorage
            const localMessages = this.loadLocalMessages();
            
            // Cargar desde backend
            const backendMessages = await this.loadBackendMessages();
            
            // Combinar y deduplicar
            this.messages = this.mergeMessages(localMessages, backendMessages);
            
            // Actualizar contadores
            this.updateFilterCounts();
            
            // Mostrar mensajes
            this.displayMessages();
            
        } catch (error) {
            console.error('Error cargando mensajes:', error);
            this.messages = this.loadLocalMessages(); // Fallback a local
            this.updateFilterCounts();
            this.displayMessages();
        } finally {
            this.isLoading = false;
        }
    }
    
    loadLocalMessages() {
        const messages = [];
        
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith('tts_mall_library_message_')) {
                try {
                    const message = JSON.parse(localStorage.getItem(key));
                    messages.push(message);
                } catch (e) {
                    console.error('Error parsing message:', e);
                }
            }
        }
        
        return messages;
    }
    
    async loadBackendMessages() {
        try {
            // Cargar mensajes guardados desde BD (incluye archivos de audio)
            const response = await fetch('/api/saved-messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list' })
            });
            
            if (!response.ok) throw new Error('Backend error');
            
            const result = await response.json();
            
            if (result.success && result.messages) {
                console.log('[CampaignLibrary] Cargados', result.messages.length, 'mensajes desde BD');
                
                // Formatear mensajes de audio para que sean compatibles
                return result.messages.map(msg => ({
                    id: msg.id,
                    title: msg.title || msg.filename,
                    content: msg.content || 'Archivo de audio',
                    description: msg.description || msg.content || '',
                    category: msg.category || 'sin_categoria',
                    type: msg.type || 'audio',
                    filename: msg.filename,
                    timestamp: msg.timestamp || new Date(msg.createdAt).getTime(),
                    createdAt: msg.createdAt,
                    playCount: msg.playCount,
                    radioCount: msg.radioCount
                }));
            }
            
            return [];
            
        } catch (error) {
            console.warn('Backend unavailable, using local storage only:', error);
            return [];
        }
    }
    
    mergeMessages(local, backend) {
        const merged = new Map();
        
        // Agregar mensajes del backend
        backend.forEach(msg => merged.set(msg.id, msg));
        
        // Agregar/actualizar con mensajes locales
        local.forEach(msg => merged.set(msg.id, msg));
        
        return Array.from(merged.values());
    }
    
    displayMessages() {
        const grid = this.container.querySelector('#messages-grid');
        const emptyState = this.container.querySelector('#empty-state');
        
        // Aplicar filtro y ordenamiento
        this.applyFiltersAndSort();
        
        if (this.filteredMessages.length === 0 && this.messages.length === 0) {
            grid.innerHTML = '';
            emptyState.style.display = 'block';
            return;
        }
        
        emptyState.style.display = 'none';
        
        if (this.filteredMessages.length === 0) {
            grid.innerHTML = `
                <div class="no-results" style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <p style="font-size: 1.25rem;">No se encontraron mensajes</p>
                    <p>Intenta con otros filtros o términos de búsqueda</p>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = this.filteredMessages.map(message => {
            // Diferenciar entre mensajes de texto y archivos de audio
            const isAudio = message.type === 'audio';
            
            // Para archivos de audio, usar description (texto completo) o content como fallback
            // Para mensajes de texto, usar el texto completo
            let content = '';
            if (isAudio) {
                content = message.description || message.content || `🎵 Archivo de audio: ${message.filename || 'Sin nombre'}`;
            } else {
                content = message.text || message.content || 'Sin contenido';
            }
            // Limpiar y escapar el contenido
            content = escapeHtml(content.trim());
            
            // Determinar voz según el tipo
            const voiceInfo = isAudio ? 'Audio' : (message.voice || 'Sin voz');
            
            // Formatear fecha relativa
            const dateInfo = formatDate(message.savedAt || message.createdAt);
            
            // Contador de reproducciones
            const playCount = message.playCount || 0;
            
            // Obtener etiqueta y clase de categoría
            const categoryClass = `badge-${message.category || 'sin-categoria'}`;
            const categoryLabel = getCategoryShortLabel(message.category);
            
            return `
            <div class="message-card ${isAudio ? 'audio-card' : ''}" data-id="${message.id}">
                <div class="message-header">
                    <h3 class="message-title">${escapeHtml(message.title)}</h3>
                    <div class="category-badge-container">
                        <span class="message-badge ${categoryClass}" data-category="${message.category || 'sin-categoria'}" onclick="window.campaignLibrary.toggleCategoryDropdown(event, '${message.id}')">
                            ${categoryLabel}
                        </span>
                        <div class="category-dropdown" id="dropdown-${message.id}">
                            <div class="category-option" data-category="ofertas" onclick="window.campaignLibrary.updateCategory('${message.id}', 'ofertas')">✅ Ofertas</div>
                            <div class="category-option" data-category="eventos" onclick="window.campaignLibrary.updateCategory('${message.id}', 'eventos')">🎉 Eventos</div>
                            <div class="category-option" data-category="informacion" onclick="window.campaignLibrary.updateCategory('${message.id}', 'informacion')">ℹ️ Información</div>
                            <div class="category-option" data-category="servicios" onclick="window.campaignLibrary.updateCategory('${message.id}', 'servicios')">🛠️ Servicios</div>
                            <div class="category-option" data-category="horarios" onclick="window.campaignLibrary.updateCategory('${message.id}', 'horarios')">🕐 Horarios</div>
                            <div class="category-option" data-category="emergencias" onclick="window.campaignLibrary.updateCategory('${message.id}', 'emergencias')">🚨 Emergencias</div>
                            <div class="category-option" data-category="sin-categoria" onclick="window.campaignLibrary.updateCategory('${message.id}', 'sin-categoria')">📋 Sin Categoría</div>
                        </div>
                    </div>
                </div>
                
                <div class="message-content">
                    ${content}
                </div>
                
                <div class="message-meta">
                    <div class="message-actions">
                        <button class="btn-icon" onclick="window.campaignLibrary.playMessage('${message.id}')" title="Preview">▶</button>
                        <button class="btn-icon" onclick="window.campaignLibrary.editMessage('${message.id}')" title="Cambiar Título">✏️</button>
                        ${isAudio ? `<button class="btn-icon btn-schedule" onclick="window.campaignLibrary.scheduleMessage('${message.id}', '${(message.title || '').replace(/'/g, "\\'").replace(/"/g, '\\"')}')" title="Programar">📅</button>` : ''}
                        <button class="btn-icon btn-radio" onclick="window.campaignLibrary.sendToRadio('${message.id}')" title="Enviar a Radio">📡</button>
                        <button class="btn-icon btn-delete" onclick="window.campaignLibrary.deleteMessage('${message.id}')" title="Eliminar">🗑️</button>
                    </div>
                </div>
            </div>
            `;
        }).join('');
        
        // Exponer métodos globalmente para onclick
        window.campaignLibrary = {
            playMessage: (id) => this.playMessage(id),
            editMessage: (id) => this.editMessage(id),
            sendToRadio: (id) => this.sendToRadio(id),
            deleteMessage: (id) => this.deleteMessage(id),
            changeCategory: (id) => this.changeCategory(id),
            scheduleMessage: (id, title) => this.scheduleMessage(id, title),
            toggleCategoryDropdown: (event, id) => this.toggleCategoryDropdown(event, id),
            updateCategory: (id, category) => this.updateCategory(id, category)
        };
    }
    
    setFilter(filter) {
        this.currentFilter = filter;
        
        // Actualizar select activo
        const filterSelect = this.container.querySelector('#library-filter');
        if (filterSelect) {
            filterSelect.value = filter;
        }
        
        this.displayMessages();
    }
    
    searchMessages(query) {
        this.searchQuery = query.toLowerCase();
        this.displayMessages();
    }
    
    setSorting(sort) {
        console.log('[CampaignLibrary] Cambiando ordenamiento a:', sort);
        this.currentSort = sort;
        this.displayMessages();
    }
    
    collapseSearch() {
        const searchInput = this.container.querySelector('#library-search');
        const searchClear = this.container.querySelector('#search-clear');
        
        if (searchInput) {
            searchInput.classList.remove('expanded');
            searchInput.classList.add('collapsed');
        }
        
        if (searchClear) {
            searchClear.classList.remove('expanded');
            searchClear.classList.add('collapsed');
        }
    }
    
    applyFiltersAndSort() {
        // Filtrar
        this.filteredMessages = this.messages.filter(msg => {
            // Filtro de categoría
            if (this.currentFilter !== 'all') {
                const msgCategory = msg.category || 'sin-categoria';
                if (msgCategory !== this.currentFilter) {
                    return false;
                }
            }
            
            // Búsqueda
            if (this.searchQuery) {
                const searchIn = (msg.title + msg.text + msg.voice).toLowerCase();
                if (!searchIn.includes(this.searchQuery)) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Ordenar
        this.filteredMessages.sort((a, b) => {
            switch (this.currentSort) {
                case 'date_desc':
                    // Usar múltiples campos de fecha como fallback
                    const dateA = new Date(a.savedAt || a.createdAt || a.timestamp || 0).getTime();
                    const dateB = new Date(b.savedAt || b.createdAt || b.timestamp || 0).getTime();
                    return dateB - dateA;
                case 'date_asc':
                    // Usar múltiples campos de fecha como fallback
                    const dateAsc_A = new Date(a.savedAt || a.createdAt || a.timestamp || 0).getTime();
                    const dateAsc_B = new Date(b.savedAt || b.createdAt || b.timestamp || 0).getTime();
                    return dateAsc_A - dateAsc_B;
                default:
                    return 0;
            }
        });
    }
    
    updateFilterCounts() {
        const counts = {
            all: this.messages.length,
            ofertas: 0,
            eventos: 0,
            informacion: 0,
            emergencias: 0,
            servicios: 0,
            horarios: 0,
            'sin-categoria': 0
        };
        
        this.messages.forEach(msg => {
            const cat = msg.category || 'sin-categoria';
            if (counts[cat] !== undefined) {
                counts[cat]++;
            }
        });
        
        // Actualizar UI
        Object.entries(counts).forEach(([filter, count]) => {
            const btn = this.container.querySelector(`[data-filter="${filter}"] .filter-count`);
            if (btn) {
                btn.textContent = `(${count})`;
            }
        });
    }
    
    async playMessage(id) {
        const message = this.messages.find(m => m.id === id);
        
        // Determinar el archivo de audio según el tipo
        let audioFilename;
        if (message.type === 'audio') {
            audioFilename = message.filename; // Archivos de audio guardados
        } else {
            audioFilename = message.audioFilename; // Mensajes de texto con audio generado
        }
        
        if (!message || !audioFilename) {
            this.showError('Audio no disponible');
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
    
    async editMessage(id) {
        const message = this.messages.find(m => m.id === id);
        if (!message) return;
        
        const newTitle = prompt('Editar título del mensaje:', message.title);
        if (!newTitle || newTitle === message.title) return;
        
        if (newTitle.trim().length < 3) {
            this.showError('El título debe tener al menos 3 caracteres');
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
                this.showError('Error al actualizar nombre del audio');
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
        
        this.displayMessages();
        this.showSuccess('Título actualizado');
    }
    
    async sendToRadio(id) {
        const message = this.messages.find(m => m.id === id);
        
        // Determinar el archivo según el tipo
        let audioFilename;
        if (message.type === 'audio') {
            audioFilename = message.filename; // Archivos de audio guardados
        } else {
            audioFilename = message.azuracastFilename; // Mensajes de texto con audio
        }
        
        if (!message || !audioFilename) {
            this.showError('Audio no disponible para enviar');
            return;
        }
        
      //  if (!confirm(`¿Enviar "${message.title}" a la radio ahora?`)) return;
        
        try {
            // Usar diferentes endpoints según el tipo
            const endpoint = message.type === 'audio' ? '/biblioteca.php' : '/generate.php';
            const action = message.type === 'audio' ? 'send_library_to_radio' : 'send_to_radio';
            
            const response = await apiClient.post(endpoint, {
                action: action,
                filename: audioFilename
            });
            
            if (response.success) {
                this.showSuccess('¡Mensaje enviado a la radio!');
            } else {
                throw new Error(response.error);
            }
        } catch (error) {
            this.showError('Error al enviar: ' + error.message);
        }
    }

    async scheduleMessage(id, title) {
        console.log("[DEBUG] scheduleMessage - ID:", id, "Title:", title);
        const message = this.messages.find(m => m.id === id);
        
        if (!message) {
            console.error("[DEBUG] Mensaje no encontrado:", id);
            this.showError('Mensaje no encontrado');
            return;
        }
        
        console.log("[DEBUG] Mensaje encontrado:", message);
        
        if (message.type !== 'audio') {
            this.showError('Solo se pueden programar archivos de audio');
            return;
        }
        
        try {
            // Cargar el modal dinámicamente
            if (!window.ScheduleModal) {
                const module = await import('./schedule-modal.js');
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
            this.showSuccess('Usa el calendario para programar este audio');
        }
    }

    
    async deleteMessage(id) {
        const message = this.messages.find(m => m.id === id);
        if (!message) return;
        
        if (!confirm(`¿Eliminar "${message.title}" permanentemente?\n\nEsta acción no se puede deshacer.`)) return;
        
        // Eliminar localmente
        storageManager.delete(`library_message_${message.id}`);
        
        // Eliminar del array
        this.messages = this.messages.filter(m => m.id !== id);
        
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
        this.updateFilterCounts();
        this.displayMessages();
        this.showSuccess('Mensaje eliminado');
    }
    
    addMessage(message) {
        // Agregar al inicio del array
        this.messages.unshift(message);
        
        // Actualizar UI
        this.updateFilterCounts();
        this.displayMessages();
    }
    
    // Métodos getCategoryLabel y getCategoryShortLabel movidos a utils/formatters.js
    
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
    
    async updateCategory(messageId, newCategory) {
        const message = this.messages.find(m => m.id === messageId);
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
            } catch (error) {
                console.error('Error actualizando categoría:', error);
                this.showError('Error al actualizar categoría');
                return;
            }
        } else {
            // Para mensajes de texto, guardar localmente
            storageManager.save(`library_message_${message.id}`, message);
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
        this.updateFilterCounts();
        this.displayMessages();
        
        // Animación de confirmación
        const badge = document.querySelector(`[data-id="${messageId}"] .message-badge`);
        if (badge) {
            badge.style.transform = 'scale(1.2)';
            setTimeout(() => {
                badge.style.transform = 'scale(1)';
            }, 200);
        }
    }
    
    // Métodos formatDate y escapeHtml movidos a utils/formatters.js
    
    async changeCategory(id) {
        const message = this.messages.find(m => m.id === id);
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
            this.showError('Selección inválida');
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
                    this.syncCategoryToSchedules(message.filename, newCategory);
                    
                    this.displayMessages();
                    this.showSuccess('Categoría actualizada');
                }
            } else {
                message.category = newCategory;
                storageManager.save(`library_message_${message.id}`, message);
                
                // NUEVO: También sincronizar en calendarios/schedules para mensajes locales
                this.syncCategoryToSchedules(message.filename || message.audioFilename, newCategory);
                
                this.displayMessages();
                this.showSuccess('Categoría actualizada');
            }
        } catch (error) {
            console.error('Error actualizando categoría:', error);
            this.showError('Error al actualizar categoría');
        }
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type) {
        // Emitir evento global
        eventBus.emit('ui:notification', { message, type });
        
        // Fallback con notificación local
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Animar entrada
        setTimeout(() => {
            notification.style.animation = 'slideIn 0.3s ease';
        }, 10);
        
        // Auto-remover después de 3 segundos
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}