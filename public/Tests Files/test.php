<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'method' => $_SERVER['REQUEST_METHOD'],
    'input' => file_get_contents('php://input'),
    'post' => $_POST,
    'env' => $_ENV
]);
?>