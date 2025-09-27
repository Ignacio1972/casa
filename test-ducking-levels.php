<?php
/**
 * Script para probar diferentes niveles de ducking
 */

require_once '/var/www/casa/src/api/ducking-service.php';

echo "=====================================\n";
echo "   PRUEBA DE NIVELES DE DUCKING\n";
echo "=====================================\n\n";

$service = new DuckingService();

// Mensajes de prueba con diferentes duraciones
$tests = [
    [
        'text' => 'Prueba corta. Uno, dos, tres.',
        'desc' => 'Mensaje corto (3 seg)'
    ],
    [
        'text' => 'Atención clientes del centro comercial. Este es un mensaje de duración media para probar el sistema de ducking con fade suave.',
        'desc' => 'Mensaje medio (7 seg)'
    ],
    [
        'text' => 'Estimados visitantes, les informamos que en quince minutos iniciaremos el cierre del centro comercial. Por favor, diríjanse a las cajas para realizar sus últimas compras. Agradecemos su visita y los esperamos mañana a partir de las diez de la mañana.',
        'desc' => 'Mensaje largo (15 seg)'
    ]
];

echo "Opciones:\n";
echo "1) Probar mensaje corto\n";
echo "2) Probar mensaje medio\n";
echo "3) Probar mensaje largo\n";
echo "4) Probar secuencia completa (30 seg entre mensajes)\n";
echo "5) Probar fade rápido (múltiples mensajes seguidos)\n";
echo "\n";

$option = readline("Seleccione opción: ");

switch($option) {
    case '1':
    case '2':
    case '3':
        $index = (int)$option - 1;
        echo "\nEnviando: " . $tests[$index]['desc'] . "\n";
        $result = $service->sendWithDucking($tests[$index]['text'], 'juan_carlos');
        
        if ($result['success']) {
            echo "✅ Enviado - Request ID: " . $result['request_id'] . "\n";
            echo "   Duración estimada: " . $result['duration'] . " segundos\n";
            echo "\n🎧 ESCUCHA AHORA:\n";
            echo "   - La música debe hacer fade down en 4 segundos\n";
            echo "   - Música al 20% durante el mensaje\n";
            echo "   - Fade up de 4 segundos al terminar\n";
        }
        break;
        
    case '4':
        echo "\n🎬 Iniciando secuencia completa...\n\n";
        
        foreach ($tests as $i => $test) {
            echo "📢 Mensaje " . ($i + 1) . ": " . $test['desc'] . "\n";
            $result = $service->sendWithDucking($test['text'], 'juan_carlos');
            
            if ($result['success']) {
                echo "   ✅ Request ID: " . $result['request_id'] . "\n";
                echo "   ⏱️  Esperando 30 segundos...\n\n";
                
                if ($i < count($tests) - 1) {
                    sleep(30);
                }
            }
        }
        
        echo "✅ Secuencia completada\n";
        break;
        
    case '5':
        echo "\n⚡ Prueba de fade rápido (3 mensajes con 10 seg de separación)\n\n";
        
        for ($i = 1; $i <= 3; $i++) {
            $text = "Mensaje rápido número $i. Probando transiciones.";
            echo "📢 Enviando mensaje $i...\n";
            
            $result = $service->sendWithDucking($text, 'juan_carlos');
            if ($result['success']) {
                echo "   ✅ Request ID: " . $result['request_id'] . "\n";
                
                if ($i < 3) {
                    echo "   ⏱️  Esperando 10 segundos...\n\n";
                    sleep(10);
                }
            }
        }
        
        echo "\n✅ Prueba de fade rápido completada\n";
        echo "   Deberías haber escuchado transiciones suaves entre mensajes\n";
        break;
        
    default:
        echo "Opción no válida\n";
}

echo "\n";
echo "=====================================\n";
echo "CONFIGURACIÓN ACTUAL:\n";
echo "- Fade down: 4 segundos\n";
echo "- Nivel música durante TTS: 20%\n";
echo "- Fade up: 4 segundos\n";
echo "- Amplificación TTS: 1.1x\n";
echo "\n";
echo "Para ajustar estos valores, edita la configuración\n";
echo "en AzuraCast -> Liquidsoap Config\n";
echo "=====================================\n";