#!/bin/bash

# Script de prueba para TTS con ducking

echo "==================================="
echo "Test de TTS con Autoducking"
echo "==================================="

# URL del servicio
URL="http://localhost:4000/src/api/tts-ducking-service.php"

echo -e "\n1. Generando mensaje con ducking..."
curl -X POST "$URL" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "text": "Atención estimados clientes, les informamos que en 15 minutos iniciaremos el cierre del centro comercial. Por favor diríganse a las salidas.",
    "voice": "Rachel",
    "category": "informativos"
  }' | jq .

echo -e "\n2. Esperando 5 segundos..."
sleep 5

echo -e "\n3. Enviando otro mensaje..."
curl -X POST "$URL" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "text": "Promoción especial en el segundo piso, 50 por ciento de descuento en toda la tienda.",
    "voice": "Bella",
    "category": "promociones"
  }' | jq .

echo -e "\n==================================="
echo "Prueba completada"
echo "Escucha la radio para verificar el ducking"
echo "==================================="