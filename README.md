# Casa Costanera - Sistema de Radio Automatizada

Sistema de gestión de radio automatizada con generación de mensajes mediante Text-to-Speech.

## 🚀 Características

- **Generación TTS**: Integración con ElevenLabs para crear mensajes de audio naturales
- **Gestión de Radio**: Integración completa con AzuraCast
- **Dashboard Intuitivo**: Interfaz moderna para crear y gestionar mensajes
- **Administración de Voces**: Playground para configurar y probar voces
- **Base de Datos**: SQLite para almacenamiento local eficiente
- **API RESTful**: Endpoints para todas las operaciones

## 📋 Requisitos

- PHP 8.1+
- nginx
- SQLite3
- ffmpeg
- AzuraCast configurado

## 🔧 Instalación

1. Clonar el repositorio:
```bash
git clone https://github.com/Ignacio1972/casa.git
cd casa
```

2. Copiar y configurar el archivo de configuración:
```bash
cp src/api/config.php.example src/api/config.php
# Editar config.php con tus claves API
```

3. Configurar nginx (ver ejemplo en `/etc/nginx/sites-available/casa`)

4. Asegurar permisos correctos:
```bash
chown -R www-data:www-data src/api/temp/
chmod 777 database/
chmod 666 database/casa.db
```

## 🎯 Uso

Acceder a la aplicación en: `http://tu-servidor:4000`

### Módulos principales:

- **Dashboard**: Generación de mensajes TTS
- **Biblioteca**: Gestión de archivos de audio
- **Calendario**: Programación de eventos
- **Playground**: Administración de voces

## 🔑 Configuración de APIs

### ElevenLabs
Obtener API key desde: https://elevenlabs.io/

### AzuraCast
Configurar API key desde tu instalación de AzuraCast

## 📁 Estructura del Proyecto

```
casa/
├── public/          # Archivos públicos
├── src/             # Código fuente
│   ├── api/         # Backend PHP
│   ├── core/        # Núcleo JS
│   └── modules/     # Módulos del sistema
├── database/        # Base de datos SQLite
└── config/          # Configuraciones
```

## 🛠️ Tecnologías

- **Backend**: PHP 8.1, SQLite
- **Frontend**: JavaScript vanilla, CSS moderno
- **Server**: nginx + PHP-FPM
- **TTS**: ElevenLabs API
- **Radio**: AzuraCast

## 📄 Licencia

Proyecto privado - Todos los derechos reservados

## 👥 Contribuciones

Para contribuir, por favor crear un issue o pull request.

---

Desarrollado con ❤️ para Casa Costanera