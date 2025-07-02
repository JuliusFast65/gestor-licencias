<?php
session_start();

// --- Conexión a BD ---
require_once('../apis/Conectar_BD.php');
try {
    $conn = Conectar_BD();
} catch (Exception $e) {
    error_log('Error de conexión a la BD: ' . $e->getMessage());
    die('No se puede establecer conexión con la base de datos.');
}

// --- 1. PROCESAR LOGOUT ---
if (isset($_GET['accion']) && $_GET['accion'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php'); // Redirige al propio login
    exit;
}

// --- 2. PROCESAR INTENTO DE LOGIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_usuario'])) {
    $usuario = $_POST['login_usuario'];
    $password = $_POST['login_password'];
    $error_login = '';

    if (empty($usuario) || empty($password)) {
        $error_login = 'El usuario y la contraseña son obligatorios.';
    } else {
        // MODIFICADO: Añadimos 'Perfil' a la consulta
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
                // NUEVO: Guardamos el perfil en la sesión
                $_SESSION['perfil'] = $user_data['Perfil'];
                
                // Redirigimos al nuevo dashboard
                header('Location: dashboard.php');
                exit;
            }
        }        
        $error_login = 'Usuario o contraseña incorrectos.';
    }
}

// --- 3. SI YA HAY SESIÓN, REDIRIGIR AL DASHBOARD ---
if (isset($_SESSION['pk_usuario'])) {
    header('Location: dashboard.php');
    exit;
}

// --- 4. RENDERIZAR FORMULARIO DE LOGIN (solo si no hay sesión ni intento de login exitoso) ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background-color: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 300px; }
        h1 { text-align: center; color: #333; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #555; }
        input[type="text"], input[type="password"] { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.7rem; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background-color: #0056b3; }
        .error { color: #d93025; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 0.7rem; border-radius: 4px; text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Acceso al Sistema</h1>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="login_usuario">Usuario:</label>
                <input type="text" id="login_usuario" name="login_usuario" required>
            </div>
            <div class="form-group">
                <label for="login_password">Contraseña:</label>
                <input type="password" id="login_password" name="login_password" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
        <?php if (!empty($error_login)): ?>
            <div class="error"><?php echo htmlspecialchars($error_login); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>