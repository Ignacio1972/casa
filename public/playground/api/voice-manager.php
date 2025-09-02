<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$voicesFile = __DIR__ . '/../../api/data/custom-voices.json';

// Crear archivo si no existe
if (!file_exists($voicesFile)) {
    if (!file_exists(dirname($voicesFile))) {
        mkdir(dirname($voicesFile), 0755, true);
    }
    file_put_contents($voicesFile, json_encode([]));
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'list':
        $customVoices = json_decode(file_get_contents($voicesFile), true);
        echo json_encode(['success' => true, 'voices' => $customVoices]);
        break;
        
    case 'add':
        $voiceId = $input['voice_id'] ?? '';
        $voiceName = $input['voice_name'] ?? '';
        $voiceGender = $input['voice_gender'] ?? 'F';
        $voiceKey = $input['voice_key'] ?? strtolower(str_replace(' ', '_', $voiceName));
        
        if (!$voiceId || !$voiceName) {
            echo json_encode(['success' => false, 'error' => 'Voice ID y Name son requeridos']);
            exit;
        }
        
        // Cargar voces existentes
        $customVoices = json_decode(file_get_contents($voicesFile), true);
        
        // Agregar nueva voz
        $customVoices[$voiceKey] = [
            'id' => $voiceId,
            'label' => $voiceName,
            'gender' => $voiceGender,
            'custom' => true,
            'added_date' => date('Y-m-d H:i:s')
        ];
        
        // Guardar
        file_put_contents($voicesFile, json_encode($customVoices, JSON_PRETTY_PRINT));
        
        echo json_encode(['success' => true, 'message' => 'Voz agregada exitosamente']);
        break;
        
    case 'delete':
        $voiceKey = $input['voice_key'] ?? '';
        
        $customVoices = json_decode(file_get_contents($voicesFile), true);
        unset($customVoices[$voiceKey]);
        
        file_put_contents($voicesFile, json_encode($customVoices, JSON_PRETTY_PRINT));
        
        echo json_encode(['success' => true]);
        break;
        
    case 'test':
        // Probar si un Voice ID es válido
        $voiceId = $input['voice_id'] ?? '';
        
        // Aquí podrías hacer una llamada a ElevenLabs para verificar
        // Por ahora solo retornamos success
        echo json_encode(['success' => true, 'valid' => true]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}