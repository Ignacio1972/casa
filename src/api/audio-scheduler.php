<?php
/**
 * API para programación automática de reproducción de audios en la radio
 * Sistema de scheduling para Mall Barrio Independencia
 * @version 2.0 - Agregado soporte para categorías
 * @modified 2024-11-28 - Claude - Agregar campo category
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configurar zona horaria de Chile
date_default_timezone_set('America/Santiago');

// Configuración
$dbPath = __DIR__ . '/../../database/casa.db';

// Funciones principales
function getDBConnection() {
    global $dbPath;
    try {
        $db = new PDO("sqlite:$dbPath");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (Exception $e) {
        throw new Exception("Error conectando a BD: " . $e->getMessage());
    }
}

/**
 * Crear nueva programación
 * @modified Ahora incluye category y conversión de días
 */
function createSchedule($input) {
    $db = getDBConnection();
    
    $filename = $input['filename'] ?? '';
    $title = $input['title'] ?? '';
    $schedule_type = $input['schedule_type'] ?? 'interval';
    $interval_hours = $input['interval_hours'] ?? null;
    $interval_minutes = $input['interval_minutes'] ?? null;
    $schedule_days = $input['schedule_days'] ?? null;
    $schedule_times = $input['schedule_times'] ?? null;
    $start_date = $input['start_date'] ?? date('Y-m-d');
    $end_date = $input['end_date'] ?? null;
    $is_active = $input['is_active'] ?? true;
    $notes = $input['notes'] ?? '';
    
    // NUEVO: Obtener categoría del input o buscar en audio_metadata
    $category = $input['category'] ?? null;
    
    if (!$category && $filename) {
        // Intentar obtener categoría desde audio_metadata
        $stmt = $db->prepare("SELECT category FROM audio_metadata WHERE filename = ? LIMIT 1");
        $stmt->execute([$filename]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $category = $result['category'] ?? 'sin_categoria';
    } else if (!$category) {
        $category = 'sin_categoria';
    }
    
    // Calcular schedule_time basado en el tipo
    $schedule_time = null;
    if ($schedule_type === 'interval') {
        $hours = intval($interval_hours ?? 0);
        $minutes = intval($interval_minutes ?? 0);
        $schedule_time = sprintf("%02d:%02d", $hours, $minutes);
    } elseif ($schedule_type === 'specific' && $schedule_times) {
        $times = is_array($schedule_times) ? $schedule_times : json_decode($schedule_times, true);
        $schedule_time = is_array($times) ? json_encode($times) : $times;
    } elseif ($schedule_type === 'once' && $schedule_times) {
        $times = is_array($schedule_times) ? $schedule_times : [$schedule_times];
        $schedule_time = json_encode($times);
    }
    
    // Convertir días de nombres en inglés a números (0=Domingo, 1=Lunes, etc)
    if (is_array($schedule_days)) {
        $dayMapping = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6
        ];
        
        $convertedDays = [];
        foreach ($schedule_days as $day) {
            $dayLower = strtolower($day);
            if (isset($dayMapping[$dayLower])) {
                $convertedDays[] = $dayMapping[$dayLower];
            } else if (is_numeric($day)) {
                // Si ya es número, mantenerlo
                $convertedDays[] = intval($day);
            }
        }
        $schedule_days = json_encode($convertedDays);
        
        // Log para debugging - Escribir a archivo específico
        $logFile = __DIR__ . '/logs/schedule-debug.log';
        $logMessage = date('Y-m-d H:i:s') . " [AudioScheduler] Días recibidos: " . json_encode($input['schedule_days']) . " => Días convertidos: " . $schedule_days . " | Tipo: " . $schedule_type . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log("[AudioScheduler] Días convertidos: " . json_encode($input['schedule_days']) . " => " . $schedule_days);
    } else if ($schedule_days) {
        // Si ya viene como JSON string, intentar convertir
        $decodedDays = json_decode($schedule_days, true);
        if ($decodedDays) {
            $dayMapping = [
                'sunday' => 0,
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6
            ];
            
            $convertedDays = [];
            foreach ($decodedDays as $day) {
                $dayLower = strtolower($day);
                if (isset($dayMapping[$dayLower])) {
                    $convertedDays[] = $dayMapping[$dayLower];
                } else if (is_numeric($day)) {
                    $convertedDays[] = intval($day);
                }
            }
            $schedule_days = json_encode($convertedDays);
        }
    }
    
    // Preparar notes con información adicional
    $notesData = [
        'type' => $schedule_type,
        'interval_hours' => $interval_hours,
        'interval_minutes' => $interval_minutes,
        'notes' => $notes
    ];
    
    $stmt = $db->prepare("
        INSERT INTO audio_schedule (
            filename, title, schedule_time, schedule_days, 
            start_date, end_date, is_active, notes,
            created_at, updated_at, priority, category
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, 
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, ?
        )
    ");
    
    $stmt->execute([
        $filename,
        $title,
        $schedule_time,
        $schedule_days,
        $start_date,
        $end_date,
        $is_active ? 1 : 0,
        json_encode($notesData),
        1, // priority default
        $category // NUEVO: Guardar categoría
    ]);
    
    $scheduleId = $db->lastInsertId();
    
    // Log de creación
    error_log("[AudioScheduler] Schedule creado - ID: $scheduleId, Categoría: $category");
    
    return [
        'success' => true,
        'message' => 'Programación creada exitosamente',
        'schedule_id' => $scheduleId,
        'category' => $category
    ];
}

/**
 * Obtener todas las programaciones activas
 * @modified Ahora incluye category en la respuesta
 */
function getSchedules($input) {
    $db = getDBConnection();
    $active_only = $input['active_only'] ?? true;
    
    $sql = "SELECT * FROM audio_schedule";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $db->query($sql);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decodificar JSON fields y agregar category
    foreach ($schedules as &$schedule) {
        // Decodificar días - ya deberían estar como números, pero por compatibilidad
        if ($schedule['schedule_days']) {
            $decoded = json_decode($schedule['schedule_days'], true);
            if (is_array($decoded)) {
                // Verificar si necesitan conversión (si son strings)
                $needsConversion = false;
                foreach ($decoded as $day) {
                    if (is_string($day) && !is_numeric($day)) {
                        $needsConversion = true;
                        break;
                    }
                }
                
                if ($needsConversion) {
                    $dayMapping = [
                        'sunday' => 0, 'monday' => 1, 'tuesday' => 2,
                        'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6
                    ];
                    $convertedDays = [];
                    foreach ($decoded as $day) {
                        $dayLower = strtolower($day);
                        if (isset($dayMapping[$dayLower])) {
                            $convertedDays[] = $dayMapping[$dayLower];
                        } else if (is_numeric($day)) {
                            $convertedDays[] = intval($day);
                        }
                    }
                    $schedule['schedule_days'] = $convertedDays;
                } else {
                    $schedule['schedule_days'] = $decoded;
                }
            } else {
                $schedule['schedule_days'] = $decoded ?: $schedule['schedule_days'];
            }
        }
        
        // Decodificar notes
        if ($schedule['notes']) {
            $notes = json_decode($schedule['notes'], true);
            if (is_array($notes)) {
                $schedule['schedule_type'] = $notes['type'] ?? 'interval';
                $schedule['interval_hours'] = $notes['interval_hours'] ?? null;
                $schedule['interval_minutes'] = $notes['interval_minutes'] ?? null;
                $schedule['notes_text'] = $notes['notes'] ?? '';
            }
        }
        
        // Si schedule_time es JSON (múltiples horas), decodificar
        if ($schedule['schedule_time'] && $schedule['schedule_time'][0] === '[') {
            $schedule['schedule_times'] = json_decode($schedule['schedule_time'], true);
        }
        
        // Asegurar que category existe
        if (!isset($schedule['category']) || empty($schedule['category'])) {
            $schedule['category'] = 'sin_categoria';
        }
    }
    
    return [
        'success' => true,
        'schedules' => $schedules,
        'total' => count($schedules),
        'categories' => getCategoriesStats($db) // NUEVO: Estadísticas de categorías
    ];
}

/**
 * NUEVO: Obtener estadísticas de categorías
 */
function getCategoriesStats($db) {
    $stmt = $db->query("
        SELECT 
            category,
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as activos
        FROM audio_schedule
        GROUP BY category
    ");
    
    $stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['category']] = [
            'total' => $row['total'],
            'activos' => $row['activos']
        ];
    }
    
    return $stats;
}

/**
 * Actualizar programación
 * @modified Ahora puede actualizar category
 */
function updateSchedule($input) {
    $db = getDBConnection();
    $id = $input['id'] ?? 0;
    
    $updates = [];
    $params = [];
    
    // Campos actualizables
    if (isset($input['is_active'])) {
        $updates[] = 'is_active = ?';
        $params[] = $input['is_active'] ? 1 : 0;
    }
    
    if (isset($input['category'])) {
        $updates[] = 'category = ?';
        $params[] = $input['category'];
    }
    
    if (isset($input['title'])) {
        $updates[] = 'title = ?';
        $params[] = $input['title'];
    }
    
    if (empty($updates)) {
        return [
            'success' => false,
            'error' => 'No hay campos para actualizar'
        ];
    }
    
    $updates[] = 'updated_at = CURRENT_TIMESTAMP';
    $params[] = $id;
    
    $sql = "UPDATE audio_schedule SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return [
        'success' => true,
        'message' => 'Programación actualizada',
        'updated_fields' => array_keys($input)
    ];
}

/**
 * Eliminar programación
 */
function deleteSchedule($input) {
    $db = getDBConnection();
    $id = $input['id'] ?? 0;
    
    // Obtener info antes de eliminar para log
    $stmt = $db->prepare("SELECT title, category FROM audio_schedule WHERE id = ?");
    $stmt->execute([$id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("DELETE FROM audio_schedule WHERE id = ?");
    $stmt->execute([$id]);
    
    error_log("[AudioScheduler] Schedule eliminado - ID: $id, Título: {$info['title']}, Categoría: {$info['category']}");
    
    return [
        'success' => true,
        'message' => 'Programación eliminada'
    ];
}

/**
 * Obtener programaciones que deben ejecutarse ahora
 * @modified Incluye category para logging
 */
function getSchedulesToExecute() {
    $db = getDBConnection();
    $current_time = date('H:i');
    $current_day_name = strtolower(date('l')); // e.g., 'monday'
    $current_day_num = intval(date('w')); // 0=Sunday, 1=Monday, etc.
    $current_date = date('Y-m-d');
    
    $sql = "
        SELECT * FROM audio_schedule 
        WHERE is_active = 1 
        AND (start_date IS NULL OR start_date <= ?)
        AND (end_date IS NULL OR end_date >= ?)
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$current_date, $current_date]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $to_execute = [];
    
    foreach ($schedules as $schedule) {
        $should_execute = false;
        
        // Decodificar notes para obtener tipo
        $notes = json_decode($schedule['notes'], true);
        $schedule_type = $notes['type'] ?? 'interval';
        
        if ($schedule_type === 'interval') {
            $interval_hours = intval($notes['interval_hours'] ?? 0);
            $interval_minutes = intval($notes['interval_minutes'] ?? 0);
            
            if ($interval_hours > 0 || $interval_minutes > 0) {
                // Verificar si hoy está en los días programados
                $schedule_days = json_decode($schedule['schedule_days'], true) ?? [];
                $day_matches = false;
                
                // Los días pueden venir como números o strings
                foreach ($schedule_days as $day) {
                    if (is_numeric($day)) {
                        if (intval($day) === $current_day_num) {
                            $day_matches = true;
                            break;
                        }
                    } else {
                        // Por compatibilidad con datos antiguos
                        if (strtolower($day) === $current_day_name) {
                            $day_matches = true;
                            break;
                        }
                    }
                }
                
                if ($day_matches) {
                    $last_executed = getLastExecution($schedule['id']);
                    if (!$last_executed) {
                        $should_execute = true;
                    } else {
                        $last_time = strtotime($last_executed);
                        $interval_seconds = ($interval_hours * 3600) + ($interval_minutes * 60);
                        if ((time() - $last_time) >= $interval_seconds) {
                            $should_execute = true;
                        }
                    }
                }
            }
        } elseif ($schedule_type === 'specific') {
            $schedule_days = json_decode($schedule['schedule_days'], true) ?? [];
            $schedule_times = json_decode($schedule['schedule_time'], true) ?? [];
            
            // Verificar si hoy está en los días programados
            $day_matches = false;
            
            foreach ($schedule_days as $day) {
                if (is_numeric($day)) {
                    if (intval($day) === $current_day_num) {
                        $day_matches = true;
                        break;
                    }
                } else {
                    // Por compatibilidad con datos antiguos
                    if (strtolower($day) === $current_day_name) {
                        $day_matches = true;
                        break;
                    }
                }
            }
            
            if ($day_matches) {
                foreach ($schedule_times as $time) {
                    if ($time === $current_time) {
                        $should_execute = true;
                        break;
                    }
                }
            }
        } elseif ($schedule_type === 'once') {
            // Para programaciones de una sola vez
            $schedule_times = json_decode($schedule['schedule_time'], true) ?? [];
            
            // Verificar si ya se ejecutó
            $last_executed = getLastExecution($schedule['id']);
            
            // Solo ejecutar si no se ha ejecutado antes y es la hora correcta
            if (!$last_executed) {
                foreach ($schedule_times as $time) {
                    if ($time === $current_time) {
                        $should_execute = true;
                        break;
                    }
                }
            }
        }
        
        if ($should_execute) {
            // Agregar categoría al resultado
            $schedule['category'] = $schedule['category'] ?? 'sin_categoria';
            $to_execute[] = $schedule;
        }
    }
    
    return [
        'success' => true,
        'schedules' => $to_execute,
        'count' => count($to_execute)
    ];
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
 * Actualizar categoría de schedules por filename
 * NUEVO: Para sincronizar cuando se cambia categoría desde Campaign Library
 */
function updateCategoryByFilename($input) {
    $db = getDBConnection();
    $filename = $input['filename'] ?? '';
    $newCategory = $input['category'] ?? '';
    
    if (empty($filename) || empty($newCategory)) {
        return [
            'success' => false,
            'error' => 'Filename y categoría son requeridos'
        ];
    }
    
    $stmt = $db->prepare("
        UPDATE audio_schedule 
        SET category = ?, updated_at = CURRENT_TIMESTAMP
        WHERE filename = ?
    ");
    $stmt->execute([$newCategory, $filename]);
    
    $updatedRows = $stmt->rowCount();
    
    error_log("[AudioScheduler] Actualizada categoría: $filename → $newCategory ($updatedRows schedules)");
    
    return [
        'success' => true,
        'message' => "Categoría actualizada en $updatedRows schedule(s)",
        'updated_schedules' => $updatedRows
    ];
}

/**
 * Registrar ejecución
 */
function logExecution($schedule_id, $status = 'success', $message = '') {
    $db = getDBConnection();
    
    $stmt = $db->prepare("
        INSERT INTO audio_schedule_log (schedule_id, status, message, executed_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$schedule_id, $status, $message]);
}

// Procesar request
try {
    $rawInput = file_get_contents('php://input');
    error_log("[AudioScheduler] Raw input: " . $rawInput);
    
    $input = json_decode($rawInput, true) ?? [];
    $action = $input['action'] ?? $_GET['action'] ?? '';
    
    error_log("[AudioScheduler] Parsed action: " . $action);
    error_log("[AudioScheduler] Full input: " . json_encode($input));
    
    switch ($action) {
        case 'create':
            echo json_encode(createSchedule($input));
            break;
            
        case 'list':
            echo json_encode(getSchedules($input));
            break;
            
        case 'check_execute':
            echo json_encode(getSchedulesToExecute());
            break;
            
        case 'update':
            echo json_encode(updateSchedule($input));
            break;
            
        case 'delete':
            echo json_encode(deleteSchedule($input));
            break;
            
        case 'log_execution':
            logExecution(
                $input['schedule_id'], 
                $input['status'] ?? 'success', 
                $input['message'] ?? ''
            );
            echo json_encode(['success' => true]);
            break;
            
        case 'update_category_by_filename':
            echo json_encode(updateCategoryByFilename($input));
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}