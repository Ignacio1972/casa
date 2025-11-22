<?php
// Eliminar el archivo de voces custom que puede tener IDs inválidos
$customVoicesFile = __DIR__ . '/../../api/data/custom-voices.json';

if (file_exists($customVoicesFile)) {
    // Hacer backup
    copy($customVoicesFile, $customVoicesFile . '.backup');
    
    // Limpiar el archivo (empezar de cero)
    file_put_contents($customVoicesFile, json_encode([]));
    echo "Voces custom limpiadas. Backup guardado.\n";
} else {
    echo "No hay archivo de voces custom.\n";
}