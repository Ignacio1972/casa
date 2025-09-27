# Configuración de Ducking con Fade Suave (Versión Simplificada)
# Agregar DESPUÉS de: radio = azuracast.handle_jingle_mode(radio)
# y ANTES de: radio = normalize(...)

# ==============================================
# SISTEMA DE DUCKING CON FADE SUAVE
# ==============================================

# Cola para mensajes TTS
tts_queue = request.queue(id="tts_ducking_queue")

# Procesar el TTS con detección de actividad
tts_with_detection = blank.skip(
  threshold=-40., 
  max_blank=0.3,
  tts_queue
)

# Crear un smooth_add que hace el ducking automáticamente
# smooth_add baja el volumen de la primera fuente cuando la segunda está activa
# El parámetro 'delay' controla qué tan rápido hace el fade (en segundos)
# El parámetro 'p' controla a qué porcentaje baja el volumen (0.0 a 1.0)

# Opción 1: Usar smooth_add (más simple y nativo)
radio = smooth_add(
  delay=4.0,        # Fade de 4 segundos
  p=0.2,           # Bajar música al 20% cuando hay TTS
  normal=true,     # Normalizar niveles
  [radio, amplify(1.1, tts_with_detection)]
)

# Si smooth_add no está disponible, usar esta alternativa:
# Esta versión usa crossfade con parámetros personalizados

# def ducking_transition(a, b) =
#   # a = música, b = TTS
#   # Cuando b (TTS) está presente, hacer fade down de a
#   if source.is_ready(b) then
#     # Música baja con fade de 4 segundos
#     sequence([
#       fade.out(duration=4.0, a),
#       add(normalize=false, [amplify(0.2, a), amplify(1.1, b)])
#     ])
#   else
#     # Solo música al 100%
#     fade.in(duration=4.0, a)
#   end
# end
# 
# radio = cross(duration=4.0, ducking_transition, [radio, tts_with_detection])

# Log para debugging
log("Ducking with smooth fade (4s down to 20%) initialized", level=3)

# ==============================================
# FIN SISTEMA DE DUCKING
# ==============================================