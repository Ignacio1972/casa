# ==============================================
# DUCKING MINIMALISTA - VERSIÓN MÁS BÁSICA
# ==============================================

# Cola para TTS
tts_queue = request.queue(id="tts_ducking_queue")

# Detectar actividad (quitar silencios)
tts = blank.skip(threshold=-40., max_blank=0.3, tts_queue)

# Función simple de ducking
def duck() = if source.is_ready(tts) then 0.2 else 1.0 end

# Aplicar ducking y mezclar
radio = add([amplify(duck, radio), amplify(1.0, tts)])

log("Minimal ducking active", level=3)