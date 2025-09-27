# Configuración de Autoducking para TTS
# Agregar después de la línea: radio = azuracast.handle_jingle_mode(radio)

# Cola para mensajes TTS con ducking
tts_queue = request.queue(id="tts_ducking_queue", timeout=30.)

# Detectar cuando hay TTS activo
tts_with_detection = blank.skip(
  threshold=-40.,  # Umbral de detección de audio
  max_blank=0.5,   # Máximo silencio permitido
  tts_queue
)

# Función para ajustar el volumen de la música dinámicamente
def duck_level() =
  if source.is_ready(tts_with_detection) then
    0.2  # Música al 20% cuando hay TTS
  else
    1.0  # Música al 100% normal
  end
end

# Aplicar el ducking a la radio principal
radio_ducked = amplify(duck_level, radio)

# Mezclar la radio con ducking y el TTS
radio = add(
  normalize=false,
  [
    radio_ducked,           # Radio con volumen reducido
    amplify(1.2, tts_with_detection)  # TTS ligeramente amplificado
  ]
)

# El resto de tu configuración continúa igual...