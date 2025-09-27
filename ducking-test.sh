#!/bin/bash

echo "======================================="
echo "     SISTEMA DE DUCKING TTS"
echo "======================================="
echo ""

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Función para enviar TTS con ducking
send_ducking() {
    local text="$1"
    local voice="${2:-juan_carlos}"
    
    echo -e "${YELLOW}Enviando:${NC} $text"
    
    php -r '
    require_once "/var/www/casa/src/api/services/tts-service-unified.php";
    
    $text = $argv[1];
    $voice = $argv[2];
    
    $audio = @generateEnhancedTTS($text, $voice);
    if ($audio && strlen($audio) > 0) {
        $file = "/tmp/duck_" . time() . "_" . rand(1000,9999) . ".mp3";
        file_put_contents($file, $audio);
        
        $cmd = "tts_ducking_queue.push file://" . $file;
        $docker = "sudo docker exec azuracast bash -c \"echo \"" . addslashes($cmd) . "\" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock 2>&1\"";
        
        $output = trim(shell_exec($docker));
        if (is_numeric(trim(explode("\n", $output)[0]))) {
            echo "OK|" . trim(explode("\n", $output)[0]) . "|" . $file;
        } else {
            echo "ERROR|" . $output;
        }
    } else {
        echo "ERROR|TTS_FAILED";
    }
    ' "$text" "$voice" 2>/dev/null
}

# Menú interactivo
while true; do
    echo ""
    echo "Opciones:"
    echo "1) Enviar mensaje personalizado"
    echo "2) Prueba rápida (3 mensajes)"
    echo "3) Ver estado de la cola"
    echo "4) Salir"
    echo ""
    read -p "Seleccione opción: " opcion
    
    case $opcion in
        1)
            read -p "Ingrese el mensaje: " mensaje
            result=$(send_ducking "$mensaje")
            IFS='|' read -r status rid file <<< "$result"
            
            if [ "$status" = "OK" ]; then
                echo -e "${GREEN}✓ Enviado exitosamente${NC}"
                echo "  Request ID: $rid"
                echo "  Archivo: $file"
            else
                echo -e "${RED}✗ Error: $rid${NC}"
            fi
            ;;
            
        2)
            echo -e "\n${YELLOW}Enviando prueba de 3 mensajes...${NC}\n"
            
            # Mensaje 1
            result=$(send_ducking "Atención clientes del centro comercial, iniciando prueba del sistema de audio")
            IFS='|' read -r status rid file <<< "$result"
            if [ "$status" = "OK" ]; then
                echo -e "${GREEN}✓ Mensaje 1 enviado (ID: $rid)${NC}"
            fi
            
            sleep 5
            
            # Mensaje 2
            result=$(send_ducking "Este es el segundo mensaje. La música debe bajar automáticamente")
            IFS='|' read -r status rid file <<< "$result"
            if [ "$status" = "OK" ]; then
                echo -e "${GREEN}✓ Mensaje 2 enviado (ID: $rid)${NC}"
            fi
            
            sleep 5
            
            # Mensaje 3
            result=$(send_ducking "Prueba finalizada. Gracias por su atención")
            IFS='|' read -r status rid file <<< "$result"
            if [ "$status" = "OK" ]; then
                echo -e "${GREEN}✓ Mensaje 3 enviado (ID: $rid)${NC}"
            fi
            
            echo -e "\n${GREEN}Prueba completada${NC}"
            ;;
            
        3)
            echo -e "\n${YELLOW}Estado de la cola:${NC}"
            sudo docker exec azuracast bash -c 'echo "tts_ducking_queue.queue" | socat - UNIX-CONNECT:/var/azuracast/stations/test/config/liquidsoap.sock 2>&1'
            ;;
            
        4)
            echo -e "\n${GREEN}Saliendo...${NC}"
            exit 0
            ;;
            
        *)
            echo -e "${RED}Opción inválida${NC}"
            ;;
    esac
done