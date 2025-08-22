<?php
// Test page para debug del conteo de licencias
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once(__DIR__ . '/apis/Conectar_BD.php');
require_once(__DIR__ . '/php/companies.php');

$conn = Conectar_BD();

// Usar un RUC de prueba - puedes cambiar esto
$Ruc = '0992671661001'; // RUC de ejemplo

echo "<h1>Debug de Conteo de Licencias</h1>";
echo "<p>RUC: $Ruc</p>";

// Obtener datos de la empresa
$empresa = obtenerRegistroEmpresa($conn, $Ruc);
if (!$empresa) {
    echo "<p style='color: red;'>No se encontró la empresa con RUC: $Ruc</p>";
    exit;
}

echo "<h2>Datos de la Empresa</h2>";
echo "<pre>";
print_r($empresa);
echo "</pre>";

// Obtener sesiones activas
$sql = "SELECT tipo, COUNT(DISTINCT Serie) AS total
        FROM sesiones_erp
        WHERE Ruc = ?
        GROUP BY tipo";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $Ruc);
$stmt->execute();
$result = $stmt->get_result();
$conteoSesiones = [];
while ($row = $result->fetch_assoc()) {
    $conteoSesiones[$row['tipo']] = (int)$row['total'];
}

echo "<h2>Conteo de Sesiones por Tipo</h2>";
echo "<pre>";
print_r($conteoSesiones);
echo "</pre>";

// Obtener todas las sesiones para ver el detalle
$sql = "SELECT Serie, usuario, tipo, ultima_actividad 
        FROM sesiones_erp 
        WHERE Ruc = ? 
        ORDER BY ultima_actividad DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $Ruc);
$stmt->execute();
$sesionesActivas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h2>Sesiones Activas (Detalle)</h2>";
echo "<pre>";
print_r($sesionesActivas);
echo "</pre>";

// Obtener licencias
$sql = "SELECT * FROM Licencias 
        WHERE Ruc=? AND (Sistema='FSOFT' OR Sistema='LSOFT') 
        ORDER BY Sistema, Serie";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $Ruc);
$stmt->execute();
$licencias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h2>Licencias</h2>";
echo "<pre>";
print_r($licencias);
echo "</pre>";

// Probar la función calcularEstadisticasLicencias
require_once(__DIR__ . '/Consultar.php');

echo "<h2>Estadísticas FSOFT</h2>";
$statsFsoft = calcularEstadisticasLicencias($licencias, $empresa, 'FSOFT', $sesionesActivas, $conn);
echo "<pre>";
print_r($statsFsoft);
echo "</pre>";

echo "<h2>Estadísticas LSOFT</h2>";
$statsLsoft = calcularEstadisticasLicencias($licencias, $empresa, 'LSOFT', $sesionesActivas, $conn);
echo "<pre>";
print_r($statsLsoft);
echo "</pre>";

echo "<h2>Estadísticas LSOFTW</h2>";
$statsLsoftWeb = calcularEstadisticasLicencias($licencias, $empresa, 'LSOFTW', $sesionesActivas, $conn);
echo "<pre>";
print_r($statsLsoftWeb);
echo "</pre>";
?> 