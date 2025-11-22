const http = require('http');
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');

// Configuración
const PORT = 4000;
const ROOT_DIR = '/var/www/casa/public';
const SRC_DIR = '/var/www/casa/src';
const CONFIG_DIR = '/var/www/casa/config';

// Configuración de tipos MIME
const mimeTypes = {
    '.html': 'text/html',
    '.js': 'text/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
    '.png': 'image/png',
    '.jpg': 'image/jpg',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
    '.mp3': 'audio/mpeg',
    '.wav': 'audio/wav',
    '.ttf': 'font/ttf',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2'
};

// Función para manejar archivos PHP
function handlePHP(filePath, req, res) {
    const phpCmd = `php-cgi -f ${filePath}`;
    const env = {
        REQUEST_METHOD: req.method,
        QUERY_STRING: req.url.split('?')[1] || '',
        CONTENT_TYPE: req.headers['content-type'] || '',
        CONTENT_LENGTH: req.headers['content-length'] || 0,
        SCRIPT_FILENAME: filePath,
        SCRIPT_NAME: req.url.split('?')[0],
        REQUEST_URI: req.url,
        DOCUMENT_URI: req.url.split('?')[0],
        DOCUMENT_ROOT: ROOT_DIR,
        SERVER_PROTOCOL: 'HTTP/1.1',
        GATEWAY_INTERFACE: 'CGI/1.1',
        SERVER_SOFTWARE: 'NodeJS/Casa-Costanera',
        REMOTE_ADDR: req.connection.remoteAddress,
        REMOTE_PORT: req.connection.remotePort,
        SERVER_ADDR: req.connection.localAddress,
        SERVER_PORT: PORT,
        SERVER_NAME: 'casa-costanera',
        APP_ENV: 'production',
        APP_PORT: PORT,
        REDIRECT_STATUS: '200' // Necesario para PHP-CGI
    };

    if (req.method === 'POST') {
        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
        });
        req.on('end', () => {
            const child = exec(phpCmd, {
                env: { ...process.env, ...env },
                maxBuffer: 10 * 1024 * 1024 // 10MB buffer
            }, (error, stdout, stderr) => {
                if (error && !stdout) {
                    console.error('PHP Error:', error);
                    res.writeHead(500);
                    res.end('Internal Server Error');
                    return;
                }
                
                // Parsear respuesta PHP
                const parts = stdout.split('\r\n\r\n');
                if (parts.length < 2) {
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(stdout);
                    return;
                }
                
                const headers = parts[0];
                const content = parts.slice(1).join('\r\n\r\n');
                
                // Parsear headers PHP
                const headerLines = headers.split('\r\n');
                headerLines.forEach(line => {
                    const [key, value] = line.split(': ');
                    if (key && value) {
                        res.setHeader(key, value);
                    }
                });
                
                res.writeHead(200);
                res.end(content);
            });
            
            child.stdin.write(body);
            child.stdin.end();
        });
    } else {
        exec(phpCmd, {
            env: { ...process.env, ...env },
            maxBuffer: 10 * 1024 * 1024
        }, (error, stdout, stderr) => {
            if (error && !stdout) {
                console.error('PHP Error:', error);
                res.writeHead(500);
                res.end('Internal Server Error');
                return;
            }
            
            // Parsear respuesta PHP
            const parts = stdout.split('\r\n\r\n');
            if (parts.length < 2) {
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(stdout);
                return;
            }
            
            const headers = parts[0];
            const content = parts.slice(1).join('\r\n\r\n');
            
            // Parsear headers PHP
            const headerLines = headers.split('\r\n');
            headerLines.forEach(line => {
                const [key, value] = line.split(': ');
                if (key && value) {
                    res.setHeader(key, value);
                }
            });
            
            res.writeHead(200);
            res.end(content);
        });
    }
}

// Crear servidor HTTP
const server = http.createServer((req, res) => {
    // Headers CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    
    if (req.method === 'OPTIONS') {
        res.writeHead(200);
        res.end();
        return;
    }

    // Log de solicitudes
    console.log(`[${new Date().toISOString()}] ${req.method} ${req.url}`);

    // Rutas especiales para APIs en src/api
    if (req.url.startsWith('/api/')) {
        const apiPath = req.url.replace('/api/', '');
        const filePath = path.join(SRC_DIR, 'api', apiPath.split('?')[0]);
        
        if (fs.existsSync(filePath) && filePath.endsWith('.php')) {
            handlePHP(filePath, req, res);
            return;
        }
    }

    // Rutas especiales para módulos en src
    if (req.url.startsWith('/modules/')) {
        const modulePath = req.url.replace('/modules/', '');
        const filePath = path.join(SRC_DIR, 'modules', modulePath);
        
        if (fs.existsSync(filePath)) {
            const extname = String(path.extname(filePath)).toLowerCase();
            const contentType = mimeTypes[extname] || 'application/octet-stream';
            
            fs.readFile(filePath, (error, content) => {
                if (error) {
                    res.writeHead(500);
                    res.end('Error reading file');
                } else {
                    res.writeHead(200, { 'Content-Type': contentType });
                    res.end(content, 'utf-8');
                }
            });
            return;
        }
    }

    // Rutas especiales para core (antes shared)
    if (req.url.startsWith('/core/') || req.url.startsWith('/shared/')) {
        const corePath = req.url.replace('/core/', '').replace('/shared/', '');
        const filePath = path.join(SRC_DIR, 'core', corePath);
        
        if (fs.existsSync(filePath)) {
            const extname = String(path.extname(filePath)).toLowerCase();
            const contentType = mimeTypes[extname] || 'application/octet-stream';
            
            fs.readFile(filePath, (error, content) => {
                if (error) {
                    res.writeHead(500);
                    res.end('Error reading file');
                } else {
                    res.writeHead(200, { 'Content-Type': contentType });
                    res.end(content, 'utf-8');
                }
            });
            return;
        }
    }

    // Archivos estáticos desde public
    let filePath = ROOT_DIR + req.url;
    
    // Ruta por defecto
    if (filePath === ROOT_DIR + '/') {
        filePath = ROOT_DIR + '/index.html';
    }
    
    // Verificar si es archivo PHP en public
    if (filePath.endsWith('.php')) {
        handlePHP(filePath, req, res);
        return;
    }
    
    // Servir archivos estáticos
    const extname = String(path.extname(filePath)).toLowerCase();
    const contentType = mimeTypes[extname] || 'application/octet-stream';
    
    fs.readFile(filePath, (error, content) => {
        if (error) {
            if (error.code === 'ENOENT') {
                // Intentar index.html para rutas SPA
                fs.readFile(ROOT_DIR + '/index.html', (error, content) => {
                    if (error) {
                        res.writeHead(404);
                        res.end('404 Not Found');
                    } else {
                        res.writeHead(200, { 'Content-Type': 'text/html' });
                        res.end(content, 'utf-8');
                    }
                });
            } else {
                res.writeHead(500);
                res.end('Internal Server Error: ' + error.code);
            }
        } else {
            res.writeHead(200, { 'Content-Type': contentType });
            res.end(content, 'utf-8');
        }
    });
});

// Iniciar servidor
server.listen(PORT, () => {
    console.log('╔════════════════════════════════════════════════════╗');
    console.log('║           🏢 CASA COSTANERA SERVER                ║');
    console.log('╠════════════════════════════════════════════════════╣');
    console.log(`║  🚀 Server:     http://51.222.25.222:${PORT}/        ║`);
    console.log(`║  📁 Public:     ${ROOT_DIR}         ║`);
    console.log(`║  🔧 Source:     ${SRC_DIR}          ║`);
    console.log('║  🎯 Version:    1.0.0                              ║');
    console.log('║  ⚡ Status:     RUNNING                            ║');
    console.log('╚════════════════════════════════════════════════════╝');
    console.log('\nPress Ctrl+C to stop...\n');
});

// Manejo de errores
server.on('error', (error) => {
    if (error.code === 'EADDRINUSE') {
        console.error(`❌ Error: Port ${PORT} is already in use`);
        console.log('Try stopping the existing service or use a different port');
    } else {
        console.error('Server error:', error);
    }
    process.exit(1);
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('\n⏹️  Shutting down Casa Costanera server...');
    server.close(() => {
        console.log('Server stopped');
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    console.log('\n⏹️  Shutting down Casa Costanera server...');
    server.close(() => {
        console.log('Server stopped');
        process.exit(0);
    });
});