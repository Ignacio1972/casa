# Dashboard Redesign - Workflow del Usuario y Estructura Visual

## 📌 Resumen Ejecutivo
Rediseño completo del dashboard de Casa Costanera para crear un flujo de trabajo más intuitivo, inspirado en las interfaces de WhatsApp y ChatGPT Desktop. El sistema se divide en dos fases principales: generación de texto con IA y generación de audio.

---

## 🎯 Objetivo Principal
Crear un sistema de generación de mensajes para radio que:
1. Facilite la creación de múltiples sugerencias de texto con IA
2. Permita convertir esos textos en audio con diferentes voces y música
3. Mantenga un historial organizado de todas las sesiones y audios generados

---

## 📐 ESTRUCTURA VISUAL

### Layout Principal
- **Diseño de 2 columnas** en ambas fases:
  - **Columna izquierda**: 30% del ancho - Historial/Lista
  - **Columna derecha**: 70% del ancho - Área de trabajo activa
- **Inspiración**: WhatsApp Web y ChatGPT Desktop
- **Mockup disponible en**: `/public/dashboard-redesign-mockup.html`

### Navegación
- Sistema de scroll vertical entre Fase 1 y Fase 2
- Al seleccionar un texto en Fase 1 → Scroll automático a Fase 2
- Separador visual entre ambas fases

---

## 🔄 WORKFLOW DEL USUARIO

### FASE 1: GENERACIÓN DE TEXTO CON IA

#### Columna Izquierda - Historial de Sesiones
1. **Lista de sesiones**
   - Título automático: primeras palabras del input del usuario
   - Metadata: tiempo transcurrido + cantidad de sugerencias
   - Sesión activa destacada visualmente
   
2. **Funcionalidades**
   - **Búsqueda rápida**: Campo de búsqueda en la parte superior
   - **Paginación**: Para historiales largos
   - **Soft delete**: Botón trash en cada sesión (el admin puede recuperar desde playground)
   - **Click en sesión**: Carga todas las sugerencias de esa conversación en la columna derecha

#### Columna Derecha - Generador de Sugerencias

##### Flujo de Generación:

1. **Input Principal** (parte inferior)
   - **Textarea**: Para escribir el contexto del mensaje
   - **Controles inline** (lado izquierdo del textarea):
     - 🕐 **Icono reloj** → Dropdown duración (5, 10, 15, 20, 25 segundos)
     - 👤 **Icono persona** → Dropdown tono (Profesional, Entusiasta, Amigable, Urgente, Informativo)
   - **Botón Send** (lado derecho): Círculo gris con icono de avión
   - **Al enviar**: Aparece spinner "Generando 3 sugerencias..."

2. **Área de Sugerencias** (parte superior)
   - Se generan **3 cards simultáneamente**
   - **Cada card contiene**:
     - Número de sugerencia (Sugerencia 1, 2, 3)
     - Texto editable inline (contenteditable)
     - Badges mostrando duración y tono seleccionados
     - **Botón Regenerar** 🔄: Regenera solo esa sugerencia
     - **Botón Check Verde** ✓: Envía el texto a la Fase 2
   
3. **Generación Continua**
   - **Botón "Generar 3 más"**: Prominente, genera 3 sugerencias adicionales
   - Las nuevas sugerencias se apilan arriba de las existentes
   - Scroll para ver todas las generaciones de la sesión

#### Comportamiento de Sesiones

- **Nueva sesión**: Se crea cuando el usuario escribe en un input vacío
- **Sesión activa**: Se mantiene mientras use "Generar 3 más" o "Regenerar"
- **Persistencia**: Todo se guarda automáticamente en base de datos
- **Importancia del botón "Generar 3 más"**: Evita que el usuario cree múltiples sesiones innecesarias

---

### FASE 2: GENERACIÓN DE AUDIO

#### Transición entre Fases
- Usuario presiona botón check verde ✓ en una sugerencia
- **Scroll automático** hacia la Fase 2
- El texto seleccionado aparece en el editor de la Fase 2

#### Columna Izquierda - Historial de Audios

1. **Agrupación por TEXTO** (no por voz)
   - Mismo texto = mismo grupo
   - Muestra cantidad de versiones generadas
   - Expandible/colapsable con chevron

2. **Cada versión muestra**:
   - Voz utilizada
   - Música de fondo (o "Sin música")
   - Hora de generación
   - Versión activa destacada

3. **Interacción**:
   - Click en cualquier versión → La carga en el editor para modificar

#### Columna Derecha - Generador de Audio

##### Flujo de Generación de Audio:

1. **Editor de Texto** (parte superior)
   - Textarea con el texto seleccionado de Fase 1
   - Completamente editable antes de generar audio
   - Label: "Texto para generar audio:"

2. **Controles de Generación** (debajo del textarea)
   - **Dropdown Música**: Selección de música de fondo
     - Opciones: Sin música, Upbeat.mp3, Calm.mp3, etc.
   - **Dropdown Voz**: Selección de voz TTS
     - Opciones: Rachel, Antoni, Bella, Domi, Josh
   - **Botón "Generar Audio"**: Con icono de micrófono
   - **Al generar**: Spinner "Generando audio..."

3. **Player de Preview** (aparece tras generar)
   - **Reproducción automática** al completar generación
   - Visualización de forma de onda (waveform)
   - Controles estándar: Play/Pause, tiempo actual, barra de progreso
   
4. **Acciones del Audio** (3 botones debajo del player)
   - **"Enviar a Radio"** 📡
     - Interrumpe transmisión de AzuraCast
     - Toast de confirmación: "Audio enviado a la radio exitosamente"
   
   - **"Guardar en Biblioteca"** 💾
     - Guarda en módulo Campaign
     - Toast de confirmación: "Audio guardado en biblioteca"
   
   - **"Programar"** 📅
     - Abre modal existente de Campaign
     - Toast de confirmación: "Audio programado exitosamente"

---

## 🔄 Estados y Feedback Visual

### Estados de Carga
- **Generando sugerencias**: Spinner + "Generando 3 sugerencias..."
- **Generando audio**: Spinner + "Generando audio..."
- **Botón cancelar**: Disponible durante procesos largos

### Estados Vacíos
- **Sin sesiones**: Icono + "No hay sesiones anteriores"
- **Sin audios**: Mensaje indicando que no hay audios generados

### Notificaciones
- **Toast notifications**: Para confirmaciones de acciones
- **Posición**: Esquina inferior derecha
- **Duración**: 3 segundos con fade out

### Validaciones
- Input vacío no permite generar
- Límite de caracteres en textarea
- Indicador visual cuando se alcanza el límite

---

## 🎨 Elementos Visuales Clave

### Colores
- **Primary (verde)**: #00ff88 - Acciones principales y elementos activos
- **Secondary (gris)**: #6b7280 - Elementos secundarios
- **Background**: #0f0f1e - Fondo principal
- **Surface**: #1a1a2e - Cards y contenedores

### Iconografía
- Font Awesome para iconos consistentes
- Iconos custom: send.svg para botón de envío

### Animaciones
- Transiciones suaves de 0.3s
- Hover effects en botones y cards
- Scroll suave entre fases

---

## 📱 Responsive Design

### Desktop (>1024px)
- Layout de 2 columnas completo
- Todos los elementos visibles

### Tablet (768px - 1024px)
- Ajuste de proporciones: 35% / 65%
- Mantiene layout de 2 columnas

### Mobile (<768px)
- Layout de 1 columna
- Columnas se apilan verticalmente
- Menú colapsable para historiales

---

## 🚀 Flujo de Usuario Ejemplo

1. **Usuario abre el dashboard**
2. **Escribe en el input**: "Promoción 2x1 en restaurantes este fin de semana"
3. **Selecciona**: 15 segundos, tono Entusiasta
4. **Presiona Send**: Se generan 3 sugerencias
5. **Edita una sugerencia** directamente en la card
6. **Presiona check verde** ✓ en la sugerencia preferida
7. **Scroll automático** a Fase 2
8. **Ajusta el texto** si es necesario
9. **Selecciona**: Música "Upbeat.mp3", Voz "Rachel"
10. **Genera audio**: Se reproduce automáticamente
11. **Presiona "Enviar a Radio"**: Audio se transmite inmediatamente
12. **Toast de confirmación** aparece

---

## 📝 Notas Importantes

- **Soft Delete**: Los usuarios pueden "eliminar" sesiones, pero son recuperables por el admin
- **Auto-guardado**: Todas las interacciones se guardan automáticamente
- **Sin controles avanzados**: Se eliminó el toggle de opciones avanzadas por decisión del cliente
- **Contexto remoto**: La configuración del contexto de IA se hace desde el playground, no desde el dashboard
- **Preview automático**: Los audios se reproducen automáticamente al generarse para agilizar el workflow