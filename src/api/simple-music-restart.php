<?php
/**
 * Endpoint simple para reiniciar servicios de música
 * Devuelve respuesta inmediata y programa el reinicio
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Crear archivo flag para indicar que se necesita reinicio
$flagFile = '/tmp/php-fpm-needs-restart';
file_put_contents($flagFile, date('Y-m-d H:i:s'));

// Programar reinicio en background (después de 2 segundos)
$command = 'nohup bash -c "sleep 2 && sudo /bin/systemctl restart php8.1-fpm" > /dev/null 2>&1 &';
exec($command);

// Respuesta inmediata
echo json_encode([
    'success' => true,
    'message' => 'Servicios programados para reinicio. Los cambios se aplicarán en 2-3 segundos.'
]);
?>