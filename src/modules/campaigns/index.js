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
 * Nombre técnico del módulo: campaigns
 * @module CampaignLibraryModule
 */

import { eventBus } from '../../core/event-bus.js';
import { storageManager } from '../../core/storage-manager.js';
import { apiClient } from '../../core/api-client.js';
import { FileUploadManager } from './services/file-upload-manager.js';
import { MessageActions } from './services/message-actions.js';
import { formatDate, escapeHtml, getCategoryLabel, getCategoryShortLabel } from './utils/formatters.js';

export default class CampaignLibraryModule {
    constructor() {
        this.name = 'campaigns';
        this.container = null;
        this.messages = [];
        this.filteredMessages = [];
        this.currentFilter = 'all';
        this.currentSort = 'date_desc';
        this.searchQuery = '';
        this.isLoading = false;
        this.fileUploadManager = null; // Se inicializa en load()
        this.messageActions = null; // Se inicializa en load()
        this.selectionMode = false; // Modo de selección múltiple
        this.selectedMessages = new Set(); // IDs de mensajes seleccionados
    }
    
    getName() {
        return this.name;
    }
    
    async load(container) {
        console.log('[CampaignLibrary] Loading...', new Error().stack);
        this.container = container;
        
        try {
            // Renderizar estructura inicial
            this.render();
            
            // Inicializar FileUploadManager
            this.fileUploadManager = new FileUploadManager(this);
            
            // Inicializar MessageActions
            this.messageActions = new MessageActions(this);
            
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
        // MIGRADO: Los estilos ahora se cargan globalmente desde /styles-v5/main.css
        // No es necesario cargar estilos específicos del módulo
        console.log('[CampaignLibrary] Styles loaded from global styles-v5');
        
        // SEGURIDAD: Remover cualquier CSS del dashboard que pueda interferir
        const dashboardStyles = document.getElementById('dashboard-module-styles');
        if (dashboardStyles) {
            dashboardStyles.remove();
            console.log('[CampaignLibrary] Removed dashboard CSS to prevent conflicts');
        }
    }
    
  // En /v2/modules/campaigns/index.js
// Actualizar el método render() para remover header y tabs:

render() {
    this.container.innerHTML = `
        <div class="campaigns-module">
            <!-- Page Header con título y filtros -->
            <div class="page-header">
                <h1 class="page-title">
                    <span class="page-title-icon">💾</span>
                    Mensajes Guardados
                </h1>
                <div class="filter-bar">
                    <button class="btn btn-secondary" id="toggle-selection-btn" title="Modo de selección">
                        ☑️ Seleccionar
                    </button>
                    <button class="btn btn-danger" id="delete-selected-btn" style="display: none;">
                        🗑️ Eliminar (<span id="selected-count">0</span>)
                    </button>
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
        // Botón de modo selección
        const toggleSelectionBtn = this.container.querySelector('#toggle-selection-btn');
        if (toggleSelectionBtn) {
            toggleSelectionBtn.addEventListener('click', () => {
                this.toggleSelectionMode();
            });
        }
        
        // Botón de eliminar seleccionados
        const deleteSelectedBtn = this.container.querySelector('#delete-selected-btn');
        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', () => {
                this.deleteSelectedMessages();
            });
        }
        
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
            // Convertir sin_categoria a sin-categoria para que coincida con el CSS
            const categoryNormalized = (message.category || 'sin_categoria').replace(/_/g, '-');
            const categoryClass = `badge-${categoryNormalized}`;
            const categoryLabel = getCategoryShortLabel(message.category);
            
            // Verificar si está seleccionado
            const isSelected = this.selectedMessages.has(message.id);
            
            return `
            <div class="message-card ${isAudio ? 'audio-card' : ''} ${isSelected ? 'selected' : ''}" data-id="${message.id}">
                ${this.selectionMode ? `
                    <div class="selection-checkbox" style="position: absolute; top: 10px; left: 10px; z-index: 10;">
                        <input type="checkbox" 
                               class="message-checkbox" 
                               data-id="${message.id}"
                               ${isSelected ? 'checked' : ''}
                               style="width: 20px; height: 20px; cursor: pointer;"
                               onclick="window.campaignLibrary.toggleMessageSelection('${message.id}')">
                    </div>
                ` : ''}
                <div class="message-header" ${this.selectionMode ? 'style="padding-left: 40px;"' : ''}>
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
                        <button class="btn-icon btn-play" onclick="window.campaignLibrary.playMessage('${message.id}')" title="Preview">▶</button>
                        <button class="btn-icon" onclick="window.campaignLibrary.editMessage('${message.id}')" title="Cambiar Título">✏️</button>
                        ${isAudio ? `<button class="btn-icon btn-schedule" onclick="window.campaignLibrary.scheduleMessage('${message.id}', '${(message.title || '').replace(/'/g, "\\'").replace(/"/g, '\\"')}')" title="Programar">📅</button>` : ''}
                        <button class="btn-icon btn-radio" onclick="window.campaignLibrary.sendToRadio('${message.id}')" title="Enviar a Radio">
                            <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor">
                                <path d="M480-120q-42 0-71-29t-29-71q0-42 29-71t71-29q42 0 71 29t29 71q0 42-29 71t-71 29ZM254-346l-84-86q59-59 138.5-93.5T480-560q92 0 171.5 35T790-430l-84 84q-44-44-102-69t-124-25q-66 0-124 25t-102 69ZM84-516 0-600q92-94 215-147t265-53q142 0 265 53t215 147l-84 84q-77-77-178.5-120.5T480-680q-116 0-217.5 43.5T84-516Z"/>
                            </svg>
                        </button>
                        <button class="btn-icon btn-delete" onclick="window.campaignLibrary.deleteMessage('${message.id}')" title="Eliminar">🗑️</button>
                    </div>
                </div>
            </div>
            `;
        }).join('');
        
        // Exponer métodos globalmente para onclick - ahora delegamos a MessageActions
        window.campaignLibrary = {
            playMessage: (id) => this.messageActions.playMessage(id),
            editMessage: (id) => this.messageActions.editMessage(id),
            sendToRadio: (id) => this.messageActions.sendToRadio(id),
            deleteMessage: (id) => this.messageActions.deleteMessage(id),
            changeCategory: (id) => this.messageActions.changeCategory(id),
            scheduleMessage: (id, title) => this.messageActions.scheduleMessage(id, title),
            toggleCategoryDropdown: (event, id) => this.messageActions.toggleCategoryDropdown(event, id),
            updateCategory: (id, category) => this.messageActions.updateCategory(id, category),
            toggleMessageSelection: (id) => this.toggleMessageSelection(id)
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
    
    // Métodos de acciones delegados a MessageActions para compatibilidad
    async playMessage(id) {
        return this.messageActions.playMessage(id);
    }
    
    async editMessage(id) {
        return this.messageActions.editMessage(id);
    }
    
    async sendToRadio(id) {
        return this.messageActions.sendToRadio(id);
    }

    async scheduleMessage(id, title) {
        return this.messageActions.scheduleMessage(id, title);
    }

    
    async deleteMessage(id) {
        return this.messageActions.deleteMessage(id);
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
    
    /**
     * Sincroniza cambios de categoría con las programaciones del calendario
     * @param {string} filename - Nombre del archivo de audio
     * @param {string} newCategory - Nueva categoría a aplicar
     */
    async syncCategoryToSchedules(filename, newCategory) {
        if (!filename) return;
        
        try {
            console.log('[CampaignLibrary] Sincronizando categoría:', filename, '→', newCategory);
            
            const response = await apiClient.post('api/audio-scheduler.php', {
                action: 'update_category_by_filename',
                filename: filename,
                category: newCategory
            });
            
            if (response.success) {
                console.log(`[CampaignLibrary] Sincronizada categoría en ${response.updated_schedules} schedule(s)`);
                
                // Emitir evento para que calendario se refresque
                eventBus.emit('schedule:category:updated', {
                    filename: filename,
                    category: newCategory,
                    schedules_updated: response.updated_schedules
                });
            } else {
                console.warn('[CampaignLibrary] Error sincronizando categoría:', response.error);
            }
            
        } catch (error) {
            console.error('[CampaignLibrary] Error en syncCategoryToSchedules:', error);
            // No mostrar error al usuario, es una función auxiliar
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
    
    // === NUEVOS MÉTODOS PARA SELECCIÓN MÚLTIPLE ===
    
    /**
     * Alternar modo de selección múltiple
     */
    toggleSelectionMode() {
        this.selectionMode = !this.selectionMode;
        
        const toggleBtn = this.container.querySelector('#toggle-selection-btn');
        const deleteBtn = this.container.querySelector('#delete-selected-btn');
        
        if (this.selectionMode) {
            // Activar modo selección
            toggleBtn.innerHTML = '✅ Cancelar';
            toggleBtn.classList.add('active');
            deleteBtn.style.display = 'inline-block';
            
            // Agregar clase al contenedor
            this.container.querySelector('.campaigns-module').classList.add('selection-mode');
        } else {
            // Desactivar modo selección
            toggleBtn.innerHTML = '☑️ Seleccionar';
            toggleBtn.classList.remove('active');
            deleteBtn.style.display = 'none';
            
            // Limpiar selección
            this.clearSelection();
            
            // Remover clase del contenedor
            this.container.querySelector('.campaigns-module').classList.remove('selection-mode');
        }
        
        // Refrescar vista
        this.displayMessages();
    }
    
    /**
     * Alternar selección de un mensaje
     */
    toggleMessageSelection(id) {
        if (!this.selectionMode) return;
        
        if (this.selectedMessages.has(id)) {
            this.selectedMessages.delete(id);
        } else {
            this.selectedMessages.add(id);
        }
        
        // Actualizar contador
        this.updateSelectionCount();
        
        // Actualizar visual del card
        const card = this.container.querySelector(`[data-id="${id}"]`);
        if (card) {
            card.classList.toggle('selected');
            // Agregar efecto de selección
            card.style.backgroundColor = this.selectedMessages.has(id) ? '#f0f8ff' : '';
            card.style.border = this.selectedMessages.has(id) ? '2px solid #007bff' : '';
        }
    }
    
    /**
     * Actualizar contador de seleccionados
     */
    updateSelectionCount() {
        const countEl = this.container.querySelector('#selected-count');
        if (countEl) {
            countEl.textContent = this.selectedMessages.size;
        }
        
        // Habilitar/deshabilitar botón eliminar
        const deleteBtn = this.container.querySelector('#delete-selected-btn');
        if (deleteBtn) {
            deleteBtn.disabled = this.selectedMessages.size === 0;
            if (this.selectedMessages.size === 0) {
                deleteBtn.classList.add('disabled');
            } else {
                deleteBtn.classList.remove('disabled');
            }
        }
    }
    
    /**
     * Limpiar toda la selección
     */
    clearSelection() {
        this.selectedMessages.clear();
        this.updateSelectionCount();
        
        // Limpiar visualmente todos los cards
        this.container.querySelectorAll('.message-card.selected').forEach(card => {
            card.classList.remove('selected');
            card.style.backgroundColor = '';
            card.style.border = '';
        });
    }
    
    /**
     * Eliminar mensajes seleccionados
     */
    async deleteSelectedMessages() {
        if (this.selectedMessages.size === 0) {
            this.showError('No hay mensajes seleccionados');
            return;
        }
        
        // Convertir Set a Array
        const selectedIds = Array.from(this.selectedMessages);
        
        // Delegar al método deleteMultiple de MessageActions
        await this.messageActions.deleteMultiple(selectedIds);
        
        // Si la eliminación fue exitosa, salir del modo selección
        if (this.selectedMessages.size === 0) {
            this.toggleSelectionMode();
        }
    }
    
    /**
     * Seleccionar todos los mensajes visibles
     */
    selectAll() {
        this.filteredMessages.forEach(msg => {
            this.selectedMessages.add(msg.id);
        });
        this.updateSelectionCount();
        this.displayMessages();
    }
    
    /**
     * Deseleccionar todos
     */
    deselectAll() {
        this.clearSelection();
        this.displayMessages();
    }
}