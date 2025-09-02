<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../logger/tts-logger.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

$logger = new TTSLogger('playground', TTSLogger::LEVEL_DEBUG);

switch($action) {
    case 'recent':
        $logs = $logger->getRecentLogs(50);
        echo json_encode(['logs' => $logs]);
        break;
        
    case 'log':
        $logger->log(
            $input['message'] ?? '', 
            $input['level'] === 'error' ? TTSLogger::LEVEL_ERROR : TTSLogger::LEVEL_INFO
        );
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}