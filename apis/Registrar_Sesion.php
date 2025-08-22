<?php
// -----------------------------------------------------------------------------
// Sesión y Validación de Licencias API
// Este script procesa peticiones de sesión, valida firma HMAC y maneja
// inserción de sesiones de los ERPs de Listosoft por licencias solicitadas.
// -----------------------------------------------------------------------------

// Setear en 1  para ver errores fatales, 0 cuando vaya a producción
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); // Aquí va E_ALL para ver todos los errores, 0 ninguno

header('Content-Type: application/json');
date_default_timezone_set('America/Guayaquil');

// Permite activar logs de depuración (en Debug_Config.php se define log_debug)
define('DEBUG_MODE', true);
require_once 'Debug_Config.php';
require_once 'Conectar_BD.php';
require_once 'Validar_Firma.php';

try {
    // Conexión y transacción
    $mysqli = Conectar_BD();
    $mysqli->begin_transaction();
    
    // Validación de la firma en la petición
    $input = validarPeticion();

    $RUC                   = $input['RUC'] ?? null;
    $Serie                 = $input['Serie'] ?? null;
    $usuario               = $input['usuario'] ?? null;
    $licencias_solicitadas = array_map('strtoupper', $input['licencias'] ?? []);
    $ping_token            = $input['ping_token'] ?? null;

    if (!$RUC || !$usuario || empty($licencias_solicitadas)) {
        throw new Exception('Datos incompletos: RUC, usuario y licencias son requeridos.', 400);
    }
    log_debug("Datos validados - RUC: $RUC, Serie: $Serie, Usuario: $usuario");

    // Mapeo códigos de licencias a descripciones para mensajes de bloqueo
    $tipo_descripcion = [
        'FSOFT_BA'  => 'Licencia básica',
        'FSOFT_RP'  => 'Licencia de nómina',
        'FSOFT_CE'  => 'Licencia de comprobantes electrónicos',
        'FSOFT_AF'  => 'Licencia de activos fijos',
        'FSOFT_OP'  => 'Licencia de producción',
        'FSOFT_OT'  => 'Licencia de órdenes de trabajo',
        'FSOFT_PV'  => 'Licencia de punto de ventas',
        'LSOFT_BA'  => 'Licencia básica',
        'LSOFT_RP'  => 'Licencia de nómina',
        'LSOFT_CE'  => 'Licencia de comprobantes electrónicos',
        'LSOFT_AF'  => 'Licencia de activos fijos',
        'LSOFT_OP'  => 'Licencia de producción',
        'LSOFT_OT'  => 'Licencia de órdenes de trabajo',
        'LSOFT_PV'  => 'Licencia de punto de ventas',
        'LSOFTW_BA' => 'Licencia básica',
        'LSOFTW_RP' => 'Licencia de nómina',
        'LSOFTW_AF' => 'Licencia de activos fijos',
        'LSOFTW_OP' => 'Licencia de producción',
    ];


    // Consulta cupos y sesiones
    $sql_cupos = "SELECT Cant_Lic_FSOFT_BA, Cant_Lic_FSOFT_CE, Cant_Lic_FSOFT_RP,
                   Cant_Lic_LSOFT_BA, Cant_Lic_LSOFT_CE, Cant_Lic_LSOFT_RP,
                   Cant_Lic_LSOFT_AF, Cant_Lic_LSOFT_OP, Cant_Lic_LSOFT_OT,
                   Cant_Lic_LSOFT_PV, Cant_Lic_LSOFTW_BA, Cant_Lic_LSOFTW_RP,
                   Cant_Lic_LSOFTW_AF, Cant_Lic_LSOFTW_OP
            FROM Empresas WHERE Ruc = ?";
    $stmt_cupos = $mysqli->prepare($sql_cupos);
    $stmt_cupos->bind_param('s', $RUC);
    $stmt_cupos->execute();
    $cupos = $stmt_cupos->get_result()->fetch_assoc();
    if (!$cupos) {
        throw new Exception('Empresa no encontrada o sin licencias configuradas.', 404);
    }
    log_debug('Cupos de licencias obtenidos.');

    // Conteo sesiones activas (excluyendo hibernadas)
    $stmt_conteo = $mysqli->prepare(
        "SELECT tipo, COUNT(DISTINCT Serie) AS total
         FROM sesiones_erp
         WHERE Ruc = ? AND Serie != ? AND (estado = 'A' OR estado IS NULL)
         GROUP BY tipo"
    );
    $stmt_conteo->bind_param('ss', $RUC, $Serie);
    $stmt_conteo->execute();
    $sesiones_activas = [];
    $res_conteo = $stmt_conteo->get_result();
    while ($row = $res_conteo->fetch_assoc()) {
        $sesiones_activas[$row['tipo']] = $row['total'];
    }
    log_debug('Conteo de sesiones activas obtenido.');

    // Procesar ping_token y generar uno nuevo
    if ($ping_token) {
        log_debug('Eliminando sesiones anteriores para ping_token.');
        $stmt_del = $mysqli->prepare('DELETE FROM sesiones_erp WHERE ping_token = ?');
        $stmt_del->bind_param('s', $ping_token);
        $stmt_del->execute();
    }
    $nuevo_ping_token = hash('sha256', $RUC . ($Serie ?? 'WEB') . $usuario . microtime(true) . random_int(1000, 9999));
    log_debug('Nuevo ping_token: ' . $nuevo_ping_token);

    // Preparar inserciones
    $stmt_insert_sesion = $mysqli->prepare(
        'INSERT INTO sesiones_erp (RUC, Serie, usuario, tipo, ping_token, fecha_inicio, ultima_actividad)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt_insert_licencia = $mysqli->prepare(
        "INSERT INTO Licencias (RUC, Serie, Maquina, Sistema, Tipo_Licencia, Alta)
         VALUES (?, ?, ?, 'LSOFTW', 1, NOW())"
    );

    $nuevo_serial_generado = null;
    $licencias_permitidas = [];
    $licencias_bloqueadas = [];
    $detalle_bloqueos     = [];
    $bases_permitidas     = [];

    // Pasada 1: Licencias base (_BA)
    foreach ($licencias_solicitadas as $tipo) {
        if (substr($tipo, -3) !== '_BA') continue;
        $desc = $tipo_descripcion[$tipo] ?? $tipo;
        $serie_lic = $Serie;
        if (strpos($tipo, 'LSOFTW_') === 0 && empty($serie_lic)) {
            if (!$nuevo_serial_generado) {
                $nuevo_serial_generado = generarSerialUnico($mysqli);
                $serie_lic = $nuevo_serial_generado;
                log_debug('Serial web generado: ' . $serie_lic);
                $nombre_maquina = 'Navegador Web (' . ($input['info_nav'] ?? 'nd') . ')';
                $stmt_insert_licencia->bind_param('sss', $RUC, $serie_lic, $nombre_maquina);
                if (!$stmt_insert_licencia->execute()) {
                    throw new Exception('Error al registrar licencia de navegador.', 500);
                }
            } else {
                $serie_lic = $nuevo_serial_generado;
            }
        }
        if (empty($serie_lic)) {
            $licencias_bloqueadas[] = $tipo;
            $detalle_bloqueos[] = "$desc: se requiere número de Serie.";
            continue;
        }
        $campo_cupo = 'Cant_Lic_' . $tipo;
        $disp       = $cupos[$campo_cupo] ?? 0;
        $en_uso     = $sesiones_activas[$tipo] ?? 0;
        if ($disp > 0 && $en_uso < $disp) {
            $stmt_insert_sesion->bind_param('sssss', $RUC, $serie_lic, $usuario, $tipo, $nuevo_ping_token);
            if ($stmt_insert_sesion->execute()) {
                $licencias_permitidas[]     = $tipo;
                $bases_permitidas[]         = $tipo;
                log_debug('Base registrada: ' . $tipo);
            } else {
                throw new Exception('Error al insertar sesión base ' . $tipo, 500);
            }
        } else {
            $licencias_bloqueadas[] = $tipo;
            if ($disp <= 0) {
                $detalle_bloqueos[] = "$desc no adquirida.";
            } else {
                $detalle_bloqueos[] = "Sin cupos disponibles para $desc.";
            }
        }
    }

    // Pasada 2: Módulos (sin _BA)
    foreach ($licencias_solicitadas as $tipo) {
        if (substr($tipo, -3) === '_BA') continue;
        $desc    = $tipo_descripcion[$tipo] ?? $tipo;
        $base_req = explode('_', $tipo)[0] . '_BA';
        $base_desc = $tipo_descripcion[$base_req] ?? $base_req;
        if (!in_array($base_req, $bases_permitidas)) {
            $licencias_bloqueadas[] = $tipo;
            $detalle_bloqueos[] = "$desc requiere la $base_desc activa.";
            continue;
        }
        $serie_lic = (strpos($tipo, 'LSOFTW_') === 0) ? $nuevo_serial_generado : $Serie;
        if (empty($serie_lic)) {
            $licencias_bloqueadas[] = $tipo;
            $detalle_bloqueos[] = "$desc: se requiere número de Serie.";
            continue;
        }
        $campo_cupo = 'Cant_Lic_' . $tipo;
        $disp       = $cupos[$campo_cupo] ?? 0;
        $en_uso     = $sesiones_activas[$tipo] ?? 0;
        if ($disp > 0 && $en_uso < $disp) {
            $stmt_insert_sesion->bind_param('sssss', $RUC, $serie_lic, $usuario, $tipo, $nuevo_ping_token);
            if ($stmt_insert_sesion->execute()) {
                $licencias_permitidas[] = $tipo;
                log_debug('Módulo registrado: ' . $tipo);
            } else {
                throw new Exception('Error al insertar sesión módulo ' . $tipo, 500);
            }
        } else {
            $licencias_bloqueadas[] = $tipo;
            if ($disp <= 0) {
                $detalle_bloqueos[] = "$desc no adquirida.";
            } else {
                $detalle_bloqueos[] = "Sin cupos disponibles para $desc.";
            }
        }
    }

    // FASE 3: Commit y respuesta
    $mysqli->commit();
    log_debug('Commit exitoso');

    $response = [
        'Fin'                  => 'OK',
        'Mensaje'              => 'Sesión procesada.',
        'ping_token'           => $nuevo_ping_token,
        'nuevo_serial'         => $nuevo_serial_generado,
        'licencias_permitidas' => array_unique($licencias_permitidas),
        'licencias_bloqueadas' => array_unique($licencias_bloqueadas),
        'detalle_bloqueos'     => $detalle_bloqueos
    ];

    http_response_code(200);
    echo json_encode($response);
    log_debug('Respuesta enviada');

} catch (Exception $e) {
    $mysqli->rollback();
    log_debug('Error: ' . $e->getMessage() . ' Rollback.');
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    echo json_encode([ 'Fin' => 'Error', 'Mensaje' => $e->getMessage() ]);
} finally {
    if ($mysqli) {
        $mysqli->close();
    }
}

//-----------------------------------------------------------------------------
// Función auxiliar: generar serial único
//-----------------------------------------------------------------------------
function generarSerialUnico(mysqli $conn): string
{
    $caracteres = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $len = strlen($caracteres);
    do {
        $serial = '';
        for ($i = 0; $i < 9; $i++) {
            $serial .= $caracteres[random_int(0, $len - 1)];
        }
        $stmt = $conn->prepare('SELECT 1 FROM Licencias WHERE Serie = ?');
        $stmt->bind_param('s', $serial);
        $stmt->execute();
        $result = $stmt->get_result();
    } while ($result->num_rows > 0);
    $stmt->close();
    return $serial;
}
