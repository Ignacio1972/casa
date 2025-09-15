// Script para analizar los tamaños de fuente directamente en el dashboard
// Ejecutar este script en la consola del navegador cuando estés en el dashboard

(function() {
    console.clear();
    console.log('%c=== ANÁLISIS DE TAMAÑOS DE FUENTE EN EL DASHBOARD ===', 'background: #333; color: #fff; padding: 5px; font-size: 14px;');
    
    // Buscar el título "Mensajes Recientes"
    const mensajesTitle = Array.from(document.querySelectorAll('.card-title')).find(el => 
        el.textContent.includes('Mensajes Recientes')
    );
    
    // Buscar el título "¿Qué necesitas anunciar?"
    const aiTitle = Array.from(document.querySelectorAll('.card-title, h2, h3')).find(el => 
        el.textContent.includes('Qué necesitas anunciar')
    );
    
    if (!mensajesTitle) {
        console.error('❌ No se encontró el título "Mensajes Recientes"');
        return;
    }
    
    if (!aiTitle) {
        console.error('❌ No se encontró el título "¿Qué necesitas anunciar?"');
        console.log('Buscando elementos alternativos...');
        
        // Buscar en toda la estructura de AI
        const aiContainer = document.querySelector('.ai-suggestions-container, .ai-config-panel, .ai-panel');
        if (aiContainer) {
            console.log('Encontrado contenedor AI:', aiContainer.className);
            const headers = aiContainer.querySelectorAll('h1, h2, h3, .card-title');
            console.log('Headers encontrados en AI container:', headers.length);
            headers.forEach((h, i) => {
                console.log(`  ${i}: ${h.tagName}.${h.className} -> "${h.textContent.substring(0, 50)}..."`);
            });
        }
        return;
    }
    
    // Obtener estilos computados
    const mensajesStyles = window.getComputedStyle(mensajesTitle);
    const aiStyles = window.getComputedStyle(aiTitle);
    
    // Propiedades a comparar
    const props = [
        'fontSize',
        'fontWeight', 
        'lineHeight',
        'fontFamily',
        'color',
        'letterSpacing',
        'textTransform',
        'margin',
        'padding'
    ];
    
    console.log('\n%c📊 COMPARACIÓN DE ESTILOS:', 'font-weight: bold; color: #0066cc;');
    console.log('════════════════════════════════════════════════════════════════');
    
    // Tabla de comparación
    const comparison = [];
    props.forEach(prop => {
        const val1 = mensajesStyles[prop];
        const val2 = aiStyles[prop];
        const match = val1 === val2;
        
        comparison.push({
            'Propiedad': prop,
            'Mensajes Recientes': val1,
            '¿Qué necesitas?': val2,
            'Coincide': match ? '✅' : '❌'
        });
        
        if (!match) {
            console.log(`%c⚠️ DIFERENCIA en ${prop}:`, 'color: red; font-weight: bold;');
            console.log(`   Mensajes Recientes: ${val1}`);
            console.log(`   ¿Qué necesitas?: ${val2}`);
        }
    });
    
    console.table(comparison);
    
    // Información adicional
    console.log('\n%c🔍 INFORMACIÓN ADICIONAL:', 'font-weight: bold; color: #0066cc;');
    
    // Clases y estructura
    console.log('Mensajes Recientes:');
    console.log('  - Elemento:', mensajesTitle.tagName);
    console.log('  - Clases:', mensajesTitle.className);
    console.log('  - Padre:', mensajesTitle.parentElement.className);
    console.log('  - Abuelo:', mensajesTitle.parentElement.parentElement.className);
    
    console.log('\n¿Qué necesitas anunciar?:');
    console.log('  - Elemento:', aiTitle.tagName);
    console.log('  - Clases:', aiTitle.className);
    console.log('  - Padre:', aiTitle.parentElement.className);
    console.log('  - Abuelo:', aiTitle.parentElement.parentElement.className);
    
    // Medidas reales
    const mensajesRect = mensajesTitle.getBoundingClientRect();
    const aiRect = aiTitle.getBoundingClientRect();
    
    console.log('\n%c📏 MEDIDAS REALES:', 'font-weight: bold; color: #0066cc;');
    console.log('Mensajes Recientes:');
    console.log('  - Altura:', mensajesRect.height + 'px');
    console.log('  - Ancho:', mensajesRect.width + 'px');
    
    console.log('\n¿Qué necesitas anunciar?:');
    console.log('  - Altura:', aiRect.height + 'px');
    console.log('  - Ancho:', aiRect.width + 'px');
    
    // CSS aplicado (para debug)
    console.log('\n%c🎨 REGLAS CSS APLICADAS:', 'font-weight: bold; color: #0066cc;');
    
    // Intentar obtener las reglas CSS
    const sheets = document.styleSheets;
    const mensajesRules = [];
    const aiRules = [];
    
    try {
        for (let sheet of sheets) {
            try {
                const rules = sheet.cssRules || sheet.rules;
                for (let rule of rules) {
                    if (rule.selectorText) {
                        // Para Mensajes Recientes
                        if (mensajesTitle.matches(rule.selectorText)) {
                            if (rule.style.fontSize || rule.style.fontWeight) {
                                mensajesRules.push({
                                    selector: rule.selectorText,
                                    fontSize: rule.style.fontSize,
                                    fontWeight: rule.style.fontWeight
                                });
                            }
                        }
                        // Para AI
                        if (aiTitle.matches(rule.selectorText)) {
                            if (rule.style.fontSize || rule.style.fontWeight) {
                                aiRules.push({
                                    selector: rule.selectorText,
                                    fontSize: rule.style.fontSize,
                                    fontWeight: rule.style.fontWeight
                                });
                            }
                        }
                    }
                }
            } catch(e) {
                // Ignorar errores de CORS
            }
        }
    } catch(e) {
        console.log('No se pudieron leer todas las hojas de estilo (CORS)');
    }
    
    if (mensajesRules.length > 0) {
        console.log('\nReglas para Mensajes Recientes:');
        console.table(mensajesRules);
    }
    
    if (aiRules.length > 0) {
        console.log('\nReglas para ¿Qué necesitas?:');
        console.table(aiRules);
    }
    
    // Resumen final
    console.log('\n%c📋 RESUMEN:', 'background: #ffcc00; color: #000; padding: 5px; font-size: 14px; font-weight: bold;');
    
    const fontSize1 = parseFloat(mensajesStyles.fontSize);
    const fontSize2 = parseFloat(aiStyles.fontSize);
    
    if (fontSize1 === fontSize2) {
        console.log('✅ Los tamaños de fuente son IDÉNTICOS:', mensajesStyles.fontSize);
    } else {
        const diff = fontSize2 - fontSize1;
        const percent = ((diff / fontSize1) * 100).toFixed(1);
        console.log(`❌ HAY DIFERENCIA en el tamaño de fuente:`);
        console.log(`   Mensajes Recientes: ${mensajesStyles.fontSize}`);
        console.log(`   ¿Qué necesitas?: ${aiStyles.fontSize}`);
        console.log(`   Diferencia: ${diff > 0 ? '+' : ''}${diff}px (${percent}%)`);
    }
    
    // Crear indicadores visuales en la página
    mensajesTitle.style.outline = '2px solid red';
    mensajesTitle.style.outlineOffset = '2px';
    
    aiTitle.style.outline = '2px solid blue';
    aiTitle.style.outlineOffset = '2px';
    
    console.log('\n%c🎯 INDICADORES VISUALES:', 'font-weight: bold; color: #0066cc;');
    console.log('- Borde ROJO: Mensajes Recientes');
    console.log('- Borde AZUL: ¿Qué necesitas anunciar?');
    
})();