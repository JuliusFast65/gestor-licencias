<?php
// reporte_licencias.php - Página base para el reporte de licencias
require_once(__DIR__ . '/php/auth.php');
require_once(__DIR__ . '/apis/Conectar_BD.php');

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario) {
    exit; // El login se maneja en auth.php
}

// Conexión a la base de datos
$conn = Conectar_BD();

// Procesar filtros
$filtro_empresa = $_GET['empresa'] ?? '';
$filtro_sistema = $_GET['sistema'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

// Construir consulta con filtros
$sql = "
    SELECT e.Nombre AS Empresa, l.Ruc, l.Sistema, l.Serie, l.Tipo_Licencia, l.Usuario, l.Ultimo_Acceso
    FROM Licencias l
    LEFT JOIN Empresas e ON l.Ruc = e.Ruc
    WHERE l.Baja IS NULL
";

$params = [];
$types = '';

if (!empty($filtro_empresa)) {
    $sql .= " AND e.Nombre LIKE ?";
    $params[] = "%$filtro_empresa%";
    $types .= 's';
}

if (!empty($filtro_sistema)) {
    $sql .= " AND l.Sistema = ?";
    $params[] = $filtro_sistema;
    $types .= 's';
}

if (!empty($filtro_estado)) {
    if ($filtro_estado === 'activa') {
        $sql .= " AND l.Ultimo_Acceso >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($filtro_estado === 'inactiva') {
        $sql .= " AND (l.Ultimo_Acceso < DATE_SUB(NOW(), INTERVAL 30 DAY) OR l.Ultimo_Acceso IS NULL)";
    }
}

$sql .= " ORDER BY e.Nombre, l.Sistema, l.Serie";

// Ejecutar consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$licencias = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Calcular contadores
$total_licencias = count($licencias);
$contador_sistemas = [];
$contador_estados = ['activa' => 0, 'inactiva' => 0];

foreach ($licencias as $lic) {
    // Contador por sistema
    $sistema = $lic['Sistema'];
    $contador_sistemas[$sistema] = ($contador_sistemas[$sistema] ?? 0) + 1;
    
    // Contador por estado
    $ultimo_acceso = $lic['Ultimo_Acceso'];
    if ($ultimo_acceso && strtotime($ultimo_acceso) >= strtotime('-30 days')) {
        $contador_estados['activa']++;
    } else {
        $contador_estados['inactiva']++;
    }
}

// Obtener empresas para el filtro
$empresas_sql = "SELECT DISTINCT e.Nombre FROM Empresas e INNER JOIN Licencias l ON e.Ruc = l.Ruc WHERE l.Baja IS NULL ORDER BY e.Nombre";
$empresas_result = $conn->query($empresas_sql);
$empresas = $empresas_result ? $empresas_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();

function tipoLicenciaLegible($tipo) {
    // Puedes personalizar según tus tipos de licencia
    $tipos = [
        1 => 'Básica',
        2 => 'Nómina',
        3 => 'Comprobantes',
        4 => 'Activos Fijos',
        5 => 'Producción',
        // ...
    ];
    return $tipos[$tipo] ?? $tipo;
}

// Exportar a Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="reporte_licencias_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "Empresa\tRUC\tSistema\tSerie\tTipo\tUsuario\tÚltimo Acceso\n";
    foreach ($licencias as $lic) {
        echo implode("\t", [
            $lic['Empresa'] ?? 'Desconocida',
            $lic['Ruc'],
            $lic['Sistema'],
            $lic['Serie'],
            tipoLicenciaLegible($lic['Tipo_Licencia']),
            $lic['Usuario'],
            $lic['Ultimo_Acceso'] ? date('d/m/Y H:i', strtotime($lic['Ultimo_Acceso'])) : 'N/A'
        ]) . "\n";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Licencias</title>
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
        .back-link { display: inline-block; margin-top: 30px; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
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
        <h1>Reporte de Licencias</h1>
        
        <!-- Contadores -->
        <div class="contadores">
            <div class="contador">
                <div class="numero"><?= $total_licencias ?></div>
                <div class="label">Total</div>
            </div>
            <?php foreach ($contador_sistemas as $sistema => $cantidad): ?>
                <div class="contador">
                    <div class="numero"><?= $cantidad ?></div>
                    <div class="label"><?= $sistema ?></div>
                </div>
            <?php endforeach; ?>
            <div class="contador">
                <div class="numero"><?= $contador_estados['activa'] ?></div>
                <div class="label">Activas</div>
            </div>
            <div class="contador">
                <div class="numero"><?= $contador_estados['inactiva'] ?></div>
                <div class="label">Inactivas</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <form method="GET">
                <div class="filtro-grupo">
                    <label for="empresa">Empresa:</label>
                    <input type="text" id="empresa" name="empresa" value="<?= htmlspecialchars($filtro_empresa ?? '') ?>" placeholder="Buscar empresa...">
                </div>
                <div class="filtro-grupo">
                    <label for="sistema">Sistema:</label>
                    <select id="sistema" name="sistema">
                        <option value="">Todos</option>
                        <option value="FSOFT" <?= $filtro_sistema === 'FSOFT' ? 'selected' : '' ?>>FSOFT</option>
                        <option value="LSOFT" <?= $filtro_sistema === 'LSOFT' ? 'selected' : '' ?>>LSOFT</option>
                        <option value="LSOFTW" <?= $filtro_sistema === 'LSOFTW' ? 'selected' : '' ?>>LSOFTW</option>
                    </select>
                </div>
                <div class="filtro-grupo">
                    <label for="estado">Estado:</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="activa" <?= $filtro_estado === 'activa' ? 'selected' : '' ?>>Activa (últimos 30 días)</option>
                        <option value="inactiva" <?= $filtro_estado === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="reporte_licencias.php" class="btn btn-primary">Limpiar</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-success">Exportar a Excel</a>
            </form>
        </div>
        
        <table id="tabla-licencias">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>RUC</th>
                    <th>Sistema</th>
                    <th>Serie</th>
                    <th>Tipo</th>
                    <th>Usuario</th>
                    <th>Último Acceso</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($licencias)): ?>
                    <tr><td colspan="7" style="text-align:center; color:#888;">No hay licencias que coincidan con los filtros.</td></tr>
                <?php else: ?>
                    <?php foreach ($licencias as $lic): ?>
                        <tr>
                            <td><?= htmlspecialchars($lic['Empresa'] ?? 'Desconocida') ?></td>
                            <td><a href="Consultar.php?Ruc=<?= urlencode($lic['Ruc'] ?? '') ?>" class="ruc-link"><?= htmlspecialchars($lic['Ruc'] ?? '') ?></a></td>
                            <td><?= htmlspecialchars($lic['Sistema'] ?? '') ?></td>
                            <td><?= htmlspecialchars($lic['Serie'] ?? '') ?></td>
                            <td><?= htmlspecialchars(tipoLicenciaLegible($lic['Tipo_Licencia'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($lic['Usuario'] ?? '') ?></td>
                            <td><?= $lic['Ultimo_Acceso'] ? date('d/m/Y H:i', strtotime($lic['Ultimo_Acceso'])) : 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    $(document).ready(function() {
        // Clona la fila de cabecera para los filtros
        $('#tabla-licencias thead tr').clone(true).appendTo('#tabla-licencias thead');
        $('#tabla-licencias thead tr:eq(1) th').each(function(i) {
            $(this).html('<input type="text" placeholder="Filtrar" style="width: 100%"/>' );
            $('input', this).on('keyup change', function() {
                if (table.column(i).search() !== this.value) {
                    table.column(i).search(this.value).draw();
                }
            });
        });
        var table = $('#tabla-licencias').DataTable({
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