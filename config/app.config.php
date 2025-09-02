<?php
/**
 * Configuración Principal - Casa Costanera
 * Sistema de Radio Automatizada
 * Puerto: 4000
 */

// Cargar variables de entorno si existe .env
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// Configuración del Sistema
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Casa Costanera');
define('APP_VERSION', $_ENV['APP_VERSION'] ?? '1.0.0');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', $_ENV['APP_DEBUG'] ?? false);
define('APP_PORT', $_ENV['APP_PORT'] ?? 4000);

// URLs
define('APP_URL', $_ENV['APP_URL'] ?? 'http://51.222.25.222:4000');
define('API_URL', APP_URL . '/api');
define('ASSETS_URL', APP_URL . '/assets');

// Rutas del Sistema
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('SRC_PATH', ROOT_PATH . '/src');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Configuración de Zona Horaria
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'America/Santiago');

// Configuración de Logs
define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'INFO');
define('LOG_PATH', STORAGE_PATH . '/logs');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

// Configuración de Caché
define('CACHE_ENABLED', $_ENV['CACHE_ENABLED'] ?? false);
define('CACHE_PATH', STORAGE_PATH . '/cache');
define('CACHE_TTL', $_ENV['CACHE_TTL'] ?? 3600);

// Configuración de Seguridad
define('CORS_ENABLED', true);
define('CORS_ORIGIN', $_ENV['CORS_ORIGIN'] ?? '*');
define('API_RATE_LIMIT', $_ENV['API_RATE_LIMIT'] ?? 100); // requests per minute

// Límites del Sistema
define('MAX_UPLOAD_SIZE', 12 * 1024 * 1024); // 12MB
define('MAX_TTS_LENGTH', 5000); // caracteres
define('MAX_FILE_AGE', 3600); // 1 hora para archivos temporales

// Cargar configuración del cliente específico
$clientConfig = CONFIG_PATH . '/clients/casa.config.php';
if (file_exists($clientConfig)) {
    require_once $clientConfig;
}

// Cargar configuración de base de datos
require_once CONFIG_PATH . '/database.php';

// Función helper para logs
function logMessage($level, $message, $context = []) {
    $logFile = LOG_PATH . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logEntry = "[$timestamp] [$level] $message $contextStr" . PHP_EOL;
    
    if (!file_exists(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Headers CORS globales
if (CORS_ENABLED && php_sapi_name() !== 'cli') {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Crear directorios necesarios si no existen
$requiredDirs = [LOG_PATH, CACHE_PATH, STORAGE_PATH . '/temp', STORAGE_PATH . '/backups'];
foreach ($requiredDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}