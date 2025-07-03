<?php
// reporte_empresas.php - Reporte de empresas
require_once(__DIR__ . '/php/auth.php');
require_once(__DIR__ . '/apis/Conectar_BD.php');

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario) {
    exit; // El login se maneja en auth.php
}

// Conexión a la base de datos
$conn = Conectar_BD();

// Procesar filtros
$filtro_nombre = $_GET['nombre'] ?? '';
$filtro_estado_suscripcion = $_GET['estado_suscripcion'] ?? '';
$filtro_actividad = $_GET['actividad'] ?? '';

// Construir consulta con filtros
$sql = "
    SELECT 
        e.Ruc,
        e.Nombre,
        e.Inicio_Suscripcion,
        e.Fin_Suscripcion,
        e.Cant_Lic_FSOFT_BA,
        e.Cant_Lic_LSOFT_BA,
        e.Version_FSoft,
        e.Version_LSoft,
        e.Version_LSoft_Web,
        COUNT(l.PK_Licencia) as Licencias_Activas,
        MAX(l.Ultimo_Acceso) as Ultima_Actividad
    FROM Empresas e
    LEFT JOIN Licencias l ON e.Ruc = l.Ruc AND l.Baja IS NULL
";

$params = [];
$types = '';

if (!empty($filtro_nombre)) {
    $sql .= " AND e.Nombre LIKE ?";
    $params[] = "%$filtro_nombre%";
    $types .= 's';
}

$sql .= " GROUP BY e.Ruc, e.Nombre, e.Inicio_Suscripcion, e.Fin_Suscripcion, e.Cant_Lic_FSOFT_BA, e.Cant_Lic_LSOFT_BA, e.Version_FSoft, e.Version_LSoft, e.Version_LSoft_Web";

if (!empty($filtro_estado_suscripcion)) {
    if ($filtro_estado_suscripcion === 'activa') {
        $sql .= " HAVING e.Fin_Suscripcion >= CURDATE()";
    } elseif ($filtro_estado_suscripcion === 'vencida') {
        $sql .= " HAVING e.Fin_Suscripcion < CURDATE()";
    } elseif ($filtro_estado_suscripcion === 'por_vencer') {
        $sql .= " HAVING e.Fin_Suscripcion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
}

if (!empty($filtro_actividad)) {
    if ($filtro_actividad === 'activa') {
        $sql .= " HAVING Ultima_Actividad >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($filtro_actividad === 'inactiva') {
        $sql .= " HAVING (Ultima_Actividad < DATE_SUB(NOW(), INTERVAL 30 DAY) OR Ultima_Actividad IS NULL)";
    }
}

$sql .= " ORDER BY e.Nombre";

// Ejecutar consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$empresas = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Calcular contadores
$total_empresas = count($empresas);
$contador_suscripciones = ['activa' => 0, 'vencida' => 0, 'por_vencer' => 0];
$contador_actividad = ['activa' => 0, 'inactiva' => 0];
$total_licencias_compradas = 0;
$total_licencias_activas = 0;

foreach ($empresas as $emp) {
    // Contador por estado de suscripción
    $fin_suscripcion = $emp['Fin_Suscripcion'];
    if ($fin_suscripcion) {
        $fecha_fin = new DateTime($fin_suscripcion);
        $hoy = new DateTime();
        $dias_restantes = $hoy->diff($fecha_fin)->days;
        
        if ($fecha_fin < $hoy) {
            $contador_suscripciones['vencida']++;
        } elseif ($dias_restantes <= 30) {
            $contador_suscripciones['por_vencer']++;
        } else {
            $contador_suscripciones['activa']++;
        }
    }
    
    // Contador por actividad
    $ultima_actividad = $emp['Ultima_Actividad'];
    if ($ultima_actividad && strtotime($ultima_actividad) >= strtotime('-30 days')) {
        $contador_actividad['activa']++;
    } else {
        $contador_actividad['inactiva']++;
    }
    
    // Totales de licencias
    $total_licencias_compradas += ($emp['Cant_Lic_FSOFT_BA'] ?? 0) + ($emp['Cant_Lic_LSOFT_BA'] ?? 0);
    $total_licencias_activas += $emp['Licencias_Activas'] ?? 0;
}

$conn->close();

function estadoSuscripcion($fin_suscripcion) {
    if (!$fin_suscripcion) return 'Sin fecha';
    
    $fecha_fin = new DateTime($fin_suscripcion);
    $hoy = new DateTime();
    $dias_restantes = $hoy->diff($fecha_fin)->days;
    
    if ($fecha_fin < $hoy) {
        return '<span style="color: #dc3545; font-weight: bold;">Vencida</span>';
    } elseif ($dias_restantes <= 30) {
        return '<span style="color: #ffc107; font-weight: bold;">Por vencer (' . $dias_restantes . ' días)</span>';
    } else {
        return '<span style="color: #28a745; font-weight: bold;">Activa</span>';
    }
}

function estadoActividad($ultima_actividad) {
    if (!$ultima_actividad) return '<span style="color: #6c757d;">Sin actividad</span>';
    
    if (strtotime($ultima_actividad) >= strtotime('-30 days')) {
        return '<span style="color: #28a745;">Activa</span>';
    } else {
        return '<span style="color: #dc3545;">Inactiva</span>';
    }
}

// Exportar a Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="reporte_empresas_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "Empresa\tRUC\tSuscripción\tEstado Suscripción\tLicencias Compradas\tLicencias Activas\tÚltima Actividad\tEstado Actividad\n";
    foreach ($empresas as $emp) {
        echo implode("\t", [
            $emp['Nombre'],
            $emp['Ruc'],
            $emp['Fin_Suscripcion'] ? date('d/m/Y', strtotime($emp['Fin_Suscripcion'])) : 'Sin fecha',
            strip_tags(estadoSuscripcion($emp['Fin_Suscripcion'])),
            ($emp['Cant_Lic_FSOFT_BA'] ?? 0) + ($emp['Cant_Lic_LSOFT_BA'] ?? 0),
            $emp['Licencias_Activas'] ?? 0,
            $emp['Ultima_Actividad'] ? date('d/m/Y H:i', strtotime($emp['Ultima_Actividad'])) : 'N/A',
            strip_tags(estadoActividad($emp['Ultima_Actividad']))
        ]) . "\n";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Empresas</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
        .header { background: #343a40; color: #fff; padding: 20px; text-align: right; }
        .header a { color: #ffc107; text-decoration: none; margin-left: 20px; }
        .header a:hover { text-decoration: underline; }
        .container { max-width: 1200px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 40px 30px; }
        h1 { color: #333; margin-bottom: 30px; }
        .contadores { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .contador { background: #e9ecef; padding: 15px; border-radius: 5px; text-align: center; min-width: 120px; }
        .contador .numero { font-size: 2em; font-weight: bold; color: #007bff; }
        .contador .label { font-size: 0.9em; color: #6c757d; }
        .filtros { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .filtros form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filtro-grupo { display: flex; flex-direction: column; }
        .filtro-grupo label { font-weight: bold; margin-bottom: 5px; }
        .filtro-grupo input, .filtro-grupo select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary { background: #007bff; color: #fff; }
        .btn-success { background: #28a745; color: #fff; }
        .btn:hover { opacity: 0.8; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #007bff; color: #fff; }
        tr:nth-child(even) { background: #f2f2f2; }
        .ruc-link { color: #007bff; text-decoration: none; font-weight: bold; }
        .ruc-link:hover { text-decoration: underline; }
    </style>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>
    <div class="header">
        <a href="index.php">&larr; Volver al Menú Principal</a>
    </div>
    <div class="container">
        <h1>Reporte de Empresas</h1>
        
        <!-- Contadores -->
        <div class="contadores">
            <div class="contador">
                <div class="numero"><?= $total_empresas ?></div>
                <div class="label">Total Empresas</div>
            </div>
            <div class="contador">
                <div class="numero"><?= $contador_suscripciones['activa'] ?></div>
                <div class="label">Suscripciones Activas</div>
            </div>
            <div class="contador">
                <div class="numero"><?= $contador_suscripciones['por_vencer'] ?></div>
                <div class="label">Por Vencer</div>
            </div>
            <div class="contador">
                <div class="numero"><?= $contador_suscripciones['vencida'] ?></div>
                <div class="label">Vencidas</div>
            </div>
            <div class="contador">
                <div class="numero"><?= $contador_actividad['activa'] ?></div>
                <div class="label">Con Actividad</div>
            </div>
            <div class="contador">
                <div class="numero"><?= $total_licencias_compradas ?></div>
                <div class="label">Licencias Compradas</div>
            </div>
            <div class="contador">
                <div class="numero"><?= $total_licencias_activas ?></div>
                <div class="label">Licencias Activas</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <form method="GET">
                <div class="filtro-grupo">
                    <label for="nombre">Empresa:</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($filtro_nombre) ?>" placeholder="Buscar empresa...">
                </div>
                <div class="filtro-grupo">
                    <label for="estado_suscripcion">Estado Suscripción:</label>
                    <select id="estado_suscripcion" name="estado_suscripcion">
                        <option value="">Todos</option>
                        <option value="activa" <?= $filtro_estado_suscripcion === 'activa' ? 'selected' : '' ?>>Activa</option>
                        <option value="por_vencer" <?= $filtro_estado_suscripcion === 'por_vencer' ? 'selected' : '' ?>>Por vencer (30 días)</option>
                        <option value="vencida" <?= $filtro_estado_suscripcion === 'vencida' ? 'selected' : '' ?>>Vencida</option>
                    </select>
                </div>
                <div class="filtro-grupo">
                    <label for="actividad">Actividad:</label>
                    <select id="actividad" name="actividad">
                        <option value="">Todas</option>
                        <option value="activa" <?= $filtro_actividad === 'activa' ? 'selected' : '' ?>>Con actividad (30 días)</option>
                        <option value="inactiva" <?= $filtro_actividad === 'inactiva' ? 'selected' : '' ?>>Sin actividad</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="reporte_empresas.php" class="btn btn-primary">Limpiar</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-success">Exportar a Excel</a>
            </form>
        </div>
        
        <table id="tabla-empresas">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>RUC</th>
                    <th>Fin Suscripción</th>
                    <th>Estado Suscripción</th>
                    <th>Licencias Compradas</th>
                    <th>Licencias Activas</th>
                    <th>Última Actividad</th>
                    <th>Estado Actividad</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($empresas)): ?>
                    <tr><td colspan="8" style="text-align:center; color:#888;">No hay empresas que coincidan con los filtros.</td></tr>
                <?php else: ?>
                    <?php foreach ($empresas as $emp): ?>
                        <tr>
                            <td><?= htmlspecialchars($emp['Nombre']) ?></td>
                            <td><a href="Consultar.php?Ruc=<?= urlencode($emp['Ruc']) ?>" class="ruc-link"><?= htmlspecialchars($emp['Ruc']) ?></a></td>
                            <td><?= $emp['Fin_Suscripcion'] ? date('d/m/Y', strtotime($emp['Fin_Suscripcion'])) : 'Sin fecha' ?></td>
                            <td><?= estadoSuscripcion($emp['Fin_Suscripcion']) ?></td>
                            <td><?= ($emp['Cant_Lic_FSOFT_BA'] ?? 0) + ($emp['Cant_Lic_LSOFT_BA'] ?? 0) ?></td>
                            <td><?= $emp['Licencias_Activas'] ?? 0 ?></td>
                            <td><?= $emp['Ultima_Actividad'] ? date('d/m/Y H:i', strtotime($emp['Ultima_Actividad'])) : 'N/A' ?></td>
                            <td><?= estadoActividad($emp['Ultima_Actividad']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    $(document).ready(function() {
        // Clona la fila de cabecera para los filtros
        $('#tabla-empresas thead tr').clone(true).appendTo('#tabla-empresas thead');
        $('#tabla-empresas thead tr:eq(1) th').each(function(i) {
            $(this).html('<input type="text" placeholder="Filtrar" style="width: 100%"/>' );
            $('input', this).on('keyup change', function() {
                if (table.column(i).search() !== this.value) {
                    table.column(i).search(this.value).draw();
                }
            });
        });
        var table = $('#tabla-empresas').DataTable({
            orderCellsTop: true,
            fixedHeader: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            }
        });
    });
    </script>
</body>
</html> 