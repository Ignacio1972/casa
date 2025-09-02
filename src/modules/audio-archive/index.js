/**
 * Audio Archive Module - Historial Completo de Archivos
 * Sistema MBI-v4 Radio Automatizada
 * 
 * FUNCIONALIDAD:
 * - Muestra TODOS los archivos (activos + soft-deleted)
 * - Solo lectura (historial completo)
 * - Búsqueda y filtrado
 * - Preview de archivos
 * - Paginación
 */

import { eventBus } from '../../core/event-bus.js';
import { apiClient } from '../../core/api-client.js';

export default class AudioArchiveModule {
    constructor() {
        this.name = 'audio-archive';
        this.container = null;
        this.allFiles = [];
        this.filteredFiles = [];
        this.currentPage = 1;
        this.itemsPerPage = 50;
        this.searchQuery = '';
        this.categoryFilter = 'all';
        this.statusFilter = 'all';
        this.sortOrder = 'newest';
        this.currentAudio = null;
    }
    
    getName() {
        return this.name;
    }
    
    async load(container) {
        console.log('[AudioArchive] Loading module...');
        this.container = container;
        
        try {
            this.render();
            await this.loadStyles();
            this.attachEvents();
            await this.loadArchive();
            eventBus.emit('audio-archive:loaded');
        } catch (error) {
            console.error('[AudioArchive] Load failed:', error);
            this.showError('Error al cargar el archivo de audios');
        }
    }
    
    async unload() {
        console.log('[AudioArchive] Unloading...');
        this.stopCurrentAudio();
        this.allFiles = [];
        this.filteredFiles = [];
        this.container = null;
        
        // Clean up global references
        if (window.audioArchive) {
            delete window.audioArchive;
        }
    }
    
    async loadStyles() {
        // DESACTIVADO: CSS ya está en styles-v5
        // const styleId = 'audio-archive-styles';
        // if (!document.querySelector(`#${styleId}`)) {
        //     const link = document.createElement('link');
        //     link.id = styleId;
        //     link.rel = 'stylesheet';
        //     link.href = '/modules/audio-archive/styles.css';
        //     document.head.appendChild(link);
        //     
        //     await new Promise((resolve) => {
        //         link.onload = resolve;
        //         link.onerror = resolve;
        //     });
        // }
    }
    
    render() {
        this.container.innerHTML = `
            <div class="audio-archive-module">
                <!-- Page Header -->
                <div class="archive-page-header">
                    <h1 class="archive-page-title">
                        <span class="archive-page-title-icon">🗄️</span>
                        Archivo Completo de Audios
                    </h1>
                    <div class="archive-filter-controls">
                        <div class="archive-search-box">
                            <span class="archive-search-icon">🔍</span>
                            <input type="text" 
                                   id="archiveSearch" 
                                   class="archive-search-input" 
                                   placeholder="Buscar mensajes...">
                        </div>
                        <select id="categoryFilter" class="archive-filter-select">
                            <option value="all">Todas las categorías</option>
                            <option value="ofertas">Ofertas</option>
                            <option value="eventos">Eventos</option>
                            <option value="informacion">Información</option>
                            <option value="servicios">Servicios</option>
                            <option value="horarios">Horarios</option>
                            <option value="emergencias">Emergencias</option>
                            <option value="sin_categoria">Sin Categoría</option>
                        </select>
                        <select id="statusFilter" class="archive-filter-select">
                            <option value="all">Todos los estados</option>
                            <option value="generated">Solo Generados</option>
                            <option value="saved">Solo Guardados</option>
                            <option value="deleted">Solo Eliminados</option>
                        </select>
                        <select id="sortOrder" class="archive-filter-select">
                            <option value="newest">Más recientes</option>
                            <option value="oldest">Más antiguos</option>
                            <option value="name">Por nombre</option>
                        </select>
                    </div>
                </div>

                <!-- Contador de resultados -->
                <div id="resultsCount" class="archive-results-count">
                    <span>Cargando archivo...</span>
                </div>

                <!-- Lista de archivos -->
                <div class="archive-list">
                    <div class="archive-list-header">
                        <div>Play</div>
                        <div>Título</div>
                        <div>Categoría</div>
                        <div>Voz</div>
                        <div>Fecha</div>
                        <div>Estado</div>
                    </div>
                    <div id="archiveItems" class="archive-list-items">
                        <div class="archive-loading">⏳ Cargando archivo completo...</div>
                    </div>
                </div>

                <!-- Paginación -->
                <div id="pagination" class="archive-pagination" style="display: none;">
                    <div class="archive-pagination-info">
                        <span id="paginationInfo">Página 1 de 1</span>
                    </div>
                    <div class="archive-pagination-controls">
                        <button id="prevPage" class="archive-page-btn">◀</button>
                        <div id="pageNumbers"></div>
                        <button id="nextPage" class="archive-page-btn">▶</button>
                    </div>
                </div>
            </div>

            <!-- Player flotante -->
            <div id="floatingPlayer" class="archive-floating-player" style="display: none;">
                <div class="archive-player-header">
                    <span id="currentPlaying" class="archive-current-playing">🎵 Reproduciendo...</span>
                    <button id="closePlayer" class="archive-player-close">✕</button>
                </div>
                <audio id="audioPlayer" class="archive-audio-player" controls></audio>
            </div>
        `;
        
        // Expose global methods
        window.audioArchive = {
            playFile: (filename) => this.playFile(filename)
        };
    }
    
    attachEvents() {
        // Search input
        const searchInput = this.container.querySelector('#archiveSearch');
        searchInput?.addEventListener('input', (e) => {
            this.searchQuery = e.target.value.toLowerCase();
            this.applyFilters();
        });
        
        // Category filter
        const categoryFilter = this.container.querySelector('#categoryFilter');
        categoryFilter?.addEventListener('change', (e) => {
            this.categoryFilter = e.target.value;
            this.applyFilters();
        });
        
        // Status filter
        const statusFilter = this.container.querySelector('#statusFilter');
        statusFilter?.addEventListener('change', (e) => {
            this.statusFilter = e.target.value;
            this.applyFilters();
        });
        
        // Sort order
        const sortOrder = this.container.querySelector('#sortOrder');
        sortOrder?.addEventListener('change', (e) => {
            this.sortOrder = e.target.value;
            this.applySorting();
        });
        
        // Pagination
        const prevPage = this.container.querySelector('#prevPage');
        const nextPage = this.container.querySelector('#nextPage');
        
        prevPage?.addEventListener('click', () => this.goToPage(this.currentPage - 1));
        nextPage?.addEventListener('click', () => this.goToPage(this.currentPage + 1));
        
        // Player controls
        const closePlayer = this.container.querySelector('#closePlayer');
        closePlayer?.addEventListener('click', () => this.closePlayer());
    }
    
    async loadArchive() {
        try {
            console.log('[AudioArchive] Loading archive from API...');
            
            const response = await apiClient.post('/audio-archive.php', {
                action: 'list_all'
            });
            
            if (response.success && response.files) {
                this.allFiles = response.files;
                this.applyFilters();
                console.log(`[AudioArchive] Loaded ${this.allFiles.length} files`);
            } else {
                throw new Error(response.error || 'Error desconocido');
            }
            
        } catch (error) {
            console.error('[AudioArchive] Error loading archive:', error);
            this.showError('Error al cargar el archivo de audios: ' + error.message);
        }
    }
    
    applyFilters() {
        let filtered = [...this.allFiles];
        
        // Search filter
        if (this.searchQuery) {
            filtered = filtered.filter(file => 
                file.display_name.toLowerCase().includes(this.searchQuery) ||
                file.filename.toLowerCase().includes(this.searchQuery)
            );
        }
        
        // Category filter
        if (this.categoryFilter !== 'all') {
            filtered = filtered.filter(file => file.category === this.categoryFilter);
        }
        
        // Status filter
        if (this.statusFilter !== 'all') {
            filtered = filtered.filter(file => file.status === this.statusFilter);
        }
        
        this.filteredFiles = filtered;
        this.applySorting();
    }
    
    applySorting() {
        switch (this.sortOrder) {
            case 'oldest':
                this.filteredFiles.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                break;
            case 'name':
                this.filteredFiles.sort((a, b) => a.display_name.localeCompare(b.display_name));
                break;
            case 'newest':
            default:
                this.filteredFiles.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                break;
        }
        
        this.currentPage = 1;
        this.updateResultsCount();
        this.renderItems();
        this.renderPagination();
    }
    
    updateResultsCount() {
        const counter = this.container.querySelector('#resultsCount');
        const total = this.filteredFiles.length;
        const start = (this.currentPage - 1) * this.itemsPerPage + 1;
        const end = Math.min(start + this.itemsPerPage - 1, total);
        
        if (total === 0) {
            counter.innerHTML = `
                <div class="archive-empty">
                    <div class="archive-empty-icon">📭</div>
                    <p>No se encontraron archivos con los filtros aplicados</p>
                </div>
            `;
        } else {
            counter.innerHTML = `
                Mostrando <span class="archive-count-number">${start}-${end}</span> 
                de <span class="archive-count-number">${total}</span> archivos totales
            `;
        }
    }
    
    renderItems() {
        const container = this.container.querySelector('#archiveItems');
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        const pageItems = this.filteredFiles.slice(start, end);
        
        if (pageItems.length === 0) {
            container.innerHTML = '<div class="archive-loading">No hay archivos para mostrar</div>';
            return;
        }
        
        container.innerHTML = pageItems.map(file => `
            <div class="archive-list-item ${file.status}" data-filename="${file.filename}">
                <button class="archive-btn-preview" 
                        onclick="audioArchive.playFile('${file.filename}')" 
                        title="Reproducir audio">
                    ▶
                </button>
                <div class="archive-item-title" title="${file.display_name}">
                    ${file.display_name}
                </div>
                <span class="archive-category-badge archive-badge-${file.category || 'sin-categoria'}">
                    ${file.category_label}
                </span>
                <div class="archive-item-voice">
                    <span>🎙️</span>
                    <span>${file.voice || 'Desconocida'}</span>
                </div>
                <div class="archive-item-date">
                    <span>📅</span>
                    <span>${file.formatted_date}</span>
                </div>
                <span class="archive-status-badge archive-status-${file.status}">
                    ${this.getStatusLabel(file.status)}
                </span>
            </div>
        `).join('');
        
        // Add click events to items
        container.querySelectorAll('.archive-list-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (!e.target.classList.contains('archive-btn-preview')) {
                    this.selectItem(item);
                }
            });
        });
    }
    
    renderPagination() {
        const totalPages = Math.ceil(this.filteredFiles.length / this.itemsPerPage);
        const paginationContainer = this.container.querySelector('#pagination');
        
        if (totalPages <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }
        
        paginationContainer.style.display = 'flex';
        
        // Update info
        const info = this.container.querySelector('#paginationInfo');
        info.textContent = `Página ${this.currentPage} de ${totalPages}`;
        
        // Update buttons
        const prevBtn = this.container.querySelector('#prevPage');
        const nextBtn = this.container.querySelector('#nextPage');
        
        prevBtn.disabled = this.currentPage === 1;
        nextBtn.disabled = this.currentPage === totalPages;
        
        // Update page numbers
        const pageNumbers = this.container.querySelector('#pageNumbers');
        pageNumbers.innerHTML = this.generatePageNumbers(totalPages);
        
        // Add click events to page numbers
        pageNumbers.querySelectorAll('.archive-page-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const pageNum = parseInt(btn.textContent);
                if (!isNaN(pageNum)) {
                    this.goToPage(pageNum);
                }
            });
        });
    }
    
    generatePageNumbers(totalPages) {
        const current = this.currentPage;
        const delta = 2;
        const range = [];
        const rangeWithDots = [];
        
        for (let i = Math.max(2, current - delta); i <= Math.min(totalPages - 1, current + delta); i++) {
            range.push(i);
        }
        
        if (current - delta > 2) {
            rangeWithDots.push(1, '...');
        } else {
            rangeWithDots.push(1);
        }
        
        rangeWithDots.push(...range);
        
        if (current + delta < totalPages - 1) {
            rangeWithDots.push('...', totalPages);
        } else {
            rangeWithDots.push(totalPages);
        }
        
        return rangeWithDots.map(item => {
            if (item === '...') {
                return '<span style="color: var(--text-secondary);">...</span>';
            }
            
            const isActive = item === current ? 'active' : '';
            return `<button class="archive-page-btn ${isActive}">${item}</button>`;
        }).join('');
    }
    
    goToPage(pageNum) {
        const totalPages = Math.ceil(this.filteredFiles.length / this.itemsPerPage);
        
        if (pageNum >= 1 && pageNum <= totalPages && pageNum !== this.currentPage) {
            this.currentPage = pageNum;
            this.renderItems();
            this.renderPagination();
            this.updateResultsCount();
            
            // Scroll to top
            this.container.scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    selectItem(item) {
        // Remove selection from other items
        this.container.querySelectorAll('.archive-list-item').forEach(i => {
            i.style.background = '';
        });
        
        // Highlight selected item
        item.style.background = 'var(--bg-tertiary)';
        
        const filename = item.dataset.filename;
        console.log('[AudioArchive] Selected file:', filename);
    }
    
    async playFile(filename) {
        try {
            console.log('[AudioArchive] Playing file:', filename);
            
            const player = this.container.querySelector('#floatingPlayer');
            const audio = this.container.querySelector('#audioPlayer');
            const playing = this.container.querySelector('#currentPlaying');
            
            if (player && audio && playing) {
                // Stop current audio if playing
                this.stopCurrentAudio();
                
                // Find file info
                const file = this.allFiles.find(f => f.filename === filename);
                const displayName = file ? file.display_name : filename;
                
                // Set up new audio - usar mismo endpoint que otros módulos
                audio.src = `/api/biblioteca.php?filename=${filename}`;
                playing.textContent = `🎵 ${displayName}`;
                player.style.display = 'block';
                
                // Update button state
                this.updatePlayButton(filename, 'playing');
                
                // Play audio
                await audio.play();
                this.currentAudio = { filename, audio, button: null };
                
                // Handle ended event
                audio.onended = () => {
                    this.updatePlayButton(filename, 'stopped');
                    this.currentAudio = null;
                };
                
                // Handle error event
                audio.onerror = () => {
                    console.error('[AudioArchive] Audio playback error');
                    this.updatePlayButton(filename, 'error');
                    this.currentAudio = null;
                };
            }
            
        } catch (error) {
            console.error('[AudioArchive] Error playing file:', error);
            this.updatePlayButton(filename, 'error');
        }
    }
    
    stopCurrentAudio() {
        if (this.currentAudio) {
            this.currentAudio.audio.pause();
            this.currentAudio.audio.src = '';
            this.updatePlayButton(this.currentAudio.filename, 'stopped');
            this.currentAudio = null;
        }
    }
    
    updatePlayButton(filename, state) {
        const item = this.container.querySelector(`[data-filename="${filename}"]`);
        const button = item?.querySelector('.archive-btn-preview');
        
        if (button) {
            switch (state) {
                case 'playing':
                    button.textContent = '⏸';
                    button.classList.add('playing');
                    break;
                case 'stopped':
                case 'error':
                default:
                    button.textContent = '▶';
                    button.classList.remove('playing');
                    break;
            }
        }
    }
    
    closePlayer() {
        const player = this.container.querySelector('#floatingPlayer');
        if (player) {
            player.style.display = 'none';
        }
        
        this.stopCurrentAudio();
    }
    
    getStatusLabel(status) {
        const labels = {
            'generated': 'Generado',
            'saved': 'Guardado',
            'deleted': 'Eliminado'
        };
        return labels[status] || 'Desconocido';
    }
    
    showError(message) {
        const container = this.container.querySelector('#archiveItems');
        if (container) {
            container.innerHTML = `
                <div class="archive-loading" style="color: var(--error, #dc2626);">
                    ❌ ${message}
                </div>
            `;
        }
    }
}