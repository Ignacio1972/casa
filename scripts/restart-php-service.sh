#!/bin/bash
# Script para reiniciar PHP-FPM de forma asíncrona
# Esto evita el error 502 cuando se reinicia desde una petición PHP

# Esperar un poco para que la respuesta HTTP se complete
(sleep 2 && sudo /bin/systemctl restart php8.1-fpm) &

# Devolver inmediatamente
echo "OK"
exit 0