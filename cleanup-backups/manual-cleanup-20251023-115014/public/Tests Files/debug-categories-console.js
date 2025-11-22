// Script de debug para ejecutar en la consola del navegador
// Copiar y pegar este código en la consola cuando esté en la página de Campaigns

console.log('=== INICIANDO DEBUG DE CATEGORÍAS ===');

// 1. Verificar si el módulo campaigns está cargado
if (window.campaignLibrary) {
    console.log('✓ Módulo CampaignLibrary cargado');
    
    // 2. Ver mensajes en memoria
    console.log('\n📋 MENSAJES EN MEMORIA:');
    if (window.campaignLibrary.messages && window.campaignLibrary.messages.length > 0) {
        window.campaignLibrary.messages.forEach((msg, idx) => {
            console.log(`Mensaje ${idx + 1}:`, {
                title: msg.title,
                category: msg.category,
                category_type: typeof msg.category,
                category_value: msg.category === null ? 'null' : msg.category === undefined ? 'undefined' : `"${msg.category}"`
            });
        });
        
        // Categorías únicas
        const categorias = [...new Set(window.campaignLibrary.messages.map(m => m.category))];
        console.log('\n🏷️ Categorías únicas encontradas:', categorias);
    } else {
        console.log('No hay mensajes en memoria');
    }
} else {
    console.log('❌ Módulo CampaignLibrary NO está cargado');
}

// 3. Verificar estilos CSS cargados
console.log('\n🎨 VERIFICACIÓN DE ESTILOS CSS:');
const testCategories = ['ofertas', 'eventos', 'informacion', 'servicios', 'horarios', 'emergencias', 'sin-categoria', 'sin_categoria'];
const styleResults = {};

testCategories.forEach(cat => {
    const testEl = document.createElement('span');
    testEl.className = `badge badge-${cat}`;
    testEl.style.position = 'absolute';
    testEl.style.visibility = 'hidden';
    document.body.appendChild(testEl);
    
    const computed = window.getComputedStyle(testEl);
    styleResults[`badge-${cat}`] = {
        background: computed.backgroundColor,
        color: computed.color,
        exists: computed.backgroundColor !== 'rgba(0, 0, 0, 0)' && computed.backgroundColor !== ''
    };
    
    testEl.remove();
});

console.table(styleResults);

// 4. Verificar archivos CSS cargados
console.log('\n📁 ARCHIVOS CSS CARGADOS:');
const cssFiles = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.href);
cssFiles.forEach(file => console.log('  -', file));

// 5. Test de renderizado en vivo
console.log('\n🔧 TEST DE RENDERIZADO:');

// Crear un div de prueba
const testDiv = document.createElement('div');
testDiv.id = 'category-debug-test';
testDiv.style.position = 'fixed';
testDiv.style.top = '10px';
testDiv.style.right = '10px';
testDiv.style.background = 'white';
testDiv.style.border = '2px solid black';
testDiv.style.padding = '10px';
testDiv.style.zIndex = '99999';
testDiv.innerHTML = `
    <h4>Debug Categorías</h4>
    <div style="display: flex; flex-direction: column; gap: 5px;">
        <span class="badge badge-ofertas">ofertas</span>
        <span class="badge badge-eventos">eventos</span>
        <span class="badge badge-sin-categoria">sin-categoria</span>
        <span class="badge badge-sin_categoria">sin_categoria</span>
    </div>
    <button onclick="this.parentElement.remove()" style="margin-top: 10px;">Cerrar</button>
`;

document.body.appendChild(testDiv);

// 6. Verificar qué pasa cuando se renderizan los mensajes
console.log('\n📝 ANÁLISIS DE RENDERIZADO ACTUAL:');
const messageCards = document.querySelectorAll('.message-card');
if (messageCards.length > 0) {
    console.log(`Se encontraron ${messageCards.length} message-cards`);
    
    messageCards.forEach((card, idx) => {
        const badge = card.querySelector('.message-badge');
        if (badge) {
            const classes = Array.from(badge.classList);
            const computed = window.getComputedStyle(badge);
            console.log(`Card ${idx + 1} badge:`, {
                classes: classes.join(', '),
                background: computed.backgroundColor,
                color: computed.color,
                text: badge.textContent.trim()
            });
        }
    });
} else {
    console.log('No se encontraron message-cards renderizadas');
}

// 7. Función para probar el renderizado con diferentes categorías
window.testCategoryRender = function(category) {
    const categoryClass = `badge-${category || 'sin-categoria'}`;
    console.log(`Input: "${category}" → Clase: "${categoryClass}"`);
    
    const testEl = document.createElement('span');
    testEl.className = `message-badge ${categoryClass}`;
    testEl.textContent = category || 'sin-categoria';
    document.body.appendChild(testEl);
    
    const computed = window.getComputedStyle(testEl);
    console.log('Estilos aplicados:', {
        background: computed.backgroundColor,
        color: computed.color
    });
    
    testEl.remove();
    return categoryClass;
};

console.log('\n✅ Debug completado. Usa testCategoryRender("categoria") para probar renderizado');
console.log('Ejemplo: testCategoryRender("ofertas")');
console.log('Ejemplo: testCategoryRender("sin_categoria")');