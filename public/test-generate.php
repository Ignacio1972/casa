<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Mock response para test
$input = json_decode(file_get_contents('php://input'), true);

if ($input['action'] === 'generate_audio') {
    echo json_encode([
        'success' => true,
        'filename' => 'tts' . date('YmdHis') . '.mp3',
        'azuracast_filename' => 'tts' . date('YmdHis') . '.mp3',
        'processed_text' => $input['text'] ?? 'Test'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);
}
?>