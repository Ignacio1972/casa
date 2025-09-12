<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$voicesFile = __DIR__ . '/../../../src/api/data/voices-config.json';

// Asegurar que existe el archivo
if (!file_exists($voicesFile)) {
    file_put_contents($voicesFile, json_encode([
        'voices' => [],
        'settings' => ['default_voice' => '', 'version' => '2.0']
    ], JSON_PRETTY_PRINT));
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'list_all':
        $config = json_decode(file_get_contents($voicesFile), true);
        echo json_encode(['success' => true, 'data' => $config]);
        break;
        
    case 'add':
        $config = json_decode(file_get_contents($voicesFile), true);
        $voiceKey = strtolower(str_replace(' ', '_', $input['label']));
        
        // Verificar si ya existe
        if (isset($config['voices'][$voiceKey])) {
            echo json_encode(['success' => false, 'error' => 'Voice key already exists']);
            exit;
        }
        
        $config['voices'][$voiceKey] = [
            'id' => $input['voice_id'],
            'label' => $input['label'],
            'gender' => $input['gender'] ?? 'F',
            'active' => true,
            'category' => 'custom',
            'added_date' => date('Y-m-d H:i:s')
        ];
        
        $config['settings']['last_updated'] = date('c');
        file_put_contents($voicesFile, json_encode($config, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'message' => 'Voice added successfully']);
        break;
        
    case 'toggle':
        $config = json_decode(file_get_contents($voicesFile), true);
        $voiceKey = $input['voice_key'];
        
        if (!isset($config['voices'][$voiceKey])) {
            echo json_encode(['success' => false, 'error' => 'Voice not found']);
            exit;
        }
        
        $config['voices'][$voiceKey]['active'] = !$config['voices'][$voiceKey]['active'];
        $config['settings']['last_updated'] = date('c');
        file_put_contents($voicesFile, json_encode($config, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'active' => $config['voices'][$voiceKey]['active']]);
        break;
        
    case 'delete':
        $config = json_decode(file_get_contents($voicesFile), true);
        $voiceKey = $input['voice_key'];
        
        if (!isset($config['voices'][$voiceKey])) {
            echo json_encode(['success' => false, 'error' => 'Voice not found']);
            exit;
        }
        
        // No permitir borrar la voz por defecto
        if ($config['settings']['default_voice'] === $voiceKey) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete default voice']);
            exit;
        }
        
        unset($config['voices'][$voiceKey]);
        $config['settings']['last_updated'] = date('c');
        file_put_contents($voicesFile, json_encode($config, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'message' => 'Voice deleted']);
        break;
        
    case 'reorder':
        $config = json_decode(file_get_contents($voicesFile), true);
        $voiceKey = $input['voice_key'];
        $direction = $input['direction']; // 'up' o 'down'
        
        if (!isset($config['voices'][$voiceKey])) {
            echo json_encode(['success' => false, 'error' => 'Voice not found']);
            exit;
        }
        
        $currentOrder = $config['voices'][$voiceKey]['order'] ?? 999;
        
        // Encontrar la voz con la que intercambiar
        $swapKey = null;
        $swapOrder = null;
        
        if ($direction === 'up') {
            // Buscar la voz con order inmediatamente menor
            $maxLower = 0;
            foreach ($config['voices'] as $key => $voice) {
                $order = $voice['order'] ?? 999;
                if ($order < $currentOrder && $order > $maxLower) {
                    $maxLower = $order;
                    $swapKey = $key;
                    $swapOrder = $order;
                }
            }
        } else if ($direction === 'down') {
            // Buscar la voz con order inmediatamente mayor
            $minHigher = 9999;
            foreach ($config['voices'] as $key => $voice) {
                $order = $voice['order'] ?? 999;
                if ($order > $currentOrder && $order < $minHigher) {
                    $minHigher = $order;
                    $swapKey = $key;
                    $swapOrder = $order;
                }
            }
        }
        
        // Intercambiar órdenes
        if ($swapKey !== null) {
            $config['voices'][$voiceKey]['order'] = $swapOrder;
            $config['voices'][$swapKey]['order'] = $currentOrder;
            $config['settings']['last_updated'] = date('c');
            
            file_put_contents($voicesFile, json_encode($config, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'message' => 'Order updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Cannot move in that direction']);
        }
        break;
        
    case 'set_default':
        $config = json_decode(file_get_contents($voicesFile), true);
        $voiceKey = $input['voice_key'];
        
        if (!isset($config['voices'][$voiceKey])) {
            echo json_encode(['success' => false, 'error' => 'Voice not found']);
            exit;
        }
        
        // Quitar default anterior
        foreach($config['voices'] as $key => &$voice) {
            $voice['is_default'] = false;
        }
        
        // Establecer nuevo default
        $config['voices'][$voiceKey]['is_default'] = true;
        $config['settings']['default_voice'] = $voiceKey;
        $config['settings']['last_updated'] = date('c');
        
        file_put_contents($voicesFile, json_encode($config, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'message' => 'Default voice updated']);
        break;
        
    case 'update_label':
        $config = json_decode(file_get_contents($voicesFile), true);
        $voiceKey = $input['voice_key'];
        $newLabel = $input['new_label'] ?? '';
        
        if (!isset($config['voices'][$voiceKey])) {
            echo json_encode(['success' => false, 'error' => 'Voice not found']);
            exit;
        }
        
        if (empty($newLabel)) {
            echo json_encode(['success' => false, 'error' => 'Label cannot be empty']);
            exit;
        }
        
        // Actualizar el label
        $config['voices'][$voiceKey]['label'] = $newLabel;
        $config['settings']['last_updated'] = date('c');
        
        file_put_contents($voicesFile, json_encode($config, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'message' => 'Voice label updated successfully']);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action: ' . $action]);
}
?>
