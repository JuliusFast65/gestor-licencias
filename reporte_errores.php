<?php
require_once(__DIR__ . '/php/auth.php');
require_once(__DIR__ . '/apis/Conectar_BD.php');

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario) {
    exit;
}

$conn = Conectar_BD();

// Filtros
$filtro_ruc = $_GET['ruc'] ?? '';
$filtro_empresa = $_GET['empresa'] ?? '';
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_fecha_rapida = $_GET['fecha_rapida'] ?? 'hoy';
$filtro_desde = $_GET['desde'] ?? '';
$filtro_hasta = $_GET['hasta'] ?? '';

// Calcular fechas según filtro rápido
$hoy = date('Y-m-d');
$primer_dia_semana = date('Y-m-d', strtotime('monday this week'));
$primer_dia_mes = date('Y-m-01');
$semana_pasada = date('Y-m-d', strtotime('monday last week'));
$mes_pasado = date('Y-m-01', strtotime('first day of last month'));
$ultimo_dia_mes_pasado = date('Y-m-t', strtotime('last month'));

switch ($filtro_fecha_rapida) {
    case 'hoy':
        $filtro_desde = $hoy;
        $filtro_hasta = $hoy;
        break;
    case 'semana':
        $filtro_desde = $primer_dia_semana;
        $filtro_hasta = $hoy;
        break;
    case 'mes':
        $filtro_desde = $primer_dia_mes;
        $filtro_hasta = $hoy;
        break;
    case 'semana_pasada':
        $filtro_desde = $semana_pasada;
        $filtro_hasta = $hoy;
        break;
    case 'mes_pasado':
        $filtro_desde = $mes_pasado;
        $filtro_hasta = $ultimo_dia_mes_pasado;
        break;
    case 'personalizado':
        // Usar los valores que el usuario haya puesto
        break;
    default:
        $filtro_desde = $hoy;
        $filtro_hasta = $hoy;
}

$sql = "SELECT Fecha, RUC, Empresa, Usuario, Version, Fuente, Linea, ErrorNum, Error, Programa FROM Errores WHERE 1=1";
$params = [];
$types = '';

if (!empty($filtro_ruc)) {
    $sql .= " AND RUC LIKE ?";
    $params[] = "%$filtro_ruc%";
    $types .= 's';
}
if (!empty($filtro_empresa)) {
    $sql .= " AND Empresa LIKE ?";
    $params[] = "%$filtro_empresa%";
    $types .= 's';
}
if (!empty($filtro_usuario)) {
    $sql .= " AND Usuario LIKE ?";
    $params[] = "%$filtro_usuario%";
    $types .= 's';
}
if (!empty($filtro_desde)) {
    $sql .= " AND DATE(Fecha) >= ?";
    $params[] = $filtro_desde;
    $types .= 's';
}
if (!empty($filtro_hasta)) {
    $sql .= " AND DATE(Fecha) <= ?";
    $params[] = $filtro_hasta;
    $types .= 's';
}
$sql .= " ORDER BY Fecha DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$errores = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Errores ERP - Básico</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
        .header { background: #343a40; color: #fff; padding: 20px; text-align: right; }
        .header a { color: #ffc107; text-decoration: none; margin-left: 20px; }
        .header a:hover { text-decoration: underline; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 40px 30px; }
        h1 { color: #333; margin-bottom: 30px; }
        .filtros { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .filtros form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filtro-grupo { display: flex; flex-direction: column; }
        .filtro-grupo label { font-weight: bold; margin-bottom: 5px; }
        .filtro-grupo input, .filtro-grupo select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; background: #007bff; color: #fff; }
        .btn:hover { opacity: 0.8; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 1em; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #007bff; color: #fff; }
        tr:nth-child(even) { background: #f2f2f2; }
    </style>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>
    <div class="header">
        <a href="index.php">&larr; Volver al Menú Principal</a>
    </div>
    <div class="container">
        <h1>Reporte de Errores ERP (Básico)</h1>
        <div class="filtros">
            <form method="GET" id="form-filtros">
                <div class="filtro-grupo">
                    <label for="fecha_rapida">Rango rápido:</label>
                    <select id="fecha_rapida" name="fecha_rapida" onchange="actualizarFechas()">
                        <option value="hoy" <?= $filtro_fecha_rapida === 'hoy' ? 'selected' : '' ?>>Hoy</option>
                        <option value="semana" <?= $filtro_fecha_rapida === 'semana' ? 'selected' : '' ?>>Esta semana</option>
                        <option value="mes" <?= $filtro_fecha_rapida === 'mes' ? 'selected' : '' ?>>Este mes</option>
                        <option value="semana_pasada" <?= $filtro_fecha_rapida === 'semana_pasada' ? 'selected' : '' ?>>Desde la semana pasada</option>
                        <option value="mes_pasado" <?= $filtro_fecha_rapida === 'mes_pasado' ? 'selected' : '' ?>>Desde el mes pasado</option>
                        <option value="personalizado" <?= $filtro_fecha_rapida === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                    </select>
                </div>
                <div class="filtro-grupo">
                    <label for="desde">Desde:</label>
                    <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($filtro_desde) ?>" <?= $filtro_fecha_rapida !== 'personalizado' ? 'readonly' : '' ?> >
                </div>
                <div class="filtro-grupo">
                    <label for="hasta">Hasta:</label>
                    <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($filtro_hasta) ?>" <?= $filtro_fecha_rapida !== 'personalizado' ? 'readonly' : '' ?> >
                </div>
                <button type="submit" class="btn">Filtrar</button>
                <a href="reporte_errores.php" class="btn">Limpiar</a>
            </form>
        </div>
        <table id="tabla-errores">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>RUC</th>
                    <th>Empresa</th>
                    <th>Usuario</th>
                    <th>Versión</th>
                    <th>Fuente</th>
                    <th>Línea</th>
                    <th>Número</th>
                    <th>Error</th>
                    <th>Programa</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($errores)): ?>
                    <tr><td colspan="10" style="text-align:center; color:#888;">No hay errores que mostrar.</td></tr>
                <?php else: ?>
                    <?php foreach ($errores as $err): ?>
                        <tr>
                            <td><?= $err['Fecha'] ? date('d/m/Y H:i', strtotime($err['Fecha'])) : '-' ?></td>
                            <td><?= htmlspecialchars($err['RUC']) ?></td>
                            <td><?= htmlspecialchars($err['Empresa']) ?></td>
                            <td><?= htmlspecialchars($err['Usuario']) ?></td>
                            <td><?= htmlspecialchars($err['Version']) ?></td>
                            <td><?= htmlspecialchars($err['Fuente']) ?></td>
                            <td><?= htmlspecialchars($err['Linea']) ?></td>
                            <td><?= htmlspecialchars($err['ErrorNum']) ?></td>
                            <td style="white-space: pre-line;" title="<?= htmlspecialchars($err['Error']) ?>">
                                <?= htmlspecialchars($err['Error']) ?>
                            </td>
                            <td style="white-space: pre-line;" title="<?= htmlspecialchars($err['Programa']) ?>">
                                <?= htmlspecialchars($err['Programa']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    function actualizarFechas() {
        var filtro = document.getElementById('fecha_rapida').value;
        var hoy = new Date();
        var yyyy = hoy.getFullYear();
        var mm = (hoy.getMonth() + 1).toString().padStart(2, '0');
        var dd = hoy.getDate().toString().padStart(2, '0');
        var primerDiaSemana = new Date(hoy.setDate(hoy.getDate() - hoy.getDay() + 1));
        var primerDiaMes = yyyy + '-' + mm + '-01';
        var semanaPasada = new Date(hoy.setDate(hoy.getDate() - hoy.getDay() - 6));
        var mesPasado = new Date(yyyy, hoy.getMonth() - 1, 1);
        var ultimoDiaMesPasado = new Date(yyyy, hoy.getMonth(), 0);
        if (filtro === 'personalizado') {
            document.getElementById('desde').readOnly = false;
            document.getElementById('hasta').readOnly = false;
        } else {
            document.getElementById('desde').readOnly = true;
            document.getElementById('hasta').readOnly = true;
        }
    }
    $(document).ready(function() {
        // Filtros en cabecera y ordenamiento
        $('#tabla-errores thead tr').clone(true).appendTo('#tabla-errores thead');
        $('#tabla-errores thead tr:eq(1) th').each(function(i) {
            $(this).html('<input type="text" placeholder="Filtrar" style="width: 100%"/>' );
            $('input', this).on('keyup change', function() {
                if (table.column(i).search() !== this.value) {
                    table.column(i).search(this.value).draw();
                }
            });
        });
        var table = $('#tabla-errores').DataTable({
            orderCellsTop: true,
            fixedHeader: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            }
        });
        actualizarFechas();
    });
    </script>
</body>
</html> 