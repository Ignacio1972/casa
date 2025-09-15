# Complete Generation Flow

<workflow_overview>
Flujo completo desde la idea hasta la emisión en radio.
Este documento conecta todos los componentes en el orden que un usuario real los utiliza.
</workflow_overview>

## 📊 Flujo Principal (90% de casos)

<main_flow>
```
💡 Idea/Necesidad
    ↓
🤖 Generar texto con AI
    ↓
🎙️ Experimentar con voces
    ↓
🎵 ¿Agregar música?
    ↓ Sí        ↓ No
🎼 Crear Jingle │
    ↓           ↓
💾 Guardar Audio
    ↓
📅 Programar emisión
    ↓
📆 Ver en calendario
    ↓
📻 Emitir automático
```
</main_flow>

## Paso a Paso Detallado

<step_by_step>

### Paso 1: Generar Texto con AI
```bash
# Quick: Obtener 3 sugerencias
curl -X POST "http://localhost:4000/src/api/claude-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generateSuggestions",
    "client_id": "casa",
    "category": "promociones",
    "context": "50% descuento en electrónica",
    "tone": "urgente",
    "max_suggestions": 3
  }'
```

**Respuesta típica:**
```json
{
  "suggestions": [
    {"text": "¡Último día! 50% de descuento en toda la electrónica. No dejes pasar esta oportunidad única."},
    {"text": "¡Atención! Solo hoy, mitad de precio en televisores, laptops y smartphones. ¡Corre antes que se agoten!"},
    {"text": "¡50% OFF en electrónica! Ofertas increíbles solo por hoy. Visítanos ya en Casa Costanera."}
  ]
}
```

### Paso 2: Generar Audio Base
```bash
# Con texto seleccionado de AI
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "text": "¡Último día! 50% de descuento en toda la electrónica.",
    "voice": "Rachel",
    "category": "promociones"
  }'
```

### Paso 3: Iterar con Voice Settings
```bash
# Ajustar para más energía y urgencia
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "text": "¡Último día! 50% de descuento en toda la electrónica.",
    "voice": "Domi",
    "voice_settings": {
      "stability": 0.3,
      "similarity_boost": 0.85,
      "style": 0.7,
      "use_speaker_boost": true
    },
    "category": "promociones"
  }'
```

### Paso 4: Convertir a Jingle (Opcional)
```bash
# Agregar música de fondo energética
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "client_id": "casa",
    "text": "¡Último día! 50% de descuento en toda la electrónica.",
    "voice": "Domi",
    "voice_settings": {
      "stability": 0.3,
      "similarity_boost": 0.85,
      "style": 0.7
    },
    "music_file": "upbeat_commercial.mp3",
    "music_volume": 0.25,
    "voice_volume": 1.0,
    "fade_in": 1,
    "fade_out": 2,
    "music_duck": true,
    "duck_level": 0.2
  }'
```

### Paso 5: Guardar Mensaje
```bash
# Guardar en biblioteca para uso futuro
curl -X POST "http://localhost:4000/src/api/saved-messages.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save",
    "client_id": "casa",
    "title": "Black Friday - Último Día",
    "text": "¡Último día! 50% de descuento en toda la electrónica.",
    "voice": "Domi",
    "category": "promociones",
    "audio_file": "jingle_20250112_160000_Domi.mp3",
    "tags": ["black_friday", "urgente", "electronica", "50_off"],
    "favorite": true,
    "metadata": {
      "ai_generated": true,
      "has_music": true,
      "campaign": "black_friday_2025"
    }
  }'
```

### Paso 6: Programar Emisión
```bash
# Programar cada 2 horas hoy de 10:00 a 22:00
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "client_id": "casa",
    "audio_file": "jingle_20250112_160000_Domi.mp3",
    "schedule_type": "interval",
    "schedule_data": {
      "interval": 120,
      "start_time": "10:00",
      "end_time": "22:00",
      "days": [5]
    },
    "metadata": {
      "campaign": "black_friday_2025",
      "priority": "high"
    }
  }'
```

### Paso 7: Verificar en Calendario
```bash
# Ver programación de hoy
curl "http://localhost:4000/src/api/calendar-api.php?action=today"
```

**Respuesta:**
```json
{
  "events": [
    {
      "time": "10:00",
      "title": "Black Friday - Último Día",
      "type": "scheduled",
      "category": "promociones"
    },
    {
      "time": "12:00",
      "title": "Black Friday - Último Día",
      "type": "scheduled",
      "category": "promociones"
    }
  ]
}
```

### Paso 8: Interrupción Manual (Si Urgente)
```bash
# Interrumpir radio AHORA con prioridad máxima
curl -X POST "http://localhost:4000/src/api/services/radio-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "interrupt",
    "filename": "jingle_20250112_160000_Domi.mp3",
    "priority": "urgent",
    "fade_out_current": true,
    "fade_duration": 2,
    "return_to_playlist": true
  }'
```
</step_by_step>

## Tiempos Típicos

<typical_times>
| Operación | Tiempo | Notas |
|-----------|--------|-------|
| Generación con AI | 2-3 segundos | Depende del modelo |
| Generación TTS | 3-5 segundos | Depende de longitud |
| Preview audio | Instantáneo | Ya generado |
| Ajuste de settings | Instantáneo | Solo UI |
| Regeneración | 3-5 segundos | Nueva llamada API |
| Crear jingle | 5-8 segundos | Procesamiento audio |
| Guardar mensaje | < 1 segundo | Base de datos |
| Programar | < 1 segundo | Base de datos |
| Ver calendario | < 1 segundo | Query simple |
| Interrumpir radio | 1-2 segundos | Docker + Liquidsoap |
| **Total workflow** | **2-5 minutos** | Con iteraciones |
</typical_times>

## Flujos Alternativos

<alternative_flows>

### Flujo Rápido (Sin AI, Sin Música)
```bash
# 1. Directo al audio
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -d '{"text":"Mensaje directo","voice":"Rachel","category":"informativos"}'

# 2. Programar inmediato
curl -X POST "http://localhost:4000/src/api/audio-scheduler.php" \
  -d '{"audio_file":"audio.mp3","schedule_type":"once","datetime":"NOW"}'
```

### Flujo Emergencia
```bash
# 1. Generar con máxima urgencia
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -d '{
    "text":"EVACUACIÓN INMEDIATA. Diríjanse a las salidas de emergencia.",
    "voice":"Antoni",
    "voice_settings":{"stability":0.2,"style":0.9},
    "category":"emergencias"
  }'

# 2. Interrumpir inmediatamente
curl -X POST "http://localhost:4000/src/api/services/radio-service.php" \
  -d '{"action":"interrupt","filename":"emergency.mp3","priority":"urgent"}'
```

### Flujo Batch (Múltiples mensajes)
```javascript
// Generar serie de mensajes para el día
const mensajes = [
  {hora: "09:00", texto: "Buenos días, bienvenidos"},
  {hora: "13:00", texto: "Visita nuestro patio de comidas"},
  {hora: "18:00", texto: "Últimas horas de ofertas"},
  {hora: "21:00", texto: "Gracias por visitarnos"}
];

for (const msg of mensajes) {
  // 1. Generar audio
  const audio = await generateTTS(msg.texto);
  
  // 2. Programar para hora específica
  await scheduleAudio(audio.filename, "specific", {
    times: [msg.hora],
    days: [1,2,3,4,5,6]
  });
}
```
</alternative_flows>

## Atajos para Power Users

<shortcuts>
### One-liner: Texto a Radio en 30 segundos
```bash
# Generar + Programar + Emitir (todo en uno)
TEXT="Oferta especial solo hoy" && \
AUDIO=$(curl -s -X POST http://localhost:4000/src/api/generate.php \
  -d "{\"text\":\"$TEXT\",\"voice\":\"Rachel\",\"category\":\"ofertas\"}" \
  | jq -r '.data.filename') && \
curl -X POST http://localhost:4000/src/api/audio-scheduler.php \
  -d "{\"audio_file\":\"$AUDIO\",\"schedule_type\":\"once\",\"datetime\":\"$(date -Iseconds)\"}" && \
curl -X POST http://localhost:4000/src/api/services/radio-service.php \
  -d "{\"action\":\"interrupt\",\"filename\":\"$AUDIO\",\"priority\":\"high\"}"
```

### Script: Campaña completa
```bash
#!/bin/bash
# generate-campaign.sh

CAMPAIGN="verano_2025"
VOICE="Bella"
CATEGORY="promociones"

# Textos de la campaña
TEXTS=(
  "Llegó el verano a Casa Costanera"
  "30% de descuento en ropa de verano"
  "2x1 en helados y bebidas frías"
  "Ven con tu traje de baño y obtén 10% extra"
)

# Generar todos los audios
for i in "${!TEXTS[@]}"; do
  echo "Generando mensaje $((i+1))..."
  
  curl -X POST http://localhost:4000/src/api/generate.php \
    -d "{
      \"text\":\"${TEXTS[$i]}\",
      \"voice\":\"$VOICE\",
      \"category\":\"$CATEGORY\",
      \"save_message\":true,
      \"metadata\":{\"campaign\":\"$CAMPAIGN\",\"index\":$i}
    }"
  
  sleep 2
done

echo "Campaña $CAMPAIGN generada con éxito"
```
</shortcuts>

## Mejores Prácticas

<best_practices>
1. **Siempre preview antes de guardar**: El audio puede sonar diferente a lo esperado
2. **Guarda múltiples versiones**: Útil para A/B testing
3. **Usa tags descriptivos**: Facilita encontrar mensajes después
4. **Programa con anticipación**: Evita interrupciones de último minuto
5. **Documenta campañas**: Usa el campo metadata para contexto
6. **Test en horas valle**: Prueba nuevos mensajes cuando hay menos audiencia
7. **Revisa el calendario**: Evita conflictos con otros mensajes programados
</best_practices>

## Troubleshooting

<troubleshooting>
### "El audio no se genera"
```bash
# Verificar API keys
cat .env | grep ELEVENLABS_API_KEY

# Verificar quota
curl http://localhost:4000/src/api/generate.php \
  -d '{"action":"check_quota"}'
```

### "No se puede programar"
```bash
# Verificar que el archivo existe
ls -la /audio/jingle_*.mp3

# Verificar base de datos
sqlite3 database/casa.db "SELECT * FROM audio_metadata WHERE filename='archivo.mp3';"
```

### "Radio no se interrumpe"
```bash
# Verificar Docker
docker ps | grep azuracast

# Verificar Liquidsoap
docker exec azuracast bash -c "echo 'request.queue' | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock"
```
</troubleshooting>