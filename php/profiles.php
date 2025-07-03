<?php
// profiles.php - Lógica de gestión de perfiles de usuario para el Gestor de Licencias
// Incluye: dashboard principal, edición de perfil, y funciones auxiliares relacionadas.
//
// Este archivo debe ser incluido después de auth.php y companies.php.

// Protección contra inclusiones múltiples
if (defined('PROFILES_INCLUDED')) {
    return;
}
define('PROFILES_INCLUDED', true);

// -------------------------------------------------------------------
// Dashboard principal (vista inicial sin empresa seleccionada)
// -------------------------------------------------------------------
/**
 * Renderiza la página principal del dashboard cuando no hay empresa seleccionada.
 * Muestra el widget de búsqueda y un mensaje de bienvenida.
 * @param mysqli $conn Conexión activa a la base de datos
 */
if (!function_exists('renderizarDashboard')) {
function renderizarDashboard(mysqli $conn): void {
    $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8');
    
    // Usamos la misma estructura HTML base para consistencia
    echo <<<HTML
    <!DOCTYPE html><html lang="es">
    <head>
        <meta charset="UTF-8"><title>Dashboard - Gestor de Licencias</title>
        <!-- Reutilizamos algunos estilos para consistencia del mensaje flash -->
        <style>
            body { font-family: sans-serif; background-color: #f8f9fa; padding: 20px; }
            .container { max-width: 1400px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div style="background-color:#333; color:white; padding:10px 20px; text-align:right;">
            Bienvenido, <strong>{$nombreUsuario}</strong> ({$_SESSION['perfil']}) | <a href="?Accion=Mi_Perfil" style="color:white; text-decoration: underline;">Mi Perfil</a> | <a href="?Accion=Logout" style="color: #ffc107; text-decoration: none;">Cerrar Sesión</a>
        </div>
        <div class="container">
HTML;
    
    // <-- CAMBIO: Añadido bloque para mostrar el mensaje flash -->
    if (isset($_SESSION['mensaje_flash'])) {
        echo "<div style='background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin: 0 0 20px 0; border-radius: 5px;'>" 
             . htmlspecialchars($_SESSION['mensaje_flash']) 
             . "</div>";
        unset($_SESSION['mensaje_flash']);
    }

    // Se renderiza el widget de búsqueda y el mensaje inicial
    echo renderizarWidgetBusquedaYComando();
    echo "<p style='text-align:center; color:#6c757d; font-size:1.2em; margin-top:40px;'>Utilice la búsqueda para seleccionar una empresa y administrar sus licencias.</p>";
    
    echo "</div>"; // Cierre de .container
    echo renderizarScriptBusqueda(); // Incluimos el JS de búsqueda
    echo "</body></html>";
    exit;
}
}

// -------------------------------------------------------------------
// Mapeo de módulos del sistema (función auxiliar)
// -------------------------------------------------------------------
/**
 * Retorna el mapeo de códigos de módulos a sus nombres y configuraciones.
 * @return array Array con la configuración de módulos disponibles
 */
if (!function_exists('obtenerMapeoDeModulos')) {
function obtenerMapeoDeModulos(): array {
    return [
        ['codigo' => 1, 'mods' => [], 'nombre' => 'Básica'],
        ['codigo' => 3, 'mods' => ['RP'], 'nombre' => 'Básica + Nómina'],
        ['codigo' => 4, 'mods' => ['CE'], 'nombre' => 'Básica + Comp. Elec.'],
        ['codigo' => 5, 'mods' => ['RP', 'CE'], 'nombre' => 'Básica + Nómina + Comp. Elec.'],
        ['codigo' => 6, 'mods' => ['AF'], 'nombre' => 'Básica + Activos Fijos'],
        ['codigo' => 7, 'mods' => ['AF', 'RP'], 'nombre' => 'Básica + Activos Fijos + Nómina'],
        ['codigo' => 8, 'mods' => ['AF', 'CE'], 'nombre' => 'Básica + Activos Fijos + Comp. Elec.'],
        ['codigo' => 9, 'mods' => ['AF', 'RP', 'CE'], 'nombre' => 'Básica + Activos Fijos + Nómina + Comp. Elec.'],
        ['codigo' => 10, 'mods' => ['OP'], 'nombre' => 'Básica + Producción'],
        ['codigo' => 11, 'mods' => ['OP', 'RP'], 'nombre' => 'Básica + Producción + Nómina'],
        ['codigo' => 12, 'mods' => ['OP', 'CE'], 'nombre' => 'Básica + Producción + Comp. Elec.'],
        ['codigo' => 13, 'mods' => ['OP', 'RP', 'CE'], 'nombre' => 'Básica + Producción + Nómina + Comp. Elec.'],
        ['codigo' => 14, 'mods' => ['OP', 'AF'], 'nombre' => 'Básica + Producción + Activos Fijos'],
        ['codigo' => 15, 'mods' => ['OP', 'AF', 'RP'], 'nombre' => 'Básica + Producción + Activos Fijos + Nómina'],
        ['codigo' => 16, 'mods' => ['OP', 'AF', 'CE'], 'nombre' => 'Básica + Producción + Activos Fijos + Comp. Elec.'],
        ['codigo' => 17, 'mods' => ['OP', 'AF', 'RP', 'CE'], 'nombre' => 'Completa (Todos los módulos)'],
    ];
}
}

// -------------------------------------------------------------------
// Renderizar página de perfil
// -------------------------------------------------------------------
/**
 * Renderiza la página de edición del perfil del usuario actual.
 * Muestra un formulario con los datos del usuario y permite su edición.
 * @param mysqli $conn Conexión activa a la base de datos
 */
if (!function_exists('renderizarPaginaPerfil')) {
function renderizarPaginaPerfil(mysqli $conn): void {
    $pk_usuario = $_SESSION['pk_usuario'];

    $stmt = $conn->prepare("SELECT Nombre, Usuario, Telefono, Perfil, Email FROM Usuarios WHERE PK_Usuario = ?");
    $stmt->bind_param("i", $pk_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmt->close();

    if (!$usuario) {
        die("Error: No se pudo encontrar el perfil del usuario.");
    }
    
    $val = function($key) use ($usuario) {
        return htmlspecialchars($usuario[$key] ?? '', ENT_QUOTES, 'UTF-8');
    };

    $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8');
    $tituloPagina = "Mi Perfil";
    $actionUrl = htmlspecialchars($_SERVER['PHP_SELF']);

    $urlRetorno = $_SERVER['PHP_SELF'];
    if (isset($_SESSION['Ruc']) && !empty($_SESSION['Ruc'])) {
        $urlRetorno .= '?Accion=Administrar&Ruc=' . urlencode($_SESSION['Ruc']);
    }

    $mensaje_html = '';
    if (isset($_SESSION['mensaje_perfil'])) {
        $tipo_mensaje = $_SESSION['mensaje_perfil']['tipo'];
        $texto_mensaje = $_SESSION['mensaje_perfil']['texto'];
        $color_fondo = ($tipo_mensaje === 'success') ? '#d4edda' : '#f8d7da';
        $color_texto = ($tipo_mensaje === 'success') ? '#155724' : '#721c24';
        $mensaje_html = "<div style='background-color: {$color_fondo}; color: {$color_texto}; border: 1px solid {$color_fondo}; padding: 15px; margin-bottom: 20px; border-radius: 5px;'>" . htmlspecialchars($texto_mensaje) . "</div>";
        unset($_SESSION['mensaje_perfil']);
    }

    echo <<<HTML
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>{$tituloPagina}</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 0; margin: 0; }
        .header-bar { background-color:#333; color:white; padding:10px 20px; text-align:right; }
        .header-bar a { color: #ffc107; text-decoration: none; }
        .header-bar a.profile-link { color: white; text-decoration: underline; margin-right: 15px;}
        .container { max-width: 800px; margin: 20px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { display: flex; flex-direction: column; }
        label { font-weight: bold; margin-bottom: 5px; color: #333; }
        input[type=text], input[type=password], input[type=email] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        input.readonly { background-color: #e9ecef; color: #495057; cursor: not-allowed; }
        .form-actions { text-align: right; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-submit { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; }
        .btn-submit:hover { background-color: #0056b3; }
        .btn-cancel { display: inline-block; background-color: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px; }
        .password-note { font-size: 0.9em; color: #6c757d; margin-top: 5px; }
    </style>
    </head><body>
    <div class="header-bar">
        Bienvenido, <strong>{$nombreUsuario}</strong> | <a href="?Accion=Mi_Perfil" class="profile-link">Mi Perfil</a> | <a href="?Accion=Logout">Cerrar Sesión</a>
    </div>
    <div class="container">
        <h1>Editar Mi Perfil</h1>
        <p>Aquí puedes ver y actualizar tu información personal. La contraseña solo se cambiará si rellenas los campos correspondientes.</p>
        {$mensaje_html}
        <form action="{$actionUrl}" method="POST">
            <input type="hidden" name="Accion" value="Procesar_Edicion_Perfil">
            <div class="form-grid">
                <div class="input-group"><label for="perfil">Perfil de Usuario</label><input type="text" id="perfil" value="{$val('Perfil')}" class="readonly" readonly></div>
                <div class="input-group"><label for="email">Email</label><input type="email" id="email" name="Email" value="{$val('Email')}" required></div>
                <div class="input-group"><label for="nombre">Nombre Completo</label><input type="text" id="nombre" name="Nombre" value="{$val('Nombre')}" required></div>
                <div class="input-group"><label for="usuario">Nombre de Usuario (Login)</label><input type="text" id="usuario" name="Usuario" value="{$val('Usuario')}" required></div>
                <div class="input-group"><label for="telefono">Teléfono</label><input type="text" id="telefono" name="Telefono" value="{$val('Telefono')}"></div>
            </div>
            <h3 style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Cambiar Contraseña</h3>
            <div class="form-grid">
                 <div class="input-group"><label for="password">Nueva Contraseña</label><input type="password" id="password" name="Password" placeholder="Dejar en blanco para no cambiar"></div>
                 <div class="input-group"><label for="password_confirm">Confirmar Nueva Contraseña</label><input type="password" id="password_confirm" name="Password_Confirm" placeholder="Repetir nueva contraseña"></div>
            </div>
            <p class="password-note">La contraseña debe ser segura. Se recomienda una combinación de letras, números y símbolos.</p>
            <div class="form-actions">
                <button type="submit" class="btn-submit">Guardar Cambios</button>
                <a href="{$urlRetorno}" class="btn-cancel">Volver</a>
            </div>
        </form>
    </div>
    </body></html>
HTML;
    exit;
}
}

// -------------------------------------------------------------------
// Procesar edición de perfil
// -------------------------------------------------------------------
/**
 * Procesa el formulario de edición de perfil del usuario.
 * Valida los datos, actualiza la base de datos y maneja los mensajes de respuesta.
 * @param mysqli $conn Conexión activa a la base de datos
 */
if (!function_exists('procesarEdicionPerfil')) {
function procesarEdicionPerfil(mysqli $conn): void {
    $pk_usuario = $_SESSION['pk_usuario'];
    $nombre = trim($_POST['Nombre'] ?? '');
    $usuario_login = trim($_POST['Usuario'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $telefono = trim($_POST['Telefono'] ?? '');
    $password = $_POST['Password'] ?? '';
    $password_confirm = $_POST['Password_Confirm'] ?? '';

    $stmt = $conn->prepare("SELECT PK_Usuario FROM Usuarios WHERE (Usuario = ? OR Email = ?) AND PK_Usuario != ?");
    $stmt->bind_param("ssi", $usuario_login, $email, $pk_usuario);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['mensaje_perfil'] = ['tipo' => 'error', 'texto' => 'El nombre de usuario o el email ya están en uso por otra cuenta.'];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Mi_Perfil');
        exit;
    }
    $stmt->close();

    $password_hash = null;
    if (!empty($password)) {
        if ($password !== $password_confirm) {
            $_SESSION['mensaje_perfil'] = ['tipo' => 'error', 'texto' => 'Las contraseñas no coinciden.'];
            header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Mi_Perfil');
            exit;
        }
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($password_hash) {
        $sql = "UPDATE Usuarios SET Nombre = ?, Usuario = ?, Email = ?, Telefono = ?, Password = ? WHERE PK_Usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $nombre, $usuario_login, $email, $telefono, $password_hash, $pk_usuario);
    } else {
        $sql = "UPDATE Usuarios SET Nombre = ?, Usuario = ?, Email = ?, Telefono = ? WHERE PK_Usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $nombre, $usuario_login, $email, $telefono, $pk_usuario);
    }

    if ($stmt->execute()) {
        $_SESSION['nombre'] = $nombre;
        $_SESSION['usuario'] = $usuario_login;
        $_SESSION['mensaje_flash'] = 'Tu perfil se ha actualizado correctamente.';
    } else {
        $_SESSION['mensaje_perfil'] = ['tipo' => 'error', 'texto' => 'Ocurrió un error al actualizar tu perfil: ' . $stmt->error];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Mi_Perfil');
        exit;
    }
    
    $stmt->close();

    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_SESSION['Ruc']) && !empty($_SESSION['Ruc'])) {
        $redirect_url .= '?Accion=Administrar&Ruc=' . urlencode($_SESSION['Ruc']);
    }

    header('Location: ' . $redirect_url);
    exit;
}
} 