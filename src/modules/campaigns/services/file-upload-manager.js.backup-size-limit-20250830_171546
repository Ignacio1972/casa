/**
 * FileUploadManager - Maneja la carga de archivos de audio externos
 * Extraído del módulo Campaign Library para mejor mantenibilidad
 */

export class FileUploadManager {
    constructor(parent) {
        this.parent = parent; // Referencia al CampaignLibrary
        this.uploadXHR = null; // Para poder cancelar uploads si es necesario
    }

    /**
     * Abrir selector de archivos
     */
    openFileSelector() {
        console.log('[FileUploadManager] === openFileSelector LLAMADO ===');
        const fileInput = this.parent.container.querySelector('#audio-file-input');
        console.log('[FileUploadManager] Input encontrado:', !!fileInput);
        
        if (fileInput) {
            console.log('[FileUploadManager] Haciendo clic en input file...');
            fileInput.click();
        } else {
            console.error('[FileUploadManager] ERROR: No se encontró el input file');
        }
    }

    /**
     * Manejar archivo seleccionado
     */
    async handleFileSelected(file) {
        console.log('[FileUploadManager] === INICIO handleFileSelected ===');
        console.log('[FileUploadManager] Archivo seleccionado:', file.name);
        console.log('[FileUploadManager] Tamaño:', file.size, 'bytes');
        console.log('[FileUploadManager] Tamaño MB:', (file.size / 1024 / 1024).toFixed(2), 'MB');
        console.log('[FileUploadManager] Tipo:', file.type);
        
        // Validación 1: Tamaño máximo 12MB
        const maxSize = 12 * 1024 * 1024; // 12MB
        console.log('[FileUploadManager] Límite máximo:', (maxSize / 1024 / 1024), 'MB');
        console.log('[FileUploadManager] ¿Excede límite?', file.size > maxSize);
        
        if (file.size > maxSize) {
            console.error('[FileUploadManager] ERROR: Archivo excede límite');
            this.parent.showError(`El archivo excede el límite de 12MB (${(file.size / 1024 / 1024).toFixed(2)}MB)`);
            return;
        }
        
        // Validación 2: Formatos permitidos
        const allowedTypes = [
            'audio/mpeg',       // MP3
            'audio/wav',        // WAV  
            'audio/x-wav',      // WAV (alternate)
            'audio/flac',       // FLAC
            'audio/aac',        // AAC
            'audio/ogg',        // Ogg Vorbis/Opus
            'audio/mp4',        // M4A
            'audio/x-m4a'       // M4A (alternate)
        ];
        
        if (!allowedTypes.includes(file.type)) {
            this.parent.showError('Formato no permitido. Use: MP3, WAV, FLAC, AAC, Ogg, M4A');
            return;
        }
        
        // Validación 3: Extensión
        const fileName = file.name.toLowerCase();
        const allowedExtensions = ['mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a', 'opus'];
        const hasValidExtension = allowedExtensions.some(ext => fileName.endsWith('.' + ext));
        
        if (!hasValidExtension) {
            this.parent.showError('Extensión no válida');
            return;
        }
        
        // Proceder con upload directo
        await this.uploadAudioFile(file);
    }

    /**
     * Subir archivo a servidor
     */
    async uploadAudioFile(file) {
        // Mostrar modal de progreso
        this.showUploadProgress(file);
        
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('action', 'upload_external');
            formData.append('audio', file);
            
            const xhr = new XMLHttpRequest();
            this.uploadXHR = xhr; // Guardar referencia para posible cancelación
            let startTime = Date.now();
            
            // Event listener para progreso de upload
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    const elapsedTime = (Date.now() - startTime) / 1000; // segundos
                    const uploadSpeed = e.loaded / elapsedTime; // bytes por segundo
                    
                    this.updateUploadProgress(percentComplete, uploadSpeed);
                }
            });
            
            // Event listener para carga completa
            xhr.addEventListener('load', () => {
                console.log('[FileUploadManager] Upload completado, status:', xhr.status);
                console.log('[FileUploadManager] Response:', xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('[FileUploadManager] Response parseado:', response);
                        
                        if (response.success) {
                            // Actualizar UI de progreso
                            this.updateUploadProgress(100, 0);
                            this.updateUploadStatus('✅ Archivo subido exitosamente', 'success');
                            
                            // Esperar 2 segundos antes de cerrar
                            setTimeout(() => {
                                this.hideUploadProgress();
                                
                                // Recargar la biblioteca
                                this.parent.loadMessages();
                                this.parent.showSuccess(`✅ "${file.name}" agregado a la biblioteca`);
                                
                                // Limpiar input
                                const fileInput = this.parent.container.querySelector('#audio-file-input');
                                if (fileInput) fileInput.value = '';
                                
                                resolve(response);
                            }, 2000);
                        } else {
                            this.updateUploadStatus(`❌ Error: ${response.error || 'Error desconocido'}`, 'error');
                            setTimeout(() => this.hideUploadProgress(), 3000);
                            reject(new Error(response.error || 'Upload falló'));
                        }
                    } catch (error) {
                        console.error('[FileUploadManager] Error parseando respuesta:', error);
                        this.updateUploadStatus('❌ Error en servidor', 'error');
                        setTimeout(() => this.hideUploadProgress(), 3000);
                        reject(error);
                    }
                } else {
                    this.updateUploadStatus(`❌ Error HTTP ${xhr.status}`, 'error');
                    setTimeout(() => this.hideUploadProgress(), 3000);
                    reject(new Error(`HTTP ${xhr.status}`));
                }
            });
            
            // Event listener para errores
            xhr.addEventListener('error', () => {
                console.error('[FileUploadManager] Error en upload');
                this.updateUploadStatus('❌ Error de conexión', 'error');
                setTimeout(() => this.hideUploadProgress(), 3000);
                reject(new Error('Error de red'));
            });
            
            // Enviar request
            xhr.open('POST', '/api/biblioteca.php');
            xhr.send(formData);
        });
    }

    showUploadProgress(file) {
        const modal = this.parent.container.querySelector('#upload-progress-modal');
        const fileName = this.parent.container.querySelector('#upload-file-name');
        const fileSize = this.parent.container.querySelector('#upload-file-size');
        const progressFill = this.parent.container.querySelector('#upload-progress-fill');
        const percentage = this.parent.container.querySelector('#upload-percentage');
        const speed = this.parent.container.querySelector('#upload-speed');
        const status = this.parent.container.querySelector('#upload-status');
        
        // Actualizar información del archivo
        fileName.textContent = file.name;
        fileSize.textContent = this.formatFileSize(file.size);
        
        // Resetear progreso
        progressFill.style.width = '0%';
        percentage.textContent = '0%';
        speed.textContent = '0 KB/s';
        status.textContent = 'Iniciando upload...';
        
        // Mostrar modal
        modal.style.display = 'flex';
        
        // Event listener para cerrar modal
        const closeBtn = this.parent.container.querySelector('#progress-close-btn');
        closeBtn.onclick = () => {
            this.cancelUpload();
            this.hideUploadProgress();
        };
    }

    updateUploadProgress(percentage, speed) {
        const progressFill = this.parent.container.querySelector('#upload-progress-fill');
        const percentageSpan = this.parent.container.querySelector('#upload-percentage');
        const speedSpan = this.parent.container.querySelector('#upload-speed');
        const status = this.parent.container.querySelector('#upload-status');
        
        // Actualizar barra de progreso
        progressFill.style.width = `${percentage}%`;
        percentageSpan.textContent = `${Math.round(percentage)}%`;
        
        // Actualizar velocidad
        speedSpan.textContent = this.formatSpeed(speed);
        
        // Actualizar status
        if (percentage < 100) {
            status.textContent = 'Subiendo archivo...';
        }
    }

    updateUploadStatus(message, type = 'info') {
        const status = this.parent.container.querySelector('#upload-status');
        const modal = this.parent.container.querySelector('#upload-progress-modal');
        
        status.textContent = message;
        
        // Cambiar color según el tipo
        status.className = 'progress-status';
        if (type === 'success') {
            status.classList.add('success');
        } else if (type === 'error') {
            status.classList.add('error');
        } else if (type === 'processing') {
            status.classList.add('processing');
        }
    }

    hideUploadProgress() {
        const modal = this.parent.container.querySelector('#upload-progress-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    /**
     * Cancelar upload en progreso
     */
    cancelUpload() {
        if (this.uploadXHR && this.uploadXHR.readyState !== XMLHttpRequest.DONE) {
            this.uploadXHR.abort();
            this.parent.showNotification('Upload cancelado', 'info');
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    formatSpeed(bytesPerSecond) {
        if (bytesPerSecond === 0) return '0 KB/s';
        
        const k = 1024;
        const sizes = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
        const i = Math.floor(Math.log(bytesPerSecond) / Math.log(k));
        
        return parseFloat((bytesPerSecond / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
}