# ==============================================
# SISTEMA DE DUCKING CON FADE PROFESIONAL
# Para AzuraCast/Liquidsoap
# ==============================================
# INSTRUCCIONES:
# Copiar este código en la configuración personalizada de AzuraCast
# DESPUÉS de: radio = azuracast.handle_jingle_mode(radio)
# ANTES de: radio = normalize(...)

# Cola para mensajes TTS
tts_queue = request.queue(id="tts_ducking_queue")

# Limpiar silencios del TTS
tts_clean = blank.skip(
  threshold=-40., 
  max_blank=0.3,
  track_sensitive=false,
  tts_queue
)

# Estado del ducking (compartido entre threads)
is_ducking = ref(false)
duck_level = ref(1.0)

# Función que detecta cuando hay TTS activo
def check_tts() =
  was_ducking = !is_ducking
  is_now = source.is_ready(tts_clean)
  
  # Si cambió el estado, loguear
  if is_now and not was_ducking then
    log("🔊 Ducking: Iniciando fade down (4s)")
  elsif not is_now and was_ducking then  
    log("🔊 Ducking: Iniciando fade up (4s)")
  end
  
  is_ducking := is_now
  true
end

# Ejecutar chequeo cada 100ms
thread.run(every=0.1, check_tts)

# Función que calcula el nivel de ducking con fade suave
def get_duck_level() =
  target = if !is_ducking then 0.2 else 1.0 end
  current = !duck_level
  
  # Calcular diferencia
  diff = target -. current
  
  if abs_float(diff) > 0.005 then
    # Fade en 4 segundos
    # Con updates cada 0.04s = 25 updates/seg
    # 4 segundos = 100 updates
    # Para ir de 1.0 a 0.2 (diff=0.8) necesitamos: 0.8/100 = 0.008 por step
    # Generalizando: diff / 100
    step = diff *. 0.01
    
    new_level = current +. step
    
    # Clamp entre 0.2 y 1.0
    new_level = max(0.2, min(1.0, new_level))
    duck_level := new_level
    new_level
  else
    duck_level := target
    target
  end
end

# Actualizar nivel de ducking suavemente
thread.run(every=0.04, { ignore(get_duck_level()) })

# Aplicar ducking con fade a la música
radio_ducked = amplify(get_duck_level, radio)

# TTS con nivel apropiado
# Ajusta este valor si el TTS suena muy alto o muy bajo
tts_balanced = amplify(1.0, tts_clean)

# Agregar fade in/out al TTS para transición más suave
tts_final = fade.in(duration=0.3, fade.out(duration=0.3, tts_balanced))

# Mezclar música ducked + TTS
radio = add(
  normalize=false,
  [radio_ducked, tts_final]
)

# Log de inicialización
log("✅ Ducking system initialized: 4s fade, 20% duck level", level=3)