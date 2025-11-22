<?php
/**
 * Configuración Específica - MediaFlow
 * Cliente: MediaFlow
 * Personalización y parámetros específicos
 */

// Identificación del Cliente
define('CLIENT_NAME', 'MediaFlow');
define('CLIENT_CODE', 'CASA');
define('CLIENT_LOGO', '/assets/images/casa-logo.png');
define('CLIENT_PRIMARY_COLOR', '#1e40af'); // Azul profundo
define('CLIENT_SECONDARY_COLOR', '#3b82f6'); // Azul claro

// Configuración de AzuraCast
define('AZURACAST_BASE_URL', 'http://51.222.25.222');
define('AZURACAST_API_KEY', 'c3802cba5b5e61e8:fed31be9adb82ca57f1cf482d170851f');
define('AZURACAST_STATION_ID', 1);
define('AZURACAST_STATION_NAME', 'MediaFlow Radio');
define('PLAYLIST_ID_GRABACIONES', 3);
define('AZURACAST_MEDIA_PATH', '/var/azuracast/stations/test/media/Grabaciones/');

// Configuración de ElevenLabs TTS
define('ELEVENLABS_API_KEY', 'sk_f5d2f711a5cb2c117a2c6e2a00ab50bf34dbaec234bc61b2');
define('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1');
define('ELEVENLABS_VOICE_ID', 'bVMeCyTHy58xNoL34h3p'); // Jeremy Voice
define('ELEVENLABS_VOICE_NAME', 'Jeremy');
define('ELEVENLABS_MODEL_ID', 'eleven_multilingual_v2');

// Voces disponibles para el cliente
$VOICES_CONFIG = [
    [
        'id' => 'bVMeCyTHy58xNoL34h3p',
        'name' => 'Jeremy',
        'language' => 'Español',
        'gender' => 'Masculino',
        'age' => 'Adulto',
        'description' => 'Voz profesional masculina'
    ],
    [
        'id' => 'EXAVITQu4vr4xnSDxMaL',
        'name' => 'Bella',
        'language' => 'Español',
        'gender' => 'Femenino',
        'age' => 'Adulto',
        'description' => 'Voz femenina cálida'
    ],
    [
        'id' => 'ErXwobaYiN019PkySvjV',
        'name' => 'Antoni',
        'language' => 'Español',
        'gender' => 'Masculino',
        'age' => 'Joven',
        'description' => 'Voz juvenil masculina'
    ]
];

// Categorías personalizadas
$CATEGORIES_CONFIG = [
    ['name' => 'General', 'color' => '#6b7280', 'icon' => 'folder'],
    ['name' => 'Promociones', 'color' => '#ef4444', 'icon' => 'megaphone'],
    ['name' => 'Tiendas', 'color' => '#10b981', 'icon' => 'store'],
    ['name' => 'Eventos', 'color' => '#8b5cf6', 'icon' => 'calendar'],
    ['name' => 'Seguridad', 'color' => '#f59e0b', 'icon' => 'shield'],
    ['name' => 'Servicios', 'color' => '#3b82f6', 'icon' => 'info'],
    ['name' => 'Estacionamiento', 'color' => '#14b8a6', 'icon' => 'car'],
    ['name' => 'Música', 'color' => '#ec4899', 'icon' => 'music']
];

// Mensajes predefinidos del cliente
$DEFAULT_MESSAGES = [
    'bienvenida' => 'Bienvenidos a MediaFlow, su sistema de radio automatizada.',
    'cierre' => 'MediaFlow informa que en 30 minutos cerraremos nuestras puertas. Les agradecemos su visita.',
    'emergencia' => 'Atención, esto es un mensaje de emergencia. Por favor, diríjanse a la salida más cercana de manera ordenada.',
    'promocion' => 'No te pierdas las increíbles ofertas. Visita nuestras tiendas participantes.',
    'estacionamiento' => 'Recuerda que contamos con estacionamiento gratuito las primeras dos horas.'
];

// Horarios de operación
define('OPENING_TIME', '10:00');
define('CLOSING_TIME', '22:00');
define('SUNDAY_OPENING', '11:00');
define('SUNDAY_CLOSING', '21:00');

// Configuración de notificaciones
define('NOTIFICATION_EMAIL', 'admin@mediaflow.cl');
define('NOTIFICATION_ENABLED', true);

// Límites específicos del cliente
define('CLIENT_MAX_TTS_PER_DAY', 100);
define('CLIENT_MAX_SCHEDULED_MESSAGES', 50);
define('CLIENT_STORAGE_QUOTA', 1024 * 1024 * 1024); // 1GB

// Features habilitadas
define('FEATURE_TTS', true);
define('FEATURE_UPLOAD', true);
define('FEATURE_SCHEDULE', true);
define('FEATURE_CALENDAR', true);
define('FEATURE_ANALYTICS', false);
define('FEATURE_MULTI_STATION', false);

// Configuración de tema
define('THEME_NAME', 'casa');
define('THEME_DARK_MODE', true);
define('THEME_FONT_FAMILY', "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif");

// Metadatos del cliente
define('CLIENT_CREATED_AT', '2024-09-02');
define('CLIENT_TIMEZONE', 'America/Santiago');
define('CLIENT_LANGUAGE', 'es-CL');
define('CLIENT_CURRENCY', 'CLP');