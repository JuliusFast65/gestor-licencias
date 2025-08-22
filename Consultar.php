<?php
// Verificar si este archivo se está ejecutando directamente o siendo incluido
$es_archivo_principal = !defined('CONSULTAR_INCLUDED');

// Solo configurar headers si es el archivo principal
if ($es_archivo_principal) {
    // Iniciar output buffering para capturar cualquier salida no deseada
    ob_start();
    
    header('Content-Type: text/html; charset=utf-8');
    date_default_timezone_set('America/Bogota');
}

session_start();

// ===================================================================
// INCLUIR AUTENTICACIÓN ANTES DE CUALQUIER USO DE SESIÓN
// ===================================================================
require_once(__DIR__ . '/php/auth.php');
// ===================================================================
// FIN DE AUTENTICACIÓN
// ===================================================================

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

// Conexión a la base de datos (necesaria para todas las operaciones)
try {
    require_once(__DIR__ . '/apis/Conectar_BD.php');
    $conn = Conectar_BD();
} catch (Exception $e) {
    error_log('Error de conexión a la BD: ' . $e->getMessage());
    die('No se puede establecer conexión con la base de datos.');
}

// Incluir solo las funciones necesarias para el procesamiento temprano
require_once(__DIR__ . '/php/permissions.php');
require_once(__DIR__ . '/php/companies.php');
require_once(__DIR__ . '/php/profiles.php');

// La lógica de autenticación ahora está en php/auth.php
// ===================================================================
// FIN DE LA LÓGICA DE AUTENTICACIÓN
// ===================================================================

// El sistema de permisos ahora está en php/permissions.php
// ===================================================================
// FIN DEL SISTEMA DE PERMISOS
// ===================================================================

// --- Configuración e Inicialización ---
require_once('ObtActivacion.php');

// --- Lógica Principal (Controlador) ---
$Ruc = $_SESSION['Ruc'] ?? '';
$Accion = $_GET['Accion'] ?? $_POST['Accion'] ?? 'Dashboard';

// Procesar acciones que requieren JSON ANTES de cualquier salida HTML
if ($Accion === 'Buscar_Empresas_Live') {
    procesarBusquedaLive($conn);
    exit; // Salir inmediatamente después de procesar la búsqueda
}

// Procesar acciones de formulario ANTES de cualquier salida HTML
if ($Accion === 'Procesar_Alta_Empresa') {
    verificarPermisoYSalir(usuarioPuedeCrearEmpresa());
    procesarFormularioEmpresa($conn, null);
    exit;
}

if ($Accion === 'Procesar_Edicion_Empresa') {
    verificarPermisoYSalir(usuarioPuedeEditarEmpresa($Ruc));
    procesarFormularioEmpresa($conn, $Ruc);
    exit;
}

if ($Accion === 'Procesar_Eliminar_Empresa') {
    verificarPermisoYSalir(usuarioPuedeEliminarEmpresa());
    procesarEliminarEmpresa($conn, $Ruc);
    exit;
}

if ($Accion === 'Procesar_Edicion_Perfil') {
    procesarEdicionPerfil($conn);
    exit;
}

// Procesar redirecciones ANTES de cualquier salida HTML
// Procesar redirecciones de RUC
if (isset($_REQUEST['Ruc']) && !empty($_REQUEST['Ruc'])) {
    $ruc_entrante = trim($_REQUEST['Ruc']);
    $ruc_sesion_actual = $_SESSION['Ruc'] ?? null;
    if ($ruc_entrante !== $ruc_sesion_actual) {
        // Verificar si hay salida antes de la redirección
        if ($es_archivo_principal && ob_get_length() > 0) {
            $output = ob_get_clean();
            error_log('Salida detectada antes de redirección: ' . substr($output, 0, 200));
        }
        
        $_SESSION['Ruc'] = $ruc_entrante;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Administrar');
        exit;
    }
}

$acciones_requieren_ruc = ['Administrar', 'Editar_Empresa', 'Procesar_Edicion_Empresa', 'Eliminar_Empresa', 'Procesar_Eliminar_Empresa', 'Baja', 'Baja_Sesiones', 'Alta', 'Activar'];
if (in_array($Accion, $acciones_requieren_ruc) && empty($Ruc)) {
    // Verificar si hay salida antes de la redirección
    if ($es_archivo_principal && ob_get_length() > 0) {
        $output = ob_get_clean();
        error_log('Salida detectada antes de redirección (RUC vacío): ' . substr($output, 0, 200));
    }
    
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
        


    case 'Alta_Empresa':
        verificarPermisoYSalir(usuarioPuedeCrearEmpresa());
        renderizarFormularioEmpresa($conn, null);
        break;
    case 'Editar_Empresa':
        verificarPermisoYSalir(usuarioPuedeEditarEmpresa($Ruc));
        renderizarFormularioEmpresa($conn, $Ruc);
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

        
    default:
        renderizarDashboard($conn);
        break;
}

$conn->close();

// ===================================================================
// INCLUIR ARCHIVOS DESPUÉS DE LAS REDIRECCIONES
// ===================================================================
// ===================================================================
// FIN DE INCLUSIONES
// ===================================================================

// Marcar que este archivo ha sido incluido (para futuras inclusiones)
define('CONSULTAR_INCLUDED', true);

// Limpiar el buffer de salida si es el archivo principal
if ($es_archivo_principal) {
    ob_end_flush();
}

// --- Bloque de Funciones ---

// La función renderizarFormularioLogin está en php/auth.php

function renderizarPaginaAdministracion(mysqli $conn, string $Ruc): void {
    $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
    $perfilUsuario = htmlspecialchars($_SESSION['perfil'] ?? 'Sin perfil', ENT_QUOTES, 'UTF-8');
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
            Bienvenido, <strong>{$nombreUsuario}</strong> ({$perfilUsuario}) | <a href="?Accion=Mi_Perfil" style="color:white; text-decoration: underline;">Mi Perfil</a> | <a href="?Accion=Logout" style="color: #ffc107; text-decoration: none;">Cerrar Sesión</a>
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
    
    // Nueva lógica basada en campos de tipo de licenciamiento
    // Si viene nulo o en blanco, se considera Máquina (M)
    $tipoFsoft = trim($empresa['Tipo_Lic_FSOFT'] ?? 'M');
    $tipoLsoft = trim($empresa['Tipo_Lic_LSOFT'] ?? 'M');
    $controlFsoftPorSesion = !empty($tipoFsoft) && $tipoFsoft === 'S';
    $controlLsoftPorSesion = !empty($tipoLsoft) && $tipoLsoft === 'S';
    
    // LSOFT Web: Solo si hay licencias compradas
    $controlLsoftWebPorSesion = !empty($empresa['Version_LSoft_Web']) && 
        ((int)($empresa['Cant_Lic_LSOFTW_BA'] ?? 0) > 0 || 
         (int)($empresa['Cant_Lic_LSOFTW_RP'] ?? 0) > 0 || 
         (int)($empresa['Cant_Lic_LSOFTW_AF'] ?? 0) > 0 || 
         (int)($empresa['Cant_Lic_LSOFTW_OP'] ?? 0) > 0);
    
    // Si hay mezcla (uno 'M' y otro 'S'), se toma el nuevo licenciamiento (Sesión)
    $esNuevoModelo = $controlFsoftPorSesion || $controlLsoftPorSesion || $controlLsoftWebPorSesion;

    $licencias = obtenerTodasLasLicenciasPorRuc($conn, $Ruc);
    $sesionesActivas = $esNuevoModelo ? obtenerSesionesActivasPorRuc($conn, $Ruc) : [];

    $statsFsoft = calcularEstadisticasLicencias($licencias, $empresa, 'FSOFT', $sesionesActivas);
    $statsLsoft = calcularEstadisticasLicencias($licencias, $empresa, 'LSOFT', $sesionesActivas);
    $statsLsoftWeb = calcularEstadisticasLicencias($licencias, $empresa, 'LSOFTW', $sesionesActivas);
    
    echo "<div style='display:flex; flex-wrap: wrap; gap: 40px; margin-bottom: 30px;'>";
    echo renderizarBloqueSistema('F-Soft', $statsFsoft, $empresa['Version_FSoft'] ?? null, $controlFsoftPorSesion, $tipoFsoft);
    echo renderizarBloqueSistema('L-Soft', $statsLsoft, $empresa['Version_LSoft'] ?? null, $controlLsoftPorSesion, $tipoLsoft);
    echo renderizarBloqueSistema('L-Soft Web', $statsLsoftWeb, $empresa['Version_LSoft_Web'] ?? null, true, 'S');
    echo "</div>";

    $totalLicenciasCompradas = (int)($empresa['Cant_Lic_FSOFT_BA'] ?? 0) + (int)($empresa['Cant_Lic_LSOFT_BA'] ?? 0);
    
    echo renderizarTablaLicencias($conn, $licencias, $totalLicenciasCompradas, $esNuevoModelo, $sesionesActivas);

    echo "</div>";
    echo renderizarScriptBusqueda();
    echo "</body></html>";
    exit;
}

// La función obtenerRegistroEmpresa está en php/companies.php

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

    // Nueva lógica basada en campos de tipo de licenciamiento
    // Si viene nulo o en blanco, se considera Máquina (M)
    $usarModeloPorSesion = false;
    if ($sistema === 'FSOFT') {
        $tipoFsoft = trim($empresa['Tipo_Lic_FSOFT'] ?? 'M');
        $usarModeloPorSesion = !empty($tipoFsoft) && $tipoFsoft === 'S';
    } elseif ($sistema === 'LSOFT') {
        $tipoLsoft = trim($empresa['Tipo_Lic_LSOFT'] ?? 'M');
        $usarModeloPorSesion = !empty($tipoLsoft) && $tipoLsoft === 'S';
    } elseif ($sistema === 'LSOFTW') {
        // LSOFT Web: Solo si hay licencias compradas
        $usarModeloPorSesion = !empty($empresa['Version_LSoft_Web']) && 
            ((int)($empresa['Cant_Lic_LSOFTW_BA'] ?? 0) > 0 || 
             (int)($empresa['Cant_Lic_LSOFTW_RP'] ?? 0) > 0 || 
             (int)($empresa['Cant_Lic_LSOFTW_AF'] ?? 0) > 0 || 
             (int)($empresa['Cant_Lic_LSOFTW_OP'] ?? 0) > 0);
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
            $sistemaLicencia = strtoupper($l['Sistema']);
            
            // Filtrar sesiones solo del sistema de esta licencia
            $sesionesFiltradas = null;
            if (isset($sesionesPorSerie[$l['Serie']])) {
                $sesionesFiltradas = [
                    'fsoft_ba' => 0, 'fsoft_mod' => 0, 
                    'lsoft_ba' => 0, 'lsoft_mod' => 0, 
                    'lsoftw_ba' => 0, 'lsoftw_mod' => 0, 
                    'detalles' => []
                ];
                
                foreach ($sesionesPorSerie[$l['Serie']]['detalles'] as $sesion) {
                    if (strpos($sesion['tipo'], $sistemaLicencia . '_') === 0) {
                        $tipo = $sesion['tipo'];
                        if ($tipo === 'FSOFT_BA') $sesionesFiltradas['fsoft_ba']++;
                        elseif (strpos($tipo, 'FSOFT_') === 0) $sesionesFiltradas['fsoft_mod']++;
                        elseif ($tipo === 'LSOFT_BA') $sesionesFiltradas['lsoft_ba']++;
                        elseif (strpos($tipo, 'LSOFT_') === 0) $sesionesFiltradas['lsoft_mod']++;
                        elseif ($tipo === 'LSOFTW_BA') $sesionesFiltradas['lsoftw_ba']++;
                        elseif (strpos($tipo, 'LSOFTW_') === 0) $sesionesFiltradas['lsoftw_mod']++;
                        $sesionesFiltradas['detalles'][] = $sesion;
                    }
                }
            }
            
            $tieneSesiones = !empty($sesionesFiltradas) && !empty($sesionesFiltradas['detalles']);
            
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
            $html .= "<td>" . ($sesionesFiltradas['fsoft_ba'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesionesFiltradas['fsoft_mod'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesionesFiltradas['lsoft_ba'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesionesFiltradas['lsoft_mod'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesionesFiltradas['lsoftw_ba'] ?? 0) . "</td>";
            $html .= "<td>" . ($sesionesFiltradas['lsoftw_mod'] ?? 0) . "</td>";
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
                foreach ($sesionesFiltradas['detalles'] as $detalle) {
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

// La función renderizarWidgetBusquedaYComando está en php/companies.php

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


function renderizarBloqueSistema(string $sistemaNombreDisplay, array $stats, ?string $version, bool $usaControlPorSesion, string $tipoLicenciamiento = 'M'): string {
    $textoControl = $usaControlPorSesion ? 'Por Sesión' : 'Por Máquina';
    $claseControl = $usaControlPorSesion ? 'control-sesion' : 'control-maquina';
    $controlInfo = "<span class='control-info {$claseControl}'>{$textoControl}</span>";
    
    // Si viene nulo o en blanco, se considera Máquina (M)
    $tipoLicTrim = trim($tipoLicenciamiento);
    $tipoLicText = (!empty($tipoLicTrim) && $tipoLicTrim === 'S') ? 'Sesión' : 'Máquina';
    $versionHtml = htmlspecialchars($version ?: 'N/A', ENT_QUOTES, 'UTF-8');
    $versionInfo = "<div style='font-size: 0.9em; color: #666; margin-bottom: 10px;'>Versión: <strong>{$versionHtml}</strong> | Tipo: <strong>{$tipoLicText}</strong> {$controlInfo}</div>";

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

// La función renderizarFormularioEmpresa está en php/companies.php


// La función procesarFormularioEmpresa está en php/companies.php


// La función procesarBusquedaLive está en php/companies.php

// La función renderizarDashboard está en php/profiles.php


// La función renderizarScriptBusqueda está en php/companies.php

// La función obtenerMapeoDeModulos está en php/profiles.php

// La función renderizarPaginaPerfil está en php/profiles.php

// La función procesarEdicionPerfil está en php/profiles.php

// La función procesarEliminarEmpresa está en php/companies.php