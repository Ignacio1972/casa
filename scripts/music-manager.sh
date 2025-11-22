#!/bin/bash

# Script simplificado para gestión rápida de música
# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

MUSIC_DIR="/var/www/casa/public/audio/music"
SCRIPT_DIR="/var/www/casa/scripts"

# Función para mostrar el menú
show_menu() {
    echo -e "\n${BLUE}=== GESTOR DE MÚSICA PARA JINGLES ===${NC}"
    echo -e "${YELLOW}¿Qué desea hacer?${NC}"
    echo "1) Ver canciones disponibles"
    echo "2) Agregar nueva canción"
    echo "3) Eliminar canción"
    echo "4) Validar canciones"
    echo "5) Reiniciar servicios"
    echo "6) Salir"
    echo -n "Seleccione opción [1-6]: "
}

# Función para listar canciones
list_music() {
    echo -e "\n${BLUE}=== CANCIONES DISPONIBLES ===${NC}"
    if [ -z "$(ls -A $MUSIC_DIR/*.mp3 2>/dev/null)" ]; then
        echo -e "${YELLOW}No hay canciones disponibles${NC}"
    else
        for file in $MUSIC_DIR/*.mp3; do
            if [ -f "$file" ]; then
                basename "$file"
            fi
        done | sort | nl
    fi
}

# Función para agregar canción
add_music() {
    echo -e "\n${BLUE}=== AGREGAR CANCIÓN ===${NC}"
    echo -n "Ingrese la ruta completa del archivo MP3: "
    read filepath

    if [ ! -f "$filepath" ]; then
        echo -e "${RED}Error: El archivo no existe${NC}"
        return 1
    fi

    # Verificar que es MP3
    if ! file "$filepath" | grep -q "Audio file"; then
        echo -e "${YELLOW}Advertencia: El archivo podría no ser un MP3 válido${NC}"
        echo -n "¿Desea continuar? (s/n): "
        read answer
        if [ "$answer" != "s" ]; then
            return 1
        fi
    fi

    filename=$(basename "$filepath")
    dest="$MUSIC_DIR/$filename"

    if [ -f "$dest" ]; then
        echo -e "${YELLOW}El archivo ya existe: $filename${NC}"
        echo -n "¿Desea reemplazarlo? (s/n): "
        read answer
        if [ "$answer" != "s" ]; then
            return 1
        fi
    fi

    # Copiar archivo
    if cp "$filepath" "$dest"; then
        chmod 644 "$dest"
        echo -e "${GREEN}✓ Canción agregada: $filename${NC}"

        # Reiniciar automáticamente
        echo -e "${YELLOW}Reiniciando servicios...${NC}"
        sudo systemctl restart php8.1-fpm
        echo -e "${GREEN}✓ Servicios reiniciados${NC}"
        return 0
    else
        echo -e "${RED}Error al copiar el archivo${NC}"
        return 1
    fi
}

# Función para eliminar canción
remove_music() {
    echo -e "\n${BLUE}=== ELIMINAR CANCIÓN ===${NC}"
    list_music
    echo -n "Ingrese el nombre del archivo a eliminar: "
    read filename

    filepath="$MUSIC_DIR/$filename"

    if [ ! -f "$filepath" ]; then
        echo -e "${RED}Error: El archivo no existe${NC}"
        return 1
    fi

    echo -e "${YELLOW}¿Está seguro que desea eliminar '$filename'? (s/n):${NC} "
    read answer
    if [ "$answer" != "s" ]; then
        return 1
    fi

    if rm "$filepath"; then
        echo -e "${GREEN}✓ Canción eliminada: $filename${NC}"

        # Reiniciar automáticamente
        echo -e "${YELLOW}Reiniciando servicios...${NC}"
        sudo systemctl restart php8.1-fpm
        echo -e "${GREEN}✓ Servicios reiniciados${NC}"
        return 0
    else
        echo -e "${RED}Error al eliminar el archivo${NC}"
        return 1
    fi
}

# Función para validar canciones
validate_music() {
    echo -e "\n${BLUE}=== VALIDANDO CANCIONES ===${NC}"

    valid=0
    invalid=0

    for file in $MUSIC_DIR/*.mp3; do
        if [ -f "$file" ]; then
            filename=$(basename "$file")
            echo -n "Validando: $filename... "

            if ffmpeg -i "$file" -f null - 2>/dev/null; then
                echo -e "${GREEN}✓${NC}"
                ((valid++))
            else
                echo -e "${RED}✗${NC}"
                ((invalid++))
            fi
        fi
    done

    echo -e "\n${BLUE}Resultados:${NC}"
    echo -e "  ${GREEN}Válidas: $valid${NC}"
    if [ $invalid -gt 0 ]; then
        echo -e "  ${RED}Con problemas: $invalid${NC}"
    fi
}

# Función para reiniciar servicios
restart_services() {
    echo -e "\n${YELLOW}Reiniciando servicios...${NC}"

    # Reiniciar PHP-FPM
    if sudo systemctl restart php8.1-fpm; then
        echo -e "${GREEN}✓ PHP-FPM reiniciado${NC}"
    else
        echo -e "${RED}✗ Error reiniciando PHP-FPM${NC}"
    fi

    # Limpiar archivos temporales antiguos
    echo -e "${YELLOW}Limpiando archivos temporales...${NC}"
    find /var/www/casa/src/api/temp/ -type f -name "*.mp3" -mtime +7 -delete 2>/dev/null
    echo -e "${GREEN}✓ Archivos temporales limpiados${NC}"
}

# Verificar permisos
if [ ! -d "$MUSIC_DIR" ]; then
    echo -e "${RED}Error: El directorio de música no existe${NC}"
    exit 1
fi

if [ ! -w "$MUSIC_DIR" ]; then
    echo -e "${RED}Error: Sin permisos de escritura en el directorio de música${NC}"
    echo -e "${YELLOW}Ejecute: sudo chown -R www-data:www-data $MUSIC_DIR${NC}"
    exit 1
fi

# Menú principal
while true; do
    show_menu
    read choice

    case $choice in
        1) list_music ;;
        2) add_music ;;
        3) remove_music ;;
        4) validate_music ;;
        5) restart_services ;;
        6) echo -e "${GREEN}¡Hasta luego!${NC}"; exit 0 ;;
        *) echo -e "${RED}Opción inválida${NC}" ;;
    esac

    echo -e "\n${YELLOW}Presione Enter para continuar...${NC}"
    read
done