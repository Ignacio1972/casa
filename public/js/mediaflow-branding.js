document.addEventListener('DOMContentLoaded', function() {
    // Reemplazar título de la página
    if (document.title.includes('AzuraCast')) {
        document.title = document.title.replace('AzuraCast', 'Mediaflow');
    }
    
    // Reemplazar footer con branding de Mediaflow
    const footer = document.querySelector('#footer');
    if (footer && footer.innerHTML.includes('AzuraCast')) {
        footer.innerHTML = 'Powered by <a href="http://51.222.25.222:4000/login.html" target="_blank" style="color: inherit; text-decoration: underline;">Mediaflow</a>';
    }
    
    // Observador para cambios dinámicos en caso de que el footer se cargue después
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                const footerCheck = document.querySelector('#footer');
                if (footerCheck && footerCheck.innerHTML.includes('AzuraCast')) {
                    footerCheck.innerHTML = 'Powered by <a href="http://51.222.25.222:4000/login.html" target="_blank" style="color: inherit; text-decoration: underline;">Mediaflow</a>';
                    observer.disconnect();
                }
            }
        });
    });
    
    // Configurar el observador
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Desconectar el observador después de 10 segundos para evitar overhead
    setTimeout(() => observer.disconnect(), 10000);
});