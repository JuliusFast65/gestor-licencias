<?php
session_start();

// Guardián: si no hay sesión, se va al login
if (!isset($_SESSION['pk_usuario'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ddd; padding-bottom: 1rem; }
        .header h1 { margin: 0; }
        .header a { text-decoration: none; background-color: #dc3545; color: white; padding: 0.5rem 1rem; border-radius: 5px; }
        .nav { margin-top: 2rem; }
        .nav a { display: inline-block; background-color: #007bff; color: white; padding: 1rem; border-radius: 5px; text-decoration: none; margin-right: 1rem; }
        .nav a:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h1>
        <a href="login.php?accion=logout">Cerrar Sesión</a>
    </div>

    <div class="nav">
        <a href="Consultar.php">Módulo de Licencias</a>
        <a href="">Reportes varios</a>
        
        <?php
        // CONTROL DE ACCESO: Solo el presidente ve este enlace
        if (isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'presidente') {
            echo '<a href="gestion_usuarios.php" style="background-color: #28a745;">Gestión de Usuarios</a>';
        }
        ?>
    </div>
    
    <p>Tu perfil es: <strong><?php echo htmlspecialchars(ucfirst($_SESSION['perfil'])); ?></strong>.</p>
    <p>Utiliza la navegación para acceder a los módulos de la aplicación.</p>

</body>
</html>