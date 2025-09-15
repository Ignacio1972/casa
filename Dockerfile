# Dockerfile para Casa Costanera Radio Automation
FROM node:18-slim

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    php8.2-cli \
    php8.2-cgi \
    php8.2-sqlite3 \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    sqlite3 \
    ffmpeg \
    nginx \
    supervisor \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Crear directorio de trabajo
WORKDIR /app

# Copiar todo el proyecto (incluyendo playground)
COPY . /app/

# Crear directorios necesarios con permisos
RUN mkdir -p /app/database \
    && mkdir -p /app/src/api/temp \
    && mkdir -p /app/public/audio \
    && mkdir -p /app/public/playground/logs \
    && chmod -R 777 /app/database \
    && chmod -R 777 /app/src/api/temp \
    && chmod -R 777 /app/public/audio \
    && chmod -R 777 /app/public/playground/logs

# Copiar base de datos si existe, o crear nueva
RUN if [ -f /app/database/casa.db ]; then \
        chmod 666 /app/database/casa.db; \
    else \
        sqlite3 /app/database/casa.db "CREATE TABLE IF NOT EXISTS audio_metadata (id INTEGER PRIMARY KEY);" && \
        chmod 666 /app/database/casa.db; \
    fi

# Configuración de supervisor para múltiples procesos
RUN echo '[supervisord]\n\
nodaemon=true\n\
\n\
[program:nodejs]\n\
command=node /app/server.js\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
\n\
[program:php-cgi]\n\
command=php-cgi -b 127.0.0.1:9000\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0' > /etc/supervisor/conf.d/supervisord.conf

# Exponer puerto
EXPOSE 4000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:4000/ || exit 1

# Comando de inicio
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]