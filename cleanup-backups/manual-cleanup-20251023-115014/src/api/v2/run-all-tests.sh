#!/bin/bash
# Test Runner - Ejecuta todas las pruebas del sistema v2

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}                     SISTEMA DE AUDIO V2 - TEST SUITE COMPLETO                   ${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

# Variables para tracking
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
TEST_RESULTS=""

# Función para ejecutar un test
run_test() {
    local test_name=$1
    local test_file=$2
    
    echo -e "${YELLOW}▶ Ejecutando: ${test_name}${NC}"
    echo "────────────────────────────────────────────────────────────────────"
    
    if [ -f "$test_file" ]; then
        php "$test_file"
        exit_code=$?
        
        if [ $exit_code -eq 0 ]; then
            echo -e "${GREEN}✅ ${test_name} - COMPLETADO${NC}\n"
            ((PASSED_TESTS++))
            TEST_RESULTS="${TEST_RESULTS}${GREEN}✓${NC} ${test_name}\n"
        else
            echo -e "${RED}❌ ${test_name} - FALLÓ${NC}\n"
            ((FAILED_TESTS++))
            TEST_RESULTS="${TEST_RESULTS}${RED}✗${NC} ${test_name}\n"
        fi
    else
        echo -e "${RED}❌ Archivo no encontrado: ${test_file}${NC}\n"
        ((FAILED_TESTS++))
        TEST_RESULTS="${TEST_RESULTS}${RED}✗${NC} ${test_name} (archivo no encontrado)\n"
    fi
    
    ((TOTAL_TESTS++))
    echo ""
}

# Verificar prerequisitos
echo -e "${YELLOW}🔍 Verificando prerequisitos...${NC}"

# Verificar FFmpeg
if command -v ffmpeg &> /dev/null; then
    echo -e "${GREEN}✓${NC} FFmpeg instalado ($(ffmpeg -version 2>&1 | head -n1 | cut -d' ' -f3))"
else
    echo -e "${RED}✗${NC} FFmpeg no encontrado"
fi

# Verificar PHP
if command -v php &> /dev/null; then
    echo -e "${GREEN}✓${NC} PHP instalado ($(php -v | head -n1 | cut -d' ' -f2))"
else
    echo -e "${RED}✗${NC} PHP no encontrado"
fi

# Verificar directorio de logs
if [ -d "/var/www/casa/src/api/v2/logs" ]; then
    echo -e "${GREEN}✓${NC} Directorio de logs existe"
else
    echo -e "${YELLOW}⚠${NC} Creando directorio de logs..."
    mkdir -p /var/www/casa/src/api/v2/logs
fi

# Verificar directorio temporal
if [ -d "/var/www/casa/src/api/v2/temp" ]; then
    echo -e "${GREEN}✓${NC} Directorio temporal existe"
else
    echo -e "${YELLOW}⚠${NC} Creando directorio temporal..."
    mkdir -p /var/www/casa/src/api/v2/temp
fi

echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

# Ejecutar tests individuales
run_test "Audio Processor (Normalización LUFS)" "/var/www/casa/src/api/v2/tests/test-audio-processor.php"
run_test "Rate Limiter (Control de APIs)" "/var/www/casa/src/api/v2/tests/test-rate-limiter.php"
run_test "Integración Completa" "/var/www/casa/src/api/v2/tests/test-integration.php"

# Mostrar resumen
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}                                RESUMEN GENERAL                                  ${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo -e "${TEST_RESULTS}"

echo -e "\n${YELLOW}📊 Estadísticas:${NC}"
echo "   Total de test suites: $TOTAL_TESTS"
echo -e "   ${GREEN}Pasados: $PASSED_TESTS${NC}"
echo -e "   ${RED}Fallidos: $FAILED_TESTS${NC}"

PERCENTAGE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
echo "   Porcentaje de éxito: ${PERCENTAGE}%"

echo ""

if [ $PERCENTAGE -eq 100 ]; then
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║            🎉 ¡TODOS LOS TESTS PASARON EXITOSAMENTE! 🎉           ║${NC}"
    echo -e "${GREEN}║           El sistema v2 está completamente funcional              ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
elif [ $PERCENTAGE -ge 66 ]; then
    echo -e "${YELLOW}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║              ⚠️  SISTEMA PARCIALMENTE FUNCIONAL ⚠️                 ║${NC}"
    echo -e "${YELLOW}║         La mayoría de componentes funcionan correctamente         ║${NC}"
    echo -e "${YELLOW}╚═══════════════════════════════════════════════════════════════════╝${NC}"
else
    echo -e "${RED}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                ❌ SISTEMA REQUIERE ATENCIÓN ❌                    ║${NC}"
    echo -e "${RED}║          Varios componentes críticos están fallando               ║${NC}"
    echo -e "${RED}╚═══════════════════════════════════════════════════════════════════╝${NC}"
fi

echo ""

# Generar reporte
REPORT_FILE="/var/www/casa/src/api/v2/tests/test-report-$(date +%Y%m%d-%H%M%S).txt"
echo "Sistema de Audio v2 - Reporte de Tests" > "$REPORT_FILE"
echo "Fecha: $(date)" >> "$REPORT_FILE"
echo "----------------------------------------" >> "$REPORT_FILE"
echo "Total tests: $TOTAL_TESTS" >> "$REPORT_FILE"
echo "Pasados: $PASSED_TESTS" >> "$REPORT_FILE"
echo "Fallidos: $FAILED_TESTS" >> "$REPORT_FILE"
echo "Porcentaje: ${PERCENTAGE}%" >> "$REPORT_FILE"

echo -e "${BLUE}📄 Reporte guardado en: ${REPORT_FILE}${NC}"
echo ""

exit $FAILED_TESTS