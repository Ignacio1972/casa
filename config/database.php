<?php
/**
 * Configuración de Base de Datos
 * Casa Costanera - SQLite
 */

// Ruta de la base de datos
define('DB_PATH', DATABASE_PATH . '/casa.db');

// Configuración de SQLite
define('DB_TIMEOUT', 5000); // ms
define('DB_BUSY_TIMEOUT', 5000); // ms

// Opciones de conexión
$dbOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

/**
 * Obtener conexión a la base de datos
 */
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH, null, null, $GLOBALS['dbOptions']);
            $db->exec('PRAGMA journal_mode = WAL');
            $db->exec('PRAGMA busy_timeout = ' . DB_BUSY_TIMEOUT);
            $db->exec('PRAGMA synchronous = NORMAL');
        } catch (PDOException $e) {
            logMessage('ERROR', 'Database connection failed: ' . $e->getMessage());
            throw new Exception('Error de conexión a la base de datos');
        }
    }
    
    return $db;
}

/**
 * Inicializar base de datos si no existe
 */
function initDatabase() {
    if (!file_exists(DB_PATH)) {
        try {
            $db = getDB();
            
            // Crear tablas
            $sql = file_get_contents(DATABASE_PATH . '/migrations/001_initial_schema.sql');
            if ($sql) {
                $db->exec($sql);
                logMessage('INFO', 'Database initialized successfully');
            }
        } catch (Exception $e) {
            logMessage('ERROR', 'Database initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

/**
 * Ejecutar query con reintentos
 */
function executeQuery($sql, $params = [], $retries = 3) {
    $lastError = null;
    
    for ($i = 0; $i < $retries; $i++) {
        try {
            $db = getDB();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $lastError = $e;
            if (strpos($e->getMessage(), 'database is locked') !== false) {
                usleep(100000); // 100ms
                continue;
            }
            throw $e;
        }
    }
    
    throw $lastError;
}

/**
 * Transacción segura
 */
function transaction($callback) {
    $db = getDB();
    $db->beginTransaction();
    
    try {
        $result = $callback($db);
        $db->commit();
        return $result;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}