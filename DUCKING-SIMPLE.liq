# ==============================================
# DUCKING SIMPLE SIN THREADS
# ==============================================

# Cola para mensajes TTS
tts_queue = request.queue(id="tts_ducking_queue")

# Limpiar silencios del TTS
tts_active = blank.skip(
  threshold=-40.,
  max_blank=0.3,
  track_sensitive=false,
  tts_queue
)

# Método 1: Usando operadores nativos (más simple y estable)
# El operador 'add' con ponderación hace ducking automático

# Crear función de ducking que se evalúa dinámicamente
def duck_music() =
  if source.is_ready(tts_active) then
    0.2  # Música al 20% cuando hay TTS
  else
    1.0  # Música al 100% normal
  end
end

# Aplicar ducking a la música
radio_with_ducking = amplify(duck_music, radio)

# TTS con volumen balanceado
tts_output = amplify(1.0, tts_active)

# Mezclar música con ducking + TTS
radio = add(
  normalize=false,
  [radio_with_ducking, tts_output]
)

log("Simple ducking system active (20% duck level)", level=3)