#!/usr/bin/php
<?php
/**
 * Prueba de generación de audio después del fix
 */

echo "=== Probando generación de audio ===\n\n";

// Cambiar al directorio de la API
chdir('/var/www/casa/src/api');

// Simular request POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['SCRIPT_NAME'] = 'generate.php';

// Datos de prueba
$testData = [
    'action' => 'generate_audio',
    'text' => 'Prueba del sistema corregido',
    'voice' => 'rachel',
    'category' => 'test'
];

// Capturar la salida
ob_start();

// Simular input JSON
$inputJson = json_encode($testData);
file_put_contents('php://input', $inputJson);

// Simular que viene del stdin
$GLOBALS['HTTP_RAW_POST_DATA'] = $inputJson;

// Interceptar file_get_contents para php://input
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPhpStream");

class MockPhpStream {
    private $data;
    private $position = 0;
    
    function stream_open($path, $mode, $options, &$opened_path) {
        if ($path === 'php://input') {
            global $testData;
            $this->data = json_encode($testData);
            $this->position = 0;
            return true;
        }
        return false;
    }
    
    function stream_read($count) {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    function stream_eof() {
        return $this->position >= strlen($this->data);
    }
    
    function stream_stat() {
        return [];
    }
}

// Incluir y ejecutar
try {
    require 'generate.php';
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();

// Restaurar wrapper
stream_wrapper_restore("php");

// Analizar resultado
$result = json_decode($output, true);

echo "Respuesta raw: " . substr($output, 0, 500) . "\n\n";

if ($result && isset($result['success'])) {
    if ($result['success']) {
        echo "✅ Audio generado exitosamente!\n";
        echo "   Archivo: " . $result['filename'] . "\n";
        echo "   AzuraCast: " . $result['azuracast_filename'] . "\n";
        
        // Verificar formato del nombre
        if (preg_match('/^mensaje_/', $result['filename'])) {
            echo "✅ Usa el nuevo formato de nombres\n";
        } else {
            echo "⚠️ No usa el formato esperado\n";
        }
    } else {
        echo "❌ Error: " . ($result['error'] ?? 'Desconocido') . "\n";
    }
} else {
    echo "❌ Respuesta inválida\n";
}

echo "\n=== Fin de la prueba ===\n";
?>