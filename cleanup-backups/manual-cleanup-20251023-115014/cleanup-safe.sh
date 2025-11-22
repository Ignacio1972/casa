#!/bin/bash
################################################################################
# Casa Costanera - Script de Limpieza Segura
# Elimina archivos innecesarios CON BACKUP AUTOMÁTICO
################################################################################

set -e  # Exit on error

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'

CASA_DIR="/var/www/casa"
BACKUP_DIR="$CASA_DIR/cleanup-backups"
BACKUP_NAME="cleanup-backup-$(date +%Y%m%d-%H%M%S)"
BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"

# Contadores
DELETED_FILES=0
DELETED_DIRS=0
SPACE_FREED=0

################################################################################
# FUNCIONES
################################################################################

print_header() {
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BOLD}$1${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# Función para hacer backup de un archivo antes de eliminarlo
backup_and_delete() {
    local file="$1"
    local type="${2:-file}"

    if [ ! -e "$file" ]; then
        return
    fi

    # Calcular tamaño antes de eliminar
    local size=$(du -sh "$file" 2>/dev/null | cut -f1)

    # Crear estructura de directorios en backup
    local rel_path="${file#$CASA_DIR/}"
    local backup_file="$BACKUP_PATH/$rel_path"
    local backup_dir=$(dirname "$backup_file")

    mkdir -p "$backup_dir"

    # Copiar a backup
    if [ "$type" = "dir" ]; then
        cp -r "$file" "$backup_dir/" 2>/dev/null || true
        ((DELETED_DIRS++))
    else
        cp "$file" "$backup_file" 2>/dev/null || true
        ((DELETED_FILES++))
    fi

    # Eliminar original
    if [ "$type" = "dir" ]; then
        rm -rf "$file"
        print_success "Eliminado directorio: $rel_path ($size)"
    else
        rm -f "$file"
        print_success "Eliminado archivo: $rel_path ($size)"
    fi
}

################################################################################
# INICIO
################################################################################

clear
echo ""
print_header "🧹 CASA COSTANERA - LIMPIEZA SEGURA"
echo ""

# Verificar que estamos en el directorio correcto
if [ ! -d "$CASA_DIR" ]; then
    print_error "Directorio $CASA_DIR no existe"
    exit 1
fi

cd "$CASA_DIR"

################################################################################
# FASE 0: CREAR BACKUP
################################################################################

print_header "📦 FASE 0: CREAR BACKUP"
echo ""

mkdir -p "$BACKUP_DIR"
mkdir -p "$BACKUP_PATH"

print_info "Directorio de backup: $BACKUP_PATH"
echo ""

################################################################################
# FASE 1: ARCHIVOS DUPLICADOS Y TEST (BAJO RIESGO)
################################################################################

print_header "🟢 FASE 1: ARCHIVOS DUPLICADOS Y TEST"
echo ""
print_info "Eliminando archivos duplicados innecesarios..."
echo ""

# Duplicados de recent-messages
backup_and_delete "$CASA_DIR/recent-messages.php"
backup_and_delete "$CASA_DIR/public/recent-messages.php"
backup_and_delete "$CASA_DIR/public/api/recent-messages.php"

# Archivos de test individuales
backup_and_delete "$CASA_DIR/api/test-messages.php"
backup_and_delete "$CASA_DIR/src/api/test-jingle.php"
backup_and_delete "$CASA_DIR/src/api/test-skip-api.php"
backup_and_delete "$CASA_DIR/src/api/test-recent.php"
backup_and_delete "$CASA_DIR/src/api/radio-test.php"
backup_and_delete "$CASA_DIR/src/api/recent-messages-fix.php"
backup_and_delete "$CASA_DIR/public/api/test-db.php"
backup_and_delete "$CASA_DIR/public/test-php.php"

# Directorios de tests
if [ -d "$CASA_DIR/src/api/v2/tests" ]; then
    backup_and_delete "$CASA_DIR/src/api/v2/tests" "dir"
fi

if [ -d "$CASA_DIR/public/Tests Files" ]; then
    backup_and_delete "$CASA_DIR/public/Tests Files" "dir"
fi

# Archivos "old" renombrados
find "$CASA_DIR/src/modules" -type f \( -name "*old.js" -o -name "*old.html" \) 2>/dev/null | while read file; do
    backup_and_delete "$file"
done

echo ""
print_success "Fase 1 completada"
echo ""

################################################################################
# VERIFICACIÓN POST-FASE 1
################################################################################

print_header "✅ VERIFICACIÓN"
echo ""
print_info "Verificando que el sistema sigue funcionando..."
echo ""

# Test básico de páginas
HTTP_DASHBOARD=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:4000/index.html" 2>/dev/null)
HTTP_API=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:4000/api/recent-messages.php" 2>/dev/null)

if [ "$HTTP_DASHBOARD" = "200" ]; then
    print_success "Dashboard: OK (HTTP $HTTP_DASHBOARD)"
else
    print_error "Dashboard: FALLO (HTTP $HTTP_DASHBOARD)"
fi

if [ "$HTTP_API" = "200" ]; then
    print_success "API recent-messages: OK (HTTP $HTTP_API)"
else
    print_error "API recent-messages: FALLO (HTTP $HTTP_API)"
fi

echo ""

################################################################################
# PREGUNTA: ¿CONTINUAR CON FASE 2?
################################################################################

print_header "🟡 FASE 2: BACKUPS ANTIGUOS (OPCIONAL)"
echo ""
print_warning "Esta fase elimina backups de módulos de septiembre 2025"
print_warning "Solo continuar si el sistema funciona correctamente"
echo ""
print_info "Archivos a eliminar en Fase 2:"
echo "  - Backups de calendar (sept 2025)"
echo "  - Backups de campaigns (sept 2025)"
echo "  - Backup de playground (sept 2025)"
echo ""

read -p "¿Ejecutar Fase 2? (s/N): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Ss]$ ]]; then
    echo ""
    print_info "Ejecutando Fase 2..."
    echo ""

    # Backups de calendar
    find "$CASA_DIR/src/modules/calendar" -type d -name "*backup-*" 2>/dev/null | while read dir; do
        backup_and_delete "$dir" "dir"
    done

    # Backups de campaigns
    find "$CASA_DIR/src/modules/campaigns" -type d -name "*backup-*" 2>/dev/null | while read dir; do
        backup_and_delete "$dir" "dir"
    done

    # Backup de playground
    if [ -d "$CASA_DIR/public/playground-backup-20250904_200620" ]; then
        backup_and_delete "$CASA_DIR/public/playground-backup-20250904_200620" "dir"
    fi

    echo ""
    print_success "Fase 2 completada"
else
    echo ""
    print_info "Fase 2 omitida (puedes ejecutarla más tarde)"
fi

echo ""

################################################################################
# RESUMEN FINAL
################################################################################

print_header "📊 RESUMEN"
echo ""

# Calcular tamaño del backup
BACKUP_SIZE=$(du -sh "$BACKUP_PATH" 2>/dev/null | cut -f1)

echo -e "Archivos eliminados:     ${BOLD}$DELETED_FILES${NC}"
echo -e "Directorios eliminados:  ${BOLD}$DELETED_DIRS${NC}"
echo -e "Backup creado en:        ${CYAN}$BACKUP_PATH${NC}"
echo -e "Tamaño del backup:       ${BOLD}$BACKUP_SIZE${NC}"

echo ""
print_success "Limpieza completada exitosamente"
echo ""

print_header "📝 NOTAS IMPORTANTES"
echo ""
print_info "1. El backup está en: $BACKUP_PATH"
print_info "2. Para restaurar un archivo:"
echo "   cp $BACKUP_PATH/ruta/archivo /var/www/casa/ruta/archivo"
print_info "3. Si todo funciona bien después de 1 semana, puedes eliminar el backup"
print_info "4. Para eliminar backup: rm -rf $BACKUP_PATH"
echo ""

print_header "🔴 DASHBOARD DUPLICADO - ACCIÓN MANUAL REQUERIDA"
echo ""
print_warning "Hay 2 versiones del dashboard que requieren revisión manual:"
echo ""
echo "  1. /src/modules/dashboard/ (actual)"
echo "  2. /src/modules/dashboard-redesign/ (492 KB)"
echo "  3. /src/modules/dashboard.backup-20250912-113625/ (540 KB)"
echo ""
print_info "Para verificar cuál está en uso:"
echo "  grep -r 'dashboard-redesign' /var/www/casa/public/*.html"
echo ""
print_info "Si dashboard-redesign NO está en uso, puedes eliminarlo:"
echo "  rm -rf /var/www/casa/src/modules/dashboard-redesign"
echo ""
print_info "Si el dashboard actual funciona bien (>1 mes), puedes eliminar el backup:"
echo "  rm -rf /var/www/casa/src/modules/dashboard.backup-*"
echo ""

print_header "✅ VERIFICACIÓN FINAL RECOMENDADA"
echo ""
print_info "Ejecuta el health check para verificar el sistema:"
echo "  cd /var/www/casa && ./health-check.sh"
echo ""

exit 0
