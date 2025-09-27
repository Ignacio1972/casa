# Configuración de Ducking con Fade Suave
# Agregar DESPUÉS de: radio = azuracast.handle_jingle_mode(radio)
# y ANTES de: radio = normalize(...)

# ==============================================
# SISTEMA DE DUCKING CON FADE PROFESIONAL
# ==============================================

# Cola dedicada para mensajes TTS con ducking
tts_ducking = request.queue(id="tts_ducking_queue")

# Detector de actividad en TTS (detecta cuando hay audio)
tts_active = blank.skip(
  threshold=-40.,     # Umbral de detección (dB)
  max_blank=0.3,      # Máximo silencio antes de considerar inactivo
  min_noise=0.1,      # Duración mínima de ruido
  tts_ducking
)

# Variable para controlar el estado del ducking con suavizado
duck_state = ref(1.0)
duck_target = ref(1.0)
fade_speed = ref(0.0)

# Función que se ejecuta cada frame para suavizar el fade
def smooth_ducking() =
  current = !duck_state
  target = !duck_target
  
  # Calcular la diferencia
  diff = target -. current
  
  # Si hay diferencia significativa, hacer fade
  if abs_float(diff) > 0.01 then
    # Velocidad de fade: 4 segundos para cambio completo
    # Con 44100 Hz y procesamiento cada ~0.04s, necesitamos ~100 pasos
    # Para cambio de 1.0 a 0.2 (0.8 de diferencia) en 4 segundos
    step = diff *. 0.025  # Ajusta este valor para cambiar velocidad del fade
    
    # Aplicar el paso de fade
    new_val = current +. step
    
    # Limitar entre 0.2 y 1.0
    new_val = if new_val < 0.2 then 0.2 else new_val end
    new_val = if new_val > 1.0 then 1.0 else new_val end
    
    duck_state := new_val
  else
    # Si ya llegamos al target, mantener
    duck_state := target
  end
  
  !duck_state
end

# Thread que actualiza el estado del ducking
thread.run(every=0.04, {
  if source.is_ready(tts_active) then
    # TTS activo: bajar música al 20%
    duck_target := 0.2
  else
    # TTS inactivo: música al 100%
    duck_target := 1.0
  end
})

# Aplicar el ducking suavizado a la radio
radio_with_smooth_ducking = amplify(smooth_ducking, radio)

# El TTS con un poco de amplificación para que se escuche claro
# Reducido de 1.3 a 1.1 para mejor balance
tts_amplified = amplify(1.1, tts_active)

# Opcional: Agregar un pequeño fade in/out al TTS también
tts_with_fade = fade.in(duration=0.5, fade.out(duration=0.5, tts_amplified))

# Mezclar la radio (con ducking suave) y el TTS
radio = add(
  normalize=false,
  weights=[1.0, 1.0],
  [radio_with_smooth_ducking, tts_with_fade]
)

# Log para debugging
log("Ducking system with smooth fade (4s) initialized", level=3)

# ==============================================
# FIN SISTEMA DE DUCKING CON FADE
# ==============================================

# El resto de tu configuración continúa normal...