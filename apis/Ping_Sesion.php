<?php
// Configuración de errores
ini_set('display_errors', '0');
ini_set('log_errors', '1');

error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Activa o desactiva el modo debug desde aquí
define('DEBUG_MODE', false); // Cambia a false para apagar logs

require_once("Debug_Config.php");
require("Conectar_BD.php");
date_default_timezone_set("America/Guayaquil");

// Validación de la firma en la petición
require_once 'Validar_Firma.php';
try {
    $input = validarPeticion();
} catch (Exception $e) {
    http_response_code($e->getCode());
    echo json_encode([
        'Fin'     => 'Error',
        'Mensaje' => $e->getMessage()
    ]);
    exit();
}

$ping_token = $input["ping_token"] ?? '';

// Validación de token
if (!$ping_token) {
    http_response_code(400);
    echo json_encode([
        "Fin" => "Error",
        "Mensaje" => "No se recibió el token"
    ]);
    exit;
}

// Conexión a la base de datos
$mysqli = Conectar_BD();
if (!$mysqli) {
    http_response_code(500);
    echo json_encode([
        "Fin" => "Error",
        "Mensaje" => "Error al conectar a la base de datos"
    ]);
    exit;
}

// Actualizar última actividad
$fecha = date("Y-m-d H:i:s");
$sql = "UPDATE sesiones_erp SET ultima_actividad = '$fecha' WHERE ping_token = '$ping_token'";

$result = $mysqli->query($sql);

if ($result && $mysqli->affected_rows > 0) {
    echo json_encode([
        "Fin" => "OK",
        "Mensaje" => "Sesión actualizada"
    ]);
} else {
    echo json_encode([
        "Fin" => "Advertencia",
        "Mensaje" => "Token no encontrado"
    ]);
}

$mysqli->close();
?>
