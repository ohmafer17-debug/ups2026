<?php
// =================================================================
// CONTROLADOR COMPLEMENTARIO: cliente_procesar.php (VERSIÓN FIX COMPATIBLE)
// =================================================================
ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once "conexion.php";

$inputRaw = file_get_contents("php://input");
$datos = json_decode($inputRaw, true);

$accion = isset($_POST['accion']) ? $_POST['accion'] : (isset($datos['accion']) ? $datos['accion'] : '');

// --- ACCIÓN 1: CREAR NODO OPERATIVO ---
if ($accion === 'crear_usuario_operativo') {
    $rol_ejecutor = strtolower(trim($datos['rol_ejecutor']));
    
    if ($rol_ejecutor !== 'administrador' && $rol_ejecutor !== 'consultor' && $rol_ejecutor !== 'responsable nacional' && $rol_ejecutor !== 'responsable_nacional' && $rol_ejecutor !== 'tipo 1') {
        echo json_encode(["status" => "error", "message" => "Denegado: Su rango operativo no posee permisos para crear nodos de estructura."]);
        exit;
    }

    $nombre      = $conexion->real_escape_string(trim($datos['nombre']));
    $rol_a_crear = $conexion->real_escape_string(trim($datos['rol']));
    $email       = $conexion->real_escape_string(trim($datos['email']));
    $pass        = $conexion->real_escape_string(trim($datos['pass']));
    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));

    if (empty($nombre) || empty($rol_a_crear) || empty($email) || empty($pass) || empty($empresa_cod)) {
        echo json_encode(["status" => "error", "message" => "Existen campos mandatorios incompletos."]);
        exit;
    }

    $check = $conexion->query("SELECT id FROM empresas_clientes WHERE email = '$email' LIMIT 1");
    if($check && $check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "El correo electrónico ya pertenece a un nodo registrado."]);
        exit;
    }

    $base_empresa   = explode('-', $empresa_cod)[0]; 
    $cod_unico_nodo = $base_empresa . "-" . substr(md5($email), 0, 4);

    $queryInsert = "INSERT INTO empresas_clientes (cod, nombre, email, pass, activo, rol) 
                    VALUES ('$cod_unico_nodo', '$nombre', '$email', '$pass', 1, '$rol_a_crear')";

    if ($conexion->query($queryInsert)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conexion->error]);
    }
    exit;
}

// --- ACCIÓN 2: SUBIR O ACTUALIZAR DOCUMENTO (CON PARCHADO DE FECHA NOT NULL) ---
if ($accion === 'subir_documento') {
    $rol_ejecutor = strtolower(trim($_POST['rol_ejecutor']));

    if ($rol_ejecutor === 'tipo 2' || $rol_ejecutor === 'tipo 3') {
        echo json_encode(["status" => "error", "message" => "Denegado: Su rango operativo no está facultado para subir archivos."]);
        exit;
    }

    $empresa_cod = $conexion->real_escape_string(trim($_POST['empresa_cod']));
    $tipo_doc    = $conexion->real_escape_string(trim($_POST['tipo_doc']));
    $nombre_p    = $conexion->real_escape_string(trim($_POST['nombre_personalizado']));
    
    // Captura de vencimiento
    $vencimiento = isset($_POST['fecha_vencimiento']) ? trim($_POST['fecha_vencimiento']) : '';
    
    // 🚀 SOLUCIÓN DE COMPATIBILIDAD: Si viene vacío, en vez de mandar NULL (que rompe tu BD), mandamos '0000-00-00'
    $vencimiento_sql = empty($vencimiento) ? "'0000-00-00'" : "'" . $conexion->real_escape_string($vencimiento) . "'";
    
    $fecha_subida_manual = isset($_POST['fecha_subida']) ? trim($_POST['fecha_subida']) : '';
    if (!empty($fecha_subida_manual) && empty($vencimiento)) {
        $nombre_p .= " [Reg: " . $fecha_subida_manual . "]";
    }

    $es_actualizacion = isset($_POST['es_actualizacion']) ? $_POST['es_actualizacion'] : 'no';

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['archivo']['tmp_name'];
        $fileName    = $_FILES['archivo']['name'];
        $fileSize    = $_FILES['archivo']['size'];
        
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $extensiones_permitidas)) {
            echo json_encode(["status" => "error", "message" => "Formato no válido. Solo se admiten PDFs o Imágenes."]);
            exit;
        }

        if ($fileSize > 52428800) {
            echo json_encode(["status" => "error", "message" => "El archivo supera el límite de 50MB permitido."]);
            exit;
        }

        $dest_path = "./uploads_dictamenes/";
        if (!is_dir($dest_path)) { mkdir($dest_path, 0777, true); }

        $nuevo_nombre_fisico = md5(time() . $fileName) . "." . $ext;
        $ruta_final = $dest_path . $nuevo_nombre_fisico;

        if (move_uploaded_file($fileTmpPath, $ruta_final)) {
            
            if ($es_actualizacion === 'si') {
                $checkDoc = $conexion->query("SELECT id, nombre_archivo_fisico, nombre_personalizado, fecha_vencimiento FROM documentos_pc WHERE empresa_cod = '$empresa_cod' AND tipo_doc = '$tipo_doc' LIMIT 1");
                if ($checkDoc && $checkDoc->num_rows > 0) {
                    $docViejo = $checkDoc->fetch_assoc();
                    $dId = $docViejo['id'];
                    $vNom = $conexion->real_escape_string($docViejo['nombre_personalizado']);
                    
                    // Adaptado también para heredar comodines en el historial si es necesario
                    $vFec_val = is_null($docViejo['fecha_vencimiento']) || empty($docViejo['fecha_vencimiento']) ? "'0000-00-00'" : "'".$docViejo['fecha_vencimiento']."'";
                    $vArc = $docViejo['nombre_archivo_fisico'];
                    
                    $conexion->query("INSERT INTO historial_documentos (documento_id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, nombre_archivo_fisico) VALUES ($dId, '$empresa_cod', '$tipo_doc', '$vNom', $vFec_val, '$vArc')");
                    
                    $conexion->query("UPDATE documentos_pc SET nombre_personalizado = '$nombre_p', fecha_vencimiento = $vencimiento_sql, nombre_archivo_fisico = '$nuevo_nombre_fisico', estatus = 1 WHERE id = $dId");
                    
                    echo json_encode(["status" => "success", "message" => "¡Documento actualizado e historial registrado con éxito!"]);
                    exit;
                }
            }

            // Inserción inyectando la cadena '0000-00-00' de forma segura
            $queryDoc = "INSERT INTO documentos_pc (empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, estatus, nombre_archivo_fisico) 
                         VALUES ('$empresa_cod', '$tipo_doc', '$nombre_p', $vencimiento_sql, 1, '$nuevo_nombre_fisico')";
            
            if ($conexion->query($queryDoc)) {
                echo json_encode(["status" => "success", "message" => "¡Archivo guardado e indexado con éxito!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al registrar en BD: " . $conexion->error]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Error físico al mover el archivo."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No se subió ningún archivo."]);
    }
    exit;
}

// --- ACCIÓN 3: LISTAR DOCUMENTOS ---
if ($accion === 'listar_documentos') {
    $rol_ejecutor = strtolower(trim($datos['rol_ejecutor']));

    if ($rol_ejecutor === 'administrador') {
        echo json_encode(["status" => "error", "message" => "Acceso restringido por privacidad de datos."]);
        exit;
    }

    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $base_empresa = explode('-', $empresa_cod)[0];

    $res = $conexion->query("SELECT id, tipo_doc, nombre_personalizado, fecha_vencimiento, estatus, nombre_archivo_fisico FROM documentos_pc WHERE empresa_cod = '$base_empresa' OR empresa_cod LIKE '$base_empresa-%' ORDER BY id DESC");
    $documentos = [];
    if($res) { while($row = $res->fetch_assoc()) { $documentos[] = $row; } }
    echo json_encode(["status" => "success", "data" => $documentos]);
    exit;
}

// --- ACCIÓN 4: SUSPENDER DOCUMENTO ---
if ($accion === 'suspender_documento') {
    $rol_ejecutor = strtolower(trim($datos['rol_ejecutor']));
    if ($rol_ejecutor === 'tipo 2' || $rol_ejecutor === 'tipo 3') {
        echo json_encode(["status" => "error", "message" => "Su rango no permite modificar archivos."]); exit;
    }
    
    $id_doc = intval($datos['id_documento']);

    if ($conexion->query("UPDATE documentos_pc SET estatus = 0 WHERE id = $id_doc")) {
        echo json_encode(["status" => "success", "message" => "Archivo archivado como inactivo de manera segura."]);
    } else { echo json_encode(["status" => "error", "message" => $conexion->error]); }
    exit;
}

// --- ACCIÓN 5: VER HISTORIAL DE VERSIONES ---
if ($accion === 'ver_historial_documento') {
    $id_doc = intval($datos['id_documento']);
    $res = $conexion->query("SELECT nombre_personalizado, fecha_vencimiento, nombre_archivo_fisico, fecha_modificacion FROM historial_documentos WHERE documento_id = $id_doc ORDER BY id DESC");
    $historial = [];
    if($res) { while($row = $res->fetch_assoc()) { $historial[] = $row; } }
    echo json_encode(["status" => "success", "data" => $historial]);
    exit;
}

// --- ACCIÓN 6: LISTAR USUARIOS ---
if ($accion === 'listar_usuarios') {
    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $base_empresa = explode('-', $empresa_cod)[0];
    
    $res = $conexion->query("SELECT nombre, email, rol FROM empresas_clientes WHERE cod = '$base_empresa' OR cod LIKE '$base_empresa-%' ORDER BY id DESC");
    $usuarios = [];
    if($res) { while($row = $res->fetch_assoc()) { $usuarios[] = $row; } }
    echo json_encode(["status" => "success", "data" => $usuarios]);
    exit;
}
?>