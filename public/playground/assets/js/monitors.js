/**
 * Monitors Module for MBI-v3 Playground
 * Standalone module - no modifica playground.js
 */

(function() {
    'use strict';
    
    let quotaChart = null;
    let isInitialized = false;
    
    // Esperar a que todo esté listo
    function waitForDependencies() {
        if (typeof Chart === 'undefined' || !window.playground) {
            setTimeout(waitForDependencies, 100);
            return;
        }
        initMonitorsModule();
    }
    
    function initMonitorsModule() {
        if (isInitialized) return;
        isInitialized = true;
        
        console.log('📊 Monitors module loading...');
        
        // Detectar click en tab de monitors
        const monitorsBtn = document.querySelector('[data-section="monitors"]');
        if (monitorsBtn) {
            monitorsBtn.addEventListener('click', function() {
                setTimeout(loadMonitorsDashboard, 100);
            });
        }
    }
    
    function loadMonitorsDashboard() {
        console.log('Loading monitors dashboard...');
        
        // Inicializar componentes
        initQuotaChart();
        loadRecentGenerations();
        loadPerformanceStats();
        loadVoiceUsage();
    }
    
    function initQuotaChart() {
        const canvas = document.getElementById('quota-chart');
        if (!canvas) return;
        
        // Si ya existe, solo actualizar
        if (quotaChart) {
            updateQuotaChart();
            return;
        }
        
        const ctx = canvas.getContext('2d');
        
        // Crear chart
        quotaChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Usado', 'Disponible'],
                datasets: [{
                    data: [0, 100],
                    backgroundColor: ['#3b82f6', '#1e293b'],
                    borderColor: ['#60a5fa', '#334155'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            padding: 15,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return label + ': ' + value.toLocaleString() + ' caracteres';
                            }
                        }
                    }
                }
            }
        });
        
        // Actualizar con datos reales
        updateQuotaChart();
    }
    
    function updateQuotaChart() {
        if (!quotaChart || !window.playground.quotaInfo) return;
        
        const quota = window.playground.quotaInfo;
        const used = quota.used || 0;
        const remaining = Math.max(0, (quota.limit || 100000) - used);
        const percentage = ((used / quota.limit) * 100).toFixed(1);
        
        // Actualizar datos
        quotaChart.data.datasets[0].data = [used, remaining];
        
        // Cambiar color según uso
        let color = '#3b82f6'; // Azul
        if (percentage > 90) {
            color = '#ef4444'; // Rojo
        } else if (percentage > 70) {
            color = '#f59e0b'; // Amarillo  
        } else if (percentage > 50) {
            color = '#10b981'; // Verde
        }
        
        quotaChart.data.datasets[0].backgroundColor[0] = color;
        quotaChart.update();
        
        // Actualizar detalles
        updateQuotaDetails(quota, percentage, color);
    }
    
    function updateQuotaDetails(quota, percentage, color) {
        const detailsDiv = document.getElementById('quota-details');
        if (!detailsDiv) return;
        
        const alertHtml = percentage > 80 ? `
            <div class="quota-alert">
                ⚠️ Quota alta: ${percentage}% usado
            </div>
        ` : '';
        
        detailsDiv.innerHTML = `
            <div class="quota-details-box">
                <div class="quota-row">
                    <span>Usado:</span>
                    <strong>${quota.used.toLocaleString()} chars</strong>
                </div>
                <div class="quota-row">
                    <span>Límite:</span>
                    <strong>${quota.limit.toLocaleString()} chars</strong>
                </div>
                <div class="quota-row">
                    <span>Disponible:</span>
                    <strong>${quota.remaining.toLocaleString()} chars</strong>
                </div>
                <div class="quota-row">
                    <span>Porcentaje:</span>
                    <strong style="color: ${color}">${percentage}%</strong>
                </div>
                <div class="quota-row">
                    <span>Requests:</span>
                    <strong>${quota.requests || 0}</strong>
                </div>
                <div class="quota-row">
                    <span>Reset:</span>
                    <strong>${quota.reset_date || 'Fin de mes'}</strong>
                </div>
                ${alertHtml}
            </div>
        `;
    }
    
    function loadRecentGenerations() {
        const container = document.getElementById('recent-generations');
        if (!container) return;
        
        // Obtener del historial de playground si existe
        const history = window.playground.generationHistory || [];
        const recent = history.slice(0, 5);
        
        if (recent.length === 0) {
            container.innerHTML = '<p class="no-data">No hay generaciones recientes</p>';
            return;
        }
        
        container.innerHTML = recent.map(gen => `
            <div class="generation-item">
                <div class="generation-header">
                    <strong>${gen.voice || 'Desconocida'}</strong>
                    <span class="generation-time">${gen.time || ''}</span>
                </div>
                <div class="generation-text">
                    ${(gen.text || '').substring(0, 50)}${gen.text && gen.text.length > 50 ? '...' : ''}
                </div>
                <div class="generation-status">
                    <span class="${gen.success ? 'status-success' : 'status-error'}">
                        ${gen.success ? '✅ Exitoso' : '❌ Error'}
                    </span>
                    <span class="generation-duration">${gen.duration || 0}s</span>
                </div>
            </div>
        `).join('');
    }
    
    function loadPerformanceStats() {
        const container = document.getElementById('performance-stats');
        if (!container) return;
        
        // Calcular estadísticas
        const history = window.playground.generationHistory || [];
        const performanceData = window.playground.performanceData || [];
        
        const avgTime = performanceData.length > 0
            ? (performanceData.reduce((a, b) => a + b, 0) / performanceData.length).toFixed(2)
            : '0';
            
        const successCount = history.filter(g => g.success).length;
        const successRate = history.length > 0
            ? ((successCount / history.length) * 100).toFixed(1)
            : '100';
            
        const sessionTime = window.sessionStart 
            ? Math.floor((Date.now() - window.sessionStart) / 60000)
            : 0;
        
        container.innerHTML = `
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">Tiempo Promedio</div>
                    <div class="stat-value">${avgTime}s</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Tasa de Éxito</div>
                    <div class="stat-value ${successRate > 90 ? 'text-success' : 'text-warning'}">${successRate}%</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Total Generaciones</div>
                    <div class="stat-value">${history.length}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Sesión Activa</div>
                    <div class="stat-value">${sessionTime}m</div>
                </div>
            </div>
        `;
    }
    
    function loadVoiceUsage() {
        const container = document.getElementById('voice-usage');
        if (!container) return;
        
        // Contar uso de voces
        const history = window.playground.generationHistory || [];
        const voiceCount = {};
        
        history.forEach(gen => {
            if (gen.voice) {
                voiceCount[gen.voice] = (voiceCount[gen.voice] || 0) + 1;
            }
        });
        
        // Ordenar y tomar top 5
        const sorted = Object.entries(voiceCount)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5);
            
        if (sorted.length === 0) {
            container.innerHTML = '<p class="no-data">No hay datos de uso</p>';
            return;
        }
        
        const maxCount = sorted[0][1];
        
        container.innerHTML = sorted.map(([voice, count]) => {
            const percentage = (count / maxCount * 100);
            return `
                <div class="voice-usage-item">
                    <div class="voice-usage-header">
                        <span>${voice}</span>
                        <span class="voice-count">${count} usos</span>
                    </div>
                    <div class="voice-usage-bar">
                        <div class="voice-usage-fill" style="width: ${percentage}%"></div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    // Agregar estilos CSS
    function addStyles() {
        if (document.getElementById('monitors-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'monitors-styles';
        style.textContent = `
            .quota-details-box {
                padding: 1rem;
                background: var(--bg-tertiary);
                border-radius: 0.5rem;
                margin-top: 1rem;
            }
            .quota-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
            }
            .quota-alert {
                margin-top: 1rem;
                padding: 0.5rem;
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid #ef4444;
                border-radius: 0.25rem;
                color: #ef4444;
            }
            .generation-item {
                padding: 0.75rem;
                background: var(--bg-tertiary);
                border-radius: 0.375rem;
                margin-bottom: 0.5rem;
            }
            .generation-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.25rem;
            }
            .generation-time {
                color: var(--text-secondary);
                font-size: 0.875rem;
            }
            .generation-text {
                color: var(--text-secondary);
                font-size: 0.875rem;
                margin-bottom: 0.25rem;
            }
            .generation-status {
                font-size: 0.875rem;
            }
            .generation-duration {
                color: var(--text-secondary);
                margin-left: 0.5rem;
            }
            .status-success { color: #10b981; }
            .status-error { color: #ef4444; }
            .stats-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
                padding: 1rem;
                background: var(--bg-tertiary);
                border-radius: 0.5rem;
            }
            .stat-item {
                text-align: center;
            }
            .stat-label {
                color: var(--text-secondary);
                font-size: 0.875rem;
                margin-bottom: 0.25rem;
            }
            .stat-value {
                font-size: 1.5rem;
                font-weight: bold;
                color: var(--accent);
            }
            .text-success { color: #10b981; }
            .text-warning { color: #f59e0b; }
            .voice-usage-item {
                margin-bottom: 0.75rem;
            }
            .voice-usage-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.25rem;
            }
            .voice-count {
                color: var(--text-secondary);
                font-size: 0.875rem;
            }
            .voice-usage-bar {
                height: 8px;
                background: var(--bg-tertiary);
                border-radius: 4px;
                overflow: hidden;
            }
            .voice-usage-fill {
                height: 100%;
                background: var(--accent);
                transition: width 0.3s;
            }
            .no-data {
                color: var(--text-secondary);
                padding: 1rem;
                text-align: center;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Inicializar
    addStyles();
    waitForDependencies();
    
    console.log('📊 Monitors module loaded');
})();