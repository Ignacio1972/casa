#!/bin/bash
# Script simple para probar la cola del Player Local

QUEUE_DIR="/var/www/casa/database/local-player-queue"

echo "=== COLA DEL PLAYER LOCAL ==="
echo ""

case "$1" in
  add)
    echo "Agregando mensaje de prueba..."
    php /var/www/casa/public/api/test-local-player-simple.php
    ;;

  check|list)
    echo "Mensajes en cola:"
    count=$(ls -1 $QUEUE_DIR/*.json 2>/dev/null | wc -l)
    echo "Total: $count mensajes"
    echo ""

    if [ $count -gt 0 ]; then
      for file in $QUEUE_DIR/*.json; do
        id=$(basename $file .json)
        echo "  [$id]"
        # Leer datos con PHP (más confiable que jq)
        php -r "
          \$data = json_decode(file_get_contents('$file'), true);
          echo '    Texto: ' . substr(\$data['text'], 0, 60) . '...' . PHP_EOL;
          echo '    Categoría: ' . \$data['category'] . ' | Creado: ' . \$data['created_at'] . PHP_EOL;
        " 2>/dev/null
        echo ""
      done
    fi
    ;;

  clear)
    echo "Limpiando TODA la cola..."
    count=$(ls -1 $QUEUE_DIR/*.json 2>/dev/null | wc -l)
    rm -f $QUEUE_DIR/*.json
    echo "✓ $count mensajes eliminados de la cola."
    ;;

  cleanup)
    echo "Limpiando archivos procesados antiguos..."
    php -r "require_once '/var/www/casa/src/api/helpers/local-player-queue.php'; cleanupLocalPlayerProcessed();"
    echo "✓ Limpieza completada."
    ;;

  *)
    echo "Uso: $0 {add|check|clear|cleanup}"
    echo ""
    echo "  add     - Agregar mensaje de prueba a la cola"
    echo "  check   - Ver mensajes en la cola"
    echo "  clear   - Limpiar TODA la cola (¡cuidado!)"
    echo "  cleanup - Limpiar archivos procesados antiguos"
    echo ""
    echo "Ubicación de la cola: $QUEUE_DIR"
    exit 1
    ;;
esac
