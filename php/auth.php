<?php
// auth.php - Lógica de autenticación para el Gestor de Licencias
// Incluye: inicio de sesión, cierre de sesión, verificación de sesión activa y renderizado del formulario de login.
//
// Este archivo debe ser incluido al inicio de cualquier script que requiera autenticación.

// Protección contra inclusiones múltiples
if (defined('AUTH_INCLUDED')) {
    return;
}
define('AUTH_INCLUDED', true);

// -------------------------------------------------------------------
// Renderizar formulario de login (declarado primero para evitar errores)
// -------------------------------------------------------------------
/**
 * Muestra el formulario de inicio de sesión y termina la ejecución.
 * @param string $error_msg Mensaje de error a mostrar (si existe)
 * @param mysqli $conn Conexión activa a la base de datos (se cierra al terminar)
 */
function renderizarFormularioLogin(string $error_msg, mysqli $conn): void {
    $error_html = '';
    if (!empty($error_msg)) {
        $error_html = "<p class='error'>" . htmlspecialchars($error_msg) . "</p>";
    }
    $actionUrl = htmlspecialchars($_SERVER['PHP_SELF']);
    
    echo <<<HTML
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Iniciar Sesión - Gestor de Licencias</title>
    <style>
        body { font-family: sans-serif; background-color: #343a40; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center; width: 350px; }
        .login-container h1 { color: #333; margin-bottom: 30px; }
        .login-container .input-group { margin-bottom: 20px; text-align: left; }
        .login-container label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .login-container input[type="text"], .login-container input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .login-container input[type="submit"] { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1.1em; font-weight: bold; }
        .login-container input[type="submit"]:hover { background-color: #0056b3; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
    </head><body>
    <div class="login-container">
        <h1>Gestor de Licencias</h1>
        {$error_html}
        <form action="{$actionUrl}" method="POST">
            <div class="input-group">
                <label for="login_usuario">Usuario:</label>
                <input type="text" id="login_usuario" name="login_usuario" required autofocus>
            </div>
            <div class="input-group">
                <label for="login_password">Contraseña:</label>
                <input type="password" id="login_password" name="login_password" required>
            </div>
            <input type="submit" value="Iniciar Sesión">
        </form>
    </div>
    </body></html>
HTML;
    $conn->close();
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Habilitar la visualización de errores solo para depuración (puedes comentar en producción)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Conexión a la base de datos (ajusta la ruta si es necesario)
require_once(__DIR__ . '/../apis/Conectar_BD.php');
try {
    $conn = Conectar_BD();
} catch (Exception $e) {
    error_log('Error de conexión a la BD: ' . $e->getMessage());
    die('No se puede establecer conexión con la base de datos.');
}

// -------------------------------------------------------------------
// Procesar logout (cierre de sesión)
// -------------------------------------------------------------------
if (isset($_GET['Accion']) && $_GET['Accion'] === 'Logout') {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// -------------------------------------------------------------------
// Procesar intento de login
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_usuario'])) {
    $usuario = $_POST['login_usuario'];
    $password = $_POST['login_password'];
    $error_login = '';

    if (empty($usuario) || empty($password)) {
        $error_login = 'El usuario y la contraseña son obligatorios.';
    } else {
        // Consulta de usuario y verificación de contraseña
        $stmt = $conn->prepare("SELECT PK_Usuario, Usuario, Password, Nombre, Perfil FROM Usuarios WHERE Usuario = ? AND Baja IS NULL");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
            
            if (password_verify($password, $user_data['Password'])) {
                session_regenerate_id(true); 
                $_SESSION['pk_usuario'] = $user_data['PK_Usuario'];
                $_SESSION['usuario'] = $user_data['Usuario'];
                $_SESSION['nombre'] = $user_data['Nombre'];
                $_SESSION['perfil'] = $user_data['Perfil'];
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }        

        $error_login = 'Usuario o contraseña incorrectos.';
    }
    renderizarFormularioLogin($error_login, $conn);
}

// -------------------------------------------------------------------
// Verificar sesión activa
// -------------------------------------------------------------------
if (session_id() == '' || !isset($_SESSION['usuario'])) {
    renderizarFormularioLogin('', $conn);
} 