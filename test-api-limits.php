<?php
// Verificar límites de la API y plan de suscripción

function checkApiLimits($apiKey, $keyName) {
    echo "\n=== Verificando API Key: $keyName ===\n";
    
    // 1. Verificar información de usuario/suscripción
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.elevenlabs.io/v1/user",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'xi-api-key: ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $userData = json_decode($response, true);
        
        echo "✅ API Key válida\n\n";
        echo "📊 Información de la cuenta:\n";
        echo "================================\n";
        
        if (isset($userData['subscription'])) {
            $sub = $userData['subscription'];
            echo "📌 Plan: " . ($sub['tier'] ?? 'Unknown') . "\n";
            echo "📌 Estado: " . ($sub['status'] ?? 'Unknown') . "\n";
            
            // Verificar límites
            if (isset($sub['character_count'])) {
                echo "📌 Caracteres usados: " . number_format($sub['character_count']) . "\n";
            }
            if (isset($sub['character_limit'])) {
                echo "📌 Límite de caracteres: " . number_format($sub['character_limit']) . "\n";
            }
            
            // Determinar capacidades según el tier
            $tier = strtolower($sub['tier'] ?? '');
            echo "\n🎯 Capacidades del plan '$tier':\n";
            
            if (strpos($tier, 'free') !== false || strpos($tier, 'basic') !== false) {
                echo "❌ Solo MP3 128kbps (mono)\n";
                echo "❌ No soporta MP3 192kbps\n";
                echo "❌ No soporta PCM\n";
            } elseif (strpos($tier, 'creator') !== false || strpos($tier, 'starter') !== false) {
                echo "✅ MP3 128kbps (mono)\n";
                echo "✅ MP3 192kbps disponible\n";
                echo "❌ PCM requiere plan Pro\n";
            } elseif (strpos($tier, 'pro') !== false) {
                echo "✅ MP3 128kbps (mono)\n";
                echo "✅ MP3 192kbps\n";
                echo "✅ PCM 44.1kHz disponible\n";
            }
        } else {
            echo "No se pudo obtener información de suscripción\n";
        }
        
        // Mostrar más detalles si están disponibles
        if (isset($userData['xi_api_key'])) {
            echo "\n🔑 Detalles de la API Key:\n";
            echo "- Key ID: " . substr($userData['xi_api_key'], 0, 10) . "...\n";
        }
        
    } else {
        echo "❌ Error al verificar API Key (HTTP $httpCode)\n";
        if ($response) {
            $error = json_decode($response, true);
            if ($error) {
                echo "Error: " . json_encode($error, JSON_PRETTY_PRINT) . "\n";
            }
        }
    }
    
    echo str_repeat('-', 60) . "\n";
}

// API Key actual del sistema
require_once __DIR__ . '/src/api/config.php';
checkApiLimits(ELEVENLABS_API_KEY, "API Key Actual del Sistema");

// Nueva API Key proporcionada
$newKey = 'sk_fdea7277f86413944955135401ee682be927a89f8d3da167';
checkApiLimits($newKey, "Nueva API Key");

echo "\n=== RECOMENDACIONES ===\n";
echo "1. Si el plan es Free/Basic: Solo tendrás 128kbps mono\n";
echo "2. Si el plan es Creator/Starter: Puedes usar mp3_44100_192\n";
echo "3. Si el plan es Pro: Puedes usar PCM y todos los formatos\n";
echo "4. Alternativa: Mejorar con FFmpeg después de generar\n";