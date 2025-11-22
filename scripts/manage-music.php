#!/usr/bin/php
<?php
/**
 * Script para gestionar música del sistema de jingles
 * Permite agregar, eliminar y listar canciones de forma segura
 */

// Colores para output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[0;33m");
define('BLUE', "\033[0;34m");
define('NC', "\033[0m"); // No Color

// Directorio de música
define('MUSIC_DIR', '/var/www/casa/public/audio/music/');

// Función para imprimir con color
function println($message, $color = NC) {
    echo $color . $message . NC . PHP_EOL;
}

// Función para listar canciones
function listMusic() {
    println("\n=== CANCIONES DISPONIBLES ===", BLUE);

    $files = glob(MUSIC_DIR . "*.mp3");

    if (empty($files)) {
        println("No hay canciones disponibles", YELLOW);
        return;
    }

    $total = 0;
    foreach ($files as $file) {
        $name = basename($file);
        $size = round(filesize($file) / 1024 / 1024, 2);
        $total++;
        println("  • $name ($size MB)", GREEN);
    }

    println("\nTotal: $total canciones", BLUE);
}

// Función para agregar canción
function addMusic($sourcePath) {
    if (!file_exists($sourcePath)) {
        println("ERROR: El archivo no existe: $sourcePath", RED);
        return false;
    }

    // Verificar que es un archivo MP3
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $sourcePath);
    finfo_close($finfo);

    if (!in_array($mimeType, ['audio/mpeg', 'audio/mp3'])) {
        println("ERROR: El archivo no es un MP3 válido (tipo: $mimeType)", RED);
        return false;
    }

    $filename = basename($sourcePath);
    $destPath = MUSIC_DIR . $filename;

    // Verificar si ya existe
    if (file_exists($destPath)) {
        println("ADVERTENCIA: El archivo ya existe: $filename", YELLOW);
        echo "¿Desea reemplazarlo? (s/n): ";
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) !== 's') {
            println("Operación cancelada", YELLOW);
            return false;
        }
    }

    // Copiar archivo
    if (copy($sourcePath, $destPath)) {
        chmod($destPath, 0644);
        println("✓ Canción agregada exitosamente: $filename", GREEN);

        // Verificar con ffmpeg que es válido
        exec("ffmpeg -i \"$destPath\" -f null - 2>&1", $output, $returnVar);
        if ($returnVar !== 0) {
            println("ADVERTENCIA: El archivo podría tener problemas de codificación", YELLOW);
        }

        return true;
    } else {
        println("ERROR: No se pudo copiar el archivo", RED);
        return false;
    }
}

// Función para eliminar canción
function removeMusic($filename) {
    $filePath = MUSIC_DIR . $filename;

    if (!file_exists($filePath)) {
        println("ERROR: El archivo no existe: $filename", RED);
        return false;
    }

    echo "¿Está seguro que desea eliminar '$filename'? (s/n): ";
    $answer = trim(fgets(STDIN));
    if (strtolower($answer) !== 's') {
        println("Operación cancelada", YELLOW);
        return false;
    }

    if (unlink($filePath)) {
        println("✓ Canción eliminada exitosamente: $filename", GREEN);
        return true;
    } else {
        println("ERROR: No se pudo eliminar el archivo", RED);
        return false;
    }
}

// Función para reiniciar servicios
function restartServices() {
    println("\nReiniciando servicios...", YELLOW);

    // Reiniciar PHP-FPM
    exec("sudo systemctl restart php8.1-fpm 2>&1", $output, $returnVar);
    if ($returnVar === 0) {
        println("✓ PHP-FPM reiniciado", GREEN);
    } else {
        println("✗ Error reiniciando PHP-FPM", RED);
        return false;
    }

    // Limpiar archivos temporales antiguos (más de 7 días)
    $tempDir = '/var/www/casa/src/api/temp/';
    exec("find $tempDir -type f -name '*.mp3' -mtime +7 -delete 2>&1", $output, $returnVar);
    if ($returnVar === 0) {
        println("✓ Archivos temporales limpiados", GREEN);
    }

    return true;
}

// Función para validar todas las canciones
function validateMusic() {
    println("\n=== VALIDANDO CANCIONES ===", BLUE);

    $files = glob(MUSIC_DIR . "*.mp3");
    $valid = 0;
    $invalid = [];

    foreach ($files as $file) {
        $name = basename($file);
        echo "Validando: $name... ";

        // Verificar con ffmpeg
        exec("ffmpeg -i \"$file\" -f null - 2>&1", $output, $returnVar);
        if ($returnVar === 0) {
            println("✓", GREEN);
            $valid++;
        } else {
            println("✗", RED);
            $invalid[] = $name;
        }
    }

    println("\nResultados:", BLUE);
    println("  Válidas: $valid", GREEN);
    if (!empty($invalid)) {
        println("  Con problemas: " . count($invalid), RED);
        foreach ($invalid as $file) {
            println("    - $file", RED);
        }
    }
}

// Función principal
function main($argv) {
    if (count($argv) < 2) {
        showHelp();
        exit(1);
    }

    $command = $argv[1];
    $needsRestart = false;

    switch ($command) {
        case 'list':
        case 'listar':
            listMusic();
            break;

        case 'add':
        case 'agregar':
            if (!isset($argv[2])) {
                println("ERROR: Debe especificar el archivo a agregar", RED);
                println("Uso: php manage-music.php add /ruta/al/archivo.mp3", YELLOW);
                exit(1);
            }
            if (addMusic($argv[2])) {
                $needsRestart = true;
            }
            break;

        case 'remove':
        case 'eliminar':
            if (!isset($argv[2])) {
                println("ERROR: Debe especificar el archivo a eliminar", RED);
                println("Uso: php manage-music.php remove nombre.mp3", YELLOW);
                exit(1);
            }
            if (removeMusic($argv[2])) {
                $needsRestart = true;
            }
            break;

        case 'validate':
        case 'validar':
            validateMusic();
            break;

        case 'restart':
        case 'reiniciar':
            restartServices();
            break;

        default:
            println("Comando no reconocido: $command", RED);
            showHelp();
            exit(1);
    }

    // Si se hicieron cambios, reiniciar servicios
    if ($needsRestart) {
        println("\nLos cambios requieren reiniciar servicios", YELLOW);
        echo "¿Desea reiniciar ahora? (s/n): ";
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) === 's') {
            restartServices();
        } else {
            println("IMPORTANTE: Debe ejecutar 'php manage-music.php restart' para aplicar los cambios", YELLOW);
        }
    }

    println("\n✓ Operación completada", GREEN);
}

// Función para mostrar ayuda
function showHelp() {
    println("\n=== GESTOR DE MÚSICA PARA JINGLES ===", BLUE);
    println("\nUso: php manage-music.php [comando] [opciones]", YELLOW);
    println("\nComandos disponibles:");
    println("  list, listar         - Mostrar todas las canciones disponibles");
    println("  add, agregar         - Agregar una nueva canción");
    println("  remove, eliminar     - Eliminar una canción");
    println("  validate, validar    - Validar integridad de todas las canciones");
    println("  restart, reiniciar   - Reiniciar servicios para aplicar cambios");
    println("\nEjemplos:");
    println("  php manage-music.php list");
    println("  php manage-music.php add /home/user/nueva_cancion.mp3");
    println("  php manage-music.php remove vieja_cancion.mp3");
    println("  php manage-music.php validate");
    println("  php manage-music.php restart");
}

// Verificar permisos
if (!is_dir(MUSIC_DIR)) {
    println("ERROR: El directorio de música no existe: " . MUSIC_DIR, RED);
    exit(1);
}

if (!is_writable(MUSIC_DIR)) {
    println("ERROR: Sin permisos de escritura en: " . MUSIC_DIR, RED);
    println("Ejecute: sudo chown -R www-data:www-data " . MUSIC_DIR, YELLOW);
    exit(1);
}

// Ejecutar
main($argv);
?>