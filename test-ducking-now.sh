#!/bin/bash
echo "==================================="
echo "    TEST RÁPIDO DE DUCKING"
echo "==================================="
echo ""
echo "Enviando mensaje de prueba..."

php /var/www/casa/src/api/ducking-service.php

echo ""
echo "==================================="
echo "Si el ducking funciona deberías escuchar:"
echo "✓ La música baja al 20% de volumen"
echo "✓ El mensaje TTS suena claro"
echo "✓ La música vuelve al 100% al terminar"
echo ""
echo "NOTA: El cambio es INSTANTÁNEO (sin fade)"
echo "==================================="
