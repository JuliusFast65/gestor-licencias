<?php
// index.php - Página principal del sistema
// Muestra login si no hay sesión, y menú principal si el usuario está autenticado

require_once(__DIR__ . '/php/auth.php');

// Si el usuario está autenticado, mostrar menú principal
$usuario = $_SESSION['usuario'] ?? null;
$nombre = $_SESSION['nombre'] ?? $usuario;
$perfil = $_SESSION['perfil'] ?? '';

if (!$usuario) {
    // El login ya se muestra automáticamente por auth.php si no hay sesión
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Menú Principal - Gestor de Licencias</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
        .header { background: #343a40; color: #fff; padding: 20px; text-align: right; }
        .header strong { color: #ffc107; }
        .menu-container { max-width: 500px; margin: 60px auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 40px 30px; text-align: center; }
        .menu-container h1 { margin-bottom: 30px; color: #333; }
        .menu-btn { display: block; width: 100%; margin: 18px 0; padding: 18px; font-size: 1.2em; background: #007bff; color: #fff; border: none; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background 0.2s; }
        .menu-btn:hover { background: #0056b3; }
        .logout-link { color: #dc3545; text-decoration: none; font-size: 1em; margin-top: 30px; display: inline-block; }
        .logout-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">
        Bienvenido, <strong><?= htmlspecialchars($nombre) ?></strong> (<?= htmlspecialchars($perfil) ?>)
        | <a href="?Accion=Logout" class="logout-link">Cerrar Sesión</a>
    </div>
    <div class="menu-container">
        <h1>Menú Principal</h1>
        <a href="Consultar.php" class="menu-btn">Gestión de Licencias</a>
        <a href="reporte_licencias.php" class="menu-btn">Reporte de Licencias</a>
        <a href="reporte_empresas.php" class="menu-btn">Reporte de Empresas</a>
        <!-- Aquí puedes agregar más opciones de menú en el futuro -->
    </div>
</body>
</html> 