#!/bin/bash
#################################################
# Script de Deployment - Casa Costanera
# Deploy seguro en VPS con AzuraCast en producción
#################################################

set -e  # Salir si hay errores

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuración
PROJECT_NAME="casa-costanera"
REMOTE_HOST="" # Configurar con IP o dominio del VPS
REMOTE_USER="" # Usuario SSH
REMOTE_DIR="/opt/casa-costanera"
BACKUP_DIR="/opt/backups/casa"
CURRENT_DATE=$(date +%Y%m%d_%H%M%S)

echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}   🚀 CASA COSTANERA - DEPLOYMENT SCRIPT${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"

# Verificar configuración
if [ -z "$REMOTE_HOST" ] || [ -z "$REMOTE_USER" ]; then
    echo -e "${RED}❌ Error: Configura REMOTE_HOST y REMOTE_USER en el script${NC}"
    exit 1
fi

# Función para confirmar acciones
confirm() {
    read -p "¿Continuar? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Deployment cancelado${NC}"
        exit 1
    fi
}

# 1. PREPARACIÓN LOCAL
echo -e "\n${YELLOW}📦 Paso 1: Preparando archivos locales...${NC}"
echo "- Limpiando archivos temporales..."
find . -name "*.log" -type f -delete 2>/dev/null || true
find ./src/api/temp -type f -delete 2>/dev/null || true

# 2. VERIFICAR CONEXIÓN SSH
echo -e "\n${YELLOW}🔌 Paso 2: Verificando conexión SSH...${NC}"
ssh -o ConnectTimeout=5 ${REMOTE_USER}@${REMOTE_HOST} "echo 'Conexión SSH OK'" || {
    echo -e "${RED}❌ No se pudo conectar al servidor${NC}"
    exit 1
}
echo -e "${GREEN}✓ Conexión establecida${NC}"

# 3. BACKUP EN SERVIDOR REMOTO
echo -e "\n${YELLOW}💾 Paso 3: Creando backup en servidor remoto...${NC}"
ssh ${REMOTE_USER}@${REMOTE_HOST} << EOF
    # Crear directorio de backups si no existe
    sudo mkdir -p ${BACKUP_DIR}
    
    # Si existe instalación previa, hacer backup
    if [ -d "${REMOTE_DIR}" ]; then
        echo "Creando backup de instalación existente..."
        sudo tar -czf ${BACKUP_DIR}/backup_${CURRENT_DATE}.tar.gz -C ${REMOTE_DIR} .
        echo "Backup guardado en: ${BACKUP_DIR}/backup_${CURRENT_DATE}.tar.gz"
    fi
    
    # Crear directorio del proyecto
    sudo mkdir -p ${REMOTE_DIR}
    sudo chown ${REMOTE_USER}:${REMOTE_USER} ${REMOTE_DIR}
EOF
echo -e "${GREEN}✓ Backup completado${NC}"

# 4. COPIAR ARCHIVOS AL SERVIDOR
echo -e "\n${YELLOW}📤 Paso 4: Copiando archivos al servidor...${NC}"
echo "Esto puede tomar varios minutos..."

# Crear archivo tar excluyendo archivos innecesarios
tar -czf /tmp/${PROJECT_NAME}.tar.gz \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='*.log' \
    --exclude='playground-backup*' \
    --exclude='Technical Docs' \
    .

# Copiar y extraer en el servidor
scp /tmp/${PROJECT_NAME}.tar.gz ${REMOTE_USER}@${REMOTE_HOST}:/tmp/
ssh ${REMOTE_USER}@${REMOTE_HOST} << EOF
    cd ${REMOTE_DIR}
    tar -xzf /tmp/${PROJECT_NAME}.tar.gz
    rm /tmp/${PROJECT_NAME}.tar.gz
    
    # Asegurar permisos correctos
    chmod +x deploy.sh
    sudo chmod -R 777 database/
    sudo chmod -R 777 src/api/temp/
    sudo chmod -R 777 public/audio/
    sudo chmod -R 777 public/playground/logs/
EOF

# Limpiar archivo temporal local
rm /tmp/${PROJECT_NAME}.tar.gz
echo -e "${GREEN}✓ Archivos copiados${NC}"

# 5. CONFIGURAR ENVIRONMENT
echo -e "\n${YELLOW}⚙️ Paso 5: Configurando variables de entorno...${NC}"
echo -e "${RED}IMPORTANTE: Debes editar el archivo .env en el servidor${NC}"
echo "Archivo: ${REMOTE_DIR}/.env"
confirm

# 6. BUILD Y START CON DOCKER
echo -e "\n${YELLOW}🐳 Paso 6: Construyendo y ejecutando con Docker...${NC}"
ssh ${REMOTE_USER}@${REMOTE_HOST} << EOF
    cd ${REMOTE_DIR}
    
    # Verificar si Docker está instalado
    if ! command -v docker &> /dev/null; then
        echo -e "${RED}Docker no está instalado. Instalando...${NC}"
        curl -fsSL https://get.docker.com -o get-docker.sh
        sudo sh get-docker.sh
        sudo usermod -aG docker ${REMOTE_USER}
        rm get-docker.sh
    fi
    
    # Verificar docker-compose
    if ! command -v docker-compose &> /dev/null; then
        echo "Instalando docker-compose..."
        sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        sudo chmod +x /usr/local/bin/docker-compose
    fi
    
    # Detener contenedor anterior si existe
    docker-compose down 2>/dev/null || true
    
    # Construir y ejecutar
    echo "Construyendo imagen Docker..."
    docker-compose build --no-cache
    
    echo "Iniciando contenedor..."
    docker-compose up -d
    
    # Esperar a que el servicio esté listo
    echo "Esperando que el servicio inicie..."
    sleep 10
    
    # Verificar estado
    docker-compose ps
    docker-compose logs --tail=20
EOF

# 7. CONFIGURAR NGINX (OPCIONAL)
echo -e "\n${YELLOW}🔧 Paso 7: Configuración de Nginx${NC}"
echo "¿Deseas configurar Nginx para proxy reverso? (y/n)"
read -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    ssh ${REMOTE_USER}@${REMOTE_HOST} << 'EOF'
        # Crear configuración de nginx
        sudo tee /etc/nginx/sites-available/casa-costanera > /dev/null << 'NGINX'
# Configuración para Casa Costanera (NO MODIFICAR AZURACAST)
server {
    listen 80;
    server_name casa.tudominio.com;  # CAMBIAR POR TU DOMINIO
    
    location / {
        proxy_pass http://127.0.0.1:4000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Timeouts largos para TTS
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
NGINX
        
        # Activar sitio
        sudo ln -sf /etc/nginx/sites-available/casa-costanera /etc/nginx/sites-enabled/
        
        # Verificar configuración
        sudo nginx -t
        
        # Recargar nginx
        sudo systemctl reload nginx
        
        echo "Nginx configurado. Recuerda actualizar el server_name con tu dominio"
EOF
fi

# 8. VERIFICACIÓN FINAL
echo -e "\n${YELLOW}✅ Paso 8: Verificación final...${NC}"
ssh ${REMOTE_USER}@${REMOTE_HOST} << EOF
    echo "Estado del contenedor:"
    docker ps | grep ${PROJECT_NAME}
    
    echo -e "\nProbando conectividad:"
    curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:4000/
    
    echo -e "\nLogs recientes:"
    docker-compose -f ${REMOTE_DIR}/docker-compose.yml logs --tail=10
EOF

echo -e "\n${GREEN}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}   ✅ DEPLOYMENT COMPLETADO${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo -e "\n📝 SIGUIENTES PASOS:"
echo -e "1. Editar ${REMOTE_DIR}/.env con las API keys correctas"
echo -e "2. Verificar en: http://${REMOTE_HOST}:4000"
echo -e "3. Configurar DNS si usas subdominio"
echo -e "4. Monitorear logs: docker-compose logs -f"
echo -e "\n🔄 COMANDOS ÚTILES:"
echo -e "- Ver logs: ssh ${REMOTE_USER}@${REMOTE_HOST} 'cd ${REMOTE_DIR} && docker-compose logs -f'"
echo -e "- Reiniciar: ssh ${REMOTE_USER}@${REMOTE_HOST} 'cd ${REMOTE_DIR} && docker-compose restart'"
echo -e "- Detener: ssh ${REMOTE_USER}@${REMOTE_HOST} 'cd ${REMOTE_DIR} && docker-compose down'"
echo -e "- Rollback: ssh ${REMOTE_USER}@${REMOTE_HOST} 'cd ${REMOTE_DIR} && tar -xzf ${BACKUP_DIR}/backup_${CURRENT_DATE}.tar.gz'"