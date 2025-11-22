#!/bin/bash
################################################################################
# MediaFlow - Health Check Script
# Verifica el estado de todos los componentes del sistema
################################################################################

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Configuración
CASA_DIR="/var/www/casa"
API_BASE="http://localhost:2082"
AZURACAST_API="http://localhost/api"
LOG_FILE="$CASA_DIR/logs/health-check-$(date +%Y-%m-%d).log"

# Contadores
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0
WARNING_CHECKS=0

# Función para logging
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE" 2>/dev/null
}

# Función para imprimir header
print_header() {
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BOLD}$1${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
}

# Función para check exitoso
check_ok() {
    echo -e "  ${GREEN}✓${NC} $1"
    ((PASSED_CHECKS++))
    ((TOTAL_CHECKS++))
    log "OK: $1"
}

# Función para check fallido
check_fail() {
    echo -e "  ${RED}✗${NC} $1"
    ((FAILED_CHECKS++))
    ((TOTAL_CHECKS++))
    log "FAIL: $1"
}

# Función para warning
check_warn() {
    echo -e "  ${YELLOW}⚠${NC} $1"
    ((WARNING_CHECKS++))
    ((TOTAL_CHECKS++))
    log "WARN: $1"
}

# Función para info
print_info() {
    echo -e "  ${BLUE}ℹ${NC} $1"
}

################################################################################
# CHECKS DEL SISTEMA
################################################################################

echo ""
print_header "🏥 MEDIAFLOW - HEALTH CHECK"
echo -e "${CYAN}Fecha:${NC} $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# 1. SERVICIOS DEL SISTEMA
print_header "1. 🔧 SERVICIOS DEL SISTEMA"

# Nginx
if systemctl is-active --quiet nginx 2>/dev/null; then
    check_ok "nginx está corriendo"
else
    check_fail "nginx NO está corriendo"
fi

# PHP-FPM
if systemctl is-active --quiet php8.1-fpm 2>/dev/null; then
    check_ok "PHP-FPM 8.1 está corriendo"
    PHP_WORKERS=$(ps aux | grep php-fpm | grep -c "pool www")
    print_info "Workers PHP-FPM: $PHP_WORKERS"
else
    check_fail "PHP-FPM NO está corriendo"
fi

# Verificar puerto 2082
if netstat -tlpn 2>/dev/null | grep -q ":2082" || ss -tlpn 2>/dev/null | grep -q ":2082"; then
    check_ok "Puerto 2082 está escuchando"
else
    check_fail "Puerto 2082 NO está escuchando"
fi

echo ""

# 2. INTERFACES WEB
print_header "2. 🌐 INTERFACES WEB"

# Dashboard principal
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE/index.html" 2>/dev/null)
if [ "$HTTP_CODE" = "200" ]; then
    check_ok "Dashboard principal (HTTP $HTTP_CODE)"
else
    check_fail "Dashboard principal (HTTP $HTTP_CODE)"
fi

# Modo Automático
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE/automatic-mode.html" 2>/dev/null)
if [ "$HTTP_CODE" = "200" ]; then
    check_ok "Modo Automático (HTTP $HTTP_CODE)"
else
    check_fail "Modo Automático (HTTP $HTTP_CODE)"
fi

# Playground
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE/playground/index.html" 2>/dev/null)
if [ "$HTTP_CODE" = "200" ]; then
    check_ok "Playground (HTTP $HTTP_CODE)"
else
    check_warn "Playground (HTTP $HTTP_CODE)"
fi

echo ""

# 3. APIs CRÍTICAS
print_header "3. 🔌 APIS CRÍTICAS"

# Generate API
RESPONSE=$(curl -s -X POST "$API_BASE/api/generate.php" -H "Content-Type: application/json" -d '{}' 2>/dev/null)
if echo "$RESPONSE" | grep -q "error"; then
    check_ok "Generate API respondiendo"
else
    check_fail "Generate API no responde correctamente"
fi

# Jingle Service
RESPONSE=$(curl -s -X POST "$API_BASE/api/jingle-service.php" -H "Content-Type: application/json" -d '{}' 2>/dev/null)
if echo "$RESPONSE" | grep -q "error\|success"; then
    check_ok "Jingle Service respondiendo"
else
    check_fail "Jingle Service no responde"
fi

# Claude Service
RESPONSE=$(curl -s -X POST "$API_BASE/api/claude-service.php" -H "Content-Type: application/json" -d '{"action":"test"}' 2>/dev/null)
if echo "$RESPONSE" | grep -q "error\|success"; then
    check_ok "Claude Service respondiendo"
else
    check_fail "Claude Service no responde"
fi

# Dashboard Module
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE/modules/dashboard/index.js" 2>/dev/null)
if [ "$HTTP_CODE" = "200" ]; then
    check_ok "Dashboard Module cargando"
else
    check_fail "Dashboard Module no carga (HTTP $HTTP_CODE)"
fi

echo ""

# 4. BASE DE DATOS
print_header "4. 💾 BASE DE DATOS"

DB_PATH="$CASA_DIR/database/casa.db"

if [ -f "$DB_PATH" ]; then
    check_ok "Base de datos existe"

    # Tamaño de la DB
    DB_SIZE=$(du -h "$DB_PATH" | cut -f1)
    print_info "Tamaño: $DB_SIZE"

    # Permisos
    DB_PERMS=$(stat -c "%a" "$DB_PATH" 2>/dev/null || stat -f "%A" "$DB_PATH" 2>/dev/null)
    if [ "$DB_PERMS" = "666" ] || [ "$DB_PERMS" = "664" ]; then
        check_ok "Permisos correctos ($DB_PERMS)"
    else
        check_warn "Permisos pueden causar problemas ($DB_PERMS)"
    fi

    # Contar registros
    if command -v sqlite3 &> /dev/null; then
        MESSAGE_COUNT=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM audio_metadata;" 2>/dev/null)
        if [ -n "$MESSAGE_COUNT" ]; then
            check_ok "Base de datos accesible ($MESSAGE_COUNT mensajes)"
        else
            check_warn "No se pudo contar registros"
        fi
    else
        check_warn "sqlite3 no está instalado para verificar contenido"
    fi
else
    check_fail "Base de datos NO existe en $DB_PATH"
fi

echo ""

# 5. DIRECTORIOS Y ARCHIVOS
print_header "5. 📁 DIRECTORIOS Y ARCHIVOS"

# Directorio temp
if [ -d "$CASA_DIR/src/api/temp" ]; then
    check_ok "Directorio temp existe"
    TEMP_FILES=$(ls -1 "$CASA_DIR/src/api/temp"/*.mp3 2>/dev/null | wc -l)
    print_info "Archivos temp: $TEMP_FILES"

    # Verificar archivos de hoy
    TODAY_FILES=$(find "$CASA_DIR/src/api/temp" -name "*.mp3" -mtime 0 2>/dev/null | wc -l)
    if [ "$TODAY_FILES" -gt 0 ]; then
        check_ok "Archivos generados hoy: $TODAY_FILES"
    else
        check_warn "No hay archivos generados hoy"
    fi
else
    check_fail "Directorio temp NO existe"
fi

# Directorio de música
if [ -d "$CASA_DIR/public/audio/music" ]; then
    MUSIC_COUNT=$(find "$CASA_DIR/public/audio/music" -name "*.mp3" -o -name "*.m4a" 2>/dev/null | wc -l)
    if [ "$MUSIC_COUNT" -gt 0 ]; then
        check_ok "Música disponible ($MUSIC_COUNT archivos)"
    else
        check_warn "No hay archivos de música"
    fi
else
    check_fail "Directorio de música NO existe"
fi

# Directorio de logs
if [ -d "$CASA_DIR/src/api/logs" ]; then
    check_ok "Directorio de logs existe"

    # Verificar logs de hoy
    TODAY_LOG="$CASA_DIR/src/api/logs/tts-$(date +%Y-%m-%d).log"
    if [ -f "$TODAY_LOG" ]; then
        LOG_SIZE=$(du -h "$TODAY_LOG" | cut -f1)
        check_ok "Log de hoy existe ($LOG_SIZE)"

        # Buscar errores recientes
        ERROR_COUNT=$(grep -i "error" "$TODAY_LOG" 2>/dev/null | wc -l)
        if [ "$ERROR_COUNT" -gt 0 ]; then
            check_warn "Log contiene $ERROR_COUNT líneas con 'error'"
        fi
    else
        check_warn "No hay log de hoy (sin actividad reciente)"
    fi
else
    check_warn "Directorio de logs NO existe"
fi

echo ""

# 6. CONFIGURACIONES
print_header "6. ⚙️  CONFIGURACIONES"

# Clients config
if [ -f "$CASA_DIR/src/api/data/clients-config.json" ]; then
    check_ok "Configuración de clientes existe"
    if command -v jq &> /dev/null; then
        ACTIVE_CLIENT=$(jq -r '.active_client' "$CASA_DIR/src/api/data/clients-config.json" 2>/dev/null)
        print_info "Cliente activo: $ACTIVE_CLIENT"
    fi
else
    check_fail "Configuración de clientes NO existe"
fi

# Voices config
if [ -f "$CASA_DIR/src/api/data/voices-config.json" ]; then
    check_ok "Configuración de voces existe"
    if command -v jq &> /dev/null; then
        ACTIVE_VOICES=$(jq '[.voices | to_entries[] | select(.value.active == true)] | length' "$CASA_DIR/src/api/data/voices-config.json" 2>/dev/null)
        print_info "Voces activas: $ACTIVE_VOICES"
    fi
else
    check_fail "Configuración de voces NO existe"
fi

# TTS config
if [ -f "$CASA_DIR/src/api/data/tts-config.json" ]; then
    check_ok "Configuración TTS existe"
else
    check_fail "Configuración TTS NO existe"
fi

# Jingle config
if [ -f "$CASA_DIR/src/api/data/jingle-config.json" ]; then
    check_ok "Configuración de jingles existe"
else
    check_warn "Configuración de jingles NO existe"
fi

echo ""

# 7. AZURACAST
print_header "7. 🎵 AZURACAST"

# Verificar si Docker está corriendo
if command -v docker &> /dev/null; then
    if docker ps --format "{{.Names}}" 2>/dev/null | grep -q "azuracast"; then
        check_ok "Contenedor AzuraCast corriendo"

        # CPU y memoria del contenedor
        CONTAINER_STATS=$(docker stats --no-stream --format "{{.CPUPerc}} {{.MemUsage}}" azuracast 2>/dev/null)
        if [ -n "$CONTAINER_STATS" ]; then
            print_info "Recursos: $CONTAINER_STATS"
        fi
    else
        check_fail "Contenedor AzuraCast NO está corriendo"
    fi
else
    check_warn "Docker no disponible para verificar"
fi

# API Status
AZURA_STATUS=$(curl -s "$AZURACAST_API/status" 2>/dev/null)
if echo "$AZURA_STATUS" | grep -q "online.*true"; then
    check_ok "API AzuraCast online"
else
    check_fail "API AzuraCast no responde"
fi

# Liquidsoap
LIQUIDSOAP_COUNT=$(ps aux | grep liquidsoap | grep -v grep | wc -l)
if [ "$LIQUIDSOAP_COUNT" -gt 0 ]; then
    check_ok "Liquidsoap corriendo ($LIQUIDSOAP_COUNT instancias)"
else
    check_fail "Liquidsoap NO está corriendo"
fi

# Icecast
if netstat -tlpn 2>/dev/null | grep -q ":8000" || ss -tlpn 2>/dev/null | grep -q ":8000"; then
    check_ok "Icecast escuchando en puerto 8000"
else
    check_warn "Icecast no detectado en puerto 8000"
fi

echo ""

# 8. RECURSOS DEL SISTEMA
print_header "8. 💻 RECURSOS DEL SISTEMA"

# Memoria
TOTAL_MEM=$(free -h | awk '/^Mem:/ {print $2}')
USED_MEM=$(free -h | awk '/^Mem:/ {print $3}')
AVAIL_MEM=$(free -h | awk '/^Mem:/ {print $7}')
MEM_PERCENT=$(free | awk '/^Mem:/ {printf "%.0f", $3/$2 * 100}')

if [ "$MEM_PERCENT" -lt 80 ]; then
    check_ok "Memoria: $USED_MEM / $TOTAL_MEM (${MEM_PERCENT}%)"
elif [ "$MEM_PERCENT" -lt 90 ]; then
    check_warn "Memoria: $USED_MEM / $TOTAL_MEM (${MEM_PERCENT}%)"
else
    check_fail "Memoria crítica: $USED_MEM / $TOTAL_MEM (${MEM_PERCENT}%)"
fi

# Disco
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
DISK_AVAIL=$(df -h / | awk 'NR==2 {print $4}')

if [ "$DISK_USAGE" -lt 80 ]; then
    check_ok "Disco: ${DISK_USAGE}% usado (${DISK_AVAIL} disponible)"
elif [ "$DISK_USAGE" -lt 90 ]; then
    check_warn "Disco: ${DISK_USAGE}% usado (${DISK_AVAIL} disponible)"
else
    check_fail "Disco crítico: ${DISK_USAGE}% usado"
fi

# CPU Load
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
CPU_CORES=$(nproc)
print_info "Load average: $LOAD_AVG (cores: $CPU_CORES)"

# Uptime
UPTIME=$(uptime -p 2>/dev/null || uptime | awk '{print $3, $4}')
print_info "Uptime: $UPTIME"

echo ""

################################################################################
# RESUMEN FINAL
################################################################################

print_header "📊 RESUMEN"

PASS_PERCENT=$((PASSED_CHECKS * 100 / TOTAL_CHECKS))

echo -e "Total de checks:     ${BOLD}$TOTAL_CHECKS${NC}"
echo -e "Exitosos:            ${GREEN}$PASSED_CHECKS${NC}"
echo -e "Advertencias:        ${YELLOW}$WARNING_CHECKS${NC}"
echo -e "Fallidos:            ${RED}$FAILED_CHECKS${NC}"
echo -e "Porcentaje exitoso:  ${BOLD}${PASS_PERCENT}%${NC}"

echo ""

# Estado general
if [ "$FAILED_CHECKS" -eq 0 ] && [ "$WARNING_CHECKS" -eq 0 ]; then
    echo -e "${GREEN}${BOLD}✓ SISTEMA COMPLETAMENTE SALUDABLE${NC}"
    EXIT_CODE=0
elif [ "$FAILED_CHECKS" -eq 0 ]; then
    echo -e "${YELLOW}${BOLD}⚠ SISTEMA OPERATIVO CON ADVERTENCIAS${NC}"
    EXIT_CODE=1
elif [ "$FAILED_CHECKS" -lt 3 ]; then
    echo -e "${YELLOW}${BOLD}⚠ SISTEMA DEGRADADO${NC}"
    EXIT_CODE=2
else
    echo -e "${RED}${BOLD}✗ SISTEMA CON PROBLEMAS CRÍTICOS${NC}"
    EXIT_CODE=3
fi

echo ""
print_info "Log guardado en: $LOG_FILE"
echo ""

exit $EXIT_CODE
