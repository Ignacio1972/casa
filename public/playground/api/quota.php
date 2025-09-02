<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../api/config.php';

// Simulación de quota - en producción esto vendría de ElevenLabs API
$quotaInfo = [
    'used' => rand(10000, 50000),  // Caracteres usados
    'limit' => 100000,              // Límite mensual
    'reset_date' => date('Y-m-d', strtotime('first day of next month'))
];

echo json_encode($quotaInfo);