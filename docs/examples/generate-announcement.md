# Generate Announcement Examples

<workflow_overview>
Ejemplos completos de generación de anuncios con diferentes configuraciones
y casos de uso comunes en Casa Costanera.
</workflow_overview>

## Basic Announcement Generation

<example_basic>
### Simple informative announcement
```bash
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "text": "Estimados visitantes, les informamos que el estacionamiento nivel 3 está temporalmente cerrado por mantenimiento. Disculpen las molestias.",
    "voice": "Rachel",
    "category": "informativos"
  }'
```

### Expected Response
```json
{
  "success": true,
  "data": {
    "audio_url": "/audio/tts_20250112_150000_Rachel.mp3",
    "filename": "tts_20250112_150000_Rachel.mp3",
    "duration": 7.5,
    "character_count": 142
  }
}
```
</example_basic>

## Promotional Announcement with AI

<example_ai_promotion>
### Step 1: Get AI suggestions
```bash
curl -X POST "http://localhost:4000/src/api/claude-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generateSuggestions",
    "client_id": "casa",
    "category": "promociones",
    "context": "Liquidación de temporada en tienda Ripley, hasta 70% descuento",
    "tone": "urgente",
    "max_suggestions": 3
  }'
```

### Step 2: Generate audio from suggestion
```bash
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "text": "¡Última semana de liquidación en Ripley! Hasta 70% de descuento en toda la tienda. Ropa, calzado y accesorios con precios increíbles. ¡Apresúrate, stock limitado!",
    "voice": "Domi",
    "category": "promociones",
    "voice_settings": {
      "stability": 0.4,
      "similarity_boost": 0.8,
      "style": 0.6
    },
    "save_message": true,
    "metadata": {
      "ai_generated": true,
      "campaign": "liquidacion_ripley",
      "expires": "2025-01-20"
    }
  }'
```
</example_ai_promotion>

## Template-Based Generation

<example_template>
### Using predefined templates
```bash
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "casa",
    "template_type": "oferta_simple",
    "template_data": {
      "tienda": "Falabella",
      "descuento": "40",
      "categoria": "electrodomésticos",
      "dias": "este fin de semana"
    },
    "voice": "Antoni",
    "category": "promociones"
  }'
```

### Generated text from template:
"¡Atención! Falabella tiene 40% de descuento en electrodomésticos este fin de semana. No dejes pasar esta increíble oportunidad."
</example_template>

## Jingle with Music

<example_jingle>
### Generate jingle with background music
```bash
curl -X POST "http://localhost:4000/src/api/jingle-service.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "client_id": "casa",
    "text": "Casa Costanera, donde encuentras todo lo que buscas. Ven y disfruta la mejor experiencia de compras.",
    "voice": "Bella",
    "music_file": "upbeat_commercial.mp3",
    "music_volume": 0.3,
    "voice_volume": 1.0,
    "fade_in": 2,
    "fade_out": 3,
    "category": "jingles"
  }'
```

### Response with combined audio
```json
{
  "success": true,
  "data": {
    "jingle_url": "/audio/jingle_20250112_160000_Bella.mp3",
    "duration": 12.5,
    "has_music": true,
    "music_file": "upbeat_commercial.mp3"
  }
}
```
</example_jingle>

## Batch Generation

<example_batch>
### Generate multiple announcements
```javascript
const announcements = [
  {
    text: "Buenos días, bienvenidos a Casa Costanera",
    category: "bienvenida",
    schedule: "09:00"
  },
  {
    text: "Les recordamos que el horario de atención es de 10 AM a 10 PM",
    category: "informativos",
    schedule: "10:00"
  },
  {
    text: "No olviden visitar nuestro patio de comidas en el segundo nivel",
    category: "informativos",
    schedule: "13:00"
  }
];

async function generateBatch() {
  const results = [];
  
  for (const announcement of announcements) {
    const response = await fetch('http://localhost:4000/src/api/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        client_id: 'casa',
        text: announcement.text,
        voice: 'Rachel',
        category: announcement.category,
        save_message: true
      })
    });
    
    const data = await response.json();
    
    // Schedule the announcement
    if (data.success) {
      await fetch('http://localhost:4000/src/api/audio-scheduler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create',
          client_id: 'casa',
          audio_file: data.data.audio_url,
          schedule_type: 'specific',
          schedule_data: {
            times: [announcement.schedule],
            days: [1, 2, 3, 4, 5, 6, 0],
            repeat: true
          }
        })
      });
    }
    
    results.push(data);
  }
  
  return results;
}
```
</example_batch>

## Advanced Voice Settings

<example_voice_settings>
### Different voice configurations for various moods

#### Urgent/Emergency
```json
{
  "voice": "Antoni",
  "voice_settings": {
    "stability": 0.3,
    "similarity_boost": 0.9,
    "style": 0.7,
    "use_speaker_boost": true
  }
}
```

#### Calm/Informative
```json
{
  "voice": "Rachel",
  "voice_settings": {
    "stability": 0.7,
    "similarity_boost": 0.6,
    "style": 0.2,
    "use_speaker_boost": true
  }
}
```

#### Friendly/Promotional
```json
{
  "voice": "Bella",
  "voice_settings": {
    "stability": 0.5,
    "similarity_boost": 0.75,
    "style": 0.5,
    "use_speaker_boost": true
  }
}
```
</example_voice_settings>

## Error Handling Examples

<error_handling>
### Retry with exponential backoff
```javascript
async function generateWithRetry(data, maxRetries = 3) {
  let lastError;
  
  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch('http://localhost:4000/src/api/generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      
      const result = await response.json();
      
      if (result.success) {
        return result;
      }
      
      // Handle rate limiting
      if (result.code === 'RATE_LIMIT') {
        const waitTime = Math.pow(2, i) * 1000; // Exponential backoff
        console.log(`Rate limited. Waiting ${waitTime}ms...`);
        await new Promise(resolve => setTimeout(resolve, waitTime));
        continue;
      }
      
      throw new Error(result.error);
      
    } catch (error) {
      lastError = error;
      console.error(`Attempt ${i + 1} failed:`, error.message);
    }
  }
  
  throw lastError;
}
```
</error_handling>

## Testing Commands

<testing_commands>
### Quick test suite
```bash
# Test basic generation
./test-generate.sh "Test announcement" Rachel informativos

# Test all voices
for voice in Rachel Domi Bella Antoni Josh; do
  echo "Testing voice: $voice"
  curl -s -X POST "http://localhost:4000/src/api/generate.php" \
    -H "Content-Type: application/json" \
    -d "{
      \"client_id\": \"casa\",
      \"text\": \"Prueba de voz con $voice\",
      \"voice\": \"$voice\",
      \"category\": \"test\"
    }" | jq '.success'
done

# Test quota check
curl -X POST "http://localhost:4000/src/api/generate.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "check_quota",
    "client_id": "casa"
  }'
```
</testing_commands>