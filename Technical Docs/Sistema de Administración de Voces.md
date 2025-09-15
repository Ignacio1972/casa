Análisis Completo del Sistema de Administración de Voces

  He analizado en profundidad el sistema de administración de voces. Aquí
  está el flujo completo:

  🏗️ ARQUITECTURA DEL SISTEMA

  El sistema tiene 3 capas principales:

  1. Capa de Datos: voices-config.json
  2. Capa de API: APIs PHP que leen/escriben el JSON
  3. Capa de Presentación: Playground Admin + Dashboard

  📁 1. ALMACENAMIENTO CENTRAL

  Archivo: /src/api/data/voices-config.json

  {
      "voices": {
          "voice_key": {
              "id": "ElevenLabs_Voice_ID",
              "label": "Nombre Display",
              "gender": "M/F",
              "active": true/false,
              "category": "custom",
              "added_date": "timestamp",
              "is_default": true/false
          }
      },
      "settings": {
          "default_voice": "voice_key",
          "last_updated": "timestamp",
          "version": "2.0"
      }
  }

  🔄 2. FLUJO DE ADMINISTRACIÓN (Playground)

  Frontend: voice-admin.js

  - Clase: VoiceAdminManager
  - Endpoint: /playground/api/voice-admin.php
  - Acciones disponibles:
    - list_all: Listar todas las voces
    - add: Agregar nueva voz
    - toggle: Activar/desactivar voz
    - delete: Eliminar voz
    - set_default: Establecer voz por defecto

  Backend: voice-admin.php

  - Lee/escribe directamente en voices-config.json
  - Validaciones:
    - No permite duplicados
    - No permite eliminar voz por defecto
    - Actualiza timestamps automáticamente

  🎤 3. CONSUMO EN DASHBOARD

  Carga de Voces:

  1. Dashboard llama a VoiceService.loadVoices()
  2. VoiceService hace request a /api/generate.php con action: 'list_voices'
  3. generate.php lee voices-config.json y filtra solo voces activas
  4. Dashboard recibe las voces y llena el selector

  Flujo de Código:

  // Dashboard (index.js)
  async loadVoices() {
      const voicesData = await VoiceService.loadVoices();
      // Convierte objeto a array
      this.state.voices = Object.entries(voicesData).map(...)
      this.populateVoiceSelector();
  }

  // VoiceService (voice-service.js)
  static async loadVoices() {
      // Cache de 1 minuto
      const response = await fetch('/api/generate.php', {
          body: JSON.stringify({action: 'list_voices'})
      });
      return data.voices;
  }

  // generate.php
  if ($input["action"] === "list_voices") {
      $config = json_decode(file_get_contents($voicesFile), true);
      // Filtra solo voces activas
      $activeVoices = array_filter($config['voices'], fn($v) =>
  $v['active']);
      echo json_encode(['success' => true, 'voices' => $activeVoices]);
  }

  🔐 4. CARACTERÍSTICAS DE SEGURIDAD

  1. Validación de duplicados: No permite keys repetidos
  2. Protección de voz default: No se puede eliminar
  3. Sanitización: Los keys se generan desde labels (espacios → underscores)
  4. Timestamps automáticos: Registro de cambios

  🎯 5. PUNTOS CLAVE DEL SISTEMA

  1. Single Source of Truth: Todo está en voices-config.json
  2. Cache inteligente: VoiceService cachea por 1 minuto
  3. Separación de concerns:
    - Admin modifica TODO (activas e inactivas)
    - Dashboard solo ve voces ACTIVAS
  4. Hot reload: Cambios en admin se reflejan inmediatamente (cache de 1
  min)

  ⚙️ 6. FLUJO COMPLETO PASO A PASO

  1. ADMIN agrega voz en Playground
     ↓
  2. voice-admin.php escribe en voices-config.json
     ↓
  3. Dashboard hace request (cada minuto o al refrescar)
     ↓
  4. generate.php lee voices-config.json
     ↓
  5. Filtra solo voces activas
     ↓
  6. Dashboard muestra voces en selector
     ↓
  7. Usuario selecciona voz y genera audio
     ↓
  8. generate.php usa el voice_id de la voz seleccionada
     ↓
  9. Llamada a ElevenLabs API con ese voice_id

  🚀 7. CARACTERÍSTICAS IMPORTANTES

  - 13 voces configuradas actualmente
  - Voz por defecto: "veronica"
  - Hot-swap: Activar/desactivar voces sin reiniciar
  - Gender tracking: Para filtros futuros
  - Category system: Preparado para múltiples categorías

● El sistema está muy bien diseñado: el Playground modifica el archivo
  central voices-config.json, y el Dashboard consume esas voces filtrando
  solo las activas. Es un sistema robusto y eficiente con separación clara
  de responsabilidades.