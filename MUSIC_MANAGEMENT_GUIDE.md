# Guía de Gestión de Música para Jingles

## 📋 Resumen Rápido

Para agregar o eliminar canciones sin romper el sistema, use uno de estos métodos:

### Método 1: Script Interactivo (Recomendado)
```bash
bash /var/www/casa/scripts/music-manager.sh
```

### Método 2: Comandos Directos
```bash
# Listar canciones
php /var/www/casa/scripts/manage-music.php list

# Agregar canción
php /var/www/casa/scripts/manage-music.php add /ruta/a/tu/cancion.mp3

# Eliminar canción
php /var/www/casa/scripts/manage-music.php remove nombre_cancion.mp3

# Reiniciar servicios (IMPORTANTE después de cambios)
php /var/www/casa/scripts/manage-music.php restart
```

## 🎵 Canciones Disponibles Actualmente

- Cool.mp3
- Kids.mp3
- Pop.mp3
- Slow.mp3
- Smooth.mp3
- Uplift.mp3

## ➕ Agregar Nueva Canción

### Pasos Seguros:

1. **Preparar el archivo MP3**
   - Asegúrese de que es un archivo MP3 válido
   - Nombre sin espacios especiales es preferible
   - Tamaño recomendado: menos de 20MB

2. **Agregar usando el script interactivo**
   ```bash
   bash /var/www/casa/scripts/music-manager.sh
   # Seleccionar opción 2 (Agregar nueva canción)
   # Ingresar la ruta completa del archivo
   # El script reiniciará los servicios automáticamente
   ```

3. **O agregar manualmente**
   ```bash
   # Copiar archivo
   sudo cp /ruta/a/tu/cancion.mp3 /var/www/casa/public/audio/music/

   # Ajustar permisos
   sudo chmod 644 /var/www/casa/public/audio/music/tu_cancion.mp3

   # IMPORTANTE: Reiniciar PHP-FPM
   sudo systemctl restart php8.1-fpm
   ```

## ➖ Eliminar Canción

### Pasos Seguros:

1. **Usando el script interactivo**
   ```bash
   bash /var/www/casa/scripts/music-manager.sh
   # Seleccionar opción 3 (Eliminar canción)
   # Ingresar el nombre del archivo
   # El script reiniciará los servicios automáticamente
   ```

2. **O eliminar manualmente**
   ```bash
   # Eliminar archivo
   sudo rm /var/www/casa/public/audio/music/nombre_cancion.mp3

   # IMPORTANTE: Reiniciar PHP-FPM
   sudo systemctl restart php8.1-fpm
   ```

## ⚠️ IMPORTANTE: Reiniciar Servicios

**Después de CUALQUIER cambio en las canciones (agregar o eliminar), SIEMPRE debe reiniciar PHP-FPM:**

```bash
sudo systemctl restart php8.1-fpm
```

Si no reinicia, pueden ocurrir los siguientes problemas:
- Error "Archivo de música no encontrado" aunque el archivo exista
- Error "Failed to execute 'json' on 'Response'"
- El sistema puede intentar usar canciones eliminadas

## 🔧 Solución de Problemas

### Error: "Archivo de música no encontrado"
```bash
# Verificar que el archivo existe
ls -la /var/www/casa/public/audio/music/

# Reiniciar servicios
sudo systemctl restart php8.1-fpm
```

### Error: "Failed to execute 'json' on 'Response'"
Este error generalmente indica que se está usando un archivo que ya no existe.
```bash
# Reiniciar PHP-FPM para limpiar caché
sudo systemctl restart php8.1-fpm
```

### Validar todas las canciones
```bash
php /var/www/casa/scripts/manage-music.php validate
```

## 📁 Ubicaciones Importantes

- **Directorio de música**: `/var/www/casa/public/audio/music/`
- **Scripts de gestión**: `/var/www/casa/scripts/`
- **Configuración de jingles**: `/var/www/casa/src/api/data/jingle-config.json`

## 🚀 Comandos Útiles

```bash
# Ver canciones disponibles
ls -la /var/www/casa/public/audio/music/*.mp3

# Ver espacio usado por las canciones
du -sh /var/www/casa/public/audio/music/

# Verificar permisos
ls -la /var/www/casa/public/audio/music/

# Ver logs si hay problemas
tail -f /var/www/casa/src/api/logs/tts-$(date +%Y-%m-%d).log

# Limpiar archivos temporales antiguos
find /var/www/casa/src/api/temp/ -type f -name "*.mp3" -mtime +7 -delete
```

## 📝 Recomendaciones

1. **Nombres de archivo**: Use nombres sin espacios o caracteres especiales
   - Bueno: `musica_feliz.mp3`, `intro_2024.mp3`
   - Evitar: `música feliz!.mp3`, `intro (nueva).mp3`

2. **Formato**: Solo archivos MP3 son soportados

3. **Tamaño**: Mantenga los archivos bajo 20MB para mejor rendimiento

4. **Respaldo**: Haga respaldo de sus canciones antes de eliminarlas

5. **Pruebas**: Después de agregar música nueva, pruebe con:
   ```bash
   php /var/www/casa/test-jingle-system.php
   ```

## 🔄 Proceso Automático Completo

Para máxima seguridad, use siempre el script interactivo:

```bash
# Ejecutar el gestor de música
bash /var/www/casa/scripts/music-manager.sh

# El script se encarga de:
# - Validar archivos
# - Copiar con permisos correctos
# - Reiniciar servicios automáticamente
# - Verificar la integridad
```

---

**Última actualización**: 2025-10-26
**Versión**: 1.0