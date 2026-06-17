<?php
ini_set('display_errors', 0);
require_once "conexion.php";

// 🚀 Sincronizado con la acción exacta del formulario modificado: 'reportar_sismo_emergencia'
if (isset($_POST['accion']) && $_POST['accion'] === 'reportar_sismo_emergencia') {
    
    // Captura limpia y segura de los nuevos campos del formulario
    $empresa       = $conexion->real_escape_string($_POST['empresa_cod']);
    $zona_afectada = $conexion->real_escape_string($_POST['zona_afectada']);
    $clasificacion = $conexion->real_escape_string($_POST['clasificacion']);
    $comentarios   = $conexion->real_escape_string($_POST['comentarios']);
    $lat           = $conexion->real_escape_string($_POST['latitud']);
    $lng           = $conexion->real_escape_string($_POST['longitud']);

    // Configuración segura del directorio de subida de evidencias
    $dest_path = "./uploads_sismos/";
    if (!is_dir($dest_path)) { 
        mkdir($dest_path, 0777, true); 
    }

    // Validación y procesamiento del archivo adjunto
    if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        $fileName = $_FILES['evidencia']['name'];
        // Creamos un nombre único usando el código de la empresa y la marca de tiempo
        $nuevo_nombre = "SISMO_" . $empresa . "_" . time() . "." . pathinfo($fileName, PATHINFO_EXTENSION);
        
        if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $dest_path . $nuevo_nombre)) {
            
            // 🚀 QUERY ACTUALIZADA: Incluye la columna 'clasificacion' para guardar si fue mueble o inmueble
            $query = "INSERT INTO reportes_sismo (empresa_cod, zona, clasificacion, descripcion, ruta_archivo, latitud, longitud) 
                      VALUES ('$empresa', '$zona_afectada', '$clasificacion', '$comentarios', '$nuevo_nombre', '$lat', '$lng')";
            
            if ($conexion->query($query)) {
                echo json_encode(["status" => "success", "message" => "¡Reporte de siniestro levantado exitosamente con ubicación GPS!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al registrar el reporte en la base de datos: " . $conexion->error]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "No se pudo mover el archivo de evidencia al servidor."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Archivo de evidencia no recibido o dañado."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Acción no autorizada o no reconocida."]);
}
?>