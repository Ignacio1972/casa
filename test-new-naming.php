<?php
/**
 * Script de prueba para verificar el nuevo sistema de nombres descriptivos
 */

echo "=== Test Sistema de Nombres Descriptivos ===\n\n";

// Test 1: Generar un mensaje con el nuevo sistema
echo "Test 1: Generando mensaje con nombre descriptivo...\n";

$testData = [
    'action' => 'generate_audio',
    'text' => 'Bienvenidos a Casa Costanera, disfruten sus compras',
    'voice' => 'rachel',
    'category' => 'promociones'
];

$ch = curl_init('http://localhost:4000/src/api/generate.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
$result = json_decode($response, true);

if ($result && isset($result['success']) && $result['success']) {
    echo "✓ Mensaje generado exitosamente\n";
    echo "  Nombre del archivo: " . $result['filename'] . "\n";
    echo "  Nombre en AzuraCast: " . $result['azuracast_filename'] . "\n";
    
    // Verificar que NO sea el formato tts antiguo
    if (!preg_match('/^tts\d{14}\.mp3$/', $result['filename'])) {
        echo "✓ Formato de nombre correcto (no es tts legacy)\n";
        
        // Verificar que sea el nuevo formato mensaje_*
        if (preg_match('/^mensaje_/', $result['filename'])) {
            echo "✓ Usa el nuevo formato 'mensaje_'\n";
        } else {
            echo "✗ No usa el formato esperado 'mensaje_'\n";
        }
    } else {
        echo "✗ ERROR: Todavía usa el formato tts antiguo\n";
    }
    
    echo "\n";
    
    // Test 2: Verificar que el archivo se puede recuperar desde biblioteca
    echo "Test 2: Verificando acceso desde biblioteca...\n";
    $filename = $result['filename'];
    
    $url = "http://localhost:4000/src/api/biblioteca.php?filename=" . urlencode($filename);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // Solo headers
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✓ Archivo accesible desde biblioteca\n";
    } else {
        echo "✗ Error accediendo archivo desde biblioteca (HTTP $httpCode)\n";
    }
    
    // Test 3: Listar archivos de biblioteca
    echo "\nTest 3: Listando archivos de biblioteca...\n";
    $listData = ['action' => 'list_library'];
    
    $ch = curl_init('http://localhost:4000/src/api/biblioteca.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($listData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $listResult = json_decode($response, true);
    if ($listResult && isset($listResult['success']) && $listResult['success']) {
        echo "✓ Lista obtenida exitosamente\n";
        echo "  Total archivos: " . $listResult['total'] . "\n";
        
        // Buscar el archivo que acabamos de crear
        $found = false;
        foreach ($listResult['files'] as $file) {
            if ($file['filename'] === $filename) {
                $found = true;
                echo "✓ Archivo nuevo encontrado en la lista\n";
                break;
            }
        }
        
        if (!$found) {
            echo "⚠ Archivo nuevo no aparece en la lista (puede tardar en sincronizar)\n";
        }
        
        // Contar archivos con nuevo formato
        $newFormat = 0;
        $oldFormat = 0;
        foreach ($listResult['files'] as $file) {
            if (preg_match('/^mensaje_/', $file['filename'])) {
                $newFormat++;
            } elseif (preg_match('/^tts\d+/', $file['filename'])) {
                $oldFormat++;
            }
        }
        
        echo "\n  Archivos con formato nuevo (mensaje_*): $newFormat\n";
        echo "  Archivos con formato antiguo (tts*): $oldFormat\n";
    } else {
        echo "✗ Error listando archivos\n";
    }
    
} else {
    echo "✗ Error generando mensaje\n";
    echo "Respuesta: " . $response . "\n";
}

echo "\n=== Fin de pruebas ===\n";
?>