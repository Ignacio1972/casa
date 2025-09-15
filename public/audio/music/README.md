# 🎵 Carpeta de Música para Jingles

Esta carpeta contiene los archivos de música que se pueden usar como fondo en los jingles generados.

## 📁 Ubicación
`/var/www/casa/public/audio/music/`

## 🎼 Música Disponible Actualmente

1. **Martin Roth - Just Sine Waves.mp3** - Ambiente relajante
2. **Martin Roth - The Silence Between the Notes.mp3** - Contemplativo
3. **Charly García - Pasajera en Trance.mp3** - Rock energético
4. **Lucie Treacher - Origata Atelier.mp3** - Electrónica moderna
5. **Maneesh de Moor - Oracle.mp3** - World/Místico
6. **Mark Alow - En La Niebla.mp3** - Deep House
7. **Mark Isham - Raffles In Rio.mp3** - Jazz suave
8. **Max Cooper - Reflect.mp3** - Experimental

## ➕ Cómo Agregar Nueva Música

1. **Sube el archivo MP3** a esta carpeta (`/var/www/casa/public/audio/music/`)
   
2. **Formatos soportados**: MP3, WAV, OGG

3. **Recomendaciones**:
   - Duración ideal: 2-3 minutos
   - Bitrate: 128-320 kbps
   - Música sin letra o con letra suave funciona mejor
   - Evitar cambios bruscos de volumen

4. **Metadata automática**: El sistema detectará automáticamente los nuevos archivos y los mostrará con el nombre del archivo.

5. **Para agregar metadata personalizada** (opcional):
   - Editar `/var/www/casa/src/api/music-service.php`
   - Agregar entrada en el array `$musicMetadata` con:
     - `name`: Nombre amigable
     - `category`: Categoría (ambient, rock, electronic, etc.)
     - `mood`: Estado de ánimo (calm, energetic, happy, etc.)
     - `description`: Descripción breve

## 🎛️ Uso en el Sistema

### Dashboard (Versión Simple)
- Los usuarios verán un selector con los nombres amigables
- Configuración automática optimizada

### Jingle Studio (Playground)
- Control total sobre todos los parámetros
- Preview de música antes de generar
- Historial de jingles creados

## 🔧 Troubleshooting

Si la música no aparece:
1. Verifica que el archivo esté en esta carpeta
2. Verifica permisos de lectura (chmod 644)
3. Limpia caché del navegador
4. Verifica que ffprobe esté instalado (para duración)

## 📝 Notas

- El sistema usa ffmpeg para mezclar la música con la voz
- El auto-ducking reduce automáticamente el volumen de la música cuando habla la voz
- Los archivos muy grandes pueden tardar más en procesarse