<?php
/**
 * Scheduler Cron - Casa Costanera
 * Ejecuta programaciones automáticas cada minuto
 *
 * Este archivo debe ser llamado por cron cada minuto:
 * * * * * /usr/bin/php /var/www/casa/src/api/scheduler-cron.php >> /var/www/casa/src/api/logs/scheduler-cron.log 2>&1
 */

// Configurar zona horaria
date_default_timezone_set('America/Santiago');

// Definir constantes necesarias
define('AZURACAST_BASE_URL', 'http://51.222.25.222');
define('AZURACAST_API_KEY', 'c3802cba5b5e61e8:fed31be9adb82ca57f1cf482d170851f');
define('AZURACAST_STATION_ID', 1);

// Incluir servicios necesarios
require_once __DIR__ . '/services/radio-service.php';

// Función de logging
function logScheduler($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [Scheduler] $message" . PHP_EOL;

    // Log a archivo específico
    $logFile = __DIR__ . '/logs/scheduler-cron.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    // También a consola para cron
    echo $logMessage;
}

// Función de logging compatible con radio-service.php
function logMessage($message) {
    logScheduler($message);
}

/**
 * Obtener conexión a BD
 */
function getDBConnection() {
    $dbPath = __DIR__ . '/../../database/casa.db';
    try {
        $db = new PDO("sqlite:$dbPath");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (Exception $e) {
        logScheduler("ERROR: No se pudo conectar a BD: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtener programaciones que deben ejecutarse ahora
 */
function getSchedulesToExecute() {
    $db = getDBConnection();
    $current_time = date('H:i');
    $current_day_num = intval(date('w')); // 0=Domingo, 1=Lunes, etc.
    $current_date = date('Y-m-d');

    logScheduler("Verificando schedules para: $current_date $current_time (día: $current_day_num)");

    $sql = "
        SELECT * FROM audio_schedule
        WHERE is_active = 1
        AND (start_date IS NULL OR start_date <= ?)
        AND (end_date IS NULL OR end_date >= ?)
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$current_date, $current_date]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logScheduler("Encontrados " . count($schedules) . " schedules activos");

    $to_execute = [];

    foreach ($schedules as $schedule) {
        $should_execute = false;

        // Decodificar notes para obtener tipo
        $notes = json_decode($schedule['notes'], true);
        $schedule_type = $notes['type'] ?? 'interval';

        logScheduler("  Evaluando schedule #{$schedule['id']} - Tipo: $schedule_type");

        if ($schedule_type === 'interval') {
            $interval_hours = intval($notes['interval_hours'] ?? 0);
            $interval_minutes = intval($notes['interval_minutes'] ?? 0);

            if ($interval_hours > 0 || $interval_minutes > 0) {
                // Verificar si hoy está en los días programados
                $schedule_days = json_decode($schedule['schedule_days'], true) ?? [];
                $day_matches = in_array($current_day_num, $schedule_days);

                if ($day_matches) {
                    // NUEVO: Verificar rango horario si está definido
                    $schedule_times = json_decode($schedule['schedule_time'], true);

                    // Si schedule_time es un array de 2 elementos, es un rango [inicio, fin]
                    if (is_array($schedule_times) && count($schedule_times) === 2) {
                        $start_time = $schedule_times[0];
                        $end_time = $schedule_times[1];

                        // Verificar si estamos dentro del rango horario
                        if ($current_time < $start_time || $current_time > $end_time) {
                            logScheduler("    Fuera de rango horario ($start_time - $end_time)");
                            continue; // Saltar este schedule
                        }

                        logScheduler("    Dentro de rango horario ($start_time - $end_time)");
                    }

                    $last_executed = getLastExecution($schedule['id']);

                    if (!$last_executed) {
                        logScheduler("    Nunca ejecutado, programando...");
                        $should_execute = true;
                    } else {
                        $last_time = strtotime($last_executed);
                        $interval_seconds = ($interval_hours * 3600) + ($interval_minutes * 60);
                        $elapsed = time() - $last_time;

                        if ($elapsed >= $interval_seconds) {
                            logScheduler("    Intervalo cumplido ({$elapsed}s >= {$interval_seconds}s)");
                            $should_execute = true;
                        } else {
                            $remaining = $interval_seconds - $elapsed;
                            logScheduler("    Faltan {$remaining}s para próxima ejecución");
                        }
                    }
                } else {
                    logScheduler("    Hoy no está programado (días: " . implode(',', $schedule_days) . ")");
                }
            }
        } elseif ($schedule_type === 'specific') {
            $schedule_days = json_decode($schedule['schedule_days'], true) ?? [];
            $schedule_times = json_decode($schedule['schedule_time'], true) ?? [];

            $day_matches = in_array($current_day_num, $schedule_days);

            if ($day_matches && in_array($current_time, $schedule_times)) {
                logScheduler("    Hora específica coincide");
                $should_execute = true;
            }
        } elseif ($schedule_type === 'once') {
            $schedule_times = json_decode($schedule['schedule_time'], true) ?? [];
            $last_executed = getLastExecution($schedule['id']);

            if (!$last_executed && in_array($current_time, $schedule_times)) {
                logScheduler("    Ejecución única programada");
                $should_execute = true;
            }
        }

        if ($should_execute) {
            $schedule['category'] = $schedule['category'] ?? 'sin_categoria';
            $to_execute[] = $schedule;
        }
    }

    logScheduler("Total a ejecutar: " . count($to_execute));
    return $to_execute;
}

/**
 * Obtener última ejecución de una programación
 */
function getLastExecution($schedule_id) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT MAX(executed_at) as last_executed
        FROM audio_schedule_log
        WHERE schedule_id = ?
    ");
    $stmt->execute([$schedule_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['last_executed'] ?? null;
}

/**
 * Registrar ejecución
 */
function logExecution($schedule_id, $status = 'success', $message = '') {
    $db = getDBConnection();

    $stmt = $db->prepare("
        INSERT INTO audio_schedule_log (schedule_id, status, message, executed_at)
        VALUES (?, ?, ?, datetime('now','localtime'))
    ");
    $stmt->execute([$schedule_id, $status, $message]);
}

/**
 * Ejecutar un schedule
 */
function executeSchedule($schedule) {
    logScheduler("Ejecutando schedule #{$schedule['id']}: {$schedule['title']}");
    logScheduler("  Archivo: {$schedule['filename']}");
    logScheduler("  Categoría: {$schedule['category']}");

    try {
        $filename = $schedule['filename'];

        // Ruta dentro del contenedor Docker de AzuraCast
        $dockerPath = "/var/azuracast/stations/mediaflow/media/Grabaciones/" . $filename;

        // Verificar si el archivo existe DENTRO del contenedor Docker
        $checkCmd = sprintf(
            'sudo docker exec azuracast test -f %s && echo "EXISTS" || echo "NOT_FOUND"',
            escapeshellarg($dockerPath)
        );
        $checkResult = trim(shell_exec($checkCmd));

        if ($checkResult !== 'EXISTS') {
            throw new Exception("Archivo no encontrado en AzuraCast: {$filename}");
        }

        logScheduler("  ✓ Archivo encontrado en AzuraCast");

        // Obtener duración del audio usando ffprobe DENTRO del contenedor
        $durationCmd = sprintf(
            'sudo docker exec azuracast ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($dockerPath)
        );
        $durationOutput = trim(shell_exec($durationCmd));

        if ($durationOutput && is_numeric($durationOutput)) {
            $duration = floatval($durationOutput);
            logScheduler("  Duración detectada: {$duration}s");
        } else {
            $duration = 15; // Default si no se puede obtener
            logScheduler("  Usando duración por defecto: {$duration}s");
        }

        // Interrumpir la radio con el archivo
        $success = interruptRadioWithSkip($filename, $duration, true);

        if ($success) {
            logScheduler("  ✅ Ejecución exitosa");
            logExecution($schedule['id'], 'success', "Ejecutado correctamente a las " . date('H:i:s'));
            return true;
        } else {
            throw new Exception("Fallo la interrupción de radio");
        }

    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        logScheduler("  ❌ Error: $errorMsg");
        logExecution($schedule['id'], 'error', $errorMsg);
        return false;
    }
}

// ==============================================
// EJECUCIÓN PRINCIPAL
// ==============================================

try {
    logScheduler("========================================");
    logScheduler("INICIANDO VERIFICACIÓN DE SCHEDULES");
    logScheduler("========================================");

    // Obtener schedules a ejecutar
    $schedules = getSchedulesToExecute();

    if (empty($schedules)) {
        logScheduler("No hay schedules para ejecutar en este momento");
    } else {
        logScheduler("Ejecutando " . count($schedules) . " schedule(s)...");

        foreach ($schedules as $schedule) {
            executeSchedule($schedule);
        }
    }

    logScheduler("Verificación completada");

} catch (Exception $e) {
    logScheduler("ERROR CRÍTICO: " . $e->getMessage());
    logScheduler($e->getTraceAsString());
}

logScheduler("========================================");
