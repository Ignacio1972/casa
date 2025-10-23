# 🚀 GUÍA DE DEPLOYMENT - Casa Costanera con Docker

## ✅ TODO ESTÁ LISTO - Incluye:
- ✅ **Dashboard completo** con TTS, categorías, voces
- ✅ **Playground** con jingle-studio, claude integration
- ✅ **Calendario** con scheduling
- ✅ **Base de datos** SQLite
- ✅ **APIs** de ElevenLabs, Claude, AzuraCast

## 📋 PASOS PARA DEPLOYMENT

### 1️⃣ **Preparación en TU VPS actual (desarrollo)**
```bash
# Dar permisos al script
chmod +x deploy.sh

# Editar el script con los datos del VPS de producción
nano deploy.sh
# Cambiar estas líneas:
# REMOTE_HOST="IP_DEL_VPS_PRODUCCION"
# REMOTE_USER="tu_usuario_ssh"
```

### 2️⃣ **Ejecutar el deployment**
```bash
# Opción A: Usar el script automatizado
./deploy.sh

# Opción B: Hacerlo manualmente
scp -r /var/www/casa usuario@vps-produccion:/opt/
```

### 3️⃣ **En el VPS de PRODUCCIÓN**

#### Configurar el archivo .env:
```bash
cd /opt/casa-costanera
cp .env.production .env
nano .env

# Cambiar:
# - APP_URL con tu dominio
# - AZURACAST_API_KEY (obtener desde AzuraCast)
# - Mantener las API keys de ElevenLabs y Claude
```

#### Iniciar con Docker:
```bash
# Construir imagen
docker-compose build

# Iniciar contenedor
docker-compose up -d

# Ver logs
docker-compose logs -f
```

### 4️⃣ **Configurar Nginx (para subdominio)**
```nginx
# En /etc/nginx/sites-available/casa
server {
    listen 80;
    server_name casa.tu-radio.com;
    
    location / {
        proxy_pass http://localhost:4000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}

# Activar
sudo ln -s /etc/nginx/sites-available/casa /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 🔍 VERIFICACIÓN

### Comprobar que funciona:
```bash
# Ver estado del contenedor
docker ps

# Probar la aplicación
curl http://localhost:4000

# Ver logs en tiempo real
docker-compose logs -f casa-costanera
```

### URLs para acceder:
- **Dashboard**: `http://tu-dominio:4000/`
- **Playground**: `http://tu-dominio:4000/playground/`
- **Jingle Studio**: `http://tu-dominio:4000/playground/jingle-studio.html`
- **Claude AI**: `http://tu-dominio:4000/playground/claude.html`

## 🛠️ COMANDOS ÚTILES

```bash
# Reiniciar aplicación
docker-compose restart

# Detener aplicación
docker-compose down

# Ver uso de recursos
docker stats casa-costanera-app

# Entrar al contenedor
docker exec -it casa-costanera-app bash

# Ver base de datos
docker exec -it casa-costanera-app sqlite3 /app/database/casa.db

# Backup de la base de datos
docker cp casa-costanera-app:/app/database/casa.db ./backup-casa.db
```

## ⚠️ IMPORTANTE - Seguridad

1. **NO interferirá con AzuraCast** - Corre en contenedor aislado
2. **Recursos limitados** - Máximo 512MB RAM, 1 CPU
3. **Puerto configurable** - Si 4000 está ocupado, cambiar en docker-compose.yml
4. **Backup automático** - El script hace backup antes de actualizar

## 🔄 ACTUALIZAR LA APLICACIÓN

```bash
# En tu VPS de desarrollo
git add .
git commit -m "Update"

# Ejecutar deployment nuevamente
./deploy.sh

# El script automáticamente:
# - Hace backup
# - Actualiza archivos
# - Reinicia contenedor
# - Mantiene la base de datos
```

## 🆘 TROUBLESHOOTING

### Si el puerto 4000 está ocupado:
```yaml
# Editar docker-compose.yml
ports:
  - "4001:4000"  # Cambiar a otro puerto
```

### Si falta memoria:
```yaml
# Reducir límites en docker-compose.yml
deploy:
  resources:
    limits:
      memory: 256M  # Reducir
```

### Si no se conecta con AzuraCast:
```bash
# Verificar que AzuraCast API está activa
curl http://localhost/api/stations

# Verificar API key en .env
```

## 📞 SOPORTE

- Los logs están en: `docker-compose logs`
- La base de datos persiste en: `/opt/casa-costanera/database/`
- Los audios generados en: `/opt/casa-costanera/public/audio/`

---

**💡 TIP FINAL**: Primero prueba en un VPS de prueba antes de producción real.