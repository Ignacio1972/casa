# Voice Experimentation Workflow

<workflow_overview>
Proceso iterativo de experimentación con voces y configuraciones hasta lograr el audio perfecto.
Este es el corazón del sistema donde los usuarios pasan más tiempo.
</workflow_overview>

<critical>
⚡ Los usuarios típicamente prueban 5-10 combinaciones antes de estar satisfechos.
La velocidad de iteración es crítica para la experiencia de usuario.
</critical>

## Flujo de Experimentación

<experimentation_flow>

### 1️⃣ Generación Inicial con AI
```javascript
// Usuario solicita sugerencias
POST /src/api/claude-service.php
{
  "action": "generateSuggestions",
  "category": "promociones",
  "context": "Black Friday en electrónica",
  "tone": "urgente"
}

// Recibe 3 sugerencias
// Selecciona una como base
```

### 2️⃣ Primera Generación de Audio
```javascript
// Genera con configuración default
POST /src/api/generate.php
{
  "text": "Gran venta Black Friday...",
  "voice": "Rachel",
  "voice_settings": {
    "stability": 0.5,      // Default
    "similarity_boost": 0.75,
    "style": 0.0
  }
}

// Escucha preview
// Decide: "Necesita más energía"
```

### 3️⃣ Ajuste de Voice Settings
```javascript
// Modifica sliders en UI
// Regenera con más expresividad
{
  "voice_settings": {
    "stability": 0.3,      // ⬇️ Más expresivo
    "similarity_boost": 0.85,
    "style": 0.6          // ⬆️ Más estilizado
  }
}

// Escucha nuevo preview
// Decide: "Mejor, pero probar otra voz"
```

### 4️⃣ Cambio de Voz
```javascript
// Cambia a voz más enérgica
{
  "voice": "Domi",  // Voz joven, enérgica
  "voice_settings": {
    "stability": 0.4,
    "similarity_boost": 0.8,
    "style": 0.5
  }
}

// Escucha preview
// Decide: "Perfecta para promoción"
```

### 5️⃣ Agregar Música (Jingle)
```javascript
// Convierte a jingle
POST /src/api/jingle-service.php
{
  "text": "Gran venta Black Friday...",
  "voice": "Domi",
  "music_file": "upbeat_commercial.mp3",
  "music_volume": 0.3,
  "voice_volume": 1.0,
  "fade_in": 2,
  "fade_out": 3,
  "music_duck": true
}

// Escucha con música
// Ajusta volúmenes si necesario
```

### 6️⃣ Guardar Versión Final
```javascript
// Satisfecho con resultado
{
  "save_message": true,
  "metadata": {
    "campaign": "black_friday_2025",
    "final_version": true,
    "iterations": 5
  }
}
```
</experimentation_flow>

## Voice Settings Guide

<voice_settings_explained>

### Stability (Estabilidad)
```
0.0 ←────────────────→ 1.0
Expresivo          Monótono

Para promociones urgentes: 0.2-0.4
Para información clara: 0.6-0.8
Para anuncios normales: 0.5
```

### Similarity Boost (Similitud)
```
0.0 ←────────────────→ 1.0
Variable           Original

Recomendado: 0.75-0.85
Más bajo = más variación permitida
Más alto = más fiel a voz original
```

### Style (Estilo)
```
0.0 ←────────────────→ 1.0
Natural           Estilizado

Para urgencia: 0.6-0.8
Para calma: 0.0-0.3
Normal: 0.4-0.5
```

### Speaker Boost
```
TRUE: Mejora claridad y presencia
FALSE: Más natural y suave
```
</voice_settings_explained>

## Available Voices

<voices>
### Voces Femeninas
- **Rachel**: Profesional, clara, confiable
- **Domi**: Joven, enérgica, dinámica
- **Bella**: Suave, amigable, cálida
- **Elli**: Madura, autoritativa
- **Charlotte**: Elegante, sofisticada

### Voces Masculinas
- **Antoni**: Profesional, versátil
- **Josh**: Joven, casual
- **Arnold**: Profunda, autoritativa
- **Adam**: Versátil, neutral
- **Sam**: Casual, amigable
</voices>

## Mejores Prácticas

<best_practices>
1. **Empieza con defaults**: No ajustes todo de inmediato
2. **Un cambio a la vez**: Modifica un parámetro y escucha
3. **Guarda versiones buenas**: Aunque no sean perfectas
4. **Prueba voces opuestas**: Si Rachel no funciona, prueba Antoni
5. **Música al final**: Primero perfecciona la voz
6. **Usa el contexto**: Una voz urgente necesita settings urgentes
7. **Preview siempre**: Nunca guardes sin escuchar
</best_practices>

## Quick Combinations

<quick_presets>
### 🚨 Urgente/Emergencia
```json
{
  "voice": "Antoni",
  "stability": 0.2,
  "similarity_boost": 0.95,
  "style": 0.8,
  "use_speaker_boost": true
}
```

### 📢 Promoción Enérgica
```json
{
  "voice": "Domi",
  "stability": 0.4,
  "similarity_boost": 0.8,
  "style": 0.6,
  "use_speaker_boost": true
}
```

### ℹ️ Información Clara
```json
{
  "voice": "Rachel",
  "stability": 0.7,
  "similarity_boost": 0.75,
  "style": 0.2,
  "use_speaker_boost": true
}
```

### 🎵 Jingle Amigable
```json
{
  "voice": "Bella",
  "stability": 0.5,
  "similarity_boost": 0.8,
  "style": 0.5,
  "use_speaker_boost": true,
  "music_volume": 0.3
}
```

### 🎄 Navideño Cálido
```json
{
  "voice": "Charlotte",
  "stability": 0.6,
  "similarity_boost": 0.85,
  "style": 0.4,
  "use_speaker_boost": true
}
```
</quick_presets>

## Common Patterns

<common_patterns>
### Pattern: Escalada de Energía
1. Empieza con Rachel (neutral)
2. Si necesitas más energía → Domi
3. Si necesitas urgencia → Antoni con stability baja
4. Si necesitas calidez → Bella

### Pattern: Refinamiento Progresivo
1. Genera con defaults
2. Ajusta stability primero (expresividad)
3. Luego style (caracterización)
4. Finalmente similarity_boost (fidelidad)

### Pattern: A/B Testing
1. Genera versión A con una voz
2. Genera versión B con settings opuestos
3. Compara y mezcla lo mejor de ambos
</common_patterns>

## Troubleshooting

<troubleshooting>
### "Suena muy robótico"
- Baja stability a 0.3-0.4
- Sube style a 0.5-0.6
- Prueba otra voz más expresiva

### "Muy inconsistente entre frases"
- Sube stability a 0.7-0.8
- Sube similarity_boost a 0.85-0.9

### "No suena urgente"
- Stability: 0.2
- Style: 0.7-0.8
- Cambiar a Antoni o Josh

### "Demasiado agresivo"
- Sube stability a 0.6
- Baja style a 0.3
- Cambiar a Rachel o Bella
</troubleshooting>

## API Examples

<api_examples>
### Quick Test Different Voices
```bash
# Test all female voices
for voice in Rachel Domi Bella Elli Charlotte; do
  curl -X POST "http://localhost:4000/src/api/generate.php" \
    -d "{\"text\":\"Prueba $voice\",\"voice\":\"$voice\"}"
done
```

### Test Stability Range
```bash
# Test stability from 0.2 to 0.8
for stability in 0.2 0.4 0.6 0.8; do
  curl -X POST "http://localhost:4000/src/api/generate.php" \
    -d "{
      \"text\":\"Estabilidad $stability\",
      \"voice\":\"Rachel\",
      \"voice_settings\":{\"stability\":$stability}
    }"
done
```
</api_examples>