<?php
/**
* API para gestionar mensajes guardados (favoritos)
* Integra archivos de audio marcados como guardados con mensajes de texto
*/

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
   http_response_code(200);
   exit();
}

// Configuración
$dbPath = __DIR__ . '/../../database/casa.db';

/**
* Obtener todos los mensajes guardados (audio + texto)
*/
function getSavedMessages() {
   global $dbPath;
   
   try {
       $db = new PDO("sqlite:$dbPath");
       $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       
       $messages = [];
       
       // 1. Obtener archivos de audio guardados desde BD
       $stmt = $db->query("
           SELECT 
               filename,
               display_name,
               description,
               category,
               saved_at,
               play_count,
               radio_sent_count,
               tags,
               notes
           FROM audio_metadata 
           WHERE is_saved = 1 AND is_active = 1
           ORDER BY saved_at DESC
       ");
       
       $audioFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
       
       foreach ($audioFiles as $file) {
           $messages[] = [
               'id' => 'audio_' . str_replace('.mp3', '', $file['filename']),
               'type' => 'audio',
               'title' => $file['display_name'] ?? $file['filename'],
               'content' => $file['description'] ?? 'Archivo de audio',
               'description' => $file['description'] ?? '',
               'category' => $file['category'] ?? 'sin_categoria',
               'filename' => $file['filename'],
               'createdAt' => $file['saved_at'],
               'playCount' => $file['play_count'],
               'radioCount' => $file['radio_sent_count'],
               'tags' => $file['tags'],
               'notes' => $file['notes']
           ];
       }
       
       // 2. Obtener mensajes de texto guardados desde localStorage
       // (Esto lo maneja el frontend, aquí solo devolvemos los de audio)
       
       return [
           'success' => true,
           'messages' => $messages,
           'total' => count($messages),
           'categories' => getCategories($db)
       ];
       
   } catch (Exception $e) {
       return [
           'success' => false,
           'error' => $e->getMessage()
       ];
   }
}

/**
* Obtener categorías disponibles con conteo
*/
function getCategories($db) {
   $stmt = $db->query("
       SELECT 
           category,
           COUNT(*) as count
       FROM audio_metadata 
       WHERE is_saved = 1 AND is_active = 1
       GROUP BY category
   ");
   
   $categories = [];
   $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
   foreach ($results as $row) {
       $cat = $row['category'] ?? 'sin_categoria';
       $categories[$cat] = $row['count'];
   }
   
   return $categories;
}

/**
* Actualizar categoría de un mensaje
*/
function updateCategory($data) {
   global $dbPath;
   
   $id = $data['id'] ?? '';
   $category = $data['category'] ?? 'sin_categoria';
   
   // Si es un archivo de audio
   if (strpos($id, 'audio_') === 0) {
       $filename = str_replace('audio_', '', $id) . '.mp3';
       
       try {
           $db = new PDO("sqlite:$dbPath");
           $stmt = $db->prepare("UPDATE audio_metadata SET category = ?, updated_at = CURRENT_TIMESTAMP WHERE filename = ?");
           $stmt->execute([$category, $filename]);
           
           return ['success' => true, 'message' => 'Categoría actualizada'];
       } catch (Exception $e) {
           return ['success' => false, 'error' => $e->getMessage()];
       }
   }
   
   return ['success' => false, 'error' => 'Tipo de mensaje no soportado'];
}

/**
* Actualizar nombre de display de un mensaje de audio
*/
function updateDisplayName($data) {
   global $dbPath;
   
   $id = $data['id'] ?? '';
   $displayName = $data['display_name'] ?? '';
   
   if (empty($displayName)) {
       return ['success' => false, 'error' => 'Nombre requerido'];
   }
   
   // Si es un archivo de audio
   if (strpos($id, 'audio_') === 0) {
       $filename = str_replace('audio_', '', $id) . '.mp3';
       
       try {
           $db = new PDO("sqlite:$dbPath");
           $stmt = $db->prepare("UPDATE audio_metadata SET display_name = ?, updated_at = CURRENT_TIMESTAMP WHERE filename = ?");
           $stmt->execute([$displayName, $filename]);
           
           if ($stmt->rowCount() > 0) {
               return ['success' => true, 'message' => 'Nombre actualizado'];
           } else {
               return ['success' => false, 'error' => 'Audio no encontrado'];
           }
       } catch (Exception $e) {
           return ['success' => false, 'error' => $e->getMessage()];
       }
   }
   
   return ['success' => false, 'error' => 'Tipo de mensaje no soportado'];
}

/**
* Marcar un mensaje como guardado (favorito) CON CATEGORÍA
*/
function markAsSaved($data) {
   global $dbPath;
   
   $filename = $data['filename'] ?? '';
   $id = $data['id'] ?? '';
   $category = $data['category'] ?? 'sin_categoria';
   $title = $data['title'] ?? '';
   
   // Validar categoría
   $valid_categories = ['ofertas', 'eventos', 'informacion', 'servicios', 'horarios', 'emergencias', 'sin_categoria'];
   if (!in_array($category, $valid_categories)) {
       $category = 'sin_categoria';
   }
   
   // Si viene con ID, extraer filename
   if (!$filename && $id) {
       if (strpos($id, 'audio_') === 0) {
           $filename = str_replace('audio_', '', $id) . '.mp3';
       }
   }
   
   if (empty($filename)) {
       return ['success' => false, 'error' => 'Filename requerido'];
   }
   
   try {
       $db = new PDO("sqlite:$dbPath");
       $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       
       // Preparar display_name si viene título
       $display_name_sql = '';
       $params = [$category, $filename];
       
       if (!empty($title)) {
           $display_name_sql = ', display_name = ?';
           array_splice($params, 1, 0, [$title]); // Insertar título en posición 1
       }
       
       $stmt = $db->prepare("
           UPDATE audio_metadata 
           SET is_saved = 1, 
               saved_at = CURRENT_TIMESTAMP,
               category = ?
               $display_name_sql
           WHERE filename = ? 
               AND is_active = 1
       ");
       
       $stmt->execute($params);
       
       if ($stmt->rowCount() > 0) {
           // Obtener los datos actualizados para emitir el evento correcto
           $stmt = $db->prepare("SELECT * FROM audio_metadata WHERE filename = ?");
           $stmt->execute([$filename]);
           $updatedData = $stmt->fetch(PDO::FETCH_ASSOC);
           
           return [
               'success' => true, 
               'message' => 'Mensaje guardado en favoritos',
               'data' => [
                   'id' => 'audio_' . str_replace('.mp3', '', $filename),
                   'filename' => $filename,
                   'category' => $updatedData['category'],
                   'display_name' => $updatedData['display_name'],
                   'saved_at' => $updatedData['saved_at']
               ]
           ];
       } else {
           return [
               'success' => false, 
               'error' => 'Mensaje no encontrado'
           ];
       }
       
   } catch (Exception $e) {
       return ['success' => false, 'error' => $e->getMessage()];
   }
}

/**
* Soft delete de un mensaje (marcar como inactivo)
*/
function softDeleteMessage($data) {
   global $dbPath;
   
   $id = $data['id'] ?? '';
   
   // Extraer filename del ID
   if (strpos($id, 'audio_') === 0) {
       $filename = str_replace('audio_', '', $id) . '.mp3';
   } else {
       return ['success' => false, 'error' => 'ID inválido'];
   }
   
   try {
       $db = new PDO("sqlite:$dbPath");
       $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       
       $stmt = $db->prepare("
           UPDATE audio_metadata 
           SET is_active = 0,
               updated_at = CURRENT_TIMESTAMP 
           WHERE filename = ?
       ");
       
       $stmt->execute([$filename]);
       
       if ($stmt->rowCount() > 0) {
           return [
               'success' => true, 
               'message' => 'Mensaje archivado correctamente'
           ];
       } else {
           return [
               'success' => false, 
               'error' => 'Mensaje no encontrado'
           ];
       }
       
   } catch (Exception $e) {
       return ['success' => false, 'error' => $e->getMessage()];
   }
}

/**
* Borrado batch de mensajes (soft delete masivo)
* Límite de 50 mensajes por operación
* No borra mensajes con programaciones activas
*/
function deleteBatch($data) {
   global $dbPath;
   
   $ids = $data['ids'] ?? [];
   $forceDelete = $data['force'] ?? false; // Para borrado físico futuro
   
   // Validación básica
   if (empty($ids) || !is_array($ids)) {
       return ['success' => false, 'error' => 'No se proporcionaron IDs válidos'];
   }
   
   // Límite de seguridad: máximo 50 mensajes por operación
   if (count($ids) > 50) {
       return ['success' => false, 'error' => 'Máximo 50 mensajes por operación. Seleccionaste ' . count($ids)];
   }
   
   // Extraer filenames de los IDs
   $filenames = [];
   foreach ($ids as $id) {
       if (strpos($id, 'audio_') === 0) {
           $filenames[] = str_replace('audio_', '', $id) . '.mp3';
       }
   }
   
   if (empty($filenames)) {
       return ['success' => false, 'error' => 'No se encontraron archivos válidos'];
   }
   
   try {
       $db = new PDO("sqlite:$dbPath");
       $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       
       // Iniciar transacción
       $db->beginTransaction();
       
       // Verificar si hay mensajes con programaciones activas
       $placeholders = str_repeat('?,', count($filenames) - 1) . '?';
       $checkStmt = $db->prepare("
           SELECT DISTINCT am.filename, COUNT(ash.id) as active_schedules
           FROM audio_metadata am
           LEFT JOIN audio_schedule ash ON am.filename = ash.filename AND ash.is_active = 1
           WHERE am.filename IN ($placeholders)
           GROUP BY am.filename
       ");
       $checkStmt->execute($filenames);
       $results = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
       
       $messagesWithSchedules = [];
       $safeToDelete = [];
       
       foreach ($results as $row) {
           if ($row['active_schedules'] > 0) {
               $messagesWithSchedules[] = $row['filename'];
           } else {
               $safeToDelete[] = $row['filename'];
           }
       }
       
       // Si hay mensajes con programaciones activas, advertir
       if (!empty($messagesWithSchedules)) {
           $db->rollBack();
           return [
               'success' => false,
               'error' => 'Algunos mensajes tienen programaciones activas',
               'messages_with_schedules' => $messagesWithSchedules,
               'safe_to_delete' => $safeToDelete
           ];
       }
       
       // Proceder con el soft delete solo de mensajes seguros
       if (!empty($safeToDelete)) {
           $placeholders = str_repeat('?,', count($safeToDelete) - 1) . '?';
           
           // Log en archivo en lugar de tabla audit_log (que no existe)
           foreach ($safeToDelete as $file) {
               error_log("[BATCH DELETE PRE] Marcando para eliminación: $file");
           }
           
           // Realizar soft delete
           $deleteStmt = $db->prepare("
               UPDATE audio_metadata 
               SET is_active = 0,
                   is_saved = 0,
                   updated_at = CURRENT_TIMESTAMP,
                   notes = COALESCE(notes, '') || ' [Batch deleted: ' || datetime('now') || ']'
               WHERE filename IN ($placeholders)
           ");
           $deleteStmt->execute($safeToDelete);
           
           $deletedCount = $deleteStmt->rowCount();
           
           // Commit de la transacción
           $db->commit();
           
           // Log adicional para auditoría
           error_log("[BATCH DELETE] Usuario eliminó $deletedCount mensajes: " . implode(', ', $safeToDelete));
           
           return [
               'success' => true,
               'message' => "Se eliminaron $deletedCount mensajes correctamente",
               'deleted_count' => $deletedCount,
               'deleted_files' => $safeToDelete
           ];
       }
       
       $db->rollBack();
       return ['success' => false, 'error' => 'No se encontraron mensajes para eliminar'];
       
   } catch (Exception $e) {
       if (isset($db)) {
           $db->rollBack();
       }
       error_log("[BATCH DELETE ERROR] " . $e->getMessage());
       return ['success' => false, 'error' => 'Error en el proceso: ' . $e->getMessage()];
   }
}

/**
* Obtener estadísticas para limpieza automática
*/
function getCleanupStats() {
   global $dbPath;
   
   try {
       $db = new PDO("sqlite:$dbPath");
       
       // Mensajes candidatos para limpieza automática
       $stmt = $db->query("
           SELECT COUNT(*) as count
           FROM audio_metadata
           WHERE is_active = 1
             AND is_saved = 1
             AND category = 'sin_categoria'
             AND julianday('now') - julianday(created_at) > 30
             AND play_count = 0
       ");
       $autoCleanCandidates = $stmt->fetchColumn();
       
       // Total de mensajes guardados por categoría
       $stmt = $db->query("
           SELECT category, COUNT(*) as count
           FROM audio_metadata
           WHERE is_active = 1 AND is_saved = 1
           GROUP BY category
       ");
       $byCategory = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
       
       // Mensajes inactivos (soft deleted)
       $stmt = $db->query("
           SELECT COUNT(*) as count
           FROM audio_metadata
           WHERE is_active = 0
       ");
       $inactiveCount = $stmt->fetchColumn();
       
       return [
           'success' => true,
           'stats' => [
               'auto_clean_candidates' => $autoCleanCandidates,
               'by_category' => $byCategory,
               'inactive_count' => $inactiveCount,
               'recommendation' => $autoCleanCandidates > 20 ? 
                   'Se recomienda ejecutar limpieza automática' : 
                   'No es necesaria limpieza automática'
           ]
       ];
       
   } catch (Exception $e) {
       return ['success' => false, 'error' => $e->getMessage()];
   }
}

// Procesar request
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$action = $input['action'] ?? $_GET['action'] ?? 'list';

// Debug logging
error_log("[SAVED-MESSAGES DEBUG] Method: $method");
error_log("[SAVED-MESSAGES DEBUG] Raw input: " . substr($rawInput, 0, 500));
error_log("[SAVED-MESSAGES DEBUG] Decoded action: $action");
if ($action === 'delete_batch' && isset($input['ids'])) {
    error_log("[SAVED-MESSAGES DEBUG] IDs received: " . json_encode($input['ids']));
}

switch ($action) {
   case 'list':
       echo json_encode(getSavedMessages());
       break;
       
   case 'update_category':
       echo json_encode(updateCategory($input));
       break;
       
   case 'update_display_name':
       echo json_encode(updateDisplayName($input));
       break;
       
   case 'mark_as_saved':
       echo json_encode(markAsSaved($input));
       break;
       
   case 'soft_delete':
       echo json_encode(softDeleteMessage($input));
       break;
       
   case 'delete_batch':
       echo json_encode(deleteBatch($input));
       break;
       
   case 'cleanup_stats':
       echo json_encode(getCleanupStats());
       break;
       
   default:
       echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}