# Audio Processing & Enhancement Services

<endpoint_overview>
Servicios internos críticos para procesamiento, mejora y generación avanzada de audio.
Estos servicios trabajan en conjunto para producir audio de calidad profesional.
</endpoint_overview>

---

## Audio Processor Service

### Service: `/services/audio-processor.php`

<description>
Servicio de procesamiento post-generación que mejora la calidad del audio
y lo prepara para emisión en radio.
</description>

<features>
### Core Features
- **Silence Padding**: Agrega silencios configurables antes/después del audio
- **Format Preservation**: Mantiene características exactas del original (mono/stereo, bitrate)
- **Audio Analysis**: Analiza y preserva sample rate, channels, bitrate
- **Safe Processing**: Crea copias temporales para procesamiento no destructivo
</features>

<functions>

### addSilenceToAudio($inputFile)
Agrega 3 segundos de silencio antes y después del audio.

```php
// Ejemplo de uso
$originalFile = '/tmp/tts20250112143022.mp3';
$processedFile = addSilenceToAudio($originalFile);

// Resultado: archivo con silencios agregados
// Preserva: mono/stereo, bitrate, sample rate originales
```

#### Technical Details
```php
// Análisis del archivo original
$probe_cmd = 'ffprobe -v quiet -print_format json -show_streams file.mp3';
$audio_info = json_decode(shell_exec($probe_cmd), true);

// Preservación de características
$channels = $stream['channels'];      // 1 (mono) o 2 (stereo)
$sample_rate = $stream['sample_rate']; // 44100, 48000, etc.
$bit_rate = $stream['bit_rate'];      // 128k, 192k, 320k

// Generación de silencio idéntico
$channel_layout = ($channels == 1) ? 'mono' : 'stereo';
ffmpeg -f lavfi -i anullsrc=channel_layout=$channel_layout:sample_rate=$sample_rate
```

### normalizeAudioLevels($inputFile, $targetLUFS = -16)
Normaliza niveles de audio según estándar de broadcasting.

```php
$normalizedFile = normalizeAudioLevels($inputFile, -16);
// LUFS recomendados:
// -16: Radio/Streaming
// -14: Podcast
// -23: TV Broadcasting
```

### applyCompression($inputFile, $threshold = -20, $ratio = 4)
Aplica compresión dinámica para consistencia de volumen.

```php
$compressedFile = applyCompression($inputFile, -20, 4);
// Parámetros:
// threshold: -20dB (cuando empieza compresión)
// ratio: 4:1 (reducción de señales sobre threshold)
```
</functions>

<integration>
### Integration with TTS Pipeline
```javascript
// Pipeline completo de procesamiento
async function processAudioPipeline(text, voice) {
    // 1. Generar TTS
    const tts = await generateTTS(text, voice);
    
    // 2. Agregar silencios
    const withSilence = await fetch('/api/services/audio-processor.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add_silence',
            file: tts.filepath,
            before: 3,
            after: 3
        })
    });
    
    // 3. Normalizar audio
    const normalized = await fetch('/api/services/audio-processor.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'normalize',
            file: withSilence.filepath,
            target_lufs: -16
        })
    });
    
    return normalized;
}
```
</integration>

---

## TTS Service Enhanced

### Service: `/services/tts-service-enhanced.php`

<description>
Versión mejorada del servicio TTS con sistema dinámico de voces y
configuración avanzada respetando los settings del frontend.
</description>

<features>
### Enhanced Features
- **Dynamic Voice System**: Carga voces desde configuración JSON
- **Frontend Settings Respect**: Preserva voice_settings del usuario
- **Voice ID Resolution**: Mapea nombres amigables a IDs de ElevenLabs
- **Advanced Configuration**: Control granular de cada aspecto de la voz
- **Fallback Support**: Manejo inteligente de voces no configuradas
</features>

<voice_configuration>
### Voice Configuration System
```json
// /src/api/data/voices-config.json
{
  "voices": {
    "Rachel": {
      "id": "21m00Tcm4TlvDq8ikWAM",
      "label": "Rachel - Professional",
      "gender": "F",
      "defaults": {
        "stability": 0.75,
        "similarity_boost": 0.8,
        "style": 0.5
      }
    },
    "custom_ceo": {
      "id": "IKne3meq5aSn9XLyUdCD",
      "label": "CEO Voice",
      "gender": "M",
      "custom": true,
      "defaults": {
        "stability": 0.9,
        "similarity_boost": 0.95,
        "style": 0.2
      }
    }
  }
}
```
</voice_configuration>

<usage>
### Enhanced TTS Usage
```php
// Uso básico
$audio = generateEnhancedTTS("Mensaje de prueba", "Rachel");

// Con voice settings personalizados
$audio = generateEnhancedTTS(
    "Mensaje urgente",
    "Antoni",
    [
        'voice_settings' => [
            'stability' => 0.3,        // Más expresivo
            'similarity_boost' => 0.9,  // Más similar a la voz original
            'style' => 0.8,            // Más estilizado
            'use_speaker_boost' => true
        ],
        'model_id' => 'eleven_multilingual_v2'
    ]
);

// Con voz personalizada (ID directo)
$audio = generateEnhancedTTS(
    "Mensaje del CEO",
    "IKne3meq5aSn9XLyUdCD", // ID directo de ElevenLabs
    ['use_v3' => true]
);
```
</usage>

<voice_settings_ranges>
### Voice Settings Explained
```
stability (0.0 - 1.0):
  0.0 = Muy variable/expresivo
  0.5 = Balanceado
  1.0 = Muy estable/monótono
  
similarity_boost (0.0 - 1.0):
  0.0 = Permite más variación
  0.75 = Default recomendado
  1.0 = Máxima similitud con voz original
  
style (0.0 - 1.0):
  0.0 = Sin estilización
  0.5 = Estilo moderado
  1.0 = Máxima estilización (más lento)
  
use_speaker_boost (boolean):
  true = Mejora claridad y presencia
  false = Voz más natural/suave
```
</voice_settings_ranges>

---

## Announcement Generator Module

### Service: `/services/announcement-module/announcement-generator.php`

<description>
Sistema avanzado de generación de anuncios con templates, optimización
automática de voces y procesamiento inteligente de texto.
</description>

<features>
### Generator Features
- **Template System**: Integración con sistema de plantillas
- **Voice Optimization**: Ajuste automático según tipo de anuncio
- **Multi-language Support**: Soporte para múltiples idiomas
- **Context-aware Generation**: Genera según contexto y categoría
</features>

<usage>
### Generate Announcement
```php
// Desde texto directo
$result = AnnouncementGenerator::generate([
    'text' => 'Bienvenidos a Casa Costanera',
    'voice' => 'Rachel',
    'voice_settings' => [
        'stability' => 0.7,
        'similarity_boost' => 0.8
    ]
]);

// Desde template
$result = AnnouncementGenerator::generate([
    'template' => 'welcome_morning',
    'template_category' => 'greetings',
    'template_variables' => [
        'time' => 'mañana',
        'day' => 'lunes'
    ],
    'voice' => 'Antoni'
]);

// Resultado
[
    'audio' => $audioData,           // Audio binario
    'processed_text' => $finalText,  // Texto procesado
    'voice' => 'Antoni',
    'settings_used' => [...]         // Configuración aplicada
]
```
</usage>

<voice_optimization>
### Automatic Voice Optimization
```php
function optimizeVoiceSettings($baseSettings, $templateType) {
    $optimizations = [
        'emergency' => [
            'stability' => 0.3,      // Muy expresivo
            'similarity_boost' => 0.95,
            'style' => 0.7
        ],
        'informative' => [
            'stability' => 0.8,      // Estable y claro
            'similarity_boost' => 0.75,
            'style' => 0.2
        ],
        'promotional' => [
            'stability' => 0.5,      // Balanceado
            'similarity_boost' => 0.8,
            'style' => 0.6          // Más estilizado
        ]
    ];
    
    return array_merge($baseSettings, $optimizations[$templateType] ?? []);
}
```
</voice_optimization>

---

## Announcement Templates System

### Service: `/services/announcement-module/announcement-templates.php`

<description>
Sistema de plantillas predefinidas para generación rápida de anuncios comunes.
</description>

<template_categories>
### Available Categories
```php
$categories = [
    'greetings' => [    // Saludos y bienvenidas
        'welcome_morning',
        'welcome_afternoon', 
        'welcome_evening',
        'goodbye_closing'
    ],
    'promotions' => [   // Promociones y ofertas
        'sale_announcement',
        'discount_today',
        'last_chance',
        'new_arrival'
    ],
    'information' => [  // Informativos
        'schedule_change',
        'event_reminder',
        'service_update',
        'facility_notice'
    ],
    'emergency' => [    // Emergencias
        'evacuation',
        'security_alert',
        'weather_warning',
        'system_failure'
    ]
];
```
</template_categories>

<template_usage>
### Using Templates
```php
// Generar desde template
$result = AnnouncementTemplates::generateFromTemplate(
    'promotions',           // Categoría
    'sale_announcement',    // Template
    [                      // Variables
        'store' => 'Falabella',
        'discount' => '50%',
        'category' => 'ropa de verano',
        'duration' => 'este fin de semana'
    ]
);

// Resultado
[
    'text' => '¡Atención! Falabella tiene 50% de descuento en ropa de verano este fin de semana.',
    'template_name' => 'sale_announcement',
    'variables_used' => [...],
    'category' => 'promotions'
]
```
</template_usage>

<custom_templates>
### Creating Custom Templates
```php
// Registrar template personalizado
AnnouncementTemplates::registerTemplate(
    'custom_category',
    'my_template',
    '{{greeting}}, les informamos que {{message}}. {{closing}}',
    [
        'greeting' => ['required' => true, 'default' => 'Estimados visitantes'],
        'message' => ['required' => true],
        'closing' => ['required' => false, 'default' => 'Gracias por su atención']
    ]
);
```
</custom_templates>

---

## Complete Audio Generation Pipeline

<complete_pipeline>
### From Template to Radio: Full Pipeline
```javascript
async function completeAudioPipeline(config) {
    // 1. Generate from template or text
    const announcement = await fetch('/api/services/announcement-module/announcement-generator.php', {
        method: 'POST',
        body: JSON.stringify({
            template: config.template,
            template_category: config.category,
            template_variables: config.variables,
            voice: config.voice,
            voice_settings: {
                stability: 0.5,
                similarity_boost: 0.8,
                style: 0.4
            }
        })
    });
    
    // 2. Process audio (add silence, normalize)
    const processed = await fetch('/api/services/audio-processor.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'process',
            file: announcement.audio_file,
            operations: [
                { type: 'add_silence', before: 2, after: 3 },
                { type: 'normalize', target_lufs: -16 },
                { type: 'compress', threshold: -20, ratio: 4 }
            ]
        })
    });
    
    // 3. If it's a jingle, add music
    if (config.add_music) {
        const jingle = await fetch('/api/jingle-service.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'mix',
                voice_file: processed.file,
                music_file: config.music,
                music_volume: 0.3,
                duck_level: 0.2
            })
        });
        processed.file = jingle.file;
    }
    
    // 4. Upload to AzuraCast
    const uploaded = await fetch('/api/services/radio-service.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'upload',
            filepath: processed.file,
            category: config.category
        })
    });
    
    // 5. Schedule or interrupt
    if (config.urgent) {
        await fetch('/api/services/radio-service.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'interrupt',
                filename: uploaded.filename,
                priority: 'urgent'
            })
        });
    }
    
    return {
        success: true,
        audio_url: uploaded.url,
        duration: processed.duration,
        status: config.urgent ? 'playing' : 'scheduled'
    };
}
```
</complete_pipeline>

---

## Error Handling

<error_handling>
### Common Errors

#### Audio Processing Failed
```json
{
  "success": false,
  "error": "Error procesando audio",
  "code": "PROCESSING_ERROR",
  "details": {
    "operation": "add_silence",
    "reason": "ffmpeg not found or audio corrupted",
    "file": "/tmp/audio.mp3"
  }
}
```

#### Template Not Found
```json
{
  "success": false,
  "error": "Template no encontrado",
  "code": "TEMPLATE_NOT_FOUND",
  "details": {
    "category": "promotions",
    "template": "unknown_template",
    "available": ["sale_announcement", "discount_today"]
  }
}
```

#### Voice Configuration Error
```json
{
  "success": false,
  "error": "Configuración de voz inválida",
  "code": "VOICE_CONFIG_ERROR",
  "details": {
    "voice": "InvalidVoice",
    "reason": "Voice not found in config or ElevenLabs",
    "suggestion": "Use a valid voice name or ID"
  }
}
```
</error_handling>

---

## Configuration Files

<configuration_files>
### Required Configuration Files

#### /src/api/data/voices-config.json
```json
{
  "voices": {
    "voice_name": {
      "id": "elevenlabs_voice_id",
      "label": "Display Name",
      "gender": "M/F",
      "defaults": {
        "stability": 0.75,
        "similarity_boost": 0.8,
        "style": 0.5
      }
    }
  },
  "models": {
    "default": "eleven_multilingual_v2",
    "turbo": "eleven_turbo_v2"
  }
}
```

#### /src/api/data/templates-config.json
```json
{
  "categories": {
    "category_name": {
      "templates": {
        "template_id": {
          "text": "Template with {{variable}}",
          "variables": ["variable"],
          "voice_preset": "informative"
        }
      }
    }
  }
}
```
</configuration_files>