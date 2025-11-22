# 🎵 Player de Audio Local - Documentación Completa

## Tabla de Contenidos
1. [Visión General](#visión-general)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Estructura de Archivos](#estructura-de-archivos)
4. [Implementación de Módulos Python](#implementación-de-módulos-python)
5. [Interfaz Web (UI)](#interfaz-web-ui)
6. [Configuración](#configuración)
7. [Instalación](#instalación)
8. [Uso y Operación](#uso-y-operación)
9. [Troubleshooting](#troubleshooting)

---

## Visión General

### ¿Qué es este sistema?

Un reproductor de audio local que corre en Mac Mini (o Windows) y proporciona:

- **Reproducción continua 24/7** de música local (archivos MP3)
- **Interrupción elegante** con ducking/fade cuando llega TTS del VPS
- **Interfaz web** para control remoto (sliders de volumen, play/pause, next)
- **Sin dependencia de internet** para la música (solo para recibir TTS)

### Flujo de Operación

```
┌─────────────────────────────────────────┐
│  Mac Mini (hardware físico)             │
│                                         │
│  Python App corriendo 24/7              │
│  ┌───────────────────────────────────┐  │
│  │ music/ (MP3 locales)              │  │
│  │   → Audio Engine                  │  │
│  │   → Mixing (música + TTS)         │  │
│  │   → Ducking/Fade                  │  │
│  │   → Speakers 🔊                   │  │
│  └───────────────────────────────────┘  │
│                                         │
│  Flask Web Server :5000                 │
│  (acepta control remoto)                │
└──────────────┬──────────────────────────┘
               │
       ┌───────┴────────┐
       │                │
   Internet         LAN
       │                │
   ┌───▼────┐      ┌───▼────┐
   │  VPS   │      │ Laptop │
   │ (envía │      │(control│
   │  TTS)  │      │   UI)  │
   └────────┘      └────────┘
```

---

## Arquitectura del Sistema

### Componentes Principales

```
┌────────────────────────────────────────────────┐
│            main.py (Entry Point)               │
│  - Inicializa todos los componentes           │
│  - Coordina threads                           │
└────────────────────────────────────────────────┘
            │
            ├─────────────────────────────────┐
            │                                 │
┌───────────▼──────────┐      ┌──────────────▼────────────┐
│  audio_engine.py     │      │  web_server.py (Flask)    │
│  ────────────────    │      │  ──────────────────────   │
│  - sounddevice       │      │  - HTTP endpoints         │
│  - Mixing 2 canales  │      │  - SocketIO (real-time)   │
│  - Fade lineal       │      │  - Sirve UI estática      │
└───────────┬──────────┘      └───────────────────────────┘
            │
    ┌───────┼───────┐
    │       │       │
┌───▼─────┐ │ ┌─────▼──────┐
│ music_  │ │ │ tts_       │
│ player  │ │ │ handler    │
│         │ │ │            │
│ Gestión │ │ │ Ducking    │
│ playlist│ │ │ Secuencia  │
└─────────┘ │ └────────────┘
            │
    ┌───────▼────────┐
    │  vps_client    │
    │  ────────────  │
    │  HTTP polling  │
    │  (cada 2s)     │
    └────────────────┘
```

### Threading Model

```python
# Thread 1: Audio Engine (principal)
# - Reproduce música continuamente
# - Mezcla con TTS cuando llega
# - Salida a sounddevice

# Thread 2: Flask Web Server
# - Sirve UI
# - Acepta comandos (sliders, play/pause)
# - WebSocket para updates en tiempo real

# Thread 3: VPS Client (polling)
# - Cada 2s pregunta al VPS si hay TTS nuevo
# - Descarga TTS si existe
# - Notifica a tts_handler

# Thread 4: TTS Handler
# - Escucha eventos de nuevo TTS
# - Orquesta la secuencia de ducking
# - Coordina con audio_engine
```

---

## Estructura de Archivos

```
audio-player/
├── venv/                          # Virtual environment (generado)
├── requirements.txt               # Dependencias Python
├── config.json                    # Configuración principal
├── README.md                      # Guía rápida
├── install.sh                     # Script instalación Mac
├── install.bat                    # Script instalación Windows
│
├── music/                         # 🎵 Tu biblioteca MP3
│   ├── track1.mp3
│   ├── track2.mp3
│   └── ...
│
├── jingles/                       # 🎺 Jingles opcionales
│   ├── intro.mp3
│   └── outro.mp3
│
├── temp/                          # 🗂️ TTS descargados (temporal)
│   └── tts_*.mp3
│
├── logs/                          # 📋 Logs de operación
│   ├── audio-player-2025-01-21.log
│   └── ...
│
└── src/
    ├── main.py                    # 🚀 Entry point
    ├── audio_engine.py            # 🎚️ Motor de audio
    ├── music_player.py            # 🎵 Gestión playlist
    ├── tts_handler.py             # 📢 Manejo interrupciones
    ├── vps_client.py              # 🌐 Comunicación VPS
    ├── web_server.py              # 🖥️ Flask server
    ├── config_manager.py          # ⚙️ Gestión config
    └── static/
        ├── index.html             # 🎨 UI principal
        ├── style.css              # 🎨 Estilos
        └── app.js                 # 🎨 Lógica frontend
```

---

## Implementación de Módulos Python

### 1. requirements.txt

```txt
# Audio
sounddevice==0.4.6
numpy==1.24.3
scipy==1.11.4
pydub==0.25.1

# Web Server
flask==3.0.0
flask-socketio==5.3.5
flask-cors==4.0.0

# VPS Communication
requests==2.31.0
websockets==12.0

# Utilities
python-socketio==5.10.0
```

### 2. config.json

```json
{
  "audio": {
    "sample_rate": 44100,
    "buffer_size": 2048,
    "channels": 2,
    "device": null
  },
  "volumes": {
    "music": 0.7,
    "tts": 0.9,
    "master": 0.8
  },
  "ducking": {
    "enabled": true,
    "fade_out_duration": 2.0,
    "fade_in_duration": 2.0,
    "duck_level": 0.2,
    "pre_tts_silence": 0.5,
    "post_tts_silence": 0.5
  },
  "jingles": {
    "enabled": false,
    "intro": "jingles/intro.mp3",
    "outro": "jingles/outro.mp3"
  },
  "vps": {
    "polling_url": "http://tu-vps.com/api/pending-tts.php",
    "polling_interval": 2,
    "download_url": "http://tu-vps.com/public/audio/generated/"
  },
  "web_ui": {
    "host": "0.0.0.0",
    "port": 5000
  },
  "music_folder": "music",
  "temp_folder": "temp",
  "log_folder": "logs"
}
```

### 3. src/config_manager.py

```python
"""
Gestión de configuración del sistema
"""
import json
import os
from pathlib import Path

class ConfigManager:
    def __init__(self, config_path='config.json'):
        self.config_path = config_path
        self.config = self.load()

    def load(self):
        """Carga configuración desde archivo JSON"""
        if not os.path.exists(self.config_path):
            raise FileNotFoundError(f"Config file not found: {self.config_path}")

        with open(self.config_path, 'r') as f:
            return json.load(f)

    def save(self):
        """Guarda configuración actual a archivo"""
        with open(self.config_path, 'w') as f:
            json.dump(self.config, f, indent=2)

    def get(self, path, default=None):
        """
        Obtiene valor de config usando dot notation
        Ejemplo: config.get('audio.sample_rate')
        """
        keys = path.split('.')
        value = self.config

        for key in keys:
            if isinstance(value, dict) and key in value:
                value = value[key]
            else:
                return default

        return value

    def set(self, path, value):
        """
        Establece valor en config usando dot notation
        Ejemplo: config.set('volumes.music', 0.8)
        """
        keys = path.split('.')
        target = self.config

        for key in keys[:-1]:
            if key not in target:
                target[key] = {}
            target = target[key]

        target[keys[-1]] = value
```

### 4. src/audio_engine.py

```python
"""
Motor de audio principal
Gestiona reproducción, mixing y salida a speakers
"""
import sounddevice as sd
import numpy as np
import threading
import queue
import logging
from pydub import AudioSegment
from scipy import signal

logger = logging.getLogger(__name__)

class AudioEngine:
    def __init__(self, config):
        self.config = config
        self.sample_rate = config.get('audio.sample_rate', 44100)
        self.channels = config.get('audio.channels', 2)
        self.buffer_size = config.get('audio.buffer_size', 2048)

        # Volúmenes
        self.music_volume = config.get('volumes.music', 0.7)
        self.tts_volume = config.get('volumes.tts', 0.9)
        self.master_volume = config.get('volumes.master', 0.8)

        # Colas de audio
        self.music_queue = queue.Queue(maxsize=10)
        self.tts_queue = queue.Queue(maxsize=5)

        # Estado
        self.is_playing = False
        self.is_ducking = False
        self.current_duck_level = 1.0

        # Stream de salida
        self.stream = None

        # Lock para operaciones thread-safe
        self.lock = threading.Lock()

    def start(self):
        """Inicia el motor de audio"""
        logger.info("Starting audio engine...")

        # Listar dispositivos disponibles
        logger.info("Available audio devices:")
        logger.info(sd.query_devices())

        # Crear stream de salida
        self.stream = sd.OutputStream(
            samplerate=self.sample_rate,
            channels=self.channels,
            blocksize=self.buffer_size,
            callback=self._audio_callback,
            dtype='float32'
        )

        self.is_playing = True
        self.stream.start()
        logger.info("Audio engine started")

    def stop(self):
        """Detiene el motor de audio"""
        logger.info("Stopping audio engine...")
        self.is_playing = False

        if self.stream:
            self.stream.stop()
            self.stream.close()

        logger.info("Audio engine stopped")

    def _audio_callback(self, outdata, frames, time_info, status):
        """
        Callback principal de audio
        Se llama continuamente por sounddevice
        """
        if status:
            logger.warning(f"Audio callback status: {status}")

        # Inicializar buffer de salida con silencio
        output = np.zeros((frames, self.channels), dtype='float32')

        # Canal 1: Música (si hay en la cola)
        music_data = self._get_audio_data(self.music_queue, frames)
        if music_data is not None:
            # Aplicar volumen de música y ducking
            with self.lock:
                music_volume = self.music_volume * self.current_duck_level
            output += music_data * music_volume

        # Canal 2: TTS (si hay en la cola)
        tts_data = self._get_audio_data(self.tts_queue, frames)
        if tts_data is not None:
            # Aplicar volumen de TTS
            output += tts_data * self.tts_volume

        # Aplicar volumen master
        output *= self.master_volume

        # Clipping para evitar distorsión
        output = np.clip(output, -1.0, 1.0)

        # Escribir a salida
        outdata[:] = output

    def _get_audio_data(self, audio_queue, frames):
        """Obtiene datos de audio de una cola"""
        try:
            data = audio_queue.get_nowait()

            # Si hay menos frames que los necesarios, rellenar con ceros
            if len(data) < frames:
                padding = np.zeros((frames - len(data), self.channels), dtype='float32')
                data = np.vstack([data, padding])

            return data[:frames]

        except queue.Empty:
            return None

    def queue_music(self, audio_data):
        """Agrega audio de música a la cola"""
        try:
            self.music_queue.put_nowait(audio_data)
        except queue.Full:
            logger.warning("Music queue is full, dropping frames")

    def queue_tts(self, audio_data):
        """Agrega audio de TTS a la cola"""
        try:
            self.tts_queue.put_nowait(audio_data)
        except queue.Full:
            logger.warning("TTS queue is full, dropping frames")

    def set_duck_level(self, level):
        """
        Establece el nivel de ducking (0.0 - 1.0)
        1.0 = volumen normal, 0.2 = ducked al 20%
        """
        with self.lock:
            self.current_duck_level = level

    def fade_duck(self, target_level, duration):
        """
        Fade lineal del nivel de ducking

        Args:
            target_level: Nivel objetivo (0.0 - 1.0)
            duration: Duración en segundos
        """
        steps = int(duration * 50)  # 50 pasos por segundo

        with self.lock:
            start_level = self.current_duck_level

        for i in range(steps):
            progress = (i + 1) / steps
            new_level = start_level + (target_level - start_level) * progress

            with self.lock:
                self.current_duck_level = new_level

            threading.Event().wait(duration / steps)

    def load_audio_file(self, file_path):
        """
        Carga un archivo de audio y lo convierte a formato adecuado

        Returns:
            numpy array de audio normalizado
        """
        try:
            # Cargar con pydub
            audio = AudioSegment.from_file(file_path)

            # Convertir a sample rate y canales correctos
            audio = audio.set_frame_rate(self.sample_rate)
            audio = audio.set_channels(self.channels)

            # Convertir a numpy array
            samples = np.array(audio.get_array_of_samples(), dtype='float32')

            # Normalizar a rango -1.0 a 1.0
            samples = samples / (2**15)  # 16-bit audio

            # Reshape para stereo si es necesario
            if self.channels == 2:
                samples = samples.reshape((-1, 2))

            return samples

        except Exception as e:
            logger.error(f"Error loading audio file {file_path}: {e}")
            return None

    def set_music_volume(self, volume):
        """Establece volumen de música (0.0 - 1.0)"""
        self.music_volume = np.clip(volume, 0.0, 1.0)
        logger.info(f"Music volume set to {self.music_volume:.2f}")

    def set_tts_volume(self, volume):
        """Establece volumen de TTS (0.0 - 1.0)"""
        self.tts_volume = np.clip(volume, 0.0, 1.0)
        logger.info(f"TTS volume set to {self.tts_volume:.2f}")

    def set_master_volume(self, volume):
        """Establece volumen master (0.0 - 1.0)"""
        self.master_volume = np.clip(volume, 0.0, 1.0)
        logger.info(f"Master volume set to {self.master_volume:.2f}")
```

### 5. src/music_player.py

```python
"""
Gestión de playlist y reproducción de música
"""
import os
import random
import threading
import logging
from pathlib import Path

logger = logging.getLogger(__name__)

class MusicPlayer:
    def __init__(self, config, audio_engine):
        self.config = config
        self.audio_engine = audio_engine

        self.music_folder = config.get('music_folder', 'music')
        self.playlist = []
        self.current_index = 0
        self.shuffle = False
        self.repeat = True

        self.is_playing = False
        self.is_paused = False

        self.playback_thread = None
        self.stop_event = threading.Event()

    def scan_music_folder(self):
        """Escanea carpeta de música y crea playlist"""
        logger.info(f"Scanning music folder: {self.music_folder}")

        music_path = Path(self.music_folder)

        if not music_path.exists():
            logger.error(f"Music folder does not exist: {self.music_folder}")
            return

        # Buscar archivos MP3
        mp3_files = list(music_path.glob('*.mp3'))

        if not mp3_files:
            logger.warning(f"No MP3 files found in {self.music_folder}")
            return

        self.playlist = [str(f) for f in mp3_files]
        logger.info(f"Found {len(self.playlist)} tracks")

        # Shuffle si está habilitado
        if self.shuffle:
            random.shuffle(self.playlist)

    def start(self):
        """Inicia reproducción de música"""
        if not self.playlist:
            self.scan_music_folder()

        if not self.playlist:
            logger.error("Cannot start playback: empty playlist")
            return

        self.is_playing = True
        self.is_paused = False
        self.stop_event.clear()

        self.playback_thread = threading.Thread(target=self._playback_loop, daemon=True)
        self.playback_thread.start()

        logger.info("Music playback started")

    def stop(self):
        """Detiene reproducción de música"""
        self.is_playing = False
        self.stop_event.set()

        if self.playback_thread:
            self.playback_thread.join(timeout=2.0)

        logger.info("Music playback stopped")

    def pause(self):
        """Pausa reproducción"""
        self.is_paused = True
        logger.info("Music playback paused")

    def resume(self):
        """Reanuda reproducción"""
        self.is_paused = False
        logger.info("Music playback resumed")

    def next_track(self):
        """Salta al siguiente track"""
        self.current_index = (self.current_index + 1) % len(self.playlist)
        logger.info(f"Skipping to next track: {self.get_current_track()}")

    def previous_track(self):
        """Vuelve al track anterior"""
        self.current_index = (self.current_index - 1) % len(self.playlist)
        logger.info(f"Going to previous track: {self.get_current_track()}")

    def get_current_track(self):
        """Retorna el nombre del track actual"""
        if not self.playlist:
            return None

        track_path = self.playlist[self.current_index]
        return os.path.basename(track_path)

    def _playback_loop(self):
        """Loop principal de reproducción"""
        while self.is_playing and not self.stop_event.is_set():
            if self.is_paused:
                self.stop_event.wait(0.1)
                continue

            # Obtener track actual
            track_path = self.playlist[self.current_index]
            logger.info(f"Playing: {os.path.basename(track_path)}")

            # Cargar audio
            audio_data = self.audio_engine.load_audio_file(track_path)

            if audio_data is None:
                logger.error(f"Failed to load track: {track_path}")
                self.next_track()
                continue

            # Reproducir en chunks
            chunk_size = self.audio_engine.buffer_size
            total_chunks = len(audio_data) // chunk_size

            for i in range(total_chunks):
                if not self.is_playing or self.stop_event.is_set():
                    break

                # Esperar si está en pausa
                while self.is_paused:
                    self.stop_event.wait(0.1)

                # Obtener chunk
                start = i * chunk_size
                end = start + chunk_size
                chunk = audio_data[start:end]

                # Enviar a audio engine
                self.audio_engine.queue_music(chunk)

                # Pequeña espera para no saturar la cola
                self.stop_event.wait(0.01)

            # Track terminado, pasar al siguiente
            if self.is_playing:
                self.next_track()

                # Si llegamos al final y no hay repeat, detener
                if self.current_index == 0 and not self.repeat:
                    self.stop()
```

### 6. src/tts_handler.py

```python
"""
Manejo de interrupciones TTS con ducking
"""
import os
import time
import threading
import logging
from pathlib import Path

logger = logging.getLogger(__name__)

class TTSHandler:
    def __init__(self, config, audio_engine, music_player):
        self.config = config
        self.audio_engine = audio_engine
        self.music_player = music_player

        # Configuración de ducking
        self.fade_out_duration = config.get('ducking.fade_out_duration', 2.0)
        self.fade_in_duration = config.get('ducking.fade_in_duration', 2.0)
        self.duck_level = config.get('ducking.duck_level', 0.2)
        self.pre_silence = config.get('ducking.pre_tts_silence', 0.5)
        self.post_silence = config.get('ducking.post_tts_silence', 0.5)

        # Jingles
        self.jingles_enabled = config.get('jingles.enabled', False)
        self.jingle_intro = config.get('jingles.intro', None)
        self.jingle_outro = config.get('jingles.outro', None)

        self.is_interrupting = False
        self.interrupt_lock = threading.Lock()

    def play_tts(self, tts_file_path):
        """
        Reproduce un TTS interrumpiendo la música con ducking

        Args:
            tts_file_path: Path al archivo TTS MP3
        """
        with self.interrupt_lock:
            if self.is_interrupting:
                logger.warning("Already playing TTS, ignoring new request")
                return

            self.is_interrupting = True

        try:
            logger.info(f"Starting TTS interruption: {tts_file_path}")

            # 1. Fade out música (ducking)
            logger.info(f"Ducking music to {self.duck_level * 100}%...")
            self.audio_engine.fade_duck(self.duck_level, self.fade_out_duration)

            # 2. Silencio pre-TTS
            if self.pre_silence > 0:
                logger.info(f"Pre-TTS silence: {self.pre_silence}s")
                time.sleep(self.pre_silence)

            # 3. Jingle intro (opcional)
            if self.jingles_enabled and self.jingle_intro:
                self._play_jingle(self.jingle_intro)

            # 4. Reproducir TTS
            logger.info("Playing TTS...")
            self._play_audio_file(tts_file_path)

            # 5. Jingle outro (opcional)
            if self.jingles_enabled and self.jingle_outro:
                self._play_jingle(self.jingle_outro)

            # 6. Silencio post-TTS
            if self.post_silence > 0:
                logger.info(f"Post-TTS silence: {self.post_silence}s")
                time.sleep(self.post_silence)

            # 7. Fade in música (restaurar)
            logger.info("Restoring music volume...")
            self.audio_engine.fade_duck(1.0, self.fade_in_duration)

            logger.info("TTS interruption complete")

        except Exception as e:
            logger.error(f"Error during TTS playback: {e}")
            # Restaurar música en caso de error
            self.audio_engine.set_duck_level(1.0)

        finally:
            with self.interrupt_lock:
                self.is_interrupting = False

    def _play_audio_file(self, file_path):
        """Reproduce un archivo de audio completo"""
        if not os.path.exists(file_path):
            logger.error(f"Audio file not found: {file_path}")
            return

        # Cargar audio
        audio_data = self.audio_engine.load_audio_file(file_path)

        if audio_data is None:
            logger.error(f"Failed to load audio: {file_path}")
            return

        # Reproducir en chunks
        chunk_size = self.audio_engine.buffer_size
        total_chunks = len(audio_data) // chunk_size

        for i in range(total_chunks):
            start = i * chunk_size
            end = start + chunk_size
            chunk = audio_data[start:end]

            # Enviar a cola de TTS
            self.audio_engine.queue_tts(chunk)

            # Esperar para mantener sincronización
            time.sleep(chunk_size / self.audio_engine.sample_rate)

        # Reproducir último chunk (residuo)
        remaining = len(audio_data) % chunk_size
        if remaining > 0:
            last_chunk = audio_data[-chunk_size:]
            self.audio_engine.queue_tts(last_chunk)
            time.sleep(chunk_size / self.audio_engine.sample_rate)

    def _play_jingle(self, jingle_path):
        """Reproduce un jingle"""
        if not os.path.exists(jingle_path):
            logger.warning(f"Jingle not found: {jingle_path}")
            return

        logger.info(f"Playing jingle: {jingle_path}")
        self._play_audio_file(jingle_path)
```

### 7. src/vps_client.py

```python
"""
Cliente para comunicación con VPS
Polling HTTP para verificar si hay nuevos TTS
"""
import requests
import time
import threading
import logging
from pathlib import Path

logger = logging.getLogger(__name__)

class VPSClient:
    def __init__(self, config, tts_handler):
        self.config = config
        self.tts_handler = tts_handler

        self.polling_url = config.get('vps.polling_url')
        self.download_url = config.get('vps.download_url')
        self.polling_interval = config.get('vps.polling_interval', 2)
        self.temp_folder = config.get('temp_folder', 'temp')

        self.is_running = False
        self.polling_thread = None
        self.stop_event = threading.Event()

        # Crear carpeta temp si no existe
        Path(self.temp_folder).mkdir(exist_ok=True)

    def start(self):
        """Inicia el polling al VPS"""
        if not self.polling_url:
            logger.warning("VPS polling URL not configured, skipping VPS client")
            return

        self.is_running = True
        self.stop_event.clear()

        self.polling_thread = threading.Thread(target=self._polling_loop, daemon=True)
        self.polling_thread.start()

        logger.info(f"VPS client started (polling {self.polling_url} every {self.polling_interval}s)")

    def stop(self):
        """Detiene el polling"""
        self.is_running = False
        self.stop_event.set()

        if self.polling_thread:
            self.polling_thread.join(timeout=5.0)

        logger.info("VPS client stopped")

    def _polling_loop(self):
        """Loop de polling al VPS"""
        while self.is_running and not self.stop_event.is_set():
            try:
                # Hacer request al VPS
                response = requests.get(
                    self.polling_url,
                    timeout=5,
                    params={'client_id': 'audio_player'}
                )

                if response.status_code == 200:
                    data = response.json()

                    # Si hay TTS pendiente
                    if data.get('has_pending'):
                        tts_info = data.get('tts')
                        logger.info(f"New TTS available: {tts_info}")

                        # Descargar y reproducir
                        self._handle_new_tts(tts_info)

            except requests.RequestException as e:
                logger.error(f"VPS polling error: {e}")

            except Exception as e:
                logger.error(f"Unexpected error in polling loop: {e}")

            # Esperar intervalo de polling
            self.stop_event.wait(self.polling_interval)

    def _handle_new_tts(self, tts_info):
        """
        Maneja un nuevo TTS del VPS

        Args:
            tts_info: Dict con información del TTS
                {
                    'file': 'tts_123.mp3',
                    'url': 'http://vps.com/audio/tts_123.mp3'
                }
        """
        try:
            # Obtener URL de descarga
            tts_url = tts_info.get('url')
            if not tts_url:
                # Construir URL si no viene
                tts_file = tts_info.get('file')
                tts_url = f"{self.download_url}/{tts_file}"

            # Descargar TTS
            logger.info(f"Downloading TTS from {tts_url}")
            response = requests.get(tts_url, timeout=30)

            if response.status_code != 200:
                logger.error(f"Failed to download TTS: HTTP {response.status_code}")
                return

            # Guardar en carpeta temp
            tts_filename = tts_info.get('file', f'tts_{int(time.time())}.mp3')
            tts_path = Path(self.temp_folder) / tts_filename

            with open(tts_path, 'wb') as f:
                f.write(response.content)

            logger.info(f"TTS downloaded to {tts_path}")

            # Reproducir TTS
            self.tts_handler.play_tts(str(tts_path))

            # Opcional: Eliminar archivo temp después de reproducir
            # tts_path.unlink()

        except Exception as e:
            logger.error(f"Error handling new TTS: {e}")
```

### 8. src/web_server.py

```python
"""
Flask web server para interfaz de control
"""
from flask import Flask, render_template, jsonify, request, send_from_directory
from flask_socketio import SocketIO, emit
from flask_cors import CORS
import logging
import threading
import os

logger = logging.getLogger(__name__)

class WebServer:
    def __init__(self, config, audio_engine, music_player, tts_handler):
        self.config = config
        self.audio_engine = audio_engine
        self.music_player = music_player
        self.tts_handler = tts_handler

        # Configuración Flask
        self.host = config.get('web_ui.host', '0.0.0.0')
        self.port = config.get('web_ui.port', 5000)

        # Crear app Flask
        self.app = Flask(__name__,
                        static_folder='static',
                        template_folder='static')
        CORS(self.app)

        self.app.config['SECRET_KEY'] = 'audio-player-secret-key'
        self.socketio = SocketIO(self.app, cors_allowed_origins="*")

        # Registrar rutas
        self._register_routes()
        self._register_socketio_events()

        self.server_thread = None

    def start(self):
        """Inicia el servidor web"""
        self.server_thread = threading.Thread(
            target=self._run_server,
            daemon=True
        )
        self.server_thread.start()
        logger.info(f"Web server started at http://{self.host}:{self.port}")

    def _run_server(self):
        """Corre el servidor Flask"""
        self.socketio.run(
            self.app,
            host=self.host,
            port=self.port,
            debug=False,
            use_reloader=False
        )

    def _register_routes(self):
        """Registra rutas HTTP"""

        @self.app.route('/')
        def index():
            """Página principal"""
            return send_from_directory('static', 'index.html')

        @self.app.route('/api/status')
        def status():
            """Estado actual del player"""
            return jsonify({
                'status': 'ok',
                'is_playing': self.music_player.is_playing,
                'is_paused': self.music_player.is_paused,
                'current_track': self.music_player.get_current_track(),
                'volumes': {
                    'music': self.audio_engine.music_volume,
                    'tts': self.audio_engine.tts_volume,
                    'master': self.audio_engine.master_volume
                },
                'is_interrupting': self.tts_handler.is_interrupting
            })

        @self.app.route('/api/play', methods=['POST'])
        def play():
            """Iniciar reproducción"""
            if not self.music_player.is_playing:
                self.music_player.start()
            elif self.music_player.is_paused:
                self.music_player.resume()

            return jsonify({'status': 'playing'})

        @self.app.route('/api/pause', methods=['POST'])
        def pause():
            """Pausar reproducción"""
            self.music_player.pause()
            return jsonify({'status': 'paused'})

        @self.app.route('/api/next', methods=['POST'])
        def next_track():
            """Siguiente track"""
            self.music_player.next_track()
            return jsonify({
                'status': 'ok',
                'current_track': self.music_player.get_current_track()
            })

        @self.app.route('/api/volume', methods=['POST'])
        def set_volume():
            """Establecer volumen"""
            data = request.json
            channel = data.get('channel')  # 'music', 'tts', 'master'
            volume = float(data.get('volume', 0.5))

            if channel == 'music':
                self.audio_engine.set_music_volume(volume)
            elif channel == 'tts':
                self.audio_engine.set_tts_volume(volume)
            elif channel == 'master':
                self.audio_engine.set_master_volume(volume)
            else:
                return jsonify({'error': 'Invalid channel'}), 400

            # Guardar en config
            self.config.set(f'volumes.{channel}', volume)
            self.config.save()

            return jsonify({'status': 'ok', 'volume': volume})

        @self.app.route('/api/config', methods=['GET'])
        def get_config():
            """Obtener configuración actual"""
            return jsonify({
                'ducking': {
                    'fade_out_duration': self.config.get('ducking.fade_out_duration'),
                    'fade_in_duration': self.config.get('ducking.fade_in_duration'),
                    'duck_level': self.config.get('ducking.duck_level')
                }
            })

        @self.app.route('/api/config', methods=['POST'])
        def update_config():
            """Actualizar configuración"""
            data = request.json

            if 'ducking' in data:
                ducking = data['ducking']
                if 'fade_out_duration' in ducking:
                    self.config.set('ducking.fade_out_duration', ducking['fade_out_duration'])
                    self.tts_handler.fade_out_duration = ducking['fade_out_duration']

                if 'fade_in_duration' in ducking:
                    self.config.set('ducking.fade_in_duration', ducking['fade_in_duration'])
                    self.tts_handler.fade_in_duration = ducking['fade_in_duration']

                if 'duck_level' in ducking:
                    self.config.set('ducking.duck_level', ducking['duck_level'])
                    self.tts_handler.duck_level = ducking['duck_level']

            self.config.save()
            return jsonify({'status': 'ok'})

    def _register_socketio_events(self):
        """Registra eventos WebSocket"""

        @self.socketio.on('connect')
        def handle_connect():
            logger.info("Client connected via WebSocket")
            emit('status', {
                'current_track': self.music_player.get_current_track(),
                'is_playing': self.music_player.is_playing
            })

        @self.socketio.on('disconnect')
        def handle_disconnect():
            logger.info("Client disconnected from WebSocket")

    def emit_status_update(self, data):
        """Emite actualización de estado a todos los clientes conectados"""
        self.socketio.emit('status_update', data)
```

### 9. src/main.py

```python
"""
Entry point principal del Audio Player
"""
import os
import sys
import signal
import logging
from pathlib import Path
from datetime import datetime

# Importar módulos del sistema
from config_manager import ConfigManager
from audio_engine import AudioEngine
from music_player import MusicPlayer
from tts_handler import TTSHandler
from vps_client import VPSClient
from web_server import WebServer

# Configuración de logging
def setup_logging(config):
    """Configura el sistema de logging"""
    log_folder = config.get('log_folder', 'logs')
    Path(log_folder).mkdir(exist_ok=True)

    log_file = Path(log_folder) / f"audio-player-{datetime.now().strftime('%Y-%m-%d')}.log"

    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        handlers=[
            logging.FileHandler(log_file),
            logging.StreamHandler(sys.stdout)
        ]
    )

class AudioPlayer:
    """Aplicación principal del Audio Player"""

    def __init__(self, config_path='config.json'):
        # Cargar configuración
        self.config = ConfigManager(config_path)

        # Setup logging
        setup_logging(self.config)
        self.logger = logging.getLogger(__name__)

        # Componentes del sistema
        self.audio_engine = None
        self.music_player = None
        self.tts_handler = None
        self.vps_client = None
        self.web_server = None

        # Flag de running
        self.is_running = False

    def start(self):
        """Inicia el sistema completo"""
        self.logger.info("=" * 60)
        self.logger.info("🎵 Audio Player Starting...")
        self.logger.info("=" * 60)

        try:
            # 1. Inicializar Audio Engine
            self.logger.info("Initializing Audio Engine...")
            self.audio_engine = AudioEngine(self.config)
            self.audio_engine.start()

            # 2. Inicializar Music Player
            self.logger.info("Initializing Music Player...")
            self.music_player = MusicPlayer(self.config, self.audio_engine)
            self.music_player.scan_music_folder()
            self.music_player.start()

            # 3. Inicializar TTS Handler
            self.logger.info("Initializing TTS Handler...")
            self.tts_handler = TTSHandler(self.config, self.audio_engine, self.music_player)

            # 4. Inicializar VPS Client
            self.logger.info("Initializing VPS Client...")
            self.vps_client = VPSClient(self.config, self.tts_handler)
            self.vps_client.start()

            # 5. Inicializar Web Server
            self.logger.info("Initializing Web Server...")
            self.web_server = WebServer(
                self.config,
                self.audio_engine,
                self.music_player,
                self.tts_handler
            )
            self.web_server.start()

            self.is_running = True

            # Mostrar información de inicio
            web_host = self.config.get('web_ui.host', '0.0.0.0')
            web_port = self.config.get('web_ui.port', 5000)

            self.logger.info("=" * 60)
            self.logger.info("✅ Audio Player Started Successfully!")
            self.logger.info("")
            self.logger.info(f"🌐 Web UI: http://{web_host}:{web_port}")
            self.logger.info(f"🎵 Playing: {self.music_player.get_current_track()}")
            self.logger.info(f"📁 Music folder: {self.config.get('music_folder')}")
            self.logger.info(f"📊 Tracks in playlist: {len(self.music_player.playlist)}")
            self.logger.info("")
            self.logger.info("Press Ctrl+C to stop")
            self.logger.info("=" * 60)

        except Exception as e:
            self.logger.error(f"Failed to start Audio Player: {e}", exc_info=True)
            self.stop()
            sys.exit(1)

    def stop(self):
        """Detiene el sistema completo"""
        if not self.is_running:
            return

        self.logger.info("=" * 60)
        self.logger.info("🛑 Stopping Audio Player...")
        self.logger.info("=" * 60)

        # Detener componentes en orden inverso
        if self.vps_client:
            self.logger.info("Stopping VPS Client...")
            self.vps_client.stop()

        if self.music_player:
            self.logger.info("Stopping Music Player...")
            self.music_player.stop()

        if self.audio_engine:
            self.logger.info("Stopping Audio Engine...")
            self.audio_engine.stop()

        self.is_running = False
        self.logger.info("✅ Audio Player stopped")

    def run(self):
        """Corre el player hasta que se detenga"""
        # Registrar signal handler para Ctrl+C
        def signal_handler(sig, frame):
            self.logger.info("\nReceived shutdown signal")
            self.stop()
            sys.exit(0)

        signal.signal(signal.SIGINT, signal_handler)
        signal.signal(signal.SIGTERM, signal_handler)

        # Iniciar sistema
        self.start()

        # Mantener el programa corriendo
        try:
            while self.is_running:
                signal.pause()
        except KeyboardInterrupt:
            self.stop()

def main():
    """Entry point principal"""
    # Cambiar al directorio del script
    os.chdir(Path(__file__).parent.parent)

    # Verificar que existe config.json
    if not os.path.exists('config.json'):
        print("❌ Error: config.json not found")
        print("Please copy config.json.example to config.json and configure it")
        sys.exit(1)

    # Crear y correr el player
    player = AudioPlayer()
    player.run()

if __name__ == '__main__':
    main()
```

---

## Interfaz Web (UI)

### src/static/index.html

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casa Costanera - Audio Player</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🎵 Casa Costanera Audio Player</h1>
            <div class="status-indicator">
                <span id="connection-status" class="status-dot"></span>
                <span id="connection-text">Connecting...</span>
            </div>
        </header>

        <main>
            <!-- Now Playing -->
            <section class="now-playing">
                <h2>Now Playing</h2>
                <div id="current-track" class="track-info">Loading...</div>
            </section>

            <!-- Volume Controls -->
            <section class="volume-controls">
                <h2>Volume Controls</h2>

                <div class="slider-container">
                    <label for="music-volume">
                        🎵 Music Volume
                        <span id="music-volume-value">70%</span>
                    </label>
                    <input type="range"
                           id="music-volume"
                           min="0"
                           max="100"
                           value="70"
                           class="slider">
                </div>

                <div class="slider-container">
                    <label for="tts-volume">
                        📢 TTS Volume
                        <span id="tts-volume-value">90%</span>
                    </label>
                    <input type="range"
                           id="tts-volume"
                           min="0"
                           max="100"
                           value="90"
                           class="slider">
                </div>

                <div class="slider-container">
                    <label for="master-volume">
                        🔊 Master Volume
                        <span id="master-volume-value">80%</span>
                    </label>
                    <input type="range"
                           id="master-volume"
                           min="0"
                           max="100"
                           value="80"
                           class="slider">
                </div>
            </section>

            <!-- Playback Controls -->
            <section class="playback-controls">
                <h2>Playback Controls</h2>
                <div class="button-group">
                    <button id="play-btn" class="control-btn">▶️ Play</button>
                    <button id="pause-btn" class="control-btn">⏸️ Pause</button>
                    <button id="next-btn" class="control-btn">⏭️ Next Song</button>
                </div>
            </section>

            <!-- Settings -->
            <section class="settings">
                <h2>⚙️ Ducking Settings</h2>

                <div class="setting-item">
                    <label for="fade-duration">Fade Duration (seconds)</label>
                    <select id="fade-duration" class="setting-select">
                        <option value="1.0">1.0s</option>
                        <option value="1.5">1.5s</option>
                        <option value="2.0" selected>2.0s</option>
                        <option value="2.5">2.5s</option>
                        <option value="3.0">3.0s</option>
                    </select>
                </div>

                <div class="setting-item">
                    <label for="duck-level">Duck Level (%)</label>
                    <select id="duck-level" class="setting-select">
                        <option value="0.1">10%</option>
                        <option value="0.2" selected>20%</option>
                        <option value="0.3">30%</option>
                        <option value="0.4">40%</option>
                        <option value="0.5">50%</option>
                    </select>
                </div>

                <button id="save-settings-btn" class="control-btn">💾 Save Settings</button>
            </section>

            <!-- Status -->
            <section class="status-section">
                <h2>Status</h2>
                <div id="status-info" class="status-info">
                    <p><strong>Player:</strong> <span id="player-status">Loading...</span></p>
                    <p><strong>Interrupting:</strong> <span id="interrupt-status">No</span></p>
                </div>
            </section>
        </main>

        <footer>
            <p>Casa Costanera Audio Player v1.0</p>
        </footer>
    </div>

    <script src="/socket.io/socket.io.js"></script>
    <script src="app.js"></script>
</body>
</html>
```

### src/static/style.css

```css
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
}

header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    text-align: center;
}

header h1 {
    font-size: 2em;
    margin-bottom: 10px;
}

.status-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #ccc;
    animation: pulse 2s infinite;
}

.status-dot.connected {
    background: #4ade80;
}

.status-dot.disconnected {
    background: #ef4444;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

main {
    padding: 30px;
}

section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #e5e7eb;
}

section:last-child {
    border-bottom: none;
}

h2 {
    color: #1f2937;
    margin-bottom: 20px;
    font-size: 1.3em;
}

.now-playing {
    text-align: center;
    padding: 20px;
    background: #f9fafb;
    border-radius: 10px;
}

.track-info {
    font-size: 1.5em;
    color: #667eea;
    font-weight: 600;
}

.slider-container {
    margin-bottom: 20px;
}

.slider-container label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: #374151;
    font-weight: 500;
}

.slider {
    width: 100%;
    height: 8px;
    border-radius: 5px;
    background: #d1d5db;
    outline: none;
    -webkit-appearance: none;
}

.slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #667eea;
    cursor: pointer;
    transition: all 0.2s;
}

.slider::-webkit-slider-thumb:hover {
    transform: scale(1.2);
    background: #764ba2;
}

.slider::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #667eea;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.slider::-moz-range-thumb:hover {
    transform: scale(1.2);
    background: #764ba2;
}

.button-group {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.control-btn {
    flex: 1;
    min-width: 120px;
    padding: 15px 25px;
    font-size: 1em;
    font-weight: 600;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.control-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.control-btn:active {
    transform: translateY(0);
}

.setting-item {
    margin-bottom: 15px;
}

.setting-item label {
    display: block;
    margin-bottom: 8px;
    color: #374151;
    font-weight: 500;
}

.setting-select {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1em;
    color: #1f2937;
    background: white;
    cursor: pointer;
    transition: border-color 0.2s;
}

.setting-select:focus {
    outline: none;
    border-color: #667eea;
}

.status-info {
    background: #f9fafb;
    padding: 20px;
    border-radius: 10px;
}

.status-info p {
    margin-bottom: 10px;
    color: #374151;
}

.status-info p:last-child {
    margin-bottom: 0;
}

footer {
    background: #f9fafb;
    padding: 20px;
    text-align: center;
    color: #6b7280;
}

@media (max-width: 600px) {
    .button-group {
        flex-direction: column;
    }

    .control-btn {
        width: 100%;
    }
}
```

### src/static/app.js

```javascript
// Conexión WebSocket
const socket = io();

// Referencias a elementos DOM
const connectionStatus = document.getElementById('connection-status');
const connectionText = document.getElementById('connection-text');
const currentTrack = document.getElementById('current-track');
const playerStatus = document.getElementById('player-status');
const interruptStatus = document.getElementById('interrupt-status');

// Sliders
const musicVolumeSlider = document.getElementById('music-volume');
const ttsVolumeSlider = document.getElementById('tts-volume');
const masterVolumeSlider = document.getElementById('master-volume');

const musicVolumeValue = document.getElementById('music-volume-value');
const ttsVolumeValue = document.getElementById('tts-volume-value');
const masterVolumeValue = document.getElementById('master-volume-value');

// Botones
const playBtn = document.getElementById('play-btn');
const pauseBtn = document.getElementById('pause-btn');
const nextBtn = document.getElementById('next-btn');
const saveSettingsBtn = document.getElementById('save-settings-btn');

// Settings
const fadeDurationSelect = document.getElementById('fade-duration');
const duckLevelSelect = document.getElementById('duck-level');

// Estado de conexión
socket.on('connect', () => {
    console.log('Connected to server');
    connectionStatus.classList.add('connected');
    connectionStatus.classList.remove('disconnected');
    connectionText.textContent = 'Connected';
    loadStatus();
    loadConfig();
});

socket.on('disconnect', () => {
    console.log('Disconnected from server');
    connectionStatus.classList.add('disconnected');
    connectionStatus.classList.remove('connected');
    connectionText.textContent = 'Disconnected';
});

// Updates en tiempo real
socket.on('status_update', (data) => {
    console.log('Status update:', data);
    updateUI(data);
});

// Cargar estado inicial
async function loadStatus() {
    try {
        const response = await fetch('/api/status');
        const data = await response.json();
        updateUI(data);
    } catch (error) {
        console.error('Error loading status:', error);
    }
}

// Cargar configuración
async function loadConfig() {
    try {
        const response = await fetch('/api/config');
        const data = await response.json();

        if (data.ducking) {
            fadeDurationSelect.value = data.ducking.fade_out_duration;
            duckLevelSelect.value = data.ducking.duck_level;
        }
    } catch (error) {
        console.error('Error loading config:', error);
    }
}

// Actualizar UI
function updateUI(data) {
    if (data.current_track) {
        currentTrack.textContent = data.current_track;
    }

    if (data.is_playing !== undefined) {
        playerStatus.textContent = data.is_paused ? 'Paused' :
                                   data.is_playing ? 'Playing' : 'Stopped';
    }

    if (data.is_interrupting !== undefined) {
        interruptStatus.textContent = data.is_interrupting ? 'Yes' : 'No';
        interruptStatus.style.color = data.is_interrupting ? '#ef4444' : '#10b981';
    }

    if (data.volumes) {
        musicVolumeSlider.value = Math.round(data.volumes.music * 100);
        ttsVolumeSlider.value = Math.round(data.volumes.tts * 100);
        masterVolumeSlider.value = Math.round(data.volumes.master * 100);

        updateVolumeDisplay();
    }
}

// Actualizar displays de volumen
function updateVolumeDisplay() {
    musicVolumeValue.textContent = musicVolumeSlider.value + '%';
    ttsVolumeValue.textContent = ttsVolumeSlider.value + '%';
    masterVolumeValue.textContent = masterVolumeSlider.value + '%';
}

// Event listeners para sliders
musicVolumeSlider.addEventListener('input', updateVolumeDisplay);
ttsVolumeSlider.addEventListener('input', updateVolumeDisplay);
masterVolumeSlider.addEventListener('input', updateVolumeDisplay);

musicVolumeSlider.addEventListener('change', () => {
    setVolume('music', musicVolumeSlider.value / 100);
});

ttsVolumeSlider.addEventListener('change', () => {
    setVolume('tts', ttsVolumeSlider.value / 100);
});

masterVolumeSlider.addEventListener('change', () => {
    setVolume('master', masterVolumeSlider.value / 100);
});

// Funciones de control
async function setVolume(channel, volume) {
    try {
        await fetch('/api/volume', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ channel, volume })
        });
        console.log(`Volume set: ${channel} = ${volume}`);
    } catch (error) {
        console.error('Error setting volume:', error);
    }
}

playBtn.addEventListener('click', async () => {
    try {
        await fetch('/api/play', { method: 'POST' });
        loadStatus();
    } catch (error) {
        console.error('Error playing:', error);
    }
});

pauseBtn.addEventListener('click', async () => {
    try {
        await fetch('/api/pause', { method: 'POST' });
        loadStatus();
    } catch (error) {
        console.error('Error pausing:', error);
    }
});

nextBtn.addEventListener('click', async () => {
    try {
        const response = await fetch('/api/next', { method: 'POST' });
        const data = await response.json();
        currentTrack.textContent = data.current_track;
    } catch (error) {
        console.error('Error skipping:', error);
    }
});

saveSettingsBtn.addEventListener('click', async () => {
    try {
        const fadeDuration = parseFloat(fadeDurationSelect.value);
        const duckLevel = parseFloat(duckLevelSelect.value);

        await fetch('/api/config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ducking: {
                    fade_out_duration: fadeDuration,
                    fade_in_duration: fadeDuration,
                    duck_level: duckLevel
                }
            })
        });

        alert('Settings saved successfully!');
    } catch (error) {
        console.error('Error saving settings:', error);
        alert('Error saving settings');
    }
});

// Refresh status cada 5 segundos
setInterval(loadStatus, 5000);
```

---

## Instalación

### Mac (macOS Monterey o superior)

```bash
# 1. Instalar Homebrew (si no lo tienes)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# 2. Instalar Python 3.9+
brew install python@3.9

# 3. Instalar ffmpeg (requerido por pydub)
brew install ffmpeg

# 4. Descargar/clonar audio-player
cd ~/Documents
# (copiar carpeta audio-player aquí)

# 5. Crear virtual environment
cd audio-player
python3 -m venv venv
source venv/bin/activate

# 6. Instalar dependencias
pip install --upgrade pip
pip install -r requirements.txt

# 7. Copiar música
cp ~/Music/*.mp3 music/

# 8. Editar configuración
nano config.json
# Cambiar: vps.polling_url a tu VPS
# Cambiar: vps.download_url a tu VPS

# 9. Ejecutar player
python src/main.py

# 10. Abrir navegador
# http://localhost:5000
```

### Windows 10/11

```bash
# 1. Descargar Python 3.9+ desde python.org
# https://www.python.org/downloads/
# ⚠️ IMPORTANTE: Marcar "Add Python to PATH" durante instalación

# 2. Descargar ffmpeg
# https://www.gyan.dev/ffmpeg/builds/
# Extraer y agregar a PATH

# 3. Abrir PowerShell o CMD
cd C:\audio-player

# 4. Crear virtual environment
python -m venv venv
venv\Scripts\activate

# 5. Instalar dependencias
pip install --upgrade pip
pip install -r requirements.txt

# 6. Copiar música
copy C:\Users\TuUsuario\Music\*.mp3 music\

# 7. Editar config.json
notepad config.json

# 8. Ejecutar player
python src\main.py

# 9. Abrir navegador
# http://localhost:5000
```

### Script de Instalación Automática (Mac)

Crear `install.sh`:

```bash
#!/bin/bash
set -e

echo "🎵 Installing Casa Costanera Audio Player..."

# Check Python
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 not found. Installing..."
    brew install python@3.9
fi

# Check ffmpeg
if ! command -v ffmpeg &> /dev/null; then
    echo "📦 Installing ffmpeg..."
    brew install ffmpeg
fi

# Create venv
echo "📦 Creating virtual environment..."
python3 -m venv venv
source venv/bin/activate

# Install dependencies
echo "📦 Installing Python dependencies..."
pip install --upgrade pip
pip install -r requirements.txt

# Create folders
mkdir -p music jingles temp logs

echo ""
echo "✅ Installation complete!"
echo ""
echo "Next steps:"
echo "1. Copy your MP3 files to the 'music/' folder"
echo "2. Edit config.json with your VPS URL"
echo "3. Run: source venv/bin/activate && python src/main.py"
echo "4. Open http://localhost:5000 in your browser"
```

Hacer ejecutable:
```bash
chmod +x install.sh
./install.sh
```

---

## Uso y Operación

### Iniciar el Player

```bash
# Activar virtual environment
source venv/bin/activate  # Mac/Linux
venv\Scripts\activate     # Windows

# Correr player
python src/main.py

# Salida esperada:
# ============================================================
# 🎵 Audio Player Starting...
# ============================================================
# Initializing Audio Engine...
# Available audio devices:
# ...
# Audio engine started
# Initializing Music Player...
# Found 50 tracks
# Music playback started
# Initializing VPS Client...
# VPS client started (polling http://vps.com/api/pending-tts.php every 2s)
# Initializing Web Server...
# Web server started at http://0.0.0.0:5000
# ============================================================
# ✅ Audio Player Started Successfully!
#
# 🌐 Web UI: http://0.0.0.0:5000
# 🎵 Playing: Cool.mp3
# 📁 Music folder: music
# 📊 Tracks in playlist: 50
#
# Press Ctrl+C to stop
# ============================================================
```

### Acceder a la Interfaz Web

1. **Desde la misma máquina:** `http://localhost:5000`
2. **Desde otra máquina en la red:**
   - Obtener IP del Mac Mini: `ifconfig | grep inet`
   - Abrir: `http://192.168.x.x:5000`

### Controles Disponibles

#### Sliders de Volumen
- **Music Volume:** Volumen de la música de fondo (0-100%)
- **TTS Volume:** Volumen de los anuncios TTS (0-100%)
- **Master Volume:** Volumen general del sistema (0-100%)

Los cambios se aplican instantáneamente y se guardan en `config.json`.

#### Botones de Reproducción
- **Play:** Inicia/reanuda la reproducción
- **Pause:** Pausa la música
- **Next Song:** Salta a la siguiente canción

#### Configuración de Ducking
- **Fade Duration:** Tiempo que tarda en bajar/subir el volumen (1-3s)
- **Duck Level:** Nivel al que baja la música durante TTS (10-50%)

### Correr como Servicio (24/7)

#### Mac - usar launchd

Crear `/Library/LaunchDaemons/com.casa.audioplayer.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.casa.audioplayer</string>
    <key>ProgramArguments</key>
    <array>
        <string>/Users/tu-usuario/audio-player/venv/bin/python</string>
        <string>/Users/tu-usuario/audio-player/src/main.py</string>
    </array>
    <key>WorkingDirectory</key>
    <string>/Users/tu-usuario/audio-player</string>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/Users/tu-usuario/audio-player/logs/launchd-stdout.log</string>
    <key>StandardErrorPath</key>
    <string>/Users/tu-usuario/audio-player/logs/launchd-stderr.log</string>
</dict>
</plist>
```

Cargar servicio:
```bash
sudo launchctl load /Library/LaunchDaemons/com.casa.audioplayer.plist
sudo launchctl start com.casa.audioplayer
```

#### Windows - usar NSSM

```bash
# 1. Descargar NSSM
# https://nssm.cc/download

# 2. Instalar servicio
nssm install AudioPlayer "C:\audio-player\venv\Scripts\python.exe" "C:\audio-player\src\main.py"

# 3. Configurar
nssm set AudioPlayer AppDirectory C:\audio-player
nssm set AudioPlayer Start SERVICE_AUTO_START

# 4. Iniciar
nssm start AudioPlayer
```

---

## Troubleshooting

### Problema: No se escucha audio

**Solución:**
```bash
# 1. Verificar dispositivos de audio disponibles
python -c "import sounddevice as sd; print(sd.query_devices())"

# 2. Si el dispositivo correcto no es el default, editarlo en config.json
{
  "audio": {
    "device": 2  // Usar el ID del dispositivo correcto
  }
}
```

### Problema: "No MP3 files found"

**Solución:**
```bash
# Verificar que haya archivos en la carpeta
ls -la music/

# Copiar música
cp ~/Music/*.mp3 music/

# O especificar otra carpeta en config.json
{
  "music_folder": "/Users/tu-usuario/MiMusica"
}
```

### Problema: VPS client no conecta

**Solución:**
```bash
# 1. Verificar URL en config.json
{
  "vps": {
    "polling_url": "http://tu-vps.com/api/pending-tts.php"
  }
}

# 2. Probar URL manualmente
curl http://tu-vps.com/api/pending-tts.php?client_id=test

# 3. Revisar logs
tail -f logs/audio-player-$(date +%Y-%m-%d).log | grep VPS
```

### Problema: Web UI no carga

**Solución:**
```bash
# 1. Verificar que el puerto no esté ocupado
lsof -i :5000  # Mac
netstat -ano | findstr :5000  # Windows

# 2. Cambiar puerto en config.json
{
  "web_ui": {
    "port": 8080
  }
}

# 3. Verificar firewall
# Mac: System Preferences > Security > Firewall
# Windows: Windows Defender Firewall > Allow an app
```

### Problema: Audio se corta o tiene glitches

**Solución:**
```bash
# Aumentar buffer_size en config.json
{
  "audio": {
    "buffer_size": 4096  // En lugar de 2048
  }
}
```

### Problema: Ducking no funciona suave

**Solución:**
```bash
# Ajustar duración de fade en la UI o config.json
{
  "ducking": {
    "fade_out_duration": 3.0,  // Más lento
    "fade_in_duration": 3.0
  }
}
```

### Logs Útiles

```bash
# Ver logs en tiempo real
tail -f logs/audio-player-$(date +%Y-%m-%d).log

# Buscar errores
grep ERROR logs/audio-player-*.log

# Ver solo eventos de TTS
grep TTS logs/audio-player-*.log
```

---

## Resumen de Comandos Rápidos

```bash
# Iniciar player
source venv/bin/activate && python src/main.py

# Ver logs
tail -f logs/audio-player-$(date +%Y-%m-%d).log

# Agregar más música
cp ~/Music/nuevas/*.mp3 music/

# Backup de configuración
cp config.json config.json.backup

# Ver dispositivos de audio
python -c "import sounddevice as sd; print(sd.query_devices())"

# Test de imports
python -c "import sounddevice, numpy, pydub, flask; print('OK')"
```

---

**¡Listo!** Con esta documentación tienes todo lo necesario para implementar el player de audio local. En el siguiente documento veremos cómo integrar el VPS.
