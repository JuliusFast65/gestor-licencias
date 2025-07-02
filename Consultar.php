<?php
session_start();
// Habilitar la visualización de errores solo para depuración
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// <-- CAMBIO 1: Array para almacenar los logs de depuración
$debug_logs = [];

// <-- CAMBIO 2: La función ahora guarda en el array en lugar de imprimir
function debug_log($title, $data = null) {
    global $debug_logs; // Usamos la variable global
    $debug_logs[] = ['title' => $title, 'data' => $data];
}

// <-- CAMBIO 3: Una función para imprimir todos los logs acumulados
function print_debug_logs() {
    global $debug_logs;
    if (empty($debug_logs)) return;

    echo '<div style="position:fixed; bottom:0; left:0; width:100%; z-index:9999; max-height: 200px; overflow-y:auto; background-color: rgba(0,0,0,0.8);">';
    foreach ($debug_logs as $log) {
        echo '<div style="background-color: #f9f2f4; border-bottom: 2px solid #c51f5d; padding: 10px; font-family: monospace; color: black;">';
        echo '<h3 style="margin: 0; color: #c51f5d;">DEBUG: ' . htmlspecialchars($log['title']) . '</h3>';
        if ($log['data'] !== null) {
            echo '<pre style="color: #333;">';
            print_r($log['data']);
            echo '</pre>';
        }
        echo '</div>';
    }
    echo '</div>';
}

// <-- CAMBIO 4: Registramos un "shutdown function" para que imprima los logs siempre al final.
// Esto es genial porque incluso si el script muere con un `exit`, los logs se mostrarán.
register_shutdown_function('print_debug_logs');

// ===================================================================
// LÓGICA COMPLETA DE AUTENTICACIÓN
// ===================================================================

// --- Conexión a BD (necesaria para el login) ---
require_once('../apis/Conectar_BD.php');
try {
    $conn = Conectar_BD();
} catch (Exception $e) {
    error_log('Error de conexión a la BD: ' . $e->getMessage());
    die('No se puede establecer conexión con la base de datos.');
}

// --- 1. PROCESAR LOGOUT ---
if (isset($_GET['Accion']) && $_GET['Accion'] === 'Logout') {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
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
        // <-- RBAC: Se añade el campo 'Perfil' a la consulta de login -->
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
                // <-- RBAC: Se guarda el perfil del usuario en la sesión -->
                $_SESSION['perfil'] = $user_data['Perfil'];
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }        

        $error_login = 'Usuario o contraseña incorrectos.';
    }
    renderizarFormularioLogin($error_login, $conn);
}

// --- 3. VERIFICAR SESIÓN ACTIVA ---
if (session_id() == '' || !isset($_SESSION['usuario'])) {
    renderizarFormularioLogin('', $conn);
}
// ===================================================================
// FIN DE LA LÓGICA DE AUTENTICACIÓN
// ===================================================================

// ===================================================================
// RBAC: SISTEMA DE PERMISOS BASADO EN ROLES
// ===================================================================
//define('RUC_PRUEBA_DESARROLLO', '1234567890123');
// Define una lista de RUCs permitidos para el perfil de desarrollo
define('RUCS_PRUEBA_DESARROLLO', [
    '1234567890123', // RUC de prueba original
    '0992247525001', // Nuevo RUC de prueba
    '0910155720001'  // Otro RUC de prueba
]);

/**
 * Verifica si el usuario actual tiene permiso para crear una empresa.
 * @return bool True si tiene permiso, false si no.
 */
function usuarioPuedeCrearEmpresa(): bool {
    // Normalizamos el perfil a minúsculas para una comparación robusta.
    $perfil = strtolower($_SESSION['perfil'] ?? '');
    return in_array($perfil, ['administrador', 'presidente']);
}

/**
 * Verifica si el usuario actual tiene permiso para editar datos de una empresa.
 * @param string $ruc El RUC de la empresa que se intenta editar.
 * @return bool True si tiene permiso, false si no.
 */
function usuarioPuedeEditarEmpresa(string $ruc): bool {
    // Normalizamos el perfil a minúsculas.
    $perfil = strtolower($_SESSION['perfil'] ?? '');

    if (in_array($perfil, ['administrador', 'presidente'])) {
        return true;
    }
    if ($perfil === 'desarrollo' && in_array($ruc, RUCS_PRUEBA_DESARROLLO)) {
        return true;
    }
    return false;
}

/**
 * Verifica si el usuario actual tiene permiso para eliminar una empresa.
 * @return bool True si tiene permiso, false si no.
 */
function usuarioPuedeEliminarEmpresa(): bool {
    // Normalizamos el perfil a minúsculas para una comparación robusta.
    $perfil = strtolower($_SESSION['perfil'] ?? '');
    return in_array($perfil, ['administrador', 'presidente']);
}


/**
 * Verifica si el usuario actual tiene permiso para dar de baja una licencia.
 * @param string $ruc El RUC de la empresa afectada.
 * @return bool True si tiene permiso, false si no.
 */
function usuarioPuedeDarDeBajaLicencia(string $ruc): bool {
    // Normalizamos el perfil a minúsculas.
    $perfil = strtolower($_SESSION['perfil'] ?? '');

    if (in_array($perfil, ['administrador', 'presidente'])) {
        return true;
    }
    if ($perfil === 'desarrollo' && in_array($ruc, RUCS_PRUEBA_DESARROLLO)) {
        return true;
    }
    return false;
}

/**
 * Función central para verificar permisos y detener el script si no se cumplen.
 * @param bool $tienePermiso El resultado de una de las funciones de verificación.
 * @param string $mensaje El mensaje a mostrar si no hay permiso.
 */
function verificarPermisoYSalir(bool $tienePermiso, string $mensaje = 'No tiene permiso para realizar esta acción.'): void {
    if (!$tienePermiso) {
        http_response_code(403); // Forbidden
        die("<div style='font-family: sans-serif; text-align: center; padding: 50px;'>
                <h1 style='color: #d9534f;'>Acceso Denegado</h1>
                <p style='font-size: 1.2em;'>{$mensaje}</p>
                <a href='{$_SERVER['PHP_SELF']}'>Volver al inicio</a>
             </div>");
    }
}
// ===================================================================
// FIN DEL SISTEMA DE PERMISOS
// ===================================================================

// --- Configuración e Inicialización ---
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('America/Bogota');
require_once('ObtActivacion.php');

// --- Lógica Principal (Controlador) ---
if (isset($_REQUEST['Ruc']) && !empty($_REQUEST['Ruc'])) {
    $ruc_entrante = trim($_REQUEST['Ruc']);
    $ruc_sesion_actual = $_SESSION['Ruc'] ?? null;
    if ($ruc_entrante !== $ruc_sesion_actual) {
        $_SESSION['Ruc'] = $ruc_entrante;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Administrar');
        exit;
    }
}
$Ruc = $_SESSION['Ruc'] ?? '';
$Accion = $_GET['Accion'] ?? $_POST['Accion'] ?? 'Dashboard';

$acciones_requieren_ruc = ['Administrar', 'Editar_Empresa', 'Procesar_Edicion_Empresa', 'Eliminar_Empresa', 'Procesar_Eliminar_Empresa', 'Baja', 'Baja_Sesiones', 'Alta', 'Activar'];
if (in_array($Accion, $acciones_requieren_ruc) && empty($Ruc)) {
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- Enrutador de Acciones con Verificación de Permisos ---
switch ($Accion) {
    case 'Administrar':
        renderizarPaginaAdministracion($conn, $Ruc);
        break;
    case 'Dashboard':
        renderizarDashboard($conn);
        break;
        
    case 'Buscar_Empresas_Live':
        procesarBusquedaLive($conn);
        break;

    case 'Alta_Empresa':
        verificarPermisoYSalir(usuarioPuedeCrearEmpresa());
        renderizarFormularioEmpresa($conn, null);
        break;
    case 'Editar_Empresa':
        verificarPermisoYSalir(usuarioPuedeEditarEmpresa($Ruc));
        renderizarFormularioEmpresa($conn, $Ruc);
        break;
    case 'Procesar_Alta_Empresa':
        verificarPermisoYSalir(usuarioPuedeCrearEmpresa());
        procesarFormularioEmpresa($conn, null);
        break;
    case 'Procesar_Edicion_Empresa':
        verificarPermisoYSalir(usuarioPuedeEditarEmpresa($Ruc));
        procesarFormularioEmpresa($conn, $Ruc);
        break;
    // <-- VALIDACIÓN/ELIMINACIÓN: Nueva acción para procesar la eliminación -->
    case 'Procesar_Eliminar_Empresa':
        verificarPermisoYSalir(usuarioPuedeEliminarEmpresa());
        procesarEliminarEmpresa($conn, $Ruc);
        break;        
    case 'Alta':
        renderizarPaginaAlta($conn, $Ruc);
        break;
    case 'Activar':
        $Serie = (string) ($_POST['Serie'] ?? '');
        renderizarPaginaActivar($conn, $Ruc, $Serie);
        break;
    case 'Baja':
        verificarPermisoYSalir(usuarioPuedeDarDeBajaLicencia($Ruc));
        $pkLicencia = (int) ($_GET['PK_Licencia'] ?? 0);
        procesarBajaLicencia($conn, $Ruc, $pkLicencia);
        break;
    case 'Baja_Sesiones':
        $Serie_baja = (string) ($_POST['Serie'] ?? '');
        procesarBajaSesiones($conn, $Ruc, $Serie_baja);
        break;
 
    case 'Mi_Perfil':
        renderizarPaginaPerfil($conn);
        break;
    case 'Procesar_Edicion_Perfil':
        procesarEdicionPerfil($conn);
        break;
        
    default:
        renderizarDashboard($conn);
        break;
}

$conn->close();

// --- Bloque de Funciones ---

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

function renderizarPaginaAdministracion(mysqli $conn, string $Ruc): void {
    $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8');
    $empresa = obtenerRegistroEmpresa($conn, $Ruc);
    if (!$empresa) {
        unset($_SESSION['Ruc']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    echo <<<HTML
    <!DOCTYPE html><html lang="es">
    <head>
        <meta charset="UTF-8"><title>Gestor de Licencias - {$empresa['Nombre']}</title>
        <style>
            body { font-family: sans-serif; background-color: #f8f9fa; padding: 20px; }
            .container { max-width: 1400px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .suscripcion-info { font-size: 1.1em; padding: 8px 12px; border-radius: 5px; margin: 15px 0 25px 0; text-align: center; }
            .suscripcion-ok { background-color: #e6f7ff; border: 1px solid #91d5ff; color: #0050b3; }
            .suscripcion-warning { background-color: #fffbe6; border: 1px solid #ffe58f; color: #ad8b00; font-weight: bold; }
            .suscripcion-expired { background-color: #fff1f0; border: 1px solid #ffccc7; color: #cf1322; font-weight: bold; }
            .control-info { font-size: 0.8em; color: #fff; padding: 2px 6px; border-radius: 4px; margin-left: 10px; vertical-align: middle; }
            .control-sesion { background-color: #28a745; }
            .control-maquina { background-color: #17a2b8; }
            .fila-fsoft { background-color: #e7f5ff !important; }
            .fila-lsoft { background-color: #e6fffa !important; }
            .fila-lsoftw { background-color: #f9f0ff !important; }
            tr:hover { background-color: #f5f5f5 !important; }
        </style>
    </head>
    <body>
        <div style="background-color:#333; color:white; padding:10px 20px; text-align:right;">
            Bienvenido, <strong>{$nombreUsuario}</strong> ({$_SESSION['perfil']}) | <a href="?Accion=Mi_Perfil" style="color:white; text-decoration: underline;">Mi Perfil</a> | <a href="?Accion=Logout" style="color: #ffc107; text-decoration: none;">Cerrar Sesión</a>
        </div>
        <div class="container">
HTML;
    
    // <-- CAMBIO: Bloque para mostrar mensajes flash, movido aquí para aparecer arriba. -->
    if (isset($_SESSION['mensaje_flash'])) {
        echo "<div style='background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin: 0 0 20px 0; border-radius: 5px;'>" 
             . htmlspecialchars($_SESSION['mensaje_flash']) 
             . "</div>";
        unset($_SESSION['mensaje_flash']);
    }
    
    echo renderizarWidgetBusquedaYComando();
    echo "<hr style='margin: 15px 0;'>";

    $nombreEmpresa = htmlspecialchars($empresa['Nombre'], ENT_QUOTES, 'UTF-8');
    $rucSeguro = htmlspecialchars($Ruc, ENT_QUOTES, 'UTF-8');

    $botonEditarEmpresa = '';
    if (usuarioPuedeEditarEmpresa($Ruc)) {
        $botonEditarEmpresa = "<a href='?Accion=Editar_Empresa&Ruc={$Ruc}' style='background-color:#ffc107; color:black; padding:10px 15px; text-decoration:none; border-radius:4px; height: fit-content;'>Editar Empresa</a>";
    }

    echo "<div style='display:flex; justify-content:space-between; align-items:center;'>
            <div>
                <h1 style='margin-bottom: 0;'>Licencias de: {$nombreEmpresa}</h1>
                <div style='font-size: 0.9em; color: #555; margin-top: -5px;'>RUC: {$rucSeguro}</div>
            </div>
            {$botonEditarEmpresa}
          </div>";



// Fechas de suscripción
$inicioSuscripcion = !empty($empresa['Inicio_Suscripcion'])
    ? new DateTime($empresa['Inicio_Suscripcion'])
    : null;
$finSuscripcion = !empty($empresa['Fin_Suscripcion'])
    ? new DateTime($empresa['Fin_Suscripcion'])
    : null;

// Normalizo hoy y mañana a medianoche
$hoyMidnight     = (new DateTime())->setTime(0, 0, 0);
$mananaMidnight  = (clone $hoyMidnight)->modify('+1 day');

$claseCssSuscripcion = 'suscripcion-ok';
$mensajeSuscripcion   = 'Suscripción activa.';

if ($finSuscripcion) {
    $finNorm     = (clone $finSuscripcion)->setTime(0, 0, 0);
    $fechaFinFmt = $finNorm->format('d/m/Y');
    $fechaInicioFmt = $inicioSuscripcion
        ? $inicioSuscripcion->format('d/m/Y')
        : 'N/A';

    if ($finNorm < $hoyMidnight) {
        // Ya vencida
        $claseCssSuscripcion = 'suscripcion-expired';
        $mensajeSuscripcion  = "SUSCRIPCIÓN VENCIDA. Finalizó el {$fechaFinFmt}.";
    }
    elseif ($finNorm == $hoyMidnight) {
        // Vence hoy
        $claseCssSuscripcion = 'suscripcion-warning';
        $mensajeSuscripcion  = "¡ATENCIÓN! La suscripción vence hoy ({$fechaFinFmt}).";
    }
    else {
        // Vence mañana o después
        $intervalo     = $mananaMidnight->diff($finNorm);
        $diasRestantes = $intervalo->days + 1; // incluir día de fin

        if ($diasRestantes == 1) {
            $claseCssSuscripcion = 'suscripcion-warning';
            $mensajeSuscripcion  = "¡ATENCIÓN! La suscripción vence mañana.";   
        } else {
            if ($diasRestantes <= 30) {
                $claseCssSuscripcion = 'suscripcion-warning';
                $mensajeSuscripcion  = "¡ATENCIÓN! La suscripción vence en {$diasRestantes} día(s) (el {$fechaFinFmt}).";
            } else {
                $mensajeSuscripcion  = "Suscripción activa desde el {$fechaInicioFmt} hasta el {$fechaFinFmt}.";
            }
        }
    }
}
else {
    $mensajeSuscripcion = "No se ha definido una fecha de fin de suscripción.";
}



// Ahora puedes usar $claseCssSuscripcion y $mensajeSuscripcion en tu vista/template

    echo "<div class='suscripcion-info {$claseCssSuscripcion}'>{$mensajeSuscripcion}</div>";
    
    $controlFsoftPorSesion = version_compare($empresa['Version_FSoft'] ?? '0.0.0', '5.2.89', '>=');
    $controlLsoftPorSesion = version_compare($empresa['Version_LSoft'] ?? '0.0.0', '2.1.36', '>=');
    $controlLsoftWebPorSesion = !empty($empresa['Version_LSoft_Web']); 
    
    $esNuevoModelo = $controlFsoftPorSesion || $controlLsoftPorSesion || $controlLsoftWebPorSesion;

    $licencias = obtenerTodasLasLicenciasPorRuc($conn, $Ruc);
    $sesionesActivas = $esNuevoModelo ? obtenerSesionesActivasPorRuc($conn, $Ruc) : [];

    $statsFsoft = calcularEstadisticasLicencias($licencias, $empresa, 'FSOFT', $sesionesActivas);
    $statsLsoft = calcularEstadisticasLicencias($licencias, $empresa, 'LSOFT', $sesionesActivas);
    $statsLsoftWeb = calcularEstadisticasLicencias($licencias, $empresa, 'LSOFTW', $sesionesActivas);
    
    echo "<div style='display:flex; flex-wrap: wrap; gap: 40px; margin-bottom: 30px;'>";
    echo renderizarBloqueSistema('F-Soft', $statsFsoft, $empresa['Version_FSoft'] ?? null, $controlFsoftPorSesion);
    echo renderizarBloqueSistema('L-Soft', $statsLsoft, $empresa['Version_LSoft'] ?? null, $controlLsoftPorSesion);
    echo renderizarBloqueSistema('L-Soft Web', $statsLsoftWeb, $empresa['Version_LSoft_Web'] ?? null, true);
    echo "</div>";

    $totalLicenciasCompradas = (int)($empresa['Cant_Lic_FSOFT_BA'] ?? 0) + (int)($empresa['Cant_Lic_LSOFT_BA'] ?? 0);
    
    echo renderizarTablaLicencias($conn, $licencias, $totalLicenciasCompradas, $esNuevoModelo, $sesionesActivas);

    echo "</div>";
    echo renderizarScriptBusqueda();
    echo "</body></html>";
    exit;
}

function obtenerRegistroEmpresa(mysqli $conn, string $Ruc): ?array {
    $stmt = $conn->prepare("SELECT * FROM Empresas WHERE Ruc = ?");
    $stmt->bind_param("s", $Ruc);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function obtenerSesionesActivasPorRuc(mysqli $conn, string $Ruc): array {
    $sql = "SELECT Serie, usuario, tipo, ultima_actividad 
            FROM sesiones_erp 
            WHERE Ruc = ? 
            ORDER BY ultima_actividad DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $Ruc);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function obtenerTodasLasLicenciasPorRuc(mysqli $conn, string $Ruc): array {
    $sql = "SELECT * FROM Licencias 
            WHERE Ruc=? AND (Sistema='FSOFT' OR Sistema='LSOFT') 
            ORDER BY Sistema, Serie";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $Ruc);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function calcularEstadisticasLicencias(array $licencias_escritorio, array $empresa, string $sistema, array $sesionesActivas = []): array {
    $stats = [
        'compradas_basicas' => (int)($empresa["Cant_Lic_{$sistema}_BA"] ?? 0),
        'usadas_basicas' => 0,
        'modulos' => [
            'RP' => ['nombre' => 'Nómina', 'compradas' => (int)($empresa["Cant_Lic_{$sistema}_RP"] ?? 0), 'usadas' => 0],
            'CE' => ['nombre' => 'Comprobantes Electrónicos', 'compradas' => (int)($empresa["Cant_Lic_{$sistema}_CE"] ?? 0), 'usadas' => 0],
            'AF' => ['nombre' => 'Activos Fijos', 'compradas' => (int)($empresa["Cant_Lic_{$sistema}_AF"] ?? 0), 'usadas' => 0],
            'OP' => ['nombre' => 'Producción', 'compradas' => (int)($empresa["Cant_Lic_{$sistema}_OP"] ?? 0), 'usadas' => 0],
        ]
    ];

    $usarModeloPorSesion = false;
    if ($sistema === 'FSOFT') {
        $version = $empresa['Version_FSoft'] ?? '0.0.0';
        if (version_compare($version, '5.2.89', '>=')) {
            $usarModeloPorSesion = true;
        }
    } elseif ($sistema === 'LSOFT') {
        $version = $empresa['Version_LSoft'] ?? '0.0.0';
        if (version_compare($version, '2.1.36', '>=')) {
            $usarModeloPorSesion = true;
        }
    } elseif ($sistema === 'LSOFTW') {
        $usarModeloPorSesion = true;
    }

    if ($usarModeloPorSesion) {
        $combinacionesUnicasContadas = [];
        foreach ($sesionesActivas as $sesion) {
            if (empty($sesion['Serie']) || strpos($sesion['tipo'], $sistema . '_') !== 0) {
                continue;
            }
            $claveUnica = $sesion['Serie'] . '-' . $sesion['tipo'];
            if (isset($combinacionesUnicasContadas[$claveUnica])) {
                continue;
            }
            $combinacionesUnicasContadas[$claveUnica] = true;
            $sufijo = substr($sesion['tipo'], strlen($sistema) + 1);
            if ($sufijo === 'BA') {
                $stats['usadas_basicas']++;
            } elseif (isset($stats['modulos'][$sufijo])) {
                $stats['modulos'][$sufijo]['usadas']++;
            }
        }
    } else {
        $mapaModulosRaw = obtenerMapeoDeModulos();
        $mapaPorCodigo = [];
        foreach ($mapaModulosRaw as $mapa) {
            $mapaPorCodigo[$mapa['codigo']] = $mapa['mods'];
        }
        $licenciasDelSistema = array_filter($licencias_escritorio, function($lic) use ($sistema) {
            return strcasecmp($lic['Sistema'], $sistema) === 0;
        });
        $stats['usadas_basicas'] = count($licenciasDelSistema);
        foreach ($licenciasDelSistema as $licencia) {
            $tipoLicencia = (int) $licencia['Tipo_Licencia'];
            if (isset($mapaPorCodigo[$tipoLicencia])) {
                foreach ($mapaPorCodigo[$tipoLicencia] as $codigoModulo) {
                    if (isset($stats['modulos'][$codigoModulo])) {
                        $stats['modulos'][$codigoModulo]['usadas']++;
                    }
                }
            }
        }
    }
    return $stats;
}

function procesarBajaSesiones(mysqli $conn, string $Ruc, string $Serie): void {
    if (empty($Ruc) || empty($Serie)) {
        die("Error: Faltan datos para procesar la baja de sesiones.");
    }
    $stmt = $conn->prepare("DELETE FROM sesiones_erp WHERE Ruc = ? AND Serie = ?");
    $stmt->bind_param("ss", $Ruc, $Serie);
    $stmt->execute();
    $filas_afectadas = $stmt->affected_rows;
    $stmt->close();
    $_SESSION['mensaje_flash'] = "Se han cerrado {$filas_afectadas} sesiones para la máquina {$Serie}.";
    $redirect_url = $_SERVER['PHP_SELF'] . '?Accion=Administrar&Ruc=' . urlencode($Ruc);
    header('Location: ' . $redirect_url);
    exit;
}

function renderizarTablaLicencias(mysqli $conn, array $licencias, int $totalSlots, bool $esNuevoModelo, array $sesionesActivas, bool $mostrarFlash = true): string {
    $html = '';
    if ($mostrarFlash && isset($_SESSION['mensaje_flash'])) {
        $html .= "<div style='background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>" . htmlspecialchars($_SESSION['mensaje_flash']) . "</div>";
        unset($_SESSION['mensaje_flash']);
    }

    $mapaModulosRaw = obtenerMapeoDeModulos();
    $mapaPorCodigo = [];
    foreach ($mapaModulosRaw as $mapa) {
        $mapaPorCodigo[$mapa['codigo']] = $mapa['nombre'];
    }

    if ($esNuevoModelo) {
        // --- MODELO NUEVO: Por Sesión (Sin cambios en esta parte) ---
        $html .= "<a href='?Accion=Alta' class='btn-alta-maquina'>+ Activar Nueva Máquina</a>";
        
        $sesionesPorSerie = [];
        foreach ($sesionesActivas as $sesion) {
            $serie = $sesion['Serie'];
            if (empty($serie)) continue;
            if (!isset($sesionesPorSerie[$serie])) {
                $sesionesPorSerie[$serie] = ['fsoft_ba' => 0, 'fsoft_mod' => 0, 'lsoft_ba' => 0, 'lsoft_mod' => 0, 'lsoftw_ba' => 0, 'lsoftw_mod' => 0, 'detalles' => []];
            }
            $tipo = $sesion['tipo'];
            if ($tipo === 'FSOFT_BA') $sesionesPorSerie[$serie]['fsoft_ba']++;
            elseif (strpos($tipo, 'FSOFT_') === 0) $sesionesPorSerie[$serie]['fsoft_mod']++;
            elseif ($tipo === 'LSOFT_BA') $sesionesPorSerie[$serie]['lsoft_ba']++;
            elseif (strpos($tipo, 'LSOFT_') === 0) $sesionesPorSerie[$serie]['lsoft_mod']++;
            elseif ($tipo === 'LSOFTW_BA') $sesionesPorSerie[$serie]['lsoftw_ba']++;
            elseif (strpos($tipo, 'LSOFTW_') === 0) $sesionesPorSerie[$serie]['lsoftw_mod']++;
            $sesionesPorSerie[$serie]['detalles'][] = $sesion;
        }
        
        $html .= "<h3>Máquinas Activadas (" . count($licencias) . ")</h3>";
        $html .= "<table><thead><tr><th>No.</th><th>Máquina</th><th>Serie</th><th>Sistema</th><th>Tipo Lic. Activada</th>
                        <th>F-Soft Bás.</th><th>F-Soft Mód.</th><th>L-Soft Bás.</th><th>L-Soft Mód.</th>
                        <th>L-Soft Web Bás.</th><th>L-Soft Web Mód.</th><th>Últ. Acceso</th>
                        <th>Acción</th></tr></thead><tbody>";

        if (empty($licencias)) {
            $html .= "<tr><td colspan='13'>No hay máquinas activadas para esta empresa.</td></tr>";
        }
        
        $traducciones_sesion = [
            'FSOFT_BA' => 'F-Soft Básica', 'FSOFT_RP' => 'F-Soft Nómina', 'FSOFT_CE' => 'F-Soft Comp. Elec.',
            'LSOFT_BA' => 'L-Soft Básica', 'LSOFT_RP' => 'L-Soft Nómina', 'LSOFT_CE' => 'L-Soft Comp. Elec.', 'LSOFT_AF' => 'L-Soft Activos Fijos', 'LSOFT_OP' => 'L-Soft Producción',
            'LSOFTW_BA' => 'L-Soft Web Básica', 'LSOFTW_RP' => 'L-Soft Web Nómina', 'LSOFTW_AF' => 'L-Soft Web Activos Fijos', 'LSOFTW_OP' => 'L-Soft Web Producción',
        ];

        $i = 1;
        $rucActual = $_SESSION['Ruc'] ?? '';

        foreach ($licencias as $l) {
            $serie = htmlspecialchars($l['Serie'], ENT_QUOTES, 'UTF-8');
            $sesiones = $sesionesPorSerie[$l['Serie']] ?? null;
            $tieneSesiones = !empty($sesiones);
            
            $estiloFila = $tieneSesiones ? "cursor:pointer; text-decoration: underline; text-decoration-style: dotted;" : '';
            $filaOnclick = $tieneSesiones ? "onclick=\"toggleSesiones('{$serie}')\"" : '';

            $claseColor = '';
            switch(strtoupper($l['Sistema'])) {
                case 'FSOFT': $claseColor = 'fila-fsoft'; break;
                case 'LSOFT': $claseColor = 'fila-lsoft'; break;
                case 'LSOFTW': $claseColor = 'fila-lsoftw'; break;
            }

            $tipoLicenciaCodigo = (int) $l['Tipo_Licencia'];
            $tipoLicenciaNombre = htmlspecialchars($mapaPorCodigo[$tipoLicenciaCodigo] ?? "Código: {$tipoLicenciaCodigo}");

            $html .= "<tr class='lic-row {$claseColor}' {$filaOnclick} style='{$estiloFila}'>";
            $html .= "<td>" . $i++ . "</td>";
            $html .= "<td>" . htmlspecialchars($l['Maquina']) . "</td>";
            $html .= "<td>{$serie}</td>";
            $html .= "<td>" . htmlspecialchars($l['Sistema']) . "</td>";
            $html .= "<td>{$tipoLicenciaNombre}</td>";
            $html .= "<td>" . ($sesiones['fsoft_ba'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesiones['fsoft_mod'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesiones['lsoft_ba'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesiones['lsoft_mod'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesiones['lsoftw_ba'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesiones['lsoftw_mod'] ?? 0) . "</td>";
            $html .= "<td>" . ($l['Ultimo_Acceso'] ? date('d M y H:i', strtotime($l['Ultimo_Acceso'])) : 'N/A') . "</td>";
            
            $celdaAcciones = "<td>";
            if(usuarioPuedeDarDeBajaLicencia($rucActual)) {
                $celdaAcciones .= "<a href='?Accion=Baja&PK_Licencia={$l['PK_Licencia']}' onclick=\"event.stopPropagation(); return confirm('¿DAR DE BAJA LA LICENCIA para esta máquina?');\">Baja Lic.</a>";
            }
            if ($tieneSesiones) {
                $formBajaSesiones = "<form action='{$_SERVER['PHP_SELF']}' method='POST' style='display:inline; margin-left:10px;'>
                                        <input type='hidden' name='Ruc' value='".htmlspecialchars($rucActual, ENT_QUOTES, 'UTF-8')."'>
                                        <input type='hidden' name='Serie' value='{$serie}'>
                                        <input type='hidden' name='Accion' value='Baja_Sesiones'>
                                        <input type='submit' value='Baja Sesiones' onclick=\"event.stopPropagation(); return confirm('¿CERRAR TODAS LAS SESIONES para esta máquina?');\" style='font-size:0.8em; padding: 2px 5px; cursor: pointer;'>
                                     </form>";
                $celdaAcciones .= $formBajaSesiones;
            }
            $celdaAcciones .= "</td>";
            $html .= $celdaAcciones;
            $html .= "</tr>";

            if ($tieneSesiones) {
                $html .= "<tr class='sesiones-detalle-fila' id='sesiones-{$serie}' style='display:none;'><td colspan='13'><div class='sesiones-detalle-contenido'>";
                $html .= "<table><thead><th>Usuario</th><th>Tipo Sesión</th><th>Última Actividad</th></thead><tbody>";
                foreach ($sesiones['detalles'] as $detalle) {
                    $tipo_sesion_legible = $traducciones_sesion[$detalle['tipo']] ?? htmlspecialchars($detalle['tipo']);
                    $html .= "<tr><td>" . htmlspecialchars($detalle['usuario']) . "</td><td>" . $tipo_sesion_legible . "</td><td>" . htmlspecialchars(date('d M y H:i', strtotime($detalle['ultima_actividad']))) . "</td></tr>";
                }
                $html .= "</tbody></table></div></td></tr>";
            }
        }
        $html .= "</tbody></table>";
    } else {
        // --- MODELO CLÁSICO: Por Máquina/Slot (SECCIÓN MODIFICADA) ---
        $html .= "<h3>Licencias Compradas ({$totalSlots} slots)</h3>";
        $html .= "<table><thead><tr><th>No.</th><th>Máquina</th><th>Serie</th><th>Sistema</th><th>Tipo</th><th>Últ. Acceso</th><th>IP</th><th>Usuario</th><th>Acción</th></tr></thead><tbody>";
        
        $rucActual = $_SESSION['Ruc'] ?? '';

        for ($i = 0; $i < $totalSlots; $i++) {
            if (isset($licencias[$i])) {
                $l = $licencias[$i];
                
                // <-- CAMBIO: Se aplica la misma lógica de coloreado que en el modelo nuevo. -->
                $claseColor = '';
                switch(strtoupper($l['Sistema'])) {
                    case 'FSOFT': $claseColor = 'fila-fsoft'; break;
                    case 'LSOFT': $claseColor = 'fila-lsoft'; break;
                }
            
                $tipoLicenciaCodigo = (int) $l['Tipo_Licencia'];
                $tipoLicenciaNombre = htmlspecialchars($mapaPorCodigo[$tipoLicenciaCodigo] ?? "Código: {$tipoLicenciaCodigo}");
                
                $html .= "<tr class='{$claseColor}'><td>" . ($i + 1) . "</td><td>".htmlspecialchars($l['Maquina'])."</td><td>".htmlspecialchars($l['Serie'])."</td><td>".htmlspecialchars($l['Sistema'])."</td><td>{$tipoLicenciaNombre}</td><td>".($l['Ultimo_Acceso'] ? date('d M y H:i', strtotime($l['Ultimo_Acceso'])) : 'N/A')."</td><td>".htmlspecialchars($l['IP'])."</td><td>".htmlspecialchars($l['Usuario'])."</td>";
                
                $celdaAcciones = "<td>";
                if (usuarioPuedeDarDeBajaLicencia($rucActual)) {
                    $celdaAcciones .= "<a href='?Accion=Baja&PK_Licencia={$l['PK_Licencia']}' onclick=\"return confirm('¿Está seguro?');\">Baja</a>";
                }
                $celdaAcciones .= "</td>";
                $html .= $celdaAcciones;
                $html .= "</tr>";

            } else {
                $html .= "<tr style='background:#D4EDDA;'><td>" . ($i + 1) . "</td><td><em>Disponible</em></td><td></td><td></td><td></td><td></td><td></td><td></td>";
                $html .= "<td><a href='?Accion=Alta'>Alta</a></td></tr>";
            }
        }
        $html .= "</tbody></table>";
    }
    
    $html .= <<<JS
    <script>
        function toggleSesiones(serie) {
            const fila = document.getElementById('sesiones-' + serie);
            if (fila) {
                const esVisible = fila.style.display === 'table-row';
                fila.style.display = esVisible ? 'none' : 'table-row';
            }
        }
    </script>
JS;
    return $html;
}

function renderizarWidgetBusquedaYComando(): string {
    $botonNuevaEmpresa = '';
    if (usuarioPuedeCrearEmpresa()) {
        $botonNuevaEmpresa = "<a href='?Accion=Alta_Empresa'
                                 style='background-color: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; white-space: nowrap;'>
                                 Nueva Empresa
                               </a>";
    }
 return <<<HTML
 <div class="widget-busqueda"
      style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd;">
   <div style="
        display: flex;
        justify-content: left;
        align-items: flex-end;
        gap: 10px;">
     <div>
       <label for="campo-busqueda-nombre"
              style="font-weight: bold; display: block; margin-bottom: 5px;">
         Buscar Empresa (por Nombre o RUC)
       </label>
       <input
         type="text"
         id="campo-busqueda-nombre"
         placeholder="Escriba el nombre o RUC de la empresa..."
         style="width: 400px; padding: 12px; font-size: 1.1em; border: 1px solid #ccc; border-radius: 4px;"
       >
     </div>
     {$botonNuevaEmpresa}
   </div>
   <div id="resultados-busqueda" style="margin-top: 15px;"></div>
 </div>
HTML;
}

function renderizarPaginaAlta(mysqli $conn, string $Ruc): void {
    $empresa = obtenerRegistroEmpresa($conn, $Ruc);
    $licenciasUsadas = contarLicenciasUsadas($conn, $Ruc);
    $totalLicenciasCompradas = (int)($empresa['Cant_Lic_FSOFT_BA'] ?? 0) + (int)($empresa['Cant_Lic_LSOFT_BA'] ?? 0);

    $nombreEmpresa = htmlspecialchars($empresa['Nombre'] ?? 'Desconocida', ENT_QUOTES, 'UTF-8');
    $actionUrl = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');

    echo "<h1>Activar Nueva Licencia para {$nombreEmpresa}</h1>";

    if ($licenciasUsadas >= $totalLicenciasCompradas) {
        echo "<p style='color:red;'><strong>No hay licencias básicas disponibles para activar.</strong></p>";
        echo "<p><a href='{$actionUrl}?Accion=Administrar'>Volver a la administración</a></p>";
        return;
    }
    
    echo <<<HTML
    <p>Por favor, ingrese el número de serie de la máquina a activar.</p>
    <form action="{$actionUrl}" method="post" style="border: 1px solid #ccc; padding: 15px; border-radius: 5px; width: 400px;">
        <input type="hidden" name="Accion" value="Activar">
        <label for="serie_input"><strong>Número de Serie:</strong></label><br>
        <input type="text" id="serie_input" name="Serie" size="15" maxlength="9" required pattern="[a-zA-Z0-9]+">
        <input type="submit" value="Buscar Activaciones">
    </form>
    <p><a href="{$actionUrl}?Accion=Administrar">Cancelar y volver</a></p>
HTML;
}

function renderizarPaginaActivar(mysqli $conn, string $Ruc, string $Serie): void {
    $actionUrl = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
    if (empty($Serie)) {
        die("El número de serie es obligatorio. <a href='{$actionUrl}?Accion=Alta'>Intente de nuevo</a>.");
    }

    if (licenciaExiste($conn, $Ruc, $Serie)) {
        die("La serie <strong>" . htmlspecialchars($Serie, ENT_QUOTES, 'UTF-8') . "</strong> ya se encuentra activada para esta empresa. <a href='{$actionUrl}?Accion=Administrar'>Volver</a>.");
    }
    
    $empresa = obtenerRegistroEmpresa($conn, $Ruc);
    $licencias = obtenerTodasLasLicenciasPorRuc($conn, $Ruc);
    
    $nombreEmpresa = htmlspecialchars($empresa['Nombre'], ENT_QUOTES, 'UTF-8');
    $serieSegura = htmlspecialchars($Serie, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
    <h1>Activaciones Posibles para la Serie <u>{$serieSegura}</u></h1>
    <h2>Empresa: {$nombreEmpresa}</h2>
    <p>A continuación se muestran los números de activación para las combinaciones de módulos con licencias disponibles.</p>
HTML;

    foreach (['FSOFT', 'LSOFT'] as $sistema) {
        $stats = calcularEstadisticasLicencias($licencias, $empresa, $sistema);
        echo generarHtmlActivaciones($Serie, $sistema, $stats);
    }

    echo "<br><p><a href='{$actionUrl}?Accion=Administrar'>Volver a la administración (sin activar)</a></p>";
}

function procesarBajaLicencia(mysqli $conn, string $Ruc, int $pkLicencia): void {
    $message = "Ocurrió un error inesperado.";
    $success = false;

    if ($pkLicencia > 0) {
        $stmt = $conn->prepare("DELETE FROM Licencias WHERE PK_Licencia = ? AND Ruc = ?");
        $stmt->bind_param("is", $pkLicencia, $Ruc);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "La licencia ha sido dada de baja correctamente.";
            $success = true;
        } else {
            $message = "No se encontró la licencia o no se pudo eliminar. Es posible que ya haya sido borrada.";
        }
        $stmt->close();
    } else {
        $message = "No se proporcionó un identificador de licencia válido.";
    }

    $color = $success ? 'green' : 'red';
    $messageSeguro = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $actionUrl = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?Accion=Administrar&Ruc=' . urlencode($Ruc);


    // NUEVO: Guardar el mensaje en la sesión para mostrarlo en la siguiente página.
    $_SESSION['mensaje_flash'] = $message;

    // NUEVO: Redirigir de vuelta a la página de administración de la empresa.
    $redirect_url = $_SERVER['PHP_SELF'] . '?Accion=Administrar&Ruc=' . urlencode($Ruc);
    header('Location: ' . $redirect_url);
    exit;
}


function renderizarBloqueSistema(string $sistemaNombreDisplay, array $stats, ?string $version, bool $usaControlPorSesion): string {
    $textoControl = $usaControlPorSesion ? 'Por Sesión' : 'Por Máquina';
    $claseControl = $usaControlPorSesion ? 'control-sesion' : 'control-maquina';
    $controlInfo = "<span class='control-info {$claseControl}'>{$textoControl}</span>";
    
    $versionHtml = htmlspecialchars($version ?: 'N/A', ENT_QUOTES, 'UTF-8');
    $versionInfo = "<div style='font-size: 0.9em; color: #666; margin-bottom: 10px;'>Versión: <strong>{$versionHtml}</strong> {$controlInfo}</div>";

    $html = "<div style='border: 1px solid #ccc; padding: 15px; border-radius: 5px; flex-grow: 1; min-width: 300px;'>";
    $html .= "<h3>Licencias {$sistemaNombreDisplay}</h3>";
    $html .= $versionInfo;
    $html .= "<em>Licencias básicas compradas: <strong>{$stats['compradas_basicas']}</strong></em><br>";
    $html .= "<em>Licencias básicas en uso: <strong>{$stats['usadas_basicas']}</strong></em><br><br>";

    $modulosHtml = '';
    foreach ($stats['modulos'] as $modulo) {
        if ($modulo['compradas'] > 0 || $modulo['usadas'] > 0) {
            $disponibles = $modulo['compradas'] - $modulo['usadas'];
            $colorDisponibles = $disponibles > 0 ? 'green' : 'black';
            $modulosHtml .= "<em>Módulo {$modulo['nombre']}:</em><br>";
            $modulosHtml .= "<span style='padding-left: 15px;'>Compradas: {$modulo['compradas']}, En uso: {$modulo['usadas']}";
            if ($disponibles >= 0) {
                 $modulosHtml .= ", Disponibles: <strong style='color:{$colorDisponibles};'>{$disponibles}</strong>";
            }
            $modulosHtml .= "</span><br>";
        }
    }
    if (empty($modulosHtml)) {
        $modulosHtml = "<p style='font-style: italic; color: #888;'>No hay módulos adicionales contratados para este sistema.</p>";
    }
    $html .= $modulosHtml;
    $html .= "</div>";
    return $html;
}

function generarHtmlActivaciones(string $Serie, string $sistema, array $stats): string {
    $html = "<div style='border: 1px solid #ccc; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
    $html .= "<h3>Para sistema: {$sistema}</h3>";
    
    $activacionesGeneradas = 0;
    $paramSistema = ($sistema === 'FSOFT') ? 1 : 1;

    $modulosAct = obtenerMapeoDeModulos();

    foreach ($modulosAct as $act) {
        $disponible = true;
        if ($stats['compradas_basicas'] <= $stats['usadas_basicas']) {
            $disponible = false;
        }
        if ($disponible) {
            foreach ($act['mods'] as $modKey) {
                if ($stats['modulos'][$modKey]['compradas'] <= $stats['modulos'][$modKey]['usadas']) {
                    $disponible = false;
                    break;
                }
            }
        }
        if ($disponible) {
            $activacionesGeneradas++;
            $numeroActivacion = ObtActivacion($Serie, $paramSistema, $act['codigo']);
            $html .= "<p>Para <strong>{$act['nombre']}</strong>: <strong style='color:blue; font-size:1.1em;'>{$numeroActivacion}</strong></p>";
        }
    }

    if ($activacionesGeneradas === 0) {
        $html .= "<p><em>No hay suficientes licencias compradas disponibles para generar nuevas activaciones en este sistema.</em></p>";
    }
    
    $html .= "</div>";
    return $html;
}

function licenciaExiste(mysqli $conn, string $Ruc, string $Serie): bool {
    $stmt = $conn->prepare("SELECT 1 FROM Licencias WHERE Ruc = ? AND Serie = ?");
    $stmt->bind_param("ss", $Ruc, $Serie);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function contarLicenciasUsadas(mysqli $conn, string $Ruc): int {
    $stmt = $conn->prepare("SELECT COUNT(PK_Licencia) FROM Licencias WHERE Ruc = ?");
    $stmt->bind_param('s', $Ruc);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_row()[0];
}

function renderizarFormularioEmpresa(mysqli $conn, ?string $Ruc): void {
    $esEdicion = $Ruc !== null;
    $empresa = [];
    $tituloPagina = "Alta de Nueva Empresa";

    // <-- VALIDACIÓN/ELIMINACIÓN: Comprobar si hay datos de un intento fallido en la sesión -->
    $datosFormulario = $_SESSION['form_data_temp'] ?? null;
    $errorFormulario = $_SESSION['form_error_temp'] ?? null;
    unset($_SESSION['form_data_temp'], $_SESSION['form_error_temp']);

    if ($esEdicion) {
        $empresa = obtenerRegistroEmpresa($conn, $Ruc);
        if (!$empresa) {
            die("Error: No se encontró la empresa con RUC " . htmlspecialchars($Ruc));
        }
        $tituloPagina = "Editando Empresa: " . htmlspecialchars($empresa['Nombre']);
    }

    // Usar datos de la sesión si existen (en caso de error de validación), si no, usar los de la BD o por defecto.
    $fuenteDeDatos = $datosFormulario ?? $empresa;
    
    $val = function($key, $default = '') use ($fuenteDeDatos) {
        return htmlspecialchars($fuenteDeDatos[$key] ?? $default, ENT_QUOTES, 'UTF-8');
    };

    $actionUrl = htmlspecialchars($_SERVER['PHP_SELF']);
    $accionProceso = $esEdicion ? 'Procesar_Edicion_Empresa' : 'Procesar_Alta_Empresa';

    // <-- CAMBIO: Lógica para determinar la URL de retorno del botón "Cancelar" -->
    $urlCancelar = $_SERVER['PHP_SELF']; // Por defecto, al dashboard
    $rucContexto = $Ruc ?? $_SESSION['Ruc'] ?? '';
    if (!empty($rucContexto)) {
        $urlCancelar = $_SERVER['PHP_SELF'] . '?Accion=Administrar&Ruc=' . urlencode($rucContexto);
    }
    
    $inicioSuscripcionDefault = $val('Inicio_Suscripcion') ?: date('Y-m-d');
    $finSuscripcionDefault = $val('Fin_Suscripcion') ?: date('Y-m-d', strtotime('+1 year'));

    $enListaNegra = $val('En_Lista_Negra');
    $opcionSiSeleccionada = $enListaNegra === '1' ? 'selected' : '';
    $opcionNoSeleccionada = $enListaNegra !== '1' ? 'selected' : '';

    echo <<<HTML
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>{$tituloPagina}</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 20px; }
        .form-container { max-width: 900px; margin: auto; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .form-header { padding: 20px 30px; background-color: #343a40; color: white; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .form-content { padding: 30px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .input-group { display: flex; flex-direction: column; }
        label { font-weight: bold; margin-bottom: 5px; color: #333; }
        input[type=text], input[type=number], input[type=email], input[type=date], textarea, select {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em;
        }
        input:disabled, input:read-only { background-color: #e9ecef; cursor: not-allowed; }
        .form-actions { text-align: right; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;}
        .btn-submit { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; }
        .btn-submit:hover { background-color: #218838; }
        .btn-cancel { display: inline-block; background-color: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px; }
        .tab-nav { list-style-type: none; padding: 0; margin: 0; border-bottom: 2px solid #dee2e6; display: flex; }
        .tab-nav-item { margin-bottom: -2px; }
        .tab-nav-link { display: block; padding: 10px 20px; border: 2px solid transparent; border-bottom: none; border-top-left-radius: .25rem; border-top-right-radius: .25rem; cursor: pointer; color: #007bff; text-decoration: none; }
        .tab-nav-link.active { color: #495057; background-color: #fff; border-color: #dee2e6 #dee2e6 #fff; }
        .tab-content { display: none; padding-top: 20px; }
        .tab-content.active { display: block; }
        .sub-section-title { margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; font-size: 1.1em; color: #495057; }
    </style>
    </head><body>
    <div class="form-container">
        <div class="form-header"><h1>{$tituloPagina}</h1></div>
        <div class="form-content">
HTML;

    if ($errorFormulario) {
        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px;'>" . htmlspecialchars($errorFormulario) . "</div>";
    }
    if (isset($_SESSION['mensaje_flash_error'])) {
        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px;'>" . htmlspecialchars($_SESSION['mensaje_flash_error']) . "</div>";
        unset($_SESSION['mensaje_flash_error']);
    }

    echo <<<HTML
            <form action="{$actionUrl}" method="POST" id="empresa-form">
                <input type="hidden" name="Accion" value="{$accionProceso}">
                <ul class="tab-nav">
                    <li class="tab-nav-item"><a class="tab-nav-link active" data-tab="tab-general">Datos Principales</a></li>
                    <li class="tab-nav-item"><a class="tab-nav-link" data-tab="tab-suscripcion">Suscripción y Mensajes</a></li>
                    <li class="tab-nav-item"><a class="tab-nav-link" data-tab="tab-licencias">Licencias y Versiones</a></li>
                    <li class="tab-nav-item"><a class="tab-nav-link" data-tab="tab-sistema">Datos de Sistema</a></li>
                </ul>

                <div id="tab-general" class="tab-content active">
                    <div class="form-grid">
                        <div class="input-group"><label for="Nombre">Nombre</label><input type="text" id="Nombre" name="Nombre" value="{$val('Nombre')}" maxlength="30" required></div>
                        <div class="input-group"><label for="RUC">RUC</label><input type="text" id="RUC" name="RUC" value="{$val('RUC')}" maxlength="13" required " . ($esEdicion ? 'readonly' : '') . "></div>
                        <div class="input-group"><label for="Telefono">Teléfono</label><input type="text" id="Telefono" name="Telefono" value="{$val('Telefono')}" maxlength="9"></div>
                        <div class="input-group"><label for="Ciudad">Ciudad</label><input type="text" id="Ciudad" name="Ciudad" value="{$val('Ciudad')}" maxlength="30"></div>
                        <div class="input-group"><label for="eMail">Email</label><input type="email" id="eMail" name="eMail" value="{$val('eMail')}" maxlength="100"></div>
                        <div class="input-group"><label for="eMail2">Email 2</label><input type="email" id="eMail2" name="eMail2" value="{$val('eMail2')}" maxlength="100"></div>
                    </div>
                    <div class="input-group" style="margin-top:20px;"><label for="Comentario">Comentario Interno</label><textarea id="Comentario" name="Comentario" rows="4">{$val('Comentario')}</textarea></div>
                </div>
                <div id="tab-suscripcion" class="tab-content">
                    <!-- Un sólo grid de 3 columnas -->
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem;">
                        <div class="input-group">
                            <label for="Inicio_Suscripcion">Suscrito desde</label>
                            <input type="date" id="Inicio_Suscripcion" name="Inicio_Suscripcion"
                                   value="{$inicioSuscripcionDefault}" required>
                        </div>
                        <div class="input-group">
                            <label for="Fin_Suscripcion">Suscrito hasta</label>
                            <input type="date" id="Fin_Suscripcion" name="Fin_Suscripcion"
                                   value="{$finSuscripcionDefault}" required>
                        </div>
                        <div class="input-group">
                            <label for="En_Lista_Negra">¿Mostrar Mensaje al Iniciar?</label>
                            <select id="En_Lista_Negra" name="En_Lista_Negra">
                                <option value="1" {$opcionSiSeleccionada}>Sí</option>
                                <option value="0" {$opcionNoSeleccionada}>No</option>
                            </select>
                        </div>
                    </div>
                
                    <!-- Mensaje a Mostrar como textarea full-width, 4 filas, mismo estilo que Comentario Interno -->
                    <div class="input-group" style="margin-top:20px;">
                        <label for="Motivo_Lista_Negra">Mensaje a Mostrar</label>
                        <textarea id="Motivo_Lista_Negra" name="Motivo_Lista_Negra" rows="4">{$val('Motivo_Lista_Negra')}</textarea>
                    </div>
                </div>
                <div id="tab-licencias" class="tab-content">
                    <p>Ingrese la cantidad de licencias adquiridas para cada módulo y la versión instalada en la empresa.</p>
                
                    <h4 class="sub-section-title">F-Soft (Escritorio)</h4>
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(4,1fr); gap: 20px;">
                        <div class="input-group">
                            <label for="Version_FSoft">Versión</label>
                            <input type="text" id="Version_FSoft" name="Version_FSoft"
                                   value="{$val('Version_FSoft')}" maxlength="10" readonly>
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_FSOFT_BA">Lic. Básicas</label>
                            <input type="number" id="Cant_Lic_FSOFT_BA" name="Cant_Lic_FSOFT_BA"
                                   value="{$val('Cant_Lic_FSOFT_BA', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_FSOFT_RP">Mód. Nómina</label>
                            <input type="number" id="Cant_Lic_FSOFT_RP" name="Cant_Lic_FSOFT_RP"
                                   value="{$val('Cant_Lic_FSOFT_RP', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_FSOFT_CE">Mód. Comp. Elec.</label>
                            <input type="number" id="Cant_Lic_FSOFT_CE" name="Cant_Lic_FSOFT_CE"
                                   value="{$val('Cant_Lic_FSOFT_CE', 0)}" min="0" style="text-align: right;">
                        </div>
                    </div>
                
                    <h4 class="sub-section-title">L-Soft (Escritorio)</h4>
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(4,1fr); gap: 20px;">
                        <div class="input-group">
                            <label for="Version_LSoft">Versión</label>
                            <input type="text" id="Version_LSoft" name="Version_LSoft"
                                   value="{$val('Version_LSoft')}" maxlength="10" readonly>
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_BA">Lic. Básicas</label>
                            <input type="number" id="Cant_Lic_LSOFT_BA" name="Cant_Lic_LSOFT_BA"
                                   value="{$val('Cant_Lic_LSOFT_BA', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_RP">Mód. Nómina</label>
                            <input type="number" id="Cant_Lic_LSOFT_RP" name="Cant_Lic_LSOFT_RP"
                                   value="{$val('Cant_Lic_LSOFT_RP', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_CE">Mód. Comp. Elec.</label>
                            <input type="number" id="Cant_Lic_LSOFT_CE" name="Cant_Lic_LSOFT_CE"
                                   value="{$val('Cant_Lic_LSOFT_CE', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_AF">Mód. Activos Fijos</label>
                            <input type="number" id="Cant_Lic_LSOFT_AF" name="Cant_Lic_LSOFT_AF"
                                   value="{$val('Cant_Lic_LSOFT_AF', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_OP">Mód. Producción</label>
                            <input type="number" id="Cant_Lic_LSOFT_OP" name="Cant_Lic_LSOFT_OP"
                                   value="{$val('Cant_Lic_LSOFT_OP', 0)}" min="0" style="text-align: right;">
                        </div>
                    </div>
                
                    <h4 class="sub-section-title">L-Soft Web (Blazor)</h4>
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(4,1fr); gap: 20px;">
                         <div class="input-group">
                             <label for="Version_LSoft_Web">Versión</label>
                             <input type="text" id="Version_LSoft_Web" name="Version_LSoft_Web"
                                    value="{$val('Version_LSoft_Web')}" maxlength="10" readonly>
                         </div>
                         <div class="input-group">
                             <label for="Cant_Lic_LSOFTW_BA">Lic. Básicas</label>
                             <input type="number" id="Cant_Lic_LSOFTW_BA" name="Cant_Lic_LSOFTW_BA"
                                    value="{$val('Cant_Lic_LSOFTW_BA', 0)}" min="0" style="text-align: right;">
                         </div>
                         <div class="input-group">
                             <label for="Cant_Lic_LSOFTW_RP">Mód. Nómina</label>
                             <input type="number" id="Cant_Lic_LSOFTW_RP" name="Cant_Lic_LSOFTW_RP"
                                    value="{$val('Cant_Lic_LSOFTW_RP', 0)}" min="0" style="text-align: right;">
                         </div>
                         <div class="input-group">
                             <label for="Cant_Lic_LSOFTW_AF">Mód. Activos Fijos</label>
                             <input type="number" id="Cant_Lic_LSOFTW_AF" name="Cant_Lic_LSOFTW_AF"
                                    value="{$val('Cant_Lic_LSOFTW_AF', 0)}" min="0" style="text-align: right;">
                         </div>
                         <div class="input-group">
                             <label for="Cant_Lic_LSOFTW_OP">Mód. Producción</label>
                             <input type="number" id="Cant_Lic_LSOFTW_OP" name="Cant_Lic_LSOFTW_OP"
                                    value="{$val('Cant_Lic_LSOFTW_OP', 0)}" min="0" style="text-align: right;">
                         </div>
                    </div>
                </div>

                <div id="tab-sistema" class="tab-content">
                     <div class="form-grid">
                        <div class="input-group"><label for="Codigo">Código</label><input type="text" id="Codigo" value="{$val('Codigo')}" disabled></div>
                        <div class="input-group"><label for="Alta">Fecha de Alta</label><input type="text" id="Alta" value="{$val('Alta')}" readonly></div>
                        <div class="input-group"><label for="Cant_Ingresos">Cantidad de Ingresos</label><input type="number" id="Cant_Ingresos" value="{$val('Cant_Ingresos')}" disabled></div>
                    </div>
                </div>
            </form>
            <div class="form-actions">
                <button type="submit" form="empresa-form" class="btn-submit">Guardar Cambios</button>
HTML;

    if ($esEdicion && usuarioPuedeEliminarEmpresa()) {
        $rucSeguro = htmlspecialchars($Ruc, ENT_QUOTES, 'UTF-8');
        $confirmarJS = "return confirm('¿Está SEGURO de que desea ELIMINAR esta empresa? Esta acción es irreversible y solo se puede hacer si no tiene licencias activas.');";
        echo "<form action='{$actionUrl}?Ruc={$rucSeguro}' method='POST' style='display:inline-block; margin-left:10px;'>
                  <input type='hidden' name='Accion' value='Procesar_Eliminar_Empresa'>
                  <button type='submit' onclick=\"{$confirmarJS}\"
                          style='background-color:#d9534f; color:white; border:none; padding:12px 20px; border-radius:5px; cursor:pointer; font-size:1.1em;'>
                      Eliminar Empresa
                  </button>
              </form>";
    }
    // <-- CAMBIO: El href del botón "Cancelar" ahora usa la URL dinámica -->
    echo <<<HTML
                <a href="{$urlCancelar}" class="btn-cancel">Cancelar</a>
            </div>
        </div>
    </div>    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.tab-nav-link');
            const tabContents = document.querySelectorAll('.tab-content');
            tabLinks.forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const tabId = this.getAttribute('data-tab');
                    tabLinks.forEach(l => l.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
    </body></html>
HTML;
    exit;
}


function procesarFormularioEmpresa(mysqli $conn, ?string $RucEdicion): void {
    
    // Estandarización de nombres de campos
    $campos_a_procesar = [
        'RUC', 'Nombre', 'Telefono', 'Ciudad', 'eMail', 'eMail2', 'Comentario',
        'Inicio_Suscripcion', 'Fin_Suscripcion', 'En_Lista_Negra', 'Motivo_Lista_Negra',
        'Version_FSoft', 'Version_LSoft', 'Version_LSoft_Web',
        'Cant_Lic_FSOFT_BA', 'Cant_Lic_FSOFT_RP', 'Cant_Lic_FSOFT_CE', 
        'Cant_Lic_LSOFT_BA', 'Cant_Lic_LSOFT_RP', 'Cant_Lic_LSOFT_CE', 'Cant_Lic_LSOFT_AF', 'Cant_Lic_LSOFT_OP',
        'Cant_Lic_LSOFTW_BA', 'Cant_Lic_LSOFTW_RP', 'Cant_Lic_LSOFTW_AF', 'Cant_Lic_LSOFTW_OP'
    ];
    
    $datos = [];
    foreach ($campos_a_procesar as $campo) {
        if (strpos($campo, 'Cant_Lic_') === 0) {
            $datos[$campo] = (int)($_POST[$campo] ?? 0);
        } else {
            $datos[$campo] = $_POST[$campo] ?? null;
        }
    }

    $esEdicion = $RucEdicion !== null;

    // <-- VALIDACIÓN: Validar RUC duplicado solo en modo ALTA -->
    if (!$esEdicion) {
        $ruc_a_validar = $datos['RUC'];
        if (empty($ruc_a_validar)) {
            $_SESSION['form_data_temp'] = $_POST;
            $_SESSION['form_error_temp'] = "El campo RUC es obligatorio.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Alta_Empresa');
            exit;
        }

        $stmt_check = $conn->prepare("SELECT 1 FROM Empresas WHERE RUC = ?");
        $stmt_check->bind_param("s", $ruc_a_validar);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            // RUC duplicado encontrado
            $_SESSION['form_data_temp'] = $_POST; // Guardar todos los datos del POST
            $_SESSION['form_error_temp'] = "El RUC '{$ruc_a_validar}' ya está registrado. Por favor, ingrese uno diferente.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Alta_Empresa');
            exit;
        }
    }

    if ($esEdicion) {
        // --- LÓGICA DE UPDATE ---
        $datos['RUC'] = $RucEdicion;
        $set_parts = [];
        $params = [];
        $types = '';
        $campos_para_set = array_diff($campos_a_procesar, ['RUC']);
        foreach ($campos_para_set as $campo) {
            $set_parts[] = "`$campo` = ?";
            $params[] = &$datos[$campo];
            $types .= (strpos($campo, 'Cant_Lic_') === 0 || strpos($campo, 'En_Lista_Negra') !== false) ? 'i' : 's';
        }
        $sql = "UPDATE Empresas SET " . implode(', ', $set_parts) . " WHERE RUC = ?";
        $types .= 's';
        $params[] = &$RucEdicion;

    } else {
        // --- LÓGICA DE INSERT ---
        $datos['Alta'] = date("Y-m-d H:i:s");
        $campos_para_insert = array_merge($campos_a_procesar, ['Alta']);
        $placeholders = implode(', ', array_fill(0, count($campos_para_insert), '?'));
        $columnas = implode('`, `', $campos_para_insert);
        $sql = "INSERT INTO Empresas (`$columnas`) VALUES ($placeholders)";
        $params = [];
        $types = '';
        foreach ($campos_para_insert as $campo) {
            $params[] = &$datos[$campo];
            $types .= (strpos($campo, 'Cant_Lic_') === 0 || strpos($campo, 'En_Lista_Negra') !== false) ? 'i' : 's';
        }
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) { die("Error al preparar la sentencia: " . $conn->error); }
    
    // bind_param necesita referencias.
    $stmt->bind_param($types, ...array_values($params));

    if ($stmt->execute()) {
        // <-- CAMBIO: Simplificado el mensaje de éxito -->
        $accionRealizada = $esEdicion ? "actualizado" : "creado";
        $_SESSION['mensaje_flash'] = "La empresa se ha {$accionRealizada} correctamente.";
        
        $rucFinal = $esEdicion ? $RucEdicion : $datos['RUC'];
        $_SESSION['Ruc'] = $rucFinal;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Administrar&Ruc=' . urlencode($rucFinal));
    } else {
        die("Error al ejecutar la operación: " . $stmt->error);
    }
    exit;
}


function procesarBusquedaLive(mysqli $conn): void {
    header('Content-Type: application/json');
    $terminoBusqueda = $_GET['q'] ?? '';
    if (strlen($terminoBusqueda) < 2) {
        echo json_encode([]);
        exit;
    }
    $empresas = [];
    $param = "%" . $terminoBusqueda . "%";
    $sql = "SELECT Nombre, RUC, Ciudad, Telefono, Alta, Fin_Suscripcion 
            FROM Empresas 
            WHERE Nombre LIKE ? OR RUC LIKE ?
            ORDER BY Nombre ASC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $param, $param);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['Alta'] = $row['Alta'] ? date('d/m/Y', strtotime($row['Alta'])) : 'N/A';
            $row['Fin_Suscripcion'] = $row['Fin_Suscripcion'] ? date('d/m/Y', strtotime($row['Fin_Suscripcion'])) : 'N/A';
            $empresas[] = $row;
        }
        $stmt->close();
    }
    echo json_encode($empresas);
    exit;
}

// <-- NUEVO: Función para la vista inicial sin empresa seleccionada -->
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


function renderizarScriptBusqueda(): string {
    return <<<JS
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const campoBusqueda = document.getElementById('campo-busqueda-nombre');
            const contenedorResultados = document.getElementById('resultados-busqueda');
            let debounceTimer;
            campoBusqueda.addEventListener('keyup', function() {
                clearTimeout(debounceTimer);
                const termino = campoBusqueda.value.trim();
                if (termino.length < 2) {
                    contenedorResultados.innerHTML = '';
                    return;
                }
                debounceTimer = setTimeout(() => {
                    contenedorResultados.innerHTML = '<p>Buscando...</p>';
                    fetch(`?Accion=Buscar_Empresas_Live&q=\${encodeURIComponent(termino)}`)
                        .then(response => {
                            if (!response.ok) { throw new Error('Error en la red o en el servidor'); }
                            return response.json();
                        })
                        .then(empresas => {
                            contenedorResultados.innerHTML = '';
                            if (empresas.length === 0) {
                                contenedorResultados.innerHTML = '<p style="color: #6c757d;">No se encontraron empresas.</p>';
                                return;
                            }
                            const tabla = document.createElement('table');
                            tabla.style.width = '100%';
                            tabla.style.borderCollapse = 'collapse';
                            tabla.innerHTML = `
                                <thead style="background-color: #e9ecef;">
                                    <tr>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Nombre</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">RUC</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Ciudad</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Teléfono</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Alta</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Fin Suscripción</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Acción</th>
                                    </tr>
                                </thead>`;
                            const tbody = document.createElement('tbody');
                            empresas.forEach(empresa => {
                                const fila = document.createElement('tr');
                                fila.innerHTML = `
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Nombre}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.RUC}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Ciudad || '-'}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Telefono || '-'}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Alta}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Fin_Suscripcion}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">
                                        <a href="?Accion=Administrar&Ruc=\${empresa.RUC}" style="background-color: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px;">Administrar</a>
                                    </td>`;
                                tbody.appendChild(fila);
                            });
                            tabla.appendChild(tbody);
                            contenedorResultados.appendChild(tabla);
                        })
                        .catch(error => {
                            console.error('Error en la búsqueda:', error);
                            contenedorResultados.innerHTML = '<p style="color: red;">Ocurrió un error al realizar la búsqueda.</p>';
                        });
                }, 300);
            });
        });
    </script>
JS;
}

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

// <-- VALIDACIÓN/ELIMINACIÓN: Nueva función para procesar la eliminación de una empresa -->
function procesarEliminarEmpresa(mysqli $conn, string $Ruc): void {
    // 1. Verificar si la empresa tiene licencias activas
    $licenciasCount = contarLicenciasUsadas($conn, $Ruc);
    if ($licenciasCount > 0) {
        // No se puede eliminar, redirigir con mensaje de error
        $_SESSION['mensaje_flash_error'] = "No se puede eliminar la empresa porque tiene {$licenciasCount} licencia(s) registrada(s).";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Editar_Empresa&Ruc=' . urlencode($Ruc));
        exit;
    }

    // 2. Proceder con la eliminación
    $stmt = $conn->prepare("DELETE FROM Empresas WHERE Ruc = ?");
    $stmt->bind_param("s", $Ruc);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje_flash'] = "La empresa con RUC {$Ruc} ha sido eliminada correctamente.";
        unset($_SESSION['Ruc']); // Limpiar el RUC de la sesión
        header('Location: ' . $_SERVER['PHP_SELF']); // Redirigir al dashboard
    } else {
        $_SESSION['mensaje_flash_error'] = "Ocurrió un error al intentar eliminar la empresa: " . $stmt->error;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Editar_Empresa&Ruc=' . urlencode($Ruc));
    }
    exit;
}