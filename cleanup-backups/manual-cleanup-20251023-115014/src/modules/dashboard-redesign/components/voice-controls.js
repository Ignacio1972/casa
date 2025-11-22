/**
 * Audio Slider Component
 * Slider reutilizable para controles de audio
 * @module AudioSlider
 */

export class AudioSlider {
    constructor() {
        this.min = 0;
        this.max = 100;
        this.value = 50;
        this.step = 1;
        this.label = 'Control';
        this.icon = '🎚️';
        this.unit = '%';
        this.showLabels = true;
        this.minLabel = '';
        this.maxLabel = '';
        this.onChange = null;
        this.onInput = null;
        
        // Estado interno
        this.isDragging = false;
        this.element = null;
    }
    
    /**
     * Renderiza el componente
     * @param {HTMLElement} container - Contenedor donde renderizar
     * @param {Object} options - Opciones de configuración
     */
    static async render(container, options = {}) {
        const instance = new AudioSlider();
        Object.assign(instance, options);
        
        container.innerHTML = instance.getHTML();
        instance.element = container.querySelector('.audio-slider');
        instance.attachEvents();
        instance.updateUI();
        
        return instance;
    }
    
    /**
     * Genera el HTML del componente
     */
    getHTML() {
        return `
            <div class="audio-slider" data-slider-id="${this.generateId()}">
                <div class="audio-slider__header">
                    <label class="audio-slider__label">
                        <span class="audio-slider__icon">${this.icon}</span>
                        <span class="audio-slider__text">${this.label}</span>
                    </label>
                    <span class="audio-slider__value">${this.formatValue(this.value)}</span>
                </div>
                
                <div class="audio-slider__control">
                    <div class="audio-slider__track" role="slider"
                         aria-label="${this.label}"
                         aria-valuemin="${this.min}"
                         aria-valuemax="${this.max}"
                         aria-valuenow="${this.value}"
                         tabindex="0">
                        <div class="audio-slider__fill"></div>
                        <div class="audio-slider__thumb"></div>
                    </div>
                    
                    ${this.showLabels ? `
                        <div class="audio-slider__labels">
                            <span class="audio-slider__label-min">${this.minLabel || this.min}</span>
                            <span class="audio-slider__label-max">${this.maxLabel || this.max}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    /**
     * Adjunta event listeners
     */
    attachEvents() {
        const track = this.element.querySelector('.audio-slider__track');
        const thumb = this.element.querySelector('.audio-slider__thumb');
        
        // Mouse events
        track.addEventListener('mousedown', (e) => this.startDrag(e));
        document.addEventListener('mousemove', (e) => this.onDrag(e));
        document.addEventListener('mouseup', () => this.endDrag());
        
        // Touch events
        track.addEventListener('touchstart', (e) => this.startDrag(e.touches[0]));
        document.addEventListener('touchmove', (e) => {
            if (this.isDragging) {
                e.preventDefault();
                this.onDrag(e.touches[0]);
            }
        });
        document.addEventListener('touchend', () => this.endDrag());
        
        // Click directo en track
        track.addEventListener('click', (e) => {
            if (!this.isDragging) {
                this.setValueFromPosition(e.clientX);
            }
        });
        
        // Keyboard support
        track.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Wheel support (opcional)
        track.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -this.step : this.step;
            this.setValue(this.value + delta);
        });
    }
    
    /**
     * Inicia el arrastre
     */
    startDrag(e) {
        this.isDragging = true;
        this.element.classList.add('audio-slider--dragging');
        
        // Cambiar cursor
        document.body.style.cursor = 'grabbing';
        
        // Actualizar valor inmediatamente
        if (e.type !== 'touchstart') {
            this.setValueFromPosition(e.clientX);
        }
    }
    
    /**
     * Durante el arrastre
     */
    onDrag(e) {
        if (!this.isDragging) return;
        
        requestAnimationFrame(() => {
            this.setValueFromPosition(e.clientX);
        });
    }
    
    /**
     * Termina el arrastre
     */
    endDrag() {
        if (!this.isDragging) return;
        
        this.isDragging = false;
        this.element.classList.remove('audio-slider--dragging');
        document.body.style.cursor = '';
        
        // Trigger onChange al final
        if (this.onChange) {
            this.onChange(this.value);
        }
    }
    
    /**
     * Establece valor desde posición del mouse
     */
    setValueFromPosition(clientX) {
        const track = this.element.querySelector('.audio-slider__track');
        const rect = track.getBoundingClientRect();
        
        // Calcular porcentaje
        let percentage = (clientX - rect.left) / rect.width;
        percentage = Math.max(0, Math.min(1, percentage));
        
        // Convertir a valor
        const range = this.max - this.min;
        let value = this.min + (range * percentage);
        
        // Aplicar step
        value = Math.round(value / this.step) * this.step;
        
        this.setValue(value);
    }
    
    /**
     * Maneja eventos de teclado
     */
    handleKeyboard(e) {
        let newValue = this.value;
        const bigStep = (this.max - this.min) / 10;
        
        switch(e.key) {
            case 'ArrowLeft':
            case 'ArrowDown':
                e.preventDefault();
                newValue = this.value - this.step;
                break;
            case 'ArrowRight':
            case 'ArrowUp':
                e.preventDefault();
                newValue = this.value + this.step;
                break;
            case 'PageDown':
                e.preventDefault();
                newValue = this.value - bigStep;
                break;
            case 'PageUp':
                e.preventDefault();
                newValue = this.value + bigStep;
                break;
            case 'Home':
                e.preventDefault();
                newValue = this.min;
                break;
            case 'End':
                e.preventDefault();
                newValue = this.max;
                break;
            default:
                return;
        }
        
        this.setValue(newValue);
        
        if (this.onChange) {
            this.onChange(this.value);
        }
    }
    
    /**
     * Establece el valor
     */
    setValue(value) {
        // Clamp value
        value = Math.max(this.min, Math.min(this.max, value));
        
        // Aplicar step
        value = Math.round(value / this.step) * this.step;
        
        if (value === this.value) return;
        
        this.value = value;
        this.updateUI();
        
        // Trigger onInput durante cambios
        if (this.onInput) {
            this.onInput(this.value);
        }
    }
    
    /**
     * Actualiza la UI
     */
    updateUI() {
        if (!this.element) return;
        
        const percentage = ((this.value - this.min) / (this.max - this.min)) * 100;
        
        // Actualizar fill y thumb
        const fill = this.element.querySelector('.audio-slider__fill');
        const thumb = this.element.querySelector('.audio-slider__thumb');
        const valueDisplay = this.element.querySelector('.audio-slider__value');
        const track = this.element.querySelector('.audio-slider__track');
        
        fill.style.width = `${percentage}%`;
        thumb.style.left = `${percentage}%`;
        valueDisplay.textContent = this.formatValue(this.value);
        
        // Actualizar ARIA
        track.setAttribute('aria-valuenow', this.value);
        
        // Cambiar color según valor (opcional)
        this.updateColor(percentage);
    }
    
    /**
     * Actualiza color según valor (opcional)
     */
    updateColor(percentage) {
        const fill = this.element.querySelector('.audio-slider__fill');
        
        // Ejemplo: cambiar color gradualmente
        if (this.colorGradient) {
            if (percentage < 33) {
                fill.style.background = 'linear-gradient(90deg, #10b981, #22d3ee)';
            } else if (percentage < 66) {
                fill.style.background = 'linear-gradient(90deg, #3b82f6, #6366f1)';
            } else {
                fill.style.background = 'linear-gradient(90deg, #f59e0b, #ef4444)';
            }
        }
    }
    
    /**
     * Formatea el valor para mostrar
     */
    formatValue(value) {
        // Si tiene decimales, mostrar solo 1
        const formatted = value % 1 === 0 ? value : value.toFixed(1);
        return formatted + this.unit;
    }
    
    /**
     * Genera ID único
     */
    generateId() {
        return 'slider_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * Obtiene el valor actual
     */
    getValue() {
        return this.value;
    }
    
    /**
     * Habilita/deshabilita el slider
     */
    setEnabled(enabled) {
        const track = this.element.querySelector('.audio-slider__track');
        
        if (enabled) {
            track.removeAttribute('disabled');
            track.setAttribute('tabindex', '0');
            this.element.classList.remove('audio-slider--disabled');
        } else {
            track.setAttribute('disabled', 'true');
            track.setAttribute('tabindex', '-1');
            this.element.classList.add('audio-slider--disabled');
        }
    }
}

// Export default para import dinámico
export default AudioSlider;