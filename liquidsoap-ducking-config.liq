# Configuración de Ducking para TTS sobre música
# Agregar DESPUÉS de la línea: radio = azuracast.handle_jingle_mode(radio)
# y ANTES de: radio = normalize(...)

# ==============================================
# SISTEMA DE DUCKING PARA TTS
# ==============================================

# Cola dedicada para mensajes TTS con ducking
tts_ducking = request.queue(id="tts_ducking")

# Detector de actividad en TTS (detecta cuando hay audio)
tts_active = blank.skip(
  threshold=-35.,     # Umbral de detección (dB)
  max_blank=0.2,      # Máximo silencio antes de considerar inactivo
  min_noise=0.05,     # Duración mínima de ruido
  tts_ducking
)

# Función dinámica para el volumen de la música
# Cuando hay TTS activo, baja la música al 25%
def duck_music_level() =
  if source.is_ready(tts_active) then
    0.25  # Música al 25% cuando hay TTS
  else
    1.0   # Música al 100% normal
  end
end

# Aplicar el ducking dinámico a la radio
radio_with_ducking = amplify(duck_music_level, radio)

# Amplificar ligeramente el TTS para que se escuche claro
tts_amplified = amplify(1.3, tts_active)

# Mezclar la radio (con ducking) y el TTS
# normalize=false es importante para mantener los niveles relativos
radio = add(
  normalize=false,
  weights=[1.0, 1.0],
  [radio_with_ducking, tts_amplified]
)

# Log para debugging
log("Ducking system initialized for TTS overlay", level=3)

# ==============================================
# FIN SISTEMA DE DUCKING
# ==============================================

# El resto de tu configuración continúa normal...