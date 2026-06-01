<?php
// =================================================================
// GUARDIÁN DE PRIVACIDAD: documento_descargar.php (PASO 5 - FIX)
// =================================================================
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "conexion.php";

$archivo = isset($_GET['archivo']) ? trim($_GET['archivo']) : '';

if (empty($archivo)) {
    die("Error: Parámetro de archivo ausente.");
}

// 🔐 REGLA DE INCÓGNITO: Validamos si se recibe un token seguro (ID de sesión) desde el LocalStorage
if (!isset($_GET['token_seguro']) || empty($_GET['token_seguro'])) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>Acceso Denegado: No tienes una sesión activa en este navegador.</h1>";
    exit;
}

$archivo_limpio = basename($archivo);
$ruta_completa = "./uploads_dictamenes/" . $archivo_limpio;

if (!file_exists($ruta_completa)) {
    header("HTTP/1.1 404 Not Found");
    die("El expediente solicitado no existe en el servidor.");
}

$ext = strtolower(pathinfo($archivo_limpio, PATHINFO_EXTENSION));
switch ($ext) {
    case "pdf":  $mime = "application/pdf"; break;
    case "jpg":
    case "jpeg": $mime = "image/jpeg"; break;
    case "png":  $mime = "image/png"; break;
    default:     $mime = "application/octet-stream"; break;
}

header("Content-Type: $mime");
header("Content-Length: " . filesize($ruta_completa));
header("Content-Disposition: inline; filename=\"$archivo_limpio\"");
readfile($ruta_completa);
exit;
?>